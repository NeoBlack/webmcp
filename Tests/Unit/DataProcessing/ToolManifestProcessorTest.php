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
use Neoblack\Webmcp\Tool\ManifestValidator;
use Neoblack\Webmcp\Tool\Primitive;
use Neoblack\Webmcp\Tool\ToolProviderInterface;
use Psr\Log\AbstractLogger;
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
        $processor = $this->processor(
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
        );

        $json = $processor->process($this->cObj(), [], ['as' => 'webmcpConfigJson'], [])['webmcpConfigJson'];

        // With JSON_HEX_TAG no literal "<" (and thus no "</script>") can remain.
        self::assertStringNotContainsString('</script>', $json);
        self::assertStringNotContainsString('<', $json, 'every "<" must be hex-escaped');
        self::assertNotNull(json_decode($json), 'output must remain valid JSON');
    }

    public function testEmitsEmptyStringWhenNoToolsRegistered(): void
    {
        $processor = $this->processor();

        $result = $processor->process($this->cObj(), [], ['as' => 'webmcpConfigJson'], []);

        self::assertSame('', $result['webmcpConfigJson']);
    }

    public function testEmitsRuntimeOptionDefaults(): void
    {
        $config = $this->decode($this->processor($this->provider(new Manifest('t', 'd', [], Primitive::StaticList))), []);

        self::assertSame('/webmcp-event', $config['endpoint']);
        self::assertTrue($config['legacyNavigatorFallback']);
        self::assertSame(1500, $config['outputLimit']);
    }

    public function testPassesConfiguredRuntimeOptionsThrough(): void
    {
        $processor = $this->processor($this->provider(new Manifest('t', 'd', [], Primitive::StaticList)));

        $config = $this->decode($processor, [
            'endpoint' => '/custom-event',
            'legacyNavigatorFallback' => '0',
            'outputLimit' => '800',
        ]);

        self::assertSame('/custom-event', $config['endpoint']);
        self::assertFalse($config['legacyNavigatorFallback']);
        self::assertSame(800, $config['outputLimit']);
    }

    public function testClampsNegativeOutputLimitToUnlimited(): void
    {
        $config = $this->decode($this->processor($this->provider(new Manifest('t', 'd', [], Primitive::StaticList))), ['outputLimit' => '-5']);

        self::assertSame(0, $config['outputLimit']);
    }

    public function testLogsValidatorFindingsButStillEmitsTheTool(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message];
            }
        };
        $processor = $this->processor($this->provider(new Manifest(str_repeat('a', 40), 'd', [], Primitive::StaticList)));
        $processor->setLogger($logger);

        $config = $this->decode($processor, []);

        self::assertCount(1, $config['tools']);
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0][0]);
        self::assertStringContainsString('40 characters', $logger->records[0][1]);
    }

    private function processor(ToolProviderInterface ...$providers): ToolManifestProcessor
    {
        return new ToolManifestProcessor(new ToolRegistry($providers), new ManifestValidator());
    }

    private function provider(Manifest $manifest): ToolProviderInterface
    {
        return new class($manifest) implements ToolProviderInterface {
            public function __construct(private Manifest $manifest)
            {
            }

            public function name(): string
            {
                return $this->manifest->name;
            }

            public function manifest(ContentObjectRenderer $cObj, array $processedData): Manifest
            {
                return $this->manifest;
            }
        };
    }

    /**
     * @param array<string, mixed> $processorConfiguration
     *
     * @return array<string, mixed>
     */
    private function decode(ToolManifestProcessor $processor, array $processorConfiguration): array
    {
        $json = $processor->process($this->cObj(), [], $processorConfiguration + ['as' => 'out'], [])['out'];
        $config = json_decode($json, true);
        self::assertIsArray($config);

        return $config;
    }

    /**
     * A stand-in that resolves plain (non-stdWrap) values like the real
     * ContentObjectRenderer::stdWrapValue(): the configured value, else the default.
     */
    private function cObj(): ContentObjectRenderer
    {
        $cObj = $this->createStub(ContentObjectRenderer::class);
        $cObj->method('stdWrapValue')->willReturnCallback(
            static fn (string $key, array $config, mixed $default = ''): mixed => $config[$key] ?? $default,
        );

        return $cObj;
    }
}
