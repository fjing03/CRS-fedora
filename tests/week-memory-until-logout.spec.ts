import { test, expect } from '@playwright/test';

/**
 * SDD week-memory-until-logout (user law 2026-10-20): the week memory is
 * SESSION long, per page, from login until logout.
 *  - Fresh visit of a page in the login session = TODAY (the page's own
 *    today family — the server's currentTimelineWeekIndex() for my/cohort/
 *    student, the client's currentWeekIndex() for the venue).
 *  - prev/next/select/Today-click saves that page's version (session-
 *    scoped sessionStorage key: weekmem-<key>@<login_week_stamp>).
 *  - Revisiting a page in-session auto-navs to its own saved version.
 *  - Logout (or expiry) → a new login stamp → everything lands on TODAY.
 * Row hygiene: the marker-guarded once-per-tab add-init clears the week
 * memories once per context (never mid-trip); my-timetable rows always
 * read the weekSelector (the documented deliberate window.desync).
 * The DANCE (the student-page live row) = RUNS LAST in this file by
 * design: its fresh staff login + the real logout + the student login
 * use a SEPARATE bare context with its OWN sessions (4363 → 25RSD0001),
 * so the shared storageState's 5770 session is never invalidated.
 */
const PW = 'Tarumt@2026';   // the demo convention (auth.setup.ts carries the same constant)

test.use({ storageState: '.auth/staff-5770.json' });   // M1-M4 use the shared 5770 state; M5 (the dance) uses a bare context + its OWN logins (4363 -> 25RSD0001)

// the marker-guarded once-per-context memory clear (safe for multi-goto rows)
async function enforceMemorylessOnce(page) {
  await page.addInitScript(() => {
    if (sessionStorage.getItem('__mmcleared') === '1') return;
    sessionStorage.setItem('__mmcleared', '1');
    Object.keys(sessionStorage).filter(k => k.startsWith('weekmem-')).forEach(k => sessionStorage.removeItem(k));
    ['weekNav-weekSelect', 'venueTimetableWeek', 'cohortTimetableWeek'].forEach(k => localStorage.removeItem(k));
    localStorage.removeItem('currentWeek');
  });
}
async function weekHopNext(page) { await page.evaluate(() => (window as any).nextWeek()); }
async function weekHopPrev(page) { await page.evaluate(() => (window as any).prevWeek()); }

test('M1 — my-timetable: fresh visit = TODAY; selector saves the version; today-click saves today', async ({ page }) => {
  await enforceMemorylessOnce(page);
  await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  const served = await page.evaluate(() => (window as any).currentWeek);
  const sel = await page.locator('#weekSelect').inputValue();
  expect(parseInt(sel, 10)).toBe(served);
  weekHopNext(page);
  await page.waitForTimeout(600);
  const keys = await page.evaluate(() =>
    Object.keys(sessionStorage).filter(k => k.startsWith('weekmem-')));
  expect(keys.length).toBe(1);
  await page.locator('#todayBtn').click();
  await page.waitForTimeout(600);
  const savedNow = await page.evaluate(() => {
    const k = Object.keys(sessionStorage).find(k => k.startsWith('weekmem-'));
    return k ? sessionStorage.getItem(k) : null;
  });
  expect(parseInt(String(savedNow), 10)).toBe(served);
});

test('M2 — the two-page trip: page B starts at TODAY; page A resumes its own version', async ({ page }) => {
  await enforceMemorylessOnce(page);
  await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  weekHopNext(page); await page.waitForTimeout(500);
  weekHopNext(page); await page.waitForTimeout(500);
  const aSaved = await page.evaluate(() =>
    parseInt(String(sessionStorage.getItem(Object.keys(sessionStorage).find(k => k.startsWith('weekmem-')) ?? '')), 10));
  await page.goto('/venue-timetable-ui?venue=B110', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  const venueToday = await page.evaluate(() => (window as any).currentWeekIndex());
  const selV = await page.locator('#weekSelect').inputValue();
  expect(parseInt(selV, 10)).toBe(venueToday);
  await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  const selA = await page.locator('#weekSelect').inputValue();
  expect(parseInt(selA, 10)).toBe(aSaved);
});

test('M3 — the stamp rebind: a stale-stamp week key matches nothing (fresh login = today)', async ({ page }) => {
  await page.addInitScript(() => {
    sessionStorage.setItem('weekmem-weekNav-weekSelect@wkSTALE', '9');
  });
  await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  const served = await page.evaluate(() => (window as any).currentWeek);
  const sel = await page.locator('#weekSelect').inputValue();
  expect(parseInt(sel, 10)).toBe(served);
});

test('M4 — the venue + the cohort: today on fresh; the venue resumes', async ({ page }) => {
  await enforceMemorylessOnce(page);
  await page.goto('/venue-timetable-ui?venue=B110', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  const vToday = await page.evaluate(() => (window as any).currentWeekIndex());
  const selV = await page.locator('#weekSelect').inputValue();
  expect(parseInt(selV, 10)).toBe(vToday);
  weekHopNext(page); await page.waitForTimeout(600);
  // cohort = the server's today family (pin the faculty+cohort pair first)
  await page.goto('/cohort-timetable', { waitUntil: 'networkidle' });
  const faculty = page.locator('#facultySelect, .faculty-select').first();
  if (await faculty.isVisible().catch(() => false)) await faculty.selectOption({ index: 1 });
  const cohort = page.locator('#cohortSelect, .cohort-select').first();
  if (await cohort.isVisible().catch(() => false)) await cohort.selectOption({ index: 1 });
  await page.waitForTimeout(600);
  const served = await page.evaluate(() => (window as any).currentWeek);
  const selC = await page.locator('#weekSelect').inputValue();
  expect(parseInt(selC, 10)).toBe(served);
  weekHopNext(page); await page.waitForTimeout(600);
  // the venue revisit = its own version resumes (the per-page family)
  await page.goto('/venue-timetable-ui?venue=B110', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  const selV2 = await page.locator('#weekSelect').inputValue();
  expect(parseInt(selV2, 10)).toBe(vToday + 1);
  // the cohort revisit = the PAGE's OWN deliberate contract: a cohort
  // switch re-lands TODAY (the onCohortChange tail's re-anchor quirk —
  // the last-visit-dd D3 family kept it) — the revisit receipt = today
  await page.goto('/cohort-timetable', { waitUntil: 'networkidle' });
  if (await (page.locator('#facultySelect, .faculty-select').first()).isVisible().catch(() => false)) {
    await (page.locator('#facultySelect, .faculty-select').first()).selectOption({ index: 1 });
    await (page.locator('#cohortSelect, .cohort-select').first()).selectOption({ index: 1 });
  }
  await page.waitForTimeout(600);
  const selC2 = await page.locator('#weekSelect').inputValue();
  expect(parseInt(selC2, 10)).toBe(served);
});

test('M5 — the real logout → student login dance: her page = TODAY (cross-logout forget)', async ({ browser }, testInfo) => {
  test.setTimeout(90_000);   // the 3-login dance (4363 → logout → 25RSD0001) = the all-page family walks; 30s default = double-trip tight
  void testInfo;
  // THE ISOLATED CONTEXT, RUNS LAST BY DESIGN: its own fresh staff login
  // (4363 — NOT the storageState's 5770: Laravel's login regenerate()
  // tears down the requesting context's old session row) + the real
  // logout + the student login (25RSD0001). The cross-logout receipt:
  // a fresh stamp makes every previous session's week-keys match nothing.
  // ⚠ the storageState OVERRIDE is load-bearing (M5 mystery, cornered
  // 2026-10-23): inside a test, browser.newContext() INHERITS this file's
  // test.use({ storageState: staff-5770 }) — the dance booted as a live
  // 5770 session, so the 4363 login was a double-login over an existing
  // session and landed on the old identity's intended /dashboard (the
  // "Welcome (5770)" hang). An explicitly EMPTY storageState makes the
  // context genuinely bare; consent for the empty jar is the whole point.
  const bare = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  const p = await bare.newPage();
  await p.addInitScript(() => {
    if (sessionStorage.getItem('__mmcleared') === '1') return;
    sessionStorage.setItem('__mmcleared', '1');
    Object.keys(sessionStorage).filter(k => k.startsWith('weekmem-')).forEach(k => sessionStorage.removeItem(k));
  });
  await p.goto('/login/staff');
  await p.fill('#login_id', '4363');
  await p.fill('#password', PW);
  await p.click('button[type="submit"]');
  await p.waitForURL(/my-timetable/, { timeout: 60_000 });
  await p.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
  await p.waitForTimeout(900);
  const staffServed = await p.evaluate(() => (window as any).currentWeek);
  weekHopNext(p);
  await p.waitForTimeout(600);
  // the REAL logout UI (the nav-bar's button → the Fortify POST)
  // the .logout-btn = exactly ONE rendered (ui-nav-bar :71 — the topbar
  // icon; the drawer's logout carries .nav-drawer-logout instead), so
  // .first() is a harmless guard, not a two-match workaround:
  await p.locator('.logout-btn').first().click();
  await p.waitForURL(/login\/staff/, { timeout: 60_000 });
  // the student login (a fresh student session — LoginResponse lands her page)
  await p.goto('/login/student');
  await p.fill('#login_id', '25RSD0001');
  await p.fill('#password', PW);
  await p.click('button[type="submit"]');
  await p.waitForURL(/student-my-timetable-ui/, { timeout: 60_000 });
  await p.waitForTimeout(900);
  // her page = the STUDENT's today — the landing (before any nav of hers):
  // the selector = her index (a fresh login lands TODAY even when the tab
  // carries another session's week-key — the leftover stays inert)
  const landingSel = await p.locator('#weekSelect').inputValue();
  const served = await p.evaluate(() => (window as any).currentWeek);
  expect(parseInt(landingSel, 10)).toBe(served);
  const beforeKeys = await p.evaluate(() => Object.keys(sessionStorage).filter(k => k.startsWith('weekmem-')));
  // the previous session's week-key sits inert in the tab's storage (its
  // stamp = the previous login's; her session reads her own) — her own
  // nav+save adds HERS without restoring the earlier one's week:
  const countBefore = beforeKeys.length;
  await p.evaluate(() => (window as any).prevWeek());
  await p.waitForTimeout(600);
  const afterKeys = await p.evaluate(() => Object.keys(sessionStorage).filter(k => k.startsWith('weekmem-')));
  expect(afterKeys.length).toBe(countBefore + 1);
  // after her own prevWeek (previous session's = staffServed, hers = served,
  // her nav lands = the prev of her own today — the law's receipt):
  expect(await p.locator('#weekSelect').inputValue()).toBe(String(served - 1));
  await bare.close();
});
