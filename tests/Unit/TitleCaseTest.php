<?php

namespace Tests\Unit;

use App\Support\TitleCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * notif-inactive-hide-sender (2026-11-05) — the panel reads Title Case, the
 * stored titles stay sentences. These are the real strings the emitters write.
 */
class TitleCaseTest extends TestCase
{
    /** @return array<string, array{0: string, 1: string}> */
    public static function titles(): array
    {
        return [
            'cancel notice' => ['Scheduled class cancelled', 'Scheduled Class Cancelled'],
            'submitted' => ['New replacement request', 'New Replacement Request'],
            'approved' => ['Replacement request approved', 'Replacement Request Approved'],
            'rejected' => ['Replacement request rejected', 'Replacement Request Rejected'],
            'already title case' => ['Replacement Class Updated', 'Replacement Class Updated'],
            'id token survives' => ['New Replacement Request (ID #28)', 'New Replacement Request (ID #28)'],
            'minor words stay low' => ['Replacement of the class on demand', 'Replacement of the Class on Demand'],
            'minor word first' => ['to the class', 'To the Class'],
            'acronym survives' => ['AMCS2093 cancelled at B110', 'AMCS2093 Cancelled at B110'],
            'module with digits' => ['AMSE2003 lecture moved', 'AMSE2003 Lecture Moved'],
            'code with slash' => ['P/T class cancelled', 'P/T Class Cancelled'],
            'empty' => ['', ''],
            'spaces only' => ['   ', ''],
            'single word' => ['cancelled', 'Cancelled'],
            'punctuation kept' => ['(late) notice', '(Late) Notice'],
        ];
    }

    #[DataProvider('titles')]
    public function test_it_title_cases_the_real_titles(string $stored, string $expected): void
    {
        $this->assertSame($expected, TitleCase::of($stored));
    }
}
