<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tests\Unit\DataProcessing;

use Neoblack\Webmcp\DataProcessing\ToolManifestProcessor;
use Neoblack\Webmcp\Registry\ToolRegistry;
use Neoblack\Webmcp\Tool\Manifest;
use Neoblack\Webmcp\Tool\Primitive;
use Neoblack\Webmcp\Tool\ToolProviderInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ToolManifestProcessorTest extends UnitTestCase
{
    /**
     * The manifest is embedded verbatim in a <script> block, so editor-controlled
     * tool data must not be able to break out of it (stored XSS).
     */
    public function testEscapesScriptTagInToolDataToPreventXss(): void
    {
        $evil = '</script><img src=x onerror=alert(document.cookie)>';
        $processor = new ToolManifestProcessor(new ToolRegistry([
            new class($evil) implements ToolProviderInterface {
                public function __construct(private string $evil)
                {
                }

                public function name(): string
                {
                    return 'evil';
                }

                public function manifest(ContentObjectRenderer $cObj, array $processedData): Manifest
                {
                    return new Manifest('evil', $this->evil, [], Primitive::StaticList, ['label' => $this->evil]);
                }
            },
        ]));

        $json = $processor->process($this->cObj(), [], ['as' => 'webmcpConfigJson'], [])['webmcpConfigJson'];

        // With JSON_HEX_TAG no literal "<" (and thus no "</script>") can remain.
        self::assertStringNotContainsString('</script>', $json);
        self::assertStringNotContainsString('<', $json, 'every "<" must be hex-escaped');
        self::assertNotNull(json_decode($json), 'output must remain valid JSON');
    }

    public function testEmitsEmptyStringWhenNoToolsRegistered(): void
    {
        $processor = new ToolManifestProcessor(new ToolRegistry([]));

        $result = $processor->process($this->cObj(), [], ['as' => 'webmcpConfigJson'], []);

        self::assertSame('', $result['webmcpConfigJson']);
    }

    private function cObj(): ContentObjectRenderer
    {
        $cObj = $this->createStub(ContentObjectRenderer::class);
        $cObj->method('stdWrapValue')->willReturn('');

        return $cObj;
    }
}
