# Explore brief — `notif-ghost-retract-strip` (the user's bug: "Conflicted Booking Intent Incorrectly Displayed as Selected")

## The report (staff 5425, /venue-timetable-ui → AMSE2003 (L) W14 Thu 24 Dec → B002 Fri 06 Nov 09:00)
Bug 1 — the conflict banner prints "Pre-selected AMSE2003 (L) … the pre-selection stays"
after correctly reporting the slot unavailable. Bug 2 — a summary card shows the
conflicted slot as a selection although no `event-block event-selection` exists.

## Live receipts (:8000, read-only probes /tmp/invBugBi*.cjs)
- The exact URL reproduces: banner (manual branch) + the clause + ONE summary card
  "B002 · Week 7 · Fri · 9:00–11:00 · 4 slots", `event-selection` blocks = 0,
  selected tds = 0.
- Grid columns are Mon=0..Sat=5, Sun=6 (`data-day 4` = Friday). Friday 09:00–11:00
  = hour2/hour3 `cell-available`, **hour4/hour5 `cell-occupied`** → the held
  detection is CORRECT (partial occupancy; the words stay accurate).
- `window.__rDebug`: one consume — `r:identical?null ng:true sb:-` then
  `r:manual?false flags:false` — **the boot's pre-rebuild consume judged
  canPlaceBlock TRUE** (the pre-rebuild grid, the same trap the ghost lane
  receipted for 5770) → `selectBlock` placed + saved
  `selectedSlotsByVenue[B002][6]` + the ghost toast.
- The boot's own `buildTimetable()` painted the REAL grid; `loadCurrentWeek()`
  re-validation refuses (hour4/5 busy) → `selectedBlock = null` (no event block).
- The ghost law (`venue-intent-ghost-success-spot`, boot tail): detected the
  ghost, **retracted the STORE entry** (`selectedSlotsByVenue[B002][6] = null`),
  `toast.dismiss()`, armed `fallbackAttribution` (session path, :4176),
  `resolveBookingIntent()` re-armed, `evaluateBookingFit()` → the held banner
  + the pulse. THE STORE IS CLEAN ("No Selection" modal receipt via proceed()).
- **The card is a STALE STRIP**: the ghost consume's `updateSelectionSummary`
  painted the card; the law's retract tail calls only resolve/evaluate/render —
  NO `updateSelectionSummary()`/`updateCounter()` — so the strip keeps showing
  the retracted ghost until an unrelated re-render. (Subject-clear does NOT
  re-render the strip — `applySubjectChange('')` early-returns before it.)

## Root (ONE cause, two symptoms)
The ghost-retract law retracts the store but never re-renders the selection
strip (Bug 2), and its session-path attribution arm reuses
`venue-intent-banner-kind`'s frozen clause "Pre-selected X (Y) — its sessions
fit <venue>; pick another free slot — the pre-selection stays." (Bug 1) —
worded for a SURVIVING pre-selection, printed in a state where NOTHING was
selected (the retract just removed it).

## Wording law conflict (scope boundary)
The frozen clause (user-approved in `venue-intent-banner-kind`) is HONEST only
for the `in-the-way` arrival (a real different selection exists). Every OTHER
manual-branch render with the clause armed = the same lie family (C2 trap
B110/5770 included). The user's new ruling overrides: "Do not describe the
conflicted slot as pre-selected … preserve the subject/session context … but
distinguish it from an actual selected replacement slot."

## Rejected approaches
- CSS-hiding the card/clause — the user explicitly forbids it.
- Preventing the ghost at the pre-rebuild consume (move the consume to
  post-rebuild) — a decision-level reorder of the trusted ghost-lane design;
  overlkill: the retract law works, the DISPLAY pair is the lie.
- Re-wording the frozen `in-the-way`/fallback clause globally — the fallback
  path (no-session, a REAL auto-pick placed) is honest today ("stays" true).

## Fix shape (per the user's expectations)
R1 — the law's retract tail gains the strip rider pair:
`updateSelectionSummary(); updateCounter();` (mirrors `removeSavedBlock`'s
null-write pair :3176-3182 and the :2236 staleness law). The card dies with
its store entry.
R2 — the manual banner splits the clause by state: when NO selection exists
(store-scanned), render the no-claim context clause
`<module> (<type>) — its sessions fit <venue>; pick another free slot for it.`
(no "Pre-selected", no "stays"); `in-the-way` keeps the frozen wording.
R3 — the pulse + held banner wording unchanged (partials-accurate).
Gates + receipts: the exact repro URL → the card gone + the no-claim clause +
the honest pulse; the honest-success control + the C2 family row stay green;
`in-the-way` control keeps the frozen clause; no DB writes.

## Open questions
- Whether R2's split is rendered from a live store scan at renderBookingIntent
  time (honest-at-render) or a flag set at the arm sites (simpler).
  Design decides; no user ask open.
