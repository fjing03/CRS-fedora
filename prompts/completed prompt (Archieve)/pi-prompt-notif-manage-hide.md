# Paste-ready prompt for the PI session — Notifications: batch hide + hidden shelf (design LOCKED, preview approved)

> Generated 2026-11-02 (main session) after the user approved the design and the
> clickable preview. Self-contained — paste this whole block into the PI session.
>
> **Before you start: read `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` (in `prompts/for-ai-to-read/`, refreshed
> 2026-11-02, commit `bbd5615`).** At minimum §0 (Quick Status: **21** unpushed
> commits, the never-surface rules) and **§0.8** (this feature's locked design +
> the traps found while prototyping). §0.8 supersedes this prompt only on
> wording; the copy table below is authoritative.
>
> **⚠️ Two facts supersede §0.8 — it was written minutes before these:**
> 1. **The migration already exists and is APPLIED on all three databases:**
>    `database/migrations/2026_11_02_000001_add_hidden_at_to_notifications_table.php`
>    (`hidden_at` nullable timestamp + `(user_id, hidden_at)` index), committed
>    `5af11a6`; `migrate:status` = **Ran** on `class_replacement_fresh`,
>    `class_replacement_demo` and `class_replacement_testing`. §0.8's
>    "F. NOT started: no migration" is **stale** — **do not create a second
>    migration.** Snapshots were taken first:
>    `backups/class_replacement_*-pre-hidden_at-2026-11-02.dump`.
> 2. **The demo DBs are no longer blank:** `class_replacement_fresh` holds
>    **5 notifications + 2 replacement requests** (created 2026-11-02); the twin
>    holds 0 notifications. Never assume an empty canvas, never reseed them.
>    The write is logged in `docs/demo-db-data-changes.md` (2026-11-02 entry).

---

# Task: let users tidy the notification panel by hiding finished notifications (reversible), with a find-back shelf

**Plain-words goal:** the bell panel accumulates every notification forever
(rows are never deleted by design). Users want to clear the clutter **without
losing anything**. So: batch-select the rows they are *done with*, hide them
(one timestamp column), and always be able to get them back from a
`Hidden (N)` shelf in the same panel.

**Approved by the user after a 9-question review. Do not re-litigate the
decisions below — implement them.**

---

## 1. Repo rules to load first (non-negotiable)

- **READ AND OBEY `prompts/for-ai-to-read/my-coding-rules.md` IN FULL before writing any code.**
  It is the user's own distilled rulebook (the 10 UI rules + the canonical
  colour map + OOP/PHP/naming + the commit/SDD policy). `AGENTS.md` and
  `prompts/for-ai-to-read/CodingMAIN.md` §10 are the long form; where they differ, the stricter
  reading wins. For this task the rules that actually bite:
  tokens-only colours; **same meaning = same colour**; icon-first with a
  `data-tip`; fewest clicks; confirm-or-undo on critical actions; no duplicated
  markup/CSS/data (promote to shared on the 3rd repetition).
- `AGENTS.md`, `prompts/for-ai-to-read/CodingMAIN.md` §10 (conventions + the 10 UI rules),
  `docs/venue-restrictions.md` (not relevant here but read it once).
- **Colours = `theme.css` custom-property tokens only** — never hardcode
  hex/rgb.
- **No new dependencies.** Flux icons are at `resources/views/flux/icon/` —
  reuse them; otherwise inline SVG in the house 24×24 stroke style.
- Conventional commits (`feat:`, `ui:`, `test:`, `docs:`), **NEVER push** unless
  the user says so.
- **Do not stage** `docs/demo-db-data-changes.md` (another session's log).
- **Do not touch the parked test lane** (`tests/helpers/receipt-target-guard.ts`,
  `.sdd/changes/receipt-target-guard/`, `tests/submission-conflict-refresh.spec.ts`)
  — it is waiting for the user's separate word (§0.7 D).

## 2. The locked design (implement exactly this)

### 2.1 Data

- **Already shipped and applied** (commit `5af11a6`):
  `database/migrations/2026_11_02_000001_add_hidden_at_to_notifications_table.php`
  — `hidden_at` nullable timestamp + index `(user_id, hidden_at)`; Ran on
  `class_replacement_fresh`, `class_replacement_demo`, `class_replacement_testing`.
  **Do not add another migration for it.**
- Hide = stamp `hidden_at = now()`. Unhide = `hidden_at = NULL`.
  **Rows are never deleted.** No "cannot be undone" copy anywhere.
- **Hideable gate = `read_at !== null || inactive_reason !== null`.** This is the
  exact complement of the badge predicate (`read_at === null &&
  inactive_reason === null`), so hidden rows can never be badge-counted.
  `inactive_reason` is **derived in PHP** (`isInactiveRow()`), not a column —
  therefore the gate must be evaluated in PHP and the posted ids
  **re-validated server-side** (never trust the client; a hidden row that fails
  the gate must be rejected).
- Hidden rows are excluded from the visible list (both normal and manage mode).
  They do **not** participate in the unread/active/type filters — those apply to
  the visible list only. `hiddenCount` is the total hidden for this user.
- Badge logic in `render()` is **untouched** (proof: hideable ∩ badge-counted = ∅).
- Update the frozen docblock honestly: "rows are never deleted" stays true; add
  that hiding is a soft, reversible flag.

### 2.2 States and copy (strings are final — use verbatim)

**Inbox (default)**
- Head: title `Notifications`, unread pill, **icon-only** Mark all
  (envelope-with-tick), **Manage** (list-with-tick), Close.
- The Manage icon renders **only when ≥1 hideable row exists**.
- Filters row unchanged except §2.4 (type select full width).
- Footer, right-aligned quiet line **`Hidden (N)`** — rendered **only when N > 0**
  and **also in the empty/caught-up state** (that is the find-back guarantee).

**Manage mode** (entered via the Manage icon)
- The filter row is **replaced** by a selection bar:
  left `N of M selected`, right `Select all` · `Clear`.
- Muted caption under the bar: `Notifications that still need you can't be hidden.`
- List = **only hideable rows**, every row **pre-ticked on entry**, each row
  carries a checkbox (reuse `.bulk-checkbox`; selected row = reuse
  `.row-selected` — the existing promoted pair from My Request History).
- Footer: left `N selected`, right neutral ghost button `Hide selected`
  (`.btn-outline` family — **no red, no blue**; hiding is reversible).
- `Hide selected` → hide + **Undo toast** (no confirm modal):
  `N notifications hidden · Undo restores them` (singular: `1 notification hidden`).
- Leaving manage mode without hiding changes nothing.

**Hidden shelf** (footer `Hidden (N)` → )
- Head title `Hidden notifications`, **icon-only Back** (arrow-left).
- Bar: left `N hidden`, right `Unhide all`.
- Rows are the same row partial but **non-anchor**, dimmed, each with a small
  `Unhide` action.
- Footer note: `Hidden notifications stay in your mailbox — restore them anytime.`
- Empty shelf: `Nothing hidden — everything is back in your inbox.`
- Unhide / Unhide all return rows to the inbox immediately (small confirmation
  toast, e.g. `Notification restored to the inbox` — no Undo needed).
- Entering/leaving any state must not lose the other state's scroll or the
  filter settings.

### 2.3 Head icons (all widths)

- Mark all becomes **icon-only at every width** (user decision), same 30px
  square + border as the Close/Manage buttons; tooltip + `aria-label` carry the
  meaning. `#notifMarkAll` id and `wire:click="markAll"` **must stay** (tests and
  behaviour depend on them).
- Back (hidden shelf) is the same 30px icon button with `aria-label="Back to inbox"`.
- Verified in the preview: with pill + 3 icons the head does **not** overflow at
  320 / 360 / 390 / 1280px. Re-verify after implementation.

### 2.4 Type filter

- `#notifTypeFilter` must **span the whole `.notif-filters` row**
  (`flex: 1 1 100%` / `width: 100%`), dropping to its own line under the two
  toggles. Everything else about the control stays as shipped in `210a232`
  (36px, same id/options/`wire:model.live="typeFilter"`).

### 2.5 Tooltips — reuse the EXISTING system, do not invent one

All tips ride the shared `data-tip` + `.data-tip-tooltip` system
(`initDataTipTooltips()` in `public/js/ui-common.js`). `\n` in the attribute
switches the existing multi-line variant on.

| Target | `data-tip` |
|---|---|
| unread pill | `N unread messages` (dynamic; also `aria-label`) |
| Mark all | `Mark every notification as read.` |
| Manage | `Put away notifications you have already read.` + `\n` + `Nothing is deleted — you can bring them back.` |
| Hidden (N) | `See the notifications you put away.` + `\n` + `Every one of them can be restored.` |
| Back | `Go back to the notification list.` |
| type select | **add one** (e.g. `Filter the list by notification type.`) **and remove the native `title` attribute** — two tooltip systems would both fire |
| Unread only / Active only | **keep the existing copy verbatim** (`Show only rows you haven't actioned yet` / `Hide decided/inactive rows — show live items only`) |

## 3. Traps already proven (do not re-discover them)

1. ⛔ `theme.css:444 #notifMarkAll` is the old **44px text-button** rule with
   `padding: 0 10px`. Putting a 16px svg inside it **squeezes the icon to ~10px**
   and it looks faded. **Rewrite that rule** (drop the text-button sizing), don't
   just add a new style on top.
2. The undo toast must **survive the 10-second `wire:poll`** re-render
   (`wire:poll.10s.visible` on the island root). Keep the undo id list in
   Livewire state and render the toast from the component; a purely client-side
   toast gets wiped by the poll. Suggested: toast rendered when
   `$undoIds !== []`, its timer calls a `dismissUndo` action.
3. Manage mode is the reason the rows stop being anchors — a nested `<button>`
   inside an `<a>` is invalid HTML. In manage mode rows are `<label>` wrappers
   with a checkbox; hidden-shelf rows are plain non-anchor elements.
4. `#notifMarkAll` is driven **by id** in
   `tests/notifications-island-testing-db.spec.ts:179–186` — keep the id.
5. The panel's `.notif-panel` has `overflow: hidden`; the shared tooltip is
   `position: fixed`, so it is not clipped — do not add local tooltip CSS.
6. **The `hidden_at` migration is already applied** (see the header + §2.1).
   `php artisan migrate:status` must show
   `2026_11_02_000001_add_hidden_at_to_notifications_table … Ran` on all three
   DBs; if it does not, **stop and report** — do not "fix" it by migrating or
   resetting anything.

## 4. File-by-file plan (starting point — adjust if the code says otherwise)

1. **Migration — already exists and is applied; do NOT recreate it**
   (`database/migrations/2026_11_02_000001_add_hidden_at_to_notifications_table.php`,
   commit `5af11a6`). If any *further* schema change turns out to be needed,
   follow the schema-migration law in `prompts/for-ai-to-read/CodingMAIN.md` §4: additive-only,
   verified `pg_dump` first, rehearse on `class_replacement_testing`, print the
   target, then plain `migrate` fresh → demo.
2. **`app/Livewire/NavNotifications.php`**
   - State: `bool $manageMode`, `array $selectedIds`, `bool $hiddenView`,
     `array $undoIds`.
   - `render()`: keep the badge block byte-for-byte; add `$visible`, `$hideable`,
     `$hidden` collections (hidden rows excluded via `whereNull('hidden_at')`),
     `$hiddenCount`. Do not change the existing `inactive_reason` classifier.
   - Actions: `enterManage()`, `exitManage()`, `toggleSelect(int $id)`,
     `selectAllVisible()`, `clearSelection()`, `hideSelected()`,
     `undoHide()`, `dismissUndo()`, `showHidden()`, `showInbox()`,
     `unhide(int $id)`, `unhideAll()`.
   - `hideSelected()`: filter ids through the PHP gate, `update(['hidden_at' => now()])`
     for the survivors, keep them in `$undoIds`, exit manage mode.
   - Authorise every action (`Auth::id()`); never trust ids from the client.
3. **`resources/views/livewire/nav-notifications.blade.php`**
   - Head: icon-only Mark all (`#notifMarkAll` kept), gated Manage icon, tooltips.
   - Filters: keep both toggles + their tips; add the type-select tooltip,
     remove `title`.
   - Manage bar + caption + checked rows (`.notif-row--pick` with
     `.bulk-checkbox`), footer `N selected` + `Hide selected`.
   - Hidden shelf markup + footer `Hidden (N)` (also in the empty state).
   - Undo toast markup (survives polling — see trap 2).
4. **`resources/views/partials/notif-row.blade.php`**
   - Add an **optional variant** parameter (`pick` / `hidden`), default `null`
     reproducing today's output byte-for-byte for every existing caller (the
     frozen partial contract). No behavioural change for the default path.
5. **`public/css/theme.css`**
   - Rewrite `#notifMarkAll` (§3.1).
   - New dated block: `.notif-manage-bar`, `.notif-bar-count`, `.notif-bar-btn`,
     `.notif-caption`, `.notif-foot`, `.notif-foot-link`, `.notif-row--pick`
     (grid: checkbox + icon + body + time), `.notif-row--hidden`, `.nr-right`
     (time over Unhide), and the `#notifTypeFilter` full-width rule.
   - Reuse `.bulk-checkbox` / `.row-selected` — do **not** duplicate them.
   - Mobile ≤640px: the footer must stay visible in the sheet; no new media
     block unless the existing one needs it.
6. **Tests**
   - Extend `tests/notifications-island.spec.ts`: enter manage → pre-ticked
     count, untick, `Hide selected`, undo toast, `Hidden (N)` appears, shelf
     rows, `Unhide all`, footer link disappears at 0. Keep the existing
     `#notifTypeFilter` / toggle assertions untouched and passing.
   - If `tests/Feature/NotificationsBridgeTest.php` covers the component, add:
     gate rejects a non-hideable id; hide stamps `hidden_at`; unhide clears it;
     badge unchanged after hiding; hidden rows absent from the visible list.
7. **Changelog** `page-changelogs/notifications-panel-changelog.md` — dated entry
   with the new states, copy, the `hidden_at` column, the `#notifMarkAll`
   rewrite, the select width change and the test evidence.

## 5. Verification (receipts required)

1. **Blade touched** → two separate shell commands, never combined:
   `pkill -f "[a]rtisan serve --port=8000"`
   then
   `rm -f storage/framework/views/*.php && nohup php artisan serve --port=8000 >/tmp/crs-serve-8000.log 2>&1 &`
2. **Read-only Playwright on `:8000`** (staff `5770` / `Tarumt@2026`; the demo DB
   is **read-only**: it currently holds 5 notifications + 2 requests from
   2026-11-02 — drive any *write* flow on the seeded **testing** lane instead,
   never on the demo DBs without the user's per-write gate):
   - head has no overflow at **320 / 360 / 390 / 1280**;
   - type select width = the filters row's content width;
   - hover each of the six tips → the shared tooltip renders (text matches the
     table), dark **and** light;
   - full loop: Manage → untick one → Hide selected → Undo toast → `Hidden (N)` →
     Unhide one → Unhide all → footer link disappears;
   - screenshots into `/tmp/opencode/` (desktop + 360px, dark + light).
3. **Gates:** `composer run lint:check` + `vendor/bin/phpstan analyse
   --memory-limit=1G`; `node --check` for any JS touched.
   Run the notification specs **only** against the disposable testing DB
   (`DB_DATABASE=class_replacement_testing … migrate:fresh --seed`, lane
   `:8030`). ⛔ Never use `--env=testing` (no `.env.testing` exists — it would
   silently boot the demo DB).
4. **Schema writes:** the `hidden_at` migration is **already applied** — no
   further demo-DB write is needed for it. Any *new* schema change is a demo-DB
   write: verified `pg_dump -Fc` (`pg_restore --list` must pass) + the user's
   explicit gate; the testing DB is free.
5. **Commits:** the migration is already committed (`5af11a6`), so suggest
   `feat:` (component + view) / `ui:` (CSS + tooltips + select width) /
   `test:` — or one `feat:` if cleaner; changelog entry included.
   **Do not push.**

## 6. Acceptance checklist

- [ ] `prompts/for-ai-to-read/my-coding-rules.md` honoured: tokens-only colours, one meaning =
      one colour, icon-first with working tooltips, undo instead of a confirm
      popup, no duplicated markup/CSS/row markup (partial reused, not cloned).
- [ ] `notifications.hidden_at` is **verified present** (nullable, indexed) and
      its migration shows Ran on all three DBs — **not recreated**; nothing is
      ever deleted; undo restores.
- [ ] Only read-or-inactive rows are ever hideable, enforced **server-side**;
      the badge is provably unaffected.
- [ ] Manage mode: pre-ticked, selection bar, caption, neutral `Hide selected`,
      undo toast — survives the 10s poll.
- [ ] `Hidden (N)` renders when N > 0 (including empty state); shelf has Unhide +
      Unhide all; the link disappears at 0.
- [ ] Mark all + Back are icon-only with working tooltips; `#notifMarkAll` id and
      `wire:click="markAll"` preserved; `theme.css #notifMarkAll` **rewritten**
      (icon renders at 16px, not squeezed).
- [ ] Type filter spans the row; its native `title` removed; both toggle tips
      unchanged.
- [ ] No hex/rgb outside existing token usage; no new dependencies; no other
      page affected.
- [ ] Lint + phpstan green; notification specs green on the testing DB;
      changelog updated; commits made, nothing pushed.
