# Design — session-arrival-pin-guard

## D1 — the block after the fix (gate + pure deletion; reviewer round 1 🔴1)

Shipped today (`:2961–2975`, the T18 rider):

```js
if (firstConflictRow) {
    window.pinnedOccurrence = { sessionId: firstConflictRow.sessionId, week: firstConflictRow.week + 1 };
    urlParams = urlParams || {};
    window.urlParams = Object.assign({}, window.urlParams, {
        day: firstConflictRow.day,
        start: firstConflictRow.start,
        originalVenue: firstConflictRow.venue,
    });
    currentSessionId = Number(firstConflictRow.sessionId);
} else { … else-ride … }
```

After the fix:

```js
/* occurrence-week-pin-truthfulness (T18, user request 2026-10-19/20): a
   POST-BOOT subject switch auto-pins the earliest conflicted occurrence —
   the picker-first ask only when the subject carries NO conflicted slot.
   session-arrival-pin-guard (user report 2026-10-20): ?session= arrivals
   switch the subject PROGRAMMATICALLY at the boot (pre-__bootDone) — the
   auto-pin must not hijack the arrival pin there; the else-ride keeps it
   and courseCtx() resolves the strip from the pinned session's own facts.
   The former window.urlParams shadow block is deleted: no consumers exist
   (verified: the only occurrence repo-wide was the write), and routing
   these writes into the local urlParams would hijack the POST-BOOT
   anchor/submit fallback (the bug class this change kills). */
if (firstConflictRow && window.__bootDone) {
    window.pinnedOccurrence = { sessionId: firstConflictRow.sessionId, week: firstConflictRow.week + 1 };
    currentSessionId = Number(firstConflictRow.sessionId);
} else {
    /* no conflicted occurrence for this subject → keep the picker-first
       ask (the honest guard); on a boot-time arrival WITH a pin, the
       ride below is THIS branch: the arrival pin stays the user's. */
    const wanted = autoPinRide ? String(autoPinRide.sessionId) : null;
    currentSessionId = wanted && currentCourse.sessions && currentCourse.sessions[wanted]
        ? Number(wanted)
        : (currentCourse.sessions ? Number(Object.keys(currentCourse.sessions)[0]) : null);
}
```

- NO local `urlParams` writes anywhere in the block — the shipped
  post-boot path never wrote the local either, so deleting the rows
  leaves POST-BOOT behaviour unchanged BY CONSTRUCTION (this restores
  the proposal's byte-parity row the round-1 draft broke).
- `window.__bootDone` pre-assignment reads `undefined` (falsy) — the
  file's existing convention (:3008, :3240 read it the same way).
- The else-ride keeps the pre-existing fallback when the pinned session
  misses the course's `sessions` map (falls to the first key — untouched).

## D2 — why pure deletion is correct (the receipts)

- `window.urlParams` = ONE occurrence repo-wide (:2968, the write) — zero
  readers (verified; reviewer round 1 confirmed the grep).
- The shipped block's only local-visibility side effect = nothing:
  `anchorSlotFromPinned` (:2374) reads the local `urlParams` hydrated by
  the boot from the SERVER pin — after the fix that stays exactly the
  arrival session's own facts on both paths (no mixed-anchor hijack
  possible: the same source, one writer).
- The submit `orig` fallback (:1992–1993) reads the local → honest on
  both paths.

## D3 — what the arrival looks like post-fix (honest state + residuals)

`?session=49&week=14` arrival:
- `pinnedOccurrence` = `{49, 14}` end-to-end (submit honours the click).
- `currentSessionId = 49` → `courseCtx()` = 49's session entry → strip:
  `(P)` · `DFT2 (S1) G1` · `18` — all 5 STRIP lines describe the session
  the user clicked.
- The strip's slot line unchanged: the local anchor (49's own Fri Dec 25
  11:00–12:00 @ B011) + `PAGE_DATA.pinnedSession.week`.
- The trigger: T18's "Auto-pinned" note (:2987) is a dead write on the
  arrival — the boot tail (:3542–3552) rewrites the trigger from the pin
  anchor (probed final state both before AND after) — byte-identical
  visuals.
- **KNOWN RESIDUAL (documented; user decision pending — NOT a rider)**:
  the T18 auto-NAV block (:3019–3027) — ungated today AND after this fix
  — runs on a truthy `firstConflictRow` and navigates the ARRIVAL grid to
  the earliest conflict's week (`weekNav.selectWeek` + `save()`), so the
  grid can land/persist W8 while strip/pin say W14; a later bare visit
  restores it from localStorage. This is PRE-EXISTING (the shipped state
  — the probes' strips sat on W14 while the grid navigated). Two options:
  (a) document as a known residual (this change does that), or
  (b) add the same `window.__bootDone` conjunct to the auto-nav's `if`
  (one token; post-boot unchanged; user-visible on the arrival = the grid
  stays on the pinned week). Drawn in tasks as an OPTIONAL task pending
  the user's word.

## D4 — sequencing with `venue-cross-week-handoff` (reviewer round 1 🔴2)

**Land together, or venue-cross-week-handoff first — never this alone
before it.** Reason: today the venue cross-week repro's submit files the
TRUE pair `{97, 8}` ONLY because the T18 clobber coincidentally rewrites
the phantom `{97, 7}`; with this gate landed alone, the clobber dies →
the pin stays `{97, 7}` (a nonexistent pair — the server's
`no_occurrence_pin`/fact-loser family would reject or file wrong).
The threading fix makes the arrival pin `{97, 8}` honestly; this gate
then protects it. Related honesty: A6/C2's "green" post-fix rides the
correct threading arriving first (fragile coupling — named here).

## D5 — blast matrix (+ reviewer round 1 additions)

| Flow | Today | After | Note |
|---|---|---|---|
| Post-boot subject switch (bare dropdown) | auto-pin + local untouched | bit-identical (the writes: pinnedOccurrence + currentSessionId only; the shadow block deleted = no-op for today's behavior) | gate TRUE |
| `default-pin.spec.ts` — 3 rows (T18-S1/S2/S3) | green | green (S3's dd lists only conflict-bearing subjects :2495–2499, so the else-ride's null-pin case never occurs) | unchanged rows |
| Fallback prefill path (`armIntentFallbackPrefill` :2534+, no `?session=`) | post-boot auto-pin fires; the 2c bridge (:2590) LAST-WINS the matched-session pin | identical (2c's last write stands; the gate is true post-boot; the deleted shadow never fed this flow) | byte-identical |
| `?session=` arrival strip | `(L)` + wrong cohorts/count | `(P)` + own cohorts/count | THE FIX |
| `?session=` arrival pin | `{97, 8}` (hijacked) | the arrival pair | submit honours the click |
| `?session=` arrival GRID week | auto-nav rebuild/persist to the earliest's week | UNCHANGED (the documented residual, D3) | user decision pending |
| venue-intent A6/C2 | green | green | depends on the co-change's correct threading (named coupling) |
| `window.urlParams` | a diverged shadow | absent | zero readers (verified) |

## D6 — verification vehicle (locator + vehicle alignment per review)

NEW `tests/session-arrival-pin-guard.spec.ts` (staff-5770 storage state;
follow `replace-now.spec.ts`'s S5a/S7 house pattern):

1. Direct arrival `?session=49&week=14&from=my-timetable` → assert the
   strip `(P)` + `DFT2 (S1) G1` (and NOT `DFT2 (S1) G1 + DSF2 (S1) G1`);
   assert `#slotTriggerText` is NOT the T18 "Auto-pinned: the W8 …"
   wording (the dead-write claim becomes enforced); assert
   `window.pinnedOccurrence` = `{sessionId: 49, week: 14}` after settle.
2. Direct arrival `?session=39&week=14&from=replacement-home` (the
   user's own sighting) → the strip `(P)` + `DSF2 (S1) G1` +
   `window.pinnedOccurrence` = `{39, 14}`.
3. Click-through control: my-timetable W14 (`#weekSelect` '13') → the
   Fri `AMCS2093(P)` block scoped to the `tr[data-day-index="4"]` row's
   hour-6 cell (bare `td[data-day="4"]` matches a day-column of hour
   cells — do NOT use it bare) → class modal → assert `#btnReplaceNow`
   visible (the W14-Friday Christmas-Day fact) → Replace Now → lands
   `session=49&week=14&from=my-timetable` → the same honest-arrival
   asserts (ties the producer to the boot).

Gates: blade-JS only → `php -l` + the Playwright run
(`npx playwright test tests/session-arrival-pin-guard.spec.ts
tests/default-pin.spec.ts tests/replace-now.spec.ts
tests/venue-intent-modal.spec.ts [--plus tests/venue-cross-week-handoff.spec.ts
if already landed] --grep-invert "TC70"`); phpunit not in the delta (no
PHP edits). The /tmp pin-probe shape re-run on both URLs = the manual
receipt. Stale-cache ritual first.

## D7 — skipped batches

Condensed single-round batch review (explore-brief baseline;
proposal/design/tasks reviewed together; round 1 verdict folded in —
see review-log.md) — ≤2h 2-edit bug fix; `specs/` skip (the mechanism +
receipts live here; the sequencing + residual contracts are written out
in D3–D5).
