# Spec — records-intact-gate

Capability: row-count snapshot tooling (`crs:db-row-counts`).
Baselines: frozen proposal.md §2 item 3 / design.md §7.

## R1 Enumeration

- R1.1 Enumerate ALL tables in the `public` schema from `information_schema.tables` — no hardcoded table list (currently 26; self-proving on schema drift).
- R1.2 Output TSV: `table<TAB>rows`, **alphabetical by table name** (stable across runs).

## R2 Behavior

- R2.1 Read-only; works on any database the .env points at.
- R2.2 Non-zero exit + clear error on connection or schema failure.
- R2.3 No side effects, no caching.

## R3 Usage contract

- R3.1 The importer's final step invokes the same output (one implementation, no duplicated SQL).
- R3.2 The before/after pair from execution is recorded in this change's execution notes and cited in `page-changelogs/backend-automated-by-ai.md` as the standing records-intact gate for future merges.
