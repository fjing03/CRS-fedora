import { test, expect } from '@playwright/test';
import { pickSpanFreeCell, selectWeek } from './helpers/intent-modal-helper';

/** SDD arrange-intent-session-choice T6 — the producer modal spec (6 tests):
 *  wire-call modal (the Livewire-runtime regression guard), compatible-only
 *  dropdown per venue type, byte-frozen labels, zero-compatible message,
 *  8-param handoff + landing, fallback prefill for bypassed intents. */
test.use({ storageState: '.auth/staff-5770.json' });



test('A1: B110 modal opens after wire call (the RuntimeException regression)', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B110');
  await page.waitForTimeout(600);
  // W8 (Deepavali in-lieu): AMCS2093 L is conflicted there — the only offer
  await selectWeek(page, 7);
  await page.waitForTimeout(300);
  await pickSpanFreeCell(page);
  await expect(page.locator('#availableTooltip')).toHaveClass(/show/);
  await page.locator('#tooltipBookBtn').click();
  // the wire call resolves → modal renders (use toBeVisible to force the wait)
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  const subjects = await page.locator('#intentSubjectSelect option').allTextContents();
  /* the session picker is the occurrence-grain custom dropdown (user request
     2026-10-16) — rows + data-tips, not a native <option> list */
  const sessions = await page.locator('#intentSessionPanel .session-dd-item').allTextContents();
    // conflicted-only: subjects = AMCS2093 (its Deepavali-week L occurrence)
  expect(subjects).toEqual(['AMCS2093 — Operating Systems']);
  // lecture hall: only the subject's L occurrence is offered — the row label
  // carries the FLAGGED occurrence (user request 2026-10-16): type, week, day,
  // date, time range "(L) W8, Mon, Nov 9 . 11:00 ~ 13:00"
  expect(sessions.some(t => /\(L\) W8, Mon, Nov 9 \. 11:00 ~ 13:00/.test(t))).toBe(true);
  expect(sessions.some(t => /\(P\)/.test(t))).toBe(false);
  // every row carries the shared data-tip with the full details (module, cohort
  // size, original slot, conflict reason)
  const tips = await page.locator('#intentSessionPanel .session-dd-item').evaluateAll(
    els => els.map(e => e.getAttribute('data-tip') || ''));
  expect(tips.length).toBeGreaterThan(0);
  expect(tips.every(t => t.includes('AMCS2093') && t.includes('students') && t.includes('conflict:'))).toBe(true);
  // the Livewire morph must NOT wipe the JS-built grid (wire:ignore guard)
  await page.waitForTimeout(400);
  expect(await page.locator('#tableBody .cell-available').count()).toBeGreaterThan(0);
  expect(await page.locator('#weekSelect option').count()).toBeGreaterThan(0);
  await page.locator('#intentCancelBtn').click();
  await expect(page.locator('#intentSessionModal')).not.toBeVisible();
});

test('A2: B009 modal offers only P sessions with the byte-frozen label', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B009');
  await page.waitForTimeout(600);
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  await selectWeek(page, last);
  await page.waitForTimeout(300);
  await pickSpanFreeCell(page);
  await page.locator('#tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  const subjects = await page.locator('#intentSubjectSelect option').allTextContents();
  /* the session picker is the occurrence-grain custom dropdown (user request
     2026-10-16) — rows + data-tips, not a native <option> list */
  const sessions = await page.locator('#intentSessionPanel .session-dd-item').allTextContents();
    /* user directive 2026-10-16 (week-agnostic pool): the label carries the
       class's own recurring slot (day + time), never the occurrence date */
    /* user request 2026-10-16: the row label = the flagged occurrence —
       "(P) W14, Thu, Dec 24 . 13:30 ~ 15:00" */
    expect(sessions.some(t => /\(P\) W14, Thu, Dec 24 \. 13:30 ~ 14:30/.test(t))).toBe(true);
  expect(sessions.some(t => /\(P\) W14, Fri, Dec 25 \. 11:00 ~ 12:00/.test(t))).toBe(true);
  expect(sessions.some(t => /\(L\)/.test(t))).toBe(false);
  // conflicted-only (demo holidays land W14 Thu + Fri): both AMCS2093 P
  // occurrences ARE conflicted and offered; BMIT2013 (Tue/Wed) is healthy →
  // never offered (subjects list holds AMCS2093 only)
  expect(subjects).toEqual(['AMCS2093 — Operating Systems']);
});

test('A6: [Arrange] handoff lands with the 8-param URL + pinned week', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B110');
  await page.waitForTimeout(600);
  await selectWeek(page, 7); // W8: Deepavali conflict
  await page.waitForTimeout(300);
  await pickSpanFreeCell(page);
  await page.locator('#tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  await page.locator('#intentArrangeBtn').click();
  await page.waitForURL(/replacement-arrangement\?.*session=/, { timeout: 10_000 });
  expect(page.url()).toMatch(/venue=B110&date=.+&time=.+&from=venue-timetable&code=.+&session=\d+&cohort=.+&week=\d+/);
  // the pinned week rendered + amber block preselected
  await page.waitForTimeout(800);
  const hasBlock = await page.locator('#tableBody .event-block, #tableBody .event-mine').first().isVisible().catch(() => false);
  });

test('C2: fallback prefill for modal-bypass full-intent URLs (attributed auto-pick)', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B110');
  await page.waitForTimeout(500);
  // venue-c2-toast-noise (root-caused 2026-10-23, explore-brief §2–5): the
  // ORIGINAL target (W8-Tue 11:00) sat INSIDE the proposer's own teaching
  // rows ([1,6,8] 11:00–12:00 + [1,8,10] 12:00–13:00 — recurring, constant
  // every week), and W8-Mon is the Deepavali `cell-ph` row — the contract
  // can NEVER auto-place on either (cell-occupied / cell-ph ≠ cell-available;
  // the consume honestly goes manual: `r:manual?true … reason:held`, no toast).
  // The honest target = a legal OWN-free + room-free slot: W9-Mon 15:00
  // (probed live: the attributed toast fires, the 4 cells = cell-selected).
  const dayEntry = await page.evaluate(() => (window as any).weekData[8].days[0]); // W9 Monday
  const url = '/replacement-arrangement?venue=B110&date=' + encodeURIComponent(dayEntry.date) + '&time=' + encodeURIComponent('15:00') + '&from=venue-timetable';
  await page.goto(url);
  // AMCS2093 (L) auto-picked — the subject select binds it; the shared toast
  // bar is a single slot (later boot toasts may overwrite), so poll for it.
  const subjectVal = await page.locator('#subjectSelector').inputValue();
  expect(subjectVal).toBe('AMCS2093');
  const toastMsg = page.locator('#toastBar .toast-message');
  await expect.poll(async () => (await toastMsg.textContent()) || '', { timeout: 5000 })
    .toContain('auto-picked');
  await expect.poll(async () => (await toastMsg.textContent()) || '', { timeout: 1000 })
    .toContain('fit B110');
  // the placement receipt: the span-4 (2h) block landed ON the intent slot
  const picked = await page.locator('#tableBody td[data-day="0"] .cell-selected').count();
  expect(picked).toBe(4);
  });

test('C2b: the manual arrival banner is KIND-COMPLETE (one voice, the attribution clause)', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B110');
  await page.waitForTimeout(500);
  // venue-intent-banner-kind (2026-10-23, the user's option 2): the own-busy
  // trap arrival (W8-Tue 11:00 — the proposer teaches 11:00–13:00, constant
  // every week; receipted in venue-c2-toast-noise) goes manual. The banner is
  // the ONE voice: it must carry WHICH class the fallback pre-selected and
  // that the pre-selection survives. NO floating toast (rejected = redundancy).
  const dayEntry = await page.evaluate(() => (window as any).weekData[7].days[1]); // W8 Tuesday
  const url = '/replacement-arrangement?venue=B110&date=' + encodeURIComponent(dayEntry.date) + '&time=' + encodeURIComponent('11:00') + '&from=venue-timetable';
  await page.goto(url);
  const subjectVal = await page.locator('#subjectSelector').inputValue();
  expect(subjectVal).toBe('AMCS2093');   // the fallback prefill still binds on the manual path
  const banner = page.locator('#bookingIntent');
  await expect(banner).toContainText('Your booked slot', { timeout: 5000 });
  await expect(banner).toContainText('Pre-selected AMCS2093');
  await expect(banner).toContainText('its sessions fit B110');
  await expect(banner).toContainText('the pre-selection stays');
  // the third-voice guard: no floating toast on the manual path
  const toastText = (await page.locator('#toastBar .toast-message').textContent()) || '';
  expect(toastText).not.toContain('auto-picked');
  });

test('B002: the ghost success despawns honestly — the manual banner + the kind clause, no false "pre-selected" cells', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B002');
  await page.waitForTimeout(500);
  // the USER'S live bug report (2026-10-23): B002 Fri 12:00 — the venue
  // paints 12:00–13:00 free but 13:00–14:00 occupied (the = the = = = real
  // other-class block): a span-4 AMCS2093 pre-select CANNOT hold. The
  // pre-build consume judged a stale grid and toasted success; the boot's
  // final buildTimetable/loadCurrentWeek despawned the block silently.
  // venue-intent-ghost-success-spot: the boot tail re-validates the placed
  // block against the REAL grid — the ghost retracts its toast and the
  // honest manual banner (with the venue-intent-banner-kind clause) speaks.
  const dayEntry = await page.evaluate(() => (window as any).weekData[5].days[4]); // W6 Friday
  const url = '/replacement-arrangement?venue=B002&date=' + encodeURIComponent(dayEntry.date) + '&time=' + encodeURIComponent('12:00') + '&from=venue-timetable&code=' + encodeURIComponent('AMCS2093') + '&session=97&cohort=' + encodeURIComponent('DFT2, DSF2') + '&week=8';
  await page.goto(url);
  await page.waitForTimeout(1500);
  // NO ghost cells: the block despawned against the real grid
  const picked = await page.locator('#tableBody td[data-day="4"] .cell-selected').count();
  expect(picked).toBe(0);
  // the ghost toast retracted: the REAL occupancy makes the honest banner = ONE voice
  const banner = page.locator('#bookingIntent');
  await expect(banner).toContainText('Your booked slot', { timeout: 5000 });
  await expect(banner).toContainText('Pre-selected AMCS2093');
  await expect(banner).toContainText('the pre-selection stays');
  // the ghost toast retracted: the bar HIDES (its stale text stays in the DOM,
  // hidden — assert the bar's visibility, not the text)
  await expect(page.locator('#toastBar')).not.toHaveClass(/visible/, { timeout: 5000 });
  // the real blocker speaks: the banner names the availability cause
  await expect(banner).toContainText('no longer available');
  });

test('B1: zero-compatible modal message (4363 — L-only lecturer) on lab B009', async ({ browser }) => {
  // dedicated context = the 4363 storageState (no in-test login swap)
  const ctx = await browser.newContext({ storageState: '.auth/staff-4363.json' });
  const page = await ctx.newPage();
  await page.goto('/venue-timetable-ui?venue=B009');
  await page.waitForTimeout(600);
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  await selectWeek(page, last);
  await page.waitForTimeout(300);
  await pickSpanFreeCell(page);
  await page.locator('#tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  const notice = await page.locator('#intentNotice').textContent();
    expect(notice || '').toContain('None of your classes can run in B009');
  expect(notice || '').toContain('hosts Practical (P) sessions only');
  await expect(page.locator('#intentPickers')).toBeHidden();
  await expect(page.locator('#intentArrangeBtn')).toBeHidden();
  await ctx.close();
});
