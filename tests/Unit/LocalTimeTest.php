<?php

namespace Tests\Unit;

use App\Services\ReplacementRequestMapper;
use App\Support\LocalTime;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * LocalTime (2026-11-04) — the ONE user-facing clock.
 *
 * The pin: the notifications panel formatted the STORED instant directly, so
 * Request #26's tooltip read `2 Nov 2026, 11:00 AM` (UTC) while the history
 * page — which converted — read `7:00 PM` for the same request. These
 * assertions are the guard on the single conversion those two now share.
 */
final class LocalTimeTest extends TestCase
{
    public function test_it_converts_a_stored_utc_instant_to_the_users_clock(): void
    {
        $stamp = Carbon::parse('2026-11-02 11:00:12', 'UTC');

        $this->assertSame('2 Nov 2026, 7:00 PM', LocalTime::stamp($stamp, 'j M Y, g:i A'));
    }

    public function test_it_keeps_the_notification_panel_short_shape(): void
    {
        $stamp = Carbon::parse('2026-11-02 11:00:12', 'UTC');

        $this->assertSame('2 Nov, 7:00 PM', LocalTime::stamp($stamp, 'j M, g:i A'));
    }

    public function test_a_null_stamp_stays_null(): void
    {
        $this->assertNull(LocalTime::stamp(null, 'j M, g:i A'));
    }

    public function test_the_conversion_never_mutates_the_caller_instant(): void
    {
        $stamp = Carbon::parse('2026-11-02 11:00:12', 'UTC');

        LocalTime::stamp($stamp, 'j M Y, g:i A');

        $this->assertSame('11:00', $stamp->format('H:i'), 'the stored instant is read-only');
    }

    public function test_the_mapper_constant_mirrors_the_helper(): void
    {
        $this->assertSame('Asia/Kuala_Lumpur', LocalTime::TZ);
        $this->assertSame(LocalTime::TZ, ReplacementRequestMapper::DISPLAY_TZ);
    }
}
