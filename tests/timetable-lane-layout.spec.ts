import { test, expect } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * timetable-lane-layout.spec.ts — A14 (cancelled-slot-reuse S17).
 *
 * The PURE `layoutTimetableDay()` planner in public/js/ui-common.js, driven
 * through the SERVED file (page.evaluate on the login page — the one route
 * that loads ui-common.js without an authenticated session) with synthetic
 * arrays. No DB access, no fixture writes: the guard below is the standing
 * browser-target lock only (CRS_MODE=testing + CRS_BASE=127.0.0.1:8030), so
 * the spec can never quietly point at the demo serve.
 *
 * Covered: exact / partial / multiple / adjacent / non-overlap lane reuse,
 * shuffle invariance, input non-mutation, per-day lane scoping, and the
 * cancelled-first deterministic comparator — plus the half-hour true-
 * alignment case (pending 13:00–14:00 / cancellation 13:30–14:30) and an
 * exhaustive 1764-pair oracle against an independent occupied-cell model.
 */

abortUnlessTestingTarget('tests/timetable-lane-layout.spec.ts');

/* Half-hour grid indices: 08:00 = 0, 09:00 = 2, 10:00 = 4, 11:00 = 6 … */
const ev = (over: Record<string, unknown>) => Object.assign({ di: 0, type: 'L', venue: 'B001', name: '', code: 'TST' }, over);

type Res = {
  days: Record<string, { placed: { event: { eventKey?: string; id?: number; start?: number; end?: number }; lane: number; span: number }[]; lanes: number }>;
  lanes: number;
};

/** lane assignments keyed by eventKey (or id) — the assertion shorthand. */
function lanesOf(res: Res, di = '0'): Record<string, number> {
  const out: Record<string, number> = {};
  (res.days[di]?.placed ?? []).forEach((p) => {
    out[String(p.event.eventKey ?? p.event.id)] = p.lane;
  });
  return out;
}

test.describe('layoutTimetableDay (A14 / S17)', () => {
  let res: (events: unknown[]) => Promise<Res>;

  test.beforeEach(async ({ page }) => {
    await page.goto('/login/staff', { waitUntil: 'networkidle' });
    await expect(page.locator('#password')).toBeVisible();
    res = (events: unknown[]) => page.evaluate(
      (input) => (window as unknown as { layoutTimetableDay: (e: unknown[]) => Res }).layoutTimetableDay(input),
      events,
    );
  });

  test('exact overlap: cancelled stacks above the pending landing (D6 example)', async ({ page }) => {
    const r = await res([
      ev({ id: 1, eventKey: 'occ:S:1:1', status: 'cancelled', start: 2, end: 5 }),
      ev({ id: 2, eventKey: 'request:77', status: 'pending', start: 2, end: 5 }),
    ]);
    expect(r.days['0'].lanes).toBe(2);
    const lanes = lanesOf(r);
    expect(lanes['occ:S:1:1']).toBe(0);
    expect(lanes['request:77']).toBe(1);
    r.days['0'].placed.forEach((p) => expect(p.span).toBe(4));
  });

  test('partial overlap keeps TRUE ranges on two lanes (cancelled 09–11 / pending 10–12)', async ({ page }) => {
    const r = await res([
      ev({ id: 1, eventKey: 'occ:S:1:1', status: 'cancelled', start: 2, end: 5 }),
      ev({ id: 2, eventKey: 'request:78', status: 'pending', start: 4, end: 7 }),
    ]);
    expect(r.days['0'].lanes).toBe(2);
    const lanes = lanesOf(r);
    expect(lanes['occ:S:1:1']).toBe(0);
    expect(lanes['request:78']).toBe(1);
    const spans = Object.fromEntries(r.days['0'].placed.map((p) => [String(p.event.eventKey), p.span]));
    expect(spans).toEqual({ 'occ:S:1:1': 4, 'request:78': 4 });
  });

  test('two cancellations + one approved overlap → THREE lanes (D6 example)', async ({ page }) => {
    const r = await res([
      ev({ id: 1, eventKey: 'occ:S:1:1', status: 'cancelled', start: 2, end: 5 }),
      ev({ id: 2, eventKey: 'occ:S:1:1b', status: 'cancelled', start: 2, end: 5 }),
      ev({ id: 3, eventKey: 'request:79', status: 'replacement', start: 3, end: 5 }),
    ]);
    expect(r.days['0'].lanes).toBe(3);
    const lanes = lanesOf(r);
    expect(lanes['occ:S:1:1']).toBe(0);
    expect(lanes['occ:S:1:1b']).toBe(1);
    expect(lanes['request:79']).toBe(2);
  });

  test('adjacent half-open intervals SHARE a lane (09–10 + 10–11)', async ({ page }) => {
    const r = await res([
      ev({ id: 1, eventKey: 'occ:S:1:1', status: 'cancelled', start: 2, end: 3 }),
      ev({ id: 2, eventKey: 'occ:S:2:1', status: 'normal', start: 4, end: 5 }),
    ]);
    expect(r.days['0'].lanes).toBe(1);
    expect(lanesOf(r)['occ:S:1:1']).toBe(0);
    expect(lanesOf(r)['occ:S:2:1']).toBe(0);
  });

  test('independent components reuse lanes from zero; day lane count = largest component', async ({ page }) => {
    const r = await res([
      ev({ id: 1, eventKey: 'occ:S:1:1', status: 'cancelled', start: 2, end: 5 }),
      ev({ id: 2, eventKey: 'request:77', status: 'pending', start: 4, end: 7 }),
      /* distant, non-overlapping block — its own component */
      ev({ id: 3, eventKey: 'request:80', status: 'normal', start: 12, end: 15 }),
    ]);
    expect(r.days['0'].lanes).toBe(2);
    expect(lanesOf(r)['request:80']).toBe(0);
  });

  test('same-start distinct identities NEVER overwrite each other', async ({ page }) => {
    const r = await res([
      ev({ id: 1, eventKey: 'occ:S:1:1', status: 'cancelled', start: 4, end: 7 }),
      ev({ id: 2, eventKey: 'request:77', status: 'pending', start: 4, end: 7 }),
      ev({ id: 3, eventKey: 'request:81', status: 'replacement', start: 4, end: 7 }),
    ]);
    const lanes = lanesOf(r);
    expect(Object.keys(lanes).sort()).toEqual(['occ:S:1:1', 'request:77', 'request:81']);
    expect(lanes['occ:S:1:1']).toBe(0);
    expect(lanes['request:77']).toBe(1);
    expect(lanes['request:81']).toBe(2);
  });

  test('multi-day input partitions per day; every day restarts at lane 0', async ({ page }) => {
    const r = await res([
      ev({ id: 1, eventKey: 'occ:S:1:1', status: 'cancelled', di: 0, start: 2, end: 5 }),
      ev({ id: 2, eventKey: 'request:77', status: 'pending', di: 0, start: 4, end: 7 }),
      ev({ id: 3, eventKey: 'occ:S:2:1', status: 'cancelled', di: 1, start: 2, end: 5 }),
      ev({ id: 4, eventKey: 'request:78', status: 'normal', di: 1, start: 4, end: 7 }),
    ]);
    expect(r.days['0'].lanes).toBe(2);
    expect(r.days['1'].lanes).toBe(2);
    expect(lanesOf(r, '0')['occ:S:1:1']).toBe(0);
    expect(lanesOf(r, '1')['occ:S:2:1']).toBe(0);
    expect(r.lanes).toBe(2);
  });

  test('shuffle invariance: reordering the input never changes the output', async ({ page }) => {
    const base = [
      ev({ id: 1, eventKey: 'occ:S:1:1', status: 'cancelled', start: 2, end: 5 }),
      ev({ id: 2, eventKey: 'request:77', status: 'pending', start: 4, end: 7 }),
      ev({ id: 3, eventKey: 'request:78', status: 'replacement', start: 3, end: 5 }),
      ev({ id: 4, eventKey: 'occ:S:3:1', status: 'cancelled', start: 8, end: 9 }),
      ev({ id: 5, eventKey: 'request:80', status: 'normal', start: 10, end: 13 }),
    ];
    const a = await res(base);
    const b = await res([...base].reverse());
    const c = await res([base[2], base[4], base[0], base[3], base[1]]);
    const norm = (r: Res) => r.days['0'].placed.map((p) => ({
      key: String(p.event.eventKey), lane: p.lane, span: p.span,
    }));
    expect(norm(b)).toEqual(norm(a));
    expect(norm(c)).toEqual(norm(a));
  });

  test('the input array and its event objects are NEVER mutated', async ({ page }) => {
    const snapshot = await page.evaluate(() => {
      const input = [
        { di: 0, start: 2, end: 5, status: 'cancelled', eventKey: 'occ:S:1:1', code: 'CXL', type: 'L', venue: 'B001', lecturer: 'X', name: 'A' },
        { di: 0, start: 4, end: 7, status: 'pending', eventKey: 'request:77', code: 'CXL', type: 'L', venue: 'B001', lecturer: 'X', name: 'A' },
      ];
      const before = JSON.stringify(input);
      /* eslint-disable-next-line @typescript-eslint/no-explicit-any */
      (window as unknown as { layoutTimetableDay: (e: unknown[]) => Res }).layoutTimetableDay(input);
      return { before, after: JSON.stringify(input) };
    });
    expect(snapshot.after).toBe(snapshot.before);
  });

  test('empty input → one lane; key-less legacy records stay separate and deterministic', async ({ page }) => {
    expect((await res([])).lanes).toBe(1);

    const r = await res([
      ev({ id: 1, status: 'cancelled', start: 2, end: 5 }),            // no eventKey (mock/legacy)
      ev({ id: 2, status: 'pending', start: 4, end: 7 }),
    ]);
    const r2 = await res([
      ev({ id: 2, status: 'pending', start: 4, end: 7 }),
      ev({ id: 1, status: 'cancelled', start: 2, end: 5 }),
    ]);
    const idsOf = (x: Res) => x.days['0'].placed.map((p) => [p.event.id, p.lane, p.span]);
    expect(idsOf(r)).toEqual(idsOf(r2));
    expect(r.days['0'].lanes).toBe(2);
    /* the cancelled legacy record still owns lane 0 */
    expect(idsOf(r)[0]).toEqual([1, 0, 4]);
  });

  test('cancelled-only days keep one lane; the comparator is stable across runs', async ({ page }) => {
    const events = [
      ev({ id: 1, eventKey: 'occ:S:9:1', status: 'cancelled', start: 2, end: 5 }),
      ev({ id: 2, eventKey: 'occ:S:9:1', status: 'cancelled', start: 2, end: 5, code: 'Twin' }),
    ];
    const a = await res(events);
    const b = await res(events);
    expect(a.days['0'].placed).toEqual(b.days['0'].placed);
    expect(a.days['0'].lanes).toBe(2);
  });

  test('half-hour staff5770 case: pending 13:00–14:00 (10–11) vs cancellation 13:30–14:30 (11–12)', async () => {
    /* 08:00 = cell 0 → 13:00 = 10, 13:30 = 11, 14:30 = 12 (inclusive ends).
       The pending covers cells 10–11, the cancellation 11–12: they share
       exactly the 13:30–14:00 cell, so two lanes with the cancellation
       ABOVE, and every original coordinate must survive verbatim. */
    const r = await res([
      ev({ id: 1, eventKey: 'request:5770', status: 'pending', start: 10, end: 11 }),
      ev({ id: 2, eventKey: 'occ:S:5770:1', status: 'cancelled', start: 11, end: 12 }),
    ]);
    expect(r.days['0'].lanes).toBe(2);
    const lanes = lanesOf(r);
    expect(lanes['occ:S:5770:1']).toBe(0);
    expect(lanes['request:5770']).toBe(1);
    /* TRUE coordinates: no half-open normalisation, no start shifting */
    const placed = Object.fromEntries(
      r.days['0'].placed.map((p) => [String(p.event.eventKey), { start: p.event.start, end: p.event.end, span: p.span }]),
    );
    expect(placed['occ:S:5770:1']).toEqual({ start: 11, end: 12, span: 2 });
    expect(placed['request:5770']).toEqual({ start: 10, end: 11, span: 2 });
  });

  test('half-hour exhaustive oracle: 1764 ordered range×status pairs vs independent cell occupancy', async ({ page }) => {
    const out = await page.evaluate(() => {
      const layout = (window as unknown as { layoutTimetableDay: (e: unknown[]) => Res }).layoutTimetableDay;

      /* 21 inclusive half-hour ranges: start 0..5, end start..5 */
      const ranges: Array<[number, number]> = [];
      for (let s = 0; s <= 5; s++) for (let e = s; e <= 5; e++) ranges.push([s, e]);

      /* INDEPENDENT occupancy oracle: explicit occupied-cell arrays per
         range — intersection is decided by shared CELLS, never by the
         production half-open arithmetic, so a wrong `[start,end)` in
         layoutTimetableDay cannot agree with its own oracle. Covers exact
         30-minute (single-cell), one-cell partial, containment and
         adjacency (adjacent cell sets are disjoint → no intersection). */
      const cellSets = ranges.map((r: [number, number]) => {
        const c: number[] = [];
        for (let i = r[0]; i <= r[1]; i++) c.push(i);
        return c;
      });
      const overlaps = (i: number, j: number) => cellSets[i].some((c) => cellSets[j].indexOf(c) >= 0);

      const mk = (id: number, eventKey: string, status: string, r: [number, number]) =>
        ({ id, eventKey, di: 0, status, start: r[0], end: r[1], type: 'L', venue: 'B001', code: 'TST', name: '' });

      const STATUS_PAIRS: string[][] = [
        ['cancelled', 'cancelled'], ['cancelled', 'pending'],
        ['pending', 'cancelled'], ['pending', 'replacement'],
      ];

      const failures: string[] = [];
      let checked = 0;

      for (const [st1, st2] of STATUS_PAIRS) {
        for (let i = 0; i < ranges.length; i++) {
          for (let j = 0; j < ranges.length; j++) {
            const r1 = ranges[i];
            const r2 = ranges[j];
            /* Expected assignment derived ONLY from the frozen documented
               comparator (cancelled first, then start/end/eventKey) plus
               first-fit over the independent CELL sets: the first event
               always takes lane 0; the second takes lane 1 exactly when the
               cell sets intersect. On an overlap the cancellation (which
               the comparator sorts first) is therefore ABOVE the active. */
            const a = mk(1, 'occ:pair', st1, r1);
            const b = mk(2, 'request:pair', st2, r2);
            const first = [a, b].sort((x, y) =>
              (x.status === 'cancelled' ? 0 : 1) - (y.status === 'cancelled' ? 0 : 1)
              || x.start - y.start || x.end - y.end || (x.eventKey < y.eventKey ? -1 : 1))[0];
            const second = first === a ? b : a;
            const overlap = overlaps(i, j);
            const expectLane: Record<string, number> = { [first.eventKey]: 0, [second.eventKey]: overlap ? 1 : 0 };
            const expectLanes = overlap ? 2 : 1;

            const norms: Record<string, Record<string, string>> = {};
            for (const order of ['ab', 'ba']) {
              const input = order === 'ab' ? [a, b] : [b, a];
              const before = JSON.stringify(input);
              const r = layout(input) as Res;
              const after = JSON.stringify(input);
              const ctx = `statuses=[${st1},${st2}] r1=[${r1}] r2=[${r2}] order=${order}`;
              const fail = (msg: string) => { if (failures.length < 8) failures.push(`${ctx}: ${msg}`); };

              if (after !== before) fail('input payload mutated');
              const day = r.days['0'];
              if (!day) { fail('day 0 missing from result'); continue; }
              if (day.lanes !== expectLanes) fail(`day lanes=${day.lanes} expected ${expectLanes}`);
              if (day.placed.length !== 2) { fail(`placed.length=${day.placed.length} expected 2`); continue; }
              const norm: Record<string, string> = {};
              for (const p of day.placed) {
                const key = String(p.event.eventKey);
                norm[key] = `${p.lane}/${p.span}`;
                if (p.lane !== expectLane[key]) fail(`${key} lane=${p.lane} expected ${expectLane[key]}`);
                if (p.event !== (key === 'occ:pair' ? a : b)) fail(`${key} lost its original event reference`);
                const src = key === 'occ:pair' ? r1 : r2;
                if (p.event.start !== src[0] || p.event.end !== src[1]) {
                  fail(`${key} coordinates drifted to [${p.event.start},${p.event.end}] expected [${src[0]},${src[1]}]`);
                }
                if (p.span !== src[1] - src[0] + 1) fail(`${key} span=${p.span} expected ${src[1] - src[0] + 1}`);
              }
              norms[order] = norm;
            }
            /* reverse input must produce IDENTICAL lane/span assignments */
            if (JSON.stringify(norms.ab) !== JSON.stringify(norms.ba)) {
              if (failures.length < 8) {
                failures.push(`statuses=[${st1},${st2}] r1=[${r1}] r2=[${r2}]: reverse input changed assignments `
                  + `${JSON.stringify(norms.ab)} → ${JSON.stringify(norms.ba)}`);
              }
            }
            checked++;
          }
        }
      }
      return { checked, failures };
    });

    expect(out.checked).toBe(21 * 21 * 4);
    expect(out.failures, `oracle mismatches (first shown):\n${out.failures.join('\n')}`).toEqual([]);
  });
});
