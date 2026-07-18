# WebMCP for TYPO3

A declarative [WebMCP](https://github.com/webmachinelearning/webmcp) tool
framework for TYPO3. Define agent tools server-side as small PHP providers; the
extension collects them into a per-page manifest and a generic JavaScript
runtime registers each tool against `document.modelContext` /
`navigator.modelContext`. Optional, privacy-preserving first-party analytics
are included.

- **Extension key:** `neoblack_webmcp`
- **Composer:** `neoblack/webmcp`
- **Requires:** TYPO3 v14.3+, PHP 8.2+

## Installation

```bash
composer require neoblack/webmcp
vendor/bin/typo3 extension:setup --extension neoblack_webmcp
```

## In a nutshell

A tool is a PHP class implementing `\Neoblack\Webmcp\Tool\ToolProviderInterface`.
It returns a `Manifest` naming one of four behaviour **primitives** —
`navigate`, `search`, `mailto`, `static` — whose generic interpreters live in
`webmcp.js`. New tools are therefore pure server-side configuration; no
JavaScript required. For anything the primitives don't cover, a manifest may
point at its own ES module.

```php
#[AutoconfigureTag('webmcp.tool')] // inherited from the interface
final class GreetToolProvider implements ToolProviderInterface
{
    public function name(): string { return 'greet'; }

    public function manifest(ContentObjectRenderer $cObj, array $processedData): ?Manifest
    {
        return new Manifest(
            name: 'greet',
            description: 'Return a greeting.',
            inputSchema: ['type' => 'object', 'properties' => new \stdClass()],
            primitive: Primitive::StaticList,
            data: ['items' => [['text' => 'Hello']], 'resultKey' => 'messages',
                   'text' => ['line' => '{text}']],
        );
    }
}
```

Wire the `ToolManifestProcessor`, render its JSON block as
`<script id="webmcp-config">`, and include `webmcp.js`. See the full
documentation under [`Documentation/`](Documentation/Index.rst).

## Analytics

An optional first-party endpoint (`/webmcp-event`) logs one row per tool call —
tool name + coarse client hint only, no PII — visualised in the
**Web → WebMCP** backend module. Disable via the `analyticsEnabled` extension
setting.

## License

GPL-2.0-or-later.
