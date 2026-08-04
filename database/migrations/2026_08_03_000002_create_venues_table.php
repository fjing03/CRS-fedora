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
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('room_code', 20)->unique();
            $table->unsignedSmallInteger('capacity');
            $table->string('room_type', 30);
            $table->string('allowed_session_types', 10);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE venues ADD CONSTRAINT venues_room_type_check CHECK (room_type IN (\'tutorial\', \'lecture_hall\', \'lab\', \'cisco_lab\'))');

        Schema::table('venues', function (Blueprint $table) {
            $table->index('room_type');
            $table->index('capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
