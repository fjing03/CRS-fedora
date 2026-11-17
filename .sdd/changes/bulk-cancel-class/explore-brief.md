# explore-brief — `bulk-cancel-class` (2026-11-10)

**Status: grilled, awaiting `/sdd-propose`.** Twenty rulings were taken in a grilling session on
2026-11-10. Every fact below was measured in the repo or the demo database on that day, not remembered.
No code has been written.

Prompt sources: `prompts/Bulk Cancel Class.md` (the brief as pasted) and the follow-up it replaced
(a prototype-only version was dropped by the user in favour of a working feature).

## 1. What the user asked for

On **`my-timetable-ui`**: a **Cancel Class** button (error colour) inside the `.semester-bar`, right of
the Today button. A working modal + workflow that handles **staff absence across several days**, where a
day may hold several classes, with three ways of choosing:

1. all classes for one day,
2. all classes in a date range,
3. only specific classes inside a date range,

and one action that cancels the whole selection instead of one by one. Selected classes must be
validated, and invalid or unauthorised cancellations must be prevented.

## 2. What the environment says (measured 2026-11-10)

| Fact | Where |
|---|---|
| A cancel today = **one occurrence**: one `class_exceptions` row (unique per session + week), one `audit_logs` row (`class_cancelled`), one notification fan-out | `app/Actions/CancelClass.php` |
| The page calls it through one trait method: `cancelClass(sessionId, weekNumber, reason, otherDetail)` | `app/Concerns/CancelsClasses.php` |
| Cancellable today (client gates): **own** class, status `normal` **or** `replacement`, day not a holiday/Sunday, class date **≥ today − 2 working days** | `ClassCancellation.isCancellable()` in `public/js/ui-common.js` |
| The 2-working-day window and the holiday ban are **client-only** — the action checks neither | `CancelClass::__invoke()` |
| `class_exceptions.reason` CHECK allows 10 values; the modal offers **8** (`public_holiday`/`official_event` never offered) | `2026_10_27_000001_widen_cancel_reason_and_audit_checks.php`, `ClassCancellation.REASONS` |
| `replacement_requests.status` CHECK: `pending · approved · rejected · cancelled · completed` — no "closed" state exists | same migration |
| `time_slots.status` CHECK: `available · pending · occupied` | `2026_08_03_000006_create_time_slots_table.php` |
| Approving a request **already stamps** a `class_exceptions` row for the original week with reason `official_event` — that is what removes the original block | `ApproveRequest` (~:262) |
| A **replacement block** on My Timetable is the *same* session rendered at the approved landing slot (`status: 'replacement'`, carries `requestId`) | `App\Concerns\ResolvesTimetableTimeline` (~:360) |
| `audit_logs.action` is a CHECK: `submitted · approved · rejected · cancelled · completed · occ_conflict · class_cancelled · class_cancel_undone` | live constraint `audit_logs_action_check` |
| The slot-release routine (junction read, lock, version-checked flip to `available`, per-flip audit, junction delete) is written **three times** | `RejectRequest`, `AutoRejectRequest`, `CancelPendingRequest` |
| Notifications: `classCancelled` writes **1 row for the lecturer + 1 per cohort student**; **no email** is sent by any cancel path | `NotificationEmitter` (~:162); the only three mails are submit / decide / approval-notice |
| Student tips are chosen by **title** (`'Scheduled class cancelled'` → kind `class_cancelled`, schedule line = the ORIGINAL class facts) | `NotificationTip` (~:62, ~:130) |
| `.semester-bar` is one include (`partials/ui-week-nav` with the Today button); the shared cancel modal is used by **three** pages | `resources/views/livewire/my-timetable.blade.php:48`, `partials/ui-cancel-class-modal.blade.php` |
| The page already receives **all 14 weeks** of events client-side (`$eventsByWeek` → `eventsData`), so a range picker needs no round trip | `my-timetable.blade.php` (~:110) |
| Demo load: Surayaini (5425) **10 classes / week** across 5 days (13 h), holding **2 approved + 1 pending** requests; Lim Jia Zheng (5770) 7 / week (10 h), 2 pending | `class_replacement_fresh`, 2026-11-10 |

### The bug the brief walks into

The single-class Cancel sends `weekIndex + 1` — **the week on screen**. On a *replacement* block that is
the **landing** week, so today it writes an exception for a week where that class never meets and the
approved booking is untouched: the block looks cancelled until the page reloads, and the request still
reads *Approved*. "Cancel a replacement class" therefore does not work today — the feature has to
**replace** that path, not extend it.

## 3. The rulings (all twenty, in order)

| # | Ruling |
|---|---|
| R1 | Cancelling a **replacement** class also closes its **approved request** (C) |
| R2 | The closed request ends as a normal **`cancelled`** request: `decided_at` set, slots released `occupied → available` (version++), junction rows deleted, audit `cancelled` with `details.via = 'class_cancel'` and `cancelled_by = 'proposer'`. **No new status, no flag column** (A) |
| R3 | A class holding a **pending** request also closes that request the same way (B) |
| R4 | **One reason for the whole batch** (A) — stored on every cancelled class |
| R5 | **One bell row per class** now; "group in one go" is a *later, display-only* step — no data change (C) |
| R6 | Add a nullable **`notifications.batch_id`** now so that later grouping is exact, not guessed (A) |
| R7 | The **PL gets one bell row per closed request** (no email) (A) |
| R8 | **All-or-nothing**: the modal pre-checks every row, the action re-checks, one transaction, any violation writes nothing and names the blockers (A) |
| R9 | **One screen** picker: a date range + per-day tick + per-class tick; the three modes are just ways of ticking it (A) |
| R10 | Opens on the **week you are viewing**; range capped only by the 14-week semester (A) |
| R11 | **New partial + own controller** (`BulkCancelModal`); shared pieces (reason list, irreversible line, fact formatting, tokens) reused, never copied (A) |
| R12 | **One `class_cancelled` audit row per class**, all sharing `details.batch`; no summary row, no new action value (A) |
| R13 | The **single-class path is fixed too** — it uses the same server path as the bulk one (N = 1) (A) |
| R14 | A cancelled replacement reads as **"Replacement class cancelled"**, naming the landing slot, with a **properly structured hover tip** (B) |
| R15 | **Refactor first, own commit**: one shared `ReleaseRequestSlots` used by all four callers, existing tests as the proof (A) |
| R16 | After success the grid **re-reads from the server** (one refresh when the box closes) (A) |
| R17 | The bar button is **enabled iff the week on screen has a cancellable class**; otherwise disabled (`cursor: not-allowed`) with a tooltip saying why (A+B) |
| R18 | The existing `official_event` exception is **corrected in place** to the batch's real reason, audited `old → new` (A) |
| R19 | The button lives **only on My Timetable**; cohort/venue keep their per-class cancel (A) |
| R20 | The success box **lists every cancelled class** plus counts and a **"View my timetable" jump** (A) |

## 4. Shape of the change

1. **Refactor** (no behaviour change): `App\Actions\ReleaseRequestSlots` — takes a request, the expected
   slot status and the audit wording; `CancelPendingRequest`, `RejectRequest`, `AutoRejectRequest` and
   the new class-cancel path all call it.
2. **Migration** (additive): `notifications.batch_id` nullable string; written only by the bulk path.
3. **Action** `App\Actions\CancelClassBatch` — validates ownership, the 2-working-day window, holidays,
   the reason and duplicates; then in one transaction: exceptions (or reason correction), request
   closures, slot releases, audits with `details.batch`, notification rows with `batch_id`.
4. **Trait method** for the page (renderless) + the promise that the server response is the truth.
5. **UI**: `.semester-bar` button (R17 state), `partials/ui-bulk-cancel-modal.blade.php`, `BulkCancelModal`
   in `ui-common.js` (picker, pre-check, reason gate, success/refusal states, refresh on close).
6. **Notifications**: a `replacement_class_cancelled` kind for students (structured tip), a
   `request_withdrawn` kind for PLs (one row per closed request).
7. **Single path**: the shared modal's confirm routes replacement blocks to the same action.

## 5. Traps and risks

- **`class_exceptions` is unique per (session, week)** — never insert twice; the replacement path
  updates the existing row (R18) instead.
- **`replacement_requests.status` has no new value to invent** (R2) and `time_slots.status` is
  `available/pending/occupied` — the release must flip from `occupied`, unlike today's pending path.
- **`notifications.batch_id` is a demo-DB schema change** → snapshot (`pg_dump -Fc`, `pg_restore --list`),
  rehearse on `class_replacement_testing`, print the target, plain `migrate`, row-count receipt +
  `docs/demo-db-data-changes.md`.
- **The approval-stamped exception** already exists for approved requests, so R18 is an UPDATE, and it
  must be scoped `where class_session_id + week_number`.
- **Notifications are matched by title** (`NotificationTip::kindFor`) — a new title *without* a new arm
  silently becomes the legacy `title — body` tip (R14 forbids that).
- **No email** exists on any cancel path; do not add mail without the user's word.
- **The 2-working-day window must move into the action** (standing rule: a rule the page enforces must
  exist in the action). A bulk write is the moment to close that gap.
- **Demo data**: cancellations write to whichever DB the server points at; receipts must run on
  `class_replacement_testing` only, and the fixture must not use Sunday (day 6 is refused).

## 6. Acceptance criteria (draft — the proposal numbers them)

1. The bar button sits right of Today, error colour, and is enabled only when the viewed week holds a
   cancellable class, with a tooltip when it is not.
2. One day, a whole range, and specific classes inside a range can all be selected on one screen, and
   the header checkbox of a day ticks that day's classes only.
3. The confirm button stays disabled until at least one class and a reason are chosen ("Other" needs its
   detail).
4. Pre-checked refusals (not mine / already cancelled / holiday / older than the window) are visibly
   disabled with the reason, and the batch cannot be confirmed while one is selected.
5. A successful batch writes one exception per class (or corrects the existing one), closes the pending
   or approved requests, releases their slots, and audits one row per class sharing a batch id.
6. Nothing is written when any item is invalid (all-or-nothing), and the refusal names the blockers.
7. Students get one "Scheduled class cancelled" row per normal class and one "Replacement class
   cancelled" row (structured tip naming the landing slot) per closed approved request.
8. Each closed request sends one bell row to the department's PLs, with `batch_id` written on every row
   of the batch.
9. The success box lists every cancelled class with counts and offers "View my timetable".
10. Cancelling a single replacement block from the old button now also closes its request (no orphan).
11. `ReleaseRequestSlots` is the only slot-release implementation; the three existing callers behave
    byte-identically (their tests unchanged and green).
12. Gates green: pint, phpstan 0, full PHPUnit, `node --check`, `view:cache`, plus a Playwright receipt
    for the modal flow on `:8030`.

## 7. The five small questions (answered 2026-11-10)

| Question | Answer |
|---|---|
| Do days with no class appear in the range list? | **Yes** — the day shows *"No class"* (Sunday says it is a Sunday), so the list matches the calendar |
| Does a picker row name the student group? | **Yes** — every group the class belongs to (`DFT2(S1)G1 · DSF2(S1)G2`); a class with none says *"No group"*. The receipt repeats it |
| `batch_id` type | A nullable UUID string written by the bulk path only; no new table (technical default) |
| Does the success box say who was told? | **Yes**, one plain sentence: *"Students of each class have a message in their bell, and the PL has one for each withdrawn request."* No counts, no email |
| Phone scrolling | One scroll surface: the sheet scrolls, not the list inside it (technical default) |

**Measured to answer the "who is told" question** (demo, 2026-11-10): one cancelled class writes **1 bell row for the lecturer + 1 per student** — 26 rows for an AMSE1003 lecture (25 students), 13–16 for the smaller ones; **no email**, and the PL gets nothing from a plain cancel (that fan-out was reversed on 2026-11-03). A 10-class batch therefore lands around 150–250 bell rows. The stale "THREE fan-outs" comment in `CancelClass` is corrected when that file is touched.
