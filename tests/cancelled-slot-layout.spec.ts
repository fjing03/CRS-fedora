import { test, expect, Page } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * cancelled-slot-layout.spec.ts — A15/A16/A17/A19 browser receipts
 * (cancelled-slot-reuse S17–S21, minus the PHP-payload cross-checks that
 * belong to A20c once the payload agent's eventKey ships).
 *
 * READ-ONLY for the database: both proofs stage a SYNTHETIC scoped payload
 * onto `window.eventsData` (the same bridge my-timetable already exposes and
 * venue-timetable now mirrors) and re-render through the page's own
 * buildTimetable()/selectWeek(). No SQL fixture, so the guard below carries
 * no CRS_ALLOW_SQL requirement — but it still refuses any target other than
 * the disposable 8030 testing serve.
 *
 * Staging week: picked dynamically ≥2 weeks ahead of the real current week
 * with an ordinary Monday+Tuesday — outside the 3-working-day lead-time
 * window, so the bookable branches (and the venue base availability row)
 * can never be suppressed by the too-soon rule. Precondition asserts guard
 * the Monday/Tuesday pair against a public holiday so the arithmetic
 * assertions stay honest.
 */

abortUnlessTestingTarget('tests/cancelled-slot-layout.spec.ts');

test.use({ storageState: '.auth/staff-5770.json' });

/* Synthetic scoped payload for the DEFAULT grid (my-timetable). Two cancelled
   originals, each with a same-cell landing request (exact + partial overlap),
   plus one normal class — all owned by the viewer so myTeachingStats counts. */
function defaultSynthetic(me: string) {
  return [
    { id: 9001, sessionId: 901, eventKey: 'occ:202605:901:14', code: 'CXL1', name: 'Cancelled Original', type: 'L', venue: 'B001', lecturer: me, di: 0, start: 2, end: 5, status: 'cancelled', cohorts: ['RSD3(S1)'], studentCount: 20 },
    { id: 9002, sessionId: 901, requestId: 77, eventKey: 'request:77', code: 'CXL1', name: 'Cancelled Original', type: 'L', venue: 'B001', lecturer: me, di: 0, start: 4, end: 7, status: 'pending', requestedAt: '2026-01-01 09:00', requestedBy: me, cohorts: ['RSD3(S1)'], studentCount: 20 },
    { id: 9003, sessionId: 902, eventKey: 'occ:202605:902:14', code: 'CXL2', name: 'Second Cancel', type: 'T', venue: 'B002', lecturer: me, di: 1, start: 2, end: 5, status: 'cancelled', cohorts: ['RSD3(S1)'], studentCount: 20 },
    { id: 9004, sessionId: 902, requestId: 78, eventKey: 'request:78', code: 'CXL2', name: 'Second Cancel', type: 'T', venue: 'B002', lecturer: me, di: 1, start: 3, end: 5, status: 'replacement', remarks: '11-Nov-2026', cohorts: ['RSD3(S1)'], studentCount: 20 },
    { id: 9005, sessionId: 903, eventKey: 'occ:202605:903:14', code: 'NRM1', name: 'Normal Class', type: 'L', venue: 'B003', lecturer: me, di: 1, start: 0, end: 1, status: 'normal', cohorts: ['RSD3(S1)'], studentCount: 20 },
  ];
}

/* Synthetic scoped payload for the CUSTOM venue grid (venue-timetable):
   two overlapping cancelled history records (→ 2 lanes) over hours that are
   TRULY free, plus a LIVE pending blocker at 17:00–18:00 that must keep
   blocking. History covers 09:00–12:00; the live blocker covers 17:00–18:00. */
function venueSynthetic() {
  return [
    { id: 8001, sessionId: 801, eventKey: 'occ:202605:801:14', code: 'CXL9', name: 'Room History', type: 'L', venue: 'B005', lecturer: 'Other Lecturer', di: 0, start: 2, end: 5, status: 'cancelled', mine: false, cohorts: ['RSD3(S1)'] },
    { id: 8002, sessionId: 802, eventKey: 'occ:202605:802:14', code: 'CXL8', name: 'Room History 2', type: 'L', venue: 'B005', lecturer: 'Other Lecturer', di: 0, start: 4, end: 7, status: 'cancelled', mine: false, cohorts: ['RSD3(S1)'] },
    { id: 8003, sessionId: 803, requestId: 79, eventKey: 'request:79', code: 'BLK1', name: 'Live Blocker', type: 'L', venue: 'B005', lecturer: 'Other Lecturer', di: 0, start: 18, end: 19, status: 'pending', mine: false, cohorts: ['RSD3(S1)'] },
  ];
}

/* The TRUTHFUL room-matrix fixture for venueSynthetic (A21): the history
   rows were freed (free offers 09:00–12:00) and the live pending booking
   holds its rows (17:00–18:00). Only what the fixture asserts is stated —
   every other hour stays ABSENT (no offer), so nothing is fabricated. */
function venueSyntheticMatrix(): Record<string, string> {
  const cells: Record<string, string> = {};
  for (let hi = 2; hi <= 7; hi++) cells[`0:${hi}`] = 'free';
  cells['0:18'] = 'blocked';
  cells['0:19'] = 'blocked';
  return cells;
}

async function stageAndRebuild(page: Page, wk: number, events: unknown[], matrix?: Record<string, string>) {
  return page.evaluate(({ wk, events, matrix }) => {
    const w = window as unknown as {
      eventsData: unknown[][]; roomCellStatesByWeek?: Record<string, Record<string, string>>[];
      selectWeek: (v: string) => void; buildTimetable: () => void;
    };
    const backup = {
      events: JSON.stringify(w.eventsData[wk]),
      cells: (w.roomCellStatesByWeek ? JSON.stringify(w.roomCellStatesByWeek[wk]) : null) ?? null,
    };
    w.eventsData[wk] = events;
    if (w.roomCellStatesByWeek && matrix) w.roomCellStatesByWeek[wk] = matrix;
    w.selectWeek(String(wk));
    return backup;
  }, { wk, events, matrix });
}

async function unstage(page: Page, wk: number, backup: { events: string; cells: string | null }) {
  await page.evaluate(({ wk, backup }) => {
    const w = window as unknown as {
      eventsData: unknown[][]; roomCellStatesByWeek?: Record<string, Record<string, string>>[];
      buildTimetable: () => void;
    };
    w.eventsData[wk] = JSON.parse(backup.events);
    if (w.roomCellStatesByWeek && backup.cells != null) w.roomCellStatesByWeek[wk] = JSON.parse(backup.cells);
    w.buildTimetable();
  }, { wk, backup });
}

/* A rendered block/card must sit inside its OWN parent td — visibility is
   judged against the actual cell, never the viewport (horizontal off-screen
   is allowed while the grid scrolls). */
async function blockInsideOwnTd(page: Page, selector: string): Promise<boolean> {
  return page.evaluate((sel) => {
    const el = document.querySelector(sel) as HTMLElement | null;
    const td = el?.closest('td') ?? null;
    if (!el || !td) return false;
    const r = el.getBoundingClientRect();
    const t = td.getBoundingClientRect();
    return r.left >= t.left - 1 && r.right <= t.right + 1
      && r.top >= t.top - 1 && r.bottom <= t.bottom + 1;
  }, selector);
}

/* The theme swap animates (theme.css `--transition` + the theme-toggle
   helpers) and a screenshot taken mid-fade shows a half-blended page. The
   test only WAITS for the page's own animations/transitions to finish
   before capturing — production CSS/behaviour is untouched. A bounded
   fallback keeps an infinite animation (the pulse family) from hanging. */
async function settleThemeAnimations(page: Page): Promise<void> {
  await page.evaluate(() => new Promise<void>((resolve) => {
    const running = document.getAnimations({ subtree: true })
      .filter((a) => a.playState === 'running' || a.playState === 'pending');
    if (!running.length) { resolve(); return; }
    let left = running.length;
    const done = () => { clearTimeout(timer); resolve(); };
    const timer = setTimeout(done, 1000);
    for (const a of running) {
      a.finished.then(() => { if (--left === 0) done(); })
        .catch(() => { if (--left === 0) done(); });
    }
  }));
}

/**
 * The staging week: the first week at least TWO weeks ahead of the real
 * current week whose Monday+Tuesday are ordinary bookable days — safely
 * outside the 3-working-day lead-time window, so the bookable branches (and
 * the venue base availability row) can never be suppressed by the too-soon
 * rule. -1 = no eligible week (test fails loudly, never stages silently).
 */
async function pickStagingWeek(page: Page): Promise<number> {
  return page.evaluate(() => {
    const w = window as unknown as {
      weekData: { days: { holiday: boolean; sunday: boolean; today: boolean }[] }[];
      isSlotTooSoon: (wd: unknown, wk: number, di: number) => boolean;
    };
    let cur = 0;
    w.weekData.forEach((week, i) => week.days.forEach((d) => { if (d.today) cur = i; }));
    for (let i = cur + 2; i < w.weekData.length; i++) {
      const d = w.weekData[i].days;
      if (!d[0].holiday && !d[0].sunday && !d[1].holiday && !d[1].sunday
        && !w.isSlotTooSoon(w.weekData, i, 0) && !w.isSlotTooSoon(w.weekData, i, 1)) {
        return i;
      }
    }
    return -1;
  });
}

test.describe('cancelled-slot stacked layout receipts', () => {

  test('S17/S18/S19 — default grid: lanes, spans, day header, per-identity targets, summaries', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const wk = await pickStagingWeek(page);
    expect(wk, 'a bookable staging week (Mon+Tue, ≥2 weeks ahead) must exist').toBeGreaterThanOrEqual(0);

    /* precondition: the staged Monday/Tuesday must be ordinary days */
    const dayFlags = await page.evaluate((w) => {
      const win = window as unknown as { weekData: { days: { holiday: boolean; sunday: boolean }[] }[] };
      const d = win.weekData[w].days;
      return { mon: d[0].holiday || d[0].sunday, tue: d[1].holiday || d[1].sunday };
    }, wk);
    expect(dayFlags.mon, 'staging Monday must not be a holiday/Sunday').toBe(false);
    expect(dayFlags.tue, 'staging Tuesday must not be a holiday/Sunday').toBe(false);

    const backup = await stageAndRebuild(page, wk, defaultSynthetic(
      await page.evaluate(() => (window as unknown as { MockData: { currentUser: { name: string } } }).MockData.currentUser.name),
    ));

    try {
      /* ── lanes: two physical rows for Monday, three tallies of identity ── */
      const monRows = page.locator('#tableBody tr[data-day-index="0"]');
      await expect(monRows).toHaveCount(2);
      await expect(page.locator('#tableBody tr[data-day-index="0"][data-lane-row="0"]')).toHaveCount(1);
      await expect(page.locator('#tableBody tr[data-day-index="0"][data-lane-row="1"]')).toHaveCount(1);
      /* day header spans its physical lane rows */
      await expect(page.locator('#tableBody tr[data-day-index="0"] td.time-col[rowspan="2"]')).toHaveCount(1);

      /* ── true positions/spans: cancelled lane 0 at 09:00, span 4 (2 h) ── */
      const occTd = page.locator('#tableBody tr[data-lane-row="0"][data-day-index="0"] td[data-hour="2"]');
      const occBlock = occTd.locator('.event-block[data-event-key="occ:202605:901:14"]');
      await expect(occBlock).toHaveCount(1);
      expect(await occTd.evaluate((td) => (td as HTMLTableCellElement).colSpan)).toBe(4);

      /* pending landing lane 1 at 10:00, same-start coordinate shared with
         the cancelled original (its tail) — BOTH survive, never overwritten */
      const reqTd = page.locator('#tableBody tr[data-lane-row="1"][data-day-index="0"] td[data-hour="4"]');
      const reqBlock = reqTd.locator('.event-block[data-event-key="request:77"]');
      await expect(reqBlock).toHaveCount(1);
      expect(await reqTd.evaluate((td) => (td as HTMLTableCellElement).colSpan)).toBe(4);
      expect(await page.locator('#tableBody .event-block[data-event-key="occ:202605:901:14"]').count()).toBe(1);
      expect(await page.locator('#tableBody .event-block[data-event-key="request:77"]').count()).toBe(1);

      /* day 1: cancelled lane 0 + approved replacement lane 1 + normal class */
      await expect(page.locator('#tableBody tr[data-day-index="1"]')).toHaveCount(2);
      await expect(page.locator('#tableBody tr[data-lane-row="0"][data-day-index="1"] .event-block[data-event-key="occ:202605:902:14"]')).toHaveCount(1);
      await expect(page.locator('#tableBody tr[data-lane-row="1"][data-day-index="1"] .event-block[data-event-key="request:78"]')).toHaveCount(1);

      /* ── a no-overlap day keeps its single physical row (existing height) ── */
      await expect(page.locator('#tableBody tr[data-day-index="2"]')).toHaveCount(1);

      /* ── one focusable target per identity; click opens THAT event ── */
      await occBlock.click();
      await expect(page.locator('#classModal')).toBeVisible();
      await expect(page.locator('#classModal')).toContainText('Class cancelled — a replacement is needed');
      await page.keyboard.press('Escape');
      await expect(page.locator('#classModal')).toBeHidden();

      /* ── keyboard: Enter AND Space open the same focused target ── */
      await reqBlock.focus();
      await page.keyboard.press('Enter');
      await expect(page.locator('#classModal')).toBeVisible();
      await expect(page.locator('#classModal')).toContainText('Replacement request awaiting approval');
      await page.keyboard.press('Escape');
      await expect(page.locator('#classModal')).toBeHidden();
      await reqBlock.focus();
      await page.keyboard.press(' ');
      await expect(page.locator('#classModal')).toBeVisible();
      await page.keyboard.press('Escape');
      await expect(page.locator('#classModal')).toBeHidden();

      /* ── jumps: the SOURCE selector must hit the cancelled original, never
            its same-cell landing neighbour; the request selector hits the
            landing block (S19) ── */
      const srcHits = await page.evaluate(() => {
        const els = Array.from(document.querySelectorAll('#tableBody .event-block[data-sid="901"]:not([data-rid])'));
        const land = Array.from(document.querySelectorAll('#tableBody .event-block[data-sid="901"][data-rid="77"]'));
        return {
          src: els.map((el) => (el as HTMLElement).dataset.eventKey),
          land: land.map((el) => (el as HTMLElement).dataset.eventKey),
        };
      });
      expect(srcHits.src).toEqual(['occ:202605:901:14']);
      expect(srcHits.land).toEqual(['request:77']);

      /* ── honest summaries (S22): 3 non-cancelled identities printed as the
            finished part + pending share (2+1), 4.5 h total (2.5+2),
            conflicts = the two cancellations once each ── */
      await expect(page.locator('#sumTotal')).toHaveText('2+1');
      await expect(page.locator('#sumPending')).toHaveText('1');
      await expect(page.locator('#sumReplacement')).toHaveText('1');
      await expect(page.locator('#sumConflict')).toHaveText('2');
      await expect(page.locator('#sumHours')).toHaveText('2.5+2');
      /* My Teaching (D7): the personal page has NO My-Teaching cards — the
         finished summary cards here are #sumTotal/#sumHours/#sumReplacement/
         #sumPending/#sumConflict only (My Classes/My Hours live on the cohort
         and venue pages). The same viewer-restricted tally is therefore
         asserted PURELY through the shared myTeachingStats() engine over the
         staged payload: 3 non-cancelled identities (1 pending), 4.5 h of
         which 2 h pending — the cancelled originals never inflate it. */
      const my = await page.evaluate((wk) => {
        const w = window as unknown as {
          eventsData: unknown[][];
          myTeachingStats: (events: unknown[]) => { classes: number; hours: number; pendingClasses: number; pendingHours: number };
        };
        return w.myTeachingStats(w.eventsData[wk]);
      }, wk);
      expect(my.classes).toBe(3);
      expect(my.pendingClasses).toBe(1);
      expect(my.hours).toBe(4.5);
      expect(my.pendingHours).toBe(2);
    } finally {
      await unstage(page, wk, backup);
    }
  });

  test('S20 — venue custom grid: history lanes + base availability row, real selection, live blockers', async ({ page }) => {
    await page.goto('/venue-timetable-ui?venue=B005', { waitUntil: 'networkidle' });

    const wk = await pickStagingWeek(page);
    expect(wk, 'a bookable staging week (Mon, ≥2 weeks ahead) must exist').toBeGreaterThanOrEqual(0);

    const dayFlags = await page.evaluate((w) => {
      const win = window as unknown as { weekData: { days: { holiday: boolean; sunday: boolean }[] }[] };
      const d = win.weekData[w].days;
      return { mon: d[0].holiday || d[0].sunday };
    }, wk);
    expect(dayFlags.mon, 'staging Monday must not be a holiday/Sunday').toBe(false);

    const backup = await stageAndRebuild(page, wk, venueSynthetic(), venueSyntheticMatrix());

    try {
      /* ── Monday: 2 history lanes + 1 base availability row; header spans 3 ── */
      await expect(page.locator('#tableBody tr[data-day-index="0"]')).toHaveCount(3);
      await expect(page.locator('#tableBody tr[data-day-index="0"][data-lane-row="base"]')).toHaveCount(1);
      await expect(page.locator('#tableBody tr[data-day-index="0"] td.time-col[rowspan="3"]')).toHaveCount(1);

      /* cancelled history stacked: 09:00–11:00 lane 0, 10:00–12:00 lane 1 */
      await expect(page.locator('#tableBody tr[data-lane-row="0"][data-day-index="0"] .event-block[data-event-key="occ:202605:801:14"]')).toHaveCount(1);
      await expect(page.locator('#tableBody tr[data-lane-row="1"][data-day-index="0"] .event-block[data-event-key="occ:202605:802:14"]')).toHaveCount(1);

      /* the base row exposes EXACTLY the covered-and-truly-free hours
         (09:00–12:00 = half-hour indices 2..7) as green booking cells */
      const baseGreens = page.locator('#tableBody tr[data-lane-row="base"][data-day-index="0"] td .cell-available');
      await expect(baseGreens).toHaveCount(6);
      for (let hi = 2; hi <= 7; hi++) {
        await expect(page.locator(`#tableBody tr[data-lane-row="base"][data-day-index="0"] td[data-hour="${hi}"] .cell-available`)).toHaveCount(1);
      }

      /* NO duplicated green booking controls in the history lanes (S20) */
      for (let hi = 2; hi <= 7; hi++) {
        await expect(page.locator(`#tableBody tr[data-lane-row="0"][data-day-index="0"] td[data-hour="${hi}"] .cell-available`)).toHaveCount(0);
        await expect(page.locator(`#tableBody tr[data-lane-row="1"][data-day-index="0"] td[data-hour="${hi}"] .cell-available`)).toHaveCount(0);
      }

      /* live pending blocker (17:00–18:00, indices 18–19) still blocks:
         no green card anywhere at those hours, and the span check refuses */
      for (let hi = 18; hi <= 19; hi++) {
        await expect(page.locator(`#tableBody tr[data-day-index="0"] td[data-hour="${hi}"] .cell-available`)).toHaveCount(0);
      }
      expect(await page.evaluate(() => {
        const w = window as unknown as { nextEventStartAfter: (di: number, hi: number) => number };
        return w.nextEventStartAfter(0, 3);
      })).toBe(18);   // the cancelled starts at 4/2 are IGNORED; the live 18:00 start is the next real event

      /* history does not limit a selectable span: the geometric span check
         (used by hover + the intent modal's stage 2) ignores CANCELLED rows */
      const spanFacts = await page.evaluate(() => {
        const w = window as unknown as {
          spanBlocked: (di: number, hi: number, slots: number) => boolean;
          roomBeforeNextEvent: (di: number, hi: number) => number;
        };
        return {
          coveredFreeSpan: w.spanBlocked(0, 3, 4),      // 4 slots from 09:30 — history only below/over
          liveSpan: w.spanBlocked(0, 17, 2),            // runs into the live pending at 18
          room: w.roomBeforeNextEvent(0, 3),
        };
      });
      expect(spanFacts.coveredFreeSpan).toBe(false);
      expect(spanFacts.liveSpan).toBe(true);
      expect(spanFacts.room).toBeGreaterThanOrEqual(2);

      /* ── the designated actual cell answers a click on the BASE row ── */
      await page.locator('#tableBody tr[data-lane-row="base"][data-day-index="0"] td[data-hour="3"] .cell-available').click();
      await expect(page.locator('#availableTooltip')).toHaveClass(/show/);
      await expect(page.locator('#tooltipText')).toContainText('B005');
      await page.keyboard.press('Escape');
      await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);

      /* ── a no-overlap day keeps one physical row, no base row ── */
      await expect(page.locator('#tableBody tr[data-day-index="4"]')).toHaveCount(1);
      await expect(page.locator('#tableBody tr[data-day-index="4"][data-lane-row="base"]')).toHaveCount(0);

      /* ── the AVAILABLE card equals the green cells actually on screen ── */
      const greens = await page.locator('#tableBody .cell-available').count();
      const card = await page.locator('#sumAvailable').textContent();
      expect(Number(card)).toBe(greens);
    } finally {
      await unstage(page, wk, backup);
    }
  });

  test('S20b — A21 matrix: pre-cleanup held cells stay neutral under history, released offers re-open the base', async ({ page }) => {
    await page.goto('/venue-timetable-ui?venue=B005', { waitUntil: 'networkidle' });

    const wk = await pickStagingWeek(page);
    expect(wk, 'a bookable staging week must exist').toBeGreaterThanOrEqual(0);

    /* One cancelled history run 09:00–12:00 (indices 2..7).
       PRE-CLEANUP: the raw rows 09:00–11:00 are STILL occupied (no actual
       holder renders); 11:00–12:00 was already released (free offers). */
    const history = [
      { id: 8101, sessionId: 811, eventKey: 'occ:202605:811:14', code: 'CXL7', name: 'Pre-Cleanup', type: 'L', venue: 'B005', lecturer: 'Other Lecturer', di: 0, start: 2, end: 7, status: 'cancelled', mine: false, cohorts: ['RSD3(S1)'] },
    ];
    const preMatrix: Record<string, string> = {};
    for (let hi = 2; hi <= 5; hi++) preMatrix[`0:${hi}`] = 'blocked';
    for (let hi = 6; hi <= 7; hi++) preMatrix[`0:${hi}`] = 'free';

    const backup = await stageAndRebuild(page, wk, history, preMatrix);
    try {
      /* the base row exists only for the released tail (6..7) */
      await expect(page.locator('#tableBody tr[data-day-index="0"]')).toHaveCount(2);
      await expect(page.locator('#tableBody tr[data-day-index="0"][data-lane-row="base"]')).toHaveCount(1);

      /* held hours: NO green card, a NEUTRAL held cell — never a fabricated
         NORMAL/bookable look under the cancelled history */
      for (let hi = 2; hi <= 5; hi++) {
        const td = page.locator(`#tableBody tr[data-lane-row="base"][data-day-index="0"] td[data-hour="${hi}"]`);
        await expect(td.locator('.cell-available')).toHaveCount(0);
        await expect(td.locator('.cell-held')).toHaveCount(1);
      }
      /* released hours carry the green base cells */
      for (let hi = 6; hi <= 7; hi++) {
        await expect(page.locator(`#tableBody tr[data-lane-row="base"][data-day-index="0"] td[data-hour="${hi}"] .cell-available`)).toHaveCount(1);
      }

      /* the actual truth feeds the span decision: the run over the held
         rows refuses even though no event geometry covers them */
      expect(await page.evaluate(() => {
        const w = window as unknown as { spanBlocked: (di: number, hi: number, slots: number) => boolean };
        return w.spanBlocked(0, 3, 4);
      })).toBe(true);
    } finally {
      await unstage(page, wk, backup);
    }

    /* RELEASED: the whole original run falls back to available offers —
       the history keeps its lane AND the base row re-opens green. */
    const releasedMatrix: Record<string, string> = {};
    for (let hi = 2; hi <= 7; hi++) releasedMatrix[`0:${hi}`] = 'free';
    const backup2 = await stageAndRebuild(page, wk, history, releasedMatrix);
    try {
      const baseGreens = page.locator('#tableBody tr[data-lane-row="base"][data-day-index="0"] td .cell-available');
      await expect(baseGreens).toHaveCount(6);
      for (let hi = 2; hi <= 7; hi++) {
        await expect(page.locator(`#tableBody tr[data-lane-row="base"][data-day-index="0"] td[data-hour="${hi}"] .cell-held`)).toHaveCount(0);
      }
      /* history alone never blocks a span: the freed run now fits */
      expect(await page.evaluate(() => {
        const w = window as unknown as { spanBlocked: (di: number, hi: number, slots: number) => boolean };
        return w.spanBlocked(0, 3, 4);
      })).toBe(false);
    } finally {
      await unstage(page, wk, backup2);
    }
  });

  test('S21 — responsive widths, both themes and print keep every lane row', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/venue-timetable-ui?venue=B005', { waitUntil: 'networkidle' });
    const wk = await pickStagingWeek(page);
    expect(wk, 'a bookable staging week must exist').toBeGreaterThanOrEqual(0);
    const backup = await stageAndRebuild(page, wk, venueSynthetic(), venueSyntheticMatrix());

    try {
      const info = test.info();

      /* true geometry: each visible block/card sits INSIDE its own parent td
         (never judged against the viewport — horizontal off-screen is allowed
         while the grid scrolls) */
      expect(await blockInsideOwnTd(page, '#tableBody tr[data-lane-row="0"][data-day-index="0"] .event-block[data-event-key="occ:202605:801:14"]')).toBe(true);
      expect(await blockInsideOwnTd(page, '#tableBody tr[data-lane-row="1"][data-day-index="0"] .event-block[data-event-key="occ:202605:802:14"]')).toBe(true);
      expect(await blockInsideOwnTd(page, '#tableBody tr[data-lane-row="base"][data-day-index="0"] td[data-hour="3"] .cell-available')).toBe(true);

      for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        for (const theme of ['light', 'dark']) {
          await page.evaluate((t) => {
            const html = document.documentElement;
            html.classList.remove('light', 'dark');
            html.classList.add(t);
          }, theme);
          /* settle the theme's own animations/transitions before any assert
             or capture (no global transition masking in production) */
          await settleThemeAnimations(page);
          const baseCell = page.locator('#tableBody tr[data-lane-row="base"][data-day-index="0"] td[data-hour="3"] .cell-available');
          await expect(baseCell).toBeVisible();
          await expect(page.locator('#tableBody tr[data-lane-row="0"][data-day-index="0"] .event-block[data-event-key="occ:202605:801:14"]')).toBeVisible();
          await expect(page.locator('#tableBody tr[data-lane-row="1"][data-day-index="0"] .event-block[data-event-key="occ:202605:802:14"]')).toBeVisible();

          /* evidence screenshot for the main session to inspect */
          const shot = info.outputPath(`s21-${width}-${theme}.png`);
          await page.screenshot({ path: shot });
          await info.attach(`s21-${width}-${theme}`, { path: shot, contentType: 'image/png' });
        }
        /* horizontal access is asserted on the MAIN grid's OWN .grid-scroll
           ancestor (`#tableBody`'s closest) — never the first .grid-scroll in
           the document, which is not necessarily the real grid */
        if (width >= 768) {
          const facts = await page.evaluate(() => {
            const el = (document.getElementById('tableBody') as HTMLElement | null)?.closest('.grid-scroll');
            if (!el) return null;
            const cs = getComputedStyle(el);
            return { ox: cs.overflowX, sw: el.scrollWidth, cw: el.clientWidth };
          });
          expect(facts, 'the MAIN grid must sit inside a .grid-scroll').not.toBeNull();
          expect(facts!.sw).toBeGreaterThanOrEqual(facts!.cw);
          if (width > 768) {
            /* desktop: the frozen theme keeps horizontal scrolling */
            expect(facts!.ox === 'auto' || facts!.ox === 'scroll').toBe(true);
          } else {
            /* exactly 768 hits the frozen ≤768px mobile-card layout
               (theme.css "Mobile card layout for timetable grid") — cards fit
               100% width and overflow is intentionally hidden; the lane
               visibility asserts above prove every block still renders */
            expect(facts!.ox).toBe('hidden');
          }
        }

        /* cancelled-slot-reuse A19 mobile receipt: at 390px the base row's
           free offers must carry their OWN visible time range and a ≥44px
           touch target (the desktop absolute fill used to persist and
           collapse them into blank thin green bars), with no duplicate
           identity or card inflation. */
        if (width === 390) {
          const baseFacts = await page.evaluate(() => {
            const els = Array.from(
              document.querySelectorAll('#tableBody tr[data-lane-row="base"][data-day-index="0"] .cell-base-offer'),
            ) as HTMLElement[];
            return els.map((el) => {
              const label = el.querySelector('.cell-time-label') as HTMLElement | null;
              const lr = label ? label.getBoundingClientRect() : null;
              return {
                hour: Number(el.dataset.hour),
                text: label ? label.textContent : '',
                visible: !!label
                  && getComputedStyle(label).display !== 'none'
                  && !!lr && lr.width > 0 && lr.height > 0,
                target: el.getBoundingClientRect().height,
              };
            });
          });
          expect(baseFacts.map((f) => f.hour)).toEqual([2, 3, 4, 5, 6, 7]);
          for (const f of baseFacts) {
            expect(f.visible, `base hour ${f.hour}: time label visible`).toBe(true);
            expect(f.target, `base hour ${f.hour}: ≥44px touch target`).toBeGreaterThanOrEqual(44);
            /* safe textContent, 12-hour times, the standard `~` range joiner */
            expect(f.text).toMatch(/^\d{1,2}:\d{2} (AM|PM) ~ \d{1,2}:\d{2} (AM|PM)$/);
          }
          /* no duplicate identity / card inflation on mobile */
          expect(await page.locator('#tableBody .event-block[data-event-key="occ:202605:801:14"]').count()).toBe(1);
          expect(await page.locator('#tableBody .event-block[data-event-key="occ:202605:802:14"]').count()).toBe(1);
          expect(await page.locator('#tableBody tr[data-day-index="0"][data-lane-row="base"]').count()).toBe(1);
          expect(await page.locator('#tableBody .cell-available').count()).toBe(6);
        }
      }

      /* print emulation retains ALL physical rows (no fixed-height clipping) */
      await page.emulateMedia({ media: 'print' });
      await expect(page.locator('#tableBody tr[data-lane-row="0"][data-day-index="0"] .event-block[data-event-key="occ:202605:801:14"]')).toBeVisible();
      await expect(page.locator('#tableBody tr[data-lane-row="1"][data-day-index="0"] .event-block[data-event-key="occ:202605:802:14"]')).toBeVisible();
      await expect(page.locator('#tableBody tr[data-lane-row="base"][data-day-index="0"] td[data-hour="3"] .cell-available')).toBeVisible();
      const printShot = info.outputPath('s21-print.png');
      await page.screenshot({ path: printShot, fullPage: true });
      await info.attach('s21-print', { path: printShot, contentType: 'image/png' });
      await page.emulateMedia({ media: 'screen' });
    } finally {
      await unstage(page, wk, backup);
    }
  });

  /* A16 half-hour DOM regression (default grid, NO DB writes — the same
     stageAndRebuild/unstage JS staging as every proof above). The D6-style
     half-hour split on a THURSDAY: pending 13:00–14:00 (cells 10–11), a
     cancelled original 13:30–14:30 (cells 11–12) overlapping its tail, and
     an ADJACENT normal 14:30–15:30 (cells 13–14) that must SHARE lane 0
     with the cancelled original → two physical rows, cancellation lane 0,
     pending lane 1, day header rowspan 2. Asserts the REAL rendered td
     left/width against the row's own empty half-hour cell (origin + per-
     slot step, self-calibrated from two adjacent empty cells), not merely
     the colSpan metadata, so shifted HTML colSpans move the td and fail. */
  test('half-hour DOM regression: Thursday pending 13:00–14:00 / cancelled 13:30–14:30 / adjacent normal 14:30–15:30 on two true lanes', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });

    const wk = await pickStagingWeek(page);
    expect(wk, 'a bookable staging week (Mon+Tue, ≥2 weeks ahead) must exist').toBeGreaterThanOrEqual(0);

    /* precondition: the staged THURSDAY must be an ordinary day */
    const thuOff = await page.evaluate((w) => {
      const win = window as unknown as { weekData: { days: { holiday: boolean; sunday: boolean }[] }[] };
      const d = win.weekData[w].days[3];
      return d.holiday || d.sunday;
    }, wk);
    expect(thuOff, 'staging Thursday must not be a holiday/Sunday').toBe(false);

    const me = await page.evaluate(() =>
      (window as unknown as { MockData: { currentUser: { name: string } } }).MockData.currentUser.name);

    const backup = await stageAndRebuild(page, wk, [
      { id: 9201, sessionId: 921, eventKey: 'occ:202605:921:14', code: 'THC1', name: 'Thursday Cancelled', type: 'L', venue: 'B004', lecturer: me, di: 3, start: 11, end: 12, status: 'cancelled', cohorts: ['RSD3(S1)'], studentCount: 20 },
      { id: 9202, sessionId: 921, requestId: 920, eventKey: 'request:920', code: 'THC1', name: 'Thursday Pending', type: 'L', venue: 'B004', lecturer: me, di: 3, start: 10, end: 11, status: 'pending', requestedAt: '2026-01-01 09:00', requestedBy: me, cohorts: ['RSD3(S1)'], studentCount: 20 },
      { id: 9203, sessionId: 923, eventKey: 'occ:202605:923:14', code: 'THN1', name: 'Thursday Normal', type: 'L', venue: 'B006', lecturer: me, di: 3, start: 13, end: 14, status: 'normal', cohorts: ['RSD3(S1)'], studentCount: 20 },
    ]);

    const cxlSel = '#tableBody tr[data-day-index="3"][data-lane-row="0"] .event-block[data-event-key="occ:202605:921:14"]';
    const pendSel = '#tableBody tr[data-day-index="3"][data-lane-row="1"] .event-block[data-event-key="request:920"]';
    const normSel = '#tableBody tr[data-day-index="3"][data-lane-row="0"] .event-block[data-event-key="occ:202605:923:14"]';

    const info = test.info();
    try {
      for (const variant of [{ width: 1440, height: 900, theme: 'light' }, { width: 390, height: 844, theme: 'dark' }]) {
        await page.setViewportSize({ width: variant.width, height: variant.height });
        await page.evaluate((t) => {
          const html = document.documentElement;
          html.classList.remove('light', 'dark');
          html.classList.add(t);
        }, variant.theme);
        await settleThemeAnimations(page);

        /* two physical rows; the day header spans BOTH lane rows */
        await expect(page.locator('#tableBody tr[data-day-index="3"]')).toHaveCount(2);
        await expect(page.locator('#tableBody tr[data-day-index="3"] td.time-col[rowspan="2"]')).toHaveCount(1);

        /* one block per identity, exactly where the planner put it */
        for (const sel of [cxlSel, pendSel, normSel]) {
          await expect(page.locator(sel)).toHaveCount(1);
          await expect(page.locator(sel)).toBeVisible();
          expect(await blockInsideOwnTd(page, sel), `${sel} must sit inside its own td`).toBe(true);
        }

        /* metadata + real geometry of every lane row's data-hour cells */
        const geo = await page.evaluate(() => {
          const grab = (lane: string) => {
            const row = document.querySelector(`#tableBody tr[data-day-index="3"][data-lane-row="${lane}"]`) as HTMLTableRowElement | null;
            if (!row) return null;
            const tds = Array.from(row.querySelectorAll<HTMLTableCellElement>('td[data-hour]'));
            const rects = tds.map((td) => td.getBoundingClientRect());
            const cells: Record<string, { left: number; width: number; span: number }> = {};
            tds.forEach((td, k) => {
              cells[td.dataset.hour as string] = { left: rects[k].left, width: rects[k].width, span: td.colSpan };
            });
            /* origin + per-slot step self-calibrated from two adjacent EMPTY
               single cells of the SAME row (cells 0 and 1 are empty here) */
            return {
              domCount: tds.length,
              origin: rects[0].left,
              unit: rects[0].width,
              step: rects[1].left - rects[0].left,
              cells,
            };
          };
          return { lane0: grab('0'), lane1: grab('1') };
        });
        const g0 = geo.lane0!;
        const g1 = geo.lane1!;

        /* every lane row still carries ALL 22 half-hour data-hour cells */
        expect(g0.domCount, 'lane 0 data-hour cells').toBe(22);
        expect(g1.domCount, 'lane 1 data-hour cells').toBe(22);

        /* colSpan metadata: 2 exactly where the half-hour split says so */
        expect(g0.cells['11'].span, 'cancelled td colSpan').toBe(2);
        expect(g0.cells['13'].span, 'normal td colSpan').toBe(2);
        expect(g1.cells['10'].span, 'pending td colSpan').toBe(2);

        if (variant.width >= 768) {
          /* desktop table layout: the VISIBLE data-hour cells' colSpans must
             total exactly the day's 22 half-hour slots in BOTH lane rows —
             a shifted or lost colspan breaks this total */
          const totals = await page.evaluate(() => ['0', '1'].map((lane) => {
            const row = document.querySelector(`#tableBody tr[data-day-index="3"][data-lane-row="${lane}"]`) as HTMLTableRowElement;
            return Array.from(row.querySelectorAll<HTMLTableCellElement>('td[data-hour]'))
              .reduce((s, td) => s + (td.getBoundingClientRect().width > 0 ? td.colSpan : 0), 0);
          }));
          expect(totals, 'visible colSpan totals per lane row').toEqual([22, 22]);

          /* REAL rendered positions: left = origin + hour × per-slot step,
             width = 2 half-hour columns (unit + step) — shifted HTML
             colSpans displace the td and fail here, metadata cannot. */
          const near = (got: number, want: number, what: string) =>
            expect(Math.abs(got - want), what).toBeLessThanOrEqual(2);
          /* cancelled 13:30–14:30 → lane 0 at cell 11, 2 slots wide */
          near(g0.cells['11'].left, g0.origin + 11 * g0.step, 'cancelled td left = origin + 11 slots');
          near(g0.cells['11'].width, g0.unit + g0.step, 'cancelled td width = 2 half-hour columns');
          /* adjacent normal 14:30–15:30 → SAME lane 0 at cell 13 */
          near(g0.cells['13'].left, g0.origin + 13 * g0.step, 'normal td left = origin + 13 slots');
          near(g0.cells['13'].width, g0.unit + g0.step, 'normal td width = 2 half-hour columns');
          /* pending 13:00–14:00 → lane 1 at cell 10, sharing 13:30–14:00 */
          near(g1.cells['10'].left, g1.origin + 10 * g1.step, 'pending td left = origin + 10 slots');
          near(g1.cells['10'].width, g1.unit + g1.step, 'pending td width = 2 half-hour columns');
        }
        /* at 390 the frozen ≤768px mobile-card layout stacks the cells and
           hides the empty half-hour cells (theme.css), so column arithmetic
           is undefined there — the visibility/containment asserts above
           carry the variant, matching the S21 receipt's precedent */

        /* coordinates are judged RELATIVE to the grid's own origin/viewport
           (the .grid-scroll ancestor), never the page viewport, so allowed
           horizontal scrolling can never fake a pass at either variant */
        for (const sel of [cxlSel, pendSel, normSel]) {
          const inside = await page.evaluate((s) => {
            const el = document.querySelector(s) as HTMLElement;
            const scroll = el.closest('.grid-scroll') as HTMLElement | null;
            if (!scroll) return null;
            const r = el.getBoundingClientRect();
            const sr = scroll.getBoundingClientRect();
            return { before: r.left - sr.left, after: sr.right - r.right };
          }, sel);
          expect(inside, `${sel} must live inside the grid's own .grid-scroll`).not.toBeNull();
          expect(inside!.before, `${sel} clipped left of the grid origin`).toBeGreaterThanOrEqual(-1);
          expect(inside!.after, `${sel} clipped right of the grid viewport`).toBeGreaterThanOrEqual(-1);
        }

        /* evidence screenshot for the main session */
        const name = `half-hour-dom-${variant.width}-${variant.theme}`;
        const shot = info.outputPath(`${name}.png`);
        await page.screenshot({ path: shot, fullPage: true });
        await info.attach(name, { path: shot, contentType: 'image/png' });
      }
    } finally {
      await unstage(page, wk, backup);
    }
  });
});
