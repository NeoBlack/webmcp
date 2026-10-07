/* WebMCP – generic client runtime.
 *
 * Reads a declarative tool manifest from <script type="application/json"
 * id="webmcp-config"> (emitted by ToolManifestProcessor) and registers each
 * tool individually via ModelContext.registerTool(). Tool behaviour is
 * data-driven: every tool names a primitive (navigate | search | mailto |
 * static) whose generic interpreter lives here, so new tools are defined
 * entirely server-side. A tool that needs behaviour no primitive covers may
 * instead point `moduleUrl` at an ES module exporting execute(args, ctx).
 *
 * Progressive enhancement throughout: absent config, absent ModelContext, a
 * malformed manifest or an embedding iframe simply means nothing is
 * registered; regular visitors are never affected. */
(function () {
    'use strict';

    // Tools belong to the top-level document only: agents (e.g. ChatGPT's
    // built-in browser) ignore tools from iframes, and a page embedded by a
    // third party must not offer its tools through the embedder.
    if (window.top !== window.self) { return; }

    var cfgEl = document.getElementById('webmcp-config');
    if (!cfgEl) { return; }
    var config;
    try { config = JSON.parse(cfgEl.textContent || 'null'); } catch (e) { return; }
    if (!config || !Array.isArray(config.tools) || !config.tools.length) { return; }

    var supportsRegisterTool = function (candidate) {
        return !!candidate && typeof candidate.registerTool === 'function';
    };

    // DEPRECATED: navigator.modelContext is the pre-spec location of the API,
    // still served by Chrome's origin trial builds and polyfills. Kept as an
    // opt-out fallback (config.legacyNavigatorFallback, default on). Switch the
    // default to off, then remove this function, once Chrome and the polyfills
    // drop the alias. The extension deliberately logs nothing here.
    var legacyNavigatorModelContext = function () {
        return navigator.modelContext;
    };

    // The spec exposes ModelContext on the document (page-scoped); prefer it.
    var resolveModelContext = function () {
        if (supportsRegisterTool(document.modelContext)) { return document.modelContext; }
        if (config.legacyNavigatorFallback !== false) {
            var legacy = legacyNavigatorModelContext();
            if (supportsRegisterTool(legacy)) { return legacy; }
        }
        return null;
    };

    var mc = resolveModelContext();
    // Every registration carries an AbortSignal, the spec's only way to
    // unregister a tool again.
    if (!mc || typeof AbortController !== 'function') { return; }

    var endpoint = config.endpoint || '/webmcp-event';

    // ---- helpers ---------------------------------------------------------

    var lower = function (s) { return (s || '').toString().toLowerCase(); };

    var normalizeArgs = function (input) {
        var p = input || {};
        if (p.arguments && typeof p.arguments === 'object') { p = p.arguments; }
        return p;
    };

    // Fill {placeholders} from an object; {n} yields the passed 1-based index.
    // Only own properties are read, so a {constructor}/{__proto__}/{toString}
    // placeholder resolves to '' instead of leaking a prototype-chain value.
    var fill = function (tpl, obj, n) {
        return (tpl || '').replace(/\{(\w+)\}/g, function (_, key) {
            if (key === 'n' && n !== undefined) { return String(n); }
            var v = (obj && Object.prototype.hasOwnProperty.call(obj, key)) ? obj[key] : undefined;
            return (v === undefined || v === null) ? '' : String(v);
        });
    };

    // All whitespace-separated terms must appear in the haystack.
    var matchesAll = function (haystack, query) {
        var terms = lower(query).trim().split(/\s+/);
        return terms.every(function (t) { return haystack.indexOf(t) !== -1; });
    };

    // Pick/rename fields from a source item: array = pick keys 1:1,
    // object = {outputKey: sourceKey} rename, null = pass through unchanged.
    var project = function (item, fields) {
        if (!fields) { return item; }
        var out = {};
        if (Array.isArray(fields)) {
            fields.forEach(function (f) { out[f] = item[f]; });
        } else {
            Object.keys(fields).forEach(function (k) { out[k] = item[fields[k]]; });
        }
        return out;
    };

    // First-party usage analytics: each call sends a small same-origin beacon.
    // sendBeacon is used on purpose — it survives the immediate navigation that
    // navigate/mailto tools trigger, where a normal request would be cancelled.
    // "Who" is best-effort: the agent's optional self-reported `client`, else a
    // coarse User-Agent hint, else "unbekannt".
    var clientProp = {
        type: 'string',
        description: 'Optional: name of the calling AI agent (e.g. Claude, ChatGPT) for anonymous usage statistics.'
    };

    var detectClient = function (params) {
        if (params && typeof params.client === 'string' && params.client.trim()) {
            return params.client.trim().slice(0, 40);
        }
        var ua = navigator.userAgent || '';
        var markers = ['ChatGPT', 'GPTBot', 'OAI-SearchBot', 'ClaudeBot', 'Claude-User', 'Claude',
            'Anthropic', 'PerplexityBot', 'Perplexity', 'Gemini', 'Copilot', 'Bytespider'];
        for (var i = 0; i < markers.length; i++) {
            if (ua.indexOf(markers[i]) !== -1) { return markers[i]; }
        }
        return 'unbekannt';
    };

    var track = function (tool, client) {
        var payload = JSON.stringify({ tool: tool, client: client || 'unbekannt' });
        try {
            if (navigator.sendBeacon) {
                navigator.sendBeacon(endpoint, new Blob([payload], { type: 'application/json' }));
            } else {
                fetch(endpoint, {
                    method: 'POST', body: payload, keepalive: true,
                    headers: { 'Content-Type': 'application/json' }
                });
            }
        } catch (e) {}
    };

    // Same-origin JSON indices, fetched once per URL and cached for the page.
    var indexCache = {};
    var loadIndex = function (url) {
        if (!url) { return Promise.resolve([]); }
        if (!indexCache[url]) {
            indexCache[url] = fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .catch(function () { return []; });
        }
        return indexCache[url];
    };

    var textResult = function (text) { return { content: [{ type: 'text', text: text }] }; };

    // A recoverable tool failure: same shape as textResult but flagged isError so
    // the agent can tell "the call failed" from "the call succeeded with this text".
    // Reserved for genuine failures (unknown option, unavailable contact) — an empty
    // but valid search result is a success, not an error.
    var errorResult = function (text) { return { content: [{ type: 'text', text: text }], isError: true }; };

    // Cap the text an agent receives from one call (Chrome's "Secure tools"
    // guidance recommends 1,500 characters). 0 disables the cap. The truncation
    // marker counts towards the limit, so the visible text never exceeds it.
    // structuredContent is left untouched: cutting JSON would corrupt it.
    var outputLimit = typeof config.outputLimit === 'number' ? config.outputLimit : 1500;
    var limitOutput = function (result) {
        if (!(outputLimit > 0) || !result || !Array.isArray(result.content)) { return result; }
        var note = '\n[Output truncated to ' + outputLimit + ' characters.]';
        var budget = outputLimit;
        result.content.forEach(function (part) {
            if (!part || part.type !== 'text' || typeof part.text !== 'string') { return; }
            if (part.text.length <= budget) { budget -= part.text.length; return; }
            part.text = budget > 0 ? part.text.slice(0, Math.max(0, budget - note.length)) + note : '';
            budget = 0;
        });
        return result;
    };

    // Human-in-the-loop confirmation before a side effect (navigate, mailto).
    // Opt-in: only asks when the tool configured a `confirm` message. Uses
    // requestUserInteraction() on the execute callback's second argument where
    // an implementation offers it (older drafts and Chrome's guidance; the
    // 2026-10-02 draft passes only { signal }), else a plain confirm().
    // Returns a Promise<boolean>: true means proceed.
    var confirmSideEffect = function (mcClient, message) {
        if (!message) { return Promise.resolve(true); }
        var ask = function () { return window.confirm(message); };
        if (mcClient && typeof mcClient.requestUserInteraction === 'function') {
            return Promise.resolve(mcClient.requestUserInteraction(ask)).then(function (r) { return r !== false; });
        }
        return Promise.resolve(ask());
    };

    // ---- primitive interpreters -----------------------------------------
    // Each returns an execute() closure for the given tool manifest.

    var primitives = {

        // Navigate the browser to a URL chosen from a fixed option set.
        // data: { param, options:[{match,label,url}], confirm,
        //         messages:{success,unknown,cancelled} }
        navigate: function (tool) {
            var d = tool.data || {};
            var param = d.param || 'value';
            var options = d.options || [];
            var byMatch = {};
            options.forEach(function (o) { byMatch[o.match] = o; });
            var msgs = d.messages || {};
            return function (input, mcClient) {
                var p = normalizeArgs(input);
                track(tool.name, detectClient(p));
                var opt = byMatch[p[param]];
                if (!opt) {
                    var avail = options.map(function (o) { return o.match; }).join(', ');
                    return errorResult(msgs.unknown ? fill(msgs.unknown, { options: avail })
                        : 'Unknown option. Available: ' + avail + '.');
                }
                return confirmSideEffect(mcClient, d.confirm ? fill(d.confirm, opt) : '').then(function (ok) {
                    if (!ok) { return errorResult(msgs.cancelled ? fill(msgs.cancelled, opt) : 'Cancelled.'); }
                    window.location.href = opt.url;
                    return textResult(msgs.success ? fill(msgs.success, opt) : 'Navigating to "' + opt.label + '".');
                });
            };
        },

        // Fetch a same-origin JSON index, filter by query, return the hits.
        // data: { indexUrl, queryParam, limitParam, limitDefault, queryRequired,
        //         searchFields:[…], resultKey, resultFields, deepLinkTemplate,
        //         text:{heading,headingAll,emptyQuery,emptyAll,line} }
        search: function (tool) {
            var d = tool.data || {};
            var qp = d.queryParam || 'query';
            var lp = d.limitParam || 'limit';
            var limitDefault = d.limitDefault || 10;
            var fields = d.searchFields || [];
            var t = d.text || {};
            return function (input) {
                var p = normalizeArgs(input);
                track(tool.name, detectClient(p));
                var query = (p[qp] || '').toString().trim();
                var limit = p[lp] > 0 ? p[lp] : limitDefault;
                return loadIndex(d.indexUrl).then(function (items) {
                    var list = items;
                    if (query) {
                        list = items.filter(function (it) {
                            var hay = fields.map(function (f) { return lower(it[f]); }).join(' ');
                            return matchesAll(hay, query);
                        });
                    } else if (d.queryRequired) {
                        list = [];
                    }
                    var hits = list.slice(0, limit);

                    var text;
                    if (!hits.length) {
                        var empty = query ? t.emptyQuery : t.emptyAll;
                        text = empty ? fill(empty, { query: query })
                            : (query ? 'No results for "' + query + '".' : 'No entries.');
                    } else {
                        var head = query ? t.heading : (t.headingAll || t.heading);
                        var headStr = head ? fill(head, { count: hits.length, query: query })
                            : (hits.length + (query ? ' result(s):' : ' entries:'));
                        var lineTpl = t.line || '{title} – {url}';
                        text = headStr + '\n' + hits.map(function (it, n) { return fill(lineTpl, it, n + 1); }).join('\n');
                    }

                    var structured = { count: hits.length, total: items.length };
                    structured[qp] = query;
                    structured[d.resultKey || 'results'] = hits.map(function (it) { return project(it, d.resultFields); });
                    if (d.deepLinkTemplate && query) {
                        structured.searchUrl = fill(d.deepLinkTemplate, { query: encodeURIComponent(query) });
                    }
                    return { content: [{ type: 'text', text: text }], structuredContent: structured };
                });
            };
        },

        // Build a pre-filled mailto: link and open it. No server storage.
        // data: { to(base64), subjectTemplate, bodyLines:[{label,param,optional}],
        //         messageParam, successTemplate, confirm }
        mailto: function (tool) {
            var d = tool.data || {};
            var to = '';
            try { to = window.atob(d.to || ''); } catch (e) { to = ''; }
            return function (input, mcClient) {
                var p = normalizeArgs(input);
                track(tool.name, detectClient(p));
                if (!to) { return errorResult('Contact is currently unavailable.'); }
                var lines = [];
                (d.bodyLines || []).forEach(function (bl) {
                    var val = p[bl.param];
                    if (bl.optional && !val) { return; }
                    lines.push(bl.label + ': ' + (val || ''));
                });
                if (d.messageParam) { lines.push('', (p[d.messageParam] || '')); }
                var subject = fill(d.subjectTemplate || 'Anfrage', p);
                var body = lines.join('\n');
                var mailto = 'mailto:' + to
                    + '?subject=' + encodeURIComponent(subject)
                    + '&body=' + encodeURIComponent(body);
                return confirmSideEffect(mcClient, d.confirm ? fill(d.confirm, { to: to, subject: subject }) : '').then(function (ok) {
                    if (!ok) { return errorResult('Cancelled.'); }
                    window.location.href = mailto;
                    return {
                        content: [{ type: 'text', text: fill(d.successTemplate || 'A pre-filled e-mail to {to} has been opened.', { to: to }) }],
                        structuredContent: { to: to, subject: subject, body: body, mailto: mailto }
                    };
                });
            };
        },

        // Return a curated, static list verbatim.
        // data: { items:[…], resultKey, text:{heading,line} }
        static: function (tool) {
            var d = tool.data || {};
            var items = d.items || [];
            var t = d.text || {};
            return function (input) {
                var p = normalizeArgs(input);
                track(tool.name, detectClient(p));
                var lineTpl = t.line || '{title} – {url}';
                var body = items.map(function (it, n) { return fill(lineTpl, it, n + 1); }).join('\n');
                var text = (t.heading ? t.heading + '\n' : '') + body;
                var structured = {};
                structured[d.resultKey || 'items'] = items;
                return { content: [{ type: 'text', text: text }], structuredContent: structured };
            };
        }
    };

    // Escape hatch: a tool with no matching primitive but a moduleUrl loads its
    // own ES module (exporting execute(args, ctx)) on first call.
    var moduleExecute = function (tool) {
        return function (input, mcClient) {
            var p = normalizeArgs(input);
            track(tool.name, detectClient(p));
            return import(tool.moduleUrl).then(function (mod) {
                return mod.execute(p, { tool: tool, config: config, client: mcClient });
            });
        };
    };

    // ---- registration ----------------------------------------------------

    // Report a tool that cannot be registered once, by name, without ever
    // throwing: one broken tool must not affect the page or the other tools.
    var warned = {};
    var warnOnce = function (name, reason) {
        if (warned[name]) { return; }
        warned[name] = true;
        if (typeof console !== 'undefined' && console.warn) {
            console.warn('[webmcp] Tool "' + name + '" was not registered:', reason);
        }
    };

    // Turn one manifest entry into a ModelContext tool descriptor, or null if it
    // names no known primitive and no module.
    var toDescriptor = function (tool) {
        var factory = primitives[tool.primitive];
        var execute;
        if (factory) { execute = factory(tool); }
        else if (tool.moduleUrl) { execute = moduleExecute(tool); }
        else { return null; }

        // Every tool implicitly accepts the analytics `client` hint.
        var schema = (tool.inputSchema && typeof tool.inputSchema === 'object')
            ? tool.inputSchema : { type: 'object' };
        schema.properties = schema.properties || {};
        if (!schema.properties.client) { schema.properties.client = clientProp; }

        var descriptor = {
            name: tool.name,
            description: tool.description || '',
            inputSchema: schema,
            // Synchronous throws become rejections; text output is capped.
            execute: function (input, mcClient) {
                return new Promise(function (resolve) { resolve(execute(input, mcClient)); }).then(limitOutput);
            }
        };
        // Optional human-readable label, distinct from the machine-stable name.
        if (tool.title) { descriptor.title = tool.title; }
        // Pass the annotations (readOnlyHint, untrustedContentHint,
        // consequentialHint, debugging) through verbatim; the agent uses them to
        // decide whether a call needs user confirmation.
        if (tool.annotations && typeof tool.annotations === 'object') {
            descriptor.annotations = tool.annotations;
        }
        return descriptor;
    };

    // Build the descriptor set, dropping entries with no name, entries that fail
    // to build, and any later tool reusing a name already taken in the manifest.
    var seen = {};
    var descriptors = [];
    config.tools.forEach(function (tool) {
        if (!tool || typeof tool.name !== 'string' || !tool.name || seen[tool.name]) { return; }
        var descriptor;
        try { descriptor = toDescriptor(tool); } catch (e) { warnOnce(tool.name, e); return; }
        if (!descriptor) { return; }
        seen[tool.name] = true;
        descriptors.push(descriptor);
    });
    if (!descriptors.length) { return; }

    // Each tool is registered on its own with its own AbortController, so tools
    // appear one after another (there is no atomic "register all" in the spec).
    // registerTool() throws for a name already taken on the page — e.g. by a
    // third-party script — or an invalid descriptor; that only skips this tool.
    // Its return value is tolerated in every shape (undefined, a Promise, an
    // object) because the spec has not settled it yet.
    var registrations = new Map();
    var registerAll = function () {
        descriptors.forEach(function (descriptor) {
            var name = descriptor.name;
            if (registrations.has(name)) { return; }
            var controller = new AbortController();
            registrations.set(name, controller);
            var failed = function (reason) {
                if (registrations.get(name) === controller) { registrations.delete(name); }
                warnOnce(name, reason);
            };
            try {
                Promise.resolve(mc.registerTool(descriptor, { signal: controller.signal })).catch(failed);
            } catch (e) {
                failed(e);
            }
        });
    };

    // Unregister everything when the page is hidden, and register again if it
    // comes back from the back/forward cache.
    var unregisterAll = function () {
        registrations.forEach(function (controller) { controller.abort(); });
        registrations.clear();
    };
    window.addEventListener('pagehide', unregisterAll);
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) { registerAll(); }
    });

    registerAll();
})();
