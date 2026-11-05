# Review log — venue-slot-validation

## Batch 1 (proposal) → Batch 4 (tasks) — 2026-11-05

No `@sdd-reviewer` subagent is available in this harness (pi), so the four batches were
self-reviewed against `explore-brief.md` before freezing instead of by subagent round. Recorded
honestly rather than skipped.

### 🔴 Fixed
- The proposal first allowed "keep `minClassSlots` if the hover still feels honest" — removed:
  the brief's §5 ("Do not reject a slot based on an assumed class duration") settles it, and the
  previous brief's "real class lengths" ask is superseded for the hover stage only.
- `#intentNotice` was going to be deleted wholesale; it still serves the room-type case
  (`fitting.length === 0`), so it stays and only its duration branch is removed.

### 🟡 Addressed
- Day-end failures needed their own copy — reusing the brief's "None of your classes can run"
  title would be untrue when an earlier cell works. Split into two copy rows in the design.
- Pre-selection: kept "first fitting row" so the modal still opens usable, with the first row as
  fallback when nothing fits.

### 🔴 Outstanding
- (none)

## Apply round — 2026-11-05

- `tests/venue-book.spec.ts` 19 passed · venue neighbours 61 passed / 2 skipped · full PHPUnit
  344 / 1899 · pint clean · PHPStan 0 · Blade cache compiles.
- Deviations from design: none. Two implementation facts captured in `tasks.md` (window export
  for inline `onclick=`; fixtures keyed on `data-slots`).
