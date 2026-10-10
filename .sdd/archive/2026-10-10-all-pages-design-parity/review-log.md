# Review Log — all-pages-design-parity

## proposal Round 1 — 2026-10-10
### 🔴 Fixed (audit-table corrections — all five verified by reviewer against frozen templates + working tree)
- **C2**: frozen cohort cards are **5** (`sumTotal, sumHours, sumMyClasses, sumMyHours, sumConflict` — Conflicts STAYS with warn-keyword); only Replacements/Pending are replaced by the My-Teaching pair. My "4 cards, conflict removed" would have deleted a frozen card.
- **C3 + S2**: frozen cohort AND student templates pass **no `tooltipExtra`** → frozen tooltip = 2 segments `lecturer · status`. Parity fix = REMOVE tooltipExtra (not "supply cohort context" as I wrote).
- **C5 (new, audit gap)**: cohort blade tests `e.isMine` but no payload ever sets it → ownership axis fully broken (everything renders "others'"; ownership hint would be a lie). Frozen idiom `e.lecturer === MockData.currentUser.name` + page must set currentUser.
- **M2**: my-timetable already has all 5 frozen cards — real delta is description markup (strong → keyword spans) only.
- **M3**: working `isConflict = day.holiday` only vs frozen `day.holiday || status === 'conflict'` → B005-conflicted class loses Replace Now on live page. Aligned in scope.
### 🟡 Addressed (folded into scope S4/S5)
- `showPrint => true` missing on all three pages' week-nav; guide-block text drift; `computeSummary` cancelled-filter alignment; deliberate divergences (cancel modal → Slice B, cohort week-nav disabled adaptation, week-persistence key) to be recorded in design; never assert "always 0" for sumPending/sumReplacement (payload can emit those statuses); student `MockData.currentUser` absent + `requestId`/`requestedBy` modal TypeError risk → S3.
### Verdict
**Conditional FAIL resolved — all 🔴 audit corrections + 🟡 fold-ins applied. Proposal sent for Round 2 confirmation.**

## proposal Round 2 — 2026-10-10
### 🔴 Outstanding
- (none)
### Verification
- All five 🔴 corrections re-derived from source by reviewer and confirmed accurate (down to "Conflicts stays with warn-keyword" and the exact cancelled-filter wording).
- All 🟡 fold-ins confirmed in their assigned scopes (S3/S4/S5).
- 💡 noted: cohort blade ALREADY sets MockData.currentUser (L101) — design must NOT add a redundant "set currentUser" task for cohort; C5 is the idiom swap only.
### Verdict
**PASS — proposal FROZEN.** Next: Batch 2 (design.md).

## design Round 1 — 2026-10-10
### 🔴 Outstanding
- (none)
### 🟡 Addressed (soft-freeze amendments, applied as prescribed)
- **Student pin made falsifiable**: asserts the VALUE of `MockData.currentUser.name` after login (seeded student via 25DFT0001), not mere presence — mock-data.js ships a global default persona ('En. Lim Jia Zheng') that would pass vacuously.
- **Cohort selection persistence recorded as divergence #5** (`cohortTimetableState`): real page is server-driven (?cohort= + $pinnedCohortId); client restore would fight it. Week key (item 3) covers the useful part.
- **My-timetable micro-deltas recorded**: tooltip fallback aligned to frozen (`e.cohort || ''` — drops spurious '—' third segment); multi-cohort modal join/sum recorded as payload-shape divergence (#6, #7).
### 💡 Addressed
- §5→§4 cross-ref fixed; M3b CLOSED now (verified: neither working blade nor frozen template passes statusClassFn — shared default is already frozen-equivalent, no-op); tasks will scope "Playwright green" to the gate set (legacy ui-regression/cancel-class stay parked).
### Verdict
**PASS — design.md FROZEN** (soft-freeze amendments applied as conditioned; no decision-level changes).

## specs + tasks Round 1 — 2026-10-10
### 🔴 Outstanding
- (none)
### 🟡 Addressed (soft-freeze amendments, applied as prescribed)
- Spec M2 gap closed: my-timetable card description spans added to R4 (was tasks-only).
- Spec R7 divergence enumeration completed: #6 (my-timetable tooltip fallback `e.cohort || ''`) + #3 (`cohortTimetableWeek` key) — both were tasks-only blade changes.
- Spec C5 second half folded into R3: `replacementNoteFn.checkOwnership` idiom swap named in the requirement (was tasks-only).
- tasks T12 typo fixed (nav-identity); 💡 adopted: new `pages-parity.spec.ts` home (4288 already at 3 logins in timetable-wiring — ceiling is 5); T13 dead conditional removed (no Feature test asserts DOM ids — any red = payload regression, not a test edit).
### Verdict
**Batch 3 (spec): PASS after amendments — FROZEN. Batch 4 (tasks): PASS — FROZEN. Full chain frozen → proceed to /sdd-apply.**

## verify — 2026-10-10
### Applied
- Cohort: frozen legend (3 + ownershipHint), frozen 5 cards, tooltipExtra removed, ownership idiom swap (C5 bug), PH owner-gate, showPrint, guide text, cancelled filter, frozen week key
- My-timetable: keyword-span cards, isConflict || status==='conflict' (M3 bug), tooltip fallback, showPrint, guide text, cancelled filter
- Student: MockData.currentUser from auth (S3 bug), keyword-span cards, tooltipExtra removed, showPrint, cancelled filter
- Tests: tests/pages-parity.spec.ts (3 pins: ownership axis / B005 Replace Now / identity value pin)
### Gates
- lint ✓ · phpstan 0 (--memory-limit=1G) ✓ · phpunit 130/130 (848) ✓ — Feature suite untouched and green, as designed
- Playwright: venue-db 5/5 · timetable-wiring · nav-identity · pages-parity 3/3 = 16/16 total ✓
- records-intact ✓ · route:list 0 api/v1 ✓ · per-page 302 smoke during apply ✓
### Notes
- One test-iteration fix during T12: the faculty/cohort walk originally asserted toBeVisible on <option> elements (unreliable in Playwright for closed selects) — rewritten to the waitForFunction + $$eval pattern proven by an inline probe. No production change involved.
- Divergences kept per design §4: no cancel modal (Slice B), dynamic week-nav disabled, no client-side state persistence, honest zeros.
