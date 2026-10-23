# Review log — session-arrival-pin-guard

## Batch 1 (proposal + design + tasks, condensed single round) — Round 1 — 2026-10-20

Reviewer: pi subprocess (`pi --model commandcode/z-ai/glm-5.3-flash
--tools read,grep,find,ls`; full output in session; verdict "Not ready
for /sdd-apply yet — two blockers").

### 🔴 Fixed
- **"Mutate the local `urlParams`" in the gate block was a behaviour
  change, not a shadow removal** (round-1 draft D1/D2): the shipped
  block writes NOTHING into the local; rerouting the day/start/venue
  writes to the local would hijack the POST-BOOT path
  (`anchorSlotFromPinned` would build a foreign-module anchor on the
  stale arrival week; the submit `orig` fallback would file a wrong
  original). Fix = **pure deletion**: the gate conjunct added AND the
  entire :2967–2973 shadow block deleted with NO reroute — post-boot
  bit-identical by construction; the arrival fixed purely by the
  else-ride. Proposal "In scope"/NOT-in-scope + design D1/D2 + tasks T1
  rewritten accordingly.
- **The cross-change independence claim was false** ("nothing
  regresses / apply order irrelevant"): landed alone-first, the gate
  removes the T18 clobber that today coincidentally rescues the venue
  repro's submit (`{97,7} → {97,8}`), leaving the phantom `{97,7}` —
  a live regression window. Fix = an explicit APPLY SEQUENCING section
  (proposal + design D4 + tasks T7): land together with or after
  `venue-cross-week-handoff`; the A6/C2 coupling honesty named in D4/D5.

### 🟡 Addressed
- "6/6 default-pin rows" corrected to **3/3 rows (T18-S1/S2/S3)** — the
  real file has 3 tests (proposal success criteria + tasks T4 + design
  D5).
- **The auto-nav residual (:3019–3027) documented** as pre-existing and
  user-visible (the arrival grid navigates to the earliest conflict's
  week and persists it) — design D3 carries options (a)/(b); drawn as an
  OPTIONAL task (T6) pending the user's decision, not a silent rider.
- **The D6 click-through locator made house-shaped**: scoped to
  `tr[data-day-index="4"]` + the hour-6 cell (bare `td[data-day="4"]`
  matches a day-column), the `#btnReplaceNow` visible pre-assert, the
  S5a/S7 pattern; the D6 vehicle list aligned with tasks T4 (no
  contradiction between the two).
- **The :2966/:3215 citation drift fixed**: the T18 write being edited
  cites :2961–2975 (gate :2965); the replacement-home producers cite
  the real sites (replacement-home.blade.php:533 / arrangement ~:3215).
### 🔴 Outstanding
- None beyond the OPEN USER DECISION on T6 (the auto-nav residual —
  documented as designed; not a blocker to the strip/pin fix).

## Confirmation round — 2026-10-20 (same batch, post-fix)

Reviewer confirmed every named fix against the live blade (the :2965 gate,
the :2967–2973 shadow block, the sole-repo window.urlParams write, the
else-ride, the 3-row default-pin file, the auto-nav residual wording, the
house-shaped locators): ✅ CONFIRMED.

### 🔴 Fixed
- (none needed)
### 🟡 Addressed
- D1's slipped non-English word cleaned ("the shipped post-boot path
  ... unchanged BY CONSTRUCTION").
### 🔴 Outstanding
- None — **PASS** (the T6 auto-nav gate stays unapplied pending the
  user's explicit decision, as documented).

## T6 decision record — 2026-10-20 (post-apply, user-decided)

The user answered the parked explore-brief question (the auto-nav
residual, design D3 options a/b) with an explicit **B** — gate the T18
auto-nav with the same `__bootDone` conjunct. Execution receipts (this
round = the user's decision, not a new review ask):

- The auto-nav block edited in the same file (+ the design D3 wording
  already covered both options — no unfreeze needed: task T6 WAS the
  drawn option (b)).
- New asserts in S1: `#weekSelector` = '13' + `localStorage['arrangementWeek']`
  = '13' on the 49 arrival (the old residual persisted '7').
- Runs: session-arrival-pin-guard 3/3 · default-pin 3/3 (post-boot
  auto-NAV byte-identical) · venue-cross-week-handoff 2/2 ·
  venue-intent-modal 7/8 (only the stash-proven C2 toast-poll noise
  fails, same signature).
