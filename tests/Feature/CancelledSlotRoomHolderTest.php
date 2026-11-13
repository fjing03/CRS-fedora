<?php

namespace Tests\Feature;

use App\Livewire\ReplacementArrangement;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Lecturer;
use App\Models\Module;
use App\Models\Programme;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * cancelled-slot-reuse Part B (design D8 / spec R7 S24–S25) — the
 * arrangement grid's ACTUAL holder explanations, backend payload receipt.
 *
 * The law under test: a room-only blocked cell names its ACTUAL active
 * holder — the live request receipt (junction or the documented anchor/span
 * fallback) outranks any stale `class_session_id`, a full actual span is
 * shown (never an anchor or half-cell), the public state word is exactly
 * NORMAL / PENDING / REPLACEMENT, an unknown or malformed claim stays
 * NEUTRAL (no fabricated class name, no resurrected cancelled original),
 * released cells stay unannotated, one holder in several arms is ONE library
 * identity while different holders survive identical display strings, and
 * the permitted vocabulary only — never a remark, a private cancellation
 * reason or a submission timestamp.
 */
final class CancelledSlotRoomHolderTest extends TestCase
{
    use RefreshDatabase;

    private User $lecturerA;

    private User $lecturerB;

    private Semester $semester;

    private Venue $venue;

    private Module $moduleA;

    private Module $moduleB;

    private Cohort $cohortA;

    private Cohort $cohortB;

    protected function setUp(): void
    {
        parent::setUp();

        /* the payload under test is date-free; the clock is pinned for parity
           with the arrangement fixtures (week 3 campus time) */
        Carbon::setTestNow(Carbon::parse('2026-09-14 09:00'));

        $this->semester = Semester::create([
            'semester_code' => '202605',
            'label' => '202605 Semester',
            'start_date' => '2026-08-31',
            'end_date' => '2026-12-06',
            'week_count' => 14,
        ]);

        $faculty = Faculty::create(['faculty_code' => 'FOCS', 'faculty_name' => 'FOCS']);
        $dept = Department::create(['dept_code' => 'DCIT', 'dept_name' => 'DCIT', 'faculty_id' => $faculty->id]);
        $prog = Programme::create(['programme_code' => 'RSD', 'programme_name' => 'RSD', 'faculty_id' => $faculty->id]);

        $this->venue = Venue::create([
            'room_code' => 'B100', 'room_name' => 'Tutorial Room B100',
            'room_type' => 'tutorial', 'allowed_session_types' => 'L,T', 'capacity' => 35,
        ]);

        $this->lecturerA = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer A']);
        Lecturer::create(['user_id' => $this->lecturerA->id, 'staff_id' => '6001', 'dept_id' => $dept->id, 'is_pl' => false]);
        $this->lecturerB = User::factory()->create(['role' => 'lecturer', 'name' => 'Lecturer B']);
        Lecturer::create(['user_id' => $this->lecturerB->id, 'staff_id' => '6002', 'dept_id' => $dept->id, 'is_pl' => false]);

        $this->cohortA = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 1, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 20,
        ]);
        $this->cohortB = Cohort::create([
            'programme_id' => $prog->id, 'current_year' => 3, 'semester' => 1,
            'tutorial_group' => 2, 'academic_year' => '2025/26', 'intake' => 'June 2023', 'student_count' => 18,
        ]);

        $this->moduleA = Module::create(['module_code' => 'BMIT1001', 'module_name' => 'Software Engineering', 'allowed_session_types' => 'L,T']);
        $this->moduleB = Module::create(['module_code' => 'BMIT2002', 'module_name' => 'Databases', 'allowed_session_types' => 'L,T']);

        // A's own recurring class: Monday 10:00–12:00 at B100 (cells 4–7).
        $this->createSession($this->lecturerA, $this->moduleA, [$this->cohortA], 1, '10:00:00', '12:00:00');
    }

    private function createSession(
        User $lecturer,
        Module $module,
        array $cohorts,
        int $day = 1,
        string $start = '10:00:00',
        string $end = '12:00:00',
        ?Venue $venue = null,
        string $type = 'L',
    ): ClassSession {
        $session = ClassSession::create([
            'semester_id' => $this->semester->id,
            'module_id' => $module->id,
            'lecturer_id' => $lecturer->id,
            'day_of_week' => $day,
            'start_time' => $start,
            'end_time' => $end,
            'venue_id' => ($venue ?? $this->venue)->id,
            'session_type' => $type,
        ]);
        $session->cohorts()->attach(collect($cohorts)->pluck('id')->all());

        return $session;
    }

    /**
     * The seeded deck's shape: contiguous 30-minute slot rows, optionally
     * carrying the session's id (the original reservation's legacy column).
     *
     * @return list<TimeSlot>
     */
    private function createBlockSlots(int $week, int $day, string $start, int $count, string $status, ?ClassSession $session = null): array
    {
        $slots = [];

        foreach (range(0, $count - 1) as $k) {
            $st = date('H:i:s', strtotime($start) + $k * 1800);
            $slots[] = TimeSlot::create([
                'semester_id' => $this->semester->id,
                'class_session_id' => $session?->id,
                'week_number' => $week,
                'day_of_week' => $day,
                'start_time' => $st,
                'end_time' => date('H:i:s', strtotime($st) + 1800),
                'venue_id' => $this->venue->id,
                'status' => $status,
            ]);
        }

        return $slots;
    }

    /**
     * A request + its live junction receipt (the OCC shape: the anchor column
     * rides the run's first cell, the junction carries the whole run).
     *
     * @param  list<TimeSlot>  $slots
     */
    private function makeRequest(
        User $proposer,
        ClassSession $source,
        int $sourceWeek,
        array $slots,
        string $status = 'pending',
        ?int $span = null,
    ): ReplacementRequest {
        $request = ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $proposer->id,
            'class_session_id' => $source->id,
            'week_number' => $sourceWeek,
            'replacement_time_slot_id' => $slots[0]->id,
            'replacement_span' => $span,
            'status' => $status,
            'submitted_at' => now(),
        ]);

        foreach ($slots as $slot) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slot->id,
            ]);
        }

        return $request;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payloadOf(User $lecturer): array
    {
        $payload = null;

        Livewire::actingAs($lecturer)->test(ReplacementArrangement::class)
            ->assertViewHas('payload', function (array $p) use (&$payload): bool {
                $payload = $p;

                return true;
            });

        return $payload;
    }

    /**
     * The B100 rows of one JS week index, keyed `day:startIdx` (two days can
     * share a start index — the key must never collide).
     *
     * @return array<string, array{0: int, 1: int, 2: int, 3: list<int>|null}>
     */
    private function rowsOf(array $payload, int $weekIdx): array
    {
        $rows = [];

        foreach ($payload['venueSlotsByWeek'][$weekIdx]['B100'] ?? [] as $row) {
            $rows[$row[0].':'.$row[1]] = $row;
        }

        return $rows;
    }

    /**
     * @return list<string>
     */
    private function resolveBlocks(array $payload, ?array $ids): array
    {
        if ($ids === null) {
            return [];
        }

        return array_map(fn (int $id): string => $payload['holderTexts'][$id], $ids);
    }

    /* ── S24 · the room-only holder ──────────────────────────────────── */

    public function test_room_only_holder_names_the_actual_class_without_shared_lecturer_or_cohort(): void
    {
        // B's class Wed 14:00–16:00 at B100 — the viewer shares NEITHER the
        // lecturer NOR the cohort, so the busy vectors carry nothing there:
        // the cell is blocked by the ROOM alone and must still be explained.
        $held = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 3, '14:00:00', '16:00:00');
        $this->createBlockSlots(6, 3, '14:00:00', 4, 'occupied', $held);

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5); // week 6 → JS index 5

        for ($idx = 12; $idx <= 15; $idx++) {
            $row = $rows['3:'.$idx] ?? null;
            $this->assertNotNull($row, "the $idx half-cell is in the payload");
            $this->assertSame(1, $row[2], 'still the mock status 1');
            $this->assertIsArray($row[3], "the room-only $idx cell carries its holder ids");

            $blocks = $this->resolveBlocks($payload, $row[3]);
            $this->assertCount(1, $blocks, 'one holder block');
            $block = $blocks[0];
            $this->assertStringContainsString('B100 · BMIT2002 (L) · 2:00 PM ~ 4:00 PM', $block, 'room · code (type) · the FULL 12-hour span');
            $this->assertStringContainsString("\nNORMAL", $block, 'a class holder is NORMAL');
            $this->assertStringContainsString('Lecturer B (6002)', $block, 'the permitted lecturer line');
            $this->assertStringNotContainsString('You — ', $block, 'another lecturer holds it, not the viewer');
            $this->assertSame('NORMAL', $this->stateWord($block));
        }

        // the room-only law: the viewer's busy arms have NO row there
        $ownSessionId = (string) ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->value('id');
        foreach (['', $ownSessionId] as $key) {
            $covering = collect($payload['busyByWeek'][$key][5] ?? [])
                ->first(fn (array $r): bool => $r[0] === 3 && $r[1] <= 12 && $r[2] > 12);
            $this->assertNull($covering, "the $key arm carries no blocking row at the held cells");
        }
    }

    /* ── S24 · full actual spans + the public states ─────────────────── */

    public function test_pending_and_approved_holders_show_their_full_actual_span_and_public_state(): void
    {
        $sourceB = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 1, '08:00:00', '09:00:00');

        // PENDING: a 2-hour proposal (4 half-cells) — every covered cell names
        // the request's FULL span, never its own anchor or half-cell.
        $pendingSlots = $this->createBlockSlots(6, 3, '14:00:00', 4, 'pending');
        $this->makeRequest($this->lecturerB, $sourceB, 8, $pendingSlots, 'pending', 4);

        // REPLACEMENT: an approved 1-hour proposal elsewhere in the same room.
        // (A distinct source occurrence — one active request per block.)
        $approvedSlots = $this->createBlockSlots(7, 3, '09:00:00', 2, 'occupied');
        $this->makeRequest($this->lecturerB, $sourceB, 9, $approvedSlots, 'approved');

        $payload = $this->payloadOf($this->lecturerA);

        $pendingRows = $this->rowsOf($payload, 5);
        $firstBlock = null;
        for ($idx = 12; $idx <= 15; $idx++) {
            $blocks = $this->resolveBlocks($payload, $pendingRows['3:'.$idx][3] ?? null);
            $this->assertCount(1, $blocks, "the pending $idx cell names its holder");
            $this->assertSame('PENDING', $this->stateWord($blocks[0]));
            $this->assertStringContainsString('2:00 PM ~ 4:00 PM', $blocks[0], 'the FULL actual span on every covered cell');
            $firstBlock ??= $blocks[0];
            $this->assertSame($firstBlock, $blocks[0], 'one identical full-span block across the run');
        }

        $approvedRows = $this->rowsOf($payload, 6);
        for ($idx = 2; $idx <= 3; $idx++) {
            $blocks = $this->resolveBlocks($payload, $approvedRows['3:'.$idx][3] ?? null);
            $this->assertCount(1, $blocks, "the approved $idx cell names its holder");
            $this->assertSame('REPLACEMENT', $this->stateWord($blocks[0]));
            $this->assertStringContainsString('9:00 AM ~ 10:00 AM', $blocks[0], 'the approved run’s full span');
        }
    }

    /* ── S24 · the live receipt beats a stale original id ────────────── */

    public function test_a_stale_original_id_loses_to_the_live_request_receipt(): void
    {
        // The pre-cleanup shape: class A cancelled in week 6, its original
        // cells STILL carrying its id — then re-booked by an active request.
        // The holder must be THAT request, never the resurrected class.
        $cancelled = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->firstOrFail();
        ClassException::create(['class_session_id' => $cancelled->id, 'week_number' => 6, 'reason' => 'medical_leave']);
        $staleSlots = $this->createBlockSlots(6, 1, '10:00:00', 4, 'pending', $cancelled);

        $sourceB = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 1, '08:00:00', '09:00:00');
        $this->makeRequest($this->lecturerB, $sourceB, 8, $staleSlots, 'pending', 4);

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5);

        for ($idx = 4; $idx <= 7; $idx++) {
            $blocks = $this->resolveBlocks($payload, $rows['1:'.$idx][3] ?? null);
            $this->assertCount(1, $blocks, "the stale-id $idx cell names its LIVE holder");
            $this->assertStringContainsString('BMIT2002', $blocks[0], 'the request’s own source class');
            $this->assertStringContainsString("\nPENDING", $blocks[0]);
            $this->assertStringNotContainsString('BMIT1001', $blocks[0], 'the cancelled original is never resurrected as the holder');
        }
    }

    /* ── S24 · the legal junction-less stored span ───────────────────── */

    public function test_a_junction_less_stored_span_names_the_full_actual_range(): void
    {
        $sourceB = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 1, '08:00:00', '09:00:00');

        // NO junction rows — the documented anchor + replacement_span fallback
        // is the only geometry this legacy-shaped request has.
        $slots = $this->createBlockSlots(6, 3, '14:00:00', 4, 'pending');
        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerB->id,
            'class_session_id' => $sourceB->id,
            'week_number' => 8,
            'replacement_time_slot_id' => $slots[0]->id,
            'replacement_span' => 4,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5);

        for ($idx = 12; $idx <= 15; $idx++) {
            $blocks = $this->resolveBlocks($payload, $rows['3:'.$idx][3] ?? null);
            $this->assertCount(1, $blocks, "the fallback-held $idx cell names its holder");
            $this->assertStringContainsString('2:00 PM ~ 4:00 PM', $blocks[0], 'the stored span, not the anchor half-cell');
            $this->assertSame('PENDING', $this->stateWord($blocks[0]));
        }
    }

    /* ── S25 · malformed/unknown claims stay neutral ─────────────────── */

    public function test_malformed_or_unknown_claims_stay_neutral(): void
    {
        $sourceB = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 1, '08:00:00', '09:00:00');

        // (a) a NON-CONTIGUOUS junction run — malformed, proves nothing — and
        //     a class whose geometry covers the same cells, which must NOT
        //     speak over the ambiguous evidence either.
        $gappy = $this->createBlockSlots(6, 3, '14:00:00', 1, 'pending');
        $gappy[] = $this->createBlockSlots(6, 3, '15:00:00', 1, 'pending')[0];
        $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 3, '14:00:00', '16:00:00');
        $this->makeRequest($this->lecturerB, $sourceB, 8, $gappy, 'pending');

        // (b) a pending claim whose receipt cell is occupied — a state
        //     mismatch proves nothing. (Distinct source occurrence — one
        //     active request per block.)
        $mismatched = $this->createBlockSlots(6, 4, '09:00:00', 1, 'occupied');
        $this->makeRequest($this->lecturerB, $sourceB, 9, $mismatched, 'pending');

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5);

        $this->assertNull($rows['3:12'][3] ?? null, 'the malformed run’s first cell stays neutral');
        $this->assertNull($rows['3:15'][3] ?? null, 'the malformed run’s gapped cell stays neutral — never the covering class');
        $this->assertNull($rows['4:2'][3] ?? null, 'the state-mismatched claim stays neutral');

        // (c) two distinct active claims on ONE cell (a junction receipt plus
        //     the junction-less fallback anchored on the same slot): refuse,
        //     never pick a side.
        $contested = $this->createBlockSlots(6, 5, '09:00:00', 1, 'pending');
        $this->makeRequest($this->lecturerB, $sourceB, 10, $contested, 'pending');
        // the SECOND claim must be a different occurrence too (the partial
        // unique allows one ACTIVE request per block) — a junction-less
        // fallback anchored on the SAME slot is the contest itself.
        $secondSource = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 1, '08:00:00', '09:00:00');
        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerB->id,
            'class_session_id' => $secondSource->id,
            'week_number' => 10,
            'replacement_time_slot_id' => $contested[0]->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5);
        $this->assertNull($rows['5:2'][3] ?? null, 'the contested cell stays neutral');
    }

    /* ── S25 · a refused fallback neutralizes its WHOLE landing ──────── */

    public function test_a_refused_fallback_claim_neutralizes_every_covered_row(): void
    {
        $sourceB = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 1, '08:00:00', '09:00:00');

        // A junction-less pending claim (anchor 09:00, stored span 4) whose
        // SECOND tail half-cell drifted `occupied` — a state mismatch, so the
        // claim refuses. Its still-pending siblings are IN the grid input:
        // an uncancelled covering class must never print NORMAL over them.
        $slots = $this->createBlockSlots(6, 5, '09:00:00', 4, 'pending');
        $slots[2]->update(['status' => 'occupied']);
        $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 5, '09:00:00', '11:00:00');

        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerB->id,
            'class_session_id' => $sourceB->id,
            'week_number' => 8,
            'replacement_time_slot_id' => $slots[0]->id,
            'replacement_span' => 4,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5);

        for ($idx = 2; $idx <= 5; $idx++) {
            $this->assertNotNull($rows['5:'.$idx] ?? null, "the refused claim's $idx row stays in the payload");
            $this->assertNull($rows['5:'.$idx][3], "the refused claim's $idx covered cell stays neutral — never the covering class");
        }
    }

    /* ── S24 · a drifted-`available` anchor is still discovered ──────── */

    public function test_a_junction_less_claim_with_a_drifted_available_anchor_stays_neutral(): void
    {
        $sourceB = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 1, '08:00:00', '09:00:00');

        // The grid input carries only occupied/pending rows, so this claim's
        // anchor row (drifted `available`) is INVISIBLE to the input — the
        // discovery must still find it by its coordinate tuple, validate it
        // through the shared strict core (it refuses: the anchor coordinate
        // itself is no longer claim-state), and its pending tails go neutral
        // — never the covering uncancelled class geometry.
        $slots = $this->createBlockSlots(6, 5, '09:00:00', 4, 'pending');
        $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 5, '09:00:00', '11:00:00');

        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerB->id,
            'class_session_id' => $sourceB->id,
            'week_number' => 8,
            'replacement_time_slot_id' => $slots[0]->id,
            'replacement_span' => 4,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $slots[0]->update(['status' => 'available']);

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5);

        $this->assertArrayNotHasKey('5:2', $rows, 'the drifted anchor row is no longer in the grid input');

        for ($idx = 3; $idx <= 5; $idx++) {
            $this->assertNotNull($rows['5:'.$idx] ?? null, "the $idx tail row stays in the payload");
            $this->assertNull($rows['5:'.$idx][3], "the $idx tail cell of the drifted-anchor claim stays neutral — never the covering class");
        }
    }

    /* ── S24 · an available duplicate anchor with a pending twin proves ─ */

    public function test_a_duplicate_available_anchor_with_a_pending_twin_still_proves(): void
    {
        $sourceB = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 1, '08:00:00', '09:00:00');

        // The seeded grid keeps duplicate rows on one coordinate: this
        // request's anchor is an `available` twin, the UNIQUE pending row at
        // the same coordinate is the claim's own. The shared validator is
        // coordinate-based, so the twin PROVES the claim — the full stored
        // span shows on every covered cell, beating the covering class.
        $slots = $this->createBlockSlots(6, 5, '09:00:00', 4, 'pending');
        $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 5, '09:00:00', '11:00:00');
        $anchorRow = TimeSlot::create([
            'semester_id' => $this->semester->id,
            'class_session_id' => null,
            'week_number' => 6,
            'day_of_week' => 5,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'venue_id' => $this->venue->id,
            'status' => 'available',
        ]);

        ReplacementRequest::create([
            'semester_id' => $this->semester->id,
            'proposer_id' => $this->lecturerB->id,
            'class_session_id' => $sourceB->id,
            'week_number' => 8,
            'replacement_time_slot_id' => $anchorRow->id,
            'replacement_span' => 4,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5);

        for ($idx = 2; $idx <= 5; $idx++) {
            $blocks = $this->resolveBlocks($payload, $rows['5:'.$idx][3] ?? null);
            $this->assertCount(1, $blocks, "the twin-proven $idx cell names its holder — never the covering class");
            $this->assertStringContainsString('B100 · BMIT2002 (L) · 9:00 AM ~ 11:00 AM', $blocks[0], 'the FULL stored span');
            $this->assertSame('PENDING', $this->stateWord($blocks[0]));
        }
    }

    /* ── S25 · the pre-cleanup cancelled original holds nothing ──────── */

    public function test_a_still_occupied_cancelled_original_is_neutral(): void
    {
        // Part A's cleanup has NOT run: the cancelled occurrence's original
        // cells remain occupied with their stale id and NO active claim.
        $cancelled = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->firstOrFail();
        ClassException::create(['class_session_id' => $cancelled->id, 'week_number' => 6, 'reason' => 'medical_leave']);
        $this->createBlockSlots(6, 1, '10:00:00', 4, 'occupied', $cancelled);

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5);

        for ($idx = 4; $idx <= 7; $idx++) {
            $row = $rows['1:'.$idx] ?? null;
            $this->assertNotNull($row, 'the cell stays in the payload');
            $this->assertSame(1, $row[2], 'the cell stays blocked');
            $this->assertNull($row[3], 'the pre-cleanup original is NEVER described as an active holder');
        }
    }

    /* ── S25 · released cells are unannotated ────────────────────────── */

    public function test_released_cells_are_unannotated(): void
    {
        // Part A DID release these: available, owner cleared — they are not
        // in the grid payload at all, and no cancellation note exists.
        $this->createBlockSlots(6, 1, '10:00:00', 4, 'available');

        $payload = $this->payloadOf($this->lecturerA);
        $rows = $this->rowsOf($payload, 5);

        for ($idx = 4; $idx <= 7; $idx++) {
            $this->assertArrayNotHasKey('1:'.$idx, $rows, 'a released cell is not in the grid payload');
        }

        $joined = implode("\n", $payload['holderTexts']);
        $this->assertStringNotContainsString('cancel', strtolower($joined), 'no cancellation note exists anywhere in the holder library');
    }

    /* ── S24 · one holder in several arms is one identity ────────────── */

    public function test_one_holder_in_several_arms_shares_one_library_identity(): void
    {
        // The viewer's OWN class holds B100 Mon 10:00–12:00: it reaches the
        // cell through the lecturer vector AND the room arm — one factual
        // holder, ONE library entry, one block in the merged tip.
        $own = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->firstOrFail();
        $this->createBlockSlots(6, 1, '10:00:00', 4, 'occupied', $own);

        $payload = $this->payloadOf($this->lecturerA);

        $busyRow = collect($payload['busyByWeek'][''][5])
            ->first(fn (array $r): bool => $r[0] === 1 && $r[1] === 4 && $r[2] === 8);
        $this->assertNotNull($busyRow, 'the own class rides the lecturer vector');
        $this->assertNotEmpty($busyRow[7], 'the busy row carries its holder identity id');

        $rows = $this->rowsOf($payload, 5);
        $gridIds = $rows['1:4'][3];
        $this->assertIsArray($gridIds, 'the room arm carries the holder too');

        $this->assertSame(
            $busyRow[7][0],
            $gridIds[0],
            'the same factual holder shares ONE library identity across the room and own arms',
        );
    }

    /* ── S24 · different holders survive identical display strings ───── */

    public function test_different_holders_survive_identical_display_strings(): void
    {
        // Two DISTINCT sessions with identical facts (a double-booked seed
        // shape): both are real holders and must both survive — the identity
        // law keeps them apart even though their display strings match.
        $first = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 3, '14:00:00', '16:00:00');
        $second = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 3, '14:00:00', '16:00:00');
        $this->assertNotSame($first->id, $second->id);
        $this->createBlockSlots(6, 3, '14:00:00', 4, 'occupied', $first);

        $payload = $this->payloadOf($this->lecturerA);

        $rows = $this->rowsOf($payload, 5);
        $ids = $rows['3:12'][3];
        $this->assertIsArray($ids);
        $this->assertCount(2, $ids, 'both distinct holders ride the cell');
        $this->assertNotSame($ids[0], $ids[1], 'two distinct library identities');

        $texts = array_map(fn (int $id): string => $payload['holderTexts'][$id], $ids);
        $this->assertCount(1, array_unique($texts), 'the display strings are identical — the identities are not');
    }

    /* ── S24 · the permitted vocabulary only ─────────────────────────── */

    public function test_holder_blocks_carry_no_private_text(): void
    {
        $sourceB = $this->createSession($this->lecturerB, $this->moduleB, [$this->cohortB], 1, '08:00:00', '09:00:00');
        $slots = $this->createBlockSlots(6, 3, '14:00:00', 4, 'pending');

        $request = $this->makeRequest($this->lecturerB, $sourceB, 8, $slots, 'pending', 4);
        $request->update(['remarks' => 'PRIVATE REMARKS MUST NOT LEAK', 'submitted_at' => '2026-09-14 09:00:00']);

        $cancelled = ClassSession::query()->where('lecturer_id', $this->lecturerA->id)->firstOrFail();
        ClassException::create(['class_session_id' => $cancelled->id, 'week_number' => 7, 'reason' => 'medical_leave']);

        $payload = $this->payloadOf($this->lecturerA);
        $joined = implode("\n", $payload['holderTexts']);

        $this->assertStringNotContainsString('PRIVATE REMARKS', $joined, 'no remark ever rides a holder block');
        $this->assertStringNotContainsString('medical_leave', $joined, 'no private cancellation reason');
        $this->assertStringNotContainsString('2026-09-14', $joined, 'no submission timestamp');
        $this->assertStringNotContainsString('rejected', strtolower($joined), 'no request-decision metadata');

        // the one shared state vocabulary, exactly
        foreach ($payload['holderTexts'] as $block) {
            $this->assertContains($this->stateWord($block), ['NORMAL', 'PENDING', 'REPLACEMENT'], 'the final public state word is one of the three');
        }
    }

    /** The block's FINAL line — the public state word (D8). */
    private function stateWord(string $block): string
    {
        $lines = explode("\n", trim($block));

        return trim((string) end($lines));
    }
}
