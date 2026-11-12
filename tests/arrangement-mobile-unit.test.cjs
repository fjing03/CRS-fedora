#!/usr/bin/env node
/*
 * T20 — dependency-free Node unit coverage for the mobile arrangement adapter.
 *
 * Runs the REAL `public/js/arrangement-mobile.js` inside a `node:vm` sandbox
 * (the file ends with `})(window)`, so a sandbox `window` is all it needs) and
 * the REAL T15 retention-marker helpers sliced out of the page's
 * `@section('page-scripts')`. No browser, no Playwright, no DOM library and no
 * new dependency; the timeline cases use a hand-rolled element stub.
 *
 * What this file does NOT prove: real browser layout/geometry, Livewire
 * round-trips, actual clocks, real save/load/discard-all lifecycles or
 * breakpoint behaviour. Those stay with T21–T23/T26–T27.
 *
 * Run:  node tests/arrangement-mobile-unit.test.cjs
 */

'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const ADAPTER_PATH = path.join(__dirname, '..', 'public', 'js', 'arrangement-mobile.js');
const PAGE_PATH = path.join(
    __dirname,
    '..',
    'resources',
    'views',
    'livewire',
    'replacement-arrangement.blade.php'
);

const adapterSource = fs.readFileSync(ADAPTER_PATH, 'utf8');
const pageSource = fs.readFileSync(PAGE_PATH, 'utf8');

/* ── Harness ───────────────────────────────────────────────────────────── */

const tests = [];

function test(name, fn) {
    tests.push({ name: name, fn: fn });
}

function flush() {
    /* The adapter schedules one microtask; setImmediate runs after it. */
    return new Promise(function (resolve) {
        setImmediate(resolve);
    });
}

function wait(ms) {
    return new Promise(function (resolve) {
        setTimeout(resolve, ms);
    });
}

/* Bring a vm-realm value into this realm so strict deep equality is sane. */
function plain(value) {
    if (value === null || value === undefined) return value;
    return JSON.parse(JSON.stringify(value));
}

/* ── Adapter loader ────────────────────────────────────────────────────── */

function loadAdapter(overrides) {
    const sandbox = Object.assign(
        {
            queueMicrotask: queueMicrotask,
            setTimeout: setTimeout,
            clearTimeout: clearTimeout,
        },
        overrides || {}
    );
    sandbox.window = sandbox;
    vm.createContext(sandbox);
    vm.runInContext(adapterSource, sandbox, { filename: 'arrangement-mobile.js' });
    return sandbox.ArrangementMobile;
}

/* ── Minimal DOM stub (only what the settled render touches) ───────────── */

function makeStyle() {
    const props = Object.create(null);
    return {
        setProperty: function (name, value) {
            props[name] = String(value);
        },
        removeProperty: function (name) {
            delete props[name];
        },
        getPropertyValue: function (name) {
            return props[name] || '';
        },
    };
}

function makeElement(tagName) {
    const attrs = Object.create(null);
    const children = [];
    const classes = new Set();
    const node = {
        tagName: String(tagName || 'div').toUpperCase(),
        attributes: attrs,
        children: children,
        className: '',
        textContent: '',
        hidden: false,
        inert: false,
        disabled: false,
        open: false,
        style: makeStyle(),
        classList: {
            add: function (name) {
                classes.add(name);
            },
            remove: function (name) {
                classes.delete(name);
            },
            contains: function (name) {
                return classes.has(name);
            },
        },
        setAttribute: function (name, value) {
            attrs[name] = String(value);
        },
        getAttribute: function (name) {
            return Object.prototype.hasOwnProperty.call(attrs, name) ? attrs[name] : null;
        },
        removeAttribute: function (name) {
            delete attrs[name];
        },
        appendChild: function (child) {
            child.parentNode = node;
            children.push(child);
            return child;
        },
        removeChild: function (child) {
            const at = children.indexOf(child);
            if (at >= 0) children.splice(at, 1);
            child.parentNode = null;
            return child;
        },
        addEventListener: function () {},
        removeEventListener: function () {},
        close: function () {
            node.open = false;
        },
        getBoundingClientRect: function () {
            return { height: 0 };
        },
        closest: function () {
            return null;
        },
    };
    Object.defineProperty(node, 'firstChild', {
        get: function () {
            return children.length ? children[0] : null;
        },
    });
    return node;
}

function makeDoc() {
    const body = {
        classList: { add: function () {}, remove: function () {} },
        style: makeStyle(),
    };
    return {
        body: body,
        createElement: function (tagName) {
            return makeElement(tagName);
        },
        createComment: function (text) {
            return { nodeType: 8, textContent: String(text || ''), parentNode: null };
        },
        getElementById: function () {
            return null;
        },
        querySelector: function () {
            return null;
        },
        querySelectorAll: function () {
            return [];
        },
    };
}

/*
 * `hosts` maps querySelector strings to stub nodes. The root carries the
 * listener bookkeeping the adapter's idempotency claims are checked against.
 */
function makeRoot(hosts) {
    const elements = hosts || {};
    const listeners = [];
    const doc = makeDoc();
    const root = {
        hidden: true,
        inert: true,
        ownerDocument: doc,
        querySelector: function (selector) {
            return elements[selector] || null;
        },
        addEventListener: function (type, fn) {
            listeners.push({ type: type, fn: fn });
        },
        removeEventListener: function (type, fn) {
            for (let i = 0; i < listeners.length; i++) {
                if (listeners[i].type === type && listeners[i].fn === fn) {
                    listeners.splice(i, 1);
                    return;
                }
            }
        },
        getAttribute: function () {
            return null;
        },
        setAttribute: function () {},
        removeAttribute: function () {},
    };
    return { root: root, doc: doc, listeners: listeners, elements: elements };
}

/* ── Snapshot fixtures (plain canonical shape, read-only) ──────────────── */

function baseSnapshot(overrides) {
    const snap = {
        weekIndex: 0,
        venueCode: 'B103',
        weeks: [
            {
                index: 0,
                label: 'Week 1',
                eligible: true,
                days: [
                    { index: 0, abbr: 'Mon', date: '9 Nov 2026', eligible: true },
                    { index: 1, abbr: 'Tue', date: '10 Nov 2026', eligible: false },
                ],
            },
        ],
        hours: ['08:00', '08:30', '09:00', '09:30'],
        cells: [
            { dayIndex: 0, hourIndex: 0, known: true, stateClasses: ['cell-available'] },
            { dayIndex: 0, hourIndex: 1, known: true, stateClasses: ['cell-available'] },
            { dayIndex: 0, hourIndex: 2, known: true, stateClasses: ['cell-available'] },
            { dayIndex: 0, hourIndex: 3, known: true, stateClasses: ['cell-available'] },
        ],
        selections: { entries: [], totalSlots: 0 },
    };
    return Object.assign(snap, overrides || {});
}

function mountRendered(snapshotValue, hosts) {
    const adapter = loadAdapter();
    const dom = makeRoot(hosts);
    const controller = adapter.create({
        root: dom.root,
        ports: { readSnapshot: function () { return snapshotValue; } },
    });
    return { controller: controller, dom: dom };
}

function timelineRows(timeline) {
    return timeline.children.filter(function (child) {
        return child.getAttribute('data-mobile-row') === 'hour';
    });
}

/* ═══════════════════════════════════════════════════════════════════════
 * Module surface
 * ═══════════════════════════════════════════════════════════════════════ */

test('adapter exposes exactly the controller factory and the frozen utils map', function () {
    const api = loadAdapter();
    assert.deepEqual(Object.keys(api).sort(), ['create', 'utils']);
    assert.deepEqual(Object.keys(api.utils).sort(), ['chooseEligibleDay', 'savedEntries', 'savedSpan']);
    assert.equal(typeof api.create, 'function');
    assert.equal(Object.isFrozen(api.utils), true);
});

/* ═══════════════════════════════════════════════════════════════════════
 * chooseEligibleDay — original indexes, retention, fallback, fail closed
 * ═══════════════════════════════════════════════════════════════════════ */

test('chooseEligibleDay keeps the previous original index on non-contiguous days', function () {
    const api = loadAdapter();
    const days = [
        { index: 2, eligible: true },
        { index: 5, eligible: true },
        { index: 7, eligible: true },
    ];
    assert.equal(api.utils.chooseEligibleDay(days, 5), 5);
    assert.equal(api.utils.chooseEligibleDay(days, 7), 7);
    assert.equal(api.utils.chooseEligibleDay(days, 2), 2);
});

test('chooseEligibleDay returns the original index, never the filtered position', function () {
    const api = loadAdapter();
    const days = [{ index: 3, eligible: false }, { index: 4, eligible: true }, { index: 6, eligible: true }];
    /* Position 1 holds original index 4. */
    assert.equal(api.utils.chooseEligibleDay(days, null), 4);
    assert.equal(api.utils.chooseEligibleDay(days, 6), 6);
});

test('chooseEligibleDay accepts a single same-index eligible day', function () {
    const api = loadAdapter();
    const days = [{ index: 4, eligible: true }];
    assert.equal(api.utils.chooseEligibleDay(days, 4), 4);
    assert.equal(api.utils.chooseEligibleDay(days, null), 4);
});

test('chooseEligibleDay falls back to the first eligible day when retention fails', function () {
    const api = loadAdapter();
    const days = [
        { index: 1, eligible: false },
        { index: 2, eligible: false },
        { index: 5, eligible: true },
        { index: 8, eligible: true },
    ];
    assert.equal(api.utils.chooseEligibleDay(days, 2), 5);
    assert.equal(api.utils.chooseEligibleDay(days, 99), 5);
});

test('chooseEligibleDay falls back for null, undefined, negative and non-integer pointers', function () {
    const api = loadAdapter();
    const days = [{ index: 0, eligible: false }, { index: 3, eligible: true }];
    assert.equal(api.utils.chooseEligibleDay(days, null), 3);
    assert.equal(api.utils.chooseEligibleDay(days, undefined), 3);
    assert.equal(api.utils.chooseEligibleDay(days, -1), 3);
    assert.equal(api.utils.chooseEligibleDay(days, 1.5), 3);
    assert.equal(api.utils.chooseEligibleDay(days, NaN), 3);
});

test('chooseEligibleDay keeps a numeric-string pointer as its integer index', function () {
    const api = loadAdapter();
    const days = [{ index: 6, eligible: true }];
    assert.equal(api.utils.chooseEligibleDay(days, '6'), 6);
});

test('chooseEligibleDay fails closed without an eligible day', function () {
    const api = loadAdapter();
    assert.equal(api.utils.chooseEligibleDay([{ index: 0, eligible: false }], 0), null);
    assert.equal(api.utils.chooseEligibleDay([{ index: 0 }], null), null);
    assert.equal(api.utils.chooseEligibleDay([{}], 0), null);
});

test('chooseEligibleDay fails closed for empty, null and non-array day lists', function () {
    const api = loadAdapter();
    assert.equal(api.utils.chooseEligibleDay([], 0), null);
    assert.equal(api.utils.chooseEligibleDay([], null), null);
    assert.equal(api.utils.chooseEligibleDay(null, 0), null);
    assert.equal(api.utils.chooseEligibleDay(undefined, 0), null);
    assert.equal(api.utils.chooseEligibleDay({ 0: { index: 0, eligible: true } }, 0), null);
});

test('chooseEligibleDay only accepts an explicit eligible === true', function () {
    const api = loadAdapter();
    assert.equal(api.utils.chooseEligibleDay([{ index: 0, eligible: 1 }], null), null);
    assert.equal(api.utils.chooseEligibleDay([{ index: 0, eligible: 'true' }], null), null);
    assert.equal(api.utils.chooseEligibleDay([{ index: 0, eligible: true }], null), 0);
});

test('chooseEligibleDay trusts helper-supplied calendar results instead of recomputing them', function () {
    const api = loadAdapter();
    /* The page's existing calendar helper decides Sunday/PH/too-soon. The
       adapter must consume those flags verbatim — it has no clock of its own. */
    const days = [
        { index: 0, abbr: 'Mon', date: '9 Nov 2026', eligible: true },
        { index: 1, abbr: 'Tue', date: '10 Nov 2026', eligible: true },
        { index: 2, abbr: 'Wed', date: '11 Nov 2026', eligible: false },
        { index: 3, abbr: 'Thu', date: '12 Nov 2026', eligible: false },
        { index: 4, abbr: 'Fri', date: '13 Nov 2026', eligible: true },
    ];
    /* A saved/prefilled Wednesday (helper said no): fall to the first yes. */
    assert.equal(api.utils.chooseEligibleDay(days, 2), 0);
    /* A still-eligible Tuesday is retained as its original index. */
    assert.equal(api.utils.chooseEligibleDay(days, 1), 1);
    /* An omitted flag is not an implicit yes. */
    assert.equal(api.utils.chooseEligibleDay([{ index: 0, abbr: 'Mon' }], 0), null);
});

test('chooseEligibleDay ignores an ineligible duplicate and keeps the eligible original index', function () {
    const api = loadAdapter();
    const days = [
        { index: 3, eligible: false },
        { index: 3, eligible: true },
    ];
    assert.equal(api.utils.chooseEligibleDay(days, 3), 3);
    assert.equal(api.utils.chooseEligibleDay(days, null), 3);
});

test('chooseEligibleDay uses the position when a day carries no valid original index', function () {
    const api = loadAdapter();
    assert.equal(api.utils.chooseEligibleDay([{ eligible: true }], null), 0);
    assert.equal(api.utils.chooseEligibleDay([{ index: -2, eligible: true }], null), 0);
    assert.equal(api.utils.chooseEligibleDay([{ index: 1.5, eligible: true }], null), 0);
});

/* ═══════════════════════════════════════════════════════════════════════
 * savedSpan — validation, exclusive end, slot count
 * ═══════════════════════════════════════════════════════════════════════ */

test('savedSpan reports the exclusive end and the covered half-hour count', function () {
    const api = loadAdapter();
    assert.deepEqual(plain(api.utils.savedSpan({ day: 1, startHour: 2, endHour: 4 })), {
        dayIndex: 1,
        startHour: 2,
        endHour: 4,
        slotCount: 2,
    });
    assert.deepEqual(plain(api.utils.savedSpan({ day: 0, startHour: 5, endHour: 6 })), {
        dayIndex: 0,
        startHour: 5,
        endHour: 6,
        slotCount: 1,
    });
});

test('savedSpan coerces numeric strings but rejects everything else', function () {
    const api = loadAdapter();
    assert.deepEqual(plain(api.utils.savedSpan({ day: '1', startHour: '2', endHour: '4' })), {
        dayIndex: 1,
        startHour: 2,
        endHour: 4,
        slotCount: 2,
    });
    assert.equal(api.utils.savedSpan(null), null);
    assert.equal(api.utils.savedSpan(undefined), null);
    assert.equal(api.utils.savedSpan('1::2::4'), null);
    assert.equal(api.utils.savedSpan({}), null);
});

test('savedSpan rejects invalid day, start and end indexes', function () {
    const api = loadAdapter();
    assert.equal(api.utils.savedSpan({ day: -1, startHour: 0, endHour: 1 }), null);
    assert.equal(api.utils.savedSpan({ day: 0.5, startHour: 0, endHour: 1 }), null);
    assert.equal(api.utils.savedSpan({ day: 'Mon', startHour: 0, endHour: 1 }), null);
    assert.equal(api.utils.savedSpan({ day: 0, startHour: -1, endHour: 1 }), null);
    assert.equal(api.utils.savedSpan({ day: 0, startHour: 1.5, endHour: 3 }), null);
    assert.equal(api.utils.savedSpan({ day: 0, startHour: 2, endHour: 2 }), null);
    assert.equal(api.utils.savedSpan({ day: 0, startHour: 3, endHour: 2 }), null);
    assert.equal(api.utils.savedSpan({ day: 0, startHour: 2, endHour: 2.5 }), null);
    assert.equal(api.utils.savedSpan({ day: 0, startHour: 2 }), null);
});

test('savedSpan never mutates the stored entry', function () {
    const api = loadAdapter();
    const entry = Object.freeze({ day: 1, startHour: 2, endHour: 4 });
    assert.doesNotThrow(function () {
        api.utils.savedSpan(entry);
    });
    assert.deepEqual(plain(entry), { day: 1, startHour: 2, endHour: 4 });
});

/* ═══════════════════════════════════════════════════════════════════════
 * savedEntries — keyed identities, sorting, legacy entries, no mutation
 * ═══════════════════════════════════════════════════════════════════════ */

const WEEK_DATA = [
    {
        label: 'Week 1',
        days: [
            { abbr: 'Mon', date: '9 Nov 2026' },
            { abbr: 'Tue', date: '10 Nov 2026' },
            { abbr: 'Wed', date: '11 Nov 2026' },
        ],
    },
    {
        label: 'Week 2',
        days: [{ abbr: 'Mon', date: '16 Nov 2026' }],
    },
];

test('savedEntries fails closed for missing or malformed stores', function () {
    const api = loadAdapter();
    assert.deepEqual(plain(api.utils.savedEntries(null, WEEK_DATA)), []);
    assert.deepEqual(plain(api.utils.savedEntries(undefined, WEEK_DATA)), []);
    assert.deepEqual(plain(api.utils.savedEntries('store', WEEK_DATA)), []);
    assert.deepEqual(plain(api.utils.savedEntries({}, WEEK_DATA)), []);
    /* No week data at all: unrenderable identities are not invented. */
    assert.deepEqual(plain(api.utils.savedEntries({ B103: { 0: { day: 0, startHour: 1, endHour: 2 } } }, null)), []);
});

test('savedEntries returns keyed venue/week/day identities with canonical day text', function () {
    const api = loadAdapter();
    const entries = plain(
        api.utils.savedEntries({ B103: { 0: { day: 1, startHour: 2, endHour: 4 } } }, WEEK_DATA)
    );
    assert.equal(entries.length, 1);
    assert.deepEqual(entries[0], {
        key: 'B103::0',
        venueKey: 'B103',
        weekIndex: 0,
        weekLabel: 'Week 1',
        dayIndex: 1,
        startHour: 2,
        endHour: 4,
        slotCount: 2,
        dayKnown: true,
        dayAbbr: 'Tue',
        date: '10 Nov 2026',
    });
});

test('savedEntries sorts by week, then day, then start hour', function () {
    const api = loadAdapter();
    const store = {
        B103: {
            1: { day: 0, startHour: 3, endHour: 4 },
            0: { day: 2, startHour: 5, endHour: 6 },
        },
        B204: {
            0: { day: 0, startHour: 6, endHour: 7 },
        },
    };
    const entries = plain(api.utils.savedEntries(store, WEEK_DATA));
    assert.deepEqual(
        entries.map(function (entry) {
            return [entry.key, entry.dayIndex, entry.startHour];
        }),
        [
            ['B204::0', 0, 6],
            ['B103::0', 2, 5],
            ['B103::1', 0, 3],
        ]
    );
});

test('savedEntries preserves every legacy entry instead of keeping only one', function () {
    const api = loadAdapter();
    const store = {
        B103: { 0: { day: 0, startHour: 2, endHour: 3 } },
        B204: { 0: { day: 1, startHour: 4, endHour: 6 } },
        B305: { 1: { day: 0, startHour: 1, endHour: 2 } },
    };
    const entries = plain(api.utils.savedEntries(store, WEEK_DATA));
    assert.equal(entries.length, 3);
    assert.deepEqual(
        entries.map(function (entry) {
            return entry.key;
        }),
        ['B103::0', 'B204::0', 'B305::1']
    );
});

test('savedEntries keeps a missing week with dayKnown false and a fallback label', function () {
    const api = loadAdapter();
    const entries = plain(
        api.utils.savedEntries({ B103: { 3: { day: 0, startHour: 1, endHour: 2 } } }, WEEK_DATA)
    );
    assert.equal(entries.length, 1);
    assert.equal(entries[0].weekIndex, 3);
    assert.equal(entries[0].weekLabel, 'Week 4');
    assert.equal(entries[0].dayKnown, false);
    assert.equal(entries[0].dayAbbr, '');
    assert.equal(entries[0].date, '');
    assert.equal(entries[0].slotCount, 1);
});

test('savedEntries keeps a missing day of a real week with dayKnown false', function () {
    const api = loadAdapter();
    const entries = plain(
        api.utils.savedEntries({ B103: { 0: { day: 9, startHour: 1, endHour: 2 } } }, WEEK_DATA)
    );
    assert.equal(entries.length, 1);
    assert.equal(entries[0].weekLabel, 'Week 1');
    assert.equal(entries[0].dayIndex, 9);
    assert.equal(entries[0].dayKnown, false);
    assert.equal(entries[0].date, '');
});

test('savedEntries skips unparseable week keys and invalid spans', function () {
    const api = loadAdapter();
    const store = {
        B103: {
            abc: { day: 0, startHour: 1, endHour: 2 },
            '-1': { day: 0, startHour: 1, endHour: 2 },
            '0.5': { day: 0, startHour: 1, endHour: 2 },
            0: { day: 0, startHour: 4, endHour: 4 },
            1: { day: 0, startHour: 1, endHour: 2 },
        },
        B204: { 0: null },
    };
    const entries = plain(api.utils.savedEntries(store, WEEK_DATA));
    assert.equal(entries.length, 1);
    assert.equal(entries[0].key, 'B103::1');
});

test('savedEntries falls back to a generated week label when the week has none', function () {
    const api = loadAdapter();
    const entries = plain(
        api.utils.savedEntries({ B103: { 1: { day: 0, startHour: 1, endHour: 2 } } }, [{ days: [] }, { days: [] }])
    );
    assert.equal(entries.length, 1);
    assert.equal(entries[0].weekLabel, 'Week 2');
    assert.equal(entries[0].dayKnown, false);
});

test('savedEntries reads the store without mutating it', function () {
    const api = loadAdapter();
    const store = {
        B103: { 0: Object.freeze({ day: 1, startHour: 2, endHour: 4 }) },
        B204: { 1: Object.freeze({ day: 0, startHour: 6, endHour: 7 }) },
    };
    const before = JSON.stringify(store);
    let entries = null;
    assert.doesNotThrow(function () {
        entries = api.utils.savedEntries(store, WEEK_DATA);
    });
    assert.equal(entries.length, 2);
    assert.equal(JSON.stringify(store), before);
});

/* ═══════════════════════════════════════════════════════════════════════
 * create() — safe no-port / no-root / early-refresh / destroy behaviour
 * ═══════════════════════════════════════════════════════════════════════ */

test('create({}) without a root is inert: mount, refresh, snapshot and destroy are safe', async function () {
    const api = loadAdapter();
    let reads = 0;
    const controller = api.create({
        ports: {
            readSnapshot: function () {
                reads += 1;
                return baseSnapshot();
            },
        },
    });
    assert.doesNotThrow(function () {
        controller.mount();
        controller.refresh('before-root');
    });
    await flush();
    /* No root: mount/refresh never schedule a settled turn. */
    assert.equal(reads, 0);
    /* snapshot() is the documented read-only probe: one fresh port read. */
    assert.equal(plain(controller.snapshot()).venueCode, 'B103');
    assert.equal(reads, 1);
    assert.equal(controller.isMobile(), false);
    assert.equal(controller.isInfoOpen(), false);
    assert.doesNotThrow(function () {
        controller.destroy();
        controller.destroy();
        controller.mount();
        controller.refresh('after-destroy');
    });
    await flush();
    /* Destroyed + rootless: no further port read and snapshot() is terminal. */
    assert.equal(reads, 1);
    assert.equal(controller.snapshot(), null);
});

test('create with no snapshot port fails closed to null', async function () {
    const api = loadAdapter();
    const dom = makeRoot();
    const controller = api.create({ root: dom.root });
    controller.mount();
    await flush();
    assert.equal(controller.snapshot(), null);
    assert.equal(controller.isMobile(), false);
    controller.destroy();
});

test('a throwing snapshot port is contained and returns null', async function () {
    const api = loadAdapter();
    const dom = makeRoot();
    const controller = api.create({
        root: dom.root,
        ports: {
            readSnapshot: function () {
                throw new Error('snapshot boom');
            },
        },
    });
    assert.doesNotThrow(function () {
        controller.mount();
    });
    await flush();
    assert.equal(controller.snapshot(), null);
    assert.doesNotThrow(function () {
        controller.refresh('again');
    });
    await flush();
    assert.equal(controller.snapshot(), null);
    controller.destroy();
});

test('a non-object snapshot port result is discarded', async function () {
    const api = loadAdapter();
    const dom = makeRoot();
    const controller = api.create({
        root: dom.root,
        ports: { readSnapshot: function () { return 'nope'; } },
    });
    controller.mount();
    await flush();
    assert.equal(controller.snapshot(), null);
    controller.destroy();
});

test('a throwing onSnapshot callback cannot break the settled refresh', async function () {
    const api = loadAdapter();
    const dom = makeRoot();
    let reads = 0;
    const controller = api.create({
        root: dom.root,
        ports: {
            readSnapshot: function () {
                reads += 1;
                return baseSnapshot();
            },
            onSnapshot: function () {
                throw new Error('callback boom');
            },
        },
    });
    assert.doesNotThrow(function () {
        controller.mount();
    });
    await flush();
    assert.equal(reads, 1);
    assert.equal(plain(controller.snapshot()).venueCode, 'B103');
    controller.destroy();
});

test('refresh before mount is a no-op; mount reads exactly once', async function () {
    const api = loadAdapter();
    const dom = makeRoot();
    let reads = 0;
    const controller = api.create({
        root: dom.root,
        ports: {
            readSnapshot: function () {
                reads += 1;
                return baseSnapshot();
            },
        },
    });
    controller.refresh('early');
    await flush();
    assert.equal(reads, 0);
    controller.mount();
    await flush();
    assert.equal(reads, 1);
    controller.destroy();
});

test('repeated refresh calls coalesce into one settled read', async function () {
    const api = loadAdapter();
    const dom = makeRoot();
    let reads = 0;
    const controller = api.create({
        root: dom.root,
        ports: {
            readSnapshot: function () {
                reads += 1;
                return baseSnapshot();
            },
        },
    });
    controller.mount();
    await flush();
    assert.equal(reads, 1);
    controller.refresh('a');
    controller.refresh('b');
    controller.refresh('c');
    await flush();
    assert.equal(reads, 2);
    /* Rendering never re-enters the scheduler. */
    await flush();
    assert.equal(reads, 2);
    controller.destroy();
});

test('snapshot() reads fresh before mount and reuses the cached read afterwards', async function () {
    const api = loadAdapter();
    const dom = makeRoot();
    let reads = 0;
    const controller = api.create({
        root: dom.root,
        ports: {
            readSnapshot: function () {
                reads += 1;
                return baseSnapshot();
            },
        },
    });
    assert.equal(plain(controller.snapshot()).venueCode, 'B103');
    assert.equal(reads, 1);
    controller.mount();
    await flush();
    assert.equal(reads, 2);
    assert.equal(plain(controller.snapshot()).venueCode, 'B103');
    assert.equal(reads, 2);
    controller.destroy();
});

test('repeated mount/destroy bind and release listeners exactly once', async function () {
    const api = loadAdapter();
    const dom = makeRoot();
    let reads = 0;
    const controller = api.create({
        root: dom.root,
        ports: {
            readSnapshot: function () {
                reads += 1;
                return baseSnapshot();
            },
        },
    });
    controller.mount();
    controller.mount();
    await flush();
    /* Two delegated click listeners (day + timeline), no duplicates. */
    assert.deepEqual(
        dom.listeners.map(function (listener) {
            return listener.type;
        }),
        ['click', 'click']
    );
    assert.equal(reads, 1);
    controller.destroy();
    assert.equal(dom.listeners.length, 0);
    controller.destroy();
    controller.mount();
    await flush();
    assert.equal(reads, 1);
    assert.equal(dom.listeners.length, 0);
    assert.equal(controller.snapshot(), null);
});

test('destroy cancels a pending fallback refresh before it can read', async function () {
    const api = loadAdapter({ queueMicrotask: undefined });
    const dom = makeRoot();
    let reads = 0;
    const controller = api.create({
        root: dom.root,
        ports: {
            readSnapshot: function () {
                reads += 1;
                return baseSnapshot();
            },
        },
    });
    controller.mount();
    controller.destroy();
    await wait(15);
    assert.equal(reads, 0);
});

test('refresh after destroy stays inert and never re-renders', async function () {
    const api = loadAdapter();
    const timeline = makeElement('div');
    const dom = makeRoot({ '[data-arrangement-mobile-timeline]': timeline });
    let reads = 0;
    const controller = api.create({
        root: dom.root,
        ports: {
            readSnapshot: function () {
                reads += 1;
                return baseSnapshot();
            },
        },
    });
    controller.mount();
    await flush();
    assert.equal(reads, 1);
    assert.equal(timeline.children.length, 4);
    controller.destroy();
    const renderedAfterDestroy = timeline.children.length;
    controller.refresh('late');
    await flush();
    assert.equal(reads, 1);
    assert.equal(timeline.children.length, renderedAfterDestroy);
});

/* ═══════════════════════════════════════════════════════════════════════
 * Settled render — canonical timeline fail-closed + saved-span snapshots
 * (production render path through the stub DOM; presentation, not browser)
 * ═══════════════════════════════════════════════════════════════════════ */

test('the timeline fails closed for a missing canonical cell', async function () {
    const timeline = makeElement('div');
    const snapshot = baseSnapshot({
        cells: [
            { dayIndex: 0, hourIndex: 0, known: true, stateClasses: ['cell-available'] },
            { dayIndex: 0, hourIndex: 1, known: true, stateClasses: ['cell-available'] },
            { dayIndex: 0, hourIndex: 2, known: true, stateClasses: ['cell-available'] },
            /* hour 3 deliberately absent */
        ],
    });
    const mounted = mountRendered(snapshot, { '[data-arrangement-mobile-timeline]': timeline });
    mounted.controller.mount();
    await flush();
    const rows = timelineRows(timeline);
    assert.equal(rows.length, 4);
    const missing = rows[3];
    assert.equal(missing.disabled, true);
    assert.equal(missing.getAttribute('data-mobile-state'), 'unknown');
    assert.equal(missing.getAttribute('aria-disabled'), 'true');
    assert.match(missing.className, /arrangement-mobile-row--unknown/);
    mounted.controller.destroy();
});

test('the timeline rejects an unknown cell and unknown state classes', async function () {
    const timeline = makeElement('div');
    const snapshot = baseSnapshot({
        cells: [
            { dayIndex: 0, hourIndex: 0, known: false, stateClasses: ['cell-available'] },
            { dayIndex: 0, hourIndex: 1, known: true, stateClasses: ['not-a-canonical-class'] },
            { dayIndex: 0, hourIndex: 2, known: true, stateClasses: [] },
            { dayIndex: 0, hourIndex: 3, known: true, stateClasses: ['cell-available'] },
        ],
    });
    const mounted = mountRendered(snapshot, { '[data-arrangement-mobile-timeline]': timeline });
    mounted.controller.mount();
    await flush();
    const rows = timelineRows(timeline);
    assert.equal(rows.length, 4);
    [0, 1, 2].forEach(function (index) {
        assert.equal(rows[index].disabled, true, 'row ' + index + ' must be disabled');
        assert.equal(rows[index].getAttribute('data-mobile-state'), 'unknown');
        assert.equal(rows[index].className, 'arrangement-mobile-row arrangement-mobile-row--unknown');
    });
    assert.equal(rows[3].disabled, false);
    assert.equal(rows[3].getAttribute('data-mobile-state'), 'available');
    assert.equal(rows[3].className, 'arrangement-mobile-row cell-available');
    mounted.controller.destroy();
});

test('only whitelisted canonical classes reach a known row', async function () {
    const timeline = makeElement('div');
    const snapshot = baseSnapshot({
        cells: [
            {
                dayIndex: 0,
                hourIndex: 0,
                known: true,
                stateClasses: ['cell-pending', 'cell-pending', 'evil-owner-ring', 'cell-sun'],
            },
        ],
    });
    const mounted = mountRendered(snapshot, { '[data-arrangement-mobile-timeline]': timeline });
    mounted.controller.mount();
    await flush();
    const row = timelineRows(timeline)[0];
    assert.equal(row.className, 'arrangement-mobile-row cell-pending cell-sun');
    assert.equal(row.getAttribute('data-mobile-state'), 'sunday');
    /* No ownership-ring class and no duplicate class name. */
    assert.equal(/owner-ring/.test(row.className), false);
    mounted.controller.destroy();
});

test('a selected saved span renders one whole block with no duplicated half-hours', async function () {
    const timeline = makeElement('div');
    const snapshot = baseSnapshot({
        cells: [
            { dayIndex: 0, hourIndex: 0, known: true, stateClasses: ['cell-selected'] },
            { dayIndex: 0, hourIndex: 1, known: true, stateClasses: ['cell-selected'] },
            { dayIndex: 0, hourIndex: 2, known: true, stateClasses: ['cell-available'] },
            { dayIndex: 0, hourIndex: 3, known: true, stateClasses: ['cell-available'] },
        ],
        selections: {
            entries: [{ venueKey: 'B103', weekIndex: 0, dayIndex: 0, startHour: 0, endHour: 2 }],
            totalSlots: 2,
        },
    });
    const mounted = mountRendered(snapshot, { '[data-arrangement-mobile-timeline]': timeline });
    mounted.controller.mount();
    await flush();

    const spans = timeline.children.filter(function (child) {
        return child.getAttribute('data-mobile-row') === 'span';
    });
    assert.equal(spans.length, 1);
    assert.equal(spans[0].getAttribute('data-mobile-span'), '2');
    assert.equal(spans[0].className, 'arrangement-mobile-span');
    assert.equal(spans[0].style.gridRow || spans[0].getAttribute('style'), 'span 2');
    /* 4 axis hours - 2 covered + the one block = 3 rendered children. */
    assert.equal(timeline.children.length, 3);
    const hours = timelineRows(timeline).map(function (row) {
        return row.getAttribute('data-mobile-hour');
    });
    assert.deepEqual(hours, ['2', '3']);
    mounted.controller.destroy();
});

test('the saved span is not painted over a blocked canonical cell', async function () {
    const timeline = makeElement('div');
    const snapshot = baseSnapshot({
        cells: [
            { dayIndex: 0, hourIndex: 0, known: true, stateClasses: ['cell-selected'] },
            { dayIndex: 0, hourIndex: 1, known: true, stateClasses: ['cell-occupied'] },
            { dayIndex: 0, hourIndex: 2, known: true, stateClasses: ['cell-available'] },
            { dayIndex: 0, hourIndex: 3, known: true, stateClasses: ['cell-available'] },
        ],
        selections: {
            entries: [{ venueKey: 'B103', weekIndex: 0, dayIndex: 0, startHour: 0, endHour: 2 }],
            totalSlots: 2,
        },
    });
    const mounted = mountRendered(snapshot, { '[data-arrangement-mobile-timeline]': timeline });
    mounted.controller.mount();
    await flush();
    assert.equal(
        timeline.children.filter(function (child) {
            return child.getAttribute('data-mobile-row') === 'span';
        }).length,
        0
    );
    assert.equal(timeline.children.length, 4);
    mounted.controller.destroy();
});

test('a saved span reaching past the axis is not drawn at all', async function () {
    const timeline = makeElement('div');
    const snapshot = baseSnapshot({
        selections: {
            entries: [{ venueKey: 'B103', weekIndex: 0, dayIndex: 0, startHour: 0, endHour: 6 }],
            totalSlots: 6,
        },
    });
    const mounted = mountRendered(snapshot, { '[data-arrangement-mobile-timeline]': timeline });
    mounted.controller.mount();
    await flush();
    assert.equal(
        timeline.children.filter(function (child) {
            return child.getAttribute('data-mobile-row') === 'span';
        }).length,
        0
    );
    assert.equal(timeline.children.length, 4);
    mounted.controller.destroy();
});

/* ═══════════════════════════════════════════════════════════════════════
 * T15 retention identity cleanup — page-logic coverage via a narrow
 * extraction of the REAL page helpers. This is not browser/flow evidence:
 * saveCurrentWeek/load/discard-all integration stays with T22b.
 * ═══════════════════════════════════════════════════════════════════════ */

function loadRetentionHelpers() {
    const start = pageSource.indexOf('        let retainedUnrenderableKeys = {};');
    const endMarker = [
        '        function clearAllRetained() {',
        '            retainedUnrenderableKeys = {};',
        '        }',
    ].join('\n');
    assert.notEqual(start, -1, 'T15 retention block start anchor missing from the page');
    const end = pageSource.indexOf(endMarker, start);
    assert.notEqual(end, -1, 'T15 retention block end anchor missing from the page');
    const slice = pageSource.slice(start, end + endMarker.length);
    const sandbox = {};
    vm.createContext(sandbox);
    return vm.runInContext(
        slice +
            '\n;({' +
            ' keys: function () { return retainedUnrenderableKeys; },' +
            ' sameBlock: sameBlock, key: key, markRetained: markRetained,' +
            ' isRetained: isRetained, clearRetained: clearRetained,' +
            ' clearAllRetained: clearAllRetained });',
        sandbox,
        { filename: 'replacement-arrangement.blade.php::T15-retention' }
    );
}

test('retention marker keys are venue::week identities and refuse invalid weeks', function () {
    const helpers = loadRetentionHelpers();
    assert.equal(helpers.key('B103', 0), 'B103::0');
    assert.equal(helpers.key('B103', '2'), 'B103::2');
    assert.equal(helpers.key('B103', -1), '');
    assert.equal(helpers.key('B103', 1.5), '');
    assert.equal(helpers.key('B103', 'abc'), '');
    assert.equal(helpers.key('B103', undefined), '');
    /* Recorded coercion quirk: Number(null) is 0, so null reads as week 0.
       No production call site passes null, and T20 must not change the
       page helper to make the test look tidier. */
    assert.equal(helpers.key('B103', null), 'B103::0');
});

test('retention markers store the exact numeric block identity only', function () {
    const helpers = loadRetentionHelpers();
    assert.equal(helpers.markRetained('B103', 0, { day: 1, startHour: 2, endHour: 4 }), true);
    assert.deepEqual(plain(helpers.keys()['B103::0']), { day: 1, startHour: 2, endHour: 4 });
    assert.equal(helpers.isRetained('B103', 0, { day: 1, startHour: 2, endHour: 4 }), true);
    assert.equal(helpers.isRetained('B103', 0, { day: 1, startHour: 2, endHour: 5 }), false);
    assert.equal(helpers.isRetained('B103', 0), true);
    assert.equal(helpers.isRetained('B103', 1), false);
});

test('retention markers reject partial blocks and invalid keys', function () {
    const helpers = loadRetentionHelpers();
    assert.equal(helpers.markRetained('B103', 0, { day: 1, startHour: 2 }), false);
    assert.equal(helpers.markRetained('B103', 0, null), false);
    assert.equal(helpers.markRetained('B103', -1, { day: 1, startHour: 2, endHour: 3 }), false);
    assert.deepEqual(plain(helpers.keys()), {});
});

test('an explicit keyed cleanup removes only that exact venue/week marker', function () {
    const helpers = loadRetentionHelpers();
    helpers.markRetained('B103', 0, { day: 1, startHour: 2, endHour: 4 });
    helpers.markRetained('B103', 1, { day: 0, startHour: 1, endHour: 2 });
    helpers.markRetained('B204', 0, { day: 2, startHour: 3, endHour: 4 });

    helpers.clearRetained('B103', 0);
    assert.equal(helpers.isRetained('B103', 0), false);
    assert.equal(helpers.isRetained('B103', 1), true);
    assert.equal(helpers.isRetained('B204', 0), true);

    /* Removing an absent key is a no-op, never a wipe. */
    helpers.clearRetained('B999', 3);
    helpers.clearRetained('B103', -1);
    assert.deepEqual(Object.keys(plain(helpers.keys())).sort(), ['B103::1', 'B204::0']);

    helpers.clearAllRetained();
    assert.deepEqual(plain(helpers.keys()), {});
    assert.equal(helpers.isRetained('B103', 1), false);
});

test('sameBlock compares only complete numeric day/start/end identities', function () {
    const helpers = loadRetentionHelpers();
    assert.equal(helpers.sameBlock({ day: 1, startHour: 2, endHour: '4' }, { day: 1, startHour: 2, endHour: 4 }), true);
    assert.equal(helpers.sameBlock({ day: 1, startHour: 2, endHour: 4 }, { day: 1, startHour: 2 }), false);
    assert.equal(helpers.sameBlock(null, null), false);
    assert.equal(helpers.sameBlock({ day: null, startHour: 2, endHour: 4 }, { day: null, startHour: 2, endHour: 4 }), false);
});

/* ── Run ───────────────────────────────────────────────────────────────── */

(async function run() {
    let failed = 0;
    for (const entry of tests) {
        try {
            await entry.fn();
        } catch (err) {
            failed += 1;
            console.error('FAIL ' + entry.name);
            console.error('     ' + String((err && err.message) || err).split('\n').join('\n     '));
        }
    }
    if (failed > 0) {
        console.error('arrangement-mobile-unit: ' + (tests.length - failed) + ' passing, ' + failed + ' failing');
        process.exitCode = 1;
        return;
    }
    console.log('arrangement-mobile-unit: ' + tests.length + ' passing, 0 failing');
})();
