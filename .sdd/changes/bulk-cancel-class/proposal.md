# proposal — `bulk-cancel-class` (2026-11-10)

**Approval-gated. No code written yet.** Baseline: `explore-brief.md` in this folder — twenty user
rulings taken in a grilling session on 2026-11-10, with the code and demo data measured that day.

## Why

Cancelling is the one staff action that still costs a click per class. A lecturer who is away for three
days has to open each class, press *Cancel Class?*, pick the reason, confirm, and repeat — for
**10 classes a week** on My Timetable (measured: Surayaini, staff 5425). Worse, the same button is
currently **wrong on a replacement block**: it writes an exception for the week you are looking at (the
landing week), leaves the approved request holding its slot, and the block returns on reload.

The brief asks for one honest bulk action. Because a class can already be moved by an approved request,
"cancel" has to mean "cancel the class **and** close whatever request it has", or the system ends up
believing two opposite things about one occurrence.

## What changes (behaviour, not implementation)

1. **A new button in the semester bar** of My Timetable only, right of Today, error colour: **Cancel
   Class**. It is enabled only when the week on screen contains at least one cancellable class; otherwise
   it is inert with a tooltip explaining why and naming the first week that can be cancelled.
2. **One screen picks the classes.** The box opens on the week you were viewing and offers a *From* /
   *To* date pair plus a list of days; each day has a header tick ("all classes that day") and each class
   a row tick. So the three asked-for modes are three ways of ticking the same list:
   one day · a whole range · specific classes inside a range.
3. **Every row shows what it is**: time, module code + type, venue, **the student group(s) it serves**
   (`No group` when it has none), and a badge — `Normal`, `Replacement`, `Already cancelled`, `Too old`,
   `Holiday`. Non-cancellable rows are greyed and cannot be ticked, and the reason is written next to
   them. Days with no class still appear, marked `No class`, so the list matches the calendar.
4. **One reason for the batch** (the 8 the modal already offers, `Other` needing its detail), then one
   confirm. The decision stays **FINAL** — the shared irreversible line is shown before the button.
5. **Cancelling a class cancels its request too.** If the occurrence has a **pending** or **approved**
   request, that request is closed in the same transaction: status `cancelled`, `decided_at` set, its
   slots released, its junction rows deleted. No new status value is invented.
6. **The old reason line is corrected.** An approved request left a `class_exceptions` row stamped
   `official_event` for the original week; cancelling the replacement rewrites that row's reason to the
   real one (e.g. `annual_leave`) instead of leaving a cause that never happened.
7. **All-or-nothing.** The box pre-checks every selected row; the action re-checks (ownership, the
   2-working-day window, holidays, duplicates, the reason) and writes in **one transaction**. If any
   item is invalid, nothing is written and the box names the blockers.
8. **The receipt is visible.** After success the box lists every cancelled class with totals
   (classes · days · hours · requests withdrawn), follows them with one plain sentence — *"Students of
   each class have a message in their bell, and the PL has one for each withdrawn request."* — and
   offers **View my timetable**, which closes the box, refreshes from the server and lands on the first
   cancelled week.
9. **The old single-class path is fixed** by the same rule: pressing *Cancel Class?* on a replacement
   block now closes its request instead of writing a useless exception.
10. **People are told, per class, with the right words**: students get *Scheduled class cancelled* for a
    normal class and *Replacement class cancelled* (structured tip naming the landing slot) for a closed
    approved request; the department's PLs get one row per closed request. **No email is sent** — no
    cancel path in this system sends mail today, and this feature does not start.

## Rules the action owns (not the page)

| Rule | Refusal |
|---|---|
| The occurrence belongs to the caller | `not_owner` |
| The class date is on/after today − 2 working days | `too_old` |
| The day is not a holiday or Sunday | `holiday` |
| The reason is one of the 10 schema values (8 offered) | `invalid_reason` |
| The occurrence has no existing exception — except the `official_event` one an approval wrote, which is corrected (R18) | `already_cancelled` |
| Every item passes, else nothing is written | `blocked` (with the list) |

## Data model

- **One additive migration**: `notifications.batch_id` — nullable string, indexed with `user_id`
  (`[user_id, batch_id]`) so the later "group in one go" is one query. Nothing else changes: no new
  status, no new column on `class_exceptions`, no new table.
- **Audit**: one `class_cancelled` row per class, `details = {class_session_id, week_number, reason,
  batch, request_id?, closed_request?, previous_reason?}`. No new `action` value (the CHECK is fixed).
- **Demo DBs**: the migration is additive; it still follows the standing ritual — verified `pg_dump -Fc`
  snapshot, rehearse on `class_replacement_testing`, print the target, plain `migrate`, row-count
  receipt + a `docs/demo-db-data-changes.md` line. **User-gated.**

## Sequencing (one concern per commit)

1. `refactor(oop): one slot-release action for every caller` — `ReleaseRequestSlots`; the three existing
   actions call it; their tests are the proof (no behaviour change).
2. `feat(db): notifications carry the batch they were written for` — the additive migration (+ receipt).
3. `feat(cancel): a class cancel closes the request it holds` — `CancelClassBatch` + the trait method +
   the fixed single-class path + the two notification kinds, with PHPUnit receipts.
4. `ui(cancel): the semester bar can cancel a whole absence` — the button, the partial, the controller,
   the success/refusal states + the Playwright receipt.
5. `docs:` changelog entries (My Timetable, notifications) + the archive question at the end.

## Files in scope

| Layer | Files |
|---|---|
| Action | `app/Actions/ReleaseRequestSlots.php` (new), `CancelClassBatch.php` (new), `CancelClass.php` (uses it), `CancelPendingRequest.php`, `RejectRequest.php`, `AutoRejectRequest.php` (callers) |
| Bridge | `app/Concerns/CancelsClasses.php` (one new renderless method, the old one routed) |
| Notifications | `app/Support/Notifications/NotificationEmitter.php` (+2 kinds, `batch_id` written), `NotificationTip.php` (two tips) |
| DB | one migration for `notifications.batch_id` |
| UI | `resources/views/livewire/my-timetable.blade.php` (button + include), `partials/ui-bulk-cancel-modal.blade.php` (new), `public/js/ui-common.js` (`BulkCancelModal`, shared reason list reused), `public/css/theme.css` (button/badge/picker states, tokens only) |
| Tests | `tests/Feature/CancelClassBatchTest.php`, `tests/Feature/ReleaseRequestSlotsTest.php`, additions to `NotificationsBridgeTest`; `tests/bulk-cancel.spec.ts` (Playwright, guarded) |
| Docs | `page-changelogs/my-timetable-changelog.md`, `page-changelogs/notifications-panel-changelog.md` |

## Out of scope

- No new button on Cohort Timetable, Venue Timetable or My Replacements (R19).
- No email for cancellations.
- No "group in one go" display yet — only the `batch_id` that makes it exact later (R5/R6).
- No change to the cancel window, the holiday ban, the reason vocabulary or the FINAL rule.
- No undo (Cancel Class is FINAL since 2026-11-05).

## Acceptance criteria

1. **Button state** — in the bar, right of Today, error colour; enabled only when the viewed week holds a
   cancellable class; when disabled it is inert (`cursor: not-allowed`) and its tooltip names the reason.
2. **Three modes, one screen** — a day header tick selects exactly that day's classes; a range plus
   individual ticks selects only the ticked classes; the range opens on the viewed week.
3. **Gating** — the confirm button stays disabled until at least one class and one reason are chosen;
   `Other` requires its detail. The irreversible line is visible before the button.
4. **Pre-check** — a row that fails a rule (not yours, already cancelled, holiday, too old) is greyed,
   cannot be ticked, and says which rule it failed.
5. **All-or-nothing** — a batch containing any invalid item writes nothing and reports the blockers; a
   valid batch writes every item in one transaction.
6. **Request closure** — a class with a pending or approved request closes it: status `cancelled`,
   `decided_at` set, slots back to `available` with a version bump, junction rows deleted.
7. **Reason correction** — an approval-stamped `official_event` exception is updated to the batch's
   reason, with the audit recording `old → new`.
8. **Audit** — one `class_cancelled` row per cancelled class, all carrying the same `details.batch`.
9. **Notifications** — students: one row per class, with a structured tip that differs for a replacement
   cancellation; PLs: one row per closed request; every row of the batch carries `batch_id`.
10. **No mail** — the feature sends no email (a test asserts no mailable is queued by a cancel).
11. **Receipt** — the success box lists every cancelled class, its totals and who was told, and
    *View my timetable* closes the box, refreshes from the server and lands on the first cancelled week.
12. **The old button is honest** — cancelling a single replacement block closes its request; a normal
    class behaves exactly as before.
13. **No regression** — `ReleaseRequestSlots` is the only release implementation, and the three existing
    callers' tests stay green and unchanged.
14. **Gates** — pint, phpstan 0, full PHPUnit, `node --check`, `view:cache`, and a Playwright receipt on
    `:8030` covering: enable/disable, one day, a range, specific classes, refusal, success + jump.

## Risks

| Risk | Mitigation |
|---|---|
| The refactor touches three shipped actions | Pure refactor commit first; existing tests unchanged and green before the feature starts |
| A wide batch can close several requests | All-or-nothing + the receipt names how many requests were withdrawn; the PL is told per request |
| Notification volume (one row per class per student) | Explicit ruling (R5); `batch_id` prepares a later display grouping without data change |
| Cancelling an approved replacement removes a class students were promised | The *Replacement class cancelled* row says exactly that, and the PL is told the request is withdrawn |
| The migration touches demo DBs | Additive column + the standing snapshot/rehearse/receipt ritual, user-gated |

## Review

`/sdd-propose` reviewer round pending (separate read-only process). Review log: `review-log.md`.
