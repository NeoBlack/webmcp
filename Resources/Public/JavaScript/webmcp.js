/* WebMCP – generic client runtime.
 *
 * Reads a declarative tool manifest from <script type="application/json"
 * id="webmcp-config"> (emitted by ToolManifestProcessor) and registers each
 * tool against the page's ModelContext. Tool behaviour is data-driven: every
 * tool names a primitive (navigate | search | mailto | static) whose generic
 * interpreter lives here, so new tools are defined entirely server-side. A tool
 * that needs behaviour no primitive covers may instead point `moduleUrl` at an
 * ES module exporting execute(args, ctx).
 *
 * Progressive enhancement throughout: absent config, absent ModelContext, or a
 * malformed manifest simply means nothing is registered; regular visitors are
 * never affected. */
(function () {
    'use strict';

    var cfgEl = document.getElementById('webmcp-config');
    if (!cfgEl) { return; }
    var config;
    try { config = JSON.parse(cfgEl.textContent || 'null'); } catch (e) { return; }
    if (!config || !Array.isArray(config.tools) || !config.tools.length) { return; }

    // The W3C spec exposes ModelContext on the document (page-scoped); Chrome's
    // origin trial still ships it on navigator. The two have not converged, so
    // feature-detect both, preferring the canonical document surface.
    var mc = document.modelContext || navigator.modelContext;
    if (!mc || typeof mc.registerTool !== 'function') { return; }

    var endpoint = config.endpoint || '/webmcp-event';

    // ---- helpers ---------------------------------------------------------

    var lower = function (s) { return (s || '').toString().toLowerCase(); };

    var normalizeArgs = function (input) {
        var p = input || {};
        if (p.arguments && typeof p.arguments === 'object') { p = p.arguments; }
        return p;
    };

    // Fill {placeholders} from an object; {n} yields the passed 1-based index.
    var fill = function (tpl, obj, n) {
        return (tpl || '').replace(/\{(\w+)\}/g, function (_, key) {
            if (key === 'n' && n !== undefined) { return String(n); }
            var v = obj ? obj[key] : undefined;
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

    // ---- primitive interpreters -----------------------------------------
    // Each returns an execute() closure for the given tool manifest.

    var primitives = {

        // Navigate the browser to a URL chosen from a fixed option set.
        // data: { param, options:[{match,label,url}], messages:{success,unknown} }
        navigate: function (tool) {
            var d = tool.data || {};
            var param = d.param || 'value';
            var options = d.options || [];
            var byMatch = {};
            options.forEach(function (o) { byMatch[o.match] = o; });
            var msgs = d.messages || {};
            return function (input) {
                var p = normalizeArgs(input);
                track(tool.name, detectClient(p));
                var opt = byMatch[p[param]];
                if (!opt) {
                    var avail = options.map(function (o) { return o.match; }).join(', ');
                    return errorResult(msgs.unknown ? fill(msgs.unknown, { options: avail })
                        : 'Unknown option. Available: ' + avail + '.');
                }
                window.location.href = opt.url;
                return textResult(msgs.success ? fill(msgs.success, opt) : 'Navigating to "' + opt.label + '".');
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
        //         messageParam, successTemplate }
        mailto: function (tool) {
            var d = tool.data || {};
            var to = '';
            try { to = window.atob(d.to || ''); } catch (e) { to = ''; }
            return function (input) {
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
                window.location.href = mailto;
                return {
                    content: [{ type: 'text', text: fill(d.successTemplate || 'A pre-filled e-mail to {to} has been opened.', { to: to }) }],
                    structuredContent: { to: to, subject: subject, body: body, mailto: mailto }
                };
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
        return function (input) {
            var p = normalizeArgs(input);
            track(tool.name, detectClient(p));
            return import(tool.moduleUrl).then(function (mod) {
                return mod.execute(p, { tool: tool, config: config });
            });
        };
    };

    // ---- registration ----------------------------------------------------

    config.tools.forEach(function (tool) {
        if (!tool || !tool.name) { return; }
        var factory = primitives[tool.primitive];
        var execute;
        if (factory) { execute = factory(tool); }
        else if (tool.moduleUrl) { execute = moduleExecute(tool); }
        else { return; }

        // Every tool implicitly accepts the analytics `client` hint.
        var schema = (tool.inputSchema && typeof tool.inputSchema === 'object')
            ? tool.inputSchema : { type: 'object' };
        schema.properties = schema.properties || {};
        if (!schema.properties.client) { schema.properties.client = clientProp; }

        var descriptor = {
            name: tool.name,
            description: tool.description || '',
            inputSchema: schema,
            execute: execute
        };
        // Pass the read-only hint through verbatim so the agent can decide whether
        // the tool may run without user confirmation.
        if (tool.annotations && typeof tool.annotations === 'object') {
            descriptor.annotations = tool.annotations;
        }
        mc.registerTool(descriptor);
    });
})();
