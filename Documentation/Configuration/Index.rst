..  include:: /Includes.rst.txt

.. _configuration:

=============
Configuration
=============

Extension configuration
========================

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

Add the data processor to the page's ``FLUIDTEMPLATE`` (or ``PAGEVIEW``). If a
tool provider relies on an earlier data processor (e.g. a ``MenuProcessor``),
make sure that runs first.

..  code-block:: typoscript

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
            as = webmcpConfigJson
        }
    }

..  confval:: endpoint
    :name: dataprocessor-endpoint
    :type: string
    :Default: /webmcp-event

    Analytics beacon target written into the manifest.

..  confval:: as
    :name: dataprocessor-as
    :type: string
    :Default: webmcpConfigJson

    Variable the JSON manifest is assigned to.

2. Render the JSON block
------------------------

Output the manifest once per page inside a ``<script>`` tag with the id
``webmcp-config`` (the id the runtime looks for):

..  code-block:: html

    <f:if condition="{webmcpConfigJson}">
        <script type="application/json" id="webmcp-config"><f:format.raw>{webmcpConfigJson}</f:format.raw></script>
    </f:if>

3. Include the runtime
----------------------

..  code-block:: typoscript

    page.includeJSFooter {
        webmcp = EXT:neoblack_webmcp/Resources/Public/JavaScript/webmcp.js
        webmcp.defer = 1
    }

Backend module
==============

When analytics is enabled, the module :guilabel:`Web > WebMCP` shows calls per
tool and client, a per-day timeline and the most recent events. The accepted
tool names for the ingest endpoint are derived automatically from the
registered providers.
