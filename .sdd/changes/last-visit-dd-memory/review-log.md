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
