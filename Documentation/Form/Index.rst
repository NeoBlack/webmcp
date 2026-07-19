..  include:: /Includes.rst.txt

.. _form:

=================
EXT:form bridge
=================

Expose a TYPO3 form (from the *Form Framework*, ``typo3/cms-form``) as a WebMCP
tool: an agent reads the form's fields as a tool schema, fills them, and submits
— server-side validation and finishers run exactly as for a human submission.

..  warning::

    **Experimental — and more so than the rest of this extension.** The Form
    Framework exposes **no stable public API** for programmatic submission: the
    classes this bridge relies on to run a form's own validation and finishers
    (``FormRuntime``, ``FormState``, ``ProcessingRule``, ``FinisherContext``) are
    all marked ``@internal`` and may change in any TYPO3 patch release. All such
    use is isolated in a single service so breakage stays local, but expect this
    feature to need maintenance across TYPO3 updates. Reading a form's structure
    to build the schema uses non-``@internal`` interfaces and is more stable.

..  contents::
    :local:
    :depth: 1

Requirements
============

*   ``typo3/cms-form`` installed (this extension only *suggests* it).
*   The form is opted in explicitly (see below). Forms are **never** exposed
    implicitly — a form becomes a WebMCP tool only when you ask for it.

Opting a form in
================

Set the ``webmcp`` rendering option on the form definition. ``renderingOptions``
is the stable, documented place for form-level configuration:

..  code-block:: yaml
    :caption: my_form.form.yaml

    renderingOptions:
      webmcp:
        enable: true
        description: 'Send a message to our team.'   # shown to the agent
        confirm: 'Send this message?'                # optional confirmation prompt
        successMessage: 'Thanks, we got your message.'  # optional reply on success

..  list-table::
    :header-rows: 1
    :widths: 25 75

    *   -   Option
        -   Meaning
    *   -   ``enable``
        -   Required. ``true`` exposes the form as a WebMCP tool.
    *   -   ``description``
        -   Shown to the agent as the tool description. Defaults to the form label.
    *   -   ``confirm``
        -   Optional. If set, the runtime asks the user to confirm before
            submitting (via the WebMCP client's confirmation, else ``confirm()``).
    *   -   ``successMessage``
        -   Optional. Returned to the agent after a successful submission.

Supported field types
=====================

Only single-step forms built from the standard form elements are supported. Each
element is classified once — this table is the single source of truth, mirrored
in code by :php:`\Neoblack\Webmcp\Form\FieldSupport`.

..  list-table::
    :header-rows: 1
    :widths: 25 20 55

    *   -   Element
        -   Handling
        -   Schema mapping
    *   -   Text, Textarea, Password, AdvancedPassword, Telephone
        -   Fillable
        -   ``string``
    *   -   Email
        -   Fillable
        -   ``string`` (``format: email``)
    *   -   Url
        -   Fillable
        -   ``string`` (``format: uri``)
    *   -   Number
        -   Fillable
        -   ``number``
    *   -   Date, DatePicker
        -   Fillable
        -   ``string`` (``format: date``)
    *   -   Checkbox
        -   Fillable
        -   ``boolean``
    *   -   RadioButton, SingleSelect
        -   Fillable
        -   ``string`` with ``enum`` from the element's options
    *   -   MultiCheckbox, MultiSelect
        -   Fillable
        -   ``array`` of ``string`` (``enum`` from options)
    *   -   CountrySelect
        -   Fillable
        -   ``string``
    *   -   StaticText, ContentElement
        -   Display-only
        -   ignored (no input)
    *   -   Form, Page, Fieldset, GridRow, GridColumn
        -   Structural
        -   containers; their children are walked
    *   -   Hidden
        -   Special
        -   submitted with its default value, not shown to the agent
    *   -   Honeypot
        -   Special
        -   ignored
    *   -   **FileUpload, ImageUpload**
        -   **Unsupported**
        -   an agent cannot provide files
    *   -   **SummaryPage / multi-step (more than one page)**
        -   **Unsupported**
        -   not completable in a single call

Required fields are those carrying the ``NotEmpty`` validator; they become
``required`` in the schema.

Unsupported fields
==================

The policy is strict: **a form containing any unsupported field — or any unknown,
custom element type — is refused as a whole and never offered to an agent.** A
partial schema that would fail on submission is worse than no tool at all, so the
bridge does not skip unsupported fields and expose the rest.

Unknown or third-party element types are treated as unsupported on purpose: the
bridge fails safe rather than guessing at a mapping it cannot verify.

This is enforced at two points:

*   **When the form is saved** in the form editor — saving a WebMCP-enabled form
    that contains an unsupported field is refused, and the editor shows a clear
    error message naming the offending field. (The refusal is a persistence
    exception the editor already surfaces as its save-error message.)
*   **When the form is registered** for a page — an unsupported form is not
    exposed and the reason is logged.

How a submission works
======================

..  uml::
    :caption: From the agent's tool call to the form's finishers

    actor Agent
    participant "webmcp.js\n(form primitive)" as RT
    participant "/webmcp-form\n(middleware)" as EP
    participant "FormSubmissionService" as SVC

    Agent -> RT : call tool with field values
    RT -> RT : optional user confirmation
    RT -> EP : POST { token, values }
    EP -> EP : same-origin + rate limit + verify token
    EP -> SVC : submit(persistenceIdentifier, values)
    SVC -> SVC : validate via the form's processing rules
    alt invalid
        SVC --> Agent : { ok: false, errors }
    else valid
        SVC -> SVC : run finishers (e-mail, save…)
        SVC --> Agent : { ok: true, message }
    end

The tool submits to the same-origin ``/webmcp-form`` endpoint. Validation and
finishers are the form's own — the submission goes through EXT:form's server-side
machinery, exactly as a browser submission would, so a required field left empty
comes back as a per-field error the agent can correct and retry.

Finishers
=========

The form's configured finishers run on a successful submission. The **e-mail** and
**save-to-database** finishers work as usual. The **redirect** finisher is skipped
(it belongs to the browser response the agent does not have); the agent receives
the ``successMessage`` instead.

Security
========

*   **Signed token.** Each form tool carries an HMAC-signed token identifying its
    form. The agent cannot forge it or point the endpoint at another (or a
    non-opted-in) form; the endpoint re-checks that the form is still enabled and
    supported before submitting.
*   **Same-origin only**, rate-limited per client, like the analytics endpoint.

Limits
======

*   Single-step forms only; multi-step forms are refused.
*   No file uploads.
*   Redirect finishers are not executed.

..  warning::

    Because there is no stable EXT:form submission API, this feature is
    **experimental** (see the warning at the top). Verify it against your forms
    after a TYPO3 update.

Placement
=========

The manifest is emitted by the :php:`ToolManifestProcessor`. For a form tool to
appear, that processor must run **after** the form has been rendered on the page
(the form is discovered as it renders). Emit the WebMCP config block late in the
page — e.g. in the footer — rather than in the ``<head>``.
