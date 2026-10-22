# Explore brief — session-arrival-pin-guard

Source: the user's bug report (2026-10-20, two sightings pasted verbatim) +
PI verification round + reviewer round 1 (2026-10-20, condensed; corrections
folded in — see review-log.md).

## Symptom (real data, staff 5770)

Both `?session=` handoffs land the arrangement page with the WRONG
subject context and — deadlier — a HIJACKED submit pin:

- My-timetable Replace Now (Class Ref#49 = AMCS2093 **P**, Fri 25 Dec
  11:00–12:00 @ B011) → `?session=49&week=14&from=my-timetable` → the
  strip reads **"AMCS2093 — Operating Systems **(L)** | Week 14 · Fri,
  Dec 25 2026 · 11:00 AM–12:00 PM @ B011 | **DFT2 (S1) G1 + DSF2 (S1)
  G1** | **18 + 15 = 33**" — 3 of its lines belong to session **97**
  (the joint (L) lecture), not the clicked practical.
- Replacement-home "Replacement Details" (AMCS2093 **Practical**, Thu 24
  Dec 13:30–14:30 @ B010) → `?session=39&week=14&from=replacement-home`
  → the same `(L)` + joint-cohort substitution on the Thu P occurrence.

## The data truth (tinker, `class_replacement_fresh`, 2026-10-20)

| Session | Type | Own facts | Pivot cohorts | Its real conflict |
|---|---|---|---|---|
| 39 | P @ B010, Thu 13:30–14:30 | — | **DSF2 (S1) G1 only** | W14-Thu = Christmas Eve |
| 49 | P @ B011, Fri 11:00–12:00 | — | **DFT2 (S1) G1 only** | W14-Fri = Christmas Day |
| 97 | L @ B110, Mon 11:00–13:00 | 33 students | DFT2 + DSF2 (joint) | W8-Mon Deepavali (the module's EARLIEST conflict) |

Both conflict stamps are REAL (holiday-blocked); the strips' day/time/
venue/week lines are honest; `(L)`, the joint cohorts, `18 + 15 = 33`
are the wrong session's substitutions.

## The Frankenstein state (live probes, fresh auth, 2026-10-20)

```
?session=49&week=14&from=my-timetable:
pinnedOccurrence: { sessionId: 97, week: 8 }        ← clobbered (49,14)!
window.urlParams: { day:0, start:6, originalVenue:"B110" }   ← 97's facts
strip slot line: "Week 14 · Fri, Dec 25 … @ B011"    ← 49's facts (local anchor)
?session=39&week=14&from=replacement-home:
pinnedOccurrence: { sessionId: 97, week: 8 };  strip: "(L) | W14 · Thu, Dec 24 · 1:30–2:30 @ B010 | DFT2+DSF2 | 18+15=33"
```

## The mechanism (line-anchored, verified + reviewer round 1)

1. Boot `:3436+` hydrates the server pin honestly:
   `pinnedOccurrence={49,14}`, local `urlParams` = 49's day/start/venue.
2. `:3523` `applyUrlParams()` runs (still pre-`__bootDone`, set at
   **:3527**) and at **:3408–3413** programmatically sets the subject
   selector then calls `onSubjectChange()`.
3. `applySubjectChange('AMCS2093')` hits T18's auto-pin block
   (`:2961–2975`, gate at **:2965**): `firstConflictRow` = the module's
   **earliest** conflicted occurrence over `MockData.cohortTimetable`
   (= `{ events: PAGE_DATA.conflictEvents }`, blade :1178; 3 AMCS2093
   rows sorted by week → 97-W8) → **`window.pinnedOccurrence = {97, 8}`**
   — the arrival pin is clobbered; `currentSessionId = 97` →
   `courseCtx()` (:2356) merges 97's session entry → the strip's type/
   cohorts/counts = the (L) joint facts; `window.urlParams = Object.assign(…)`
   (**:2968**) forks a SEPARATE window shadow (a true repo-wide zero-reader).
4. The local `urlParams` (read by `anchorSlotFromPinned` :2374 and the
   submit `orig` fallback :1992–1993) keeps 49's facts → the strip's
   honest-looking slot line rides the WRONG pin underneath.
5. `submitBlocks` sends `window.pinnedOccurrence` — `(97, 8)`, a REAL
   conflict pair, so the server gate PASSES and the request files
   against the lecture's Deepavali Monday.
6. The same boot runs for EVERY `?session=` handoff (venue /
   replacement-home / my-timetable), so the guard benefits all producers.
7. **Additional pre-gate residual (reviewer round 1)**: the T18 auto-NAV
   block (:3019–3027) navigates the ARRIVAL grid to the earliest
   conflict's week and PERSISTS it (`weekNav.selectWeek` + `save()`) —
   pre-existing, independent of the pin fix; documented in design D3 as
   the know-residual with the optional same-conjunct gate DRAWN (a user
   decision).

## Rejected approaches

1. **URL-sniff gate** (suppress the auto-pin when `urlParams.session`
   exists) — reimplements "boot-time" with a string check; the codebase's
   own convention already encodes this moment (`window.__bootDone`,
   read at :3008/:3240 before assignment as falsy-undefined). REJECTED.
2. **Merge-by-sessionId equality** (auto-pin keeps the arrival pin only
   when the earliest row's session matches) — silently re-pins a
   DIFFERENT session when the arrival session has no conflict rows.
   REJECTED (sandbag; healed by the existing else-ride :2977–2979).
3. **Strip-context-only fix** (render `courseCtx` from the PAGE pin) —
   heals the display; leaves the SUBMIT pin hijacked. REJECTED (cosmetic
   fix over a deadly half).
4. **Reorder the boot** (move the subject programming after
   `__bootDone = true`) — large blast radius across the fallback/intent
   flows to accomplish what ONE conjunct covers. REJECTED.
5. **Delete T18 entirely** — its post-boot semantics (the user's own
   accepted T18 ruling) are wanted; only its ARRIVAL firing is the
   breach. REJECTED.
6. **"Delete the shadow block AND reroute its writes into the local
   `urlParams`"** (the round-1 draft's D1 shape, reviewer 🔴1) — the
   shipped block never wrote the local; rerouting would let a
   post-boot subject switch put a DIFFERENT module's slot facts into
   the local anchor source while `PAGE_DATA.pinnedSession` still names
   the arrival session (a virtual anchor of the new module's time/venue
   on the stale arrival week + a wrong `orig` fallback value) —
   recreating the hijack on the OTHER path. REJECTED in favour of
   PURE DELETION (design D1/D2).

## Final solution

Gate T18's auto-pin on `window.__bootDone` (post-boot, genuine user
switches only) + DELETE the :2967–2973 shadow block WITHOUT rerouting
(no local writes) — the arrival falls to the existing else-ride
(`currentSessionId` = the arrival session). Zero server edits;
`pinnedOccurrence` = the pair the user actually clicked, everywhere.

**Sequencing**: land together with — or after — `venue-cross-week-handoff`
(landed alone-first, the gate removes the clobber that coincidentally
rescues the venue repro's submit today).

## Known open questions

1. **The arrival GRID week (the auto-nav residual)** — document-only (a)
   vs the one-token `__bootDone` conjunct for the auto-nav gate (b):
   user-visible on arrivals either way; PENDING THE USER'S DECISION
   (drawn as an optional task; the strip/pin fix is independent of it).
