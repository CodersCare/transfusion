<?php

declare(strict_types=1);

namespace T3thi\Transfusion\Controller;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Controller responsible for the disconnection of translated records and their parents
 */
class TransfusionController
{
    protected readonly PageRenderer $pageRenderer;
    protected array $dataMap = [];
    protected DataHandler $dataHandler;

    public function __construct()
    {
        $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        $this->dataHandler = GeneralUtility::makeInstance(DataHandler::class);
    }

    /**
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
     * @throws Exception
     */
    protected function fetchConnectedRecordsAndPrepareDataMap(string $table, int $language, int $page): void
    {
        $this->dataMap[$table] = [];
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
        $translationParent = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
        if (empty($translationParent)) {
            throw new InvalidArgumentException(
                    'Table must be translatable and provide a transOrigPointerField to be connected. This table can\'t be disconnected',
                    1706372241
            );
        }
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $connectedRecords = $queryBuilder
                ->select('uid')
                ->from($table)
                ->where(
                        $queryBuilder->expr()->eq($languageField, $language),
                        $queryBuilder->expr()->gt($translationParent, 0)
                )
                ->executeQuery();
        while ($record = $connectedRecords->fetchAssociative()) {
            $this->dataMap[$table][$record['uid']][$translationParent] = 0;
        }
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
