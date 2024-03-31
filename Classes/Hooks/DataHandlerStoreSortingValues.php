<?php

declare(strict_types=1);

namespace T3thi\Transfusion\Hooks;

use TYPO3\CMS\Core\DataHandling\DataHandler;

class DataHandlerStoreSortingValues
{
    public function processDatamap_preProcessFieldArray(
        array &$incomingFieldArray,
        string $table,
        string $id,
        DataHandler $dataHandler
    ): void
    {
        if (!empty($dataHandler->overrideValues['storeSortingValuesForTransFusion'])) {
            $sortBy = $GLOBALS['TCA'][$table]['ctrl']['sortby'] ?? '';
            if (!empty($sortBy)) {
                // if there is a configured sorting column, don't touch it
                if (!isset($GLOBALS['TCA'][$table]['columns'][$sortBy])) {
                    $GLOBALS['TCA'][$table]['columns'][$sortBy] = [
                        'config' => [
                            'type' => 'passthrough'
                        ]
                    ];
                    $dataHandler->overrideValues['removeTransFusionSortingField'] = $sortBy;
                } else {
                    $dataHandler->overrideValues['removeTransFusionSortingField'] = '';
                }
            }
        }
    }

    public function processDatamap_postProcessFieldArray(
        string $status,
        string $table,
        string $id,
        array $fieldArray,
        DataHandler $dataHandler
    ): void
    {
        if (!empty($dataHandler->overrideValues['storeSortingValuesForTransFusion'])) {
            $sortBy = $dataHandler->overrideValues['removeTransFusionSortingField'] ?? '';
            // if there was no configured sorting column, remove it now
            if (!empty($sortBy) && isset($GLOBALS['TCA'][$table]['columns'][$sortBy])) {
                unset($GLOBALS['TCA'][$table]['columns'][$sortBy]);
            }
        }
    }
}
