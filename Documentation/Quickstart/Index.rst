..  include:: /Includes.rst.txt

.. _quickstart:

==========
Quickstart
==========

This walkthrough takes you from a freshly installed extension to a working tool
that an AI agent can call on the page. It uses the ``static`` primitive, so no
JavaScript and no external index are required.

..  contents::
    :local:
    :depth: 1

Prerequisites
=============

*   The extension is installed and set up (see :ref:`installation`).
*   You have a site package where you can add PHP classes and TypoScript.

Step 1 – Write a tool provider
==============================

Create a small PHP class in your site package (or any extension). Because the
:php:`\Neoblack\Webmcp\Tool\ToolProviderInterface` carries the
``#[AutoconfigureTag('webmcp.tool')]`` attribute, an autoconfigured service
implementing it is picked up automatically — no :file:`Services.yaml` entry is
needed as long as your extension enables autoconfiguration.

..  code-block:: php
    :caption: EXT:my_sitepackage/Classes/Tool/ServicesToolProvider.php

    <?php

    declare(strict_types=1);

    namespace Vendor\SitePackage\Tool;

    use Neoblack\Webmcp\Tool\Manifest;
    use Neoblack\Webmcp\Tool\Primitive;
    use Neoblack\Webmcp\Tool\ToolProviderInterface;
    use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

    final class ServicesToolProvider implements ToolProviderInterface
    {
        public function name(): string
        {
            return 'list_services';
        }

        public function manifest(ContentObjectRenderer $cObj, array $processedData): ?Manifest
        {
            return new Manifest(
                name: 'list_services',
                description: 'List the services this company offers.',
                inputSchema: ['type' => 'object', 'properties' => new \stdClass()],
                primitive: Primitive::StaticList,
                data: [
                    'items' => [
                        ['title' => 'Consulting', 'url' => 'https://example.org/consulting'],
                        ['title' => 'Development', 'url' => 'https://example.org/development'],
                    ],
                    'resultKey' => 'services',
                    'text' => [
                        'heading' => 'Our services:',
                        'line' => '{n}. {title} – {url}',
                    ],
                ],
            );
        }
    }

..  note::

    If your site package does not autoconfigure services, add the tag manually:

    ..  code-block:: yaml
        :caption: EXT:my_sitepackage/Configuration/Services.yaml

        Vendor\SitePackage\Tool\ServicesToolProvider:
          tags:
            - name: webmcp.tool

Step 2 – Wire the manifest into the page
========================================

Add the three pieces described in :ref:`configuration` to your site package. In
short:

..  code-block:: typoscript
    :caption: TypoScript setup — data processor and runtime

    page.10.dataProcessing {
        40 = Neoblack\Webmcp\DataProcessing\ToolManifestProcessor
        40 {
            endpoint = /webmcp-event
            as = webmcpConfigJson
        }
    }

    page.includeJSFooter {
        webmcp = EXT:neoblack_webmcp/Resources/Public/JavaScript/webmcp.js
        webmcp.defer = 1
    }

Render the JSON block once in your page template:

..  code-block:: html
    :caption: Fluid page template — emit the manifest

    <f:if condition="{webmcpConfigJson}">
        <script type="application/json" id="webmcp-config"><f:format.raw>{webmcpConfigJson}</f:format.raw></script>
    </f:if>

Step 3 – Verify it in the browser
=================================

Reload a frontend page and open the browser's developer console.

#.  Confirm the manifest is on the page:

    ..  code-block:: javascript
        :caption: Console — inspect the manifest

        JSON.parse(document.getElementById('webmcp-config').textContent).tools

    You should see your ``list_services`` tool in the returned array.

#.  Confirm the runtime registered it against the ``ModelContext``:

    ..  code-block:: javascript
        :caption: Console — check for an agent surface

        document.modelContext || navigator.modelContext

    A ``ModelContext`` implementation is only present in agent-capable browsers
    (or Chrome with the origin trial enabled). If :js:`document.modelContext` and
    :js:`navigator.modelContext` are both ``undefined``, the page is fine — there
    is simply no agent surface to register against.

..  tip::

    Once an agent operates the page, it can discover ``list_services`` and call
    it; the runtime returns the curated list and (unless disabled) records one
    anonymous usage row visible in the :ref:`backend module <analytics>`.

Next steps
==========

*   Swap ``static`` for another primitive — ``search``, ``navigate`` or
    ``mailto`` — see :ref:`developer`.
*   Return ``null`` from :php:`manifest()` to hide a tool on pages where it does
    not apply.

..  seealso::

    *   :ref:`developer` – every primitive's payload and the escape hatch.
    *   :ref:`troubleshooting` – what to check if the tool does not show up.
    *   :ref:`analytics` – inspect usage in the :guilabel:`System > WebMCP` module.
