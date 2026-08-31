<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Programme;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Route-gate matrix over the 9 UI routes × 4 actors (rbac-route-gating spec).
 *
 * Buckets (design route table, auth FIRST per D3):
 *  - auth (all roles):        /cohort-timetable-ui
 *  - role:student:            /student-my-timetable-ui, /upcoming-replacements-ui
 *  - role:lecturer (+PL):     /my-timetable-ui, /replacement-home-ui, /replacement-arrangement,
 *                             /my-request-history-ui, /venue-timetable-ui
 *  - pl:                      /request-approval-ui
 */
class RouteGateMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const ALL_ROUTES = [
        '/my-timetable-ui',
        '/cohort-timetable-ui',
        '/student-my-timetable-ui',
        '/upcoming-replacements-ui',
        '/replacement-home-ui',
        '/replacement-arrangement',
        '/my-request-history-ui',
        '/venue-timetable-ui',
        '/request-approval-ui',
    ];

    private const STUDENT_ONLY = ['/student-my-timetable-ui', '/upcoming-replacements-ui'];

    private const LECTURER_ONLY = [
        '/my-timetable-ui',
        '/replacement-home-ui',
        '/replacement-arrangement',
        '/my-request-history-ui',
        '/venue-timetable-ui',
        '/request-approval-ui',
    ];

    private User $student;

    private User $lecturer;

    private User $pl;

    protected function setUp(): void
    {
        parent::setUp();

        $faculty = Faculty::create(['faculty_code' => 'FOCS', 'faculty_name' => 'FOCS']);
        $dept = Department::create(['dept_code' => 'DCIT', 'dept_name' => 'DCIT', 'faculty_id' => $faculty->id]);
        $prog = Programme::create(['programme_code' => 'RSD', 'programme_name' => 'RSD', 'faculty_id' => $faculty->id]);
        $cohort = Cohort::create([
            'programme_id' => $prog->id,
            'current_year' => 3,
            'semester' => 1,
            'tutorial_group' => 1,
            'academic_year' => '2025/26',
            'intake' => 'June 2023',
        ]);

        $this->student = User::factory()->create(['role' => 'student']);
        Student::create([
            'user_id' => $this->student->id,
            'student_id' => '25RSD0001',
            'cohort_id' => $cohort->id,
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer']);
        Lecturer::create([
            'user_id' => $this->lecturer->id,
            'staff_id' => '5001',
            'dept_id' => $dept->id,
            'is_pl' => false,
        ]);

        $this->pl = User::factory()->create(['role' => 'lecturer']);
        Lecturer::create([
            'user_id' => $this->pl->id,
            'staff_id' => '5002',
            'dept_id' => $dept->id,
            'is_pl' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login_on_every_route(): void
    {
        foreach (self::ALL_ROUTES as $uri) {
            $this->get($uri)->assertRedirect(route('login.student'));
        }
    }

    public function test_student_sees_all_role_pages_and_is_forbidden_on_staff_pages(): void
    {
        foreach (self::ALL_ROUTES as $uri) {
            if (in_array($uri, self::STUDENT_ONLY) || $uri === '/cohort-timetable-ui') {
                $this->actingAs($this->student)->get($uri)->assertOk();
            } else {
                $this->actingAs($this->student)->get($uri)->assertForbidden();
            }
        }
    }

    public function test_lecturer_sees_lecturer_pages_and_is_forbidden_on_student_and_pl_pages(): void
    {
        foreach (self::ALL_ROUTES as $uri) {
            if ($uri === '/cohort-timetable-ui' || in_array($uri, self::LECTURER_ONLY)) {
                if ($uri === '/request-approval-ui') {
                    $this->actingAs($this->lecturer)->get($uri)->assertForbidden();
                } else {
                    $this->actingAs($this->lecturer)->get($uri)->assertOk();
                }
            } else {
                $this->actingAs($this->lecturer)->get($uri)->assertForbidden();
            }
        }
    }

    public function test_pl_passes_lecturer_pages_and_pl_queue_but_is_forbidden_on_student_pages(): void
    {
        foreach (self::ALL_ROUTES as $uri) {
            if (in_array($uri, self::STUDENT_ONLY)) {
                $this->actingAs($this->pl)->get($uri)->assertForbidden();
            } else {
                $this->actingAs($this->pl)->get($uri)->assertOk();
            }
        }
    }
}
