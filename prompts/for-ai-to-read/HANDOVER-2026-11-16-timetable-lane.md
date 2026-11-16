# HANDOVER — 2026-11-16, PI chat → new PI chat

> Written by the session that finished `my-timetable-responsive-prompt`. Read this AFTER the four
> standing files (they outrank it on every rule), and treat it as the map of where the last session
> stopped — not as authority. **Verify, never assume.**

---

## 0. Read these first, in this order

1. `prompts/for-ai-to-read/AGENTS.md` — how to work in this repo.
2. `prompts/for-ai-to-read/CodingMAIN.md` — the spec of record (§4 DB safety, §7 FR & NFR, §10 conventions + UI rules).
3. `prompts/for-ai-to-read/my-coding-rules.md` — the user's rulebook in quick form.
4. `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` — §0 quick status, §1 sessions, §2 the **51** standing rulings.
5. `prompts/for-ai-to-read/my to-do list.md` — **the user's own task list. Untracked: never stage it.** Read it, never rewrite / re-word / delete a task; a status block may be updated after you verify something.
6. `.sdd/archive/2026-11-16-my-timetable-responsive-prompt/` — the lane this handover is about, **archived 2026-11-16** (`proposal.md`, `design.md`, `specs/…/spec.md` (R1–R5c), `tasks.md`, `apply-log.md`, `review-log.md`, `verify-report.md`, `sdd.yaml` with `status: archived`).

The user's standing style: **plain everyday English**, short. For a decision: explain in plain terms,
list the options with their real risks, recommend one, **ask one question at a time**, and never
start an approval-requiring action before the answer.

---

## 1. Where the project stands (measured 2026-11-16, verify before quoting)

| Fact | Value |
|---|---|
| Branch | `fedora-backend` (`origin` = `fjing03/CRS-fedora.git`; `upstream` is pull-only) |
| HEAD | **`803aed7`** (a login-page fix from another session; the timetable lane's own work ends at **`7809c1b`**) |
| Unpushed | **40 commits** — `origin/fedora-backend` is still at `c8b06ff`. Re-check: `git rev-list --count origin/fedora-backend..HEAD` |
| Dev server | `:8030` on **`class_replacement_testing`** — this DB holds the **user's protected manual work**. It is running. |
| SDD lanes | 18 folders in `.sdd/changes/`; **153** in `.sdd/archive/` — the timetable lane was **archived 2026-11-16** (`.sdd/archive/2026-11-16-my-timetable-responsive-prompt/`) |

**Two sessions were editing this tree.** The working tree carries uncommitted changes from other
sessions and from this one (see §5). Anything you commit must be **hunk-staged to this session's own
work** — never a blanket `git add -A`, never another lane's files, never `docs/demo-db-data-changes.md`
unless that log *is* your task.

---

## 2. What this session completed (all committed — do NOT redo)

Task 8 of the user's list, `my-timetable-responsive-prompt`: the responsive Student + Staff My
Timetable pages. Four phases, four commits, all verified:

| Commit | What |
|---|---|
| `f1b2a66` | **Two user bugs.** (a) **TODAY landed on the wrong date below 1280** — the button moves the *week* while the module keeps the selected *weekday*; `_bindToday()` now puts the day back on today (deferred + retried 12 × 80ms). (b) **A public holiday lost its class cards below 768** — the notice was a replacement, not an addition; it now draws above the day's cards. |
| `13489ea` | **Word ruling (§2 #51).** A cancelled block/card already saying `(Proposed ⏳)` no longer also says `✕ Cancelled` — both surfaces, cancelled only (a conflicted block with a proposal keeps `⚠ Conflict`). Tips/modal/payload keep the server's truth. |
| `f285139` | Page-changelog entries for the above. |
| `7809c1b` | **Round 2 of the prototype comparison: nine rulings applied, four kept.** Applied: #1 week dropdown type per band (13px from 768 up) · #2 phone band ends at **767** · #3 every phone week label fits (all 14) · #4 tablet heading `Mon, 23 Nov 2026 ~ Tue, 24 Nov 2026` · #7 sheet foot Close (48px) · #8 sheet is content-height · #10 empty weekday is plain + names its date · #11 a whole empty week is ONE panel with the hint inside · #12 guide dialog takes the preview layout (**opt-in**; the other seven pages unchanged). **Kept as-is on the user's word:** #5 the 320 toolbar wrap · #6 the full-width tablet sheet · #9 the short semester chip above the phone band · #13 the cohort chip's spacing. |

Earlier in the same lane (already in `origin` history before this session): the phone agenda + day
strip, the tablet transposed window, the Week summary sheet with `SummarySheet` **gated off** on these
two pages, the byte-identical pin, and 12 first-round prototype follow-ups.

**Receipts, all green (measured 2026-11-16):**

```bash
CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
  npx playwright test tests/timetable-responsive.spec.ts tests/timetable-callers-pin.spec.ts \
  tests/e2e/timetable.spec.js --reporter=line      # 42 passed
```

`tests/timetable-responsive.spec.ts` holds 33 cases; the last six (`T16d`) are the round-2 rulings —
they **measure** the label fit against all fourteen weeks and drive the empty-week panel at
390/900/1440 by blanking the week in the page's own payload.

**Known reds — not this lane's, do not "fix" them inside it:** `summary-cards-button` T11a
(testing-DB data gap on `/my-replacements`), `student-today` + `timetable-wiring` (both assert
`#weekSubtitle`, removed from the live blades earlier; the element only survives in
`resources/views/ui-design-templates/`, which must not be edited), `modal-responsive-width` (5 reds
**at HEAD** — proven by stashing this lane's changes and re-running), `ui-regression` (9 stale cases
wanting a "Subject Code" label removed by `0e43019`), `class-modal-affordances` (fails inside its own
login).

---

## 3. What is still open

1. **Nothing left in the lane — it is archived.** `.sdd/archive/2026-11-16-my-timetable-responsive-prompt/`
   (2026-11-16), `status: archived`, and the handover + to-do status were synced the same evening.
2. **A docs sync sits uncommitted** (see §5) — this session's user-facing docs were updated but not
   committed, because the same four files carry another session's hunks.
3. **Nothing else from this lane.** The user's to-do list has further tasks below task 8; **do not
   start one without being told which.**

---

## 4. Decisions and traps you must not re-litigate

**Design decisions already made and shipped** (full detail: the lane's `spec.md` R5c, `apply-log.md`):

- **Phone band = 320–767; 768 is a tablet.** Every band rule uses that boundary. Getting it wrong is
  what made the year vanish at exactly 768.
- **The Week summary sheet is an overlay div (`z` 1200), not `<dialog>`** — top layer would beat the
  ⓘ dialog. One Escape = one layer (capture-phase, suspended while a dialog above is shown).
- **`SummarySheet.init()` returns early on `.tt-responsive`** — this lane's own sheet moves the same
  two nodes; two relocators would fight. Do not un-gate it.
- **The tablet band is a grid the module builds** (days as columns) — the real table has days as
  rows, so hiding rows can never look like the preview.
- **`buildTimetableGrid()` changes live only inside `cfg.responsive`**, pinned byte-for-byte against
  `tests/fixtures/t0a-baseline.json` by `tests/timetable-callers-pin.spec.ts`. Four other callers must
  stay identical.
- **`?week=` is not honoured by these two blades** — the week is fixed through `window.selectWeek(i)`.
- **`window.currentWeek` can lag a Livewire swap**; the pages' own closures are the truth, and the
  phones hide the whole `.grid-wrapper` (its 1px frame showed as a stray hairline under a short panel).
- **The phone week label is 11px.** The user chose the smaller type over shortening the words
  (`Week 10 · 23–29 Nov`), because a new date format would need a month-boundary rule.

**Standing constraints** (from the files — repeated only because they are the ones most often broken):

- **Never `git push`.** Commit only files the current task touches; conventional commits
  (`feat:`/`fix:`/`ui:`/`oop:`/`refactor:`/`style:`/`docs:`/`test:`).
- **Never** `migrate:fresh` / `migrate:refresh` / `db:wipe` / re-seed a demo DB, and never
  `--env=testing` (weirdly boots the demo DB). `:8030` / `class_replacement_testing` = the user's
  manual data.
- **No code edit without the user's explicit per-task approval.** SDD workflow for anything real
  (explore → propose → design → specs → tasks → apply → verify → archive); the small-change exemption
  turns on **blast radius**, not line count.
- **Never edit `ui-prototype/*`** or `resources/views/ui-design-templates/*`.
- Colours from `theme.css` tokens only; reuse Flux/Livewire/Tailwind + `ui-common.js`; no new
  dependencies.

---

## 5. Uncommitted work in the tree (know this before you touch git)

- **This session's docs sync (uncommitted, deliberate):** `prompts/for-ai-to-read/CodingMAIN.md`,
  `PI_Chat_Context_Handover.md`, `my-coding-rules.md` (+ `my to-do list.md`, which must never be
  staged). They contain today's rulings, the refreshed push state, §2 #51 and the §1 session entry.
  If the user asks for a `docs:` commit, **hunk-stage only those additions** (`git diff` → filter →
  `git apply --cached --recount`), because the same files also carry another session's edits.
- **This session's lane state:** the lane's `sdd.yaml` was corrected to `status: archived`,
  `implementation_approved: true` and `batch: complete (T0a-T19)` — it had still said "proposed / ready
  for apply", which is exactly the kind of stale flag that makes the next session redo the work.
- **Other sessions' uncommitted edits** you will also see: `replacement-home.blade.php`,
  `replacement-history.blade.php`, `tests/rearrangement-toolbar-tooltips.spec.ts`,
  `tests/top-zone-round2.spec.ts`, several other lanes' `.sdd` files, `page-changelogs/cohort-…` and
  `page-changelogs/venue-…`, `docs/demo-db-data-changes.md`, and a deleted
  `.playwright-mcp/venue-toolbar-after.png`. **Leave them alone.**

---

## 6. Your first three steps

1. Read the six items in §0 (fully — `CodingMAIN.md` is long and is the spec).
2. **Verify the state yourself, do not trust §1:** `git log --oneline -6`,
   `git rev-list --count origin/fedora-backend..HEAD`, `git status --porcelain`, `tail -15
   .sdd/archive/2026-11-16-my-timetable-responsive-prompt/sdd.yaml`, and (if the server answers) the 42-check
   command in §2.
3. Then ask the user **one** question: which task on `my to-do list.md` to start next. Nothing from
   this lane is pending — do not offer to "finish" or "archive" it.

**Do not repeat:** the phone agenda, the tablet window, the Week summary sheet, the pin, the 12
first-round follow-ups, the two bug fixes, the Proposed/Cancelled ruling, or the nine round-2
rulings. All are committed, receipted and green — re-building any of them would be rework.
