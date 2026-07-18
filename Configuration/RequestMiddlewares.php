<?php

declare(strict_types=1);

use Neoblack\Webmcp\Middleware\EventMiddleware;

/**
 * Register the WebMCP event ingest endpoint early in the frontend stack so a
 * POST to /webmcp-event is handled without full page/site resolution.
 */
return [
    'frontend' => [
        'neoblack/webmcp/event' => [
            'target' => EventMiddleware::class,
            'after' => [
                'typo3/cms-frontend/normalized-params-attribute',
            ],
            'before' => [
                'typo3/cms-frontend/site',
            ],
        ],
    ],
];
