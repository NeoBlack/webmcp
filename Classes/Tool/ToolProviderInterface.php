<?php

declare(strict_types=1);

namespace Neoblack\Webmcp\Tool;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * A single WebMCP tool, defined server-side. Implement this interface anywhere
 * (this extension, a site package, a third-party extension) and the service is
 * tagged automatically — the AutoconfigureTag below is inherited by every
 * autoconfigured implementation, so no manual Services.yaml wiring is needed.
 *
 * The ContentObjectRenderer is handed in so providers can build TYPO3 URLs
 * (typolink / createUrl) and reach the current request via $cObj->getRequest();
 * URLs must never be hand-built in JavaScript.
 */
#[AutoconfigureTag('webmcp.tool')]
interface ToolProviderInterface
{
    /**
     * The tool's stable, context-free name (e.g. "search_articles"). Used by the
     * analytics middleware to whitelist incoming events before the frontend is
     * resolved, so this must not depend on request state. Must equal the name of
     * the Manifest returned by manifest().
     */
    public function name(): string;

    /**
     * Return the tool's manifest, or null to omit the tool for this request
     * (e.g. a blog tool on a site without a blog, or a page-context guard).
     *
     * $processedData carries the results of any DataProcessors that ran before
     * ToolManifestProcessor in the same content object, so a provider can build
     * on them (e.g. read a menu produced by an earlier MenuProcessor) instead of
     * recomputing it.
     *
     * @param array<string, mixed> $processedData
     */
    public function manifest(ContentObjectRenderer $cObj, array $processedData): ?Manifest;
}
