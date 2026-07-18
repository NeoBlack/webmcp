..  include:: /Includes.rst.txt

.. _installation:

============
Installation
============

Composer
========

Install the extension with Composer:

..  code-block:: bash

    composer require neoblack/webmcp

Then set up the extension (creates the analytics table
``tx_neoblackwebmcp_event``):

..  code-block:: bash

    vendor/bin/typo3 extension:setup --extension neoblack_webmcp

Requirements
============

*   TYPO3 v14.3+
*   PHP 8.2+

The extension ships no site configuration of its own. It only becomes active
once at least one tool provider is registered and the
:php:`ToolManifestProcessor` plus the :file:`webmcp.js` runtime are wired into
your site (see :ref:`configuration`). Registering the tools themselves is
described in :ref:`developer`.
