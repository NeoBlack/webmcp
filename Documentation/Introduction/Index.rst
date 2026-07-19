..  include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============

What is WebMCP?
===============

WebMCP exposes page-scoped *tools* to AI agents through the browser's
``ModelContext`` interface (:js:`document.modelContext`, with a fallback to
:js:`navigator.modelContext` used by Chrome's origin trial). An agent operating
the page can discover these tools and call them – for example to search content,
navigate to a section, or open a pre-filled contact e-mail.

..  seealso::

    The `WebMCP proposal <https://github.com/webmachinelearning/webmcp>`__ of the
    W3C Web Machine Learning Community Group describes the underlying browser API
    this extension builds on.

What this extension does
========================

The extension turns tool definitions into a *declarative* concern:

*   A tool is a small PHP class implementing
    :php:`\Neoblack\Webmcp\Tool\ToolProviderInterface`. It returns a
    :php:`\Neoblack\Webmcp\Tool\Manifest` describing the tool's name, JSON schema
    and behaviour.
*   Behaviour is expressed through one of four **primitives** whose generic
    interpreters live in a single JavaScript runtime (:file:`webmcp.js`). No
    per-tool JavaScript is required.
*   A :php:`\Neoblack\Webmcp\DataProcessing\ToolManifestProcessor` (a TYPO3 data
    processor) collects every registered provider into one JSON block per page;
    the runtime reads it and registers the tools.

The four primitives at a glance:

..  list-table::
    :header-rows: 1
    :widths: 20 80

    *   -   Primitive
        -   Behaviour
    *   -   :typoscript:`navigate`
        -   Navigate the browser to a URL chosen from a fixed set of options.
    *   -   :typoscript:`search`
        -   Fetch a same-origin JSON index, filter it client-side, return the hits.
    *   -   :typoscript:`mailto`
        -   Build a pre-filled ``mailto:`` link and open it (no server storage).
    *   -   :typoscript:`static`
        -   Return a curated, static list of items verbatim.

Because tools are pure server-side configuration, other extensions and site
packages can add their own without touching JavaScript. For behaviour that no
primitive covers, a tool may point at its own ES module (escape hatch).

..  tip::

    See :ref:`developer` for the full payload of each primitive and the
    escape-hatch contract.

Privacy-preserving analytics
============================

An optional first-party ingest endpoint (``/webmcp-event``) logs one row per
tool call – only the tool name and a coarse, self-reported client hint, never
free text, cookies or IP. A backend module visualises the usage. Analytics can
be disabled in the extension configuration.

..  seealso::

    :ref:`analytics` covers exactly what is recorded, how long it is kept and how
    the public endpoint is hardened.

Feature detection & progressive enhancement
============================================

Everything degrades gracefully: without a ``ModelContext`` implementation,
without configuration, or with a malformed manifest, nothing is registered and
regular visitors are unaffected.

..  warning::

    **Experimental.** This extension is built on top of the WebMCP proposal,
    which is itself an early-stage, experimental specification. Both the
    specification and this extension's API may change or break at any time
    without notice. Use at your own risk.
