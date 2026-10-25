# tasks — `cohort-venue-replace-now`

Each task ≤ 2h; the frozen design.md D1–D5 is the acceptance surface.

- [ ] **T1 — Cohort page markup + gate** (design D1): add the
  `btnReplaceNow` button (the venue markup module, `event.id`, `currentWeek
  + 1`, `'cohort-timetable'`) to the `@section('modal-footer')` right
  column; **AND** the `window.currentModalEvent` channel the page lacks
  (R1 blocker): the init near the bridge block + the assignment as the
  first statement of `openModal`; then the gate receipts at openModal
  (the isMine + status formula before the CancelClass.renderButton call);
  the supersession comment.
- [ ] **T2 — Venue page week fix** (design D2): `currentWeek` →
  `currentWeek + 1` at the :186 click arg; the dated comment receipt.
- [ ] **T3 — BackNavigator route row** (design D3): add
  `'cohort-timetable': '/cohort-timetable'` (CANONICAL — the `-ui` URL is
  the temporary redirect routes/web.php :99; review R1).
- [ ] **T4 — the NEW spec** (design D4): `tests/cohort-venue-replace-now.spec.ts`
  — the 5-row route + regressions; re-anchor any venue hand-off assertion
  in `tests/class-modal-affordances.spec.ts` if (and only if) it goes red.
- [ ] **T5 — Gates** (design D5): the new spec ×2, the re-run of the
  class-modal-affordances lane spec ×1, pint, phpstan-1G, phpunit 258,
  BOTH changelog dated entries, ONE conventional commit with explicit
  paths. **NEVER push.**
