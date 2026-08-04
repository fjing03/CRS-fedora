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
        Schema::create('replacement_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->cascadeOnDelete();
            $table->foreignId('proposer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->foreignId('replacement_time_slot_id')->constrained('time_slots')->cascadeOnDelete();
            $table->foreignId('approver_id')->nullable()->constrained('users');
            $table->string('status', 20);
            $table->text('rejection_reason')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
        });

        DB::statement('ALTER TABLE replacement_requests ADD CONSTRAINT replacement_requests_week_number_check CHECK (week_number BETWEEN 1 AND 14)');
        DB::statement('ALTER TABLE replacement_requests ADD CONSTRAINT replacement_requests_status_check CHECK (status IN (\'pending\', \'approved\', \'rejected\', \'cancelled\', \'completed\'))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('replacement_requests');
    }
};
