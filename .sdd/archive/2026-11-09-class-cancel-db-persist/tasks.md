# tasks — `class-cancel-db-persist`

(≤ 2 h each; the frozen proposal + the design D1–D6 = the acceptance
surface.)

- [x] **T1 — the server** (design D1–D2): NEW
  `app/Livewire/Concerns/CancelClassBridge.php` (cancelClass +
  cancelClassUndo, ownership-scoped, both `#[Renderless]`); the Action's
  `REASONS` widens 10 + the `?string $otherDetail` signature + the audit
  details ride; `CancelClassRequest` mirrors + `validReason()`; the four
  components' `use` lines (MyTimetable, CohortTimetable, VenueTimetable,
  ReplacementHome); the ReplacementFlowTest :356 comment re-key.
- [x] **T2 — the migration** (design D5): NEW
  `*_widen_cancel_reason_and_audit_checks.php` (the two CHECK widenings;
  the down() hazard-comment stance); the demo-DB SQL sheet (from the
  frozen proposal verbatim) appended to `docs/demo-db-data-changes.md`
  (the convention's paste-target).
- [x] **T3 — the client** (design D4 + the round-2 receipts): the REASONS
  data-pairs + the radio build + `validate()` re-keys + `_refreshGate`'s
  `'other'` literal; the confirm await-POST + the pending-disable + the
  server-truth adoption; `exceptionId` rides the ledger entry;
  `undoViaServer()` promoted with both surfaces plugged in — the toast
  callback awaits BEFORE the deep-link (the async-IIFE wrap), the home
  chip pending-disables its Undo button, and the trait wraps
  delete-then-audit in `DB::transaction` (no deserted audit row).
- [x] **T4 — the home page** (design D4.4): the `badgeClass` map + the
  badge CSS block (the 5 token pairs) + the titleCase extension
  (`medical_leave` → 'Medical/Sick Leave', `other` → 'Other',
  `hospitalisation/maternity/compassionate/paternity_leave` natural).
- [x] **T5 — the record-keeping** (the D-6 gates): the four changelogs
  (my-timetable, cohort, venue, home); the standing gates = pint +
  phpstan-1G + `node --check` + phpunit 258 (NO Playwright run — the
  user's regime); the ONE conventional commit; NEVER push.
