..  include:: /Includes.rst.txt

.. _start:

======
WebMCP
======

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

..  warning::

    **Experimental.** This extension is experimental and not yet ready for
    production use. It is built on top of
    `WebMCP <https://github.com/webmachinelearning/webmcp>`__, which is itself
    an experimental, early-stage proposal. Both the underlying specification
    and this extension's API may change or break at any time without notice.
    Use at your own risk.

A declarative `WebMCP <https://github.com/webmachinelearning/webmcp>`__ tool
framework for TYPO3. Define agent tools server-side as small PHP providers;
the extension collects them into a per-page manifest and a generic JavaScript
runtime registers each tool against ``document.modelContext`` /
``navigator.modelContext``. Optional, privacy-preserving first-party analytics
are included.

----

**Table of contents:**

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Developer/Index
