..  include:: /Includes.rst.txt

.. _developer:

===============
Writing tools
===============

A tool is a PHP class implementing
:php:`\Neoblack\Webmcp\Tool\ToolProviderInterface`. Thanks to the
``#[AutoconfigureTag('webmcp.tool')]`` attribute on the interface, any
autoconfigured service implementing it is picked up automatically – in this
extension, a site package, or any third-party extension. No manual
:file:`Services.yaml` wiring is needed.

The interface
=============

..  code-block:: php

    interface ToolProviderInterface
    {
        // Context-free, stable name. Used for the analytics whitelist before
        // the frontend is resolved. Must equal the Manifest name.
        public function name(): string;

        // Return the tool's Manifest, or null to omit it for this request
        // (e.g. a blog tool on a site without a blog). $processedData carries
        // the results of data processors that ran earlier in the same content
        // object, so you can build on them (e.g. a menu).
        public function manifest(ContentObjectRenderer $cObj, array $processedData): ?Manifest;
    }

The manifest
============

..  code-block:: php

    new Manifest(
        name: 'search_articles',
        description: 'Search all articles …',
        inputSchema: [ /* JSON schema for the arguments */ ],
        primitive: Primitive::Search,
        data: [ /* primitive-specific payload, see below */ ],
        moduleUrl: null, // optional escape hatch, see below
    );

The runtime injects a ``client`` string property into every tool's input schema
automatically (for the optional analytics hint), so you do not declare it
yourself.

Primitives
==========

Every tool maps to exactly one primitive. The generic runtime interprets the
``data`` payload; you never write JavaScript.

navigate
--------

Navigate to a URL chosen from a fixed option set.

..  code-block:: php

    primitive: Primitive::Navigate,
    data: [
        'param' => 'kategorie',                       // which argument selects
        'options' => [
            ['match' => 'software', 'label' => 'Software', 'url' => 'https://…'],
        ],
        'messages' => [                               // optional templates
            'success' => 'Navigating to „{label}".',
            'unknown' => 'Unknown option. Available: {options}.',
        ],
    ]

search
------

Fetch a same-origin JSON index, filter it by the query terms, return structured
hits.

..  code-block:: php

    primitive: Primitive::Search,
    data: [
        'indexUrl' => 'https://…/index.json',
        'queryParam' => 'query',
        'limitParam' => 'limit',
        'limitDefault' => 10,
        'queryRequired' => true,                      // false: list all when empty
        'searchFields' => ['title', 'teaser'],        // fields searched
        'resultKey' => 'results',
        'resultFields' => ['title' => 'title', 'cat' => 'categoryLabel'],
        'deepLinkTemplate' => 'https://…/blog#q={query}', // optional
        'text' => [                                   // optional output templates
            'heading' => '{count} hits for „{query}“:',
            'headingAll' => '{count} entries:',
            'line' => '{n}. {title} – {url}',
            'emptyQuery' => 'No hits for „{query}“.',
            'emptyAll' => 'No entries.',
        ],
    ]

``resultFields`` may be a list (pick fields 1:1) or a map (``output => source``
rename); omit it to pass items through unchanged.

mailto
------

Build a pre-filled ``mailto:`` link and open it. No server storage.

..  code-block:: php

    primitive: Primitive::Mailto,
    data: [
        'to' => base64_encode('me@example.org'),      // base64, kept out of source
        'subjectTemplate' => 'Request – {anliegen}',
        'bodyLines' => [                              // "Label: value" lines
            ['label' => 'Name', 'param' => 'name'],
            ['label' => 'Organisation', 'param' => 'org', 'optional' => true],
        ],
        'messageParam' => 'message',                  // free text block at the end
        'successTemplate' => 'A pre-filled e-mail to {to} has been opened.',
    ]

static
------

Return a curated list verbatim.

..  code-block:: php

    primitive: Primitive::StaticList,
    data: [
        'items' => [['title' => 'Software', 'url' => 'https://…']],
        'resultKey' => 'services',
        'text' => ['heading' => 'Services:', 'line' => '{n}. {title} – {url}'],
    ]

Template placeholders
=====================

The text templates use ``{field}`` placeholders filled from the item (or, for
headings, from ``{count}`` / ``{query}``). ``{n}`` yields the 1-based index of
the current line.

Escape hatch
============

If no primitive fits, leave ``data`` minimal and set ``moduleUrl`` to the URL of
an ES module exporting ``execute(args, ctx)``. The runtime imports it on first
call and delegates to it.

Analytics
=========

Every tool call sends a same-origin beacon to the configured endpoint. The
:php:`ToolRegistry::toolNames()` list (all registered providers) is the
whitelist the ingest middleware validates against – it follows your tools
automatically.
