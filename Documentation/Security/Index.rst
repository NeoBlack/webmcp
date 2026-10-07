..  include:: /Includes.rst.txt

.. _security:

=======================
Security considerations
=======================

WebMCP lets an AI agent act on a page on the user's behalf. That creates new
ways for content to influence the agent. This chapter summarises the threats
named by the specification, what the extension does about them, and what
remains your responsibility. There is no known exploit against a production
site so far; an academic demonstration of manipulating the tool surface at
runtime exists (`arXiv:2606.06387 <https://arxiv.org/abs/2606.06387>`__).

..  contents::
    :local:
    :depth: 1

Threats named by the specification
==================================

The specification's security section lists, among others:

*   **Metadata / description attacks (tool poisoning).** Tool names,
    descriptions and schemas are read by the model. Text placed there can try
    to steer the agent.
*   **Output injection.** A tool's result is fed back into the model. Content
    from editors, users or third parties in that result can carry instructions.
*   **Tool implementations as attack targets.** Bugs in the code behind a tool
    can be triggered by crafted arguments.
*   **Misrepresentation of intent** and **privacy leakage through
    over-parameterisation** — a tool that asks for more than it needs.

Why provideContext() is gone
============================

``provideContext()`` replaced the complete tool set of a page. Any script on the
page — including third-party scripts — could call it, wipe the tools registered
by others and register its own tool under a trusted name, then proxy the calls
(`issue #101 <https://github.com/webmachinelearning/webmcp/issues/101>`__). The
specification removed it; :js:`registerTool()` rejects a name that is already
taken.

What this means for you:

*   **Third-party scripts share your tool namespace.** Whoever registers a name
    first owns it. :file:`webmcp.js` is included deferred in the footer, so a
    script that runs earlier could take one of your names. Keep third-party
    scripts on pages with tools to a minimum and only from sources you trust.
*   **A failed registration is visible.** If another script took a name, the
    runtime skips that tool and logs one console warning naming it (see
    :ref:`troubleshooting`). It never silently replaces another script's tool.
*   Each registration carries its own ``AbortSignal``, so only the extension can
    unregister its own tools.

What the extension does
=======================

*   **Annotations.** Every tool carries ``readOnlyHint``,
    ``untrustedContentHint`` and ``consequentialHint`` with defaults derived
    from the primitive (see :ref:`developer`). ``search`` output is flagged as
    untrusted; ``mailto`` is flagged as consequential.
*   **Confirmation.** ``navigate`` and ``mailto`` can ask the user before acting
    (``confirm`` message).
*   **Output cap.** Text output is capped at :confval:`outputLimit
    <dataprocessor-outputlimit>` characters (default 1,500).
*   **Top-level only.** Tools are never registered inside iframes, and
    ``exposedTo`` is never set, so tools are not offered to other origins.
*   **Safe embedding.** The manifest is JSON-escaped (``\uXXXX`` for
    ``< > & ' "``), so editor-controlled values cannot break out of the
    :html:`<script>` block.
*   **Limits.** The manifest validator logs a warning for names and
    descriptions over Chrome's recommended sizes (see :ref:`developer`).

What you are responsible for
============================

*   **Set annotations honestly.** Override the defaults when a tool behaves
    differently — e.g. ``untrustedContent: true`` for a ``static`` list built
    from user input, ``consequential: true`` for an escape-hatch module that
    submits data.
*   **No sensitive data in tool output.** Anything a tool returns goes to the
    agent and, potentially, to its provider. Never return personal data,
    internal URLs or secrets.
*   **Keep descriptions factual.** Write them for the model, but never include
    instructions that try to steer the agent beyond the tool's purpose.
*   **Treat arguments as untrusted input** in escape-hatch modules.

..  note::

    **Agent identity is unsolved.** The page cannot reliably tell which agent —
    or whether an agent at all — calls a tool (`issue #105
    <https://github.com/webmachinelearning/webmcp/issues/105>`__). The analytics
    ``client`` hint is self-reported and must never be used for access
    decisions.

..  seealso::

    *   `Chrome: Secure tools <https://developer.chrome.com/docs/ai/webmcp/secure-tools>`__
    *   :ref:`standards`
