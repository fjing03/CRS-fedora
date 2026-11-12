# Review log — arrangement-mobile-day-view

## Batch 1 Round 1 — 2026-11-11

Scope: proposal.md only. Frozen artifacts: none.
Baseline: explore-brief.md, approved prototype and relevant live source.
Reviewer: independent sdd-reviewer; parent records the returned findings because
the reviewer operates read-only.

### 🔴 Fixed
- None; no blocking issues were found.

### 🟡 Addressed
- Reviewer checked prototype completeness, desktop isolation, booking guards,
  saved-selection behaviour, accessibility and fixture/production boundaries.
- Following review, proposal explicitly states 30-minute timeline rows and
  >=44×44px targets for all mobile controls, not just timeline rows. Both were
  already approved in the baseline; these are clarifications, not new decisions.

### 🔴 Outstanding
None.

Reviewer verdict: PASS for Batch 1. Clarified proposal submitted for a follow-up
check before recording its final freeze. No implementation permission.

## Batch 1 Round 2 — 2026-11-11

Scope: proposal.md only. Frozen artifacts at review: none.
Reviewer operated read-only; parent records returned findings.

### 🔴 Fixed
- None required; no blocking issues in either round.

### 🟡 Addressed
- Confirmed explicit 30-minute timeline rows.
- Confirmed >=44×44px targets for all mobile interactive controls.
- Both clarifications match explore-brief.md; no scope drift found.

### 🔴 Outstanding
None.

Verdict: PASS. Batch 1 proposal is frozen following this review.
No implementation permission or advancement to another batch.

## Batch 2 Round 1 — 2026-11-11

Scope: design.md only. Frozen: proposal.md. Baseline: explore-brief.md.
Independent reviewer operated read-only; parent records returned findings.

### 🔴 Fixed
- Design now specifies mobile keyboard ownership: bypass legacy document grid
  shortcuts while retaining critical-modal Escape/native button behaviour.
- Design adds page-owned retained-entry metadata and guarded save/load clock
  reconciliation to prevent null visual pointers erasing expired selections.
- Design corrects the false assumption of an existing submit busy latch and
  defines a mobile-origin request latch plus final Yes-boundary date/identity
  validation, failure release and resize lifetime.

### 🟡 Addressed
- Assigned live calendar refresh ownership of rebuilding eligible week options.
- Added exact revealTarget port/hook for same-week and explicit target flashes.

### 🔴 Outstanding
- Round 1 found three blockers; the fixes above await independent re-review.

Reviewer verdict on pre-fix draft: NOT READY TO FREEZE.
Only design.md changed; frozen proposal and brief were not changed.

## Batch 2 Round 2 — 2026-11-11

Scope: design.md only. Frozen: proposal.md. Baseline unchanged.
Independent reviewer operated read-only; parent records returned findings.

### 🔴 Fixed
- Confirmed all three Round 1 blockers resolved: keyboard ownership, expiry
  save/store preservation and final-boundary mobile submit lifecycle.

### 🟡 Addressed
- Confirmed calendar-owned eligible week-option rebuilding.
- Confirmed visible target reveal/flash port, including same-week navigation.
- No new substantive contradiction with frozen proposal, baseline or source.

### 🔴 Outstanding
None.

Reviewer verdict: PASS. Batch 2 design.md is frozen following this review.
This is artifact review only; no implementation or browser execution permission.

## Batch 3 Round 1 — 2026-11-11

Scope: specs/mobile-arrangement/spec.md only. Frozen: proposal.md, design.md.
Baseline: explore-brief.md. Independent reviewer operated read-only; parent
records returned findings.

### 🔴 Fixed
- None required; no blocking issues found.

### 🟡 Addressed
- Reviewer confirmed canonical projection/indexes, expiry retention, final Yes
  checks/busy lifecycle, desktop isolation, guards and accessibility coverage.
- Added MA-12e to explicitly cover all legacy mobile document shortcuts with no
  sheet open, matching frozen design §12.1. Native control keys remain usable.

### 🔴 Outstanding
None.

Reviewer verdict: PASS, with non-blocking keyboard clarification recommended.
Clarification submitted for independent follow-up before final freeze.
No system changes, tests or browser execution.

## Batch 3 Round 2 — 2026-11-11

Scope: specifications only. Frozen: proposal.md, design.md.
Independent reviewer operated read-only; parent records returned findings.

### 🔴 Fixed
- None required; no blocking issues in either round.

### 🟡 Addressed
- Confirmed MA-12e resolves the keyboard recommendation and matches design
  §12.1, including native control keys and critical-modal Escape.

### 🔴 Outstanding
None.

Reviewer verdict: PASS. Batch 3 specs/mobile-arrangement/spec.md is frozen.
No implementation, test/browser execution or tasks performed in this batch.

## Batch 4 Round 1 — 2026-11-11

Scope: tasks.md only. Frozen: proposal.md, design.md and capability specification.
Independent reviewer operated read-only; parent records returned findings.

### 🔴 Fixed
- None required; no blocking issues found.

### 🟡 Addressed
- T03 now waits for T02's static pre-change inventory; browser baseline remains
  separately permission-gated in T25.
- Split dense T21–T23 authoring groups into <=90-minute lettered tasks while
  retaining full scenario coverage, guard requirements and serial file editing.

### 🔴 Outstanding
None.

Reviewer verdict: PASS with two non-blocking recommendations. Clarifications
submitted for independent follow-up before final freeze. No code/test execution.

## Batch 4 Round 2 — 2026-11-11

Scope: tasks.md only; frozen baseline unchanged.
Independent reviewer operated read-only; parent records returned findings.

### 🔴 Fixed
- None required; no blocking issues in either round.

### 🟡 Addressed
- Confirmed static baseline dependency before edits and separately gated browser
  baseline capture.
- Confirmed smaller lettered tasks, serialized editing and complete group
  dependency semantics; no scenarios or safeguards lost from MA-01–MA-15.

### 🔴 Outstanding
None.

Reviewer verdict: PASS. Batch 4 tasks.md is frozen. All four proposal batches
have passed independent review. Implementation and browser execution still need
their respective explicit user approvals. No system code/test execution changes.

## Apply T01 — approval and working-tree baseline — 2026-11-11 17:00 +08:00

Scope: T01 only, delegated by the parent session. The delegation explicitly
reports user approval for SDD Apply in this lane. Batch 4 Round 2 above records
the tasks freeze. This receipt does not grant browser permission, mark any task
checkbox, or authorise work beyond T01.

### Baseline inspected

- Branch: `fedora-backend`, two commits ahead of the locally recorded
  `origin/fedora-backend` (no fetch/push performed).
- HEAD: `28f9f4d1e1141cf2150d8a6fa8616af80bd9f07f`
  (`ui(ui): borderless owned slots + a conflict badge that names only the kind`).
  Previous commit: `adeb3f921437e812f567be2df0ca90f63f08a4a0`.
- Read root/maintained agent rules, CodingMAIN in full, quick rules, handover,
  requirements, demo-login/venue references, user task-list restrictions, SDD
  workflow, and all lane artifacts including the explore brief and review log.
- `git status --short --branch --untracked-files=all`: the only tracked dirty
  file is `docs/demo-db-data-changes.md`. Its existing diff is 47 inserted lines
  (module-title/read-flag receipts plus migration/backfill log rows); it is
  unrelated, left unchanged and unstaged. `git diff --cached --name-status` is
  empty.
- Untracked: all seven files in this lane; the three files in
  `.sdd/changes/bulk-cancel-class/`; `docs/Modal inconsistent Report checklist.md`;
  `prompts/for-ai-to-read/my to-do list.md`; and
  `prompts/replacement-arrangement-top-zone.md`. All remain in place; only this
  review log is appended by T01.
- No current uncommitted page/shared-code/test overlap was detected. A focused
  `git diff HEAD --` for the arrangement Blade, ui-common.js, theme.css,
  arrangement-slot-ownership.spec.ts and arrangement page changelog is empty.
  Earlier shared-source edits are now committed, not working-tree hunks.
- Current Blade anchors remain: DOMContentLoaded 1194; saved store 1249;
  WeekNavigator 1250; save/load 1269/1274; availability/full-span selection
  1654/1660/1669; classifyState/buildTimetable 1767/1819; proceed 2300;
  keyboard 2747; original summary 2938; pulse/flash 3380/3394; keyed removal
  3561. These are inspection anchors, not a T02 regression inventory.

### Blocking committed-source / frozen-artifact conflict

HEAD changes this lane's baseline semantics: `public/css/theme.css:2791,2808`
removes the own-pending/own-conflict 3px borders; the arrangement Blade legend
at approximately 1039–1058 drops the ownership hint and uses borderless Pending
and Conflict swatches. The committed page changelog records this as a new user
ruling, and `tests/Feature/SlotBorderAndReasonBadgeTest.php:41–76` explicitly
pins borderless cells and absence of the old ownership hint. These are inspected
source assertions, not tests executed by this agent.

Frozen proposal §5, design §7 and MA-07b still require thick own-state samples
and the exact `Thick border (3px) = Yours · No border = Others' (Cohorts or Venue)`
hint. That conflicts with the new committed rule and with projecting canonical
states unchanged. This is a decision-level mismatch, not merely the old six-row
legend assertion drift anticipated by T02. The maintained colour/ownership rules
also still describe the earlier border language; no policy file is changed here.

**Result: STOP / hand back to parent before further Apply work.** No dirty-code
collision exists, but the committed overlapping page/CSS change needs an explicit
baseline decision and the required SDD unfreeze/re-review if frozen decisions
change. Do not silently restore borders or alter frozen acceptance to fit HEAD.

### Execution limits / receipt

Only this append is written. No source, tests, frozen artifacts, prototype,
task-list status or checkbox changed; nothing staged, committed or pushed.
No browser, app/server command, test execution or DB access/write performed.
T01 approval/baseline evidence covers MA-15a/MA-15c only; it is not implementation,
browser, accessibility or verification-pass evidence. Parent verifies disposition.

## Baseline amendment / full-chain unfreeze — 2026-11-11

User explicitly chose option 1: mobile follows the latest borderless design from
`28f9f4d`. Pending/conflict ownership rings and the obsolete border hint must not
be restored; blue current-selection outline remains. Ownership stays in existing
state wording and tap-open details. Approved prototype file remains untouched;
the amended artifacts override its old border styling.

Unfreeze proposal.md and all downstream design/spec/tasks for this decision-level
change. Re-review in original batch order; only each current batch is edited.
Apply remains paused until the full chain passes. No system code changed.
T01 baseline receipt remains recorded; no checkbox is claimed complete yet.

## Batch 1 amendment Round 1 — 2026-11-11

Scope: amended proposal only, amended Explore baseline. Downstream unfrozen.
Independent read-only reviewer confirmed the borderless rule, no ownership hint,
state/detail wording and retained blue outline match the new user ruling/source.

### 🔴 Fixed
- None required.
### 🟡 Addressed
- Prototype override and paused full-chain re-review are explicit; booking/desktop
  safeguards remain intact.
### 🔴 Outstanding
None.

Verdict: PASS. Amended proposal refrozen. Proceed to amended design review only.

## Batch 2 amendment Round 1 — 2026-11-11

Scope: amended design only; proposal refrozen, specs/tasks remain unfrozen.
Independent read-only reviewer found no stale normative ring-restoration rule.

### 🔴 Fixed
- None required.
### 🟡 Addressed
- Confirmed borderless samples/rows, omitted ownership hint, retained blue outline
  and ownership labels/data; verification baseline matches `28f9f4d`.
### 🔴 Outstanding
None.

Verdict: PASS. Amended design refrozen. Proceed to specification review only.

## Batch 3 amendment Round 1 — 2026-11-11

Scope: amended specification; proposal/design refrozen, tasks remain unfrozen.

### 🔴 Fixed
- None required.
### 🟡 Addressed
- Independent reviewer confirmed MA-04a/MA-07b borderless/no-hint requirements,
  preserved ownership text/data, blue outline and unchanged other safeguards.
- No stale ownership-ring mandate remains in the specification.
### 🔴 Outstanding
None.

Verdict: PASS. Amended specification refrozen. Proceed to tasks review only.

## Batch 4 amendment Round 1 — 2026-11-11

Scope: amended tasks, all upstream artifacts refrozen.

### 🔴 Fixed
- None required.
### 🟡 Addressed
- Independent reviewer confirmed current borderless baseline, T11 no-hint rows/
  swatches, owner wording/details, blue outline and complete scenario/dependency map.
### 🔴 Outstanding
None.

Verdict: PASS. Amended tasks refrozen. Full-chain amendment review complete.
The T01 source-baseline blocker is resolved by explicit user decision and these
four independent passes. Resume Apply under the prior explicit user approval;
browser permission remains absent. T01 baseline/approval task is now complete.

## Apply T02 — static desktop baseline / regression inventory — 2026-11-11

Scope: delegated T02 only, following completed T01 and all four amendment passes.
Read amended brief/proposal/design/spec/tasks and current source/tests. Baseline is
`28f9f4d`; its borderless Pending/Conflict, four legend rows, absent ownership hint
and retained blue selection outline are accepted, **not an outstanding blocker**.
Below, `RA` means `resources/views/livewire/replacement-arrangement.blade.php`;
line anchors refer to this pre-implementation source, not measured browser output.

### Controls, order and style contracts

| Desktop host/order | Actual nodes / contract |
|---|---|
| Header → guide → intent → original context | `.top-bar` Back/logo, `.theme-toggle`; shared guide; `#bookingIntent`, `#subjectInfo` (RA:944–988). Back/logo use existing guarded navigation. |
| `.toolbar-primary` | `#subjectSelector.selector-dropdown`, then complete `#slotPicker.slot-dd` containing `#slotTrigger`, `#slotTriggerText`, `#slotPanel`, `#slotTooltip` (RA:989–1003). Picker initially hidden; preserve its panel and outside-click handling. |
| `.toolbar-center` | `#buildingSelectorDropdown.venue-dd` (not a native `#buildingSelector` select), then `#venueChipGroup` with `#venueCapacity`, `#arrVenueDescription` (RA:1004–1018). Keep the same VenueDropdown instance/trigger/panel; recent rooms remain, favourites do not. |
| `.toolbar-filters` | `.week-nav`: previous `.week-arrow`, `#weekSelector.week-select`, next `.week-arrow`; sibling `#earliestBtn.today-btn.today-btn-earliest` (RA:1019–1021; partials/ui-week-nav:18–25, ui-today-btn:14–17). No Today/Print action on this page. |
| Grid → legend → summary | Shared grid `#timetable`/`#tableBody` inside `.grid-scroll`; four `.legend-item` rows in order Available / Your Current Selection / Pending / Conflict; `#selSummary` contains `#summaryEmpty`, `#summaryGrid`, `#summaryInfo` (RA:1037–1107). |
| Summary actions | `.sel-summary-action > #summaryTip` immediately followed by `#submitTip > #submitRequestBtn` (RA:1112–1132). Tip's icon/label survive updates; `setSummaryTip()` changes data-tip/aria only. Clear ALL/progress/summary-card counters are commented out, not new controls to restore. |
| Existing overlays | `#confirmModal`: title/body, header Close, footer outline Close, `#modalConfirmBtn`, optional `#modalActionBtn`; `#helpOverlay` separately (RA:1153–1184). Keep callback/reset behaviour, not merely visible labels. |

- Desktop CSS: fixed header (RA:30), 68px app top padding (:111); subject/slot
  triggers 36px (:115,320), subject max-width 225px (:189), slot max-width 384px
  (:315), venue trigger max-width 216px (:308). Wrapping toolbar stays flex;
  **769–1200px** week controls take a full right-aligned row (:294–301).
  Summary becomes two columns at <=1024px (:745); existing <=900px context rules
  also apply at 769px. Do not compare 769px to wide desktop as if identical.
- Tip is 32px high, 12px/600, help cursor and primary focus outline; action row
  is right-aligned flex/gap 10px (RA:705–743). Preserve desktop sizing, not mobile
  44px overrides. Shared `.cell-content` is absolute inset fill (theme.css:2679);
  changing it globally collapses the grid. `.event-selection` is primary-container
  with 2px primary border (:2597). Pending/reserved stay amber, conflicts red,
  including own classes with **no ownership border** (:2776–2810).
- Canonical state remains RA:1767–1817; `buildTimetable()` (:1819–2082) applies
  Sunday/PH first, then too-soon override except own-pending, keeps existing reason
  tips, restores selection, updates counters/arrows. Real `.cell-state-label`
  spans and authorised plain-text `data-tip`/aria are the projection source; do
  not reclassify the raw tuples or rely on the stale ownership comments.

### Keyboard and integration hazards

- Available cells have tabindex/role/button, focus/hover preview and local
  Enter/Space activation (RA:1826–1837). Selected overlay is focusable and click
  removes it (:1592–1631). Summary cards have role/button/tabindex, Enter/Space
  and click jump; nested `.card-remove` is excluded (:1470–1506).
- Document handler RA:2747–2839: modal Escape closes first; help Escape closes;
  select/input/textarea retain native keys (Escape blurs). Otherwise Up/Down move
  day, Left/Right move half-hour, Enter/Space toggles focused cell, Escape clears
  focus, brackets navigate week, ? opens help, Ctrl/Meta+Z undoes, Ctrl/Meta+1–3
  choose filtered venues. Preserve this desktop path, including existing quirks;
  the local cell key handler bubbles to the document handler (no stopPropagation).
- Boot registers both that handler and `initWeekKeyboardShortcuts()` (RA:4810;
  ui-common.js:2029–2041). The latter also listens to brackets/prevents default,
  independently of page modal/mobile state: consider **both listeners** when
  isolating mobile native keys; page-handler return alone is not the whole story.
  VenueDropdown owns capture-phase Escape/outside-click while open
  (ui-common.js:4211–4255); close panels before moving the original nodes.
- Script is DOMContentLoaded-wrapped; inline handlers require exports at
  RA:4825–4845. Refresh must follow load/counter completion, not intermediate
  half-committed context. Keep source hosts mounted where existing selectors use
  them; inactive canonical grid must lose tab stops without losing its cells.

### Guard, saved-store, arrival and submission contracts

- Store is `selectedSlotsByVenue[venue][week] = {day,startHour,endHour}`; end is
  exclusive, weeks/day indexes are original 0-based indexes. `selectedBlock` is
  only current-view pointer (RA:1249–1299). Week navigation saves before rebuild;
  load restores only if **every** canonical cell is available. Counter, summary,
  submit and leave gates read all saved weeks (:1306–1533), not just pointer.
  Current null-pointer save overwrites that entry; deselect/clearVisual repaint
  green (:1535–1589). These explain the frozen expiry-retention hooks, not a
  reason to duplicate the store. Preview uses visible geometry (:1706–1740),
  so do not run it against the hidden table for mobile.
- `toggleCell` gates subject/too-soon and delegates full-span checks;
  `canPlaceBlock`/`isCellAvailable` read table classes; `selectBlock` checks fit,
  day end and global budget before writing store/history and consuming intent
  (RA:1654–1701,2088–2108). Width/budget follow original occurrence, URL boot
  duration lock and explicit slot/subject-switch rules (:4269–4304,4560–4571).
- Venue changes use pointer **or global total**, confirm discard, restore current
  venue on No, and refill chips on committed rebuild (RA:2126–2178). Subject
  changes use the same store-wide guard, restore last subject/tip on No
  (:3118–3139). Slot changes compare full session/week/day/start/venue identity,
  avoiding same-slot false prompts; No restores prior pick or virtual pinned
  anchor (:4114–4185). Confirmation callbacks and restore values must survive
  relocation; never optimistically treat a pending select value as committed.
- Post-boot subject switch auto-pins/auto-commits earliest conflict, keeps visible
  week if bookable for settled room or uses earliest, silently stays if none
  (RA:3650–3805,3878–3935). `syncVenueFamily` chooses original room when eligible,
  otherwise existing eligible-room fallback; type/capacity/population eligibility
  stays on existing ruleMap consumers (:3823–3870,4232–4266).
- Boot resolves owned PAGE_DATA pin before duration/controls, saved-week/earliest
  policy, URL/intent, real grid, ghost-prefill check and fallback (:4492–4808).
  Pin `{sessionId,week}` uses **1-based original occurrence week**, independent of
  replacement grid week. Pinned arrivals use earliest; combined pin+intent date
  wins; plain arrival may resume saved arrangementWeek. No ?week grid pin.
  `consumeBookingIntent` never overwrites an active different block and rechecks
  real full-span fit (:3577–3648); explicit discard spends matching intent.
- Summary jump uses existing guarded week/venue path and exact flash
  (RA:3547–3559); keyed removal handles off-view null-pointer store entries and
  consumes matching intent (:3561–3574). Leave/Back use store-wide confirmation,
  allowUnload only for confirmed departure; beforeunload protects browser leave;
  bfcache revalidates live pin first, then URL, and clears restored selection/
  history (:2521–2649). Do not replace those paths with mobile navigation.
- `proceed()` summarises all saved entries; Yes builds blocks, requires pin,
  chooses original slot/URL facts and calls `pageLivewire().call('submitBlocks',
  blocksJson, code, origJson, pinJson)` (RA:2300–2352). Current desktop has no
  busy latch or transport catch; add only frozen mobile-origin safeguards.
  Business conflict preserves selection and offers explicit Refresh without
  retry; success clears store/history and immediately redirects to
  `/my-replacements?new-req=…` (:2353–2485). Backend renderless submit preserves
  client DOM, ownership/pin/original-week/span/holiday validation and positional
  results (app/Livewire/ReplacementArrangement.php:1053–1202); action/engine/OCC
  remain authoritative. No backend change belongs to this adapter.

### Existing evidence limits — not new lane regressions

- **No tests were run here.** Handover's last full PHPUnit receipt is dated
  2026-11-10: 528 green + one stale ReplacementHistoriesTest payload-shape error
  (529 total). It predates `28f9f4d`; it is not a fresh green baseline. Weekend
  day-6 fixtures, clock-relative bookability and Fortify 5/min login limits are
  known test constraints (handover §3/§5), not grounds to weaken rules.
- Current `SlotBorderAndReasonBadgeTest.php:41–76` and
  `arrangement-slot-ownership.spec.ts:554–603` already assert four-row/no-hint/
  borderless states; ownership spec still checks ownership words/precedence.
  Old six-row/3px receipts/comments are superseded, not failing product rules.
  Run ownership fixtures separately later: week-filter-preserved reseeds requests.
- Concrete stale control assumptions still present: `tests/confirm-guards.spec.ts:166`
  and `tests/ui-regression.spec.ts:283` click absent `#clearAllBtn`;
  `tests/clear-guards-cross-week.spec.ts:120` also reads its absent disabled state
  in a null-safe helper (that read alone is not a failing assertion).
  Source RA:1135–1148 deliberately comments that control out. Record/report such
  failures separately; do not restore Clear ALL or silently rewrite old tests.
- `submission-conflict-refresh.spec.ts` T3d has no recorded green receipt
  (handover §5: login redirect/absent weekSelector during staged arrival);
  `venue-book.spec.ts` U2a-T3 has a reported pre-existing hover race. Neither was
  reproduced here. Fixed fixture weeks such as submission-conflict-refresh:36
  also age with the live cutoff. Existing screenshots/counts are historical.

### Candidate desktop comparisons (T25/T27, permission-gated)

Capture **before/after the same baseline/data/clock/theme**, at 769px plus 1024,
1200/1201 and 1440px (light/dark). For each record screenshot, node parent/sibling
order/identity, values, active pin, saved summary and relevant computed styles:

1. Plain empty/no-subject and owned pinned arrival: header/context/toolbar wrap,
   full week labels, eligible arrows/Earliest, empty summary/disabled Submit.
2. 1h and 2h selected spans: exact colspan/hidden cells, blue 2px outline, preview,
   summary's four icon rows, Tip empty/selected/MAX text, disabled/hover/focus skins;
   desktop click removal, card jump and keyed off-week removal unchanged.
3. Own/other pending, own/cohort/venue overlap, holderless blocked, PH and cutoff:
   canonical classes/labels/aria/tip text and placement; amber/red **0px ownership
   borders**, four legend rows/no hint; do not lose reasons beneath too-soon paint.
4. Saved off-week change Subject/Slot/Venue: No restores committed context/store;
   Yes discards once; same-slot identity causes no prompt; week policy/original-room
   sync/type-capacity filtering remain identical. Compare exact submitted args in
   controlled transport, success redirect and persistent conflict/Refresh behaviour.
5. Pin-only, intent-only, combined intent+pin, consumed intent reload and guarded
   Back/leave/bfcache; explicit same-week summary/Earliest flash stays functional.
6. Keyboard/native selects, grid arrows/Enter/Space, summary keyboard jump, help,
   modal Escape, undo and venue/week shortcuts. At 769→768→769 with open dropdown,
   saved block or open confirmation: exact nodes return to original hosts, panels
   close, current Tip restores, no duplicate IDs/listeners, data mutation or resize
   confirmation. Compare another timetable page for isolation, not redesign.

**Receipt:** T02 static inventory recorded (MA-01b, MA-09a–b, MA-15b); parent
verifies and marks its checkbox. Browser baseline capture remains **blocked by
absent permission**, tracked separately in T25; no visual/keyboard/accessibility
pass is claimed. Only this log append is written. No source/test/prototype edits,
browser/test/DB/server commands, staging, commit or push; existing unrelated
`docs/demo-db-data-changes.md` and other untracked work remain untouched.

## Apply T03 — inert mobile markup/controller shell — 2026-11-11

Delegated T03 only, under the parent's reported user Apply approval. Read the
maintained root rules, amended frozen artifacts and T01/T02 baseline receipts.
The accepted `28f9f4d` borderless Pending/Conflict/no-hint rule is unchanged;
no legend/state painting is implemented by this shell.

- Added `resources/views/partials/ui-arrangement-mobile.blade.php`: hidden/inert
  root, semantic original-context/facts/subject/slot hosts, venue/week/day controls,
  tools/Earliest/Tip hosts, day heading/timeline/empty host, summary/action hosts
  and one named native information dialog. New buttons explicitly use type=button.
- Added `public/js/arrangement-mobile.js`: the single `ArrangementMobile.create`
  factory accepts root/closure ports and returns mount/refresh/destroy. Refresh
  before mount/after destroy and repeated lifecycle calls are safe no-ops. This
  shell intentionally consumes no snapshots, binds no handlers/listeners, moves
  no controls, activates neither layout and owns no booking state/global instance.
- Page Blade includes the partial and cache-busted asset. Its controller remains
  closure-owned; the null-safe refresh bridge is ready for T05. Mount occurs at
  the closure tail, after URL/intent resolution, canonical-grid/ghost checks,
  fallback prefill and existing inline-handler exports. Desktop remains untouched;
  the shell also stays inactive on mobile pending later tasks.
- T13 follow-up: suppress **both** handleKeyDown and the separately registered
  initWeekKeyboardShortcuts listener on mobile; T03 deliberately changes neither.

Checks actually run: `node --check public/js/arrangement-mobile.js`; extracted
page-scripts section checked through Node --check stdin; `php artisan view:cache`
then `php artisan view:clear`; `git diff --check` — all passed. A dependency-free
Node VM/stub smoke also passed early refresh, repeated mount/destroy, null root,
post-destroy calls and no snapshot reads/visibility activation. These are static/
stub checks, **not** browser, desktop visual, accessibility or device receipts.

No frozen artifact/checklist/prototype/shared/backend/other-session edits; no
browser, DB access/write, server restart, staging, commit or push. Page changelog,
full PHP quality gates and sanctioned server restart remain with parent T24/T29.
Only the two new shell files, page integration and this receipt are task edits.
Final status also shows a new concurrent dirty `replacement-home.blade.php`;
it was not edited, reverted or staged by T03 and is outside this receipt.

## Apply T03 — re-verification, no source delta — 2026-11-11

Delegated T03 re-run after T01/T02. The T03 shell from the receipt above is
already present in the working tree (`public/js/arrangement-mobile.js` and
`resources/views/partials/ui-arrangement-mobile.blade.php`, both untracked, plus
the page integration already in the arrangement Blade diff). Re-creating it
would duplicate reviewed work, so this run reviewed the existing shell against
the frozen T03 contract instead and changed **no source**. The only write is
this append.

### Verified anchors (current working tree)

- `resources/views/partials/ui-arrangement-mobile.blade.php:2` — page-local
  `<section id="arrangementMobile">`, `hidden` + `inert` by default; hosts for
  original context (`:3–8`), controls (`:10–14`), tools (`:16–20`), day
  heading/timeline/empty (`:22–26`), summary/action (`:28–31`), and one native
  `<dialog id="arrangementMobileInfo">` named by `aria-labelledby` (`:33–40`).
  Both buttons (`:18` legend, `:36` Close) are explicitly `type="button"`.
- `public/js/arrangement-mobile.js:5–39` — one `window.ArrangementMobile.create`
  factory; returns only `mount`/`refresh`/`destroy`. `refresh` is a no-op before
  mount and after destroy; `mount`/`destroy` are idempotent; no booking store,
  snapshot read, network call, listener, timer, observer or global instance.
  Desktop stays hidden/inert because activation belongs to T06.
- `resources/views/livewire/replacement-arrangement.blade.php:1153` — partial
  include; `:1188` cache-busted script include; `:1199–1203` closure-owned
  null-safe `refreshArrangementMobile(reason)` bridge (no-op until creation);
  `:4856–4861` single `create` + `mount` at the closure tail, after URL/intent,
  canonical-grid/ghost checks, fallback prefill and inline-handler exports.

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Dependency-free Node VM smoke (throwaway file in `/tmp/opencode`): namespace
  shape, null-root and early-refresh no-ops, repeated mount/destroy, no remount
  after destroy, destroy closes an open dialog once, no snapshot reads — pass.
- Extracted the page `page-scripts` section and ran `node --check` on it — pass.
- `php -r` DOMDocument parse of the partial: tag balance OK, 2 buttons / 0 with a
  non-`button` type, exactly 1 dialog, dialog named, root `hidden` + `inert` — pass.
- `php artisan view:cache` then `php artisan view:clear` — both succeeded;
  no DB access and no `--env=testing`.
- `git diff --check` — pass.

These are **not** browser, visual, accessibility or device receipts. No browser,
DB access/write, server restart, staging, commit or push. Frozen artifacts,
task checkboxes, prototype, shared `theme.css`/`ui-common.js`, backend and other
sessions' dirty files were left untouched. T03's checkbox remains for the parent
to mark from the combined receipts.

### Carried forward

T13 must suppress **both** document keyboard owners: the page `handleKeyDown`
(RA:4820) and the separately registered `initWeekKeyboardShortcuts()`
(RA:4821 / `ui-common.js`). T03 intentionally changes neither.

## Apply T04 — read-only canonical snapshot port — 2026-11-11

Delegated T04 only, after completed T01–T03 and the amended frozen artifacts.
This is a snapshot port only: no timeline render, no control relocation, no
refresh hooks, no booking writes, no browser execution.

### Changed anchors

- `resources/views/livewire/replacement-arrangement.blade.php:2960` —
  `resolveOriginalAnchorSlot()` extracted byte-equivalently from
  `renderTitleSummary()` (same live-pin → virtual-anchor precedence) so the
  snapshot reuses the ONE original occurrence choice instead of copying it.
  `renderTitleSummary()` now calls it; its rendered output is unchanged.
- `:4870` — `SNAPSHOT_STATE_CLASSES` whitelist: `cell-available`,
  `cell-selected`, `cell-pending`, `cell-reserved`, `cell-occupied`,
  `cell-occupied--mine`, `cell-sun`, `cell-ph`, `cell-too-soon`.
- `:4878–4903` — page-local read helpers: `snapshotText` (text only, `<br>` →
  newline), `snapshotAttr`, `snapshotShown` (hidden/inert ancestor + computed
  style). No HTML is inserted from any captured value.
- `:4905` — `readArrangementSnapshot()`: pure read of
  `weekNav.currentWeek`/`venueDropdown.getSelected()||currentVenue`, real
  `weekData`, `weekHasBookableSlot`/`isSlotTooSoon`, canonical
  `#tableBody td[data-day][data-hour] .cell-content`, `selectedSlotsByVenue`,
  `selectedBlock`, `courseCtx()`/`resolveOriginalAnchorSlot()` and the existing
  control/Tip nodes. Never mutates the store, table, nodes or network.
- `:5117` — tail `ArrangementMobile.create({ root, ports: { readSnapshot:
  readArrangementSnapshot } })`; mount position unchanged.
- `public/js/arrangement-mobile.js` — stored read-only port, `snapshot()`
  (re-reads, fail-closed on missing port/exception/destroy, no writes),
  `refresh()` still a safe no-op, `destroy()` clears the cached snapshot.
  New frozen `Object.freeze` utils: `chooseEligibleDay(days, previousIndex)`
  (same original index if `eligible === true`, else first eligible, else null),
  `savedSpan(entry)` (validated end-exclusive span) and `savedEntries(store,
  weekData)` (plain, sorted, keyed `venue::week`, store untouched; unrenderable
  weeks keep identity with `dayKnown:false` rather than being dropped).

### Snapshot shape (plain, JSON-cloneable)

`readOnly, source:'canonical-table', weekIndex, weekNumber, venueCode,
weeks:[{index,label,range,rangeShort,eligible,days:[{index,abbr,date,sunday,
holiday,holidayLabel,today,firstBookable,tooSoon,eligible}]}]`, `hours`,
`cells:[{dayIndex,hourIndex,known,stateClasses[],stateLabel,detailText,
ariaText,timeLabel}]`, `cellsComplete`, `selections:{entries[],current,
totalSlots}`, `original:{hasSubject,code,name,type,cohorts,cohortCounts,
studentCount,hasSlot,weekIndex,dayIndex,weekdayName,date,startIndex,endIndex,
startTime,endTime,venue,pin}`, `subjectInfoText`, `controls:{subject,slot,
venue,week}` and `tips:{summary,submit}`.

- Cells: current week/venue only, every original day × `hours` index. A cell
  with no `.cell-content` or no whitelisted state class is `known:false`; no
  availability is inferred. `stateLabel` keeps `<br>` line breaks; `timeLabel`
  keeps the existing 24-hour start/end pair.
- Indexes: weeks 0-based (+1 only in `weekNumber`/labels); days keep original
  `weekData` positions; saved spans keep the store's end-exclusive convention;
  `original.startIndex/endIndex` follow the anchor's end-**inclusive** convention
  and are named/commented to prevent mixing.

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` → `node --check` — pass.
- Dependency-free Node VM test (`/tmp/opencode/t04-smoke.cjs`): eligibility
  choice/fail-closed truthiness, span validation, sorted keyed entries,
  unrenderable week retention, store immutability, missing/erroring port,
  post-destroy reads — pass.
- Stub-DOM test of the real extracted `readArrangementSnapshot()` block
  (`/tmp/opencode/t04-snapshot-smoke.cjs`): 154 cells, missing-cell
  `known:false`, whitelist filtering, `<br>` labels, tip/aria/time text,
  holiday/Sunday/too-soon ineligibility, saved entries and original context,
  JSON-cloneable output, store unchanged — pass.
- `php artisan view:cache` then `view:clear` — pass; `git diff --check` — pass.
  No `--env=testing`, DB access, browser, Playwright, server restart, staging,
  commit or push.

These are static/stub receipts, **not** browser, visual, accessibility or
device evidence. Frozen artifacts, task checkboxes, prototype, `theme.css`,
`ui-common.js`, backend, `replacement-home.blade.php` and other sessions' dirty
files were not edited; only the two T04 files and this append changed.

### Integration hazards carried to later tasks

- While a desktop selection exists, `renderMergedBlock()` writes
  `Clear your selection first — click it to remove` into every free cell's
  `data-tip`; the snapshot copies it verbatim as `detailText`. T09/T11 must not
  surface that desktop-only transient hint as a canonical reason (or must
  replace it with mobile wording) — no filtering was invented in T04.
- `snapshot()` re-reads on every call. T05 should call it once per settled
  refresh (coalesced) rather than per render, and must not treat a null/partial
  snapshot as proof of availability.
- `savedEntries` depends on `window.ArrangementMobile.utils` (asset is loaded
  before the DOMContentLoaded closure and cache-busted by `filemtime`). A stale
  cached module would omit entries; T20 should pin the same utility contract.
- `original.pin.weekIndex` derives from the page's 1-based
  `pinnedOccurrence.week`; it is identity context, not the current grid week.
- `controls.*.visible` currently reflects the desktop layout (T06 has not
  relocated nodes yet); no layout state is inferred from it.

## Apply T05 — coalesced refresh hooks — 2026-11-11

Delegated T05 only, after T01–T04 and the amended frozen artifacts. Only the
refresh hook path is added: no timeline, control relocation, sheets, listeners,
timers, booking writes or later-task work. Baseline remains `28f9f4d`
(borderless Pending/Conflict, no ownership hint, blue selection outline).

### Changed anchors

- `public/js/arrangement-mobile.js:104–206` — controller now owns the single
  scheduler (`queueMicrotask`, `setTimeout(…,0)` fallback at `:119–121`).
  `refresh(reason)` (`:164`) ignores calls before mount/after destroy/during the
  notification turn, keeps the **latest** reason and queues at most one turn;
  `runRefresh()` (`:141`) clears the queue, reads the stored read-only port
  **once**, caches `lastSnapshot` and only then calls an optional
  `ports.onSnapshot(snapshot, reason)` (exceptions contained). `snapshot()`
  (`:176`) returns the cached snapshot while mounted, else re-reads the port
  once; missing/erroring port still fails closed to null. `destroy()`
  (`:190`) clears queue/reason/cache, so a queued run is a no-op. No booking
  port, listener, timer or observer was added; the T04 utils/ports contract is
  unchanged.
- `resources/views/livewire/replacement-arrangement.blade.php` — bridge/comment
  `:1201–1208`; `:1356` end of
  `updateCounter()` (after `updateSelectionSummary`/`updateSelectionProgress`/
  `updateSummaryStats`); `:1429` end of `setSummaryTip()` (only after both
  `data-tip` and `aria-label` are written; the missing-node early return fires
  no hook); `:2099–2103` end of `buildTimetable()` after `loadCurrentWeek()` →
  `updateCounter()` → `weekNav._updateArrows()`; `:3119–3120` end of
  `renderTitleSummary()` after `innerHTML`. Reasons: `counter`, `tip`,
  `timetable`, `original`; mount sends `mount`.
- `:5129–5133` — adapter create/mount position unchanged (still the closure
  tail after URL/intent, real-grid/ghost checks, fallback prefill and
  inline-handler exports), so the single initial refresh is the mount trigger.
  No confirmation callback, no store write and no intermediate half-committed
  point gained a call.

### Coalescing behaviour

One read-only snapshot per settled task turn. Boot/pre-mount hook calls are
no-ops (null bridge); mount queues one turn. Every hook fired inside one
synchronous chain (e.g. `buildTimetable` → `updateCounter` → `setSummaryTip`)
collapses to one port read after the whole chain commits, with the last reason.
A callback calling `refresh` during notification is ignored (recursion guard).

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (3944 lines) → `node --check` — pass.
- `/tmp/opencode/t05-adapter-smoke.cjs` (Node `vm`, stub window/root): pre-mount
  and post-destroy no-ops, exactly one read per coalesced turn, latest reason
  (`original` after counter/timetable/original), cached `snapshot()` with no
  extra read, repeated mount not re-reading, destroy-invalidated queued run,
  callback recursion suppressed, missing/erroring/throwing ports fail closed,
  non-object port dropped, `setTimeout` fallback coalesces, unmounted snapshot
  re-reads — pass.
- `php artisan view:cache` then `view:clear` — pass; `git diff --check` — pass.
  No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push.

These are static/stub receipts, **not** browser, visual, accessibility or device
evidence. Frozen artifacts, task checkboxes, prototype, `theme.css`,
`ui-common.js`, backend and other sessions' dirty files
(`replacement-home.blade.php`, `docs/demo-db-data-changes.md`, other lane dirs)
were not edited; only the adapter, the four Blade hooks plus bridge comment and
this append changed.

### Hazards carried forward

- Cached-first `snapshot()` returns the previous snapshot until the queued turn
  runs; T09/T11 renderers must render from `onSnapshot`/after the turn rather
  than synchronously right after a committed mutation.
- Desktop confirmation callbacks were not touched; while a desktop selection
  exists `renderMergedBlock()` still writes the selection-wipe `data-tip` that
  the snapshot copies into `detailText`. **T09/T11:** filter
  `Clear your selection first — click it to remove` out of canonical reasons
  (not done here).
- Hooks fire on desktop too, but the adapter stays hidden/inert and layout
  activation remains T06; snapshot reading has no desktop side effect.

T05 checkbox remains for the parent to mark; full gates/changelog stay T24/T29.

## Apply T06 — same-node breakpoint relocation/restore — 2026-11-11

Delegated T06 only, after completed T01–T05 and the amended frozen artifacts
(borderless Pending/Conflict, no ownership hint, retained blue selection
outline). This task adds layout activation only: no timeline render, no day
selection, no sheets, no submit guards, no booking writes and no browser run.
Baseline `28f9f4d`; current HEAD in the working tree is `5b0b9ff` (a later
committed UI commit, not touched here).

### Changed anchors

- `public/js/arrangement-mobile.js:102–129` — the plan constants: ONE
  `MOBILE_MEDIA_QUERY = '(max-width: 768px)'`, `MOBILE_BODY_CLASS =
  'arrangement-mobile-mode'`, the nine-entry `RELOCATION_PLAN` (node selector →
  mobile host selector; `#venueChipGroup` shares the venue host after
  `#buildingSelectorDropdown`) and `DESKTOP_CONTAINERS`
  (`.toolbar-restructure`, `.sel-summary`, `.grid-wrapper`, `.legend-bar`).
  These are node-identity references, never copies.
- `:150, 226–233` — optional `ports.closePanels` stored once and called inside
  `try/catch`; no broad DOM clicks.
- `:239–283` `enterMobile()` — guard on `mobileActive`; close panels; capture the
  root's current `hidden`/`inert`; for each plan entry resolve the node in the
  document and the host inside the mobile root (missing node/host is skipped, not
  guessed), record `parent` + `nextSibling`, insert a comment marker before the
  node, then `appendChild` the SAME node into the host; snapshot and set
  `hidden`/`inert` on the four inactive desktop containers; reveal/un-inert the
  root; add the body class. All moves happen before any container is hidden.
- `:290–323` `exitMobile()` — close panels; move each node back before its
  marker (fallback: recorded parent/nextSibling when a marker is gone; marker
  removed); restore each container's exact captured `hidden`/`inert`; restore the
  root's captured state; remove the body class.
- `:325–360` — one media owner: `global.matchMedia` created once at mount, with
  `addEventListener('change')` when available and the legacy
  `addListener`/`removeListener` fallback; `detachMediaListener()` on destroy.
  No matchMedia API means desktop-only (fail closed).
- `:363–381` — `syncLayout()` runs the current mode at mount; `isMobile()` is a
  read-only query for later owners (T13); `mount()` keeps the single read-only
  `refresh('mount')`. `destroy()` now exits mobile, detaches the listener, then
  runs the existing dialog/root teardown.
- `resources/views/livewire/replacement-arrangement.blade.php:939–1020` —
  page-local CSS only: `#arrangementMobile`/`[hidden]` stay `display:none`,
  `body.arrangement-mobile-mode #arrangementMobile:not([hidden])` shows it, and
  inside `@media (max-width: 768px)` the four desktop containers get a defensive
  `display:none` plus simple full-width/spacing rules for the moved originals.
  No token/colour added; no desktop rule changed.
- `:1234` partial include unchanged; `:5210–5221` the tail create now also passes
  `closePanels: function () { closeSlotPanel();
  venueDropdown?._close-if-available }`; `arrangementMobile.mount()` stays the
  last statement after URL/intent, real-grid/ghost checks and fallback prefill.
  No confirmation callback, `proceed()` branch or booking path touched.
- Existing hosts consumed from the frozen T03 partial
  (`ui-arrangement-mobile.blade.php:5,6,7,11,12,17,19,30`): context facts,
  subject, slot, venue, week, Earliest, Tip, Submit.

### Enter / exit behaviour

Enter (mount at ≤768px, or a `change` event to matching): panels close first, the
nine original nodes move into the mobile hosts in the plan order, the four
desktop containers are hidden + inert, the root is revealed/un-inerted and
`body.arrangement-mobile-mode` is added. Exit (>768px or destroy): panels close,
nodes return before their markers (exact desktop parents/order), container and
root `hidden`/`inert` states are restored, and the body class is removed.
Duplicate events while already in a mode are no-ops; `refresh()` never relocates
nodes and never adds a listener; destroy restores then removes the media
listener. IDs, inline `onclick`s, values, the shared `VenueDropdown` instance,
Tip `data-tip` text and the desktop order survive because the elements are never
recreated.

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (lines 1272–5223, 3952 lines) →
  `node --check` — pass.
- `/tmp/opencode/t06-adapter-smoke.cjs` (dependency-free Node `vm` + hand-built
  DOM stub, 64 passing assertions) — pass: one listener after mount; desktop mount moves
  nothing and keeps the root hidden/inert with no body class; enter moves all
  nine nodes to their correct hosts (venue host keeps dropdown-then-chips
  order), leaves restore markers, hides+inerts the four containers, reveals the
  root and adds the body class; a redundant mobile event does not re-close or
  re-move; `refresh()` neither relocates nor adds a listener; exit restores exact
  parents/order (`.toolbar-primary` subject→slot, toolbar zones, `.toolbar-filters`
  week-nav→Earliest, `.toolbar-center` venue→chips, action row Tip→Submit) and
  removes all markers; container/root hidden+inert restored; body class removed;
  re-enter then `destroy()` restores desktop and removes the listener; post-
  destroy events/snapshot are inert; null root is safe; missing `closePanels`
  port still relocates/restores; legacy-only `addListener` mode registers, syncs,
  exits and unregisters.
- Duplicate-id scan across the page + the five involved partials: 47 ids, no
  duplicates. Page-styles brace balance: 190/190. `php artisan view:cache` then
  `php artisan view:clear` — pass. `git diff --check` — pass. No `--env=testing`,
  DB access/write, browser, Playwright, server restart, staging, commit or push.

These are static/stub receipts, **not** browser, visual, accessibility or device
evidence. Nothing here proves 768/769px rendering, safe areas, focus order or the
other-page isolation case.

### Hazards carried forward

- **`#summaryTip` floating tooltip suppression is T11.** It is moved intact with
  its current `data-tip`; the shared hover/focus tooltip will still show on mobile
  until T11's mobile-scoped suppression and desktop restoration.
- **Overlay width while the desktop grid is hidden.** `renderMergedBlock()` sets
  the blue overlay width from `firstTd.offsetWidth` (`RA:1824`). Boot-time
  selections are fine (the table is still visible when mount runs), but a
  selection made while mobile (T10) measures a `display:none` table and would
  leave a zero-width overlay until a rebuild on exit — T10/T19 must force that
  rebuild (T06 writes no booking state).
- **Keyboard ownership is still T13.** The inactive grid/containers are `inert`,
  but the page `handleKeyDown` and the separately registered
  `initWeekKeyboardShortcuts()` listener must both still return on mobile; inert
  alone does not stop document-level shortcuts.
- **The context `<details>` stays collapsed by default**, so the relocated
  subject/slot controls sit inside a closed disclosure until the user opens it;
  T14's compact context/expansion work owns the default-open/height treatment.
- **Any future Livewire render while relocated** would morph the server HTML
  against nodes that are no longer in their original parents. The only current
  call is `#[Renderless] submitBlocks` (and bfcache reload), so there is no
  morph today; T19 should re-check this if a render path is ever added.
- **No real `change` occurs on some desktop emulators/print views**; mode is only
  as good as `matchMedia`, which is the single owner by design (no resize
  listeners were added).

T06 checkbox remains for the parent to mark; page changelog, full PHP gates and
the sanctioned server restart stay T24/T29.


## Apply T07 — eligible-day navigation and empty semester state — 2026-11-11

Delegated T07 only, after completed T01–T06 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership hint, retained blue
selection outline). This task adds day presentation and the empty-semester
state only: no timeline rows (T09), sheets/legend/Tip (T11), selection command
(T10), styling composition (T14) or booking writes. Baseline `28f9f4d`; the
working tree also carries the uncommitted T03–T06 lane and other sessions'
dirty files, none of which were touched.

### Changed anchors

- `public/js/arrangement-mobile.js:131–141` — T07 constants: the four frozen
  hosts (`[data-arrangement-mobile-days]`, `-day-heading`, `-empty`,
  `-timeline`), the exact empty text
  `No bookable dates remaining this semester.` and the hop cap.
- `:177–191` — controller presentation state only: `viewedDayIndex`,
  `lastWeekIndex`, `emptyState`, captured `emptyControlState`, the defensive
  hop guard (`hopFromWeek`, `hopAttempts`) and `hopRefreshRequested` (see
  behaviour). `chooseWeekPort` is stored once; a missing port disables hops
  (fail closed, day rendering still works).
- `:220–227` — `runRefresh()` now calls `render(value, true)` after the single
  port read and before the optional `onSnapshot`. The call is wrapped so a
  presentation failure cannot break the settled turn; `render` never calls
  `refresh` (the T05 recursion guard stays authoritative).
- `:236–244` — after a turn that requested a week hop, the same scheduler
  queues exactly one follow-up read (`refresh('week-hop')`). This is required
  because the page's own refresh call inside `weekNav.selectWeek()` is ignored
  while `notifying` is true.
- `:306–330` — `enterMobile()` ends with `renderFromCache()` (cached snapshot,
  no port read). `:337–343` — `exitMobile()` releases the empty-state control
  disable before restoring nodes, so desktop never inherits it.
- `:374–574` — read-only day logic:
  `savedViewDay()` (current week+venue only, from `selections.current` then
  `selections.entries`, must be eligible), `renderDays()` (real
  `<button type="button">` with original `data-day-index`, `aria-pressed` and
  `abbr, date` text; only `eligible === true` days; host children rebuilt),
  `renderDayHeading()` (viewed day's `abbr, DD Mon YYYY`; cleared when no day),
  `emptyControlElements()`/`applyEmptyControls()`/`releaseEmptyControls()`,
  `setEmptyState()` and `maybeHopWeek()`.
- `:576–606` — `render(snapshot, allowWeekHop)`: initial snapshot prefers an
  eligible saved/prefilled day in the current week+venue, else the first
  eligible day; subsequent snapshots call `chooseEligibleDay(days,
  viewedDayIndex)`, which keeps the original day index when still eligible
  (same week or changed week = weekday retention) and otherwise falls back to
  the first eligible day. Day indexes are never renumbered; no booking store is
  read or written; the venue is never touched.
- `:608–634` — `renderFromCache()` (presentation only, `allowWeekHop = false`)
  and the single delegated `onDayClick()`: validates the original index against
  the cached snapshot's eligibility, sets `viewedDayIndex`, then re-renders the
  buttons/heading from the cache with no port read, no refresh, no week rebuild
  and no booking write.
- `:676–688` — one delegated `click` listener is bound on the mobile root at
  mount and `:692–727` destroy removes it, resets the day/week pointers,
  hop/refresh flags, clears the adapter-rendered buttons/heading and releases
  any empty-state disable.
- `resources/views/livewire/replacement-arrangement.blade.php:5249–5258` — the
  create call now passes `chooseWeek(index)`; it validates the integer and
  calls the existing `weekNav.selectWeek(index)` only (no venue change, no
  direct store mutation).
- `:1018–1046` — minimal page-local T07 CSS inside the existing
  `@media (max-width:768px)` block: wrapping day-button row, button
  border/radius/selected (`aria-pressed="true"`) states using existing
  `theme.css` tokens, and the empty message block. No 44px/sticky geometry
  (T14), no token or shared-file change.

### Day-resolution and empty behaviour

| Snapshot | Resolution |
|---|---|
| First render after mount | eligible saved/prefilled entry for the current week+venue → its day; else first eligible day; else none |
| Same week, viewed day still eligible | unchanged |
| Same week, viewed day no longer eligible | first eligible day |
| Week changed | same original day index when eligible there; else first eligible day |
| Empty day picker | no buttons; heading cleared |
| No week has `eligible` (real week list) | exact message shown, timeline host hidden, `#weekSelector`, every `.week-arrow`, `#earliestBtn` and `#submitRequestBtn` disabled |
| Missing/failed snapshot | no empty claim, no disabled controls, no day buttons (fail closed without misinforming) |
| Empty → eligible again | restores only week select/arrows/Earliest; Submit is left to the page's existing counter/snapshot state |
| Mobile → desktop | full restore of every control the adapter disabled, including Submit |
| Current week has no eligible day, another week does | one `chooseWeek(nextEligibleOrEarliest)` request per source week, capped at three; a still-invalid arriving snapshot does not recurse; never changes venue |

Day taps create no selection and no week change. Empty state never clears or
alters saved entries (the adapter holds no store reference); the summary and
context/tools stay available. Empty-state disabling is mobile-only, so desktop
keeps its current rendered state.

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (lines 1301–5261, 3961 lines) →
  `node --check` — pass.
- `/tmp/opencode/t07-adapter-smoke.cjs` — dependency-free Node `vm` + DOM stub,
  **75 passing assertions**: one read per settled turn; initial first-eligible
  day with Sunday/holiday/too-soon holes skipped (only original indexes
  `[0,3,4,5]` rendered); prefilled saved day chosen initially; same-week
  fallback when the viewed day becomes ineligible; weekday retention across a
  week change and fallback when that weekday is ineligible; day tap updates
  `aria-pressed`/heading with no extra port read and no `chooseWeek`;
  `type="button"`; empty text exact, timeline hidden, no buttons, heading
  cleared, week/arrows/Earliest/Submit disabled; date controls restored and
  Submit untouched on recovery; full restore on mobile exit/destroy; one hop to
  the next eligible week plus exactly one follow-up read showing the moved
  week; repeated invalid snapshots do not loop; desktop never hops, breakpoint
  enter never reads/hops, and empty snapshots on desktop leave desktop controls
  enabled until mobile is entered; a null/failed snapshot shows no false empty
  message and no disabled controls.
- Page-style brace balance to the section end: 196/196. `php artisan view:cache`
  then `view:clear` — pass. `git diff --check` — pass. No `--env=testing`, DB
  access/write, browser, Playwright, server restart, staging, commit or push.

These are static/stub receipts, **not** browser, visual, accessibility or device
evidence. Nothing here proves real layout, tap size, screen-reader output or
midnight expiry behaviour.

### Hazards carried forward

- **Recovery from empty leaves Submit to the page.** If a future path exits
  the empty state without an existing counter/update pass, Submit stays
  disabled until T16's clock reconciliation updates it. This follows the task
  rule deliberately; T16 owns the live recheck.
- **Breakpoint enter does not repair an ineligible week.** `renderFromCache`
  renders day/empty presentation only (`allowWeekHop = false`) so a resize
  never navigates (T06 acceptance). A stale invalid week is repaired on the
  next settled refresh; T16's visibility/focus checks make that reliable.
- **The hop uses existing week navigation.** `weekNav.selectWeek()` fires
  `onBeforeNavigate` → `saveCurrentWeek()`, which can write the existing null
  pointer. Saved-entry retention is T15's scope; T07 writes no selection.
- **Timeline host is hidden only via the `hidden` property.** T09 must not
  give that host a `display` rule that beats the UA `[hidden]` state while the
  semester is empty.
- **Day buttons become interactive by `onDayClick` alone.** T13 must keep that
  delegated listener reachable while blocking legacy document-level shortcuts;
  T10 must not add a second selection path on top of it.
- **No announcement yet.** Day changes update `aria-pressed` and the heading
  only; the single polite announcement is T13.
- **Day text is one `abbr, date` string.** T14 owns wrapping, 44px touch
  targets and the sticky day cluster; T07 adds no geometry beyond the small
  page-local button/empty block.
- The empty message is written even while the shell is hidden on desktop (no
  desktop effect); it is exposed only while mobile is active.

T07 checkbox remains for the parent to mark; page changelog, full PHP gates and
the sanctioned server restart stay T24/T29.


## Apply T08 — explicit visible target reveal and Earliest integration — 2026-11-11

Delegated T08 only, after completed T01–T07 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership hint, retained blue
selection outline). This task adds the presentation-only reveal port and wires
the existing feedback/navigation hooks to it: no timeline renderer (T09), no
selection command (T10), no sheets/legend/Tip (T11), no responsive styling
composition (T14) and no booking writes. Baseline `28f9f4d`; the working tree
also carries the uncommitted T03–T07 lane and other sessions' dirty files, none
of which were touched.

### Changed anchors

- `public/js/arrangement-mobile.js:143–152` — T08 constants: `PULSE_CLASS =
  'attention-pulse'` (the page's existing page-local class), `PULSE_MS = 1300`
  (same cleanup duration as the page's own attention ring) and
  `HOUR_ROW_ATTRIBUTE = 'data-mobile-hour'` (the T09 timeline-row hook).
- `:204–209` — controller presentation state only: `pendingReveal` (one target,
  original indexes), `pulseTimer` and `pulseTargetElement` (this adapter's own
  pulse cleanup; the shared `flashRowPulse` keeps its own timer).
- `:360–363` — `exitMobile()` clears a pending target and the owned pulse
  timer, so a breakpoint change can never apply a stale reveal on re-entry.
- `:617–625` — `render()` consumes a matching `pendingReveal` **after** day
  resolution and **before** `renderDays()`/`renderDayHeading()`, then scrolls/
  flashes once. Weekday retention and the T07 saved-day preference still run;
  an explicit reveal simply wins.
- `:637–808` — the T08 block: `prefersReducedMotion()`, `clearPulse()`,
  `pulseNode()`, `isVisibleTarget()`, `revealElement()`, `revealNode()`,
  `revealDays()`, `revealMatchesSnapshot()`, `resolveRevealTarget()`,
  `revealTarget()` and `consumePendingReveal()`. No port read, no refresh call,
  no week/venue command and no DOM write to the canonical table.
- `:911–914` — `destroy()` clears the pending target and pulse timer alongside
  the existing hop/refresh reset.
- `:933` — the controller now returns `revealTarget` with the existing
  `mount/refresh/destroy/snapshot/isMobile` surface.
- `resources/views/livewire/replacement-arrangement.blade.php:1380–1398` — the
  existing `weekNav.flashEarliestBookable` assignment is wrapped: mobile computes
  `firstBookableDayIn(weekData, weekNav.currentWeek)` and calls
  `revealTarget({weekIndex, venueKey: currentVenue.code || currentVenue,
  dayIndex, hourIndex: null, tone: 'flash'})`; desktop keeps the exact
  `flashEarliestBookableDay(weekData, weekNav.currentWeek)` call.
  `jumpToEarliestBookable`, the `#earliestBtn` click listener and week
  navigation are untouched.
- `:3539–3570` — `pulseTargetCell()`/`flashTargetCell()` now early-return on
  `arrangementMobile?.isMobile()` with `revealTarget(..., tone:'pulse'|'flash')`;
  the desktop selector path, scroll and shared `flashRowPulse` call are
  byte-identical to the baseline.
- `:3709–3749` — new `revealSavedTarget(venueKey, weekIdx, block)` (mobile:
  explicit week/venue/day/start with tone `flash`; desktop: the original
  `flashTargetCell`), used by both branches of `navigateToSavedBlock()` in place
  of the two raw flash calls. Neither helper selects, navigates or writes.
- No new page port was added: the page closure calls the existing controller
  variable. Boot/prefill day resolution stays exactly as T07 left it.

### Reveal behaviour

| Case | Result |
|---|---|
| Mobile inactive / unmounted / destroyed / no snapshot | `false`, nothing stored or drawn |
| Missing `weekIndex`/`venueKey` | resolved from the cached snapshot (`explicit` value wins when given) |
| Snapshot already on the requested week+venue | immediate: viewed day set, day buttons + heading re-rendered from cache, visible target scrolled + feedback, no port read |
| Snapshot still on the old week/venue (navigation just committed) | target stored as `pendingReveal`; the next settled `render()` applies it exactly once when the snapshot matches |
| Pending target whose settled render still mismatches | cleared silently; never applied by a later render |
| Requested day missing / not `eligible === true` | `false`; no pending, no selection, no venue/week change |
| Valid hour with a visible T09 row (`[data-mobile-hour="N"]`) | that row is scrolled/flashed (also used for saved start hours) |
| No hour, no row, or a hidden row | visible day button (Earliest has `hourIndex: null` by design) |
| `tone:'flash'` | shared `global.flashRowPulse` when available (page `.bookable-flash` family), else the page `.attention-pulse` class |
| `tone:'pulse'` | page `.attention-pulse` class, reflow restart, removed after 1300 ms |
| Reduced motion | instant scroll, no flash/pulse animation; state still changes |
| Any reveal | no `refresh`, no `chooseWeek`, no venue switch, no canonical rebuild, no booking write |

The target is scrolled with `block:'center'` (so a sticky header/footer cannot
hide it); the reveal deliberately does not move keyboard focus or announce —
T13 owns focus/announcements.

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (lines 1301–5308, 4008 lines) →
  `node --check` — pass.
- `/tmp/opencode/t08-adapter-smoke.cjs` — dependency-free Node `vm` + DOM stub,
  **136 passing assertions** (the 75 T07 baseline assertions unchanged plus 61
  T08 ones; the stub itself gained a per-element `classList` and exact
  attribute-value selector matching so the reveal targets can be identified):
  same-week reveal sets the day/heading, flashes and scrolls the
  visible day button with no port read/hop/timeline output; a real timeline row
  is the target when visible and a hidden row falls back to the day button;
  `pulse` uses `.attention-pulse`, leaves the shared flash untouched and removes
  the class after cleanup; a stale snapshot after a committed week change stores
  the target and the matching settled render applies it exactly once with one
  flash (later renders never repeat it); a mismatched settled render clears the
  target and it is never resurrected by a later matching snapshot; ineligible
  Sunday/too-soon/unknown/negative/non-numeric targets and missing input are
  refused with no flash/day move; a venue mismatch is stored then cleared
  without switching venue; desktop, pre-mount and post-destroy calls are inert;
  destroy clears the pulse class/timer and the pending target while still
  removing the delegated listener; reduced motion scrolls instantly with no
  animation; without the shared helper, `flash` falls back to the pulse class.
- `/tmp/opencode/t08-page-static.cjs` — static wiring check, **39 passing
  assertions**: both feedback helpers check mobile first and keep the desktop
  selector/flash; `revealSavedTarget` passes explicit week/venue/day/start and
  keeps the desktop call; `navigateToSavedBlock` uses it in both branches with
  no raw flash and no selection; the Earliest wrapper resolves
  `firstBookableDayIn`, passes week/venue/`hourIndex:null`/flash, keeps the
  desktop call, creates no selection, and the original click listener and
  `jumpToEarliestBookable` are untouched; the controller returns
  `revealTarget`; `pendingReveal`/`consumePendingReveal`/`.attention-pulse`/
  `flashRowPulse`/`data-mobile-hour` are present and `revealTarget` performs no
  port read.
- Existing lane stubs re-run against the T08 adapter: t04 `PASS`, t05 `PASS`,
  t06 `PASS` (64 assertions), t07 `PASS` (75 assertions). `t03-shell-smoke.cjs`
  still fails its pre-T04 namespace assertion (`['create']` vs
  `['create','utils']`); that drift predates T08 and T04's receipt records the
  added `utils` export — reported, not "fixed" here.
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` — pass.
  No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push.

These are static/stub receipts, **not** browser, visual, accessibility or device
evidence. Nothing here proves real scroll/sticky behaviour, the 768/769px
rendering, touch targets, focus order or screen-reader output.

### Hazards carried forward

- **T09 timeline-row hook.** Hour targeting depends on
  `[data-arrangement-mobile-timeline] [data-mobile-hour="<hourIndex>"]` rows
  with original hour indexes; without that attribute every reveal falls back to
  the day button. Rows inside a `hidden` timeline are skipped by design, so T09
  must not make the host visible while the semester is empty (T07 hazard).
- **T09 must follow an immediate same-week reveal.** The immediate path
  re-renders `renderDays()`/`renderDayHeading()` only (T09's timeline does not
  exist yet). When T09 adds the daily timeline it must re-render from
  `viewedDayIndex` on the same day-change funnel (or expose its own render hook
  the adapter calls) so a reveal of another day in the SAME week does not leave
  the old day's rows behind; the pending path already runs inside `render()`
  after day resolution.
- **Sticky offsets are T14.** The reveal uses `block:'center'` and no
  `scroll-margin`; T14 owns the measured sticky/action-bar insets and must
  re-check that the first/last row and the day cluster stay visible after a
  scroll.
- **Keyboard/focus/announcements are T13.** `revealTarget` moves no focus and
  makes no announcement; day buttons remain adapter-rendered and depend on the
  single delegated `onDayClick`. T13 must keep that listener reachable while
  blocking legacy document-level shortcuts.
- **Pending is one-shot per settled render.** If a committed navigation never
  produces a snapshot with the requested week+venue (e.g. a rejected/cancelled
  change), the target is dropped rather than retried — intentional: no timer,
  no forced navigation, no silent venue switch. A later committed change needs
  its own reveal call.
- **Boot/prefill pulse still targets the canonical table.** `pulseTargetCell`
  runs during `applyUrlParams()` before `ArrangementMobile.create()`, so the
  optional-chain short-circuits to the existing desktop path; T07's mount
  render still resolves the initial mobile day from the prefill. T10/T17 must
  not add a second selection path around this ordering.
- **Reveal never expands the original-context `<details>`.** The clicked/jumped
  target is a day/slot surface only; T14's compact context stays as T06 left it.
- `revealTarget` returns `true` when a target is merely pending; callers that
  need "already applied" must not treat the return value as visual proof.

T08 checkbox remains for the parent to mark; page changelog, full PHP gates and
the sanctioned server restart stay T24/T29.


## Apply T09 — daily timeline and continuous selected span — 2026-11-11

Delegated T09 only, after completed T01–T08 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership hint, retained blue
selection outline). This task renders the one-day vertical timeline and the
saved blue span: no selection/tap wiring (T10), no sheets/legend/Tip/removal
(T11), no fixed summary bar (T12) and no sticky/measured/44px geometry (T14).
Baseline `28f9f4d`; the working tree also carries the uncommitted T03–T08 lane
and other sessions' dirty files, none of which were touched.

### Changed anchors

- `public/js/arrangement-mobile.js:153–170` — T09 constants: the canonical
  state-class whitelist (identical to the page port's
  `SNAPSHOT_STATE_CLASSES`), structural row/unknown/span classes and the
  `data-mobile-state` / `data-mobile-day` / `data-mobile-span` hooks.
- `:836–1068` — the T09 block: `canonicalStateClasses()`,
  `primaryStateToken()`, `timelineStateText()`, `addHalfHour()`/`clockEnd()`/
  `hourRangeText()`/`twelveHourRange()`, `entrySpan()`, `selectedSpanFor()`,
  `spanRenderable()`, `createTimelineRow()`, `createSelectedSpan()` and
  `renderTimeline()`. Pure presentation: no port read, no `refresh()`, no
  store reference, no selection command.
- `:646` — `render()` calls `renderTimeline(snap)` after `setEmptyState()` and
  after the viewed-day/empty resolution, so every settled snapshot, week hop,
  cached breakpoint re-render and pending reveal lands on the current day.
- `:819` — the T08 pending/consumed reveal path is inside `render()`; the
  immediate same-week path now also calls `renderTimeline(lastSnapshot)` before
  `revealElement()` so a real hour row can be flashed.
- `:1133–1137` — `onDayClick()` rebuilds rows from the cached snapshot with the
  new `viewedDayIndex`; still no port read, week change or booking write.
- `:1235–1239` — `destroy()` clears adapter rows and hides the host.
- `resources/views/livewire/replacement-arrangement.blade.php:1047–1136` —
  page-scoped T09 CSS inside the existing `@media (max-width:768px)` block:
  single-column grid with `--arrangement-mobile-row-height` (44px fallback),
  an explicit `[hidden]` guard, row/span structure, canonical `::after` hover
  overlays retired, per-state readable foreground tokens, neutral unknown row
  and the 2px primary blue span outline. No token, shared CSS or layout change.
- `resources/views/partials/ui-arrangement-mobile.blade.php` — unchanged. The
  frozen timeline host and the existing summary/submit hosts were sufficient;
  no second summary/remove control was added.

### Timeline and span behaviour

| Input | Rendered result |
|---|---|
| Snapshot + eligible `viewedDayIndex` + `hours` | One `<button type="button">` per half-hour, ordered `0..hours.length-1`, original `data-mobile-hour`; axis label `08:00–08:30` (24-hour, end via `hours[end]` or +30 min fallback) |
| Canonical available cell | `cell-available` class, `data-mobile-state="available"`, text `Available`; all available rows look alike (no valid-start styling) |
| Canonical pending/reserved/occupied(+`--mine`)/too-soon/sun/PH | The snapshot's own whitelisted classes applied verbatim, `data-mobile-state` token (`pending`/`reserved`/`conflict`/`too-soon`/`sunday`/`holiday`) and the snapshot `stateLabel` with `<br>` flattened to spaces (`Your & Cohorts Conflict`, `Other Pending`, `Min. 3 Working Days`); borderless Pending/Conflict stays as the amendment requires |
| Label present but empty (sun/PH/unknown) | Neutral `Unavailable`; never green, never invented reasons |
| `stateClasses` empty or `known !== true` or cell absent | `arrangement-mobile-row--unknown`, `data-mobile-state="unknown"`, `Unavailable`, `disabled` + `aria-disabled`; the full axis is still rendered |
| Saved entry in `selections.entries` for current week+venue+viewed day | One blue block at `startHour`, `data-mobile-row="span"`, `data-mobile-state="selected"`, `data-mobile-span` slot count, grid `span N`, readable `11:00 AM ~ 12:00 PM` (+ venue text); covered hours are skipped, no duplicate rows |
| No matching saved entry | No blue block; all rows are ordinary canonical rows |
| Saved entry with any covered cell not `cell-selected`/`cell-available` (blocked, too-soon, pending or unknown) | No blue block; the real canonical rows stay, so an expired/unrenderable entry is never painted over them |
| Saved entry beyond `hours.length` | Not drawn; rows remain |
| `selections.current` only (no entries copy) | Accepted as a defensive fallback for the same week/venue/day; entries are always read first |
| Other week/day/venue saved entry | Ignored by the timeline (T12 owns the cross-view saved bar) |
| T07 empty semester / no eligible viewed day / missing snapshot / no axis | Timeline host hidden and cleared; the empty message and disabled date/Earliest/Submit controls are untouched |
| Enter mobile / exit / destroy | Breakpoint re-render rebuilds from the cache; destroy hides and empties the host |

Span height comes from the saved indexes and CSS grid row span, not from
desktop cell measurement; the spanning item stretches over the tracks (plus the
internal gap) it would have occupied. Rendering mutates neither the snapshot nor
the page store (asserted by deep-equal in the stub).

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (lines 1392–5399) → `node --check`
  — pass.
- `/tmp/opencode/t09-adapter-smoke.cjs` — dependency-free Node `vm` + DOM stub,
  **80 passing assertions**: full ordered axis with original hour indexes and
  `type="button"`; 24-hour start/end labels; available rows identical; the
  canonical classes/labels for conflict+pending+too-soon+Sunday+PH with
  multiline flattening and no raw `detailText`; empty-label `Unavailable`;
  unknown/missing/absent cells disabled, never green, with the axis intact;
  one grid-span saved block from `entries` with 12-hour range, venue, skipped
  half-hours and original-order remainder; no snapshot/store mutation; other
  week/day/venue ignored; entries beat an other-day pointer; pointer-only
  fallback; blocked/too-soon/pending/unknown and past-day-end spans draw no
  blue; 24-hour fallback when the shared clock helpers are absent; day tap
  re-renders the new original day with no port read; same-week immediate reveal
  re-renders then flashes the generated hour row; empty semester keeps the
  exact T07 message, hidden host and cleared rows, recovery rebuilds, destroy
  clears/hides; missing snapshot/no eligible day hidden; non-whitelisted
  classes ignored.
- `/tmp/opencode/t09-page-static.cjs` — **32 passing assertions**: T09 calls
  `renderTimeline` from the settled render, the day tap and the immediate
  reveal; the renderer touches no port/store/selection/refresh; the empty state
  and no-day guard hide the host; destroy clears; row/span hooks and disabled
  unknown cells exist; span height uses the saved slot count; no new listeners
  or timers; CSS has the `[hidden]` guard, no global `.timetable` selector and
  no non-token colours; the partial keeps the frozen hosts and adds no second
  summary control.
- Existing lane stubs: t04 adapter `PASS`, t04 snapshot `PASS`, t05 `PASS`,
  t06 `ALL PASS`. t07 unchanged `74/75` and t08 unchanged `132/136`; the five
  failures are the stubs' pre-T09 expectations and were re-run with only those
  assertions updated in `/tmp/opencode/t09-regression-t07.cjs` (75/75) and
  `t09-regression-t08.cjs` (136/136):
  - `S9 timeline not hidden` on a **null** snapshot is superseded by the T09
    rule "keep the timeline host hidden if there is no viewed day/snapshot".
  - t08 `S10 reveal renders no timeline content` is superseded because the
    reveal now rebuilds rows for the revealed day.
  - t08 `S11` appended a fake row then expected it to survive; T09 now owns and
    rebuilds the host, so the updated copy asserts the generated hour row and
    the missing-hour day-button fallback.
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` —
  pass. No `--env=testing`, DB access/write, browser, Playwright, server
  restart, staging, commit or push.

These are static/stub receipts, **not** browser, visual, accessibility or device
evidence. Nothing here proves real grid sizing, scroll/sticky behaviour, 768/769
parity, tap targets, contrast on every theme or screen-reader output.

### Hazards carried forward

- **T10 tap routing.** Rows emit `data-mobile-row="hour"`, `data-mobile-hour`
  (original index), `data-mobile-day` and `data-mobile-state`
  (`available`/`selected`/`pending`/`reserved`/`conflict`/`too-soon`/
  `sunday`/`holiday`/`unknown`). Only `available` is a selection candidate;
  blocked rows remain enabled for T11 reason sheets, while `unknown` rows are
  `disabled` and must never be selected. The blue block is
  `data-mobile-row="span"`/`data-mobile-state="selected"` and must not be
  routed as a green tap. No selection listener exists yet.
- **T10/T11 must not rebuild rows themselves.** `renderTimeline` owns and
  replaces the host children on every settled render, day tap and immediate
  reveal; any separately added node or listener inside the host is discarded.
  Delegated listeners must sit on the root/controller, not on generated rows.
- **T11 reason filtering.** The timeline deliberately renders only
  `stateLabel`; raw `detailText`/`ariaText` are not shown. Reason sheets must
  read the permitted canonical `detailText`/`ariaText` from the snapshot (or a
  new port) and keep the desktop "Clear your selection first — click it to
  remove" wipe hint out of canonical reasons. The blue span only exposes
  `data-mobile-hour`/`data-mobile-day`/`data-mobile-span`; venue/week identity
  for details/removal must come from the snapshot or a T11 port.
- **T12 saved bar is independent.** This timeline draws only the current
  week+venue+viewed-day entry; off-view/legacy entries are not listed or
  counted here. T12 must read all entries and provide the summary/removal.
- **T14 geometry contract.** The blue block's equivalent height depends on the
  timeline host staying a single-column CSS grid with
  `grid-auto-rows: minmax(var(--arrangement-mobile-row-height, 44px), auto)`;
  switching the host to flex or fixed pixel rows would break the span height.
  T14 may raise the variable to 48px, add sticky clusters/safe areas and wrap
  the span, but must keep the `[hidden]` guard (T07 empty state) and the
  `data-mobile-hour` hooks (T08 reveal).
- **Reveal never targets the hidden canonical table.** Rows are generated only
  for the viewed day; an hour outside the axis falls back to the day button.
  T14's sticky offsets must re-check that a revealed row is not hidden behind
  the sticky cluster or fixed bar.
- **Blocked rows are visually canonical, not actionable yet.** They carry
  pending/conflict/too-soon colours and labels but no reason sheet until T11;
  tapping them currently does nothing (no selection attempt).
- **No announcement.** Rebuilt rows change silently; the single polite
  announcement and focus management stay T13.

T09 checkbox remains for the parent to mark; page changelog, full PHP gates and
the sanctioned server restart stay T24/T29.

## Apply T10 — mobile selection and five-second feedback — 2026-11-11

Delegated T10 only, after completed T01–T09 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership hint, retained blue
selection outline). This task connects taps to the ONE existing canonical
selection path and adds mobile-only transient feedback: no sheets/removal (T11),
no saved-summary bar (T12), no keyboard/focus/announcement work (T13), no
geometry/sticky styling (T14), no expiry or submit guards (T16–T18) and no
booking-store writes in the adapter. Baseline `28f9f4d`; the working tree still
carries the uncommitted T03–T09 lane and other sessions' dirty files, none of
which were touched here.

### Changed anchors

- `public/js/arrangement-mobile.js:172–181` — T10 constants:
  `ROW_KIND_ATTRIBUTE = 'data-mobile-row'`, the exact guidance
  `You already selected a class-length block. Open its details to remove it
  before choosing another.` and the short stale-row transient
  `That time just became unavailable. Pick another free time.`
- `:205–206` — the two optional ports are captured once:
  `chooseAvailable` (canonical selection) and `showTransient` (mobile toast).
  Missing ports fail closed; neither is a booking store or a second command.
- `:237` — `timelineClickBound` tracks the single delegated listener.
- `:1156–1250` — the T10 block:
  `snapshotDays()`, `snapshotCell()`, `rowIsSelectable()`,
  `hasSavedSelection()`, `notifyTransient()` and `onTimelineClick()`.
  All reads are from the cached `lastSnapshot`; no `refresh()`, store write,
  DOM optimistic paint or availability reclassification happens here.
- `:1307–1312` — one `root.addEventListener('click', onTimelineClick)` at
  mount (alongside the existing T07 day listener), bound only once.
- `:1328–1331` — `destroy()` removes the timeline listener and clears the flag.
  Refresh and breakpoint changes add no listener.
- `:1367–1372` (tail comment only) — the returned controller surface is
  unchanged; T10 adds no public method.
- `resources/views/livewire/replacement-arrangement.blade.php:5375–5397` —
  the page ports:
  `chooseAvailable(dayIndex, hourIndex)` validates the two original indexes,
  resolves the actual canonical
  `#tableBody td[data-day="…"][data-hour="…"] .cell-content`, calls the existing
  `toggleCell(day, hour, cell)` exactly once and returns `false` when no cell
  exists (otherwise `true`);
  `showTransient(message)` calls `toast.show(message, null, 5000)` exactly.
- `:5407–5409` — both are passed to `ArrangementMobile.create` beside the
  existing `readSnapshot`, `closePanels` and `chooseWeek` ports. No submit,
  removal, venue, original-context or second selection command is added.

### Click routing and pre-checks

| Tap / state | Behaviour |
|---|---|
| Day button | The existing T07 `onDayClick` still owns it; the timeline handler ignores it |
| `data-mobile-row="hour"`, `data-mobile-state="available"` | Reads `data-mobile-day` + original `data-mobile-hour`, re-checks the cached snapshot, then calls `chooseAvailable(day, hour)` once |
| Child span inside an available row | Delegates through `closest()` to the same hour row; one call |
| `data-mobile-row="span"` (blue selection) | Ignored; T11 owns inspect/remove |
| pending / reserved / conflict / too-soon / sunday / holiday / selected / unknown | Ignored with no selection attempt and no toast; T11 owns reason sheets |
| Viewed day no longer `eligible === true` | Short 5s transient, no port call |
| Snapshot cell missing, `known !== true`, or no longer `cell-available` | Short 5s transient, no port call |
| Any saved entry anywhere (`entries.length > 0`, `totalSlots > 0`, or `current`) | Exact already-selected guidance for 5000ms; no `chooseAvailable`, no replace/clear, no optimistic span |
| No saved selection and a valid free row | `chooseAvailable` only; the page's `toggleCell` keeps subject/too-soon/full-span/budget/day-end feedback and `selectBlock` writes the store; the coalesced T05 refresh then renders the blue span from the committed snapshot |
| Desktop / unmounted / destroyed / post-destroy | No routing and no mobile toast |
| Throwing page port or toast port | Contained; no retry or duplicate call |

The adapter never writes `selectedBlock`, `selectedSlotsByVenue`, a blue block
or availability classes. It does not suppress or rewrite the canonical page
feedback, and it adds no document-level handler, timer or submit path. Desktop
row clicks, the T07 day taps/empty state and the T08 reveal remain untouched.

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (lines 1392–5428, 4037 lines) →
  `node --check` — pass.
- `/tmp/opencode/t10-adapter-smoke.cjs` — dependency-free Node `vm` + DOM stub,
  **109 passing assertions** (the 80 T09 assertions unchanged, plus T10):
  exactly two root click listeners after mount; an available row and a child
  inside it call the port once with the original day/hour; no transient on a
  valid free tap; repeated `refresh()` and breakpoint enter/exit add no
  listener; destroy removes both; the blue span, pending, conflict, too-soon and
  unknown rows never call the port and stay silent (T11); entries-only,
  totalSlots-only and current-only saved selections each show the exact
  guidance, call nothing and leave the snapshot/store deep-equal; a cell that
  turns unknown/blocked and an ineligible viewed day show the short transient
  and call nothing; a throwing page port and a throwing/missing toast port are
  contained; desktop and post-destroy taps are inert.
- `/tmp/opencode/t10-page-static.cjs` — **40 passing assertions**, including an
  executed stub of the extracted page functions: `chooseAvailable` queries the
  exact `#tableBody td[data-day="…"][data-hour="…"] .cell-content`, rejects
  negative/non-integer indexes before querying, passes the real cell to
  `toggleCell` once and returns `false` without a cell; `showTransient` invokes
  `toast.show(message, null, 5000)` exactly once; both ports are wired and the
  existing ports remain; the adapter has exactly one `chooseAvailable` call
  site, one timeline bind/remove pair, the three-part saved-selection guard and
  no `event-selection`/HTML paint; T09 CSS is intact and T10 adds none.
- Lane regressions re-run against the T10 adapter: t04 adapter/utils `PASS`,
  t04 snapshot `PASS`, t05 `PASS`, t06 `ALL PASS`, t09 adapter `80/80`,
  t08 page-static `39/39`. The original T09 regression copies fail only their
  pre-T10 listener-count assertions; with those copies updated for the new
  second root listener they pass: t07 `75/75`, t08 `136/136`, T09 page-static
  `32/32`. The three stale failures are reported, not disguised as T10 product
  regressions.
- `php artisan view:cache` then `php artisan view:clear` — pass. `git diff
  --check` — pass. No `--env=testing`, DB access/write, browser, Playwright,
  server restart, staging, commit or push.

These are static/stub receipts, **not** browser, visual, touch, accessibility or
device evidence. Nothing here proves real 768/769px rendering, five-second wall
clock behaviour under timers, `matchMedia` transitions on a device, focus after
selection, or actual toast timing over the fixed bar (T14).

### Hazards carried forward

- **T11 owns every non-available row and the blue span.** The delegated handler
  deliberately returns for `data-mobile-state !== "available"` and for
  `data-mobile-row="span"`, so T11 must add the sheet/removal opener (or extend
  this listener) without duplicating the selection guard. Do not turn blocked
  rows into a second selection path. `detailText` still contains the desktop
  selection-wipe hint and must stay out of canonical reasons.
- **The guidance wording already points at details.** Until T11/T12 land, the
  message says to open details; the span and summary openers are those tasks'
  scope. Keep the exact five-second wording authoritative if the opener changes.
- **T12 saved bar is independent.** The adapter only inspects
  `selections.entries`/`totalSlots`/`current` for the guard; it never counts or
  renders saved entries. T12 must read all entries, provide the persistent
  summary and use the relocated original Submit, not a new handler.
- **T13 keyboard/focus.** Rows are native `type="button"` elements, so
  Enter/Space activation still arrives as a click; T13 must preserve that while
  suppressing both document keyboard owners and must move focus/announce after
  a committed selection. The adapter moves no focus and makes no announcement.
- **T16 live expiry.** `rowIsSelectable()` trusts the cached snapshot's
  `eligible`/cell classes. A row that became too soon after the last settled
  read can still be tapped until T16's local-date/visibility recheck and week
  option rebuild. The canonical `toggleCell`/cell class is the second line of
  defence, but it is only as fresh as the last `buildTimetable()`.
- **T06/T19 teardown is covered for this listener.** `destroy()` removes both
  root click listeners; `refresh()` and `enterMobile()`/`exitMobile()` never bind
  one. T19 should re-check node restoration and any future added listeners.
- **Focus/announcement and 44px polish are absent by design.** The available
  rows are buttons but the adapter adds no cursor/geometry CSS; T14 owns the
  sticky/44–48px composition and toast offset above the action bar.

T10 checkbox remains for the parent to mark; page changelog, full PHP gates and
the sanctioned server restart stay T24/T29.


## Apply T11 — information sheets, legend, Tip and explicit removal — 2026-11-11

Delegated T11 only, after completed T01–T10 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership-border hint, retained
blue selection outline). This task adds the ONE native information-sheet flow,
the four-row legend, the state-aware Tip sheet with mobile tooltip suppression
and the exact-keyed Remove port. It does **not** add the fixed saved-summary bar
(T12), keyboard/focus management (T13), full bottom-sheet geometry/safe areas/44px
polish (T14), expiry retention (T15/T16) or submit guards (T17/T18). Baseline
`28f9f4d`; the working tree still carries the uncommitted T03–T10 lane and other
sessions' dirty files (`docs/demo-db-data-changes.md`,
`resources/views/livewire/replacement-home.blade.php`), none of which were touched.

### Changed anchors

- `public/js/arrangement-mobile.js:183–241` — T11 constants: the existing partial
  hooks, the whole-line selection-wipe filter, the Tip label prefix, the last-resort
  base guidance, canonical state-label fallbacks and the four real legend rows
  (`cell-available`, `event-selection`, `cell-pending`, `cell-occupied`).
- `:265–268` — one optional `removeSelection` port captured once. Missing port
  fails closed (no Remove action is offered).
- `:467–469` / `:503–505` — breakpoint exit closes the mobile sheet once and then
  restores the latest Tip message; destroy also clears the opener/candidate.
- `:744–746` — `render()` re-applies the Tip suppression after every settled read.
- `:1301–1304` — the existing single root click listener now routes T11 first;
  only available hour rows fall through to the T10 selection path.
- `:1338–1782` — the T11 block: `summaryTipNode()`, `tipTextFrom()`,
  `applySummaryTip()`, `criticalModalOpen()`, `openInfo()`, `closeInfo()`,
  `onInfoClosed()`, `sheetFactList()`, `permittedReasonText()`,
  `sheetReasonBlock()`, `sheetVenueText()`, `sheetTimeText()`, `durationText()`,
  `sheetStateText()`, `savedEntryFor()`, `removeSavedSelection()`,
  `openSelectedSpanSheet()`, `openBlockedRowSheet()`, `openLegendSheet()`,
  `openTipSheet()` and `routeInfoClick()`. All content is `textContent`/DOM-node
  only; there is no `innerHTML`, no second click listener and no sheet timer.
- `:1837–1842` — one native dialog `close` listener at mount (focus return);
  removed in `destroy()`.
- `:1914` — the controller surface adds only `closeInfo` for the page's
  critical-modal ordering hook.
- `resources/views/livewire/replacement-arrangement.blade.php:1138–1240` —
  page-scoped T11 CSS inside the existing `@media (max-width:768px)` block:
  sheet/backdrop (`--color-scrim`), wrapping `pre-line` body, readable fact rows,
  legend swatches and the `::after { content:none }` guard. T14 keeps ownership
  of full bottom-sheet geometry/safe areas/44px polish; no token, shared CSS or
  layout change.
- `:2524–2526` and `:2558–2561` — `showAlertModal()` and `showConfirmModal()`
  call `arrangementMobile?.closeInfo?.()` first so a critical modal never stacks
  above the sheet. Existing callbacks/cancel semantics are untouched.
- `:5506–5517` — the page `removeSelection(venueKey, weekIndex)` port validates
  the exact key/week and calls the existing `removeSavedBlock()` once. It
  reimplements no store rule and adds no second control.
- `:5529–5531` — `removeSelection: removeSelection` is passed beside the existing
  T10 ports. No submit/venue/second selection port is added.
- `resources/views/partials/ui-arrangement-mobile.blade.php` — unchanged. The
  T03 dialog shell (heading/body/actions/Close) and legend opener were sufficient;
  no second summary/remove control was added.

### Sheet, removal, legend and Tip behaviour

| Input | Behaviour |
|---|---|
| `[data-mobile-row="span"]` (blue block) | Opens `Saved selection` with the matching `selections.entries` row (current week+venue+day+start/end) or the `selections.current` fallback; shows subject, date, time, duration and venue. No mutation. |
| Matching entry + remove port | One `Remove selection` `type=button` action; calls the port with that entry's exact `venueKey`/`weekIndex` once, closes the sheet once, then queues the existing coalesced refresh. The row is never deleted optimistically. |
| Span with no matching entry/current | Details still open from the row/snapshot; no Remove action (fail closed). |
| Pending / Conflict / Reserved / Too-soon / Sunday / PH row | Opens the canonical state label as heading plus State/Date/Time/Venue facts and the permitted plain-text reason from `detailText`, falling back to `ariaText`. Whole lines beginning `Clear your selection first…` (case-insensitive variants) are removed; other multiline holder text keeps its paragraph breaks. If no reason remains, only the canonical state label/facts are shown — nothing is invented. |
| Unknown row | No sheet; T10 routing also declines it. |
| Available row | No sheet; the T10 `chooseAvailable` path is unchanged. |
| Day button | T07's `onDayClick` still owns it; the info routing ignores it. |
| Legend opener | `Colours & borders`: four real swatch classes; Pending/Conflict borderless; explains that tapping them shows ownership and why; no `Thick border (3px) = Yours…` hint and no ownership equation. |
| Tip opener (`#summaryTip`) | `Tip` sheet with the live state-aware message (`tips.summary.tip`, then the always-maintained aria-label, then base guidance). While mobile only this trigger's `data-tip` is removed; its `aria-label` and the page controller message are kept. Desktop exit restores the latest message. |
| Close button / Escape / backdrop | Closes the sheet once and returns basic focus to the tapped control (or active element). Information never auto-dismisses. |
| Critical modal already open | A new info sheet is refused and any open sheet is closed first. Opening the existing alert/confirm closes info first. |
| Breakpoint exit / destroy | The sheet is closed once; the Tip trigger gets its latest tooltip back; the dialog `close` listener and both root click listeners are removed on destroy. |

All sheet text uses `textContent`; no holder/state text is parsed as markup. The
adapter reads only the cached snapshot and calls no store, network or submit path.
No timers exist for sheets; the only adapter timers remain the T05 scheduler
fallback and the T08 reveal pulse.

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (4058 lines) → `node --check` — pass.
- `/tmp/opencode/t11-adapter-smoke.cjs` — dependency-free Node `vm` + DOM stub
  (dialog `showModal`/`close`, focus/activeElement, listener dispatch),
  **84 passing assertions**: mobile mount removes only the Tip `data-tip` and
  keeps aria; Tip sheet shows the live message and re-suppression after a later
  `setSummaryTip`; desktop exit restores the latest message; legend has exactly
  the four real swatch classes, the four labels, the ownership/why explanation,
  no obsolete thick-border hint and no actions; blue span details include
  subject/date/time/duration/venue; Remove is `type=button`, calls the port once
  with `['B103', 0]`, closes once, leaves the row in place until refresh and
  cannot run twice; the current-pointer fallback removes its exact key; a missing
  removal port still inspects but offers no Remove; conflict facts and multiline
  permitted reasons render, with the wipe line filtered; wipe-only detail falls
  back to aria; an empty label falls back to the canonical state token with no
  invented reason; unknown/available routing is unchanged (`chooseAvailable`
  still called for the available row); backdrop/Close/Escape dismiss and restore
  focus; an open critical modal blocks a new sheet; breakpoint exit closes the
  sheet and restores the Tip; destroy closes and removes every owned listener;
  Tip base guidance fallback; literal markup in labels/holder text stays text
  and creates no element.
- `/tmp/opencode/t11-page-static.cjs` — **80 passing assertions**: the page
  `removeSelection` port validates empty/non-integer/negative keys and calls
  `removeSavedBlock` exactly once per accepted key (executed against a stub);
  the port is wired once and every T10/T06 port is preserved; both critical
  modal openers close info first; the partial still has exactly one dialog and
  no second remove control; the adapter has one `openInfo`, one `showModal`, one
  `removeSelectionPort` call site, one routing gate, one dialog `close`
  listener, no `innerHTML`/`insertAdjacentHTML` and no sheet timer; the reason
  filter is whole-line/case-insensitive with aria as fallback; all four legend
  classes and the ownership/why wording exist with no thick-border/3px text;
  Tip suppression touches only `data-tip` and restores the latest message; CSS
  is inside the mobile media block, uses theme tokens only (no literal hex/rgb),
  has a scrim backdrop, wraps text, keeps swatches borderless and preserves the
  T07 `[hidden]` guard and T09 row CSS. `theme.css` is untouched.
- Lane regressions re-run against the T11 adapter: t04 adapter/utils `PASS`,
  t04 snapshot `PASS`, t05 `PASS`, t06 adapter `ALL PASS`, t10-regression-t07
  `75/75`, t10-regression-t08 `136/136`, t09 adapter `80/80`, t10 adapter
  `109/109`. Three static copies carried pre-T11 expectations and were re-run
  with only those expectations updated, not hidden as product failures:
  - t10-regression-t09-page asserted `detailText` is never referenced and a
    3-count listener budget; T11 legitimately reads `detailText` through the
    filtered reason builder and adds the dialog `close` listener. Updated copy
    `t11-regression-t09-page.cjs` → `32/32`.
  - t10-page-static asserted `event-selection` never appears; T11's legend must
    use that real swatch class. Updated copy `t11-regression-t10-page.cjs` →
    `41/41` (asserts exactly one reference, the legend row).
  - t08-page-static pinned the exact controller return list; T11 adds
    `closeInfo`. Updated copy `t11-regression-t08-page.cjs` → `39/39`.
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` — pass.
  No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push.

These are static/stub receipts, **not** browser, visual, touch, accessibility or
device evidence. Nothing here proves real `<dialog>` top-layer/backdrop behaviour,
focus containment or restoration on a device, actual multiline rendering,
swatch contrast across themes, that the shared tooltip engine never flashes on
focus before suppression, or 768/769px parity.

### Hazards carried forward

- **T12 needs a summary opener, not a second sheet.** `openInfo()` is private;
  the controller exposes only `closeInfo`. T12 should add one public details
  method (or reuse the span flow) that reads **all** `selections.entries` and
  calls the same `removeSelection` port for each exact key. Do not create a
  second dialog or a second removal rule; off-view removal (MA-05e) is not
  reachable from this task's current-view span.
- **T12/T13 focus.** Removal closes the sheet before the refresh rebuilds rows;
  focus returns to the old span/row, which the settled render then replaces.
  T13 must provide identity-based restoration after refresh/removal and one
  polite announcement. `infoOpener`/`onInfoClosed` are the current best-effort
  hooks; `destroy()` nulls them.
- **T13 keyboard.** Escape is native `<dialog>` cancellation; the adapter adds
  no key handling. Legacy document shortcuts are still active behind an open
  sheet until T13 suppresses them. The dialog `close` event may fire async, so
  focus restoration is deliberately idempotent (`infoOpener` cleared first).
- **T14 geometry.** The sheet currently uses `margin: auto auto 0`,
  `max-height: 85dvh`, `overflow: auto` and no safe-area padding; T14 owns the
  measured bottom-sheet/44px/sticky-heading/safe-area work. Keep the page-local
  `.arrangement-mobile-info-swatch::after { content:none }` guard (otherwise the
  canonical cell hover overlay can paint over a swatch) and keep Pending/Conflict
  swatches borderless; `event-selection` intentionally keeps its blue outline.
- **Critical-modal detection is page-specific.** `criticalModalOpen()` checks
  `#confirmModal` inline display and any other open `<dialog>`. T17/T18 must not
  replace the existing alert/confirm callbacks; the two `closeInfo()` hooks stay
  before those openers. A future critical modal implemented outside
  `#confirmModal`/`<dialog>` would need the same close hook.
- **Tip suppression is render-synchronous.** `setSummaryTip()` writes `data-tip`
  and refreshes; the coalesced render removes it again. If T16/T19 add a path
  that writes `data-tip` without a settled refresh, only that one path could
  show one floating tooltip until the next render. Do not change the shared
  tooltip engine or other triggers.
- **Snapshot freshness.** Sheets render from the last settled snapshot (T05
  contract); T16's clock recheck/rebuild owns live expiry. T11 does not
  reclassify cells or refetch holder data, and no sheet auto-closes on expiry
  (MA-10d leaves that readable state intact).
- **No submit/busy path.** T11 adds no Submit handler or latch; T17/T18 remain
  the only confirmation/identity/duplicate-request owners.

T11 checkbox remains for the parent to mark; page changelog, full PHP gates and
the sanctioned server restart stay T24/T29.

## Apply T12 — fixed saved-summary bar — 2026-11-11

Delegated T12 only, after completed T01–T11 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership-border hint, retained
blue selection outline). This task adds the always-visible saved-summary bar
inside the existing mobile action host and the one all-entry details sheet,
reusing the T11 `openInfo` dialog, the T11 exact-keyed `removeSelection` port and
the relocated original Submit control. It does **not** add expiry/request guards
(T15–T18), full keyboard/focus management (T13) or the final measured
bar/sticky/safe-area geometry (T14). Baseline `28f9f4d`; the working tree still
carries the uncommitted T03–T11 lane and other sessions' dirty files
(`docs/demo-db-data-changes.md`, `resources/views/livewire/replacement-home.blade.php`),
none of which were touched.

### Changed anchors

- `public/js/arrangement-mobile.js:241–255` — T12 constants: the existing
  summary-host hook, the opener attribute/selector, the exact empty copy
  (`Select a time` / `Tap a green time to begin.`), the multi-entry review hint
  and the defensive saved-but-unexpressible copy.
- `:759–761` — `render()` calls `renderSummaryBar(snap)` in the same settled turn,
  after the day/empty and timeline presentation. It never reads a port, never
  calls `refresh()` and never moves the week/day.
- `:1769–1776` — the existing `routeInfoClick` gate gains one summary branch
  **before** the legend branch; the tapped summary button becomes
  `infoOpenerCandidate`. No additional click listener was added.
- `:1803–1988` — the T12 block: `summaryEntryList()` reads **all**
  `selections.entries` (any week/venue/day) and falls back to `selections.current`
  only when that list normalises to nothing; `summaryEntryTimeText()`,
  `summaryEntryMetaText()`, `summaryTextNode()`, `summaryNotice()`,
  `summaryButton()`, `renderSummaryBar()`, `summaryRemoveActions()` and
  `openSummarySheet()`. All copy is `textContent`/DOM-node only; no `innerHTML`,
  no new timer and no second dialog.
- `:2116–2120` — `destroy()` clears the adapter-rendered summary host.
- `:2133` — trailing ownership comment now names T12. The controller return
  surface is unchanged (`closeInfo` only; the summary sheet stays routed).
- `resources/views/livewire/replacement-arrangement.blade.php:1240–1307` —
  page-scoped T12 CSS inside the existing `@media (max-width:768px)` block:
  fixed bottom action bar (background/border tokens, `z-index: 45`, safe-area
  bottom padding), reserved page-end padding (`112px + env(safe-area-inset-bottom)`),
  a `>=44px` summary control, wrapping title/meta text and an entry heading for
  the multi-entry sheet.
- `resources/views/partials/ui-arrangement-mobile.blade.php` — unchanged. The
  T03 summary/action/submit hosts and the one T11 dialog were sufficient; no
  second control, dialog or submit handler was added.

### Summary bar and sheet behaviour

| Input | Behaviour |
|---|---|
| No saved entries | One non-interactive bar block: `Select a time` plus `Tap a green time to begin.`; no opener attribute, no details sheet, no Submit change. |
| Exactly one saved entry (any week/day/venue) | Real `<button type="button">` with that entry's date and 12-hour range, plus venue and duration; `aria-haspopup="dialog"`/`aria-controls` point at the one info dialog. Tapping opens `Saved selection` with subject once and Date/Time/Duration/Venue. |
| Multiple legacy entries | Button reads `N selected blocks` + `Tap to review or remove`; the sheet heading reads `Saved selections`, subject appears once, and entries render as `Selection 1 of N` … each with its own Date/Time/Duration/Venue. Every entry gets an individually keyed `Remove selection N` action. Nothing is hidden, dropped or reduced to the first entry, and no new multi-booking path exists. |
| Remove | The shared T11 `removeSavedSelection()` calls the page's `removeSelection` port once with that entry's exact `venueKey`/`weekIndex`, closes the sheet once, then queues the existing coalesced refresh; the bar is never optimistically emptied and the entry returns if the committed store still has it. |
| Browsing another day/week | The bar is built from the whole entry list, so a day tap or a different viewed week leaves the summary and its details unchanged (MA-06b). |
| Empty semester | The empty message/timeline gating is untouched and the saved summary plus its inspect/remove path remain available. |
| Entries absent but `totalSlots > 0`/current exists | The current block is shown as the one-entry summary (never a false empty bar). If nothing can be expressed, the bar reads `Selection saved` with kept-selection guidance and offers no broken opener. |
| Missing removal port | Details still open; no Remove action is offered (fail closed). |
| Invalid legacy entries | Empty/non-integer/negative venue/week or non-positive spans are skipped defensively; remaining valid entries still render with the correct single/count copy. |
| Submit | Untouched: `#submitRequestBtn` inside the relocated `#submitTip` remains the only submit control; the adapter only reads it for the T07 empty-state disable. |

Sheet text is DOM-only (`textContent`), so holder/venue/subject markup stays
literal. No new removal rule, submit handler, timer, dialog or listener exists.

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (4058 lines) → `node --check` — pass.
- `/tmp/opencode/t12-adapter-smoke.cjs` — dependency-free Node `vm` + DOM stub,
  **97 passing assertions**: empty bar copy with no opener/details; one off-view
  entry renders a real `type=button` summary with its own week/date/12-hour
  range/venue/duration; tapping opens the shared dialog with subject once and
  date/time/duration/venue; day browsing leaves the summary unchanged; Remove
  forwards the exact off-view key once (`['A101', 1]`), closes once, refreshes
  through the existing read and does not optimistically empty the bar; three
  legacy entries show `3 selected blocks`, list `Selection 1..3 of 3`, subject
  once, three keyed Remove actions, and removing the second sends only
  `['B205', 0]` then `['C307', 1]` after a re-open with no duplicated sheet
  content; current-only fallback shows its real date/time; `totalSlots > 0`
  without an expressible entry never shows the empty copy; invalid entries are
  skipped without faking a count; empty-semester summary remains
  inspectable/removable; a missing removal port still inspects with no Remove;
  available-row selection, conflict sheet, legend and Tip routing are unchanged
  and summary taps never select; literal markup stays text; `destroy()` clears
  the bar and closes the sheet.
- `/tmp/opencode/t12-page-static.cjs` — **68 passing assertions**: one
  `renderSummaryBar` implementation/call in the settled turn; the host selector
  is the frozen partial hook; one `openSummarySheet`, one `showModal`, one
  `removeSelectionPort` call site, no adapter-created dialog; the whole entry
  list (not the viewed span) is read and `selections.current` is only the
  empty-list fallback; the routing branch precedes the legend branch and sets
  the opener; the existing two root click listeners and one dialog `close`
  listener are unchanged; exact empty copy, count copy and defensive saved copy
  exist; `destroy()` clears the host; Tip suppression and the single Submit read
  are untouched; no new timers, HTML writes, submit handler or synthesised
  click; the partial still has one dialog/summary host/submit host and no second
  control; the page's keyed `removeSelection` port is unchanged; T12 CSS is
  mobile-scoped, fixed, token-only (no literal hex/rgb), safe-area padded,
  reserves page-end space, has a 44px summary target and wrapping text;
  `theme.css` and `ui-common.js` stay untouched.
- Lane regressions re-run against the T12 adapter: t04 adapter/utils `PASS`,
  t04 page snapshot `PASS`, t05 coalescing `PASS`, t06 adapter `ALL PASS`,
  t09 adapter `80/80`, t10 adapter `109/109`, t10-regression-t07 `75/75`,
  t10-regression-t08 `136/136`, t11-adapter `84/84`, t11-page-static `80/80`,
  t11-regression-t08-page `39/39`, t11-regression-t10-page `41/41`.
  One static copy carried a pre-T12 expectation and was re-run with only that
  expectation updated, not hidden as a product failure:
  - `t11-regression-t09-page` asserted the page CSS never mentions
    `data-arrangement-mobile-summary-host` (a T09-era "no second summary
    control" guard). T12 legitimately styles the one existing host and adds no
    second control/host. Updated copy `t12-regression-t09-page.cjs` → `33/33`.
  Older direct copies `t07-adapter`, `t08-adapter`, `t09-page-static`,
  `t10-page-static` and `t10-regression-t09-page` still assert pre-T09/T10/T11
  baselines (listener budget, "no `event-selection`", "no summary-host CSS",
  fake hour-row target) and are superseded by the maintained regression copies
  above; they were not treated as T12 regressions and were not rewritten.
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` — pass.
  No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push.

These are static/stub receipts, **not** browser, visual, touch, accessibility or
device evidence. Nothing here proves fixed-bar behaviour during real scrolling,
safe-area insets on a device, `<dialog>` top-layer stacking above the bar,
focus return after Remove/refresh, or 768/769px parity.

### Hazards carried forward

- **T13 focus/announcements.** Remove closes the sheet and the settled refresh
  replaces the summary button that was the opener, so best-effort focus return
  can land on a disconnected node. T13 needs identity-based restoration plus one
  polite announcement; `infoOpener`/`infoOpenerCandidate` are the current hooks
  and `destroy()` nulls them. The bar is rebuilt on every settled render but the
  root click listener stays single.
- **T13 keyboard.** The summary/details sheet still inherits the T11 gaps:
  legacy document shortcuts remain active behind an open sheet and Escape is
  native dialog cancellation. The summary opener is a real `type=button`, so
  native Enter/Space work without adapter key handling.
- **T14 geometry.** T12 deliberately uses a simple `position: fixed` bar with a
  fixed `112px + env(safe-area-inset-bottom)` page-end reserve, a 44px summary
  and an unbounded sheet footer of Remove actions. T14 must replace this with
  measured bar/sticky insets, toast-above-bar placement, `scroll-margin`, a
  wrapped/multi-row bar if the Submit block is tall, and sticky sheet heading;
  keep the bar's safe-area padding and token colours. Do not move the summary
  outside `[data-arrangement-mobile-actions]` or add a second bar.
- **T15 retention.** The bar shows only entries the snapshot can express. A
  legacy entry whose week/day is missing still shows with its week label and
  `dayKnown`-style fallback (date comes from `entry.day` when present), but a
  store entry the snapshot copy cannot express at all only yields the defensive
  `Selection saved` notice until T15's retained-identity metadata lands. T15
  must keep the bar non-empty and must not let a refresh silently drop it.
- **T16 expiry.** `openSummarySheet()` reads the last settled snapshot, so an
  expired/unrenderable saved entry can still be inspected/removed; T16 owns the
  live clock recheck, disabled Submit and "expired but inspectable" guidance.
  The summary never reclassifies cells or rebuilds weeks itself.
- **T17/T18 submit.** The bar adds no Submit handler, latch or confirmation
  path; the relocated `#submitRequestBtn` keeps its existing behaviour and
  T17/T18 remain the only identity/busy/duplicate owners.
- **Multiple-entry removal loop.** Removing one legacy entry closes the sheet
  and refreshes; the bar count then reflects the committed store. Re-opening is
  required for the next removal (one sheet flow, no optimistic multi-delete and
  no loop that could remove an unrelated key).

T12 checkbox remains for the parent to mark; page changelog, full PHP gates and
the sanctioned server restart stay T24/T29.

## Apply T13 — keyboard isolation, sheet focus and polite announcements — 2026-11-11

Delegated T13 only, after completed T01–T12 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership-border hint, retained
blue selection outline). This task adds the page keyboard guards, the sheet's
initial/return focus and one polite live region. It does **not** add expiry or
request guards (T15–T18), measured sticky/44px geometry (T14) or full visual
polish. Baseline `28f9f4d`; the working tree still carries the uncommitted
T03–T12 lane and other sessions' dirty files (`docs/demo-db-data-changes.md`,
`resources/views/livewire/replacement-home.blade.php`), none of which were
touched.

### Changed anchors

- `public/js/arrangement-mobile.js:258–263` — T13 constants: the single live
  region hook (`[data-arrangement-mobile-live]`); no second key handler exists
  in the adapter.
- `:332–344` — focus state (`infoFocusDescriptor`, `infoFocusPending`,
  `infoDeferFocus`, `focusAfterRender`) and announcement state
  (`lastPresentedDayIndex`, one-shot `selectionTotalBaselineSet`,
  `lastSelectionTotal`).
- `:747–800` — `render()` now resolves the presented day after a pending
  reveal, remembers it, calls `noteSelectionChange(snap)`, announces a real day
  change, and runs the deferred post-render focus restore. Initial hydration
  only sets the selection baseline; identical refresh/resize/empty renders are
  silent.
- `:952–977` — `revealTarget()` records the presented day and announces one
  `Showing …` message when an explicit reveal changes it.
- `:1275–1297` — `onDayClick()` records/announces only a real day change.
- `:1469–1543` — `openInfo()` records the opener identity descriptor, marks one
  pending return, clears any deferred restore, and focuses the visible Close
  button after `showModal`; a failed `showModal` clears the pending state.
- `:1548–1558` — `closeInfo()` still calls the explicit idempotent restore only
  while a return is pending.
- `:1560–1701` — `focusDescriptorFor()`, `focusableNode()`, `currentDayButton()`,
  `firstVisibleRow()`, `resolveFocusTarget()`, `focusInfoClose()`,
  `onInfoClosed()` and `restoreFocusAfterRender()`. Descriptors cover summary,
  legend, Tip, row and span by day+hour; fallback order is summary opener →
  current day → first visible row → legend. Restoration is guarded by
  `criticalModalOpen()` and happens exactly once.
- `:1704–1755` — `announce()`, `announceDay()` and `selectionTotalOf()` /
  `noteSelectionChange()`. Every announcement is `textContent` only; repeated
  identical text is cleared first so a reader still speaks it; only a changed
  saved total after the initial snapshot is announced.
- `:1853–1866` — `removeSavedSelection()` defers focus return when closing the
  sheet for a valid keyed removal, so the settled render can focus the rebuilt
  control. The existing single-shot guard/port/refresh order is unchanged.
- `:2267–2273` — `isInfoOpen()` returns whether the one dialog is open.
- `:2290–2294` — the existing one native dialog `close` listener binding is
  unchanged; no resize path adds another.
- `:2358–2366` — `destroy()` clears descriptor/pending/deferred focus and the
  live region text alongside the existing listener teardown.
- `:2383` — the controller surface adds only `isInfoOpen` beside every prior
  port.
- `resources/views/livewire/replacement-arrangement.blade.php:3182–3215` —
  `handleKeyDown()` keeps the critical `#confirmModal` Escape and help-overlay
  Escape branches first, then the `isInfoOpen()` guard, the existing native
  form-control guard, and the `isMobile()` guard immediately before the grid
  shortcut switch. Desktop with neither state follows the original path.
- `:3290–3304` — one `blockSharedWeekShortcuts()` function: it ignores every
  key except `[`/`]`, and only while mobile or the info dialog is open does it
  `preventDefault()` + `stopImmediatePropagation()` in capture phase.
- `:5314–5318` — the blocker is registered once at boot, between the page
  handler and the unchanged `initWeekKeyboardShortcuts()` call. No resize
  listener registers it again.
- `:1307–1330` — minimal page-local T13 CSS inside the existing
  `@media (max-width: 768px)` block: visually hidden live region and a
  `focus-visible` ring on mobile buttons/selects/summary/tabindex controls using
  `--color-primary`. T14 still owns 44px, sticky clusters and measured insets.
- `resources/views/partials/ui-arrangement-mobile.blade.php:28` — exactly one
  `<p class="arrangement-mobile-live" data-arrangement-mobile-live
  aria-live="polite" aria-atomic="true">` inside the mobile root.

### Keyboard, focus and announcement behaviour

| Input | Behaviour |
|---|---|
| Mobile active, no sheet: Arrow keys/Enter/Space on a mobile control | Page handler returns without `preventDefault`; the real button/select keeps native activation and scrolling. No hidden cell focus/selection. |
| Mobile active, no sheet: `[`/`]` | Capture blocker prevents and stops the shared `initWeekKeyboardShortcuts` listener; the page handler also returns before its week shortcut. |
| Mobile active, no sheet: `?`, Ctrl/Meta+Z, Ctrl/Meta+1–3 | Page handler returns before the switch; help/undo/venue shortcuts do not run. |
| Sheet open (mobile or desktop transition) | Page handler returns after the critical branches; `[`/`]` blocked in capture. Native Escape cancels the `<dialog>`; critical-modal Escape still closes `#confirmModal` first. |
| Desktop, no sheet | Critical/help branches and the whole grid/undo/help/week/venue switch run exactly as before; capture blocker ignores the keys. |
| Sheet opens | Focus goes to the visible Close button; the named dialog keeps native Tab containment. |
| Sheet closes (Close, Escape, backdrop) | Descriptor resolves the current summary/legend/Tip/row/span; otherwise summary opener → current day → first visible row → legend. Exactly once, including when the native `close` event also fires. |
| Remove | Sheet closes first; focus return waits for the coalesced settled render, then targets the rebuilt summary/day/current row and never the disconnected opener. |
| Critical confirmation | Closing/refreshing the info sheet does not focus anything while `#confirmModal`/another dialog is open. |
| Initial hydration / resize / identical refresh | No announcement and no focus movement. |
| Day change (day button or explicit reveal) | One `Showing <weekday>, <date>` message. Re-tapping the viewed day says nothing. |
| Saved total changes after the first snapshot | One `Selection saved.` or `Selection removed.` message; later renders with the same total stay silent. Invalid/no snapshot changes nothing. |
| Teardown | Descriptor/pending state and live text cleared; the one native close listener and both root click listeners are removed. |

All announcement/day text is written with `textContent`; day labels containing
literal markup remain text. No new dialog, booking write, submit path, timer or
shared helper was added.

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (4088 lines) → `node --check` — pass.
- `/tmp/opencode/t13-adapter-smoke.cjs` — dependency-free Node `vm` + DOM stub,
  **46 passing assertions**: `isInfoOpen` false/true/false; visible Close focus
  after `showModal`; exactly-once return when the native close event repeats;
  rebuilt summary opener receives focus while the stale node does not; Remove
  defers focus until the settled render and then focuses the current day when
  the entry disappears; row focus returns to the same day+hour rebuilt row;
  missing legend falls back to the rebuilt summary; an open critical modal
  suppresses focus return; initial hydration writes nothing to the live region;
  one day change = one `Showing Wed, 18 Nov 2026` write and re-tapping is
  silent; identical refreshes write nothing; add/remove announce once each;
  literal markup stays text with no created element; `destroy()` clears the
  live text and listeners.
- `/tmp/opencode/t13-page-static.cjs` — **71 passing assertions**: the real
  `handleKeyDown()` and `blockSharedWeekShortcuts()` extracted from the Blade
  source and executed against stubs — mobile returns without preventDefault for
  arrows/Space/`?`/`[`/Ctrl+Z/Ctrl+1 and never focuses/toggles/navigates the
  hidden grid; info-open returns without preventing native keys; desktop still
  prevents arrows, navigates brackets, undoes, selects the Nth venue and opens
  help; critical-modal Escape wins even on mobile with a sheet open; help Escape
  and native select Escape remain; the capture blocker stops only `[`/`]` while
  mobile/info and ignores desktop/no-sheet/other keys. Source order is
  critical modal → help → info guard → form guard → mobile guard → switch; one
  capture registration exists between `handleKeyDown` and
  `initWeekKeyboardShortcuts()`. The partial has exactly one polite atomic live
  region; the adapter exposes `isInfoOpen`, one announce mechanism, descriptor
  resolution, deferred Remove focus, one close listener and one `showModal`
  path, with no HTML writes/submit path; T13 CSS stays inside the mobile media
  block, uses only theme tokens and adds no 44px/sticky/dvh geometry.
- Lane regressions re-run against the T13 adapter: t04 adapter/utils `PASS`,
  t04 page snapshot `PASS`, t05 coalescing `PASS`, t06 adapter `ALL PASS`,
  t09 adapter `80/80`, t10 adapter `109/109`, t10-regression-t07 `75/75`,
  t10-regression-t08 `136/136`, t11 adapter `84/84`, t11 page-static `80/80`,
  t12 adapter `97/97`, t12 page-static `68/68`,
  t12-regression-t09-page `33/33`. One static copy pinned the pre-T13
  controller return list and was re-run with only that expectation updated, not
  hidden as a product failure:
  - `t11-regression-t08-page` asserted the exact return surface without
    `isInfoOpen` → updated copy `t13-regression-t08-page.cjs` → `39/39`.
  Older direct copies `t07-adapter`, `t08-adapter`, `t09-page-static`,
  `t10-regression-t09-page` and `t11-regression-t09-page` still assert
  pre-T09/T10/T11/T12 baselines (listener budget, "no `event-selection`",
  "no summary-host CSS", raw `detailText`, old return/class snapshots) and are
  superseded by the maintained regression copies above; they were not treated
  as T13 regressions and were not rewritten.
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` — pass.
  No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push. `theme.css` and `ui-common.js` remain untouched.

These are static/stub receipts, **not** browser, visual, touch, screen-reader or
device evidence. Nothing here proves real `<dialog>` top-layer containment,
focus rings in both themes, that a screen reader speaks either announcement,
actual 768/769px transition behaviour, or that a physical Escape/backdrop close
returns focus on a device.

### Hazards carried forward

- **Two keyboard owners.** The page handler is guard-only now; the shared
  `ui-common.js` `[`/`]` listener is stopped solely by the page's capture
  blocker. T19 must not remove or duplicate that blocker, and any future shared
  shortcut owner needs the same capture treatment. T23 should exercise both
  owners with and without a sheet and confirm native Escape still reaches
  `<dialog>`/`#confirmModal`.
- **Rebuilt opener identity.** Focus return relies on descriptors, not node
  identity. Rows/spans are identified by `data-mobile-day` + `data-mobile-hour`;
  summary/legend/Tip resolve through the current host. If T14 changes row markup
  or T12's summary host, `focusDescriptorFor()` / `resolveFocusTarget()` must be
  updated in the same change. T15 expiry must not leave `focusAfterRender`
  pointing at a control it removes without a fallback.
- **Remove deferral.** Focus restoration after Remove is intentionally delayed
  to the settled render. A future T15/T16 path that closes the sheet for a
  different reason must not set `infoDeferFocus`; otherwise the one pending
  restore can be lost if no render follows.
- **Announcement baseline.** The first snapshot with a `selections` object sets
  the total baseline. T15/T16 retained/expired entries that change
  `totalSlots` during a clock reconciliation will announce as a selection
  add/remove unless those tasks explicitly suppress non-user changes. Day
  announcements are keyed to original day index, so a same-weekday week change
  does not speak a new date.
- **T14 geometry.** T13 adds only the hidden live region and focus ring. T14
  still owns 44px targets, sticky clusters, sheet `85dvh`/sticky Close,
  safe-area padding, toast offsets and `scroll-margin`. The focus ring uses
  `--color-primary`; keep it visible in both themes and do not remove it while
  restyling.
- **Critical-modal timing.** `closeInfo()` can run just before an existing
  critical modal is displayed; the immediate return may briefly focus the
  opener before the modal's own focus path runs. The late native `close` event
  is made idempotent, and `criticalModalOpen()` suppresses restoration once a
  modal is visible. T17/T18 must keep the T11 `closeInfo()` hooks before those
  openers and must not add a second focus owner.

T13 checkbox remains for the parent to mark; page changelog, full PHP gates and
the sanctioned server restart stay T24/T29.

## Apply T14 — sticky/header/safe-area responsive styling — 2026-11-11

Delegated T14 only, after completed T01–T13 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership hint, retained blue
selection outline). This task completes the mobile composition: header in
document flow, compact expandable original context, sticky venue/week/day
cluster, wrapping tools row, 48px timeline rows, fixed measured action bar,
safe-area insets, bottom-sheet geometry, toast-above-bar placement and
focus/reduced-motion polish. It does **not** add expiry retention/clock rebuild
(T15–T16), submit identity/busy guards (T17–T18), final teardown review (T19) or
browser/visual verification (T21–T28). Baseline `28f9f4d`; the working tree
still carries the uncommitted T03–T13 lane and other sessions' dirty files
(`docs/demo-db-data-changes.md`, `resources/views/livewire/replacement-home.blade.php`),
none of which were touched. `theme.css`, `ui-common.js`, the shared layout, the
frozen artifacts and the partial were not modified.

### Changed anchors

- `public/js/arrangement-mobile.js:265–277` — T14 constants: the two measured
  hosts (`[data-arrangement-mobile-controls]`,
  `[data-arrangement-mobile-actions]`), the frozen context-summary hook, the
  page-root property names and the short no-subject guidance. No booking,
  calendar or occurrence constant is added.
- `:367–373` — layout-measurement state: one observer reference, one resize
  fallback flag and the last two written values (so a settled render/observer
  callback only writes a real change).
- `:514–517` — `enterMobile()` measures the relocated sticky cluster and fixed
  bar immediately after `renderFromCache()`/Tip suppression, then attaches the
  one observer (or its resize fallback).
- `:565–567` — `exitMobile()` detaches the observer/fallback and removes both
  body properties before the mobile body class is dropped.
- `:816–818` — `render()` calls `renderContextSummary(snap)` in the same settled
  turn as the timeline/summary, so the collapsed context follows every committed
  snapshot (including breakpoint re-render).
- `:833–835` — every settled mobile render re-measures the two owned hosts after
  the final DOM work (`restoreFocusAfterRender`), covering summary/timeline
  height changes.
- `:2255–2301` — T14 context block: `contextSubjectText()`,
  `contextSummaryText()` and `renderContextSummary()`. Subject text comes from
  `snapshot.original` code/name/type (with the snapshot's own subject control
  text only as a same-snapshot fallback); the slot line is gated by
  `original.hasSlot === true` and uses the snapshot's real weekday/date and 12h
  `startTime`/`endTime`. No occurrence resolution, no store read, no write.
- `:2306–2403` — measured-inset block: `pageBody()`, `bodyStyle()`,
  `measuredHeight()` (`getBoundingClientRect`, ceil, positive only),
  `measureInsets()`, `attachInsets()` (exactly one `ResizeObserver`, observing
  the controls and the bar; window `resize` only when the observer API is
  absent), `clearMeasuredInsets()` and `detachInsets()`.
- `:2489–2491` — `destroy()` also calls the idempotent `detachInsets()` so a
  destroy while desktop is active cannot leave measurement state behind.
- `:2568–2572` — tail ownership comment names T14; the controller surface is
  unchanged (`closeInfo` only). No new port, click listener, timer or submit
  path exists.
- `resources/views/livewire/replacement-arrangement.blade.php:1331–1578` — the
  page-local T14 CSS inside the existing `@media (max-width: 768px)` block. Every
  rule is scoped with `body.arrangement-mobile-mode`; nothing above the mobile
  block (desktop rules) changed.
- `resources/views/partials/ui-arrangement-mobile.blade.php` — unchanged. The
  frozen context/controls/tools/timeline/actions/dialog hosts were sufficient;
  no second control, host, dialog or bar was added.

### Context summary behaviour

| Snapshot | Collapsed `<summary>` text |
|---|---|
| Subject + real original slot | `CODE — Name (Type) · Wed, 18 Nov 2026 · 10:00 AM ~ 12:00 PM` (12h times exactly as the snapshot's `startTime`/`endTime`; weekday falls back to date, date to weekday) |
| Subject, `hasSlot !== true` | `CODE — Name (Type)` only |
| Subject but no usable date/time text | Subject only; no invented slot |
| `original.hasSubject !== true` (or no `original`) | Exact short guidance `Select a subject and original slot` |
| Subject text absent while the flag is true | Falls back to the same-snapshot subject control text, else the guidance |

All values are `textContent` only; no markup is parsed or created. Expanded
facts remain the existing `#subjectInfo` strip plus the relocated subject/slot
controls; the strip now wraps/compacts inside the details and no fact is hidden.

### Measured inset behaviour

| Moment | Behaviour |
|---|---|
| Enter mobile | After relocation/show: measure both hosts, write `--arrangement-mobile-sticky-height` / `--arrangement-mobile-bar-height` as `Npx` on `<body>` (page root, so the layout-level toast can read the same value), then attach one `ResizeObserver` to both hosts |
| Settled refresh / summary or timeline change | `render()` re-measures after the final DOM work; the observer also fires on real height changes. Values are written only when they differ |
| Unmeasurable/zero height | No property is written; the CSS fallbacks (`0px` sticky, `112px` bar) stay in force instead of a false `0px` |
| Window resize | Only the fallback listener when `ResizeObserver` is unavailable; it is bound once |
| Breakpoint exit | Observer disconnected (or resize listener removed) and both body properties removed before desktop styles are restored |
| Destroy (mobile or desktop) | The same idempotent detach/clear runs; nothing is written to `documentElement` and no timer/interval is added |

The page inset (`#arrangementMobile` bottom padding), row/span
`scroll-margin-top`/`scroll-margin-bottom` and the page-local toast `bottom` all
consume the measured body properties. The fixed bar keeps safe-area padding and
may grow/wrap; the page reserves its real height + safe area + 16px.

### Composition and touch geometry

| Area | Applied |
|---|---|
| Header | `.app-container` top padding 0; `.top-bar` static with top safe-area padding; Back static/`transform:none`/44px; title `clamp(16px,4.6vw,20px)` wrapping; logo 28px. Theme toggle keeps its existing mobile hiding |
| Context | Token surface/outline `<details>`, 44px summary, padded facts, wrapping conflict strip (`cs-head`/`cs-seg`/`cs-badge` no longer nowrap, badge loses auto margin) |
| Sticky cluster | Controls sticky `top: 0`, `z-index: 30`, `--color-bg`, 8px/12px padding, outline divider; day picker single-row internal horizontal scroll with 44×44 buttons; venue/week/slot/Earliest/arrow controls ≥44px and full width; slot/venue dropdown rows ≥44px |
| Tools | Row wraps with 8px gap/padding; legend, Earliest and Tip are ≥44px targets |
| Timeline | `--arrangement-mobile-row-height: 48px`; T09 single-column grid, `[hidden]` guard, original-hour hooks and span grid-row contract untouched; rows/span grow for text |
| Action bar | Fixed bottom, `z-index: 40`, token surface, outline top, safe-area padding, shadow, margin 0; 44px summary and original Submit; column layout can wrap |
| Toast | `body.arrangement-mobile-mode .toast-bar` bottom = measured bar + safe area + 12px (page-local position only; shared toast engine untouched) |
| Sheet | Full-width bottom sheet (`position: fixed`, `inset: auto 0 0`, 100% width, 85dvh, margin 0, 18px top radii, safe-area bottom padding, internal `overflow-y: auto`); sticky token-surface header; Close and sheet actions ≥44px; T11 text/swatch styles retained, Pending/Conflict still borderless and no ownership hint added |
| Focus/motion | T13 `--color-primary` focus ring retained; nested `prefers-reduced-motion` drops page-local transition/animation durations and scroll behaviour; toast animation shortened. Tokens only — no literal hex/rgb |

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (244,221 chars) → `node --check` — pass.
- `/tmp/opencode/t14-adapter-smoke.cjs` — dependency-free Node `vm` + DOM stub
  (rects, body style shim, fake `ResizeObserver` and window resize registry),
  **80 passing assertions**: the 46 T13 assertions unchanged, plus the compact
  summary (subject/date/time, no subject, no slot, slot flag without date/time),
  enter-time measurement of both hosts, one observer watching both, observer
  callback writing ceil heights, settled-render re-measure, no second observer,
  exit removing both body properties and disconnecting, re-entry creating one new
  observer, destroy disconnecting/clearing, zero/NaN heights leaving the CSS
  fallback, the `ResizeObserver`-absent resize fallback binding once, re-measuring
  and unbinding on exit, and no `documentElement` write.
- `/tmp/opencode/t14-page-static.cjs` — **73 passing assertions**: T14 CSS is
  inside the mobile block and `body.arrangement-mobile-mode`-scoped; header flow,
  Back/title/logo rules; 44px context summary and wrapping conflict strip with no
  hidden facts; sticky controls (`z-index:30`, token background/border); internal
  day-picker scroll + 44px buttons; 44px venue/week/tools/legend/Tip/Earliest/
  summary/Submit/sheet actions; 48px row variable; measured scroll margins; fixed
  bar (`z-index:40`, safe-area padding, shadow); measured page inset; toast above
  the bar; 85dvh bottom sheet with sticky header; reduced-motion query; retained
  focus ring; no literal hex/rgb; no `.timetable`/grid restyle; `theme.css`,
  `ui-common.js` and the shared layout untouched; the partial still has one dialog
  and the same frozen hosts; the adapter has one context renderer, one observer
  construction, both body properties written/removed, no interval and no extra
  timer/listener budget beyond the documented resize fallback.
- Lane regressions re-run against the T14 adapter: t04 adapter/utils `PASS`,
  t04 page snapshot `PASS`, t05 coalescing `PASS`, t06 adapter `ALL PASS`,
  t09 adapter `80/80`, t10 adapter `109/109`, t10-regression-t07 `75/75`,
  t10-regression-t08 `136/136`, t11 adapter `84/84`, t11 page-static `80/80`,
  t12 adapter `97/97`, t12 page-static `68/68`, t13 adapter `46/46`,
  t13-regression-t08-page `39/39`, t11-regression-t10-page `41/41`.
  Three maintained copies carried pre-T14 expectations and were re-run with only
  those expectations updated, not hidden as product failures:
  - t13-page-static bounded its T13 slice at the end of the stylesheet and
    therefore counted T14's deliberate 44px/sticky/dvh rules. Updated copy
    `t14-regression-t13-page.cjs` (slice ends at the T14 anchor) → `71/71`,
    proving T13 itself still adds none.
  - t12-regression-t09-page pinned a five-`addEventListener` budget; T14 adds
    exactly one optional window `resize` fallback listener. Updated copy
    `t14-regression-t09-page.cjs` → `34/34` (asserts the 6th is the single
    resize fallback).
  - The older direct copies `t11-regression-t08-page` (pre-T13 controller return
    list) and `t13-page-static` (pre-T14 geometry slice) are superseded by
    `t13-regression-t08-page` and `t14-regression-t13-page` respectively, as
    already recorded in the T12/T13 receipts; they were not treated as T14
    regressions.
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` — pass;
  the untracked adapter/partial/review-log also pass
  `git diff --no-index --check /dev/null <file>`. No `--env=testing`, DB
  access/write, browser, Playwright, server restart, staging, commit or push.

These are static/stub receipts, **not** browser, visual, touch, screen-reader or
device evidence. Nothing here proves real sticky/`dvh` behaviour, the actual
measured values on a phone, safe-area insets, sheet top-layer scrolling/sticky
heading, 320/390/768/769px rendering, text zoom, rotation, contrast in both
themes or that a screen reader keeps the focus ring/announcements usable.

### Hazards carried forward

- **T15/T16 must re-measure after their own DOM changes.** The observer watches
  the sticky cluster and the bar, so bar summary copy changes and control
  height changes are covered; a future path that changes either host while
  mobile is active outside a settled render should call the existing
  `measureInsets()` rather than adding a second observer or a timer.
- **T19 teardown.** `exitMobile()`/`destroy()` disconnect the single observer
  (or remove the resize fallback) and remove both body properties. Any future
  path that calls `enterMobile()` outside `mount`/matchMedia must keep the
  `mobileActive` guard; repeated attach/detach is idempotent by design.
- **Row markup contract.** T14 only adds CSS to rows/span. The T09 hooks
  (`data-mobile-row`, `data-mobile-hour`, `data-mobile-day`, `data-mobile-span`,
  `data-mobile-state`), the single-column grid and the `[hidden]` guard are
  unchanged, so T13 focus descriptors, T08 reveal and T11/T12 sheet routing keep
  working.
- **Context summary is display-only.** It reads `snapshot.original`; T15/T16
  retained/expired entries do not change it, and it never resolves a second
  occurrence or selects a slot. The relocated `#subjectInfo` strip is the
  expanded truth.
- **z-index stack.** Sticky controls 30, fixed bar 40, shared toast 200, the
  one information `<dialog>` in the top layer. T17/T18 must not add another
  fixed layer between the bar and the toast without re-checking the toast
  offset and the page inset.
- **Page root property scope.** The two `--arrangement-mobile-*` properties are
  written on `<body>` only while mobile and removed on exit/destroy; no
  `documentElement` variable is used. A future page-local rule must not rely on
  them outside the mobile mode class.
- **Toast rule is page-local.** Only `body.arrangement-mobile-mode .toast-bar`
  moves; the shared `.toast-bar` and its 640px behaviour are untouched and other
  pages keep their position.
- **Browser evidence is still owed.** The measured-height mechanism, sticky
  offsets, bottom-sheet scrolling, safe areas and 44px targets need T23/T25–T28
  at 320/390/768/769px, both themes, text zoom and rotation before any
  accessibility/visual claim.

T14 checkbox remains for the parent to mark; page changelog, full PHP gates and
the sanctioned server restart stay T24/T29.

## Apply T15 — preserve unrenderable saved entries through save/load — 2026-11-11

Delegated T15 only, after completed T01–T14 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership hint, retained blue
selection outline). Baseline `28f9f4d`; the working tree still carries the
uncommitted T03–T14 lane plus other sessions' dirty files
(`docs/demo-db-data-changes.md`, `resources/views/livewire/replacement-home.blade.php`),
none of which were touched. Only
`resources/views/livewire/replacement-arrangement.blade.php` changed in this
task. `public/js/arrangement-mobile.js`, the partial, `theme.css`, `ui-common.js`,
the shared layout, the backend and the frozen artifacts were not modified.

### Changed anchors

- `resources/views/livewire/replacement-arrangement.blade.php:1942–1950` —
  `retainedUnrenderableKeys`, the page-owned identity map. It is reconciliation
  metadata only; `selectedSlotsByVenue` remains the sole selection store.
- `:1952–2001` — the six T15 helpers: `sameBlock` (exact day/start/end identity,
  invalid values fail closed), `key` (`"<venue>::<week>"`, the same key shape as
  the adapter's `savedEntries`), `markRetained`, `isRetained` (optional block
  argument), `clearRetained`, `clearAllRetained`.
- `:2003–2018` — `saveCurrentWeek()`. A real pointer still saves normally and
  clears its marker. A null pointer now returns without writing when the
  existing store entry matches its retained marker; every other null save is the
  original `null`. A missing entry is never recreated from metadata. No mobile
  check exists in this path, so the protection survives a resize into desktop.
- `:2020–2059` — `loadCurrentWeek()`. Restoration now checks every covered
  canonical cell for `cell-available`/`cell-selected`. A fully available span
  restores through the existing `renderMergedBlock` and clears the marker. An
  unrenderable span (expired/too-soon/Sunday/holiday/blocked/missing cell)
  leaves `selectedBlock` null, keeps the store untouched, marks the exact saved
  identity retained and never calls `deselectBlock()`/`clearVisualSelection()`
  or paints old cells green.
- `:2061–2116` — `reconcileRetainedSelections()`, the closure-scoped T16 hook
  (no global API). It scans every stored entry with real `weekData`/Sunday/
  holiday/`isSlotTooSoon` results (helper missing = fail closed), marks
  date-ineligible entries retained, invalidates only a matching current
  `selectedBlock` pointer without repainting, keeps eligible markers until
  canonical loading restored that identity, drops a marker whose stored
  identity changed, and prunes markers whose entry is gone. It starts no timer,
  listener, rebuild or save.
- `:2376–2377` — `deselectBlock()` clears the current key marker with its
  existing store null write.
- `:2522–2523` — a successful `selectBlock()` clears the marker it replaces.
- `:3269` — successful submission cleanup clears every marker after the store
  wipe (conflict/failure paths keep it).
- `:3481` — the bfcache store wipe also clears markers (defensive hygiene; the
  undoable `clearAll()` path deliberately preserves them so its undo cannot
  lose retention).
- `:4399` — `discardAllSelections()` (the confirmed subject/slot/venue discard)
  clears every marker.
- `:4466–4469` — `removeSavedBlock()` clears the exact key marker before its
  existing early return, so the off-view/null-pointer removal path also retires
  metadata without repainting expired cells.

### Protection and clear behaviour

| Event | Store | Marker |
|---|---|---|
| Null pointer, saved entry matches its marker (week hop, venue change, desktop resize) | preserved unchanged | kept |
| Null pointer, no marker / different identity | original `null` save | none added |
| Pointer present, valid save | entry saved | current key cleared |
| `loadCurrentWeek()` fully restores | entry intact | cleared |
| `loadCurrentWeek()` cannot restore any covered cell | entry intact, pointer null | exact identity marked |
| Reconcile: date ineligible | never rewritten | marked; matching pointer nulled, cells untouched |
| Reconcile: date eligible but not restored yet | never rewritten | kept |
| Reconcile: eligible + matching restored pointer | never rewritten | cleared |
| Reconcile: marker for a changed/missing entry | never rewritten | pruned (no resurrection) |
| Keyed Remove (visible or off-view/null pointer) | entry nulled by the existing path | cleared |
| `deselectBlock()` | existing null write | current key cleared |
| `discardAllSelections()` / confirmed subject/slot/venue change | existing wipe | all cleared |
| Successful submission | existing wipe | all cleared |
| `clearVisualSelection()` / ordinary navigation | preserved | preserved |

No second removal path, no copied selection store and no UI change was added;
the T12 summary still reads entries and its existing notice still appears when
a retained entry cannot be expressed.

### Checks actually run (static/stub only)

- `node --check` on the extracted page `@section('page-scripts')`
  (252,910 chars) — pass.
- `/tmp/opencode/t15-retention-smoke.cjs` — **68 passing, 0 failing**,
  dependency-free Node `vm` stub of the extracted T15 block (DOM body/cell
  stubs, real `isSlotTooSoon` stand-in). It proves: exact/mismatched identity
  matching; key normalisation; invalid identity refused; null-pointer save
  preserves only a matching retained entry and otherwise still writes null;
  pointer save clears; no resurrection from an empty store; full/partial/
  missing-cell load outcomes; restore clears the marker only after success;
  empty saved week clears a stale marker; reconcile marks too-soon, holiday and
  Sunday entries, uses the real helper, nulls only a matching pointer, leaves
  the store and DOM untouched, keeps eligible-but-unrestored markers, clears
  only a matching restored pointer, prunes missing/changed identities, and
  contains no timer, listener, build/refresh call, green repaint, global export
  or mobile-only branch.
- Lane regressions re-run against the T15 page: t04 adapter/utils `PASS`,
  t04 page snapshot `PASS`, t05 coalescing `PASS`, t06 adapter `ALL PASS`,
  t09 adapter `80/80`, t10 adapter `109/109`, t10-regression-t07 `75/75`,
  t10-regression-t08 `136/136`, t11 adapter `84/84`, t11 page-static `80/80`,
  t12 adapter `97/97`, t12 page-static `68/68`, t13 adapter `46/46`,
  t13-regression-t08-page `39/39`, t11-regression-t10-page `41/41`,
  t14 adapter `80/80`, t14 page-static `73/73`,
  t14-regression-t09-page `34/34`, t14-regression-t13-page `71/71`.
- The same three older stale copies recorded in the T12–T14 receipts still
  fail for their already-documented pre-T15 reasons, not for T15: `t13-page-static`
  (its T13 slice still counts T14's 44px/sticky rules; superseded by
  `t14-regression-t13-page`), `t12-regression-t09-page` (five-listener budget;
  superseded by `t14-regression-t09-page`), plus the older
  `t11-regression-t08-page`/`t13-page-static` pair. Additional pre-existing
  stale copies whose expectations predate T11–T14 (`t08-page-static`,
  `t09-page-static`, `t10-page-static`, `t11-regression-t09-page`,
  `t09-regression-t07`, `t09-regression-t08`, `t07-adapter-smoke`,
  `t08-adapter-smoke`) were also observed failing on adapter/CSS counts that
  T15 did not touch; their maintained successors pass above. They were not
  treated as T15 regressions.
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` — pass;
  the untracked `.sdd` copy and adapter/partial also show no whitespace errors
  via `git diff --no-index --check /dev/null <file>`.
- No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push. No demo data was read or written.

These are static/stub receipts, **not** browser, clock, resize, visual or
device evidence. Nothing here proves a real date rollover, that a live resize
into desktop preserves a retained entry, or that no future call site overwrites
the store outside the audited save/load/remove hooks.

### Hazards carried forward

- **T16 wiring.** `reconcileRetainedSelections()` is closure-scoped; T16 must
  expose/call it through its own page port, then rebuild the canonical table so
  `loadCurrentWeek()` performs the actual restore/retain decision. Reconcile
  alone only nulls a stale matching pointer and never repaints; do not assume
  the merged DOM block is gone until the rebuild runs.
- **T16 eligibility clearing.** A marker is cleared only by successful canonical
  restoration (`loadCurrentWeek`) or a matching restored pointer seen by
  reconcile. T16 must call reconcile **before** the rebuild and must not clear
  markers merely because the clock says the date is eligible again.
- **T16 must not rewrite the store while reconciling.** Use `markRetained`/
  `clearRetained` only; a `selectedSlotsByVenue[...] = null` in the clock path
  would defeat the protection exactly when the pointer is null.
- **T17/T18.** Submit guards must reconcile and then reject expired/absent saved
  entries; a successful submission may keep the existing `clearAllRetained()`
  cleanup, but a rejection/expiry must preserve both the store entry and its
  marker. Do not clear metadata on the mobile confirmation cancel path.
- **T19 teardown.** Retention metadata is in-memory page state like the store;
  a teardown that wipes the store (bfcache) already clears markers. A future
  teardown path that clears only the store must add `clearAllRetained()` or the
  prune paths will leave stale markers (still non-resurrecting, but noisy).
- **Boot ghost retract.** The existing boot-time `savedGhost` retract still
  nulls its entry directly. It runs before any marker can exist at boot, so it
  was left untouched; a future path that reuses that retract after a marker is
  set must check `isRetained` first or it will erase a retained entry.
- **Undo.** `undoSelection()` still uses its existing too-soon-only gate and was
  not widened; it may leave a marker in place for an off-view restored entry
  until load/reconcile settles it. Do not add a holiday/Sunday bypass to undo
  as part of T16–T19; T22 should test that the existing gates still hold.
- **Retained entries still count.** A retained entry remains in `selectedSlotsByVenue`, so it still contributes to `getGlobalTotal()` and the existing selection budget, and the summary lists it. T15 deliberately did not change that accounting; T16/T17 must show remove-and-choose guidance and disable Submit for an expired entry rather than silently exceeding the budget or dropping the entry.
- **Adapter untouched.** T12/T14 read entries and the bar notice; T15 adds no
  adapter branch, timer, listener or removal control. T16's timer belongs in
  the adapter; it must call the page port and must not read
  `retainedUnrenderableKeys` directly.
- **Browser evidence is still owed.** The real date boundary, week-option
  rebuild, resize into desktop and expired-entry removal belong to T22/T26 and
  were not exercised here.

T15 checkbox remains for the parent to mark; T16–T19, T20+ tests, page
changelog, full PHP gates and any commit/push stay with their own tasks.

## Apply T16 — live clock reconciliation and eligible week-option rebuilding — 2026-11-11

Delegated T16 only, after completed T01–T15 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership-border hint, retained
blue selection outline). Baseline `28f9f4d`; the working tree also carries the
uncommitted T03–T16 lane, a later committed UI commit (`5b0b9ff`) and other
sessions' dirty files (`docs/demo-db-data-changes.md`,
`resources/views/livewire/replacement-home.blade.php`), none of which were
touched. This task adds the page-owned clock pass, the eligible week-option
rebuild, the read-only retention metadata and the mobile retained-entry
guidance/lock. It does **not** add the final Yes/busy submission guards
(T17/T18), full teardown (T19), tests (T20+) or any browser/DB work.

### Changed anchors

- `resources/views/livewire/replacement-arrangement.blade.php:2125–2249` —
  the T16 page block inside the closure:
  `mobileClockReconciling` / `mobileClockLastDateKey` / `mobileClockTimer`,
  `mobileLocalDateKey()` (LOCAL `YYYY-MM-DD`), `eligibleWeekIndexes()`
  (absolute indexes from the real `weekHasBookableSlot`),
  `rebuildMobileWeekOptions()` (shared `populateWeekSelect`, `ranges:false`,
  live `weekFilter`; zero weeks -> one disabled `No bookable weeks`
  placeholder), `reconcileMobileClock()` and `scheduleMobileClock()`.
- `:3266–3273` — `proceed()` starts with the mobile-only reconcile call.
  The existing total check, confirmation summary, Yes callback and Livewire
  call are unchanged (T17/T18 own the final checks).
- `:6086–6088` and `:6105–6112` — `readArrangementSnapshot()` now exposes
  `retention: { any, count, keys }` from `Object.keys(retainedUnrenderableKeys)`
  only; the marker map/store is never returned or mutated.
- `:6160–6176` — `chooseAvailable()` validates the indexes, reconciles on
  mobile before resolving the canonical cell, refuses the tap when the clock
  pass moved the committed week, then keeps the exact existing
  `#tableBody td[data-day][data-hour] .cell-content` + one `toggleCell()` call.
- `:6234–6244` — at the closure tail, after `arrangementMobile.mount()`, one
  `visibilitychange` listener (visible only), one `window` `focus` listener and
  one `scheduleMobileClock()` call. No other timer/listener was added.
- `:1287–1292` — page-local mobile CSS for
  `.arrangement-mobile-summary--retention` only (warning tokens; no hex/rgb, no
  shared CSS change).
- `public/js/arrangement-mobile.js:280–287` — T16 constants (exact guidance
  copy); `:387` — `retentionSubmitForced` (true only while this adapter forced
  the original Submit); `:550` — desktop exit releases that lock;
  `:834` — the settled render applies the retention gate;
  `:2155–2199` — `retentionInfo()`, `retentionNotice()`,
  `retentionSubmitNode()`, `applyRetentionSubmit()` and
  `releaseRetentionSubmit()`; `:2237–2242` — the single summary bar renders the
  guidance above the ONE existing details opener (no second bar/control).
  The controller surface is unchanged (`closeInfo` only).

### Clock behaviour

| Trigger | Behaviour |
|---|---|
| Desktop (any trigger) | `reconcileMobileClock()` returns `false` before any work: no T15 pass, no options, no build, no refresh, no Submit write. Existing desktop path untouched. |
| Mobile, first pass on a local date | Re-entrancy flag + `mobileClockLastDateKey` admit exactly one full pass per LOCAL date; focus + visibility + a due timer cannot loop or duplicate work. |
| Pass order | `reconcileRetainedSelections()` (T15) -> eligible week list -> option rebuild -> canonical keep/build or ONE `weekNav.selectWeek()`. |
| Current week still eligible | Options rebuilt with that absolute week selected, then `buildTimetable()` repaints newly too-soon/holiday/occupied cells; the page's own counter/refresh follows. |
| Current week no longer eligible | Next eligible week forward, else the earliest eligible week, via one `weekNav.selectWeek(target)` (existing `onBeforeNavigate` save + grid/select/arrows/save stay canonical). Venue and selected time are never touched. |
| Zero eligible weeks | `populateWeekSelect` yields zero options; one disabled `No bookable weeks` option is inserted, no build/navigation runs (no `jumpToEarliestBookable`/today fallback), and one `refreshArrangementMobile('clock')` lets T07 show the exact MA-03f message and disable select/arrows/Earliest/Submit. Saved entries remain inspectable/removable. |
| T15 markers | Markers are never auto-cleared by the clock. Only the canonical `loadCurrentWeek()` restore can clear one; the pass never writes `selectedSlotsByVenue`. |
| Open information sheet / saved history | Never closed or cleared by expiry (no `closeInfo`/history/store calls in the block). |
| Local-midnight timeout | One timer for the next local midnight (+1s); it calls the same guarded pass and re-arms exactly one successor. |
| Tab return | `visibilitychange` while visible and window `focus` call the same guarded pass; same-date repeats are no-ops. |
| Mobile tap | `chooseAvailable()` reconciles before resolving the cell; if the pass moved the week, the stale row is refused rather than selecting the new week's same day/hour. A same-week too-soon cell is still refused by the existing `toggleCell()` guard. |
| Mobile Submit | `proceed()` reconciles first (T17/T18 add the identity/busy checks after). |

### Retention presentation and Submit

| Snapshot | Summary bar | Mobile Submit |
|---|---|---|
| `retention.any === true` + saved entry | One retention notice (`Saved selection is no longer bookable.` / `Remove it below, then choose another date.`) above the existing details button; the sheet still lists the entry with its one keyed Remove. | Forced disabled on every settled mobile render, even when a later eligible date/week exists. |
| `retention.any === true`, no expressible entry | The normal saved/empty notice plus the same guidance; no false empty bar and no broken opener. | Forced disabled. |
| Retention clears | Guidance disappears; the adapter writes nothing, so the page's normal counter/total gate (and a later T18 busy latch folded into it) owns the value; a disabled busy Submit is never force-enabled. | Untouched by the adapter. |
| Breakpoint exit / destroy | Mobile-only lock released back to the page's ordinary `totalSlots > 0` gate so desktop does not inherit it (T18 must keep its latch across viewport changes). | Restored to the desktop gate. |
| Desktop mount | Bar is hidden with the desktop layout; the adapter never writes the desktop Submit. | Unchanged. |

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (4407 lines) -> `node --check` — pass.
- `/tmp/opencode/t16-clock-smoke.cjs` — **73 passing, 0 failing**, executing the
  real extracted T16 block against stubs: desktop no-op; T15-first order;
  `ranges:false` + the live `weekHasBookableSlot` filter; absolute option
  indexes; same-date skip; local-date rollover; keep-current rebuild; forward
  then earliest resolution with exactly one `selectWeek`; zero-week disabled
  placeholder with no navigator/build and one `clock` refresh; re-entrancy
  refusal; one idempotent midnight timer re-armed once; and static guards that
  the block never touches the store, markers, venue, selection, history, the
  information sheet or green repaint.
- `/tmp/opencode/t16-adapter-smoke.cjs` — **105 passing, 0 failing** (the 80
  maintained T14 assertions unchanged plus 25 T16 ones): guidance + exact copy
  in the one bar; one details opener and one keyed Remove; retained entry stays
  inspectable; Submit forced disabled; clearing retention removes only the
  guidance and preserves both an enabled page gate and a simulated busy gate;
  breakpoint exit returns Submit to the total gate; defensive
  retention-without-entry copy; desktop mount never writes Submit.
- `/tmp/opencode/t16-regression-t04-snapshot.cjs` — pass; the real
  `readArrangementSnapshot()` block executed with the T15 marker map injected:
  `retention.any/count/keys` populated and the store deep-equal afterwards.
- Lane regressions re-run against the T16 code: t04 adapter/utils `PASS`,
  t05 coalescing `PASS` (with its file argument), t06 adapter `ALL PASS`,
  t09 adapter `80/80`, t10 adapter `109/109`, t10-regression-t07 `75/75`,
  t10-regression-t08 `136/136`, t11 adapter `84/84`, t11 page-static `80/80`,
  t12 adapter `97/97`, t13 adapter `46/46`, t13-regression-t08-page `39/39`,
  t14 adapter `80/80`, t14 page-static `73/73`,
  t14-regression-t09-page `34/34`, t14-regression-t13-page `71/71`.
  Four maintained copies carried pre-T16 expectations and were re-run with only
  those expectations updated, not hidden as product failures:
  - `t04-snapshot-smoke` needed the new `retainedUnrenderableKeys` parameter;
    updated copy `t16-regression-t04-snapshot.cjs` (adds the retention asserts).
  - `t15-retention-smoke` sliced its block to `function getDays()`, which now
    contains T16; updated copy `t16-regression-t15-retention.cjs` ends at the
    T16 anchor -> `68/68`.
  - `t11-regression-t10-page` executes the extracted `chooseAvailable`, which
    now reads `arrangementMobile`; updated copy `t16-regression-t10-page.cjs`
    adds a desktop stub -> `41/41`.
  - `t12-page-static` pinned exactly one `submitRequestBtn` read; T16 adds the
    second read-only retention gate. Updated copy `t16-regression-t12-page.cjs`
    -> `68/68` (still asserts no submit handler and no synthetic click).
  The older direct copies `t04-snapshot-smoke`, `t15-retention-smoke`,
  `t11-regression-t10-page` and `t12-page-static` now fail only on those
  already-documented pre-T16 assumptions; they were not rewritten.
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` — pass;
  the untracked adapter also shows no whitespace errors via
  `git diff --no-index --check /dev/null public/js/arrangement-mobile.js`.
- No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push. No demo data was read or written.

These are static/stub receipts, **not** browser, clock, visual, screen-reader
or device evidence. Nothing here proves a real local-midnight rollover (or
timer throttling in a hidden tab), an actual `visibilitychange`/focus sequence,
real week-select rendering/rotation, the retained bar on a phone, or that the
server would reject a stale submit; T22/T26 own the expiry lifecycle.

### Hazards carried forward

- **T17 final identity.** `proceed()` now reconciles but deliberately continues
  even when the pass marked the saved entry expired or moved the week. T17 must
  re-run `reconcileMobileClock()` (same date-key guard) at the Yes boundary and
  compare the presented identity; `snapshot.retention`/`retainedUnrenderableKeys`
  are the expiry evidence. Do not treat the Submit `disabled` state as proof —
  it is applied only after a settled render.
- **T18 busy layering.** `applyRetentionSubmit()` only ever writes `true` and
  never re-enables on clear. `releaseRetentionSubmit()` (desktop exit/destroy)
  sets the plain `totalSlots > 0` gate, so T18's `mobileSubmissionInFlight` must
  be folded into that same gate/`updateCounter()` and re-applied on viewport
  change, or a busy desktop Submit could be re-enabled here.
- **T19 teardown.** The page closure now owns one `setTimeout`
  (`mobileClockTimer`) and two document/window listeners for the page lifetime;
  there is no page-level teardown hook today (page unload only). T19 must not
  add a second clock timer and should document/own these if an SPA-style
  lifecycle is ever added. The adapter still owns only its T05 scheduler, T08
  pulse, T14 observer/resize fallback and click/dialog listeners.
- **Stale-row refusal is silent.** When a clock pass moves the week, the page
  port returns `false` and the adapter (T10) currently shows no transient; the
  queued settled render corrects the view. T17/T18 should not turn that into a
  second selection path.
- **Placeholder can be replaced by a breakpoint re-populate.** The shared
  `populateWeekSelect` resize registry re-runs with our live filter (still zero
  options), so crossing 768px while the semester is empty can leave an empty,
  T07-disabled select instead of the `No bookable weeks` option. It stays
  non-navigable and the exact empty message still shows; no shared helper was
  changed to work around it.
- **First pass rebuilds on the first mobile interaction of a date.** That is
  intentional (T15 markers + options are made current before select/Submit), but
  it means a stale mobile row tapped in that first interaction is refused when
  the week moved. T23/T26 should exercise this with controlled fixtures.
- **Sheet content is not refetched.** Expiry does not close or rebuild an open
  information sheet (MA-10d); its Remove still uses the exact keyed port, while
  the bar guidance/Submit lock update on the next settled read.
- **Date-grain only.** Eligibility is date-based, so a same-date focus/select
  pass is skipped by design. A server-side calendar/payload change (e.g. a new
  holiday row) still needs the existing reload/refresh path.
- **Browser evidence is still owed.** Real midnight, visibility/focus, option
  rebuild, retained remove-and-choose, and 768/769px behaviour belong to
  T22/T23/T26 and were not exercised here.

T16 checkbox remains for the parent to mark; T17–T19, T20+ tests, page
changelog, full PHP gates and any commit/push stay with their own tasks.

## Apply T17 — final-confirmation identity/date validation — 2026-11-11

Delegated T17 only, after completed T01–T16 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership-border hint, retained
blue selection outline). Baseline `28f9f4d`; the working tree also carries the
uncommitted T03–T17 lane, a later committed UI commit (`5b0b9ff`) and other
sessions' dirty files (`docs/demo-db-data-changes.md`,
`resources/views/livewire/replacement-home.blade.php`), none of which were
touched. Only `resources/views/livewire/replacement-arrangement.blade.php`
changed in this task. `public/js/arrangement-mobile.js`, the partial,
`theme.css`, `ui-common.js`, the shared layout, the backend, the frozen
artifacts and `tasks.md` were not modified. No T18 request latch, backend/OCC
rule, second submit path, timer or removal control was added.

### Changed anchors

- `resources/views/livewire/replacement-arrangement.blade.php:2251–2398` — the
  page-owned T17 block inside the closure, after `scheduleMobileClock()`:
  `MOBILE_SUBMIT_REVIEW_TITLE`/`MOBILE_SUBMIT_REVIEW_HINT` (persistent guidance
  copy), `mobileHasRetention()`, `mobileTextOrNull()`, `mobileNumberOrNull()`,
  `mobileComparableObject()`, `mobileSavedEntryIdentities()`,
  `mobileOriginalIdentity()`, `mobileRetentionIdentity()`,
  `captureMobileSubmissionIdentity()`, `mobileSavedDatesStillValid()`,
  `mobileSubmissionStillValid(presented)` and `showMobileSubmissionReview()`.
- `:3415–3429` — `proceed()` now reconciles, captures the presented identity,
  and refuses to open the confirmation when a T15 marker exists or any saved
  entry's date is already invalid. Desktop (`mobileSubmission` stays `null`)
  walks the exact existing total/rows/confirmation path.
- `:3481–3496` — the existing Yes callback, immediately after the unchanged
  pin gate + `orig` preparation and immediately before the one
  `pageLivewire().call('submitBlocks', ...)`: re-run `reconcileMobileClock()`,
  revalidate the captured identity, and on any mismatch show persistent
  `showAlertModal(...)` guidance and return without a network call.
- `:3292` — the existing `showAlertModal()` is reused unchanged (one Close
  button, no action callback, no timer); `:6054` still exports the same
  `window.proceed`.

### Identity and expiry behaviour

| Case | Opener `proceed()` | Yes boundary (immediately before Livewire) |
|---|---|---|
| Desktop-origin (no mobile context) | Existing reconcile-free path, existing confirmation/body/rows, existing Yes callback | No capture exists: no date/identity gate, no wording change, existing call/success/rejection path byte-identical |
| Mobile, saved entry already expired (Sunday/holiday/too-soon) | Persistent `showAlertModal` only; the confirmation never opens; store and markers untouched | n/a |
| Mobile, T15 retention marker present | Persistent `showAlertModal` only; no confirmation; store and markers untouched | n/a |
| Mobile, valid at open | Capture exact presented identity, then the existing confirmation | n/a yet |
| Mobile, unchanged valid Yes | n/a | Reconcile (same-date no-op allowed), recapture, JSON-equal identity -> the one existing `submitBlocks` call with the same `blocks`/subject/`orig`/pin arguments |
| Saved entry added/removed/day/start/end changed | n/a | No call; persistent guidance (`identity` cause); store/markers kept |
| Subject code changed | n/a | No call; persistent guidance; store/markers kept |
| Original identity changed (`selectedOriginalSlot` or `urlParams` day/start/originalVenue) | n/a | No call; persistent guidance; store/markers kept |
| Pinned occurrence changed (`window.pinnedOccurrence`, key-order-insensitive compare) | n/a | No call; persistent guidance; store/markers kept |
| Date expired between open and Yes | n/a | `isSlotTooSoon`/Sunday/holiday re-run rejects; no call; `expired` guidance; store/markers kept |
| Retention appeared between open and Yes | n/a | Rejected by the explicit retention check and the identity compare; no call; `retention` guidance; markers kept |
| Mobile opened, resized to desktop before Yes | n/a | The captured context survives; guard still runs; `reconcileMobileClock()` safely no-ops on desktop but the direct date/identity revalidation still rejects a change |
| Missing pin at Yes | Confirmation still opens (existing flow) | Existing picker-first `alert()` runs before the T17 guard; no `submitBlocks` call |
| Rejection/expiry at Yes | n/a | No auto-retry, no store clear, no `deselectBlock()`/`clearVisualSelection()`/`clearAllRetained()`; the existing Submit gate is not force-enabled |

Identity shape (read-only, JSON-comparable): sorted `entries[]`
(`venueKey`, numeric `weekIndex`, `day`, `startHour`, `endHour`), `subject`
string, `original` (`day`/`start` numeric-or-null, `venue` trimmed-or-null),
`pin` (shallow key-sorted copy of `window.pinnedOccurrence` or `null`) and
`retention` (`count`, sorted `keys`). Capture sorts a new array; it never sorts
or writes `selectedSlotsByVenue` or `retainedUnrenderableKeys`.

### Checks actually run (static/stub only)

- Extracted page `@section('page-scripts')` (4,581 lines, 270,567 chars) ->
  `node --check` — pass.
- `/tmp/opencode/t17-confirmation-smoke.cjs` — **144 passing, 0 failing**. It
  executes the real extracted T17 helper block plus the real extracted
  `proceed()` and its confirmation callback against stubs. It proves:
  one capture/date/validation helper and one `submitBlocks` call site;
  no T18 latch; no timer/store/marker write in the helper block; opener order
  reconcile -> capture -> retention/date refusal; Yes guard after the pin/orig
  preparation and before the call; read-only capture with sorted entries and
  normalized original/pin/retention identity; `selectedOriginalSlot` preferred
  over the URL pair; Sunday/`Sun` abbr/holiday/too-soon/missing-week/missing-day/
  missing-helper rejection; opener refusal for expired and retention states;
  valid open -> one exact-identity send; entry add/remove/change, subject,
  original, pin, mid-review expiry and mid-review retention each produce no
  network call, persistent guidance with remove/choose-next-date copy, and
  unchanged store/markers; the existing pin gate still precedes the guard;
  desktop-origin open/Yes shows no mobile guidance and still submits; and a
  mobile-opened confirmation resized to desktop retains its protection.
- `/tmp/opencode/t17-regression-t16-clock.cjs` — **73 passing, 0 failing**.
  This is the maintained T16 clock copy with its block boundary moved from
  `function getDays()` to the new `const MOBILE_SUBMIT_REVIEW_TITLE` anchor;
  T17 helpers now sit between T16 and `getDays()`, so the older
  `t16-clock-smoke.cjs` slice counts T17's read-only store accessor and fails
  only on those two stale slice assertions (documented, not a product failure).
- Lane regressions re-run against the T17 page: t04 adapter/utils `PASS`,
  t05 coalescing `PASS` (with its file argument), t06 adapter `ALL PASS`,
  t09 adapter `80/80`, t10 adapter `109/109`, t10-regression-t07 `75/75`,
  t10-regression-t08 `136/136`, t11 adapter `84/84`, t11 page-static `80/80`,
  t12 adapter `97/97`, t16-regression-t12-page `68/68`, t13 adapter `46/46`,
  t13-regression-t08-page `39/39`, t16-regression-t10-page `41/41`,
  t14 adapter `80/80`, t14 page-static `73/73`,
  t14-regression-t09-page `34/34`, t14-regression-t13-page `71/71`,
  t16-regression-t15-retention `68/68`, t16 adapter `105/105`,
  t16-regression-t04-snapshot `PASS`.
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` — pass;
  `node --check public/js/arrangement-mobile.js` — pass (adapter untouched).
- No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push. No demo data was read or written.

These are static/stub receipts, **not** browser, clock, visual or device
evidence. Nothing here proves a real local-midnight rollover while a
confirmation is open, a real orientation change on a phone, that a hidden tab's
timer fires, or that the server would reject a stale payload. T22/T26 own those
lifecycle scenarios.

### Hazards carried forward

- **T18 must not duplicate this guard.** The Yes boundary now validates and
  returns before `pageLivewire()`. T18's latch belongs immediately before the
  same call, after this guard passes, and must not add a second
  `submitBlocks` site or a second confirmation. The guard deliberately does
  **not** set any busy flag, so T18 must set `mobileSubmissionInFlight`
  synchronously after validation and before the call.
- **T18 release path.** A business rejection (`results` with `ok:false`) and a
  transport/synchronous failure must release the latch, keep this task's
  retained store/markers and guidance semantics, and must not auto-retry. The
  existing success branch's `clearAllRetained()` and redirect stay authoritative.
- **`updateCounter()` still owns Submit.** T17 never writes the Submit flag.
  T18 must fold `mobileSubmissionInFlight` into `updateCounter()` and the T16
  `applyRetentionSubmit()`/`releaseRetentionSubmit()` gate; do not re-enable a
  busy button when retention clears.
- **Same-date reconciliation is intentionally skipped.** `reconcileMobileClock()`
  admits one full pass per LOCAL date, so the Yes re-run is often a no-op. The
  direct `mobileSavedDatesStillValid()` + identity compare is the actual
  protection; do not remove it as "redundant" in T18/T19.
- **Reconcile may still navigate at Yes.** If the clock pass moves the week, the
  existing `weekNav.selectWeek()` runs its normal save/build before the identity
  compare. That preserves the store (T15) and the exact identities, but T18
  should not rely on the week view being unchanged; only the captured identity
  matters.
- **Pin comparison is shallow.** `window.pinnedOccurrence` is today a flat
  `{sessionId, week}` object; a future nested pin would compare by
  `JSON.stringify` insertion order inside the nested object. Deepen the
  canonical copy if the pin shape ever grows (still no new backend rule).
- **Original identity normalization is comparison-only.** `urlParams` values
  are strings while `selectedOriginalSlot` values are numbers; the submitted
  `orig` JSON is untouched. A deliberate future change to the submit argument
  shape must update both the capture and the call together.
- **Retention copy is generic.** One persistent modal names expiry, retention and
  identity-change causes; it does not list which exact entry expired. The saved
  summary/sheet (T11/T16) remains the place that names the entry and its keyed
  Remove. No removal control was added here.
- **Browser evidence is still owed.** Real expiry while the confirmation is
  open, a real repeat Yes, rotation/counter refresh, transport failure and
  success teardown belong to T22/T26 and were not exercised here.

T17 checkbox remains for the parent to mark; T18–T19, T20+ tests, page
changelog, full PHP gates and any commit/push stay with their own tasks.

## Apply T18 — mobile-origin request latch and recovery — 2026-11-11

Delegated T18 only, after completed T01–T17 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership-border hint, retained
blue selection outline). Baseline `28f9f4d`; the working tree also carries the
uncommitted T03–T18 lane, a later committed UI commit (`5b0b9ff`) and other
sessions' dirty files (`docs/demo-db-data-changes.md`,
`resources/views/livewire/replacement-home.blade.php`), none of which were
touched. Two files changed in this task:
`resources/views/livewire/replacement-arrangement.blade.php` and
`public/js/arrangement-mobile.js`. The partial, `theme.css`, `ui-common.js`,
the shared layout, the backend, the frozen artifacts and `tasks.md` were not
modified. No second submit site, no auto-retry, no backend/OCC change, no new
dependency, no page-local CSS (the existing `.btn-primary:disabled` tokens
already render the disabled Submit/Yes state) and no desktop success/conflict
wording change.

### Changed anchors

- `resources/views/livewire/replacement-arrangement.blade.php:2400–2450` — the
  page-owned T18 block inside the closure, after
  `showMobileSubmissionReview()` and before `getDays()`:
  `MOBILE_SUBMIT_UNKNOWN_TITLE`/`MOBILE_SUBMIT_UNKNOWN_BODY` (persistent
  uncertain-outcome copy), `mobileSubmissionInFlight` (page state),
  `mobileSubmitBusyButtons()`, `setMobileSubmissionBusy()`,
  `releaseMobileSubmissionLatch()` and `showMobileSubmissionUnknown()`.
- `:2475–2478` — the ONE existing `updateCounter()` Submit gate now reads
  `!has || mobileSubmissionInFlight`; every other line of it is unchanged.
- `:3470–3476` — `proceed()` starts with the latch guard, before any
  reconcile/capture/confirmation (a duplicate confirmation cannot open).
- `:3556–3562` — inside the existing Yes callback, immediately after T17's
  identity/date validation and immediately before the call: duplicate guard,
  synchronous `mobileSubmissionInFlight = true`, then
  `setMobileSubmissionBusy(true)` (existing Submit + `#modalConfirmBtn` +
  `#modalActionBtn` disabled).
- `:3563–3580` — the ONE `pageLivewire().call('submitBlocks', ...)` is unchanged
  in arguments, wrapped in a local try/catch (mobile: release + persistent
  uncertain modal; desktop: rethrow) plus a non-promise check (uncertain).
- `:3677` — the existing conflict-result branch releases the latch before the
  unchanged conflict modal (selection still kept).
- `:3714–3729` — the mobile-only `.catch` on the submit chain: releases, keeps
  selection/store/markers, shows the persistent uncertain modal, never retries,
  and never stacks a second modal after an already-reported rejection.
- `:6384–6385` — `readArrangementSnapshot()` exposes the read-only
  `submissionBusy: mobileSubmissionInFlight === true` field next to
  `retention`; the store/marker maps are still never exposed.
- `public/js/arrangement-mobile.js:289–295` — T18 contract comment;
  `:396–398` — `submissionBusyCached`; `:559–565` — `exitMobile()` re-reads the
  latch from the last settled snapshot before releasing the empty/retention
  locks; `:731–735` — the full empty-state release also honours the latch;
  `:2189–2222` — `submissionBusy()`, `applyRetentionSubmit()` and
  `releaseRetentionSubmit()` (the existing T12/T16 Submit gates); `:2669–2673`
  — controller-surface comment. The public controller surface is unchanged.

### Latch lifecycle

| Point | Page state | Submit / Yes+Action | Store + markers |
|---|---|---|---|
| Mobile `proceed()` while busy | latch true | no second confirmation opens | untouched |
| Yes, T17 validation fails | latch never set | existing review modal; no send | untouched |
| Yes, validation passes | latch set synchronously, then one call | Submit + Yes + Action disabled before the call | untouched |
| Counter refresh / Adapter settled render while busy | latch true | `updateCounter()` keeps Submit disabled even with total > 0; `applyRetentionSubmit()` re-disables on every render | untouched |
| Breakpoint exit to desktop while busy | latch true (page-owned) | `releaseEmptyControls(true)` and `releaseRetentionSubmit()` both refuse to re-enable from the cached snapshot | untouched |
| Desktop-origin request (no mobile context) | latch never set | existing path byte-identical (no capture, no gate, no catch, sync throw still propagates) | untouched |

### Outcome behaviour

| Outcome | Latch | Modal / navigation | Selection |
|---|---|---|---|
| All results `ok` | kept through the existing cleanup, `updateCounter()` and redirect | existing redirect to `/my-replacements`; no recovery modal; controls are never re-enabled before leaving | existing success wipe + `clearAllRetained()` unchanged |
| Any result `ok:false` (business rejection) | released before the conflict modal | existing conflict modal, byte-identical wording/buttons; no uncertain modal | kept (no `deselectBlock()`/`clearAllRetained()` on this path) |
| Promise rejection (transport) | released | persistent `showAlertModal`, Close only, says the outcome is unknown, advises checking My Replacements before retrying; no auto-retry | kept; store/markers untouched |
| Synchronous throw from `pageLivewire().call(...)` | released | same persistent uncertain modal | kept |
| Non-promise result | released | same persistent uncertain modal | kept |
| Duplicate mobile Yes while busy | still true | refused silently (confirmation already hidden) | untouched |

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (4,683 lines, 276,848 chars) ->
  `node --check` — pass.
- `/tmp/opencode/t18-latch-smoke.cjs` — **84 passing, 0 failing**. Executes the
  real extracted T18 block + real `proceed()`/Yes callback against stubs:
  one latch declaration/one release/one call site; latch + control disabling
  happen before the call; success keeps the latch through cleanup/redirect and
  keeps Submit disabled; business rejection clears the latch before the
  conflict modal (Submit gate re-applied, selection kept); transport rejection,
  synchronous throw and non-promise each clear the latch, keep the store and
  markers, and show exactly one persistent uncertain modal with no retry;
  a duplicate `proceed()`/Yes while pending sends nothing; desktop-origin keeps
  its synchronous throw and shows no mobile modal; release without a latch is a
  no-op; no timer/reload/auto-retry in the block.
- `/tmp/opencode/t18-regression-t17-confirmation.cjs` — **167 passing, 0
  failing**: the maintained T17 contract, re-sliced at the new T18 anchor, with
  the one "T18 latch not added" assertion replaced by the latch presence/
  placement assertions and a duplicate-Yes case.
- `/tmp/opencode/t18-adapter-smoke.cjs` — **117 passing, 0 failing**: the
  maintained 105 T16 assertions plus 12 T18 ones (busy forces Submit disabled on
  mount and on every settled refresh; clearing retention never re-enables it;
  breakpoint exit to desktop keeps it disabled; empty-semester release keeps it
  disabled; a desktop mount never applies the lock; the adapter adds no
  `pageLivewire`/`submitBlocks` path).
- `/tmp/opencode/t18-regression-t04-snapshot.cjs` — pass; the real
  `readArrangementSnapshot()` block reports `submissionBusy === false/true`
  from the injected latch and still deep-equals the store afterwards.
- Lane regressions re-run against the T18 code: t04 adapter/utils `PASS`,
  t05 coalescing `PASS` (with its file argument), t06 adapter `ALL PASS`,
  t09 adapter `80/80`, t10 adapter `109/109`, t10-regression-t07 `75/75`,
  t10-regression-t08 `136/136`, t11 adapter `84/84`, t11 page-static `80/80`,
  t12 adapter `97/97`, t16-regression-t12-page `68/68`, t13 adapter `46/46`,
  t13-regression-t08-page `39/39`, t16-regression-t10-page `41/41`,
  t14 adapter `80/80`, t14 page-static `73/73`,
  t14-regression-t09-page `34/34`, t14-regression-t13-page `71/71`,
  t16-regression-t15-retention `68/68`, t16 adapter `105/105`,
  t17-regression-t16-clock `73/73`.
- Three maintained copies now fail only on their pre-T18 assumptions and were
  not rewritten: `t17-confirmation-smoke` (pinned "T18 latch not added" and a
  helper slice ending at `getDays()`, which now includes the T18 block ->
  superseded by `t18-regression-t17-confirmation`), `t16-regression-t04-snapshot`
  (its snapshot factory passes a fixed dependency list that now needs
  `mobileSubmissionInFlight` -> superseded by
  `t18-regression-t04-snapshot`), and the older `t16-clock-smoke` pair already
  documented in the T17 receipt (unchanged, 71/2).
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` — pass;
  the untracked adapter also shows no whitespace errors via
  `git diff --no-index --check /dev/null public/js/arrangement-mobile.js`.
- No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push. No demo data was read or written.

These are static/stub receipts, **not** browser, clock, transport, visual or
device evidence. Nothing here proves that a real Livewire round-trip fails the
way a rejected stub promise does, that a real counter refresh lands while a
request is pending, that a real rotation preserves the disabled controls, or
that the server would reject a duplicate send. T22/T26 own those lifecycle
scenarios.

### Hazards carried forward

- **T19 teardown.** The latch is page-closure state, deliberately outside the
  adapter: `destroy()` must not (and cannot) clear it. A future page-level
  teardown that wipes booking state must not also forget the latch while a
  request is pending; today only a fresh page load resets it.
- **Cached-latch staleness on breakpoint exit.** `submissionBusyCached` is the
  last settled snapshot, not a fresh read (T07/T08 pin "exit mobile does not
  read the port"). If the latch is set and no settled render followed before a
  resize, the page's own synchronous Submit disable is what survives; both
  release helpers refuse to write in that case. Do not add a fresh port read on
  exit without updating those assertions deliberately.
- **`proceed()` guard is not mobile-scoped.** It is `if (mobileSubmissionInFlight)
  return;` at the top because the latch is only ever set by a mobile-origin
  request; a resize into desktop therefore still cannot open a second
  confirmation. Desktop-origin requests see `false` and are unchanged. Do not
  "simplify" that into an adapter check.
- **Modal buttons on success.** The Yes/Action buttons are disabled with the
  latch and are deliberately not re-enabled on success; the page navigates
  immediately. If T19 ever adds a non-navigating success path, re-enable those
  two buttons there or the next modal would open with a dead Confirm.
- **Catch scope.** The mobile `.catch` also covers a throw from the downstream
  success/conflict rendering. It skips recovery when the latch is already
  released (confirmed rejection already reported), so no modal stacking.
- **Retention appearance mid-flight.** A clock pass during a pending request can
  mark retention; that pass refreshes the adapter, so the cached busy state is
  current. Debugging a "Submit re-enabled after resize" report should first
  check that a settled render happened.
- **Sampled reconciliation is unchanged.** Same-date `reconcileMobileClock()`
  is still a no-op at the Yes boundary; T17's direct date/identity compare
  remains the protection and must not be removed as redundant.
- **Browser evidence is still owed.** Real duplicate Yes, counter refresh /
  rotation while busy, transport failure, synchronous Livewire failure and the
  success teardown belong to T22/T26 and were not exercised here.

T18 checkbox remains for the parent to mark (tasks.md untouched); T19, T20+
tests, page changelog, full PHP gates and any commit/push stay with their own
tasks.

## Apply T19 — controller teardown and resize integration — 2026-11-11

Delegated T19 only, after completed T01–T18 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership-border hint, retained
blue selection outline). Baseline `28f9f4d`; the working tree also carries the
uncommitted T03–T19 lane, a later committed UI commit (`5b0b9ff`) and other
sessions' dirty files (`docs/demo-db-data-changes.md`,
`resources/views/livewire/replacement-home.blade.php`), none of which were
touched. Two files changed: `public/js/arrangement-mobile.js` and
`resources/views/livewire/replacement-arrangement.blade.php`. The partial,
`theme.css`, `ui-common.js`, the shared layout, the backend, the frozen
artifacts and `tasks.md` were not modified. No T20–T23 test authoring, no
browser/DB/Playwright work, no commit and no push. No new dependency, no new
listener/timer, and no booking write path was added.

### Changed anchors

- `public/js/arrangement-mobile.js:322–329` — T19 keeps the fallback
  scheduler's handle (`refreshTimer`) while leaving the primary
  `queueMicrotask` path (and the previously pinned exact
  `global.setTimeout(run, 0)` expression) intact.
- `:428–431` — `runRefresh()` clears the handle when a fallback turn actually
  runs, so it never refers to a fired timer.
- `:339–344` — boot-time capture of the partial's own static context-summary and
  dialog-heading text, restored by `destroy()` instead of hardcoded copy.
- `:2593–2698` — `destroy()` keeps its existing order (exit mobile → detach the
  matchMedia listener → detach the observer/resize fallback → reset info/focus
  state → remove the dialog `close` + two root `click` delegates → mark
  destroyed/unmounted → drop queued work) and adds T19: cancel a still-pending
  fallback refresh, clear the empty-state message, restore the shell context
  summary and dialog heading, drop adapter-built sheet body/action nodes (their
  listeners go with them), keep the existing day/timeline/summary/live clearing,
  close the dialog and leave the root hidden + inert.
- `resources/views/livewire/replacement-arrangement.blade.php:2452–2501` — the
  page-owned T19 block: `arrangementMobileTornDown`,
  `onMobileClockVisibility()`, `onMobileClockFocus()`,
  `teardownArrangementMobile()` and `onArrangementPageHide(event)`.
- `:6566–6568` — the boot now registers those two named clock handlers and ONE
  `pagehide` listener (`onArrangementPageHide`); `scheduleMobileClock()` still
  runs once. No anonymous clock listener remains.

### Teardown behaviour

| Situation | Clock timeout | Clock listeners | Adapter | Booking/store/latch |
|---|---|---|---|---|
| Page enters bfcache (`pagehide`, `persisted === true`) | kept | kept | kept alive | untouched; the existing `pageshow` (blade `:3877–3897`) owns the restored snapshot |
| Real unload/navigation/reload (`persisted` false or missing event) | exactly the one `mobileClockTimer` cleared and nulled | exactly `visibilitychange` + window `focus` removed | one `destroy()` call, wrapped in a local try/catch | never written |
| `pagehide` again / manual repeat | no second clear | no second removal | no second destroy (idempotency flag) | never written |
| No pending timer | `clearTimeout` not called | still removed | still destroyed | never written |
| `destroy()` throws in a DOM-poor state | already cleared | already removed | one attempt, exception contained | never written; page still leaves |
| Adapter `destroy()` called twice | n/a | removed once | second call returns immediately | never written |
| Adapter `destroy()` while desktop | n/a | media listener/observer/root+dialog delegates still released | terminal teardown | Submit left to the page gate; latch untouched |
| Adapter `destroy()` while mobile | n/a | all released once | exit restores controls/order/container hidden state, current Tip, measured insets removed, hosts emptied | Submit never force-enabled; busy latch honoured |

`teardownArrangementMobile()` deliberately does **not** touch
`selectedSlotsByVenue`, `retainedUnrenderableKeys`, `selectionHistory`,
`mobileSubmissionInFlight`, the selection store, `saveCurrentWeek()`,
`buildTimetable()`, `updateCounter()`, `proceed()` or `submitBlocks`; a real
page load is what resets those. The existing Livewire guards, confirmation
focus/return and `pageshow` restore path are unchanged.

### Resize and re-entry behaviour (adapter)

| Action | What runs | What is guaranteed |
|---|---|---|
| Enter mobile | relocate the same nodes, hide/inert desktop containers, render from the cached snapshot, suppress the relocated Tip's `data-tip`, measure insets, attach one observer | no port read, no refresh, no week hop, no selection/removal, no store write |
| Exit mobile | close panels/sheet, restore each node at its marker (or recorded parent/sibling), restore container hidden/inert, release only the mobile empty/retention Submit locks, detach observer/resize, restore the latest Tip message and `aria-label` | no port read, booking data/latch untouched; desktop controls and order restored |
| Repeat enter/exit | the same guarded functions | exactly one matchMedia listener, two root click delegates, one dialog close listener and at most one active observer; no duplicate binding |
| Re-entry after exit | a fresh single observer is created; the old one is disconnected exactly once | one listener set; the retired observer stays dead |
| `destroy()` then `mount()`/`refresh()` | both no-op (`destroyed` latch) | no remount, no read, no re-render, snapshot() returns null |
| Busy request + breakpoint exit | `submissionBusyCached` from the last settled snapshot | Submit stays disabled; the page-owned latch is not read afresh or written |
| Retention + breakpoint exit/destroy | retention lock released only to the page's normal total gate | a busy Submit is never re-enabled |

### Checks actually run (static/stub only)

- `node --check public/js/arrangement-mobile.js` — pass.
- Extracted page `@section('page-scripts')` (4,733 lines, 279,492 chars) ->
  `node --check` — pass.
- `/tmp/opencode/t19-adapter-teardown.cjs` — **79 passing, 0 failing**,
  executing the real adapter against a dependency-free DOM/observer/timer stub:
  one matchMedia listener, one active observer (no resize fallback when
  ResizeObserver exists), two root delegates and one dialog listener on mount;
  three enter/exit cycles add none, read no port snapshot, move no week, select,
  remove or toast; exit restores the latest (not boot-stale) Tip text and the
  original control parents/order; re-entry keeps one listener set and exactly
  one active observer with the previous one disconnected exactly once; destroy
  removes every listener, disconnects the observer, clears the pulse/fallback
  timers, drops measured body properties, empties day/timeline/summary/live
  hosts, restores the shell context summary and dialog heading, closes the
  dialog, restores the current Tip, leaves the root hidden/inert and never
  selects/removes/hops; a second destroy and a post-destroy
  `mount()`/`refresh()`/`revealTarget()` change no count and read nothing;
  a busy + retained snapshot keeps Submit disabled through exit and destroy
  while the page latch value is unchanged; the no-microtask (setTimeout
  fallback) path is cancelled by destroy and never reads; static checks confirm
  the adapter contains no store/marker/history/latch/Livewire/save/build path.
- `/tmp/opencode/t19-page-teardown.cjs` — **65 passing, 0 failing**: static
  checks for exactly one teardown, one pagehide handler, one named visibility
  and focus handler, one registration each, one `clearTimeout`, one adapter
  `destroy()` call, no added timer/listener and one idempotency flag;
  executable stub of the real block proves bfcache `persisted === true` keeps
  the timer/listeners/adapter, a real departure clears exactly the owned
  timeout and removes exactly the two clock listeners plus one destroy, repeat
  calls are no-ops, a missing timer does not call `clearTimeout`, a missing
  event is treated as a departure, a throwing `destroy()` does not break
  cleanup, and the handlers still reconcile only for visible/focus (hidden tab
  does not). Comment-stripped checks prove the block never mentions booking
  state, markers, history, the request latch, retention cleanup, save/build/
  counter/submit functions, storage or navigation.
- Lane regressions re-run against the T19 code: t04 adapter/utils `PASS`,
  t05 coalescing `PASS` (with its file argument), t06 adapter `ALL PASS`,
  t09 adapter `80/80`, t10 adapter `109/109`, t10-regression-t07 `75/75`,
  t10-regression-t08 `136/136`, t11 adapter `84/84`, t11 page-static `80/80`,
  t12 adapter `97/97`, t16-regression-t12-page `68/68`, t13 adapter `46/46`,
  t13-regression-t08-page `39/39`, t16-regression-t10-page `41/41`,
  t14 adapter `80/80`, t14 page-static `73/73`, t14-regression-t09-page
  `34/34`, t14-regression-t13-page `71/71`, t16-regression-t15-retention
  `68/68`, t16 adapter `105/105`, t17-regression-t16-clock `73/73`,
  t18 adapter `117/117`, t18 latch `84/84`,
  t18-regression-t17-confirmation `167/167`, t18-regression-t04-snapshot `PASS`.
  The older direct copies remain on their already-documented pre-task
  assumptions and were not rewritten: `t16-clock-smoke` 71/2 (pre-T17 slice
  includes T17's read-only store accessor), `t17-confirmation-smoke` fails the
  pinned "T18 latch not added" assertion and then crashes on T18's `document`
  stub (superseded by `t18-regression-t17-confirmation`), and
  `t04-snapshot-smoke` keeps its fixed pre-T16 dependency list (superseded by
  `t18-regression-t04-snapshot`).
- `php artisan view:cache` then `view:clear` — pass. `git diff --check` —
  pass; `git diff --no-index --check /dev/null public/js/arrangement-mobile.js`
  reports no whitespace errors.
- No `--env=testing`, DB access/write, browser, Playwright, server restart,
  staging, commit or push. No demo data was read or written.

These are static/stub receipts, **not** browser, listener-audit, bfcache,
device or screen-reader evidence. Nothing here proves that a real browser
fires `pagehide` with the expected `persisted` value on this page, that a real
bfcache restore leaves the controller working, that Livewire's DOM morphing
never detaches the adapter root, or that a real unload keeps the mobile layout
from flashing during restore. T23/T26/T27 own those lifecycle/browser checks.

### Hazards carried forward

- **`destroy()` is terminal.** It relocates nodes back to desktop and then
  hides/inerts the root; it is correct for a real departure but must never be
  repurposed as a live mobile on/off toggle. Breakpoint hiding belongs to
  `exitMobile()` alone.
- **The pagehide hook is intentionally not wired to `beforeunload`.**
  `beforeunload` can be cancelled, so tearing down there would destroy a page
  the user chooses to stay on. If a future SPA/Livewire `wire:navigate`
  lifecycle is introduced, it needs its own hook that matches that lifecycle;
  `pagehide` would not fire per component navigation.
- **bfcache correctness depends on the existing `pageshow` restore.** That
  handler (blade `:3877–3897`) wipes the booking snapshot and re-validates
  entry; T19 skips teardown only because of it. Changing or removing that
  handler would make a restored page resume a controller that is still alive
  (safe) but a stale booking snapshot; do not make it skip state recovery
  without revisiting this decision.
- **The pagehide teardown does not release the T18 latch or the saved store.**
  If the tab dies mid-request, the outcome remains genuinely unknown; T22 must
  test the real transport/browser behaviour rather than infer success.
- **Fallback scheduler cancellation only exists on the non-queueMicrotask
  path.** On the primary path the queued microtask cannot be cancelled, but
  `runRefresh()` refuses once `destroyed`, so no post-teardown read happens.
  A future change that reads the port outside `runRefresh()` would break that
  guarantee.
- **The adapter root is captured once.** If Livewire ever replaces
  `#arrangementMobile` rather than morphing it, the adapter would keep the old
  detached root. This page has no `wire:navigate` today; T23/T27 should include
  an other-page/isolation and Livewire-refresh check.
- **Shell text capture is boot-time only.** If the partial's default dialog
  heading or context summary text is changed in markup, no adapter change is
  needed (it is re-captured on load), but changing it at runtime would be
  reverted by destroy.
- **`pagehide` fires before the page is gone.** `destroy()` moves the relocated
  nodes back during that event; this is invisible on a real unload but is a
  DOM mutation in the departure path. Keep it non-destructive (no save/network)
  if it is ever touched.
- **Stub listener counts are not browser counts.** The T19 numbers prove the
  adapter's own bookkeeping, not real event-listener leaks, bfcache behaviour,
  observer delivery, timer throttling or Livewire interplay.
- **Browser evidence is still owed.** Real navigation/back/forward, bfcache
  restore, Livewire refresh while mobile, rotation during a pending request
  and desktop parity belong to T22/T23/T26/T27 and were not exercised here.

T19 checkbox remains for the parent to mark (tasks.md untouched); T20+ tests,
the page changelog, full PHP gates and any commit/push stay with their own
tasks.

## Apply T20 — dependency-free adapter/utility unit coverage — 2026-11-11

Delegated T20 only, after completed T01–T19 and the amended frozen artifacts
(borderless Pending/Conflict for everyone, no ownership-border hint, retained
blue selection outline). Baseline `28f9f4d`; the working tree also carries the
uncommitted T03–T19 lane, a later committed UI commit (`5b0b9ff`) and other
sessions' dirty files (`docs/demo-db-data-changes.md`,
`resources/views/livewire/replacement-home.blade.php`), none of which were
touched. **One file added:** `tests/arrangement-mobile-unit.test.cjs`
(1,148 lines, 48 cases). `public/js/arrangement-mobile.js`, the Blade page, the
partial, `theme.css`, `ui-common.js`, the shared layout, the backend, the frozen
artifacts and `tasks.md` were not modified. No new dependency (Node built-ins
only), no production behaviour changed for test convenience, no browser/
Playwright, no DB access, no server restart, no commit and no push.

### What the file runs

The suite loads the REAL `public/js/arrangement-mobile.js` through `node:vm`
with a sandbox `window` (the IIFE ends `})(window)`), and the REAL T15
retention-marker helpers sliced out of the page's `@section('page-scripts')`
from the `let retainedUnrenderableKeys = {};` anchor to the end of
`clearAllRetained()`. Timeline cases drive the production `render()` path with a
hand-rolled element stub (no DOM library). Guard anchors are asserted, so a
future page/adapter rename fails the suite loudly instead of silently skipping.

### Coverage

| Area | Cases | What is actually proven (helper level) |
|---|---|---|
| Module surface | 1 | Only `create` + frozen `utils` are exported |
| `chooseEligibleDay` | 12 | Original-index retention on non-contiguous indexes, same-index eligibility, first-eligible fallback, numeric-string pointer, position fallback for a missing/invalid `index`, ineligible duplicate handling, `eligible !== true` fail-closed (1/'true'/omitted), non-array/null/empty fail-closed, helper-supplied Sunday/PH/too-soon flags consumed verbatim (no adapter clock) |
| `savedSpan` | 4 | Valid half-hour spans, exclusive `end` (4 -> 2 slots), single slot, numeric-string coercion, invalid day/start/end (negative, fractional, String, `end <= start`, missing) rejected, frozen entry not mutated |
| `savedEntries` | 9 | `venue::week` keys, numeric week index, week label/day abbr/date, week->day->start sorting, every legacy venue/week entry preserved, missing week kept with `dayKnown:false` + generated label, missing day of a real week kept with `dayKnown:false`, unparseable week keys/invalid spans skipped, malformed/missing store and missing week data fail closed, store untouched (frozen + JSON before/after) |
| `create()` safe failure | 11 | No-root `create({})` inert (mount/refresh schedule no read; `snapshot()` is the documented one fresh read; destroy is terminal), no snapshot port -> `null`, throwing port contained -> `null`, non-object port result discarded, throwing `onSnapshot` contained (one read, snapshot intact), pre-mount refresh is a no-op then mount reads once, 3 queued refreshes collapse to one read and render never re-enters, cached-vs-fresh `snapshot()`, one mount binds exactly two root click delegates and one destroy removes them (repeat mount/destroy no-op, post-destroy refresh inert), fallback-timer turn cancelled by destroy before any read, post-destroy refresh never re-renders |
| Timeline fail-closed / span | 6 | Missing canonical cell -> disabled `--unknown` row (`data-mobile-state=unknown`, `aria-disabled`), `known:false`/non-canonical/empty `stateClasses` all fail closed, only whitelisted classes applied (duplicate dropped, no ownership-ring class), a 2-slot saved span renders ONE block (`data-mobile-span=2`, `grid-row: span 2`) with no covered half-hour rows, a blocked cell under a saved span suppresses the span (rows stay), a span past the axis is not drawn |
| T15 retention identity (page-logic extraction) | 5 | `venue::week` marker keys and invalid-week refusal, exact numeric block identity stored and matched (`isRetained` exact vs absent), partial/null blocks refused, keyed cleanup removes only that marker (absent key is a no-op) and `clearAllRetained()` empties all, `sameBlock` requires complete numeric identities |

Scenario support (not browser evidence): MA-03a/c/d at the day-choice helper
level, MA-04a–d at the canonical-row/span-render level, MA-06c keyed legacy
entries, MA-10a–d only for the retention identity-marker primitives.

### Checks actually run

```
$ node tests/arrangement-mobile-unit.test.cjs
arrangement-mobile-unit: 48 passing, 0 failing
$ echo $?
0
```

Failure path proven with a temporary copy (deleted afterwards): one expected
value changed to `999` produced

```
FAIL chooseEligibleDay keeps the previous original index on non-contiguous days
     Expected values to be strictly equal:

     5 !== 999

arrangement-mobile-unit: 46 passing, 1 failing
failing-copy-exit=1
```

so the runner exits nonzero on failure and prints exactly one line on success.

Also run: `node --check tests/arrangement-mobile-unit.test.cjs` — pass;
`php artisan view:cache` then `php artisan view:clear` — pass (no UI change; the
mandated gate only); `git diff --check` — pass; `git diff --no-index --check
/dev/null tests/arrangement-mobile-unit.test.cjs` — no whitespace errors
(exit 1 is the expected "new file differs" status). `git status --short` shows
only the new untracked test file beyond the pre-existing lane/other-session
files. No `--env=testing`, no DB read/write, no browser/Playwright, no staging.

### Honest gaps and findings

- **Clock reconciliation is NOT unit-tested.** The adapter computes no
  eligibility or notice period; it consumes `eligible` flags, and that is what
  the helper-supplied calendar case proves. The page's `reconcileMobileClock()`
  target choice (keep eligible / next forward / earliest, zero-option
  placeholder) is closure code with no pure export. Re-implementing it in a test
  would be exactly the duplicate this task forbids, so T22b/T26 still own the
  real-clock/expiry evidence. No pass is claimed for MA-10a–d beyond the marker
  primitives above.
- **T15 lifecycle is NOT unit-tested end-to-end.** Save/load at a null visual
  pointer, discard-all, success cleanup, re-eligibility restoration and desktop
  resize stay with T22b. The extracted cases cover the identity/cleanup
  primitives only, and the file says so.
- **Coercion quirk recorded, not "fixed":** `key('B103', null)` returns
  `'B103::0'` because `Number(null) === 0`. No production call site passes
  `null` (they pass `weekNav.currentWeek`/numeric keys), and T20 must not edit
  the page helper to make the test tidier. The test pins the real behaviour.
- **Not browser proof.** Stub-DOM rows, listener counts and the vm sandbox are
  adapter bookkeeping, not real layout, Livewire morphing, focus, geometry or
  touch behaviour. T21–T23/T26–T27 keep those.
- **`create()` requires an options object.** `create()` with no argument (as
  opposed to `create({})`) still throws at destructuring; the page always passes
  `{root, ports}`, so no production change was made.

T20 is authored and green on its own command; its checkbox and the page
changelog/full gates stay with their own tasks.

## Apply T21a–T21c — mobile calendar/selection/information browser group — 2026-11-11

Delegated T21a, T21b and T21c only, after completed T01–T20 and the amended
frozen artifacts (borderless Pending/Conflict for everyone, no ownership-border
hint, retained blue selection outline). Baseline `28f9f4d`; the working tree also
carries the uncommitted T03–T20 lane, a later committed UI commit (`5b0b9ff`) and
other sessions' dirty files (`docs/demo-db-data-changes.md`,
`resources/views/livewire/replacement-home.blade.php`), none of which were
touched. **One file added:** `tests/arrangement-mobile.spec.ts` (1,430 lines,
18 `test()` declarations). `public/js/arrangement-mobile.js`, the Blade page,
the partial, `theme.css`, `ui-common.js`, the shared layout, the backend, the
frozen artifacts and `tasks.md` were not modified. No new dependency. **Not
executed:** no Playwright/browser, no DB read/write, no server start/restart, no
commit and no push. T22/T23 authoring was deliberately not started.

### Scope and guard

- The file covers only T21a–T21c. Clear comment markers for the later T22a–c
  and T23a–c groups sit at the bottom; no T22/T23 case is authored.
- First statement after imports:
  `abortUnlessTestingTarget('tests/arrangement-mobile.spec.ts', { requireAllowSql: true })`
  — exact `CRS_MODE=testing` + `CRS_BASE=http://127.0.0.1:8030` +
  `CRS_ALLOW_SQL=1`, no localhost fallback and no `:8000` default. `BASE` is
  read from `CRS_BASE`; the spec is serial and documented to run alone.
- Viewport is 390×844 with touch enabled (the adapter breakpoint is
  `max-width: 768px`). Later T23 cases still own 769px/desktop parity; T21 does
  not claim it.
- Login is the existing testing staff account `5425 / Tarumt@2026`; every
  `test()` gets a fresh page/context and logs in through the real
  `/login/staff` form.
- Assertions use the adapter's public DOM contract
  (`[data-arrangement-mobile-*]`, `[data-mobile-hour]`, `[data-mobile-state]`,
  `[data-mobile-row]`, `[data-mobile-span]`, `#arrangementMobileInfo`) plus the
  page's real canonical grid/helpers (`#tableBody`, `window.weekData`,
  `isSlotTooSoon`, `leadTimeCutoff`, `firstBookableDay`, `to12h`,
  `add30min`). No copied availability engine and no direct adapter-instance
  global; `#tableBody` is read, never clicked.

### Fixtures (guarded testing DB only)

Three SQL fixture families, each id/key-keyed and reversed in `afterEach` and
again in `afterAll`:

| Fixture | Purpose | Write/reverse |
|---|---|---|
| One room/week `time_slots` reservation | MA-03b: an eligible date with no free times stays visible | capture exact `id`s of flipped `available` rows; inserted rows captured via `RETURNING id`; restore status/version by id and delete inserted ids |
| One `class_exceptions` week-1 row for the discovered session | MA-02b: a past original slot stays selectable while week 1 is not a replacement week | delete-then-insert exact `(session_id, week 1)`; delete that exact pair |
| Marker-labelled `holidays` on weeks 1–14 × Mon–Sat | MA-03f: zero eligible dates, exact empty message and disabled controls | `ON CONFLICT DO NOTHING`, label `PW T21c mobile empty semester`; delete only that label |

No demo DB, no reseed, no `migrate:fresh`, no `--env=testing`, no queue worker.
Concurrent spec re-seeding is still a documented run-alone hazard (the ownership
spec has the same constraint).

### Cases actually authored (not executed)

| Group | Cases | Contract asserted |
|---|---|---|
| T21a | 8 | Per-offered-week day buttons equal the real Sunday/holiday/too-soon helper result, Sunday index 6 never offered, seeded holiday days hidden when that week is offered; notice-cutoff date is the first eligible date of its week and the prior calendar day is hidden/its week unoffered; a fully pending eligible room/week keeps its days visible with 22 non-available rows and real state text; no-subject context guidance, expanded subject reachability and the existing gate toast, no block; native holiday + inserted week-1 original slots are both offered/selectable while week 1 is not a replacement week; weekday retention/fallback with no selection created; Earliest reveals the first eligible day (shared flash observed via a MutationObserver) and creates no selection; all-weeks-holiday empty semester shows exactly `No bookable dates remaining this semester.`, hides the timeline, disables week/arrows/Earliest/Submit and leaves context/tools/legend |
| T21b | 6 (1 statically skipped) | 2 h → one 4-half-hour span from one tap, 1 h → one 2-half-hour span, both with exact `start12 ~ end12` text, curated venue label, `heading · range` summary title, `venue · duration` meta and no covered hour rows (22−span+1 rows remain); short-gap and day-end starts show the existing `Won't Fit` toast with no span/summary; a saved selection blocks a second tap with the exact five-second guidance and unchanged summary/span; off-view week keeps the summary identity, the sheet lists exact date/time/duration/venue and one keyed Remove clears only that entry (no span, no selected canonical cells, empty bar, disabled Submit) |
| T21c | 4 | Blocked conflict/pending row opens its canonical state/date/time/venue + filtered reason, text-only nodes (no `script`/`img`/`iframe`), no row controls in the sheet, stays open beyond 5,600 ms; the canonical selection-wipe tip is present on green cells after a selection yet absent from the sheet; legend has exactly four real-class rows, `0px` borders on Available/Pending/Conflict, a non-zero blue selection sample and no ownership-border hint; Tip opens the live message, loses only `data-tip` while mobile and never shows the always-mounted shared floating tooltip |

The 5.6-second wait in T21c is the explicit "no auto-dismiss timer" check; the
five-second transient/fitty timer belongs to T21b/T22 and is not conflated with
the information sheet.

### Data-dependent skips/annotations (honest, not passes)

- No conflicted occurrence for staff 5425, no 2-half-hour or 4-half-hour
  occurrence, no contiguous whole-span free run in the pinned venue, no
  conflict/pending row in the offered weeks, no green no-subject row: the test
  skips with a specific reason.
- Notice cutoff outside the seeded semester, holiday week not bookable this run,
  only one bookable week, no later week retaining/falling back the weekday, no
  second green row after the first selection: annotated or skipped, never
  claimed green.
- **Legacy multiple-entry summary is statically skipped.** The store
  (`selectedSlotsByVenue`) is closure-private and the page budget flexes to the
  committed occurrence's span, so no sanctioned user path can create a second
  entry. T20 already pins `savedEntries` keyed entries; T22b/T26 keep lifecycle
  evidence. The skip text says so.

### Static sanity actually run

```
$ node --experimental-strip-types --check tests/arrangement-mobile.spec.ts
$ echo $?
0
```

This is a syntax parse only (Node strips types): it proves the file parses, not
that Playwright collects it, not TypeScript type correctness and not any test
outcome. `git diff --no-index --check /dev/null tests/arrangement-mobile.spec.ts`
reported no whitespace errors (the expected exit status 1 is "new file
differs"). No `tsc`/tsconfig exists in the repo. No browser/Playwright/DB
command was run.

### Honest gaps and findings

- **Authored only.** Nothing below is a pass receipt. The spec must be run
  alone on the disposable testing DB (`class_replacement_testing`) at :8030 with
  `CRS_MODE=testing CRS_ALLOW_SQL=1` after explicit browser permission (T25/T26).
- **The page's real clock drives eligibility.** Date expectations are derived
  from the page's own helpers at run time, not hardcoded, but a run near the end
  of the seeded term will exercise fewer weeks and produce more honest skips.
- **Selection budget determinism:** the pinned arrival auto-pins the earliest
  conflicted occurrence of the subject, which can differ from the URL's
  occurrence. The spec therefore commits the URL-pinned original slot through
  the real picker before tapping a replacement row, so `MAX_SELECTION` and
  `BLOCK_SPAN` describe the same occurrence. Without that user-level step a
  seed where the auto-pin is a different duration could legitimately show the
  budget toast instead of a block.
- **Fixture interleaving:** running this spec concurrently with
  `arrangement-slot-ownership`, `arrangement-conflict-autoselect`,
  `arrangement-entry-guard` or a re-seeding spec can invalidate the room/week
  reservation or the exception row. Run alone.
- **Not visual/accessibility proof.** DOM text/state/geometry read via
  Playwright would not prove real device touch, screen-reader output, safe-area
  insets, rotation or theme rendering. T23/T27–T28 keep those.
- **Tip-node quirk:** the shared floating tip div is always mounted (hidden);
  the T21c assertion is about visibility/opacity, not node absence. A future
  shared-engine change to pre-render text is not covered here.
- **No T22/T23 authoring.** Guard/expiry/submit lifecycle and keyboard/geometry/
  desktop parity remain explicitly parked behind the section markers.

T21a–T21c checkboxes remain for the parent to mark (tasks.md untouched); T22,
T23, T24+ and any commit/push stay with their own tasks.

## Apply T22a–T22c — guard, expiry and submit-lifecycle browser group — 2026-11-12

Delegated T22a, T22b and T22c only, after completed T01–T21 and the amended
frozen artifacts (borderless Pending/Conflict for everyone, no ownership-border
hint, retained blue selection outline). Baseline `28f9f4d`; the working tree still
carries the uncommitted T03–T21 lane, the later committed UI commit (`5b0b9ff`)
and other sessions' dirty files (`docs/demo-db-data-changes.md`,
`resources/views/livewire/replacement-home.blade.php`), none of which were
touched. **One test file changed:** `tests/arrangement-mobile.spec.ts`
(1,430 → 2,469 lines; 18 → 31 `test()` declarations; 13 new), plus this receipt.
`public/js/arrangement-mobile.js`, the Blade page, the partial, `theme.css`,
`ui-common.js`, the shared layout, the backend, the frozen artifacts and
`tasks.md` were not modified. No new dependency. **Not executed:** no
Playwright/browser, no DB read/write, no server start/restart, no commit and no
push.

### Scope and guard

- T21a's exact-keyed fixture setup is reused unchanged. T22 adds **no SQL
  writes**: T22a/T22c are read-only UI/page flows and T22b drives the page's own
  clock helpers in memory. The file's first statement after imports remains
  `abortUnlessTestingTarget('tests/arrangement-mobile.spec.ts',
  { requireAllowSql: true })`; the serial/run-alone contract, 390×844 mobile
  viewport, testing staff login, `CRS_BASE` and the no-`:8000` default are
  unchanged.
- The T21a–T21c cases are untouched. T23 remains unauthored; its section marker
  is preserved (only the now-stale “stops before T22” sentence was corrected).
- Assertions still read the adapter's public DOM contract and the page's real
  canonical grid/helpers. T22 adds two narrow test-controlled surfaces, both
  page-context-only: the deterministic clock reader and the submit transport.

### Deterministic clock (the important finding)

The `/replacement-arrangement` page explicitly runs
`MockData.mockNow = null` at boot, so `DateHelper.getTodayMs()` — the one helper
`isSlotTooSoon()`, `leadTimeCutoff()` and `weekHasBookableSlot()` read — falls
back to the real browser `Date`. `page.clock` alone can therefore move
eligibility, but it also moves the local-date key that
`reconcileMobileClock()` uses for its **once-per-local-date** guard, making a
second pass on the same simulated day a silent no-op.

T22b uses two controls to keep the two concerns separate:

1. `page.clock.setFixedTime()` moves the browser date to a **distinct sentinel**
   local date (2035-01-01 + a per-simulation day offset) so the page's reconcile
   guard actually runs.
2. A page-context override of `DateHelper.getTodayMs()` carries the **simulated
   today** used by the real eligibility helpers.

`t22SameWeekExpiryClock()` chooses that simulated today from the saved week's
own real dates by calling the page's OWN `isSlotTooSoon()` and
`weekHasBookableSlot()` — it does not re-implement the notice period. It finds
a **forward/same-day** candidate (preferring the saved day, then earlier
working days that are still not before the real today) where the saved date is
too soon but another day in the same week remains eligible, so the same-week
rebuild path (not a week hop) is exercised. T22b therefore prefers a Mon–Tue
saved run; final option expiry uses a date two years beyond the saved date.

No production file and no shared `MockData` object is edited. The override lives
in the disposable page context; every test gets a fresh page. `t22RequireClock()`
skips the clock-driven cases with a reason if the runner lacks Playwright's
supported `page.clock`, so an unavailable clock is blocked, never a false pass.
The real local midnight timer is still **not executed** — T26/T29 keep that
evidence.

### Controlled transport (no server write)

T22c installs a page-context wrapper around `window.pageLivewire()` that
intercepts `submitBlocks` only and records the call count and exact arguments
(blocks, subject code, original identity, pin). Modes: pending (test-resolved
promise), resolved success, resolved business rejection, synchronous throw and
promise rejection. No `submitBlocks` Livewire request is made. The success
redirect to `/my-replacements?new-req=…` is fulfilled by `page.route`, so no
booking is submitted against any DB, demo or testing.

The success receipt additionally records `#submitRequestBtn.disabled` from the
real `pagehide` event into `sessionStorage` and reads it on the fulfilled
redirect page, proving the button was still disabled when the document left.

### Cases actually authored (not executed)

| Group | Cases | Contract asserted |
|---|---|---|
| T22a | 5 | Subject, original-slot and venue changes each open the existing confirmation; Cancel restores the committed selector/trigger/label and the saved span/summary; Confirm performs the existing discard/change once and leaves the bar empty. Arrival URL pin is preserved through mobile boot and a replacement pick (no invented occurrence), the in-page `Unsaved Changes` Stay path keeps the page/selection, and the native `beforeunload` is observed when available. A booking-intent URL keeps its booked date/time identity through the existing banner or auto-selection surface; a legitimate session rewrite is allowed. |
| T22b | 2 | Same-week expiry: retained summary/marker, saved-day buttons gone while later same-week days remain, option list equals the real helper result, no green/selected canonical cells, open details sheet survives with its facts and Remove, week hop/desktop resize keeps the marker, explicit removal clears entry + marker and does not resurrect. Final option expiry: exact `No bookable dates remaining this semester.`, hidden timeline, disabled week/arrows/Earliest/Submit, one disabled `No bookable weeks` placeholder, retained summary + open sheet + Remove in the empty semester. |
| T22c | 6 | Date expiry before Yes makes **zero** `submitBlocks` calls, keeps the saved data and shows the persistent `Review Your Saved Selection` guidance; pin-identity drift before Yes does the same with the identity wording. Pending duplicate Submit/Yes, a real week rebuild (counter refresh), in-mobile rotation, breakpoint crossing and desktop transition all keep exactly one call and Submit/Yes disabled; promise rejection releases the latch, keeps data, shows persistent `Request OUTCOME UNKNOWN` and does not retry. Resolved business rejection releases the latch with the existing persistent `Submission CONFLICT` shell and no retry. Synchronous throw releases the latch with the same unknown-outcome guidance. Controlled success keeps busy through the redirect and sends the presented blocks/venue/week/day/start/end/subject/pin exactly once. |

### Static checks actually run

```
$ node --experimental-strip-types --check tests/arrangement-mobile.spec.ts
$ echo $?
0

$ git diff --no-index --check /dev/null tests/arrangement-mobile.spec.ts
# exit 1 is the expected "new file differs"; no whitespace diagnostics printed
```

This is a syntax parse only. It does not prove Playwright collection, TypeScript
type correctness, any test outcome, or browser behaviour. No Playwright,
browser, DB, `artisan`, server or seed command was run.

### Honest gaps and findings

- **Authored only.** Nothing above is a pass receipt. T22 must run alone on the
  disposable `class_replacement_testing` DB at :8030 with
  `CRS_MODE=testing CRS_ALLOW_SQL=1` after explicit browser permission
  (T25/T26).
- **Data-dependent skips:** no conflicted occurrence/free run, no second
  subject/slot/venue, no same-week simulated today that expires the saved day
  while keeping the week bookable, no intent banner/selection, only one offered
  week, or a headless run suppressing `beforeunload` each skip/annotate with a
  specific reason. A run near/past the seeded term end will exercise fewer
  weeks and produce more skips.
- **The real clock is still live.** T22b's deterministic override proves the
  reconciliation/retention rules, not the real local-midnight timer or a real
  date rollover. T26/T29 still own that receipt.
- **Venue panel geometry:** the venue-change case drives the real four-column
  row handlers from the page context after the real trigger opens the panel.
  T23 still owns the panel's mobile geometry/visual assertions; T22 does not
  claim them.
- **Rotation is a viewport resize, not a device rotation.** 700×390 tests a
  mobile rotation; 844×390 deliberately crosses the app's 768px breakpoint and
  is treated as desktop. No real-device, touch, screen-reader, safe-area or
  theme rendering is proven.
- **“Counter refresh” is a real week-select rebuild**, not a synthetic Livewire
  morph. The latch's page-owned flag and the adapter's Submit gate are asserted
  across that rebuild and the resize; an actual Livewire counter morph was not
  fabricated.
- **Success redirect is stubbed** by `page.route` with a minimal document; the
  real My Replacements landing page, its DB reads and its flash were not
  exercised.
- **Retained + busy combined state** (a retained entry plus an in-flight
  request across a breakpoint) is not exercised; T22c's busy-resize case is a
  normal saved selection. The page/adapter code paths for that combination
  remain untested here.
- **No T23 authoring.** Keyboard/focus/announcements, 320/390/768/769 geometry,
  computed styles, themes, safe areas and desktop parity remain parked behind
  the T23 marker.

T22a–T22c checkboxes remain for the parent to mark (`tasks.md` untouched);
T23, T24+ and any commit/push stay with their own tasks. No commit, stage or
push was performed.

## Apply T23a–T23c — accessibility, geometry and desktop-parity browser group — 2026-11-12

Delegated T23a, T23b and T23c only, after completed T01–T22 and the amended
frozen artifacts (borderless Pending/Conflict, no ownership-border hint, blue
selection outline). **One test file changed:** `tests/arrangement-mobile.spec.ts`
(2,469 → 4,224 lines; 31 → 46 `test()` declarations; 15 new), plus this receipt.
`public/js/arrangement-mobile.js`, the Blade page, the partial, `theme.css`,
`ui-common.js`, the shared layout, the backend, the frozen artifacts and
`tasks.md` were not modified. No new dependency. **Not executed:** no
Playwright/browser, no DB read/write, no server start/restart, no screenshot, no
commit and no push.

### Scope and guard

- The file's first statement after imports remains
  `abortUnlessTestingTarget('tests/arrangement-mobile.spec.ts',
  { requireAllowSql: true })`; the serial/run-alone contract, 390×844 mobile
  base viewport, testing staff login, `CRS_BASE`, and the no-`:8000`/no-demo-DB
  rule are unchanged. T23 adds **no SQL writes** and reuses T21a's exact-keyed
  fixtures unchanged; the only test-state mutations are page-context (clock,
  dialog counters, listener counters, a scoped style tag) and are torn down with
  the page.
- T21a–T22c cases are untouched. The stale “T23 is not authored here” section
  marker was replaced by the real T23a–T23c group and its shared probes; the
  T21/T22 headings, fixtures and helpers are preserved.
- MA-15a/MA-15b are evidenced here only as diff scope (test-file-only +
  receipt), guard reuse and zero new SQL. MA-15c is a review task, not a
  browser assertion; neither is claimed as a pass.

### Deterministic methods (and their limits)

- **Computed styles + bounding boxes only.** No screenshot/pixel comparison is
  authored and no visual claim is made, because browser permission is absent.
  Desktop parity is asserted structurally: the same node objects (expando
  identity), the same parent and sibling index, the same values/ids, the same
  hidden/inert and computed-display states at 768 vs 769 vs 1280.
- **Breakpoint geometry.** 768px must show only the mobile shell (`grid-wrapper`,
  `.sel-summary`, `.toolbar-restructure`, `.legend-bar` computed `none`, hidden +
  inert), 769px must restore them and expose no mobile-only tab stop. 320/390/768
  assert ≥44px width/height for every visible real `button`/`select`/`summary`
  inside the mobile root, no page-wide horizontal overflow, vertical-only
  timeline scrolling, a bounded day-picker scroller, unclipped wrapping text and
  the measured `--arrangement-mobile-bar-height` equal to the bar's real height.
  The compact original-slot trigger is allowed to ellipsize (desktop contract);
  when it does, the assertion requires its full facts in the trigger tip instead
  of pretending the text never clips.
- **Text enlargement is simulated** with a page-scoped
  `#arrangementMobile { font-size: 200%; }` style plus a same-specificity
  padding override that guarantees the fixed bar really changes height. The
  receipt therefore proves “layout survives enlarged text and the measured
  inset follows a real size change”, **not** OS/browser text zoom, device
  rendering or a screen-reader reading order.
- **Span/row geometry** uses the saved indexes: grid-row must span exactly the
  saved half-hours, height ≥44px per half-hour and within the 48px+4px-gap
  nominal (±tolerance for wrapped venue text), symmetric non-overlapping gaps,
  `scroll-margin-top = sticky + 12px` and `scroll-margin-bottom = bar + 12px`
  when the measured values exist. First/last rows are scrolled into view and
  hit-tested with `elementFromPoint`; the toast must clear the fixed bar; the
  confirmation action must be hit-testable. Safe-area support is asserted as a
  stylesheet declaration (`env(safe-area-inset-bottom`) plus the existing
  `viewport-fit=cover` meta — no real notch/safe-area device was available.
- **Keyboard.** Native Enter/Space are pressed on real day, slot-trigger, legend
  and Close controls; a bubble-phase keydown probe records `defaultPrevented`
  (registered after boot, so it observes the page's own handler), and a
  patched `HTMLDialogElement.showModal`/`close` counter proves one dialog
  open/close per activation. The shared `[`/`]` capture blocker must prevent the
  bubble probe from ever seeing those keys on mobile. Tab/Shift+Tab containment
  relies on native modal-dialog top-layer behaviour (Chromium in this runner);
  focus return is asserted by identity descriptor (legend, summary opener) and
  after Remove by the documented rebuilt-control fallback (current day button).
- **Announcements.** One `MutationObserver` on the existing
  `[data-arrangement-mobile-live]` region (asserted `aria-live="polite"`,
  `aria-atomic="true"`) counts non-empty messages: one per real day/selection/
  removal change, none on resize or a no-op repeat. Slot buttons are asserted to
  name their 24-hour range + canonical state; the date comes from the labelled
  day heading. This is DOM/AT-contract evidence, **not** a screen-reader test.
- **Five-second timing.** `page.clock.install()` is installed **after** boot and
  after the selection exists, so Livewire's boot timers are not frozen. After
  the first `fastForward`, Playwright's fake clock is paused (including
  `requestAnimationFrame`), so every later activation in these cases is a real
  DOM `click()` (`t23DomClick`) through the same delegated listener rather than
  a locator gesture. Assertions: visible at 4,990ms, gone at 5,030ms; a newer
  warning at 3,000ms stays visible at 2,400ms after the newer one, gone at
  5,100ms after it. Persistent sheets/Tip and the critical confirmation survive
  a 60,000ms advance with zero `submitBlocks` calls. This is precise for
  `setTimeout`-driven UI only; it does not prove background-tab timer throttling
  or a real device clock.
- **Listener/controller non-duplication.** A pre-navigation `addInitScript`
  wrapper counts `addEventListener`/`removeEventListener` calls by target label
  + type. It asserts exactly two delegated root clicks (day + timeline), one
  dialog close listener, and unchanged add/remove maps after three full
  768/769/390 crossings. This proves no re-binding; it does not fingerprint
  function identity. One root/host/dialog and one of each relocated control, no
  duplicate ids, and no re-entry after a real teardown corroborate the single
  controller even though the closure instance itself is private.
- **Teardown.** A synthetic non-persisted `pagehide` exercises the page's own
  handler (`teardownArrangementMobile`): root hidden/inert, body class and
  measured vars removed, controls restored, rendered hosts/live region cleared,
  values unchanged, the media listener released (a later 390px viewport cannot
  re-enter mobile) and a second dispatch is a safe no-op with no `pageerror`.
  A synthetic event is the only available way to exercise this handler without
  destroying the page context; real unload/bfcache timing is still unverified.
- **Other-page isolation** loads `/my-timetable-ui` at 390px: no
  `#arrangementMobile`, no `[data-arrangement-mobile*]` hosts/body class/vars,
  and its grid is not hidden; returning to `/replacement-arrangement` activates
  the mobile shell again. Only that one page was checked.
- **Themes/motion.** The real `.theme-toggle` is clicked twice, so both current
  `html` theme classes are exercised; text/background pairs are resolved from
  computed colours (walking to the first opaque background) with an automated
  floor of **3.0** — a regression floor, **not** a WCAG 4.5:1 audit. The blue
  span keeps a 2px non-transparent outline in both themes. `emulateMedia`
  asserts reduced-motion collapses the nonessential transition durations to
  `0.01ms`/`0s`.
- **Tip/tooltip.** Tip must live in the tools group to the right of
  Colours & borders, open the same live message, leave exactly one shared
  `.data-tip-tooltip` node and show no floating tooltip on mobile; at 1280 the
  latest message (not boot-stale) returns to `data-tip` and focusing the trigger
  shows the floating tooltip, which hides on blur; re-entering mobile suppresses
  it again. The T21c helper's `offsetParent !== null` visibility test is
  unreliable for the shared tooltip's `position: fixed`; T23c uses a new
  visibility/opacity/width probe and deliberately leaves T21c untouched.

### Cases actually authored (not executed)

| Group | Cases | Contract asserted |
|---|---|---|
| T23a | 4 | Enter/Space activate day, original-slot trigger, legend and Close once; no `preventDefault` on native keys; ArrowDown on the week select is not hijacked (popup-vs-commit recorded honestly); hidden-grid focus cannot select a cell; a sheet takes initial focus on Close, Tab/Shift+Tab stay inside, survives 5,600ms, Escape and the keyboard Close both dismiss it with focus returning to the legend opener, keyboard Remove dismisses it and focus falls back to the rebuilt current-day control, and Remove clears the saved cells; `[`/`]` are capture-blocked on mobile with and without a sheet, `?`/Ctrl+Z/Ctrl+1/arrows run nothing, background state/venue/week/selection are unchanged, critical confirmation Escape still closes without clearing data, and desktop `]`/`?`/Escape still work; the polite region announces one day change, is silent on a same-day repeat and on resize, announces removal exactly once, and announces exactly one `Selection saved.` after a real re-selection. |
| T23b | 6 | 768 is mobile-only and 769 is the restored desktop layout with only its own controls/tab stops; three 768↔769↔390 crossings keep the same nodes/parents/sibling indexes/values, no duplicate ids, no confirmation, no booking change and the saved span; 320/390/768 keep ≥44px targets, bounded page width, vertical-only timeline, unclipped wrapping text and a measured bar height that follows a real 200% text + padding change, then 1280 shows no mobile surface/inset leak; the saved span keeps its index-derived grid-row/height/gaps and 44px+ rows with sticky/bar-derived scroll margins, the sheet remains 85dvh and safe-area-declared; first/last rows and the toast are hit-testable/clear of sticky/bar and the confirmation action is not obscured; both themes keep computed-colour text/state/span styling and reduced motion collapses animation; `/my-timetable-ui` receives no shell/hosts/classes/vars. |
| T23c | 5 | The already-selected warning is visible at 4,990ms, gone at 5,030ms and a newer warning restarts the window (visible 2,400ms after it, gone at 5,100ms) without changing the saved block; legend/Tip sheets and the critical confirmation survive 60,000ms with zero submit calls; Tip opens beside the legend, carries the same live message, keeps one tooltip node and no floating duplicate, restores the current desktop tooltip on 1280 and suppresses it again on re-entry; three breakpoint crossings leave the listener add/remove maps unchanged, one shell/host/control set and no duplicate ids, with no announcements until one real day change and none on a following resize; a non-persisted `pagehide` restores desktop, clears rendered hosts/insets/live text, keeps values, releases the media listener (no mobile re-entry) and a second dispatch produces no page error. |

### Static checks actually run

```
$ node --experimental-strip-types --check tests/arrangement-mobile.spec.ts
$ echo $?
0

$ git diff --no-index --check /dev/null tests/arrangement-mobile.spec.ts
# exit 1 is the expected "new file differs"; no whitespace diagnostics printed
```

This is a syntax parse only. It does not prove Playwright collection,
TypeScript type correctness, any test outcome, or browser/visual behaviour. No
Playwright, browser, DB, `artisan`, server or seed command was run.

### Honest gaps and findings

- **Authored only.** Nothing above is a pass receipt. T23 must run alone on the
  disposable `class_replacement_testing` DB at :8030 with
  `CRS_MODE=testing CRS_ALLOW_SQL=1` after explicit browser permission
  (T25/T27).
- **No same-state refresh announcement case.** A rebuild that changes nothing
  can only be driven from public UI by re-dispatching the week select's
  `change` event, which MA-10a forbids as a synthetic control command. T23
  therefore asserts announcement non-duplication across real day changes,
  resizes, removal and re-selection; T23c asserts listener non-duplication
  across crossings. A genuine same-state counter/timetable refresh receipt is
  left to execution in T26/T27.
- **Data-dependent skips** annotate with a specific reason: fewer than two
  eligible days, no second free row in the viewed day, no rendered span, only
  one offered week, a browser that keeps the native select popup open, or a
  missing `/my-timetable-ui` page.
- **No visual/device evidence.** No screenshots, pixel diff, real phone,
  rotation sensor, notch/safe-area hardware, screen reader, Firefox or WebKit
  were used. 44px, geometry, overlay and theme assertions are computed/DOM
  assertions only. The contrast floor is 3.0, not a WCAG audit.
- **Text enlargement is simulated** by a page-scoped font-size override plus a
  test-only padding override; it is not browser/OS text zoom.
- **Clock scope.** The fake clock is installed after boot, so `fastForward` may
  also fire unrelated due page timers (e.g. a Livewire poll). The assertions do
  not depend on timer quietness, but that interaction is unexecuted. After the
  first advance the clock is paused (including rAF), so later activations use a
  DOM click rather than a locator gesture; a real user gesture in that paused
  window is not exercised.
- **Listener counts, not identities.** The probe proves no re-binding across
  crossings; it cannot prove two same-typed listeners are different functions.
- **Desktop parity is structural**, not a pixel comparison with the pre-change
  baseline. T02's static inventory plus T25/T27 own the screenshot baseline.
- **Teardown uses a synthetic `pagehide`.** It exercises the page's handler but
  not real navigation/bfcache timing; the existing `pageshow` restore path is
  not exercised by T23.
- **Prior helper caveat:** T21c's `floatingTooltipState()` treats
  `offsetParent !== null` as visible, which is always false for the shared
  tooltip's `position: fixed`. Its “not shown” assertion is therefore partly
  vacuous. T23c's new probe covers the real desktop-show/mobile-hide behaviour;
  a T27 follow-up should decide whether T21c needs that stricter helper too.
- **Tip geometry** assumes the tools row does not wrap at 390px; a narrower
  wrap would make “beside” a false expectation, and the case would surface it
  rather than silently pass.
- **Single controller** is observed indirectly (one root/host/control set,
  listener counts, no re-entry after destroy); the closure instance is private.
- **Theme cycling** proves both current `html` classes and token-derived computed
  colours for the sampled text; first-paint/theme persistence and every token
  pair are not covered.

T23a–T23c checkboxes remain for the parent to mark (`tasks.md` untouched);
T24+ and any commit/push stay with their own tasks. No commit, stage or push was
performed.

## Apply T24 — permitted static/Node gates — 2026-11-12

Executed only static/Node gates; no browser, no DB, no full PHPUnit, no commit/push.
Exact outcomes:

- `node --check public/js/arrangement-mobile.js` — pass.
- `node tests/arrangement-mobile-unit.test.cjs` — `arrangement-mobile-unit: 48 passing, 0 failing`.
- `node --experimental-strip-types --check tests/arrangement-mobile.spec.ts` — pass (syntax only).
- `composer run lint:check` (Pint) — passed.
- `vendor/bin/phpstan analyse --memory-limit=1G` — passed, 0 errors.
- `php artisan view:cache` then `view:clear` — pass.
- `git diff --check` — clean.
- `scripts/dev-serve.sh 8000` — HTTP 302, opcache revalidate_freq=0; log `/tmp/opencode/serve-8000.log`.

Working-tree observations at this point (preserved, not touched by this lane):
`docs/demo-db-data-changes.md` modified; `resources/views/livewire/replacement-home.blade.php`
modified; `.sdd/changes/replacement-arrangement-top-zone/` and
`.sdd/changes/student-modal-staff-parity/` untracked; the user's untracked docs/todo/top-zone
prompt remain. These are unrelated to the mobile lane and must not be staged or swept.

These gates do not prove browser layout, touch, safe-area, device, screen-reader or
Livewire/transport behaviour. T25 and the authored browser specs remain unexecuted.
Task checkbox left for the parent after verification.

## Apply T25–T27 — authorised browser execution — 2026-11-12

User explicitly authorised browser execution. Only the disposable testing lane was used:
`class_replacement_testing`, `scripts/dev-serve.sh 8030`, exact guard env
`CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030`. The demo DBs were not used.
Testing DB was reseeded with `migrate:fresh --seed` (allowed only there).

### Authored mobile suite — final receipt

`npx playwright test tests/arrangement-mobile.spec.ts --reporter=line`

**46 passed, 3 skipped, 0 failed** (4.3 min, one worker). The three skips are explicit
data/environment gaps (legacy multi-entry store is closure-private; deterministic space/row
cases unavailable); they are not passes and are not hidden.

Browser failures found and fixed during this run:
- `getDays()` crashed at boot when the empty-semester select had no option value. It now
  falls back to the navigator's clamped week; the empty state mounts instead of aborting.
- The blocked-day fixture passed the page's 0-based week index into SQL's 1-based
  `week_number`; test corrected to `week + 1`.
- 1-hour selection picked the wrong same-start occurrence because the picker match omitted
  the end time/venue; exact match now includes them.
- The suite logged in per test and exhausted Fortify's 5/min limiter; it now uses the
  setup project's `.auth/staff-5425.json` storage state once per run.
- Test expectation fixes: hour-row count excludes the separate span node; empty-week
  placeholder `value=""` is not week 0; Escape does not call the dialog `.close()` method;
  toast measurement waits for the 0.3 s slide; theme sample waits for token transition;
  reduced-motion serialisation `1e-05s` is compared in seconds; desktop `?` uses
  `page.keyboard.press('?')`; tooltip focus test no longer hovers first.
- Product/accessibility fixes: explicit native-dialog Tab containment; focus owns the
  data-tip tooltip so pointer-out cannot hide it; tools row keeps Tip beside Colours &
  borders and gives Earliest its own row; closePanels only calls existing closers when a
  panel is actually open, avoiding fake listener removals on resize.

Final static gates after these fixes:
- `node --check public/js/arrangement-mobile.js` and `public/js/ui-common.js` — pass.
- `node tests/arrangement-mobile-unit.test.cjs` — 48/48.
- `node --experimental-strip-types --check tests/arrangement-mobile.spec.ts` — pass.
- `composer run lint:check` — passed; `phpstan --memory-limit=1G` — 0 errors.
- `php artisan view:cache`/`view:clear` — pass; `git diff --check` — clean.

### Existing arrangement regressions on the same testing lane

- `tests/arr-slot-original-venue-sync.spec.ts` — **9/9 passed**.
- `tests/arrangement-entry-guard.spec.ts` — **9/9 passed**.
- `tests/arrangement-conflict-autoselect.spec.ts` — **10/10 passed**.
- `tests/arrangement-slot-ownership.spec.ts` — **3 passed; 1 failed; 3 not run**.
  The failure is the known date-dependent Week 8 fixture (`Week 8` no longer offers a
  bookable week on 2026-11-12), documented in the page changelog before this lane. The
  read-only resolver/legend cases passed. It is a baseline fixture failure, not attributed
  to this mobile change.

T25 permission/desktop-structural baseline, T26 mobile group and T27 responsive/existing
regressions are complete with the evidence above. No screenshots were saved for T25; the
desktop baseline is the T02 static inventory plus T23b's 769/1280 computed-style/structural
parity, not a pixel-by-pixel screenshot comparison.

T28 (manual device/touch/screen-reader review) remains user-owned and unclaimed. T29
changelog and final SDD Verify handoff remain pending.

## 2026-11-12 — Round 1 mobile design polish (post-apply, user-approved)

User reviewed the mobile day view and requested seven polish items, then approved the
result. All changes are page-local to `/replacement-arrangement`; desktop rules are
untouched except for the shared `ui-today-btn` label being wrapped in a span (no
desktop visual change).

Implemented:

1. Footer clearance moved out of the mobile root and under the footer. The last
   timeline row is followed by the footer (12px section margin), while the fixed
   action bar's 132px clearance sits below the footer content. At scroll end the
   footer content clears the bar; the old blank band is gone.
2. `#earliestBtn` is icon-only on mobile (`.today-btn-label` hidden); its
   `aria-label` remains "Jump to the earliest bookable slot".
3. `Colours & borders` has a palette icon and a token-based surface/shadow; the
   four legend rows are light cards, with Pending/Conflict still borderless.
4. All mobile pop-ups use the Saved Selection bottom-sheet language: the native
   info sheet, `#confirmModal` (Unsaved Changes and every change/submit/conflict
   guard) and the shortcuts card share a sticky header, text Close, 85dvh,
   safe-area padding and full-width actions.
5. Day picker is compact: weekday over bold date number (`Tue` / `17`), with the
   full date preserved in `aria-label`.
6. Back button is text-less on mobile (arrow only, 44px, `aria-label="Back"`).
7. Mobile top zone follows the approved top-zone prototype: full-bleed light-blue
   (`--color-primary-container`) bar, crest + two-line title centred as one pair,
   text-less 44px back pill, and a 44px theme toggle. The toggle uses the existing
   `#theme-icon` / `updateIcon()` sun-moon swap; light mode shows the sun, dark
   mode the moon. The pair stays side-by-side, per the user's final choice.

### Final browser receipt — clean run

Command (disposable testing lane only):

`CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test tests/arrangement-mobile.spec.ts --reporter=line`

Result: **47 passed, 3 skipped, 0 failed** (4.2 min, one worker, 50 tests including
the new round-1 test). The three skips are explicit data/environment gaps (legacy
multi-entry store and unavailable deterministic row cases), not passes.

New test: `round-1 mobile top zone, compact picker and footer clearance` checks the
text-less back control, visible theme toggle, compact day button/aria-label, icon-only
Earliest, legend icon, no blank footer band and footer content clearing the fixed bar.

Static gates after the final edits: `node --check` (arrangement-mobile.js, ui-common.js),
unit `48/48`, spec syntax, Blade `view:cache`/`view:clear`, `git diff --check`, Pint,
PHPStan 0 errors.

### T28 limited receipt

The user explicitly chose automated viewport review over a physical-phone check.
**Tested:** 390px Chromium viewport, light and dark themes, top zone, compact dates,
legend/sheets, footer clearance. **Approved:** user accepted the mobile design.
**Not tested / unresolved:** real-phone touch, rotation, enlarged text on a device,
safe-area on a real notch, and screen-reader output. These are recorded as unresolved,
not claimed as passes. No live demo booking was made.

### T29 scenario-to-evidence ledger

| Requirement | Evidence | Status |
|---|---|---|
| MA-01 responsive isolation | T23b 768/769 + desktop parity; T27 existing specs | automated pass |
| MA-02 context/controls | T21a context/navigation group; T28 viewport approval | automated pass; device caveat |
| MA-03 calendar/navigation | T21a eight calendar/navigation cases | automated pass |
| MA-04 canonical timeline | T21b/T21c timeline and row sheet cases | automated pass |
| MA-05 selection/removal | T21b selection, saved identity, keyed removal | automated pass |
| MA-06 summary/actions | T21b summary bar and off-view removal cases | automated pass |
| MA-07 information sheets | T21c blocked row, legend, Tip; round-1 shared sheet CSS | automated pass |
| MA-08 feedback lifetime | T23c five-second warning and persistent sheets | automated pass |
| MA-09 current guards/identity | T22a guards; T27 entry/venue/conflict specs | automated pass |
| MA-10 expiry/save preservation | T22b expired retained entries and week rebuild | automated pass |
| MA-11 mobile-origin submission | T22c identity/duplicate/rejection/transport cases | automated pass |
| MA-12 keyboard/focus | T23a activation, Tab containment, Escape, announcements | automated pass |
| MA-13 geometry/themes | T23b 320/390/768, 200% text, themes, reduced motion, round-1 test | automated pass |
| MA-14 lifecycle/reveal | T23b/T23c resize, pagehide, one controller/listener set | automated pass |
| MA-15 operational boundaries | static gates, diff check, testing-DB guard, no demo writes | pass with device caveat |

No commit, archive or push was performed. The exact unchanged baseline failure in
`arrangement-slot-ownership.spec.ts` (date-dependent Week 8 fixture) remains recorded
above and is not attributed to this lane.
