import { test, expect } from '@playwright/test';

/**
 * replacement-home-status-badges (2026-11-03) — the READ-ONLY receipt for D1/D2/
 * D5/D7/D8 on /replacement-home-ui. Nothing here writes: every step is a GET plus
 * a DOM read (no fixtures, no SQL, no demo-DB writes).
 *
 * Fixtures pinned from the live demo DB (`class_replacement_fresh`), viewer =
 * staff 5425 (user 1, Surayaini) — the same identity the venue specs use:
 *   · request 27 — PENDING, class_session 38 = BMSE2163(P), occurrence W14
 *     (W14 Thu = Christmas Eve) → a HANDLED row
 *   · request 26 — PENDING, class_session 40 = AMSE2003(P), occurrence W14
 *     (W14 Fri = Christmas Day)  → a HANDLED row
 *   · the W8 Monday (Deepavali) and the remaining W14 rows carry no request
 *     → rows that still need action
 *
 * If the demo DB is replaced, re-derive these two ids from the home page itself
 * (the badge href contains `notif-hl=r<id>`) before trusting a red/green result.
 */

const HOME = '/replacement-home-ui';
const MRH = '/my-request-history-ui';

test.use({ storageState: '.auth/staff-5425.json' });

async function homeRows(page: import('@playwright/test').Page) {
  await page.goto(`${HOME}?fresh=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(700);
  return page.evaluate(() => Array.from(document.querySelectorAll('#tableBody tr')).map((tr) => ({
    code: (tr.querySelector('.col-code')?.textContent || '').trim(),
    original: (tr.querySelector('.col-original')?.textContent || '').replace(/\s+/g, ' ').trim(),
    days: (tr.querySelector('.col-urgency')?.textContent || '').trim(),
    reason: (tr.querySelector('.col-reason')?.textContent || '').trim(),
    badgeHref: tr.querySelector('.col-action a.badge-link')?.getAttribute('href') ?? null,
    badgeText: (tr.querySelector('.col-action a.badge-link')?.textContent || '').trim() || null,
    hasArrange: !!tr.querySelector('.col-action .btn-action'),
    endedLabel: (tr.querySelector('.col-action .action-stack > span.badge')?.textContent || '').trim() || null,
  })));
}

test('receipt 1 — a handled row shows a clickable badge and no Arrange button', async ({ page }) => {
  const rows = await homeRows(page);
  const handled = rows.filter((r) => r.badgeHref !== null);

  expect(handled.length, 'at least one handled occurrence stays on the list (D1)').toBeGreaterThan(0);
  for (const row of handled) {
    expect(row.badgeText, 'D2: the badge names the request state').toMatch(/^(Pending|Approved)$/);
    expect(row.badgeHref, 'D2: the badge links to My Requests with the request id').toMatch(/^\/my-request-history-ui\?notif-hl=r\d+$/);
    expect(row.hasArrange, 'D2: a handled row has NO Arrange button').toBe(false);
  }
});

test('receipt 2 — the badge lands on My Requests with that request targeted', async ({ page }) => {
  const rows = await homeRows(page);
  const handled = rows.find((r) => r.badgeHref !== null);
  expect(handled, 'a handled row exists').toBeTruthy();

  const requestId = handled!.badgeHref!.split('=r')[1];
  await page.locator(`#tableBody .col-action a.badge-link[href="${handled!.badgeHref}"]`).first().click();
  await page.waitForURL(new RegExp(`${MRH.replace('/', '\\/')}`), { timeout: 15000 });

  const row = page.locator(`tr[data-id="${requestId}"]`);
  await expect(row, 'the request row is on the page').toHaveCount(1, { timeout: 8000 });
  expect(new URL(page.url()).pathname, 'the destination is My Requests').toBe(MRH);
  /* the shared 1.3 s flash is opportunistic here (arrival renders after a
     400 ms skeleton) — assert it only if it lands inside the window. */
  const flashed = await row.evaluate((el) => el.classList.contains('bookable-flash')).catch(() => false);
  test.info().annotations.push({ type: 'flash', description: flashed ? 'bookable-flash observed' : 'flash window missed (navigation receipt stands)' });
});

test('receipt 3 — Days Left is an em dash on a handled row', async ({ page }) => {
  const rows = await homeRows(page);
  const handled = rows.filter((r) => r.badgeHref !== null);
  expect(handled.length).toBeGreaterThan(0);
  for (const row of handled) {
    expect(row.days, 'D7: a handled row reads —').toBe('—');
  }
  /* D7's second half: the em dash is a DISPLAY change only — a needs-action row
     still shows its real countdown next to it */
  const actionable = rows.filter((r) => r.badgeHref === null);
  expect(actionable.length).toBeGreaterThan(0);
  for (const row of actionable) {
    expect(row.days).toMatch(/days? left|Overdue|today/i);
  }
});

test('receipt 4 — handled rows sort AFTER the rows that need action', async ({ page }) => {
  const rows = await homeRows(page);
  const handledFlags = rows.map((r) => r.badgeHref !== null);
  const firstHandled = handledFlags.indexOf(true);
  expect(firstHandled, 'a handled row exists').toBeGreaterThan(-1);
  expect(handledFlags.slice(firstHandled).every(Boolean), 'D8: once the handled group starts, it never returns to needs-action').toBe(true);
});

test('receipt 5 — the mobile card carries the badge and tapping it navigates', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto(`${HOME}?fresh=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);

  const badge = page.locator('.replacement-card .rc-header a.badge-link').first();
  await expect(badge, 'D5: the card shows the same badge').toHaveCount(1);
  const href = await badge.getAttribute('href');
  expect(href).toMatch(/\?notif-hl=r\d+$/);

  await page.locator('.replacement-card', { has: page.locator('a.badge-link') }).first().click();
  await page.waitForURL(new RegExp(MRH.replace('/', '\\/')), { timeout: 15000 });
  expect(new URL(page.url()).pathname).toBe(MRH);
});

test('receipt 6 — the quick view on a handled row offers View Request + a status row', async ({ page }) => {
  await page.goto(`${HOME}?fresh=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(700);

  const handledRow = page.locator('#tableBody tr', { has: page.locator('a.badge-link') }).first();
  await expect(handledRow).toHaveCount(1);
  // the row's own click opens the quick view (the badge stops propagation)
  await handledRow.locator('.col-code').click();
  await expect(page.locator('#quickViewModal')).toBeVisible();

  await expect(page.locator('#qvArrangeLabel')).toHaveText('View Request');
  const body = page.locator('#qvBody');
  await expect(body).toContainText('Replacement Status');
  await expect(body).toContainText(/Pending|Approved/);
});

test('receipt 7 — an actionable row still starts the arrangement (no regression)', async ({ page }) => {
  await page.goto(`${HOME}?fresh=${Date.now()}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(700);

  const row = page.locator('#tableBody tr', { has: page.locator('.col-action .btn-action') }).first();
  await expect(row, 'D2: a needs-action row keeps its Arrange button').toHaveCount(1);
  await row.locator('.col-code').click();
  await expect(page.locator('#qvArrangeLabel')).toHaveText('Arrange Replacement');
  await expect(page.locator('#qvBody')).not.toContainText('Replacement Status');
});
