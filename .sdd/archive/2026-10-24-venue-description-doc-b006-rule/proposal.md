# Proposal — `venue-description-doc-b006-rule` (the sv's directive, 2026-10-23)

**Two asks in one lane** (both small, both user-approved):

## A. The doc rules (record-only)
1. **B006 (cisco_lab) = FOCS-only gate** — ALLOW: DFT, DSF, RSD; DISALLOW:
   RAF, RBU (never). The sv's priority nuance = dropped by the user's word;
   this = a pure population gate. Status = DOC-ONLY (the same as R2/R3/R4 —
   enforcement = the planned "venue-restrictions enforcement" SDD; no code).
2. **B005 = "only degree students"** recorded positively — the SAME rule as
   the enforced R2 (no-Diploma), no behavior change.
3. The 2 live AMCS1013 DSF sessions in B006 = noted cross-ref — under the new
   gate they are future conflicts once enforcement lands; no write today.

## B. Every room gets a plain DESCRIPTION
- **Data:** `venues.description` (nullable text) via ONE reversible migration
  (up: add + fill the drafts; down: drop the column — the viva DB stays safe;
  a pre-migration snapshot at `backups/venues-description-baseline-2026-10-23.dump`).
  Applied to the demo by choice **A** (the plain `php artisan migrate` — only
  ADDs a column, never re-seeds).
- **Show on:** the venue page (a small line under the venue label) AND the
  replacement-arrangement page (a line under the venue dropdown trigger).
  Plain text, no priority wording anywhere.
- **The mirror rule:** `mock-data.js` venue entries gain the same
  `description` (documented mirror of the DB fill; the DB = canonical when
  a description differs).
- **Draft texts** seeded in the migration; the user corrects wording after.

Out of scope: any enforcement code, priority logic, admin editing UI.
