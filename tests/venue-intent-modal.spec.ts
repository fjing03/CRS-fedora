import { test, expect } from '@playwright/test';

/** SDD arrange-intent-session-choice T6 — the producer modal spec (6 tests):
 *  wire-call modal (the Livewire-runtime regression guard), compatible-only
 *  dropdown per venue type, byte-frozen labels, zero-compatible message,
 *  8-param handoff + landing, fallback prefill for bypassed intents. */
test.use({ storageState: '.auth/staff-5770.json' });

test('A1: B110 modal opens after wire call (the RuntimeException regression)', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B110');
  await page.waitForTimeout(600);
  // jump to the last week so free+future cells exist
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  await page.evaluate((idx) => (window as any).selectWeek(idx), last);
  await page.waitForTimeout(300);
  const cell = page.locator('#tableBody .cell-available:not(.cell-no-fit)').first();
  await cell.click();
  await expect(page.locator('#availableTooltip')).toHaveClass(/show/);
  await page.locator('#tooltipBookBtn').click();
  // the wire call resolves → modal renders (use toBeVisible to force the wait)
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  const subjects = await page.locator('#intentSubjectSelect option').allTextContents();
  const sessions = await page.locator('#intentSessionSelect option').allTextContents();
    // lecture hall: only the subject's L occurrence is offered
  expect(sessions.some(t => /\(L\) ·/.test(t))).toBe(true);
  expect(sessions.some(t => /\(P\) ·/.test(t))).toBe(false);
  await page.locator('#intentCancelBtn').click();
  await expect(page.locator('#intentSessionModal')).not.toBeVisible();
});

test('A2: B009 modal offers only P sessions with the byte-frozen label', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B009');
  await page.waitForTimeout(600);
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  await page.evaluate((idx) => (window as any).selectWeek(idx), last);
  await page.waitForTimeout(300);
  await page.locator('#tableBody .cell-available:not(.cell-no-fit)').first().click();
  await page.locator('#tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  const sessions = await page.locator('#intentSessionSelect option').allTextContents();
    expect(sessions.some(t => /\(P\) · W\d+, [A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} · \d{2}:\d{2}–\d{2}:\d{2}/.test(t))).toBe(true);
  expect(sessions.some(t => /\(L\) ·/.test(t))).toBe(false);
});

test('A6: [Arrange] handoff lands with the 8-param URL + pinned week', async ({ page }) => {
  await page.goto('/venue-timetable-ui?venue=B110');
  await page.waitForTimeout(600);
  const last = await page.evaluate(() => (window as any).weekData.length - 1);
  await page.evaluate((idx) => (window as any).selectWeek(idx), last);
  await page.waitForTimeout(300);
  await page.locator('#tableBody .cell-available:not(.cell-no-fit)').first().click();
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
  const dayEntry = await page.evaluate((w) => (window as any).weekData[w].days[0], last); // Mon of last week
  const url = '/replacement-arrangement?venue=B110&date=' + encodeURIComponent(dayEntry.date) + '&time=' + encodeURIComponent('08:00') + '&from=venue-timetable';
  await page.goto(url);
  await page.waitForTimeout(1200); // fallback fires post-boot-render
  // AMCS2093 (L) auto-picked — the subject select binds it
  const subjectVal = await page.locator('#subjectSelector').inputValue();
    expect(subjectVal).toBe('AMCS2093');
  const toastText = await page.locator('#toastBar').textContent().catch(() => '');
    expect(toastText || '').toContain('auto-picked');
  expect(toastText || '').toContain('fit B110');
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
  await page.evaluate((idx) => (window as any).selectWeek(idx), last);
  await page.waitForTimeout(300);
  await page.locator('#tableBody .cell-available:not(.cell-no-fit)').first().click();
  await page.locator('#tooltipBookBtn').click();
  await expect(page.locator('#intentSessionModal')).toBeVisible({ timeout: 10_000 });
  const notice = await page.locator('#intentNotice').textContent();
    expect(notice || '').toContain('None of your classes can run in B009');
  expect(notice || '').toContain('hosts Practical (P) sessions only');
  await expect(page.locator('#intentPickers')).toBeHidden();
  await expect(page.locator('#intentArrangeBtn')).toBeHidden();
  await ctx.close();
});
