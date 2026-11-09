# tasks.md — `venue-c2-toast-noise` (the user's C: A now, B as its own later lane)

- [x] T1 (explore, `558b8f8`): the root decode with receipts — the C2 row's URL
  (W8-Tue 11:00 B110) sits inside the proposer's OWN teaching rows
  (`busyByWeek` [1,6,8] + [1,8,10], recurring every week); the room itself is
  free (two honest sources); `__rDebug` = the manual branch twice
  (`reason:held`), toast empty at every sample. The honest control slots also
  receipted: W8-Mon 15:00 = the Deepavali `cell-ph` row (unplaceable by
  design), W9-Mon 15:00 = `r:manual?false` + the attributed toast + 4 cells
  `cell-selected`. The row was never green from birth; the app = honest.
- [x] T2 (the fix, Option A — test-only): `tests/venue-intent-modal.spec.ts`
  C2 row retargeted to the proven legal slot (W9-Mon 15:00, week idx 8 / day
  idx 0 with the root-cause comment block); the vacuous final `hasAmber`
  count (no assert) replaced with the honest placement receipt (`expect(picked).toBe(4)`
  on `cell-selected` ×4); debug console.logs dropped. NO app file touched.
- [x] T3 (the receipt runs): the file **8/8** (26.7s — the always-red row = 2.5s);
  C2 ×3 solo = green every time (12.4s / 11.3s / 12.5s); the neighboring
  battery spot (venue-book + cross-week handoff + earliest-week) = **14/14**.
- [x] T4 (the gates): PHP untouched → no phpunit/phpstan delta; the guard
  `--grep-invert "TC70"` held on every run; no demo-DB writes (read-only tinker).
- [x] T5 (the quiver): "C2 = documented pre-existing noise" RETIRED from the
  known-failures ledger — the fresh ledger = the TC-2.x stale-key rows (design
  says leave) + TC34/35 baseline rows + the battery-load flake family (S1/S2/
  venue-book-T3 rotate red under load only, green ≈×3 solo).
  **USER PARK (2026-10-23): the flake probe is skipped 1–2 months** by the
  user's word — revisit later; the family = documented, never claimed green.

## Parked (the user's C = B is its own lane)

- **B — the app-honesty manual toast** (NOT in this lane): when a full-intent
  arrival's best-fit slot is unplaceable (own-busy / cell-ph), the manual
  branch stays silent today. Give it an attributed honest toast (e.g.
  "Auto-picked AMCS2093 — but Tue 11:00 sits inside your own class time; pick
  another slot."), a new test row, the UI changelog entries, full gates.
  Owner: a future SDD (proposal preset = this review-log's receipt chain).
