<?php

use T3thi\Transfusion\Hooks\DataHandlerStoreSortingValues;

defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:transfusion/Configuration/TsConfig/All.tsconfig">');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['transfusion'] = [
        'T3thi\Transfusion\ViewHelpers\Backend',
];

$GLOBALS['TYPO3_CONF_VARS']['BE']['stylesheets']['transfusion'] = 'EXT:transfusion/Resources/Public/Css/transfusion-backend.css';

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = DataHandlerStoreSortingValues::class;
