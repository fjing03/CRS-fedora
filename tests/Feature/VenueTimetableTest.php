<?php

namespace Tests\Feature;

use App\Livewire\VenueTimetable;
use App\Models\ClassSession;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SDD changes `venue-timetable-db` + `venue-event-blocks-db` + the
 * `venue-pending-and-type-labels` reserved-slot second pass — the
 * VenueTimetable Livewire component against the REAL seeded schedule
 * (suite-proven explicit `$this->seed()` pattern inside RefreshDatabase's
 * per-test transaction).
 *
 * Frozen anchors (real data): B006 week 1 has 48 occupied ROWS across 15
 * sessions (coalesced into one event per session); one of its Monday-09:00
 * sessions is BMIT2154 "Switching and Routing Technologies" (lecturer user_id
 * 3); the component is render-only (no week/venue actions).
 * Holidays (canonical seed): W8 Monday, W14 Wed+Thu.
 */
final class VenueTimetableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function staffUser(string $staffId): User
    {
        $lecturer = Lecturer::where('staff_id', $staffId)->firstOrFail();

        return User::findOrFail($lecturer->user_id);
    }

    private function studentUser(): User
    {
        $student = Student::where('student_id', '25RSD0001')->firstOrFail();

        return User::findOrFail($student->user_id);
    }

    /**
     * Craft a pending reservation onto a free B006 slot (venue-pending-and-
     * type-labels D1 fixture style): slot → class_session_id NULL + status
     * 'pending', request → the ORIGINAL class being replaced (its module/
     * lecturer/cohorts become the event's class facts).
     *
     * @return array{0: TimeSlot, 1: ClassSession, 2: ReplacementRequest}
     */
    private function craftPendingReservation(User $proposer): array
    {
        $venue = Venue::where('room_code', 'B006')->firstOrFail();

        // A free interior-week slot the request can reserve (W1's counts are
        // frozen anchors for other tests; W8/W14 keep their holiday shapes).
        $slot = TimeSlot::query()
            ->where('venue_id', $venue->id)
            ->where('status', 'available')
            ->whereBetween('week_number', [2, 13])
            ->firstOrFail();
        $slot->update(['status' => 'pending']);

        // Any imported session with cohorts — the class being replaced.
        $original = ClassSession::query()
            ->whereHas('cohorts')
            ->with(['module', 'lecturer', 'cohorts.programme'])
            ->firstOrFail();

        $request = ReplacementRequest::create([
            'semester_id' => $slot->semester_id,
            'proposer_id' => $proposer->id,
            'class_session_id' => $original->id,
            'week_number' => $slot->week_number,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        return [$slot, $original, $request];
    }

    /**
     * Expected cohort codes for a session — mirrors the component's
     * cohortCode() contract (§10.0.2: same name + same meaning).
     *
     * @return array<int, string>
     */
    private function expectedCohortCodes(ClassSession $session): array
    {
        return $session->cohorts
            ->map(fn ($c) => $c->programme->programme_code.$c->current_year.'(S'.$c->semester.')G'.$c->tutorial_group)
            ->values()
            ->all();
    }

    public function test_totals_and_events_match_seed_for_b006_week_1(): void
    {
        // Coalesced contract (venue-block-span-coalescing): one event per
        // distinct SESSION, not per half-hour row. The frozen "48" lives on in
        // the row-counted cards (sumOccupied), not in the event count.
        $expectedSessions = TimeSlot::query()
            ->where('week_number', 1)
            ->where('status', 'occupied')
            ->whereHas('classSession', fn ($q) => $q->whereHas('venue', fn ($v) => $v->where('room_code', 'B006')))
            ->distinct()
            ->count('class_session_id');

        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($this->staffUser('5425'))
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($eventsByWeek) use ($expectedSessions): bool {
            // contiguous 0..13 map (client-side WeekNavigator depends on it)
            if (count($eventsByWeek) !== 14 || ! isset($eventsByWeek[13])) {
                return false;
            }
            // B006 week 1 = 48 occupied ROWS across 15 distinct sessions
            // (coalesced: one event per session)
            if (count($eventsByWeek[0]) !== $expectedSessions) {
                return false;
            }

            // every event maps into the baseEvent contract
            $ev = $eventsByWeek[0][0];

            return isset($ev['di'], $ev['start'], $ev['end'], $ev['code'], $ev['venue'], $ev['mine']);
        });

        $component->assertViewHas('eventsByWeek', function ($eventsByWeek): bool {
            // The BMIT2154 L/T adjacent-session trap (proposal S1): Monday runs
            // the Lecture 09:00–11:00 straight into the Tutorial 11:00–12:00 —
            // two DISTINCT sessions that must NOT fuse.
            $bmit = [];
            foreach ($eventsByWeek[0] as $ev) {
                if ($ev['code'] === 'BMIT2154' && $ev['di'] === 0) {
                    $bmit[] = $ev;
                }
            }
            if (count($bmit) !== 2) {
                return false;
            }

            foreach ($bmit as $ev) {
                $isLecture = $ev['start'] === 2;
                $isTutorial = $ev['start'] === 6;
                if (! $isLecture && ! $isTutorial) {
                    return false;
                }
                if ($isLecture && ($ev['end'] !== 5 || $ev['name'] !== 'Switching and Routing Technologies' || $ev['venue'] !== 'B006' || $ev['status'] !== 'normal')) {
                    return false;   // coalesced span-4: 09:00 → index 2, 11:00 → end index 5
                }
                if ($isTutorial && $ev['end'] !== 7) {
                    return false;   // coalesced span-2: 11:00 → index 6, 12:00 → end index 7
                }
            }

            return true;
        });

        $component->assertViewHas('totalsByWeek', function ($totals): bool {
            // 5425 (user 1) teaches nothing in B006 — ownership cards are 0.
            return count($totals) === 14
                && $totals[0]['total'] === 120   // 6 days × 20 slots
                && $totals[0]['occupied'] === 48
                && $totals[0]['available'] === 72
                && $totals[0]['myClasses'] === 0
                && $totals[0]['myHours'] === '0';
        });
    }

    public function test_ownership_cards_count_only_the_viewers_rows(): void
    {
        // BMIT2154 Mon-09:00 in B006 W1 belongs to user 3 (frozen anchor).
        $bmit2154 = TimeSlot::query()
            ->where('week_number', 1)
            ->where('status', 'occupied')
            ->whereHas('classSession', fn ($q) => $q->whereHas('module', fn ($m) => $m->where('module_code', 'BMIT2154'))
                ->whereHas('venue', fn ($v) => $v->where('room_code', 'B006')))
            ->with('classSession')
            ->firstOrFail();
        $owner = User::findOrFail($bmit2154->classSession->lecturer_id);

        // Expected values derived from the DB directly (not hardcoded): the
        // owner's distinct sessions + occupied slots in B006 W1.
        $ownerSlots = TimeSlot::query()
            ->where('week_number', 1)
            ->where('status', 'occupied')
            ->whereHas('classSession', fn ($q) => $q->where('lecturer_id', $owner->id)
                ->whereHas('venue', fn ($v) => $v->where('room_code', 'B006')))
            ->get();
        $expectedClasses = $ownerSlots->pluck('class_session_id')->unique()->count();
        $expectedHours = rtrim(rtrim(sprintf('%.1f', $ownerSlots->count() * 0.5), '0'), '.');
        $this->assertGreaterThan(0, $expectedClasses);

        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($owner)
            ->test(VenueTimetable::class);

        $component->assertViewHas('totalsByWeek', function ($totals) use ($expectedClasses, $expectedHours): bool {
            return $totals[0]['myClasses'] === $expectedClasses
                && $totals[0]['myHours'] === $expectedHours;
        });
    }

    public function test_twin_merge_collapses_combined_lectures_by_severity(): void
    {
        // Real twins cannot exist (occupied/pending partial unique index), so
        // the defensive merge is unit-tested directly via reflection.
        $method = (new \ReflectionClass(VenueTimetable::class))->getMethod('mergeTwinEvents');
        $method->setAccessible(true);

        $base = fn (array $over) => array_merge([
            'id' => 1, 'di' => 0, 'start' => 2, 'end' => 3, 'code' => 'BMIT2154',
            'name' => 'Switching and Routing Technologies', 'type' => 'L', 'venue' => 'B006',
            'lecturer' => 'Dr. Christopher Lazarus', 'cohort' => 'RSD3S1G1',
            'cohorts' => ['RSD3S1G1'], 'studentCount' => 20, 'status' => 'normal',
            'remarks' => '', 'mine' => false,
        ], $over);

        $merged = $method->invoke(new VenueTimetable, [
            '0:2' => [
                $base(['id' => 1, 'cohort' => 'RSD3S1G1', 'cohorts' => ['RSD3S1G1'], 'studentCount' => 20, 'status' => 'normal', 'mine' => false]),
                $base(['id' => 2, 'cohort' => 'RSD3S1G2', 'cohorts' => ['RSD3S1G2'], 'studentCount' => 18, 'status' => 'pending', 'mine' => true]),
            ],
            '1:4' => [
                $base(['id' => 3, 'di' => 1, 'start' => 4, 'cohort' => 'RSD3S1G1', 'cohorts' => ['RSD3S1G1'], 'studentCount' => 20, 'status' => 'normal']),
            ],
        ]);

        $this->assertCount(2, $merged);
        $twin = $merged[0];
        $this->assertSame('pending', $twin['status']);          // severity wins
        $this->assertSame('RSD3S1G1 + RSD3S1G2', $twin['cohort']); // cohorts joined
        $this->assertSame(['RSD3S1G1', 'RSD3S1G2'], $twin['cohorts']);
        $this->assertSame(38, $twin['studentCount']);           // students summed
        $this->assertTrue($twin['mine']);                       // mine = OR
        $this->assertSame(1, $twin['id']);                      // first slot id kept
        $this->assertSame(3, $merged[1]['id']);                 // singleton passed through
    }

    public function test_mine_flag_true_only_for_the_owning_lecturer(): void
    {
        // Derive the owning user from the data itself (ids are not stable
        // across seed runs): any B006 W1 occupied slot's session owner.
        $slot = TimeSlot::query()
            ->where('week_number', 1)
            ->where('status', 'occupied')
            ->whereHas('classSession', fn ($q) => $q->whereHas('venue', fn ($v) => $v->where('room_code', 'B006')))
            ->with('classSession')
            ->firstOrFail();
        $owner = User::findOrFail($slot->classSession->lecturer_id);
        $other = User::where('role', 'lecturer')->where('id', '!=', $owner->id)->firstOrFail();

        $asOwner = Livewire::withQueryParams(['venue' => 'B006'])->actingAs($owner)->test(VenueTimetable::class);
        $asOwner->assertViewHas('eventsByWeek', function ($events): bool {
            foreach ($events[0] as $ev) {
                if ($ev['code'] === 'BMIT2154' && $ev['mine'] === true) {
                    return true;
                }
            }

            return false;
        });

        $asOther = Livewire::withQueryParams(['venue' => 'B006'])->actingAs($other)->test(VenueTimetable::class);
        $asOther->assertViewHas('eventsByWeek', function ($events): bool {
            foreach ($events[0] as $ev) {
                if ($ev['code'] === 'BMIT2154' && $ev['mine'] === true) {
                    return false; // must never be true for a non-owner
                }
            }

            return true;
        });
    }

    public function test_holiday_payload_contains_w8_monday(): void
    {
        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($this->staffUser('5425'))
            ->test(VenueTimetable::class);

        $component->assertViewHas('holidaysJs', function ($holidays): bool {
            foreach ($holidays as $h) {
                if ((int) $h['week'] === 8 && (int) $h['dayIndex'] === 0) {
                    return true;
                }
            }

            return false;
        });
    }

    public function test_own_class_on_public_holiday_is_in_payload_and_excluded_from_available(): void
    {
        // Crafted-row PH test (venue-event-blocks-db spec R2 payload contract):
        // the viewer's own session on the W8 Monday holiday must appear in
        // eventsByWeek, count in the ownership cards, and its day's free slots
        // must be EXCLUDED from sumAvailable (the grid renders that day as
        // offday cells — counting them Available contradicted the legend tip).
        $viewer = $this->staffUser('5425');   // teaches zero B006 classes (frozen anchor)
        $venue = Venue::where('room_code', 'B006')->firstOrFail();

        // Craft onto an existing free W8-Monday slot (avoids the occupied
        // partial unique index; keeps row counts stable for the total card).
        $slot = TimeSlot::query()
            ->where('venue_id', $venue->id)
            ->where('week_number', 8)
            ->where('day_of_week', 0)
            ->where('status', 'available')
            ->firstOrFail();
        $session = ClassSession::create([
            'semester_id' => $slot->semester_id,
            'module_id' => Module::query()->firstOrFail()->id,
            'lecturer_id' => $viewer->id,
            'day_of_week' => 0,
            'start_time' => $slot->start_time,
            'end_time' => $slot->end_time,
            'venue_id' => $venue->id,
            'session_type' => 'L',
        ]);
        $slot->update(['class_session_id' => $session->id, 'status' => 'occupied']);

        // Expected totals derived from the DB itself (house style): Available
        // = free W8 slots NOT on the holiday day. Guard: there must be free
        // holiday-day slots, or the exclusion assertion proves nothing.
        $freeW8 = TimeSlot::where('venue_id', $venue->id)->where('week_number', 8);
        $offdayFree = (clone $freeW8)->where('day_of_week', 0)->where('status', 'available')->count();
        $this->assertGreaterThan(0, $offdayFree);
        $expectedAvailable = $freeW8->where('status', 'available')->count() - $offdayFree;

        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($viewer)
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($events): bool {
            foreach ($events[7] as $ev) {
                if ($ev['di'] === 0 && $ev['mine'] === true) {
                    return true;   // the crafted own class on the holiday day
                }
            }

            return false;
        });

        $component->assertViewHas('totalsByWeek', function ($totals) use ($expectedAvailable): bool {
            return $totals[7]['total'] === 120
                && $totals[7]['myClasses'] === 1
                && $totals[7]['myHours'] === '0.5'
                && $totals[7]['available'] === $expectedAvailable;
        });
    }

    public function test_own_pending_reservation_renders_the_original_class_facts(): void
    {
        // Design D1 "mine" leg: the proposer's own reservation shows ONE
        // amber Pending event at the reserved slot's week/day/start, with
        // class facts from the ORIGINAL class (the class being replaced is
        // what will occupy this room), remarks byte-matching the legend tip.
        $viewer = $this->staffUser('5425');   // teaches zero B006 classes (frozen anchor)
        [$slot, $original] = $this->craftPendingReservation($viewer);

        // Same slotIndex() arithmetic as the component (30-min grid from 08:00).
        $idx = fn (string $t): int => ((int) substr($t, 0, 2) - 8) * 2 + intdiv((int) substr($t, 3, 2), 30);

        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($viewer)
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($events) use ($slot, $original, $idx): bool {
            $matches = [];
            foreach ($events[$slot->week_number - 1] as $ev) {
                if ($ev['id'] === $slot->id && $ev['status'] === 'pending') {
                    $matches[] = $ev;
                }
            }
            if (count($matches) !== 1) {
                return false;   // one request reserves one anchor slot — no coalescing
            }

            $ev = $matches[0];
            $expectedCohorts = $this->expectedCohortCodes($original);

            return $ev['di'] === $slot->day_of_week
                && $ev['start'] === $idx($slot->start_time)
                && $ev['end'] === $idx($slot->end_time) - 1
                && $ev['code'] === $original->module->module_code
                && $ev['name'] === $original->module->module_name
                && $ev['type'] === $original->session_type
                && $ev['lecturer'] === $original->lecturer->name
                && $ev['venue'] === 'B006'
                && $ev['cohort'] === (count($expectedCohorts) === 1
                    ? $expectedCohorts[0]
                    : implode(' + ', $expectedCohorts))
                && $ev['cohorts'] === $expectedCohorts
                && $ev['studentCount'] === (int) $original->cohorts->sum('student_count')
                && $ev['mine'] === true
                && $ev['remarks'] === 'Replacement request awaiting PL approval';
        });
    }

    public function test_other_lecturers_pending_reservation_is_not_mine(): void
    {
        // Design D1 "others'" leg (feature-test only — the demo DB has no
        // foreign-proposer pending request): renders amber WITHOUT the mine
        // border (event-others-pending).
        $viewer = $this->staffUser('5425');
        $proposer = User::where('role', 'lecturer')->where('id', '!=', $viewer->id)->firstOrFail();
        [$slot] = $this->craftPendingReservation($proposer);

        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($viewer)
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($events) use ($slot): bool {
            foreach ($events[$slot->week_number - 1] as $ev) {
                if ($ev['id'] === $slot->id && $ev['status'] === 'pending') {
                    return $ev['mine'] === false;
                }
            }

            return false;
        });
    }

    public function test_cancelled_reservation_emits_no_pending_event(): void
    {
        // Design D1 cancel legs: (A) the slot is still 'pending' but its
        // request is cancelled — the orphan defence emits nothing; (B) the
        // slot is released back to 'available' — it never re-enters the
        // query. No leak in either case.
        $viewer = $this->staffUser('5425');

        // Case A first, so crafting Case B cannot hit the one-active-request
        // partial unique index on the same (class_session, week).
        [$slotA, , $requestA] = $this->craftPendingReservation($viewer);
        $requestA->update(['status' => 'cancelled']);

        [$slotB, , $requestB] = $this->craftPendingReservation($viewer);
        $requestB->update(['status' => 'cancelled']);
        $slotB->update(['status' => 'available']);

        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($viewer)
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($events) use ($slotA, $slotB): bool {
            foreach ($events as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    if ($ev['id'] === $slotA->id || $ev['id'] === $slotB->id) {
                        return false;   // no leak in either leg
                    }
                }
            }

            return true;
        });
    }

    public function test_reserved_slot_beyond_semester_weeks_emits_nothing(): void
    {
        // Design D1 week guard: the same 1..$weeks window as the imported
        // path. The 1..14 grid is schema-enforced (time_slots_week_number_
        // check), so the out-of-bounds leg shrinks the active semester's
        // week_count instead — the guard reads $weeks from it at render time.
        $viewer = $this->staffUser('5425');
        [$slot] = $this->craftPendingReservation($viewer);
        $semester = Semester::active();
        $this->assertNotNull($semester);
        $this->assertGreaterThan(1, $slot->week_number);   // helper crafts interior weeks
        $semester->update(['week_count' => 1]);

        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($viewer)
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($events) use ($slot): bool {
            foreach ($events as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    if ($ev['id'] === $slot->id) {
                        return false;
                    }
                }
            }

            return true;
        });
    }

    public function test_orphan_pending_slot_emits_nothing(): void
    {
        // Design D1 orphan defence: a pending slot with no ReplacementRequest
        // row (should not exist — cancel/submit keep slot+request in lockstep)
        // emits nothing, silently. The render itself succeeding is the
        // "no error" half of the assertion.
        $viewer = $this->staffUser('5425');
        $venue = Venue::where('room_code', 'B006')->firstOrFail();
        $slot = TimeSlot::query()
            ->where('venue_id', $venue->id)
            ->where('status', 'available')
            ->whereBetween('week_number', [2, 13])
            ->firstOrFail();
        $slot->update(['status' => 'pending']);   // no request row → orphan

        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($viewer)
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($events) use ($slot): bool {
            foreach ($events as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    if ($ev['id'] === $slot->id) {
                        return false;
                    }
                }
            }

            return true;
        });
    }

    public function test_unknown_venue_code_falls_back_to_first_venue(): void
    {
        $component = Livewire::withQueryParams(['venue' => 'ZZZZ'])
            ->actingAs($this->staffUser('5425'))
            ->test(VenueTimetable::class);

        $component->assertViewHas('venue', function ($venue): bool {
            return $venue['code'] !== '' && $venue['code'] !== 'ZZZZ';
        });
    }

    public function test_lecturer_page_renders_real_module_code(): void
    {
        $this->actingAs($this->staffUser('5425'))
            ->get('/venue-timetable-ui?venue=B006')
            ->assertOk()
            ->assertSee('BMIT2154');
    }

    public function test_student_is_forbidden(): void
    {
        $this->actingAs($this->studentUser())
            ->get('/venue-timetable-ui')
            ->assertForbidden();
    }
}
