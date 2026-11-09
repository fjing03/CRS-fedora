# proposal — `makeup-busy-vector` (2026-10-27)

## Why (the bug9 investigation receipts)

The arrangement availability marks B010 Fri W6 13:30/14:00 `available`
although the SAME lecturer (Surayaini, 5425) has an APPROVED make-up
(rr #1 → BMSE3153, occupied `time_slots` 32612/32613, B009 W6 Fri
13:30–14:30) in that exact hour.

Root cause: `MatrixIntersectionEngine`'s three busy vectors derive ONLY
from `ClassSession` rows — the replacement make-up rows (`time_slots`
status `occupied|pending` with `class_session_id NULL`, linked through
`replacement_request_slots ↔ replacement_requests`) NEVER enter them:

- the LECTURER vector misses the lecturer's own make-up ✓ the repro;
- the COHORT vector misses the rr cohorts' commitment (the latent general
  case; harmless in today's data — the cohorts don't intersect);
- the SUBMIT side accepts (`validateSlot(span2, B010, AMSE2003) = true`)
  — a genuine rule violation, not a UI trace.

The venue axis is NOT the failing one: B010 was genuinely free, and a
B009 booking does not block B010. The cohorts genuinely don't overlap.

## The user's locked semantics (2026-10-27)

- **1(b) EVERYONE's** — ANY approved/pending make-up (any lecturer) blocks
  EVERY caller's vector (the stricter, submit-already-fails-via-the-venue
  axis, but the UI marks the truth earlier).
- **2 BOTH** — the lecturer vector AND the cohort vector get the make-up
  rows (the cohort side scoped: a make-up contributes one range per
  affected cohort GROUP of its rr's class_session).
- **3 DELEGATE** — one engine source (the UI already delegates to
  `engine()->lecturerBusyRanges/cohortBusyRanges` in
  `ReplacementArrangement::busyByWeekPayload` — verified; no second SQL).
- **4 NEW LANE** — this lane.
- **5 PARKED** — the conflict-schedule-vs-`?session=` UI copy stays out.

## Scope

| File | Change |
|---|---|
| `app/Services/MatrixIntersectionEngine.php` | NEW private `makeUpRanges(int $semesterId, int $weekNumber): array` — the approved+pending make-up slots of that week (`whereIn status [pending, occupied]` joined through the rr chain, the STATUS filter excludes cancelled/rejected); `lecturerBusyRanges()` merges it; `cohortBusyRanges()` merges it (the cohort-join already powers the shape via `session_cohorts`) |
| `tests/Feature/MakeUpBusyVectorTest.php` | NEW: the 6 receipts (the blocking, the direct span, the boundary half-open, the pending + everyone's, the cancelled exclusion, the doctor's dedup-key shape parity) |

The ONE-SOURCE delegation keeps the UI layer untouched: the
`busyByWeekPayload`'s `$toRanges` engine calls inherit the ranges, the
TEMP-HOLDER-LABEL 4th-tuple join rides the same ids.

## out of scope

- the conflict-schedule panel's copy (`?session=` ties) — parked;
- the restaur `SubmitReplacementRequest`'s own duplicate check — untouched
  (the anchor-status gate makes the self-block hazard moot);
- the venue-grid's `venueSlotsByWeekPayload` — untouched (the venue axis
  already correct);

## Acceptance

1. `validateSlot(span2, B010 Fri W6 13:30–14:30, AMSE2003 ctx) = false`
   post-fix (the fail receipt on the exact repro).
2. The 14:30 slot and the 13:00 slot stay UNBLOCKED (the half-open
   boundary law [13:30,14:30)).
3. Any lecturer's pending make-up ALSO blocks (1(b) receipt).
4. Everyone's cancelled rr rows do NOT block (the released slots stay
   available).
5. `php artisan test` 262+ green; Playwright: the frontend cells flip to
   the occupied class on the exact repro's ctx post-fix.
