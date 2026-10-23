# Spec — pin-truthfulness (F1 + F2 + F3 + F4)

## Requirements

### R1 — the server rejects a pin that is not a real conflicted occurrence
`SubmitReplacementRequest::__invoke`: after ownership + the week-range
check (the range reads `LectureSessionCatalog::weekBound()` — the literal
14 retires), BEFORE the slot lookup and BEFORE `ReplacementRequest::create`:

```php
$fact = LectureSessionCatalog::occurrenceFact($session, $requestWeek);
if ($fact === null) {
    return $fail('occurrence_not_conflicted');
}
```

- The loser = the existing flat `$fail()` shape; zero new exception paths.
- The direct-input pair rides the guest `$requestWeek` (post-D-P2: the
  caller's `$occurrenceWeek`), never the silent fallback.

### R2 — the pinned week must be REAL (no fallback)
`ReplacementArrangement::submitBlocks`:
- absent `$occurrenceWeek` → the per-block result row
  `['ok' => false, 'reason' => $this->conflictText('no_occurrence_pin')]`
  (the row CONTRACT intact — the client pairs results[i] with blocks[i]);
- present → `$requestWeek = $occurrenceWeek` in every later read.

### R3 — the client gate
- Submit zero (no `window.pinnedOccurrence`) → blocked with the same copy
  (picker-first message; the picker stays open).
- Grid pick (:3159) + the Replace-Now URL hydration (:3392) remain THE two
  pin sources; both set `window.pinnedOccurrence`.

### R4 — the my-timetable popup is exact-pair truthful
- The button shows ONLY when the opened event's `occurrenceFact` stamp is
  non-null (`ResolvesTimetableTimeline` week loop stamps
  `['week' => w, 'kind', 'reason']` from ONE batched context).
- Handoff = `goToReplacementFor(event.id, event.occurrenceFact.week,
  'my-timetable')` — never `window.currentWeek`.
- The loud-red day-flag DISPLAY branch stays on `holidaysForJs()` verbatim
  (both derive the same `week_count` — divergence impossible).

### R5 — the ONE-source covenant
`conflictFacts()` + `conflictMapFor()` collapse onto the D-P0 primitive
(`factContext` + `occurrenceFact` + `factsIndex` + `weekBound`); their
signatures and return shapes stay byte-identical; `conflictedFor`/`build`
sit unchanged.

## Scenarios
- S1: no-pin submit → per-block `no_occurrence_pin`; blocks[i] stays
  aligned; no reservation; no audit.
- S2: a pin on a NORMAL week (bookable slot, valid span, session-owned) →
  `occurrence_not_conflicted`; no reserve; no audit; the polite copy shows
  in the D8 reporter.
- S3: a pin on the holiday week (W14 for the AMSE2003-type fixture) →
  accepted end-to-end; `week_number = 14`.
- S4: the same submit, session and no pin → rejected (R2 prevention).
- S5: the popup for a W14-flagged class → the button with `(office 14)`-key
  handoff; for the SAME class on a W5 view → no button.
- S6 (approve chain): the accepted.submit approve (the presenter flip) → D3 stamps the ORIGINAL week (`class_exceptions <session, 14>`); releaseOriginal frees nothing (no W14 rows exist); the positive control test asserts the whole chain.
- S7 (fact kinds): each of the three verdict kinds — exception (Cancelled — …), holiday (Public Holiday (…)), venue (Venue Restriction (B005 no-Diploma)) — produces a HANDOFF-carrying button THROUGH THE DEEPER-POLICY fixtures; a no-fact week has NO button.
- S8 (seeder premise): the first fact test asserts the AMSE2003-class+holiday-seed premise (the pair carries the holiday fact) so seeder can't edit it silently flips the suite's meaning.

## Landing-week coexistence (D-P10 — the CHOICE-A ruling, 2026-10-19/20)
- R-L1: a class's natural block renders in EVERY week its timeslot exists —
  the replaced week's suppression is the D7 stamp's ALONE.
- R-L2 (approved): the natural block + the approved receipt block COEXIST on
  a landing week (same-day different-hours ⇒ two blocks; the receipt at the
  junction's cell per the frozen D7 keying).
- R-L3 (pending): the natural block + the AMBER proposal block coexist; at
  an exact same-hour overlap the AMBER wins (the grid's slotMap
  last-write-wins — the push-order covenant: natural first, amber second).
- R-L4: the Proposed ⏳ hint stays on the natural conflict occurrence's own
  cell (pendingByOccurrence + buildReplacementNote; no client edit).
- R-L5: the occurrenceFact stamp rides the natural event independently; no
  proposed-cell payload ever gains a stamp.
- S9: the #13 shape — W5 renders the natural AMSE2003(P) Fri 09:00–10:00
  block AND the approved receipt at Fri 13:00–14:00; W14 renders nothing
  (the (40,14) stamp).
- S10: a pending #12-shape (the makeup at the SAME hour as the own class) →
  the amber covers the cell (last-write-wins) — the visually identical
  pending look the user knows.
