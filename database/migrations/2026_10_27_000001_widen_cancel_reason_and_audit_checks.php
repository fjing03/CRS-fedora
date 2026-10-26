<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * class-cancel-db-persist — widen the two cancel-related CHECK constraints
 * after the reason vocabulary grew (the user's 4 new leave types + `other`
 * on the radio list) and the undo flow gained its audit action.
 *
 * Replaces the reasons CHECK (5 → 10 values) and the audit action CHECK
 * (7 → 8 values, + class_cancel_undone). Mirrors the demo-DB safety sheet
 * pasted in docs/demo-db-data-changes.md — that sheet is for the LIVE demo
 * DB, which is never migrated; fresh DBs get this via migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE class_exceptions DROP CONSTRAINT IF EXISTS class_exceptions_reason_check');
        DB::statement("ALTER TABLE class_exceptions ADD CONSTRAINT class_exceptions_reason_check
            CHECK (reason IN ('public_holiday', 'annual_leave', 'medical_leave', 'official_event', 'emergency_leave',
                              'hospitalisation_leave', 'maternity_leave', 'compassionate_leave', 'paternity_leave', 'other'))");

        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check
            CHECK (action IN ('submitted', 'approved', 'rejected', 'cancelled', 'completed', 'occ_conflict', 'class_cancelled', 'class_cancel_undone'))");
    }

    /**
     * Reverse the migrations — HAZARD: narrowing fails once any row uses a
     * widened value (hospitalisation_leave, `other`, class_cancel_undone…)
     * — the 2026_08_24_000001 precedent, design D5. A real-usage database
     * cannot narrow without data loss, so down() keeps the wide CHECKs and
     * only declines (fresh DBs reach the 10-value state via the sequence).
     */
    public function down(): void
    {
        logger()->warning('migration down() declined: narrowing the widened CHECK constraints would fail against rows using the new values (class-cancel-db-persist design D5)');
    }
};
