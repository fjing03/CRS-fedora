# Proposal — the request remembers its own length

## Why

A replacement slot is a 30-minute cell. A 1-hour class books two cells, a 2-hour class four,
and the request's run lives in `replacement_request_slots` (the junction).

`RejectRequest`, `CancelPendingRequest` and `AutoRejectRequest` DELETE those junction rows when
they release the reservation — that is how the cells become bookable again. Only the anchor
(`replacement_requests.replacement_time_slot_id`, the span's FIRST cell) survives, and
`ReplacementRequestMapper::spanFromAnchor()` assumes `count = 1`.

Consequence, measured on `class_replacement_fresh`:

| id | status | class | real span (audit proof) | shown today |
|---|---|---|---|---|
| 24 | rejected | 09:00–10:00 | 2 cells = 1 h | 13:30 – 14:00 (30 min) |
| 37 | rejected | 09:00–11:00 | 4 cells = 2 h | 10:30 – 11:00 (30 min) |
| 25, 30–34, 36 | cancelled | 1–2 h | 2 or 4 cells | 30 min |
| 26, 27 | approved | 1 h | junction kept | correct |
| 28, 35 | pending | 1–2 h | junction kept | correct |

7 of 7 released requests are wrong; 5 of 5 live ones are right. The historical "Cancelled by YOU"
report is the same defect (the label was removed, the maths never was).

## What

Store the span on the request at submit time and read it when the junction is gone:

1. additive nullable column `replacement_requests.replacement_span` (number of 30-minute cells);
2. written by `SubmitReplacementRequest` from its own `$span`;
3. `spanFromAnchor(?TimeSlot $anchor, ?int $span = null)` grows the end by `span × 30` minutes;
4. `ReplacementRequestMapper::span()` and `ReplacementHome::buildRow()` pass the stored value;
5. a dry-run-by-default backfill command fills old rows from the audit log (one `submitted` row per
   cell, so the count is the span).

## Out of scope

- **No "span must equal the class length" rule.** Checked: the action's callers and ~10 feature tests
  submit `span = 1` by default against 1–2 hour sessions; enforcing equality is a separate rule
  change with its own blast radius. Recorded as a follow-up, not bundled here.
- No change to the junction, the release logic, the grid, approval validity or auto-reject.
- No display redesign: the same column, the same words, the right end time.
