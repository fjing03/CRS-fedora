import { test, expect, type Browser, type Page } from '@playwright/test';
import { isSanctionedTestingTarget, GUARD_INVOCATION } from './helpers/receipt-target-guard';

/**
 * filters-panel-first-column — the focused receipt for P3-a of the
 * `filters-panel-and-popup-footer` lane (docs/ui-drift-2026-11-17.md §9).
 *
 * Defect: the open filters card sat on the grid's first column — which is the column the
 * filters narrow — so a lecturer could not see the rows answer. Narrowing the card could
 * never help (it shares the column's left edge). Law now: at ≥769 the card starts past the
 * grid's first LABELLED column (a leading checkbox cell does not count); top, width and
 * both viewport clamps are unchanged; the grid never shifts and gains no scroll. ≤768
 * keeps the docked bottom sheet.
 *
 * Read-only: opens the panel, resizes, measures. No fixture, no DB write, and it refuses
 * to run off the testing lane (fail-closed, same guard as its neighbours).
 */

const PANEL_PAGES = [
  { path: '/my-replacements', state: '.auth/staff-5425.json', name: 'My Replacements' },
  { path: '/request-approval-ui', state: '.auth/staff-5425.json', name: 'Request Approval' },
] as const;

const WIDTHS = [1440, 1024, 769] as const;

test.beforeAll(() => {
  if (!isSanctionedTestingTarget()) {
    throw new Error(
      `⛔ filters-panel-and-popup-footer REFUSED: this spec only runs on the disposable testing lane.\n${GUARD_INVOCATION}`,
    );
  }
});

interface PanelGeom {
  vw: number;
  buttonBottom: number;
  panel: { x: number; y: number; w: number; h: number };
  firstColumn: { label: string; x: number; right: number } | null;
  overlapPx: number;
  gridX: number;
  scrollOverflow: number;
  overlayBg: string;
}

async function openPanel(page: Page): Promise<PanelGeom> {
  await page.locator('#filtersButton').click();
  await expect(page.locator('#filtersPanel')).toBeVisible();
  return page.evaluate(() => {
    const R = (el: Element) => {
      const r = el.getBoundingClientRect();
      return { x: Math.round(r.x), y: Math.round(r.y), w: Math.round(r.width), h: Math.round(r.height), right: Math.round(r.right) };
    };
    const panelEl = document.getElementById('filtersPanel')!;
    const pr = panelEl.getBoundingClientRect();
    const btn = document.getElementById('filtersButton')!.getBoundingClientRect();
    const th = [...document.querySelectorAll('table thead th')].find(
      (el) => (el as HTMLElement).offsetParent !== null && (el.textContent || '').trim() !== '',
    );
    const tr = th ? th.getBoundingClientRect() : null;
    const grid = (document.querySelector('.grid-wrapper') || document.querySelector('.grid-scroll')) as HTMLElement | null;
    const sc = document.querySelector('.grid-scroll') as HTMLElement | null;
    return {
      vw: window.innerWidth,
      buttonBottom: Math.round(btn.bottom),
      panel: R(panelEl),
      firstColumn: tr ? { label: (th!.textContent || '').trim().slice(0, 24), x: Math.round(tr.x), right: Math.round(tr.right) } : null,
      overlapPx: tr ? Math.max(0, Math.round(Math.min(pr.right, tr.right) - Math.max(pr.left, tr.x))) : 0,
      gridX: grid ? Math.round(grid.getBoundingClientRect().x) : 0,
      scrollOverflow: sc ? sc.scrollWidth - sc.clientWidth : 0,
      overlayBg: getComputedStyle(document.getElementById('filtersOverlay')!).backgroundColor,
    };
  });
}

async function freshPage(browser: Browser, state: string, width: number, height = 900) {
  const context = await browser.newContext({ storageState: state, viewport: { width, height } });
  const page = await context.newPage();
  return { context, page };
}

for (const spec of PANEL_PAGES) {
  test(`${spec.name}: the card clears the first labelled column or uses its fallback (≥769)`, async ({ browser }) => {
    for (const width of WIDTHS) {
      const { context, page } = await freshPage(browser, spec.state, width);
      try {
        const gridBefore = await page.goto(spec.path, { waitUntil: 'networkidle' }).then(() =>
          page.evaluate(() => {
            const sc = document.querySelector('.grid-scroll') as HTMLElement | null;
            const grid = (document.querySelector('.grid-wrapper') || sc) as HTMLElement | null;
            return { x: grid ? Math.round(grid.getBoundingClientRect().x) : 0, overflow: sc ? sc.scrollWidth - sc.clientWidth : 0 };
          }),
        );
        const g = await openPanel(page);
        if (spec.name === 'My Replacements') expect(g.firstColumn?.label).toBe('Course');
        const w = Math.min(460, width - 16);
        const clampRight = width - w - 8;

        expect(Math.abs(g.panel.y - (g.buttonBottom + 8)), `${width}: top = button bottom + 8`).toBeLessThanOrEqual(2);
        expect(g.panel.w, `${width}: the card is still capped at 460px`).toBeLessThanOrEqual(460);
        expect(g.panel.x, `${width}: left never leaves the gutter`).toBeGreaterThanOrEqual(8 - 2);
        expect(g.panel.x + g.panel.w, `${width}: right never leaves the gutter`).toBeLessThanOrEqual(width - 8 + 2);
        expect(g.overlayBg, `${width}: the desktop veil is still the 0.32 literal`).toBe('rgba(0, 0, 0, 0.32)');

        if (g.firstColumn) {
          const expected = Math.min(Math.max(g.firstColumn.right + 12, 8), clampRight);
          expect(Math.abs(g.panel.x - expected), `${width}: left starts past the first labelled column`).toBeLessThanOrEqual(2);
          if (g.firstColumn.right <= clampRight) {
            expect(g.overlapPx, `${width}: "${g.firstColumn.label}" is not covered`).toBe(0);
          }
        } else {
          const button = (await page.locator('#filtersButton').boundingBox())!;
          expect(Math.abs(g.panel.x - Math.min(Math.max(button.x, 8), clampRight))).toBeLessThanOrEqual(2);
        }

        /* the panel floats: the grid does not move and gains no scroll because of it */
        expect(g.gridX, `${width}: the grid did not shift`).toBe(gridBefore.x);
        expect(g.scrollOverflow, `${width}: opening the panel added no horizontal scroll`).toBe(gridBefore.overflow);
      } finally {
        await context.close();
      }
    }
  });
}

test('desktop course search updates uncovered rows while the panel stays open', async ({ browser }) => {
  const { context, page } = await freshPage(browser, PANEL_PAGES[0].state, 1440);
  try {
    await page.goto(PANEL_PAGES[0].path, { waitUntil: 'networkidle' });
    const rows = page.locator('#tableBody tr[data-id]');
    const firstCells = rows.locator('td:first-child');
    const initial = await firstCells.allTextContents();
    const code = initial[0]?.match(/\b[A-Z]{3,5}\d{4}\b/)?.[0];
    expect(code).toBeTruthy();
    expect(initial.some(cell => !cell.includes(code!))).toBe(true);
    await openPanel(page);
    await page.locator('#searchInput').fill(code!);
    await expect.poll(async () => {
      const filtered = await firstCells.allTextContents();
      return filtered.length > 0 && filtered.length < initial.length && filtered.every(cell => cell.includes(code!));
    }).toBe(true);
    const panel = (await page.locator('#filtersPanel').boundingBox())!;
    const cell = (await firstCells.first().boundingBox())!;
    expect(panel.x).toBeGreaterThanOrEqual(cell.x + cell.width);
  } finally {
    await context.close();
  }
});

test('the phone (≤768) still gets the docked sheet, not the card', async ({ browser }) => {
  const { context, page } = await freshPage(browser, PANEL_PAGES[0].state, 768);
  try {
    await page.goto(PANEL_PAGES[0].path, { waitUntil: 'networkidle' });
    const g = await openPanel(page);
    expect(g.panel.x, 'the sheet starts at the left edge').toBeLessThanOrEqual(2);
    expect(Math.abs(g.panel.w - 768), 'the sheet spans the viewport').toBeLessThanOrEqual(2);
    expect(Math.abs(g.panel.y + g.panel.h - 900), 'the sheet is docked to the bottom').toBeLessThanOrEqual(2);
    expect(g.overlayBg, 'the phone veil is the 0.45 literal').toBe('rgba(0, 0, 0, 0.45)');
  } finally {
    await context.close();
  }
});
