import { test, expect } from '@playwright/test';

/** SDD session-arrival-pin-guard (user report 2026-10-20, two sightings):
 *  every ?session= handoff arrives with an honest server pin — and the
 *  boot's own subject programming (pre-__bootDone) used to let T18's
 *  earliest-conflict auto-pin HIJACK it: window.pinnedOccurrence read
 *  {97, 8} (the joint (L) lecture's Deepavali Monday) while the user had
 *  clicked the practicals' Christmas sessions, and the strip rendered the
 *  lecture's (L) + joint cohorts + 18 + 15 = 33 over the clicked practicals.
 *  The fix (?session-arrival-pin-guard): the auto-pin fires only POST-boot;
 *  on arrivals the else-ride keeps the user's pin and courseCtx() resolves
 *  the strip from the pinned session's own facts.
 *  Data truth (tinker-locked): session 49 = AMCS2093 (P) @ B011 Fri 11:00,
 *  cohorts DFT2 (S1) G1, 18 students; session 39 = AMCS2093 (P) @ B010 Thu
 *  13:30, cohorts DSF2 (S1) G1, 15 students; session 97 = the joint (L). */
test.use({ storageState: '.auth/staff-5770.json' });

test('S1 — the pinned arrival keeps the user\'s pair + the strip reads 49\'s own facts', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=49&week=14&from=my-timetable');
  await page.waitForSelector('.conflict-strip', { timeout: 15_000 });
  await page.waitForTimeout(900);
  const strip = await page.locator('#subjectInfo').innerText();
  // the honest subject line: the PRACTICAL's own type (was "(L)")
  expect(strip).toContain('(P)');
  expect(strip).not.toContain('(L)');
  // the user's clicked cohort only — NOT the lecture's joint line
  expect(strip).toContain('DFT2 (S1) G1');
  expect(strip).not.toContain('DFT2 (S1) G1 + DSF2 (S1) G1');
  // the pinned pair the submit honours = the arrival pair (was hijacked to {97, 8})
  const pin = await page.evaluate(() => (window as any).pinnedOccurrence);
  expect(pin).toEqual({ sessionId: 49, week: 14 });
  // the enrolled headcount = 49's own (18) — not the lecture's "18 + 15 = 33"
  expect(strip).not.toContain('18 + 15 = 33');
  // the trigger reads the pinned anchor (the boot tail's rewrite), never
  // the T18 "Auto-pinned: the earliest" wording (the dead-write becomes enforced)
  await expect(page.locator('#slotTriggerText')).not.toContainText('Auto-pinned');
  // the strip's slot line still = 49's own Fri Dec 25 facts (unchanged by the fix)
  expect(strip).toContain('Week 14');
  // ARRIVAL-GRID-NO-PIN (user law 2026-10-20): the pin carries DATA only —
  // the GRID lands on the Earliest Bookable week (NOT the pinned W14, and
  // NOT the T18-earliest-conflict W8 — both position-writers are retired;
  // the assert reads the live weekSelector's value vs the shared engine:
  const ebP = await page.evaluate(() => (window as any).firstBookableDay((window as any).weekData));
  expect(ebP).not.toBeNull();
  const gridWeek = await page.evaluate(() => parseInt(document.getElementById('weekSelector').value, 10));
  expect(gridWeek).toBe(ebP.week);
  // the pin pair rides only in the strip/submit — the bridge saves NOTHING:
  const saved = await page.evaluate(() => localStorage.getItem('arrangementWeek'));
  expect(saved).toBeNull();
});

test('S2 — the replacement-home sighting (39) reads its own facts (P · DSF2 · {39,14})', async ({ page }) => {
  await page.goto('/replacement-arrangement?session=39&week=14&from=replacement-home');
  await page.waitForSelector('.conflict-strip', { timeout: 15_000 });
  await page.waitForTimeout(900);
  const strip = await page.locator('#subjectInfo').innerText();
  expect(strip).toContain('(P)');
  expect(strip).not.toContain('(L)');
  expect(strip).toContain('DSF2 (S1) G1');
  expect(strip).not.toContain('DSF2 (S1) G1 + DFT2');
  const pin = await page.evaluate(() => (window as any).pinnedOccurrence);
  expect(pin).toEqual({ sessionId: 39, week: 14 });
  expect(strip).not.toContain('18 + 15 = 33');
  await expect(page.locator('#slotTriggerText')).not.toContainText('Auto-pinned');
});

test('S3 — Replace Now click-through: the producer hands 49 and the boot keeps it', async ({ page }) => {
  // the producer side (replace-now's S7 family shape): W14 view, the Fri
  // AMCS2093(P) block (day 4 = Friday, hour 6 = 11:00) carries the
  // Christmas-Day fact → Replace Now hands ?session=49&week=14&from=my-timetable
  await page.goto('/my-timetable-ui');
  await page.locator('#weekSelect').selectOption('13');
  await page.waitForSelector('#tableBody .event-block', { timeout: 15_000 });
  const block = page.locator('#tableBody td[data-day="4"][data-hour="6"] .event-block:has-text("AMCS2093(P)")').first();
  await expect(block).toBeVisible();
  await block.click();
  await expect(page.locator('#classModal')).toBeVisible({ timeout: 10_000 });
  const replaceBtn = page.locator('#btnReplaceNow');
  await expect(replaceBtn).toBeVisible();
  // stability hardening (repeat-battery receipt): the class modal (repeat-battery receipt): the class modal's SHOW
  // transition makes the button wobble — settle, then give BOTH legs the
  // full window (the click's own 5s default died under load before the
  // native navigation could report)
  await page.waitForTimeout(400);
  await Promise.all([
    page.waitForURL(/replacement-arrangement\?.*session=/, { timeout: 15_000 }),
    replaceBtn.click({ timeout: 15_000 }),
  ]);
  const url = new URL(page.url());
  expect(url.searchParams.get('session')).toBe('49');
  expect(url.searchParams.get('week')).toBe('14');
  expect(url.searchParams.get('from')).toBe('my-timetable');
  // the boot must KEEP that pair (the hijack regression receipts)
  await page.waitForSelector('.conflict-strip', { timeout: 15_000 });
  await page.waitForTimeout(900);
  const pin = await page.evaluate(() => (window as any).pinnedOccurrence);
  expect(pin).toEqual({ sessionId: 49, week: 14 });
  const strip = await page.locator('#subjectInfo').innerText();
  expect(strip).toContain('(P)');
  expect(strip).toContain('DFT2 (S1) G1');
  expect(strip).not.toContain('18 + 15 = 33');
});
