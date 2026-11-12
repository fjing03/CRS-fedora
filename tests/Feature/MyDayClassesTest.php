<?php

namespace Tests\Feature;

use App\Livewire\MyTimetable;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SDD cancel-whole-day, task T3 — the two renderless methods the whole-day block
 * reads (`myDayClasses`) and writes through (`cancelClassDay`).
 *
 * The laws this file pins (design F5, spec R4):
 *   · the set is the CALLER's own classes only — a colleague's class of the same
 *     day is absent, so a tampered page cannot widen it;
 *   · an occurrence that already holds an exception is absent (cancelled, or moved
 *     by an approved replacement);
 *   · every row carries what the block prints: time, code, type, room, cohorts,
 *     students, and the request state;
 *   · the day confirm re-resolves the set on the server and refuses an empty day.
 */
final class MyDayClassesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private int $week = 12;

    private int $day;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->owner = User::query()->whereIn('id', ClassSession::query()->select('lecturer_id'))->firstOrFail();
        $this->other = User::query()
            ->where('role', 'lecturer')
            ->where('id', '!=', $this->owner->id)
            ->firstOrFail();

        $session = ClassSession::query()->where('lecturer_id', $this->owner->id)->firstOrFail();
        $this->day = (int) $session->day_of_week;
    }

    /** The renderless read, called the way the client calls it. */
    private function myDay(): array
    {
        $this->actingAs($this->owner);

        return (new MyTimetable)->myDayClasses($this->week, $this->day);
    }

    /** The renderless day confirm. */
    private function cancelDay(string $reason, array $requestIds = [], array $sessionIds = []): array
    {
        $this->actingAs($this->owner);

        return (new MyTimetable)->cancelClassDay($this->week, $this->day, $reason, null, $requestIds, $sessionIds);
    }

    public function test_the_day_lists_only_my_own_classes_with_what_the_block_prints(): void
    {
        $result = $this->myDay();

        $this->assertTrue((bool) ($result['ok'] ?? false), 'the read answers ok');
        $this->assertNotEmpty($result['classes'] ?? [], 'the seeded lecturer teaches on this day');

        foreach ($result['classes'] as $row) {
            $session = ClassSession::findOrFail((int) $row['sessionId']);
            $this->assertSame((int) $this->owner->id, (int) $session->lecturer_id, 'never a colleague\'s class');
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', (string) $row['start'], 'HH:MM for the block');
            $this->assertNotEmpty((string) $row['code']);
            $this->assertContains($row['request'], ['none', 'pending', 'approved']);
            $this->assertIsArray($row['cohorts']);
        }
    }

    public function test_an_occurrence_that_already_has_an_exception_is_not_in_the_set(): void
    {
        $before = $this->myDay()['classes'] ?? [];
        $this->assertNotEmpty($before);

        $victim = (int) $before[0]['sessionId'];
        ClassException::create([
            'class_session_id' => $victim,
            'week_number' => $this->week,
            'reason' => 'medical_leave',
        ]);

        $after = $this->myDay()['classes'] ?? [];
        $ids = array_map(fn (array $r): int => (int) $r['sessionId'], $after);

        $this->assertNotContains($victim, $ids, 'a cancelled occurrence is already off the timetable');
    }

    public function test_a_pending_request_on_the_day_is_reported(): void
    {
        $classes = $this->myDay()['classes'] ?? [];
        $this->assertNotEmpty($classes);

        $sessionId = (int) $classes[0]['sessionId'];
        $session = ClassSession::findOrFail($sessionId);

        $slot = TimeSlot::create([
            'semester_id' => (int) $session->semester_id,
            'class_session_id' => null,
            'week_number' => $this->week + 1,
            'day_of_week' => 4,
            'start_time' => '15:00:00',
            'end_time' => '15:30:00',   // the DB only allows the 30-minute cells
            'venue_id' => $session->venue_id,
            'status' => 'pending',
            'version' => 2,
        ]);

        ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => (int) $session->lecturer_id,
            'class_session_id' => $sessionId,
            'week_number' => $this->week,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $rows = $this->myDay()['classes'] ?? [];
        $row = collect($rows)->firstWhere('sessionId', $sessionId);

        $this->assertNotNull($row);
        $this->assertSame('pending', $row['request'], 'the amber pill has something to say');
    }

    /**
     * 2026-11-11 (user bug report): the grid paints a class's PENDING make-up block
     * in the week the make-up falls in. That block is NOT a class timetabled that
     * day — its occurrence is another week — so the read reports it separately and
     * never lets a tick cancel it.
     */
    public function test_a_make_up_block_on_the_day_is_reported_but_not_tickable(): void
    {
        $classes = $this->myDay()['classes'] ?? [];
        $this->assertNotEmpty($classes);

        $session = ClassSession::findOrFail((int) $classes[0]['sessionId']);
        $otherWeek = $this->week === 14 ? 13 : $this->week + 1;   // any OTHER valid week

        $slot = TimeSlot::create([
            'semester_id' => (int) $session->semester_id,
            'class_session_id' => null,
            'week_number' => $this->week,          // the make-up FALLS on this day
            'day_of_week' => $this->day,
            'start_time' => '08:00:00',
            'end_time' => '08:30:00',
            'venue_id' => $session->venue_id,
            'status' => 'pending',
            'version' => 2,
        ]);

        $request = ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => (int) $session->lecturer_id,
            'class_session_id' => (int) $session->id,
            'week_number' => $otherWeek,           // …but its class lives in another week
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        ReplacementRequestSlot::create([
            'replacement_request_id' => $request->id,
            'time_slot_id' => $slot->id,
        ]);

        $day = $this->myDay();
        $ids = array_map(fn (array $r): int => (int) $r['sessionId'], $day['classes']);

        $this->assertCount(1, $day['makeUps'], 'the make-up block is reported');
        $this->assertSame($otherWeek, $day['makeUps'][0]['week'], 'and names its own week');
        $this->assertSame('08:00', $day['makeUps'][0]['start']);
        $this->assertSame('08:30', $day['makeUps'][0]['end'], 'the real run of the block');
        $this->assertSame('pending', $day['makeUps'][0]['request']);

        /* nothing in `makeUps` is a class row — the tick list cannot reach it */
        $this->assertContains((int) $session->id, $ids, 'the class itself is still a normal row');
        $this->assertNotContains((int) $session->id, array_map(fn (array $m): int => (int) ($m['week'] ?? 0), $day['makeUps']),
            'a make-up is never a class row');
    }

    public function test_the_day_confirm_cancels_the_whole_set_and_reports_the_withdrawals(): void
    {
        $day = $this->myDay()['classes'];
        $all = array_map(fn (array $r): int => (int) $r['sessionId'], $day);
        $expected = count($all);
        $result = $this->cancelDay('medical_leave', [], $all);

        $this->assertTrue($result['ok'], 'a whole day in one call');
        $this->assertSame($expected, count($result['cancelled']), 'exactly the day\'s set came back');
        $this->assertSame(0, $result['requestsClosed']);

        foreach ($result['cancelled'] as $row) {
            $this->assertSame(1, ClassException::query()
                ->where('class_session_id', (int) $row['session_id'])
                ->where('week_number', $this->week)->count());
        }
    }

    /**
     * 2026-11-11 (user): a ticked MAKE-UP row withdraws its request only — the class
     * it was arranged for keeps running. This is the one path where "cancel" does
     * NOT cancel a class, so it gets its own receipt.
     */
    public function test_a_ticked_make_up_row_withdraws_the_request_and_leaves_the_class_alone(): void
    {
        $classes = $this->myDay()['classes'] ?? [];
        $this->assertNotEmpty($classes);

        $session = ClassSession::findOrFail((int) $classes[0]['sessionId']);
        $otherWeek = $this->week === 14 ? 13 : $this->week + 1;

        $slot = TimeSlot::create([
            'semester_id' => (int) $session->semester_id,
            'class_session_id' => null,
            'week_number' => $this->week,
            'day_of_week' => $this->day,
            'start_time' => '08:00:00',
            'end_time' => '08:30:00',
            'venue_id' => $session->venue_id,
            'status' => 'pending',
            'version' => 2,
        ]);

        $request = ReplacementRequest::create([
            'semester_id' => (int) $session->semester_id,
            'proposer_id' => (int) $session->lecturer_id,
            'class_session_id' => (int) $session->id,
            'week_number' => $otherWeek,
            'replacement_time_slot_id' => $slot->id,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        ReplacementRequestSlot::create([
            'replacement_request_id' => $request->id,
            'time_slot_id' => $slot->id,
        ]);

        $result = $this->cancelDay('medical_leave', [(int) $request->id]);

        $this->assertTrue($result['ok']);
        $this->assertSame(1, $result['requestsWithdrawn'], 'the make-up request was withdrawn');

        $request->refresh();
        $this->assertSame('cancelled', (string) $request->status, 'the request is closed');
        $this->assertSame('available', (string) TimeSlot::find($slot->id)->status, 'its slot came back');

        /* the class the make-up was arranged FOR is untouched: no exception on its
           own week. (The class the modal was opened for is always cancelled — that
           is what the red button is for.) */
        $this->assertSame(0, ClassException::query()
            ->where('week_number', $otherWeek)
            ->count(), 'the other week\'s class keeps running');

        $this->assertCount(1, $result['cancelled'], 'only the opened class was cancelled');
        $this->assertNotSame($otherWeek, (int) $result['cancelled'][0]['week']);
    }

    /**
     * 2026-11-11 (BUG, user report): unticking a row still cancelled it, because the
     * day path took the WHOLE day from the read. The page now sends only what is
     * ticked (plus the opened class), and anything else survives.
     */
    public function test_an_unticked_class_is_not_cancelled(): void
    {
        $day = $this->myDay()['classes'];
        $cancelSession = ClassSession::findOrFail((int) $day[0]['sessionId']);

        /* a second class of MINE on the same day, so one can be left unticked */
        $keptSession = ClassSession::create([
            'semester_id' => $cancelSession->semester_id,
            'module_id' => $cancelSession->module_id,
            'lecturer_id' => $this->owner->id,
            'day_of_week' => $this->day,
            'start_time' => '17:00:00',
            'end_time' => '18:00:00',
            'venue_id' => $cancelSession->venue_id,
            'session_type' => $cancelSession->session_type,
        ]);
        $keptSession->cohorts()->attach($cancelSession->cohorts()->pluck('cohorts.id')->all());

        $kept = (int) $keptSession->id;
        $cancel = [(int) $day[0]['sessionId']];

        $result = $this->cancelDay('medical_leave', [], $cancel);

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $result['cancelled'], 'exactly the ticked session');
        $this->assertSame($cancel[0], (int) $result['cancelled'][0]['session_id']);

        $this->assertSame(1, ClassException::query()->where('class_session_id', $cancel[0])->where('week_number', $this->week)->count());
        $this->assertSame(0, ClassException::query()->where('class_session_id', $kept)->where('week_number', $this->week)->count(),
            'the UNTICKED class must survive');
    }

    public function test_the_day_confirm_refuses_when_nothing_is_left_to_cancel(): void
    {
        ClassException::query()->where('week_number', $this->week)->delete();

        // every class of mine that day is already cancelled
        $classes = $this->myDay()['classes'] ?? [];
        foreach ($classes as $row) {
            ClassException::create([
                'class_session_id' => (int) $row['sessionId'],
                'week_number' => $this->week,
                'reason' => 'medical_leave',
            ]);
        }

        $result = $this->cancelDay('medical_leave');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('nothing to cancel', (string) $result['hint']);
    }
}
