# Proposal — Class Details stops holding a dead column

**Lane:** `arrangement-class-details-collapse` · **Surface:** Replacement Arrangement (`replacement-arrangement.blade.php`)
**Status:** proposed. Owner chose **B** (hide when empty; relocate the guidance) on 2026-11-17.
Reviewer round 1 raised two blockers (B-1 mechanism unnamed, B-2 pointed the implementer at deleting a line
that must stay); both are resolved below. **Nothing applied.**

---

## 1. The problem, measured

At rest — before a course and a conflict slot are picked — the top zone draws three setup cards plus the
Selection Summary. The middle card, **Class Details**, carries one grey sentence:

> "Select a course and conflict slot to see the class details."

Measured on the live page, no slot selected, both themes identical:

| Width | Top zone columns | Class Details | Replacement Schedule |
|---|---|---|---|
| 1440 | `397 / 248 / 397 / 280` | **248 × 316** | 397 |
| 1280 | `470 / 294 / 411` | **294 × 213** | 411 |
| 1024 | single column | 942 × 66 (fine) | 942 |

At 1440 the card is **248px wide and 316px tall** — about 78 000 px² of surface — holding one sentence,
while the two cards that actually produce a replacement are squeezed. At 1280 it costs **294px**.

## 2. Root cause — one deliberate line, plus one mistake

`replacement-arrangement.blade.php`, `!hasSlot` branch of the details refresh (≈ 5646–5656):

```js
detailsToggle.hidden = true;                      // ← the actual trap: the affordance disappears
topZone.classList.remove('details-collapsed');    // ← KEEP THIS. It is the rest-state reset.
```

**Read precisely.** The `remove('details-collapsed')` call is *not* the bug and **must not be deleted**:
it clears a stale manual collapse. If it went, this reachable sequence breaks the page — the user
collapses Class Details by hand (the toggle handler adds `details-collapsed`), then clears the slot; the
card is hidden but the zone keeps `details-collapsed`, and at ≥1320 the three survivors auto-place into
`8fr / 56px / 8fr` — the **Replacement Schedule renders in a 56px rail**.

The bug is the pair *as a whole*: the card is forced open **and** its toggle is hidden, so an empty card
neither earns its width nor can be closed. Mobile already resolved this the other way —
`body.arrangement-mobile-mode .ra-class-details:not(.has-slot) { display: none; }` (line 2340).

Everything needed for B already exists: `has-slot` is toggled by the existing refresh (5648/5658), and
`:has()` is house style — 20 uses in `theme.css`, one in this blade. **B therefore costs no JavaScript.**

## 3. What must not break

- **The affordance may not vanish with the space.** A hidden card must hold no focusable descendant;
  a *partly* hidden card that keeps its toggle hidden would be a panel nobody can open.
- **The hint may not disappear, and it may not lie.** It is the only place that says what the card will
  show — and it now lives where the card is *not* visible, so its wording matters (§4).
- **The twelve existing grid templates.** AC-7 below forbids new ones, and the mechanism in §4 needs none.
- **Do not regress the ≥1320 fourth column.** The 280px Selection Summary must stay 280px at the right edge.

## 4. The mechanism (measured, not assumed)

Hiding a grid child does **not** close its track — the survivors re-flow into the vacated tracks. Measured
by injecting `#classDetailsCard { display: none }` alone at 1440: the Replacement Schedule slid into the
**248px** track and the Selection Summary into the 397px track, leaving the 280px column empty. That is
worse than the dead space it removes, and it is why AC-7 as first drafted was unsatisfiable.

The fix is **one rule**, not a template per band — let the card that replaces the details *span* the
vacated track:

```css
@media (min-width: 1101px) {
  .toolbar-restructure.ra-top-zone:not(:has(.ra-class-details.has-slot)) #classDetailsCard + section {
    grid-column: 2 / 4;      /* tracks 2–3 in both bands; the 280px summary is track 4 at ≥1320 */
  }
}
```

Measured with that rule plus `display: none` on the empty card:

| Width | Replacement Schedule before → after | Selection Summary | conflict-collapsed variant |
|---|---|---|---|
| 1440 | 397 → **657** | 280, x1119 unchanged | replacement **998**, no scroll |
| 1280 | 411 → **716** | full width below, unchanged | **1130** |
| 1101 | 348 → **609** | full width below, unchanged | **951** |
| 1024 | single column, unchanged | unchanged | unchanged |

`document.documentElement.scrollWidth` is unchanged at every width. **No new template, no new
`conflict-collapsed × details` variant, and it holds in the collapsed-conflict case for free** — `2 / 4`
resolves correctly whether there are three tracks (1101–1319) or four (≥1320).

The recovered space goes to the **Replacement Schedule**, not split evenly between the two live cards. The
Conflict Schedule keeps its width because its controls (course, slot) have a natural size; the schedule is
the card whose content grows.

### The hint — three decisions, made

The Conflict Schedule card has **no empty state today**; the sentence exists once, in the card being
hidden. So this is a **new element in `#conflictCardBody`**, and it must be the same element moved, keeping
`id="detailsEmpty"` — the existing refresh already sets `detailsEmpty.hidden` from `hasSlot`, so the
show/hide lifecycle is inherited, not rebuilt.

1. **Lifecycle:** shown while no slot is chosen, hidden the moment the Class Details card appears. No stale
   instruction under live controls, because the two states are the same condition.
2. **Manual collapse of the Conflict card:** the hint goes with the body. Accepted consciously — the user
   who collapsed it asked for less, and the collapsed summary lines still speak.
3. **Wording:** rewritten, because the current sentence promises a panel that is now invisible at rest:
   **"Class details appear once you pick a course and slot."**

## 5. Acceptance criteria

- **AC-1** At ≥1101px with no slot, Class Details occupies **zero** width; at 1440 the Replacement Schedule
  is ≥650px (measured 657) and the Selection Summary is still 280px at the right edge.
- **AC-2** No horizontal scroll is introduced at 1024 / 1101 / 1280 / 1440 (`scrollWidth` unchanged), and
  the `conflict-collapsed` variant behaves as measured in §4.
- **AC-3** Selecting a slot reveals the card in its current shape with reason/cohorts/students intact;
  clearing the selection returns to the rest state. The `remove('details-collapsed')` line still runs.
- **AC-4** The hidden card contains **no focusable descendant** (nothing tabbable, nothing in the a11y
  tree); when it is shown, `aria-expanded` and both aria-labels are truthful.
- **AC-5** The guidance sentence still exists and still describes what will appear, from its new home in
  the Conflict Schedule card; it disappears exactly when the Class Details card appears.
- **AC-6** Below 1101px the single-column layout is untouched; the mobile-mode hide at line 2340 is
  untouched (and now redundant, which is fine — deleting it is a separate decision).
- **AC-7** **No new grid template.** The four combinations in the 1101–1319 and ≥1320 bands stay as they
  are; the empty state is handled by the single spanning rule in §4. No `theme.css` change.
- **AC-8** Keyboard: Tab from the Conflict Schedule reaches the Replacement Schedule without passing
  through hidden controls, and the print/details flows are unaffected.

## 6. Tasks

1. ~~Decide §4~~ — owner chose **B** (2026-11-17).
2. Scoped CSS: `:not(.has-slot)` hides the card at ≥1101 and the spanning rule from §4. Keep the
   `display:none` pair inside the blade's own `<style>`, not `theme.css`.
3. Move `#detailsEmpty` into `#conflictCardBody` unchanged in id and class; reword per §4.3.
4. Leave `topZone.classList.remove('details-collapsed')` **in place** — it is the rest-state reset.
5. Verify by measurement at 1024 / 1101 / 1280 / 1440 / 768, both themes, plus the `conflict-collapsed`
   variant; keyboard pass per AC-4/AC-8.
6. Changelog entry; receipt in the apply log.

## 7. Risk & reversibility

One file, one scoped `<style>` block, no schema, no dependency, no shared token, no JS change. Reversible
by reverting one commit. The residual risk is the `#classDetailsCard + section` selector's dependence on
document order — a fact of the markup today, and AC-1/AC-2 would catch it breaking.

## 8. Gate

Proposal + one reviewer round complete (blockers resolved in this revision). Needs a confirming round on
the two blockers, then the edit. Not blocked by the Cohort/Venue port: this file is not held.

## 9. Amendment history

**Rev 2 (after reviewer round 1).** B-1: the naive `display:none` was measured and shown to re-flow the
survivors into the vacated tracks (replacement into 248px, summary into 397px, 280px column empty); the
mechanism is now named — one spanning rule — and AC-7 is restated around it. B-2: §2 now says explicitly
that `remove('details-collapsed')` must be **kept**, with the exact sequence that breaks without it. The
hint's lifecycle, wording and manual-collapse behaviour are decided in §4 rather than left open, AC-3/AC-4
are restated for B (the A-only "rail" and "re-opening" checks are gone), and AC-1/AC-2 now carry the
measured numbers instead of the hypothetical 493/493 split.
