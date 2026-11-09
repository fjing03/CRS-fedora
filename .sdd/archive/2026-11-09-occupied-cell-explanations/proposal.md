# Proposal — occupied-cell-explanations

## Problem (the confirmed gap)

On `/replacement-arrangement`, occupied cells explained ONLY by the venue
slot grid render `cell-occupied` with **no tooltip and no aria text**:

- **Reproduction** (2026-10-27, staff 5425, B010, Week 7 · 02–08 Nov):
  Thu 14:00–14:30 and Fri 16:00–16:30 → `data-tip=null`, `aria=null`,
  hovering shows nothing. The conflicting rows are real imported classes:
  AMCS2093 (P) Lim Jia Zheng (5770) Thu 13:30–14:30, and AMIS1003 (T)
  Mohd Nur Rahmat Fri 15:30–16:30 — both at B010, weeks 1–13.
- **Root cause**: `venueSlotsByWeekPayload()` (ReplacementArrangement.php
  ~:375–384) emits the grid triples `[day, startIdx, status]`; the Blade's
  `cellData[2] === 1` arm (blade :1782–1790) attaches `data-tip`/aria only
  `if (cellData[3])` — **which the payload never supplies**. The comment at
  blade :1793–1797 documents the expected 4th holder key ("same renderer
  treatment — same meaning, same look") — the designed feature was never
  implemented. The holder texts exist ONLY for the engine-vector arm
  (the TEMP-HOLDER-LABEL block in `busyByWeekPayload()`).

Every "foreign lecturer's class in the viewed venue" cell — and the
arranger's own occurrence cell — is therefore unexplained. The engine arm
cannot cover them by design (vectors are owner-scoped + cohort-scoped;
cs 39/41 share neither the arranger nor her cohorts).

## What this change does

Emit the holder text for the slot-grid rows and let the existing Blade
branch consume it — the tooltip look becomes IDENTICAL to the engine arm.

1. **Payload**: `venueSlotsByWeekPayload()` gains a 4th tuple key:
   `[day, startIdx, status, holderLabels|null]` (APPENDED at
   index 3 — no existing index moves; the triple's `[0..2]` bytes stay).
   **`holderLabels` is an ARRAY of block strings (never a pre-joined
   string)** — one element per blocking holder row, each a complete
   tidy-round block:
   `ROOM · CODE (TYPE) · START–END\n[You — ]lecturer (staff)\ncohorts`.
   The labels are looked up in the SAME
   `busyCellHolderLabels()` map (computed ONCE per payload run, shared
   with `busyByWeekPayload()`), selected by containment
   `startIdx <= hi && hi < endIdx` **and** the SAME WEEK index and the
   SAME room in the key (the round-5 room-rider collision guard).
2. **Blade**: the `cellData[2]` block builds pseudo-matches in the
   engine arm's exact tuple shape —
   `[di, hi, hi+1, label, statusKind]`, one per `holderLabels` element —
   and calls the UNCHANGED `renderHolderTip(matches)`. The header count,
   the round-3 why-tags and the flat aria therefore parse per-BLOCK, not
   per-cell: the `\n\n`-join mistake (one string through the per-tuple
   pipeline) is structurally impossible. The statusKind mapping:
   grid `1` (occupied) → `null` (⚠ header, the safe red rank);
   grid `3` (my pending) / `4` (others' pending, the cell-reserved
   family) → `'pending'` (the ⏳ all-pending header + the tertiary
   rank) — the same rank the engine arm already gives make-up rows of
   the matching rr statuses. The exact pending-arm copy is PROPOSED
   here and gets frozen in design.md (not a user ruling against Q1).

## Decisions locked by the user (2026-10-27)

- **Full identity** on grid-occupied cells — module · room · time ·
  lecturer (name + staff id) · cohorts, exactly the tidy-round block
  format; no redaction for foreign classes or foreign make-ups.
- **Arrangement-only** — the `ApiReadController:600–605` twin feed
  (venue-timetable) is NOT touched in this lane; parity re-decided after
  the user reviews the arrangement result.
- The **Earliest-Bookable default landing** stays untouched (the
  week-anchor question is closed with NO change).

## Out of scope

- No server validation/booking rule change (`validateSlot`, the engine,
  the submit path untouched — the occupancy verdicts were confirmed CORRECT).
- No timetable-data/schema change; no new dependencies; no mock additions.
- No change to the week selector / `ARRIVAL-GRID-NO-PIN` landing.
- The TEMP-HOLDER-LABEL viva teardown stays a separate future task; the
  two features coexist (the grid arm's 4th key is owned by THIS lane, not
  by the TEMP block).
- Student pages / other timetables untouched.

## Risks & constraints

- **Payload size**: ~294 occupied rows/week at 30-min grain — a multi-
  hour class repeats its block text across every grid row it spans
  (a 2-hour class ×4), so the label text multiplies faster than the
  engine arm's once-per-range rows. The `PAGE_DATA` shard partial
  (>60KB) already contains this growth; the accepted fallback if the
  verify measurement exceeds it: a holder DICTIONARY (`PAGE_DATA` once
  per label, grid rows carrying references) instead of inline text —
  verify decides on a number, not a guess.
- **Type migration**: `venueSlotsByWeekPayload()`'s `@return` docblock
  shape moves from `list<array{int, int, int}>` to the 4-tuple — the
  annotation is UPDATED, never deleted (the round-5 drift lesson).
- **No status-class change to the CSS** — the arm classes
  (`cell-occupied`/`cell-pending`/`cell-reserved`) stay byte-identical;
  only the tip/aria attach is new.
- **The engine tests' frozen tuple law** (265/265): this lane appends to
  the DIFFERENT array (the venue-slot grid triples); the busy-vector
  5-slot shape `[day, startIdx, endIdx, block, statusKind]` stays frozen.

## Known constants (the tautology receipt)
- For a GRID block the `🏛 Venue conflict` tag fires on EVERY logged
  holder (the holder's room IS the viewed venue — `room === currentVenue`
  is always true there). That is INTENDED noise, not a bug; the 👤 Own
  and 👥 Cohorts tags parse normally from the block text.
- The negative case: a grid-occupied cell with NO matching label must
  still render `cell-occupied` tip-less (the `|null` arm) — styled, no
  regression.

## Success criteria

- B010 / Week 7 Thu 14:00–14:30 hover = the AMCS2093 block (room-first
  tidy format) + the 🏛 Venue-conflict tag (tautological for grid
  blocks — see the constants section); Fri
  16:00–16:30 = the AMIS1003 block; the arranger's own Thu 12:00–13:00
  cell = the `You — ` block; Week 14 = the holiday arm (`cell-ph`) still
  wins — grid changes never shadow it.
- Regression receipts: `tests/Feature/ReplacementArrangementTest`
  payload assertions (the 4th key present for the cs 39/41 rows),
  Playwright DOM receipts (`data-tip` + aria present), full phpunit
  suite green, pint + phpstan 0, page-changelog entry.
