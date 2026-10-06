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
        Schema::table('cohorts', function (Blueprint $table): void {
            $table->unique(
                ['programme_id', 'academic_year', 'intake', 'current_year', 'semester', 'tutorial_group'],
                'cohorts_natural_key_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cohorts', function (Blueprint $table): void {
            $table->dropUnique('cohorts_natural_key_unique');
        });
    }
};
