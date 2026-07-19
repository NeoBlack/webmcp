..  include:: /Includes.rst.txt

.. _developer:

=============
Writing tools
=============

A tool is a PHP class implementing
:php:`\Neoblack\Webmcp\Tool\ToolProviderInterface`. Thanks to the
``#[AutoconfigureTag('webmcp.tool')]`` attribute on the interface, any
autoconfigured service implementing it is picked up automatically – in this
extension, a site package, or any third-party extension. No manual
:file:`Services.yaml` wiring is needed.

..  contents::
    :local:
    :depth: 1

The interface
=============

..  php:namespace:: Neoblack\Webmcp\Tool

..  php:interface:: ToolProviderInterface

    Implemented by every tool and tagged ``webmcp.tool`` via the interface's
    ``#[AutoconfigureTag]`` attribute.

    ..  php:method:: name()

        Context-free, stable tool name. Used for the analytics whitelist before
        the frontend is resolved, so it must equal the :php:`Manifest` name.

        :returns: ``string`` – the stable tool name

    ..  php:method:: manifest($cObj, $processedData)

        Build the tool's manifest for the current request.

        :param ContentObjectRenderer $cObj: the current content object renderer
        :param array $processedData: results of data processors that ran earlier
            in the same content object, so you can build on them (e.g. a menu)
        :returns: a :php:`Manifest`, or ``null`` to omit the tool for this
            request (e.g. a blog tool on a site without a blog)

The manifest
============

..  php:class:: Manifest

    The serialisable description of a single tool. A provider returns one of
    these; the data processor collects them into the page's JSON block.

Construct it with named arguments:

..  code-block:: php
    :caption: Building a Manifest

    new Manifest(
        name: 'search_articles',
        description: 'Search all articles …',
        inputSchema: [ /* JSON schema for the arguments */ ],
        primitive: Primitive::Search,
        data: [ /* primitive-specific payload, see below */ ],
        moduleUrl: null, // optional escape hatch, see below
    );

..  list-table::
    :header-rows: 1
    :widths: 20 20 60

    *   -   Argument
        -   Type
        -   Purpose
    *   -   ``name``
        -   ``string``
        -   Tool name; must equal :php:`ToolProviderInterface::name()`.
    *   -   ``description``
        -   ``string``
        -   Human-readable description shown to the agent.
    *   -   ``inputSchema``
        -   ``array``
        -   JSON schema for the tool arguments.
    *   -   ``primitive``
        -   :php:`Primitive`
        -   One of the four behaviour primitives.
    *   -   ``data``
        -   ``array``
        -   Primitive-specific payload (see below).
    *   -   ``moduleUrl``
        -   ``?string``
        -   Optional ES-module URL for the escape hatch.
    *   -   ``readOnly``
        -   ``?bool``
        -   Override the read-only hint. ``null`` (default) derives it from the
            primitive; see below.
    *   -   ``title``
        -   ``?string``
        -   Optional human-readable label for UI display. The machine-stable
            ``name`` is used when omitted.
    *   -   ``untrustedContent``
        -   ``?bool``
        -   Override the untrusted-content hint. ``null`` (default) derives it from
            the primitive; see below.

..  note::

    The runtime injects a ``client`` string property into every tool's input
    schema automatically (for the optional analytics hint), so you do not declare
    it yourself.

Read-only hint
==============

Each manifest carries a WebMCP ``annotations.readOnlyHint`` flag telling the agent
whether the tool merely reads state or changes it. Agents use it to decide whether
a call may run without user confirmation.

The value is derived from the primitive: ``search`` and ``static`` are read-only,
``navigate`` (changes the browser location) and ``mailto`` (opens a mail client)
are not. Pass ``readOnly: true``/``false`` explicitly to override the default — for
example when an escape-hatch module built on the ``search`` primitive actually
mutates state.

Untrusted-content hint
======================

The manifest also carries a WebMCP ``annotations.untrustedContentHint`` flag. It
tells the agent that the tool's *output* may contain untrusted, third-party data
that should be treated with caution — a prompt-injection defence.

It is derived from the primitive: only ``search`` is flagged, because its results
come from a JSON index that can hold user-generated content. ``static`` is curated,
and ``navigate`` / ``mailto`` only return messages the runtime built itself, so all
three default to ``false``. Pass ``untrustedContent: true``/``false`` to override —
for instance when a ``static`` list is assembled from user-supplied data, or an
escape-hatch module returns third-party content.

Primitives
==========

Every tool maps to exactly one primitive. The generic runtime interprets the
``data`` payload; you never write JavaScript.

..  tabs::

    ..  group-tab:: navigate

        Navigate the browser to a URL chosen from a fixed option set.

        ..  code-block:: php
            :caption: navigate primitive

            primitive: Primitive::Navigate,
            data: [
                'param' => 'kategorie',                       // which argument selects
                'options' => [
                    ['match' => 'software', 'label' => 'Software', 'url' => 'https://…'],
                ],
                'confirm' => 'Open „{label}"?',               // optional; see below
                'messages' => [                               // optional templates
                    'success' => 'Navigating to „{label}".',
                    'unknown' => 'Unknown option. Available: {options}.',
                    'cancelled' => 'Navigation to „{label}" was cancelled.',
                ],
            ]

    ..  group-tab:: search

        Fetch a same-origin JSON index, filter it by the query terms, return
        structured hits.

        ..  code-block:: php
            :caption: search primitive

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

        ..  tip::

            ``resultFields`` may be a list (pick fields 1:1) or a map
            (``output => source`` rename); omit it to pass items through
            unchanged.

        ..  uml::
            :caption: How the search primitive resolves a query

            actor Agent
            participant "webmcp.js" as RT
            participant "JSON index\n(same-origin)" as IDX

            Agent -> RT : call with query + limit
            RT -> RT : send analytics beacon
            RT -> IDX : fetch indexUrl\n(cached per page)
            IDX --> RT : items
            RT -> RT : filter by searchFields\n(all query terms must match)
            RT -> RT : slice to limit,\nproject resultFields
            RT --> Agent : text + structuredContent (hits)

    ..  group-tab:: mailto

        Build a pre-filled ``mailto:`` link and open it. No server storage.

        ..  code-block:: php
            :caption: mailto primitive

            primitive: Primitive::Mailto,
            data: [
                'to' => base64_encode('me@example.org'),      // base64, kept out of source
                'subjectTemplate' => 'Request – {anliegen}',
                'bodyLines' => [                              // "Label: value" lines
                    ['label' => 'Name', 'param' => 'name'],
                    ['label' => 'Organisation', 'param' => 'org', 'optional' => true],
                ],
                'messageParam' => 'message',                  // free text block at the end
                'confirm' => 'Send this e-mail to {to}?',      // optional; see below
                'successTemplate' => 'A pre-filled e-mail to {to} has been opened.',
            ]

    ..  group-tab:: static

        Return a curated list verbatim.

        ..  code-block:: php
            :caption: static primitive

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

Confirming side effects
=======================

The ``navigate`` and ``mailto`` primitives change state — they move the browser or
open a mail client. Set a ``confirm`` message on their ``data`` to require a
human-in-the-loop confirmation *before* the side effect runs. The runtime prefers
the WebMCP client's ``requestUserInteraction()`` (which lets the agent surface the
page to the user first) and falls back to a plain ``confirm()`` when the client
does not provide it. If the user declines, the tool returns an ``isError`` result
(``navigate`` uses the ``cancelled`` message when set) and the side effect never
happens.

Omit ``confirm`` to keep the previous behaviour — the tool runs without asking. The
``confirm`` string is filled with the same ``{placeholders}`` as the other
messages (``navigate``: the chosen option; ``mailto``: ``{to}`` and ``{subject}``).

Escape hatch
============

If no primitive fits, leave ``primitive`` at any value, keep ``data`` minimal and
set ``moduleUrl`` to the URL of an ES module exporting an ``execute`` function.
The runtime imports the module lazily — on the tool's first call — and delegates
to it. A ``moduleUrl`` is only used when no built-in primitive matches, so a
custom module always wins the fallback, never overrides a primitive.

..  uml::
    :caption: Escape hatch — a custom module loads lazily on first call

    actor Agent
    participant "webmcp.js" as RT
    participant "your-tool.js\n(ES module)" as MOD

    Agent -> RT : first tool call
    RT -> RT : send analytics beacon
    RT -> MOD : import(moduleUrl)\n(once, then cached)
    MOD --> RT : { execute }
    RT -> MOD : execute(args, ctx)
    MOD --> RT : MCP result\n(content, structuredContent)
    RT --> Agent : result

The contract
------------

..  code-block:: javascript
    :caption: your-tool.js — custom execute() implementation

    // served same-origin, or CORS-enabled for dynamic import
    export function execute(args, ctx) {
        // args: the tool arguments the agent passed, already normalised
        //       (the runtime unwraps an { arguments: {…} } envelope for you).
        //       Includes the optional `client` analytics hint if the agent set it.
        //
        // ctx:  { tool, config, client }
        //   ctx.tool   – this tool's manifest object
        //                { name, description, inputSchema, primitive, data, moduleUrl }
        //   ctx.config – the whole page manifest { endpoint, tools: [...] }
        //   ctx.client – the WebMCP ModelContextClient (may be undefined); call
        //                ctx.client.requestUserInteraction(cb) for confirmations

        // Return an MCP tool result. `content` is required; `structuredContent`
        // is optional machine-readable output. You may return a Promise.
        return {
            content: [{ type: 'text', text: 'Done.' }],
            structuredContent: { ok: true },
        };
    }

..  tip::

    **Analytics is already handled.** The runtime fires the usage beacon before
    importing your module, so you do not call the endpoint yourself. Keep ``data``
    for your own config and read it from :js:`ctx.tool.data`.

..  warning::

    **Errors surface to the agent.** A thrown error or rejected Promise
    propagates as the tool call's failure — for a recoverable "no match /
    unavailable" case return a normal result with ``isError: true`` instead of
    throwing, so the agent learns the call failed but the page stays healthy:

    ..  code-block:: javascript

        return { content: [{ type: 'text', text: 'No match.' }], isError: true };

    Loading is lazy and best-effort: a failed import fails only that one call,
    nothing else on the page is affected. The built-in primitives already flag
    their failure paths (e.g. ``navigate`` with an unknown option) this way.

Analytics
=========

Every tool call sends a same-origin beacon to the configured endpoint. The
:php:`\Neoblack\Webmcp\Registry\ToolRegistry::toolNames()` list (all registered
providers) is the whitelist the ingest middleware validates against – it follows
your tools automatically.

..  seealso::

    *   :ref:`quickstart` – a full end-to-end example using the ``static`` primitive.
    *   :ref:`architecture` – how providers, the registry, the processor and the
        runtime fit together.
    *   :ref:`analytics` – what the beacon records and how the ingest endpoint is
        protected.
