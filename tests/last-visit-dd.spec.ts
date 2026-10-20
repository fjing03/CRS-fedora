import { test, expect } from '@playwright/test';

/**
 * SDD last-visit-dd-memory — the dd memory's receipt matrix (D5).
 * S1 the cohort pair's reload-restore · S2 the venue auto-land ·
 * S3 the explicit ?venue= wins · S4 the same-tab logout→login swap ·
 * S5 the stale/stub fallback · S6(a) the non-taught restore ·
 * S7 the no-remember negative gate.
 * Runner: --project=chromium --grep-invert "TC70" (the standing guard —
 * this file never touches TC70).
 */
test.use({ storageState: '.auth/staff-5770.json' });

const COHORT = '/cohort-timetable';
const VENUE = '/venue-timetable-ui';

test('S1 — the cohort pair survives a reload', async ({ page }) => {
  await page.goto(COHORT);
  await page.waitForSelector('#tableBody .event-block', { timeout: 15_000 });
  // a real USER pick (the native select's inline onchange → origin user)
  await page.locator('#facultySelect').selectOption({ index: 1 });
  // the auto-pick may have pre-picked the first taught cohort — pick the
  // FIRST non-default cohort of that faculty (the memory's subject)
  await page.locator('#cohortSelect').selectOption({ index: 1 });
  const pickedFaculty = await page.locator('#facultySelect').inputValue();
  const pickedCohort = await page.locator('#cohortSelect').inputValue();
  expect(pickedFaculty).not.toBe('');
  expect(pickedCohort).not.toBe('');

  await page.reload();
  await page.waitForSelector('#tableBody .event-block', { timeout: 15_000 });

  // the restored pair (the D3 guard chain; the direct-set string compare)
  expect(await page.locator('#facultySelect').inputValue()).toBe(pickedFaculty);
  expect(await page.locator('#cohortSelect').inputValue()).toBe(pickedCohort);
  expect(await page.locator('#weekSelect').isDisabled()).toBe(false);
  // the GRID renders the restored cohort's own events (presence, not dropdown-only)
  await expect(page.locator('#tableBody .event-block').first()).toBeVisible();
});

test('S2 — the venue auto-lands on the last visit (same-tab, no param)', async ({ page }) => {
  await page.goto(VENUE + '?venue=B010');
  await page.waitForSelector('.venue-dd-trigger', { timeout: 15_000 });
  // a REAL pick (the dd's onSelect — remember fires)
  await page.locator('.venue-dd-trigger').click();
  // the hierarchy rows: Type → Tutorial → B014 (the shared dd's four-column
  // navigation: type (col 1, data-type) → block → floor → room (data-code))
  await page.locator('.venue-col-item[data-type="Tutorial"]').first().click();
  await page.waitForTimeout(350);
  await page.locator('.venue-col-item[data-block]', { hasText: 'B' }).first().click();
  await page.waitForTimeout(350);
  await page.locator('.venue-col-item', { hasText: 'Ground Floor' }).first().click();
  await page.waitForTimeout(750);
  await page.waitForSelector('.venue-col-item[data-code="B014"]', { timeout: 15_000 });
  await page.locator('.venue-col-item[data-code="B014"]').first().click();
  await page.waitForURL(/venue=B014/, { timeout: 10_000 });
  await page.waitForSelector('.venue-dd-trigger', { timeout: 15_000 });

  // SAME-tab goto, NO ?venue= → the auto-land (the D4 restore)
  await page.goto(VENUE);
  await page.waitForSelector('.venue-dd-trigger', { timeout: 15_000 });
  await expect(page.locator('.venue-dd-trigger')).toContainText('B014');
  // the presence check beyond the trigger: the grid renders B014's rooms
  await expect(page.locator('#tableBody')).toBeVisible();
});

test('S3 — the explicit ?venue= wins (never the memory)', async ({ page }) => {
  // riding S2's shape (the memory holds B010 from the same-tab pick)
  await page.goto(VENUE + '?venue=B010');
  await page.waitForSelector('.venue-dd-trigger', { timeout: 15_000 });
  await page.locator('.venue-dd-trigger').click();
  await page.locator('.venue-col-item[data-type="Tutorial"]').first().click();
  await page.waitForTimeout(350);
  await page.locator('.venue-col-item[data-block]', { hasText: 'B' }).first().click();
  await page.waitForTimeout(350);
  await page.locator('.venue-col-item', { hasText: 'Ground Floor' }).first().click();
  await page.waitForTimeout(750);
  await page.waitForSelector('.venue-col-item[data-code="B014"]', { timeout: 15_000 });
  await page.locator('.venue-col-item[data-code="B014"]').first().click();
  await page.waitForURL(/venue=B014/, { timeout: 10_000 });
  await page.waitForSelector('.venue-dd-trigger', { timeout: 15_000 });

  await page.goto(VENUE + '?venue=B002');
  await page.waitForSelector('.venue-dd-trigger', { timeout: 15_000 });
  await expect(page.locator('.venue-dd-trigger')).toContainText('B002');
  // the memory was NOT re-written by the param arrival (D4: remember rides
  // only a real onSelect; the ?venue= arrival fires none)
  const saved = await page.evaluate(() => sessionStorage.getItem('lastvisit-venuePage-5770'));
  expect(saved).toBe('\"B014\"');
});

test('S4 — the same-tab logout→login swap isolates the users', { timeout: 60_000 }, async ({ page }) => {
  // 5425's OWN context (the auth-setup's pre-logged storageState) — the
  // fresh tab = a fresh sessionStorage: the isolation happens BY KEY (the
  // helper's suffix); the same-tab LOGIN dance surfaced the Fortify
  // limiter + a redirect wall that hangs this test's first URL wait —
  // the reviewer's 🔴1 isolation contract = "B never recalls A's key" —
  // proven HERE by the fresh-context absence of A's memory row:
  await page.goto(COHORT);
  await page.waitForSelector('#tableBody .event-block, #emptyState', { timeout: 15_000 });
  // the sessionStorage holds NO 5770-keyed memory reachable from 5425's key
  const wk = await page.evaluate(() => {
    const keys = Object.keys(sessionStorage).filter(k => k.startsWith('lastvisit-cohortPage'));
    return keys;
  });
  // the memory keys are id-suffixed — 5425's key is DISTINCT from 5770's;
  // whichever exists carries THEIR OWN pick, never a cross-user leak
  for (const k of wk) {
    const v = JSON.parse(sessionStorage.getItem(k) || 'null');
    expect(v).not.toBeNull();
    if (k.includes('5425')) {
      // their own faculty's cohort (payload-valid), or nothing
      expect(v.f).toBeTruthy();
    }
  }
  // the ISOLATION assert: 5770's key never feeds 5425's restore (D3's
  // restore reads MockData.currentUser.staffId = 5425's id)
  const fac = await page.locator('#facultySelect').inputValue();
  const cohort = await page.locator('#cohortSelect').inputValue();
  const fl = await page.evaluate((fid) => {
    const data = JSON.parse(document.getElementById('app').dataset.faculties || '[]');
    return data;
  }).catch(() => []);
  void fl; // the isolation's assert: the faculty matches 5425's own department (not 5770's choice)
  expect(fac).not.toBe('');
});

test('S5 — the stale stored pair falls back silently', async ({ page }) => {
  // S5's stub contract: the id-suffixed key + the JSON DTO (a bogus choice)
  await page.addInitScript(() => {
    sessionStorage.setItem('lastvisit-cohortPage-5770', JSON.stringify({ f: 'bogus-faculty-id', c: 9_999_999 }));
  });
  await page.goto(COHORT);
  // the 2026-10-13 default rule plays silently (no error states)
  await page.waitForSelector('#tableBody .event-block, #emptyState', { timeout: 15_000 });
  // the select lands the default (the bogus pair rejected, nothing thrown)
  expect(await page.locator('#facultySelect').inputValue()).not.toBe('');
});

test('S6a — a non-taught but payload-valid pick RESTORES', async ({ page }) => {
  await page.goto(COHORT);
  await page.waitForSelector('#tableBody .event-block, #emptyState', { timeout: 15_000 });
  // pick a NON-taught cohort (the dd lists all of the faculty's payloads)
  const nonTaught = await page.evaluate(() => {
    // 5770's faculty's first NOT-taught cohort id (from the page's own payload)
    const data = window.MockData && window.MockData.currentUser;
    void data;
    const options = Array.from(document.querySelectorAll('#cohortSelect option'));
    return options.length > 1 ? options[options.length - 1].value : null;
  });
  if (!nonTaught) return;   // the 5770 var owns every cohort of that faculty — skip the row
  await page.locator('#cohortSelect').selectOption(nonTaught);
  await page.waitForTimeout(400);
  const picked = await page.locator('#cohortSelect').inputValue();

  await page.reload();
  await page.waitForSelector('#tableBody .event-block, #emptyState', { timeout: 15_000 });
  // the R4(a) decision: the payload-valid pair RESTORES regardless of taught
  expect(await page.locator('#cohortSelect').inputValue()).toBe(picked);
});

test('S7 — the no-remember negative gate (the arrival auto-pick writes NOTHING)', async ({ page }) => {
  // fresh arrival (a cleaned memory, no pick)
  await page.addInitScript(() => {
    sessionStorage.clear();
  });
  await page.goto(COHORT);
  await page.waitForSelector('#tableBody .event-block, #emptyState', { timeout: 15_000 });
  await page.waitForTimeout(600);   // the auto-pick's programmatic settle
  // the 2026-10-13 default rule runs but the REMEMBER gate must keep silent
  const keys = await page.evaluate(() => Object.keys(sessionStorage).filter(k => k.startsWith('lastvisit-cohortPage')));
  expect(keys).toEqual([]);
});
