import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * THE 2026-11-01 BUG FIX RECEIPT — conflicted subject switch auto-selects the
 * matching conflict slot (SDD-waived; the fix = applySubjectChange's POST-boot
 * tail commits the pin-matching picker row via selectSlot(opts.auto)).
 *
 * Run against the DISPOSABLE testing DB (:8030) — the demo DB :8000 holds ZERO
 * conflicts since the user's cleanout, so the rich scenario can only live here.
 * Fixture: ONE class_exceptions row (cs 67 `AMSE2003 (L)` Thu 9–11 B100, W6)
 * inserted + swept by THIS spec; the OTHER two conflicts are SEED-ORGANIC
 * (W8 Mon Deepavali + W14 Thu/Fri Christmas holidays) — never inserted.
 * Demo DB :8000 is never touched.
 *
 * REQUIRES the testing DB seeded + :8030:
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/arrangement-conflict-autoselect.spec.ts
 */

const BASE = process.env.CRS_BASE ?? '';
const ALLOWED = process.env.CRS_MODE === 'testing'
  && process.env.CRS_ALLOW_SQL === '1'
  && BASE.includes(':803');

const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';
/* the fixture probe: cs 67 (AMSE2003 (L) Thu 9–11 B100) week 6 — an exception
   id-captured by the insert + swept in beforeEach/afterAll (the T7 hygiene:
   id-keyed, run-idempotent). */
const FIXTURES = [
  { sessionId: 67, week: 6 },   /* AMSE2003 (L) Thu 9–11 B100 — W6 */
  { sessionId: 23, week: 9 },   /* BMSE3153 (P) Tue 13–14 B009 — W9 */
];

function sql(query: string): void {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' });
}

function insertFixture(): void {
  // delete-then-insert keyed by the EXACT session:week pairs — never the seed's
  FIXTURES.forEach(({ sessionId, week }) => {
    const reason = (sessionId === 23) ? 'annual_leave' : 'medical_leave';
    sql(`DELETE FROM class_exceptions WHERE class_session_id = ${sessionId} AND week_number = ${week};`);
    sql(`INSERT INTO class_exceptions (class_session_id, week_number, reason, created_at, updated_at)
         VALUES (${sessionId}, ${week}, '${reason}', now(), now());`);
  });
}

function sweepFixture(): void {
  FIXTURES.forEach(({ sessionId, week }) => {
    sql(`DELETE FROM class_exceptions WHERE class_session_id = ${sessionId} AND week_number = ${week};`);
  });
}

/** The pre-existing clear-guard law: a user-armed store (the ?session= arrival
    paints the pinned block) pops 'Change Subject?' on a subject switch — the
    human answer is 'Yes, Change' (the same-path discard, then apply). Helper =
    the modal click-through, NOT a fake: closed = no-op. */
async function acceptChangeModalIfOpen(page: Page): Promise<void> {
  const yes = page.locator('#confirmModal button', { hasText: 'Yes, Change' }).first();
  if (await yes.isVisible().catch(() => false)) {
    await yes.click();
    await page.waitForTimeout(700);
  }
}

async function login(page: Page): Promise<void> {
  await page.goto(`${BASE}/login/staff?_=arrfix-login-${Date.now()}`);
  await page.fill('#login_id', '5425');
  await page.fill('#password', 'Tarumt@2026');
  await page.click('#loginBtn');
  await page.waitForURL(/my-timetable|dashboard/, { timeout: 30_000 });
}

/** The ONE .slot-dd-item.selected's label text ('' when none). */
async function selectedItemText(page: Page): Promise<string> {
  return page.evaluate(() => {
    const el = document.querySelector('.slot-dd-item.selected .slot-dd-item-label');
    return el ? (el as HTMLElement).textContent || '' : '';
  });
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => { if (ALLOWED) insertFixture(); });

test.afterAll(() => { if (ALLOWED) sweepFixture(); });

test.beforeEach(() => { if (ALLOWED) insertFixture(); });
/* afterEach keeps the run idempotent even mid-failure */
test.afterEach(() => { if (ALLOWED) sweepFixture(); });

test('subject switch auto-selects the matching conflict slot (the bug fix)', async ({ page }) => {
  test.skip(!ALLOWED, 'run with CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 against the disposable testing DB');
  await login(page);

  // ── 1. the PROMPT'S scenario organically: the home-row handoff shape →
  //      the pinned-session arrival pre-selects the matching conflict slot
  //      (criteria 1: the ORIGINAL pre-selection law still holds)
  await page.goto(`${BASE}/replacement-arrangement?session=99&week=8&from=replacement-home`);
  await page.waitForTimeout(2500);
  expect(await selectedItemText(page)).toContain('Week 8');
  expect(await selectedItemText(page)).toContain('B111');

  // the picker holds exactly the subject's conflict rows; only ONE selected
  const totalItems = await page.locator('.slot-dd-item').count();
  expect(totalItems).toBeGreaterThan(0);
  expect(await page.locator('.slot-dd-item.selected').count()).toBe(1);

  // ── 2. THE BUG: change the Conflicted Subjects selection → the matching
  //      slot must be auto-selected again (the W6 Thu 9–11 B100 fixture row
  //      = the earliest conflicted occurrence of AMSE2003)
  await page.selectOption('#subjectSelector', 'AMSE2003');
  await page.waitForTimeout(1500);
  // the house clear-guard: the arrival's pinned block is on the grid →
  // 'Change Subject?' pops; the user confirms (the same-path discard)
  await acceptChangeModalIfOpen(page);
  await page.waitForTimeout(2500); // the wipe + auto-pin + commit chain

  const labelText = await selectedItemText(page);
  expect(labelText, 'the matching conflict slot is auto-selected after the subject change').toContain('Week 6');
  expect(labelText).toContain('9:00 AM - 11:00 AM');
  expect(labelText).toContain('B100');
  expect(await page.locator('.slot-dd-item.selected').count()).toBe(1);

  // the dropdown's INTERNAL state stays consistent with the DOM pick — the
  // POST-boot auto-pin doctrine's own witnesses: the grid jumped to the
  // occurrence's week (0-based) and the venue family followed the schedule's
  // ORIGINAL room (arr-slot-original-venue-sync)
  const witnesses = await page.evaluate(() => ({
    pin: (window as any).pinnedOccurrence || null,
    venue: (typeof (window as any).__venueSelected === 'function') ? (window as any).__venueSelected() : null,
  }));
  expect(witnesses.pin, 'the D1 pin follows the auto-picked occurrence (what the submit honours)').toEqual(
    expect.objectContaining({ sessionId: 67, week: 6 }),
  );
  expect(witnesses.venue).toBe('B100', 'the venue family synced to the schedule room');
  const trigger = (await page.locator('#slotTriggerText').textContent()) || '';
  expect(trigger).toContain('Week 6');

  // criteria 7/8: the ONLY modal in the whole flow was the pre-existing
  // 'Change Subject?' clear-guard (receipted above) — and NO 'Change Time
  // Slot?' modal may appear: the auto pick rides opts.auto on an EMPTY store
  expect(await page.locator('#confirmModal').evaluate((el) => getComputedStyle(el).display)).not.toBe('block');

  // ── 3. a THIRD conflict schedule (BMSE3153 — the W9 fixture row): its own
  //      earliest row auto-selects; the AMSE1003/AMSE2003 rows stay unselected
  //      (criteria 6: no unrelated slot is picked). NOTE (pre-existing): the
  //      hidden panel may keep old items — visible rows only are asserted.
  await page.selectOption('#subjectSelector', 'BMSE3153');
  await page.waitForTimeout(1500);
  await acceptChangeModalIfOpen(page);
  await page.waitForTimeout(2500);
  const thirdLabel = await selectedItemText(page);
  expect(thirdLabel).toContain('Week 9');
  expect(thirdLabel).toContain('1:00 PM - 2:00 PM');
  expect(thirdLabel).toContain('B009');
  await expect(page.locator('.slot-dd-item.selected')).toHaveCount(1);
  const thirdPin = await page.evaluate(() => (window as any).pinnedOccurrence || null);
  expect(thirdPin).toEqual(expect.objectContaining({ sessionId: 23, week: 9 }));

  // ── 4. switch BACK to the original subject → its conflict row
  //      auto-selected again (criteria 5: switching between multiple
  //      conflicted subjects selects correctly each time)
  await page.selectOption('#subjectSelector', 'AMSE1003');
  await page.waitForTimeout(1500);
  await acceptChangeModalIfOpen(page); // defensive: should be a NO-op here
  await page.waitForTimeout(2500);
  const backLabel = await selectedItemText(page);
  expect(backLabel).toContain('Week 8');
  expect(backLabel).toContain('B111');
  expect(await page.locator('.slot-dd-item.selected').count()).toBe(1);
  const backPin = await page.evaluate(() => (window as any).pinnedOccurrence || null);
  expect(backPin).toEqual(expect.objectContaining({ sessionId: 99, week: 8 }));

  // criteria 8, second half: after the flow, no modal lingers OPEN
  expect(await page.locator('#confirmModal').evaluate((el) => getComputedStyle(el).display)).not.toBe('block');
});
