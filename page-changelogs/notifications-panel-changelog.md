## 2026-11-04 — the click flash follows the new pulse

Same day, same change: the shared `bookable-pulse` now paints **3 flashes at 0.5 s** with a **4 px / 63 %**
ring instead of one 1.15 s pulse at 3 px / 45 %. A notification click's highlight looks exactly like the
others because they all share the one animation.

## 2026-11-04 — wider panel, and a way back out of Manage

Two items from the user's earlier notification order (delivered late, on request).

**Width +40%.** `.notif-panel` goes from `min(360px, …)` to **`min(504px, …)`**. Measured 504 px at
1920 / 1440 / 900 with a 12 px gap to the screen edge, no internal overflow
(`scrollWidth === clientWidth` on the panel and its children); at 390 px the phone sheet is still
full-width with no page overflow.

**Back out of Manage.** The manage screen had no exit; the shelf's own Back arrow now appears there,
wired to the existing `exitManage()` — state only, so the ticks are dropped and nothing is written.
Measured: enter manage → 8 of 8 pre-ticked; click Back → 0 picks, the manage bar gone, the normal list
back with its 7 rows.

## 2026-11-04 — the click follows the record's status, not the stored link

User order (part 1): cancelled → My Replacements + flash the row; approved → My Replacements + flash
the row; completed → put the row away in place, no navigation.

**Where the row goes now.** Class news for the lecturer is written to `/my-replacements` (it was
`/my-timetable-ui`), and the row partial maps the **legacy** rows already stored with the old link, so
the existing notifications behave like new ones. Student rows, the approval queue and rejected rows are
untouched.

**How the row is identified.** An approved row carries `r<request_id>` — the replacement record itself,
never a week or a name. A cancelled class has no request yet, so it carries `o<class_session_id>` plus
`notif-w`, and the merged page matches it on session + week. Both end in the same pulse the notification
jumps have always used (`.bookable-flash`, 1.15 s, once).

**Completed puts the row away** through the existing hide path (`NavNotifications::hideOne` → the
manage-mode gate → `hidden_at`): nothing is deleted, and the Hidden shelf brings it back. It branches on
the record's **live** status, so a derived Completed counts.

**One bug found and fixed on the way.** `tryFind()` flashed the element it had found 500 ms earlier; the
merged page's skeleton teardown rebuilds the table inside that window, so the measured element was
disconnected while carrying the class — an invisible pulse. It now re-queries at flash time.

Measured (demo DB, read-only): `r26` → row 4014 flashed; `o67&notif-w=14` → row 6714 flashed; a row that
is not on the page → no flash, no error, params stripped. Panel rows: approved → `/my-replacements` +
`r26`; cancelled → `/my-replacements` + `o24`/`o23` + week 7; submitted → `/request-approval-ui`;
rejected → `/my-request-history-ui`. No page errors.

## 2026-11-04 — reading is not a decision: a read row stays alive, and "Active only" means undecided

Two rulings from the user's review of the notification rules, applied together because they are the
same sentence read twice.

**1. A read "new request" row stays usable.** Until today, reading a `submitted` (or `awaiting`
reminder) row made it *inactive*: it lost its anchor, its composed title with the request ID and its
schedule line, and its tooltip read *"…because you have already read this request."* The classifier
now stops at the decisions — `cancelled > approved > rejected` — and **`read` is no longer an
inactive reason at all**. A read row of a still-pending request is an ordinary active row: clickable,
composed, and it still jumps + flashes its record.

Nothing else moved, by construction:

| Law | Predicate | Effect of this change |
|---|---|---|
| Badge (D5) | `read_at === null && inactive_reason === null` | unchanged — the badge still drops on read |
| Hide gate | `read_at !== null \|\| inactive_reason !== null` | unchanged — read rows stay hideable |
| "Unread only" (default) | literal `read_at === null` | unchanged — a read row leaves the *view*, which is the filter working, not the row dying |
| "Active only" | `! isInactiveRow()` → `inactive_reason === null` | **now means "not yet decided"** — read-but-pending rows stay visible |

**2. "Active only" = the request has not been decided yet.** This needed no second code path: the
toggle already keys off the same classifier the row partial renders by, so retiring the `read` reason
made the switch mean exactly what its name says. Only the docblock says so explicitly now.

**Retired copy:** `This notification is inactive because you have already read this request.` — its
key is gone from the row partial's reason map, so no state can render it.

**Receipts.** `NotificationsBridgeTest` **24/24** (180 assertions): the old D15 read arm — which
asserted the read sentence and `notif-row--inactive` — is replaced by an arm asserting the opposite
(`data-id` + `data-notif-target` + the composed `New replacement request (ID #N)` and **no** read
sentence), plus an "Active only" arm proving the read-but-pending row survives the switch while the
decided row does not. Gates: pint passed · PHPStan 0 · **full suite 340 passed / 1875 assertions**.
Live `:8000`: 6/6 inbox rows carry `data-id`, and the retired sentence appears nowhere in the page.
Honest gap: the demo mailbox holds no read-and-still-pending row, so the "read row stays alive" case
has no live screenshot — proving it there would mean writing to the demo DB; the test arm is the
proof.

## 2026-11-04 — the row click marks read again, and flashes again: the `$variant` inheritance trap

**Report (user, live):** clicking a notification row navigates, but the row is never marked read —
the unread state comes straight back; the badge never drops.

**Root cause — one inherited variable.** The click contract has always been the anchor's
`data-id`: `ui-common.js` listens for `.notif-row[data-id]`, fires the durable POST
(`/notifications/read`, keepalive) and then appends `?notif-hl=` for the flash. The batch-hide lane
(`467357b`, 2026-11-02) added an optional `$variant` to `partials/notif-row` and gated the three
hooks on `$variant === null`. The panel is mounted `variant="panel"`, and Blade's `@include`
**shares the parent's scope** — so inside every inbox row `$variant` was `"panel"`, never `null`,
and the partial's own `$variant = $variant ?? null` could not help (a real string is not "missing").

Live DOM before the fix — six anchors, `data-id` null on every one:

```
<a class="notif-row" href="/request-approval-ui">New replacement request (ID #28)</a>   ← no hooks
```

Two behaviours died on 2026-11-02 and stayed dead for two days: **mark-read on click**, and
**click-to-highlight** (`data-notif-target` / `data-notif-week` ride the same guard, so nothing
anchored or flashed either). Mark-all kept working (a `wire:click`), which is why the badge could
still be cleared and the panel looked healthy.

**Why the tests missed it.** The one spec that clicks a row selects the attribute that had vanished —
`notifications-island-testing-db.spec.ts:170`, `#notifList a.notif-row[data-id]` — so it could not
pass; it runs only on the disposable `:8030` lane, never in the routine PHPUnit suite, and the
lane's own receipts exercised sizes, checkboxes and the shelf rather than a row click.

**Fix (two parts).**
1. The inbox `@include` now passes `'variant' => null` explicitly (`nav-notifications.blade.php:207`).
2. The component's public prop is renamed `$variant` → **`$island`** (mounts, blade branch and the
   ~30 test mounts follow). The row partial does not use that name, so a `@include` in this blade
   can never inherit it again — the trap class is gone, not just this instance.

**Receipts.** `NotificationsBridgeTest` **24/24** (new pin: an active inbox row carries
`data-id="<id>"` + `data-notif-target="r<id>"` + its href, and an inactive row carries none of them).
Live `:8000`, DOM after the fix — six anchors, e.g. `data-id="168"` + `data-notif-target="r28"`.
A stubbed-fetch click on row 168 (no DB write) fired exactly:

```
POST /notifications/read   body {"id":168}
href  /request-approval-ui  →  /request-approval-ui?notif-hl=r28
```

So the click now marks read **and** hands the landing page its anchor again. Still open by
decision, not by bug: a read `submitted` row is rendered inactive (D15) — the user's 2026-11-04
ruling reverses that, separately.

## 2026-11-04 — the panel stops printing UTC: one `LocalTime` helper owns the user's clock

**Root cause.** `config/app.php` stores and computes in **UTC** (correct — changing it would shift
`now()`, every stored row, the semester maths and the whole suite), but the panel formatted the
stored instant with a raw `->format(...)`. So Request #26's tooltip read `2 Nov 2026, 11:00 AM`
(UTC) while `/my-request-history-ui` — which already converted — read `7:00 PM` for the same
request. The app clock and the user's clock differ by the +08 KL offset.

**What changed (A + B1).**

| File | Change |
|---|---|
| `app/Support/LocalTime.php` | **new** — the one user-facing clock: `TZ = 'Asia/Kuala_Lumpur'`, `stamp(?CarbonInterface, string $format): ?string` (copies, never mutates). Same house style as `App\Support\ReasonLabel` |
| `app/Services/ReplacementRequestMapper.php` | `DISPLAY_TZ = LocalTime::TZ` (kept as the compatibility mirror), `stampDisplay()` and `requestedAtDisplay()` delegate → output **byte-identical**, so the PL queue, the API and the history page cannot detect it |
| `resources/views/livewire/nav-notifications.blade.php` | the `$absTime` (chip tooltip) and `$tipAbs` (`Requested at:` tip line) closures delegate to `LocalTime`; `$relTime` / `$relLong` / `$minutesAgo` untouched — relative labels compare UTC with UTC and were already right |
| `tests/Unit/LocalTimeTest.php` | **new** — 5 pins: the conversion, the short panel shape, `null` stays `null`, no caller mutation, and `DISPLAY_TZ === LocalTime::TZ` |

**Scope decisions (user, 2026-11-04).**

- **B2 rejected — `MyRequestHistory::requestRow()`'s `reviewedAt` stays raw UTC.** That file is
  retired by the `replacement-merge-one-table` lane (design §2.2 "retires — no forwarder"; **T16**
  deletes `app/Livewire/MyRequestHistory.php` + its blade, and the merged page has no *Reviewed At*
  row at all), so the one-line fix would be churn on a file already scheduled for deletion.
  Consequence, stated honestly: until T16 lands, that page's modal still prints the UTC instant —
  for request 24 it shows *Reviewed At 7:04 AM* while the true decision was **3:04 PM MYT**, next to
  a correct *Requested At 2:49 PM*. `/request-approval-ui` reads `reviewedAt` from `toRow()` and is
  **already MYT**, so nothing that survives the merge carries the bug.
- **C dropped — the `Requested at:` line keeps reading `created_at`.** Verified: there are **zero
  `awaiting` reminder rows in both demo DBs** and `notifications:remind-pending` is **not
  scheduled**, so sourcing the line from `request->submitted_at` (plus the `setRelation` batch it
  needs) would have changed nothing visible today. Re-open it as a one-line follow-up if that
  command is ever scheduled.

**Receipts** (live `:8000`, demo DB, **read-only** — no writes):

```
row "New replacement request (ID #26)"  data-tip
  before: Requested at: 2 Nov 2026, 11:00 AM (…)
  after : Requested at: 2 Nov 2026, 7:00 PM (Yesterday)
  chip  : "Yesterday"   chip data-tip: "2 Nov, 7:00 PM"
row (ID #27): Requested at: 3 Nov 2026, 11:10 AM (20 hours ago)   chip data-tip: "3 Nov, 11:10 AM"
390 px: chip data-tip "2 Nov, 7:00 PM", panel renders, 0 page errors
```

Screenshots: `/tmp/opencode/localtime-panel.png`, `/tmp/opencode/localtime-panel-390.png`.

**Gates:** `composer run lint:check` passed · PHPStan level 7 → **0 errors** · full suite
**325 passed / 1729 assertions** (was 320 / 1723 — the 5 new pins).

`SDD-waived w/ user approval` — display-only formatting, one helper + two closures, no schema, no
API shape, no behaviour change, and the A+B+C decision was taken in conversation on 2026-11-02.

## 2026-11-03 — notif-recipient-rulings: who gets a row, and the staff line

Five user rulings (R1–R5), applied exactly as decided in
`prompts/pi-notification-recipient-rulings.md` (SDD lane
`.sdd/changes/notif-recipient-rulings/`).

| # | Ruling | What changed |
|---|---|---|
| **R1** | a non-PL proposer gets a confirmation row | `NotificationEmitter::requestSubmitted()` now takes the proposer first and appends one `update` row — `Replacement request submitted` · `<CODE> (<TYPE>) · W<w> · awaiting PL approval` · `/my-request-history-ui`. **The PL fan-out is untouched**: a PL proposer keeps their single queue row and gets nothing extra |
| **R2** | the proposer is never emailed their own request | the submit action's mail loop skips the proposer **by id**; every other PL still gets the mail |
| **R3** | the owner's approval rows MERGE into one | new `replacementConfirmed()` writes ONE `approved` row — `Replacement class updated` · `<classFact> · approved by <decider> · your timetable is updated` · `/my-timetable-ui`, carrying `request_id` **and** `class_session_id`. The old separate `Replacement approved` row is gone; the cohort students' notice is unchanged. Written once, **before** the cohort loop |
| **R4** | a cancelled class notifies the owner + students only | `classCancelled()` loses the whole department-PL fan-out (step 3) — **this REVERSES the 2026-10-30 "THREE fan-outs" ruling**. The owner is the actor and keeps their record row; a PL who owns the class still gets theirs as the owner |
| **R5** | every row shows `Name (staff_id)` in every state | `NavNotifications` computes a per-row `staff_line` from two batched loads (the request batch extended with `approver.lecturer`, plus ONE `ClassSession` batch for class rows) — request rows name the proposer, self rows name the PL who acted (approve **or** reject: `approver_id` is stamped by both), class rows name the lecturer, and a self-only event omits the line. The shared `notif-row` partial renders it **when its text differs** from the composed subtitle, so the inbox, the `pick` mode and the hidden shelf all follow, and a null line renders nothing |

**Two implementation refinements the user added at review** (2026-11-03):

1. The partial compares **text** (`$staffLine !== $subtitle`), not "is a subtitle set" —
   today both behave identically, but a future change to the composed line then *adds* a
   line instead of silently dropping the staff line.
2. A test counts the subtitle spans on one row in **four states** — unread, read, decided,
   hidden — proving the line never doubles (counted on spans, because the row tooltip
   legitimately repeats the name).

**Receipts.** `NotificationsBridgeTest` + `EmailQueueTest` **29/29**; full suite
**301/301**; pint clean; PHPStan 0. Live on the disposable `:8030`: 5770's two self rows
each show `Surayaini Binti Basri (5425)`; 5425's (decided) queue row shows
`Lim Jia Zheng (5770)`; a cohort student's notice and cancellation rows both show
`Lim Jia Zheng (5770)`; and a real UI cancellation by 5770 wrote 42 rows (owner + 41
students, **zero PL rows**) with **no** staff line on the owner's own row. No migration;
the demo DBs were never written.

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
- The summary-card + #todayBtn flashes live in the SEPARATE lane
  `notif-jump-flash-family` — **applied 2026-10-30** (`4a77a1f`); its receipts
  and the record of what it changed are in
  `page-changelogs/my-request-history-changelog.md` (§ the same date, lane
  `notif-jump-flash-family`), not here. (This line said "pending the user's
  apply word" until 2026-11-04, which was stale.)
## [2026-11-01] Submitted rows compose at render time + the actionable-unread badge — SDD change `notif-new-request-ux` (T1–T9)

The bell's `submitted` rows (a PL's inbox of new requests) stop being one-line
stored copies: they compose SERVER-SIDE on every render — title with the request
ID, a `Name (staff_id)` subtitle line, a schedule desc, a 5-line multiline hover
tip, and the badge redefined to the ACTIONABLE-unread total. Legacy demo rows
get the new look for free (no backfill, no schema change). Frozen rulings D1–D15;
artifacts in `.sdd/changes/notif-new-request-ux/`.

### The ruling lines (user-dated)
- **D6/D7 — render-time composition, no backfill**: NO emitter/API/schema
  change; the composer runs per render in `NavNotifications` — legacy stored
  rows compose the same as fresh ones (legacy submitted row id 1 on :8000
  receipts it live).
- **D5 — the badge = the actionable-unread total** (F-2 law REFRESHED): blue
  bell badge = panel pill = `$userTotal` = the derived number
  `read_at === null && inactive_reason === null` over the WHOLE mailbox — batch-
  derived in PHP, ONE render; the standalone COUNT subquery is DELETED.
  Described-request reads stay neutral (the badge never drops for reading the
  REQUEST while the ROW keeps its anchor).
- **D15 — the inactive reason-first copy family**: decided request → the linked
  `submitted`/`awaiting` rows render inactive with the exact copy
  cancelled > approved > rejected > read —
  `This notification is inactive because the request was approved.` (etc.).
  No reason = active row (href/data-id/data-notif-target preserved).
- **D3/D4 amendments**: the legacy D4 "links stay alive" law is OVERTURNED for
  the submitted/awaiting family (a decided row is disabled-look, no anchor);
  non-submitted request-linked rows keep the notif-click-highlight
  both-clickable law.
- **D1** — the cancelled string is exactly
  "This notification is inactive because the request was cancelled by the
  requester." (the real-bridge D4 copy updated).
- **D2 side-effect (DELIBERATE)**: unread `awaiting` reminders of a LATER-decided
  request also inherit the decided-state copy via D15 — asserted in the bridge
  suite's fail-open matrix, recorded here so nobody "fixes" it back.

### Files Modified

| File | Change |
|---|---|
| `app/Services/ReplacementRequestMapper.php` | PROMOTED: `stringsFromSpan()` (span→date/time/week/day/venue strings), `spansForRequests()` (ONE batched join, requestId→span), `dayName()` + `DAY_NAMES` — 3rd request-display consumer rides ONE format source; `replacementStrings()` is now a thin wrapper (existing callers unchanged) |
| `app/Livewire/NavNotifications.php` | render-time composer `composeSubmitted()` (title-with-ID / subtitle / desc / aria / 5-line tip; fail-open: identity lines per-line omitted, schedule all-or-nothing, missing semester nulls the tip, raw 24h times + unspaced en dash, venue name null → code alone); the D15 inactive-reason classifier; the count subquery deleted, badge = batch-derived actionable unread |
| `resources/views/partials/notif-row.blade.php` + `resources/views/livewire/nav-notifications.blade.php` | `_display` rendering + the new `.notif-row-subtitle` span; `$rowTip` = the 5-line multiline tip (the Requested-at line injected after the Staff line via the `$tipAbs`/`$relLong` closures: just now / N minutes ago / 1 hour ago / N hours ago / Yesterday / N days ago); inactive rows lose href/data-id/data-notif-target and carry the D-family data-tip; badge/pill read the DERIVED total |
| `public/css/theme.css` | ONE new `.notif-row-subtitle` (12px, `--color-on-surface-variant`) — no new tokens |
| `tests/Feature/NotificationsBridgeTest.php` | 13/13: composition receipt (slot 32612 = W6 Fri 13:30–14:00 B010), the D15 fail-open matrix (missing proposer/module → line omitted; missing semester → whole-tip fallback; legacy status-null edge), the D15 read arm, the D2 awaiting side-effect, ONE-query law intact |
| `tests/notifications-island-testing-db.spec.ts` (:8030) | 6/6 ×2: the badge lifecycle, the disabled-with-D1 cancelled row, and the D15 decided-row receipt (approve →inactive span WITHOUT read_at; badge drops) — with id-keyed per-test fixture sweeps + a beforeAll residue sweep so the run is re-idempotent (run 2 = no reseed, pure swept state) |
| `tests/notifications-island.spec.ts` (:8000) | 8/8: composer receipts on the REAL legacy submitted row (id 1), ONE-FEED equality, open-panel-survives-poll, caught-up swap; the F-2 test name reworded to the actionable-unread wording (D5) |

### Gates + receipts
- pint passed; PHPStan errors: 0 (1G flag); full phpunit **279 passed** (the
  frozen prompt predicted 277 — 2 tests came from lanes committed after the
  freeze); `node --check ui-common.js` OK.
- T7 (:8030) 6/6 run 1 after `migrate:fresh --seed`, then run 2 with NO reseed
  → 6/6 (idempotency proof; between-run DB state = 0 probe rows).
- T8 (:8000) 8/8, read-only.
- Commit ladder: `6886db2` (oop T1) → `591449e` (feat T2–T4) → `0b7674f`
  (style T5) → `acefe72` (test T6) → `bcdaeb9` (test T7/T8) → this `docs:` commit
  (T9).

## 2026-11-01 (lane `notif-filter-bar-active-type`, SDD-waived by the user) — the filter bar grows the Active-only toggle + the type dropdown

The user asked for two controls in the notch panel's filter bar: an
"Active only" toggle reusing the "Unread only" switch design, and a
notification-type dropdown. SDD skipped ON THE USER'S EXPLICIT WAIVER
("skip sdd if possible") — logged here per the cb696ee precedent.

### What changed
- `app/Livewire/NavNotifications.php` — two new server-owned props
  (`activeOnly` bool default OFF, `typeFilter` string default '') + the
  composable view-filter chain (unread-only ⊕ active-only ⊕ type) + the
  private `isInactiveRow()` — the SAME activation law the row partial renders
  by (D15 reason, else the legacy D4 missing/cancelled edge). THE BADGE IS
  UNTOUCHED: the derived actionable-unread total (D5) is computed before and
  independently of the view filters — the badge/pill/mark-all/read-endpoint
  stay the frozen law.
- `resources/views/livewire/nav-notifications.blade.php` — the bar now holds
  TWO `.toggle-wrapper` switches ("Unread only", "Active only") + ONE
  `.filter-select` dropdown (All types / New requests / Awaiting decision /
  Approved / Rejected / Updates) — no new component classes, the globally
  promoted switch + the canonical select, `wire:model.live` on both so poll
  morphs preserve them.
- `public/css/theme.css` — the bar gains `flex-wrap` + `gap` (no new tokens),
  and ONE panel-context block `.notif-filters .filter-select` (12px family
  step, 44px touch target, `margin-left: auto`).
- `tests/Feature/NotificationsBridgeTest.php` — NEW receipt
  `test_panel_view_filters_active_only_and_type`: the type filter isolates the
  stored type; active-only drops the D15-decided submitted row while the
  update row stays; a filter combo never moves `viewData('unreadTotal')`
  (the badge law). Suite 14/14.
- `tests/notifications-island.spec.ts` — NEW read-only receipt (filter bar:
  Active-only toggle + the type dropdown, badge unmoved) + the composer
  receipt RE-ANCHORED: the earlier "REAL legacy row id 1" anchor retired by
  the user's demo-DB recovery swap (commits 67606dd + d32ebd7) — the receipt
  is now STRUCTURAL (whatever unread pending-submitted row the live mailbox
  holds, the title-with-ID / subtitle / desc / 5-line tip invariants are
  asserted against its own values). Island spec 9/9 green on :8000.

### Gates
- pint passed; PHPStan errors 0 (1G); `node --check ui-common.js` OK;
  bridge 14/14; island 9/9 (:8000, read-only); full phpunit report in the
  commit message (the suite takes ~6–7 min: every Feature class reseeds the
  disposable testing DB — by design, the demo DBs are never touched).

### Demo-DB note (the same-day handover ruling)
The :8000 record = the user-sanctioned October reconstruction (agent-rebuilt
on the :8001 twin, swapped 21:19 per option A; the pre-swap composer row
state lives in backups/class_replacement_fresh-pre-swap-2026-11-01.dump).
The receipts here are relative/structural so the LIVE mailbox may keep
getting decided without breaking them. SAME-EVENING AMENDMENT (follow-up
`test:` commit): the user ordered the FULL STORY CLEANOUT at 22:12–22:25 —
both demo DBs went pristine-blank (0 requests / 0 notifications / 0
exceptions; next rr id 24). The island spec's two row-dependent receipts
(ONE-FEED, composer structural) are now EMPTY-CASE-ADAPTIVE: on a blank
canvas they assert the caught-up arm + the controls; with rows back they
assert the equality/composition laws as before. Verified 9/9 ON THE BLANK
STATE.


## 2026-11-02 (lane `notifTypeFilter` re-skin, SDD-waived with the user's approval) — the type filter's brand-new compact skin (week-select DNA)

The user's verdict on the filter bar lane's dropdown: **too tall** — the 44px
WCAG touch-target `min-height` crowded the compact row. Brand-new design
ordered (do NOT reuse the current skin), visual reference =
`<select class="week-select">`. Visual re-skin ONLY — same id
(`#notifTypeFilter`), same 6 options/values (the wire:model filter keys), same
`wire:model.live="typeFilter"`, zero behavior change.

### What changed
- **`resources/views/livewire/nav-notifications.blade.php`** (one attribute):
  the element drops `filter-select` and stands alone on the previously dead
  hook `notif-type-select` — which becomes the NEW design's class.
- **`public/css/theme.css`**:
  - NEW `.notif-type-select` (next to `.notif-filters`, ~:386): a verbatim
    copy of `.week-select`'s visual DNA — surface bg, outline border,
    13px/600, `--radius-md`, custom SVG chevron, blue hover border + focus
    glow, `0 32px 0 12px` chevron-room padding, `min-width: 170px`,
    `margin-left: auto` (stays parked row-end), 150ms transitions — at a
    **fixed `height: 36px`** (the user's explicit compactness instruction for
    this one control: a dated waiver of the 44px touch-target convention,
    floor 32px, never above 36), plus `option` styling and the
    `.light .notif-type-select` light-theme chevron swap
    (color-scheme + the `%235A6978` stroke, mirroring `.light .week-select`).
    The ONLY hex copies are the two already-sanctioned `.week-select`
    exceptions (the SVG chevron strokes + the focus glow), each commented
    verbatim-source; no new hex introduced (tokens everywhere else).
  - DELETED the dead `.notif-filters .filter-select` context rule
    (12px/min-height: 44px) — nothing else in `.notif-filters` ever used
    `.filter-select` (grep-verified); keeping it would have been a live rule
    for a class no element carries.

### Hard constraints honoured
`.filter-select` base family, `.week-select`, the `.semester-bar select:not(.week-select)`
special-case, the toggle styles and every other page are untouched; no JS/PHP
change; the bell island is shared but the change is visually confined to
`.notif-filters`.

### Receipts
- **Read-only visual check (:8000, demo DB untouched — blank state is the
  honest canvas)**: staff 5770, the bell panel opened on `/my-timetable-ui`;
  computed height = **36px** exactly (dark + light), 13px/600, appearance:none
  with the SVG chevron, right-parked (`margin-left: auto`), the row's
  `overflow` false (no cropping) at the 360px panel, the select wrapping to
  its own row-right line beside the two toggles (the row's designed
  `flex-wrap` law). Screenshots: `/tmp/opencode/notif-filter-dark.png`,
  `-light.png` (the `-open.png` twins show the control in use — the native
  options list is an OS menu, not capturable in headless Chromium).
- **`tests/notifications-island.spec.ts` — 6/6 × 2 runs, UNMODIFIED**, against
  the DISPOSABLE testing lane (:8030) via a temp Playwright config
  (`playwright.testing-lane.tmp.config.ts`, repo-root, untracked, never
  committed): same test file, absolute-relative URLs resolved to :8030, one
  lane login (staff 5425) — the spec's `test.use` storageState file is a
  MEDIUM swapped for the run and restored byte-identical after. The coupled
  receipts (the dropdown's visibility + `selectOption` driving through the
  :175 filter-bar test, the toggles) all green on the new skin.
- Gates: `composer run lint:check` passed · PHPStan 0 errors (1G).
- Never touched: `class_replacement_fresh` / `class_replacement_demo` (no
  snapshot needed — zero writes), no `--env=testing` anywhere.

## 2026-11-02 (lane `notif-manage-hide`, the user's approved design) — batch hide + the find-back shelf

The panel accumulated every notification forever (rows are never deleted by
design), so "clear the clutter" had no answer. Now the user can put away the
rows they are DONE with — and always get them back.

### Data (no new migration in this lane)
`notifications.hidden_at` (nullable timestamp + `(user_id, hidden_at)` index)
already existed and showed **Ran on all three DBs** (verified by direct
`information_schema`/`pg_indexes` reads before touching anything; commit
`5af11a6`) — **not recreated**. Hide = stamp `hidden_at = now()`, unhide =
`NULL`. **No row is ever deleted, and no "cannot be undone" copy exists
anywhere.**

### The gate (server-side, the badge's exact complement)
Hideable ⇔ `read_at !== null || inactive_reason !== null` — literally the
negation of the badge predicate (`read_at === null && inactive_reason ===
null`), so **hideable ∩ badge-counted = ∅** and the `render()` badge block is
untouched by construction. `inactive_reason` is PHP-derived, never a column,
so every posted id is RE-VALIDATED in `hideSelected()` (a tampered, stale or
foreign id is dropped silently, never written) — proven by a tamper receipt.

### The states
- **Inbox**: `Notifications` + unread pill + **icon-only Mark all**
  (`#notifMarkAll` kept; id + `wire:click` load-bearing) + **Manage** (rendered
  only when ≥1 hideable row exists) + Close.
- **Manage mode**: the filter row is REPLACED by `N of M selected` ·
  `Select all` · `Clear`, under it the honest caption
  `Notifications that still need you can't be hidden.`; the list is ONLY the
  hideable rows, **pre-ticked on entry**, each row a `<label>` + `.bulk-checkbox`
  (the promoted pair reused, not cloned — and no `<button>` inside an `<a>`);
  footer `N selected` + neutral ghost `Hide selected` (`.btn-outline`: no red,
  no blue — hiding is reversible). Leaving manage mode writes nothing.
- **Hidden shelf** (footer quiet link `Hidden (N)`): title
  `Hidden notifications` + icon-only Back (`aria-label="Back to inbox"`),
  bar `N hidden` · `Unhide all`, dimmed non-anchor rows with a small `Unhide`,
  footer note `Hidden notifications stay in your mailbox — restore them
  anytime.`, empty shelf `Nothing hidden — everything is back in your inbox.`
- **Undo toast** (no confirm modal): `N notifications hidden · Undo restores
  them` (singular `1 notification hidden · Undo restores them`). It lives in
  **component state (`$undoIds`)** and is rendered BY the component, so the
  10 s `wire:poll` morph cannot wipe it; its 12 s timer calls `dismissUndo()`
  (12 s chosen deliberately so a poll cycle happens WHILE it is up — the
  poll-safety is observable, not just claimed).

### The find-back guarantee
`Hidden (N)` sits OUTSIDE the list/empty branches, so it also renders in the
**empty/caught-up state** — measured live: catching-up view + `Hidden (1)`
together. It disappears at N = 0.

### CSS (`theme.css`)
- `#notifMarkAll` **REWRITTEN**, not layered: the old 44px text-button sizing
  squeezed the 16px svg to ~10px and looked faded. It is now the 30px square
  icon button of the Close/Manage family (measured 30×30, svg 16px).
- New dated block: `.notif-iconbtn`, `.notif-manage-bar`, `.notif-bar-count`,
  `.notif-bar-btn`, `.notif-caption`, `.notif-row--pick` (4-track grid),
  `.notif-row--hidden` + `.nr-right` + `.notif-unhide`, `.notif-foot`
  (+`--manage`), `.notif-foot-link`, `.notif-foot-count`, `.notif-foot-note`,
  `.notif-undo` + `.notif-undo-btn`. Tokens only; `.bulk-checkbox` /
  `.row-selected` reused, never re-declared.
- `#notifTypeFilter` spans the whole filter row (`flex: 1 1 100%; width: 100%`)
  on its own line under the two toggles; its native `title` is REMOVED (one
  tooltip system only) and it gains `data-tip="Filter the list by notification
  type."`. Both toggle tips are unchanged verbatim.

### Tooltips
All six ride the SHARED `data-tip` + `.data-tip-tooltip` system (no new
mechanism); the two-line tips use `&#10;`. Verified live (dark + light):
pill, Mark all, Manage, Hidden (N), type select, close, and the unchanged
unread toggle copy.

### Receipts
- **PHPUnit** `NotificationsBridgeTest` **18/18** (14 pre-existing + 4 new:
  manage pre-ticks only hideable rows, hide is reversible and never deletes,
  hiding never moves the badge / hidden rows leave the inbox / shelf round
  trip, and the authorisation scope that refuses foreign ids). Full suite
  **291/291**.
- **Playwright write lane** `tests/notifications-hide-manage.spec.ts` (NEW,
  :8030 only — the receipt-target guard aborts a bare/demo run; id-keyed
  fixture sweeps per test): **5/5 × 2 runs**, 0 hidden + 0 residue after each.
  Covers the full loop (pre-tick count → untick → Hide → undo toast → poll
  survival at 10.5 s → Undo restore → re-hide → shelf → Unhide → Unhide all →
  empty shelf → Back with the link gone) and "leaving manage mode changes
  nothing".
- **Playwright read-only** `tests/notifications-island.spec.ts` **10/10 × 2
  (demo DB untouched: 5 rows, 0 hidden)**: the new layout receipt — type select
  width == the filters row's content width (326 = 326 at the 360px panel), 36px
  height, no native `title`, the head has no overflow, Mark all is 30×30 with a
  16px glyph, and Manage appears exactly when a hideable row exists (the count
  taken with the view filter OFF — the filter legitimately hides read rows).
- Read-only geometry sweep at **320 / 360 / 390 / 1280** and in **dark +
  light**: head overflow FALSE at every width; select width == row content
  width at every width; screenshots in `/tmp/opencode/`
  (`hide-inbox|manage|undo|shelf|shelf-empty|back-inbox-*`, `hide-empty-with-link.png`).
- Gates: pint passed · PHPStan 0 errors (1G) · `node --check` on the shared JS
  (untouched here) · no new dependencies · no other page affected.

### Lane note (deviation, recorded honestly)
The prompt's §6 suggested extending `tests/notifications-island.spec.ts` with
the write loop; that spec runs on the DEMO mailbox, which this lane treats as
read-only. The write loop therefore lives in its own guarded spec on the
disposable testing DB, and the island spec got the READ-ONLY layout half.
