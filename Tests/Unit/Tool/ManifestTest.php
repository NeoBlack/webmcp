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

    public function testDerivesConsequentialHintFromPrimitive(): void
    {
        // mailto acts outside the site (mail client); navigate, search and static do not.
        foreach (Primitive::cases() as $primitive) {
            $json = (new Manifest('t', 'd', [], $primitive))->jsonSerialize();

            self::assertSame(Primitive::Mailto === $primitive, $json['annotations']['consequentialHint'], $primitive->value);
        }
    }

    public function testExplicitConsequentialOverridesPrimitiveDefault(): void
    {
        $json = (new Manifest('go', 'd', [], Primitive::Navigate, consequential: true))->jsonSerialize();

        self::assertTrue($json['annotations']['consequentialHint']);
    }

    public function testOmitsDebuggingAnnotationUnlessRequested(): void
    {
        $default = (new Manifest('l', 'd', [], Primitive::StaticList))->jsonSerialize();
        $debug = (new Manifest('l', 'd', [], Primitive::StaticList, debugging: true))->jsonSerialize();

        self::assertArrayNotHasKey('debugging', $default['annotations']);
        self::assertTrue($debug['annotations']['debugging']);
    }

    public function testExistingPositionalArgumentsKeepTheirMeaning(): void
    {
        // 0.3 call sites pass up to nine positional arguments; the new ones are appended.
        $json = (new Manifest('l', 'd', [], Primitive::StaticList, [], null, false, 'Title', true))->jsonSerialize();

        self::assertFalse($json['annotations']['readOnlyHint']);
        self::assertSame('Title', $json['title']);
        self::assertTrue($json['annotations']['untrustedContentHint']);
        self::assertFalse($json['annotations']['consequentialHint']);
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
