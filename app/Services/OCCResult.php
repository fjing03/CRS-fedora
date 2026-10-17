<?php

namespace App\Services;

use App\Models\TimeSlot;

final readonly class OCCResult
{
    private function __construct(
        public bool $success,
        public ?string $conflictReason,
        public ?TimeSlot $timeSlot,
        public ?string $failingStart = null,
        public ?string $failingEnd = null,
    ) {}

    public static function success(TimeSlot $slot): self
    {
        return new self(success: true, conflictReason: null, timeSlot: $slot);
    }

    /**
     * Full-block extension (SDD full-block-reservation, design D6): the
     * NO-kind-param shape stays — the failing-kind stamps action-side (a raced
     * row always exists; the action reads these keys into its flat return).
     */
    public static function conflict(
        string $reason,
        ?TimeSlot $slot = null,
        ?string $failingStart = null,
        ?string $failingEnd = null,
    ): self {
        return new self(
            success: false,
            conflictReason: $reason,
            timeSlot: $slot,
            failingStart: $failingStart,
            failingEnd: $failingEnd,
        );
    }
}
