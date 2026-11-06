<?php

namespace Tests\Feature;

use App\Actions\AutoRejectRequest;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Holiday;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\PendingRequestExpiry;
use App\Services\ReplacementRequestMapper;
use App\Support\LocalTime;
use App\Support\WorkingHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The auto-reject rule (user brief "Auto-Reject Pending Requests", 2026-11-06).
 *
 * A PENDING request whose proposed start is within — or exactly — 3 working
 * hours is rejected by the system; anything further out stays pending. Only
 * `pending` rows are ever considered, and the release is the cancel family's
 * (`pending→available`, junction deleted) so the reserved cells come back.
 *
 * The clock is frozen on a real Friday so the brief's own table is the
 * fixture: 2026-11-06 08:45 → proposals at 10:00 (75 min, rejected), 11:30
 * (180 min, rejected on the boundary) and 13:00 (255 min, stays pending).
 */
final class AutoRejectPendingRequestsTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Carbon $semesterStart;

    private Venue $venue;

    private User $lecturer;

    protected function setUp(): void
    {
        parent::setUp();

        // Fri 6 Nov 2026, 08:45 in the USER's clock (the app clock is UTC).
        Carbon::setTestNow(Carbon::parse('2026-11-06 08:45', LocalTime::TZ));

        $this->semester = Semester::create([
            'semester_code' => 'AR001',
            'label' => 'Auto-Reject Fixture',
            'start_date' => '2026-09-21', // a MONDAY — week 1 day 0
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);
        $this->semesterStart = Carbon::parse((string) $this->semester->start_date);

        $this->venue = Venue::create([
            'room_code' => 'AR101',
            'capacity' => 30,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        $faculty = Faculty::create(['faculty_code' => 'ARF', 'faculty_name' => 'Auto Reject Faculty']);
        $department = Department::create(['dept_code' => 'ARD', 'dept_name' => 'Auto Reject Dept', 'faculty_id' => $faculty->id]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer', 'name' => 'Auto Reject Lecturer']);
        Lecturer::create([
            'user_id' => $this->lecturer->id,
            'staff_id' => 'AR0001',
            'dept_id' => $department->id,
            'is_pl' => false,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── fixture ───────────────────────────────────────────────────────────

    /** @return array{0: int, 1: int} [week, day] inside the fixture semester */
    private function weekDayFor(string $date): array
    {
        $days = (int) $this->semesterStart->copy()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay());

        return [(int) floor($days / 7) + 1, $days % 7];
    }

    /**
     * A pending request whose proposed span starts at `$date` + `$start`
     * (`$span` contiguous 30-min cells, reserved the way submit leaves them).
     */
    private function pendingRequest(string $date, string $start = '10:00:00', int $span = 2, string $status = 'pending'): ReplacementRequest
    {
        [$week, $day] = $this->weekDayFor($date);

        $module = Module::create([
            'module_code' => 'AR'.random_int(1000, 9999),
            'module_name' => 'Auto-Reject Fixture Module',
            'allowed_session_types' => 'L,T',
        ]);

        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => $day,
            'start_time' => '10:00:00',
            'end_time' => '12:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ]);

        // the original occurrence was cancelled — the row exists to be replaced
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => $week,
            'reason' => 'official_event',
        ]);

        $slotIds = [];
        for ($k = 0; $k < $span; $k++) {
            $cellStart = date('H:i:s', strtotime($start) + $k * 1800);

            $slotIds[] = TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => null,
                'week_number' => $week,
                'day_of_week' => $day,
                'start_time' => $cellStart,
                'end_time' => date('H:i:s', strtotime($cellStart) + 1800),
                'venue_id' => $this->venue->id,
                'status' => 'available',
                'version' => 1,
            ])->id;
        }

        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturer->id,
            'class_session_id' => $session->id,
            'week_number' => $week,
            'replacement_time_slot_id' => $slotIds[0],
            'status' => $status,
            'submitted_at' => now()->subHour(),
        ]);

        foreach ($slotIds as $slotId) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slotId,
            ]);

            if ($status === 'pending') {
                DB::table('time_slots')->where('id', $slotId)->update(['status' => 'pending', 'version' => 2]);
            }
        }

        return $request;
    }

    // ── the rule ──────────────────────────────────────────────────────────

    public function test_a_request_inside_the_three_working_hours_is_rejected(): void
    {
        $request = $this->pendingRequest('2026-11-06', '10:00:00'); // 75 working minutes away

        $this->artisan('requests:auto-reject')->assertExitCode(0);

        $row = $request->refresh();
        $this->assertSame('rejected', (string) $row->status);
        $this->assertNull($row->approver_id, 'no human decided — the row must not claim one');
        $this->assertNotNull($row->decided_at);
        $this->assertSame(AutoRejectRequest::REASON, (string) $row->rejection_reason);

        // the reserved cells come back (the cancel family's release)
        $this->assertSame(0, ReplacementRequestSlot::query()->where('replacement_request_id', $row->id)->count());
        $this->assertSame(0, TimeSlot::query()->where('status', 'pending')->count());
        $this->assertSame(2, TimeSlot::query()->where('status', 'available')->where('version', 3)->count());
    }

    public function test_the_three_hour_boundary_itself_is_rejected(): void
    {
        $request = $this->pendingRequest('2026-11-06', '11:30:00'); // exactly 180

        $this->artisan('requests:auto-reject')->assertExitCode(0);

        $this->assertSame('rejected', (string) $request->refresh()->status);
    }

    public function test_a_request_with_more_than_three_working_hours_stays_pending(): void
    {
        $request = $this->pendingRequest('2026-11-06', '13:00:00'); // 255 minutes away

        $this->artisan('requests:auto-reject')->assertExitCode(0);

        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(2, TimeSlot::query()->where('status', 'pending')->count(), 'the span stays reserved');
        $this->assertSame(2, ReplacementRequestSlot::query()->where('replacement_request_id', $request->id)->count());
        $this->assertSame(0, AuditLog::query()->count(), 'a surviving request is not audited');
    }

    public function test_the_weekend_is_not_counted(): void
    {
        // Fri 16:30 → Mon 11:00 = 1 h + 2.5 h = 3.5 hours → stays pending
        Carbon::setTestNow(Carbon::parse('2026-11-06 16:30', LocalTime::TZ));
        $far = $this->pendingRequest('2026-11-09', '11:00:00');

        // ...while Mon 09:30 is only 2 working hours away → rejected
        $near = $this->pendingRequest('2026-11-09', '09:30:00', 2);

        $this->artisan('requests:auto-reject')->assertExitCode(0);

        $this->assertSame('pending', (string) $far->refresh()->status);
        $this->assertSame('rejected', (string) $near->refresh()->status);
    }

    public function test_only_pending_rows_are_considered(): void
    {
        $approved = $this->pendingRequest('2026-11-06', '10:00:00', 2, 'approved');
        $pending = $this->pendingRequest('2026-11-06', '10:00:00');

        $this->artisan('requests:auto-reject')->assertExitCode(0);

        $this->assertSame('approved', (string) $approved->refresh()->status, 'a decided request is never re-stamped');
        $this->assertSame('rejected', (string) $pending->refresh()->status, 'the pending one is still swept');
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $request = $this->pendingRequest('2026-11-06', '10:00:00');

        $result = app(PendingRequestExpiry::class)->sweep(true);

        $this->assertSame(1, $result['scanned']);
        $this->assertSame([(int) $request->id], $result['due']);
        $this->assertSame([], $result['rejected']);
        $this->assertSame('pending', (string) $request->refresh()->status);
        $this->assertSame(0, AuditLog::query()->count());
    }

    // ── the evidence ──────────────────────────────────────────────────────

    public function test_the_rejection_is_audited_as_a_system_decision(): void
    {
        $request = $this->pendingRequest('2026-11-06', '10:00:00');
        $slotIds = ReplacementRequestSlot::query()
            ->where('replacement_request_id', $request->id)
            ->pluck('time_slot_id')
            ->all();

        $this->artisan('requests:auto-reject')->assertExitCode(0);

        $audits = AuditLog::query()->orderBy('id')->get();
        $this->assertCount(3, $audits, 'one summary row + one per released cell');

        foreach ($audits as $audit) {
            $this->assertSame('rejected', (string) $audit->action);
            $this->assertSame((int) $this->lecturer->id, (int) $audit->user_id, 'attributed to the proposer — no system user exists');
            $this->assertTrue((bool) ($audit->details['auto'] ?? false));
            $this->assertSame(AutoRejectRequest::REASON, (string) ($audit->details['reason'] ?? ''));
        }

        $summary = $audits->last();
        $this->assertNull($summary->new_status);
        $this->assertSame((int) $request->id, (int) $summary->replacement_request_id);
        $this->assertSame('pending', (string) $summary->old_status);
        $this->assertEqualsCanonicalizing($slotIds, $summary->details['slots']);

        // the per-cell rows come first (one per released cell), the summary last
        $this->assertSame(
            ['available', 'available'],
            $audits->slice(0, 2)->map(fn (AuditLog $a): string => (string) $a->new_status)->values()->all(),
        );
    }

    public function test_a_second_sweep_is_a_no_op(): void
    {
        $request = $this->pendingRequest('2026-11-06', '10:00:00');

        $this->artisan('requests:auto-reject')->assertExitCode(0);
        $auditCount = AuditLog::query()->count();

        $result = app(PendingRequestExpiry::class)->sweep();

        $this->assertSame(['scanned' => 0, 'due' => [], 'rejected' => []], $result);
        $this->assertSame($auditCount, AuditLog::query()->count(), 'a rejected row is gone from the sweep');
        $this->assertSame('rejected', (string) $request->refresh()->status);
    }

    // ── the tooltip ───────────────────────────────────────────────────────

    public function test_the_pending_badge_tip_states_the_deadline(): void
    {
        $request = $this->pendingRequest('2026-11-06', '13:00:00');
        $mapper = app(ReplacementRequestMapper::class);

        // the deadline rides the row's own resolved strings (no second span read)
        $strings = $mapper->replacementStrings($request, $this->semesterStart);

        // Fri 13:00 − 3 working hours = Fri 10:00 — the inverse of the gap.
        $label = $mapper->approvalDeadlineLabel($strings['date'], $strings['time'], (int) $this->semester->id);
        $this->assertSame('Fri, 6 Nov 2026, 10:00 AM', $label);

        $lecturerTip = $mapper->statusTip('pending', 'lecturer', $label);
        $lines = explode("\n", $lecturerTip);
        $this->assertCount(2, $lines, 'two lines, never inline HTML');
        $this->assertSame("Your request has been Submitted and is AWAITING the Programme Leader's decision (Approve or Reject).", $lines[0]);
        $this->assertSame('If the Programme Leader has not Approved this Request by Fri, 6 Nov 2026, 10:00 AM, it will be Automatically Rejected.', $lines[1]);

        // the PL reads the same fact from the other side of the decision
        $plTip = $mapper->statusTip('pending', 'pl', $label);
        $this->assertSame('This request is AWAITING your review and decision (Approve or Reject).', explode("\n", $plTip)[0]);
        $this->assertSame('If you have not Approved it by Fri, 6 Nov 2026, 10:00 AM, it will be Automatically Rejected.', explode("\n", $plTip)[1]);

        // a decided row (or no deadline) keeps the shipped single line
        $this->assertSame(
            'The Programme Leader has REJECTED your request.',
            $mapper->statusTip('rejected', 'lecturer', $label),
        );
        $this->assertSame(
            "Your request has been Submitted and is AWAITING the Programme Leader's decision (Approve or Reject).",
            $mapper->statusTip('pending'),
        );
    }

    public function test_the_deadline_skips_a_semester_holiday(): void
    {
        $request = $this->pendingRequest('2026-11-09', '10:00:00'); // Monday

        // Deepavali lands on that Monday (week 8 day 0 of the fixture semester).
        Holiday::create([
            'semester_id' => $this->semester->id,
            'week_number' => 8,
            'day_of_week' => 0,
            'label' => 'Deepavali',
        ]);

        $mapper = app(ReplacementRequestMapper::class);
        $strings = $mapper->replacementStrings($request, $this->semesterStart);

        // The holiday contributes no working minutes, so the walk continues into
        // Friday's tail: 17:30 − 3 hours = 14:30.
        $this->assertSame('Fri, 6 Nov 2026, 2:30 PM', $mapper->approvalDeadlineLabel($strings['date'], $strings['time'], (int) $this->semester->id));

        // and the same request is NOT auto-rejected: Friday 08:45 → Monday 10:00
        // counts only Friday's 8 h 45 min, which is far more than 3 hours.
        $this->assertSame(525, (new WorkingHours($this->semester))->gapMinutes(
            Carbon::parse('2026-11-06 08:45', LocalTime::TZ),
            Carbon::parse('2026-11-09 10:00', LocalTime::TZ),
        ));
    }
}
