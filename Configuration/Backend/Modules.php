<?php

declare(strict_types=1);

use Neoblack\Webmcp\Controller\DashboardController;

/**
 * Backend module "WebMCP" – evaluation of the WebMCP tool usage events.
 */
return [
    'system_webmcp' => [
        'parent' => 'system',
        'access' => 'user',
        'path' => '/module/system/webmcp',
        'iconIdentifier' => 'neoblack-webmcp-module',
        'labels' => 'LLL:EXT:neoblack_webmcp/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => DashboardController::class . '::handleRequest',
            ],
        ],
    ],
];
