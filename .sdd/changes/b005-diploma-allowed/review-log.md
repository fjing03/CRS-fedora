# review-log — b005-diploma-allowed

## round 1 (glm-5.3-flash, 2026-10-27) — READY after fixes
- 🔴1 VenueTimetable:191 severity arm (the third derive-branch caller
  the proposal missed) → folded into design D4 item 3 + task T2.
- 🔴2 AGENTS.md:13's standing rule (auto-loaded) → folded into design
  D7 + task T5.
- 🟡 the 5-file Playwright set named precisely (venue-db:161/179,
  class-modal-affordances:99-102, venue-timetable:318,
  pages-parity:74, venue-restrictions-enforcement) → folded into D6.
- 🟡 D6's test re-cut precision (the named rows +
  the CONDITIONAL fixture re-point — never manufacture) → folded.
- 🟡 the venues.description truthfulness (the "DEGREE students only."
  claim becomes false) → DECIDED: AMEND (the demo DB paste + the
  seeder + the mock mirror), design D7 + task T5.
- ✅ the fail-closed reasoning (D5) + the anti-bird receipt (D6) kept.


## apply-round fold (2026-10-27): the debug-mode 500 blocker (D8/T6)
During the apply T4's Playwright receipts the 5 replacement-family pages
threw a debug-mode 500: Livewire's root-element detector's lazy script
regex overruns pcre.backtrack_limit on a ~1MB single PAGE_DATA script
(the detector is DEBUG-only — the debug-off serve rendered; the test
suite skipped the path). The pre-existing bug only appeared as an error
receipt in this lane; the shard partial fixed it (the consumer
contract unchanged) — design D8, task T6, the shared file + the 5
blades. The failure's root receipt: the provider's temporary mount
diagnostics logged the 1MB forward (the detector's loadHTML saw the
SAME 1MB — the preg_replace null → the error text distortion) and the
temp hook was removed after the diagnosis.
