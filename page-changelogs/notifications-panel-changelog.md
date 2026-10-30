# Changelog — Notifications Panel (shared nav bell)

## [2026-10-02] Notifications panel behind the nav bell (frontend mock) — SDD change `notifications-panel`

Kills the `alert('Notifications panel')` stub and the hardcoded "3" badge: the
top-bar bell now opens a real panel — an **unread-only todo tray** with per-role
rows and localStorage read-state (FR-4.15 / 2.13 / 1.9 mock surface; FR 4.16
backend = Sprint 3).

### Files Created

#### `resources/views/partials/ui-notifications-panel.blade.php`
- Overlay + dialog shell: scrim, `role="dialog"` panel, header with unread pill
  and **"Mark all as read"** text button (user-granted rule-5 exception; hidden
  at 0 unread), ✕ close, JS-rendered `#notifList` body.
- Inline caught-up empty state ("You're all caught up").
- Wired by the ui-common `initNotifPanel` module — no page-side JS.

### Files Modified

| File | Change |
|---|---|
| `resources/views/partials/ui-nav-bar.blade.php` | Bell → `toggleNotifPanel()` + `aria-haspopup` / `data-tip`; badge span emptied + `hidden` (AD-8 no-flash); panel partial included after the drawer |
| `resources/views/layouts/ui-template.blade.php` | Dropped the `notifCount` forwarder arg (last server-side "3" echo) |
| `public/css/theme.css` | New tokens `--top-bar-height: 56px` (`.top-bar` refactored to consume it; visuals byte-identical, screenshot-verified) + `--color-scrim` light/dark pair (the one approved rgba); `.notif-*` family — desktop 360px fixed popover @ z 999 / ≤768px full-width top sheet @ z 998 overlay, 44px rows, 5 canonical container tiles |
| `public/js/ui-common.js` | Hoisted `openNavDrawer()`/`closeNavDrawer()` out of `initMobileNav` (behavior-identical, smoke-tested open/close/Esc/swipe/scroll-lock). Panel module: `NOTIF_ROLE_BY_PAGE` (/studentMyTimetable + upcomingReplacements → student, requestApproval → pl, else lecturer); per-role localStorage read state (AD-4, seed-once first paint from `read: true` flags, `MockData` never mutated); `relTime` + absolute `data-tip` times; `renderNotifList` (AD-5 unread-only + caught-up swap + AD-15 scroll restore); `markNotifRead`/`markAllNotifsRead`; `open/read/close/toggleNotifPanel` (aria-expanded, body overflow inline lock, AD-12 drawer↔panel mutual exclusion BOTH directions); `refreshNotifBadge` on DOMContentLoaded |
| `public/js/mock-data.js` | New §2.13 `MockData.notifications` — 12 rows (4/role: pl submitted/awaiting; lecturer approved/rejected; student update), `minutesAgo` ages, `read: true` on 6 seed-read rows; DELETED dead `studentTimetable.notificationCount` + stale comment (§2.5 single-source) |
| `ui-design-templates/request-approval-UI-design-template.blade.php` | `@extends` += `pageKey => 'requestApproval'` (role-map lever; routes untouched) |
| `ui-design-templates/student-my-timetable-UI-design-template.blade.php` + `upcoming-replacements-UI-design-template.blade.php` | Deleted per-page `'notifCount' => 3` args + inline `notifBadge.textContent` feed blocks (badge now computed by `refreshNotifBadge`) |

### Role → seed state
- Fresh localStorage = **badge 2 on every page** (2 unread rows per role; 6
  pre-read rows seeded at first paint).
- Behavior: row click = mark read + navigate (D4 deep links); list shows unread
  only (trims on read); 0 unread → "You're all caught up"; panel does **not**
  auto-clear on open.
- localStorage reset instructions: `localStorage.removeItem('notifications-read-student')`
  (also `-pl`, `-lecturer`) in DevTools → back to badge 2.

### Deviation notes (honest)
- Design's `.notif-head` border-bottom called for `--color-outline-variant` —
  token never existed in theme.css; used `--color-outline`.
- Data-tip on rows sits on the row anchor (whole-row hover) rather than only the
  time span; shown elements keep their `hidden` attribute with inline display
  overriding (cosmetic consistency note).
- Font sizes normalized to px per house convention (design's 0.75rem = 12px).

### Verification
T9–T14 all pass:
- [x] Fresh badges 2/2/2 on student / PL / lecturer pages
- [x] Row click trims + navigates; mark-all → caught-up
- [x] Persistence both ways + key-removal restore
- [x] 1280↔375: popover ↔ top-sheet
- [x] Drawer mutual exclusion both directions; overlay/Esc close
- [x] Scroll-glued fixed panel
- [x] Dark + light token render; console 0 errors
- [x] Hardcoded-color scan clean
- [x] Lint/types = pre-existing app/-only set

## [2026-10-02 post-apply] "Unread only" filter toggle (design §12, AD-19/AD-20)

User-requested permanent revision: the implicit unread-only tray gains an explicit filter switch.
**ON (default, every load)** = frozen AD-5 behavior byte-for-byte. **OFF** = all of the role's rows
render newest-first (`minutesAgo` asc), read rows muted via `.notif-row--read` (weight-400 title in
`--color-on-surface-variant`; still live deep-link anchors, mark-read idempotent). Caught-up message
only fires in ON mode; badge/pill/mark-all always follow the unread count. Toggle state not persisted.

| File | Change |
|---|---|
| `partials/ui-notifications-panel.blade.php` | `.notif-filters` row with the shared `.toggle-wrapper` switch `#notifUnreadOnly` (checked default, label "Unread only") |
| `public/css/theme.css` | `.notif-filters` strip + `.notif-row--read` muted variant (tokens only) |
| `public/js/ui-common.js` | `renderNotifList` filter branch (unsorted frozen order ON / sorted copy OFF — MockData stays read-only), toggle `change` → re-render with AD-15 scroll restore, null-guarded |

Verified: 7 rows/4 muted in OFF mode, badge untouched by the toggle, muted-row click navigates
without storage change, mark-all in OFF mode mutes all + hides badge but keeps the list, ON-mode
caught-up intact, no horizontal overflow at 375px, console 0 errors.

## [2026-10-02 post-apply] Approved tile → success green (user correction)

`.notif-tile-approved` moved `--color-secondary-container` → `--color-success-container` (+ `-on-`),
matching every other live "Approved" surface (`.badge-normal`, approved summary value). The §10.0
canonical legend row "Approved → secondary-container" is the stale source — noted as backlog; the
design §5 table carries the correction inline.

## [2026-10-03 post-apply] Row hover tooltip = full message (AD-21)

Rows clip long titles/descs to one line in the 360px panel; hovering a row now shows the **full
text message** (`title — desc`) in the house tooltip, and the absolute datetime tooltip moved onto
the `.notif-row-time` span (design §3's original placement). Nested data-tips resolve via the
global `closest('[data-tip]')` mechanism — hover anywhere on the row = message, hover the time =
date/time. Rows also gained `aria-label` = full message.

**Post-verify fixes (from `.sdd/changes/notifications-panel` verification report, 2026-10-05):**

1. **D-1 (blocking) — AD-9 desktop list cap implemented:** `.notif-list` gains
   `max-height: 380px` on desktop (design.md D7's frozen number, over the proposal's "~420px")
   so rows stay reachable on a short viewport; the ≤768 mobile branch reverts it (`none`) —
   that mode scrolls the whole sheet per design.md:152. Verified live: with overflowing probe
   rows the list scrolls its full 504px range and the last row lands exactly on the panel's
   bottom edge; mobile recomputes panel cap `calc(100dvh - 56px)` with no inner cap.
2. **Suggestion 2 upgraded to fix — `.notif-close` styled:** it had no CSS at all and rendered
   the UA-default outset button in the panel header. Now mirrors the established
   `.modal-close` family (30×30, radius-sm, 1px outline border, on-surface-variant ✕, hover
   surface-variant / outline-strong) — same-name-same-color per §10.0.

### Postscript — sweep-fixes-round-1 (2026-10-06, F-2 pre-wire + F-12 closure)

1. **F-2 pre-wired to per-user mailbox semantics (user decision: backend-ready):** the badge
   now gives one number per logged-in user — `recipientId` derived onto every mock row
   (one-place map at the mock-data aliasing block, rows copied per the read-only convention;
   backend day = the API returns the user's own rows, drop the block); the per-role read
   store (`notifications-read-<role>`) is retired in favour of ONE user store
   (`notifications-read-user-<staffId>`), migrated at first paint by union (seed = the 10
   data `read:true` rows; legacy contribution verified equal), old keys removed.
   Badge/pill/mark-all follow the **mailbox unread total**; the panel LIST stays
   category-scoped by page context (AD-2 frozen, kept); mark-all = the whole user mailbox
   (backend semantics); panel open resyncs the header. AD-4/AD-8 comments updated.
   Verified live: badge 11 on lecturer AND student pages (drift gone), mark-one-read → 10
   with pill synced, reseed deterministic on fresh store, 0 console errors.
2. **F-12 (user decision: document as exception):** `CodingMAIN.md` §10.0 rule 1 now
   sanctions the layout's pre-theme paint literals (`html.dark/html.light` background in
   `ui-template.blade.php`) as the sole FOUC-guard exception, with an update-both note.
   Zero code change.

## 2026-10-28 (notifications-real-bridge lane) — the bell is REAL: five events, DB read-state, wire:poll self-updates
The frozen panel's feed swapped from the mock §2.13 seed to the real
`notifications` table (fan-out rows per recipient; `read_at` = the DB-backed
read state; the `type` = the FROZEN tile keys; `role_view` = the AD-2 key).
- The emitter (NotificationEmitter, after-commit — the mails' law) writes
  on: submit→PLs; approve→the proposer + the cohorts' students; reject→
  the proposer; class-cancelled→the class's lecturer + the cohorts'
  students + the department's PLs (the 5770/ABCD6767 ruling);
  reminders→PLs (24 h, deduped until read, re-armed daily,
  `notifications:remind-pending`).
- The islands (ONE component, two mounts): the bell button + badge and
  the whole panel body render server-side under `wire:poll.10s` — the
  badge updates itself without a page refresh (the user's ruling).
- The Dulrante read path: native anchors + the capture-phase durable
  POST (`/notifications/read`, keepalive) — the D4 law with the DB
  state. The localStorage store + the mock read seeds RETIRED from JS
  (mock-data.js the FILE stays, §2.13 dormant as the spec anchor).
- The lifecycle: rows never delete; a cancelled/missing request target
  renders the row disabled (no href) with the user's tooltip
  "This notification is inactive because the request was cancelled."
- PENDING_NAV_COUNT (the approval nav badge) untouched — a different truth.
- Tests: 7 new bridge tests (273/273). The demo's visible rows = the
  user-run seed INSERTs (the data-changes convention).

### The poll-cycle receipt (2026-10-28, live demo DB)
With the page OPEN and zero reloads: a second DB-side event row fired
mid-session → the badge grew **1 → 2 within one poll cycle (≤10 s)**
(screenshots /tmp/poll-before.png → /tmp/poll-after.png). The click
receipt: row click → the durable POST landed → the row reads read and
the badge shrank to 1 after the back-navigation (the D4 law live). The
open-panel-poll-survival receipt (AD-15): the panel stayed open with the
list intact across a poll morph (/tmp/poll-survival.png). The two
receipt rows stay in the demo DB (title `Demo receipt — poll cycle*`,
safe to delete — logged per the data-changes convention).

## 2026-10-28 (the bridge hotfix) — pageLivewire() restores the page-component targeting + the category-aware caught-up text
The user's live 500s (cancelRequest / reject / approve /
intentSessionOptions "public method not found"): the bell islands render
BEFORE the page content, so the house's `Livewire.first().call(...)`
pattern (8 sites) resolved to the island, not the page component. The
hotfix adds `pageLivewire()` (the first component that is NOT
nav-notifications) and repoints all 8 call sites (submitBlocks,
cancelRequest(s), intentSessionOptions, approve, reject, cancelClassUndo,
cancelClass). Verified live: `Livewire.all()` = [NavNotifications,
NavNotifications, request-approval] and pageLivewire() = request-approval.
Also: the caught-up swap text is now category-aware
("No unread in this category — N unread in other tabs" when the bell's
total > 0 while the viewed tab is clear) — the frozen F-2 law kept (the
pill stays the whole-mailbox total). The submit-receipt note: the rows
WERE created (ids 3/4 — both PLs) on the user's real submit; the
arrangement page has no bell by the frozen BUG-04 design.

## 2026-10-29 (the SDD-verify re-verify round) — the agreed fixes, one commit per concern

Re-verified the verify pass's 10 items independently (per the main session's
prompt): 7 AGREED and fixed, 2 DISAGREE, 1 CONFIRMED baseline. Evidence +
per-item verdicts live in the PI chat report. Commits (on `fedora-backend`, not
pushed):

- `968a867` Item 1 --- the islands' poll is now `wire:poll.10s.visible` (the
  artifacts' `->whenVisible()` law via the REAL Livewire 4.3.3 API: the
  `.visible` modifier, `pauseWhile` off-viewport). The bell never pauses (the
  `.top-bar` bell is on-viewport at every breakpoint); the panel island polls
  only while OPEN --- while closed it idles (halving the per-tab poll load);
  the first open shows page-load data, fresh within ≤10 s.
- `2e5a071` Item 3 --- the three missing feature receipts: the REJECTED
  emitter, the mark-read endpoint (auth scope + idempotency via a frozen
  clock + the guest bounce), and the ONE-`whereIn` per render (a DB::listen
  capture). The render query also dropped the dead `->with('request')`
  eager load (it was a SECOND whereIn + unused data) — the D4 N+1 law now
  literally holds.
- `34f2570` Item 6 --- `partials/notif-row.blade.php` created (the named
  T2/D6 partial): ONE builder for the active/inactive branch pair; the live
  :8000 before/after DOM diff is element-identical (residuals: the planned
  `.visible` attr, per-request snapshot ids, a minute-rollover in a time tip).
- `5c8a40d` Item 4 --- `POST /notifications/read-all` + `readAll()` DELETED
  (the user's Option A ruling, 2026-10-29; the island `wire:click="markAll"`
  won over the D2/D3 contradiction). No reference anywhere.
- `fb16ad2` Item 2 --- the durable Playwright receipts: `:8000` read-only
  (F-2 badge/pill total, the caught-up swap, the AD-15 open-panel-poll
  survival) + the testing-DB writable ones (the poll-cycle badge growth with
  a no-reload sentinel, the durable-POST badge shrink, the mark-all zero,
  the D4 disabled-row tooltip — HARD-GUARDED to the disposable DB).
- `75d920a` Item 9 --- the stale localStorage header in `ui-common.js`
  rewritten (comment-only).
- `3959b34` Item 5 --- the T6 demo seed INSERTs DRAFTED in
  `docs/demo-db-data-changes.md` (USER-RUN, never agent-run; NOT executed).

DISAGREE (no change, per protocol): Item 8 (the emitter's event-named API
vs the design sketch's `send()/notifyPls()` — the sketch was illustrative;
renaming working, tested, call-site-wired methods is churn with zero
behavioral gain). Item 10: the full suite CONFIRMED 276/276 (the 266
baseline + the 7 bridge tests + the 3 new ones).

⚠ NEW finding out of the 10-item fence (listed, NOT fixed): the island's
`resolveRoleView()` matches camelCase keys (`requestApproval`) while the
mounts pass the nav's kebab keys (`request-approval`) — the AD-2 per-page
tab map NEVER engages and every panel defaults to the lecturer tab. Refer to
the PI report for the evidence; the fix (3 lines in `NavNotifications.php`)
awaits the user's verdict.

## 2026-10-29 (lane `notif-ad2-pagekey-tab`) — the AD-2 tab map is LIVE

The re-verify round's out-of-scope finding, now the user-approved fix: the
panel always mounted the LEcas vacuum lecturer tab because
`ui-nav-bar.blade.php:127` fed the island the nav's KEBAB `activeNav`
(`request-approval`) while `resolveRoleView()` (correctly, per the retired
`NOTIF_ROLE_BY_PAGE`) matches the layout's CAMEL `$pageKey`
(`requestApproval`). Kebab keys structurally can't drive AD-2 — `my-timetable`
and `replacement-history` each serve a student AND a staff page.

- THE FIX (one line): the include now passes `$pageKey ?? $activeNav ?? ''` —
  the layout's camel per-page key (all 9 components + all legacy templates
  already publish it; `<body data-page>` is the same contract). The
  component, the frozen classes/copy and the F-2 badge law untouched.
- VISIBLE CHANGE (intended): `/request-approval-ui` → the pl tab (5425's real
  unread row 3 renders in the list — live innerHTML receipt: roleView "pl" +
  the `a[href="/request-approval-ui"]` anchor); the two student pages → the
  student tab; everything else unchanged (lecturer default).
- Receipts: the camel-key mapping test (suite 277/277; lint/phpstan green);
  `notifications-island.spec.ts` 7/7 on :8000 with the AD-2 leg TIGHTENED to
  pin the fixed behavior; `notifications-island-testing-db.spec.ts` 5/5 on
  the reseeded testing DB (regression).
- Both in-code "UNDER USER VERDICT" stale notes closed (comment-only).

## 2026-10-29 (lane `pagelivewire-wire-call-shape`) — pageLivewire() actually works now

The investigation_root_cause from the post-apply bug round (Bugs 1–4/7/8 "no
visible response", zero server logs): the committed pageLivewire() (6be2235)
returned the RAW Component object — in Livewire 4.3.3 `all()` yields raw
components WITHOUT .call (the call API lives on `.$wire`; only the legacy
`first()`/`find()` helpers returned `$wire`) and the kebab name-compare
missed the live-registered form (`NavNotifications`). Every page action died
client-side as `TypeError: pageLivewire(...).call is not a function` — no
wire POST, no 500, nothing in laravel.log.

- FIX (one function, lives with the 8 call sites untouched):
  `ui-common.js` — spelling-agnostic island exclusion (normalized
  `navnotifications` / `nav-notifications`) + `return page.$wire ? page.$wire
  : page` (the SAME object shape the pre-hotfix house law returned). A page
  with no page component returns null (honest, and the stub pages' failure
  class is unchanged).
- Receipts: the resolver's .call is a function on every page (:8000 probe);
  the venue read-only intentSessionOptions through the REAL resolver → wire
  200 + the real payload; :8030 UI chains — cancelRequest completed
  (cancelled 0→1, audit +1), approve/reject confirm clicks emitted 200s and
  surfaced the actions' own OCC verdicts; cancelClass fan-out earlier proven
  (+18 notification rows, audit, exception via the fixed shape). Islands
  7/7; the full suite 277/277; node --check pass.
- The "excluded component name" record: the live first component registers
  as 'NavNotifications' (Studly) — the normalization excludes it either way
  (fold 1 of the review round settled empirically).

## 2026-10-29 (lane `notif-unified-feed`) — ONE notification feed on every page

The user's new ruling (supersedes D2's AD-2 page-category tabs AND absorbs
`notif-ad2-pagekey-tab`'s mapping fix as history): all staff pages show the
SAME recipient-wide feed — the same notifications, unread count and read
state on every page they can open.

- CHANGE (behaviour): `NavNotifications::render()` no longer filters by
  `role_view` — the panel renders the recipient's whole mailbox
  (`orderByDesc created_at`, the SAME order everywhere); `$unreadInView` =
  the full-mailbox unread (the badge/pill/list states stay one number, F-2).
  `$roleView`/`resolveRoleView()` removed; `$pageKey` still accepted at
  mount (compat) but no longer selects anything.
- COPY: the caught-up view is now always the single frozen line
  "You're all caught up" — the per-category "No unread in this category — N
  unread in other tabs" wording retired WITH the tabs (its branch is
  unreachable under one feed). The frozen classes/ids untouched.
- Receipts: the bridge test's mapping receipt replaced by the unified-feed
  receipt (same rows on every pageKey + recipient isolation); :8030 matrix —
  the 6 PL pages feed-equal (badge 4, ids+order identical, the inactive row
  everywhere), mark-read sync via the durable POST (4→3 visible read across
  pages), poll-driven appearance (3→4 within a cycle, no-reload sentinel),
  the 5 non-PL pages feed-equal for 5770 with isolation (only their rows),
  the approval page 403 for non-PL; suite 277/277; lint/phpstan green;
  islands 7/7 on :8000.
- The demo receipt `notifications` id 2 removal stays a separate user-run
  DB step (docs/demo-db-data-changes.md carries its rollback).

## 2026-10-30 · The inactive (lifecycle) row gets its own look (user styling directive)
`.notif-row--inactive` had NO styling at all — a cancelled-request row was
indistinguishable from a live one (same ink, same pointer cursor, same hover).
Now the AMBER warning family marks it (deactivated ≠ error — the rejected tile
already owns red):
- `background: var(--color-warning-container)` + `box-shadow: inset 3px 0 0
  var(--color-warning)` — the theme tokens carry both themes (dark wash 0.50 /
  light 0.25), the ink stays readable on the wash.
- `cursor: not-allowed` + a no-shift `:hover` — the row is a span, not a link;
  the frozen tooltip still explains WHY it's inactive.
- The title goes italic + weight-400 (the --read demotion's weight, + the
  italic "OFF" marker), colour stays full `--color-on-surface`.
Layout, tile colours, tooltip, aria-label and ALL notification logic untouched —
pure CSS (theme.css), no restart needed. Live receipt: request #7 is cancelled
in the demo DB → its two `submitted` rows (ids 5 & 6) render inactive.

## 2026-10-30 (lane `notif-abstime-precision`) — the abs-time tooltip no longer drifts

The user's live observation: the same notification showed "3:50 AM" one poll
cycle and "3:51 AM" the next (both dual-role class-cancel copies were written
at the identical DB second — 03:50:19 — but the island's abs-tip helper
RECONSTRUCTED the time as now() − truncated minutes, so the render second
determined the displayed minute).

- FIX (F2, user-approved): the island's `$absTime` formats the STORED
  `$n->created_at` Carbon directly (`j M, g:i A` — the same string, a stable
  source); the notif-row partial's `data-tip` reads it from the row's own
  timestamp. `$relTime` stays clock-driven (it SHOULD advance); the drift
  artifact is gone.
- Receipt: the same rows' tips captured ≥10 s apart are byte-equal; the twin
  rows show the identical "3:50 AM"; the suite 277/277; islands 7/7.
- Also recorded this day: the user DELETED the second-and-last demo receipt
  (notifications id 1, per docs/demo-db-data-changes.md) and REJECTED the
  venue #tooltipBookBtn affordance lane (out of the backlog).

## 2026-10-30 (lane `notif-actor-pl-dedupe`) — F1: the actor-PL twin deduped

The class-cancel fan-out wrote owner-PL staff TWO rows for their own cancel
(the actor record + the dept-PL copy — 5425's ids 8+31, same second);
the unified one-feed panel surfaced them side by side. The user approved F1
(and the one-row demo cleanup as an explicit agent-run DB step).

- FIX: `NotificationEmitter::classCancelled` step 3 skips the actor
  (`(int) $pl->id === (int) $actor->id → continue`) — the owner keeps their
  actor record row (/cohort-timetable); the OTHER dept PL's row unchanged.
- Receipt: the bridge test's dedupe assertion (no pl-role row for the owner
  on the event; the other PL keeps theirs); suite 277/277; pint/phpstan
  green; islands 7/7 (--grep-invert TC70).
- DEMO-DB cleanup (user-approved agent-run): id 31 deleted (belt-braced
  one-row clause; logged in docs/demo-db-data-changes.md) — 5425's mailbox
  now holds ONE 'Scheduled class cancelled' row for the W6 event (id 8).

## 2026-10-30 (lane `notif-click-highlight`) — clicking a notification anchors + flashes its record

The user's design (2026-10-30): an ACTIVE notification row click lands on the
linked page and FLASHES the related row/cell, located by the notification's
own record id — read or unread, both; inactive rows keep-as-it (no anchor, no
trigger).

- HANDOFF: the row partial stamps `data-notif-target` (r = request rows on
  approval/history; s = the cancel rows on /my-timetable-ui — the link law
  changed per the ruling: the owner-actor AND the dept-PL copies now link
  the OWNER'S OWN TIMETABLE PAGE; the students' rows untouched) +
  `data-notif-week` (kind-s only). The capture listener appends
  `?notif-hl=<target>[&notif-w=<week>]` beside the durable POST.
- ARRIVAL: `applyNotifHighlight()` (ui-common, wired after initNotifPanel) —
  the URL params strip FIRST (unconditional), the finder (14 × 250 ms, load
  unblocked), `flashRowPulse` after the scroll settles; the builder stamps
  `data-sid` (event.sessionId ?? event.id); the new theme arm
  `.event-block.bookable-flash` (the same bookable-pulse keyframes — the
  exact earliestBtn flash); the `?week=` pin law GAINED the narrow
  notif-hl-only exception (the user's ruling A).
- The persisted legacy rows (the old link strings) simply keep plain
  navigation (no anchor attributes — the lifecycle law, no migration).
- Receipts: the mutation-observer FLASH TR data-id=5 on the approval queue;
  the s-arm end-to-end (the click → the week switch → the cell flash); the
  legacy/inactive keep-as-it receipts; suite 277/277; islands 7/7;
  pint/phpstan green.
- The summary-card + #todayBtn flashes live in SEPARATE lane
  `notif-jump-flash-family` (pending the user's apply word).
