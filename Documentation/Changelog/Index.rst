..  include:: /Includes.rst.txt

.. _changelog:

=========
Changelog
=========

This extension is experimental. Until a ``1.0.0`` release, the API described in
this documentation may change or break between versions without notice.

..  note::

    The canonical, commit-level history lives in the Git repository. This page
    records notable, user-facing changes per release.

Unreleased
==========

Aligns the runtime with the WebMCP Community Group draft of 2026-10-02. See :ref:`upgrading` for what to check.

Changed
-------

*   **Behavior change:** the runtime no longer uses ``provideContext()``, which
    the specification removed. Every tool is registered individually via
    ``registerTool()`` with its own ``AbortSignal``; tools therefore appear one
    after another instead of atomically. A tool whose registration fails (e.g.
    duplicate name) is skipped with a single ``console.warn``; the others are
    unaffected. Tools are unregistered on ``pagehide`` and registered again when
    the page returns from the back/forward cache.
*   **Behavior change:** tools are only registered in the top-level document,
    never inside iframes.
*   **Behavior change:** the text output of a tool call is capped at 1,500
    characters by default (see ``outputLimit``).
*   The deprecated ``navigator.modelContext`` fallback is isolated and can be
    switched off.

Added
-----

*   ``consequential`` argument on :php:`Manifest`, emitted as
    ``annotations.consequentialHint`` (derived from the primitive: only
    ``mailto`` is flagged).
*   ``debugging`` argument on :php:`Manifest`, emitted as
    ``annotations.debugging`` only when ``true``.
*   Data processor options ``legacyNavigatorFallback`` (default ``1``) and
    ``outputLimit`` (default ``1500``, ``0`` = unlimited).
*   :php:`ManifestValidator`: the data processor logs warnings for tool or
    parameter names over 30 characters, descriptions over 500, parameter
    descriptions over 150 (Chrome recommendations) and names outside the
    specification's pattern. Tools are still emitted.
*   Runtime tests (Node.js built-in test runner) and documentation chapters
    :ref:`standards`, :ref:`security` and :ref:`upgrading`.

Removed
-------

*   Use of ``provideContext()`` in the runtime.

Migration
---------

No action is required for tools built on the four primitives. Escape-hatch
modules that called ``provideContext()`` themselves must switch to
``registerTool()``. Check tools that rely on long text output against the new
``outputLimit``. Details: :ref:`upgrading`.

0.3.0 - 2026-07-20
==================

*   **Added:** each manifest now also carries the WebMCP
    ``annotations.untrustedContentHint`` flag, warning the agent that a tool's
    output may contain untrusted third-party data. It is derived from the primitive
    (only ``search`` is flagged) and overridable via the new ``untrustedContent``
    argument on :php:`Manifest`.
*   **Added:** :php:`Manifest` gained an optional ``title`` argument — a
    human-readable label for UI display, distinct from the machine-stable
    ``name``. It is emitted into the manifest and forwarded to the tool descriptor
    only when set.
*   **Changed:** the runtime now registers its tools atomically via
    ``provideContext({ tools })`` (the WebMCP spec's primary entry point), falling
    back to per-tool ``registerTool`` only where ``provideContext`` is absent.
    Manifest entries that reuse a name already taken are dropped, so a duplicate
    tool name no longer aborts registration.
*   **Added:** the ``navigate`` and ``mailto`` primitives accept an optional
    ``confirm`` message that triggers a human-in-the-loop confirmation before the
    side effect runs, via the WebMCP client's ``requestUserInteraction()`` (with a
    ``confirm()`` fallback). Escape-hatch modules now receive the client as
    ``ctx.client``. Without a ``confirm`` message the behaviour is unchanged.
*   **Changed:** the built-in primitives now flag genuine failure paths
    (``navigate`` with an unknown option, ``mailto`` with no configured contact)
    with the WebMCP ``isError`` result flag, so agents can tell a failed call from
    a successful one. An empty but valid search result stays a success.
*   **Added:** each tool manifest now carries a WebMCP
    ``annotations.readOnlyHint`` flag, derived from the primitive (``search`` and
    ``static`` are read-only; ``navigate`` and ``mailto`` are not) and overridable
    via the new ``readOnly`` argument on :php:`Manifest`. The generic runtime
    forwards it to ``registerTool`` so agents can tell read-only tools from
    state-changing ones.

0.2.0 - 2026-07-19
==================

*   **Breaking:** the backend module moved from the :guilabel:`Web` group to
    :guilabel:`System`, and its route identifier changed from ``web_webmcp`` to
    ``system_webmcp``. The module no longer carries a page tree — its statistics
    are site-wide. Update any backend user/group access rights and hardcoded
    module links accordingly.
*   **Changed:** the backend module icon was redrawn in the three-colour TYPO3
    v14 icon style.
*   **Added:** an :file:`ext_emconf.php` so the extension can be published to and
    installed from the TER.
*   **Documentation:** added a :ref:`quickstart`, an :ref:`architecture` overview,
    a dedicated :ref:`analytics` chapter (data model, retention, endpoint
    hardening), a :ref:`troubleshooting` guide and this changelog.

0.1.0
=====

Initial experimental release.

*   Declarative tool framework: define agent tools as server-side PHP providers
    (:php:`ToolProviderInterface`) collected into a per-page manifest.
*   Four behaviour primitives interpreted by a single generic runtime
    (:file:`webmcp.js`): ``navigate``, ``search``, ``mailto``, ``static``.
*   Escape hatch: a tool may point at its own ES module for behaviour no
    primitive covers.
*   Optional, privacy-preserving first-party analytics: anonymous per-call
    logging via ``/webmcp-event``, rate limiting, and a :guilabel:`System > WebMCP`
    backend module.
*   Requires TYPO3 v14.3+ and PHP 8.2+.
