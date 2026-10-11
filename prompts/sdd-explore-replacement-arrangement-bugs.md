# /sdd-explore — bug inventory: replacement-arrangement flow

> Paste this whole file into a fresh chat (or run it here). The session executes
> `/sdd-explore` with the brief below. Written 2026-10-11.

FIRST: load the sdd-workflow skill, then read AGENTS.md + CodingMAIN.md (§7 FRs,
§10 conventions) before anything else.

## Mission

Find EVERY bug in the replacement-arrangement flow (and the closely related
pending-data rendering on timetable pages) and record them in an explore-brief
under `.sdd/changes/replacement-arrangement-bug-hunt/explore-brief.md`.
The user already tried to test and was **BLOCKED** by these bugs, so your job is
to (1) formally capture + root-cause the user's list below, then (2) find MORE
bugs they couldn't reach. READ-ONLY audit: no fixes, no code edits, no commits,
no push — only the explore-brief, its SDD artifacts, and screenshot evidence.

## Coordination (critical)

- **Audit state @ start:** run `git status` + `git log -3`; note the hash and any
  dirty files. Expected baseline: clean tree at `e99abbf` or later.
- The Slice B session ALREADY landed fixes today (2026-10-11) that touch the
  user's symptoms — verify each one FIRST and label it:
  - `89843b0` restore frozen UI on my-request-history + replacement-history
  - `ee5e956` submit/cancel MethodNotFoundException fix (Livewire wire-proxy)
  - `d3d4d40` grid renders engine-excluded cells occupied (ends
    "free cell → conflict popup")
  - `e99abbf` occurrence identity end-to-end: entry `?session=&week=` handoff
    (D1 pair), home hides active-request occurrences, my-timetable touch
  → Every user symptom gets verdict: `FIXED-BY <hash>` / `STILL-BROKEN` /
    `NEEDS-RETEST`. Do NOT re-report a bug as open without retesting on HEAD.
- If the tree becomes dirty DURING your audit, the Slice B session is mid-edit
  again: mark findings in its files `[WIP-OVERLAP]` so that session absorbs them
  instead of double-fixing. Never touch, stash, commit, or revert its files.

## Part 0 — USER-REPORTED SYMPTOMS (reproduce + root-cause each first)

Verbatim from the user — preserve their numbering (no "4"):

1. "fix block reservation" — verbatim user note; clarify what this refers to
   (block/anchor-slot reservation semantics — spec says only the clicked anchor
   half-hour slot is reserved, the block's other cells stay available).

2. `/venue-timetable-ui`
   a. NO "Book Now" button — the user DESIGNED one and it was removed by AI.
      PRE-AUDIT EVIDENCE: frozen template has `.btn-book` inside
      `.available-tooltip` (template line ~197) + booking-banner/booking-hint
      styles; live view `resources/views/livewire/venue-timetable.blade.php`
      has **no `.btn-book`** (grep count 0, verified 2026-10-11), only hint
      text "booking arrives with the replacement workflow (Slice B)" +
      "READ-only v1". Changelog 2026-08-08 records "Pass code+cohort through
      when Book Now is clicked". Verify exactly what was removed vs kept, and
      what a faithful restore + Slice-B wiring requires.
   b. Data inaccurate: the user's OWN PENDING classes do not show on this page.

3. Timetable data inaccurate, especially PENDING, on `/cohort-timetable-ui` and
   `/venue-timetable-ui` — cross-check against `/my-timetable-ui` (user says
   check it too). One pending reality, four pages must agree.

5. `/replacement-arrangement`
   5a. Entry-parameter schemes were INCONSISTENT across entry points — test ALL
       THREE (i/ii may be unified by `e99abbf` — verify):
       (i)   `from=replacement-home`: `?session=AMSE1003&week=2026-11-09&from=replacement-home`
             (session = module code string, week = date string)
       (ii)  `from=my-timetable`: `?session=67&week=3&from=my-timetable`
             (session = numeric id, week = week number)
       (iii) `from=venue "Replace Now"`: `?code=AMCS2093&cohort=DFT2(S1)G1+DSF2(S1)G1&day=0&start=6&end=9&originalVenue=B110&duration=2&from=replacement-home`
       Each must load the correct class context (or degrade honestly) — check
       the parser handles all three, flag any remaining incompatible param
       designs as a spec-level defect.
   5b. (each bullet = its own bug row)
       - Changing the slotTrigger dropdown does NOT affect some data: cohort
         count, class type, available venues.
       - After a SUCCESSFUL submit the page should NAVIGATE to
         `/my-request-history-ui` — currently it doesn't.
       - After a FAILED submit the table cells should re-render/re-derive —
         currently they render EMPTY.
       - Error message must be clearer + use the error colour: current text
         "Submission Conflict" is vague/misstyled.
       - `from=replacement-home` entry does NOT auto-select the slots.
       - Changing venue doesn't reflect in the grid (user could not fully test).
       - Known-broken copy/behaviour: "Home hides covered occurrences — a
         pending/approved request removes that occurrence from the list; it
         returns automatically if the PL rejects or you cancel" — still errors;
         AND the covered occurrence should also be hidden on my-timetable
         (`e99abbf` claims partial fix — retest both directions).
       - Contradiction: the NEW pending replacement DOES appear on
         my-timetable ("Schedule / Venue B110 / Day MONDAY / Date 12 Oct 2026 /
         Start Time 11:00 AM / End Time 11:00 AM" — user quote says
         11:00 AM–1:00 PM) — so home hides while my-timetable shows; pin down
         the intended single semantic from design.md/spec and report both
         pages against it.

## Part A — Playwright hands-on run-through (DO THIS, don't only read code)

Restart the server with the two-command stale-cache rule (kill artisan serve,
then rm compiled views, then serve — never one combined string). Log in with
demo lecturer/PL credentials (CodingMAIN / dataset docs). Play systematically:

1. Entry paths: all THREE param schemes above; then MISSING params, garbage
   params, `duration=0`, `duration=99`, unknown session/cohort — must degrade
   honestly, never blank page or 500.
2. Venue dropdown: rapid repeated changes (debounce spam), disallowed venue
   type for the session (L/T/P filter), switch back — grid recalc <500 ms,
   never stale slots or endless spinner.
3. slotTrigger/subject dropdown (user bug 5b): change it and diff EVERY piece
   of page data before/after (cohort count, class type, available venues,
   grid) — catalogue exactly which fields fail to update.
4. Week nav: prev/next weeks 1–14, Today, beyond-range jumps, week change
   WHILE a slot is selected.
5. Grid clicking: select green, reselect another, deselect, multi-hour block,
   :30-start class, click occupied/reserved/pending cells (graceful refusal).
6. Navigation abuse: browser BACK after entering, BACK after selecting,
   refresh mid-selection, second tab, navigate away and return, logout
   mid-flow.
7. Submit + aftermath (the ONE allowed write-repro, see DB rule): success →
   toast + navigate to `/my-request-history-ui` + Pending (You) state; second
   browser context submits same slot → OCC loser (conflict alert in ERROR
   colour, grid re-derives winner as reserved, request row deleted, audit
   survives); failed submit → grid re-renders, not empty; then cancel the
   pending request and confirm the slot frees + home/my-timetable update
   consistently.
8. Pending-data consistency sweep (user bugs 2b/3): create ONE pending
   request, then visit my-timetable, cohort-timetable, venue-timetable,
   replacement-home — record what each shows vs the design's intended
   semantics (covered-occurrence hiding rule).
9. Cross-cutting: mobile viewport resize, toast/error styling, honest empty
   states ("No available slots"), stale-cache check BEFORE trusting any
   visual finding.

Save a screenshot per anomaly under
`.sdd/changes/replacement-arrangement-bug-hunt/evidence/` (`<bug-id>.png`),
reference it from the bug row.

## Part B — Code audit (back visual findings with root cause)

1. Entry param parsing (all three schemes) + defaults.
2. Grid render: 30-min granularity (addendum 2b: 2h = 4 cells, :30 starts),
   14-week navigation.
3. cellState precedence (design.md): occupied > reserved-by-others >
   pending(you) > selected > available — every branch incl. Public Holiday,
   multi-cohort sessions.
4. Venue recompute: 250 ms debounce, <500 ms budget, default = original venue
   (FR 2.5), L/T/P filter, venue rules (B005 no-Diploma, B006).
5. Block selection: whole-duration select, deselect/Clear, stale summary strip.
6. Submit + OCC: winner (slot→pending, version+1, audit, PL email queued) vs
   loser (request deleted, conflict audit survives — D5 pre-null FK order).
7. Post-submit: cancel pending, cancel class (5-value reason CHECK),
   navigation targets after submit.
8. Validation layers: RBAC + own-class scoping (FR 2.11), Form Request rules,
   engine four-vector (lecturer × cohort × room × capacity), DB partial
   unique `uq_replacement_requests_active_block` — find invalid input that
   PASSES and valid case wrongly BLOCKED.
9. Venue page "Book Now" removal audit (user bug 2a): diff frozen template vs
   live view around `.available-tooltip`/`.btn-book`; identify every file+line
   that dropped it and what a faithful restore + Slice-B wiring requires.

## Reference docs (read, don't modify)

- `.sdd/changes/wire-backend-into-refactored-ui/design.md` — "Slice B
  arrangement data flow", cellState table, state machine, D5/D7, Actions/Form
  Requests
- `.sdd/changes/wire-backend-into-refactored-ui/specs/replacement-flow-wiring/spec.md`
  (incl. Amendment 2b) — expected behaviour + FR numbers
- `.sdd/changes/wire-backend-into-refactored-ui/tasks.md` Phase 2 —
  not-yet-built ≠ bug; list those separately as "gaps"
- `page-changelogs/venue-timetable-ui-changelog.md` + replacement-home /
  replacement-arrangement changelogs for documented divergences

## DB safety

Demo DB `class_replacement_fresh`: NEVER re-seed/migrate. Prefer code-reading +
PHPUnit (disposable `class_replacement_testing`). The only write-repro allowed
is the submit/cancel in Part A step 7 — do it minimally, note every row
created/changed, and end the brief with exact cleanup SQL per row.

## Triage — rank & bucket every bug (feeds the fix plan)

Assign each bug TWO extra tags in the brief:

**Priority** (dependency order beats importance — a blocker always sorts first):

| Tag | Rule | Typical examples |
|---|---|---|
| **P0** | stops all further testing/demo flow — fix before anything else | broken entry params, empty grid after failed submit |
| **P1** | wrong data shown (fatal in viva/demo) OR the demo story can't complete | pending accuracy across pages, no navigation after success, venue change not reflecting |
| **P2** | user-designed element missing / visible design gap | Book Now button |
| **P3** | polish: wording, colour, minor styling | "Submission Conflict" message + error colour |

**Proposed SDD cluster** (provisional — you may recommend moving a bug if root
causes say so; the user makes the final call):

| Cluster | SDD (planned) | Scope | Bugs |
|---|---|---|---|
| **A** | Arrangement flow fix-pack | 1 page: replacement-arrangement | 1, 5a, 5b |
| **B** | Pending-truth consistency | venue/cohort/my-timetable/home | 2b, 3, home↔my-timetable contradiction |
| **C** | Venue Book Now restore | venue page | 2a |

Note: bugs sharing ONE root cause must land in the SAME cluster+SDD even if
reported as separate symptoms (`e99abbf` already merged 3 symptoms into
"occurrence identity" — expect more of these). Clusters A/B/C are a starting
proposal, not a verdict — if your root-cause analysis says two clusters are
really one (or one is really two), say so in the brief with reasons.

## Output format (explore-brief.md)

- "Audit state": git hash + dirty files at time of audit
- One row per bug: ID | flow step | exact repro | expected (FR/spec ref) |
  actual | evidence screenshot | root-cause hypothesis (file:line) |
  **priority (P0–P3)** | **cluster (A/B/C)** |
  status (`STILL-BROKEN`/`FIXED-BY`) | [WIP-OVERLAP]? | user-reported? (Y/#)
- Sections: Bugs (user-reported first) / Not-yet-built gaps / Cross-page
  pending-semantics verdict (one paragraph: what the design actually says)
- Finish with a **priority-grouped (P0→P3) summary table**, each line tagged
  with its cluster, plus a "proposed fix order" list respecting dependencies
  (entry works → then downstream testing possible).
- Report it back to me.
- **Do NOT run `/sdd-propose` — I decide next.**
