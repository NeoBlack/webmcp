<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\Registry;

use Neoblack\Webmcp\Registry\ToolRegistry;
use Neoblack\Webmcp\Tool\Manifest;
use Neoblack\Webmcp\Tool\Primitive;
use Neoblack\Webmcp\Tool\ToolProviderInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ToolRegistryTest extends UnitTestCase
{
    public function testCollectSkipsProvidersThatReturnNull(): void
    {
        $registry = new ToolRegistry([
            $this->provider('a', $this->manifest('a')),
            $this->provider('b', null),
            $this->provider('c', $this->manifest('c')),
        ]);

        $manifests = $registry->collect($this->createStub(ContentObjectRenderer::class), []);

        self::assertCount(2, $manifests);
        self::assertSame(['a', 'c'], array_map(static fn (Manifest $m): string => $m->name, $manifests));
    }

    public function testToolNamesAreDeduplicated(): void
    {
        $registry = new ToolRegistry([
            $this->provider('a', null),
            $this->provider('a', null),
            $this->provider('b', null),
        ]);

        self::assertSame(['a', 'b'], $registry->toolNames());
    }

    private function manifest(string $name): Manifest
    {
        return new Manifest($name, 'd', [], Primitive::StaticList, []);
    }

    private function provider(string $name, ?Manifest $manifest): ToolProviderInterface
    {
        return new class($name, $manifest) implements ToolProviderInterface {
            public function __construct(private string $name, private ?Manifest $manifest)
            {
            }

            public function name(): string
            {
                return $this->name;
            }

            public function manifest(ContentObjectRenderer $cObj, array $processedData): ?Manifest
            {
                return $this->manifest;
            }
        };
    }
}
