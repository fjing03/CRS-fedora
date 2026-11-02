# PI prompt — Red "Too late to book" (no-fit) cells on the Venue Timetable

**What this is:** paste-ready prompt for the PI session to implement the user's red-cell directive on
the venue page — and to record every change in the matching changelog file.

**Page:** `http://127.0.0.1:8000/venue-timetable-ui?venue=B006` (ONLY this page may be changed).
**User decision (2026-10-25):** no-fit cells go red on BOTH the resting background AND the hover label pill.
**Status:** ready to hand over — scope already decided, implement.

---

Copy everything below this line:

````markdown
# Venue Timetable — make "Too late to book" cells RED (approved, implement now)

## Context
Repo: TARUMT Class Replacement System (Laravel 12 + Livewire + Blade, branch `fedora-backend`).
Page: `http://127.0.0.1:8000/venue-timetable-ui?venue=B006` — route `/venue-timetable-ui` →
`App\Livewire\VenueTimetable` → view `resources/views/livewire/venue-timetable.blade.php`.

Read FIRST (every session): `CodingMAIN.md` in full, then `prompts/my-coding-rules.md`
(UI Design Rules §10.0 — colours only from `public/css/theme.css` CSS custom-property tokens,
never hardcoded hex/rgb).

## Baseline note (the user's words — read before touching anything)
> "Note: I've made minor UI updates via OpenCode — just use those new versions of the UI."

In practice: **the repo's CURRENT working-tree state is the intended UI.** Build on top of it —
never revert, re-apply, or "restore" recent-looking UI (extra legend chips, changed hover
labels/wording, CSS tweaks) to whatever older docs, specs, changelogs, or `.sdd/archive` files
still describe. If a spec/comment/doc disagrees with what the page actually renders, the **page
wins**: keep the UI as-is, report the mismatch, and only touch the spec/comment AFTER the user
approves it.

## The directive (user's law, decided 2026-10-25)
On the venue timetable, empty cells past the booking lead time get the classes
`cell-available cell-no-fit` and show the hover label **"Too late to book"**. Today they are painted
GREEN exactly like genuinely bookable cells (only the hover words say otherwise), so users can mistake
them for slots they can still book. They must read as RED / not-bookable:

1. **Resting background** = error red.
2. **Hover label pill** = also red (today's blue pill is the "bookable" affordance).

Keep everything else identical: the label stays the user's two-line text
`'Too Late\A to Book'` (renders "Too Late" / "to Book"; `white-space: pre-line` is already in place),
`cursor: not-allowed`, no
change to booking/click behaviour, no legend change — the venue legend is frozen at 5 chips
(Available · Normal · Replacement · Pending · Conflict / Public Holiday), locked by
`tests/venue-db.spec.ts` and `tests/venue-timetable.spec.ts` TC34/TC35. Do NOT touch
`.cell-too-soon` — that is a different state with its own meaning.

## Exact current state (line numbers drift — re-locate before editing)
- `.cell-available` — `public/css/theme.css` ~:2122 — green resting background; the hover label pill
  (`::after`) uses `--color-primary` (blue).
- `.cell-available.cell-no-fit` — `resources/views/livewire/venue-timetable.blade.php` ~:55–57 —
  page-level CSS: sets the `'Too late to book'` content + `cursor: not-allowed`, **no colour override**
  at all → it inherits the green/blue bookable look. This is where the fix belongs.
- Red-token precedent: `.cell-occupied` in `public/css/theme.css` ~:2205 uses
  `background: var(--color-error-container)`.

## The change (confined to the venue page blade ONLY)
In the venue blade's page CSS block for `.cell-available.cell-no-fit`:
- Resting: `background: var(--color-error-container);` (pull any needed border/text colour from the
  same error token family — tokens only).
- Hover pill: override the `::after` pill the same way `.cell-available` styles it, e.g.
  `.cell-available.cell-no-fit:hover::after { background: var(--color-error); color: var(--color-on-error); }`
  — read `theme.css` first and match the exact selector shape it uses.

No edits to `theme.css`, `ui-common.js`, `mock-data.js` or any partial — the override lives in
`venue-timetable.blade.php`'s page CSS only. No new dependencies.

## Verification (in order)
1. Serve ritual — TWO separate shell commands, never one combined string:
   a) `pkill -f "[a]rtisan serve" || true`
   b) `rm -f storage/framework/views/*.php && php artisan serve --port=8000` (background it)
2. Live check on `/venue-timetable-ui?venue=B006`, future week: a no-fit cell is red at rest AND its
   hover pill is red; a normal free cell is still green with the blue hover pill; occupied cells
   unchanged.
3. Targeted specs — TC70 must NEVER run (always `--grep-invert "TC70"`):
   `npx playwright test tests/venue-book.spec.ts tests/venue-db.spec.ts tests/venue-timetable.spec.ts tests/pages-parity.spec.ts --grep-invert "TC70"`
   `tests/venue-book.spec.ts` T6 locks the two-line label TEXT (`Too Late` / `to Book`) only —
   colours are free.
4. `composer run lint:check` and `vendor/bin/phpstan analyse --memory-limit=1G` (a blade was touched).
   NOTE: `composer run types:check` crashes at the default 128M memory limit — do not use it.

If any test is red for a reason that is NOT this change, REPORT it to the user — do not edit specs
unless the user explicitly approves the spec change first.

## MANDATORY — changelog on EVERY change
Every time you change ANY file, update the matching `page-changelogs/*.md` in the SAME step:
- This task → `page-changelogs/venue-timetable-ui-changelog.md`, a dated entry (`2026-10-25`)
  stating: what changed (no-fit cells now red at rest + red hover pill, which tokens), where
  (venue blade page CSS), and honest receipts (live-probe result + spec/lint/phpstan outcomes,
  including any pre-existing failures). Never claim a green run you did not see.
- If you touch another page's files → that page's matching changelog file too.
- Changelog wording must stay honest to what the page actually does.

## Hard rules
- Only `theme.css` custom-property tokens for colour — never hardcoded hex/rgb.
- Same meaning = same colour everywhere (§10.0 canonical legend/status map).
- Conventional commit (`ui:`), one commit for the task. **Never push** — the user says when.
- Demo DB is read-only: never `migrate:fresh`, never re-seed. Tests use `class_replacement_testing`.
- Report back: files changed, changelog entry added, spec/lint/phpstan results (honest, warts and all).
````
