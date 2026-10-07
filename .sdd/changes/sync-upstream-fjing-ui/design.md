# Design — sync-upstream-fjing-ui

**Batch 2 of 4** · Created 2026-10-06
**Baseline (frozen):** `proposal.md`, `explore-brief.md` (§5 corrected), `AGENTS.md`
**Ground truth input:** read-only trial merge `git merge-tree --write-tree HEAD upstream/fjing` → tree `2133c236`, run 2026-10-06. All hunk coordinates and quotations below come from that tree, not from inference.

---

## 1. Execution sequence (ordered, each step gated)

| # | Step | Gate before next step |
|---|---|---|
| S1 | Assert git state: `HEAD == 9d87a79` (fails fast if the concurrent session committed again), `upstream/fjing == 36c4d2c`, worktree clean of tracked mods outside `.sdd/changes/sync-upstream-fjing-ui/` | Abort if HEAD ≠ 9d87a79 — the analysis is pinned to it |
| S2 | **Pre-merge gate** — see §2 | **Abort and report** on non-empty intersection |
| S3 | **Fresh rollback tag** `backup/pre-merge-<HHMMSS>` at HEAD — the existing `backup/pre-fjing-merge` is stale and **must not be used** (resets destroy the concurrent session's 4 commits) | Tag exists and points at HEAD |
| S4 | `git merge --no-ff upstream/fjing` | 6 conflicts expected; anything else = abort + `git merge --abort` |
| S5 | Resolve per §4 | Every criterion-2 resolution verifiable |
| S6 | `git commit` the merge (message: `merge: upstream/fjing 36c4d2c — ReplacementHistory rename + 2026-10 sdd sweeps`) | None — recorded for traceability |
| S7 | Verify the 7 auto-merged paths per §5 | Each check passes |
| S8 | 2 post-merge edits, §6 | grep finds exactly the expected text |
| S9 | Extract 3 artifacts, §7 | Files exist; `phpunit.xml` not blocking the new seeder |
| S10 | Run gates, §8 table | All green |
| S11 | Route smoke test `GET /replacement-history-ui` → 200 | 200 |
| S12 | `/sdd-verify`, then `/sdd-archive` | — |

**Mid-merge abort procedure** (any unresolvable conflict, or S1 state drift): `git merge --abort` — never `reset --hard` mid-conflict, never `-f`. If the tree is already dirty from resolution work, `git checkout --merge` on individual files or `git merge --abort` after `git checkout -f .` is **forbidden** (destroys concurrent session's uncommitted work); report instead.

## 2. Pre-merge gate procedure

```bash
gh api "repos/FjingXR/class-replacement-system/compare/f44cc5c...36c4d2c" \
  --jq '[.files[].filename]' > /tmp/opencode/incoming.json
git status --porcelain | sed 's/^...//' > /tmp/opencode/local.txt
python3 - <<'PY'
import json
inc=set(json.load(open('/tmp/opencode/incoming.json')))
loc=[l.strip() for l in open('/tmp/opencode/local.txt') if l.strip()]
hit=[p for p in loc if p in inc]
print('COLLISIONS:', hit if hit else 'NONE')
raise SystemExit(1 if hit else 0)
PY
```

Non-empty intersection → **abort and report to the user**. Latest recorded run: 0 collisions across 4 local paths.

## 3. Ground truth contract

`merge-tree` is the single source for *which* paths conflict. Any prior expectation (the "12") that disagrees with it is **superseded without discussion** — see review-log unfreeze entry.

## 4. Conflict resolution — 6 files, 18 hunks

### 4.1 `routes/web.php` — 1 hunk (merged-tree lines 94–116)

**Keep OURS.** Upstream wrote explicit per-route closures; we keep the `$uiPages`-array + `foreach` loop, per explore-brief §5.1.

Then, *within* `$uiPages`, apply two keyed edits:

| Key | Action |
|---|---|
| `'/upcoming-replacements-ui'` | **Delete** — the route upstream renamed |
| `'/replacement-history-ui'` | **Add** with exactly: `'component' => 'App\Livewire\ReplacementHistory'`, `'legacy' => 'ui-design-templates.replacement-history-UI-design-template'`, `'nav' => 'replacement-history'`, `'mw' => ['auth', 'role:student']` |

Safe because `routes/web.php:88` `class_exists('App\Livewire\ReplacementHistory')` is false → closure falls to `$page['legacy']` view, never a 500.

⚠ **Upstream's `Route::get('/replacement-history-ui', function(){...})` lines from *their* side of the hunk are NOT merged in** — they're inside the conflict region we're resolving away.

### 4.2 `CodingMAIN.md` — 14 hunks

Coordinates below are the **merged-tree** line numbers. Where a rule deviates from explore-brief §5.1's coarser "theirs", the deviation is named.

| # | Merged lines | Resolution | Why |
|---|---|---|---|
| H1 | 37–40 | **theirs** | Objective-4 row; upstream's `"create/submit/cancel own — FR 4.13"` refine |
| H2 | 81–84 | **theirs** | Notification list; upstream's re-order + "via DB-backed queue" matches FR 4.16 |
| H3 | 154–157 | **theirs** | Superset — ours plus crossref "see §3 state-machine note re FR 4.11" |
| H4 | 181–192 | **⚠ OURS** — deviation from explore-brief "§6 theirs" | **RBAC semantic, proven against the FR source (not merely our §3):** real `../final/FR&NFR.md` FR 1.3 and FR 1.4 both read *"…for their cohort"* and FR 2.15 reads *"own"* — while theirs grants `View global replacement history ledger \| ✅ \| ✅ \| ✅`, i.e. *student × global*, contradicting all three plus the FR 1.2 scoping gloss our repo carries at `CodingMAIN.md:166` (§6 permissions matrix — `../final/FR&NFR.md` is only 110 lines, so "line 166" cannot live there). Also verified: upstream's `replacement-history-UI-design-template.blade.php` reads from `window.MockData` (5 references) — a UI mock, so theirs' matrix row documents aspiration, not enforced behaviour. Keep ours' cohort-scoped row |
| H5 | 207–211 | **theirs** | Latest sync note 2026-10-01, "48 FRs / 26 NFRs" |
| H6 | 218–233 | **theirs** | §7.1 traceability table, resynced |
| H7 | 253–256 | **theirs** | FR 2.3 adds "or cancelled" — a superset matching FR 2.16 |
| H8 | 272–277 | **theirs** | FR 3.1 refined with explicit inheritance scope (except 2.11/2.12) |
| H9 | 285–312 | **theirs — all 13 rows, no restores** *(Round 1 correction: this row previously asked to hand-restore our FR 4.7 + FR 4.11; both restores are rejected by evidence, see below)* | Verified against **`../final/FR&NFR.md`, the FR source of truth**: row 4.11 reads *(Available, Pending, and Occupied (Harel, 1987))*, the token `reserved` appears **nowhere** in the whole FR source, and `2026_08_03_000006_create_time_slots_table.php:31` has always forbidden a 4th state — `CHECK (status IN ('available','pending','occupied'))` — with **zero** uses of `'reserved'` in `app/`. Our 4-state row, our FR 4.7 supersession gloss, and our trimmed citations (4.8/4.11/4.12 drop `Kung & Robinson, 1981` / `Harel, 1987`) all diverged from the superset source; upstream's is the faithful sync. ⚠ Consequence: §3's own Slot State Machine still carries a `reserved` row → registered as debt in §10 |
| H10 | 324–327 | **theirs** | NFR 1.2 "less than 100 ms extra time beyond the database write" is measurable; ours "with no noticeable delay" is not |
| H11 | 334–343 | **theirs** | Same content compressed ("Eloquent ORM", bold `staff`/`student`, "CSRF") |
| H12 | 349–352 | **theirs** | NFR 3.4 names concrete destinations |
| H13 | 368–371 | **theirs** | NFR 7.1 allowlist, same thesis |
| H14 | 378–396 | **UNION — row-by-row** | §7.4 is *implementation status*, not spec. Both sides added disjoint rows. See §4.2.1. *(Counted under the governing clause, not a third override — criterion 2's "exactly two" refers to semantic overrides; H14 is a status union that rejects no upstream content.)* |

#### 4.2.1 `CodingMAIN.md` H14 — row-by-row union

| Row | Take | Detail |
|---|---|---|
| NFR 2.4 / 2.5 | **ours** | We have `EnsureSessionLifetime` implemented (✓); upstream's version is a pre-implementation re-statement of the same spec |
| NFR 3.4 | **ours** | Same destinations + ✓ state marked |
| NFR 3.5 / 3.6 | **theirs** | Upstream adds `ui-template.blade.php pre-CSS guard` detail ours lacks |
| NFR 5.1, 5.2 | **either** (identical) | Take theirs to minimize line count |
| NFR 5.3 | **ours** | Ours reflects reality — both services + `OCCResult` exist |
| NFR 1.2 | **theirs only** | New benchmark note, absent in ours |
| FR 4.16 / NFR 6.2 / 1.4 | **ours** | Same text + our "(Sprint 3)" suffix |
| FR 2.1 ⚠ pending | **ours only** | Prefix normalization pending — ours |
| NFR 7.1 | **theirs only** | Data allowlist, absent in ours |

### 4.3 `page-changelogs/my-request-history-changelog.md`, `…/replacement-home-changelog.md`, `…/request-approval-changelog.md` — 1 hunk each (merged-tree lines 3–19)

**Append both, theirs block first, then ours** — ordering verified: HEAD's changelog file is newest-first (`## [2026-08-31]…` at :3, descending), so their newer `## [2026-10-02] Empty state above summary…` block precedes our older `## [2026-08-31] Sync upstream/fjing UI refactor…` block. Both sides' `### Files Changed` lists are preserved inside their blocks.

Identical resolution in all 3 files, changes only material.

### 4.4 `page-changelogs/upcoming-replacements-ui-changelog.md` — modify/delete

`git rm page-changelogs/upcoming-replacements-ui-changelog.md` — accepts upstream's deletion, per explore-brief §5.3 (decision 4). The file is the *old* name's history; upstream's 2026-10-02 Replacement History change supersedes it.

## 5. Auto-merge verification — 7 paths, after S7

No conflict markers to fix, but intent to verify. Run-after-merge checks:

| Path | Check |
|---|---|
| `public/js/ui-common.js` | **highest priority.** Confirm `to12h`, `formatDate`, `toggleTheme`, `updateIcon`, `navigateHome`, the consolidated table/sort/pagination/urgency helpers all still exist — commit `0a62f1a`'s pages depend on these globals. Confirm the file is one coherent script (no duplicated block, no `<<<<<<<` left). **Never hand-reopen it** — if a helper is missing, that's a regression to report, not to patch inline |
| 5 × `page-changelogs/{cohort-timetable-ui,my-timetable,replacement-arrangement,student-my-timetable-ui,venue-timetable}` | Both sides' entries survive; latest first; count parity — assert `git diff <S3-tag>..HEAD -- <path>` shows our-side lines retained. ⚠ **Baseline is the S3 tag (merge-time HEAD), not `438bdfe`** — that ref predates the concurrent session's 4 commits (`633eeb3`..`9d87a79`), so diffing against it misattributes their edits to the merge and can false-fail a healthy merge |
| `page-changelogs/todo list/todo-list.md` | **Both-modified.** Our `633eeb3` changed 4 lines (appended/improved rollover items), upstream also edited. Confirm both edits survived. ⚠ This path contains a literal space — always quote |
| `routes/web.php` (post-merge parity) | **Route set parity — pattern derived from the file, and the *fenced* command below is executed verbatim, not a re-serialisation.** The array at `routes/web.php:30–85` has **9 keys** — 8 `-ui`-suffixed plus `/replacement-arrangement`, which sits *inside* `$uiPages` at `:61–66` and is outside the `-ui` pattern by construction (`RouteGateMatrixTest.php:35` merely mirrors it in the test's 9-route `ALL_ROUTES` const). Expectation: **7 pre-merge** (`upcoming-replacements-ui` still present), **8 post-edit** (the whole 7→8 delta is `/replacement-history-ui`'s addition — `upcoming-replacements` is not in the alternation, so its deletion is count-neutral). Run *after S8*'s keyed edits. Post-merge assert `= 8`; a lower count means the `$uiPages` merge dropped keys silently |

```bash
# B2-16: bare pipes, NOT \| escapes — \| renders fine in GFM but breaks verbatim
# copy-paste execution (bash collapses \\ -> \ -> GNU ERE literal pipe -> 0 matches).
# The resolution column above describes the gate; this fence is the executable form.
# Execute verbatim; record the count in review-log at S7/S8.
grep -cE "^\s+'/(my-timetable|cohort-timetable|student-my-timetable|replacement-history|replacement-home|my-request-history|venue-timetable|request-approval)-ui' =>" routes/web.php
# expected: 7 pre-merge, 8 after S8's replacement-history-ui addition
```

## 6. Post-merge edits — 2

| File | Change |
|---|---|
| `app/Livewire/StudentMyTimetable.php:77` | `navItems` → point at `replacement-history` (was `upcoming-replacements-*`) |
| `tests/Feature/RouteGateMatrixTest.php:20,33,41` | route-gate expectations for the renamed route. ⚠ The test uses **hardcoded** `const` arrays (`STUDENT_ONLY` / `LECTURER_ONLY`, `:29–50`) — it is *not* driven by `$uiPages`; the three literal edits at `:20`, `:33`, `:41` **are** the mechanism. Do not expect a data-driven test to pick up route changes automatically |

Verified in explore-brief §7: upstream already handles `student-my-timetable-UI-design-template.blade.php:6` and `mock-data.js` comment ("renamed from upcoming-replacements-ui 2026-10-06") — nothing to do for those.

## 7. Extraction from `origin/fedora-frontend` — 3 artifacts

Source branch: `origin/fedora-frontend` (fetch first if needed: `git fetch origin fedora-frontend`).

| Artifact | Destination | Method |
|---|---|---|
| `BACKEND-TASKS.md` | repo root | `git show origin/fedora-frontend:BACKEND-TASKS.md > BACKEND-TASKS.md` |
| `ReplacementRequestsSeeder.php` | `database/seeders/` | `git show origin/fedora-frontend:database/seeders/ReplacementRequestsSeeder.php > database/seeders/ReplacementRequestsSeeder.php` |
| `tests/e2e/**` | `tests/e2e/` | `git checkout origin/fedora-frontend -- tests/e2e` |

**`playwright.config.ts` — NOT taken from `fedora-frontend`.** Our fork's version at HEAD is what stays.
Verified at Batch 2 Round 1: `playwright.config.ts` is **absent** from `origin/fedora-frontend` (tree `35a51d1`) — so there is nothing to reject and criterion 6's "ours retained" is satisfied by construction. `tests/e2e/api-wiring.spec.js` confirmed present, so the checkout is non-empty.

## 8. Verification matrix (proposal criterion → how)

| Criterion | How |
|---|---|
| 1 `/replacement-history-ui` = 200 | `curl http://localhost:8000/replacement-history-ui` after S11 restart; expect `200` and route renders with the `replacement-history` activeNav |
| 2 — 6+7 file resolutions + the 7 auto-merged files honoring intent (§5's checks pass) | Per-row grep against this design: `routes/web.php`: `grep -c "App\\\\Livewire\\\\ReplacementHistory"` ≥ 1. **H9 (3-state, theirs wholesale):** `grep -c "Available, Pending, and Occupied"` ≥ 1, and **ours' 4-state phrase must be gone** — `grep -c "Pending (Self), Reserved (Other), and Occupied"` = **0** (sentinel corrected Batch 3 R1: the old `Reserved (Self)` token could never match — `(Self)` belongs to `Pending`; declarative soft-freeze, no decision change) and `grep -c "Supersedes MPU-3133-specific"` = **0**. **H4 (ours wins):** `grep -c "cohort-scoped, FR 1.3–1.4"` ≥ 1 and `grep -c "View global replacement history ledger"` **== 0**. 4 `page-changelogs` files contain both sides' entries |
| 3 — 8 `page-changelogs/*` entries both sides present, none dropped | Diff each of the 8 against the **S3 tag (merge-time HEAD)**, never `438bdfe` — that ref predates the concurrent session's 4 commits (`633eeb3`..`9d87a79`), so diffing against it misattributes their edits to the merge → false gate failure. Assert: lines not removed |
| 4 file gone | `test ! -f page-changelogs/upcoming-replacements-ui-changelog.md` |
| 5 gates | Exact commands in criterion 5, incl. `--memory-limit=1G` bug-guard |
| 6 artifacts + playwright | `ls BACKEND-TASKS.md`, seeder, `tests/e2e/`; `git diff --stat 9d87a79..HEAD -- playwright.config.ts` empty |
| 7 pre-merge gate | Recorded in S2 execution; only passable if run before merge |
| 8 post-merge `git status` | After merge: only `.commandcode/` untracked + this change's own `.sdd/` files modified; **nothing else** (8 foreign committed upstream-theirs are now tracked) |
| 9 verify + archive | Result of the run itself |

## 9. Rollback design

```bash
# On failure before committing the merge
git merge --abort
# After S6 commit, to undo the whole merge
git reset --hard <fresh-tag-from-S3>
```

Stale `backup/pre-fjing-merge` → `438bdfe`: **do not reset to it.** The fresh tag from S3 is
the rollback point instead — one action, and it keeps the older tag untouched by accident.
Recoverability is *not* in question (the earlier wording here contradicted itself and is fixed):
verified at Batch 1 Round 5 that the concurrent session's 4 commits are pushed to **both**
remotes — `origin/fedora-backend == local/fedora-backend == HEAD == 9d87a79` — so even a
destructive reset recovers them via `git fetch <remote>` + reset, never via the stale tag.

## 10. Downstream impact (deferred) — decision-level, out of scope here

- **`UpcomingReplacements` → `ReplacementHistory` rename** — frozen `wire-backend-into-refactored-ui` still names `UpcomingReplacements` at `design.md:31,52`, `specs/rbac-route-gating/spec.md:11,22`, `tasks.md:42`. That's **3-batch unfreeze + re-review** before any Wave 3 apply.
- **`auth-wiring` disposition** — active local work, 58 lines of tasks added; min `tasks.md` growth (B1-8's sibling concern upstream may have un-archived on purpose, since `prompts/sdd-propose-ui-page.md` references active work)
- ⚠ **Dangerous command lives in 2 files, not 1:** `AGENTS.md:37` **and** `CodingMAIN.md:108` (identical `pkill -9 php`). Both must be amended in the follow-up change; fixing only one leaves the dangerous line in the project's single-source-of-truth doc. Recorded in proposal Risks.

## 11. Required `CodingMAIN.md` §10 sections

| Section | Content |
|---|---|
| **Promoted to shared** | **None by this change.** This merge introduces no new UI markup of ours. Upstream's 6 archived UI pages may contain promotions; re-auditing them is out of scope (they're already merged). Note the one *indirect* promotion risk: `page-changelogs/todo list/todo-list.md` both-modified |
| **Mobile view** | **N/A** — no UI page authored here. Upstream's change may carry mobile work; not verifiable screen-by-screen in this change (SDD scope is the merge mechanics, not UI QA) |
| **Toast/undo bar** | **N/A** — no new toast wiring |
| **UI Design Rules §10.0 (colors/naming)** | Not violated: nothing new is drawn. The only risk was `ui-common.js` (both-modified), and §5 verifies its shared-helper globals survive intact |

## 12. Roles & boundaries

| Who | Task |
|---|---|
| This change | merge mechanics, conflict resolution, post-merge edits, extraction, verification |
| **Not** this change | `database/migrations/*` edits, `dataset/*` edits (other session), ERD redraw (other session's item 5), pushing to `upstream`, or pushing the merge to GitHub without explicit user authorization (AGENTS.md: never push unless asked) |

## 13. Debt registered from this change (follow-up work — do **not** absorb here)

| Debt | Evidence | Why not fixed here |
|---|---|---|
| **`CodingMAIN.md` disagrees with the DB CHECK on `reserved` — 6 sites, and §3 is only one of them.** | Case-insensitive sweep of our HEAD `CodingMAIN.md` lists: **:46** (§3 slot-state row), **:53** (§3 grey-legend line), **:145** (§4 domain-model `time_slots` row — the sharpest residual: its `status` enum *explicitly lists `available/pending/reserved/occupied`, the exact 4th value `2026_08_03_000006:31` has always forbidden), **:251** (§7.2 FR 4.11 — *fixed by H9* taking upstream's 3-state), **:398/:414** (§10.0 colour legend, `grey=reserved/neutral` + `Reserved by Others` — legitimate UI semantics for "another lecturer's pending request", not a slot state). The "outside every hunk" check design.md ran for §3 applies to :53/:145/:398/:414 identically — all four survive untouched, so the surviving slot-state contradictions are **3** (:46, :53, :145); :398/:414 are legitimate UI semantics (describing "another lecturer's pending request", not a slot state) | §3–§4 edits would expand this change's scope past what Batch 1 froze. Phase 3/4 code is unaffected — zero `reserved` uses in `app/` or migrations, documentation-only |
| Dangerous `pkill -9 php` at `AGENTS.md:37` *and* `CodingMAIN.md:108` | Both verified live; 6 FPM processes active | Follow-up change per Batch 1 decision |
| `wire-backend-into-refactored-ui` still names `UpcomingReplacements` (`design.md:31,52`, `specs/rbac-route-gating/spec.md:11,22`, `tasks.md:42`) | Frozen artifact | 3-batch unfreeze + re-review before Wave 3 |
| `auth-wiring` disposition (active local work, 58 lines added) | `git diff f44cc5c..HEAD` | Deferred to Wave 3 input |
| `H5`'s "48 FRs / 26 NFRs" claim | **Verified TRUE** (Batch 2 Round 2 audit): 85 numbered rows = 48 FR (9+16+7+16) + 26 NFR (4+6+6+3+3+1+1+2) + 11 `x.0` headers — exact match | *(no follow-up needed — converted from debt to a verified note)* |
