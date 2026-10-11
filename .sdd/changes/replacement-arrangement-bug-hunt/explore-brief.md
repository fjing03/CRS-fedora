# Explore Brief — Replacement-Arrangement Flow Bug Hunt

> /sdd-explore · written 2026-10-11 · READ-ONLY audit (no code touched, no commits).
> Method: every user symptom retested live on HEAD via Playwright against the demo DB
> (`class_replacement_fresh`), then root-caused in code. One write-repro performed
> (submit + cancel of ONE new request) — exact cleanup SQL at the bottom.

## Audit state

- **HEAD = `e99abbf`** ("occurrence identity end-to-end"), code tree **CLEAN** for the whole audit.
  Only untracked paths: the prompt file itself + this SDD dir. **No [WIP-OVERLAP] occurred** —
  the Slice B session did not edit anything under us.
- The 4 fix commits under review: `89843b0` (frozen UI restore, histories), `ee5e956`
  (Livewire v4 wire-proxy JS API), `d3d4d40` (engine-excluded cells render occupied),
  `e99abbf` (occurrence identity handoff).
- Server restarted with the two-command stale-cache ritual before any visual finding was trusted.
- Login used: staff `5770` (Lim Jia Zheng — owns AMCS2093 #97 Mon 11:00–13:00 @ B110) and
  staff `5425` (Surayaini, PL) for the multi-subject cases.

### Verdicts on the four fix commits (retested live on HEAD)

| Commit | Claim | Verdict |
|---|---|---|
| `89843b0` | my-request-history / replacement-history frozen UI restored | **WORKING** — history page renders the frozen sortable table, confirm-cancellation modal, summary cards; page title correct |
| `ee5e956` | submit/cancel MethodNotFoundException (wire-proxy JS API) | **FIXED-BY ee5e956** — submit (`Livewire.first().call('submitBlocks', …)`, view :1965) and cancel both succeed live, 0 console errors; the user's "blocked testing" state is gone |
| `d3d4d40` | grid renders engine-excluded cells occupied | **WORKING, but undermined** — engine-blocked cells render occupied on load (51 occupied cells on wk-8 B110); however the grid is NOT re-rendered on subject change (BUG A2) or week nav (BUG A1), so the protection is only as fresh as the last rebuild |
| `e99abbf` | occurrence identity end-to-end | **FIXED-BY e99abbf** — `?session=97&week=8&from=my-timetable` pins the exact occurrence (subject, original venue B110, week, slot auto-picked); home hides covered occurrences and **returns them after cancel (verified both directions)**; the class-detail modal now shows End Time **1:00 PM** (the user's "End Time 11:00 AM" quote is gone) |

---

## Part 0 — USER-REPORTED SYMPTOMS (verdicts)

Preserves the user's numbering (no "4"). Every row retested on HEAD.

| ID | Flow step | Exact repro | Expected (FR/spec ref) | Actual | Evidence | Root cause (file:line) | Pri | Cluster | Status | WIP? | User |
|---|---|---|---|---|---|---|---|---|---|---|---|
| U1 | Submit (block semantics) | Select a 4-cell block (e.g. wk-8 B110 Tue 4:30–6:30 PM) → Submit → read confirm dialog → submit → read My Request History row | Spec replacement-flow-wiring Amendment 2b: **only the clicked anchor half-hour slot is reserved** (documented prototype limitation); UI must not promise more | Confirm dialog says "**4:30 PM ~ 6:30 PM · 4 slots**"; DB/request row holds ONE slot (16:30–17:00); history row shows a 30-min replacement | (code-level; repro chain verified) | `proceed()` renders the full block span (replacement-arrangement.blade.php:1938) while `submitBlocks`→`SubmitReplacementRequest` reserves the anchor only (ReplacementArrangement.php:504–532). The limitation is documented — the **copy overpromises** (also selection-summary "4 slots (2 Hours)") | **P1** | A | STILL-BROKEN (limitation by design; UI copy misleading) | N | **Y/1** |
| U2a | Venue timetable — Book affordance | Open `/venue-timetable-ui`, hover a free cell | Frozen design: `.available-tooltip` with **`.btn-book`** ("Book") on free cells (+ booking banner/hint + `B` shortcut) → hands off `?venue=&date=&time=&from=venue-timetable` | No Book affordance anywhere (grep `.btn-book` in live view = 0); only an italic hint "Slot booking arrives with the replacement workflow (Slice B)." | (grep + live) | Conversion to the real-data view dropped the whole tooltip/booking block. Frozen source: `ui-design-templates/venue-timetable-UI-design-template.blade.php:178–215, 350, 395, 461–463, 1046–1098` (`showAvailableTooltip`/`bookVenue`). The **consumer side is already live**: `resolveBookingIntent` (replacement-arrangement.blade.php:2442–2540) parses `?venue&date&time`. Faithful restore = port tooltip + `bookVenue` into `livewire/venue-timetable.blade.php` cellRender's free-cell branch (:230–235) with the same URL contract; Slice-B wiring then already works | **P2** | C | STILL-BROKEN (removed at conversion, never restored) | N | **Y/2a** |
| U2b | Venue timetable — own pending missing | As 5770 with pending req #1 (slot 27309), open venue B110 wk 4 (and wk 8 for slot 27758) | One pending reality: the reserved slot renders as a Pending block (legend has "Pending"; §10.0 table B) | **Pending slot invisible** — grid shows only imported classes; own/others' pending replacements render nowhere | `evidence-2b-venue-pending-invisible.png` | `VenueTimetable.php:88–93` — events are built from `time_slots` and rows with `class_session_id === null` are **skipped**; replacement-reserved slots always have `class_session_id = NULL` (verified: 27309/27373/27851/27758 all NULL). Fix: left-join active `replacement_requests` and emit pending events for reserved slots (`mine` by proposer) | **P1** | B | STILL-BROKEN | N | **Y/2b** |
| U3 | Pending consistency across 4 pages | With one pending request, visit my-timetable / cohort / venue / home | "One pending reality, four pages must agree" (trait docblock + D1) | my-timetable wk-4: original occurrence **event-pending** ✓; cohort: same trait ✓ (holiday weeks mask it, see B3); venue: **nothing** (U2b); home: occurrence **hidden** ✓ | `evidence-mt-pending-modal.png` | Venue page root cause above; the rest conforms to the documented semantics | **P1** (venue leg) | B | PARTIALLY STILL-BROKEN (venue leg) | N | **Y/3** |
| U5a | Entry param schemes (all three) | (i) `?session=AMSE1003&week=2026-11-09` (old shape) (ii) `?session=97&week=8` (iii) legacy `?code=&cohort=&day=&start=&end=&originalVenue=&duration=` | Each loads the correct class context or degrades honestly; one consistent design | **(ii) works end-to-end** (subject + original venue + pinned week + slot auto-picked — `evidence-5a-arrival-home-pin.png`). **(iii) still parses** (verified live: subject AMSE1003, trigger W7·Nov 09 9:00–11:00, venue B111). **(i) is no longer emitted** by any sender (all four emitters now use `goToReplacementFor`, ui-common.js:1268) and if hand-typed degrades to the honest bare page (pinned=null). Garbage: `session=99999`, `session=abc&week=date`, `venue=ZZZ`, bare — all degrade honestly, no blank/500 | `evidence-5a-arrival-home-pin.png` | `pinnedSessionPayload` (ReplacementArrangement.php:123–148) ownership-scopes + falls back to the legacy parser (`resolveSession` :548–578; `applyUrlParams` view :2990–3030). Residue: TWO param schemes coexist (identity pair + legacy strings) — consolidation debt, not a live defect. Minor: `&duration=0` is not rejected — clamps to BLOCK_SPAN=1 (view :3053–3058) instead of ignoring | **P2** (residue) | A | **FIXED-BY e99abbf** (+ honest degradation verified) | N | **Y/5a** |
| U5b-a | slotTrigger change vs context | As 5425: enter `?session=68&week=14` (AMSE2003 T @ B100), pick the **P** occurrence (Fri @ B010) in the slot picker | Cohort count, class type, available venues must reflect the picked occurrence | Strip still shows **(L)** type + module-level cohort/total; venue stays B100; "venues that fit 22 students"; **Labs offered in the venue dropdown for an L module**; grid busy vectors unchanged | `evidence-5b-slottrigger-stale-context.png` | Context is **module-grain, not occurrence-grain**: `coursesPayload` groups by module, type = first session, cohorts = union (ReplacementArrangement.php:182–210); `busyByWeek`/`blockedVenues` keyed by module with a representative session (`sessionsByCode` :318–337); strip renders `currentCourse` (view :2321–2390). Plus the filter/engine divergence (A3) | **P1** | A | STILL-BROKEN | N | **Y/5b** |
| U5b-b | After successful submit | Select block → Submit → Yes | Navigate to `/my-request-history-ui` | Stays on the arrangement page; toast "✓ Submitted — Pending Approval … **View →**" link only | `evidence-5b-submit-no-nav.png` | `proceed()` success path (replacement-arrangement.blade.php:1980–1998) shows the mock-era toast; no navigation | **P1** | A | STILL-BROKEN | N | **Y/5b** |
| U5b-c | After failed submit | (Not write-repro'd live — see note) Failed submit → grid re-derives; error clear + error colour | FR 4.10 alert; grid re-derivation; NFR 3.3 | On HEAD the failure modal **does** appear (ee5e956 restored the call) with a per-block reason list and the selection kept — but the title is the vague "**Submission Conflict**", rendered in a **generic confirm modal (btn-primary, no error colour)**, and the grid is **never re-derived** (no mechanism: payload is server-rendered once; no re-render after the Livewire result). The user's "cells render EMPTY" was the pre-`ee5e956` dead promise — not reproducible on HEAD | (code-level) | Failed branch (view :1999–2007) calls `showConfirmModal` only; no `buildTimetable()` / payload refresh. Data freshness would need a Livewire re-render or a `wire:poll`-style refresh | **P2** (re-derive) / **P3** (copy+colour) | A | PARTIALLY FIXED-BY ee5e956 (call works); re-derive + copy STILL-BROKEN | N | **Y/5b** |
| U5b-d | Home entry auto-select | Click a conflicted card on replacement-home | The clicked occurrence is auto-selected in the slot picker | Subject/venue/week DO hydrate ✓, but the **auto-picked original slot is the FIRST matching occurrence, not the pinned week's** whenever a class has conflict occurrences in several weeks (applyDefaultSlotSelection first-option + applyUrlParams first-match ignore `window.pinnedOccurrence.week`). Not demonstrable on current demo data (no multi-week-conflict class exists) — code-level finding. Related: trigger/strip display 0-based week labels ("W7" / "Week 7") for Week 8 | `evidence-5a-arrival-home-pin.png` (label visible) | `applyDefaultSlotSelection` (view :2937–2963) + URL-match (:3016–3028) match on day/start/venue only; 0-based labels from `conflictEventsPayload` `'week' => $week - 1` (ReplacementArrangement.php:250) | **P2** | A | PARTIALLY STILL-BROKEN (wrong-week auto-pick latent; labels live) | N | **Y/5b** |
| U5b-e | Venue change vs grid | Change venue B110 ↔ B111 (and wk-5 check) | FR 2.6: recalc ≤500 ms, grid reflects | **Works on HEAD** — grid re-derives (`applyVenueChange` → `buildTimetable`); verified with an observable difference: wk-5 B110 shows the Pending(You) cell Mon 14:00, B111 shows it green | `evidence-5b-venue-change-pending-you.png` | — | — | A | **WORKS / NEEDS-RETEST → PASS** (user's earlier attempt was likely the stale-cache trap) | N | **Y/5b** |
| U5b-f | "Home hides covered occurrences … returns if PL rejects or you cancel" | Submitted req #4 covering (97, wk 8) → home row vanished; cancelled it → row **returned**; my-timetable kept showing the occurrence (pending/holiday state) + modal **End Time 1:00 PM** ✓ | D1 + e99abbf claim; my-timetable should keep showing the occurrence with its state | Both directions verified live. The home↔my-timetable "contradiction" resolves as designed: **home = to-do list (hides handled occurrences); timetables = schedule truth (show the state)** — per the trait docblock + spec. The old "End Time = Start Time" modal bug is gone | `evidence-mt-pending-modal.png` | `ReplacementHome.php:61–74` (active-request filter); trait `buildEventsByWeek` pending overlay | — | B | **FIXED-BY e99abbf** (both directions) | N | **Y/5b** |

---

## NEW bugs found (not user-reported)

| ID | Flow step | Exact repro | Expected | Actual | Evidence | Root cause (file:line) | Pri | Cluster | Status | WIP? | User |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **A1** | Week navigation (arrangement) | On `/replacement-arrangement`, select a slot, press › / ‹ / week dropdown / "Earliest bookable" | Grid re-renders for the new week (days, holidays, pending cells, busy vectors) | **The grid never rebuilds.** Week number + dropdown update; day columns, PH cells, pending cells, busy vectors all stay from the old week; the selected block persists visually across weeks; repeated navs silently duplicate the block under several week keys (observed 2 summary cards for one selection); `save()` is never reached | `evidence-mobile-arrangement-375.png` is unaffected (fresh load); behaviour verified live by diffing cell sets across weeks | `WeekNavigator._buildTimetable()` delegates to `window.buildTimetable` (ui-common.js) — the arrangement page **never exports it** (window-exports block, replacement-arrangement.blade.php:3141–3156), so the call no-ops. my-timetable/venue/cohort all export it; the arrangement conversion missed it | **P0** | A | STILL-BROKEN (new) | N | N |
| **A2** | Subject change (arrangement) | Pick subject A (cohorts X) → note cell counts → switch to subject B (different cohorts) | Grid re-derives B's busy vectors / blocked venues | **Grid byte-identical** (35/53/44 cells unchanged across AMSE2003→AMSE1003 with different cohorts); engine-blocked cells stale for the new subject → the "free cell → conflict popup" trap `d3d4d40` closed re-opens on every subject switch | (live diff, counts identical) | `applySubjectChange` (view :2648–2674) never calls `buildTimetable()` | **P1** | A | STILL-BROKEN (new) | N | N |
| **A3** | Venue dropdown filter (arrangement) | For an L module (AMSE1003), open the venue dropdown | FR 4.7: only venues allowing the module's session type (DB `allowed_session_types`: labs=P only, B110/B111=L only) | Dropdown offers **Tutorial + Lecture Hall + Lab** for the L module; picking a lab lands on a grid whose cells are ALL engine-occupied (`blockedVenues`) | (live: dropdown options listed) | View filter `typeOk = Tutorial || Lab || (L && LectureHall)` (view :2683) contradicts both the DB and the engine's `eligibleVenues` (MatrixIntersectionEngine.php:337–351). Should derive from the same source as `blockedVenuesPayload` | **P1** | A | STILL-BROKEN (new) | N | N |
| **A4** | Week-nav guards (arrangement) | With a selection active, click › | Confirm modal ("selection will be saved…") per changelog 2026-08-19 | **No confirmation** — week changes silently | (live: guardShown=false) | The mock-era guarded wrappers (`guardedPrevWeek`/`guardedNextWeek`/`guardedWeekChange`/`guardedJumpToToday`) were not carried into the live view; the `ui-week-nav` include passes bare `weekNav.prevWeek()/nextWeek()` (view :1015). Same conversion gap family as A1 — fix together | **P2** | A | STILL-BROKEN (new) | N | N |
| **B1** | Class-type label (home + quick view) | As 5425, open replacement-home: rows for P sessions (#39/#40/#49) | Same name + same meaning everywhere (§10.0.2): Practical (P) | Rendered "**(T)**" / "Tutorial" for every P session | (live row HTML captured) | `ui-common.js:2372` `c.type === 'L' ? 'L' : 'T'` collapses P→T (also :2389/:2393 card view, :2351/:2357 request-history variant); home quick-view `c.type === 'L' ? 'Lecture' : 'Tutorial'` (replacement-home.blade.php:560). Mock-era binary label; the real data has P sessions | **P1** | B | STILL-BROKEN (new) | N | N |
| **B2** | Page title (home) | Open `/replacement-home-ui`, read the tab | "Replacement Home …" | Tab reads "**Replacement Arrangement — Class Replacement System**" | (live) | Copy-paste: `replacement-home.blade.php:6` | **P3** | B | STILL-BROKEN (new) | N | N |
| **B3** | Pending visibility on holiday weeks (my-timetable/cohort) | Pending request covering an occurrence that falls on a public holiday (req #4 → wk-8 Mon Deepavali) | Pending state readable (yellow) per the state machine | Renders **event-conflict red** (holiday wins) — pending invisible on both my-timetable and cohort pages (wk-4 non-holiday pending renders yellow ✓) | (live class lists captured) | Grid builders colour by `day.holiday` before `e.status`; the trait marks the occurrence pending but the renderer precedence hides it | **P3** | B | STILL-BROKEN (new) | N | N |
| **A5** | Cancel-request toast (my-request-history) | Cancel a pending request → click the toast's **Undo** | Either a working undo or no undo affordance (design toast table: cancel = no undo; modal says "cannot be undone") | **Dead Undo button** — clicked, nothing happens; contradicts the modal copy | (live: rows unchanged after Undo click) | The component dropped undo callbacks by design ("the frozen modal copy itself says the action cannot be undone", MyRequestHistory.php:20–22) but the shared toast still renders the Undo button | **P3** | A′ (see re-clustering) | STILL-BROKEN (new) | N | N |
| **A6** | Week labels (arrangement) | Enter pinned wk 8; read slot trigger / strip vs week nav | One week-numbering convention | Trigger "W7 · Nov 09", strip "Week 7 · Monday, Nov 09 2026", nav "Week 8" — same week, two numbers | `evidence-5a-arrival-home-pin.png` | `conflictEventsPayload` emits 0-based `'week' => $week - 1` (ReplacementArrangement.php:250) which the picker/strip print raw; weekData labels are 1-based | **P3** | A | STILL-BROKEN (new) | N | N |

---

## Not-yet-built gaps (NOT bugs — tasks.md Phase 3/4)

| Gap | Evidence | Ref |
|---|---|---|
| PL email on submit (FR 4.15) not wired — `jobs` table stayed empty after the live submit; `SubmitReplacementRequest` dispatches nothing | jobs query: 0 rows; `.env` QUEUE_CONNECTION=database, MAIL_MAILER=log | tasks 3.1–3.4 unchecked (Slice C) |
| Request-approval real backend (`/request-approval-ui`) — still the frozen mock template per CodingMAIN §9 | CodingMAIN page table | Slice C |
| Cancel-class write path (FR 2.16) — action + modal exist; not exercised (write budget) | `app/Actions/CancelClass.php`, `partials/ui-cancel-class-modal` | Slice B remnant / untested |
| OCC loser path not exercised live (needs a second concurrent submitter = extra writes); automated coverage exists (`OccConcurrencyTest`, `ReplacementFlowTest` per tasks 2.1 ✅) | — | note only |
| `duration=0` accepted and silently shrinks the block (input validation nicety) | view :3053–3058 | mock-phase param debt |

---

## Cross-page pending-semantics verdict

The design **does** define one semantic, and four of five surfaces already implement it; only the venue page is out of line. The trait docblock (ResolvesTimetableTimeline.php:20–23) + the wire-backend design's cellState table + spec Amendment 2b say: a **pending** request marks the **original occurrence** (yellow "pending" on my-timetable and cohort pages — verified live, wk-4 occurrence renders `event-pending`); an **approved** request moves the occurrence to its new slot (status `replacement`); the **arrangement grid** shows the reserved NEW slot as "Pending (You)" (cellState rule 3 — verified live, wk-5 B110 Mon 14:00 `cell-pending`) and others' as grey "Reserved"; and **replacement-home** is a to-do list that hides occurrences with an active request and returns them on reject/cancel (D1 — verified live both directions with request #4). The intended reading of the user's home↔my-timetable "contradiction" is therefore: **home hides handled occurrences; timetables keep showing them with their state** — both are correct per design and now conform after `e99abbf`. The outlier is `VenueTimetable`, whose event source skips `class_session_id = NULL` rows, so reservation-state blocks (which never carry a class session) are invisible there (U2b) — its own legend already promises a "Pending" block, which is currently unreachable except for imported-pending rows. Secondary inconsistency: when the covered occurrence falls on a public holiday, the holiday rendering masks the pending state on both timetable pages (B3) — defensible precedence, but the pending state becomes unreadable; worth a decision (e.g. pending border/badge over the holiday fill).

---

## Priority-grouped summary (P0 → P3)

| Pri | ID | Bug (short) | Cluster | User? |
|---|---|---|---|---|
| **P0** | A1 | Week nav never rebuilds the arrangement grid (missing `window.buildTimetable` export) | A | — |
| **P1** | U2b | Venue page: pending replacements invisible (`class_session_id` NULL skip) | B | Y/2b |
| **P1** | U3 | Pending consistency sweep — venue leg broken (rest conforms) | B | Y/3 |
| **P1** | U1 | Submit dialog promises 4 slots; only the anchor slot is reserved | A | Y/1 |
| **P1** | U5b-a | slotTrigger change leaves type/cohorts/venues module-grain (stale context) | A | Y/5b |
| **P1** | U5b-b | No navigation to My Request History after successful submit | A | Y/5b |
| **P1** | A2 | Subject change doesn't re-render the grid (stale busy vectors) | A | — |
| **P1** | A3 | Venue dropdown filter contradicts engine/DB (`allowed_session_types`) | A | — |
| **P1** | B1 | P sessions labelled "(T)"/"Tutorial" on home + quick view | B | — |
| **P2** | U2a | Venue page Book Now removed (user-designed; restore + wiring spec) | C | Y/2a |
| **P2** | U5a | Dual entry-param schemes remain (legacy fallback parser) + `duration=0` not rejected | A | Y/5a |
| **P2** | U5b-d | Pinned-week occurrence not auto-selected for multi-conflict classes; 0-based week labels | A | Y/5b |
| **P2** | U5b-c | Failed submit: no grid re-derivation (copy/colour → P3) | A | Y/5b |
| **P2** | A4 | Week-nav confirmation guards lost in conversion | A | — |
| **P3** | U5b-c′ | "Submission Conflict" copy + error colour (NFR 3.3) | A | Y/5b |
| **P3** | A5 | Dead Undo button on cancel toast (contradicts modal copy) | A′ | — |
| **P3** | A6 | 0-based week labels in trigger/strip vs 1-based nav | A | — |
| **P3** | B2 | Home page `<title>` says "Replacement Arrangement" | B | — |
| **P3** | B3 | Pending state masked by holiday rendering on timetable pages | B | — |

## Proposed fix order (dependency-driven)

1. **A1 (+A4 in the same touch)** — export `window.buildTimetable` (and restore the guarded week-nav wrappers) on the arrangement page. Everything week-dependent is untestable until the grid rebuilds; this is pure view wiring, no data risk.
2. **A2 + A3** — re-render the grid on subject change; drive the venue dropdown filter from the same eligibility source as `blockedVenuesPayload` (engine `allowed_session_types`). Together these make the D4-rule-1 grid trustworthy, which the submit flow depends on.
3. **U2b (cluster B)** — venue-page pending events from `replacement_requests`; completes the one-pending-reality story (U3) and the demo narrative "submit → see it pending on the venue page".
4. **U5b-b + U1** — post-submit navigation target; align the block copy with the anchor-slot reality (or reserve the whole block if the user wants to retire the documented limitation — that is a spec decision, flag it).
5. **U5b-a (+A6)** — occurrence-grain context for type/cohorts/venues; fix the 0-based labels in the same pass.
6. **B1 (+B2)** — P→T label collapse in the shared helpers; home title.
7. **U2a (cluster C)** — Book Now restore on the venue page (purely additive; the arrangement-side consumer is already live).
8. **P3 polish** — U5b-c′ error colour/copy, A5 dead Undo, B3 pending-over-holiday decision, `duration=0` clamp.

### Re-clustering recommendation

- **A4 joins A1** (one conversion gap: the week-nav family — rebuild hook + guards — single fix site, `replacement-arrangement.blade.php` ui-week-nav include + exports block).
- **A5 (dead Undo)** sits on my-request-history, not the arrangement page; keep it with **A** anyway (the cancel flow is the arrangement's aftermath and shares the toast/modal vocabulary) rather than forking a 4th cluster — the user makes the final call.
- **A2+A3+U5b-a** share one deeper root (**arrangement context is module-grain and rebuilt only on venue/week paths**); if the fix plan introduces an occurrence-grain context object, all three collapse into one task — worth planning them as one A-sub-package.
- **U3 and U2b** are one root (venue-page pending rendering) — B already covers both.
- Clusters B and C stay as proposed; no cross-cluster merges found beyond the above.

---

## Write-repro ledger + cleanup SQL

**Performed (Part A step 7, minimally):** as staff 5770 — ONE submit (created request **#4**: session 97, week 8, slot **27758** = B110 Tue 16:30–17:00; slot → pending, version 1→2; audit row **#4** `submitted`) and ONE cancel of that same request (request #4 → `cancelled`, decided_at 2026-10-11 09:58:06; slot 27758 → available, version 2→3; audit row **#5** `cancelled`). No queue jobs were created (email = Slice C gap).

**Pre-existing rows NOT created by this audit (do not touch):** `replacement_requests` 1, 2, 3 (+ their audit rows 1–3 and slots 27309, 27373, 27851).

```sql
-- Cleanup for THIS audit's write-repro (run inside one transaction).
-- audit_logs.replacement_request_id FK is NO ACTION → clear audit rows first.
BEGIN;
DELETE FROM audit_logs WHERE id IN (4, 5);            -- this audit's submitted + cancelled rows
DELETE FROM replacement_requests WHERE id = 4;        -- this audit's request row (status 'cancelled')
UPDATE time_slots SET status = 'available', version = 1 WHERE id = 27758;  -- restore pristine cell
COMMIT;
```

Browser-side side effects (harmless, per-tab/per-origin): `localStorage['arrangementWeek']`, `localStorage['replacement-favourite'|'replacement-recent']` untouched-or-set during navigation, `sessionStorage['bookingIntent*']` absent (no venue handoff exercised). No files outside `.sdd/changes/replacement-arrangement-bug-hunt/` were created or modified.
