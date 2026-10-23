# Proposal — session-arrival-pin-guard

## Why (with the live repro)

Every `?session=` handoff (my-timetable Replace Now, replacement-home
Arrange Replacement, the venue intent handoff) arrives with an honest
server pin — and the boot then HIJACKS it. Verified live (staff 5770,
2026-10-20):

1. Arrive `?session=49&week=14&from=my-timetable`: the boot hydrates the
   honest pin `{49, 14}` (session 49 = AMCS2093 **P**, Fri 25 Dec
   11:00–12:00 @ B011, whose W14-Fri occurrence = the Christmas Day fact).
2. The boot's own subject programming (`:3408–3413`, pre-`__bootDone` set
   at :3527) fires `onSubjectChange()` → T18's auto-pin (`:2961–2975`,
   the `if (firstConflictRow)` at **:2965**, introduced by this
   workstream's T18 rider `29d566a`) treats it as a user switch and
   re-pins the module's EARLIEST conflict: **`pinnedOccurrence` =
   `{97, 8}`** (the joint (L) lecture's Deepavali Monday).
3. The strip's Subject line renders `courseCtx()` — now 97's entry — so
   the user's two sightings show **`(L)` + `DFT2 (S1) G1 + DSF2 (S1) G1`
   + `18 + 15 = 33`** over what they actually clicked (`(P)` /
   `DFT2 (S1) G1` / `18` for 49; `(P)` / `DSF2 (S1) G1` / `15` for 39).
4. Deadliest half: `submitBlocks` (gate :1987) sends
   `window.pinnedOccurrence` (:1994) — the request would file against
   session 97's W8 Deepavali Monday while the user believes they are
   replacing the practical's Christmas session (and `(97,8)` is a real
   conflict pair, so the engine's gate passes silently).

Root sentence: T18's earliest-conflict auto-pin (this workstream's own
rider `29d566a`) cannot distinguish "the page switched the subject for
me while boarding" from "I switched the subject" — and it fires on the
boot moment, replacing the user's arrival pin. A second latent defect
(:2968's `window.urlParams` fork — a true repo-wide zero-reader dead
shadow, verified) is deleted in the same touch WITH correct semantics
(design D2).

## In scope

- The gate conjunct: T18's auto-pin block runs only post-boot
  (`window.__bootDone` — the file's established convention).
- **The arrival falls to the else-ride**: `currentSessionId` = the
  arrival pin's own session (the branch exists at :2977–2979) — the
  strip context then resolves the pinned session's own
  type/cohorts/count via `courseCtx()`.
- **The :2967–2973 shadow block DELETED ENTIRELY — no local reroute**
  (reviewer round 1 🔴1): today the auto-pin block writes nothing into
  the local `urlParams`; re-routing those writes to the local would
  recreate the mixed-anchor/hijack on the POST-BOOT path (the exact bug
  class this change kills). Deleting the lines restores the shipped
  post-boot behavior bit-for-bit and removes the dead window shadow.
- Playwright rows locking the honest arrival (the strip's `(P)`/own
  cohort/count + `pinnedOccurrence` = the arrival pair) + the dated
  changelog entry.

## Explicitly NOT in scope

- The auto-nav residual (the T18 grid-rebuild block `:3019–3027`):
  today it also navigates the ARRIVAL's grid to the earliest conflict's
  week and persists it (`weekNav.selectWeek` + `save()`) while
  strip/pin say the arrival week — a REAL, user-visible residual this
  change's gate+deletion leaves untouched. Documented as a known
  residual (design D3); its possible one-token fix is a USER DECISION
  (open question — explore-brief), not a silent rider.
- The venue cross-week double-duty fix (`venue-cross-week-handoff`).
  **Sequencing (reviewer round 1 🔴2): this change must land together
  with — or no earlier than — `venue-cross-week-handoff`**: landed
  alone, the gate removes the clobber that today coincidentally rescues
  the venue repro's submit (`{97,7} → {97,8}`), leaving `{97,7}` (a
  nonexistent pair) — a live regression window otherwise.
- `pinnedSessionPayload`/server PHP (zero PHP edits).

## Impact scope

| File | Change |
|---|---|
| `resources/views/livewire/replacement-arrangement.blade.php` | 2 edits: the :2965 gate conjunct (+ the else-ride comment); the :2967–2973 shadow block DELETED (no local reroute) |
| `tests/session-arrival-pin-guard.spec.ts` (NEW) | the honest-arrival rows (49 and 39 + a click-through control) |
| `page-changelogs/replacement-arrangement-changelog.md` | dated fix entry |

## Success criteria

- Both user sightings, live: the strip reads
  `AMCS2093 — Operating Systems (P)` + the session's OWN cohort line
  (`DFT2 (S1) G1` for 49 / `DSF2 (S1) G1` for 39) + the own count (18/15);
  `window.pinnedOccurrence` = `{49,14}` / `{39,14}` (the clicked pair).
- Submit honours the arrival pair; the `orig` fallback (:1992–1993)
  keeps reading the honest local facts.
- `tests/default-pin.spec.ts` — **3/3 rows** (T18-S1/S2/S3) unchanged
  green (T18's accepted post-boot semantics intact); the venue-intent
  A6/C2 rows unchanged green (their green A week rides the co-change's
  correct threading — the coupling is named in design D4).

## Apply sequencing (reviewer round 1 🔴2)

`venue-cross-week-handoff` first (or in the same apply run) — never this
change alone before the venue threading.
