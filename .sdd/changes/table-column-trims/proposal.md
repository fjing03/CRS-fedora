# Proposal — the three list tables lose what the pop-up already shows

**Lane:** `table-column-trims` · created 2026-11-15 · **proposal, not frozen**
**Reference:** `ui-prototype/request-approval.html` and `ui-prototype/my-replacements.html`,
the injected "the 9" layer, items **#8** and **#9** (bottom of each file).
**Evidence:** `explore-brief.md` in this folder (file:line for every claim below).

---

## 1. Why

The three list tables print the same fact twice, and the pop-up prints it a third time.

- **Request Approval's Staff column** prints `Surayaini Binti Basri (5425)` and, under it, her email
  with a copy icon. Every row repeats a staff ID and an email address the details pop-up already
  carries, and the table is the widest thing on the page: at **1024–1280px** the Staff and Course
  columns are the ones squeezing the slot columns. (Below 768px the table does not render at all —
  the cards take over — so this is a desktop-width problem, not a phone one.)
- **All three Course columns** print the code, then the full subject name, then the type. The same
  name is the pop-up's subtitle, so a reader who wants it is one click from it either way.

The user settled both trims in the prototypes (`the 9` #8 and #9) and then asked which lane owned
them. **None did** — not `filters-button`, not the `details-modal-consistency` prompt, not the
`cards-and-badges-responsive` prompt (all three explicitly leave the tables alone). This lane is
that home.

It is a **presentational** trim, not a data-minimisation measure: the payload still ships every
proposer's email to the browser (`ReplacementRequestMapper.php:349`), and FR 7.1 governs what the
system *processes*, listing staff IDs and TAR UMT email addresses among the permitted data. Say
what it is: a queue view that is easier to read, not a privacy change.

## 2. What this lane is not

Two prompts and one frozen lane touch the same three pages, and none of them touches a `<td>`:

| work | what it owns |
|---|---|
| `filters-button` (a lane, proposal frozen) | the filter door, the panel, the chips, the count pill |
| `details-modal-consistency` (**prompt only**, backlog item 10) | the pop-up's tabs, strip and footer |
| `cards-and-badges-responsive` (**prompt only**, backlog item 11) | the cards below 768px and the badge geometry |

The other seven items of the prototypes' "the 9" are also **not** here: #4 is asked for by
`filters-button`; #1–#2 are the summary cards; #5–#7 are unowned and unrelated. This lane carries
#8 and #9 only.

## 3. What this lane changes

Two trims, four cells, three pages.

### 3a. Staff column — Request Approval only

| | today | wanted |
|---|---|---|
| cell | `<div class="lecturer-cell-name">Surayaini Binti Basri <span class="lecturer-cell-id">(5425)</span></div>` + `<div class="lecturer-cell-email">surayaini@tarc.edu.my 📋</div>` | `<div class="lecturer-cell-name">Surayaini Binti Basri</div>` |

- **The `lecturer-cell-name` wrapper stays.** Its rule (`request-approval.blade.php:146`,
  600 weight / 13px) has this cell as its only live consumer; dropping the wrapper to "simplify"
  would silently un-bold every name in the column.
- The ID and the email leave the **markup**, not just the paint — a CSS hide keeps both reachable by
  select-all and by a screen reader, which is not the point.
- Both stay in the **details pop-up's** `Staff (Proposer)` row, which already prints the name, the
  `(staffId)` and the click-to-copy email.
- The **column header does not move**: it stays `Staff (Proposer)`, and so does its sort.
- The `else` branch (`:686-688`, lecturer not found → prints `r.lecturer`, a name) is already
  trim-safe. **It stays as it is** — no reason to collapse a fallback inside a trim.
- The My Replacements and student tables have no staff column; nothing happens there.
- **Not the cards and not the confirm dialogs.** `approvalCard()` (`request-approval.blade.php`
  `:510-541`, the `staffId` at `:516-519`) prints `Name (staffId)` inside the Approve/Reject confirm
  step, and the phone card (`HtmlBuilder.requestCard`, `public/js/ui-common.js:3554`) does the same.
  **No lane owns those** (§4c), so this lane leaves them untouched **by decision, not by deferral.**

### 3b. Course column — all three pages (user, 2026-11-15: yes, the student table too)

| | today | wanted |
|---|---|---|
| cell | `AMSE2003`<br>`Software Testing and Quality Assurance (L)` | `AMSE2003 (L)` |

- One line: the code, a space, the type in brackets — `AMSE2003 (L)`, `AMSE2003 (T)`, `BMSE2163 (P)`.
- The letter comes from `UiLabels.classType(type, 'code')` (`public/js/ui-common.js:3276`), so it can
  never print `Lecture` here while the pop-up prints `L`.
- **Missing or unknown type prints the code alone** — no `(undefined)`. `UiLabels.classType` returns
  its input unchanged, and the sibling subtitle already guards the same way
  (`request-approval.blade.php:1090`). The DB constraint `session_type IN ('L','T','P')` makes it
  unreachable on live data; the guard is one ternary and costs nothing.
- **Request Approval's cell gains a fact.** It prints no type today, so on that page #9 is an
  addition inside a "trim" — the acceptance must say so (§8).

**The three cells are rewritten in place, and two of them are shape-pinned by a source-regex test
(`tests/Feature/CourseColumnTipTest.php`, §6). Each keeps its own wrapper:**

| page | what the new cell must look like in the source |
|---|---|
| Request Approval | the line must still open `html += '<td><div class="cell-code">' + r.courseCode` and stay a single `<div class="cell-code">CODE (T)</div>` — **no `cell-name`, no `data-tip`** (the test asserts both the prefix and the absence of a tip) |
| My Replacements | the cell object must still end `cls: 'col-code'` on its own line, with its `html:` key last before it; the content becomes `<span class="cell-code">CODE</span> <span class="cell-type-label">(L)</span>` |
| Student | `courseCell(r)` returns the same two spans, with the type guarded |

- **`cell-name` must go, not just be emptied:** `.cell-name { display: block }`
  (`public/css/theme.css:1308-1313`), so keeping the span for the type would print `CODE` and `(L)`
  on two lines and break §8's "one line".
- The type keeps the shipped **`cell-type-label`** class — the small grey bracket the tables already
  use. No new class, no `theme.css` line.
- The name leaves the cell and **survives in three places**: the pop-up's subtitle
  (`CODE — Name (Type)`), the search haystack (which reads the data, not the cell), and the row's own
  payload.
- **No hover rescue** (§4b).
- The frozen `ui-design-templates` copy is reference only and is **not** edited. Inside
  `ui-common.js`, the **legacy nine-cell body below the `if (opts.merged)` block (today `:3939-3949`)
  stays byte-identical** — it belongs to
  that template, not to the live page.

## 4. Decisions

### a. Does the student page's Course column change too? — **DECIDED: yes (user, 2026-11-15)**

The user answered "yes — `AMSE2003 (L)` on one line" for the student table as well. That is the
third page, which is why this is a lane and not a small-change exemption; a "no" would have
re-opened that shape argument. Nothing in the repo forced it — the student blade's "mirror the staff
table" comment is about the *column set*, not the cell markup.

### b. The course name's last home — **not open; already forced**

The 2026-11-09 ruling "the Course **cell** carries no hover text" is recorded in
`request-approval.blade.php:689-690` and in the student `courseCell()` comment, and it is **locked by
a passing test**: `tests/Feature/CourseColumnTipTest.php:84-88` requires the Request Approval blade
to contain no `UiLabels.courseTip(`, and `:98-100` requires the shared builder to attach no course
tip. So a cell tooltip is not a trade-off; it is "reverse a recorded ruling **and** flip a passing
test". **Recommendation: pop-up only.** If the user wants the tooltip, it is its own small decision
with that test in view.

### c. Look-alike surfaces that keep the ID — **a decision, not a deferral**

The phone card and the Approve/Reject confirm card still print `Name (staffId)`.
**No lane owns either**: `cards-and-badges-responsive` is an unstarted prompt scoped to the three
cards + badges, and its Request Approval section asks for the proposer in the card head without
deciding the ID. **Recommend:** leave them, and record here that the desk table and the phone card
deliberately differ until someone asks for the card change — not "someone else will handle it".

### d. One shared course-cell helper? — **recommend no, and record the exemption**

The repo's promote-on-3rd rule would normally ask for one shared home. Here it cannot have one
cleanly: Request Approval's cell is **pinned to stay inline** by the regex in
`tests/Feature/CourseColumnTipTest.php:89-93`, the student cell is page-local, and the My
Replacements cell already lives inside the shared row builder. Three one-line builders in three
architecturally forced places is the honest answer — but **design.md must record it as an explicit
"not promoted, because …"**, so `/sdd-verify` has something to check instead of a silent exemption.
(Note: `HtmlBuilder.replacementHomeCard` already prints `CODE (L)` at `ui-common.js:3676`; the shape
is not new, only its home.)

## 5. Out of scope

- The **cards** (phone and tablet), the **badges**, the **pop-ups**, the **filters**, the summary
  cards, the pagination bar — other work, and this lane touches no shared component of theirs.
- The **notification row** (`resources/views/partials/notif-row.blade.php`) — tips are composed
  server-side; nothing here touches them.
- The **approve/reject confirm card** and the **phone card** — see §4c.
- The **print view** and the **table header tooltips**.
- Any backend, route, Livewire property, mapper or DB change. This is Blade/JS render code.
- The **column widths**. My Replacements is `table-layout: fixed` with a share per column
  (`.col-code { width: 16% }`, `replacement-home.blade.php:26`) and `min-width: 1280px` (`:58`);
  changing those is `filters-button`'s and the cards lane's business, not this one's.
- The other seven items of "the 9".

## 6. Constraints

- Colours only from `theme.css`; in practice this lane adds **no** colour and **no** new class — it
  removes two elements, drops one `span`, and rewrites three lines.
- `lecturer-cell-id` and `lecturer-cell-email` **stay** in the blade's `<style>`: the details strip
  (`:932-936`) and `approvalCard` (`:516-519`) still use them.
- `compactCohort()`, the slot badge, the age chip and the class blocks are untouched.
- The row's `data-request-id` / `data-id` and the click target are untouched, so the cancel flash,
  the bulk-select and the row→pop-up path keep working.
- **`tests/Feature/CourseColumnTipTest.php` is verified, never weakened.** Its two regexes pin the
  shape of both rewritten cells; if they fail, the rewrite broke the constraint, not the test.
- No new dependency. No test run without the user's word; if a spec breaks, it is fixed inside this
  lane with a note, never by weakening the page.

## 7. Risks

| risk | real? | mitigation |
|---|---|---|
| The PL loses one-click email from the queue | **yes** | the pop-up keeps it, one click away; recorded as a decision (§3a), not an accident |
| The PL can no longer scan subject names | **yes** | the name is in the pop-up subtitle and still searchable; the row is clickable today |
| A test asserts the old cell | **yes — shape, not text** | `CourseColumnTipTest:89-93` (RA prefix) and `:102-106` (merged object shape); both are specified in §3b and verified in §9 |
| Search stops finding a hidden name | **no** | both searches read the data array (`request-approval.blade.php:650`, `replacement-home.blade.php:711-712`) |
| `cell-name`'s `display:block` splits the line | **yes if it is kept** | §3b drops the span and keeps the inline `cell-type-label` |
| A shared builder breaks another page | **no** | the merged branch has one live caller (`replacement-home.blade.php:793`); the legacy body is untouched and the design template is frozen |
| The desk and the phone disagree about the ID | **yes, by decision** | §4c: cards keep it, and no lane owns them |
| Row height changes | **unlikely** | the staff cell loses its second line, so rows get *shorter*; the course cell was already two lines and stays two lines of box only if the type wraps — verify at 1024 |

## 8. What "done" looks like

- Request Approval: every row's Staff cell reads the name alone in its bold 13px wrapper; the pop-up
  still shows the name, the ID and the email; the header still reads `Staff (Proposer)` and still
  sorts.
- All three tables: the Course cell reads `CODE (TYPE)` on **one line**; the pop-up subtitle still
  reads `CODE — Name (Type)`; sorting by Course is unchanged; typing a subject name still finds the
  row whose name is no longer printed.
- Request Approval's Course cell now carries the type — an **addition**; the rest of the change is a
  trim. Say so in that page's changelog.
- Request Approval's table relaxes at **1024–1280px**: the Staff column is narrower and the slot
  columns stop being squeezed. (My Replacements keeps its fixed 16% share and its 1280px min-width,
  so its *row* gets shorter and stops wrapping, but the table does not gain pixels.)
- Both themes, the three pages, at 768 / 1024 / 1280 / 1600.
- No page error in the console; the cancel flash, bulk-select and row→pop-up paths still work.
- The three page changelogs updated; the two timetable pages proven unchanged (they are not touched).

## 9. Files this lane will touch

| file | change |
|---|---|
| `resources/views/livewire/request-approval.blade.php` | staff cell → name only (wrapper kept); course cell → `CODE (TYPE)` (prefix preserved) |
| `public/js/ui-common.js` | `replacementHomeRow`, **`if (opts.merged)` branch only** (today `:3905-3919`) → `CODE (TYPE)`; legacy body untouched |
| `resources/views/livewire/replacement-history.blade.php` | `courseCell()` (`:309-312`) → `CODE (TYPE)` |
| `resources/views/livewire/replacement-home.blade.php` | **nothing** — its cell comes from the shared row builder |
| `tests/Feature/CourseColumnTipTest.php` | **verify only**; no edit expected if §3b's shapes hold |
| `page-changelogs/{request-approval,replacement-home,replacement-history}-changelog.md` | the three receipts |

No `theme.css` block, no new partial, no migration.
