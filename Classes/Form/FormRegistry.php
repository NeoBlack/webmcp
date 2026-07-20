<?php

declare(strict_types=1);

/*
 * This file is part of the package neoblack/webmcp.
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Neoblack\Webmcp\Form;

use Neoblack\Webmcp\Tool\Manifest;

/**
 * Request-scoped collection of the WebMCP tools built for opted-in forms rendered
 * on the current page. The {@see RegisterWebMcpForm} listener fills it while forms
 * are loaded; {@see \Neoblack\Webmcp\Registry\ToolRegistry} reads it when it
 * assembles the page manifest, so form tools appear alongside provider tools.
 *
 * A form may be loaded more than once per request (e.g. render plus validation);
 * entries are de-duplicated by tool name.
 */
final class FormRegistry
{
    /** @var array<string, Manifest> keyed by tool name */
    private array $manifests = [];

    public function add(Manifest $manifest): void
    {
        $this->manifests[$manifest->name] ??= $manifest;
    }

    /**
     * @return list<Manifest>
     */
    public function all(): array
    {
        return array_values($this->manifests);
    }
}
