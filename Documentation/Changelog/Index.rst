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

*   **Breaking:** the backend module moved from the :guilabel:`Web` group to
    :guilabel:`System`, and its route identifier changed from ``web_webmcp`` to
    ``system_webmcp``. The module no longer carries a page tree — its statistics
    are site-wide. Update any backend user/group access rights and hardcoded
    module links accordingly.
*   **Changed:** the backend module icon was redrawn in the three-colour TYPO3
    v14 icon style.
*   **Added:** an ``ext_emconf.php`` so the extension can be published to and
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
