# design — `cohort-venue-replace-now`

Reads against the frozen proposal.md. All file:line anchors verified
2026-10-24 (pre-apply); the implementer re-greps at apply.

## D1 — Cohort page blast surface (resources/views/livewire/cohort-timetable.blade.php)

1. **markup (~:159–166)** — the `@section('modal-footer')` right column gains
   the venue's button markup, retargeted:

   ```blade
   <button class="btn-replace-now" id="btnReplaceNow" style="display:none"
           onclick="goToReplacementFor(currentModalEvent?.id, currentWeek + 1, 'cohort-timetable')">Replace Now</button>
   ```
   - `currentModalEvent?.id` (NOT `?.sessionId`) — the cohort `baseEvent`
     shape keys the session id at `id`;
   - `currentWeek + 1` — the handoff week = the HUMAN week number (the
     arrangement's `pinnedSessionPayload` validates 1..weeks; the pattern
     the BRIDGE PATCH `+1` rule spells page-wide);
   - `currentModalEvent` reads the window-scope modal state — **REVIEW R1
     CRITICAL FIX: the cohort page does NOT maintain this channel today**
     (no `window.currentModalEvent` anywhere in the blade — the earlier
     ":527 verified" receipt was WRONG). T1 lands BOTH the init
     (near the bridge block ~:192: `window.currentModalEvent = null;`)
     AND the assignment (`window.currentModalEvent = event;` as the first
     statement of `openModal`, before `openClassModal`): without them the
     click throws `ReferenceError` on a fresh load (the optional chain
     guards the property, NOT the unbound identifier); my-timetable (:138
     init / :151 assign) and venue (:218 / :295) are the two precedents.
   - The(`/cohort-timetable` back-route entry added in D3 makes the
     `from=cohort-timetable` param resolvable.

2. **the comment retirement** — the footer comment "…no Replace Now on
   cohort — the frozen design D1/D3" rewrites to the supersession note
   (the user's decision superseding D1's no-button point, dated 2026-10-24).

3. **the gate at openModal (~:525–545)** — before the existing
   `CancelClass.renderButton(...)` call:

   ```js
   const replaceBtn = document.getElementById('btnReplaceNow');
   if (replaceBtn) {
       const isMine = event.mine === true || event.lecturer === MockData.currentUser.name;
       const isConflict = (weekData[currentWeek].days[event.di] && weekData[currentWeek].days[event.di].holiday)
           || event.status === 'conflict';
       replaceBtn.style.display = (isMine && isConflict) ? 'flex' : 'none';
   }
   ```
   - APPLY-PHASE REFINEMENT (receipt 2026-10-24): the conflict arm reads
     the page's OWN isConflict definition (statusClassFn's
     `(day.holiday) || status === 'conflict'`) — the cohort `baseEvent`
     stamps only the venue-rule verdict; holiday weeks stay status-normal
     in the payload (the AMSE2003 (T) Dec-24 class = status-normal ON the
     W14 holiday). The venue payload bakes holidays into its per-week
     status — so the "venue gate family" parity is honored by reading the
     PAGE's conflict truth (the weekData-local `days`, the same read the
     openClassModal two lines below uses).
   - the SAME formula the venue page runs (its `event.mine` arm kept for
     parity; the cohort payload sets neither `mine` nor a phantom);
   - NO occurrenceFact dependency (the cohort/venue pages' grid gates at
     the rendered cell — the modal opens from a visibly rendered row);
   - the gate runs at EVERY openModal (a modal reopened after a week
     switch re-evaluates — the display:none default = fail-closed).

## D2 — Venue page (resources/views/livewire/venue-timetable.blade.php)

- The ONE-attribute change at :186 — the click arg
  `currentWeek` → `currentWeek + 1`. The gate block (:311–320) stays
  byte-identical (its `event.mine` arm + `lecturer` read is already the
  house shape).
- The close-below text `Replace Now [own + conflict, viewed week] …`
  comments unchanged; add a dated one-line receipt pointing at this lane
  (the "viewed week" claim now honest: the handoff names the SAME week
  the pair the arrangement validates).

## D3 — BackNavigator routes (public/js/ui-common.js :3017)

- `static #routes` gains ONE row pointing at the CANONICAL route (review
  R1: `routes/web.php` :37 = `'/cohort-timetable'`; the `-ui` URL is a
  temporary redirect :99 — baking it in would break when that cleanup
  lands):
  `'cohort-timetable': '/cohort-timetable',`
- The other four rows + `getDefault()` + `getBackUrl()` byte-identical.
- NOTE the `getBackUrl` context-keeper branch stays venue-only (the cohort
  handoff carries no `code/cohort` context params — the cohort page
  restores the selection from its own LastVisit memory).

## D4 — Tests

- **NEW `tests/cohort-venue-replace-now.spec.ts`** (Playwright, the
  storage-state regime = `.auth/staff-5425.json` — the SAME actor family
  the class-modal-affordances spec uses; rows):
  1. cohort: the OWN conflicted class's modal shows `Replace Now`
     (visible), clicking hands off
     `/replacement-arrangement?session=<id>&week=<human>&from=cohort-timetable`;
  2. cohort: another lecturer's conflicted class's modal HIDES the button;
  3. cohort: the OWN non-conflict class's modal HIDES the button
     (+ the fail-closed pin: with the user bridge empty the default
     `display:none` stays — review R1 optional);
  4. venue regression: the OWN conflicted class's modal button hands off
     `?week=<human>` (the +1 receipt — the off-by-one fix; NOTE: ALSO cures
     the venue Week-1 silent no-op — the `!week` guard ate the 0-based
     index 0 — log that second honest fix in the venue changelog);
  5. the way-back: BackNavigator resolves `from=cohort-timetable` →
     `/cohort-timetable` (the CANONICAL route — R1; a direct URL probe on
     the arrangement page's back-btn or a unit-ish window probe reading
     the static map — pick the cheap probe at apply).
  - Honest skips for data-not-present rows (the same law as the
    session-context skips in class-modal-affordances).

- **Existing `tests/class-modal-affordances.spec.ts`** — reviewer R1
  verified it contains NO venue hand-URL `week=` assertion (zero grep
  matches), so the `+1` change cannot move any row red; keep a conditional
  ×1 re-run at T5 purely as the regression tripwire. 

## D5 — the changelog + commit

- `page-changelogs/cohort-timetable-changelog.md` +
  `page-changelogs/venue-timetable-changelog.md` dated entries.
- ONE conventional commit `feat(cohort-venue-replace-now): …` with explicit
  paths; NEVER push (the standing rule).

## Gates (T-final)

- pint + `vendor/bin/phpstan analyse --memory-limit=1G`
- full phpunit (baseline 258)
- the new spec ×2 + the class-modal-affordances battery ×2 (the route
  probe included)
- NOT the spot battery (no surgery to any confirm-guard path — the change
  rides the existing modal footer; the class-modal-affordances family IS
  the spot battery here).
