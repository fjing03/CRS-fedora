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
            'B002' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B014' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B015' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B016' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B017' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B018' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B100' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B101' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B102' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B103' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B104' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B105' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B106' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B107' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B108' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            'B109' => 'Tutorial Room. Seats 35. For lectures and tutorials.',
            // Lecture Halls (capacity 80, L only)
            'B110' => 'Lecture Hall. Large multi-cohort assemblies; lectures only.',
            'B111' => 'Lecture Hall. Large multi-cohort assemblies; lectures only.',
            // Labs (capacity 28, P only)
            'B005' => 'Hardware Lab. For practicals. Degree students only; diploma cohorts not permitted.',
            'B009' => 'Computer Lab. For practicals.',
            'B010' => 'Computer Lab. For practicals.',
            'B011' => 'Computer Lab. For practicals.',
            // Cisco Specialized Lab (capacity 32, P only; FOCS-only gate, sv rule 2026-10-23)
            'B006' => 'Cisco Lab. Networking equipment room. FOCS only: DFT, DSF, RSD permitted; RAF, RBU not permitted.',
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
