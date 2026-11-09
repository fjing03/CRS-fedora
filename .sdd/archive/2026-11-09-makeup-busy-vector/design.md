# design — `makeup-busy-vector` (2026-10-27)

## D1 — the engine's union, extended by a third source

`MatrixIntersectionEngine` gains a single private range reader:

```php
private function makeUpRanges(int $semesterId, int $weekNumber): array
{
    return TimeSlot::query()
        ->whereIn('time_slots.status', ['pending', 'occupied'])
        ->where('time_slots.semester_id', $semesterId)
        ->where('time_slots.week_number', $weekNumber)
        ->join('replacement_request_slots',
               'replacement_request_slots.time_slot_id', '=', 'time_slots.id')
        ->join('replacement_requests',
               'replacement_requests.id', '=',
               'replacement_request_slots.replacement_request_id')
        ->whereIn('replacement_requests.status', ['pending', 'approved'])
        ->get(['time_slots.day_of_week', 'time_slots.start_time',
               'time_slots.end_time'])
        ->map(fn ($s): array => ['day' => (int) $s->day_of_week,
                                 'start' => (string) $s->start_time,
                                 'end' => (string) $s->end_time])
        ->all();
}
```

The double filter (`time_slots.status` AND `replacement_requests.status`)
is the deliberate belt+braces: the OCC's lifecycle is the rr status, the
grid row is the time_slots status; both move in-tandem per the lanes'
invariants (approved ⇒ occupied; pending ⇒ pending; cancelled/rejected ⇒
the slots released).

## D2 — BOTH vectors merge (the user's lock 2), the owner semantics decided

`lecturerBusyRanges(...)` and `cohortBusyRanges(...)` each gain the
`makeUpRanges` merge:

- **the LECTURER vector = OWNER-SCOPED**: the vector's `$lecturerId`
  owns the make-up (the rr's `class_session.lecturer_id`). A make-up is a
  PERSON's committed-time layer; another lecturer's make-up must not
  block my calendar (their Fri 13:30 makeup must not pin my Fri 13:30).
  Approved + pending BOTH count (the user's (b): "everyone's" = ANY
  status of the owner's make-ups enter, owner-only stops.

- **the COHORT vector = UNSCOPED (ownerless)** — the same shape the
  class-side ranges already ride (`cohortBusyRanges`' SQL joins the
  cohorts' classes from every lecturer; a cohort's time-layer is not
  owner-scoped). The make-up's rows enter through
  `replacement_requests.class_session_id → session_cohorts`, restricted
  to the vector's `$cohortIds`. Any lecturer's pending make-up for
  DSF2G1 blocks DSF2G1's other bookings — the (b) receipt.

The DOUBLE filter (D1) plus the joins:

```php
// the lecturer merge (inside lecturerBusyRanges):
->join('class_sessions', 'class_sessions.id', '=',
       'replacement_requests.class_session_id')
->where('class_sessions.lecturer_id', $lecturerId)
// no owner/exception exclusion on the class_session_id — the make-up's
// rows ARE the week's new commitments (the original pair is exception'd
// and frees its own ranges via the class-side vector).

// the cohort merge (inside cohortBusyRanges):
->join('session_cohorts as sc', 'sc.class_session_id', '=',
       'replacement_requests.class_session_id')
->whereIn('sc.cohort_id', $cohortIds)
```

## D3 — `validateSlot` inherits byte-free

`validateSlot` composes `lecturerBusyRanges + cohortBusyRanges` already —
NO new code; the span's k>0 virtuals evaluate against the merged ranges.

## D4 — the UI inherits byte-free

`ReplacementArrangement::busyByWeekPayload` already delegates
(`$this->engine()->lecturerBusyRanges(...)` per week — the 15:41 receipt)
and merges under `array_unique(SORT_REGULAR)` (duplicate-safe);
`ReplacementArrangement::busyCellHolderLabels` (the TEMP-HOLDER-LABEL
4th tuple key) unions the make-up rows' holder text under the same
`[weekIdx][day:start:end]` key shape (the module code + ` · Replacement`
so the occupied-cell aria stays honest). Same file, same key law.
**THE PER-ROW KEY LAW** (the reviewer's 🟡): the label keys ride EACH
time_slot row (13:30–14:00 / 14:00–14:30 = two tuples — the reviewer
verified `slotIndex` substr+H:i handles both sides byte-identical, so
the format is a non-issue; the granularity is the only hazard) — never
the rr's span. `array_unique(SORT_REGULAR)` tolerates the dedup.

## D5 — the tests (NEW `tests/Feature/MakeUpBusyVectorTest.php`)

- the 5425/40 repro: `validateSlot(34292, span2, AMSE2003) = false`.
- the boundary: the 13:00 slot + the 14:30 slot UN-perturbed.
- the pending + everyone's: a pending rr (the SAME lecturer, another
  class) ALSO blocks; ANOTHER lecturer's make-up does NOT enter MY
  lecturer vector (design D2's decision).
- the cohort side: the rr's OWN cohorts see the make-up in their vector;
  the UNRELATED cohorts do NOT (a scoped receipt).
- the cancelled rr: NOT in the vector (the slots released).
- the UI delegation: `busyByWeekPayload`'s '' key carries the make-up's
  tuples (the delegation receipt — no second SQL).

## D6 — the receipts

- the B005's SDD lanes are SEPARATE from this lane (the B005 repeal is
  applied; this lane's ctx = the做出了's own 5652-URL? — the receipts stay
  on the AMSE2003 (session 40) ctx (the bug's original page).
- pint + phpstan + the full phpunit suite + node --check.
