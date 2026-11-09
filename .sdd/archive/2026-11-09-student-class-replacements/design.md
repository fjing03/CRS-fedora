# design — `student-class-replacements` (2026-11-09)

Baseline: frozen `proposal.md` + `explore-brief.md`. This is **how**, decision by decision. Nothing here
changes behaviour the proposal did not already promise.

---

## 1. Where the rows come from (the data bridge)

`App\Livewire\ReplacementHistory::render()` becomes an occurrence walk instead of a request list. The point
is the **shape**: one read per input, **none per row** (eight statements in total across the table below —
not the "six" an earlier draft claimed) — the same shape the staff page uses:

| # | Read | Why |
|---|---|---|
| 1 | `timelineSemester()` / `timelineWeeks()` (trait) | the semester's start date and its week count |
| 2 | my cohort (`Student::where('user_id')->first()?->cohort`) + `cohortCode()` | who I am; the page's chip |
| 3 | `ClassSession::with(['module','venue','lecturer'])->whereHas('cohorts', fn ⇒ whereKey(cohort))->where('semester_id', …)` | my classes |
| 4 | `ClassException::whereIn('class_session_id', …)->get()->keyBy('class_session_id:week_number')` | the cancelled-class side of "conflicted" |
| 5 | `collect($this->holidaysForJs())->keyBy('week:dayIndex')` | the calendar side |
| 6 | `ReplacementRequest::with('replacementTimeSlot.venue')->whereIn('class_session_id', …)->orderBy('submitted_at')->orderBy('id')->get(['id','class_session_id','week_number','status','submitted_at','semester_id','replacement_time_slot_id','replacement_span'])` → `keyBy('class_session_id:week_number')` | the latest request per occurrence — **last write wins**, so a re-submitted request replaces the earlier one. The eager load and the explicit column list are both load-bearing: the anchor fallback needs its own venue and `replacement_span`, and the deadline needs `semester_id` — omit either and the page lazy-loads once per row |
| 7 | `$mapper->spansForRequests(ids)` + one `Venue::whereIn` | the request's true run and its room |

The occurrence loop is `foreach (sessions) { for ($week = 1; $week <= $weeks; $week++) { … } }` — the staff
page's own shape (`ReplacementHome::render()`), with one row kept per **conflicted** occurrence.

**Which occurrences are conflicted** (identical test to the staff page, so the two pages cannot disagree):

```
exception present            → reason = titleCase(exception->reason)
                               unless a holiday also lands here AND the reason is `official_event`
                               (the D9 stamp written by ApproveRequest) → reason = 'Public Holiday'
no exception, holiday lands  → reason = 'Public Holiday'
neither                      → not a row
```

**Student wording of the reason** (unchanged from the 2026-11-09 Q1=B ruling): `Public Holiday` keeps the
calendar's own label (`Public Holiday — Deepavali Holiday (In Lieu)`); every other reason collapses to
`Class Cancelled`. The raw reason string never reaches the browser.

## 1a. Row identity (and why it cannot be the request id)

Today every row's `id` **is** the request id, and the blade resolves the pop-up with
`MockData.replacementHistory.find(x => x.id === id)`. Under this model most rows have **no request**
(the proposal's own table: 6 of 6, 10 of 11, 9 of 11), so reusing the request id would leave `data-id`
undefined and the row click would silently do nothing.

**The row key is the occurrence, not the request:**

| Payload key | Value | Used by |
|---|---|---|
| `id` | `"<sessionId>:<week>"` — stable, unique per occurrence (a session meets once per week) | `data-id`, the pop-up lookup, the delegated click/keyboard handlers |
| `week` | int — the occurrence's **0-based** week index | the week filter, the sort keys, the card chip |
| `requestId` | int or `null` | **only** the *Go to My Timetable* button; absent on unarranged rows |

The blade's lookup becomes `find(x => String(x.id) === String(id))`, and `openReplacementModal` receives the
`data-id` **string** (`Number(...)` is removed from both the click and keyboard handlers). The jump button is
rendered only when **`requestId !== null`** — that is the ONE guard across design, parity test and browser receipt
(a live request always carries both an id and a span, so `requestId` and `req` coincide; the id is what the URL needs).

## 2. The status of a row, and where the words live

The row's status is derived exactly as the staff table derives it, then **one** of five states is printed:

| Stored request | Derived key | Badge text (student) | Badge class |
|---|---|---|---|
| none, `cancelled`, `rejected` | `none` | `Awaiting Arrangement` | `badge badge-outline status-none` |
| `pending` | `pending` | `Pending — Waiting for Approval` | `badge status-pending` |
| `approved`, make-up date ≥ today | `approved` | `Approved` | `badge status-approved` |
| `approved`, make-up date < today | `completed` | `Completed — n days ago` | `badge status-completed` |

**One place for the words.** Only **`statusLabel()`** gains an optional audience argument whose default
reproduces today's output byte-for-byte; its `'student'` arm maps the **null** status key to
**`Awaiting Arrangement`**. `statusShort()` is deliberately left alone (it feeds staff badges and the API;
this page never reads it, and a short/key mix-up is how vocabularies drift). `statusTip()` already has that audience switch (added
2026-11-09) — its student null text stays exactly as it is ("No request has been made for this class
yet."), because a tip explains the state and the badge already names it.

**The null key stops at the mapper.** The mapper's arms remain null-keyed, and the **payload carries the
literal string `'none'`**, because three client readers compare it:

| Reader | Needs |
|---|---|
| the badge | `status-none` would print as `status-null` if the value were PHP `null`; the cell must render `badge badge-outline status-none` (the staff no-request shape) |
| the cards | `status === 'none'` for `Awaiting Arrangement` |
| the sort rank | `{ none: 3, pending: 0, approved: 1, completed: 2 }` — unarranged rows sort **last**, never `undefined` |

The component maps a cancelled or rejected request to `'none'` **before** asking the mapper, so no surface
ever receives a dead request's state from this page.

Every other label string stays byte-identical for the staff surfaces: their calls do not pass the argument.

## 3. The two slot columns

Both print the 2026-11-09 4-line block (`slotBlock()` in the page) from **one primitive shape**:

```php
'orig' => ['week','date','day','start','end','duration','venue']   // the class's own occurrence
'req'  => same shape, or null                                      // the latest request's *span*
```

- `req` comes from the junction span, falling back to the anchor cell widened by `replacement_span`
  (the junction is DELETEd on cancel/reject, so the fallback is not optional).
- A **cancelled or rejected** request contributes **no** `req`: the row reads as unarranged, and the
  REQUESTED SLOT cell prints an em dash — the staff page's own treatment of an owner-cancelled request.
- The countdown: `orig` always carries one; `req` only while the status is `pending` (the user's Q3 ruling
  from the same day).

## 4. Filters, toggle and chips

- **Week filter keys on the class's own week** (`row.week` = the occurrence's week, 0-based internally).
  All-option label: **`All Original Class Weeks`**.
- **`Exclude COMPLETED Status`** (the existing checkbox, unchanged markup and default ON) hides rows whose
  derived status is `completed`. Because `completed` is derived from the **make-up date**, an unarranged
  row is never hidden by it — the accepted consequence of R4.
- The Active Filters bar keeps its two chips; the week chip now reads `Original Class Week: …`.
- Persisted view state keeps its key and shape (`week`, `excludeCompleted`), so a returning student lands
  where they left.

## 5. Cards (five)

| Card | Count over the visible set |
|---|---|
| `Awaiting Arrangement` | status `none` |
| `Pending` | status `pending` |
| `Approved` | status `approved` |
| `Completed` | status `completed` |
| `Class Hours Affected` | Σ `orig.duration` over **all** visible rows, arranged or not |

`Total` disappears and `Hours` is renamed. Today's `Upcoming` card counted `r.status === 'approved'`; that
predicate is retired with the card name, and no leftover filter may narrow the new Approved card.

**Open question 1, decided (changeable):** the `Completed` card reads `0` whenever the toggle is on, which
is the default. The card keeps counting the visible set (consistency with its four neighbours) and its
hover text states why: *"Switch Exclude COMPLETED Status off to see them."*

## 6. Page identity, route and nav

- `routes/web.php`: a new **`/student-class-replacements`** entry (same component, same `legacy` template,
  `nav` key unchanged, `mw: auth + role:student`), and the old **`/replacement-history-ui`** entry stays as
  a second address (house practice: several addresses, no redirect — query strings survive). Nothing
  stored points at the old URL.
- `partials/ui-nav-bar.blade.php`: label **`Class Replacements`**, href the new address; the `nav` key stays
  `replacement-history`, so `activeNav` highlighting keeps working.
- Tests that name the URL move to the new address **and keep one assertion for the alias**.

## 7. The pop-up (read-only, with one jump)

Same grouped modal shell and the same five groups as today (`Course`, `Original Slot`, `Conflict Reason`,
`Requested Slot`, `Request Status`), plus a footer button:

- **`Go to My Timetable`** — rendered **only when `req` exists** (nothing to flash otherwise).
- `goToReplacementInTimetable(requestId, week, basePath)` gains an **optional third argument** defaulting to
  `'/my-timetable-ui'`, so the two staff call sites (`replacement-home.blade.php:488` and `:937`) stay
  byte-identical. The student page passes `'/student-my-timetable-ui'`.
- It builds `/student-my-timetable-ui?repl-hl=<requestId>&repl-w=<req.week>` — **the requested slot's
  1-based week**, not the class's week. The staff caller passes the same thing today (`c.replacementWeek`),
  and passing the class's week instead would land on a week whose blocks carry no `data-rid`, i.e. a jump
  that silently fails to flash.
- The button is appended into the shared modal's footer at open time and removed at close (the house pattern
  `CancelClass.renderButton` already uses), so no shared `openClassModal` option is invented and nothing in
  §9 is touched.
- `applyReplacementHighlight()` (ui-common) accepts **both** timetable paths instead of only the staff one;
  it already reads `repl-hl`/`repl-w`, switches the week and flashes the block carrying `data-rid`, which the
  grid already emits for replacement blocks on both pages. This is the single behavioural change the jump
  needs — measured working on the staff side today.
- No other control exists: no arrange, no cancel, no approve.

## 8. Copy that dies with the page (absorbing the parked lane)

| Today | Becomes |
|---|---|
| title `No replacements this week` / `… in the selected week` | `No conflicted classes to show` / `Nothing matches this view for <cohort>. Try another week, or switch **Exclude COMPLETED Status** off.` |
| description `REPLACEMENT CLASSES confirmed or pending for your cohort.` | `Conflicted **CLASSES** and where each one's replacement stands — read-only, for your cohort.` |
| the old `Awaiting PL approval` wording (retired earlier on 2026-11-09, when pending rows started printing their real slot) | stays retired: an unarranged row prints an em dash, an arranged one prints the real slot |

## 9. What must not move

- No write path of any kind is added; the page stays a pure read.
- The staff pages' payload, wording and behaviour (only the shared jump helper learns a second base path).
- The student timetable's grid, blocks and tooltips.
- `statusTip()`'s existing lecturer/pl arms, byte-identical.
- The conflict-reason privacy rule (no leave type) and the holiday-name rule.

## 10. Receipts (what apply must leave behind)

1. **Payload invariants** (rewrite `ReplacementHistoryRowTest`): a holiday occurrence with **no request** is
   a row; a cancelled class with no request is a row; a cancelled *request* yields `Awaiting Arrangement`
   **and no** requested slot; the latest request wins when an occurrence holds two; `completed` follows the
   make-up date (clock frozen); the reason never leaks a leave type.
2. **Source invariants** — `StudentReplacementHistoryParityTest` is **rewritten**, not extended: its current
   `allLabel: 'All Requested Slots Weeks'` and `'Requested Replacement Week:'` chip assertions die by §4,
   while the badge-markup, toggle and slot-block assertions survive. It must pin: the five card labels, the
   **absence** of `Total`/`Upcoming`/`sumTotal`, the week-filter basis and `All Original Class Weeks`, the
   toggle's staff wording, the sort rank for `none`, no action column, the pop-up button's guard
   (`requestId`), and that the forbidden vocabulary (**`Need to Arrange`, `Cancelled by YOU`, `Rejected`,
   `Awaiting PL approval`**) appears nowhere in the page or its component. `ReplacementHistoryDefaultWeekTest`
   keeps its law (the page still opens on the All-option) and must be updated for the new label.
   A third assertion pins the hours card: it sums `orig.duration` over **all** visible rows, arranged or not.
3. **Route/nav tests** moved to `/student-class-replacements`, with the alias still asserted.
4. **One browser receipt**: the page renders with no clickable action; the pop-up shows the button on an
   arranged row and hides it on an unarranged one; clicking it lands on the student timetable with the
   replacement block flashing; zero console errors on desktop and 390 px.
5. **The page's changelog entry** (`page-changelogs/replacement-history-changelog.md`), as the frozen
   proposal's in-scope list requires.
