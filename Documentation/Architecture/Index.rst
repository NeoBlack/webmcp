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

..  uml::
    :caption: Emitting tools — from PHP provider to registered agent tool

    participant "Tool provider" as TP
    participant "ToolRegistry" as TR
    participant "ToolManifestProcessor" as TMP
    participant "Page HTML" as PG
    participant "webmcp.js" as RT
    participant "ModelContext" as MC

    TP -> TR : manifest($cObj, $processedData)
    note right of TR : providers returning\nnull are dropped
    TR -> TMP : list of Manifest
    TMP -> PG : JSON block\n(JSON_HEX_* escaped)
    PG -> RT : read #webmcp-config
    note right of RT : per tool: primitive\ninterpreter or moduleUrl
    RT -> MC : provideContext({ tools })\n(else registerTool per tool)
    MC --> RT : tools discoverable & callable

..  list-table:: Key classes
    :header-rows: 1
    :widths: 40 60

    *   -   Class
        -   Responsibility
    *   -   :php:`\Neoblack\Webmcp\Tool\ToolProviderInterface`
        -   The contract each tool implements. Tagged ``webmcp.tool``
            (autoconfigured).
    *   -   :php:`\Neoblack\Webmcp\Tool\Manifest` /
            :php:`\Neoblack\Webmcp\Tool\Primitive`
        -   The serialisable tool description and its behaviour selector.
    *   -   :php:`\Neoblack\Webmcp\Registry\ToolRegistry`
        -   Collects manifests for the current request and exposes
            :php:`toolNames()` for the analytics whitelist.
    *   -   :php:`\Neoblack\Webmcp\DataProcessing\ToolManifestProcessor`
        -   Serialises the manifests into the page's JSON block.
    *   -   :file:`Resources/Public/JavaScript/webmcp.js`
        -   The generic runtime holding all four primitive interpreters and the
            escape-hatch loader.

Ingesting usage events (call time)
==================================

..  uml::
    :caption: Ingesting usage events — from tool call to backend dashboard

    actor "Agent on page" as P
    participant "webmcp.js" as JS
    participant "EventMiddleware" as MW
    participant "RateLimiter" as RL
    participant "ToolRegistry" as TR
    database "tx_neoblackwebmcp_event" as DB

    P -> JS : tool call
    JS -> MW : POST /webmcp-event\nsendBeacon { tool, client }
    MW -> MW : same-origin guard\n(Sec-Fetch-Site)
    MW -> RL : allow(ip, limit)?
    MW -> TR : tool in toolNames()?
    MW -> DB : log(tool, client, ts)

    == Backend module ==

    actor "Editor" as ED
    participant "DashboardController" as DC
    participant "StatisticsService" as SS
    participant "EventRepository" as ER

    ED -> DC : open System > WebMCP
    DC -> SS : collect(filter)
    SS -> ER : aggregate by tool / client / day
    ER -> DB : SELECT

..  list-table:: Key classes
    :header-rows: 1
    :widths: 40 60

    *   -   Class
        -   Responsibility
    *   -   :php:`\Neoblack\Webmcp\Middleware\EventMiddleware`
        -   The public ingest endpoint. Inert when analytics is disabled; passes
            unknown tools down the stack so it can coexist with other handlers.
    *   -   :php:`\Neoblack\Webmcp\Security\RateLimiter`
        -   Fixed-window limiter keyed on a hashed IP + window number (no
            plaintext IP stored).
    *   -   :php:`\Neoblack\Webmcp\Domain\Repository\EventRepository`
        -   The only class that writes/reads the event table.
    *   -   :php:`\Neoblack\Webmcp\Service\StatisticsService`
        -   Aggregates rows into the DTOs the backend module renders.
    *   -   :php:`\Neoblack\Webmcp\Controller\DashboardController`
        -   Thin backend controller; reads the filter, delegates, renders.

Why the two flows are decoupled
===============================

The middleware runs early, before the frontend page is resolved, so it cannot
rely on a rendered manifest. It therefore validates incoming events against
:php:`\Neoblack\Webmcp\Registry\ToolRegistry::toolNames()` — the context-free
provider names — rather than against the per-page manifest.

..  important::

    This is why :php:`\Neoblack\Webmcp\Tool\ToolProviderInterface::name()` must be
    stable and must equal the :php:`Manifest` name. A mismatch means valid tool
    calls are dropped by the ingest middleware.

..  seealso::

    *   :ref:`developer` – the provider interface and manifest in detail.
    *   :ref:`analytics` – the event table and how the endpoint is hardened.
