..  include:: /Includes.rst.txt

.. _troubleshooting:

===============
Troubleshooting
===============

Everything in the extension degrades silently on purpose — a missing manifest,
an absent ``ModelContext`` or a malformed config simply registers nothing.
That is good for visitors but means failures are quiet. Work through the checks
below in order.

..  contents::
    :local:
    :depth: 1

No tools are registered
=======================

Check, from the outside in:

1.  **Is the JSON block on the page?** View source and look for
    ``<script type="application/json" id="webmcp-config">``. In the console:

    ..  code-block:: javascript

        document.getElementById('webmcp-config')?.textContent

    *   Empty / missing → the manifest was not rendered. Verify the Fluid
        snippet (Step 2 in :ref:`quickstart`) is in your template and that the
        variable name matches the data processor's ``as`` (default
        ``webmcpConfigJson``).

2.  **Does the block contain your tool?**

    ..  code-block:: javascript

        JSON.parse(document.getElementById('webmcp-config').textContent).tools

    *   Empty array → no provider yielded a manifest. Either the provider is not
        registered (see below) or its :php:`manifest()` returned ``null`` for
        this request.

3.  **Is the runtime loaded?** Confirm :file:`webmcp.js` is included in the page
    footer (the ``includeJSFooter`` TypoScript).

4.  **Is there a ModelContext to register against?**

    ..  code-block:: javascript

        document.modelContext || navigator.modelContext

    *   ``undefined`` in both → the browser has no agent surface. This is
        expected in a normal browser; the runtime intentionally does nothing.
        Test with an agent-capable client or Chrome with the WebMCP origin
        trial enabled.

My provider is never called
===========================

The provider is a service tagged ``webmcp.tool``. If it is not picked up:

*   Confirm your extension enables autoconfiguration in
    :file:`Configuration/Services.yaml` (``_defaults`` with ``autoconfigure:
    true``). The tag is inherited from the interface's
    ``#[AutoconfigureTag('webmcp.tool')]`` only when autoconfiguration is on.
*   Otherwise tag the service manually (see the note in :ref:`quickstart`).
*   Clear the TYPO3 caches after adding a new service.

A tool depends on an earlier data processor
===========================================

Providers receive ``$processedData`` from data processors that ran **before**
the :php:`ToolManifestProcessor` in the same content object. If your provider
builds on, say, a ``MenuProcessor`` output, that processor must have a lower
key so it runs first (see the ordered example in :ref:`configuration`).

Analytics events are not recorded
=================================

*   **Is analytics enabled?** Check ``analyticsEnabled`` in the extension
    configuration. When off, ``/webmcp-event`` is passed through and nothing is
    stored.
*   **Is the tool name whitelisted?** Only names returned by a registered
    provider's :php:`name()` are logged, and :php:`name()` must equal the
    :php:`Manifest` name. A mismatch silently drops the event.
*   **Getting 429s?** You are hitting the rate limit
    (``analyticsRateLimit`` calls per IP per minute). Raise it or set ``0`` to
    disable — see :ref:`analytics`.
*   **Getting 204 but no row?** A ``204`` is also returned for cross-site posts
    rejected by the ``Sec-Fetch-Site`` guard and for the normal success path.
    Confirm the beacon is a genuine same-origin ``POST`` to ``/webmcp-event``.

The manifest looks corrupted / cut off
======================================

Tool values are embedded verbatim inside a ``<script>`` block. The processor
escapes ``< > & ' "`` as ``\uXXXX`` (``JSON_HEX_*``), so a ``</script>`` inside
an editor-controlled value cannot break out. If you see raw HTML entities where
you expected characters, do **not** disable those flags — decode on read
instead.
