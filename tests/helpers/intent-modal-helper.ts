import { type Page } from '@playwright/test';

/**
 * Shared helper (SDD arrange-intent-session-choice — DRY: used by
 * venue-intent-modal + venue-book specs). Selects the first venue cell whose
 * full 2h (4-slot) span is free of event blocks, so the modal has at least one
 * span-fitting session to offer regardless of venue crowding.
 */
export async function pickSpanFreeCell(page: Page): Promise<void> {
  const target = await page.evaluate(() => {
    const occ: Record<string, Set<number>> = {};
    document.querySelectorAll('#tableBody .event-block').forEach((b) => {
      const td = b.closest('td');
      if (!td) return;
      const di = parseInt(td.dataset.day || '', 10);
      const start = parseInt(td.dataset.hour || '', 10);
      const span = parseInt((b.className.match(/span-(\d+)/) || [])[1] || '1', 10);
      for (let k = 0; k < span; k++) {
        (occ[di] = occ[di] || new Set()).add(start + k);
      }
    });
    const cells = Array.from(document.querySelectorAll('#tableBody .cell-available:not(.cell-no-fit)'));
    for (const c of cells as HTMLElement[]) {
      const di = parseInt(c.dataset.day || '', 10);
      const hi = parseInt(c.dataset.hour || '', 10);
      if (Number.isNaN(di) || Number.isNaN(hi)) continue;
      if (hi + 4 > document.querySelectorAll('#tableHead th.hour-header').length) continue;
      let free = true;
      for (let k2 = 0; k2 < 4; k2++) if ((occ[di] && occ[di].has(hi + k2))) free = false;
      if (free) return { di, hi };
    }
    return null;
  });
  if (!target) throw new Error('no span-free cell');
  await page.locator(`#tableBody td[data-day="${target.di}"][data-hour="${target.hi}"] .cell-available:not(.cell-no-fit)`).click();
}

/** Jump the venue page to a week index (client-side nav). */
export async function selectWeek(page: Page, weekIdx: number): Promise<void> {
  await page.evaluate((idx) => {
    (window as any).__specWeek = idx;
    (window as any).selectWeek(idx);
  }, weekIdx);
  await page.waitForTimeout(300);
}
