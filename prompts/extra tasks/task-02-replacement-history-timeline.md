# Extra task 02 — one honest timeline rail for the replacement modals

> **Status: ⏸ PARKED — do not start unless the user names it.**
> Added 2026-11-02, immediately after the investigation that found the fake date.
> **Current state of the code (user's word, 2026-11-02):** only the student
> page's rail is commented out
> (`resources/views/livewire/replacement-history.blade.php:387`). **All staff
> rails are still live and still carry the defects below** — deliberately not
> touched before the SV demo. This file is the single place they are recorded.

## Why — the verified inventory (all four rails, 2026-11-02)

There are **four** implementations of the same "Submitted → … " rail in the
live app, and none of them reads a real timeline. There is **no** timeline
table/column in the database — the sequence is invented in the browser from a
status flag.

| # | Surface | Code | What it renders today | Defect |
|---|---|---|---|---|
| 1 | Student **Replacement History** modal | `replacement-history.blade.php:387–391` (**commented**) | nothing | The "Submitted" time was the literal **`01 Sep 2026, 09:15 AM`**: the rail read `MockData.cohortTimetable.rsd3g2Flags` (`:329`) and the backend ships that map **empty** (`ReplacementHistory.php:59`), so the fallback always won — while the same modal's *Requested At* row (`:383`) was correct. |
| 2 | **Class-detail** modal (student & staff timetable pages) | `public/js/ui-common.js:1268–1274` | real date, fake stages | "Submitted" is real (`event.requestedAt` ← `ResolvesTimetableTimeline.php:422` ← `submitted_at`, MYT `d M Y, h:i A`), but "In progress" / "Next" are literal strings and every state/dot is hardcoded. Rail is **owner-only** (`:1184`) and **disappears entirely** once the request is decided — no Approved/Rejected step ever. Students never see it (they fail the owner gate), staff see it on their own pending class. |
| 3 | Lecturer **My Request History** | `my-request-history.blade.php:769–773` | dynamic, **wrong times** | (a) `reviewedAt` is serialized **without** the MYT conversion while `requestedAt` has it (`MyRequestHistory.php:81–82`) → steps 2–3 are **8 hours early**. Receipt: request **#24** decided `07:04:42` UTC shows `2 Nov 2026, 7:04 AM`; truth is `3:04 PM`. (b) `CancelPendingRequest` sets `decided_at` (`app/Actions/CancelPendingRequest.php:92`), so a **cancelled** request renders "Under Review — completed ✅ (green)" with the cancellation time, then "Cancelled" with the same time — review that never happened. Receipt: **#25**, cancelled 31 s after submitting. (c) Pending rows show "Under Review" (active) *and* a final step labelled "Pending" — two steps saying the same thing. (d) No `wire:poll`: a decision needs a page reload. |
| 4 | PL **Request Approval** | `request-approval.blade.php:956–960` | one **dead** step | "Viewed" computes `r.viewedAt`, which **does not exist**: `ReplacementRequestMapper::toRow()` emits `requestedAt` / `reviewedAt` / `reviewedBy` only (`:108–111`), there is no `viewed_*` column in the DB, and `viewedIds` is an in-memory `Set` used only for the row's `row-viewed` class (`:560`, `:674`, `:944`, cleared at `:1226`/`:1253`) — never persisted, never sent. So the middle step is **permanently `—` + grey**. The third step is always labelled "Reviewed", never the outcome, so the rail never says Approved/Rejected. No re-render while the modal stays open (payload swapped only on the next fetch, `:1164`). |

Why the student rail was the only one changed: it was the only rail printing a
**provably false value**. The staff rails print real data — wrongly converted,
or with one step that has no source — so removing them without a decision would
have deleted correct information (Submitted / Reviewed times) along with the
defect.

### Adjacent finding (NOT part of this task)

`RequestApproval::rowsPayload()` reads **every** request with no programme scoping
and no exclusion of the PL's own (`app/Livewire/RequestApproval.php:62–67`). In
the demo DB the PL is also the proposer of all three requests, and **#24 was
rejected with `approver_id = 1`** — the same user who proposed it. Self-approval
is currently possible. That is an RBAC/scope issue, not a timeline one: it needs
its own verdict (and probably its own task) — do **not** fold it into this one.

## Goal

One rail, one source of truth, honest in every state:

1. **Submitted** — always; time = `submitted_at` (MYT), never a fallback literal.
2. **Under Review** — active while `pending` (time "Awaiting PL", not a fake
   "In progress" clock); completed once genuinely reviewed, time = `decided_at`
   (MYT). A **cancelled-before-decision** request must NOT show this step as
   completed.
3. **Outcome** — the real one: `Approved` / `Rejected` / `Cancelled` (rejection
   reason reachable in the tip/row), time = `decided_at` (MYT). Only an approved
   request gets "Awaiting Replacement".
4. **Viewed** — either backed by a persisted, audited `viewed_at`, or removed.
   No step may render a dash forever.
5. Nothing is rendered from a mock map; no literal dates anywhere.

## Files that would change

- `resources/views/livewire/replacement-history.blade.php` — restore the rail
  properly (delete the mock `rsd3g2Flags` lookup at `:329`).
- `app/Livewire/ReplacementHistory.php` — expose `reviewedAt` + the raw status
  in `historyRow()` (today it collapses status to `pending|replacement`).
- `public/js/ui-common.js` — class-detail rail (same vocabulary; keep the
  owner-only gate).
- `resources/views/livewire/my-request-history.blade.php` + `app/Livewire/MyRequestHistory.php`
  — fix the `reviewedAt` timezone (step B of the A+B+C brief) and the
  cancelled/pending step semantics.
- `resources/views/livewire/request-approval.blade.php` — the "Viewed" step
  (remove or back it with a real source) and the outcome label.
- One changelog entry per touched page.

## Dependency (do that first)

**The A+B+C timestamp fix** (`prompts/completed prompt (Archieve)/pi-bug-notification-requested-at-timezone.md`)
introduces `App\Support\LocalTime::stamp()` and fixes `MyRequestHistory`'s
`reviewedAt` — which is defect 3(a) above. Build this task on that helper; do
not invent a second timezone path.

## Decisions the user must make (ask before coding)

1. **Student page:** show the outcome step for rejected/cancelled requests too,
   or only for approved ones?
2. **Wording:** keep "Under Review / Awaiting Replacement", or use the status
   vocabulary already used by the badges ("Pending / Approved / Rejected /
   Cancelled")?
3. **Class-detail modal:** same rail for every role that can see the pending
   block (owner only today), or only the proposer?
4. **Past replacements:** does the rail still show for a replacement that has
   already taken place (today: no rail at all)?
5. **"Viewed" step (PL page):** build a real `viewed_at` (first open by a PL,
   audited — and then the lecturer could see it too), or delete the step?
6. **Cancelled:** make "Cancelled" the terminal step and leave "Under Review"
   neutral/not-completed, even though `decided_at` is set by the cancel action?
7. **PL rail outcome:** replace the generic "Reviewed" with the actual
   Approved/Rejected (plus reason tip)?

## Acceptance checklist

Student page
- [ ] Pending: Submitted (real MYT time) → Under Review (active); no invented outcome
- [ ] Approved: … → Approved → Awaiting Replacement
- [ ] Rejected: outcome shown, reason reachable (tip/row)
- [ ] Cancelled: shows Cancelled, no stale "Awaiting Replacement"
- [ ] `grep -n "01 Sep 2026"` returns nothing; `rsd3g2Flags` is not consulted by any live page

Lecturer page (`my-request-history`)
- [ ] Step 2/3 times equal `decided_at` in **MYT** (compare against the DB, not the screen)
- [ ] Cancelled-before-decision shows no green "completed" review step
- [ ] Pending shows one "waiting" step, not two labels for the same state
- [ ] `Reviewed At` row in the tab agrees with the rail

PL page (`request-approval`)
- [ ] No step can render `—` forever: "Viewed" either has a persisted source or is gone
- [ ] The terminal step names the decision (Approved / Rejected), matching the badge
- [ ] A decided request's rail is correct on first open, without a reload

All surfaces
- [ ] The same status renders the same rail on every surface
- [ ] Dark + light, 390 px, no console errors
- [ ] No `wire:poll` surprise: the chosen update model is stated (reload vs poll)
- [ ] `composer run lint:check`, phpstan 0, suite green (quote the measured count)

## Landing

Changelog entries for the touched pages, stale-cache restart (two separate
commands), live receipts as student `25DFT0001`, lecturer `5425`, and the PL
account, one conventional commit (`ui:`), **no push**, then ask about archiving
this file.
