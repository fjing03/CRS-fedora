import { expect, type Page } from '@playwright/test';

/**
 * filters-panel-helper — the one door for the specs that drive the moved filter
 * controls (SDD `filters-button`, spec R8; design §1, §6).
 *
 * Since 2026-11-15 each page's `.toolbar` is MOVED into `#filtersPanelBody`, which
 * lives inside `#filtersOverlay` — `hidden` at rest. The ids never changed
 * (`#searchInput`, `#statusFilter`, `#weekFilter`, `#weekFilterRequested` /
 * `#weekFilterOriginal`, `#completedToggle` / `#hideCompleted`), but Playwright's
 * `click` / `fill` / `selectOption` / `hover` all require VISIBILITY, so a spec
 * that drives them must open the panel first. Assertions that only read a node
 * (`$$eval`, `inputValue`, `toBeChecked`, `toHaveValue`, `toContainText`,
 * `toHaveAttribute`) keep working with the panel closed.
 *
 * The panel's own trap/veil means a click on anything still on the page (a chip,
 * a table row, the Filters button itself) must happen with the panel CLOSED —
 * hence `closeFiltersPanel` and the `withFilters` wrapper.
 */

/**
 * Open the Filters panel if the page has one.
 *
 * - A page without the panel (no `#filtersPanel`) is left untouched, so the same
 *   specs can drive a page that never opted into the lane.
 * - Already open → no-op (idempotent, so a test may call it defensively).
 * - Otherwise click `#filtersButton` and wait for `#filtersOverlay` to be visible,
 *   then check the button's `aria-expanded` followed the open state.
 */
export async function openFiltersPanel(page: Page): Promise<void> {
  if ((await page.locator('#filtersPanel').count()) === 0) return;

  const overlay = page.locator('#filtersOverlay');
  if (await overlay.isVisible()) return;

  await page.locator('#filtersButton').click();
  await expect(overlay, 'the Filters panel opens on the button click').toBeVisible();
  await expect(page.locator('#filtersButton')).toHaveAttribute('aria-expanded', 'true');
}

/**
 * Close the Filters panel if it is open (Escape is the lane's own close path, so
 * this also proves that path on every spec that uses it). A page without the
 * panel, or with it already closed, is a no-op.
 */
export async function closeFiltersPanel(page: Page): Promise<void> {
  const overlay = page.locator('#filtersOverlay');
  if ((await overlay.count()) === 0 || !(await overlay.isVisible())) return;

  await page.keyboard.press('Escape');
  await expect(overlay, 'Escape closes the Filters panel').toBeHidden();
  await expect(page.locator('#filtersButton')).toHaveAttribute('aria-expanded', 'false');
}

/**
 * Run `fn` with the panel open and always close it afterwards — the shape to use
 * when the work inside drives a moved control and the test's next step needs the
 * page uncovered. The close runs in `finally`, so a failing assertion inside `fn`
 * still leaves the page in the closed state.
 */
export async function withFilters<T>(page: Page, fn: () => Promise<T>): Promise<T> {
  await openFiltersPanel(page);
  try {
    return await fn();
  } finally {
    await closeFiltersPanel(page);
  }
}
