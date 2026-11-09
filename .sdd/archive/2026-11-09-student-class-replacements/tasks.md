# tasks — student-class-replacements

Frozen baselines: `proposal.md` (9 behaviour points, 8 acceptance criteria) + `design.md` (11 sections).
Each task is ≤ 2 hours. **Order: T2 → T1 → T3 → T4 → T5 → T6 → T7 → T8.** Between T1 and T3 the page is
temporarily degraded (the modal lookup and the cards still read the old shape), so those three run
back-to-back and nothing ships in between.

## T1 — the data bridge: occurrences, not requests  *(~2 h)*

`app/Livewire/ReplacementHistory.php`

- [x] Walk my cohort's sessions × weeks 1..`timelineWeeks()`; keep an occurrence only when it has a
      `class_exceptions` row or a `holidays` row lands on the session's day (the staff page's own test,
      including the `official_event`-on-a-holiday rule).
- [x] The same reads the design names: sessions (with module/venue/lecturer), exceptions, holidays
      (from `holidaysForJs()`), the **latest** request per `class_session_id:week_number`
      (explicit column list + `replacementTimeSlot.venue` eager load), `spansForRequests`, one `Venue::whereIn`.
- [x] Row shape (the complete list — nothing else is invented later):
      `id` = `"<sessionId>:<week>"`; `week` (0-based occurrence week); `requestId` (nullable);
      `code`, `name`, `type` (course primitives, read by the sort key and the pop-up); `lecturer`;
      `orig` + `req` (§3 primitives, `req` null when unarranged); `conflictKind` + `holidayName`;
      `status` (`none|pending|approved|completed`, always a **string**); `statusLabel`;
      `statusTip`; `requestedAt`.
- [x] **The mapper is called with `null`, the payload says `'none'`.** The mapper's arms stay null-keyed
      (`statusLabel(null, …, 'student')` → `Awaiting Arrangement`); the row builder maps a cancelled or
      rejected request to `null` before asking it, and only the payload's `status` carries the literal
      `'none'`. Calling the mapper with the string `'none'` would print "None"; calling it with `null` and
      no audience prints "Need to Arrange" — both are forbidden.
- [x] A **pending** row's tip carries the auto-reject deadline exactly as today
      (`approvalDeadlineLabel()` over `req`), because the student tip quotes it.
- [x] Status rules (§2): no request / `cancelled` / `rejected` → `none`; `pending` → `pending`;
      `approved` → `effectiveStatus()` for `approved` vs `completed`. A cancelled or rejected request
      contributes **no** `req` and no `requestId`.
- [x] Student wording comes from the mapper's new audience arm; the raw exception reason never leaves PHP.
- **Done when:** `tests/Feature/ReplacementHistoryRowTest.php` is **rewritten in this task** (it is the
      payload's own receipt) and passes on the testing DB — including the tip (with its pending deadline)
      and the countdown inputs.

## T2 — the mapper's student label arm  *(~30 min)*

`app/Services/ReplacementRequestMapper.php`

- [x] `statusLabel(?string $status, ?string $date, string $audience = 'lecturer')`: the `'student'` arm maps
      the **null** key to `Awaiting Arrangement`; every other output byte-identical for every existing caller.
- [x] `statusVocabulary()` **forwards its audience** to `statusLabel()` (one call path, so no page has to
      remember to ask twice); every existing caller keeps its bytes because the default is unchanged.
- [x] `statusShort()` untouched (design §2).
- **Done when:** the new `tests/Feature/ReplacementMapperStatusLabelTest.php` pins all three arms
      (default → `Need to Arrange`, `'pl'`/default unchanged, `'student'` → `Awaiting Arrangement`) and no
      staff caller changes.

## T3 — the page: columns, cells, filter, toggle, cards, copy  *(~2 h)*

`resources/views/livewire/replacement-history.blade.php`

- [x] Six columns unchanged in order; cells read the new primitives; `statusCell()` renders
      `badge badge-outline status-none` for `none`, `status-*` otherwise; no action column, no row-number column.
- [x] Week filter keys on the occurrence's week; All-option `All Original Class Weeks`; chips follow
      (`Original Class Week: …`).
- [x] `Exclude COMPLETED Status` hides `status === 'completed'` only; persisted view state kept.
- [x] Five cards: `Awaiting Arrangement` · `Pending` · `Approved` · `Completed` · `Class Hours Affected`
      (`Σ orig.duration` over the visible set); `Total`/`sumTotal`/`Upcoming` gone. The Completed card's
      **`description`** (the summary-bar include's hover text) reads verbatim:
      `Replacements that have already taken place — switch Exclude COMPLETED Status off to see them.`
- [x] Sort keys: `course`, `lecturer`, `originalSlot`, `reason`, `requestedSlot`, `status` with
      `{ none: 3, pending: 0, approved: 1, completed: 2 }`; an unarranged row's `requestedSlot` key is
      `null` and sorts **last in both directions** (the existing nulls-last rule); default order = class
      date ascending.
- [x] Copy: empty state + page description per design §8; unarranged requested slot = em dash.
- [x] Row click/keyboard handlers pass the `data-id` **string** (no `Number()`), lookup by `String() ===`.
- **Done when:** the page renders on the demo data with the new cards and no forbidden vocabulary.

## T4 — the pop-up and its one button  *(~1 h)*

- [x] Five groups as today, driven by `orig` / `req`; `Requested Slot` group shows the em dash for `none`.
- [x] `Go to My Timetable` appended to the modal footer **only when `requestId !== null`**, removed on close
      (the `CancelClass.renderButton` house pattern).
- [x] The button calls `goToReplacementInTimetable(requestId, <the 1-based REQUESTED slot week>, '/student-my-timetable-ui')`.
      Note the trap: `row.week` is 0-based while `req.week` is the 1-based slot week the arrival expects —
      passing the wrong one lands on a week with no `data-rid` block and the flash silently never happens.
- **Done when:** an arranged row shows the button, an unarranged row does not.

## T5 — the jump learns the student timetable  *(~30 min)*

`public/js/ui-common.js`

- [x] `goToReplacementInTimetable(requestId, week, basePath)` — third argument optional, default
      `'/my-timetable-ui'`; both staff call sites untouched.
- [x] `applyReplacementHighlight()` accepts `/my-timetable-ui` **and** `/student-my-timetable-ui`.
- **Done when:** clicking the button on the student page lands on the student timetable, switches the week
      and pulses the block carrying `data-rid`, with no console error.

## T6 — the page's new address and label  *(~45 min)*

- [x] `routes/web.php`: add `/student-class-replacements` (same component, `nav` key unchanged, `mw` student),
      keep `/replacement-history-ui` as a second address.
- [x] `partials/ui-nav-bar.blade.php`: label `Class Replacements`, href the new address.
- [x] Update the tests that name the URL (`NavIdentityTest`, `RouteGateMatrixTest`, `nav-identity.spec.ts`)
      and keep one alias assertion.
- **Done when:** both addresses render the page for a student; a lecturer still gets 403.

## T7 — receipts  *(~1 h 30)*

- [x] `ReplacementHistoryRowTest` was authored in T1 — T7 confirms it covers: a holiday occurrence with no
      request; a cancelled class with no request; a cancelled **request** → `none` + no requested slot;
      the latest request wins; `completed` from the make-up date (clock frozen); no leave type anywhere.
- [x] Rewrite `tests/Feature/StudentReplacementHistoryParityTest.php` — five card labels; no
      `Total`/`Upcoming`/`sumTotal`; `All Original Class Weeks`; the toggle's staff markup; the rank literal;
      no action column; the `requestId` guard; the forbidden vocabulary absent from page + component; the
      hours card sums `orig.duration` over all visible rows.
- [x] Update `ReplacementHistoryDefaultWeekTest` for the new All-label (its law survives).
- [x] Browser receipt on both demo students, desktop + 390 px: no clickable action, pop-up button present/hidden
      as ruled, jump+flash works, zero console errors. Click-through only (no writes); if it joins a Playwright
      suite run, keep the existing `--grep-invert "TC70"` guard.
- **Done when:** every gate is green — `pint`, `phpstan`, `node --check`, `view:cache`, targeted PHPUnit.

## T8 — changelog  *(~30 min)*

- [x] `page-changelogs/replacement-history-changelog.md`: what the page is now, the eight rulings, the route
      move, the receipts, and the honest notes (holiday-heavy list, unarranged rows never become completed).

## Gates (every task)

`composer run lint:check` · `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` ·
`php artisan view:cache` · `node --check public/js/ui-common.js` · targeted
`DB_DATABASE=class_replacement_testing php artisan test --filter …`.
