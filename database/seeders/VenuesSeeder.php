<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VenuesSeeder extends Seeder
{
    /**
     * Block B venue groups per CodingMAIN §3 rules.
     *
     * `$names` carries a room's TRUE short name (user 2026-10-27) when the
     * real world names it specifically — e.g. the computer labs are numbered
     * independently of their codes (B009 = Lab 1 … B005 = Lab 5); rooms
     * without an entry keep the shared "label code" name.
     *
     * @param  array<int, string>  $codes
     * @param  array<string, string>  $names
     */
    private function insertGroup(array $codes, string $label, string $type, string $allowed, int $capacity, array $names = []): void
    {
        foreach ($codes as $code) {
            DB::table('venues')->insert([
                'room_code' => $code,
                'room_name' => $names[$code] ?? "{$label} {$code}",
                'room_type' => $type,
                'allowed_session_types' => $allowed,
                'capacity' => $capacity,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function run(): void
    {
        // Tutorial Rooms (capacity 35, allowed L/T)
        $this->insertGroup(
            ['B002', 'B014', 'B015', 'B016', 'B017', 'B018', 'B100', 'B101', 'B102', 'B103', 'B104', 'B105', 'B106', 'B107', 'B108', 'B109'],
            'Tutorial Room', 'tutorial', 'L,T', 35,
        );

        // Lecture Halls (capacity 80, allowed L only)
        $this->insertGroup(['B110', 'B111'], 'Lecture Hall', 'lecture_hall', 'L', 80);

        // Computer Labs (capacity 28, allowed P only) — real room names
        // (user 2026-10-27: Computer Lab 1..5 keyed to B009/B010/B011/B005)
        $this->insertGroup(
            ['B005', 'B009', 'B010', 'B011'], 'Computer Lab', 'lab', 'P', 28,
            ['B005' => 'Computer Lab 5', 'B009' => 'Computer Lab 1', 'B010' => 'Computer Lab 2', 'B011' => 'Computer Lab 3'],
        );

        // Cisco Lab (capacity 32, allowed P only) — full title carries the lab number
        $this->insertGroup(
            ['B006'], 'Cisco Lab', 'cisco_lab', 'P', 32,
            ['B006' => 'Computer Lab 4 - CISCO Lab'],
        );
    }
}
