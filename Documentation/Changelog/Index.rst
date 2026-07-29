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

*   **Added (experimental):** an EXT:form bridge. A TYPO3 form opted in with the
    ``renderingOptions.webmcp`` flag is exposed as a WebMCP tool; an agent fills
    and submits it, and the form's own server-side validation and finishers run.
    Only single-step forms of supported field types are offered — a form with any
    unsupported field is refused, both at save time (with a clear editor message)
    and at registration. Requires ``typo3/cms-form`` (a suggestion); the extension
    stays installable without it. See :ref:`form`. Because EXT:form exposes no
    stable submission API, this relies on internal API and may need maintenance
    across TYPO3 updates.


*   *(nothing yet)*

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
