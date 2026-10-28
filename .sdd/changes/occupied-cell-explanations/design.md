# Design — occupied-cell-explanations

## D1. One computation, two consumers (the share law)

`ReplacementArrangement::mount()` computes
`$holderLabels = $this->busyCellHolderLabels($semesterId, $weeks);` ONCE
and passes it DOWN into both payload builders (signature change):

- `busyByWeekPayload($sessions, $semesterId, $weeks, $holderLabels)`
  — the existing call becomes the (unchanged) consumer;
- `venueSlotsByWeekPayload($semesterId, $weeks, $holderLabels)`.

**The internal call INSIDE `busyByWeekPayload()` at :405
(`$holderLabels = $this->busyCellHolderLabels(...)`) is REPLACED by the
threaded parameter** — otherwise mount+builder double-compute, which D1
forbids. `busyCellHolderLabels()`'s body stays untouched (the round-5
room-rider keys and the make-up per-slot-row keys are the reuse
assets). No double computation.

## D2. The grid lookup (containment over a day-indexed view)

Inside `venueSlotsByWeekPayload()`, build a per-call view:
`[weekIdx][dayIdx . ':' . roomCode][] = [startIdx, endIdx, label[]]`
(one pass over `$holderLabels`, ~294 entries/week).

For each slot row the payload already iterates:

```
$hi = slotIndex(start); $day = (int)$slot->day_of_week;
$room = $slot->venue->room_code;
foreach ($view[$weekIdx][$day.':'.$room] ?? [] as [$s, $e, $labels]) {
    if ($s <= $hi && $hi < $e) { $picked = array_merge($picked?, $labels); }
}
$holderLabels4 = $picked ? array_values(array_unique($picked)) : null;
```

Half-open containment — the SAME predicate the client's `engineBlocked`
uses (`hi >= b[1] && hi < b[2]`); a 13:00–15:00 class covers the
13:00/13:30/14:00/14:30 half-cells, never 15:00.

**Key parse law**: the map keys are the strings
`day:startIdx:endIdx:room` (:569/:642). The view explodes them with
`explode(':', $key, 3)` — LIMIT 3, so the room stays the remainder
(room codes carry no colons); a field-by-field explode of all parts
corrupts the room and re-creates the round-5 room-rider phantom this
lane exists to prevent.

`$status` code itself stays the tuple's index 2 (1 occupied / 3 my-pending / 4 others'-pending
— the `$proposers` map's semantics untouched).

Tuple emission: `[$day, $startIdx, $status, $holderLabels4]`.
`@return` docblock moves to `list<array<int|string, int|array<int,
string>|null>>` — updated, never deleted (round-5 lesson).

## D3. The Blade pseudo-match conversion (one renderer, both arms)

`renderHolderTip()` stays byte-unchanged. In the cellRender's `cellData`
arm, BEFORE the status branches:

```
const gridHolderMatches = Array.isArray(cellData[3])
    && cellData[3].length
    ? cellData[3].map(l => [di, hi, hi + 1, l,
          cellData[2] === 1 ? null : 'pending'])
    : null;
```

statusKind law (matches the engine arm's make-up ranks):
- grid `1` (occupied) → `null` → `⚠ Occupied — N class(es) in the way`
  header, red rank;
- grid `3` (my pending) / `4` (others' pending) → `'pending'` → the
  `⏳ Pending make-up — awaiting approval` header + `Pending — ` flat
  prefix when ALL matches are pending — the SAME copy `renderHolderTip`
  already emits for engine-side pending make-ups (this is the canonical
  copy; no second implementation).

**statusKind governs the TIP only.** The cell's class always comes from
the grid status code via the untouched branches
(`1`→cell-occupied, `3`→cell-pending, `4`→cell-reserved). Pre-existing
asymmetry stated as intended: engine-arm all-pending → cell-pending;
grid-4 → cell-reserved with a ⏳ pending tooltip — not a verify bug.

Attach (the same attribute set the engine arm's `attachHolder()` lays):

```
if (gridHolderMatches) {
    const rendered = renderHolderTip(gridHolderMatches);
    if (rendered) {
        div.setAttribute('data-tip', rendered.readable);
        div.setAttribute('data-tip-pos', 'left');
        div.setAttribute('aria-label', rendered.flat);
        div.setAttribute('tabindex', '0');
        div.style.cursor = 'help';
    }
}
```

The per-status branches then run unchanged (only the occupied arm's
old `if (cellData[3])` single-string attach is REPLACED by the shared
converter above — the single-string shape no longer exists).

## D4. Canonical constants (frozen)

- The block format = `busyCellHolderLabels()`'s `$blockFor` output
  (reuse; no new format). The round-3 tags and the round-2 header live
  in the unchanged client renderer.
- The 🏛 Venue-conflict tag fires on every grid holder (tautology —
  intended noise, round-2 receipt).
- Negative arm: `holderLabels4 = null` → no tip, class unchanged —
  byte-identical to today.
- `array_unique` preserves first-appearance order (deterministic); the
  tip's block sort stays client-side (room-code) as today.
- **Known grid-arm simplification (receipt, not a bug)**: the grid arm's
  `statusKind` derives per CELL (`cellData[2]`), while the engine arm's
  safe rank is per ROW (`b[4]`). In an overlap conflict the two may
  disagree (e.g. a foreign pending make-up sharing the half-hour with
  an approved one under a grid-1 cell announces ⚠ via the occupied
  rank; a grid-3 cell carrying an approved blocker reads ⏳/pending).
  The label map carries labels WITHOUT row status, so per-row truth is
  not recoverable without touching the frozen `busyCellHolderLabels()`.
  Accepted simplification — the cell CLASS always follows the grid
  status (D3's statusKind-for-tip-only law), the exclusive visible rank
  never flips wrongly, and the engine arm keeps the per-row law at the
  submit boundary.

## D5. Test plan

- **phpunit** (`ReplacementArrangementTest` additions): seed a fixture
  class (venue, module, lecturer, cohort) + its occupied width-N slot
  rows for a week; assert the payload's grid tuples carry an ARRAY of
  the block strings for every covered half-hour (incl. the span's tail
  cells), the room/wrong-room isolation (the round-5 guard), the
  `null` arm for an untipped status, and the Week-14-holiday primacy
  receipt (the holiday arm wins in the DOM — Playwright, not phpunit).
- **Payload-size gate (the frozen proposal's decision path)**: measure
  the rendered `PAGE_DATA` blob size at apply/verify (a probe page's
  response length, B010/W7 landing). If it crosses the shard's 60KB
  trigger comfortably (>90KB, the ~50% headroom guard), enact the
  accepted fallback: a holder DICTIONARY in `PAGE_DATA` + reference
  indexes on the grid rows. The decision is a measured number, never a
  guess.
- **Playwright receipts** (image + DOM): B010/W7 Thu 14:00–14:30
  (AMCS2093 block), Fri 16:00–16:30 (AMIS1003), the arranger's own
  Thu 12:00–13:00 (`You — `), Week 14 both cells `cell-ph` without tips.
- Gates: pint, phpstan 0 (1G), full suite ≥ 265/265, changelog entry.
