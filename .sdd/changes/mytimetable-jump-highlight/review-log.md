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

## Proposal Round 2 — 2026-11-04 (A1 answered)

### 🟡 Recorded
- **A1 answered by the user:** the ACTION-column icon is the **same jump**, tooltip `Go to My
  Timetable`, on Approved / Completed rows only. FR 5.1–5.6 and tasks T6–T10 are therefore final as
  written; the alternative readings in `proposal.md` §6 are closed.
- Frozen state unchanged: no decision-level edit was needed, only the removal of the open question
  (the spec already specified the approved/completed behaviour).

### 🔴 Outstanding
- **F1** — the notification arm's dead week switch. **Awaiting the user's decision** (fix now in this
  lane, fix as its own tiny commit, or leave it). Apply is on hold until the user says go.

## Apply — 2026-11-04 (measured on the demo DB, read-only GETs)

**Entry points, both measured independently** (`/my-replacements` → click → `/my-timetable-ui`):

| entry point | landed week | flashed | URL after |
|---|---|---|---|
| the row's icon button (request 26) | Week 7 · 02 Nov ~ 08 Nov | `data-rid="26"`, count **1** | `/my-timetable-ui` (params gone) |
| the modal's `Go to My Timetable` (request 26) | Week 7 | `data-rid="26"`, count **1** | `/my-timetable-ui` |

**The "never a neighbour" law, measured:**

| URL | result |
|---|---|
| `?repl-hl=26&repl-w=7` | flashes **26** only |
| `?repl-hl=27&repl-w=8` | flashes **27** only |
| `?repl-hl=26&repl-w=8` (26 lands in week 7) | **no flash** — the wrong-week case flashes nothing rather than the neighbour |
| `?repl-hl=999&repl-w=7` | **no flash**, no error |

**The gates, measured on the payload's `requestStatus` (not the badge text):**

| row | ACTION buttons | modal `Go to My Timetable` |
|---|---|---|
| need to arrange (×3) | 1 (arrange) | n/a |
| pending (27) | 1 (cancel) | **hidden** (cancel visible, as before) |
| approved (26) | **2** (view + jump) | **visible** (cancel + arrange hidden) |

**Layout:** `.col-action` 6 % → 10 %, `#timetable .col-reason` 22 % → 18 %. Measured at 1920 / 1600 /
1440 / 1366 / 1280 / 1100 / 900 / 820 px — **0 overflowing cells**, page overflow 0, the two-button cell
never clipped, and below 1280 the table scrolls inside `#gridScroll` (50 px at 1280).

**Not measured:** the buttons on a **Completed** row — the demo holds no completed request. The gate is
`c.requestStatus === 'completed'`, the same expression `actionCell()` already uses for the eye button, and
the status resolution itself was proven in the previous lane (a past replacement date yields `completed`).

**Gates:** pint passed · PHPStan level 7 → 0 errors · `node --check` ok ·
**full suite 340 passed / 1875 assertions** (490.2 s). Testing DB reseeded afterwards.
