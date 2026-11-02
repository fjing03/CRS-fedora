# Paste-ready prompt — the system-wide footer (`ui-footer-global`)

> Generated 2026-11-02 (main session), from the **frozen** SDD lane
> `.sdd/changes/ui-footer-global/` (proposed, designed and reviewed 2026-11-01;
> two reviewer rounds, round-2 verdict `🔴 Outstanding: NONE — PASS`).
> This is **not** an extra task. The lane is frozen and its apply was held for the
> user's explicit word — **this prompt is that word.** Paste the whole block into
> the PI session as-is.

---

## 0. Task card

| Field | Value |
|---|---|
| **Mode** | `IMPLEMENT` |
| **SDD** | **existing frozen lane** `.sdd/changes/ui-footer-global/` — run it as `/sdd-apply`, then `/sdd-verify`. **Do not open a new lane and do not redesign**: `proposal.md`, `design.md` and `tasks.md` are the spec. |
| **DB impact** | none — markup + CSS only |
| **Deliverable** | 5 files (3 new-ish: 2 blades + 1 CSS block; 2 layout includes) + a new changelog + receipts |
| **Done when** | every page (including both login pages) ends in one quiet centered footer strip; bottom-pinned on short pages; no clipping at 390px; light + dark legible; print hides it |

If any frozen decision contradicts the current code, **stop and report** — do not
improvise a different footer.

## 1. Read first

- `.sdd/changes/ui-footer-global/proposal.md` — the 9 user decisions (verbatim).
- `.sdd/changes/ui-footer-global/design.md` — files, anchors, token map, the
  per-breakpoint bleed law, the scope exclusion.
- `.sdd/changes/ui-footer-global/tasks.md` — T1–T5 (frozen).
- `.sdd/changes/ui-footer-global/review-log.md` — why the mobile mirror exists.
- `prompts/for-ai-to-read/AGENTS.md`, `my-coding-rules.md`,
  `prompts/for-ai-to-read/CodingMAIN.md` §10.0 (UI rules + colour map) and §10.
- `prompts/PI-PROMPT-TEMPLATE.md` §5 (changelog), §7 (gates), §10 (archive ask).

## 2. What the footer says (verbatim, from the frozen proposal)

Segments ①–⑥, in one centered, `flex-wrap` row — **gap only, no `·` text
separators between segments**:

1. `TAR UMT Class Replacement System`
2. version chip **`v1.0`** — `@php($version = 'v1.0')` lives **inside the partial**;
   no other file may name the version (single-writer law).
3. owner line — `© 2026 Poong Foo Jing · Bachelor in Information Technology (Honours) (Software Systems Development) · TAR UMT Sabah (RSD)`
4. `Demo dataset — all names & schedules simulated`
5. `poongfj@gmail.com` — icon-led `mailto:`, real `<a>`, `aria-label` + `title`
6. `https://github.com/fjing03/CRS-fedora` — icon-led (`folder-git-2`), `target="_blank"`,
   `rel="noopener noreferrer"`, `aria-label` + `title`

No JS. No PHP logic. Tokens only — **no hex/rgb anywhere in the block.**

## 3. Files (the design's F1–F5)

| # | File | Action |
|---|---|---|
| F1 | `resources/views/flux/icon/mail.blade.php` | **NEW** — Lucide *mail*, `Credit: Lucide (https://lucide.dev)` header, structure cloned verbatim from `resources/views/flux/icon/folder-git-2.blade.php` (same `@props(['variant' => 'outline'])`, same solid-variant throw, same `Flux::classes` matcher + svg wrapper). The shared set goes from 4 → 5 icons. |
| F2 | `resources/views/partials/ui-footer.blade.php` | **NEW** — segments ①–⑥, optional `$fixed` prop, version chip single-writer. |
| F3 | `resources/views/layouts/ui-template.blade.php` | **EDIT** — one `@include('partials.ui-footer')` **inside `.app-container`**, immediately after `@yield('content')` (today: `.app-container` at `:45`, `@yield` at `:46`, closing `</div>` at `:47`). |
| F4 | `resources/views/layouts/login-template.blade.php` | **EDIT** — one `@include('partials.ui-footer', ['fixed' => true])` **after the `.login-card` closing `</div>`** (today `:417`, before the inline `<script>` block). |
| F5 | `public/css/theme.css` | **EDIT** — ONE additive `.ui-footer*` block appended at EOF (file is 4306 lines today). |

## 4. The CSS block — must contain ALL of these

1. **Base `.ui-footer`** — centered, `display: flex; flex-wrap: wrap; justify-content: center; align-items: center;`
   with a **gap only** (no separators); `margin-top: auto` so it is bottom-pinned
   inside the flex-column `.app-container` (`theme.css:863–871`);
   `font-size`/`color: var(--color-on-surface-variant)`; a top hairline
   `1px solid var(--color-outline)`;
   background `var(--color-bg)`; desktop full-bleed `margin-left/right: -24px`
   (container x-padding is `16px 24px`).
2. **The ≤768px mirror** — inside `@media (max-width: 768px)`, `margin-left/right: -12px`,
   because `.app-container` drops to `padding: 10px 12px` there
   (`theme.css:1550–1551`). **This is the round-1 blocker:** without the mirror the
   strip overhangs 12px per side and is clipped by `body { overflow-x: hidden }`
   (`theme.css:156`).
3. `.ui-footer-chip` — `var(--radius-pill)` (precedent `.semester-chip`, `theme.css:889`),
   1px `var(--color-outline)` border, small padding, `white-space: nowrap`.
4. `.ui-footer-link` + `:hover` — `var(--color-on-surface-variant)` →
   `var(--color-on-surface)`; `text-decoration: none`; focus visible.
5. `.ui-footer--fixed` — for the auth pages: `position: fixed; left: 0; right: 0; bottom: 0; margin: 0; z-index: 5; background: transparent;`
   (the login body centers the card and hides overflow; `.bg-layer`/`.bg-overlay`
   are pointer-events-none, `.login-card` z:10 and `.theme-toggle` z:20 sit higher).
   Write this rule **after** the media mirror — equal specificity, later wins, and
   `margin: 0` neutralises the bleed on the fixed variant.
6. `@media print { .ui-footer { display: none; } }`.

**Light-theme check on the login pages:** the fixed strip sits over the animated
photo background. Eyeball it in light mode; if it fails legibility, the only
allowed tool is a **token-only `color-mix` scrim** — no new hex.

## 5. Honest scope (already decided — repeat it in the changelog)

- **Settings surfaces are excluded.** `pages/settings/*` renders through the
  starter-kit `layouts/app` shell, not `ui-template`/`login-template`. The footer
  covers app pages + login/auth pages only. Do **not** wire it there in this lane.
- No vendor/Flux edits beyond adding the one `mail` icon; no `ui-common.js`
  changes; no per-page `@section('page-styles')`; no routes, DB or PHP logic.

## 6. Verified anchors — 2026-11-02 (`49f9a58` head at the time of writing)

The November-1 numbers in `design.md` **have drifted**; re-grep before editing.

| Anchor | 2026-11-01 said | Today |
|---|---|---|
| `.app-container` | `:608–615` | **`:863–871`** (`padding: 16px 24px; padding-top: 72px`) |
| mobile `.app-container` | `:1285–1286` | **`:1550–1551`** (`padding: 10px 12px; padding-top: 66px`) |
| `body { overflow-x: hidden }` | `:156` | `:156` (unchanged) |
| `--radius-pill` | `:78 / :142` | `:78 / :142` (unchanged) |
| `.semester-chip` | `:645` | **`:889`** |
| token lines | `:18/:22/:25/:57`, light `:85/:89/:92/:124` | same |
| `.login-card` closing `</div>` | — | **`:417`** |
| `.app-container` / `@yield` | — | `:45` / `:46` |

## 7. Steps (frozen T1–T5)

1. **T1** add `flux/icon/mail.blade.php`.
2. **T2** add `partials/ui-footer.blade.php`.
3. **T3** wire both layouts (exactly one include each, exactly where §3 says).
4. **T4** append the CSS block (§4).
5. **T5** gates + receipts + record:
   - `composer run lint:check` ✓
   - `vendor/bin/phpstan analyse --memory-limit=1G` → **0 errors**
   - `php artisan test` → expect the current green suite (quote the real count;
     the lane predicted 277 — re-measure, don't repeat the number blindly)
   - stale-cache ritual, **two separate commands**:
     `pkill -f "[a]rtisan serve" || true`
     then `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`
   - live receipts as lecturer **5770**: login page (fixed, transparent, legible in
     light mode) · My Timetable + Cohort Timetable (bottom-pinned) ·
     **390px width** (wrap, no clipped edges — proves the −12px mirror) ·
     light theme · `@media print` hides it · screenshots into `/tmp/opencode/`.

## 8. Record + commit

- **Create** `page-changelogs/ui-footer-changelog.md` (per the template's
  create-or-update rule): what the strip contains, the token-only law, the
  per-breakpoint bleed, the fixed auth variant, **the settings-surface exclusion**,
  and the receipts.
- Commit message:
  `ui: the system-wide footer — identity, version chip, demo-data disclaimer, contact + repo links (ui-footer-global)`
- Stage **only**: the 5 code files + the new changelog + `.sdd/changes/ui-footer-global/`
  (when its status flips). **NEVER push.**
- Then `/sdd-verify` the lane against the frozen artifacts, flip `sdd.yaml` to
  `applied` when it passes, and report the SDD state in the reply.
- Finally, ask: *"Task done. Do you want me to archive this prompt into
  `prompts/completed prompt (Archieve)/`?"* — and only move it on a "yes"
  (`PI-PROMPT-TEMPLATE.md` §10). The lane itself is archived separately, later.

## 9. Stop and ask

- If the `.login-card` `</div>` at `:417` is no longer the card's close (the login
  template moves), stop and confirm the include point.
- If any token in the map is missing from either theme, stop — do not fall back to
  a literal colour.
- If the footer breaks an existing layout (nav overlap, scrollbars, clipping at
  any breakpoint), stop and report the measurement rather than "nudging" values.
