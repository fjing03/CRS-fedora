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
 * SDD change `b005-diploma-allowed` (2026-10-27) — the R2 no-Diploma rule
 * (SDD b005-diploma-conflict's law) was REPEALED by the user's ruling:
 * Diploma cohorts may use B005. The receipts invert to the POSITIVE: the
 * formerly-flagged standing session (AMIT2034 P in B005, D*-cohort-only)
 * now renders `normal` on every timetable page and no payload carries a
 * venue-restriction conflict. No DB mutation: the 3-state slot machine
 * (FR 4.11) is untouched; display-only derivation.
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

    public function test_d_cohort_b005_session_renders_normal_on_all_four_pages(): void
    {
        $sessionId = $this->conflictedSessionId();
        $this->assertGreaterThan(0, $sessionId);

        // MyTimetable — Daniel sees his own class CLEAN (the repeal receipt).
        $my = Livewire::actingAs($this->danielUser())->test(MyTimetable::class);
        $my->assertViewHas('eventsByWeek', function ($weeks) use ($sessionId): bool {
            foreach ($weeks as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    if (($ev['id'] ?? null) === $sessionId) {
                        return $ev['status'] === 'normal';
                    }
                }
            }

            return false;
        });

        // CohortTimetable — student-scoped (own cohort only, FR 1.2): a
        // student of one of the session's actual cohorts sees it clean.
        $session38 = ClassSession::findOrFail($sessionId);
        $attendeeCohortId = (int) $session38->cohorts->first()->id;
        $dftStudent = User::whereHas('student', fn ($q) => $q->where('cohort_id', $attendeeCohortId))->firstOrFail();
        $cohort = Livewire::actingAs($dftStudent)->test(CohortTimetable::class);
        $cohort->assertViewHas('eventsByCohort', function ($byCohort) use ($sessionId): bool {
            foreach ($byCohort as $weeks) {
                foreach ($weeks as $weekEvents) {
                    foreach ($weekEvents as $ev) {
                        if (($ev['id'] ?? null) === $sessionId) {
                            return $ev['status'] === 'normal';
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
                        return $ev['status'] === 'normal';
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
                        return $ev['status'] === 'normal' && $ev['mine'] === true;
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
                        return $ev['status'] === 'normal' && $ev['mine'] === false;
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
            // occurrence-week-pin-truthfulness T20: the W8/W14 weeks carry the
            // DERIVED holiday rows (the recurring families whose venue owns
            // them — the holiday family's own status family). The loop scope
            // stays on the normal weeks — the derived weeks are asserted by
            // their own VenueTimetableTest row.
            $weeks = array_slice($weeks, 0, 7, true);
            foreach ($weeks as $weekEvents) {
                foreach ($weekEvents as $ev) {
                    // BMIS2113 = the RSD-only B005 practicals — stay normal.
                    // (AMIT2034: the repeal receipt — normal above.)
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

        // occurrence-week-pin-truthfulness T20: the derived weeks (W8/W14)
        // carry the holiday family's conflict shapes — the loop's scope stays
        // on the normal weeks (the derived weeks assert by their own row).
        $component->assertViewHas('eventsByWeek', function ($weeks): bool {
            $weeks = array_slice($weeks, 0, 7, true);
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

    public function test_pending_request_rides_a_clean_b005_session(): void
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

        // SDD b005-diploma-allowed re-cut: the formerly "natural conflict"
        // pair rides CLEAN (the repeal) — only the pending amber renders
        // (requestId carries it; the slotMap's last-write-wins shows the
        // amber cell at the same hour; no conflict block can stack).
        $component = Livewire::actingAs($daniel)->test(MyTimetable::class);
        $component->assertViewHas('eventsByWeek', function ($weeks) use ($sessionId): bool {
            $hasPending = false;
            foreach ($weeks[0] as $ev) {
                if (($ev['requestId'] ?? null) !== null && ($ev['status'] ?? null) === 'pending') {
                    $hasPending = true;
                }
                if (($ev['id'] ?? null) === $sessionId && ($ev['status'] ?? null) === 'conflict') {
                    return false; // the repeal receipt: no conflict block rides the B005 session
                }
            }

            return $hasPending;
        });

        $request->delete();

        $after = Livewire::actingAs($daniel)->test(MyTimetable::class);
        $after->assertViewHas('eventsByWeek', function ($weeks) use ($sessionId): bool {
            foreach ($weeks[0] as $ev) {
                if (($ev['id'] ?? null) === $sessionId) {
                    return $ev['status'] === 'normal'; // clean re-emerges (the repeal receipt)
                }
            }

            return false;
        });
    }

    public function test_d_prefix_matches_only_diploma_programmes(): void  // the dataset identity (the repeal's receipts compose on)
    {
        $diplomaCodes = Programme::where('programme_code', 'like', 'D%')->pluck('programme_code')->all();
        foreach ($diplomaCodes as $code) {
            $this->assertStringContainsStringIgnoringCase('diploma', Programme::where('programme_code', $code)->value('programme_name'));
        }
        $this->assertNotEmpty($diplomaCodes); // the rule has real targets
    }
}
