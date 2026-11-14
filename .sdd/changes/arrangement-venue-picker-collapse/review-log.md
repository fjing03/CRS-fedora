# Review log — arrangement-venue-picker-collapse

> **Lane shape:** light — `proposal.md` + `design.md` + `tasks.md`, no `specs/`
> (user instruction: "I want the light SDD lane").

## all (light lane: proposal + design + tasks) Round 1 — 2026-11-13

**Method note.** All three artifacts were checked against the real files rather
than their own assertions: `venue-timetable.blade.php` (CSS 229–276, JS
655–707), `replacement-arrangement.blade.php` (1226–1319, 1809–1829, 5322–5474),
`partials/ui-arrangement-mobile.blade.php`, `theme.css` (line 6, 4273),
`playwright.config.ts`, `tests/venue-dropdown-responsive.spec.ts`,
`tests/arrangement-mobile.spec.ts`, and `.sdd/changes/cancelled-slot-reuse/`.

The reviewer had no shell, so `git diff` figures were not independently re-run
in round 1. The orchestrator re-verified the two blockers (R1, R2) and five of
the six 🟡 findings directly; results are noted per finding.

### 🔴 Fixed
- (none — round 1)

### 🔴 Outstanding (blocking, both fixed in round 1 → see "Fixed" in Round 2)

- **R1 — the rollback destroys a concurrent lane's work.** `cancelled-slot-reuse`
  is `status: applying` with `implementation_approved: true`. Its **B02** edits
  `replacement-arrangement.blade.php` ("Update arrangement desktop and mobile")
  and its **B03** edits `page-changelogs/replacement-arrangement-changelog.md`
  ("Update arrangement changelog") — the same two files this lane edits.
  **Verified:** `grep -n "B02\|B03" .sdd/changes/cancelled-slot-reuse/tasks.md`
  → both tasks are `- [ ]` (unticked, not yet started) and the lane is genuinely
  mid-run (its `app/CancelledSlotCleanup*.php` files were written minutes before).
  Therefore the T1 whole-file `/tmp` restore is **as destructive as
  `git checkout`** — it would silently drop every `cancelled-slot-reuse` hunk
  written between backup and restore. Proposal line "No lane currently owns the
  existing uncommitted work" is **false**.
  Also: the ownership note named **B02** (tooltip composition) when the step
  that rewrites the changelog is **B03**.
  → Fix: hunk-scoped revert; name the concurrent `applying` lane; note →
  B03; apply gate waits for B02/B03.

- **R2 — the T1/T6 verification commands cannot run as written, and one would
  hit the demo DB.** **Verified:**
  `tests/arrangement-mobile.spec.ts:72` →
  `abortUnlessTestingTarget('tests/arrangement-mobile.spec.ts', { requireAllowSql: true })`
  — the bare `npx playwright test` aborts at collection without
  `CRS_MODE=testing`, `CRS_ALLOW_SQL=1` and `CRS_BASE`. **Verified:**
  `playwright.config.ts:10` → `baseURL: (process.env.CRS_BASE ?? 'http://localhost:8000')`,
  i.e. `:8000` = the **demo DB**, and
  `grep -c abortUnlessTestingTarget tests/venue-dropdown-responsive.spec.ts` → **0**,
  so that untracked spec is unguarded and T1's baseline would drive the demo DB.
  T1's "if a test is already failing, stop and report" then turns a
  mis-invocation into a false lane stall — or into the forbidden temptation to
  loosen the guard.
  → Fix: paste the exact guarded invocation and the `:8030` start into T1 and T6.

### 🟡 Outstanding (non-blocking)

- **Y1 — "rule 2 is load-bearing, B010 fails without it" is wrong twice.**
  **Verified:** `arrangementVenueParts` (blade 5322–5330) uses `text.match(/\d/)`
  and takes `floorMatch[0]` — the **first digit** — so `B010` is floor `'0'`, not
  "Floor 1". The venue page's "B100 sits on Floor 1" comment was mis-transplanted.
  **Verified by reasoning:** any search that resolves to one venue yields one
  group, `groupIndex === 0`, so it opens even with the `q ? true :` branch
  deleted — `B010` *and* `B100` both pass without it. The branch genuinely
  matters only when a search spans **two or more** groups (e.g. a term matching
  venues in different blocks), and **no test covers that**.
  → Fix: search a term spanning ≥2 groups and assert a **non-first** group is
  the one that opened; correct the sentence in design §7.2/§12.6 and T4.

- **Y2 — the reduced-motion claim is factually wrong.** **Verified:**
  `replacement-arrangement.blade.php:1811–1829` has its own
  `@media (prefers-reduced-motion: reduce)` block that sets
  `transition-duration: 0.01ms !important` on
  `body.arrangement-mobile-mode #arrangementMobile *` (and `::before`/`::after`).
  The chevron is **already** reduced-motion compliant. Reading only
  `theme.css:4273` (`.skeleton`) gave the wrong answer. Behaviour is correct;
  no code change needed.
  → Fix: replace design §10's claim; T4 must not assert `transform` under
  reduced-motion emulation.

- **Y3 — wrong chevron class in one proposal bullet.** `proposal.md` §4 wrote
  `svg.arrangement-mobile-venue-chevron`; the real name is
  `.arrangement-mobile-venue-group-chevron` (design §3/§8 are correct). A literal
  copy into the test would fail.
  → Fix: renamed in the proposal; class added to the mapping table.

- **Y4 — REJECTED: the reviewer misread `###` as `##`.** The reviewer stated the
  changelog has "no 2026-11-13 entry and no entry about the mobile venue sheet",
  newest being `## 2026-11-12` at line 1. **Verified false.**
  `grep -n "2026-11-13\|venue picker width" page-changelogs/replacement-arrangement-changelog.md`:
  - line **3753** `### 2026-11-12 — venue picker width, week nav width, mobile venue sheet`
  - line **3768** `### 2026-11-13 — Selection Summary always present + collapse arrow`

  Both exist, as `###` entries appended at the **end** of the file — which is
  exactly the "existing uncommitted section" T5 referred to, and confirms the
  venue-sheet precedent this proposal cites. The reviewer's `^## ` grep missed
  them.
  **Residual valid point:** the file's convention is newest-first `##` headings
  at the top, while these uncommitted entries sit at the bottom.
  → Fix: T5 adds a **new `##` entry at the top** (newest-first, per AGENTS.md)
  and explicitly does **not** move or reorder the existing uncommitted `###`
  block at 3753–3782.

- **Y5 — T6 never restarts the dev server.** **Verified rule** (AGENTS.md standing
  law): after any Blade change, restart through `scripts/dev-serve.sh`, or the
  stale-view/OPcache trap serves the old markup and the new test fails for a
  phantom reason.
  → Fix: restart step added between T2/T3 and the Playwright runs.

- **Y6 — `groupIndex` is used but never introduced.** **Verified:** blade line
  5372 is `}).forEach(function (group) {` — the callback has no index, yet every
  T3 bullet depends on `groupIndex`.
  → Fix: T3 states the signature change `function (group, groupIndex)`.

### ✅ Confirmed good (re-verified, no action)

- The port is verbatim: every property and attribute in design §3/§5/§8 is
  present and in the same order as the reference, including `font: inherit`
  **before** `font-size: 11px` (reversing them silently kills the 11px).
- Mapping table has no collisions; `arrangementPickerGroup<N>` cannot clash with
  the venue page's `venuePickerGroup<N>`.
- All three breakage claims hold. `arrangement-mobile.spec.ts` `t22DifferentVenue`
  is additionally safe because it queries and clicks **inside `page.evaluate`**.
- `grep -c "venue-picker-group" public/css/theme.css` → **0**; the sheet lives in
  `partials/ui-arrangement-mobile.blade.php`, `@include`d by one page only. So
  "no shared file touched" is honest.
- Sticky/z-index reasoning holds; `box-sizing: border-box` (theme.css:6) means the
  bar cannot overflow, so the existing `scrollWidth <= clientWidth + 1`
  assertion survives.
- No duplicate-id risk: one host, cleared on every render.

### Verdict round 1

**FAIL — 2 🔴.** Not freezable, not applicable. Both blockers are
documentation-level fixes, not a design rethink.

---

## all (light lane) Round 2 — 2026-11-13 · fixes applied, awaiting re-review

### 🔴 Fixed

- **R1 — rollback is now hunk-scoped, and the concurrent lane is named.**
  - `proposal.md`: the `cp`-restore rollback is **removed**. Replaced with
    hunk-scoped capture (`git diff -U3 … > lane.patch`) and `git apply -R`, with
    a mandatory "re-diff and confirm no `cancelled-slot-reuse` hunks are caught"
    step before any revert.
  - Added a "A second lane is applying on these same two files" section with the
    B02/B03 table, and an explicit **apply gate** (wait for B02+B03, or the user
    accepts concurrent apply with hunk-scoped rollback).
  - The ownership note now names **B03** for the changelog (B02 edits the blade).
  - `tasks.md`: the "Before anything else" block states all three facts and
    forbids every whole-file restore path.

- **R2 — the guarded invocation is now written out verbatim.** `tasks.md` T1 and
  T6, plus `design.md` §13 step 6–7, carry:
  `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030`, then
  `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test …`.
  The reason is recorded inline: `arrangement-mobile.spec.ts:72` aborts at
  collection without it, and `playwright.config.ts:10` otherwise falls back to
  `http://localhost:8000` = the demo DB, with
  `venue-dropdown-responsive.spec.ts` carrying no guard of its own.

### 🟡 Addressed

- **Y1** — `design.md` §7.2 rewritten: rule 2 matters only for **multi-group**
  searches; a single-venue search (`B010`, `B100`) opens at index 0 either way,
  so it proves nothing. The `/\d/` first-digit behaviour of
  `arrangementVenueParts` is recorded, and the mis-transplanted "Floor 1"
  comment is explicitly warned against. `design.md` §12 item 6 and `tasks.md` T4
  now require a **multi-group** term and a **non-first** group assertion.
- **Y2** — `design.md` §10 rewritten: the blade's own block at 1811–1829 already
  zeroes transitions inside `#arrangementMobile`, so reduced motion is already
  handled and needs no code. T4 forbids asserting `transform` under
  reduced-motion emulation, and T2 forbids touching that block.
- **Y3** — `proposal.md` §4 now reads
  `svg.arrangement-mobile-venue-group-chevron`. T3 pins the same class.
- **Y5** — `tasks.md` T6 begins with the `scripts/dev-serve.sh 8030` restart,
  with the stale-view trap named.
- **Y6** — `tasks.md` T3 opens with the signature change
  `forEach(function (group, groupIndex) {`; `design.md` §5 explains that the
  current callback receives no index and that `groupIndex` restarts per render.
- Reference line span corrected to JS **655–707**.

### 🟡 Rejected (with evidence)

- **Y4** — the reviewer stated the changelog has no mobile-venue-sheet entry and
  no 2026-11-13 entry. **That is false.**
  `grep -n "2026-11-13\|venue picker width" page-changelogs/replacement-arrangement-changelog.md`
  returns line **3753** (`### 2026-11-12 — venue picker width, week nav width,
  mobile venue sheet`) and line **3768** (`### 2026-11-13 — Selection Summary
  always present + collapse arrow`). The reviewer grepped `^## `, which misses
  `###`.
  The **residual** valid point is accepted: those entries sit at the *bottom*
  while the file's `##` headings are newest-first, so T5 now adds a **new `##`
  entry at the top** and explicitly leaves the 3753–3782 block untouched.

### 🔴 Outstanding

- None known. Awaiting round 2 re-review to confirm.

### Deviation from the skill (recorded)

The skill's default is four separate review batches with a freeze between each.
This lane was reviewed as one batch because the user chose the **light lane**
shape ("I want the light SDD lane"), which is itself the logged waiver for that
process step. `specs/` is omitted for the same reason; the skill states specs are
optional for simple changes.

### Round 2 re-review — verdict and fixes

**R2 closed. Five of six 🟡 closed. Y4 was a reviewer error** — its round-1 grep
returned a *truncated* 40-result set of `##` headings and it concluded "no entry"
from that, not from a `###`/`##` subtlety. Both entries exist (3753, 3768).

The reviewer was **right about R1 and I was wrong**: `git diff <file> > patch`
diffs against the index, so it captures *every* uncommitted change in the file —
the pre-existing `+423/−67`, this lane's edits, and any `cancelled-slot-reuse`
hunk. `git apply -R` on it would revert all of it. My "hunk-scoped patch" was
still whole-file data loss with an extra step. Rejected and replaced.

## all (light lane) Round 3 — 2026-11-13 · fixes applied

### 🔴 Fixed

- **R1 — the patch recipe is deleted; rollback is a region restore.**
  `proposal.md` §Rollback summary now explains *why each git mechanism fails*
  (`checkout`/`restore`/`stash`/`clean` discard pre-existing work;
  `git diff > patch` + `apply -R` reverts everything; `git diff -L` diffs
  against HEAD and would delete the venue-sheet CSS; `cp` whole-file restore
  drops anything another lane wrote in between) and states the only working
  mechanism: paste back the **two regions** saved in T1 — the 7-line
  `.arrangement-mobile-venue-group` rule and the whole
  `renderArrangementVenuePicker` function — after re-reading them to confirm the
  other lane has not edited them.
  `patch -R` with ≥3 lines of context is allowed as an automation of the same
  operation, because it fails loudly on a context mismatch.
  `git diff` is now explicitly **read-only** (T6 uses it to confirm the only
  hunks are this lane's two regions).
  The apply gate is now **absolute** in both `proposal.md` and `tasks.md`: wait
  for B02+B03, no "apply alongside" branch (`design.md` §13 step 1 updated).

### 🟡 Addressed

- **N1** — `proposal.md` §3.2 and §4.6 rewritten to match `design.md`
  §7.2/§12.6: rule 2 matters only for multi-group searches, the `/\d/`
  first-digit behaviour is recorded, and the "B010 is on Floor 1 … without this
  rule that test fails" and "search B100 …" claims are gone. The three artifacts
  no longer specify different tests for the same behaviour.
- **N2** — the protected changelog block is corrected to **3753–3786** (the file
  ends at 3786; 3784–3786 is the last paragraph of the 2026-11-13 Selection
  Summary entry) in `proposal.md`, `design.md` §13 step 5 and `tasks.md` T5.
  Verified: `wc -l` → 3786.
- **N3** — T4 and `design.md` §12.6 now name the term (`tutorial`, via
  `humanizeType` → `'Tutorial Room'`), assert `groupCount > 1` **first**, and
  assert **every** matching group is expanded. The count guard is the point: it
  converts data drift into a loud failure instead of a vacuous pass — the exact
  hole that hid rule 2 in the first place.
- **N4** — the "accept concurrent apply" branch is removed (gate is absolute),
  and T6 now checks `ss -ltnp | grep 8030` and confirms with the user before
  restarting, because `cancelled-slot-reuse` B03 runs its arrangement regressions
  on that same disposable port.
- Reduced-motion references corrected to **1811–1827** in `design.md` §10 and
  `tasks.md` T2. Measured: 1827 closes the `prefers-reduced-motion` block, 1828
  closes the `@media (max-width: 768px)` block opened at 918, 1829 is blank. So
  1811–1827 is the rule block and is the right citation.
- T7 now records that `tests/venue-dropdown-responsive.spec.ts` stays
  **untracked**, so it must not be staged.

### 🔴 Outstanding

- None known. Awaiting round 3 confirmation.

### Round 3 re-review — verdict

**R1 mechanism verified correct; N1–N4 closed; Y4 remains a reviewer error.**
One blocker survived: **R3**.

- **R3 — the rollback oracle was captured before the gate.** `tasks.md` gated only
  "T2", so T1 (which saves the rollback reference and the green baseline) would
  run *before* `cancelled-slot-reuse` B02 lands. B02 edits
  `renderArrangementVenuePicker`'s neighbourhood, so the saved text would be the
  **pre-B02** function and a rollback would paste it back over B02's work — R1's
  failure class reintroduced through ordering rather than through a git command.
  Two further consequences: T1's baseline would be pre-B02 (so its "stop and
  report" rule could not separate a pre-existing failure from one B02
  introduces), and T1 restarted `:8030` without the port check T6 has, which
  could kill B03's regression run. `design.md` also carried **no gate at all**.
- **N5** rollback oracle said "the 7-line rule"; measured it is **8 lines**
  (1257–1264, closing brace included), and T2 replaces one rule with **five**, so
  "paste it back" alone would leave four orphaned rules.
- **N6** `design.md` §7.2 still prescribed the weaker test ("assert a non-first
  group"), contradicting §12.6/T4/proposal §4.
- **N7** "hunk-scoped" survived in `proposal.md` and `tasks.md`, the latter citing
  a section that says "region restore only".

## all (light lane) Round 4 — 2026-11-13 · fixes applied

### 🔴 Fixed

- **R3 — the gate now covers T1, and `design.md` has one.**
  - `tasks.md`: "start **T1** only once B02 and B03 are both ticked", plus a
    paragraph explaining *why* T1 and not T2 — T1 captures the rollback oracle and
    the baseline, so capturing them before B02 makes the oracle stale.
  - `design.md` §13 gained **step 0 — the gate**, so the apply-order document a
    fresh implementer follows no longer says to start immediately.
  - `tasks.md` T1 gained the same `ss -ltnp | grep 8030` port check T6 has, with
    the B03-contention reason.

### 🟡 Addressed

- **N5** — the oracle is now precise: `region-css.txt` = the rule **from the
  selector through its closing brace, 1257–1264 (8 lines)**, and the revert is
  defined as "**remove everything T2 added** (five rules: bar, `-label`,
  `-chevron`, the collapsed rotation, `-items[hidden]`), **then** paste the
  original rule back" — so no orphaned CSS is left behind.
- **N6** — `design.md` §7.2 no longer prescribes its own test; it points at
  §12.6 with the `tutorial` term, the `groupCount > 1` guard and the
  "every matching group" assertion. The three artifacts now specify one test.
- **N7** — both "hunk-scoped" occurrences replaced with "region restore only".
- `tasks.md` T6 also diffs the **changelog** now, so "one new entry, 3753–3786
  untouched" is verified rather than asserted.
- Audit-trail wording corrected: 1827 closes the reduced-motion block, 1828
  closes the 768 media query, 1829 is blank.

**Survivor check** after these edits (`proposal.md`, `design.md`, `tasks.md`):
`3782` 0 · `1829` 0 · `hunk-scoped` 0 · `7-line` 0 · `accept concurrent` 0.
The remaining `apply -R` (proposal) and `apply alongside` (proposal, tasks) are
**negative** uses — the text that says those things do not work.

### 🔴 Outstanding

- None known. Awaiting round 4 confirmation.

## all (light lane) Round 4 — freeze review — 2026-11-13

**Method.** Read all three artifacts end to end, walked the rollback chain as a
fresh implementer, and re-grepped every old value across the four files.

### 🔴 Fixed
- **R3 — the gate now covers T1.** `tasks.md` gates T1 (not T2) with the reasoning
  inline; the oracle is captured after the gate; `design.md` §13 gained
  **Step 0 — the gate**; T1 gained the `:8030` port check. Because the gate
  precedes T1, the saved `renderArrangementVenuePicker` is the **post-B02**
  function, so a wholesale restore puts B02's text back — the round-3 hole is
  closed rather than papered over.

### 🟡 Addressed
- **N5** — `region-css.txt` = selector through closing brace, **1257–1264
  (8 lines)**; revert = remove T2's additions **then** paste the rule back.
  Measured against blade 1257–1264 ✓.
- **N6** — design §7.2 now points at §12.6 and no longer prescribes its own
  weaker test; the sole surviving "non-first" is the unrelated UX parity note.
- **N7** — "hunk-scoped" → "region restore only" in both places; 0 survivors.
- T6 now diffs the changelog too; the 1827/1828/1829 note is factually correct.

### 🔴 Outstanding
- None.

### Survivor audit (three artifacts; review-log history excluded by design)
`hunk-scoped` 0 · `3782` 0 · `1829` 0 · `7-line` 0 · `accept concurrent` 0 ·
`start T2` 0. Remaining `apply -R` and `apply alongside` occurrences are explicit
negations ("does not work either" / "There is no … option") and should stay.

### Post-freeze declarative fixes (soft freeze — no decision change)
- `proposal.md` §Rollback: "one rule with five" replaced by "delete the whole CSS
  block T2 inserted — do not count them". Counting is what leaves one behind.
- `design.md` §13 step 2: `~1257–1263` → `~1257–1264 (through its closing brace)`,
  matching the oracle.
- `tasks.md` T4: added the `tutorial` → `b` fallback and "do not delete the guard
  to make the test pass", so a guard firing on real data reads as the guard
  working rather than as a spec defect.

### Verdict round 4
**PASS.** Frozen: `proposal.md`, `design.md`, `tasks.md`.

Apply may start only after `cancelled-slot-reuse` **B02 and B03 are both ticked**
(`sdd.yaml` → `status: ready-to-apply`, `implementation_approved: false`).
