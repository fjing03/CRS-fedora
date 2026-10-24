# design — `arr-slot-original-venue-sync`

## The restructure (one blade function: `commitSlotSelection`)

Old shape: the venue family lived ONLY inside the
`slot.sessionId && Number(slot.sessionId) !== currentSessionId` branch
(session CHANGE), and the fallback moved venues only when the current venue
was ineligible (`venueEligible(curV, courseCtx())` false → first filtered).
**Gap A:** a same-session pick (the same session's occurrence re-picked or a
second conflicted week of one session) skipped the venue family entirely.
**Gap B:** a session-change pick with an ELIGIBLE-but-inherited venue stayed.

New shape:

```js
if (slot.sessionId && Number(slot.sessionId) !== currentSessionId) {
    /* session re-derive: id, buildVenueFilter(), D1 pin, replaceState —
       unchanged */
    if (opts && opts.auto) {
        /* the ARRIVAL/AUTO-PIN law (D1): the old eligibility fallback +
           the else rebuilt buildTimetable — byte-identical, now explicitly
           the auto path (pre-boot defaults + post-boot subject-switch pin). */
    }
}
if (window.__bootDone && !(opts && opts.auto)) {
    /* ORIGINAL-VENUE SYNC (user law 2026-10-24):
       1. orig = slot.venue (runtime, from the SAME slot object the picker
          rows + strip anchor read — no new payload).
       2. offered check = filtered.some(v => v.code === orig) (filtered ⊂
          venueDropdown.venues, so it also defeats the select() phantom
          guard silently returning on a code outside the deck).
       3. orig offered → target = orig. NOT offered → the STANDING fallback
          law: target = filtered[0] ONLY when the current venue would render
          dead (ineligible for courseCtx()); if the current venue is
          eligible, KEEP it (documented receipt, no new toast/chrome).
       4. target != cur → deselectBlock() (block + mid-switch venue move),
          revertingChange true/false pair around venueDropdown.select(target)
          (onVenueChange early-returns — no "Change Venue?" confirm; label
          parity through _updateTrigger), then applyVenueChange(target)
          (grid + __fillVenueChips + favstar).
       5. target == cur → venueDropdown.select(target) parity refire only
          (fires onSelect under reverting — safe; _close() is already the
          panel-closed state) + saveCurrentWeek() + buildTimetable().
       6. target == null → saveCurrentWeek() + buildTimetable() (as today). */
}
```

The old in-branch fallback is REMOVED for the non-auto path — the generic
sync's step 3 reproduces it with the user's orig-first precedence (an
offered orig now wins over the first-filtered move that Gap B law would
have made for session-change picks).

## D1 — why the fallback stays for `opts.auto` (DEVIATION note, corrected by probe)

The handoff prompt's sketch drops the whole venue family for ALL auto
picks. The probe corrected the premise: POST-boot the fallback is already
inert (applySubjectChange re-keys currentSessionId BEFORE the auto pick,
so its commit sees a same-session/no-change family skip — today's label
stays B010 on a P→L switch). POST-boot auto picks skip the family
IDENTICALLY under both shapes. But PRE-boot the session change is real:
currentSessionId starts NULL (:2378), so the arrival default pick
(applyUrlParams's committing default + the first-option fallback) runs
the family under `opts.auto` — the dead-venue fallback + the
else buildTimetable ARE the arrival law. The restructure therefore keeps
the old fallback + else-buildTimetable VERBATIM for the session-change
branch on `opts && opts.auto` — the pre-boot arrival laws stay
byte-identical — and the NEW law owns the non-auto path.

Net effect on the post-switch P→L dead-venue sighting from the probe: NOT
fixed by an auto action (unchanged → the honest pre-existing state), but
the user's FIRST MANUAL PICK now lands the schedule's own room and the
grid lives — the approved law's own coverage.

## D2 — the `selectSlot` cancel/snap-back idempotency (decision)

The cancel path re-commits the previous slot via
`commitSlotSelection(slotPickerSlots[lastSlotIndex], lastSlotIndex)` with
`revertingChange = true` set by the caller. The sync runs on that re-commit
(selects the PREVIOUS slot's orig room — the venue that was already
current either by the last sync or by arrival) → same room, step 5 parity
refire, no residue. IDEMPOTENT by construction — no special-casing, caller
never reset needed. Documented; no code for it.

## D3 — equal-target select() refire safety (decision)

`venueDropdown.select(code)` calls `_updateTrigger` (label write — the parity
assert target), `_updateActive` (checkmarks), `_close` (panel state — already
closed after a row click; on keyboard focus picks it closes it, cosmetic),
and fires `onSelect` → `onVenueChange()` → early-`return` under
`revertingChange` (no confirm modal mid-slot-switch). Mirrors the existing
fallback pairing — SAFE.

## D4 — the orig-not-offered-and-current-eligible receipt (decision)

The user sees: the venue stays, the count note (existing #venueCountNote)
stays. No new toast — the standing rule forbids new UI chrome without a
house pattern; the picker row itself displays "... @ <orig>" so the mismatch
via the strip. NOT reproducible natively on this demo deck (see the
reality-check): every deck session's orig room self-fits its own ctx
(ruleMap: labs P-only+capacity; Lecture/Tutorial admit L,T). The sync's
fallback half is therefore covered by the D1 clone (identical mechanics)
+ the strip-anchor spec's spot battery; the non-auto keep-current branch
gets its receipt from a scripted-venue-family probe rather than an invented
dataset row.

## REALITY CHECK receipts (2026-10-24 probe `//tmp/probe-rows.cjs`, staff-5425)

- Subjects on the 38/14 deck: AMSE1003 (1 row: W8 Mon Nov 09 9–11 @ B111,
  sid 99 · 25 students), BMSE2163 (1 row: W14 Thu Dec 24 12:00–13:00 @ B010,
  sid 38 · 12), AMSE2003 (2 rows: W14 Thu Dec 24 9–11 @ B100 sid 67 · L 15,
  and W14 Thu Dec 24 11–12 @ B100 · T).
- NO session carries multiple conflicted weeks → the multi-week pure Gap-A
  variant is BLOCKED-fallback to the same-session manual re-pick row (sid 38
  re-picked while current); the venue-family RUN semantics are still proven.
- The brief's AMSE1003 "W14 Thu 12:00 @ B010" row does NOT exist — never
  invented.
- Boot label arrival pre-change: 38/14 → "B010 — Lab (28 seats)",
  99/8 → "B111 — Lecture Hall (80 seats)", 67/14 → "B100 — Tutorial (35
  seats)" (the arrival laws pin correctly).

## R2 — the 2026-10-24 68/14 sighting (DECISION-LEVEL amendment by the user's new word)

**Sighting:** land `?session=68&week=14` (AMSE2003 T @ B100), switch the subject
to BMSE2163 (P @ B010) — the strip immediately reads `@ B010` but the
dropdown keeps `B100 — Tutorial (35 seats)` → the whole grid renders red
(`cell-available` = 0) until the user MANUALLY picks the slot row.

**Mechanism (ӎarker receipts + code):** the post-boot auto-pin's default
pick NEVER reaches commitSlotSelection — `applyDefaultSlotSelection`'s
branch-1 pinned-slot early-return
(`urlParams.code && day && start && originalVenue`, hydrated from
PAGE_DATA.pinnedSession at boot) skips it; applySubjectChange only writes
pinnedOccurrence + the trigger/strip. So the round-1 sync (gated
`__bootDone && !opts.auto`) had no hook on the auto-pin at all.

**The amendment:** the sync is a SHARED helper `syncVenueFamily(slot, phase)`
called from BOTH `commitSlotSelection` (manual picks, `__bootDone` — the
auto/arrival conjunct dropped; pre-boot arrivals stay excluded by
`__bootDone` alone) AND `applySubjectChange`'s post-boot tail
(`firstConflictRow`, phase 'auto-pin'). The CTX-freshness problem of the
commit-time call (which raced applySubjectChange's tail re-filter during
debugging) disappears — the helper re-runs `buildVenueFilter()` itself. The
equal/no-target saveCurrentWeek+buildTimetable is SKIPPED for 'auto-pin'
(the tail's own buildTimetable already positioned the grid).

**Net law now:** every POST-boot context arrival (user pick OR subject-switch
auto-pin) agrees the dropdown with the schedule's ORIGINAL room; PRE-boot
arrivals keep the bridge fallback verbatim. The auto-pin follows even when
the inherited venue was eligible (orig wins — the manual law's symmetry).
