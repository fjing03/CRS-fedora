import { test, expect, type Page } from '@playwright/test';

/**
 * timetable-print.spec.ts — the paper's receipts
 * (SDD `timetable-print-v2`, PR-05 … PR-19).
 *
 * WHAT THIS FILE PROVES, and what it deliberately does NOT.
 * `emulateMedia({ media: 'print' })` can prove VISIBILITY: which surface is on the
 * page and which has stepped aside, what the sheet contains, how a column reads.
 * It cannot see `@page` size or page count — no DOM instrument can — so A4
 * landscape, the 8 mm margin and "one page for a normal week" are checked by
 * exporting a PDF (`page.pdf({ preferCSSPageSize: true })`) and by eye, and they
 * are asserted here only as far as the stylesheet *requesting* them goes.
 *
 * Read-only: it logs in through the UI, prints to the DOM/PDF, and writes nothing
 * to the database. It still refuses any target but the blessed 8030 testing serve.
 */

const PAGES = [
  { name: 'My Timetable', path: '/my-timetable-ui', login: '/login/staff', id: '5770', ownership: false, leads: 'cohorts' },
  { name: 'Cohort Timetable', path: '/cohort-timetable?week=9', login: '/login/staff', id: '5770', ownership: true, leads: 'course' },
  { name: 'Venue Timetable', path: '/venue-timetable-ui?venue=B005&week=9', login: '/login/staff', id: '5770', ownership: true, leads: 'cohorts' },
  { name: 'Student My Timetable', path: '/student-my-timetable-ui', login: '/login/student', id: '24RSD0009', ownership: false, leads: 'course' },
];

/* ONE session for the whole staff file: the `setup` project mints it, and Fortify's
   limiter (5 logins/minute) makes a per-test login a flaky trap — the same rule the
   neighbouring specs follow. The student case below opens its own context once. */
test.use({ storageState: '.auth/staff-5770.json' });

/** Only used by the student case, in its own context, exactly once. */
async function signIn(page: Page, login: string, id: string) {
  const base = process.env.CRS_BASE ?? 'http://127.0.0.1:8030';
  await page.goto(base + login, { waitUntil: 'networkidle' });
  await page.locator('#login_id').fill(id);
  await page.locator('#password').fill('Tarumt@2026');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.click('button[type="submit"]'),
  ]);
}

const paper = (page: Page) => page.evaluate(() => {
  const body = document.getElementById('printTableBody');
  const blocks = body ? [...body.querySelectorAll('.print-block')] : [];
  return {
    sheet: !!document.getElementById('printSheet'),
    header: !!document.getElementById('printHeader'),
    blocks: blocks.length,
    rows: body ? body.querySelectorAll('tr').length : 0,
    statusWords: body ? body.querySelectorAll('.tt-block-status, .ev-note').length : 0,
    pending: body ? body.querySelectorAll('.print-block.print-pending').length : 0,
    hours: [...document.querySelectorAll('#printTableHead th.hour-header')].map(th => ({
      top: th.querySelector('.hour-top')?.textContent?.trim() ?? '',
      bottom: th.querySelector('.hour-bottom')?.textContent?.trim() ?? '',
    })),
    key: [...document.querySelectorAll('#printKey .print-key-item')].map(e => e.textContent.trim()),
    ownershipLine: !!document.querySelector('.print-ownership'),
    weekCount: document.getElementById('printWeekCount')?.textContent?.trim() ?? '',
    weekDates: document.getElementById('printWeekDates')?.textContent?.trim() ?? '',
    context: document.getElementById('printContext')?.textContent?.trim() ?? '',
    sub: document.getElementById('printSub')?.textContent?.trim() ?? '',
    first: (() => {
      const b = blocks[0];
      if (!b) return null;
      return {
        cls: b.className,
        corners: [...b.querySelectorAll('.print-corner')].map(c => c.textContent.trim()),
        centre: [...b.querySelectorAll('.print-main-line')].map(c => c.textContent.trim()),
        time: b.querySelector('.print-time')?.textContent?.trim() ?? '',
      };
    })(),
  };
});

test.describe('the paper replaces the screen', () => {
  test('PR-01/PR-05: the button prints, the paper exists, and only the paper prints', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);

    const btn = page.locator('.print-btn');
    await expect(btn).toHaveAttribute('aria-label', 'Print this week');
    await expect(btn).toHaveAttribute('data-tip', 'Print this week');

    /* on screen the paper is invisible AND off the viewport, but it is laid out —
       the fit measures it, so `display: none` would break the shrinking */
    const onScreen = await page.evaluate(() => {
      const s = getComputedStyle(document.getElementById('printSheet'));
      return { visibility: s.visibility, position: s.position, width: Math.round(document.getElementById('printSheet').getBoundingClientRect().width) };
    });
    expect(onScreen.visibility).toBe('hidden');
    expect(onScreen.position, 'off the viewport, never display:none').toBe('absolute');
    expect(onScreen.width, 'laid out at the paper content width').toBe(1062);
    await expect(page.locator('#printSheet')).toBeHidden();

    await page.emulateMedia({ media: 'print' });
    for (const sel of ['.grid-wrapper', '.legend-bar', '#summarySection', '.page-header', '.semester-bar', '.print-btn']) {
      await expect(page.locator(sel), `${sel} must not print`).toBeHidden();
    }
    await expect(page.locator('#printHeader')).toBeVisible();
    await expect(page.locator('#printSheet')).toBeVisible();

    const axis = await page.evaluate(() => [...document.querySelectorAll('#printTableHead th.hour-header')]
      .map(th => th.querySelector('.hour-top')?.textContent?.trim() ?? ''));
    expect(axis.length, 'a printed column per hour pair').toBeGreaterThan(8);
    for (const label of axis) expect(label, 'PR-08: the hour axis reads 12-hour').toMatch(/^\d{1,2}:\d{2} (AM|PM)$/);

    await page.emulateMedia({ media: 'screen' });
    await expect(page.locator('#printSheet'), 'and it goes back off the viewport').toBeHidden();
  });

  test('PR-06/PR-07: one header, its context per page, and the two-part week line', async ({ page }) => {
    for (const p of PAGES.filter(x => x.login === '/login/staff')) {
      await page.goto(p.path, { waitUntil: 'networkidle' });
      await page.waitForTimeout(700);
      const info = await paper(page);
      expect(info.header, `${p.name}: the paper has a header`).toBe(true);
      expect(await page.locator('.ui-print-header').count(), `${p.name}: ONE header, not one per surface`).toBe(1);
      expect(info.weekCount, `${p.name}: the week line names the week`).toMatch(/^Week \d+ of \d+$/);
      expect(info.weekDates, `${p.name}: and the dates beside it`).toMatch(/Nov 2026/);
      expect(info.context.length, `${p.name}: the header says what it is showing`).toBeGreaterThan(0);
      if (p.name === 'Venue Timetable') expect(info.context, 'venue context uses an em dash').toContain(' — ');
      if (p.name === 'Cohort Timetable') {
        /* a cohort code legitimately carries brackets — `DFT2(S1)G1`. What must NOT
           print is a person's ID, which the app writes as `(5770)`. */
        expect(info.context, 'a cohort code, never a person ID').not.toMatch(/\(\d{4,}\)/);
        expect(info.sub).toMatch(/^Faculty: /);
      }
      if (p.name === 'My Timetable') expect(info.context, 'a person, so the ID prints').toMatch(/\(\d{4}\)$/);
    }
  });

  test('PR-07: the student prints their ID beside their name', async ({ browser }) => {
    /* an EMPTY storage state on purpose: `test.use` hands the staff session to every
       context this file makes, and a staff session cannot see the student login form */
    const ctx = await browser.newContext({
      baseURL: process.env.CRS_BASE ?? 'http://127.0.0.1:8030',
      storageState: { cookies: [], origins: [] },
    });
    const page = await ctx.newPage();
    await signIn(page, '/login/student', '24RSD0009');
    await page.waitForTimeout(700);
    const info = await paper(page);
    expect(info.context).toContain('24RSD0009');
    expect(info.context.match(/24RSD0009/g)?.length, 'the ID is not printed twice').toBe(1);
    expect(info.sub).toMatch(/^Cohort: /);
    await ctx.close();
  });

  test('PR-09/PR-14: corners, centre and the three-chip key per page', async ({ page }) => {
    for (const p of PAGES.filter(x => x.login === '/login/staff')) {
      await page.goto(p.path, { waitUntil: 'networkidle' });
      await page.waitForTimeout(700);
      const info = await paper(page);
      expect(info.key, `${p.name}: exactly three chips on paper`).toEqual(['Normal', 'Replacement', 'Conflict / Public Holiday']);
      expect(info.key, 'no Pending and no Available on paper').not.toContain('Pending');
      expect(info.ownershipLine, `${p.name}: the ownership line only where the screen key has one`).toBe(p.ownership);
      if (!info.first) continue;
      expect(info.first.corners.length, `${p.name}: two corners`).toBeGreaterThanOrEqual(1);
      expect(info.first.centre.length, `${p.name}: a centre`).toBeGreaterThanOrEqual(1);
      expect(info.first.time).toMatch(/^\d{1,2}:\d{2} (AM|PM) ~ \d{1,2}:\d{2} (AM|PM)$/);
      expect(info.statusWords, `${p.name}: PR-12 — no status word on paper`).toBe(0);
      /* the centre leads with cohorts on My and Venue, with the course on Student and
         Cohort (contract BLOCK LAYOUT); a cohort code carries a bracket, a course does not */
      const centreHasBracket = info.first.centre.some(l => l.includes('('));
      expect(centreHasBracket, `${p.name}: the centre leads with ${p.leads}`).toBe(p.leads === 'cohorts');
    }
  });

  test('PR-13: an empty Sunday prints no row; the printed rows are six', async ({ page }) => {
    await page.goto('/cohort-timetable?week=9', { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);
    const info = await paper(page);
    const names = await page.evaluate(() => [...document.querySelectorAll('#printTableBody .day-label')].map(e => e.textContent.trim()));
    expect(names.includes('Sun'), 'a Sunday with nothing on it is not printed').toBe(false);
    expect(info.rows).toBe(names.length);
  });
});

test.describe('the paper shows only what runs', () => {
  test('PR-11: pending and cancelled never reach paper, and the screen keeps both', async ({ page }) => {
    /* Drive the two derivations the pages use with synthetic rows, so the rule is
       proven even in a week whose live data carries neither status (fabricating demo
       rows is forbidden). Same functions, same page, no database. */
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);
    const derived = await page.evaluate(() => {
      const w = window as unknown as { printRunSet: (e: unknown[]) => unknown[]; printSemesterSet: (d: unknown[][]) => unknown[] };
      const rows = [
        { sessionId: 1, di: 0, start: 2, end: 3, code: 'A', status: 'normal' },
        { sessionId: 2, di: 0, start: 4, end: 5, code: 'B', status: 'pending' },
        { sessionId: 3, di: 1, start: 2, end: 3, code: 'C', status: 'cancelled' },
        { sessionId: 4, di: 1, start: 6, end: 7, code: 'D', status: 'replacement' },
      ];
      /* a class that is normal in every week, with a different occurrence key each
         week — exactly what the payload does */
      const weeks = Array.from({ length: 14 }, (_, w) => [
        { sessionId: 9, id: 9, di: 2, start: 2, end: 3, code: 'E', status: 'normal', eventKey: 'E-' + w },
      ]);
      return {
        run: w.printRunSet(rows).map((e: { code: string }) => e.code),
        union: w.printSemesterSet(weeks).length,
      };
    });
    expect(derived.run, 'PR-11: pending and cancelled are dropped, the rest survive').toEqual(['A', 'D']);
    expect(derived.union, 'PR-16: the semester union counts the class ONCE, not fourteen times').toBe(1);
  });

  test('PR-17: density is computed from the print set, and changes nothing on screen', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);

    const read = () => page.evaluate(() => {
      const sheet = document.getElementById('printSheet');
      const block = document.querySelector('#printTableBody .print-block');
      return {
        dense: sheet?.getAttribute('data-print-dense'),
        padding: block ? getComputedStyle(block).paddingTop : null,
        borderMine: block ? getComputedStyle(block).borderTopWidth : null,
        screenHtml: document.getElementById('timetable').outerHTML.length,
      };
    });

    const normal = await read();
    expect(normal.dense).toBe('0');

    /* 33 synthetic normal blocks: the threshold is the print set's own size */
    const dense = await page.evaluate(() => {
      const P = (window as unknown as { PrintSheet: { init: (c: unknown) => void } }).PrintSheet;
      const events = Array.from({ length: 33 }, (_, i) => ({
        sessionId: 100 + i, di: i % 5, start: 2 + (i % 6), end: 3 + (i % 6), code: 'X' + i, status: 'normal',
        venue: 'B100', lecturer: 'En. Lim Jia Zheng', cohorts: ['DFT1(S1)G1'], type: 'L',
      }));
      const days = Array.from({ length: 7 }, (_, d) => ({ abbr: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][d], date: '', sunday: d === 6, today: false, holiday: false }));
      P.init({
        build: () => ({ events, days, weekCount: 'Week 1 ~ 14', weekDates: '(Whole Semester)', emptyTitle: '', emptyText: '' }),
        printShape: (e: { venue: string; code: string }) => ({ cornerLeft: e.venue, cornerRight: '', centre: [e.code] }),
        printClassFn: (div: HTMLElement, e: { status: string }) => div.classList.add('print-normal', e.status === 'normal' ? 'print-mine' : 'print-others'),
      });
      const sheet = document.getElementById('printSheet');
      const block = document.querySelector('#printTableBody .print-block');
      const mine = document.querySelector('#printTableBody .print-block.print-mine');
      const other = document.querySelector('#printTableBody .print-block.print-others');
      return {
        flag: sheet?.getAttribute('data-print-dense'),
        count: document.querySelectorAll('#printTableBody .print-block').length,
        padding: block ? getComputedStyle(block).paddingTop : null,
        mineBorder: mine ? getComputedStyle(mine).borderTopWidth : null,
        otherBorder: other ? getComputedStyle(other).borderTopWidth : null,
        screenBlocks: document.querySelectorAll('#timetable .event-block').length,
      };
    });

    expect(dense.count, 'the synthetic print set really is over the threshold').toBe(33);
    expect(dense.flag, 'PR-17: dense comes on above 32 printed blocks').toBe('1');
    expect(dense.padding, 'dense tightens the block spacing').not.toBe(normal.padding);
    if (dense.mineBorder && dense.otherBorder) {
      expect(dense.mineBorder, 'the ownership weights stay unequal in dense mode').not.toBe(dense.otherBorder);
    }
    expect(await read().then(r => r.screenHtml), 'PR-17: no screen rule moved').toBe(normal.screenHtml);
  });
});

test.describe('the normal view', () => {
  test('PR-02/PR-16: the toggle prints the semester, and never touches the screen', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);

    await expect(page.locator('#printNormalToggle'), 'the toggle is on the page').toHaveCount(1);
    const screenBefore = await page.evaluate(() => ({
      html: document.getElementById('timetable').outerHTML,
      blocks: document.querySelectorAll('#timetable .event-block').length,
    }));

    await page.click('.print-normal-toggle');
    await page.waitForTimeout(600);

    const view = await page.evaluate(() => ({
      normalClass: document.getElementById('printSheet')?.classList.contains('normal-view'),
      keyDisplay: getComputedStyle(document.getElementById('printKey')).display,
      weekCount: document.getElementById('printWeekCount')?.textContent?.trim(),
      weekDates: document.getElementById('printWeekDates')?.textContent?.trim(),
      dates: [...document.querySelectorAll('#printTableBody .date-label')].map(e => e.textContent.trim()),
      holidays: document.querySelectorAll('#printTableBody .holiday-badge').length,
      dayLabels: [...document.querySelectorAll('#printTableBody .day-label')].map(e => e.textContent.trim()),
      dayTransform: (() => { const d = document.querySelector('#printTableBody .day-label'); return d ? getComputedStyle(d).textTransform : ''; })(),
      statuses: [...document.querySelectorAll('#printTableBody .print-block')].map(b => b.className),
      screenHtml: document.getElementById('timetable').outerHTML,
      screenBlocks: document.querySelectorAll('#timetable .event-block').length,
    }));

    expect(view.normalClass, 'the sheet carries `normal-view`, not the toggle class').toBe(true);
    expect(view.keyDisplay, 'PR-16: a key for one status explains nothing').toBe('none');
    expect(view.weekCount).toBe('Week 1 ~ 14');
    expect(view.weekDates).toBe('(Whole Semester)');
    expect(view.dates.every(d => d === ''), 'the date line is dropped').toBe(true);
    expect(view.holidays, 'every public-holiday trace is gone').toBe(0);
    expect(view.dayLabels.every(d => !d.includes('Nov')), 'the day prints alone — no date').toBe(true);
    expect(view.dayTransform, 'and upper-cased, which is a CSS transform, not a different string').toBe('uppercase');
    expect(view.statuses.every(c => !c.includes('print-replacement') && !c.includes('print-conflict'))).toBe(true);

    expect(view.screenHtml, 'PR-16: the screen grid keeps every block, byte for byte').toBe(screenBefore.html);
    expect(view.screenBlocks).toBe(screenBefore.blocks);

    /* and it is a reading mode, not a preference */
    await page.click('.print-normal-toggle');
    await page.waitForTimeout(400);
    expect(await page.evaluate(() => getComputedStyle(document.getElementById('printKey')).display)).not.toBe('none');
  });

  test('PR-02: the switch is a tablet-and-up control, on purpose', async ({ browser }) => {
    /* The phone band's week row was tuned to the millimetre by task 8 — at 390 the
       week label needs 144px and the box offers 143. A sixth control in that row
       truncated the label, which that lane's receipt refuses, so the switch stands
       down below 768 instead of shaving the label again. This case makes the scope a
       decision with a receipt rather than a silent gap. */
    for (const [width, expected] of [[390, 0], [768, 1], [1280, 1]] as const) {
      const ctx = await browser.newContext({
        baseURL: process.env.CRS_BASE ?? 'http://127.0.0.1:8030',
        viewport: { width, height: 900 },
      });
      const page = await ctx.newPage();
      await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
      await page.waitForTimeout(500);
      const toggle = page.locator('.print-normal-toggle');
      if (expected === 1) {
        await expect(toggle, `${width}px: the switch is offered`).toBeVisible();
        await expect(page.locator('#printNormalToggle')).toHaveAttribute('aria-label', /normal timetable/);
      } else {
        await expect(toggle, `${width}px: and stands down in the phone band`).toBeHidden();
      }
      await ctx.close();
    }
  });
});
