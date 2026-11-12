# Tasks — Replacement Arrangement mobile day view

Batch 4 · 2026-11-11. Baselines: frozen proposal.md, design.md and
specs/mobile-arrangement/spec.md (MA scenario IDs). All boxes start unchecked.
Amendment: source baseline `28f9f4d`, user-approved borderless Pending/Conflict
for everyone; no ownership hint; existing state text/tapped details explain owner.
Blue selection outline stays. The earlier prototype rings are not to be copied.

**This checklist is not permission to implement.** User approval is required
before SDD Apply. Browser execution requires separate explicit permission.
No system code, tests or prototype are edited while preparing/reviewing this file.

## Execution rules

- Each task is estimated <=2 hours; split further if needed rather than expand
  scope or skip checks. Follow dependencies; do not parallel-edit the same file.
- During Apply, editing agents follow the SDD workflow. The parent coordinates
  integration/review and records honest evidence, including skipped/blocked checks.
- Commit only on user instruction. Never push without explicit instruction.
- Preserve unrelated work, especially existing shared ui-common.js edits and
  docs/demo-db-data-changes.md. No DB/schema/data edits or new dependencies.
- Keep the approved prototype unchanged. Do not copy its fixture clock/data or
  simplified guards into production. No shared helper/layout/CSS change planned.
- Tests described below are to be authored during Apply, not this planning batch.
- Browser tests are **not run** until explicit permission; any browser receipts
  that write/fixture data require the existing fail-closed testing guard and
  exact disposable DB/port configuration. Never use demo DBs for those receipts.

## A. Preconditions and baseline

- [x] **T01 — Confirm Apply approval and isolate working tree** (30 min)
  - Dependency: explicit user approval to start SDD Apply; Batch 4 frozen.
  - Record current branch/HEAD/status and existing unrelated file/hunk changes.
  - Re-read maintained project rules and frozen artifacts; check current source
    anchors and other sessions' work before editing. Stop on an overlapping edit.
  - Deliverable: scope/baseline receipt in review-log; no unrelated work staged.
  - Coverage: MA-15a, MA-15c.

- [x] **T02 — Record desktop baseline and regression inventory** (1 hour)
  - Dependency: T01. Files: review-log; read-only code/test inspection.
  - Inventory current controls/IDs, desktop class/style contracts, keyboard and
    venue/subject/slot/arrival/leave/submit flows; record known stale test assertions
    separately from new regressions. Current baseline borderless/four-row/no-hint
    assertions, including SlotBorderAndReasonBadgeTest, are authoritative;
    superseded six-row/3px expectations must not be restored.
  - Define screenshot/computed-style/behaviour comparison cases for 769px and
    ordinary desktop widths. If browser permission is absent, record static
    baseline now and mark browser baseline capture blocked, not completed.
  - T02 completion means the static inventory is recorded; browser capture is
    tracked independently by T25 and need not block this static inventory.
  - Coverage: MA-01b, MA-09a–b, MA-15b.

## B. Page-local skeleton and canonical projection

- [x] **T03 — Add inert mobile markup and controller shell** (1 hour)
  - Dependency: T01 and completed T02 static inventory. Files: new ui-arrangement-mobile partial and
    public/js/arrangement-mobile.js; page Blade include/asset hook.
  - Add semantic hosts for context/controls/tools/timeline/bar and one named
    information dialog. Keep mobile hidden/inert by default; information buttons
    type=button. Expose single controller namespace, init/refresh/destroy ports.
  - Initialise once after existing page boot/URL/intent resolution; early refresh
    calls are safe no-ops. No duplicate booking state/global instance ownership.
  - Acceptance: no mobile output or new handlers active on desktop.
  - Coverage: MA-01a, MA-14a, MA-14c, MA-15a.

- [x] **T04 — Implement read-only canonical snapshot port** (90 min)
  - Dependency: T03. Files: page Blade and adapter.
  - Snapshot real original context, week/venue, eligibility via existing helpers,
    canonical cell classes/labels/tip/aria text and saved entries from store.
  - Preserve original indexes, whitelist state classes, fail closed for missing
    cells, copy text safely; no raw payload classification or fixture registries.
  - Expose date-choice/snapshot pure utilities for dependency-free Node checks.
  - Coverage: MA-02a–c, MA-03a, MA-04a–b, MA-04d, MA-07d.

- [x] **T05 — Integrate coalesced refresh hooks** (1 hour)
  - Dependency: T04. Files: page Blade and adapter.
  - Refresh after canonical build/load/counter, selection/removal, original-summary
    and Tip updates. Guard against recursion and stale queued snapshots.
  - Context comes from existing occurrence resolver; pending confirmations retain
    committed values and callbacks rather than optimistic mobile writes.
  - Coverage: MA-09a–b, MA-14a.

- [x] **T06 — Relocate existing controls and restore on breakpoint exit** (2 hours)
  - Dependency: T03–T05. Files: adapter and page-scoped CSS/markup.
  - One matchMedia owner, explicit node references/comment anchors. Move original
    subject/slot/venue/chips/week/Earliest/Tip/Submit nodes to approved mobile hosts.
  - Close panels before moving; preserve IDs/handlers/value/shared instances.
    Hide/inert inactive hosts while keeping required source containers mounted.
  - Restore exact original order/hosts, current Tip text and dropdown state on
    exit. No booking mutations/navigation just from resize.
  - Coverage: MA-01a–c, MA-02c, MA-07c, MA-14c.

## C. Date navigation and presentation

- [x] **T07 — Implement eligible-day navigation and empty semester state** (90 min)
  - Dependency: T04–T06. Files: adapter/page ports.
  - Use existing eligible week filter/clock; hide Sunday/PH/too-soon replacement
    dates without renumbering indexes or filtering original-slot options.
  - Initial eligible selected/prefilled day, weekday retention/fallback, first
    eligible date otherwise. Occupied eligible days/weeks stay visible.
  - Empty semester exact message, disabled date controls/Earliest/Submit and hidden
    timeline; keep context/tools/saved inspection/removal available.
  - Coverage: MA-02b, MA-03a–d, MA-03f.

- [x] **T08 — Add explicit visible target reveal and Earliest integration** (1 hour)
  - Dependency: T07. Files: adapter and page pulse/flash/navigation hooks.
  - Wire revealTarget after committed existing intent/summary navigation and
    Earliest, including same-week targets without canonical rebuild.
  - Validate current week/venue/date, reveal exact day/time, use existing flash
    mechanic on visible mobile row; do not silently select/navigate around guards.
  - Coverage: MA-03e, MA-09b, MA-14b.

- [x] **T09 — Render daily timeline and continuous selected span** (90 min)
  - Dependency: T04–T07. Files: adapter/page-scoped CSS.
  - Ordered 30-minute rows, readable 24-hour axis, all canonical times and actual
    ownership classes; selected span comes from saved indexes, not table geometry.
  - Selected block has readable start/end/venue, correct equivalent height and
    no duplicated covered half-hours. Text-safe holder fallback, no page-wide
    sideways timetable scrolling or valid-start recolouring.
  - Coverage: MA-04a–d, MA-05a–b.

- [x] **T10 — Connect mobile selection and five-second feedback** (90 min)
  - Dependency: T05, T09. Files: page ports/adapter; narrow selection feedback hook.
  - Recheck real date and subject/original gates, then use canonical toggleCell/
    full-span selection path; never draw optimistic success.
  - Existing saved selection anywhere -> transient guidance, no replacement or
    second block. Fit/day-end failure uses existing toast, 5000ms mobile timing.
  - Preserve desktop feedback/click behaviour; no shared toast-engine changes.
  - Coverage: MA-02c, MA-05a–c, MA-08a.

## D. Details, summary and layout accessibility

- [x] **T11 — Implement information sheets and explicit removal** (2 hours)
  - Dependency: T05, T09–T10. Files: partial/adapter and page remove port.
  - Selected blue block/summary -> full saved details and exact keyed Remove.
    Conflict/pending -> existing permitted plain-text reasons/holders.
  - Legend four swatches with borderless Pending/Conflict samples/rows, no obsolete
    ownership hint; preserve owner wording/details and blue selection outline.
    State-aware Tip by tap with mobile
    floating tooltip suppression and desktop restoration. No additional holder API.
  - Close before existing critical modal opens; preserve current callbacks.
  - Coverage: MA-05d–e, MA-07a–e, MA-08b.

- [x] **T12 — Implement fixed saved-summary bar** (1 hour)
  - Dependency: T05, T11. Files: adapter and mobile partial/styles.
  - Always-visible Select a time/disabled Submit when empty; saved date/time and
    details access when selected, regardless of viewed week/day pointer.
  - Read all saved entries defensively; legacy multiple entries get count/list
    and keyed removals, not hidden/dropped data or new multi-selection support.
  - Use the relocated original Submit command, not a second submit handler.
  - Coverage: MA-06a–c, MA-05e.

- [x] **T13 — Isolate keyboard commands and manage modal focus** (90 min)
  - Dependency: T06, T11–T12. Files: page handleKeyDown hook and adapter.
  - Mobile bypasses legacy grid/undo/help/week/venue shortcuts even with no sheet;
    native button/select keys remain usable and critical-modal Escape still works.
  - Block background shortcuts while information sheet open, including transition;
    named dialog, initial focus, native containment, Close/Escape and identity-based
    focus restoration after refresh/removal. One polite announcement per action.
  - Coverage: MA-12a–e, MA-07e, MA-14a.

- [x] **T14 — Complete sticky/header/safe-area responsive styling** (2 hours)
  - Dependency: T06–T13. Files: page-scoped CSS and adapter geometry observer.
  - Mobile header in document flow; sticky venue/week/day cluster only; compact
    expandable original context; tools above timeline; fixed bar can wrap.
  - >=44×44px controls throughout, 48px nominal time rows; <=85dvh scrolling
    sheets with usable sticky heading/Close; actual measured bar/sticky insets.
  - Toast above bar, scroll-margin for target visibility, safe-area support using
    existing viewport meta, light/dark tokens and reduced-motion support.
  - No global timetable CSS/layout edits. Desktop style branch stays unchanged.
  - Coverage: MA-01b, MA-02a, MA-06a, MA-13a–c.

## E. Expiry and request lifecycle safeguards

- [x] **T15 — Preserve unrenderable saved entries through save/load** (90 min)
  - Dependency: T04–T05, T10–T12. Files: page closure/save/load/remove hooks.
  - Add identity-keyed retainedUnrenderableKeys metadata (no copied selection store).
  - Clock reconciliation invalidates visual pointer/rebuilds canonical states
    without deselect/clearVisual green repaint. Null pointer saves preserve matching
    retained entries, including after resize into desktop.
  - Clear metadata on explicit keyed remove, discard-all/confirmed changes and
    success; never resurrect missing entries. Re-eligibility clears only after
    successful canonical restoration; existing undo still obeys availability/date.
  - Coverage: MA-10b–d, MA-05e, MA-09a.

- [x] **T16 — Implement live clock reconciliation and option rebuilding** (90 min)
  - Dependency: T07, T15. Files: adapter timer/listeners and page calendar port.
  - Recheck local date boundary, visibility/focus and before mobile select/Submit.
    Rebuild existing week options/arrows from helper eligibility, without synthetic
    selector changes. Resolve current/next/earliest eligible week correctly.
  - Zero options -> disabled placeholder/no navigator fallback. Expired selection
    stays inspectable, Submit disabled even with later eligible dates. Keep open
    details readable and show remove/choose guidance; no DB write or silent clear.
  - Coverage: MA-03f, MA-10a–d.

- [x] **T17 — Add final-confirmation identity/date validation** (90 min)
  - Dependency: T12, T15–T16. Files: narrow page proceed/mobile-context hook.
  - Mobile opener reconciles, then opens existing confirmation. Capture presented
    saved and original/pin identity; revalidate live date and exact identity in
    existing Yes callback immediately before Livewire call.
  - Expiry/change -> no network call, persistent review guidance and retained data.
    Desktop-origin default context/path remains unchanged.
  - Coverage: MA-11a, MA-09b, MA-08b.

- [x] **T18 — Add mobile-origin request latch and recovery** (90 min)
  - Dependency: T17. Files: page closure/proceed promise/updateCounter hooks.
  - Set busy synchronously before call; disable Submit/Yes across counter refresh
    and viewport changes. Duplicate mobile-origin callbacks cannot send again.
  - Release on business rejection and promise/synchronous transport errors; keep
    selection, persistent recovery and no blind retry/false certainty. Keep busy
    through successful existing cleanup/redirect. Do not drop latch on adapter exit.
  - Coverage: MA-11b–d, MA-08b.

- [x] **T19 — Complete controller teardown and resize integration** (1 hour)
  - Dependency: T06, T08, T13–T18. Files: adapter/page lifecycle hooks.
  - Clean up owned timers/listeners/observers on page teardown; safe no-op repeated
    refresh/destroy. Preserve existing Livewire guards and confirmation focus.
  - Verify resize itself neither rebuilds booking state nor drops retention/request
    safeguards; desktop receives original controls/Tip and normal interaction path.
  - Coverage: MA-01c, MA-14a–c, MA-10b, MA-11d.

## F. Test authoring (no browser execution implied)

- [x] **T20 — Author pure utility/adapter unit coverage** (90 min)
  - Dependency: T04, T07, T15–T16. File: arrangement-mobile-unit.test.cjs.
  - Test original index retention, eligible-day choice/weekday fallback, unknown
    cell fail-closed, selected-span snapshots, keyed legacy entries and retained
    identity cleanup. Use Node built-ins, no new dependencies.
  - Test clock-driven decisions using helper-supplied real-calendar results, not
    a duplicate production notice-period implementation.
  - Coverage: MA-03a–d, MA-04a–d, MA-06c, MA-10a–d.

### T21 — Mobile calendar/timeline/selection browser test group
  - Dependency: T07–T12. File: arrangement-mobile.spec.ts.
  - Controlled testing fixtures: cutoff/holidays/Sunday/Saturday, empty weeks/
    semester, occupied eligible date, no subject, holder fallback, short span/day
    end, 1h/2h selection, no second block, same-day/off-view inspect/remove, summary
    legacy count and original holiday slot still selectable.
  - Cover text safety, format/labels, whole-span layout and same-week Earliest/
    explicit target reveal. Fixture setup/write paths must use existing fail-closed
    guard; do not author tests that can write via unguarded :8000 defaults.
  - Coverage: MA-02a–c, MA-03a–f, MA-04a–d, MA-05a–e, MA-06a–c,
    MA-07a–d, MA-14b.

- [x] **T21a — Author calendar/context/navigation fixtures and cases** (90 min)
  - Dependencies/safety: T21 group above. Cover MA-02a–c, MA-03a–f and MA-14b;
    establish guarded deterministic fixture setup reused by this test file.
- [x] **T21b — Author canonical timeline/selection/summary cases** (90 min)
  - Dependency: T21a. Cover MA-04a–d, MA-05a–e and MA-06a–c, including defensive
    legacy-store display and off-view exact-key removal.
- [x] **T21c — Author information text/legend/Tip cases** (1 hour)
  - Dependency: T21a–b. Cover MA-07a–d and escaped holder-text rendering.

### T22 — Guard, expiry and submit lifecycle browser test group
  - Dependency: T15–T19. File: arrangement-mobile.spec.ts.
  - Confirm/cancel venue/subject/slot, arrival/pin/intent/leave guards; saved expiry
    same-week rebuild -> week hop -> desktop resize -> explicit removal; option
    expiry/no options and persistent open sheet; no green repaint/resurrection.
  - Clock expiry/identity change before Yes, duplicate Yes/Submit, counter/rotation
    while busy, business rejection/transport/synchronous failure and success path.
  - Use controlled transport responses for browser lifecycle assertions where
    possible; genuine integration writes only in the guarded testing lane.
  - Coverage: MA-08b, MA-09a–b, MA-10a–d, MA-11a–d.

- [x] **T22a — Author existing guards/arrival identity cases** (90 min)
  - Dependencies/safety: T22 group above plus T21a fixture setup. Cover MA-09a–b.
- [x] **T22b — Author expiry/store-retention cases** (90 min)
  - Dependency: T22a. Cover MA-10a–d, including metadata cleanup, desktop resize,
    final option expiry and persistent open information.
- [x] **T22c — Author request identity/busy/error lifecycle cases** (90 min)
  - Dependency: T22b. Cover MA-11a–d and MA-08b using controlled transport where
    appropriate, including unknown outcome, synchronous exception and busy resize.

### T23 — Accessibility, geometry and desktop browser test group
  - Dependency: T13–T14, T19. File: arrangement-mobile.spec.ts; existing specs
    only if a justified assertion extension is needed, not a baseline rewrite.
  - Native Enter/Space, inactive layout tab stops, shortcut suppression with and
    without sheet, critical-modal Escape, containment/return, announcements and
    safe text. Timer checks for 5000ms vs persistent sheets/errors.
  - 320/390/768/769px + ordinary desktop, themes, text zoom, rotation, >=44px,
    overlays/insets/no clipping, no floating Tip duplicate, exact node restoration,
    single controller/listeners and other-page isolation. Capture baseline parity
    evidence without changing known old legend requirements silently.
  - Coverage: MA-01a–c, MA-07c–e, MA-08a–b, MA-12a–e, MA-13a–c,
    MA-14a–c, MA-15a–b.

- [x] **T23a — Author keyboard/focus/announcement cases** (90 min)
  - Dependencies/safety: T23 group above plus T21a fixture setup. Cover MA-12a–e,
    MA-07e and refresh focus from MA-14a.
- [x] **T23b — Author responsive/geometry/theme/desktop parity cases** (90 min)
  - Dependency: T23a. Cover MA-01a–c, MA-13a–c and MA-14c, including node identity,
    text zoom and other-page isolation; compare against T02/T25 baseline.
- [x] **T23c — Author feedback/tooltip/lifecycle-isolation cases** (1 hour)
  - Dependency: T23b. Cover MA-07c–e, MA-08a–b and MA-14a–c/MA-15a–b as applicable,
    including five-second timers, persistent errors and no duplicate floating Tip.

References to T21, T22 or T23 below and in the coverage table mean **all** of that
group's lettered tasks, not a single two-hour umbrella task. Groups are ordered
to avoid concurrent edits to the same browser spec. The group bullets retain
the full fixture/safety/coverage contract; no scenario is removed by splitting.

## G. Verification, documentation and approval gates

- [x] **T24 — Run permitted static/Node gates and record results** (1 hour)
  - Dependency: T19–T23. No browser permission is inferred here.
  - Run node syntax/unit checks, Blade cache/clear, composer lint:check and
    PHPStan with --memory-limit=1G per repo rules. Do not use --env=testing.
  - Restart the dev server only through scripts/dev-serve.sh after actual Blade
    changes, per repo policy; no demo DB mutation or unrelated process kills.
  - Record exact commands/outcomes/baseline failures; unit/static success is not
    browser or accessibility proof. Fix lane regressions as small follow-up tasks.
  - Coverage: MA-15a–b; all static-verifiable constraints.

- [x] **T25 — Obtain browser permission and capture desktop baseline** (30 min)
  - Dependency: T02, T24 and explicit user permission to run browser checks.
  - If absent, stop browser work and report blocked; do not mark task complete.
  - Verify disposable testing DB/port/guard before fixtures/writes; never run
    queue workers or use demo data to manufacture receipts. Use safe baseline
    revision/worktree if needed without discarding the current or other sessions'
    work; no baseline captures that submit on a demo server.
  - Coverage: MA-01b, MA-15b.

- [x] **T26 — Run mobile calendar/selection/guard test group** (90 min)
  - Dependency: T21–T22, T25. Run authorised guarded tests, record exact results
    for MA-02–MA-11 and MA-14b; isolate fixtures and clean up only owned fixtures.
  - Preserve/debug failing evidence; fix regressions before calling these green.
  - No browser permission -> blocked, not skipped-as-pass.

- [x] **T27 — Run responsive/accessibility/desktop regression group** (90 min)
  - Dependency: T23, T25. Run authorised new cases and compare desktop baseline.
  - Include existing ownership (alone, to avoid conflicting fixture resets),
    conflict auto-select, original-venue sync, entry/arrival pin and applicable
    summary/week/submit regressions. Record stale baseline failures separately.
  - Record geometry/computed-style/screenshots for MA-01, MA-12–MA-14; stop
    disposable test server after runs. No claimed screen-reader/device verification
    from automated DOM assertions alone.

- [x] **T28 — Manual mobile review and unresolved verification receipt** (1 hour)
  - Dependency: T26–T27. User/authorised manual review of the agreed prototype
    against real-data presentation: narrow phone, scroll, rotation, enlarged text,
    long details, focus, safe areas and touch. Do not make live demo bookings.
  - Record tested devices/browser settings; flag unavailable real-device/screen-
    reader checks explicitly. Unverified requirements remain unresolved.
  - Coverage: MA-13a–c and visual/touch acceptance across MA-01–MA-14.
  - **Limited receipt 2026-11-12:** user approved the mobile design after a 390px
    Chromium viewport review, light and dark themes. Real-phone touch, rotation,
    device text enlargement, notch safe-area and screen-reader checks were **not
    performed** and remain unresolved. No demo booking was made.

- [x] **T29 — Update page changelog and complete SDD Verify handoff** (1 hour)
  - Dependency: T24–T28 (or explicitly report blocked verification, not a pass).
  - Add dated page changelog entry; append scenario-to-evidence ledger below to
    review-log, with command/test/screenshot or clear blocked/fail status.
  - Run SDD Verify against frozen artifacts; do not self-declare browser passes.
    Review final diff for unrelated hunks and missing gates. Present remaining
    issues; seek instruction before any commit/archive/push, none implied here.
  - Coverage: MA-15a–c and entire capability acceptance.
  - **Done 2026-11-12:** dated changelog entry added; round-1 evidence and the
    MA-01–MA-15 ledger appended to `review-log.md`. Final diff reviewed for
    unrelated hunks; no commit, archive or push performed. Remaining unresolved
    item is the real-device/screen-reader portion of T28.

## Scenario-to-task mapping (completeness checklist)

| Frozen requirement | Main implementation | Authored evidence | Execution/review |
|---|---|---|---|
| MA-01 a–c | T03, T06, T14, T19 | T23 | T02, T25, T27–T29 |
| MA-02 a–c | T04, T06–T07, T10, T14 | T21 | T26, T28–T29 |
| MA-03 a–f | T07–T08, T16 | T20–T21 | T26, T28–T29 |
| MA-04 a–d | T04, T09 | T20–T21 | T26, T28–T29 |
| MA-05 a–e | T09–T12, T15 | T21–T22 | T26, T28–T29 |
| MA-06 a–c | T11–T12 | T20–T21 | T26, T28–T29 |
| MA-07 a–e | T06, T11, T13 | T21, T23 | T26–T29 |
| MA-08 a–b | T10–T11, T17–T18 | T22–T23 | T26–T29 |
| MA-09 a–b | T05, T08, T15, T17 | T22 | T02, T26, T29 |
| MA-10 a–d | T15–T16 | T20, T22 | T26, T29 |
| MA-11 a–d | T17–T19 | T22 | T26, T29 |
| MA-12 a–e | T13 | T23 | T27–T29 |
| MA-13 a–c | T14 | T23 | T27–T29 |
| MA-14 a–c | T05, T08, T19 | T21, T23 | T26–T29 |
| MA-15 a–c | T01–T02, all scoped tasks | T21–T23 guard/diff constraints | T24–T29 |

Checks count as complete only with actual evidence. Writing a test, getting an
artifact review pass or preparing commands is not executing or passing that test.
