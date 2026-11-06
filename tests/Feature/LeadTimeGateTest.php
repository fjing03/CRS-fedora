<?php

namespace Tests\Feature;

use App\Actions\SubmitReplacementRequest;
use App\Models\AuditLog;
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
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Support\LocalTime;
use App\Support\WorkingHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FR 2.17 on the SERVER (2026-11-06).
 *
 * The arrangement grid has always refused a too-soon cell
 * (`leadTimeCutoff()` in `ui-common.js`), but that is the browser's word. This
 * pins the action's own gate: a submit may only propose a slot whose DATE is at
 * least the 3rd working day after today — weekends and semester holidays skipped,
 * the 3rd working day itself selectable, today never.
 *
 * Clock: Monday 19 Oct 2026, 09:00 (MYT). Working days after it are Tue 20 (1),
 * Wed 21 (2), Thu 22 (3) → **Thu 22 Oct is the first bookable date**.
 */
final class LeadTimeGateTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Venue $venue;

    private Module $module;

    private User $lecturer;

    private ClassSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00', LocalTime::TZ));

        $this->semester = Semester::create([
            'semester_code' => 'LT001',
            'label' => 'Lead Time Fixture',
            'start_date' => '2026-09-21', // a MONDAY — week 5 = Mon 19 Oct
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);

        $faculty = Faculty::create(['faculty_code' => 'LTF', 'faculty_name' => 'Lead Time Faculty']);
        $department = Department::create(['dept_code' => 'LTD', 'dept_name' => 'Lead Time Dept', 'faculty_id' => $faculty->id]);
        $programme = Programme::create(['programme_code' => 'LTP', 'programme_name' => 'Lead Time Programme', 'faculty_id' => $faculty->id]);

        $cohort = Cohort::create([
            'programme_id' => $programme->id,
            'current_year' => 3,
            'semester' => 1,
            'tutorial_group' => 1,
            'academic_year' => '2026/27',
            'intake' => 'June 2024',
            'student_count' => 20,
        ]);

        $this->venue = Venue::create([
            'room_code' => 'LT101',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        $this->module = Module::create([
            'module_code' => 'LT1001',
            'module_name' => 'Lead Time Module',
            'allowed_session_types' => 'L,T',
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer', 'name' => 'Lead Time Lecturer']);
        Lecturer::create([
            'user_id' => $this->lecturer->id,
            'staff_id' => 'LT0001',
            'dept_id' => $department->id,
            'is_pl' => false,
        ]);

        $this->session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 0,                    // the class itself sits elsewhere in the week
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $this->session->cohorts()->attach([$cohort->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── fixture ───────────────────────────────────────────────────────────

    /** An available 30-min cell at (week, day) 14:00 + the conflicted occurrence that allows it. */
    private function cell(int $week, int $day): TimeSlot
    {
        ClassException::query()->firstOrCreate(
            ['class_session_id' => $this->session->id, 'week_number' => $week],
            ['reason' => 'official_event'],
        );

        return TimeSlot::create([
            'semester_id' => $this->semester->id,
            'class_session_id' => null,
            'week_number' => $week,
            'day_of_week' => $day,
            'start_time' => '14:00:00',
            'end_time' => '14:30:00',
            'venue_id' => $this->venue->id,
            'status' => 'available',
            'version' => 1,
        ]);
    }

    /** @return array{ok: bool, conflict_reason: string|null, request_id: int|null} */
    private function submit(TimeSlot $slot, int $week): array
    {
        return app(SubmitReplacementRequest::class)(
            $this->lecturer,
            $this->session,
            $week,
            (int) $slot->id,
        );
    }

    // ── the gate ──────────────────────────────────────────────────────────

    public function test_the_cutoff_is_the_third_working_day(): void
    {
        $calendar = new WorkingHours($this->semester);

        // Mon 19 Oct → Tue 20 (1), Wed 21 (2), Thu 22 (3)
        $this->assertSame('2026-10-22', $calendar->noticeCutoff()->toDateString());
        $this->assertFalse($calendar->isBookableOnDate(Carbon::parse('2026-10-19', LocalTime::TZ)), 'today is never bookable');
        $this->assertFalse($calendar->isBookableOnDate(Carbon::parse('2026-10-20', LocalTime::TZ)));
        $this->assertFalse($calendar->isBookableOnDate(Carbon::parse('2026-10-21', LocalTime::TZ)));
        $this->assertTrue($calendar->isBookableOnDate(Carbon::parse('2026-10-22', LocalTime::TZ)), 'the third working day itself stays selectable');
    }

    public function test_a_slot_inside_the_notice_window_is_refused_and_leaves_no_trace(): void
    {
        $slot = $this->cell(5, 1); // Tue 20 Oct — 2 working days ahead

        $result = $this->submit($slot, 5);

        $this->assertFalse($result['ok']);
        $this->assertSame('slot_too_soon', $result['conflict_reason']);
        $this->assertNull($result['request_id']);

        // nothing was created and nothing was reserved
        $this->assertSame(0, ReplacementRequest::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
        $slot->refresh();
        $this->assertSame('available', (string) $slot->status);
        $this->assertSame(1, (int) $slot->version);
    }

    public function test_todays_own_slot_is_refused(): void
    {
        $slot = $this->cell(5, 0); // Mon 19 Oct — today

        $result = $this->submit($slot, 5);

        $this->assertFalse($result['ok']);
        $this->assertSame('slot_too_soon', $result['conflict_reason']);
        $this->assertSame(0, ReplacementRequest::query()->count());
    }

    public function test_the_third_working_day_books_through_the_real_pipeline(): void
    {
        $slot = $this->cell(5, 3); // Thu 22 Oct — the cutoff itself

        $result = $this->submit($slot, 5);

        $this->assertTrue($result['ok'], 'the boundary date is bookable, got: '.var_export($result['conflict_reason'], true));
        $this->assertSame('pending', (string) ReplacementRequest::query()->whereKey($result['request_id'])->value('status'));
        $this->assertSame('pending', (string) $slot->refresh()->status);
    }

    public function test_a_holiday_pushes_the_cutoff_one_working_day_later(): void
    {
        // Deepavali-equivalent on Tue 20 Oct (week 5 day 1): the working days are
        // now Wed 21 (1), Thu 22 (2), Fri 23 (3) → Fri 23 is the first bookable.
        Holiday::create([
            'semester_id' => $this->semester->id,
            'week_number' => 5,
            'day_of_week' => 1,
            'label' => 'Fixture Holiday',
        ]);

        $this->assertSame('2026-10-23', (new WorkingHours($this->semester))->noticeCutoff()->toDateString());

        // Thu 22 Oct was bookable a moment ago — the holiday moved it out.
        $thursday = $this->cell(5, 3);
        $this->assertSame('slot_too_soon', $this->submit($thursday, 5)['conflict_reason']);

        // Fri 23 Oct is the new boundary and books.
        $friday = $this->cell(5, 4);
        $this->assertTrue($this->submit($friday, 5)['ok']);
    }

    public function test_the_weekend_is_not_counted_when_the_clock_is_friday(): void
    {
        // Fri 23 Oct 2026 → Mon 26 (1), Tue 27 (2), Wed 28 (3) → bookable from Wed 28.
        Carbon::setTestNow(Carbon::parse('2026-10-23 10:00', LocalTime::TZ));

        $this->assertSame('2026-10-28', (new WorkingHours($this->semester))->noticeCutoff()->toDateString());

        $monday = $this->cell(6, 0);   // Mon 26 Oct
        $this->assertSame('slot_too_soon', $this->submit($monday, 6)['conflict_reason']);

        $wednesday = $this->cell(6, 2); // Wed 28 Oct
        $this->assertTrue($this->submit($wednesday, 6)['ok']);
    }
}
