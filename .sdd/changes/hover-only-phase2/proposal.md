# Phase 2 — the carriers the legend fix did not reach

**Status: DRAFT — owner has chosen the approach, plan not frozen, no code written.** Prepared
2026-11-17 while another chat owned `ui-common.js`, `theme.css` and the replacement blades. Nothing
here is measured against that tree; the inventory is read from source, and the browser numbers are
quoted from the Phase 1 survey (`docs/ui-hover-only-2026-11-17.md`, committed `3bcac14` + correction
`8be270c`).

**Owner decision, 2026-11-17 (recorded 2026-11-17): option A for the badges and the day headers,
option B for the three cohort-count spans and `.result-count`. Option C rejected everywhere.**
The gate now is the tree, not the decision: do not edit `ui-common.js`, `theme.css` or the replacement
blades until the other chat finishes, and do not measure a layout that is still moving.

## 1. What Phase 1 actually fixed

The legend items and the arrangement's disabled-submit reason. Those were the ones with a **visible
promise** attached ("Tap a colour to learn more") and the ones where the tip was the *only* copy of a
meaning. Phase 1 gave them a tab stop, a name, and a 48px floor on coarse pointers. Shipped
`47c5dd8`.

## 2. What is left — the honest inventory

Read from source on 2026-11-17. Every one of these is a `data-tip` with **no focus stop and no
accessible name**, so on touch and with a screen reader the text simply does not exist.

| Group | Carrier | Where | What the tip holds |
|---|---|---|---|
| Day headers | 8 × `<th>` per week view | generated in `ui-common.js:2135` — `if (col.tip) th.setAttribute('data-tip', col.tip)`, the string comes from `col.tip` in `buildTimetableGrid()` | the day's **date and holiday name** — meaning that exists nowhere else in the cell |
| Status badges | `.badge` + `statusTipOf(c)` | `replacement-home.blade.php:622` | *why* the status is what it is |
| Holiday badge | `data-tip` on the badge | `ui-common.js:4021` (`day.holidayLabel`) | the holiday's name |
| Cohort counts | `.cdl-cohort` × 3 sites | `ui-common.js:6334`, `:6355`, `:6913` | the cohort codes behind the count |
| Cancel note | `.cancel-day-note` | `ui-common.js:6439` | the cancelled periods |
| List count | `.result-count` | survey §4 | "12 requests" spelled out where the row says a number |

## 3. Why this is a content decision, not a mechanism decision

Phase 1's mechanism was forced: the legend **says** "tap a colour", so it had to be tappable.
Nothing in this list says that. Two carriers here are already **interactive-adjacent and ruled out**:

- **`<th>` is inside the grid header.** Making 8 headers focus stops puts eight tab stops *before* the
  first slot on every page load, and a header cell is not a control. Nobody tabs to a column heading.
- **A badge is never a touch target** — the task-11 ruling (`my-coding-timetable-rules`, and the
  `.badge` exit from the 1024 block). Giving badges a tab stop and a 48px floor would re-open exactly
  the argument that ruling closed, and would inflate every request row to make room for a pill you
  are not meant to tap.

So the question is not "how do we make these reachable" but **"is this text a tooltip at all"**. Three
options, with what each costs:

### Option A — move the meaning into visible text, delete the tip (recommended for badges + headers)

- Badge: the pill already says *Rejected*; the reason becomes a sentence in the row, or the existing
  reason column carries it. No tooltip, nothing to reach.
- `<th>`: the date/holiday becomes text in the header — the header is already 40px tall and already
  prints the day name.
- **Cost:** screen space, mostly on the phone layouts, where those columns already collapse.
- **Wins:** nothing depends on a gesture at all; the print sheet inherits it for free; no new tab stops.

### Option B — announce it without a tab stop (`role="img"` + `aria-label`, like the legend)

- A screen reader hears the meaning; a sighted finger still cannot.
- Respects "badges are never touch targets".
- **Cost:** partial fix. It converts "unreachable" into "reachable only if you use a screen reader".

### Option C — full parity: tab stop + 48px floor, same as Phase 1

- Consistent with the legend, WCAG 1.4.13 satisfied outright.
- **Cost:** 8 extra tab stops before the grid; badges become 48px targets, contradicting task 11.

**Recommendation: A for badges and `<th>`, B for the three cohort-count spans and `.result-count`**
(where the tooltip is genuinely supplementary detail, not the meaning). That split is what the
evidence supports; it is an owner call, not a reviewer call.

## 3a. Measured 2026-11-17, tree free after `summary-zero-pending` landed — the header row is not a copy problem

The seven `<th data-tip>` carriers are **sortable column headers on the request tables**, not timetable
day headers, and five of them are sortable:

| Fact | Value |
|---|---|
| carriers on `/my-replacements` | **7** (`Course`, `Cohort`, `Original Slot`, `Conflict Reason`, `Requested Slot`, `Request Status`, `Action`) |
| sortable | **5** — each with `addEventListener('click')`, **no** `tabindex`, **no** `role`, **no** `aria-sort`, **no** key handler |
| height | **35px** at 1440 and 1024 |
| keyboard | clicking sorts; **no keyboard path exists**, so sorting is mouse/touch only |
| phone (390) | the table header is **not rendered** (`th` 0×0, table `display: block`) — a 48px floor on a header can never bite there |
| `.sr-only` from Tailwind | **does not reach these pages** — probe span computed `position: static`, 9×20 |

So option A ("move the meaning into visible text, delete `col.tip`") is the wrong tool here: the tip is
a column explanation, and deleting it fixes nothing while **the sort itself stays keyboard-dead**. The
honest fix is to make the header a control (`tabindex`, Enter/Space, `aria-sort` kept in sync by the
same `render()` callback) — which then gives the tip focus parity for free through the existing engine.
That is a behaviour change, not a copy change, so it needs its own go.

**Owner ruling A, 2026-11-17 — done in `75a2c14`.** Sortable headers take `tabindex=0`, `aria-sort` on
the `th` (implicit `columnheader`; `role=button` may not carry `aria-sort`), Enter and Space through the
same toggle as the click, focus ring, and 48px under `(any-pointer: coarse)` — `height`, not
`min-height`, because a `th` is `display: table-cell` and ignores it (measured: computed 48px, box 35).
The two non-sortable headers keep no tab stop and send their explanation through `.sr-only` instead.

## 4. What was measured once the tree freed up

Done, and the answers changed the plan: see §3a. `.badge` is still outside the 1024 block,
`.result-count` still renders, and the phone layout hides the whole request table (so header geometry
is a desktop-only concern).

**Landed, `7b7b09b`:** `.sr-only` in `theme.css`, the result-count explanation as a sibling, and the
four cohort carriers. **Waiting on a decision:** the sortable headers (§3a) and the badge copy (§5).

## 5. Decisions

1. **Settled 2026-11-17: A for badges + day headers, B for the count lines.** C rejected — eight tab
   stops before the first class is worse than no tooltip, and a 48px badge reopens the task-11 ruling.
2. **Open:** who writes the visible "why" sentence on the badge — this lane, or the request-card lane
   that owns the row's copy? Ask before drafting, do not guess.
3. **Open:** the print sheet is a consequence of A, not a separate decision — the day headers print, a
   tooltip does not, so A improves the paper. To be confirmed against a real PDF after the edit, not
   asserted now.

## 6. Explicitly out of scope

`(hover: none)` blocks added by the cohort/venue port (`theme.css:7288` on the other chat's tree) — a
different concern, hover *skins*, not load-bearing copy.
