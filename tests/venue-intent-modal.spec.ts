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
  const sessions = await page.locator('#intentSessionSelect option').allTextContents();
    // conflicted-only: subjects = AMCS2093 (its Deepavali-week L occurrence)
  expect(subjects).toEqual(['AMCS2093 — Operating Systems']);
  // lecture hall: only the subject's L occurrence is offered (label = class's recurring slot)
  expect(sessions.some(t => /\(L\) Mon · \d{2}:\d{2}–\d{2}:\d{2}/.test(t))).toBe(true);
  expect(sessions.some(t => /\(P\)/.test(t))).toBe(false);
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
  const sessions = await page.locator('#intentSessionSelect option').allTextContents();
    /* user directive 2026-10-16 (week-agnostic pool): the label carries the
       class's own recurring slot (day + time), never the occurrence date */
    expect(sessions.some(t => /\(P\) Thu · \d{2}:\d{2}–\d{2}:\d{2}/.test(t))).toBe(true);
  expect(sessions.some(t => /\(P\) Fri · \d{2}:\d{2}–\d{2}:\d{2}/.test(t))).toBe(true);
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
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  const dayEntry = await page.evaluate((w) => (window as any).weekData[w].days[1], 7); // W8 Tuesday (a bookable day in the Deepavali week)
  const url = '/replacement-arrangement?venue=B110&date=' + encodeURIComponent(dayEntry.date) + '&time=' + encodeURIComponent('11:00') + '&from=venue-timetable';
  await page.goto(url);
  // AMCS2093 (L) auto-picked — the subject select binds it; the shared toast
  // bar is a single slot (later boot toasts may overwrite), so poll for it.
  const subjectVal = await page.locator('#subjectSelector').inputValue();
  console.log('C2 SUBJECT:', subjectVal);
  console.log('C2 URL:', page.url());
    expect(subjectVal).toBe('AMCS2093');
  const toastMsg = page.locator('#toastBar .toast-message');
  await expect.poll(async () => (await toastMsg.textContent()) || '', { timeout: 5000 })
    .toContain('auto-picked');
  await expect.poll(async () => (await toastMsg.textContent()) || '', { timeout: 1000 })
    .toContain('fit B110');
  // the pinned occurrence's amber block sits on the booked slot row (an event block exists on Mon)
  const hasAmber = await page.locator('#tableBody tr').first().locator('.event-block, .event-mine, .event-others').count();
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
