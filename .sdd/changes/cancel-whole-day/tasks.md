# Tasks — cancel-whole-day

Approved for **planning only**. No task below may be started until the user says apply.
Each task is one concern and one commit, in this order.

- [x] **T1 — one slot-release helper** (design F1/F4)
      New `app/Actions/ReleaseRequestSlots.php` extracted from `CancelPendingRequest`, `RejectRequest`,
      `AutoRejectRequest`; all three call it. Behaviour identical.
      Receipt: those three actions' existing tests pass **unchanged**; `ReleaseRequestSlotsTest` proves the
      freed ids and the version bump.
      Commit: `refactor(oop): one slot-release action for every caller`

- [x] **T2 — a cancel closes the request it holds** (design F2/F3)
      New `CancelClassBatch` with the six rules, all-or-nothing, request closure, one audit row per class,
      notifications after commit; `CancelClass` delegates with a one-item set.
      Receipt: `CancelClassBatchTest` — the six refusals, the atomic write, the closed request (status,
      `decided_at`, slots, junction), the audit rows.
      Commit: `feat(cancel): a class cancel closes the request it holds`

- [x] **T3 — the server can name my classes for a day** (design F5)
      `myDayClasses()` and `cancelClassDay()` on `CancelsClasses`; ownership-scoped, exception-excluded,
      requests eager-loaded, the opened class included.
      Receipt: `MyDayClassesTest` — a colleague's class of the same day is absent; an exception row
      excludes a class; `request` answers pending/approved/none; cohorts and students match the staff page.
      Commit: `feat(cancel): the server can name my classes for one day`

- [x] **T4 — the block** (design F6/F8, decisions 1–3, 10–15)
      The partial's shell, the block CSS (tokens only), the client render: sentence, rows in the
      `h:mm ~ h:mm · CODE(T) · ROOM · COHORT` format, `n cohorts` + hover, the amber pill stacked under
      the cohort, select-all with the half-tick, the fold chevron, the two live counts, the button text.
      Receipt: Playwright S1–S4, S8 (appears/hides, unticking, folding, both counts, 390 px).
      Commit: `ui(cancel): the modal offers the whole day`

- [x] **T5 — the day confirm and its success screen** (design F7, decisions 4–5)
      `confirm()` branches on the tick; the day success screen (`✓ N classes cancelled`, the date, the
      reason, the withdrawn count, the row list with the red pill, one Close); Close refreshes from the
      server; the ledger gets one entry per class.
      Receipt: Playwright S5 on `class_replacement_testing` — 4 rows cancelled, 1 request withdrawn, the
      grid shows all four cancelled after Close; plus S6 (a server refusal writes nothing).
      Commit: `ui(cancel): a confirmed whole day cancels every class and says so`

- [x] **T6 — receipts, gates, changelogs**
      `NotificationsBridgeTest` additions (one student row per cancelled class, one per withdrawn
      request), `node --check public/js/ui-common.js`, `composer run lint:check`, phpstan 0, `view:cache`,
      full PHPUnit on `class_replacement_testing` (the known `ReplacementHistoriesTest` error is the only
      red), Playwright S7 on the Cohort and Venue timetables.
      Changelog: `page-changelogs/my-timetable-changelog.md` (the decision record, the fifteen rulings,
      the reuse of the parked lane's action, and what stayed out).
      Commit: `docs: the whole-day cancel, recorded`

## Definition of done

Spec R1–R7 hold on the demo data on all three timetables; S1–S8 are green or explicitly waived by the
user; nothing of the parked `bulk-cancel-class` UI has leaked in; the single-class flow is unchanged.

## Parked behind this lane

- the parked `bulk-cancel-class` UI (semester-bar button, From/To picker, `notifications.batch_id`),
- any "group in one go" display of a batch,
- email for cancellations (none is sent today, and this lane does not start one).

## Applied 2026-11-11 — what the build actually did

- **T1** `ReleaseRequestSlots` extracted with a `strict` policy switch (the three callers did NOT
  behave alike: strict for RejectRequest, skip for CancelPendingRequest / AutoRejectRequest). Design
  amended to option A before any edit; the three callers' suites stayed green and unchanged.
- **T2** `CancelClassBatch` + `CancelClass` delegating (`enforceWindow: false`, because the
  single-class path never had a server-side window — spec R7.1). Request closure included.
- **T3** `myDayClasses()` / `cancelClassDay()` on `CancelsClasses`, so all four surfaces share them.
- **T4/T5** the block, the day confirm, the day success screen (one Close, grid refreshed from the
  server) — verified live on `:8000` and end-to-end on the disposable DB (`tests/cancel-whole-day.spec.ts`:
  sentence, 3 rows, `Yes, Cancel 4 Classes`, partial tick → 3, success `✓ 4 classes cancelled`, 0 left).
- **Rev 4** (user, after seeing it): unticked + folded default; "on the same day"; cohort hovers carry
  the full name; make-up blocks are a note, never a row.
- Receipts added: `ReleaseRequestSlotsTest` (4), `CancelClassBatchTest` (7), `MyDayClassesTest` (6),
  `tests/cancel-whole-day.spec.ts`. Full suite **558 / 557 passing, 1 error** = the pre-existing
  `ReplacementHistoriesTest` failure.
