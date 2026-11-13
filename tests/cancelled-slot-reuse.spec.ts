import { test, expect, type Page } from '@playwright/test';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';
import {
  CSR_BASE,
  csrAutoReject,
  csrFixtureSeed,
  csrFixtureTarget,
  csrSeedPending,
  loginOnce,
  openStaff,
  sqlCount,
  sqlScalar,
  type CsrFixtureReceipt,
} from './helpers/cancelled-slot-reuse-fixture';

/* ═══════════════════════════════════════════════════════════════════════
 * cancelled-slot-reuse — A20 / A20b / A20c BROWSER TESTS ONLY (approved).
 * Frozen spec: .sdd/changes/cancelled-slot-reuse/specs/cancelled-occurrence-
 * reuse/spec.md — R2 S05, R3 S09–S11, R4 S14–S16, R5 S19, R6 S22–S23.
 *
 * It WRITES on the disposable testing DB only, so it is double-guarded:
 *
 *   1. the module-first receipt-target-guard abort (below, requireAllowSql);
 *   2. the fixture CLI (tests/helpers/csr-fixture-runner.php) queries the LIVE
 *      connection's current_database() before every command and refuses
 *      anything but class_replacement_testing.
 *
 * Baseline (main runs it ONCE, testing-only): DatabaseSeeder on an EMPTY
 * guarded testing DB — auth 5770/5425/4363 + the profile tables. This spec
 * NEEDS NO TestingFixtureSeeder (its own fixture builds every source grid
 * and request it uses), and main reruns it WITHOUT any fresh/reseed step:
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/cancelled-slot-reuse.spec.ts
 *
 * The spec itself NEVER reseeds, never runs migrate:fresh, never wipes a
 * table: its rebuild is marker-scoped (module CSR901 / venue CRSX1 / two
 * fixture emails), by `seed` at the start of each run.
 *
 * DISCLOSED SEAMS (everything else is a public UI action):
 *   · `seed` cancels the withdraw/reject/auto-reject occurrences through the
 *     app's REAL CancelClass action (CLI) — the approve-lifecycle occurrence
 *     is cancelled through the UI in T1a;
 *   · `seed-pending` (T2/T4) creates the ending-lifecycle pendings through the
 *     REAL SubmitReplacementRequest action (guarded testing-CLI) instead of
 *     re-clicking the submit UI three times;
 *   · `auto-reject` (T5) runs the REAL AutoRejectRequest action with a frozen
 *     PHP clock INSIDE that CLI process only. The scheduled checker (every
 *     5 min) is not changed, not stopped and not run; no UI clock is frozen.
 *   · the PL decision (T1c/T3) is the real Request Approval queue UI — no
 *     direct action call, nothing to disclaim there.
 *
 * ISOLATED FIXTURE: own lecturer + cohort + module + fresh lecture-hall room
 * + ONE recurring source class Tuesday 09:00–11:00 (four 30-minute original
 * cells, created for the full 14-week range). No demo lecturer/cohort busy
 * vectors ride along. Weeks are computed from the REAL 3-working-day notice
 * cutoff (cutoff + 7 calendar days) — no week-8 assumption, no fabricated
 * clock; if the semester cannot offer one, the runner refuses.
 *
 * NOT duplicated here (layout agent owns them):
 *   tests/timetable-lane-layout.spec.ts, tests/cancelled-slot-layout.spec.ts
 *   (full synthetic viewport matrix, theme/print screenshots).
 * ═══════════════════════════════════════════════════════════════════════ */

/* MODULE-FIRST guard — before any test, login or fixture runs. */
abortUnlessTestingTarget('tests/cancelled-slot-reuse.spec.ts', { requireAllowSql: true });

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

/* ONE login per identity per run (Fortify limiter: 5/min per login_id). The
   states live under .auth/ next to the auth.setup project's own files — no
   setup-project file is ever overwritten. */
const FIX_STAFF_STATE = path.join(ROOT, '.auth', 'csr-fix-staff.json');
const FIX_STUDENT_STATE = path.join(ROOT, '.auth', 'csr-fix-student.json');
const PL_STATE = path.join(ROOT, '.auth', 'csr-fix-pl.json');
const OUTSIDER_STATE = path.join(ROOT, '.auth', 'csr-fix-outsider.json');

let F: CsrFixtureReceipt;
let approvedRequestId = 0;   // T1b creates it, T1c consumes it, T7 asserts it

/* ───────────────────────────── shared reads ───────────────────────────── */

interface ServedEvent {
  id: number;
  di: number;
  start: number;
  end: number;
  status: string;
  code: string;
  venue: string;
  requestId: number | null;
  cancelledReason: string | null;
}

/** The SERVED per-week payload (identity + true span, not a DOM lane count). */
async function servedEvents(page: Page, weekIdx: number): Promise<ServedEvent[]> {
  return page.evaluate((w) => (((window as unknown as { eventsData?: ServedEvent[][] }).eventsData ?? [])[w] ?? []).map((e) => ({
    id: e.id,
    di: e.di,
    start: e.start,
    end: e.end,
    status: e.status,
    code: e.code,
    venue: e.venue,
    requestId: e.requestId ?? null,
    cancelledReason: e.cancelledReason ?? null,
  })), weekIdx);
}

interface DomBlock {
  sid: string;
  rid: string | null;
  cls: string;
  tip: string;
  tabIndex: number;
  top: number;
  left: number;
  text: string;
}

async function domBlocks(page: Page): Promise<DomBlock[]> {
  return page.evaluate(() => Array.from(document.querySelectorAll('#tableBody .event-block')).map((b) => {
    const el = b as HTMLElement;
    const r = el.getBoundingClientRect();
    return {
      sid: el.dataset.sid ?? '',
      rid: el.dataset.rid ?? null,
      cls: el.className,
      tip: el.dataset.tip2 ?? '',
      tabIndex: el.tabIndex,
      top: r.top,
      left: r.left,
      text: (el.textContent ?? '').replace(/\s+/g, ' ').trim(),
    };
  }));
}

/** One label/value row of the shared #classModal detail markup. */
async function modalRow(page: Page, label: string): Promise<string | null> {
  return page.evaluate((rowLabel) => {
    const modal = document.getElementById('classModal');
    if (!modal) return null;
    const rows = Array.from(modal.querySelectorAll('.detail-row'));
    const hit = rows.find((r) => ((r.querySelector('.detail-label')?.textContent ?? '').trim()) === rowLabel);
    return hit ? ((hit.querySelector('.detail-value')?.textContent ?? '').trim()) : null;
  }, label);
}

/** One summary-bar card: printed value + its `+N` pending tail. */
async function summaryCard(page: Page, id: string): Promise<{ text: string; tail: string | null } | null> {
  return page.evaluate((cardId) => {
    const el = document.getElementById(cardId);
    if (!el) return null;
    const tail = el.querySelector('.sum-pending');
    return { text: (el.textContent ?? '').trim(), tail: tail ? (tail.textContent ?? '').trim() : null };
  }, id);
}

async function gotoMyTimetableWeek(page: Page, weekIdx: number): Promise<void> {
  await page.goto(`${CSR_BASE}/my-timetable-ui?bust=${Date.now()}`, { waitUntil: 'networkidle', timeout: 60_000 });
  expect(new URL(page.url()).pathname).toBe('/my-timetable-ui');
  await page.locator('#weekSelect').selectOption(String(weekIdx));
  await page.waitForTimeout(1200);
}

/* ═══════════════════════════ fixture + one-login-per-identity ══════════ */

test.describe.configure({ mode: 'serial' });

test.describe('cancelled-slot-reuse A20 — approved/ending lifecycle on the testing lane', () => {
  test.beforeAll(async ({ browser }) => {
    /* pre-write proof: the runner's live current_database() gate (and the TS
       re-check inside each call) — before ANY fixture write */
    csrFixtureTarget();
    F = csrFixtureSeed();

    expect(F.session.id, 'the fixture session id').toBeGreaterThan(0);
    expect(F.weeks.approve.week, 'the approve week stays inside the semester').toBeLessThanOrEqual(14);
    expect(F.weeks.autoReject.week, 'all four lifecycle weeks fit').toBeLessThanOrEqual(14);
    expect(F.weeks.approve.slotIds, 'the approve week carries its four original cells').toHaveLength(4);

    await loginOnce(browser, F.lecturer.staffId, FIX_STAFF_STATE, 'staff');
    await loginOnce(browser, F.student.studentNo, FIX_STUDENT_STATE, 'student');
    /* the PL + an unrelated outside staff — own states, once each */
    await loginOnce(browser, '5425', PL_STATE, 'staff');
    await loginOnce(browser, '5770', OUTSIDER_STATE, 'staff');
  });

  /* ─────────── T1a · the UI cancel releases the original cells ─────────── */

  test('T1a · cancelling the selected class through the UI releases its exact original cells (S05/S09)', async ({ browser }) => {
    test.setTimeout(300_000);
    const { context, page, errors } = await openStaff(browser, FIX_STAFF_STATE);
    const sid = F.session.id;
    const aw = F.weeks.approve;

    await gotoMyTimetableWeek(page, aw.week - 1);

    /* the served truth BEFORE: exactly the fixture class, normal, full range */
    const before = await servedEvents(page, aw.week - 1);
    expect(before).toHaveLength(1);
    expect(before[0]).toMatchObject({
      id: sid, di: F.session.day, start: 2, end: 5,
      status: 'normal', code: F.marker.module, venue: F.marker.venue,
    });

    /* open the popup from the block: no Replace-Now on a normal week (D-P4),
       the Cancel affordance present */
    await page.locator(`#tableBody .event-block[data-sid="${sid}"]`).first().click();
    await expect(page.locator('#classModal')).toBeVisible();
    expect(await page.locator('#btnReplaceNow').isVisible()).toBe(false);
    await expect(page.locator('[data-cancel-class-btn]')).toBeVisible();

    /* cancel through the UI: reason arms the button, the confirm writes */
    await page.locator('[data-cancel-class-btn]').click();
    await expect(page.locator('#cancelClassOverlay')).toHaveClass(/show/);
    await page.locator('#cancelReasonList input[value="medical_leave"]').check();
    await expect(page.locator('#confirmCancelClassBtn')).toBeEnabled();
    await page.locator('#confirmCancelClassBtn').click();
    await expect(page.locator('#cancelClassSuccessState')).toBeVisible({ timeout: 20_000 });
    expect((await page.locator('#cancelClassSummary').textContent()) ?? '').toContain(F.marker.module);
    await page.locator('#cancelLaterBtn').click();          // close + server rebuild
    await page.waitForTimeout(1200);

    /* the block STAYS — red, own target, no request attached (S14/S16) */
    const cancelledEvents = (await servedEvents(page, aw.week - 1)).filter((e) => e.status === 'cancelled');
    expect(cancelledEvents).toHaveLength(1);
    /* the SERVED contract is the raw reason code ('medical_leave' — the
       ReasonLabel display string lives in the popup/modal text layer) */
    expect(cancelledEvents[0]).toMatchObject({
      id: sid, di: F.session.day, start: 2, end: 5,
      requestId: null, cancelledReason: 'medical_leave',
    });
    const blocks = await domBlocks(page);
    const cancelled = blocks.filter((b) => b.cls.includes('event-conflict') && b.sid === String(sid) && b.rid === null);
    expect(cancelled).toHaveLength(1);
    expect(cancelled[0].tip.toUpperCase()).toContain('CANCELLED');
    expect(cancelled[0].tabIndex, 'the block is its own focusable target (S19)').toBe(0);

    /* the server agrees (S01/S05): the exception + the released four cells.
       A released cell loses its original class_session_id, so the four cells
       are asserted by their OCCURRENCE COORDINATES, not by the stale id. */
    expect(sqlScalar(`select reason from class_exceptions where class_session_id=${sid} and week_number=${aw.week}`)).toBe('medical_leave');
    expect(sqlCount(`select count(*) from time_slots where week_number=${aw.week} and venue_id=${F.venue.id}`
      + ` and day_of_week=${F.session.day} and start_time in ('09:00:00','09:30:00','10:00:00','10:30:00')`
      + ` and status='available' and class_session_id is null and version=2`)).toBe(4);
    /* every OTHER week's original cells unchanged (S01) */
    expect(sqlCount(`select count(*) from time_slots where class_session_id=${sid} and status='occupied'`)).toBe(40);
    /* the audit receipt: one class_cancelled row carrying the 4-cell release */
    expect(sqlCount(`select count(*) from audit_logs where action='class_cancelled'`
      + ` and details->>'class_session_id'='${sid}' and details->>'week_number'='${aw.week}'`
      + ` and json_array_length(details->'original_release'->'changed')=4`)).toBe(1);

    /* the summary cards already read the honest zeros: cancelled = 0 classes,
       0 hours, 1 conflict (S22) */
    expect((await summaryCard(page, 'sumTotal'))?.text).toBe('0+0');
    expect((await summaryCard(page, 'sumHours'))?.text).toBe('0+0');
    expect((await summaryCard(page, 'sumPending'))?.text).toBe('0');
    expect((await summaryCard(page, 'sumConflict'))?.text).toBe('1');

    expect(errors, 'no page errors').toEqual([]);
    await context.close();
  });

  /* ─── T1b · re-book the SAME class at the exact original date/time/room ── */

  test('T1b · the same class re-books its exact original date/time/room through the UI — pending full span, two independent blocks (S09/S14/S16/S19/S22)', async ({ browser }) => {
    test.setTimeout(300_000);
    const { context, page, errors } = await openStaff(browser, FIX_STAFF_STATE);
    const sid = F.session.id;
    const aw = F.weeks.approve;
    const day = F.session.day;

    /* the cancelled block's own popup carries the Replace-Now handoff (D-P4) */
    await gotoMyTimetableWeek(page, aw.week - 1);
    await page.locator(`#tableBody .event-block[data-sid="${sid}"]`).first().click();
    await expect(page.locator('#classModal')).toBeVisible();
    await expect(page.locator('#btnReplaceNow')).toBeVisible();
    await page.locator('#btnReplaceNow').click();
    await page.waitForURL(/\/replacement-arrangement/, { timeout: 60_000 });
    expect(new URL(page.url()).pathname).toBe('/replacement-arrangement');
    await page.waitForTimeout(2500);

    /* the arrangement opens on the occurrence; hop to ITS week explicitly.
       The Replace-Now handoff carries no venue param, but the pinned
       occurrence's own room hydrates the selector (BRIDGE PATCH), so the grid
       settles on the original room — assert it before trusting the cells. */
    await page.locator('#weekSelector').selectOption(String(aw.week - 1));
    await page.waitForTimeout(1500);
    await expect(page.locator('#buildingSelectorDropdown .venue-dd-trigger'))
      .toContainText(F.marker.venue, { timeout: 10_000 });

    /* the ORIGINAL cells are plain available — green, selectable, with NO
       cancellation tooltip/state label (S09 client/server agreement, S25) */
    for (const hour of [2, 3, 4, 5]) {
      const cell = page.locator(`#tableBody td[data-day="${day}"][data-hour="${hour}"] .cell-content`);
      await expect(cell).toHaveClass(/cell-available/);
      expect(await page.locator(`#tableBody td[data-day="${day}"][data-hour="${hour}"] .cell-state-label`).count()).toBe(0);
      const tip = await cell.getAttribute('data-tip');
      expect(tip ?? '', 'a freed cell never carries a cancellation note (S25)').not.toMatch(/cancel/i);
    }

    /* pick the exact original range: 09:00–11:00 = 4 of 4 slots */
    await page.locator(`#tableBody td[data-day="${day}"][data-hour="2"] .cell-content`).click();
    await page.waitForTimeout(600);
    await expect(page.locator('#infoTotal')).toContainText('4 of 4 slots');

    /* submit through the page's own confirm modal */
    await page.evaluate(() => (window as unknown as { proceed: () => void }).proceed());
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await expect(page.locator('#confirmModal')).toContainText('Confirm Your Selection');
    await page.locator('#modalConfirmBtn').click();
    await page.waitForURL(/\/my-replacements/, { timeout: 60_000 });

    /* the pending receipt — SQL FIRST, before any pending UI assertion */
    expect(sqlCount(`select count(*) from replacement_requests where class_session_id=${sid}`
      + ` and week_number=${aw.week} and status='pending'`)).toBe(1);
    const requestId = Number(sqlScalar(`select id from replacement_requests where class_session_id=${sid}`
      + ` and week_number=${aw.week} and status='pending' order by id desc limit 1`));
    approvedRequestId = requestId;
    expect(requestId).toBeGreaterThan(0);

    expect(sqlScalar(`select replacement_time_slot_id from replacement_requests where id=${requestId}`))
      .toBe(String(aw.slotIds[0]));
    expect(sqlScalar(`select coalesce(replacement_span::text, 'null') from replacement_requests where id=${requestId}`)).toBe('4');
    expect(sqlCount(`select count(*) from replacement_request_slots where replacement_request_id=${requestId}`)).toBe(4);
    expect(sqlCount(`select count(*) from time_slots ts join replacement_request_slots l on l.time_slot_id=ts.id`
      + ` where l.replacement_request_id=${requestId} and ts.status='pending'`)).toBe(4);

    /* the landing row flashes on My Replacements as Pending */
    await expect(page.locator(`#tableBody tr[data-request-id="${requestId}"] .col-status .badge`))
      .toHaveText('Pending — Waiting for Approval', { timeout: 20_000 });

    /* the SAME occurrence now shows BOTH blocks — cancelled history + the
       pending make-up, independently targeted, full true spans (S14/S16) */
    await gotoMyTimetableWeek(page, aw.week - 1);
    const events = await servedEvents(page, aw.week - 1);
    expect(events.filter((e) => e.status === 'cancelled')).toHaveLength(1);
    const pendingEvents = events.filter((e) => e.status === 'pending');
    expect(pendingEvents).toHaveLength(1);
    expect(pendingEvents[0]).toMatchObject({
      id: sid, di: day, start: 2, end: 5, requestId, venue: F.marker.venue,
    });

    const blocks = await domBlocks(page);
    const cancelled = blocks.filter((b) => b.cls.includes('event-conflict') && b.sid === String(sid) && b.rid === null);
    const pending = blocks.filter((b) => b.rid === String(requestId));
    expect(cancelled).toHaveLength(1);
    expect(pending).toHaveLength(1);
    /* cancelled ABOVE the pending block, same day column (S16, cancelledtop) */
    expect(cancelled[0].top).toBeLessThan(pending[0].top);
    expect(Math.abs(cancelled[0].left - pending[0].left)).toBeLessThan(60);
    /* both are their own focusable target, both carry the TRUE full span */
    expect(cancelled[0].tabIndex).toBe(0);
    expect(pending[0].tabIndex).toBe(0);
    expect(cancelled[0].tip.toUpperCase()).toContain('CANCELLED');
    expect(pending[0].tip.toUpperCase()).toContain('PENDING');
    for (const b of [cancelled[0], pending[0]]) {
      expect(b.text, 'the true 2-hour span, never an anchor-only 30 min').toContain('9:00 AM ~ 11:00 AM');
    }

    /* the cancelled popup opens from ITS block */
    await page.locator(`#tableBody .event-block.event-conflict:not([data-rid])`).first().click();
    await expect(page.locator('#classModal')).toBeVisible();
    expect(await modalRow(page, 'Session ID')).toBe('#' + sid);
    expect(await modalRow(page, 'Status')).toContain('Cancelled');
    expect(await modalRow(page, 'Status Description')).toBe('Class cancelled — a replacement is needed');
    expect(await modalRow(page, 'Time & Duration')).toContain('9:00 AM ~ 11:00 AM');
    await page.keyboard.press('Escape');
    await expect(page.locator('#classModal')).toBeHidden();

    /* …and the pending popup opens independently from ITS block, still with
       the full span (S19 popups independent + true spans) */
    await page.locator(`#tableBody .event-block[data-rid="${requestId}"]`).first().click();
    await expect(page.locator('#classModal')).toBeVisible();
    expect(await modalRow(page, 'Status')).toContain('Pending');
    expect(await modalRow(page, 'Status Description')).toBe('Replacement request awaiting approval');
    expect(await modalRow(page, 'Time & Duration')).toContain('9:00 AM ~ 11:00 AM');
    /* the requester's own facts stay with the owner (S15 contrast in T2) */
    expect(await modalRow(page, 'Requested At'), 'the owner sees the request stamp').not.toBeNull();
    expect(await modalRow(page, 'Requested By')).toBe(F.lecturer.name);
    await page.keyboard.press('Escape');
    await expect(page.locator('#classModal')).toBeHidden();

    /* the pending block also answers its keyboard (S19: Enter opens) */
    await page.locator(`#tableBody .event-block[data-rid="${requestId}"]`).first().focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#classModal')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('#classModal')).toBeHidden();

    /* the cards: cancelled contributes 0 hours, pending counts 2 — not 4
       (S22: never the naive double count) */
    expect((await summaryCard(page, 'sumTotal'))?.text).toBe('0+1');
    const hours = await summaryCard(page, 'sumHours');
    expect(hours?.text).toBe('0+2');
    expect(hours?.tail).toBe('+2');
    expect((await summaryCard(page, 'sumPending'))?.text).toBe('1');

    expect(errors, 'no page errors').toEqual([]);
    await context.close();
  });

  /* ─────────── T1c · the PL approves in the real queue UI ─────────────── */

  test('T1c · the PL approves in the queue UI — landing occupied and never freed, original reason preserved (S11/S14/S16/S22/S23)', async ({ browser }) => {
    test.setTimeout(300_000);
    const sid = F.session.id;
    const aw = F.weeks.approve;
    const day = F.session.day;
    const requestId = approvedRequestId;
    expect(requestId, 'T1b must have created the pending request').toBeGreaterThan(0);

    /* the pending landing versions, for the +1 decision assertion */
    const pendingVersions = sqlScalar(`select string_agg(ts.version::text, ',' order by ts.id) from time_slots ts`
      + ` join replacement_request_slots l on l.time_slot_id=ts.id where l.replacement_request_id=${requestId}`);
    const plUserId = Number(sqlScalar(`select u.id from users u join lecturers l on l.user_id=u.id where l.staff_id='5425'`));

    const { context, page, errors } = await openStaff(browser, PL_STATE);
    await page.goto(`${CSR_BASE}/request-approval-ui?bust=${Date.now()}`, { waitUntil: 'networkidle', timeout: 60_000 });
    expect(new URL(page.url()).pathname).toBe('/request-approval-ui');

    /* the queue row: the Requested Slot cell already reads the FULL span */
    const rowText = await page.evaluate((rid) => {
      const row = Array.from(document.querySelectorAll('#tableBody tr'))
        .find((r) => ((r.children[1]?.textContent ?? '').trim()) === '#' + rid);
      if (!row) return null;
      return {
        replacement: ((row.querySelector('.col-replacement') as HTMLElement | null)?.innerText ?? '').replace(/\s+/g, ' '),
      };
    }, requestId);
    expect(rowText, 'the pending row is on the PL queue').not.toBeNull();
    expect(rowText?.replacement ?? '').toContain('9:00 AM ~ 11:00 AM');
    expect(rowText?.replacement ?? '').toContain(F.marker.venue);

    /* approve through the real UI: row action → notes modal → confirm */
    await page.evaluate((rid) => {
      const row = Array.from(document.querySelectorAll('#tableBody tr'))
        .find((r) => ((r.children[1]?.textContent ?? '').trim()) === '#' + rid);
      const btn = row?.querySelector('.btn-approve') as HTMLElement | null;
      btn?.click();
    }, requestId);
    await expect(page.locator('#approveNotesModal')).toHaveClass(/show/);
    await expect(page.locator('#approveNotesSummary')).toContainText(F.marker.module);
    await page.locator('#confirmApproveBtn').click();
    await page.waitForTimeout(2500);

    /* the queue re-rendered without the row */
    await expect.poll(() => page.evaluate((rid) => Array.from(document.querySelectorAll('#tableBody tr'))
      .some((r) => ((r.children[1]?.textContent ?? '').trim()) === '#' + rid), requestId),
      { timeout: 30_000 }).toBe(false);
    await context.close();
    expect(errors, 'no page errors').toEqual([]);

    /* ── the server truth (S11) ── */
    expect(sqlScalar(`select status from replacement_requests where id=${requestId}`)).toBe('approved');
    expect(sqlScalar(`select coalesce(approver_id::text, 'null') from replacement_requests where id=${requestId}`)).toBe(String(plUserId));
    expect(sqlCount(`select count(*) from replacement_requests where id=${requestId} and decided_at is not null`)).toBe(1);
    /* the junction rows STAY (the live receipt) and the landing cells are
       occupied, +1 version each, still NOT original-owned (never freed) */
    expect(sqlCount(`select count(*) from replacement_request_slots where replacement_request_id=${requestId}`)).toBe(4);
    expect(sqlCount(`select count(*) from time_slots ts join replacement_request_slots l on l.time_slot_id=ts.id`
      + ` where l.replacement_request_id=${requestId} and ts.status='occupied' and ts.class_session_id is null`)).toBe(4);
    const approvedVersions = sqlScalar(`select string_agg(ts.version::text, ',' order by ts.id) from time_slots ts`
      + ` join replacement_request_slots l on l.time_slot_id=ts.id where l.replacement_request_id=${requestId}`);
    expect(approvedVersions).not.toBe(pendingVersions);
    const beforeList = pendingVersions.split(',').map(Number);
    const afterList = approvedVersions.split(',').map(Number);
    expect(afterList.map((v, i) => v - beforeList[i]).every((d) => d === 1), 'every landing cell +1 version').toBe(true);
    /* the ORIGINAL cancellation evidence is preserved, not replaced (S11):
       reason stays medical_leave, the class_cancelled audit stays, and the
       approval freed NOTHING (landing cells had already been released) */
    expect(sqlScalar(`select reason from class_exceptions where class_session_id=${sid} and week_number=${aw.week}`)).toBe('medical_leave');
    expect(sqlCount(`select count(*) from audit_logs where action='class_cancelled'`
      + ` and details->>'class_session_id'='${sid}' and details->>'week_number'='${aw.week}'`
      + ` and json_array_length(details->'original_release'->'changed')=4`)).toBe(1);
    expect(sqlCount(`select count(*) from audit_logs where action='approved' and replacement_request_id=${requestId}`
      + ` and json_array_length(details->'original_release'->'freed_slots')=0`
      + ` and json_array_length(details->'slots')=4`)).toBe(1);

    /* ── the staff timetable: cancellation ABOVE the approved block (S16).
       This is the frozen cancelled-slot-reuse target — it deliberately
       supersedes the older cancelled-blocks-stay-visible suppression-on-
       approval for an overlapping position; it stays the gating assertion
       until the A14–A17 stack ships. ── */
    const { context: ctx2, page: page2, errors: errors2 } = await openStaff(browser, FIX_STAFF_STATE);
    await gotoMyTimetableWeek(page2, aw.week - 1);
    const events = await servedEvents(page2, aw.week - 1);
    expect(events.filter((e) => e.status === 'cancelled')).toHaveLength(1);
    const replacementEvents = events.filter((e) => e.status === 'replacement');
    expect(replacementEvents).toHaveLength(1);
    expect(replacementEvents[0]).toMatchObject({
      id: sid, di: day, start: 2, end: 5, requestId, venue: F.marker.venue,
    });

    const blocks = await domBlocks(page2);
    const cancelled = blocks.filter((b) => b.cls.includes('event-conflict') && b.sid === String(sid) && b.rid === null);
    const approved = blocks.filter((b) => b.rid === String(requestId));
    expect(cancelled).toHaveLength(1);
    expect(approved).toHaveLength(1);
    expect(approved[0].cls).toContain('event-replacement');
    expect(cancelled[0].top).toBeLessThan(approved[0].top);
    expect(Math.abs(cancelled[0].left - approved[0].left)).toBeLessThan(60);
    expect(approved[0].tip.toUpperCase()).toContain('REPLACEMENT');
    for (const b of [cancelled[0], approved[0]]) {
      expect(b.text).toContain('9:00 AM ~ 11:00 AM');
    }

    /* the approved popup: its own landing facts + the ORIGINAL rows (S14) */
    await page2.locator(`#tableBody .event-block[data-rid="${requestId}"]`).first().click();
    await expect(page2.locator('#classModal')).toBeVisible();
    expect(await modalRow(page2, 'Status')).toContain('Replacement');
    expect(await modalRow(page2, 'Time & Duration')).toContain('9:00 AM ~ 11:00 AM');
    expect(await modalRow(page2, 'Original Venue')).toBe(F.marker.venue);
    expect(await modalRow(page2, 'Original Start Time')).toBe('9:00 AM');
    expect(await modalRow(page2, 'Original End Time')).toBe('11:00 AM');
    expect(await modalRow(page2, 'Original Date'), 'the original occurrence date').not.toBeNull();
    await page2.keyboard.press('Escape');
    await expect(page2.locator('#classModal')).toBeHidden();

    /* the cards after approval: 2 confirmed hours, cancelled still 0, the
       landing counted as the replacement (S22) */
    expect((await summaryCard(page2, 'sumTotal'))?.text).toBe('1+0');
    expect((await summaryCard(page2, 'sumHours'))?.text).toBe('2+0');
    expect((await summaryCard(page2, 'sumReplacement'))?.text).toBe('1');
    expect((await summaryCard(page2, 'sumPending'))?.text).toBe('0');

    /* the venue cards: the union of REAL cells is 2 teaching hours at this
       room — cancelled history and any base rows contribute nothing (S22/S23,
       never the naive 4) */
    await page2.goto(`${CSR_BASE}/venue-timetable-ui?venue=${F.marker.venue}&bust=${Date.now()}`,
      { waitUntil: 'networkidle', timeout: 60_000 });
    expect(new URL(page2.url()).pathname).toBe('/venue-timetable-ui');
    await page2.locator('#weekSelect').selectOption({ index: aw.week - 1 });
    await page2.waitForTimeout(1200);
    expect((await summaryCard(page2, 'sumMyHours'))?.text).toBe('2+0');
    expect((await summaryCard(page2, 'sumMyClasses'))?.text).toBe('1+0');
    expect((await summaryCard(page2, 'sumOccupied'))?.text).toBe('4+0');

    expect(errors2, 'no page errors').toEqual([]);
    await ctx2.close();
  });

  /* ── T2 · unrelated staff get no private request facts (S15) ─────────── */

  test('T2 · unrelated staff read the fixture pending block without private request facts (S15)', async ({ browser }) => {
    test.setTimeout(300_000);
    /* seam disclosure: the pending is seeded through the REAL
       SubmitReplacementRequest action (guarded testing-CLI), not the UI */
    const seeded = csrSeedPending(F.weeks.reject.week);
    expect(seeded.junctionCount).toBe(4);
    expect(sqlScalar(`select status from replacement_requests where id=${seeded.requestId}`)).toBe('pending');
    expect(sqlScalar(`select coalesce(replacement_span::text, 'null') from replacement_requests where id=${seeded.requestId}`)).toBe('4');

    /* the OUTSIDER: the block itself is public, the request facts are not */
    const { context, page, errors } = await openStaff(browser, OUTSIDER_STATE);
    await page.goto(`${CSR_BASE}/venue-timetable-ui?venue=${F.marker.venue}&bust=${Date.now()}`,
      { waitUntil: 'networkidle', timeout: 60_000 });
    expect(new URL(page.url()).pathname).toBe('/venue-timetable-ui');
    await page.locator('#weekSelect').selectOption({ index: F.weeks.reject.week - 1 });
    await page.waitForTimeout(1200);

    /* stable identity first: the pending block carries the request id from
       the seed-pending receipt (the label suffix is the SESSION type L, not
       a pending marker — assert the code and the true type separately) */
    const amber = page.locator(`#tableBody .event-block[data-rid="${seeded.requestId}"]`);
    await expect(amber).toHaveCount(1);
    const amberText = (await amber.textContent()) ?? '';
    expect(amberText, 'the fixture course code').toContain(F.marker.module);
    expect(amberText, 'the fixture session type is L, never an invented (P)').toContain(`(${F.session.type})`);
    expect(amberText).toMatch(/9:00 AM [~-] 11:00 AM/);
    await amber.first().click();
    await expect(page.locator('#classModal')).toBeVisible();
    expect(await modalRow(page, 'Status')).toContain('Pending');
    expect(await modalRow(page, 'Lecturer')).toBe(F.lecturer.name);
    expect(await modalRow(page, 'Requested At'), 'no request stamp for other staff').toBeNull();
    expect(await modalRow(page, 'Requested By'), 'no requester name for other staff').toBeNull();
    expect(await page.locator('#classModal .modal-footer a.btn-action', { hasText: 'View Full Request' }).count(),
      'no full-request action for other staff').toBe(0);
    await page.locator('#classModal .btn-close-modal').click();
    await expect(page.locator('#classModal')).toBeHidden();
    await context.close();
    expect(errors, 'no page errors').toEqual([]);

    /* the OWNER contrast: the same block carries the stamp + the action */
    const { context: ctx2, page: page2, errors: errors2 } = await openStaff(browser, FIX_STAFF_STATE);
    await page2.goto(`${CSR_BASE}/venue-timetable-ui?venue=${F.marker.venue}&bust=${Date.now()}`,
      { waitUntil: 'networkidle', timeout: 60_000 });
    await page2.locator('#weekSelect').selectOption({ index: F.weeks.reject.week - 1 });
    await page2.waitForTimeout(1200);
    await page2.locator(`#tableBody .event-block[data-rid="${seeded.requestId}"]`).first().click();
    await expect(page2.locator('#classModal')).toBeVisible();
    expect(await modalRow(page2, 'Requested At'), 'the owner still sees the stamp').not.toBeNull();
    expect(await modalRow(page2, 'Requested By')).toBe(F.lecturer.name);
    expect(await page2.locator('#classModal .modal-footer a.btn-action', { hasText: 'View Full Request' }).count(),
      'the owner gets the full-request action').toBe(1);
    await page2.locator('#classModal .btn-close-modal').click();
    await ctx2.close();
    expect(errors2, 'no page errors').toEqual([]);
  });

  /* ── T3 · the PL rejects — active block gone, history stays, cells free ── */

  test('T3 · the PL rejects week C in the queue UI — active block disappears, cancellation history stays, cells released (S16)', async ({ browser }) => {
    test.setTimeout(300_000);
    const sid = F.session.id;
    const rw = F.weeks.reject;
    const requestId = Number(sqlScalar(`select id from replacement_requests where class_session_id=${sid}`
      + ` and week_number=${rw.week} and status='pending' order by id desc limit 1`));
    expect(requestId, 'T2 seeded the pending').toBeGreaterThan(0);

    const { context, page, errors } = await openStaff(browser, PL_STATE);
    await page.goto(`${CSR_BASE}/request-approval-ui?bust=${Date.now()}`, { waitUntil: 'networkidle', timeout: 60_000 });
    await page.evaluate((rid) => {
      const row = Array.from(document.querySelectorAll('#tableBody tr'))
        .find((r) => ((r.children[1]?.textContent ?? '').trim()) === '#' + rid);
      const btn = row?.querySelector('.btn-reject') as HTMLElement | null;
      btn?.click();
    }, requestId);
    await expect(page.locator('#rejectReasonModal')).toHaveClass(/show/);
    await expect(page.locator('#rejectReasonTitle')).toHaveText('Rejection Reason (#' + requestId + ')');
    await expect(page.locator('#confirmRejectBtn')).toBeDisabled();
    await page.locator('#rejectReasonInput').fill('Slot conflict — the room was rebooked for a seminar.');
    await expect(page.locator('#confirmRejectBtn')).toBeEnabled();
    await page.locator('#confirmRejectBtn').click();
    /* the queue re-rendered without the row (the Livewire round-trip's receipt) */
    await expect.poll(() => page.evaluate((rid) => Array.from(document.querySelectorAll('#tableBody tr'))
      .some((r) => ((r.children[1]?.textContent ?? '').trim()) === '#' + rid), requestId),
      { timeout: 30_000 }).toBe(false);
    await context.close();
    expect(errors, 'no page errors').toEqual([]);

    /* the server truth: rejected by the PL, junction gone, cells released
       (asserted by the occurrence's coordinates — released cells carry no id) */
    expect(sqlScalar(`select status from replacement_requests where id=${requestId}`)).toBe('rejected');
    expect(sqlCount(`select count(*) from replacement_request_slots where replacement_request_id=${requestId}`)).toBe(0);
    expect(sqlCount(`select count(*) from time_slots where week_number=${rw.week} and venue_id=${F.venue.id}`
      + ` and day_of_week=${F.session.day} and start_time in ('09:00:00','09:30:00','10:00:00','10:30:00')`
      + ` and status='available'`)).toBe(4);
    expect(sqlCount(`select count(*) from audit_logs where action='rejected' and replacement_request_id=${requestId}`
      + ` and details->>'decision'='rejected' and details->>'auto' is null`)).toBeGreaterThan(0);

    /* the lecturer's page: the badge reads Rejected; the occurrence is back
       to "arrange again" territory with the cancelled history intact */
    const { context: ctx2, page: page2, errors: errors2 } = await openStaff(browser, FIX_STAFF_STATE);
    await page2.goto(`${CSR_BASE}/my-replacements?bust=${Date.now()}`, { waitUntil: 'networkidle', timeout: 60_000 });
    expect(new URL(page2.url()).pathname).toBe('/my-replacements');
    await expect(page2.locator(`#tableBody tr[data-request-id="${requestId}"] .col-status .badge`))
      .toHaveText('Rejected', { timeout: 20_000 });

    await gotoMyTimetableWeek(page2, rw.week - 1);
    const events = await servedEvents(page2, rw.week - 1);
    expect(events.filter((e) => e.status === 'cancelled')).toHaveLength(1);
    expect(events.filter((e) => e.status === 'pending' || e.status === 'replacement')).toHaveLength(0);
    const blocks = await domBlocks(page2);
    expect(blocks.filter((b) => b.rid !== null && b.text.includes(F.marker.module))).toHaveLength(0);
    expect(blocks.filter((b) => b.cls.includes('event-conflict') && b.sid === String(sid))).toHaveLength(1);
    expect((await summaryCard(page2, 'sumHours'))?.text).toBe('0+0');
    await ctx2.close();
    expect(errors2, 'no page errors').toEqual([]);
  });

  /* ── T4 · the lecturer withdraws — "Need to Arrange", history stays ───── */

  test('T4 · the lecturer withdraws week B through My Replacements — display becomes Need to Arrange, the record stays Cancelled, cells released (S16)', async ({ browser }) => {
    test.setTimeout(300_000);
    /* seam disclosure: the pending is seeded through the REAL
       SubmitReplacementRequest action (guarded testing-CLI) */
    const seeded = csrSeedPending(F.weeks.withdraw.week);
    expect(sqlScalar(`select status from replacement_requests where id=${seeded.requestId}`)).toBe('pending');
    const requestId = seeded.requestId;
    const sid = F.session.id;
    const ww = F.weeks.withdraw;

    const { context, page, errors } = await openStaff(browser, FIX_STAFF_STATE);
    await page.goto(`${CSR_BASE}/my-replacements?bust=${Date.now()}`, { waitUntil: 'networkidle', timeout: 60_000 });
    const row = page.locator(`#tableBody tr[data-request-id="${requestId}"]`);
    await expect(row.locator('.col-status .badge')).toHaveText('Pending — Waiting for Approval', { timeout: 20_000 });

    /* withdraw through the row's ban icon and the frozen confirm modal */
    await row.locator('.col-action .btn-icon--danger').click();
    await expect(page.locator('#cancelConfirmOverlay')).toBeVisible({ timeout: 5_000 });
    await expect(page.locator('#cancelConfirmBody')).toContainText('Are you sure you want to CANCEL this Replacement Request?');
    await expect(page.locator('#cancelConfirmBody')).toContainText('This action cannot be UNDONE.');
    await page.locator('#confirmCancelAction').click();

    /* the DISPLAY becomes no-active-request; the RECORD stays cancelled */
    await expect(row.locator('.col-status .badge')).toHaveText('Need to Arrange', { timeout: 20_000 });
    await expect(row.locator('.col-status .badge')).toHaveClass(/badge-outline/);
    await expect(row.locator('.col-status .badge')).toHaveClass(/status-none/);
    await expect(row.locator('.col-requested .cell-dash')).toHaveText('—');
    await context.close();
    expect(errors, 'no page errors').toEqual([]);

    expect(sqlScalar(`select status from replacement_requests where id=${requestId}`)).toBe('cancelled');
    expect(sqlCount(`select count(*) from replacement_request_slots where replacement_request_id=${requestId}`)).toBe(0);
    expect(sqlCount(`select count(*) from time_slots where week_number=${ww.week} and venue_id=${F.venue.id}`
      + ` and day_of_week=${F.session.day} and start_time in ('09:00:00','09:30:00','10:00:00','10:30:00')`
      + ` and status='available'`)).toBe(4);
    expect(sqlCount(`select count(*) from audit_logs where action='cancelled' and replacement_request_id=${requestId}`)).toBeGreaterThan(0);

    /* the timetable: the active block disappeared, the cancellation history
       stays, the released cells are plain again */
    const { context: ctx2, page: page2, errors: errors2 } = await openStaff(browser, FIX_STAFF_STATE);
    await gotoMyTimetableWeek(page2, ww.week - 1);
    const events = await servedEvents(page2, ww.week - 1);
    expect(events.filter((e) => e.status === 'cancelled')).toHaveLength(1);
    expect(events.filter((e) => e.status === 'pending' || e.status === 'replacement')).toHaveLength(0);
    const blocks = await domBlocks(page2);
    expect(blocks.filter((b) => b.rid !== null && b.text.includes(F.marker.module))).toHaveLength(0);
    expect(blocks.filter((b) => b.cls.includes('event-conflict') && b.sid === String(sid))).toHaveLength(1);
    expect((await summaryCard(page2, 'sumHours'))?.text).toBe('0+0');
    await ctx2.close();
    expect(errors2, 'no page errors').toEqual([]);
  });

  /* ── T5 · one real 390px flow, then the REAL auto-reject leg ──────────── */

  test('T5 · 390px real flow books week D, then the REAL AutoRejectRequest action (frozen CLI clock) rejects it (S16)', async ({ browser }) => {
    test.setTimeout(360_000);
    const sid = F.session.id;
    const dw = F.weeks.autoReject;
    const day = F.session.day;

    /* the 390px REAL flow: the mobile day view books the exact original range */
    const { context, page, errors } = await openStaff(browser, FIX_STAFF_STATE, { width: 390, height: 844 });
    await page.goto(`${CSR_BASE}/replacement-arrangement?venue=${F.marker.venue}&session=${sid}&week=${dw.week}`
      + `&code=${F.marker.module}&cohort=${encodeURIComponent(F.cohort.spaced)}&from=my-timetable&bust=${Date.now()}`,
      { waitUntil: 'domcontentloaded', timeout: 60_000 });
    await page.waitForTimeout(2500);
    expect(new URL(page.url()).pathname).toBe('/replacement-arrangement');
    await expect(page.locator('#arrangementMobile')).toBeAttached();

    /* the mobile view defaults to the first-bookable week, not the URL's
       target week — hop to the auto-reject occurrence's own week through the
       real week selector (the same relocated control T1b uses) */
    await page.locator('#weekSelector').selectOption(String(dw.week - 1));
    await page.waitForTimeout(1500);

    await page.locator('[data-arrangement-mobile-days] button[data-day-index="' + day + '"]').click();
    await page.waitForTimeout(800);
    /* the visible mobile timeline row for the 09:00 canonical hour — the
       delegated tap routes an available row through the page's canonical
       toggleCell path (no hidden desktop grid click) */
    await page.locator('[data-arrangement-mobile-timeline] button[data-mobile-row="hour"][data-mobile-hour="2"]').click();
    await page.waitForTimeout(500);
    await expect(page.locator('[data-mobile-row="span"]')).toHaveCount(1);

    /* submit through the relocated original Submit control (its own proceed) */
    await page.locator('#submitRequestBtn').click();
    await expect(page.locator('#confirmModal')).toBeVisible({ timeout: 10_000 });
    await page.locator('#modalConfirmBtn').click();
    await page.waitForURL(/\/my-replacements/, { timeout: 60_000 });
    await context.close();
    expect(errors, 'no page errors').toEqual([]);

    /* SQL: pending, full span, junction rows — verified before the sweep */
    expect(sqlCount(`select count(*) from replacement_requests where class_session_id=${sid}`
      + ` and week_number=${dw.week} and status='pending'`)).toBe(1);
    const requestId = Number(sqlScalar(`select id from replacement_requests where class_session_id=${sid}`
      + ` and week_number=${dw.week} and status='pending' order by id desc limit 1`));
    expect(requestId).toBeGreaterThan(0);
    expect(sqlCount(`select count(*) from replacement_request_slots where replacement_request_id=${requestId}`)).toBe(4);

    /* SEAM (disclosed): the REAL AutoRejectRequest action with a frozen PHP
       clock INSIDE the guarded CLI only — Tuesday 08:00 MYT is past the
       proposed start's 3-working-hour deadline, so the fixture is DUE. The
       scheduled checker (every 5 min) is untouched; no UI clock is frozen. */
    const ar = csrAutoReject(requestId, `${dw.date} 08:00`);
    expect(ar.ok).toBe(true);
    expect(ar.status).toBe('rejected');
    expect(ar.approverId).toBeNull();
    expect(ar.rejectionReason).toContain('Automatically rejected');
    expect(ar.rejectionReason).toContain('3 working hours');

    expect(sqlScalar(`select coalesce(approver_id::text, 'null') from replacement_requests where id=${requestId}`)).toBe('null');
    expect(sqlCount(`select count(*) from replacement_request_slots where replacement_request_id=${requestId}`)).toBe(0);
    expect(sqlCount(`select count(*) from time_slots where week_number=${dw.week} and venue_id=${F.venue.id}`
      + ` and day_of_week=${F.session.day} and start_time in ('09:00:00','09:30:00','10:00:00','10:30:00')`
      + ` and status='available'`)).toBe(4);
    expect(sqlCount(`select count(*) from audit_logs where action='rejected' and replacement_request_id=${requestId}`
      + ` and details->>'auto'='true' and user_id=${F.lecturer.userId}`)).toBeGreaterThan(0);

    /* the display aftermath on the normal viewport */
    const { context: ctx2, page: page2, errors: errors2 } = await openStaff(browser, FIX_STAFF_STATE);
    await page2.goto(`${CSR_BASE}/my-replacements?bust=${Date.now()}`, { waitUntil: 'networkidle', timeout: 60_000 });
    await expect(page2.locator(`#tableBody tr[data-request-id="${requestId}"] .col-status .badge`))
      .toHaveText('Rejected', { timeout: 20_000 });

    await gotoMyTimetableWeek(page2, dw.week - 1);
    const events = await servedEvents(page2, dw.week - 1);
    expect(events.filter((e) => e.status === 'cancelled')).toHaveLength(1);
    expect(events.filter((e) => e.status === 'pending' || e.status === 'replacement')).toHaveLength(0);
    const blocks = await domBlocks(page2);
    expect(blocks.filter((b) => b.rid !== null && b.text.includes(F.marker.module))).toHaveLength(0);
    expect(blocks.filter((b) => b.cls.includes('event-conflict') && b.sid === String(sid))).toHaveLength(1);
    await ctx2.close();
    expect(errors2, 'no page errors').toEqual([]);
  });

  /* ── T7 · the student payload: own cohort only, no private fields (S15) ── */

  test('T7 · the student payload holds only the own-cohort records and no private field (S15)', async ({ browser }) => {
    test.setTimeout(240_000);
    const { context, page, errors } = await openStaff(browser, FIX_STUDENT_STATE);
    await page.goto(`${CSR_BASE}/student-class-replacements?bust=${Date.now()}`,
      { waitUntil: 'networkidle', timeout: 60_000 });
    expect(new URL(page.url()).pathname).toBe('/student-class-replacements');

    const rows = await page.evaluate(() =>
      (window as unknown as { PAGE_DATA?: { replacementHistory?: Array<Record<string, unknown>> } })
        .PAGE_DATA?.replacementHistory ?? []);

    /* the isolated cohort sees ONLY the fixture class's four cancelled
       occurrences (approve/withdraw/reject/auto-reject weeks) — no unrelated
       record can appear */
    expect(rows, 'exactly the four fixture occurrences').toHaveLength(4);
    for (const row of rows) {
      expect(row.code).toBe(F.marker.module);
      expect(row.cohorts).toEqual([F.cohort.spaced]);
      /* a leave type never leaves the server method (S15) */
      expect(row.conflictKind).toBe('Class Cancelled');
      expect(Object.keys(row), 'no private fields on the student payload').not.toContain('remarks');
      expect(Object.keys(row)).not.toContain('rejectionReason');
    }
    const serialized = JSON.stringify(rows);
    expect(serialized).not.toContain('medical_leave');
    expect(serialized).not.toContain('Medical/Sick Leave');
    expect(serialized).not.toContain('official_event');
    expect(serialized, 'no staff free-text on the student payload').not.toContain('seminar');

    /* the approved week reads Approved and points at the approved request */
    const approvedRow = rows.find((r) => (r.orig as { date?: string } | undefined)?.date === F.weeks.approve.date);
    expect(approvedRow).toBeDefined();
    expect(approvedRow?.status).toBe('approved');
    expect(approvedRow?.requestId).toBe(approvedRequestId);

    /* read-only apart from the status toggle: no decision action anywhere.
       Scoped to the request surfaces ONLY — desktop rows (#upcomingBody), phone
       cards (#upcomingList) and the detail modal (#classModal). The shared bell
       island / notification panel renders "Scheduled Class Cancelled" inbox
       links that navigate to this page; they are notice links, not request
       actions, so they must not fail this check. */
    const actionWords = await page.evaluate(() => Array.from(document.querySelectorAll(
      '#upcomingBody button, #upcomingBody a, '
      + '#upcomingList button, #upcomingList a, '
      + '#classModal button, #classModal a'))
      .map((b) => (b.textContent ?? '').trim())
      .filter((t) => /approve|reject|cancel/i.test(t)));
    expect(actionWords, 'the student page never acts on a request').toEqual([]);

    expect(errors, 'no page errors').toEqual([]);
    await context.close();
  });
});
