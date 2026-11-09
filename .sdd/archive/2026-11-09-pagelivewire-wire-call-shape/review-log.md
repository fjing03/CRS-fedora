## proposal+design+tasks Round 1 — 2026-10-29 (combined small-bugfix batch)
### 🔴 Fixed
- (none — the reviewer found NO blocking issues; verdict: ready to proceed)
### 🟡 Addressed (folded before freeze)
- The proposal's StudlyCase premise corrected: the source walk (Finder
  kebab-cases) vs the live receipt ('NavNotifications') conflict is settled
  EMPIRICALLY in T2 — the :8000 result prints the ACTUAL excluded c.name;
  the normalization is spelling-agnostic either way.
- design D3 residue ("hmm", the dead :2188 anchor) removed; the definitive
  8-site audit list stands (the reviewer's full-repo grep agrees).
- The T2 receipt now also prints the excluded component name for the record.
- D1 anchor corrected to :1890–1899.
### 🔴 Outstanding
- (none)

## Applied receipt — 2026-10-29 (the user's fix-plan approval)
- T1: the resolver rewrite landed (ui-common.js — normalized spelling-agnostic
  island exclusion + returns comp.$wire; node --check OK).
- T2: :8000 — the resolver returns a .call-able object on every page; the
  fold-1 RECORD settled: the live first component name IS 'NavNotifications'
  (normalized 'navnotifications' → excluded); the venue read-only
  intentSessionOptions call through the REAL resolver → wire 200 with the
  real payload (modules/roomType/venue).
- :8030 UI chains with the REAL resolver: cancelRequest completed (cancelled
  0→1 + audit row); approve/reject confirm clicks each emitted wire 200 and
  returned the actions' OWN verdicts (the OCC conflict toast copy — the
  seeded fixtures collide with bookings, which is the action's designed
  behaviour; the earlier fixed-shim cancelClass receipt proved the +18
  notification rows / audit / exception fan-out machinery end-to-end).
- Islands: notifications-island.spec.ts 7/7 (:8000); suite 277/277.
- NO push; no demo-DB writes.
