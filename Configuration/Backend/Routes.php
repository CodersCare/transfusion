<?php

use T3thi\Transfusion\Controller;

return [
        'language_connect' => [
                'path' => '/language/connect',
                'access' => 'private',
                'target' => Controller\TransfusionController::class . '::connectAction',
        ],
        'language_disconnect' => [
                'path' => '/language/disconnect',
                'access' => 'private',
                'target' => Controller\TransfusionController::class . '::disconnectAction',
        ],
];