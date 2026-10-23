# Explore Brief — `venue-c2-toast-noise` (the C2 toast-poll "noise" decode)

/ sdd-explore · 2026-10-23 · READ-ONLY (no app/test files touched; the lane dir only).
The C2 row = `tests/venue-intent-modal.spec.ts:89–112` ("fallback prefill for modal-bypass
full-intent URLs") — the ALWAYS-red row documented as noise since 2026-10-08, never claimed green.

## The receipt chain (fresh, 2026-10-23, clean tree `b347c55`)

1. **Fresh C2 probe (the test's exact URL — W8 Tuesday 11:00 @ B110):** `__rDebug`
   = `r:manual?true flags:false … || r:manual?true flags:true … reason:held` ×2
   (the 2d pre-rebuild pass + the 2e re-consume BOTH take the manual branch),
   `selected:false`, toast = `""` at every sample (0s → 6s+). The toast NEVER fires —
   not a timing race, not a Playwright artifact.
2. **Why manual (the busy decode):** the arrangement's `busyByWeek` for 5770 shows
   `[1,6,8]` (Tue 11:00–12:00) + `[1,8,10]` (Tue 12:00–13:00) — **the proposer
   HIMSELF teaches Tue 11:00–13:00** (his own recurring rows, constant across all
   14 weeks). The DOM confirms: `d1h12..h15 = cell-occupied`. `canPlaceBlock` reads
   the DOM (`isCellAvailable` at :1536 → `cell-available` required) → false →
   `selectBlock`'s manual branch → pulse + notice, NO attributed toast.
3. **Why not the room:** the venue page's own `weekData` shows B110 Mon AND Tue 11:00
   **FREE every week** — room-free ≠ proposer-free. The room data (the c2-occ probe)
   vs the arrangement's own-busy = two honest sources that disagree per intent.
4. **Control experiment (my first retarget guess — W8 Monday 15:00):** STILL manual.
   DOM: `d0h12..h17 = cell-ph` — **W8-Mon = the Deepavali holiday row** → placeholder
   cells = unplaceable by design. ALSO honest.
5. **The honest URL (W9-Monday 15:00):** `r:manual?false`, `selected:true`, the four
   cells = `cell-selected`, and the toast text EXACTLY:
   `Pre-selected AMCS2093 (L) — auto-picked because its sessions fit B110; change if needed.`
   → **the whole fallback family WORKS end-to-end.**

## Conclusion

The C2 row was **never green from birth** — its URL points at a slot the app's contract
can NEVER auto-place (own-busy), so the asserted success-toast cannot fire. The app is
honest; the test's URL + assertion are the dishonest parts. The "noise" label was wrong.

## The fix shape (to grill)

- **A (test-only):** retarget the C2 URL to a legal slot (W9-Mon 15:00, live-proven)
  and keep the honest assert family (subject bind + the attributed toast poll +
  the block-selected receipt). The always-red row dies; the fallback stays tested.
- **B (app honesty, bigger):** ALSO give the manual branch an attributed honest toast
  (e.g. "Auto-picked AMCS2093 — but Tue 11:00 sits inside your own teaching time;
  pick another slot.") so REAL users get feedback instead of silence on a
  room-free-but-own-busy arrival. Touches the consume branch + a new test row +
  changelog (an UI-behavior change → the full SDD + gates).
- **C:** A now, B as its own later lane (recommended — A needs no UI change).

## Standing guards

- TC70 never runs (`--grep-invert`). The demo DB untouched. The server ritual =
  two commands before any live receipt. Push = user's word only.
