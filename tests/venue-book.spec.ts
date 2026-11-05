import { test, expect, type Page } from '@playwright/test';
import { pickSpanFreeCell, selectWeek } from './helpers/intent-modal-helper';

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
      return 'Arrange a replacement in B110 on ' + day.abbr + ', ' + day.date + ' at ' + hours[Number(h)] + '?';
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
  // W8 (Deepavali week): AMCS2093 (L) is the conflicted occurrence the modal
  // offers; W14 has no conflicted L → zero-compat would hide [Arrange] by design.
  await page.goto(PAGE);
  await selectWeek(page, 7);
  await pickSpanFreeCell(page);
  await page.locator('#availableTooltip.show #tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
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

test('U2a-T6: no-fit cell shows the two-line "Too Late / to Book" label; click gives the shared toast, no navigation', async ({ page }) => {
  await page.goto(PAGE);
  await selectLastWeek(page);
  const noFit = page.locator('.cell-available.cell-no-fit').first();
  // venue-timetable-bookable-cells: the late run now follows the SHORTEST class
  // this venue can host, so its size varies by lecturer/venue — never assert 1.
  expect(await page.locator('.cell-available.cell-no-fit').count()).toBeGreaterThan(0);
  const label = await noFit.evaluate((el) => getComputedStyle(el, '::after').content);
  // Two-line label law (user, 2026-10-25): "Too Late" / "to Book". The
  // computed content CSS-serialises the embedded newline — Chromium hands it
  // back as "\a" (lowercase), other engines as "\A" or a literal newline —
  // normalise all forms, strip the CSS string quotes, assert the lines.
  const lines = label
    .replace(/^["']|["']$/g, '')
    .replace(/\\a\s?/gi, '\n')
    .split('\n')
    .map((s) => s.trim())
    .filter(Boolean);
  expect(lines).toEqual(['Too Late', 'to Book']);
  await noFit.click();
  // venue-slot-validation (2026-11-06): the hover floor is a fixed 60 minutes —
  // never a class-derived number, never the old 120.
  await expect(page.locator('#toastBar .toast-message'))
    .toHaveText(/^A booking needs 60 minutes/);
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
  expect(page.url()).toContain('/venue-timetable-ui');
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
  // both directions — nothing blocked that fits, nothing offered that does not
  await page.goto(PAGE);
  await selectLastWeek(page);
  const audit = await page.evaluate(() => {
    /* MIN_BOOK_SLOTS — the fixed one-hour hover floor (venue-slot-validation) */
    const min = 2;
    /* `hours` is a top-level const in ui-common.js — a lexical global, not a
       window property, so read it by its bare name (same as U2a-T2 does). */
    const hoursLen = hours.length;
    const cur = (window as any).currentWeek;
    const evs = (MockData.myTimetable.eventsByWeek || [])[cur] || [];
    const cells = Array.from(document.querySelectorAll('#tableBody .cell-available')) as HTMLElement[];
    const bad: string[] = [];
    cells.forEach((el) => {
      const di = Number(el.dataset.day);
      const hi = Number(el.dataset.hour);
      let next = Infinity;
      evs.forEach((ev: any) => { if (ev.di === di && ev.start > hi && ev.start < next) next = ev.start; });
      const room = next === Infinity ? Infinity : next - hi;
      const expectLate = hi + min > hoursLen;
      const expectNoRoom = !expectLate && room < min;
      if (expectLate !== el.classList.contains('cell-no-fit')) bad.push('late ' + di + '/' + hi);
      if (expectNoRoom !== el.classList.contains('cell-no-room')) bad.push('room ' + di + '/' + hi + ' room=' + room + ' min=' + min);
    });
    return { min, hoursLen, checked: cells.length, bad };
  });
  expect(audit.checked).toBeGreaterThan(0);
  expect(audit.bad).toEqual([]);
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
