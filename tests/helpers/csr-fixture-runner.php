<?php

/**
 * csr-fixture-runner.php — THE guarded fixture/action CLI for
 * tests/cancelled-slot-reuse.spec.ts (SDD cancelled-slot-reuse A20/A20b/A20c).
 *
 * What it exists for:
 *   · an ISOLATED fixture (own lecturer + cohort + module + lecture-hall room +
 *     one recurring source class 09:00–11:00 with its full-range original cells
 *     in every week) so the browser tests never ride the demo lecturers'
 *     unpredictable busy vectors;
 *   · REAL pre-cancellations: the withdraw/reject/auto-reject occurrences are
 *     cancelled through the app's OWN CancelClass action (exception + audit
 *     + original release), never hand-shaped SQL;
 *   · pending requests seeded through the REAL SubmitReplacementRequest action
 *     (the disclosed testing-CLI seam — the browser tests themselves stay on
 *     public UI actions);
 *   · the auto-reject leg through the REAL AutoRejectRequest action with a
 *     frozen PHP clock ONLY inside this process — the scheduled checker
 *     (requests:auto-reject, every 5 min) is NOT touched, not changed, not
 *     run, and no UI clock is frozen anywhere.
 *
 * Fail-closed contract (mirrors tests/helpers/receipt-target-guard.ts):
 *   · the module-level Playwright guard already refuses a non-testing target;
 *   · THIS runner re-checks before every command by querying the LIVE
 *     connection's current_database() — it refuses (exit 1, before any write)
 *     unless that query itself answers 'class_replacement_testing';
 *   · every invocation MUST set DB_DATABASE=class_replacement_testing in the
 *     child environment (the TS helper does; no --env=testing anywhere).
 *
 * Scope law: every delete is marker-scoped (module CSR901 / venue CRSX1 /
 * the two fixture emails). No table is ever wiped, no migrate:fresh, no
 * reseed, no global delete happens here — the main agent seeds the
 * DatabaseSeeder baseline (on an empty guarded testing DB) before the
 * browser run.
 *
 * Usage (from the repo root):
 *   DB_DATABASE=class_replacement_testing php tests/helpers/csr-fixture-runner.php target
 *   DB_DATABASE=class_replacement_testing php tests/helpers/csr-fixture-runner.php seed
 *   DB_DATABASE=class_replacement_testing php tests/helpers/csr-fixture-runner.php seed-pending <week>
 *   DB_DATABASE=class_replacement_testing php tests/helpers/csr-fixture-runner.php auto-reject <requestId> "YYYY-MM-DD HH:MM"
 *
 * Every command prints ONE JSON object on stdout (a `db` key first — the TS
 * helper re-checks it) and refuses with a non-zero exit + STDERR line.
 */

use App\Actions\AutoRejectRequest;
use App\Actions\CancelClass;
use App\Actions\SubmitReplacementRequest;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Support\WorkingHours;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

const TARGET_DB = 'class_replacement_testing';

/* ── fixture markers (the spec's only cleanup handles) ── */
const MARKER_MODULE = 'CSR901';
const MARKER_VENUE = 'CRSX1';
const MARKER_LECT_EMAIL = 'csr-fix-lect@tarc.edu.my';
const MARKER_STU_EMAIL = 'csr-fix-stu@student.tarc.edu.my';
const MARKER_STAFF_ID = '9871';
const MARKER_STUDENT_NO = '26CSR0001';

const FIXTURE_PASSWORD = 'Tarumt@2026';

/* the source class's immutable recurring coordinates: Tuesday 09:00–11:00,
   four 30-minute cells (slot indexes 2..5 on the shared 08:00 grid) */
const FIXTURE_DAY = 1;
const FIXTURE_START = '09:00:00';
const FIXTURE_END = '11:00:00';
const FIXTURE_TYPE = 'L';
const FIXTURE_CELL_STARTS = ['09:00:00', '09:30:00', '10:00:00', '10:30:00'];

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function refuse(string $why): never
{
    fwrite(STDERR, "csr-fixture-runner REFUSED: {$why}\n");
    exit(1);
}

function emit(array $payload): never
{
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
    exit(0);
}

/* ── THE pre-write gate: the LIVE connection's own current_database(). ── */
$liveDb = (string) (DB::select('select current_database() as db')[0]->db ?? '');
if ($liveDb !== TARGET_DB) {
    refuse("current_database() = [{$liveDb}]. This runner writes only [".TARGET_DB.']. '
        .'Run every command with DB_DATABASE='.TARGET_DB.' in the environment (no --env=testing).');
}

$command = $argv[1] ?? '';

/* ─────────────────────────────────────────────────────────────────────
 * Marker-scoped resolver + cleaner
 * ───────────────────────────────────────────────────────────────────── */

/**
 * Every id the fixture may have left behind, by MARKER only (never a table
 * count, never a "delete all"). Read-only.
 *
 * @return array<string, list<int>>
 */
function fixtureIds(): array
{
    $moduleIds = DB::table('modules')->where('module_code', MARKER_MODULE)->pluck('id')->map(fn ($v) => (int) $v)->all();
    $sessionIds = $moduleIds === []
        ? []
        : DB::table('class_sessions')->whereIn('module_id', $moduleIds)->pluck('id')->map(fn ($v) => (int) $v)->all();
    $venueIds = DB::table('venues')->where('room_code', MARKER_VENUE)->pluck('id')->map(fn ($v) => (int) $v)->all();
    $userIds = DB::table('users')->whereIn('email', [MARKER_LECT_EMAIL, MARKER_STU_EMAIL])->pluck('id')->map(fn ($v) => (int) $v)->all();

    $requestIds = [];
    if ($sessionIds !== [] || $userIds !== []) {
        $requestIds = DB::table('replacement_requests')
            ->where(function ($q) use ($sessionIds, $userIds): void {
                if ($sessionIds !== []) {
                    $q->orWhereIn('class_session_id', $sessionIds);
                }
                if ($userIds !== []) {
                    $q->orWhereIn('proposer_id', $userIds);
                }
            })
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    $slotIds = [];
    if ($venueIds !== [] || $sessionIds !== []) {
        $slotIds = DB::table('time_slots')
            ->where(function ($q) use ($venueIds, $sessionIds): void {
                if ($venueIds !== []) {
                    $q->orWhereIn('venue_id', $venueIds);
                }
                if ($sessionIds !== []) {
                    $q->orWhereIn('class_session_id', $sessionIds);
                }
            })
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    $cohortIds = [];
    $programme = DB::table('programmes')->where('programme_code', 'RSD')->first();
    if ($programme !== null) {
        $cohortIds = DB::table('cohorts')
            ->where('programme_id', $programme->id)
            ->where('current_year', 3)
            ->where('semester', 1)
            ->where('tutorial_group', 9)
            ->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    return [
        'moduleIds' => $moduleIds,
        'sessionIds' => $sessionIds,
        'venueIds' => $venueIds,
        'userIds' => $userIds,
        'requestIds' => $requestIds,
        'slotIds' => $slotIds,
        'cohortIds' => $cohortIds,
    ];
}

/** Delete ONLY the marker-scoped rows, in FK-safe order. */
function cleanFixture(): void
{
    $ids = fixtureIds();

    // audits first: they point at slots, requests and the fixture users
    DB::table('audit_logs')
        ->where(function ($q) use ($ids): void {
            if ($ids['slotIds'] !== []) {
                $q->orWhereIn('time_slot_id', $ids['slotIds']);
            }
            if ($ids['requestIds'] !== []) {
                $q->orWhereIn('replacement_request_id', $ids['requestIds']);
            }
            if ($ids['userIds'] !== []) {
                $q->orWhereIn('user_id', $ids['userIds']);
            }
        })
        ->delete();

    DB::table('notifications')
        ->where(function ($q) use ($ids): void {
            if ($ids['requestIds'] !== []) {
                $q->orWhereIn('request_id', $ids['requestIds']);
            }
            if ($ids['sessionIds'] !== []) {
                $q->orWhereIn('class_session_id', $ids['sessionIds']);
            }
            if ($ids['userIds'] !== []) {
                $q->orWhereIn('user_id', $ids['userIds']);
            }
        })
        ->delete();

    if ($ids['requestIds'] !== []) {
        DB::table('replacement_request_slots')->whereIn('replacement_request_id', $ids['requestIds'])->delete();
        DB::table('replacement_requests')->whereIn('id', $ids['requestIds'])->delete();
    }

    if ($ids['sessionIds'] !== []) {
        DB::table('class_exceptions')->whereIn('class_session_id', $ids['sessionIds'])->delete();
    }
    if ($ids['slotIds'] !== []) {
        DB::table('time_slots')->whereIn('id', $ids['slotIds'])->delete();
    }
    if ($ids['sessionIds'] !== []) {
        DB::table('session_cohorts')->whereIn('class_session_id', $ids['sessionIds'])->delete();
        DB::table('class_sessions')->whereIn('id', $ids['sessionIds'])->delete();
    }
    if ($ids['moduleIds'] !== []) {
        DB::table('modules')->whereIn('id', $ids['moduleIds'])->delete();
    }
    if ($ids['venueIds'] !== []) {
        DB::table('venues')->whereIn('id', $ids['venueIds'])->delete();
    }
    if ($ids['userIds'] !== []) {
        DB::table('students')->whereIn('user_id', $ids['userIds'])->delete();
        DB::table('users')->whereIn('id', $ids['userIds'])->delete();
    }
    if ($ids['cohortIds'] !== []) {
        DB::table('cohorts')->whereIn('id', $ids['cohortIds'])->delete();
    }
}

/** The fixture's session model, resolved by marker. */
function fixtureSession(): ClassSession
{
    $session = ClassSession::query()->whereHas('module', fn ($m) => $m->where('module_code', MARKER_MODULE))->first();

    return $session ?? refuse('the marker fixture (module '.MARKER_MODULE.') is not seeded — run `seed` first.');
}

/** The fixture lecturer's user model, resolved by marker email. */
function fixtureLecturer(): User
{
    $user = User::query()->where('email', MARKER_LECT_EMAIL)->first();

    return $user ?? refuse('the fixture lecturer user is missing — run `seed` first.');
}

/**
 * The first week whose Tuesday sits at least 7 calendar days after the REAL
 * 3-working-day notice cutoff — comfortably future, safely outside the live
 * checker's expiry window, inside the semester's 14-week bound, on a day with
 * no holiday. NO date is fabricated: if the semester cannot offer one, the
 * runner refuses (a documented fixed-date fixture would be a deliberate
 * decision, not a silent fallback).
 *
 * @return array{cutoff: Carbon, week: int, start: Carbon}
 */
function fixtureWeekPlan(): array
{
    $semester = Semester::query()->orderBy('id')->first();
    if ($semester === null) {
        refuse('no semester row — the baseline seed did not run.');
    }

    $start = Carbon::parse((string) $semester->start_date)->startOfDay();
    $cutoff = (new WorkingHours($semester))->noticeCutoff()->startOfDay();
    $horizon = $cutoff->copy()->addDays(7); // 7 calendar days ≥ 5 working days

    for ($w = 1; $w <= 11; $w++) {
        $tuesday = $start->copy()->addWeeks($w - 1)->addDays(FIXTURE_DAY);
        if ($tuesday->gte($horizon)) {
            return ['cutoff' => $cutoff, 'week' => $w, 'start' => $start];
        }
    }

    refuse('no comfortably future Tuesday remains inside the semester bounds '
        ."(cutoff {$cutoff->toDateString()}, end {$semester->end_date}) — refusing instead of fabricating a date.");
}

/** The week's four original cells, ascending start. */
function weekSlotIds(ClassSession $session, int $week): array
{
    return TimeSlot::query()
        ->where('semester_id', $session->semester_id)
        ->where('week_number', $week)
        ->where('day_of_week', FIXTURE_DAY)
        ->where('venue_id', $session->venue_id)
        ->whereIn('start_time', FIXTURE_CELL_STARTS)
        ->orderBy('start_time')
        ->pluck('id')
        ->map(fn ($v) => (int) $v)
        ->values()
        ->all();
}

/* ─────────────────────────────────────────────────────────────────────
 * Commands
 * ───────────────────────────────────────────────────────────────────── */

switch ($command) {
    case 'target':
        emit(['db' => $liveDb]);
        // no break — emit exits

    case 'seed':
        try {
            DB::transaction(function (): void {
                cleanFixture();
            });

            $built = DB::transaction(function () {
                $semester = Semester::query()->orderBy('id')->first();
                if ($semester === null) {
                    refuse('no semester row — the baseline seed did not run.');
                }
                $programme = DB::table('programmes')->where('programme_code', 'RSD')->first();
                $dept = DB::table('departments')->where('dept_code', 'DCIT')->first();
                if ($programme === null || $dept === null) {
                    refuse('reference rows (RSD programme / DCIT department) missing.');
                }

                $lecturerUser = User::create([
                    'honorific' => 'Ts.',
                    'name' => 'CSR Fixture Lecturer',
                    'email' => MARKER_LECT_EMAIL,
                    'password' => Hash::make(FIXTURE_PASSWORD),
                    'role' => 'lecturer',
                ]);
                Lecturer::create([
                    'user_id' => $lecturerUser->id,
                    'staff_id' => MARKER_STAFF_ID,
                    'dept_id' => $dept->id,
                    'is_pl' => false,
                ]);

                $cohort = Cohort::create([
                    'programme_id' => $programme->id,
                    'current_year' => 3,
                    'semester' => 1,
                    'tutorial_group' => 9,
                    'academic_year' => '2026/27',
                    'intake' => 'June 2026',
                    'student_count' => 5,
                ]);

                $studentUser = User::create([
                    'name' => 'CSR Fixture Student',
                    'email' => MARKER_STU_EMAIL,
                    'password' => Hash::make(FIXTURE_PASSWORD),
                    'role' => 'student',
                ]);
                Student::create([
                    'user_id' => $studentUser->id,
                    'student_id' => MARKER_STUDENT_NO,
                    'cohort_id' => $cohort->id,
                ]);

                $module = Module::create([
                    'module_code' => MARKER_MODULE,
                    'module_name' => 'CSR901 Cancelled-Slot-Reuse Fixture Module',
                    'allowed_session_types' => 'L',
                ]);

                $venue = Venue::create([
                    'room_code' => MARKER_VENUE,
                    'room_name' => 'Cancelled-Slot-Reuse Fixture Room '.MARKER_VENUE,
                    'capacity' => 40,
                    'room_type' => 'lecture_hall',
                    'allowed_session_types' => 'L',
                ]);

                $session = ClassSession::create([
                    'semester_id' => $semester->id,
                    'module_id' => $module->id,
                    'lecturer_id' => $lecturerUser->id,
                    'day_of_week' => FIXTURE_DAY,
                    'start_time' => FIXTURE_START,
                    'end_time' => FIXTURE_END,
                    'venue_id' => $venue->id,
                    'session_type' => FIXTURE_TYPE,
                ]);
                DB::table('session_cohorts')->insert([
                    'class_session_id' => $session->id,
                    'cohort_id' => $cohort->id,
                ]);

                /* the fresh dedicated room has NO preseeded grid — the source
                   class's OWN full range is the grid this fixture needs */
                foreach (range(1, (int) $semester->week_count) as $w) {
                    foreach (FIXTURE_CELL_STARTS as $cellStart) {
                        TimeSlot::create([
                            'semester_id' => $semester->id,
                            'class_session_id' => $session->id,
                            'week_number' => $w,
                            'day_of_week' => FIXTURE_DAY,
                            'start_time' => $cellStart,
                            'end_time' => Carbon::parse($cellStart)->addMinutes(30)->format('H:i:s'),
                            'venue_id' => $venue->id,
                            'status' => 'occupied',
                            'version' => 1,
                        ]);
                    }
                }

                return [
                    'sessionId' => (int) $session->id,
                    'lecturerUserId' => (int) $lecturerUser->id,
                    'studentUserId' => (int) $studentUser->id,
                    'cohortId' => (int) $cohort->id,
                    'moduleId' => (int) $module->id,
                    'venueId' => (int) $venue->id,
                    'semesterId' => (int) $semester->id,
                    'weekCount' => (int) $semester->week_count,
                ];
            });

            /* ── week plan + the REAL pre-cancellations (the app's own action) ── */
            $plan = fixtureWeekPlan();
            $approveWeek = $plan['week'];
            $weeks = [];
            foreach (['approve' => 0, 'withdraw' => 1, 'reject' => 2, 'autoReject' => 3] as $key => $offset) {
                $w = $approveWeek + $offset;
                $weeks[$key] = [
                    'week' => $w,
                    'date' => $plan['start']->copy()->addWeeks($w - 1)->addDays(FIXTURE_DAY)->toDateString(),
                ];
            }

            $session = fixtureSession();
            $lecturer = fixtureLecturer();
            foreach (['withdraw', 'reject', 'autoReject'] as $key) {
                $ok = (new CancelClass)($lecturer, $session, $weeks[$key]['week'], 'medical_leave');
                if (! $ok) {
                    throw new RuntimeException("the REAL CancelClass action refused week {$weeks[$key]['week']}.");
                }
                $weeks[$key]['slotIds'] = weekSlotIds($session, $weeks[$key]['week']);
            }
            $weeks['approve']['slotIds'] = weekSlotIds($session, $weeks['approve']['week']);

            emit([
                'db' => $liveDb,
                'marker' => [
                    'module' => MARKER_MODULE,
                    'venue' => MARKER_VENUE,
                    'lecturerEmail' => MARKER_LECT_EMAIL,
                ],
                'session' => [
                    'id' => $built['sessionId'],
                    'day' => FIXTURE_DAY,
                    'start' => FIXTURE_START,
                    'end' => FIXTURE_END,
                    'type' => FIXTURE_TYPE,
                    'durationSlots' => count(FIXTURE_CELL_STARTS),
                ],
                'lecturer' => [
                    'userId' => $built['lecturerUserId'],
                    'staffId' => MARKER_STAFF_ID,
                    'name' => 'CSR Fixture Lecturer',
                ],
                'student' => [
                    'userId' => $built['studentUserId'],
                    'studentNo' => MARKER_STUDENT_NO,
                ],
                'cohort' => [
                    'id' => $built['cohortId'],
                    'code' => 'RSD3(S1)G9',
                    'spaced' => 'RSD3 (S1) G9',
                ],
                'module' => ['id' => $built['moduleId'], 'code' => MARKER_MODULE],
                'venue' => ['id' => $built['venueId'], 'code' => MARKER_VENUE],
                'semester' => [
                    'id' => $built['semesterId'],
                    'weeks' => $built['weekCount'],
                    'noticeCutoff' => $plan['cutoff']->toDateString(),
                ],
                'weeks' => $weeks,
            ]);
        } catch (Throwable $e) {
            // leave nothing half-built behind
            try {
                DB::transaction(fn () => cleanFixture());
            } catch (Throwable) {
                // the refusal below still fires
            }
            refuse('seed failed, the fixture was rolled back: '.$e->getMessage());
        }
        // no break — emit exits

    case 'seed-pending':
        $week = (int) ($argv[2] ?? 0);
        if ($week < 1 || $week > 14) {
            refuse('seed-pending needs a week number 1..14.');
        }

        $session = fixtureSession();
        $lecturer = fixtureLecturer();

        $active = ReplacementRequest::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', $week)
            ->whereIn('status', ['pending', 'approved'])
            ->exists();
        if ($active) {
            refuse("week {$week} already holds an active request — re-run `seed` for a clean fixture.");
        }

        $anchor = TimeSlot::query()
            ->where('semester_id', $session->semester_id)
            ->where('week_number', $week)
            ->where('day_of_week', FIXTURE_DAY)
            ->where('venue_id', $session->venue_id)
            ->where('start_time', FIXTURE_START)
            ->first();
        if ($anchor === null) {
            refuse("week {$week}'s 09:00 anchor row is missing — the fixture grid was not created.");
        }
        if ((string) $anchor->status !== 'available') {
            refuse("week {$week}'s 09:00 anchor is [{$anchor->status}], not available — the occurrence was not released.");
        }

        /* THE disclosed seam: the REAL SubmitReplacementRequest action
           (engine pre-check → OCC reserve → junction rows), invoked from this
           guarded testing-CLI so the browser tests do not have to re-click the
           submit UI for every ending-lifecycle case. */
        $result = app(SubmitReplacementRequest::class)($lecturer, $session, $week, (int) $anchor->id, null, count(FIXTURE_CELL_STARTS));
        if (! $result['ok']) {
            refuse('the REAL SubmitReplacementRequest action refused: '
                .(string) ($result['conflict_reason'] ?? 'unknown'));
        }

        $request = ReplacementRequest::query()->findOrFail((int) $result['request_id']);

        emit([
            'db' => $liveDb,
            'requestId' => (int) $request->id,
            'week' => $week,
            'anchorSlotId' => (int) $request->replacement_time_slot_id,
            'span' => (int) ($request->replacement_span ?? 0),
            'junctionCount' => (int) DB::table('replacement_request_slots')
                ->where('replacement_request_id', $request->id)->count(),
        ]);
        // no break — emit exits

    case 'auto-reject':
        $requestId = (int) ($argv[2] ?? 0);
        $frozenNow = (string) ($argv[3] ?? '');
        if ($requestId < 1 || $frozenNow === '') {
            refuse('auto-reject needs <requestId> and a frozen "YYYY-MM-DD HH:MM" (MYT).');
        }

        $request = ReplacementRequest::query()->find($requestId);
        if ($request === null) {
            refuse("request {$requestId} does not exist.");
        }
        /* the ID alone is never enough — the row must BELONG to the marker
           fixture, or the runner refuses rather than touch a foreign request */
        $sessionIds = fixtureIds()['sessionIds'];
        if (! in_array((int) $request->class_session_id, $sessionIds, true)) {
            refuse("request {$requestId} does not belong to the marker fixture — refusing.");
        }
        if ((string) $request->status !== 'pending') {
            refuse("request {$requestId} is [{$request->status}], not pending.");
        }

        /* THE disclosed seam: the REAL AutoRejectRequest action with a frozen
           PHP clock inside THIS process only. The scheduled checker (every
           5 min) is untouched — nothing here changes or runs it, and no UI
           clock is frozen. The fixture's proposed start sits inside the
           3-working-hour window OF THE FROZEN CLOCK (that is what makes it a
           separately due fixture). */
        $frozen = Carbon::parse($frozenNow, 'Asia/Kuala_Lumpur');
        Carbon::setTestNow($frozen);
        $ok = (new AutoRejectRequest)($requestId);
        Carbon::setTestNow(); // clear before this process ends
        $request->refresh();

        emit([
            'db' => $liveDb,
            'ok' => (bool) $ok,
            'status' => (string) $request->status,
            'approverId' => $request->approver_id !== null ? (int) $request->approver_id : null,
            'decidedAt' => $request->decided_at !== null ? $request->decided_at->format('Y-m-d H:i:s') : null,
            'rejectionReason' => (string) ($request->rejection_reason ?? ''),
        ]);
        // no break — emit exits

    default:
        refuse("unknown command [{$command}] — use target | seed | seed-pending <week> | auto-reject <id> <frozen-now>.");
}
