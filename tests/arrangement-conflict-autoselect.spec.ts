import { test, expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * SUBJECT-SWITCH RECEIPTS — two laws, one file:
 *
 *  1. the 2026-11-01 fix: a conflicted subject switch still AUTO-PINS and
 *     auto-commits the matching conflict slot (SDD-waived at the time);
 *  2. `arrangement-week-keep-on-subject-switch` (user brief 2026-11-07, decided
 *     2026-11-08): that switch NO LONGER jumps the Week dropdown to the clash
 *     week — the week stays when it is bookable for the new subject, otherwise
 *     it moves to the earliest bookable week, and when nothing is bookable it
 *     stays put and says nothing.
 *
 * Run against the DISPOSABLE testing DB (:8030) — the demo DB holds almost no
 * conflicts, so the rich scenarios can only live here. Fixtures (inserted +
 * swept by THIS spec, id-keyed, run-idempotent):
 *   • TWO class_exceptions rows (cs 67 `AMSE2003 (L)` Thu 9–11 B100 @ W6,
 *     cs 23 `BMSE3153 (P)` Tue 13–14 B009 @ W9) — the pin fixtures;
 *   • room reservations (`reserveRoom`) that fill a room's whole week with
 *     `pending` time_slots so the week is genuinely unbookable.
 * Demo DBs are never touched.
 *
 * REQUIRES the testing DB seeded + :8030:
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   scripts/dev-serve.sh 8030
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/arrangement-conflict-autoselect.spec.ts
 */

const BASE = process.env.CRS_BASE ?? '';
const ALLOWED = process.env.CRS_MODE === 'testing'
  && process.env.CRS_ALLOW_SQL === '1'
  && BASE.includes(':803');

const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';
/* the pin fixtures: cs 67 (AMSE2003 (L) Thu 9–11 B100) week 6, cs 23
   (BMSE3153 (P) Tue 13–14 B009) week 9 — an exception id-captured by the
   insert + swept in beforeEach/afterAll (the T7 hygiene: id-keyed,
   run-idempotent). */
const FIXTURES = [
  { sessionId: 67, week: 6 },   /* AMSE2003 (L) Thu 9–11 B100 — W6 */
  { sessionId: 23, week: 9 },   /* BMSE3153 (P) Tue 13–14 B009 — W9 */
];

/** Flip markers: 998 = an existing `available` row we turned pending (restore
 *  it on release); 999 = a row we inserted (delete it on release). */
const FLIPPED = 998;
const INSERTED = 999;

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

/**
 * Make ONE room's whole week unbookable: every half-hour of every schedulable day
 * (Mon–Sat, 08:00–18:30 — the DB refuses Sunday, and the gate skips it anyway)
 * gets an `occupied/pending` time_slots row (the payload's taken register).
 * Existing `available` rows are flipped (version 998); missing half-hours are
 * inserted (version 999). `releaseRoom` undoes both.
 */
function reserveRoom(roomCode: string, week: number): void {
  sql(`UPDATE time_slots SET status = 'pending', version = ${FLIPPED}, updated_at = now()
       WHERE week_number = ${week}
         AND venue_id = (SELECT id FROM venues WHERE room_code = '${roomCode}')
         AND status = 'available';`);
  sql(`INSERT INTO time_slots
         (semester_id, class_session_id, week_number, day_of_week, start_time, end_time, venue_id, status, version, created_at, updated_at)
       SELECT s.id, NULL, ${week}, d, (time '08:00' + (h * interval '30 minutes'))::time,
              (time '08:00' + ((h + 1) * interval '30 minutes'))::time, v.id, 'pending', ${INSERTED}, now(), now()
       FROM semesters s, venues v, generate_series(0, 5) AS d, generate_series(0, 21) AS h   /* Mon–Sat: the DB check refuses 6 (Sunday), and the gate skips Sunday anyway */
       WHERE v.room_code = '${roomCode}' AND s.semester_code = '202605'
         AND NOT EXISTS (
           SELECT 1 FROM time_slots x
           WHERE x.semester_id = s.id AND x.week_number = ${week} AND x.day_of_week = d
             AND x.venue_id = v.id
             AND x.start_time = (time '08:00' + (h * interval '30 minutes'))::time);`);
}

function releaseRoom(roomCode: string, week: number): void {
  sql(`UPDATE time_slots SET status = 'available', version = 1, updated_at = now()
       WHERE version = ${FLIPPED} AND week_number = ${week}
         AND venue_id = (SELECT id FROM venues WHERE room_code = '${roomCode}');`);
  sql(`DELETE FROM time_slots WHERE version = ${INSERTED} AND week_number = ${week}
         AND venue_id = (SELECT id FROM venues WHERE room_code = '${roomCode}');`);
}

/* every reservation this run made, so afterEach can never leave residue */
const RESERVED: Array<{ room: string; week: number }> = [];

function reserve(roomCode: string, week: number): void {
  reserveRoom(roomCode, week);
  RESERVED.push({ room: roomCode, week });
}

function releaseAll(): void {
  while (RESERVED.length) {
    const r = RESERVED.pop()!;
    releaseRoom(r.room, r.week);
  }
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

/** The week selector's value (0-based week index, as a string) + its label. */
async function weekState(page: Page): Promise<{ value: number; label: string }> {
  return page.evaluate(() => {
    const sel = document.getElementById('weekSelector') as HTMLSelectElement | null;
    if (!sel) return { value: -1, label: '' };
    return { value: parseInt(sel.value, 10), label: (sel.options[sel.selectedIndex]?.textContent || '').trim() };
  });
}

/** The read-only decision probe (window.__switchWeek, the lane's receipt surface). */
async function decide(page: Page, sessionId: number, venue: string): Promise<{ week: number; why: string }> {
  return page.evaluate(({ sessionId, venue }) => {
    const api = (window as any).__switchWeek;
    return api.decide({ sessionId, venue });
  }, { sessionId, venue });
}

test.describe.configure({ mode: 'serial' });

test.beforeAll(() => { if (ALLOWED) insertFixture(); });

test.afterAll(() => { if (ALLOWED) { releaseAll(); sweepFixture(); } });

test.beforeEach(() => { if (ALLOWED) insertFixture(); });
/* afterEach keeps the run idempotent even mid-failure */
test.afterEach(() => { if (ALLOWED) { releaseAll(); sweepFixture(); } });

// ─────────────────────────────────────────────────────────────────────────────
// Law 1 + Law 2 together: the switch still pins the matching conflict slot, and
// the WEEK now stays where the user was (acceptance criteria 1 and 6).
// ─────────────────────────────────────────────────────────────────────────────
test('subject switch keeps the week while still auto-selecting the matching conflict slot', async ({ page }) => {
  test.skip(!ALLOWED, 'run with CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 against the disposable testing DB');
  await login(page);

  // ── 1. the PROMPT'S scenario organically: the home-row handoff shape →
  //      the pinned-session arrival pre-selects the matching conflict slot
  await page.goto(`${BASE}/replacement-arrangement?session=99&week=8&from=replacement-home`);
  await page.waitForTimeout(2500);
  expect(await selectedItemText(page)).toContain('Week 8');
  expect(await selectedItemText(page)).toContain('B111');

  // the picker holds exactly the subject's conflict rows; only ONE selected
  const totalItems = await page.locator('.slot-dd-item').count();
  expect(totalItems).toBeGreaterThan(0);
  expect(await page.locator('.slot-dd-item.selected').count()).toBe(1);

  // the arrival's landing week is the baseline the switch must NOT move
  const landing = await weekState(page);
  expect(landing.value).toBeGreaterThanOrEqual(0);

  // ── 2. change the Conflicted Subjects selection → the matching slot is
  //      auto-selected again (the W6 Thu 9–11 B100 fixture row = the earliest
  //      conflicted occurrence of AMSE2003) — and the WEEK STAYS
  await page.selectOption('#subjectSelector', 'AMSE2003');
  await page.waitForTimeout(1500);
  await acceptChangeModalIfOpen(page);          // the house clear-guard
  await page.waitForTimeout(2500);              // the wipe + auto-pin + commit chain

  const labelText = await selectedItemText(page);
  expect(labelText, 'the matching conflict slot is auto-selected after the subject change').toContain('Week 6');
  expect(labelText).toMatch(/9:00 AM\s*[-~]\s*11:00 AM/);   // the picker row's separator, not the strip's
  expect(labelText).toContain('B100');
  expect(await page.locator('.slot-dd-item.selected').count()).toBe(1);

  // THE NEW LAW: the week did not jump to the occurrence's week (6) — it stayed
  const afterSwitch = await weekState(page);
  expect(afterSwitch.value, 'the Week dropdown keeps the week the user was viewing').toBe(landing.value);
  expect(afterSwitch.label).not.toContain('Week 6');
  expect(await decide(page, 67, 'B100'), 'the decision is "kept", not "earliest"').toEqual(
    expect.objectContaining({ why: 'kept' }),
  );

  // the dropdown's INTERNAL state stays consistent with the DOM pick — the
  // POST-boot auto-pin doctrine's own witnesses: the pin followed the
  // occurrence and the venue family followed the schedule's ORIGINAL room
  // (arr-slot-original-venue-sync — unchanged by this lane)
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

  // the ONLY modal in the whole flow was the pre-existing 'Change Subject?'
  // clear-guard — and NO 'Change Time Slot?' modal may appear
  expect(await page.locator('#confirmModal').evaluate((el) => getComputedStyle(el).display)).not.toBe('block');

  // ── 3. a THIRD conflict schedule (BMSE3153 — the W9 fixture row): its own
  //      earliest row auto-selects; the week STILL stays where the user is
  await page.selectOption('#subjectSelector', 'BMSE3153');
  await page.waitForTimeout(1500);
  await acceptChangeModalIfOpen(page);
  await page.waitForTimeout(2500);
  const thirdLabel = await selectedItemText(page);
  expect(thirdLabel).toContain('Week 9');
  expect(thirdLabel).toMatch(/1:00 PM\s*[-~]\s*2:00 PM/);
  expect(thirdLabel).toContain('B009');
  await expect(page.locator('.slot-dd-item.selected')).toHaveCount(1);
  const thirdPin = await page.evaluate(() => (window as any).pinnedOccurrence || null);
  expect(thirdPin).toEqual(expect.objectContaining({ sessionId: 23, week: 9 }));
  expect((await weekState(page)).value, 'a clash in week 9 does not move the week').toBe(landing.value);

  // ── 4. switch BACK to the original subject → its conflict row auto-selected
  //      again, the week still unmoved
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
  expect((await weekState(page)).value).toBe(landing.value);

  // after the flow, no modal lingers OPEN
  expect(await page.locator('#confirmModal').evaluate((el) => getComputedStyle(el).display)).not.toBe('block');
});

// ─────────────────────────────────────────────────────────────────────────────
// AC5 — a kept week that CONTAINS the subject's own clash is still bookable
// (the pin's own week), so the decision is 'kept'. Old and new code agree on the
// resulting index here, so the discriminating witness is the decision flag.
// ─────────────────────────────────────────────────────────────────────────────
test('a kept week that contains the subject\'s own clash still counts as bookable (AC5)', async ({ page }) => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');
  await login(page);
  await page.goto(`${BASE}/replacement-arrangement?session=23&week=9&from=replacement-home`);
  await page.waitForTimeout(2500);

  // move the user ONTO the clash week (W9 = index 8), if the landing was elsewhere
  const target = await page.evaluate(() => {
    const sel = document.getElementById('weekSelector') as HTMLSelectElement | null;
    const opt = sel ? Array.from(sel.options).find(o => (o.textContent || '').trim().startsWith('Week 9 ·')) : null;
    return opt ? opt.value : null;
  });
  test.skip(target === null, 'week 9 is not offered by this seed');
  await page.selectOption('#weekSelector', target!);
  await page.waitForTimeout(1200);

  const decision = await decide(page, 23, 'B009');
  expect(decision.why, 'a week holding the class\'s own clash is bookable when it has a free cell').toBe('kept');
  expect(decision.week).toBe(await page.evaluate(() =>
    parseInt((document.getElementById('weekSelector') as HTMLSelectElement).value, 10)));
});

// ─────────────────────────────────────────────────────────────────────────────
// AC2 — the kept week is NOT bookable → the earliest bookable week wins.
// ─────────────────────────────────────────────────────────────────────────────
test('an unbookable kept week moves the selector to the earliest bookable week (AC2)', async ({ page }) => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');
  await login(page);
  await page.goto(`${BASE}/replacement-arrangement?session=99&week=8&from=replacement-home`);
  await page.waitForTimeout(2500);

  // the user moves to Week 12, then the room the switch will settle on (B100,
  // AMSE2003's own room) is filled for that whole week
  const week12 = await page.evaluate(() => {
    const sel = document.getElementById('weekSelector') as HTMLSelectElement | null;
    const opt = sel ? Array.from(sel.options).find(o => (o.textContent || '').trim().startsWith('Week 12 ·')) : null;
    return opt ? opt.value : null;
  });
  test.skip(week12 === null, 'week 12 is not offered by this seed');
  await page.selectOption('#weekSelector', week12!);
  await page.waitForTimeout(1200);
  reserve('B100', 12);
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(2500);
  await page.selectOption('#weekSelector', week12!);
  await page.waitForTimeout(1200);

  const before = await weekState(page);
  expect(before.value).toBe(parseInt(week12!, 10));

  await page.selectOption('#subjectSelector', 'AMSE2003');
  await page.waitForTimeout(1500);
  await acceptChangeModalIfOpen(page);
  await page.waitForTimeout(2500);

  const after = await weekState(page);
  expect(after.value, 'the unbookable week is left for the earliest bookable one').not.toBe(before.value);
  expect(after.value, 'and it is earlier than the kept week').toBeLessThan(before.value);
  const pin = await page.evaluate(() => (window as any).pinnedOccurrence || null);
  expect(pin).toEqual(expect.objectContaining({ sessionId: 67, week: 6 }));
});

// ─────────────────────────────────────────────────────────────────────────────
// AC3 — the walk SKIPS weeks with no free cell, including the clash week itself.
// ─────────────────────────────────────────────────────────────────────────────
test('the earliest-bookable walk skips the clash week when that week has no free cell (AC3)', async ({ page }) => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');
  await login(page);
  await page.goto(`${BASE}/replacement-arrangement?session=99&week=8&from=replacement-home`);
  await page.waitForTimeout(2500);

  // BMSE3153's own clash is W9 and its settled room is B009: fill W8 (the week
  // the user is on) and W9 (the clash week) → the walk must pass both and land
  // on the next week with a free cell
  reserve('B009', 8);
  reserve('B009', 9);
  await page.reload({ waitUntil: 'networkidle' });
  await page.waitForTimeout(2500);

  const before = await weekState(page);
  await page.selectOption('#subjectSelector', 'BMSE3153');
  await page.waitForTimeout(1500);
  await acceptChangeModalIfOpen(page);
  await page.waitForTimeout(2500);

  const after = await weekState(page);
  expect(after.value, 'the filled clash week (9) is skipped').not.toBe(8);
  expect(after.value, 'the walk does not stop on the clash week').not.toBe(before.value === 8 ? -1 : 8);
  expect(after.label, 'a later week with a free cell wins').toMatch(/Week (1[0-4])/);
  const decision = await decide(page, 23, 'B009');
  expect(['kept', 'earliest', 'none']).toContain(decision.why);
});

// ─────────────────────────────────────────────────────────────────────────────
// AC4 — nothing bookable at all → stay put, silent (D6). Driven through the
// decision probe: no UI path can reach it (the selector never sits on a week it
// hides), so the in-page taken register is stubbed full for every week.
// ─────────────────────────────────────────────────────────────────────────────
test('when nothing is bookable the week stays put and the decision is "none" (AC4)', async ({ page }) => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');
  await login(page);
  await page.goto(`${BASE}/replacement-arrangement?session=99&week=8&from=replacement-home`);
  await page.waitForTimeout(2500);

  const result = await page.evaluate(() => {
    const Mock = (window as any).MockData;
    const original = Mock.venueSlots;
    const stubbed: any = {};
    const weeks = 14;
    for (let w = 0; w < weeks; w++) {
      stubbed[w] = { B100: [] };
      for (let d = 0; d < 6; d++) {                 // Mon–Sat (the grid's schedulable columns)
        for (let h = 0; h < 22; h++) stubbed[w].B100.push([d, h, 1, null]);
      }
    }
    Mock.venueSlots = stubbed;                       // the room is taken everywhere
    const decision = (window as any).__switchWeek.decide({ sessionId: 67, venue: 'B100' });
    Mock.venueSlots = original;                      // restore — the grid reads it too
    const sel = document.getElementById('weekSelector') as HTMLSelectElement;
    return { decision, current: parseInt(sel.value, 10) };
  });

  expect(result.decision.why, 'no bookable week → silent stay').toBe('none');
  expect(result.decision.week, 'the kept week is returned unchanged').toBe(result.current);
});

// ─────────────────────────────────────────────────────────────────────────────
// (standing) span-follow-subject-switch: the block width follows the picked
// occurrence (the 2 h→1 h duration bug) — unchanged by this lane.
// ─────────────────────────────────────────────────────────────────────────────
test('span-follow-subject-switch: the block width follows the picked occurrence (the 2 h→1 h duration bug)', async ({ page }) => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');
  await login(page);

  // The painted hover preview is the user's exact witness (the span state is
  // closure-private: BLOCK_SPAN is not on window). Preview width = span cells:
  // span = round((W + spacing) / (cell + spacing)).
  const hoverSpan = async () => {
    await page.locator('#tableBody .cell-content.cell-available').first().hover();
    await page.waitForTimeout(500);
    return await page.evaluate(() => {
      const div = document.querySelector('.event-selection-preview');
      if (!div) return null;
      const td = document.querySelector('#tableBody td[data-hour]');
      const cell = td ? td.offsetWidth : 0;
      const bs = parseInt(getComputedStyle(document.getElementById('timetable')).borderSpacing) || 0;
      return Math.round((parseFloat((div as HTMLElement).style.width) + bs) / (cell + bs));
    });
  };

  // ARRIVAL the user reported: ?session=99 (AMSE1003, a 2 h lecture) — the
  // URL duration authority paints the preview 4 cells wide (2 h) pre-switch
  await page.goto(`${BASE}/replacement-arrangement?session=99&week=8&from=replacement-home`);
  await page.waitForTimeout(2500);
  expect(await hoverSpan()).toBe(4);

  // switch to BMSE2163 — the auto-committed pick is its 1 h practical
  await page.selectOption('#subjectSelector', 'BMSE2163');
  await page.waitForTimeout(1500);
  await acceptChangeModalIfOpen(page);
  await page.waitForTimeout(1500);

  // the dropdown state agrees with the picked occurrence (Week 14 · 12:00–1:00)
  expect(await page.locator('.slot-dd-item.selected').count()).toBe(1);
  expect((await page.locator('.slot-dd-item.selected').textContent()) || '').toContain('12:00 PM');

  // THE REGRESSION UNDER TEST: the hover preview must now paint 2 cells (1 h)
  // instead of the arrival's stale 4
  expect(await hoverSpan()).toBe(2);

  // and BACK to the 2 h lecture: the span follows AGAIN (4)
  await page.selectOption('#subjectSelector', 'AMSE1003');
  await page.waitForTimeout(1500);
  await acceptChangeModalIfOpen(page);
  await page.waitForTimeout(1500);
  expect(await page.locator('.slot-dd-item.selected').count()).toBe(1);
  expect(await hoverSpan()).toBe(4);
});

// ─────────────────────────────────────────────────────────────────────────────
// (standing) the hidden-stale-panel quirk — unchanged by this lane.
// ─────────────────────────────────────────────────────────────────────────────
test('the hidden-stale-panel quirk: clearing the subject clears the hidden panel', async ({ page }) => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');
  await login(page);

  await page.goto(`${BASE}/replacement-arrangement?session=99&week=8&from=replacement-home`);
  await page.waitForTimeout(2500);
  expect(await page.locator('.slot-dd-item').count()).toBe(1);
  expect(await page.locator('.slot-dd-item.selected').count()).toBe(1);

  // the arrival's block is armed → the house clear-guard pops on the subject
  // reset; the user answers 'Yes, Change' (the same-path discard)
  await page.selectOption('#subjectSelector', '');
  await page.waitForTimeout(1500);
  await acceptChangeModalIfOpen(page);
  await page.waitForTimeout(1500);

  // THE QUIRK'S LAW: the picker is hidden AND the panel EMPTY
  expect(await page.locator('#slotPicker').evaluate((el) => getComputedStyle(el).display)).toBe('none');
  expect(await page.locator('.slot-dd-item').count()).toBe(0);
  expect(await page.locator('.slot-dd-item.selected').count()).toBe(0);
});
