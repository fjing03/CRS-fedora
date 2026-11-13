<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CancelledSlotCleanupApplier;
use App\Services\CancelledSlotCleanupFingerprint;
use App\Services\CancelledSlotCleanupInventory;
use App\Services\CancelledSlotCleanupPlan;
use App\Services\CancelledSlotCleanupSnapshotGate;
use App\Services\CancelledSlotCleanupTargetPolicy;
use App\Services\OriginalOccurrencePlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * classes:release-cancelled-slots — the guarded existing-data cleanup
 * facility of SDD cancelled-slot-reuse (design D9, specs S26–S29).
 *
 * DEFAULT = read-only dry-run: SELECTs only, no audit/notification/write of
 * any kind. It inventories the real cancellation pairs (a non-maintenance
 * `class_cancelled` audit + the matching CURRENT exception per exact
 * session/week), classifies every expected 30-minute cell through the shared
 * original-occurrence planner, and prints (optionally writes) a fingerprinted
 * plan: eligible originals with their exact before→after fields, protected
 * and available no-ops, and whole-occurrence exclusions for anything missing
 * or ambiguous.
 *
 * APPLY = explicit `--apply` + `--confirm-database` (exact current_database()
 * identity — live allowlist is `class_replacement_fresh` ONLY; rehearsals on
 * `class_replacement_testing`) + `--plan` (the approved fingerprinted plan
 * file, carrying the approval block) + `--snapshot`/`--snapshot-manifest`
 * (verified `pg_dump -Fc` + `pg_restore --list` receipts) + the separate
 * approval reference/operator + the operation id and audit actor. Every gate
 * fails closed; inside one transaction the scope is locked, re-measured and
 * fingerprint-compared — ANY drift aborts the whole plan before a write.
 * Zero notifications; one legal maintenance audit per released occurrence.
 *
 * `--env=testing` is FORBIDDEN (no `.env.testing` exists — it would silently
 * boot the demo `.env`); select the testing database only with the
 * `DB_DATABASE=class_replacement_testing` environment variable. There is no
 * scheduled invocation and no controller/render caller; recovery is a
 * separately approved operator operation and is deliberately NOT exposed
 * here.
 */
final class ReleaseCancelledClassSlots extends Command
{
    protected $signature = 'classes:release-cancelled-slots
                            {--plan-output= : dry-run only — write the fingerprinted plan JSON to this path}
                            {--apply : perform the release (default is a read-only dry-run)}
                            {--confirm-database= : the exact current_database() identity this run may touch}
                            {--plan= : the APPROVED plan JSON file carrying the approval block}
                            {--snapshot= : the verified pg_dump -Fc dump path}
                            {--snapshot-manifest= : the snapshot verification manifest JSON}
                            {--approval-reference= : the separate write approval reference}
                            {--approval-operator= : the approving operator recorded with the approval}
                            {--operation-id= : the operation id recorded in the maintenance audits}
                            {--actor-user-id= : an EXISTING user id recorded on the maintenance audits}';

    protected $description = 'Inventory (default, read-only) or release (explicit --apply) the original cells of already-cancelled class occurrences';

    /** The apply options that must ALL be present before anything runs. */
    private const APPLY_REQUIRED = [
        'confirm-database',
        'plan',
        'snapshot',
        'snapshot-manifest',
        'approval-reference',
        'approval-operator',
        'operation-id',
        'actor-user-id',
    ];

    public function handle(): int
    {
        // The --env=testing trap (no `.env.testing` exists): Laravel's
        // LoadEnvironmentVariables only switches the environment file when
        // `.env.<value>` EXISTS — the forbidden flag therefore silently
        // keeps the demo `.env`, so inspect the RAW global --env flag
        // BEFORE anything else (this refuses before current_database(),
        // before the inventory and before any write), and keep the
        // environment-file-name guard beneath it.
        if (self::requestedEnv(new ArgvInput) === 'testing'
            || app()->environmentFile() === '.env.testing') {
            $this->error('--env=testing is forbidden (no .env.testing exists — it would silently boot the demo .env).'
                .' Select the testing database only with DB_DATABASE=class_replacement_testing.');

            return Command::FAILURE;
        }

        $actualDatabase = $this->currentDatabase();

        $this->line('Target database (current_database()): '.$actualDatabase);

        if (! (bool) $this->option('apply')) {
            return $this->dryRun($actualDatabase);
        }

        return $this->apply($actualDatabase);
    }

    // ------------------------------------------------------------------
    // Dry run
    // ------------------------------------------------------------------

    private function dryRun(string $actualDatabase): int
    {
        $this->line('Mode: DRY RUN — SELECT only. No audit, notification, slot or exception write happens in this mode.');

        $plan = (new CancelledSlotCleanupInventory)->build();
        $this->renderPlan($plan);

        $outputPath = $this->option('plan-output');

        if (is_string($outputPath) && $outputPath !== '') {
            $document = $plan->document;
            $document['generated_at'] = now()->toISOString(); // informational — excluded from the fingerprint by law
            $document['fingerprint'] = $plan->fingerprint;

            if (@file_put_contents($outputPath, (string) json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
                $this->error('Could not write the plan file to '.$outputPath);

                return Command::FAILURE;
            }

            $this->line('Plan file written: '.$outputPath);
        }

        $this->line('Apply requires, ALL together: --apply, --confirm-database (exact identity above), the APPROVED plan file'
            .' (--plan, with its approval block), a verified snapshot (--snapshot + --snapshot-manifest), and the separate'
            .' approval (--approval-reference/--approval-operator). Planning approval does NOT authorise this write.');

        return Command::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Apply
    // ------------------------------------------------------------------

    private function apply(string $actualDatabase): int
    {
        $missing = [];

        foreach (self::APPLY_REQUIRED as $option) {
            $value = $this->option($option);

            if (! is_string($value) || trim($value) === '') {
                $missing[] = '--'.$option;
            }
        }

        if ($missing !== []) {
            $this->error('Apply refused — missing required gates: '.implode(', ', $missing));

            return Command::FAILURE;
        }

        $confirmed = (string) $this->option('confirm-database');

        $refusal = CancelledSlotCleanupTargetPolicy::check($confirmed, $actualDatabase);

        if ($refusal !== null) {
            $this->error('Apply refused — target gate: '.$refusal
                .' (live allowlist: '.CancelledSlotCleanupTargetPolicy::LIVE_TARGET.'; testing rehearsals: '
                .CancelledSlotCleanupTargetPolicy::REHEARSAL_TARGET.' only).');

            return Command::FAILURE;
        }

        $snapshotGate = new CancelledSlotCleanupSnapshotGate;
        $snapshot = $snapshotGate->verify(
            (string) $this->option('snapshot-manifest'),
            (string) $this->option('snapshot'),
            $actualDatabase,
        );

        if (! $snapshot['ok']) {
            $this->error('Apply refused — snapshot gate: '.$snapshot['reason']);

            return Command::FAILURE;
        }

        $this->line('Snapshot verified: dump checksum + restore-list receipt checksum match the manifest; manifest target = '
            .$actualDatabase.' = live target. REMINDER: hash verification is NOT provenance — the pg_restore --list receipt'
            .' must have been produced by the operator from THIS dump (recorded checklist).');

        $plan = $this->loadApprovedPlan((string) $this->option('plan'), $actualDatabase);

        if ($plan === null) {
            return Command::FAILURE;
        }

        $this->renderPlan($plan);

        $this->line('Applying the approved plan inside ONE transaction; any drift in the scoped ownership facts aborts the'
            .' ENTIRE plan before a write and requires a fresh dry-run + new approval. An approved plan and a verified'
            .' snapshot are NOT, by themselves, the write approval.');

        $result = (new CancelledSlotCleanupApplier)->apply($plan, [
            'operation_id' => (string) $this->option('operation-id'),
            'actor_user_id' => (int) $this->option('actor-user-id'),
            'approval_reference' => (string) $this->option('approval-reference'),
            'approval_operator' => (string) $this->option('approval-operator'),
            'expected_target' => $actualDatabase,
        ]);

        if (! $result->applied) {
            $this->error('Apply ABORTED before any write: '.$result->abortedReason
                .' — run a fresh dry-run and obtain a new approval.');

            return Command::FAILURE;
        }

        $this->line('Operation id: '.(string) $this->option('operation-id'));
        $this->line('Changed cells: '.$result->changedCellCount().'; maintenance audits written: '.$result->auditsWritten()
            .'; notifications: 0.');

        foreach ($result->occurrences as $occurrence) {
            $this->line('  session '.$occurrence['class_session_id'].' week '.$occurrence['week_number']
                .' — changed '.count($occurrence['changed']).', protected/available no-ops '.count($occurrence['no_ops'])
                .', audit '.($occurrence['audit_id'] ?? 'none (zero-change)').'.');
        }

        if ($result->verification !== []) {
            $this->error('POST-COMMIT VERIFICATION FINDINGS (the write already committed — investigate, do not re-apply):');

            foreach ($result->verification as $finding) {
                $this->error('  - '.$finding);
            }

            return Command::FAILURE;
        }

        $this->line('Post-commit verification: clean (changed cells, protected fields/links/versions, exceptions, recurring'
            .' schedules and other weeks all exact).');

        $this->line('NEXT (operator, not this command): append ONLY this operation\'s receipt to docs/demo-db-data-changes.md;'
            .' a repeat dry-run should propose zero further changes; rollback/recovery is a SEPARATELY APPROVED operation.');

        return Command::SUCCESS;
    }

    /**
     * Load + validate the approved plan file: structure, target binding,
     * fingerprint self-consistency and the approval block's binding of
     * reference/operator/fingerprint (fail closed on any mismatch).
     */
    private function loadApprovedPlan(string $path, string $actualDatabase): ?CancelledSlotCleanupPlan
    {
        $raw = @file_get_contents($path);

        if ($raw === false) {
            $this->error('Apply refused — plan file unreadable: '.$path);

            return null;
        }

        try {
            $file = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->error('Apply refused — plan file is not valid JSON: '.$path);

            return null;
        }

        if (! is_array($file)) {
            $this->error('Apply refused — plan file is not a JSON object: '.$path);

            return null;
        }

        $algorithm = $file['algorithm'] ?? null;
        $planDatabase = $file['current_database'] ?? null;
        $recordedFingerprint = $file['fingerprint'] ?? null;
        $planOccurrences = $file['occurrences'] ?? null;

        if (! is_string($algorithm)
            || ! is_string($planDatabase)
            || ! is_string($recordedFingerprint)
            || ! is_array($planOccurrences)) {
            $this->error('Apply refused — plan file structure invalid (algorithm/current_database/occurrences/fingerprint required).');

            return null;
        }

        foreach ($planOccurrences as $planOccurrence) {
            if (! is_array($planOccurrence)) {
                $this->error('Apply refused — plan file structure invalid (occurrences must be a list of occurrence objects).');

                return null;
            }
        }

        $computed = CancelledSlotCleanupFingerprint::hashDocument($file);

        if (! hash_equals($recordedFingerprint, $computed)) {
            $this->error('Apply refused — plan fingerprint mismatch (the file\'s contents do not hash to its recorded fingerprint).');

            return null;
        }

        if ($planDatabase !== $actualDatabase) {
            $this->error('Apply refused — the approved plan was fingerprinted for "'.$planDatabase
                .'", not this target "'.$actualDatabase.'".');

            return null;
        }

        $approval = $file['approval'] ?? null;

        if (! is_array($approval)) {
            $this->error('Apply refused — the plan file carries no approval block.');

            return null;
        }

        $reference = (string) $this->option('approval-reference');
        $operator = (string) $this->option('approval-operator');

        $approvalReference = array_key_exists('reference', $approval) ? $approval['reference'] : null;
        $approvalOperator = array_key_exists('operator', $approval) ? $approval['operator'] : null;
        $approvalFingerprint = array_key_exists('plan_fingerprint', $approval) ? $approval['plan_fingerprint'] : null;

        if (! is_string($approvalReference) || $approvalReference !== $reference) {
            $this->error('Apply refused — approval reference does not match the plan file\'s approval block.');

            return null;
        }

        if (! is_string($approvalOperator) || $approvalOperator !== $operator) {
            $this->error('Apply refused — approval operator does not match the plan file\'s approval block.');

            return null;
        }

        if ($approvalFingerprint !== $computed) {
            $this->error('Apply refused — the approval block does not bind this plan\'s fingerprint.');

            return null;
        }

        $actor = User::query()->find((int) $this->option('actor-user-id'));

        if ($actor === null) {
            $this->error('Apply refused — --actor-user-id does not name an existing user.');

            return null;
        }

        try {
            return CancelledSlotCleanupPlan::fromDocument($file, $recordedFingerprint);
        } catch (RuntimeException $e) {
            $this->error('Apply refused — plan file structure invalid: '.$e->getMessage());

            return null;
        }
    }

    // ------------------------------------------------------------------
    // Reporting
    // ------------------------------------------------------------------

    private function renderPlan(CancelledSlotCleanupPlan $plan): void
    {
        $included = count($plan->included());
        $total = count($plan->occurrences);

        $this->line('Cancellation pairs: '.$total.' — included '.$included.', excluded '.($total - $included).'.');

        if ($total === 0) {
            $this->line('No cancellation pairs found (non-maintenance class_cancelled audit + current exception). Nothing to do.');

            return;
        }

        foreach ($plan->occurrences as $occurrence) {
            $pair = $occurrence->pair;

            $this->line('  session '.$pair->classSessionId.' week '.$pair->weekNumber
                .' — semester '.$pair->semesterId.', reason "'.$pair->reason
                .'", audits ['.implode(',', $pair->auditIds).']'
                .($pair->undoAuditIds !== [] ? ', UNDO audits ['.implode(',', $pair->undoAuditIds).']' : '')
                .', exception '.($pair->exceptionId ?? 'none').($pair->exceptionReason !== null ? ' ('.$pair->exceptionReason.')' : ''));

            $planObject = $occurrence->plan;

            if ($planObject === null) {
                $this->line('    status: EXCLUDED — missing_session');

                continue;
            }

            $changed = $planObject->releaseEligibleSlotIds();

            $this->line('    status: '.(! $occurrence->included
                ? 'EXCLUDED — '.($occurrence->exclusionReason ?? 'unknown')
                : ($changed === []
                    ? 'INCLUDED (zero-change — protected/available no-ops only)'
                    : 'INCLUDED — '.count($changed).' original cell(s) to release')));

            foreach ($planObject->entries as $entry) {
                $projection = ! $occurrence->included
                    ? '→ occurrence excluded; cell evidence: '.$entry->classification.($entry->reason !== null ? ' ('.$entry->reason.')' : '')
                    : match ($entry->classification) {
                        OriginalOccurrencePlan::ORIGINAL => '→ release: available, owner NULL, version '.((int) $entry->version + 1),
                        OriginalOccurrencePlan::AVAILABLE => '→ NO-OP already available (duplicate rows: '.implode(',', $entry->duplicateSlotIds).')',
                        OriginalOccurrencePlan::REQUEST_HELD => '→ PROTECTED (request '.$entry->heldByRequestId.')',
                        default => '→ refusal: '.($entry->reason ?? 'unexplained'),
                    };

                $this->line('      '.$entry->startTime.'–'.$entry->endTime.' slot '.$entry->slotId
                    .' ['.$entry->classification.'] occ v'.$entry->version.' owner '.$entry->ownerSessionId.' '.$projection);
            }

            if ($planObject->outOfRange !== []) {
                $ids = implode(',', array_map(fn (array $row): int => (int) $row['slot_id'], $planObject->outOfRange));
                $this->line('      out-of-range rows carrying the source id (reported, never freed): ['.$ids.']');
            }
        }

        $this->line('Fingerprint: sha256:'.$plan->fingerprint);
    }

    private function currentDatabase(): string
    {
        $row = DB::selectOne('SELECT current_database() AS db');
        $name = is_object($row) ? ($row->db ?? null) : null;

        if ($name === null) {
            throw new RuntimeException('Could not read current_database().');
        }

        return (string) $name;
    }

    /**
     * The raw global `--env` value of the console input, or null when the
     * flag is absent or carries no value (PURE token inspection — nothing
     * bootstrapped, no database touched). Laravel's
     * LoadEnvironmentVariables::setEnvironmentFilePath() only switches the
     * environment file when `.env.<value>` EXISTS: with no `.env.testing`
     * file in this repo, the forbidden `--env=testing` flag silently falls
     * back to the demo `.env`, so the environment file NAME alone can never
     * prove the flag absent. Both raw inputs are unit-testable.
     */
    public static function requestedEnv(ArgvInput $input): ?string
    {
        if (! $input->hasParameterOption('--env')) {
            return null;
        }

        $value = $input->getParameterOption('--env', null);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
