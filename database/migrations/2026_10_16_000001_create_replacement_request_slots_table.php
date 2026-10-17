<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Full-block reservation (SDD full-block-reservation, design D0):
     * the LIVE-reservation receipt — every reserved slot links its request.
     * Rows are DELETED on release (cancel; future approval reuses the same
     * release), so the UNIQUE keeps its meaning: a slot is live-linked by at
     * most one request at a time (mirrors the per-slot partial unique at the
     * request grain). The legacy `replacement_requests.replacement_time_slot_id`
     * anchor column stays (anchor identity + backward compat).
     */
    public function up(): void
    {
        Schema::create('replacement_request_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('replacement_request_id')->constrained('replacement_requests')->cascadeOnDelete();
            $table->foreignId('time_slot_id')->constrained('time_slots')->cascadeOnDelete();
            $table->unique('time_slot_id');
            $table->timestamps();

            $table->index('replacement_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacement_request_slots');
    }
};
