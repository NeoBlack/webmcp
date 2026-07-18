..  include:: /Includes.rst.txt

.. _analytics:

=========
Analytics
=========

The extension ships an optional, first-party usage log: one anonymous row per
tool call, visualised in a backend module. It is enabled by default and can be
switched off entirely (see :ref:`configuration`).

..  contents::
    :local:
    :depth: 1

What is recorded
================

Every tool call fires a small same-origin beacon
(``navigator.sendBeacon``, with a ``fetch`` fallback) to the ingest endpoint.
The :php:`EventMiddleware` appends exactly two values per call:

*   the **tool name** – validated against the registered providers
    (:php:`ToolRegistry::toolNames()`); events for unknown names are not stored.
*   a **client hint** – a short, self-reported or coarsely detected label of the
    calling agent.

Nothing else is stored: no free text (search queries, e-mail contents, names),
no cookies, no IP address, no user agent string.

The client hint
---------------

The hint is best-effort and resolved client-side in this order:

1.  the agent's optional ``client`` argument, if it sets one (the runtime adds a
    ``client`` property to every tool's input schema automatically);
2.  otherwise a coarse match against known agent markers in the User-Agent
    (e.g. ``Claude``, ``ChatGPT``, ``GeminiBot``);
3.  otherwise the literal ``unbekannt`` ("unknown").

Server-side the value is sanitised (letters, digits, space and ``._-/()`` only)
and capped at 64 characters before it is stored.

Data model
==========

Events live in a single append-only table, created by
:bash:`extension:setup`:

..  code-block:: text
    :caption: tx_neoblackwebmcp_event

    uid     int          primary key
    crdate  int          unix timestamp of the call
    tool    varchar(64)  registered tool name
    client  varchar(64)  sanitised client hint

There is no ``pid``, no relation to a user or session, and no additional
payload column — the schema itself makes it impossible to store PII.

Retention and deletion
======================

The table is append-only; the extension does **not** prune it automatically.
Because a row is fully anonymous, there is no per-subject deletion to perform,
but you remain free to trim or clear the log at any time, for example:

..  code-block:: sql

    -- drop everything older than 90 days
    DELETE FROM tx_neoblackwebmcp_event WHERE crdate < UNIX_TIMESTAMP() - 90*86400;

    -- clear the log completely
    TRUNCATE tx_neoblackwebmcp_event;

If you schedule this, a simple periodic ``DELETE`` on ``crdate`` is enough; the
``crdate`` column is indexed.

The ingest endpoint
===================

``POST /webmcp-event`` is public by design (agents call it without a backend
session). It is protected by several deliberate constraints:

*   **Feature-gated:** when ``analyticsEnabled`` is off the endpoint is inert and
    the request is passed through untouched.
*   **Same-origin guard:** requests whose ``Sec-Fetch-Site`` header is present
    and neither ``same-origin`` nor ``same-site`` are rejected with ``204``.
*   **Rate limited:** at most ``analyticsRateLimit`` calls per client IP per
    minute (default 60; ``0`` disables). Excess calls receive ``429``. The
    limiter stores only a hashed IP + time-window counter that expires after two
    windows — the plaintext IP is never persisted.
*   **Whitelisted:** only registered tool names are logged; anything else is
    handed down the middleware stack.

Backend module
==============

When analytics is enabled, the :guilabel:`System > WebMCP` module shows calls per
tool and per client, a per-day timeline and the most recent events. The
timeframe (7 / 30 / 90 days) and tool/client filters are selectable; the
accepted tool names are derived automatically from the registered providers.

The module is not page-related: it has no page tree and shows site-wide
figures, mirroring the event table which has no ``pid``. It lives in the
:guilabel:`System` menu; there is nothing to pick in a tree.

..  figure:: /Images/BackendModule.png
    :alt: The WebMCP backend module showing calls per tool and a timeline

    The WebMCP dashboard: calls per tool/client, and a per-day timeline.

..  figure:: /Images/BackendModule2.png
    :alt: The WebMCP backend module showing also the recent calls in a table

    The WebMCP dashboard: The most recent events.
