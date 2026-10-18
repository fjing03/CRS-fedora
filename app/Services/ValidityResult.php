<?php

namespace App\Services;

/**
 * The validity read (design D6 / spec decision-flow R4): a value object the
 * mapper and the queue badge consume directly — `valid` OR `conflict(reason)`
 * with the pinned 2-case strings from ApprovalValidity.
 */
final class ValidityResult
{
    private function __construct(
        public readonly bool $valid,
        public readonly ?string $reason = null,
    ) {}

    public static function valid(): self
    {
        return new self(true, null);
    }

    public static function conflict(string $reason): self
    {
        return new self(false, $reason);
    }
}
