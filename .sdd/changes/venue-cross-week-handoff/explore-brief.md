# Explore brief — venue-cross-week-handoff

Source: user prompt `prompts/pi-prompt-venue-cross-week-handoff.md` (2026-10-20) +
PI verification round + reviewer round 1 (2026-10-20, condensed; the 🔴/🟡
corrections folded in below — see review-log.md).

## Symptom (real data, staff 5770)

Venue page `?venue=B111`, Week 7 view, click the free Tue 03 Nov 13:00 cell →
"Arrange a replacement in B111" modal → pick the session-dd row
`(L) W8, Mon, Nov 9 . 11:00 ~ 13:00` (AMCS2093) → [Arrange] → URL carries
**`&week=7`** (the VIEWED slot week), not 8 (the PICKED occurrence week).
`pinnedSessionPayload` (server) then pins the **pair `<97, 7>` that does not
exist** (97's only fact = the W8 Deepavali Monday; tinker `conflictedFor(97)`,
verified 2026-10-20) — the URL, the server payload and the strip anchor all
store/display a fact that is not real:
`Week 7 · Mon, Nov 02 2026 · 11:00 AM–1:00 PM @ B110`.

### The honest threat model (reviewer-corrected, round 1)

Today — BEFORE this change — a submission on the repro happens to file the
TRUE pair `{97, 8}`, because the boot's T18 auto-pin (the clobber, :2966)
coincidentally rewrites the pin from `{97, 7}` to `{97, 8}` (97 IS the
module's earliest conflicted occurrence). The rescue is an accident owned by
another change (`session-arrival-pin-guard`). The real harms today:

1. **The strip lies**: the phantom `Week 7 · Nov 02` line under the honest
   day/time-of-day facts.
2. **The URL + the server payload store a nonexistent pair** (`{97, 7}`).
3. **The rescue does not generalise**: pick a cross-week occurrence that is
   NOT the module's earliest conflict (e.g. an AMCS2093 `(P) W14` row from
   that same W7 slot) → URL `&week=7` pins `{49, 7}` phantom → the T18
   clobber re-pins `{97, 8}` → **the submit files the lecture's Deepavali
   Monday while the user picked the practical's Christmas Friday** — and
   the strip's slot line would honestly read W14 while the pin files W8.
   Sandwiched between the threading fix and the arrival gate, the honest
   end-to-end truth for non-earliest picks requires BOTH changes; this
   change alone removes the phantom base pair and makes the threadable pin
   exist; the gate stops the clobber.

## The code chain (line receipts, verified + reviewer round 1)

- `venue-timetable.blade.php:484` — picker rows DO carry the occurrence
  week: `item.dataset.week = String(occ.week || '')` → `'8'`.
- `:552–562` `confirmIntentSession` — the picked row's sid + cohort only
  (the occurrence week dropped); `:565/:580` `bookVenueWithSession` —
  hardcodes `'&week=' + encodeURIComponent(currentWeek + 1)`.
- Server `ReplacementArrangement.php:181–204` `pinnedSessionPayload()`
  reads `(session, week)` from the URL verbatim → pins the pair; the boot
  at `:3445–3448` hydrates `window.pinnedOccurrence` from it.
- `:2372–2397` `anchorSlotFromPinned()` composes the strip anchor from the
  LOCAL urlParams (the pinned session's own day/start/venue) +
  `PAGE_DATA.pinnedSession.week` — the pinned session's true day/time rides
  the WRONG week number (the phantom line).
- **NOT on this path** (reviewer round 1 🔴): the `:2590` "2c pin bridge"
  re-pin sits behind `armIntentFallbackPrefill`'s short-circuits
  (`:2539–2542`), including `if (urlParams.session) return;` at `:2541` —
  mutually exclusive with a hydrated pin — so no venue `?session=` flow can
  reach it. The venue path's only re-pin risk is the **T18 clobber at
  `:2966`** (boot `:3523` `applyUrlParams` → `:3409–3413` sel.value +
  `onSubjectChange()` → `applySubjectChange`) — owned by the separate
  change `session-arrival-pin-guard`.

## Product semantics (user ruling, 2026-10-16)

A makeup/booking slot in an EARLIER week than the occurrence it replaces
(slot W7 replacing a W8 class) is VALID — "a class can be arranged at ANY
week past the lead time." So no producer-side validation; the fix is
truth-keeping only.

## Rejected approaches

1. **Producer-side validation** (never offer cross-week pairings) —
   rejects flows the ruling declares valid. REJECTED.
2. **A separate `&occWeek=` param** — doubles the param set while the
   server's `pinnedSessionPayload` keeps reading `week` (the pair would
   stay phantom server-side). REJECTED.
3. **sessionStorage handoff** — the URL is this repo's SSOT for the
   handoff (A6's 8-param assert family); a second truth source violates
   it. REJECTED.
4. **Consumer-only re-derivation** (rebuild the occurrence week from the
   picker rows on arrival) — an inference where a fact exists. REJECTED.
5. **The `:2590` same-session pin-keep guard** (the original step 3) —
   reviewer round 1 🔴: the site is unreachable for hydrated-pin flows
   (`:2541` short-circuit); the drafted comment described an event that
   cannot occur. DELETED from scope (task removal; see review-log).

## Final solution

Producer-only: thread the picked occurrence week through the EXISTING
`&week=` param (meaning: the pinned OCCURRENCE week); `&date`/`&time` keep
the slot's own landing facts. Zero consumer/server edits — the boot
hydration + (after the parallel change lands) the arrival gate make the pin
the user's pick honestly instead of by coincidence.

## Key cross-module data flow after

venue picker row (`dataset.week='8'`) → `confirmIntentSession` →
`bookVenueWithSession(…, occurrenceWeek='8')` → URL `&week=8` →
`pinnedSessionPayload` (unchanged reads) → boot pin `{97, 8}` → the strip's
Week 8 · Mon, Nov 9 line + submit `{97, 8}` without relying on the T18
coincidence. No server edit — the SAME param feeds the pin server-side.

## Known open questions

None. Byte-parity contract (same-week handoffs byte-identical) verified
against A6/C2 shapes this session + reviewer round 1.
