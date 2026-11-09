# Explore brief — occupied-cell-explanations (investigation receipts, 2026-10-27)

## Provenance
The user's Task: "Investigate Occupied Timetable Cells Without Tooltips" (the
5425 / `?session=38&week=14` report). Investigation report issued 2026-10-27;
user approved the plan with rulings (verbatim):
- Q1 (identity) → **"1 full ."** — grid-occupied cells carry FULL identity
  (module · room · time · lecturer · cohorts), including the other
  lecturers' classes and the other people's make-up rows.
- Q4 (scope) → **"arrangement-only"** — the `ApiReadController` twin feed
  (venue-timetable page parity) stays OUT of this lane.
- Q2 — the Earliest-Bookable default landing stays UNTOUCHED (ruling closes
  the `?week=` anchor question with NO change).

## Where each cell's silence comes from (the receipts)
- `app/Livewire/ReplacementArrangement.php → venueSlotsByWeekPayload()`
  (~:345–383): the slot-grid payload emits `[day, startIdx, status]` —
  THREE keys; statuses: occupied→1, my-pending→3, others'-pending→4.
- `resources/views/livewire/replacement-arrangement.blade.php:1782–1790`:
  the `cellData[2] === 1` arm renders `cell-occupied` and attaches
  `data-tip`/aria **only `if (cellData[3])`** — and `cellData[3]` is NEVER
  emitted. The branch's own comment says the venueSlot rows' 4th holder key
  was expected ("the same renderer treatment — same meaning, same look").
  → the designed feature was never implemented (missing feature, not a
  regression) — confirmed by the live DOM receipt (`data-tip=null`,
  `aria-label=null`) at Thu 14:00–14:30 / Fri 16:00–16:30, Week 7.
- The conflicting rows verified slot-by-slot: cs 39 = AMCS2093 (P) Lim Jia
  Zheng (5770) DSF2G1 Thu 13:30–14:30 B010 weeks 1–13; cs 41 = AMIS1003 (T)
  Mohd Nur Rahmat DSF1G1 Fri 15:30–16:30 B010 — all `time_slots.status
  occupied`. Occupancy itself CORRECT (venue axis); only the explanation
  missing. The arranger's own occurrence (Thu 12:00–13:00, the ctx class)
  renders the same silent way.

## The reuse point (the whole lane rides it)
`busyCellHolderLabels()` (~:464+) already produces the tidy-round block
format keyed `[weekIdx][day:slotStartIdx:slotEndIdx:room]`:
`ROOM · CODE (TYPE) · START–END\n[You — ]lecturer (staff)\ncohorts` —
for BOTH class-session rows AND make-up rows (`replacement_request_slots`
join; status pending+approved; per-slot-row keys, per-row ONE tuple law).
The grid emission can LOOK UP this same map instead of duplicating any
business rule.

## The critical mismatch the design must solve
The grid's slot rows are 30-MINUTE half-open cells; the label map is keyed
by the FULL class range (`13:00–15:00 → key day:10:14:room`). An exact key
match misses. Containment predicate = the client's own law:
`startIdx <= hi && hi < endIdx` with the ROOM in the key (the round-5
room-rider collision guard). Make-up rows are already split per 30-min
slot row, so they hit exactly.

## Rejected approaches
- Client-side join over lecturers/cohorts payloads: duplicates business
  rules client-side + bigger payload — rejected.
- Emitting labels for the ENGINE arm (already done by TEMP-HOLDER-LABEL):
  does not help grid-only conflicts (foreign lecturer cohorts at the
  viewed venue) — those have no engine tuples by design.
- Changing `ApiReadController` in the same lane: user-ruled deferred.

## Known open questions (for design.md)
1. Tuple append vs insert: `cellData`'s consumer indexes `[0..2]` — the
   4th key must be APPENDED at index 3 (byte-compatible).
2. The grid arm's tip must reuse `renderHolderTip()` (header + round-3
   why-tags + flat aria) so the look stays IDENTICAL to the engine arm
   (same-name-same-color canon).
3. Pending/reserved (status 3/4) tips: same key, renderHolderTip's pending
   header family; exact copy frozen in design (design.md).
4. Payload size: labels ride every occupied row (~300/week) — the PAGE_DATA
   shard partial (>60KB trigger) already exists; measure in verify.
5. Double computation: `busyCellHolderLabels()` must be computed ONCE per
   payload run and shared between the vector and the grid arms.
