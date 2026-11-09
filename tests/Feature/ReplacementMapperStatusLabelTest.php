<?php

namespace Tests\Feature;

use App\Services\ReplacementRequestMapper;
use Tests\TestCase;

/**
 * `student-class-replacements` T2 — the ONE word that differs by audience.
 *
 * The student page is read-only, so `Need to Arrange` (an instruction) becomes
 * `Awaiting Arrangement` (a state) when the page asks as `'student'`. Every other
 * label is shared on purpose: two pages must never disagree about what a state
 * means, only about who is reading it. The default arm is the staff one, so no
 * existing caller changes.
 */
final class ReplacementMapperStatusLabelTest extends TestCase
{
    public function test_the_default_arm_is_unchanged_for_every_staff_caller(): void
    {
        $mapper = app(ReplacementRequestMapper::class);

        $this->assertSame('Need to Arrange', $mapper->statusLabel(null, null));
        $this->assertSame('Pending — Waiting for Approval', $mapper->statusLabel('pending', '2026-11-09'));
        $this->assertSame('Approved', $mapper->statusLabel('approved', '2026-11-09'));
        $this->assertSame('Rejected', $mapper->statusLabel('rejected', '2026-11-09'));
        $this->assertSame('Cancelled by YOU', $mapper->statusLabel('cancelled', '2026-11-09'));

        // the lecturer audience named EXPLICITLY is the same bytes as the default
        $this->assertSame(
            $mapper->statusLabel(null, null),
            $mapper->statusLabel(null, null, 'lecturer'),
        );
    }

    public function test_the_student_arm_changes_only_the_unarranged_state(): void
    {
        $mapper = app(ReplacementRequestMapper::class);

        $this->assertSame('Awaiting Arrangement', $mapper->statusLabel(null, null, 'student'));
        $this->assertSame('Pending — Waiting for Approval', $mapper->statusLabel('pending', '2026-11-09', 'student'));
        $this->assertSame('Approved', $mapper->statusLabel('approved', '2026-11-09', 'student'));
        $this->assertSame('Rejected', $mapper->statusLabel('rejected', '2026-11-09', 'student'));
        $this->assertSame('Cancelled by YOU', $mapper->statusLabel('cancelled', '2026-11-09', 'student'));
    }

    public function test_the_vocabulary_forwards_the_audience_so_a_page_asks_once(): void
    {
        $mapper = app(ReplacementRequestMapper::class);

        $this->assertSame(
            'Awaiting Arrangement',
            $mapper->statusVocabulary(null, null, 'student')['label'],
            'statusVocabulary() must forward its audience to statusLabel()',
        );
        $this->assertSame(
            'Need to Arrange',
            $mapper->statusVocabulary(null, null)['label'],
            'and the default must stay the staff wording',
        );
    }
}
