<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SDD replacement-span-persistence T1 — the request remembers its own length.
 *
 * A replacement slot is a 30-minute cell, so a 1-hour class books two of them and
 * a 2-hour class four. The run lived only in `replacement_request_slots`, and the
 * release actions (RejectRequest / CancelPendingRequest / AutoRejectRequest)
 * DELETE those rows to free the cells — leaving the anchor (the span's FIRST
 * cell) as the sole evidence. `spanFromAnchor()` assumed `count = 1`, so every
 * rejected or cancelled request displayed 30 minutes and a truncated end time
 * (user report 2026-11-07, requests #24 and #37).
 *
 * ADDITIVE ONLY: one nullable column, no default, no index, no data statement.
 * A null row keeps today's anchor behaviour byte-identically, so this migration
 * cannot change a single display until a row carries a value (the backfill
 * command fills old rows; every new submit writes it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('replacement_requests', function (Blueprint $table) {
            // the number of 30-minute cells the proposal covers (the full block width)
            $table->smallInteger('replacement_span')->nullable()->after('replacement_time_slot_id');
        });
    }

    public function down(): void
    {
        Schema::table('replacement_requests', function (Blueprint $table) {
            $table->dropColumn('replacement_span');
        });
    }
};
