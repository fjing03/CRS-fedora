<?php

namespace Tests\Unit;

use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Faculty;
use App\Models\Module;
use App\Models\Programme;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatrixIntersectionEngine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ReflectionMethod;
use Tests\TestCase;

final class MatrixIntersectionEngineTest extends TestCase
{
    use RefreshDatabase;

    private int $semesterId;

    private int $lecturerId;

    private int $cohortOneId;

    private int $cohortTwoId;

    private int $venueSmallId;

    private int $venueBigId;

    private int $venueLabId;

    private int $venueCiscoId;

    private int $venueMediumId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semesterId = DB::table('semesters')->insertGetId([
            'semester_code' => '2026-3',
            'label' => 'Semester 3, 2026',
            'start_date' => '2026-06-01',
            'end_date' => '2026-09-30',
            'week_count' => 14,
        ]);

        $faculty = Faculty::create([
            'faculty_code' => 'FOCS',
            'faculty_name' => 'Faculty of Computing',
        ]);

        $programme = Programme::create([
            'programme_code' => 'RDS',
            'programme_name' => 'Bachelor of Computer Science',
            'faculty_id' => $faculty->id,
        ]);

        $this->cohortOneId = Cohort::create([
            'programme_id' => $programme->id,
            'current_year' => 1,
            'semester' => 1,
            'tutorial_group' => 1,
            'academic_year' => '2026',
            'intake' => 'March 2026',
        ])->id;

        $this->cohortTwoId = Cohort::create([
            'programme_id' => $programme->id,
            'current_year' => 1,
            'semester' => 1,
            'tutorial_group' => 2,
            'academic_year' => '2026',
            'intake' => 'March 2026',
        ])->id;

        $this->lecturerId = User::factory()->lecturer()->create()->id;

        $this->venueSmallId = Venue::create([
            'room_code' => 'SML-01',
            'capacity' => 10,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ])->id;

        $this->venueBigId = Venue::create([
            'room_code' => 'BIG-01',
            'capacity' => 60,
            'room_type' => 'lecture_hall',
            'allowed_session_types' => 'L,T',
        ])->id;

        $this->venueLabId = Venue::create([
            'room_code' => 'LAB-01',
            'capacity' => 28,
            'room_type' => 'lab',
            'allowed_session_types' => 'P',
        ])->id;

        $this->venueCiscoId = Venue::create([
            'room_code' => 'CIS-01',
            'capacity' => 32,
            'room_type' => 'cisco_lab',
            'allowed_session_types' => 'P',
        ])->id;

        $this->venueMediumId = Venue::create([
            'room_code' => 'MED-01',
            'capacity' => 30,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ])->id;

        foreach ([$this->cohortOneId, $this->cohortTwoId] as $cohortId) {
            $studentUser = User::factory()->student()->create();
            Student::create([
                'user_id' => $studentUser->id,
                'student_id' => fake()->unique()->numerify('#########'),
                'cohort_id' => $cohortId,
            ]);
        }
    }

    private function engine(): MatrixIntersectionEngine
    {
        return new MatrixIntersectionEngine;
    }

    /**
     * @param  array<int, mixed>  $args
     * @return array<int, array{day: int, start: string, end: string}>
     */
    private function busyRanges(string $method, array $args): array
    {
        $reflection = new ReflectionMethod(MatrixIntersectionEngine::class, $method);

        return $reflection->invoke($this->engine(), ...$args);
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function invokePrivate(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod(MatrixIntersectionEngine::class, $method);

        return $reflection->invoke($this->engine(), ...$args);
    }

    /**
     * Top up a cohort with $count students (users are mandatory: students.user_id is a NOT NULL FK).
     */
    private function seedStudents(int $cohortId, int $count): void
    {
        $userIds = User::factory()->student()->count($count)->create()->pluck('id');

        $rows = [];

        foreach ($userIds as $userId) {
            $rows[] = [
                'user_id' => $userId,
                'student_id' => fake()->unique()->numerify('##########'),
                'cohort_id' => $cohortId,
            ];
        }

        DB::table('students')->insert($rows);
    }

    private function seedSlots(
        int $venueId,
        int $day,
        string $start,
        string $end,
        string $status = 'available',
        ?int $classSessionId = null,
        int $weekNumber = 1,
    ): TimeSlot {
        return TimeSlot::factory()->create([
            'semester_id' => $this->semesterId,
            'week_number' => $weekNumber,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'venue_id' => $venueId,
            'status' => $status,
            'class_session_id' => $classSessionId,
        ]);
    }

    private function seedHoliday(int $weekNumber, int $dayOfWeek): void
    {
        DB::table('holidays')->insert([
            'semester_id' => $this->semesterId,
            'week_number' => $weekNumber,
            'day_of_week' => $dayOfWeek,
            'label' => 'Public holiday',
        ]);
    }

    /**
     * @return array<int, array{
     *   day: int,
     *   start_time: string,
     *   end_time: string,
     *   venue_id: int,
     *   venue_code: string,
     *   time_slot_ids: array<int, int>,
     *   run_start: string,
     *   run_end: string,
     * }>
     */
    private function availableSlots(
        string $sessionType = 'L',
        int $duration = 60,
        ?int $venueId = null,
        int $weekNumber = 1,
    ): array {
        return $this->engine()->findAvailableSlots(
            lecturerId: $this->lecturerId,
            cohortIds: [$this->cohortOneId, $this->cohortTwoId],
            semesterId: $this->semesterId,
            weekNumber: $weekNumber,
            sessionType: $sessionType,
            duration: $duration,
            venueId: $venueId,
        );
    }

    private function advanceTime(string $time, int $minutes): string
    {
        [$hours, $mins] = array_map('intval', explode(':', $time));

        $total = $hours * 60 + $mins + $minutes;

        return sprintf('%02d:%02d:00', intdiv($total, 60), $total % 60);
    }

    /**
     * Seed consecutive 30-minute cells from $start to $end (exclusive).
     *
     * @return array<int, TimeSlot>
     */
    private function seedFreeRun(
        int $venueId,
        int $day,
        string $start,
        string $end,
        int $weekNumber = 1,
        string $status = 'available',
    ): array {
        $cells = [];

        for ($current = $start; $current < $end; $current = $this->advanceTime($current, 30)) {
            $cells[] = $this->seedSlots(
                $venueId,
                $day,
                $current,
                $this->advanceTime($current, 30),
                $status,
                weekNumber: $weekNumber,
            );
        }

        return $cells;
    }

    private function createSession(
        int $day,
        string $start,
        string $end,
        array $cohortIds = [],
    ): ClassSession {
        $session = ClassSession::factory()->create([
            'semester_id' => $this->semesterId,
            'module_id' => Module::factory()->create()->id,
            'lecturer_id' => $this->lecturerId,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'venue_id' => Venue::factory()->create()->id,
        ]);

        $session->cohorts()->attach($cohortIds);

        return $session;
    }

    public function test_s2_lecturer_busy_includes_scheduled_session(): void
    {
        $this->createSession(day: 1, start: '09:00:00', end: '11:00:00');

        $ranges = $this->busyRanges('lecturerBusyRanges', [$this->semesterId, $this->lecturerId, 1]);

        $this->assertContains(['day' => 1, 'start' => '09:00:00', 'end' => '11:00:00'], $ranges);
    }

    public function test_s2_lecturer_busy_excludes_other_lecturer_sessions(): void
    {
        $otherLecturer = User::factory()->lecturer()->create();

        $this->createSession(day: 2, start: '14:00:00', end: '15:00:00');
        ClassSession::factory()->create([
            'semester_id' => $this->semesterId,
            'module_id' => Module::factory()->create()->id,
            'lecturer_id' => $otherLecturer->id,
            'day_of_week' => 2,
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'venue_id' => Venue::factory()->create()->id,
        ]);

        $ranges = $this->busyRanges('lecturerBusyRanges', [$this->semesterId, $this->lecturerId, 1]);

        $this->assertCount(1, $ranges);
        $this->assertSame(2, $ranges[0]['day']);
    }

    public function test_s3_cohort_busy_includes_assigned_session(): void
    {
        $this->createSession(day: 3, start: '10:00:00', end: '12:00:00', cohortIds: [$this->cohortOneId]);

        $ranges = $this->busyRanges('cohortBusyRanges', [$this->semesterId, [$this->cohortOneId], 1]);

        $this->assertContains(['day' => 3, 'start' => '10:00:00', 'end' => '12:00:00'], $ranges);
    }

    public function test_s4_single_cohort_ignores_other_cohort_sessions(): void
    {
        $this->createSession(day: 4, start: '09:00:00', end: '11:00:00', cohortIds: [$this->cohortTwoId]);

        $ranges = $this->busyRanges('cohortBusyRanges', [$this->semesterId, [$this->cohortOneId], 1]);

        $this->assertSame([], $ranges);
    }

    public function test_s9_exception_subtracts_and_is_week_aware(): void
    {
        $session = $this->createSession(day: 1, start: '09:00:00', end: '11:00:00');

        DB::table('class_exceptions')->insert([
            'class_session_id' => $session->id,
            'week_number' => 1,
            'reason' => 'public_holiday',
        ]);

        $cancelled = $this->busyRanges('lecturerBusyRanges', [$this->semesterId, $this->lecturerId, 1]);
        $running = $this->busyRanges('lecturerBusyRanges', [$this->semesterId, $this->lecturerId, 2]);

        $this->assertSame([], $cancelled);
        $this->assertContains(['day' => 1, 'start' => '09:00:00', 'end' => '11:00:00'], $running);
    }

    public function test_s16_invalid_session_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine()->findAvailableSlots(
            lecturerId: 1,
            cohortIds: [1],
            semesterId: 1,
            weekNumber: 1,
            sessionType: 'X',
        );
    }

    public function test_s16_invalid_week_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine()->findAvailableSlots(
            lecturerId: 1,
            cohortIds: [1],
            semesterId: 1,
            weekNumber: 0,
        );
    }

    public function test_s16_invalid_week_15_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine()->findAvailableSlots(
            lecturerId: 1,
            cohortIds: [1],
            semesterId: 1,
            weekNumber: 15,
        );
    }

    public function test_s16_invalid_duration_45_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine()->findAvailableSlots(
            lecturerId: 1,
            cohortIds: [1],
            semesterId: 1,
            weekNumber: 1,
            duration: 45,
        );
    }

    public function test_s16_invalid_duration_0_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine()->findAvailableSlots(
            lecturerId: 1,
            cohortIds: [1],
            semesterId: 1,
            weekNumber: 1,
            duration: 0,
        );
    }

    public function test_s16_valid_arguments_validate_ok(): void
    {
        $this->assertSame([], $this->engine()->findAvailableSlots(
            lecturerId: 1,
            cohortIds: [1],
            semesterId: 1,
            weekNumber: 1,
            duration: 60,
        ));
    }

    public function test_s16_validate_slot_invalid_session_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->engine()->validateSlot(
            timeSlotId: 1,
            lecturerId: 1,
            cohortIds: [1],
            sessionType: 'X',
            weekNumber: 1,
        );
    }

    public function test_s13_validate_slot_true(): void
    {
        $cells = $this->seedFreeRun($this->venueBigId, day: 1, start: '09:00:00', end: '10:00:00');

        $this->assertTrue($this->engine()->validateSlot(
            timeSlotId: $cells[0]->id,
            lecturerId: $this->lecturerId,
            cohortIds: [$this->cohortOneId, $this->cohortTwoId],
            sessionType: 'L',
            weekNumber: 1,
        ));
    }

    public function test_s14_validate_slot_false_each_vector(): void
    {
        $engine = $this->engine();

        $occupiedSession = $this->createSession(day: 1, start: '14:00:00', end: '15:00:00');
        $occupied = $this->seedSlots(
            $this->venueBigId,
            day: 1,
            start: '14:00:00',
            end: '14:30:00',
            status: 'occupied',
            classSessionId: $occupiedSession->id,
        );

        $holiday = $this->seedSlots($this->venueBigId, day: 1, start: '09:00:00', end: '09:30:00');
        $this->seedHoliday(1, 1);

        $lecturerBusy = $this->seedSlots($this->venueBigId, day: 2, start: '09:00:00', end: '09:30:00');
        $this->createSession(day: 2, start: '09:00:00', end: '11:00:00');

        $cohortBusy = $this->seedSlots($this->venueBigId, day: 3, start: '09:00:00', end: '09:30:00');
        $this->createSession(day: 3, start: '09:00:00', end: '11:00:00', cohortIds: [$this->cohortOneId]);

        $venueType = $this->seedSlots($this->venueLabId, day: 4, start: '09:00:00', end: '09:30:00');

        $this->seedStudents($this->cohortOneId, 9);
        $this->seedStudents($this->cohortTwoId, 9);
        $venueCapacity = $this->seedSlots($this->venueSmallId, day: 5, start: '09:00:00', end: '09:30:00');

        $this->assertFalse($engine->validateSlot($occupied->id, $this->lecturerId, [$this->cohortOneId, $this->cohortTwoId], 'L', 1));
        $this->assertFalse($engine->validateSlot($holiday->id, $this->lecturerId, [$this->cohortOneId, $this->cohortTwoId], 'L', 1));
        $this->assertFalse($engine->validateSlot($lecturerBusy->id, $this->lecturerId, [$this->cohortOneId, $this->cohortTwoId], 'L', 1));
        $this->assertFalse($engine->validateSlot($cohortBusy->id, $this->lecturerId, [$this->cohortOneId, $this->cohortTwoId], 'L', 1));
        $this->assertFalse($engine->validateSlot($venueType->id, $this->lecturerId, [$this->cohortOneId, $this->cohortTwoId], 'L', 1));
        $this->assertFalse($engine->validateSlot($venueCapacity->id, $this->lecturerId, [$this->cohortOneId, $this->cohortTwoId], 'L', 1));
    }

    public function test_s15_validate_slot_week_guard(): void
    {
        $cell = $this->seedSlots($this->venueBigId, day: 1, start: '09:00:00', end: '09:30:00', weekNumber: 1);

        $this->assertFalse($this->engine()->validateSlot(
            timeSlotId: $cell->id,
            lecturerId: $this->lecturerId,
            cohortIds: [$this->cohortOneId, $this->cohortTwoId],
            sessionType: 'L',
            weekNumber: 2,
        ));
    }

    public function test_s13_validate_slot_respects_exceptions(): void
    {
        $session = $this->createSession(day: 1, start: '09:00:00', end: '11:00:00');

        $cell = $this->seedSlots($this->venueBigId, day: 1, start: '09:00:00', end: '09:30:00');

        DB::table('class_exceptions')->insert([
            'class_session_id' => $session->id,
            'week_number' => 1,
            'reason' => 'public_holiday',
        ]);

        $this->assertTrue($this->engine()->validateSlot(
            timeSlotId: $cell->id,
            lecturerId: $this->lecturerId,
            cohortIds: [$this->cohortOneId, $this->cohortTwoId],
            sessionType: 'L',
            weekNumber: 1,
        ));

        $this->assertFalse($this->engine()->validateSlot(
            timeSlotId: $cell->id,
            lecturerId: $this->lecturerId,
            cohortIds: [$this->cohortOneId, $this->cohortTwoId],
            sessionType: 'L',
            weekNumber: 2,
        ));
    }

    public function test_s14_validate_slot_missing_slot_throws(): void
    {
        $this->expectException(ModelNotFoundException::class);

        $this->engine()->validateSlot(
            timeSlotId: 999999,
            lecturerId: $this->lecturerId,
            cohortIds: [$this->cohortOneId, $this->cohortTwoId],
            sessionType: 'L',
            weekNumber: 1,
        );
    }

    public function test_s5_lab_excluded_for_lecture_type(): void
    {
        $venues = $this->invokePrivate('eligibleVenues', [1, 'L']);

        $this->assertArrayHasKey($this->venueSmallId, $venues);
        $this->assertArrayHasKey($this->venueBigId, $venues);
        $this->assertArrayNotHasKey($this->venueLabId, $venues);
        $this->assertArrayNotHasKey($this->venueCiscoId, $venues);
    }

    public function test_s5_lab_included_for_practical_type(): void
    {
        $venues = $this->invokePrivate('eligibleVenues', [1, 'P']);

        $this->assertArrayHasKey($this->venueLabId, $venues);
        $this->assertArrayHasKey($this->venueCiscoId, $venues);
        $this->assertArrayNotHasKey($this->venueSmallId, $venues);
        $this->assertArrayNotHasKey($this->venueBigId, $venues);
    }

    public function test_s6_capacity_multi_cohort_sum(): void
    {
        $this->seedStudents($this->cohortOneId, 24);
        $this->seedStudents($this->cohortTwoId, 19);

        $this->assertSame(45, $this->invokePrivate('requiredHeadcount', [[$this->cohortOneId, $this->cohortTwoId]]));

        $venues = $this->invokePrivate('eligibleVenues', [45, 'L']);

        $this->assertArrayNotHasKey($this->venueSmallId, $venues);
        $this->assertArrayNotHasKey($this->venueLabId, $venues);
        $this->assertArrayHasKey($this->venueBigId, $venues);
    }

    public function test_s6_capacity_re_evaluated_single_cohort(): void
    {
        $this->seedStudents($this->cohortOneId, 24);

        $this->assertSame(25, $this->invokePrivate('requiredHeadcount', [[$this->cohortOneId]]));

        $singleCohort = $this->invokePrivate('eligibleVenues', [25, 'L']);
        $bothCohorts = $this->invokePrivate('eligibleVenues', [45, 'L']);

        $this->assertArrayHasKey($this->venueMediumId, $singleCohort);
        $this->assertArrayNotHasKey($this->venueMediumId, $bothCohorts);
    }

    public function test_s12_provided_venue_still_filtered_by_type(): void
    {
        $venues = $this->invokePrivate('eligibleVenues', [1, 'L', $this->venueLabId]);

        $this->assertSame([], $venues);
    }

    public function test_s12_provided_venue_still_filtered_by_capacity(): void
    {
        $venues = $this->invokePrivate('eligibleVenues', [45, 'L', $this->venueSmallId]);

        $this->assertSame([], $venues);
    }

    public function test_s12_provided_venue_ok_when_passing(): void
    {
        $venues = $this->invokePrivate('eligibleVenues', [1, 'L', $this->venueBigId]);

        $this->assertSame([$this->venueBigId => 'BIG-01'], $venues);
    }

    public function test_s4b_venue_occupied_excluded(): void
    {
        $availableCell = $this->seedSlots($this->venueSmallId, day: 1, start: '09:00:00', end: '09:30:00');

        $session = ClassSession::factory()->create([
            'semester_id' => $this->semesterId,
            'module_id' => Module::factory()->create()->id,
            'lecturer_id' => $this->lecturerId,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'venue_id' => $this->venueBigId,
        ]);

        $this->seedSlots($this->venueBigId, day: 1, start: '09:00:00', end: '09:30:00', status: 'occupied', classSessionId: $session->id);

        $cells = $this->invokePrivate('baseCells', [$this->semesterId, 1, [$this->venueSmallId, $this->venueBigId], null, []]);

        $this->assertCount(1, $cells);
        $this->assertSame($availableCell->id, $cells[0]['id']);
        $this->assertSame('SML-01', $cells[0]['venue_code']);
    }

    public function test_s10_holiday_day_blocked(): void
    {
        $this->seedSlots($this->venueSmallId, day: 1, start: '09:00:00', end: '09:30:00');
        $this->seedSlots($this->venueSmallId, day: 2, start: '09:00:00', end: '09:30:00');
        $this->seedHoliday(1, 2);

        $days = $this->invokePrivate('holidayDays', [$this->semesterId, 1]);
        $this->assertSame([2], $days);

        $cells = $this->invokePrivate('baseCells', [$this->semesterId, 1, [$this->venueSmallId], null, $days]);
        $this->assertSame([1], array_values(array_unique(array_column($cells, 'day'))));

        DB::table('holidays')->where('semester_id', $this->semesterId)->delete();

        $cellsWithoutHoliday = $this->invokePrivate('baseCells', [$this->semesterId, 1, [$this->venueSmallId], null, []]);
        $this->assertContains(2, array_column($cellsWithoutHoliday, 'day'));
    }

    public function test_s10_holiday_week_specific(): void
    {
        $this->seedHoliday(1, 2);

        $this->assertSame([2], $this->invokePrivate('holidayDays', [$this->semesterId, 1]));
        $this->assertSame([], $this->invokePrivate('holidayDays', [$this->semesterId, 2]));
    }

    public function test_base_ordered_by_day_start_venue_code(): void
    {
        $this->seedSlots($this->venueSmallId, day: 2, start: '09:00:00', end: '09:30:00');
        $this->seedSlots($this->venueSmallId, day: 1, start: '11:00:00', end: '11:30:00');
        $this->seedSlots($this->venueSmallId, day: 1, start: '09:00:00', end: '09:30:00');
        $this->seedSlots($this->venueBigId, day: 1, start: '09:00:00', end: '09:30:00');

        $cells = $this->invokePrivate('baseCells', [$this->semesterId, 1, [$this->venueSmallId, $this->venueBigId], null, []]);

        $this->assertSame(
            [
                ['day' => 1, 'start' => '09:00:00', 'venue_code' => 'BIG-01'],
                ['day' => 1, 'start' => '09:00:00', 'venue_code' => 'SML-01'],
                ['day' => 1, 'start' => '11:00:00', 'venue_code' => 'SML-01'],
                ['day' => 2, 'start' => '09:00:00', 'venue_code' => 'SML-01'],
            ],
            array_map(
                fn (array $cell): array => ['day' => $cell['day'], 'start' => $cell['start'], 'venue_code' => $cell['venue_code']],
                $cells,
            ),
        );
    }

    public function test_single_venue_mode_restricts_grid(): void
    {
        $this->seedSlots($this->venueSmallId, day: 1, start: '09:00:00', end: '09:30:00');
        $this->seedSlots($this->venueBigId, day: 1, start: '09:00:00', end: '09:30:00');

        $cells = $this->invokePrivate('baseCells', [$this->semesterId, 1, [$this->venueSmallId, $this->venueBigId], $this->venueSmallId, []]);

        $this->assertCount(1, $cells);
        $this->assertSame($this->venueSmallId, $cells[0]['venue_id']);
        $this->assertSame('SML-01', $cells[0]['venue_code']);
    }

    public function test_s1_happy_path_4_vector(): void
    {
        $cells = $this->seedFreeRun($this->venueBigId, day: 1, start: '09:00:00', end: '11:00:00');

        $results = $this->availableSlots();

        $this->assertCount(1, $results);

        $result = $results[0];

        $this->assertSame(1, $result['day']);
        $this->assertSame('09:00:00', $result['start_time']);
        $this->assertSame('10:00:00', $result['end_time']);
        $this->assertSame($this->venueBigId, $result['venue_id']);
        $this->assertSame('BIG-01', $result['venue_code']);
        $this->assertSame(array_column($cells, 'id'), $result['time_slot_ids']);
        $this->assertSame('09:00:00', $result['run_start']);
        $this->assertSame('11:00:00', $result['run_end']);
    }

    public function test_s1_single_venue_mode_filters_to_provided_venue(): void
    {
        $this->seedFreeRun($this->venueBigId, day: 1, start: '09:00:00', end: '11:00:00');
        $this->seedFreeRun($this->venueMediumId, day: 1, start: '09:00:00', end: '11:00:00');

        $results = $this->availableSlots(venueId: $this->venueBigId);

        $this->assertCount(1, $results);
        $this->assertSame($this->venueBigId, $results[0]['venue_id']);
        $this->assertSame('BIG-01', $results[0]['venue_code']);
    }

    public function test_s7_no_common_slot_returns_empty(): void
    {
        $this->assertSame([], $this->availableSlots());

        $this->seedFreeRun($this->venueBigId, day: 1, start: '09:00:00', end: '11:00:00');
        $this->createSession(day: 1, start: '09:00:00', end: '11:00:00');

        $this->assertSame([], $this->availableSlots());
    }

    public function test_s8_duration_filter(): void
    {
        $shortRun = $this->seedFreeRun($this->venueBigId, day: 1, start: '09:00:00', end: '10:00:00');
        $longRun = $this->seedFreeRun($this->venueBigId, day: 2, start: '09:00:00', end: '11:00:00');

        $results = $this->availableSlots(duration: 90);

        $this->assertCount(1, $results);

        $result = $results[0];

        $this->assertSame(2, $result['day']);
        $this->assertSame('09:00:00', $result['start_time']);
        $this->assertSame('10:30:00', $result['end_time']);
        $this->assertSame(array_column($longRun, 'id'), $result['time_slot_ids']);

        $shortResult = $this->availableSlots(venueId: $this->venueBigId, weekNumber: 1, duration: 60);

        $this->assertSame(array_column($shortRun, 'id'), $shortResult[0]['time_slot_ids']);
    }

    public function test_s11_all_venues_mode_ordering(): void
    {
        $this->seedFreeRun($this->venueBigId, day: 1, start: '09:00:00', end: '10:00:00');
        $this->seedFreeRun($this->venueMediumId, day: 1, start: '10:00:00', end: '11:00:00');
        $this->seedFreeRun($this->venueSmallId, day: 2, start: '09:00:00', end: '10:00:00');

        $results = $this->availableSlots();

        $this->assertCount(3, $results);
        $this->assertSame([1, 1, 2], array_column($results, 'day'));
        $this->assertSame(['09:00:00', '10:00:00', '09:00:00'], array_column($results, 'start_time'));
        $this->assertSame(['BIG-01', 'MED-01', 'SML-01'], array_column($results, 'venue_code'));
        $this->assertSame(
            [$this->venueBigId, $this->venueMediumId, $this->venueSmallId],
            array_column($results, 'venue_id'),
        );
    }

    public function test_runs_do_not_bleed_across_venues_sharing_start_time(): void
    {
        $this->seedSlots($this->venueBigId, day: 1, start: '09:00:00', end: '09:30:00');
        $this->seedSlots($this->venueMediumId, day: 1, start: '09:30:00', end: '10:00:00');

        $this->assertSame([], $this->availableSlots(duration: 60));

        $results = $this->availableSlots(duration: 30);

        $this->assertCount(2, $results);
        $this->assertSame($this->venueBigId, $results[0]['venue_id']);
        $this->assertSame($this->venueMediumId, $results[1]['venue_id']);
        $this->assertSame('09:00:00', $results[0]['start_time']);
        $this->assertSame('09:30:00', $results[1]['start_time']);
    }
}
