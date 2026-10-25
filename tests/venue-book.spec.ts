import { test, expect, type Page } from '@playwright/test';
import { pickSpanFreeCell, selectWeek } from './helpers/intent-modal-helper';

/**
 * venue-book.spec.ts — U2a end-to-end coverage for the venue-page booking
 * affordance (design D5). One login per run via the setup project's
 * storageState; distinct slots per test to avoid sessionStorage bleed.
 *
 * Fixtures are probed dynamically, never hard-coded against occupancy:
 * free cells are taken from the LAST semester week (far future, outside
 * the 3-working-day lead-time); too-soon probes run on the REAL current
 * week (the mockNow clock bridge makes the page open there).
 */
test.use({ storageState: '.auth/staff-5770.json' });

const PAGE = '/venue-timetable-ui?venue=B110';

async function selectLastWeek(page: Page): Promise<void> {
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  await page.evaluate((idx) => (window as any).selectWeek(idx), last);
  await page.waitForTimeout(300);
}

async function firstFreeCell(page: Page, selector = '.cell-available:not(.cell-no-fit)') {
  return page.locator(selector).first();
}

test('U2a-T1: free cell exposes the "Arrange ?" hover overlay (class + ::after label)', async ({ page }) => {
  await page.goto(PAGE);
  await selectLastWeek(page);
  const cell = await firstFreeCell(page);
  await expect(cell).toHaveClass(/cell-available/);
  const label = await cell.evaluate((el) => getComputedStyle(el, '::after').content);
  expect(label).toContain('Arrange ?');
});

test('U2a-T2: clicking a free cell opens the tooltip with the exact Arrange sentence', async ({ page }) => {
  await page.goto(PAGE);
  await selectLastWeek(page);
  const cell = await firstFreeCell(page);
  const di = await cell.getAttribute('data-day');
  const hi = await cell.getAttribute('data-hour');
  const expected = await page.evaluate(
    ([d, h]) => {
      const day = (window as any).weekData[(window as any).currentWeek].days[Number(d)];
      return 'Arrange a replacement in B110 on ' + day.abbr + ', ' + day.date + ' at ' + hours[Number(h)] + '?';
    },
    [di, hi],
  );
  await cell.click();
  const tooltip = page.locator('#availableTooltip');
  await expect(tooltip).toHaveClass(/show/);
  await expect(page.locator('#tooltipText')).toHaveText(expected);
  await expect(page.locator('#tooltipBookBtn')).toHaveText('Arrange');
});

test('U2a-T3: clicking Arrange opens the session modal, then hands off — arrangement page opens pre-pinned', async ({ page }) => {
  // SDD arrange-intent-session-choice: the modal interposes (designed contract
  // change — session + cohort + week join the handoff); routed through it here.
  // W8 (Deepavali week): AMCS2093 (L) is the conflicted occurrence the modal
  // offers; W14 has no conflicted L → zero-compat would hide [Arrange] by design.
  await page.goto(PAGE);
  await selectWeek(page, 7);
  await pickSpanFreeCell(page);
  await page.locator('#availableTooltip.show #tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  await page.locator('#intentArrangeBtn').click();
  await page.waitForURL(/\/replacement-arrangement\/?\?.*session=/, { timeout: 10_000 });
  expect(page.url()).toMatch(/venue=B110&date=.+&time=.+&from=venue-timetable&code=.+&session=\d+&cohort=.+&week=\d+/);
  // consumer: the pinned session bound (subject auto-picked) + banner-free
  // booked state (pre-selected toast path)
  await expect(page.locator('#subjectSelector')).toHaveValue(/AMCS|BMIT|CS/);
});

test('U2a-T4: real current week — lead-time cells render cell-too-soon, read-only', async ({ page }) => {
  await page.goto(PAGE); // opens on the REAL current week (mockNow bridge)
  const tooSoon = page.locator('.cell-too-soon');
  const count = await tooSoon.count();
  expect(count).toBeGreaterThanOrEqual(1);
  const first = tooSoon.first();
  await expect(first).not.toHaveAttribute('tabindex');
  await first.click();
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
});

test('U2a-T5: B opens the tooltip on a focused free cell; Escape and .grid-scroll hide it', async ({ page }) => {
  await page.goto(PAGE);
  await selectLastWeek(page);
  const cell = await firstFreeCell(page);
  await cell.focus();
  await page.keyboard.press('b');
  await expect(page.locator('#availableTooltip')).toHaveClass(/show/);
  await page.keyboard.press('Escape');
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
  await page.keyboard.press('b');
  await expect(page.locator('#availableTooltip')).toHaveClass(/show/);
  await page.locator('.grid-scroll').evaluate((el) => el.dispatchEvent(new Event('scroll')));
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
});

test('U2a-T6: no-fit cell shows the two-line "Too Late / to Book" label; click gives the shared toast, no navigation', async ({ page }) => {
  await page.goto(PAGE);
  await selectLastWeek(page);
  const noFit = page.locator('.cell-available.cell-no-fit').first();
  await expect(noFit).toHaveCount(1); // last rows always exist → exactly these cells
  const label = await noFit.evaluate((el) => getComputedStyle(el, '::after').content);
  // Two-line label law (user, 2026-10-25): "Too Late" / "to Book". The
  // computed content CSS-serialises the embedded newline — Chromium hands it
  // back as "\a" (lowercase), other engines as "\A" or a literal newline —
  // normalise all forms, strip the CSS string quotes, assert the lines.
  const lines = label
    .replace(/^["']|["']$/g, '')
    .replace(/\\a\s?/gi, '\n')
    .split('\n')
    .map((s) => s.trim())
    .filter(Boolean);
  expect(lines).toEqual(['Too Late', 'to Book']);
  await noFit.click();
  await expect(page.locator('#toastBar .toast-message')).toHaveText(/120 minutes/);
  await expect(page.locator('#availableTooltip')).not.toHaveClass(/show/);
  expect(page.url()).toContain('/venue-timetable-ui');
});
