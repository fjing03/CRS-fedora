<?php

namespace App\Support;

use App\Models\Cohort;

/**
 * The ONE spaced cohort convention — `DFT2 (S1) G1`.
 *
 * Promoted from `ReplacementHome::spacedCohortLabel()` (private) when the
 * student Class Replacements pop-up needed the same facts as the staff pop-up
 * (SDD student-modal-staff-parity, 2026-11-11). Two copies of a formatting law
 * is one too many, so both components read it here; the staff payload's output
 * is byte-identical to what the private method produced.
 */
final class CohortLabel
{
    /**
     * A missing cohort (or a cohort without its programme) is unknown, not
     * empty — the em dash tells the reader so on both pages.
     */
    public static function spaced(?Cohort $cohort): string
    {
        if ($cohort === null || $cohort->programme === null) {
            return '—';
        }

        return $cohort->programme->programme_code
            .$cohort->current_year.' (S'.$cohort->semester.')'
            .' G'.$cohort->tutorial_group;
    }

    /**
     * The SAME cohort spelled out (2026-11-11, user): the compact code above is
     * what the rows print; this is what their hover says —
     * `Diploma in Software Engineering — Year 2, Semester 1, Group 1`.
     */
    public static function full(?Cohort $cohort): string
    {
        if ($cohort === null || $cohort->programme === null) {
            return '—';
        }

        return $cohort->programme->programme_name
            .' — Year '.$cohort->current_year
            .', Semester '.$cohort->semester
            .', Group '.$cohort->tutorial_group;
    }
}
