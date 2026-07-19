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

    public function testDerivesReadOnlyHintFromPrimitive(): void
    {
        $readOnly = (new Manifest('list', 'd', [], Primitive::StaticList))->jsonSerialize();
        $writing = (new Manifest('go', 'd', [], Primitive::Navigate))->jsonSerialize();

        self::assertTrue($readOnly['annotations']['readOnlyHint']);
        self::assertFalse($writing['annotations']['readOnlyHint']);
    }

    public function testExplicitReadOnlyOverridesPrimitiveDefault(): void
    {
        // A search that mutates state (custom module) can opt out of the read-only default.
        $json = (new Manifest('x', 'd', [], Primitive::Search, [], null, false))->jsonSerialize();

        self::assertFalse($json['annotations']['readOnlyHint']);
    }

    public function testDerivesUntrustedContentHintFromPrimitive(): void
    {
        // search returns third-party index data (untrusted); static is curated.
        $search = (new Manifest('s', 'd', [], Primitive::Search))->jsonSerialize();
        $static = (new Manifest('l', 'd', [], Primitive::StaticList))->jsonSerialize();

        self::assertTrue($search['annotations']['untrustedContentHint']);
        self::assertFalse($static['annotations']['untrustedContentHint']);
    }

    public function testExplicitUntrustedContentOverridesPrimitiveDefault(): void
    {
        // A static list assembled from user-supplied data can opt into the hint.
        $json = (new Manifest('l', 'd', [], Primitive::StaticList, [], null, null, null, true))->jsonSerialize();

        self::assertTrue($json['annotations']['untrustedContentHint']);
    }

    public function testOmitsTitleWhenNull(): void
    {
        $json = (new Manifest('greet', 'desc', [], Primitive::StaticList))->jsonSerialize();

        self::assertArrayNotHasKey('title', $json);
    }

    public function testSerialisesTitleWhenSet(): void
    {
        $json = (new Manifest('greet', 'desc', [], Primitive::StaticList, [], null, null, 'Say hello'))->jsonSerialize();

        self::assertSame('Say hello', $json['title']);
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
