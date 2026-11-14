import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { pickSpanFreeCell, selectWeek } from './helpers/intent-modal-helper';
import { throwUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * venue-book.spec.ts — U2a end-to-end coverage for the venue-page booking
 * affordance (design D5). One login per run via the setup project's
 * storageState; distinct slots per test to avoid sessionStorage bleed.
 *
 * Fixtures are probed dynamically, never hard-coded against occupancy:
 * free cells are taken from the LAST semester week (far future, outside
 * the 3-working-day lead-time); too-soon probes run on the REAL current
 * week (the mockNow clock bridge makes the page open there).
 */
test.use({ storageState: '.auth/staff-5770.json' });

const PAGE = '/venue-timetable-ui?venue=B110';

async function selectLastWeek(page: Page): Promise<void> {
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  await page.evaluate((idx) => (window as any).selectWeek(idx), last);
  await page.waitForTimeout(300);
}

const FREE_CELL = '.cell-available:not(.cell-no-fit):not(.cell-no-room)';

async function firstFreeCell(page: Page, selector = FREE_CELL) {
  return page.locator(selector).first();
}

/** Walk the semester until a week shows the given cell state; -1 when none. */
async function weekWith(page: Page, cls: string): Promise<number> {
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  for (let w = 0; w <= last; w++) {
    await selectWeek(page, w);
    if (await page.locator('#tableBody .cell-available.' + cls).count() > 0) return w;
  }
  return -1;
}

/* ═══════════════════════════════════════════════════════════════════════════
   T6/T8 tail-offer fixture (cancelled-slot-reuse A21 reconciliation).
   The seed's grid offers end at 18:00 (time_slots rows start 08:00–17:30),
   so the day's FINAL hour-axis cells (18:00 = hi 20, 18:30 = hi 21) have no
   offer row and render inert — a no-fit cell can never exist without a
   fixture. T6/T8 therefore STAGE the two missing tail offers (status
   available, version 1) for one future bookable week/day discovered on the
   page's own campus clock (lead time + holidays — never a hardcoded week),
   guarded by a before-write existence check and a runtime current_database
   check, and DELETE only their own rows in finally. Read-only siblings are
   untouched; the whole-run receipt keeps CRS_ALLOW_SQL=1. */
function sql(query: string): string {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  return execFileSync('psql',
    ['-X', '-q', '-t', '-v', 'ON_ERROR_STOP=1', '-U', 'philler', '-d', 'class_replacement_testing', '-c', oneLine],
    { encoding: 'utf8' }).trim();
}

/** The ACTUAL connection must be the disposable testing DB before ANY write. */
function assertTestingDb(): void {
  const db = sql('SELECT current_database()');
  if (db !== 'class_replacement_testing') {
    throw new Error(`fixture write REFUSED: connected to [${db}], only class_replacement_testing may be written`);
  }
}

/** Mirror of Semester::active(): start_date <= campus today, latest first,
 *  then id 1, then the latest row — the id is discovered, never assumed. */
function discoverSemesterId(): number {
  const campus = sql("SELECT to_char((now() AT TIME ZONE 'Asia/Kuala_Lumpur')::date, 'YYYY-MM-DD')");
  let row = sql(`SELECT id FROM semesters WHERE start_date <= '${campus}' ORDER BY start_date DESC, id ASC LIMIT 1`);
  if (!row) row = sql('SELECT id FROM semesters WHERE id = 1');
  if (!row) row = sql('SELECT id FROM semesters ORDER BY start_date DESC, id ASC LIMIT 1');
  if (!row) throw new Error('no semester row exists');
  return Number(row);
}

interface TailFixture { weekIdx: number; weekNumber: number; dayIdx: number; venueId: number; semesterId: number; slotIds: number[] }

/** The two tail offers the seed never creates: 18:00 (hi 20) and 18:30
 *  (hi 21), both available — the REAL last hour-axis cells of the day. */
const TAIL_OFFERS = [
  { hi: 20, start: '18:00', end: '18:30' },
  { hi: 21, start: '18:30', end: '19:00' },
];

/** Insert the tail offers for B110 on a future bookable week/day (the page's
 *  own lead-time + holiday clock picks it), then reload so the served room
 *  matrix carries them. */
async function stageTailOffers(page: Page): Promise<TailFixture> {
  assertTestingDb();
  const target = await page.evaluate(() => {
    const wd = (window as any).weekData;
    for (let w = wd.length - 1; w >= 0; w--) {
      const d = firstBookableDayIn(wd, w);   // bookable = not Sunday/holiday/too-soon
      if (d >= 0) return { weekIdx: w, dayIdx: d };
    }
    return null;
  });
  if (!target) {
    throw new Error('no future bookable week exists on the campus clock — the tail-offer fixture has nowhere truthful to land');
  }

  const venueId = Number(sql(`SELECT id FROM venues WHERE room_code = 'B110'`));
  if (!venueId) throw new Error('venue B110 not found');
  const semesterId = discoverSemesterId();
  const weekNumber = target.weekIdx + 1;

  // guard: query the target BEFORE the write — never double-write a cell
  for (const offer of TAIL_OFFERS) {
    const exists = Number(sql(`SELECT count(*) FROM time_slots
        WHERE venue_id = ${venueId} AND semester_id = ${semesterId}
          AND week_number = ${weekNumber} AND day_of_week = ${target.dayIdx}
          AND start_time = '${offer.start}:00'`));
    if (exists !== 0) {
      throw new Error(`tail fixture target already holds a row (B110 W${weekNumber} D${target.dayIdx} ${offer.start}) — refusing to double-write`);
    }
  }

  const slotIds: number[] = [];
  try {
    for (const offer of TAIL_OFFERS) {
      assertTestingDb();
      slotIds.push(Number(sql(`INSERT INTO time_slots
          (semester_id, venue_id, class_session_id, week_number, day_of_week,
           start_time, end_time, status, version, created_at, updated_at)
         VALUES (${semesterId}, ${venueId}, NULL, ${weekNumber}, ${target.dayIdx},
                 '${offer.start}:00', '${offer.end}:00', 'available', 1, now(), now())
         RETURNING id`)));
    }

    // a fresh payload: the served room matrix is read at DOMContentLoaded
    await page.goto(PAGE);
    await selectWeek(page, target.weekIdx);
  } catch (err) {
    // never leave a half-staged fixture behind — a reload/setup failure
    // cleans up too
    cleanupTailOffers({ weekIdx: target.weekIdx, weekNumber, dayIdx: target.dayIdx, venueId, semesterId, slotIds });
    throw err;
  }
  return { weekIdx: target.weekIdx, weekNumber, dayIdx: target.dayIdx, venueId, semesterId, slotIds };
}

/** Scoped restore: delete only the rows THIS fixture inserted — and only while
 *  each still reads exactly like the fixture's own creation (version 1,
 *  available, ownerless, the exact staged coordinate) with no live request
 *  link and no ACTIVE anchor whose fallback span intersects the row (same
 *  semester/week/day/venue, row start inside anchor start + span) — not just
 *  an exact anchor match. The checks and the guarded delete run in ONE atomic
 *  DO block: any drift RAISEs and deletes nothing, so a newer holder is never
 *  freed silently. */
function cleanupTailOffers(fx: TailFixture): void {
  fx.slotIds.forEach((id, i) => {
    assertTestingDb();
    const expectedStart = `${TAIL_OFFERS[i].start}:00`;
    sql(`DO $tail$ DECLARE
           v_deleted int;
         BEGIN
           IF NOT EXISTS (
             SELECT 1 FROM time_slots
              WHERE id = ${id}
                AND status = 'available' AND version = 1 AND class_session_id IS NULL
                AND venue_id = ${fx.venueId} AND semester_id = ${fx.semesterId}
                AND week_number = ${fx.weekNumber} AND day_of_week = ${fx.dayIdx}
                AND start_time = '${expectedStart}'
           ) THEN
             RAISE EXCEPTION 'tail fixture row ${id} is gone or drifted from its created shape (B110 W${fx.weekNumber} D${fx.dayIdx} ${expectedStart}) — refusing to delete a reused offer';
           END IF;
           IF EXISTS (
             SELECT 1 FROM replacement_request_slots WHERE time_slot_id = ${id}
           ) THEN
             RAISE EXCEPTION 'tail fixture row ${id} acquired a live request link — refusing to delete';
           END IF;
           IF EXISTS (
             SELECT 1 FROM replacement_requests r
               JOIN time_slots a ON a.id = r.replacement_time_slot_id
               JOIN time_slots s ON s.id = ${id}
              WHERE r.status IN ('pending', 'approved')
                AND a.semester_id = s.semester_id AND a.week_number = s.week_number
                AND a.day_of_week = s.day_of_week AND a.venue_id = s.venue_id
                AND s.start_time >= a.start_time
                AND s.start_time < a.start_time + COALESCE(NULLIF(r.replacement_span, 0), 1) * interval '30 minutes'
           ) THEN
             RAISE EXCEPTION 'tail fixture row ${id} acquired a live anchor-span claim — refusing to delete';
           END IF;
           DELETE FROM time_slots
            WHERE id = ${id}
              AND status = 'available' AND version = 1 AND class_session_id IS NULL
              AND venue_id = ${fx.venueId} AND semester_id = ${fx.semesterId}
              AND week_number = ${fx.weekNumber} AND day_of_week = ${fx.dayIdx}
              AND start_time = '${expectedStart}';
           GET DIAGNOSTICS v_deleted = ROW_COUNT;
           IF v_deleted <> 1 THEN
             RAISE EXCEPTION 'tail fixture row ${id} changed during cleanup — refusing to delete a newer holder';
           END IF;
         END $tail$;`);
  });
}

test('U2a-T1: free cell exposes the "Arrange ?" hover overlay (class + ::after label)', async ({ page }) => {
  await page.goto(PAGE);
  await selectLastWeek(page);
  const cell = await firstFreeCell(page);
  await expect(cell).toHaveClass(/cell-available/);
  const label = await cell.evaluate((el) => getComputedStyle(el, '::after').content);
  expect(label).toContain('Arrange ?');
});

test('U2a-T2: clicking a free cell opens the tooltip with the exact Arrange sentence', async ({ page }) => {
  await page.goto(PAGE);
  await selectLastWeek(page);
  const cell = await firstFreeCell(page);
  const di = await cell.getAttribute('data-day');
  const hi = await cell.getAttribute('data-hour');
  const expected = await page.evaluate(
    ([d, h]) => {
      const day = (window as any).weekData[(window as any).currentWeek].days[Number(d)];
      /* the arrange question is a SENTENCE, so the date/time standard prose
         applies (audit §0 ruling 3 + rule 11: the day word spelled out, the
         time 12-hour) — the page composes exactly this in
         showAvailableTooltip, so the expectation mirrors it, room/date/time
         all exact. */
      const dayLong: Record<string, string> = {
        Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday',
        Fri: 'Friday', Sat: 'Saturday', Sun: 'Sunday',
      };
      return 'Arrange a replacement in B110 on ' + (dayLong[day.abbr] || day.abbr) + ', '
        + day.date + ' at ' + to12h(hours[Number(h)]) + '?';
    },
    [di, hi],
  );
  await cell.click();
  const tooltip = page.locator('#availableTooltip');
  await expect(tooltip).toHaveClass(/show/);
  await expect(page.locator('#tooltipText')).toHaveText(expected);
  await expect(page.locator('#tooltipBookBtn')).toHaveText('Arrange');
});

test('U2a-T3: clicking Arrange opens the session modal, then hands off — arrangement page opens pre-pinned', async ({ page }) => {
  // SDD arrange-intent-session-choice: the modal interposes (designed contract
  // change — session + cohort + week join the handoff); routed through it here.
  // The pool is WEEK-AGNOSTIC (VenueTimetable::intentSessionOptions): $week1 no
  // longer filters candidates — every own conflicted session occurrence is
  // offered wherever the booked cell lives. So a future landing week is valid
  // even when the pinned SOURCE occurrence (the Deepavali AMCS2093 week) sits
  // in the past; the last semester week keeps this test bookable without a
  // hardcoded week number.
  await page.goto(PAGE);
  await selectLastWeek(page);
  await pickSpanFreeCell(page);
  await page.locator('#availableTooltip.show #tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  // the modal's options arrive over a wire call — wait for the populated
  // fitting row (the first fit is auto-selected) before pressing Arrange
  await expect(page.locator('#intentSessionPanel .session-dd-item[data-fit="1"]').first())
    .toBeAttached({ timeout: 10_000 });
  await page.locator('#intentArrangeBtn').click();
  await page.waitForURL(/\/replacement-arrangement\/?\?.*session=/, { timeout: 10_000 });
  expect(page.url()).toMatch(/venue=B110&date=.+&time=.+&from=venue-timetable&code=.+&session=\d+&cohort=.+&week=\d+/);
  // consumer: the pinned session bound (subject auto-picked) + banner-free
  // booked state (pre-selected toast path)
  await expect(page.locator('#subjectSelector')).toHaveValue(/AMCS|BMIT|CS/);
});

test('U2a-T4: real current week — lead-time cells render cell-too-soon, read-only', async ({ page }) => {
  await page.goto(PAGE); // opens on the REAL current week (mockNow bridge)
  const tooSoon = page.locator('.cell-too-soon');
  const count = await tooSoon.count();
  expect(count).toBeGreaterThanOrEqual(1);
  const first = tooSoon.first();
  await expect(first).not.toHaveAttribute('tabindex');
  await first.click();
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
});

test('U2a-T5: B opens the tooltip on a focused free cell; Escape and .grid-scroll hide it', async ({ page }) => {
  await page.goto(PAGE);
  await selectLastWeek(page);
  const cell = await firstFreeCell(page);
  await cell.focus();
  await page.keyboard.press('b');
  await expect(page.locator('#availableTooltip')).toHaveClass(/show/);
  await page.keyboard.press('Escape');
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
  await page.keyboard.press('b');
  await expect(page.locator('#availableTooltip')).toHaveClass(/show/);
  await page.locator('.grid-scroll').evaluate((el) => el.dispatchEvent(new Event('scroll')));
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
});

test('U2a-T6: the day-end cell reads "Not Enough Slot" (green) and click gives the shared toast, no navigation', async ({ page }) => {
  /* 2026-11-14 (venue-evening-tail, user): the red "Too Late / to Book" day-end
     state is retired. With the evening slots live, 18:30–19:00 is a REAL cell
     with only 30 minutes left, and the user ruled it must read like every other
     "your class cannot fit here" cell: green + "Not Enough Slot". */
  // writes: guarded fail-closed (exact strings, first line, loud red)
  throwUnlessTestingTarget('U2a-T6', { requireAllowSql: true });
  await page.goto(PAGE);
  const fx = await stageTailOffers(page);
  try {
    // the day's final hour-axis cell must be green + "Not Enough Slot", and the
    // red day-end class must no longer be painted anywhere.
    expect(await page.locator('.cell-available.cell-no-fit').count(), 'no red day-end cell is painted').toBe(0);
    const dayEnd = page.locator('.cell-available.cell-no-room').last();
    const coords = await dayEnd.evaluate((el) => ({ day: el.dataset.day, hour: el.dataset.hour }));
    const lastHourIdx = await page.evaluate(() => hours.length - 1);
    expect(Number(coords.hour), 'the labelled cell sits at the FINAL hour-axis index').toBe(lastHourIdx);
    const facts = await dayEnd.evaluate((el) => ({
      label: getComputedStyle(el, '::after').content.toLowerCase(),
      cursor: getComputedStyle(el).cursor,
    }));
    expect(facts.label).toContain('not enough slot');
    expect(facts.cursor).toBe('not-allowed');
    await dayEnd.click();
    // venue-slot-validation (2026-11-06): the hover floor is a fixed 60 minutes —
    // never a class-derived number, never the old 120.
    await expect(page.locator('#toastBar .toast-message'))
      .toHaveText('Not enough room below this class — pick a Free Cell.');
    await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
    expect(page.url()).toContain('/venue-timetable-ui');
  } finally {
    cleanupTailOffers(fx);
  }
});

test('U2a-T7: a free cell with too little room before the next class is blocked by cursor + words only', async ({ page }) => {
  // venue-timetable-bookable-cells change 3 (user ruling 2026-11-05): the cell
  // keeps its normal background; only the cursor and the hover words change.
  await page.goto(PAGE);
  const week = await weekWith(page, 'cell-no-room');
  expect(week, 'no blocked cell in any week of this venue').toBeGreaterThanOrEqual(0);

  const cell = page.locator('#tableBody .cell-available.cell-no-room').first();
  await expect(cell).not.toHaveClass(/cell-no-fit/);   // the two states are exclusive
  const facts = await cell.evaluate((el) => {
    const cs = getComputedStyle(el);
    return { cursor: cs.cursor, label: getComputedStyle(el, '::after').content, bg: cs.backgroundColor };
  });
  expect(facts.cursor).toBe('not-allowed');
  expect(facts.label.toLowerCase()).toContain('not enough slot');
  const normalBg = await page.locator('#tableBody ' + FREE_CELL).first()
    .evaluate((el) => getComputedStyle(el).backgroundColor);
  expect(facts.bg).toBe(normalBg);                     // no red at rest

  await cell.click();
  await expect(page.locator('#toastBar .toast-message'))
    .toHaveText('Not enough room below this class — pick a Free Cell.');
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
});

test('U2a-T8: the late and no-room states follow the real shortest class, cell by cell', async ({ page }) => {
  // writes: guarded fail-closed (exact strings, first line, loud red)
  throwUnlessTestingTarget('U2a-T8', { requireAllowSql: true });
  await page.goto(PAGE);
  const fx = await stageTailOffers(page);
  try {
    /* both arms, judged SEPARATELY against the page's real contracts — never
       one folded formula (cancelled-slot-reuse A21 reconciliation):
       · END OF DAY (no-fit): `hi + MIN_BOOK_SLOTS > hours.length` — the day
         axis alone decides; the staged 18:30 offer exercises it for real.
       · NEXT BLOCK (no-room): the EXPECTED room is derived INDEPENDENTLY of
         the page helper — the first live (cancelled history is NOT occupancy,
         S20) event start after `hi`, and the first non-free SERVED row after
         `hi`, whichever comes first, and never later than the grid's end.
         The actual roomBeforeNextEvent must EQUAL that derivation before the
         class comparison — this checks the page's bug fixes instead of merely
         repeating its own result.
       · 2026-11-14 (venue-evening-tail, user): the scan stops at the day's
         LAST served row, so the unoffered half-hours after it are no longer a
         blocker and the room is capped by `hours.length`. `Infinity` therefore
         cannot occur any more; a room that reaches the grid's end is grounded
         by the DAY END itself, and a served cell's own day-end red rule only
         applies where a real row exists (`hi <= last served row`). */
    const audit = await page.evaluate(() => {
      const min = 2;   /* MIN_BOOK_SLOTS — the fixed one-hour hover floor */
      const hoursLen = hours.length;
      const cells = Array.from(document.querySelectorAll('#tableBody .cell-available')) as HTMLElement[];
      const bad: string[] = [];
      let late = 0;
      let dayEndRoom = 0;
      let unofferedTail = 0;
      cells.forEach((el) => {
        const di = Number(el.dataset.day);
        const hi = Number(el.dataset.hour);
        const matrix = ((window as any).roomCellStatesByWeek || [])[(window as any).currentWeek] || {};
        /* the day's LAST served row — derived from the matrix itself */
        let lastServed = -1;
        for (let k = 0; k < hoursLen; k++) {
          if (matrix[di + ':' + k] !== undefined) lastServed = k;
        }
        const isTail = hi > lastServed;
        if (isTail) unofferedTail += 1;
        /* 2026-11-14 (venue-evening-tail, user): the red day-end arm is retired —
           no cell may wear cell-no-fit any more. The day end is just another
           "not enough room" case, so it is judged by the room test below. */
        if (el.classList.contains('cell-no-fit')) bad.push('late ' + di + '/' + hi);
        /* arm 2 — the next-block gap, derived INDEPENDENTLY first */
        const liveStarts = ((MockData.myTimetable.eventsByWeek || [])[(window as any).currentWeek] || [])
          .filter((ev: any) => ev.di === di && ev.status !== 'cancelled' && ev.start > hi)
          .map((ev: any) => ev.start);
        let firstBlocked = Infinity;
        for (let k = hi + 1; k <= lastServed; k++) {
          if (matrix[di + ':' + k] !== 'free') { firstBlocked = k; break; }
        }
        /* the next blocker is an ABSOLUTE index; the room helper answers a
           DELTA from hi, capped by the grid's own end */
        const next = Math.min(...liveStarts, firstBlocked, hoursLen);
        const derived = next - hi;
        const room = (window as any).roomBeforeNextEvent(di, hi);
        if (room !== derived) {
          bad.push('room-derivation ' + di + '/' + hi + ' actual=' + room + ' derived=' + derived);
        }
        if ((room < min) !== el.classList.contains('cell-no-room')) {
          bad.push('room ' + di + '/' + hi + ' room=' + room + ' min=' + min);
        }
        if (hi + room === hoursLen) {
          dayEndRoom += 1;   // grounded by the grid's own end
          return;
        }
        /* a FINITE room below the end must be grounded: a live event starts
           exactly at hi+room, or the room run ends at a non-free SERVED row */
        const evGround = ((MockData.myTimetable.eventsByWeek || [])[(window as any).currentWeek] || [])
          .some((ev: any) => ev.di === di && ev.status !== 'cancelled' && ev.start === hi + room);
        const mxGround = matrix[di + ':' + (hi + room)] !== 'free';
        if (!evGround && !mxGround) bad.push('room ungrounded ' + di + '/' + hi + ' room=' + room);
      });
      return { min, hoursLen, checked: cells.length, late, dayEndRoom, unofferedTail, bad };
    });
    expect(audit.checked).toBeGreaterThan(0);
    expect(audit.late, 'no cell wears the retired red day-end class').toBe(0);
    expect(audit.dayEndRoom, 'some rooms run to the grid end and are grounded by it').toBeGreaterThan(0);
    expect(audit.bad).toEqual([]);
  } finally {
    cleanupTailOffers(fx);
  }
});

test('U2a-T17: the evening tail is painted, counted and labelled (venue-evening-tail)', async ({ page }) => {
  /* 2026-11-14 (user ruling): the grid keeps ending at 19:00, and the half-hours
     after the day's last served row (17:30) are no longer blank —
       17:30–18:00  plain green, NO "Not Enough Slot"
       18:00–18:30  plain green
       18:30–19:00  green + "Not Enough Slot" (never the red "Too Late to Book")
     and all of them count towards Available. Read-only: nothing is staged. */
  await selectWeek(page, await page.evaluate(() => (window as any).weekData.length - 1));
  await page.waitForTimeout(400);

  const tail = await page.evaluate(() => {
    const rowFor = (di: number) => {
      const out: Record<number, { cls: string; label: string } | null> = {};
      [18, 19, 20, 21].forEach((hi) => {
        const el = document.querySelector(`#tableBody td[data-day="${di}"][data-hour="${hi}"] .cell-content`);
        out[hi] = el ? { cls: (el as HTMLElement).className, label: getComputedStyle(el, '::after').content } : null;
      });
      return out;
    };
    /* the last bookable day of the visible week (never Sunday/holiday) */
    const days = (window as any).weekData[(window as any).currentWeek].days;
    let di = -1;
    days.forEach((d: any, i: number) => { if (!d.sunday && !d.holiday) di = i; });
    return {
      di,
      cells: rowFor(di),
      greenCells: document.querySelectorAll('#tableBody .cell-available').length,
      availableCard: (document.getElementById('sumAvailable')?.textContent || '').trim(),
    };
  });

  expect(tail.di, 'a plain (non-Sunday, non-holiday) day exists').toBeGreaterThanOrEqual(0);
  const c = tail.cells;
  for (const hi of [18, 19, 20, 21]) expect(c[hi], `cell ${hi} renders`).not.toBeNull();

  // 17:00–17:30 and 17:30–18:00 and 18:00–18:30: plain green, no label
  for (const hi of [18, 19, 20]) {
    expect(c[hi]!.cls, `hi ${hi}: available`).toContain('cell-available');
    expect(c[hi]!.cls, `hi ${hi}: not the red day-end state`).not.toContain('cell-no-fit');
    expect(c[hi]!.cls, `hi ${hi}: no "Not Enough Slot"`).not.toContain('cell-no-room');
    expect(c[hi]!.label.toLowerCase()).toContain('arrange');
  }

  // 18:30–19:00: green + "Not Enough Slot", never red
  expect(c[21]!.cls).toContain('cell-available');
  expect(c[21]!.cls).toContain('cell-no-room');
  expect(c[21]!.cls).not.toContain('cell-no-fit');
  expect(c[21]!.label.toLowerCase()).toContain('not enough slot');

  // the tail counts towards Available, so the card matches the grid
  expect(Number(tail.availableCard)).toBe(tail.greenCells);
});

test('U2a-T9: "Earliest bookable" sits after Today, jumps to the semester week and paints the chip', async ({ page }) => {
  // venue-timetable-bookable-cells change 4
  await page.goto(PAGE);
  await expect(page.locator('#todayBtn')).toHaveCount(1);
  await expect(page.locator('#earliestBtn')).toHaveCount(1);
  const order = await page.evaluate(() => {
    const t = document.getElementById('todayBtn')!;
    const e = document.getElementById('earliestBtn')!;
    return (t.compareDocumentPosition(e) & Node.DOCUMENT_POSITION_FOLLOWING) ? 'after' : 'before';
  });
  expect(order).toBe('after');
  // the tooltip is computed (real date + week), never the static fallback
  await expect(page.locator('#earliestBtn')).toHaveAttribute('data-tip', /— .+, \d{1,2} \w+ \d{4} \(Week \d+\)/);

  const target = await page.evaluate(() => {
    const wd = (window as any).weekData;
    for (let w = 0; w < wd.length; w++) {
      for (let d = 0; d < wd[w].days.length; d++) if (wd[w].days[d].firstBookable) return { week: w, day: d };
    }
    return null;
  });
  expect(target).not.toBeNull();

  await selectLastWeek(page);
  await expect(page.locator('.bookable-badge')).toHaveCount(0);   // not on the last week
  await page.locator('#earliestBtn').click();
  await page.waitForTimeout(800);
  expect(await page.evaluate(() => (window as any).currentWeek)).toBe(target!.week);
  await expect(page.locator('#tableBody tr.bookable-flash').first()).toBeVisible({ timeout: 5000 });
  await expect(page.locator('.bookable-badge').first()).toHaveText(/bookings open/i);
});

test('U2a-T10: the arrangement page keeps its single action button (regression)', async ({ page }) => {
  // showEarliestBtn defaults to false, so the shared toolbar is unchanged there:
  // that page's Today button IS the earliest variant.
  await page.goto('/replacement-arrangement');
  await expect(page.locator('#earliestBtn')).toHaveCount(1);
  await expect(page.locator('#todayBtn')).toHaveCount(0);
});

/* ═══════════════════════════════════════════════════════════════════════════
   venue-slot-validation (prompts/Venue Timetable Slot Validation.md)
   Fixture: lecturer 5770 at B002, LAST semester week, Monday — 12:00/12:30/13:00
   free, 14:00–15:00 occupied by MPU-3232 (a regular class, so the shape is the
   same in every teaching week; the last week keeps the run date-independent).
   Pool there (fresh seed): AMCS2093 (L) = 2 hours (4 slots), AMCS2093 (T) =
   1 hour (2 slots) — the two durations the brief's table needs, picked by the
   row's own `data-slots`, never by a hard-coded module name.
   ═══════════════════════════════════════════════════════════════════════════ */
const B002 = '/venue-timetable-ui?venue=B002';
const MONDAY = 0;

/** Free cell → Arrange tooltip → Select-a-Session modal, ready to be used. */
async function openArrangeMonday(page: Page, hour: number): Promise<void> {
  await page.goto(B002);
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  await page.evaluate((w) => (window as any).selectWeek(w), last);
  await page.waitForTimeout(400);
  await page.locator(`.cell-available[data-day="${MONDAY}"][data-hour="${hour}"]`).click();
  await page.waitForTimeout(300);
  await page.locator('#tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 20_000 });
  await expect(page.locator('#intentSubjectSelect option').first()).toBeAttached({ timeout: 20_000 });
  await page.waitForTimeout(300);
}

/** Pick the session whose real length is `slots` half-hours — the brief's cases
 *  are about duration, not about a particular module. */
async function pickSessionOfLength(page: Page, slots: number): Promise<void> {
  /* the session picker is a custom dropdown (the rows live in a closed panel
     until the trigger opens it — the native-select parity shell) */
  await page.locator('#intentSessionTrigger').click();
  await expect(page.locator('#intentSessionDd')).toHaveClass(/open/);
  const row = page.locator(`#intentSessionPanel .session-dd-item[data-slots="${slots}"]`).first();
  await expect(row).toBeVisible({ timeout: 10_000 });
  await row.click();
  await expect(row).toHaveClass(/active/);
  await expect(page.locator('#intentSessionDd')).not.toHaveClass(/open/);
}

test('U2a-T11: a cell with only 30 minutes before the next class stays "Not Enough Slot" (brief case 1)', async ({ page }) => {
  await page.goto(B002);
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  await page.evaluate((w) => (window as any).selectWeek(w), last);
  await page.waitForTimeout(400);
  const cell = page.locator(`.cell-available[data-day="${MONDAY}"][data-hour="11"]`);   // 13:30, class at 14:00
  await expect(cell).toHaveClass(/cell-no-room/);
  await expect(cell).not.toHaveClass(/cell-no-fit/);
  const label = await cell.evaluate((el) => getComputedStyle(el, '::after').content);
  expect(label.toLowerCase()).toContain('not enough slot');
  await cell.click();
  await expect(page.locator('#toastBar .toast-message'))
    .toHaveText('Not enough room below this class — pick a Free Cell.');
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
});

test('U2a-T12: a 2-hour class at 12:00 fits before the 2 PM class and books (brief case 2)', async ({ page }) => {
  await openArrangeMonday(page, 8);                       // 12:00
  await pickSessionOfLength(page, 4);                     // 2-hour class
  await page.locator('#intentArrangeBtn').click();
  await page.waitForURL(/\/replacement-arrangement\?/, { timeout: 20_000 });
  expect(page.url()).toContain('venue=B002');
  expect(page.url()).toContain('time=12%3A00');
  expect(page.url()).toContain('code=AMCS2093');
  expect(page.url()).toContain('from=venue-timetable');
});

test('Ua2-T13: a 2-hour class at 12:30 warns instead of booking (brief case 3)', async ({ page }) => {
  await openArrangeMonday(page, 9);                       // 12:30
  await pickSessionOfLength(page, 4);                     // 2-hour class
  await page.locator('#intentArrangeBtn').click();
  await expect(page.locator('#slotWarningOverlay')).toBeVisible({ timeout: 10_000 });
  await expect(page.locator('#slotWarningHead'))
    .toHaveText('None of your classes can run in B002 at this cell');
  await expect(page.locator('#slotWarningBody'))
    .toHaveText(/^12:30 plus the selected class duration \(2 hours\) runs into an existing event below — the class starting at 14:00\.$/);
  await expect(page.locator('#slotWarningGuide')).toHaveText('NOT ENOUGH FREE TIME BELOW');
  await expect(page.locator('#slotWarningTail')).toContainText('pick a Free Cell');
  await expect(page.locator('#intentSessionModal')).toBeHidden();
  expect(page.url()).toContain('/venue-timetable-ui');    // no navigation
});

test('U2a-T14: a 2-hour class at 13:00 warns (brief case 4) and closing returns to the grid', async ({ page }) => {
  await openArrangeMonday(page, 10);                      // 13:00
  await pickSessionOfLength(page, 4);                     // 2-hour class
  await page.locator('#intentArrangeBtn').click();
  await expect(page.locator('#slotWarningOverlay')).toBeVisible({ timeout: 10_000 });
  await expect(page.locator('#slotWarningBody')).toContainText('13:00 plus the selected class duration (2 hours)');
  await page.locator('#slotWarningPickBtn').click();
  await expect(page.locator('#slotWarningOverlay')).toBeHidden();
  await expect(page.locator('#tableBody')).toBeVisible();
  expect(page.url()).toContain('/venue-timetable-ui');
});

test('U2a-T15: a 1-hour class at 13:00 books (brief case 5)', async ({ page }) => {
  await openArrangeMonday(page, 10);                      // 13:00
  await pickSessionOfLength(page, 2);                     // 1-hour class
  await page.locator('#intentArrangeBtn').click();
  await page.waitForURL(/\/replacement-arrangement\?/, { timeout: 20_000 });
  expect(page.url()).toContain('time=13%3A00');
  expect(page.url()).toContain('code=AMCS2093');
});

test('U2a-T16: no duration verdict is shown before a class is chosen (brief case 6)', async ({ page }) => {
  await openArrangeMonday(page, 10);                      // 13:00 — 2h cannot fit here
  // nothing is warned on open / on subject change
  await expect(page.locator('#intentNotice')).toBeHidden();
  await expect(page.locator('#slotWarningOverlay')).toBeHidden();
  await expect(page.locator('#intentPickers')).toBeVisible();
  // every session of the subject is listed, each carrying its own length, and
  // the too-long one is listed too — with no verdict attached to it
  const rows = await page.locator('#intentSessionPanel .session-dd-item').evaluateAll((els) =>
    els.map((e) => ({ slots: (e as HTMLElement).dataset.slots, fit: (e as HTMLElement).dataset.fit })));
  expect(rows.length).toBeGreaterThan(0);
  const twoHour = rows.find((r) => r.slots === '4');
  expect(twoHour, 'the 2-hour class must still be listed').toBeTruthy();
  expect(twoHour!.fit).toBe('0');
  expect(rows.some((r) => r.fit === '1'), 'a fitting 1-hour row exists here').toBe(true);
  await pickSessionOfLength(page, 4);
  await expect(page.locator('#intentNotice')).toBeHidden();
  await expect(page.locator('#slotWarningOverlay')).toBeHidden();
  await expect(page.locator('#intentArrangeBtn')).toBeEnabled();
});
