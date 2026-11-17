# Design — four cells lose a line, one gains a type

**Lane:** `table-column-trims` · written 2026-11-15 · batch 2 of 4
**Frozen upstream:** `proposal.md` (this lane), `explore-brief.md`
**Read with:** `prompts/for-ai-to-read/CodingMAIN.md` §10.0 (colour legend) and §10.5 (JS house style)

---

## 1. The shape of the change

Three render sites, four cells, no new function, no new class, no `theme.css` line. Every edit is a
string-built cell in code that already exists; nothing is refactored, nothing moves house. The only
decisions worth writing down are **which wrappers stay**, **how the type is guarded**, and **why the
three sites are not one helper**.

## 2. The exact edits

### 2a. Request Approval — the Staff cell (`request-approval.blade.php`, today `:720-726`)

> **Anchor by symbol, not by number:** the blade gains and loses lines as other lanes land
> (`filters-button`, `tooltip-viewport-clamp`, `table-column-trims` itself). The numbers
> below were right on 2026-11-15; the `before` blocks are verbatim, so `grep` beats any
> line reference. This applies to every file in §2.

```js
/* before — two lines per row, the ID and the email in the queue */
var lec = lookupLecturer(r.lecturer);
if (lec) {
    html += '<td><div class="lecturer-cell-name">' + lec.name + ' <span class="lecturer-cell-id">(' + lec.staffId + ')</span></div>';
    html += '<div class="lecturer-cell-email" onclick="copyEmail(\'' + lec.email + '\', event)" data-tip="Click to copy email">' + lec.email + ' <span class="copy-icon">📋</span></div></td>';
} else {
    html += '<td>' + r.lecturer + '</td>';
}
```

```js
/* after — the name alone, in the same wrapper, so the column keeps its 600 weight */
var lec = lookupLecturer(r.lecturer);
if (lec) {
    html += '<td><div class="lecturer-cell-name">' + lec.name + '</div></td>';
} else {
    html += '<td>' + r.lecturer + '</td>';
}
```

- `lecturer-cell-name` **stays**: it is the only live consumer of the `:146` rule
  (`font-weight: 600; font-size: 13px`). A bare `<td>` would drop the column to body text.
- The `else` branch is untouched — `r.lecturer` is already a name
  (`ReplacementRequestMapper:349`), so it is trim-safe by construction.
- `lecturer-cell-id` and `lecturer-cell-email` rules stay in the page's `<style>`: the
  Approve/Reject confirm card (`:552`) and the details strip (`:982`) still build them. Only this
  cell's use goes.

### 2b. Request Approval — the Course cell (`:729`)

```js
/* before */
html += '<td><div class="cell-code">' + r.courseCode + '</div><div class="cell-name">' + r.courseName + '</div></td>';
```

```js
/* after — one div, one line, the type guarded */
html += '<td><div class="cell-code">' + r.courseCode
    + (r.classType ? ' (' + UiLabels.classType(r.classType, 'code') + ')' : '')
    + '</div></td>';
```

- The literal prefix `html += '<td><div class="cell-code">' + r.courseCode` is kept **because a test
  matches it** (§5) — do not reshuffle it into a variable.
- `cell-name` goes entirely: it is `display: block` (`theme.css:1308-1313`), so keeping it for the
  type would print `CODE` then `(L)` on a second line and fail §8.
- `UiLabels.classType` is already in this page's scope (used at `:561`, `:1138`).

### 2c. My Replacements — the merged table's Course cell (`ui-common.js`, `replacementHomeRow`)

Today, inside `if (opts.merged)` (locate by symbol; numbers drift as other lanes land):

```js
{
    html: '<span class="cell-code">' + c.code + '</span>' +
        '<span class="cell-name">' + c.name + ' <span class="cell-type-label">(' + typeLabel + ')</span></span>',
    cls: 'col-code'
},
```

```js
{
    html: '<span class="cell-code">' + c.code + '</span>' +
        (c.type ? ' <span class="cell-type-label">(' + c.type + ')</span>' : ''),
    cls: 'col-code'
},
```

- The object keeps `html:` **before** `cls: 'col-code'` on its own line — the second regex in §5 pins
  that shape.
- **The leading space is not cosmetic:** `.cell-code` is inline with no margin (`theme.css:1305-1307`)
  and `.cell-type-label` has none either (`:5548-5552`), so `</span><span …>` renders
  `AMSE2003(L)`. The proposal (frozen) spells the space out; 2d carries it. Miss it here and the
  desk, the student table and this one stop agreeing.
- `typeLabel` (computed at the top of `replacementHomeRow`) stays: the **legacy nine-cell body** still
  uses it. Do not delete it as "unused".
- The legacy body is **byte-identical**. It is the frozen mock template's renderer; the live page
  never reaches it (one caller, `replacement-home.blade.php`, passes `merged: true`).
- `.cell-type-label` is inline, 12px/400, `--color-on-surface-variant` (`theme.css:5548-5552`) — the
  small grey bracket the tables already use. No new class.

### 2d. The student page — `courseCell()` (`replacement-history.blade.php`, today `:309`)

```js
/* before — note the comment ABOVE the function, which the edit must also rewrite:
   "The staff two-line COURSE cell: code on its own line, then the full name with the class
   type in small type." — false the moment 2d lands. Comments here are the ruling record
   (the same block carries the 2026-11-09 no-hover ruling), so leaving it is a rework trigger. */
function courseCell(r) {
    return '<span class="cell-code">' + r.code + '</span>' +
        '<span class="cell-name">' + r.name + ' <span class="cell-type-label">(' + r.type + ')</span></span>';
}
```

```js
/* after */
function courseCell(r) {
    return '<span class="cell-code">' + r.code + '</span>' +
        (r.type ? ' <span class="cell-type-label">(' + UiLabels.classType(r.type, 'code') + ')</span>' : '');
}
```

- Same class pair and the same leading space as My Replacements, so the two tables read identically.
- The comment above the function (`:307-308`) must be rewritten to: *"The COURSE cell: the code,
  then the class type in small grey brackets. No name, no hover text (user rulings 2026-11-09 and
  2026-11-15)."* It is cited elsewhere as the no-hover record; a stale copy is worse than none.
- The guard matters here: `classType(type, 'code')` returns its input unchanged, so an empty type
  would print `()`. The page already loads `UiLabels` (`:362`, `:624`).

## 3. Type and code — one wording law

| input | prints |
|---|---|
| `L` | `AMSE2003 (L)` |
| `T` | `AMSE2003 (T)` |
| `P` | `AMSE2003 (P)` |
| `null` / `''` / missing key | `AMSE2003` — no brackets, no `(null)` |
| an unexpected raw value (`'Lecture'`) | `AMSE2003 (Lecture)` — `classType`'s passthrough; never guessed |

The DB constraint `session_type IN ('L','T','P')` makes the last two rows unreachable on live data;
the guard costs one ternary and keeps a payload defect from printing `()`.

## 4. Promoted to shared — **nothing**, and why

The repo's promote-on-3rd rule asks for one shared home when a block ends up the same on 3+ pages.
Here it cannot have one, and the exemption is deliberate:

| site | why it stays where it is |
|---|---|
| Request Approval | the cell is **pinned inline** by `tests/Feature/CourseColumnTipTest.php:89-93`, which matches the literal `html += '<td><div class="cell-code">' + r.courseCode`. A helper call would break a passing test that also guards the 2026-11-09 "no hover on the cell" ruling. |
| My Replacements | already lives in the shared row builder (`ui-common.js`) — it is *the* shared home for that table, and the legacy body beside it must not move. |
| Student | page-local by architecture: the page owns its own `courseCell()` beside `reasonCell()` / `statusCell()`, and its row shape is its own. |

A 3-line `UiLabels.courseCellLine()` would be *worse* than the three sites: it would either be called
by two pages and leave the third behind (a half-promotion), or force a test rewrite for no user-visible
gain. **Recorded here so `/sdd-verify` has something to check** rather than finding a silent exemption.

## 5. Test constraints (verify, never weaken)

`tests/Feature/CourseColumnTipTest.php` reads the sources as text:

1. `:84-88` — the Request Approval blade must **not** contain `UiLabels.courseTip(`. (This lane adds
   no tip; the 2026-11-09 ruling stands — §4b of the proposal is therefore not an open question.)
2. `:89-93` — `/html \+= '<td><div class="cell-code">' \+ r\.courseCode/` — satisfied by 2b's prefix.
3. `:98-100` — `ui-common.js` must **not** contain `tip: UiLabels.courseTip(`.
4. `:102-106` — `/cls: 'col-code'\s*\n\s*\},/` — satisfied by 2c's object shape **only**. The legacy
   body writes `…, cls: 'col-code' },` on one line and never matched; so this test is *not* a
   safety net for the merged object — if someone reformats 2c onto one line, it fails. Keep the
   three-line object.

If any of these fails after the edit, the rewrite broke the constraint; fix the cell, not the test.

Three Playwright specs touch the same DOM and all survive, but verify them rather than assuming:
`tests/type-labels.spec.ts:47/52/64/68` (`.cell-type-label` must read `(P)` / `(L)` / `(T)`, exactly
one per row — 2c keeps one); `tests/ui-regression.spec.ts:194-195` and
`tests/replacement-home-status-badges.spec.ts:56/188/252` (`.col-code` text);
`tests/request-cancel-pending.spec.ts:150` (`.col-code` click). `tests/request-approval.spec.ts:96`
counts copy-email icons **inside the modal only**, which is why removing the cell's copy icon is safe.

## 6. Colours, classes, tokens

- Classes added: **none**. Classes removed: `cell-name` (2b, 2c, 2d) and `lecturer-cell-id` +
  `lecturer-cell-email` from the *cell* only (2a).
- `theme.css`: **no edit**. Every token used is already resolved by the classes that stay.
- No inline style, no hex, no `rgb()`, no new icon.

## 7. Not in this design

- Column widths, `min-width`, `table-layout` — My Replacements is `fixed` with `.col-code { width: 16% }`
  and `1280px` min-width; changing those belongs to `filters-button` / `cards-and-badges-responsive`.
- The cards (`requestCard`, the phone replacement card), the confirm dialogs (`approvalCard`), the
  details pop-ups' strips, the notification rows, the print view, the header tooltips.
- Any backend, mapper, route, Livewire property or DB change.
- The other seven items of "the 9".

## 8. Acceptance, mapped to proposal §8

| # | check | where |
|---|---|---|
| 1 | RA Staff cell is the name alone, still bold 13px | 2a + `request-approval.blade.php:146` |
| 2 | RA pop-up still prints name, `(staffId)`, click-to-copy email | unchanged `:934` |
| 3 | All three tables' Course cell reads `CODE (TYPE)` on one line | 2b/2c/2d |
| 4 | Pop-up subtitle still reads `CODE — Name (Type)` | unchanged `:624`-style builders |
| 5 | Typing a subject name still finds the row | searches read the data, not the cell |
| 6 | RA table relaxes at 1024–1280px (auto layout) | 2a |
| 7 | My Replacements rows get shorter, no `cell-name` wrap; the table gains no pixels (fixed 16%) | 2c |
| 8 | Both themes, 768 / 1024 / 1280 / 1600, three pages, no console error | manual pass |
| 9 | Cancel flash, bulk-select, row→pop-up still work (no `data-*` touched) | manual pass |
| 10 | `CourseColumnTipTest` still green | §5 |
| 11 | Sorting by Course still works (the sort key is `course`, not the cell text) | manual pass |
| 12 | RA header still reads `Staff (Proposer)` and still sorts | `request-approval.blade.php:617` |
| 13 | The two timetable pages proven unchanged (no `.cell-code` consumer there) | `git diff --stat` |
| 14 | RA's changelog records that its Course cell **gains** the type (an addition, not a trim) | §10.5 |

## 9. Rollback

Three string literals and one `<div>` per page. Reverting is the inverse diff; no data, no cache, no
schema, nothing persisted. The three `page-changelogs` entries name the commit, so a revert is
traceable.

## 10. Order of work

1. `request-approval.blade.php` — 2a and 2b in one pass (same render loop, one reload to check).
2. `ui-common.js` — 2c only, then reload My Replacements.
3. `replacement-history.blade.php` — 2d, then the student page.
4. `CourseColumnTipTest` — run it; expect green.
5. The three page changelogs — and RA's entry must say its Course cell *gains* a fact (the type)
   while everything else is a trim — then one `ui:` commit.

The three pages are independent; a partial landing still leaves every page self-consistent.
