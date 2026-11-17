# Task 10 — `details-modal-consistency` (compact lane)

> **The brief is the source of truth**: `prompts/pi-prompt-details-modal-consistency.md`. Its
> §Decisions (1–10) are accepted as written and are **not** reopened here. This artifact records
> what the live code still needs, the two questions the brief leaves to the user, and the task
> list — one file, one review round, per the user's 2026-11-17 instruction to cut SDD cost.

## 1. Scope

Three pop-ups, one shape. The CSS half already shipped; only the blades are behind.

| Pop-up | Blade | Shell |
|---|---|---|
| Replacement Details (staff) | `replacement-home.blade.php` | `#quickViewModal` |
| Request Details (PL) | `request-approval.blade.php` | `#modalOverlay` |
| Replacement Details (student) | `replacement-history.blade.php` | `#classModal` |

## 2. Where the live code stands (measured 2026-11-17)

| Requirement | staff | PL | student |
|---|---|---|---|
| Short tab labels | `Requested Slot Schedule` / `Original Slot Schedule` / `Request Status (x)` — **long** | long | `Original Slot Schedule` / `Class Info` / `Request Status (x)` — **long** |
| `Class Info` tab | **missing** | **missing** | present |
| Strip: `Status` first, badge-led | `Cohorts` · `Students` · `Conflict` — **no Status** | `Staff` · `Cohorts` · `Students` · `Conflict` — **no Status, wrong last row** | `Conflict` only — **no Status** |
| `Days Left` row | countdown inline beside the date | **no countdown at all** | inline |
| Footer primary | `Close` gets `is-primary` when no action (`classList.toggle`) | `Approve` **not** filled | `Close` **hard-coded** `btn-outline is-primary` |

## 3. Decisions this artifact takes (the brief's open items)

### D1 — The student's always-on `Overdue` is aligned to the staff rule *(needs the user's word — see §5)*

The student's `Original` tab prints `Overdue (n Working Days Ago)` even when the request is
approved or completed; the staff pop-up hides the countdown once the row is handled
(`countdownHidden`). The brief calls this the user's call. **Recommended: align it** — a completed
row is not overdue — and it is one call to the existing flag. Held until the user answers.

### D2 — `Class Info` is written twice, not shared

The brief asks whether the two staff blocks can be one helper. **Answer: no.** The facts differ
(PL carries `Staff (Proposer)` + the email chip; staff does not), the builders differ (`r` vs `c`,
`compactCohort` vs `cohorts.join`), and a shared partial for three rows would need more parameters
than rows. Written per blade, same order, same captions.

### D3 — the strip's `Status` uses each page's existing badge helper

`statusCell(c)` (staff), `statusClass(r.status)` + `statusTipAttr(r)` (PL), `statusCell(r)`
(student). No new colour, no coloured text — the brief's decision 3.

### D4 — `Days Left` keeps the existing words and colours, only its home moves

`detailCountdown` / `daysLeftLabel` + `urgencyClass|urgencyBandClass` unchanged; the value moves
out of the date row into its own `DetailModal.row('Days Left', …, { strong: false })` under
`Day & Date`. The 2026-11-07 bracket/em-dash placement is superseded **for these three pop-ups
only** (brief decision 7).

## 4. Acceptance criteria

- **AC1** All three pop-ups show exactly `Requested` · `Original` · `Class Info` · `Status` where
  the slot exists; the section captions keep the long names.
- **AC2** Both staff pop-ups gain `Class Info` **before** `Status`; the student's existing tab is
  verified, not duplicated.
- **AC3** Strip order: student & staff `Status` → `Conflict`; PL `Staff (Proposer)` → `Status` →
  `Slot Validity`. `Status` is each page's own badge component.
- **AC4** `Days Left` is its own row under `Day & Date`: student Original + Requested (existing
  flag), staff Original (unless `countdownHidden`) + Requested (pending only), PL Requested
  (pending only — new).
- **AC5** No filled `Close` on any of the three. PL's `Approve` is the filled (`is-primary`)
  button, `Reject` the outline above it.
- **AC6** The pinned header/footer still hold (only `.modal-body` scrolls) at 402 and 1440, and no
  `position: sticky` is added.
- **AC7** Nothing outside these three pop-ups changes: the timetable class pop-up, the confirm
  dialogs, `.modal--detail`'s 680px width, and the shared `theme.css` rules the brief protects.

## 4b. Review round 1 — what it changed (2026-11-17)

The reviewer blocked the artifact on one factual error I had repeated from the brief without
measuring, and on a missing task. Both are fixed:

- **§2's footer row was wrong for staff and student.** There is no `is-primary` and no
  `classList.toggle` anywhere in either blade — the brief's §5 claim is stale, and I re-stated it
  as a fresh measurement. Only the PL's `Approve` was really unfilled. T10a/T10c are therefore
  *verify*, not port; AC5 was already satisfied for two of the three.
- **The email chip**: decision 5 now states it explicitly — the strip carries the proposer's
  **name only**, and the staff ID + the copy-email chip move into `Class Info` (which is what
  `tests/request-approval.spec.ts` then had to be re-pointed for).
- **A test-update task was missing entirely.** Five receipts go red under this lan's decisions and
  now have their own task (T10f): `request-approval.spec.ts` (strip contents, email count, 3 → 4
  tabs), `request-cancel-pending.spec.ts` (2 → 3 tabs), `replacement-home-working-days.spec.ts`
  (the countdown leaves the date row), and the Feature parity test that pinned the literal tab
  labels.
- **Recorded:** `DetailModal.render` activates tab index 0, and the student page falls back to
  `class-info` when no slot was proposed — so AC1's "where the slot exists" has a defined answer
  (`original` leads on the two staff pop-ups too, because they only add `requested` when one
  exists).

## 5. Open question (the user's, not mine)

**D1** — should the student's `Overdue` disappear on an approved/completed row (matching staff), or
stay as it is? One line either way; it changes what a student sees today.

## 6. Tasks

| # | Work | Files |
|---|---|---|
| T10a | Staff pop-up: short labels, `Class Info` tab, strip → `Status`·`Conflict`, `Days Left` row, drop the `is-primary` toggle on `Close` | `replacement-home.blade.php` |
| T10b | PL pop-up: short labels, `Class Info` tab (Staff + Cohorts + Students), strip → `Staff`·`Status`·`Slot Validity`, `Days Left` (pending), `Approve` filled | `request-approval.blade.php` |
| T10c | Student pop-up: short labels, strip → `Status`·`Conflict`, `Days Left` row, `Close` never filled | `replacement-history.blade.php` |
| T10d | Verify at 402 / 768 / 1440: tab row on one line, strip order, footer primary, pinned regions | — |
| T10e | Changelog ×3, apply log, survey report's modal row | `page-changelogs/`, `docs/` |
| T10f | Re-point the five receipts this lane's decisions invalidate (see §4b) | `tests/*.spec.ts`, `tests/Feature/*` |

**Effort:** one session. **Risk:** medium — three blades against one renderer, which is where the
reviewer caught four blockers on task 11. The reviewer runs before the code lands.

## 7. Not in this lane

The timetable class pop-up, the confirm dialogs, the cards, the tables, the badges, `theme.css`'s
detail rules, the 680px width, `.detail-facts` geometry.
