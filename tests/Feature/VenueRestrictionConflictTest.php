<?php

namespace Tests\Feature;

use App\Livewire\CohortTimetable;
use App\Livewire\MyTimetable;
use App\Livewire\StudentMyTimetable;
use App\Livewire\VenueTimetable;
use App\Models\ClassSession;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SDD change `b005-diploma-conflict` — the B005 venue-restriction violation
 * (session 38: AMIT2034 P, Wed 11:00–13:00, combined DFT+DSF diploma lecture)
 * renders as a DERIVED `conflict` on every timetable page. No DB mutation:
 * the 3-state slot machine (FR 4.11) is untouched; display-only derivation.
 */
final class VenueRestrictionConflictTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function conflictedSessionId(): int
    {
        // Session 38 is dataset-stable (frozen anchor in tasks/design), but
        // derive it from the rule itself so the test pins the RULE, not an id.
        return (int) ClassSession::query()
            ->whereHas('venue', fn ($q) => $q->where('room_code', 'B005'))
            ->whereHas('cohorts.programme', fn ($q) => $q->where('programme_code', 'like', 'D%'))
            ->value('id');
    }

    private function danielUser(): User
    {
        // En. Daniel Royd Michael — the owning lecturer of session 38.
        return User::where('name', 'Daniel Royd Michael')->firstOrFail();
    }

    private function otherLecturerUser(): User
    {
        return User::where('role', 'lecturer')->where('name', '!=', 'Daniel Royd Michael')->firstOrFail();
    }

    public function test_session_38_conflicts_on_all_four_pages(): void
    {
        $sessionId = $this->conflictedSessionId();
        $this->assertGreaterThan(0, $sessionId);

        // MyTimetable — Daniel sees his own class loud-conflicted.
        $my = Livewire::actingAs($this->danielUser())->test(MyTimetable::class);
        $my->assertViewHas('eventsByWeek', function ($weeks) use ($sessionId): bool {
            foreach ($weeks as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    if (($ev['id'] ?? null) === $sessionId) {
                        return $ev['status'] === 'conflict';
                    }
                }
            }

            return false;
        });

        // CohortTimetable — student-scoped (own cohort only, FR 1.2): a student
        // of one of session 38's actual cohorts sees the conflict.
        $session38 = ClassSession::findOrFail($sessionId);
        $attendeeCohortId = (int) $session38->cohorts->first()->id;
        $dftStudent = User::whereHas('student', fn ($q) => $q->where('cohort_id', $attendeeCohortId))->firstOrFail();
        $cohort = Livewire::actingAs($dftStudent)->test(CohortTimetable::class);
        $cohort->assertViewHas('eventsByCohort', function ($byCohort) use ($sessionId): bool {
            foreach ($byCohort as $weeks) {
                foreach ($weeks as $weekEvents) {
                    foreach ($weekEvents as $ev) {
                        if (($ev['id'] ?? null) === $sessionId) {
                            return $ev['status'] === 'conflict';
                        }
                    }
                }
            }

            return false;
        });

        // StudentMyTimetable — the same attendee student's own view.
        $student = Livewire::actingAs($dftStudent)->test(StudentMyTimetable::class);
        $student->assertViewHas('eventsByWeek', function ($weeks) use ($sessionId): bool {
            foreach ($weeks as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    if (($ev['id'] ?? null) === $sessionId) {
                        return $ev['status'] === 'conflict';
                    }
                }
            }

            return false;
        });

        // VenueTimetable — B005 grid (owner and non-owner). Venue events carry
        // SLOT ids, so match by module code + type (AMIT2034 P in B005 = session 38).
        $matchAmit = fn (array $ev): bool => $ev['code'] === 'AMIT2034' && $ev['type'] === 'P';

        $venueOwner = Livewire::withQueryParams(['venue' => 'B005'])
            ->actingAs($this->danielUser())
            ->test(VenueTimetable::class);
        $venueOwner->assertViewHas('eventsByWeek', function ($weeks) use ($matchAmit): bool {
            foreach ($weeks as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    if ($matchAmit($ev)) {
                        return $ev['status'] === 'conflict' && $ev['mine'] === true;
                    }
                }
            }

            return false;
        });

        $venueOther = Livewire::withQueryParams(['venue' => 'B005'])
            ->actingAs($this->otherLecturerUser())
            ->test(VenueTimetable::class);
        $venueOther->assertViewHas('eventsByWeek', function ($weeks) use ($matchAmit): bool {
            foreach ($weeks as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    if ($matchAmit($ev)) {
                        return $ev['status'] === 'conflict' && $ev['mine'] === false;
                    }
                }
            }

            return false;
        });
    }

    public function test_rsd_only_b005_sessions_stay_normal(): void
    {
        $component = Livewire::withQueryParams(['venue' => 'B005'])
            ->actingAs($this->otherLecturerUser())
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($weeks): bool {
            foreach ($weeks as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    // BMIS2113 = the RSD-only B005 practicals — must stay normal.
                    // (AMIT2034 is the legitimately-flagged conflict session.)
                    if ($ev['code'] === 'BMIS2113' && $ev['status'] === 'conflict') {
                        return false;
                    }
                }
            }

            return true;
        });
    }

    public function test_b006_sessions_stay_normal_regardless_of_module(): void
    {
        $component = Livewire::withQueryParams(['venue' => 'B006'])
            ->actingAs($this->otherLecturerUser())
            ->test(VenueTimetable::class);

        $component->assertViewHas('eventsByWeek', function ($weeks): bool {
            foreach ($weeks as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    if ($ev['venue'] === 'B006' && $ev['status'] === 'conflict') {
                        return false; // priority rule — never a display conflict
                    }
                }
            }

            return true;
        });
    }

    public function test_twin_merge_severity_keeps_conflict_on_top(): void
    {
        $method = (new \ReflectionClass(VenueTimetable::class))->getMethod('reduceTwins');
        $method->setAccessible(true);

        $base = fn (array $over) => array_merge([
            'id' => 1, 'di' => 2, 'start' => 6, 'end' => 9, 'code' => 'AMIT2034',
            'name' => 'Fundamentals of Computer Networks', 'type' => 'P', 'venue' => 'B005',
            'lecturer' => 'En. Daniel Royd Michael', 'cohort' => 'DFT1S1G1',
            'cohorts' => ['DFT1S1G1'], 'studentCount' => 20, 'status' => 'normal',
            'remarks' => '', 'mine' => false,
        ], $over);

        $merged = $method->invoke(new VenueTimetable, [
            $base(['status' => 'normal', 'mine' => false]),
            $base(['id' => 2, 'status' => 'conflict', 'cohort' => 'DSF1S1G1', 'cohorts' => ['DSF1S1G1'], 'studentCount' => 18, 'mine' => true]),
        ]);

        $this->assertSame('conflict', $merged['status']); // conflict outranks normal
        $this->assertSame('DFT1S1G1 + DSF1S1G1', $merged['cohort']);
        $this->assertTrue($merged['mine']); // mine = OR
    }

    public function test_pending_request_supersedes_conflict_display(): void
    {
        $sessionId = $this->conflictedSessionId();
        $daniel = $this->danielUser();

        // Seed a pending replacement request on session 38, week 1.
        $request = ReplacementRequest::factory()->create([
            'class_session_id' => $sessionId,
            'week_number' => 1,
            'proposer_id' => $daniel->id,
            'status' => 'pending',
        ]);

        $component = Livewire::actingAs($daniel)->test(MyTimetable::class);
        $component->assertViewHas('eventsByWeek', function ($weeks) use ($sessionId): bool {
            foreach ($weeks[0] as $ev) {
                if (($ev['id'] ?? null) === $sessionId) {
                    return $ev['status'] === 'pending'; // pending > conflict
                }
            }

            return false;
        });

        $request->delete();

        $after = Livewire::actingAs($daniel)->test(MyTimetable::class);
        $after->assertViewHas('eventsByWeek', function ($weeks) use ($sessionId): bool {
            foreach ($weeks[0] as $ev) {
                if (($ev['id'] ?? null) === $sessionId) {
                    return $ev['status'] === 'conflict'; // conflict re-emerges
                }
            }

            return false;
        });
    }

    public function test_d_prefix_matches_only_diploma_programmes(): void
    {
        $diplomaCodes = Programme::where('programme_code', 'like', 'D%')->pluck('programme_code')->all();
        foreach ($diplomaCodes as $code) {
            $this->assertStringContainsStringIgnoringCase('diploma', Programme::where('programme_code', $code)->value('programme_name'));
        }
        $this->assertNotEmpty($diplomaCodes); // the rule has real targets
    }
}
