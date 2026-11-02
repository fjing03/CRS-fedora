# pi session handoff — 2026-10-24 (Bug-1 shipped · Bug-2 to OpenCode · read me FIRST)

**Purpose:** this file transfers THIS chat's context to a NEW pi session. The new
session should read it top-to-bottom before any work, then `git log --oneline -20`
to see the receipts itself. Repo constants come free (AGENTS.md + the
`crs-workspace` skill auto-load) — this doc carries only the session-specific state.

## 1. State snapshot

- Repo: `/home/philler/Desktop/tarumt/degree/y3s1/BMCS3404 PROJECT I (4)/CRS-fedora-backend`
  — branch `fedora-backend`, HEAD = **`6149ab2`**, **16 commits unpushed**
  (origin = `fjing03/CRS-fedora.git` at `1903bea`). **NEVER push without the
  user's explicit word "push".**
- ⚠️ Parallel-agent update AT handoff time: the keyword-voice toast lane was
  COMMITTED by the other agent as `6149ab2` (toast bar 480→560,
  `showRich`/`_present`, the arrangement + venue-page keyword toasts, two
  changelog entries, ZERO test edits; `venue-intent-modal` 10/10 claimed by
  its author) — the tree is CLEAN again. The new session should NOT re-touch
  that lane's files without need; see §3 for what it was.
- This chat (chronological): the 5425 Bug-1/Bug-2 investigation → Bug-1
  apply → the trigger-format + picker-width follow-up → the Bug-2 OpenCode
  handoff. The parallel keyword-voice lane landed mid-session as `6149ab2`.

## 2. Shipped by THIS chat (all committed by this chat; a parallel agent
committed its own keyword-voice lane as `6149ab2` — see §3)

1. **The 5425 Bug-1/2 Playwright investigation** (read-only): landing
   `?session=99&week=8&from=replacement-home` (AMSE1003 · W8 · B111; the
   brief's described card = session 67/W14). Bug 1 = the Conflict-Schedule
   strip kept the boot-frozen anchor (`anchorSlotFromPinned()` built from
   `PAGE_DATA.pinnedSession` + boot `urlParams`) after a subject switch
   re-pinned the new subject's earliest conflict. Bug 2 = the venue family
   (below). Both root-caused at code+live level.
2. **`548c84d` — Bug 1 (user-approved):** `renderTitleSummary()` now prefers
   the LIVE `window.pinnedOccurrence` (when its occurrence exists in the
   current subject's conflict list — via the existing
   `extractSlotsForSubject`) over the boot-frozen virtual anchor; and the
   auto-pinned trigger note is now written AFTER `renderSlotPicker` (that
   function re-set "Select a slot to replace" internally — the second half
   of the bug). NEW spec `tests/replacement-strip-anchor.spec.ts` **4/4**.
3. **`4711bf8` — user follow-up:** (a) the trigger uses the SAME picked-slot
   format — **promoted ONE shared `slotTriggerLabel(slot)` formatter** used by
   `commitSlotSelection` picks AND the `applySubjectChange` auto-pin (the
   2026-10-11 BRIDGE-PATCH +1 week-number law lives inside it); (b) the slot
   picker **60% wider** (`.slot-dd` flex-basis 180→288 / cap 240→384;
   `.slot-dd-panel` min-width 320→512). Battery 24/24; pint/phpstan-0;
   phpunit **230/230 stands from 548c84d** (Blade-only text/CSS since — NOT
   re-run, noted honestly).
4. **`248d3f6` — the Bug-2 OpenCode handoff** (docs): see §4.

## 3. The keyword-voice toast lane (COMMITTED as `6149ab2` — not this chat's work)

(Update: the in-flight edits present at handoff time were committed by the
parallel agent while this doc was written; kept here so the new session
knows what `6149ab2` is.) The "keyword-voice toast" work:
- `public/css/theme.css` — `.toast-bar` max-width 480 → 560.
- `public/js/ui-common.js` — `ToastManager.showRich(message, duration)` + a
  `_present(message, opts, asHtml)` refactor (asHtml = innerHTML; CALLERS MUST
  pass constant strings, never user data).
- `replacement-arrangement.blade.php` — `selectBlock`'s canPlaceBlock-fail
  toasts + `renderBookingIntent`'s "no longer available"/fallbackAttribution
  em = CAP+bold+`--color-on-error/primary/success-container` spans.
- `venue-timetable.blade.php` — the gap notice's 4th line = "Not enough room
  below — pick a Free Cell.".

**The new session must NOT re-touch/re-commit these** (now inside `6149ab2`,
claimed green by its author on `venue-intent-modal` 10/10). Any NEW commit by
the new session = explicit paths of ITS work only (no `git add -A`).

## 4. Bug 2 — handed to OpenCode (do not re-implement in pi; supervise/verify)

The user approved Bug 2 with a full brief and asked for an OPENCODE handoff
instead. The complete prompt = **`prompts/opencode-prompt-arr-slot-original-venue-sync.md`**
(COMMITTED in `248d3f6`; it is NOT gitignored). Its essentials, so the new
session can verify OpenCode's result:
- **The law:** every (non-auto) `slotTrigger` pick re-selects the picked
  schedule's ORIGINAL room (`slot.venue` — dynamic, never hardcoded), updating
  BOTH `venueDropdown.getSelected()` and the `.venue-dd-label`; the OPTIONS
  keep the existing eligibility filter (`buildVenueFilter` semantics); when
  the original room is NOT offered → the STANDING fallback law (first-filtered
  ONLY when the current venue would render dead; else keep current + report).
- **The two gaps:** Gap A — same-session picks (a different conflicted week of
  the same session) currently skip the venue family ENTIRELY in
  `commitSlotSelection` (~:3259); Gap B — session-change picks keep an
  eligible-but-inherited venue.
- **The pairing law:** the label goes through `venueDropdown.select(code)`
  (ui-common.js :3142 — writes `_updateTrigger`), wrapped in the
  `revertingChange` true/false pair so `onVenueChange` (:1775) early-returns;
  then an explicit `applyVenueChange(code)` (:1816 — grid + `__fillVenueChips`
  + favstar). Gate on `window.__bootDone && !(opts && opts.auto)` — the
  pre-boot URL/pinned arrival laws stay.
- **Reality check for verification:** the user's example row "AMSE1003 (L) W14
  Thu 12:00 @ B010" is NOT verified on this dataset (session 99 = AMSE1003 L
  Mon 9–11 B111). Demand REAL picker rows (dump `slotPickerSlots`) in any
  receipt; a missing row = BLOCKED with evidence, never invented data.
- OpenCode must also create the SDD lane `.sdd/changes/arr-slot-original-venue-sync/`
  + spec `tests/arr-slot-original-venue-sync.spec.ts` (5 rows, staff-5425
  storageState), changelog entry, ONE conventional commit, never push.

## 5. SDD inventory (awaits the user's verify/archive word)

- `.sdd/changes/venue-description-doc-b006-rule` — applied through the chips
  parity/staleness commits (pre-`4711bf8`); review-log + archive still pending.
- `.sdd/changes/replacement-strip-stale-anchor` — applied today (`548c84d`),
  proposal/tasks/review-log written; not yet verified/archived.
- Archives under `.sdd/archive/2026-10-23-*`; verify/archive day procedure =
  re-run each lane's gates → review-log → move to `.sdd/archive/2026-10-24-*`.

## 6. Session habits to carry (the non-auto-loaded ones)

- Reply in B1-level English when asked; present choices as numbered options
  with a recommendation.
- Receipts-first: never claim green that wasn't run; the known documented reds
  (venue-timetable TC34/35, ui-regression TC-2.x staleness, the parked
  battery-load flake family — parked 1–2 months by the user) stay red-labeled.
- **TC70 (`tests/venue-timetable.spec.ts:690–730`) NEVER executes** — always
  `--grep-invert "TC70"` in battery runs.
- Serve ritual = TWO separate commands: `pkill -f "[a]rtisan serve" || true`;
  then `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`.
- Demo DB = `class_replacement_fresh` (never re-seed / `migrate:fresh`); tests
  use `class_replacement_testing`; pgAdmin repairs = USER-RUN ONLY.
- Scratch probes live in `/tmp/*.cjs`; purge before committing.
- `prompts/pi-prompt-*.md` = gitignored local handoffs (this file is one);
  the OpenCode prompt above is the exception (gitignored pattern is
  `prompts/pi-prompt-*` only — commit it deliberately).

## 7. Awaiting the user (as of the handoff)

1. The "push" word — 16 commits pending (through `6149ab2`).
2. Bug-2: unleash OpenCode with the §4 prompt, then pi verifies its receipts.
3. The verify/archive day for the two applied lanes (§5).
4. Older TBDs: the venue-page description chip (B005/B006 lane), the venue
   page's plain `#venueDescription` line → chip conversion.
