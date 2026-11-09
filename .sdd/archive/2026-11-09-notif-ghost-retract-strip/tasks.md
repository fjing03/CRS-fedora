# tasks — `notif-ghost-retract-strip`

- [x] **T1 (the retract rides, ONE rider + the stale comment):**
  `resources/views/livewire/replacement-arrangement.blade.php`, the ghost
  law's retract block (`:4168-4188`): after
  `resolveBookingIntent(); evaluateBookingFit();` append `updateCounter();`
  (the chained strip/progress/stats trio at :1333-1335 — one call). The
  retract tail must read AFTER the re-arm (order per D1). The stale comment
  in `deselectBlock` (`:1489-1492`, "rendered by updateSelectionSummary, not
  updateCounter") rewords to state the :1334 chaining — text-only change;
  the :1495 duplicate call stays.
- [x] **T2 (the clause state-split):** `renderBookingIntent()`'s
  `intentNeedsManual` branch (`:2959-2987`): the
  `${fallbackAttribution ? … : ''}` clause becomes
  `intentReason === 'in-the-way' ? <FROZEN clause verbatim> : <NO-CLAIM
  clause>` — the frozen arm byte-identical (the token spans included); the
  no-claim arm: `<module> (<type>) — its sessions fit <venue>; pick another
  free slot for it.` (no "Pre-selected", no "the pre-selection stays"; the
  same `<em>` structure + the same token spans). NO other wording change —
  R3 stands (the `intentReason` strings are BYTE-IDENTICAL; fix the D0
  "fallbxAttribution" typo in design.md is a doc-only rider, not code).
- [x] **T3 (the spec-family rows flip + the Bug-2 gate):**
  `tests/venue-intent-modal.spec.ts`: the C2b row (`:118-143`) and the
  B002-ghost row (`:146-170`) flip their final banner assertions to the
  no-claim split (the row comments gain the state-split note — the frozen
  clause remains for `in-the-way` only). The strip gate: the
  `.sel-summary-card` count = 0 after settle — MANDATORY on the B002-ghost
  row (the direct Bug-2 gate); on the C2b row it is the T4-receipt-validated
  natural zero (the fallback short-circuit places nothing — the round-4
  🟡-1 fold: the "AD in BOTH rows" wording is scoped to be the validated
  natural zero, not a lane-specific retract gate).
- [x] **T4 (the gates):** pint (`composer run lint:check`) ✓;
  phpstan-1G 0; the full `php artisan test` green; the Playwright family =
  `tests/venue-intent-modal.spec.ts` on the seeded :8030 (`--grep-invert
  "TC70"` — the standing guard) — the 2 flipped rows + the rest green; the
  :8000 demo receipt (read-only, the 5425 repro URL): the held banner +
  the NO-CLAIM clause + **ZERO summary cards** + the pulse + the honest
  `proceed()` "No Selection" state — receipt-logged in review-log. NO push.
- [x] **T5 (the docs):** `page-changelogs/replacement-arrangement-changelog.md`
  — the dated entry: the user's bug report root (the ghost-retract law's
  missing strip rider + the mis-armed frozen clause), the R1/R2 changes,
  the wording-split law (the frozen clause lives for `in-the-way` only; the
  no-claim context line everywhere else), the in-the-way receipt
  data-block note (the compensating-gate argument), both receipt rows.
  SDD: this tasks list; the changelog's own cross-link.
