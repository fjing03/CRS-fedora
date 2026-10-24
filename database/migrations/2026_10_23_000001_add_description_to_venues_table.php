<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SDD venue-description-doc-b006-rule: every room gains a plain DESCRIPTION
 * (the sv's directive, 2026-10-23) — a nullable text column + first-draft
 * seed texts (the user corrects the wording after). Reversible: the DOWN
 * drops the column (and the texts with it).
 *
 * Safety: the viva demo DB was snapshotted BEFORE this run
 * (backups/venues-description-baseline-2026-10-23.dump). The plain migrate
 * never re-seeds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });

        $drafts = [
            // Tutorial Rooms (capacity 35, L/T)
            'B002' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B014' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B015' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B016' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B017' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B018' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B100' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B101' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B102' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B103' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B104' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B105' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B106' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B107' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B108' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            'B109' => 'Tutorial Room. For PRACTICAL and TUTORIAL.',
            // Lecture Halls (capacity 80, L only)
            'B110' => 'Lecture Hall. For LECTURE.',
            'B111' => 'Lecture Hall. For LECTURE.',
            // Labs (capacity 28, P only)
            'B005' => 'Lab. For PRACTICAL Class. DEGREE students only.',
            'B009' => 'Computer Lab. For PRACTICAL.',
            'B010' => 'Computer Lab. For PRACTICAL.',
            'B011' => 'Computer Lab. For PRACTICAL.',
            // Cisco Specialized Lab (capacity 32, P only; FOCS-only gate, sv rule 2026-10-23)
            'B006' => 'Cisco Lab. Networking & IoT equipment room. For FOCS cohorts only.',
        ];

        foreach ($drafts as $roomCode => $text) {
            DB::table('venues')->where('room_code', $roomCode)->update(['description' => $text]);
        }
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
