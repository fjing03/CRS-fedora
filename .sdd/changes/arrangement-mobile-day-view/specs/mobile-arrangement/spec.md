# Mobile Replacement Arrangement — capability specification

Batch 3 · 2026-11-11. Normative baseline: frozen `proposal.md` and `design.md`,
including design §12 review safeguards. `explore-brief.md` records user decisions.
These requirements apply only to `/replacement-arrangement`; no implementation
or browser execution permission is granted by this document.
Amended baseline: user chose the borderless arrangement rule shipped in `28f9f4d`;
this supersedes the prototype's ownership rings/hint, not its approved layout.

## Terms and traceability

- **Mobile:** viewport width <=768px. **Desktop:** width >768px.
- **Eligible date:** an existing real semester day that is not Sunday, not a
  public holiday, and not too soon under the existing calendar helpers.
- **Available time:** existing canonical table marks that half-hour available;
  this alone does not imply the whole class fits from that time.
- **Saved entry:** the existing venue/week-keyed selection, not just the current
  grid's `selectedBlock` pointer. No new independent booking store is permitted.
- **Transient warning:** non-critical selection feedback, not an opened details
  sheet, confirmation or server/request-outcome error.
- Scenario IDs below are stable acceptance-test references for Batch 4 tasks.

| Requirement | Proposal | Design |
|---|---|---|
| MA-01 responsive isolation | scope, §7 | §§1, 4, 9 |
| MA-02 original context/controls | §1 | §4 |
| MA-03 calendar/navigation | §2, §6 | §5, §12.2/12.4 |
| MA-04 canonical timeline | §3 | §§1–3, 6 |
| MA-05 selection/removal | §§3–4 | §6 |
| MA-06 summary/actions | §4 | §§6–8 |
| MA-07 information sheets | §5 | §§7–8 |
| MA-08 feedback lifetime | §5 | §§6–7 |
| MA-09 current guards/identity | preserved behaviour | §§2–4, 6 |
| MA-10 expiry/save preservation | §§4, 6 | §5, §12.2 |
| MA-11 mobile-origin submission | §§4–5, 7 | §7, §12.3 |
| MA-12 keyboard/focus | §7 | §§7–9, §12.1 |
| MA-13 geometry/themes | §7 | §8 |
| MA-14 lifecycle/reveal | §§2, 7 | §§3, 9, §12.4 |
| MA-15 operational boundaries | out of scope/verification | §§2, 10–11 |

## MA-01 — Responsive isolation and one control identity

The system SHALL show the approved day-view presentation on mobile and preserve
the existing desktop weekly presentation and interactions on desktop. The adapter
SHALL keep one set of actual subject/slot/venue/week/Tip/Submit controls, preserving
IDs, values, handlers and shared dropdown identity when relocating them. Inactive
presentation containers SHALL be hidden and inert without hiding active controls.

### Scenarios

- **MA-01a — Boundary:** Given the page at 768px, the day view is active; at
  769px the existing desktop view is active. In each layout only its active
  controls are visible, keyboard reachable and exposed to assistive technology.
- **MA-01b — Desktop parity:** Given the pre-change desktop baseline, after this
  change desktop control ordering, styles, legend, hover/focus tooltips, selected
  block removal and booking interactions remain unchanged. Other pages do not
  inherit the new arrangement mobile styles.
- **MA-01c — Identity/resize:** Given selected subject, occurrence, venue, week
  and saved block, when crossing the breakpoint repeatedly, the same actual
  controls return to their exact desktop hosts, values/selection remain intact,
  no duplicate IDs/listeners/booking are created and no change confirmation is
  triggered merely by resizing. Open dropdown panels close before relocation.

## MA-02 — Compact original class context

The mobile context SHALL show the selected subject and original date/time without
expansion. Extra current facts SHALL be available by expanding it, including
cohorts, student count and original venue. Subject/conflicted-slot selectors and
existing help/venue metadata SHALL remain accessible. Original slot options SHALL
NOT be filtered by replacement-date eligibility.

### Scenarios

- **MA-02a — Expanded facts:** Given a selected original occurrence, collapsed
  context shows its correct subject/date/time; expansion reveals its real extra
  facts and selectors without inventing sample data or changing the selection.
- **MA-02b — Original holiday/past occurrence:** Given a conflicted original slot
  on a holiday or in the past, it remains selectable as the original while such
  dates are hidden from replacement-day navigation.
- **MA-02c — Missing subject/slot:** Given no subject or required original slot,
  current guidance and gates remain effective, controls remain reachable and no
  replacement can be created by the mobile view bypassing those gates.

## MA-03 — Eligible dates, weeks and navigation

The mobile picker SHALL derive eligibility from real `weekData` and the existing
clock/calendar helpers, never fixture dates or room occupancy. Internal day/week
indexes SHALL remain original indexes (weeks 0-based internally, +1 for display).

### Scenarios

- **MA-03a — Calendar boundary:** Given real calendar fixtures, Sundays, holidays,
  today/past and dates before the notice cutoff are absent; the notice boundary
  date itself is present unless independently a holiday/Sunday. Eligible Saturday
  remains visible under the existing rules; do not invent a Saturday ban.
- **MA-03b — Weeks/occupancy:** A week with no eligible date is absent from the
  week select and skipped by arrows. A date/week with eligible dates but no free
  times stays visible and displays its blocked times.
- **MA-03c — Weekday retention:** When changing week, keep the viewed weekday if
  eligible in the target week; otherwise display its first eligible original day
  index. No day/week browsing operation creates a selection automatically.
- **MA-03d — Initial view:** After existing boot/URL/intent resolution, show an
  eligible saved/prefilled selection's day in the current week/venue when present;
  otherwise the first eligible date in the resolved week. Existing original pin,
  auto-selection and navigation defaults are not replaced by prototype defaults.
- **MA-03e — Earliest:** When Earliest bookable is activated, use existing week
  resolution and reveal/highlight its first eligible day, including when already
  in the destination week. No selection is created by this presentation action.
- **MA-03f — Empty semester:** When no eligible dates remain, display exactly
  `No bookable dates remaining this semester.`, hide the timeline, disable day/
  week/arrows/Earliest and Submit, and retain access to original context, tools
  and any saved selection's details/removal. Do not silently erase saved data.

## MA-04 — One canonical availability path and readable timeline

The timeline SHALL show one date with ordered 30-minute rows, existing 24-hour
axis labels and all times, including blocked/pending. It SHALL use resolved
canonical cell state/label/detail text, not independently classify raw payloads.
Missing/unrecognised cells SHALL fail closed. Selected span geometry SHALL come
from saved start/end indexes, not hidden desktop cell dimensions.

### Scenarios

- **MA-04a — Cell parity:** Given venue occupancy and own/cohort busy-vector
  combinations, mobile state, borderless pending/conflict and ownership reason match the canonical
  table's existing classification and precedence for every rendered time.
- **MA-04b — Unknown/holder fallback:** A missing or unknown canonical cell is
  not offered as available. A recognised blocked cell without holder text still
  shows its actual state/date/time/venue and no fabricated holder details.
- **MA-04c — Readability:** The chosen date's times run vertically, without
  wrapping a class span into a button matrix or requiring page-wide horizontal
  timetable scrolling. A blue span displays start/end and venue clearly, with
  no label overlapping adjacent rows; covered half-hours are not duplicated.
- **MA-04d — Data formats:** Axis retains 24-hour time; date/time details follow
  current CRS formats (e.g. `Fri, 20 Nov 2026`, `11:00 AM ~ 1:00 PM`). There is
  no hardcoded preview clock, sample lecturer schedule or fixed eligibility date.

## MA-05 — Whole-class selection and explicit removal

Free rows SHALL keep the same green treatment regardless of full-span fit. One
tap SHALL use existing selection checks for the full original class duration,
budget, pin and intent semantics. Mobile selected-block activation SHALL inspect
details instead of immediately removing it. Desktop behaviour SHALL not change.

### Scenarios

- **MA-05a — Full span:** Given a valid original class and enough continuous free
  time, one tap creates exactly its required span (e.g. two or four half-hours)
  through the existing path and updates the saved store/bar once.
- **MA-05b — Gap/day end:** Given a green start whose required span overlaps a
  blocked time or exceeds day end, the same checks reject it; transient feedback
  appears, no optimistic blue span/store entry appears, and other state is kept.
- **MA-05c — Existing choice:** Given any saved entry, including another week,
  tapping another green time does not create/replace/clear it. Show five-second
  guidance to inspect/remove the current selection first.
- **MA-05d — Inspect then remove:** Activating the mobile blue block opens its
  date/time/duration/venue details without mutation. Only explicit Remove clears
  that exact venue/week entry through `removeSavedBlock()`, preserving existing
  intent-consumption semantics and refreshing summary/timeline.
- **MA-05e — Off-view removal:** Given a selection saved in another week/day,
  open its bottom summary and remove it; only its keyed entry is removed. No
  hidden current-week pointer is mistaken for it and it does not reappear later.

## MA-06 — Persistent summary and actions

The bottom bar SHALL remain visible, reserve sufficient page space and derive its
summary from all saved entries, not only the viewed pointer. Without selection,
show `Select a time` and disabled Submit. With selection, show compact date/time
and details access; Submit SHALL satisfy existing and mobile-expiry/busy gates.

### Scenarios

- **MA-06a — Persistent action:** Scrolling from context to the end of the day
  leaves the bar usable. Empty bar gives direction; after valid selection it
  reflects real date/time/venue/duration, with full details one activation away.
- **MA-06b — Off-view summary:** Browsing a different day/week does not hide or
  replace the saved summary or change the submitted original/requested identity.
- **MA-06c — Defensive legacy store:** If more than one saved entry already
  exists, show a count and all entries with individually keyed removal in details;
  do not silently discard/select the first. This does not permit new multi-booking.

## MA-07 — Touch-accessible information sheets

Conflict/pending rows SHALL open persistent bottom sheets with the existing
authorised reason/holder information. Colours & borders SHALL open four legend
meanings; Tip SHALL open state-aware guidance beside that legend opener above the
timeline. Use text/escaped content, not executable holder HTML.

### Scenarios

- **MA-07a — Blocked information:** Tapping a conflict/pending row opens its
  correct state/date/time/venue and existing reason/holder text. It creates no
  booking and reveals no additional data beyond the current payload permissions.
- **MA-07b — Legend:** The sheet contains Available, Your Current Selection,
  Pending and Conflict, with borderless Pending/Conflict samples. All actual
  Pending/Conflict rows, including yours, stay borderless. No obsolete
  ownership-border hint is displayed. Existing state wording and tapped details
  explain yours/others and conflict reasons. Blue selection keeps its existing
  outline; canonical ownership data/classes are preserved, not painted as rings.
- **MA-07c — Tip:** Tip sits beside Colours & borders, opens the appropriate
  existing empty/selected/MAX guidance, and does not also show the shared floating
  tooltip on mobile hover/focus. Desktop restores its current correct tooltip.
- **MA-07d — Safe text:** Multiline holder text remains readable; literal markup
  in input is displayed safely and cannot execute or create unintended controls.
- **MA-07e — Persistent details:** Information stays open beyond five seconds
  until Close/Escape or an allowed information-backdrop dismissal. Opening an
  existing critical confirmation closes the information sheet first; callbacks
  and cancel semantics stay with the existing confirmation.

## MA-08 — Feedback lifetime

Transient selection warnings SHALL disappear after 5000ms using existing toast
mechanics, without altering desktop timing/copy. Details, confirmation and
critical request outcome/error information SHALL NOT auto-dismiss.

### Scenarios

- **MA-08a — Timed warning:** A mobile selection-fit/already-selected warning
  appears immediately, remains before its five-second expiry and disappears at
  expiry. A newer warning replaces/restarts the timer without stale timer removal.
- **MA-08b — Critical information:** A server conflict, uncertain transport
  outcome or invalidated confirmation remains readable until explicit dismissal/
  recovery and does not erase the saved selection or auto-retry the request.

## MA-09 — Preserve existing change guards and occurrence identity

Subject/original-slot/venue changes, navigation/leave guards, original pinning,
URL/intent resolution, existing availability filters and server/OCC checks SHALL
remain on their current paths. Mobile presentation SHALL not optimistically commit
a change still awaiting confirmation or bypass a restore-on-cancel callback.

### Scenarios

- **MA-09a — Change confirmation:** With a saved selection, subject, original
  slot and venue change each use their current confirmation; Cancel restores
  committed control/context values and selection. Confirm performs the current
  explicit discard/change and resulting default/week/venue resolution once.
- **MA-09b — Arrival/leave:** Existing plain arrival, original-session URL,
  booking-intent prefill/consumption and protected page-leave behaviours retain
  their original identity and gates. Mobile does not invent occurrence pins.

## MA-10 — Clock expiry and saved-selection preservation

Mobile SHALL reconcile the real calendar on local date boundary, visibility/focus
return and immediately before selection/Submit. It SHALL rebuild canonical states
and eligible week options, not merely select an existing stale option. Retained
expired entries SHALL remain inspectable/removable and not be silently overwritten
by `saveCurrentWeek()` when their visual pointer becomes null.

### Scenarios

- **MA-10a — Expiry refresh:** When the calendar advances, newly ineligible
  dates/weeks disappear. Keep the current week if eligible; otherwise resolve
  forward then earliest. The final expired option leads to MA-03f, not a fallback
  exposing an unbookable date. Do not fire synthetic control-change commands.
- **MA-10b — Retained identity:** Given a saved selection whose date expires,
  canonical rebuild makes its cells blocked/too-soon, the current pointer may be
  null, but its saved identity stays intact through same-week rebuild, navigation
  away/back and resize into desktop. Submit stays disabled while it is expired,
  even if later eligible dates exist, with clear remove-and-choose guidance.
- **MA-10c — Explicit removal:** Removing that retained entry, confirming a
  clearing change or successfully submitting clears its retention metadata too.
  No stale marker resurrects it; removal does not paint expired cells green.
- **MA-10d — Open sheet expiry:** Expiry does not auto-close readable details.
  Stale selection/Submit actions cannot bypass new eligibility. If eligibility
  returns, the marker clears only once canonical loading restores the same entry;
  undo still respects existing availability/date gates.

## MA-11 — Mobile-origin Submit lifecycle

Mobile submission SHALL use the existing confirmation/Livewire/server path with a
page-owned request latch and presented-identity snapshot. The final Yes callback,
not just the opener, SHALL validate current date, saved-entry identity and original
pin before sending. Protection for a mobile-origin flow survives resizing.

### Scenarios

- **MA-11a — Confirm boundary:** Submit opens existing confirmation without a
  network call. If the date expires or original/saved identity changes before
  Yes, no `submitBlocks` call is made; saved data remains and persistent review
  guidance appears. Unchanged valid Yes sends the exact presented identity once.
- **MA-11b — Duplicate suppression:** Once sending, set busy before the call;
  repeated Submit/Yes, counter refresh, canonical rebuild and rotation do not
  re-enable the command or issue duplicate mobile-origin requests.
- **MA-11c — Failure release:** Business rejection or promise/synchronous transport
  error releases the latch, reapplies current eligibility, retains selection and
  shows existing/persistent recovery information. An uncertain transport result
  is not claimed as a confirmed failed booking and never blindly auto-retries.
- **MA-11d — Success/desktop:** Full success keeps Submit disabled through existing
  cleanup/redirect. A normally desktop-origin flow retains existing desktop
  behaviour; resizing a protected mobile-origin request does not drop its latch.

## MA-12 — Native keyboard actions, modal focus and hidden-grid isolation

The mobile adapter SHALL prevent legacy document grid shortcuts acting on hidden
cells. Native button/select keys SHALL remain usable without global prevention.
Background grid commands SHALL not run behind information sheets. Critical-modal
Escape remains functional; normal desktop keyboard handling remains unchanged.

### Scenarios

- **MA-12a — Native activation:** Enter/Space activates mobile day/slot/tools/
  summary/Close controls as appropriate, once. Arrow keys retain native select/
  scroll behaviour; legacy hidden-grid focus cannot select another cell.
- **MA-12b — Background isolation:** Bracket, undo and grid shortcuts while a
  sheet is open cannot navigate/change hidden booking state, including during
  breakpoint transition. Critical modal Escape retains its existing close path.
- **MA-12c — Sheet access:** Each sheet has a named heading, focus enters it,
  Tab stays inside, Escape/visible Close dismisses it and focus returns to the
  surviving opener or corresponding rebuilt control/current-day/summary fallback.
- **MA-12d — Screen reader:** Day selection is announced, slot buttons identify
  state/date/time, selection changes have one polite announcement, and inactive
  layouts/refreshes do not duplicate controls or produce repeated announcements.
- **MA-12e — Mobile shortcuts without a sheet:** With mobile active and no sheet
  open, legacy document-level grid, undo, help, week and venue shortcuts do not
  execute. Native button/select keys remain usable without global prevention;
  existing critical-modal Escape handling remains available when applicable.

## MA-13 — Geometry, themes and safe areas

Every mobile interactive target SHALL be >=44×44px. Sticky controls and fixed
actions SHALL leave measured safe space and respect safe-area insets. Sheets SHALL
fit within 85dvh, scroll internally and keep heading/Close usable. CRS tokens,
light/dark themes and reduced-motion settings SHALL remain supported.

### Scenarios

- **MA-13a — Small screens/zoom:** At 320px, 390px and 768px, and enlarged text,
  all controls meet touch size, long values wrap/read without losing facts, and
  timeline/footer have no page-wide horizontal overflow. The day picker may
  scroll inside its own bounded container only.
- **MA-13b — Overlays:** First/last time rows, focused rows, toast, fixed-bar
  actions and confirmation actions are not obscured by the header, sticky strip,
  safe areas or fixed bottom bar. The bar may wrap as needed; measurements update
  after size/text changes rather than relying on a guessed fixed footer height.
- **MA-13c — Themes/motion:** Both current themes use shared tokens with readable
  state text/borders/focus. Reduced motion is respected; no meaning depends on
  animation. Existing viewport-fit=cover is reused, not a global layout rewrite.

## MA-14 — Lifecycle, refresh and explicit target reveal

Refresh SHALL be coalesced/idempotent with no booking mutations or duplicate
listeners. Explicit target revelation SHALL act on visible eligible mobile rows,
using existing navigation and flash helpers, including same-week targets.

### Scenarios

- **MA-14a — Refresh ordering:** Boot hooks before controller creation are safe;
  first render sees resolved URL/intent data. Subsequent refresh reads canonical
  cells after saved selection restoration; it does not recursively rebuild or
  reset an open confirmation/focus.
- **MA-14b — Exact target:** After existing intent/summary/Earliest navigation
  commits, reveal its exact eligible current-week/venue day/time and flash a
  visible row with existing mechanic. Same-week target works without rebuild;
  mismatched/ineligible target neither creates selection nor silently changes venue.
- **MA-14c — Teardown:** Repeated page visits/resize leave one live controller;
  owned timers/observers/listeners are cleaned up, actual controls are restored
  on desktop and booking state is unchanged solely by layout transitions.

## MA-15 — Scope, review and verification discipline

The lane SHALL introduce no DB/schema/booking-engine rule changes, dependencies,
sample production data, desktop redesign, other-page behaviour changes or edits
to the approved prototype. Unrelated working-tree edits SHALL remain untouched.

### Scenarios

- **MA-15a — Diff boundaries:** Implementation diff is limited to approved page/
  adapter/partial/tests/changelog integration; any genuinely necessary shared
  helper change is isolated and reviewed before declaring it safe. Frozen
  artifacts are not silently altered to fit code.
- **MA-15b — Honest receipts:** Static/Node checks do not count as browser visual
  or accessibility verification. Playwright remains paused unless explicitly
  authorised; writing browser receipts use only guarded disposable testing DB/
  port. A skipped or baseline-failing test is reported honestly, not claimed green.
- **MA-15c — Review progression:** Specs pass independent review before freeze;
  tasks follow in their own reviewed batch. Implementation starts only with
  explicit user approval and is followed by verification against these scenarios.
