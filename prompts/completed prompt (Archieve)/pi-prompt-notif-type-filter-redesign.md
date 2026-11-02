# Paste-ready prompt for the PI session — UI: brand-new compact design for the notification type filter (`notifTypeFilter`)

> Generated 2026-11-02 (main session). Self-contained — paste this whole block
> into the PI session as-is. This is item **2)** of the user's current UI list
> and is standalone (no dependency on item 1).
>
> **Before you start: read the updated chat context — `PI_Chat_Context_Handover.md`
> (repo root, refreshed 2026-11-02 in commit `813b5ce`).** At minimum §0
> (Quick Status: the 15 unpushed commits, the BLANK demo DBs, the never-surface
> rules) and §0.7 (the 2026-11-02 session addendum). It supersedes any older
> context you may be carrying.

---

# Task: replace the notification type filter's skin with a brand-new compact design (visual re-skin only)

**User's ask (2026-11-02, verbatim intent):** a **brand-new** design for
`notifTypeFilter` (do NOT reuse the current one) — the current UI is **too
tall** to fit the `.notif-filters` row. Use the **color design of
`<select class="week-select" id="weekFilter">`** as the visual reference.

**Plain-words goal:** the type-filter dropdown inside the notification bell
panel currently renders 44px tall (a WCAG touch-target min-height) and crowds
the compact filter row. Re-skin it as a **36px, week-select-looking** control.
Same id, same options, same Livewire binding — **zero behavior change**.

---

## 1. Repo rules to load first (non-negotiable)

- **The updated chat context: `PI_Chat_Context_Handover.md`** (repo root,
  refreshed 2026-11-02, commit `813b5ce`) — read it before doing anything.
  Key facts it carries that this prompt assumes: the branch is
  `fedora-backend` with **15 unpushed local commits** (`origin` = `ab912af` +
  the user's own `cfd5d51`; re-count — never push); **both demo DBs are
  BLANK pristine** (0 requests/notifications; do not seed without the user's
  per-write gate); `docs/demo-db-data-changes.md` is the other session's write
  log — **never stage it**; the `receipt-target-guard` test lane is parked
  pending the user's word (do not mix it into this lane); and the ⛔
  `--env=testing` trap (no `.env.testing` — it silently boots the demo DB;
  testing DB only via `DB_DATABASE=class_replacement_testing`).
- `AGENTS.md` + `prompts/my-coding-rules.md` — colors = `public/css/theme.css`
  custom-property tokens only (**never hardcode hex/rgb**), except the
  pre-existing, documented exceptions you will be *copying verbatim* from
  `.week-select` (SVG data-URI chevron strokes + the focus glow — both are
  already sanctioned there; add a comment saying so, introduce no new hex).
- This is a **visual re-skin**. No JS, no PHP/Livewire logic, no DB writes.
  The demo DBs (`class_replacement_fresh` :8000, `class_replacement_demo`
  :8001) are **read-only** — verification here needs no writes, hence no
  snapshot required.
- Conventional commit (`ui:`), **do NOT push** unless the user explicitly says so.

## 2. Current state (verified in the main session — trust these lines)

**Markup** — `resources/views/livewire/nav-notifications.blade.php`
- Lines 80–100: `div.notif-filters` holds two toggle labels
  (`#notifUnreadOnly`, `#notifActiveOnly`) followed by:
  ```html
  <select class="filter-select notif-type-select" id="notifTypeFilter"
          aria-label="Filter by notification type"
          title="Filter by notification type" wire:model.live="typeFilter">
      <option value="">All types</option>
      <option value="submitted">New requests</option>
      <option value="awaiting">Awaiting decision</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
      <option value="update">Updates</option>
  </select>
  ```

**Old skin causing the height problem** — `public/css/theme.css`
- Base `.filter-select` (:859): surface-variant bg, on-surface-variant text,
  14px, `padding: 9px 14px`, radius-md.
- `.notif-filters .filter-select` (:387): 12px, `padding: 6px 10px`,
  **`min-height: 44px`** (the height the user objects to), `margin-left: auto`.
  Its comment says "the panel-context dropdown = the ONE `.filter-select`" —
  grep confirms nothing else inside `.notif-filters` uses this class, so once
  the element stops using `filter-select` this rule is dead and must be deleted
  (clean single-source; record the deletion in the changelog).
- The class `notif-type-select` exists on the element but has **zero styles
  anywhere** (dead hook). It becomes the **new** design's class — this is not a
  reuse of old styling.

**Test coupling (keep green, unchanged):**
- `tests/notifications-island.spec.ts` — asserts `#notifTypeFilter` visible
  (:145, :189) and drives it via `selectOption('#notifTypeFilter', …)`
  (:192–207); toggles via `.notif-filters .toggle-wrapper` (:68, :110, :186).
- `tests/notifications-island-testing-db.spec.ts` — toggles only (:191, :254).
- ⇒ the control MUST stay a real `<select>` with the same id and the same
  option `value`s (those values are the wire:model filter keys).

**Changelog home:** `page-changelogs/notifications-panel-changelog.md`.

## 3. Design reference — copy `.week-select`'s visual DNA only

Reference block: `public/css/theme.css:1714–1758` (`.week-select`, plus
`.week-select:hover/:focus/option` and the `.light .week-select` chevron swap).

**Do NOT extend or put `.week-select` on this element.** That class is welded
to week-nav semantics (fixed 180px min-width) and is special-cased by
`.semester-bar select:not(.week-select)` rules — touching it risks other pages.
Instead: a **new scoped class** (default name: `.notif-type-select`, reusing
the existing dead hook name) whose declarations copy the look. A second copy of
these declarations is fine under the repo's promote-on-3rd rule.

### Required declarations for `.notif-type-select`
- `font-family: inherit; font-size: 13px; font-weight: 600;`
- `height: 36px;` — **fixed**. This is the user's explicit compactness request
  and a conscious waiver of the 44px touch-target convention **for this one
  control only**; say exactly that in the CSS comment (dated) and in the
  changelog. Floor 32px if 36 still crowds; never above 36.
- `border-radius: var(--radius-md);`
- `border: 1px solid var(--color-outline);`
- `background: var(--color-surface); color: var(--color-on-surface);`
- `cursor: pointer; appearance: none; -webkit-appearance: none; -moz-appearance: none;`
- Custom chevron (copy the exact data-URIs from `.week-select`):
  ```css
  background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%239EAAB8' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
  background-repeat: no-repeat;
  background-position: right 10px center;
  ```
  `padding: 0 32px 0 12px;` (chevron room, like the reference)
- `min-width: 170px;` and keep `margin-left: auto;` (stays parked row-end).
- Hover: `border-color: var(--color-primary);`
- Focus: `outline: none; border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(0,77,152,0.15);`
  (the reference's exact glow — same documented exception, comment it)
- `.notif-type-select option { background: var(--color-surface); color: var(--color-on-surface); }`
- Light theme: mirror `.light .week-select` — `color-scheme: light;` + the same
  chevron with light stroke `%235A6978` (SVG data-URIs cannot use `var()` —
  the reference's own comment says so).
- Transition: `border-color 0.15s, box-shadow 0.15s;` (reference parity).

Place the new block in `theme.css` next to the `.notif-filters` section
(~:387) with a dated doc comment.

## 4. Markup change (one attribute edit)

Line 91 becomes:
```html
<select class="notif-type-select" id="notifTypeFilter" aria-label="Filter by notification type"
        title="Filter by notification type" wire:model.live="typeFilter">
```
Everything else (the 6 options incl. values, wrapper, toggles) untouched.

## 5. Hard constraints — do NOT

- touch the `.filter-select` base family (other pages consume it — e.g. the
  intent-session modal), `.week-select`, the semester-bar rules, toggle styles;
- change `id`, `wire:model.live="typeFilter"`, aria-label, title, option
  labels/values, or the element type;
- add JS or PHP logic, or reorder the panel's filters;
- affect any other page (the bell island is shared — the change must be
  visually confined to `.notif-filters` in the dropdown panel).

## 6. Verification + receipts

1. **Blade touched** → stale-cache restart, TWO separate shell commands (never
   combined; precise kill, spare other servers):
   `pkill -f "[a]rtisan serve --port=8000"`
   then
   `rm -f storage/framework/views/*.php && nohup php artisan serve --port=8000 >/tmp/crs-serve-8000.log 2>&1 &`
2. **Read-only visual check (playwright, :8000, staff 5770 / `Tarumt@2026`):**
   open the bell dropdown on `/my-timetable-ui`, then
   - measure `.notif-type-select` height = **36px**;
   - confirm the two toggles + select fit the row at the panel's natural width
     (~360px) with no cropping/clipping and the select still parked right;
   - screenshot the OPEN dropdown (options list visible) in **dark AND light**
     themes (`/tmp/opencode/notif-filter-dark.png`, `-light.png`);
   - the demo DB is blank → the panel legitimately shows its empty state; the
     filter row must still render normally.
3. **Specs:** run `tests/notifications-island.spec.ts` **against the disposable
   testing DB only** — it needs seeded notification rows, so reseed
   `class_replacement_testing` (`DB_DATABASE=class_replacement_testing php
   artisan migrate:fresh --seed`) and run against that lane (:8030). ⛔ Never
   reseed/reset `class_replacement_fresh` or `class_replacement_demo`, and
   never use `--env=testing` (no `.env.testing` exists — it would silently hit
   the demo DB). Spec file itself must stay **unmodified**.
4. **Gates:** `composer run lint:check` + `vendor/bin/phpstan analyse
   --memory-limit=1G` (cheap safety even though blade/CSS-only).
5. **Changelog:** dated entry in `page-changelogs/notifications-panel-changelog.md`
   — the new class + copied colour map, the 44→36 height waiver (user
   instruction), the deleted dead `.notif-filters .filter-select` rule, the
   spec coverage note.
6. **Commit:** `ui:` conventional message noting "SDD-waived w/ user approval"
   (restyle ordered directly by the user). **Do not push.**

## 7. Acceptance checklist

- [ ] `#notifTypeFilter` is a working `<select>`, same id/options/wire binding;
      island specs green (testing DB lane).
- [ ] Rendered height = 36px (waiver documented); fits `.notif-filters` beside
      the toggles, no clipping, still right-aligned.
- [ ] Visual DNA = `.week-select`: surface bg, outline border, 13px/600 text,
      rounded-md, chevron, blue hover/focus ring — identical in dark + light.
- [ ] No hex/colors outside the two copied exceptions; no other page shifts.
- [ ] Dead `.notif-filters .filter-select` rule removed; changelog updated;
      commit made, nothing pushed.
