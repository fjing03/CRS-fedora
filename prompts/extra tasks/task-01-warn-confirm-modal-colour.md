# Extra task 01 — the "lose your selection" modals get the warning (yellow) face

> **Status: ⏸ PARKED — do not start unless the user names it.**
> Added 2026-11-02. Settled by the user in the same message ("Settled — 6 call
> sites get `{ warnStyle: true }`"). Goal is **colour only**: no copy, layout,
> button, icon or callback changes, and no other page is touched.

## What the user asked for (verbatim intent)

On `replacement-arrangement`, after selecting any slot, these actions pop a
confirmation modal that warns the selection will be lost:

1. changing the **venue**
2. changing the **subject**
3. changing the **slot**
4. **Clear All** selections
5. the system **Go back** button
6. leaving the page through in-app navigation (same "Unsaved Changes" family)

Those menus must use the **warning colour (yellow)** because they are warnings.
**ONLY the colour changes.**

## Read first

- `prompts/for-ai-to-read/AGENTS.md` (standing rules) + `my-coding-rules.md`
- `prompts/for-ai-to-read/CodingMAIN.md` §10.0 (UI rules + colour tokens), §10
- `page-changelogs/replacement-arrangement-changelog.md` (where the entry lands)
- The existing `.error-modal` pattern in `public/css/theme.css` — this task is
  its warning-coloured sibling, built the same way (class toggled per call site,
  re-derived on every open, reset on close).

## Verified anchors (2026-11-02, source HEAD `49f9a58`)

Re-verify before editing — these drift.

| Anchor | Where | Note |
|---|---|---|
| `showConfirmModal(title, bodyHtml, callback, opts)` | `resources/views/livewire/replacement-arrangement.blade.php:2021` | add `opts.warnStyle` beside the existing `errorStyle` toggle |
| `.error-modal` toggle | same file `:2031` **and duplicated at `:2036`** | the duplication already exists; add `warnStyle` once — **do not "fix" the duplicate** (out of scope) |
| venue guard | `:1932` (`onVenueChange`) | `{ danger: true, … }` |
| subject / slot guard | `:2081` (shared function) | one call site serves **both** subject and slot → 5 call sites, 6 triggers |
| clear all | `:2291` (`clearAll`) | `{ danger: true, … }` |
| navigate away (in-app) | `:2325` (`navigateTo`) | `"Unsaved Changes"` |
| system back | `:2345` (`goBack`) | `"Unsaved Changes"` |
| `.error-modal` styles | `public/css/theme.css:2955–2980` | title `:2959`, header hairline `:2962`, conflict-rows `:2965`, footer fill `:2972` — **note: this moved from the ~:2686 quoted in the original plan** |
| `.warn-modal` | — | **does not exist yet** |
| tokens | `theme.css:52/54` (dark) and `:119/121` (light) | `--color-warning` `#D4880F`, `--color-warning-container` (rgba) |

Call sites **not** in scope: `:2133` "No Selection", `:2151` "Confirm Your
Selection", `:2256` "Submission CONFLICT" (stays **red**), and every other page.

## Implementation (three steps)

1. **`showConfirmModal`** (`blade:2021`) — mirror the `errorStyle` line:
   `document.querySelector('#confirmModal .modal').classList.toggle('warn-modal', !!opts.warnStyle);`
   Re-derived on every open, exactly like `.error-modal` (no stale class leaks).
2. **`theme.css`** — one additive block beside `.error-modal` (~`:2955`), scoped
   to the `#confirmModal` shell, tokens only:
   - `.modal-title { color: var(--color-warning); }`
   - `.modal-header { border-bottom: 1px solid var(--color-warning-container); }`
   - a modal border in `var(--color-warning-container)` if the shell reads better
     with one (optional, still colour-only)
   - **no footer rule**: the buttons keep their current `danger` styling.
3. **The 5 call sites** (`:1932`, `:2081`, `:2291`, `:2325`, `:2345`) — add
   `warnStyle: true` to the existing opts object. Everything else in those calls
   stays byte-identical (labels, copy, `danger`, callbacks).

## Non-goals (explicit)

- No text/copy changes; no icon or button changes; no layout/spacing changes.
- No behaviour change: the same modals open for the same triggers, with the same
  confirm/cancel labels and callbacks.
- Do not touch the `.error-modal` rules or the duplicated toggle.
- Do not widen the guard logic or "improve" it while here.
- No change outside `replacement-arrangement` + `theme.css`.

## Acceptance checklist

- [ ] Venue change → modal **yellow** (title + header hairline; buttons unchanged)
- [ ] Subject change → **yellow**
- [ ] Slot change → **yellow**
- [ ] Clear All → **yellow**
- [ ] System **Go back** → **yellow**
- [ ] In-app navigation ("Unsaved Changes") → **yellow**
- [ ] "No Selection" → unchanged (neutral)
- [ ] "Confirm Your Selection" → unchanged (neutral)
- [ ] Submission CONFLICT → still **red** (error face intact)
- [ ] Closing and reopening a neutral modal shows **no leftover yellow**
- [ ] Dark + light theme both read as warning, not error
- [ ] No console errors

## Landing

1. Changelog entry in `page-changelogs/replacement-arrangement-changelog.md`
   (what, why, the 6 triggers, "colour only", the 5 call sites).
2. No PHP is touched → `composer run lint:check` / phpstan are optional, but the
   user's original plan asked for them; run them if the session changes any PHP.
3. Stale-cache restart ritual, **two separate commands** (per AGENTS):
   `pkill -f "[a]rtisan serve" || true` then
   `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`
   (for the Viva copy on `:8001`, do the same with its own `--port=8001`).
4. Live verify as lecturer **5770** (or 5425) on the page, then screenshot.
5. Commit one conventional commit, e.g.
   `ui: the lose-your-selection modals take the warning face — warnStyle opt-in at 5 call sites (6 triggers), one additive .warn-modal block, colour only`.
   Stage only the two touched files + the changelog. **No push.**
6. After shipping, the Viva copy (`CRS-demo`, `:8001`) can fast-forward and shows
   the same change — no migration, no database work. Then ask the user whether to
   archive this file (`prompts/general&reusable prompt references/PI-PROMPT-TEMPLATE.md` §10).

## Risks / notes

- Line drift is the main risk: this file's anchors were true at `49f9a58`; if the
  arrangement page changes first, re-grep `showConfirmModal(`, `error-modal` and
  `--color-warning` instead of trusting the numbers.
- The warning token is amber (`#D4880F`), not yellow-yellow; that is the project's
  canonical warning colour — do not introduce a new hex.
- The modals already render `btn-danger` (via `danger: true`). That is deliberate
  and stays; only the shell's colour changes.
