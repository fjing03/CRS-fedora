import { test, expect, type Page, type TestInfo } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';
import { csrFixtureSeed, csrSeedPending } from './helpers/cancelled-slot-reuse-fixture';

/* ═══════════════════════════════════════════════════════════════════════
 * cancelled-slot-reuse — Part B BROWSER RECEIPTS (B03) — room-only holders.
 * Frozen spec: .sdd/changes/cancelled-slot-reuse/specs/cancelled-occurrence-
 * reuse/spec.md — R7 S24/S25.
 *
 * It WRITES on the disposable testing DB only (the pending request + its
 * junction rows + the covered slot flips the REAL action produces, restored
 * in afterAll), so it is guarded module-first with the EXACT blessed
 * strings:
 *
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/cancelled-slot-room-holder.spec.ts
 *
 * The testing DB is prepared by the main session ONLY when it is empty
 * (plain `php artisan migrate` + `php artisan db:seed` against
 * DB_DATABASE=class_replacement_testing). This spec NEVER reseeds, never
 * migrate:fresh, and never resets sequences or schema.
 *
 * THE FIXTURE IS THE GUARDED CSR RUNNER — one isolated path, no fallback:
 *   · tests/helpers/cancelled-slot-reuse-fixture.ts → csr-fixture-runner.php
 *     (`seed` + `seed-pending`) builds an isolated source/cohort/venue and
 *     claims the four Tuesday 09:00–11:00 anchor cells through the REAL
 *     SubmitReplacementRequest action — truthful metadata by construction
 *     (real T2h source, isolated cohort, isolated room; no invented booking
 *     rule, no fake source);
 *   · the VIEWER is staff 5425 and the selection stays EMPTY. Cohort
 *     disjointness is proven BEFORE the claim: the CSR cohort is disjoint
 *     from ALL cohorts the viewer teaches, and the fixture lecturer is NOT
 *     the viewer. With no course selected the served grid applies ONLY the
 *     viewer's OWN calendar — busyByWeek key '' is exactly
 *     MatrixIntersectionEngine::lecturerBusyRanges (own classes, weeks an
 *     exception frees excluded, plus the viewer's own pending/approved
 *     make-ups). Unrelated classes belonging to a DIFFERENT viewer course
 *     must NOT disqualify a cell, so csrViewerFreeCells mirrors that exact
 *     own-busy contract in SQL — available + ownerless FULL four cells, no
 *     own lecturer class, no own make-up in the anchor week, no own live
 *     claim — and never consults the cohort vectors;
 *   · the anchor is a candidate withdraw/reject/auto-reject week whose four
 *     cells are ACTUALLY available (the approve week is occupied and skips)
 *     and strictly future — the 5-minute checker can never reach these
 *     pending rows mid-run;
 *   · if even that cannot hold, the run FAILS LOUDLY — the fixture is never
 *     weakened.
 *
 * DISCLOSED SEAMS (nothing else is invented):
 *   · the CSR pending comes from the REAL action (its own guard chain); the
 *     isolated marker-scoped fixture rows it leaves behind are cleaned by
 *     the runner's next `seed` — the same seam the cancelled-slot-reuse
 *     spec discloses;
 *   · this spec's OWN tracked post-state (the four flipped slot versions +
 *     junction ids, registered row by row AFTER the real claim and verified
 *     against what the action actually wrote) is restored by the scoped
 *     atomic cleanup: ONE locked DO block re-verifies every tracked row
 *     (exact flipped/original state, ownerless, own junction only, no
 *     foreign live claim by anchor equality or span intersection) before
 *     deleting this spec's junction/audit/notification/request rows and
 *     monotonic-restoring each flipped slot — any drift RAISEs and the
 *     whole block rolls back, so a newer live holder is never erased;
 *   · a SYNTHETIC page-payload stage (disclosed, DB never mutated):
 *     window.MockData payloads reshaped in the page and re-rendered — the
 *     presentation layer (merge, escaping, themes, eligibility, the mobile
 *     sheet) receipted deterministically. The feature file
 *     tests/Feature/CancelledSlotRoomHolderTest.php carries the ACTUAL
 *     backend payload proof. No full-E2E claim is made beyond these
 *     disclosed seams.
 * ═══════════════════════════════════════════════════════════════════════ */

abortUnlessTestingTarget('tests/cancelled-slot-room-holder.spec.ts', { requireAllowSql: true });

const BASE = process.env.CRS_BASE as string;
const VIEWER_STAFF = '5425';
const FIXTURE_MARK = 'RHH room-holder receipt';

/* NO shell JSON quoting — the argument array passes the SQL verbatim. */
function sql(query: string): string {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  return execFileSync('psql',
    ['-X', '-q', '-t', '-v', 'ON_ERROR_STOP=1', '-U', 'philler', '-d', 'class_replacement_testing', '-c', oneLine],
    { encoding: 'utf8' }).trim();
}

/** EVERY fixture write (and restore) checks the ACTUAL connection first. */
function assertTestingDb(): void {
  const db = sql('SELECT current_database()');
  if (db !== 'class_replacement_testing') {
    throw new Error(`fixture write REFUSED: connected to [${db}], only class_replacement_testing may be written`);
  }
}

interface Semester { id: number; startDate: string; weeks: number }

/** Mirror of Semester::active() — the id is discovered, never assumed. */
function discoverSemester(): Semester {
  const campus = sql("SELECT to_char((now() AT TIME ZONE 'Asia/Kuala_Lumpur')::date, 'YYYY-MM-DD')");
  let row = sql(`SELECT id, start_date::text, week_count FROM semesters
                  WHERE start_date <= '${campus}' ORDER BY start_date DESC, id ASC LIMIT 1`);
  if (!row) row = sql('SELECT id, start_date::text, week_count FROM semesters WHERE id = 1');
  if (!row) row = sql('SELECT id, start_date::text, week_count FROM semesters ORDER BY start_date DESC, id ASC LIMIT 1');
  if (!row) throw new Error('no semester row exists — the fixture has no timeline to live on');
  const [id, startDate, weeks] = row.split('|').map((x) => x.trim());
  return { id: Number(id), startDate, weeks: Number(weeks) };
}

const toMinutes = (t: string): number => Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));

/** `2:00 PM` — the shared 12-hour clock, from minutes (carry-hour safe). */
function twelveHour(minutes: number): string {
  const m = ((minutes % 1440) + 1440) % 1440;
  const h24 = Math.floor(m / 60);
  const min = m % 60;
  const h12 = h24 % 12 === 0 ? 12 : h24 % 12;
  return `${h12}:${String(min).padStart(2, '0')} ${h24 < 12 ? 'AM' : 'PM'}`;
}

/** `2:00 PM ~ 3:00 PM` — the TRUE full span: first cell start to
 *  LAST-cell-start + 30 minutes (carry-hour safe), the joiner the page
 *  prints on every covered cell. */
function spanTextFor(starts: string[]): string {
  const first = toMinutes(starts[0]);
  const lastEnd = toMinutes(starts[starts.length - 1]) + 30;
  return `${twelveHour(first)} ~ ${twelveHour(lastEnd)}`;
}

interface FreeSlot { id: number; start: string; day: number; room: string; week: number }

/** The viewer's OWN freeness arms as one SQL fragment (any alias) — the
 *  EXACT mirror of the served grid's active calendar for an EMPTY subject
 *  selection (busyByWeek key '' = MatrixIntersectionEngine::lecturerBusy-
 *  Ranges): the viewer's own lecturer classes (weeks a class exception
 *  frees excluded — the same week filter the engine applies) and the
 *  viewer's own make-ups (pending AND approved rr rows joined through the
 *  rr chain, the engine's double filter). The COHORT vectors are never
 *  consulted: with no course selected no cohort calendar is applied, so
 *  unrelated classes of a DIFFERENT viewer course must not disqualify a
 *  cell. The viewer's own anchor-claim span is tracked separately (a claim
 *  held at another venue covers its whole span, wider than the slot rows).
 *  The `available + ownerless` base condition is added by the caller. */
function ownFreeArms(alias: string, viewerUserId: number): string {
  return `
   NOT EXISTS (SELECT 1 FROM class_sessions csl
                WHERE csl.semester_id = ${alias}.semester_id
                  AND csl.lecturer_id = ${viewerUserId}
                  AND csl.day_of_week = ${alias}.day_of_week
                  AND ${alias}.start_time >= csl.start_time AND ${alias}.start_time < csl.end_time
                  AND NOT EXISTS (SELECT 1 FROM class_exceptions ce
                                   WHERE ce.class_session_id = csl.id
                                     AND ce.week_number = ${alias}.week_number))
   AND NOT EXISTS (SELECT 1 FROM time_slots ms
                    JOIN replacement_request_slots mrs ON mrs.time_slot_id = ms.id
                    JOIN replacement_requests mr ON mr.id = mrs.replacement_request_id
                    JOIN class_sessions mcs ON mcs.id = mr.class_session_id
                   WHERE ms.status IN ('pending', 'occupied')
                     AND ms.semester_id = ${alias}.semester_id AND ms.week_number = ${alias}.week_number
                     AND mr.status IN ('pending', 'approved')
                     AND mcs.lecturer_id = ${viewerUserId}
                     AND ms.day_of_week = ${alias}.day_of_week
                     AND ${alias}.start_time >= ms.start_time AND ${alias}.start_time < ms.end_time)
   AND NOT EXISTS (SELECT 1 FROM replacement_requests r
                    JOIN time_slots a ON a.id = r.replacement_time_slot_id
                   WHERE r.proposer_id = ${viewerUserId}
                     AND r.status IN ('pending', 'approved')
                     AND (a.id = ${alias}.id
                       OR (a.semester_id = ${alias}.semester_id AND a.week_number = ${alias}.week_number
                           AND a.day_of_week = ${alias}.day_of_week AND a.venue_id = ${alias}.venue_id
                           AND ${alias}.start_time >= a.start_time
                           AND ${alias}.start_time < a.start_time + COALESCE(NULLIF(r.replacement_span, 0), 1) * interval '30 minutes')))`;
}

/* ─── everything this spec created — cleaned up in afterAll, never a sweep ── */

interface TrackedSlot { id: number; requestId: number; originalVersion: number; flippedVersion: number }
const created: { requests: number[]; slots: TrackedSlot[] } = { requests: [], slots: [] };

/** Full-set cleanup in ONE atomic DO block. It row-locks the registered
 *  requests then the slot ids (each sorted), re-verifies inside that same
 *  transaction every tracked row's exact original/flipped ownerless shape
 *  and that no foreign live claim exists (anchor equality AND fallback-span
 *  intersection — same semester/week/day/venue, row start inside anchor start
 *  + span, own requests excluded), then deletes only this spec's junction,
 *  audit, notification and request rows and monotonic-restores every slot it
 *  actually flipped (matched count verified via GET DIAGNOSTICS). Any drift
 *  or mismatch RAISEs, so the whole block rolls back — a newer live holder is
 *  never freed silently and no partial cleanup is ever committed. */
function cleanupFixture(): void {
  if (created.requests.length === 0 && created.slots.length === 0) return;
  assertTestingDb();

  const ownIds = created.requests.join(',');
  const slotIds = created.slots.map((s) => s.id).sort((a, b) => a - b).join(',');
  const slotLock = slotIds
    ? `PERFORM 1 FROM time_slots WHERE id IN (${slotIds}) ORDER BY id FOR UPDATE;`
    : '';

  const requestGuards = created.requests.map((id) => `
    IF (SELECT status FROM replacement_requests WHERE id = ${id}) IS DISTINCT FROM 'pending' THEN
      RAISE EXCEPTION 'request ${id} is gone or no longer pending — refusing blind cleanup';
    END IF;`).join('\n');

  const slotGuards = created.slots.map((s) => `
    SELECT ts.status, ts.version, ts.class_session_id IS NULL,
           coalesce((SELECT string_agg(rrs.replacement_request_id::text, ',' ORDER BY rrs.replacement_request_id)
                       FROM replacement_request_slots rrs WHERE rrs.time_slot_id = ${s.id}), ''),
           (SELECT count(*) FROM replacement_requests r
             WHERE r.replacement_time_slot_id = ${s.id}
               AND r.status IN ('pending', 'approved') AND r.id NOT IN (${ownIds})),
           (SELECT count(*) FROM replacement_requests r
              JOIN time_slots a ON a.id = r.replacement_time_slot_id
              JOIN time_slots t ON t.id = ${s.id}
             WHERE r.status IN ('pending', 'approved')
               AND a.semester_id = t.semester_id AND a.week_number = t.week_number
               AND a.day_of_week = t.day_of_week AND a.venue_id = t.venue_id
               AND t.start_time >= a.start_time
               AND t.start_time < a.start_time + COALESCE(NULLIF(r.replacement_span, 0), 1) * interval '30 minutes'
               AND r.id NOT IN (${ownIds}))
      INTO v_status, v_version, v_ownerless, v_junction, v_anchor, v_span
      FROM time_slots ts WHERE ts.id = ${s.id};
    IF NOT FOUND THEN
      RAISE EXCEPTION 'slot ${s.id} vanished — another writer removed it; refusing to touch anything';
    END IF;
    IF v_anchor <> 0 OR v_span <> 0 THEN
      RAISE EXCEPTION 'slot ${s.id} carries a foreign live claim (anchor=%, span=%) — refusing blind restore', v_anchor, v_span;
    END IF;
    IF v_status = 'pending' AND v_version = ${s.flippedVersion} AND v_ownerless AND v_junction = '${s.requestId}' THEN
      v_flipped := array_append(v_flipped, ${s.id});
    ELSIF NOT (v_status = 'available' AND v_version = ${s.originalVersion} AND v_ownerless AND v_junction = '') THEN
      RAISE EXCEPTION 'slot ${s.id} drifted (status=%, version=%, ownerless=%, junction=%) — refusing blind restore', v_status, v_version, v_ownerless, v_junction;
    END IF;`).join('\n');

  const restoreGuards = created.slots.map((s) => `
    IF ${s.id} = ANY (v_flipped) THEN
      UPDATE time_slots SET status = 'available', version = version + 1, updated_at = now()
       WHERE id = ${s.id} AND status = 'pending' AND version = ${s.flippedVersion} AND class_session_id IS NULL;
      GET DIAGNOSTICS v_cnt = ROW_COUNT;
      IF v_cnt <> 1 THEN
        RAISE EXCEPTION 'slot ${s.id} changed between the cleanup checks and its restore — refusing to guess';
      END IF;
    END IF;`).join('\n');

  sql(`DO $roomholder$ DECLARE
         v_status text; v_version int; v_ownerless boolean; v_junction text; v_anchor int; v_span int;
         v_flipped int[] := ARRAY[]::int[]; v_cnt int;
       BEGIN
         PERFORM 1 FROM replacement_requests WHERE id IN (${ownIds}) ORDER BY id FOR UPDATE;
         ${slotLock}
         ${requestGuards}
         ${slotGuards}
         DELETE FROM replacement_request_slots WHERE replacement_request_id IN (${ownIds});
         DELETE FROM audit_logs WHERE replacement_request_id IN (${ownIds});
         DELETE FROM notifications WHERE request_id IN (${ownIds});
         DELETE FROM replacement_requests WHERE id IN (${ownIds});
         ${restoreGuards}
       END $roomholder$;`);

  created.requests.length = 0;
  created.slots.length = 0;
}

/* ─── the fixture assembly (the guarded CSR runner, one isolated path) ──── */

interface Fixture {
  semester: Semester;
  room: string;
  week: number;
  day: number;
  slotIds: number[];
  starts: string[];
  span: number;
  hours: number[];
  requestId: number;
  holderModule: string;
  viewerSessionId: number | null;
  viewerSessionLabel: string;
  viewerCohortIds: number[];
  holderCohortIds: number[];
  holderLecturerId: number;
  viewerLecturerId: number;
  spanText: string;
}

function viewerCohortIdsFor(staffId: string, sem: Semester): number[] {
  return sql(`SELECT DISTINCT sc.cohort_id
                FROM session_cohorts sc
                JOIN class_sessions cs ON cs.id = sc.class_session_id
                JOIN lecturers l ON l.user_id = cs.lecturer_id
               WHERE l.staff_id = '${staffId}' AND cs.semester_id = ${sem.id}
               ORDER BY 1`).split('\n').map((l) => l.trim()).filter(Boolean).map(Number);
}

/* the CSR runner's published anchor: Tuesday 09:00–11:00, four 30-minute
   cells (tests/helpers/csr-fixture-runner.php FIXTURE_* constants — read,
   not invented) */
const CSR_CELLS = ['09:00:00', '09:30:00', '10:00:00', '10:30:00'];
const CSR_DAY = 1;

/** The four CSR anchor rows, only if EVERY cell is available, ownerless and
 *  free of the viewer's OWN calendar BEFORE the claim (the runner's own
 *  real-action guard handles the rest). The own-busy mirror — never the
 *  cohort vectors, which the served grid does not apply to an empty
 *  selection. */
function csrViewerFreeCells(sem: Semester, room: string, week: number, viewerUserId: number): FreeSlot[] | null {
  const rows = sql(`SELECT ts.id, ts.start_time::text, ts.status, ts.class_session_id IS NULL,
         ${ownFreeArms('ts', viewerUserId)}
    FROM time_slots ts
    JOIN venues v ON v.id = ts.venue_id
   WHERE ts.semester_id = ${sem.id} AND ts.week_number = ${week} AND ts.day_of_week = ${CSR_DAY}
     AND v.room_code = '${room}'
     AND ts.start_time IN (${CSR_CELLS.map((c) => `'${c}'`).join(', ')})
   ORDER BY ts.start_time`);
  const cells = rows.split('\n').map((l) => l.trim()).filter(Boolean).map((line) => {
    const [id, start, status, ownerless, free] = line.split('|').map((x) => x.trim());
    return { id: Number(id), start, ok: status === 'available' && ownerless === 't' && free === 't' };
  });
  if (cells.length !== CSR_CELLS.length || !cells.every((c) => c.ok)) return null;
  return cells.map((c) => ({ id: c.id, start: c.start, day: CSR_DAY, room, week }));
}

/** The guarded CSR runner builds an isolated source/cohort/venue and the
 *  REAL SubmitReplacementRequest action claims the block — truthful
 *  metadata by construction. Cohort disjointness from ALL the viewer's
 *  cohorts, a different lecturer, anchor viewer-freeness (own calendar
 *  only) and a future anchor date are proven BEFORE the claim; anything
 *  else is a loud hard block, never a weakened fixture. */
function buildCsrFixture(sem: Semester): Fixture {
  const seed = csrFixtureSeed();
  const viewerUserId = Number(sql(`SELECT user_id FROM lecturers WHERE staff_id = '${VIEWER_STAFF}' LIMIT 1`));
  if (!viewerUserId) throw new Error(`staff ${VIEWER_STAFF} has no lecturer row — the CSR fixture has no viewer`);

  const allViewerCohortIds = viewerCohortIdsFor(VIEWER_STAFF, sem);
  if (allViewerCohortIds.includes(seed.cohort.id)) {
    throw new Error(`the CSR fixture is BLOCKED: viewer ${VIEWER_STAFF} teaches cohort ${seed.cohort.id} `
      + `(${seed.cohort.spaced}) — the outsider identity is not cohort-disjoint from the CSR fixture; `
      + 'report to main instead of weakening the fixture');
  }
  if (seed.lecturer.userId === viewerUserId) {
    throw new Error('the CSR fixture is BLOCKED: the fixture lecturer IS the viewer — the holder must be another staff member');
  }

  /* the approve week is pre-approved (its cells are occupied) and skips;
     the first withdraw/reject/auto-reject candidate whose four cells are
     ACTUALLY available carries the claim */
  const candidates = [seed.weeks.approve, seed.weeks.withdraw, seed.weeks.reject, seed.weeks.autoReject];
  const today = sql("SELECT to_char((now() AT TIME ZONE 'Asia/Kuala_Lumpur')::date, 'YYYY-MM-DD')");

  for (const wk of candidates) {
    const week = wk.week;
    if (week < 1 || week > sem.weeks) continue;
    /* future-week-start guard: the anchor date must be strictly future, so
       the 5-minute checker can never reach this pending row mid-run */
    const anchorDate = sql(`SELECT to_char(('${sem.startDate}'::date + (${week} - 1) * 7 + ${CSR_DAY}), 'YYYY-MM-DD')`);
    if (anchorDate <= today) continue;
    const cells = csrViewerFreeCells(sem, seed.marker.venue, week, viewerUserId);
    if (!cells) continue;

    const seeded = csrSeedPending(week);
    expect(seeded.junctionCount, 'the REAL action wrote the full four-cell junction').toBe(CSR_CELLS.length);

    /* register the claim's post-state for the guarded cleanup — verified
       row by row before anything is tracked */
    const tracked: TrackedSlot[] = [];
    for (const c of cells) {
      assertTestingDb();
      const row = sql(`SELECT ts.status, ts.version,
             coalesce((SELECT string_agg(rrs.replacement_request_id::text, ',' ORDER BY rrs.replacement_request_id)
                         FROM replacement_request_slots rrs WHERE rrs.time_slot_id = ${c.id}), '')
        FROM time_slots ts WHERE ts.id = ${c.id}`);
      const [status, version, junction] = row.split('|').map((x) => x.trim());
      if (status !== 'pending' || Number(junction) !== seeded.requestId) {
        throw new Error(`slot ${c.id} is (status=${status} junction=${junction}) after seed-pending — the REAL `
          + `action's claim shape is not what the receipt promised; the rows stay untouched for main to inspect`);
      }
      tracked.push({ id: c.id, requestId: seeded.requestId, originalVersion: Number(version) - 1, flippedVersion: Number(version) });
    }
    created.requests.push(seeded.requestId);
    created.slots.push(...tracked);

    const starts = cells.map((c) => c.start);
    return {
      semester: sem,
      room: seed.marker.venue,
      week,
      day: CSR_DAY,
      slotIds: cells.map((c) => c.id),
      starts,
      span: CSR_CELLS.length,
      hours: cells.map((c) => (toMinutes(c.start) - 8 * 60) / 30),
      requestId: seeded.requestId,
      holderModule: seed.marker.module,
      viewerSessionId: null,
      viewerSessionLabel: '(empty selection — the CSR cohort is disjoint from ALL cohorts the viewer teaches)',
      viewerCohortIds: allViewerCohortIds,
      holderCohortIds: [seed.cohort.id],
      holderLecturerId: seed.lecturer.userId,
      viewerLecturerId: viewerUserId,
      spanText: spanTextFor(starts),
    };
  }

  throw new Error('the CSR fixture could not claim a viewer-free FUTURE anchor week — hard block; the fixture is not weakened');
}

async function loginOnce(p: Page): Promise<void> {
  await p.goto(`${BASE}/login/staff?_=rhh-${Date.now()}`);
  await p.fill('#login_id', VIEWER_STAFF);
  await p.fill('#password', 'Tarumt@2026');
  await p.click('#loginBtn');
  await p.waitForURL(/my-timetable|dashboard/, { timeout: 60_000 });
}

/** Land on the arrangement page for one venue (and, when the fixture
 *  pinned a viewer session, for that session), then a DATA week by its
 *  option LABEL (the selector's values are array indexes). Works on the
 *  desktop grid AND the phone lane (the grid host is display:none there,
 *  so the wait is attachment, not visibility). Returns the selected week
 *  INDEX — the busyByWeek payload's week key. */
async function gotoGrid(p: Page, room: string, week: number, sessionId: number | null): Promise<string> {
  const qs = sessionId !== null ? `session=${sessionId}&` : '';
  await p.goto(`${BASE}/replacement-arrangement?${qs}venue=${room}&_=${Date.now()}`);
  await p.locator('#tableBody .cell-content').first().waitFor({ state: 'attached', timeout: 30_000 });
  const value = await p.evaluate((w) => {
    const sel = document.getElementById('weekSelector') as HTMLSelectElement | null;
    const opt = Array.from(sel?.options ?? []).find((o) => new RegExp('^Week\\s+' + w + '\\b').test(o.textContent ?? ''));
    return opt ? opt.value : null;
  }, week);
  expect(value, `the week selector must offer "Week ${week}"`).not.toBeNull();
  await p.selectOption('#weekSelector', value as string);
  await p.waitForFunction(() => document.querySelectorAll('#tableBody .cell-content').length > 0);
  return value as string;
}

/** The SERVED page must agree the selection is empty — the contract that
 *  makes busyByWeek key '' the ONLY active calendar (no course selected →
 *  no cohort vector is applied). */
async function servedEmptySelection(p: Page): Promise<void> {
  expect(await p.evaluate(() => (document.getElementById('subjectSelector') as HTMLSelectElement | null)?.value ?? null),
    'the SERVED page must have NO course selected (subjectSelector value "") — key "" is then the only active busy vector')
    .toBe('');
}

const cellLocator = (p: Page, day: number, hour: number) =>
  p.locator(`#tableBody td[data-day="${day}"][data-hour="${hour}"] .cell-content`);

/** The room-only proof against the SERVED payload — correct scope: with NO
 *  course selected the page applies ONLY the viewer's OWN calendar, the
 *  busyByWeek key '' (the lecturer vector). Unrelated unselected cohort
 *  calendars need not be free, so ONLY '' is consulted: no tuple of the
 *  viewer's own vector may cover any held cell of the fixture week — the
 *  block the viewer sees can only be the room arm. */
async function busyAtCell(p: Page, weekIdx: string, day: number, hours: number[]): Promise<string[]> {
  return p.evaluate(({ wk, d, hs }) => {
    const busy = (window as unknown as {
      MockData?: { busyByWeek?: Record<string, Record<string, Array<[number, number, number]>>> };
    }).MockData?.busyByWeek ?? {};
    const covering: string[] = [];
    for (const tuple of busy['']?.[wk] ?? []) {
      const [bd, si, ei] = tuple;
      if (bd === d && hs.some((h) => h >= si && h < ei)) covering.push(`'' · day ${bd} · slots ${si}..${ei - 1}`);
    }
    return covering;
  }, { wk: weekIdx, d: day, hs: hours });
}

/** Engine Vector 4 re-read: the fixture room must NOT be blocked for the
 *  pinned viewer session (type + headcount) — the venue itself is
 *  eligible, so only the room's held cells block. */
async function blockedVenuesFor(p: Page, sessionId: number): Promise<string[]> {
  return p.evaluate((courseKey) =>
    ((window as unknown as { MockData?: { blockedVenues?: Record<string, string[]> } }).MockData?.blockedVenues ?? {})[courseKey] ?? [],
  String(sessionId));
}

/** Screenshot into the run's own output dir (room-holder-* names). */
async function snap(p: Page, info: TestInfo, name: string): Promise<void> {
  const file = info.outputPath(`${name}.png`);
  mkdirSync(path.dirname(file), { recursive: true });
  await p.screenshot({ path: file, fullPage: true });
}

/** The mobile lane's day picker (tap the fixture day when the picker
 *  exists). */
async function pickMobileDay(p: Page, day: number): Promise<void> {
  const dayButton = p.locator(`#arrangementMobile [data-arrangement-mobile-days] button[data-day-index="${day}"]`);
  if (await dayButton.count()) {
    await dayButton.click();
    await p.waitForTimeout(400);
  }
}

const mobileRow = (h: number) =>
  `#arrangementMobile [data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="${h}"]`;

test.describe.configure({ mode: 'serial', timeout: 180_000 });

let fixture: Fixture | null = null;

test.describe('real room-only holder (guarded fixture)', () => {
  test.beforeAll(async ({ browser }) => {
    const semester = discoverSemester();
    fixture = buildCsrFixture(semester);
    const f = fixture;

    /* THE TRUTHFULNESS PROOFS — recorded BEFORE any browser assertion. A
       broken fixture fails here loudly; it never green-skips. */
    expect(f.holderCohortIds.filter((id) => f.viewerCohortIds.includes(id)),
      `the viewer's cohorts must be disjoint from the holder source's cohorts — an overlap would make the `
      + `held block more than room-only (overlap: [${f.holderCohortIds.filter((id) => f.viewerCohortIds.includes(id)).join(',')}])`,
    ).toEqual([]);
    expect(f.holderLecturerId,
      'the holder source is taught by a DIFFERENT lecturer than the viewer').not.toBe(f.viewerLecturerId);
    expect(f.slotIds.length, 'the tracked slot set is the full span').toBe(f.span);
    expect(f.starts.length).toBe(f.span);

    // one context + one login for the whole run (the Fortify limiter)
    const context = await browser.newContext({ viewport: { width: 1440, height: 950 } });
    const page = await context.newPage();
    await loginOnce(page);
    await context.storageState({ path: 'test-results/room-holder-viewer.json' });
    await context.close();
  });

  test.afterAll(async () => {
    cleanupFixture();
  });

  test('T1 · the served holder library is real, well-formed and names DB modules (read-only proof)', async ({ browser }) => {
    const context = await browser.newContext({ storageState: 'test-results/room-holder-viewer.json', viewport: { width: 1440, height: 950 } });
    const page = await context.newPage();
    await page.goto(`${BASE}/replacement-arrangement?_=${Date.now()}`);
    await page.locator('#tableBody .cell-content').first().waitFor({ state: 'attached', timeout: 30_000 });

    const proof = await page.evaluate(() => {
      const data = (window as unknown as { PAGE_DATA?: { holderTexts?: string[]; venueSlotsByWeek?: Record<string, Record<string, Array<[number, number, number, number[] | null]>>> } }).PAGE_DATA
        ?? {};
      const texts = data.holderTexts ?? [];
      let named = 0;
      let withIds = 0;
      const states: string[] = [];
      for (const text of texts) {
        const lines = text.split('\n');
        const state = (lines[lines.length - 1] ?? '').trim();
        states.push(state);
        if (state === 'NORMAL') named++;
      }
      for (const week of Object.values(data.venueSlotsByWeek ?? {})) {
        for (const rows of Object.values(week)) {
          for (const row of rows) {
            if (row[3]) {
              withIds++;
              for (const id of row[3]) if (!(id >= 0 && id < texts.length)) return { ok: false, reason: `holder id ${id} outside the library` };
            }
          }
        }
      }
      return { ok: true, librarySize: texts.length, normalBlocks: named, annotatedSlots: withIds, states };
    });
    expect(proof.ok, JSON.stringify(proof)).toBe(true);
    expect(proof.librarySize, 'the seeded deck holds real holders').toBeGreaterThan(0);
    expect(proof.normalBlocks, 'NORMAL class holders exist on the real deck').toBeGreaterThan(0);
    expect(proof.annotatedSlots, 'real venue slots carry holder ids').toBeGreaterThan(0);
    for (const state of proof.states) {
      expect(['NORMAL', 'PENDING', 'REPLACEMENT'], 'the one public state vocabulary').toContain(state);
    }

    /* every NORMAL block's module exists in the DB — no fabricated holder */
    const modules = await page.evaluate(() =>
      ((window as unknown as { PAGE_DATA?: { holderTexts?: string[] } }).PAGE_DATA?.holderTexts ?? [])
        .map((t) => (t.split('\n')[0].split(' · ')[1] ?? '').split(' (')[0].trim())
        .filter(Boolean));
    const uniqueModules = Array.from(new Set(modules));
    expect(uniqueModules.length).toBeGreaterThan(0);
    for (const code of uniqueModules) {
      const quoted = code.replace(/'/g, "''");
      expect(Number(sql(`SELECT count(*) FROM modules WHERE module_code = '${quoted}'`)), `module ${code} must be a real row`).toBeGreaterThan(0);
    }
    await context.close();
  });

  test('T2 · desktop: the room-only pending cell names its ACTUAL holder (TRUE 12h span on EVERY covered cell, PENDING, not selectable, dark + light)', async ({ browser }, testInfo) => {
    /* a broken fixture must FAIL, never green-skip */
    expect(fixture, 'the guarded fixture must exist — a broken fixture fails loudly').not.toBeNull();
    const f = fixture!;
    const context = await browser.newContext({ storageState: 'test-results/room-holder-viewer.json', viewport: { width: 1440, height: 950 } });
    const page = await context.newPage();
    const weekIdx = await gotoGrid(page, f.room, f.week, f.viewerSessionId);

    /* the served page must agree the selection is empty — key '' is then
       the ONLY active calendar */
    await servedEmptySelection(page);

    /* ROOM-ONLY proofs against the SERVED payload: the viewer's OWN busy
       vector (key '', the only active calendar with an empty selection) is
       EMPTY at every covered cell, and the room itself is venue-eligible
       for the pinned session — so the only reason the cell is not
       selectable is the room being held. */
    expect(await busyAtCell(page, weekIdx, f.day, f.hours),
      "busyByWeek[''] must be EMPTY at every held cell — the block is the room arm alone").toEqual([]);
    if (f.viewerSessionId !== null) {
      expect(await blockedVenuesFor(page, f.viewerSessionId),
        `room ${f.room} must be venue-eligible for the pinned viewer session`).not.toContain(f.room);
    }

    const hour = f.hours[0];
    const cell = cellLocator(page, f.day, hour);
    await expect(cell).toHaveClass(/cell-reserved/);
    await expect(cell).not.toHaveClass(/cell-pending/);

    /* .cell-state-label is hidden until hover — hover the cell first so the
       label is visible, otherwise innerText falls back to textContent and
       the <br> between 'Other' and 'Pending' is dropped */
    await cell.hover();
    await page.waitForTimeout(250);

    /* the hover words name the family, ownership stays words-only */
    const label = await cell.locator('.cell-state-label').innerText();
    expect(label.replace(/\s+/g, ' ').trim()).toBe('Other Pending');

    const tip = (await cell.getAttribute('data-tip')) ?? '';
    expect(tip, 'the cell tip is attached').toBeTruthy();
    expect(tip.split('\n')[0]).toContain('Pending');
    expect(tip).toContain(f.holderModule, 'the ACTUAL holder’s own course');
    expect(tip).toContain(f.spanText, 'the TRUE full 12-hour span (last cell start + 30 min), not the half-cell');
    expect(tip.trim().split('\n').pop()?.trim()).toBe('PENDING');
    expect(tip).not.toContain('You — ', 'the holder is another staff member');
    expect(await cell.getAttribute('aria-label')).toContain('Pending — ');

    /* the tips show no private request data */
    expect(tip.toLowerCase()).not.toContain(FIXTURE_MARK.toLowerCase());

    /* eligibility invariant: the held cell is not selectable */
    const counterBefore = await page.locator('#infoTotal').textContent();
    await cell.click();
    await page.waitForTimeout(400);
    await expect(cell).not.toHaveClass(/cell-selected/);
    const counterAfter = await page.locator('#infoTotal').textContent();
    expect(counterAfter).toBe(counterBefore);

    /* EVERY covered cell names the SAME true full span */
    for (const h of f.hours) {
      expect((await cellLocator(page, f.day, h).getAttribute('data-tip')) ?? '',
        `covered cell hour ${h} names the same full span`).toContain(f.spanText);
    }

    /* DARK capture — the shipped default theme */
    expect(await page.evaluate(() => document.documentElement.classList.contains('dark')),
      'the page ships dark by default').toBe(true);
    /* the tooltip must be VISIBLE in the capture: the blocked click above
       left the pointer on the cell and the tip hidden — reset the pointer,
       re-hover the held cell and let the shared tip settle before snapping */
    await page.mouse.move(0, 0);
    await cellLocator(page, f.day, hour).hover();
    await page.waitForTimeout(300);
    const darkTip = page.locator('.data-tip-tooltip');
    await expect(darkTip).toBeVisible();
    await expect(darkTip).toContainText(f.holderModule);
    await snap(page, testInfo, 'room-holder-desktop-dark');

    /* LIGHT through the ACTUAL toggler, settled, re-asserted, captured */
    await page.locator('.theme-toggle').first().click();
    await page.waitForTimeout(600);
    expect(await page.evaluate(() => document.documentElement.classList.contains('light')),
      'the actual theme toggler switched the page to light').toBe(true);
    expect((await cellLocator(page, f.day, hour).getAttribute('data-tip')) ?? '')
      .toContain(f.spanText, 'the receipt survives the theme re-render');
    /* same reset + re-hover: the theme-toggle click moved the pointer off
       the cell, so the light capture must re-establish the settled tip */
    await page.mouse.move(0, 0);
    await cellLocator(page, f.day, hour).hover();
    await page.waitForTimeout(300);
    const lightTip = page.locator('.data-tip-tooltip');
    await expect(lightTip).toBeVisible();
    await expect(lightTip).toContainText(f.holderModule);
    await snap(page, testInfo, 'room-holder-desktop-light');
    await context.close();
  });

  test('T3 · phone: the blocked row’s sheet shows the same holder (textContent, no markup, dark + light)', async ({ browser }, testInfo) => {
    expect(fixture, 'the guarded fixture must exist — a broken fixture fails loudly').not.toBeNull();
    const f = fixture!;
    const context = await browser.newContext({ storageState: 'test-results/room-holder-viewer.json', viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    const weekIdx = await gotoGrid(page, f.room, f.week, f.viewerSessionId);
    await expect(page.locator('#arrangementMobile')).toBeVisible({ timeout: 15_000 });
    await servedEmptySelection(page);

    /* the viewed day must be the fixture day: tap its picker button */
    await pickMobileDay(page, f.day);

    /* every covered row renders reserved; the own-vector proof holds on the
       phone render too */
    for (const h of f.hours) {
      await expect(page.locator(mobileRow(h))).toHaveClass(/cell-reserved/, { timeout: 10_000 });
    }
    expect(await busyAtCell(page, weekIdx, f.day, f.hours), "busyByWeek[''] must be EMPTY at the held cells (phone render)").toEqual([]);

    const openSheet = async (h: number) => {
      const row = page.locator(mobileRow(h));
      await row.click();
      await expect(page.locator('#arrangementMobileInfo')).toBeVisible({ timeout: 10_000 });
      const reasonText = (await page.locator('#arrangementMobileInfo .arrangement-mobile-info-reason').textContent()) ?? '';
      expect(reasonText).toContain(f.holderModule, 'the ACTUAL holder’s course');
      expect(reasonText).toContain(f.spanText, 'the TRUE full 12-hour span');
      expect(reasonText.trim().split('\n').pop()?.trim()).toBe('PENDING');
      expect(reasonText).not.toContain('You — ');
    };
    const closeSheet = async () => {
      await page.locator('#arrangementMobileInfo [data-arrangement-mobile-info-close]').click();
      await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    };

    /* the sheet names the TRUE span from the FIRST and the LAST covered row */
    await openSheet(f.hours[0]);
    await snap(page, testInfo, 'room-holder-phone-dark');
    await closeSheet();
    await openSheet(f.hours[f.hours.length - 1]);
    await closeSheet();

    /* text-only rendering: holder text is never parsed as markup */
    const unsafe = await page.evaluate(() =>
      document.querySelectorAll('#arrangementMobileInfo script, #arrangementMobileInfo img, #arrangementMobileInfo iframe').length);
    expect(unsafe).toBe(0);

    /* LIGHT theme on the phone: the toggler is hidden in mobile mode, so
       the ACTUAL persistence mechanism applies it (localStorage 'theme' +
       the template's pre-CSS guard) and the page re-renders */
    await page.evaluate(() => localStorage.setItem('theme', 'light'));
    await page.reload();
    const weekIdx2 = await gotoGrid(page, f.room, f.week, f.viewerSessionId);
    expect(await page.evaluate(() => document.documentElement.classList.contains('light')),
      'light applied through the real persistence mechanism').toBe(true);
    await expect(page.locator('#arrangementMobile')).toBeVisible({ timeout: 15_000 });
    await servedEmptySelection(page);
    await pickMobileDay(page, f.day);
    expect(await busyAtCell(page, weekIdx2, f.day, f.hours)).toEqual([]);
    const row2 = page.locator(mobileRow(f.hours[0]));
    await expect(row2).toHaveClass(/cell-reserved/, { timeout: 10_000 });
    await row2.click();
    await expect(page.locator('#arrangementMobileInfo')).toBeVisible({ timeout: 10_000 });
    expect(((await page.locator('#arrangementMobileInfo .arrangement-mobile-info-reason').textContent()) ?? ''))
      .toContain(f.spanText, 'the receipt survives the theme re-render');
    await page.waitForTimeout(600); // settle the transition before capture
    await snap(page, testInfo, 'room-holder-phone-light');
    await page.locator('#arrangementMobileInfo [data-arrangement-mobile-info-close]').click();
    await expect(page.locator('#arrangementMobileInfo')).toBeHidden();
    await context.close();
  });
});

/* ─── the disclosed SYNTHETIC presentation stage (no DB mutation) ──────── */

interface SyntheticStage {
  weekSelectorValue: string;
  venue: string;
}

/** Reshape the page's own payload copy and re-render the real grid. */
async function stageSynthetic(p: Page, holderTexts: string[], cells: Array<[number, number, number, number[] | null]>, busyWeek: unknown[][] | null): Promise<SyntheticStage> {
  /* Stage the LAST bookable week FIRST: the injected Thursday/Friday cells
     must sit safely beyond the notice window — the default week is the real
     current one, and re-classifying the anchor as too-soon there would
     break the presentation receipt artificially. The LAST offered week can
     itself contain holidays (e.g. the semester's final week), so walk the
     offered weeks from the END and take the latest one whose every injected
     day index is actually bookable — days exist, not a holiday/Sunday, and
     beyond the too-soon window. */
  const dayIndices = Array.from(new Set(cells.map((row) => row[0])));
  const lastWeek = await p.evaluate((days) => {
    const sel = document.getElementById('weekSelector') as HTMLSelectElement | null;
    if (!sel || sel.options.length === 0) {
      throw new Error('#weekSelector missing or empty — cannot stage the synthetic week');
    }
    const w = window as unknown as {
      weekData: Array<{ days: Array<{ holiday?: boolean; sunday?: boolean } | undefined> }>;
      isSlotTooSoon: (weekData: unknown, weekIdx: number, dayIdx: number) => boolean;
    };
    for (let i = sel.options.length - 1; i >= 0; i--) {
      const idx = Number(sel.options[i].value);
      const week = w.weekData?.[idx];
      const bookable = Number.isInteger(idx) && !!week && days.every((di) => {
        const day = week.days?.[di];
        if (!day) return false;
        if (day.holiday || day.sunday) return false; /* same flags the grid builders read */
        return !w.isSlotTooSoon(w.weekData, idx, di);
      });
      if (bookable) return sel.options[i].value;
    }
    throw new Error('no offered week has every injected day bookable — cannot stage the synthetic week');
  }, dayIndices);
  await p.selectOption('#weekSelector', lastWeek);
  await p.waitForFunction(() => document.querySelectorAll('#tableBody .cell-content').length > 0);

  return p.evaluate(({ texts, injected, busy }) => {
    const wk = Number((document.getElementById('weekSelector') as HTMLSelectElement).value);
    const w = window as unknown as {
      PAGE_DATA: { holderTexts: string[] };
      MockData: {
        holderTexts?: string[];
        busyByWeek: Record<string, unknown>;
        blockedVenues: Record<string, unknown>;
        venueSlots: Record<string, Record<string, unknown>>;
      };
      buildTimetable: () => void;
    };
    w.PAGE_DATA.holderTexts = texts;
    w.MockData.holderTexts = texts;
    w.MockData.busyByWeek = busy ? { '': { [wk]: busy } } : {};
    w.MockData.blockedVenues = {};
    const venue = 'B103';
    w.MockData.venueSlots[wk] = w.MockData.venueSlots[wk] || {};
    w.MockData.venueSlots[wk][venue] = injected;
    w.buildTimetable();
    return { weekSelectorValue: String(wk), venue };
  }, { texts: holderTexts, injected: cells, busy: busyWeek });
}

const cellTip = async (p: Page, day: number, hour: number) => {
  /* .cell-state-label is hidden until hover — hover the cell first so the
     label is visible, otherwise innerText returns '' (display:none) */
  await cellLocator(p, day, hour).hover();
  await p.waitForTimeout(250);
  return p.evaluate(({ d, h }) => {
    const cell = document.querySelector(`#tableBody td[data-day="${d}"][data-hour="${h}"] .cell-content`) as HTMLElement | null;
    return {
      cls: cell?.className ?? '',
      tip: cell?.getAttribute('data-tip') ?? '',
      label: (cell?.querySelector('.cell-state-label') as HTMLElement | null)?.innerText ?? '',
      tabIndex: cell?.tabIndex ?? -1,
    };
  }, { d: day, h: hour });
};

test.describe('synthetic presentation stage (disclosed, no DB writes)', () => {
  test('T4 · desktop: the merged tip keeps the room-only holder beside an engine block, escapes text, and never changes eligibility (dark + light)', async ({ browser }) => {
    const context = await browser.newContext({ storageState: 'test-results/room-holder-viewer.json', viewport: { width: 1440, height: 950 } });
    const page = await context.newPage();
    await page.goto(`${BASE}/replacement-arrangement?_=${Date.now()}`);
    await page.locator('#tableBody .cell-content').first().waitFor({ state: 'attached', timeout: 30_000 });

    /* The room-only holder ALONE: no engine rows at all. */
    const texts = [
      'B900 · RHH901 (L) · 2:00 PM ~ 4:00 PM\nRoom Holder (6002)\n—\nNORMAL',
      '<b>EVIL</b> & Co · RHH902 (T) · 9:00 AM ~ 9:30 AM\n<script>alert(1)</script>x (6001)\n—\nNORMAL',
    ];
    await stageSynthetic(page, texts, [[3, 12, 1, [0]], [4, 2, 1, [1]]], null);

    let tip = await cellTip(page, 3, 12);
    expect(tip.cls).toContain('cell-occupied');
    expect(tip.label.replace(/\s+/g, ' ').trim()).toBe('Venue Conflict');
    expect(tip.tip).toContain('RHH901');
    expect(tip.tip.trim().split('\n').pop()?.trim()).toBe('NORMAL');
    expect(tip.tabIndex).toBe(0);

    /* escaping: the raw holder text rides data-tip as TEXT; the shared
       engine renders textContent — no element is ever created from it */
    tip = await cellTip(page, 4, 2);
    expect(tip.tip).toContain('<b>EVIL</b>');
    expect(tip.tip).toContain('<script>alert(1)</script>');
    await cellLocator(page, 4, 2).hover();
    await page.waitForTimeout(250);
    const unsafe = await page.evaluate(() =>
      Array.from(document.querySelectorAll('.data-tip-tooltip script, .data-tip-tooltip img, .data-tip-tooltip iframe')).length);
    expect(unsafe, 'holder text is never parsed as markup').toBe(0);

    /* eligibility invariant: a held cell is not selectable */
    await cellLocator(page, 3, 12).click();
    await page.waitForTimeout(300);
    expect(await cellLocator(page, 3, 12).getAttribute('class')).not.toContain('cell-selected');

    /* THE MERGE: an own-calendar block (another room, same half-hour) must
       NOT hide the room-only holder — both blocks print, one header. */
    const ownBlock = 'C500 · BMIT1001 (T) · 2:00 PM ~ 3:00 PM\nYou — Lecturer A (6001)\n—\nNORMAL';
    await stageSynthetic(
      page,
      [texts[0], texts[1], ownBlock],
      [[3, 12, 1, [0]]],
      [[3, 12, 14, ownBlock, null, true, ['own'], [2]]],
    );
    tip = await cellTip(page, 3, 12);
    expect(tip.tip.split('\n')[0]).toContain('2 classes in the way');
    expect(tip.tip).toContain('RHH901', 'the room-only holder survives the merge');
    expect(tip.tip).toContain('BMIT1001', 'the own-calendar block survives too');

    /* SAME-identity dedup: the same factual holder arriving through both
       arms prints once. */
    await stageSynthetic(
      page,
      [texts[0], texts[1], ownBlock],
      [[3, 12, 1, [0]]],
      [[3, 12, 14, texts[0], null, true, ['own'], [0]]],
    );
    tip = await cellTip(page, 3, 12);
    expect(tip.tip.split('\n')[0]).toContain('1 class in the way');
    expect(tip.tip.split('RHH901').length - 1).toBe(1, 'one factual holder, one block');

    /* DIFFERENT identities, identical display strings: both survive. */
    const twin = 'B900 · RHH903 (L) · 2:00 PM ~ 4:00 PM\nTwin Holder (6003)\n—\nNORMAL';
    await stageSynthetic(
      page,
      [texts[0], texts[1], ownBlock, twin, twin],
      [[3, 12, 1, [3, 4]]],
      null,
    );
    tip = await cellTip(page, 3, 12);
    expect(tip.tip.split('\n')[0]).toContain('2 classes in the way', 'two distinct holders, not a merged copy');

    /* LIGHT THEME: the same merged/escaped behaviour, re-rendered. */
    await page.evaluate(() => localStorage.setItem('theme', 'light'));
    await page.reload();
    await page.locator('#tableBody .cell-content').first().waitFor({ state: 'attached', timeout: 30_000 });
    await stageSynthetic(page, texts, [[3, 12, 1, [0]], [4, 2, 1, [1]]], null);
    tip = await cellTip(page, 3, 12);
    expect(tip.tip).toContain('RHH901');
    expect(tip.cls).toContain('cell-occupied');
    await cellLocator(page, 3, 12).hover();
    await page.waitForTimeout(250);
    const lightTipNode = await page.evaluate(() => (document.querySelector('.data-tip-tooltip') as HTMLElement | null)?.textContent ?? '');
    expect(lightTipNode).toContain('RHH901');
    await context.close();
  });

  test('T5 · phone: the sheet shows the escaped holder text in both themes', async ({ browser }) => {
    const context = await browser.newContext({ storageState: 'test-results/room-holder-viewer.json', viewport: { width: 390, height: 844 } });
    const page = await context.newPage();
    await page.goto(`${BASE}/replacement-arrangement?_=${Date.now()}`);
    await page.locator('#tableBody .cell-content').first().waitFor({ state: 'attached', timeout: 30_000 });
    await expect(page.locator('#arrangementMobile')).toBeVisible({ timeout: 15_000 });

    const texts = [
      '<b>EVIL</b> & Co · RHH902 (T) · 9:00 AM ~ 9:30 AM\n<script>alert(1)</script>x (6001)\n—\nNORMAL',
    ];
    await stageSynthetic(page, texts, [[3, 2, 1, [0]]], null);

    /* the viewed day must be the injected day */
    const dayButton = page.locator('#arrangementMobile [data-arrangement-mobile-days] button[data-day-index="3"]');
    if (await dayButton.count()) {
      await dayButton.click();
      await page.waitForTimeout(400);
    }

    const row = page.locator('#arrangementMobile [data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="2"]');
    await expect(row).toHaveClass(/cell-occupied/, { timeout: 10_000 });

    await row.click();
    await expect(page.locator('#arrangementMobileInfo')).toBeVisible({ timeout: 10_000 });
    const reasonText = (await page.locator('#arrangementMobileInfo .arrangement-mobile-info-reason').textContent()) ?? '';
    expect(reasonText).toContain('<b>EVIL</b>', 'the raw text, never parsed');
    expect(reasonText).toContain('<script>alert(1)</script>');
    expect(reasonText.trim().split('\n').pop()?.trim()).toBe('NORMAL');
    const unsafe = await page.evaluate(() =>
      document.querySelectorAll('#arrangementMobileInfo script, #arrangementMobileInfo img, #arrangementMobileInfo iframe').length);
    expect(unsafe).toBe(0);
    await page.locator('#arrangementMobileInfo [data-arrangement-mobile-info-close]').click();

    /* LIGHT THEME on the phone too */
    await page.evaluate(() => localStorage.setItem('theme', 'light'));
    await page.reload();
    await page.locator('#tableBody .cell-content').first().waitFor({ state: 'attached', timeout: 30_000 });
    await expect(page.locator('#arrangementMobile')).toBeVisible({ timeout: 15_000 });
    await stageSynthetic(page, texts, [[3, 2, 1, [0]]], null);
    const dayButton2 = page.locator('#arrangementMobile [data-arrangement-mobile-days] button[data-day-index="3"]');
    if (await dayButton2.count()) {
      await dayButton2.click();
      await page.waitForTimeout(400);
    }
    const row2 = page.locator('#arrangementMobile [data-arrangement-mobile-timeline] [data-mobile-row="hour"][data-mobile-hour="2"]');
    await expect(row2).toHaveClass(/cell-occupied/, { timeout: 10_000 });
    await row2.click();
    await expect(page.locator('#arrangementMobileInfo')).toBeVisible({ timeout: 10_000 });
    expect(((await page.locator('#arrangementMobileInfo .arrangement-mobile-info-reason').textContent()) ?? ''))
      .toContain('<b>EVIL</b>');
    await context.close();
  });
});
