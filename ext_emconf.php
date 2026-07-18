<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'WebMCP',
    'description' => 'Declarative WebMCP tool framework: define agent tools server-side, expose them via document.modelContext, with optional first-party usage analytics.',
    'category' => 'fe',
    'constraints' => [
        'depends' => [
            'typo3' => '14.0.0-14.99.99',
        ],
        'conflicts' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'Neoblack\\Webmcp\\' => 'Classes',
        ],
    ],
    'state' => 'beta',
    'clearCacheOnLoad' => 1,
    'author' => 'Frank Nägler',
    'author_email' => 'frank@naegler.hamburg',
    'author_company' => 'Neoblack',
    'version' => '0.1.0',
];
