# design — `venue-description-doc-b006-rule`

## D1 — the rule texts (verbatim, recorded 2026-10-23)
- sv (via the user): "Cisco Lab normally is only for IT (DFT, RSD, non-DSF)
  students, especially degree IT (RSD)."
- The user's decode: scope = B006 only (the sole cisco_lab); RAF/RBU NEVER;
  DSF **ALLOW** ("as long as it is FOCS"); priority = dropped for the binary
  gate; descriptions = plain, no priority wording.
- B005 (the user's extra): "B005 is ONLY allowed degree students" = R2's
  no-Diploma restated positively — no change.

## D2 — the seed texts (first drafts, the user corrects post-apply)
| Room | draft description |
|---|---|
| B002/B014–B018/B100–B109 | Tutorial Room. Seats 35. For lectures and tutorials. |
| B110/B111 | Lecture Hall. Large multi-cohort assemblies; lectures only. |
| B005 | Hardware Lab. For practicals. Degree students only. |
| B009–B011 | Computer Lab. For practicals. |
| B006 | Cisco Lab. Networking equipment room. FOCS only: DFT, DSF, RSD permitted; RAF, RBU not permitted. |

## D3 — the data paths
- `ReplacementArrangement::venuesPayload` (+ `$v->description`) — the
  arrangement page.
- `VenueTimetable`'s `$venuesJs` (+ description) — the venue page.
- `mock-data.js` venue entries gain `description:` — the client dropdowns
  (a documented mirror; the DB = canonical on any difference).
- Drift guard: a HOUSE note in mock-data.js ("mirror of venues.description —
  update both or the DB wins").

## D4 — safety (the viva DB)
- Snapshot BEFORE anything: `backups/venues-description-baseline-2026-10-23.dump` (pg_dump -Fc, 27 tables).
- The migration = reversible (DOWN = dropColumn). The restore recipe =
  `pg_restore --clean --if-exists -d class_replacement_fresh <dump>` still
  available; the plain `php artisan migrate` never re-seeds.
