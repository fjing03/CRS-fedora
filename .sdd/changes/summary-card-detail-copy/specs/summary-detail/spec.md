# Spec — Summary card back spacing and explanation detail

> **Change:** `summary-card-detail-copy`
> **Applies to:** `/my-timetable-ui`, `/student-my-timetable-ui`,
> `/cohort-timetable`, `/venue-timetable-ui`, `/my-replacements`,
> `/request-approval-ui`
> **Re-synced:** 2026-11-14. Each requirement now carries a `[state]` marker:
> `absent` = nothing shipped, `shipped-elsewhere` = present but not by this
> lane, `pre-existing-gap` = a defect this lane documents but does not own.

## Terms

- **Back face:** `.summary-card-back`, the flipped side of a `.summary-card`.
- **Info button:** `.summary-info`, 44×44px, opens the explanation modal.
- **Detail:** the optional `$card['detail']` array (`formula`, `items`, `foot`).
- **Formula strip:** `.explain-formula`, the chip equation at the top of the modal.

## Requirements

### SD-01 — Back spacing  · `[absent]`

The back face's description uses `line-height: 1.45` and `.summary-points`
uses a `4px` row gap. The block is vertically centred within the 96px card.
On every flip card, at both 1360px and 390px, the space above the text and the
space below it differ by no more than 2px.

*Verified 2026-11-14 as the target:* patched pages measure 18px / 18px.
*Current state:* `line-height: 1.15`, `gap: 0`, measured 7px / 48px.

### SD-02 — Icon clearance  · `[absent]`

`.summary-info` is anchored at the **top-right** of the back face and keeps a
44×44px target. The description reserves `38px` on the right on every line.
No rendered text overlaps the button's box, and no text spills below the back
face, at each of the **verified widths 320, 360, 375, 390, 414, 768, 1024 and
1360px**.

320–389px is verified with the summary bar in a single column
(`@media (max-width: 389px)`, placed after the existing `max-width: 768px`
block). 390–768px is two columns; above 768px the existing 3-column and
5-column rules are untouched.

*Verified 2026-11-14 with the patch:* 18/18 above/below, max 3 lines, max spill
−12px, 0 overlaps at all eight widths.

> **`[pre-existing-gap]` the shipped CSS already spills.** Measured today with
> no patch: 320px is **6 lines, spill 0** and Request Approval · Total Reviewed
> reaches **6 lines**. That is shipped behaviour, not caused by this lane — but
> SD-02 must not be read as "the back never spills today". It describes the
> state after this lane's change.

### SD-03 — Three lines on the phone  · `[absent]`

Every card back is at most 3 rendered lines at each verified width. A card
whose copy would exceed that has its copy shortened rather than allowed to
wrap, and that card's full wording is preserved in its `detail` items.

*Verified 2026-11-14:* with the patch + the four shortened bullets, max 3 lines
at 320 / 360 / 375 / 390 / 414 / 768 / 1024 / 1360px.

### SD-04 — Structured explanation  · `[absent]`

A card that supplies `detail` opens the modal showing, in order: an optional
formula strip, then one list item per term, then an optional footnote. Each
list item renders as `term <br> meaning`, with the term bold and the meaning
inside a `<span>` carrying the muted colour. A chip may carry an optional
colour class. A card without `detail` falls back to today's single paragraph.
The modal title is unchanged (the card label).

*Current state verified 2026-11-14:* the modal renders exactly one
`DIV.summary-explanation`, 0 `<br>`, 0 `<li>`, 0 `.explain-formula`.

### SD-05 — Detail content is truthful  · `[absent]`

The formula strip states the **relationship** between terms and never a live
number, because every front value is written at runtime and a literal would be
wrong on any other week (verified again: zero `'value' =>` matches in
`resources/views/livewire/`). Every term, operator and rule in a `detail` entry
must match the page's own arithmetic — no formula may state a rule the page does
not enforce.

**No chip may assert `= Total` on Total Classes.** `computeSummary()` counts
conflict blocks in `total` (it sets `total = evs.length - cancelled`), so that sum is false whenever
a red block exists. See `design.md` §8.

**The strip must not contradict the shipped back bullets.** Those bullets now
state the formula in words; the `items` expand them rather than restating a
different rule (`design.md` §9.1).

### SD-06 — Card geometry preserved  · `[shipped-elsewhere]`

Card height stays 96px, the value colour map is unchanged and every `valueId`
is unchanged. Column widths are unchanged at 390px and above; the single-column
summary bar below 390px required by SD-02 is the only intended exception.

*Current state:* 96px and the value ids are already correct (shipped by the
flip-card lane). **This lane must not change them.** The single-column rule is
still absent.

### SD-07 — Preserved behaviour  · `[shipped-elsewhere]`

Flip on hover / tap / Enter / Space, the info button never flips the card, and
closing the modal restores the card's state and focus. No summary value,
calculation, filter, week navigation or modal-closing path changes.

*Current state:* implemented in `SummaryCards` (`var SummaryCards` in `ui-common.js`). Verified
live: clicking `[data-summary-info]` opens the modal without flipping;
`Escape` closes and restores. **This lane must not regress it.**

### SD-08 — Tokens only  · `[absent]`

Every new colour, radius and border uses an existing `theme.css` custom
property. No new dependency, font or icon library.

## Scenario map

| Scenario | Requirements | State 2026-11-14 |
|---|---|---|
| Desktop, card back | SD-01, SD-02, SD-06 | spacing **absent** |
| Phone 390px, card back (two columns) | SD-01, SD-02, SD-03 | spacing **absent**, 4 bullets wrap |
| Small phone 320–375px, card back (one column) | SD-01, SD-02, SD-03 | single-column rule **absent** |
| A card with a formula | SD-04, SD-05 | modal is one paragraph |
| A card without a formula | SD-04 | n/a — no `detail` exists |
| A card without `detail` | SD-04 | fallback unexercised (see §9.3) |
| Keyboard user opens the explanation | SD-04, SD-07 | SD-07 already works |
| Dark theme modal | SD-08 | n/a — no modal styles yet |
