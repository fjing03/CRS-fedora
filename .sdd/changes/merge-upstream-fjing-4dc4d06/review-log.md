# Review log — merge-upstream-fjing-4dc4d06

## Batch 1 (proposal) Round 1 — 2026-10-08

Reviewer: sdd-reviewer subagent (self-run per standing instruction). Baseline: explore-brief.md; no frozen artifacts yet.

### 🔴 Fixed

- **[R1-1] Dropped execution-time pre-merge gate.** The proposal-invalidation guard only fired on *conflict*; a conflict-free newer tip (the other workstation pushes actively) would have merged unexplored content, and the predecessor's hard-won gate (SHA pin + preflight re-run + dirty-tree intersect, never stash) was missing. **Fix:** What step 2 now mandates the ordered 3-clause gate (2a `rev-parse` = `4dc4d06`, 2b re-run `merge-tree`, 2c porcelain ∩ delta-census = empty), merge by SHA `git merge --no-ff 4dc4d06`, plus a rollback protocol row (abort pre-commit; reset to `52e25ca` post-commit after tree-clean check).

### 🟡 Addressed

- **[R1-2] Criterion 5 under-scoped the smoke** (1 of 3 touched blades). Now enumerates all 3 blades with one visible assertion per delta theme, cross-checked against upstream's archived `2026-10-07-venue-event-blocks` design notes.
- **[R1-3] Safe serve-restart sequence** now carried in Risks (`pkill -f "[a]rtisan serve"`, never `pkill -9 php` — live-FPM-pool hazard), pending the out-of-scope doc-repair amendment.
- **[R1-4] "Two hunks-level-overlap files" wording** → "7 path-overlap files (one JS + 6 changelogs)".
- **[R1-5] phpstan gate** now spells the `--memory-limit=1G --no-progress` form (128M-crash precedent) so a memory error is never misread as a merge regression.
- **[R1-6] Supersession note** added to What step 1 (predecessor's "zero collision" claim was Wave-2-scoped; this change records the 7-path census).

### 💡 Reviewer arbitrations

- **Specs omitted — accepted.** Mechanical merge, zero authored behaviour; the proposal's own criteria table + "Expected post-merge tree state" paragraph do the exclusion work.
- Reviewer's optional suggestions on rollback row and supersession record: both applied.

### 🔴 Outstanding

- (none — re-review followed)

## Batch 1 (proposal) Round 2 — 2026-10-08

Reviewer: sdd-reviewer subagent (continuation, `ses_ee66a8b80ffeeXuct0ljYuqEut`).

### ✅ Verdict

**PASS — 0 🔴.** R1-1's gate confirmed complete: SHA pin + merge-by-SHA closes the moved-tip exploit; 2c's census-scoped dirty check and the never-stash rule are correctly scoped; all 5 delta themes trace into criterion 5; `tests/Feature/` vs `tests/` precision confirmed correct.

### 🟡 Hardening (applied in the same pass as freeze, per reviewer)

- **[R2-1]** Gate 2b preflights the **pinned SHA** (`HEAD 4dc4d06`), not the ref — closes the 2a→2b TOCTOU seam.
- **[R2-2]** `mock-data.js` source checks (real names + `currentUser` 5770 state) folded into What step 3 — criterion 3's greps now have a What-step owner.
- **[R2-3]** (optional, applied) Rollback asserts `HEAD^1 = 52e25ca` before reset; step 4 notes smoke scope per criterion 5.

**Proposal.md is FROZEN as of this entry.**

## Batch 2 (design) Round 1 — 2026-10-08

Reviewer: sdd-reviewer subagent (`ses_ee6667ddaffeD0T3VIMR4SZ0WU`). Frozen baselines: explore-brief + proposal.

### 🔴 Fixed

- **[D-1] Criterion 2 had no verification owner.** §1 now ends with the post-merge structural check: two-parent evidence (`git log --format=%P -1`) + `git diff --name-only HEAD^1..HEAD` vs census with **both** `comm -13` and `comm -23` empty (exact equality, per frozen "exactly"), failure routed to §6 rollback.

### 🟡 Addressed

- **[D-2]** §6 row 1 relabeled: G2a/G2c = STOP/ABORT in §1 (nothing to abort); `merge --abort` only for in-progress-uncommitted failures; row 2 explicitly owns post-commit gate failures.
- **[D-3]** Expected post-commit status defined exactly (untracked `.commandcode/` + this change's `.sdd/` only) before reset.
- **[D-4]** S1 grep now `grep -nE "window\.(…)"` (B2-16 bare-pipe convention) with concrete guard sentinels named (`window.currentWeek = currentWeek;` + three `typeof window.*` guards).
- **[D-5]** S2b added: hunk-parity check (every added line of their ui-common.js delta present in the base→HEAD added set) — silent auto-merge drops structurally impossible to miss.
- **[D-6]** S2 anchored with preflight evidence (their hunk adds `classList.add('event-conflict')` in ui-common.js); theme.css noted as additive.
- **[D-7]** 523-assertion number re-scoped: gate on 110/110 test count; 523 recorded as Wave-2 T6.2 provenance, drift = investigate-don't-fail.

### 💡 Optional (applied)

- §4 message wording "UI/docs/SDD-history + one Playwright spec"; census citation §1/§2; G2c paths under `/tmp/opencode/`; S5 fallback (derive selectors from delta diff) added; two-parent evidence folded into §1.

### 🔴 Outstanding

- (none)

## Batch 2 (design) Round 2 — 2026-10-08

Reviewer: sdd-reviewer subagent (continuation, `ses_ee65dfdb9ffe5gWCsAuT0TTnOw`).

### 🔴 Fixed

- **[D-8] S2b `comm` direction guaranteed a false positive on a CORRECT merge.** `comm -13 their head` would have flagged our Wave-1 `jumpToToday` guards (legitimately HEAD-side extras) as missing → spurious §6 rollback of a good merge. **Fix:** direction re-pinned as `comm -23 <their-added> <head-added>` = empty, with explicit role labeling, `sort` on both grep sets (comm's sorted-input requirement), and honest phrasing ("every added line present" — line-set parity, not hunk counting).

### 🟡 Addressed

- **[D-9]** §1's stale "only `.commandcode/`" expected-status claim corrected: now also names this change's own untracked `.sdd/` dir, matching §6's D-3 fix (no more false STOP on a valid pre-merge state).
- **[D-9-opt]** G2a now also pins HEAD (`rev-parse HEAD` = `52e25ca`) — symmetric state assertion, cheaper than relying on the post-merge `%P` probe alone.

### 🔴 Outstanding

- (none)

## Batch 2 (design) Round 3 — 2026-10-08

Reviewer: sdd-reviewer subagent (continuation, `ses_ee659f574ffegXvCR52UO8SDIz`).

### ✅ Verdict

**PASS — 0 🔴.** [D-8] verified by re-derivation: correct merge ⇒ empty `comm -23` cell (auto-merge copies upstream added lines verbatim); a dropped hunk surfaces its lines ⇒ failure. The false-positive-by-design case (our guards as HEAD-side extras) is structurally excluded — `comm -23` never prints file2-only lines. [D-9] §1↔§6 status expectations word-for-word aligned; G2a HEAD pin consistent with `%P`/`HEAD^1` probes. Known limitation recorded: `grep '^+[^+] '` misses added-blank-line-only hunks (negligible for JS; census covers path-level drops).

**Design.md is FROZEN as of this entry.**


## Batch 3 (tasks) Round 1 — 2026-10-08

Reviewer: sdd-reviewer subagent (`ses_ee65811d5ffe5QgLB3xTZCBiSv`). Full coverage map verified: every frozen criterion/What-step/design-§ has an owning task; D-8's `comm -23` direction and the safe serve sequence quoted verbatim; scope clean (Wave-3 guarded via T3.6, doc-repair absent, no-push pinned in T6.5).

### 🟡 Addressed

- **[T-1]** Selector-recording obligation made explicit: T3.5/T3.7 now require the S5-extracted selectors transcribed verbatim into execution notes.
- *(optional, applied)* "(failure → design §6 rollback row)" cross-references added to T4.4/T5.1; T5.1 carries the `/login/student` http-200 check.

### ✅ Verdict

**PASS — 0 🔴.**

**Tasks.md is FROZEN as of this entry. All 3 batches frozen — apply may begin.**

## Verify pass — sdd-verify — 2026-10-08

Reviewer-verifier subagent (self-run per standing instruction). Criteria 1–5 **PASS** with first-hand-corroborated evidence (merged ui-common.js read directly: guards :286–292/:525 coexist with their `event-conflict` else-if chain :759; mock-data.js :80 unchanged; blade :480/:482 pins match). Criterion 6 pending by workflow design. S2b parity (99 ⊆ 104, comm -23 empty) called out as the structural proof the merge is drop-free. T5's asset-level curl method **sanctioned** — frozen criterion 5's own evidence column is curl-based; Playwright is explicitly not a gate (frozen Risks row 4). **VERDICT: PASS — 0 🔴, 0 🟡.**

Optional notes recorded: pre-existing changelog duplication (venue :268–274, dated 2026-10-03 — predates delta, upstream-side cleanup); theme.css `.badge-public-holiday` pinned at :891.
