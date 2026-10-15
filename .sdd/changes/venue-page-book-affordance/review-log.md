# Review Log — venue-page-book-affordance

## Proposal Round 1 — 2026-10-13 (sdd-reviewer)
### 🔴 Fixed
 - **Missed test-impact file `tests/venue-timetable.spec.ts`** (65 tests, same live page, dormant template-era layer): added a full **test reconciliation** item to Impact scope + a dedicated success criterion. Audited live during this round: TC49 + TC51 are **already red today** (2026-10-13 scoped run: 2 failed / 1 passed) on template-era `#bookingBanner`/`#errorBanner` elements that don't exist in the live blade — pre-existing red, independent of U2a. Dispositions recorded: TC46 reworded to Arrange (it activates on `.cell-available` and would fail on its `/Book …/` regex), TC49–TC51 retired, TC70 audited/re-pointed, TC33/45/47/48/54/55 waking count-guards inherited-or-fixed. Added gate-honesty note: the true full suite is 316 tests / 25 files; earlier "full suite" gate runs were ~120-test scoped subsets.
### 🟡 Addressed
 - **Toast dependency unpinned**: resolved with evidence — no adaptation needed. `ui-common.js:2603` constructs the global `toast` (`new ToastManager()`), `#toastBar` exists in the shared layout (`layouts/ui-template.blade.php:68`); the frozen `toast.show(...)` no-fit call is reused as-is. No-fit **click** path (hover label + toast) added to scope + spec coverage.
 - **Line-number drift**: `bookVenue` :1085 (not :1065); show/hide tooltip :1046–1079 with scroll listeners :1082–1083; consumer `cell-too-soon` precedent :1682/:1689; live tint rules :16–22.
 - **SC3 "no affordance" ambiguity**: clarified — no *booking* affordance; the shared `.cell-too-soon` informational hover label *"Min. 3 working days ahead"* (`theme.css:2199`) remains.
### 🟢 Outstanding
 - (none — proposal frozen on this round's fixes)
### 💡 Carried into design.md
 - Inherited frozen quirks get explicit inherit-or-guard decisions: `focusedCell` staleness after week nav; document-level B/Escape keydown vs typing in inputs.

## Proposal Round 2 — 2026-10-13 (sdd-reviewer)
### 🔴 Fixed
 - **"Guest-reachable, needs no login" was factually wrong.** Verified: `/venue-timetable-ui` is gated `auth + role:lecturer` (`routes/web.php:73–77`, `RouteGateMatrixTest`), and `tests/venue-timetable.spec.ts` has **zero login scaffolding**. Corrected everywhere. Consequence owned honestly: the round-1 TC49/TC51 red probe ran **unauthenticated**, so the "missing template elements" attribution is unproven — unauthenticated runs land on the login page and the whole 65-test file mass-fails. Reconciliation upgraded to three ordered deliverables: (1) one-login `storageState` scaffolding for the file (65 per-test logins would blow the Fortify 5/min limiter), (2) authenticated full-file baseline run recorded in review-log (settles banners-vs-auth attribution), (3) dispositions applied to the baseline facts (TC46 reword stands regardless; TC49–TC51/TC70 expected retire, confirmed against baseline; waking guards audited). SC6 rewritten to "zero unexpected failures" + scaffolding.
### 🟡 Addressed
 - **Suite figures**: kept **316 tests / 25 spec files** — this is Playwright's own `--list` output run this session (13 `tests/*.spec.ts` + 12 `tests/e2e/*.spec.js` = 25; the recount of ~147/12 missed the `tests/e2e/*.spec.js` directory, which Playwright's `testDir: './tests'` includes recursively). Relabelled precisely as "Playwright's own count" and SC6 no longer pins a number ("zero unexpected failures"). Gate-honesty substance unchanged: earlier "full suite" runs were ~120-test scoped subsets.
### 🟢 Outstanding
 - (none — proposal frozen on this round's fixes)

## Proposal Round 3 — 2026-10-13 (sdd-reviewer)
### 🔴 Fixed
 - **Baseline run would execute TC70's real destructive write against the demo DB.** TC70 drives the live cancel-class flow (`app/Actions/CancelClass.php:39–55` → `class_exceptions` + `audit_logs` rows in a transaction) and is non-idempotent by schema (unique `(class_session_id, week_number)`, `:17–18`) — verified. Fix applied: deliverable 2 now **excludes TC70** from the baseline (`--grep-invert`) until dispositioned; the TC70 audit bullet now names the destructive write + non-idempotency as the decisive retirement grounds (stronger than the cell-language mismatch alone); explicit note that TC70's disposition lands **before** the SC6 full-suite gate.
### 🟡 Addressed
 - Figure slips: spec-file split corrected to **12 `tests/*.spec.ts` + 13 `tests/e2e/*.spec.js`** (verified by `ls | wc -l`); venue-db logins corrected to **5 `loginAsStaff` call sites, each staff ID at most 3× per run** (grep-verified) in both places it appeared.
 - 💡 adopted: Impact note that deliverable 1 touches `playwright.config.ts` (setup project) + `.auth/` state files.
### 🟢 Outstanding
 - (none — proposal frozen on this round's fixes)

## Proposal Round 4 — 2026-10-13 (sdd-reviewer)
### 🟢 Verdict: READY TO FREEZE (no 🔴)
 - Round-3 🔴 verified closed by source inspection: the deliverable ordering cannot execute TC70's write against the demo DB (baseline excludes it; disposition precedes the SC6 gate); reconciliation consistency and both figure fixes re-verified accurate.
### 🟡 Fixed at freeze (reviewer pre-approved, no re-review)
 - Deliverable 2: "baseline or any gate run" → "**ANY Playwright run** before its disposition is decided" — closes the intermediate-run hazard (post-scaffolding runs execute TC70 authenticated; the write commits server-side at the confirm click before the assertions fail, so even a failing run mutates the demo DB).
 - SC6: "TC49–TC51 and TC70 … per the baseline facts" → "TC49–TC51 per the baseline facts, **TC70 per its destructive-write grounds**" — TC70 has no baseline facts by design.
 - 💡 tightened: "mutates real data every time it runs" → "on its first run per occurrence" (unique constraint blocks re-runs; CancelClass catches the QueryException at :56–58).
 - 💡 carried into design.md: restate the no-TC70-execution rule as an explicit task-level guard so it survives into /sdd-apply.

**PROPOSAL FROZEN — 2026-10-13 (4 rounds; 0 outstanding).**

## Design Round 1 — 2026-10-13 (sdd-reviewer)
### 🔴 Fixed
 - **`MockData.mockNow` anchor mismatch (producer vs consumer lead-time).** The venue blade never nulls the mock clock anchor (Oct 5) while every other real-data page does (4 precedents) — the new `isSlotTooSoon` branch would compute an Oct-8 cutoff against the consumer's Oct-16 cutoff, making the page-open week's cells look bookable and then be rejected on arrival. Decision recorded in D1: add the one-line bridge `MockData.mockNow = null;`, with the **declared behavior change** (page opens on the real current week; week arrows/Today follow the real date) flagged for changelog + live verification. Design-level bridge within proposal SC3's intent (SC3 requires honest too-soon cells) — no proposal unfreeze needed; the reviewer asked for the decision to be recorded either way.
### 🟡 Addressed
 - **TC70 mechanism corrected in D5** (proposal's frozen wording untouched, per reviewer): on today's live page TC70 dies at :709 (expects `#eventModal`/`#cancelClassOverlay`/`#confirmCancelClassBtn`; live blade has only `#classModal` and zero cancel-class machinery) — it never reaches the write *today*. The retire decision and the no-execution guard stand unchanged, now explicitly framed as defense-in-depth against selector-repair.
 - **D2 placement corrected**: tooltip JS lives **inside the existing `DOMContentLoaded` closure** (:110–308) where `venueInfo` (:126, closure-only) / `weekData` / `currentWeek` are in scope — the earlier "top-level" pointer would have produced `"Arrange a replacement in undefined on …"`.
### 🟢 Adopted 💡
 - Too-soon branch line drift fixed (:861–866; also `theme.css` positioning :2063). "Verbatim except two points" reframed (CSS :178–212 with one box-shadow exception; hover-label block :315–317 is a separate, reworded port). sessionStorage model stated precisely (fresh context per test; within-test navigations only). `--grep` vs setup-project inclusion noted for single-TC verification. `sumAvailable` card-copy nit (server-computed count includes lead-time cells) recorded as a changelog line, future copy pass.

## Design Round 2 — 2026-10-13 — ⚠️ INTERIM SELF-REVIEW BY THE IMPLEMENTER (NOT an sdd-reviewer round)
**Context:** the sdd-reviewer agent failed with a provider-tier error on 4 consecutive attempts. User decision (2026-10-13): proceed with a documented interim self-review; design may freeze on this basis, tasks.md follows, **`/sdd-apply` is blocked until the real sdd-reviewer performs the missing confirmation round(s) on design.md and tasks.md** (or the user explicitly waives).
### Verified against source (same checklist given to the reviewer)
 - ✅ **🔴 fix complete and honest.** `getTodayMs` (`ui-common.js:2202–2209`) resolves from `MockData.mockNow` when present, falls back to `new Date()` when deleted — the bridge line works as designed. The four `mockNow = null` precedents re-grep-verified. Nothing else depends on the anchor: the venue blade overrides `MockData.semester`/`MockData.holidays` from the **real DB** (`:111–112`), and `currentWeekIndex` (`:36–43`) clamps into `[0, weeks-1]` — nulling only shifts which week opens (declared behavior change), no out-of-range risk.
 - ✅ **TC70 restatement accurate.** `tests/venue-timetable.spec.ts:709` expects `#eventModal`; the live blade's modal is `classModal` (`venue-timetable.blade.php:154/:166/:294`), no `#cancelClassOverlay`/`#confirmCancelClassBtn` anywhere → authenticated TC70 dies at `:709`, never reaching the `CancelClass` write. Guard + retire decision unchanged.
 - ✅ No other new inconsistencies in a full design.md read-through (D0–D7 internally consistent; proposal in-scope items 1–6 + copy set + SC1–SC7 all mapped to design sections).
### 🔴 Found and fixed by this self-review
 - **Baseline ordering ambiguity:** D5 didn't pin whether the authenticated baseline runs before or after the blade edit. The port changes observable behavior (cell language; real-week open via the clock bridge), so a post-edit baseline would misattribute port effects to the template-era layer. **Fix:** tasks.md fixes the order — scaffolding + baseline on the **pre-change blade** → port lands → dispositions applied to baseline facts → full-suite gate last.
### Honest-caveat note
 - A self-review by the author is not independent (this session's track record: the real reviewer caught 4 issues the implementer missed). This freeze is **interim**; the change is NOT cleared for `/sdd-apply` until the sdd-reviewer's retroactive confirmation round lands.

**DESIGN INTERIM-FROZEN — 2026-10-13 (round 1 = sdd-reviewer; round 2 = self-review ⚠️; reviewer confirmation pending).**

## Tasks Round 1 — 2026-10-13 — ⚠️ INTERIM SELF-REVIEW BY THE IMPLEMENTER (NOT an sdd-reviewer round)
Same context as the design round-2 entry above (reviewer agent down; apply gate stands).
### Checks performed
 - ✅ Coverage: every design section D0–D7 maps to ≥1 task (port inventory → T3/T4/T5; clock bridge → T5; copy → T6; consumer untouched → T11.3 re-assertion; test plan → T1/T2/T7–T12; quirks → T4 optional line; docs/closure → T14/T15). Every proposal SC1–SC7 covered.
 - ✅ Ordering safety: T2 baseline explicitly pinned to the **pre-change blade** (the self-review's 🔴 fix); T8 (TC70 retirement) lands before the first unfiltered full-file run (T10) and the full-suite gate (T12); the ⛔ TC70 guard + ⛔ apply gate banners sit at the top of tasks.md so they survive into `/sdd-apply`.
 - ✅ Sizing: every task ≤ 2h (largest: T5 — multi-function JS port, but all from the frozen template with documented adaptations; T11 — six Playwright tests).
 - ✅ Limiter math: exactly 1 new login/run (setup project) + existing per-test logins unchanged (venue-db ≤3 per staff ID) — within Fortify 5/min.
### 🔴 Found and fixed by this self-review
 - **Design D6 inconsistency:** design.md promised the optional `focusedCell` hardening would be "listed in tasks", but tasks.md omitted it. Fixed: T4 now carries the optional `focusedCell = null` in `buildTimetable` line with its changelog-note condition.
### Honest-caveat note
 - Author self-review; not independent. Retroactive sdd-reviewer confirmation of tasks.md required before `/sdd-apply` (banner in tasks.md).

**TASKS INTERIM-FROZEN — 2026-10-13 (self-review ⚠️; reviewer confirmation pending). Change ready for `/sdd-apply` ONLY after the reviewer's retroactive design+tasks confirmation rounds (or explicit user waiver).**

## Design Round 2 Confirmation — 2026-10-15 (sdd-reviewer via pi subprocess)

### 🟢 Verdict: CONFIRMED — design.md FROZEN (no 🔴)

Independent confirmation round upgrading the 2026-10-13 interim freeze to a real freeze. Full transcript: `review-transcript-design-r2-confirmation.md` (same dir).

### Verification performed (highlights)
- All claimed round-1/self-review fixes verified **in source, not prose**: `MockData.mockNow = null` bridge lands at T5 next to :111–112 — *before* both `generateWeekData()` (:114, consumes `getTodayMs()` at ui-common.js:57) and `currentWeekIndex()` (:119), the only correct order; the 4 precedents re-verified; declared behavior change correctly scoped (venue blade overrides semester/holidays from real DB, so nulling only shifts week-open/Today/arrows).
- D2 closure: `venueInfo` :126 / `weekData` :114–115 inside the DOMContentLoaded closure (:110–308); `day.abbr` in scope for the ported aria-label.
- TC70 mechanism re-verified: dies at `:709` (expects retired `#eventModal`/`#cancelClassOverlay`; live blade has only `classModal` :154/:166/:294) — "defense-in-depth against selector-repair" framing accurate.
- Baseline ordering (self-review 🔴 fix) landed in T2 ("pre-change blade, must run before T4"); D6 optional `focusedCell = null` hardening present in T4.
- Scope conformance: 1 runtime file per frozen proposal; mobile-card appends correctly omitted (:894+); no arrow-nav/banner/consumer code; D4 cites (`BLOCK_SPAN :1190`, `resolveBookingIntent :2472`, null-bridge precedent :1184) verified; D0 template line-cites spot-checked (:861–884, :1044, :1046–1085, :872, :1048); shared-infra cites verified (`partials/ui-grid-table.blade.php:2/:10`, `ui-common.js:2603`, `theme.css :2077/:2197`, legend tip :79, hint :102).

### 🟡 Carried to tasks.md confirmation round (non-blocking; both confirmed real)
1. TC70-guard banner cited "retired in T9" — actual retirement task is T8. **Fixed immediately in tasks.md banner (safety-control accuracy; reviewer-confirmed).**
2. D5 reconciliation step 1: add "on the pre-change blade" (5 words) — reviewer-directed frozen-artifact amendment, to be applied + logged in the tasks confirmation round.

### 💡 Carried to /sdd-apply (optional)
1. D2/T5 no-fit toast: keep frozen expression `'A booking needs ' + (BOOK_SPAN * 30) + ' minutes …'` (:1048) instead of unfolded `'120 minutes'` (identical output; byte-faithfulness).
2. T8: remove editorializing self-negotiation ("Keep it in T9's run out of habit? No…") → plain instruction.
3. D5 test 4: parenthetical — target non-weekend/non-holiday weekday cells for `cell-too-soon`.
4. T1: empirically verify grep/setup-project semantics via `npx playwright test --list -g "TC46"`.

**DESIGN FROZEN — 2026-10-15 (round-2 independent confirmation; 2026-10-13 interim label upgraded). Tasks.md confirmation round remains the only gate before `/sdd-apply`.**


## Design Round 2 Confirmation B — 2026-10-15 (second independent pi-subprocess round)

### 🟢 Verdict: CONFIRMED — no outstanding 🔴 (independent re-verification of the first confirmation round)

Second real sdd-reviewer round (pi subprocess, `--model z-ai/glm-5.3-flash` pinned after diagnosing a gateway-side model outage on the default-picked `deepseek/deepseek-v4-pro` /responses endpoint — see session log). Full transcript: `review-transcript-design-r2-confirmation-b.md`. This round did NOT trust the 2026-10-15 entry's prose: it re-verified every fix from source and re-spot-checked ~30 line-cites (all accurate), then found **one substantive gap every prior round missed**:

### 🟡 New finding (provable from source today; fold into tasks-confirmation batch as a declarative D5/T10 amendment)
 - **Template-era red clusters with no pre-decided disposition**: TC12/TC13 (`:123/:140`) expect `#favStar` — the live blade documents the star as **stays dropped** (blade :58–59) and `#favStar` exists nowhere live; TC39–TC44 (`:371–424`) assert `#eventModal` / `.modal-footer [data-cancel-class-btn]` vs live `#classModal` with zero cancel-class machinery. Same template-era layer as TC49–TC51/TC70. Pre-decide: TC12/TC13 → **retire per the documented divergence, NEVER modify the page to satisfy a test** (a byte-scope violation of the 99.9%-UI rule); TC39–TC44 → re-point `#eventModal`→`#classModal`, retiring any assertion the live blade cannot satisfy (cancel-class machinery). T10's catch-all widened from "broken by the real-week-open change" to "disposition **every** baseline-red test".
 
### 🟢 Amendments applied in this batch (declarative / soft-freeze, logged here)
 - design D5 step 1: "on the pre-change blade" wording landed (carried from the first confirmation round).
 - design D5 step 2 + tasks T10: disposition-everything rule + pre-decided TC12/13 + TC39–44 dispositions + never-modify-the-page line.
 - design D5 test 4 parenthetical: too-soon probes target non-weekend/non-holiday weekday cells.
 - tasks T5: no-fit toast keeps the frozen expression `'A booking needs ' + (BOOK_SPAN * 30) + ' minutes …'`.
 - tasks T8: editorial self-negotiation replaced with a plain instruction.

**DESIGN FROZEN (re-affirmed + declaratively extended, 2026-10-15, two independent rounds).**

## Tasks Round 1 Confirmation — 2026-10-15 (sdd-reviewer via pi subprocess)

### 🟢 Verdict: CONFIRMED — no outstanding 🔴 → TASKS FROZEN, /sdd-apply gate CLEARED

First real sdd-reviewer round on tasks.md (pi subprocess; full transcript: `review-transcript-tasks-r1-confirmation.md`). Verified from source, not prose:
- All declarative amendments from Design Round 2 Confirmation B landed correctly and consistently (D5 disposition-everything rule; TC12/TC12 `#favStar` pre-decided retire — `#favStar` grep-verified absent from the live blade with the divergence documented at blade :56–62; TC39–TC44 `#eventModal`→`#classModal` with cancel-machinery absence verified; T10 widened; T5 frozen toast expression; T8 plain instruction).
- Coverage: D0–D7 → tasks, SC1–SC7 → tasks, ordering safe (T2 pre-change baseline before T4; T8 retirement before T10/T12; TC70 guard banner cites T8), sizing ≤2h, Fortify limiter math re-verified (venue-db 5 call sites, each staff ID ≤3×).
- Spot-checked line-cites substantively accurate.

### 🟡 Carried to execution (no artifact change needed)
 - **TC61** (`tests/venue-timetable.spec.ts:593–597`) clicks `#favStar` — a third test in the confirmation-B cluster, red today. Covered procedurally by T10's widened rule; on execution, fold it into the TC12/TC13 retire disposition (same grounds: documented divergence; never modify the page to satisfy a test).
 - Cosmetic: design D5's TC12/TC13 cite ":123/:140" — TC13 actually starts :127 (:140 is TC14).
 - T10 may exceed 2h once all ~9 template-era reds land; the task text permits splitting into mechanical re-points vs retirement decision-notes.

**TASKS FROZEN — 2026-10-15 (self-review 2026-10-13 upgraded by this real confirmation). All three artifacts (proposal / design / tasks) now hold real freezes. `/sdd-apply` is cleared per the user's standing directive.**

## T2 — Authenticated baseline (pre-change blade) — 2026-10-15 (implementer, apply phase)

**Run:** `npx playwright test tests/venue-timetable.spec.ts --grep-invert "TC70"` — 64 tests (65 − TC70), **47 passed / 18 failed**, 3.1 min. Scaffolded login (T1): setup project → `.auth/staff-5770.json`; file opts in via `test.use({ storageState })`; zero per-test logins. Raw failure causes from `test-results/*/error-context.md` (18, categorized):

- **EventModal cluster — TC39** (`:375` opens modal), **TC40** (course-code field), **TC41** (venue field "CODE — Type (N seats)"), **TC44** (overlay closes modal): `#eventModal` never visible — live modal is `#classModal`, zero cancel machinery. → pre-decided (design D5 ext.): re-point to `#classModal`; retire unsatisfiable assertions.
- **FavStar cluster — TC12** (`:127` visible), **TC13** (`:131` toggle), **TC61** (`:597` localStorage): `#favStar` not found. → pre-decided: retire (documented divergence); never add the star to satisfy tests.
- **`venueDropdown` global cluster — TC10** (`:109` default selection), **TC25** (`:237` dropdown close/selection), **TC60** (`:588`), **TC62** (`:604`), **TC69** (`:673`): `page.evaluate: ReferenceError: venueDropdown is not defined` — template-era evaluate-helpers assume a global the live blade never exposed (live page uses the 4-column `.venue-dd-*` cascading dropdown). → disposition at T10: re-point to DOM helpers or retire with note.
- **Banners — TC49** (`:487` `#bookingBanner`), **TC51** (`:505` `#errorBanner` not-class-show): **elements genuinely absent, even authenticated → attribution SETTLED: not auth-redirect; template elements don't exist** (as expected). → retire.
- **Misc template-era content asserts — TC03** (`.page-desc`), **TC04** (`#semesterChip`), **TC35b** (`:317` `#tableBody .event-conflict` count), **TC37** (summary-card element): selector/expectation vs live-page mismatches (live structure uses other partials/ids). → disposition each at T10 with decision note (re-point to live selector where the assertion is still meaningful, else retire).

⇒ All 18 are template-era-layer reds against the live blade; every cluster now has a pre-decided or rule-backed disposition (design D5 extended). No failure implicates the U2a port surface beyond what D5 plans.

## T12 — Gates — 2026-10-15 (implementer, apply phase)

- ✅ pint (`composer run lint:check`): passed — zero PHP changes as designed.
- ✅ phpstan 1G: 0 errors.
- ✅ phpunit: **173/173** (tasks expected 172/172; 1 unrelated test landed since the freeze — noted, out of scope).
- ✅ **In-scope Playwright green:** venue-book.spec.ts (new) 6/6 (three runs); venue-timetable.spec.ts reconciled **56/56**; venue-db.spec.ts 6/6 incl. T7 re-point; historical gate subset (6 files, 76 tests): 75/76 with one click-stability flake on venue-db's login button (passed on standalone retry; `#loginBtn` "element is not stable" — pre-existing login-page animation flake under load, not port-related; recorded for the next venue-db touch).
- ⛔ scope-ENLARGING discovery, NOT improvisable by the implementer: the first-ever TRUE full `npx playwright test` (316 tests / 25 files) reports **161 passed / 3 skipped / ~149 failed**, ALL failures in files U2a never touched and never gated — `e2e/full-suite.spec.js` 63, `e2e/auth-wiring` 15, `ui-regression` 14, `cancel-class` 14, `e2e/api-wiring` 13, `confirm-guards` 13, e2e smoke pages 11 more — rotating login per tests confirmed? NO — spot-verified failing STANDALONE too (cancel-class 1/5 pass; api-wiring 2/7 pass), i.e. a dormant template/mock/API-era red layer, exactly the class of never-run suite the proposal's gate-honesty note flagged, generalized to the whole never-gated layer. U2a's frozen scope reconciles ONLY venue-timetable.spec.ts + this page's idiom — 149-test retirement/reconciliation is a decision-level scope change requiring the user's call.
- **T12 status: ⏸ PENDING USER DECISION on SC6** ("zero unexpected failures" was frozen assuming the rest of the suite was green). Options: (A) gate U2a on the maintained subset (green ✅ above) and record the never-gated layer as its own follow-up SDD change; (B) user waiver on the full-suite gate; (C) expand scope now to reconcile all 16 files. No further apply steps block — T13/T14 proceed; T15 records the pending decision.

## Apply completion note — 2026-10-15 (implementer)

**U2a implemented and machine-verified live.** All 15 tasks executed (T12's full-suite gate clause pending the user's decision — see T12 entry). Files touched: `resources/views/livewire/venue-timetable.blade.php` (the port), `.gitignore` (`.auth/`), `playwright.config.ts` (setup project), `tests/auth.setup.ts` (new), `tests/venue-timetable.spec.ts` (storageState + reconciliation: TC03/04/10/25/37/39–44/46/60 re-points, TC12/13/35b/49–51/61/62/69/70 retired with decision notes), `tests/venue-db.spec.ts` (T7 re-point), `tests/venue-book.spec.ts` (new, 6 tests), `page-changelogs/venue-timetable-ui-changelog.md` (postscript). Stale-cache ritual performed; requests table verified empty (honest-state note in changelog). TC70: **retired in T8, never executed in any run** (guard honoured throughout). Awaiting: user decision on T12 scope; `/sdd-verify` + `/sdd-archive` after that.
