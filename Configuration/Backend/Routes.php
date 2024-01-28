<?php

use T3thi\Transfusion\Controller\TransfusionController;

return [
        'transfusion_connect' => [
                'path' => '/transfusion/connect',
                'access' => 'private',
                'target' => TransfusionController::class . '::connectAction',
        ],
        'transfusion_disconnect' => [
                'path' => '/transfusion/disconnect',
                'access' => 'private',
                'target' => TransfusionController::class . '::disconnectAction',
        ],
];
