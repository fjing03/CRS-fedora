<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VenuesSeeder extends Seeder
{
    /**
     * Block B venue groups per CodingMAIN §3 rules.
     *
     * @param  array<int, string>  $codes
     */
    private function insertGroup(array $codes, string $label, string $type, string $allowed, int $capacity): void
    {
        foreach ($codes as $code) {
            DB::table('venues')->insert([
                'room_code' => $code,
                'room_name' => "{$label} {$code}",
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

        // Computer Labs (capacity 28, allowed P only)
        $this->insertGroup(['B005', 'B009', 'B010', 'B011'], 'Computer Lab', 'lab', 'P', 28);

        // Cisco Lab (capacity 32, allowed P only)
        $this->insertGroup(['B006'], 'Cisco Lab', 'cisco_lab', 'P', 32);
    }
}
