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

---

## Batch 1 Round 3 — 2026-10-06 18:55

**Reviewed:** `proposal.md` (post–Round 2 fixes)
**Baseline:** `explore-brief.md` + `AGENTS.md`
**Verdict: ✅ PASS — Batch 1 FROZEN**

### 🔴 Fixed

- **[B1-6] RESOLVED.** The `pkill` cell now does all three things required:
  1. records the decision as taken — *"explicitly confirmed by the user 2026-10-06 (closes
     explore-brief §10)"* — so an open question is no longer presented as silently settled;
  2. names the conflict explicitly — *"Deviates from `AGENTS.md:37`"* — and states precedence:
     *"For this change the proposal takes precedence over `AGENTS.md`"*, so an implementer
     following the auto-loaded standing rule now has a documented resolution instead of two
     contradictory instructions;
  3. quarantines the debt — *"must be amended in a **follow-up change**, not this one"* — so the
     dangerous line at `AGENTS.md:37` is neither silently propagated nor smuggled into this
     change's scope.

  The full safe restart sequence is spelled out, and `explore-brief.md` §10 was updated to
  `~~strikethrough~~ RESOLVED 2026-10-06`, so the checklist no longer dangles an open item.

### 🟡 Addressed

- **[B1-7] RESOLVED.** Success criterion 5 now names the exact commands —
  `composer run lint:check`, `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`,
  `php vendor/phpunit/phpunit/phpunit --no-coverage` — and explicitly warns that plain
  `composer run types:check` (`AGENTS.md:35`) exhausts the default 128M, so an implementer who
  hits `Allowed memory size exhausted` will not misread it as a merge regression.
- **[B1-8]** is a Batch 2 prerequisite rather than a `proposal.md` defect; it is recorded here
  and must be honored before `design.md` is written.

### 🔴 Outstanding

- **None.** Both Round 1 blockers and the Round 2 blocker are resolved with verifiable,
  greppable artifacts. Empty section = freeze condition met.

### 🟡 Advisory (carried forward — non-blocking, may be folded into `design.md` / `tasks.md`)

- **[A1] Gate count vs criterion count.** `proposal.md` *What* item 4 commits to *"the 5
  verification gates"*, defined in `explore-brief.md` §9. Success criterion 5 covers gates 1–3,
  criterion 1 covers gate 4, but **gate 5 (`/sdd-verify` → `/sdd-archive`) appears in no success
  criterion.** The commitment exists in the same document, so it is discoverable — but an
  implementer treating the criteria table as the checklist would stop one step short. Suggest
  adding a criterion 9 for the SDD verify/archive step.
- **[A2] No mid-merge abort procedure.** The pre-merge gate specifies abort-and-report *before*
  merging, and `backup/pre-fjing-merge` is recorded as preparation, but nothing says what to do
  when conflicts turn out to be unresolvable *during* the merge. `git merge --abort` (and, if
  needed, reset to the tag) should be named in `tasks.md` as the rollback path.
- **[A3] Criterion 6 wording.** *"HEAD's `playwright.config.ts` unchanged"* — temporally
  ambiguous (HEAD before vs after the merge). Should read "the `playwright.config.ts` present at
  `438bdfe` is retained; the `fedora-frontend` copy is discarded."

### Assessment

Three rounds, two substantive blocker sets, all closed with evidence rather than rewording.
The proposal now carries: a pinned 12-file conflict surface, an executable pre-merge gate with
an abort rule, an explicitly confirmed operational decision with its AGENTS.md conflict
recorded, exact gate commands, a deferred-question section, and an enumerated post-merge
working-tree assertion.

**`proposal.md` is FROZEN as of 2026-10-06 18:55.** Subsequent reviews treat it as baseline;
only declarative changes (mapping tables, examples, typos) are permitted from here.

**Next → Batch 2: `design.md`.** Prerequisite [B1-8]: read `CodingMAIN.md` in full first, per
`AGENTS.md`. Advisories [A1]–[A3] may be folded in.
Round count: 3 of 5. Batch 1 of 4 complete.

---

## ⚠️ Post-freeze discovery — UNFREEZE of `proposal.md` + `explore-brief.md` §5 — 2026-10-06 19:0x

**Not a review round** (no reviewer call produced this; it emerged while preparing Batch 2).
Logged for audit integrity: the author discovered it by running an empirical test that Rounds 1–3
should have run.

**Trigger:** while drafting `design.md`, the author ran a read-only trial merge to get ground
truth rather than relying on the predicted conflict set:

```
git merge-tree --write-tree --name-only HEAD upstream/fjing
→ exit 1, 6 CONFLICT lines
```

**Finding: the "exactly 12 conflicting files" figure carried through proposal *What* item 1,
In-scope, success criterion 2, and `explore-brief.md` §5 is wrong.** The 12 was a
*changed-file intersection* — a deliberate over-approximation whose method was sound but whose
output was mislabeled as conflicts. Actual conflicts: **6.**

| Real conflict (6) | Type |
|---|---|
| `CodingMAIN.md` | content |
| `routes/web.php` | content |
| `page-changelogs/my-request-history-changelog.md` | content |
| `page-changelogs/replacement-home-changelog.md` | content |
| `page-changelogs/request-approval-changelog.md` | content |
| `page-changelogs/upcoming-replacements-ui-changelog.md` | modify/delete — *"Version HEAD … left in tree"*, so `git rm` accepts upstream's deletion |

**Auto-merge cleanly (6) — no manual resolution required:**
`public/js/ui-common.js`, `cohort-timetable-ui-changelog.md`, `my-timetable-changelog.md`,
`replacement-arrangement-changelog.md`, `student-my-timetable-ui-changelog.md`,
`venue-timetable-ui-changelog.md`.

**Why this is decision-level, not declarative:** success criterion 2 requires *"All 3
resolutions in the hard-conflict table"* — and the hard-conflict table's third entry is
`public/js/ui-common.js`, which will **never present a conflict**. The criterion is unsatisfiable
as written, and an implementer hunting for 12 conflicts would likely hand-rewrite files git had
already merged correctly, damaging the shared-helper globals that commit `0a62f1a` depends on.

**What the earlier rounds got right:** the `.sdd/` analysis held exactly (zero conflicts from
upstream's two archive commits, zero add/add), and the `0 database/` + `0 dataset/` premise
underpinning the pre-merge gate is unaffected. Only the conflict *count* and its downstream
criteria were wrong.

**Actions taken:** `proposal.md` unfrozen and corrected (12 → 6, criterion 2 rewritten,
In-scope and *What* item 1 corrected); `explore-brief.md` §5 relabeled with the empirical result
and a correction note. Downstream `design.md`/`specs/`/`tasks.md` do not exist yet, so no
downstream unfreeze is needed. **Batch 1 must be re-reviewed and re-frozen — Round 4.**

---

## Batch 1 Round 4 — 2026-10-06 19:03

**Reviewed:** `proposal.md` + `explore-brief.md` (both modified post-freeze; re-review after unfreeze)
**Baseline:** `explore-brief.md` §5 correction + `AGENTS.md` + live git state
**Verdict: ❌ FAIL — 1 🔴 Outstanding** (Round 4 of 5)

### 🔴 Fixed

- **12 → 6 correction verified sound.** `What` item 1, *In scope*, and `explore-brief.md` §5 +
  §5.1 all now state 6 real conflicts, cite `git merge-tree` as the source, and carry an explicit
  correction block rather than quietly overwriting the original claim. §5.1's third row
  (`public/js/ui-common.js`) is correctly downgraded to *"verify only — do not hand-rewrite"*,
  which was the operationally dangerous part of the error.
- **Stale backup tag surfaced and scoped.** `backup/pre-fjing-merge` → `438bdfe` is now flagged as
  destructive against the concurrent session's 4 commits, with the required remedy (fresh tag at
  merge time). This would have been a genuinely painful surprise at rollback time.
- **Premise decay documented honestly.** The risk cell now states the
  "they-only-own-`database/`+`dataset/`" rationale is **no longer true** and that the gate — not
  that rationale — is the safety mechanism. Correcting one's own weakened argument is the right
  instinct; leaving it would have been the more tempting edit.
- **Criterion 8 rewritten** from an unsatisfiable enumeration to a no-regression assertion, with
  the supersession reason inline.

### 🟡 Addressed

- Fresh gate evidence recorded: **0 collisions, 4 local paths vs 114 incoming.**
- `page-changelogs/todo list/todo-list.md` correctly identified as the one **both-modified**
  auto-merge and named explicitly in criterion 2.

### 🔴 Outstanding

- **[B1-9] The artifact now contradicts itself on a count — and the smaller number omits the
  highest-risk file.** The auto-merged verification set is stated three different ways:

  | Location | Says | Implies |
  |---|---|---|
  | *What* item 1 (line 25) | *"verifying the **6** paths that auto-merge silently"* | 6 |
  | *In scope* (line 42) | *"verification of the **6** that auto-merge — `public/js/ui-common.js` and 5 page-changelogs"* | 1 + 5 = **6** |
  | Success criterion 2 (line 74) | *"verify the **7 auto-merged paths**"* | **7** |

  Ground truth from `merge-tree` is **7**: `ui-common.js`, 5 page-changelogs, and
  `page-changelogs/todo list/todo-list.md`.

  The discrepancy is not a typo — it is a **partial correction**. The concurrent session's
  `633eeb3` introduced `todo-list.md` as a new both-modified path *after* the original 12-path
  analysis; criterion 2 was updated to include it, but *What* and *In scope* were not. An
  implementer working from the In-scope work list verifies 6, skips `todo-list.md`, and then
  fails criterion 2 — and `todo-list.md` is precisely the file most likely to be silently wrong,
  being the only one both sides modified without producing a conflict.

  **Fix:** make all three locations say 7, and enumerate the set once so it cannot drift again
  (e.g. *"the 7 auto-merged paths listed in `explore-brief.md` §5"*).

### 🟡 Advisory (non-blocking — carry to Batch 2/4)

- **[A1]** still open from Round 3: *What* item 4 commits to *"the 5 verification gates"*
  (`explore-brief.md` §9), but gate 5 — `/sdd-verify` → `/sdd-archive` — appears in **no** success
  criterion. 5 gates vs 8 criteria, one gate unreferenced.
- **[A3]** still open: criterion 6's *"HEAD's `playwright.config.ts`"* remains temporally
  ambiguous.
- **[A4] NEW:** the pkill debt register names only `AGENTS.md:37`. **`CodingMAIN.md:108`
  contains the identical `pkill -9 php` command** (author's `CodingMAIN.md` read, B1-8). The
  follow-up amendment must cover **both** files, or the dangerous line survives in the project's
  own single-source-of-truth document.
- **[A5] NEW:** *Out of scope* still justifies excluding `database/migrations/*` + `dataset/*` with
  *"another session owns those"* — they have since been **committed** by that session. The
  boundary remains correct; only the rationale is stale.
- **[A6] NEW:** the concurrent session's 4 commits are **unpushed** (`origin` sits at `438bdfe`).
  The recommended fresh-tag rollback keeps them locally, but they have no remote copy — worth
  considering a push as hardening.

### Assessment

The unfreeze was handled correctly: discovery logged as its own non-round entry rather than
buried in a passing round, root cause named (intersection ≠ conflicts), the operationally
dangerous half of the error (`ui-common.js`) specifically neutralized, and two *additional*
latent problems (stale rollback tag, decayed safety premise) surfaced in the same pass.

The single remaining 🔴 is an incomplete correction, not a flawed one — the new fact was applied
to the pass/fail criterion but not to the two prose lists that describe the same work.

**Next:** fix [B1-9] in `proposal.md` only (optionally fold in [A1]/[A3]/[A4]), then Round 5.
**Round 5 is the cap** — if it does not pass, stop and present options rather than loop.

---

## Batch 1 Round 5 — 2026-10-06 19:09 (CAP ROUND)

**Reviewed:** `proposal.md` + `explore-brief.md`
**Verdict: ✅ PASS — Batch 1 RE-FROZEN**

### 🔴 Fixed

- **[B1-9] RESOLVED.** All three locations now agree on **7**, and the count is structurally
  prevented from drifting again:
  - *What* item 1 → *"verifying the 7 paths that auto-merge silently (enumerated in
    `explore-brief.md` §5 — one list, one number, no local copy to drift)"*
  - *In scope* → defers to brief §5, count deliberately not carried as an independent fact
  - success criterion 2 → *"verify the 7 auto-merged paths"*
  - `explore-brief.md` §5 now holds the **canonical enumerated list of 7**, explicitly labeled
    *"This is the canonical list… do not restate the count elsewhere"*, with an inline warning
    that `todo list/todo-list.md` is the only both-modified path, the only one outside the
    original 12-path analysis, and the one git merges silently.

### 🟡 Addressed

- **[A1] closed** — new success criterion 9 (`/sdd-verify` + `/sdd-archive`); the 5-gates-vs-8-
  criteria gap is gone (table now has 9 rows).
- **[A3] closed** — criterion 6 rewritten as *"our fork's `playwright.config.ts` is retained and
  the `fedora-frontend` copy is discarded"*; no SHA, no temporal ambiguity.
- **[A4] closed** — pkill debt now names **both** `AGENTS.md:37` and `CodingMAIN.md:108`, with
  the reasoning spelled out: fixing only one leaves the dangerous line in the project's
  single-source-of-truth document.
- **[A5] closed** — out-of-scope rationale updated: those paths were **committed** by the
  concurrent session, not merely owned.
- **[A6] resolved by the concurrent session** — verified this round: `origin/fedora-backend` =
  `9d87a79` = HEAD, `git log origin/fedora-backend..HEAD` empty. Nothing unpushed.

### 🔴 Outstanding

- **None.** Every decision-level blocker raised across the batch (B1-1, B1-2, B1-6, B1-9) is
  closed with a verifiable artifact.

### 🟡 Declarative fixes (apply post-freeze under the soft-freeze rule — none change behavior)

- **[D1]** The pkill risk cell spans physical lines 94→97. GFM table rows terminate at a newline,
  so the row breaks and lines 95–97 render as loose paragraph text — i.e. the **restart-sequence
  instruction may not render inside the table**. Content is correct; join to one line.
  Introduced by the author's own Round 4 edit.
- **[D2]** *In scope* reads *"**verification of the 7** that auto-merge — … (the count here is
  deliberately* not *restated, to prevent drift)"* — it restates the count and then claims not to.
  Harmless (7 is correct and consistent) but self-contradictory; drop the parenthetical.
- **[D3]** *Why* still calls `438bdfe` our "tip"; HEAD is now `9d87a79`. Narrative only — the
  operative sections (In-scope, Risks) already carry the correct SHA.

### New environmental facts recorded this round

- **A third remote `local` → `/home/jinglinux/CRS-fedora-local.git`** was added by the concurrent
  session, and branch tracking now points at `local/fedora-backend`. **A bare `git push` would
  go to the local mirror, not GitHub** — push explicitly as `git push origin fedora-backend`.
- Concurrent session pushed everything; `origin` and `local` both at `9d87a79`.

### Assessment

Five rounds, four substantive blockers, one mid-flight unfreeze. The unfreeze was the pivotal
event: the original conflict set was an over-approximation presented as fact, and catching it
converted a wrong-but-confident plan into a correct one with a documented error trail. Two
further latent problems it exposed — the stale rollback tag (would have destroyed 4 commits) and
the decayed safety premise — were arguably worth more than the count correction itself.

**`proposal.md` and `explore-brief.md` are FROZEN as of 2026-10-06 19:09.** Apply [D1]–[D3] as
declarative soft-freeze edits.

**Next → Batch 2: `design.md`.** Prerequisite [B1-8] satisfied — `CodingMAIN.md` read in full
this session. Advisories to carry: none outstanding.

---

## Batch 2 Round 1 — 2026-10-06 19:1x

**Reviewed:** `design.md` (new)
**Baseline:** frozen `proposal.md`, `explore-brief.md` §5, `AGENTS.md`
**Ground truth used:** read-only trial-merge tree `2133c236`; **`../final/FR&NFR.md`** (the FR source of truth, mtime Sep 16 21:35, *outside* both repos); our migration tree; unit tests
**Verdict: ❌ FAIL — 2 🔴 Outstanding** (Batch 2 Round 1 of 5)

### 🔴 OutStanding

- **[B2-1] H9's "restore our FR 4.11 (4-state)" is rejected by three independent sources.**
  `design.md` §4.2 H9 instructs taking upstream's rows then **hand-keeping our** FR 4.11 —
  `Available, Pending (Self), Reserved (Other), Occupied`. Three facts oppose it:

  1. **The FR source of truth says 3 states.** `../final/FR&NFR.md:61` reads exactly upstream's
     wording — *"…the slot state machine… Available, Pending, and Occupied (state-machine method
     in Harel, 1987)"* — and the token `Reserved` appears **nowhere** in the entire FR&NFR file
     (verified by scan).
  2. **The database forbids the 4th state.** `2026_08_03_000006_create_time_slots_table.php:31`
     defines `CHECK (status IN ('available','pending','occupied'))` — `reserved` has never been a
     legal value since the table was created.
  3. **No code uses it.** `grep -rn "'reserved'" app/ database/migrations/` → zero hits.

  If applied, the merge would document an **impossible state** and drive a false
  FR↔design contradiction. FR 4.11 must take **theirs (3-state)**, wholesale — reversing the
  very correction the design narrative presents as evidence-based. The instinct (protect OCC's
  FCFS semantics) was right; the standard I applied (our §3 state machine) was not the FR.

- **[B2-2] H9's "restore our FR 4.7 (supersession note)" is also rejected.** The real FR&NFR
  row 4.7 carries **no** supersession note — ours appended *(Supersedes MPU-3133-specific
  wording — see §3 venue rules.)*, an editorial gloss. §3's own line 62 already records the full
  ruling and is **outside every hunk**, so it survives the merge untouched either way. Two
  further converging signs: upstream's §7 header says *"verbatim; reader-facing glosses and
  citations trimmed"*, and our side's rows **trim** citations that the real file carries
  (FR 4.8/4.11/4.12ours drop *(Kung & Robinson, 1981)*/*(Harel, 1987)*) — i.e. our §7.2 diverged;
  upstream's is the faithful sync. **H9 must take theirs wholesale — all 13 rows, no restores.**
  The design's "restore 2 rows" table must be deleted.

### 🟡 Discovery that must be registered (not a design error — a latent contradiction the merge will surface)

- **[B2-3] `CodingMAIN.md` §3's own Slot State Machine still lists `reserved`.** With H9 taking
  upstream's 3-state FR 4.11, the merged file will claim **4 states in §3 and 3 in §7.2** — plus
  the DB CHECK permits only 3. Since §3 sits outside every hunk, this merge cannot and should not
  fix it; it must be recorded in §10's downstream-impact list as follow-up debt — *including a
  note that Phase 3/4 code is unaffected because no code references `reserved` at all.*

### 🟡 Confirmed-correct with strengthened evidence

- **[B2-4] H4 (ours) is now provably right, not stylistically right.** Real FR&NFR rows 1.3 and
  1.4 both read *"…for their cohort"*, which upstream's `View global replacement history ledger |
  ✅ | ✅ | ✅` directly violates — *student* × global ledger. Also verified: upstream's
  `replacement-history-UI-design-template.blade.php` reads from `window.MockData` (5 references),
  so it's a阶段 mock and its matrix row is aspiration, not enforced behaviour. Our override needs
  the FR citations added to the design's justification so Round 2 doesn't have to re-litigate it.

### 🟡 Advisory (non-blocking)

- **[B2-5] §9's rollback reasoning contradicts itself.** *"Losing them is irreversible — they're
  pushed to `origin` actually, so restoring is possible"* — the second clause negates the first.
  Reword: the real reasons for the fresh tag are convenience (a single reset point) and never
  touching `438bdfe`-era refs; recoverability is fact (push verified at Round 5, `origin ==
  9d87a79`).
- **[B2-6] §8's criterion-2 check for H4 is inverted phrasing.** `grep -c 'View global
  replacement history ledger'` is described as *"≥ 1 forbidden (must be 0)"* — flip to
  unequivocally `== 0` with a positive assertion instead.
- **[B2-7] §5 lacks a post-merge route parity check.** The `$uiPages` loop won't merge upstream's
  explicit-route side, so nothing verifies the union of both sides' UI routes survived. Add:
  after the merge, diff our 9 post-edit keys against upstream's explicit route list (5 lines
  inside H9's web.php hunk + routes outside it).
- **[B2-8] §7 (extraction) — record the playwright fact.** `playwright.config.ts` is
  **absent from `origin/fedora-frontend`** (verified `git ls-tree`), so criterion 6's "ours
  retained, theirs discarded" is trivially satisfied — worth stating so nobody goes looking for a
  config to reject.
- **[B2-9] §8's FR-row count claim ("48 FRs / 26 NFRs") is unaudited.** A quick machine count of
  the real FR&NFR starts before it ends up in a verification step; either verify at merge time or
  drop the number.

### Assessment

The three decisions the user asked pointed at were each independently settled: **H4 ours (right,
now with FR citations)**, **H9 ours-overrides (wrong — overturned by the FR source, a DB
constraint, and the codebase)**, and H14's UNION (reasonable, minor wording issues).

The design's method — pull hunk ground truth from `merge-tree` instead of inferring it — was
correct; this round's failures were in *what standard* a row should be judged against: our §3
consistency rather than the FR source itself. The distinction matters because our §3 is itself
inconsistent with the database and with no code using `reserved`.

**Next:** fix [B2-1] + [B2-2] in `design.md` §4.2 H9; register [B2-3] in §10; apply [B2-5]–[B2-9]
while editing; then Round 2. Round count: Batch 2, 1 of 5.

---

## Batch 2 Round 2 — 2026-10-06

**Reviewed:** `design.md` (post–Round 1 fixes)
**Baseline:** frozen `proposal.md`, `explore-brief.md` §5, `AGENTS.md`, `../final/FR&NFR.md`
**Independent re-verification this round:** FR&NFR.md full 110-line audit; migration :31 read
directly; case-insensitive `reserved` sweeps (FR source / app/ / database/); `web.php` $uiPages
key set; `RouteGateMatrixTest.php` in full; `CodingMAIN.md` `reserved` site sweep.
**Verdict: ❌ FAIL — 2 🔴 Outstanding** (Batch 2 Round 2 of 5)

### 🔴 Fixed

- **[B2-1] CLOSED — sharpest question re-litigated and settled.** All three pillars re-verified
  first-hand: `reserved` has zero case-insensitive matches in `../final/FR&NFR.md` (FR 4.11 =
  line 61, 3-state, Harel 1987); the DB CHECK at `2026_08_03_000006_create_time_slots_table.php:31`
  is pinned to 3 states; `'reserved'` has zero uses in `app/**/*.php` and `database/`. Verdict:
  evidence decisively favors upstream's 3-state + debt registration. The alternative (ours'
  4-state + "fix §3") documents a DB-impossible state, contradicts the FR source, and demands a
  decision-level edit outside every hunk. **H9 stays theirs — do not reverse again.**
- **[B2-2] CLOSED.** Real FR 4.7 (FR source line 57) carries no supersession note (`Supersedes|MPU`
  → 0 hits in the whole file); ours' gloss verified at `CodingMAIN.md:247`; criterion 2's
  `Supersedes MPU-3133-specific == 0` check is now implemented. §3's ruling line 62 is outside
  every hunk and survives either way.
- **[B2-5] CLOSED** — §9 no longer self-contradicts; matches Batch 1 Round 5 push-verify facts.
- **[B2-6] CLOSED** — `== 0` with positive assertion `cohort-scoped, FR 1.3–1.4 ≥ 1`
  (literal verified present at `CodingMAIN.md:168`).
- **[B2-8] CLOSED** — §7 states `playwright.config.ts` absent from `origin/fedora-frontend`.

### 🟡 Addressed

- **[B2-3]** — registered in §10 + §13 with the "code never uses `reserved`" caveat (verified
  true); see [B2-12] for under-scoping.
- **[B2-4]** — H4 now carries FR 1.3/1.4/2.15 citations; re-verified at FR source lines 18/19/40.
- **[B2-7]** — route-parity row added, but broken; escalated to 🔴 [B2-11].
- **[B2-9]** — moved to §13 as debt, but the claim is now auditable and TRUE; see [B2-13].

### 🔴 Outstanding

- **[B2-10] `design.md` H9 contradicts frozen `proposal.md:78` criterion 2** ("CodingMAIN.md
  *keeps our FR 4.7* + Staff-ID rows"). Post-[B2-2] H9 drops ours' gloss, so §8's own checks
  pass while literal criterion 2 fails — and an implementer "satisfying" it would re-create the
  rejected merge outcome. **Fix:** surgical unfreeze of `proposal.md` criterion 2 → "FR 4.7
  takes theirs — supersession gloss deliberately dropped (FR source has none); Staff-ID rows
  unchanged (ours' 2.1 == FR source line 26 verbatim)" + dated correction note. No downstream
  artifacts exist, so the unfreeze is cheap. Decision-level, cannot be soft-frozen in.
- **[B2-11] §5 route-parity gate self-contradicts, expected count provably wrong.** Row says
  "8 keys" then asserts the grep "counts the **7** `-ui` suffixed keys". Verified against
  `routes/web.php`: 8 `$uiPages` keys (lines 31–79, all `/…-ui`), post-edit still 8 — the
  swap is suffixed-for-suffixed. `grep -cE` counts lines → **8**. Also: the
  `replacement-arrangement` alternative in the `-ui`-suffixed pattern can never match (explicit
  route, cf. `RouteGateMatrixTest.php:35`), and the pattern's quote is unbalanced. Deterministic
  false failure at S7 — same class as frozen-batch [B1-9]. **Fix: expected count 8; drop the
  dead alternative; close the quote.**

### 🟡 New (non-blocking, fold into the same edit pass)

- **[B2-12] `reserved` debt under-scoped.** Our HEAD `CodingMAIN.md` carries `reserved` at 6
  sites: :46 (§3 row — the only one registered), :53 (§3 grey legend), **:145 (§4 domain model —
  status-column enum explicitly listing 4 values; the sharpest residual contradiction with the
  DB CHECK the same debt row cites)**, :251 (FR 4.11 — fixed by H9), :398/:414 (§10.0 colour
  legend). Apply the same "outside every hunk" check used for §3 to :145/:53/:398/:414 and
  enumerate all surviving sites in §13 row 1.
- **[B2-13] [B2-9] audits TRUE — stop deferring.** Full numeric audit of `../final/FR&NFR.md`:
  **85 rows = 48 FR (9+16+7+16) + 26 NFR (4+6+6+3+3+1+1+2) + 11 `x.0` headers** — exact match
  to H5's claim. Delete §13's last debt row or convert to a verified note.
- **[B2-14] H4's citation misattributed.** "the same file's line 166" — `../final/FR&NFR.md` is
  110 lines; the FR 1.2 scoping gloss lives in our `CodingMAIN.md:166` (§6 permissions matrix).
  Substance unaffected; re-point the citation.

### 💡 Optional

- §4.1 keyed-edit table drops the leading `/` present in the actual array keys.
- §6 parenthetical "our `$uiPages` array drives test generation" contradicts the test's
  hardcoded constants (`RouteGateMatrixTest.php:29–50`); the :20/:33/:41 literal edits (line
  numbers verified) are the real mechanism. Reword so nobody skips the literals.

### Assessment

Round 1's corrections are genuinely landed and evidence-backed. The one design reversal in it
(H9 → theirs) survives sharp re-litigation with fresh evidence. What remains are (a) the frozen
proposal lagging two decision-level corrections behind the current evidence ([B2-10]) and (b) a
verification gate carrying a provably wrong count ([B2-11]). Both are cheap; the proposal
unfreeze in [B2-10] mirrors the documented 12→6 precedent.

**Next:** apply [B2-10] (proposal unfreeze) + [B2-11] + fold [B2-12]–[B2-14]; then Round 3.
Round count: Batch 2, 2 of 5.

---

## ⚠️ Surgical unfreeze acted on — `proposal.md` criterion 2 — 2026-10-06 (post-Round 2, not a review round)

**Not a review round** (no reviewer call produced this; it is the direct execution of [B2-10]'s
prescribed fix, mirroring the 12→6 unfreeze precedent). Scope of the unfreeze: **criterion 2
only**, one clause. Nothing else in the frozen artifact was touched.

- **Old clause:** *"CodingMAIN.md keeps our FR 4.7 + Staff-ID rows"* — unsatisfiable after H9's
  evidence correction: the supersession gloss is dropped by design, so the criterion would have
  failed literally while §8's own checks passed.
- **New clause:** criterion 2 now defers to `design.md` §4.2's 14-hunk table and names both
  evidence-based overrides explicitly — **H4 ours** (cohort-scoping, FR-verified) and **H9
  theirs-wholesale** (FR 4.7 gloss dropped; FR 4.11 3-state per FR source + DB CHECK) — so
  proposal, design, and §8's grep matrix now say the same thing. Staff-ID rows unchanged (ours'
  FR 2.1 == FR source verbatim, re-verified).
- **Why one clause rather than two sequential unfreezes:** H4's ours-override (a Batch 2 finding)
  was likewise not reflected in any frozen artifact; folding both into a single criterion-level
  correction avoids a second unfreeze cycle.

**Batch 1 status:** re-frozen upon this correction passing through Batch 2 Round 3's review of
the full chain (no separate Batch 1 round, per the precedent that an unfreeze is a non-counted
event followed by the next round's re-verification of all affected artifacts).

---

## Batch 2 Round 3 — 2026-10-06

**Reviewed:** `design.md` + `proposal.md` as one chain (post–Round 2 fixes + surgical unfreeze)
**Baseline:** frozen `proposal.md` (criterion 2 re-corrected), `explore-brief.md` §5, `AGENTS.md`, `../final/FR&NFR.md`
**Independent re-verification this round:** the §5 grep executed literally against `routes/web.php` (plus a corrected-pattern control); full `$uiPages` array read; `RouteGateMatrixTest.php` in full; case-insensitive `reserved` sweep of `CodingMAIN.md`; `CodingMAIN.md:166/:168` read; full 110-line `../final/FR&NFR.md` audit; migration `:31` read; `StudentMyTimetable.php` `navItems` located.
**Verdict: ❌ FAIL — 1 🔴 Outstanding** (Batch 2 Round 3 of 5)

### 🔴 Fixed

- **[B2-10] RESOLVED — the surgical unfreeze genuinely dissolved the contradiction; it did not paper over it.** `proposal.md:78` criterion 2 now defers to "the frozen `design.md` §4.2 14-hunk table governs" and names **both** overrides explicitly — H4 (ours, cohort-scoped, FR 1.3–1.4, verified against `../final/FR&NFR.md`) and H9 (theirs wholesale, all 13 rows) — with the FR 4.7 gloss's deliberate drop and the FR 4.11 3-state grounding stated inline. The dated correction note ("Ⓑ2 surgical unfreeze 2026-10-06 … mirrors the 12→6 precedent") is present, and the non-counted unfreeze entry is in `review-log.md`. An implementer executing criterion 2 literally now produces exactly the outcome §4.2 prescribes — the two artifacts no longer diverge on any row. Factual pillars re-verified first-hand: `FR&NFR.md:61` reads 3-state with `(state-machine method in Harel, 1987)`; the token `Reserved` appears **nowhere** in the 110-line file; FR 1.3/:18 and 1.4/:19 both read *"for their cohort"*; FR 4.7 (:57) carries no supersession note; migration `:31` CHECK pins `('available','pending','occupied')`. Scope of the unfreeze was one clause; nothing else in the frozen artifact drifted.
- **[B2-12] RESOLVED.** Case-insensitive sweep of our HEAD `CodingMAIN.md` returns **exactly 6 sites — :46, :53, :145, :251, :398, :414 — matching §13's enumeration line-for-line.** §13 row 1 now carries the full list, the sharpest residual (:145, the §4 domain-model 4-value enum) is named, and the "outside every hunk" check is explicitly extended to :53/:145/:398/:414.
- **[B2-13] RESOLVED.** §13's last row is converted to verified-TRUE with the arithmetic inline. Independently re-counted this round against the real file: 48 FR (9+16+7+16) + 26 NFR (4+6+6+3+3+1+1+2) + 11 `x.0` headers = 85 — exact match. Correctly no longer carried as debt.
- **[B2-14] RESOLVED.** H4's citation now reads "`CodingMAIN.md:166`" and `:166` genuinely carries the FR 1.2 scoping gloss (*"FR 1.2 scopes students to their cohort timetable"*) — verified. `../final/FR&NFR.md` confirmed 110 lines, so the old "same file's line 166" was indeed impossible.
- **Both Round 2 Optional items hold up.** §4.1's keyed-edit table now uses `'/upcoming-replacements-ui'` / `'/replacement-history-ui'` with leading slashes — matching the actual array-key format at `routes/web.php:49`. §6's hardcoded-const warning is accurate against the test source: `ALL_ROUTES`/`STUDENT_ONLY`/`LECTURER_ONLY` are `private const` literals at `:29–50`, not `$uiPages`-driven, and the only `upcoming-replacements` occurrences are exactly `:20`, `:33`, `:41`.

### 🟡 Addressed

- *(none this round beyond the above — no new 🟡 was outstanding from Round 2)*

### 🔴 Outstanding

- **[B2-11] NOT fixed — the corrected gate still deterministically fails at S7, and the fix's prose introduces two new factual errors.** Three verified defects in `design.md` §5's route-parity row:
  1. **The literal command yields 0, not 8.** Executed verbatim against `routes/web.php`: the pattern `^\s+'/(...)-ui',` requires a **comma** immediately after the key's closing quote, but every `$uiPages` key line ends `-ui' => [` — result **0 matches**. Control run with `...-ui' =>` instead: 7 pre-merge (8 post-edit). Same deterministic-false-failure class as the original [B2-11]; the quote was closed but the trailing `',` was never re-derived from the file.
  2. **The prose misstates the array.** "Our `$uiPages` post-edit stays **8 keys**" and "the explicit non-`-ui` route is `/replacement-arrangement`, which lives **outside** `$uiPages` per `RouteGateMatrixTest.php:35`" are both false: the array at `routes/web.php:30–85` has **9 keys**, and `/replacement-arrangement` sits **inside** it at **:61–66**. `RouteGateMatrixTest.php:35` is merely that route's entry in the test's 9-route `ALL_ROUTES` const — which mirrors the array's 9 keys and therefore *corroborates the opposite*. (Round 2's own "8 keys, lines 31–79, all `-ui`" verification was itself a miscount; this fix inherited it.) The **expected number 8 remains correct** — but only as the count of `-ui`-suffixed keys, not as the array size.
  3. **The dead `replacement-arrangement` alternative is still in the alternation** despite the fix claim that it was removed. Count-neutral (verified it cannot match), but its survival shows the pattern was patched, not re-derived.

  **Fix:** pattern → `grep -cE "^\s+'/(my-timetable|cohort-timetable|student-my-timetable|replacement-history|replacement-home|my-request-history|venue-timetable|request-approval)-ui' =>" routes/web.php` == **8**; prose → "post-edit the array keeps its **9** keys — 8 `-ui`-suffixed plus `/replacement-arrangement` (inside `$uiPages`, `routes/web.php:61–66`, outside the pattern by construction)". No other artifact is affected — this is one row of §5.

### 🟡 New (non-blocking, fold into the same edit pass)

- **[B2-15] §5 and §8 criterion 3 diff against the stale `438bdfe` baseline.** §5's 5-changelog row asserts `git diff 438bdfe..HEAD -- <path>` and §8 row 3 diffs "the 8 against `438bdfe` versions" — but `438bdfe` predates the concurrent session's 4 commits (`633eeb3` fix(auth) … `9d87a79` FR 4.3 report), and the same design's S3 declares that ref "stale and **must not be used**". Any line removal made by those 4 commits would be misattributed to the merge → false gate failure → false abort. §8 row 6 already uses the correct baseline (`9d87a79..HEAD`) — use the S1-pinned merge-time HEAD everywhere for post-merge retention checks.

### 💡 Optional

- §13 row 1's "post-merge contradiction count is **≥2, not 1**" understates its own evidence: with :251 fixed by H9, the surviving slot-state contradictions are **3** (:46, :53, :145); :398/:414 are, by the row's own argument, legitimate UI semantics. State 3.
- §13 row 1 contains "explícit­ly" — accented í plus a soft hyphen; cosmetic, but it sits in a debt register someone may grep.
- Criterion 2's "exactly two evidence-based overrides" doesn't cover H14's row-by-row UNION (which keeps ours for 9 rows). The governing clause ("the §4.2 14-hunk table governs") already subsumes it, and H14 rejects no upstream content, so no contradiction — one clause noting "H14 is a union, not an override" would close the last ambiguity.

### Assessment

Round 2's decision-level work is genuinely landed: the surgical unfreeze did exactly what [B2-10] prescribed and survives first-hand re-verification against the FR source, and [B2-12]–[B2-14] plus both optionals are accurate against ground truth. What remains is narrow and mechanical but still gate-breaking: the §5 parity gate, twice corrected on paper, has never once been executed against the actual file — running it (this round, verbatim) returns 0. The pattern must be re-derived from `routes/web.php:30–85`, not edited in place.

**Next:** fix [B2-11] (pattern + prose, one row) and fold [B2-15]; execute the grep before submitting Round 4. Round count: Batch 2, 3 of 5.

---

## Batch 2 Round 4 — 2026-10-06

**Reviewed:** `design.md` (post–Round 3 fixes)
**Baseline:** frozen `proposal.md` (criterion 2 as surgically unfrozen), `explore-brief.md` §5, `AGENTS.md`
**Independent re-verification this round:** the corrected §5 grep executed first-hand against `routes/web.php` (**7 pre-merge** matches at :31/:37/:43/:55/:67/:73/:79); full `$uiPages` read (`routes/web.php:30–85`); `RouteGateMatrixTest.php:29–50`; raw-byte inspection of the §5 and §8 table cells (read + literal fixed-string grep cross-checked against each other); §4 hunk arithmetic (1+14+3 = 18 ✓).
**Verdict: ❌ FAIL — 1 🔴 Outstanding** (Batch 2 Round 4 of 5)

### 🔴 Fixed

- **[B2-11] core RESOLVED — semantics and prose now match ground truth.** The corrected pattern (trailing `' =>`, dead `replacement-arrangement` alternative removed — confirmed absent from the alternation) was executed first-hand this round: **7 pre-merge**, exactly as re-derived. Post-edit = 8 by construction: `/replacement-history-ui` is absent pre-merge (verified) and the swap is suffixed-for-suffixed. The prose is finally true against the file: `$uiPages` has **9 keys** (8 `-ui`-suffixed incl. `/upcoming-replacements-ui` at :49, plus `/replacement-arrangement` at :61–66 **inside** the array, outside the pattern by construction); `RouteGateMatrixTest.php:35` is correctly described as that route's entry in the test's 9-route `ALL_ROUTES` const (:29–39), which corroborates rather than contradicts. All three Round 3 prose errors are gone.
- **[B2-15] RESOLVED.** §5's changelog row (`design.md:119`) and §8's criterion-3 row (:151) both baseline against the **S3 tag (merge-time HEAD)** with the `633eeb3..9d87a79` rationale stated inline; row 6's `9d87a79..HEAD` is the same ref given S1 pins HEAD == 9d87a79, so S3-tag == 9d87a79 and all three post-merge retention checks now agree.
- **Round 3 optionals landed.** §13 row 1 states **3** surviving slot-state contradictions (:46, :53, :145) with :398/:414 correctly excluded as UI semantics; the accented "explícit­ly" is fixed; H14 (:86) carries the "union, not an override" clause, closing criterion 2's "exactly two" ambiguity. H4's citation remains `CodingMAIN.md:166` — no regression.

### 🟡 Addressed

- *(none — no 🟡 was outstanding from Round 3)*

### 🔴 Outstanding

- **[B2-16] §5's route-parity command is not executable as serialized — [B2-11]'s failure class returns at the transcription layer.** Raw `design.md:121` contains `\\|` (double backslash) before every alternation pipe (verified by literal fixed-string grep: `timetable\\|cohort` matches, `timetable\|cohort` does not). Two independent consequences:
  1. **Verbatim copy-paste from the raw file deterministically fails.** Bash double quotes collapse `\\` → `\`, so `grep -cE` receives `\|`, which GNU ERE treats as a **literal pipe** — the pattern then demands the literal string "my-timetable|cohort-timetable|…" → **0 matches** → deterministic false failure at S7 → false abort of a healthy merge. This is precisely [B2-11] defect #1, twice corrected on paper and now reintroduced by the escaping.
  2. **GFM fidelity is unprovable.** In GFM tables a pipe preceded by an even number of backslashes is a cell delimiter under parity-aware parsers, so the row can split into ~9 cells — the same malformation class the §8 fix below was supposed to eliminate. (A pipe preceded by one backslash, `\|`, is the correct in-table escape and renders as `|`.)

  The author's terminal run was real — I reproduced it (bare pipes → 7) — but **the executed command and the documented command differ**, and Round 3's standard was explicitly "execute the grep before submitting": the artifact must carry the command that was executed. A bare-pipe alternation cannot live inside a table cell; that is why §2 puts its command in a fenced block. **Fix:** re-serialize with single `\|` per pipe (renders correctly, copies correctly from rendered output) or — better — move the command to a fenced block directly under the §5 table (the §2 pattern), then execute the fenced command verbatim and record the output before Round 5.

### 🟡 New (non-blocking, must land before freeze)

- **[B2-17] §8's criterion-2 and criterion-3 rows are still 3-cell — the fix claimed this round did not land.** `design.md:150` (`| 2 | 6+7 file resolutions … | Per-row grep … |`) and `:151` (`| 3 | 8 page-changelogs/* … | Diff each of the 8 … |`) each carry an extra cell against the 2-column `| Criterion | How |` header; every other row follows the merged-criterion format of row 1 (`| 1 `/replacement-history-ui` = 200 | … |`). GFM drops excess trailing cells, so the rendered table silently loses the entire How content of criteria 2 and 3 — exactly the H4/H9 grep matrix and the S3-baseline rule ([B2-15]'s fix lives inside the malformed row). Content is correct in raw text (hence 🟡, not 🔴), but the confirm item fails: the table does **not** parse cleanly. Join both rows to the 2-column form before freeze.

### 💡 Optional

- §5's parentheticals mis-explain the 7→8 delta: `upcoming-replacements` is not in the alternation, so its presence or deletion never affects the count; the delta is entirely `/replacement-history-ui`'s addition. Numbers are right; the explanation would send a re-deriving implementer in a circle.
- "Command, executed and confirmed returning 8 after the merge" overstates — the merge has not run; 8 is confirmed by derivation (airtight). Say "derived; to be confirmed at S7/S8".
- §13 row 1's tail is mangled edit residue: ":398/:414 are the row's own argument-that it's legitimate UI semantics" — garbled grammar, and it duplicates the same point made earlier in the same cell. Rewrite.
- Sequencing: §5 is "after S7", but the web.php row's `= 8` expectation only holds after S8's keyed edits; at S7 the honest expectation is 7. Both values are stated, so navigable — consider marking the `= 8` assertion "run after S8".

### Assessment

Every decision-level item across Batch 2 is now closed and first-hand verified: the H9/H4 evidence chain, the surgical unfreeze, the route-parity semantics (7/8/9 keys, :61–66, the test's mirroring const), and the S3-tag baselines. What remains is purely mechanical but gate-breaking in exactly the way [B2-11] was: the artifact's own command still fails verbatim execution, and two table rows still don't parse as claimed. The round's fix claims were accurate about substance and inaccurate about serialization — the grep was executed in a terminal, but the command that entered the document is not the command that was executed.

**Round count: Batch 2, 4 of 5. Round 5 is legitimate (cap = 5).** Fix [B2-16] + [B2-17] + optionals, execute the fenced command verbatim, then run Round 5.

---

## Batch 2 Round 5 — 2026-10-06

**Scope:** re-review of Round 4's fix pass on `design.md`. `proposal.md` frozen.

### Verification (first-hand)
- **§5 fenced grep executed verbatim → 7.** Matches `routes/web.php` :31/:37/:43/:55/:67/:73/:79; pre-merge premise confirmed live: `/upcoming-replacements-ui` present at :49, no `'/replacement-history-ui'` key.
- **Pipe-width sweep (design.md + proposal.md): ALL TABLES WELL-FORMED** at single width per block — §1/§4.1/§4.2 (H4 fixed: 4 cols, 3 `\|` escapes)/§4.2.1/§5/§6/§8/§11/§12/§13; proposal criteria (criterion 2 fixed) + Risks.
- **§8 rows 2–3 confirmed 2-column, content intact** (H4/H9 grep matrix, `Reserved (Self)`=0, `Supersedes MPU-3133-specific`=0, `View global…ledger`==0; S3-tag-not-`438bdfe` rule with `633eeb3`..`9d87a79` rationale).
- **No `\\|` in either artifact** (only review-log:717 quotes the old defect). Single `\|` appears only in design.md:76 and proposal.md:78 as intended in-table escapes.
- **B2-13 debt row** clean 3-cell; Round 4 optional items all landed (7→8 delta attribution, run-after-S8 sequencing note, §13 tail).

### 🔴 Critical Issues
None.

### 🟡 Should Fix
None.

### 💡 Optional Suggestions
- `design.md:124` fence comment ("single \ before every pipe — in-table escape would break rendering") is technically muddled: `\|` renders fine in GFM; it breaks verbatim copy-paste execution, not rendering. Comment-only.
- `design.md:208` tail phrasing "the row's own argument—that it's legitimate" slightly garbled; comprehensible.

### ⚖️ Verdict
**PASS — no 🔴 outstanding. Batch 2 (design.md) freezes.**

---

## Batch 3 Round 1 — 2026-10-06

**Reviewed:** `specs/merge-conflict-resolution/spec.md` (new, first spec file)
**Baseline:** frozen `proposal.md` (criterion 2 as surgically unfrozen), `explore-brief.md` §5, frozen `design.md` (Batch 2 Round 5), `AGENTS.md`
**Independent execution this round (verbatim greps against the live pre-merge tree):** S3 parity grep = **7** (:31/:37/:43/:55/:67/:73/:79); `grep -c "upcoming-replacements" routes/web.php` = **3** (:49/:51/:52); `test -f page-changelogs/upcoming-replacements-ui-changelog.md` = **true pre-merge** (so S5's `test ! -f` is a meaningful gate); S1's en-dash phrase live at `:168` (1); `View global replacement history ledger` = **0**; `Available, Pending, and Occupied` = **0**; `Supersedes MPU-3133-specific` = **1** (:247); `Reserved (Self)` = **0**; `grep -ci "reserved" ../final/FR&NFR.md` = **0** (also line-count 110 ✓, FR 4.11 at :61 verbatim ✓, FR 4.7 at :57 gloss-free ✓); `'reserved'` in `app/` and `database/` = **0/0**; migration CHECK at `:31` ✓; `class_exists` single hit at `routes/web.php:88` in the `$useComponent` ternary ✓; `$uiPages` = 9 keys (:30–85) incl. `/replacement-arrangement` at :61–66 ✓; `CodingMAIN.md` case-insensitive `reserved` = exactly 6 sites (:46/:53/:145/:251/:398/:414) matching design §13 ✓; `ui-common.js` helpers all present pre-merge (to12h :1409, formatDate :1410, toggleTheme :11, updateIcon :3, navigateHome :20) ✓; whole 8-file S4 list exists on disk ✓.

### 🔴 Critical Issues (implementation blockers)

- **[B3-1] S2's `Reserved (Self)` sentinel is vacuous — the check can never fail, so it verifies nothing.** Executed first-hand: `grep -c "Reserved (Self)" CodingMAIN.md` returns **0 pre-merge**. Ours' actual 4-state FR 4.11 row at `:251` reads *"…through Available, Pending (Self), Reserved (Other), and Occupied"* — the token pair `(Self)` belongs to **Pending**, not **Reserved** (`:45` confirms: `pending` = "Pending (Self)"). So `Reserved (Self)` → 0 pre-merge AND 0 post-merge regardless of resolution, meaning an implementer who preserves ours' 4-state row entirely **passes S2**. The frozen design §8 row carries the identical vacuous sentinel (`Reserved (Self)=0`), so the spec inherited a defect that survived all five Batch 2 rounds exactly like [B2-11]/[B2-16] did — a gate never executed against ground truth. Same class, third occurrence. Note the internal-interaction danger: S7 requires the `reserved` rows at :46/:53/:145 to survive while S2 asserts `reserved`-token-only phrases == 0 — safe only when the sentinel is precise, and it isn't.
  **Fix:** replace the sentinel with a phrase that exists pre-merge and dies with the :251 row: `grep -c "Pending (Self), Reserved (Other), and Occupied"` — **≥ 1 pre-merge, == 0 post-merge**. Do **not** use `Pending (Self)` alone (it survives at :45 via S7). Execute first-hand before Batch 3 Round 2. Apply the identical one-token soft-freeze correction to frozen `design.md` §8's row (declarative: swaps one grep for a truthful one, changes no decision).
- **[B3-2] S3 under-asserts the `replacement-history-ui` key: only 2 of the 4 design-mandated fields are gated, and one of the missing ones is the 500-prevention field.** Spec line 31 asserts `'component' => 'App\Livewire\ReplacementHistory'` + "`mw` including `'role:student'`". The frozen design §4.1 table mandates **exactly** four fields — `component` **/ `legacy` => `'ui-design-templates.replacement-history-UI-design-template'` / `nav` => `'replacement-history'` / `mw` => `['auth', 'role:student']`**. An implementer satisfying the spec can add the key without `legacy`/`nav` and **pass every gate** — and verified this round, `routes/web.php:90–92` falls back through `fn () => view($page['legacy'], ['activeNav' => $page['nav']])`, so a key stripped to component+mw produces an **undefined-array-key** fallback and threatens a 500 — precisely the failure mode the proposal's risk table promises can never happen. Pedagogically safe only because `class_exists` at :88 **is false** for `App\Livewire\ReplacementHistory` (verified: only `UpcomingReplacements` is referenced in the pre-merge array), which means the legacy view is the **only** render path until Wave 3 wires the component — `legacy` is load-bearing, not decoration.
  **Fix:** extend S3 to assert all four fields verbatim from design §4.1 (grep for the `legacy` view name and `nav` value scoped to the key's block; assert `mw` is exactly `['auth', 'role:student']`, not merely "including").
- **[B3-3] The `todo list/todo-list.md` explicit check is a frozen requirement (criterion 2, design §5, design §8) with **zero** trace in the spec.** Frozen criterion 2 says the file "is **both-modified** … yet merges silently, so check it explicitly". `grep` confirms the spec contains no `todo`, no `H14`, no `7.4` token. S4's 8-file list matches frozen criterion 3's count (5 auto-merge changelogs + 3 content conflicts), so the omission is a **scoping decision, not a typo** — but it's the wrong side of the line: `todo-list.md` is the **9th** changelog, present on disk, and this spec is the sole requirements artifact Batch 4's `tasks.md` will be derived from. An implementer working from the spec alone skips the path most likely to be silently wrong.
  **Fix:** one S4 extension paragraph — assert `page-changelogs/todo list/todo-list.md` (quote the path; spans a literal space) diffed against the S3 tag shows our `633eeb3` rollover lines **and** upstream's edits both retained, per design §5's row.

### 🟡 Should Fix (non-blocking but recommended)

- **[B3-4] S4's diff gate and S7's line-number pinning lack the pre-merge→post-merge delta the design fence already carries.** S3 says "execute the §5 fenced command, expected **8**" with no statement of pre-merge **7** or "run after S8" — the fence carries both, so a spec-only implementer risks the deterministic false-failure class. S7 pins ":46/:53/:145" — **pre-merge** coordinates; H1/H3/H14 above them take theirs', so post-merge line numbers shift; *text survives* is the claim, not coordinates.
  **Fix:** S3 += "(**7** immediately post-merge, **8** only after S8's keyed edits — run at S8 completion)"; S7 += "post-merge `reserved` == **5** sites (6 pre-merge, minus the :251 slot-state row killed by H9), asserting the remaining :46/:53/:145 texts survive contentually, coordinates may shift".
- **[B3-5] S6's nameless helpers can't be gated.** Seven helper names are greppable, but "the consolidated table/sort/pagination/urgency helpers" is nameless prose inherited from design §5. **Fix:** name real greppable symbols from the live file (e.g. `getWeekNumber` :1541 and the consolidation entry points visible at :1321/:1409-1411).
- **[B3-6] S2's migration quote cannot be matched literally.** Spec line 22 writes `CHECK (status IN ('available','pending','occupied'))`; live :31 has escaped quotes — semantically true, grep-false. **Fix:** phrase as "the `time_slots_status_check` CHECK constraint lists exactly `available`, `pending`, `occupied`"; note `grep -c` counts lines not occurrences.

### ✅ What's Done Well
- Traceability near-perfect: S1↔H4, S2↔H9, S3↔§4.1+§5+§8, S4↔criterion 3, S5↔criterion 4+§4.4, S6↔criterion 2's ui-common clause+§5, S7↔design §13. S4's 8 items all correspond to on-disk pages.
- The S3 in-prose grep is **byte-identical** to design's §5 fence; the [B2-16]/[B2-17] escape classes do **not** recur.
- S1/S2's evidence chains re-verified and hold (FR&NFR 110 lines, :61/:57, reserved 0/greps, 6 sites, helpers present, 8 files on disk).
- S5's `test ! -f` is genuinely meaningful (file exists pre-merge) and "git rm, not checkout-restore" closes the resurrection path.
- S7's framing (assert the debt registration, not a doc "fix") is correct for a merge change that cannot scope-edit §3/§4.

### 💡 Optional
- S1's opening "`routes`/`matrix` outputs of grep" reads like edit residue — rephrase.
- Boundary invariant's post-merge `git status` should decode *what aspect* it reads (tracked-vs-untracked post-[B1-9] supersession).
- Consider re-ordering scenarios to the chronological S-step mirrors of design §1.

### ⚖️ Verdict

**FAIL — 3 🔴 Outstanding ([B3-1], [B3-2], [B3-3]).** Same block mode as Batch 2: gates written but never executed first-hand. All three fixes narrow; no artifact unfreeze required (design §8's identical [B3-1] sentinel is a declarative soft-freeze correction). Round count: Batch 3, 1 of 5. `tasks.md` must be derived from the **fixed** spec.

---

## Batch 3 Round 2 — 2026-10-06

**Reviewed:** `specs/merge-conflict-resolution/spec.md` (post–Round 1 fixes; sole spec file in the batch)
**Baseline:** frozen `proposal.md` (criterion 2 as surgically unfrozen), frozen `design.md` (Batch 2 Round 5 + the §8 sentinel soft-freeze), `explore-brief.md` §5, `AGENTS.md`
**Independent execution this round (verbatim, against the live pre-merge tree):**
- S3 parity grep executed literally → **7 pre-merge** (:31/:37/:43/:55/:67/:73/:79; `/replacement-history-ui` absent pre-merge, so post-S8 = 8 by construction). Matches the spec's "7 immediately post-S7 / 8 at S8 completion" sequencing exactly.
- Swapped sentinel executed literally: `grep -c "Pending (Self), Reserved (Other), and Occupied"` = **1 pre-merge**, at `CodingMAIN.md:251` only. `Pending (Self)` alone appears at :45 and :251 — :45's tail ("Drafted by current user, awaiting PL approval…") confirmed distinct, so the spec's warning "do not use `Pending (Self)` alone" and the sentinel's uniqueness claim are both true.
- `test -f page-changelogs/upcoming-replacements-ui-changelog.md` → **exists pre-merge** → S5's `test ! -f` gate is meaningful, as asserted.
- `design.md:159` §8 confirmed: sentinel swapped with the inline "(sentinel corrected Batch 3 R1… declarative soft-freeze, no decision change)" note. Verified decision-neutral: it swaps one impossible grep for a truthful one; the H9 "theirs wholesale" semantics are unchanged. The `App\\\\Livewire\\\\ReplacementHistory` grep in the same row sits inside a code span, so backslashes survive rendering; raw- or rendered-copy → bash double-quote collapse → regex `\\` matches one literal backslash → correctly matches the PHP single-quoted component string post-S8 (≥ 1). No [B2-16]-class recurrence.
- `public/js/ui-common.js` S6 symbols re-verified live: all 8 consolidation entry points present as `function` definitions (`statusClass` :1848, `requestAgeHtml` :1875, `getWeekRange` :1899, `isInWeek` :1904, `getWeekNumber` :1911, `populateWeekSelect` :1923, `updateNavBadge` :1954, `rebuildTable` :1975), plus the 5 named symbols at exactly the spec's cited lines (:3/:11/:20/:1409/:1410).
- S3's four field assertions re-checked character-for-character against `design.md:61` (§4.1 row) and live `routes/web.php:88/:92` (`class_exists` ternary → `$page['legacy']`/`$page['nav']` fallback) — the `legacy`-is-load-bearing rationale is factually grounded.

### 🔴 Fixed

- **[B3-1] RESOLVED.** The sentinel is now `Pending (Self), Reserved (Other), and Occupied` with the pre-merge truth (1, at :251) recorded inside the spec itself — and it was executed first-hand this round and reproduces exactly. It is a discriminating gate: the phrase exists pre-merge and dies precisely when H9 takes theirs' 3-state row, so an implementer who preserves ours' 4-state row now **fails** S2 (Round 1's failure mode). The ":45 does not match" caveat is accurate first-hand. The frozen design §8 carries the identical corrected sentinel with an honest correction note — the soft-freeze was applied correctly and is auditable in the artifact, not just in this log.
- **[B3-2] RESOLVED.** S3 now asserts **all four** §4.1 fields verbatim — component/legacy/nav/mw — as exact array contents, and states `legacy`'s load-bearing rationale: `class_exists` false pre-merge (:88), `:90–92` fallback is the only render path, stripped-to-component+mw ⇒ undefined-array-key 500. Re-verified live: the spec's quoted strings match design §4.1's row character-for-character, and the fallback code is exactly as described.
- **[B3-3] RESOLVED.** S4's 9th paragraph names `page-changelogs/todo list/todo-list.md` with the space quoted, requires both sides retained (our `633eeb3` rollover edits + upstream's independent edits), baselines against the S3 tag (not `438bdfe` — consistent with [B2-15]'s rule), and states the gate's distinct purpose: git auto-merges it, so the gate confirms acceptance was correct rather than hands a noisy resolution. Traceability from frozen criterion 2 → design §5 → spec is now unbroken.

### 🟡 Addressed

- **[B3-4] RESOLVED.** S3 carries the 7/8 sequencing clause with "run the gate at S8 completion, not mid-merge"; S7 no longer pins pre-merge coordinates — "coordinates may shift… assert the *texts survive contentually*" — and records the post-truth arithmetic `reserved` 6 → 5 (:251 dies with H9; surviving :46/:53/:145 texts asserted contentually). Consistent with design §13's 3-contradiction + 2-UI-legend enumeration.
- **[B3-5] RESOLVED.** S6 names 12 real greppable symbols, all verified live as `function` definitions this round; the "regression to report, never a hand-patch" rule survives.
- **[B3-6] RESOLVED.** S2's migration evidence is now a semantic description ("lists **exactly** `available`, `pending`, `occupied`") with an explicit honesty note about the escaped quotes — a claim that can no longer deterministically mislead.
- **Optional from Round 1:** S1's "outputs of grep" residue is gone (clean grep-bullet form); §6-matrix wording tightened. Both landed.

### 🔴 Outstanding

- **None.** All three Round 1 blockers and all three 🟡 are closed with first-hand-executed, artifact-internal evidence. Empty section = freeze condition met.

### [B3-4]-interaction sanity check (asked explicitly)

**No new contradiction.** The two gates now partition cleanly: S2's sentinel is a **phrase-level** grep whose only pre-merge match is :251 (proven: full-phrase count = 1), while S7's 6 → 5 is a **token-level** count across sites that do not contain the sentinel phrase. Post-merge, :251's death simultaneously satisfies S2 (1 → 0) and S7 (6 → 5) from the single same edit — the two gates are different probes of one underlying fact, mutually consistent by construction, and both now deliberately avoid the fixed-line-number fragility that would have recursion'd the [B2-16] false-failure class. S2's evidence chain and S7's content-survival assertion do not overlap on any single string.

### 💡 Optional Suggestions

- S4's 9th paragraph says the 9th file "produces **no** conflict" — factually right (both-modified auto-merge), but pre-merge verification of *that* claim is only reachable via `git merge-tree`; when `tasks.md` derives from this spec, carry the tree-dry-run as the pre-merge source rather than leaving it as historical fact.
- S4's 8-file list states the `theirs-before-ours` ordering rule only for "the 3 resolved files"; a one-line "(the 5 auto-merged changelogs are excluded from the ordering rule — no resolution occurred)" would prevent an over-eager implementer from trying to diff-order auto-merged blocks.

### ⚖️ Verdict

**PASS — Batch 3 (`specs/merge-conflict-resolution/spec.md`) freezes.** Round count: Batch 3, 2 of 5. `tasks.md` (Batch 4) must be derived from this fixed spec; the design §8 sentinel soft-freeze is within the declarative rule and needs no unfreeze chain.

---

## Batch 4 Round 1 — 2026-10-06

**Reviewed:** `tasks.md` (new; 7 task blocks / 35 items — final batch, a PASS freezes the change)
**Baseline:** frozen `proposal.md` (criterion 2 as surgically unfrozen), frozen `design.md` (B2R5 + §8 sentinel soft-freeze), frozen `specs/merge-conflict-resolution/spec.md` (B3R2), `explore-brief.md` §5, `AGENTS.md`
**Independent execution this round (file-level, verbatim against the live pre-merge tree):** parity grep = **7** (:31/:37/:43/:55/:67/:73/:79); `upcoming-replacements` in `routes/web.php` = **3** (:49/:51/:52); sentinel `Pending (Self), Reserved (Other), and Occupied` = **1**, only :251; `Available, Pending, and Occupied` = **0 pre-merge**; `cohort-scoped, FR 1.3–1.4` = **1** (:168); `View global replacement history ledger` = **0**; `(?i)reserved` in `CodingMAIN.md` = **6 sites** (:46/:53/:145/:251/:398/:414, = design §13's base); `class_exists` :88 ✓; `StudentMyTimetable.php:77` matches T4.1's key/label/href exactly; `RouteGateMatrixTest.php` :20/:33/:41 = T4.2's three spots exactly; migration CHECK `2026_08_03_000006:31` verbatim (escaped quotes, exactly available/pending/occupied). Not reproducible without a terminal (accepted as recorded truth): adminer-only pint baseline, 105/105 phpunit, playwright-config absence on `fedora-frontend`, merge-tree hunk ledger.

### 🔴 Outstanding

- **[B4-1] Frozen design §8 gate with no executing task: `grep -c "Supersedes MPU-3133-specific"` == 0 appears nowhere in `tasks.md`.** Criterion 2 says the FR 4.7 supersession gloss is "deliberately dropped"; T2.3 performs the drop, but the only probe catching a hand-restore during H9 resolution (the exact error design §4.2's H9 row records from its own Round-1 history) is absent. Same class as [B3-1]/[B3-3]: a frozen requirement unreachable from the implementer's checklist.
  **Fix:** fold into T6.6 — one line: `grep -c "Supersedes MPU-3133-specific" CodingMAIN.md` == 0.
- **[B4-2] T7.2 is untraceable to any frozen artifact AND as ordered invalidates T7.4's criterion-8 gate.** Grep: `backend-automated-by-ai` occurs **only** in `tasks.md`; T7.2's citation ("proposal §In-scope last bullet") is wrong — that bullet is "Gate runs and the route smoke test." Ordering defect: T7.2 appends to a **tracked** `page-changelogs/backend-automated-by-ai.md`, then T7.4 asserts (frozen design §8 criterion 8) "only `.commandcode/` untracked + this change's own `.sdd/` files modified; **nothing else**" — deterministically false after the append.
  **Fix (any one, deliberate):** (a) drop T7.2; (b) move the status check before the append; (c) keep order + widen allowlist with a logged criterion-8 soft-freeze.

### 🟡 Should Fix

- **[B4-3] T3.1 says "all **12** greppable symbols" then lists **13.** List complete, only the count wrong. Fix: "13".
- **[B4-4] T3.4's "at S7−pre-T2.2 the honest value is 7" describes an unreachable state** — T2.2 precedes Task 3, so 8 is the only honest value at T3.4. Reword.
- **[B4-5] T6.8 hard-asserts `grep -ci "reserved"` == 5 post-merge, but 6→5 is derived, not merge-tree-verified** — assumes the 11 theirs hunks contribute zero `reserved` lines; otherwise a false failure ([B2-16] class). Frozen spec S7 has text-survival primary. Fix: content-survival primary, count informational (or pre-verified against merge-tree at execution).

### 💡 Optional

- T6.4 typo "grafically" → "graphically" (the carried rule — never `pkill -9 php` — is correct).
- T1.2's parenthetical = authoring-time truth; at execution `tasks.md`/`sdd.yaml` also appear.
- Spec S3's four-fields-verbatim + `class_exists` ≥ 1 have no post-merge task assertion; T6.5's smoke covers both functionally. Optional belt-and-braces in T3.4/T4.3.

### ✅ What's Done Well
- T2.7 sits precisely where design row 8's "*After merge:*" makes the narrow form true — genuine option-(a) execution; the `.git/index`-calibrated probe confirms the `.sdd/` wording correctly pins the tracked → ` M` form.
- The two corrected gates partition cleanly over the timeline with no contradictory residue; the false Round-2 annotation is gone.
- Enumeration facts verifiable against the index are all true (BACKEND-TASKS.md / seeder untracked, playwright.config.ts tracked-unmodified).

### 💡 Optional Suggestions
- T6.4: `pkill -f "artisan serve"` exits non-zero on no-match → `&&` chain dead-ends; add `|| true` (applied in this pass).
- `.commandcode/` file-glob returned nothing at review time — absence never false-fails, but a "if present" hedge removes ambiguity (folded into T2.7 wording in this pass).
- Cosmetic: T7.2's "M app/…" vs porcelain's " M". Harmless.

### ⚖️ Verdict

**FAIL — 1 🔴 Outstanding ([B4-7]).** Round 3 of 5. The fix-pass applied in the same pass: [B4-7] T5.4 pre-check + T7.2 re-enumeration (12 `A`-staged frontend paths; `auth-wiring.spec.js` ours-only → no status line; overlay-not-delete semantics empirically verified in a scratch repo); [B4-8] registered as a T1.1 apply-time pin-refresh + merge-tree re-derivation note; both optionals applied. `playwright.config.ts` claim in T7.2 corrected (tracked-unmodified = no status line). Round 4 next.

---

## Batch 4 Round 3 — 2026-10-06

**Reviewed:** `tasks.md` (post–Round 2 fix pass: [B4-6] option (a) — new T2.7 + re-scoped T7.2; T6.4 dash restore; false-annotation removal). **Last batch — a PASS here freezes the change for apply.**
**Baseline:** frozen `proposal.md` (criterion 2 as surgically unfrozen; criterion 8's supersession note at :84), frozen `design.md` (B2R5 + §8 sentinel soft-freeze), frozen `specs/merge-conflict-resolution/spec.md` (B3R2), `explore-brief.md` §5, `AGENTS.md`
**Review-runtime constraints (audit note):** no shell in this reviewer runtime — `git status`/`ls-tree`/`merge-tree` not executable; tracked-vs-untracked facts calibrated via fixed-string greps of `.git/index` (calibrated: known-present route file → match; known-absent sentinel → no-match; no `MERGE_HEAD` → clean post-commit snapshot). Reflog/refs read textually.

### First-hand verification this round
- Cold-executed pre-merge truths reproduce exactly: parity grep = **7** (:31/:37/:43/:55/:67/:73/:79); sentinel = **1** (:251 only); `Supersedes MPU-3133-specific` = **1** (:247).
- **[B4-6] half 1 — T2.7 in the right position**, half-truth honest, anchored to design §8 row 8's "*After merge:*" (design.md:165); **the `.sdd/`-form probe = no defect** (files are tracked, committed at `45f725e`, so they show as ` M`, and T2.7 says "modified").
- **[B4-6] half 2 — T7.2 reads as the enumerated form** citing `proposal.md:84` ("plus this change's own files"); the "stays true inside its own window" residue is gone.
- **Enumeration vs predecessors:** T4's two ` M` ✓; `?? BACKEND-TASKS.md` ✓ (index: absent); `?? ReplacementRequestsSeeder.php` ✓ (index: absent); `playwright.config.ts` tracked + unmodified ✓ (B4R2's optional correction holds).
- **Safety rules survive:** T2.1 exactly-6-or-abort ✓; T6.4 `pkill -f "artisan serve"` ✓ (dash restored in this pass); T7.5 no-push ✓.

### 🔴 Outstanding

- **[B4-7] T7.2's `staged A tests/e2e/**` rests on an unexamined premise: `tests/e2e/` is NOT empty in our pre-merge tree — `tests/e2e/auth-wiring.spec.js` is tracked** (index-verified this round; live file confirmed). Three consequences T5.4/T7.2 never model:
  1. T7.2's "T5 tracked-new adds" claims the whole `tests/e2e/**` prefix is new to our HEAD — false for this path.
  2. T5.4's `git checkout origin/fedora-frontend -- tests/e2e` is **overwrite-capable** for every path present on both sides — content equality/divergence unverified.
  3. Whether the frontend branch still contains a copy — and whether it diverged — is unverifiable in this runtime; under a "divergent copy" scenario a staged `M tests/e2e/auth-wiring.spec.js` appears, which T7.2's "nothing else" declares unexpected debris → false stop on a healthy tree.
  **Fix (narrow, tasks.md-only):** T5.4 gains an honest first-hand pre-check (`git ls-tree -r origin/fedora-frontend tests/e2e/` + `git diff HEAD origin/fedora-frontend -- tests/e2e/auth-wiring.spec.js`), records which outcome holds (absent / identical / divergent), and only then checks out; T7.2 re-enumerates `A`-lines for frontend-only paths + the pre-existing tracked `auth-wiring.spec.js`'s disposition per the recorded outcome — explicitly non-debris. No frozen-artifact semantics change (criterion 6 / design §7 surface).

### 🟡 Should Fix

- **[B4-8] The pinned baseline git truth has drifted — first-hand verified via reflog.** `9d87a79` (Oct 6 18:59) → `43b7bb9a` 19:07 → `20d1cbbf` 19:16 → `2982ce83` 19:41 (dataset/ERD doc commits); `fedora-backend == origin/fedora-backend == 2982ce83` (pushed — no unpushed hazard); `upstream/fjing` unchanged at `36c4d2c`. `tasks.md`'s "HEAD `9d87a79`" pin is stale, so **T1.1's abort-and-report will deterministically trigger at apply**. Register: before `/sdd-apply`, refresh the pin + re-derive the `merge-tree` 6-conflict truth against `2982ce83` and re-check T1.2's confined-dirt assertion with the other session active.

### ✅ What's Done Well
- The option-(a) fix executed faithfully — T2.7 in the exact window design row 8's "*After merge:*" describes; the ` M`-form probe clean; both corrected gates partition cleanly.
- Index-verifiable enumeration facts all true (BACKEND-TASKS.md/seeder untracked; playwright.config.ts tracked-unmodified).

### 💡 Optional
- T6.4 `|| true` so an idle server doesn't dead-end the `&&` chain.
- `.commandcode/` "if present" hedge in T2.7.
- Cosmetic ` M` vs `M` in T7.2.

### ⚖️ Verdict

**FAIL — 1 🔴 Outstanding ([B4-7]) + 1 🟡 registered ([B4-8]).** Round count: Batch 4, 3 of 5.

---

## ⚠️ Non-counted soft-freeze edit — 2026-10-06 (post-Round-4 PASS, per the round's own instruction)

[B4-9] + the three Round 4 optionals applied to `tasks.md` (declarative, no decision content, no
re-review round needed per the round's explicit instruction):
1. **[B4-9]** e2e count **12 → 13** in both T5.4 and T7.2, with `helpers/page-check.js` named
   explicitly (with `-r`, `git ls-tree` flattens `helpers/`).
2. T2.7's `.commandcode/` clause now carries the "if present" hedge the round noted was missing.
3. T5.4's attribution corrected — R3 live-verified the *ours*-side; the frontend side was
   independently re-verified against the remote tip at R4.
4. T6.4 "graphically never" → "explicitly never".
Round 5 remains unspent. **All 4 batches frozen — change is ready for `/sdd-apply`.**

---

## Batch 4 Round 4 — 2026-10-06

**Reviewed:** `tasks.md` (post–Round 3 fix pass: [B4-7] T5.4 pre-check + T7.2 re-enumeration; [B4-8] T1.1 live-truth note; T6.4 `|| true` + dash; T2.7 hedge). **Final batch — a PASS here freezes the change for apply.**
**Baseline:** frozen `proposal.md` (criterion 2 as surgically unfrozen; criterion 8 supersession), frozen `design.md` (B2R5 + §8 sentinel soft-freeze), frozen `specs/merge-conflict-resolution/spec.md` (B3R2), `explore-brief.md` §5, `AGENTS.md`
**Review-runtime constraints (audit note):** no shell in this reviewer runtime. Tracked-vs-untracked calibrated via `.git/index` fixed-string greps — **recalibrated this round**: known-tracked `RouteGateMatrixTest` → tool error; known-absent sentinel → clean no-match. Contract: **tool error = matched; no-matches = absent**. Frontend-branch content verified first-hand via the **GitHub API against the exact commit** (remote tip == `35a51d18` == local ref).

### 🔴 Critical Issues

- **None.** [B4-7] closed for real: ours-side (solitary tracked file — index + worktree) and theirs-side (12 specs + `helpers/`, no `auth-wiring.spec.js` — remote tip == local ref, so the API listing is local-ref ground truth) both first-hand verified; the load-bearing overlay-semantics claim is correct git behavior, consistent with the scratch-repo empirical test. Post-checkout `auth-wiring.spec.js` keeps an unchanged index entry → **no status line**, exactly as T7.2 records. The "divergent copy" scenario is dead — the two path sets are disjoint by verified fact, not by assertion. [B4-8] honestly encoded; substrate confirms one trigger (9d87a79→2982ce83) and **no further movement**.

### 🟡 Should Fix

- **[B4-9] The e2e staging count is 13, not 12, in both places the count appears.** Frontend side = **12 top-level specs + `helpers/page-check.js` = 13 paths**; with `-r`, `git ls-tree` flattens `helpers/` away. Fix in T5.4 and T7.2 (declarative one-number soft-freeze; mirrors [D1]–[D3]/[B4-8] precedents — no re-review round needed).

### ✅ What's Done Well
- [B4-7] fixed at substance — recorded as a re-run-for-drift pre-check, airtight reasoning chain, overlap-emptiness independently verified against the remote tip.
- [B4-8] predicts its own trigger ("verify, don't assume"); `2982ce83` is pushed, so the refresh introduces no unpushed-work hazard.
- T2.7's timing exact; `playwright.config.ts` correctly absent from every enumeration.
- Safety rules all survive: T2.1 exactly-6-or-abort ✓; T6.4 `pkill -f "artisan serve" || true` with the `-9 php` prohibition ✓; T7.5 no-push-without-authorization ✓.
- Spec S1–S7 ↔ task coverage one-to-one; criterion-8 gates at honest windows.

### 💡 Optional
- T2.7's claimed ".commandcode/ if present" hedge isn't in the text — fold one word in.
- T5.4's "Batch 4 R3 verified these facts live" over-credits R3 (which verified ours-side only) — reword to "recorded in the B4-R3 fix pass".
- "graphically never `pkill -9 php`" → odd word; "explicitly never" reads better.

### ⚖️ Verdict

**PASS — Batch 4 (final batch) FROZEN for apply.** No 🔴 outstanding across all four batches. [B4-9] + optionals land as a soft-freeze edit before/during `/sdd-apply` with a one-line non-counted note. At apply-time honor T1.1 as written: expect abort-and-report to trigger (HEAD has moved to `2982ce83`), refresh the pin, re-derive merge-tree, re-check T1.2.
Round count: Batch 4, 4 of 5 (Round 5 unspent). **All 4 batches frozen — the change is ready for `/sdd-apply`.**

---

## Batch 4 Round 2 — 2026-10-06

**Reviewed:** `tasks.md` (post–Round 1 fix pass: B4-1…B4-5 + the review-entry misplacement cleanup)
**Baseline:** frozen `proposal.md` (criterion 2 as surgically unfrozen), frozen `design.md` (B2R5 + §8 sentinel soft-freeze), frozen `specs/merge-conflict-resolution/spec.md` (B3R2), `explore-brief.md` §5, `AGENTS.md`
**Independent execution this round (verbatim, live pre-merge tree):** `grep -c "Supersedes MPU-3133-specific" CodingMAIN.md` = **1** (:247); parity grep = **7** (:31/:37/:43/:55/:67/:73/:79); sentinel `Pending (Self), Reserved (Other), and Occupied` = **1** (:251); `tasks.md` grep for `## Batch 4 Round 1` = **0** (only `review-log.md` matches — process error confirmed self-caught); design §8 rows 2/8 read raw (design.md:159/:165); spec S6 symbol list read; `page-changelogs/backend-automated-by-ai.md` existence confirmed.

### 🔴 Fixed

- **[B4-1] RESOLVED.** T6.6 now carries `grep -c "Supersedes MPU-3133-specific" CodingMAIN.md` == 0, annotated with why it matters. Matches the frozen design §8 criterion-2 gate verbatim. Executed first-hand: **1 pre-merge at :247** — a discriminating gate that dies exactly when H9 takes theirs.
- **[B4-2] ordering RESOLVED (option b).** T7.2 = status gate before T7.3's append; T7.3 honestly marked *not traceable to a frozen artifact, deliberate housekeeping per the standing rule*. The append no longer contaminates the criterion-8 window. **But see [B4-6]: the gate fails at T7.2's position for a different, pre-existing reason the reorder made explicit.**
- **[B4-3] RESOLVED.** T3.1 says **13**; names match spec S6 one-for-one, all verified as live functions in B3R2.
- **[B4-4] RESOLVED.** T3.4's rewording is honest: 7 only pre-T2.2; 8 at T3.4's position. Reproduced first-hand.
- **[B4-5] RESOLVED.** T6.8 content-survival primary; `≈ 5` demoted to derived+pre-verified, never hard-asserted — consistent with spec S7's B3-4 form.
- **Process error cleaned.** `tasks.md` ends at T7.5, zero review headings; `## Batch 4 Round 1` lives only in `review-log.md`.

### 🟡 Addressed

- Optionals: T6.4 "graphically"; T6.5's belt-and-braces note (covers S3's four-fields + `class_exists` functionally).

### 🔴 Outstanding

- **[B4-6] T7.2's criterion-8 gate cannot hold at its own position.** At T7.2 (after Tasks 4–5) the tree legitimately contains, by plan design: `M StudentMyTimetable.php`, `M RouteGateMatrixTest.php`, `?? BACKEND-TASKS.md`, `?? ReplacementRequestsSeeder.php`, staged `A tests/e2e/**` — ≥5 violation entries against "nothing else" → deterministic false failure. The fix pass's own annotation ("stays true inside its own window") was false: the window was authored for the **immediately-post-merge** position (design §8 row 8 = "After merge").
  **Fix (orchestrator chose: option (a)):** run the narrow criterion-8 gate at its honest position — **new T2.7**, immediately after T2.6's merge commit, where "nothing else" is true — and **re-scope T7.2** into an enumerated expected-outputs assertion under the senior frozen wording ("plus **this change's own files**", `proposal.md:84`); no design §8 soft-freeze needed because the frozen row's "After merge:" already describes T2.7's position.

### 💡 Optional

- T6.4's quoted foot-gun lost its dash (`pkill 9 php` → `pkill -9 php`); operative command unaffected. **Fixed in the same pass.**
- T7.2's enumeration also mis-listed `?? playwright.config.ts` (it is tracked + unmodified, hence no status line — T5.5 asserts it); corrected during the fix pass.

### ⚖️ Verdict

**FAIL — 1 🔴 Outstanding ([B4-6]).** Round count: Batch 4, 2 of 5.

## Verify pass — sdd-verify — 2026-10-07

**Mode:** apply-complete verify (all 4 artifacts frozen; implementation committed at `e686e3c`). Run via the sdd-reviewer subagent (self-run per user instruction).

### Criteria walkthrough (proposal rows 1–8)

- Row 1 (`/replacement-history-ui` 200): **PASS** — reviewer re-ran live probe: route registered (bogus control route 404s); key at `routes/web.php:49` with all 4 §4.1 fields; authenticated smoke 200 + `class="nav-item active" href="/replacement-history-ui"`.
- Row 2 (6+7 resolutions): **PASS** — all sentinel greps re-ran by reviewer: H4-ours 1 (:169) / H4-theirs 0; 3-state 1 (:251, Harel citation intact) / ours-4-state 0; FR4.7 gloss 0; 3 changelogs newest-first (10-02 @:3, 08-31 @:10); old-name file absent; `todo-list.md` both sides (633eeb3's TASK-004 lines :173/:188 + upstream TASK-006 :220–237); `ui-common.js` all 13 S6 symbols as `function` definitions.
- Row 3 (8 changelogs both sides): **PASS** — 5 auto-merges on T3.2's recorded −0 diffs; both-side presence re-verified first-hand.
- Row 4 (old-name file deleted): **PASS** — glob absent.
- Row 5 (gates green): **PASS (recorded)** — phpstan `errors:0`, phpunit `105/105 (458)`, lint adminer-only baseline.
- Row 6 (3 extractions + playwright.config.ts): **PASS** — `BACKEND-TASKS.md` (608 ln), `ReplacementRequestsSeeder.php` (117 ln), `tests/e2e/` = 13 frontend paths + untouched `auth-wiring.spec.js` = 14 on disk; `playwright.config.ts` present.
- Row 7 (pre-merge gate): **PASS (recorded)** — 0 collisions, 7 local × 114 incoming; reflog confirms no concurrent fixups between tag and merge.
- Row 8 (post-merge porcelain): **PASS (recorded + corroborated)** — matches T7.2 enumeration exactly; reflog shows zero commits after the merge, no push.

### 🟡 Gate-neutral observations (no action required)

- **[V-1]** Unauthenticated live behavior re-measured = 200 (login-page HTML) vs recorded `302 → /login/student` — both auth-gated, never 500; no frozen criterion asserts the unauthenticated status code. If a future change ever gates unauthenticated behavior, re-pin against reality.
- **[V-2]** §13 debt sites shifted by theirs-taking hunks above them (:46→:47, :53→:54, :145→:146, :398→:401, :414→:417) — S7's "coordinates may shift" anticipated this; `reserved` case-insensitive count = 6 (matches T2.3/T6.8's falsified-forecast note). `:146`'s 4-value enum remains the sharpest residual for the follow-up change.
- **[V-3]** H14's FR-2.1 row wording differs trivially from design §4.2.1's quoted shorthand — union semantics preserved, no grep gate affected.
- **[V-4]** Tool gates (phpstan/phpunit/lint/pre-merge/porcelain/scope-scan) accepted as recorded truth (reviewer runtime has no shell); each corroborated indirectly. Orchestrator already re-ran all of them first-hand this session.

### Scope discipline

`e686e3c` = merge commit with the prescribed S6 message; boundary-content probes unchanged (`2026_08_03_000006:31` CHECK intact, 9 `dataset/*` present, `auth-wiring.spec.js` intact); the recorded porcelain pins the declared surface.

### ⚖️ Verdict

**PASS — no 🔴 outstanding.** Safe to proceed: commit the change's own `.sdd/` files → T7.3 housekeeping → `/sdd-archive` → report (no push).

## 🟡 Settlement — post-verify housekeeping — 2026-10-07

All four verify-pass 🟡 observations explicitly settled (user-requested); none converts into a 🔴.

- **[V-4] CLOSED — re-run first-hand at final HEAD `0201ea8` (post-archive):** lint:check flags only `public/adminer.php`; phpstan `{"result":"passed","errors":0}`; phpunit `{"result":"passed","tests":105,"assertions":458}` — all three byte-identical to the recorded truth. Zero reliance on recorded numbers remains.
- **[V-1] CLOSED — root-caused + re-pinned:** both measurements were the same behavior from two angles. Unauthenticated `GET /replacement-history-ui` = **302 → `/login/student`** (curl, no-follow); with `-L` (fetch-follows-redirect semantics) the same request reports **200** at effective URL `/login/student`, body = login page (17312 B ≈ reviewer's 17310). Mock-gate inference: if a future change ever asserts unauthenticated behavior, prefer the **no-follow status + redirect target** as the canonical form.
- **[V-2] PARKED — confirmed already ledgered:** design §13 rows 1–2 — the 3 surviving slot-state `reserved` contradictions (:47 §3 row, :54 grey line, **:146 §4 domain-model 4-value enum**, the sharpest) and the `pkill -9 php` pair (`AGENTS.md:37` + `CodingMAIN.md:108`) both belong to the **same doc-repair follow-up change**; :398/:417 are legitimate UI semantics (grey = another lecturer's pending request), exempt. Line-shifts from :46→:47 etc. anticipated by S7.
- **[V-3] CLOSED — no action by design:** union semantics preserved; no grep gate exists for the shorthand.
