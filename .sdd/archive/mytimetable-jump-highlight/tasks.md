# Tasks — `mytimetable-jump-highlight`

Each task ≤ 2 h. Receipts are recorded in `review-log.md` under **Apply**.

**A1 is answered** (2026-11-04): the ACTION icon is the same jump — tooltip `Go to My Timetable`, on
Approved / Completed rows only. **Apply completed 2026-11-04** (receipts in `review-log.md`).

## Batch 1 — the identity (server + shared client)

- [x] **T1** `ResolvesTimetableTimeline.php` — stamp `$ev['requestId'] = (int) $r->id;` in the approved
      branch of `buildEventsByWeek()`. *Receipt:* a `php artisan tinker`/test dump of one approved
      request's event array showing `requestId`, `status = 'replacement'`, and the other keys untouched.
- [x] **T2** `ui-common.js` `buildTimetableGrid()` — set `div.dataset.rid` when the event carries
      `requestId`. *Receipt:* `node --check` + a DOM read showing `data-rid` on a replacement block and
      **absent** on the class's natural block in the same week.
- [x] **T3** `ui-common.js` — add `goToReplacementInTimetable(requestId, week)` beside
      `goToReplacementFor()`. *Receipt:* `node --check`; the built URL printed from the console.

## Batch 2 — the arrival arm

- [x] **T4** `ui-common.js` — add `applyReplacementHighlight()` and register it in the existing
      `DOMContentLoaded` block at the same 150 ms tick. Copy the structure of `applyNotifHighlight()`
      with **one deliberate difference**: read `repl-w` **before** deleting it from `params`
      (see `design.md` §3.3 — the notification arm's ordering bug must not be reproduced).
      *Receipt:* `node --check`; console trace showing `repl-hl`/`repl-w` absent from `location.search`
      on arrival.
- [x] **T5** Verify the no-fallback rule. *Receipt:* navigate with a bogus `repl-hl=999999` on a week
      that holds a replacement → nothing flashes, no console error.

## Batch 3 — My Replacements page

- [x] **T6** `replacement-home.blade.php` — `CAL_SVG` beside the other three inline icons (same 15 px /
      stroke 2.5 voice).
- [x] **T7** Modal footer — `#qvTimetableBtn` after `#qvArrangeBtn`, `style="display:none"`, calendar
      icon + `Go to My Timetable`.
- [x] **T8** Footer gate — visible only for `approved` / `completed`, beside the two existing gates.
- [x] **T9** `qvGoToTimetable()` — resolve the record by `currentOccurrenceId`, close the modal, call
      the shared helper.
- [x] **T10** `actionCell()` — second button on the approved/completed branch (eye first, then calendar),
      tooltip + `aria-label` `Go to My Timetable`, `stopPropagation`.
- [x] **T11** CSS — `.col-action` 6 % → 10 %, `#timetable .col-reason` 22 % → 18 %. *Receipt:* the
      eight-width overflow measurement (see T12) — 0 overflowing cells, `col-action` holds both buttons
      at 1280 px.

## Batch 4 — receipts

- [x] **T12** New spec `tests/mytimetable-jump-highlight.spec.ts` — planted approved request
      (id-keyed fixture + before/after sweep, the house pattern), asserting S1–S8 of the spec,
      including "only the clicked block flashes" and the eight-width overflow sweep.
- [x] **T13** Re-run the neighbouring suites: `replacement-home-qv.spec.ts`,
      `replacement-home-status-badges.spec.ts`, `request-completed-status.spec.ts`,
      `request-cancel-pending.spec.ts`, `ui-regression.spec.ts`, `notifications-island*.spec.ts`.
      *Receipt:* pass counts, not adjectives.
- [x] **T14** Gates: `composer run lint:check`, `vendor/bin/phpstan analyse --memory-limit=1G
      --no-progress`, `node --check public/js/ui-common.js`, full `php artisan test` on
      `class_replacement_testing` (backgrounded, polled — never alongside a filtered run).
- [x] **T15** Changelogs — `page-changelogs/replacement-home-changelog.md` and
      `page-changelogs/my-timetable-changelog.md` (the arrival arm is a my-timetable behaviour).
- [x] **T16** Apply record in `review-log.md` + `sdd.yaml` → `applied`, then ask about archiving.

## Deliberately not scheduled

- The notification arm's `notif-w` ordering bug (finding **F1**, below) — a one-line fix in the same
  function, but it belongs to the notification lane; offered separately.
- Removing the pin icon from `/my-replacements` (open parity question from the venue-code lane).
