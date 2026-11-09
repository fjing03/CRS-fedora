# Proposal — `notif-ghost-retract-strip` (the user's bug report: "Conflicted Booking Intent Incorrectly Displayed as Selected")

**The report (staff 5425, live-reproducible):** the venue-timetable arrange
handoff (`?venue=B002&date=06 Nov 2026&time=09:00&code=AMSE2003&session=67&week=14`)
lands on the arrangement with the conflict banner ("…no longer available on the
B002 grid — an existing class already occupies it") PLUS
"Pre-selected AMSE2003 (L) — its sessions fit B002; pick another free slot —
the pre-selection stays." AND a summary card showing the conflicted slot as a
selection, while the grid carries NO `event-block event-selection`.

## Why it happens (the live receipts — `/tmp/invBugBi*.cjs`, :8000, read-only)

1. The boot's pre-rebuild consume judges `canPlaceBlock` TRUE on the
   pre-rebuild grid → `selectBlock` places + saves `selectedSlotsByVenue[B002][6]`
   + the ghost toast (the SAME trap the `venue-intent-ghost-success-spot` lane
   receipted for 5770 — its law fires here).
2. The boot's real-grid paint despawns the block; `loadCurrentWeek()` refuses
   re-restore (Fri 10:00/10:30 = `cell-occupied` — the conflict detection is
   honest, partial occupancy).
3. The law's retract tail nulls the store entry, retracts the toast, re-arms
   the intent, and renders the held banner — **but never re-renders the
   selection strip** (`updateSelectionSummary`/`updateCounter` missing) →
   the ghost's summary card SURVIVES on screen (Bug 2). The store itself is
   clean (the `proceed()` modal says "No Selection").
4. The same tail arms `fallbackAttribution` reusing
   `venue-intent-banner-kind`'s frozen clause — worded for a SURVIVING
   pre-selection — in a state where nothing survives → "Pre-selected … the
   pre-selection stays." is a lie (Bug 1).

## The change (fix the state/law — NO CSS hiding)

- **R1 — the strip rider.** The law's retract tail gains ONE
  `updateCounter();` — it chains `updateSelectionSummary();
  updateSelectionProgress(); updateSummaryStats();` (:1334–1335), so the card
  dies with its store entry and the counter/buttons/strip all mirror. (Round-1
  🟡 fold: the original explicit pair was redundant — updateCounter already
  chains the strip.)
- **R2 — the clause state-split.** `renderBookingIntent()`'s manual branch
  renders the attribution clause CONDITIONALLY (live store-scan at render
  time — the honest-at-render shape, the round-1 🟡-2 fold): the frozen
  wording renders ONLY when a selection exists that MATCHES the attribution
  (the `in-the-way` arrival, or a surviving store entry whose block belongs
  to `fallbackAttribution`'s module); in EVERY other armed state the clause
  becomes the no-claim context line
  `<module> (<type>) — its sessions fit <venue>; pick another free slot for it.`
  — no "Pre-selected", no "the pre-selection stays" (the user's ruling:
  distinguish the context from an actual selection; keep the subject/session
  context + the manual-pick instruction). This closes the third-state variant
  (a selection survives but belongs to ANOTHER module — the frozen wording
  would lie there too).
- **R3 — the held wording untouched.** `evaluateBookingFit`/`intentReason`'s
  five branches and their strings stay byte-identical (the partial-occupancy
  wording stays accurate; the round-1 🟡-3 fold: one-liner so design/tasks
  don't touch the reason string).

## Out of scope

- The held-reason wording itself ("an existing class already occupies it") —
  accurate for the receipted partial occupancy; the user's own premise calls
  the conflict detection correct.
- The fallback-path success wording ("Pre-selected …" toast family) — the
  auto-pick DID place a real selection there; honest today.
- The pre-rebuild consume ordering (a decision-level reorder of the trusted
  ghost-lane design; the retract law works — the DISPLAY pair was the lie).
- Any DB write.

## Verify

- The exact repro URL: the honest end-state = the held banner + the
  no-claim clause + the pulse + **ZERO summary cards** (the strip's
  "0 of N"-family state mirrors) + zero `event-selection` cells.
- The `in-the-way` control keeps the frozen clause (no regression).
- The C2-family row (the B110/5770 trap — the no-claim path's cheapest
  regression detector, the round-1 🟡-1 fold): the manual banner renders the
  NO-CLAIM clause; previously it printed the lying frozen one.
- The honest-success control (a truly placeable booking) keeps its toast +
  landed block + card (no regression).
- Gates: the arrangement page's Playwright family green (TC70 never runs);
  pint + phpstan-1G 0; the full phpunit suite green; node --check on any JS
  file touched. NO push.
