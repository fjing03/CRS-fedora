# explore-brief — `class-modal-affordances` (grilling receipts, 2026-10-24)

## 0. The problem (the user's live report + the verified regression)

The Cancel Class affordance was designed + shipped 2026-10-08 (SDD
`cancel-class-enhancement`, commit `4a387d1`) on the then-current
**legacy `ui-design-templates/*` pages** — the `CancelClass.renderButton(...)`
call sites lived ONLY there (verified: `git show 4a387d1 --stat`). The repo
then cut over to the LIVE Livewire blades; the shared class-detail modal
(`partials/ui-class-detail-modal`) got the **Replace Now** footer wired
(occurrence-week-pin-truthfulness) on my-timetable, but the
**Cancel render-call was never re-homed** — no live page calls it. Confirmed
2026-10-24: the ENTIRE `cancel-class.spec.ts` family is red (15 rows,
~5.4s each = the button never appears). Everything else is intact:
`CancelClass`/`CancelClassModal`/`ClassCancellation` modules in
`ui-common.js`, the `partials/ui-cancel-class-modal.blade.php` partial,
the `CancelClass` backend action, the ledger/undo machinery.

## 1. The scope decision (Q1, user option 1 — all three live pages)

The Cancel affordance restores on ALL THREE class-detail modals of the LIVE
blades — my-timetable (`classModal`), cohort-timetable (`eventModal`),
venue-timetable (`classModal`) — each wired at its own modal-open path with
`CancelClass.renderButton(footer, event, days, weekIndex, onCancelled)`;
the ownership/status/end-time/holiday guards live in
`ClassCancellation.isCancellable` (S1–S5 machmedia) untouched. The legacy
templates' wiring (CohortTimetable template ~:390, MyTimetable ~:207,
venue template ~:1023) is the PORT SOURCE for each page's callbacks +
guard context.

## 2. The venue page's Replace Now (the option-2 add, user-approved idea)

The venue page's `classModal` gains the **Replace Now** footer button for
OWN conflicted occurrences — the S5a semantics by construction: the venue
grid is week-scoped, so the modal opens on the VIEWED week's occurrence
(the week is already exact — no stamping hop needed; final wiring =
design territory). Hidden for other lecturers' classes and for
non-conflicted weeks. Q2's FACT-week-only semantics: adopted by
recommendation (recorded; veto possible at review).

## 3. The design constraint (the user's directive)

99% reuse of the previous design: **error-colored button, bottom-right of
the modal**. Mechanically: `CancelClass.renderButton` already appends into
`.modal-footer-right` when the footer has one, self-hides via the
existing guards, and styles via `.btn-cancel-class` when the page defines
it ELSE the theme's `.btn-danger` (error-token) ghost — both = the error
color at the footer's right. NO new UI beyond the restored button(s) (the
99.9%-UI rule; the modal partial/partial footer text untouched).

## 4. What is NOT in scope

- Any backend change (the `CancelClass` action, the ledger, the undo
  toast, the slot machine — untouched).
- The notif-btn sync lane (separate decision, parked).
- The arrangement popup / replacement-home affordances (already live).
- Any copy rewrite beyond the restored buttons (their labels are the
  oct-8 frozen copy: "Cancel Class?", "Yes, Cancel Class", "Keep Class",
  "Arrange Replacement Now", "Later").

## 5. The receipts (acceptance)

- `cancel-class.spec.ts` — the 15 red rows back to green ON THE LIVE
  my-timetable (S1–S15/S17; the family was green at the legacy cutover).
- NEW Playwright rows: cohort-timetable (own class → the button;
  others' class → none) + venue-timetable (own class → the button;
  others' → none; the conflicted occurrence → Replace Now; healthy
  week → none).
- Standing gates: pint/phpstan-1G/phpunit; the spot battery
  (`--grep-invert "TC70"`); the suites that touch these pages
  (page-changelogs entries for the three pages).

## 6. Rejected / parked

- Rewiring the LEGACY templates "back to life" — the live blades are the
  product; the legacy files are not rendered (the route renders the
  Livewire component since `mock_fallback` is false).
- A new "Cancel" entry point elsewhere (cards/actions) — the modal footer
  IS the previous design; nothing new.
- Fixing the notif-btn sync here — separate lane.
