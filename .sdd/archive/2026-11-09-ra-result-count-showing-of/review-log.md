## Batch 1 (proposal.md) Round 1 — 2026-10-30
### 🔴 Fixed
- (none required)
### 🟡 Addressed
- Problem-section quote softened at freeze: the span's static markup is
  empty; "0 results" is the runtime output of the local JS (now stated
  as "renders a bare runtime count").
- All anchors fact-verified by the reviewer against live code (def :804,
  single call :846, shared helper ui-common.js:1608 string match, MRH
  :495 / replacement-home :497 consumers, PAGE_DATA bridge :1257 runs
  before first renderTable :1270, refresh at :1164) — Y stays honest
  after approve/reject.
- Name-collision checked and cleared: ui-common.js loads in <head>
  (:50 of ui-template), so the global resolves cleanly once the local
  shadow is deleted; no other caller, no visibility hazard.
### 🔴 Outstanding
- (none) — verdict: proposal FROZEN.

## Batch 2 (design.md) Round 1 — 2026-10-30
### 🔴 Fixed (before freeze)
- D5 REWRITTEN to the structural receipt (the Playwright part SKIPPED per
  the user's explicit instruction — recorded as the USER WAIVER below):
  (1) the local function gone + exactly ONE updateResultCount({ call with
  the frozen cfg; (2) ui-common.js:1608 untouched; (3) gates = lint:check +
  phpstan 1G + the stale-serve ritual; the D4 node --check gate dropped
  (no .js file touched).
- D2 gains the declarative Y-clause: Y = the pending + decided rows as
  emitted by the span-aware payload; Cancelled/Completed filter selections
  yield an honest 'Showing 0 of Y results'.
- D1 gains the grep-anchor note (the :804/:846 anchors may drift after the
  in-flight status-tooltips-filter-chips landing).
- The :1160 "Success rows leave the list" comment's inaccuracy cleared in
  D2 (approved/rejected ROWS FLIP STATUS into the decided bucket — Y stays
  constant).
### 🟡 Addressed / Carried
- The static #resultCount span occurs at :274 (not :275) — verified; the
  stays-empty decision stands (server-driven data; no baked default).
- The rowsPayload rows are NOT ownership-scoped (frozen span-aware
  behavior) — recorded; no action this batch.
### 🧾 THE USER WAIVER (recorded per the unfreeze rules)
- The FROZEN proposal's Success criterion "Playwright receipt asserting the
  format on both pages" is WAIVED by the user's instruction this session
  ("u may SKIP it's playwright test") — the structural receipt + the gates
  replace it. No proposal unfreeze needed; the waiver is recorded here and
  in tasks.md.
### 🔴 Outstanding
- (none — ready to freeze; proceed to tasks.md → apply)

## Applied receipt — 2026-10-30 (the user's "proceed with sdd ra-result-count-showing-of")
- T1: the page-local shadow DELETED (found via the banner comment); the
  single call site rewritten to the shared helper with the frozen cfg.
- T2 stub: the structural receipt — local defs 0, shared calls 1, the frozen
  cfg string present, ui-common.js:1608 untouched (byte-verified).
- T3 gates: lint:check PASSED; phpstan 0 (1G); the full suite 277/277; the
  :8000 island spec 7/7 (the ritual run — the blade change is covered); the
  Playwright receipt skipped per the USER WAIVER recorded above.
- The changelog + ONE ui: commit; NO push.
