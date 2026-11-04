<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ApiReadController;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\ReplacementRequestMapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * request-approval-ui 3C (2026-11-04) — the API's key set is a CONTRACT.
 *
 * `ReplacementRequestMapper::toRow()` has exactly two consumers: the PL queue
 * (`RequestApproval::rowsPayload()`) and `/api/requests/my`
 * (`ApiReadController::myRequests()`, which strips the keys its older clients
 * never saw). 3C adds `replacementVenueName` for the PL pop-up — a row key the
 * API must NOT grow, or the pinned JSON shape changes under its consumers.
 *
 * Nothing pinned that key set before: `SpanReservationTest` asserts
 * `replacementTime`'s VALUE, and `tests/e2e/api-wiring.spec.js` reads values, so
 * a stray key would have passed every existing test. The routes are commented
 * out (`routes/api.php`, orphaned since 2026-10), which is exactly why this pins
 * the controller directly instead of going through HTTP.
 */
final class MyRequestsApiShapeTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Carbon $start;

    private Venue $venue;

    private User $user;

    private Cohort $cohort;

    protected function setUp(): void
    {
        parent::setUp();

        $start = today()->startOfWeek(Carbon::MONDAY)->subWeeks(2);

        $this->semester = Semester::create([
            'semester_code' => 'API001',
            'label' => 'API Shape Semester',
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addWeeks(20)->toDateString(),
            'week_count' => 14,
        ]);
        $this->start = Carbon::parse($this->semester->start_date);

        $faculty = Faculty::create(['faculty_code' => 'FOCS', 'faculty_name' => 'FOCS']);
        $dept = Department::create(['dept_code' => 'DCIT', 'dept_name' => 'DCIT', 'faculty_id' => $faculty->id]);
        $programme = Programme::create(['programme_code' => 'RSD', 'programme_name' => 'RSD', 'faculty_id' => $faculty->id]);

        $this->venue = Venue::create([
            'room_code' => 'B100',
            'room_name' => 'Tutorial Room B100',
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
            'capacity' => 35,
        ]);

        $this->cohort = Cohort::create([
            'programme_id' => $programme->id,
            'current_year' => 3,
            'semester' => 1,
            'tutorial_group' => 1,
            'academic_year' => '2025/26',
            'intake' => 'June 2023',
            'student_count' => 20,
        ]);

        $this->user = User::factory()->create(['role' => 'lecturer', 'name' => 'API Lecturer']);
        Lecturer::create([
            'user_id' => $this->user->id,
            'staff_id' => '6101',
            'dept_id' => $dept->id,
            'is_pl' => true,
        ]);
    }

    private function pendingRequest(): ReplacementRequest
    {
        $module = Module::create([
            'module_code' => 'BMIT9001',
            'module_name' => 'API Shape Module',
            'allowed_session_types' => 'L,T',
        ]);

        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $this->user->id, // Lecturer's PK IS user_id
            'day_of_week' => 1,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);
        $session->cohorts()->attach([$this->cohort->id]);

        $slot = TimeSlot::create([
            'semester_id' => $this->semester->id,
            'class_session_id' => null,
            'week_number' => 5,
            'day_of_week' => 3,
            'start_time' => '14:00:00',
            'end_time' => '14:30:00',
            'venue_id' => $this->venue->id,
            'status' => 'pending',
            'version' => 2,
        ]);

        return ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->user->id,
            'class_session_id' => $session->id,
            'week_number' => 5,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    public function test_the_pl_row_carries_the_venue_name_but_the_api_row_does_not(): void
    {
        // the controller falls back to staff 5425 when nobody is authenticated;
        // this fixture's lecturer is not 5425, so sign in as the fixture's.
        $this->actingAs($this->user);

        $request = $this->pendingRequest();

        $row = app(ReplacementRequestMapper::class)->toRow($request, $this->start, 'queue');
        $this->assertArrayHasKey('replacementVenueName', $row, '3C reads the name from the PL payload');
        $this->assertSame('Tutorial Room B100', $row['replacementVenueName']);
        $this->assertSame('B100', $row['replacementVenue']);

        // the routes are parked (routes/api.php is commented out since 2026-10),
        // so the controller is called directly — exactly what the route will do
        // when Wave 3b re-enables it. `resolveUser()` then finds this user.
        $json = app(ApiReadController::class)->myRequests()->getData(true);

        $apiRow = $json['requests'][0];
        $this->assertArrayNotHasKey('replacementVenueName', $apiRow, 'the API key set never grows');
        $this->assertSame('B100', $apiRow['replacementVenue']);
    }

    public function test_the_api_row_key_set_is_pinned(): void
    {
        $this->actingAs($this->user);
        $this->pendingRequest();

        $json = app(ApiReadController::class)->myRequests()->getData(true);
        $keys = array_keys($json['requests'][0]);
        sort($keys);

        $this->assertSame([
            'classDate', 'classDay', 'classType', 'cohortCounts', 'cohorts',
            'courseCode', 'courseName', 'duration', 'id', 'lecturer',
            'rejectionReason', 'remarks', 'replacementDate', 'replacementTime',
            'replacementVenue', 'requestedAt', 'reviewedAt', 'reviewedBy',
            'status', 'timeEnd', 'timeStart', 'totalStudents', 'venue',
        ], $keys, 'the /api/requests/my row shape is a contract — add keys deliberately, and strip them here');
    }
}
