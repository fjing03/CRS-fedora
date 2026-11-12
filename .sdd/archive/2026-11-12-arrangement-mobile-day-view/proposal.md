# Proposal — Replacement Arrangement mobile day view

## Why

The current phone layout retains the complexity of a weekly desktop timetable:
wrapped time cells, sideways scrolling, disappearing day context, and actions
below a long week. Users cannot easily inspect a class-length selection or read
hover-only conflict explanations on touch screens.

Replace the mobile presentation with the user-approved daily timeline prototype,
while retaining the existing booking rules and keeping desktop unchanged.

## Approval and baseline

- User approved the mobile prototype, completed Explore, and requested SDD Propose.
- `explore-brief.md` contains the binding user decisions and source-code findings.
- Visual/interaction reference: `../ui-prototype/replacement-arrangement-mobile-preview.html`.
- Its sample timetable, fixture clock, simplified guards and mock submission are
  NOT implementation contracts. Existing system rules take precedence.
- This batch proposes **what and why** only. It does not authorise implementation.
- Apply was subsequently authorised. Baseline reconciliation on 2026-11-11:
  user chose the newer borderless arrangement design shipped in `28f9f4d`.
  This ruling supersedes prototype/earlier brief ownership rings and hint.
  A full-chain unfreeze/re-review precedes resuming Apply; prototype stays unchanged.

## Scope

Only `/replacement-arrangement` at viewport widths **<=768px** receives the new
presentation. At **>768px**, existing desktop styling, controls, tooltips, navigation
and booking interactions remain unchanged. Other pages must not change.

### 1. Original class context

Show subject and original date/time in a compact expandable section. Cohorts,
student count, original venue and other existing contextual facts expand on tap.
Retain access to subject and conflicted-slot selectors, including current defaults,
auto-selection, occurrence pinning and confirmation/restore behaviour.

### 2. Sticky navigation and eligible dates

Keep a compact venue/week control row and eligible-day picker visible while the
timeline scrolls. Use the existing real calendar and notice-period helpers.

- Hide Sunday, public holidays and dates that are too soon to book.
- Hide weeks containing no eligible dates. Do not hide an eligible date/week
  merely because its times are occupied.
- Changing week retains the viewed weekday if eligible; otherwise show the
  first eligible day. Browsing dates never creates a new time selection.
- Preserve the current Earliest bookable action and reveal its destination day.
- The initial mobile day must show a valid existing auto-selected/prefilled
  booking when present and eligible; otherwise an eligible day in the resolved
  current week. Exact initial/fallback mechanics will be specified in design.

### 3. Daily timeline and whole-class selection

Show one day as a vertical timeline of 30-minute rows with readable 24-hour axis
labels and touch targets at least 44px. Include all times for that date, not just green rows.

Green rows retain their current visual meaning. Tapping a green row invokes the
existing class-length fit/selection checks; do not introduce a separate valid-start
colour or change rules to make a choice fit. Display a selected span as one clear
blue block with its start/end time.

Tapping the selected block opens details instead of removing it immediately.
Removal is an explicit action in those details. This mobile-specific interaction
must not change the existing desktop removal behaviour.

### 4. Persistent summary and actions

A bottom action bar stays visible. Without selection it says **Select a time**
and disables Submit. With a valid saved selection it shows compact date/time,
access to details, and **Submit Request**, gated by existing checks.

The summary represents the saved booking even when users browse another day/week,
not just the current grid pointer. It must not create another booking store.
Tapping it opens the same details as tapping the selected blue block: full date,
time, duration and venue, with **Remove selection**.

Use the existing confirmation and real submission path. Moving or hiding controls
must not bypass confirmations, double-submit, or change the submitted occurrence.

### 5. Tap-open information and temporary feedback

- Conflict/pending rows open bottom sheets with the existing reason/holder facts.
  Retain existing ownership, permission and information-disclosure limits.
- **Colours & borders** opens the four current legend meanings: Available,
  Your Current Selection, Pending, Conflict. Pending and Conflict are borderless
  for both yours and others, matching the current canonical grid. Do not restore
  the obsolete ownership-border hint. Ownership/reason remains visible in current
  state wording and tapped details. The current selection keeps its existing blue
  outline; it is a selection indicator, not an ownership ring.
- Compact icon + **Tip** sits beside that button above the timeline and opens
  the current state-aware guidance by tap.
- Explicitly opened details/legend/Tip sheets remain until closed. Confirmations
  remain deliberate actions, never timed dismissals.
- Transient selection warnings/errors disappear after **5 seconds**. This does
  not silently dismiss server failure/critical confirmation information or clear
  a failed submission's saved selection.

### 6. Empty and constrained states

If no eligible dates remain anywhere in the semester, show:

> No bookable dates remaining this semester.

Disable date navigation and Submit, keep original class context accessible, and
do not display an unexplained blank timetable. This state wins over a saved
selection's Submit availability; do not silently erase the saved selection.

An eligible but fully occupied day still displays its blocked times and reasons.
Missing subject/original-slot states keep the existing booking gates and guidance.

### 7. Mobile usability and accessibility

- All mobile interactive controls have at least 44×44px touch targets, including
  day/week navigation, selectors, sheet Close/removal and bottom-bar actions.
- Sticky controls and fixed actions leave sufficient content/safe-area space;
  no time row, warning, sheet action or confirmation button is obscured.
- Wide readable rows, text labels as well as colours, and no page-wide sideways
  timetable scrolling on a phone.
- Bottom sheets have named headings, visible Close controls, keyboard dismissal,
  managed focus and focus return. Inactive desktop/mobile controls are not tabbable
  or exposed as duplicate interactive content.
- Resize/rotation preserves selection and navigation state without double handlers
  or a second booking. Light/dark themes and reduced motion remain supported.
- Current CRS tokens, typography conventions and date/time formats; no dependencies.

## Preserve unchanged

- Duration/budget, lecturer/cohort/venue availability, capacity/type filtering,
  public-holiday and notice-period checks, occurrence identity and permissions.
- Venue/subject/original-slot change guards, cancelled-change restoration, saved
  selection management, booking-intent arrivals, and leave/navigation protections.
- Backend submission, OCC, approval workflow, notifications and DB schema/data.
- Desktop and other timetable pages; this is not a global responsive redesign.

## Out of scope

- Multiple independent replacement bookings or changing the full-class selection rule.
- Desktop top-zone redesign (`prompts/replacement-arrangement-top-zone.md`).
- Changing the shared calendar, booking engine, state colour meanings or ownership rules.
- Copying sample subjects, occupancy or fixed prototype dates into production.
- Bulk cancel, other parked changes, new dependencies, live DB edits or deployment.
- Implementation, test execution, commits or pushing as part of this proposal batch.

## Expected impact and implementation risks

The main change is the page's mobile presentation and its integration with the
existing selection flow. Candidate touch points are the page Blade/CSS/JS, focused
page-scoped helpers/partials if needed, regression tests and its page changelog.
Exact file boundaries belong to design, not this first proposal.

The biggest risk is that current availability checks depend on the rendered table.
The design must preserve their behaviour without duplicating the booking engine.
It must also account for the saved-store/current-pointer distinction and existing
destructive-change guards, which the lightweight prototype does not fully model.

Shared files currently contain unrelated working-tree edits. Integrating a helper
does not permit reverting or committing that work. Changes should stay page-local
where possible and be reviewed separately if a shared component must change.

## Acceptance and later verification

1. Mobile matches the agreed prototype structure with real system data and rules.
2. Date/week visibility, weekday retention and empty-semester behaviour follow
   this proposal without hiding fully occupied eligible dates.
3. Full-span selection, saved-summary inspection and explicit removal work across
   day/week navigation and viewport changes; no selection is lost accidentally.
4. Pending/conflict, legend and Tip information is accessible by tap and keyboard;
   details remain open, while temporary selection feedback expires in 5 seconds.
5. Existing change confirmations, booking-intent handling and submit/server gates
   still work, with the original occurrence and requested slot unchanged.
6. Desktop appearance/interactions and other pages have no regression.
7. Test narrow phones, <=768px boundary and >768px desktop, both themes, rotation,
   safe areas, focus handling, full occupancy, missing subject and no eligible dates.

Later design/spec/tasks batches must map these to existing/new tests and honest
verification receipts. Playwright remains paused until explicit user permission;
writing browser tests run only on the guarded disposable testing DB/port, never
the demo DB. No browser or production-write permission is implied by this proposal.
