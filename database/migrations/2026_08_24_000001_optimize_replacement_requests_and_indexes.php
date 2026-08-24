<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // (a) FK swap: cascade -> restrict on replacement_requests
        Schema::table('replacement_requests', function (Blueprint $table): void {
            $table->dropForeign(['class_session_id']);
            $table->dropForeign(['replacement_time_slot_id']);

            $table->foreign('class_session_id')
                ->references('id')->on('class_sessions')->restrictOnDelete();
            $table->foreign('replacement_time_slot_id')
                ->references('id')->on('time_slots')->restrictOnDelete();
        });

        // (b)+(c)+(e) plain hot-path indexes
        DB::statement('CREATE INDEX idx_replacement_requests_time_slot ON replacement_requests (replacement_time_slot_id)');
        DB::statement('CREATE INDEX idx_replacement_requests_proposer_submitted ON replacement_requests (proposer_id, submitted_at)');
        DB::statement('CREATE INDEX idx_audit_logs_time_slot ON audit_logs (time_slot_id)');

        // (d) partial unique enforcing D1 — one active request per block occurrence
        DB::statement("
            CREATE UNIQUE INDEX uq_replacement_requests_active_block
                ON replacement_requests (class_session_id, week_number)
                WHERE status IN ('pending', 'approved')
        ");

        // (f) denormalized cohort headcount + named CHECK
        Schema::table('cohorts', function (Blueprint $table): void {
            $table->smallInteger('student_count')->nullable();
        });

        DB::statement('ALTER TABLE cohorts ADD CONSTRAINT cohorts_student_count_check CHECK (student_count > 0)');

        // (g) convergence backfill from live student population (no-op on fresh-seed path)
        DB::statement(
            'UPDATE cohorts c SET student_count = sub.cnt
             FROM (SELECT cohort_id, COUNT(*) AS cnt FROM students GROUP BY cohort_id) sub
             WHERE c.id = sub.cohort_id AND c.student_count IS NULL'
        );

        // (h) widen audit action CHECK to seven values
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check
            CHECK (action IN ('submitted', 'approved', 'rejected', 'cancelled', 'completed', 'occ_conflict', 'class_cancelled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // (h) reverted — HAZARD: fails if any 'class_cancelled' rows exist (see design §2)
        DB::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS audit_logs_action_check');
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check
            CHECK (action IN ('submitted', 'approved', 'rejected', 'cancelled', 'completed', 'occ_conflict'))");

        // (g)+(f) reverted
        DB::statement('ALTER TABLE cohorts DROP CONSTRAINT IF EXISTS cohorts_student_count_check');
        Schema::table('cohorts', fn (Blueprint $table) => $table->dropColumn('student_count'));

        // (d) reverted
        DB::statement('DROP INDEX IF EXISTS uq_replacement_requests_active_block');

        // (e),(c),(b) reverted
        DB::statement('DROP INDEX IF EXISTS idx_audit_logs_time_slot');
        DB::statement('DROP INDEX IF EXISTS idx_replacement_requests_proposer_submitted');
        DB::statement('DROP INDEX IF EXISTS idx_replacement_requests_time_slot');

        // (a) reverted — original CASCADE semantics restored, default names regenerated
        Schema::table('replacement_requests', function (Blueprint $table): void {
            $table->dropForeign(['class_session_id']);
            $table->dropForeign(['replacement_time_slot_id']);

            $table->foreign('class_session_id')
                ->references('id')->on('class_sessions')->cascadeOnDelete();
            $table->foreign('replacement_time_slot_id')
                ->references('id')->on('time_slots')->cascadeOnDelete();
        });
    }
};
