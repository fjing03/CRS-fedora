import { test, expect } from '@playwright/test';
import type { BrowserContext, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * arrangement-slot-ownership-legend — the browser receipt (2026-11-06).
 *
 * The law (2026-11-11 rewrite, user ruling): COLOUR = state (amber = a pending
 * reservation, red = a conflict) and NOTHING ELSE — the 3px ownership ring was
 * removed from both families, so your own slot draws like everyone else's. What
 * still separates them is the CLASS (`cell-pending` vs `cell-reserved`,
 * `cell-occupied--mine`) and the hover words ("Your Pending" vs "Other Pending").
 * The bug this lane fixes: another staff member's pending slot used to paint the
 * "Pending (You)" amber whenever the selected subject shared that cohort,
 * because the engine's busy vector is ownerless and had no ownership test.
 *
 * TWO describes:
 *   T9a (writes): a pending request owned by staff 5770 on a slot pair that a
 *        staff 5425 session's cohort also carries. Viewed by 5425 it is
 *        `cell-reserved` (Other Pending); viewed by 5770 it is `cell-pending`
 *        (Your Pending). BOTH are amber and BOTH are borderless now — the class
 *        and the hover words are the whole difference. ONE test() — the fixture
 *        flips the slots, so a second test's `available` precondition would trip
 *        on its own fixture.
 *   T9b (read-only): the resolver's precedence table (crafted facts, incl. the
 *        two counter-intuitive crossings) and the legend's four rows + tips.
 *
 * Fixture hygiene: slots are DISCOVERED at runtime (never a hardcoded pair —
 * `TestingFixtureSeeder` already holds B010 week 7 Fri 14:00/14:30 for request
 * 26), asserted available + adjacent BEFORE the insert, and every row this
 * spec creates is deleted by afterAll, with the two slot statuses restored.
 *
 * RUN THIS SPEC ALONE. `tests/week-filter-preserved.spec.ts` re-seeds
 * `TestingFixtureSeeder` in its own beforeEach, and that seeder DELETEs every
 * replacement request — which would wipe this fixture mid-run (the symptom is
 * an orphaned pending slot reading "reserved"). `assertFixtureAlive()` turns
 * that into an explicit message instead of a misleading class mismatch.
 *
 * REQUIRES the testing DB seeded + a serve on :8030:
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/arrangement-slot-ownership.spec.ts
 */

// The standing guard (the 2026-11-01 data-loss incident): module-level abort
// BEFORE anything loads. Exact strings: no localhost, no :8000, no bare run.
abortUnlessTestingTarget('tests/arrangement-slot-ownership.spec.ts', { requireAllowSql: true });

const BASE = process.env.CRS_BASE as string;

/* Fixture identities are DISCOVERED at runtime (cancelled-slot-reuse A21
   reconciliation): the old session-39/40 + semester-1 + week-8 constants died
   the moment the sequences advanced past the seed ids. The natural identities
   still exist and are all this fixture needs — staff 5770 (owner) and staff
   5425 (viewer) sharing ONE cohort through two B010 sessions — found by their
   staff_id + session_cohorts membership, never by an id. The owner side is
   additionally pinned to its natural type + code, AMCS2093 (P) Thu 13:30
   (see discoverIdentity): the mirror assertion needs the owner's class NOT
   to sit on the viewer's own calendar, and the first B010 5770 session by id
   (BM… P, Wed 09:00) does — 5425 teaches B009 at that same time. The proposal week is
   picked on the CAMPUS notice clock (Asia/Kuala_Lumpur, Semester::active()
   mirror) at least two campus weeks ahead, so the 5-minute auto-reject
   checker's 3-working-hour window can never touch these pendings; the source
   occurrence week per request is a week that session really teaches with no
   other ACTIVE request holding the pair (one-active-per-source-week). */
const OWNER_STAFF = '5770';
const VIEWER_STAFF = '5425';
const VENUE = 'B010';
const FIXTURE_MARK = 'PW ownership receipt';

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

/** Mirror of Semester::active(): start_date <= campus today, latest first,
 *  then id 1, then the latest row — the id is discovered, never assumed. */
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

/** The CAMPUS week index (LocalTime::daysSince + currentTimelineWeekIndex). */
function campusWeekIndex(sem: Semester): number {
  const days = Math.floor((Date.parse(sql("SELECT to_char((now() AT TIME ZONE 'Asia/Kuala_Lumpur')::date, 'YYYY-MM-DD')"))
    - Date.parse(sem.startDate)) / 86_400_000);
  return Math.max(0, Math.min(sem.weeks - 1, Math.floor(days / 7)));
}

interface SessionIdentity { id: number; day: number; hour: number; viewerSession: number }

/** The owner session: the ${OWNER_STAFF} AMCS2093 (P) session IN B010 that
 *  carries cohorts, paired with an ${VIEWER_STAFF} session sharing one of them
 *  (the shared cohort is the natural identity — never a hardcoded session id).
 *
 *  The module_code + session_type pin is NOT arbitrary. The fixture's mirror
 *  assertion reads the owner's class cell from the viewer's grid and demands
 *  it NOT be hers (`cell-occupied` WITHOUT `--mine`). First-B010-session-by-id
 *  picked BM… (P) Wed 09:00, and staff 5425 teaches a different room (B009)
 *  at that same Wed 09:00 — a legitimate any-own overlap sitting in the
 *  VIEWER'S OWN calendar — which would make the not-mine assertion invalid.
 *  The natural identity AMCS2093 (P) Thu 13:30 has no such unrelated
 *  own-calendar collision, so the owner side is pinned to its type + code
 *  (discovered live: class 87 in the current testing DB, id 39 long dead);
 *  staff / venue / semester / cohort-shared viewer stay dynamically resolved. */
function discoverIdentity(semId: number): SessionIdentity {
  const owners = sql(`SELECT cs.id, cs.day_of_week, cs.start_time
                        FROM class_sessions cs
                        JOIN lecturers l ON l.user_id = cs.lecturer_id
                        JOIN venues v ON v.id = cs.venue_id
                        JOIN modules m ON m.id = cs.module_id
                       WHERE l.staff_id = '${OWNER_STAFF}' AND v.room_code = '${VENUE}'
                         AND m.module_code = 'AMCS2093' AND cs.session_type = 'P'
                         AND cs.semester_id = ${semId}
                         AND EXISTS (SELECT 1 FROM session_cohorts sc WHERE sc.class_session_id = cs.id)
                       ORDER BY cs.id ASC`);
  for (const line of owners.split('\n').filter(Boolean)) {
    const [id, day, start] = line.split('|').map((x) => x.trim());
    const minute = Number(start.slice(0, 2)) * 60 + Number(start.slice(3, 5));
    const viewer = sql(`SELECT cs.id FROM class_sessions cs
                          JOIN lecturers l ON l.user_id = cs.lecturer_id
                          JOIN session_cohorts sc1 ON sc1.class_session_id = ${id}
                          JOIN session_cohorts sc2 ON sc2.cohort_id = sc1.cohort_id AND sc2.class_session_id = cs.id
                         WHERE l.staff_id = '${VIEWER_STAFF}' AND cs.semester_id = ${semId}
                         ORDER BY cs.id ASC LIMIT 1`);
    if (viewer) {
      return { id: Number(id), day: Number(day), hour: (minute - 8 * 60) / 30, viewerSession: Number(viewer) };
    }
  }
  throw new Error(
    `no ${OWNER_STAFF} AMCS2093 (P) session in ${VENUE} shares a cohort with a ${VIEWER_STAFF} session — `
    + 'the ownership fixture has no natural identity to build on',
  );
}

/** The proposal week: the first campus-clock-future week (≥ current + 2, so
 *  the 5-minute checker can never reach the proposed start) whose B010 Friday
 *  still offers TWO disjoint adjacent available pairs. */
function discoverProposalWeek(sem: Semester): number {
  const start = campusWeekIndex(sem) + 2;
  for (let w = start; w <= sem.weeks; w++) {
    try {
      discoverTwoPairs(w, sem.id);
      return w;
    } catch { /* that week's Friday has no two free pairs — try the next */ }
  }
  throw new Error(`no week from ${start} to ${sem.weeks} offers two disjoint adjacent pairs on B010 Friday`);
}

/** The source occurrence week for one request: a week that session REALLY
 *  teaches with NO class_exception for that exact (session, week) — an
 *  occupied row alone proves nothing, because the exception is what makes the
 *  occurrence need a replacement (and active claims may retain a stale
 *  class_session_id, so they are no proof either) — plus no other ACTIVE
 *  request holding the (session, week) pair (one-active-per-source-week). */
function discoverSourceWeek(sem: Semester, sessionId: number, proposalWeek: number): number {
  for (let w = 1; w <= sem.weeks; w++) {
    if (w === proposalWeek) continue;   // the entry guard: source week ≠ test week
    const taught = Number(sql(`SELECT count(*) FROM time_slots ts
                                WHERE ts.class_session_id = ${sessionId}
                                  AND ts.semester_id = ${sem.id}
                                  AND ts.week_number = ${w} AND ts.status = 'occupied'
                                  AND NOT EXISTS (SELECT 1 FROM class_exceptions ce
                                                   WHERE ce.class_session_id = ${sessionId}
                                                     AND ce.week_number = ${w})`));
    if (taught === 0) continue;
    const active = Number(sql(`SELECT count(*) FROM replacement_requests
                                WHERE class_session_id = ${sessionId} AND week_number = ${w}
                                  AND semester_id = ${sem.id}
                                  AND status IN ('pending', 'approved')`));
    if (active !== 0) continue;         // one-active-per-source-week
    return w;
  }
  throw new Error(`session ${sessionId} has no free source week (unexceptioned teaching occurrence, no active request, ≠ ${proposalWeek})`);
}

interface SlotPair { ids: number[]; starts: string[]; day: number; hours: number[] }

/** The FIRST TWO disjoint adjacent pairs of the day (one per staff member).
 *  Throws a plain Error (no expect) so the proposal-week scan can skip a week. */
function discoverTwoPairs(week: number, semesterId: number): [SlotPair, SlotPair] {
  const first = discoverSlotPair(week, semesterId);
  const taken = new Set(first.ids);
  const raw = sql(`SELECT ts.id, ts.start_time, ts.day_of_week
                     FROM time_slots ts JOIN venues v ON v.id = ts.venue_id
                    WHERE ts.semester_id = ${semesterId} AND ts.week_number = ${week}
                      AND ts.day_of_week = 4 AND v.room_code = '${VENUE}'
                      AND ts.status = 'available'
                    ORDER BY ts.start_time ASC`);
  const all = raw.split('\n').filter(Boolean).map((line) => {
    const [id, start, day] = line.split('|').map((x) => x.trim());
    return { id: Number(id), start, day: Number(day) };
  });
  const toMin = (t: string) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));
  for (let i = all.length - 2; i >= 0; i -= 1) {
    if (taken.has(all[i].id) || taken.has(all[i + 1].id)) continue;
    if (toMin(all[i + 1].start) - toMin(all[i].start) !== 30) continue;

    const rows = [all[i], all[i + 1]];
    return [first, {
      ids: rows.map((r) => r.id),
      starts: rows.map((r) => r.start),
      day: rows[0].day,
      hours: rows.map((r) => (toMin(r.start) - 8 * 60) / 30),
    }];
  }
  throw new Error(`a second adjacent free pair must exist on B010 week ${week} Friday`);
}

/** Two ADJACENT available half-hours, venue B010, that week's Friday. */
function discoverSlotPair(week: number, semesterId: number): SlotPair {
  const raw = sql(`SELECT ts.id, ts.start_time, ts.day_of_week
                     FROM time_slots ts JOIN venues v ON v.id = ts.venue_id
                    WHERE ts.semester_id = ${semesterId} AND ts.week_number = ${week}
                      AND ts.day_of_week = 4 AND v.room_code = '${VENUE}'
                      AND ts.status = 'available'
                    ORDER BY ts.start_time ASC`);
  const all = raw.split('\n').filter(Boolean).map((line) => {
    const [id, start, day] = line.split('|').map((x) => x.trim());
    return { id: Number(id), start, day: Number(day) };
  });
  if (all.length < 2) throw new Error(`free half-hours must exist on B010 week ${week} Friday`);

  // the FIRST genuinely adjacent pair (30 minutes apart) — a 2-slot request
  // needs a real pair, and a drifted seed must not hand us a 90-minute "pair"
  const toMin = (t: string) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));
  /* Pairs come from the END of the day on purpose: `arrangement-entry-guard`
     submits through the real pipeline by clicking the FIRST free cell, and a
     shared testing DB must not let the two fixtures fight over one slot. */
  let pairIndex = -1;
  for (let i = all.length - 2; i >= 0; i -= 1) {
    if (toMin(all[i + 1].start) - toMin(all[i].start) === 30) { pairIndex = i; break; }
  }
  if (pairIndex < 0) throw new Error(`an adjacent free pair must exist on B010 week ${week} Friday`);

  const rows = [all[pairIndex], all[pairIndex + 1]];
  const day = rows[0].day;
  const hours = rows.map((r) => (toMin(r.start) - 8 * 60) / 30);

  return { ids: rows.map((r) => r.id), starts: rows.map((r) => r.start), day, hours };
}

/** Everything this spec created — cleaned up in afterAll, never a blind sweep.
 *  Each slot is registered with the OWNED creation facts the fixture found and
 *  made: the original ownerless-available version, and the version the flip
 *  produced — so the cleanup can prove every row is still exactly what this
 *  fixture wrote before restoring it, and refuse LOUDLY when it is not
 *  (a blind restore would erase a newer live holder). */
interface TrackedSlot { id: number; requestId: number; originalVersion: number; flippedVersion: number }
const created: { requests: number[]; slots: TrackedSlot[] } = { requests: [], slots: [] };

/** A pending request on `pair`, proposed by the staff member who owns `sessionId`.
 *  semester_id and the source occurrence week are DISCOVERED inputs (A21
 *  reconciliation — never the dead literal 1/9); the anchor slot lives in the
 *  future proposal week, so the 5-minute checker can never reach these rows.
 *
 *  Setup hygiene: BOTH rows' owned creation facts are snapshotted, validated
 *  and REGISTERED before the first junction/flip write, so a setup error
 *  leaves a registry the scoped cleanup can act on (a request with 0/1/2
 *  linked/flipped slots) — never a blind sweep. Each slot's conditional flip
 *  + junction link is ONE statement (all-or-nothing), verified by the
 *  RETURNING id, never by a command tag (psql -q suppresses those). */
function insertPending(sessionId: number, staffId: string, pair: SlotPair, semesterId: number, sourceWeek: number): void {
  const proposer = sql(`SELECT user_id FROM lecturers WHERE staff_id = '${staffId}' LIMIT 1`);
  if (!proposer) throw new Error(`staff ${staffId} has no lecturer row — fixture identity invalid`);

  /* snapshot and validate BOTH rows first — an available row in this grid is
     ownerless (class_session_id NULL) */
  const facts = pair.ids.map((slotId) => {
    assertTestingDb();
    const before = sql(`SELECT status, version, class_session_id IS NULL AS ownerless
                          FROM time_slots WHERE id = ${slotId}`);
    if (!before) throw new Error(`slot ${slotId} vanished before the fixture flip`);
    const [status, version, ownerless] = before.split('|').map((x) => x.trim());
    if (status !== 'available' || ownerless !== 't') {
      throw new Error(`slot ${slotId} is (status=${status} ownerless=${ownerless}) — refusing to flip a foreign holder`);
    }
    return { slotId, originalVersion: Number(version) };
  });

  assertTestingDb();
  const requestId = Number(sql(`INSERT INTO replacement_requests
       (semester_id, proposer_id, class_session_id, week_number, replacement_time_slot_id,
        status, remarks, submitted_at, created_at, updated_at)
     VALUES (${semesterId}, ${proposer}, ${sessionId}, ${sourceWeek}, ${pair.ids[0]},
             'pending', '${FIXTURE_MARK}', now(), now(), now())
     RETURNING id`));
  created.requests.push(requestId);

  /* register BEFORE the first junction/flip write */
  for (const f of facts) {
    created.slots.push({ id: f.slotId, requestId, originalVersion: f.originalVersion, flippedVersion: f.originalVersion + 1 });
  }

  for (const f of facts) {
    assertTestingDb();
    /* the junction insert only sees rows the guarded update produced, so a
       drifted row inserts nothing and the RETURNING id proves the flip */
    const flipped = sql(`WITH flipped AS (
        UPDATE time_slots SET status = 'pending', version = version + 1, updated_at = now()
         WHERE id = ${f.slotId} AND status = 'available'
           AND version = ${f.originalVersion} AND class_session_id IS NULL
        RETURNING id
      )
      INSERT INTO replacement_request_slots (replacement_request_id, time_slot_id, created_at, updated_at)
      SELECT ${requestId}, id, now(), now() FROM flipped
      RETURNING time_slot_id`);
    if (Number(flipped) !== f.slotId) {
      throw new Error(`slot ${f.slotId} changed under the fixture (expected available v${f.originalVersion}) — flip refused`);
    }
  }
}

/** Full-set cleanup in ONE atomic DO block. It row-locks the registered
 *  requests then the slot ids (each sorted), re-verifies inside that same
 *  transaction every tracked row's exact original/flipped ownerless shape and
 *  that no foreign live claim exists (anchor equality AND fallback-span
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
  const slotIds = created.slots.map((s) => s.id).join(',');
  const slotLock = slotIds
    ? `PERFORM 1 FROM time_slots WHERE id IN (${slotIds}) ORDER BY id FOR UPDATE;`
    : '';

  const requestGuards = created.requests.map((id) => `
    IF (SELECT status FROM replacement_requests WHERE id = ${id}) IS DISTINCT FROM 'pending' THEN
      RAISE EXCEPTION 'request ${id} is gone or no longer pending — refusing blind cleanup';
    END IF;`).join('\n');

  const slotGuards = created.slots.map((s) => `
    SELECT ts.status, ts.version,
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
      INTO v_status, v_version, v_junction, v_anchor, v_span
      FROM time_slots ts WHERE ts.id = ${s.id};
    IF NOT FOUND THEN
      RAISE EXCEPTION 'slot ${s.id} vanished — another writer removed it; refusing to touch anything';
    END IF;
    IF EXISTS (SELECT 1 FROM time_slots WHERE id = ${s.id} AND class_session_id IS NOT NULL) THEN
      RAISE EXCEPTION 'slot ${s.id} has a class_session_id inside the locked transaction — refusing blind restore';
    END IF;
    IF v_anchor <> 0 OR v_span <> 0 THEN
      RAISE EXCEPTION 'slot ${s.id} carries a foreign live claim (anchor=%, span=%) — refusing blind restore', v_anchor, v_span;
    END IF;
    IF v_status = 'pending' AND v_version = ${s.flippedVersion} AND v_junction = '${s.requestId}' THEN
      v_flipped := array_append(v_flipped, ${s.id});
    ELSIF NOT (v_status = 'available' AND v_version = ${s.originalVersion} AND v_junction = '') THEN
      RAISE EXCEPTION 'slot ${s.id} drifted (status=%, version=%, junction=%) — refusing blind restore', v_status, v_version, v_junction;
    END IF;`).join('\n');

  const restoreGuards = created.slots.map((s) => `
    IF ${s.id} = ANY (v_flipped) THEN
      UPDATE time_slots SET status = 'available', version = version + 1, updated_at = now()
       WHERE id = ${s.id} AND status = 'pending' AND version = ${s.flippedVersion};
      GET DIAGNOSTICS v_cnt = ROW_COUNT;
      IF v_cnt <> 1 THEN
        RAISE EXCEPTION 'slot ${s.id} changed between the cleanup checks and its restore — refusing to guess';
      END IF;
    END IF;`).join('\n');

  sql(`DO $ownership$ DECLARE
         v_status text; v_version int; v_junction text; v_anchor int; v_span int;
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
       END $ownership$;`);

  created.requests.length = 0;
  created.slots.length = 0;
}

/**
 * Land on a DATA week through the page's own week selector.
 *
 * The `?week=` deep link is off by one on this page (measured: `?week=7` lands
 * on data week 8, i.e. array index 7) — a pre-existing arrival quirk, not this
 * lane's business. The selector's option VALUES are array indexes, so
 * `week - 1` is the honest way to ask for a data week, and it is the same path
 * a user takes.
 */
async function gotoWeek(page: Page, sessionId: number, week: number, venue: string = VENUE): Promise<void> {
  await page.goto(`${BASE}/replacement-arrangement?session=${sessionId}&venue=${venue}&_=${Date.now()}`);
  await expect(page.locator('#subjectSelector')).toBeVisible({ timeout: 30_000 });
  await page.waitForFunction(() => document.querySelectorAll('#tableBody .cell-content').length > 0);

  // pick "Week N" by its LABEL (the option values are array indexes — asking
  // for the data week directly is the only honest, user-level way in)
  const value = await page.evaluate((w) => {
    const sel = document.getElementById('weekSelector') as HTMLSelectElement | null;
    const opt = Array.from(sel?.options ?? []).find((o) => new RegExp('^Week\\s+' + w + '\\b').test(o.textContent ?? ''));
    return opt ? opt.value : null;
  }, week);

  expect(value, `the week selector must offer "Week ${week}" (bookable weeks only)`).not.toBeNull();
  await page.selectOption('#weekSelector', value as string);
  await page.waitForFunction(() => document.querySelectorAll('#tableBody .cell-content').length > 0);
}

/** The fixture must still exist — a concurrent re-seed deletes it (see header). */
function assertFixtureAlive(): void {
  const alive = Number(sql(`SELECT count(*) FROM replacement_requests WHERE id IN (${created.requests.join(',')})`));
  expect(
    alive,
    'the fixture vanished mid-run — another spec re-seeded the database; run THIS spec alone',
  ).toBe(created.requests.length);
}

/**
 * arrangement-cell-state-labels (user 2026-11-07): hover a cell and read the
 * words it shows. The label is a real span now, so the receipt can assert the
 * TEXT, that it stays INSIDE the cell box (4A: wrap + shrink, never overflow),
 * and that the retired `::after` overlay is gone.
 */
async function hoverLabel(p: Page, locator: ReturnType<Page['locator']>) {
  await locator.hover();
  await p.waitForTimeout(220);

  return locator.evaluate((el) => {
    const span = el.querySelector('.cell-state-label') as HTMLElement | null;
    const cell = el.getBoundingClientRect();
    const box = span ? span.getBoundingClientRect() : null;
    return {
      html: span ? span.innerHTML : null,
      // innerText (not textContent): a `<br>` is a real line break there, so the
      // receipt reads the words a person sees ("Other Pending", never "OtherPending")
      text: span ? (span.innerText || span.textContent || '').replace(/\s+/g, ' ').trim() : null,
      display: span ? getComputedStyle(span).display : 'none',
      inside: box
        ? box.top >= cell.top - 1 && box.left >= cell.left - 1
          && box.bottom <= cell.bottom + 1 && box.right <= cell.right + 1
        : false,
      fits: span ? span.scrollHeight <= span.clientHeight + 1 : false,
      after: getComputedStyle(el, '::after').content,
      tip: el.getAttribute('data-tip'),
    };
  });
}

/** Every label the cells may show (user answers 1A/2A). */
const ALLOWED_LABELS = [
  'Your Pending',
  'Other Pending',
  'Venue Conflict',
  'Cohorts Conflict',
  'Your Conflict',
  'Your & Cohorts Conflict',
];

async function login(p: Page, staffId: '5425' | '5770'): Promise<void> {
  await p.goto(`${BASE}/login/staff?_=ownership-${staffId}-${Date.now()}`);
  await p.fill('#login_id', staffId);
  await p.fill('#password', 'Tarumt@2026');
  await p.click('#loginBtn');
  await p.waitForURL(/my-timetable|dashboard/, { timeout: 60_000 });
}

test.describe.configure({ mode: 'serial', timeout: 120_000 });

/* ── T9a — the two families on a real grid (WRITES: guarded fixture) ───── */
test.describe('ownership on the grid', () => {
  let pairA: SlotPair;   // staff 5770's pending
  let pairB: SlotPair;   // staff 5425's pending
  let fx: { semesterId: number; ownerSession: number; ownerDay: number; ownerHour: number; viewerSession: number; proposalWeek: number };
  let ownerCtx: BrowserContext;
  let viewerCtx: BrowserContext;
  let ownerPage: Page;
  let viewerPage: Page;

  test.beforeAll(async ({ browser }) => {
    assertTestingDb();
    const sem = discoverSemester();
    const identity = discoverIdentity(sem.id);
    const proposalWeek = discoverProposalWeek(sem);
    fx = {
      semesterId: sem.id,
      ownerSession: identity.id,
      ownerDay: identity.day,
      ownerHour: identity.hour,
      viewerSession: identity.viewerSession,
      proposalWeek,
    };
    [pairA, pairB] = discoverTwoPairs(proposalWeek, sem.id);
    insertPending(identity.id, OWNER_STAFF, pairA, sem.id, discoverSourceWeek(sem, identity.id, proposalWeek));
    insertPending(identity.viewerSession, VIEWER_STAFF, pairB, sem.id, discoverSourceWeek(sem, identity.viewerSession, proposalWeek));

    /* ONE CONTEXT PER IDENTITY. A second login on the same context does not
       switch the session (measured: the form posts, /dashboard loads, the
       identity stays the first user) — so the two views must never share
       cookies, or the receipt would compare a page against itself. */
    ownerCtx = await browser.newContext();
    ownerPage = await ownerCtx.newPage();
    await login(ownerPage, '5770');

    viewerCtx = await browser.newContext();
    viewerPage = await viewerCtx.newPage();
    await login(viewerPage, '5425');
  });

  test.afterAll(async () => {
    await ownerCtx?.close();
    await viewerCtx?.close();
    cleanupFixture();
  });

  test('each staff member sees their own pending named as theirs, the other one flat', async () => {
    const cellOn = (p: Page, day: number, hour: number) => p.locator(`#tableBody td[data-day="${day}"][data-hour="${hour}"] .cell-content`);
    const ringWidth = (locator: ReturnType<typeof cellOn>) => locator.evaluate((el) => getComputedStyle(el).borderTopWidth);

    /* ── 1. staff 5770 — pair A is HIS, pair B is hers; BOTH borderless ── */
    assertFixtureAlive();
    await gotoWeek(ownerPage, fx.ownerSession, fx.proposalWeek);

    const ownerPending = cellOn(ownerPage, pairA.day, pairA.hours[0]);
    await expect(ownerPending).toHaveClass(/cell-pending/);
    /* 2026-11-11 (user): no ownership ring on ANY family — colour alone says
       "cannot book"; the words say whose it is. */
    expect(await ringWidth(ownerPending)).toBe('0px');

    // the tip is the pending-make-up header (the twin merge keeps ONE block)
    const tip = await ownerPending.getAttribute('data-tip');
    expect(tip, 'the cell tip is attached').toBeTruthy();
    expect(tip!.split('\n')[0]).toContain('Pending');
    expect(await ownerPending.getAttribute('aria-label')).toContain('Pending — ');

    const ownerSeesOthers = cellOn(ownerPage, pairB.day, pairB.hours[0]);
    await expect(ownerSeesOthers).toHaveClass(/cell-reserved/);
    await expect(ownerSeesOthers).not.toHaveClass(/cell-pending/);
    expect(await ringWidth(ownerSeesOthers)).toBe('0px');

    /* THE ENGINE ARM ALONE — the actual bug. The pending slot sits in B010, but
       the busy vector is a TIME vector: viewed from ANY room it still blocks,
       and that ownerless vector is where another staff member's pending used to
       paint itself "Pending (You)". Here the page's own payload (real tuples,
       real owner flag) is fed to the page's own resolver with NO grid row —
       exactly the arm-only situation — for both viewers. */
    const engineArm = await ownerPage.evaluate(({ sid, weekIdx, day, hour }) => {
      const rows = ((window as any).PAGE_DATA.busyByWeek?.[String(sid)]?.[weekIdx] ?? [])
        .filter((r: any[]) => r[0] === day && r[1] === hour && r[4] === 'pending');
      return {
        rows,
        verdict: (window as any).classifyCell({ gridStatus: null, engineMatches: rows, engineBlocked: rows.length > 0 }),
      };
    }, { sid: fx.ownerSession, weekIdx: fx.proposalWeek - 1, day: pairA.day, hour: pairA.hours[0] });
    expect(engineArm.rows.length).toBeGreaterThan(0);
    expect(engineArm.rows.every((r: any[]) => r[5] === true), "the owner's own make-up is flagged mine").toBe(true);
    expect(engineArm.verdict).toBe('cell-pending');

    // his own class (the DISCOVERED owner session, in B010) → red, still MINE
    // by class, no ring
    const ownerOwnClass = cellOn(ownerPage, fx.ownerDay, fx.ownerHour);
    await expect(ownerOwnClass).toHaveClass(/cell-occupied/);
    await expect(ownerOwnClass).toHaveClass(/cell-occupied--mine/);
    expect(await ringWidth(ownerOwnClass)).toBe('0px');

    /* ── 2. staff 5425 — the mirror image on the same grid ───────────── */
    assertFixtureAlive();
    await gotoWeek(viewerPage, fx.viewerSession, fx.proposalWeek);

    const viewerSeesOthers = cellOn(viewerPage, pairA.day, pairA.hours[0]);
    await expect(viewerSeesOthers).toHaveClass(/cell-reserved/);
    await expect(viewerSeesOthers).not.toHaveClass(/cell-pending/);
    expect(await ringWidth(viewerSeesOthers)).toBe('0px');

    const viewerPending = cellOn(viewerPage, pairB.day, pairB.hours[0]);
    await expect(viewerPending).toHaveClass(/cell-pending/);
    expect(await ringWidth(viewerPending)).toBe('0px');

    // the engine arm alone again — the SAME time, from the OTHER viewer
    const viewerEngineArm = await viewerPage.evaluate(({ sid, weekIdx, day, hour }) => {
      const rows = ((window as any).PAGE_DATA.busyByWeek?.[String(sid)]?.[weekIdx] ?? [])
        .filter((r: any[]) => r[0] === day && r[1] === hour && r[4] === 'pending');
      return {
        rows,
        verdict: (window as any).classifyCell({ gridStatus: null, engineMatches: rows, engineBlocked: rows.length > 0 }),
      };
    }, { sid: fx.viewerSession, weekIdx: fx.proposalWeek - 1, day: pairA.day, hour: pairA.hours[0] });
    expect(viewerEngineArm.rows.length).toBeGreaterThan(0);
    expect(viewerEngineArm.rows.every((r: any[]) => r[5] === false), 'the same make-up is NOT hers').toBe(true);
    expect(viewerEngineArm.verdict, "engine arm alone: someone else's pending → amber, no ring").toBe('cell-reserved');

    // the owner's class (the SAME discovered coordinates) is someone else's
    // from her side → red, no ring
    const viewerOthersClass = cellOn(viewerPage, fx.ownerDay, fx.ownerHour);
    await expect(viewerOthersClass).toHaveClass(/cell-occupied/);
    await expect(viewerOthersClass).not.toHaveClass(/cell-occupied--mine/);
    expect(await ringWidth(viewerOthersClass)).toBe('0px');

    /* ── 3. the HOVER WORDS (arrangement-cell-state-labels) ──────────── */
    const ownerPendingLabel = await hoverLabel(ownerPage, ownerPending);
    expect(ownerPendingLabel.text, 'your own pending cell says so').toBe('Your Pending');
    expect(ownerPendingLabel.display, 'the label shows on hover').toBe('flex');
    expect(ownerPendingLabel.inside, 'the label stays inside the cell').toBe(true);
    expect(ownerPendingLabel.fits, 'the label is not clipped').toBe(true);
    expect(ownerPendingLabel.after, 'the old CSS overlay is retired where the span exists').toBe('none');
    expect(ownerPendingLabel.tip, 'the tooltip is untouched').toContain('Pending');

    const ownerOthersLabel = await hoverLabel(ownerPage, ownerSeesOthers);
    expect(ownerOthersLabel.text, "another staff member's pending says so").toBe('Other Pending');
    expect(ownerOthersLabel.inside).toBe(true);
    expect(ownerOthersLabel.fits).toBe(true);
    expect(ownerOthersLabel.after).toBe('none');

    /* the tip sits ABOVE the cell (user 2026-11-07) — the shared engine's default
       placement, flipping below only when there is no room. It used to be pinned
       `left`, which laid a multi-line block of text over the day columns. */
    const tipGeo = await ownerPage.evaluate(() => {
      const tip = document.querySelector('.data-tip-tooltip') as HTMLElement | null;
      const anchor = document.querySelector('#tableBody .cell-content:hover');
      if (!tip || !anchor) return null;
      const t = tip.getBoundingClientRect();
      const a = anchor.getBoundingClientRect();
      return {
        above: t.bottom <= a.top + 8,
        overlaps: !(t.right < a.left || t.left > a.right),
        opacity: getComputedStyle(tip).opacity,
        pinnedLeft: anchor.getAttribute('data-tip-pos'),
      };
    });
    expect(tipGeo, 'the hovered cell shows its tip').not.toBeNull();
    expect(tipGeo!.above, 'the tip sits ABOVE the hour cell').toBe(true);
    expect(tipGeo!.overlaps, 'and stays over that cell horizontally').toBe(true);
    expect(tipGeo!.opacity).toBe('1');
    expect(tipGeo!.pinnedLeft, 'no left-pin any more').toBeNull();

    const ownerOwnClassLabel = await hoverLabel(ownerPage, ownerOwnClass);
    expect(ownerOwnClassLabel.text, 'your own class → "Your Conflict"').toBe('Your Conflict');

    /* the tooltip's why-tag says YOUR, not OWN (user 2026-11-07) — the cell label
       and the tag must not use two different words for the same fact */
    const ownTipText = await ownerPage.evaluate(() =>
      (document.querySelector('.data-tip-tooltip') as HTMLElement | null)?.innerText ?? '');
    expect(ownTipText).toContain('Your conflict');
    expect(ownTipText).not.toContain('Own conflict');
    expect(ownerOwnClassLabel.inside).toBe(true);
    expect(ownerOwnClassLabel.fits).toBe(true);
    expect(ownerOwnClassLabel.after).toBe('none');

    /* the grid-wide sweep: no cell may invent a label outside the six. The span
       is `display:none` until hover, and `innerText` falls back to textContent
       for a hidden node — so the words are read from the MARKUP, where a `<br>`
       becomes the space a person sees. `.cell-too-soon` labels are the
       separately specified lead-time flags (the 3-working-day rule), not
       ownership wording, so they are excluded from the six-label sweep and
       pinned by their own class instead — nothing arbitrary is waved through. */
    const labelsOnGrid = await viewerPage.evaluate(() =>
      Array.from(document.querySelectorAll('#tableBody .cell-content .cell-state-label'))
        .map((el) => ({
          tooSoon: Boolean(el.closest('.cell-too-soon')) && el.classList.contains('cell-state-label--too-soon'),
          text: el.innerHTML
            .replace(/<br\s*\/?>/gi, ' ')
            .replace(/<[^>]*>/g, '')
            .replace(/&amp;/g, '&')
            .replace(/\s+/g, ' ')
            .trim(),
        })));
    expect(labelsOnGrid.length, 'the grid shows labels at all').toBeGreaterThan(0);
    const ownershipLabels = labelsOnGrid.filter((l) => !l.tooSoon).map((l) => l.text);
    expect(ownershipLabels.length, 'ownership labels are on the grid').toBeGreaterThan(0);
    expect(Array.from(new Set(ownershipLabels)).every((t) => ALLOWED_LABELS.includes(t))).toBe(true);
    /* anything NOT in the six must be a `.cell-too-soon` lead-time flag — a
       label may not dodge the sweep by being merely unknown wording. */
    const offSweep = labelsOnGrid.filter((l) => !ALLOWED_LABELS.includes(l.text));
    expect(offSweep.every((l) => l.tooSoon), 'every label outside the six is a .cell-too-soon flag').toBe(true);

    /* receipts: one grid showing every state at once, both themes. The page's
       OWN toggle is used — the app defaults to dark, so a hand-added `dark`
       class would produce two identical images. */
    const themeName = await viewerPage.evaluate(() => (document.documentElement.classList.contains('dark') ? 'dark' : 'light'));
    await viewerPage.screenshot({ path: `/tmp/opencode/arrangement-ownership-${themeName}.png`, fullPage: true });
    await viewerPage.evaluate(() => (window as any).toggleTheme?.());
    await viewerPage.waitForTimeout(400);
    const other = themeName === 'dark' ? 'light' : 'dark';
    await viewerPage.screenshot({ path: `/tmp/opencode/arrangement-ownership-${other}.png`, fullPage: true });
  });
});

/* ── T9b — the resolver's precedence table + the legend (read-only) ───── */
test.describe('resolver precedence and the legend', () => {
  let context: BrowserContext;
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext();
    page = await context.newPage();
    await login(page, '5425');
    await page.goto(`${BASE}/replacement-arrangement?venue=${VENUE}&_=${Date.now()}`);
    await page.waitForFunction(() => typeof (window as any).classifyCell === 'function');
  });

  test.afterAll(async () => { await context?.close(); });

  test('the five rules and the three crossing cases', async () => {
    const verdicts = await page.evaluate(() => {
      const mine = (status: string | null) => [4, 12, 16, 'block', status, true];
      const theirs = (status: string | null) => [4, 12, 16, 'block', status, false];
      const classify = (facts: any) => (window as any).classifyCell(facts);

      return {
        ownPendingGrid: classify({ gridStatus: 3, engineMatches: [], engineBlocked: false }),
        ownPendingEngine: classify({ gridStatus: null, engineMatches: [mine('pending')], engineBlocked: true }),
        othersPendingGrid: classify({ gridStatus: 4, engineMatches: [], engineBlocked: false }),
        othersPendingEngine: classify({ gridStatus: null, engineMatches: [theirs('pending')], engineBlocked: true }),
        ownConflict: classify({ gridStatus: null, engineMatches: [mine(null)], engineBlocked: true }),
        cohortConflict: classify({ gridStatus: null, engineMatches: [theirs(null)], engineBlocked: true }),
        gridOccupied: classify({ gridStatus: 1, engineMatches: [], engineBlocked: false }),
        free: classify({ gridStatus: null, engineMatches: [], engineBlocked: false }),
        // crossings
        ownPendingBeatsOthers: classify({ gridStatus: 3, engineMatches: [theirs('pending')], engineBlocked: true }),
        othersPendingBeatsMyClass: classify({ gridStatus: 4, engineMatches: [mine(null)], engineBlocked: true }),
        twinRowsMineWins: classify({ gridStatus: null, engineMatches: [mine(null), theirs(null)], engineBlocked: true }),
        fiveKeyTupleIsNotOthers: classify({ gridStatus: null, engineMatches: [[4, 12, 16, 'block', 'pending']], engineBlocked: true }),
      };
    });

    expect(verdicts.ownPendingGrid).toBe('cell-pending');
    expect(verdicts.ownPendingEngine).toBe('cell-pending');
    expect(verdicts.othersPendingGrid).toBe('cell-reserved');
    expect(verdicts.othersPendingEngine).toBe('cell-reserved');
    expect(verdicts.ownConflict).toBe('cell-occupied cell-occupied--mine');
    expect(verdicts.cohortConflict).toBe('cell-occupied');
    expect(verdicts.gridOccupied).toBe('cell-occupied');
    expect(verdicts.free).toBeNull();

    // the crossings the design writes down on purpose
    expect(verdicts.ownPendingBeatsOthers).toBe('cell-pending');
    expect(verdicts.othersPendingBeatsMyClass, 'another staff\'s pending beats my own class → amber, no ring').toBe('cell-reserved');
    expect(verdicts.twinRowsMineWins, 'is ANY row mine? → the mine class').toBe('cell-occupied cell-occupied--mine');
    expect(verdicts.fiveKeyTupleIsNotOthers, 'a stale 5-key tuple is not "others"').toBe('cell-occupied');
  });

  /* arrangement-cell-state-labels (user 2026-11-07, answers 1A 2A 3A 4A):
     the words are DATA-DRIVEN — `mine` for ownership, the 7th tuple key `arms`
     for the reason, the grid payload's status 1 for the room. Nothing parses a
     holder string. This pins all six labels and their class beside them. */
  test('the six hover labels come from the data, not from the colour', async () => {
    const verdicts = await page.evaluate(() => {
      // [day, start, end, label, statusKind, mine, arms]
      const minePending = [4, 12, 16, 'block', 'pending', true, ['own']];
      const otherPending = [4, 12, 16, 'block', 'pending', false, ['cohort']];
      const myClass = [4, 12, 16, 'block', null, true, ['own']];
      const cohortClass = [4, 12, 16, 'block', null, false, ['cohort']];
      const twin = [4, 12, 16, 'block', null, true, ['own', 'cohort']];
      const state = (facts: any) => (window as any).classifyState(facts);

      return {
        yourPendingGrid: state({ gridStatus: 3, engineMatches: [], engineBlocked: false }),
        yourPendingEngine: state({ gridStatus: null, engineMatches: [minePending], engineBlocked: true }),
        otherPendingGrid: state({ gridStatus: 4, engineMatches: [], engineBlocked: false }),
        otherPendingEngine: state({ gridStatus: null, engineMatches: [otherPending], engineBlocked: true }),
        yourConflict: state({ gridStatus: null, engineMatches: [myClass], engineBlocked: true }),
        cohortsConflict: state({ gridStatus: null, engineMatches: [cohortClass], engineBlocked: true }),
        yourAndCohorts: state({ gridStatus: null, engineMatches: [twin], engineBlocked: true }),
        venueConflict: state({ gridStatus: 1, engineMatches: [], engineBlocked: false }),
        staleTuple: state({ gridStatus: null, engineMatches: [[4, 12, 16, 'block', null]], engineBlocked: true }),
        free: state({ gridStatus: null, engineMatches: [], engineBlocked: false }),
        // the words and the class must always agree — one resolver, one answer
        labelOfYourConflict: (window as any).cellStateLabel({ gridStatus: null, engineMatches: [myClass], engineBlocked: true }),
      };
    });

    expect(verdicts.yourPendingGrid).toEqual({ cls: 'cell-pending', label: 'Your Pending' });
    expect(verdicts.yourPendingEngine).toEqual({ cls: 'cell-pending', label: 'Your Pending' });
    expect(verdicts.otherPendingGrid).toEqual({ cls: 'cell-reserved', label: 'Other<br>Pending' });
    expect(verdicts.otherPendingEngine).toEqual({ cls: 'cell-reserved', label: 'Other<br>Pending' });
    expect(verdicts.yourConflict).toEqual({ cls: 'cell-occupied cell-occupied--mine', label: 'Your<br>Conflict' });
    expect(verdicts.cohortsConflict).toEqual({ cls: 'cell-occupied', label: 'Cohorts<br>Conflict' });
    expect(verdicts.yourAndCohorts, 'a twin keeps BOTH arms').toEqual({
      cls: 'cell-occupied cell-occupied--mine',
      label: 'Your &amp; Cohorts<br>Conflict',
    });
    expect(verdicts.venueConflict).toEqual({ cls: 'cell-occupied', label: 'Venue<br>Conflict' });
    expect(verdicts.staleTuple, 'a tuple with no arm keeps the old word').toEqual({ cls: 'cell-occupied', label: 'Occupied' });
    expect(verdicts.free).toBeNull();
    expect(verdicts.labelOfYourConflict).toBe('Your<br>Conflict');
  });

  test('the legend draws the four looks the grid actually paints', async () => {
    /* 2026-11-11 (user): the ownership ring is gone from both families, so the
       legend lost the extra rows that only differed by a ring AND the
       "thick border (3px) = yours" hint line. Four rows, every swatch flat. */
    const rows = page.locator('.legend-bar .legend-item');
    await expect(rows).toHaveCount(4);

    const expected = [
      ['Available', 'cell-available'],
      ['Your Current Selection', 'event-selection'],
      ['Pending', 'cell-pending'],
      ['Conflict', 'cell-occupied'],
    ];

    for (let i = 0; i < expected.length; i += 1) {
      const row = rows.nth(i);
      await expect(row).toContainText(expected[i][0]);
      await expect(row.locator('.legend-swatch')).toHaveClass(new RegExp(expected[i][1]));
      await expect(await row.getAttribute('data-tip')).toBeTruthy();
    }

    /* the swatch must equal the cell it stands for: no ring anywhere except the
       blue "Your Current Selection" chip, which is an outline by design */
    for (let i = 0; i < expected.length; i += 1) {
      if (i === 1) continue;
      const border = await rows.nth(i).locator('.legend-swatch')
        .evaluate((el) => getComputedStyle(el).borderTopWidth);
      expect(border, `row ${i} ("${expected[i][0]}") is borderless`).toBe('0px');
    }

    // tip wording (capitals = emphasis; a data-tip is plain text)
    const tips = await rows.evaluateAll((els) => els.map((el) => el.getAttribute('data-tip') ?? ''));
    expect(tips[0]).toContain('FREE SLOT — CLICK');
    expect(tips[1]).toContain('SELECTED');
    expect(tips[2]).toContain('WAITING');
    /* 2026-11-17 (P2 pointer contract): the gesture verb left the copy. "HOVER the slot"
       named one input mode where the app runs on three — the action is a hover, a tap or
       a focus depending on the device, so the tips now say OPEN, which is true on all of
       them. Asserted for the same reason the old HOVER was: the sentence must name a way
       to actually see the reason. */
    expect(tips[2]).toContain('OPEN');
    expect(tips[2]).not.toMatch(/HOVER/i);
    expect(tips[3]).toContain('OPEN');
    expect(tips[3]).not.toMatch(/HOVER/i);
    expect(tips[3]).toContain('CANNOT BOOK');
    expect(tips[3]).toContain('VENUE CONFLICT');
    expect(tips[3]).toContain('COHORT CONFLICT');
    /* the border language is gone from BOTH tips — it described a ring the page
       no longer paints (the exact lie this lane exists to prevent) */
    expect(tips[2]).not.toContain('thick border');
    expect(tips[3]).not.toContain('thick border');
    expect(tips[3]).not.toMatch(/[<>]/, 'a <br> would print literally — the break is a newline');
    expect(tips[3].split('\n')).toHaveLength(2);

    // the ownership hint line is deleted, not re-worded
    await expect(page.locator('.legend-ownership-hint')).toHaveCount(0);
    await expect(page.locator('.legend-bar .osd-thick')).toHaveCount(0);
    await expect(page.locator('.legend-bar .osd-thin')).toHaveCount(0);

    /* The swatches borrow the CELL classes, and those carry a "hover label"
       overlay (`.cell-occupied::after` …) meant to fill a grid CELL. On a swatch
       it had no positioned ancestor, so on mouse-out it painted the whole
       viewport in the state colour for the 0.15s fade (the 2026-11-07 flash).
       A swatch must therefore never generate that pseudo box — while hovering or
       after leaving — and the grid cell must still get its label. */
    const swatches = page.locator('.legend-bar .legend-swatch');
    const count = await swatches.count();
    for (let i = 0; i < count; i += 1) {
      const sw = swatches.nth(i);
      expect(
        await sw.evaluate((el) => getComputedStyle(el, '::after').content),
        `legend swatch ${i} paints no hover-label overlay`,
      ).toBe('none');
      await sw.hover();
      await page.waitForTimeout(200);
      expect(
        await sw.evaluate((el) => getComputedStyle(el, '::after').content),
        `legend swatch ${i} stays overlay-free on hover`,
      ).toBe('none');
      await page.mouse.move(4, 4);
      await page.waitForTimeout(40);
      const leaving = await sw.evaluate((el) => {
        const cs = getComputedStyle(el, '::after');
        return { content: cs.content, width: cs.width };
      });
      expect(leaving.content, `legend swatch ${i} paints nothing on mouse-out`).toBe('none');
      expect(leaving.width, `legend swatch ${i} is never viewport-sized`).not.toBe('1440px');
    }

    /* …and the grid keeps the affordance the swatches gave up — since
       arrangement-cell-state-labels (2026-11-07) the grid's words are the REAL
       span, so the receipt follows them there: the cell still says WHY it is
       blocked, the span fills the cell, and the retired CSS overlay stays
       `none` on the grid too (one mechanism, not two). */
    const cell = page.locator('.cell-content.cell-occupied').first();
    await cell.hover();
    await page.waitForTimeout(220);
    const gridReceipt = await cell.evaluate((el) => {
      const span = el.querySelector('.cell-state-label') as HTMLElement | null;
      const rect = el.getBoundingClientRect();
      const box = span ? span.getBoundingClientRect() : null;
      return {
        after: getComputedStyle(el, '::after').content,
        display: span ? getComputedStyle(span).display : 'none',
        words: span ? span.innerHTML.replace(/<br\s*\/?>/gi, ' ') : '',
        // a cell's own border (if it has one) sits outside the label's containing
        // block, so "fills" means: inside the cell, and never the viewport-shaped
        // overlay of 2026-11-07
        fills: box
          ? box.width <= rect.width + 1 && box.height <= rect.height + 1
            && box.width > rect.width * 0.8 && box.height > rect.height * 0.8
          : false,
      };
    });
    expect(gridReceipt.after, 'the old overlay is retired on the grid').toBe('none');
    expect(gridReceipt.display, 'the state label shows on hover').toBe('flex');
    expect(gridReceipt.words, 'a red cell names its reason').toMatch(/Conflict/);
    expect(gridReceipt.fills, 'the label fills its own cell, never the viewport').toBe(true);
  });
});
