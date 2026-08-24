<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VenuesSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            // Tutorial Rooms (capacity 35, allowed L/T)
            ['B002', 'tutorial', 'L,T', 35],
            ['B014', 'tutorial', 'L,T', 35],
            ['B015', 'tutorial', 'L,T', 35],
            ['B016', 'tutorial', 'L,T', 35],
            ['B017', 'tutorial', 'L,T', 35],
            ['B018', 'tutorial', 'L,T', 35],
            ['B100', 'tutorial', 'L,T', 35],
            ['B101', 'tutorial', 'L,T', 35],
            ['B102', 'tutorial', 'L,T', 35],
            ['B103', 'tutorial', 'L,T', 35],
            ['B104', 'tutorial', 'L,T', 35],
            ['B105', 'tutorial', 'L,T', 35],
            ['B106', 'tutorial', 'L,T', 35],
            ['B107', 'tutorial', 'L,T', 35],
            ['B108', 'tutorial', 'L,T', 35],
            ['B109', 'tutorial', 'L,T', 35],
            // Lecture Halls (capacity 80, allowed L only)
            ['B110', 'lecture_hall', 'L', 80],
            ['B111', 'lecture_hall', 'L', 80],
            // Computer Labs (capacity 28, allowed P only)
            ['B005', 'lab', 'P', 28],
            ['B009', 'lab', 'P', 28],
            ['B010', 'lab', 'P', 28],
            ['B011', 'lab', 'P', 28],
            // Cisco Lab (capacity 32, allowed P only)
            ['B006', 'cisco_lab', 'P', 32],
        ];

        foreach ($rooms as [$code, $type, $allowed, $capacity]) {
            $typeLabel = match ($type) {
                'tutorial' => 'Tutorial Room',
                'lecture_hall' => 'Lecture Hall',
                'lab' => 'Computer Lab',
                'cisco_lab' => 'Cisco Lab',
            };

            DB::table('venues')->insert([
                'room_code' => $code,
                'room_name' => "$typeLabel $code",
                'room_type' => $type,
                'allowed_session_types' => $allowed,
                'capacity' => $capacity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
