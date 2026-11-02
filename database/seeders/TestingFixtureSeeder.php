<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * TestingFixtureSeeder — the "prop kit" for the Chapter 5 browser drills.
 *
 * It rebuilds the fixture rows the Playwright receipts assert on, WITHOUT
 * touching any demo database. It refuses to run unless the connection points at
 * class_replacement_testing, and it is idempotent (it clears its own fixture
 * rows first).
 *
 * Run it only like this:
 *   DB_DATABASE=class_replacement_testing php artisan db:seed --class=TestingFixtureSeeder
 *
 * Fixtures, each traced to the spec that needs it:
 *  - class_exceptions: session 24, week 7, medical_leave  (venue-status-parity)
 *  - request 24 rejected / 25 cancelled / 26 pending       (status tooltips, venue blocks)
 *  - request 26 junction: venue 21 week 7 Fri 14:00 + 14:30, slots pending
 *  - request 10 approved: AMCS2093 week 5 Thu 12:00 + 12:30 in B002, slots occupied
 *    (request-approval-verify, the row the October cleanout lost)
 */
class TestingFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $database = DB::connection()->getDatabaseName();
        if ($database !== 'class_replacement_testing') {
            fwrite(STDERR, "REFUSED: TestingFixtureSeeder targets only class_replacement_testing, got [{$database}].\n");
            exit(1);
        }

        DB::transaction(function (): void {
            // idempotent: clear only this seeder's rows
            DB::table('replacement_request_slots')->delete();
            DB::table('replacement_requests')->delete();
            DB::table('class_exceptions')->delete();

            $now = now();

            // ── cancelled class fixture (venue-status-parity) ──
            DB::table('class_exceptions')->insert([
                'class_session_id' => 24,
                'week_number' => 7,
                'reason' => 'medical_leave',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // ── slot lookup helper ──
            $slotId = function (int $venueId, int $week, int $day, string $start): int {
                $id = DB::table('time_slots')
                    ->where('venue_id', $venueId)
                    ->where('week_number', $week)
                    ->where('day_of_week', $day)
                    ->where('start_time', $start)
                    ->value('id');
                if ($id === null) {
                    throw new \RuntimeException("fixture slot missing: venue {$venueId} week {$week} day {$day} {$start}");
                }

                return (int) $id;
            };

            // ── request 26: the pending AMSE2003 proposal held on B010 (venue 21) ──
            $held = [$slotId(21, 7, 4, '14:00:00'), $slotId(21, 7, 4, '14:30:00')];
            DB::table('replacement_requests')->insert([
                'id' => 26,
                'semester_id' => DB::table('class_sessions')->where('id', 40)->value('semester_id'),
                'proposer_id' => 1,
                'class_session_id' => 40,
                'week_number' => 14,
                'replacement_time_slot_id' => $held[0],
                'approver_id' => null,
                'status' => 'pending',
                'rejection_reason' => null,
                'remarks' => null,
                'submitted_at' => '2026-11-02 11:00:12',
                'decided_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($held as $slot) {
                DB::table('replacement_request_slots')->insert([
                    'replacement_request_id' => 26,
                    'time_slot_id' => $slot,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('time_slots')->where('id', $slot)->update(['status' => 'pending', 'updated_at' => $now]);
            }

            // ── request 25: cancelled, request 24: rejected (status tooltips) ──
            DB::table('replacement_requests')->insert([
                [
                    'id' => 24,
                    'semester_id' => DB::table('class_sessions')->where('id', 40)->value('semester_id'),
                    'proposer_id' => 1,
                    'class_session_id' => 40,
                    'week_number' => 14,
                    'replacement_time_slot_id' => $slotId(21, 8, 4, '14:00:00'),
                    'approver_id' => 1,
                    'status' => 'rejected',
                    'rejection_reason' => 'Venue unavailable',
                    'remarks' => null,
                    'submitted_at' => '2026-11-02 06:49:47',
                    'decided_at' => '2026-11-02 07:04:42',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'id' => 25,
                    'semester_id' => DB::table('class_sessions')->where('id', 38)->value('semester_id'),
                    'proposer_id' => 1,
                    'class_session_id' => 38,
                    'week_number' => 14,
                    'replacement_time_slot_id' => $slotId(21, 9, 4, '14:00:00'),
                    'approver_id' => null,
                    'status' => 'cancelled',
                    'rejection_reason' => null,
                    'remarks' => null,
                    'submitted_at' => '2026-11-02 08:29:00',
                    'decided_at' => '2026-11-02 08:29:29',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);

            // ── request 10: the approved AMCS2093 replacement the cleanout lost ──
            // week 5, Thursday 12:00 and 12:30, B002 (venue 1), span 2, occupied
            $booked = [$slotId(1, 5, 4, '12:00:00'), $slotId(1, 5, 4, '12:30:00')];
            DB::table('replacement_requests')->insert([
                'id' => 10,
                'semester_id' => DB::table('class_sessions')->where('id', 97)->value('semester_id'),
                'proposer_id' => 11, // staff 5770, the my-timetable identity
                'class_session_id' => 97,
                'week_number' => 5,
                'replacement_time_slot_id' => $booked[0],
                'approver_id' => 1,
                'status' => 'approved',
                'rejection_reason' => null,
                'remarks' => null,
                'submitted_at' => '2026-10-20 10:00:00',
                'decided_at' => '2026-10-20 12:00:00',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ($booked as $slot) {
                DB::table('replacement_request_slots')->insert([
                    'replacement_request_id' => 10,
                    'time_slot_id' => $slot,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('time_slots')->where('id', $slot)->update(['status' => 'occupied', 'updated_at' => $now]);
            }
        });

        $this->command->info('TestingFixtureSeeder: requests 10/24/25/26, exception on session 24, slots 26=pending and 10=occupied.');
    }
}
