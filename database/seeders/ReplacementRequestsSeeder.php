<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ReplacementRequestsSeeder extends Seeder
{
    public function run(): void
    {
        $surayainiId = DB::table('users')
            ->where('name', 'LIKE', '%Surayaini%')
            ->value('id');

        $rahmatId = DB::table('users')
            ->where('name', 'LIKE', '%Rahmat%')
            ->value('id');

        $pendingSlot = DB::table('time_slots')
            ->where('semester_id', 1)
            ->where('week_number', 11)
            ->where('day_of_week', 0)
            ->where('start_time', '10:00:00')
            ->where('venue_id', 14)
            ->value('id');

        $approvedSlot = DB::table('time_slots')
            ->where('semester_id', 1)
            ->where('week_number', 11)
            ->where('day_of_week', 0)
            ->where('start_time', '14:00:00')
            ->where('venue_id', 17)
            ->value('id');

        $rejectedSlot = DB::table('time_slots')
            ->where('semester_id', 1)
            ->where('week_number', 11)
            ->where('day_of_week', 1)
            ->where('start_time', '08:00:00')
            ->where('venue_id', 18)
            ->value('id');

        if (! $pendingSlot && ! $approvedSlot && ! $rejectedSlot) {
            return;
        }

        $requests = [];

        if ($pendingSlot) {
            $requests[] = [
                'semester_id' => 1,
                'proposer_id' => $surayainiId,
                'class_session_id' => 9,
                'week_number' => 11,
                'replacement_time_slot_id' => $pendingSlot,
                'approver_id' => null,
                'status' => 'pending',
                'rejection_reason' => null,
                'remarks' => 'Medical appointment',
                'submitted_at' => '2026-07-20 09:00:00',
                'decided_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($approvedSlot) {
            $requests[] = [
                'semester_id' => 1,
                'proposer_id' => $surayainiId,
                'class_session_id' => 12,
                'week_number' => 11,
                'replacement_time_slot_id' => $approvedSlot,
                'approver_id' => $rahmatId,
                'status' => 'approved',
                'rejection_reason' => null,
                'remarks' => 'Official university event',
                'submitted_at' => '2026-07-18 14:30:00',
                'decided_at' => '2026-07-19 10:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rejectedSlot) {
            $requests[] = [
                'semester_id' => 1,
                'proposer_id' => $surayainiId,
                'class_session_id' => 22,
                'week_number' => 11,
                'replacement_time_slot_id' => $rejectedSlot,
                'approver_id' => $rahmatId,
                'status' => 'rejected',
                'rejection_reason' => 'Insufficient notice period; replacement lecturer unavailable',
                'remarks' => null,
                'submitted_at' => '2026-07-21 11:00:00',
                'decided_at' => '2026-07-22 09:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($requests as $request) {
            DB::table('replacement_requests')->insert($request);
        }

        // Conflict fixture — class_exceptions for lecturer 5425 session 9 (week 5)
        DB::table('class_exceptions')->insert([
            'class_session_id' => 9,
            'week_number' => 5,
            'reason' => 'medical_leave',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
