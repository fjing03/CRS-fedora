<?php

namespace Tests\Feature;

use App\Console\Commands\ReleaseCancelledClassSlots;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use App\Models\Module;
use App\Models\ReplacementRequest;
use App\Models\ReplacementRequestSlot;
use App\Models\Semester;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Venue;
use App\Services\CancelledSlotCleanupRecovery;
use App\Services\CancelledSlotCleanupSnapshotGate;
use App\Services\CancelledSlotCleanupTargetPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Input\ArgvInput;
use Tests\TestCase;

/**
 * The guarded existing-data cleanup facility (SDD cancelled-slot-reuse,
 * design D9, specs S26–S30, tasks C01–C03) — code+testing rehearsal ONLY.
 *
 * Nothing here touches a demo database: every artisan invocation runs
 * against the testing process's actual database (`class_replacement_testing`
 * via phpunit.xml — never a config swap), forbidden targets are covered by
 * PURE policy unit cases, and snapshot manifests are fabricated files whose
 * checksums match — no external backup command is ever executed.
 * RefreshDatabase with minimal explicit fixtures, no seed data.
 */
final class CancelledSlotCleanupTest extends TestCase
{
    use RefreshDatabase;

    private Semester $semester;

    private Venue $venue;

    private Module $module;

    private User $lecturer;

    private User $actor;

    private ClassSession $session;

    private string $tempDir = '';

    /** The occurrence under test: week 5 of a Tuesday 09:00–11:00 class → four 30-minute cells. */
    private const WEEK = 5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir().'/crs-cleanup-'.uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->semester = Semester::create([
            'semester_code' => 'CSC001',
            'label' => 'Cancelled Slot Cleanup Fixture',
            'start_date' => '2026-09-21', // a Monday
            'end_date' => '2026-12-27',
            'week_count' => 14,
        ]);

        $this->venue = Venue::create([
            'room_code' => 'CS101',
            'capacity' => 40,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        $this->module = Module::create([
            'module_code' => 'CS1001',
            'module_name' => 'Cleanup Module',
            'allowed_session_types' => 'L,T',
        ]);

        $this->lecturer = User::factory()->create(['role' => 'lecturer']);
        $this->actor = User::factory()->create(['role' => 'lecturer']);

        $this->session = $this->makeSession();
    }

    protected function tearDown(): void
    {
        if ($this->tempDir !== '' && is_dir($this->tempDir)) {
            foreach (glob($this->tempDir.'/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($this->tempDir);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // S26 — dry-run inventory, zero writes
    // ------------------------------------------------------------------

    public function test_dry_run_writes_nothing(): void
    {
        $cells = $this->originalCells($this->session, self::WEEK);
        $this->cancelOccurrence($this->session, self::WEEK);

        $this->artisan('classes:release-cancelled-slots')
            ->expectsOutputToContain('DRY RUN')
            ->expectsOutputToContain('class_replacement_testing')
            ->expectsOutputToContain('release: available, owner NULL, version 2')
            ->assertExitCode(0);

        // Byte-exact zero writes: the only audit is the user cancellation.
        $this->assertSame(1, DB::table('audit_logs')->count());
        $this->assertSame(1, DB::table('class_exceptions')->count());
        $this->assertSame(0, DB::table('notifications')->count());

        foreach ($cells as $index => $cell) {
            $fresh = DB::table('time_slots')->where('id', $cell->id)->first();
            $this->assertSame('occupied', $fresh->status);
            $this->assertSame((int) $this->session->id, (int) $fresh->class_session_id);
            $this->assertSame($index + 1, (int) $fresh->version);
        }
    }

    public function test_dry_run_classifies_cells_and_reports_exclusions(): void
    {
        // Cell 1 stays the pristine original; cell 2 is already available
        // (with a duplicate available row); cell 3 is held by a live pending
        // request through its junction; cell 4 is an unexplained pending row.
        $cells = $this->originalCells($this->session, self::WEEK);

        $availableCopy = TimeSlot::create([
            'semester_id' => $this->semester->id,
            'class_session_id' => null,
            'week_number' => self::WEEK,
            'day_of_week' => 1,
            'start_time' => '09:30:00',
            'end_time' => '10:00:00',
            'venue_id' => $this->venue->id,
            'status' => 'available',
            'version' => 1,
        ]);
        $cells[1]->update(['status' => 'available', 'class_session_id' => null, 'version' => 3]);

        $held = $cells[2];
        $request = $this->requestHolding($this->session, [$held->id]);
        $held->update(['status' => 'pending', 'class_session_id' => null]);

        $cells[3]->update(['status' => 'pending', 'class_session_id' => null]);

        $this->cancelOccurrence($this->session, self::WEEK);

        $this->artisan('classes:release-cancelled-slots')
            ->expectsOutputToContain('EXCLUDED — unexplained_pending')
            ->expectsOutputToContain('cell evidence: request_held')
            ->expectsOutputToContain('cell evidence: available')
            ->expectsOutputToContain('occurrence excluded; cell evidence: ambiguous (unexplained_pending)')
            ->assertExitCode(0);

        $this->assertSame(1, DB::table('audit_logs')->count());

        $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $this->tempDir.'/plan.json'])->assertExitCode(0);

        $plan = json_decode((string) file_get_contents($this->tempDir.'/plan.json'), true, 512, JSON_THROW_ON_ERROR);
        $occurrence = $plan['occurrences'][0];

        $this->assertFalse($occurrence['included']);
        $this->assertSame('unexplained_pending', $occurrence['exclusion_reason']);
        $this->assertSame('original', $occurrence['plan']['entries'][0]['classification']);
        $this->assertSame('available', $occurrence['plan']['entries'][1]['classification']);
        $this->assertSame('request_held', $occurrence['plan']['entries'][2]['classification']);
        $this->assertSame('ambiguous', $occurrence['plan']['entries'][3]['classification']);
        $this->assertSame([$cells[1]->id, $availableCopy->id], $occurrence['plan']['entries'][1]['duplicate_slot_ids']);
        $this->assertSame($request->id, $occurrence['plan']['entries'][2]['held_by_request_id']);
    }

    public function test_evidence_discriminator_ignores_maintenance_undo_and_approval_only(): void
    {
        $this->originalCells($this->session, self::WEEK);
        $this->cancelOccurrence($this->session, self::WEEK);

        // (a) a MAINTENANCE receipt is not cancellation evidence: an
        // occurrence carrying only a maintenance audit never pairs.
        $otherSession = $this->makeSession(['start_time' => '13:00:00', 'end_time' => '14:00:00']);
        $this->originalCells($otherSession, self::WEEK);
        ClassException::create(['class_session_id' => $otherSession->id, 'week_number' => self::WEEK, 'reason' => 'medical_leave']);
        AuditLog::create([
            'user_id' => $this->actor->id,
            'action' => 'class_cancelled',
            'details' => [
                'maintenance' => true,
                'operation_id' => 'op-earlier',
                'class_session_id' => $otherSession->id,
                'week_number' => self::WEEK,
                'original_release' => ['changed' => [], 'no_ops' => []],
            ],
        ]);

        // (b) an approval-only `official_event` exception stamp (no
        // cancellation audit) never pairs.
        $thirdSession = $this->makeSession(['start_time' => '15:00:00', 'end_time' => '16:00:00']);
        $this->originalCells($thirdSession, self::WEEK);
        ClassException::create(['class_session_id' => $thirdSession->id, 'week_number' => self::WEEK, 'reason' => 'official_event']);

        // (c) a legacy UNDO audit alongside a live exception contradicts the pair.
        $fourthSession = $this->makeSession(['start_time' => '16:00:00', 'end_time' => '17:00:00']);
        $this->originalCells($fourthSession, self::WEEK);
        $this->cancelOccurrence($fourthSession, self::WEEK);
        AuditLog::create([
            'user_id' => $this->actor->id,
            'action' => 'class_cancel_undone',
            'details' => ['class_session_id' => $fourthSession->id, 'week_number' => self::WEEK],
        ]);

        // (d) DUPLICATE cancellation audits contradict the pair — a SECOND
        // non-maintenance `class_cancelled` audit against the SAME current
        // exception (the exception stays unique; only the audit duplicates).
        $fifthSession = $this->makeSession(['start_time' => '17:00:00', 'end_time' => '18:00:00']);
        $this->originalCells($fifthSession, self::WEEK);
        $this->cancelOccurrence($fifthSession, self::WEEK);
        AuditLog::create([
            'user_id' => $this->actor->id,
            'action' => 'class_cancelled',
            'details' => [
                'class_session_id' => $fifthSession->id,
                'week_number' => self::WEEK,
                'reason' => 'medical_leave',
            ],
        ]);

        $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $this->tempDir.'/plan.json'])->assertExitCode(0);

        $plan = json_decode((string) file_get_contents($this->tempDir.'/plan.json'), true, 512, JSON_THROW_ON_ERROR);
        $byKey = [];

        foreach ($plan['occurrences'] as $occurrence) {
            $byKey[$occurrence['class_session_id'].':'.$occurrence['week_number']] = $occurrence;
        }

        $this->assertTrue($byKey[$this->session->id.':'.self::WEEK]['included']);
        $this->assertArrayNotHasKey($otherSession->id.':'.self::WEEK, $byKey); // maintenance receipt is no pair at all
        $this->assertArrayNotHasKey($thirdSession->id.':'.self::WEEK, $byKey); // approval-only stamp is no pair at all
        $this->assertFalse($byKey[$fourthSession->id.':'.self::WEEK]['included']);
        $this->assertSame('undo_evidence_present', $byKey[$fourthSession->id.':'.self::WEEK]['exclusion_reason']);
        $this->assertFalse($byKey[$fifthSession->id.':'.self::WEEK]['included']);
        $this->assertSame('duplicate_cancellation_audit', $byKey[$fifthSession->id.':'.self::WEEK]['exclusion_reason']);
    }

    // ------------------------------------------------------------------
    // S27 — fail-closed gates (pure policy + per-gate refusals)
    // ------------------------------------------------------------------

    public function test_target_policy_pure_validation_without_any_database(): void
    {
        $this->assertNull(CancelledSlotCleanupTargetPolicy::check('class_replacement_fresh', 'class_replacement_fresh'));
        $this->assertNull(CancelledSlotCleanupTargetPolicy::check('class_replacement_testing', 'class_replacement_testing'));

        // Forbidden targets refuse even with a MATCHING confirmation.
        $this->assertSame(
            'disallowed_target:class_replacement_viva',
            CancelledSlotCleanupTargetPolicy::check('class_replacement_viva', 'class_replacement_viva'),
        );
        $this->assertSame(
            'disallowed_target:class_replacement_demo',
            CancelledSlotCleanupTargetPolicy::check('class_replacement_demo', 'class_replacement_demo'),
        );
        $this->assertSame(
            'disallowed_target:postgres',
            CancelledSlotCleanupTargetPolicy::check('postgres', 'postgres'),
        );

        // A confirmation that does not equal the actual identity refuses.
        $this->assertSame(
            'target_mismatch:confirmed class_replacement_fresh vs actual class_replacement_testing',
            CancelledSlotCleanupTargetPolicy::check('class_replacement_fresh', 'class_replacement_testing'),
        );
        $this->assertSame('missing_target_confirmation', CancelledSlotCleanupTargetPolicy::check(null, 'class_replacement_testing'));
    }

    public function test_apply_gate_failures_are_per_gate_and_write_nothing(): void
    {
        $this->originalCells($this->session, self::WEEK);
        $this->cancelOccurrence($this->session, self::WEEK);

        $planPath = $this->tempDir.'/plan.json';
        $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $planPath])->assertExitCode(0);
        $this->approvePlanFile($planPath, 'GATES');
        $snap = $this->snapshotFiles();

        $base = $this->applyOptions($planPath, $snap, 'GATES');

        $asserts = function (string $name): void {
            $this->assertSame(1, DB::table('audit_logs')->count(), $name.' must write no audit');
            $this->assertSame(4, DB::table('time_slots')
                ->where('class_session_id', $this->session->id)
                ->where('week_number', self::WEEK)
                ->where('status', 'occupied')
                ->count(), $name.' must flip nothing');
        };

        // Missing options fail before anything else.
        $this->artisan('classes:release-cancelled-slots', ['--apply' => true])->assertFailed();
        $asserts('missing gates');

        // A forbidden/mismatched target name refuses.
        $this->artisan('classes:release-cancelled-slots', ['--confirm-database' => 'class_replacement_viva'] + $base)->assertFailed();
        $asserts('forbidden target');

        // A tampered dump refuses the snapshot gate.
        $tamperedDump = $this->tempDir.'/tampered.dump';
        copy($snap['dump'], $tamperedDump);
        file_put_contents($tamperedDump, 'tampered');
        $this->artisan('classes:release-cancelled-slots', ['--snapshot' => $tamperedDump] + $base)->assertFailed();
        $asserts('snapshot checksum mismatch');

        // An unreadable plan file refuses.
        $this->artisan('classes:release-cancelled-slots', ['--plan' => $this->tempDir.'/missing.json'] + $base)->assertFailed();
        $asserts('plan unreadable');

        // Edited plan CONTENT no longer hashes to its recorded fingerprint.
        $tamperedPlan = $this->tempDir.'/tampered-plan.json';
        copy($planPath, $tamperedPlan);
        $tampered = json_decode((string) file_get_contents($tamperedPlan), true, 512, JSON_THROW_ON_ERROR);
        array_pop($tampered['occurrences'][0]['plan']['entries']);
        file_put_contents($tamperedPlan, (string) json_encode($tampered, JSON_PRETTY_PRINT));
        $this->artisan('classes:release-cancelled-slots', ['--plan' => $tamperedPlan] + $base)->assertFailed();
        $asserts('plan fingerprint mismatch');

        // A plan file without the approval block refuses.
        $unapprovedPlan = $this->tempDir.'/unapproved-plan.json';
        copy($planPath, $unapprovedPlan);
        $unapproved = json_decode((string) file_get_contents($unapprovedPlan), true, 512, JSON_THROW_ON_ERROR);
        unset($unapproved['approval']);
        file_put_contents($unapprovedPlan, (string) json_encode($unapproved, JSON_PRETTY_PRINT));
        $this->artisan('classes:release-cancelled-slots', ['--plan' => $unapprovedPlan] + $base)->assertFailed();
        $asserts('approval block missing');

        // The approval reference must match BOTH the flags and the plan block.
        $this->artisan('classes:release-cancelled-slots', ['--approval-reference' => 'OTHER'] + $base)->assertFailed();
        $asserts('approval reference mismatch');

        // The audit actor must exist.
        $this->artisan('classes:release-cancelled-slots', ['--actor-user-id' => '999999'] + $base)->assertFailed();
        $asserts('unknown actor');
    }

    // ------------------------------------------------------------------
    // S27 — snapshot gate (filesystem only, no external command)
    // ------------------------------------------------------------------

    public function test_snapshot_gate_verifies_manifest_checksums_and_target(): void
    {
        $snap = $this->snapshotFiles();

        $gate = new CancelledSlotCleanupSnapshotGate;

        $verified = $gate->verify($snap['manifest'], $snap['dump'], 'class_replacement_testing');
        $this->assertTrue($verified['ok']);

        // A tampered dump refuses.
        file_put_contents($snap['dump'], 'tampered');
        $this->assertSame('snapshot_checksum_mismatch', $gate->verify($snap['manifest'], $snap['dump'], 'class_replacement_testing')['reason']);

        // A manifest naming another target refuses (port ≠ database: the
        // manifest and the live current_database() must agree exactly).
        $snap2 = $this->snapshotFiles();
        $this->assertFalse($gate->verify($snap2['manifest'], $snap2['dump'], 'class_replacement_fresh')['ok']);
    }

    public function test_env_flag_pure_parser_fires_while_the_environment_file_stays_env(): void
    {
        // The REAL forbidden input, parsed purely (no artisan run, nothing
        // bootstrapped): the repo has NO .env.testing file, so Laravel
        // keeps the demo .env — the environment file NAME alone is a false
        // assurance. The raw --env parser must still report 'testing'.
        $flag = new ArgvInput(['artisan', 'classes:release-cancelled-slots', '--env=testing']);
        $this->assertSame('testing', ReleaseCancelledClassSlots::requestedEnv($flag));
        $this->assertSame('.env', app()->environmentFile(),
            'the false assurance: with no .env.testing file, Laravel silently stays on .env');

        // The guard is the OR of BOTH detections — it must fire here on the
        // raw flag alone (the filename arm is false), before anything else.
        $this->assertTrue(
            ReleaseCancelledClassSlots::requestedEnv($flag) === 'testing'
                || app()->environmentFile() === '.env.testing',
            'the guard must catch --env=testing even while the environment file name remains .env',
        );

        // Space-separated form carries the same value.
        $spaced = new ArgvInput(['artisan', 'classes:release-cancelled-slots', '--env', 'testing']);
        $this->assertSame('testing', ReleaseCancelledClassSlots::requestedEnv($spaced));

        // No flag, or a bare valueless flag, parses to null (the filename
        // guard beneath it still applies).
        $clean = new ArgvInput(['artisan', 'classes:release-cancelled-slots']);
        $this->assertNull(ReleaseCancelledClassSlots::requestedEnv($clean));

        $bare = new ArgvInput(['artisan', 'classes:release-cancelled-slots', '--env']);
        $this->assertNull(ReleaseCancelledClassSlots::requestedEnv($bare));
    }

    // ------------------------------------------------------------------
    // S28/S29 — testing apply rehearsal, receipts, repeat
    // ------------------------------------------------------------------

    public function test_apply_releases_originals_with_receipts_and_zero_change_repeat(): void
    {
        $cells = $this->originalCells($this->session, self::WEEK);

        // Protected mixture: cell 3 request-held through a live junction;
        // cell 4 already available (duplicate rows); a request in ANOTHER
        // week of the same session stays untouched throughout.
        $request = $this->requestHolding($this->session, [$cells[2]->id]);
        $cells[2]->update(['status' => 'pending', 'class_session_id' => null, 'version' => 2]);

        $availableCopy = TimeSlot::create([
            'semester_id' => $this->semester->id,
            'class_session_id' => null,
            'week_number' => self::WEEK,
            'day_of_week' => 1,
            'start_time' => '10:30:00',
            'end_time' => '11:00:00',
            'venue_id' => $this->venue->id,
            'status' => 'available',
            'version' => 5,
        ]);
        $cells[3]->update(['status' => 'available', 'class_session_id' => null, 'version' => 5]);

        $otherWeek = $this->cell($this->session, '09:00:00', 6);
        $this->cancelOccurrence($this->session, self::WEEK);

        // Rehearsal 1 — dry run produces the plan file.
        $planPath = $this->tempDir.'/plan.json';
        $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $planPath])->assertExitCode(0);

        $plan = json_decode((string) file_get_contents($planPath), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($plan['occurrences'][0]['included']);
        $this->assertSame($cells[3]->id, $plan['occurrences'][0]['plan']['entries'][3]['slot_id']);

        $snap = $this->snapshotFiles();
        $this->approvePlanFile($planPath);

        // Rehearsal 1 — apply on the testing database.
        $this->artisan('classes:release-cancelled-slots', $this->applyOptions($planPath, $snap, 'REHEARSAL-1'))
            ->expectsOutputToContain('maintenance audits written: 1')
            ->expectsOutputToContain('Post-commit verification: clean')
            ->expectsOutputToContain('PROTECTED (request '.$request->id.')')
            ->assertExitCode(0);

        // The original subset released exactly; version +1, owner cleared.
        $released1 = DB::table('time_slots')->where('id', $cells[0]->id)->first();
        $released2 = DB::table('time_slots')->where('id', $cells[1]->id)->first();
        $this->assertSame('available', $released1->status);
        $this->assertNull($released1->class_session_id);
        $this->assertSame(2, (int) $released1->version);
        $this->assertSame('available', $released2->status);
        $this->assertNull($released2->class_session_id);
        $this->assertSame(3, (int) $released2->version);

        // Protected cells byte-identical (stale ids preserved, no bump).
        $protected = DB::table('time_slots')->where('id', $cells[2]->id)->first();
        $this->assertSame('pending', $protected->status);
        $this->assertNull($protected->class_session_id);
        $this->assertSame(2, (int) $protected->version);
        $this->assertSame(1, DB::table('replacement_request_slots')->where('time_slot_id', $cells[2]->id)->count());
        $this->assertSame('pending', (string) ReplacementRequest::query()->find($request->id)->status);

        $availableRow = DB::table('time_slots')->where('id', $availableCopy->id)->first();
        $this->assertSame('available', $availableRow->status);
        $this->assertNull($availableRow->class_session_id);
        $this->assertSame(5, (int) $availableRow->version);

        // Exactly ONE legal maintenance receipt: maintenance flag, operation
        // id, per-cell before/after/no-op evidence; NO notification.
        $audits = AuditLog::query()->where('action', 'class_cancelled')->get();
        $this->assertSame(2, $audits->count());
        $maintenance = $audits->filter(fn (AuditLog $audit): bool => ($audit->details['maintenance'] ?? false) === true);
        $this->assertSame(1, $maintenance->count());
        $details = $maintenance->first()->details;
        $this->assertSame('cleanup-rehearsal-1', $details['operation_id']);
        $this->assertSame('REHEARSAL-1', $details['approval_reference']);
        $this->assertSame($this->session->id, $details['class_session_id']);
        $this->assertSame(self::WEEK, $details['week_number']);
        $this->assertCount(2, $details['original_release']['changed']);
        $this->assertCount(2, $details['original_release']['no_ops']);
        $this->assertSame(0, DB::table('notifications')->count());

        // The user cancellation receipt and the exception stay intact.
        $this->assertSame(1, DB::table('class_exceptions')->where('class_session_id', $this->session->id)->where('week_number', self::WEEK)->count());
        $this->assertSame('medical_leave', (string) ClassException::query()->first()->reason);

        // Other weeks untouched.
        $otherWeekRow = DB::table('time_slots')->where('id', $otherWeek->id)->first();
        $this->assertSame('occupied', $otherWeekRow->status);
        $this->assertSame(1, (int) $otherWeekRow->version);
        $this->assertSame((int) $this->session->id, (int) $otherWeekRow->class_session_id);

        // Zero-change repeat: a second inventory proposes nothing further and
        // a repeat apply writes no audit and bumps no version.
        $repeatPath = $this->tempDir.'/plan-repeat.json';
        $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $repeatPath])->assertExitCode(0);

        $repeatEntries = json_decode((string) file_get_contents($repeatPath), true, 512, JSON_THROW_ON_ERROR)['occurrences'][0]['plan']['entries'];
        $this->assertSame('available', $repeatEntries[0]['classification']);
        $this->assertSame('available', $repeatEntries[1]['classification']);
        $this->assertSame('request_held', $repeatEntries[2]['classification']);
        $this->assertSame('available', $repeatEntries[3]['classification']);

        $this->approvePlanFile($repeatPath, 'REHEARSAL-2');
        $snap2 = $this->snapshotFiles();

        $auditCountBefore = DB::table('audit_logs')->count();

        $this->artisan('classes:release-cancelled-slots', $this->applyOptions($repeatPath, $snap2, 'REHEARSAL-2'))
            ->expectsOutputToContain('maintenance audits written: 0')
            ->assertExitCode(0);

        $this->assertSame($auditCountBefore, DB::table('audit_logs')->count());
        $this->assertSame(2, (int) DB::table('time_slots')->where('id', $cells[0]->id)->first()->version);
    }

    public function test_apply_aborts_entire_plan_on_fingerprint_drift(): void
    {
        $cells = $this->originalCells($this->session, self::WEEK);
        $this->cancelOccurrence($this->session, self::WEEK);

        $cellIds = array_map(fn (TimeSlot $cell) => $cell->id, $cells);

        $drifts = [
            'slot version drifted' => [
                fn () => DB::table('time_slots')->where('id', $cells[1]->id)->update(['version' => 9]),
                fn () => DB::table('time_slots')->where('id', $cells[1]->id)->update(['version' => 2]),
            ],
            'protected claim appeared' => [
                function () use ($cells): void {
                    $this->requestHolding($this->session, [$cells[0]->id]);
                },
                function (): void {
                    DB::table('replacement_request_slots')->delete();
                    DB::table('replacement_requests')->whereIn('class_session_id', [$this->session->id])->delete();
                },
            ],
            'exception reason drifted' => [
                fn () => DB::table('class_exceptions')->update(['reason' => 'official_event']),
                fn () => DB::table('class_exceptions')->update(['reason' => 'medical_leave']),
            ],
            'audit membership drifted' => [
                function (): void {
                    AuditLog::create([
                        'user_id' => $this->actor->id,
                        'action' => 'class_cancel_undone',
                        'details' => ['class_session_id' => $this->session->id, 'week_number' => self::WEEK],
                    ]);
                },
                fn () => DB::table('audit_logs')->where('action', 'class_cancel_undone')->delete(),
            ],
        ];

        foreach ($drifts as $name => [$drift, $rollback]) {
            // An aborted plan is dead — each case starts from a fresh one.
            $casePath = $this->tempDir.'/plan-'.md5($name).'.json';
            $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $casePath])->assertExitCode(0);
            $this->approvePlanFile($casePath, 'DRIFT');
            $caseSnap = $this->snapshotFiles();

            $drift();

            // The drift is this test's own deliberate action — the apply must
            // add NOTHING to it. Snapshot AFTER the intended external drift,
            // BEFORE the apply; the assertion is then "no further mutations".
            $before = DB::table('time_slots')->whereIn('id', $cellIds)->orderBy('id')->get()->toJson();
            $auditsBefore = DB::table('audit_logs')->count();

            $this->artisan('classes:release-cancelled-slots', $this->applyOptions($casePath, $caseSnap, 'DRIFT'))->assertFailed();

            // NOTHING was written: the aborted plan left the post-drift state
            // byte-identical (it mutated nothing beyond the deliberate drift).
            $this->assertSame($before, DB::table('time_slots')->whereIn('id', $cellIds)->orderBy('id')->get()->toJson(), $name.' must abort everything');
            $this->assertSame($auditsBefore, DB::table('audit_logs')->count(), $name.' must write no audit');

            $rollback();
        }
    }

    public function test_apply_excludes_ambiguous_occurrence_and_releases_safe_sibling(): void
    {
        // Safe session: all four cells pristine originals.
        $safeCells = $this->originalCells($this->session, self::WEEK);
        $this->cancelOccurrence($this->session, self::WEEK);

        // Ambiguous sibling: a MISSING expected row (three of four present).
        $ambiguousSession = $this->makeSession(['start_time' => '13:00:00', 'end_time' => '15:00:00']);
        $ambiguousCells = $this->originalCells($ambiguousSession, self::WEEK);
        $ambiguousCells[3]->delete();
        $this->cancelOccurrence($ambiguousSession, self::WEEK);

        $planPath = $this->tempDir.'/plan.json';
        $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $planPath])->assertExitCode(0);
        $this->approvePlanFile($planPath, 'MIXED');
        $snap = $this->snapshotFiles();

        $this->artisan('classes:release-cancelled-slots', $this->applyOptions($planPath, $snap, 'MIXED'))->assertExitCode(0);

        // Only the safe sibling's originals released.
        foreach ($safeCells as $cell) {
            $row = DB::table('time_slots')->where('id', $cell->id)->first();
            $this->assertSame('available', $row->status);
        }

        foreach (array_slice($ambiguousCells, 0, 3) as $cell) {
            $row = DB::table('time_slots')->where('id', $cell->id)->first();
            $this->assertSame('occupied', $row->status);
            $this->assertSame((int) $ambiguousSession->id, (int) $row->class_session_id);
        }

        $maintenance = AuditLog::query()->get()->filter(
            fn (AuditLog $audit): bool => ($audit->details['maintenance'] ?? false) === true,
        );
        $this->assertSame(1, $maintenance->count());
        $this->assertSame($this->session->id, $maintenance->first()->details['class_session_id']);
    }

    public function test_apply_leaves_a_post_cancel_request_landing_in_a_different_room_byte_identical(): void
    {
        $cells = $this->originalCells($this->session, self::WEEK);
        $this->cancelOccurrence($this->session, self::WEEK);

        // A NEW post-cancel request landing in a DIFFERENT room: its anchor
        // and junction cells are NOT the occurrence's coordinates, so the
        // shared planner must neither reject the cleanup nor treat them as
        // claims on the original cells — the lock union rides the shared
        // helper, the release frees ONLY this room's original cells, and
        // the other room's booking stays byte-identical (status, owner
        // link, version, junction links and the request row itself).
        $otherVenue = Venue::create([
            'room_code' => 'CS201',
            'capacity' => 25,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ]);

        $anchor = $this->cell($this->session, '13:00:00', self::WEEK, [
            'venue_id' => $otherVenue->id,
            'status' => 'available',
            'class_session_id' => null,
        ]);
        $secondLanding = $this->cell($this->session, '13:30:00', self::WEEK, [
            'venue_id' => $otherVenue->id,
            'status' => 'available',
            'class_session_id' => null,
        ]);

        $request = ReplacementRequest::create([
            'semester_id' => (int) $this->session->semester_id,
            'proposer_id' => (int) $this->session->lecturer_id,
            'class_session_id' => (int) $this->session->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => (int) $anchor->id,
            'replacement_span' => 2,
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        foreach ([$anchor->id, $secondLanding->id] as $slotId) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slotId,
            ]);
        }

        $landingBefore = DB::table('time_slots')
            ->whereIn('id', [$anchor->id, $secondLanding->id])
            ->orderBy('id')
            ->get()
            ->toJson();

        $planPath = $this->tempDir.'/plan.json';
        $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $planPath])->assertExitCode(0);
        $this->approvePlanFile($planPath, 'OTHER-ROOM');
        $snap = $this->snapshotFiles();

        $this->artisan('classes:release-cancelled-slots', $this->applyOptions($planPath, $snap, 'OTHER-ROOM'))
            ->expectsOutputToContain('Post-commit verification: clean')
            ->assertExitCode(0);

        // This room's original cells released exactly.
        foreach ($cells as $cell) {
            $row = DB::table('time_slots')->where('id', $cell->id)->first();
            $this->assertSame('available', $row->status);
            $this->assertNull($row->class_session_id);
        }

        // The OTHER room's landing stayed byte-identical — full status/link
        // /version pin over the helper's whole union of bookings.
        $this->assertSame($landingBefore, DB::table('time_slots')
            ->whereIn('id', [$anchor->id, $secondLanding->id])
            ->orderBy('id')
            ->get()
            ->toJson());

        $this->assertSame('pending', (string) ReplacementRequest::query()->find($request->id)->status);
        $this->assertSame(2, DB::table('replacement_request_slots')
            ->where('replacement_request_id', $request->id)
            ->count());
    }

    // ------------------------------------------------------------------
    // S30 — recovery rehearsal (testing-only helper)
    // ------------------------------------------------------------------

    public function test_recovery_rehearsal_restores_safe_cells_with_monotonic_versions(): void
    {
        $this->originalCells($this->session, self::WEEK);
        $this->cancelOccurrence($this->session, self::WEEK);

        $planPath = $this->tempDir.'/plan.json';
        $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $planPath])->assertExitCode(0);
        $this->approvePlanFile($planPath, 'RECOVER-1');
        $snap = $this->snapshotFiles();

        $this->artisan('classes:release-cancelled-slots', $this->applyOptions($planPath, $snap, 'RECOVER-1'))->assertExitCode(0);

        $receiptCells = $this->maintenanceReceiptCells();
        $this->assertCount(4, $receiptCells);

        $recovery = new CancelledSlotCleanupRecovery;
        $verdict = $recovery->check($receiptCells);

        $this->assertSame('restore_allowed', $verdict['verdict']);

        $exceptionCountBefore = DB::table('class_exceptions')->count();
        $auditCountBefore = DB::table('audit_logs')->count();

        $result = $recovery->rehearse($receiptCells);

        $this->assertSame('restored', $result['verdict'], (string) $result['refusal_reason']);
        $this->assertCount(4, $result['restored']);

        // Every cell re-occupied by its ORIGINAL owner, version INCREMENTED
        // from the current one (post 2 → restored 3) — never the old version.
        foreach ($result['restored'] as $restored) {
            $row = DB::table('time_slots')->where('id', $restored['slot_id'])->first();
            $this->assertSame('occupied', $row->status);
            $this->assertSame((int) $this->session->id, (int) $row->class_session_id);
            $this->assertSame($restored['after_version'], (int) $row->version);
            $this->assertGreaterThan($restored['before_version'], $restored['after_version']);
        }

        // Cancellation history untouched: the recovery does not UNDO the class.
        $this->assertSame($exceptionCountBefore, DB::table('class_exceptions')->count());
        $this->assertSame($auditCountBefore, DB::table('audit_logs')->count());
        $this->assertSame(1, DB::table('class_exceptions')->where('class_session_id', $this->session->id)->count());
    }

    public function test_recovery_refuses_drifted_or_reused_cells_without_partial_restore(): void
    {
        $cells = $this->originalCells($this->session, self::WEEK);
        $this->cancelOccurrence($this->session, self::WEEK);

        $planPath = $this->tempDir.'/plan.json';
        $this->artisan('classes:release-cancelled-slots', ['--plan-output' => $planPath])->assertExitCode(0);
        $this->approvePlanFile($planPath, 'RECOVER-2');
        $snap = $this->snapshotFiles();

        $this->artisan('classes:release-cancelled-slots', $this->applyOptions($planPath, $snap, 'RECOVER-2'))->assertExitCode(0);

        $recovery = new CancelledSlotCleanupRecovery;

        // Case 1 — a receipt cell's version drifted since the release.
        DB::table('time_slots')->where('id', $cells[0]->id)->update(['version' => 99]);
        $verdict = $recovery->check($this->maintenanceReceiptCells());
        $this->assertSame('refused_no_partial_restore', $verdict['verdict']);
        $this->assertContains($cells[0]->id.':version_drifted', $verdict['refusals']);
        $this->assertSame([], $recovery->rehearse($this->maintenanceReceiptCells())['restored']);
        $this->assertSame('available', (string) DB::table('time_slots')->where('id', $cells[1]->id)->first()->status);
        DB::table('time_slots')->where('id', $cells[0]->id)->update(['version' => 2]);

        // Case 2 — a receipt cell was REUSED by a new live junction claim
        // while still sitting EXACTLY as the receipt recorded it (available,
        // owner NULL, recorded post-version): this isolates the junction
        // guard instead of tripping the earlier not-available guard.
        $this->requestHolding($this->session, [$cells[1]->id]);
        $verdict = $recovery->check($this->maintenanceReceiptCells());
        $this->assertSame('refused_no_partial_restore', $verdict['verdict']);
        $this->assertSame('live_junction_claim', $this->cellReason($verdict, $cells[1]->id));
        $this->assertSame([], $recovery->rehearse($this->maintenanceReceiptCells())['restored']);
        $this->assertSame('available', (string) DB::table('time_slots')->where('id', $cells[0]->id)->first()->status);
        DB::table('replacement_request_slots')->delete();
        DB::table('replacement_requests')->whereIn('class_session_id', [$this->session->id])->delete();

        // Case 3 — a receipt cell was re-occupied by another session: the
        // FIRST guard (availability) rightly wins — an occupied cell is
        // not_available before ownership is even read, so owner_changed is
        // NOT the reason here.
        $otherSession = $this->makeSession(['start_time' => '12:00:00', 'end_time' => '13:00:00']);
        DB::table('time_slots')->where('id', $cells[2]->id)->update(['status' => 'occupied', 'class_session_id' => $otherSession->id]);
        $verdict = $recovery->check($this->maintenanceReceiptCells());
        $this->assertSame('refused_no_partial_restore', $verdict['verdict']);
        $this->assertContains($cells[2]->id.':not_available', $verdict['refusals']);
        $this->assertSame('not_available', $this->cellReason($verdict, $cells[2]->id));
        $this->assertSame([], $recovery->rehearse($this->maintenanceReceiptCells())['restored']);
        $this->assertSame('available', (string) DB::table('time_slots')->where('id', $cells[3]->id)->first()->status);

        // Case 4 — a receipt cell sits EXACTLY as the receipt recorded it
        // (available, recorded post-version untouched) but with a WRONG
        // owner: this isolates the owner guard instead of tripping the
        // earlier not_available guard, and still restores nothing.
        DB::table('time_slots')->where('id', $cells[3]->id)->update(['status' => 'available', 'class_session_id' => $otherSession->id]);
        $verdict = $recovery->check($this->maintenanceReceiptCells());
        $this->assertSame('refused_no_partial_restore', $verdict['verdict']);
        $this->assertSame('owner_changed', $this->cellReason($verdict, $cells[3]->id));
        $this->assertSame([], $recovery->rehearse($this->maintenanceReceiptCells())['restored']);
        $this->assertSame('available', (string) DB::table('time_slots')->where('id', $cells[0]->id)->first()->status);
    }

    /** The refusal reason the assessment recorded for one receipt cell. */
    private function cellReason(array $verdict, int $slotId): ?string
    {
        foreach ($verdict['cells'] as $cell) {
            if ($cell['slot_id'] === $slotId) {
                return $cell['reason'];
            }
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function makeSession(array $overrides = []): ClassSession
    {
        return ClassSession::create(array_merge([
            'semester_id' => $this->semester->id,
            'module_id' => $this->module->id,
            'lecturer_id' => $this->lecturer->id,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue_id' => $this->venue->id,
            'session_type' => 'L',
        ], $overrides));
    }

    private function cell(ClassSession $session, string $start, int $week = self::WEEK, array $overrides = []): TimeSlot
    {
        return TimeSlot::create(array_merge([
            'semester_id' => (int) $session->semester_id,
            'class_session_id' => (int) $session->id,
            'week_number' => $week,
            'day_of_week' => (int) $session->day_of_week,
            'start_time' => $start,
            'end_time' => date('H:i:s', strtotime($start) + 1800),
            'venue_id' => (int) $session->venue_id,
            'status' => 'occupied',
            'version' => 1,
        ], $overrides));
    }

    /**
     * The session's whole expected cell coverage at its own recurring
     * coordinates (one 30-minute row per half hour of the range).
     *
     * @return list<TimeSlot>
     */
    private function originalCells(ClassSession $session, int $week): array
    {
        $start = strtotime((string) $session->start_time);
        $minutes = (int) round((strtotime((string) $session->end_time) - $start) / 60);
        $cells = [];

        for ($index = 0; $index < $minutes / 30; $index++) {
            $cells[] = $this->cell($session, date('H:i:s', $start + $index * 1800), $week, ['version' => $index + 1]);
        }

        return $cells;
    }

    /**
     * A request whose LIVE receipt is the given slot ids (junction rows).
     *
     * @param  list<int>  $slotIds
     * @param  array<string, mixed>  $overrides
     */
    private function requestHolding(ClassSession $source, array $slotIds, array $overrides = []): ReplacementRequest
    {
        $request = ReplacementRequest::create(array_merge([
            'semester_id' => (int) $source->semester_id,
            'proposer_id' => (int) $source->lecturer_id,
            'class_session_id' => (int) $source->id,
            'week_number' => self::WEEK,
            'replacement_time_slot_id' => $slotIds[0],
            'replacement_span' => count($slotIds),
            'status' => 'pending',
            'submitted_at' => now(),
        ], $overrides));

        foreach ($slotIds as $slotId) {
            ReplacementRequestSlot::create([
                'replacement_request_id' => $request->id,
                'time_slot_id' => $slotId,
            ]);
        }

        return $request;
    }

    /** The user cancellation evidence: a CURRENT exception + a non-maintenance class_cancelled audit. */
    private function cancelOccurrence(ClassSession $session, int $week, string $reason = 'medical_leave'): void
    {
        ClassException::create([
            'class_session_id' => $session->id,
            'week_number' => $week,
            'reason' => $reason,
        ]);

        AuditLog::create([
            'user_id' => $this->actor->id,
            'action' => 'class_cancelled',
            'details' => [
                'class_session_id' => (int) $session->id,
                'week_number' => $week,
                'reason' => $reason,
            ],
        ]);
    }

    /** The `changed` entries of the rehearsal's maintenance receipt. */
    private function maintenanceReceiptCells(): array
    {
        $audit = AuditLog::query()
            ->where('action', 'class_cancelled')
            ->get()
            ->first(fn (AuditLog $audit): bool => ($audit->details['maintenance'] ?? false) === true);

        $this->assertNotNull($audit, 'the rehearsal apply must have written its maintenance receipt');

        return $audit->details['original_release']['changed'];
    }

    /** @return array{dump: string, manifest: string} */
    private function snapshotFiles(): array
    {
        $dump = $this->tempDir.'/snapshot-'.uniqid().'.dump';
        file_put_contents($dump, 'PGDMP-fixture-'.uniqid()."\n");

        $receipt = $this->tempDir.'/restore-list-'.uniqid().'.txt';
        file_put_contents($receipt, 'pg_restore --list receipt for '.$dump."\n");

        $manifest = $this->tempDir.'/manifest-'.uniqid().'.json';
        file_put_contents($manifest, (string) json_encode([
            'dump_path' => $dump,
            'dump_sha256' => hash_file('sha256', $dump),
            'restore_list_path' => $receipt,
            'restore_list_sha256' => hash_file('sha256', $receipt),
            'target_database' => 'class_replacement_testing',
            'captured_at' => '2026-11-13T09:00:00+08:00',
        ], JSON_PRETTY_PRINT));

        return ['dump' => $dump, 'manifest' => $manifest];
    }

    private function approvePlanFile(string $path, string $reference = 'REHEARSAL-1', string $operator = 'operator-main'): void
    {
        $file = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $file['approval'] = [
            'reference' => $reference,
            'operator' => $operator,
            'plan_fingerprint' => $file['fingerprint'],
            'decided_at' => '2026-11-13T09:00:00+08:00',
        ];
        file_put_contents($path, (string) json_encode($file, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, string|bool> */
    private function applyOptions(string $planPath, array $snap, string $reference): array
    {
        return [
            '--apply' => true,
            '--confirm-database' => 'class_replacement_testing',
            '--plan' => $planPath,
            '--snapshot' => $snap['dump'],
            '--snapshot-manifest' => $snap['manifest'],
            '--approval-reference' => $reference,
            '--approval-operator' => 'operator-main',
            '--operation-id' => 'cleanup-'.strtolower($reference),
            '--actor-user-id' => (string) $this->actor->id,
        ];
    }
}
