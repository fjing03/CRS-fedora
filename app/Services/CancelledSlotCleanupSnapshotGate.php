<?php

namespace App\Services;

/**
 * The snapshot verification gate of the cleanup facility (SDD
 * cancelled-slot-reuse, design D9, spec S27) — filesystem-only, no shell,
 * no external backup commands from code.
 *
 * It verifies the operator's manifest against the files it names:
 * the dump's SHA-256, the captured `pg_restore --list` receipt's SHA-256,
 * and the manifest's target database against the live `current_database()`.
 *
 * WHAT IT CANNOT VERIFY: that the receipt was genuinely produced by
 * `pg_restore --list` FROM this dump. That provenance is the documented
 * OPERATOR gate — the runbook's recorded checklist (operator runs
 * `pg_dump -Fc` + `pg_restore --list` themselves, hashes both, writes the
 * manifest). Hash equality proves the files are the ones the manifest
 * describes; it must never be presented as proof the dump is restorable.
 * The command prints that boundary on every apply.
 */
final class CancelledSlotCleanupSnapshotGate
{
    /**
     * @return array{ok: bool, reason: string|null, manifest: array<string, mixed>|null}
     */
    public function verify(string $manifestPath, string $dumpPath, string $actualDatabase): array
    {
        $manifest = $this->readManifest($manifestPath);
        $data = $manifest['data'];

        if ($data === null) {
            return ['ok' => false, 'reason' => $manifest['reason'] ?? 'manifest_invalid', 'manifest' => null];
        }

        $required = ['dump_path', 'dump_sha256', 'restore_list_path', 'restore_list_sha256', 'target_database'];

        foreach ($required as $key) {
            if (! is_string($data[$key] ?? null) || ((string) $data[$key]) === '') {
                return ['ok' => false, 'reason' => 'manifest_missing_key:'.$key, 'manifest' => null];
            }
        }

        if ((string) $data['dump_path'] !== $dumpPath) {
            return ['ok' => false, 'reason' => 'snapshot_path_mismatch:manifest names '.(string) $data['dump_path'], 'manifest' => $data];
        }

        $actualDumpHash = @hash_file('sha256', $dumpPath);

        if ($actualDumpHash === false) {
            return ['ok' => false, 'reason' => 'snapshot_unreadable:'.$dumpPath, 'manifest' => $data];
        }

        if (! hash_equals((string) $data['dump_sha256'], $actualDumpHash)) {
            return ['ok' => false, 'reason' => 'snapshot_checksum_mismatch', 'manifest' => $data];
        }

        $restoreListPath = (string) $data['restore_list_path'];
        $actualReceiptHash = @hash_file('sha256', $restoreListPath);

        if ($actualReceiptHash === false) {
            return ['ok' => false, 'reason' => 'restore_list_receipt_unreadable:'.$restoreListPath, 'manifest' => $data];
        }

        if (! hash_equals((string) $data['restore_list_sha256'], $actualReceiptHash)) {
            return ['ok' => false, 'reason' => 'restore_list_receipt_checksum_mismatch', 'manifest' => $data];
        }

        if ((string) $data['target_database'] !== $actualDatabase) {
            return ['ok' => false, 'reason' => 'snapshot_target_mismatch:manifest names '.(string) $data['target_database'], 'manifest' => $data];
        }

        return ['ok' => true, 'reason' => null, 'manifest' => $data];
    }

    /**
     * @return array{data: array<string, mixed>|null, reason: string|null} exactly one of the two is set
     */
    private function readManifest(string $manifestPath): array
    {
        $raw = @file_get_contents($manifestPath);

        if ($raw === false) {
            return ['data' => null, 'reason' => 'manifest_unreadable:'.$manifestPath];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return ['data' => null, 'reason' => 'manifest_invalid_json'];
        }

        if (! is_array($decoded)) {
            return ['data' => null, 'reason' => 'manifest_invalid_json'];
        }

        return ['data' => $decoded, 'reason' => null];
    }
}
