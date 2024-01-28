<?php

declare(strict_types=1);

namespace T3thi\Transfusion\Controller;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Controller responsible for the disconnection of translated records and their parents
 */
class TransfusionController
{
    protected readonly PageRenderer $pageRenderer;
    protected array $dataMap = [];
    protected DataHandler $dataHandler;
    protected bool $missingInformation = false;
    public function __construct()
    {
        $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function connectAction(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['connect'])) {
            foreach ($queryParams['connect'] as $page => $disconnections) {
                if (!empty($disconnections)) {
                    foreach ($disconnections as $language => $tables) {
                        if (!empty($tables)) {
                            foreach ($tables as $table) {
                                $transFusionFields = $this->checkTranslationFields($table, 'connect');
                                $this->fetchDisconnectedRecordsAndPrepareDataMap($table, $language, $page, $transFusionFields);
                            }
                        }
                    }
                }
            }
        }

        if (!$this->missingInformation) {
            $this->executeDataHandler();
            if (!empty($queryParams['redirect'])) {
                return new RedirectResponse(GeneralUtility::locationHeaderUrl($queryParams['redirect']), 303);
            }
        }

        return $this->pageRenderer->renderResponse();
    }

    /**
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     * @throws Exception
     */
    public function disconnectAction(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams['disconnect'])) {
            foreach ($queryParams['disconnect'] as $page => $disconnections) {
                if (!empty($disconnections)) {
                    foreach ($disconnections as $language => $tables) {
                        if (!empty($tables)) {
                            foreach ($tables as $table) {
                                $transFusionFields = $this->checkTranslationFields($table, 'disconnect');
                                $this->fetchConnectedRecordsAndPrepareDataMap($table, $language, $page, $transFusionFields);
                            }
                        }
                    }
                }
            }
        }

        $this->executeDataHandler();

        if (!empty($queryParams['redirect'])) {
            return new RedirectResponse(GeneralUtility::locationHeaderUrl($queryParams['redirect']), 303);
        } else {
            return $this->pageRenderer->renderResponse();
        }
    }

    /**
     * @param string $table
     * @param int $language
     * @param int $page
     * @param array $transFusionFields
     * @throws Exception
     */
    protected function fetchConnectedRecordsAndPrepareDataMap(string $table, int $language, int $page, array $transFusionFields): void
    {
        $this->dataMap[$table] = [];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
                ->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $connectedRecords = $queryBuilder
                ->select('uid',$transFusionFields['source'],$transFusionFields['original'])
                ->from($table)
                ->where(
                        $queryBuilder->expr()->eq('pid', $page),
                        $queryBuilder->expr()->eq($transFusionFields['language'], $language),
                        $queryBuilder->expr()->gt($transFusionFields['parent'], 0)
                )
                ->executeQuery();
        while ($record = $connectedRecords->fetchAssociative()) {
            $this->dataMap[$table][$record['uid']][$transFusionFields['parent']] = 0;
            if (empty($record[$transFusionFields['source']])) {
                $this->dataMap[$table][$record['uid']][$transFusionFields['source']] = $record[$transFusionFields['original']];
            }
        }
    }

    /**
     * @param string $table
     * @param int $language
     * @param int $page
     * @param array $transFusionFields
     * @return void
     * @throws Exception
     */
    protected function fetchDisconnectedRecordsAndPrepareDataMap(string $table, int $language, int $page, array $transFusionFields): void
    {
        $this->dataMap[$table] = [];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
                ->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $connectedRecords = $queryBuilder
                ->select('uid',$transFusionFields['source'],$transFusionFields['original'])
                ->from($table)
                ->where(
                        $queryBuilder->expr()->eq('pid', $page),
                        $queryBuilder->expr()->eq($transFusionFields['language'], $language),
                        $queryBuilder->expr()->eq($transFusionFields['parent'], 0)
                )
                ->executeQuery();
        while ($record = $connectedRecords->fetchAssociative()) {
            if (
                !empty($record[$transFusionFields['source']])
                && !empty($record[$transFusionFields['original']])
                && $record[$transFusionFields['source']] === $record[$transFusionFields['original']]
            ) {
                $originalRecord = BackendUtility::getRecord($table, $record[$transFusionFields['original']], '*');
                if (empty($originalRecord['sys_language_uid'])) {
                    $this->dataMap[$table][$record['uid']][$transFusionFields['parent']] = $record[$transFusionFields['original']];
                } else {
                    $this->missingInformation = true;
            }
            } else {
                $this->missingInformation = true;
            }
            if ($this->missingInformation) {
                // DebugUtility::debug($record);
            }
        }
    }

    /**
     * @param string $table
     * @param string $action
     * @return array
     */
    protected function checkTranslationFields(string $table, string $action): array {
        $transFusionFields  = [];
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
        $translationParent = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
        $translationSource = $GLOBALS['TCA'][$table]['ctrl']['translationSource'];
        $origUid = $GLOBALS['TCA'][$table]['ctrl']['origUid'];
        if (empty($languageField)) {
            throw new InvalidArgumentException(
                    'Table must be translatable and provide a language field. This table can\'t be translated',
                    1706372241
            );
        }
        if (empty($translationParent)) {
            throw new InvalidArgumentException(
                    'Table must be translatable and provide a transOrigPointerField to be ' . $action . 'ed. This table can\'t be ' . $action . 'ed',
                    1706372241
            );
        }
        if (empty($translationSource)) {
            throw new InvalidArgumentException(
                    'Table must be translatable and provide a translationSource to be ' . $action . 'ed. This table can\'t be ' . $action . 'ed',
                    1706372241
            );
        }
        if (empty($origUid)) {
            throw new InvalidArgumentException(
                    'Table must be translatable and provide an origUid to be ' . $action . 'ed. This table can\'t be ' . $action . 'ed',
                    1706372241
            );
        }
        $transFusionFields['language'] = $languageField;
        $transFusionFields['parent'] = $translationParent;
        $transFusionFields['source'] = $translationSource;
        $transFusionFields['original'] = $origUid;
        
        return $transFusionFields;
    }

    /**
     * @return void
     */
    protected function executeDataHandler(): void
    {
        $this->dataHandler->start($this->dataMap, []);
        $this->dataHandler->process_datamap();
    }

}
