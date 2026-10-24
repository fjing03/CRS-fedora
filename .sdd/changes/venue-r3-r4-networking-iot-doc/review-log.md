# review-log — venue-r3-r4-networking-iot-doc (DOC-ONLY, 2026-10-24)

## create Round 1 — 2026-10-24
### receipts (verified before writing)
- Mirror check: `public/js/mock-data.js` B006 rows = 22 — AMCS1013 ×2,
  AMIT2033 ×3, BMIS2003 ×2, BMIT2154 ×3, BMIT3084 ×6, BMIT3173 ×2,
  BMIT3273 ×4 → **zero AMIT2034 rows in B006** ✓; AMIT2034 = L/T in B010 +
  P in B005 (×2 per cohort, DFT + DSF) ✓ — the demotion changes no code,
  no counts, no data (the claimant list lives only in this doc; nothing in
  the repo reads it — grep receipts for `$networkingIoT`/`AMIT2034` outside
  the doc: none).
- The user's two decisions recorded verbatim: (1) the R3 demotion;
  (2) the R4 amendment (IoT L/T no lab + no B006 claim; IoT P → B006 ONLY).
### 🔴 Fixed
- (self-review) the first §4 edit dropped R3 ⚠️1 (the B006
  allowed_session_types widening) from the enforcement MUST-resolve list —
  restored with its new scope (networking claimants' L/T ONLY, never IoT).
### 🟡 Addressed
- R3's "Everybody else" line now explicitly includes AMIT2034 (since
  2026-10-24) so the demotion reads as a PRIORITY move, never a ban.
### 🔴 Outstanding
- none

## round 2 — 2026-10-24 (the user's three simplifications)
### receipts
- Live B006 description verified byte-identical to the user's "KEEP AS IS"
  string: "Cisco Lab. Networking & IoT equipment room. For FOCS cohorts
  only." (mock mirror; R7's DB-wins mirror untouched).
- The round-1 decision-1/2 wording (claimant demotion, priority) is
  SUPERSEDED — the doc now carries round-2 only; round-1 text remains as
  history in this lane, not in the doc.
- Stale-reference sweep: R5's "priority rule", R6's "R3 = ordering",
  §2 room_type table (R4 ref), §4 sentence, §4 tail (R2/R3/R4), docs/README
  index — all updated; grep shows no priority-model remnants outside the
  "Priority is retired" statements themselves.
### 🔴 Outstanding
- none
