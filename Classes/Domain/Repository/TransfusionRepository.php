<?php

declare(strict_types=1);

namespace T3thi\Transfusion\Domain\Repository;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
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
     * @param array $connect
     * @param array $fullDataMap
     * @return array
     * @throws Exception
     */
    public function fetchDefaultLanguageRecords(array $connect, array $fullDataMap): array
    {
        $defaultLanguageRecords = [];
        if (!empty($connect)) {
            foreach ($connect as $page => $connections) {
                if (!empty($connections)) {
                    foreach ($connections as $tables) {
                        if (!empty($tables)) {
                            foreach ($tables as $table) {
                                $transFusionFields = $this->checkTransfusionFields($table, 'connect');
                                $defaultLanguageRecords[$table] = $this->fetchDefaultLanguageRecordsForTable(
                                    $table,
                                    $page,
                                    $transFusionFields,
                                    $fullDataMap
                                );
                            }
                        }
                    }
                }
            }
        }
        return $defaultLanguageRecords;
    }

    /**
     * @param string $table
     * @param string $action
     * @return array
     */
    public function checkTransfusionFields(string $table, string $action): array
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
     * @param int $page
     * @param array $transFusionFields
     * @param array $fullDataMap
     * @return array
     * @throws Exception
     */
    protected function fetchDefaultLanguageRecordsForTable(
        string $table,
        int    $page,
        array  $transFusionFields,
        array  $fullDataMap
    ): array
    {
        $defaultLanguageRecords = [];
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
                'previewData' => $record
            ];
            foreach ($fullDataMap[$table] as $dataMapRecord) {
                if (
                    $dataMapRecord[$transFusionFields['original']] === $preparedRecord['uid']
                ) {
                    $preparedRecord['matchedConnection'] = $dataMapRecord['uid'];
                } elseif (
                    !empty($dataMapRecord['possibleParent'])
                    && $dataMapRecord['possibleParent']['uid'] === $preparedRecord['uid']
                ) {
                    $preparedRecord['possibleConnection'] = $dataMapRecord['possibleParent']['translation'];
                }
            }
            $defaultLanguageRecords[$preparedRecord['uid']] = $preparedRecord;
        }
        return $defaultLanguageRecords;
    }

    /**
     * @param string $table
     * @param int $language
     * @param int $page
     * @param array $transFusionFields
     * @return array
     * @throws Exception
     */
    public function fetchConnectedRecordsAndPrepareDataMap(
        string $table,
        int    $language,
        int    $page,
        array  $transFusionFields
    ): array
    {
        $dataMap = [];
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $connectedRecords = $queryBuilder
            ->select('uid', $transFusionFields['source'], $transFusionFields['original'])
            ->from($table)
            ->where(
                $queryBuilder->expr()->eq('pid', $page),
                $queryBuilder->expr()->eq($transFusionFields['language'], $language),
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
     * @param array $transFusionFields
     * @param array $fullDataMap
     * @return array
     * @throws Exception
     */
    public function fetchDisconnectedRecordsAndPrepareDataMap(
        string $table,
        int    $language,
        int    $page,
        array  $transFusionFields,
        array  &$fullDataMap
    ): array
    {
        $dataMap = [];
        $missingInformation = false;
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
                $queryBuilder->expr()->eq('pid', $page),
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

    public function fetchPreviewRecords(array $previewDataIds)
    {
        $previewRecords = [];
        foreach ($previewDataIds as $table => $ids) {
            if (empty($ids)) {
                continue;
            }
            $idList = implode(',', array_keys($ids));
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $queryBuilder
                ->getRestrictions()
                ->removeAll()
                ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $previewRecords[$table] = $queryBuilder
                ->select(
                    '*',
                )
                ->from($table)
                ->where(
                    $queryBuilder->expr()->in('uid', $idList)
                )
                ->executeQuery()
                ->fetchAllAssociativeIndexed();
        }
        return $previewRecords;
    }

}
