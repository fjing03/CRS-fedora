# OpenCode handoff — `arr-slot-original-venue-sync` (Bug 2, user-approved, TO IMPLEMENT)

**Task:** fix Bug 2 of the 2026-10-24 5425 investigation on the Replacement
Arrangement page — the venue dropdown (`venue-dd-label`) must FOLLOW the
newly selected conflict schedule's ORIGINAL venue whenever the user switches
the conflicted slot via `slotTrigger`. Bug 1 (the strip anchor) is ALREADY
fixed by commits `548c84d` + `4711bf8` — do not redo it. Bug 2 = HELD until
this handoff; nothing is implemented yet.

## 1. The repo + the state

- Repo: `/home/philler/Desktop/tarumt/degree/y3s1/BMCS3404 PROJECT I (4)/CRS-fedora-backend`
  — branch `fedora-backend`, HEAD `4711bf8`, tree CLEAN, 14 commits unpushed
  (**NEVER push without the user's explicit "push"**).
- ⚠️ There is a SECOND checkout at `/home/philler/Desktop/FYP/CRS-fedora-backend`
  — always `cd` to the tarumt path first; scratch probes go in `/tmp`.
- Live server = `php artisan serve --port=8000`; DB = `class_replacement_fresh`
  (PostgreSQL, user `philler`, never re-seed, never `migrate:fresh`; tests use
  `class_replacement_testing`). Auth states in `.auth/staff-5425.json` etc.
  (re-run `npx playwright test tests/auth.setup.ts --project=setup` if expired).

## 2. The user's law (verbatim intent, 2026-10-24)

Every time the user switches the conflicted slot using `slotTrigger`, the venue
dropdown automatically selects and displays **the original venue of the newly
selected conflict schedule**:

1. The original venue must come **from the selected conflict schedule** — never
   from the previously selected subject, the previously selected venue, or the
   available venue options. Read it at runtime (no hardcoded B010/venue codes).
2. Both the dropdown's **underlying selected value** AND the displayed
   `.venue-dd-label` must represent that venue (parity).
3. The venue OPTIONS keep the existing eligibility/filtering rules for the
   current replacement subject + session type (`buildVenueFilter` semantics).
4. **If the schedule's original venue is NOT in the filtered options — do NOT
   silently select an unrelated venue.** Investigate and keep the standing
   fallback law (below) and REPORT the chosen behaviour.
5. Smallest safe change; no unrelated refactors; no eligibility-rule edits.

## 3. The code map (verified receipts, today's tree)

All in `resources/views/livewire/replacement-arrangement.blade.php` (line nos
≈, they shifted with 548c84d/4711bf8 — locate by name):

- `commitSlotSelection(slot, index, opts)` (~:3259) — **the fix lives here**.
  Current shape: the venue family runs ONLY inside
  `if (slot.sessionId && Number(slot.sessionId) !== currentSessionId)` (a
  session CHANGE), and only falls back when the current venue is INELIGIBLE
  (`curV && !venueEligible(curV, courseCtx())` → `venueDropdown.getFiltered()[0]`
  → `if (selectedBlock) deselectBlock(); revertingChange = true;
  venueDropdown.select(target.code); revertingChange = false;
  applyVenueChange(target.code)`), else `window.__bootDone { saveCurrentWeek();
  buildTimetable(); }`.
  - **Gap A (the bug):** a same-session pick (a different conflicted WEEK of
    the same session — the common case when a subject has several conflicted
    occurrences) skips the venue family ENTIRELY.
  - **Gap B:** even on a session change, an ELIGIBLE-but-inherited venue stays
    (the old code only moved when the current venue would render dead).
- `selectSlot(slot, index, opts)` (~:3220) — the confirm wrapper. NOTE: its
  CANCEL path re-commits the previous slot via
  `commitSlotSelection(slotPickerSlots[lastSlotIndex], lastSlotIndex)` with
  `revertingChange = true`. Your sync will re-run there — harmless/idempotent
  (it lands on the same original room), but DOCUMENT the decision.
- `applyVenueChange(newVenue)` (~:1816) — sets `currentVenue`, `buildTimetable()`,
  `window.__fillVenueChips(newVenue)` (the venue-page chips), `updateFavStar`.
  It does NOT touch the dropdown: the LABEL sync must go through
  `venueDropdown.select(code)` (which writes the label via `_updateTrigger`).
- `onVenueChange()` (~:1775) — the dropdown's `onSelect`. Guards
  `if (revertingChange) return;` — wrap your `venueDropdown.select()` in the
  same `revertingChange` true/false pair (the existing fallback shape) so the
  select does not raise the "Change Venue?" confirm modal mid-slot-switch;
  then call `applyVenueChange(target)` explicitly.
- `buildVenueFilter()` (~:3063) + `venueEligible(v, ctx)` (~:3049) — the
  fail-closed ruleMap options filter. DO NOT CHANGE their semantics.
- `slotTriggerLabel(slot)` (~:3247, shared formatter from 4711bf8) — untouched.
- `applyDefaultSlotSelection(slots)` (~:3363) — the auto default pick path
  (`opts.auto`, used by URL arrivals pre-boot). **Your sync must NOT fire for
  auto picks** — the URL/pinned venue governs pre-boot (arrival laws).
- `public/js/ui-common.js` `VenueDropdown`: class ~:3108, `select(code)` :3142
  (phantom guard: ignores a code not in `this.venues`; sets `selectedCode`,
  label, checkmarks, closes, fires `onSelect`), `getSelected()` :3161,
  `getFiltered()` :3157, `_updateTrigger()` :3616 (label = "CODE — Type (N
  seats)"). Ideally NO ui-common edit is needed; justify if you think otherwise.

**Where the "original venue" lives:** the picked slot object (from
`extractSlotsForSubject(code)` — the same objects the picker rows and the strip
anchor use) already carries **`slot.venue`** = the conflict schedule's original
room (shown in every picker row label "... @ B010"). No new payload needed.
`courseCtx()` gives the new headcount/type context for eligibility checks.

## 4. The approved implementation shape (then verify, don't blind-apply)

In `commitSlotSelection`, restructure to:

```js
if (slot.sessionId && Number(slot.sessionId) !== currentSessionId) {
    currentSessionId = Number(slot.sessionId);
    buildVenueFilter();
    window.pinnedOccurrence = { sessionId: slot.sessionId, week: slot.week + 1 };
    history.replaceState(...session/week/from...);   // keep as is
}
if (window.__bootDone && !(opts && opts.auto)) {
    /* ORIGINAL-VENUE SYNC (user law 2026-10-24): the picked schedule's own
       room is the selected venue + label on EVERY (non-auto) pick; options
       stay the eligibility-filtered family; not offered → the standing
       fallback law below. */
    const origCode = slot.venue;
    const filtered = venueDropdown.getFiltered ? venueDropdown.getFiltered() : [];
    const curVenue = String(currentVenue.code || currentVenue || '');
    let target = null;
    if (origCode && filtered.some(v => v.code === origCode)) {
        target = String(origCode);
    } else {
        // orig NOT offered: NOT silently unrelated — keep the standing law:
        // move to the first venue the matrix allows ONLY when the current
        // venue would render dead (ineligible for the new context); the
        // slot-change confirm already consented to the switch.
        const curV = (MockData.venues || []).find(v => v.code === curVenue);
        if (!curV || !venueEligible(curV, courseCtx())) {
            const first = filtered[0];
            if (first && first.code) target = String(first.code);
        }
        // else: current venue stays eligible → keep it (report this rule).
    }
    if (target && String(target) !== curVenue) {
        if (selectedBlock) deselectBlock();
        revertingChange = true;
        venueDropdown.select(target);   // value + label parity
        revertingChange = false;
        applyVenueChange(target);       // grid + chips + favstar
    } else if (target) {
        venueDropdown.select(target);   // parity even when equal (Test 5)
        saveCurrentWeek(); buildTimetable();
    } else {
        saveCurrentWeek(); buildTimetable();
    }
}
```

Decide + document (in the lane's design.md): (a) the selectSlot cancel/snap-back
idempotency; (b) whether `target` equal → `select()` re-fire is safe (it calls
`_close()` + fires onSelect under reverting — mirror the existing fallback's
pairing); (c) what the user sees when orig-not-offered-and-current-eligible
(keep current + the count note as the receipt — no toast unless the house
pattern requires one; do not add new UI chrome).

## 5. SDD lane + receipts (house layout)

- Create `.sdd/changes/arr-slot-original-venue-sync/` — `proposal.md`,
  `design.md` (including points 4a–4c above), `tasks.md`, `review-log.md`
  (mirror `.sdd/changes/replacement-strip-stale-anchor/` in language).
- New spec `tests/arr-slot-original-venue-sync.spec.ts` (5 rows). House laws:
  `test.use({ storageState: '.auth/staff-5425.json' })`; if you ever build a
  child context inside such a file it INHERITS that state — a genuinely bare
  context must declare `{ storageState: { cookies: [], origins: [] } }`
  explicitly (frozen bare-context law). **NEVER run TC70** — always
  `--grep-invert "TC70"` when touching shared battery runs.
- Changelog: dated entry at the TOP of
  `page-changelogs/replacement-arrangement-changelog.md` (law + tests + gates).
- ONE commit, explicit paths only (no `git add -A`), conventional style, gates
  inside the message: `fix(arr-slot-original-venue-sync, Bug 2 approved): …`.
  NEVER push.

## 6. The 5 Playwright tests (from the user's brief, adapted to REAL data)

⚠️ **Reality check first**: dump the actual picker rows per subject via
Playwright (`window.slotPickerSlots` or the rendered `.slot-dd-item` labels)
and write asserts from REAL rows. Verified facts: landing
`?session=38&week=14` shows BMSE2163 (P) with a single conflicted row
"(P) W14 · Dec 24, 12:00 PM - 1:00 PM @ B010"; session 99 = AMSE1003 (L) W8
Mon 9–11 @ B111; session 67 = AMSE2003 (L) W14 Thu 9–11 @ B100. The user's
example row "AMSE1003 (L) W14 Thu 12:00 @ B010" is NOT verified on this
dataset — if a needed row doesn't exist, mark that sub-test **BLOCKED** with
the row dump as evidence (never invent data or fixture edits).

1. **Follow the original venue:** with subject BMSE2163, pick its B010 row →
   the label becomes "B010 — …", and switching to any OTHER offered row whose
   original room differs → the label follows THAT room.
2. **Multi-slot alternation** across slots with different original venues
   (subject switches are allowed to create the variety — e.g. AMSE2003 T vs L
   rows) — the label updates per pick.
3. **Same subject, week alternation** (Gap A) — the venue family actually runs
   on a same-session pick; the label reflects that slot's original room.
4. **Subject-change interplay (Gap B law):** after a subject change with a
   different eligibility family, picking a row whose orig room is not offered
   → the dropdown shows the STANDING fallback result (first-eligible only when
   the current venue renders dead, else the current venue) and the options
   filter/count note stay the existing behaviour.
5. **Value/label parity:** `venueDropdown.getSelected()` === the code parsed
   from the label ("B010 — Computer Lab (N seats)") — read BOTH via
   page.evaluate; also assert the chips followed (`#venueCapacity`/tooltip
   `data-tip` on `#arrVenueDescription`) proving `applyVenueChange` ran.

Record before/after results per test (PASS/FAIL/BLOCKED + evidence).

## 7. Gates (the standing absolutes)

- Serve ritual = TWO separate commands: `pkill -f "[a]rtisan serve" || true`;
  then `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`.
- Your spec green ×1 minimum (×2 if any flake smell), plus the spot battery:
  `replacement-strip-anchor`, `session-arrival-pin-guard`, `default-pin`,
  `venue-cross-week-handoff`, `arrangement-earliest-week-default`,
  `venue-intent-modal`, `replace-now` (all `--grep-invert "TC70"`).
- The blade = a PHP file → `composer run lint:check` +
  `vendor/bin/phpstan analyse --memory-limit=1G`. Re-run phpunit only if PHP
  behaviour code changed (baseline = 230/230 from `4711bf8`; declare what you ran).
- Known documented reds (never claim green): venue-timetable TC34/35 baseline
  rows; ui-regression TC-2.x staleness rows; the parked battery-load flake family.
- Identities: PW `Tarumt@2026`; 5425 = the demo PL (storage state in use).
- Report back in the user's Final-Report format: 1) root cause, 2) changes
  made (files/functions), 3) verification per test (PASS/FAIL/BLOCKED + evidence),
  4) regression risks — then STOP for review; the user approves the push.
