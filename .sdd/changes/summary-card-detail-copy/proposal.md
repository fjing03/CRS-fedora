# Proposal — Summary card back spacing and explanation detail

> **Status:** active · created 2026-11-13 · **re-synced 2026-11-14**
> **Change:** `summary-card-detail-copy`
> **Reference:** `ui-prototype/summary-card-back-preview.html` (repo parent — not
> inside `CRS-fedora-backend`; earlier drafts wrote it as a repo path, which is wrong)
> **Follows:** `my-timetable-ui-cleanup` (shipped the flip cards this lane adjusts)
> **Prompt:** `ui-prototype/my-timetable-responsive-prompt.md` §9

## Re-sync check — 2026-11-14

This lane sat idle while other lanes shipped summary-card work. Before any
implementation, every premise below was re-read against the current working
tree. **Result: none of this lane's own changes have shipped. All of T1–T8 are
still open.**

| This lane's premise | Current reality (verified) | Still valid? |
|---|---|---|
| Back text too tight | `line-height: 1.15` (13.8px), `.summary-points { gap: 0 }`, `padding-right: 30px`, `justify-content: flex-start`, `.summary-info { bottom: 0 }`, measured **7px above / 48px below** | **yes** |
| Modal is one dense paragraph | Still one `<div class="summary-explanation">` printed from `.summary-explanation-source.innerHTML` (the `body.innerHTML` line in `SummaryCards.open()`); 0 `<br>`, 0 `<li>`, 0 `.explain-formula` on a live page | **yes** |
| Needs a `detail` array | `$card['detail']` appears **nowhere**; the partial reads only `bullets` / `explanation` | **yes** |
| Needs `.explain-*` CSS | `grep explain-` over `public/` and `resources/` = **0 matches** | **yes** |
| Four bullets wrap at 390px | Re-measured with the patch applied: 4 cards over 3 lines | **yes** |
| Sub-390px single column | No `max-width: 389px` rule exists | **yes** |

What *did* change since 2026-11-13, and therefore what this document now says
differently:

- **The per-card back copy shipped from another lane.** All six flip pages now
  pass `bullets` + `explanation` (three short bullets per card, e.g.
  `Normal + blue` / `+N = pending` / `No red blocks`). That was
  `my-timetable-ui-cleanup`, not this lane — but it changes two things here:
  the bullets are **no longer plain text** (most now carry `<strong>`), and the
  back already states the formula *in words*. See `design.md` §3 and the new
  §9.
- **The files this lane would touch have all moved.** `ui-summary-bar.blade.php`
  is now 105 lines with the flip branch and a sibling modal; `SummaryCards` sits
  at ~2200 in `ui-common.js`, not `~1971`.
- **`ui-prototype/` is the correct path**, not `ui-prototype/`.

Everything else in this proposal is unchanged and still describes the work to
be done.

## Why

`my-timetable-ui-cleanup` shipped the 96px flip cards. Two problems surfaced as
soon as they were used on real data:

1. **The back text is too tight.** Three bullets sit at `line-height: 1.15` with
   `.summary-points { gap: 0 }`, so the lines are 1.8px apart. Measured on the
   live pages: the block occupies **7px from the top and leaves 48px below** —
   it is jammed against the top edge with a dead band underneath. The cause is
   the 44×44px `.summary-info` button reserving the bottom-right corner, which
   forced `justify-content: flex-start` and a 30px right reserve.

2. **The explanation modal is one dense paragraph.** A card like Teaching Hours
   opens a single unbroken sentence — `Hours of those classes for the selected
   period (each slot = 30 minutes). Cancelled classes are not counted.` — with
   no formula, no scannable terms and no visual structure. Users asked for the
   detail to "do some work": break the terms onto their own lines, and stop
   relying on one wall of text. Note it shows the **relationship**, not the
   live value — real numbers are written at runtime, so a printed number would
   go stale (see `design.md` §4).

A third, smaller problem: four bullets wrap to a second line at 390px once the
spacing is fixed, because the phone card is only 178px wide and the text column
is 110px. Below 390px the two-column grid cannot hold three lines at all, so
that band needs a single column.

## What

Four changes, all inside the existing flip-card variant. **None are shipped
yet** — see the re-sync table above.

| # | Change | Pages | Status 2026-11-14 |
|---|---|---|---|
| 1 | Back face gets real leading: `line-height: 1.45`, `4px` between bullets, block vertically centred, and the `.summary-info` button moved to the **top-right** so it stops eating the bottom of the text | all six flip-card pages | **not started** |
| 2 | The card back reserves `38px` on every line for that button, and the summary bar drops to one column below 390px so small phones still fit three lines | all six | **not started** |
| 3 | Four bullets shortened so every card is 3 lines at 390px; the full wording stays in the modal. Venue and both timetable pages need no copy change | My Replacements, Request Approval | **not started** — the bullets still ship long |
| 4 | The explanation modal becomes **formula strip → term/more per bullet → optional footnote**, replacing the single paragraph. The strip states the relationship, never a live number | all six | **not started** |

> **What already shipped elsewhere and is *not* this lane's work:** the
> `bullets` + `explanation` data on all six pages, the flip branch in the
> partial, the sibling `#summaryExplainModal`, `.summary-points`,
> `.summary-info` and `SummaryCards`. This lane builds on top of those and must
> not re-create them.

## Scope

### In scope

- `public/css/theme.css` — the `.summary-section--flip` block only: back-face
  leading, bullet gap, the description's right reserve, the back's vertical
  alignment, and `.summary-info`'s corner. These values are still the shipped
  ones (`line-height: 1.15` at line 1771, `padding-right: 30px` at 1772,
  `.summary-points { gap: 0 }` at 1783, `.summary-info { bottom: 0 }` at 1795).
- `public/css/theme.css` — new `.explain-formula`, `.explain-chip`,
  `.explain-list` and `.explain-foot` rules for the modal. **None exist yet
  (0 matches for `explain-`).**
- `resources/views/partials/ui-summary-bar.blade.php` — accept an optional
  `detail` array per card and render the structured markup into
  `.summary-explanation-source`. **Not implemented:** the partial reads only
  `$card['bullets']` and `$card['explanation']`.
- `public/js/ui-common.js` — `SummaryCards.open()` keeps printing the
  source's HTML (the `body.innerHTML` line in `SummaryCards.open()`), so it needs no logic change; only if the
  markup needs a wrapper does it change.
- The six Livewire views that pass `flipCards` — supply `detail` per card; four
  bullets are shortened (three distinct wordings) on two of those pages.
- `page-changelogs/` — one dated entry per touched page.

### Out of scope

- Any summary value, calculation, payload field, filter or `valueId`. (Zero
  `'value' =>` matches still — every front number is written at runtime.)
- Card height (stays 96px) and the 5/3/2-column layout at 390px and above. The
  single-column summary bar below 390px is **in scope** (SD-02).
- The non-flip callers (`replacement-history`) and the `$descriptions` fallback
  map.
- `replacement-arrangement`'s `.sel-summary-card`, a different component.
- Any database, route, dependency or icon-library change.
- **The `bullets` / `explanation` copy that other lanes already shipped.** This
  lane edits four bullets and adds `detail`; it does not rewrite the rest of
  the back copy.

## The class-collision constraint

`$card['class']` **cannot** key the detail data. It is reused with different
meanings:

| class | used for |
|---|---|
| `card-total` | Total Classes (My, Student, Cohort) · Total Requests · **Total Reviewed** · Total Slots |

`request-approval` renders **two** `card-total` cards on one page, so a
class-keyed map cannot tell them apart. Detail must be passed per card, exactly
as `bullets` and `explanation` already are. The existing `$descriptions`
fallback map has this same latent bug; it is out of scope here, but this lane
must not extend it.

## References

- Prototype: `ui-prototype/summary-card-back-preview.html` — six card sets, a
  Current/Proposed switch, and a live readout of lines / space above / space
  below / text under the button.
- Measurements in `design.md` were taken in headless Chromium at 1360px and
  390px.

## Impact

| File | Change | State 2026-11-14 |
|---|---|---|
| `public/css/theme.css` | Flip-card back spacing + modal detail rules | spacing not applied; `.explain-*` absent |
| `resources/views/partials/ui-summary-bar.blade.php` | Optional `detail` array; structured markup | **not present** — only `bullets`/`explanation` |
| `resources/views/livewire/my-timetable.blade.php` | `detail` per card (5 cards) | bullets shipped by another lane; `detail` missing |
| `resources/views/livewire/student-my-timetable.blade.php` | `detail` per card (5 cards, `sum*` ids, "Class Hours") | bullets shipped; `detail` missing |
| `resources/views/livewire/cohort-timetable.blade.php` | `detail` per card (5 cards) | bullets shipped; `detail` missing |
| `resources/views/livewire/venue-timetable.blade.php` | `detail` per card (5 cards, no copy change) | bullets shipped; `detail` missing |
| `resources/views/livewire/replacement-home.blade.php` | `detail` per card + one shortened bullet | bullets shipped; `detail` missing |
| `resources/views/livewire/request-approval.blade.php` | `detail` per card + three shortened bullets | bullets shipped; `detail` missing |
