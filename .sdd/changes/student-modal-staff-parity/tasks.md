# Tasks — student-modal-staff-parity

Frozen tasks (each ≤ ~2 h). Order matters: F2 first (the data), then the pop-up, then receipts.
Nothing here is started — apply waits for the user's approval.

- [x] **T1 — promote the cohort label to one shared rule** (design F4/F5)
      NEW `app/Support/CohortLabel.php` with `CohortLabel::spaced(?Cohort $cohort): string`, body moved
      verbatim from `ReplacementHome::spacedCohortLabel()` (`:396`);
      `ReplacementHome` keeps its private method as a one-line delegation (`return CohortLabel::spaced($cohort);`)
      so its payload stays byte-identical.
      Receipt: `replacement-home` payload for one row unchanged (same cohorts array before/after).

- [x] **T2 — the student row gains three display facts** (design F2)
      `occurrenceRow()` adds `cohorts` (`CohortLabel::spaced` per cohort), `totalStudents`
      (`$session->cohorts->sum('student_count')`, `0` ⇒ the view prints `—`) and `statusShort`
      (`$vocabulary['short']`). Comment states: display facts only, no leave reason.
      Receipt: feature test asserting the three keys for a known occurrence.

- [x] **T3 — the footer becomes static** (design F1a)
      Add the `@section('modal-footer')` block with `Close` (`.btn-outline`, left) and
      `#stJumpTimetableBtn` (`.btn-action` + calendar glyph, `display:none`);
      delete the `jump`/`jumpCall`/`insertAdjacentHTML` block and the
      `.modal-footer .btn-jump-timetable` CSS rule;
      add `currentReplacement`, `jumpToTimetable()` and the `window.jumpToTimetable` export;
      gate the button on `approved`/`completed` + request id + requested slot.
      Receipt: a freshly cancelled row shows no jump; an approved row shows it, on the RIGHT.

- [x] **T4 — the pop-up body becomes the staff shape** (design F1b–F1d)
      Replace `openClassModal({ groups })` with `DetailModal.render({ modalId, title, subtitle,
      factsHtml, tabs })`: title `Replacement Details`, subtitle `CODE — Name (Type)`, strip carrying
      only the full conflict badge, tabs Requested → Original → Class Info → Request Status (Short),
      `DetailModal.selectTab('class-info')` when there is no requested tab.
      Delete the `Course` and `Conflict Reason` groups.
      Receipt: the S1/S3/S4 scenarios from the spec.

- [x] **T5 — receipts, gates, changelog, commit**
      Playwright pass on the four scenarios + 390px (S5); `composer run lint:check`;
      `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`; `php artisan view:cache`;
      full `php artisan test` on `class_replacement_testing` (expect the known stale
      `ReplacementHistoriesTest` error to remain, nothing new);
      prepend the changelog entry (decision record, the footer flip defect it fixes, the parked items
      B8/B10 and the "no leave reason for students" ruling);
      commit ONLY: the two blade/PHP edits, the new support class, the test, the changelog, this SDD
      directory. Never stage `docs/demo-db-data-changes.md`, the other session's
      `arrangement-mobile-*` files, or the parked table-width edit. **Never push.**

## Definition of done

Spec R1–R6 all hold on the demo data; the five receipts above are green or explicitly waived by the
user; the walk-through difference between the two pop-ups is gone.

## Applied 2026-11-11 — notes from the build

- **The modal width moved 616 → 680 px (user's ruling, mid-lane).** Four tabs needed 606 px; the body
  gave 570. The user chose "expand the modal's width" over shortening a label or dropping the Class
  Info tab. The ONE-width law was kept: `theme.css` `.modal.modal--detail` plus the three shells that
  set it (this page's inline cap, `replacement-home`, `request-approval` ×2) moved together, and
  `ModalConsistencyTest` pins 680 and asserts none was left behind.
- **The phone tab row wraps below 520 px (user's ruling, same day).** 402 px of labels in a 304 px rail
  left the fourth tab unreachable; the staff pop-up's three tabs had the same clip first. Wrapping
  shows every tab at any width; the desktop rail is unchanged because it already fits.
- The row's `cohorts` key forced an eager load (`cohorts.programme`) so the new keys cost no query
  per row; `CohortLabel::spaced()` is the one formatter, and `ReplacementHome` now delegates to it.
- `statusShort` speaks the STUDENT word for the unarranged state (`Awaiting Arrangement`) — the staff
  page's own wording stays off this page (R2).
