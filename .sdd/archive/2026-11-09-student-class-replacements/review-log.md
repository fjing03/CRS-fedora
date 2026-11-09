# review-log — student-class-replacements

## proposal Round 1 — 2026-11-09 11:40
Reviewed by a separate read-only `pi` subprocess (glm-5.3-flash) against `explore-brief.md`.

### 🔴 Fixed
- None — the reviewer found no blockers.

### 🟡 Addressed
- The pop-up button's **absence on rows with no requested slot** was missing from point 6 and AC 7 → folded into both.
- "Four files name the old URL" was a miscount → corrected to five files plus the component's `activeNav` key.
- The **latest-request-per-(session, week)** rule was missing from the contract table → added, with the demo's mixed-state row as the witness.
- The **default sort order** was not carried forward from the brief → added to the contract table (class date ascending).
- The `Approved` card's basis vs today's `Upcoming` predicate → added as point 9, so the old predicate cannot survive as a leftover.

### 🔴 Outstanding
- None.

**Verdict:** batch passes — `proposal.md` frozen (2026-11-09).

## design Round 1 — 2026-11-09 12:05
Reviewed by a separate read-only `pi` subprocess against the frozen `proposal.md`.

### 🔴 Fixed
- **No row identity for request-less occurrences** — reusing the request id would have left most rows unclickable. Added §1a: the row key is `"<sessionId>:<week>"`, `requestId` is a separate nullable key used only by the jump button, and the blade's `Number(...)` cast is removed.
- **The unarranged status key was contradictory** (`none` vs `null`) and would have printed `status-null`, broken the card count and left the sort rank `undefined`. Pinned: mapper arms stay null-keyed, the payload carries the literal `'none'`, the badge renders `badge badge-outline status-none`, and the rank map is `{ none: 3, pending: 0, approved: 1, completed: 2 }`.

### 🟡 Addressed
- "Six queries" → the honest count (eight statements, one read per input, none per row).
- Read #6 now pins its eager load and column list (the anchor fallback's venue, `replacement_span`, `semester_id`) so no lazy-loading sneaks in.
- `statusTip()`'s student null text is explicitly left unchanged (a tip explains, the badge names).
- The jump pins `repl-w` to the **requested slot's** week, and `goToReplacementInTimetable()` gains an optional base-path argument so both staff call sites stay byte-identical.
- The pop-up footer mechanism is named (append/remove at open/close, the `CancelClass.renderButton` house pattern).
- `StudentReplacementHistoryParityTest` is a **rewrite** (which assertions die is now stated); `ReplacementHistoryDefaultWeekTest` is named.
- The two stale "today" claims corrected: `Upcoming` counted `r.status === 'approved'` (no week clause), and `Awaiting PL approval` was already retired on 2026-11-09.
- Receipts gained the forbidden-vocabulary assertion, the hours-summation assertion, and the changelog entry.

### 🔴 Outstanding
- None.

**Verdict:** batch passes — `design.md` frozen (2026-11-09).

## design Round 2 — 2026-11-09 12:20
Re-review of the two Round-1 blockers only.

### 🔴 Fixed
- Round-1 blocker 1 (row identity) — verified resolved: `"<sessionId>:<week>"`, separate `requestId`, no `Number()` cast.
- Round-1 blocker 2 (status key) — verified resolved: literal `'none'`, `badge badge-outline status-none`, rank map `{ none: 3, pending: 0, approved: 1, completed: 2 }`, card predicates unchanged in meaning.

### 🟡 Addressed
- One guard predicate chosen: **`requestId !== null`** across design, parity test and browser receipt.
- `statusShort()` deliberately gets **no** audience arm (staff/API only; this page never reads it).
- `sessionId` dropped as a payload key — the row key already carries it, and no consumer existed.

### 🔴 Outstanding
- None.

**Verdict:** batch passes — `design.md` frozen (2026-11-09). Specs skipped on purpose: single page, no
cross-module contract, and the proposal's acceptance criteria are already the requirement set.

## tasks Round 1 — 2026-11-09 12:40
Reviewed by a separate read-only `pi` subprocess against the frozen proposal + design. The spec skip was
judged defensible (single page, no cross-module contract).

### 🔴 Fixed
- T1's payload key list was incomplete (no `statusTip`, no pending deadline, no course primitives) → the full shape is now written out, and the two countdown/tip receipts are named.
- Two "done when" lines pointed at receipts that a later task was to author → `ReplacementHistoryRowTest` is authored **in T1**, and T2 gains its own `ReplacementMapperStatusLabelTest`.
- The null-vs-`'none'` convention was under-pinned → pinned: the mapper is called with `null`, only the payload carries `'none'`, with both wrong builds named.

### 🟡 Addressed
- The T2 → T1 → T3 sequence is now explicit, including the degraded window.
- T4 warns that `row.week` is 0-based while the jump needs the requested slot's 1-based week.
- `statusVocabulary()` now forwards its audience (one call path).
- The Completed card's exact description string is pasted; unarranged `requestedSlot` sorting is pinned to nulls-last.
- The browser receipt keeps the `--grep-invert "TC70"` guard where relevant.

### 🔴 Outstanding
- None.

**Verdict:** fixes applied and self-checked line by line against the review text (no second reviewer round was
run for this batch — recorded here for honesty). `tasks.md` frozen (2026-11-09). All four batches frozen;
ready to apply.

## apply — 2026-11-09 (T1–T8)

Implemented directly in the main session (no subagent) — the change spans one component, one blade, the
mapper, one JS helper and six test files, and every step was verified against the frozen artifacts.

Findings during apply, all folded in and recorded here:

- **One JS syntax error**, caught by `node --check` on the extracted page script: the pop-up's jump button
  was built with mixed quotes. Rebuilt as a `jumpCall` string plus attribute concatenation.
- **`closeModalOutside` was never exported** on this page, so every click on the modal's overlay threw
  "closeModalOutside is not defined" (pre-existing; surfaced by the new button's click path, which bubbles
  to the overlay). Exported beside `window.renderUpcoming`.
- **Two source assertions were stale after pint** (`const jump = r.requestId…` gained a parenthesis; a
  comment still said "Requested Replacement Week:"). Both corrected.
- The `page`/`component` helper names collided with `TestCase::component()` — renamed to
  `pageSource()`/`componentSource()` (the documented PHPUnit trap).

Measured: the page renders 6 holiday/cancellation rows for `24DFT0001` and 10 for `24RSD0009`; an
unarranged row's pop-up shows **no** jump button, an approved row's shows
`goToReplacementInTimetable(27, 8, '/student-my-timetable-ui')`, which lands on the student timetable at
Week 8 and pulses the block with `data-rid="27"`. Alias `/replacement-history-ui` still renders. Zero console
errors (desktop + 390 px).

Gates: pint passed · phpstan 0 · `node --check` OK · `view:cache` OK · targeted PHPUnit 37/37.
