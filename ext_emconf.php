<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'TransFusion',
    'description' => 'Wizard module to deal with TYPO3 connected and free mode translations and to fix the notorious mixed mode',
    'category' => 'module',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-12.4.99'
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'T3thi\\Transfusion\\' => 'Classes',
        ],
    ],
    'state' => 'alpha',
    'author' => 'Jo Hasenau',
    'author_email' => 'jh@cybercraft.de',
    'author_company' => 'TYPO3 Translation Handling Initiative',
    'version' => '0.0.1',
];
