# Proposal — P2: say it without naming a gesture

**Lane:** `hover-only-copy-touch` · **Surface:** Replacement Arrangement (legend copy + the disabled-action reason)
**Status:** proposed, nothing applied, no system code touched. Reviewer round 1 raised 3 blockers; rev 2 resolves
them (this file). Needs a confirming round, then the owner's explicit go.
**Topic:** the critique's *"[P2] Load-bearing meanings live in hover, which touch is banned from."*

---

## 1. The problem, and which part of it is real

Measured on touch emulation (`docs/ui-hover-only-2026-11-17.md`): the shared `data-tip` engine listens only
to `mouseenter` / `mouseleave` / `mouseout` / `focusin` / `focusout` — no touch path. But tap-to-focus means
**anything focusable already works on a touch screen**. Tested on a real blocked slot: 91 reason tips in the
grid, all on `.cell-content[tabindex="0"]` (set in this blade, `:4123`, `:4176`, `:4216`), all tap-reachable.

So the per-slot reasoning — the hard part — is fine. Two things are not:

**(a) The legend names a gesture that two of three input modes cannot perform.** Two of the four legend tips
say, in capitals:

> `resources/views/livewire/replacement-arrangement.blade.php:2710` — "…**HOVER the slot** to see whether it
> is YOURS …"
> `:2714` — "CANNOT BOOK — the slot is taken by a scheduled class\n**HOVER the slot** to see WHY: your own
> class, a VENUE CONFLICT …, or a COHORT CONFLICT …"

On touch the gesture is a **tap**; on a keyboard it is **focus**. The sentence is correct on exactly one of
the three, and wrong today on devices the app already runs on.

**(b) The reason a dead button is dead is hover-only.** `:2668`:

```html
<span class="btn-tip" id="submitTip" data-tip="Select a time slot first to enable submission">
```

`.btn-tip .btn:disabled { pointer-events: none }` (`:500`) removes the only pointer target inside it, and a
disabled button cannot take focus, so neither the button nor its wrapper is reachable by tap or Tab. A touch
user — and a keyboard user — sees a dead button and no reason.

**Corrections from review round 1, now reflected here:**

- It is **one** live carrier, not two. `#clearTip` (`:2722`) sits inside a Blade comment (`{{-- Clear ALL is
  COMMENTED OUT … --}}`, since 2026-11-10, returning with multi-block selection). It is not in the DOM and is
  out of scope; the existing `updateCounter` code already null-guards it.
- `#submitTip`'s tip is **state-aware**: `updateCounter()` (`:3484`) rewrites it between *"Select a time slot
  first to enable submission"* (empty) and *"Send your replacement request for approval"* (a slot is selected),
  and the same call toggles `#submitRequestBtn.disabled` (`:3479`). So the wrapper only carries a *reason*
  while the button is disabled; with a selection it carries an *action*.

## 2. Scope — copy, two attributes, one sync. No engine.

**In scope:**

1. **Legend copy.** Rewrite the two `HOVER the slot` sentences to name no gesture: *"Open the slot's tooltip
   to see …"*. **Keep the emphasis capital on the verb** — `OPEN` — because the rest of the tip capitalises
   its load-bearing nouns (YOURS, CANNOT BOOK, VENUE CONFLICT) and the blade's own comment says "Capitals do
   the emphasis". Keep the `\n` line break (the engine renders `pre-line`) and every word of meaning.
2. **`#submitTip` becomes reachable.** Give the wrapper a `:focus-visible` ring (this blade's own `<style>`),
   and make it focusable **only while its button is disabled** — `tabindex="0"` plus an `aria-label` set
   inside `updateCounter()`, **keyed off the button's actual `disabled` state — not off whether a slot is
   selected**. That distinction is load-bearing: `:3479` disables Submit on `!has || mobileSubmissionInFlight`,
   so a slot can be selected while Submit stays disabled (the T18 mobile-origin latch, released only by
   `releaseMobileSubmissionLatch()`). Keying off `has` would strand exactly that state. The announced name
   tracks the visible tip in every state, and the wrapper is a tab stop only while the button is genuinely
   disabled — an always-focusable wrapper around an *enabled* button is a dead tab stop (Enter does nothing).

**Out of scope, recorded as phase 2:** the eight `<th>` header tips on My Replacements, and the three badge
tips (`badge-holiday`, `status-rejected`, `badge-outline`). They hold real text and deserve the same
treatment, but their carriers are created in `ui-common.js` (`th.setAttribute('data-tip', …)`, `:2135`;
`statusTip` `:4052`/`:4072`; `reasonTip` `:4169`) and in `replacement-home.blade.php:501` — and the first is
held by the in-flight Cohort/Venue port.

**Out of scope, on principle:** any touch handler in the engine. Nothing found needs one; the focus parity
already serves every focusable carrier, and a touch path would introduce a dismissal problem for no measured
gain.

## 3. Cost, restated after review

One blade file. Two strings, one `tabindex` + `aria-label` pair maintained in `updateCounter()`, and about
three lines of scoped CSS for the focus ring. **The earlier "four strings" estimate was wrong** — one of the
two wrappers does not exist and the other needs a state sync, so this is slightly more than copy and slightly
less than a component.

## 4. Proposed wording

| Now | After |
|---|---|
| "…\n**HOVER the slot** to see whether it is YOURS …" | "…\n**OPEN the slot's tooltip** to see whether it is YOURS …" |
| "…\n**HOVER the slot** to see WHY: your own class, …" | "…\n**OPEN the slot's tooltip** to see WHY: your own class, …" |

"Open" rather than "Tap or focus": true on all three modes, names no device, and does not read as an
instruction that only works on some screens. Switching the verb by pointer type (as `theme.css:2261` does
with "Tap a colour") needs a media query per string and still leaves keyboard users with a verb that does not
apply to them. If the owner finds "tooltip" jargon-y, the fallback is the descriptive *"the slot's tooltip
explains why"* — not a per-pointer variant.

## 5. Acceptance criteria

- **AC-1** Neither legend sentence contains the word "hover" in any case; both still explain what the tip
  reveals, including the YOURS / another-staff distinction and the three conflict kinds.
- **AC-2** The `\n` survives; both tips render in the engine's multi-line mode (`.data-tip-tooltip--multi`).
- **AC-3** At a 1024 touch context, **tapping** `#submitTip` while Submit is disabled shows its tip, and
  tapping a target that is **not focusable** hides it (hiding works because focus drops to `body` — a
  focusable neighbour would give a false pass).
- **AC-4** By keyboard, Tab reaches the wrapper **while Submit is disabled** and shows the tip with a visible
  focus ring; once a slot is selected the wrapper is **not** a tab stop (no dead stop in front of an enabled
  button). `#clearTip` is absent from the DOM and is not tested.
- **AC-5** The reason is announced, not just shown: the wrapper's accessible name equals its **current** tip
  text in every state — empty ("Select a time slot first…"), selected ("Send your replacement request…"), and
  **selected-but-latched**, where Submit is still disabled and the tip already reads the action string.
- **AC-6** No change to `ui-common.js`, `theme.css`, or any Livewire class; `scrollWidth` unchanged at
  1024 / 1280 / 1440; the arrangement still arranges and still submits.
- **AC-7** Phase 2 carriers (8 `<th>` tips, 3 badge tips) are untouched by this lane and named in the receipt.

## 6. Tasks

1. ~~Decide the approach~~ — owner picked copy-first (2026-11-17); approval to apply was given, then withdrawn.
2. Rewrite the two legend strings (`:2710`, `:2714`).
3. `#submitTip`: `tabindex` + `aria-label` maintained inside `updateCounter()`, both branches, keyed off
   `#submitRequestBtn.disabled` (so the mobile-origin latch state is covered); the tab stop is removed only
   when the button is genuinely enabled.
4. `:focus-visible` ring for `.btn-tip` in this blade's `<style>`.
5. Verify: 1024 touch tap (focusable and non-focusable dismissal targets), keyboard Tab in both states, both
   themes, `scrollWidth`, multi-line tip, submit still works.
6. Changelog entry + apply-log receipt naming the phase-2 carriers.

## 7. Risk

Two strings, two attributes maintained in one existing function, three lines of scoped CSS — one file, one
revert. The real risk is the state sync: a `tabindex` or `aria-label` that only updates on selection but not
on clear would leave a stale reason, and keying the sync off `has` instead of `btn.disabled` would strand the
selected-but-latched state. AC-4/AC-5 test both transitions and that third state; `updateCounter` is the single
gate that already owns `disabled` (`:3479`) and the tip text (`:3484`), so there is one place to be right.

## 8. Gate

Proposal + round 1 review complete (3 blockers, resolved in this revision). Needs: a confirming round, then the
owner's explicit go.

**Recorded open question** (`sdd.yaml`): whether phase 1 lands under the **small-change exemption** (no
proposal needed) or inside this lane. This lane exists because the work turned out to be more than copy — a
state-aware `tabindex`/`aria-label` pair is behaviour, not wording — so the lane is the honest home. If the
owner prefers the exemption for the two strings alone, the carrier work still needs a lane, which is this one.
