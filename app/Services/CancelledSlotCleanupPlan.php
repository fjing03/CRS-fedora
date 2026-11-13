<?php

namespace App\Services;

/**
 * The typed, fingerprinted plan of the WHOLE cleanup inventory (SDD
 * cancelled-slot-reuse, design D9, spec S26/S28).
 *
 * `document` is the fingerprintable payload exactly as it lands in the plan
 * file (keys: algorithm, current_database, occurrences) — the approved plan
 * file carries this plus its fingerprint and the operator's approval block.
 * The applier re-runs the inventory inside its transaction and compares
 * fingerprints byte-for-byte: ANY drift aborts the whole plan.
 */
final class CancelledSlotCleanupPlan
{
    /**
     * @param  list<CancelledSlotCleanupOccurrence>  $occurrences  sorted by session id then week
     * @param  array<mixed>  $document  the decoded plan-file document as produced by `json_decode` (string keys at runtime; key types are not analyser-trackable) — shape: {algorithm: string, current_database: string, occurrences: list<array<string, mixed>>}
     */
    public function __construct(
        public readonly array $occurrences,
        public readonly string $currentDatabase,
        public readonly string $fingerprint,
        public readonly array $document,
    ) {}

    /** @return list<CancelledSlotCleanupOccurrence> */
    public function included(): array
    {
        return array_values(array_filter(
            $this->occurrences,
            fn (CancelledSlotCleanupOccurrence $occurrence): bool => $occurrence->included,
        ));
    }

    /** @return list<string> every occurrence key, in document order */
    public function keys(): array
    {
        return array_map(fn (CancelledSlotCleanupOccurrence $occurrence): string => $occurrence->key(), $this->occurrences);
    }

    public function hasKey(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    public function occurrence(string $key): ?CancelledSlotCleanupOccurrence
    {
        foreach ($this->occurrences as $occurrence) {
            if ($occurrence->key() === $key) {
                return $occurrence;
            }
        }

        return null;
    }

    /**
     * Every scoped slot id across all occurrences (expected rows, duplicates,
     * out-of-range rows, receipt runs, fallback anchors) — the apply
     * transaction's row-lock union, ascending and unique.
     *
     * @return list<int>
     */
    public function candidateSlotIds(): array
    {
        // `??` already handles the null plan (isset semantics); the fallback
        // stays because a missing plan still contributes no ids.
        return $this->unionIds(fn (CancelledSlotCleanupOccurrence $occurrence): array => $occurrence->plan->candidateSlotIds ?? []);
    }

    /**
     * Every active request the classification consulted across all
     * occurrences (claims, fallback candidates) — locked ascending before
     * the apply re-plan.
     *
     * @return list<int>
     */
    public function consultedRequestIds(): array
    {
        return $this->unionIds(fn (CancelledSlotCleanupOccurrence $occurrence): array => $occurrence->plan->claimingRequestIds ?? []);
    }

    /** Every pair's source session id, ascending and unique — the advisory-lock keys.
     *
     * @return list<int>
     */
    public function sessionIds(): array
    {
        return $this->unionIds(fn (CancelledSlotCleanupOccurrence $occurrence): array => [$occurrence->pair->classSessionId]);
    }

    /**
     * Rebuild a plan from a plan file's stored document + recorded
     * fingerprint (the apply side). The caller has already verified that the
     * document hashes to that fingerprint; the applier then compares a FRESH
     * inventory's fingerprint against the recorded one.
     *
     * @param  array<mixed>  $document  the decoded plan-file document (string keys at runtime; every field is validated/normalised inside this method) — shape: {algorithm, current_database, occurrences: [{class_session_id, semester_id, week_number, day_of_week, venue_id, included, exclusion_reason, evidence{...}, plan{...}, facts{...}}]}
     */
    public static function fromDocument(array $document, string $fingerprint): self
    {
        $occurrences = [];

        foreach (is_array($document['occurrences'] ?? null) ? $document['occurrences'] : [] as $occurrenceDocument) {
            if (! is_array($occurrenceDocument)) {
                continue;
            }

            $evidence = is_array($occurrenceDocument['evidence'] ?? null) ? $occurrenceDocument['evidence'] : [];
            $planDocument = is_array($occurrenceDocument['plan'] ?? null) ? $occurrenceDocument['plan'] : [];

            $pair = new CancelledSlotCleanupPair(
                (int) $occurrenceDocument['class_session_id'],
                (int) $occurrenceDocument['semester_id'],
                (int) $occurrenceDocument['week_number'],
                (string) ($evidence['audit_reason'] ?? ''),
                self::intList($evidence['audit_ids'] ?? []),
                self::intList($evidence['undo_audit_ids'] ?? []),
                self::intOrNull($evidence['exception_id'] ?? null),
                self::strOrNull($evidence['exception_reason'] ?? null),
                self::strOrNull($evidence['contradiction'] ?? null),
            );

            $entries = [];

            foreach (is_array($planDocument['entries'] ?? null) ? $planDocument['entries'] : [] as $entryDocument) {
                if (! is_array($entryDocument)) {
                    continue;
                }

                $entries[] = new OriginalOccurrenceEntry(
                    (string) $entryDocument['start_time'],
                    (string) $entryDocument['end_time'],
                    (string) $entryDocument['classification'],
                    self::intOrNull($entryDocument['slot_id'] ?? null),
                    self::strOrNull($entryDocument['status'] ?? null),
                    self::intOrNull($entryDocument['owner_session_id'] ?? null),
                    self::intOrNull($entryDocument['version'] ?? null),
                    self::intList($entryDocument['duplicate_slot_ids'] ?? []),
                    self::intOrNull($entryDocument['held_by_request_id'] ?? null),
                    self::strOrNull($entryDocument['reason'] ?? null),
                );
            }

            $outOfRange = [];

            foreach (is_array($planDocument['out_of_range'] ?? null) ? $planDocument['out_of_range'] : [] as $rowDocument) {
                $outOfRange[] = self::outOfRangeRow($rowDocument);
            }

            $plan = new OriginalOccurrencePlan(
                (int) $occurrenceDocument['class_session_id'],
                (int) $occurrenceDocument['semester_id'],
                (int) $occurrenceDocument['week_number'],
                (int) $occurrenceDocument['day_of_week'],
                (int) $occurrenceDocument['venue_id'],
                $entries,
                $outOfRange,
                self::intList($planDocument['candidate_slot_ids'] ?? []),
                self::intList($planDocument['claiming_request_ids'] ?? []),
                self::strOrNull($planDocument['refusal_reason'] ?? null),
            );

            $occurrences[] = new CancelledSlotCleanupOccurrence(
                $pair,
                $plan,
                (bool) $occurrenceDocument['included'],
                self::strOrNull($occurrenceDocument['exclusion_reason'] ?? null),
                is_array($occurrenceDocument['facts'] ?? null) ? $occurrenceDocument['facts'] : [],
            );
        }

        return new self(
            $occurrences,
            (string) $document['current_database'],
            $fingerprint,
            $document,
        );
    }

    /**
     * ONE out-of-range row in the planner's STRICT shape (reported, never
     * freed — display evidence only). Runtime field checks against the
     * untrusted plan-file JSON: every field is validated at its real type,
     * never cast into place, and a row that is not exactly the planner's
     * shape refuses the whole document (fail closed — the fingerprint
     * binds the raw document, but this DTO must stay byte-honest with
     * {@see OriginalOccurrenceReservation}.
     *
     * @return array{slot_id: int, semester_id: int, week_number: int, day_of_week: int, venue_id: int, start_time: string, end_time: string, status: string, version: int}
     */
    private static function outOfRangeRow(mixed $row): array
    {
        if (! is_array($row)
            || ! is_int($row['slot_id'] ?? null)
            || ! is_int($row['semester_id'] ?? null)
            || ! is_int($row['week_number'] ?? null)
            || ! is_int($row['day_of_week'] ?? null)
            || ! is_int($row['venue_id'] ?? null)
            || ! is_string($row['start_time'] ?? null)
            || ! is_string($row['end_time'] ?? null)
            || ! is_string($row['status'] ?? null)
            || ! is_int($row['version'] ?? null)) {
            throw new \RuntimeException('plan_file_malformed:out_of_range_row');
        }

        return [
            'slot_id' => $row['slot_id'],
            'semester_id' => $row['semester_id'],
            'week_number' => $row['week_number'],
            'day_of_week' => $row['day_of_week'],
            'venue_id' => $row['venue_id'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'status' => $row['status'],
            'version' => $row['version'],
        ];
    }

    /** @return list<int> */
    private static function intList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map('intval', $value));
    }

    private static function intOrNull(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private static function strOrNull(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    /**
     * @param  callable(CancelledSlotCleanupOccurrence): list<int>  $extract
     * @return list<int>
     */
    private function unionIds(callable $extract): array
    {
        $ids = [];

        foreach ($this->occurrences as $occurrence) {
            $ids = array_merge($ids, $extract($occurrence));
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }
}
