<?php

namespace Tests\Unit;

use App\Services\LectureSessionCatalog;
use App\Services\VenueGuideRules;
use PHPUnit\Framework\TestCase;

final class VenueGuideRulesTest extends TestCase
{
    public function test_pl_gets_the_complete_sorted_lab_roster_with_exact_session_types(): void
    {
        $rules = LectureSessionCatalog::ruleMap();
        $summary = VenueGuideRules::summarize('lab', 'B009', $rules, true, []);
        $codes = array_keys($rules['lab_access']);
        sort($codes);

        self::assertSame(['Practical (P)'], $summary['defaultTypes']);
        self::assertNull($summary['programmes']);
        self::assertSame($codes, array_column($summary['exceptions'], 'code'));
        self::assertContains(['code' => 'AMCS1013', 'types' => ['Tutorial (T)']], $summary['exceptions']);
        self::assertContains(['code' => 'AMIT2033', 'types' => ['Lecture (L)', 'Tutorial (T)']], $summary['exceptions']);
    }

    public function test_lecturer_intersects_both_module_and_type_without_duplicate_rows(): void
    {
        $summary = VenueGuideRules::summarize('lab', 'B009', LectureSessionCatalog::ruleMap(), false, [
            'AMCS1013' => ['L'],
            'AMIT2033' => ['T', 'T'],
            'BMCS2053' => ['L', 'T'],
        ]);

        self::assertTrue($summary['hasRoomExceptions']);
        self::assertSame([['code' => 'AMIT2033', 'types' => ['Tutorial (T)']]], $summary['exceptions']);
    }

    public function test_distinguishes_a_lab_with_no_matching_teaching_from_a_room_without_exceptions(): void
    {
        $rules = LectureSessionCatalog::ruleMap();
        $lab = VenueGuideRules::summarize('lab', 'B009', $rules, false, []);
        $hall = VenueGuideRules::summarize('lecture_hall', 'B111', $rules, true, []);
        $tutorial = VenueGuideRules::summarize('tutorial', 'B101', $rules, true, []);

        self::assertTrue($lab['hasRoomExceptions']);
        self::assertSame([], $lab['exceptions']);
        self::assertFalse($hall['hasRoomExceptions']);
        self::assertSame([], $hall['exceptions']);
        self::assertSame(['Lecture (L)'], $hall['defaultTypes']);
        self::assertSame(['Lecture (L)', 'Tutorial (T)'], $tutorial['defaultTypes']);
    }

    public function test_explicit_b006_programme_exclusion_takes_precedence_for_all_types(): void
    {
        $rules = LectureSessionCatalog::ruleMap();
        $rules['b006_banned_programmes'][] = 'DSF';
        $summary = VenueGuideRules::summarize('cisco_lab', 'B006', $rules, true, []);

        self::assertSame(['Practical (P)'], $summary['defaultTypes']);
        self::assertSame(['DFT', 'RSD'], $summary['programmes']);
        self::assertNotEmpty($summary['exceptions']);
    }

    public function test_missing_type_does_not_create_a_default_or_exception(): void
    {
        $rules = LectureSessionCatalog::ruleMap();
        unset($rules['L']);
        $rules['lab_access']['UNKNOWN'] = ['X'];
        $summary = VenueGuideRules::summarize('lab', 'B009', $rules, true, []);

        self::assertNotContains('UNKNOWN', array_column($summary['exceptions'], 'code'));
        self::assertNotContains('BMIT2013', array_column($summary['exceptions'], 'code'));
        self::assertContains(['code' => 'AMIT2033', 'types' => ['Tutorial (T)']], $summary['exceptions']);
    }

    public function test_empty_selection_and_unknown_room_type_do_not_gain_defaults(): void
    {
        $rules = LectureSessionCatalog::ruleMap();
        foreach (['', 'UNKNOWN'] as $code) {
            self::assertSame([
                'defaultTypes' => [],
                'programmes' => null,
                'exceptions' => [],
                'hasRoomExceptions' => false,
            ], VenueGuideRules::summarize('unknown', $code, $rules, true, []));
        }
    }
}
