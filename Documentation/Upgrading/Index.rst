..  include:: /Includes.rst.txt

.. _upgrading:

=========
Upgrading
=========

..  contents::
    :local:
    :depth: 1

From 0.3 to 0.4
===============

Version 0.4 aligns the runtime with the WebMCP specification draft of
2026-10-02. Requirements are unchanged (TYPO3 14.3+, PHP 8.2+).

What changes
------------

*   **Registration.** The runtime no longer calls ``provideContext()``, which the
    specification removed. Each tool is registered individually with
    :js:`registerTool()`, so tools appear one after another instead of
    all at once. If one registration fails, only that tool is missing and the
    console shows one warning naming it.
*   **Lifecycle.** Tools are unregistered on ``pagehide`` and registered again
    when the page is restored from the back/forward cache.
*   **Iframes.** Tools are no longer registered when the page is embedded in an
    iframe.
*   **Output cap.** The text output of a tool call is limited to 1,500
    characters by default.
*   **New annotation.** Every manifest now also carries
    ``annotations.consequentialHint`` (``true`` for ``mailto``).

Do I need to act?
-----------------

*   **Tools built on the four primitives:** no action required.
*   **Escape-hatch modules that call** ``provideContext()`` **themselves:**
    replace the call with one :js:`registerTool(tool, { signal })` per tool.
    ``provideContext()`` does not exist in current browsers.
*   **Tools with long text output** (large ``static`` lists, high search
    limits): check whether the output exceeds 1,500 characters. Shorten it or
    set :confval:`outputLimit <dataprocessor-outputlimit>` (``0`` disables the
    cap).
*   **Embedded pages:** if you relied on tools inside an iframe, that no longer
    works — agents do not discover tools in iframes anyway.
*   **Check your log** after the first page render: the data processor now
    warns about tool or parameter names over 30 characters and descriptions over
    the recommended sizes (see :ref:`developer`).

New, optional settings
----------------------

*   :confval:`legacyNavigatorFallback <dataprocessor-legacynavigatorfallback>`
    — set to ``0`` to stop using the deprecated :js:`navigator.modelContext`.
*   :confval:`outputLimit <dataprocessor-outputlimit>` — the output cap.
*   Manifest arguments ``consequential`` and ``debugging`` (see
    :ref:`developer`).
