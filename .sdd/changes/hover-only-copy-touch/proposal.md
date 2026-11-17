# Proposal — P2: say it without naming a gesture

**Lane:** `hover-only-copy-touch` · **Surface:** Replacement Arrangement (legend + disabled-action reasons)
**Status:** proposed, nothing applied. Needs one reviewer round, then the owner's explicit go.
**Topic:** the critique's *"[P2] Load-bearing meanings live in hover, which touch is banned from."*

---

## 1. The problem, and the part of it that is real

Measured on touch emulation (see `docs/ui-hover-only-2026-11-17.md`): the shared `data-tip` engine listens
only to `mouseenter` / `mouseleave` / `mouseout` / `focusin` / `focusout` — no touch path. But tap-to-focus
means **anything focusable already works on a touch screen**. Tested on a real blocked slot: 91 reason tips
in the grid, all on `.cell-content` elements with `tabindex="0"`, all reachable by tap.

So the per-slot reasoning — the hard part — is fine. What is **not** fine is two things:

**(a) The legend names a gesture two of three input modes cannot perform.** All four legend tips are read
on a tablet, and two of them say, in capitals:

> `resources/views/livewire/replacement-arrangement.blade.php:2710`
> "…**HOVER the slot** to see whether it is YOURS …"
> `:2714`
> "CANNOT BOOK — the slot is taken by a scheduled class\n**HOVER the slot** to see WHY: your own class, a
> VENUE CONFLICT …, or a COHORT CONFLICT …"

On touch the gesture is a **tap**; on keyboard it is **focus**. The sentence is correct on exactly one of
the three. It is wrong today, on devices the app already runs on — independent of any engine change.

**(b) Two explanations for an apparently broken control are hover-only.** `:2668` and `:2722`:

```html
<span class="btn-tip" id="submitTip" data-tip="Select a time slot first to enable submission">
<span class="btn-tip" id="clearTip"  data-tip="Nothing to clear yet — select a time slot first">
```

Both wrap a **disabled** button, and `.btn-tip .btn:disabled { pointer-events: none }` (`:500`) — so neither
the button nor its wrapper can take focus. A touch user sees a dead button and no reason; a keyboard user
sees the same, because a disabled control is not focusable. These are the only two carriers in this file
that hold a *statement* (why the control is dead) rather than a detail.

## 2. Scope — copy and two carriers, no engine

**In scope, this lane:**

1. Rewrite the two `HOVER the slot` sentences so they name **no** gesture — true on pointer, touch and
   keyboard alike: *"open the slot's tooltip"*. Keep the capitals, the `\n` line break (the engine renders
   `pre-line`; the code comment above the block says so), and every word of meaning.
2. Make the two `.btn-tip` wrappers focusable (`tabindex="0"`) and give each an `aria-label` equal to its
   tip, so the reason is reachable by tap and by Tab — and actually announced, which `data-tip` alone never
   is. Add a `:focus-visible` ring in this blade's own `<style>` block so the new stop is visible.

**Deliberately out of scope:** the eight `<th>` column-header tips on My Replacements and the badge tips
(`badge-holiday`, `status-rejected`, `badge-outline`). They carry real text and deserve the same treatment,
but their carriers are created in **`ui-common.js`** (`th.setAttribute('data-tip', …)`, line 2135) and in
`replacement-home.blade.php` (line 501) — the first is held by the in-flight Cohort/Venue port. They are
recorded here as **phase 2**, to be done in that file once it is free, or in a follow-up lane.

**Also out of scope:** any touch handler in the engine. Nothing found needs one — the focus parity already
covers every focusable carrier. Adding one would create a dismissal problem for no measured gain.

## 3. Why copy first

Three reasons, in order of weight:

1. **The sentence is already wrong** for keyboard users, today, regardless of touch. A copy fix needs no
   architecture and cannot conflict with the port.
2. **It is one file, one `<style>` block, four strings.** Reversible by one revert.
3. **It tests the hypothesis cheaply.** If gesture-neutral wording reads well, the remaining 13 carriers
   become a mechanical follow-up; if it does not, the wording is where the mistake is, not the mechanism.

## 4. Proposed wording

| Now | After |
|---|---|
| "…\n**HOVER the slot** to see whether it is YOURS …" | "…\n**Open the slot's tooltip** to see whether it is YOURS …" |
| "…\n**HOVER the slot** to see WHY: your own class, …" | "…\n**Open the slot's tooltip** to see WHY: your own class, …" |

"Open" rather than "Tap or focus": it is true on all three modes, it does not name a device, and it does not
read as an instruction that only works on some screens. The alternative — switching the verb by pointer type,
as `theme.css:2261` does with "Tap a colour" — needs a media query per string and still leaves keyboard
users with a verb that does not apply to them.

## 5. Acceptance criteria

- **AC-1** Neither sentence contains the word "hover"; both still explain what the tip reveals, including the
  YOURS / another-staff distinction and the three conflict kinds.
- **AC-2** The `\n` line break survives; both tips still render in the engine's multi-line mode
  (`.data-tip-tooltip--multi`).
- **AC-3** On a 1024 touch context, **tapping** `.btn-tip` shows its tip, and tapping elsewhere hides it.
- **AC-4** By keyboard, Tab reaches both wrappers, shows the tip, and shows a visible focus ring; the
  disabled buttons inside remain non-focusable (no new dead tab stops).
- **AC-5** A screen reader gets the reason: each wrapper's accessible name is its tip text (not empty).
- **AC-6** No change to `ui-common.js`, `theme.css`, or any Livewire class; `scrollWidth` unchanged at
  1024 / 1280 / 1440; the arrangement still arranges.
- **AC-7** The `<th>` and badge carriers are untouched by this lane, and the follow-up is written down (§2).

## 6. Tasks

1. Rewrite the two legend strings (`:2710`, `:2714`).
2. Add `tabindex="0"` and an `aria-label` to `#submitTip` and `#clearTip` (`:2668`, `:2722`).
3. Add a `:focus-visible` ring for `.btn-tip` to this blade's `<style>`.
4. Verify: 1024 touch tap, keyboard Tab, both themes, `scrollWidth`, and that the tooltip still renders
   multi-line.
5. Changelog entry + apply-log receipt; note the phase-2 carriers in the same receipt.

## 7. Risk

Two strings and two attributes in one file, plus three lines of scoped CSS. No schema, no dependency, no
shared file. The only real risk is the `aria-label` duplicating the visible tooltip (an acceptable double
announcement) or the focus ring colliding with `.btn-tip`'s existing inline-flex layout — both caught by
AC-4/AC-5.

## 8. Gate

Proposal only. **Nothing applied, no system code touched.** Needs: one reviewer round against this file,
then the owner's explicit go. Not blocked by the Cohort/Venue port — this is not one of its files.
