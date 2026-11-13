<?php

namespace App\Services;

use JsonException;

/**
 * The deterministic fingerprint of ONE scoped cleanup plan (SDD
 * cancelled-slot-reuse, design D9, spec S26/S28).
 *
 * Law: the fingerprint covers EVERY scoped ownership fact of the inventory —
 * source session rows, cancellation evidence (audits + current exceptions),
 * exact slot rows (duplicates and out-of-range rows included, with their
 * versions and owners), active requests with their full junction membership
 * and anchor/span fallback facts, and every consulted audit row. It never
 * includes the current clock, wall time or any drifting fact — only stored,
 * re-readable database state, so a re-measure inside the apply transaction
 * can be compared byte-for-byte with the approved plan.
 *
 * Canonical form: compact JSON with recursively sorted object keys and
 * list order preserved (every list in the inventory is sorted by
 * construction), SHA-256 over the UTF-8 bytes. Facts carry only scalars —
 * no floats, no Carbon objects — so encoding is stable across runs.
 */
final class CancelledSlotCleanupFingerprint
{
    /** The algorithm tag embedded in every plan document and fingerprint input. */
    public const ALGORITHM = 'crs-cancelled-slot-cleanup-fingerprint-v1';

    /** The plan-document keys the fingerprint covers (approval/plan metadata never enters the hash). */
    private const DOCUMENT_KEYS = ['algorithm', 'current_database', 'occurrences'];

    /** SHA-256 over the canonical JSON of any value. */
    public static function hash(mixed $value): string
    {
        return hash('sha256', self::canonicalJson($value));
    }

    /**
     * Hash a plan document's fingerprintable portion: exactly the
     * {@see DOCUMENT_KEYS} of the given document, nothing else.
     *
     * @param  array<mixed>  $document  the decoded plan-file document as produced by `json_decode` — its key types are not analyser-trackable, so only the DOCUMENT_KEYS are read and everything else is ignored
     */
    public static function hashDocument(array $document): string
    {
        $covered = [];

        foreach (self::DOCUMENT_KEYS as $key) {
            $covered[$key] = $document[$key] ?? null;
        }

        return self::hash($covered);
    }

    /** Compact canonical JSON: object keys recursively sorted, list order preserved. */
    public static function canonicalJson(mixed $value): string
    {
        try {
            return (string) json_encode(
                self::canonicalize($value),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            throw new \RuntimeException('Cancelled-slot cleanup fingerprint could not encode the plan: '.$e->getMessage(), 0, $e);
        }
    }

    private static function canonicalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                $list = [];

                foreach ($value as $item) {
                    $list[] = self::canonicalize($item);
                }

                return $list;
            }

            $keys = array_keys($value);
            sort($keys, SORT_STRING);

            $map = [];

            foreach ($keys as $key) {
                $map[$key] = self::canonicalize($value[$key]);
            }

            return $map;
        }

        if (is_object($value)) {
            return self::canonicalize((array) $value);
        }

        return $value;
    }
}
