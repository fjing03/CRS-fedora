# Cohort and Venue Timetable — responsive prototype port

Status: IMPLEMENTED — compact SDD verification PASS (2026-11-17).
Both ports, the Venue status-colour regression and the final Cohort wide-touch row
correction are complete. Manual-device and real-data coverage limits are recorded below.
Independent review PASS; recommended clarifications incorporated.
User approved this plan, including retaining the current Select-a-Session popup;
that popup may be updated as a separately approved change later.
Format: user-approved compact lane (one artifact, one independent review round).
Design guidance: Impeccable `adapt`, explicitly confirmed by the user during apply;
the prototypes are visual authority and this compact plan is the delivery checklist.
No extra planning cycle.

## Why

The two live pages still stack the week on smaller screens. Port their existing
responsive prototypes, not a fresh design and not a forced copy of My Timetable.
The arrangement grid is explicitly excluded: the user chose to keep it unchanged.

## Authority

- `ui-prototype/venuetimetable/venue-timetable-responsive-preview.html`: opening
  DESIGN CONTRACT and inline VENUE CONTRACT comments.
- `ui-prototype/Cohort timetable/cohort-timetable-responsive-preview.html`:
  opening design contract, especially sections 3–11, 16 and 17.
- Current live data, permissions, booking checks, class detail/cancel flows and
  `timetable-print-v2` paper output win over mock-only behaviour or old prototype
  print implementations. Prototype fixtures are never production data.
- PRODUCT.md: task-focused web app; confirmed repository UI/DB safety rules apply.

## Scope and decisions

### 1. Preserve each prototype's layout bands

| Page | Width/input | View |
|---|---|---|
| Cohort | below 744 | selected-day class cards |
| Cohort | 744–1023 | two-day vertical timeline |
| Cohort | 1024–1279 | three-day vertical timeline |
| Cohort | 1280+ with touch capability | four-day vertical timeline |
| Cohort | 1280+ mouse-only | existing full-week desktop grid |
| Venue | below 768 | one-day vertical timeline with bookable free cells |
| Venue | 768–1023 | two-day vertical timeline |
| Venue | 1024–1279 | three-day vertical timeline |
| Venue | 1280+ | existing full-week desktop grid |

These differences are intentional: do not silently standardise 744 to 768,
turn Venue into a read-only card list, or impose Cohort's wide-touch band on Venue.
Keep selected week/day across resizing; seven-day controls scroll rather than shrink.

### 2. Controls and summary

- Cohort: preserve Faculty/Cohort selection, taught markings, disabled states,
  week/day persistence and Today. Toolbar remains in normal page flow.
- Venue: preserve room picker, week, Today and Earliest bookable. Below 1280,
  room selection scrolls away while week/day controls stay pinned under navigation.
  Touch picker works on wide touch devices too; mouse cascade remains intact.
- Summary remains a whole-week summary, never just a visible-day count. Move the
  existing cards/legend into the responsive Week summary sheet without duplicating
  their IDs or letting SummarySheet and TimetableResponsive both own the nodes.
- Cohort Print becomes visible only in full mouse-desktop mode (a deliberate
  change from live, matching prototype R7). Venue hides Print only below 768;
  tablet/desktop retain it. Hiding controls never changes paper output.
- Venue's semester badge below 768 uses the semester code plus Semester without
  dates; tablet/desktop retain the dated badge.
- Summary cards remain in-page at widths >=1280; below 1280 they move into the
  sheet. This also applies to Cohort's wide-touch timeline.

### 3. Class content and interaction

- Follow prototype hierarchy: time/venue first; course code/type secondary;
  note last. Full subject names remain in details, accessible names and tips.
- Preserve status colours, own/other border distinction and overlap explanations.
  Keep live class-details, Arrange and Cancel permissions and confirm flows.
- Cohort cards and timeline blocks open the existing live class details.
- Venue FREE cells stay interactive: reuse the live intent/session selector and
  existing route handoff to arrangement; never copy mock preview booking actions.
  Explicit proposed exception to the prototype: retain the shipped responsive
  Select-a-Session modal instead of porting its prototype sheet restyle/custom
  touch Subject option list. User approval of this plan must cover that exception.
- Preserve the complete live cell-state vocabulary: a mid-day free gap that is
  too short before the next class remains not bookable with its current normal
  background and Not Enough Slot hover label (`.cell-no-room`). Do not revive
  the retired red `.cell-no-fit` paint. Implementation inspection corrected the
  reviewer's stale description: current blade lines 73–82 and paint loop use
  green `.cell-no-room`; Authority's live-behaviour preservation takes precedence.
  The end-of-day 18:30 rule below likewise keeps green plus disabled.
- Venue ends with a non-interactive 19:00 marker, with exactly 22 half-hour slots.
  A genuinely free 18:30–19:00 cell stays green but cannot start a booking: show
  Not Enough Slots on hover/focus, mark disabled and prevent activation. Do not
  override occupied, holiday or too-soon states with that label.
- Earliest bookable button and green day dot use one existing eligibility lookup;
  jump to and highlight the same day. Today highlights the whole visible day.
- Empty-day booking hints appear only where booking is genuinely allowed.

### 4. Touch, keyboard and empty states

- Real interactive targets at least 48×48 on `any-pointer: coarse`, including
  wide tablets. Native select options remain exempt under the user's ruling.
- No hover effects on non-hover devices; retain visible keyboard focus and
  details access. Summary/picker dialogs trap focus, Escape closes and focus returns.
- Carry prototype holiday/empty-day structures where already specified. A holiday
  notice is additive: its class cards remain visible. Do not invent cancellation
  reasons for empty weeks.
- Do not roll out the prototype's parked four-page wording changes (§16) in this
  two-page lane. Keep My/Student My Timetable behaviour and copy unchanged.

### 5. Reuse and isolation

Inspect the full prototype renderers and current shared responsive implementation
before implementation. Reuse TimetableResponsive and existing partials; add
explicit opt-in page configuration/hooks for page bands, class actions, Venue
free-cell rendering and pinned controls. Specifically extend configurable phone
boundaries, per-band visible-day counts, a touch-capability media-query listener
beside the existing width listeners, and a free-cell rendering/activation hook.
Do not fork the shared module or paste
whole standalone prototypes into blades. New configuration must default to the
current My/Student behaviour. Preserve one source of truth for week, events,
selection and booking eligibility. Page-specific styling stays scoped; shared
changes must not alter unrelated pages.

Expected targets: `public/js/ui-common.js`, scoped `public/css/theme.css` only
where existing shared CSS needs opt-in support, the two live timetable blades,
responsive partials if reuse needs them, focused read-only browser checks and
matching page-changelogs. No backend rule/schema/dependency changes.
Do not edit prototypes or ui-design-templates.

## Acceptance and evidence

- Compare live pages against their prototypes using matching viewport, input,
  theme and comparable event states; mock counts need not equal real DB counts.
- Check 320, 375, 390, 600, 743, 744, 767, 768, 800, 1024, 1100, 1279,
  1280, 1440 and 1600 widths with
  touch/mouse where a band differs; both themes. No page-level sideways overflow.
- Exercise day/week/entity changes, resizing, Today, Earliest bookable, summary
  open/close, keyboard focus and empty/holiday/overlap states without data writes.
- Check Venue free-cell handoff only up to selection/navigation; never submit,
  approve, cancel or create test fixtures on the protected manual database.
- Compare desktop and printed output before/after. Retain the shipped A4 paper
  builder, filters, legend and modal choices; prototype print summaries do not port.
- Recheck My and Student My responsive views after shared changes.
- Run JS syntax checks, focused read-only behaviour receipts, and Impeccable
  detector once after the finished UI. Report physical iPad/Safari/gesture gaps
  honestly; a narrow mouse screenshot is not touch evidence.

## Tasks (each <=2 hours; split further during apply if needed)

- [x] Read both complete prototypes and companion notes; capture baseline views
      and map live render/action hooks against the requirements above.
- [x] Review this compact proposal independently; resolve findings and obtain
      the user's approval before touching application code.
- [x] Add opt-in responsive configuration/extensions with unchanged staff/student defaults.
- [x] Port Cohort toolbar/day/cards/window/summary integration and page bands.
- [x] Port Venue toolbar/day/timeline/summary, picker and booking handoffs.
- [x] Check touch, keyboard, empty/holiday/overlap states and late-slot guard.
- [x] Capture paired responsive/desktop views; verify existing print output and
      staff/student shared-module regressions using read-only receipts.
- [x] Run final detector, update page changelogs and record verification.

## Review and baseline

Independent read-only pi reviewer: PASS, no blocking findings.
Receipt: `/tmp/cohort-venue-responsive-review.txt`.
The five recommended clarifications (mid-day no-fit state, semester badge,
shared extension points, interior-width checks and explicit session-modal
exception) are incorporated above. Implementation started after explicit user approval.

Before-captures: `ui-prototype/Cohort timetable/crs-cohort-venue-baseline/`
(moved there by the user). 20 page/theme/device combinations, each with full-page
and viewport captures, plus receipt.json. Captured on HEAD 57f740e, Week 9,
Cohort FOCS/DSF1(S1)G1 and Venue B005. These are read-only baselines, not prototypes
to edit. Chromium touch emulation is not physical-device evidence.

### Apply finding — Venue responsive status colours (user report)

User supplied two screenshots and explicitly confirmed this bug is Venue-only,
non-desktop. The existing scope requires preserving status colours. A read-only
same-event comparison reproduces grey responsive normal blocks versus green desktop
blocks: `node /tmp/crs-venue-colour-repro.mjs` exits 1. The six normal/pending/
replacement ownership colour rules in `theme.css` currently match only `.timetable`.
Extend those existing token rules to Venue's window renderer only; preserve desktop,
Cohort, My/Student, conflict/cancelled/holiday styling and ownership borders. Add a
focused computed-colour regression check. No new design or planning cycle is needed;
this corrects the approved port's colour-preservation requirement. Verification is
not complete until this reported defect is corrected and checked.

**Resolved:** the six existing ownership/status rules now also match
`.tt-responsive[data-tt-page="venue"][data-tt-mode="window"] .tt-card` with their
corresponding status class. Token declarations and desktop selectors are unchanged;
no other page's responsive palette was changed. The original real-event comparison
now passes. New V12 colour checks passed in actual light and dark root themes at
390/768/1024/1279: six status/ownership variants per width, 48 comparisons overall,
including background, text colour and border colour/width. Variants are browser-local
clones of a served event through the real card renderer, not database fixtures.
Receipt: `/tmp/venue-colour-green-themes-verified.log` (2 passed); regression source:
`tests/cohort-venue-responsive.spec.ts`. Only these focused checks were rerun for the
CSS fix, not the whole suite. Handoff: `/tmp/venue-responsive-colour-fix-handoff.md`.

## Apply verification receipts

- Combined pre-colour-report responsive suite: **61/61 passed** (28 Cohort/Venue,
  33 existing My/Student), `/tmp/cohort-venue-final-61-clean.txt`. Earlier reds from
  expired staff cookies and the old Venue-has-no-responsive-root test guard were
  diagnosed; ordinary UI logins refreshed auth, and only that obsolete guard was
  updated. The later user report exposed missing paint coverage, now supplied by V12.
- Boundary/theme/input probe: **88/88 passed**, widths 320–1600, no page-level
  sideways overflow or JS errors. `/tmp/crs-cohort-venue-after/receipt.json`.
  Final Venue captures after the heading/tooltip/week-bridge fixes:
  `/tmp/crs-cohort-venue-finished-venue/`. Scrolled measurements show the room row
  leaving view while week controls remain at top 56px below navigation.
- Paper: **6/6 exact HTML hashes match** the pre-apply baseline (week, normal,
  all-period for both pages), `/tmp/crs-cohort-venue-print-after/receipt.json`.
- `node --check`, both Blade PHP syntax checks, Composer lint and PHPStan passed.
  Existing local PHP SQLite-extension startup warnings remain environmental.
- One full Impeccable detector scan was run and triaged: four unchanged warning
  locations; eight changed-line font-size advisories reflect the approved prototype/
  incumbent sizes, not a documented DESIGN.md ramp (no DESIGN.md exists). Raw scan:
  `/tmp/cohort-venue-final-impeccable.json`; changed-line triage:
  `/tmp/cohort-venue-final-impeccable-triage.json`. The later Venue colour fix only
  groups existing token rules with scoped selectors; manually reviewed, no repeat scan.
- **Final known gap resolved:** Cohort >=1280 coarse-pointer rows were 44px because
  the later, more-specific wide-touch rule overrode the global 48px rule. The wide-touch
  rule now uses 48px. Before/after computed geometry at 1366 touch: 62px header plus
  22×44px → 62px plus 22×48px, four visible days and zero overflow. Cohort 1440 mouse
  remains full-week; Venue 1024 touch remains 22×48px plus its 24px endpoint row.
  Read-only geometry probe `/tmp/widetouch-rows-probe.js`; handoff and viewport captures
  `/tmp/cohort-wide-touch-final-fix-handoff.md` and `/tmp/cohort-widetouch-after-{light,dark}.png`.
  No behaviour suite or detector repeat for this one CSS-value correction, following
  the user's pure-CSS rule; final CSS diff manually reviewed.
- Honest manual gaps: physical iPad/Safari/gestures not tested; the current protected
  DB has no reachable cancelled-history/free-offer base-lane example, so that new lane
  has structural/code evidence but no real-data screenshot. No DB fixtures were added.

## Compact SDD verification — 2026-11-17

**Tasks:** 8/8 complete. **Scope:** both prototype-specific sets of bands, toolbar/
summary behaviour, live details and booking handoff are implemented. The approved
Select-a-Session popup exception is retained. Arrangement, prototypes, database,
backend rules, dependencies and My/Student behaviour are unchanged by this lane.
The single existing My/Student receipt's obsolete Venue-has-no-responsive-root assertion
was updated; its guide-layout isolation assertions remain intact.

**Design:** opt-in shared TimetableResponsive configuration/hooks, not a fork or copied
prototype renderer. Selection and room-aware eligibility remain page-owned; summary
nodes move without duplicate IDs. Venue bootstrap now synchronises the recalled week
bridge, so a room change preserves the same rendered week. Status colour rules reuse
the existing tokens and preserve ownership borders. Paper HTML is unchanged.

**Quality gates:** combined 61-case responsive receipt passed before the reported colour
fix; the two new V12 cases subsequently passed 48 colour/border comparisons with actual
light/dark root themes. The final row correction was measured separately, not presented
as another suite run. JS syntax, three Blade/partial PHP syntax checks, Composer lint,
PHPStan (`--memory-limit=1G`, zero errors) and whitespace checks passed before commit.
Final static receipts: `/tmp/cohort-venue-commit-lint.txt`,
`/tmp/cohort-venue-commit-types.txt`. No PHPUnit or business-data writes were performed.

**Verdict: PASS — implementation and automated verification complete**, with the manual
coverage limits above disclosed, not claimed as tested. Ready for local commit.

**Git authority:** the user requested a commit once complete (2026-11-17). Only this
lane's ten implementation/test/changelog/proposal paths were committed (`e386106`).
Unrelated `.gitignore`, user-added baseline files and other sessions' work remained
excluded. The user then authorised archiving; this artifact now lives under
`.sdd/archive/2026-11-17-cohort-venue-responsive-port/`. Push is still **not**
authorised.
