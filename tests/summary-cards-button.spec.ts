import { test, expect, type Page } from '@playwright/test';

/**
 * Summary cards behind one header button on phones — summary-cards-button
 * (SDD `.sdd/changes/summary-cards-button`, specs R1–R7).
 *
 * At ≤768px each page's own `#summarySection` is MOVED (never copied) into the one
 * shared sheet emitted by `partials/ui-summary-bar`, and opens from a bare
 * bar-chart icon beside the help "?". At ≥769px nothing moves, nothing shows and
 * the button is `display:none`.
 *
 * Two identities: staff 5770 (flip-card pages) and a student (the plain variant on
 * `student-class-replacements`). Both states are produced by `tests/auth.setup.ts`
 * and the student login below; the Fortify limiter is 5/min per identity.
 */

const BASE = process.env.CRS_BASE || 'http://127.0.0.1:8030';
const PW = 'Tarumt@2026';

const FLIP_PAGES = [
  { name: 'my-timetable', path: '/my-timetable-ui', state: '.auth/staff-5770.json' },
  { name: 'venue-timetable', path: '/venue-timetable-ui', state: '.auth/staff-5770.json' },
];

const PLAIN = { name: 'replacement-history', path: '/student-class-replacements' };

async function loginStudent(page: Page) {
  await page.goto(`${BASE}/login/student`);
  await page.fill('#login_id', '25RSD0001');
  await page.fill('#password', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/student-my-timetable/, { timeout: 30_000 });
}

/** The cards must be WHERE the width says they are; returns what it saw. */
async function cardPlacement(page: Page) {
  return page.evaluate(() => {
    const section = document.getElementById('summarySection');
    const body = document.getElementById('summarySheetBody');
    const btn = document.getElementById('summaryButton');
    const sheet = document.getElementById('summarySheet');
    return {
      inSheet: !!section && !!body && section.parentNode === body,
      btnHiddenAttr: btn ? btn.hidden : null,
      btnDisplay: btn ? getComputedStyle(btn).display : null,
      sheetHidden: sheet ? sheet.hidden : null,
      bodyOpen: document.body.classList.contains('summary-sheet-open'),
      sectionDisplay: section ? getComputedStyle(section).display : null,
    };
  });
}

// ───────────────────────── T11a: relocation, repaint, empty view ─────────────────────────

test.describe('T11a — relocation, repaint and the empty view', () => {
  test('the button is a phone control: visible at 768, gone at 769', async ({ browser }) => {
    for (const pg of FLIP_PAGES) {
      for (const [width, expectVisible] of [[768, true], [769, false]] as const) {
        const ctx = await browser.newContext({ viewport: { width, height: 900 }, storageState: pg.state });
        const page = await ctx.newPage();
        await page.goto(BASE + pg.path, { waitUntil: 'networkidle' });
        await page.waitForTimeout(800);

        const btn = page.locator('#summaryButton');
        await expect(btn, `${pg.name} @${width} renders the button in the DOM`).toHaveCount(1);

        /* VISIBILITY, never toHaveCount: the button is Blade-rendered for opted
           pages and merely display:none above the breakpoint. */
        if (expectVisible) {
          await expect(btn, `${pg.name} @${width} shows the button`).toBeVisible();
          await expect(btn).toHaveAccessibleName('Summary');
          const box = (await btn.boundingBox())!;
          expect(Math.round(box.width), 'the phone target is 44px wide').toBe(44);
          expect(Math.round(box.height), 'the phone target is 44px tall').toBe(44);
        } else {
          await expect(btn, `${pg.name} @${width} hides the button`).toBeHidden();
        }
        await ctx.close();
      }
    }
  });

  test('the cards leave the page flow at ≤768 and stay inline at ≥769', async ({ browser }) => {
    for (const pg of FLIP_PAGES) {
      const phone = await browser.newContext({ viewport: { width: 390, height: 900 }, storageState: pg.state });
      const p1 = await phone.newPage();
      await p1.goto(BASE + pg.path, { waitUntil: 'networkidle' });
      await p1.waitForTimeout(900);
      let at = await cardPlacement(p1);
      expect(at.inSheet, `${pg.name}: the section moved into the shared sheet`).toBe(true);
      expect(at.sheetHidden, `${pg.name}: the sheet itself is closed`).toBe(true);
      /* the frozen ids still exist — moved, never re-rendered */
      await expect(p1.locator('#summarySheetBody #sumTotal')).toHaveCount(1);
      await phone.close();

      const desk = await browser.newContext({ viewport: { width: 1440, height: 900 }, storageState: pg.state });
      const p2 = await desk.newPage();
      await p2.goto(BASE + pg.path, { waitUntil: 'networkidle' });
      await p2.waitForTimeout(900);
      at = await cardPlacement(p2);
      expect(at.inSheet, `${pg.name}: inline at 1440`).toBe(false);
      expect(at.btnHiddenAttr, `${pg.name}: button hidden at 1440`).toBe(true);
      await expect(p2.locator('#summarySection #sumTotal')).toBeVisible();
      await desk.close();
    }
  });

  test('opening moves nothing but shows the cards, the lock and the expanded state', async ({ browser }) => {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 900 }, storageState: '.auth/staff-5770.json' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(900);

    await page.locator('#summaryButton').click();
    await expect(page.locator('#summarySheet')).toBeVisible();
    await expect(page.locator('#summaryButton')).toHaveAttribute('aria-expanded', 'true');
    await expect(page.locator('#summarySheetTitle')).toHaveText('Summary');
    await expect(page.locator('.summary-sheet-body #summaryBar .summary-card')).toHaveCount(5);
    expect((await cardPlacement(page)).bodyOpen).toBe(true);
    /* focus moves into the panel */
    await expect(page.locator('.summary-sheet-close')).toBeFocused();
    await ctx.close();
  });

  test('a filter repaint reaches the relocated cards, and the venue page keeps them inside wire:ignore', async ({ browser }) => {
    /* Part 1 — a real repaint (status filter, My Replacements). The SAME filter is
       read twice, inline at 1440 and inside the phone sheet; both are painted by
       the page, so a stale or orphaned node would disagree. The filter measurably
       changes the number (4 -> 2 on the demo data), so "nothing repainted" cannot
       pass by accident. */
    const desk = await browser.newContext({ viewport: { width: 1440, height: 900 }, storageState: '.auth/staff-5770.json' });
    const dp = await desk.newPage();
    await dp.goto(BASE + '/my-replacements', { waitUntil: 'networkidle' });
    await dp.waitForTimeout(1000);
    const allCount = ((await dp.locator('#summarySection #summaryNeeds').textContent()) || '').trim();
    await dp.selectOption('#statusFilter', 'none');
    await dp.waitForTimeout(700);
    const filtInline = ((await dp.locator('#summarySection #summaryNeeds').textContent()) || '').trim();
    expect(filtInline, 'the filter really repaints the cards').not.toBe(allCount);
    await desk.close();

    const phone = await browser.newContext({ viewport: { width: 390, height: 900 }, storageState: '.auth/staff-5770.json' });
    const pp = await phone.newPage();
    await pp.goto(BASE + '/my-replacements', { waitUntil: 'networkidle' });
    await pp.waitForTimeout(1000);
    /* every control that repaints sits OUTSIDE the sheet, so the sheet is closed
       while the filter changes and reopened after — the moved node must carry it */
    await pp.selectOption('#statusFilter', 'none');
    await pp.waitForTimeout(800);
    await pp.locator('#summaryButton').click();
    await expect(pp.locator('#summarySheet')).toBeVisible();
    await expect(pp.locator('#summarySheetBody #summaryNeeds')).toHaveText(filtInline);
    await phone.close();

    /* Part 2 — the structural claim from the design: on the venue page both the
       section's origin and the sheet it moves into live inside the SAME
       wire:ignore subtree, so the move never leaves Livewire's ignore region. */
    const vctx = await browser.newContext({ viewport: { width: 390, height: 900 }, storageState: '.auth/staff-5770.json' });
    const vp = await vctx.newPage();
    await vp.goto(BASE + '/venue-timetable-ui', { waitUntil: 'networkidle' });
    await vp.waitForTimeout(1200);
    const wired = await vp.evaluate(() => {
      const sheet = document.getElementById('summarySheet');
      const section = document.getElementById('summarySection');
      const findIgnore = function (start: Element | null) {
        let node = start;
        while (node && node !== document.body) {
          if (node.hasAttribute && node.hasAttribute('wire:ignore')) return node;
          node = node.parentElement;
        }
        return null;
      };
      const ignore = findIgnore(sheet);
      return {
        sheetInIgnore: !!ignore,
        sectionInSameIgnore: !!(ignore && section && ignore.contains(section)),
        inSheet: !!section && section.parentNode === document.getElementById('summarySheetBody'),
      };
    });
    expect(wired.inSheet, 'the venue page relocated its cards too').toBe(true);
    expect(wired.sheetInIgnore, 'the sheet frame is inside the wire:ignore subtree').toBe(true);
    expect(wired.sectionInSameIgnore, 'the moved section never leaves that subtree').toBe(true);
    await vctx.close();
  });

  test('an empty view hides the button too — at runtime, not only at load', async ({ browser }) => {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 900 }, storageState: '.auth/staff-5770.json' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/my-replacements', { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    const btn = page.locator('#summaryButton');
    await expect(btn).toBeVisible();
    /* the section lives inside the CLOSED sheet at this width, so visibility of the
       node is the wrong question — its own display is the contract */
    expect((await cardPlacement(page)).sectionDisplay).not.toBe('none');

    /* a search that matches nothing makes the page hide the section
       (syncSummarySection(false)) — the button must follow it */
    await page.fill('#searchInput', 'zzzz-no-such-class');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(900);

    const at = await cardPlacement(page);
    expect(at.sectionDisplay, 'the page hid the cards').toBe('none');
    expect(at.btnHiddenAttr, 'the button follows the page, not a media query').toBe(true);
    await expect(btn).toBeHidden();
    /* and opening is refused */
    await page.evaluate(() => (window as any).SummarySheet.open(document.getElementById('summaryButton')));
    expect((await cardPlacement(page)).sheetHidden, 'no empty sheet can be opened').toBe(true);
    await ctx.close();
  });
});

// ───────────────────────── T11b: closing, layering, plain variant, print ─────────────────────────

test.describe('T11b — closing, layering, the plain variant and print', () => {
  test('three ways to close, all returning focus to the button', async ({ browser }) => {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 900 }, storageState: '.auth/staff-5770.json' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(900);
    const btn = page.locator('#summaryButton');

    for (const closer of ['close-button', 'escape', 'backdrop'] as const) {
      await btn.click();
      await expect(page.locator('#summarySheet')).toBeVisible();

      if (closer === 'close-button') await page.locator('.summary-sheet-close').click();
      else if (closer === 'escape') await page.keyboard.press('Escape');
      else await page.mouse.click(195, 60); // the dim area above the panel

      await expect(page.locator('#summarySheet'), `${closer} closes the sheet`).toBeHidden();
      await expect(btn, `${closer} returns focus`).toBeFocused();
      await expect(btn).toHaveAttribute('aria-expanded', 'false');
      expect((await cardPlacement(page)).bodyOpen, `${closer} releases the lock`).toBe(false);
    }
    await ctx.close();
  });

  test('the ⓘ opens ABOVE the sheet; one Escape closes only the ⓘ; the trap yields to it', async ({ browser }) => {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 900 }, storageState: '.auth/staff-5770.json' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(900);

    await page.locator('#summaryButton').click();
    await expect(page.locator('#summarySheet')).toBeVisible();

    /* flip the card first: `.summary-info` is opacity/hidden until the card is
       flipped or hovered — unchanged behaviour, and the sheet must not alter it */
    await page.locator('#summarySheetBody .summary-card').first().click();
    const info = page.locator('#summarySheetBody [data-summary-info]').first();
    await expect(info).toBeVisible();
    await info.click();
    await expect(page.locator('#summaryExplainModal')).toBeVisible();

    /* the explanation dialog must paint above the sheet, not behind it */
    const z = await page.evaluate(() => ({
      modal: Number(getComputedStyle(document.getElementById('summaryExplainModal')!).zIndex),
      sheet: Number(getComputedStyle(document.getElementById('summarySheet')!).zIndex),
    }));
    expect(z.modal, `ⓘ z ${z.modal} must beat the sheet z ${z.sheet}`).toBeGreaterThan(z.sheet);
    expect(await page.evaluate(() => (document.elementFromPoint(195, 300) as HTMLElement).closest('#summaryExplainModal') !== null)).toBe(true);

    /* the trap must be suspended while the ⓘ is up: Tab reaches its controls */
    const reachable = await page.evaluate(() => {
      const btns = [...document.querySelectorAll('#summaryExplainModal button')] as HTMLElement[];
      btns[0]?.focus();
      return document.activeElement?.closest('#summaryExplainModal') !== null;
    });
    expect(reachable, 'the ⓘ is reachable by keyboard above the sheet').toBe(true);

    /* ONE Escape: the ⓘ closes, the sheet and its lock survive */
    await page.keyboard.press('Escape');
    await expect(page.locator('#summaryExplainModal')).toBeHidden();
    await expect(page.locator('#summarySheet')).toBeVisible();
    expect((await cardPlacement(page)).bodyOpen).toBe(true);

    /* containment resumes in the sheet */
    await page.keyboard.press('Escape');
    await expect(page.locator('#summarySheet')).toBeHidden();
    await ctx.close();
  });

  test('plain variant: two columns, tap-to-flip, reachable cards, and a clean resize back', async ({ browser }) => {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 900 } });
    const page = await ctx.newPage();
    await loginStudent(page);
    await page.goto(BASE + PLAIN.path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);

    await page.locator('#summaryButton').click();
    await expect(page.locator('#summarySheet')).toBeVisible();

    /* two columns, not today's three cramped ones */
    const cols = await page.evaluate(() =>
      getComputedStyle(document.getElementById('summaryBar')!).gridTemplateColumns.split(' ').length);
    expect(cols, 'the plain variant gets the flip pages’ two-column phone sheet').toBe(2);

    /* the frozen R5 letter: the tablet band (769-1024) must keep TODAY's three
       columns — the block this lane must not disturb */
    for (const width of [769, 1024]) {
      await page.setViewportSize({ width, height: 900 });
      await page.waitForTimeout(400);
      const desktopCols = await page.evaluate(() =>
        getComputedStyle(document.getElementById('summaryBar')!).gridTemplateColumns.split(' ').length);
      expect(desktopCols, `plain variant keeps 3 columns at ${width}`).toBe(3);
    }
    await page.setViewportSize({ width: 390, height: 900 });
    await page.locator('#summaryButton').click();
    await expect(page.locator('#summarySheet')).toBeVisible();

    /* the plain cards are focusable and announce themselves inside the sheet */
    const card = page.locator('#summarySheetBody .summary-card').first();
    await expect(card).toHaveAttribute('role', 'group');
    await expect(card).toHaveAttribute('tabindex', '0');
    expect((await card.getAttribute('aria-label')) || '').toMatch(/tap to show what it counts/i);

    /* tap flips it, and the back has room — nothing clips */
    await card.click();
    await expect(card).toHaveClass(/is-flipped/);
    const overflow = await page.evaluate(() =>
      Math.max(...[...document.querySelectorAll('#summaryBar .summary-card-back')].map(b => b.scrollHeight - b.clientHeight)));
    expect(overflow, 'no card back clips its text').toBeLessThanOrEqual(1);

    /* back above the breakpoint: inline again, and the tap state is stripped */
    await page.setViewportSize({ width: 900, height: 900 });
    await page.waitForTimeout(500);
    const after = await page.evaluate(() => {
      const section = document.getElementById('summarySection')!;
      const cards = [...section.querySelectorAll('.summary-card')];
      return {
        inSheet: section.parentNode === document.getElementById('summarySheetBody'),
        flipped: cards.filter(c => c.classList.contains('is-flipped') || c.classList.contains('is-unflipped')).length,
        marked: section.querySelectorAll('[data-sheet-a11y]').length,
        sheetHidden: document.getElementById('summarySheet')!.hidden,
      };
    });
    expect(after.inSheet).toBe(false);
    expect(after.flipped, 'no pinned/!tapped state survives the resize').toBe(0);
    expect(after.marked, 'the added a11y attributes are stripped').toBe(0);
    expect(after.sheetHidden, 'the sheet closed itself on the way up').toBe(true);
    await ctx.close();
  });

  test('no card back overflows at 320, 390 and 768 — both variants', async ({ browser }) => {
    for (const [path, state] of [['/my-timetable-ui', '.auth/staff-5770.json'], ['/student-class-replacements', null]] as const) {
      for (const width of [320, 390, 768]) {
        const ctx = await browser.newContext({ viewport: { width, height: 900 }, storageState: state || undefined });
        const page = await ctx.newPage();
        if (!state) await loginStudent(page);
        await page.goto(BASE + path, { waitUntil: 'networkidle' });
        await page.waitForTimeout(900);
        await page.locator('#summaryButton').click();
        await expect(page.locator('#summarySheet')).toBeVisible();

        /* every back, flipped and unflipped, must fit its card */
        const worst = await page.evaluate(() => {
          let max = 0;
          for (const back of document.querySelectorAll('#summaryBar .summary-card-back')) {
            max = Math.max(max, back.scrollHeight - back.clientHeight);
          }
          return max;
        });
        expect(worst, `${path} @${width}: a card back clips its text`).toBeLessThanOrEqual(1);
        await ctx.close();
      }
    }
  });

  test('print: the sheet never captures the page, and the cards come back afterwards', async ({ browser }) => {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 900 }, storageState: '.auth/staff-5770.json' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(900);

    await page.locator('#summaryButton').click();
    await expect(page.locator('#summarySheet')).toBeVisible();

    await page.emulateMedia({ media: 'print' });
    const printed = await page.evaluate(() => ({
      overlay: getComputedStyle(document.getElementById('summarySheet')!).display,
      bodyOverflow: getComputedStyle(document.body).overflow,
      cards: document.querySelectorAll('#summaryBar .summary-card').length,
    }));
    expect(printed.overlay, 'the sheet is not printed').toBe('none');
    expect(printed.bodyOverflow, 'the scroll lock does not leak into print').not.toBe('hidden');
    expect(printed.cards, 'the cards are still there to print inline').toBe(5);

    await page.emulateMedia({ media: 'screen' });
    await page.evaluate(() => window.dispatchEvent(new Event('afterprint')));
    await page.waitForTimeout(400);
    expect((await cardPlacement(page)).inSheet, 'the cards return into the sheet after printing').toBe(true);
    await ctx.close();
  });
});
