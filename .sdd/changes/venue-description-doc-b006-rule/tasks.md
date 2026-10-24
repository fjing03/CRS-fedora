# tasks — `venue-description-doc-b006-rule`

- [x] T1 (the snapshots + the migrate, choice A): the pre-migration snapshot
  `backups/venues-description-baseline-2026-10-23.dump` (pg_dump -Fc, 27
  tables — the viva DB protected); `2026_10_23_000001` migration ran clean
  (add + fill; DOWN = dropColumn); the tinker receipt confirms all drafts
  landed (B006/B005/B002 verified).
- [x] T2 (the payloads): `VenueTimetable` `$venuesJs[].description` + the
  viewed `$venue.description`; `ReplacementArrangement::venuesPayload`
  gains description (canonical from the DB).
- [x] T3 (the pages): the venue page `.venue-description` line under the
  dropdown; the arrangement page the same line under its trigger; the CSS
  = the existing tokens only.
- [x] T4 (the mock mirror): mock-data.js venues gain `description` (the
  documented drift guard: the DB wins).
- [x] T5 (the doc rules): `docs/venue-restrictions.md` R6 (B006 FOCS-only
  gate, DOC-ONLY) + R7 (descriptions enforced) + R2's positive restatement;
  the = the = AMCS1013 DSF cross-ref flagged for the enforcement SDD.
- [x] T6 (the gates): pint ✓; phpstan-1G **0**; phpunit **230/230**; the
  spot battery (venue-timetable + intent-modal + default-pin + pin-guard)
  **69 green** with only the documented TC34/35 baseline reds; live B006/B002
  receipts on both pages. No reseed; TC70 never ran.
- [x] T7 (the docs): both changelogs dated entries.
