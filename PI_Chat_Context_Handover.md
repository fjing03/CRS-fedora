# PI Chat Context Handover — TAR UMT Class Replacement System

> Generated 2026-10-28 (evening, after commit `6be2235`). Source: the full
> session conversation. Purpose: a working reference for a NEW PI chat
> session. Nothing in this file is invented; each claim traces to a
> receipt, a code anchor, or an explicit user ruling recorded below.
> Updated 2026-11-01 (main session): the notif-new-request-ux apply CLOSED
> (§0.6); the ui-footer-global frozen record landed (§0.6 tail); Phase-H
> handover housekeeping committed.
> Updated 2026-11-02 (main session): the Oct-31 demo-DB loss recovered,
> both demo DBs then cleaned to the blank plateau by the user's order (§0.7);
> two SDD-waived UI tweaks committed local (58edc29, a37e452 — unpushed);
> the next PI prompt written (`prompts/pi-prompt-notif-type-filter-redesign.md`,
> gitignored by design).

---

## 0. Quick Status First (read before anything else)

| Item | State |
|---|---|
| Working branch | `fedora-backend` (remote: `origin` = `fjing03/CRS-fedora.git`, PULL-ONLY `upstream` = `FjingXR/class-replacement-system`) |
| Push policy | **NEVER `git push`** — the user pushes manually (they said so explicitly). One exception, commanded 2026-11-02: `67606dd`+`d32ebd7` were pushed by the agent; afterwards the rule resumed. |
| Unpushed commits | **23** (`origin/fedora-backend` = `ab912af`; re-count before trusting). The 2026-11-02 tail: `b0fd936` (toast 728), `a37e452` (toast 800), `58edc29` (modal advisory), the entry-guard chain `62cbbb0`/`b0685ae`/`99329be`, `083f7bd` (student 12h idle), `e8f9671`/`b58b319`/`f1e7f9a` (span-follow), `5056c77`/`f1b27b2`/`88c3b3a` (slot-picker clear), the PI lane `7107abb`/`210a232`/`f0b7215`, then `5af11a6` (notifications.hidden_at), `dc41c79` (schema-migration law), `0e99119` (PI prompt template). |
| Demo DB | `class_replacement_fresh` (:8000) + `class_replacement_demo` (:8001) — both were left **BLANK** by the 2026-11-02 user-ordered cleanout, but **`:8000` holds real data again**: 2 `replacement_requests` + 5 `notifications` created 2026-11-02 (`:8001` still 0). Treat both as live. **Never re-seed the demo DBs**; any demo write = user-gated per write + logged in `docs/demo-db-data-changes.md` + snapshot law first (`backups/README.md`; canonical viva dump = `class_replacement_demo-post-recovery-2026-11-01.dump`). |
| Test DB | `class_replacement_testing` — disposable; fresh+seed fine; used by the suite |
| Suite | Last full PHPUnit run: **287/287** (2026-11-02 entry-guard lane). NOT re-run after the evening UI tweaks (blade/CSS-only changes); Playwright used only as read-only receipts. |
| Gates | `composer run lint:check` (pint), `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` (NOT `types:check` — crashes at 128M), full phpunit, `node` syntax check for JS |
| SDD lanes applied, NOT archived | **12** (see §F — `notifications-real-bridge`'s sdd.yaml was correctly flipped to applied during this audit) |
| The user's language rule | **Reply in B1-level English ONLY — no other languages** |
| Approval rule | investigate first; **no code edits without the user's explicit per-task approval** |

---

## 0.5 SESSION ADDENDUM — 2026-10-31 (the next session's work, read after §0)

> This section records the 2026-10-30/31 agent session so the file stays
> current. Everything below is committed+pushed (the user commanded the
> push this once — the NEVER-push rule otherwise stands).

**Lanes closed this session:**
1. `rearrangement-toolbar-tooltips` — APPLIED + committed. The toolbar's
   three hover tooltips (a/b/c) via `applySlotTip`/`applySubjectTip` +
   the week `selectTip`; **same-day user bug fixed**: the boot-tail
   anchor restore was clobbering the auto-pin's tip (the virtual anchor
   carried no type) — fixed by shipping the REAL `session_type`
   (display-only `pinnedSessionPayload` field) + the anchor carrying
   type/day. One superseded behaviour: the arrangement page's old
   subject-empty hint ("Select a subject first to trigger the timeslots
   selector") went by the fail-open law.
   Receipt `tests/rearrangement-toolbar-tooltips.spec.ts` 3/3 green.
2. `status-tooltips-filter-chips` — FULLY APPLIED (T5/T6/T8 closed after
   the user's TODAY ruling: **keep `Urgent — the Proposed Class is
   TODAY.`**). Receipt `tests/status-tooltips-filter-chips.spec.ts`
   6/6 green; gates green; the 3 failing regression tests
   (request-approval-verify REQ-1/REQ-2 + U3-T9f) confirmed PRE-EXISTING
   and unrelated (staff-5770 data mismatch + the approve-wire toast
   probe drift — none touch the lane's surfaces). Commit topology note:
   the RA/ui-common/theme.css parts had piggybacked into other lanes'
   commits (`4a77a1f`, `bb46a19`) while parked; the closure series
   commits only the remaining files.
3. `notif-new-request-ux` — **PROPOSE CHAIN FROZEN, apply pending**:
   explore (611-line file:line notes) → proposal → design → tasks, all
   through glm reviewer rounds (5 log entries; the D15 copy-family gap
   and the semester-fallback contradiction caught and fixed). Key
   rulings: render-time composition (no emitter/schema change, no
   backfill — legacy rows get the new look for free), inactive =
   read/approved/rejected/cancelled with a REASON-FIRST copy family
   (D15; precedence cancelled > approved > rejected > read), badge =
   actionable-unread (batch-derived; the :55 COUNT query deletes),
   ONE replacement_requests query per render preserved. The ask file:
   `prompts/pi-prompt-UIUX Update New Replacement Request.md`.

**Handover tunnel:** the frozen notif artifacts live in
`.sdd/changes/notif-new-request-ux/` (explore-notes.md is the 611-line
fact base with anchors — read it BEFORE coding). The apply prompt lives
in the chat handoff below.

**Other 2026-10-31 session facts:**
- `ra-result-count-showing-of` — applied + receipted (its code rode
  commit `bb46a19` under the PI session).
- `f73b02c` (submission-conflict-refresh) landed mid-session by PI.
- Tooling: BOTH toolbar-receipt subagents died to a usage-limit error;
  the LR (lead-recipe) lesson = have subagents WRITE ARTIFACTS TO DISK
  as they work (the notif explorer did — its notes survived).
- New window export for receipts: `window.statusTooltip` on the MRH page
  (one line, beside the other exports).

---

## 0.6 SESSION ADDENDUM — 2026-11-01 (notif-new-request-ux apply — CLOSED, T1–T9 receipted)

> The apply phase started (driving frozen prompt: `prompts/pi-prompt-UIUX Update New
> Replacement Request.md`) and was interrupted mid-run; the resume prompt
> **`prompts/pi-prompt-continue-notif-new-request-ux-apply.md`** (exact stuck point, work
> items 1–7, commit ladder) has since been EXECUTED to completion. **Closure:** T7
> idempotency ×2 green (id-keyed fixture sweeps), T8 8/8, gates green, the commit ladder
> landed — `591449e` (T2–T4), `0b7674f` (T5), `acefe72` (T6), `bcdaeb9` (T7/T8),
> `338e22e` (T9), `8b9f6b7` (sdd applied). Everything else below = the historical
> record of the mid-run state; the prompt file stays as the historical reference.

**Done, UNCOMMITTED in the working tree (code frozen, do not rewrite):**
- T1 `app/Services/ReplacementRequestMapper.php` — `stringsFromSpan()` /
  `spansForRequests()` (ONE batched query) / `dayName()` + `DAY_NAMES`.
- T2 `app/Livewire/NavNotifications.php` — render-time composition
  (`composeSubmitted()`), batch-derived actionable-unread badge (NO
  COUNT subquery; the :55 COUNT is DELETED), `inactive_reason`
  classifier (cancelled > approved > rejected > read).
- T3+T4 the blades — `resources/views/partials/notif-row.blade.php` (D1
  + D15 copy family, multiline tip with the Requested-at line
  injected after the Staff line, data-notif-target nulled when
  inactive) + `resources/views/livewire/nav-notifications.blade.php`
  (`$tipAbs`/`$relLong` closures; rel = just now / N minutes ago /
  1 hour ago / N hours ago / Yesterday / N days ago).
- T5 `public/css/theme.css` — `.notif-row-subtitle`.
- T6 `tests/Feature/NotificationsBridgeTest.php` — 13/13 GREEN,
  including the new `test_submitted_row_renders_the_ux_composition`
  (slot 32612 = W6 Fri 13:30–14:00 B010, subtitle 'Surayaini Binti
  Basri (5425)', 'Subject: BMSE3153 (P)') and
  `test_submitted_row_fail_open_matrix` (the D2 awaiting side-effect
  asserted as DELIBERATE + the D15 read arm on class_session_id 67
  week 3 to dodge `uq_replacement_requests_active_block`).
- T8 `tests/notifications-island.spec.ts` (:8000, read-only) — 8/8 GREEN,
  incl. the real legacy row id 1 receipt + the F-2 badge test rename.
- Gates were green before the stop: pint, PHPStan errors:0 at the 1G
  memory flag, full phpunit 277 passed, ONE-query law intact.
  Re-run them at resume; the CODE ITSELF is settled.

**WHERE IT STUCK (the resume point):**
T7 `tests/notifications-island-testing-db.spec.ts` (:8030, testing DB)
re-ends `1 failed` + `2 did not run` on the badge-lifecycle test.
Cause: destructive probe runs (receipt rows + a pending request
`class_session_id=23, week=8, slot=32612`) survived in the testing DB,
so the fixture is NOT re-run-idempotent — uniques collide and badge
counts drift. FIXTURE HYGIENE ONLY; never touch the settled app code
for it. The fix = per-test DELETE sweeps of the exact rows the test
inserted, then `migrate:fresh --seed` on the testing DB before each
run, then the run twice (the second run IS the idempotency proof).

**Also NOT done yet (work items 3–7 in the resume prompt):**
T8 re-verify 8/8, the four fresh gates, the commit ladder (conventional
commits — T3+T4 ride ONE commit; T4 = the partial + the island blade;
T9 = the closing `docs:` commit), T9 the
`page-changelogs/notifications-panel-changelog.md` entry (F-2 badge
semantics + the D2 side-effect + the mapper promotion lines), the
final report.

**Pre-commit hygiene:** DELETE the untracked leftovers
`tests/ProbeRace.php` and `tests/tmp-explore.spec.ts` — they must not
enter any commit.

**Push state (re-count at resume):** only 3 commits ahead of
`origin/fedora-backend` (`5b2acfc`, `31cc7a6`, `e045ea5`); the user has
pushed the older stack themselves. NEVER push — user pushes.

**Other 2026-11-01 session facts (all committed earlier, BEFORE the
apply phase):** `notif-jump-flash-family` (`4a77a1f` — today-column
flash 22 cells via `jumpToToday`; `flashTargetCell` success-family vs
`pulseTargetCell` attention-ring split; the apply round caught the old
`block.day` OBJECT bug → `td[data-day="[object Object]"]` silent
no-op, fixed to `block.dayIdx`), `notif-ghost-retract-strip`
(`6e29d56` — the ghost card dies with the retract + one
`updateCounter()` rider after the resolve/evaluate chain; the demo
B002 wall-to-wall saturation made the in-the-way receipts
data-blocked, declared limitation), the user-ruled no-claim clause
removal (`cb696ee` — SDD-waived, logged),
`submission-conflict-modal-refresh` (`5b0fa8e` + `54d539c` — the
Refresh Page red CTA, keyword palette `.warn-keyword` red / `.go-
keyword` green / `.ge-keyword` blue, the filled-red error-modal face),
`submission-conflict-refresh` (`f73b02c` — `allowUnload = true` page-
lifetime flag in the Refresh callback; the user manually confirmed it
works), `filter-clear-all-off` (`5b2acfc` + `31cc7a6` — Clear all =
filters OFF not reset-to-DEFAULT; the RA two-statement `hideCompleted`
flip; Reset Filters keeps the DEFAULT law),
`rearrangement-toolbar-tooltips` Batch 2 (`e045ea5` — venue identity
tip via `VenueDropdown.triggerTipPrefix`, Picker→Selector wording,
the favourite star + machinery REMOVED, `slotTriggerLabel` = Week ·
Mon D, YEAR · range single-writer with the cohort line).

**Closure + post-closure records (2026-11-01, main session):** the lane
CLOSED — T7 ×2 green, T8 8/8, gates green, the ladder above. Handover
housekeeping landed the same day: the applied-flip record
(notifications-real-bridge), the 8 earlier lanes' artifact dirs tracked,
this addendum updated to CLOSED + 2 handoff prompt files tracked.

**Ready prompts index (state at 2026-11-01, main session):**

- `prompts/pi-summary-cards-confirmed-pending.md` — carries the SDD-lane
  block; TWO assumptions still awaiting the user: ① venue page Total
  Slots plain or split; ② always `X+Y`, or plain `X` when Y=0.
- `prompts/pi-bug-arrangement-reentry-after-pending.md` — the 2-layer
  entry-guard bug (server pin-guard + bfcache revalidation), verified
  anchors; run as a normal fix lane unless SDD is wanted.
- `ui-footer-global` — SDD artifacts FROZEN (2 glm reviewer rounds,
  round-2 PASS: no outstanding); the user orders the APPLY separately
  (stated: days later); code NOT applied in this handover's state.
- Modal-colour lane (yellow/warning confirm-modals, arrangement page —
  5 call sites incl. the navigateTo family) — PLANNED in the main
  session, PARKED; prompt file intentionally not written.
- `prompts/pi-prompt-notif-type-filter-redesign.md` (2026-11-02,
  **GITIGNORED** `.gitignore:38` — local-only by design) — item 2 of the
  user's UI list: brand-new compact `notifTypeFilter` (44→36px,
  `.week-select` colour DNA, zero behaviour change).
- ⚠ 2026-11-01 DATA-LOSS INCIDENT (NOT this lane): the demo DB was
  wiped at ~03:22 by the T7 receipt family writing directly onto
  `class_replacement_fresh`, then restored from pristine + `migrate
  --force` — full entry at the top of `docs/demo-db-data-changes.md`;
  its standing guard (abort unless `CRS_BASE = :8030` AND
  `CRS_MODE = testing`) must ship with the next code commit.

---

## 0.7 SESSION ADDENDUM — 2026-11-02 (demo-DB recovery → cleanout; two SDD-waived UI tweaks; next PI prompt written)

> Everything below was verified in the 2026-11-02 main session. Every
> demo-DB write was explicitly gated by the user, per write, and is logged
> in `docs/demo-db-data-changes.md` — whose 2026-11-02 entries are still
> **UNCOMMITTED** (that file is the other session's log: **do not stage it**).
> The one push this session (`67606dd`, `d32ebd7`) was explicitly commanded;
> the NEVER-push rule otherwise stands (the later commits stay LOCAL).

**A. Demo-DB recovery → twin upgrade → full cleanout (CLOSED)**

1. The October demo record lost in the 2026-11-01 incident was **REBUILT**
   in one transaction — 11 requests (1 approved / 2 rejected / 5 cancelled /
   the 3-pending trio on 39/W14, 49/W14, 97/W8 — all Lim Jia Zheng 5770,
   AMCS2093), 3 exact `class_exceptions`, 90 notifications (badge = 3 unread
   per PL; older rows read at 17:40), look-alike class picks user-approved —
   and verified on-screen (badge, trio tiles, approval inbox, proposer
   history). Receipt: `docs/demo-db-oct31-recovered.md` (commit `67606dd`).
   The executed SQL = `/tmp/opencode/twin-write.sql` (a reference only;
   /tmp is volatile).
2. `:8001` twin (`../CRS-demo`) upgraded to the main repo's HEAD `ed0fb01`
   + the 4 pending migrations (additive, no data loss; the dirty
   `package-lock.json` stashed as "package-lock drift before twin upgrade
   2026-11-01").
3. `:8000` (`class_replacement_fresh`) then FULLY SWAPPED (user choice: full
   swap) from `class_replacement_demo-post-recovery-2026-11-01.dump` (21:19)
   so both servers served the identical state.
4. THEN the user ordered a **FULL STORY CLEANOUT on BOTH DBs**: 4
   audit_logs, 12+0 requests (+2 span rows), 13 slot cells (incl. foreign
   34413/34414), 93+90 notifications, 3+3 exceptions deleted. **Post-state =
   0 everything on both DBs; next `replacement_requests` id = 24.** The
   BLANK state is the intended demo canvas — submits through the real UI get
   proper native spans. Do NOT re-seed the old story; any new demo data is
   user-gated, logged, and snapshot-first.
5. Prevention laws landed in `CodingMAIN.md` §4/§14, `AGENTS.md`,
   `prompts/my-coding-rules.md` (commit `d32ebd7`, pushed): the ⛔
   `--env=testing` trap (no `.env.testing` exists — it would silently boot
   the demo DB; select the testing DB ONLY via
   `DB_DATABASE=class_replacement_testing`), the snapshot law (verified
   `pg_dump -Fc`; `pg_restore --list` must pass; before any demo-DB write
   and after milestones; dumps in `backups/`), user-gate + logging, and the
   canonical viva-state dump pointer. Restore recipe + ledger:
   `backups/README.md`.

**B. UI/UX completed this session** (both SDD-waived w/ user approval;
committed LOCAL, NOT pushed):

1. `58edc29` `ui:` — the always-visible venue-availability advisory in the
   Select-a-Session modal (`resources/views/partials/ui-intent-session-modal.blade.php`
   + `theme.css` `.intent-advisory`): yellow `--color-warning` head + border
   bar, red `--color-error` risk line, the shared `.ge-keyword` (blue) /
   `.go-keyword` (green = the grid's available-green) keyword classes; the
   `.notice-muted` typography was EXTENDED (no duplicated declarations).
   Receipts: 4/4 read-only Playwright + screenshot; pint + phpstan
   (`--memory-limit=1G`) green.
2. `a37e452` `ui:` — the blocked-entry toast `.toast-bar` max-width
   728 → 800px (+10%, a user follow-up on the entry-guard overlap fix).
   Measured: desktop 1280 → bar 749px, the approved sentence now renders
   UNCLIPPED (it tail-ellipsized at 728); mobile 375 → 343px, unchanged
   (the ≤640px media block untouched); zero overlaps, no horizontal
   overflow. Receipts: `/tmp/opencode/toast-desktop.png` + `toast-mobile.png`.

**C. Next PI prompt written:** `prompts/pi-prompt-notif-type-filter-redesign.md`
— item 2) of the user's current UI list: a BRAND-NEW compact design for
`notifTypeFilter` (the bell panel's type dropdown), 44px → 36px, housing
the colour DNA of `.week-select`, zero behaviour change. NOTE: it is
**gitignored** (`.gitignore:38 prompts/pi-prompt-*.md`) — local-only by
design; there is nothing to commit. Verified anchors baked in:
`resources/views/livewire/nav-notifications.blade.php:91`; `theme.css`
`.filter-select` :859 + `.notif-filters .filter-select` :387 (the 44px
`min-height`, the height problem) + `.week-select` :1720–1758 (the colour
reference); the tests couple ONLY on the id / `selectOption`
(`tests/notifications-island.spec.ts` :145/:189/:192–207) — keep it a real
`<select>` with the same id/options/`wire:model.live="typeFilter"`.

**D. In progress — the test lane (awaiting the user's word):**

- Root cause CONFIRMED for the hanging conflict spec:
  `tests/submission-conflict-refresh.spec.ts:122` — `dialog.dismiss()`
  cancels the `beforeunload` navigation, so `page.reload()` never resolves.
  Designed one-line fix: `await pageA.reload({ timeout: 15_000 }).catch(() => {});`
  (keep the `expect(dialogsA).toContain('beforeunload')` assertion).
- Then: reseed `class_replacement_testing`
  (`DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`
  — a re-run left 1 reserve request on W9-FRI ids 27929–32), rerun the
  conflict spec solo at :8030, patch the `receipt-target-guard` review-log's
  "see below" line + flip `sdd.yaml` → `applied`, `rounds: 1`, kill `:8030`
  precisely, then commit `test:` (helper + the 4 guard specs +
  request-approval) and `sdd:` (the SDD record) — **ask before pushing**.
  Files: `tests/helpers/receipt-target-guard.ts` and
  `.sdd/changes/receipt-target-guard/` (both untracked).

**E. NOT done / do not assume:** no PHPUnit run in the 2026-11-02 session
(last full = 287/287 earlier that day); the notification-island specs were
NOT re-run; the `notifTypeFilter` redesign is NOT applied (prompt only);
`:8001`'s clone lags the main repo's post-`ed0fb01` commits until its next
git sync; the composer lane re-runs itself on `:8000` — if `:8000`/`:8001`
diverge again, that is why.
## 0.8 SESSION ADDENDUM — 2026-11-02 (evening) (notif batch-hide design LOCKED + clickable preview approved; next PI prompt written)

> Design-only session: **no app code, migration, or DB row was touched.** The
> work product is (a) a clickable static preview, (b) the locked design, and
> (c) `prompts/pi-prompt-notif-manage-hide.md`. Everything below is verified.

**A. The feature (user-ordered, design locked after a 9-question grill)**

Batch **hide** in the bell panel with a find-back path. Never deletes rows.

1. **Soft-hide:** new nullable `notifications.hidden_at`; hide = stamp it,
   unhide = null it. Rows are never deleted.
2. **Hideable gate = `read_at !== null || inactive_reason !== null`** — the
   exact complement of the badge's actionable-unread predicate
   (`read_at === null && inactive_reason === null`), so hiding can NEVER
   corrupt the badge (the two sets are disjoint by construction). The
   original "inactive only" idea was REJECTED: the classifier only tags
   `submitted`/`awaiting` rows, so decided `approved`/`rejected` and `update`
   rows stay "active" forever — exactly the clutter users want to clear.
   Server MUST re-validate the posted ids against the PHP classifier
   (`isInactiveRow()`) — never trust the client.
3. **Entry:** a list-with-tick icon button in the head, rendered ONLY when
   ≥1 hideable row exists. Mobile: the *Mark all as read* text link becomes
   icon-only too (user decision C), so the head holds pill + 3 × 30px icons
   and never overflows (verified 320/360/390/1280).
4. **Manage mode:** the filter row is replaced by a selection bar
   (`N of M selected · Select all / Clear`), the list shows ONLY hideable
   rows, and every row arrives **pre-ticked**. Muted caption:
   "Notifications that still need you can't be hidden."
5. **Safety = Undo toast only** (no confirm modal — hiding is reversible).
   Copy: "N notifications hidden · Undo restores them".
6. **Footer:** neutral ghost "Hide selected" (no red/blue — it is reversible).
7. **Find-back:** footer line **"Hidden (N)"** in the inbox (only when N > 0,
   and it must render in the caught-up/empty state too) → hidden shelf with
   per-row **Unhide** + **Unhide all** + "Hidden notifications stay in your
   mailbox — restore them anytime." Hidden rows render non-anchor.
8. **Vocabulary:** "Hide", never "Delete"; no "cannot be undone" copy.
9. **Type filter spans the whole filter row** (new ask): the select becomes
   full-width on its own line under the two toggles.

**B. Tooltips (all via the EXISTING system-wide `data-tip` /
`.data-tip-tooltip` system — no new mechanism)**

| Target | Copy |
|---|---|
| unread pill | `N unread messages` (dynamic) |
| Mark all | `Mark every notification as read.` |
| Manage | `Put away notifications you have already read.` + `Nothing is deleted — you can bring them back.` |
| Hidden (N) | `See the notifications you put away.` + `Every one of them can be restored.` |
| Back (hidden shelf) | `Go back to the notification list.` |
| type select | **new** — needs a `data-tip` (it currently has a native `title`, which must be REMOVED to avoid two tooltips) |
| Unread only / Active only | **keep verbatim** the existing `data-tip`s (blade :81, :86) |

Mark all + Back become **icon-only** (envelope-with-tick / arrow-left), same
30px square as the Manage and Close buttons.

**C. Traps found while prototyping (bake these into the implementation)**

1. ⛔ `theme.css:444` `#notifMarkAll` is the old **44px text-button** rule
   (`padding: 0 10px`). An icon-only button inside it gets its 16px svg
   **squeezed to ~10px** (looks faded/greyed). The rule must be **REWRITTEN**
   (size/padding removed), not merely restyled — the preview had to override
   it to look right.
2. `resources/views/livewire/nav-notifications.blade.php:91` still carries
   `title="Filter by notification type"` — remove it when adding `data-tip`.
3. `#notifMarkAll` is driven **by id** in
   `tests/notifications-island-testing-db.spec.ts:179–186` — the icon
   conversion must keep the id (and `wire:click="markAll"`).
4. The PI lane's type-filter re-skin **landed during this session**
   (`210a232` + changelog `f0b7215`, unpushed): `.notif-type-select` is now a
   real 36px class and the dead `.notif-filters .filter-select` rule is gone.
   `prompts/pi-prompt-notif-type-filter-redesign.md` is therefore **historical
   / done**.
5. Badge safety is structural, not vigilance: hideable ∩ badge-counted = ∅.

**D. Clickable preview (the approved visual)**

`/tmp/opencode/notif-manage-preview.html` — static, links the REAL
`theme.css` from `:8000`, three states (`#inbox` / `#manage` / `#hidden`),
fully wired (hide → Undo → shelf → unhide, live counts). Receipts:
`/tmp/opencode/v2-*.png` (desktop + 360px + hover tips). `/tmp` is volatile —
regenerate if needed; the spec above is the source of truth, not the file.

**E. Next prompt:** `prompts/pi-prompt-notif-manage-hide.md` (gitignored,
`.gitignore:38`) — the implementation brief for the PI session.

**F. Amendment (same evening, written AFTER §0.8): the schema prep is DONE and
shipped — §0.8 F as originally written is superseded.**

- **Migration shipped:** `database/migrations/2026_11_02_000001_add_hidden_at_to_notifications_table.php`
  — `hidden_at` nullable timestamp + `(user_id, hidden_at)` index, strictly
  additive. Commit `5af11a6`.
- **Snapshots first (verified):** `backups/class_replacement_fresh-pre-hidden_at-2026-11-02.dump`
  + `backups/class_replacement_demo-pre-hidden_at-2026-11-02.dump`
  (`pg_restore --list` opened each, 56 tables).
- **Applied, in order:** rehearsal on `class_replacement_testing` (2 rows → 2
  rows) → plain `php artisan migrate` on `class_replacement_fresh` (14.77ms) →
  `DB_DATABASE=class_replacement_demo php artisan migrate` (13.34ms). The target
  was printed from the live connection before each run.
- **Counts unchanged:** fresh 5 notifications / 2 requests / 38,640 slots /
  169 users; demo 0 / 38,640 / 169. Every existing row reads `hidden_at = NULL`.
  `Notification::count()` = 5 (Eloquent sane).
- **Law + log:** the schema-migration law landed in `CodingMAIN.md` §4,
  `AGENTS.md`, `prompts/my-coding-rules.md` (commit `dc41c79`); the write is
  logged at the top of `docs/demo-db-data-changes.md` (that file stays
  **UNSTAGED** per the standing rule).
- **Prompt updated:** `prompts/pi-prompt-notif-manage-hide.md` now says
  "already applied — do not recreate" in the header, §2.1, a new trap 6, §4, §5
  and the checklist.
- **New reusable asset:** `prompts/PI-PROMPT-TEMPLATE.md` (commit `0e99119`) —
  the system-wide PI prompt template. It is **committable** (it does not match
  the local-only `.gitignore` pattern `prompts/pi-prompt-*.md`).
- **Still NOT started:** the hide feature itself — no component/view/CSS/test/
  changelog work. §0.7 D's test lane stays parked; do not mix.

---

## Table of Contents

1. [A. Project Overview](#a-project-overview)
2. [B. My Preferences and Working Rules](#b-my-preferences-and-working-rules)
3. [C. Important Decisions and Requirements](#c-important-decisions-and-requirements)
4. [D. Implementation Status](#d-implementation-status)
5. [E. Bug Reports and Investigation Findings](#e-bug-reports-and-investigation-findings)
6. [F. SDD and Development Workflow](#f-sdd-and-development-workflow)
7. [G. Current Problems and Pending Tasks](#g-current-problems-and-pending-tasks)
8. [H. Important File and Technical References](#h-important-file-and-technical-references)
9. [I. Latest Session State](#i-latest-session-state)
10. [Instructions for the New PI Chat Session](#instructions-for-the-new-pi-chat-session)

---

## A. Project Overview

**TAR UMT Class Replacement System (CRS)** — a Laravel + Livewire class
replacement booking app for TAR UMT (FOCS/DCIT). Lecturers book
replacement slots for cancelled classes; PLs approve/reject via an FCFS
dashboard; students see the updated timetable.

- **Stack**: Laravel 13.17, Livewire 4.1, PHP 8.3+ (runtime PHP 8.5.11),
  PostgreSQL, Flux UI + Tailwind, Fortify auth, Blade templates with a
  frozen house design (`theme.css` CSS tokens; SAME NAME = SAME COLOR
  canon), Playwright for receipts, SDD (spec-driven development) for
  every code change.
- **Dev servers**: `php artisan serve --port=8000` (CRS-fedora-backend)
  + a sibling `:8001` (CRS-demo) — restarted via the stale-serve ritual
  after any Blade/PHP edit: kill first (`pkill -f "[a]rtisan serve"`),
  then clean + serve — TWO separate commands, never one string.
- **Key pages**: `/my-timetable-ui`, `/cohort-timetable`,
  `/venue-timetable-ui`, `/student-my-timetable-ui`,
  `/replacement-home-ui`, `/replacement-arrangement`,
  `/my-request-history-ui`, `/replacement-history-ui`,
  `/request-approval-ui` (PL), `/dashboard`.
- **Login** (demo, read receipts): staff IDs **5425** (Surayaini Binti
  Basri — also a DCIT PL; user 1) and **5516** (Mohd Nur Rahmat — the
  second PL; user 2), 5652/5653/5770 (staff; 5770 = Lim Jia Zheng, the
  demo proposer); password `Tarumt@2026`. Students: role-gated
  `role:student`.
- **Core architecture** (CodingMAIN.md is the single source of truth):
  ① Matrix Intersection Engine (4 busy vectors), ② OCC layer (wired —
  `SubmitReplacementRequest` → `validateAndReserve()`), ③ FCFS approval
  dashboard, ④ RBAC + notifications, ⑤ deployment (localhost only).
- **Wave 3b (booking/replacement write path)**: SHIPPED — this session
  verified and updated the docs accordingly.
- **Demo data facts**: `holidays` W14 day 3 = Christmas Eve (Thu Dec 24), day 4 = Christmas Day (Fri Dec 25) — 0=Monday is CONFIRMED (note: `tests/venue-db.spec.ts:10` has a stale comment saying 'W14 Wed/Thu' — cosmetic, fix on touch). `replacement_requests` #1 approved (rr#1: cs 23 W8
  → Fri W6 13:30–14:30 B009, the make-up slots 32612/32613), #5/#4
  cancelled; request **#6 pending** (the user's own live submit from the
  bridge testing); `class_exceptions`: 23/W8 medical_leave, 40/W5
  medical_leave, 40/W7 annual_leave (5 rows total was stale — 3 now);
  holidays: W8 Mon (Deepavali in lieu), W14 Thu + Fri (Christmas).
- **One new table added this session**: `notifications` (schema-add only — the table now holds 4 rows: the user's real submit fan-out ids 3/4 + the 2 logged demo-receipt rows).

## B. My Preferences and Working Rules

1. **Language**: reply in B1-level English. No other languages, ever.
2. **Approvals**: investigation BEFORE fixes for reported bugs ("Do not
   edit my system code yet" until the user approves). Most lanes get an
   explicit "apply" — never assume.
3. **SDD mandatory**: every code change needs a proposal → reviewer
   rounds (glm) → freeze → apply → verify → archive. Small bugfix lanes
   may combine proposal+design+tasks in one batch.
4. **Reviewer command** (the proven recipe — file-based, NEVER inline
   heredocs with nested parens):
   `pi --model commandcode/z-ai/glm-5.3-flash -p --tools read,grep,find,ls --system-prompt "$(cat ~/.config/opencode/agents/sdd-reviewer.md)" "$(cat /tmp/prompt.txt)" > /tmp/roundN.txt`
   (write the prompt to a temp file first; glm can hang — retry or run
   backgrounded; `commandcode/deepseek/deepseek-v4-pro` hangs silently).
5. **Playwright receipts for everything**: fresh in-flow login (staff
   login, `input[name="login_id"]`), screenshots + DOM snippets before
   claiming anything works. Gotchas: use `.cjs` files run from the repo
   cwd (package.json `"type": "module"`); avoid `ctx` name collisions;
   `:visible` is not a CSS selector; add `?_=` cache-busters.
6. **Demo-DB safety**: never re-seed/migrate:fresh the demo DB; never
   modify the user's real requests #1–#5 (#6 pending is theirs too);
   intentional demo-data writes = the user runs hand-drafted SQL,
   logged in `docs/demo-db-data-changes.md` (one exception was granted
   and logged: the two "Demo receipt — poll cycle*" rows, agent-run with
   the rollback SQL in the log).
7. **Honest zeros / honest claims**: never mark something done without a
   receipt; distinguish CONFIRMED vs SUSPECTED findings.
8. **Commits**: conventional (`feat:`, `fix:`, `ui:`, `docs:`,
   `refactor:`, `oop:`), one commit per lane, never push.
9. **UI laws**: Promote-on-3rd duplication → shared partial/helper;
   same-name-same-color; minimise plain text (icon buttons); don't
   overwhelm the page surface (explanations live in hover tooltips);
   confirm critical actions with modals; fewest clicks.
10. **After any Blade/PHP edit**: the stale-serve ritual (two commands) +
    restart the :8001 sibling:
    `cd ../CRS-demo && nohup php artisan serve --port=8001 > /tmp/opencode/demo-server.log 2>&1 &`

## C. Important Decisions and Requirements

### The user's standing rulings (do not change without approval)

| # | Ruling |
|---|---|
| 1 | **Notifications events — ALL FIVE**: submit→PL, approve→proposer+cohorts, reject→proposer, class-cancelled→owner+cohorts+**dept PLs** (`notifyDeptPls`, owner's `lecturers.dept_id`), pending reminder→PLs (24 h, deduped until read, re-armed daily). |
| 2 | **Real-time = Livewire `wire:poll` (option A)** — NOT Reverb/websockets. Poll 10 s. |
| 3 | **Notification lifecycle**: rows never deleted; an inactive target renders the row **disabled** (no href) + tooltip `This notification is inactive because the request was cancelled.` |
| 4 | **Email/S**: SV approved **Gmail SMTP** ("CAN") for demo/testing with the user's personal Gmail; NFR 7.2 may be revised by the USER (their own doc edit). The SMTP flip = an ops step outside code lanes. |
| 5 | **mock-data.js the FILE STAYS** (retire consumers lane by lane; dormant rows = spec anchors). |
| 6 | **B005 no-Diploma REPEALED** (b005-diploma-allowed) — Diploma cohorts may use B005; AMIT2034 renders clean. |
| 7 | **B006 networking priority** still enforced; P→labs rule. |
| 8 | **Receipts strategy** = disposable test-DB suite + hand-run demo seed SQL (never agent-run booking flows on the demo DB). |
| 9 | **The caught-up swap is category-aware** (the hotfix): "No unread in this category — N unread in other tabs" when the bell total > 0 but the viewed tab is clear. The pill/badge = the whole-mailbox unread TOTAL (the frozen F-2 law). |
| 10 | **No bell on the replacement-arrangement page** = ACCEPTED BY DESIGN (BUG-04). |
| 11 | **`?week=` never moves the week selector** (ARRIVAL-GRID-NO-PIN) = WONTFIX (user decision). |
| 12 | **Demo story** (viva ~week of 7 Dec 2026, UNCONFIRMED, on this MSI machine): timetables + booking + cancel class + approve + bells + emails; auth = verbal only; students demo the "replacement class got updated". |
| 13 | **THE pageLivewire() LAW (the hotfix)**: page actions use `pageLivewire()`, NEVER `Livewire.first()` (the bell islands render first). |

## D. Implementation Status

### Completed and verified (receipts exist)

| Item | Receipt |
|---|---|
| occupied-cell-explanations: the venue-grid holder tips (the dictionary: `holderTexts` 103 texts; payload 740KB→432KB) | `40ac994` + live hover receipts |
| holiday-occurrence-badge → superseded by conflict-schedule-header-status-tip: the status tooltip on `.cs-head` (cancelled `⚠️ Class Cancelled — {ReasonLabel}`; holiday literal; none for normal) | `fa39a0c` + 3 anchor receipts (40/W7, 40/W5, 40/W14) |
| selection-wipe-spares-holder-tips (the 1-line selector fix) | `13460c2` + live receipts (0 orphans after selection) |
| slot-cancel-restores-session-anchor ("No, Keep My Selection" restores the handoff trigger) | `58efa83` + byte-equal receipt |
| summary-card-click-navigate (`navigateToSavedBlock` + the tooltip + the × intact) | `f4005cc` + away-hop receipt |
| notifications-real-bridge (the table, the emitter ×4 actions, the islands, the durable POST, the reminder command, the JS retirement) | `f3b15d7` + suite 273/273 |
| The poll-cycle receipts (badge 1→2 mid-flight, no reload; the durable POST; the open-panel survival) | `647dd34` |
| pageLivewire() hotfix (the 8 call sites; the category-aware caught-up text) | `6be2235` |
| CodingMAIN.md synced to the Wave 3b reality; `docs/viva-demo-runbook.md` drafted; `docs/README.md` indexed | `05a6737` |
| The tidy tooltip family, makeup-busy-vector, pending-cell amber, B005 repeal etc. — earlier receipted (see §F) | committed |
| The Oct-31 demo-record REBUILD + both-server recovery (11 requests / 3 exceptions / 90 notifications; on-screen verified) | `docs/demo-db-oct31-recovered.md`; commit `67606dd` (pushed) |
| Demo-DB safety hardening — the ⛔ `--env=testing` trap, snapshot law, user-gate/logging, canonical-dump pointer | commit `d32ebd7` (pushed) |
| The user-ordered FULL cleanout — both demo DBs now blank pristine (0 everything; next rr id 24) | 2026-11-02 session receipts; entries in `docs/demo-db-data-changes.md` (UNCOMMITTED, other session's file) |
| venue intent-session modal advisory (yellow/red/blue token warning block) | `58edc29` + 4/4 read-only Playwright + screenshot; pint + phpstan green |
| toast-bar 728 → 800px (+10%): the blocked-entry sentence is unclipped at desktop; mobile untouched | `a37e452` + measured receipts (desktop 749px / mobile 343px) |

### Partially completed

- `notifications-real-bridge`: **applied + receipted but NOT archived**;
  the Reverb upgrade is deliberately future.
- The viva demo pack: the runbook exists (draft); the SMTP flip, NFR
  benchmark receipts, UAT script = pending.
- The demo seed rows (T6) drafted in the apply message — the USER still
  needs to run them (or skip; the two "Demo receipt" rows already give a
  visible bell). **2026-11-02 note: moot for now — the demo DBs were cleaned
  to BLANK by the user's order; a populated story would need a fresh
  user-gated seeding run.**
- The 2026-11-02 test lane: the conflict-spec hang is root-caused and the
  one-line fix is designed; the guard-lane specs + the `receipt-target-guard`
  SDD record await the reseed + solo rerun + commits (NOT applied yet — see
  §0.7 D).

### Planned (agreed, not started)

- Notification "handled/inactive" nuance for OTHER statuses (only
  `cancelled` requests currently disable rows) — parked.
- Reverb (true push) upgrade — additive future lane.
- `notifTypeFilter` compact redesign — prompt written
  (`prompts/pi-prompt-notif-type-filter-redesign.md`, gitignored; item 2 of
  the user's current UI list): 44 → 36px height, `.week-select` colour DNA,
  behaviour frozen. NOT started.
- NFR 1.2 benchmark (<100 ms OCC) + the engine <500 ms receipt — for the
  demo-pack week (no number written until measured).

### Unverified / believed-work

- The user's manual retest of approve/reject/cancel-request flows after
  the pageLivewire hotfix (the hotfix targeted correctly in the live
  probe, but the user has not confirmed the actions end-to-end).
- Gmail SMTP delivery (the SV approved; the setup not yet performed).

## E. Bug Reports and Investigation Findings

| # | Bug | Root cause | Status |
|---|---|---|---|
| 1 | Occupied cells without tooltips (the original investigation) | The venue-grid arm emitted 3-key triples; the blade's `cellData[3]` branch never got data (the designed 4th key was never implemented) + the `?week=` landing confusion | FIXED (occupied-cell-explanations) + the week UX explained |
| 2 | The phantom B102 block in tooltips | The holder labels collided on the shared (day,start,end) key — room-blind | FIXED (the room-rider key, `c9c5768`) |
| 3 | The tooltip blocks lacked why-tags | The user's own add (round 3 of the tidy lane) | DONE (👤/🏛/👥 tags) |
| 4 | Selection wiped tooltips | `clearMergedBlock`'s domain-blind selector | FIXED (13460c2) |
| 5 | The slot-dropdown cancel blanked the trigger | The handoff's null `lastSlotIndex` | FIXED (58efa83) |
| 6 | THE HOTFIX REGRESSION (pageLivewire): cancelRequest/reject/approve/intentSessionOptions 500 "public method not found" | The bell islands broke `Livewire.first()` | FIXED (pageLivewire(), 6be2235) — the user should re-verify by hand (their real request #6 = their test case) |
| 7 | "No new notification after submit" | NOT A BUG — the rows WERE created (ids 3/4); the arrangement page has no bell (BUG-04 frozen) | Explained |
| 8 | Pill=1 next to "all caught up" | The F-2 whole-mailbox total vs the per-category tab | IMPROVED (the category-aware caught-up text) |
| 9 | W14 Thu/Fri "occupied without tooltips" investigation | ALSO found: the phantom W14 occurrence on the strip; the W7-vs-W14 landing mismatch | Resolved via the two header/status lanes + the documented design law |
| 10 | BUG-01/02/03/05/06 (the 2026-10-24 pending bugs) | See docs/replacement-arrangement-pending-bugs.md | **PENDING the user's verdicts** — do not open fix lanes until they review |
| 11 | `tests/submission-conflict-refresh.spec.ts` hangs on the Refresh callback | CONFIRMED 2026-11-02: `dialog.dismiss()` cancels the `beforeunload` navigation, so `page.reload()` (:122) never resolves | FIX DESIGNED (`reload` with timeout + `catch`) — NOT applied; awaits the user's word |
| 12 | The October demo record went missing | See `docs/demo-db-oct31-recovered.md` — the record was rebuilt in one transaction; prevention laws landed in `d32ebd7` | RECOVERED + REBUILT; both DBs since cleaned to the blank plateau by the user's order |

## F. SDD and Development Workflow

**Applied, awaiting `/sdd-archive`** — **12 lanes** (grep-verified; archived only on the user's word):

1. `mock-statics-die` (+ the Diploma scale amendment)
2. `b005-diploma-allowed`
3. `makeup-busy-vector` (+ the pending-cell amber round)
4. `occupied-cell-explanations`
5. `holiday-occurrence-badge` (partially superseded by #6)
6. `conflict-schedule-header-status-tip`
7. `selection-wipe-spares-holder-tips`
8. `slot-cancel-restores-session-anchor`
9. `summary-card-click-navigate`
10. `notifications-real-bridge`
11. `cancelled-blocks-stay-visible`
12. `class-cancel-2-working-day-window` + `class-cancel-db-persist`

**EXCEPTION (do not try to archive)**: the tidy-tooltip work
(why-tags/rounds/hotfixes, commits `de3db81`/`c9c5768`/`7dd99a4`) shipped
WITHOUT its own `.sdd/changes/` dir (it rode the
`prompts/pi-tidy-arrangement-cell-tooltips.md` prompt + commit chain) —
there is nothing to archive; the changelog is the record.

**2026-11-02 note**: the entry-guard (`62cbbb0`), slot-picker-clear
(`88c3b3a`), span-follow-subject-switch (`b58b319`) and student-12h-idle
(`083f7bd`) lanes shipped the same way (prompt / commit-chain lanes, no
`.sdd/changes/` dir). `receipt-target-guard` IS an SDD lane; its record
(`review-log` + `sdd.yaml`) is still UNTRACKED pending the test-lane close
(§0.7 D).

**Also in `.sdd/changes/` — WIP, NOT applied (the new agent must know):**
- 3 dirs with PARTIAL artifacts and **no sdd.yaml** (do not treat as
  lanes): `venue-c2-toast-noise`, `venue-intent-banner-kind`,
  `venue-intent-ghost-success-spot` — fix or delete by the user's
  decision.
- 2 ACTIVE lanes: `class-modal-affordances`,
  `selection-budget-original-duration`; 1 in EXPLORE:
  `cohort-venue-replace-now` — their state is unclear; re-verify before
  touching.

**Frozen**: NONE (the notifications lane's sdd.yaml `frozen` label was
caught during this audit and flipped to `applied`).

**SDD storage**: `.sdd/changes/<name>/` (proposal/design/tasks/
review-log/sdd.yaml; the status flow: proposed→review→frozen→applied).
Helper scripts: `SDD_ROOT=.sdd bash ~/.config/opencode/tools/sdd/{new-change,list-changes,change-status,archive-change}.sh`.

**Rules**: batches created one at a time with reviewer rounds
(MAX 5); frozen artifacts = declarative-only edits; a decision-level
change = unfreeze the chain. The reviewer verdict: no `### 🔴
Outstanding` (or empty) = pass/freeze.

**Temporary code to remember**: `TEMP-HOLDER-LABEL` (grep-able ×5 —
teardown AFTER the final viva).

## G. Current Problems and Pending Tasks

1. **[USER]** Verify by hand: approve / reject / cancel-request /
   venue-book flows after the pageLivewire hotfix (request #6 pending is
   the ready test case). **2026-11-02 note: #6 was removed by the cleanout —
   the demo DB is blank; use a fresh gated submit as the test case, or skip.**
2. **[USER]** Run (or skip) the T6 demo seed INSERTs for a populated demo bell.
   **(Moot while the demo DBs are blank — see §0.7 A4.)**
3. **[AGENT, on the user's word]** `/sdd-archive` ×8 lanes.
4. **[USER]** The BUG round verdicts (BUG-01/02/03/05/06).
5. **[USER/OPS]** Gmail SMTP flip (`MAIL_MAILER=smtp`, host
   `smtp.gmail.com`, port 587 TLS, an App Password) + Mailpit as the
   fallback — the runbook has the setup.
6. **[AGENT, demo-pack week]** the NFR benchmark receipts; the UAT script.
7. **[USER]** Confirm the viva date (anchored: week of 7 Dec 2026).
8. **[USER]** Push the commit stack — now **13** local commits
   (`origin/fedora-backend` = `ab912af`; re-count before trusting).
9. **[LOW]** `?week=14`-style deep links still land on the remembered week
   (accepted); the notification "handled" nuance beyond `cancelled`.
10. **[AGENT, on the user's word]** The 2026-11-02 test lane (see §0.7 D):
    apply the `:122` reload fix, reseed the testing DB, rerun the conflict
    spec solo at :8030, patch the `receipt-target-guard` record, commit
    `test:` + `sdd:` — **ask before pushing**.
11. **[PI, on the user's word]** Execute
    `prompts/pi-prompt-notif-type-filter-redesign.md` (item 2 of the UI
    list; prompt is gitignored/local-only by design).
12. **[USER]** The viva demo data: both DBs are **BLANK** — decide whether a
    populated story is wanted (user-gated SQL + logged + snapshot-first).

**Hard absolutes the new agent MUST know (previously missing):**

- **⛔ TC70**: `tests/venue-timetable.spec.ts:690–730` must NEVER execute
  before T10 retires it — a real destructive write against the demo DB.
  Run any venue-timetable suite with `--grep-invert "TC70"`. The rule
  lives in the repo's AGENTS.md and the skills — READ AGENTS.md.
- **The queue worker**: NOTHING is running `queue:work` right now — the
  queued Mailables pile up in the DB `jobs` table and send only when the
  worker runs (`php artisan queue:work`, or `--once` for one). The SMTP
  flip is pointless without it (the demo-pack item).
- **Playwright status**: the 273/273 receipt is PHPUNIT only. The
  data-dependent Playwright specs (request-approval, warning-keywords)
  are NOT re-receiably true post-bridge — the pending request #6 can
  change their shape; re-run before trusting them.
- **UNTRACKED / unstaged inventory (2026-11-02 — re-check with `git status`)**:
  - untracked: `.sdd/changes/receipt-target-guard/`,
    `tests/helpers/receipt-target-guard.ts`, `tests/request-cancel-pending.spec.ts`,
    `docs/livewire-js-investigation.md`, `docs/Livewire-JS-Investigation-Report.md`,
    `issues/ugly toast message.png`, two user prompts under `prompts/`
    (`Bug Fix: Conflicted Subject Does Not Auto-Select…`,
    `Investigate Livewire JavaScript.md`).
  - modified-but-unstaged: the five guard-lane spec files +
    the user-modified `prompts/pi-bug-arrangement-reentry-after-pending.md`;
    and `docs/demo-db-data-changes.md` — **NEVER stage that one** (the other
    session's write log).
  - `prompts/pi-prompt-notif-type-filter-redesign.md` is **gitignored**
    (`.gitignore:38 prompts/pi-prompt-*.md`) — local-only by design; nothing
    to commit.
- **CodingMAIN.md stale spots** (during the next docs pass): :40 (Obj 4's
  "NEXT LANE" note — the bridge shipped), :374 (the "read-only until
  Wave 3b" heading), :382–389 (the mock-fallback page table incl. the
  dead `/upcoming-replacements-ui` route), :7 ("Last reviewed: 2026-10-10").

## H. Important File and Technical References

- `CodingMAIN.md` — the synced spec (Objective 2/4 🟢; the read-only rule
  retired; the notif bridge = the NEXT-LANE note now applied).
- `app/Support/Notifications/NotificationEmitter.php` — the five-event
  emitter (after-commit; `send` via `write`; the dept-PL scoping).
- `app/Livewire/NavNotifications.php` + `resources/views/livewire/nav-notifications.blade.php` — the island (variant bell/panel;
  `wire:poll.10s`).
- `app/Support/ReasonLabel.php` — the ONE reason-label law (don't
  duplicate).
- `resources/views/partials/ui-nav-bar.blade.php` /
  `ui-notifications-panel.blade.php` — the nav shell + the panel island
  mount.
- `resources/views/partials/page-data-script.blade.php` — the payload
  shard (the >60KB chunking — CRITICAL for big PAGE_DATA payloads).
- `public/js/ui-common.js` — `pageLivewire()` (line ~1882),
  `durableMarkNotifRead`, the rewritten `initNotifPanel`; the retired
  mock-phase notif functions are GONE.
- `app/Services/MatrixIntersectionEngine.php` — the busy vectors (the
  room riders; the makeup vectors) — DO NOT break the 5-slot tuple law.
- `app/Livewire/ReplacementArrangement.php` — the payload builder
  (`venueSlotsByWeekPayload` 4-key + `holderTextLibrary`; `busyByWeekPayload`
  with the inline labels — TEMP-HOLDER-LABEL).
- `resources/views/livewire/replacement-arrangement.blade.php` — the
  arrangement's render components (`renderHolderTip` + the grid
  pseudo-matches at ~:1803; `writeSlotTriggerFromAnchor`;
  `navigateToSavedBlock`; the clearMergedBlock selector law).
- `app/Actions/*` — the emission call sites (submit/approve/reject/cancel).
- `app/Console/Commands/NotifyPendingRequests.php` + `routes/console.php`
  — the reminder (daily 08:00 + on-demand).
- `routes/web.php` — `POST /notifications/read` + `/read-all`.
- `docs/viva-demo-runbook.md`, `docs/demo-db-data-changes.md`,
  `docs/replacement-arrangement-pending-bugs.md`, `docs/queue-and-emails.md`.
- `page-changelogs/*.md` — update after any page change.
- Commands: the stale-serve ritual; `php artisan queue:work --once`;
  `php artisan notifications:remind-pending`.

## I. Latest Session State

1. **Most recently (2026-11-02)**: the Oct-31 demo-DB loss recovery —
   record rebuilt + verified, `:8001` twin upgraded to `ed0fb01`, `:8000`
   full-swapped, then the user ordered a FULL cleanout: **both demo DBs are
   now blank pristine** (0 everything, next rr id 24). Two SDD-waived UI
   tweaks committed local (`58edc29` modal advisory, `a37e452` toast 800px —
   plus the earlier entry-guard/spans/slot-clear/student-idle lanes: 13
   commits unpushed). The next PI prompt written (gitignored):
   `prompts/pi-prompt-notif-type-filter-redesign.md`.
2. **Investigated & closed**: the demo-DB recovery chain (receipts +
   prevention laws, `67606dd`/`d32ebd7`), the two UI tweaks (receipted), and
   the conflict-spec hang root cause (`dialog.dismiss()` vs `beforeunload` —
   fix designed, not applied).
3. **Unfinished**: the 2026-11-02 test lane (fix + reseed + rerun + the
   `test:`/`sdd:` commits); the `notifTypeFilter` redesign (prompt only);
   the older open items (archives, BUG verdicts, SMTP ops, benchmarks) and
   the new viva-demo-data decision (the canvas is blank).
4. **The new AI should do first**: read this file top to bottom (esp. §0.7),
   then `git status` + re-count `origin..HEAD`; the demo DBs are BLANK —
   do not seed them without the user's per-write gate; await direction.
5. **MUST NOT do without approval**: push; demo-DB writes or reseeds
   (outside the gate; ⛔ never `--env=testing`); staging
   `docs/demo-db-data-changes.md`; opening/reopening SDD lanes; touching the
   user's real requests; changing the frozen UI classes/copy; skipping the
   reviewer rounds; running the notif-filter prompt without the run order.

---

## Instructions for the New PI Chat Session

- Load the repo skills first (`crs-workspace`, `sdd-workflow`) and
  `prompts/my-coding-rules.md`; CodingMAIN.md is the spec source.
- The house language is B1 English. Be concise; receipts over claims.
- When the user says "go --> <prompts/pi-*.md>", execute that file
  verbatim.
- The prompt files live in `prompts/` (`pi-*.md`); Claude handoffs too
  (`Claude_Gmail_SMTP_Setup_Prompt.md` — already corrected).
- Verify every claim with Playwright or tests before reporting; archive
  lanes only on the user's word; keep `docs/` + `page-changelogs/` in
  sync with every change.
- When in doubt about a demo-DB write: don't — draft the SQL and hand it
  over.
