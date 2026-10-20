# Review log — last-visit-dd-memory

## proposal Round 1 — 2026-10-20
### 🔴 Fixed
 - 🔴1: the storage claim re-worded honestly (per-tab sessionStorage; the id-suffixed key = the isolation mechanism; the same-user-in-tab restore accepted) + the vacuous fresh-context test row replaced by the SAME-TAB LOGOUT→LOGIN-SWAP isolation row (asserts B's arrival defaults, never A's memory).
 - 🔴2: the venue page's identity leak closed — a real-identity seed line + the helper's explicit-id contract (never `MockData.currentUser` on real-DB pages; the mock `'5770'` leak named).
 - 🔴3: remember pinned to user-initiated picks ONLY (the inline onchange path); the arrival default, the faculty-switch auto-pick, and the restore path = non-remembering; the restore preselect direct-sets the selects (never routes through onCohortChange).
 - 🔴4: the restore gate = payload-validity (the non-taught but valid pair RESTORES — staff browse them; the dd lists them); "stale" = data-changed only; the fallback stays the 2026-10-13 rule.
### 🟡 Addressed
 - The week-selector divergence stated (localStorage cross-session vs the dd sessionStorage — the `weekNav-*` keys untouched); the venue remember guard scoped to the page's own onSelect closure (no shared-class edit); the concrete success criteria (the summary/one-line states).
### 💡 Adopted
 - `forget()` rides the helper; the VenueDropdown.select's ignore-unknown-behaviour cited as the restore's validity check.
### Verdict: proposal FROZEN (all four 🔴 landed).
## design Round 2 — 2026-10-20
### 🔴 Fixed
 - D4's dead `venueJsCodes` guard → the `servedVenues.some(...)` form hoisted beside the restore (one const, two consumers; the exact placement = inside the DOMContentLoaded handler, after :193's venueInfo, before :197's `new VenueDropdown(`; the constructor's opts line switches to `venues: servedVenues`); the direct identity read off the payload line (no venue-page MockData reads).
### 🟡 Addressed
 - 🟡1: D3's staff-arm restructured to the ReferenceError-safe guard chain (the default rule verbatim inside `else`); the `saved && !isStudentViewer` drop (staff already guarantees).
 - 🟡2: the servedVenues naming row (three distinct names — served / saved / known).
 - 🟡3: D5's S1 got the full assertion set (selects + weekSelect.disabled + the grid's presence).
 - D5 gains the S5 stub contract (the id-suffixed key + JSON DTO), the S2/S4's same-tab goto rule, and the NEW S7 no-remember negative gate (the arrival auto-pick writes NOTHING).
 - D6 names the changelogs concretely.
### 💡 Adopted
 - The remember bullet's explicit MockData.currentUser.staffId source; the TimelineWiringTest S17 note rode review round 1 of the AMENDMENT batch (this change's own specs).
### Verdict: design FROZEN (zero 🔴).
## tasks Round 1 — 2026-10-20
### 🔴 Fixed
 - T4's bare "S2, S3" rows contradicted the D4 remember gate (remember = REAL-pick onSelect only; a `?venue=` arrival never fires it) — S2 now pins a REAL pick of B010 before the same-tab no-param auto-land; S3 rides S2's memory (B002 param-only, no new memory written); the full D5 assert set spelled per row.
### 🟡 Addressed
 - T5's suite list named (the venue tetrads + the cohort page's suites) + the gates line (JS/Blade-only → no phpstan/phpunit rerun); T2's identity-source note (MockData.currentUser.staffId explicit).
### 💡 Adopted
 - S2's grid presence check beyond the dropdown value; the pointer-typo fix (D3 not D7); the S5 stub contract carried from D7.
### Verdict: tasks FROZEN (zero 🔴).
