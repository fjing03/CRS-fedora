import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * arrangement-mobile — T21a–T23c of SDD `arrangement-mobile-day-view`.
 *
 * SCOPE (authored only; browser execution remains PAUSED):
 *   T21a — calendar, compact original context and eligible-day navigation
 *          (MA-02a–c, MA-03a–f, MA-14b).
 *   T21b — canonical timeline, whole-class selection and saved summary
 *          (MA-04a–d, MA-05a–e, MA-06a–c).
 *   T21c — blocked-row sheets, legend and Tip
 *          (MA-07a–d; the five-second/persistent split is asserted where it
 *          belongs to the information sheet, not to the transient toast).
 *   T22a — existing change guards, arrival/pin/intent and leave guards
 *          (MA-09a–b).
 *   T22b — clock expiry, week-option rebuild and saved-store retention
 *          (MA-10a–d).
 *   T22c — mobile-origin request identity, busy and error lifecycle
 *          (MA-11a–d, MA-08b).
 *   T23a — native keys, sheet focus/containment/return and one polite
 *          announcement (MA-12a–e, MA-07e, MA-14a).
 *   T23b — 768/769 boundary, node identity/restoration, 320/390/768 zoom,
 *          both themes, 44px/48px geometry, overlays and other-page isolation
 *          (MA-01a–c, MA-13a–c, MA-14c).
 *   T23c — five-second transient vs persistent details/errors, Tip tooltip
 *          suppression/restoration, single controller/listeners and teardown
 *          (MA-07c–e, MA-08a–b, MA-14a–c, MA-15a–b).
 *
 * Every T23 case is authored against the same real page/adapter contract as
 * T21–T22. T23 adds only page-context probes (computed styles, bounding boxes,
 * listener counts, the shared toast clock); it adds NO SQL writes and NO
 * production/shared edits.
 *
 * The page is the real `/replacement-arrangement` page at a 390×844 mobile
 * viewport (the adapter breakpoint is `max-width: 768px`). Assertions read the
 * adapter's public DOM contract and the page's real canonical grid/helpers —
 * never a copied availability engine:
 *   [data-arrangement-mobile-days] button[data-day-index]
 *   [data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour]
 *     [data-mobile-state], [data-mobile-day]
 *   [data-mobile-row="span"][data-mobile-span]
 *   [data-arrangement-mobile-summary-host], [data-arrangement-mobile-summary-opener]
 *   #arrangementMobileInfo + [data-arrangement-mobile-info-*]
 *   #weekSelector, #earliestBtn, #summaryTip, #submitRequestBtn, #tableBody
 *
 * FIXTURE HYGIENE (the standing 2026-11-01 data-loss guard):
 *   - module-level `abortUnlessTestingTarget(..., { requireAllowSql: true })`:
 *     exact CRS_MODE=testing + CRS_BASE=http://127.0.0.1:8030 + CRS_ALLOW_SQL=1.
 *   - the only SQL writes are: (a) one room/week `time_slots` reservation,
 *     (b) one `class_exceptions` week-1 row for a past occurrence, (c) a set of
 *     marker-labelled `holidays` rows for the empty-semester case.
 *   - every write captures its exact ids/keys and is reversed in `afterEach`
 *     and again in `afterAll`; nothing is swept by a blanket predicate.
 *   - the spec is serial and must be run ALONE (fixtures mutate one seeded
 *     testing DB). No default :8000, no demo DB.
 *
 * The spec is NOT executed in this batch. Environment-dependent cases skip
 * loudly with a reason instead of being counted as passes.
 *
 * REQUIRES the disposable testing DB seeded + a serve on :8030:
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/arrangement-mobile.spec.ts
 */

// The standing guard, first statement after imports. This file writes fixtures,
// so the SQL switch is required as well as the testing base.
abortUnlessTestingTarget('tests/arrangement-mobile.spec.ts', { requireAllowSql: true });

/* Page globals used read-only inside `page.evaluate`. Function declarations
   (`to12h`, `add30min`, `isSlotTooSoon`, `weekHasBookableSlot`,
   `firstBookableDay`, `leadTimeCutoff`) are window properties; the time axis
   is a top-level `const` and is therefore accessed by its lexical name. */
declare const hours: string[];
/* T22b: the page's one calendar clock reader (a classic-script lexical class,
   not a window property). Overridden only inside the disposable page context. */
declare const DateHelper: { getTodayMs: () => number };

const BASE = process.env.CRS_BASE as string;
const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';
const SEMESTER_CODE = '202605';
const ROW_COUNT = 22; // 08:00..18:30, one row per half-hour
const EMPTY_SEMESTER_MARK = 'PW T21c mobile empty semester';
const EXCEPTION_REASON = 'medical_leave';

/* ─────────────────────────────────────────────────────────────────────────
 * Serial describe + mobile viewport (a few later cases can still cross to
 * 769/1280 in T23; T21 itself is mobile-only by contract).
 * ───────────────────────────────────────────────────────────────────────── */
test.describe.configure({ mode: 'serial', timeout: 180_000 });
test.use({
  viewport: { width: 390, height: 844 },
  hasTouch: true,
  /* auth.setup.ts logs in ONCE per run and saves this state. Per-test POSTs
     exhaust Fortify's 5/min limiter and make later cases fail at login. */
  storageState: '.auth/staff-5425.json',
});

/* ─────────────────────────────────────────────────────────────────────────
 * SQL fixtures (guarded testing DB only; exact ids/keys everywhere)
 * ───────────────────────────────────────────────────────────────────────── */
function sql(query: string): string {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  return execSync(PSQL + JSON.stringify(oneLine), { encoding: 'utf8' }).trim();
}

interface ReservedRoom {
  room: string;
  week: number;
  flippedIds: number[];
  insertedIds: number[];
}

/** One room's whole week becomes `pending`; returns the exact row ids touched. */
function reserveRoomWeek(room: string, week: number): ReservedRoom {
  const flippedIds = sql(`
    SELECT id FROM time_slots
     WHERE semester_id = (SELECT id FROM semesters WHERE semester_code = '${SEMESTER_CODE}' LIMIT 1)
       AND week_number = ${week}
       AND venue_id = (SELECT id FROM venues WHERE room_code = '${room}' LIMIT 1)
       AND status = 'available'`)
    .split(/\s+/).filter(Boolean).map(Number);

  if (flippedIds.length) {
    sql(`UPDATE time_slots
            SET status = 'pending', version = version + 1, updated_at = now()
          WHERE id IN (${flippedIds.join(',')})`);
  }

  const insertedIds = sql(`
    INSERT INTO time_slots
      (semester_id, class_session_id, week_number, day_of_week, start_time, end_time,
       venue_id, status, version, created_at, updated_at)
    SELECT s.id, NULL, ${week}, gd.d,
           (time '08:00' + (gh.h * interval '30 minutes'))::time,
           (time '08:00' + ((gh.h + 1) * interval '30 minutes'))::time,
           v.id, 'pending', 1, now(), now()
      FROM semesters s, venues v,
           generate_series(0, 5) AS gd(d),
           generate_series(0, 21) AS gh(h)
     WHERE s.semester_code = '${SEMESTER_CODE}'
       AND v.room_code = '${room}'
       AND NOT EXISTS (
         SELECT 1 FROM time_slots x
          WHERE x.semester_id = s.id
            AND x.week_number = ${week}
            AND x.day_of_week = gd.d
            AND x.venue_id = v.id
            AND x.start_time = (time '08:00' + (gh.h * interval '30 minutes'))::time)
    RETURNING id`)
    .split(/\s+/).filter(Boolean).map(Number);

  return { room, week, flippedIds, insertedIds };
}

function releaseRoomWeek(fixture: ReservedRoom): void {
  if (fixture.flippedIds.length) {
    sql(`UPDATE time_slots
            SET status = 'available', version = GREATEST(version - 1, 0), updated_at = now()
          WHERE id IN (${fixture.flippedIds.join(',')})`);
  }
  if (fixture.insertedIds.length) {
    sql(`DELETE FROM time_slots WHERE id IN (${fixture.insertedIds.join(',')})`);
  }
}

interface PendingException {
  sessionId: number;
  week: number;
}

function insertPastException(sessionId: number, week = 1): PendingException {
  sql(`DELETE FROM class_exceptions
        WHERE class_session_id = ${sessionId} AND week_number = ${week}`);
  sql(`INSERT INTO class_exceptions
         (class_session_id, week_number, reason, created_at, updated_at)
       VALUES (${sessionId}, ${week}, '${EXCEPTION_REASON}', now(), now())`);
  return { sessionId, week };
}

function removePastException(fixture: PendingException): void {
  sql(`DELETE FROM class_exceptions
        WHERE class_session_id = ${fixture.sessionId} AND week_number = ${fixture.week}`);
}

/** Marker-labelled holidays on every schedulable day of every week. */
function insertEmptySemesterHolidays(): void {
  sql(`INSERT INTO holidays
         (semester_id, week_number, day_of_week, label, created_at, updated_at)
       SELECT s.id, gw.w, gd.d, '${EMPTY_SEMESTER_MARK}', now(), now()
         FROM semesters s, generate_series(1, 14) AS gw(w), generate_series(0, 5) AS gd(d)
        WHERE s.semester_code = '${SEMESTER_CODE}'
       ON CONFLICT (semester_id, week_number, day_of_week) DO NOTHING`);
}

function removeEmptySemesterHolidays(): void {
  sql(`DELETE FROM holidays WHERE label = '${EMPTY_SEMESTER_MARK}'`);
}

/* Fixtures held for the whole file; each is idempotently released. */
let reservedRooms: ReservedRoom[] = [];
let pastExceptions: PendingException[] = [];

function cleanupFixtures(): void {
  while (reservedRooms.length) releaseRoomWeek(reservedRooms.pop()!);
  while (pastExceptions.length) removePastException(pastExceptions.pop()!);
  removeEmptySemesterHolidays();
}

test.afterEach(() => { cleanupFixtures(); });
test.afterAll(() => { cleanupFixtures(); });

/* ─────────────────────────────────────────────────────────────────────────
 * Page helpers
 * ───────────────────────────────────────────────────────────────────────── */
async function login(page: Page): Promise<void> {
  /* The setup project already authenticated this context and saved
     .auth/staff-5425.json; test.use({ storageState }) loads it. Navigating to a
     protected page proves the session without another POST to Fortify's
     5/min login limiter. */
  await page.goto(`${BASE}/my-timetable-ui?_=mobile-auth-${Date.now()}`, { waitUntil: 'domcontentloaded' });
  await expect(page).not.toHaveURL(/\/login/, { timeout: 30_000 });
}

async function waitForMobile(page: Page): Promise<void> {
  await expect(page.locator('#arrangementMobile')).toBeAttached();
  await page.waitForFunction(() => document.body.classList.contains('arrangement-mobile-mode'));
  await page.waitForFunction(() => {
    const root = document.getElementById('arrangementMobile');
    if (!root) return false;
    const empty = root.querySelector('[data-arrangement-mobile-empty]') as HTMLElement | null;
    const days = root.querySelectorAll('[data-arrangement-mobile-days] button').length;
    return days > 0 || (empty !== null && empty.hidden === false);
  }, undefined, { timeout: 30_000 });
}

function withCacheBust(path: string): string {
  const sep = path.includes('?') ? '&' : '?';
  return `${path}${sep}_=${Date.now()}`;
}

async function gotoArrangement(page: Page, path = '/replacement-arrangement'): Promise<void> {
  await page.goto(`${BASE}${withCacheBust(path)}`, { waitUntil: 'domcontentloaded' });
  await waitForMobile(page);
  await page.waitForFunction(() => document.querySelectorAll('#tableBody .cell-content').length > 0);
}

async function gotoArrival(page: Page, event: MobileEvent): Promise<void> {
  await gotoArrangement(page, `/replacement-arrangement?session=${event.sessionId}&week=${event.weekIndex + 1}`);
  await page.waitForFunction(() => document.querySelectorAll('.slot-dd-item').length > 0, undefined, { timeout: 30_000 });
}

interface MobileEvent {
  sessionId: number;
  weekIndex: number;
  code: string;
  durationSlots: number;
  dayOfWeek: number;
  venue: string;
  status: string;
  startTime: string;
  endTime: string;
}

/** Conflicted occurrences as the page's own payload sees them (real data). */
async function readMobileEvents(page: Page): Promise<MobileEvent[]> {
  return page.evaluate(() => {
    const data = (window as any).PAGE_DATA || {};
    const sessions: Record<string, any> = {};
    (data.courses || []).forEach((course: any) => {
      Object.keys(course.sessions || {}).forEach((sid) => {
        sessions[sid] = { code: course.code, ...course.sessions[sid] };
      });
    });
    return (data.conflictEvents || []).map((row: any) => {
      const session = sessions[String(row.event.sessionId)] || {};
      return {
        sessionId: Number(row.event.sessionId),
        weekIndex: Number(row.week),
        code: String(session.code || row.event.code || ''),
        durationSlots: Number(session.durationSlots || 0),
        dayOfWeek: Number(row.event.di),
        venue: String(row.event.venue || session.venue || ''),
        status: String(row.event.status || ''),
        startTime: String(session.startTime || ''),
        endTime: String(session.endTime || ''),
      };
    });
  });
}

function pickEvent(
  events: MobileEvent[],
  predicate: (event: MobileEvent) => boolean,
): MobileEvent | null {
  return events.find(predicate) || null;
}

async function offeredWeekIndexes(page: Page): Promise<number[]> {
  return page.evaluate(() => {
    const sel = document.getElementById('weekSelector') as HTMLSelectElement | null;
    if (!sel) return [];
    return Array.from(sel.options)
      /* The empty-semester placeholder is `value=""`; Number('') is 0, which
         would falsely look like week 0. Only real week values count. */
      .filter((option) => option.value !== '')
      .map((option) => Number(option.value))
      .filter((value) => Number.isInteger(value) && value >= 0);
  });
}

async function selectWeekByIndex(page: Page, week: number): Promise<boolean> {
  const offered = await offeredWeekIndexes(page);
  if (!offered.includes(week)) return false;
  await page.selectOption('#weekSelector', String(week));
  await page.waitForFunction(
    (value) => (document.getElementById('weekSelector') as HTMLSelectElement | null)?.value === value,
    String(week),
  );
  await page.waitForFunction(() => document.querySelectorAll('#tableBody .cell-content').length > 0);
  await page.waitForTimeout(400);
  return true;
}

async function currentWeekIndex(page: Page): Promise<number> {
  return page.evaluate(() =>
    parseInt((document.getElementById('weekSelector') as HTMLSelectElement | null)?.value ?? '', 10));
}

async function visibleDayIndexes(page: Page): Promise<number[]> {
  return page.evaluate(() => Array.from(
    document.querySelectorAll('[data-arrangement-mobile-days] button[data-day-index]'),
  ).map((button) => Number(button.getAttribute('data-day-index'))));
}

async function expectedEligibleDays(page: Page, weekIndex: number): Promise<number[]> {
  return page.evaluate((wi) => {
    const data = (window as any).weekData;
    const week = data && data[wi];
    if (!week || !Array.isArray(week.days)) return [];
    const out: number[] = [];
    week.days.forEach((day: any, index: number) => {
      if (day.sunday || day.abbr === 'Sun' || day.holiday) return;
      if ((window as any).isSlotTooSoon(data, wi, index)) return;
      out.push(index);
    });
    return out;
  }, weekIndex);
}

async function pressedDay(page: Page): Promise<number | null> {
  return page.evaluate(() => {
    const pressed = document.querySelector(
      '[data-arrangement-mobile-days] button[aria-pressed="true"]',
    );
    return pressed ? Number(pressed.getAttribute('data-day-index')) : null;
  });
}

async function clickDay(page: Page, day: number): Promise<void> {
  const button = page.locator(`[data-arrangement-mobile-days] button[data-day-index="${day}"]`);
  await expect(button).toBeVisible();
  await button.click();
  await expect(button).toHaveAttribute('aria-pressed', 'true');
}

async function dayHeading(page: Page): Promise<string> {
  return (await page.locator('[data-arrangement-mobile-day-heading]').textContent() || '').trim();
}

interface CellRun { day: number; hour: number }

/**
 * The first whole-span run of canonical `cell-available` cells whose day has a
 * visible mobile day button. This reads the page's OWN resolved cells (the
 * adapter reads the same cells); it never re-implements availability.
 */
async function findCanonicalRun(page: Page, span: number): Promise<CellRun | null> {
  return page.evaluate((spanSlots) => {
    const available: Record<string, boolean> = {};
    document.querySelectorAll('#tableBody td[data-day][data-hour] .cell-content').forEach((cell) => {
      const td = cell.closest('td');
      if (!td) return;
      const day = Number(td.getAttribute('data-day'));
      const hour = Number(td.getAttribute('data-hour'));
      if (cell.classList.contains('cell-available')) available[`${day}:${hour}`] = true;
    });
    for (let day = 0; day < 6; day += 1) {
      if (!document.querySelector(`[data-arrangement-mobile-days] button[data-day-index="${day}"]`)) continue;
      for (let hour = 0; hour + spanSlots <= 22; hour += 1) {
        let complete = true;
        for (let k = 0; k < spanSlots; k += 1) {
          if (!available[`${day}:${hour + k}`]) { complete = false; break; }
        }
        if (complete) return { day, hour };
      }
    }
    return null;
  }, span);
}

/** A green start whose required span crosses a blocked half-hour. */
async function findGapStart(page: Page, span: number): Promise<CellRun | null> {
  return page.evaluate((spanSlots) => {
    const available: Record<string, boolean> = {};
    document.querySelectorAll('#tableBody td[data-day][data-hour] .cell-content').forEach((cell) => {
      const td = cell.closest('td');
      if (!td) return;
      const day = Number(td.getAttribute('data-day'));
      const hour = Number(td.getAttribute('data-hour'));
      if (cell.classList.contains('cell-available')) available[`${day}:${hour}`] = true;
    });
    for (let day = 0; day < 6; day += 1) {
      if (!document.querySelector(`[data-arrangement-mobile-days] button[data-day-index="${day}"]`)) continue;
      for (let hour = 0; hour + spanSlots <= 22; hour += 1) {
        if (!available[`${day}:${hour}`]) continue;
        for (let k = 1; k < spanSlots; k += 1) {
          if (!available[`${day}:${hour + k}`]) return { day, hour };
        }
      }
    }
    return null;
  }, span);
}

/** A green start whose required span runs past the 22-row day end. */
async function findDayEndStart(page: Page, span: number): Promise<CellRun | null> {
  return page.evaluate((spanSlots) => {
    const available: Record<string, boolean> = {};
    document.querySelectorAll('#tableBody td[data-day][data-hour] .cell-content').forEach((cell) => {
      const td = cell.closest('td');
      if (!td) return;
      const day = Number(td.getAttribute('data-day'));
      const hour = Number(td.getAttribute('data-hour'));
      if (cell.classList.contains('cell-available')) available[`${day}:${hour}`] = true;
    });
    for (let day = 0; day < 6; day += 1) {
      if (!document.querySelector(`[data-arrangement-mobile-days] button[data-day-index="${day}"]`)) continue;
      for (let hour = 22 - spanSlots + 1; hour < 22; hour += 1) {
        if (available[`${day}:${hour}`]) return { day, hour };
      }
    }
    return null;
  }, span);
}

async function findFirstRunAcrossWeeks(
  page: Page,
  span: number,
): Promise<(CellRun & { week: number }) | null> {
  const weeks = await offeredWeekIndexes(page);
  for (const week of weeks) {
    const moved = await selectWeekByIndex(page, week);
    if (!moved) continue;
    const run = await findCanonicalRun(page, span);
    if (run) return { week, ...run };
  }
  return null;
}

async function tapHourRow(page: Page, day: number, hour: number): Promise<void> {
  await clickDay(page, day);
  const row = page.locator(
    `[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${hour}"]`,
  );
  await expect(row).toHaveAttribute('data-mobile-state', 'available');
  await row.click();
}

async function waitForSavedSpan(page: Page, span: number): Promise<void> {
  const block = page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="span"]');
  await expect(block).toHaveCount(1, { timeout: 20_000 });
  await expect(block).toHaveAttribute('data-mobile-span', String(span));
}

async function expectNoSelection(page: Page): Promise<void> {
  await expect(
    page.locator('[data-arrangement-mobile-summary-host] .arrangement-mobile-summary-title').first(),
  ).toHaveText('Select a time');
  await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);
}

function summaryTitle(page: Page) {
  return page.locator('[data-arrangement-mobile-summary-host] .arrangement-mobile-summary-title').first();
}

function summaryMeta(page: Page) {
  return page.locator('[data-arrangement-mobile-summary-host] .arrangement-mobile-summary-meta').first();
}

async function venueLabel(page: Page): Promise<string> {
  return ((await page.locator('#buildingSelectorDropdown .venue-dd-label').textContent()) || '').trim();
}

function venueCodeOf(label: string): string {
  return label.split(/\s+/)[0].trim();
}

interface SpanGeometry {
  start24: string;
  end24: string;
  start12: string;
  end12: string;
}

/** Expected 12-hour span text from the page's own axis + shared formatter. */
async function expectedSpanGeometry(page: Page, hour: number, span: number): Promise<SpanGeometry> {
  return page.evaluate(({ start, size }) => {
    const endExclusive = start + size;
    // `hours` is the page's real axis (top-level const); `add30min`/`to12h`
    // are the page's own shared formatters.
    const end = endExclusive < hours.length ? hours[endExclusive] : (window as any).add30min(hours[endExclusive - 1]);
    return {
      start24: hours[start],
      end24: end,
      start12: (window as any).to12h(hours[start]),
      end12: (window as any).to12h(end),
    };
  }, { start: hour, size: span });
}

async function openInfo(page: Page): Promise<void> {
  await expect(page.locator('#arrangementMobileInfo')).toBeVisible({ timeout: 10_000 });
}

async function infoOpen(page: Page): Promise<boolean> {
  return page.evaluate(() => {
    const dialog = document.getElementById('arrangementMobileInfo') as HTMLDialogElement | null;
    return !!(dialog && dialog.open);
  });
}

/** The shared floating tip is a single always-mounted div; it must stay hidden. */
async function floatingTooltipState(page: Page): Promise<Array<{ shown: boolean; text: string }>> {
  return page.evaluate(() => Array.from(document.querySelectorAll('.data-tip-tooltip')).map((node) => {
    const element = node as HTMLElement;
    const style = getComputedStyle(element);
    return {
      shown: style.visibility !== 'hidden' && style.opacity !== '0' && element.offsetParent !== null,
      text: (element.textContent || '').trim(),
    };
  }));
}

/** All fact tables currently in the sheet, each as label -> value. */
async function infoFacts(page: Page): Promise<Array<Record<string, string>>> {
  return page.evaluate(() => {
    return Array.from(
      document.querySelectorAll('[data-arrangement-mobile-info-body] .arrangement-mobile-info-facts'),
    ).map((list) => {
      const facts: Record<string, string> = {};
      list.querySelectorAll('li').forEach((item) => {
        const label = (item.querySelector('.arrangement-mobile-info-fact-label')?.textContent || '').trim();
        const value = (item.querySelector('.arrangement-mobile-info-fact-value')?.textContent || '').trim();
        if (label) facts[label] = value;
      });
      return facts;
    });
  });
}

async function canonicalCellTip(page: Page, day: number, hour: number): Promise<string> {
  return page.evaluate(({ d, h }) => {
    const cell = document.querySelector(`#tableBody td[data-day="${d}"][data-hour="${h}"] .cell-content`);
    return cell ? (cell.getAttribute('data-tip') || '').trim() : '';
  }, { d: day, h: hour });
}

async function rowStateText(page: Page, hour: number): Promise<string> {
  return (
    (await page.locator(
      `[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${hour}"] .arrangement-mobile-row-state`,
    ).textContent()) || ''
  ).trim();
}

interface BlockedRow { week: number; day: number; hour: number; state: string; labelText: string }

/** The first conflict/pending/reserved mobile row anywhere the picker offers. */
async function findBlockedRow(page: Page): Promise<BlockedRow | null> {
  const weeks = await offeredWeekIndexes(page);
  for (const week of weeks) {
    const moved = await selectWeekByIndex(page, week);
    if (!moved) continue;
    const days = await visibleDayIndexes(page);
    for (const day of days) {
      await clickDay(page, day);
      const row = await page.evaluate(() => {
        const rows = Array.from(document.querySelectorAll(
          '[data-arrangement-mobile-timeline] [data-mobile-row="hour"]',
        ));
        const blocked = rows.map((item) => ({
          hour: Number(item.getAttribute('data-mobile-hour')),
          state: String(item.getAttribute('data-mobile-state') || ''),
          labelText: (item.querySelector('.arrangement-mobile-row-state')?.textContent || '').trim(),
        })).filter((item) => item.state === 'conflict' || item.state === 'pending' || item.state === 'reserved');
        // Prefer a canonical label ("Your Conflict", "Pending", …); a
        // holder-less blocked cell reads the neutral "Unavailable" but is
        // still a legal fallback for the state/date/time/venue receipt.
        return blocked.find((item) => item.labelText && item.labelText !== 'Unavailable') || blocked[0] || null;
      });
      if (row) return { week, day, hour: row.hour, state: row.state, labelText: row.labelText };
    }
  }
  return null;
}

async function selectFirstRun(page: Page, span: number): Promise<(CellRun & { week: number }) | null> {
  const run = await findFirstRunAcrossWeeks(page, span);
  if (!run) return null;
  await tapHourRow(page, run.day, run.hour);
  await waitForSavedSpan(page, span);
  return run;
}

/**
 * Select the exact conflicted occurrence the URL pinned, through the real
 * original-slot picker. This is user-level and important: the arrival auto-pin
 * may pick another occurrence of the same subject, and the page flexes its
 * selection budget to the COMMITTED occurrence's duration. Committing this
 * occurrence first makes the budget and the URL duration agree, so the
 * one-tap whole-span receipt is deterministic instead of data-luck.
 */
async function selectOriginalEventSlot(page: Page, event: MobileEvent): Promise<void> {
  const details = page.locator('[data-arrangement-mobile-context]');
  const isOpen = await details.evaluate((node) => (node as unknown as HTMLDetailsElement).open);
  if (!isOpen) await page.locator('[data-arrangement-mobile-context-summary]').click();

  const date = await page.evaluate(({ week, day }) => {
    const data = (window as any).weekData;
    return data && data[week] && data[week].days[day] ? String(data[week].days[day].date) : '';
  }, { week: event.weekIndex, day: event.dayOfWeek });

  await page.locator('#slotTrigger').click();
  await expect(page.locator('#slotPanel')).toHaveClass(/open/);
  const index = await page.evaluate(({ week, start, endTime, venueCode, dateText }) => {
    const parts = String(dateText).split(' ');
    // The picker prints "Dec 24, 2026" (month first) from "24 Dec 2026".
    const labelDate = parts.length === 3 ? `${parts[1]} ${parts[0]}, ${parts[2]}` : String(dateText);
    const start12 = (window as any).to12h(start);
    const end12 = (window as any).to12h(endTime);
    const items = Array.from(document.querySelectorAll('.slot-dd-item'));
    const exact = items.findIndex((item) => {
      const text = (item.textContent || '').replace(/\s+/g, ' ');
      return new RegExp(`Week\\s+${week + 1}\\b`).test(text)
        && text.indexOf(labelDate) !== -1
        && text.indexOf(start12) !== -1
        && text.indexOf(end12) !== -1
        && (!venueCode || text.indexOf(venueCode) !== -1);
    });
    if (exact >= 0) return exact;
    return items.findIndex((item) => {
      const text = (item.textContent || '').replace(/\s+/g, ' ');
      return new RegExp(`Week\\s+${week + 1}\\b`).test(text)
        && text.indexOf(start12) !== -1
        && text.indexOf(end12) !== -1
        && (!venueCode || text.indexOf(venueCode) !== -1);
    });
  }, {
    week: event.weekIndex,
    start: event.startTime,
    endTime: event.endTime,
    venueCode: event.venue,
    dateText: date,
  });

  expect(index, `the original-slot picker offers the pinned occurrence (week ${event.weekIndex + 1}, ${event.startTime})`)
    .toBeGreaterThanOrEqual(0);
  await page.locator('.slot-dd-item').nth(index).click();
  await expect(page.locator('.slot-dd-item').nth(index)).toHaveClass(/selected/);
  // The item's own click handler closes the panel (`closeSlotPanel()`).
  await expect(page.locator('#slotPanel')).not.toHaveClass(/open/);
  // Committing an occurrence rebuilds the canonical grid and queues the mobile
  // refresh; let the coalesced turn settle before scanning the timeline.
  await page.waitForTimeout(500);
}

/* ─────────────────────────────────────────────────────────────────────────
 * T21a — calendar / context / navigation (MA-02a–c, MA-03a–f, MA-14b)
 * ───────────────────────────────────────────────────────────────────────── */
test.describe('T21a — mobile calendar, context and navigation', () => {
  test.beforeEach(async ({ page }) => { await login(page); });

  test('only real eligible dates are offered; Sundays, holidays and too-soon dates are absent', async ({ page }) => {
    await gotoArrangement(page);
    const weeks = await offeredWeekIndexes(page);
    test.skip(weeks.length === 0, 'the testing semester offers no bookable week (empty state); the calendar matrix cannot be asserted');

    for (const week of weeks) {
      expect(await selectWeekByIndex(page, week), `week ${week} is offered and selectable`).toBe(true);
      const visible = await visibleDayIndexes(page);
      const expected = await expectedEligibleDays(page, week);
      expect(visible, `week ${week}: only dates that pass Sunday/holiday/too-soon are shown`).toEqual(expected);
      expect(visible, 'Sunday (index 6) is never offered as a replacement day').not.toContain(6);
      expect(await pressedDay(page), `week ${week}: a viewed day is resolved`).not.toBeNull();
    }

    // The seeded holiday week must actually be exercised when the term still
    // offers it (data-dependent, but never silently skipped as a pass).
    const holidayWeek = await page.evaluate(() => {
      const data = (window as any).weekData || [];
      for (let w = 0; w < data.length; w += 1) {
        const holidayDays: number[] = [];
        (data[w].days || []).forEach((day: any, index: number) => {
          if (day.holiday) holidayDays.push(index);
        });
        if (holidayDays.length) return { week: w, days: holidayDays };
      }
      return null;
    });
    if (holidayWeek) {
      const offered = await offeredWeekIndexes(page);
      if (offered.includes(holidayWeek.week)) {
        expect(await selectWeekByIndex(page, holidayWeek.week)).toBe(true);
        const visible = await visibleDayIndexes(page);
        for (const holidayDay of holidayWeek.days) {
          expect(visible, `holiday day ${holidayDay} is hidden from replacement navigation`).not.toContain(holidayDay);
        }
      } else {
        test.info().annotations.push({
          type: 'data-gap',
          description: `holiday week ${holidayWeek.week + 1} is not bookable in this run; holiday-hiding is still covered by the per-week helper comparison`,
        });
      }
    }
  });

  test('the exact notice boundary is the first offered date and the prior day is hidden', async ({ page }) => {
    await gotoArrangement(page);
    const boundary = await page.evaluate(() => {
      const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
      const label = (date: Date) => `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
      const cutoff = (window as any).leadTimeCutoff((window as any).weekData);
      const previous = new Date(cutoff);
      previous.setDate(previous.getDate() - 1);
      const find = (wanted: string) => {
        const data = (window as any).weekData || [];
        for (let w = 0; w < data.length; w += 1) {
          for (let d = 0; d < (data[w].days || []).length; d += 1) {
            if (data[w].days[d].date === wanted) return { week: w, day: d, date: wanted };
          }
        }
        return null;
      };
      return {
        boundary: find(label(cutoff)),
        previous: find(label(previous)),
        offered: Array.from((document.getElementById('weekSelector') as HTMLSelectElement).options)
          .map((option) => Number(option.value)),
      };
    });

    test.skip(
      boundary.boundary === null,
      'the 3-working-day cutoff falls outside the seeded semester, so no in-term boundary date exists',
    );

    const current = boundary.boundary!;
    expect(boundary.offered, 'the boundary week must be bookable').toContain(current.week);
    expect(await selectWeekByIndex(page, current.week)).toBe(true);
    const visible = await visibleDayIndexes(page);
    expect(visible, 'the boundary date itself is offered').toContain(current.day);

    const expected = await expectedEligibleDays(page, current.week);
    expect(expected[0], 'the boundary date is the first eligible date of its week').toBe(current.day);

    if (boundary.previous) {
      if (boundary.previous.week === current.week) {
        expect(visible, 'the calendar day before the boundary is too-soon and hidden').not.toContain(boundary.previous.day);
      } else {
        expect(
          boundary.offered,
          'a whole week before the boundary has no eligible date and is not offered',
        ).not.toContain(boundary.previous.week);
      }
    }
  });

  test('an eligible date with no free times remains visible and shows its blocked rows', async ({ page }) => {
    const events = await (async () => {
      await gotoArrangement(page);
      return readMobileEvents(page);
    })();
    const event = pickEvent(events, (item) => item.durationSlots > 0);
    test.skip(event === null, 'no conflicted occurrence exists for staff 5425 in the seeded testing DB');

    await gotoArrival(page, event!);
    const week = await currentWeekIndex(page);
    expect(Number.isInteger(week), 'a bookable week resolved').toBe(true);
    const room = venueCodeOf(await venueLabel(page));
    expect(room, 'the pinned venue resolved').not.toBe('');

    // `week` is the page's 0-based weekData index; SQL week_number is 1-based.
    reservedRooms.push(reserveRoomWeek(room, week + 1));
    await page.reload({ waitUntil: 'domcontentloaded' });
    await waitForMobile(page);
    await page.waitForFunction(() => document.querySelectorAll('#tableBody .cell-content').length > 0);

    // The week and its eligible days are date-based, never occupancy-based.
    expect(await offeredWeekIndexes(page), 'the fully blocked week stays offered').toContain(week);
    expect(await selectWeekByIndex(page, week)).toBe(true);
    const expected = await expectedEligibleDays(page, week);
    expect(await visibleDayIndexes(page), 'eligible dates stay visible even with no free time').toEqual(expected);

    const day = expected[0];
    await clickDay(page, day);
    const rows = page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="hour"]');
    await expect(rows).toHaveCount(ROW_COUNT);
    const states = await rows.evaluateAll((nodes) => nodes.map((node) => ({
      state: node.getAttribute('data-mobile-state') || '',
      text: (node.querySelector('.arrangement-mobile-row-state')?.textContent || '').trim(),
    })));
    expect(states.every((row) => row.state !== 'available'), 'no half-hour is offered as free').toBe(true);
    expect(states.every((row) => row.text.length > 0), 'every blocked row shows real canonical state text').toBe(true);
    expect(
      states.some((row) => row.state === 'conflict' || row.state === 'pending' || row.state === 'reserved'),
      'the blocked times are visibly blocked',
    ).toBe(true);
  });

  test('without a subject the guidance is shown and a tap cannot create a replacement', async ({ page }) => {
    await gotoArrangement(page);
    await expect(page.locator('[data-arrangement-mobile-context-summary]')).toHaveText('Select a course and original slot');
    await expectNoSelection(page);

    // The original subject control is reachable through the compact context.
    await page.locator('[data-arrangement-mobile-context-summary]').click();
    await expect(page.locator('#subjectSelector')).toBeVisible();
    await expect(page.locator('[data-arrangement-mobile-context-facts]')).toBeAttached();

    const green = await page.evaluate(() => {
      const rows = Array.from(document.querySelectorAll(
        '[data-arrangement-mobile-timeline] [data-mobile-row="hour"]',
      ));
      const row = rows.find((item) => item.getAttribute('data-mobile-state') === 'available');
      return row ? Number(row.getAttribute('data-mobile-hour')) : null;
    });
    test.skip(green === null, 'no free mobile row exists in the first bookable week to exercise the no-subject gate');

    await page.locator(
      `[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${green}"]`,
    ).click();
    await expect(page.locator('#toastBar')).toHaveClass(/visible/, { timeout: 10_000 });
    await expect(page.locator('#toastBar .toast-message'))
      .toHaveText('Select a course first to trigger the timeslots selector');
    await expectNoSelection(page);
    await expect(page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="span"]')).toHaveCount(0);
  });

  test('a holiday or past original slot stays selectable while its date stays hidden', async ({ page }) => {
    await gotoArrangement(page);
    const events = await readMobileEvents(page);
    test.skip(events.length === 0, 'staff 5425 has no conflicted occurrence in the seeded testing DB');

    const holiday = pickEvent(events, (item) => item.status === 'conflict' && item.weekIndex >= 0);
    const any = pickEvent(events, (item) => item.weekIndex >= 0);
    const target = holiday || any;
    test.skip(target === null, 'no seeded conflicted occurrence is available for the original-slot case');

    const past = insertPastException(target!.sessionId, 1);
    pastExceptions.push(past);
    await gotoArrival(page, target!);

    // Both the native holiday occurrence and the row just created in week 1
    // are in the picker: the original-slot list is never date-filtered.
    await page.locator('[data-arrangement-mobile-context-summary]').click();
    await page.locator('#slotTrigger').click();
    await expect(page.locator('#slotPanel')).toHaveClass(/open/);
    const pastItems = page.locator('.slot-dd-item').filter({ hasText: /Week 1\b/ });
    expect(await pastItems.count(), 'the past occurrence is offered as an original slot').toBeGreaterThan(0);

    await pastItems.first().click();
    await expect(page.locator('.slot-dd-item.selected')).toContainText(/Week 1\b/);
    await expect(page.locator('#slotTriggerText')).toContainText(/Week 1\b/);

    // Week 1 (past) is hidden from replacement-day navigation entirely.
    expect(await offeredWeekIndexes(page), 'a past week is not a replacement destination').not.toContain(0);

    if (holiday) {
      await page.locator('#slotTrigger').click();
      const holidayItem = page.locator('.slot-dd-item').filter({ hasText: new RegExp(`Week ${holiday.weekIndex + 1}\\b`) });
      expect(await holidayItem.count(), 'the holiday occurrence is offered as an original slot').toBeGreaterThan(0);
      await holidayItem.first().click();
      await expect(page.locator('.slot-dd-item.selected')).toContainText(new RegExp(`Week ${holiday.weekIndex + 1}\\b`));

      const offered = await offeredWeekIndexes(page);
      if (offered.includes(holiday.weekIndex)) {
        await selectWeekByIndex(page, holiday.weekIndex);
        const visible = await visibleDayIndexes(page);
        expect(visible, 'the holiday itself is still hidden as a replacement date').not.toContain(holiday.dayOfWeek);
        expect(await pressedDay(page), 'the holiday original slot does not force an ineligible day').not.toBe(holiday.dayOfWeek);
      } else {
        test.info().annotations.push({
          type: 'data-gap',
          description: `the original holiday occurrence is in week ${holiday.weekIndex + 1}, which this run does not offer as a replacement week`,
        });
      }
    }
  });

  test('week changes retain an eligible weekday or fall back to the first eligible day, without selecting', async ({ page }) => {
    await gotoArrangement(page);
    const weeks = await offeredWeekIndexes(page);
    test.skip(weeks.length === 0, 'the testing semester offers no bookable week for a retention/fallback check');
    const firstWeek = weeks[0];
    await selectWeekByIndex(page, firstWeek);

    const firstEligible = await expectedEligibleDays(page, firstWeek);
    test.skip(firstEligible.length < 2, 'the first bookable week needs two eligible days for a retention check');
    const weekday = firstEligible[firstEligible.length - 1];
    await clickDay(page, weekday);
    await expectNoSelection(page);

    // Retention: find a later week where the same weekday is eligible.
    let retained = false;
    for (const week of weeks) {
      if (week <= firstWeek) continue;
      const expected = await expectedEligibleDays(page, week);
      if (!expected.includes(weekday)) continue;
      expect(await selectWeekByIndex(page, week)).toBe(true);
      expect(await pressedDay(page), `week ${week} keeps the viewed weekday ${weekday}`).toBe(weekday);
      retained = true;
      break;
    }
    if (!retained) {
      test.info().annotations.push({
        type: 'data-gap',
        description: `no later bookable week offers weekday ${weekday}; the fallback assertion still proves the rule`,
      });
    }

    // Fallback: find an offered week where the weekday is ineligible.
    let fallback = false;
    for (const week of weeks) {
      const expected = await expectedEligibleDays(page, week);
      if (expected.length === 0 || expected.includes(weekday)) continue;
      expect(await selectWeekByIndex(page, week)).toBe(true);
      expect(await pressedDay(page), `week ${week} falls back to its first eligible day`).toBe(expected[0]);
      fallback = true;
      break;
    }
    test.skip(!fallback, 'no offered week both excludes the viewed weekday and has another eligible day');

    await expectNoSelection(page);
  });

  test('Earliest bookable reveals the first eligible day of its week and creates no selection', async ({ page }) => {
    await gotoArrangement(page);
    const earliest = await page.evaluate(() => (window as any).firstBookableDay((window as any).weekData));
    test.skip(earliest === null, 'the seeded semester has no bookable day under the real clock');

    // Move away from the earliest week first so the reveal is observable.
    const weeks = await offeredWeekIndexes(page);
    const lastWeek = weeks[weeks.length - 1];
    test.skip(lastWeek === earliest.week, 'only one bookable week is offered; the reveal cannot be distinguished');
    test.skip(
      await page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches),
      'reduced motion intentionally skips the flash animation; the day/week reveal assertions alone would not prove MA-14b',
    );
    await selectWeekByIndex(page, lastWeek);
    const lastEligible = await expectedEligibleDays(page, lastWeek);
    if (lastEligible.length) await clickDay(page, lastEligible[lastEligible.length - 1]);

    // Observe the shared flash family (Earliest uses the page's `flash` tone).
    await page.evaluate(() => {
      const host = document.querySelector('[data-arrangement-mobile-days]');
      if (!host) return;
      (window as any).__mobileReveals = [];
      new MutationObserver((records) => {
        records.forEach((record) => {
          const target = record.target as HTMLElement;
          if (!target.classList) return;
          if (target.classList.contains('bookable-flash') || target.classList.contains('attention-pulse')) {
            (window as any).__mobileReveals.push(Number(target.getAttribute('data-day-index')));
          }
        });
      }).observe(host, { subtree: true, attributes: true, attributeFilter: ['class'] });
    });

    await page.locator('#earliestBtn').click();
    await expect.poll(async () => page.evaluate(() => (window as any).__mobileReveals || []), {
      timeout: 10_000,
    }).toContain(earliest.day);

    expect(await currentWeekIndex(page)).toBe(earliest.week);
    expect(await pressedDay(page)).toBe(earliest.day);
    expect(await dayHeading(page)).toContain(await page.evaluate((day) => {
      const data = (window as any).weekData;
      const week = (window as any).weekNav.currentWeek;
      return data[week].days[day].date;
    }, earliest.day));
    await expectNoSelection(page);
  });

  test('the empty semester shows the exact message, hides the timeline and disables date/Submit controls', async ({ page }) => {
    await gotoArrangement(page);
    const hadBookable = await page.evaluate(() => (window as any).firstBookableDay((window as any).weekData));
    test.skip(hadBookable === null, 'the semester already has no eligible date; the fixture could not distinguish the empty state');

    insertEmptySemesterHolidays();
    await page.reload({ waitUntil: 'domcontentloaded' });
    await waitForMobile(page);

    const empty = page.locator('[data-arrangement-mobile-empty]');
    await expect(empty).toBeVisible();
    await expect(empty).toHaveText('No bookable dates remaining this semester.');
    await expect(page.locator('[data-arrangement-mobile-timeline]')).toBeHidden();
    await expect(page.locator('[data-arrangement-mobile-days] button')).toHaveCount(0);
    await expect(page.locator('#weekSelector')).toBeDisabled();
    await expect(page.locator('#earliestBtn')).toBeDisabled();
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    await expect(page.locator('.week-arrow[aria-label="Previous week"]')).toBeDisabled();
    await expect(page.locator('.week-arrow[aria-label="Next week"]')).toBeDisabled();
    await expect(page.locator('[data-arrangement-mobile-context-summary]')).toHaveText('Select a course and original slot');

    // Context/tools/saved-inspection access survives the empty semester.
    await expect(page.locator('[data-arrangement-mobile-legend]')).toBeVisible();
    await expect(page.locator('[data-arrangement-mobile-legend]')).toBeEnabled();
    await expect(page.locator('[data-arrangement-mobile-context-summary]')).toBeVisible();
  });
});

/* ─────────────────────────────────────────────────────────────────────────
 * T21b — canonical timeline / whole-class selection / saved summary
 * (MA-04a–d, MA-05a–e, MA-06a–c)
 * ───────────────────────────────────────────────────────────────────────── */
test.describe('T21b — mobile timeline, selection and summary', () => {
  test.beforeEach(async ({ page }) => { await login(page); });

  async function arrivalFor(
    page: Page,
    durationSlots: number,
  ): Promise<MobileEvent | null> {
    await gotoArrangement(page);
    const events = await readMobileEvents(page);
    return pickEvent(events, (item) => item.durationSlots === durationSlots && item.weekIndex >= 0);
  }

  async function prepareSelection(page: Page, durationSlots: number): Promise<MobileEvent> {
    const event = await arrivalFor(page, durationSlots);
    test.skip(
      event === null,
      `no conflicted ${durationSlots}-half-hour occurrence exists for staff 5425 in the seeded testing DB`,
    );
    await gotoArrival(page, event!);
    // A pinned arrival must not pre-save a replacement block; if it does, the
    // one-occurrence-length rule (and this receipt) cannot be exercised here.
    await expectNoSelection(page);
    await selectOriginalEventSlot(page, event!);
    return event!;
  }

  test('a 2-hour class becomes one continuous four-half-hour block from one mobile row tap', async ({ page }) => {
    await prepareSelection(page, 4);
    const run = await selectFirstRun(page, 4);
    test.skip(run === null, 'the pinned venue has no contiguous 4-half-hour free run in any offered week');

    const geometry = await expectedSpanGeometry(page, run!.hour, 4);
    const label = await venueLabel(page);
    const heading = await dayHeading(page);
    const block = page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="span"]');

    await expect(block).toHaveAttribute('data-mobile-day', String(run!.day));
    await expect(block).toHaveAttribute('data-mobile-hour', String(run!.hour));
    await expect(block).toHaveAttribute('data-mobile-span', '4');
    await expect(block).toHaveAttribute('data-mobile-state', 'selected');
    await expect(block).toHaveAttribute('aria-label', `Selected ${geometry.start12} ~ ${geometry.end12}, ${label}`);
    await expect(block.locator('.arrangement-mobile-span-time'))
      .toHaveText(`${geometry.start12} ~ ${geometry.end12}`);
    await expect(block.locator('.arrangement-mobile-span-venue')).toHaveText(label);

    // No duplicated covered half-hours: the span replaces exactly four rows.
    for (let hour = run!.hour; hour < run!.hour + 4; hour += 1) {
      await expect(
        page.locator(`[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${hour}"]`),
      ).toHaveCount(0);
    }
    await expect(page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="hour"]'))
      .toHaveCount(ROW_COUNT - 4);

    // Readable 24-hour axis rows and current CRS 12-hour summary formats.
    expect(heading).toMatch(/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{1,2} [A-Z][a-z]{2} \d{4}$/);
    const sampleRow = page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="hour"]').first();
    expect(((await sampleRow.locator('.arrangement-mobile-row-time').textContent()) || '').trim())
      .toMatch(/^\d{2}:\d{2}\u2013\d{2}:\d{2}$/);
    expect(`${geometry.start12} ~ ${geometry.end12}`)
      .toMatch(/^\d{1,2}:\d{2} [AP]M ~ \d{1,2}:\d{2} [AP]M$/);

    const code = venueCodeOf(label);
    await expect(summaryTitle(page)).toHaveText(`${heading} \u00b7 ${geometry.start12} ~ ${geometry.end12}`);
    await expect(summaryMeta(page)).toHaveText(`${code} \u00b7 2 hours`);
    await expect(page.locator('#submitRequestBtn')).toBeEnabled({ timeout: 10_000 });
  });

  test('a 1-hour class becomes one continuous two-half-hour block with singular duration copy', async ({ page }) => {
    await prepareSelection(page, 2);
    const run = await selectFirstRun(page, 2);
    test.skip(run === null, 'the pinned venue has no contiguous 2-half-hour free run in any offered week');

    const geometry = await expectedSpanGeometry(page, run!.hour, 2);
    const label = await venueLabel(page);
    const heading = await dayHeading(page);
    const block = page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="span"]');

    await expect(block).toHaveAttribute('data-mobile-span', '2');
    await expect(block.locator('.arrangement-mobile-span-time'))
      .toHaveText(`${geometry.start12} ~ ${geometry.end12}`);
    await expect(page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="hour"]'))
      .toHaveCount(ROW_COUNT - 2);
    for (let hour = run!.hour; hour < run!.hour + 2; hour += 1) {
      await expect(
        page.locator(`[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${hour}"]`),
      ).toHaveCount(0);
    }

    const code = venueCodeOf(label);
    await expect(summaryTitle(page)).toHaveText(`${heading} \u00b7 ${geometry.start12} ~ ${geometry.end12}`);
    await expect(summaryMeta(page)).toHaveText(`${code} \u00b7 1 hour`);
  });

  test('a short gap or the day end refuses the span without drawing an optimistic block', async ({ page }) => {
    await prepareSelection(page, 2);
    const weeks = await offeredWeekIndexes(page);
    let gap: (CellRun & { week: number }) | null = null;
    let dayEnd: (CellRun & { week: number }) | null = null;
    for (const week of weeks) {
      const moved = await selectWeekByIndex(page, week);
      if (!moved) continue;
      if (!gap) {
        const found = await findGapStart(page, 2);
        if (found) gap = { week, ...found };
      }
      if (!dayEnd) {
        const found = await findDayEndStart(page, 2);
        if (found) dayEnd = { week, ...found };
      }
      if (gap && dayEnd) break;
    }
    test.skip(gap === null && dayEnd === null, 'no green start crosses a blocked half-hour or the day end in this seed');

    for (const [kind, candidate] of [['gap', gap], ['day end', dayEnd]] as const) {
      if (!candidate) continue;
      await selectWeekByIndex(page, candidate.week);
      await tapHourRow(page, candidate.day, candidate.hour);
      await expect(page.locator('#toastBar')).toHaveClass(/visible/, { timeout: 10_000 });
      const message = await page.locator('#toastBar .toast-message').innerText();
      expect(message, `${kind} refusal uses the existing full-span feedback`).toMatch(/Won't Fit/);
      await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);
      await expect(summaryTitle(page)).toHaveText('Select a time');
      // Let the shared five-second toast retire before the next candidate so
      // the fixed bar cannot cover the following row.
      await expect(page.locator('#toastBar')).not.toHaveClass(/visible/, { timeout: 8_000 });
    }
  });

  test('a saved selection anywhere blocks a second block and keeps its identity', async ({ page }) => {
    await prepareSelection(page, 4);
    const run = await selectFirstRun(page, 4);
    test.skip(run === null, 'the pinned venue has no contiguous 4-half-hour free run in any offered week');

    const originalTitle = await summaryTitle(page).textContent();
    const originalMeta = await summaryMeta(page).textContent();

    // Another green row in the same week/day is still refused: no replacement,
    // no clear, no second block.
    const other = await page.evaluate(({ day, hour, span }) => {
      const rows = Array.from(document.querySelectorAll(
        '[data-arrangement-mobile-timeline] [data-mobile-row="hour"]',
      ));
      const inside = (candidate: number) => candidate >= hour && candidate < hour + span;
      for (const row of rows) {
        if (row.getAttribute('data-mobile-state') !== 'available') continue;
        const candidate = Number(row.getAttribute('data-mobile-hour'));
        if (Number(row.getAttribute('data-mobile-day')) === day && inside(candidate)) continue;
        return candidate;
      }
      return null;
    }, { day: run!.day, hour: run!.hour, span: 4 });

    if (other !== null) {
      await page.locator(
        `[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${other}"]`,
      ).click();
      await expect(page.locator('#toastBar')).toHaveClass(/visible/, { timeout: 10_000 });
      await expect(page.locator('#toastBar .toast-message')).toHaveText(
        'You already selected a class-length block. Open its details to remove it before choosing another.',
      );
      await expect(summaryTitle(page)).toHaveText(originalTitle || '');
      await expect(summaryMeta(page)).toHaveText(originalMeta || '');
      await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
    } else {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'the selected day filled the only remaining free row; no second green row was available to tap',
      });
    }
  });

  test('an off-view summary keeps the saved identity and its exact-key removal clears only that entry', async ({ page }) => {
    await prepareSelection(page, 4);
    const run = await selectFirstRun(page, 4);
    test.skip(run === null, 'the pinned venue has no contiguous 4-half-hour free run in any offered week');

    const heading = await dayHeading(page);
    const geometry = await expectedSpanGeometry(page, run!.hour, 4);
    const label = await venueLabel(page);
    const code = venueCodeOf(label);
    const title = `${heading} \u00b7 ${geometry.start12} ~ ${geometry.end12}`;

    await expect(summaryTitle(page)).toHaveText(title);
    await expect(summaryMeta(page)).toHaveText(`${code} \u00b7 2 hours`);

    // Browse a different week: the bar must not hide or replace the entry.
    const otherWeek = (await offeredWeekIndexes(page)).find((week) => week !== run!.week);
    test.skip(otherWeek === undefined, 'only one bookable week is offered; off-view summary cannot be exercised');
    expect(await selectWeekByIndex(page, otherWeek!)).toBe(true);
    await expect(summaryTitle(page)).toHaveText(title);
    await expect(summaryMeta(page)).toHaveText(`${code} \u00b7 2 hours`);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);

    await page.locator('[data-arrangement-mobile-summary-opener]').click();
    await openInfo(page);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Saved selection');
    const facts = await infoFacts(page);
    // The sheet carries the subject once, then the saved entry's own facts.
    expect(facts.some((entry) => entry.Course)).toBe(true);
    const entryFacts = facts.find((entry) => typeof entry.Date === 'string') || {};
    expect(entryFacts.Date, 'the off-view sheet names the saved date').toBe(heading);
    expect(entryFacts.Time).toBe(`${geometry.start12} ~ ${geometry.end12}`);
    expect(entryFacts.Duration).toBe('2 hours');
    expect(entryFacts.Venue).toBe(code);

    // Exact-key removal: one entry, one keyed Remove.
    const remove = page.locator('[data-arrangement-mobile-info-actions] button', { hasText: 'Remove selection' });
    await expect(remove).toHaveCount(1);
    await remove.click();
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    await expect(summaryTitle(page)).toHaveText('Select a time');
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();

    // Back on the saved week/day: no span, no selected canonical cell, all rows.
    expect(await selectWeekByIndex(page, run!.week)).toBe(true);
    await clickDay(page, run!.day);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);
    await expect(page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="hour"]'))
      .toHaveCount(ROW_COUNT);
    const selectedCells = await page.evaluate(({ day, hour, span }) => {
      let count = 0;
      for (let h = hour; h < hour + span; h += 1) {
        const cell = document.querySelector(`#tableBody td[data-day="${day}"][data-hour="${h}"] .cell-content`);
        if (cell && cell.classList.contains('cell-selected')) count += 1;
      }
      return count;
    }, { day: run!.day, hour: run!.hour, span: 4 });
    expect(selectedCells, 'exact-key removal clears the saved cells too').toBe(0);
  });

  test('more than one legacy saved entry would show a count and keyed removals', async () => {
    // The store is closure-private (`selectedSlotsByVenue`); the UI budget
    // (`MAX_SELECTION = occurrence span`) refuses a second block, so no
    // sanctioned user path can create a legacy multi-entry store. T20 already
    // pins `savedEntries`/summary keyed-entry behaviour at helper level.
    test.skip(
      true,
      'not fixture-able without reaching the closure-private selection store or weakening the one-occurrence-length budget; '
        + 'T20 covers keyed legacy entries and T22b/T26 keep the lifecycle evidence',
    );
  });
});

/* ─────────────────────────────────────────────────────────────────────────
 * T21c — information text / legend / Tip (MA-07a–d)
 * ───────────────────────────────────────────────────────────────────────── */
test.describe('T21c — mobile information sheets, legend and Tip', () => {
  test.beforeEach(async ({ page }) => { await login(page); });

  async function pinnedPage(page: Page, durationSlots = 2): Promise<MobileEvent> {
    await gotoArrangement(page);
    const events = await readMobileEvents(page);
    const event = pickEvent(events, (item) => item.durationSlots === durationSlots && item.weekIndex >= 0);
    test.skip(event === null, `no conflicted ${durationSlots}-half-hour occurrence exists for staff 5425`);
    await gotoArrival(page, event!);
    return event!;
  }

  test('a blocked row opens a persistent sheet with its canonical state, date, time, venue and reason', async ({ page }) => {
    await pinnedPage(page);
    const blocked = await findBlockedRow(page);
    test.skip(blocked === null, 'no conflict/pending row exists in the offered weeks for the pinned venue');

    const stateText = blocked!.labelText || await rowStateText(page, blocked!.hour);
    const geometry = await expectedSpanGeometry(page, blocked!.hour, 1);
    const timeText = `${geometry.start12} ~ ${geometry.end12}`;
    const heading = await dayHeading(page);
    const label = await venueLabel(page);
    const tip = await canonicalCellTip(page, blocked!.day, blocked!.hour);

    await page.locator(
      `[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${blocked!.hour}"]`,
    ).click();
    await openInfo(page);
    const fallbackHeading = /^(Conflict|Pending|Reserved)$/;
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText(
      stateText && stateText !== 'Unavailable' ? stateText : fallbackHeading,
    );

    const facts = await infoFacts(page);
    expect(facts).toHaveLength(1);
    if (stateText && stateText !== 'Unavailable') {
      expect(facts[0].State, 'the sheet names the canonical state').toBe(stateText);
    } else {
      expect(
        ['Conflict', 'Pending', 'Reserved'],
        'a holder-less blocked row still names its real family',
      ).toContain(facts[0].State);
    }
    expect(facts[0].Date).toBe(heading);
    expect(facts[0].Time).toBe(timeText);
    expect(facts[0].Venue).toBe(label);

    const reason = page.locator('[data-arrangement-mobile-info-body] .arrangement-mobile-info-reason');
    if (tip) {
      await expect(reason).toHaveCount(1);
      const reasonText = (await reason.textContent() || '').trim();
      expect(reasonText.length, 'the canonical reason is shown, not invented').toBeGreaterThan(0);
      expect(reasonText.split('\n')[0].trim()).toBe(tip.split('\n')[0].trim());
      expect(reasonText.toLowerCase()).not.toContain('clear your selection first');
    } else {
      // Holder-less blocked cells still show state/date/time/venue (MA-04b).
      await expect(reason).toHaveCount(0);
    }

    // Text-only rendering: holder text is never parsed as markup.
    const unsafeNodes = await page.evaluate(() =>
      document.querySelectorAll('#arrangementMobileInfo script, #arrangementMobileInfo img, #arrangementMobileInfo iframe').length);
    expect(unsafeNodes).toBe(0);
    await expect(page.locator('#arrangementMobileInfo [data-mobile-row]')).toHaveCount(0);

    // Information sheets never auto-dismiss (no five-second timer).
    await page.waitForTimeout(5600);
    expect(await infoOpen(page), 'details remain open beyond five seconds').toBe(true);
    await page.locator('[data-arrangement-mobile-info-close]').click();
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
  });

  test('the desktop selection-wipe hint never reaches a blocked-row sheet', async ({ page }) => {
    const event = await pinnedPage(page, 4);
    await selectOriginalEventSlot(page, event);
    const run = await selectFirstRun(page, 4);
    test.skip(run === null, 'the pinned venue has no contiguous 4-half-hour free run to create a selection');

    const wipeTips = await page.evaluate(() =>
      Array.from(document.querySelectorAll('#tableBody .cell-content.cell-available[data-tip]'))
        .filter((cell) => /clear your selection first/i.test(cell.getAttribute('data-tip') || ''))
        .length);
    expect(wipeTips, 'the canonical selection-wipe hint is present in this state').toBeGreaterThan(0);

    const blocked = await findBlockedRow(page);
    test.skip(blocked === null, 'no conflict/pending row exists while the selection is saved');
    const tip = await canonicalCellTip(page, blocked!.day, blocked!.hour);
    await page.locator(
      `[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${blocked!.hour}"]`,
    ).click();
    await openInfo(page);

    const bodyText = await page.locator('[data-arrangement-mobile-info-body]').innerText();
    expect(bodyText.toLowerCase(), 'the selection-wipe instruction is filtered out').not.toContain('clear your selection first');
    const reason = page.locator('[data-arrangement-mobile-info-body] .arrangement-mobile-info-reason');
    if (tip) {
      await expect(reason).toHaveCount(1);
      expect(((await reason.textContent()) || '').split('\n')[0].trim()).toBe(tip.split('\n')[0].trim());
    }
    await page.locator('[data-arrangement-mobile-info-close]').click();
  });

  test('Colours & borders opens four real rows, borderless Pending/Conflict and no ownership-border hint', async ({ page }) => {
    await gotoArrangement(page);
    await page.locator('[data-arrangement-mobile-legend]').click();
    await openInfo(page);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Colours & borders');

    const rows = page.locator('[data-arrangement-mobile-info-body] .arrangement-mobile-info-legend > li');
    await expect(rows).toHaveCount(4);
    const expected = [
      ['Available', 'cell-available'],
      ['Your Current Selection', 'event-selection'],
      ['Pending', 'cell-pending'],
      ['Conflict', 'cell-occupied'],
    ];
    for (let index = 0; index < expected.length; index += 1) {
      await expect(rows.nth(index)).toContainText(expected[index][0]);
      await expect(rows.nth(index).locator('.arrangement-mobile-info-swatch'))
        .toHaveClass(new RegExp(expected[index][1]));
    }

    // Pending and Conflict (and Available) are borderless; only the real blue
    // selection sample keeps its outline.
    for (const index of [0, 2, 3]) {
      expect(
        await rows.nth(index).locator('.arrangement-mobile-info-swatch')
          .evaluate((el) => getComputedStyle(el).borderTopWidth),
        `"${expected[index][0]}" swatch is borderless`,
      ).toBe('0px');
    }
    expect(
      await rows.nth(1).locator('.arrangement-mobile-info-swatch')
        .evaluate((el) => getComputedStyle(el).borderTopWidth),
      'the blue selection sample keeps its existing outline',
    ).not.toBe('0px');

    const sheetText = await page.locator('[data-arrangement-mobile-info-body]').innerText();
    expect(sheetText, 'no obsolete ownership-border hint').not.toMatch(/thick border|3\s*px|ownership ring|border.*ownership/i);
    await expect(page.locator('#arrangementMobileInfo .legend-ownership-hint')).toHaveCount(0);
    await page.locator('[data-arrangement-mobile-info-close]').click();
  });

  test('Tip opens the live state-aware message and suppresses the duplicate floating tooltip', async ({ page }) => {
    await gotoArrangement(page);

    // The relocated trigger keeps its accessible name but loses only data-tip.
    await expect(page.locator('#arrangementMobile #summaryTip')).toHaveCount(1);
    expect(await page.locator('#summaryTip').getAttribute('data-tip')).toBeNull();
    const aria = (await page.locator('#summaryTip').getAttribute('aria-label')) || '';
    expect(aria).toMatch(/^Tip:\s*/);

    await page.locator('#summaryTip').click();
    await openInfo(page);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Tip');
    const tipText = ((await page.locator('[data-arrangement-mobile-info-body] .arrangement-mobile-info-tip').textContent()) || '').trim();
    expect(tipText.length).toBeGreaterThan(0);
    expect(tipText, 'the sheet shows the same live Tip message').toBe(aria.replace(/^Tip:\s*/i, '').trim());

    // No shared floating tooltip is drawn on top of the sheet. The shared
    // engine always mounts ONE hidden div; the assertion is visibility, not
    // node count.
    expect((await floatingTooltipState(page)).every((tip) => !tip.shown), 'no duplicate floating tooltip').toBe(true);
    await page.waitForTimeout(400);
    const after = await floatingTooltipState(page);
    expect(after.every((tip) => !tip.shown), 'no duplicate floating tooltip after the click settles').toBe(true);
    expect(await page.locator('#summaryTip').getAttribute('data-tip'), 'suppression survives the open sheet').toBeNull();

    await page.locator('[data-arrangement-mobile-info-close]').click();
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    expect((await floatingTooltipState(page)).every((tip) => !tip.shown)).toBe(true);
  });
});

/* ─────────────────────────────────────────────────────────────────────────
 * T22 — guard, expiry and submit-lifecycle browser group
 * ─────────────────────────────────────────────────────────────────────────
 * AUTHORED ONLY (browser execution remains PAUSED). This group still uses the
 * file's first-statement fail-closed guard, T21a's exact-keyed testing
 * fixtures and the serial run-alone contract. It adds NO SQL writes: T22a/c
 * are read-only page/UI flows and T22b drives the page's own clock helpers in
 * memory (no DB, no reseed).
 *
 *   T22a — existing guards / arrival identity        (MA-09a–b)
 *   T22b — clock expiry and store retention          (MA-10a–d)
 *   T22c — request identity, busy and error lifecycle (MA-11a–d, MA-08b)
 *
 * TRANSPORT: T22c never submits against a server. It wraps the page's
 * `window.pageLivewire()` resolver in the browser context and returns a
 * controlled promise for `submitBlocks` only. The post-success redirect is
 * fulfilled by `page.route`, so no Livewire request and no DB write occur.
 * ───────────────────────────────────────────────────────────────────────── */

/* ─────────────────────────────────────────────────────────────────────────
 * T22 shared helpers
 * ───────────────────────────────────────────────────────────────────────── */

interface T22Selection extends CellRun {
  week: number;
  span: number;
}

/**
 * This page deliberately sets `MockData.mockNow = null` at boot, so
 * `DateHelper.getTodayMs()` (the page's one calendar clock reader) falls back
 * to the real browser `Date`. T22b therefore uses TWO deterministic controls:
 *   - `page.clock.setFixedTime()` moves the browser Date to a DISTINCT sentinel
 *     local date, which unlocks `reconcileMobileClock()`'s once-per-date guard;
 *   - the page-context override of `DateHelper.getTodayMs()` carries the
 *     simulated "today" used by isSlotTooSoon()/weekHasBookableSlot().
 * They must be separate: a single page.clock date would both expire eligibility
 * and leave the guard key equal to the previous pass, so a second reconcile
 * could be skipped. No production file and no shared MockData object is edited;
 * every test gets a fresh page context.
 */
let t22ClockTick = 0;

/** Honest block if the runner predates Playwright's supported page.clock. */
function t22RequireClock(page: Page): void {
  if (!page.clock || typeof (page.clock as any).setFixedTime !== 'function') {
    test.skip(true, 'Playwright page.clock is unavailable in this runner; the deterministic-clock cases are blocked, not passed');
  }
}

async function t22SetToday(page: Page, date: Date): Promise<void> {
  t22RequireClock(page);
  t22ClockTick += 1;
  /* A distinct date key only unlocks the page's reconcile guard; eligibility
     itself reads DateHelper.getTodayMs below. */
  await page.clock.setFixedTime(new Date(2035, 0, 1 + t22ClockTick, 12, 0, 0));
  await page.evaluate(({ y, m, d }) => {
    const simulated = new Date(y, m, d);
    (DateHelper as any).getTodayMs = function () { return simulated.getTime(); };
  }, { y: date.getFullYear(), m: date.getMonth(), d: date.getDate() });
  await page.evaluate(() => window.dispatchEvent(new Event('focus')));
}

function t22ParseCrsDate(text: string): Date | null {
  const match = /^(\d{1,2}) ([A-Z][a-z]{2}) (\d{4})$/.exec(text);
  if (!match) return null;
  const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
  const month = months.indexOf(match[2]);
  if (month < 0) return null;
  return new Date(Number(match[3]), month, Number(match[1]));
}

async function t22DayDate(page: Page, week: number, day: number): Promise<string> {
  return page.evaluate(({ w, d }) => {
    const data = (window as any).weekData;
    return data && data[w] && data[w].days[d] ? String(data[w].days[d].date) : '';
  }, { w: week, d: day });
}

/** The first whole-span canonical run, capped to `maxDay` (0 = Monday). */
async function t22FindRunAcrossWeeks(
  page: Page,
  span: number,
  maxDay: number,
): Promise<(CellRun & { week: number }) | null> {
  const weeks = await offeredWeekIndexes(page);
  for (const week of weeks) {
    if (!(await selectWeekByIndex(page, week))) continue;
    const run = await page.evaluate(({ spanSlots, dayCap }) => {
      const available: Record<string, boolean> = {};
      document.querySelectorAll('#tableBody td[data-day][data-hour] .cell-content').forEach((cell) => {
        const td = cell.closest('td');
        if (!td) return;
        const day = Number(td.getAttribute('data-day'));
        const hour = Number(td.getAttribute('data-hour'));
        if (cell.classList.contains('cell-available')) available[`${day}:${hour}`] = true;
      });
      for (let day = 0; day <= dayCap && day < 6; day += 1) {
        if (!document.querySelector(`[data-arrangement-mobile-days] button[data-day-index="${day}"]`)) continue;
        for (let hour = 0; hour + spanSlots <= 22; hour += 1) {
          let complete = true;
          for (let k = 0; k < spanSlots; k += 1) {
            if (!available[`${day}:${hour + k}`]) { complete = false; break; }
          }
          if (complete) return { day, hour };
        }
      }
      return null;
    }, { spanSlots: span, dayCap: maxDay });
    if (run) return { week, ...run };
  }
  return null;
}

/**
 * Create a real saved selection through the page's own arrival/pin/canonical
 * selection path. `maxDay` lets T22b prefer Mon–Tue so a same-week expiry can
 * be exercised with a forward/same-day clock move.
 */
async function t22CreateSelection(page: Page, maxDay = 5): Promise<T22Selection> {
  await gotoArrangement(page);
  const events = await readMobileEvents(page);
  for (const span of [4, 2]) {
    const event = pickEvent(events, (item) => item.durationSlots === span && item.weekIndex >= 0);
    if (!event) continue;
    await gotoArrival(page, event);
    await expectNoSelection(page);
    await selectOriginalEventSlot(page, event);
    const run = await t22FindRunAcrossWeeks(page, span, maxDay);
    if (!run) continue;
    await tapHourRow(page, run.day, run.hour);
    await waitForSavedSpan(page, span);
    return { week: run.week, day: run.day, hour: run.hour, span };
  }
  test.skip(
    true,
    `no conflicted occurrence${maxDay < 5 ? ` with a contiguous free run on day <= ${maxDay}` : ' with a contiguous free run'} exists for staff 5425`,
  );
  return null as unknown as T22Selection;
}

/**
 * Choose a simulated today IN the saved block's own week where the saved day
 * is too soon but the week still has another eligible day. Uses the page's
 * OWN helper rules (isSlotTooSoon/weekHasBookableSlot), never a copied
 * notice-period calculation.
 */
async function t22SameWeekExpiryClock(
  page: Page,
  week: number,
  day: number,
): Promise<Date | null> {
  const result = await page.evaluate(({ w, d }) => {
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const data = (window as any).weekData;
    const weekData = data && data[w];
    if (!weekData || !Array.isArray(weekData.days)) return null;
    const original = (DateHelper as any).getTodayMs;
    const realToday = new Date(original());
    realToday.setHours(0, 0, 0, 0);
    try {
      /* Prefer the saved day itself (a forward/same-day clock move), then
         earlier working days in the same week only when they are still not
         before the real today. A backward clock is never simulated. */
      for (let c = d; c >= 0; c -= 1) {
        const candidate = weekData.days[c];
        if (!candidate || candidate.sunday || candidate.abbr === 'Sun' || candidate.holiday) continue;
        const parts = String(candidate.date || '').split(' ');
        if (parts.length !== 3) continue;
        const month = months.indexOf(parts[1]);
        if (month < 0) continue;
        const dt = new Date(Number(parts[2]), month, Number(parts[0]));
        if (Number.isNaN(dt.getTime())) continue;
        if (dt.getTime() < realToday.getTime()) continue;
        (DateHelper as any).getTodayMs = function () { return dt.getTime(); };
        const selectedTooSoon = !!(window as any).isSlotTooSoon(data, w, d);
        const weekStillBookable = !!(window as any).weekHasBookableSlot(data, w);
        if (selectedTooSoon && weekStillBookable) {
          return { y: dt.getFullYear(), m: dt.getMonth(), day: dt.getDate() };
        }
      }
      return null;
    } finally {
      (DateHelper as any).getTodayMs = original;
    }
  }, { w: week, d: day });
  return result ? new Date(result.y, result.m, result.day) : null;
}

/** The offered weeks the page's own week filter says should be present. */
async function t22ExpectedOfferedWeeks(page: Page): Promise<number[]> {
  return page.evaluate(() => {
    const data = (window as any).weekData || [];
    const out: number[] = [];
    data.forEach((_week: unknown, index: number) => {
      if ((window as any).weekHasBookableSlot(data, index)) out.push(index);
    });
    return out;
  });
}

function t22RetentionNotice(page: Page) {
  return page.locator('.arrangement-mobile-summary--retention');
}

/* The saved-entry opener, not the retention notice that sits above it. The
   generic summaryTitle() helper returns the first title in the host, which is
   the retention notice once a saved entry is retained. */
function t22SavedSummaryTitle(page: Page) {
  return page.locator('[data-arrangement-mobile-summary-opener] .arrangement-mobile-summary-title').first();
}

function t22SavedSummaryMeta(page: Page) {
  return page.locator('[data-arrangement-mobile-summary-opener] .arrangement-mobile-summary-meta').first();
}

async function t22WaitRetained(page: Page): Promise<void> {
  await expect(t22RetentionNotice(page)).toBeVisible({ timeout: 20_000 });
  await expect(page.locator('#submitRequestBtn')).toBeDisabled();
}

async function t22CanonicalClasses(
  page: Page,
  day: number,
  startHour: number,
  span: number,
): Promise<Array<{ hour: number; classes: string[] }>> {
  return page.evaluate(({ d, h, s }) => {
    const out: Array<{ hour: number; classes: string[] }> = [];
    for (let hour = h; hour < h + s; hour += 1) {
      const cell = document.querySelector(`#tableBody td[data-day="${d}"][data-hour="${hour}"] .cell-content`);
      out.push({
        hour,
        classes: cell ? Array.from(cell.classList) : [],
      });
    }
    return out;
  }, { d: day, h: startHour, s: span });
}

async function t22ExpectNoGreenOrSelected(
  page: Page,
  day: number,
  startHour: number,
  span: number,
): Promise<void> {
  const cells = await t22CanonicalClasses(page, day, startHour, span);
  expect(cells.length, 'the saved canonical cells are still present').toBe(span);
  for (const cell of cells) {
    expect(cell.classes, `expired saved cell ${cell.hour} must not be repainted available`)
      .not.toContain('cell-available');
    expect(cell.classes, `expired saved cell ${cell.hour} must not stay selected`)
      .not.toContain('cell-selected');
  }
}

/* ── T22c controlled transport (page context only; no network, no DB) ── */

type T22TransportMode = 'pending' | 'success' | 'business-reject' | 'throw' | 'reject';

async function t22InstallSubmitTransport(page: Page, mode: T22TransportMode): Promise<void> {
  await page.evaluate((transportMode) => {
    const w = window as any;
    w.__t22 = { calls: [] as string[], payloads: [] as any[], resolve: null, reject: null };
    const original = w.pageLivewire;
    w.pageLivewire = function () {
      const wire = typeof original === 'function' ? original() : null;
      return {
        call: function (name: string, ...args: any[]) {
          if (name !== 'submitBlocks') {
            return wire && typeof wire.call === 'function'
              ? wire.call(name, ...args)
              : Promise.resolve(null);
          }
          w.__t22.calls.push(name);
          w.__t22.payloads.push({
            blocks: JSON.parse(args[0] || '[]'),
            code: args[1],
            original: JSON.parse(args[2] || '{}'),
            pin: JSON.parse(args[3] || '{}'),
          });
          if (transportMode === 'throw') throw new Error('T22 controlled synchronous transport failure');
          if (transportMode === 'reject') return Promise.reject(new Error('T22 controlled transport rejection'));
          if (transportMode === 'success') return Promise.resolve([{ ok: true, request_id: 987654 }]);
          if (transportMode === 'business-reject') {
            return Promise.resolve([{ ok: false, reason: 'slot_not_available' }]);
          }
          return new Promise(function (resolve, reject) {
            w.__t22.resolve = resolve;
            w.__t22.reject = reject;
          });
        },
      };
    };
  }, mode);
}

async function t22TransportState(page: Page): Promise<{ calls: number; payloads: any[] }> {
  return page.evaluate(() => ({
    calls: ((window as any).__t22 && (window as any).__t22.calls.length) || 0,
    payloads: ((window as any).__t22 && (window as any).__t22.payloads) || [],
  }));
}

async function t22ResolveSubmit(page: Page, results: unknown[]): Promise<void> {
  await page.evaluate((value) => { (window as any).__t22.resolve(value); }, results);
}

async function t22RejectSubmit(page: Page): Promise<void> {
  await page.evaluate(() => {
    (window as any).__t22.reject(new Error('T22 controlled transport rejection'));
  });
}

/* ── T22a guard helpers ── */

async function t22EnsureContextOpen(page: Page): Promise<void> {
  const details = page.locator('[data-arrangement-mobile-context]');
  const open = await details.evaluate((node) => (node as unknown as HTMLDetailsElement).open);
  if (!open) await page.locator('[data-arrangement-mobile-context-summary]').click();
}

async function t22DifferentSubject(page: Page): Promise<{ from: string; to: string } | null> {
  await t22EnsureContextOpen(page);
  const choice = await page.evaluate(() => {
    const select = document.getElementById('subjectSelector') as HTMLSelectElement | null;
    if (!select) return null;
    const options = Array.from(select.options).filter((option) => option.value && option.value !== select.value);
    return options.length ? { from: select.value, to: options[0].value } : null;
  });
  if (!choice) return null;
  await page.selectOption('#subjectSelector', choice.to);
  return choice;
}

async function t22DifferentSlot(
  page: Page,
): Promise<{ index: number; previousIndex: number; label: string; beforeText: string } | null> {
  await t22EnsureContextOpen(page);
  const panelOpen = await page.locator('#slotPanel').evaluate((node) => node.classList.contains('open'));
  if (!panelOpen) await page.locator('#slotTrigger').click();
  await expect(page.locator('#slotPanel')).toHaveClass(/open/);
  const beforeText = ((await page.locator('#slotTriggerText').textContent()) || '').trim();
  const current = await page.evaluate(() =>
    Array.from(document.querySelectorAll('.slot-dd-item')).findIndex((item) => item.classList.contains('selected')));
  const target = await page.evaluate((currentIndex) => {
    const items = Array.from(document.querySelectorAll('.slot-dd-item'));
    const index = items.findIndex((_item, itemIndex) => itemIndex !== currentIndex);
    if (index < 0) return null;
    const label = (items[index].textContent || '').replace(/\s+/g, ' ').trim();
    return { index, label };
  }, current);
  if (!target) return null;
  await page.locator('.slot-dd-item').nth(target.index).click();
  return { index: target.index, previousIndex: current, label: target.label, beforeText };
}

/**
 * Drive the real four-column venue row handlers. T23 owns the panel's mobile
 * geometry; T22 only needs the real onSelect/confirm path, so the hierarchy is
 * clicked in-page after the real trigger opens the panel.
 */
async function t22DifferentVenue(page: Page): Promise<string | null> {
  const current = venueCodeOf(await venueLabel(page));
  const isOpen = await page.locator('#buildingSelectorDropdown').evaluate((node) => node.classList.contains('open'));
  if (!isOpen) await page.locator('#buildingSelectorDropdown .venue-dd-trigger').click();
  await expect(page.locator('#buildingSelectorDropdown')).toHaveClass(/open/);
  const picked = await page.evaluate((currentCode) => {
    const host = document.getElementById('buildingSelectorDropdown');
    if (!host) return null;
    const click = (node: Element | null) => { if (node) (node as HTMLElement).click(); };
    const types = Array.from(host.querySelectorAll('.venue-col-item-parent[data-type]'));
    for (const type of types) {
      click(type);
      const blocks = Array.from(host.querySelectorAll('.venue-col-item-parent[data-block]'));
      for (const block of blocks) {
        click(block);
        const floors = Array.from(host.querySelectorAll('.venue-col-item-parent[data-floor]'));
        for (const floor of floors) {
          click(floor);
          const rooms = Array.from(host.querySelectorAll('.venue-col-item-room[data-code]'));
          for (const room of rooms) {
            const code = room.getAttribute('data-code');
            if (code && code !== currentCode) { (room as HTMLElement).click(); return code; }
          }
        }
      }
    }
    return null;
  }, current);
  if (!picked) await page.keyboard.press('Escape');
  return picked;
}

/* ─────────────────────────────────────────────────────────────────────────
 * T22a — existing change guards, arrival/pin/intent and leave guards
 * (MA-09a–b)
 * ───────────────────────────────────────────────────────────────────────── */
test.describe('T22a — mobile change guards, arrival pin and leave guards', () => {
  test.beforeEach(async ({ page }) => { await login(page); });

  test('subject change opens the existing confirmation; Cancel restores value/selection and Confirm discards once', async ({ page }) => {
    await t22CreateSelection(page, 5);
    const titleBefore = await summaryTitle(page).textContent();
    const metaBefore = await summaryMeta(page).textContent();
    expect(await page.locator('[data-mobile-row="span"]').count(), 'a real saved block exists').toBe(1);

    const choice = await t22DifferentSubject(page);
    test.skip(choice === null, 'the seeded subject selector offers only the already-committed subject');

    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#modalTitle')).toHaveText('Change Course?');
    await expect(page.locator('#confirmModal .btn-outline')).toHaveText('No, Keep My Selection');
    await page.locator('#confirmModal .btn-outline').click();
    await expect(page.locator('#confirmModal')).toBeHidden();

    await expect(page.locator('#subjectSelector')).toHaveValue(choice!.from);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
    await expect(summaryTitle(page)).toHaveText(titleBefore || '');
    await expect(summaryMeta(page)).toHaveText(metaBefore || '');

    /* Confirm is the existing explicit discard + one subject application. */
    await page.selectOption('#subjectSelector', choice!.to);
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
    await expect(page.locator('#subjectSelector')).toHaveValue(choice!.to);
    await expectNoSelection(page);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);
  });

  test('original-slot change Cancel restores the committed slot and Confirm applies the one existing change', async ({ page }) => {
    await t22CreateSelection(page, 5);
    const titleBefore = await summaryTitle(page).textContent();
    const metaBefore = await summaryMeta(page).textContent();

    const target = await t22DifferentSlot(page);
    test.skip(target === null, 'the original-slot picker offers no second occurrence to change to');

    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#modalTitle')).toHaveText('Change Time Slot?');
    await page.locator('#confirmModal .btn-outline').click();
    await expect(page.locator('#confirmModal')).toBeHidden();

    await expect(page.locator('#slotTriggerText')).toHaveText(target!.beforeText);
    await expect(page.locator('.slot-dd-item.selected')).toHaveCount(1);
    await expect(page.locator('.slot-dd-item.selected')).toHaveAttribute('data-index', String(target!.previousIndex));
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
    await expect(summaryTitle(page)).toHaveText(titleBefore || '');
    await expect(summaryMeta(page)).toHaveText(metaBefore || '');

    const again = await t22DifferentSlot(page);
    test.skip(again === null, 'the second original-slot pick was no longer offered');
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
    await expect(page.locator('.slot-dd-item.selected')).toHaveAttribute('data-index', String(again!.index));
    await expect(page.locator('#slotTriggerText')).toContainText(/\bWeek \d+\b/);
    await expectNoSelection(page);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);
  });

  test('venue change opens the current confirmation; Cancel restores label/selection and Confirm applies the one existing change', async ({ page }) => {
    await t22CreateSelection(page, 5);
    const labelBefore = await venueLabel(page);
    const titleBefore = await summaryTitle(page).textContent();
    const metaBefore = await summaryMeta(page).textContent();

    const picked = await t22DifferentVenue(page);
    test.skip(picked === null, 'the seeded venue matrix offers no second venue for a real change');

    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#modalTitle')).toHaveText('Change Venue?');
    await page.locator('#confirmModal .btn-outline').click();
    await expect(page.locator('#confirmModal')).toBeHidden();

    expect(await venueLabel(page)).toBe(labelBefore);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
    await expect(summaryTitle(page)).toHaveText(titleBefore || '');
    await expect(summaryMeta(page)).toHaveText(metaBefore || '');

    const again = await t22DifferentVenue(page);
    test.skip(again === null, 'the second venue pick was no longer offered');
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
    expect(venueCodeOf(await venueLabel(page))).toBe(again);
    await expectNoSelection(page);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);
  });

  test('arrival pin is preserved, mobile invents no occurrence, and both leave guards remain', async ({ page }) => {
    await gotoArrangement(page);
    const events = await readMobileEvents(page);
    const event = pickEvent(events, (item) => item.weekIndex >= 0 && item.durationSlots > 0);
    test.skip(event === null, 'staff 5425 has no conflicted occurrence for an arrival-pin check');

    await gotoArrival(page, event!);
    const pin = await page.evaluate(() => {
      const value = (window as any).pinnedOccurrence;
      return value ? { sessionId: Number(value.sessionId), week: Number(value.week) } : null;
    });
    expect(pin, 'the arrival URL owns the occurrence pin').not.toBeNull();
    expect(pin!.sessionId).toBe(event!.sessionId);
    expect(pin!.week).toBe(event!.weekIndex + 1);
    await expectNoSelection(page);

    const span = Math.min(Math.max(event!.durationSlots, 1), 4);
    const run = await selectFirstRun(page, span);
    test.skip(run === null, `the pinned venue has no contiguous ${span}-half-hour free run`);

    const pinAfter = await page.evaluate(() => {
      const value = (window as any).pinnedOccurrence;
      return value ? { sessionId: Number(value.sessionId), week: Number(value.week) } : null;
    });
    expect(pinAfter, 'choosing a replacement does not invent a new pin').toEqual(pin);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);

    /* In-page leave guard: the existing Unsaved Changes modal, Stay path. */
    const urlBefore = page.url();
    await page.locator('.back-btn').click();
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#modalTitle')).toHaveText('Unsaved Changes');
    await expect(page.locator('#confirmModal .btn-outline')).toHaveText('Stay');
    await page.locator('#confirmModal .btn-outline').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
    expect(page.url()).toBe(urlBefore);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);

    /* Browser-level guard: dismiss the native beforeunload and stay put. */
    const dialogs: string[] = [];
    page.once('dialog', async (dialog) => {
      dialogs.push(dialog.type());
      await dialog.dismiss().catch(() => {});
    });
    await page.reload({ timeout: 5_000 }).catch(() => {});
    if (dialogs.length) {
      expect(dialogs, 'the beforeunload guard protects the unsaved selection').toContain('beforeunload');
    } else {
      test.info().annotations.push({
        type: 'environment-gap',
        description: 'this headless run suppressed the native beforeunload dialog; the in-page Unsaved Changes guard above still proved the protected-leave path',
      });
    }
  });

  test('a booking-intent arrival keeps its own venue/date/time identity while mobile remains active', async ({ page }) => {
    await gotoArrangement(page);
    const events = await readMobileEvents(page);
    const event = pickEvent(events, (item) => item.weekIndex >= 0 && !!item.startTime);
    test.skip(event === null, 'no conflicted occurrence exists to build a booking-intent URL');

    const date = await t22DayDate(page, event!.weekIndex, event!.dayOfWeek);
    test.skip(!date, 'the event week/day does not resolve to a real weekData date');
    const intentPath = '/replacement-arrangement?venue=' + encodeURIComponent(event!.venue)
      + '&date=' + encodeURIComponent(date)
      + '&time=' + encodeURIComponent(event!.startTime);
    await gotoArrangement(page, intentPath);

    /* The existing handoff may legitimately rewrite the URL to the pinned
       session; the intent identity is asserted through the banner/selection
       below rather than forcing byte-identical query params. */
    const params = new URL(page.url()).searchParams;
    expect(params.get('venue') === event!.venue || params.get('session') !== null,
      'the intent handoff still exposes the booked venue/session it arrived with').toBe(true);
    expect(params.get('date') === date || params.get('session') !== null,
      'the intent handoff still exposes the booked date/session it arrived with').toBe(true);
    expect(params.get('time') === event!.startTime || params.get('session') !== null,
      'the intent handoff still exposes the booked time/session it arrived with').toBe(true);

    /* The fallback/auto-pin consume runs after boot; give the existing
       intent path one settled turn before deciding banner vs selection. */
    await page.waitForFunction(() => {
      const banner = document.querySelector('#bookingIntent .booking-intent');
      const span = document.querySelector('[data-mobile-row="span"]');
      return !!banner || !!span;
    }, undefined, { timeout: 5_000 }).catch(() => {});

    const banner = page.locator('#bookingIntent .booking-intent');
    const bannerCount = await banner.count();
    const spanCount = await page.locator('[data-mobile-row="span"]').count();
    if (bannerCount === 0 && spanCount === 0) {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'this intent URL produced neither the existing intent banner nor an auto-selection in the seeded data; no mobile rewrite was observed',
      });
      return;
    }
    if (spanCount > 0) {
      const expectedHour = await page.evaluate((time) => hours.indexOf(time), event!.startTime);
      await expect(page.locator('[data-mobile-row="span"]')).toHaveAttribute('data-mobile-day', String(event!.dayOfWeek));
      await expect(page.locator('[data-mobile-row="span"]')).toHaveAttribute('data-mobile-hour', String(expectedHour));
      await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
    } else {
      await expect(banner.first()).toContainText(date);
      await expect(banner.first()).toContainText(event!.startTime);
      await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);
    }
  });
});

/* ─────────────────────────────────────────────────────────────────────────
 * T22b — clock expiry and saved-store retention (MA-10a–d)
 * ───────────────────────────────────────────────────────────────────────── */
test.describe('T22b — mobile clock expiry, retained entries and week options', () => {
  test.beforeEach(async ({ page }) => {
    t22RequireClock(page);
    /* The page nulls MockData.mockNow at boot, so DateHelper.getTodayMs()
       falls back to the browser Date. Install the Date patch before the page
       scripts run; t22SetToday then moves it to a distinct sentinel date key
       while overriding the clock reader to the simulated day. */
    await page.clock.setFixedTime(new Date());
    await login(page);
  });

  test('an expired saved selection survives same-week rebuild, week hop, desktop resize and clears only on explicit removal', async ({ page }) => {
    const selection = await t22CreateSelection(page, 1);
    const dateText = await t22DayDate(page, selection.week, selection.day);
    const selectedDate = t22ParseCrsDate(dateText);
    test.skip(selectedDate === null, 'the saved day has no parseable real date');

    const expiryClock = await t22SameWeekExpiryClock(page, selection.week, selection.day);
    test.skip(expiryClock === null, 'no same-week simulated today expires the saved day while keeping another day eligible');

    const titleBefore = ((await t22SavedSummaryTitle(page).textContent()) || '').trim();
    const metaBefore = ((await t22SavedSummaryMeta(page).textContent()) || '').trim();
    const headingBefore = await dayHeading(page);
    const venueBefore = await venueLabel(page);

    /* Open the saved-details sheet BEFORE expiry: MA-10d says clock
       reconciliation must not auto-close readable information. */
    await page.locator('[data-arrangement-mobile-summary-opener]').click();
    await openInfo(page);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Saved selection');
    const factsBefore = await infoFacts(page);

    await t22SetToday(page, expiryClock!);
    await t22WaitRetained(page);

    /* Same week is retained (not a forward hop), the saved day vanished, and
       the option list was rebuilt from the real helper result. */
    expect(await currentWeekIndex(page)).toBe(selection.week);
    const offered = await offeredWeekIndexes(page);
    expect(offered, 'the current week still has another eligible day').toContain(selection.week);
    expect(offered, 'week options are rebuilt from the real week filter').toEqual(await t22ExpectedOfferedWeeks(page));
    const visible = await visibleDayIndexes(page);
    expect(visible, 'the expired saved day is no longer offered').not.toContain(selection.day);
    expect(visible.length, 'a later day in the same week remains').toBeGreaterThan(0);

    await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);
    await expect(t22SavedSummaryMeta(page)).toHaveText(metaBefore);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);
    await expect(page.locator('[data-arrangement-mobile-timeline] [data-mobile-row="hour"]'))
      .toHaveCount(ROW_COUNT);

    /* No green repaint: the canonical cells for the saved range are blocked,
       never available/selected again. */
    await t22ExpectNoGreenOrSelected(page, selection.day, selection.hour, selection.span);

    /* Open sheet persists with readable facts and its Remove action. */
    expect(await infoOpen(page), 'an open information sheet is not auto-closed by expiry').toBe(true);
    const factsAfter = await infoFacts(page);
    const entryBefore = factsBefore.find((entry) => typeof entry.Date === 'string') || {};
    const entryAfter = factsAfter.find((entry) => typeof entry.Date === 'string') || {};
    expect(entryBefore.Date, 'the open sheet listed the saved date before expiry').toBeTruthy();
    expect(entryBefore.Time, 'the open sheet listed the saved time before expiry').toBeTruthy();
    expect(entryAfter.Date).toBe(entryBefore.Date);
    expect(entryAfter.Time).toBe(entryBefore.Time);
    await expect(
      page.locator('[data-arrangement-mobile-info-actions] button', { hasText: 'Remove selection' }),
    ).toHaveCount(1);
    await page.locator('[data-arrangement-mobile-info-close]').click();

    /* Week hop away/back keeps the saved summary and its marker. */
    const otherWeek = (await offeredWeekIndexes(page)).find((week) => week !== selection.week);
    if (otherWeek !== undefined) {
      expect(await selectWeekByIndex(page, otherWeek)).toBe(true);
      await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);
      await expect(t22RetentionNotice(page)).toBeVisible();
      expect(await selectWeekByIndex(page, selection.week)).toBe(true);
      await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);
      await expect(t22RetentionNotice(page)).toBeVisible();
    } else {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'only the saved week is offered; the week-hop retention half could not be exercised',
      });
    }

    /* Desktop resize (and, when possible, a desktop week hop) must not let
       saveCurrentWeek() erase the retained identity. */
    await page.setViewportSize({ width: 1280, height: 800 });
    await expect(page.locator('body')).not.toHaveClass(/arrangement-mobile-mode/);
    const desktopWeeks = await offeredWeekIndexes(page);
    const desktopOther = desktopWeeks.find((week) => week !== selection.week);
    if (desktopOther !== undefined) {
      expect(await selectWeekByIndex(page, desktopOther)).toBe(true);
      expect(await selectWeekByIndex(page, selection.week)).toBe(true);
    }
    await page.setViewportSize({ width: 390, height: 844 });
    await waitForMobile(page);
    await t22WaitRetained(page);
    await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);
    await expect(t22SavedSummaryMeta(page)).toHaveText(metaBefore);
    expect(await venueCodeOf(await venueLabel(page))).toBe(venueCodeOf(venueBefore));
    expect(await dayHeading(page)).not.toBe(headingBefore);

    /* Explicit removal clears the entry and its marker; no stale resurrection. */
    await page.locator('[data-arrangement-mobile-summary-opener]').click();
    await openInfo(page);
    const remove = page.locator('[data-arrangement-mobile-info-actions] button', { hasText: 'Remove selection' });
    await expect(remove).toHaveCount(1);
    await remove.click();
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    await expectNoSelection(page);
    await expect(t22RetentionNotice(page)).toHaveCount(0);
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    await t22ExpectNoGreenOrSelected(page, selection.day, selection.hour, selection.span);

    if (otherWeek !== undefined) {
      expect(await selectWeekByIndex(page, otherWeek)).toBe(true);
      expect(await selectWeekByIndex(page, selection.week)).toBe(true);
      await expectNoSelection(page);
      await expect(t22RetentionNotice(page)).toHaveCount(0);
      await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(0);
    }
  });

  test('the final expired week shows the exact empty state while the retained selection stays inspectable and removable', async ({ page }) => {
    const selection = await t22CreateSelection(page);
    const dateText = await t22DayDate(page, selection.week, selection.day);
    const selectedDate = t22ParseCrsDate(dateText);
    test.skip(selectedDate === null, 'the saved day has no parseable real date');

    const titleBefore = ((await t22SavedSummaryTitle(page).textContent()) || '').trim();
    const metaBefore = ((await t22SavedSummaryMeta(page).textContent()) || '').trim();
    const offeredBefore = await offeredWeekIndexes(page);
    expect(offeredBefore.length, 'a real week option existed before the clock advance').toBeGreaterThan(0);

    /* Open details before the final option expires. */
    await page.locator('[data-arrangement-mobile-summary-opener]').click();
    await openInfo(page);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Saved selection');

    const farFuture = new Date(selectedDate!.getFullYear() + 2, selectedDate!.getMonth(), selectedDate!.getDate());
    await t22SetToday(page, farFuture);

    const empty = page.locator('[data-arrangement-mobile-empty]');
    await expect(empty).toBeVisible({ timeout: 20_000 });
    await expect(empty).toHaveText('No bookable dates remaining this semester.');
    await expect(page.locator('[data-arrangement-mobile-timeline]')).toBeHidden();
    await expect(page.locator('[data-arrangement-mobile-days] button')).toHaveCount(0);
    await expect(page.locator('#weekSelector')).toBeDisabled();
    await expect(page.locator('#earliestBtn')).toBeDisabled();
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    await expect(page.locator('.week-arrow[aria-label="Previous week"]')).toBeDisabled();
    await expect(page.locator('.week-arrow[aria-label="Next week"]')).toBeDisabled();

    /* The final option is replaced by the disabled placeholder, not a stale
       selectable week. */
    expect(await t22ExpectedOfferedWeeks(page)).toEqual([]);
    expect(await offeredWeekIndexes(page)).toEqual([]);
    const options = await page.evaluate(() => {
      const select = document.getElementById('weekSelector') as HTMLSelectElement | null;
      return select
        ? Array.from(select.options).map((option) => ({
            value: option.value,
            text: (option.textContent || '').trim(),
            disabled: option.disabled,
          }))
        : [];
    });
    expect(options).toHaveLength(1);
    expect(options[0].value).toBe('');
    expect(options[0].disabled).toBe(true);
    expect(options[0].text).toContain('No bookable weeks');

    /* Saved data is retained and still readable; the open sheet did not close. */
    await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);
    await expect(t22SavedSummaryMeta(page)).toHaveText(metaBefore);
    await expect(t22RetentionNotice(page)).toBeVisible();
    expect(await infoOpen(page), 'the open details sheet survives final option expiry').toBe(true);
    await expect(
      page.locator('[data-arrangement-mobile-info-actions] button', { hasText: 'Remove selection' }),
    ).toHaveCount(1);
    await expect(page.locator('[data-arrangement-mobile-legend]')).toBeVisible();
    await expect(page.locator('[data-arrangement-mobile-context-summary]')).toBeVisible();

    /* Explicit removal in the empty semester clears the bar/marker and keeps
       the empty presentation. */
    await page.locator('[data-arrangement-mobile-info-actions] button', { hasText: 'Remove selection' }).click();
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    await expectNoSelection(page);
    await expect(t22RetentionNotice(page)).toHaveCount(0);
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    await expect(empty).toBeVisible();
    await expect(empty).toHaveText('No bookable dates remaining this semester.');
  });
});

/* ─────────────────────────────────────────────────────────────────────────
 * T22c — mobile-origin request identity, busy and error lifecycle
 * (MA-11a–d, MA-08b)
 * ───────────────────────────────────────────────────────────────────────── */
test.describe('T22c — mobile submit identity, duplicate suppression and failure release', () => {
  test.beforeEach(async ({ page }) => { await login(page); });

  async function openSubmitConfirmation(page: Page): Promise<void> {
    await expect(page.locator('#submitRequestBtn')).toBeEnabled({ timeout: 15_000 });
    await page.locator('#submitRequestBtn').click();
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#modalTitle')).toHaveText('Confirm Your Selection');
    await expect(page.locator('#modalConfirmBtn')).toHaveText('Yes, Submit Request');
  }

  test('a date that expires before Yes blocks submitBlocks, keeps data and shows persistent review guidance', async ({ page }) => {
    t22RequireClock(page);
    await page.clock.setFixedTime(new Date());
    const selection = await t22CreateSelection(page);
    const titleBefore = ((await t22SavedSummaryTitle(page).textContent()) || '').trim();
    await t22InstallSubmitTransport(page, 'pending');
    await openSubmitConfirmation(page);
    expect((await t22TransportState(page)).calls, 'Submit does not start a network call by itself').toBe(0);

    /* Expire the saved date while the confirmation is already open. */
    const dateText = await t22DayDate(page, selection.week, selection.day);
    const parsed = t22ParseCrsDate(dateText);
    const expiry = parsed
      ? new Date(parsed.getFullYear() + 2, parsed.getMonth(), parsed.getDate())
      : new Date(2100, 0, 1);
    await t22SetToday(page, expiry);

    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#modalTitle')).toHaveText(/Review Your Saved Selection/, { timeout: 10_000 });
    await expect(page.locator('#modalBody')).toContainText('Nothing was submitted and your saved selection was kept');
    expect((await t22TransportState(page)).calls, 'the final Yes made no submitBlocks call').toBe(0);
    await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);
    await expect(t22RetentionNotice(page)).toBeVisible();
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();

    /* Persistent, not a transient toast: still readable well beyond 5 s. */
    await page.waitForTimeout(5_600);
    await expect(page.locator('#modalTitle')).toHaveText(/Review Your Saved Selection/);
    expect((await t22TransportState(page)).calls).toBe(0);
    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
  });

  test('an identity change before Yes blocks submitBlocks and keeps the saved data', async ({ page }) => {
    await t22CreateSelection(page);
    const titleBefore = ((await t22SavedSummaryTitle(page).textContent()) || '').trim();
    await t22InstallSubmitTransport(page, 'pending');
    await openSubmitConfirmation(page);

    const presented = await page.evaluate(() => {
      const pin = (window as any).pinnedOccurrence;
      return pin ? { ...pin } : null;
    });
    test.skip(presented === null, 'the selection was created without a pinned occurrence to drift');
    await page.evaluate((pin) => {
      (window as any).pinnedOccurrence = { ...pin, sessionId: Number(pin.sessionId) + 1 };
    }, presented);

    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#modalTitle')).toHaveText(/Review Your Saved Selection/, { timeout: 10_000 });
    await expect(page.locator('#modalBody')).toContainText('changed while the confirmation was open');
    expect((await t22TransportState(page)).calls, 'identity drift made no submitBlocks call').toBe(0);
    await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
  });

  test('duplicate Submit/Yes, counter rebuild and rotation cannot duplicate a pending request; rejection releases the latch', async ({ page }) => {
    const selection = await t22CreateSelection(page);
    const titleBefore = ((await t22SavedSummaryTitle(page).textContent()) || '').trim();
    await t22InstallSubmitTransport(page, 'pending');
    await openSubmitConfirmation(page);

    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
    expect((await t22TransportState(page)).calls).toBe(1);
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    expect(await page.evaluate(() => (document.getElementById('modalConfirmBtn') as HTMLButtonElement).disabled),
      'the hidden Yes button is disabled by the same latch').toBe(true);

    /* Duplicate activation paths cannot send again. */
    await page.evaluate(() => (document.getElementById('submitRequestBtn') as HTMLButtonElement).click());
    await page.evaluate(() => (document.getElementById('modalConfirmBtn') as HTMLButtonElement).click());
    expect((await t22TransportState(page)).calls).toBe(1);

    /* Counter rebuild via a real week change re-derives Submit; the latch wins. */
    const otherWeek = (await offeredWeekIndexes(page)).find((week) => week !== selection.week);
    if (otherWeek !== undefined) {
      expect(await selectWeekByIndex(page, otherWeek)).toBe(true);
      await expect(page.locator('#submitRequestBtn')).toBeDisabled();
      expect((await t22TransportState(page)).calls).toBe(1);
      expect(await selectWeekByIndex(page, selection.week)).toBe(true);
      await expect(page.locator('#submitRequestBtn')).toBeDisabled();
      expect((await t22TransportState(page)).calls).toBe(1);
    } else {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'only one week is offered; the busy counter-refresh half was not exercised',
      });
    }

    /* Rotation within mobile, landscape crossing the breakpoint and desktop
       transition all preserve the page-owned latch. */
    await page.setViewportSize({ width: 700, height: 390 });
    await expect(page.locator('body')).toHaveClass(/arrangement-mobile-mode/);
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    expect((await t22TransportState(page)).calls).toBe(1);

    await page.setViewportSize({ width: 844, height: 390 });
    await expect(page.locator('body')).not.toHaveClass(/arrangement-mobile-mode/);
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    expect((await t22TransportState(page)).calls).toBe(1);

    await page.setViewportSize({ width: 1280, height: 800 });
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    await page.setViewportSize({ width: 390, height: 844 });
    await waitForMobile(page);
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    expect((await t22TransportState(page)).calls).toBe(1);

    /* An unconfirmed transport outcome releases the latch, keeps the data and
       says the outcome is UNKNOWN; no auto-retry. */
    await t22RejectSubmit(page);
    await expect(page.locator('#modalTitle')).toHaveText(/Request OUTCOME UNKNOWN/, { timeout: 10_000 });
    await expect(page.locator('#modalBody')).toContainText('may or may not have been received');
    await expect(page.locator('#modalBody')).toContainText('My Replacements');
    await expect(page.locator('#submitRequestBtn')).toBeEnabled();
    await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);
    expect((await t22TransportState(page)).calls, 'no blind auto-retry after rejection').toBe(1);
    await page.waitForTimeout(1_500);
    expect((await t22TransportState(page)).calls).toBe(1);
    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
  });

  test('a resolved business rejection releases the latch with the existing persistent conflict guidance and no retry', async ({ page }) => {
    await t22CreateSelection(page);
    const titleBefore = ((await t22SavedSummaryTitle(page).textContent()) || '').trim();
    await t22InstallSubmitTransport(page, 'business-reject');
    await openSubmitConfirmation(page);

    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#modalTitle')).toHaveText(/Submission CONFLICT/, { timeout: 10_000 });
    await expect(page.locator('#modalBody')).toContainText('Some selections could not be');
    await expect(page.locator('#modalBody')).toContainText('REFRESH');
    expect((await t22TransportState(page)).calls).toBe(1);
    await expect(page.locator('#submitRequestBtn')).toBeEnabled();
    await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);

    await page.waitForTimeout(1_500);
    await expect(page.locator('#modalTitle')).toHaveText(/Submission CONFLICT/);
    expect((await t22TransportState(page)).calls, 'the conflict modal does not auto-resubmit').toBe(1);
    await page.locator('#confirmModal .btn-outline').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
  });

  test('a synchronous transport throw releases the latch with persistent unknown-outcome guidance', async ({ page }) => {
    await t22CreateSelection(page);
    const titleBefore = ((await t22SavedSummaryTitle(page).textContent()) || '').trim();
    await t22InstallSubmitTransport(page, 'throw');
    await openSubmitConfirmation(page);

    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#modalTitle')).toHaveText(/Request OUTCOME UNKNOWN/, { timeout: 10_000 });
    await expect(page.locator('#modalBody')).toContainText('We could not confirm that your replacement request reached the server');
    await expect(page.locator('#modalBody')).toContainText('nothing will be sent again automatically');
    expect((await t22TransportState(page)).calls).toBe(1);
    await expect(page.locator('#submitRequestBtn')).toBeEnabled();
    await expect(t22SavedSummaryTitle(page)).toHaveText(titleBefore);
    await page.waitForTimeout(1_500);
    expect((await t22TransportState(page)).calls).toBe(1);
    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
  });

  test('a successful submit keeps busy through the redirect and sends the presented identity once', async ({ page }) => {
    const selection = await t22CreateSelection(page);
    const venueCode = venueCodeOf(await venueLabel(page));
    const subjectCode = await page.evaluate(() =>
      (document.getElementById('subjectSelector') as HTMLSelectElement | null)?.value || '');
    const pin = await page.evaluate(() => {
      const value = (window as any).pinnedOccurrence;
      return value ? { ...value } : null;
    });
    test.skip(pin === null, 'the arrival did not expose a pinned occurrence for the payload receipt');

    await t22InstallSubmitTransport(page, 'pending');
    await openSubmitConfirmation(page);
    await page.locator('#modalConfirmBtn').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
    expect((await t22TransportState(page)).calls).toBe(1);
    await expect(page.locator('#submitRequestBtn')).toBeDisabled();
    /* The page context is replaced by the redirect; capture the recorded
       transport state BEFORE resolving the pending promise. */
    const transportBeforeRedirect = await t22TransportState(page);

    /* Record the real DOM busy state at the moment the document leaves. */
    await page.evaluate(() => {
      window.addEventListener('pagehide', () => {
        try {
          sessionStorage.setItem(
            '__t22_busy_at_leave',
            String(!!(document.getElementById('submitRequestBtn') as HTMLButtonElement | null)?.disabled),
          );
        } catch (error) { /* the assertion below reports the missing receipt */ }
      });
    });
    await page.route('**/my-replacements**', (route) => route.fulfill({
      status: 200,
      contentType: 'text/html',
      body: '<!doctype html><html><body>T22 redirect stub</body></html>',
    }));

    await t22ResolveSubmit(page, [{ ok: true, request_id: 987654 }]);
    await page.waitForURL(/\/my-replacements.*new-req=987654/, { timeout: 20_000 });
    expect(await page.evaluate(() => sessionStorage.getItem('__t22_busy_at_leave')),
      'Submit was still disabled when the success redirect left the page').toBe('true');

    const state = transportBeforeRedirect;
    expect(state.calls, 'the success path sent exactly one request').toBe(1);
    const payload = state.payloads[0];
    expect(payload.code).toBe(subjectCode);
    expect(payload.pin.sessionId).toBe(pin!.sessionId);
    expect(payload.blocks[0]).toMatchObject({
      venue: venueCode,
      week: selection.week,
      day: selection.day,
      startHour: selection.hour,
      endHour: selection.hour + selection.span,
    });
  });
});

/* ─────────────────────────────────────────────────────────────────────────
 * T23 — accessibility, geometry and desktop-parity browser group
 * ─────────────────────────────────────────────────────────────────────────
 * AUTHORED ONLY (browser execution remains PAUSED). Same first-statement
 * fail-closed guard, T21a exact-keyed fixtures, serial run-alone contract and
 * 390×844 mobile base viewport as T21/T22. T23 adds NO SQL writes; the page
 * clock is driven in TEST-ONLY page context and no network submit is made.
 *
 *   T23a — native keys, sheet focus/containment/return, one polite
 *          announcement, persistent sheet (MA-12a–e, MA-07e, MA-14a).
 *   T23b — 768/769 boundary, node identity/restoration, 320/390/768 + 1280
 *          baseline, text enlargement, both themes, >=44px targets,
 *          safe-area/scroll-margin/overlay geometry, span geometry and
 *          other-page isolation (MA-01a–c, MA-13a–c, MA-14c).
 *   T23c — five-second transient vs persistent details/errors, Tip sheet and
 *          no duplicate floating tooltip with desktop restoration, repeated
 *          refresh/resize non-duplication and real teardown (MA-07c–e,
 *          MA-08a–b, MA-14a–c, MA-15a–b).
 *
 * DETERMINISM: assertions use computed styles, bounding boxes, the adapter's
 * public DOM contract, the page's own helpers and real user-level actions.
 * Text enlargement is simulated with a page-scoped font-size override (no
 * browser/OS text-zoom API exists in Playwright); it is NOT device or
 * screen-reader verification. No screenshot is captured because no browser
 * permission exists for image comparison.
 * ───────────────────────────────────────────────────────────────────────── */

/* ─────────────────────────────────────────────────────────────────────────
 * T23 shared page-context probes and helpers
 * ───────────────────────────────────────────────────────────────────────── */

const T23_MOBILE_WIDTHS = [320, 390, 768] as const;
const T23_DESKTOP = { width: 1280, height: 800 } as const;
/* The adapter's user-visible copy; the timing case asserts the real text. */
const T23_ALREADY_SELECTED =
  'You already selected a class-length block. Open its details to remove it before choosing another.';
/* Node identity plan: the SAME elements T06 relocates (never copies). */
const T23_NODES = [
  '#subjectSelector',
  '#slotPicker',
  '#buildingSelectorDropdown',
  '#venueChipGroup',
  '.toolbar-filters .week-nav',
  '#earliestBtn',
  '#summaryTip',
  '#submitTip',
  '#subjectInfo',
  '.ra-class-details',
];

/** Switch to a mobile width and wait for the one breakpoint owner to settle. */
async function t23SetMobile(page: Page, width = 390, height = 844): Promise<void> {
  await page.setViewportSize({ width, height });
  await waitForMobile(page);
  await expect(page.locator('body')).toHaveClass(/arrangement-mobile-mode/);
  await expect(page.locator('#arrangementMobile')).toBeVisible();
}

/** Switch to a desktop width and wait for the layout to restore. */
async function t23SetDesktop(
  page: Page,
  width = T23_DESKTOP.width,
  height = T23_DESKTOP.height,
): Promise<void> {
  await page.setViewportSize({ width, height });
  await page.waitForFunction(() => !document.body.classList.contains('arrangement-mobile-mode'));
  await expect(page.locator('#arrangementMobile')).toBeHidden();
}

interface T23Layout {
  mobile: boolean;
  rootHidden: boolean;
  rootInert: boolean;
  rootDisplay: string;
  gridDisplay: string;
  gridHidden: boolean;
  gridInert: boolean;
  summaryDisplay: string;
  toolbarDisplay: string;
  legendDisplay: string;
  barVar: string;
  stickyVar: string;
}

async function t23Layout(page: Page): Promise<T23Layout> {
  return page.evaluate(() => {
    const root = document.getElementById('arrangementMobile') as HTMLElement | null;
    const grid = document.querySelector('.grid-wrapper') as HTMLElement | null;
    const summary = document.querySelector('.sel-summary') as HTMLElement | null;
    const toolbar = document.querySelector('.toolbar-restructure') as HTMLElement | null;
    const legend = document.querySelector('.legend-bar') as HTMLElement | null;
    const display = (node: HTMLElement | null) => (node ? getComputedStyle(node).display : 'missing');
    return {
      mobile: document.body.classList.contains('arrangement-mobile-mode'),
      rootHidden: root ? root.hidden : true,
      rootInert: root ? (root as any).inert === true : true,
      rootDisplay: display(root),
      gridDisplay: display(grid),
      gridHidden: grid ? grid.hidden : true,
      gridInert: grid ? (grid as any).inert === true : true,
      summaryDisplay: display(summary),
      toolbarDisplay: display(toolbar),
      legendDisplay: display(legend),
      barVar: (document.body.style.getPropertyValue('--arrangement-mobile-bar-height') || '').trim(),
      stickyVar: (document.body.style.getPropertyValue('--arrangement-mobile-sticky-height') || '').trim(),
    };
  });
}

/** Duplicate element ids are never allowed after relocation/restoration. */
async function t23DuplicateIds(page: Page): Promise<Array<{ id: string; count: number }>> {
  return page.evaluate(() => {
    const counts: Record<string, number> = {};
    document.querySelectorAll('[id]').forEach((node) => {
      const id = node.id;
      if (!id) return;
      counts[id] = (counts[id] || 0) + 1;
    });
    return Object.keys(counts)
      .filter((id) => counts[id] > 1)
      .map((id) => ({ id, count: counts[id] }));
  });
}

/** Tag the real relocated nodes with an identity + desktop position. */
async function t23MarkNodes(page: Page): Promise<void> {
  await page.evaluate((selectors: string[]) => {
    const marker = window as any;
    marker.__t23nodes = {};
    selectors.forEach((selector, index) => {
      const node = document.querySelector(selector) as any;
      if (!node || !node.parentNode) return;
      node.__t23identity = index;
      marker.__t23nodes[selector] = {
        index: Array.prototype.indexOf.call(node.parentNode.childNodes, node),
        parentTag: node.parentNode.tagName,
        parentId: node.parentNode.id || '',
        parentClass: String(node.parentNode.className || ''),
      };
    });
  }, T23_NODES.slice());
}

/** Read the same nodes back: identity, parent and sibling index. */
async function t23NodeReport(page: Page): Promise<any[]> {
  return page.evaluate((selectors: string[]) => {
    const state = (window as any).__t23nodes || {};
    return selectors.map((selector) => {
      const node = document.querySelector(selector) as any;
      if (!node || !node.parentNode) {
        return { selector, present: false, sameNode: false, identity: null, index: -1, parentTag: '', parentId: '', saved: state[selector] || null };
      }
      return {
        selector,
        present: true,
        sameNode: node.__t23identity !== undefined,
        identity: node.__t23identity === undefined ? null : Number(node.__t23identity),
        index: Array.prototype.indexOf.call(node.parentNode.childNodes, node),
        parentTag: node.parentNode.tagName,
        parentId: node.parentNode.id || '',
        parentClass: String(node.parentNode.className || ''),
        saved: state[selector] || null,
      };
    });
  }, T23_NODES.slice());
}

interface T23Values {
  subject: string;
  week: string;
  tipText: string;
  tipDataTip: string | null;
  slotText: string;
  venue: string;
  submitDisabled: boolean | null;
  tipInMobile: boolean;
}

async function t23Values(page: Page): Promise<T23Values> {
  return page.evaluate(() => {
    const root = document.getElementById('arrangementMobile');
    const subject = document.getElementById('subjectSelector') as HTMLSelectElement | null;
    const week = document.getElementById('weekSelector') as HTMLSelectElement | null;
    const tip = document.getElementById('summaryTip');
    const slot = document.getElementById('slotTriggerText');
    const submit = document.getElementById('submitRequestBtn') as HTMLButtonElement | null;
    const venue = document.querySelector('#buildingSelectorDropdown .venue-dd-label');
    return {
      subject: subject ? subject.value : '',
      week: week ? week.value : '',
      tipText: tip ? (tip.textContent || '').trim() : '',
      tipDataTip: tip ? tip.getAttribute('data-tip') : null,
      slotText: slot ? (slot.textContent || '').trim() : '',
      venue: venue ? (venue.textContent || '').trim() : '',
      submitDisabled: submit ? submit.disabled : null,
      tipInMobile: root && tip ? !!tip.closest('#arrangementMobile') : false,
    };
  });
}

interface T23Target {
  id: string;
  tag: string;
  width: number;
  height: number;
  text: string;
}

/** Every visible real control inside the active mobile root. */
async function t23Targets(page: Page): Promise<T23Target[]> {
  return page.evaluate(() => {
    const root = document.getElementById('arrangementMobile');
    if (!root) return [];
    const out: T23Target[] = [];
    root.querySelectorAll('button, select, summary').forEach((element) => {
      const node = element as HTMLElement;
      const style = getComputedStyle(node);
      if (style.display === 'none' || style.visibility === 'hidden') return;
      if (node.closest('dialog:not([open])')) return;
      const rect = node.getBoundingClientRect();
      if (rect.width < 1 || rect.height < 1) return;
      out.push({
        id: node.id || '',
        tag: node.tagName.toLowerCase(),
        width: Math.round(rect.width * 10) / 10,
        height: Math.round(rect.height * 10) / 10,
        text: (node.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 48),
      });
    });
    return out;
  });
}

interface T23Overflow {
  viewport: number;
  pageScrollWidth: number;
  bodyScrollWidth: number;
  timelineClientWidth: number;
  timelineScrollWidth: number;
  dayHostOverflowX: string;
  offenders: Array<{ tag: string; cls: string; right: number }>;
}

async function t23Overflow(page: Page): Promise<T23Overflow> {
  return page.evaluate(() => {
    const root = document.getElementById('arrangementMobile');
    const doc = document.documentElement;
    const timeline = root ? root.querySelector('[data-arrangement-mobile-timeline]') as HTMLElement | null : null;
    const dayHost = root ? root.querySelector('[data-arrangement-mobile-days]') as HTMLElement | null : null;
    const offenders: Array<{ tag: string; cls: string; right: number }> = [];
    if (root) {
      root.querySelectorAll('*').forEach((element) => {
        const node = element as HTMLElement;
        if (node.closest('[data-arrangement-mobile-days]')) return; // intentional bounded scroller
        if (node.closest('dialog:not([open])')) return;
        const style = getComputedStyle(node);
        if (style.display === 'none' || style.visibility === 'hidden') return;
        const rect = node.getBoundingClientRect();
        if (rect.width < 1 && rect.height < 1) return;
        if (rect.right > window.innerWidth + 1 || rect.left < -1) {
          offenders.push({ tag: node.tagName.toLowerCase(), cls: String(node.className || '').slice(0, 60), right: Math.round(rect.right) });
        }
      });
    }
    return {
      viewport: window.innerWidth,
      pageScrollWidth: doc.scrollWidth,
      bodyScrollWidth: document.body.scrollWidth,
      timelineClientWidth: timeline ? timeline.clientWidth : 0,
      timelineScrollWidth: timeline ? timeline.scrollWidth : 0,
      dayHostOverflowX: dayHost ? getComputedStyle(dayHost).overflowX : 'missing',
      offenders: offenders.slice(0, 8),
    };
  });
}

/** Horizontal clipping of the text that must stay readable (wrapping). */
async function t23Clipping(page: Page): Promise<Array<{ label: string; clipped: boolean; scrollWidth: number; clientWidth: number }>> {
  return page.evaluate(() => {
    const plan: Array<[string, string]> = [
      ['day-heading', '[data-arrangement-mobile-day-heading]'],
      ['summary-title', '[data-arrangement-mobile-summary-host] .arrangement-mobile-summary-title'],
      ['summary-meta', '[data-arrangement-mobile-summary-host] .arrangement-mobile-summary-meta'],
      ['legend', '[data-arrangement-mobile-legend]'],
    ];
    const out: Array<{ label: string; clipped: boolean; scrollWidth: number; clientWidth: number }> = [];
    plan.forEach(([label, selector]) => {
      const node = document.querySelector(selector) as HTMLElement | null;
      if (!node) return;
      out.push({
        label,
        clipped: node.scrollWidth > node.clientWidth + 1,
        scrollWidth: node.scrollWidth,
        clientWidth: node.clientWidth,
      });
    });
    return out;
  });
}

/** The compact slot trigger may ellipsize; its full facts must stay available. */
async function t23SlotTriggerFacts(page: Page): Promise<{ text: string; tip: string; clipped: boolean; hasFullFacts: boolean } | null> {
  return page.evaluate(() => {
    const node = document.getElementById('slotTriggerText') as HTMLElement | null;
    if (!node) return null;
    const tip = node.getAttribute('data-tip') || '';
    return {
      text: (node.textContent || '').trim(),
      tip,
      clipped: node.scrollWidth > node.clientWidth + 1,
      hasFullFacts: /Week\s+\d+/.test(tip) && tip.includes('~'),
    };
  });
}

async function t23BarMeasurement(page: Page): Promise<{ css: string; measured: number }> {
  return page.evaluate(() => {
    const bar = document.querySelector('[data-arrangement-mobile-actions]') as HTMLElement | null;
    return {
      css: (document.body.style.getPropertyValue('--arrangement-mobile-bar-height') || '').trim(),
      measured: bar ? Math.ceil(bar.getBoundingClientRect().height) : 0,
    };
  });
}

interface T23SpanGeometry {
  slotCount: number;
  height: number;
  top: number;
  bottom: number;
  gridRow: string;
  borderWidth: string;
  borderColor: string;
  rowMinHeight: string;
  rowHeight: number;
  beforeGap: number | null;
  afterGap: number | null;
  scrollMarginTop: string;
  scrollMarginBottom: string;
  stickyVar: string;
  barVar: string;
}

async function t23SpanGeometry(page: Page): Promise<T23SpanGeometry | null> {
  return page.evaluate(() => {
    const span = document.querySelector(
      '[data-arrangement-mobile-timeline] [data-mobile-row="span"]',
    ) as HTMLElement | null;
    if (!span) return null;
    const timeline = span.closest('[data-arrangement-mobile-timeline]') as HTMLElement;
    const rows = Array.from(timeline.querySelectorAll('[data-mobile-row="hour"]')) as HTMLElement[];
    const start = Number(span.getAttribute('data-mobile-hour'));
    const before = rows.filter((row) => Number(row.getAttribute('data-mobile-hour')) < start).pop() || null;
    const after = rows.find((row) => Number(row.getAttribute('data-mobile-hour')) > start) || null;
    const rect = span.getBoundingClientRect();
    const style = getComputedStyle(span);
    const rowStyle = rows[0] ? getComputedStyle(rows[0]) : null;
    return {
      slotCount: Number(span.getAttribute('data-mobile-span')),
      height: Math.round(rect.height * 10) / 10,
      top: rect.top,
      bottom: rect.bottom,
      gridRow: style.gridRow || '',
      borderWidth: style.borderTopWidth,
      borderColor: style.borderTopColor,
      rowMinHeight: rowStyle ? rowStyle.minHeight : '',
      rowHeight: rows[0] ? Math.round(rows[0].getBoundingClientRect().height * 10) / 10 : 0,
      beforeGap: before ? Math.round((rect.top - before.getBoundingClientRect().bottom) * 10) / 10 : null,
      afterGap: after ? Math.round((after.getBoundingClientRect().top - rect.bottom) * 10) / 10 : null,
      scrollMarginTop: style.scrollMarginTop,
      scrollMarginBottom: style.scrollMarginBottom,
      stickyVar: (document.body.style.getPropertyValue('--arrangement-mobile-sticky-height') || '').trim(),
      barVar: (document.body.style.getPropertyValue('--arrangement-mobile-bar-height') || '').trim(),
    };
  });
}

interface T23OverlayCheck {
  firstClear: boolean;
  lastClear: boolean;
  firstHit: boolean;
  lastHit: boolean;
  stickyBottom: number;
  firstTop: number;
  lastBottom: number;
  barTop: number;
}

/** First/last row must be reachable and clear of the sticky cluster/bar. */
async function t23OverlayCheck(page: Page): Promise<T23OverlayCheck | null> {
  return page.evaluate(() => {
    const bar = document.querySelector('[data-arrangement-mobile-actions]') as HTMLElement | null;
    const sticky = document.querySelector('[data-arrangement-mobile-controls]') as HTMLElement | null;
    const rows = Array.from(document.querySelectorAll(
      '[data-arrangement-mobile-timeline] [data-mobile-row="hour"], [data-arrangement-mobile-timeline] [data-mobile-row="span"]',
    )) as HTMLElement[];
    if (!bar || !sticky || !rows.length) return null;
    const first = rows[0];
    const last = rows[rows.length - 1];
    const hit = (node: HTMLElement) => {
      const rect = node.getBoundingClientRect();
      const x = Math.min(Math.max(rect.left + rect.width / 2, 0), window.innerWidth - 1);
      const y = Math.min(Math.max(rect.top + rect.height / 2, 0), window.innerHeight - 1);
      const element = document.elementFromPoint(x, y);
      return !!(element && node.contains(element));
    };
    first.scrollIntoView({ block: 'center' });
    const firstRect = first.getBoundingClientRect();
    const stickyRect = sticky.getBoundingClientRect();
    const firstHit = hit(first);
    last.scrollIntoView({ block: 'center' });
    const lastRect = last.getBoundingClientRect();
    const barRect = bar.getBoundingClientRect();
    const lastHit = hit(last);
    return {
      firstClear: firstRect.top >= stickyRect.bottom - 1,
      lastClear: lastRect.bottom <= barRect.top + 1,
      firstHit,
      lastHit,
      stickyBottom: stickyRect.bottom,
      firstTop: firstRect.top,
      lastBottom: lastRect.bottom,
      barTop: barRect.top,
    };
  });
}

async function t23ToastBarCheck(page: Page): Promise<{ visible: boolean; above: boolean; toastBottom: number; barTop: number } | null> {
  return page.evaluate(() => {
    const toastBar = document.getElementById('toastBar');
    const actions = document.querySelector('[data-arrangement-mobile-actions]') as HTMLElement | null;
    if (!toastBar || !actions) return null;
    const toastRect = toastBar.getBoundingClientRect();
    const barRect = actions.getBoundingClientRect();
    return {
      visible: toastBar.classList.contains('visible'),
      above: toastRect.bottom <= barRect.top + 1,
      toastBottom: toastRect.bottom,
      barTop: barRect.top,
    };
  });
}

async function t23HitTest(page: Page, selector: string): Promise<{ inside: boolean; hit: string } | null> {
  return page.evaluate((sel) => {
    const node = document.querySelector(sel) as HTMLElement | null;
    if (!node) return null;
    const rect = node.getBoundingClientRect();
    const x = Math.min(Math.max(rect.left + rect.width / 2, 0), window.innerWidth - 1);
    const y = Math.min(Math.max(rect.top + rect.height / 2, 0), window.innerHeight - 1);
    const element = document.elementFromPoint(x, y) as HTMLElement | null;
    return {
      inside: !!(element && node.contains(element)),
      hit: element ? element.tagName.toLowerCase() + (element.id ? '#' + element.id : '') : '',
    };
  }, selector);
}

interface T23ContrastSample {
  label: string;
  fg: string;
  bg: string;
  ratio: number;
}

/** Computed-colour contrast samples (an automated floor, not a WCAG audit). */
async function t23ContrastSamples(page: Page): Promise<{ theme: string; samples: T23ContrastSample[] }> {
  return page.evaluate(() => {
    const parse = (value: string) => {
      const match = /rgba?\(([^)]+)\)/.exec(value || '');
      if (!match) return null;
      const parts = match[1].split(',').map((part) => Number(part.trim()));
      if (parts.length < 3 || parts.some((part) => Number.isNaN(part))) return null;
      return { r: parts[0], g: parts[1], b: parts[2], a: parts.length > 3 ? parts[3] : 1 };
    };
    const luminance = (colour: { r: number; g: number; b: number }) => {
      const channel = (value: number) => {
        const scaled = value / 255;
        return scaled <= 0.03928 ? scaled / 12.92 : Math.pow((scaled + 0.055) / 1.055, 2.4);
      };
      return 0.2126 * channel(colour.r) + 0.7152 * channel(colour.g) + 0.0722 * channel(colour.b);
    };
    const effectiveBackground = (node: HTMLElement) => {
      let cursor: HTMLElement | null = node;
      while (cursor) {
        const background = parse(getComputedStyle(cursor).backgroundColor);
        if (background && background.a > 0.95) return background;
        cursor = cursor.parentElement;
      }
      return { r: 255, g: 255, b: 255, a: 1 };
    };
    const ratio = (a: { r: number; g: number; b: number }, b: { r: number; g: number; b: number }) => {
      const left = luminance(a);
      const right = luminance(b);
      return (Math.max(left, right) + 0.05) / (Math.min(left, right) + 0.05);
    };
    const samples: T23ContrastSample[] = [];
    const add = (label: string, selector: string) => {
      const node = document.querySelector(selector) as HTMLElement | null;
      if (!node) return;
      const style = getComputedStyle(node);
      const foreground = parse(style.color);
      if (!foreground) return;
      const background = effectiveBackground(node);
      samples.push({
        label,
        fg: style.color,
        bg: 'rgb(' + background.r + ', ' + background.g + ', ' + background.b + ')',
        ratio: Math.round(ratio(foreground, background) * 100) / 100,
      });
    };
    add('day-heading', '[data-arrangement-mobile-day-heading]');
    add('row-time', '[data-arrangement-mobile-timeline] .arrangement-mobile-row-time');
    add('row-state', '[data-arrangement-mobile-timeline] .arrangement-mobile-row-state');
    add('summary-title', '[data-arrangement-mobile-summary-host] .arrangement-mobile-summary-title');
    add('legend', '[data-arrangement-mobile-legend]');
    return { theme: String(document.documentElement.className || ''), samples };
  });
}

/** The page-local stylesheet must still declare safe-area insets + viewport-fit. */
async function t23EnvDeclarations(page: Page): Promise<{ css: string; viewportMeta: string }> {
  return page.evaluate(() => {
    const chunks: string[] = [];
    Array.from(document.styleSheets).forEach((sheet) => {
      let rules: CSSRuleList | null = null;
      try {
        rules = sheet.cssRules;
      } catch (error) {
        return; // cross-origin/opaque sheet: not the page-local CSS
      }
      const walk = (list: CSSRuleList) => {
        Array.from(list).forEach((rule) => {
          const anyRule = rule as any;
          if (anyRule.cssRules) walk(anyRule.cssRules as CSSRuleList);
          chunks.push(String(anyRule.cssText || ''));
        });
      };
      if (rules) walk(rules);
    });
    const meta = document.querySelector('meta[name="viewport"]');
    return { css: chunks.join('\n'), viewportMeta: meta ? meta.getAttribute('content') || '' : '' };
  });
}

interface T23FocusStop {
  tag: string;
  id: string;
  inMobile: boolean;
  hiddenLayout: boolean;
  visible: boolean;
  isBody: boolean;
}

/** Tab trail: which real controls are reachable right now. */
async function t23FocusTrail(page: Page, steps: number): Promise<T23FocusStop[]> {
  const trail: T23FocusStop[] = [];
  for (let step = 0; step < steps; step += 1) {
    await page.keyboard.press('Tab');
    trail.push(await page.evaluate(() => {
      const active = document.activeElement as HTMLElement | null;
      if (!active || active === document.body) {
        return { tag: 'body', id: '', inMobile: false, hiddenLayout: false, visible: false, isBody: true };
      }
      return {
        tag: active.tagName.toLowerCase(),
        id: active.id || '',
        inMobile: !!active.closest('#arrangementMobile'),
        hiddenLayout: !!active.closest('.toolbar-restructure, .sel-summary, .grid-wrapper, .legend-bar'),
        visible: !!(active.offsetWidth || active.offsetHeight || active.getClientRects().length),
        isBody: false,
      };
    }));
  }
  return trail;
}

async function t23ObserveLive(page: Page): Promise<void> {
  await page.evaluate(() => {
    const live = document.querySelector('[data-arrangement-mobile-live]');
    const marker = window as any;
    marker.__t23live = [];
    if (!live) return;
    if (marker.__t23liveObserver) marker.__t23liveObserver.disconnect();
    marker.__t23liveObserver = new MutationObserver(() => {
      const text = (live.textContent || '').trim();
      if (text) marker.__t23live.push(text);
    });
    marker.__t23liveObserver.observe(live, { childList: true, characterData: true, subtree: true });
  });
}

async function t23LiveMessages(page: Page): Promise<string[]> {
  return page.evaluate(() => (((window as any).__t23live || []) as string[]).slice());
}

/** Count real showModal()/close() calls (a repeated activation is visible). */
async function t23InstrumentDialog(page: Page): Promise<void> {
  await page.evaluate(() => {
    const marker = window as any;
    marker.__t23dialog = { shows: 0, closes: 0 };
    const proto = HTMLDialogElement.prototype as any;
    if (proto.__t23patched === true) return;
    const show = proto.showModal;
    const close = proto.close;
    proto.showModal = function (...args: any[]) {
      (window as any).__t23dialog.shows += 1;
      return show.apply(this, args);
    };
    proto.close = function (...args: any[]) {
      (window as any).__t23dialog.closes += 1;
      return close.apply(this, args);
    };
    proto.__t23patched = true;
  });
}

async function t23DialogCounters(page: Page): Promise<{ shows: number; closes: number }> {
  return page.evaluate(() => ({ ...((window as any).__t23dialog || { shows: 0, closes: 0 }) }));
}

/** Bubble-phase key probe: shows what the page left unprevented. */
async function t23InstallKeyProbe(page: Page): Promise<void> {
  await page.evaluate(() => {
    const marker = window as any;
    marker.__t23keys = [];
    document.addEventListener('keydown', (event) => {
      const target = event.target as HTMLElement | null;
      marker.__t23keys.push({
        key: event.key,
        prevented: event.defaultPrevented,
        target: target ? (target.id || target.tagName) : '',
      });
    });
  });
}

async function t23KeyProbe(page: Page): Promise<Array<{ key: string; prevented: boolean; target: string }>> {
  return page.evaluate(() => (((window as any).__t23keys || []) as any[]).slice());
}

/**
 * Count addEventListener/removeEventListener calls by target label + type.
 * Added before navigation so the adapter's mount-time binding is observed;
 * a re-bind after resize would change the count.
 */
async function t23InstallListenerProbe(page: Page): Promise<void> {
  await page.addInitScript(() => {
    const marker = window as any;
    const state = {
      adds: {} as Record<string, number>,
      removes: {} as Record<string, number>,
    };
    marker.__t23listeners = state;
    const label = (target: any) => {
      if (target === window) return 'window';
      if (target === document) return 'document';
      if (target && target.id) return '#' + target.id;
      if (target && target.constructor && target.constructor.name) return target.constructor.name;
      return 'unknown';
    };
    const bump = (bucket: Record<string, number>, key: string) => {
      bucket[key] = (bucket[key] || 0) + 1;
    };
    const proto = EventTarget.prototype as any;
    const add = proto.addEventListener;
    const remove = proto.removeEventListener;
    proto.addEventListener = function (type: string, ...rest: any[]) {
      bump(state.adds, label(this) + ':' + type);
      return add.call(this, type, ...rest);
    };
    proto.removeEventListener = function (type: string, ...rest: any[]) {
      bump(state.removes, label(this) + ':' + type);
      return remove.call(this, type, ...rest);
    };
  });
}

async function t23ListenerCounts(page: Page): Promise<{ adds: Record<string, number>; removes: Record<string, number> }> {
  return page.evaluate(() => JSON.parse(JSON.stringify((window as any).__t23listeners || { adds: {}, removes: {} })));
}

interface T23ActiveState {
  tag: string;
  id: string;
  inDialog: boolean;
  inMobile: boolean;
  isDayButton: boolean;
  isSummaryOpener: boolean;
  isInfoClose: boolean;
  dayIndex: string;
}

async function t23Active(page: Page): Promise<T23ActiveState> {
  return page.evaluate(() => {
    const active = document.activeElement as HTMLElement | null;
    if (!active || active === document.body) {
      return { tag: 'body', id: '', inDialog: false, inMobile: false, isDayButton: false, isSummaryOpener: false, isInfoClose: false, dayIndex: '' };
    }
    return {
      tag: active.tagName.toLowerCase(),
      id: active.id || '',
      inDialog: !!active.closest('#arrangementMobileInfo'),
      inMobile: !!active.closest('#arrangementMobile'),
      isDayButton: active.matches('[data-arrangement-mobile-days] [data-day-index]'),
      isSummaryOpener: active.matches('[data-arrangement-mobile-summary-opener]'),
      isInfoClose: active.hasAttribute('data-arrangement-mobile-info-close'),
      dayIndex: active.getAttribute('data-day-index') || '',
    };
  });
}

/* ── T23c timer helpers (page-context fake clock; no production edit) ── */

function t23RequireTimerClock(page: Page): void {
  if (!page.clock || typeof (page.clock as any).install !== 'function') {
    test.skip(
      true,
      'Playwright page.clock.install is unavailable in this runner; the 5000ms timer cases are blocked, not passed',
    );
  }
}

/**
 * Install the fake clock AFTER the page has booted and the selection exists,
 * so only the toast/sheet timers authored below are driven. Installation after
 * navigation is intentional: freezing timers before boot could starve Livewire.
 */
async function t23InstallClock(page: Page): Promise<void> {
  t23RequireTimerClock(page);
  await page.clock.install();
}

async function t23Forward(page: Page, milliseconds: number): Promise<void> {
  await page.clock.fastForward(milliseconds);
}

async function t23ToastState(page: Page): Promise<{ visible: boolean; message: string }> {
  return page.evaluate(() => {
    const bar = document.getElementById('toastBar');
    if (!bar) return { visible: false, message: '' };
    return {
      visible: bar.classList.contains('visible'),
      message: ((bar.querySelector('.toast-message')?.textContent) || '').trim(),
    };
  });
}

/**
 * Dispatch a real DOM click through the page's own delegated listener without
 * Playwright's actionability frame wait. Needed once the fake clock has paused
 * requestAnimationFrame, where a locator.click() could wait forever.
 */
async function t23DomClick(page: Page, selector: string): Promise<boolean> {
  return page.evaluate((sel) => {
    const node = document.querySelector(sel) as HTMLElement | null;
    if (!node) return false;
    node.click();
    return true;
  }, selector);
}

/** A second free row outside the saved span in the currently viewed day. */
async function t23OtherGreenHour(page: Page, day: number, hour: number, span: number): Promise<number | null> {
  return page.evaluate(({ d, h, s }) => {
    const rows = Array.from(document.querySelectorAll(
      '[data-arrangement-mobile-timeline] [data-mobile-row="hour"]',
    ));
    for (const row of rows) {
      if (row.getAttribute('data-mobile-state') !== 'available') continue;
      if (Number(row.getAttribute('data-mobile-day')) !== d) continue;
      const candidate = Number(row.getAttribute('data-mobile-hour'));
      if (candidate >= h && candidate < h + s) continue;
      return candidate;
    }
    return null;
  }, { d: day, h: hour, s: span });
}

async function t23SelectedCanonicalCount(page: Page): Promise<number> {
  return page.evaluate(() => document.querySelectorAll('#tableBody .cell-content.cell-selected').length);
}

/* ─────────────────────────────────────────────────────────────────────────
 * T23a — native keyboard, sheet focus and announcements
 * (MA-12a–e, MA-07e, MA-14a)
 * ───────────────────────────────────────────────────────────────────────── */
test.describe('T23a — mobile keyboard, sheet focus and announcements', () => {
  test.beforeEach(async ({ page }) => { await login(page); });

  test('Enter and Space activate day/slot/tools controls once and never reach the hidden grid', async ({ page }) => {
    await gotoArrangement(page);
    await t23InstrumentDialog(page);
    await t23InstallKeyProbe(page);

    const days = await visibleDayIndexes(page);
    test.skip(days.length < 2, 'the bookable week offers fewer than two eligible days; the once-per-activation check needs two');

    /* Day button: one native Enter = one press, one announcement. */
    const pressed = await pressedDay(page);
    const firstTarget = days.find((day) => day !== pressed) ?? days[0];
    await t23ObserveLive(page);
    const firstButton = page.locator(`[data-arrangement-mobile-days] button[data-day-index="${firstTarget}"]`);
    await firstButton.focus();
    await page.keyboard.press('Enter');
    await expect(firstButton).toHaveAttribute('aria-pressed', 'true');
    const firstMessages = await t23LiveMessages(page);
    expect(firstMessages).toHaveLength(1);
    expect(firstMessages[0]).toMatch(/^Showing /);

    const enterProbe = (await t23KeyProbe(page)).filter((entry) => entry.key === 'Enter');
    expect(enterProbe.length, 'the native Enter keydown reached the document bubble phase').toBeGreaterThan(0);
    expect(enterProbe.every((entry) => entry.prevented === false), 'mobile never preventDefaults a native button key').toBe(true);
    await expect(page.locator('#tableBody .cell-content.cell-focused')).toHaveCount(0);
    await expectNoSelection(page);

    /* Space on another day: exactly one more announcement. */
    const secondTarget = days.find((day) => day !== firstTarget) ?? firstTarget;
    if (secondTarget !== firstTarget) {
      const secondButton = page.locator(`[data-arrangement-mobile-days] button[data-day-index="${secondTarget}"]`);
      await secondButton.focus();
      await page.keyboard.press('Space');
      await expect(secondButton).toHaveAttribute('aria-pressed', 'true');
      expect((await t23LiveMessages(page)).length, 'Space produced exactly one more announcement').toBe(2);
    } else {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'only one distinct eligible day existed; the Space half of the native-activation check was not exercised',
      });
    }

    /* Original-slot trigger: Enter opens once, Enter again closes once.
       The trigger is hidden until a subject exists, so open the context and
       commit the first real option first (plain arrival has no subject). */
    const context = page.locator('[data-arrangement-mobile-context]');
    if (!(await context.evaluate((el) => (el as HTMLDetailsElement).open))) {
      await page.locator('[data-arrangement-mobile-context-summary]').click();
    }
    const subjectOptions = page.locator('#subjectSelector option');
    if (await subjectOptions.count() > 1) {
      const subjectValue = await subjectOptions.nth(1).getAttribute('value');
      await page.locator('#subjectSelector').selectOption(subjectValue!);
      await expect(page.locator('#slotPicker')).toBeVisible();
      if (await page.locator('#slotPanel').evaluate((el) => el.classList.contains('open'))) {
        await page.mouse.click(5, 5);
        await expect(page.locator('#slotPanel')).not.toHaveClass(/open/);
      }
      await page.locator('#slotTrigger').focus();
      await page.keyboard.press('Enter');
      await expect(page.locator('#slotPanel')).toHaveClass(/open/);
      await page.keyboard.press('Enter');
      await expect(page.locator('#slotPanel')).not.toHaveClass(/open/);
    } else {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'no subject option existed; the slot-trigger native-key half was not exercised',
      });
    }

    /* Native select keys are not hijacked: ArrowDown reaches the select and is
       never preventDefaulted. Chromium may open the popup instead of committing
       immediately, so a value change is reported, not demanded. */
    const offered = await offeredWeekIndexes(page);
    const weekBefore = await currentWeekIndex(page);
    await page.locator('#weekSelector').focus();
    await page.keyboard.press('ArrowDown');
    const arrowProbe = (await t23KeyProbe(page)).filter((entry) => entry.key === 'ArrowDown');
    expect(arrowProbe.length, 'ArrowDown reached the document').toBeGreaterThan(0);
    expect(arrowProbe.every((entry) => entry.prevented === false),
      'the mobile page never preventDefaults native select arrows').toBe(true);
    if (offered.length > 1 && (await currentWeekIndex(page)) === weekBefore) {
      test.info().annotations.push({
        type: 'environment-gap',
        description: 'this browser kept the native select popup open on ArrowDown rather than committing; '
          + 'the unprevented-key assertion still proves the legacy grid handler did not take it',
      });
      await page.keyboard.press('Escape');
    }
    await expect(page.locator('#tableBody .cell-content')).not.toHaveCount(0);

    /* Tools control: one showModal for one Enter, one close. */
    await page.locator('[data-arrangement-mobile-context-summary]').click();
    const before = await t23DialogCounters(page);
    await page.locator('[data-arrangement-mobile-legend]').focus();
    await page.keyboard.press('Enter');
    await openInfo(page);
    await expect.poll(async () => (await t23DialogCounters(page)).shows).toBe(before.shows + 1);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Colours & borders');
    await page.keyboard.press('Escape');
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    /* Native Escape dismisses through the dialog's own cancel/close event, not
       a scripted dialog.close(); hidden + focus return are the real receipt. */
    await expect.poll(async () => page.evaluate(() =>
      document.activeElement === document.querySelector('[data-arrangement-mobile-legend]')),
    { message: 'Escape returns focus to the legend opener' }).toBe(true);

    /* Keyboard Close (the initial focus target) dismisses and restores too. */
    await page.locator('[data-arrangement-mobile-legend]').focus();
    await page.keyboard.press('Enter');
    await openInfo(page);
    expect((await t23Active(page)).isInfoClose, 'the Close button owns initial focus').toBe(true);
    await page.keyboard.press('Enter');
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    await expect.poll(async () => page.evaluate(() =>
      document.activeElement === document.querySelector('[data-arrangement-mobile-legend]')),
    { message: 'Close returns focus to the legend opener' }).toBe(true);

    /* Legacy hidden-grid focus cannot select another cell. */
    const focusResult = await page.evaluate(() => {
      const cell = document.querySelector('#tableBody td[data-day="0"][data-hour="0"] .cell-content') as HTMLElement | null;
      if (!cell) return 'missing';
      cell.setAttribute('tabindex', '0');
      cell.focus();
      return document.activeElement === cell ? 'focused' : 'refused';
    });
    expect(['missing', 'refused']).toContain(focusResult);
    await page.keyboard.press('Enter');
    await page.keyboard.press('Space');
    expect(await t23SelectedCanonicalCount(page), 'no canonical cell was selected from the hidden grid').toBe(0);
    await expect(page.locator('#tableBody .cell-content.cell-focused')).toHaveCount(0);
    await expectNoSelection(page);
  });

  test('a sheet takes focus, contains Tab, persists past five seconds and returns focus on Escape and Remove', async ({ page }) => {
    const selection = await t22CreateSelection(page);
    await t23InstrumentDialog(page);

    /* Open from the summary opener with the keyboard. */
    const opener = page.locator('[data-arrangement-mobile-summary-opener]');
    await opener.focus();
    await page.keyboard.press('Enter');
    await openInfo(page);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Saved selection');

    const initial = await t23Active(page);
    expect(initial.inDialog, 'focus enters the named dialog on open').toBe(true);
    expect(initial.isInfoClose, 'the Close button is the explicit initial target').toBe(true);

    /* Native dialog containment: Tab/Shift+Tab never leave the dialog. */
    for (let step = 0; step < 6; step += 1) {
      await page.keyboard.press('Tab');
      expect((await t23Active(page)).inDialog, `Tab ${step + 1} stays inside the dialog`).toBe(true);
    }
    for (let step = 0; step < 3; step += 1) {
      await page.keyboard.press('Shift+Tab');
      expect((await t23Active(page)).inDialog, `Shift+Tab ${step + 1} stays inside the dialog`).toBe(true);
    }

    /* Information survives well beyond the transient five-second window. */
    await page.waitForTimeout(5_600);
    expect(await infoOpen(page), 'the details sheet is persistent, not a five-second toast').toBe(true);
    await page.keyboard.press('Escape');
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    await expect.poll(async () => (await t23Active(page)).isSummaryOpener, { timeout: 10_000 })
      .toBe(true);
    await expect.poll(async () => (await t23Active(page)).inMobile, { timeout: 10_000 }).toBe(true);

    /* Re-open and Remove with the keyboard: the rebuilt bar has no opener, so
       focus must land on the corresponding rebuilt control (current day). */
    await opener.focus();
    await page.keyboard.press('Enter');
    await openInfo(page);
    const remove = page.locator('[data-arrangement-mobile-info-actions] button', { hasText: 'Remove selection' });
    await expect(remove).toHaveCount(1);
    await remove.focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    await expectNoSelection(page);
    await expect.poll(async () => (await t23Active(page)).inMobile, { timeout: 10_000 })
      .toBe(true);
    await expect.poll(async () => (await t23Active(page)).isDayButton, { timeout: 10_000 })
      .toBe(true);
    expect(await page.evaluate(() => document.querySelectorAll('#tableBody .cell-content.cell-selected').length),
      'removal cleared the saved canonical cells').toBe(0);
    test.info().annotations.push({
      type: 'data-note',
      description: `focus-return receipt used the saved entry in week ${selection.week + 1}. ` +
        'The rebuilt-opener fallback is the current day button because the emptied bar has no details opener.',
    });
  });

  test('legacy shortcuts stay inert on mobile with and without a sheet, and critical Escape still closes', async ({ page }) => {
    await t22CreateSelection(page);
    await t23InstrumentDialog(page);
    await t23InstallKeyProbe(page);
    const weekBefore = await currentWeekIndex(page);
    const venueBefore = venueCodeOf(await venueLabel(page));
    const pressedBefore = await pressedDay(page);
    const spanCount = await page.locator('[data-mobile-row="span"]').count();
    expect(spanCount).toBe(1);
    const selectedCells = await t23SelectedCanonicalCount(page);
    expect(selectedCells).toBeGreaterThan(0);

    const helpActive = () => page.evaluate(() => {
      const overlay = document.getElementById('helpOverlay');
      return !!(overlay && overlay.classList.contains('active'));
    });

    /* No sheet: [ and ] are stopped by the capture-phase guard; the rest reach
       the page handler and return before preventDefault on mobile. */
    await page.keyboard.press('[');
    await page.keyboard.press(']');
    expect(await currentWeekIndex(page)).toBe(weekBefore);
    const bracketProbe = (await t23KeyProbe(page)).filter((entry) => entry.key === '[' || entry.key === ']');
    expect(bracketProbe, 'the shared week shortcuts never reach the bubble probe').toHaveLength(0);

    for (const key of ['?', 'Control+z', 'Control+1', 'ArrowDown']) {
      await page.keyboard.press(key);
    }
    const mobileProbe = (await t23KeyProbe(page)).filter((entry) =>
      entry.key === '?' || entry.key === 'z' || entry.key === '1' || entry.key === 'ArrowDown');
    expect(mobileProbe.length, 'the legacy keys reached the document handler').toBeGreaterThan(0);
    expect(mobileProbe.every((entry) => entry.prevented === false),
      'mobile does not preventDefault native keys it does not own').toBe(true);
    expect(await helpActive(), 'no help overlay on mobile').toBe(false);
    expect(await currentWeekIndex(page)).toBe(weekBefore);
    expect(venueCodeOf(await venueLabel(page))).toBe(venueBefore);
    expect(await pressedDay(page)).toBe(pressedBefore);
    expect(await t23SelectedCanonicalCount(page)).toBe(selectedCells);

    /* With the information sheet open the background is fully isolated. */
    await page.locator('[data-arrangement-mobile-legend]').click();
    await openInfo(page);
    const sheetShows = (await t23DialogCounters(page)).shows;
    for (const key of [']', '?', 'Control+z', 'Control+1', '[']) {
      await page.keyboard.press(key);
    }
    expect(await infoOpen(page), 'the sheet stays open').toBe(true);
    expect((await t23DialogCounters(page)).shows).toBe(sheetShows);
    expect(await helpActive()).toBe(false);
    expect(await currentWeekIndex(page)).toBe(weekBefore);
    expect(venueCodeOf(await venueLabel(page))).toBe(venueBefore);
    expect(await t23SelectedCanonicalCount(page)).toBe(selectedCells);
    await page.keyboard.press('Escape');
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();

    /* Critical modal Escape keeps its existing close path and data. */
    await expect(page.locator('#submitRequestBtn')).toBeEnabled({ timeout: 15_000 });
    await page.locator('#submitRequestBtn').click();
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#modalTitle')).toHaveText('Confirm Your Selection');
    await page.keyboard.press('Escape');
    await expect(page.locator('#confirmModal')).toBeHidden();
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
    expect(await t23SelectedCanonicalCount(page)).toBe(selectedCells);

    /* Desktop keyboard handling is unchanged: a week key moves the week and ?
       opens help. A single offered week cannot prove the week key, so it is
       annotated rather than claimed. */
    await t23SetDesktop(page);
    const desktopWeeks = await offeredWeekIndexes(page);
    if (desktopWeeks.length > 1) {
      const desktopWeek = await currentWeekIndex(page);
      await page.keyboard.press(']');
      if ((await currentWeekIndex(page)) === desktopWeek) await page.keyboard.press('[');
      await expect.poll(async () => currentWeekIndex(page), { timeout: 10_000 }).not.toBe(desktopWeek);
    } else {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'only one week is offered on desktop; the desktop week-shortcut half was not exercised',
      });
    }
    /* Park focus on the desktop grid, not a select/input left over from the
       mobile interaction: the page's form-control guard intentionally ignores
       shortcuts while a select/input/textarea owns focus. */
    await page.locator('#tableBody .cell-content').first().focus();
    await page.keyboard.press('?');
    expect(await helpActive(), 'the desktop ? shortcut still opens help').toBe(true);
    await page.keyboard.press('Escape');
    expect(await helpActive(), 'Escape still closes the help overlay').toBe(false);
  });

  test('day, slot and selection changes speak once in the polite region and never repeat on resize', async ({ page }) => {
    const selection = await t22CreateSelection(page);

    const liveAttributes = await page.evaluate(() => {
      const live = document.querySelector('[data-arrangement-mobile-live]');
      return live
        ? { live: live.getAttribute('aria-live'), atomic: live.getAttribute('aria-atomic'), text: (live.textContent || '').trim() }
        : null;
    });
    expect(liveAttributes).not.toBeNull();
    expect(liveAttributes!.live).toBe('polite');
    expect(liveAttributes!.atomic).toBe('true');

    /* Slot buttons identify time + state; the date is the labelled day heading. */
    const slotLabels = await page.evaluate(() => Array.from(
      document.querySelectorAll('[data-arrangement-mobile-timeline] [data-mobile-row="hour"]'),
    ).slice(0, 3).map((row) => ({
      label: row.getAttribute('aria-label') || '',
      state: (row.querySelector('.arrangement-mobile-row-state')?.textContent || '').trim(),
    })));
    expect(slotLabels.length).toBeGreaterThan(0);
    for (const slot of slotLabels) {
      expect(slot.label).toMatch(/^\d{2}:\d{2}\u2013\d{2}:\d{2}, .+$/);
      expect(slot.label.endsWith(slot.state), 'the row name carries its canonical state').toBe(true);
    }
    const heading = await dayHeading(page);
    expect(heading).toMatch(/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun), \d{1,2} [A-Z][a-z]{2} \d{4}$/);

    await t23ObserveLive(page);

    /* One real day change = one message; repeating the same day is silent. */
    const days = await visibleDayIndexes(page);
    const pressed = await pressedDay(page);
    const target = days.find((day) => day !== pressed);
    if (target !== undefined) {
      const button = page.locator(`[data-arrangement-mobile-days] button[data-day-index="${target}"]`);
      await button.click();
      await expect(button).toHaveAttribute('aria-pressed', 'true');
      expect((await t23LiveMessages(page)).length, 'a day change is announced once').toBe(1);
      await button.click();
      expect((await t23LiveMessages(page)).length, 're-selecting the same day is silent').toBe(1);
    } else {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'no second eligible day existed to trigger a day-change announcement',
      });
    }

    /* Resizes alone must not re-announce; a real week rebuild may legitimately
       announce a fallback viewed day, so its message count is captured after
       the rebuild and only the removal is then asserted as a new message. */
    const beforeResize = (await t23LiveMessages(page)).length;
    await t23SetMobile(page, 320, 844);
    await t23SetMobile(page, 390, 844);
    expect((await t23LiveMessages(page)).length, 'resize added no announcement').toBe(beforeResize);

    const offered = await offeredWeekIndexes(page);
    if (offered.length > 1) {
      const current = await currentWeekIndex(page);
      const next = offered.find((week) => week !== current);
      if (next !== undefined) {
        expect(await selectWeekByIndex(page, next)).toBe(true);
      }
    }
    const afterRebuild = (await t23LiveMessages(page)).length;

    /* Removal is one polite announcement, not a toast-only change. */
    await page.locator('[data-arrangement-mobile-summary-opener]').click();
    await openInfo(page);
    await page.locator('[data-arrangement-mobile-info-actions] button', { hasText: 'Remove selection' }).click();
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    await expectNoSelection(page);
    await expect.poll(async () => (await t23LiveMessages(page)).length, { timeout: 10_000 })
      .toBe(afterRebuild + 1);
    const messages = await t23LiveMessages(page);
    expect(messages[messages.length - 1]).toBe('Selection removed.');

    /* Re-select the same real block: exactly one 'Selection saved.' message.
       The day-button walk before the tap may legitimately announce its own
       day change, so the saved message is counted by text, not position. */
    expect(await selectWeekByIndex(page, selection.week)).toBe(true);
    await tapHourRow(page, selection.day, selection.hour);
    await waitForSavedSpan(page, selection.span);
    await expect.poll(async () =>
      (await t23LiveMessages(page)).filter((message) => message === 'Selection saved.').length,
    { timeout: 10_000 }).toBe(1);
    const afterReselect = (await t23LiveMessages(page)).length;
    await t23SetMobile(page, 320, 844);
    await t23SetMobile(page, 390, 844);
    expect((await t23LiveMessages(page)).length, 'resize after saving adds no announcement').toBe(afterReselect);
  });
});

/* ─────────────────────────────────────────────────────────────────────────
 * T23b — breakpoint, node identity, geometry, themes and desktop parity
 * (MA-01a–c, MA-13a–c, MA-14c)
 * ───────────────────────────────────────────────────────────────────────── */
test.describe('T23b — responsive geometry, themes and desktop parity', () => {
  test.beforeEach(async ({ page }) => { await login(page); });

  test('768px is mobile and 769px is the untouched desktop layout, each with only its own controls', async ({ page }) => {
    await gotoArrangement(page);

    for (const width of [390, 768]) {
      await t23SetMobile(page, width, 844);
      const layout = await t23Layout(page);
      expect(layout.mobile, `${width}px activates the day view`).toBe(true);
      expect(layout.rootHidden).toBe(false);
      expect(layout.rootInert).toBe(false);
      expect(layout.rootDisplay).not.toBe('none');
      expect(layout.gridDisplay, `${width}px hides the desktop grid`).toBe('none');
      expect(layout.gridHidden).toBe(true);
      expect(layout.gridInert).toBe(true);
      expect(layout.summaryDisplay).toBe('none');
      expect(layout.toolbarDisplay).toBe('none');
      expect(layout.legendDisplay).toBe('none');
      await expect(page.locator('#classDetailsCard')).toBeHidden();
      expect(await page.evaluate(() =>
        !!document.getElementById('subjectSelector')?.closest('#arrangementMobile'))).toBe(true);
      expect(await page.evaluate(() =>
        !!document.getElementById('weekSelector')?.closest('#arrangementMobile'))).toBe(true);

      const trail = await t23FocusTrail(page, 18);
      expect(trail.some((stop) => stop.inMobile), `${width}px: mobile controls are keyboard reachable`).toBe(true);
      expect(trail.every((stop) => !stop.hiddenLayout), `${width}px: no hidden desktop container is a tab stop`).toBe(true);
    }

    /* 769 is desktop by the frozen 768 boundary. */
    await t23SetDesktop(page, 769, 844);
    const desktop = await t23Layout(page);
    expect(desktop.mobile, '769px leaves the mobile mode').toBe(false);
    expect(desktop.rootHidden).toBe(true);
    expect(desktop.rootDisplay).toBe('none');
    expect(desktop.gridDisplay).not.toBe('none');
    expect(desktop.gridHidden).toBe(false);
    expect(desktop.gridInert).toBe(false);
    /* Round 2: the Summary is conditional, so a fresh page has no fourth card. */
    expect(desktop.summaryDisplay).toBe('none');
    expect(desktop.toolbarDisplay).not.toBe('none');
    expect(desktop.legendDisplay).not.toBe('none');
    await expect(page.locator('#classDetailsCard')).toBeVisible();
    await expect(page.locator('#detailsEmpty')).toBeVisible();
    expect(await page.evaluate(() =>
      !!document.getElementById('subjectSelector')?.closest('#arrangementMobile'))).toBe(false);
    expect(await page.evaluate(() =>
      !!document.getElementById('weekSelector')?.closest('#arrangementMobile'))).toBe(false);

    const desktopTrail = await t23FocusTrail(page, 18);
    expect(desktopTrail.every((stop) => !stop.inMobile), '769px: no mobile-only control is a tab stop').toBe(true);
  });

  test('mobile Class Details appears only with a slot and keeps its facts', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/replacement-arrangement?session=67&week=14&from=replacement-home');
    await page.waitForSelector('#subjectInfo', { timeout: 15_000 });
    await page.waitForTimeout(900);

    await t23SetMobile(page, 390, 844);
    const context = page.locator('[data-arrangement-mobile-context]');
    if (!(await context.evaluate((node) => (node as unknown as HTMLDetailsElement).open))) {
      await page.locator('[data-arrangement-mobile-context-summary]').click();
    }
    const card = page.locator('#classDetailsCard');
    await expect(card).toBeVisible();
    await expect(card).toHaveClass(/has-slot/);
    await expect(page.locator('#detailsEmpty')).toBeHidden();
    await expect(page.locator('#detailCohorts')).not.toBeEmpty();

    await t23SetDesktop(page, 769, 844);
    await expect(card).toBeVisible();
    await expect(card).toHaveClass(/has-slot/);
  });

  test('round-1 mobile top zone, compact picker and footer clearance', async ({ page }) => {
    await gotoArrangement(page);
    await t23SetMobile(page, 390, 844);

    const back = page.locator('.top-bar > .back-btn');
    await expect(back).toHaveAttribute('aria-label', 'Back');
    await expect(back.locator('span')).toBeHidden();
    await expect(page.locator('.top-bar > .theme-toggle')).toBeVisible();

    const firstDay = page.locator('[data-arrangement-mobile-days] button').first();
    test.skip((await firstDay.count()) === 0, 'no eligible day in this fixture');
    const dayLabel = await firstDay.getAttribute('aria-label');
    expect(dayLabel).toMatch(/^[A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4}$/);
    expect(((await firstDay.textContent()) || '').trim()).toMatch(/^[A-Z][a-z]{2}\d{1,2}$/);
    const dayParts = await firstDay.evaluate((el) => {
      const weekday = el.querySelector('.arrangement-mobile-day-weekday') as HTMLElement | null;
      const number = el.querySelector('.arrangement-mobile-day-number') as HTMLElement | null;
      if (!weekday || !number) return null;
      return {
        number: (number.textContent || '').trim(),
        weekdayBottom: weekday.getBoundingClientRect().bottom,
        numberTop: number.getBoundingClientRect().top,
      };
    });
    expect(dayParts).not.toBeNull();
    expect(dayParts!.number).toMatch(/^\d{1,2}$/);
    expect(dayParts!.numberTop).toBeGreaterThanOrEqual(dayParts!.weekdayBottom - 1);

    await expect(page.locator('#earliestBtn .today-btn-label')).toBeHidden();
    await expect(page.locator('#earliestBtn svg')).toBeVisible();
    await expect(page.locator('[data-arrangement-mobile-legend] svg')).toBeVisible();

    await page.evaluate(() => {
      window.scrollTo(0, document.documentElement.scrollHeight);
    });
    await page.waitForTimeout(200);
    const clearance = await page.evaluate(() => {
      const timeline = document.querySelector('[data-arrangement-mobile-timeline]');
      const last = timeline ? (timeline.lastElementChild as HTMLElement | null) : null;
      const footer = document.querySelector('.ui-footer') as HTMLElement | null;
      const bar = document.querySelector('[data-arrangement-mobile-actions]') as HTMLElement | null;
      if (!last || !footer || !bar) return null;
      const lastRect = last.getBoundingClientRect();
      const footerRect = footer.getBoundingClientRect();
      const barRect = bar.getBoundingClientRect();
      const footerContentBottom = footerRect.bottom - parseFloat(getComputedStyle(footer).paddingBottom);
      return {
        gap: footerRect.top - lastRect.bottom,
        footerContentBottom,
        barTop: barRect.top,
      };
    });
    expect(clearance).not.toBeNull();
    expect(clearance!.gap, 'no blank band between the last slot and footer').toBeLessThanOrEqual(24);
    expect(clearance!.footerContentBottom, 'footer content clears the fixed bar').toBeLessThanOrEqual(clearance!.barTop + 1);
  });

  test('node identity, order and values survive repeated crossings without a confirmation or new state', async ({ page }) => {
    const selection = await t22CreateSelection(page);
    const mobileSummary = ((await summaryTitle(page).textContent()) || '').trim();
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);

    await t23SetDesktop(page);
    await t23MarkNodes(page);
    const valuesBefore = await t23Values(page);
    const canonicalBefore = await t23SelectedCanonicalCount(page);
    expect(canonicalBefore, 'the saved block is visible on the desktop baseline').toBe(selection.span);
    expect(await t23DuplicateIds(page)).toEqual([]);

    for (let crossing = 0; crossing < 3; crossing += 1) {
      await t23SetMobile(page, 768, 844);
      await expect(page.locator('#confirmModal')).toBeHidden();
      await t23SetDesktop(page);
      await expect(page.locator('#confirmModal')).toBeHidden();
    }

    const report = await t23NodeReport(page);
    for (const entry of report) {
      expect(entry.present, `${entry.selector} is still mounted`).toBe(true);
      expect(entry.saved, `${entry.selector} was tagged on the desktop baseline`).not.toBeNull();
      expect(entry.sameNode, `${entry.selector} is the same actual node (never a copy)`).toBe(true);
      expect(entry.index, `${entry.selector} returned to its exact desktop sibling order`).toBe(entry.saved.index);
      expect(entry.parentTag, `${entry.selector} returned to its original parent tag`).toBe(entry.saved.parentTag);
      expect(entry.parentId, `${entry.selector} returned to its original parent id`).toBe(entry.saved.parentId);
    }

    expect(await t23DuplicateIds(page), 'no duplicate ids after repeated relocation').toEqual([]);
    expect(await t23Values(page)).toEqual(valuesBefore);
    expect(await t23SelectedCanonicalCount(page), 'resizing changed no booking state').toBe(canonicalBefore);

    await t23SetMobile(page);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
    expect(((await summaryTitle(page).textContent()) || '').trim()).toBe(mobileSummary);
  });

  test('320/390/768 widths, 200% text and the 1280 baseline keep 44px targets and bounded width', async ({ page }) => {
    await gotoArrangement(page);

    for (const width of T23_MOBILE_WIDTHS) {
      await t23SetMobile(page, width, 844);
      await page.waitForTimeout(250);

      const targets = await t23Targets(page);
      expect(targets.length, `${width}px renders real controls`).toBeGreaterThan(5);
      for (const target of targets) {
        expect(target.width, `${width}px: ${target.tag} "${target.text}" meets the 44px width`)
          .toBeGreaterThanOrEqual(43.5);
        expect(target.height, `${width}px: ${target.tag} "${target.text}" meets the 44px height`)
          .toBeGreaterThanOrEqual(43.5);
      }

      const overflow = await t23Overflow(page);
      expect(overflow.pageScrollWidth, `${width}px: no page-wide horizontal overflow`)
        .toBeLessThanOrEqual(overflow.viewport + 1);
      expect(overflow.bodyScrollWidth, `${width}px: the body does not overflow`)
        .toBeLessThanOrEqual(overflow.viewport + 1);
      expect(overflow.timelineScrollWidth, `${width}px: the timeline scrolls vertically only`)
        .toBeLessThanOrEqual(overflow.timelineClientWidth + 1);
      expect(overflow.dayHostOverflowX, `${width}px: only the day picker may scroll sideways`).toBe('auto');
      expect(overflow.offenders, `${width}px: no visible control extends past the viewport`).toEqual([]);

      for (const item of await t23Clipping(page)) {
        expect(item.clipped, `${width}px: ${item.label} text is not horizontally clipped`).toBe(false);
      }
      const slot = await t23SlotTriggerFacts(page);
      if (slot && slot.clipped) {
        expect(slot.hasFullFacts, 'the compact original-slot trigger keeps its full facts in the tip')
          .toBe(true);
      }

      const bar = await t23BarMeasurement(page);
      expect(bar.css, `${width}px: the measured bar height is written`).toMatch(/^\d+px$/);
      expect(Math.abs(parseFloat(bar.css) - bar.measured), `${width}px: the bar height is measured, not guessed`)
        .toBeLessThanOrEqual(2);
    }

    /* Enlarged text: page-scoped font-size override, not an OS/browser zoom API.
       A test-only padding override also guarantees a real bar-height change, so
       the assertion proves the measurement follows the DOM rather than a guess. */
    await t23SetMobile(page, 390, 844);
    const baseBar = await t23BarMeasurement(page);
    const enlarged = await page.addStyleTag({
      content: '#arrangementMobile { font-size: 200%; }\n'
        + 'body.arrangement-mobile-mode #arrangementMobile .arrangement-mobile-summary { '
        + 'padding-top: 20px; padding-bottom: 20px; }',
    });
    await page.waitForTimeout(350);
    for (const target of await t23Targets(page)) {
      expect(target.height, `200% text: ${target.tag} "${target.text}" stays a 44px target`)
        .toBeGreaterThanOrEqual(43.5);
    }
    const zoomedOverflow = await t23Overflow(page);
    expect(zoomedOverflow.pageScrollWidth, '200% text adds no page-wide horizontal overflow')
      .toBeLessThanOrEqual(zoomedOverflow.viewport + 1);
    expect(zoomedOverflow.timelineScrollWidth).toBeLessThanOrEqual(zoomedOverflow.timelineClientWidth + 1);
    expect(zoomedOverflow.offenders).toEqual([]);
    for (const item of await t23Clipping(page)) {
      expect(item.clipped, `200% text: ${item.label} still wraps instead of clipping`).toBe(false);
    }
    await expect.poll(async () => {
      const bar = await t23BarMeasurement(page);
      return Math.abs(parseFloat(bar.css || '0') - bar.measured);
    }, { timeout: 5_000 }).toBeLessThanOrEqual(2);
    const zoomedBar = await t23BarMeasurement(page);
    expect(zoomedBar.measured, 'the test-only size change really grew the bar').toBeGreaterThan(baseBar.measured);
    expect(Math.abs(parseFloat(zoomedBar.css) - zoomedBar.measured),
      'the measured inset followed the size change').toBeLessThanOrEqual(2);
    await enlarged.evaluate((node) => node.remove());

    /* 1280 baseline: the mobile surface and its measured insets are absent. */
    await t23SetDesktop(page);
    const desktop = await t23Layout(page);
    expect(desktop.mobile).toBe(false);
    expect(desktop.rootDisplay).toBe('none');
    expect(desktop.barVar, 'the measured bar inset does not leak to desktop').toBe('');
    expect(desktop.stickyVar, 'the measured sticky inset does not leak to desktop').toBe('');
    expect(desktop.gridDisplay).not.toBe('none');
    expect(await page.evaluate(() =>
      ((document.querySelector('[data-arrangement-mobile-subject-host]') as HTMLElement | null)?.childElementCount) || 0))
      .toBe(0);
    expect(await t23DuplicateIds(page)).toEqual([]);
  });

  test('the saved span and rows keep index-derived geometry clear of the sticky cluster and bar', async ({ page }) => {
    const selection = await t22CreateSelection(page);
    await t23SetMobile(page, 390, 844);
    await page.waitForTimeout(300);

    const geometry = await t23SpanGeometry(page);
    test.skip(geometry === null, 'the saved span is not renderable in the current state; geometry cannot be asserted');
    const span = geometry!;
    expect(span.slotCount).toBe(selection.span);
    expect(span.gridRow, 'the blue block spans exactly the saved half-hours').toMatch(new RegExp(`span ${selection.span}`));
    expect(span.borderWidth, 'the existing blue selection outline is kept').toBe('2px');
    expect(span.borderColor).not.toBe('rgba(0, 0, 0, 0)');
    expect(parseFloat(span.rowMinHeight), 'the nominal row height is at least 44px').toBeGreaterThanOrEqual(44);
    expect(span.rowHeight, 'a real row is at least 44px tall').toBeGreaterThanOrEqual(44);
    expect(span.height, 'the span is at least 44px per covered half-hour').toBeGreaterThanOrEqual(selection.span * 44);

    const nominal = selection.span * 48 + (selection.span - 1) * 4;
    expect(span.height, 'the span keeps the equivalent height of the covered rows').toBeGreaterThanOrEqual(nominal - 2);
    expect(span.height, 'the span has no runaway height').toBeLessThanOrEqual(nominal + 32);
    if (span.beforeGap !== null) expect(span.beforeGap, 'no overlap with the row before the span').toBeGreaterThanOrEqual(1.5);
    if (span.afterGap !== null) {
      expect(span.afterGap, 'no overlap with the row after the span').toBeGreaterThanOrEqual(1.5);
      expect(span.afterGap).toBeLessThanOrEqual(6.5);
    }

    expect(parseFloat(span.scrollMarginTop), 'a revealed row is not hidden under the sticky cluster').toBeGreaterThan(0);
    if (/^\d+px$/.test(span.stickyVar)) {
      expect(parseFloat(span.scrollMarginTop)).toBe(parseFloat(span.stickyVar) + 12);
    }
    if (/^\d+px$/.test(span.barVar)) {
      expect(parseFloat(span.scrollMarginBottom)).toBe(parseFloat(span.barVar) + 12);
    }

    const bar = await t23BarMeasurement(page);
    expect(bar.css).toMatch(/^\d+px$/);
    expect(Math.abs(parseFloat(bar.css) - bar.measured)).toBeLessThanOrEqual(2);

    const env = await t23EnvDeclarations(page);
    expect(env.css, 'safe-area insets are still declared for the mobile surface').toContain('env(safe-area-inset-bottom');
    expect(env.css, 'the sheet still fits within 85dvh').toContain('85dvh');
    expect(env.viewportMeta, 'the existing viewport-fit=cover is reused').toContain('viewport-fit=cover');

    const overlay = await t23OverlayCheck(page);
    test.skip(overlay === null, 'no visible timeline rows exist for the overlay geometry check');
    expect(overlay!.firstClear, 'the first row can be brought clear of the sticky cluster').toBe(true);
    expect(overlay!.firstHit, 'the first row is hit-testable, not covered').toBe(true);
    expect(overlay!.lastClear, 'the last row can be brought clear of the fixed bar').toBe(true);
    expect(overlay!.lastHit, 'the last row is hit-testable, not covered').toBe(true);

    /* A real transient sits above the measured bar. */
    const other = await t23OtherGreenHour(page, selection.day, selection.hour, selection.span);
    if (other !== null) {
      await page.locator(
        `[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${other}"]`,
      ).click();
      await expect(page.locator('#toastBar')).toHaveClass(/visible/, { timeout: 10_000 });
      /* Let the shared 0.3s slide-up finish before measuring; mid-animation the
         toast is intentionally 20px lower and can briefly touch the bar. */
      await page.waitForTimeout(400);
      const toast = await t23ToastBarCheck(page);
      expect(toast).not.toBeNull();
      expect(toast!.above, 'the toast clears the fixed action bar').toBe(true);
      await page.locator('#toastBar .toast-close').click();
    } else {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'no second free row existed; the mobile toast/bar clearance half was not exercised',
      });
    }

    /* Confirmation actions stay above the bar and hit-testable. */
    await expect(page.locator('#submitRequestBtn')).toBeEnabled({ timeout: 15_000 });
    await page.locator('#submitRequestBtn').click();
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    const confirmHit = await t23HitTest(page, '#modalConfirmBtn');
    expect(confirmHit).not.toBeNull();
    expect(confirmHit!.inside, 'the confirmation action is not obscured by the bar/overlays').toBe(true);
    await page.locator('#confirmModal .btn-outline').click();
    await expect(page.locator('#confirmModal')).toBeHidden();
  });

  test('both themes keep readable token colours and reduced motion drops nonessential animation', async ({ page }) => {
    await t22CreateSelection(page);
    await t23SetMobile(page, 390, 844);

    const initialTheme = await page.evaluate(() => document.documentElement.className);
    const seen: string[] = [];
    for (let pass = 0; pass < 2; pass += 1) {
      const snapshot = await t23ContrastSamples(page);
      seen.push(snapshot.theme);
      expect(snapshot.samples.length, 'the themed text targets exist').toBeGreaterThanOrEqual(3);
      for (const sample of snapshot.samples) {
        expect(
          sample.ratio,
          `${snapshot.theme}: ${sample.label} keeps a computed contrast floor (fg ${sample.fg}, bg ${sample.bg})`,
        ).toBeGreaterThanOrEqual(3);
      }

      const span = await page.evaluate(() => {
        const node = document.querySelector(
          '[data-arrangement-mobile-timeline] [data-mobile-row="span"]',
        ) as HTMLElement | null;
        if (!node) return null;
        const style = getComputedStyle(node);
        return { width: style.borderTopWidth, color: style.borderTopColor, background: style.backgroundColor };
      });
      expect(span, 'the selection sample exists in this theme').not.toBeNull();
      expect(span!.width).toBe('2px');
      expect(span!.color).not.toBe('rgba(0, 0, 0, 0)');
      expect(span!.background).not.toBe('rgba(0, 0, 0, 0)');

      /* Round 1: the mobile header now exposes the shared theme toggle. */
      const themeToggle = page.locator('body.arrangement-mobile-mode .top-bar > .theme-toggle');
      await expect(themeToggle, 'mobile exposes the theme toggle').toBeVisible();
      await themeToggle.click();
      await expect.poll(async () => page.evaluate(() => document.documentElement.className)).not.toBe(snapshot.theme);
      /* Theme colours transition; sample the stable second paint, not the
         intermediate frame where the old background can still be computed. */
      await page.waitForTimeout(400);
    }
    expect(new Set(seen).size, 'both current themes were exercised').toBe(2);
    await expect.poll(async () => page.evaluate(() => document.documentElement.className)).toBe(initialTheme);

    await page.emulateMedia({ reducedMotion: 'reduce' });
    await expect.poll(async () =>
      page.evaluate(() => window.matchMedia('(prefers-reduced-motion: reduce)').matches)).toBe(true);
    const reduced = await page.evaluate(() => {
      const node = document.querySelector('[data-arrangement-mobile-days] button') as HTMLElement | null;
      if (!node) return null;
      const style = getComputedStyle(node);
      return { transition: style.transitionDuration, animation: style.animationDuration };
    });
    expect(reduced).not.toBeNull();
    /* Browsers may serialize 0.01ms as `1e-05s`; compare seconds, not strings. */
    const seconds = (value: string) => {
      const part = value.trim();
      return part.endsWith('ms') ? parseFloat(part) / 1000 : parseFloat(part);
    };
    expect(reduced!.transition.split(',').every((value) => seconds(value) <= 0.00002),
      'reduced motion collapses nonessential transitions').toBe(true);
    expect(seconds(reduced!.animation),
      'reduced motion collapses nonessential animation').toBeLessThanOrEqual(0.00002);
    await page.emulateMedia({ reducedMotion: 'no-preference' });
  });

  test('the arrangement-mobile styles and controller stay on their own page', async ({ page }) => {
    await gotoArrangement(page);
    await t23SetMobile(page, 390, 844);
    await expect(page.locator('#arrangementMobile')).toHaveCount(1);

    await page.goto(`${BASE}${withCacheBust('/my-timetable-ui')}`, { waitUntil: 'domcontentloaded' });
    await page.waitForFunction(() => document.body !== null);
    const isolation = await page.evaluate(() => {
      const grid = document.querySelector('.grid-wrapper') as HTMLElement | null;
      return {
        roots: document.querySelectorAll('#arrangementMobile').length,
        hosts: document.querySelectorAll('[data-arrangement-mobile]').length,
        bodyClass: document.body.classList.contains('arrangement-mobile-mode'),
        barVar: document.body.style.getPropertyValue('--arrangement-mobile-bar-height').trim(),
        stickyVar: document.body.style.getPropertyValue('--arrangement-mobile-sticky-height').trim(),
        gridDisplay: grid ? getComputedStyle(grid).display : 'missing',
      };
    });
    expect(isolation.roots, 'other pages do not receive the mobile shell').toBe(0);
    expect(isolation.hosts, 'other pages have no arrangement-mobile hosts').toBe(0);
    expect(isolation.bodyClass).toBe(false);
    expect(isolation.barVar).toBe('');
    expect(isolation.stickyVar).toBe('');
    if (isolation.gridDisplay !== 'missing') expect(isolation.gridDisplay).not.toBe('none');

    await gotoArrangement(page);
    await expect(page.locator('body')).toHaveClass(/arrangement-mobile-mode/);
    await expect(page.locator('#arrangementMobile')).toBeVisible();
  });
});

/* ─────────────────────────────────────────────────────────────────────────
 * T23c — feedback lifetime, Tip tooltip, lifecycle isolation and teardown
 * (MA-07c–e, MA-08a–b, MA-14a–c, MA-15a–b)
 * ─────────────────────────────────────────────────────────────────────────
 * Timers: `page.clock` is installed AFTER boot and only the toast/sheet
 * timers authored here are driven. Installing before navigation could starve
 * Livewire's boot timers; the case is therefore blocked (not passed) when the
 * runner lacks `page.clock.install`.
 * ───────────────────────────────────────────────────────────────────────── */

/** Fixed-position tooltip visibility (the shared engine's own style signals). */
async function t23FloatingTooltip(page: Page): Promise<Array<{ shown: boolean; text: string; width: number }>> {
  return page.evaluate(() => Array.from(document.querySelectorAll('.data-tip-tooltip')).map((node) => {
    const element = node as HTMLElement;
    const style = getComputedStyle(element);
    return {
      shown: style.visibility !== 'hidden' && style.opacity !== '0' && element.getBoundingClientRect().width > 0,
      text: (element.textContent || '').trim(),
      width: Math.round(element.getBoundingClientRect().width),
    };
  }));
}

test.describe('T23c — feedback lifetime, Tip tooltip and lifecycle isolation', () => {
  test.beforeEach(async ({ page }) => { await login(page); });

  test('a mobile warning survives 4990ms, disappears just after 5000ms and a newer warning restarts the timer', async ({ page }) => {
    const selection = await t22CreateSelection(page);
    const other = await t23OtherGreenHour(page, selection.day, selection.hour, selection.span);
    test.skip(other === null, 'the viewed day has no second free row; the already-selected warning cannot be triggered');

    await t23InstallClock(page);
    const rowSelector =
      `[data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${other}"]`;
    test.skip(!(await page.locator(rowSelector).count()), 'the warning target row was not rendered');

    await t23DomClick(page, rowSelector);
    await expect(page.locator('#toastBar')).toHaveClass(/visible/);
    expect((await t23ToastState(page)).message).toBe(T23_ALREADY_SELECTED);

    await t23Forward(page, 4_990);
    expect((await t23ToastState(page)).visible, 'the warning is still visible before 5000ms').toBe(true);

    await t23Forward(page, 40);
    expect((await t23ToastState(page)).visible, 'the warning retires just after 5000ms').toBe(false);

    /* A newer warning restarts the same five-second window. */
    await t23DomClick(page, rowSelector);
    await expect(page.locator('#toastBar')).toHaveClass(/visible/);
    await t23Forward(page, 3_000);
    expect((await t23ToastState(page)).visible, 'the first window is still open at 3000ms').toBe(true);

    await t23DomClick(page, rowSelector);
    await t23Forward(page, 2_400);
    expect(
      (await t23ToastState(page)).visible,
      '5400ms after the first warning but only 2400ms after the newer one: the timer restarted',
    ).toBe(true);

    await t23Forward(page, 2_700);
    expect((await t23ToastState(page)).visible, '5100ms after the newer warning: retired').toBe(false);

    /* The saved selection was never replaced or cleared by the warning. */
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
    await expect(page.locator('#submitRequestBtn')).toBeEnabled();
  });

  test('details, Tip and the critical confirmation do not auto-dismiss when the five-second clock advances', async ({ page }) => {
    await t22CreateSelection(page);
    await t23InstallClock(page);

    /* A persistent information sheet. */
    expect(await t23DomClick(page, '[data-arrangement-mobile-legend]')).toBe(true);
    await openInfo(page);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Colours & borders');
    await t23Forward(page, 60_000);
    expect(await infoOpen(page), 'the legend sheet has no auto-dismiss timer').toBe(true);
    expect(await t23DomClick(page, '[data-arrangement-mobile-info-close]')).toBe(true);
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();

    /* The Tip sheet is equally persistent. */
    expect(await t23DomClick(page, '#summaryTip')).toBe(true);
    await openInfo(page);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Tip');
    await t23Forward(page, 60_000);
    expect(await infoOpen(page), 'the Tip sheet has no auto-dismiss timer').toBe(true);
    expect(await t23DomClick(page, '[data-arrangement-mobile-info-close]')).toBe(true);
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();

    /* A critical confirmation stays until explicit dismissal; controlled
       transport guarantees no network call is made while it is open. */
    await t22InstallSubmitTransport(page, 'pending');
    await expect(page.locator('#submitRequestBtn')).toBeEnabled({ timeout: 15_000 });
    expect(await t23DomClick(page, '#submitRequestBtn')).toBe(true);
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#modalTitle')).toHaveText('Confirm Your Selection');
    await t23Forward(page, 60_000);
    await expect(page.locator('#confirmModal')).toBeVisible();
    expect((await t22TransportState(page)).calls, 'an open confirmation makes no submitBlocks call').toBe(0);
    expect(await t23DomClick(page, '#confirmModal .btn-outline')).toBe(true);
    await expect(page.locator('#confirmModal')).toBeHidden();
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);
  });

  test('Tip keeps one mobile sheet, no duplicate floating tooltip, and the desktop tooltip is restored', async ({ page }) => {
    await gotoArrangement(page);
    const message = await page.evaluate(() => {
      const tip = document.getElementById('summaryTip');
      return tip ? (tip.getAttribute('aria-label') || '').replace(/^Tip:\s*/i, '').trim() : '';
    });
    expect(message.length, 'the live Tip message exists').toBeGreaterThan(0);
    expect(await page.locator('#summaryTip').getAttribute('data-tip'), 'mobile removes only data-tip').toBeNull();

    /* Tip sits beside Colours & borders in the tools row. */
    const geometry = await page.evaluate(() => {
      const legend = document.querySelector('[data-arrangement-mobile-legend]') as HTMLElement | null;
      const tip = document.getElementById('summaryTip') as HTMLElement | null;
      if (!legend || !tip) return null;
      const legendRect = legend.getBoundingClientRect();
      const tipRect = tip.getBoundingClientRect();
      const tools = document.querySelector('[data-arrangement-mobile-tools]');
      return {
        legendRight: legendRect.right,
        legendTop: legendRect.top,
        legendBottom: legendRect.bottom,
        tipLeft: tipRect.left,
        tipTop: tipRect.top,
        tipBottom: tipRect.bottom,
        sameToolsRow: !!(tools && legend.closest('[data-arrangement-mobile-tools]') && tip.closest('[data-arrangement-mobile-tools]')),
      };
    });
    expect(geometry).not.toBeNull();
    expect(geometry!.sameToolsRow, 'Tip lives in the same tools group as the legend opener').toBe(true);
    expect(geometry!.tipLeft, 'Tip sits to the right of Colours & borders').toBeGreaterThanOrEqual(geometry!.legendRight - 2);
    expect(geometry!.tipTop, 'Tip shares the legend row (vertical overlap)').toBeLessThan(geometry!.legendBottom);
    expect(geometry!.tipBottom).toBeGreaterThan(geometry!.legendTop);

    await page.locator('#summaryTip').click();
    await openInfo(page);
    await expect(page.locator('[data-arrangement-mobile-info-heading]')).toHaveText('Tip');
    const body = ((await page.locator('[data-arrangement-mobile-info-body] .arrangement-mobile-info-tip').textContent()) || '').trim();
    expect(body, 'the sheet carries the same live state-aware message').toBe(message);
    expect(await page.locator('.data-tip-tooltip').count(), 'one shared tooltip node, not a second copy').toBe(1);
    expect((await t23FloatingTooltip(page)).every((tip) => !tip.shown), 'no floating tooltip above the mobile sheet').toBe(true);
    await page.locator('[data-arrangement-mobile-info-close]').click();
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();

    /* Desktop restores the latest live message and a real hover/focus tooltip.
       Round 2: the Summary is conditional, so the empty desktop page has no
       Tip to focus — make a real selection first. */
    await t23SetDesktop(page);
    if (!(await page.locator('#selSummary').isVisible())) {
      const course = await page.locator('#subjectSelector option').evaluateAll(
        (options) => options.map((option) => (option as HTMLOptionElement).value).filter(Boolean)[0] || '',
      );
      await page.locator('#subjectSelector').selectOption(course);
      await page.locator('#tableBody .cell-content.cell-available').first().click();
      await expect(page.locator('#selSummary')).toBeVisible();
    }
    const desktopMessage = await page.evaluate(() => {
      const tip = document.getElementById('summaryTip');
      return tip ? (tip.getAttribute('aria-label') || '').replace(/^Tip:\s*/i, '').trim() : '';
    });
    expect(desktopMessage.length).toBeGreaterThan(0);
    expect((await page.locator('#summaryTip').getAttribute('data-tip') || '').replace(/\n/g, ' '),
      'desktop gets the current message back, not the boot-stale one').toBe(desktopMessage);
    await page.locator('#summaryTip').focus();
    await expect.poll(async () =>
      (await t23FloatingTooltip(page)).some((tip) => tip.shown && tip.text.replace(/\n/g, ' ') === desktopMessage)).toBe(true);
    await page.locator('#summaryTip').evaluate((node) => (node as HTMLElement).blur());
    await expect.poll(async () => (await t23FloatingTooltip(page)).every((tip) => !tip.shown)).toBe(true);

    await t23SetMobile(page);
    expect(await page.locator('#summaryTip').getAttribute('data-tip'), 'mobile suppresses it again on re-entry').toBeNull();
  });

  test('repeated resize/refresh keeps one controller and one listener set and never duplicates announcements', async ({ page }) => {
    await t23InstallListenerProbe(page);
    await gotoArrangement(page);

    const before = await t23ListenerCounts(page);
    expect(before.adds['#arrangementMobile:click'], 'one day delegate + one timeline delegate').toBe(2);
    expect(before.adds['#arrangementMobileInfo:close'], 'one dialog close listener').toBe(1);

    /* One shell/controller surface: no duplicate hosts or relaxed controls. */
    for (const selector of [
      '#arrangementMobile',
      '[data-arrangement-mobile-days]',
      '[data-arrangement-mobile-timeline]',
      '[data-arrangement-mobile-summary-host]',
      '#arrangementMobileInfo',
      '#weekSelector',
      '#summaryTip',
      '#submitRequestBtn',
    ]) {
      await expect(page.locator(selector), `${selector} is not duplicated`).toHaveCount(1);
    }
    expect(await t23DuplicateIds(page)).toEqual([]);

    await t23ObserveLive(page);
    for (let crossing = 0; crossing < 3; crossing += 1) {
      await t23SetMobile(page, 768, 844);
      await t23SetDesktop(page, 769, 844);
      await t23SetMobile(page, 390, 844);
    }

    const after = await t23ListenerCounts(page);
    expect(after.adds, 'resize never re-binds a listener').toEqual(before.adds);
    expect(after.removes, 'resize never removes a listener it does not own').toEqual(before.removes);
    expect(await t23LiveMessages(page), 'layout transitions announce nothing').toEqual([]);

    /* One real change speaks once; another resize stays silent. */
    const days = await visibleDayIndexes(page);
    if (days.length > 1) {
      const pressed = await pressedDay(page);
      const target = days.find((day) => day !== pressed) ?? days[0];
      await page.locator(`[data-arrangement-mobile-days] button[data-day-index="${target}"]`).click();
      await expect.poll(async () => (await t23LiveMessages(page)).length).toBe(1);
      await t23SetMobile(page, 320, 844);
      await t23SetMobile(page, 390, 844);
      expect((await t23LiveMessages(page)).length, 'the resize after a change adds no announcement').toBe(1);
    } else {
      test.info().annotations.push({
        type: 'data-gap',
        description: 'one eligible day only; the single-announcement half of the non-duplication check was not exercised',
      });
    }
  });

  test('a real pagehide teardown restores desktop, releases the media listener and cannot re-enter mobile', async ({ page }) => {
    await gotoArrangement(page);
    await t23SetMobile(page, 390, 844);
    const before = await t23Values(page);
    const errors: string[] = [];
    page.on('pageerror', (error) => errors.push(error.message));

    await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
    await expect(page.locator('body')).not.toHaveClass(/arrangement-mobile-mode/);

    const layout = await t23Layout(page);
    expect(layout.rootHidden, 'the shell is hidden after teardown').toBe(true);
    expect(layout.rootInert, 'the shell is inert after teardown').toBe(true);
    expect(layout.rootDisplay).toBe('none');
    expect(layout.barVar, 'the measured bar inset is cleared').toBe('');
    expect(layout.stickyVar, 'the measured sticky inset is cleared').toBe('');

    expect(await page.evaluate(() =>
      !!document.getElementById('subjectSelector')?.closest('#arrangementMobile')),
      'the real controls are back on their desktop hosts').toBe(false);
    for (const selector of [
      '[data-arrangement-mobile-subject-host]',
      '[data-arrangement-mobile-days]',
      '[data-arrangement-mobile-timeline]',
      '[data-arrangement-mobile-summary-host]',
    ]) {
      expect(await page.evaluate((sel) =>
        ((document.querySelector(sel) as HTMLElement | null)?.childElementCount) || 0, selector),
        `${selector} keeps no adapter-rendered children`).toBe(0);
    }
    expect(await page.evaluate(() =>
      ((document.querySelector('[data-arrangement-mobile-live]')?.textContent) || '').trim()),
      'the live region keeps no stale announcement').toBe('');

    const after = await t23Values(page);
    expect({
      subject: after.subject,
      week: after.week,
      venue: after.venue,
      slot: after.slotText,
    }, 'teardown alone changes no booking/control identity').toEqual({
      subject: before.subject,
      week: before.week,
      venue: before.venue,
      slot: before.slotText,
    });

    /* The matchMedia listener was released with the controller. */
    await page.setViewportSize({ width: 390, height: 844 });
    await page.waitForTimeout(500);
    expect(await page.evaluate(() => document.body.classList.contains('arrangement-mobile-mode')),
      'a later narrow viewport cannot revive a destroyed controller').toBe(false);
    expect(await page.locator('#arrangementMobile').isHidden()).toBe(true);

    /* A second departure is a safe no-op (no throw, no duplicate teardown). */
    await page.evaluate(() => window.dispatchEvent(new Event('pagehide')));
    await page.waitForTimeout(150);
    expect(errors, 'teardown produced no page errors').toEqual([]);
  });
});
