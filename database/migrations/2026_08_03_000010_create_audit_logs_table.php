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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('action', 30);
            $table->foreignId('replacement_request_id')->nullable()->constrained('replacement_requests');
            $table->foreignId('time_slot_id')->nullable()->constrained('time_slots');
            $table->string('old_status', 20)->nullable();
            $table->string('new_status', 20)->nullable();
            $table->string('occ_validation_result', 20)->nullable();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index('replacement_request_id');
            $table->index('created_at');
        });

        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_action_check CHECK (action IN ('submitted', 'approved', 'rejected', 'cancelled', 'completed', 'occ_conflict'))");
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_occ_validation_result_check CHECK (occ_validation_result IN ('success', 'conflict'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
