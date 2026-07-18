<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Tool;

use Neoblack\Webmcp\Tool\Manifest;
use Neoblack\Webmcp\Tool\Primitive;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ManifestTest extends UnitTestCase
{
    public function testSerialisesEmptySchemaAndDataAsObjects(): void
    {
        $json = (new Manifest('greet', 'desc', [], Primitive::StaticList, []))->jsonSerialize();

        self::assertSame('greet', $json['name']);
        self::assertSame('desc', $json['description']);
        self::assertSame('static', $json['primitive']);
        // Empty schema/data must serialise to {} not [], so JSON consumers see objects.
        self::assertInstanceOf(\stdClass::class, $json['inputSchema']);
        self::assertInstanceOf(\stdClass::class, $json['data']);
        self::assertArrayNotHasKey('moduleUrl', $json);
    }

    public function testKeepsSchemaDataAndModuleUrl(): void
    {
        $json = (new Manifest(
            'search',
            'desc',
            ['type' => 'object'],
            Primitive::Search,
            ['indexUrl' => '/x.json'],
            'https://example.org/tool.js',
        ))->jsonSerialize();

        self::assertSame(['type' => 'object'], $json['inputSchema']);
        self::assertSame(['indexUrl' => '/x.json'], $json['data']);
        self::assertSame('search', $json['primitive']);
        self::assertSame('https://example.org/tool.js', $json['moduleUrl']);
    }

    public function testIsJsonEncodable(): void
    {
        $manifest = new Manifest('navigate_to_topic', 'd', ['type' => 'object'], Primitive::Navigate, ['param' => 'x']);

        self::assertJson((string) json_encode($manifest));
    }
}
