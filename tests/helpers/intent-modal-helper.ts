import { type Page } from '@playwright/test';

/**
 * Shared helper (SDD arrange-intent-session-choice — DRY: used by
 * venue-intent-modal + venue-book specs). Selects the first venue cell whose
 * full 2h (4-slot) span is REALLY free, so the modal has at least one
 * span-fitting session to offer regardless of venue crowding. "Really free"
 * is the page's own spanBlocked contract (cancelled-slot-reuse S20/A21):
 * cancelled history is not occupancy, and every span cell must be a served
 * FREE room-matrix offer — a held row or a missing offer ends the span even
 * with no event over it.
 */
export async function pickSpanFreeCell(page: Page): Promise<void> {
  const target = await page.evaluate(() => {
    /* cancelled-slot-reuse S20: CANCELLED history is NOT occupancy — the
       page's own spanBlocked ignores it, so this finder must too. Read the
       status off the block's own event data (never a class name: the red
       conflict family is shared by live and cancelled alike). */
    const occ: Record<string, Set<number>> = {};
    document.querySelectorAll('#tableBody .event-block').forEach((b) => {
      const ev = (b as any).__eventData;
      if (ev && ev.status === 'cancelled') return;
      const td = (b as HTMLElement).closest('td');
      if (!td) return;
      const di = parseInt(td.dataset.day || '', 10);
      const start = parseInt(td.dataset.hour || '', 10);
      const span = parseInt(((b as HTMLElement).className.match(/span-(\d+)/) || [])[1] || '1', 10);
      for (let k = 0; k < span; k++) {
        (occ[di] = occ[di] || new Set()).add(start + k);
      }
    });
    /* cancelled-slot-reuse A21: the served room matrix is the REAL room truth
       — a span is only really free when every one of its cells is a served
       FREE offer (a blocked row or a missing offer row ends the run, even
       with no event over it). Bound by the shared `hours` axis, not by a
       header count. */
    const matrix = ((window as any).roomCellStatesByWeek || [])[(window as any).currentWeek] || {};
    const cells = Array.from(document.querySelectorAll('#tableBody .cell-available:not(.cell-no-fit):not(.cell-no-room)'));
    for (const c of cells as HTMLElement[]) {
      const di = parseInt(c.dataset.day || '', 10);
      const hi = parseInt(c.dataset.hour || '', 10);
      if (Number.isNaN(di) || Number.isNaN(hi)) continue;
      if (hi + 4 > hours.length) continue;
      let free = true;
      for (let k2 = 0; k2 < 4; k2++) {
        if ((occ[di] && occ[di].has(hi + k2))) free = false;
        if (matrix[di + ':' + (hi + k2)] !== 'free') free = false;
      }
      if (free) return { di, hi };
    }
    return null;
  });
  if (!target) throw new Error('no span-free cell');
  await page.locator(`#tableBody td[data-day="${target.di}"][data-hour="${target.hi}"] .cell-available:not(.cell-no-fit):not(.cell-no-room)`).click();
}

/** Jump the venue page to a week index (client-side nav). */
export async function selectWeek(page: Page, weekIdx: number): Promise<void> {
  await page.evaluate((idx) => {
    (window as any).__specWeek = idx;
    (window as any).selectWeek(idx);
  }, weekIdx);
  await page.waitForTimeout(300);
}
