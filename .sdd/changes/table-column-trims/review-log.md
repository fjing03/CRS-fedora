# Review log — table-column-trims

Append-only. Batch order: proposal → design → specs (skipped if the lane stays simple) → tasks,
each frozen before the next.

## proposal Round 1 — 2026-11-15

Reviewer: independent read-only (`opencode/sdd-reviewer`), proposal-only round.

### 🔴 Fixed
- **The My Replacements cell was anchored to the wrong branch.** The brief cited
  `ui-common.js:3649-3653` as the merged branch; that is the **legacy nine-cell body**. The merged
  branch is `:3614-3628`, cell at `:3617-3618`. An implementer would have edited the frozen
  template's renderer and shipped no change. Fixed in `explore-brief.md` (table + a sentence naming
  the live caller) and in proposal §3b/§9, which now say the legacy body stays byte-identical.
- **"No test asserts the cell" was false.** `tests/Feature/CourseColumnTipTest.php:89-93` pins the
  Request Approval prefix and `:102-106` pins the merged cell's object shape. Fixed: §6 and §7 now
  name the test and the two shapes, §9 lists it as "verify only", and §8 keeps both shapes as
  acceptance criteria.
- **§8's width promise could not pass on My Replacements.** That table is `table-layout: fixed` with
  `.col-code { width: 16% }` and `min-width: 1280px`, so a trim donates no pixels. Fixed: the width
  criterion is limited to Request Approval (auto layout), and My Replacements' criterion is now
  "shorter row, no wrap"; column widths are explicitly out of scope (§5).
- **The cell markup was unspecified** and both obvious readings broke a claim (`cell-name` is
  `display:block`, so the type would wrap; dropping `lecturer-cell-name` loses the column's only
  600-weight rule). Fixed: §3a/§3b name every kept wrapper and the `cell-type-label` class, and
  state that `cell-name` must be removed rather than emptied.

### 🟡 Addressed
- **§1's "first thing to overflow on a phone" was wrong** — the table does not render below 768px.
  The rationale now says 1024–1280px.
- **The FR 7.1 citation overreached.** It now says plainly that this is a presentational trim: IDs
  and emails are inside FR 7.1's permitted set, and the payload still ships them.
- **"Notification rows use `lecturer-cell-id`/`lecturer-cell-email`" was false.** Corrected; both
  rules stay because the details strip and `approvalCard` use them.
- **§4c's "rebuilt by `cards-and-badges-responsive`" was false** — that is an unstarted prompt, not
  a lane, and it never covers the confirm card. §2 now separates the one frozen lane from the two
  prompts, §4c says the keep is a decision with no owner, and `approvalCard` is cited `:510-541`
  / `:516-519`.
- **§4b was not genuinely open.** It is now presented as forced by the 2026-11-09 ruling **and** a
  passing test (`CourseColumnTipTest:84-88`, `:98-100`), with the tooltip path called out as its own
  decision.
- **§4d contradicted promote-on-3rd.** Now: no shared helper, with the reason recorded per page
  (RA pinned inline by the test, student page-local, My Replacements already shared) and an explicit
  "not promoted, because …" to be carried in `design.md` for verify.
- **§4a is no longer open**: the user answered on 2026-11-15 (student page in). Recorded as decided,
  including what a "no" would have re-opened.
- **The type output for a missing value, and RA gaining a type**, are now specified (§3b) and named
  in §8 as an addition rather than a trim.

### 🔴 Outstanding
- None pending re-review.

### Correction to Round 1's evidence — 2026-11-15, later the same day

`ui-common.js` grew while this lane was being written (the `filters-button` module, +266 lines at
`:2579`), so every raw line number for `replacementHomeRow` moved by ~290. The **finding was right**
— the merged branch is not the legacy nine-cell body — but its numbers (`:3614-3628`, `:3617-3618`,
legacy `:3649-3658`) no longer resolve. The brief and the proposal now cite the branch **by symbol**
(`replacementHomeRow` → the `if (opts.merged)` block) and give today's numbers only as a hint, with
a warning that the file moves under other lanes. Today: merged branch `:3905-3919`, course cell
`:3907-3911`, legacy body `:3939-3949`. **Lesson for design/tasks: cite `replacementHomeRow` and
`if (opts.merged)`, never a bare number.** The blade anchors were re-checked and still hold
(`request-approval.blade.php:684-685`, `:691`, `:146`; `replacement-history.blade.php:309`).

## proposal Round 2 — 2026-11-15

Reviewer: **not run** — the independent `opencode/sdd-reviewer` subagent aborted on the provider's
free-usage limit before it could read anything. Recorded honestly rather than counted as a pass.

Author self-check of Round 1's four 🔴 fixes, by reading the code (not the log):

- **Merged branch** — `replacementHomeRow`'s `if (opts.merged)` block is the seven-cell branch, its
  first entry is the `cls: 'col-code'` course cell, and the nine-cell body sits *below* the branch
  and is only reached when `opts.merged` is falsy. The brief and proposal now say exactly that, by
  symbol. ✅
- **The two regexes** — RA's `/html \+= '<td><div class="cell-code">' \+ r\.courseCode/` still matches
  the proposed line (which keeps that literal prefix and appends the guarded type before
  `</div></td>`); the merged builder's `/cls: 'col-code'\s*\n\s*\},/` still matches an object whose
  `html:` key precedes `cls: 'col-code'` on its own line. The legacy body is not touched, so its copy
  of the same shape keeps the test green too. ✅
- **`cell-name` is `display:block`** (`theme.css:1308-1313`) and `.cell-type-label` is inline, 12px,
  grey (`theme.css:5548-5552`); `.cell-code` is 700 weight. So `CODE (TYPE)` on one line requires
  dropping `cell-name` — which §3b now says in those words. ✅
- **`lecturer-cell-name`**'s only live use is the RA table cell (`:684`); its rule
  (`:146`) is why the wrapper stays. `lecturer-cell-id` / `-email` are still used at `:518` and
  `:934`. ✅
- **Achievability** — RA's table is `table-layout: auto` (`theme.css:1090`), so a narrower Staff cell
  does relax it; My Replacements is `fixed` with `.col-code { width: 16% }` and a 1280 `min-width`,
  which is why §8 no longer promises it extra pixels. ✅

**Outstanding: none known.** The proposal is ready for the user's freeze decision; a re-run of the
independent reviewer stays on the list for when the quota allows.

## design Round 1 — 2026-11-15

Reviewer: independent read-only (`opencode/sdd-reviewer`), design-only round, run against the frozen
proposal + explore brief.

### 🔴 Fixed
- **2c dropped the space before the type**, so My Replacements would have printed `AMSE2003(L)` while
  the other two tables printed `AMSE2003 (L)` — and the design claimed they "read identically". The
  frozen proposal spells the space out (`proposal.md:94`) and 2d carries it; only 2c lost it. Fixed:
  the block is now `(c.type ? ' <span class="cell-type-label">…`, plus a paragraph saying why the
  space is load-bearing (`theme.css:1305-1307` and `:5548-5552` both carry no margin).

### 🟡 Addressed
- **Six stale blade line numbers** (2a `:683-685` → `:720-726`, 2b → `:729`, `approvalCard :518` →
  `:552`, strip `:934` → `:982`, `UiLabels :527/:1090` → `:561/:1138`). Fixed, and §2 now carries the
  "anchor by symbol" warning that had only guarded `ui-common.js`.
- **§8 row 1 pointed at `theme.css:146`** (a banner comment) instead of the blade rule. Fixed.
- **§5's parenthetical was false**: the legacy body writes `cls: 'col-code' },` on one line and never
  matched the regex — so the merged object is the *sole* satisfier and the test is no safety net
  against reformatting. §5 now says exactly that.
- **Proposal §8 rows the acceptance table had dropped** — sorting unchanged, the `Staff (Proposer)`
  header, the timetable pages proven untouched, and the changelog *content* rule — added as rows 11–14.
- **2d left the comment above `courseCell()` saying the opposite** (it still described the two-line
  staff cell). §2d now quotes it and the finding, and prescribes the replacement wording.
- **§5 under-listed the specs** touching this DOM: `type-labels.spec.ts:47/52/64/68`,
  `ui-regression.spec.ts:194-195`, `replacement-home-status-badges.spec.ts:56/188/252`,
  `request-cancel-pending.spec.ts:150`, and why `request-approval.spec.ts:96` stays safe (modal-scoped).
- **The title's counts were wrong** ("three cells lose a line, four cells gain a type" → four lose,
  one gains), which would have set verify's expectation wrong from the first line.

### 🔴 Outstanding
- — None.

---

## Spec+tasks — round 1 (2026-11-17)

One review round for the combined artifact (`spec-and-tasks.md`), per the user's cost ruling.
Reviewer verdict: **no critical, no blocking** — three wording fixes, all applied.

### 🟡 Fixed
- **TR-01's evidence had expired, and this lane caused it.** It said the details strip still builds
  `lecturer-cell-id` / `lecturer-cell-email`. Since task 10 moved the ID and the email chip into the
  **Class Info** tab (`request-approval.blade.php:1129` prints the name alone), that sentence was
  false. Corrected to name the two real callers — the confirm card (`:620`) and Class Info (`:1284`)
  — so the conclusion (keep both rules) still holds but the check an implementer would run is now
  the right one. S3 carried the same drift and now says which row holds which fact.
- **T12d contradicted the artifact's own scope**: it asked for "four product files" while §0, §3 and
  proposal §9 all say three (replacement-home is explicitly "nothing"). Now three, and it says why
  `replacement-home.blade.php` must not appear in the diff.
- **TR-08 undercounted the pinned test.** `CourseColumnTipTest.php` has four **methods** and about a
  dozen assertions, not "four assertions". Rewritten: the four lane-relevant assertions are named,
  and the whole file must stay green.

### 💡 Taken
- `sdd.yaml`'s `open_decisions` still listed the course-tooltip question as open when proposal §4b
  and design §5 both call it forced. Removed from the open list.
- Design 2c printing raw `c.type` while 2b/2d route through `classType` is now explained in the
  artifact rather than left for verify to flag.

### 🔴 Outstanding
- None.
