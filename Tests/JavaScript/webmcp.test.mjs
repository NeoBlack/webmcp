// Behaviour tests for Resources/Public/JavaScript/webmcp.js.
//
// The runtime is a self-executing browser script, so each test evaluates it in
// a fresh vm context with a minimal fake DOM (document, navigator, window
// events) and a fake ModelContext that behaves like the spec draft: one
// registerTool() per tool, a duplicate name throws, an AbortSignal unregisters.
//
// Run with: npm test

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const source = readFileSync(new URL('../../Resources/Public/JavaScript/webmcp.js', import.meta.url), 'utf8');

const staticTool = (name, extra = {}) => ({
    name,
    description: 'd',
    inputSchema: { type: 'object' },
    primitive: 'static',
    data: { items: [{ title: 'Item', url: 'https://example.org' }] },
    annotations: { readOnlyHint: true, untrustedContentHint: false, consequentialHint: false },
    ...extra,
});

function fakeModelContext({ result } = {}) {
    const tools = new Map();
    const registrations = [];
    return {
        tools,
        registrations,
        provideContextCalls: 0,
        provideContext() { this.provideContextCalls++; },
        registerTool(descriptor, options) {
            registrations.push({ descriptor, options });
            if (tools.has(descriptor.name)) {
                throw new Error(`Duplicate tool name "${descriptor.name}"`);
            }
            tools.set(descriptor.name, descriptor);
            options?.signal?.addEventListener('abort', () => tools.delete(descriptor.name));
            return typeof result === 'function' ? result(descriptor) : result;
        },
    };
}

/**
 * Evaluate webmcp.js once against a fake page.
 *
 * @param {object}  opts
 * @param {*}       opts.config            manifest object, or a raw string for the config block
 * @param {object}  [opts.documentMc]      document.modelContext
 * @param {object}  [opts.navigatorMc]     navigator.modelContext
 * @param {boolean} [opts.inFrame]         simulate being embedded in an iframe
 */
function runPage({ config, documentMc, navigatorMc, inFrame = false }) {
    const listeners = {};
    const warnings = [];
    const window = {
        addEventListener: (type, fn) => { (listeners[type] ||= []).push(fn); },
        location: { href: '' },
        confirm: () => true,
        atob: (s) => Buffer.from(s, 'base64').toString('binary'),
    };
    window.self = window;
    window.window = window;
    window.top = inFrame ? {} : window;

    const text = config === undefined ? null : (typeof config === 'string' ? config : JSON.stringify(config));
    const document = {
        modelContext: documentMc,
        getElementById: (id) => (id === 'webmcp-config' && text !== null ? { textContent: text } : null),
    };
    const navigator = { modelContext: navigatorMc, userAgent: 'test', sendBeacon: () => true };
    const console = { warn: (...args) => warnings.push(args) };

    const context = vm.createContext({ ...window, window, document, navigator, console, AbortController, Blob, fetch: () => Promise.resolve({ ok: false }) });
    vm.runInContext(source, context);

    return {
        warnings,
        dispatch: (type, event = {}) => (listeners[type] || []).forEach((fn) => fn(event)),
    };
}

const flush = () => new Promise((resolve) => setImmediate(resolve));

// ---- feature-detection matrix ---------------------------------------------

test('registers each tool via document.modelContext when only it is present', () => {
    const mc = fakeModelContext();
    runPage({ config: { tools: [staticTool('a'), staticTool('b')] }, documentMc: mc });

    assert.deepEqual([...mc.tools.keys()], ['a', 'b']);
    assert.equal(mc.registrations.length, 2, 'one registerTool() call per tool');
    for (const { options } of mc.registrations) {
        assert.ok(options.signal, 'every registration carries an AbortSignal');
    }
});

test('falls back to navigator.modelContext when only it is present (default on)', () => {
    const nav = fakeModelContext();
    runPage({ config: { tools: [staticTool('a')] }, navigatorMc: nav });

    assert.deepEqual([...nav.tools.keys()], ['a']);
});

test('honours an explicit legacyNavigatorFallback: true', () => {
    const nav = fakeModelContext();
    runPage({ config: { legacyNavigatorFallback: true, tools: [staticTool('a')] }, navigatorMc: nav });

    assert.deepEqual([...nav.tools.keys()], ['a']);
});

test('ignores navigator.modelContext when legacyNavigatorFallback is false', () => {
    const nav = fakeModelContext();
    const page = runPage({ config: { legacyNavigatorFallback: false, tools: [staticTool('a')] }, navigatorMc: nav });

    assert.equal(nav.registrations.length, 0);
    assert.equal(page.warnings.length, 0, 'the extension itself logs nothing');
});

test('prefers document.modelContext when both are present', () => {
    const doc = fakeModelContext();
    const nav = fakeModelContext();
    runPage({ config: { tools: [staticTool('a')] }, documentMc: doc, navigatorMc: nav });

    assert.deepEqual([...doc.tools.keys()], ['a']);
    assert.equal(nav.registrations.length, 0);
});

test('falls back when document.modelContext lacks registerTool', () => {
    const nav = fakeModelContext();
    runPage({ config: { tools: [staticTool('a')] }, documentMc: { provideContext() {} }, navigatorMc: nav });

    assert.deepEqual([...nav.tools.keys()], ['a']);
});

test('does nothing without any ModelContext', () => {
    const page = runPage({ config: { tools: [staticTool('a')] } });

    assert.equal(page.warnings.length, 0);
});

test('does nothing inside an iframe', () => {
    const mc = fakeModelContext();
    runPage({ config: { tools: [staticTool('a')] }, documentMc: mc, inFrame: true });

    assert.equal(mc.registrations.length, 0);
});

test('never calls provideContext(), even where it exists', () => {
    const mc = fakeModelContext();
    runPage({ config: { tools: [staticTool('a')] }, documentMc: mc });

    assert.equal(mc.provideContextCalls, 0);
});

// ---- error isolation -------------------------------------------------------

test('a name already taken by another script skips only that tool and warns once', async () => {
    const mc = fakeModelContext();
    mc.tools.set('taken', { name: 'taken' }); // registered earlier by a third party
    const page = runPage({ config: { tools: [staticTool('taken'), staticTool('free')] }, documentMc: mc });
    await flush();

    assert.ok(mc.tools.has('free'));
    assert.equal(page.warnings.length, 1);
    assert.match(String(page.warnings[0][0]), /"taken"/);
});

for (const [label, result] of [
    ['undefined', undefined],
    ['a resolved Promise', () => Promise.resolve()],
    ['an object', (descriptor) => ({ name: descriptor.name })],
]) {
    test(`tolerates registerTool() returning ${label}`, async () => {
        const mc = fakeModelContext({ result });
        const page = runPage({ config: { tools: [staticTool('a'), staticTool('b')] }, documentMc: mc });
        await flush();

        assert.deepEqual([...mc.tools.keys()], ['a', 'b']);
        assert.equal(page.warnings.length, 0);
    });
}

test('a rejected registerTool() Promise warns for that tool only', async () => {
    const mc = fakeModelContext({ result: (d) => (d.name === 'bad' ? Promise.reject(new Error('schema')) : undefined) });
    const page = runPage({ config: { tools: [staticTool('bad'), staticTool('good')] }, documentMc: mc });
    await flush();

    assert.equal(page.warnings.length, 1);
    assert.match(String(page.warnings[0][0]), /"bad"/);
    assert.ok(mc.tools.has('good'));
});

// ---- broken manifests --------------------------------------------------------

for (const [label, config] of [
    ['missing config block', undefined],
    ['invalid JSON', '{ not json'],
    ['tools not an array', { tools: 'nope' }],
    ['empty tool list', { tools: [] }],
]) {
    test(`registers nothing for ${label}`, () => {
        const mc = fakeModelContext();
        const page = runPage({ config, documentMc: mc });

        assert.equal(mc.registrations.length, 0);
        assert.equal(page.warnings.length, 0);
    });
}

test('skips invalid entries but registers the valid ones', async () => {
    const mc = fakeModelContext();
    const page = runPage({
        config: {
            tools: [
                null,
                { description: 'no name', primitive: 'static' },
                staticTool('unknown', { primitive: 'teleport' }),
                staticTool('broken', { primitive: 'navigate', data: { options: 'not-a-list' } }),
                staticTool('ok'),
                staticTool('ok', { description: 'manifest duplicate' }),
            ],
        },
        documentMc: mc,
    });
    await flush();

    assert.deepEqual([...mc.tools.keys()], ['ok']);
    assert.equal(mc.tools.get('ok').description, 'd', 'the first entry with a name wins');
    assert.equal(page.warnings.length, 1, 'only the entry that failed to build is reported');
    assert.match(String(page.warnings[0][0]), /"broken"/);
});

// ---- descriptor --------------------------------------------------------------

test('passes title and annotations through to the descriptor', () => {
    const mc = fakeModelContext();
    const annotations = { readOnlyHint: false, untrustedContentHint: false, consequentialHint: true, debugging: true };
    runPage({ config: { tools: [staticTool('a', { title: 'A', annotations })] }, documentMc: mc });

    const descriptor = mc.tools.get('a');
    assert.equal(descriptor.title, 'A');
    assert.deepEqual({ ...descriptor.annotations }, annotations);
    assert.ok(descriptor.inputSchema.properties.client, 'analytics hint is injected');
});

// ---- lifecycle ---------------------------------------------------------------

test('unregisters on pagehide and registers again when restored from bfcache', async () => {
    const mc = fakeModelContext();
    const page = runPage({ config: { tools: [staticTool('a'), staticTool('b')] }, documentMc: mc });

    page.dispatch('pagehide', { persisted: true });
    assert.equal(mc.tools.size, 0);

    page.dispatch('pageshow', { persisted: false });
    assert.equal(mc.tools.size, 0, 'a fresh load is handled by the initial run');

    page.dispatch('pageshow', { persisted: true });
    await flush();
    assert.deepEqual([...mc.tools.keys()], ['a', 'b']);
    assert.equal(page.warnings.length, 0);
});

test('warns only once per tool across re-registrations', async () => {
    const mc = fakeModelContext({ result: () => Promise.reject(new Error('nope')) });
    const page = runPage({ config: { tools: [staticTool('a')] }, documentMc: mc });
    await flush();
    page.dispatch('pagehide', {});
    page.dispatch('pageshow', { persisted: true });
    await flush();

    assert.equal(mc.registrations.length, 2);
    assert.equal(page.warnings.length, 1);
});

// ---- output limit --------------------------------------------------------------

const longList = (count) => ({ items: Array.from({ length: count }, (_, i) => ({ title: `Item ${i} ${'x'.repeat(40)}`, url: '/' })) });

async function callStatic(config) {
    const mc = fakeModelContext();
    runPage({ config, documentMc: mc });
    return mc.tools.get('list').execute({}, undefined);
}

test('caps text output at 1500 characters by default and marks the cut', async () => {
    const result = await callStatic({ tools: [staticTool('list', { data: longList(100) })] });
    const text = result.content[0].text;

    assert.ok(text.length <= 1500, `got ${text.length}`);
    assert.match(text, /\[Output truncated to 1500 characters\.\]$/);
    assert.equal(result.structuredContent.items.length, 100, 'structuredContent is not cut');
});

test('honours a configured outputLimit', async () => {
    const result = await callStatic({ outputLimit: 200, tools: [staticTool('list', { data: longList(100) })] });

    assert.ok(result.content[0].text.length <= 200);
    assert.match(result.content[0].text, /truncated to 200 characters/);
});

test('outputLimit 0 disables the cap', async () => {
    const result = await callStatic({ outputLimit: 0, tools: [staticTool('list', { data: longList(100) })] });

    assert.ok(result.content[0].text.length > 1500);
});

test('short output is returned unchanged', async () => {
    const result = await callStatic({ tools: [staticTool('list')] });

    assert.equal(result.content[0].text, 'Item – https://example.org');
});

test('a throwing escape-hatch result becomes a rejected call, not a page error', async () => {
    const mc = fakeModelContext();
    runPage({ config: { tools: [staticTool('mod', { primitive: 'custom', moduleUrl: 'data:text/javascript,' })] }, documentMc: mc });

    await assert.rejects(mc.tools.get('mod').execute({}, undefined));
});
