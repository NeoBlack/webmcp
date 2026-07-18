<?php

if (PHP_SAPI !== 'cli') {
    die('This script supports command line usage only. Please check your command.');
}

$header = <<<EOF
This file is part of the package neoblack/webmcp.
For the full copyright and license information, please read the
LICENSE file that was distributed with this source code.
EOF;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
        'array_syntax' => ['syntax' => 'short'],
        'concat_space' => ['spacing' => 'one'],
        'class_attributes_separation' => [
            'elements' => ['property' => 'only_if_meta']
        ],
        'general_phpdoc_annotation_remove' => [
            'annotations' => ['author']
        ],
        'header_comment' => [
            'header' => $header,
        ],
        'no_extra_blank_lines' => true,
        'no_superfluous_phpdoc_tags' => false,
        'php_unit_construct' => [
            'assertions' => ['assertEquals', 'assertSame', 'assertNotEquals', 'assertNotSame']
        ],
        'php_unit_mock_short_will_return' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__ . '/../../Classes', __DIR__ . '/../../Tests'])
    );
