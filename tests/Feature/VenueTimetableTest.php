<?php

namespace Tests\Feature;

use App\Livewire\VenueTimetable;
use App\Models\Lecturer;
use App\Models\Student;
use App\Models\TimeSlot;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SDD change `venue-timetable-db` — the VenueTimetable Livewire component
 * against the REAL seeded schedule (suite-proven explicit `$this->seed()`
 * pattern inside RefreshDatabase's per-test transaction).
 *
 * Frozen anchors (real data): B006 week 1 has 48 occupied slots; one of its
 * Monday-09:00 sessions is BMIT2154 "Switching and Routing Technologies"
 * (lecturer user_id 3); the component is render-only (no week/venue actions).
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

    public function test_totals_and_events_match_seed_for_b006_week_1(): void
    {
        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($this->staffUser('5425'))
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($eventsByWeek): bool {
            // contiguous 0..13 map (client-side WeekNavigator depends on it)
            if (count($eventsByWeek) !== 14 || ! isset($eventsByWeek[13])) {
                return false;
            }
            // B006 week 1 = 48 occupied slots (frozen real-data anchor)
            if (count($eventsByWeek[0]) !== 48) {
                return false;
            }

            // every event maps into the baseEvent contract
            $ev = $eventsByWeek[0][0];

            return isset($ev['di'], $ev['start'], $ev['end'], $ev['code'], $ev['venue'], $ev['mine']);
        });

        $component->assertViewHas('eventsByWeek', function ($eventsByWeek): bool {
            // the BMIT2154 Monday-09:00 session appears with real module fields
            foreach ($eventsByWeek[0] as $ev) {
                if ($ev['code'] === 'BMIT2154') {
                    return $ev['name'] === 'Switching and Routing Technologies'
                        && $ev['di'] === 0
                        && $ev['start'] === 2  // 09:00 → index 2 from 08:00
                        && $ev['venue'] === 'B006'
                        && $ev['status'] === 'normal';
                }
            }

            return false;
        });

        $component->assertViewHas('totalsByWeek', function ($totals): bool {
            return count($totals) === 14
                && $totals[0]['total'] === 120   // 6 days × 20 slots
                && $totals[0]['occupied'] === 48
                && $totals[0]['available'] === 72
                && $totals[0]['pending'] === 0;
        });
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
