# Proposal — notif-recipient-rulings

**Date:** 2026-11-03 · **Stage:** proposal (batch 1) · **Status:** FROZEN 2026-11-03 (user: "A)")
**Ask:** `prompts/pi-notification-recipient-rulings.md` — the current version (479 lines,
with §6's role matrix and the rewritten §14).
**Baseline:** `explore-brief.md` (same folder) — every fact below was read from the code.

---

## Why

The bell/panel is the system's only push channel, and on four of five events it tells the
wrong story.

1. **A submit is silent for the proposer.** A non-PL lecturer sends a replacement request
   and gets nothing back. Meanwhile every PL in the faculty — any department — receives a
   queue row.
2. **A proposer who is a PL is emailed their own request.** The submit mail loop sends
   `ReplacementSubmitted` to every PL, the proposer included.
3. **An approval lands twice for the owner.** `requestDecided(…'approved')` writes
   "Replacement approved" for the proposer, and `approvalNotice()` writes "Replacement
   class updated" for each cohort student; when the owner is the proposer — the normal
   case — they hold two rows for one decision, and the owner's row points at the request
   history instead of the timetable that actually changed.
4. **A cancellation spams PLs who cannot act.** The 2026-10-30 "THREE fan-outs" ruling
   added the owner department's PLs to the cancel fan-out. A career PL cannot do anything
   about someone else's cancelled class.
5. **The second line of a row is inconsistent.** Some rows show a name, some show
   nothing, none shows a staff id — two lecturers called "Lim" look identical, and a PL
   reading "submitted by …" cannot tell which staff id filed it.

## What — the five rulings (quoted; not re-opened)

| # | Ruling (user, 2026-11-03) | Consequence in this lane |
|---|---|---|
| **R1** | The **non-PL proposer** gets a confirmation row: title `Replacement request submitted`, body `<CODE> (<TYPE>) · W<w> · awaiting PL approval`, link `/my-request-history-ui`, `type=update`. A **PL proposer keeps their single existing queue row** — do **not** add a second row and do **not** filter the PL fan-out. | additive: one conditional row at the end of `requestSubmitted()` |
| **R2** | The proposer is **never emailed** their own "new replacement request", whatever their role. Skip them in the mail loop only; every other PL still gets the mail. | one identity skip in the submit action's mail loop |
| **R3** | **Merge.** The owner gets **one** row: title `Replacement class updated`, body `<classFact> · approved by <decider> · your timetable is updated`, link `/my-timetable-ui`, `type=approved`, carrying `request_id` and `class_session_id`. The separate `Replacement approved` owner row is removed. Cohort students keep their existing `approvalNotice` row unchanged. | new `replacementConfirmed()`; the approve action calls it once, before the cohort loop |
| **R4** | Class cancelled → **owner + cohort students only**. Delete the department-PL fan-out entirely. The owner is the actor and is still notified; a PL who owns the class is the owner, so they still gets their row. | `classCancelled()` loses step 3 |
| **R5** | Every notification type, **every state** (unread, read, decided, inactive, hidden, manage-mode pick) shows the "other party" staff line as `Name (staff_id)`: PL/student rows → whoever the row is about (proposer on request rows, class lecturer on class rows); staff self rows → the PL who acted; a self-only event → **omit** the line. | a computed `staff_line` per row, rendered by the shared partial |

### Who receives what, after this lane (§6's role matrix, restated as the acceptance test)

| Situation | PL | Normal staff | Student | Link |
|---|---|---|---|---|
| New request submitted | yes — every PL, every department (a PL proposer keeps this copy) | yes, **if they are the proposer** (new) | no | PL `/request-approval-ui` · proposer `/my-request-history-ui` |
| Request approved | only if they own/proposed it (the merged row) — never as the approver | only if they own/proposed it | yes — every cohort student | owner `/my-timetable-ui` · student `/student-my-timetable-ui` |
| Request rejected | only if they proposed it | only if they proposed it | no | `/my-request-history-ui` |
| Pending > 24 h | yes — every PL, deduped until read | no | no | `/request-approval-ui` |
| Request cancelled by the proposer | no | no | no | nobody (no emitter call) |
| Scheduled class cancelled | only if they own the class (no dept copies) | only if they own the class | yes — every cohort student | owner `/my-timetable-ui` · student `/student-my-timetable-ui` |

No new `type` or `role_view` values. No row is written inside a transaction — every write
stays inside `DB::afterCommit`.

## The explicit reversal

The 2026-10-30 ruling *"class cancellation fans out to the owner + every cohort student +
the department's PLs"* is **reversed by R4**. Its rationale — a PL should know a class in
their department was cancelled — does not survive use: the PL cannot act on it, and the
row competes in the panel with items that need action. The owner-vs-PL dedupe that fan-out
required disappears with it.

## Scope

**In:** `NotificationEmitter` (R1, R3, R4) · `SubmitReplacementRequest` (R1 wiring, R2) ·
`ApproveRequest` (R3) · `NavNotifications` + `notif-row.blade.php` (R5) ·
`NotificationsBridgeTest` + `EmailQueueTest` (receipts) ·
`page-changelogs/notifications-panel-changelog.md`.

**Out (reported, not fixed — §13):** the unscoped approval queue and its self-approval
path; a `submitted` row losing its composed title/description after read; mail templates;
any panel redesign; the demo copy.

**No migration.** `staff_line` is computed per render; `approver_id` already exists and is
stamped by both decisions.

## Risks and how they are held down

| Risk | Mitigation (detail in `design.md`) |
|---|---|
| Owner row duplicated once per cohort | the call sits before the loop, not inside it |
| The new line dies when a row is read | it is a computed attribute, not `_display` |
| A cancellation-only inbox loses every line | the line is attached at **both** exits of `resolveRequests()` |
| The `submitted` row prints its staff line twice | the new line renders only when its **text differs** from `$subtitle` (user refinement, 2026-11-03 — see `design.md` §4.5) |
| The panel starts issuing per-row queries on the 10 s poll | two batched loads only; the ONE-`replacement_requests`-query test must stay green |
| A PL proposer loses their queue row | the PL fan-out is untouched; only an additive row for non-PLs |

## What the user will be asked to review next (batch 2)

`design.md` (the algorithm + test matrix) and `tasks.md` (the checklists) are present in
this folder but marked **unfrozen draft** — they were written for the previous version of
the prompt and will be revised against this proposal once it is agreed.
