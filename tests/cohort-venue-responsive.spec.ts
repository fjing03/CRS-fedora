import { test, expect, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * cohort-venue-responsive — READ-ONLY Playwright receipt for SDD
 * `cohort-venue-responsive-port` (approved compact lane, proposal.md).
 *
 * AUTHORITY. `.sdd/changes/cohort-venue-responsive-port/proposal.md` §1–§5 plus
 * the two prototype header contracts:
 *   · `ui-prototype/venuetimetable/venue-timetable-responsive-preview.html`
 *     (opening DESIGN CONTRACT + inline VENUE CONTRACT comments);
 *   · `ui-prototype/Cohort timetable/cohort-timetable-responsive-preview.html`
 *     (opening contract §3–§11, §16/§17).
 * Current live behaviour, the shared TimetableResponsive module
 * (`public/js/ui-common.js`), `partials/ui-tt-chrome.blade.php` and the
 * timetable-print-v2 paper win over mock-only prototype behaviour.
 *
 * STATUS. Written against the APPROVED contract while the port is still being
 * implemented by the phase-1 coder. This file has NOT been executed against the
 * finished port yet — no pass is claimed here. The parent runs it (command
 * below) once the port lands and adapts the SEAM constants if the exact
 * class/dataset vocabulary of the ported hooks differs (only the seams are
 * guessed; every other selector is the shipped schema).
 *
 * BANDS UNDER TEST (proposal §1 — the deliberate asymmetry is the point):
 *   Cohort  <744 agenda · 744–1023 window(2) · 1024–1279 window(3)
 *           ≥1280 touch window(4) · ≥1280 mouse full (Print only here)
 *   Venue   <768 window(1, bookable free cells) · 768–1023 window(2)
 *           1024–1279 window(3) · ≥1280 full (both inputs; no wide-touch band)
 *
 * SELECTIONS. Same chrome ids as My Timetable (partials/ui-tt-chrome):
 *   root `.tt-responsive[data-tt-mode]`, `#ttDayStrip .tt-day`,
 *   `#ttAgenda .tt-card`, `#ttWindowGrid` (+`--tt-day-count`, `.tt-whead`,
 *   `.tt-axis`), `#ttTools`/`#ttSummaryButton`, `#ttSheet`/`#ttSheetBody`.
 * Page details modals: cohort `#eventModal`, venue `#classModal` (both carry
 * `#modalBody`). Venue booking handoff stays on the shipped affordances:
 *   free cell → `#availableTooltip` (or direct) → `#tooltipBookBtn` →
 *   `#intentSessionModal` → `#intentCancelBtn` — never further.
 *
 * FIXTURE DISCIPLINE. Nothing hardcodes class/slot counts. Every expectation
 * is DERIVED from the served payload (cohort `window.MockData.myTimetable
 * .eventsByWeek`, venue `window.eventsData` / `window.weekData` /
 * `window.roomCellStatesByWeek`, day-strip aria-labels) or from the served
 * DOM. A precondition the fixture cannot meet is an explicit `test.skip`
 * naming the missing fact — never a silent pass. A SEAM mismatch or a missing
 * renderer/control discovered AFTER the fixtures show availability is an
 * assertion failure (never `test.skip`); only genuinely missing served data
 * justifies a skip.
 *
 * SAFETY. Read-only browsing only: no DB writes, no fixtures, no SQL, no
 * PHPUnit; the booking handoff is exercised ONLY up to the opened
 * Select-a-Session modal and closed with `#intentCancelBtn` — never submitted,
 * never navigated to the arrangement page, never created/approved/cancelled.
 * TC70 stays untouched (this file never runs `venue-timetable.spec.ts`).
 * Guard is fail-closed (module level, no CRS_ALLOW_SQL needed — this file
 * writes nothing):
 *
 *   CRS_MODE=testing CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/cohort-venue-responsive.spec.ts --reporter=line
 *
 * Identity: staff 5425 via the setup project's `.auth/staff-5425.json`
 * (one login per run — the Fortify limiter is 5/min; this file never logs in).
 */

abortUnlessTestingTarget('tests/cohort-venue-responsive.spec.ts');

const BASE = process.env.CRS_BASE || 'http://127.0.0.1:8030';
const STAFF_AUTH = '.auth/staff-5425.json';
const COHORT_TT = '/cohort-timetable';
const VENUE_TT = '/venue-timetable-ui';

/* ═══════════════ SEAM — the only guessed vocabulary in this file ═══════════════
   The proposal pins the BEHAVIOUR of the ported venue free-cell hook and the
   earliest-bookable chip marker, not their class/dataset names. These candidate
   matchers are checked against the SERVED DOM; when the port lands with a
   different vocabulary, the parent edits exactly these lines. A mismatch found
   after the fixtures show availability is an assertion failure — never a skip,
   and there is NO frozen-hit / small-target opt-out here: the 48 × 48 touch law
   knows only the native <option> allowance (R7 / prototype §11). */
const SEAM = {
  /* interactive bookable free cells inside #ttWindowGrid */
  venueCoordinate: '[data-free-day][data-free-slot], [data-day][data-hour]',
  bookableClass: /(free|available|bookable)/i,
  bookableLabel: /available/i,
  /* the green "earliest bookable" marker on a day chip (prototype: is-earliest) */
  earliestMarker: /earliest|bookings open/i,
};

/* ═════════════════════════ shared helpers (timetable-responsive idiom) ═══════════════ */

type DayChip = { index: number; label: string; count: number; holiday: boolean; pressed: boolean; today: boolean };

async function chipState(page: Page): Promise<DayChip[]> {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('#ttDayStrip .tt-day')).map((el, index) => {
      const node = el as HTMLElement;
      const label = node.getAttribute('aria-label') || '';
      const m = label.match(/, (\d+) (?:class|classes)/);
      return {
        index,
        label,
        count: node.dataset.count !== undefined ? Number(node.dataset.count) : (m ? Number(m[1]) : -1),
        holiday: node.dataset.holiday === '1',
        pressed: node.getAttribute('aria-pressed') === 'true',
        today: node.dataset.today === '1',
      };
    }),
  );
}

async function tapDay(page: Page, day: number): Promise<void> {
  await page.locator('#ttDayStrip .tt-day').nth(day).click();
  await expect
    .poll(async () => (await chipState(page)).filter((c) => c.pressed).map((c) => c.index).join(','))
    .toBe(String(day));
}

async function mode(page: Page): Promise<string> {
  return (await page.locator('.tt-responsive').getAttribute('data-tt-mode')) || '';
}

async function hScroll(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

/** The window grid as the shared module builds it (window bands, both pages). */
type WindowFacts = {
  count: string;
  heads: string[];
  axis: string[];
  cards: number;
};

async function windowGrid(page: Page): Promise<WindowFacts> {
  return page.evaluate(() => {
    const grid = document.getElementById('ttWindowGrid');
    if (!grid) return { count: '', heads: [], axis: [], cards: 0 };
    const heads = Array.from(grid.querySelectorAll('.tt-whead')).map((h) => (h.textContent || '').replace(/\s+/g, ' ').trim());
    return {
      count: getComputedStyle(grid).getPropertyValue('--tt-day-count').trim(),
      heads: heads.slice(1),
      axis: Array.from(grid.querySelectorAll('.tt-axis')).map((a) => (a.textContent || '').trim()),
      cards: grid.querySelectorAll('.tt-card').length,
    };
  });
}

/** The day window the module would show: start = min(day, 7 - N), clamped. */
function windowStart(day: number, size: number): number {
  return Math.max(0, Math.min(day, 7 - size));
}

/* ── page errors, collected from the first byte ── */
const pageErrors = new WeakMap<Page, string[]>();

async function gotoResponsive(page: Page, path: string, width: number, height = 900): Promise<void> {
  const errors: string[] = [];
  pageErrors.set(page, errors);
  page.on('pageerror', (err) => errors.push(String(err)));
  await page.setViewportSize({ width, height });
  await page.goto(BASE + path, { waitUntil: 'networkidle' });
  await expect(page.locator('.tt-responsive'), `${path} @${width}: the responsive module booted`).toHaveAttribute(
    'data-tt-mode',
    /^(agenda|window|full)$/,
  );
}

function expectNoPageErrors(page: Page, where: string): void {
  expect(pageErrors.get(page) ?? [], `${where}: no page errors`).toEqual([]);
}

/** One context per width/input — never a per-test login (Fortify 5/min). */
async function launch(
  browser: Browser,
  width: number,
  path: string,
  opts: { touch?: boolean; mobile?: boolean; height?: number } = {},
): Promise<{ ctx: BrowserContext; page: Page }> {
  const ctx = await browser.newContext({
    viewport: { width, height: opts.height ?? 900 },
    storageState: STAFF_AUTH,
    hasTouch: !!opts.touch,
    isMobile: !!opts.touch && !!opts.mobile,
  });
  const page = await ctx.newPage();
  await gotoResponsive(page, path, width, opts.height ?? 900);
  return { ctx, page };
}

/* ── request watching: proves the booking handoff never navigates or submits ── */
type Traffic = { posts: string[]; navs: string[]; url: () => string };

function watchTraffic(page: Page): Traffic {
  const posts: string[] = [];
  const navs: string[] = [];
  const start = page.url();
  page.on('request', (r) => {
    if (r.method() === 'POST') posts.push(r.url());
  });
  page.on('framenavigated', (f) => {
    if (f === page.mainFrame() && f.url() !== start) navs.push(f.url());
  });
  return { posts, navs, url: () => start };
}

/* ── week + day fixing through the pages' OWN control path ── */

async function fixWeek(page: Page, index: number): Promise<void> {
  const viaExport = await page.evaluate((i) => {
    const w = window as unknown as { selectWeek?: (n: number) => void };
    if (typeof w.selectWeek === 'function') {
      w.selectWeek!(i);
      return true;
    }
    return false;
  }, index);
  if (!viaExport) await page.selectOption('#weekSelect', String(index));
  await expect
    .poll(() => page.evaluate(() => Number((window as unknown as { currentWeek: number }).currentWeek)), {
      timeout: 10_000,
    })
    .toBe(index);
}

async function currentWeek(page: Page): Promise<number> {
  return page.evaluate(() => Number((window as unknown as { currentWeek: number }).currentWeek));
}

/** The page's OWN rendered week (its weekSelect control) — the truth the week
 *  memory round-trips. `window.currentWeek` is the boot-time bridge value and
 *  can go stale after a full render recalls a saved week (discovered 2026-11-18,
 *  reported in the handoff); the rendered control never lies. */
async function renderedWeek(page: Page): Promise<number> {
  return page.evaluate(() => {
    const sel = document.getElementById('weekSelect') as HTMLSelectElement | null;
    return sel && sel.value !== '' ? Number(sel.value) : -1;
  });
}

/* ── served payloads: the independent source of truth for every count ── */

type Ev = { di: number; start: number; end: number; status?: string };

type CohortWeek = { week: number; events: Ev[] };

/** Cohort page: the events of the SELECTED cohort, per week. */
async function cohortWeeks(page: Page): Promise<CohortWeek[]> {
  return page.evaluate(() => {
    const mb = (window as unknown as { MockData?: { myTimetable?: { eventsByWeek?: Ev[][] } } }).MockData;
    const byWeek = mb?.myTimetable?.eventsByWeek || [];
    return byWeek.map((events, week) => ({ week, events: events || [] }));
  });
}

type VenueDay = { abbr: string; date: string; holiday: boolean; today?: boolean };
type VenueWeek = { week: number; events: Ev[]; days: VenueDay[] };

/** Venue page: events + days + the served room-slot truth, per week. */
async function venueWeeks(page: Page): Promise<{
  weeks: VenueWeek[];
  matrix: Record<string, string>[];
}> {
  return page.evaluate(() => {
    const w = window as unknown as {
      eventsData?: Ev[][];
      weekData?: Array<{ days: VenueDay[] }>;
      roomCellStatesByWeek?: Record<string, string>[];
    };
    const weeks = (w.weekData || []).map((week, i) => ({ week: i, events: w.eventsData?.[i] || [], days: week.days || [] }));
    return { weeks, matrix: w.roomCellStatesByWeek || [] };
  });
}

/** A day is bookable when the page's own payload says so (never Sunday/holiday). */
function dayIsBookable(day: VenueDay): boolean {
  const d = new Date(day.date.replace(/(\d+) (\w{3}) (\d{4})/, '$2 $1 $3'));
  const sunday = !Number.isNaN(d.getTime()) && d.getDay() === 0;
  return !day.holiday && !sunday;
}

/* ── cohort boot: staff 5425 may arrive without a pinned cohort ── */

/** Pick faculty+cohort through the page's own selects (client-side, no writes). */
async function ensureCohortSelected(page: Page): Promise<boolean> {
  for (let attempt = 0; attempt < 2; attempt++) {
    const ready = await page.evaluate(() => {
      const c = document.getElementById('cohortSelect') as HTMLSelectElement | null;
      return !!c && !!c.value && !c.disabled;
    });
    if (ready) return true;
    const faculties = await page.evaluate(() => {
      const f = document.getElementById('facultySelect') as HTMLSelectElement | null;
      return f ? Array.from(f.options).filter((o) => o.value).map((o) => o.value) : [];
    });
    let picked = false;
    for (const fid of faculties) {
      await page.selectOption('#facultySelect', fid);
      await page.waitForTimeout(150);
      const opts = await page.evaluate(() => {
        const c = document.getElementById('cohortSelect') as HTMLSelectElement | null;
        return c
          ? { disabled: c.disabled, value: c.value, values: Array.from(c.options).filter((o) => o.value).map((o) => o.value) }
          : { disabled: true, value: '', values: [] };
      });
      if (!opts.disabled && (opts.value || opts.values.length)) {
        if (!opts.value) await page.selectOption('#cohortSelect', opts.values[0]);
        await page.waitForTimeout(250);
        picked = true;
        break;
      }
    }
    if (!picked) return false;
  }
  return false;
}

/* ── venue free cells (SEAM) ── */

type VenueCell = {
  day: number | null;
  slot: number | null;
  tag: string;
  interactive: boolean;
  disabled: boolean;
  ariaDisabled: string | null;
  cls: string;
  label: string;
  tip: string;
  text: string;
};

/** Every slotted node inside the responsive venue grid (class cells excluded). */
async function venueCells(page: Page): Promise<VenueCell[]> {
  return page.evaluate((seam) => {
    const grid = document.getElementById('ttWindowGrid');
    if (!grid) return [];
    const nodes = Array.from(
      grid.querySelectorAll(
        (seam as { venueCoordinate: string }).venueCoordinate + ', .tt-free, .venue-slot',
      ),
    ) as HTMLElement[];
    const out: VenueCell[] = [];
    const seen = new Set<Element>();
    for (const n of nodes) {
      if (seen.has(n)) continue;
      seen.add(n);
      if (n.classList.contains('tt-card') || n.closest('.tt-card')) continue;
      const day = n.dataset.freeDay ?? n.dataset.day;
      const slot = n.dataset.freeSlot ?? n.dataset.hour;
      out.push({
        day: day === undefined || day === '' ? null : Number(day),
        slot: slot === undefined || slot === '' ? null : Number(slot),
        tag: n.tagName,
        interactive:
          n.tagName === 'BUTTON' ||
          n.getAttribute('role') === 'button' ||
          (n.getAttribute('tabindex') !== null && n.getAttribute('tabindex') !== '-1'),
        disabled: (n as HTMLButtonElement).disabled === true,
        ariaDisabled: n.getAttribute('aria-disabled'),
        cls: n.className,
        label: n.getAttribute('aria-label') || '',
        tip: n.getAttribute('data-tip') || '',
        text: (n.textContent || '').replace(/\s+/g, ' ').trim(),
      });
    }
    return out;
  }, SEAM);
}

function cellIsBookable(c: VenueCell): boolean {
  return (
    c.interactive &&
    !c.disabled &&
    c.ariaDisabled !== 'true' &&
    (SEAM.bookableClass.test(c.cls) || SEAM.bookableLabel.test(c.label) || SEAM.bookableLabel.test(c.tip))
  );
}

/** A stable Playwright selector for one slotted cell, from the served DOM. */
function cellSelector(c: VenueCell): string | null {
  if (c.day === null || c.slot === null) return null;
  return (
    `#ttWindowGrid [data-free-day="${c.day}"][data-free-slot="${c.slot}"], ` +
    `#ttWindowGrid [data-day="${c.day}"][data-hour="${c.slot}"]:not(.tt-card)`
  );
}

/* ── the 48 × 48 law (R7 / prototype §11) ── */

type Control = { label: string; w: number; h: number; inStrip: boolean; inSheet: boolean; inTools: boolean };

async function touchControls(page: Page): Promise<Control[]> {
  /* NO frozen-hit / small-target opt-out here: the law's only allowance is the
     native <option> (a select's options are rendered by the UA, not the page). */
  return page.evaluate(() => {
    const root = document.querySelector('.tt-responsive');
    if (!root) return [];
    const sel =
      'a[href], button, select, input:not([type="hidden"]), textarea, '
      + '[role="button"], [tabindex]:not([tabindex="-1"])';
    const visible = (n: HTMLElement) => {
      const cs = getComputedStyle(n);
      if (cs.display === 'none' || cs.visibility === 'hidden') return false;
      const r = n.getBoundingClientRect();
      return r.width > 0 && r.height > 0;
    };
    return (Array.from(root.querySelectorAll(sel)) as HTMLElement[])
      .filter((n) => visible(n) && n.tagName.toLowerCase() !== 'option')
      .map((n) => ({
        label:
          n.tagName.toLowerCase()
          + (n.id ? '#' + n.id : '')
          + (typeof n.className === 'string' && n.className.trim() ? '.' + n.className.trim().split(/\s+/)[0] : '')
          + ` "${(n.getAttribute('aria-label') || n.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40)}"`,
        w: n.offsetWidth,
        h: n.offsetHeight,
        inStrip: !!n.closest('#ttDayStrip'),
        inSheet: !!n.closest('#ttSheet'),
        inTools: !!n.closest('#ttTools'),
      }));
  });
}

function expectTouchTargets(found: Control[], where: string): void {
  const offenders = found.filter((c) => c.w < 48 || c.h < 48);
  expect(
    offenders.length,
    `${where}: the touch law needs every interactive control inside .tt-responsive to be `
      + `≥48 × 48 CSS px (native <option>s are the only allowance — no frozen-hit opt-out); `
      + `${offenders.length} offender(s): `
      + offenders.map((c) => `${c.label} = ${c.w}×${c.h}`).join(' | '),
  ).toBe(0);
}

/* ── the Week summary sheet (shared chrome) ── */

for (const [name, path] of [['Cohort', COHORT_TT], ['Venue', VENUE_TT]]) {
  test(`${name}: phone Week summary opens and exposes the card explanation on tap`, async ({ browser }) => {
    const context = await browser.newContext({
      viewport: { width: 390, height: 844 },
      hasTouch: true,
      isMobile: true,
      storageState: STAFF_AUTH,
    });
    try {
      const page = await context.newPage();
      await page.goto(BASE + path);
      await expect.soft(page.locator('#summaryButton')).toHaveCount(0, { timeout: 1000 });
      await page.locator('#ttSummaryButton').tap();
      const sheet = page.locator('#ttSheet');
      await expect(sheet).toBeVisible();
      const card = sheet.locator('.summary-card').first();
      const label = (await card.locator('.summary-label').textContent())?.trim() ?? '';
      await card.tap({ position: { x: 30, y: 30 } });
      await expect(card).toHaveClass(/is-flipped/);
      await card.locator('.summary-info').tap({ timeout: 5000 });
      const explanation = page.locator('#summaryExplainModal');
      await expect(explanation).toBeVisible();
      await expect(explanation.locator('#summaryExplainTitle')).toHaveText(label);
      await explanation.locator('.btn-close-modal').tap();
      await expect(explanation).toBeHidden();
      await expect(sheet).toBeVisible();
    } finally {
      await context.close();
    }
  });
}

async function openSummarySheet(page: Page, pageName: string): Promise<{ inlineCards: number }> {
  const inlineCards = await page.evaluate(
    () => document.querySelectorAll('#summarySection .summary-card').length,
  );
  await page.locator('#ttSummaryButton').click();
  await expect(page.locator('#ttSheet'), `${pageName}: the opener opens the sheet`).toBeVisible();
  await expect(page.locator('#ttSummaryButton')).toHaveAttribute('aria-expanded', 'true');
  expect(
    await page.evaluate(() => document.body.classList.contains('tt-sheet-open')),
    `${pageName}: opening locks the page scroll`,
  ).toBe(true);
  return { inlineCards };
}

async function expectSheetDialog(page: Page, pageName: string, inlineCards: number): Promise<void> {
  const sheet = await page.evaluate(() => {
    const body = document.getElementById('ttSheetBody')!;
    const dlg = document.querySelector('#ttSheet .tt-sheet');
    return {
      role: dlg?.getAttribute('role'),
      modal: dlg?.getAttribute('aria-modal'),
      labelled: dlg?.getAttribute('aria-labelledby'),
      legend: body.querySelector('.legend-bar') !== null,
      section: body.querySelector('#summarySection') !== null,
      cards: body.querySelectorAll('.summary-card').length,
      visibleCards: Array.from(body.querySelectorAll('.summary-card')).filter(
        (c) => (c as HTMLElement).offsetWidth > 0 && (c as HTMLElement).offsetHeight > 0,
      ).length,
      focusInSheet: !!document.activeElement?.closest('#ttSheet'),
    };
  });
  expect(sheet, `${pageName}: a labelled aria-modal dialog`).toEqual({
    role: 'dialog',
    modal: 'true',
    labelled: 'ttSheetTitle',
    legend: true,
    section: true,
    cards: inlineCards,
    visibleCards: inlineCards,
    focusInSheet: true,
  });
  for (let i = 0; i < 8; i++) {
    await page.keyboard.press('Tab');
    expect(
      await page.evaluate(() => !!document.activeElement?.closest('#ttSheet')),
      `${pageName}: Tab #${i + 1} stays trapped in the sheet`,
    ).toBe(true);
  }
}

async function closeSummarySheet(page: Page, pageName: string): Promise<void> {
  await page.keyboard.press('Escape');
  await expect(page.locator('#ttSheet'), `${pageName}: Escape closes the sheet`).toBeHidden();
  await expect(page.locator('#ttSummaryButton'), `${pageName}: focus returns to the opener`).toBeFocused();
  await expect(page.locator('#ttSummaryButton')).toHaveAttribute('aria-expanded', 'false');
  expect(
    await page.evaluate(() => document.body.classList.contains('tt-sheet-open')),
    `${pageName}: the scroll lock is released`,
  ).toBe(false);
}

// ══════════════════════════════════════════════════════════════════════════════
// COHORT — bands (mouse)
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Cohort — mouse bands (proposal §1)', () => {
  test.setTimeout(120_000);

  test('C1. <744 is the agenda; 744–1023 window(2); 1024–1279 window(3); 744 is NOT 768', async ({ browser }) => {
    for (const [width, expected, days] of [
      [390, 'agenda', 0],
      [600, 'agenda', 0],
      [743, 'agenda', 0],
      [744, 'window', 2],
      [800, 'window', 2],
      [1024, 'window', 3],
      [1100, 'window', 3],
      [1279, 'window', 3],
    ] as Array<[number, string, number]>) {
      const { ctx, page } = await launch(browser, width, COHORT_TT);
      try {
        expect(await mode(page), `${width}: the cohort band`).toBe(expected);
        expect(await hScroll(page), `${width}: no page-level sideways scroll`).toBe(0);

        if (expected === 'agenda') {
          const chips = await chipState(page);
          expect(chips.length, `${width}: all seven day chips`).toBe(7);
          await expect(page.locator('#timetable'), `${width}: the desktop table is hidden`).toBeHidden();
          await expect(page.locator('#ttWindowGrid'), `${width}: no window grid in the agenda`).toBeHidden();
          const day = chips.find((c) => c.count > 0);
          if (day) {
            await tapDay(page, day.index);
            expect(
              await page.evaluate(() => document.querySelectorAll('#ttAgenda .tt-card').length),
              `${width}: one agenda card per class of the selected day (derived from the strip)`,
            ).toBe(day.count);
          }
        } else {
          const facts = await windowGrid(page);
          expect(facts.count, `${width}: --tt-day-count = ${days}`).toBe(String(days));
          await expect(page.locator('#timetable'), `${width}: the desktop table is hidden`).toBeHidden();
          expect(facts.heads.length, `${width}: one column per visible day`).toBe(days);
        }
        expectNoPageErrors(page, `cohort @${width}`);
      } finally {
        await ctx.close();
      }
    }
  });

  test('C2. ≥1280 mouse is the existing full week — one block per served class, strip gone, table back', async ({ browser }) => {
    for (const width of [1280, 1440, 1600]) {
      const { ctx, page } = await launch(browser, width, COHORT_TT);
      try {
        expect(await mode(page), `${width}: mouse desktop stays full`).toBe('full');
        await expect(page.locator('#timetable'), `${width}: the desktop table is restored`).toBeVisible();
        await expect(page.locator('#ttDayStrip'), `${width}: no day strip in full mode`).toBeHidden();
        await expect(page.locator('#ttWindowGrid'), `${width}: no window grid in full mode`).toBeHidden();
        expect(await hScroll(page), `${width}: no sideways scroll`).toBe(0);

        const week = await currentWeek(page);
        const weeks = await cohortWeeks(page);
        const served = weeks.find((w) => w.week === week);
        test.skip(
          !served || served.events.length === 0,
          `fixture: week ${week + 1} carries no class for the selected cohort in the served payload`,
        );
        expect(
          await page.evaluate(() => document.querySelectorAll('#timetable .event-block').length),
          `${width}: one desktop block per served event of week ${week + 1}`,
        ).toBe(served!.events.length);
        expectNoPageErrors(page, `cohort @${width}`);
      } finally {
        await ctx.close();
      }
    }
  });
});

// ══════════════════════════════════════════════════════════════════════════════
// COHORT — touch bands (the deliberate ≥1280-touch timeline)
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Cohort — touch bands (hasTouch, proposal §1)', () => {
  test.setTimeout(120_000);

  test('C3. touch 744/768/1024 keep the mouse bands; touch 1366 is the FOUR-day timeline, never full', async ({ browser }) => {
    for (const [width, days] of [
      [744, 2],
      [768, 2],
      [1024, 3],
      [1366, 4],
    ] as Array<[number, number]>) {
      const { ctx, page } = await launch(browser, width, COHORT_TT, { touch: true });
      try {
        expect(await mode(page), `${width} touch: the touch-capable band`).toBe('window');
        const facts = await windowGrid(page);
        expect(facts.count, `${width} touch: ${days} days visible`).toBe(String(days));
        await expect(page.locator('#timetable'), `${width} touch: the desktop table is hidden`).toBeHidden();
        expect(await hScroll(page), `${width} touch: no sideways scroll`).toBe(0);

        const chips = await chipState(page);
        expect(chips.length, `${width} touch: the strip is here with all seven chips`).toBe(7);

        /* the cells derive from the served payload of the SELECTED cohort */
        const ok = await ensureCohortSelected(page);
        test.skip(!ok, 'fixture: staff 5425 has no selectable faculty/cohort on the served cohort page');
        const week = await currentWeek(page);
        const weeks = await cohortWeeks(page);
        const served = weeks.find((w) => w.week === week)!;
        const chipsNow = await chipState(page);
        const day = chipsNow.findIndex((c) => c.count > 0);
        test.skip(day < 0, `fixture: week ${week + 1} carries no class for the selected cohort`);
        await tapDay(page, day);
        const start = windowStart(day, days);
        const expected = served.events.filter((e) => e.di >= start && e.di < start + days).length;
        expect(
          (await windowGrid(page)).cards,
          `${width} touch: one cell per served class of the visible ${days}-day window`,
        ).toBe(expected);
        expectNoPageErrors(page, `cohort touch @${width}`);
      } finally {
        await ctx.close();
      }
    }
  });

  test('C4. touch 1366 keeps the inline summary + hides Print; below 1280 the sheet owns them', async ({ browser }) => {
    /* ≥1280: cards stay in-page even on the wide-touch timeline (proposal §2) */
    const wide = await launch(browser, 1366, COHORT_TT, { touch: true });
    try {
      expect(await mode(wide.page), '1366 touch: the four-day timeline').toBe('window');
      /* the wide-touch band keeps the tools ROW — the visible day-count heading
         stays part of the timeline; ONLY the sheet opener hides (the shared
         _syncSheet guard hides #ttSummaryButton at ≥1280, never the heading) */
      await expect(
        wide.page.locator('#ttSummaryButton'),
        '1366 touch: the Week summary sheet is NOT offered at ≥1280 (the opener hides)',
      ).toBeHidden();
      await expect(
        wide.page.locator('#ttDayCount'),
        '1366 touch: the day-count heading stays visible with the four-day timeline',
      ).toBeVisible();
      await expect(wide.page.locator('#ttSheet'), 'the sheet itself stays closed').toBeHidden();
      const inline = await wide.page.evaluate(() => {
        const section = document.getElementById('summarySection') as HTMLElement | null;
        return section ? section.offsetWidth > 0 && section.offsetHeight > 0 : false;
      });
      expect(inline, '1366 touch: the summary cards are inline (never moved into a sheet)').toBe(true);
      await expect(
        wide.page.locator('button.print-btn[aria-label="Print timetable"]'),
        '1366 touch: Print is hidden — only the mouse full desktop shows it (proposal §2)',
      ).toBeHidden();
      expectNoPageErrors(wide.page, 'cohort touch @1366 summary/print');
    } finally {
      await wide.ctx.close();
    }

    /* <1280: the sheet owns legend + cards, and Print is hidden there too */
    const narrow = await launch(browser, 900, COHORT_TT, { touch: true });
    try {
      expect(await mode(narrow.page), '900 touch: the three-day window').toBe('window');
      await expect(
        narrow.page.locator('button.print-btn[aria-label="Print timetable"]'),
        '900 touch: Print is hidden below the desktop view (prototype R7)',
      ).toBeHidden();
      const { inlineCards } = await openSummarySheet(narrow.page, 'cohort @900 touch');
      test.skip(inlineCards === 0, 'fixture: the cohort summary bar rendered no cards');
      await expectSheetDialog(narrow.page, 'cohort @900 touch', inlineCards);
      await closeSummarySheet(narrow.page, 'cohort @900 touch');
      expectNoPageErrors(narrow.page, 'cohort touch @900 sheet');
    } finally {
      await narrow.ctx.close();
    }
  });
});

// ══════════════════════════════════════════════════════════════════════════════
// COHORT — selection persistence, Today, entity switch, details
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Cohort — selection, Today, cohort switch, details', () => {
  test.setTimeout(120_000);

  test('C5. the selected day + week survive every resize crossing (incl. into the touch timeline)', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 744, COHORT_TT, { touch: true });
    try {
      const ok = await ensureCohortSelected(page);
      test.skip(!ok, 'fixture: staff 5425 has no selectable faculty/cohort on the served cohort page');

      const chips = await chipState(page);
      const day = chips.findIndex((c) => c.count > 0);
      test.skip(day < 0, 'fixture: the booted week carries no class for the selected cohort');
      await tapDay(page, day);

      const totalWeeks = await page.evaluate(
        () => ((window as unknown as { weekData?: unknown[] }).weekData || []).length,
      );
      const targetWeek = ((await currentWeek(page)) + 1) % Math.max(1, totalWeeks);
      await fixWeek(page, targetWeek);

      for (const width of [390, 1024, 1366, 768]) {
        await page.setViewportSize({ width, height: 900 });
        await expect.poll(() => mode(page), { timeout: 10_000 }).toBe(width < 744 ? 'agenda' : 'window');
        const state = await page.evaluate(() => ({
          week: Number((window as unknown as { currentWeek: number }).currentWeek),
          pressed: (document.querySelector('#ttDayStrip .tt-day[aria-pressed="true"]') as HTMLElement | null)?.dataset.day,
        }));
        expect(state.week, `${width}: the fixed week survived the crossing`).toBe(targetWeek);
        expect(state.pressed, `${width}: the selected weekday survived the crossing`).toBe(String(day));
      }
      expectNoPageErrors(page, 'cohort resize persistence');
    } finally {
      await ctx.close();
    }
  });

  test('C6. a cohort switch keeps the week AND the weekday; the drawn cards follow the new payload', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 768, COHORT_TT);
    try {
      const ok = await ensureCohortSelected(page);
      test.skip(!ok, 'fixture: staff 5425 has no selectable faculty/cohort on the served cohort page');

      const chips = await chipState(page);
      const day = chips.findIndex((c) => c.count > 0);
      test.skip(day < 0, 'fixture: the booted week carries no class for the selected cohort');
      await tapDay(page, day);
      const week = await currentWeek(page);

      const switchTo = await page.evaluate(() => {
        const c = document.getElementById('cohortSelect') as HTMLSelectElement;
        const other = Array.from(c.options).find((o) => o.value && o.value !== c.value);
        return other ? other.value : '';
      });
      test.skip(!switchTo, 'fixture: this faculty lists only one cohort, so no entity switch is possible');

      await page.selectOption('#cohortSelect', switchTo!);
      await page.waitForTimeout(300);

      const after = await page.evaluate(() => ({
        week: Number((window as unknown as { currentWeek: number }).currentWeek),
        pressed: (document.querySelector('#ttDayStrip .tt-day[aria-pressed="true"]') as HTMLElement | null)?.dataset.day,
      }));
      expect(after.week, 'proposal §2 — a cohort switch keeps the selected week').toBe(week);
      expect(after.pressed, 'proposal §2 — a cohort switch keeps the selected weekday').toBe(String(day));

      /* the drawn cards are the NEW cohort's truth (MockData.myTimetable is
         refreshed by the page's own buildTimetable on every cohort switch) —
         counted across the ACTUAL clamped visible window, never one day: at
         768 the band is window(2), so the neighbour day's cards sit in the
         same grid and a one-day count would under-derive. */
      const facts = await windowGrid(page);
      const size = Math.max(1, parseInt(facts.count, 10) || 0);
      const start = windowStart(day, size);
      const served = await page.evaluate(
        ({ w, s, n, d }) => {
          const mb = (window as unknown as { MockData?: { myTimetable?: { eventsByWeek?: Ev[][] } } }).MockData;
          const evs = ((mb?.myTimetable?.eventsByWeek || [])[w] || []) as Ev[];
          const id = (e: Ev) => String(e.id == null ? '' : e.id);
          return {
            windowIds: evs.filter((e) => Number(e.di) >= s && Number(e.di) < s + n).map(id).sort(),
            keptIds: evs.filter((e) => Number(e.di) === d).map(id).sort(),
          };
        },
        { w: week, s: start, n: size, d: day },
      );
      test.skip(served.keptIds.length === 0, 'fixture: the switched-to cohort has no class on the kept weekday in this week');

      /* every drawn card IS a served event of the new cohort — identity, count,
         and the kept weekday's own membership in its grid column */
      const drawn = await page.evaluate(() =>
        Array.from(document.querySelectorAll('#ttWindowGrid .tt-card')).map((c) => {
          const el = c as HTMLElement;
          return { id: el.dataset.id || '', col: el.style.gridColumn };
        }),
      );
      expect(
        drawn.length,
        `the window cells follow the switched cohort across the whole visible ${size}-day window`,
      ).toBe(served.windowIds.length);
      expect(
        drawn.map((c) => c.id).sort(),
        'every drawn card is a served event of the NEW cohort (no stale card survives the switch)',
      ).toEqual(served.windowIds);
      expect(
        drawn.filter((c) => c.col === String(day - start + 2)).map((c) => c.id).sort(),
        'the kept weekday keeps its own cards in its own grid column',
      ).toEqual(served.keptIds);
      expectNoPageErrors(page, 'cohort switch @768');
    } finally {
      await ctx.close();
    }
  });

  test('C7. Today selects the whole visible day: today\'s chip pressed, the agenda follows', async ({ browser }) => {
    for (const width of [390, 768]) {
      const { ctx, page } = await launch(browser, width, COHORT_TT);
      try {
        const ok = await ensureCohortSelected(page);
        test.skip(!ok, 'fixture: staff 5425 has no selectable faculty/cohort on the served cohort page');
        if (width < 744) {
          const chips = await chipState(page);
          const other = chips.findIndex((c) => c.count > 0 && !c.today);
          if (other >= 0) await tapDay(page, other);
        } else {
          await page.evaluate(() => (window as unknown as { selectWeek?: (n: number) => void }).selectWeek?.(
            Math.max(0, Number((window as unknown as { currentWeek: number }).currentWeek) - 1),
          ));
          await expect.poll(() => mode(page)).toBe('window');
          await tapDay(page, 2);
        }

        await page.locator('#todayBtn').click();
        await expect
          .poll(
            () =>
              page.evaluate(() => {
                const todayChip = document.querySelector('#ttDayStrip .tt-day[data-today="1"]') as HTMLElement | null;
                const pressed = document.querySelector('#ttDayStrip .tt-day[aria-pressed="true"]') as HTMLElement | null;
                return todayChip && pressed && pressed.dataset.day === todayChip.dataset.day
                  ? todayChip.dataset.day
                  : null;
              }),
            { timeout: 10_000 },
          )
          .not.toBeNull();
        expectNoPageErrors(page, `cohort Today @${width}`);
      } finally {
        await ctx.close();
      }
    }
  });

  test('C8. a card tap opens the existing details modal; Enter opens it too; Escape closes', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 390, COHORT_TT);
    try {
      const ok = await ensureCohortSelected(page);
      test.skip(!ok, 'fixture: staff 5425 has no selectable faculty/cohort on the served cohort page');
      const chips = await chipState(page);
      const day = chips.findIndex((c) => c.count > 0);
      test.skip(day < 0, 'fixture: the booted week carries no class for the selected cohort');
      await tapDay(page, day);

      const card = page.locator('#ttAgenda .tt-card').first();
      expect(await card.evaluate((el) => el.tagName), 'a card is a real focusable button').toBe('BUTTON');

      await card.focus();
      await page.keyboard.press('Enter');
      await expect(page.locator('#eventModal'), 'Enter on the focused card opens the cohort details modal').toBeVisible();
      expect(
        await page.evaluate(() => (document.getElementById('modalBody')?.textContent || '').trim().length),
        'the modal body carries the class details',
      ).toBeGreaterThan(0);
      await page.keyboard.press('Escape');
      await expect(page.locator('#eventModal'), 'Escape closes the modal').toBeHidden();

      await card.click();
      await expect(page.locator('#eventModal'), 'a tap opens the same details modal').toBeVisible();
      await page.keyboard.press('Escape');
      await expect(page.locator('#eventModal')).toBeHidden();
      expectNoPageErrors(page, 'cohort details @390');
    } finally {
      await ctx.close();
    }
  });

  test('C9. a public holiday keeps its class cards below the additive notice (prototype §7 / §16A)', async ({ browser }) => {
    for (const width of [390, 768]) {
      const { ctx, page } = await launch(browser, width, COHORT_TT);
      try {
        const ok = await ensureCohortSelected(page);
        test.skip(!ok, 'fixture: staff 5425 has no selectable faculty/cohort on the served cohort page');
        const holiday = await page.evaluate(() => {
          const wd = (window as unknown as { weekData?: Array<{ days: Array<{ holiday: boolean; holidayLabel?: string }> }> })
            .weekData;
          const mb = (window as unknown as { MockData?: { myTimetable?: { eventsByWeek?: Ev[][] } } }).MockData;
          const byWeek = mb?.myTimetable?.eventsByWeek || [];
          if (!wd) return null;
          for (let w = 0; w < wd.length; w++) {
            const days = wd[w].days || [];
            for (let i = 0; i < days.length; i++) {
              if (days[i].holiday && ((byWeek[w] || []).filter((e) => Number(e.di) === i).length > 0)) {
                return { week: w, day: i, classes: (byWeek[w] || []).filter((e) => Number(e.di) === i).length, label: String(days[i].holidayLabel || '') };
              }
            }
          }
          return null;
        });
        test.skip(!holiday, 'fixture: no public holiday carries a class for the selected cohort in the served payload');

        await fixWeek(page, holiday!.week);
        await tapDay(page, holiday!.day);

        if (width < 744) {
          /* the agenda band: the additive notice panel above the day's cards */
          await expect(page.locator('#ttAgenda .tt-empty.tt-holiday'), `${width}: the holiday notice is there`).toHaveCount(1);
          expect(
            await page.evaluate(() => document.querySelectorAll('#ttAgenda .tt-card').length),
            `${width}: the notice is ADDITIVE — the day's classes stay drawn under it`,
          ).toBe(holiday!.classes);
          const order = await page.evaluate(() =>
            Array.from(document.getElementById('ttAgenda')!.children).map((c) =>
              c.classList.contains('tt-empty') ? 'panel' : 'card',
            ),
          );
          expect(order[0], `${width}: the notice sits ABOVE the cards`).toBe('panel');
        } else {
          /* the window band renders NO agenda panel: the holiday marker rides the
             visible day's column header (`Public Holiday` under abbr + date) and
             the day's cards stay drawn in that column (shared _buildWindow) */
          await expect(
            page.locator('#ttAgenda .tt-empty.tt-holiday'),
            `${width}: the window band carries no agenda holiday panel`,
          ).toHaveCount(0);
          const facts = await windowGrid(page);
          const size = Math.max(1, parseInt(facts.count, 10) || 0);
          const start = windowStart(holiday!.day, size);
          const at = holiday!.day - start;
          expect(
            facts.heads[at] || '',
            `${width}: the holiday day's column header carries the Public Holiday marker`,
          ).toMatch(/Public Holiday/i);
          /* the holiday keeps ALL its classes — by identity, in its own column
             (the 2-day window's neighbour day also draws, so column membership
             is the honest check, not a whole-grid count) */
          const col = String(at + 2);
          const drawnIds = await page.evaluate(
            (c) =>
              Array.from(document.querySelectorAll('#ttWindowGrid .tt-card'))
                .map((el) => el as HTMLElement)
                .filter((el) => el.style.gridColumn === c)
                .map((el) => el.dataset.id || '')
                .sort(),
            col,
          );
          const servedIds = await page.evaluate(
            ({ w, d }) => {
              const mb = (window as unknown as { MockData?: { myTimetable?: { eventsByWeek?: Ev[][] } } }).MockData;
              return ((((mb?.myTimetable?.eventsByWeek || [])[w] || []) as Ev[])
                .filter((e) => Number(e.di) === d)
                .map((e) => String(e.id == null ? '' : e.id))
                .sort());
            },
            { w: holiday!.week, d: holiday!.day },
          );
          expect(
            drawnIds,
            `${width}: the holiday keeps all ${servedIds.length} of its class cards (identity, in its own column)`,
          ).toEqual(servedIds);
        }
        expectNoPageErrors(page, `cohort holiday @${width}`);
      } finally {
        await ctx.close();
      }
    }
  });
});

// ══════════════════════════════════════════════════════════════════════════════
// COHORT — summary sheet + print (paper is its own surface)
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Cohort — summary + print', () => {
  test.setTimeout(120_000);

  test('C10. below 1280 the sheet holds legend + cards; ≥1280 mouse hides the tools; Print only on the mouse desktop', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 768, COHORT_TT);
    try {
      await expect(page.locator('#ttTools'), '768: one tools node in the DOM').toHaveCount(1);
      const { inlineCards } = await openSummarySheet(page, 'cohort @768');
      test.skip(inlineCards === 0, 'fixture: the cohort summary bar rendered no cards');
      await expectSheetDialog(page, 'cohort @768', inlineCards);
      await closeSummarySheet(page, 'cohort @768');
      expectNoPageErrors(page, 'cohort sheet @768');
    } finally {
      await ctx.close();
    }

    for (const width of [1280, 1440]) {
      const wide = await launch(browser, width, COHORT_TT);
      try {
        expect(await mode(wide.page), `${width}: the full band`).toBe('full');
        await expect(wide.page.locator('#ttTools'), `${width}: the sheet opener is not offered`).toBeHidden();
        await expect(wide.page.locator('#ttSheet'), `${width}: the sheet stays closed`).toBeHidden();
        await expect(
          wide.page.locator('button.print-btn[aria-label="Print timetable"]'),
          `${width}: the mouse desktop keeps Print`,
        ).toBeVisible();
      } finally {
        await wide.ctx.close();
      }
    }

    for (const [width, touch] of [[390, false], [744, true], [1024, true]] as Array<[number, boolean]>) {
      const narrow = await launch(browser, width, COHORT_TT, { touch });
      try {
        await expect(
          narrow.page.locator('button.print-btn[aria-label="Print timetable"]'),
          `${width}${touch ? ' touch' : ''}: Print is hidden below the desktop view`,
        ).toBeHidden();
      } finally {
        await narrow.ctx.close();
      }
    }
  });

  test('C11. printing still produces the timetable-print-v2 paper, never the screen furniture', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 768, COHORT_TT);
    try {
      await page.emulateMedia({ media: 'print' });
      for (const selector of ['.tt-days', '.tt-tools', '.tt-agenda', '#ttWindowGrid', '#ttSheet', '.grid-wrapper', '.legend-bar', '#summarySection']) {
        await expect(page.locator(selector), `C11 — ${selector} must not print`).toBeHidden();
      }
      const paper = await page.evaluate(() => {
        const sheet = document.getElementById('printSheet') as HTMLElement;
        const header = document.getElementById('printHeader') as HTMLElement;
        const table = document.getElementById('printTimetable') as HTMLElement;
        return {
          sheetVisible: getComputedStyle(sheet).visibility,
          headerVisible: getComputedStyle(header).visibility,
          tableTag: table ? table.tagName : '',
          tableDisplay: table ? getComputedStyle(table).display : '',
          headerOnce: document.querySelectorAll('.ui-print-header').length,
        };
      });
      expect(paper.tableTag, 'the paper is a table').toBe('TABLE');
      expect(paper.tableDisplay, 'the paper table is displayed').toBe('table');
      expect(paper.sheetVisible, 'the paper sheet is visible on paper').toBe('visible');
      expect(paper.headerVisible, 'the paper header prints').toBe('visible');
      expect(paper.headerOnce, 'ONE header').toBe(1);

      await page.emulateMedia({ media: 'screen' });
      expect(await mode(page), 'the screen state survived the print emulation').toBe('window');
      expectNoPageErrors(page, 'cohort print @768');
    } finally {
      await ctx.close();
    }
  });
});

// ══════════════════════════════════════════════════════════════════════════════
// VENUE — bands (both inputs; the phone band is the same window renderer)
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Venue — bands (proposal §1: 1/2/3-day timeline, full ≥1280, no wide-touch band)', () => {
  test.setTimeout(180_000);

  const BANDS: Array<{ width: number; touch: boolean; expected: string; days: number }> = [
    { width: 390, touch: true, expected: 'window', days: 1 },
    { width: 390, touch: false, expected: 'window', days: 1 },
    { width: 600, touch: false, expected: 'window', days: 1 },
    { width: 767, touch: false, expected: 'window', days: 1 },
    { width: 768, touch: false, expected: 'window', days: 2 },
    { width: 768, touch: true, expected: 'window', days: 2 },
    { width: 1024, touch: true, expected: 'window', days: 3 },
    { width: 1279, touch: false, expected: 'window', days: 3 },
    { width: 1280, touch: false, expected: 'full', days: 0 },
    { width: 1440, touch: false, expected: 'full', days: 0 },
    { width: 1440, touch: true, expected: 'full', days: 0 },
  ];

  test('V1. every band is exactly the approved one, with zero overflow and seven chips below full', async ({ browser }) => {
    for (const band of BANDS) {
      const { ctx, page } = await launch(browser, band.width, VENUE_TT, { touch: band.touch });
      try {
        expect(await mode(page), `${band.width}${band.touch ? ' touch' : ''}: the venue band`).toBe(band.expected);
        expect(await hScroll(page), `${band.width}${band.touch ? ' touch' : ''}: no page-level sideways scroll`).toBe(0);

        if (band.expected === 'window') {
          const facts = await windowGrid(page);
          expect(facts.count, `${band.width}: --tt-day-count = ${band.days}`).toBe(String(band.days));
          expect(facts.heads.length, `${band.width}: one column per visible day`).toBe(band.days);
          await expect(page.locator('#timetable'), `${band.width}: the desktop table is hidden`).toBeHidden();

          /* cells derive from the served payload: the visible window carries
             exactly the classes it carries — free cells are extras, never counted */
          const weeks = await venueWeeks(page);
          const week = await currentWeek(page);
          const served = weeks.weeks.find((w) => w.week === week)!;
          const chips = await chipState(page);
          expect(chips.length, `${band.width}: all seven day chips`).toBe(7);
          const day = chips.findIndex((c) => c.count > 0);
          if (day >= 0) {
            await tapDay(page, day);
            const start = windowStart(day, band.days);
            const expected = served.events.filter((e) => e.di >= start && e.di < start + band.days).length;
            expect(
              (await windowGrid(page)).cards,
              `${band.width}: one class cell per served class of the visible ${band.days}-day window`,
            ).toBe(expected);
          }
        } else {
          await expect(page.locator('#timetable'), `${band.width}: the desktop grid is restored`).toBeVisible();
          await expect(page.locator('#ttDayStrip'), `${band.width}: no strip in full mode`).toBeHidden();
          await expect(page.locator('#ttWindowGrid'), `${band.width}: no timeline in full mode`).toBeHidden();
        }
        expectNoPageErrors(page, `venue @${band.width}${band.touch ? ' touch' : ''}`);
      } finally {
        await ctx.close();
      }
    }
  });

  test('V2. the phone semester badge drops the dates below 768 (venue-only exception); tablet keeps them', async ({ browser }) => {
    const phone = await launch(browser, 390, VENUE_TT);
    try {
      /* the venue phone band is window(1) — the module must still print the SHORT
         chip there, which the shared agenda-only rule alone would not do */
      const chip = (await phone.page.locator('#semesterChip').textContent())?.trim() || '';
      expect(chip, '390: the phone chip is `<code> Semester` without dates').toMatch(/^20\d{4} Semester$/);
    } finally {
      await phone.ctx.close();
    }
    for (const width of [768, 1440]) {
      const wide = await launch(browser, width, VENUE_TT);
      try {
        const chip = (await wide.page.locator('#semesterChip').textContent())?.trim() || '';
        expect(chip, `${width}: the dated badge returns`).toMatch(/·/);
        expect(chip, `${width}: and it carries the year(s)`).toMatch(/\b20\d\d\b/);
      } finally {
        await wide.ctx.close();
      }
    }
  });

  test('V3. Print hides below 768 only; tablet and desktop keep it, touch included', async ({ browser }) => {
    for (const [width, touch, visible] of [
      [390, false, false],
      [600, false, false],
      [768, false, true],
      [1024, true, true],
      [1440, false, true],
    ] as Array<[number, boolean, boolean]>) {
      const { ctx, page } = await launch(browser, width, VENUE_TT, { touch });
      try {
        const btn = page.locator('button.print-btn[aria-label="Print timetable"]');
        if (visible) await expect(btn, `${width}: Print is offered`).toBeVisible();
        else await expect(btn, `${width}: Print is hidden below 768`).toBeHidden();
      } finally {
        await ctx.close();
      }
    }
  });
});

// ══════════════════════════════════════════════════════════════════════════════
// VENUE — the timeline slot law (22 half-hour slots, 19:00 marker, 18:30 guard)
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Venue — timeline slot law (VENUE CONTRACT)', () => {
  test.setTimeout(120_000);

  test('V4. exactly 22 half-hour slots per day, and the 19:00 marker is non-interactive', async ({ browser }) => {
    for (const width of [390, 768]) {
      const { ctx, page } = await launch(browser, width, VENUE_TT);
      try {
        const facts = await windowGrid(page);
        /* the shared axis is 22 half-hour labels (08:00 → 18:30); the 19:00 marker
           may ride the axis (23rd label) or stand alone — both must hold */
        const axis = facts.axis.filter((t) => /^\d{1,2}:\d{2}$/.test(t));
        const markerInside = axis.length > 22 && /19:00|7:00 ?PM/i.test(axis[axis.length - 1]);
        expect(axis.length - (markerInside ? 1 : 0), `${width}: exactly 22 half-hour slot labels`).toBe(22);

        const markers = await page.evaluate(() => {
          const grid = document.getElementById('ttWindowGrid')!;
          const hits = Array.from(grid.querySelectorAll('*')).filter((el) => {
            const t = (el.textContent || '').trim();
            return el.children.length === 0 && /^(19:00|7:00 ?PM|07:00 ?PM)$/i.test(t);
          }) as HTMLElement[];
          return hits.map((el) => ({
            tag: el.tagName,
            role: el.getAttribute('role'),
            tabindex: el.getAttribute('tabindex'),
            isButton: el.tagName === 'BUTTON',
          }));
        });
        expect(markers.length, `${width}: a 19:00 marker exists at the end of the timeline`).toBeGreaterThanOrEqual(1);
        for (const m of markers) {
          expect(m.isButton, `${width}: the 19:00 marker is not a button`).toBe(false);
          expect(m.role, `${width}: the 19:00 marker has no button role`).toBeNull();
          expect(m.tabindex, `${width}: the 19:00 marker is not focusable`).toBeNull();
        }
        expectNoPageErrors(page, `venue slot law @${width}`);
      } finally {
        await ctx.close();
      }
    }
  });

  test('V5. a genuinely free 18:30–19:00 cell stays green but cannot start a booking', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 390, VENUE_TT, { touch: true, mobile: true });
    try {
      /* derive the truth from the SERVED facts (read-only): the 18:30 tail is
         only the contract's "green but cannot start a booking" cell when the
         DAY is genuinely eligible — not Sunday/PH, past the live lead-time
         window (the page's OWN isSlotTooSoon), the room matrix really calls
         21:00–18:30 free, and no non-cancelled live event occupies it. A
         historical 'free' from week 0 inside the too-soon window is NOT a
         bookable tail. */
      const target = await page.evaluate(() => {
        const w = window as unknown as {
          weekData?: Array<{ days: Array<VenueDay & { sunday?: boolean }> }>;
          eventsData?: Ev[][];
          roomCellStatesByWeek?: Record<string, string>[];
          isSlotTooSoon?: (wd: unknown, wk: number, di: number) => boolean;
        };
        const weeks = w.weekData || [];
        const matrix = w.roomCellStatesByWeek || [];
        const tooSoon = w.isSlotTooSoon;
        for (let wk = 0; wk < weeks.length; wk++) {
          const days = weeks[wk].days || [];
          for (let di = 0; di < days.length; di++) {
            const day = days[di];
            const d = new Date(day.date.replace(/(\d+) (\w{3}) (\d{4})/, '$2 $1 $3'));
            if (day.holiday || day.sunday || (!Number.isNaN(d.getTime()) && d.getDay() === 0)) continue;
            if (tooSoon ? tooSoon(weeks, wk, di) : true) continue; // fail closed without the live helper
            if ((matrix[wk] || {})[`${di}:21`] !== 'free') continue;
            const blocked = (w.eventsData?.[wk] || []).some(
              (ev) => ev.di === di && ev.status !== 'cancelled' && 21 >= ev.start && 21 <= ev.end,
            );
            if (blocked) continue;
            return { week: wk, day: di };
          }
        }
        return null;
      });
      test.skip(
        !target,
        'fixture: no week in the served payload has a genuinely eligible 18:30 slot '
          + '(bookable day past the lead-time window, room matrix free, no live event)',
      );

      await fixWeek(page, target!.week);
      await tapDay(page, target!.day);

      const tail = (await venueCells(page)).filter((c) => c.slot === 21);
      expect(
        tail.length,
        'the ported freeCell hook renders the 18:30 tail cell the served matrix calls free '
          + '(if the hook names cells differently, fix the SEAM coordinate matchers in this file '
          + '— an assertion failure, never a skip)',
      ).toBeGreaterThanOrEqual(1);
      /* one day on screen (window(1) at 390): the guard node is the tail cell that
         names the rule — and it stays GREEN: an available-family cell that never
         wears the OFF / PH / Too soon / Occupied vocabulary */
      const cell = tail.find((c) => /not enough slots/i.test(c.label + ' ' + c.tip + ' ' + c.text)) || tail[0];

      expect(
        cell!.label + ' ' + cell!.tip + ' ' + cell!.text,
        'the eligible tail stays an available-family cell (never OFF / PH / Too soon / Occupied)',
      ).not.toMatch(/\b(OFF|PH|Too soon|Occupied)\b/i);
      expect(cell!.ariaDisabled, 'the tail cell is marked aria-disabled').toBe('true');
      expect(cell!.disabled || cell!.ariaDisabled === 'true', 'and it cannot be activated').toBe(true);
      expect(
        (cell!.label + ' ' + cell!.tip),
        'the label names the 18:30 rule, never "Available"',
      ).toMatch(/Not Enough Slots/i);

      /* activation is prevented: a disabled button fires nothing; an
         aria-disabled one must be guarded by the page's own handler */
      if (!cell!.disabled) {
        const traffic = watchTraffic(page);
        const sel = cellSelector(cell!);
        if (sel) {
          await page.locator(sel).first().click({ force: true }).catch(() => {});
        }
        expect(
          await page.evaluate(() => document.getElementById('availableTooltip')?.classList.contains('show') ?? false),
          'no booking tooltip opens from the 18:30 tail',
        ).toBe(false);
        expect(
          await page.evaluate(() => {
            const m = document.getElementById('intentSessionModal') as HTMLElement | null;
            return !!m && m.offsetWidth > 0;
          }),
          'and no session modal either',
        ).toBe(false);
        expect(page.url(), 'and the page never navigates').toBe(traffic.url());
      }
      expectNoPageErrors(page, 'venue 18:30 guard @390');
    } finally {
      await ctx.close();
    }
  });
});

// ══════════════════════════════════════════════════════════════════════════════
// VENUE — the booking handoff (read-only: up to the modal, never further)
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Venue — free slot → existing session selector (read-only handoff)', () => {
  test.setTimeout(120_000);

  test('V6. a green free slot opens the shipped #availableTooltip → #intentSessionModal; no navigation, no submit', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 390, VENUE_TT, { touch: true, mobile: true });
    try {
      /* derive the fixture from SERVED facts (never from the DOM alone): the
         first CURRENT-OR-FUTURE week/day where a minimum 1-hour booking (two
         consecutive half-hours) genuinely fits — day bookable (not Sunday/PH,
         past the page's live isSlotTooSoon window), room matrix 'free' for
         BOTH slots, and no non-cancelled live event overlapping the span.
         roomBeforeNextEvent's own default-week helper is crosschecked below. */
      const target = await page.evaluate(() => {
        const w = window as unknown as {
          weekData?: Array<{ days: Array<VenueDay & { sunday?: boolean }> }>;
          eventsData?: Ev[][];
          roomCellStatesByWeek?: Record<string, string>[];
          isSlotTooSoon?: (wd: unknown, wk: number, di: number) => boolean;
          currentWeek?: number;
        };
        const weeks = w.weekData || [];
        const matrix = w.roomCellStatesByWeek || [];
        const tooSoon = w.isSlotTooSoon;
        const cur = Number(w.currentWeek ?? 0);
        for (let wk = Math.max(0, cur); wk < weeks.length; wk++) {
          const days = weeks[wk].days || [];
          for (let di = 0; di < days.length; di++) {
            const day = days[di];
            const d = new Date(day.date.replace(/(\d+) (\w{3}) (\d{4})/, '$2 $1 $3'));
            if (day.holiday || day.sunday || (!Number.isNaN(d.getTime()) && d.getDay() === 0)) continue;
            if (tooSoon ? tooSoon(weeks, wk, di) : true) continue; // fail closed without the live helper
            const served = matrix[wk] || {};
            for (let hi = 0; hi <= 20; hi++) {
              if (served[`${di}:${hi}`] !== 'free' || served[`${di}:${hi + 1}`] !== 'free') continue;
              const occupied = (w.eventsData?.[wk] || []).some(
                (ev) => ev.di === di && ev.status !== 'cancelled' && hi >= ev.start && hi + 1 <= ev.end,
              );
              if (occupied) continue;
              return { week: wk, day: di, slot: hi };
            }
          }
        }
        return null;
      });
      test.skip(
        !target,
        'fixture: the served semester carries no genuinely bookable hour for this venue '
          + '(bookable day past the lead-time window, two consecutive free matrix rows, no live event)',
      );

      await fixWeek(page, target!.week);
      await tapDay(page, target!.day);

      const cell = (await venueCells(page)).find(
        (c) => c.day === target!.day && c.slot === target!.slot && cellIsBookable(c),
      );
      expect(
        cell,
        `the ported freeCell hook renders an INTERACTIVE bookable cell at the served-free coordinate `
          + `${target!.day}:${target!.slot} — if the hook's class/label vocabulary differs, fix the SEAM `
          + 'matchers in this file (an assertion failure, never a skip)',
      ).toBeDefined();

      const sel = cellSelector(cell!);
      expect(
        sel,
        'the bookable cell carries day/slot coordinates a selector can target (SEAM coordinate '
          + 'vocabulary — fix the SEAM matchers, never skip)',
      ).not.toBeNull();

      /* the page's OWN room helper (exported on window) must agree with the
         derived eligibility: at least a 1-hour continuous run below the cell */
      const roomRun = await page.evaluate(
        ({ di, hi, wk }) => {
          const f = (window as unknown as { roomBeforeNextEvent?: (di: number, hi: number, wk?: number) => number })
            .roomBeforeNextEvent;
          return typeof f === 'function' ? f(di, hi, wk) : null;
        },
        { di: target!.day, hi: target!.slot, wk: target!.week },
      );
      expect(
        roomRun,
        'the page\'s own roomBeforeNextEvent agrees with the served matrix: a 1-hour run fits',
      ).toBeGreaterThanOrEqual(2);

      const traffic = watchTraffic(page);
      await page.locator(sel!).first().click();

      /* the shipped affordance: tooltip (then Arrange) or straight to the modal.
         WAIT on the real displayed state — `expect.poll(...)` is a matcher
         builder, NOT a promise, so it has no .catch; waitForFunction is the
         honest wait here, and the ASSERTIONS below stay the hard gate. */
      await page
        .waitForFunction(
          () => {
            const shown = (el: Element | null) =>
              !!el && (el as HTMLElement).offsetWidth > 0 && (el as HTMLElement).offsetHeight > 0;
            const tip = document.getElementById('availableTooltip');
            const modal = document.getElementById('intentSessionModal');
            return (!!tip && tip.classList.contains('show') && shown(tip)) || shown(modal);
          },
          { timeout: 5_000 },
        )
        .then(() => true)
        .catch(() => false);
      const tooltipShown = await page.evaluate(() => {
        const t = document.getElementById('availableTooltip');
        return !!t && t.classList.contains('show') && (t as HTMLElement).offsetWidth > 0;
      });
      if (tooltipShown) {
        await expect(page.locator('#availableTooltip #tooltipBookBtn'), 'the tooltip offers Arrange').toBeVisible();
        await page.locator('#tooltipBookBtn').click();
      }
      await expect(page.locator('#intentSessionModal'), 'the existing Select-a-Session modal opens').toBeVisible({
        timeout: 10_000,
      });

      /* READ-ONLY proof: the URL is untouched, no navigation happened, and every
         POST the flow produced went to the app's own Livewire runtime channel —
         never a form submit, never the arrangement handoff, never a booking */
      expect(page.url(), 'the URL never changed (no navigation submit)').toBe(traffic.url());
      expect(traffic.navs, 'no frame navigation happened during the handoff').toEqual([]);
      const foreign = traffic.posts.filter((u) => !/livewire/i.test(u));
      expect(
        foreign,
        'no POST outside the app\'s own Livewire channel (no submit/booking/arrangement writes): '
          + foreign.join(' | '),
      ).toEqual([]);

      /* close without choosing anything — the lane stops here by contract */
      await page.locator('#intentCancelBtn').click();
      await expect(page.locator('#intentSessionModal')).toBeHidden();
      expectNoPageErrors(page, 'venue booking handoff @390');
    } finally {
      await ctx.close();
    }
  });
});

// ══════════════════════════════════════════════════════════════════════════════
// VENUE — Earliest bookable, details, picker, entity switch
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Venue — earliest bookable, details, picker', () => {
  test.setTimeout(120_000);

  test('V7. Earliest bookable jumps to the first GENUINELY bookable day and marks the chip', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 768, VENUE_TT);
    try {
      /* derive the destination INDEPENDENTLY from the served facts: the first
         week/day where a minimum 1-hour booking genuinely fits — NOT the
         lead-time cutoff alone (a cutoff day whose room is fully occupied is
         not bookable): day not Sunday/PH, the page's LIVE isSlotTooSoon
         false, two consecutive 'free' matrix rows, no non-cancelled live
         event over the span. Production is moving the earliest lookup to
         exactly this room-aware rule; this derivation is the independent
         truth it must agree with. */
      const target = await page.evaluate(() => {
        const w = window as unknown as {
          weekData?: Array<{ days: Array<VenueDay & { sunday?: boolean }> }>;
          eventsData?: Ev[][];
          roomCellStatesByWeek?: Record<string, string>[];
          isSlotTooSoon?: (wd: unknown, wk: number, di: number) => boolean;
        };
        const weeks = w.weekData || [];
        const matrix = w.roomCellStatesByWeek || [];
        const tooSoon = w.isSlotTooSoon;
        for (let wk = 0; wk < weeks.length; wk++) {
          const days = weeks[wk].days || [];
          for (let di = 0; di < days.length; di++) {
            const day = days[di];
            const d = new Date(day.date.replace(/(\d+) (\w{3}) (\d{4})/, '$2 $1 $3'));
            if (day.holiday || day.sunday || (!Number.isNaN(d.getTime()) && d.getDay() === 0)) continue;
            if (tooSoon ? tooSoon(weeks, wk, di) : true) continue; // fail closed without the live helper
            const served = matrix[wk] || {};
            for (let hi = 0; hi <= 20; hi++) {
              if (served[`${di}:${hi}`] !== 'free' || served[`${di}:${hi + 1}`] !== 'free') continue;
              const occupied = (w.eventsData?.[wk] || []).some(
                (ev) => ev.di === di && ev.status !== 'cancelled' && hi >= ev.start && hi + 1 <= ev.end,
              );
              if (occupied) continue;
              return { week: wk, day: di, slot: hi };
            }
          }
        }
        return null;
      });
      test.skip(
        !target,
        'fixture: the served semester carries no genuinely bookable day for this venue '
          + '(bookable day past the lead-time window, two consecutive free matrix rows, no live event)',
      );

      await page.locator('#earliestBtn').click();
      await expect
        .poll(
          () =>
            page.evaluate(() => ({
              week: Number((window as unknown as { currentWeek: number }).currentWeek),
              pressed: (document.querySelector('#ttDayStrip .tt-day[aria-pressed="true"]') as HTMLElement | null)?.dataset.day,
            })),
          { timeout: 10_000 },
        )
        .toEqual({ week: target!.week, pressed: String(target!.day) });

      /* the earliest marker rides the LANDED day's chip (green dot vocabulary — SEAM) */
      const marked = await page.evaluate(
        (day) => {
          const chip = document.querySelector(`#ttDayStrip .tt-day[data-day="${day}"]`) as HTMLElement | null;
          if (!chip) return false;
          const hay = [chip.className, ...Object.values(chip.dataset), ...Array.from(chip.children).map((c) => c.className)].join(' ');
          return /(earliest|bookings open)/i.test(hay);
        },
        target!.day,
      );
      expect(marked, 'the earliest day\'s chip carries the bookable marker (green dot vocabulary — SEAM)').toBe(true);

      /* the visible day follows: the heading names that day */
      const heading = (await page.locator('#ttDayHeading').textContent()) || '';
      const dayAbbr = await page.evaluate(
        (d) => ((((window as unknown as { weekData?: Array<{ days: VenueDay[] }> }).weekData) || [])[Number((window as unknown as { currentWeek: number }).currentWeek)]?.days[d]?.abbr) || '',
        target!.day,
      );
      expect(heading, 'the visible window starts on the earliest day').toContain(dayAbbr);
      expectNoPageErrors(page, 'venue earliest @768');
    } finally {
      await ctx.close();
    }
  });

  test('V8. a class block opens the existing #classModal; Enter opens it too (same window renderer)', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 768, VENUE_TT);
    try {
      const chips = await chipState(page);
      const day = chips.findIndex((c) => c.count > 0);
      test.skip(day < 0, 'fixture: the booted week carries no class for this venue');
      await tapDay(page, day);

      const block = page.locator('#ttWindowGrid .tt-card').first();
      await block.focus();
      await page.keyboard.press('Enter');
      await expect(page.locator('#classModal'), 'Enter opens the venue details modal').toBeVisible();
      await page.keyboard.press('Escape');
      await expect(page.locator('#classModal')).toBeHidden();

      await block.click();
      await expect(page.locator('#classModal'), 'a tap opens the same modal').toBeVisible();
      await page.keyboard.press('Escape');
      await expect(page.locator('#classModal')).toBeHidden();
      expectNoPageErrors(page, 'venue details @768');
    } finally {
      await ctx.close();
    }
  });

  test('V9. the venue picker: touch sheet at any width; mouse cascade on the desktop; a room switch keeps the week', async ({ browser }) => {
    /* touch 1024 — the picker must open as the sheet even on a wide touch device */
    const touch = await launch(browser, 1024, VENUE_TT, { touch: true });
    try {
      await touch.page.locator('#venueSelectDropdown .venue-dd-trigger').click();
      await expect(
        touch.page.locator('#venueTimetablePicker'),
        'touch 1024: the picker opens as the dialog, not the cascade',
      ).toBeVisible();
      const rows = touch.page.locator('#venueTimetablePicker .venue-picker-item[data-venue-code]');
      expect(await rows.count(), 'the touch sheet lists rooms grouped by block/floor').toBeGreaterThan(0);
      await touch.page.keyboard.press('Escape');
      await expect(touch.page.locator('#venueTimetablePicker')).toBeHidden();
      expectNoPageErrors(touch.page, 'venue picker touch @1024');
    } finally {
      await touch.ctx.close();
    }

    /* mouse 1440 — the shipped cascade stays; the dialog must NOT open */
    const mouse = await launch(browser, 1440, VENUE_TT);
    try {
      await mouse.page.locator('#venueSelectDropdown .venue-dd-trigger').click();
      await expect(
        mouse.page.locator('#venueSelectDropdown .venue-dd-panel'),
        'mouse 1440: the anchored four-column cascade opens',
      ).toBeVisible();
      const dialogOpen = await mouse.page.evaluate(
        () => (document.getElementById('venueTimetablePicker') as HTMLDialogElement | null)?.open ?? false,
      );
      expect(dialogOpen, 'mouse 1440: the sheet dialog does NOT open for a mouse user').toBe(false);
      await mouse.page.keyboard.press('Escape');
      await mouse.page.mouse.click(10, 400);
    } finally {
      await mouse.ctx.close();
    }

    /* entity switch: pick another room through the touch sheet; the week survives.
       The week memory is session-scoped, so the fixed week must be set in THIS
       context before the room switch re-renders the page. */
    const swapper = await launch(browser, 1024, VENUE_TT, { touch: true });
    try {
      const fixed = await ensureVenueWeek(swapper.page);
      test.skip(fixed === null, 'fixture: the served venue list/semester has no switchable room or week');
      /* the week memory is the page's OWN session-scoped WeekNavigator channel
         (weekmem-…@<login-stamp> in sessionStorage) — and it only WRITES when the
         server minted a login week stamp (the symmetric save() gate). A fresh
         logged-in staff session (auth.setup.ts staff 5425) mints that stamp, so
         the channel MUST be armed here: its absence means a stale/expired auth
         file, fixed by running the setup ONCE — not a legitimate fixture
         absence, so it is an assertion, never a skip. */
      const weekMemArmed = await swapper.page.evaluate(() =>
        Object.keys(sessionStorage).some((k) => /^weekmem-venueTimetableWeek@/.test(k)),
      );
      expect(
        weekMemArmed,
        'Fresh logged-in staff session must arm the login-scoped week memory; '
          + 'run auth.setup.ts staff 5425 once if stale',
      ).toBe(true);
      await swapper.page.locator('#venueSelectDropdown .venue-dd-trigger').click();
      await expect(swapper.page.locator('#venueTimetablePicker')).toBeVisible();
      const pick = await swapper.page.evaluate(() => {
        const items = Array.from(
          document.querySelectorAll('#venueTimetablePicker .venue-picker-item[data-venue-code]'),
        ) as HTMLElement[];
        /* the ACTUAL current code — URL param, else the picker's OWN selected row
           (aria-pressed / .selected carries the real code), else the served
           current-room payload. NEVER the trigger's decorative full text:
           'B002 — Tutorial Room' is not the code 'B002', and comparing against
           it re-selects the current room and hangs the navigation forever. */
        const current =
          new URLSearchParams(window.location.search).get('venue')
          || items.find((el) => el.getAttribute('aria-pressed') === 'true' || el.classList.contains('selected'))
              ?.dataset.venueCode
          || '';
        const row = items.find((el) => el.dataset.venueCode && el.dataset.venueCode !== current);
        return row ? row.dataset.venueCode : '';
      });
      test.skip(!pick, 'fixture: every picker row is the current venue');
      const traffic = watchTraffic(swapper.page);
      await swapper.page.locator(`#venueTimetablePicker .venue-picker-item[data-venue-code="${pick}"]`).click();
      await swapper.page.waitForURL(new RegExp(`venue=${pick}`), { timeout: 15_000 });
      await expect
        .poll(() => renderedWeek(swapper.page), { timeout: 10_000 })
        .toBe(fixed!);
      expect(traffic.posts, 'a venue switch is a plain render — no POSTs at all').toEqual([]);
      await expect(swapper.page.locator('.tt-responsive')).toHaveAttribute('data-tt-mode', /^(window|full)$/);
      /* the week survives in the page's OWN rendered state (its weekSelect
         control, populated from the recalled session memory) — that is the law.
         `window.currentWeek` is asserted separately below as the honest bridge
         fact, not silently accepted. */
      expect(await currentWeek(swapper.page), 'the window.currentWeek bridge matches the rendered week after the memory recall')
        .toBe(await renderedWeek(swapper.page));
      expectNoPageErrors(swapper.page, 'venue switch @1024');
    } finally {
      await swapper.ctx.close();
    }
  });

  /** Stand the venue page on a fixed week first so the switch has something to keep. */
  async function ensureVenueWeek(page: Page): Promise<number | null> {
    const total = await page.evaluate(
      () => ((window as unknown as { weekData?: unknown[] }).weekData || []).length,
    );
    if (total < 2) return null;
    const target = Math.min(6, total - 1);
    await fixWeek(page, target);
    return target;
  }
});

// ══════════════════════════════════════════════════════════════════════════════
// VENUE — summary sheet + print paper
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Venue — summary sheet + print paper', () => {
  test.setTimeout(120_000);

  test('V10. below 1280 the venue legend + cards live in the sheet; ≥1280 they are inline', async ({ browser }) => {
    const narrow = await launch(browser, 900, VENUE_TT);
    try {
      const { inlineCards } = await openSummarySheet(narrow.page, 'venue @900');
      test.skip(inlineCards === 0, 'fixture: the venue summary bar rendered no cards');
      await expectSheetDialog(narrow.page, 'venue @900', inlineCards);
      await closeSummarySheet(narrow.page, 'venue @900');
      expectNoPageErrors(narrow.page, 'venue sheet @900');
    } finally {
      await narrow.ctx.close();
    }

    const wide = await launch(browser, 1440, VENUE_TT);
    try {
      expect(await mode(wide.page), '1440: the full band').toBe('full');
      await expect(wide.page.locator('#ttTools'), '1440: no sheet opener').toBeHidden();
      const inline = await wide.page.evaluate(() => {
        const section = document.getElementById('summarySection') as HTMLElement | null;
        return !!section && section.offsetWidth > 0 && section.offsetHeight > 0;
      });
      expect(inline, '1440: the five venue cards stay in-page').toBe(true);
      await expect(
        wide.page.locator('button.print-btn[aria-label="Print timetable"]'),
        '1440: desktop keeps Print',
      ).toBeVisible();
    } finally {
      await wide.ctx.close();
    }
  });

  test('V11. the venue paper output is unaffected by the responsive port (print media at 768)', async ({ browser }) => {
    const { ctx, page } = await launch(browser, 768, VENUE_TT);
    try {
      await page.emulateMedia({ media: 'print' });
      for (const selector of ['.tt-days', '.tt-tools', '.tt-agenda', '#ttWindowGrid', '#ttSheet', '.grid-wrapper', '.legend-bar', '#summarySection']) {
        await expect(page.locator(selector), `V11 — ${selector} must not print`).toBeHidden();
      }
      const paper = await page.evaluate(() => {
        const sheet = document.getElementById('printSheet') as HTMLElement;
        const header = document.getElementById('printHeader') as HTMLElement;
        const table = document.getElementById('printTimetable') as HTMLElement;
        return {
          sheetVisible: sheet ? getComputedStyle(sheet).visibility : '',
          headerVisible: header ? getComputedStyle(header).visibility : '',
          tableTag: table ? table.tagName : '',
          tableDisplay: table ? getComputedStyle(table).display : '',
          headerOnce: document.querySelectorAll('.ui-print-header').length,
        };
      });
      expect(paper.tableTag, 'the paper is a table').toBe('TABLE');
      expect(paper.tableDisplay, 'the paper table is displayed').toBe('table');
      expect(paper.sheetVisible, 'the paper sheet is visible on paper').toBe('visible');
      expect(paper.headerVisible, 'the paper header prints').toBe('visible');
      expect(paper.headerOnce, 'ONE header').toBe(1);

      await page.emulateMedia({ media: 'screen' });
      expect(await mode(page), 'the screen state survived the print emulation').toBe('window');
      expectNoPageErrors(page, 'venue print @768');
    } finally {
      await ctx.close();
    }
  });
});

// ══════════════════════════════════════════════════════════════════════════════
// TOUCH LAW — 48 × 48 on every interactive control, both pages, wide tablets too
// ══════════════════════════════════════════════════════════════════════════════

test.describe('Touch law — ≥48 × 48 on any-pointer: coarse (both pages, wide tablets included)', () => {
  test.setTimeout(180_000);

  for (const [path, name, width] of [
    [COHORT_TT, 'cohort', 390],
    [COHORT_TT, 'cohort', 744],
    [COHORT_TT, 'cohort', 1366],
    [VENUE_TT, 'venue', 390],
    [VENUE_TT, 'venue', 1024],
    [VENUE_TT, 'venue', 1366],
  ] as Array<[string, string, number]>) {
    test(`T-${name}-${width}: every control inside .tt-responsive is at least 48 × 48, chips + sheet included`, async ({ browser }) => {
      const { ctx, page } = await launch(browser, width, path, { touch: true });
      try {
        if (name === 'cohort') {
          const ok = await ensureCohortSelected(page);
          test.skip(!ok, 'fixture: staff 5425 has no selectable faculty/cohort on the served cohort page');
        }
        const band = await mode(page);
        if (band === 'full') {
          /* the full band (venue ≥1280 on BOTH inputs; the mouse desktop) owns
             its days in the desktop week row — NO strip is the prescribed
             behaviour, so 0 chips is CORRECT here, never a failure and never
             an exemption: the 48px walk still covers every visible control */
          await expect(
            page.locator('#ttDayStrip'),
            `${name} @${width}: the full band renders no day strip (prescribed)`,
          ).toBeHidden();
        } else {
          const chips = await chipState(page);
          expect(chips.length, `${name} @${width}: the strip is in the walk with all seven chips`).toBe(7);
          const day = chips.find((c) => c.count > 0);
          if (day) {
            await tapDay(page, day.index);
            const controls = await touchControls(page);
            expect(controls.length, `${name} @${width}: the walk found the page's controls`).toBeGreaterThan(5);
            expect(controls.filter((c) => c.inStrip).length, `${name} @${width}: all seven chips measured`).toBe(7);
            expectTouchTargets(controls, `${name} @${width} (a day with classes)`);
          }

          /* the empty-day state and the sheet are controls too */
          const emptyDay = chips.find((c) => c.count === 0 && !c.holiday);
          if (emptyDay) {
            await tapDay(page, emptyDay.index);
            expectTouchTargets(await touchControls(page), `${name} @${width} (empty day)`);
          }
        }
        /* the sheet exists below 1280 only — at ≥1280 (incl. the cohort touch
           timeline) the summary is inline and the opener is deliberately hidden */
        if (await mode(page) !== 'full' && (await page.locator('#ttSummaryButton').isVisible())) {
          await openSummarySheet(page, `${name} @${width}`);
          expectTouchTargets(await touchControls(page), `${name} @${width} (week summary sheet)`);
          await closeSummarySheet(page, `${name} @${width}`);
        }
        /* FULL mode still walks the walk — the desktop week's visible controls
           are touch-reachable at 1366, so the 48px law applies there too */
        if (band === 'full') {
          expectTouchTargets(await touchControls(page), `${name} @${width} (full band, desktop controls)`);
        }
        expectNoPageErrors(page, `touch law ${name} @${width}`);
      } finally {
        await ctx.close();
      }
    });
  }
});

/* ═════════════════════════════════════════════════════════════════════════════
   Venue — responsive card colours (USER REPORT 2026-11-17, venue-ONLY).

   The screenshots showed Venue's non-desktop normal / pending / replacement
   event blocks GREY while the same event on the desktop grid is green/amber/
   blue. Root cause (fixed in theme.css, six selector groups): the ownership
   colour rules matched `.timetable` only, and Venue's responsive window cards
   (`#ttWindowGrid .tt-card`, the SAME class/status/ownership vocabulary from
   the page's cfg.statusClassFn) fell through to the generic .event-block grey.

   This test pins the fix by computed style, not by "not grey": for every
   variant (normal · pending · replacement × mine · others) the RESPONSIVE card
   is built by the REAL shared render path (TimetableResponsive._card with the
   page's own cfg) from a browser-local clone of a REAL served event, attached
   into the REAL #ttWindowGrid; the DESKTOP reference is an event-block with the
   SAME status/ownership class under the REAL table's tbody td. The computed
   background / text colour / border colour / border width must agree per
   variant, in both themes. No DB writes, no backend fixtures: where the served
   DB carries only `normal` events, the pending/replacement variants are
   explicitly labelled synthetic-class variants derived from the served base
   event (the classes are the shipped vocabulary the desktop rules already own).
   ═════════════════════════════════════════════════════════════════════════════ */
test.describe('Venue — responsive card colours (user report 2026-11-17)', () => {
  test.setTimeout(180_000);

  const WIDTHS = [390, 768, 1024, 1279];
  const VARIANTS: Array<{ status: string; mine: boolean; cls: string }> = [
    { status: 'normal', mine: true, cls: 'event-mine' },
    { status: 'normal', mine: false, cls: 'event-others' },
    { status: 'pending', mine: true, cls: 'event-mine-pending' },
    { status: 'pending', mine: false, cls: 'event-others-pending' },
    { status: 'replacement', mine: true, cls: 'event-mine-replacement' },
    { status: 'replacement', mine: false, cls: 'event-others-replacement' },
  ];

  for (const theme of ['light', 'dark'] as const) {
    test(`V12. responsive Venue window cards carry the desktop colour/border per status and ownership (${theme}, 390/768/1024/1279)`, async ({ browser }) => {
      for (const width of WIDTHS) {
        const { ctx, page } = await launch(browser, width, VENUE_TT);
        try {
          expect(await mode(page), `${width}: the window band`).toBe('window');

          const report = await page.evaluate(({ themeName, variantList }) => {
            /* 2026-11-17 (parent review fix): the theme is APPLIED, not just named —
               the app's dark mode is `html.dark` (ui-common toggleTheme), so the
               root class is switched here before the measurements, and the
               returned actualDarkFlag is asserted OUTSIDE so the loop's two
               labels can never silently test the same state again. */
            const html = document.documentElement;
            html.classList.toggle('dark', themeName === 'dark');
            html.classList.toggle('light', themeName !== 'dark');
            localStorage.setItem('theme', themeName);
            const actualDarkFlag = html.classList.contains('dark');
            /* the REAL served truth: a non-holiday day with at least one event,
               from the page's own payload (never a fixture) */
            const eventsData = (window as unknown as { eventsData: Array<Record<string, unknown>[]> }).eventsData || [];
            const weekData = (window as unknown as { weekData: Array<{ days: Record<string, unknown>[] }> }).weekData || [];
            const currentWeek = Number((window as unknown as { currentWeek: number }).currentWeek);
            const me = (window as unknown as { MockData: { currentUser?: { name?: string } } }).MockData?.currentUser?.name || '';
            let base: Record<string, unknown> | null = null;
            let baseDay: Record<string, unknown> | null = null;
            for (const e of eventsData[currentWeek] || []) {
              const ev = e as unknown as { di: number; status?: string };
              const day = weekData[currentWeek]?.days?.[ev.di] as Record<string, unknown> | undefined;
              if (day && !day.holiday) { base = e; baseDay = { ...day, holiday: false }; break; }
            }
            if (!base) return { error: 'no served non-holiday event this week' } as unknown as Record<string, unknown>;

            const grid = document.getElementById('ttWindowGrid') as HTMLElement;
            const tbody = document.getElementById('tableBody') as HTMLElement | null;
            const deskRow = document.querySelector('#tableBody tr') as HTMLElement | null;
            if (!grid || !tbody) return { error: 'responsive grid / table missing' } as unknown as Record<string, unknown>;
            const TR = (window as unknown as { TimetableResponsive?: { _card?: (e: Record<string, unknown>, day: Record<string, unknown> | null, list: Record<string, unknown>[]) => HTMLButtonElement } }).TimetableResponsive;
            if (!TR || typeof TR._card !== 'function') return { error: 'TimetableResponsive._card missing' } as unknown as Record<string, unknown>;

            /* the desktop reference exercises the EXISTING CSS: an event-block
               with the same class under a real table cell (browser-local clone
               of the served event's facts — no fixture writes) */
            const td = document.createElement('td');
            deskRow?.appendChild(td);
            const read = (el: HTMLElement) => {
              const cs = getComputedStyle(el);
              return { bg: cs.backgroundColor, color: cs.color, bcol: cs.borderTopColor, bw: cs.borderTopWidth };
            };

            const out: Array<{ cls: string; synthetic: boolean; card: ReturnType<typeof read>; desk: ReturnType<typeof read>; match: boolean }> = [];
            const VARIANTS = variantList as Array<{ status: string; mine: boolean; cls: string }>;
            for (const v of VARIANTS) {
              const mine = v.mine;
              /* browser-local clone of the REAL served base event — same venue/
                 cohort facts; only the status/ownership fact varies, and the
                 ownership gate is the page's own rule (e.mine || lecturer == me) */
              const e: Record<string, unknown> = { ...(base as Record<string, unknown>), status: v.status, mine };
              e.lecturer = mine ? me : 'Another Lecturer (synthetic)';
              if ((base as Record<string, unknown>).lecturer === me) e.lecturer = mine ? me : 'Another Lecturer (synthetic)';
              const card = TR._card!(e, baseDay, [e]);
              card.classList.add('v-colour-probe');
              grid.appendChild(card);
              const ref = document.createElement('div');
              ref.className = 'event-block ' + v.cls;
              ref.classList.add('v-colour-probe-desk');
              td.appendChild(ref);
              const cardStyle = read(card);
              const deskStyle = read(ref);
              const match = cardStyle.bg === deskStyle.bg && cardStyle.color === deskStyle.color
                && cardStyle.bcol === deskStyle.bcol && cardStyle.bw === deskStyle.bw;
              out.push({ cls: v.cls, synthetic: true, card: cardStyle, desk: deskStyle, match });
              card.remove();
              ref.remove();
            }
            td.remove();
            return { variants: out, theme: themeName, actualDarkFlag, baseStatus: String((base as Record<string, unknown>).status ?? 'normal') } as unknown as Record<string, unknown>;
          }, { themeName: theme, variantList: VARIANTS });

          const err = (report as { error?: string }).error;
          if (err === 'no served non-holiday event this week') test.skip(true, 'fixture: the venue week carries no non-holiday served event');
          expect(err, `${width}: the browser probe ran`).toBeUndefined();
          /* 2026-11-17 (parent review fix): the theme switch itself is proven, not
             just labelled — the evaluated root class must equal the loop's theme */
          expect((report as { actualDarkFlag?: boolean }).actualDarkFlag,
            `${width}: the html.dark root class matches the loop's theme (${theme})`)
            .toBe(theme === 'dark');

          const variants = (report as { variants: Array<{ cls: string; card: { bg: string; color: string; bcol: string; bw: string }; desk: { bg: string; color: string; bcol: string; bw: string }; match: boolean }> }).variants;
          expect(variants.map((v) => v.cls), `${width}: all six ownership variants measured`).toEqual(VARIANTS.map((v) => v.cls));
          for (const v of variants) {
            expect(v.match, `${width} ${theme} ${v.cls}: responsive card == desktop (bg=${v.card.bg} vs ${v.desk.bg}, color=${v.card.color}, border=${v.card.bcol}/${v.card.bw})`).toBe(true);
          }
          /* the fix's point: none of the six variants may wear the generic
             .event-block grey the user screenshotted (parity already proves it,
             but the grey token is named explicitly so a regression reads) */
          for (const v of variants) {
            expect(v.card.bg, `${width} ${theme} ${v.cls}: not the grey fallback`).not.toBe('rgb(107, 107, 107)');
          }
          expectNoPageErrors(page, `colour parity @${width} ${theme}`);
        } finally {
          await ctx.close();
        }
      }
    });
  }
});
