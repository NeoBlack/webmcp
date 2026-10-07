..  include:: /Includes.rst.txt

.. _standards:

==============================
Standards and browser support
==============================

..  important::

    **Status as of 2026-10-07.** WebMCP moves fast. Everything on this page can
    change without notice; check the linked sources before relying on it.
    Statements are marked as *confirmed* (taken from a primary source) or
    *unconfirmed*.

..  contents::
    :local:
    :depth: 1

The specification
=================

*   **Confirmed:** WebMCP is a *Draft Community Group Report* of the W3C Web
    Machine Learning Community Group (latest draft: 2 October 2026). It is
    **not** a W3C Standard and not on the W3C Standards Track.
*   **Confirmed:** the entry point is :js:`document.modelContext`. Tools are
    registered one at a time with :js:`registerTool(tool, { signal })` and
    unregistered by aborting that ``AbortSignal``; there is no
    ``unregisterTool()``. ``provideContext()`` and ``clearContext()`` were
    removed from the specification (`PR #132
    <https://github.com/webmachinelearning/webmcp/pull/132>`__).
*   **Confirmed:** tool annotations are ``readOnlyHint``,
    ``untrustedContentHint``, ``consequentialHint`` and ``debugging`` (all
    boolean). See :ref:`developer` for how this extension sets them.
*   **Confirmed:** the declarative (HTML form) API is still a TODO in the
    specification; only a separate explainer exists. This extension does not
    use it.
*   **Confirmed:** open proposals may still change the API, among them renaming
    ``execute`` to ``run`` (`#158
    <https://github.com/webmachinelearning/webmcp/issues/158>`__) and returning
    the registered tool from ``registerTool()`` (`#234
    <https://github.com/webmachinelearning/webmcp/issues/234>`__). The runtime
    already accepts any return value of ``registerTool()``.

Browsers
========

..  list-table::
    :header-rows: 1
    :widths: 20 50 30

    *   -   Browser
        -   Status
        -   Confidence
    *   -   Chrome
        -   Origin trial for desktop, milestones 149–156. Behind the flag
            ``chrome://flags/#enable-webmcp-testing`` for local testing. A
            default shipping milestone is **not set** on chromestatus.
        -   Confirmed (shipping date unconfirmed)
    *   -   Microsoft Edge
        -   Origin trial, expiring 2027-03-30.
        -   Confirmed
    *   -   Brave
        -   Experimental.
        -   Unconfirmed
    *   -   Firefox
        -   Mozilla standards position: *neutral*.
        -   Confirmed (position label)
    *   -   Safari / WebKit
        -   WebKit standards position: *oppose*.
        -   Confirmed (position label)

There is **no cross-browser consensus**. Expect WebMCP to be available only in
Chromium-based browsers, and only for some visitors, for the foreseeable future.

..  note::

    Chrome is reported to have deprecated :js:`navigator.modelContext` in
    favour of :js:`document.modelContext` (Chrome 150). This could not be
    confirmed from a Chrome source and is therefore *unconfirmed*. The
    ``@mcp-b/webmcp-polyfill`` package still offers the navigator alias as
    deprecated in version 5; its upcoming major no longer provides it. This
    extension neither bundles nor requires a polyfill; see
    :confval:`legacyNavigatorFallback <dataprocessor-legacynavigatorfallback>`.

Agents
======

*   **Confirmed:** the built-in browser of the ChatGPT desktop app uses WebMCP
    site tools. It only discovers tools of the top-level document (not
    iframes), runs a safety review per call and requires specific plans and
    models — see OpenAI's documentation below.
*   **Unconfirmed:** support in Gemini in Chrome has been announced but not
    confirmed by a primary source at the time of writing.

What this means for site operators
==================================

*   Treat WebMCP as **progressive enhancement**. Every page must work fully
    without it — most visitors' browsers do not offer the API.
*   The extension does nothing when the API is missing: no errors, no console
    output, no changes to the page.
*   Because the specification can still change, keep the extension up to date;
    until version 1.0.0 its API may change between minor versions.

Sources
=======

Primary sources:

*   `WebMCP specification draft <https://webmachinelearning.github.io/webmcp/>`__
    (W3C Web Machine Learning Community Group)
*   `webmcp issue #101 <https://github.com/webmachinelearning/webmcp/issues/101>`__
    — ``provideContext()`` allows overwriting previously registered tools
*   `webmcp PR #132 <https://github.com/webmachinelearning/webmcp/pull/132>`__
    — removal of ``provideContext()`` and ``clearContext()``
*   `Chrome: Secure tools <https://developer.chrome.com/docs/ai/webmcp/secure-tools>`__
    (Google guidance, not part of the specification)
*   `Chrome Platform Status: WebMCP <https://chromestatus.com/feature/5117755740913664>`__
*   `Microsoft Edge origin trials
    <https://developer.microsoft.com/en-us/microsoft-edge/origin-trials/trials/0b76fe60-b266-458e-a285-04e375c0c31a>`__
*   `Mozilla standards position #1412
    <https://github.com/mozilla/standards-positions/issues/1412>`__
*   `WebKit standards position #670
    <https://github.com/WebKit/standards-positions/issues/670>`__
*   `OpenAI: WebMCP in ChatGPT <https://learn.chatgpt.com/docs/webmcp>`__
*   `OpenAI Help: Using site tools in the ChatGPT desktop app
    <https://help.openai.com/en/articles/20001423-using-site-tools-in-the-chatgpt-desktop-app>`__

Secondary source (not independently confirmed):

*   `@mcp-b/webmcp-polyfill <https://www.npmjs.com/package/@mcp-b/webmcp-polyfill>`__
    package description and changelog of the related ``@mcp-b`` packages
    (navigator alias deprecation)
