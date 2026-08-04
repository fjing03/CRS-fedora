<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('session_cohorts', function (Blueprint $table) {
            $table->foreignId('class_session_id')
                ->constrained('class_sessions')
                ->cascadeOnDelete();
            $table->foreignId('cohort_id')
                ->constrained('cohorts')
                ->cascadeOnDelete();

            $table->primary(['class_session_id', 'cohort_id']);
            $table->index('cohort_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_cohorts');
    }
};
