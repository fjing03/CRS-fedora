# Review log — arrangement-earliest-week-default

## Batch 1 (proposal + design + tasks, condensed single round) — Round 1 — 2026-10-20

Reviewer: pi subprocess (glm-5.3-flash; full output in session). Verdict:
"no 🔴 blockers; ready to move into implementation conditional on the
four 🟡 fixes — cheap, declarative, a single fix pass suffices."

### 🔴 Fixed
- (none this round — the round found no blockers)

### 🟡 Addressed
1. **Stale line anchors** repointed to the reviewer's live-file counts
   (post-674c862): `weekNav.load()` :3507, bridge write :3514,
   `populateWeekSelect` :3515, pinned `selectWeek` :3526,
   `applyUrlParams` :3537, `__bootDone` :3541, `buildTimetable` :3554
   (explore-brief's fact map + design D1/D5).
2. **The D1 "After:" sample replaced by the collapsed single-write
   shape** (the draft's redundant `_weekFilter(ebWeek.week)` re-test +
   the `_currentWeek === null` no-op ternary retracted; the frozen
   `else if (!hadSavedWeek) { const eb = firstBookableDay(weekData); if
   (eb) weekNav._currentWeek = eb.week; }` is now the design's ONLY
   code sample — no implementer ambiguity).
3. **The spec's memoryless premise hardened**: rows enforce memoryless
   via an add-init that deletes `arrangementWeek` + `currentWeek`
   (the storageState could re-seed localStorage in a future auth setup —
   never inherited, always deterministic).
4. **No hardcoded saved week '9'**: row 2 derives a runtime-filtered
   bookable index (≠ the default's), seeds, reloads, asserts the saved
   week wins — immune to `load()`'s filter-resolve-away.

### 💡 Addressed
- "post-T6 storage holds only click-driven positions" softened to
  "user-arrived or pinned positions" (a pinned arrival saves too — the
  honest shape of when the default can fire).
- The `var` justification de-smugged (the real reasons: reassignment in
  try/catch + pre-load declaration; `const eb` frozen in-branch).
- T4 pins the exact Playwright CLI (no full-suite interpretation).
- D2 names the garbage-saved-value edge (`load()` ignores NaN; week 1
  stands; the default deliberately stays off — there WAS a memory).

### 🔴 Outstanding
- None — the four named 🟡 + the four 💡 are folded; frozen per the
  reviewer's own verdict condition ("a single fix pass suffices; no
  full-chain re-review").
