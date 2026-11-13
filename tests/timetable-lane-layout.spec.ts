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
 * cancelled-first deterministic comparator.
 */

abortUnlessTestingTarget('tests/timetable-lane-layout.spec.ts');

/* Half-hour grid indices: 08:00 = 0, 09:00 = 2, 10:00 = 4, 11:00 = 6 … */
const ev = (over: Record<string, unknown>) => Object.assign({ di: 0, type: 'L', venue: 'B001', name: '', code: 'TST' }, over);

type Res = {
  days: Record<string, { placed: { event: { eventKey?: string; id?: number }; lane: number; span: number }[]; lanes: number }>;
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
});
