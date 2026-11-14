# Design — Summary card back spacing and explanation detail

> **Change:** `summary-card-detail-copy`
> **Frozen inputs:** `proposal.md`, `specs/summary-detail/spec.md`
> **Prototype:** `../ui-prototype/summary-card-back-preview.html`
> **Re-synced:** 2026-11-14 against the current working tree. Status per section
> is stated inline; §9 lists the premises that the other lanes invalidated.

## 0. Status at re-sync — what is real today

Nothing this design proposes has been applied. Verified by reading the shipped
files, not by trusting this document:

| Claim in this design | Verified state 2026-11-14 |
|---|---|
| `line-height: 1.15` | `theme.css:1771` — unchanged |
| `padding-right: 30px` | `theme.css:1772` — unchanged |
| `.summary-points { gap: 0 }` | `theme.css:1783` — unchanged |
| `.summary-info { bottom: 0 }` | `theme.css:1795` — unchanged |
| `justify-content: flex-start` | `theme.css` flip block — unchanged |
| Sub-390 single column | `max-width: 389px` absent |
| `.explain-*` rules | 0 matches in `public/` and `resources/` |
| `detail` array | absent from the partial and all six views |
| Modal is one paragraph | the `body.innerHTML` line in `SummaryCards.open()` prints one `<div class="summary-explanation">` |
| Back copy | **shipped by another lane** — `bullets` + `explanation`, 3 bullets per card, mostly `<strong>` |

## 1. Why the back is tight (measured, not assumed)

Re-measured 2026-11-14 on the live dev pages with the current CSS. The numbers
are unchanged from the original design.

The `.summary-card-back` is 96px tall with `padding: 6px 8px`, so its usable
height is **84px**. Inside it:

| Property | Current value | Consequence |
|---|---|---|
| `.summary-description` `line-height` | `1.15` → **13.8px** per line | three lines are 41px tall |
| `.summary-points` `gap` | `0` | lines are 1.8px apart |
| `.summary-card-back` `justify-content` | `flex-start` | block hugs the top |
| `.summary-description` `padding-right` | `30px` | text stops short of the button |
| `.summary-info` | `bottom: 0; right: 0`, 44×44px | owns the bottom-right corner |

Measured on the live pages at 1360px and 390px:

```
space above the text : 7px
space below the text : 48px
```

So the card is not full — it is **top-jammed with an empty band beneath it**.
That is the reported symptom.

The 44px button is a floor, not a taste: `my-coding-rules.md` rule 9 requires
≥44×44px touch targets (WCAG 2.5.5). It cannot shrink to buy room.

## 2. The chosen fix

Move the button out of the text's way instead of shrinking the text.

```css
/* inside the existing .summary-section--flip block */
.summary-section--flip .summary-card-back          { justify-content: center; padding: 6px 8px; }
.summary-section--flip .summary-card-back
  .summary-description                             { line-height: 1.45; padding-right: 38px; }
.summary-points                                    { gap: 4px; }
.summary-section--flip .summary-info               { top: 0; bottom: auto; }

/* phones below 390px cannot hold two columns of 3 bullets.
   PLACEMENT IS PART OF THE FIX: this must sit AFTER the existing
   `@media (max-width: 768px)` flip block in theme.css. Both selectors are
   `.summary-section--flip .summary-bar` (specificity 0,2,0), so source order
   decides; placed earlier, the 768px 2-column rule wins and the spill returns. */
@media (max-width: 389px) {
    .summary-section--flip .summary-bar { grid-template-columns: 1fr; }
}
```

Verified with a minimal cascade test: 768px-rule-then-389px-rule → `1fr`
(one column); 389px-rule-then-768px-rule → `repeat(2, …)` (broken).

Result at **1360px and 390px**, re-measured 2026-11-14 on the five staff pages
(My, Cohort, Venue, My Replacements, Request Approval), flipped, with the patch
injected:

```
space above the text : 18px
space below the text : 18px
lines per card       : 3
text under the button: 0        (bullet right edge 131px, button left edge 133px -> 2px clear)
```

Student My Timetable was verified separately: 5 flip cards, same CSS, ids
`sum*`. It could not be included in the bulk sweep because it needs the student
login.

### Why the reserve is uniform (38px on every line)

The first attempt reserved 38px only on the two lines level with the button
(`li:nth-child(-n+2)`). That wrapped "Still needs action" on the phone, because
line 1 lost 38px while line 3 kept it. A uniform reserve is both simpler and
safer.

**Corrected sweep, 2026-11-14.** Re-run at 390px over the 25 staff cards with
the shortened copy applied, measuring *whether any single bullet wraps*:

| reserve | bullets that wrap |
|---|---|
| 36px | 0 |
| **38px** | **0** |
| **39px** | **0** — last safe value |
| **40px** | **1** ("Includes completed" on Request Approval · Total Reviewed) |
| 41px | 1 |
| 42px | 1 |
| 44px | 2 (+ "No Sunday/holiday" on Venue · Available) |
| 46px | 2 |

So the safe band is **≤39px, not ≤40px**. The previous text said "30–40 pass,
44 re-wraps" — **40 already fails**. 38px stays the choice because it is 1px
inside the limit and keeps the 2px clearance described below.

Measured with the **unshortened** copy at 38px, these four wrap — which is
exactly the four this lane shortens, confirming the copy change is required and
not cosmetic:

```
My Replacements · Approved    Replacement confirmed   136px
Request Approval · Approved   Replacement confirmed   136px
Request Approval · Rejected   Lecturer must re-arrange 146px
Request Approval · Total Rev. Approved or rejected    120px
```

> **Method note.** An earlier version of this table was non-monotonic (30px→3
> wraps, 38px→4, 39px→0), which is impossible — it came from accumulating
> `<style>` tags across page loads instead of setting one value and forcing a
> reflow. The table above is from a single-value sweep with an explicit reflow;
> it is monotonic and reproducible. Do not re-derive it by injecting a new
> style tag per measurement.

38px is only safe because the back's own `8px` padding adds to it:
text stops at `8 + 38 = 46px` from the card edge, clear of the button's 44px
left edge. A reader who "corrects" 38 → 44 loses that clearance **and** re-wraps
two bullets.

### Why the sub-390px single column is required

Measured 2026-11-14, two columns, patch applied, **shipped (unshortened) copy**.
"Spill" is how far the text runs past the back's inner bottom edge.

| width | card | text column | baseline (shipped CSS) | with this patch, 2 columns |
|---|---|---|---|---|
| 320px | 143px | 75px | 6 lines, spill 0 | **6 lines, spill +14** |
| 360px | 163px | 95px | 5 lines, spill −14 | 5 lines, spill **+5** |
| 375px | 171px | 103px | 4 lines, spill −28 | 5 lines, spill **+5** |
| 390px | 178px | 110px | 4 lines, spill −28 | 4 lines, spill −4 |

**Corrections from the 2026-11-14 re-measurement** (the earlier table was
produced from a narrow-viewport run and drifted):

- 375px's card is **171px** and its column **103px**, not 170/102.
- **375px regresses too (+5)**, not just 320/360. It is inside the `< 390px`
  band, so the single column still covers it — the fix is unchanged, but the
  previous "only two widths regress" claim was wrong.
- The 320px baseline is **6 lines, spill 0** (not "5 lines, −14"), and 360px
  baseline is **5 lines, −14** (not "4 lines, −29").

With `@media (max-width: 389px) { grid-template-columns: 1fr }`, verified
2026-11-14 at **320 / 360 / 375 / 390 / 414 / 768 / 1024 / 1360px** with the
shortened copy across all five staff pages: **max 3 lines, max spill −12px at
every width**, 18px above / 18px below, 0 bullets overlapping the button, one
column below 390px and two at 390px and above.

### Alternative rejected

Growing the card past 96px would fix it with no copy change, but breaks the
approved 96px geometry on all six pages. Rejected by the user.

## 3. Shortened bullets

The text column at 390px is **110px**: description 160 − `padding-right` 38 −
`.summary-points` `padding-left` 12. Re-verified 2026-11-14 across widths:
75 / 95 / 103 / 110 / 122px at 320 / 360 / 375 / 390 / 414px.

Four bullets exceed it. Measured with the patch applied at 390px over the 25
staff cards, exactly these four wrap — **4 wrapping bullets, matching the four
this lane shortens**:

| Page | Card | Before | After | Shipped today |
|---|---|---|---|---|
| My Replacements | Approved Requests | `Replacement confirmed` | `Confirmed` | still long |
| Request Approval | Approved Requests | `Replacement confirmed` | `Confirmed` | still long |
| Request Approval | Rejected Requests | `Lecturer must re-arrange` | `Arrange again` | still long |
| Request Approval | Total Reviewed | `Approved or rejected` | `Approved/rejected` | still long |

The full sentence stays in that card's `detail`, so the modal still says
"Replacement confirmed", "Lecturer must re-arrange" and "Approved or rejected"
verbatim.

**Correction to the earlier premise.** This section used to say "the shipped
bullets are plain text; the prototype shows them bold". That is no longer true
in general: since another lane rewrote the back copy, most shipped bullets now
carry `<strong>` (e.g. `No <strong class="summary-excluded">red</strong> blocks`,
`<strong>30 min</strong> / slot`). It remains true for **the four bullets this
lane shortens specifically** — those are styled in the prototype but plain on
the live pages, so the prototype's measured widths for them run a few px wide.

T5 therefore keeps its instruction: **do not add `<strong>` to the shortened
bullets.** Keeping them plain is the minimal change and preserves the current
visual weight on those two pages. If a follow-up decides every bullet should be
bold, that is a copy-style decision for a separate lane.

**Venue needs no copy change.** `No Sunday/holiday` measures 106px and fits
110px — but only at a reserve ≤39px. At 40px+ it re-wraps (see §2's sweep).
Since Venue's bullet is exactly the one that breaks at 44px, **T1's 38px reserve
must not be raised**.

`my-timetable`, `student-my-timetable` and `cohort-timetable` need **no copy
change** either: measured 0 wrapping bullets at 390px with the patch applied.

## 4. The explanation modal

### Current

`SummaryCards.open()` prints one string. Re-read 2026-11-14:

```js
// SummaryCards.open(), body.innerHTML
var explanation = source ? source.innerHTML : '';
body.innerHTML = '<div class="summary-explanation">' + (explanation || '') + '</div>';
```

Verified live: opening a card produces exactly **one child**,
`DIV.summary-explanation`, with **0 `<br>`, 0 `<li>`, 0 `.explain-formula`**.
That is the paragraph the user complained about, still shipping.

### Proposed markup

```html
<div class="explain-formula">          <!-- optional -->
  <span class="explain-chip is-blue"><b>replacement</b></span>
  <span class="explain-plus">+</span>
  <span class="explain-chip is-pending"><b>pending</b></span>
  <span class="explain-plus">=</span>
  <span class="explain-chip"><b>total</b></span>
</div>
<ul class="explain-list">
  <li><strong>Term</strong><br><span>One line of meaning.</span></li>
</ul>
<p class="explain-foot">Counts the week selected above.</p>   <!-- optional -->
```

### The chips carry no live numbers — the important decision

No view passes `'value' =>` to the partial (verified: zero matches), so every
front number is written at runtime by `computeSummary()`, `updateSummary()` and
their peers. A chip reading "36 slots × 30 min = 18 h" would be a **static
literal** that goes wrong on any other week, and the first prototype draft
contradicted itself (Total Classes chips 6 + 2 + 1 = 9 against a front value
of 6) — that draft is fixed.

So the strip states the **relationship, not the arithmetic**:

- Teaching Hours → `[slots × 30 min] = [teaching time]`
- Total Classes → `[Normal] + [Replacement] + [Pending]` (no `= Total`)

**Do not assert `= Total` on Total Classes.** `computeSummary()` sets
`total = evs.length - cancelled`, so a **conflict** block
is counted in `total` but in none of the three terms. The demo data contains
five conflict events, so `normal + replacement + pending = total` is false
whenever a red block exists. The strip shows the parts; it must not claim the
sum.

This keeps SD-05 holdable and keeps the "no JS change" promise. Putting real
numbers in the chips is a separate JS feature, not a copy change, and is out of
scope for this lane.

A terminal chip that merely repeats the card's own title is dropped — e.g.
Total Reviewed shows `[approved] + [rejected] + [completed]`, not
`… = [reviewed]`, which both repeated the heading and pushed the strip onto a
second line.

### Data shape

`$card['detail']` is optional and additive:

```php
'detail' => [
    'formula' => [                                  // optional
        ['v' => 'slots × 30 min'],                  // term chip
        ['op' => '='],
        ['v' => 'teaching time'],
    ],
    'items' => [                                    // required for the new look
        ['Measured in slots', 'Each timetable slot is <strong>30 minutes</strong>.<br>Two slots make one hour.'],
    ],
    'foot' => 'Same period and filters as Total Classes.',   // optional
],
```

- `'v'` is the chip's label. An optional `'c'` adds a colour class
  (`is-blue`, `is-pending`); without it the chip is neutral.
- `items` is `[term, meaning]`. The partial must emit the meaning inside
  `<span>` — that is the only thing that makes it muted, because `theme.css`
  has no default colour for a bare text node.
- Both halves carry HTML in practice, so both need `{!! !!}`, matching the
  existing `{!! $explanation !!}`.

### Renderer

`SummaryCards.open()` already prints `.summary-explanation-source`'s
`innerHTML` (`SummaryCards.open()`, the `body.innerHTML` line), so the partial emits the new markup
into that hidden node and **no JS change is required**. One renderer, no drift.

Re-confirmed 2026-11-14: the only thing `open()` does with the content is
`body.innerHTML = '<div class="summary-explanation">' + explanation + '</div>'`.
Because `explanation` is already an `innerHTML` string, structured markup
survives intact. The outer `div` is harmless — `.explain-*` rules can be
unscoped, so they still apply inside it.

### Styling scope

`.explain-*` rules must be **unscoped**, or scoped to `.modal--summary`. They
must **not** be written under `.summary-section--flip`: `#summaryExplainModal`
is a *sibling* of the closing `.summary-section` div (`ui-summary-bar.blade.php`
closes the section at line 82 and opens the modal at line 84), so a scoped rule
would style nothing — and the modal would still pass a "content is present"
check, failing silently.

## 5. Where the formulas come from

Every formula must be traceable to the page's own arithmetic. Re-verified
2026-11-14:

| Page | Counted by | Verified |
|---|---|---|
| My / Student / Cohort | `computeSummary(events, days, { splitPending: true })` | `computeSummary()`; `total = evs.length - cancelled` |
| Venue | precomputed `totalsData[week]` from `VenueTimetable.php` (`myHours = count * 0.5`) | `VenueTimetable.php:824` — the page has no `updateSummary()` to read |
| My Replacements | `needs = filtered.filter(c => !isHandled(c))`, hours summed from `c.duration` | read in `replacement-home.blade.php` |
| Request Approval | `reviewed = filter(['Approved','Rejected','Completed'])` | `request-approval.blade.php:717` |

No formula is invented. If a card's arithmetic changes later, its `detail` must
change with it.

## 6. Promoted to shared

Nothing new. The markup stays in `ui-summary-bar.blade.php`, already the shared
partial for six pages, and the styles join the existing flip block and modal
rules in `theme.css`.

## 7. Risks

| Risk | Mitigation |
|---|---|
| A longer bullet wraps at 390px and pushes a card to 4 lines | Re-run the sweep; the prototype reports the line count per card |
| A chip label grows too wide | `.explain-formula` is `flex-wrap: wrap` |
| `detail` forgotten on a new card | Falls back to the `explanation` paragraph — never blank |
| Someone "corrects" 38px back to 44px, or deletes the sub-390 rule | Comments in `theme.css` record the sweep and the 320/360 measurements |
| A chip is later given a live number by hand | §4 states why it must not; SD-05 makes it a requirement |

## 8. Open question for the user (found while verifying SD-05)

`computeSummary()` sets `total = evs.length - cancelled`, so **conflict (`red`)
blocks are counted in Total Classes**, while the shipped bullet says
"No red blocks" and the component parts (`normal + replacement + pending`) do
not include them. On any week with a red block the printed parts therefore do
**not** add up to the card's number.

Re-verified 2026-11-14: the line is still
`let total = evs.length - evs.filter(e => e.status === 'cancelled').length;`
and the conflicts tally still reads
`if (e.status === 'conflict' || e.status === 'cancelled' || (days[e.di] && days[e.di].holiday)) conflict++;`.
The contradiction is unchanged and still pre-existing.

This lane does not change it, because doing so would change a value. The
`= Total` chip has been removed so the modal cannot assert the false sum, but
the underlying contradiction is pre-existing and needs a product decision:
either the card should exclude conflicts (a value change, separate lane), or
its copy should stop saying "No red blocks", or a fourth `conflict` term should
join the strip.

**Sharpened by the re-sync:** the shipped Total Classes bullet *already says*
"No `red` blocks" (in red) and `Normal + blue` / `+N = pending`. So the back
face now states the same false implication the modal would have. If the user
wants the red-block contradiction resolved at all, it is now a **back-copy**
fix as much as a modal one — and that copy belongs to the other lane.

## 9. What the other lanes changed, and what it means here

Three premises this design was written against have moved. Recorded here rather
than silently rewritten in the frozen sections above.

### 9.1 The back copy already exists, and it already states the formula

All six flip pages now pass:

```php
'bullets' => ['<strong class="summary-normal">Normal</strong> + <strong class="summary-replacement">blue</strong>',
              '<strong class="summary-pending">+N</strong> = pending',
              'No <strong class="summary-excluded">red</strong> blocks'],
'explanation' => '<span class="info-keyword">Normal + replacement + pending</span> classes this week. …',
```

Two consequences:

- **The modal's `items` must not contradict the bullets.** The bullets are
  telegraphic (`+N = pending`); the `items` must expand them, not restate a
  different rule.
- **The formula strip is now partly redundant with the back face.** That is
  acceptable — the strip is what the user asked for in the modal — but it
  weakens the argument that the strip "does the work the paragraph didn't",
  because the back face now also does it. Flagged, not blocking.

### 9.2 The bullets are no longer plain text

Most shipped bullets now carry `<strong>`. §3's earlier blanket claim that
"shipped bullets are plain" is corrected there. The four bullets this lane
shortens are still plain, so T5's "add no `<strong>`" instruction stands.

### 9.3 `explanation` is present on every card — the fallback is still unexercised

Every flip card in all six views passes `explanation`, so
`$card['explanation'] ?? $desc` always takes the left branch. `replacement-history`
includes the partial **without** `flipCards` (`replacement-history.blade.php:154`),
so it has no modal and no info button. Therefore **no live page can exercise the
"card without `detail`" fallback**, which is why T7 requires a fixture instead
of a page check. Unchanged from the original design, re-confirmed.
