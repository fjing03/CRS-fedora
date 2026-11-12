# Review log — replacement-arrangement-top-zone

## Proposal Round 1 — 2026-11-11

Batch: `proposal.md` (new). Nothing frozen. Reviewed against
`explore-brief.md` + `prompts/replacement-arrangement-top-zone.md` (the user's
confirmation doc) + repo rules (`my-coding-rules.md` §1, `AGENTS.md`).
design.md / specs / tasks deliberately not required at this stage.

### 🔴 Outstanding

1. **"Move the controls, don't copy them" is missing from the proposal.**
   The user's doc states it as a hard requirement
   (`prompts/replacement-arrangement-top-zone.md:133` "Existing IDs and existing
   JS call sites stay working"; `:211-217` "Move the existing controls into their
   cards; keep their IDs: `subjectSelector`, `slotPicker`, `slotTrigger`,
   `slotPanel`, `buildingSelectorDropdown`, `weekSelector`, `earliestBtn`").
   `proposal.md` never says it. Today `renderTitleSummary()` writes `innerHTML`
   into `#subjectInfo` (`blade:3203`, template at `:3309-3336`) while
   `buildSubjectDropdown()` (`:3341`), `renderSlotPicker()`,
   `writeSlotTriggerFromAnchor()` (`:3142`) and the shared `VenueDropdown`
   (`:4857`) own their elements. An implementer who renders Subject/Slot markup
   inside the new template produces duplicate IDs and a subject dropdown that
   stops following selection — the single most likely way this change breaks.
   Also unstated: what `#subjectInfo` *is* in the new structure (the render mount
   vs the card wrapper). `tests/cohort-venue-replace-now.spec.ts:68` asserts
   `#subjectInfo` contains `Conflict Schedule`, so the boundary matters.
   → Add to §"What changes"/constraints: the four controls are **relocated with
   IDs unchanged**, `#subjectInfo` stays the render mount and the card heading
   stays inside it.

2. **The mobile lane's DOM contract is broken by this change and is not in the
   risk table.** `public/js/arrangement-mobile.js:109-129` is committed code:
   `RELOCATION_PLAN` moves `#subjectSelector`, `#slotPicker`,
   `#buildingSelectorDropdown`, `#venueChipGroup`, `.toolbar-filters .week-nav`,
   `#earliestBtn`, `#subjectInfo` into the mobile shell, and
   `DESKTOP_CONTAINERS` hides `.toolbar-restructure` (the hide rule is
   `blade:959`). This proposal removes `.toolbar-restructure` outright
   (success criterion 1) and moves the week nav out of `.toolbar-filters`
   (criterion 7). Result at ≤768px: `{ node: '.toolbar-filters .week-nav' }`
   stops resolving (fail-closed silent skip → the mobile sheet loses its week
   nav) and the desktop cards stay painted beside the mobile view.
   Being "desktop only" does not remove the hazard — it is a regression in the
   lane that ships first. → State the hook contract: either keep the ancestor
   marker classes (`.toolbar-restructure` / `.toolbar-filters` / `.week-nav`) as
   the relocation hooks, or include the matching `RELOCATION_PLAN` /
   `DESKTOP_CONTAINERS` update (and a mobile re-check) explicitly in this lane,
   and say which.

3. **Success criterion 10 is unsatisfiable as written.** Nine specs read the top
   zone; six break *by design*, not by accident: `.conflict-strip` is used as a
   readiness gate in `arrival-grid-no-pin.spec.ts:20,59`,
   `session-arrival-pin-guard.spec.ts:20,54,94`,
   `arrangement-earliest-week-default.spec.ts:72`, `default-pin.spec.ts:29`;
   `default-pin.spec.ts:33` queries `.conflict-strip .cs-seg`;
   `arr-slot-original-venue-sync.spec.ts:37,42,125,126` asserts `#venueCapacity`
   (the chip this proposal removes); `replacement-strip-anchor.spec.ts:23`
   expects the strip to contain `Dec 24 2026` while the new line 1 is day-first
   (`24 Dec 2026` per rule 11). Meanwhile §"In scope" only allows spec edits
   "if their selectors or assertions change" and criterion 10 promises they pass.
   → Name the affected specs, say the updates are in-scope work for this lane
   (Playwright still runs only on the user's word), and reword criterion 10 to
   "no new failures; the listed top-zone specs are updated here".

4. **Empty state is half-specified.** The user ruled the *Slot field is hidden*
   until a subject is chosen and that no "select a subject first" sentence is
   added (`prompts/replacement-arrangement-top-zone.md:51,109`). Today the strip
   prints `.cs-hint` "— Select a Subject first" / "— Then pick a Conflict Slot"
   (`blade:3318,3324`). `proposal.md` only says both cards stay visible and
   Details hides. An implementer will keep an empty Slot box plus the old hint,
   which is exactly what the user rejected. → Add the three empty-state rules to
   §"What changes" and to a success criterion.

### 🟡 Should Fix

- **The removed capacity chip's information needs a stated home.** The user's doc
  gives the venue trigger tooltip "full room name + capacity"
  (`prompts/…:94`); the proposal mentions the trigger label and dropping the
  chip but never the tooltip, so the only place the seat count survived can
  silently disappear (and `arr-slot-original-venue-sync.spec.ts:126`'s
  `data-tip /80 seats/` intent with it). One sentence fixes it.
- **Cohort full-name decision is left open with no default.** "Either add an
  additive payload field or use the label" + "needs a decision before apply"
  hands an unresolved *payload* question to design.md. State the default now
  (tooltip = cohort label, no server change) and that an additive
  `cohortFullNames` field needs the user's approval — the explore brief already
  says "ask before adding server payload".
- **Ops step missing:** `AGENTS.md` demands `php artisan view:cache` /
  `view:clear` and a restart through `scripts/dev-serve.sh` after any UI Blade
  change; add it to the criteria/constraints so verify doesn't skip it.

### ✅ What's Done Well

- Scope vs out-of-scope is clean and traceable to the brief; the mobile lane
  boundary, the apply gate and "re-check line numbers at apply time" are stated
  correctly.
- Reuse is right where it is stated: real shared `VenueDropdown` (+ opt-in label,
  no other-page drift), `ui-week-nav` + `populateWeekSelect` unformatted, no
  capacity chip, no new dependencies, tokens only, changelog in scope.
- Risk table is concrete and each row has a real mitigation (summary
  `scrollWidth` vs `clientWidth`, single reason tooltip, compact height,
  byte-identical other pages).
- Rejected alternatives are recorded with reasons — good baseline for design.md.

### 💡 Optional Suggestions

- The exact full/short summary strings and the Reason mapping live only in
  `explore-brief.md`; make sure they land in `specs/` rather than being
  re-invented in JS.
- `.badge-reason` (theme.css:1257) is the my-replacements *cancellation-reason*
  badge; wearing it for cohort and student chips overloads a shared semantic
  class. It is the user's ruling, so keep it — but if `.badge-outline` fits,
  that is the cheaper neutral chip.
- §"Why" calls the current strip "one plain line"; it is already a segmented row
  with three icons, a total badge and a head tooltip (`blade:3309-3336`). The
  real complaint is density/crowding — the sharper wording keeps design.md from
  treating the render as greenfield.

## Proposal Round 2 — 2026-11-11

Batch: `proposal.md` (revised). Nothing frozen. `explore-brief.md` is still the
baseline; design/specs/tasks do not exist yet by design. Round 2 re-checked the
four Round-1 🔴 items against the code (`blade:1289,1291-1325,3310-3345,3416-3417,4994-5016,5422-5451`,
`arrangement-mobile.js:109-129,415-430,476-488`) and the spec suite.

### Round-1 🔴 closure status

1. **Move-don't-copy + `#subjectInfo` boundary — CLOSED.** §6 st
   (`proposal.md:84-102`) states relocation with IDs unchanged, names the ten
   controls, makes `#subjectInfo` the card mount with the heading inside it, and
   forbids the `innerHTML` rebuild. That matches the code: `#subjectInfo` is an
   empty `<div>` mount (`blade:1289`), `renderTitleSummary()` writes
   `el.innerHTML = '<div class="conflict-strip">…'` (`blade:3416-3417`), and
   `cohort-venue-replace-now.spec.ts:68` needs the heading inside the mount.
2. **Mobile DOM hook contract — CLOSED.** §6 (`:96-102`) keeps
   `.toolbar-restructure` (the `DESKTOP_CONTAINERS` hide hook, `blade:959`) and
   `.toolbar-filters .week-nav` (`RELOCATION_PLAN:115`) and leaves the plan
   unchanged. Verified safe for the new nesting: relocation moves nodes by node
   identity with per-node comment markers (`arrangement-mobile.js:415-430`) and
   restores before those markers in plan order (`:476-488`), so putting
   `#subjectSelector`/`#slotPicker` inside `#subjectInfo` (plan index 8) does not
   double-move them — the children are extracted first.
3. **Unsatisfiable criterion 10 / affected specs — STILL OPEN** (see 🔴 1).
4. **Empty state — CLOSED.** §5 (`:74-83`) has all three rules and criterion 10
   (`:149-150`) asserts them, including removal of the `.cs-hint` lines.

### 🔴 Critical Issues (implementation blockers)

1. **The spec list is still one file short — criterion 11 is false again.**
   `rearrangement-toolbar-tooltips.spec.ts` is a top-zone spec and breaks *by
   design*, but it is not in the nine files at `proposal.md:117-121`.
   `:76` asserts the visible `#slotTriggerText` starts with `Week \d+ · ` (fails
   if the type token lands inside the trigger span), and `:80` asserts the
   month-first shape `Week ${n} · ${Mon} ${D}, ${YEAR}` — i.e.
   `Week 14 · Dec 24, 2026` — while the new Slot line 1 is day-first per rule 11
   (`Week 8 · 9 Nov 2026`, `explore-brief.md:51`). `:85` also asserts the venue
   trigger tip (`VENUE_TIP_RE … (Capacity: \d+ seats)`), which this change
   touches. Criterion 11 says "the listed top-zone specs are updated … no new
   failures", so verify goes red on a spec the lane never planned to touch.
   → Add `rearrangement-toolbar-tooltips.spec.ts` to §"In scope" (and say which
   of its assertions move: trigger start-shape, date shape, venue-tip wording).

2. **§1 and §3 describe the same control two different ways — the Slot trigger.**
   §1 (`:33-35`) gives a 55%-wide, 44px, **two-line** Slot field
   (`type + week/date`, then `time @ venue`); §3 (`:59-62`) says the slot picker
   "**Keep the old one-label row layout**" and that "trigger and panel both use
   the full Slot field width". A one-line label cannot be the two-line field, and
   this is the one element whose markup decides which of ten specs change
   (`#slotTriggerText` is read by `arr-slot-original-venue-sync:30,69`,
   `rearrangement-toolbar-tooltips:64,134,186`, `session-arrival-pin-guard:36,64`,
   `arrangement-conflict-autoselect:234`, `confirm-guards:154`, `default-pin:24`).
   An implementer reading §3 keeps `blade:1300`'s single `<span>` and the card
   never matches the prototype.
   → Say explicitly that §3 covers the **panel option rows**
   (`.slot-dd-item-label`, unchanged) and a **reason badge on the trigger**, and
   that the trigger itself is the two-line field from §1. (The ambiguity is
   inherited from `explore-brief.md:86-87`, whose parenthetical lists
   type+week/date+range+venue+cohorts+badge in "one row" — the card table
   `:49-52` is the authoritative one.)

### 🟡 Should Fix (non-blocking but recommended)

- **`#slotTriggerText` is missing from the keep-list.** §6 (`:86-89`) enumerates
  ten IDs but omits `#slotTriggerText` — the span whose inner formatting §1
  rewrites and which six specs read. Add it, plus `#arrVenueDescription` and
  `#slotTooltip`: removing `#venueChipGroup` is *not* the hazard (its writer
  early-returns on a missing group, `blade:4995-4996`), but dropping
  `#arrVenueDescription` silently kills the description chip the proposal says to
  keep (`blade:5007-5014` is null-guarded, so it dies without an error).
- **`cohortFullNames` now overrides the brief's own gate.** §"In scope" (`:110-112`)
  and the constraint (`:162-164`) decide to add the additive payload field, but
  `explore-brief.md:131` says "Ask before adding server payload", and the same
  sentence still says "otherwise fall back to the cohort label" (dead if the
  field ships). This moved from "open question" to "decided" without recording
  the user's word → record the approval, or default to label-as-tooltip and make
  the field an explicit opt-in.
- **The relocated Conflict card is a new visual in the mobile lane.**
  `RELOCATION_PLAN:119` moves `#subjectInfo` into
  `[data-arrangement-mobile-context-facts]`, which is the `<details>` "Original
  class" body (`partials/ui-arrangement-mobile.blade.php:3-5`). After this change
  that node is a full card (border, `--radius-lg`, padding, red
  `CONFLICT SCHEDULE` heading). The hooks still resolve (correct), but the mobile
  surface will show a red card heading inside "Original class" — unvalidated and
  owned by another lane. Decide the presentation now (e.g. neutralise the card
  chrome under `body.arrangement-mobile-mode`), not at apply time.
- **Keeping `.toolbar-filters` as a hook drags its legacy CSS into the card.**
  `blade:288-301` includes `@media (min-width:769px) and (max-width:1200px)
  { .toolbar-filters { flex: 1 0 100%; justify-content: flex-end } }`, and
  `:774-779` makes it a column at ≤768px. Criterion 9 tests 900 and 1024, where
  that rule fires inside the Replacement card's week column. State that the card
  scopes/neutralises it (the class is kept for the selector, not the layout).
- **The summary measurement must also re-run on visibility change.** Risk row
  `:181` only says `scrollWidth`/`clientWidth` + re-measure on resize; the Details
  row is hidden in the empty state (`:76-80`) and the whole card is hidden at
  ≤768px, so a measurement taken then sticks on the short form after the row
  reappears. Add "re-measure when the Details row becomes visible".

### ✅ What's Done Well

- §6 is now exactly the contract this change needed: relocation, the mount
  boundary, and the explicit "do not rebuild with `innerHTML`" (the single
  defect that would have broken subject-follow and caused duplicate IDs).
- The mobile hook answer is the right one — keep the ancestor markers, leave
  `RELOCATION_PLAN`/`DESKTOP_CONTAINERS` untouched — and it is verified safe for
  the new nesting, not just asserted.
- Round-1 🟡s were closed properly: the capacity chip's facts now have a stated
  home (criterion 6, trigger tooltip carries room name + capacity), and the ops
  step (`view:cache` / `view:clear` / `scripts/dev-serve.sh`) is in constraints.
- Empty state is now behavioural (three rules + a criterion) instead of
  aspirational, and criterion 1 correctly demotes `.toolbar-restructure` to a
  mobile-hide wrapper only.

### 💡 Optional Suggestions

- Criterion 7 (`Week 8 · 9 Nov 2026 ~ 15 Nov 2026`) looks already satisfied by
  the shared `populateWeekSelect` (`ui-common.js:2390-2398`); say "verify, do not
  re-implement" so nobody rewrites the shared label and drifts other pages.
- The `.badge-reason` overload for cohort/student chips (Round-1 💡) is still
  worth one line in design.md — it is the my-replacements cancellation-reason
  class.

### ⚖️ Review Verdict

**Not ready to move to design/specs/tasks — two edits, both small.** Round 1's
items 1, 2 and 4 are genuinely closed; item 3 is closed only if
`rearrangement-toolbar-tooltips.spec.ts` joins the update list, and the §1/§3
Slot-trigger contradiction must be resolved before design.md can encode a
trigger format. Neither needs re-exploring, unfreezing, or new code — one
filename plus one disambiguating sentence. With those, this proposal batch is
ready.

## Proposal Round 3 — 2026-11-11

Batch: `proposal.md` (revised again). Nothing frozen. `explore-brief.md` is still
the completeness baseline; design/specs/tasks do not exist yet by design. Round 3
re-verified the two Round-2 blockers against the real code
(`renderSlotPicker()`'s item builder, `slotTriggerLabel()`,
`writeSlotTriggerFromAnchor()`, `applySubjectChange()`, `extractSlotsForSubject()`,
`LectureSessionCatalog::sessionEntry()`, `arrangement-mobile.js:109-120`), the whole
top-zone spec suite, and every file in §"In scope".

Incidental finding while verifying: the blade moved ~67 lines mid-review
(`.toolbar-restructure` / `#subjectInfo` sat at 1289 → 1356 between two reads) —
another lane is editing it. Symbol names, not line numbers, are what this round
verified; the proposal's existing "re-check line numbers at apply time" constraint
already covers the drift.

### Round-2 🔴 closure status

1. **Affected-spec list short by one file — CLOSED.** `rearrangement-toolbar-tooltips.spec.ts`
   is now in §"In scope" (`proposal.md:132`) with its changed assertions named
   (`:133-135`: trigger start-shape, month-first date shape, venue-tip wording).
   Verified those are the actual breakers: `:76` `^Week \d+ · ` (the trigger will
   start with `(T)`), `:80` `Week ${n} · ${Mon} ${D}, ${YEAR}` (line 1 is day-first
   per rule 11), `:85` `VENUE_TIP_RE … (Capacity: \d+ seats)`. Also verified the list
   is now *complete*, not just longer: every spec touching `#subjectInfo`
   (`session-arrival-pin-guard`, `cohort-venue-replace-now`, `arrival-grid-no-pin`,
   `replacement-strip-anchor`, `venue-restrictions-enforcement`,
   `venue-cross-week-handoff`), every `.conflict-strip` readiness gate
   (`arrival-grid-no-pin:20,59`, `session-arrival-pin-guard:20,54,94`,
   `arrangement-earliest-week-default:72`, `default-pin:29,33`) and every
   `#venueCapacity` read (`arr-slot-original-venue-sync:37,42,125,126`) is listed.
   `arrangement-conflict-autoselect.spec.ts` reads `#slotTriggerText` but only via
   `toContain('Week 6')` (`:234-235`) and the panel row's range (`:249`) — both
   survive, so it correctly stays out.
2. **§1 vs §3 Slot trigger — CLOSED.** §3 (`:58-66`) now states the trigger *is* the
   two-line field from §1 and scopes the old layout to the open panel's
   **option rows**, which matches the code exactly (`renderSlotPicker()`'s item
   builder writes one `.slot-dd-item-label` row; the trigger is a single
   `#slotTriggerText` span). No reading of §3 can now produce a one-line trigger.

### 🔴 Critical Issues (implementation blockers)

None. Both Round-2 blockers are closed, the spec list is exhaustive, the mobile hook
contract still resolves (verified again: `RELOCATION_PLAN` moves `#subjectSelector`
and `#slotPicker` at plan indices 0-1, *before* `#subjectInfo` at 8, so nesting them
inside the mount extracts the children first and double-moves nothing), and the
additive `cohortFullNames` is genuinely schema-free —
`programmes.programme_name` exists (`database/migrations/0001_01_01_000005_…:14`)
and the relation is already eager-loaded (`LectureSessionCatalog:156`), so the field
costs no query and no migration.

### 🟡 Should Fix (non-blocking but recommended)

- **The reason mapping has no third case — and there is a live third case in the
  code.** `extractSlotsForSubject()` collects occurrences whose
  `status === 'conflict' || 'cancelled'`; the panel badge then derives
  `Public Holiday` (holiday day), `Cancelled` (`status === 'cancelled'`), else
  `Conflict` (a plain clash). §3 (`:64-65`) and criterion 8 say the badge text is
  `Public Holiday` / `Class Cancelled` only, and the brief's reason mapping has only
  those two rows — so a literal read collapses the plain-clash branch into
  `Class Cancelled` and every ordinary conflict row ships a false reason (nothing in
  the suite would catch it; no spec pins `.slot-dd-item-badge`). The same hole hits
  the card: `renderTitleSummary()`'s status IIFE returns `null` for a non-holiday,
  non-cancelled occurrence, so the Details **Reason** column is empty for exactly
  those picks and §1 gives it no empty-state rule.
  → Add one line per surface: panel rows keep the existing `Conflict` label for a
  plain clash; the Details Reason column states what it shows when the occurrence is
  a plain conflict (empty cell / dash / neutral `Conflict` badge) — whichever the
  user intends, written down.

- **The two-line trigger has one format law but three writers, and §1/§3 don't say
  so.** `slotTriggerLabel()` returns a **string** consumed via `.textContent` at
  `writeSlotTriggerFromAnchor()`, in `applySubjectChange()`
  (`textContent = slotTriggerLabel(firstConflictRow)`), and by the placeholder reset
  in `renderSlotPicker()`; the diagnostics snapshot also reads
  `#slotTriggerText` `textContent`. §1 rewrites the trigger's *format* but nothing
  says the write mechanism must change at all of them (markup inside the kept
  `#slotTriggerText` span, or a label object), so a partial edit leaves one path
  emitting the old one-liner — most likely the arrival/anchor path, which is the one
  `rearrangement-toolbar-tooltips` and `arr-slot-original-venue-sync` exercise.
  → One clause: "the trigger's format law stays in `slotTriggerLabel()`; every
  writer renders through it" (design.md then picks `innerHTML`-in-span vs a
  `{line1,line2}` return).

### ✅ What's Done Well

- The §1/§3 fix is the right shape: it resolves the ambiguity by *scoping* §3 to the
  panel rows instead of re-wording §1, so the authoritative visual contract (the
  card table) stays the single source for the trigger. It also correctly leaves the
  panel's `.slot-dd-item-label` untouched, which is what keeps
  `arrangement-conflict-autoselect:249` and `rearrangement-toolbar-tooltips:103`
  alive.
- The spec entry is actionable, not just a filename: naming the three moving
  assertions is what lets verify distinguish "expected change" from regression, and
  it lines up with what the file actually asserts.
- The keep-list now carries the two IDs that die silently: `#arrVenueDescription`
  (null-guarded writer → the description chip would vanish with no error) and
  `#slotTooltip`, plus `#slotTriggerText` itself.
- Every Round-2 🟡 was answered in the *behavioural* section rather than the risk
  table — mobile chrome neutralisation in §6 (`:107-109`), `.toolbar-filters` demoted
  to a hook "not a layout" (`:105-106`), summary "re-measure when the row becomes
  visible" (`:38`), and the cohort full-name decision recorded (`:120-123`, with the
  label fallback still standing).
- Feasibility spot-check passed where the proposal makes a claim: `cohortFullNames`
  is additive-only, the venue chip group survives without `#venueCapacity`, and the
  mobile relocation hooks are byte-unchanged.

### 💡 Optional Suggestions

- Keep `Select a slot to replace` as the empty trigger text. Two listed specs depend
  on it (`rearrangement-toolbar-tooltips:135` `toHaveText`, `default-pin:24`
  `not.toHaveText`) — a two-line trigger is an easy place to "improve" the
  placeholder by accident. `toHaveText` does not require visibility, so hiding the
  Slot field in the empty state is safe as long as the text stays.
- `cohort-venue-replace-now.spec.ts:68` asserts `#subjectInfo` `toContainText('Conflict
  Schedule')`. Today `.cs-head` has no `text-transform`; the new heading is
  "uppercase". Keep the DOM text `Conflict Schedule` and uppercase it in CSS, or the
  assertion needs `{ ignoreCase: true }`. The file is already listed, so this is only
  about not creating avoidable churn.
- Six specs use `.conflict-strip` as a readiness gate. Naming the replacement
  selector (e.g. the card root or the Details summary) keeps them from inventing six
  different gates at apply time.
- Mobile: §6 hides the desktop heading and the controls are relocated out, which
  leaves the **Details row** as the only thing the moved `#subjectInfo` carries into
  the "Original class" `<details>`. Say whether that row is wanted there or hidden
  under `body.arrangement-mobile-mode` too — one clause, and it stops the mobile lane
  from inheriting a stray expander.
- Risk row `:195` still says "re-measure on resize" while §1 (`:38`) now says
  resize **and** visibility. Align the row so verify does not test the weaker claim.

### ⚖️ Review Verdict

**Ready to move to design/specs/tasks — zero blockers.** Both Round-2 🔴s are closed
for the right reasons (verified in code, not just in prose), the affected-spec list is
now provably exhaustive, and no new contradiction was introduced. The two 🟡s are
declarative, not decision-level: the missing plain-conflict case in the reason mapping
and the "every `slotTriggerLabel()` writer" clause. Either can be closed with one
sentence in `proposal.md`, or captured as a design.md decision before implementation —
they do not need re-exploring or unfreezing, and neither should hold this batch.

## Design Round 1 — 2026-11-11 (batch 2)

Batch: `design.md` (new). **`proposal.md` is frozen**; specs/tasks do not exist
yet by design. Reviewed against `explore-brief.md`, the frozen proposal,
`prompts/replacement-arrangement-top-zone.md` (the user's confirmation doc), the
prototype (`../../ui-prototype/replacement-arrangement-top-zone-preview.html`),
the real code (`blade:115-364,1627-1663,3555-3811,4457-4497,4711-4864,4900-4966,
5329-5341,5615-5640,5779-5847`; `ui-common.js:69-82,3838-3852,4267-4308`;
`arrangement-mobile.js:109-129,1241-1250`; `LectureSessionCatalog:156,236,
341-353,645-654`; `ReplacementArrangement:275-281,441-443`) and the full
top-zone spec suite.

### 🔴 Critical Issues (implementation blockers)

1. **The two-line trigger cannot be produced on the anchor/arrival path.**
   `design.md:198-201` makes `writeSlotTriggerFromAnchor()` one of the three
   writers of the new format, but the code calls it with a reduced object —
   `{week, start, end, date}` only (`blade:3616`), no `type`, no `day`, no
   `venue`. `anchorSlotFromPinned()` has all three (`blade:3591-3605`); they are
   simply not forwarded. Result: every `?session=` arrival renders
   `() Week 14 · 24 Dec 2026` with no weekday and no `@ venue`, and three
   in-scope specs assert exactly that (`arrival-grid-no-pin:29-31` `(P)`/`B011`,
   `session-arrival-pin-guard:24,27,57,59`, `replacement-strip-anchor:27`).
   → Require every writer to pass the **full slot object** and forward `a` as-is.
2. **The two-line trigger's structure and the CSS it supersedes are unstated —
   and the live rules force one line.** §5 keeps a single `#slotTriggerText`
   span; the live CSS caps it (`.slot-dd { flex:1 1 288px; max-width:384px }`
   `blade:315-319`; `.slot-dd-trigger { height:36px }` `:320-337`;
   `.slot-dd-trigger span { overflow:hidden; text-overflow:ellipsis;
   white-space:nowrap }` `:338-342`) and the panel is `min-width:512px`
   (`:351-364`), which exceeds a ~450px field at the 900px width criterion 9
   tests. The prototype neutralises all four and uses
   `#slotTriggerText > span#slotTriggerLine1 + span#slotTriggerLine2`
   (prototype:189-198,201-217,631-634). → State the child-span structure, the
   flex-column container, and each superseded rule (`min-width:512px` →
   `width:100%; min-width:0`).
3. **The Reason column has no source for the virtual pinned anchor.**
   §3.5 derives the reason from `extractSlotsForSubject()` slot objects, which
   always carry `status` (`blade:4736`); but on arrivals the anchor is
   `anchorSlotFromPinned()` with **no `status`** (`blade:3591-3605`), so it
   matches none of §3.4's three cases and the Reason cell ships blank on the
   primary handoff path (including the holiday occurrences that caused the
   handoff). The honest derivation already exists in `statusTip`
   (`blade:3721-3762`: cancelled+reason from `MockData.cohortTimetable`, then
   `MockData.holidays`; `holidayLabel` is real, `ui-common.js:69-82`).
   → Point the Reason at that derivation for both real slots and the anchor, and
   state the no-fact case; also state the summary's `reason` token for a plain
   clash (else the full/short strings start with `" · "`).
4. **§8's spec map under-declares three in-scope files, so "no new failures" is
   false as written.** `arr-slot-original-venue-sync.spec.ts` asserts
   `.venue-dd-label` `toHaveText(/^B0\d\d? — /)` at
   `:29,36,41,48,56,62,67,75,78,87,98` (11 assertions) and reads the label text
   at `:121` — none survive the new `B111` + `35 seats` label, and §8 only names
   `#venueCapacity` for this file; `:91` also pins
   `/Replacement VENUE Selector: B111 — /` on the tip.
   `venue-cross-week-handoff.spec.ts:48` pins `Mon, Nov 9 2026` (day-first, new)
   and `:50` pins `1:00 PM @ B110`, which line 2's `1:00 PM ~ 2:00 PM @ B110`
   breaks. → Add the `.venue-dd-label` family and the `range @ venue` contiguity
   to §8. (`default-pin:33`'s `.cs-seg[1]` read is vacuous (`void seg2`), so the
   readiness-gate note does cover it — good call.)

### 🟡 Should Fix (non-blocking but recommended)

- **`.toolbar-restructure` scope contradicts the frozen proposal and the
  design's own rule.** `:62` says "around the two desktop cards"; the §1 tree
  (`:41-52`) puts `#subjectInfo` outside it; proposal §6 (`:105-106`) says it
  wraps both. Not cosmetic: `DESKTOP_CONTAINERS` is the only desktop hide rule
  (`arrangement-mobile.js:124-129`), so in the design's tree the Conflict card
  is hidden on mobile by relocation only, and the proposal's tree would make the
  wrapper a flex row (`blade:173-179`). → Keep the sibling layout, amend the
  proposal's one sentence via soft-freeze, and state that the Conflict card's
  mobile hiding is relocation-owned.
- **The mobile chrome neutralisation targets the wrong element.** §7
  (`:234-236`) scopes `body.arrangement-mobile-mode #subjectInfo` for
  border/radius/shadow/padding, but in the §1 tree the chrome is on the child
  `.ra-block` → the rule no-ops and mobile inherits a red bordered card inside
  "Original class". Put the card classes on the mount or scope to
  `#subjectInfo .ra-block` (same for the heading rule).
- **The venue renderer also rewrites the tooltip, reverting an earlier ruling.**
  §4 (`:170-175`) returns `{html, tip}` with `Tutorial Room (35 seats)`, dropping
  the live `Replacement VENUE Selector: … (Capacity: N seats)` shape
  (`blade:5338`, `ui-common.js:4302-4308`; asserted at
  `arr-slot-original-venue-sync:91`, `rearrangement-toolbar-tooltips:28,85`).
  The proposal only requires "full room name + capacity" (`proposal:163`), which
  the existing tip already carries. → Renderer returns only `html`,
  `_updateTrigger()`'s tip stays byte-identical — or record the prototype-parity
  intent and name both assertions.
- **The two-part venue label feeds the mobile snapshot — the separator is
  load-bearing.** `.venue-dd-label` is read as `controls.venue.text`
  (`blade:5782-5783,5820-5824`) and painted into the mobile blue span
  (`arrangement-mobile.js:1241-1250`); `snapshotText()` concatenates child text
  with no inserted space (`blade:5615-5626`), so
  `<span>B111</span><span>35 seats</span>` becomes `B11135 seats`. Keep a literal
  space in the html, or record the mobile text change.
- **Two owners for the trigger text.** §3.2 (`:116`) has
  `renderTitleSummary()` write `#slotTriggerText`; §5 (`:196-201`) makes
  `slotTriggerLabel()` the single law with three writers. Today
  `renderTitleSummary` never touches the trigger (`blade:3781-3808`), and the
  placeholder reset inside `renderSlotPicker()` (`:4771`) is deliberately
  ordered before the writers. → Keep the trigger out of `renderTitleSummary`, or
  route it through the same full-slot writer.
- **Summary measurement needs the write-full-then-measure sequence and a named
  trigger.** Prototype: `textContent = full; if (scrollWidth > clientWidth)
  short` (`prototype:940-943`). §3.4's "measure after render" without that order
  means the full form never returns when the card widens; also name the
  visibility mechanism (rAF after unhide / `ResizeObserver`) and the CSS that
  makes the comparison meaningful (`white-space:nowrap; overflow:hidden`); the
  user doc asks for a **debounced** resize re-render (`prompts/…:267`).
- **Details re-open rule missing.** The prototype re-expands when the row
  reappears after a no-slot state but keeps a manual collapse across slot
  changes (`prototype:912-918`). Unstated, the renderer will either re-open on
  every render or never.
- **Panel row date shape.** Live rows are month-first, no weekday
  (`blade:4805,4835` → `Week 14 · Dec 24, 2026`; asserted
  `rearrangement-toolbar-tooltips:103`); the prototype's row is
  `Week 8 · Wed, 11 Nov 2026` (`prototype:1086-1089`). "Keep the old row" picks
  the former — say so, or the panel and the trigger will disagree about the same
  slot and `:103` becomes accidental churn.
- **§11 omits two in-scope deliverables.** No changelog entry
  (`page-changelogs/replacement-arrangement-changelog.md`, `proposal:129`) and no
  mention of the catalog test (`proposal:127`;
  `tests/Feature/LectureSessionCatalogTest.php`). Also state that
  `renderTitleSummary()` keeps its `refreshArrangementMobile('original')` tail
  (`blade:3810`) — it is the only `'original'` refresh, so losing it freezes the
  mobile compact-context text.

### ✅ What's Done Well

- The mount contract is right and enforced where the real hazards are: static
  selectors, dynamic-only writes (`#detailsSummary`/`#detailsBody`/visibility),
  `.no-subject` kept, heading tip removed — matching proposal §6 against
  `blade:3781` (`innerHTML`), `:3810` (mobile refresh) and `:3721` (statusTip
  precedence). The four new IDs are collision-free.
- Mobile hooks answered correctly: plan untouched, the child-before-mount nesting
  order is safe (`arrangement-mobile.js:109-129`), `Select a slot to replace`
  preserved for `rearrangement-toolbar-tooltips:135`/`default-pin:24`, and
  `.toolbar-filters` demoted to a hook with its 769–1200px rule scoped
  (`blade:288-301`).
- The venue opt-in is better than the prototype's page-local observer
  (`prototype:1126-1136`): putting the renderer inside `_updateTrigger()`
  (`ui-common.js:4267-4308`) covers the constructor, `select()` and the whole
  slot-sync chain (`blade:5003`) with no observer, other pages byte-identical.
- Plain-clash handling is exactly the ruling on both surfaces — neutral
  `badge-reason` + `Conflict`, no detail, never `Class Cancelled` — and it
  correctly refuses the prototype, which collapses clashes into `Class Cancelled`
  (`prototype:881-895`) and paints the panel's `Conflict` red (`:1075-1078`).
- `cohortFullNames` is genuinely additive: every field exists
  (`Cohort::current_year/semester/tutorial_group`, `Programme::programme_name`),
  `cohorts.programme` is already eager-loaded on the only path
  (`ReplacementArrangement:276`, `LectureSessionCatalog:156`), `courseCtx()`'s
  `Object.assign` (`blade:3565`) carries a session-level field with no third
  place to patch, and the label fallback is stated. `weekData[].days[].
  holidayLabel` is real (`ui-common.js:69-82`), so §3.5's source is correct.
- Ops pair present and correct (`view:cache`/`view:clear` + `scripts/dev-serve.sh`);
  `filemtime` cache-busting means the `ui-common.js` edit needs no version bump.

### 💡 Optional Suggestions

- The front-matter "visual contract" path resolves to nothing from the change
  dir; from `.sdd/changes/<change>/` it is
  `../../../../ui-prototype/replacement-arrangement-top-zone-preview.html`. Fix
  it so the implementer opens the contract instead of re-deriving it.
- One line in §2 that `badge-reason` is the my-replacements
  **cancellation-reason** class (`theme.css:1261`), reused for cohort/student
  chips by the user's ruling.
- §3.1 ships `#details` `open`/`aria-expanded="true"`/visible while the empty
  state requires it hidden; state that the static default is hidden and the
  renderer unhides on the first slot (avoids an empty Details flash before boot
  JS).
- `.ra-*` names are collision-free across blades/CSS, so the prototype's class
  names can be adopted verbatim.

### ⚖️ Review Verdict

**Not ready for specs/tasks — three behavioural holes and one spec-map hole, all
closeable inside `design.md`.** 🔴 1 and 🔴 3 would ship a visibly degraded card
on the handoff path and take two-to-three in-scope specs with them; 🔴 2 is the
lane's visual core (a literal read of §5 produces a one-line trigger, and the
512px panel contradicts criterion 9); 🔴 4 is a paragraph of spec scope. No
re-explore, no new code, no unfreezing needed — except the one declarative
proposal sentence in 🟡 1 (`.toolbar-restructure` scope), which can ride a
soft-freeze. Close these and this batch is ready for `specs/` + `tasks/`.

## Design Round 2 — 2026-11-11 (batch 2)

Batch: `design.md` (revised). **`proposal.md` is frozen**; specs/tasks still do not
exist. Re-reviewed against `explore-brief.md`, the frozen proposal,
`prompts/replacement-arrangement-top-zone.md`, the prototype
(`ui-prototype/replacement-arrangement-top-zone-preview.html`), and the live code
(`blade:200-236,313-364,1627,1636-1642,3726-3798,3871-3912,4619-4622,5099-5120,
5278-5293,5513-5535,5769-5794,5936-5978`; `ui-common.js:4267-4310`;
`arrangement-mobile.js:109-129`; the top-zone spec suite). Line numbers drifted
again mid-review (`#subjectInfo` mount now `blade:1627`, was 1356) — every finding
below is anchored to a symbol, not a line.

### Round-1 🔴 closure status

1. **Anchor writer must pass the full slot object — CLOSED.** §5 (`:229-235`)
   names the full slot shape (`type, day, date, week, start, end, venue`) and
   explicitly forbids the reduced `{week,start,end,date}` at
   `writeSlotTriggerFromAnchor()`. Verified that is exactly the live defect
   (`blade:3766` drops `type`/`day`/`venue` although `anchorSlotFromPinned()`
   returns all three, `:3741-3755`). The fix is stated at the right level.
   *But the writer enumeration it introduces is short by one file-level writer —
   see 🔴 1.*
2. **Two-line structure + CSS supersede — CLOSED.** §5 (`:215-224`) states the
   `#slotTriggerLine1` / `#slotTriggerLine2` child spans, the column container, the
   44px trigger, and supersedes all four live rules — `.slot-dd`
   `flex:1 1 288px; max-width:384px` (`blade:315-319`), `.slot-dd-trigger`
   `height:36px` (`:320-324`), `.slot-dd-trigger span` nowrap/ellipsis
   (`:338-342`), `.slot-dd-panel` `min-width:512px` (`:351-356`) → `width:100%;
   min-width:0`. Nothing in the live block is left standing. *Two CSS precision
   gaps remain — see 🟡 2.*
3. **Reason source for the virtual anchor + plain-clash token + no-fact case —
   CLOSED as scoped.** §3.5 (`:173-176`) points the Reason at the existing
   `statusTip` derivation for **both** real slots and `anchorSlotFromPinned()`
   (verified: that IIFE reads `MockData.cohortTimetable` then `MockData.holidays`
   and returns `null` for a plain clash, `blade:3871-3912`), forbids a blank
   Reason on the handoff path, and §3.4 (`:154-157`) gives the plain-clash token
   (`Conflict`, no detail) and the no-fact fallback. *The predicate that separates
   "plain clash" from "no fact" still misclassifies the anchor — see 🟡 1.*
4. **Spec map: `.venue-dd-label` family + `range @ venue` contiguity — CLOSED.**
   §8 (`:280-285`) names the `.venue-dd-label` assertions
   (`arr-slot-original-venue-sync:29,36,41,48,56,62,67,75,78,87,98`, plus the
   `:121` text read) and `venue-cross-week-handoff`'s date/venue contiguity →
   `1:00 PM ~ 2:00 PM @ B110`. Verified the file-level facts: the tip assertion
   at `:90-91` (`/Replacement VENUE Selector: B111 — /`) genuinely survives,
   because `_updateTrigger()` builds the tip from `v.code` + `tipBase`
   (`ui-common.js:4299-4308`) *independently of the label*, so "renderer returns
   only `html`, tip byte-identical" is not just intent — it works.

### 🔴 Critical Issues (implementation blockers)

1. **The writer list is short by one — the panel-pick writer.**
   §5 (`:229-235`) presents three writers ("Every writer renders through it with
   the **full slot object**"): the anchor, `applySubjectChange()`, and the
   `renderSlotPicker()` placeholder reset. There is a **fourth**:
   `commitSlotSelection()` writes the trigger directly —
   `triggerText.textContent = slotTriggerLabel(slot)` (`blade:5285`) — and it is
   the **user-pick path** (`selectSlot()` → `commitSlotSelection`, `:5090`), plus
   the confirm-cancel restore path (`:5069`). The function's own comment even
   says so: "ONE format for the two writers (a user pick and the post-switch
   auto-pin)" (`:5093-5094`).
   With §5's `slotTriggerLabel()` returning `{line1, line2}`, the untouched
   `:5285` becomes `.textContent = {…}` → the Slot field prints
   **`[object Object]` after every panel pick**. And it would ship *silently*:
   `rearrangement-toolbar-tooltips:98-100` guards with `if (pickedWeek)`,
   `arrangement-conflict-autoselect:234` only asserts `toContain('Week 6')`
   (satisfied by the static markup in the empty state is *not* guaranteed after a
   pick), and `confirm-guards:154-159` compares the element to itself.
   → Change the bullet list to "**every** writer of `#slotTriggerText`, including
   `commitSlotSelection()`" and/or state that `slotTriggerLabel()` keeps returning
   a string that the writers inject as markup, so a missed site degrades to a
   formatting bug rather than `[object Object]`. (Verified there are exactly four
   assignment sites: `:3766`, `:4619/4621`, `:5079`, `:5285`.)

### 🟡 Should Fix (non-blocking but recommended)

- **Blocker 3's residual: the plain-clash predicate cannot classify the anchor.**
  §3.5 (`:176`) defines "Plain clash = `status === 'conflict'` with no holiday and
  no cancellation". The virtual anchor carries **no `status` at all**
  (`blade:3741-3755`), and §3.5 simultaneously says to treat real slots and the
  anchor alike (`:173-174`). Literal read: the anchor falls through to §3.4's
  "no fact at all → no badge / dash" (`:157`), while the *same occurrence* picked
  from the panel shows the neutral `Conflict` badge (`:154-156`, panel `:246`).
  So on the primary handoff path an ordinary clash shows `—` in the Reason column
  and `Conflict` in the panel row. → Define plain clash by **absence of facts**
  ("no holiday and no cancellation → `Conflict`"), which makes the no-fact branch
  unreachable except defensively. One clause; no new decision.
- **The flex-column is attached to the wrong node, and the container is missing
  `min-width: 0`.** §5 (`:220-221`) says "The line spans use `display: flex;
  flex-direction: column`; each line ellipsizes on its own." Prototype ground
  truth is the opposite shape: `.ra-slot-lines` — the **container**
  (`#slotTriggerText`) — is the flex column with `flex:1; min-width:0; gap:1px`,
  and its `> span` children carry the nowrap/ellipsis
  (`prototype:201-212,427-431`). Applied literally to the children, line 2
  (`1:00 PM ~ 2:00 PM @ <pill>`) stacks into two rows; without `min-width:0` on
  the container the nowrap children cannot shrink and the trigger overflows its
  own box at the 900px width criterion 9 tests. Also say the nested venue-pill
  span must not inherit the `.slot-dd-trigger span` ellipsis rule. → Name the
  container explicitly.
- **How the venue opt-in renderer is written is unstated.** `_updateTrigger()`
  writes the label with `label.textContent = text` (`ui-common.js:4293`). §4
  (`:191-201`) shows the renderer returning an **HTML string** and requires the
  literal space, but never says the label assignment switches to `innerHTML`
  when `opts.triggerLabel` is present. An implementer who keeps `textContent`
  prints raw markup into `.venue-dd-label` (and into the mobile blue span, which
  reads the same node). One sentence.
- **Spec-map precision: three listed-file reads still pin the old joined shape.**
  §8 covers start-shape and day-first, but not that the ` · ` between date and
  range disappears (line break instead) on the trigger string:
  `arr-slot-original-venue-sync:30,69` and `replacement-strip-anchor:27`
  (`toContainText('Week 14 · Dec 24, 2026 · 12:00 PM ~ 1:00 PM')`),
  `replacement-strip-anchor:23` (`#subjectInfo` textContent `Dec 24 2026` →
  `24 Dec 2026`; the trigger is now *inside* the mount so this read picks up the
  two-line text), and `venue-cross-week-handoff:47-50` (`#subjectInfo` innerText
  `Mon, Nov 9 2026` → day-first). All four files are already listed; naming the
  exact reads stops four different re-derivations at apply time. (Non-blocking:
  the generic "month-first → day-first" clause arguably reaches them.)
- **The mobile-mode block that restyles the old strip becomes dead, and the old
  wrapper class doubles the card gap.** `blade:1394-1411` targets
  `#arrangementMobile #subjectInfo .conflict-strip / .cs-head / .cs-seg / .cs-val
  / .cs-badge` (wrap, `white-space: normal`, `overflow-wrap: anywhere`) — after
  this change every selector no-ops, so the mobile "Original class" loses its
  wrap protections and the mobile lane inherits dead CSS. Separately, the mount
  keeps `class="conflict-strip-wrap"` (`blade:1627`; CSS `:200-202` = `width:100%;
  margin: 0 0 12px`), which stacks on top of `.toolbar-restructure`'s 12px column
  gap and makes the card gap 24px against the visual contract. → Say what
  happens to both (replace the mobile block with the `#subjectInfo .ra-block`
  rules, and drop/replace `.conflict-strip-wrap`).

### ✅ What's Done Well

- Every Round-1 🟡 was closed in the *behavioural* sections, not the risk table:
  `_updateTrigger()` keeps the tooltip byte-identical (verified to be
  structurally safe, see closure 4), the literal-space rule for the two-part
  venue label is stated with its reason (mobile `snapshotText()` inserts nothing,
  `blade:5769-5779`), `renderTitleSummary()` is explicitly barred from writing
  `#slotTriggerText` (§3.2 `:122`), and the summary section now carries the
  write-full-then-measure order, debounce, visibility re-measure and the
  `white-space:nowrap; overflow:hidden` precondition (`:143-148`).
- The `.toolbar-restructure` question is resolved the cheap way: §1 (`:27-41`)
  now nests **both** cards inside the wrapper, so the frozen proposal's §6
  sentence is satisfied as written and no soft-freeze is needed; §1 (`:62-63`)
  still says the wrapper changes from row to column and that relocation moves
  `#subjectInfo` out first.
- The four new IDs are collision-free in this codebase (`#details`,
  `#detailsToggle`, `#detailsSummary`, `#detailsBody` — no `id="details…"` exists
  anywhere under `resources/views`), so no duplicate-ID hazard.
- Removing `#venueCapacity` is genuinely safe, not just intended:
  `__fillVenueChips()` null-guards it (`blade:5518-5519`), and
  `arr-slot-original-venue-sync:121-123` splits the label on the first space, so
  `B111` survives the new `B111 35 seats` label unchanged.
- §11 now carries the changelog entry, the `LectureSessionCatalogTest.php`
  addition and the `refreshArrangementMobile('original')` tail (`:320-323`) — all
  three were silent-loss risks. The front-matter visual-contract path now
  resolves (`../../../../ui-prototype/…` from the change dir).
- Plain-clash handling on both surfaces is still the right ruling (neutral
  `badge-reason` + `Conflict`, no detail, never `Class Cancelled`) and still
  refuses the prototype's collapse into `Class Cancelled`
  (`prototype:881-895`).

### 💡 Optional Suggestions

- `controls.slot.text` / `subjectInfoText` in the dev snapshot
  (`blade:5962,5970-5973`) have **no consumer** (searched the whole repo). So the
  two-line trigger needs no separator gymnastics for the snapshot — only the
  venue label's space matters, and that one is already specified. Worth one line
  so nobody adds a defensive `' '` join to the trigger markup.
- The weekday for line 1 on a real pick has an obvious precedent —
  `slotDayAbbrs[slot.day]`, already used by `applySlotTip()` (`blade:5119`); the
  anchor carries `weekdayName` (`:3750`). Naming it removes the last reason to
  touch `renderTitleSummary`'s `slotLine` derivation (`:3852-3860`), which the
  design no longer needs.

### ⚖️ Review Verdict

**One blocking edit, then ready for `specs/` + `tasks/`.** Round-1's four 🔴s are
genuinely closed (three verified against live code, not just prose), and the
Round-1 🟡s came with it. The single new blocker is a one-line addition to §5:
`commitSlotSelection()` is the fourth writer of `#slotTriggerText`
(`blade:5285`), and with the object return it would print `[object Object]` on
the most-used path — the same "reduced/partial writer" failure the design's own
risk table warns about (`:315`). The five 🟡s are declarative (anchor
plain-clash predicate, flex-column target, `innerHTML` for the venue label,
three spec-read names, dead mobile CSS + `.conflict-strip-wrap` margin); none
needs re-exploring, unfreezing, or new code. Close 🔴 1 plus 🟡 1 and this batch
is ready.

## Design Round 3 — 2026-11-11 (batch 2)

Batch: `design.md` (revised). **`proposal.md` is frozen**; specs/tasks still do not
exist. Re-reviewed against `explore-brief.md`, the frozen proposal,
`prompts/replacement-arrangement-top-zone.md`, the prototype and the live code
(`slotTriggerLabel` / `writeSlotTriggerFromAnchor` / `applySubjectChange` /
`renderSlotPicker` / `selectSlot` / `commitSlotSelection` / `statusTip` / the panel
item builder in `replacement-arrangement.blade.php`; `ui-common.js:3816-3827,
4267-4310`; `arrangement-mobile.js:119`; the top-zone spec suite). Line numbers
drifted again mid-review (the mount moved `blade:1627` → `:1634` between two
reads) — every finding is anchored to a symbol.

### Round-2 🔴 closure status

1. **Fourth writer / `[object Object]` — CLOSED.** §5 (`:237-247`) makes
   `writeSlotTrigger(slot)` the only writer of `#slotTriggerText`, forbids
   `textContent = slotTriggerLabel(...)` outright, and names
   `commitSlotSelection()` (`blade:5418`, the user-pick path) plus the full-slot-
   object rule. The object return can no longer reach `textContent`. *The roster
   is still one site short and one bullet points at the wrong function — see 🟡 1.*

2. Round-2 optional items — all six landed, all verified in code, none cosmetic:
   - plain clash by **absence of facts** (`:177-179`) closes the anchor
     misclassification (`anchorSlotFromPinned()` carries no `status`,
     `blade:3881-3895`);
   - `#slotTriggerText` named as the flex-column container with `flex:1;
     min-width:0`, its direct `> span` children ellipsizing, and the nested venue
     pill exempted from the `.slot-dd-trigger span` rule (`:225-231`) — matches
     the prototype's `.ra-slot-lines`;
   - venue label `innerHTML` opt-in stated (`:200-202`), warm-path tip untouched;
   - the four exact spec reads named (`:303-308`) and correct:
     `arr-slot-original-venue-sync:30,69` and `replacement-strip-anchor:27` are the
     joined-string reads, `replacement-strip-anchor:23` is
     `toContain('Dec 24 2026')` (fed today by `renderTitleSummary()`'s `slotLine`,
     `blade:4000` — comma-less, unlike the trigger), `venue-cross-week-handoff:48`
     is the `Mon, Nov 9 2026` read;
   - dead mobile block + wrapper margin (`:285-290`) — the block is
     `blade:1401-1417`, `.conflict-strip-wrap` is `blade:200-202` on the mount
     `:1634`;
   - weekday source (`:236-238`) — `slotDayAbbrs` (`blade:5003`) and the anchor's
     `weekdayName` (`:3890`) are both `'Wed'`-shaped, so one formatter works;
   - `renderTitleSummary()` no longer derives `slotLine` (`:122-123`), and nothing
     outside it reads `slotLine` (`blade:3982,4000,4084-4085` only).

### 🔴 Critical Issues (implementation blockers)

None. The Round-2 blocker is genuinely closed — the fix is a single writer that
takes the full slot object, which is the right level — and no new decision-level
defect was introduced. The six items below are declarative: each is one clause in
`sections` that already exist, and none needs re-exploring, unfreezing, or code.

### 🟡 Should Fix (non-blocking but recommended)

- **The writer roster is still one site short, and one bullet names the wrong
  function.** §5 (`:245`) says `commitSlotSelection()` covers "the user-pick path
  and the confirm-cancel restore". Only the first half lives there: the reject
  handler's `if` branch re-enters `commitSlotSelection()`
  (`blade:5202`), but its `else` branch writes the trigger **directly** —
  `document.getElementById('slotTriggerText').textContent = 'Select a slot to
  replace'` (`blade:5219`) — inside `selectSlot()`. That is the fifth assignment
  site; the other four are `writeSlotTriggerFromAnchor()` (`:3892`),
  `applySubjectChange()` (`:4759`), `renderSlotPicker()` (`:5065`) and
  `commitSlotSelection()` (`:5418`). Left alone it replaces the two child spans
  with one text node, so the next `writeSlotTrigger()` writes into missing
  `#slotTriggerLine1/2` (throw, or a half-written trigger), and a throw there
  skips the rest of `commitSlotSelection` (`applySlotTip`, the panel `.selected`
  state, `renderTitleSummary()`). Same defect family as Round 2's 🔴 — a writer
  outside the roster — minus the visible garbage. → Make the rule state-based
  instead of list-based: *every* write of `#slotTriggerText` goes through
  `writeSlotTrigger()` (both placeholders pass `null`), and let
  `writeSlotTrigger()` own the two child spans
  (`el.innerHTML = '<span id="slotTriggerLine1">…</span><span
  id="slotTriggerLine2">…</span>')` so a missed site degrades to a formatting bug
  rather than a dead trigger.

- **`writeSlotTriggerFromAnchor()`'s boolean is load-bearing and §5 doesn't
  protect it.** §5 (`:241-243`) says "forward `anchorSlotFromPinned()` as-is". The
  literal implementation is `return writeSlotTrigger(anchorSlotFromPinned())` →
  `undefined`, but the confirm-reject path branches on the result:
  `if (!writeSlotTriggerFromAnchor()) { …placeholder… }` (`blade:5211`). With
  `undefined`, the placeholder overwrites a valid anchor trigger on every
  cancelled pick. → Same clause: `return false` when `anchorSlotFromPinned()` is
  `null`, `return true` after writing (the function's own contract comment,
  `blade:3898-3901`, says exactly this).

- **Line 1 cannot be "text" if the type token must be blue.** §5 (`:238-239`) says
  `writeSlotTrigger()` writes "(line 1 text, line 2 HTML)", but §2 (`:85`), the
  brief (`explore-brief.md:51`) and proposal §1 all require *only* the `(T)` token
  coloured. `textContent` on line 1 makes that impossible, and the implementer
  follows the writer section, not the styling table. → Say both lines are HTML
  (`<span class="ra-slot-type">(T)</span> Week 8 · Wed, 11 Nov 2026`), and keep
  the empty-state placeholder a single text node so
  `rearrangement-toolbar-tooltips:135`'s `toHaveText` stays exact.

- **Two derivations for one visible fact — card and picker can disagree.** §3.5
  (`:173-175`) sends the card's Reason through `statusTip`, whose precedence is
  **cancelled before holiday** (`blade:4037-4049`, with the explicit "exception
  beats holiday" note at `:4006-4008`); §5 (`:251-263`) keeps the panel's own
  logic, which is **holiday before cancelled** (`blade:5084-5096`). A class
  cancelled on a holiday day would read `Public Holiday` in the picker row and
  `Class Cancelled` in the card — and proposal success criterion 8 ("slot picker
  badges and tooltips match the Reason badge wording") fails on exactly that
  overlap. It also leaves the *mechanism* open: the row badge is inline-styled
  today (`statusBg`/`statusColor`, `blade:5130`), while §2/§5 speak of
  `badge-holiday`/`badge-reason` *families*. → One shared `reasonFacts(slot)`
  returning `{label, detail}` (cancelled first) consumed by the card, the panel
  row and both `data-tip`s; say whether the row keeps the inline vars or adopts
  the shared classes.

- **The venue opt-in skips two facts about `_updateTrigger()`.** (a) The
  constructor copies options field by field (`this.triggerTipPrefix =
  opts.triggerTipPrefix || null`, `ui-common.js:3816-3827`) — there is no
  `this.opts`, so "when `opts.triggerLabel` is present" needs an explicit
  `this.triggerLabel = opts.triggerLabel || null`. (b) The no-selection early
  return writes `'Select a venue'` with `textContent` and has no venue object to
  hand the renderer (`ui-common.js:4271-4275`) — say it keeps `textContent`, or an
  implementer routes it through the renderer and throws at boot.

- **§3.4 now states two rules for the same input.** §3.5 (`:177-179`) defines a
  plain clash as "*no holiday and no cancellation facts*", i.e. the absence of
  facts; §3.4's last bullet (`:158`) then says "no fact at all → no badge / dash".
  §3.5 calls the no-fact branch "defensive only", but the §3.4 bullets are ordered
  plain-clash-first, which is how the blank-Reason hole Round 2 closed gets
  re-opened by a literal read. → Collapse to one rule: absence of facts →
  `Conflict`; no slot at all → Details hidden (nothing else).

### ✅ What's Done Well

- The Round-2 blocker was closed at the right level — one writer, one format law,
  full slot object — instead of patching the one call site that printed
  `[object Object]`. That also removed the `slotLine` derivation from
  `renderTitleSummary()`, which is what keeps §3.2's "dynamic parts only" rule
  honest.
- Every Round-2 🟡 came back as behaviour, not as a risk-table row, and each one
  checks out against the code: the flex-column/min-width/pill-exemption trio
  matches the prototype and the live `.slot-dd` rules it supersedes; the `innerHTML`
  opt-in names the exact branch; the dead mobile block and the `.conflict-strip-wrap`
  double gap are named with their consequences.
- §8 is now actionable rather than a filename list: the four previously-implicit
  reads are pinned with line numbers, and each one is the real breaker. (Spot-checked
  four of them; all correct.) That is what lets verify separate "expected change"
  from regression, which was the whole point of the earlier spec-map findings.
- The plain-clash ruling is still the correct refusal of the prototype on both
  surfaces (neutral `Conflict`, no detail, never `Class Cancelled`), and §3.5's
  "no-fact branch exists only defensively" is the right shape for
  `anchorSlotFromPinned()`, which has no `status` by construction.
- Theme claim verified, not assumed: `.light` really is toggled on `<html>`
  (`login-UI-design-template.blade.php:536`), so "theme overrides use `html.light`"
  is right, as is `body.arrangement-mobile-mode` for the mobile neutralisation.
- Feasibility claims hold: `cohortFullNames` stays schema-free and query-free;
  `#venueCapacity`'s removal stays safe (`__fillVenueChips` null-guards);
  `.slot-dd-item-label` is untouched, keeping `arrangement-conflict-autoselect:249`
  and `rearrangement-toolbar-tooltips:103` alive.

### 💡 Optional Suggestions

- Two of §8's named "updates" need no edit: `venue-cross-week-handoff:50`
  (`1:00 PM @ B110`) survives as a substring of line 2's
  `11:00 AM ~ 1:00 PM @ B110`, and `arr-slot-original-venue-sync:121-123` splits
  the label on the first space, so `B111` still falls out of `B111 35 seats`.
  Saying so keeps the apply diff to `:23`, `:27`, `:30`, `:69`, `:48`, the
  `.venue-dd-label` regex family and `#venueCapacity`.
- Name the line-1 weekday precedence explicitly (`slot.weekdayName ||
  slotDayAbbrs[slot.day]`) rather than "for a real pick … for the anchor". One
  formatter serves both, and the anchor's `weekdayName` is the same `abbr` array,
  so no caller-identity branch is needed.

### ⚖️ Review Verdict

**Ready for `specs/` + `tasks/` — zero blockers.** Round 2's 🔴 is closed for the
right reason (a single writer taking the full slot object, not a patched call
site), all six optional items came back verified, and nothing new rose to
decision level. The six 🟡s are one-clause each and all live in sections that
already exist; fold 🟡 1–3 into §5 and 🟡 4 into §3.5/§5 **before `tasks.md` is
written**, because tasks would otherwise bake the placeholder write and the split
reason derivation into the checklist. 🟡 5 and 6 are hardening. No re-explore, no
soft-freeze, no proposal unfreeze required.

## Spec Round 1 — 2026-11-11 (batch 3)

Batch: `specs/top-zone/spec.md` (new). **`proposal.md` and `design.md` are frozen**;
`tasks.md` does not exist yet by design. Reviewed against `explore-brief.md`, the
frozen proposal (11 success criteria), the frozen design (§1–§11), the user's
confirmation doc, the prototype
(`../../../../ui-prototype/replacement-arrangement-top-zone-preview.html`), the
live code (`renderTitleSummary()`, `courseCtx()`, `statusTip`, the
`renderSlotPicker()` row builder, `slotTriggerLabel()`, `_updateTrigger()` /
`initDataTipTooltips()` / `snapshotText()`, `LectureSessionCatalog::sessionEntry()`)
and the top-zone spec suite.

Consistency pass first: R1–R10 map cleanly onto the frozen pair, and
R3/R4/R5/R6/R7/R9 all carry the decisions the design rounds closed (`(T)` token
in line 1, day-first trigger vs month-first row, plain-clash `Conflict`, single
`reasonFacts`, `data-tip` on cohorts, seats-in-trigger, dead mobile block,
`.conflict-strip-wrap`). One frozen success criterion and one frozen §6 rule did
not survive into the spec; three precision items are declarative.

### 🔴 Critical Issues (implementation blockers)

1. **The summary's first token has no value for a plain clash — and the visual
   contract fills it with the forbidden label.**
   `specs/top-zone/spec.md:R4` gives four summary strings but leaves `reason` as
   an unresolved placeholder, and `R5` defines the reason **badge** only. The
   frozen design settles it (`design.md:157-159`: "The summary reason token is
   also `Conflict`"), and `R5` already forbids `Class Cancelled` for a clash —
   but the prototype, which the proposal names as the visual contract
   (`proposal.md:6`), does exactly the forbidden thing: `slotReason()` returns
   `{text: 'Class Cancelled', cls: 'badge-reason'}` for **every** non-holiday
   slot (prototype:881-895), and the summary is built from `reason.text`
   (prototype:934-939). An implementer porting the prototype ships
   `Class Cancelled · DSF1 (S1) G1 · 24 students` for every ordinary conflict —
   the string the frozen proposal says must never appear (`proposal.md:184-188`).
   R4 cannot distinguish "reason = the badge text" from "reason = the
   prototype's text", and for the plain-clash branch the two disagree. → Add the
   third token to R4 with one example per form
   (`Conflict · DSF1 (S1) G1 · 24 students` / `Conflict · 1 cohort · 24`).
2. **R9 is titled "DOM and mobile contract" but carries none of the frozen mobile
   rules.** `proposal.md:111-114` requires, under `body.arrangement-mobile-mode`,
   that `#subjectInfo` drop its desktop card chrome (border/radius/shadow/padding),
   hide the desktop heading, and keep the Details row inside the mobile "Original
   class" `<details>`; `design.md:301-306` repeats all three. R9
   (`spec.md:119-124`) only says to keep the two hooks, replace the dead
   `.conflict-strip`/`.cs-*` block and drop `.conflict-strip-wrap`. The relocation
   plan moves `#subjectInfo` into the mobile Original-class `<details>`, so without
   those rules the shipped mobile view shows a red-bordered `CONFLICT SCHEDULE`
   card heading inside "Original class" — a regression in the lane that ships
   first, and `tasks.md` generated from R9 will not contain the fix. → Add the
   three mobile rules to R9 (chrome drop, heading hidden, Details row placement)
   as bullets, not as a design reference.

### 🟡 Should Fix (non-blocking but recommended)

- **Frozen success criterion 9 has no requirement in the spec.**
  `proposal.md:167` requires no horizontal overflow at 1280 / 1024 / 900; R1–R10
  never mention viewport fit. The design's `.slot-dd-panel min-width: 512px →
  width: 100%; min-width: 0` and the `.slot-dd` `max-width: 384px` override exist
  *only* to satisfy it (design:238-240) — with no acceptance requirement, verify
  has no gate at 900px where the 512px panel overflows the 55% Slot column, and
  the next CSS edit to the panel re-breaks it silently. → One bullet under R3 or
  R7 with the three widths.
- **Cohort badge text and tooltip are ambiguous in two directions.** R5
  (`:74-78`) says badge text = "cohort code" and `data-tip` = "full programme
  name". The page carries both `cohorts` (`DSF1 (S1) G1`, the spaced label the
  summary and the old strip print) and `cohortCodes` (`DSF1(S1)G1`, unspaced) in
  `sessionEntry()` (`LectureSessionCatalog:345-350`), so "code" picks the wrong
  visible string half the time; and the frozen design's tooltip is **not** the
  programme name — it is the composite `cohortFullNames` =
  `programme_name · Year N Sem N Group N` (`design.md:167-170`). R5 also has no
  fallback, while R10 does. → Say "badge text = the existing cohort label
  (`cohorts[i]`)" and "`data-tip` = `cohortFullNames[i]`, falling back to the
  label" in R5.
- **The panel-row badge hover collides with the row's existing tooltip, and R7
  doesn't pick a winner.** `renderSlotPicker()` already shows `#slotTooltip` on
  row `mouseenter` with the full label (`blade:5135-5139`), and the shared
  data-tip listener resolves `node.closest('[data-tip]')`
  (`ui-common.js:3715-3728`) — so hovering the badge fires **both** tooltips at
  once. R7 says "holiday name or cancellation reason on hover" and R9 keeps
  `#slotTooltip`, which is the double-tooltip state, not a decision. → One clause:
  suppress the row tip while over the badge, or accept both and say so — either
  way it must be stated, because verify cannot tell a bug from the contract.
- **The shared-venue preserved behaviour is missing, and the room type moves to
  tooltip-only.** The design touches `ui-common.js` (`_updateTrigger()` HTML
  opt-in, `this.triggerLabel`), and `design.md:213-214` requires every page
  without the option to stay byte-identical — a proposal non-goal
  (`proposal.md:147`). R10's preserved list has no such requirement, so a
  regression on replacement-home / venue-timetable / my-timetable has nothing to
  fail against. Related: the live label is `B111 — Tutorial Room`
  (`ui-common.js:4292`); the new trigger is `B111` + `35 seats`, so the room type
  is tooltip-only, and the mobile blue span (which paints `.venue-dd-label` text)
  will now read `B111 35 seats`. → Add a preserved-behaviour bullet ("pages
  without the opt-in render the identical label and `Replacement VENUE Selector:
  … (Capacity: N seats)` tip") and state the room-type-moves-to-tooltip
  consequence.
- **The precedence requirement cannot be observed, and no scenario covers the
  paths that historically broke.** R5's "cancelled-before-holiday" is only
  distinguishable from the live panel's holiday-first order (`blade:5084-5096`)
  when one occurrence is both cancelled and on a holiday; nothing in the spec says
  such a fixture exists or that `reasonFacts` gets a direct check, so the
  requirement passes vacuously and the two derivations can diverge again later.
  Separately, R3's only Given/When/Then is a subject pick: neither the `?session=`
  anchor arrival nor a panel pick has a scenario, although those are exactly the
  writers that dropped `type`/`day`/`venue` before (design:256-265). → Name the
  observability route (fixture or a helper-level test) and add two short
  scenarios: arrival, and panel pick (two-line trigger + Reason + venue sync).
- **Proposal §4's remaining shared rules are absent from R2.** `proposal.md:69-77`
  also pins "no fact icons inside the cards", "Details is the only collapse/expand
  control", and "badge/cohort details are hover-only with no extra keyboard tab
  stops". R2 covers tokens and typography only; the keyboard rule is the testable
  half (a badge that becomes focusable adds tab stops to a toolbar the user
  specifically wanted quieter). → One bullet each, or one combined bullet.

### ✅ What's Done Well

- R4's four summary strings are literal, complete and **correct against the
  prototype** — I checked the composition (`prototype:934-939`: `reason · label |
  N cohorts · total students` vs `reason · 1 cohort | N cohorts · total`),
  including the deliberate absence of "students" in the short forms and the
  single-cohort `1 cohort` collapse. With 🔴 1 closed this becomes the most
  precise acceptance text in the change.
- R5 carries the reason contract exactly as the design rounds settled it: one
  shared `reasonFacts`, cancelled-before-holiday, plain clash = absence of facts
  → neutral `Conflict`, never `Class Cancelled`, and — the item that took three
  design rounds — "virtual pinned anchor uses the same facts; it has no `status`".
  That last clause is the whole handoff-path fix, and it survived into the spec.
- R7's trigger-vs-row split is verified, not assumed: the live row really is
  month-first `.slot-dd-item-label` + `.slot-dd-item-badge` (`blade:5128-5131`),
  so "keep the row, change only the badge text/detail" keeps
  `rearrangement-toolbar-tooltips:103` and `arrangement-conflict-autoselect:249`
  alive, and renaming `Cancelled` → `Class Cancelled` breaks nothing (no spec
  pins `.slot-dd-item-badge` — checked).
- R6 is accurate against the live shared component: the trigger tooltip really is
  `Replacement VENUE Selector: <code> — <name> (Capacity: N seats)`
  (`ui-common.js:4306-4308`), so "keeps the existing room name + capacity shape"
  is the correct instruction and the removed `#venueCapacity` facts have a stated
  home.
- R8 is exactly the empty-state triad the user ruled (placeholder, Slot hidden,
  Details hidden, no hint lines, Replacement card defaults) and keeps
  `Select a slot to replace` for the two specs that read it.
- R9/R10 protect the silent-loss list: `#arrVenueDescription`, the
  `refreshArrangementMobile('original')` tail, the grid lock, the relocation plan,
  and the `.conflict-strip-wrap` double-gap.

### 💡 Optional Suggestions

- Name the three Details column labels (`Reason`, `Cohorts`, `Students`) — the
  body is otherwise only testable as "three columns at 1 : 1.5 : 0.8".
- `design.md:138-139` flips `aria-expanded` + the chevron on toggle; R4 requires
  neither, and it is the only accessibility contract the Details row has.
- Cohort-less / unknown-count edge: the prototype renders `—` for empty cohorts
  (prototype:956-968) while the page's `studentCount` is an `int` sum, so a
  cohort-less session would print `0`. One clause settles it (`—` vs `0`).
- "No repeated cohort codes" under Students (R5:84) reads as an implementation
  note, not a requirement — fold it into the badge bullet or drop it.
- R8's "Subject placeholder" could name the literal `Select a subject`
  (`buildSubjectDropdown()`, `blade:4106`); it costs one word and makes the empty
  state fully assertable.

### ⚖️ Review Verdict

**Not ready for `tasks.md` — two additive bullets required, no unfreeze.** The
spec is faithful to the frozen pair on every decision that cost the design rounds
time (one reason helper with cancelled-first, plain clash as `Conflict`, two-line
trigger owned by one writer, month-first row, seats-in-trigger, mobile hooks
untouched), and it is quantitative where it matters. It is not ready because two
frozen items did not make it in: the plain-clash **summary** token (whose only
written-down value in the repo today is the prototype's forbidden `Class
Cancelled`) and the mobile chrome/heading neutralisation that keeps the relocated
card from appearing as a red card inside "Original class". Both are one bullet
each in R4 and R9 — no re-explore, no proposal/design unfreeze, since both facts
already exist in the frozen artifacts. Close 🔴 1–2 and the 🟡 on criterion 9, and
this batch can hand off to `tasks.md`; the remaining 🟡s are precision that can
ride the same edit.

## Spec Round 2 — 2026-11-11 (batch 3)

Batch: `specs/top-zone/spec.md` (revised). **`proposal.md` and `design.md` are
frozen**; `tasks.md` still does not exist. Re-reviewed against
`explore-brief.md`, the frozen proposal (11 success criteria), the frozen design
(§1–§11), the user's confirmation doc, the prototype, and the live code
(`blade:288-301, 1639-1669, 4183-4224, 5302-5310, 5624`; `ui-common.js:3701-3735,
4292-4308`; the top-zone spec suite).

### Round-1 🔴 closure status

1. **Plain-clash summary token — CLOSED.** R4 (`:67-73`) now states the law
   ("`reason` = the same token as the Reason badge") and gives one literal
   example per form: `Conflict · DSF1 (S1) G1 · 24 students` / `Conflict · 1
   cohort · 24`. Verified this is exactly the frozen design's ruling
   (`design.md:157-159`) and that it closes the only place in the repo where a
   value existed at all — the prototype's forbidden
   `{text:'Class Cancelled'}` for every non-holiday slot, which the summary
   composes from (`prototype:881-895,934-939`). The four holiday/cancelled
   strings still match the prototype's composition, so R4 is now the most
   precise acceptance text in the change.
2. **R9 mobile chrome / heading / Details rules — CLOSED.** R9 (`:149-152`) now
   carries all three frozen rules as bullets: `#subjectInfo .ra-block` drops
   border/radius/shadow/padding, `#subjectInfo .ra-block-head` is hidden, and the
   Details row stays inside the mobile Original-class `<details>`; `:146-148`
   keeps the dead-block replacement and the `.conflict-strip-wrap` 12px-gap rule.
   Verified against `proposal.md:111-114` and `design.md:301-306`: all three
   items, in behavioural language, no design reference. Tasks generated from R9
   will now contain the fix, so the relocated card cannot ship as a red-bordered
   heading inside "Original class".

### Round-1 🟡 / 💡 closure status

All nine landed, and each is checkable rather than asserted:

- viewport gate → R3 (`:42-43`) with the three widths **and** the panel's
  `width:100%; min-width:0`, which is the design's only reason for the override
  (`design.md:238-240`);
- cohort label/tooltip → R5 (`:96-99`): "existing cohort label (`cohorts[i]`)"
  plus `cohortFullNames[i]` with label fallback — the right fix, since
  `sessionEntry()` carries both `cohorts` (spaced) and `cohortCodes` (unspaced),
  so "code" was the ambiguous word;
- panel-row tooltip collision → R7 (`:124-125`) picks suppression; feasible and
  verified: the badge is a child of the row (`blade:5302`), `#slotTooltip` is
  row-`mouseenter`-driven (`:5306`), and the shared resolver walks
  `closest('[data-tip]')` (`ui-common.js:3717`);
- shared-venue preserved behaviour + room-type-moves-to-tooltip + mobile
  `B111 35 seats` → R10 (`:161-164`);
- precedence observability → R5 (`:106-107`) with the both-facts fixture, plus
  two new R3 scenarios (arrival `:52-55`, panel pick `:57-61`) — the two writers
  that historically dropped `type`/`day`/`venue`;
- no fact icons / Details-only / hover-only+no extra tab stops → R2 (`:28-30`);
- exact Details labels → R4 (`:79`); aria/chevron → R4 (`:81`); empty cohorts
  `—` → R4 (`:82`); literal `Select a subject` → R8 (`:131`, matches
  `blade:1639,4278`).

### 🔴 Critical Issues (implementation blockers)

None. Both Round-1 blockers are genuinely closed (one verified against the
prototype's forbidden value, one against the frozen design's three mobile
rules), every Round-1 🟡 came back as acceptance text rather than as a design
pointer, and no new decision-level defect was introduced. The three 🟡s below
are one-clause hardening in sections that already exist.

### 🟡 Should Fix (non-blocking but recommended)

- **The legacy `.toolbar-filters` layout is kept as a hook but its neutralisation
  is not a requirement anywhere in the spec.** R9 (`:144-145`) keeps
  `.toolbar-filters .week-nav` (correct — it is the `RELOCATION_PLAN` hook), but
  neither R9 nor R6 says the class must stop acting as a layout, while
  `proposal.md:109-110` and `design.md:64-65` both require it. The live rule is
  real and fires at exactly the widths R3 now gates: `blade:294-301` sets
  `.toolbar-filters { flex: 1 0 100%; justify-content: flex-end }` for
  769–1200px, inside the Replacement card's week column. R3's "no horizontal
  overflow at 1280/1024/900" will not catch it — it wraps and right-aligns, it
  does not overflow. → One clause in R6 or R9: the class is kept for the
  selector, and its flex rules are neutralised inside the Replacement card.
- **R7's suppression rule leaves the plain-clash badge with no tooltip and no
  row tooltip.** `:124-125` suppresses `#slotTooltip` "while the pointer is over
  the badge", unconditionally, and `:123`/R5 (`:90-91`) give a plain clash **no**
  `data-tip`. Hovering exactly that badge therefore shows nothing, while
  hovering the row two pixels away shows the full label — a tooltip dead zone
  created by two requirements that are each correct alone. Cheap fix: suppress
  only when the badge carries a `data-tip`, or state the dead zone is intended.
  (Also worth mirroring into `design.md` §5 on the next soft-freeze: design froze
  with both tooltips live and silent on the collision, so the spec is currently
  the only artifact that states the winner.)
- **R5's precedence fixture has no mechanism, and the obvious one is forbidden.**
  `:106-107` says the cancelled-before-holiday order "is checked by a fixture
  where one occurrence is both cancelled and on a holiday". The facts come from
  `MockData.cohortTimetable` / `MockData.holidays`, which are assigned from
  server-rendered `window.PAGE_DATA` (`blade:1860,1866`), so the overlap is
  data-dependent; there is no JS test harness in the repo, and the demo DB is
  additive-only (no seeding). Read literally, an implementer either skips the
  check or seeds a demo DB — the second is a standing-rule violation. → Name the
  route: a synthetic slot object carrying both `holidayName` and
  `cancelledReason` passed to the page-global `reasonFacts`, or a
  `page.evaluate` `MockData` override. One clause.

### ✅ What's Done Well

- The two blockers were closed **where the defect lives**, not where it was
  reported: the summary fix states a general law ("same token as the Reason
  badge") *and* the two literal strings, so no future reason token can diverge
  from the badge; the mobile fix is three behavioural bullets, so it survives
  into `tasks.md` instead of being a design cross-reference.
- R3's new scenarios are the right two. The arrival and panel-pick paths are
  precisely the writers that dropped `type`/`day`/`venue` through three design
  rounds (`design.md:256-265`), so the handoff path is now a Given/When/Then
  instead of an implementation note, and it asserts venue plus Reason, not just
  the trigger shape.
- The optional items landed as precision, not padding: `cohorts[i]` vs
  `cohortCodes` is disambiguated, the tooltip is the composite
  `cohortFullNames[i]` rather than "programme name", and R10's
  "pages without the opt-in render the identical label and
  `Replacement VENUE Selector: … (Capacity: N seats)` tip" gives the shared-
  component regression an actual gate — which is the only thing that can fail if
  `_updateTrigger()`'s opt-in leaks.
- R7's hover rule picked a winner (suppression) rather than describing the
  conflict, which is what verify needs.
- R4 remains internally consistent after the addition: full/short, single/
  multiple and holiday/cancelled/conflict are all enumerated, with the
  deliberate absence of "students" in short forms preserved.

### 💡 Optional Suggestions

- R9 (`:138`) says "keep all IDs, including …", while R6 (`:114`) removes
  `#venueCapacity` — two directives in one document that read as contradictory.
  "keep all IDs except the intentionally removed `#venueCapacity`" costs three
  words. (`__fillVenueChips()` null-guards it, `blade:5830`, so the removal is
  safe either way.)
- R4 (`:82`) `Empty cohorts show —, not 0` does not say **which** field shows
  the dash, and the Students field has no zero case either (`24` /
  `7 + 18 = 25`). One clause covering both ("Cohorts and Students show `—` when
  empty") makes the prototype's `—` fully assertable.
- `proposal.md:74`'s "shared 11px tooltip" is the only §4 rule with no
  counterpart in R2; it is in `design.md:94`, so this is bookkeeping, not risk.

### ⚖️ Review Verdict

**Ready for `tasks.md` — zero blockers.** Both Round-1 🔴s are closed for the
right reasons (one verified against the prototype's forbidden value, one against
the frozen design's three mobile rules), all nine optional items came back as
acceptance text, and the spec is now quantitative exactly where the frozen
artifacts were, including the plain-clash summary, the three viewport widths and
the two historically-broken writer paths. The three 🟡s are one clause each and
none needs re-exploring, unfreezing, or new code; fold the `.toolbar-filters`
clause into R9 and the badge-suppression condition into R7 before `tasks.md` is
written, since the task checklist would otherwise bake both in.

## Tasks Round 1 — 2026-11-11 (batch 4)

Batch: `tasks.md` (new). **`proposal.md`, `design.md` and `specs/top-zone/spec.md`
are frozen.** Reviewed against `explore-brief.md`, the frozen triple, the
prototype, and the live code (`#subjectInfo` mount `blade:1634`,
`.toolbar-restructure` / `#subjectSelector` / `#slotPicker` `:1636-1648`,
`#venueCapacity` + writer `:1659,:5830`, dead mobile `.cs-*` block `:1401-1416`,
`renderTitleSummary()`'s `el.innerHTML` `:4243`, the five `#slotTriggerText`
writers `:4078,:4986,:5292,:5446,:5652`,
`tests/Feature/LectureSessionCatalogTest.php:28,37`, `phpunit.xml:29`, and the six
`.conflict-strip` readiness gates). Every frozen requirement was mapped
task-by-task: R1→T2, R2→T4 (partial), R3→T5/T10, R4→T3/T6/T9, R5→T9/T11,
R6→T2/T5/T7/T18, R7→T11, R8→T2/T8/T18, R9→T2/T8/T15, R10→T8/T12/T13; changelog
and static checks → T17; the ten spec files → T16. Apply gate is T1 and it is
first.

### 🔴 Critical Issues (implementation blockers)

1. **T2 and T8 are one atomic change, and the list splits them by six tasks.**
   T2 (`tasks.md:18-27`) moves `#subjectSelector` and `#slotPicker` inside
   `#subjectInfo` — exactly the frozen design's §1 tree — but
   `renderTitleSummary()` still writes `el.innerHTML = '<div class="conflict-strip">…'`
   on that same mount (`blade:4243`) and runs at boot and on every subject/slot
   change. Between T2 and T8 (`:69-74`) sit T3, T4, T5, T6, T7 (~6.5h) in which
   the renderer wipes the moved selectors out of the DOM: `buildSubjectDropdown()`'s
   `sel.innerHTML` write and every listener target detached nodes, and T5's
   two-line trigger cannot be inspected at all because no slot can be selected.
   This is the exact "rebuild with `innerHTML` wipes the selectors" defect four
   review rounds guarded against, reintroduced as *sequencing* rather than as an
   implementation choice. → Merge T2 + T3's shell + T8 into one "card mount +
   renderer" task, or state explicitly that T2 and T8 land together and move T8
   directly after T2.
2. **T9 omits the two frozen Details rules the spec rounds existed to close.**
   (a) *Expand-on-first-slot*: T3 (`:29-33`) sets `#details` `hidden` with
   `aria-expanded="false"`, and no T9 bullet (`:76-85`) ever reveals it or flips
   `aria-expanded`/the chevron — `design.md:108-109` requires "the renderer
   unhides and opens it on the first slot". A literal read of the checklist ships
   a permanently collapsed Details row, failing R4's "expanded by default"
   (`spec.md:66`) on the change's headline element. (b) *The summary's `reason`
   token*: T9 says "set full, measure, swap" but never states the law (R4
   `spec.md:68-74`: the summary reason = the Reason badge text, incl. plain-clash
   `Conflict` with the two literal examples). The only concrete value in the repo
   is the visual contract's forbidden one — `slotReason()` returns
   `{text:'Class Cancelled'}` for every non-holiday slot and the summary is
   composed from `reason.text` (`prototype:881-895,934-939`) — so a prototype
   port from T9's wording ships
   `Class Cancelled · DSF1 (S1) G1 · 24 students` for every ordinary conflict:
   the string Spec Round 1 graded 🔴. → Two bullets in T9: reveal +
   `aria-expanded="true"`/chevron on first slot; summary `reason` = the Reason
   badge text with the `Conflict` examples.

### 🟡 Should Fix (non-blocking but recommended)

- **The readiness-gate replacement is an either/or and one branch hangs.** T16
  (`:150`) repeats "card root/Details summary". Six sites gate on
  `page.waitForSelector('.conflict-strip')` (`arrival-grid-no-pin:20,59`,
  `session-arrival-pin-guard:20,54,94`, `arrangement-earliest-week-default:72`,
  `default-pin:29`), which waits for **visible**; `#detailsSummary` is
  hidden/absent until a slot exists, so that branch times out in any test that
  waits before choosing a subject, and six files will invent six gates. → Name
  `#subjectInfo` (always mounted and visible per R1) as the single gate; say
  `#detailsSummary` is never a boot gate.
- **T16's 1.5h covers ten files and three edit families; one file alone has 11
  assertions.** `arr-slot-original-venue-sync` pins `.venue-dd-label`
  `/^B0\d\d? — /` at `:29,36,41,48,56,62,67,75,78,87,98` plus `:121`, and the
  trigger family touches `:30,69`; `venue-cross-week-handoff` needs both the
  day-first read and the `range @ venue` shape; `rearrangement-toolbar-tooltips`
  needs three separate assertions. At ~9 min/file this is the task that most
  likely ends half-red. → Split: (a) gates/`#subjectInfo` text, (b)
  `.venue-dd-label` family + `#venueCapacity`, (c) trigger
  start-shape/day-first/`range @ venue`.
- **R10's shared-component non-regression has no gate.** T12 (`:109-115`) states
  the byte-identical rule, but T18 (`:161-167`) never checks another venue page —
  `ui-common.js` also serves replacement-home / venue-timetable / my-timetable,
  and the opt-in's failure mode (label assignment not switched to `innerHTML`
  only in the opt-in branch) is silent on every page. R10 also froze the mobile
  consequence (`.venue-dd-label` text becomes `B111 35 seats` in the mobile blue
  span, `arrangement-mobile.js:1241-1250`) in a lane that already ships. → Two
  T18 lines.
- **R5's precedence check has no task, and its forbidden shortcut is the easy
  one.** `spec.md:107-110` fixes the mechanism (synthetic slot carrying
  `holidayName` *and* `cancelledReason` into the page-global `reasonFacts`, or a
  `page.evaluate` `MockData` override) and forbids seeding. T9 implements the
  order, T18 verifies badges, neither checks the overlap — so the requirement is
  vacuous and a future edit can flip it silently. → One T18 line.
- **`#venueCapacity` is named only as a keep-list carve-out, never as a removal.**
  T2 (`:22-23`) says "keep all IDs except the intentionally removed
  `#venueCapacity`"; the thing is a static span inside `#venueChipGroup`
  (`blade:1659`) with a live, null-guarded writer (`blade:5830`) and
  `.badge-capacity` styling. R6 (`spec.md:117`) requires the removal. → One
  explicit bullet: delete the span, delete the `capA` block, leave
  `#arrVenueDescription` alone.
- **R2's hover-only / no-extra-tab-stops rule has neither a task nor a verify
  line.** `spec.md:30` (from `design.md:74`, `proposal.md:76-77`) is the only
  interaction contract a well-meaning implementation breaks: a focusable badge
  adds tab stops to a toolbar the user asked to quiet down (`data-tip` is
  pointer-only). T3 covers "no fact icons", T6 covers the toggle, nothing covers
  this. → One bullet in T6 + one T18 check.
- **T13's test run carries no DB guard.** `tests/Feature/LectureSessionCatalogTest.php`
  uses `DatabaseMigrations` + `$this->seed()` (`:28,37`) — i.e. `migrate:fresh` +
  reseed of whatever DB answers. `phpunit.xml:29` pins
  `DB_DATABASE=class_replacement_testing`, so a plain `php artisan test` is safe,
  but `AGENTS.md` forbids `--env=testing` precisely because it silently boots the
  demo DB and T13 never names the command. → One clause: run
  `php artisan test --filter=LectureSessionCatalogTest`; never `--env=testing`.

### ✅ What's Done Well

- **The single-writer contract survived into the checklist intact.** T10
  (`:89-98`) names `slotTriggerLabel() → {line1, line2}`, makes
  `writeSlotTrigger()` the only writer with the two child spans, enumerates **all
  five** sites (anchor, `applySubjectChange()`, `renderSlotPicker()` placeholder,
  `commitSlotSelection()`, `selectSlot()`'s else branch) and keeps
  `writeSlotTriggerFromAnchor()`'s `false`/`true` contract. Re-checked against the
  code (`:4078`, `:4986`, `:5292`, `:5446`, `:5652`) — exactly five, none missed.
  That is the failure family that cost Design Rounds 2–3, closed at the right
  level.
- Plain-clash `Conflict` is carried on **both** surfaces (T9 reason badge, T11
  picker badge incl. suppressing `#slotTooltip` only when a `data-tip` exists),
  and never as `Class Cancelled`.
- Cohort full-name fallback is doubled up correctly: the payload task (T13, with
  "no schema change; no new query" and the catalog test) and the consumer (T9,
  `cohortFullNames[i]` tip with label fallback).
- The silent-loss list is protected: `#arrVenueDescription`, `#slotTriggerText`,
  `#slotTooltip`, every kept ID, `.toolbar-restructure`, `.toolbar-filters
  .week-nav`, the `.no-subject` toggle and the
  `refreshArrangementMobile('original')` tail (T2/T8/T15). The venue opt-in
  carries the literal-space rule (T7) that the mobile snapshot concatenation
  depends on.
- Scope discipline holds: no task touches the prototype, the grid, the booking
  rules or the mobile lane's plan; no migration, no seed, no new dependency; the
  two non-Blade edits (`ui-common.js`, `LectureSessionCatalog`) are exactly the
  two the frozen proposal approved. T1 is the apply gate, is first, and re-lists
  the hooks it must re-verify.
- Every task is ≤2h (0.25–2h, ~21h total) and symbol-anchored rather than
  line-anchored — correct, given the blade drifted ~67 lines during the design
  rounds.

### 💡 Optional Suggestions

- `writeSlotTrigger(null)`'s single-text-node case (`design.md:253-255`) is
  unstated in T10; low risk (`toHaveText` normalises an empty second span) but one
  clause makes `rearrangement-toolbar-tooltips:135` provably safe.
- T17's "no PHP touched unless payload changed" contradicts itself — T13 *is* the
  payload change. Say "PHP touched only by T13".
- `arrival-grid-no-pin:28-31` (`(P)`, `Week 14`, `B011` via `#subjectInfo`
  innerText) needs no edit — all three survive the two-line trigger. Saying so
  keeps the apply diff to the reads design §8 already named.
- The desktop `.conflict-strip`/`.cs-*` block (`blade:200-236`) and
  `.badge-capacity` become dead after T2/T15; no task removes them.
- 21h for one page's top zone: the cheapest cuts if it slips are one combined
  T4+T6 CSS pass and T16's split above.

### ⚖️ Review Verdict

**Not ready for `/sdd-apply` — one reorder and one T9 bullet set, both inside
`tasks.md`.** Coverage of the frozen R1–R10 is otherwise complete and traceable,
the five-writer roster and the plain-clash ruling are correct in code, and no task
touches the prototype, the demo DB, or anything outside the proposal's scope; the
apply gate is first. The blockers are declarative, not decision-level: T2 detaches
the selectors while `renderTitleSummary()` still overwrites the mount
(`blade:4243`), and two frozen R4 rules (expand-on-first-slot, summary `reason`
token) are absent from the task that builds Details — the same shape of omission
that made Spec Round 1 🔴. No re-explore, no unfreeze, no code. Close 🔴 1–2 plus
the readiness-gate branch in 🟡 1, and this batch is ready for apply once T1's
mobile-lane gate is verified.

## Tasks Round 2 — 2026-11-11 (batch 4)

Batch: `tasks.md` (revised). **`proposal.md`, `design.md` and
`specs/top-zone/spec.md` are frozen.** Re-reviewed against `explore-brief.md`,
the frozen triple, the prototype, and the live code — anchored to symbols, since
the blade is still drifting: `#subjectInfo` mount, `.conflict-strip-wrap`,
`#slotTriggerText` markup, the five trigger writers (`writeSlotTriggerFromAnchor`,
`applySubjectChange`, `renderSlotPicker`'s placeholder reset, `selectSlot`'s
`else` branch, `commitSlotSelection`), `slotTriggerLabel()`, `applySlotTip()`,
`renderTitleSummary()`'s `el.innerHTML` template, `buildSubjectDropdown()`,
`__fillVenueChips()` / the `capA` block, and the top-zone spec suite
(`arr-slot-original-venue-sync`, `replacement-strip-anchor`,
`venue-cross-week-handoff`, `session-arrival-pin-guard`, `arrival-grid-no-pin`,
`default-pin`, `venue-restrictions-enforcement`, `rearrangement-toolbar-tooltips`).

### Round-1 🔴 closure status

1. **T2/T8 sequencing (selectors wiped) — CLOSED.** The atomic batch is now
   declared at the top of the file (`tasks.md:16-19`): "T2, T3, T8 and T9 must
   land together before any verification or commit … Do not verify or commit
   between them." That is exactly the defect class four review rounds guarded
   against, and it is stated as a sequencing law rather than as advice.
   Verified the hazard is still real: `renderTitleSummary()` still writes
   `el.innerHTML = '<div class="conflict-strip">…'` into the `#subjectInfo`
   mount, so between T2 and T8 the moved `#subjectSelector` / `#slotPicker`
   and T3's `#details` are all destroyed on every boot and every subject/slot
   change. The declaration covers it. *Two residual scoping gaps — see 🟡 3.*
2. **T9's two missing Details rules — CLOSED.** T9 now opens with "On the first
   slot, unhide `#details`, add `open`, set `aria-expanded="true"`, show the
   body and rotate the chevron" (frozen `design.md:108-109`, `spec.md:66,81`),
   and states the summary law as "`reason` is the **same token as the Reason
   badge** (`Public Holiday`, `Class Cancelled` or `Conflict`)" with the R4
   examples (`spec.md:68-74`). The prototype's forbidden
   `{text:'Class Cancelled'}`-for-every-non-holiday composition is no longer
   reachable from the checklist.

### Round-1 🟡 / 💡 closure status

All eleven landed, each as a change in the checklist rather than a promise:
`#subjectInfo` is named as the single readiness gate with "never use
`#detailsSummary` as a boot gate" (T16a); T16 split into a/b/c along the three
edit families; the shared-component non-regression is a T18 line
(`Replacement VENUE Selector: … (Capacity: N seats)` on a non-arrangement
page) plus the mobile `B111 35 seats` consequence; the precedence overlap is a
T18 line using a synthetic slot and "do not seed the demo DB"; `#venueCapacity`
is an explicit delete (span + `capA` block + `.badge-capacity`, verified
null-guarded at `__fillVenueChips` so the group still shows via
`chipEl.style.display = ''`); badges are non-focusable spans in T6 **and** T18;
the test command is `php artisan test --filter=LectureSessionCatalogTest` with
"never `--env=testing`"; T17 now reads "PHP is touched only by T13"; the
`writeSlotTrigger(null)` single-text-node rule is in T10; and the dead desktop
`.conflict-strip` / `.cs-*` / `.badge-capacity` CSS is removed in T4.

### 🔴 Critical Issues (implementation blockers)

None. Both Round-1 blockers are closed at the right level (one as a stated
atomic batch, one as two behavioural bullets), and no new decision-level defect
was introduced. The trigger roster still matches the code exactly — five
`#slotTriggerText` assignment sites, no sixth — and the readiness-gate file list
is still exhaustive (`.conflict-strip` appears only at
`arrival-grid-no-pin:20,59`, `session-arrival-pin-guard:20,54,94`,
`arrangement-earliest-week-default:72`, `default-pin:29`; `#venueCapacity` only
in `arr-slot-original-venue-sync`; `#subjectInfo`/`#slotTriggerText`/`.venue-dd-label`
reads only in the listed files, with `arrangement-conflict-autoselect:234` and
`confirm-guards:154-159` surviving as text-compare/contains).

### 🟡 Should Fix (non-blocking but recommended)

- **T16b's venue-label edit is written as one fixed replacement for eleven
  assertions across three rooms — and the example number is the prototype's, not
  the fixture's.** T16b (`tasks.md:170`) says "`.venue-dd-label` `/^B0\d\d? — /`
  → `B111` + `35 seats`". The file pins `/^B010 — /` (`:29,36,62,67,78`),
  `/^B111 — /` (`:41,48,87`), `/^B100 — /` (`:56,75,98`), and the real B111
  fixture is **80 seats** (`#venueCapacity` `/80/s` at `:42`, `/28/s` for B010 at
  `:37`), not `35`. A mechanical application of the bullet turns all eleven red,
  and because "Playwright runs only on the user's word" (`T16c`) the breakage
  surfaces late, after the user's run. → Say "per assertion: the same room code
  plus **that room's** seat count from the payload (`B010 28 seats`,
  `B111 80 seats`, …)". The `35 seats` figure is the prototype's illustrative
  number (and `spec.md:115`'s frozen example) — it must not be hardcoded in
  T12's renderer or in T18's mobile expectation (`B111 <capacity> seats`).
- **`#subjectInfo` text reads stop discriminating the subject once the native
  `<select>` lives inside the mount.** T2 puts `#subjectSelector` inside the
  mount; `buildSubjectDropdown()` fills it with `code + ' — ' + name` for every
  pooled course. `toContainText`/`textContent` on the mount therefore always
  contains **every** subject code, so `replacement-strip-anchor:15` (arrival =
  AMSE1003), `replacement-strip-anchor:21` (the post-switch `BMSE2163` — the only
  receipt of the Bug-1 subject-switch behaviour), and
  `venue-restrictions-enforcement:48,79` (`AMIT2033` / `BMCS2053`, the
  pre-conditions of the venue-type tests) all pass regardless of what is
  actually selected. T16a/T16c frame the `#subjectInfo` work as "day-first /
  two-line trigger" only, so the vacuity ships silently. → Add one line: the
  mount's text no longer proves which subject is selected — read
  `#subjectSelector`'s selected option (or scope the read to the Slot/Details
  region) in those four places. (Positives that survive on the trigger/Details
  text — `(P)`, `Week 14`, `B011`, cohort labels — need no change.
  `innerText` reads are safe for the panel, whose `.slot-dd-panel` is
  `display:none`; `textContent` reads are not.)
- **The dark window is wider than the declared atomic set.** T4–T7 sit between
  T2 and T8 and are equally unverifiable — the mount is still overwritten by
  `renderTitleSummary()`'s `innerHTML`, so T4's card CSS, T6's summary/badges
  and T7's venue label can be "verified" only against a card that is being wiped
  on every render; and T5's two-line trigger cannot be seen at all until T10
  wires the single writer. The note names only T2/T3/T8/T9, which reads as if
  T4–T7 can be committed independently. → Either reorder (T8 straight after T3,
  T10 straight after T5) or restate the window as "T2 → T10 lands in one pass;
  nothing is verified or committed before T10".

### ✅ What's Done Well

- **The atomicity fix is at the right level.** The Round-1 blocker was a
  sequencing defect, not a wording defect, and the fix is a sequencing law
  placed in the one spot every implementer reads first (under "Apply gate"),
  naming the exact tasks and the exact prohibition ("do not verify or commit
  between them"). It also correctly pulls T3 and T9 in, not just T2/T8 — the
  Details markup and its renderer live in the same wiped mount.
- **T9's first bullet is the change's headline element.** "Unhide → `open` →
  `aria-expanded="true"` → show body → rotate chevron" is the difference
  between the prototype and a permanently collapsed row, and it now sits
  immediately above the reason/cohort/student bullets instead of being implied
  by R4.
- **The summary law is stated as a law, not as strings.** "`reason` is the
  **same token as the Reason badge**" makes any future reason token propagate;
  the R4 reference then carries the two literal plain-clash forms. That is the
  general-rule-plus-example shape that closed the same defect at spec level.
- **The readiness-gate instruction is now unambiguous and fail-safe**:
  `#subjectInfo` for all seven sites, `#detailsSummary` explicitly banned, and
  the reason given (hidden until a slot exists). Verified `#subjectInfo` is
  visible from first paint in the new markup, so `waitForSelector` resolves; all
  seven sites are followed by a 700–900 ms settle, so the gate no longer doubling
  as a boot signal costs nothing today.
- **The silent-loss list is fully protected after the revision**: `#venueCapacity`
  is a delete with its writer and class named (and `__fillVenueChips()` does
  null-guard it, while `chipEl.style.display = ''` keeps the description chip
  visible), T7 keeps the literal space the mobile concatenation depends on, and
  T10 keeps `applySlotTip()` plus the `false`/`true` anchor contract that
  `selectSlot()` branches on.
- Every task stays ≤2h and symbol-anchored; T1 is still the mobile-lane gate and
  still first; no task touches the prototype, the demo DB, the mobile
  `RELOCATION_PLAN`, or anything outside the proposal's scope.

### 💡 Optional Suggestions

- Inline the two plain-clash summary strings in T9 (`Conflict · DSF1 (S1) G1 ·
  24 students` / `Conflict · 1 cohort · 24`) instead of deferring to "the
  examples in R4". The prototype's forbidden `Class Cancelled · …` composition
  is still the easiest thing to copy from the visual contract, and the checklist
  is the last artifact before someone opens that file.
- T9's "add `open`" implies a native `<details>` while T3 specifies `hidden` +
  `aria-expanded` and T6 owns the chevron rotation. Name the element (a `div`
  toggled by `#detailsToggle`, or `<details>`+`<summary>`) so the two tasks do
  not build different controls.
- T2's "leave `#arrVenueDescription` alone" is correct, and it is also what
  reconciles R2's "no fact icons inside the cards" with that badge's inline info
  icon (`__fillVenueChips` injects an SVG). One clause ("the description badge
  is a kept pre-existing control — do not strip its icon or the chip") stops an
  over-literal reading of R2.
- T18's shared-venue check is a manual spot-check; the only silent failure mode
  is the label assignment (`textContent` vs opt-in `innerHTML`), which also
  paints into the mobile blue span. Reading both the trigger label and the tip
  on one non-arrangement page in that same line is enough.
- `#subjectInfo`'s `textContent` now also contains the slot panel's rows (even
  though `innerText` does not). Worth one sentence so nobody adds a `textContent`
  negative assertion against the mount later.

### ⚖️ Review Verdict

**Ready for `/sdd-apply` — zero blockers.** Both Round-1 🔴s are genuinely
closed (the sequencing defect is now a stated atomic batch; the Details
expand-on-first-slot and the summary reason token are both in T9), all eleven
optional items came back as checklist changes, and the revision introduced no
new contradiction with the frozen proposal/design/spec — the five-writer roster,
the single readiness gate and the `#venueCapacity` deletion all still hold
against the code. The three 🟡s are one-line wording fixes inside `tasks.md`
(per-assertion seat counts in T16b, the now-vacuous `#subjectInfo` subject reads,
and the width of the atomic window); fold 🟡 1–2 in before apply so the spec
edits are not made twice, and start only once T1's mobile-lane gate is verified.
No re-explore, no soft-freeze, no unfreeze.

### Soft-freeze note — 2026-11-11

- `design.md` §5 now mirrors Spec R7's row-tooltip rule: suppress
  `#slotTooltip` only when the hovered badge has a `data-tip`. This is the
  one-clause interaction refinement requested by Spec Round 2; the frozen
  proposal is unaffected. No other decision-level change was made after the
  design freeze.

### Mobile compatibility addendum — 2026-11-12 (user-approved)

- User chose **Option 1**: keep the top-zone change desktop-first, but add a
  mobile compatibility gate. Do not redesign the phone view.
- `design.md` §7, `specs/top-zone/spec.md` R9 and `tasks.md` T1/T6/T18 now
  require:
  - Subject/Slot `width: 100%` in mobile mode; the 45/55 split is desktop-only;
  - the Details body stacks on mobile, not `1 : 1.5 : 0.8` at 320px;
  - `#detailsToggle` is at least 44px tall if it is a button/summary;
  - no overflow or clipped text at 320 / 390 / 768;
  - the trigger keeps its `data-tip` when clipped;
  - venue text reads `B111 <capacity> seats`;
  - `tests/arrangement-mobile.spec.ts` is green on
    `class_replacement_testing` after apply, and is not run concurrently with
    the mobile lane's server.
- No desktop behaviour changed. Apply is still blocked until
  `arrangement-mobile-day-view` is fully applied and verified.

### Whole-card collapse addendum — 2026-11-12 (user-approved)

- User chose a **second arrow on the `CONFLICT SCHEDULE` heading** to
  collapse/expand the whole card. The Details row keeps its own arrow.
- `design.md` §1/§3.1/§7, `specs/top-zone/spec.md` R2/R9 and `tasks.md`
  T2/T6/T8/T15/T18 now require:
  - `#conflictCollapse` on the heading right, default expanded, real button
    with `aria-expanded` and a clear label;
  - `#conflictCardBody` wraps the selectors and Details and is hidden when the
    card is collapsed;
  - the whole-card state and the Details state are independent; collapsing the
    card preserves the Details open/closed state;
  - the collapse is desktop-only: mobile hides the heading arrow and forces the
    card body visible.
- The prototype (`../../ui-prototype/replacement-arrangement-top-zone-preview.html`)
  and `prompts/replacement-arrangement-top-zone.md` now show the same
  heading arrow and body wrapper.
- This is a new interaction, not a visual token change. Apply still waits for
  `arrangement-mobile-day-view` and its phone tests.

### Field colour, mobile dates and pop-up reference — 2026-11-12 (user-approved)

- **Field colour:** Course and Slot use `--color-surface-variant`; both sit
  on the `--color-surface` card. The venue trigger and week select use the same
  field background; the slot/venue panels stay `--color-surface`.
  `design.md` §2, `specs/top-zone/spec.md` R3/R6 and `tasks.md` T5/T7 now carry
  it. The venue code and seat count also keep an 8.8px gap, and the dropdown
  arrow sits at the far right. The slot venue pill is outlined and flips to
  `--color-surface` inside the variant trigger, so it no longer blends in; it
  keeps `--color-surface-variant` in the collapsed summary.
- **Mobile Course height:** the Course select is 52.8px high on mobile
  (+20% from 44px); the Slot stays 44px. `design.md` §7, `spec.md` R9 and
  `tasks.md` T15 carry it.
- **Mobile dates:** trim the year to `Tue, 17 Nov`, including the Slot trigger
  line 1 `(T) Week 8 · Wed, 11 Nov` and the week select options
  (`Week 9 · 16 Nov ~ 22 Nov`). Desktop keeps the year. `design.md` §5/§7,
  `spec.md` R9 and `tasks.md` T10/T15 now carry it.
- **Pop-up reference:** the prototype now has a prototype-only “Mobile pop-up
  examples” section with four working bottom sheets:
  - Saved selection — screenshot layout; red `Remove selection`.
  - Confirmation — `Change Course?`; outline cancel + warning-amber
    `Yes, Change`; warning on the button only.
  - Conflict details — Reason/Cohorts/Students; top `Close` only.
  - Venue description — `B009 — Computer Lab`, `28`, and the real
    `Computer Lab. For PRACTICAL.` description. The `venueDescription` chip
    opens this sheet.
- Pop-ups are prototype-only; the live phone page still owns its layout.
- No live code changed. Apply still waits for `arrangement-mobile-day-view`.

### Course wording, collapsed summary and mobile detail row — 2026-11-12 (user-approved)

- **Course wording:** every visible “Subject” becomes “Course” (field label,
  saved-selection row, `Change Course?`, mobile wording). Internal IDs and
  function names (`subjectSelector`, `onSubjectChange`, `applySubjectChange`)
  stay unchanged.
- **Collapsed summary:** when the Conflict Schedule is collapsed, it shows
  `CODE (T) · W8 · Fri, 19 Nov` and `2:00 PM ~ 3:00 PM @ venue pill`. `(T)` is
  blue; the venue uses the desktop pill. The summary is hidden when expanded
  and on mobile. The arrow is disabled until a Course exists; a Course with no
  Slot shows the code only.
- **Mobile detail row:** on mobile only, Reason / Cohorts / Students share one
  row and wrap as whole items to the next line; labels and badges stay
  left-aligned inside each item; desktop keeps `1 : 1.5 : 0.8`.
- **Mobile badge system:** all Details badges are 34px high, 48px min-width,
  14px side padding, pill radius, 13px / 600, centred; the student count is a
  short pill, not a circle. Colours are unchanged.
- `design.md`, `spec.md`, `tasks.md`, the implementation prompt and the
  prototype now carry these rules. No live code changed.
- **Mobile live follow-up:** the user wants the four bottom sheets and the
  `venueDescription` tap on the real phone page too, but only after
  `arrangement-mobile-day-view` is finished. Recorded at the bottom of
  `tasks.md`; it is not part of this apply.
- **Mobile venue picker:** the prototype now opens a `Choose venue` bottom
  sheet with search and a Block/Floor grouped room list; one tap selects. The
  desktop nested panel is unchanged. The live version is a mobile-lane follow-up.
  The prototype now carries the real Block B room set (B002–B018, B100–B111,
  including the labs and Cisco room). Each Block/Floor group header
  collapses/expands. The mobile `Colours & borders` tool opens the current
  system's four-state legend sheet (Available / Selection / Pending / Conflict).
- **No full phone mock:** the prototype keeps the top-zone cards plus the
  pop-up examples; the user chose not to add a full phone page mock.
- **Prototype mobile chrome:** at ≤768px the prototype now shows the blue
  Back/crest/title top bar with the light/dark toggle at the far right, and the
  `arrangement-mobile-tools` row (`Earliest bookable` as a 44px icon-only
  button, `Colours & borders`, `Tip`); the desktop header is hidden. The
  Conflict Schedule keeps its red heading and a 44px collapse arrow at the
  top-right, and its collapsed summary shows on mobile. This is a prototype-only visual reference.

### Verify — 2026-11-12

- **Date law fix:** the live Slot trigger now keeps `weekData`'s day-first
  date (`24 Dec 2026`) instead of reversing it to month-first. Both
  `topZoneSlotDate()` and `topZoneSlotDateNoYear()` preserve the source order
  and only trim the year on mobile. The collapsed summary follows the same
  law.
- **Default-pin assertion:** T18-S1 now checks that the pin carries a valid
  week and that the trigger names that week; it no longer assumes the grid
  jumps to `currentWeek + 1`, which the 2026-11-08 week-keep lane removed.
- **Static gates:** PHP lint, `node --check` for `ui-common.js` and
  `arrangement-mobile.js`, Blade cache/clear, Pint, PHPStan (0 errors), and
  `LectureSessionCatalogTest` (5 passed, 31 assertions) on
  `class_replacement_testing`.
- **Browser gates (`:8030`, disposable testing DB):** 17/17 core top-zone
  specs passed (`arr-slot-original-venue-sync`, `default-pin`,
  `rearrangement-toolbar-tooltips`, `replacement-strip-anchor`, and the
  `venue-cross-week-handoff` S1 date check). The mobile suite passed 51/51;
  the remaining affected run was 57 passed, 3 skipped.
- **Known unrelated/time-dependent checks:** `venue-cross-week-handoff` S2
  cannot find a span-free W8 cell on 2026-11-12; `arrival-grid-no-pin` R3 and
  `clear-guards-cross-week` BUG-08 core hardcode W8 values that the lead-time
  filter now hides; the venue BUG-08 final line still asserts the removed
  `#clearAllBtn`; the slot BUG-08 first item is already the pinned pair. None
  of these touch the top-zone code or were introduced by this change.
