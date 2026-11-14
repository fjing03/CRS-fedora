# Tasks — facts-fit-content

> **Apply status:** frozen 2026-11-14 (follow-on to `modal-responsive-width`).

- [x] T1. Add `.modal .detail-facts { width: 0; min-width: 100% }` beside the base rule.
- [x] T2. `ui-intent-session-modal.blade.php` 560 → 680; `replacement-home.blade.php` 635 → 680.
- [x] T3. `ModalConsistencyTest`: assert the rule + both maxima (reshape the width law test, do not delete it).
- [x] T4. Measurement: clone-shell before/after + strip height; note that the real pop-up lives on the demo lane.
- [x] T5. Changelog entry appended to `modal-responsive-changelog.md`; `php artisan view:cache`; `git diff --check`.
- [x] T6. Run `modal-responsive-width.spec.ts` + `ModalConsistencyTest`; commit only this lane's files (never push); archive only on an explicit yes.
