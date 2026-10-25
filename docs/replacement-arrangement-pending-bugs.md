# Pending bugs — `/replacement-arrangement` (round: 2026-10-24)

Read-only exploratory Playwright session (36 probe scripts under `/tmp/eb-*.cjs`,
staff-5425 context, repo at `d38fdc3`, zero console errors during testing).
**Status: findings only — NO fixes applied yet.** The user verifies and
green-lights per bug before any SDD lane is opened.

**Closed by user decision:** BUG-04 (no notification bell on this page)
= **accepted as-is, do not fix**. Do not reopen it.

---

## Pending list (fix candidates, in recommended order)

| ID | Severity | Title | Status |
|----|----------|-------|--------|
| BUG-01 | Medium | Stale slot-trigger label after a week over-return | Pending user verify |
| BUG-02 | Medium | Back button bypasses browser history (URL replacement) | Pending user verify |
| BUG-03 | Medium | Blank-subject strip mixes identities (stale pinned anchor) | Pending user verify (possibly by-design) |
| BUG-05 | Low | Re-clicking a selected cell = "Clear current selection" modal, no toggle-off | Pending user verify |
| BUG-06 | Low | Counter stays "N of N slots" after over-return while grid shows no selection | Pending user verify (same family as BUG-01) |
| BUG-07 | Low | URL `?week=` never updates during week navigation | Pending user verify |

---

## BUG-01 · Medium — Stale slot-trigger label after a week over-return

- **Feature:** week navigation ↔ conflicted-slot (cross-family)
- **Confirmed / reproducible:** yes (probes `/tmp/eb-subject.cjs`, `/tmp/eb-ghost4.cjs`, `/tmp/eb-debug2.cjs`)
- **Preconditions:** plain landing `/replacement-arrangement` (memoryless).
- **Steps:**
  1. Open `/replacement-arrangement` (no URL params).
  2. Pick subject **AMSE1003** → auto-pins the W8 occurrence; trigger reads `W8 · Nov 09 · 9:00 AM–11:00 AM`; grid lands W8.
  3. Click **Next week** (W9 — the lead-locked week; no picks allowed).
  4. Click **Previous week** ×2 (one legit return + one **over-return** → ends on W7; no selection exists there).
- **Expected:** with nothing selected and the pinned occurrence facts unchanged, the trigger should keep its auto-pin label — OR, if the over-return invalidates the view-anchor, re-sync to the pinned pair. Either way: the week on screen, the strip, and the trigger must agree.
- **Actual:** after the over-return the trigger **still reads "W8 · Nov 09 · 9:00 AM–11:00 AM"** while the grid = W7 and the strip's data is consistent — the same-cell receipts say the pick delta stayed 4 slots both ways (no state corruption), so this is the label surviving a view state it should no longer headline.
- **Probe receipt:** `CELLS: 76 → 72 delta 4; RETURN-1-DELTA: 4; total: 4 of 4 slots` (block itself works; the WRITTEN trigger does not refresh).
- **Likely root cause:** `commitSlotSelection` (`resources/views/livewire/replacement-arrangement.blade.php` ~:3373–3457) writes `slotTriggerLabel(slot)` at ~:3449; the week-rerun path (`applySubjectChange` auto-pin + `renderSlotPicker`) does not rewrite the trigger on a **no-pick week change** — the label survives the over-return. Same family as the SDD lane `replacement-strip-stale-anchor` round-2.
- **Impact:** user reads a trigger naming a week/slot combo the visible grid no longer represents.
- **Proposed fix direction:** re-write the trigger from the SAME source the strip uses on every week re-render (or stop the over-return from clearing the pick-set contrast), so the trigger + strip + grid always read from one anchor.

## BUG-02 · Medium — Back button bypasses browser history

- **Feature:** Go Back
- **Confirmed:** yes (probes `/tmp/eb-go-back.cjs`, `/tmp/eb-go-back2.cjs`, `/tmp/eb-realflow2.cjs`)
- **Preconditions:** any arrangement session, with or without `?from=`.
- **Steps:**
  1. Real flow: `/replacement-home-ui` → click an "Arrange Replacement" CTA → lands on the arrangement page (`?session=99&week=8&from=replacement-home`).
  2. Click the page's **Back** button (`.back-btn` → `goBack()`).
- **Expected:** returns to the previous page (browser-back semantics).
- **Actual:** `BackNavigator.navigate()` (`public/js/ui-common.js` :3015–3045) does `window.location.href = getBackUrl()` — a URL-based jump to the `from=` route (or the default `/replacement-home-ui`), NOT `history.back()`. Consequences measured:
  - The 4-route `from=` matrix works ✓ (`venue-timetable`, `my-timetable`, `my-request-history`, default).
  - After leaving, the **browser's back arrow reopens the arrangement page** (the fresh URL was pushed by the navigation).
  - At the history edge the back key goes to `about:blank` (measured once in a headless probe; a loop-free but degenerate stop).
- **Likely root cause:** the navigator was designed for the `from=` route matrix (a feature), but never uses `history.back()` when a real history entry exists.
- **Impact:** users who arrive organically (not via a `from` link, e.g. the home CTA) get a re-navigation instead of a back; browser-back then "resurrects" the page they just left.
- **Proposed fix direction:** if `history.length > 1` and the referrer is the same origin, `goBack()` → `history.back()`; else keep the current default-route jump. Needs an SDD lane (shared `ui-common.js` class).

## BUG-03 · Medium (suspected by-design) — Blank subject mixes identities on the conflict strip

- **Feature:** subject selection
- **Confirmed / reproducible:** yes (probe `/tmp/eb-subject-blank.cjs`)
- **Steps:**
  1. Open `/replacement-arrangement`, pick subject AMSE1003.
  2. Switch the subject dropdown to **"" (Select a subject)**.
  3. Watch the conflict strip.
- **Expected:** strip = fully "Not selected" state.
- **Actual:** line 1 = `Not selected — Select a Subject first` BUT line 2 still shows the **boot-pinned occurrence's slot facts** (from `anchorSlotFromPinned()`).
- **Likely root cause:** `renderTitleSummary` (`.../replacement-arrangement.blade.php` ~:2438–2452) — `anchorSlot` falls back to `anchorSlotFromPinned()` even when `currentCourse = null`.
- **Note:** the slot-picker/trigger DOES reset correctly (`renderSlotPicker([])` → `Select a slot to replace`); only the strip mixes. The trigger also survived on one probe (B4 receipt showed the old `W8 · Nov 09` label) — same family as BUG-01.
- **Impact:** low-stage confusion only (no data corruption; re-picking a subject re-syncs everything).
- **Proposed fix direction:** gate the `anchorSlot` fallback behind `hasSubject` in the strip render — OR the user confirms this is the intended boot-anchor presentation and it becomes documented-by-law.

## BUG-05 · Low — Re-click a selected cell pops "Clear current selection" instead of toggling off

- **Feature:** conflicted-slot selection / grid merge UX
- **Confirmed:** yes (probe `/tmp/eb-modal.cjs`)
- **Steps:**
  1. Open the page, pick subject AMSE1003, land on W8.
  2. Click a free cell (e.g. `td[data-day="1"][data-hour="0"]`) → the 4-slot block merges (budget reads `4 of 4 slots`).
  3. Click **the same cell again**.
- **Expected:** (user-preference ask) an in-cell toggle-off, or the standard Undo-bar.
- **Actual:** alert modal: `Clear current selection × You already have a selected block. Clear it first before selecting a new one. Close Clear Current Selection`.
- **Likely root cause:** `selectBlock` (~:1566–74) has no same-cell branch — every re-click hits the alert-modal path.
- **Impact:** minor annoyance; the safe path exists (the modal works).
- **Proposed fix direction:** same-cell re-click → toggle-off OR the keyword-voice toast + Clear (follow the confirm-critical-action law).

## BUG-06 · Low — Over-return counter disagreement (same family as BUG-01)

- **Steps:** same as BUG-01; after the over-return the fee counter keeps `4 of 4 slots` while the grid renders **0** selected blocks (`event-block.event-selection` = false).
- **Interpretation:** the block state itself persists via `selectedSlotsByVenue` (per-venue/week map; the restore guard revalidates correctly on the re-landing week); the DISAGREEING part = the counter/label written by the pick (BUG-01's writer) not being reset on the over-return route.
- **Fix:** folds into BUG-01 (one probe battery covers both).

## BUG-07 · Low — URL `?week=` never updates while navigating

- **Confirmed:** yes (probe `/tmp/eb-range-slim.cjs`, `/tmp/eb-arrivals.cjs`)
- **Steps:**
  1. Open `?session=68&week=14&from=replacement-home`.
  2. Arrow/dropdown-navigate W14→W6→W13 etc.
- **Expected:** (idea) the URL reflects the viewed week on copy/reload.
- **Actual:** the URL keeps `week=14` the whole session; ONLY the slot pin's `history.replaceState` (`commitSlotSelection` ~:3407) rewrites URL params, and weekNav moves write nothing.
- **Impact:** a copied URL mid-session lands on the pinned pair's week, not the one the copier saw; a reload mid-nav re-creates the pinned arrival (earliest-bookable landing) — consistent with the ARRIVAL-GRID-NO-PIN law, but not transparent.
- **Proposed fix direction:** page-supplied `weekNav` onSelect hook doing `history.replaceState` with the current pair (a small SDD lane; optional — confirm the user actually wants the URL to move).

---

## Recommended fix order (implementation pending approval)

1. **BUG-01 (+06)** — one writer family, the same route as the strip-stale-anchor round-2.
2. **BUG-02** — `BackNavigator` browser-back parity (shared class; SDD lane).
3. **BUG-04 — CLOSED (user decision, no fix).**
4. **BUG-03** — the strip's gated anchor (or document by law).
5. **BUG-05** — same-cell toggle-off / toast+clear.
6. **BUG-07** — the URL week-sync (optional lane).

## Probe index (all in `/tmp/`, 2026-10-24)

`eb-recce.cjs` · `eb-shell.cjs` · `eb-range.cjs` / `eb-range-slim.cjs` · `eb-arrivals.cjs` · `eb-subject.cjs` · `eb-subject-blank.cjs` · `eb-go-back.cjs` / `eb-go-back2.cjs` · `eb-realflow.cjs` / `eb-realflow2.cjs` · `eb-slot.cjs` · `eb-cross.cjs` · `eb-ghost.cjs` / `eb-ghost2/3/4.cjs` · `eb-toast-ghost.cjs` · `eb-cell-reject.cjs` · `eb-cap.cjs` · `eb-toast-assert.cjs` · `eb-debug.cjs` / `eb-debug2.cjs` · `eb-modal.cjs` · `eb-combi.cjs` · `eb-multiwk.cjs` / `eb-multiwk-add.cjs` · `eb-bell*.cjs` · `eb-mail*.cjs` · `eb-logout.cjs` · `eb-homecta*.cjs` · `eb-dommytt.cjs`


## BUG-08 · Medium — the clearing-change confirms don't fire cross-week (FIXED 2026-10-25)

- **Feature:** week navigation × venue/subject/slot confirms
- **Reported:** the user's live workflow (W6 pick → W8 hop → all three
  changes silent); confirmed by Playwright + code (the three guards keyed
  on the current-week `selectedBlock` pointer only).
- **Fixed by:** `arrangement-clear-guards-cross-week` — the guards widened
  to `selectedBlock || getGlobalTotal() > 0`; the proceed semantics = a
  full-store discard + the booking-intent spent-scan; the early-out of
  `confirmChangeWithSelection` widened too. BUG-06's counter-vs-grid
  disagreement (its root family) is resolved BY the proceed's whole-store
  wipe; its remaining receipts re-verify under this lane's spec.
- **Status:** FIXED pending user verify (spec `tests/clear-guards-cross-
  week.spec.ts` 8/8 ×2).
