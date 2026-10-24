# Proposal — `venue-r3-r4-networking-iot-doc` (DOC-ONLY, user decisions 2026-10-24)

**Two user decisions, recorded in `docs/venue-restrictions.md` only (no code,
no data, no counts change — the claimant list is doc-only; nothing in this
repo reads it):**

1. **R3 demotion:** all Networking/IoT subjects keep the L/T/P priority claim
   on B006 — EXCEPT `AMIT2034` (Fundamentals of Computer Networks). Per R3's
   own wording this is a priority DEMOTION only: AMIT2034 becomes an
   "everyone else" claimant (lower priority, NEVER banned). AMIT2034 itself
   stays in the dataset fully intact (sessions, CSV rows, B005 conflict).
   Cheap by construction — verified against the mock mirror: AMIT2034 has
   ZERO B006 rows (all 22 B006 rows belong to other modules); its L/T sit in
   B010, its P in B005 (per cohort DFT + DSF).
2. **R4 amendment (the additional user decision):** an IoT subject's `L/T`
   sessions may NOT use any lab — same as every other class (labs stay
   P-only per R1's 2026-10-19 law) — and therefore carry NO B006 priority
   claim: R3's planned B006 L/T widening (⚠️1) must NEVER include IoT L/T.
   The IoT `P` exclusivity stands unchanged: P → B006 ONLY.

**Scope:** `docs/venue-restrictions.md` — the R3 claimant block (AMIT2034
removed + demotion note) and the R4 block (the L/T lab bar + the
widening-exclusion). A one-line §4 note so the enforcement SDD inherits the
demoted/eemended state. NO other files.

---

## ROUND-2 AMENDMENT (2026-10-24, the user's three simplifications — SUPERSEDE round 1's wording)

1. **Skip the IoT concern entirely** — no IoT module will exist in the
   dataset; the recorded R4 directives are RETIRED (not scheduled for any
   enforcement). The IoT mention lives ONLY in the B006 venue description,
   **kept as is**: `Cisco Lab. Networking & IoT equipment room. For FOCS
   cohorts only.` (verified live in the mock mirror — no edit needed).
2. **No complex PRIORITY** — R3 collapses to plain ALLOW / DISALLOW:
   networking subjects = ALLOWED in B006 (no ranking/yield), admission =
   R6's FOCS-only gate; the 2026-10-24 "demotion" wording is superseded.
3. **AMIT2034's own law: its L/T/P MUST execute in a lab; it may use ANY
   lab** (B005, B009, B010, B011, B006 — `lab`/`cisco_lab`). NOT a B006
   claimant — any-lab access, module-scoped; its L/T-in-lab is an R1
   override FOR THIS MODULE ONLY (current rows already conform: L/T in
   B010, P in B005).

The doc edits (already applied): R3 rewritten (ALLOW/DISALLOW + AMIT2034's
lab law), R4 rewritten as DROPPED, R5/R6/§2/§4 stale references cleaned,
docs/README.md index line updated.
