<?php

declare(strict_types=1);

use Neoblack\Webmcp\Controller\DashboardController;

/**
 * Backend module "WebMCP" – evaluation of the WebMCP tool usage events.
 */
return [
    'web_webmcp' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'user',
        'path' => '/module/web/webmcp',
        'iconIdentifier' => 'neoblack-webmcp-module',
        'labels' => 'LLL:EXT:neoblack_webmcp/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => DashboardController::class . '::handleRequest',
            ],
        ],
    ],
];
