<?php

namespace Tests\Support;

use App\Actions\CancelClass;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Support\LocalTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The shared deterministic world for the cancelled-slot-reuse feature matrix
 * (A09/A09b) and the two-connection race harness (A10/A10b) — SDD
 * cancelled-slot-reuse, design D4, spec S09–S12.
 *
 * THE CLOCK AND THE CALENDAR ARE FROZEN, wall-clock independent and far
 * outside the auto-reject checker's expiry window:
 *
 *   · frozen now  = Mon 8 Feb 2027, 09:00 MYT (Monday of week 2);
 *   · semester    = Mon 1 Feb 2027 – 30 May 2027, 14 weeks;
 *   · SOURCE_WEEK 2 — the cancelled occurrence is Thu 11 Feb. That date is the
 *     3rd working day after the frozen today (Tue 9, Wed 10, Thu 11), so the
 *     RELEASED ORIGINAL INTERVAL is exactly the FR 2.17 notice boundary — it
 *     becomes bookable the moment the cancellation commits;
 *   · the cancellation's own 2-working-day window passes too (the class date
 *     Thu 11 Feb ≥ today − 2 working days = Fri 5 Feb);
 *   · PIN_WEEK 5 — every requester's own conflicted occurrence, far past the
 *     lead time;
 *   · UNCANCELLED_WEEK 3 — Thu 18 Feb: the same class still runs there, and
 *     S10 pins that this occurrence keeps blocking after week 2 is cancelled.
 *
 * The builder is deliberately usable from BOTH test styles:
 *   · under RefreshDatabase every write rides the framework's wrapping
 *     transaction — nothing needs the registry;
 *   · under the committed-fixture concurrency harness (NO wrapping
 *     transaction, so the workers' independent backends can see the rows)
 *     every id lands in a registry and {@see cleanup()} deletes exactly those
 *     identities, children before parents, nothing else — never a bulk wipe.
 *
 * This file creates FIXTURES ONLY. It never weakens a rule and never runs
 * migrations; the concurrency harness guards the testing database before it
 * is allowed to write anything.
 */
final class CancelledSlotFixture
{
    /** The frozen campus now: Monday of week 2, 09:00 MYT. */
    public const FROZEN_NOW = '2027-02-08 09:00';

    /** Week 2 — the cancelled occurrence (Thu 11 Feb 2027). */
    public const SOURCE_WEEK = 2;

    /** Week 3 — the same class still runs (Thu 18 Feb 2027). */
    public const UNCANCELLED_WEEK = 3;

    /** Week 5 — every requester's own conflicted occurrence (the pin). */
    public const PIN_WEEK = 5;

    /** The final cancellation reason (inside the class_exceptions CHECK). */
    public const SOURCE_REASON = 'medical_leave';

    /** The source class runs 2 h = four 30-minute original cells. */
    public const SOURCE_SPAN = 4;

    public const SOURCE_DAY = 3; // Thu (0–5 = Mon–Sat encoding)

    public const SOURCE_START = '10:00:00';

    public Semester $semester;

    public Faculty $faculty;

    public Department $department;

    public Programme $programme;

    /** The released original room (tutorial, 40 seats). */
    public Venue $sourceVenue;

    /** A second tutorial room (40 seats) — the "different room" landing. */
    public Venue $altVenue;

    /** A too-small tutorial room (10 seats) — the capacity blocker. */
    public Venue $smallVenue;

    public Module $sourceModule;

    public Module $altModule;

    /** A practical-type module — the module/venue-rule blocker. */
    public Module $practicalModule;

    /** The cancelled class's cohort. */
    public Cohort $cohortA;

    public Cohort $cohortB;

    public Cohort $cohortC;

    /**
     * Users by role key: `a` = source owner, `b`/`c` = other lecturers,
     * `d` = a spare lecturer, `pl` = the programme leader.
     *
     * @var array<string, User>
     */
    public array $users = [];

    /**
     * Sessions by key: `src` = the cancelled class, `b1`/`a2`/`c1` = the three
     * reuse requesters (each with its own conflicted week-5 occurrence).
     *
     * @var array<string, ClassSession>
     */
    public array $sessions = [];

    /**
     * Named slot groups:
     *   - `released`    the source occurrence's four original cells (week 2,
     *                   occupied, owner src) — the interval the cancellation frees;
     *   - `uncancelled` the same class's week-3 cells — occupied and untouched;
     *   - `altRoom`     four available cells at CSR-B, week 2 Thu 10:00–12:00;
     *   - `smallRoom`   four available cells at CSR-S, week 2 Thu 10:00–12:00;
     *   - `tooSoon`     two available cells at CSR-A, week 2 Tue 09:00–10:00
     *                   (inside the notice window);
     *   - `b1`/`a2`/`c1` each requester's own week-5 original reservation,
     *                   which its approval releases.
     *
     * @var array<string, list<TimeSlot>>
     */
    public array $slots = [];

    /**
     * The identity registry for the committed-fixture harness. Empty bookkeeping
     * under RefreshDatabase; `cleanup()` consumes it after the workers finish.
     */
    public array $userIds = [];

    public array $sessionIds = [];

    public array $slotIds = [];

    public array $requestIds = [];

    public array $cohortIds = [];

    public array $moduleIds = [];

    public array $venueIds = [];

    public array $semesterIds = [];

    public array $programmeIds = [];

    public array $departmentIds = [];

    public array $facultyIds = [];

    public array $holidayIds = [];

    /**
     * @param  string  $suffix  a unique token for shared-database runs (the
     *                          committed-fixture harness); empty under RefreshDatabase.
     */
    public function __construct(private readonly string $suffix = '') {}

    public static function freezeClock(): void
    {
        Carbon::setTestNow(Carbon::parse(self::FROZEN_NOW, LocalTime::TZ));
    }

    public static function unfreezeClock(): void
    {
        Carbon::setTestNow();
    }

    /** The real date of a (week, day) occurrence in this fixture semester. */
    public static function dateOf(int $week, int $day): string
    {
        return Carbon::parse('2027-02-01', LocalTime::TZ)
            ->addWeeks($week - 1)
            ->addDays($day)
            ->toDateString();
    }

    /**
     * Build the whole world. Under the concurrency harness these writes are
     * autocommitted (no wrapping transaction) so the workers' independent
     * backends see them; under RefreshDatabase they ride the wrapping one.
     */
    public function build(): void
    {
        $this->semester = Semester::create([
            'semester_code' => 'CSRM'.$this->suffix,
            'label' => 'Cancelled Slot Fixture'.$this->suffix,
            'start_date' => '2027-02-01', // a MONDAY
            'end_date' => '2027-05-30',
            'week_count' => 14,
        ]);
        $this->semesterIds[] = (int) $this->semester->id;

        $this->faculty = Faculty::create([
            'faculty_code' => 'CSRF'.$this->suffix,
            'faculty_name' => 'Cancelled Slot Faculty'.$this->suffix,
        ]);
        $this->facultyIds[] = (int) $this->faculty->id;

        $this->department = Department::create([
            'dept_code' => 'CSRD'.$this->suffix,
            'dept_name' => 'Cancelled Slot Dept'.$this->suffix,
            'faculty_id' => $this->faculty->id,
        ]);
        $this->departmentIds[] = (int) $this->department->id;

        $this->programme = Programme::create([
            'programme_code' => 'CSRP'.$this->suffix,
            'programme_name' => 'Cancelled Slot Programme'.$this->suffix,
            'faculty_id' => $this->faculty->id,
        ]);
        $this->programmeIds[] = (int) $this->programme->id;

        $this->cohortA = $this->createCohort(1);
        $this->cohortB = $this->createCohort(2);
        $this->cohortC = $this->createCohort(3);

        $this->sourceVenue = $this->createVenue('CSR-A', 40, 'tutorial');
        $this->altVenue = $this->createVenue('CSR-B', 40, 'tutorial');
        $this->smallVenue = $this->createVenue('CSR-S', 10, 'tutorial');

        $this->sourceModule = $this->createModule('CSR-M1');
        $this->altModule = $this->createModule('CSR-M2');
        $this->practicalModule = $this->createModule('CSR-MP');

        $this->createUser('a', 'Fixture Lecturer A');
        $this->createUser('b', 'Fixture Lecturer B');
        $this->createUser('c', 'Fixture Lecturer C');
        $this->createUser('d', 'Fixture Lecturer D');
        $this->createUser('pl', 'Fixture Programme Leader', isPl: true);

        // The cancelled class: Thu 10:00–12:00 in its own room, cohort A.
        // NO exception yet — the cancellation under test creates it.
        $this->createSession('src', 'a', $this->sourceModule, self::SOURCE_DAY,
            '10:00:00', '12:00:00', $this->sourceVenue, 'L', [$this->cohortA->id], null);

        // The three reuse requesters, each with its own conflicted week-5 pin.
        $this->createSession('b1', 'b', $this->altModule, 0,
            '09:00:00', '11:00:00', $this->altVenue, 'L', [$this->cohortB->id], self::PIN_WEEK);
        $this->createSession('a2', 'a', $this->createModule('CSR-M3'), 4,
            '14:00:00', '16:00:00', $this->altVenue, 'L', [$this->cohortC->id], self::PIN_WEEK);
        $this->createSession('c1', 'c', $this->createModule('CSR-M4'), 1,
            '09:00:00', '11:00:00', $this->altVenue, 'L', [$this->cohortA->id], self::PIN_WEEK);

        // The source's complete original grids: week 2 (released) and week 3
        // (the uncancelled occurrence that must keep blocking).
        $this->slots['released'] = $this->createCells($this->sourceVenue, self::SOURCE_WEEK, self::SOURCE_DAY,
            self::SOURCE_START, self::SOURCE_SPAN, 'occupied', $this->sessions['src']);
        $this->slots['uncancelled'] = $this->createCells($this->sourceVenue, self::UNCANCELLED_WEEK, self::SOURCE_DAY,
            self::SOURCE_START, self::SOURCE_SPAN, 'occupied', $this->sessions['src']);

        // Landing grids and the requesters' own week-5 original reservations.
        $this->slots['altRoom'] = $this->createCells($this->altVenue, self::SOURCE_WEEK, self::SOURCE_DAY,
            self::SOURCE_START, self::SOURCE_SPAN, 'available', null);
        $this->slots['smallRoom'] = $this->createCells($this->smallVenue, self::SOURCE_WEEK, self::SOURCE_DAY,
            self::SOURCE_START, self::SOURCE_SPAN, 'available', null);
        $this->slots['tooSoon'] = $this->createCells($this->sourceVenue, self::SOURCE_WEEK, 1,
            '09:00:00', 2, 'available', null);
        $this->slots['b1'] = $this->createCells($this->altVenue, self::PIN_WEEK, 0,
            '09:00:00', self::SOURCE_SPAN, 'occupied', $this->sessions['b1']);
        $this->slots['a2'] = $this->createCells($this->altVenue, self::PIN_WEEK, 4,
            '14:00:00', self::SOURCE_SPAN, 'occupied', $this->sessions['a2']);
        $this->slots['c1'] = $this->createCells($this->altVenue, self::PIN_WEEK, 1,
            '09:00:00', self::SOURCE_SPAN, 'occupied', $this->sessions['c1']);
    }

    /**
     * The final single-class cancellation of the source occurrence — the real
     * action, the real reason, the real window (the frozen class date passes
     * it). Throws on refusal so a harness can never "pass" on a refused setup.
     */
    public function cancelSource(): void
    {
        $ok = (new CancelClass)(
            $this->users['a'],
            $this->sessions['src'],
            self::SOURCE_WEEK,
            self::SOURCE_REASON,
        );

        if (! $ok) {
            throw new RuntimeException('the fixture source cancellation was refused — the harness cannot proceed');
        }
    }

    public function createUser(string $key, string $name, bool $isPl = false): User
    {
        $user = User::factory()->create([
            'name' => $name.$this->suffix,
            'email' => 'crs-'.strtolower($key).strtolower($this->suffix).'@crs-fixture.test',
            'role' => 'lecturer',
        ]);
        Lecturer::create([
            'user_id' => $user->id,
            'staff_id' => 'CRS'.strtoupper($key).$this->suffix,
            'dept_id' => $this->department->id,
            'is_pl' => $isPl,
        ]);
        $this->userIds[] = (int) $user->id;
        $this->users[$key] = $user;

        return $user;
    }

    public function createCohort(int $tutorialGroup): Cohort
    {
        $cohort = Cohort::create([
            'programme_id' => $this->programme->id,
            'current_year' => 3,
            'semester' => 1,
            'tutorial_group' => $tutorialGroup,
            'academic_year' => '2026/27',
            'intake' => 'June 2024',
            'student_count' => 20,
        ]);
        $this->cohortIds[] = (int) $cohort->id;

        return $cohort;
    }

    public function createVenue(string $code, int $capacity, string $roomType): Venue
    {
        $venue = Venue::create([
            'room_code' => $code.$this->suffix,
            'room_name' => 'Fixture Room '.$code,
            'capacity' => $capacity,
            'room_type' => $roomType,
            'allowed_session_types' => 'L,T',
        ]);
        $this->venueIds[] = (int) $venue->id;

        return $venue;
    }

    public function createModule(string $code): Module
    {
        $module = Module::create([
            'module_code' => $code.$this->suffix,
            'module_name' => 'Fixture Module '.$code,
            'allowed_session_types' => 'L,T,P',
        ]);
        $this->moduleIds[] = (int) $module->id;

        return $module;
    }

    /**
     * A class session, its cohort membership and (optionally) its own
     * conflicted occurrence — the pin that makes a submit eligible.
     *
     * @param  array<int, int>  $cohortIds
     */
    public function createSession(
        string $key,
        string $userKey,
        Module $module,
        int $day,
        string $start,
        string $end,
        Venue $venue,
        string $type,
        array $cohortIds,
        ?int $conflictedWeek,
    ): ClassSession {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $this->users[$userKey]->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'venue_id' => $venue->id,
            'session_type' => $type,
        ]);
        $session->cohorts()->attach($cohortIds);

        if ($conflictedWeek !== null) {
            ClassException::create([
                'class_session_id' => $session->id,
                'week_number' => $conflictedWeek,
                'reason' => 'official_event',
            ]);
        }

        $this->sessionIds[] = (int) $session->id;
        if ($key !== '') {
            $this->sessions[$key] = $session;
        }

        return $session;
    }

    /**
     * Contiguous 30-minute grid cells at one venue/day/start.
     *
     * @return list<TimeSlot>
     */
    public function createCells(Venue $venue, int $week, int $day, string $start, int $count, string $status, ?ClassSession $owner, int $version = 1): array
    {
        $slots = [];

        for ($k = 0; $k < $count; $k++) {
            $slotStart = Carbon::parse($start)->addMinutes($k * 30)->format('H:i:s');
            $slot = TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => $owner?->id,
                'week_number' => $week,
                'day_of_week' => $day,
                'start_time' => $slotStart,
                'end_time' => Carbon::parse($slotStart)->addMinutes(30)->format('H:i:s'),
                'venue_id' => $venue->id,
                'status' => $status,
                'version' => $version,
            ]);
            $this->slotIds[] = (int) $slot->id;
            $slots[] = $slot;
        }

        return $slots;
    }

    /**
     * A live pending request with its real junction receipt: the pinned
     * (session, week) occurrence, landing cells created pending and linked.
     * Used for the blocker twists and the interlock fixtures.
     */
    public function createPendingRequest(
        ClassSession $pinnedSession,
        int $pinWeek,
        Venue $landingVenue,
        int $landingWeek,
        int $landingDay,
        string $landingStart,
        int $span,
    ): ReplacementRequest {
        $cells = $this->createCells($landingVenue, $landingWeek, $landingDay, $landingStart, $span, 'pending', null);

        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $pinnedSession->lecturer_id,
            'class_session_id' => $pinnedSession->id,
            'week_number' => $pinWeek,
            'replacement_time_slot_id' => $cells[0]->id,
            'replacement_span' => $span,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        foreach ($cells as $cell) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $cell->id,
            ]);
        }

        $this->requestIds[] = (int) $request->id;

        return $request;
    }

    /**
     * A live pending request that takes an ALREADY-EXISTING available cell —
     * the exact OCC reserve shape (available → pending, version + 1, one
     * junction row). Never creates a duplicate row at occupied coordinates.
     */
    public function occupyCellWithPendingRequest(ClassSession $pinnedSession, int $pinWeek, TimeSlot $cell): ReplacementRequest
    {
        $cell->refresh();
        $cell->forceFill(['status' => 'pending', 'version' => (int) $cell->version + 1])->save();

        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $pinnedSession->lecturer_id,
            'class_session_id' => $pinnedSession->id,
            'week_number' => $pinWeek,
            'replacement_time_slot_id' => $cell->id,
            'replacement_span' => 1,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        ReplacementRequestSlot::create([
            'replacement_request_id' => $request->id,
            'time_slot_id' => $cell->id,
        ]);

        $this->requestIds[] = (int) $request->id;

        return $request;
    }

    public function createHoliday(int $week, int $day, string $label): Holiday
    {
        $holiday = Holiday::create([
            'semester_id' => $this->semester->id,
            'week_number' => $week,
            'day_of_week' => $day,
            'label' => $label,
        ]);
        $this->holidayIds[] = (int) $holiday->id;

        return $holiday;
    }

    /**
     * Identity-scoped cleanup for the committed-fixture harness: deletes ONLY
     * the rows this fixture registered, children before parents, inside one
     * transaction. Never a table wipe, never a truncate, never a reseed.
     */
    public function cleanup(): void
    {
        $users = $this->unique($this->userIds);
        $requests = $this->unique($this->requestIds);
        $sessions = $this->unique($this->sessionIds);
        $slots = $this->unique($this->slotIds);
        $cohorts = $this->unique($this->cohortIds);
        $modules = $this->unique($this->moduleIds);
        $venues = $this->unique($this->venueIds);
        $semesters = $this->unique($this->semesterIds);
        $programmes = $this->unique($this->programmeIds);
        $departments = $this->unique($this->departmentIds);
        $faculties = $this->unique($this->facultyIds);
        $holidays = $this->unique($this->holidayIds);

        DB::transaction(function () use ($users, $requests, $sessions, $slots, $cohorts, $modules, $venues, $semesters, $programmes, $departments, $faculties, $holidays): void {
            // fan-out rows: the actor's own user_id, or any row pointing at my
            // requests (the PL fan-out targets whoever is a PL in this database)
            if ($users !== [] || $requests !== []) {
                DB::table('notifications')
                    ->where(function ($query) use ($users, $requests): void {
                        if ($users !== []) {
                            $query->whereIn('user_id', $users);
                        }
                        if ($requests !== []) {
                            $query->orWhereIn('request_id', $requests);
                        }
                    })
                    ->delete();
            }

            // Identity-scoped audits, never a user-wide sweep: the run's own
            // request audits by their request id, and null-request audits only
            // when a registered source identity (details.class_session_id) or a
            // registered failing slot ties them to this run — the loser's
            // pre-nulled conflict audit (FR 4.12) carries no source id in its
            // details, only the registered failing cell. Globally deleting
            // null-request audits would erase history that is not ours.
            if ($requests !== [] || $sessions !== [] || $slots !== []) {
                DB::table('audit_logs')
                    ->where(function ($query) use ($requests, $sessions, $slots): void {
                        $query->whereIn('replacement_request_id', $requests)
                            ->orWhere(function ($nullRequest) use ($sessions): void {
                                $nullRequest->whereNull('replacement_request_id')
                                    ->whereIn('details->class_session_id', array_map('strval', $sessions));
                            })
                            ->orWhere(function ($nullRequest) use ($slots): void {
                                $nullRequest->whereNull('replacement_request_id')
                                    ->whereIn('time_slot_id', $slots);
                            });
                    })
                    ->delete();
            }

            if ($requests !== []) {
                DB::table('replacement_request_slots')->whereIn('replacement_request_id', $requests)->delete();
                DB::table('replacement_requests')->whereIn('id', $requests)->delete();
            }

            if ($sessions !== []) {
                DB::table('class_exceptions')->whereIn('class_session_id', $sessions)->delete();
                DB::table('session_cohorts')->whereIn('class_session_id', $sessions)->delete();
            }

            if ($slots !== []) {
                DB::table('time_slots')->whereIn('id', $slots)->delete();
            }

            if ($sessions !== []) {
                DB::table('class_sessions')->whereIn('id', $sessions)->delete();
            }

            if ($users !== []) {
                DB::table('lecturers')->whereIn('user_id', $users)->delete();
                DB::table('users')->whereIn('id', $users)->delete();
            }

            if ($cohorts !== []) {
                DB::table('cohorts')->whereIn('id', $cohorts)->delete();
            }

            if ($modules !== []) {
                DB::table('modules')->whereIn('id', $modules)->delete();
            }

            if ($venues !== []) {
                DB::table('venues')->whereIn('id', $venues)->delete();
            }

            if ($holidays !== []) {
                DB::table('holidays')->whereIn('id', $holidays)->delete();
            }

            if ($semesters !== []) {
                DB::table('semesters')->whereIn('id', $semesters)->delete();
            }

            if ($programmes !== []) {
                DB::table('programmes')->whereIn('id', $programmes)->delete();
            }

            if ($departments !== []) {
                DB::table('departments')->whereIn('id', $departments)->delete();
            }

            if ($faculties !== []) {
                DB::table('faculties')->whereIn('id', $faculties)->delete();
            }
        });

        $this->userIds = [];
        $this->sessionIds = [];
        $this->slotIds = [];
        $this->requestIds = [];
        $this->cohortIds = [];
        $this->moduleIds = [];
        $this->venueIds = [];
        $this->semesterIds = [];
        $this->programmeIds = [];
        $this->departmentIds = [];
        $this->facultyIds = [];
        $this->holidayIds = [];
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function unique(array $ids): array
    {
        return array_values(array_unique(array_map('intval', $ids)));
    }
}
