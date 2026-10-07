<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Tool;

use Neoblack\Webmcp\Tool\Manifest;
use Neoblack\Webmcp\Tool\ManifestValidator;
use Neoblack\Webmcp\Tool\Primitive;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ManifestValidatorTest extends UnitTestCase
{
    public function testAcceptsManifestWithinAllLimits(): void
    {
        $manifest = new Manifest(
            'search_articles',
            str_repeat('d', 500),
            ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'description' => str_repeat('p', 150)]]],
            Primitive::Search,
        );

        self::assertSame([], (new ManifestValidator())->validate($manifest));
    }

    public function testWarnsAboutNameLongerThanRecommended(): void
    {
        $findings = (new ManifestValidator())->validate(new Manifest(str_repeat('a', 31), 'd', [], Primitive::StaticList));

        self::assertCount(1, $findings);
        self::assertStringContainsString('31 characters', $findings[0]);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'space' => ['search articles'],
            'umlaut' => ['suche_artikel_ä'],
            'empty' => [''],
            'too long for the spec' => [str_repeat('a', 129)],
        ];
    }

    #[DataProvider('invalidNames')]
    public function testWarnsAboutNameOutsideSpecPattern(string $name): void
    {
        $findings = (new ManifestValidator())->validate(new Manifest($name, 'd', [], Primitive::StaticList));

        self::assertCount(1, $findings, 'a pattern violation is reported once, not also as a length warning');
        self::assertStringContainsString('name pattern', $findings[0]);
    }

    public function testWarnsAboutDescriptionLongerThanRecommended(): void
    {
        $findings = (new ManifestValidator())->validate(new Manifest('t', str_repeat('ü', 501), [], Primitive::StaticList));

        self::assertCount(1, $findings);
        self::assertStringContainsString('description is 501 characters', $findings[0], 'length counts characters, not bytes');
    }

    public function testWarnsAboutParameterNameLongerThanRecommended(): void
    {
        $schema = ['type' => 'object', 'properties' => [
            str_repeat('p', 30) => ['type' => 'string'],
            'filter' => ['type' => 'object', 'properties' => [str_repeat('q', 31) => ['type' => 'string']]],
        ]];

        $findings = (new ManifestValidator())->validate(new Manifest('t', 'd', $schema, Primitive::Search));

        self::assertCount(1, $findings);
        self::assertStringContainsString('parameter name "filter.' . str_repeat('q', 31) . '" is 31 characters', $findings[0]);
    }

    public function testWarnsAboutNestedParameterDescriptions(): void
    {
        $long = str_repeat('p', 151);
        $schema = [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => $long],
                'filter' => [
                    'type' => 'object',
                    'properties' => ['category' => ['type' => 'string', 'description' => $long]],
                ],
                'tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'object', 'properties' => ['label' => ['description' => $long]]],
                ],
            ],
        ];

        $findings = (new ManifestValidator())->validate(new Manifest('t', 'd', $schema, Primitive::Search));

        self::assertCount(3, $findings);
        self::assertStringContainsString('"query"', $findings[0]);
        self::assertStringContainsString('"filter.category"', $findings[1]);
        self::assertStringContainsString('"tags.label"', $findings[2]);
    }
}
