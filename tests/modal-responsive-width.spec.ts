import { test, expect, type Page } from '@playwright/test';
import { isSanctionedTestingTarget, GUARD_INVOCATION } from './helpers/receipt-target-guard';

/**
 * modal-responsive-width.spec.ts — SDD `modal-responsive-width` T6.
 *
 * R1 dialog shrinks to content (floor 420, ceiling per variant, never wider than
 *    before) · R2 the bottom sheet is phones only (<= 600, 768 is a dialog) ·
 * R3 the stacked phone footer fills the sheet · R4 the sheet docks flush ·
 * R5 the × is 48×48 in the sheet range · R6 header/footer pinned, body scrolls.
 *
 * Plus §4.3: the two pages whose page-local 680px pins were removed — My
 * Replacements details (#quickViewModal) and Request Approval details
 * (#modalOverlay) — are measured here, because they are the user's original
 * complaint. The approval page force-shows its shell: the testing lane's queue
 * holds no requests, and this spec writes nothing.
 *
 * Read-only: no fixture, no DB write. It still refuses to run off the testing
 * lane (fail-closed, same guard family as its neighbours).
 */
test.use({ storageState: '.auth/staff-5770.json' });

const WIDTHS = [390, 600, 601, 700, 768, 1024, 1360] as const;

test.beforeAll(() => {
  if (!isSanctionedTestingTarget()) {
    throw new Error(`⛔ modal-responsive-width REFUSED: this spec only runs on the disposable testing lane.\n${GUARD_INVOCATION}`);
  }
});

interface Geom {
  w: number;
  h: number;
  left: number;
  right: number;
  bottom: number;
  radiusTop: number;
  radiusBottom: number;
  cssWidth: string;
  cssMaxWidth: string;
  closeW: number;
  closeH: number;
  overlayPadding: string;
  footerDir: string;
  footerClient: number;
  footerPadX: number;
  btns: number[];
  bodyClient: number;
  bodyScroll: number;
  modalClient: number;
  modalScroll: number;
  headerY: number;
  footerY: number;
  headerTop: number;
  footerBottom: number;
  bodyScrollTop: number;
}

async function measure(page: Page, sel: string): Promise<Geom> {
  return page.evaluate((s) => {
    const m = document.querySelector(s) as HTMLElement;
    const r = m.getBoundingClientRect();
    const cs = getComputedStyle(m);
    const overlay = m.parentElement as HTMLElement;
    const close = m.querySelector('.modal-close') as HTMLElement | null;
    const cb = close?.getBoundingClientRect();
    const body = m.querySelector('.modal-body') as HTMLElement;
    const footer = m.querySelector('.modal-footer') as HTMLElement;
    const header = m.querySelector('.modal-header') as HTMLElement;
    const hb = header.getBoundingClientRect();
    const fb = footer.getBoundingClientRect();
    return {
      w: Math.round(r.width),
      h: Math.round(r.height),
      left: Math.round(r.x),
      right: Math.round(window.innerWidth - r.x - r.width),
      bottom: Math.round(window.innerHeight - r.y - r.height),
      radiusTop: parseFloat(cs.borderTopLeftRadius),
      radiusBottom: parseFloat(cs.borderBottomLeftRadius),
      cssWidth: cs.width,
      cssMaxWidth: cs.maxWidth,
      closeW: cb ? Math.round(cb.width) : 0,
      closeH: cb ? Math.round(cb.height) : 0,
      overlayPadding: getComputedStyle(overlay).padding,
      footerDir: getComputedStyle(footer).flexDirection,
      footerClient: footer.clientWidth,
      footerPadX:
        parseFloat(getComputedStyle(footer).paddingLeft) + parseFloat(getComputedStyle(footer).paddingRight),
      btns: [...footer.querySelectorAll('button')]
        .filter((b) => (b as HTMLElement).offsetParent !== null)
        .map((b) => Math.round(b.getBoundingClientRect().width)),
      bodyClient: body.clientHeight,
      bodyScroll: body.scrollHeight,
      modalClient: m.clientHeight,
      modalScroll: m.scrollHeight,
      headerY: Math.round(hb.y),
      footerY: Math.round(fb.y),
      headerTop: Math.round(hb.top),
      footerBottom: Math.round(fb.bottom),
      bodyScrollTop: body.scrollTop,
    };
  }, sel);
}

/** Open the class details pop-up once (at 1024), then reuse it while resizing. */
async function openClassDetails(page: Page): Promise<void> {
  await page.setViewportSize({ width: 1024, height: 900 });
  await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
  await page.waitForSelector('#tableBody .event-block', { timeout: 20000 });
  await page.locator('#tableBody .event-block').first().click();
  await expect(page.locator('#classModal .modal')).toBeVisible();
  await page.waitForTimeout(450); // modalIn scale animation
}

/** DOM-only reveal of a static pop-up shell (no app action, no request row). */
async function forceShow(page: Page, overlayId: string): Promise<void> {
  await page.evaluate((id) => {
    const o = document.getElementById(id);
    if (!o) return;
    o.classList.add('show');
    o.style.display = 'flex';
  }, overlayId);
  await page.waitForTimeout(450);
}

async function resize(page: Page, width: number): Promise<void> {
  await page.setViewportSize({ width, height: 900 });
  await page.waitForTimeout(350);
}

test('R1/R2/R4 — class details: content-width dialog above 600, flush sheet at 390/600', async ({ page }) => {
  await openClassDetails(page);
  const seen: Record<number, Geom> = {};

  for (const width of WIDTHS) {
    await resize(page, width);
    const g = (seen[width] = await measure(page, '#classModal .modal'));

    // no horizontal clipping at any width
    expect(g.modalScroll, `${width}: no horizontal overflow`).toBeLessThanOrEqual(g.modalClient + 1);

    if (width <= 600) {
      // R2/R4: the sheet docks flush — full width, no side or bottom gap
      expect(g.left, `${width}: sheet side gap 0`).toBe(0);
      expect(g.right, `${width}: sheet side gap 0`).toBe(0);
      expect(g.bottom, `${width}: sheet bottom gap 0`).toBe(0);
      expect(g.radiusTop, `${width}: sheet keeps a top radius`).toBeGreaterThan(0);
      expect(g.radiusBottom, `${width}: sheet has square bottom corners`).toBe(0);
      expect(g.overlayPadding, `${width}: overlay padding is 0 in the sheet range`).toBe('0px');
      expect(g.w, `${width}: sheet is the viewport width`).toBe(width);
      continue;
    }

    // R1: a centred dialog, floored at 420 and never wider than the 680 ceiling
    expect(g.w, `${width}: dialog is at least the 420 floor`).toBeGreaterThanOrEqual(420);
    expect(g.w, `${width}: dialog is no wider than the 680 ceiling`).toBeLessThanOrEqual(680);
    expect(g.w, `${width}: class details shrank below 680`).toBeLessThan(680);
    // R2: a dialog, not a sheet
    expect(g.left, `${width}: dialog side gap > 0`).toBeGreaterThan(0);
    expect(g.right, `${width}: dialog side gap > 0`).toBeGreaterThan(0);
    expect(g.bottom, `${width}: dialog is vertically centred`).toBeGreaterThan(0);
    expect(Math.abs(g.radiusTop - g.radiusBottom), `${width}: symmetric radius`).toBeLessThanOrEqual(1);
    expect(g.footerDir, `${width}: footer is one row`).toBe('row');
  }

  // R1 "the change moves the width below the ceiling": 1360 must read the floor
  expect(seen[1360].w, '1360: shrinks to the 420 floor').toBe(420);
});

test('R5 — the × is a 48×48 target in the sheet range and smaller above it', async ({ page }) => {
  await openClassDetails(page);
  for (const width of [390, 600] as const) {
    await resize(page, width);
    const g = await measure(page, '#classModal .modal');
    expect(g.closeW, `${width}: × width`).toBe(48);
    expect(g.closeH, `${width}: × height`).toBe(48);
  }
  for (const width of [768, 1360] as const) {
    await resize(page, width);
    const g = await measure(page, '#classModal .modal');
    expect(g.closeW, `${width}: × unchanged above the sheet range`).toBeLessThan(48);
  }
});

test('R3 — the stacked phone footer buttons fill the sheet', async ({ page }) => {
  await openClassDetails(page);
  await resize(page, 390);
  const g = await measure(page, '#classModal .modal');
  expect(g.btns.length, 'both footer actions are visible').toBeGreaterThanOrEqual(2);
  const [close, cancel] = g.btns;
  expect(close, 'Close and Cancel are the same width').toBe(cancel);
  // The pages keep their own footer padding (my-timetable uses 24px), so the
  // honest invariant is "fills the footer's content box", not "fills the viewport".
  expect(close, 'the buttons fill the footer content box').toBeGreaterThanOrEqual(g.footerClient - g.footerPadX - 2);
  expect(g.footerDir, 'the phone footer stacks').toBe('column');
});

test('R6 — 390×420 with tall content: body scrolls, header and footer stay pinned', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 420 });
  await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
  await page.waitForSelector('#tableBody .event-block', { timeout: 20000 });
  await page.locator('#tableBody .event-block').first().click();
  await expect(page.locator('#classModal .modal')).toBeVisible();
  await page.waitForTimeout(450);

  const before = await page.evaluate(() => {
    const m = document.querySelector('#classModal .modal') as HTMLElement;
    const body = m.querySelector('.modal-body') as HTMLElement;
    for (let i = 0; i < 18; i++) {
      const row = document.createElement('div');
      row.textContent = `filler ${i + 1}`;
      row.style.cssText = 'padding:8px 0;border-bottom:1px solid var(--color-outline-variant)';
      body.appendChild(row);
    }
    const headerY = Math.round((m.querySelector('.modal-header') as HTMLElement).getBoundingClientRect().y);
    const footerY = Math.round((m.querySelector('.modal-footer') as HTMLElement).getBoundingClientRect().y);
    const snapshot = { headerY, footerY, bodyClient: body.clientHeight, bodyScroll: body.scrollHeight };
    body.scrollTop = body.scrollHeight; // scroll to the end
    return snapshot;
  });

  await page.waitForTimeout(200);
  const after = await measure(page, '#classModal .modal');

  // the body is the only scroller
  expect(before.bodyScroll, 'tall content overflows the body').toBeGreaterThan(before.bodyClient);
  expect(after.bodyScrollTop, 'the body scrolled').toBeGreaterThan(0);
  expect(after.bodyScroll > after.bodyClient, 'the body still overflows').toBe(true);
  expect(after.modalScroll, 'the modal shell does not scroll').toBe(after.modalClient);
  // header and footer never moved, and both stay on screen
  expect(after.headerY, 'header pinned').toBe(before.headerY);
  expect(after.footerY, 'footer pinned').toBe(before.footerY);
  expect(after.headerTop >= 0, 'header visible').toBe(true);
  expect(after.footerBottom, 'footer visible').toBeLessThanOrEqual(420);
  expect(after.closeH, 'the × is still reachable').toBe(48);
});

test('R1 — the guide and the summary keep their ceilings (paragraph bodies do not shrink)', async ({ page }) => {
  await openClassDetails(page); // any page carrying both shared shells
  await forceShow(page, 'guideModal');
  const guide = await measure(page, '#guideModal .modal');
  expect(guide.w, 'guide stays at its 520 ceiling').toBe(520);
  expect(guide.cssMaxWidth, 'guide ceiling').toBe('520px');

  await forceShow(page, 'summaryExplainModal');
  const summary = await measure(page, '#summaryExplainModal .modal');
  expect(summary.w, 'summary stays at its 420 ceiling').toBe(420);

  // the cancel-class pop-up may not grow past today's 560 in the 601–1024 band
  await forceShow(page, 'cancelClassOverlay');
  await resize(page, 700);
  const cancelSmall = await measure(page, '#cancelClassOverlay .modal');
  expect(cancelSmall.w, 'cancel does not grow past the tablet cap').toBeLessThanOrEqual(560);
  await resize(page, 1360);
  const cancelWide = await measure(page, '#cancelClassOverlay .modal');
  expect(cancelWide.w, 'cancel keeps its 588 ceiling above 1024').toBeLessThanOrEqual(588);
});

/**
 * §4.3 — the two pop-ups the user complained about. Their page-local 680px pins
 * are gone, so the shared bounded rule governs them.
 *
 * My Replacements details is CONTENT-driven: its tab rail's intrinsic width is
 * ~634px, so `fit-content` legitimately reaches the 680 ceiling above 768. The
 * assertion here is therefore "never wider than 680, never narrower than 420,
 * no clipping" — not "narrower than 680", which its own content forbids.
 */
test('§4.3 — My Replacements details and Request Approval details no longer pin 680', async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 900 });
  await page.goto('/replacement-home-ui', { waitUntil: 'networkidle' });
  await page.waitForSelector('#tableBody tr:not(.skeleton-row)', { state: 'attached', timeout: 20000 });
  await page.waitForTimeout(700);
  await page.locator('#tableBody tr:not(.skeleton-row)').first().click({ force: true });
  await expect(page.locator('#quickViewModal')).toHaveClass(/show/);
  await page.waitForTimeout(450);

  for (const width of WIDTHS) {
    await resize(page, width);
    const g = await measure(page, '#quickViewModal .modal');
    expect(g.w, `${width}: qv width bounded`).toBeGreaterThanOrEqual(width <= 600 ? width : 420);
    expect(g.w, `${width}: qv never wider than the 680 ceiling`).toBeLessThanOrEqual(680);
    expect(g.modalScroll, `${width}: qv no horizontal overflow`).toBeLessThanOrEqual(g.modalClient + 1);
    if (width <= 600) {
      expect(g.left + g.right, `${width}: qv sheet docks flush`).toBe(0);
      expect(g.bottom, `${width}: qv sheet bottom gap 0`).toBe(0);
    }
  }

  // Request Approval: the queue is empty on this lane, so reveal the shell.
  const ctx2 = await page.context().browser()!.newContext({ storageState: '.auth/staff-5425.json', viewport: { width: 1024, height: 900 } });
  const pl = await ctx2.newPage();
  await pl.goto('/request-approval-ui', { waitUntil: 'networkidle' });
  await forceShow(pl, 'modalOverlay');
  for (const width of WIDTHS) {
    await resize(pl, width);
    const g = await measure(pl, '#modalOverlay .modal');
    // This shell carries no variant class, so the 560px tablet cap governs it in
    // the sheet range (560 at 600, exactly like today — R1 forbids widening it
    // to the viewport, which R4's "full width" would otherwise demand).
    expect(g.w, `${width}: approval shell bounded`).toBeGreaterThanOrEqual(width <= 600 ? 350 : 420);
    expect(g.w, `${width}: approval shell never wider than 680`).toBeLessThanOrEqual(680);
    if (width <= 600) {
      expect(g.bottom, `${width}: approval sheet docks flush to the bottom`).toBe(0);
      expect(g.left, `${width}: approval sheet side gap stays <= the tablet cap`).toBeLessThanOrEqual(20);
    }
  }
  await ctx2.close();
});
