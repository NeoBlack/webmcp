<?php

/*
 * Required by the TYPO3 Extension Repository (TER). The extension key, title and
 * description are the authoritative Composer values; version is kept in sync by
 * Build/Scripts/release.sh together with composer.json and Documentation/guides.xml.
 */
$EM_CONF[$_EXTKEY] = [
    'title' => 'WebMCP for TYPO3',
    'description' => 'Declarative WebMCP tool framework for TYPO3: define agent tools server-side, expose them via document.modelContext, with optional first-party usage analytics.',
    'category' => 'fe',
    'author' => 'Frank Nägler',
    'author_email' => 'frank.naegler@typo3.com',
    'state' => 'alpha',
    'version' => '0.2.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'php' => '8.2.0-8.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
