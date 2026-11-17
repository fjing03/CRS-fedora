# Proposal — Class Details stops holding a dead column

**Lane:** `arrangement-class-details-collapse` · **Surface:** Replacement Arrangement (`replacement-arrangement.blade.php`)
**Status:** proposed. Owner chose **B** (hide when empty, relocate the hint) 2026-11-17. Awaiting one reviewer round before the edit.

---

## 1. The problem, measured

At rest — before a course and a conflict slot are picked — the top zone draws three setup cards plus
the Selection Summary. The middle card, **Class Details**, carries one grey sentence:

> "Select a course and conflict slot to see the class details."

Measured on the live page, no slot selected, both themes identical:

| Width | Top zone | Columns | Class Details | One live column |
|---|---|---|---|---|
| 1440 | 1392 × 342 | `397 / 248 / 397 / 280` | **248 × 316** | 397 |
| 1280 | 1232 × 525 | `470 / 294 / 411` | **294 × 213** | 470 / 411 |
| 1024 | 976 × 751 | single column | 942 × 66 (fine) | — |

So at 1440 the card is **248px wide and 316px tall** — roughly 78 000 px² of surface — holding one
sentence, while the two cards that actually produce a replacement are squeezed into 397px each. Forcing
`details-collapsed` by hand returns the width: the two live columns go to **493px each** and the card
shrinks to a 56px rail.

**The measured cost: 248px at 1440, 294px at 1280** — and about 96px of extra working width for each
neighbour once it's gone.

## 2. Root cause — it is one line, and it is deliberate

`replacement-arrangement.blade.php`, in the `!hasSlot` branch of the details refresh (≈ line 5656):

```js
topZone.classList.remove('details-collapsed');   // forced OPEN whenever there is no slot
```

Everything needed for the alternative already exists and is proven: `.details-collapsed` grid rules at
≥1101px, the 56px rail, the chevron rotation, the `aria-expanded` contract, and a working toggle
handler. The card is *told* to open when it has nothing to say.

The second half of the trap: in the same branch, `detailsToggle.hidden = true`. So the card cannot be
closed by the user either — the affordance is removed exactly when the dead space appears.

Mobile already solves this differently: `body.arrangement-mobile-mode .ra-class-details:not(.has-slot)
{ display: none; }`. Two patterns for one state.

## 3. What must not break

- **The affordance may not vanish with the space.** Collapsing to a rail while hiding the toggle would
  leave a closed card the user cannot open. Whatever ships, an empty card keeps a visible, focusable
  control.
- **The hint may not simply disappear.** It is the only place that tells a new lecturer what the card
  will show. Removing it trades dead pixels for a mystery panel that appears later.
- **The twelve grid templates.** `details-collapsed` is defined at ≥1101 and again at ≥1320, and the
  conflict-collapsed variants multiply the combinations. A change that adds a thirteenth template makes
  the next reader's day worse; the fix should *reuse* the existing ones.
- **Do not regress the ≥1320 fourth column.** The 280px column is the Selection Summary; the collapse
  rules already account for it in all four combinations.

## 4. Options — the owner picks

| | What it does | Recovers | Keeps the hint? | Cost |
|---|---|---|---|---|
| **A. Boot collapsed** | The card rests as a 56px rail with the chevron visible; the sentence moves into the rail's tooltip | 248 / 294px | yes, on hover/focus | small; rail must stay ≥24px and focusable |
| **B. Hide when empty, like mobile** | `:not(.has-slot)` → `display: none` at every width; the hint moves into the Conflict Schedule card's empty state | 248 / 294px, plus one card | yes, relocated | one pattern instead of two, one hint instead of three |
| **C. Repurpose the column** | Keep the width, fill it with the legend, slot rules and keyboard help | nothing | yes, expanded | most work, no width gain; best only if the panel earns its keep |

**Recommendation: B.** It matches what the mobile layout already decided, it deletes a pattern rather
than adding one, and it removes a card instead of shrinking it — the strongest version of "the dead
space is gone". A is the conservative fallback if the owner wants the card to remain visible as a
promise. C is a different task and should not be smuggled in here.

## 5. Acceptance criteria

- **AC-1** At ≥1101px with no slot, the Class Details card does not occupy working width beyond a 56px
  rail (A) or is absent (B). Measured, not eyeballed, at 1280 and 1440.
- **AC-2** The two live columns gain the recovered width; no horizontal scroll is introduced
  (`document.documentElement.scrollWidth` unchanged).
- **AC-3** Selecting a slot reveals the card in its current shape, with the reason/cohorts/students
  facts intact; clearing the selection returns to the rest state without the next refresh re-opening it.
- **AC-4** Whatever remains is reachable and operable by keyboard: a visible focus ring, `aria-expanded`
  truthful, and no control that is hidden while claiming to be available.
- **AC-5** The guidance sentence still exists somewhere a first-time user will meet it.
- **AC-6** Below 1101px the single-column layout is untouched; mobile mode's existing hide is untouched.
- **AC-7** No new grid template beyond the four documented combinations; no `theme.css` change.

## 6. Tasks

1. ~~Decide §4~~ — owner chose **B** (2026-11-17).
2. Change the `!hasSlot` branch: stop forcing the card open; set the rest state for the chosen option.
3. Keep or move the hint per AC-5; wire the rail tooltip if option A.
4. Verify by measurement at 1024 / 1280 / 1440 / 768, both themes; keyboard pass on the rail or the
   relocated hint.
5. Changelog entry; receipt in the apply log.

## 7. Risk & reversibility

One file, no schema, no dependency, no shared token. Reversible by reverting one commit. The real risk
is the grid-template combinatorics (§3), which is why AC-7 exists.

## 8. Gate

Proposal only — **nothing applied**. Needs: the owner's choice in §4, then one reviewer round against
this proposal, then the edit. Not blocked by the Cohort/Venue port: this file is not held.

---

## 9. Amendment — what the experiment found before the edit

**Owner chose B (2026-11-17).** B is not free. Measured on the live page by injecting
`#classDetailsCard { display: none }` and nothing else:

| 1440 | conflict | details | replacement | summary |
|---|---|---|---|---|
| resting | x41 w397 | x450 w248 | x727 | x1119 w280 |
| card hidden, zone untouched | x41 w397 | — | **x467, in the 248px track** | **x710 w397 — the summary ate the third track** |

At 1280 the same: hiding the card drops the Replacement Schedule into the 294px track. **`display: none`
removes the child, and the grid then re-flows the remaining cards into the vacated tracks** — the
replacement controls end up in the narrow column, which is worse than the dead space we set out to
remove. A span hacks the summary back but leaves the replacement card at 248px.

### The consequence for AC-7

B therefore needs **one extra template per band**, keyed on a state it can already see:

```css
.toolbar-restructure.ra-top-zone:not(:has(.ra-class-details.has-slot)) { … }        /* ≥1101–1319: 8fr 7fr */
.toolbar-restructure.ra-top-zone:not(:has(.ra-class-details.has-slot)) { … }        /* ≥1320: 8fr 8fr 280px */
```

`:has()` is already house style — 20 uses in `theme.css`, one in this very blade — so this costs **no
JavaScript change at all**. `has-slot` is already toggled by the existing refresh.

**AC-7 is restated:** at most **one** additional template per breakpoint band, never a new variant of
the `conflict-collapsed` × `details-collapsed` matrix. Two bands, two rules, no combinations.

### The hint

The element moves — same `id="detailsEmpty"`, same class — from the Class Details card into the
Conflict Schedule card's body. The existing refresh already sets `detailsEmpty.hidden` from `hasSlot`,
so the sentence automatically shows while no slot is chosen and disappears when the Class Details card
appears. One element, one home, no duplicate and no orphan, and no JS touched.

### Not changed by this amendment

Options A and C remain as described. AC-1's "56px rail" clause applies only to A; for B the assertion is
that the card occupies **zero** width and the neighbours take the recovered space — which is what the
table above now measures.
