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
        Schema::create('class_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->unsignedTinyInteger('week_number');
            $table->string('reason', 30);
            $table->timestamps();

            $table->unique(['class_session_id', 'week_number']);
        });

        DB::statement('ALTER TABLE class_exceptions ADD CONSTRAINT class_exceptions_week_number_check CHECK (week_number BETWEEN 1 AND 14)');
        DB::statement("ALTER TABLE class_exceptions ADD CONSTRAINT class_exceptions_reason_check CHECK (reason IN ('public_holiday', 'annual_leave', 'medical_leave', 'official_event', 'emergency_leave'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('class_exceptions');
    }
};