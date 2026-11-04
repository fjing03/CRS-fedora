# Review log — `mytimetable-jump-highlight`

Append-only. Batching follows the SDD workflow: proposal → design → specs → tasks, each self-reviewed
here (no `@sdd-reviewer` subagent is available in this harness, so the reviewer role is the assistant's
own adversarial pass — recorded honestly as self-review).

## Proposal Round 1 — 2026-11-04 (self-review)

### 🔴 Fixed
- The first draft assumed the ACTION-column button was a **generic** jump for every row. That contradicts
  the modal half of the order (approved/completed only) and would have produced a button with nowhere to
  go on pending/rejected rows. Narrowed to approved/completed and raised as **A1** instead of silently
  deciding it.

### 🟡 Addressed
- Added the "arrival-grid-no-pin" law explicitly, since this lane auto-scrolls a grid.
- Added the responsive-table law to the acceptance criteria — the ACTION column change is a layout
  change, not only a behaviour change.

### 🔴 Outstanding
- **A1** — the ACTION icon's purpose. User must answer.

## Design Round 1 — 2026-11-04 (self-review)

### 🔴 Fixed
- **F1 (found while writing the arrival arm).** `applyNotifHighlight()` deletes `notif-w` from the
  `URLSearchParams` object and then reads it back —
  `params.delete('notif-w'); … const wk = parseInt(params.get('notif-w') || '', 10);` — so the week
  switch in the **notification** arm is dead code: it always reads `null`. Latent, pre-existing, and
  outside this lane; recorded here, offered to the user as a separate one-line fix. The new arm reads
  `repl-w` **before** deleting, and `spec.md` FR 3.3/3.4 pins that order.
- The first draft had no answer for "what if the first render is slower than the notification arm's
  14 × 250 ms budget" — my-timetable waits on `MockData` + a week restore. Raised to 20 ticks (≈ 5 s)
  and written into FR 3.4.

### 🟡 Addressed
- `.col-action` 6 % → 10 % + `col-reason` 22 % → 18 % were added only after doing the arithmetic
  (two 34 px buttons ≈ 76 px inner width vs ≈ 77 px at 6 % of the 1280 px floor) rather than guessing.
- `completed` rows: verified they are derived from an **approved** row and that `MyTimetable` queries
  `['pending','approved']`, so a Completed record genuinely has a block to jump to.

### 🔴 Outstanding
- None beyond A1.

## Specs Round 1 — 2026-11-04 (self-review)

### 🟡 Addressed
- Added FR 1.3 (replacement blocks keep `data-sid` too) — the notification arm must not lose its
  target because of this lane.
- Added FR 7.1 so the notification, Today and earliest-bookable flashes are explicitly regression-scoped.

### 🔴 Outstanding
- None.

## Tasks Round 1 — 2026-11-04 (self-review)

### 🟡 Addressed
- Every task carries a named receipt; T13 lists suites by file, not by "the relevant tests".
- T14 pins the full-suite discipline (backgrounded, polled, never beside a filtered run) because this
  repo has aborted a suite at ~300 s before.

### 🔴 Outstanding
- Apply not started — blocked on A1.
