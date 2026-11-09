# Design — `notif-ghost-retract-strip`

**Frozen baseline:** proposal.md (round-2 READY, 2026-10-30). One blade file,
one spec file, one page changelog. No CSS, no DB, no PHP logic.

## D0 — the code-path equivalence that makes D2 cheap (the round's keystone)

The reviewer asked for "a selection exists **matching the attribution**" as
the keep-frozen condition. The matching check is UNREACHABLE-UNNECESSARY by a
code-path argument (`applySubjectChange`/`consumeBookingIntent` reads):

1. `fallbackAttribution` arms ONLY at two sites: the ghost law's retract arm
   (`:4176`, the session path — its retract guarantees the block is GONE) and
   `armIntentFallbackPrefill` 2d (`:2858`, the no-session path — the fallback
   then binds the subject and re-consumes).
2. In BOTH flows, the ONLY way a real selection coexists with a rendered
   manual banner is `consumeBookingIntent`'s `selectedBlock` branch
   (`:3218-3227`), which sets `intentReason = 'in-the-way'` BEFORE rendering —
   any placement (`selectBlock`) CONSUMES `pendingBookingIntent` and drops the
   banner (`:1617-1619`), so a later manual-branch render with a live block
   cannot occur.
3. THEREFORE at `renderBookingIntent()`'s manual branch, for CLAUSE-CARRYING
   renders (fallbackAttribution armed): `intentReason === 'in-the-way'` ⇔ a live
   selection XOR corrupts. The held-with-a-memory-restored-block pre-consume
   render (:3947 evaluateBookingFit) can read clause-less (no attribution)
   — outside the claim. The in-the-way branch's own precondition: the
   consume's `selectedBlock` guard (:3218-3227) sets `intentReason =
   'in-the-way'` with the block LIVE — the clause's module context there is
   the intent's OWN module, exactly what the user approved in
   `venue-intent-banner-kind` (the C2 receipt). Everywhere ELSE the manual
   branch renders (with the attribution armed), NOTHING was selected — the
   frozen clause has NEVER been honest in those states.

   ⇒ **The keep-frozen condition is `intentReason === 'in-the-way'`.** No
   store scan, no flag, stale-proof at render. The reviewer's third-state
   variant (a foreign-module selection during a held arrival) is impossible
   by argument 2 (the banner dies on any placement).

## D1 — R1: the strip rider in the retract tail

Anchor: the ghost law's retract block (`:4168-4188`, inside
`if (!selectedBlock) { … if (ghost) { … } }`):
after `resolveBookingIntent(); evaluateBookingFit();` append
`updateCounter();`
- `updateCounter()` chains `updateSelectionSummary(); updateSelectionProgress();
  updateSummaryStats();` (`:1334-1336`) — the strip, the "N of M" `infoTotal`,
  the duration strip, the submit/clear button states and tooltips all mirror
  the retracted store in one call.
- ORDER: the rider runs AFTER `resolveBookingIntent()` (which may call
  `selectWeek` → buildTimetable) so the strip reads the settled state.
- The stale-comment cleanup (round-1 💡): `deselectBlock`'s rider comment
  (`:1491-1494`, "rendered by updateSelectionSummary, not updateCounter")
  is FALSE since :1334 chained it — reword to state the chaining (the
  explicit `updateSelectionSummary()` at `:1495` stays; harmless duplicate,
  out of scope to remove).

## D2 — R2: the manual-branch clause state-split

In `renderBookingIntent()`'s `intentNeedsManual` branch (`:2959-2987`), the
`${fallbackAttribution ? … : ''}` clause becomes a ternary on
`intentReason === 'in-the-way'`:

- **`in-the-way`** → the frozen `venue-intent-banner-kind` wording
  BYTE-IDENTICAL (its color-token spans included):
  `Pre-selected <module> (<type>) — its sessions fit <venue>; pick another free slot — the pre-selection stays.`
- **every other reason** (`held` / `too-soon` / `span` / `type`, i.e. every
  state where the intent slot is the problem and nothing is selected) → the
  NO-CLAIM context line, same `<em>` structure, same token spans (the
  success-container/primary-container colors keep their meanings — no new
  colors):
  `<module> (<type>) — its sessions fit <venue>; pick another free slot for it.`
  (no "Pre-selected", no "the pre-selection stays" — the subject/session
  context preserved, the manual-pick instruction preserved — the user's
  ruling verbatim shapes.)

R3 stands: the five `intentReason` strings in `evaluateBookingFit`/
`consumeBookingIntent` are BYTE-IDENTICAL — design/tasks must not touch
`intentReason = 'held'` etc. (the type-cause branch `:2976-2977` names the
module from `currentCourse` — untouched).

## D3 — test updates (`tests/venue-intent-modal.spec.ts`)

TWO rows flip their final banner assertions to the no-claim split (they are
exactly the no-claim states):

1. **C2b** (`:119-143`, the B110/W8-Tue 11:00 trap): `Pre-selected AMCS2093`
   → `AMCS2093 (L) — its sessions fit B110`; `the pre-selection stays` →
   `pick another free slot for it`. The row's own "one voice" comment gains
   the state-split note (venue-intent-banner-kind's scope note: the frozen
   clause remains for `in-the-way` only).
2. **B002 ghost** (`:145-170`): the final assertions flip the same way; ADD
   the Bug-2 gate this lane exists for:
   `sel-summary-card` count **= 0** after settle (the strip mirrors the
   retract) — currently UNASSERTED, which is why the ghost card survived two
   receipted lanes.

No new test file; the two rows widen. The `in-the-way` keep-frozen side has
no existing row asserting the frozen clause — it was never receipted — and a
receipt is DATA-BLOCKED (probed live): the only organic `in-the-way` reach
needs the legacy auto-pick to place the subject's own slot on the ARRANGEMENT
grid, but EVERY subject slot's first half-hour collides with an existing
B-Block class on the demo (`psql` receipt: the lateral NOT-EXISTS scan
returned ZERO rows; B002 saturated wall-to-wall), and the :8030 fixtures
seed from the same import (shared shaper → the same collision) so a)
fixture row construction would mean a seeder change (rejected: it would
re-shape every family row). The KEEP-FROZEN arm is instead gated by
(i) the D0 equivalence argument (the frozen clause renders IFF the
in-the-way intentReason), (ii) the two no-claim rows catching the DANGEROUS
inversion (frozen rendering where nothing is selected = the reported lie —
C2b + B002-ghost now FAIL if the ternary collapses to the frozen arm), and
(iii) the changelog naming the protected branch. The LOWER-RISK inversion
(the frozen arm never rendering — an over-correction) stays uncovered by
automation; accepted honestly, logged in the design + the changelog.

## D4 — the changelog

`page-changelogs/replacement-arrangement-changelog.md` (dated entry): the
user's bug report root + the R1/R2 fixes + the wording-split law (the frozen
clause lives for `in-the-way` only; the no-claim context line everywhere
else) + both receipt rows.

## D5 — gates

pint + phpstan-1G (the blade compiles; the inline JS is syntax-covered by
the Playwright family run — round-2 🟡-2: the explicit statement, there is
no separate node --check surface for inline blade JS); the full phpunit
suite; the Playwright family = `tests/venue-intent-modal.spec.ts`
(grep-invert "TC70") on the testing DB (:8030) — expect the 2 rows green
after the flip + the rest family (the venue-intent-modal spec ran 10/10 in
the ghost lane; the current suite size governs); the :8000 demo receipt
(read-only, the 5425 repro URL) re-run: banner no-claim + ZERO cards + the
pulse + the honest-zeros `proceed()` "No Selection" receipt (the round-4
🟡-2 fold: reconciled with the T4 gate list) — receipt-logged. NO push; no
DB writes; the demo DB untouched.
