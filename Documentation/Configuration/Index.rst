..  include:: /Includes.rst.txt

.. _configuration:

=============
Configuration
=============

Extension configuration
=======================

Set these in the TYPO3 backend under :guilabel:`Admin Tools > Settings >
Extension Configuration > neoblack_webmcp` (the defaults live in
:file:`ext_conf_template.txt`).

..  confval:: analyticsEnabled
    :type: boolean
    :Default: 1

    Log each WebMCP tool call (tool name + coarse client hint, no PII) to
    ``tx_neoblackwebmcp_event`` and expose the backend module. Turn off to
    disable the ingest endpoint (``/webmcp-event`` is then passed through).

..  confval:: analyticsRateLimit
    :type: integer
    :Default: 60

    Maximum accepted ingest calls per client IP per minute (0 = unlimited).
    Protects the public endpoint against flooding and statistics pollution;
    excess calls are answered with ``429 Too Many Requests``. The limiter uses
    the extension's own cache and only stores a hashed, short-lived counter —
    no plaintext IP.

Wiring the manifest into your site
==================================

Three pieces connect the tools to the page. All of them live in your site
package / TypoScript, so you stay in control of *where* the tools are exposed.

1. Emit the manifest
--------------------

Add the data processor to the page's ``FLUIDTEMPLATE`` (or ``PAGEVIEW``).

..  code-block:: typoscript
    :caption: Page TypoScript — register the data processor

    page.10.dataProcessing {
        # optional: a menu a navigate tool can build on
        35 = menu
        35 {
            entryLevel = 0
            levels = 1
            as = webmcpTopics
        }
        40 = Neoblack\Webmcp\DataProcessing\ToolManifestProcessor
        40 {
            endpoint = /webmcp-event
            # optional, shown with their defaults
            legacyNavigatorFallback = 1
            outputLimit = 1500
            as = webmcpConfigJson
        }
    }

..  important::

    If a tool provider relies on an earlier data processor (e.g. a
    ``MenuProcessor``), make sure that processor has a lower key so it runs
    *before* the :php:`\Neoblack\Webmcp\DataProcessing\ToolManifestProcessor`.

..  confval:: endpoint
    :name: dataprocessor-endpoint
    :type: string
    :Default: /webmcp-event

    Analytics beacon target written into the manifest.

..  confval:: legacyNavigatorFallback
    :name: dataprocessor-legacynavigatorfallback
    :type: boolean
    :Default: 1

    Whether the runtime may fall back to the deprecated
    :js:`navigator.modelContext` when :js:`document.modelContext` is not
    available. The specification only defines :js:`document.modelContext`;
    the navigator location is still served by older Chrome origin trial builds
    and by polyfills. Set to ``0`` to register against
    :js:`document.modelContext` only.

    ..  note::

        The default is planned to change to ``0`` — and the fallback to be
        removed — once Chrome and the common polyfills drop the navigator
        alias. Any console warning about :js:`navigator.modelContext` comes
        from the browser or a polyfill, never from this extension.

..  confval:: outputLimit
    :name: dataprocessor-outputlimit
    :type: integer
    :Default: 1500

    Maximum number of characters of text a single tool call returns to the
    agent. Longer text is cut and ends with a visible
    ``[Output truncated to … characters.]`` marker (the marker counts towards
    the limit). ``0`` disables the cap. The default follows Chrome's
    `Secure tools <https://developer.chrome.com/docs/ai/webmcp/secure-tools>`__
    recommendation. ``structuredContent`` is never cut, because truncated JSON
    would be invalid; keep it small via the primitive's own options (e.g. the
    search ``limitDefault``).

..  confval:: as
    :name: dataprocessor-as
    :type: string
    :Default: webmcpConfigJson

    Variable the JSON manifest is assigned to.

2. Render the JSON block
------------------------

Output the manifest once per page inside a :html:`<script>` tag with the id
``webmcp-config`` (the id the runtime looks for):

..  code-block:: html
    :caption: Fluid page template — render the JSON block

    <f:if condition="{webmcpConfigJson}">
        <script type="application/json" id="webmcp-config"><f:format.raw>{webmcpConfigJson}</f:format.raw></script>
    </f:if>

3. Include the runtime
----------------------

..  code-block:: typoscript
    :caption: Page TypoScript — include the runtime

    page.includeJSFooter {
        webmcp = EXT:neoblack_webmcp/Resources/Public/JavaScript/webmcp.js
        webmcp.defer = 1
    }

Where tools are registered
==========================

The runtime registers tools **only in the top-level document**. When a page is
shown inside an iframe — same-origin or cross-origin — nothing is registered.
This is not configurable: agents such as ChatGPT's built-in browser do not
discover tools in iframes anyway, and a third-party page embedding yours must
not receive its tools.

The extension never sets the specification's ``exposedTo`` registration option,
so tools are not exposed to other origins.

Backend module
==============

When analytics is enabled, the :guilabel:`System > WebMCP` module visualises tool
usage.

..  seealso::

    :ref:`analytics` describes the module's data model, retention and the
    hardening of the public ingest endpoint.
