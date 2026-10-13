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
## tasks.md Round 1 — 2026-10-13

### ✅ Verdict: Batch 3 PASSES — tasks.md FROZEN
Coverage audit clean: every frozen requirement maps to a task (T1–T9), no scope creep,
all tasks ≤2h, dependency-correct order, apply-phase compliance verified. Zero 🔴.

### 🟡 Addressed at freeze
- T7 gate strengthened: full Playwright suite (`npx playwright test`), not just the new
  spec — T1's new venue events could break `venue-timetable.spec.ts`/`pages-parity.spec.ts`.
- 💡 noted for the implementer: T2(c) rejected-request leg is a crafted row (demo DB has
  cancelled only); T1 reads proposer_id as a plain column (no eager load).

## /sdd-propose COMPLETE — 2026-10-13
proposal.md (R1–R2), design.md (R1), tasks.md (R1) all frozen. Commit a175337 + this.
Next stage: /sdd-apply on user green-light.
## /sdd-apply — 2026-10-13 (T1–T9 complete)

Implemented via two parallel coding subagents (PHP+tests / labels+spec), verified by
the orchestrator against the frozen design before gates.

- **T1** `VenueTimetable.php` (+58): reserved-slot second pass per D1 — orphan+week
  guards, one event per anchor slot, class facts from the original session, `mine` via
  proposer_id column (no proposer eager load).
- **T2** `VenueTimetableTest.php` (+227, 5 new tests): own/other/cancelled/week-guard/
  orphan legs — **14/14 green**. Week-bounds fixture uses `semesters.week_count` shrink
  (schema caps week_number at 14, so week 15 is uncraftable) — honest equivalent of the
  design's out-of-range leg.
- **T4+T5** `UiLabels.classType` const object (placed after `StatusText`, matching file
  style) + 7 consumers (6 mislabel sites + the :943 anchor). L/T byte-identical at all
  sites; `node --check` clean.
- **T6** `tests/type-labels.spec.ts` — hardened during gates: (1) modal close scoped to
  `#quickViewModal .btn-close-modal` (a second hidden `.btn-close-modal` — keyboard
  shortcuts — exists earlier in the DOM); (2) collapsed to ONE test = ONE login — the
  Fortify login limiter is **5/min per login_id|type|ip** (FortifyServiceProvider) and
  per-test logins trip 429 on back-to-back runs (3 consecutive green runs after the
  fix); (3) login waitForURL 30s (artisan serve is single-threaded, cold spikes).
- **T7 Gates**: pint ✓ · phpstan 0 · **php artisan test 172/172** (1056 assertions;
  baseline 167 + 5 new) · **full Playwright suite 117 passed / 3 skipped / 0 failed**
  (118→117: the spec's 3 tests consolidated into 1).
- **T3 Live** (5770, post-restart ritual): B110 · option label "Week 4" (value 3) →
  amber block `AMCS2093(L) · B110 · 12:00 PM–12:30 PM`, tooltip "Lim Jia Zheng ·
  Pending", **3px solid amber border** + amber fill, class `event-mine-pending` — the
  frozen legend's vocabulary exactly. GOTCHA recorded: the venue page weekSelect is
  **0-based** (option value 3 = label "Week 4"); earlier probes set value 4 = Week 5
  and saw nothing — the block was rendering correctly all along.
- **T8** postscripts: venue-timetable-ui, replacement-home, my-request-history,
  replacement-history changelogs.

NOT part of this change (left uncommitted, user's/other session's work):
`docs/server-commands.md` (new) + `docs/README.md` index line.
