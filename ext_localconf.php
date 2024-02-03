<?php

defined('TYPO3') or die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addPageTSConfig('<INCLUDE_TYPOSCRIPT: source="FILE:EXT:transfusion/Configuration/TsConfig/All.tsconfig">');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['transfusion'] = [
        'T3thi\Transfusion\ViewHelpers\Backend',
];
