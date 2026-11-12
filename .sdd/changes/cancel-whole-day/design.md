# Design — cancel-whole-day

**Frozen:** `proposal.md` (decisions 1–15) and `explore-brief.md` (three grill rounds).
**Prototype of record:** `ui-prototype/cancel-whole-day.html` — five states on one sheet.
**No code has been written.** This file says how it would be built and what proves it.

## Shape in one picture

```
Cancel this Class?
┌ Class Information ────────────────────────────┐   (unchanged: dl.cancel-details)
│ AMSE1003 (P) — Software Engineering · Week 8   │
└────────────────────────────────────────────────┘
┌ ── the block (only when the day has other classes) ─────────────┐
│ ☑ Also cancel my other 3 classes on Wednesday            ⌄     │  ← select-all + fold
│    ☑ 9:00 AM ~ 10:00 AM  AMSE1003(L)  B009        DSF1 (S1) G1  │
│    ☑ 1:00 PM ~ 3:00 PM   BMSE2163(L)  B015      2 cohorts      │
│                                              1 request waiting  │  ← amber, stacked
│    ☑ 3:00 PM ~ 4:00 PM   BMSE2163(T)  B015      2 cohorts      │
└─────────────────────────────────────────────────────────────────┘
────────────────────  rule  ────────────────────
Cancellation Reason *   (unchanged)
Footer:  No, Keep Class                       CANNOT UNDONE  [Yes, Cancel 4 Classes]
```

## Files

| # | File | Kind |
|---|---|---|
| F1 | `app/Actions/ReleaseRequestSlots.php` | NEW — the one slot-release implementation (extracted from `CancelPendingRequest`, `RejectRequest`, `AutoRejectRequest`) |
| F2 | `app/Actions/CancelClassBatch.php` | NEW — cancels a set of occurrences in one transaction, closes the requests they hold |
| F3 | `app/Actions/CancelClass.php` | EDIT — keeps its signature, delegates to F2 with a one-item set |
| F4 | `app/Actions/CancelPendingRequest.php`, `RejectRequest.php`, `AutoRejectRequest.php` | EDIT — call F1 (behaviour identical) |
| F5 | `app/Concerns/CancelsClasses.php` | EDIT — two renderless methods: `myDayClasses()`, `cancelClassDay()` |
| F6 | `resources/views/partials/ui-cancel-class-modal.blade.php` | EDIT — the block's shell (empty containers; JS fills them, the house pattern) |
| F7 | `public/js/ui-common.js` | EDIT — `CancelClassModal`: fetch, render, select-all, fold, counts, day confirm, day success |
| F8 | `public/css/theme.css` | EDIT — the block, the rows, the stack, the two pills (tokens only) |
| F9 | tests | NEW `CancelClassBatchTest`, `ReleaseRequestSlotsTest`, `MyDayClassesTest`; additions to `NotificationsBridgeTest`; Playwright `cancel-whole-day.spec.ts` |
| F10 | `page-changelogs/my-timetable-changelog.md` (+ cohort/venue if it earns a line) | EDIT — the record |

Not touched: the reason vocabulary, the cancel window, the holiday ban, the FINAL rule, the single-class
modal layout, notifications' existing wording.

## F1 — `ReleaseRequestSlots`

One method, `__invoke(ReplacementRequest $request): array<int,int>`:

1. slot ids back to `available`, `version = version + 1` (the existing stamp law),
2. `replacement_request_slots` junction rows deleted,
3. returns the freed ids.

`CancelPendingRequest`, `RejectRequest` and `AutoRejectRequest` keep their behaviour exactly; their tests
are the proof (they must pass unchanged). F2 is the fourth caller.

## F2 — `CancelClassBatch`

```php
final class CancelClassBatch
{
    /**
     * @param  array<int, array{session: ClassSession, week: int}>  $items
     * @return array{ok: bool, refused: string, cancelled: int, requestsClosed: int}
     */
    public function __invoke(User $user, array $items, string $reason, ?string $otherDetail = null): array
}
```

Rules, checked for **every** item before anything is written (all-or-nothing):

| Rule | Refusal key |
|---|---|
| `$session->lecturer_id === $user->id` | `not_owner` |
| week 1–14 | `invalid_week` |
| date on/after today − 2 working days (the existing `ClassCancellation` window, server-side twin) | `too_old` |
| not a holiday / Sunday | `holiday` |
| reason in the 10 schema values, `other` non-blank | `invalid_reason` |
| no existing exception for that occurrence | `already_cancelled` |

On write, inside one `DB::transaction`:

1. one `class_exceptions` row per item,
2. **request closure** — for each item, any request in `pending` or `approved` on that occurrence:
   status `cancelled`, `decided_at = now()`, slots through F1, junction rows gone (the parked
   `bulk-cancel-class` rule, word for word),
3. one `AuditLog` row per cancelled class (`details = {class_session_id, week_number, reason, request_id?, closed_request?}`),
4. after commit: `NotificationEmitter::classCancelled()` per class (the existing fan-out), and one
   notice per closed request through the existing self-cancel path's wording.

Failure of any item ⇒ nothing written, `{ok: false, refused: '<key>', ...}`, and the modal shows the
existing hint line.

**Not in this action:** the `official_event` reason correction the parked lane needs. Those occurrences
are excluded from a day set by rule 6 (they already hold an exception), so the case cannot arise here.

## F5 — the two renderless methods (all four pages get them from the one trait)

```php
/** My own classes on one date, with what the block and the list print. */
#[Renderless] public function myDayClasses(int $weekNumber, int $dayIndex): array
```

Returns, ordered by start time:

```php
[
  'ok' => true,
  'date' => '2026-11-11',
  'day' => 'Wednesday',
  'classes' => [[
      'sessionId' => 24, 'week' => 8,
      'start' => '11:00', 'end' => '12:00',
      'code' => 'AMSE1003', 'type' => 'P',
      'venue' => 'B010',
      'cohorts' => ['DSF1 (S1) G1'],          // CohortLabel::spaced()
      'students' => 25,
      'request' => 'none' | 'pending' | 'approved',
      'cancellable' => true, 'why' => null,
  ], ...],
]
```

- Ownership-scoped query (`where('lecturer_id', auth()->id())`), the same week/day, no exception row,
  `with(['module','venue','cohorts.programme'])`, requests eager-loaded — no query per class.
- The **opened class is included** in `classes`; the modal drops the one it was opened for and treats the
  rest as "other". One read, no subtraction on the server.

```php
#[Renderless] public function cancelClassDay(int $weekNumber, int $dayIndex, string $reason, ?string $otherDetail = null): array
```

Resolves the same set **again** (never trusts the client's list), calls F2, returns
`{ok, cancelled: [...], requestsClosed: int, hint?}`.

`cancelClass()` (today's single path) stays for its own callers and now goes through F2 as well.

## F7 — the client

`CancelClassModal.open()` gains one step, after `_renderImpact()`:

1. `call('myDayClasses', weekIndex + 1, event.di)` → the payload above.
2. If `classes.length <= 1` (only the opened class) ⇒ **the block is not rendered at all** (decision 6).
3. Otherwise render the block with every row **ticked** (decision 10/12: the block opens as "the whole
   day", which is what the sentence says) and the label `Also cancel my other N classes on <Day>`.
4. Gate the red button: `1 + ticked` (decision 8), the warning line untouched.

| Piece | Behaviour |
|---|---|
| select-all tick | ticks/clears every row; a partly-ticked list shows the indeterminate mark |
| chevron | folds the list only; tick, count and button survive; ticking the whole-day box re-opens it (decision 13) |
| row tick | updates the label's number, the button, and the select-all's indeterminate state |
| unticking every row | whole-day box clears, list closes, button returns to `Yes, Cancel Class` |
| read failure | block absent + the existing hint line says the day could not be read (never a silent empty list) |

`confirm()` branches on the whole-day tick:

- **unticked** → today's path, byte-identical (`cancelClass`).
- **ticked** → `cancelClassDay(week, day, reason, otherDetail)`; on `ok` the client does **not** patch
  four blocks by hand: it records the ledger entries, renders the day success screen (✓ N classes
  cancelled, the day, the reason, "N waiting requests withdrawn", the row list with the red pill, one
  **Close**), and Close refreshes the page's data once (the existing `later()`/rebuild path).
- refusal → the existing hint line, no state change.

## F6 — the partial's shell

Inside `#cancelClassConfirmState`, between `#cancelClassImpact` and the `<hr class="modal-divider">`:

```blade
{{-- 2026-11-11 (SDD cancel-whole-day): the whole-day block. Its content is built by
     the client (CancelClassModal._renderDayScope) because the day's classes come from
     the server read; the shell only reserves the place. --}}
<div id="cancelDayScope" style="display:none"></div>
```

## F8 — the CSS (tokens only)

| Selector | Rule |
|---|---|
| `.cancel-day-scope` | hairline `--color-outline`, `--radius-md`, `10px 14px 12px`, `margin: 0 0 14px` |
| `.cancel-day-head` | flex, `gap: 10px` |
| `.cancel-day-toggle` | flex 1, `gap: 9px`, 13px/600, `--color-on-surface` |
| `.cd-count` | `--color-on-primary-container`, 800 |
| `.cd-collapse` | 26×26, transparent, `--color-on-surface-variant`, hover `--color-surface-variant`; `svg` rotates −90° when folded |
| `.cancel-day-scope.is-collapsed .cancel-day-list` | `display: none` |
| `.cancel-day-row` | flex, `gap: 9px`, 12.5px, hover to `--color-on-surface` |
| `.cdl-time` | `min-width: 122px`, 600, tabular numerals |
| `.cdl-venue` | `--color-on-surface-variant`, `min-width: 38px` |
| `.cdl-facts` | column stack, `margin-left: auto`, items end-aligned, `gap: 2px` |
| `.cdl-flag` | amber pill — `--color-warning-container` / `--color-on-warning-container` |
| `.cdl-flag--withdrawn` | red pill — `--color-error-container` / `--color-on-error-container` |
| `@media (max-width: 520px)` | the row wraps: time on its own line, class + venue + cohort next, the stack under |

No new hex, no new token.

## Edge cases

| Case | Behaviour |
|---|---|
| The day has only the opened class | no block (decision 6) |
| The day's other classes are all already cancelled/moved | same as above — the set is empty |
| A class in the set has a pending request | it is in the set, marked amber, cancelled with it |
| A class in the set has an approved replacement | it is **not** in the set (its occurrence already holds an exception) |
| The modal stays open past midnight | the server re-checks the window; refusal is shown, nothing written |
| Two tabs cancelling the same day | the second gets `already_cancelled`; nothing is double-written |
| A row fails on the server | nothing is written for the day; the hint names the refusal |

## Receipts

1. `ReleaseRequestSlotsTest` — the three existing callers' behaviour unchanged (their own tests stay green).
2. `CancelClassBatchTest` — the six rules, all-or-nothing, request closure, audit rows, notifications.
3. `MyDayClassesTest` — ownership scoping, the exception exclusion, the request status, cohorts/students.
4. `NotificationsBridgeTest` additions — one student row per cancelled class; one per withdrawn request.
5. `tests/cancel-whole-day.spec.ts` (Playwright, testing DB) — the block appears/hides, select-all and
   indeterminate, folding, the two counts, the day confirm, the success list, the phone frame.
6. `node --check public/js/ui-common.js`, pint, phpstan 0, `view:cache`, full PHPUnit.

## Risks

| Risk | Mitigation |
|---|---|
| The refactor of three shipped actions changes behaviour | Pure refactor commit first; their tests unchanged and green before anything else |
| A day cancel withdraws a request students were waiting on | Amber mark + success count + the existing notice wording; all-or-nothing |
| The client's list and the server's set could differ | The server re-resolves the set in `cancelClassDay()`; the client's list is display only |
| Four grids to patch after a day cancel | Not patched — the success Close refreshes from the server |
| Scope creep into the parked bulk lane | Its UI (semester-bar button, From/To picker, `batch_id`) stays out; only the action is shared |

## Rev 4 — built, then adjusted against the live page (2026-11-11)

| # | Decision (user, during apply) | Where |
|---|---|---|
| 16 | The block opens **unticked and folded**; the sentence still names the day's total, and the numbers only start tracking ticks once something is ticked | `_renderDayScope` / `_refreshDayGate` |
| 17 | The sentence reads **"… on the same day"**, not the weekday name | `_renderDayScope` |
| 18 | The cohort hover is the app's shared **`data-tip`**: one cohort ⇒ its full name; several ⇒ `n cohorts` and one `code — full name` per line | `_cohortCell` + `CohortLabel::full()` |
| 19 | A **make-up block** that falls on the day but belongs to ANOTHER week's class is a quiet **note**, never a tickable row — a tick there would cancel a different week's class (user bug report, 2026-11-11) | `makeUpsOnDay()` + `_makeUpNote()` |

Measured after Rev 4 (staff 5425, Thursday week 8, demo data): rows `67 09:00–11:00`,
`68 11:00–12:00`, `38 12:00–13:00`; the note reads
`AMSE2003(T) · week 14 · 8:00 AM ~ 9:00 AM · B100` (the request's real 2-cell run), and the
select-all/`.cd-count`/button numbers only count the three tickable rows.

## Rev 5 — make-up rows are tickable, for the request only (2026-11-11, user)

| # | Decision |
|---|---|
| 20 | A **pending** make-up block that falls on the day is a row of its own, tickable, labelled with its own week (`8:00 AM ~ 9:00 AM · AMSE2003(T) · week 14 · B100`) and the amber `1 request waiting` pill |
| 21 | Ticking it **withdraws that request** (status `cancelled`, its slot released) and does **not** cancel the class it was arranged for — the user's words: "i only it tick for cancel the request" |
| 22 | The sentence names both kinds: **"Also cancel my other 2 classes + 1 request on the same day"**, each half a live count, the request half hidden at zero, singular/plural correct |
| 23 | An **approved** make-up keeps the quiet note (withdrawing a promise is not this box's decision) |

Server: `cancelClassDay(..., array $requestIds = [])` — the ids are re-checked (mine, still `pending`)
before anything is written, then `CancelPendingRequest` runs per id; the classes still go through
`CancelClassBatch`. Receipt: `MyDayClassesTest::test_a_ticked_make_up_row_withdraws_the_request_and_leaves_the_class_alone`.
