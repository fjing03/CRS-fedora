# Review log — venue-evening-tail

## Batch review — 2026-11-14 (independent, read-only)

Reviewer: `commandcode/z-ai/glm-5.3-flash`, read-only, whole batch, checked
against the real tree (the `cellRender` closure, the room helpers, the `hours`
array, `tests/venue-book.spec.ts`, and the verified data fact that every venue's
`time_slots` rows start 08:00–17:30 only).

| Artifact | Verdict |
|---|---|
| `proposal.md` | PASS |
| `design.md` | PASS (**one blocking item**, fixed) |
| `specs/evening-tail/spec.md` | PASS |
| `tasks.md` | (**blocking item**, fixed) |

### Confirmed by the reviewer

- Diagnosis verified in the tree: 22 grid cells end at 18:30; data stops at 17:30,
  so 18:00/18:30 hit the A21 `nooffer` → spacer branch (blank), and 17:30 gets
  `room = 1 < 2` → `cell-no-room`.
- Arithmetic for the user's four outcomes checks out: hi 19 → room 3 (plain
  green); hi 20 → room 2 (plain green); hi 21 → room 1 (`cell-no-room`, red
  suppressed); a mid-day hole stays inert; a day whose last row is earlier stays
  fully inert.
- Only three cells change on a normal day: 17:30 loses the label, 18:00 and 18:30
  become green. `countedAvailable` is the sole source of the AVAILABLE card, so
  the +2/day is consistent with what the grid draws.

### Blocking issues found → resolved before freeze

1. **Stranded test.** Capping `roomBeforeNextEvent` by `hours.length` means
   `Infinity` can no longer occur, but the audit in `venue-book.spec.ts` asserts
   `noNextBlocker > 0` and has an `Infinity` branch. → design §5 now mandates
   removing/replacing that arm, and T4 says so explicitly.
2. **Vacuous ground.** `matrix[hi + room] !== 'free'` is true when
   `hi + room === hours.length` (index `undefined`). → design §5 / spec R7 add the
   day end as an explicit third ground, so the audit keeps teeth exactly where
   this change bites.

### Non-blocking notes → recorded in design §5a

- Tail cells carry the A19 time label whenever the day has a base row (same
  render branch); otherwise plain green like their neighbours.
- A cancelled history event covering the tail renders the history block, not a
  green cell (that branch is reached first).
- `lastOfferIndex` counts a blocked row as served — deliberate, commented.

## Freeze — 2026-11-14

All four artifacts frozen after the two blocking items were fixed.
