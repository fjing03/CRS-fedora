# Spec — venue-original-fields (F5)

## Requirements

### R1 — the reserved pass carries the original keys (both statuses)
`VenueTimetable`'s reserved-pass event build:
- `ReplacementRequest::with(...)` gains `classSession.venue`
  (relation-unloaded today).
- The event bag adds:
  - `originalVenue` (venue-less session → `''`)
  - `originalVenueName` (venue-less → `''`)
  - `originalStartTime` ('H:i' — `substr((string) $session->start_time, 0, 5)`),
  - `originalEndTime` ('H:i').
- The keys are STATUS-INDEPENDENT: the same single event-build emits them
  for `status 'replacement'` (approved) AND `status 'pending'`.

### R2 — the client contract is unchanged
`ui-common`'s Original rows already key these names (my-timetable's
popup); no client edit. Token colors only — no new CSS.

## Scenarios
- S1: an approved replacement event → the pair stamp + the original keys;
  the popup's Original rows read the REAL original clock/venue (unless the
  venue-less edge → the empty-string row).
- S2: a pending event → the same keys; remarks stay the byte-frozen tip.
