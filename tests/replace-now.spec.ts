import { test, expect } from '@playwright/test';

/**
 * SDD occurrence-week-pin-truthfulness — the popup's EXACT-PAIR gate +
 * handoff (F4; specs/pin-truthfulness S5 + S7's page-side kinds).
 *
 * The facts (2026-10-19 locked, re-verified live): staff-5770's AMCS2093(L)
 * carries holiday facts at —
 *   W8 Monday (Deepavali, in lieu) and W14 Wed/Thu (Christmas Eve/Day);
 * every other occurrence of the same class carries NONE.
 *
 * The gate: the Replace-Now button EXISTS on a fact cell, ABSENT on a
 * normal cell; the handoff URL pins THE FACT'S OWN week (never the viewed
 * week — the #11 incident's `currentWeek`+1 desync is dead).
 *
 * Never runs TC70 (the crs-workspace guard; this file never touches it).
 */
test.use({ storageState: '.auth/staff-5770.json' });

const MY = '/my-timetable-ui';

test('S5a — the FACT week shows Replace Now and hands off ?week=8', async ({ page }) => {
  await page.goto(MY);
  // weekSelect carries the 0-based week index (cfg.ranges:false) — W8 = '7'
  await page.locator('#weekSelect').selectOption('7');
  await page.waitForSelector('#tableBody .event-block', { timeout: 15_000 });

  // the Monday AMCS2093(L) block (the Deepavali fact rides it)
  const block = page.locator('#tableBody .event-block:has-text("AMCS2093(L)")').first();
  await expect(block).toBeVisible();
  await block.click();
  await expect(page.locator('#classModal')).toBeVisible({ timeout: 10_000 });

  const replaceBtn = page.locator('#btnReplaceNow');
  await expect(replaceBtn).toBeVisible();
  await expect(replaceBtn).toBeEnabled();

  // theovation roll uses the class's OWN id — click is enough
  const [nav] = await Promise.all([
    page.waitForURL(/replacement-arrangement/, { timeout: 15_000 }),
    replaceBtn.click(),
  ]);
  const url = new URL(page.url());
  expect(url.searchParams.get('session')).toBeTruthy();
  expect(url.searchParams.get('week')).toBe('8');          // the FACT's own week
  expect(url.searchParams.get('from')).toBe('my-timetable');
});

test('S5b — the NO-fact week of the SAME class shows NO Replace Now', async ({ page }) => {
  await page.goto(MY);
  await page.locator('#weekSelect').selectOption('9');     // an ordinary week for the owner
  await page.waitForSelector('#tableBody .event-block', { timeout: 15_000 });

  // ANY own class block on W10 — its occurrence carries no fact
  const block = page.locator('#tableBody .event-block').first();
  await expect(block).toBeVisible();
  await block.click();
  await expect(page.locator('#classModal')).toBeVisible({ timeout: 10_000 });

  await expect(page.locator('#btnReplaceNow')).toBeHidden();
});

test('S7 — the W14 fact pair (Wed) hands off ?week=14 (Christmas Eve)', async ({ page }) => {
  await page.goto(MY);
  await page.locator('#weekSelect').selectOption('13');
  await page.waitForSelector('#tableBody .event-block', { timeout: 15_000 });

  // the W14 fact rows = the Wed/Thu AMCS2093(P) cells (Thursday's P lands;
  // the Wednesday's stamp sits on the AMCS2093(P) d3 pair — the S7 assert
  // targets the FACT block explicitly)
  const block = page.locator('#tableBody .event-block:has-text("AMCS2093(P)")').first();
  await expect(block).toBeVisible();
  await block.click();
  await expect(page.locator('#classModal')).toBeVisible({ timeout: 10_000 });

  const replaceBtn = page.locator('#btnReplaceNow');
  await expect(replaceBtn).toBeVisible();
  const [nav2] = await Promise.all([
    page.waitForURL(/replacement-arrangement/, { timeout: 15_000 }),
    replaceBtn.click(),
  ]);
  const url = new URL(page.url());
  expect(url.searchParams.get('week')).toBe('14');
});
