<?php

declare(strict_types=1);

namespace T3thi\Transfusion\Domain\Repository;

use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
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
    public function fetchDefaultLanguageRecordsAndConnections(array $tables, int $language, int $page, array $fullDataMap): array
    {
        $defaultLanguageRecords = [];

        foreach ($tables as $table) {
            $defaultLanguageRecords[$table] = [
                'transFusionFields' => $this->checkTransFusionFields($table, ''),
                'records' => $this->fetchDefaultLanguageRecordsAndConnectionsForTable(
                    $table,
                    $language,
                    $page,
                    'connect',
                    $fullDataMap,
                )
            ];
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
    protected function fetchDefaultLanguageRecordsAndConnectionsForTable(
        string $table,
        int $language,
        int $page,
        string $action,
        array $fullDataMap,
    ): array {
        $defaultLanguageRecords = [];
        $assigned = [];
        $transFusionFields = $this->checkTransFusionFields($table, $action);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class));
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
                'pid' => $record['pid'],
                'label' => $record[$transFusionFields['label']],
                'type' => $record[$transFusionFields['type']],
                'language' => $record[$transFusionFields['language']],
                'parent' => $record[$transFusionFields['parent']],
                'source' => $record[$transFusionFields['source']],
                'original' => $record[$transFusionFields['original']],
                'icon' => $this->getIconForRecord($table, $record, $record[$transFusionFields['label']]),
                'previewData' => $record
            ];
            $preparedRecord['column'] = ($table === 'tt_content' ? $record[$transFusionFields['column']] : '');
            $connectedRecords = $this->getConnectedTranslations(
                $table,
                $record['uid'],
                $language,
                $transFusionFields
            );
            if (!empty($connectedRecords)) {
                // These records are already properly connected to their translation parent
                foreach ($connectedRecords as $connectedRecord) {
                    $preparedRecord['confirmedConnections'][] = [
                        'uid' => $connectedRecord['uid'],
                        'pid' => $connectedRecord['pid'],
                        'label' => $connectedRecord[$transFusionFields['label']],
                        'type' => $connectedRecord[$transFusionFields['type']],
                        'icon' => $this->getIconForRecord($table, $connectedRecord, $connectedRecord[$transFusionFields['label']]),
                        'previewData' => $connectedRecord
                    ];
                    $assigned[$connectedRecord['uid']] = true;
                }
            }
            foreach ($fullDataMap[$table] as $dataMapRecord) {
                $icon = $this->getIconForRecord($table, $dataMapRecord['previewData'], $dataMapRecord['previewData'][$transFusionFields['label']]);
                if (
                    $dataMapRecord['original'] === $preparedRecord['uid']
                    && !isset($assigned[$dataMapRecord['uid']])
                ) {
                    // These records are fully matching their translation parent but are not connected yet
                    $preparedRecord['obviousConnections'][] = [
                        'uid' => $dataMapRecord['uid'],
                        'pid' => $dataMapRecord['previewData']['pid'],
                        'label' => $dataMapRecord['previewData'][$transFusionFields['label']],
                        'type' => $dataMapRecord['previewData'][$transFusionFields['type']],
                        'icon' => $icon,
                        'previewData' => $dataMapRecord['previewData']
                    ];
                    $assigned[$dataMapRecord['uid']] = true;
                }
                if (
                    !empty($dataMapRecord['possibleParent'])
                    && !isset($assigned[$dataMapRecord['uid']])
                ) {
                    foreach ($dataMapRecord['possibleParent'] as $possibleParent) {
                        if ($possibleParent['uid'] === $preparedRecord['uid']) {
                            // These records are matching their translation parent via their source but are not connected yet
                            $preparedRecord['possibleConnections'][] = [
                                'uid' => $possibleParent['translation'],
                                'pid' => $dataMapRecord['previewData']['pid'],
                                'label' => $dataMapRecord['previewData'][$transFusionFields['label']],
                                'type' => $dataMapRecord['previewData'][$transFusionFields['type']],
                                'icon' => $icon,
                                'previewData' => $dataMapRecord['previewData']
                            ];
                            $assigned[$dataMapRecord['uid']] = true;
                        }
                    }
                }
                if (
                    !empty($dataMapRecord['brokenOrOrphaned'])
                    && !isset($assigned[$dataMapRecord['uid']])
                ) {
                    foreach ($dataMapRecord['brokenOrOrphaned'] as $brokenOrOrphaned) {
                        if ($brokenOrOrphaned['uid'] === $preparedRecord['uid']) {
                            // These records are partly matching their translation parent but are not connected yet
                            $preparedRecord['brokenConnections'][] = [
                                'uid' => $brokenOrOrphaned['translation'],
                                'pid' => $dataMapRecord['previewData']['pid'],
                                'label' => $dataMapRecord['previewData'][$transFusionFields['label']],
                                'type' => $dataMapRecord['previewData'][$transFusionFields['type']],
                                'icon' => $icon,
                                'previewData' => $dataMapRecord['previewData']
                            ];
                            $assigned[$dataMapRecord['uid']] = true;
                        }
                    }
                }
            }
            $defaultLanguageRecords[$preparedRecord['uid']] = $preparedRecord;
        }
        // Look for orphaned records that did not match the previous process
        foreach ($fullDataMap[$table] as $dataMapRecord) {
            if (!empty($dataMapRecord['uid']) && empty($assigned[$dataMapRecord['uid']])) {
                if (!empty($dataMapRecord['brokenOrOrphaned'])) {
                    $preparedRecord = ['brokenConnections' => []];
                    foreach ($dataMapRecord['brokenOrOrphaned'] as $brokenOrOrphaned) {
                        $preparedRecord['orphanedConnections'][] = [
                            'uid' => $brokenOrOrphaned['uid'],
                            'pid' => $dataMapRecord['previewData']['pid'],
                            'label' => $dataMapRecord['previewData'][$transFusionFields['label']],
                            'type' => $dataMapRecord['previewData'][$transFusionFields['type']],
                            'icon' => $this->getIconForRecord($table, $dataMapRecord['previewData'], $dataMapRecord['previewData'][$transFusionFields['label']]),
                            'previewData' => $dataMapRecord['previewData']
                        ];
                    }
                }
                $defaultLanguageRecords[$dataMapRecord['uid']] = $preparedRecord;
            }
        }
        return $defaultLanguageRecords;
    }

    /**
     * @param string $table
     * @param array $record
     * @param string $label
     * @return string
     */
    protected function getIconForRecord(string $table, array $record, string $label): string
    {
        return $this->iconFactory
            ->getIconForRecord($table, $record, Icon::SIZE_SMALL)
            ->setTitle(BackendUtility::getRecordIconAltText($record, $table) . ' # ' . $label)
            ->render();
    }

    /**
     * @param string $table
     * @param string $action
     * @return array
     */
    public function checkTransFusionFields(string $table, string $action): array
    {
        $transFusionFields = [];
        $labelField = $GLOBALS['TCA'][$table]['ctrl']['label'] ?? '';
        $typeField = $GLOBALS['TCA'][$table]['ctrl']['type'] ?? '';
        $languageField = $GLOBALS['TCA'][$table]['ctrl']['languageField'];
        $translationParent = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'];
        $translationSource = $GLOBALS['TCA'][$table]['ctrl']['translationSource'];
        $origUid = $GLOBALS['TCA'][$table]['ctrl']['origUid'];
        $sortBy = $GLOBALS['TCA'][$table]['ctrl']['sortby'] ?? '';
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
        $transFusionFields['label'] = $labelField;
        $transFusionFields['type'] = $typeField;
        $transFusionFields['language'] = $languageField;
        $transFusionFields['parent'] = $translationParent;
        $transFusionFields['source'] = $translationSource;
        $transFusionFields['original'] = $origUid;
        $transFusionFields['sorting'] = $sortBy;

        if ($table === 'tt_content') {
            $transFusionFields['column'] = 'colPos';
        }

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
    protected function getConnectedTranslations(string $table, int $uid, int $language, array $transfusionFields): array|false
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class));

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
            ->fetchAllAssociative();
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
        $transFusionFields = $this->checkTransFusionFields(
            $table,
            'disconnect'
        );
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class));
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
        $needsInteraction = false;
        $transFusionFields = $this->checkTransFusionFields(
            $table,
            $action
        );
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class));
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
                'type' => $record[$transFusionFields['type']],
                'language' => $record[$transFusionFields['language']],
                'parent' => $record[$transFusionFields['parent']],
                'source' => $record[$transFusionFields['source']],
                'original' => $record[$transFusionFields['original']],
                'previewData' => $record
            ];
            $preparedRecord['column'] = ($table === 'tt_content' ? $record[$transFusionFields['column']] : '');

            $fetchFields = $transFusionFields['language'] . ',' . $transFusionFields['type'];

            if ($table === 'tt_content') {
                $fetchFields .= ',' . $transFusionFields['column'];
            }
            if (
                !empty($preparedRecord['parent'])
            ) {
                $originalRecord = BackendUtility::getRecord(
                    $table,
                    $preparedRecord['parent'],
                    $fetchFields
                );
                if (
                    !empty($originalRecord)
                    && empty($originalRecord[$transFusionFields['language']])
                    && $originalRecord[$transFusionFields['type']] === $preparedRecord['type']
                ) {
                    $dataMap[$preparedRecord['uid']]['parent'][] = $preparedRecord['parent'];
                } else {
                    $needsInteraction = true;
                    $fetchPossibleParents = true;
                }
            } elseif (
                !empty($preparedRecord['source'])
                && !empty($preparedRecord['original'])
                && $preparedRecord['source'] === $preparedRecord['original']
            ) {
                $needsInteraction = true;
                $originalRecord = BackendUtility::getRecord(
                    $table,
                    $preparedRecord['original'],
                    $fetchFields
                );
                if (
                    !empty($originalRecord)
                    && empty($originalRecord[$transFusionFields['language']])
                    && $originalRecord[$transFusionFields['type']] === $preparedRecord['type']
                ) {
                    $dataMap[$preparedRecord['uid']]['parent'][] = $preparedRecord['original'];
                } else {
                    $fetchPossibleParents = true;
                }
            } else {
                $needsInteraction = true;
                $fetchPossibleParents = true;
            }
            $fullDataMap[$table][$preparedRecord['uid']] = $preparedRecord;
            $fetchFields .= ',' . $transFusionFields['original'] . ',uid';
            if ($fetchPossibleParents) {
                $possibleParentRecord = BackendUtility::getRecord(
                    $table,
                    $preparedRecord['source'],
                    $fetchFields
                );
                if (!empty($possibleParentRecord)) {
                    if (
                        $possibleParentRecord[$transFusionFields['type']] === $preparedRecord['type']
                    ) {
                        if (!empty($possibleParentRecord[$transFusionFields['original']])) {
                            $fullDataMap[$table][$preparedRecord['uid']]['possibleParent'][] = [
                                'uid' => $possibleParentRecord[$transFusionFields['original']],
                                'translation' => $preparedRecord['uid']
                            ];
                        } else {
                            $fullDataMap[$table][$preparedRecord['uid']]['possibleParent'][] = [
                                'uid' => $possibleParentRecord['uid'],
                                'translation' => $preparedRecord['uid']
                            ];
                        }
                    } else {
                        $fullDataMap[$table][$preparedRecord['uid']]['brokenOrOrphaned'][] = [
                            'uid' => $possibleParentRecord[$transFusionFields['original']],
                            'translation' => $preparedRecord['uid']
                        ];
                    }
                } else {
                    unset($fullDataMap[$table][$preparedRecord['uid']]);
                    $fullDataMap[$table]['NEW' . $preparedRecord['uid']] = $preparedRecord;
                    $fullDataMap[$table]['NEW' . $preparedRecord['uid']]['brokenOrOrphaned'][] = [
                        'uid' => 'NEW' . $preparedRecord['uid'],
                        'translation' => $preparedRecord['uid']
                    ];
                }
            }
        }
        return [
            'dataMap' => $dataMap,
            'needsInteraction' => $needsInteraction
        ];
    }

}
