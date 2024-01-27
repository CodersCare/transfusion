<?php

declare(strict_types=1);

namespace T3thi\Transfusion\Controller;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
    protected string $languageField;
    protected string $translationParent;
    protected string $translationSource;
    protected string $origUid;

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
                                $this->checkTranslationFields($table, 'connect');
                                $this->fetchDisconnectedRecordsAndPrepareDataMap($table, $language, $page);
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

        DebugUtility::debug($this->dataMap);

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
                                $this->checkTranslationFields($table, 'disconnect');
                                $this->fetchConnectedRecordsAndPrepareDataMap($table, $language, $page);
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
     * @throws Exception
     */
    protected function fetchConnectedRecordsAndPrepareDataMap(string $table, int $language, int $page): void
    {
        $this->dataMap[$table] = [];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
                ->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $connectedRecords = $queryBuilder
                ->select('uid',$this->translationSource,$this->origUid)
                ->from($table)
                ->where(
                        $queryBuilder->expr()->eq('pid', $page),
                        $queryBuilder->expr()->eq($this->languageField, $language),
                        $queryBuilder->expr()->gt($this->translationParent, 0)
                )
                ->executeQuery();
        while ($record = $connectedRecords->fetchAssociative()) {
            $this->dataMap[$table][$record['uid']][$this->translationParent] = 0;
            if (empty($record[$this->translationSource])) {
                $this->dataMap[$table][$record['uid']][$this->translationSource] = $record[$this->origUid];
            }
        }
    }

    /**
     * @param string $table
     * @param int $language
     * @param int $page
     * @return bool
     * @throws Exception
     */
    protected function fetchDisconnectedRecordsAndPrepareDataMap(string $table, int $language, int $page): void
    {
        $this->dataMap[$table] = [];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
                ->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $connectedRecords = $queryBuilder
                ->select('uid',$this->translationSource,$this->origUid)
                ->from($table)
                ->where(
                        $queryBuilder->expr()->eq('pid', $page),
                        $queryBuilder->expr()->eq($this->languageField, $language),
                        $queryBuilder->expr()->eq($this->translationParent, 0)
                )
                ->executeQuery();
        while ($record = $connectedRecords->fetchAssociative()) {
            if (
                !empty($record[$this->translationSource])
                && !empty($record[$this->origUid])
                && $record[$this->translationSource] === $record[$this->origUid]
            ) {
                $this->dataMap[$table][$record['uid']][$this->translationParent] = $record[$this->origUid];
            } else {
                DebugUtility::debug($record);
                $this->missingInformation = true;
            }
        }
    }

    protected function checkTranslationFields(string $table, string $action) {
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
        $this->languageField = $languageField;
        $this->translationParent = $translationParent;
        $this->translationSource = $translationSource;
        $this->origUid = $origUid;
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
