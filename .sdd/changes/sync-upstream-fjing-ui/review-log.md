# Review Log — sync-upstream-fjing-ui

## Batch 1 Round 1 — 2026-10-06 18:45

**Reviewed:** `proposal.md` (new)
**Baseline:** `explore-brief.md`
**Frozen artifacts:** none — first batch
**Verdict: ❌ FAIL — 2 🔴 Outstanding**

### 🔴 Fixed

- None — Round 1, no prior round to fix from.

### 🟡 Addressed

- None — Round 1.

### 🔴 Outstanding

- **[B1-1] An open question from the brief is carried nowhere.** `explore-brief.md` §10 lists two
  open questions. The `pkill` one was correctly promoted into the proposal's risk table. The
  second — *"whether `auth-wiring` (the one stale UI change upstream did* not *archive) should be
  archived alongside the other 6 after the merge, or kept active"* — appears **nowhere** in
  `proposal.md` (verified by grep). The skill designates the explore brief as "the completeness
  checklist during proposal review", so dropping a known open question without resolving it *or*
  explicitly deferring it leaves the implementer with no instruction. This is decision-relevant:
  post-merge, `.sdd/changes/` will contain 1 archived-by-upstream set plus `auth-wiring` and
  `wire-backend-into-refactored-ui`, and nothing says what to do with the first.
  **Fix:** either resolve it, or move it to an explicit "Deferred — Wave 3 input" line so the
  omission is deliberate and recorded.

- **[B1-2] The load-bearing mitigation is stated as a static fact but the risk is temporal, and
  there is no contingency.** The risk *"Merge rewrites the working tree while the other session
  writes into it"* is answered with *"Provably zero overlap: upstream diff has 0 `database/` and
  0 `dataset/` paths; our 12 conflict files are disjoint from their artifacts."* That claim was
  computed at analysis time (18:3x) against a snapshot of a session whose last write was 17:17.
  Nothing in the proposal re-validates it at execution time, and nothing states what happens if
  the premise fails. Concretely: `git merge` **aborts** if the working tree is dirty in a path the
  merge must update, and the proposal defines no behaviour for that case (abort-and-report?
  wait? stash?). Since this mitigation is the entire justification for running the merge
  concurrently with a second session, it must be a check performed immediately before the merge,
  not a number recorded in a document. **Fix:** add a pre-merge gate — assert none of the
  incoming 114 paths is dirty or untracked, and specify the abort-and-report contingency.

### 🟡 Advisory (non-blocking — fix opportunistically, does not hold the freeze)

- **[B1-3] Success criterion 7 is not crisply testable.** *"Working tree contains no files
  outside this change's declared surface"* — "declared surface" is never enumerated as a path
  list anywhere in the proposal. Replace with an enumerable assertion, e.g. *"`git status
  --porcelain` post-merge lists only the 7 known foreign untracked paths plus this change's own
  artifacts."*

- **[B1-4] Scope precision around `database/`.** Out-of-scope reads *"`database/migrations/*` or
  `dataset/*`"* while in-scope extracts `ReplacementRequestsSeeder.php` → `database/seeders/`.
  The wording is technically correct (migrations ≠ seeders) but easy to misread as a blanket
  `database/` exclusion. Worth one clause naming `database/seeders/` as explicitly in-scope.

- **[B1-5] The backup tag is not referenced.** `backup/pre-fjing-merge` → `438bdfe` already exists
  but the proposal never mentions it. It is the rollback mechanism for this change; listing it
  under success criteria or preparation makes the recovery path discoverable.

### Evidence verified this round

These were checked against the live API rather than taken from the prose, and they **corroborate**
the proposal:

| Check | Result | Implication |
|---|---|---|
| Upstream diff `f44cc5c...36c4d2c` total | **114 files** (49 added, 37 modified, 3 removed, 25 renamed), not truncated | matches brief §6 |
| PHPUnit `*Test.php` touched by upstream | **0** | success criterion 5 (`105/105`) is a legitimate post-merge target, not an unachievable one |
| Any path under `tests/` touched | **0** | extracting `tests/e2e/` is conflict-free |
| `database/seeders/*` touched | **0** | extracting `ReplacementRequestsSeeder.php` is conflict-free |
| `database/` and `dataset/` paths | **0 and 0** | supports [B1-2]'s premise — but see the re-validation requirement above |
| `auth-wiring` in upstream's archive commits | **absent** | confirms the brief's §10 second open question is real, sharpening [B1-1] |
| Merge geometry | merge-base `f44cc5c`, 26/71, `f44cc5c` still an ancestor of HEAD | 3-way merge well-formed, fast-forward geometry intact |

**Next:** fix [B1-1] and [B1-2] in `proposal.md` only, then re-submit for Round 2. Advisory
[B1-3]–[B1-5] may be folded into the same edit. `design.md` must not be created until this batch
passes.

---

## Batch 1 Round 2 — 2026-10-06 18:52

**Reviewed:** `proposal.md` (post–Round 1 fixes)
**Baseline:** `explore-brief.md` **+ `AGENTS.md`** (surfaced as an explicit instruction block this round; it is auto-loaded every session and was not in the Round 1 context)
**Verdict: ❌ FAIL — 1 🔴 Outstanding**

### 🔴 Fixed

- **[B1-1] RESOLVED.** New `## Deferred` section (lines 52–60) carries the `auth-wiring` question
  explicitly, parks it as Wave 3 input alongside the rename-unfreeze debt, and — correctly —
  **reframes** it: 58 lines were added to `.sdd/changes/auth-wiring/tasks.md` since the merge
  base, so it is active local work, not a discardable leftover. The correction is stated as a
  correction rather than silently overwriting the brief, which keeps the brief auditable.
  Verifiable by grep: `auth-wiring` now appears in `proposal.md`.
- **[B1-2] RESOLVED.** The mitigation is now an executable gate rather than a claim: write the
  incoming 114-path set to a file, intersect with `git status --porcelain`, **abort and report**
  on non-empty, explicitly forbidden to `stash`/`rm`/force. It re-validates at execution time
  rather than at analysis time, and the dry-run result is recorded. Success criterion 7 now
  makes the gate a pass condition, so it cannot be skipped. Independently reproduced this round:
  **0 collisions across 10 local paths vs 114 incoming.**

### 🟡 Addressed

- **[B1-3] RESOLVED** — criterion 7 is now a measurable intersection; criterion 8 enumerates the
  exact expected `git status` output (8 foreign paths named individually, plus this change's own
  directory).
- **[B1-4] RESOLVED** — `database/seeders/` named as an explicitly in-scope extraction
  destination, with the out-of-scope line clarified so `database/migrations/*` reads as the
  narrower exclusion it is.
- **[B1-5] RESOLVED** — `backup/pre-fjing-merge` → `438bdfe` now appears under *In scope →
  Preparation*, making the rollback path discoverable.
- Bonus: *In scope* now records that upstream touches **0** seeders and **0** `tests/` paths, and
  *Out of scope* correctly takes theirs for `prompts/run-auth-wiring.md`.

### 🔴 Outstanding

- **[B1-6] The `pkill` mitigation silently closes an open question the brief marked
  "not yet confirmed by the user", and it contradicts an auto-loaded standing rule.**
  Two independent defects in one cell of the risk table:

  1. **Unconfirmed decision presented as settled.** `explore-brief.md` §10 lists the process-restart
     method as *"Not yet confirmed by the user."* The proposal's risk table states it flatly as
     *"Use `pkill -f 'artisan serve'`, never `pkill -9 php`"* with no flag that it is a proposal
     awaiting sign-off. This is structurally the same defect as [B1-1]: an open question resolved
     without recording that a decision was taken.
  2. **Direct conflict with `AGENTS.md`.** Line 37 is a standing rule applying to *all* tasks:
     > `pkill -9 php && rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`

     An implementer following the repo's own auto-loaded instructions runs `pkill -9 php`. The
     proposal forbids it. Two authoritative documents give contradictory commands for an
     in-scope step with no stated precedence.

  **Severity is real, not theoretical.** Verified live at review time: **six `php-fpm` processes
  are running — PIDs 960 (master), 974, 975, 976, 977, 978.** `pkill` matches an unanchored
  regex against the process name, so the pattern `php` matches `php-fpm`. The documented command
  would SIGKILL the FPM pool, taking down whatever serves PHP-FPM on this machine — while the
  artisan server (PIDs 47187, 47192) is only two of the eight victims.

  **Fix required:** either (a) obtain the user's explicit confirmation and record it, adding a
  line noting this **deviates from `AGENTS.md:37`** and that AGENTS.md should be amended in a
  follow-up; or (b) if confirmation is not obtained, state the conflict and the precedence rule
  so the implementer knows which document wins. Do not freeze with the ambiguity in place.

### 🟡 Advisory (non-blocking)

- **[B1-7]** Success criterion 5 says *"PHPStan (0 errors)"* but `AGENTS.md:35` mandates
  `composer run lint:check` + `composer run types:check`. Plain `types:check` crashes at the
  default 128M memory limit; it requires `--memory-limit=1G`. Record the exact working command
  so an implementer who hits `Allowed memory size exhausted` does not misread it as a regression
  introduced by the merge.
- **[B1-8]** Batch 2 prerequisite: `AGENTS.md` requires `CodingMAIN.md` be read **in full before
  any design work**. `design.md` must not be written until that read has actually happened in
  this session.

### Assessment

Round 1's substantive problems are genuinely fixed, not papered over — both blockers were
resolved with verifiable artifacts rather than reworded prose, and three advisories were folded
in unprompted. [B1-6] is new: it emerges from `AGENTS.md` being in scope this round, not from a
weakened standard. It is a narrow, one-cell fix.

**Next:** resolve [B1-6] in `proposal.md` (fold in [B1-7] while editing), then Round 3.
Round count: 2 of 5.
