<?php

use T3thi\Transfusion\Controller;

return [
        'language_disconnect' => [
                'path' => '/disconnect',
                'access' => 'private',
                'target' => Controller\TransfusionController::class . '::disconnectAction',
        ],
];