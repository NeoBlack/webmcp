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
    loop each tool
      RT -> MC : registerTool(descriptor, { signal })
      MC --> RT : registered — or rejected\n(e.g. duplicate name: warn once, skip)
    end
    note right of MC : tools appear one by one;\nthere is no atomic registration

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
    *   -   :php:`\Neoblack\Webmcp\Tool\ManifestValidator`
        -   Checks each manifest against the spec's name pattern and Chrome's
            size recommendations; the processor logs findings as warnings.
    *   -   :file:`Resources/Public/JavaScript/webmcp.js`
        -   The generic runtime holding all four primitive interpreters, the
            escape-hatch loader and the registration lifecycle.

Registration in the browser
---------------------------

The runtime in :file:`webmcp.js` runs these steps once per page load:

#.  **Top-level check.** Inside an iframe it stops: agents only discover tools
    of the top-level document, and an embedding third-party page must not
    receive the tools.
#.  **Read the config block.** Missing, unparsable or tool-less → stop.
#.  **Feature detection.** :js:`document.modelContext` is used when it offers
    :js:`registerTool()`. Otherwise — only if :confval:`legacyNavigatorFallback
    <dataprocessor-legacynavigatorfallback>` is on — the deprecated
    :js:`navigator.modelContext`. Neither → stop silently.
#.  **Build descriptors.** Each manifest entry becomes a tool descriptor
    (name, title, description, input schema, annotations, ``execute``).
    Entries without a name, with an unknown primitive and no ``moduleUrl``, or
    reusing a name already used in the manifest are dropped. An entry whose
    data makes the primitive fail is reported once and skipped.
#.  **Register each tool individually.** Every tool gets its own
    :js:`AbortController`; the runtime calls
    :js:`registerTool(descriptor, { signal })` and accepts any return value
    (``undefined``, a Promise or an object). If a call throws or its Promise
    rejects — for example because another script on the page already took the
    name — that one tool is skipped and a single :js:`console.warn` names it.
    All other tools are unaffected.
#.  **Teardown.** On ``pagehide`` all controllers are aborted, which
    unregisters the tools; if the page is restored from the back/forward cache
    (``pageshow`` with ``persisted``) they are registered again.

Every ``execute`` call is wrapped so that a synchronous throw becomes a
rejected Promise and text output is capped at :confval:`outputLimit
<dataprocessor-outputlimit>` characters.

..  note::

    Up to version 0.3 the runtime preferred ``provideContext({ tools })`` for an
    "atomic" registration. The specification removed ``provideContext()``, so
    tools now appear one after another — this is intended by the specification.
    See :ref:`security` for the background.

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
