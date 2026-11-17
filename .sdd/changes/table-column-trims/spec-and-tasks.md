# Spec + tasks — the three list tables lose what the pop-up already shows

**Lane:** `table-column-trims` · combined artifact, written 2026-11-17
**Frozen upstream:** `proposal.md` (frozen, review round 1) · `design.md` (frozen, review round 1)
· `explore-brief.md`
**Why combined:** the user's 2026-11-17 ruling cuts SDD cost to one artifact and one review round
per task. The design is already frozen and carries every exact edit, so a separate spec batch and a
separate task batch would be two reviews of the same sentences. This file states **what must be
true** (§1–§2) and **what will be done** (§3), and is reviewed once.

**Nothing is written to the product until the user says apply.**

---

## 0. What this lane is, in one paragraph

Four table cells print the same fact the details pop-up already prints. The Request Approval table's
**Staff** cell prints a name, a staff ID and a copyable email; all three tables' **Course** cell
prints the code, then the subject name on a second line, then the type. This lane trims the Staff
cell to the name, and rewrites the three Course cells to `CODE (TYPE)` on one line. One cell gains a
fact (Request Approval's Course cell never printed the type); everything else loses one. The pop-up,
the cards, the confirm dialogs, the column widths and the backend are untouched.

**Not a privacy change.** The payload still ships every proposer's email, and FR 7.1 permits staff
IDs and TAR UMT addresses. This is a queue view that is easier to read.

---

## 1. Terms

| Term | Meaning |
|---|---|
| **the trim** | 2a in `design.md` — the Staff cell keeps its name and loses the ID and email |
| **the rewrite** | 2b / 2c / 2d in `design.md` — the three Course cells become `CODE (TYPE)` |
| **the type letter** | what `UiLabels.classType(type, 'code')` returns: `L` / `T` / `P` |
| **the wrapper** | `.lecturer-cell-name` — the only live consumer of the blade's 600-weight / 13px rule |
| **legacy body** | the nine-cell row in `ui-common.js` below the `if (opts.merged)` block; belongs to the frozen design template, never reached by a live page |

---

## 2. Requirements

### TR-01 — Request Approval prints the proposer's name alone *(the trim)*

The Staff cell renders one line: the name, inside `.lecturer-cell-name`. The staff ID and the email
**leave the markup**, not just the paint — a CSS hide would keep both reachable by select-all and by
a screen reader, which is not the point.

The wrapper stays; a bare `<td>` would silently drop the column to body text. The `else` branch
(lecturer not found → `r.lecturer`, already a name) is untouched. The header still reads
`Staff (Proposer)` and its sort is unchanged.

`lecturer-cell-id` and `lecturer-cell-email` **stay in the blade's `<style>`**, and the evidence is
*not* the details strip — as of 2026-11-17 (task 10) that strip prints the proposer's **name alone**,
and the ID plus the copy-email chip moved into the pop-up's **Class Info** tab. The two live
consumers today are:

- `lecturer-cell-id` — the approve/reject **confirm card** (`request-approval.blade.php:620`);
- `lecturer-cell-email` — the pop-up's **Class Info** row (`:1284`).

Both rules therefore have a caller after this trim and must not be deleted with the cell.

### TR-02 — Request Approval's Course cell gains the type, on one line

`CODE (TYPE)` in a single `<div class="cell-code">`. The literal `html += '<td><div
class="cell-code">' + r.courseCode` prefix is **preserved**, because a passing test matches it. No
`cell-name` (it is `display: block`, so keeping it for the type would put `(L)` on a second line),
no `data-tip`, no `UiLabels.courseTip(`.

This cell **gains** a fact — it prints no type today. Every other cell in this lane loses one. The
changelog must say so.

### TR-03 — My Replacements' Course cell, merged branch only

Inside `if (opts.merged)` in `replacementHomeRow`: `<span class="cell-code">CODE</span>` then a
**leading space** then `<span class="cell-type-label">(TYPE)</span>`, guarded. The object keeps
`html:` **before** `cls: 'col-code'` on its own line — a test regex pins that shape, and formatting
it onto one line turns a green file red.

`typeLabel` stays computed (the legacy body still uses it). **The legacy body is byte-identical.**

*Note on the asymmetry a verify reader will notice:* 2c's merged object prints the raw `c.type`
while 2b and 2d route through `UiLabels.classType(type, 'code')`. Behaviour is identical — `classType`
passes an unknown value through unchanged, and the DB constraint makes `L`/`T`/`P` the only live
values — so 2c is left alone rather than rewritten for symmetry. Design 2c's `before` block is
verbatim and its `after` block is what ships.

### TR-04 — The student page's Course cell matches My Replacements

`courseCell(r)` returns the same two spans with the same leading space and the same guard, so the
two tables read identically. The comment **above** the function currently describes the two-line
staff cell; it is rewritten, because that comment block is where the 2026-11-09 "no hover on the
cell" ruling is recorded and a stale copy is worse than none.

### TR-05 — One wording law for the type

| input | prints |
|---|---|
| `L` | `AMSE2003 (L)` |
| `T` | `AMSE2003 (T)` |
| `P` | `AMSE2003 (P)` |
| `null` / `''` / missing | `AMSE2003` — no brackets |
| an unexpected raw value (`'Lecture'`) | `AMSE2003 (Lecture)` — passthrough, never guessed |

`session_type IN ('L','T','P')` makes the last row unreachable on live data; the guard costs one
ternary and stops a payload defect printing `()`.

### TR-06 — No new class, no `theme.css` line

Classes added: **none**. Classes removed: `cell-name` (all three rewrites) and
`lecturer-cell-id` + `lecturer-cell-email` **from the cell only**. Every token used is already
resolved by the classes that stay. No inline style, no hex, no `rgb()`, no new icon, no new
dependency.

### TR-07 — The pop-up is unchanged

The details pop-up still prints the proposer's name, the `(staffId)` and the click-to-copy email,
and its subtitle still reads `CODE — Name (Type)`. The name the table stops printing survives in
three places: that subtitle, the search haystack, and the row payload.

### TR-08 — The pinned tests are verified, never weakened

`tests/Feature/CourseColumnTipTest.php` reads the sources as text. It has **four test methods and
about a dozen assertions** (the lane-relevant four are named below; the rest pin the `Course` label,
the header hint and the one-sentence rule). **The whole file stays green** — the four that bear on
this lane are: no `UiLabels.courseTip(` in the blade, the `html += '<td><div class="cell-code">' +
r.courseCode` prefix, no `tip: UiLabels.courseTip(` in `ui-common.js`, and the three-line
`cls: 'col-code'` object shape. **If one fails, the rewrite broke the constraint — fix the cell.**

Four Playwright specs touch the same DOM and are re-run rather than assumed: `type-labels` (`.cell-type-label`
must read `(P)`/`(L)`/`(T)`, exactly one per row), `ui-regression` and `replacement-home-status-badges`
(`.col-code` text), `request-cancel-pending` (`.col-code` click). `request-approval`'s email-chip
count is modal-scoped, which is why removing the cell's copy icon is safe.

### TR-09 — Not a deferral: the look-alikes keep the ID, by decision

The phone card and the approve/reject confirm card still print `Name (staffId)`. No lane owns them,
and this lane leaves them **by decision, recorded** — not because someone else will handle it.

### TR-10 — Out of scope, and stays out

Column widths, `min-width`, `table-layout`; the cards; the badges; the pop-ups; the filters; the
summary cards; the pagination bar; the notification rows; the print view; the header tooltips; the
frozen `ui-design-templates/*` and `ui-prototype/*`; any backend, route, Livewire property, mapper or
DB change.

---

## 2b. Scenarios

**S1 — the trim, on a row whose lecturer is found**
Given a pending request whose proposer is a known lecturer
When the Request Approval table renders
Then the Staff cell shows `Surayaini Binti Basri` in `.lecturer-cell-name`, and the row's markup
contains no staff ID and no email address.

**S2 — the trim, on a row whose lecturer is not found**
Given a request whose proposer is not in the lookup list
Then the cell shows `r.lecturer` exactly as today, and nothing about the trim applies to it.

**S3 — the pop-up still carries what the table lost**
Given the same row
When its details pop-up opens
Then the strip's `Staff (Proposer)` row shows the **name**, and the **Class Info** tab shows the
`(staffId)` and the click-to-copy email chip. *(Two rows share the label; since task 10 the strip is
name-only by decision, so a check that looks for the ID in the strip will fail the page, not the
trim.)*

**S4 — one line, three pages**
Given any of the three tables
When a row renders
Then `.cell-code`'s line reads `AMSE2003 (L)` with no second line, on 1024 / 1280 / 1600, in both
themes.

**S5 — a missing type**
Given a row whose type is null or empty
Then the cell reads `AMSE2003` with no brackets and no `()`.

**S6 — search still finds what is no longer printed**
Given a subject name that no cell prints any more
When the user types that name
Then the row is still found — both searches read the data array, not the cell.

**S7 — sorting is unaffected**
When the user sorts by Course
Then the order is unchanged — the sort key is `course`, not the cell's text.

**S8 — the row still works**
The cancel flash, the bulk select and the row→pop-up click all still work: no `data-request-id`,
no `data-id` and no click target is touched.

**S9 — the 1024–1280 squeeze relaxes**
Given the Request Approval table at 1024–1280px
Then the Staff column is narrower and the slot columns are no longer the squeezed ones (auto layout,
so the width is not pinned anywhere — this is an observation, not a number to assert).

**S10 — nothing else moved**
`git diff --stat` proves the two timetable pages, `theme.css`, the pop-up partials and every backend
file are untouched.

---

## 3. Tasks

**Shape:** one batch, three product edits, then verification. The three pages are independent; a
partial landing still leaves every page self-consistent.

**Standing constraints:** no new dependency · no colour, no new class · never edit
`ui-prototype/*` or `resources/views/ui-design-templates/*` · never `migrate:fresh`/`db:wipe`/reseed ·
never `--env=testing` · never push · commit only this lane's files.

- [ ] **T12a — Request Approval: both cells in one pass** *(30m)*
  `resources/views/livewire/request-approval.blade.php`, design 2a + 2b. The Staff cell becomes the
  name alone inside the wrapper; the Course cell becomes one `cell-code` div with the guarded type.
  The `else` branch and the `html += '<td><div class="cell-code">' + r.courseCode` prefix are
  preserved word for word. `php -l` clean; reload and confirm the rows and no console error.

- [ ] **T12b — `ui-common.js`: the merged row's Course cell** *(30m)*
  Design 2c, inside `if (opts.merged)` only. `html:` stays before `cls: 'col-code'` on its own line;
  the leading space is present; `typeLabel` stays; the legacy body is not touched. Reload My
  Replacements.

- [ ] **T12c — Student page: `courseCell()` and its comment** *(20m)*
  Design 2d. Same spans, same leading space, the guard, and the comment above the function rewritten
  to describe the cell that now exists. Reload the student page.

- [ ] **T12d — The pinned test, plus the static checks** *(30m)*
  Run `tests/Feature/CourseColumnTipTest.php` and expect **green**; if a case fails, fix the cell,
  never the test. Then the greps: no `cell-name` left in the three render sites, no
  `UiLabels.courseTip(` in the blade, the prefix intact, the three-line object shape intact, and
  `git diff --stat` listing exactly **three** product files — `request-approval.blade.php`,
  `ui-common.js`, `replacement-history.blade.php` — plus the three changelogs. **`replacement-home.blade.php`
  must not appear:** its cell comes from the shared row builder (proposal §9 says "nothing").

- [ ] **T12e — Live verification, three pages × four widths × two themes** *(1h)*
  At 768 / 1024 / 1280 / 1600, light and dark: the Staff cell reads the name alone and still bold
  13px; all three Course cells read `CODE (TYPE)` on one line; the pop-up still prints name, ID and
  the copy-email chip; the subtitle still reads `CODE — Name (Type)`; typing a subject name still
  finds the row; sorting by Course is unchanged; the cancel flash, bulk select and row→pop-up all
  work; no console error. Photograph the 1024 RA table (the squeeze) and one Course cell per page.

- [ ] **T12f — The four DOM specs** *(45m)*
  `tests/type-labels.spec.ts` · `tests/ui-regression.spec.ts` ·
  `tests/replacement-home-status-badges.spec.ts` · `tests/request-cancel-pending.spec.ts`. Record
  pass/fail **before and after** so a pre-existing red is attributed, not inherited. Any red caused
  by this lane is fixed inside this lane, with a note in the apply log.

- [ ] **T12g — Changelogs + one commit** *(30m)*
  Three `page-changelogs` entries. The Request Approval entry must state that its Course cell
  **gains** the type while the rest is a trim, and must name where the email went (the pop-up). One
  `ui:` commit, only this lane's files. Write the apply log: what changed, the receipts, and every
  observed red with its attribution.

- [ ] **T12h — Archive** *(5m, on the user's word only)*
  Move the lane to `.sdd/archive/<date>-table-column-trims/`, `status: archived`.

---

## 4. Rollback

Three string literals and one `<div>` per page. Reverting is the inverse diff; no data, no cache, no
schema, nothing persisted. The three changelog entries name the commit, so a revert is traceable.
