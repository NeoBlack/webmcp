..  include:: /Includes.rst.txt

.. _installation:

============
Installation
============

Requirements
============

..  list-table::
    :header-rows: 1
    :widths: 30 70

    *   -   Requirement
        -   Version
    *   -   TYPO3
        -   14.3 or newer
    *   -   PHP
        -   8.2, 8.3 or 8.4

Install the extension
=====================

..  tabs::

    ..  group-tab:: Composer

        ..  code-block:: bash
            :caption: Require the package

            composer require neoblack/webmcp

    ..  group-tab:: Classic (TER)

        Download and install :guilabel:`neoblack_webmcp` from the
        `TYPO3 Extension Repository <https://extensions.typo3.org/>`__ via the
        :guilabel:`Admin Tools > Extensions` backend module.

Set up the extension
====================

Run the setup command to create the analytics table
``tx_neoblackwebmcp_event``:

..  code-block:: bash
    :caption: Set up the extension

    vendor/bin/typo3 extension:setup --extension neoblack_webmcp

..  note::

    The extension ships no site configuration of its own. It only becomes active
    once at least one tool provider is registered and the
    :php:`\Neoblack\Webmcp\DataProcessing\ToolManifestProcessor` plus the
    :file:`webmcp.js` runtime are wired into your site.

..  seealso::

    *   :ref:`quickstart` – build your first tool end to end.
    *   :ref:`configuration` – wire the manifest and runtime into your site.
    *   :ref:`developer` – register the tools themselves.
