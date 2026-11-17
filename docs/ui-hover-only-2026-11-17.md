# P2 — Load-bearing meanings that only hover reveals

**Status: HELD.** Measured on real touch emulation; nothing applied.

**Method.** `hasTouch: true` at 390 / 1024 / 1180 / 1440. Inventoried every rendered `[data-tip]`
carrier, split them by whether the element is focusable, then actually **tapped** one of each kind and
watched the shared tooltip.

---

## 1. What the engine does

`public/js/ui-common.js` `initDataTipTooltips()` listens to **`mouseenter`, `mouseleave`, `mouseout`,
`focusin`, `focusout`** — and nothing else. There is no `touchstart`, no `pointerdown`, no tap path.

But the focus parity added on 2026-11-11 (line 4986) does more than help keyboards: **on a touch screen,
tapping a focusable element focuses it, `focusin` fires, and the tip appears.** Verified — tapping the
theme toggle at 1024 shows its tip, and tapping elsewhere hides it.

So the hole is precise: **tips on elements that cannot take focus are unreachable on touch, and only
those.** That is good news for the fix — the mechanism already works; the gap is the carrier, not the engine.

## 2. What is actually unreachable

Counted at 1024 / 1180 / 1440 (all identical — the table layout is the same at those widths):

| Surface | tips rendered | on non-focusable elements | of those, text > 25 chars |
|---|---|---|---|
| My Replacements | 32 | **18** | 13 |
| Replacement Arrangement | 6 | **6** | 5 |
| Request Approval | 6 | 1 | 1 |

At **390** the same pages show 1–2 carriers, because the phone cards carry the meaning as visible text.
So the exposure is the **tablet-and-touch-laptop band** — iPad landscape, Surface, a touchscreen laptop —
which is exactly the band the drawings never drew and the phone work never covered.

### The ones that matter

**a. Every column header on My Replacements, and the result count.** All eight headers explain what the
column *means*, not just names it:

> "The slot that cannot run as timetabled" · "Where the request stands — the newest request wins" ·
> "Affected cohorts — not sortable: a multi-cohort row has no single value"

`<th>` with a click handler but no `tabindex`, so it cannot be focused and the tip cannot be summoned.

**b. The arrangement legend instructs an impossible action.** All four legend entries tell the reader to
hover:

> "FREE SLOT — CLICK to select…" · "A replacement request for this slot is WAITING for approval **HOVER
> the slot to see** whether …" · "CANNOT BOOK — the slot is taken by a scheduled class **HOVER the slot to
> see WHY**…"

The help text is written for a device the user may not have. And the thing it promises — *why* a slot
cannot be booked — is itself hover-only, so a touch user is told to do something they cannot do, about
information they cannot get.

**c. Two tips are the only explanation for something that looks broken.**

- `.btn-tip` — "Select a time slot first to enable submission" is the *reason a disabled button is
  disabled*, delivered by hover.
- `.badge-outline` — "No request has been made for this class yet — it still needs arranging."
- `.badge.status-rejected` — "The Programme Leader has REJECTED your request."

A touch user sees a dead button or a bare "Rejected" badge and no reason.

**d. Holiday names.** `.badge.badge-holiday` carries "Deepavali Holiday (In Lieu)", "Christmas Eve" — if
the visible badge text is truncated at that width, the name exists only on hover.

**e. None of them has an accessibility fallback.** Of the 25 carriers above, exactly **one** has an
`aria-label`; the rest declare nothing, so the meaning is absent from the accessibility tree as well as
from touch.

## 3. Options

| | What | Fixes | Cost / risk |
|---|---|---|---|
| **A** Promote carriers `tabindex="0"` | The existing focus parity does the rest — no JS | a–d, one span at a time | Adds 13+ tab stops per table to a keyboard order that already has 8 stops before content. Needs to be **selective**, not blanket |
| **B** Teach the engine touch | On `pointerdown`/`pointerup` with `pointerType === 'touch'`, show the tip; dismiss on next tap, scroll or `Escape` | all 25 at once | One change in shared JS; needs a dismissal rule or tips will stick; must not fight the existing file. **Blocked**: `ui-common.js` is held by the Cohort/Venue port |
| **C** Say it in the layout | Rewrite the copy that lies, and move the meaning out of the tip | the worst items (b, c) without any mechanism | Copy + markup only; the legend's "HOVER" is a plain bug on touch regardless of anything else |

**Recommended order: C, then B where the tip is genuinely a detail rather than a statement.**

C is not a workaround — "HOVER the slot to see why" is wrong on a phone *today*, and no amount of engine
work makes that sentence true. The precedent already exists in the codebase: `theme.css:2261` switches
its own copy to **"Tap a colour"** under a coarse pointer. The legend simply never got the same treatment.

## 4. What this does not cover

- Cohort and Venue — held by the in-flight port; re-run there afterwards.
- Screen-reader output: only the presence of `aria-label`/`aria-describedby` was counted, not announced
  text.
- Whether a *specific* separated value (e.g. the exact multi-line conflict reason in the arrangement grid)
  is also visible in a detail modal. Some of it is; that would shrink the list and is worth checking
  before fixing.
- Touch on the phone cards is out of scope by design — they carry their own text.
