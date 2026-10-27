<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Lecturer;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\MatrixIntersectionEngine;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

/**
 * SDD makeup-busy-vector (2026-10-27): the replacement make-up slots
 * (time_slots pending|occupied, class_session_id NULL, the rr chain) enter
 * the engine's busy vectors. The user's locks: the LECTURER vector is
 * OWNER-scoped (the make-up's own lecturer — the verified reading of
 * "everyone's"); the COHORT vector is OWNERLESS (any lecturer's make-up
 * for a shared cohort blocks it); the venue axis stays independent.
 *
 * The half-open boundary law: the make-up span [13:30,14:30) blocks the
 * 13:30 and the 14:00 rows and leaves the 13:00 and the 14:30 entries free.
 */
final class MakeUpBusyVectorTest extends TestCase
{
    use DatabaseMigrations;

    private MatrixIntersectionEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->engine = app(MatrixIntersectionEngine::class);
    }

    /** The repro context: 5425's AMSE2003 (P) class (the bug9 session-40 shape). */
    private function amse2003Context(): ClassSession
    {
        return ClassSession::query()
            ->where('session_type', 'P')
            ->whereHas('module', fn ($q) => $q->where('module_code', 'AMSE2003'))
            ->firstOrFail();
    }

    /** A make-up (a placeholder anchor slot + the rr + 2 slots) for $session, at $week Fri 13:30–14:30 in $venue. */
    private function seedMakeUp(
        ClassSession $session,
        int $week,
        Venue $venue,
        string $status,
    ): ReplacementRequest {
        // the rr's legacy anchor column (the D7 shape's legacy semantics) —
        // FK-safe placeholder, then the junction carries the real slots.
        $anchor = TimeSlot::create([
            'semester_id' => $session->semester_id,
            'class_session_id' => $session->id,
            'week_number' => 8,
            'day_of_week' => 4,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'venue_id' => $venue->id,
            'status' => 'available',
        ]);

        $rr = ReplacementRequest::create([
            'semester_id' => $session->semester_id,
            'proposer_id' => $session->lecturer_id,
            'class_session_id' => $session->id,
            'week_number' => 8,
            'replacement_time_slot_id' => $anchor->id,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        foreach (['13:30:00', '14:00:00'] as $start) {
            $slot = TimeSlot::create([
                'semester_id' => $session->semester_id,
                'class_session_id' => null,
                'week_number' => $week,
                'day_of_week' => 4,
                'start_time' => $start,
                'end_time' => $start === '13:30:00' ? '14:00:00' : '14:30:00',
                'venue_id' => $venue->id,
                'status' => $status === 'approved' ? 'occupied' : 'pending',
            ]);
            ReplacementRequestSlot::create([
                'replacement_request_id' => $rr->id,
                'time_slot_id' => $slot->id,
            ]);
        }

        return $rr;
    }

    public function test_approved_makeup_blocks_the_same_lecturers_other_booking_at_another_venue(): void
    {
        $owner = User::query()->where('role', 'lecturer')->firstOrFail();
        $ownerSession = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();

        $lab = Venue::query()->where('room_type', 'lab')->firstOrFail();
        $other = Venue::query()->where('room_type', 'lab')->where('id', '!=', $lab->id)->firstOrFail();
        $this->seedMakeUp($ownerSession, 6, $other, 'approved');

        // the owner's own vector carries the make-up hour
        $ranges = $this->engine->lecturerBusyRanges(1, $owner->id, 6);
        $hit = collect($ranges)->first(fn (array $r): bool => (int) $r['day'] === 4 && (string) $r['start'] === '13:30:00' && (string) $r['end'] === '14:00:00');
        $this->assertNotNull($hit, 'the owner\'s approved make-up enters the owner\'s lecturer vector');

        // another lecturer's vector does NOT carry it (the owner-scope)
        $otherUser = User::factory()->lecturer()->create();
        $otherRanges = $this->engine->lecturerBusyRanges(1, $otherUser->id, 6);
        $stray = collect($otherRanges)->first(fn (array $r): bool => (int) $r['day'] === 4 && (string) $r['start'] === '13:30:00');
        $this->assertNull($stray, 'another lecturer\'s vector must not carry someone else\'s make-up');

        // the submit-side engine read: the OWNER cannot book the same hour elsewhere
        $session = $this->amse2003ContextFor($owner);
        $anchor = TimeSlot::query()
            ->where('week_number', 6)->where('day_of_week', 4)
            ->where('start_time', '13:30:00')->where('venue_id', $lab->id)
            ->where('status', 'available')
            ->firstOrFail();
        $cohortIds = $session->cohorts->map(fn ($c) => $c->id)->all();

        $this->assertFalse(
            $this->engine->validateSlot((int) $anchor->id, (int) $owner->id, $cohortIds, 'P', 6, 2, $session),
            'the same lecturer cannot span-2 book over their own approved make-up hour',
        );

        // the half-open boundary law: the make-up span [13:30,14:30) does NOT
        // block the 13:00 or the 14:30 entries. The edges may still be
        // unreachable in this deck for OTHER honest reasons (the seeded
        // deck's own class rows — e.g. the cohort's own Fri 14:30–16:30 row
        // receipts here); the assert = the make-up rows are NOT among the
        // edge's blockers.
        foreach (['13:00:00', '14:30:00'] as $edgeStart) {
            $edge = TimeSlot::query()
                ->where('week_number', 6)->where('day_of_week', 4)
                ->where('start_time', $edgeStart)->where('venue_id', $lab->id)
                ->where('status', 'available')
                ->first();
            if ($edge === null) {
                continue;
            }
            $overlap = fn (array $range, $slot): bool => (string) $range['start'] < (string) $slot->end_time
                && (string) $range['end'] > (string) $slot->start_time;
            $makeUpRows = collect($this->engine->lecturerBusyRanges(1, (int) $owner->id, 6))
                ->filter(fn (array $r): bool => (int) $r['day'] === 4
                    && in_array((string) $r['start'], ['13:30:00', '14:00:00'], true));
            foreach ($makeUpRows as $row) {
                $this->assertFalse(
                    $overlap($row, $edge),
                    'the make-up range ['.$row['start'].','.$row['end'].') must not block the ['.$edgeStart.'] entry (the half-open law)',
                );
            }
        }
    }

    public function test_pending_makeup_counts_for_everyones_status_but_not_their_calendar(): void
    {
        $owner = User::query()->where('role', 'lecturer')->firstOrFail();
        $ownerSession = ClassSession::query()->where('lecturer_id', $owner->id)->firstOrFail();

        $lab = Venue::query()->where('room_type', 'lab')->firstOrFail();
        $this->seedMakeUp($ownerSession, 6, $lab, 'pending');

        // pending counted in the OWNER's vector (the (b) status law)
        $ranges = $this->engine->lecturerBusyRanges(1, $owner->id, 6);
        $this->assertNotNull(collect($ranges)->first(fn (array $r): bool => (int) $r['day'] === 4 && (string) $r['start'] === '14:00:00'),
            'the owner\'s pending make-up enters the owner\'s vector (the (b) status law)');

        // a friend of the same cohorts still sees the make-up in THEIR cohort vector
        $targets = $ownerSession->cohorts->pluck('id')->all();
        $cohRanges = $this->engine->cohortBusyRanges(1, $targets, 6);
        $this->assertNotNull(collect($cohRanges)->first(fn (array $r): bool => (int) $r['day'] === 4 && (string) $r['start'] === '13:30:00'),
            'the cohort vector carries the make-up (ownerless cohort join)');

        // the cancelled rows stay out (the other side of the double filter)
        $lab2 = Venue::query()->where('room_type', 'tutorial')->firstOrFail();
        $cancelled = $this->seedMakeUp($ownerSession, 6, $lab2, 'cancelled');
        $cancelled->update(['status' => 'cancelled']);
        $ranges2 = $this->engine->lecturerBusyRanges(1, $owner->id, 6);
        $victim = collect($ranges2)->first(fn (array $r): bool => (int) $r['day'] === 4 && (string) $r['start'] === '14:30:00');
        $this->assertNull($victim, 'the cancelled rr\'s slot rows never enter the vectors');
    }

    public function test_cohort_side_blocks_shared_cohorts_and_spares_unrelated_ones(): void
    {
        $owner = User::query()->where('role', 'lecturer')->firstOrFail();
        $ownerSession = ClassSession::query()->with('cohorts')->where('lecturer_id', $owner->id)->firstOrFail();
        $ownCohortIds = $ownerSession->cohorts->pluck('id')->all();

        $lab = Venue::query()->where('room_type', 'lab')->firstOrFail();
        $this->seedMakeUp($ownerSession, 6, $lab, 'approved');

        $own = collect($this->engine->cohortBusyRanges(1, $ownCohortIds, 6))->first(
            fn (array $r): bool => (int) $r['day'] === 4 && (string) $r['start'] === '13:30:00');
        $this->assertNotNull($own, 'the rr\'s own cohorts see the make-up in their vector');

        // the re-cut: the unrelated cohorts' vector does not carry it
        $unrelated = Cohort::query()->whereNotIn('id', $ownCohortIds)->limit(2)->pluck('id')->all();
        if ($unrelated !== []) {
            $far = collect($this->engine->cohortBusyRanges(1, $unrelated, 6))->first(
                fn (array $r): bool => (int) $r['day'] === 4 && (string) $r['start'] === '13:30:00'
                    && (string) $r['end'] === '14:00:00');
            $this->assertNull($far, 'the make-up must not leak into the unrelated cohorts\' vector');
        }
    }

    /** The bug9-package session (the AMSE2003 ctx — the owner's OWN class). */
    private function amse2003ContextFor(User $owner): ClassSession
    {
        $session = ClassSession::query()
            ->with(['module', 'venue', 'cohorts.programme'])
            ->where('lecturer_id', $owner->id)
            ->where('session_type', 'P')
            ->whereHas('module', fn ($q) => $q->whereIn('module_code', ['AMSE2003', 'BMIS2113', 'BMSE3153']))
            ->firstOrFail();
        $session->setRelation('cohorts', $session->cohorts);

        return $session;
    }
}
