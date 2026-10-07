<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\DataProcessing;

use Neoblack\Webmcp\Registry\ToolRegistry;
use Neoblack\Webmcp\Tool\ManifestValidator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Emits the WebMCP tool manifest as a single JSON blob, once per page, for the
 * generic JavaScript runtime to read from a <script id="webmcp-config"> tag.
 *
 * Output shape:
 *   {
 *     "endpoint": "/webmcp-event",          // analytics beacon target
 *     "legacyNavigatorFallback": true,      // also try navigator.modelContext
 *     "outputLimit": 1500,                  // max. characters of tool text output, 0 = unlimited
 *     "tools": [ {name, description, inputSchema, primitive, data, annotations}, … ]
 *   }
 *
 * The result is empty (no tag rendered) when no provider yields a tool. Every
 * manifest is checked by the ManifestValidator; findings are logged as
 * warnings and never stop a tool from being emitted.
 *
 * TypoScript usage:
 *   dataProcessing.40 = Neoblack\Webmcp\DataProcessing\ToolManifestProcessor
 *   dataProcessing.40 {
 *     endpoint = /webmcp-event
 *     legacyNavigatorFallback = 1
 *     outputLimit = 1500
 *     as = webmcpConfigJson
 *   }
 */
final class ToolManifestProcessor implements DataProcessorInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** Chrome "Secure tools" recommends at most 1,500 characters of tool output. */
    public const DEFAULT_OUTPUT_LIMIT = 1500;

    public function __construct(
        private readonly ToolRegistry $registry,
        private readonly ManifestValidator $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $contentObjectConfiguration
     * @param array<string, mixed> $processorConfiguration
     * @param array<string, mixed> $processedData
     *
     * @return array<string, mixed>
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData,
    ): array {
        $as = (string) ($processorConfiguration['as'] ?? 'webmcpConfigJson');

        $tools = $this->registry->collect($cObj, $processedData);
        if ([] === $tools) {
            $processedData[$as] = '';

            return $processedData;
        }

        foreach ($tools as $tool) {
            foreach ($this->validator->validate($tool) as $finding) {
                $this->logger?->warning($finding, ['tool' => $tool->name]);
            }
        }

        $endpoint = trim((string) $cObj->stdWrapValue('endpoint', $processorConfiguration));

        $payload = [
            'endpoint' => '' !== $endpoint ? $endpoint : '/webmcp-event',
            'legacyNavigatorFallback' => (bool) $cObj->stdWrapValue('legacyNavigatorFallback', $processorConfiguration, '1'),
            'outputLimit' => max(0, (int) $cObj->stdWrapValue('outputLimit', $processorConfiguration, (string) self::DEFAULT_OUTPUT_LIMIT)),
            'tools' => $tools,
        ];

        // The result is embedded verbatim inside a <script> block, so tool data
        // (page titles, descriptions, menu labels – editor-controlled) must not
        // be able to break out of it. JSON_HEX_* encodes <, >, &, ', " as \uXXXX,
        // making a "</script>" in any value harmless.
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
        $processedData[$as] = json_encode($payload, $flags) ?: '';

        return $processedData;
    }
}
