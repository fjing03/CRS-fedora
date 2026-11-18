import { test, expect, type Browser, type BrowserContext, type Page } from '@playwright/test';
import { closeFiltersPanel, openFiltersPanel, withFilters } from './helpers/filters-panel-helper';

/**
 * filters-button — the DOOR and the SECOND WEEK FILTER (SDD `.sdd/changes/filters-button`,
 * T18 + T19). Authority: `design.md` §8 (the behaviour table) and
 * `specs/filters-panel/spec.md` R1–R8.
 *
 * What ships (2026-11-15): each of the three pages renders ONE Filters button in the
 * chips row; the page's own `.toolbar` is MOVED (never rebuilt) into
 * `#filtersPanelBody` inside a `hidden` `#filtersOverlay`, so no filter control is
 * on the page until the door opens. Each page also gained a SECOND week select
 * (`#weekFilterRequested` on My Replacements and Class Replacements,
 * `#weekFilterOriginal` on Request Approval).
 *
 * Identities — one login per identity per run (Fortify is 5/min):
 *   · My Replacements  → `.auth/staff-5770.json` (the file's default, `page`)
 *   · Request Approval → `.auth/staff-5425.json` (the PL) — a stored state, no login
 *   · Class Replacements → the seeded student, logged in ONCE below and reused
 * Testing lane only (`class_replacement_testing` on :8030). Nothing here writes to a
 * database, so no receipt-target guard is needed — every receipt reads the page or
 * the page's own payload.
 *
 * Fixture robustness (2026-11-15): no receipt names a row COUNT any more. Each one
 * derives what its page should show from `window.MockData` + the week-range globals
 * (`getWeekNumber`/`isInWeek`) and re-states that page's own filter predicate — see
 * `expectedFor()` below — then compares the RENDERED `data-id`s. A re-seeded testing DB
 * therefore changes the numbers, never the verdict, and a receipt that the data cannot
 * support skips itself with its reason instead of inventing a fixture.
 *
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/filters-button.spec.ts
 */

const BASE = process.env.CRS_BASE || 'http://127.0.0.1:8030';
const STUDENT = { id: '24DFT0001', password: 'Tarumt@2026' };

/* The file's default identity: My Replacements' lecturer. */
test.use({ storageState: '.auth/staff-5770.json' });

type PageSpec = {
  name: string;
  path: string;
  /** count pill label inside "Showing X of Y <label>" (null = page has no pill) */
  countLabel: 'classes' | 'results' | null;
  /** storageState for the two staff pages */
  state?: string;
  /** the seeded student — logs in once, in `studentStorage()` */
  student?: boolean;
};

const MR: PageSpec = { name: 'My Replacements', path: '/my-replacements', state: '.auth/staff-5770.json', countLabel: 'classes' };
/* The same page under the PL's identity — the ONLY fixture row in the testing DB
   that carries a replacement date belongs to staff 5425 (see receipt 1b). */
const MR_AS_PL: PageSpec = { name: 'My Replacements (PL identity)', path: '/my-replacements', state: '.auth/staff-5425.json', countLabel: 'classes' };
const RA: PageSpec = { name: 'Request Approval', path: '/request-approval-ui', state: '.auth/staff-5425.json', countLabel: 'results' };
const CR: PageSpec = { name: 'Class Replacements', path: '/student-class-replacements', student: true, countLabel: null };

/** The identity that may see the page. Documented, not guessed:
 *  the student's stored state is mirrored from `tests/student-today.spec.ts`
 *  (`#login_id` + `#password` + the loginBtn-enabled wait + the navigation race). */
let studentState: Awaited<ReturnType<BrowserContext['storageState']>> | null = null;
async function studentStorage(browser: Browser) {
  if (studentState) return studentState;

  /* An explicit empty state: Playwright Test's `browser` fixture applies the file's
     `test.use({ storageState })` to every `newContext()` call, and the student route
     would otherwise bounce straight into the staff session. */
  const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/login/student`, { waitUntil: 'domcontentloaded' });
  await page.fill('#login_id', STUDENT.id);
  await page.fill('#password', STUDENT.password);
  await page.waitForFunction(() => {
    const btn = document.getElementById('loginBtn') as HTMLButtonElement | null;
    return !!btn && !btn.disabled;
  }, { timeout: 10_000 });
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30_000 }).catch(() => {}),
    page.click('#loginBtn'),
  ]);
  studentState = await ctx.storageState();
  await ctx.close();
  return studentState;
}

/** Open a page under its own identity and wait until its boot has painted. */
async function openPage(browser: Browser, spec: PageSpec, viewport = { width: 1280, height: 720 }) {
  const state = spec.student ? await studentStorage(browser) : spec.state;
  const ctx = await browser.newContext({ viewport, storageState: state });
  const page = await ctx.newPage();
  await goto(page, spec);
  return { ctx, page };
}

async function goto(page: Page, spec: PageSpec) {
  await page.goto(BASE + spec.path, { waitUntil: 'networkidle' });
  /* `filters-ready` is the module's own boot receipt (design §2) — the button only
     appears once the toolbar has been MOVED into the panel. */
  await expect(page.locator('#filtersButton'), `${spec.name}: the door is rendered`).toBeVisible();

  /* Then wait for the page's own first paint, so no receipt measures a half-built
     list: the pill on the staff pages, the payload on the student page (its list
     can legitimately be EMPTY — a restored requested week hides every row). */
  if (spec.countLabel) {
    await expect(page.locator('#resultCount')).toHaveText(new RegExp(`^Showing \\d+ of \\d+ ${spec.countLabel}$`));
  } else {
    await expect
      .poll(() => page.evaluate(() => ((window as any).MockData?.replacementHistory?.length ?? 0) as number))
      .toBeGreaterThan(0);
  }
}

/** The ids of the rows the page is showing right now (desktop grid). */
async function visibleRowIds(page: Page, bodyId = 'tableBody'): Promise<string[]> {
  return page.$$eval(`#${bodyId} tr[data-id]`, (trs) => trs.map((tr) => (tr as HTMLElement).dataset.id || ''));
}

/* ─────────────── expected sets, derived from the page's own PAYLOAD ───────────────
   Every row count in this file used to name the fixture of the day ("three of the
   five", "two decided requests"), so a re-seeded testing DB turned the receipts red
   without a single product change. Each receipt now computes what its page SHOULD
   show from `window.MockData` plus the week-range globals the pages themselves use
   (`getWeekNumber` / `isInWeek`) and re-states that page's own filter predicate
   (status + both weeks + the COMPLETED exclusion).

   Nothing is derived from the rendered table: comparing the DOM against itself
   would prove nothing. The expectation comes from the payload, the assertion from
   the grid — which is what keeps a receipt an honest proof that the filter narrows. */

type FixtureRow = {
  id: string;
  /** the ORIGINAL week, named the way the page's own select/keys name it ('' = none) */
  origWeek: string;
  /** the REQUESTED week, same convention ('' = nothing arranged) */
  reqWeek: string;
  status: string;
  arranged: boolean;
};

type WeekGroups = { orig: Record<string, string[]>; req: Record<string, string[]> };

type Expectation = {
  /** the ids the grid should show for this filter state */
  ids: string[];
  /** the payload's "of N" for the count pill (My Replacements counts the
   *  exclusion-filtered rows, Request Approval counts every request) */
  total: number;
  rows: FixtureRow[];
  /** the rows passing status + exclusion only, grouped by week — the candidates a
   *  receipt may ask a week filter to narrow */
  baseWeeks: WeekGroups;
  /** the surviving rows, grouped by week */
  weeks: WeekGroups;
  /** rows per grid page (Infinity when the page renders every row) */
  pageSize: number;
  /** an (original week, requested week) pair that NO surviving row satisfies —
   *  the "AND, not OR" witness, derived from the payload */
  impossible: { orig: string; req: string } | null;
};

type ExpectFilters = { status?: string; origWeek?: string; reqWeek?: string };

async function expectedFor(page: Page, filters: ExpectFilters = {}): Promise<Expectation> {
  return page.evaluate((f) => {
    const w = window as any;
    const md = w.MockData || {};
    const kind = w.location.pathname.includes('request-approval')
      ? 'ra'
      : w.location.pathname.includes('class-replacements')
        ? 'cr'
        : 'mr';

    const checked = (id: string) => {
      const el = document.getElementById(id) as HTMLInputElement | null;
      return !!el && el.checked;
    };
    const weekOf = (iso: any) => (iso ? String(w.getWeekNumber(iso)) : '');
    const status = f.status || 'all';
    const origWeek = f.origWeek || 'all';
    const reqWeek = f.reqWeek || 'all';

    type Row = FixtureRow & { origIso: string; reqIso: string };
    let mapped: Row[] = [];
    let total = 0;
    let pageSize = Infinity;

    if (kind === 'mr') {
      /* buildTable(): this page is the lecturer's own-records scope */
      const hide = checked('hideCompleted');
      const all = (md.conflictedClasses || []).filter((c: any) => c.lecturer === md.currentUser.name);
      const rpp = document.getElementById('rppSelect') as HTMLSelectElement | null;
      pageSize = !rpp || !rpp.value || rpp.value === 'all' ? Infinity : Number(rpp.value);
      mapped = all
        .map((c: any) => ({
          id: String(c.id),
          origWeek: weekOf(c.date),
          reqWeek: weekOf(c.replacementDate),
          status: c.requestStatus || 'none',
          arranged: !!c.replacementDate,
          origIso: c.date || '',
          reqIso: c.replacementDate || '',
        }))
        .filter((r: Row) => !(hide && r.status === 'completed'));
      total = mapped.length;                   /* visibleBaseRows() — the pill's "of N" */
    } else if (kind === 'ra') {
      /* filterData(): every request in the payload, no own-records scope */
      const hide = checked('hideCompletedToggle');
      const all = md.approvalRequests || [];
      total = all.length;
      const rpp = document.getElementById('rowsPerPage') as HTMLSelectElement | null;
      pageSize = rpp && Number(rpp.value) ? Number(rpp.value) : 10;
      mapped = all
        .map((r: any) => ({
          id: String(r.id),
          origWeek: weekOf(r.classDate),
          reqWeek: weekOf(r.replacementDate),
          status: r.status,
          arranged: !!r.replacementDate,
          origIso: r.classDate || '',
          reqIso: r.replacementDate || '',
        }))
        .filter((r: Row) => !(hide && r.status === 'Completed'));
    } else {
      /* renderUpcoming(): a row is a class OCCURRENCE (0-based `r.week`, 1-based
         `r.req.week`, 1-based select values) and this page has no status filter */
      const hide = checked('hideCompleted');
      const all = md.replacementHistory || [];
      mapped = all
        .map((r: any) => ({
          id: String(r.id),
          origWeek: String(r.week + 1),
          reqWeek: r.req ? String(r.req.week) : '',
          status: r.status,
          arranged: !!r.req,
          origIso: '',
          reqIso: '',
        }))
        .filter((r: Row) => !(hide && r.status === 'completed'));
      total = mapped.length;
    }

    const statusPass = (r: Row) => {
      if (kind === 'cr') return true;          /* R4: no status filter on this page */
      if (status === 'all') return true;
      if (kind === 'mr') return status === 'none' ? r.status === 'none' : r.status === status;
      return r.status === status;
    };
    /* Request Approval's weeks are RANGE tests (`isInWeek`), the other two compare
       the named week value — exactly as their own builders do */
    const weekPass = (r: Row) => {
      if (kind === 'ra') {
        const origOk = origWeek === 'all' || w.isInWeek(r.origIso, origWeek);
        const reqOk = reqWeek === 'all' || w.isInWeek(r.reqIso, reqWeek);
        return origOk && reqOk;
      }
      const origOk = origWeek === 'all' || r.origWeek === origWeek;
      const reqOk = reqWeek === 'all' || (r.arranged && r.reqWeek === reqWeek);
      return origOk && reqOk;
    };

    const baseRows = mapped.filter(statusPass);
    const rows = baseRows.filter(weekPass);

    const group = (list: Row[]) => {
      const orig: Record<string, string[]> = {};
      const req: Record<string, string[]> = {};
      list.forEach((r) => {
        if (r.origWeek) (orig[r.origWeek] = orig[r.origWeek] || []).push(r.id);
        if (r.reqWeek) (req[r.reqWeek] = req[r.reqWeek] || []).push(r.id);
      });
      return { orig, req };
    };

    /* two weeks inside the payload that no single row carries together */
    const origVals = Array.from(new Set(baseRows.map((r) => r.origWeek).filter((v) => !!v)));
    const reqVals = Array.from(new Set(baseRows.map((r) => r.reqWeek).filter((v) => !!v)));
    let impossible: { orig: string; req: string } | null = null;
    for (const o of origVals) {
      for (const q of reqVals) {
        if (!baseRows.some((r) => r.origWeek === o && r.reqWeek === q)) {
          impossible = { orig: o, req: q };
          break;
        }
      }
      if (impossible) break;
    }

    return {
      ids: rows.map((r) => r.id),
      total,
      rows: rows.map((r) => ({
        id: r.id, origWeek: r.origWeek, reqWeek: r.reqWeek, status: r.status, arranged: r.arranged,
      })),
      baseWeeks: group(baseRows),
      weeks: group(rows),
      pageSize,
      impossible,
    };
  }, filters);
}

/** The week values one of these pages' week <select>s actually offers (no `All Weeks`).
 *  Accepts the id with or without a leading `#`. */
async function weekOptionValues(page: Page, selectId: string): Promise<string[]> {
  const selector = selectId.startsWith('#') ? selectId : `#${selectId}`;
  return page.$$eval(`${selector} option`, (os) =>
    os.map((o) => (o as HTMLOptionElement).value).filter((v) => !!v && v !== 'all'),
  );
}

/** Week groups that are non-empty STRICT subsets of `size` rows, biggest first and
 *  restricted to the values the select offers (that is what makes them settable). */
function narrowingWeeks(groups: Record<string, string[]>, size: number, options: string[]) {
  return weekGroups(groups, options).filter(([, ids]) => ids.length < size);
}

/** Non-empty week groups, biggest first, restricted to the select's own values. */
function weekGroups(groups: Record<string, string[]>, options: string[]) {
  return Object.entries(groups)
    .filter(([week, ids]) => options.includes(week) && ids.length > 0)
    .sort((a, b) => b[1].length - a[1].length);
}

/* A filter change repaints through the house rebuild wrapper, which empties the
   grid and re-fills it on the next frame — so a read is always POLLED, never taken
   once (the house files do the same with their short post-change waits). The grid
   has its own sort order, so the comparison is a SET.

   A filter that leaves more rows than one page holds can only paint page 1: then the
   receipt asks for the page size and a subset, instead of inventing an order. */
async function expectRowSet(
  page: Page,
  expected: string[],
  opts: { bodyId?: string; message?: string; pageSize?: number } = {},
) {
  const bodyId = opts.bodyId || 'tableBody';
  const pageSize = opts.pageSize ?? Infinity;

  if (expected.length > pageSize) {
    await expect.poll(async () => (await visibleRowIds(page, bodyId)).length, { message: opts.message }).toBe(pageSize);
    const shown = await visibleRowIds(page, bodyId);
    expect(
      shown.every((id) => expected.includes(id)),
      `${opts.message || 'the grid'} — the first page is a subset of the expected rows`,
    ).toBe(true);
    return;
  }

  await expect
    .poll(async () => (await visibleRowIds(page, bodyId)).slice().sort().join(','), { message: opts.message })
    .toBe(expected.slice().sort().join(','));
}

/** The chip bar's own pills, in order (Clear all excluded — it is not a filter). */
async function chipTexts(page: Page): Promise<string[]> {
  return page.$$eval('#filterChips .filter-chip:not(.filter-chip-clear-all)', (els) =>
    els.map((el) => (el.textContent || '').replace(/×/g, '').trim()),
  );
}

/** Poll the chip bar until it reads `pattern` (the repaint is another frame). */
async function expectChips(page: Page, pattern: RegExp, message?: string) {
  await expect.poll(async () => (await chipTexts(page)).join('|'), { message }).toMatch(pattern);
}

/** Poll the chip bar until it does NOT read `fragment`. */
async function expectNoChip(page: Page, fragment: string, message?: string) {
  await expect.poll(async () => (await chipTexts(page)).join('|'), { message }).not.toContain(fragment);
}

const TOL = 2;   /* px tolerance on the measured geometry */

// ────────────────────────── T18 — the door and the frame ──────────────────────────
// Proposal (a)–(g) + design §8 rows 1–6, 12, 13, 14, 16.

test.describe('T18 — the door and the frame', () => {
  test('(a) nothing filter-shaped is on the page until the door opens', async ({ browser }) => {
    for (const spec of [MR, RA, CR]) {
      const { ctx, page } = await openPage(browser, spec);
      try {
        /* the door: one labelled button, first in the row, closed and inert */
        const button = page.locator('#filtersButton');
        await expect(button, `${spec.name}: exactly one Filters button`).toHaveCount(1);
        await expect(button).toBeVisible();
        await expect(button).toHaveAccessibleName('Filters');
        await expect(button, 'R1: the button announces the panel it opens').toHaveAttribute('aria-haspopup', 'dialog');
        await expect(button).toHaveAttribute('aria-controls', 'filtersPanel');
        await expect(button).toHaveAttribute('aria-expanded', 'false');
        expect(
          await page.evaluate(() => document.getElementById('filtersRow')!.firstElementChild!.id),
          `${spec.name}: the button is the row's first control`,
        ).toBe('filtersButton');

        /* R1: NO filter control is visible before the door opens — every one of them
           lives inside the `hidden` overlay, so it cannot be seen, tabbed to or read. */
        const panelBody = page.locator('#filtersPanelBody');
        await expect(panelBody.locator('.toolbar'), `${spec.name}: the toolbar is inside the panel`).toHaveCount(1);
        await expect(panelBody.locator('.toolbar'), `${spec.name}: …and the closed panel hides it`).toBeHidden();
        for (const id of ['#searchInput', '#statusFilter', '#weekFilter', '#weekFilterRequested', '#weekFilterOriginal']) {
          const count = await page.locator(id).count();
          if (count === 0) continue;                       /* that page has no such field — skip it */
          await expect(page.locator(id), `${spec.name}: ${id} is not on the page until the door opens`).toBeHidden();
        }
        await expect(panelBody.locator('.toggle-wrapper'), `${spec.name}: the switch is inside the panel`).toHaveCount(1);
        await expect(panelBody.locator('.toggle-wrapper')).toBeHidden();
        await expect(page.locator('#filtersOverlay'), `${spec.name}: the overlay starts hidden`).toBeHidden();

        /* the chips and the count stayed on the page — the row is the bar now */
        await expect(page.locator('#filterChips'), `${spec.name}: the chip bar is in the row`).toBeVisible();
      } finally {
        await ctx.close();
      }
    }
  });

  test('(b) 768 is the bottom sheet, 769 is the card under the button', async ({ page }) => {
    /* My Replacements, light/dark irrelevant — this is geometry only. */
    await page.setViewportSize({ width: 768, height: 900 });
    await goto(page, MR);
    await page.locator('#filtersButton').click();
    const sheet = (await page.locator('#filtersPanel').boundingBox())!;

    /* design §3 / R2: full width, docked at the bottom of the viewport */
    expect(Math.abs(sheet.x), 'the sheet starts at the left edge').toBeLessThanOrEqual(TOL);
    expect(Math.abs(sheet.width - 768), 'the sheet spans the viewport').toBeLessThanOrEqual(TOL);
    expect(Math.abs(sheet.y + sheet.height - 900), 'the sheet is docked to the bottom').toBeLessThanOrEqual(TOL);
    expect(
      await page.evaluate(() => getComputedStyle(document.getElementById('filtersOverlay')!).backgroundColor),
      'the phone veil is .modal-overlay\'s 0.45 literal (R2)',
    ).toBe('rgba(0, 0, 0, 0.45)');

    await page.keyboard.press('Escape');

    /* …and one pixel above the breakpoint it is a card anchored under the button */
    await page.setViewportSize({ width: 769, height: 900 });
    const button = (await page.locator('#filtersButton').boundingBox())!;
    await page.locator('#filtersButton').click();
    const card = (await page.locator('#filtersPanel').boundingBox())!;

    expect(Math.abs(card.y - (button.y + button.height + 8)), 'top = button bottom + 8 (R2)').toBeLessThanOrEqual(TOL);
    expect(card.width, 'the card is capped at 460px').toBeLessThanOrEqual(460);
    expect(card.x, 'left is clamped to the viewport gutter').toBeGreaterThanOrEqual(8 - TOL);
    expect(card.x + card.width, 'right is clamped to the viewport gutter').toBeLessThanOrEqual(769 - 8 + TOL);
    /* 2026-11-18 (filters-panel-and-popup-footer): the anchor is no longer the button's
       left edge — the card starts past the grid's first LABELLED column, so an open
       panel stops covering the column it filters. Top, width and both viewport clamps
       are unchanged; a leading checkbox column does not count. */
    const anchor = await page.evaluate(() => {
      const th = [...document.querySelectorAll('table thead th')].find(
        (el) => (el as HTMLElement).offsetParent !== null && (el.textContent || '').trim() !== '',
      );
      return {
        thRight: th ? th.getBoundingClientRect().right : null,
        btnLeft: document.getElementById('filtersButton')!.getBoundingClientRect().x,
      };
    });
    const jsAnchor = anchor.thRight === null ? anchor.btnLeft : anchor.thRight + 12;
    expect(
      Math.abs(card.x - Math.min(Math.max(jsAnchor, 8), 769 - card.width - 8)),
      'left = clamp(past the first labelled column, …)',
    ).toBeLessThanOrEqual(TOL);
    expect(
      await page.evaluate(() => getComputedStyle(document.getElementById('filtersOverlay')!).backgroundColor),
      'the desktop veil is the prototype\'s 0.32 literal (R2)',
    ).toBe('rgba(0, 0, 0, 0.32)');
  });

  test('(c) aria-expanded, focus in, the Tab trap, Escape back to the button and unlock', async ({ page }) => {
    await goto(page, MR);

    await page.locator('#filtersButton').click();
    await expect(page.locator('#filtersOverlay')).toBeVisible();
    await expect(page.locator('#filtersButton')).toHaveAttribute('aria-expanded', 'true');
    expect(
      await page.evaluate(() => document.body.classList.contains('filters-panel-open')),
      'R6: the panel owns its own scroll lock',
    ).toBe(true);
    expect(
      await page.evaluate(() => getComputedStyle(document.getElementById('filtersButton')!).zIndex),
      'design §3: the pressed button stays above the veil',
    ).toBe('1300');

    /* focus lands on the FIRST body control — never on a week arrow (R6) */
    const focus = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      return {
        inPanel: !!el && document.getElementById('filtersPanel')!.contains(el),
        id: el ? el.id : '',
        isArrow: !!el && el.classList.contains('week-arrow'),
      };
    });
    expect(focus.inPanel, 'R6: focus moves into the panel').toBe(true);
    expect(focus.isArrow, 'R6: …and not onto a ‹ › landmark').toBe(false);
    expect(focus.id, 'R6: the first body control is the search box on this page').toBe('searchInput');

    /* R6: Tab and Shift+Tab cycle inside the panel only */
    for (let i = 0; i < 20; i++) {
      await page.keyboard.press('Shift+Tab');
      expect(
        await page.evaluate(() => document.getElementById('filtersPanel')!.contains(document.activeElement)),
        `R6: Shift+Tab #${i + 1} stays inside the panel`,
      ).toBe(true);
    }
    for (let i = 0; i < 20; i++) {
      await page.keyboard.press('Tab');
      expect(
        await page.evaluate(() => document.getElementById('filtersPanel')!.contains(document.activeElement)),
        `R6: Tab #${i + 1} stays inside the panel`,
      ).toBe(true);
    }

    /* a filter set inside the panel survives the close: the panel's own Escape is
       capture-phase + stops there, so the page's bubble handler (which calls
       clearAllFilters() on My Replacements) never runs. The week is the last one the
       select offers — any real value, never a fixture's count. */
    const weekOptions = await weekOptionValues(page, '#weekFilter');
    const weekValue = weekOptions[weekOptions.length - 1];
    await page.selectOption('#weekFilter', weekValue);
    await page.keyboard.press('Escape');

    await expect(page.locator('#filtersOverlay'), 'R6: Escape closes the panel').toBeHidden();
    await expect(page.locator('#filtersButton')).toHaveAttribute('aria-expanded', 'false');
    expect(
      await page.evaluate(() => document.body.classList.contains('filters-panel-open')),
      'R6: the scroll lock is released',
    ).toBe(false);
    expect(
      await page.evaluate(() => document.activeElement!.id),
      'R6: focus returns to the button that opened the panel',
    ).toBe('filtersButton');
    await expect(page.locator('#weekFilter'), 'Escape closed the panel, not the filters').toHaveValue(weekValue);
    await expect.poll(async () => (await chipTexts(page)).join('|')).toContain('Original Slot Week');
  });

  test('(e) the close control and a veil tap both close and unlock', async ({ page }) => {
    await goto(page, MR);
    const vp = page.viewportSize()!;

    await page.locator('#filtersButton').click();
    await expect(page.locator('#filtersOverlay')).toBeVisible();
    await page.locator('#filtersPanel .filters-panel-close').click();
    await expect(page.locator('#filtersOverlay'), 'R6: the × closes the panel').toBeHidden();
    await expect(page.locator('#filtersButton')).toHaveAttribute('aria-expanded', 'false');
    expect(await page.evaluate(() => document.body.classList.contains('filters-panel-open'))).toBe(false);
    expect(await page.evaluate(() => document.activeElement!.id)).toBe('filtersButton');

    /* a click on the veil itself (bottom-right corner, far from the card) */
    await page.locator('#filtersButton').click();
    await expect(page.locator('#filtersOverlay')).toBeVisible();
    await page.mouse.click(vp.width - 12, vp.height - 12);
    await expect(page.locator('#filtersOverlay'), 'R6: a veil tap closes the panel').toBeHidden();
    await expect(page.locator('#filtersButton')).toHaveAttribute('aria-expanded', 'false');
    expect(await page.evaluate(() => document.body.classList.contains('filters-panel-open'))).toBe(false);
  });

  test('(d) chips and the count repaint with the panel closed', async ({ browser }) => {
    for (const spec of [MR, RA]) {
      const { ctx, page } = await openPage(browser, spec);
      try {
        const pill = page.locator('#resultCount');
        const before = (await pill.textContent()) || '';
        const chipsBefore = (await chipTexts(page)).join('|');
        const statusOptions = await page.$$eval('#statusFilter option', (os) =>
          os.map((o) => ({ value: (o as HTMLOptionElement).value, label: (o.textContent || '').trim() })),
        );

        if (spec === MR) {
          /* My Replacements: pick a status that really NARROWS this payload, so the
             receipt still proves the pill followed the filter (a status matching every
             row would leave the numbers untouched and prove nothing). */
          const base = await expectedFor(page);                 /* status all, the page's own exclusion */
          let chosen: { value: string; label: string } | null = null;
          let expectedIds: string[] = [];
          for (const option of statusOptions.filter((o) => o.value !== 'all')) {
            const set = await expectedFor(page, { status: option.value });
            if (set.ids.length < base.total) {
              chosen = option;
              expectedIds = set.ids;
              break;
            }
          }
          test.skip(!chosen, 'fixture: no Status narrows this lecturer\'s rows — nothing for the pill to follow');

          await withFilters(page, async () => {
            await page.selectOption('#statusFilter', chosen!.value);
          });

          await expect(page.locator('#filtersOverlay'), `${spec.name}: the panel stays closed`).toBeHidden();
          /* the chip bar is repainted by the page's own builder while the panel is shut */
          await expect
            .poll(async () => (await chipTexts(page)).some((t) => t === `Status: ${chosen!.label}`), {
              message: `${spec.name}: the new status has its chip`,
            })
            .toBe(true);
          await expect(pill, 'the pill followed the filter').toHaveText(
            `Showing ${expectedIds.length} of ${base.total} classes`,
          );
          expect((await pill.textContent()) || '', `${spec.name}: the numbers changed`).not.toBe(before);
        } else {
          /* Request Approval opens on its own default status (Pending in the markup), so
             choosing All Status DROPS the chip. Both pill texts are derived from the
             payload: the boot one and the one after the change. */
          const defaultStatus = await page.inputValue('#statusFilter');
          const beforeSet = await expectedFor(page, { status: defaultStatus });
          const afterSet = await expectedFor(page, { status: 'all' });
          expect(before, `${spec.name}: the pill the page painted at boot`).toBe(
            `Showing ${beforeSet.ids.length} of ${beforeSet.total} results`,
          );

          await withFilters(page, async () => {
            await page.selectOption('#statusFilter', 'all');
          });

          await expect(page.locator('#filtersOverlay'), `${spec.name}: the panel stays closed`).toBeHidden();
          await expectNoChip(page, 'Status:', `${spec.name}: All Status is not a filter, so its chip is gone`);
          await expect(pill, 'the pill followed the filter').toHaveText(
            `Showing ${afterSet.ids.length} of ${afterSet.total} results`,
          );
          /* only assert a changed number when the payload makes the two counts differ */
          if (afterSet.ids.length !== beforeSet.ids.length) {
            expect((await pill.textContent()) || '', `${spec.name}: the numbers changed`).not.toBe(before);
          }
        }

        expect((await chipTexts(page)).join('|'), `${spec.name}: the bar was repainted without reopening`).not.toBe(chipsBefore);
      } finally {
        await ctx.close();
      }
    }
  });

  test('(f) the count pill is a pill at 390 and 1440 — and absent on the student page', async ({ browser }) => {
    for (const spec of [MR, RA]) {
      for (const width of [390, 1440]) {
        const { ctx, page } = await openPage(browser, spec, { width, height: 900 });
        try {
          /* exactly one node (design §10: a page that kept its old markup would ship two) */
          await expect(page.locator('#resultCount'), `${spec.name} @${width}: one pill`).toHaveCount(1);
          await expect(page.locator('#resultCount'), `${spec.name} @${width}: the pill is visible`).toBeVisible();
          await expect(page.locator('#resultCount')).toHaveText(
            new RegExp(`^Showing \\d+ of \\d+ ${spec.countLabel}$`),
          );
          /* and it is the row's right-hand pill, not a bar under it */
          const pill = (await page.locator('#resultCount').boundingBox())!;
          const row = (await page.locator('#filtersRow').boundingBox())!;
          expect(pill.y + pill.height / 2, `${spec.name} @${width}: the pill sits in the row`).toBeLessThanOrEqual(row.y + row.height);
        } finally {
          await ctx.close();
        }
      }
    }

    const { ctx, page } = await openPage(browser, CR, { width: 1440, height: 900 });
    try {
      await expect(page.locator('#resultCount'), 'R5: the student page has no count').toHaveCount(0);
      await expect(page.locator('#filterChips'), 'R5: …but it does have the chip row').toBeVisible();
    } finally {
      await ctx.close();
    }
  });

  test('(g) #filterChips is the same node across an open/close cycle', async ({ page }) => {
    await goto(page, MR);

    /* mark the node the page's own repaint writes into */
    await page.evaluate(() => { (document.getElementById('filterChips') as HTMLElement).dataset.probe = '1'; });

    await openFiltersPanel(page);
    await closeFiltersPanel(page);

    await expect(page.locator('#filterChips'), 'R5: one chip bar, never a re-created one').toHaveCount(1);
    expect(
      await page.evaluate(() => (document.getElementById('filterChips') as HTMLElement).dataset.probe || ''),
      'R5: the SAME DOM node survived the move and the close',
    ).toBe('1');
  });

  test('(12) one overlay at a time — the bell and the summary sheet', async ({ page }) => {
    /* The bell's overlay covers the page, so the second click in each pair is the
       module entry point itself: the rule being verified is the pair of closing
       lines in design §6.7 (FiltersPanel.open() closes the others, and each of
       them closes the panel), not the reachability of a covered button. */
    await goto(page, MR);

    await page.locator('.notif-btn').first().click();
    await expect(page.locator('#notifPanel')).toHaveClass(/open/);
    await page.evaluate(() => (window as any).FiltersPanel.open());
    await expect(page.locator('#filtersOverlay')).toBeVisible();
    await expect(page.locator('#notifPanel'), 'R2: opening the panel closes the bell').not.toHaveClass(/open/);
    await closeFiltersPanel(page);

    /* the reverse: the bell closes the panel without stealing its focus */
    await page.evaluate(() => (window as any).openNotifPanel());
    await expect(page.locator('#notifPanel')).toHaveClass(/open/);
    await page.evaluate(() => (window as any).closeNotifPanel());

    /* the same pair for the summary sheet, which is a phone control */
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('#summaryButton')).toBeVisible();
    await page.locator('#summaryButton').click();
    await expect(page.locator('#summarySheet')).toBeVisible();

    await page.evaluate(() => (window as any).FiltersPanel.open());
    await expect(page.locator('#filtersOverlay')).toBeVisible();
    await expect(page.locator('#summarySheet'), 'R2: opening the panel closes the summary sheet').toBeHidden();
    expect(await page.evaluate(() => document.body.classList.contains('summary-sheet-open'))).toBe(false);

    await page.evaluate(() => (window as any).SummarySheet.open());
    await expect(page.locator('#summarySheet')).toBeVisible();
    await expect(page.locator('#filtersOverlay'), 'R2: opening the summary sheet closes the panel').toBeHidden();
    expect(await page.evaluate(() => document.body.classList.contains('filters-panel-open'))).toBe(false);
  });

  test('(14) a resize across 768 keeps the panel open and re-shapes it', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await goto(page, MR);

    await page.locator('#filtersButton').click();
    const sheet = (await page.locator('#filtersPanel').boundingBox())!;
    expect(Math.abs(sheet.width - 390), 'at 390 the panel is the full-width sheet').toBeLessThanOrEqual(TOL);

    await page.setViewportSize({ width: 1024, height: 900 });
    await expect(page.locator('#filtersOverlay'), 'design §3: a rotated phone must not lose the panel').toBeVisible();

    await expect.poll(async () => {
      const box = await page.locator('#filtersPanel').boundingBox();
      return box ? Math.round(box.width) : 0;
    }, { message: 'the card metrics apply after the crossing' }).toBeLessThanOrEqual(460);

    const card = (await page.locator('#filtersPanel').boundingBox())!;
    const button = (await page.locator('#filtersButton').boundingBox())!;
    expect(Math.abs(card.y - (button.y + button.height + 8)), 're-placed under the button').toBeLessThanOrEqual(TOL);
    expect(card.x).toBeGreaterThanOrEqual(8 - TOL);
    expect(card.x + card.width).toBeLessThanOrEqual(1024 - 8 + TOL);
    expect(
      await page.evaluate(() => document.getElementById('filtersPanel')!.contains(document.activeElement)),
      'R6: the trap survives the breakpoint change',
    ).toBe(true);
  });

  test('(13) print hides the door and the veil and puts the toolbar back inline', async ({ page }) => {
    await goto(page, MR);
    await page.locator('#filtersButton').click();
    await expect(page.locator('#filtersOverlay')).toBeVisible();

    await page.emulateMedia({ media: 'print' });
    /* Chromium's emulateMedia only swaps the media type; a real print fires these
       two events, and they are the module's relocation hooks (design §6.8). */
    await page.evaluate(() => window.dispatchEvent(new Event('beforeprint')));

    await expect(page.locator('#filtersButton'), 'R7: the door never prints').toBeHidden();
    await expect(page.locator('#filtersOverlay'), 'R7: the panel/veil never print').toBeHidden();
    await expect(page.locator('#searchInput'), 'R7: the fields DO print — relocated back inline').toBeVisible();
    expect(
      await page.evaluate(() => document.getElementById('searchInput')!.closest('#filterToolbarHost') !== null),
      'R7: the toolbar returned to its own seat, not into the (hidden) panel',
    ).toBe(true);
    expect(
      await page.evaluate(() => getComputedStyle(document.body).overflow),
      'R7: the print page is not left with a hidden overflow',
    ).not.toBe('hidden');
    await expect(page.locator('#filterChips'), 'R7: the chips still print').toBeVisible();

    await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
    await page.emulateMedia({ media: 'screen' });
    expect(
      await page.evaluate(() => document.getElementById('searchInput')!.closest('#filtersPanelBody') !== null),
      'R7: the toolbar returns into #filtersPanelBody',
    ).toBe(true);
    await expect(page.locator('#filtersOverlay'), 'the panel itself is still open on screen').toBeVisible();
    await expect(page.locator('#searchInput'), 'R7: …and its fields are usable again')
      .toBeVisible();
  });

  test('(16) Escape with a row modal open closes only the modal', async ({ browser }) => {
    const { ctx, page } = await openPage(browser, RA);
    try {
      /* the PL page opens on Pending; this fixture's requests are decided, so show all */
      await withFilters(page, async () => {
        await page.selectOption('#statusFilter', 'all');
      });
      await expect(page.locator('#tableBody tr').first()).toBeVisible();

      /* a real row click opens the detail modal; then Escape means ONE layer */
      await page.locator('#tableBody tr').first().click();
      await expect(page.locator('#modalOverlay')).toBeVisible();
      await expect(page.locator('#filtersOverlay'), 'the panel was closed all along').toBeHidden();

      await page.keyboard.press('Escape');
      await expect(page.locator('#modalOverlay'), 'R6: the row modal closes').toBeHidden();
      await expect(page.locator('#filtersOverlay'), 'R6: …and the closed panel stays closed').toBeHidden();
      expect(await page.evaluate(() => document.body.classList.contains('filters-panel-open'))).toBe(false);

      /* …and the guard itself: a dialog ABOVE an open panel owns the Escape.
         (The row is behind the veil, so the modal is forced through the page's own
         opener — no write, just the same render path a click uses.) */
      const rowId = Number(await page.locator('#tableBody tr').first().getAttribute('data-id'));
      await page.locator('#filtersButton').click();
      await expect(page.locator('#filtersOverlay')).toBeVisible();
      await page.evaluate((id) => (window as any).openModalById(id), rowId);
      await expect(page.locator('#modalOverlay')).toBeVisible();

      await page.keyboard.press('Escape');
      await expect(page.locator('#modalOverlay'), 'R6: the dialog above closes').toBeHidden();
      await expect(page.locator('#filtersOverlay'), 'R6: the panel underneath survives that Escape').toBeVisible();

      await page.keyboard.press('Escape');
      await expect(page.locator('#filtersOverlay'), 'R6: the next Escape closes the panel').toBeHidden();
    } finally {
      await ctx.close();
    }
  });

  test('morph canary (design §8.15) — a Livewire round-trip leaves one toolbar with both weeks intact', async ({ browser }) => {
    /* A read-only `$refresh()` round-trip is used instead of a decision write: the
       decision would mutate a pending request in the testing DB, and the thing this
       canary guards is the MORPH (the server's HTML replacing the client's moved
       toolbar), which `$refresh` reproduces in full. Same component, same render. */
    const { ctx, page } = await openPage(browser, RA);
    try {
      /* two distinct weeks the selects really offer — the canary is about the
         round-trip keeping values, not about which weeks the fixture uses */
      const origWeeks = await weekOptionValues(page, '#weekFilterOriginal');
      const reqWeeks = await weekOptionValues(page, '#weekFilter');
      const origWeek = origWeeks[origWeeks.length - 1];
      const reqWeek = reqWeeks[reqWeeks.length - 1];
      await withFilters(page, async () => {
        await page.selectOption('#weekFilterOriginal', origWeek);
        await page.selectOption('#weekFilter', reqWeek);
        await expect(page.locator('#weekFilter')).toHaveValue(reqWeek);
        await expect(page.locator('#weekFilterOriginal')).toHaveValue(origWeek);
      });

      const round = await page.evaluate(async () => {
        const wire = (window as any).pageLivewire();
        if (!wire) return 'no-component';
        try {
          await wire.$refresh();
          return 'ok';
        } catch (e) {
          return 'error: ' + (e as Error).message;
        }
      });
      expect(round, 'the page component answered the round-trip').toBe('ok');

      await openFiltersPanel(page);
      await expect(page.locator('#filtersPanelBody .toolbar'), 'R3: still exactly ONE toolbar, still inside the panel').toHaveCount(1);
      await expect(page.locator('.toolbar'), 'R3: …and only one on the whole page').toHaveCount(1);
      await expect(page.locator('#weekFilter'), 'R4: the requested week still holds its value').toHaveValue(reqWeek);
      await expect(page.locator('#weekFilterOriginal'), 'R4: the original week still holds its value').toHaveValue(origWeek);
      expect(await page.locator('#weekFilter option').count(), 'R4: the requested select is still populated').toBeGreaterThan(1);
      expect(await page.locator('#weekFilterOriginal option').count(), 'R4: the original select is still populated').toBeGreaterThan(1);
    } finally {
      await ctx.close();
    }
  });
});

// ────────────────────────── T19 — the second week filter ──────────────────────────
// Proposal (h) / R4, per page.

test.describe('T19 — the second week filter', () => {
  test('1. My Replacements — the requested week, its chip, and a ✕ that clears only itself', async ({ page }) => {
    /* Every number below is derived from `MockData.conflictedClasses` (see
       `expectedFor`): which original week narrows, what a requested week leaves, and
       which ids come back when the chip's ✕ clears it. On the current fixture all of
       this lecturer's rows are unarranged, so a specific requested week can only EMPTY
       the list — the positive case is receipt 1b, which skips itself when no identity
       has an arranged row. */
    await goto(page, MR);

    const base = await expectedFor(page);
    await expectRowSet(page, base.ids, { message: 'the grid shows the payload\'s own rows', pageSize: base.pageSize });

    /* 1a — the original week narrows first, so the ✕ below has something to leave alone */
    const origOptions = await weekOptionValues(page, '#weekFilter');
    test.skip(origOptions.length === 0, 'fixture: this page offers no original week to filter by');
    const origCandidates = narrowingWeeks(base.baseWeeks.orig, base.ids.length, origOptions);
    test.skip(origCandidates.length === 0, 'fixture: no original week holds a strict subset of this lecturer\'s rows');
    const [origWeek, origIds] = origCandidates[0];

    await withFilters(page, async () => {
      await page.selectOption('#weekFilter', origWeek);
    });
    await expectRowSet(page, origIds, {
      message: `week ${origWeek}: exactly the payload's rows that fall in it`,
      pageSize: base.pageSize,
    });
    expect(origIds.length, 'the original week really narrowed the list').toBeLessThan(base.ids.length);

    /* 1b — the requested week hides every row with no arrangement (design §4.3). The
       week that keeps the most arranged rows is chosen from the payload; when the
       fixture has no arrangement at all, any real week is the same receipt. */
    const scoped = await expectedFor(page, { origWeek });
    const reqOptions = await weekOptionValues(page, '#weekFilterRequested');
    test.skip(reqOptions.length === 0, 'fixture: this page offers no requested week to filter by');
    const reqGroups = weekGroups(scoped.weeks.req, reqOptions);
    const reqWeek = reqGroups.length ? reqGroups[0][0] : reqOptions[0];
    const narrowed = await expectedFor(page, { origWeek, reqWeek });

    await withFilters(page, async () => {
      await page.selectOption('#weekFilterRequested', reqWeek);
    });
    await expectRowSet(page, narrowed.ids, {
      message: 'a specific requested week keeps only the arranged rows',
      pageSize: base.pageSize,
    });
    expect(narrowed.ids.length, 'every surviving row is an ARRANGED row').toBe(
      narrowed.rows.filter((r) => r.arranged).length,
    );
    if (scoped.ids.length > scoped.rows.filter((r) => r.arranged).length) {
      expect(narrowed.ids.length, 'a specific requested week hides the Need to Arrange rows').toBeLessThan(
        scoped.ids.length,
      );
    }
    await expect(page.locator('#resultCount')).toHaveText(`Showing ${narrowed.ids.length} of ${narrowed.total} classes`);
    await expectChips(page, /Requested Slot Week:/, 'R4: the requested week has its own chip');
    await expectChips(page, /Original Slot Week:.*Requested Slot Week:/, 'R4: …beside the original one, in field order');

    /* 1c — that chip's ✕ clears only itself */
    await page.locator('#filterChips .filter-chip', { hasText: 'Requested Slot Week:' }).locator('.filter-chip-remove').click();
    await expect(page.locator('#weekFilterRequested')).toHaveValue('all');
    await expect(page.locator('#weekFilter'), 'the original week is untouched').toHaveValue(origWeek);
    await expectRowSet(page, origIds, { message: 'the original-week rows are back', pageSize: base.pageSize });
    await expectChips(page, /Original Slot Week:/, 'the original chip stays');
    await expectNoChip(page, 'Requested Slot Week:', 'only the requested chip left');
  });

  test('1b. My Replacements — the requested week keeps the arranged row and drops the unarranged ones', async ({ browser }) => {
    /* R4's positive case ("only rows whose requested slot is in that week") needs at
       least one ARRANGED row. That requirement is derived from the payload instead of
       naming one, so the receipt skips itself with a reason on a fixture that has none
       — which is the case today: every conflicted class of every stored identity in
       the testing DB reports no `replacementDate` (five of them on the PL below). */
    const { ctx, page } = await openPage(browser, MR_AS_PL);
    try {
      const base = await expectedFor(page);
      await expectRowSet(page, base.ids, {
        message: 'the grid shows this identity\'s own rows',
        pageSize: base.pageSize,
      });

      const arrangedIds = base.rows.filter((r) => r.arranged).map((r) => r.id);
      test.skip(
        arrangedIds.length === 0,
        'fixture: no conflicted class in the testing DB carries a replacementDate — no arranged row to keep',
      );

      const reqOptions = await weekOptionValues(page, '#weekFilterRequested');
      const reqGroups = weekGroups(base.weeks.req, reqOptions);
      test.skip(reqGroups.length === 0, 'fixture: the arranged rows fall outside every selectable requested week');
      const [reqWeek, reqIds] = reqGroups[0];

      await withFilters(page, async () => {
        await page.selectOption('#weekFilterRequested', reqWeek);
      });

      const after = await expectedFor(page, { reqWeek });
      await expectRowSet(page, after.ids, {
        message: 'R4: only the rows arranged in that requested week survive',
        pageSize: base.pageSize,
      });
      expect(after.ids.length, 'the surviving rows are that week\'s arranged ones').toBe(reqIds.length);
      expect(after.rows.every((r) => r.arranged), 'the surviving rows ARE the arranged ones').toBe(true);
      expect(after.ids.length, 'every Need to Arrange row disappeared').toBeLessThan(base.ids.length);
      await expect(page.locator('#weekFilterRequested')).toHaveValue(reqWeek);
      const chips = await chipTexts(page);
      expect(chips.some((t) => t.startsWith('Requested Slot Week:')), 'R4: the requested week has its chip').toBe(true);
    } finally {
      await ctx.close();
    }
  });

  test('2. Request Approval — the original week narrows, and the two weeks compose (AND)', async ({ browser }) => {
    const { ctx, page } = await openPage(browser, RA);
    try {
      /* The page opens on Pending; this fixture's requests are decided, so show all.
         Every expected set below is derived from `MockData.approvalRequests` through
         the page's own `isInWeek()` — only the numbers come from the fixture. */
      await withFilters(page, async () => {
        await page.selectOption('#statusFilter', 'all');
      });

      const excluded = await expectedFor(page, { status: 'all' });
      await expectRowSet(page, excluded.ids, {
        message: 'the requests the COMPLETED exclusion leaves are the payload\'s own',
        pageSize: excluded.pageSize,
      });

      /* R4's AND proof needs two rows in two different weeks. The exclusion is ON at
         boot and hides the COMPLETED row, so it is switched OFF here — a control, not
         a write. Derived, never assumed: on a payload where the two sets are equal this
         step is harmless. */
      await withFilters(page, async () => {
        await page.locator('#completedToggle .toggle-track').click();
      });
      const base = await expectedFor(page, { status: 'all' });
      await expectRowSet(page, base.ids, {
        message: 'with the exclusion OFF the grid shows the payload\'s every request',
        pageSize: base.pageSize,
      });
      test.skip(base.ids.length < 2, 'fixture: fewer than two requests are visible — nothing for the weeks to narrow');

      /* the added ORIGINAL week narrows to one week alone */
      const origOptions = await weekOptionValues(page, '#weekFilterOriginal');
      const origCandidates = narrowingWeeks(base.baseWeeks.orig, base.ids.length, origOptions);
      test.skip(origCandidates.length === 0, 'fixture: no original week holds a strict subset of the visible requests');
      const [origWeek, origIds] = origCandidates[0];
      await withFilters(page, async () => {
        await page.selectOption('#weekFilterOriginal', origWeek);
      });
      await expectRowSet(page, origIds, {
        message: `R4: only the rows whose class date falls in original week ${origWeek}`,
        pageSize: base.pageSize,
      });
      expect(origIds.length, 'the original week really narrowed the list').toBeLessThan(base.ids.length);

      /* …and the two compose (AND): a requested week narrows the original-week set */
      const scoped = await expectedFor(page, { status: 'all', origWeek });
      const reqOptions = await weekOptionValues(page, '#weekFilter');
      const reqGroups = weekGroups(scoped.weeks.req, reqOptions);
      test.skip(reqGroups.length === 0, 'fixture: the rows in that original week carry no selectable requested week');
      const [reqWeek] = reqGroups[0];
      await withFilters(page, async () => {
        await page.selectOption('#weekFilter', reqWeek);
      });
      const both = await expectedFor(page, { status: 'all', origWeek, reqWeek });
      await expectRowSet(page, both.ids, { message: 'R4: only rows in BOTH weeks', pageSize: base.pageSize });
      expect(
        both.rows.every((r) => r.origWeek === origWeek && r.reqWeek === reqWeek),
        'R4: both weeks hold on every surviving row',
      ).toBe(true);

      /* an impossible pair proves it is an AND, not an OR — two weeks the payload's
         own rows never carry together */
      const impossible = base.impossible;
      test.skip(
        !impossible || !origOptions.includes(impossible.orig) || !reqOptions.includes(impossible.req),
        'fixture: no selectable original/requested week pair matches zero requests — no AND witness',
      );
      await withFilters(page, async () => {
        await page.selectOption('#weekFilterOriginal', impossible!.orig);
        await page.selectOption('#weekFilter', impossible!.req);
      });
      await expectRowSet(page, [], {
        message: 'no row is in that original week AND that requested week',
        pageSize: base.pageSize,
      });

      /* field order in BOTH the fields and the chips (R4): Status · Original · Requested */
      const statusOptions = await page.$$eval('#statusFilter option', (os) =>
        os.map((o) => (o as HTMLOptionElement).value),
      );
      const witness = base.rows.find(
        (r) => statusOptions.includes(r.status) && origOptions.includes(r.origWeek) && reqOptions.includes(r.reqWeek),
      );
      test.skip(!witness, 'fixture: no request can be pinned by status + both weeks — no chip to order');
      await withFilters(page, async () => {
        await page.selectOption('#statusFilter', witness!.status);
        await page.selectOption('#weekFilterOriginal', witness!.origWeek);
        await page.selectOption('#weekFilter', witness!.reqWeek);
      });
      await expectChips(page, /Status:.*Original Slot Week:.*Requested Slot Week:/);
      const pinned = await expectedFor(page, {
        status: witness!.status,
        origWeek: witness!.origWeek,
        reqWeek: witness!.reqWeek,
      });
      expect(pinned.ids.length, 'the row the chips name survives its own filters').toBeGreaterThan(0);
      await expectRowSet(page, pinned.ids, { message: 'the grid agrees with the chips', pageSize: base.pageSize });
    } finally {
      await ctx.close();
    }
  });

  test('3. Class Replacements — the requested week hides the unarranged rows, and its chip clears itself', async ({ browser }) => {
    const { ctx, page } = await openPage(browser, CR);
    try {
      const base = await expectedFor(page);
      await expectRowSet(page, base.ids, {
        bodyId: 'upcomingBody',
        message: 'the grid shows the seeded student\'s own occurrences',
        pageSize: base.pageSize,
      });

      /* The student's original-week select is 1-BASED while the payload's `r.week` is
         0-based (renderUpcoming subtracts one); `expectedFor` maps a row to the select
         value the page itself uses, so the week chosen here is whichever one really
         narrows this payload. */
      const origOptions = await weekOptionValues(page, '#weekFilter');
      test.skip(origOptions.length === 0, 'fixture: this page offers no original week to filter by');
      const origCandidates = narrowingWeeks(base.baseWeeks.orig, base.ids.length, origOptions);
      test.skip(origCandidates.length === 0, 'fixture: no original week holds a strict subset of the student\'s occurrences');
      const [origWeek, origIds] = origCandidates[0];
      await withFilters(page, async () => {
        await page.selectOption('#weekFilter', origWeek);
      });
      await expectRowSet(page, origIds, {
        bodyId: 'upcomingBody',
        message: `option ${origWeek}: exactly the occurrences in that week`,
      });

      /* a specific requested week hides every unarranged row (design §4.3) */
      const scoped = await expectedFor(page, { origWeek });
      const reqOptions = await weekOptionValues(page, '#weekFilterRequested');
      test.skip(reqOptions.length === 0, 'fixture: this page offers no requested week to filter by');
      const reqGroups = weekGroups(scoped.weeks.req, reqOptions);
      const reqWeek = reqGroups.length ? reqGroups[0][0] : reqOptions[0];
      const narrowed = await expectedFor(page, { origWeek, reqWeek });

      await withFilters(page, async () => {
        await page.selectOption('#weekFilterRequested', reqWeek);
      });
      await expectRowSet(page, narrowed.ids, {
        bodyId: 'upcomingBody',
        message: 'R4: a specific requested week hides every unarranged row',
      });
      if (reqGroups.length) {
        expect(narrowed.rows.every((r) => r.arranged), 'R4: every surviving row carries an arrangement').toBe(true);
        expect(narrowed.ids.length, 'R4: a specific requested week narrows the list').toBeLessThan(scoped.ids.length);
      }
      await expectChips(page, /Requested Slot Week:/);
      await expectChips(page, /Original Slot Week:.*Requested Slot Week:/);

      await page.locator('#filterChips .filter-chip', { hasText: 'Requested Slot Week:' }).locator('.filter-chip-remove').click();
      await expect(page.locator('#weekFilterRequested')).toHaveValue('all');
      await expect(page.locator('#weekFilter'), 'the original week is untouched').toHaveValue(origWeek);
      await expectRowSet(page, origIds, { bodyId: 'upcomingBody', message: 'the original-week rows are back' });
    } finally {
      await ctx.close();
    }
  });

  test('(fixture gap) the student requested week keeps the rows arranged in it', async ({ browser }) => {
    /* Kept as a NAMED gap, not worked around: on the current fixture every occurrence
       of student 24DFT0001 has `r.req = null`, so "the rows that survive carry that
       requested week" cannot be demonstrated here the way receipt 1b does it for My
       Replacements — and no receipt may manufacture an arrangement or write to the DB.
       The skip is derived, so a seeded arrangement turns the receipt back on instead of
       leaving a dead test behind. */
    const { ctx, page } = await openPage(browser, CR);
    try {
      const base = await expectedFor(page);
      const arranged = base.rows.filter((r) => r.arranged);
      test.skip(arranged.length === 0, 'fixture: no student row in the testing DB carries r.req — no arranged row to keep');

      const reqOptions = await weekOptionValues(page, '#weekFilterRequested');
      const reqGroups = weekGroups(base.weeks.req, reqOptions);
      test.skip(reqGroups.length === 0, 'fixture: the arranged occurrences fall outside every selectable requested week');
      const [reqWeek, reqIds] = reqGroups[0];

      await withFilters(page, async () => {
        await page.selectOption('#weekFilterRequested', reqWeek);
      });
      const kept = await expectedFor(page, { reqWeek });
      await expectRowSet(page, kept.ids, {
        bodyId: 'upcomingBody',
        message: 'only the occurrences arranged in that requested week',
      });
      expect(kept.ids.length, 'the surviving rows are that requested week\'s arranged ones').toBe(reqIds.length);
    } finally {
      await ctx.close();
    }
  });

  test('4. the bulk action clears BOTH weeks on every page', async ({ page, browser }) => {
    /* My Replacements — Reset is the panel head's own button (R4: page defaults,
       not "everything off"): both weeks back to All Weeks, status all, exclusion ON.
       The weeks used are the select's own last/first options — any real value. */
    await goto(page, MR);
    const mrWeeks = await weekOptionValues(page, '#weekFilter');
    const mrReqWeeks = await weekOptionValues(page, '#weekFilterRequested');
    await openFiltersPanel(page);
    await page.selectOption('#weekFilter', mrWeeks[mrWeeks.length - 1]);
    await page.selectOption('#weekFilterRequested', mrReqWeeks[0]);
    await expect(page.locator('#weekFilterRequested')).toHaveValue(mrReqWeeks[0]);
    await page.locator('#filtersResetButton').click();
    await expect(page.locator('#weekFilter'), 'R4: Reset clears the original week').toHaveValue('all');
    await expect(page.locator('#weekFilterRequested'), 'R4: Reset clears the requested week').toHaveValue('all');
    await expect(page.locator('#statusFilter'), 'R4: Reset restores this page\'s defaults').toHaveValue('all');
    await expect(page.locator('#hideCompleted'), 'R4: …including the exclusion ON').toBeChecked();
    await expectChips(page, /^(?!.*Slot Week).*$/, 'no week chip survives Reset');
    await closeFiltersPanel(page);

    /* Request Approval — same Reset, whose own default status is Pending */
    const ra = await openPage(browser, RA);
    try {
      const origWeeks = await weekOptionValues(ra.page, '#weekFilterOriginal');
      const reqWeeks = await weekOptionValues(ra.page, '#weekFilter');
      await openFiltersPanel(ra.page);
      await ra.page.selectOption('#weekFilterOriginal', origWeeks[origWeeks.length - 1]);
      await ra.page.selectOption('#weekFilter', reqWeeks[reqWeeks.length - 1]);
      await expect(ra.page.locator('#weekFilter')).toHaveValue(reqWeeks[reqWeeks.length - 1]);
      await ra.page.locator('#filtersResetButton').click();
      await expect(ra.page.locator('#weekFilter'), 'R4: Reset clears the requested week').toHaveValue('all');
      await expect(ra.page.locator('#weekFilterOriginal'), 'R4: Reset clears the original week').toHaveValue('all');
      await expect(ra.page.locator('#statusFilter'), 'R4: Reset restores Pending here').toHaveValue('Pending');
      await expect(ra.page.locator('#hideCompletedToggle')).toBeChecked();
    } finally {
      await ra.ctx.close();
    }

    /* Class Replacements — NO Reset at all (proposal decision 1): Clear all is the
       page's bulk action, and it clears both weeks. */
    const cr = await openPage(browser, CR);
    try {
      await expect(cr.page.locator('#filtersResetButton'), 'proposal decision 1: the student head has no Reset').toHaveCount(0);
      const crWeeks = await weekOptionValues(cr.page, '#weekFilter');
      const crReqWeeks = await weekOptionValues(cr.page, '#weekFilterRequested');
      await openFiltersPanel(cr.page);
      await cr.page.selectOption('#weekFilter', crWeeks[crWeeks.length - 1]);
      await cr.page.selectOption('#weekFilterRequested', crReqWeeks[0]);
      await expect(cr.page.locator('#weekFilterRequested')).toHaveValue(crReqWeeks[0]);
      await closeFiltersPanel(cr.page);
      await cr.page.locator('#filterChips .filter-chip-clear-all').click();
      await expect(cr.page.locator('#weekFilter')).toHaveValue('all');
      await expect(cr.page.locator('#weekFilterRequested')).toHaveValue('all');
      const crBase = await expectedFor(cr.page);
      await expectRowSet(cr.page, crBase.ids, { bodyId: 'upcomingBody', message: 'every row is back' });
      await expect.poll(async () => (await chipTexts(cr.page)).length, { message: 'Clear all empties the chip bar' }).toBe(0);
    } finally {
      await cr.ctx.close();
    }
  });

  test('5. the saved view remembers both weeks', async ({ page, browser }) => {
    /* My Replacements — `mrh-filters`. The two week values are the select's own
       options, so the receipt tracks the field, not one fixture's week numbers. */
    await goto(page, MR);
    const mrWeeks = await weekOptionValues(page, '#weekFilter');
    const mrReqWeeks = await weekOptionValues(page, '#weekFilterRequested');
    const week = mrWeeks[mrWeeks.length - 1];
    const reqWeek = mrReqWeeks[mrReqWeeks.length - 2] ?? mrReqWeeks[0];
    await withFilters(page, async () => {
      await page.selectOption('#weekFilter', week);
      await page.selectOption('#weekFilterRequested', reqWeek);
    });
    expect(
      await page.evaluate(() => localStorage.getItem('mrh-filters')),
      'R4: both weeks are written to the saved view',
    ).toContain(`"requestedWeek":"${reqWeek}"`);
    await page.reload({ waitUntil: 'networkidle' });
    await goto(page, MR);
    await expect(page.locator('#weekFilter'), 'R4: the original week comes back').toHaveValue(week);
    await expect(page.locator('#weekFilterRequested'), 'R4: the requested week comes back').toHaveValue(reqWeek);

    /* Class Replacements — its own key, read from the page's own script rather than
       guessed (VIEW_KEY is a closure constant, so the served markup is the source) */
    const cr = await openPage(browser, CR);
    try {
      const viewKey = await cr.page.evaluate(() => {
        const m = document.documentElement.innerHTML.match(/VIEW_KEY\s*=\s*'([^']+)'/);
        return m ? m[1] : '';
      });
      expect(viewKey, 'the student page declares its own VIEW_KEY').toBeTruthy();

      const crWeeks = await weekOptionValues(cr.page, '#weekFilter');
      const crReqWeeks = await weekOptionValues(cr.page, '#weekFilterRequested');
      const week = crWeeks[crWeeks.length - 1];
      const reqWeek = crReqWeeks[crReqWeeks.length - 1];
      await withFilters(cr.page, async () => {
        await cr.page.selectOption('#weekFilter', week);
        await cr.page.selectOption('#weekFilterRequested', reqWeek);
      });
      const saved = await cr.page.evaluate((key) => localStorage.getItem(key), viewKey);
      expect(saved, `R4: ${viewKey} carries both weeks`).toContain(`"reqWeek":"${reqWeek}"`);

      await cr.page.reload({ waitUntil: 'networkidle' });
      await goto(cr.page, CR);
      await expect(cr.page.locator('#weekFilter'), 'R4: the original week comes back').toHaveValue(week);
      await expect(cr.page.locator('#weekFilterRequested'), 'R4: the requested week comes back').toHaveValue(reqWeek);
    } finally {
      await cr.ctx.close();
    }

    /* Request Approval — the save side passes here; the restore side is receipt 6
       below. The weeks are the selects' own options. */
    const ra = await openPage(browser, RA);
    try {
      const origWeeks = await weekOptionValues(ra.page, '#weekFilterOriginal');
      const reqWeeks = await weekOptionValues(ra.page, '#weekFilter');
      const origWeek = origWeeks[origWeeks.length - 1];
      const reqWeek = reqWeeks[reqWeeks.length - 1];
      await withFilters(ra.page, async () => {
        await ra.page.selectOption('#weekFilterOriginal', origWeek);
        await ra.page.selectOption('#weekFilter', reqWeek);
      });
      const stored = await ra.page.evaluate(() => localStorage.getItem('request-approval-filters'));
      expect(stored, 'R4: the PL page writes BOTH weeks').toContain(`"origWeekFilter":"${origWeek}"`);
      expect(stored).toContain(`"weekFilter":"${reqWeek}"`);
    } finally {
      await ra.ctx.close();
    }
  });

  test('6. Request Approval restores both weeks after a reload', async ({ browser }) => {
    /* This receipt originally found a real bug: the boot order is
       `initRpp({onChange: saveFilters})` FIRST and `SkeletonLoader.with(… restoreFilters() …)`
       after it, so the Rows-Per-Page init called saveFilters() while both selects were
       still on their populated default ('all') and overwrote the stored view before
       anything read it — the original week filter included, i.e. pre-existing. Fixed in
       the page with a `filtersRestored` gate: saveFilters() ignores calls made before
       restoreFilters() has read the stored view. */
    const ra = await openPage(browser, RA);
    try {
      const origWeeks = await weekOptionValues(ra.page, '#weekFilterOriginal');
      const reqWeeks = await weekOptionValues(ra.page, '#weekFilter');
      const origWeek = origWeeks[origWeeks.length - 1];
      const reqWeek = reqWeeks[reqWeeks.length - 1];
      await withFilters(ra.page, async () => {
        await ra.page.selectOption('#weekFilterOriginal', origWeek);
        await ra.page.selectOption('#weekFilter', reqWeek);
      });
      await expect(ra.page.locator('#weekFilterOriginal')).toHaveValue(origWeek);
      await expect(ra.page.locator('#weekFilter')).toHaveValue(reqWeek);

      const stored = await ra.page.evaluate(() => localStorage.getItem('request-approval-filters'));
      expect(stored, 'R4: the PL page writes BOTH weeks').toContain(`"origWeekFilter":"${origWeek}"`);
      expect(stored).toContain(`"weekFilter":"${reqWeek}"`);

      await ra.page.reload({ waitUntil: 'networkidle' });
      await goto(ra.page, RA);
      await expect(ra.page.locator('#weekFilterOriginal'), 'R4: the original week comes back after a reload').toHaveValue(origWeek);
      await expect(ra.page.locator('#weekFilter'), 'R4: the requested week comes back after a reload').toHaveValue(reqWeek);
    } finally {
      await ra.ctx.close();
    }
  });
});
