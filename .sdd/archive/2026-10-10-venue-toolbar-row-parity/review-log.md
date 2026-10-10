# Review Log — venue-toolbar-row-parity

## proposal Round 1 — 2026-10-10
### 🔴 Outstanding
- (none)
### 🟡 Addressed (soft-freeze amendments, applied verbatim)
- **`venue-capacity` span recorded as a documented divergence** — it replaces fjing's fav-btn slot in the `venue-dropdown-wrap`; the success criterion now reads "matching the frozen fjing layout (the capacity span stands where fjing's fav-btn sits)". Prevents a verify-pass dispute over whether "matches fjing" is literal.
### 💡 Addressed
- Commit-hash provenance (`adc95bb`, `f8b35a2`) is pack-only in this clone (not resolvable as loose objects) — internally consistent with the clone timestamp; the root-cause narrative stands on current-state facts regardless.
### Verdict
**PASS — proposal FROZEN** (single-artifact micro-plus per user-approved lean pattern; the 🟡 fold-in applied as prescribed).

## verify — 2026-10-10
### Applied
- `resources/views/livewire/venue-timetable.blade.php`: the two `.semester-bar` rows merged into one (venue-dropdown-wrap + capacity span → `ui-week-nav` with `'showPrint' => true`); `#weekSubtitle` stays under the merged bar; fav-star ☆ stays dropped (documented divergence).
- `page-changelogs/venue-timetable-ui-changelog.md`: postscript appended.
### Gates
- lint ✓ (pint) · phpstan 0 ✓ · `php artisan test --filter=VenueTimetableTest` 9/9 (23 assertions) ✓ · Playwright `venue-db` 5/5 ✓ (post two-command restart)
- records-intact n/a (view-only change, no DB touch)
### Visual
- Screenshot post-restart (`.playwright-mcp/venue-toolbar-after.png`): venue dropdown + capacity + week select + Today + print stub on ONE row; `#weekSubtitle` under the bar; grid renders. Matches the frozen fjing layout and the cohort-timetable pattern.
