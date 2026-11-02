<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* SDD notif-manage-hide — STRICTLY ADDITIVE.
   Adds ONE nullable timestamp plus ONE index. No existing column, constraint,
   index or row is touched: every existing notification simply reads
   hidden_at = NULL, which the app treats as "not hidden". */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
            $table->index(['user_id', 'hidden_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'hidden_at']);
            $table->dropColumn('hidden_at');
        });
    }
};
