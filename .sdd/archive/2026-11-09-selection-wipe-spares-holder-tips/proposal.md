# Proposal — selection-wipe-spares-holder-tips

## Bug (user-reported 2026-10-28, staff 5425)

On `/replacement-arrangement`, after selecting ANY free cell (a block) in
the SAME week, **every holder tooltip on that week's grid loses its
`data-tip`** — hovered occupied cells show nothing (the tooltip layer
finds no attribute), while `data-tip-pos`, `aria-label` and `tabindex`
survive. Other weeks are unaffected (their grid rebuilt through
`buildTimetable()` re-attaches tips fresh). Every new selection/clear
repeats the wipe.

## Root cause (verified — Playwright receipts + code walk)

`clearMergedBlock()` (blade :1542) wipes tips for the has-selection
affordance — the "Clear your selection first — click it to remove" hints
its setter (:1538–1540) puts ONLY on `.cell-content.cell-available`. But
the cleaner's selector is domain-blind:

```js
body.querySelectorAll('.cell-content[data-tip]').forEach(c => c.removeAttribute('data-tip'));
```

`.cell-content[data-tip]` matches EVERY tipped cell in the current
week's body — occupied/pending holder tips (engine arm AND the
occupied-cell-explanations grid arm), the too-soon etc. The wipe runs on
every selection commit (`renderMergedBlock()` calls
`clearMergedBlock()` FIRST) and on every deselect path.

Reproduction receipts (blob of one probe run): before = 56 tipped /
0 orphans; after ONE green-cell click = 58 orphans
(`data-tip-pos` present, `data-tip` gone, aria intact); another week =
0 orphans.

## The fix (one selector — the cleaner's domain = the setter's domain)

```js
body.querySelectorAll('.cell-content.cell-available[data-tip]')...
```

- The hints only ever land on `.cell-available` cells — the cleaner now
  strips exactly what was set, and holder tips survive selections.
- No other file changes; no data change; no CSS change.

## Guard (prevent recurrence)

A Playwright assertion in the lane's receipt probes (and a repo-checkable
artifact): after a selection commit, at least one `.cell-occupied` cell
must STILL carry a `data-tip` (the occupied-cell-explanations contract
holds under selection state). `node --check` for the touched inline-JS
via the browser receipt (php -l for PHP).

## Out of scope

- The BUG-01/06 family (stale trigger label / counter) — untouched.
- No changes to the hint copy, the has-selection affordance CSS, the
  preview layer, the selection state machine, or any server code.
