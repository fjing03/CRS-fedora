# Explore notes — notif-new-request-ux (raw facts, file:line anchors)

Read-only exploration for the SDD proposal. Ask file:
`prompts/pi-prompt-UIUX Update New Replacement Request.md` (114 lines, 6 parts:
1 title-with-ID, 2 requester subtitle, 3 description=schedule+venue,
4 multiline hover tooltip, N+1 inactive-on-read/approved/rejected,
N+2 cancelled tooltip copy, N+3 inactive⇒READ category).

Status: IN PROGRESS (written incrementally).

---

## 1. CURRENT-STATE MAP

### 1.1 SDD lanes that already touch notifications (read their headers)

| Lane | sdd.yaml status | Commits |
|---|---|---|
| notifications-real-bridge | `applied` (was `frozen`; working-tree MODIFIED → `applied`) | f3b15d7, plus verify-fix batch 1acd500/968a867/2e5a071/34f2570/5c8a40d/fb16ad2/3959b34/75d920a/6be2235/647dd34 |
| notif-ad2-pagekey-tab | `applied` | 52b8399 |
| notif-unified-feed | `applied` | c5aa747 |
| notif-abstime-precision | `applied` | 5edb22c |
| notif-actor-pl-dedupe | `applied` | 32f6cd5 |
| notif-click-highlight | `applied` | a201b34 |
| notif-jump-flash-family | `applied` | 4a77a1f |
| notif-ghost-retract-strip | `active` (review-log says "Applied receipts — 2026-10-30"; 6e29d56 + acd35b7 + waiver 62dd18b) | 6e29d56, acd35b7, 62dd18b |
| notif-new-request-ux (this lane) | `active` (sdd.yaml only; proposal.md does NOT exist yet) | — |

Working tree (`git status --porcelain`, branch `fedora-backend`):
- MODIFIED: `.sdd/changes/notifications-real-bridge/review-log.md` (adds the
  2026-10-29 PI re-verify round tail), `.sdd/changes/notifications-real-bridge/sdd.yaml`
  (frozen → applied). **These two are the "pending tail" of notifications-real-bridge.**
- MODIFIED (other lanes, not notif): `app/Livewire/ReplacementArrangement.php`,
  `page-changelogs/replacement-arrangement-changelog.md`,
  `resources/views/livewire/my-request-history.blade.php` (status tooltips /
  filter chips — the notif JUMP TARGET page is dirty!),
  `resources/views/livewire/replacement-arrangement.blade.php`.
- UNTRACKED: `.sdd/changes/{notif-abstime-precision,notif-actor-pl-dedupe,
  notif-ad2-pagekey-tab,notif-click-highlight,notif-jump-flash-family,
  notif-unified-feed,notif-new-request-ux,pagelivewire-wire-call-shape,
  ra-result-count-showing-of,rearrangement-toolbar-tooltips,
  status-tooltips-filter-chips}/`, `tests/tmp-explore.spec.ts`,
  `prompts/pi-bug-arrangement-reentry-after-pending.md`,
  `prompts/pi-summary-cards-confirmed-pending.md`.

### 1.2 The notification surface (bell + panel + row partial)

- Bell mount: `resources/views/partials/ui-nav-bar.blade.php:52-56` →
  `@livewire(NavNotifications::class, ['variant' => 'bell'], key('notif-bell'))`.
- Panel shell mount: `resources/views/partials/ui-nav-bar.blade.php:127-132` →
  `@include('partials.ui-notifications-panel', ...)`.
- Panel shell (STATIC, holds `.open`): `resources/views/partials/ui-notifications-panel.blade.php:9-13`
  (overlay `#notifOverlay` :9, `#notifPanel` :11, island `variant=panel` :12).
- Livewire component: `app/Livewire/NavNotifications.php` (whole file, 122 lines).
  - `variant`/`pageKey`/`unreadOnly` props :29-43 (`pageKey` accepted but NO-OP since notif-unified-feed, :31-34).
  - `render()` :45-75 — `Notification::query()->where('user_id', ...)` :53,
    `$unreadTotal = whereNull('read_at')->count()` :55, rows `orderByDesc('created_at')` :62,
    unread-only filter :65-67.
  - `markAll()` :78-84.
  - `resolveRequests()` :96-121 — batch status lookup, sets `inactive_row`
    when `request_id !== null && (status null || status === 'cancelled')` :110-118.
- Island view: `resources/views/livewire/nav-notifications.blade.php` (90 lines)
  - glyph map (5 inline SVG path strings, NOT flux icons) :16-22
  - `$minutesAgo`/`$relTime` closures :23-32; `$absTime` :36-38 (notif-abstime-precision);
  - bell branch :41-48 (`#notifBadge` :47);
  - panel head/pill/mark-all/close :50-62; unread-only toggle :64-70;
  - `$showEmpty` :72; `#notifList` :73-77 with `@include('partials.notif-row')` :75;
  - empty state :79-88 ("You're all caught up" :87).
  - Root div `wire:poll.10s.visible` :14.
- Row partial: `resources/views/partials/notif-row.blade.php` (47 lines) — see 1.4.
- **There is NO full-page notifications page** — the bell dropdown panel is the only
  notification surface. (No route in `routes/web.php` for a notifications page;
  only `POST notifications/read` at `routes/web.php:105-110`.)
- JS shell: `public/js/ui-common.js:1934-2085` (open/close/toggle/init + the
  capture-phase click listener + `durableMarkNotifRead`).

### 1.3 Data model

- Model: `app/Models/Notification.php` (53 lines) — fields (docblock :9-23,
  fillable :26-29, casts :31-40): `id, user_id, role_view ('pl'|'lecturer'|'student'),
  type ('submitted'|'awaiting'|'approved'|'rejected'|'update'), title, body, link,
  request_id?, class_session_id?, week?, read_at?, created_at, updated_at`;
  relations `user()` :43-46, `request()` :49-52 (belongsTo ReplacementRequest).
- Table: `notifications` (created by the notifications-real-bridge lane;
  indexed (user_id, read_at) per proposal `.sdd/changes/notifications-real-bridge/proposal.md` §1).
- Emitter: `app/Support/Notifications/NotificationEmitter.php` (223 lines) —
  `requestSubmitted` :34-51, `requestDecided` :56-74, `approvalNotice` :81-102,
  `classCancelled` :110-152, `pendingReminder` :160-186, `write()` :191-204,
  `commit()` = `DB::afterCommit` :209-212, `classFact()` :214-222.
- Read path: `app/Http/Controllers/NotificationReadController.php` + route
  `routes/web.php:105-109` (`POST /notifications/read`; the read-all endpoint
  DELETED, `routes/web.php:110`).

### 1.4 The row markup (what parts 1-4 touch)

`resources/views/partials/notif-row.blade.php`:
- :12-16 tag/class: inactive → `<span class="notif-row notif-row--inactive">`;
  else `notif-row--read` if `read_at !== null` else plain `notif-row`.
- :17-19 `$rowTip` — inactive: `'This notification is inactive because the request was cancelled.'`;
  active: `$n->title.' — '.$n->body`.
- :20 `$rowLabel` (aria-label) = title — body (+ ' (inactive)').
- :21-38 `notif-click-highlight` target computation (`r<request_id>` for
  `/request-approval-ui` + `/my-request-history-ui`, `s<class_session_id>`+`notif-week`
  for `/my-timetable-ui`); inactive arm emits NO target.
- :40 the element (single long line): class, `data-id` (active only),
  `data-notif-target`, `data-notif-week`, `href` (active only), `data-tip`, `aria-label`.
- :41 tile `<span class="notif-row-icon notif-tile-{{type}}">` + inline SVG glyph.
- :42-45 `.notif-row-body` → `.notif-row-title` ($n->title) + `.notif-row-desc` ($n->body).
  **NOTE: only TWO text lines exist — there is no subtitle element today.**
- :46 `.notif-row-time` with `data-tip={{$absTime(created_at)}} data-tip-pos="left"`.

### 1.5 How a NEW replacement request becomes a notification (today)

1. `app/Actions/SubmitReplacementRequest.php:194-230` — inside `DB::afterCommit`,
   collects ALL PLs (`whereHas lecturer is_pl`) :195-197, queues the PL mail
   :208-218, then `app(NotificationEmitter::class)->requestSubmitted($pls,
   $moduleCode, $sessionType, $proposerName, $requestId, $anchor->week_number)` :222-229.
2. `NotificationEmitter::requestSubmitted` :34-51 writes ONE row per PL with:
   - `role_view='pl'`, `type='submitted'`
   - `title='New replacement request'` :42  ← **part 1 target**
   - `body = $moduleCode.' ('.$moduleType.') · W'.$week.' · submitted by '.$proposerName` :43
     ← **parts 2+3 target (body currently carries module + week + requester name)**
   - `link='/request-approval-ui'`, `request_id`, `class_session_id=null`, `week`.
3. Recipients: **PLs only** (`is_pl` users of ANY dept — no dept filter on submit).
4. Real-time: **Livewire `wire:poll.10s.visible`** (island root,
   `nav-notifications.blade.php:14`). No websockets/broadcast (notifications-real-bridge
   ruling #2: option A).
5. On click: capture-phase listener `public/js/ui-common.js:2046-2067` — marks read
   via `durableMarkNotifRead(row data-id)` :2052 (POST `/notifications/read`, :1975-1990),
   then appends `notif-hl=<target>` (+`&notif-w=` for s-rows) to the href :2053-2063,
   browser navigates; arrival `applyNotifHighlight()` `ui-common.js:305-327` strips the
   params + `tryFind()` :333-342 scrolls + `flashRowPulse()` :219-224 (bookable-flash, 1.3 s).


---

## 2. PER-ASK-PART FINDINGS (current behaviour + code sites)

### Part 1 — Title `New replacement request` → `New replacement request (ID #76)`
- CURRENT title text: written ONCE at emit time —
  `app/Support/Notifications/NotificationEmitter.php:42`
  (`'New replacement request'`), called from `requestSubmitted()` :34-51,
  caller `app/Actions/SubmitReplacementRequest.php:222-229`.
- Rendered at `resources/views/partials/notif-row.blade.php:43`
  (`.notif-row-title` ← `$n->title`); ALSO feeds `$rowTip` :19 and
  `$rowLabel` :20 (aria-label) — the ask's aria-label example shows the title there too.
- `request_id` is ALREADY stored (migration :21, model :17) → the ID is
  available at emit time (emitter already receives `$requestId`, param :34).
- NOT covered by any landed lane. Affects only NEW rows (stored string) —
  demo-DB legacy rows keep the old title unless backfilled (demo DB writes
  need explicit user approval — house law).
- Test touchpoints if title changes: Playwright ONE-FEED test compares
  `href|title` ACROSS pages for equality only
  (`tests/notifications-island.spec.ts:61-72`) — format-agnostic; no test
  hardcodes 'New replacement request' today (grep found none).

### Part 2 — Requester subtitle `Poong Foo Jing (5425)` (staff name + staff ID)
- CURRENT: there is NO subtitle element. `.notif-row-body` holds exactly two
  spans: title :43 + desc :44 (`notif-row.blade.php:42-45`);
  `theme.css:473-478` `.notif-row-body` = flex column gap 2px; only
  `.notif-row-title` :479 and `.notif-row-desc` :487 classes exist.
- The requester NAME currently lives INSIDE the body string
  (`NotificationEmitter.php:43` `'… · submitted by '.$proposerName`) — part 3
  moves it out; part 2 re-homes it as a subtitle.
- Data needed: full name = `users.name` (`$proposer->name`, passed as
  `$proposerName` from `SubmitReplacementRequest.php:189`); staff ID =
  `lecturers.staff_id` (`app/Models/Lecturer.php:14` fillable;
  `app/Models/User.php:67` `lecturer(): HasOne`, staff_id surfaced at
  `User.php:45`).
- Landed change covering it: NONE.
- Implementation fork (open question): store the subtitle in the `title`/
  `body` columns (no schema change) vs render from the `request` relation at
  render time (needs batch eager-load — see the ONE-QUERY law in §4).

### Part 3 — Description = proposed schedule + venue `Week 7 · Friday, 09:00–11:00 · B002`
- CURRENT body (store-time): `NotificationEmitter.php:43`
  `$moduleCode.' ('.$moduleType.') · W'.$week.' · submitted by '.$proposerName`.
  Same shape in `pendingReminder` :178 (NOT part of this ask — its title is
  'Approval still pending', :177).
- The emitter does NOT receive day/time/venue today. Available at the caller:
  `SubmitReplacementRequest.php:194-229` — `$anchor` TimeSlot (week_number,
  day_of_week, start_time, end_time, venue_id), `$span` (slot count),
  `$replacementTime` string built at :203-204, `$venue` room_code :205-206,
  `$sessionType` :192. Venue NAME = `venues.room_name`
  (`app/Models/Venue.php` docblock).
- Day/date derivation helpers already shared:
  `app/Services/ReplacementRequestMapper.php` `DAY_NAMES` :58,
  `classRow()` :125-148 (classDate via semesterStart+weeks+day, classDay,
  timeStart/timeEnd, venue room_code), `replacementStrings()` :218-233
  (date/time/venue), `DISPLAY_TZ = 'Asia/Kuala_Lumpur'` :42 + `stampDisplay` :45-50.
  NOTE: mapper is keyed on `ReplacementRequest` (needs `$semesterStart`).
- Landed change covering it: NONE.
- Test touchpoint: `tests/Feature/NotificationsBridgeTest.php:82`
  asserts `assertStringContainsString('submitted by Surayaini Binti Basri', $row->body)`
  — **this assertion BREAKS if the requester name leaves the body** (or must be
  updated by the lane).

### Part 4 — Multiline hover tooltip for `.notif-row` / `.notif-row--read`
- CURRENT tip: `notif-row.blade.php:17-20` — active rows
  `$n->title.' — '.$n->body` (single line), inactive rows the frozen
  cancellation sentence; set on the ROW element at :40 (`data-tip=`, no
  `data-tip-pos` → tooltip renders ABOVE the element,
  `ui-common.js:3057-3064`); the time cell carries its own
  `data-tip={{abs time}} data-tip-pos="left"` :46.
- Tooltip engine (REUSE — already multi-line capable):
  `public/js/ui-common.js:3009-3081` `initDataTipTooltips()` —
  `textContent` assignment :3033 (safe for any text), **`\n` in the attribute
  → `.data-tip-tooltip--multi` class :3038-3042**; positioning/clamping
  :3043-3069; `data-tip-pos="left"` arm :3051-3056.
  CSS: `public/css/theme.css:3797-3803` `.data-tip-tooltip--multi { white-space: pre-line; }`,
  base `.data-tip-tooltip` :3805-3820 (position:fixed, inverse-surface token).
- Relative time today: `$relTime` closure `nav-notifications.blade.php:26-32`
  (`just now / Nh ago / Yesterday / Nd ago`), minutes via `$minutesAgo` :23-25;
  abs time `$absTime` :36-38 = `j M, g:i A` (NO year — the ask's example
  shows `29 Oct 2026, 10:44 AM`, WITH year → open question).
- Landed change partially covering: **notif-abstime-precision (applied)**
  fixed the abs-time SOURCE (stored `created_at`, no drift); the ask's
  `Requested at: … (N minutes ago)` line needs BOTH the stored timestamp and
  a dynamic relative value — infra exists, composition does not.
- Ask says "for active notification rows" → inactive rows keep their single-line
  frozen tip (part N+2 owns that one).

### N+1 — Inactive when READ / APPROVED / REJECTED
- CURRENT inactive rule (render-time, batch): `app/Livewire/NavNotifications.php:110-118`
  `inactive_row = request_id !== null && (status null || status === 'cancelled')`
  — **ONLY cancelled/missing. APPROVED and REJECTED stay ACTIVE links today.**
- READ rows today are a DIFFERENT state: `notif-row--read` (active `<a>`, muted title) —
  `notif-row.blade.php:16`, `theme.css:430-433`. Inactive WINS over read in the
  class expression :14-16 (one state per row).
- NO landed lane covers read/approved/rejected → inactive.
- **DIRECT COLLISION**: `tests/Feature/NotificationsBridgeTest.php:191-201`
  `test_the_lifecycle_links_stay_alive_for_decided_statuses` asserts an
  APPROVED request's row KEEPS `href="/request-approval-ui"` — the proposal
  must change/retire that test (it encodes the D4 lifecycle law "rows stay
  alive for decided statuses").
- Second collision: notif-click-highlight (applied) emits `data-notif-target`
  ONLY for ACTIVE rows (`notif-row.blade.php:29-38`) and marks read only via
  `data-id` (`ui-common.js:2049-2052`) — inactive rows are unclickable
  `<span>`s (no href, no data-id). Making read rows inactive kills the
  "click a READ row → still navigates + flashes" law of notif-click-highlight
  (its proposal: "read or unread, both").
- Third touchpoint: default PL-queue filter = 'Pending'
  (`resources/views/livewire/request-approval.blade.php:484`) — approved
  requests are already filtered out of the landing table → the arrival flash
  gracefully skips (`ui-common.js:333-342`).
- The exact inactive CLASS/behaviour to reuse: `.notif-row--inactive`
  `theme.css:444-454` (warning-container wash, inset warning edge,
  not-allowed cursor, italic title) — landed via commit 911681c.

### N+2 — Cancelled-request tooltip copy
- CURRENT copy (single, used for EVERY inactive row):
  `notif-row.blade.php:17-18`
  `'This notification is inactive because the request was cancelled.'`
- The ask contains TWO different strings: the HTML example says
  `This Notification is inactive -- the Request was CANCELLED by the Requester.`
  (:84) while the requirement says
  `This notification is inactive because the request was cancelled by the requester.` (:90).
  → open question (proposal must pin ONE).
- Cancelled-by-requester provenance: the ONLY path setting
  `replacement_requests.status='cancelled'` is
  `app/Actions/CancelPendingRequest.php:90-94` (guarded :36-40 to the PROPOSER
  of a PENDING request — FR 2.10/2.11; audit `cancelled_by: 'proposer'` :78/:103).
  No other app path sets it (grep: NavNotifications :117 read + unrelated
  event-status literals `ReplacementArrangement.php:327`,
  `ResolvesTimetableTimeline.php:259`). So today `status='cancelled'`
  ⇒ cancelled by the requester, by construction.
- No notification is emitted on cancel (CancelPendingRequest has NO emitter
  call) — the inactive row IS the notification of a cancelled request.
- **Test touchpoints — BOTH hardcode the current sentence:**
  `tests/Feature/NotificationsBridgeTest.php:147` (`assertSee(...)`),
  `tests/notifications-island-testing-db.spec.ts:148`
  (`toHaveAttribute('data-tip', 'This notification is inactive because the request was cancelled.')`).
  Copy change ⇒ both must be updated (plus aria-label if it changes).

### N+3 — Inactive rows must count as READ (never under UNREAD)
- CURRENT read logic is `read_at`-only everywhere:
  - unread filter: `NavNotifications.php:65-67` (`read_at === null`),
  - badge/pill total: `NavNotifications.php:55` + blade `:39/:47/:53`,
  - empty-state: blade `:72` `$showEmpty = ($unreadOnly && $unreadInView === 0) || $rows->isEmpty()`,
  - `markAll()` :78-84 sets read_at for the whole mailbox,
  - read class: `notif-row.blade.php:16`.
  **An inactive row with `read_at = null` is counted UNREAD today** (it shows
  in the default unread-only view and inflates the badge) — that is exactly
  what N+3 asks to stop.
- NO landed lane covers it.
- Consequences the proposal must decide: excluding inactive rows from
  `$unreadTotal` changes the badge number (honest-zero law: the badge must
  equal the real "actionable unread" count — `nav-notifications.blade.php:9-10`
  the ONE unread total drives badge+pill+mark-all), and `markAll()` (:78-84)
  does NOT touch inactive rows (they keep read_at null while being treated as
  READ → is that acceptable, or should they be physically marked read?).
  Also: inactive rows are never click-markable (no data-id,
  `notif-row.blade.php:40`, listener `ui-common.js:2049-2050`).

---

## 3. REUSE INVENTORY (shared UI infrastructure)

### theme.css (public/css/theme.css) — notification block :259-526
- `.notif-btn` :259-276 (bell), `.notif-badge` :277-292 (badge, error token),
  `.notif-overlay` :295-302, `.notif-panel` :304-318 (+mobile :513-526),
  `.notif-head` :320-338, `.notif-unread-pill` :339-352, `.notif-close` :354-372,
  `.notif-filters` :377-386, `#notifMarkAll` :388-404, `.notif-list` :406-413 (max-height 380),
  `.notif-row` :415-427, `.notif-row--read .notif-row-title` :430-433,
  `.notif-row--inactive` :444-454, `.notif-row-icon` :456-465,
  tiles `.notif-tile-{submitted,awaiting,approved,rejected,update}` :467-471,
  `.notif-row-body` :473-478, `.notif-row-title` :479-486, `.notif-row-desc` :487-493,
  `.notif-row-time` :494-498, `.notif-empty` :500-510.
- **NO `.notif-row-subtitle` / third-line class exists** — a subtitle needs a
  new class in this block (or reuse `.notif-row-desc` styling).
- Tooltip: `.data-tip-tooltip` :3805-3820, `.data-tip-tooltip--multi` :3797-3803.
- Flash: `@keyframes bookable-pulse` :2306, `tr.bookable-flash > td` :2310,
  `.event-block.bookable-flash` :2313, `td.bookable-flash` :2317,
  `.cell-content.bookable-flash` :2318 (notif-jump-flash-family additions).
- Tokens used by the notif UI: `--color-primary-container`, `--color-on-primary-container`,
  `--color-error`/`--color-on-error` (badge), `--color-warning-container`/`--color-warning`
  (inactive), `--color-surface-variant`, `--color-on-surface-variant`,
  `--color-outline`, `--color-scrim`, `--radius-sm/lg/pill/xs`, `--shadow-lg/sm`,
  `--transition`, `--top-bar-height`. Canonical color law: §10.0 legend map (same name+color).

### ui-common.js helpers the notif code uses (public/js/ui-common.js)
- `flashRowPulse(el)` :216-224 (the ONE pulse, bookable-flash, ~1.3 s).
- `applyNotifHighlight()` :295-327 + `tryFind()` :333-342 (arrival anchor/flash), wired at DOMContentLoaded :290-293.
- Panel shell: `durableMarkNotifRead()` :1974-1990 (POST /notifications/read, keepalive),
  `openNotifPanel/closeNotifPanel/toggleNotifPanel` :1992-2023,
  `initNotifPanel()` :2025-2075 (capture click :2046-2067, Esc :2070-2074,
  overlay click :2036, drawer-closes-panel :1882-1885).
- `initDataTipTooltips()` :3009-3081 (the `data-tip` engine, multiline arm).
- `pageLivewire()` resolver excluding the islands :1954-1971.
- Stale localStorage/mock notification code RETIRED — comment :1934-1946
  (`window.MockData.notifications` no longer feeds the panel;
  `public/js/mock-data.js:966-974` §2.13 seed + `:1715` map still EXIST in the
  mock file but nothing in the panel path reads them).

### Icons
- The notif UI uses INLINE `<svg>` strings — glyph map
  `resources/views/livewire/nav-notifications.blade.php:16-22` (5 paths),
  bell :43-45, close :56-58, empty-check :80-83, per-row glyph
  `notif-row.blade.php:41`.
- `resources/views/flux/icon/` contains only 4 partials today:
  `book-open-text`, `chevrons-up-down`, `folder-git-2`, `layout-grid` —
  NONE used by the notification UI (no flux icon in the panel).
- Ask rule §5 (icon buttons): the notif UI already complies (icon-only bell/close).

### data-tip patterns in the notif code
- Bell button: `data-tip="Notifications" data-tip-pos="left"` (blade :42).
- Close button: `data-tip="Close" data-tip-pos="left"` (blade :55).
- Unread toggle wrapper: `data-tip="Show only rows you haven't actioned yet"` (blade :65).
- Row: `data-tip="{{ $rowTip }}"` (no pos → ABOVE, notif-row :40).
- Time cell: `data-tip="{{ abs }}" data-tip-pos="left"` (notif-row :46).
- Multi-line precedent elsewhere: `ui-common.js:2334-2335`, `:2442`
  (`.replace(/"/g,'&quot;')` escaping pattern for building `data-tip` in JS strings).

---

## 4. RISKS / INTERACTIONS

### 4.1 Landed lanes this proposal must NOT re-propose
- **notif-click-highlight (applied, a201b34)** — click → mark read →
  `?notif-hl=` anchor + flash on arrival (`ui-common.js:2046-2067`,
  `:305-342`, `notif-row.blade.php:21-38`). The ask's "Preserve existing
  notification navigation" (line 111) = keep this intact.
- **notif-jump-flash-family (applied, 4a77a1f)** — today column + summary-card
  flash (`ui-common.js:410`, theme.css:2317-2318, replacement-arrangement
  :3117). Separate surface; only shares `flashRowPulse`.
- **notif-ghost-retract-strip (active in sdd.yaml, applied per review-log,
  6e29d56/acd35b7/62dd18b)** — the venue-intent ghost card + banner clause in
  `resources/views/livewire/replacement-arrangement.blade.php` (:1493, :3066,
  :3117, :4312) + `tests/venue-intent-modal.spec.ts:126-178`.
  **Overlap with notifications = NONE** (it never touches the bell/panel/rows);
  risk is only repo churn — `replacement-arrangement.blade.php`,
  `app/Livewire/ReplacementArrangement.php`, its changelog are DIRTY in the
  working tree right now (uncommitted other-lane work).
- **notifications-real-bridge (applied; sdd.yaml + review-log locally
  MODIFIED = its pending tail)** — the whole table/emitter/island/read-path
  stack. The pending tail is bookkeeping only (status frozen→applied + the
  2026-10-29 PI re-verify paragraph). Its laws constrain this proposal:
  rows never delete, D4 disabled-row law, ONE-feed, one-unread-total,
  after-commit emission, frozen tile keys.
- **notif-unified-feed (applied, c5aa747)** — one recipient-wide feed on every
  page (`NavNotifications.php:21-25, :31-34, :57-59`; blade :3-4, :84-87).
  ⇒ title/subtitle/desc changes render on EVERY staff page at once; the
  ONE-FEED equality test (`notifications-island.spec.ts:49-73`) compares
  `href|title` per row across 3 pages — still passes with a new format
  (equality, not literals), but ANY per-page divergence (e.g. different
  tooltip source per page) would break it.
- **notif-ad2-pagekey-tab (applied)** — superseded by unified-feed; `pageKey`
  is a no-op (`NavNotifications.php:31-34`). Don't design against tabs.
- **notif-abstime-precision (applied)** — abs-time tooltip formats the stored
  timestamp (`nav-notifications.blade.php:33-38`, `notif-row.blade.php:46`).
  Part 4's "Requested at" line builds on this; do not reintroduce now()-math.
- **notif-actor-pl-dedupe (applied)** — cancel fan-out dedupe
  (`NotificationEmitter.php:141-147`); unrelated to this ask but shares the emitter file.

### 4.2 The ONE-QUERY law (hardest implementation constraint)
`tests/Feature/NotificationsBridgeTest.php:253-274` —
`test_the_island_renders_with_exactly_one_replacement_requests_query`
asserts EXACTLY ONE SQL mentioning `replacement_requests` per island render
(the batch `whereIn` at `NavNotifications.php:106-108`), and its comment
records that an earlier `->with('request')` eager load was REMOVED as a
second query. → Parts 2/3/4 implemented at RENDER time (reading request /
proposer / lecturer / module / venue per row) must extend the batch resolve
into ONE batched load or the test breaks. Store-time (emitter) formatting
avoids this entirely.

### 4.3 Unread-count maths + honest-zero
- Badge `#notifBadge` (blade :47), pill :53, mark-all :54 all render from
  `$unreadTotal` = `whereNull('read_at')->count()` (`NavNotifications.php:55`)
  and `$userTotal = $unreadTotal > 0` (blade :39). Hidden-at-zero = the
  honest-zero shape today.
- N+3 (inactive ⇒ READ) makes `read_at`-only counting WRONG for inactive
  rows: either the count gains an `inactive` exclusion (needs the same batch
  status data at :55 — it is computed AFTER the count today, :55 vs :61-68)
  or inactive rows must be physically marked `read_at`. Any change must keep
  badge == pill == "actionable unread" (blade comment :9-10) and must not
  fabricate counts (CodingMAIN.md:43 honest zeros).
- The `markAll()` zero path (:78-84) stamps read_at on ALL mailbox rows
  including inactive ones (it does not filter) — so post-mark-all an inactive
  row would have read_at set; N+3's auto-classification must not double-handle.

### 4.4 Ghost-retract / other dirty working tree
- Nothing in the ghost-retract strip touches notification rows; the shared
  risk is only the uncommitted files listed in §1.1 (the proposal must not
  sweep them into its diff).

### 4.5 Jump-target readiness (where a `submitted` row lands)
- `/request-approval-ui` (the `submitted` row's link, emitter :44):
  queue rows are `<tr data-id="…">` (`request-approval.blade.php:683`),
  default status filter **'Pending'** (:484) → a decided request is not in
  the default view → arrival flash gracefully skips
  (`ui-common.js:333-342`, the documented accepted miss). The flash CSS
  (`tr.bookable-flash > td`, theme.css:2310) is global → ready.
- `/my-request-history-ui` (the `approved/rejected` rows' link, emitter :68):
  rows get `row.dataset.id = r.id` (`my-request-history.blade.php:523`) →
  `tr[data-id]` exists; **BUT this file is DIRTY in the working tree**
  (uncommitted status-tooltip/filter-chip lane) — coordinate before editing.
- `/my-timetable-ui` (cancel rows, kind `s`): needs `window.selectWeek` +
  `.event-block[data-sid]` (`ui-common.js:317-326`).
- Inactive rows never navigate (`notif-row.blade.php:40` span, listener
  `ui-common.js:2049-2050`) → N+1/N+2 make the row's data non-jumpable BY DESIGN.

### 4.6 Poll / morph interactions
- Both islands re-render server-side every 10 s (`wire:poll.10s.visible`,
  blade :14) — any per-row tooltip/attribute must be emitted in the BLADE
  (server render), not bound as a one-shot JS listener.
- The `.open` shell class lives OUTSIDE the island (ui-notifications-panel
  :1-13) so panel-open state survives morphs (receipt
  `notifications-island.spec.ts:89-107`).
- Newlines in `data-tip` are safe: the engine reads the attribute value on
  each `mouseenter` (`ui-common.js:3028-3042`), no innerHTML.

### 4.7 Tests that WILL break if the ask lands as written
| Ask part | Test | Why |
|---|---|---|
| N+1 (approved→inactive) | `tests/Feature/NotificationsBridgeTest.php:191-201` | asserts approved row KEEPS `href="/request-approval-ui"` |
| N+1 (read→inactive) | `tests/notifications-island-testing-db.spec.ts:131-133` | after mark-all it expects `a.notif-row--read` rows to exist (read rows are still anchors) |
| N+2 (copy) | `tests/Feature/NotificationsBridgeTest.php:147` | exact sentence `assertSee` |
| N+2 (copy) | `tests/notifications-island-testing-db.spec.ts:148` | exact `data-tip` attribute |
| 3 (drop requester name) | `tests/Feature/NotificationsBridgeTest.php:82` | `assertStringContainsString('submitted by …', body)` |
| 1/2/3 (render-time data) | `tests/Feature/NotificationsBridgeTest.php:253-274` | one-query-per-render law |
| N+3 (counting) | `tests/Feature/NotificationsBridgeTest.php:151-166` (badge law) + `:159` `assertSee('>1</span>')` | unread total definition changes |

### 4.8 House obligations for the implementation
- After any UI Blade change: update `page-changelogs/notifications-panel-changelog.md`
  (AGENTS.md standing rule; the file already carries 385 lines of lane history).
- PHP touched ⇒ `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G`.
- Playwright: run the read-only island spec on :8000 (demo DB, staff 5425
  storageState) and the writable spec only against :8030 testing DB
  (env-guarded, `notifications-island-testing-db.spec.ts:22-25`).
- No push, no commit unless the user says so; branch `fedora-backend`.

---

## 5. TEST INVENTORY (notifications today)

### Playwright — read-only demo DB
`tests/notifications-island.spec.ts` (4 tests, `.auth/staff-5425.json`):
1. `bell + pill carry the whole-mailbox unread total (F-2), no mock rows` :26-47 —
   bell visible, panel `.open`, pill == badge, no JS errors.
2. `ONE-FEED: the same notification list renders on every page` :49-73 —
   `href|title` row set equal across /request-approval-ui, /my-timetable-ui,
   /my-request-history-ui (unread toggle flipped off to include read rows).
3. `caught-up swap shows the single frozen line when the view is empty` :75-87 —
   `#notifEmpty` "You're all caught up".
4. `an OPEN panel survives poll cycles: state, toggle and pill preserved (AD-15)` :89-107 —
   12 s wait, toggle + pill + body lock survive the morph.

### Playwright — writable testing DB (guarded by CRS_MODE/CRS_ALLOW_SQL/:803x)
`tests/notifications-island-testing-db.spec.ts` (2 tests, serial):
1. `the badge lifecycle: growth within a poll cycle, durable POST shrink, mark-all zero` :91-134 —
   insert fixture → badge 1 → DB insert → badge 2 without reload → row click
   navigates + badge 1 → mark-all → badge 0 + `a.notif-row--read` visible.
2. `a cancelled request renders the row disabled with the user tooltip` :136-153 —
   exactly 1 `span.notif-row--inactive`, EXACT `data-tip`
   `'This notification is inactive because the request was cancelled.'` (:148),
   no href (:149), no data-id (:150), exactly 1 active `a.notif-row` (:152).

### Laravel feature tests
`tests/Feature/NotificationsBridgeTest.php` (11 tests):
- `:70` submitted rows land on every PL (frozen tile/link/body contains
  'submitted by …' :82)
- `:86` approved rows follow the per-role link law
- `:100` class-cancelled fans out to owner/students/dept-PLs (+ actor dedupe :124-133)
- `:136` lifecycle renders disabled with the user tooltip (exact sentence :147)
- `:151` badge law — one unread total per user
- `:168` reminder dedupe-until-read-then-rearm
- `:191` **lifecycle links STAY ALIVE for decided statuses (approved keeps href)**
- `:203` rejected rows reach the proposer (body contains reason)
- `:221` mark-read endpoint idempotent + auth-scoped (+ :249 CSRF/redirect)
- `:253` **exactly ONE replacement_requests query per island render**
- `:280` one unified feed per recipient (all pageKeys, recipient scoping)

Related but email-side: `tests/Feature/ApprovalWorkflowTest.php:520`
(notifications R3 email contract), `:566` `test_q13_decide_own_request_still_notifies`,
`tests/Feature/EmailQueueTest.php:29`.

Not the panel: `tests/venue-intent-modal.spec.ts:126-178` (ghost-retract family),
`tests/submission-conflict-refresh.spec.ts:5` (mentions the writable island spec
as a write-conflict caveat), `tests/ui-regression.spec.ts:243` (generic toast
selector `.notification`), `tests/venue-timetable.spec.ts:577` (toast hidden by
default). `tests/tmp-explore.spec.ts` = untracked ad-hoc explore script
(MRH/RA status tooltips), not notifications.

---

## 6. OPEN QUESTIONS A PROPOSAL MUST DECIDE

1. **N+2 string authority** — the ask has TWO variants (HTML example
   `This Notification is inactive -- the Request was CANCELLED by the Requester.`
   vs requirement `This notification is inactive because the request was cancelled by the requester.`);
   current code/tests use
   `This notification is inactive because the request was cancelled.`
   Which one is frozen? (All three cannot stand.)
2. **Scope of "New replacement request" rows** — `type === 'submitted'` only,
   or also the `awaiting` reminder rows ('Approval still pending',
   `NotificationEmitter.php:177`) whose request also gets approved/rejected/
   cancelled (they would stay ACTIVE otherwise — a stale active row)?
3. **N+1 "Read → inactive"** — does a read `submitted` row lose its href/data-id
   (killing notif-click-highlight's read-row navigation, its proposal line
   "read or unread, both")? Or is "inactive look" decoupled from "clickable"?
   Does every OTHER notification type (approved/rejected/update) also go
   inactive when read, or only New-replacement-request rows?
4. **Approved/Rejected → inactive vs the D4 law** — the frozen lifecycle law
   (real-bridge ruling #3) says inactive = link TARGET GONE (cancelled);
   decided requests still exist and the feature test :191 enforces their links
   stay alive. Does N+1 OVERTURN that law (test rewrite) or narrow to a visual
   state? Also: which surface shows the PL the decided request then?
5. **N+3 mechanics** — does "belong to READ" mean (a) excluded from the
   unread-only toggle + badge/pill, or (b) physically stamp `read_at` when the
   row goes inactive? Who stamps it (render-time write? the approve/reject/cancel
   action?), and what does `markAll()` do with them? Does the badge number
   change (honest-zero + one-unread-total law)?
6. **Data source for parts 2/3/4** — store-time (extend
   `NotificationEmitter::requestSubmitted` params + `SubmitReplacementRequest.php:222`
   caller; old rows keep old text; no query change) vs render-time (compose from
   `request_id` relations; must obey the ONE-QUERY law :253). Store-time cannot
   serve part 4's rich tooltip unless a new column/body convention is added —
   or the tooltip is composed render-time while title/desc are store-time (mixed).
7. **Legacy/demo rows** — existing DB rows ('New replacement request' without ID,
   body with 'submitted by …'): leave as-is (honest old format) or backfill?
   Demo-DB writes require explicit user approval (`docs/demo-db-data-changes.md`).
8. **Timestamp formats** — ask example `29 Oct 2026, 10:44 AM` (with year) vs
   the existing `$absTime` `j M, g:i A` (no year, blade :36-38). Which timestamp:
   notification `created_at` or request `submitted_at`? Which timezone —
   stored UTC vs the display MYT law (`ReplacementRequestMapper::DISPLAY_TZ`, :42)?
9. **Relative-time vocabulary** — current `$relTime` gives `5m ago`
   (blade :26-32); the ask wants `(5 minutes ago)`. New formatter or reuse?
   (Dynamic = recomputed each 10 s poll — fine, but format must be pinned.)
10. **Tooltip geometry** — multiline tip ABOVE the row inside a fixed panel
    (`position:fixed`, z-index 9999 vs panel 999) — is "above" enough room for
    5 lines near the panel top, or does the row need `data-tip-pos`? The ask
    says "reuse the existing tooltip design and positioning" (line 110).
11. **Venue name nulls** — `venues.room_name` may be null; fallback to room_code
    only? (Ask example `B002 — Tutorial Room`.)
12. **"All relevant notification pages"** (ask :114) — there is NO full
    notifications page; the surface is the ONE panel on every staff page
    (one-feed). Confirm the verification matrix = the panel on each of the 6
    staff nav pages (and student pages? student rows never carry
    New-replacement-request type).
13. **aria-label** — ask's HTML example shows an aria-label carrying title +
    module + week + requester + `(inactive)`; current `$rowLabel`
    (`notif-row.blade.php:20`) = title — body (+ ' (inactive)'). Update or keep?
14. **Who receives** — unchanged (all PLs, any dept, `SubmitReplacementRequest.php:195-197`)?
    The ask never changes recipients — confirm no recipient change is intended.

---

## 7. ADDENDA (late-verified facts)

- The literal `New replacement request` exists in exactly 4 live places:
  `app/Support/Notifications/NotificationEmitter.php:42` (the real row),
  `public/js/mock-data.js:977` + `:1063` (the RETIRED §2.13 mock seed rows —
  still in the mock file, not read by the panel), and the MAIL
  `app/Mail/ReplacementSubmitted.php:34` / `resources/views/mail/replacement-submitted.blade.php:1`
  — **the mail already carries the request ID as `[CRS#%d]`** (the precedent
  for part 1's `(ID #76)`). No test hardcodes the notification title.
- `.sdd/archive/2026-10-05-notifications-panel/design.md:110` — the ORIGINAL
  frozen mock row shape (`n-pl-1 submitted · "New replacement request" /
  "BMIT5678 Database Systems · submitted by En. Lim Jia Zheng" · 25m`) —
  useful history for how title/body/time were originally specced.
- `routes/web.php:105-110` — the only notification HTTP routes
  (`POST /notifications/read`; read-all deleted).
- `routes/console.php:11-14` — `notifications:remind-pending` daily schedule
  (`app/Console/Commands/NotifyPendingRequests.php:18`, dedupe law at
  `NotificationEmitter.php:160-186`).
- Student-facing `notificationCount` is a DIFFERENT thing:
  `app/Http/Controllers/Api/ApiReadController.php:850-862` = count of PENDING
  requests for the student timetable payload — do not confuse with the bell badge.
- Flux icon dir confirmed: only `book-open-text`, `chevrons-up-down`,
  `folder-git-2`, `layout-grid` (no bell/user/clock icons available there —
  the notif UI's SVGs are inline paths, `nav-notifications.blade.php:16-22`).
