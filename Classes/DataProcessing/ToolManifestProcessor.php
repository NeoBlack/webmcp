<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\DataProcessing;

use Neoblack\Webmcp\Registry\ToolRegistry;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Emits the WebMCP tool manifest as a single JSON blob, once per page, for the
 * generic JavaScript runtime to read from a <script id="webmcp-config"> tag.
 *
 * Output shape:
 *   {
 *     "endpoint": "/webmcp-event",          // analytics beacon target
 *     "tools": [ {name, description, inputSchema, primitive, data}, … ]
 *   }
 *
 * The result is empty (no tag rendered) when no provider yields a tool.
 *
 * TypoScript usage:
 *   dataProcessing.40 = Neoblack\Webmcp\DataProcessing\ToolManifestProcessor
 *   dataProcessing.40 {
 *     endpoint = /webmcp-event
 *     as = webmcpConfigJson
 *   }
 */
final class ToolManifestProcessor implements DataProcessorInterface
{
    public function __construct(
        private readonly ToolRegistry $registry,
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

        $endpoint = trim((string) $cObj->stdWrapValue('endpoint', $processorConfiguration));

        $payload = [
            'endpoint' => '' !== $endpoint ? $endpoint : '/webmcp-event',
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
