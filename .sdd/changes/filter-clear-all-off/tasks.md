# tasks — `filter-clear-all-off`

- [x] T1: `my-request-history.blade.php` — the new `clearAllFilters()`
  (the resetAllFilters body with `hideCompleted.checked = false`), the chip
  button's onclick → `clearAllFilters()`, the `window.clearAllFilters`
  export beside :998; the `#resetFilters` button untouched.
- [x] T2: `request-approval.blade.php` — the new `clearAllFilters()` (the
  resetFilters body with `statusFilter = 'all'` + BOTH hideCompleted
  statements flipped), the chip button's onclick → `clearAllFilters()`, the
  explicit `window.clearAllFilters`/`window.resetFilters` exposure; the
  `#resetFilters` button untouched.
- [x] T3: `tests/status-tooltips-filter-chips.spec.ts` — the law flip on
  T5.MRH-2 + T5.RA-3 (the bar EMPTY post-clear + the controls at OFF + the
  ✕ reorder on RA); everything else byte-stays.
- [x] T4 (the gates): the status-tooltips spec 9/9 (--grep-invert "TC70");
  pint ✓; phpstan-1G 0; the full suite 277/277. NO push; no DB writes.
- [x] T5 (the docs): the arrangement/my-request-history/request-approval
  changelog entries — the dated amendment, the user's ruling, both spec
  flips; the review-log receipts.
