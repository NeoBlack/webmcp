..  include:: /Includes.rst.txt

.. _architecture:

============
Architecture
============

The extension has two independent flows: **emitting tools** at page render time
and **ingesting usage events** at call time. They share only the tool registry.

..  contents::
    :local:
    :depth: 1

Emitting tools (frontend render)
================================

..  code-block:: text

    ToolProvider(s)                 one small PHP class per tool
        │  manifest($cObj, $processedData)  → Manifest | null
        ▼
    ToolRegistry::collect()         drops providers that return null
        │  list<Manifest>
        ▼
    ToolManifestProcessor           TYPO3 data processor
        │  JSON: { endpoint, tools:[…] }   (JSON_HEX_* escaped)
        ▼
    <script id="webmcp-config">     rendered once per page by your template
        │
        ▼
    webmcp.js runtime               reads the block, feature-detects ModelContext
        │  per tool: picks the primitive interpreter (or imports moduleUrl)
        ▼
    document.modelContext.registerTool()   agent can now discover & call the tool

Key classes:

*   :php:`\Neoblack\Webmcp\Tool\ToolProviderInterface` – the contract each tool
    implements. Tagged ``webmcp.tool`` (autoconfigured).
*   :php:`\Neoblack\Webmcp\Tool\Manifest` / :php:`\Neoblack\Webmcp\Tool\Primitive`
    – the serialisable tool description and its behaviour selector.
*   :php:`\Neoblack\Webmcp\Registry\ToolRegistry` – collects manifests for the
    current request and exposes :php:`toolNames()` for the analytics whitelist.
*   :php:`\Neoblack\Webmcp\DataProcessing\ToolManifestProcessor` – serialises the
    manifests into the page's JSON block.
*   :file:`Resources/Public/JavaScript/webmcp.js` – the generic runtime holding
    all four primitive interpreters and the escape-hatch loader.

Ingesting usage events (call time)
==================================

..  code-block:: text

    webmcp.js  ──POST /webmcp-event──►  EventMiddleware
      navigator.sendBeacon                 │  same-origin guard (Sec-Fetch-Site)
      { tool, client }                     │  RateLimiter::allow(ip, limit)
                                           │  tool ∈ ToolRegistry::toolNames() ?
                                           ▼
                                     EventRepository::log(tool, client, ts)
                                           │
                                           ▼
                                  tx_neoblackwebmcp_event  (append-only)

    Backend:  DashboardController ──► StatisticsService ──► EventRepository
              (System > WebMCP module)     aggregates by tool / client / day

Key classes:

*   :php:`\Neoblack\Webmcp\Middleware\EventMiddleware` – the public ingest
    endpoint. Inert when analytics is disabled; passes unknown tools down the
    stack so it can coexist with other handlers.
*   :php:`\Neoblack\Webmcp\Security\RateLimiter` – fixed-window limiter keyed on a
    hashed IP + window number (no plaintext IP stored).
*   :php:`\Neoblack\Webmcp\Domain\Repository\EventRepository` – the only class
    that writes/reads the event table.
*   :php:`\Neoblack\Webmcp\Service\StatisticsService` – aggregates rows into the
    DTOs the backend module renders.
*   :php:`\Neoblack\Webmcp\Controller\DashboardController` – thin backend
    controller; reads the filter, delegates, renders.

Why the two flows are decoupled
===============================

The middleware runs early, before the frontend page is resolved, so it cannot
rely on a rendered manifest. It therefore validates incoming events against
:php:`ToolRegistry::toolNames()` — the context-free provider names — rather than
against the per-page manifest. This is why :php:`ToolProviderInterface::name()`
must be stable and must equal the :php:`Manifest` name.
