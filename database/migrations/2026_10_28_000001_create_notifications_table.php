<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* SDD notifications-real-bridge: ONE new table — create-only, no existing
   table/column/constraint touched. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role_view', 12);   // pl | lecturer | student (the AD-2 key)
            $table->string('type', 16);        // the FROZEN tile keys: submitted|awaiting|approved|rejected|update
            $table->string('title');
            $table->string('body');
            $table->string('link');
            $table->foreignId('request_id')->nullable()->constrained('replacement_requests')->nullOnDelete();
            $table->foreignId('class_session_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('week')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'read_at']);
            $table->index('request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
