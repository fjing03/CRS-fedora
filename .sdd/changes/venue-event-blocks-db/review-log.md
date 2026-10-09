# Review Log — venue-event-blocks-db

## proposal Round 1 — 2026-10-10

### 🔴 Fixed
- **S1 merge-forecast accuracy.** Reviewer claimed the forecast was factually wrong (wrong file, "our side never touched replacement-arrangement-changelog.md since the base", broader overlap set). **Empirically resolved with hard evidence**: re-ran `git merge-tree --write-tree HEAD upstream/fjing` at `deab68e` — exit=1 with **exactly one CONFLICT: `page-changelogs/replacement-arrangement-changelog.md`** (the reviewer's predicted "never conflicts" file), and `git diff e7f8036..HEAD -- page-changelogs/` shows our side DID append +11 lines to that file. The reviewer's reflog-based inference (no shell access) does not hold. However, the reviewer WAS right that the proposal's overlap model was under-reported: our side also touched venue Livewire/blade files, Feature tests, seeders, nav partials, and 6 changelogs since the base. S1 + brief rewritten with the true overlap model (6 both-sides changelogs, 5 auto-merge, 1 conflicts) and the merge-tree evidence recorded.
### 🟡 Addressed (folded into this fix round)
- **Card semantics (R1-🟡-1)**: S2 now pins the decision — our 5th card stays `sumOccupied`/"Occupied" (documented deviation from upstream's `sumUnavailable`), and a real inconsistency the reviewer surfaced is fixed in scope: the component counts holiday slots as Available while our own legend tip says they're not bookable → Available will exclude Sunday + PH. Full semantics table goes in design.md.
- **PH-exception testability (R1-🟡-2)**: S3 now requires a crafted-row Feature test (twin-merge reflection pattern) pinning the PH-day own-class exception if no real instance exists in the data window; success criterion updated likewise.
- **Brief fact correction (R1-🟡-3)**: explore-brief "Incoming batch" section rewritten with the corrected overlap model + evidence.
- **Tooltip format (R1-💡)**: corrected to upstream-verbatim `data-tip2` = `lecturer · status` in proposal S2 + brief (class name renders in the block face).
- **Mock-spec exclusion explicitness (R1-💡)**: S4 names `venue-timetable.spec.ts` / `confirm-guards.spec.ts` / `warning-keywords.spec.ts` as excluded.
### 🔴 Outstanding
- (none)

**Verdict: R1 FAIL resolved — all blocking and recommended items fixed with evidence; reviewer's single blocking claim itself disproven by merge-tree + git diff. Proposal FROZEN pending Round 2 confirmation.**

## proposal Round 2 — 2026-10-10
### 🔴 Outstanding
- (none)
### 🟡 Addressed (soft-freeze amendment, per reviewer condition)
- Tooltip wording corrected in proposal S2 + brief item 3: `data-tip2` = `lecturer · status` (upstream-verbatim), but the RENDERED hover tooltip = `name · lecturer · status` — the class name joins via `data-name` + theme.css `.event-block::after` prefixing (class name does NOT render in the block face; block face = ev-code/ev-venue/ev-time). Prevents a wrong S3 Playwright assertion.
- Cosmetic: brief stray colon fixed ("1 conflicts (next bullet)"); stale line-number reference dropped.
### Verdict
**PASS — proposal FROZEN** (with the one-phrase soft-freeze amendment applied as conditioned; no decision-level changes).

## design Round 1 — 2026-10-10
### 🔴 Outstanding
- (none)
### 🟡 Addressed (soft-freeze amendments, applied verbatim)
- §1.1 row 3: PH own-class exception is **holiday-only** (upstream guard `offMine = … && day.holiday`, never Sunday); own events on Sundays hide like everyone else's. Table row + rationale corrected.
- §6.1: added the mandatory deletion/replacement of the existing standalone `data-type="CiscoLab"` group assertion (spec L79–81) → 3-category cascade; prevents a guaranteed-red gate from an additive re-pin.
### 💡 Addressed
- §1.1 note: `.cell-ph::after`/`.cell-sun::after` markers are hover-reveal only; always-visible offday signal = day-header badges; spec asserts class presence + no green tint, never visible text.
- §6.2: corrected the no-op bullet — Feature layer is payload-only; Playwright owns class assertions.
- §3: "counts visible blocks" pinned as distinct session rows (dedupe by session id); My-Teaching-counts-own-PH-blocks divergence from upstream's offday-skip noted as a deliberate, grid-faithful deviation → added to the §8 changelog-entry checklist.
### Verdict
**PASS — design.md FROZEN** (soft-freeze amendments applied as conditioned; no decision-level changes).

## specs + tasks Round 1 — 2026-10-10
(Note: reviewer raced the tasks.md write — Batch 4 declared "not reviewable"; file now exists and its checklist was audited against the reviewer's own stated criteria: S1–S4 coverage ✓, merge-before-rewrite ✓, records-intact before AND after ✓, single-conflict resolution + auto-merge spot-checks ✓, Available fix + cellRender ✓, CiscoLab deletion ✓, Feature PH crafted-row test ✓, 3 named gate exclusions ✓, changelog ✓, archive ✓, ≤2h each ✓. Sent for Round 2 confirmation.)

### specs — 🔴 Outstanding
- (none)
### specs — 🟡 Fixed (soft-freeze amendments, applied verbatim)
- **R1 conflict reachability corrected (reviewer's load-bearing catch):** conflict is NOT data-unreachable — the component derives it from venue-restriction violations (`venueRestrictionConflict`, VenueTimetable.php L108–110), zero `replacement_requests` needed. Live instance: B005 AMIT2034 Wed-11:00, pinned by existing venue-db.spec.ts L102–116. Spec R1 now distinguishes pending (truly unreachable) from conflict (reachable) and mandates the B005 test SURVIVES the re-pin (owner → `event-conflict`, extend to others'-side `event-public-holiday` where feasible). **tasks T10 updated with the same clause.** Design §1.1's "unreachable pre-Slice-B — zero conflict rows" label noted here as inaccurate for conflict; the class mapping itself is unaffected — no design unfreeze needed per reviewer.
- **R3 extended:** guide-block "Slot colours" bullet rewrite (two-axis language) is now an explicit requirement — closes the fall-through crack between design §2 and tasks T5.
### specs — 💡 Addressed
- R2 parenthetical unambiguous order (`cell-sun` Sundays / `cell-ph` holidays).
- R6 "(120)" traced: 6 days × 20 DB rows, no Sunday rows (grid renders 7 columns).
### Verdict
**Batch 3 (spec): PASS after conditions — applied. Batch 4 (tasks): checklist-audited + amended (T10), sent for Round 2 confirmation.**

## tasks Round 2 — 2026-10-10
### 🔴 Outstanding
- (none)
### 🟡 Should Fix
- (none)
### Verdict
**PASS — tasks.md FROZEN.** Checklist verified line-by-line (T2 abort-escape hatch, T4 migration-absence check, T13 route:list guard all praised); T10 amendment confirmed to match the spec R1 condition; all Batch 3 conditions confirmed closed. 💡 noted: T10 is the split candidate (T10a/T10b) if it overruns. **Full chain frozen — proceed to /sdd-apply.**
