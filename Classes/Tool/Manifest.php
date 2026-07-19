<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Tool;

/**
 * The serialisable description of a single WebMCP tool. A provider returns one
 * of these; the ToolManifestProcessor collects them into the page's JSON config
 * block, and the generic JavaScript runtime registers each against
 * document.modelContext / navigator.modelContext.
 *
 * The tool's runtime behaviour is declared, not coded: pick a {@see Primitive}
 * and supply its data. For behaviour that no primitive covers, leave the
 * primitive as-is and point $moduleUrl at an ES module exporting an execute()
 * function — the runtime imports it on demand.
 */
final class Manifest implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $inputSchema      JSON Schema for the tool arguments
     * @param array<string, mixed> $data             primitive-specific payload (options, index URL, …)
     * @param string|null          $moduleUrl        optional ES module URL providing a custom execute()
     * @param bool|null            $readOnly         override the read-only hint; null derives it from
     *                                               the primitive (see {@see Primitive::isReadOnly()})
     * @param string|null          $title            optional human-readable label for UI display; the
     *                                               machine-stable $name is used when omitted
     * @param bool|null            $untrustedContent override the untrusted-content hint; null derives
     *                                               it from the primitive (see {@see Primitive::hasUntrustedOutput()})
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $inputSchema,
        public readonly Primitive $primitive,
        public readonly array $data = [],
        public readonly ?string $moduleUrl = null,
        public readonly ?bool $readOnly = null,
        public readonly ?string $title = null,
        public readonly ?bool $untrustedContent = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $out = [
            'name' => $this->name,
            'description' => $this->description,
            'inputSchema' => [] === $this->inputSchema ? new \stdClass() : $this->inputSchema,
            'primitive' => $this->primitive->value,
            'data' => [] === $this->data ? new \stdClass() : $this->data,
            'annotations' => [
                'readOnlyHint' => $this->readOnly ?? $this->primitive->isReadOnly(),
                'untrustedContentHint' => $this->untrustedContent ?? $this->primitive->hasUntrustedOutput(),
            ],
        ];
        if (null !== $this->title) {
            $out['title'] = $this->title;
        }
        if (null !== $this->moduleUrl) {
            $out['moduleUrl'] = $this->moduleUrl;
        }

        return $out;
    }
}
