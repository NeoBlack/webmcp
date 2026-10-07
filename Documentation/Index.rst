..  include:: /Includes.rst.txt

.. _start:

=====================
WebMCP tool for TYPO3
=====================

:Extension key:
    neoblack_webmcp

:Package name:
    neoblack/webmcp

:Version:
    |release|

:Language:
    en

:Author:
    Frank Nägler & contributors

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

----

A declarative `WebMCP <https://github.com/webmachinelearning/webmcp>`__ tool
framework for TYPO3. Define agent tools server-side as small PHP providers; the
extension collects them into a per-page manifest and a generic JavaScript
runtime registers each tool individually via
:js:`document.modelContext.registerTool()` (with an optional, switchable
fallback to the deprecated :js:`navigator.modelContext`). Optional, privacy-preserving first-party analytics
are included.

..  warning::

    **Experimental.** Both the underlying
    `WebMCP <https://github.com/webmachinelearning/webmcp>`__ proposal and this
    extension's API may change or break at any time. See :ref:`introduction` for
    details.

----

Getting started
===============

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :card-height: 100

    ..  card:: :ref:`✨ Introduction <introduction>`

        What WebMCP is, what this extension adds, and how everything degrades
        gracefully for regular visitors.

    ..  card:: :ref:`🚀 Quickstart <quickstart>`

        From a fresh install to a working tool an agent can call — in three
        steps, no JavaScript required.

    ..  card:: :ref:`⬇️ Installation <installation>`

        Composer and requirements. What the extension does — and does not — do
        out of the box.

    ..  card:: :ref:`🛠️ Writing tools <developer>`

        The provider interface, the four behaviour primitives and the ES-module
        escape hatch.

    ..  card:: :ref:`⚙️ Configuration <configuration>`

        Extension settings and how to wire the manifest, JSON block and runtime
        into your site.

    ..  card:: :ref:`🧭 Architecture <architecture>`

        The two request flows — emitting tools and ingesting usage events — and
        the classes behind them.

    ..  card:: :ref:`📊 Analytics <analytics>`

        What the first-party usage log records, how long it is kept and how the
        public endpoint is protected.

    ..  card:: :ref:`🌐 Standards and browser support <standards>`

        Where the WebMCP specification stands, which browsers and agents
        support it, and what is still open.

    ..  card:: :ref:`🛡️ Security considerations <security>`

        Threats named by the specification, how the extension mitigates them
        and what remains your responsibility.

    ..  card:: :ref:`🔧 Troubleshooting <troubleshooting>`

        Why tools might not register, providers not fire, or events not land —
        and how to check each.

    ..  card:: :ref:`⏫ Upgrading <upgrading>`

        What changes between versions and whether you need to act.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Quickstart/Index
    Configuration/Index
    Developer/Index
    Architecture/Index
    Analytics/Index
    Standards/Index
    Security/Index
    Troubleshooting/Index
    Upgrading/Index
    Changelog/Index
