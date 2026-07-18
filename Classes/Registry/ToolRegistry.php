<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Registry;

use Neoblack\Webmcp\Tool\Manifest;
use Neoblack\Webmcp\Tool\ToolProviderInterface;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

/**
 * Collects the manifests of all registered tool providers (services tagged
 * 'webmcp.tool', injected via Services.yaml). Providers that return null for
 * the current request are dropped.
 */
final class ToolRegistry
{
    /**
     * @param iterable<ToolProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * @param array<string, mixed> $processedData results of preceding DataProcessors
     *
     * @return list<Manifest>
     */
    public function collect(ContentObjectRenderer $cObj, array $processedData): array
    {
        $manifests = [];
        foreach ($this->providers as $provider) {
            $manifest = $provider->manifest($cObj, $processedData);
            if (null !== $manifest) {
                $manifests[] = $manifest;
            }
        }

        return $manifests;
    }

    /**
     * The context-free names of all registered tools. Used by the analytics
     * middleware, which runs before the frontend is resolved, to whitelist
     * incoming events.
     *
     * @return list<string>
     */
    public function toolNames(): array
    {
        $names = [];
        foreach ($this->providers as $provider) {
            $names[] = $provider->name();
        }

        return array_values(array_unique($names));
    }
}
