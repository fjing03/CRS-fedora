<?php

namespace Tests\Feature\Api;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiReadEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function defineDatabaseMigrations(): void
    {
        $this->artisan('migrate');
    }

    protected function refreshTestDatabase(): void
    {
        if (! DB::table('semesters')->count()) {
            DB::statement("SELECT setval(pg_get_serial_sequence('class_sessions', 'id'), 1, false)");
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class]);
        }
    }

    public function test_semester_endpoint(): void
    {
        $response = $this->getJson('/api/v1/semester');

        $response->assertOk()
            ->assertJsonStructure([
                'semester' => ['label', 'startDate', 'endDate', 'weeks', 'chipText'],
                'holidays',
            ])
            ->assertJsonFragment(['weeks' => 14]);
    }

    public function test_my_timetable_endpoint(): void
    {
        $response = $this->getJson('/api/v1/timetable/my');

        $response->assertOk()
            ->assertJsonStructure([
                'myTimetable' => [
                    'seedWeek',
                    'eventsByWeek',
                ],
            ]);
    }

    public function test_my_timetable_rejects_invalid_role(): void
    {
        $response = $this->getJson('/api/v1/timetable/my?role=invalid');

        $response->assertOk();
    }

    public function test_cohort_timetable_endpoint(): void
    {
        $response = $this->getJson('/api/v1/timetable/cohort');

        $response->assertOk()
            ->assertJsonStructure([
                'cohortTimetable' => [
                    'faculties',
                    'events',
                    'rsd3g2Base',
                ],
            ])
            ->assertJsonCount(2, 'cohortTimetable.faculties');
    }

    public function test_cohort_timetable_rejects_invalid_cohort(): void
    {
        $response = $this->getJson('/api/v1/timetable/cohort?cohort_id=nonexistent');

        $response->assertOk()
            ->assertJsonStructure(['cohortTimetable']);
    }

    public function test_student_timetable_endpoint(): void
    {
        $response = $this->getJson('/api/v1/timetable/student');

        $response->assertOk()
            ->assertJsonStructure([
                'studentTimetable' => [
                    'activeCohort',
                ],
                'cohortTimetable' => [
                    'faculties',
                    'events',
                ],
            ]);
    }

    public function test_my_requests_endpoint(): void
    {
        $response = $this->getJson('/api/v1/requests/my');

        $response->assertOk()
            ->assertJsonStructure([
                'requests' => [
                    '*' => ['id', 'code', 'status', 'requestType'],
                ],
            ]);
    }

    public function test_my_requests_rejects_invalid_role(): void
    {
        $response = $this->getJson('/api/v1/requests/my?role=invalid');

        $response->assertOk()
            ->assertJsonStructure(['requests']);
    }

    public function test_conflicts_endpoint(): void
    {
        $response = $this->getJson('/api/v1/requests/conflicts');

        $response->assertOk()
            ->assertJsonStructure([
                'conflicts',
            ]);
    }

    public function test_arrangement_slots_endpoint(): void
    {
        $response = $this->getJson('/api/v1/arrangement/slots?cohort_ids=1&session_type=L&duration=60&week_number=11');

        $response->assertOk()
            ->assertJsonStructure([
                'venueSlots',
                'arrangementWeeks',
                'venues',
            ]);
    }

    public function test_arrangement_slots_with_venue_filter(): void
    {
        $response = $this->getJson('/api/v1/arrangement/slots?cohort_ids=1&session_type=L&duration=60&week_number=11&venue_id=1');

        $response->assertOk()
            ->assertJsonStructure([
                'venueSlots',
                'arrangementWeeks',
                'venues',
            ]);
    }

    public function test_arrangement_slots_default_week11(): void
    {
        $response = $this->getJson('/api/v1/arrangement/slots?cohort_ids=1&session_type=L&duration=60');

        $response->assertOk()
            ->assertJsonFragment(['label' => 'Week 11']);
    }

    public function test_cohorts_endpoint(): void
    {
        $response = $this->getJson('/api/v1/cohorts');

        $response->assertOk()
            ->assertJsonStructure([
                'cohorts',
            ])
            ->assertJsonCount(14, 'cohorts');
    }

    public function test_cohorts_endpoint_structure(): void
    {
        $response = $this->getJson('/api/v1/cohorts');

        $response->assertOk()
            ->assertJsonStructure([
                'cohorts' => [
                    '*' => ['code', 'programme', 'faculty'],
                ],
            ]);
    }
}
