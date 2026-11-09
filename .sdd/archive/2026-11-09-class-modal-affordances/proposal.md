# proposal — `class-modal-affordances` (the Cancel/Replace restore, user-approved 2026-10-24)

## Why

The Cancel Class affordance (SDD `cancel-class-enhancement`, 2026-10-08) was
built and test-proven on the legacy `ui-design-templates/*` pages, and was
**lost in the cutover to the live Livewire blades**: no live page calls
`CancelClass.renderButton(...)` today (verified), so the entire
`cancel-class.spec.ts` family is RED (15 rows) and the user sees no Cancel
button on the class detail modal — the Oct 8 design's "it gone" report.
The user's option-2 add: the **venue page's class modal has no Replace Now
either** (only my-timetable's modal carries it).

## What this change does

1. **Restores the Cancel affordance on all three live class-detail modals**
   — my-timetable (`classModal`), cohort-timetable (`eventModal`),
   venue-timetable (`classModal`) — each wired at its modal-open path with
   the documented `CancelClass.renderButton(...)` call (the legacy
   templates' wiring as the port source). The guards (own class, status
   normal, end-time future, holiday, pending non-cancellable) live in the
   existing `ClassCancellation.isCancellable`; the modal, the reason
   picker, the undo toast and the ledger are RETAINED-untouched.
   **Include re-homing (🔴1):** `partials/ui-cancel-class-modal.blade.php`
   (the cancel modal's overlay markup) was installed on the LEGACY pages
   only — each LIVE blade gains the include beside its modal markup
   (cohort `:159` / venue `:176` beside the shared
   `ui-class-detail-modal` include; **my-timetable: beside its own
   inline modal markup** — a page lacking it renders a dead open()).
   **The currentUser bridge (round-2 🔴):** the ownership guards read
   `e.lecturer === MockData.currentUser.name` — my-timetable (:122) and
   cohort (:169) hydrate `MockData.currentUser` from the payload but the
   VENUE blade does NOT. The venue page gains the same ONE-LINE hydrate
   here, else the restored button reproduces the wrong-lecturer/gone
   failure this lane exists to repair.
   **PERSISTENCE DECISION (the user, 2026-10-24, verbatim intent):
   RESTORE-FIRST** — the Oct 8 client cancellation (in-memory status
   flip + the sessionStorage ledger + the reload-resurrects semantics)
   is restored VERBATIM; the `CancelClass` action stays UNROUTED (the
   real DB cancellation = its own later lane; the cancel remains
   visual-only + until-reload, recorded honestly in the changelog).
   **FIXTURE REGIME (🔴2a):** the 14 spec rows were authored against
   the mock's §2.6/§2.7 facts; the LIVE blades serve the real DB — each
   row gets re-anchored to the live deck with per-row receipts. The
   actors exist (RE-VERIFIED today): staff 5770 owns BMIT2013 L (DB
   day_of_week 1 = TUESDAY @ B009 — the proposal's earlier "Mon B009" was
   the mock's day-label heresy and is hereby corrected) + BMIT2013 P Wed
   B010, and AMCS2093 rows; 2873 owns AMIT2014. The anchor week must
   carry the STATUS fact the row's guard needs (`normal` shows the
   cancel; conflict-flavored weeks exist in the mock history — the
   re-anchoring reads the recreated facts, never the mock's statue). A
   row whose fact no longer exists re-points or skips honestly with
   evidence — never faked.

2. **Adds the Replace Now footer button to the venue page's `classModal`**
   for OWN occurrences that carry the conflict fact for the VIEWED week
   (the FACT-week-only law by the page's week-scoped construction) —
   hidden for others' classes + healthy weeks. Same
   `goToReplacementFor(sessionId, week, from)` handoff as my-timetable.
   **The FULL required Playwright row set (not 'possibly'):**
   (a) own class → the Cancel button; (b) others' class → none;
   (c) the conflicted occurrence → Replace Now appears; (d) a healthy
   week → none.
3. **The user's design directive:** the restored Cancel button = error
   color, bottom-right of the modal — `CancelClass.renderButton`
   appends into `.modal-footer-right` when one exists; the shared
   partial has `.modal-footer` + `@yield('modal-footer')` (no right
   group) — **the pages lacking a footer-right (cohort AND venue first-class scope pages) get a
   page-supplied footer (left: Close;
   right: the restored buttons)** so the directive holds everywhere;
   styling via the page's `.btn-cancel-class` when defined else the
   theme's error ghost (`.btn-danger` token) — 99% previous-design
   reuse, no new UI. The blast radius is bounded: the student pages include
   the same partial but stay UNWIRED (a student's cancel is not a lecturer
   action; the student flow unaffected).

## Explicitly OUT of scope

- Backend changes (the action, the ledger, the undo toast, the slot
  machine — untouched).
- The notifications-panel data sync (parked as its own lane).
- Any copy changes; the Oct 8 modal copy stays frozen verbatim.
- The legacy templates (not rendered; not "restored to life").

## Impact scope (files)

- `resources/views/livewire/my-timetable.blade.php`,
  `resources/views/livewire/cohort-timetable.blade.php`,
  `resources/views/livewire/venue-timetable.blade.php` (wiring at each
  page's modal-open path + page-styles where the legacy button styling
  needs re-homing);
- `page-changelogs/*` (three page receipts);
- `partials/ui-cancel-class-modal.blade.php`: NOT changed — only its
  include added to the three live blades;
- tests: `tests/cancel-class.spec.ts` — 14 rows RE-ANCHORED to the
  real-DB deck (back to green) + the four new venue rows (proposal item
  2) + the cohort wiring rows (a FOCS-free fixture stays its own);
- the venue Replace Now toggle is page JS — so `ui-common.js` is
  expected-untouched BUT CONDITIONAL (the port may reveal a doc/comment
  need); the page-blade scripts carry the wiring.

## Acceptance

`cancel-class.spec.ts` green on the LIVE pages; new cohort/venue rows;
standing gates green (pint/phpstan-1G/phpunit; the spot battery with
`--grep-invert "TC70"`).
