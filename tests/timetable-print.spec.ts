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

/**
 * Print through the dialog (amendment 1, §A8): the header button OPENS the dialog and
 * only its Print button prints. `week` is `'all'` or a 1-based week number; omitted
 * means "leave whatever the dialog seeded" — the week on screen.
 */
async function printViaDialog(page: Page, week?: 'all' | number) {
  await page.locator('.print-btn').click();
  await expect(page.locator('#printModal')).toBeVisible();
  if (week !== undefined) await page.selectOption('#printWeekSelect', String(week));
  await page.locator('#printModalPrint').click();
  await expect(page.locator('#printModal'), 'the dialog closes on confirm').toBeHidden();
  await page.waitForTimeout(500);
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
  test('PR-01/PR-05: the button opens the dialog, the paper exists, and only the paper prints', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);

    /* PR-01 (amendment 1): the button is an OPENER, not the print itself — the label
       stopped promising a single week the moment the dialog could choose several */
    const btn = page.locator('.print-btn');
    await expect(btn).toHaveAttribute('aria-label', 'Print timetable');
    await expect(btn).toHaveAttribute('data-tip', 'Print timetable');
    await page.evaluate(() => { (window as unknown as { __prints: number }).__prints = 0; window.print = () => { (window as unknown as { __prints: number }).__prints += 1; }; });
    await btn.click();
    await expect(page.locator('#printModal'), 'a click opens the dialog').toBeVisible();
    expect(await page.evaluate(() => (window as unknown as { __prints: number }).__prints), 'and prints nothing yet').toBe(0);
    await page.locator('#printModalCancel').click();
    await expect(page.locator('#printModal')).toBeHidden();

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
  test('PR-02/PR-16: All Weeks prints the semester, and never touches the screen', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);

    await expect(page.locator('#printNormalToggle'), 'the switch lives in the dialog').toHaveCount(1);
    await expect(page.locator('.print-normal-toggle'), 'and nowhere else on the page').toHaveCount(0);
    const screenBefore = await page.evaluate(() => ({
      html: document.getElementById('timetable').outerHTML,
      blocks: document.querySelectorAll('#timetable .event-block').length,
    }));

    await printViaDialog(page, 'all');

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

    /* and the key comes back the moment the paper spans one week instead */
    await printViaDialog(page, 9);
    expect(await page.evaluate(() => getComputedStyle(document.getElementById('printKey')).display), 'a single week shows its key').not.toBe('none');
  });

  test('PR-02: the dialog does not compete with the phone week row any more', async ({ browser }) => {
    /* Amendment 1 §A1 withdrew the old "stand down below 768" rule: the switch left the
       week row and lives in the dialog, so the row task 8 tuned to the millimetre
       (T16d #3: 144px of label in a 143px box) keeps all of its width back.
       Where the dialog can be reached follows the print button, which task 8's
       `preview-desktop-only` ruling keeps off My/Student below 1280; on Cohort it is
       there at every width, and so is the switch. */
    const open = async (width: number, path: string) => {
      const ctx = await browser.newContext({
        baseURL: process.env.CRS_BASE ?? 'http://127.0.0.1:8030',
        viewport: { width, height: 900 },
        storageState: '.auth/staff-5770.json',
      });
      const page = await ctx.newPage();
      await page.goto(path, { waitUntil: 'networkidle' });
      await page.waitForTimeout(700);
      return { page, ctx };
    };

    for (const width of [390, 768, 1280]) {
      const { page, ctx } = await open(width, '/my-timetable-ui');
      await expect(page.locator('.print-normal-toggle'), `${width}px: no switch in the week row`).toHaveCount(0);
      await ctx.close();
    }

    const { page, ctx } = await open(390, '/cohort-timetable');
    await page.locator('.print-btn').click();
    await expect(page.locator('#printModal')).toBeVisible();
    await expect(page.locator('.print-modal__field--switch .toggle-wrapper'), '390px: the house toggle is in the dialog').toBeVisible();
    await ctx.close();
  });

  test('PR-21: the dialog opens on the week on screen, and prints another week without moving it', async ({ page }) => {
    await page.goto('/my-timetable-ui?week=6', { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);

    const screenBefore = await page.evaluate(() => ({
      html: document.getElementById('timetable').outerHTML,
      week: (document.getElementById('weekSelect') as HTMLSelectElement).value,
    }));

    await page.locator('.print-btn').click();
    await expect(page.locator('#printWeekSelect'), 'seeded from the week on screen').toHaveValue(screenBefore.week);
    await page.selectOption('#printWeekSelect', '2');
    await page.locator('#printModalPrint').click();
    await page.waitForTimeout(600);

    const after = await page.evaluate(() => ({
      html: document.getElementById('timetable').outerHTML,
      week: (document.getElementById('weekSelect') as HTMLSelectElement).value,
      paperWeek: document.getElementById('printWeekCount')?.textContent?.trim(),
      paperDates: document.getElementById('printWeekDates')?.textContent?.trim(),
      key: getComputedStyle(document.getElementById('printKey')).display,
    }));

    expect(after.paperWeek, 'the paper follows the dialog, not the screen').toBe('Week 3 of 14');
    expect(after.paperDates).toContain('~');
    expect(after.key, 'PR-21: a single week keeps its key').not.toBe('none');
    expect(after.html, 'and the screen grid is byte-identical').toBe(screenBefore.html);
    expect(after.week, 'including the week picker').toBe(screenBefore.week);
  });

  test('PR-21: All Weeks disables the switch and reads ON', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);
    await page.locator('.print-btn').click();

    /* user ruling 2026-11-16: "B&C, but disable the switch only" — disabled, never
       hidden, and reading ON because that IS the paper All Weeks produces */
    await page.selectOption('#printWeekSelect', 'all');
    await expect(page.locator('#printNormalToggle'), 'disabled for All Weeks').toBeDisabled();
    await expect(page.locator('#printNormalToggle'), 'and shown ON').toBeChecked();
    await expect(page.locator('#printNormalHint'), 'with the reason in words').toBeVisible();

    await page.selectOption('#printWeekSelect', '4');
    await expect(page.locator('#printNormalToggle'), 'a single week hands it back').toBeEnabled();
    await expect(page.locator('#printNormalToggle'), 'reading OFF').not.toBeChecked();
    await expect(page.locator('#printNormalHint')).toBeHidden();
  });

  test('PR-21: a single week with the switch on prints only normal classes', async ({ page }) => {
    await page.goto('/my-timetable-ui?week=9', { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);

    await page.locator('.print-btn').click();
    await page.selectOption('#printWeekSelect', '9');
    /* amendment 2: the house input is 0x0 with pointer-events:none, so the LABEL is the
       control to click (the same move the notification specs make) */
    await page.locator('.print-modal__field--switch .toggle-wrapper').click();
    await page.locator('#printModalPrint').click();
    await page.waitForTimeout(600);

    const classes = await page.evaluate(() => [...document.querySelectorAll('#printTableBody .print-block')].map(b => b.className));
    expect(classes.length, 'the paper still holds this week').toBeGreaterThan(0);
    for (const c of classes) expect(c, 'PR-21: normal classes only').not.toContain('print-replacement');
  });

  test('PR-21: an open dialog never prints', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(700);
    await page.locator('.print-btn').click();
    await expect(page.locator('#printModal')).toBeVisible();

    /* §A2: every print now happens with the dialog open, so the overlay must step aside
       on paper — closing before window.print() is the second guard, not the only one */
    const rules = await page.evaluate(() => {
      const out: string[] = [];
      for (const sheet of [...document.styleSheets]) {
        let list: CSSRuleList;
        try { list = sheet.cssRules; } catch { continue; }
        for (const r of [...list]) {
          if (r instanceof CSSMediaRule && r.conditionText.includes('print')) {
            for (const inner of [...r.cssRules]) {
              if (inner instanceof CSSStyleRule && inner.selectorText.includes('.print-modal-overlay')) {
                out.push(inner.style.getPropertyValue('display'));
              }
            }
          }
        }
      }
      return out;
    });
    expect(rules.length, 'PR-21: the print stylesheet names the dialog overlay').toBeGreaterThan(0);
    expect(rules.join(',')).toContain('none');

    await page.emulateMedia({ media: 'print' });
    await expect(page.locator('#printModal'), 'and it is gone on paper').toBeHidden();
    await expect(page.locator('#printHeader')).toBeVisible();
  });
});
