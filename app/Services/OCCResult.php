<?php

namespace App\Services;

use App\Models\TimeSlot;

final readonly class OCCResult
{
    private function __construct(
        public bool $success,
        public ?string $conflictReason,
        public ?TimeSlot $timeSlot,
    ) {}

    public static function success(TimeSlot $slot): self
    {
        return new self(success: true, conflictReason: null, timeSlot: $slot);
    }

    public static function conflict(string $reason): self
    {
        return new self(success: false, conflictReason: $reason, timeSlot: null);
    }
}
