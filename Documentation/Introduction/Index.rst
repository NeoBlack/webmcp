..  include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============

What is WebMCP?
===============

WebMCP exposes page-scoped *tools* to AI agents through the browser's
``ModelContext`` interface (``document.modelContext``, with a fallback to
``navigator.modelContext`` used by Chrome's origin trial). An agent operating
the page can discover these tools and call them – for example to search
content, navigate to a section, or open a pre-filled contact e-mail.

What this extension does
========================

The extension turns tool definitions into a *declarative* concern:

*   A tool is a small PHP class implementing
    :php:`\Neoblack\Webmcp\Tool\ToolProviderInterface`. It returns a
    :php:`Manifest` describing the tool's name, JSON schema and behaviour.
*   Behaviour is expressed through one of four **primitives** –
    ``navigate``, ``search``, ``mailto`` and ``static`` – whose generic
    interpreters live in a single JavaScript runtime (:file:`webmcp.js`).
    No per-tool JavaScript is required.
*   A :php:`ToolManifestProcessor` (a TYPO3 data processor) collects every
    registered provider into one JSON block per page; the runtime reads it and
    registers the tools.

Because tools are pure server-side configuration, other extensions and site
packages can add their own without touching JavaScript. For behaviour that no
primitive covers, a tool may point at its own ES module (escape hatch).

Privacy-preserving analytics
============================

An optional first-party ingest endpoint (``/webmcp-event``) logs one row per
tool call – only the tool name and a coarse, self-reported client hint, never
free text, cookies or IP. A backend module visualises the usage. Analytics can
be disabled in the extension configuration.

Feature detection & progressive enhancement
============================================

Everything degrades gracefully: without a ``ModelContext`` implementation,
without configuration, or with a malformed manifest, nothing is registered and
regular visitors are unaffected.
