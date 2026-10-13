## proposal.md Round 1 — 2026-10-13

### 🔴 Fixed
- B1 site inventory was incomplete (would have shipped the "same name + same meaning"
  fix with live P mislabels remaining): added `my-request-history.blade.php:767` and
  `replacement-history.blade.php:373` (verified in code — the latter uses the
  `'Lecture (L)' : 'Tutorial (T)'` string form) to the Capability 2 site table (now six
  sites), "Affected files", and success criterion 2. Added the frozen-templates note:
  `ui-design-templates/*` copies stay untouched.
- Changelog name corrected: `page-changelogs/venue-timetable-ui-changelog.md` (exists),
  not "create if absent".

### 🟡 Addressed (deferred to design batch, as reviewer suggested)
- Anchor the canonical wording on existing `ui-common.js:943` three-way map; factor one
  shared helper (promote-on-3rd-duplication — 7+ sites after fix).
- State explicitly: one event per pending slot (no coalescing key — anchor-slot
  semantics, Amendment 2b).
- Name the manual-verify fixture (which pre-existing request serves as the
  foreign-proposer pending row) in design.md.

### 🔴 Outstanding
- (none — re-review pending)
## proposal.md Round 2 — 2026-10-13

### ✅ Verdict: Batch 1 PASSES — proposal.md FROZEN
All five fix points verified against code (site table, affected files, criterion 2,
frozen-templates note, changelog filename); ground-truth spot-check of both new blade
sites confirmed accurate; no decision-level regressions. 🟡 items formally deferred to
the design batch: ui-common.js:943 anchor, one-event-per-slot semantics, named
foreign-proposer fixture.
## design.md Round 1 — 2026-10-13

### 🔴 Fixed
- "Test reconciliation" rested on a false premise — the repo HAS an automated runner for
  this (Playwright suite: `npm test`, `tests/venue-timetable.spec.ts` etc.). Amended to
  commit to a new automated spec `tests/type-labels.spec.ts` (login 5770 → home →
  #39/#49 rows "(P)"/Practical + one L + one T row byte-identical); dropped the
  self-contradictory PHP-fallback paragraph. Frozen criterion 4 now met as written — no
  deviation remains. Spec file also added to the proposal's Affected files (declarative
  soft-freeze addition).

### 🟡 Addressed (reviewer 💡 notes)
- `remarks` byte-matches the legend tip now ('Replacement request awaiting PL approval').
- Unused `proposer` eager load dropped (mine reads the proposer_id column).
- Added the "Promoted to shared" heading (AGENTS.md formality) recording the
  UiLabels.classType promotion with its contract.

### 🔴 Outstanding
- (none — reviewer pre-approved: "no re-review round needed beyond confirming the edit";
  all fixes are exactly the prescribed amendments) → **design.md FROZEN**
