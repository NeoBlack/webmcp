<?php

declare(strict_types=1);

use Neoblack\Webmcp\Middleware\EventMiddleware;
use Neoblack\Webmcp\Middleware\FormSubmitMiddleware;

/**
 * Register the WebMCP event ingest endpoint early in the frontend stack so a
 * POST to /webmcp-event is handled without full page/site resolution.
 *
 * The form submit endpoint runs after site resolution: its finishers (e.g. the
 * e-mail finisher) need a resolved site and language on the request.
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
        'neoblack/webmcp/form-submit' => [
            'target' => FormSubmitMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
