# Review log — venue-timetable-db

## proposal Round 1 — 2026-10-08 13:10 UTC
Reviewer: @sdd-reviewer (batch 1: proposal.md; frozen: none; baseline: explore-brief.md)

### 🔴 Fixed
(none in round 1)

### 🟡 Addressed
(none)

### 🔴 Outstanding
1. **URL-param inventory factually wrong** — proposal claimed `?venue=&week=`
   deep-link preservation; the legacy template reads only `venue`, `code`, `cohort`
   (no `week` param exists anywhere — week = today + WeekNavigator saved state).
   → FIXED: scope item 1 reworded (preserve `?venue=`; `code`/`cohort` intentionally
   dropped with the booking flow; no `?week=` claim) and the risk bullet corrected.

### Notes (carried to design)
- Holiday erratum: DB has exactly **3** holiday rows (W8 Mon in-lieu, W14 Thu, W14
  Fri); Deepavali W7 Sun is docs-only, NOT in the DB — design must not annotate a
  phantom row (explore-brief line 19 wording corrected by this note).
- Wave-3b pre-emption note needs an explicit tasks.md entry (note already appended
  to the Wave-3b explore-brief addendum this session).
- Pending summary card reads 0 until Slice B — decide v1 presentation (neutral,
  honest 0).
- Legend tip "Free slot — click to book" becomes misleading → reword with the
  booking-affordance decision.
- Success criterion 1 is directional — merge into the ≥2 venues × ≥2 weeks SQL
  spot-audit.

**Verdict round 1: FAIL** (1 🔴) → proposal fixed → re-review round 2.

## proposal Round 2 — 2026-10-08 13:45 UTC
Reviewer: @sdd-reviewer (batch 1 re-review: proposal.md)

### 🔴 Fixed
1. **URL-param inventory** — verified against readUrlParams() (template lines
   509–522): reads only `code`, `cohort`, `venue`; no `week` param exists. Scope
   item 1 + risk bullet now state exactly: preserve `?venue=`; `code`/`cohort`
   dropped with booking flow; no `?week=` claim. Factually accurate.

### 🟡 Addressed
- Fix is declarative (no decision-level change, no unfreeze cascade).
- Round-1 parked notes verified carried, not dropped.

### 🔴 Outstanding
(none)

### Notes
- ⚠️ explore-brief.md line 48 carries the same `?venue=&week=` erratum → corrected
  declaratively at freeze time; design follows proposal/template facts.
- Criterion-merge note: the testable form (≥2 venues × ≥2 weeks SQL spot-audit) is
  the one verified in tasks.

**Verdict round 2: PASS** — proposal FROZEN. Proceed to design (batch 2).

## design Round 1 — 2026-10-08 14:25 UTC
Reviewer: @sdd-reviewer (batch 2: design.md; frozen: proposal.md R2 PASS + explore-brief.md)

### 🔴 Fixed
(none in round 1)

### 🟡 Addressed (round-1 carried notes → design)
1. Holiday erratum — §7 spot-audits W8 Mon; no phantom Deepavali row. ✔
2. Pending card honest 0 + title. ✔
3. Legend tip reworded ("Free slot"). ✔
4. Criterion merged into the ≥2 venues × ≥2 weeks incl. holiday spot-audit. ✔
5. `?venue=`-only deep-link facts respected (no `?week=` claim). ✔
   (Wave-3b pre-emption note remains a tasks.md entry — batch 3.)

### 🔴 Outstanding
1. **`.vt-cell-pending` token violated §10.0 canonical map** — design said
   warning/amber; maps A/C fix Pending = tertiary (engine `.event-pending` =
   tertiary-container; legend swatch matches). → FIXED: `.vt-cell-pending` =
   `--color-tertiary-container` / `--color-on-tertiary-container`.

### 🟡 Should fix (same pass, applied)
1. **VenueDropdown contract pinned** — server-side mapping added to §2:
   `code => room_code`, `name => room_name`, `capacity`, `type =>`
   Tutorial/LectureHall/Lab/CiscoLab (factory expects display values).
2. **Holiday-week card semantics decided** — DB-status-only v1; card 4 renamed
   **"Occupied"** (legacy "Unavailable" counted Sunday+holiday); holiday cells
   visually annotated in-grid, not re-counted.
3. **§3 conditional pre-answered** — use inherited `$this->slotIndex()`;
   `end = slotIndex(end_time) − 1`; null-safe `?->lecturer?->id`.
4. **Modal payload pinned** — emit the `baseEvent` key set (`venue` = room_code);
   capacity/room_name via extraFields; no bespoke modal.
+ ui-summary-bar partial named explicitly; WeekNavigator key `venueTimetableWeek`
  pinned; engine-default occupied styling note added.

### 🔴 Outstanding (after fixes)
(none)

**Verdict round 1: FAIL** (1 🔴) → all fixes applied declaratively → re-review round 2.

## design Round 2 — 2026-10-08 13:55 UTC
Reviewer: @sdd-reviewer (batch 2 re-review: design.md)

### 🔴 Fixed
1. **`.vt-cell-pending` token** — verified: theme.css:1934-1936 `.event-pending` =
   tertiary-container pair; legend partial Pending swatch = tertiary. §5 corrected. ✔
2. **VenueDropdown mapping pinned (§2)** — factory keys Tutorial/LectureHall/Lab/
   CiscoLab verified (ui-common.js:3116); v.code/v.capacity reads verified. ✔
3. **Card semantics (§2/§4)** — DB-status-only, card 4 = "Occupied", holiday cells
   annotated via engine offday-slot, not re-counted. ✔
4. **§3 arithmetic + null-safety** — trait slotIndex() at :134; end-inclusive :234/:261. ✔
5. **Modal payload / storage key** — baseEvent shape trait :257-272; modal reads
   event.venue (ui-common.js:908); statusClassFn :753; key 'venueTimetableWeek' :489. ✔

### 🟡 Addressed
- Round-1 carried notes honored; Wave-3b pre-emption deferred to tasks.md; no
  .vt-cell-* collisions anywhere.

### 🔴 Outstanding
1. **`s.module.title` → non-existent attribute** — Module model has `module_name`
   (app/Models/Module.php:15,20); Eloquent would silently return null. → FIXED:
   §3 now `name = s.module.module_name`.

### 🟡 Should fix (applied this pass)
1. **Modal cohorts row pinned** — extraFields = Cohort / Total Students / Capacity /
   Room Name (cfg pattern from my-timetable.blade.php:150-153).
2. **`.vt-cell-others` + legend swatches pinned** — Others' = surface-variant pair;
   four legend swatches = success / primary / surface-variant / tertiary-container
   (NOT legacy green Others' — same-name-same-color rule); vague "engine-default
   preserved" sentence removed.
3. **Proposal soft-freeze erratum** — card 4 label "Unavailable" → "Occupied"
   (declarative; scope unchanged).

### 🔴 Outstanding (after fixes)
(none)

**Verdict round 2: FAIL** (1 🔴) → all fixes applied → re-review round 3.

## design Round 3 — 2026-10-08 14:05 UTC
Reviewer: @sdd-reviewer (batch 2 re-review: design.md)

### 🔴 Fixed
1. **`s.module.title` → `s.module.module_name`** — Module model attribute verified
   (app/Models/Module.php:15,20); no silent-null modal names. ✔

### 🟡 Addressed
1. Modal extraFields pinned: Cohort / Total Students / Capacity / Room Name. ✔
2. `.vt-cell-others` + four legend swatches pinned (success/primary/surface-variant/
   tertiary-container); vague sentence removed. ✔
3. Proposal soft-freeze erratum: card 4 "Unavailable" → "Occupied" (declarative). ✔

### 🔴 Outstanding
(none)

### Notes
- All prior-round fixes intact; §2↔§3↔§4↔§5↔§6↔§7 cross-consistency holds; partials
  ui-legend-bar/ui-summary-bar exist; binding form is module_name (brief prose
  "title" is cosmetic).

**Verdict round 3: PASS** — design FROZEN. Specs batch SKIPPED (single capability;
engine/event contract fully pinned in design §3; per SDD skill specs are optional
for simple changes). Proceed to tasks (batch 3).

## tasks Round 1 — 2026-10-08 (UTC)
Reviewer: @sdd-reviewer (batch 3: tasks.md)

### 🔴 Fixed
(none in round 1)

### 🟡 Addressed
(none)

### 🔴 Outstanding
1. **timetable-wiring dropped from the T5 gate** — frozen proposal success criteria
   require it; T5 ran only venue-db/nav-identity/auth-full. → FIXED: added
   `tests/timetable-wiring.spec.ts` to the T5 Playwright gate.

### 🟡 Should fix (applied this pass)
1. Holiday-annotation feature assertion restored in T4 (holidays-for-JS contains
   the W8-Monday row).
2. Commit task added to T5 (feat: + chore(sdd): archive after verify; no-push rule restated).

### Notes
- Full traceability walk passed (every frozen requirement mapped; no scope
  invention); forbidden-command check PASS (isolated pattern only); granularity +
  implementability PASS.

**Verdict round 1: FAIL** (1 🔴) → T4/T5 fixed → re-review round 2.

## tasks Round 2 — 2026-10-08 14:09 UTC
Reviewer: @sdd-reviewer (batch 3 re-review: tasks.md)

### 🔴 Fixed
1. **timetable-wiring back in the T5 gate** — T5 Playwright command now runs
   venue-db + nav-identity + timetable-wiring + auth-full (frozen criterion). ✔

### 🟡 Addressed
1. Holiday-annotation assertion restored in T4 (holidays-for-JS contains W8-Monday). ✔
2. Commit task added to T5 (feat: + chore(sdd): archive after verify; no-push rule
   restated). ✔

### 🔴 Outstanding
(none)

### Notes
- Full traceability re-walk PASSED; fixes declarative-only. Non-blocking: AGENTS.md
  commit-type list lacks `chore(sdd)` — if enforced strictly, `docs(sdd):` is the
  drop-in equivalent.

**Verdict round 2: PASS** — tasks FROZEN. All batches frozen; proceed to apply.

## UNFREEZE — design (§1/§2/§4) + tasks (T1/T2/T4) — 2026-10-08 (apply-time discovery)
**Reason (decision-level):** the frozen design pinned week/venue switching to
Livewire actions (`setVenue`/`setWeek`). Reading the Slice A house pattern in full
(my-timetable.blade.php) shows week nav is **fully client-side** (WeekNavigator +
localStorage over an all-weeks `eventsByWeek` map; the init script runs once on
DOMContentLoaded). A Livewire-morphed re-render does not re-execute such scripts —
the frozen architecture would ship a grid that never repaints on week change, or
force a non-house `@script` re-init mechanism. Unfreezing to align with the house
pattern; proposal needs NO unfreeze (it pins "Slice A house pattern", which this
restores). Downstream: tasks T1/T2/T4 updated with the design.

**Unfrozen changes:**
- design §1: component is render-only (mount reads `?venue=`, NO setVenue/setWeek).
- design §2: events query drops the week filter → all-weeks `eventsByWeek` map;
  totals via one `groupBy(week_number, status)` query → per-week totals JSON.
- design §4: week nav client-side (Slice A); venue switching via `?venue=` links
  (full render — scripts re-run naturally, deep links native).
- design §7: week-nav repaint test unchanged in intent; venue-switch assertion added.
- tasks T1/T2/T4: mirror the above.

## design Round 4 + tasks Round 3 (unfreeze review) — 2026-10-08 (apply-time, post-unfreeze)
Reviewer: @sdd-reviewer (design §1/§2/§4/§7 + tasks T1/T2/T4; proposal NOT unfrozen)

### 🔴 Fixed
1. Render-only component + client-side nav — verified fixes the morph/script defect. ✔
2. All-weeks events map (index w-1 matches trait :241). ✔
3. Totals groupBy(week_number,status) — required (Available not derivable from events). ✔
4. Venue-switch assertion added both sides. ✔

### 🟡 Addressed
- Proposal stays valid unfrozen (Slice-A-pattern wording); no new contradictions.

### 🟡 Apply-time notes (non-blocking)
1. VenueDropdown has NO link support — items are divs with onSelect(code); implement
   venue switch as `onSelect: code => window.location.assign('?venue=' + code)` +
   `initialCode` preselect. Do NOT fork the factory.
2. Proposal scope-item-2 soft-freeze erratum (week-filtered wording) — apply at archive.
3. eventsByWeek contiguity — assign EVERY week index 0..N-1 (empty arrays) so @json
   stays index-aligned.

### 🔴 Outstanding
(none)

**Verdict: PASS** — proceed with apply.
