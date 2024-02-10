<?php

declare(strict_types=1);

namespace T3thi\Transfusion\Domain\Repository;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Repository responsible for the fetching
 * of translated records and their translation parents
 * to be connected or disconnected during a transfusion action
 *
 * It automagically adds possible translation parents
 * if the original translation parent is not available.
 *
 * This is done by guessing the parent based on
 * translation source information.
 */
class TransfusionRepository
{

    /**
     * @var IconFactory
     */
    protected IconFactory $iconFactory;

    public function __construct()
    {
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

    /**
     * @param array $tables
     * @param int $language
     * @param int $page
     * @param array $fullDataMap
     * @return array
     * @throws Exception
     */
    public function fetchDefaultLanguageRecords(array $tables, int $language, int $page, array $fullDataMap): array
    {
        $defaultLanguageRecords = [];

        foreach ($tables as $table) {
            $defaultLanguageRecords[$table] = $this->fetchDefaultLanguageRecordsForTable(
                $table,
                $language,
                $page,
                'connect',
                $fullDataMap,
            );
        }

        return $defaultLanguageRecords;

    }

    /**
     * @param string $table
     * @param int $page
     * @param string $action
     * @param array $fullDataMap
     * @param int $language
     * @return array
     * @throws Exception
     */
    protected function fetchDefaultLanguageRecordsForTable(
        string $table,
        int $language,
        int $page,
        string $action,
        array $fullDataMap,
    ): array {
        $defaultLanguageRecords = [];
        $transFusionFields = $this->checkTransfusionFields($table, $action);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $defaultLanguageQuery = $queryBuilder
            ->select(
                '*'
            )
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $page),
                $queryBuilder->expr()->eq($transFusionFields['language'], 0),
                $queryBuilder->expr()->eq($transFusionFields['parent'], 0)
            )
            ->orderBy($transFusionFields['sorting'])
            ->executeQuery();
        while ($record = $defaultLanguageQuery->fetchAssociative()) {
            $preparedRecord = [
                'uid' => $record['uid'],
                $transFusionFields['language'] => $record[$transFusionFields['language']],
                $transFusionFields['parent'] => $record[$transFusionFields['parent']],
                $transFusionFields['source'] => $record[$transFusionFields['source']],
                $transFusionFields['original'] => $record[$transFusionFields['original']],
                'icon' => $this->getIconForRecord($table, $record),
                'previewData' => $record
            ];
            $connectedRecord = $this->getConnectedTranslation(
                $table,
                $record['uid'],
                $language,
                $transFusionFields
            );
            if (!empty($connectedRecord)) {
                $preparedRecord['confirmedConnection'] = [
                    'uid' => $connectedRecord['uid'],
                    'icon' => $this->getIconForRecord($table, $connectedRecord),
                    'previewData' => $connectedRecord
                ];
            }
            foreach ($fullDataMap[$table] as $dataMapRecord) {
                if (
                    $dataMapRecord[$transFusionFields['original']] === $preparedRecord['uid']
                    && (
                        empty($preparedRecord['confirmedConnection'])
                        || $preparedRecord['confirmedConnection']['uid'] !== $dataMapRecord['uid']
                    )
                ) {
                    $preparedRecord['obviousConnection'] = [
                        'uid' => $dataMapRecord['uid'],
                        'icon' => $this->getIconForRecord($table, $dataMapRecord['previewData']),
                        'previewData' =>$dataMapRecord['previewData']
                    ];
                } elseif (
                    !empty($dataMapRecord['possibleParent'])
                    && $dataMapRecord['possibleParent']['uid'] === $preparedRecord['uid']
                ) {
                    $preparedRecord['possibleConnection'] = [
                        'uid' => $dataMapRecord['possibleParent']['translation'],
                        'icon' => $this->getIconForRecord($table, $dataMapRecord['previewData']),
                        'previewData' => $dataMapRecord['previewData']
                    ];
                }
            }
            $defaultLanguageRecords[$preparedRecord['uid']] = $preparedRecord;
        }
        return $defaultLanguageRecords;
    }

    /**
     * @param string $table
     * @param array $record
     * @return string
     */
    protected function getIconForRecord(string $table, array $record): string
    {
        return $this->iconFactory
            ->getIconForRecord($table, $record, Icon::SIZE_SMALL)
            ->setTitle(BackendUtility::getRecordIconAltText($record, $table))
            ->render();
    }

    /**
     * @param string $table
     * @param string $action
     * @return array
     */
    protected function checkTransfusionFields(string $table, string $action): array
    {
        $transFusionFields = [];
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
        $translationParent = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
        $translationSource = $GLOBALS['TCA'][$table]['ctrl']['translationSource'];
        $origUid = $GLOBALS['TCA'][$table]['ctrl']['origUid'];
        $sortBy = $GLOBALS['TCA'][$table]['ctrl']['sortby'] ?? ($GLOBALS['TCA'][$table]['ctrl']['default_sortby'] ?? '');
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
        $transFusionFields['sorting'] = $sortBy;

        return $transFusionFields;
    }

    /**
     * @param string $table
     * @param int $uid
     * @param int $language
     * @param array $transfusionFields
     * @return array|false
     * @throws Exception
     */
    protected function getConnectedTranslation(string $table, int $uid, int $language, array $transfusionFields): array|false
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder
            ->select('*')
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq(
                    $transfusionFields['parent'],
                    $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    $transfusionFields['language'],
                    $queryBuilder->createNamedParameter($language, Connection::PARAM_INT)
                )
            )
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * @param string $table
     * @param int $language
     * @param int $page
     * @param string $action
     * @return array
     * @throws Exception
     */
    public function fetchConnectedRecordsAndPrepareDataMap(
        string $table,
        int $language,
        int $page,
        string $action
    ): array {
        $dataMap = [];
        $transFusionFields = $this->checkTransfusionFields(
            $table,
            'disconnect'
        );
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $connectedRecords = $queryBuilder
            ->select('uid', $transFusionFields['source'], $transFusionFields['original'])
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq($transFusionFields['language'], $queryBuilder->createNamedParameter($language, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt($transFusionFields['parent'], 0)
            )
            ->executeQuery();
        while ($record = $connectedRecords->fetchAssociative()) {
            $dataMap[$record['uid']][$transFusionFields['parent']] = 0;
            if (empty($record[$transFusionFields['source']])) {
                $dataMap[$record['uid']][$transFusionFields['source']] = $record[$transFusionFields['original']];
            }
        }
        return $dataMap;
    }

    /**
     * @param string $table
     * @param int $language
     * @param int $page
     * @param string $action
     * @param array $fullDataMap
     * @return array
     * @throws Exception
     */
    public function fetchDisconnectedRecordsAndPrepareDataMap(
        string $table,
        int $language,
        int $page,
        string $action,
        array &$fullDataMap
    ): array {
        $dataMap = [];
        $missingInformation = false;
        $transFusionFields = $this->checkTransfusionFields(
            $table,
            $action
        );
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $disconnectedRecords = $queryBuilder
            ->select(
                '*'
            )
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($page, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq($transFusionFields['language'], $language),
            )
            ->executeQuery();
        while ($record = $disconnectedRecords->fetchAssociative()) {
            $fetchPossibleParents = false;
            $preparedRecord = [
                'uid' => $record['uid'],
                $transFusionFields['language'] => $record[$transFusionFields['language']],
                $transFusionFields['parent'] => $record[$transFusionFields['parent']],
                $transFusionFields['source'] => $record[$transFusionFields['source']],
                $transFusionFields['original'] => $record[$transFusionFields['original']],
                'previewData' => $record
            ];
            if (
                !empty($preparedRecord[$transFusionFields['parent']])
            ) {
                $originalRecord = BackendUtility::getRecord(
                    $table,
                    $preparedRecord[$transFusionFields['parent']],
                    $transFusionFields['language']
                );
                if (empty($originalRecord[$transFusionFields['language']])) {
                    $dataMap[$preparedRecord['uid']][$transFusionFields['parent']] = $preparedRecord[$transFusionFields['parent']];
                } else {
                    $fetchPossibleParents = true;
                    $missingInformation = true;
                }
            } elseif (
                !empty($preparedRecord[$transFusionFields['source']])
                && !empty($preparedRecord[$transFusionFields['original']])
                && $preparedRecord[$transFusionFields['source']] === $preparedRecord[$transFusionFields['original']]
            ) {
                $originalRecord = BackendUtility::getRecord(
                    $table,
                    $preparedRecord[$transFusionFields['original']],
                    $transFusionFields['language']
                );
                if (empty($originalRecord[$transFusionFields['language']])) {
                    $dataMap[$preparedRecord['uid']][$transFusionFields['parent']] = $preparedRecord[$transFusionFields['original']];
                } else {
                    $fetchPossibleParents = true;
                    $missingInformation = true;
                }
            } else {
                $fetchPossibleParents = true;
                $missingInformation = true;
            }
            $fullDataMap[$table][$preparedRecord['uid']] = $preparedRecord;
            if ($fetchPossibleParents) {
                $possibleParentRecord = BackendUtility::getRecord(
                    $table,
                    $preparedRecord[$transFusionFields['source']],
                    $transFusionFields['original'] . ',uid'
                );
                if (!empty($possibleParentRecord)) {
                    if (empty($possibleParentRecord[$transFusionFields['language']])) {
                        $fullDataMap[$table][$preparedRecord['uid']]['possibleParent'] = [
                            'uid' => $possibleParentRecord[$transFusionFields['original']],
                            'translation' => $preparedRecord['uid']
                        ];
                    }
                }
            }
        }
        return [
            'dataMap' => $dataMap,
            'missingInformation' => $missingInformation
        ];
    }

}
