# design — `arrangement-clear-guards-cross-week`

Reads against the frozen proposal. All anchors verified 2026-10-25
(pre-apply, tree at `5bd5e5d`); the implementer re-greps at apply.

## D1 — the shared proceed helper (NEW function, one place)

Insert beside the existing discard family (after `discardSelection`
~:2862–2866):

```js
/* SDD arrangement-clear-guards-cross-week (BUG-08, review R1 folded):
   the clearing-change confirms fire on the CROSS-WEEK store
   (selectedBlock || getGlobalTotal() > 0) — so their "Yes, Change"
   proceed must discard the WHOLE store, not just the current-week
   pointer (deselectBlock nulls selectedBlock AND writes the CURRENT
   [venue:week] entry only; a foreign week's block survives = the counter
   lies "4 of 4" over an empty grid, the BUG-06 disagreement class).
   The clearAll() register (:2080) minus its confirm shell + toast/undo
   — the confirm IS the explicit discard (the 2026-10-09 user decision
   contract the subject/slot proceeds already carry). */
function discardAllSelections() {
    /* review R1 blocker 3 — the booking-intent SPENT-contract across the
       store map: the pointer-only capture misses the cross-week case and
       the pre-fill would RESURRECT on the next reload against the user's
       explicit discard. Scan BEFORE the wipe with the SAME fields
       intentMatchesBlock compares (:2870: i.venue === venueKey &&
       i.weekIndex === weekKey && i.dayIndex === block.day && i.hourIndex
       === block.startHour); on ANY hit run the :2854 spent pair. */
    if (bookingIntentMemory) {
        const i = pendingBookingIntent || bookingIntentMemory;
        for (const venueKey of Object.keys(selectedSlotsByVenue)) {
            const weekMap = selectedSlotsByVenue[venueKey] || {};
            for (const weekKey of Object.keys(weekMap)) {
                const blk = weekMap[weekKey];
                if (blk && i.venue === venueKey && i.weekIndex === parseInt(weekKey, 10)
                    && i.dayIndex === blk.day && i.hourIndex === blk.startHour) {
                    markBookingCancelled();   /* :2854 — bookingIntentMemory = null + the sessionStorage stamp */
                    pendingBookingIntent = null;
                    renderBookingIntent();
                    break;   /* one hit is decisive — the intent names ONE block */
                }
            }
            if (!bookingIntentMemory) break;
        }
    }
    Object.keys(selectedSlotsByVenue).forEach(k => { selectedSlotsByVenue[k] = {}; });
    deselectBlock();
    selectionHistory.length = 0;   /* review R1 advisory 4 — the explicit-discard undo law (proceed() :2051 + pageshow :2182: "stale Ctrl+Z can't resurrect"); without it the same-week deselect HISTORY would resurrect one block into a cleaned store while the cross-week payloads have no record */
    updateCounter();               /* review R1 blocker 2 — deselectBlock()'s rider is DEAD when !selectedBlock (its early-return :1441); the helper pays its OWN tail, the exact clearAll() law ("covers the no-block case" :2084) */
    updateSelectionSummary();      /* the :1349 strip mirrors the counter drop */
}
```

## D2 — the three guard widenings (ONE formula)

## D2 header — REVIEW R1 blocker 1 (folded): the early-out widens too

`confirmChangeWithSelection` opens with `if (!selectedBlock) { proceedFn();
return; }` (:1912–1914) — after the hop `selectedBlock` IS null → the
widened subject/slot guards would call the helper which then REELS THROUGH
silently. The early-out widens with the same formula:

```js
if (!selectedBlock && getGlobalTotal() === 0) { proceedFn(); return; }
```

1. **`onVenueChange` (~:1783)** — the condition becomes
   `if ((selectedBlock || getGlobalTotal() > 0) && newVenue !== currentVenue)`
   — everything inside the modal block stays byte-identical (the cancel
   onclick pair) EXCEPT the proceed body: the old pointer-only intent
   capture lines come OUT (the helper's store-wide spent-scan in D1
   supersedes them — the intent spend WHOLE-STORE); `deselectBlock();`
   → `discardAllSelections();`.
2. **`onSubjectChange` (~:2541)** —
   `if ((selectedBlock || getGlobalTotal() > 0) && code !== lastSubject)`.
   Its proceed goes through `confirmChangeWithSelection` (:1912), whose
   body currently ends with a single `discardSelection();` → that swap
   (D3) covers the subject + slot proceeds WITHOUT touching their
   callbacks.
3. **`selectSlot` (~:3287)** —
   `if ((selectedBlock || getGlobalTotal() > 0) && index !== lastSlotIndex)`.
   **review R1 advisory 6 (pinned):** the AUTO default picks ride
   `{auto:true}` (:3576/:3585/:3597 via applyDefaultSlotSelection) — they
   run at panel build only (boot / subject change — a fresh or a JUST-
   WIPED store, getGlobalTotal() === 0) so the widened guard CANNOT nag
   for an automatic action today; the design pins this DELIBERATELY (no
   opts.auto bypass added; the lane that touches auto-picks re-checks
   this receipt).

## D3 — `confirmChangeWithSelection`'s proceed swap (~:1935)

`discardSelection();` → `discardAllSelections();`
- covers BOTH its callers (subject :2543, slot :3288) in one edit;
- the preceding intent block `if (bookingIntentMemory &&
  intentMatchesBlock(currentVenue.code || currentVenue,
  weekNav.currentWeek, selectedBlock)) { markBookingCancelled();
  pendingBookingIntent = null; renderBookingIntent(); }` is REMOVED —
  the helper's store-wide spent-scan (D1) supersedes it (review R1
  blocker 3: the pointer-only test returns false after the hop and the
  pre-fill would resurrect on the next reload against the user's
  explicit discard); the same-week case is byte-covered by the helper's
  match law (the SAME fields intentMatchesBlock compares);
  a foreign-venue/week intent block dies silently with the wipe — no
  re-arming (the same spent-contract).
- NOTE (review-verified live): `discardSelection()`'s OTHER callers —
  `clearSelection()` :1756 wrapper, `toggleCell` in-block :1777,
  `userDeselectSelectedBlock()` :2883, `removeSavedBlock()` :2880–2900 —
  NONE route through `confirmChangeWithSelection`; they keep the
  pointer-only multi-week law (the × clears the VISIBLE block).

## D4 — tests (NEW `tests/clear-guards-cross-week.spec.ts`)

Regime: fresh login staff-5425 (the StorageState bypassed — the class-
modal-affordances loginStaff pattern), fixtures verified live (the
bug08 probes): session 67's (L) pick at W6 = `td[data-day="2"][data-
hour="16"]` (4-contig, counter "4 of 4"); week values 0-based (W6='5',
W8='7').

Rows:
1. **venue guard after a hop** — pick W6 → `weekSelector` '7' → walk the
   venue widget (trigger → Lecture Hall → Block B → floors → a room ≠
   B100): `#confirmModal` visible with title `Change Venue?`;
2. **venue cancel preserves everything** — the cancel outline closes the
   modal, the venue dropdown reverts to the pre-click code, the counter
   STAYS `4 of 4 slots` (the store receipt — resolve via jumping BACK to
   W6 and asserting the block re-renders: `event-selection` visible — the
   BUG-06 companion receipt);
3. **venue proceed clears honestly** — re-walk + "Yes, Change" confirms →
   the venue applied AND `#infoTotal` drops to `0 of 4 slots` (the honest
   counter — no residue);
4. **subject guard after the hop** — fresh page, pick W6, hop W8, subject
   change → `Clear Current Selection?` appears; cancel restores the OLD
   subject + counter unchanged;
5. **subject proceed** — re-change + Yes → the subject applied + the
   counter `0 of 4 slots`;
6. **slot guard after the hop** — fresh page, pick W6, hop W8, open
   `#slotTrigger`, pick another row → the confirm modal appears; cancel
   keeps the trigger on the OLD row; Yes applies + the counter drops;
7. **the no-hop baseline tripwire** — pick at W6, venue change directly →
   the modal appears (the confirm-guards legacy receipt, one cheap re-assert);
8. **the hop itself stays silent** (review R1 advisory 5; the multi-week
   persistence law) — pick at W6, hop W6→W8 → `#confirmModal` STAYS hidden
   through the whole navigation AND the counter receipts `4 of 4 slots`
   (the honest store) for the window.

Row receipts strengthened (review R1 advisory 5): proceed rows ALSO
assert `#selCount` text + the footer button states (Submit/Clear All
flip to disabled at 0) beside `#infoTotal` — blocker 2 fails loudly;
the cancel rows keep the store-alive receipt (the counter unchanged).
Honest skips: if a fixture cell/row is unreachable (data drift), the row
skips with an explicit fixture-gap throw (the class-modal-affordances
regime). Fixture note: the hop targets pass the page's weekFilter
(verified live — values '5'→'7' land W8 exact in the probes).

## D5 — gates + bookkeeping

- the new spec ×2 runs; `tests/confirm-guards.spec.ts` ×1 tripwire; pint;
  `vendor/bin/phpstan analyse --memory-limit=1G`; phpunit (baseline 258);
  `page-changelogs/replacement-arrangement-changelog.md` dated entry;
  `docs/replacement-arrangement-pending-bugs.md` — BUG-08 row appended
  (status → FIXED pending user verify; BUG-06's fix-order line notes the
  shared-family resolution); ONE conventional commit with explicit paths;
  NEVER push.
