# Design — holiday-occurrence-badge

## D1. Where the attribute rides (the REAL markup)

The strip renders via one `el.innerHTML` template; the occurrence segment
ALREADY exists in it (blade ~:2600–2606):

```
<div class="cs-seg">
    …clock svg…
    ${slotLine
        ? `<span class="cs-val">${slotLine}</span>`
        : `<span class="cs-val cs-none">Not selected…`}
</div>
```

The attribute rides the EXISTING `<span class="cs-val">` inside that
ternary — no new wrapper (a `div.cs-line` wrapper / extra span would
change styling — a visible strip change that violates the frozen
contract):

```
const holidayTip = (function () {
    const hs = (window.MockData && MockData.holidays) || [];
    if (!hasSlot || !anchorSlot) return null;
    const hit = hs.find(h => Number(h.week) === anchorSlot.week + 1
        && Number(h.dayIndex) === anchorSlot.day);
    if (!hit) return null;
    const label = String(hit.label || '');
    const stem = /holiday/i.test(label) ? label : label + ' holiday';
    return '\u00B7 ' + stem + ' \u2014 no class';
})();
// the SAME existing ternary, one attribute richer when the tip exists:
${slotLine
    ? `<span class="cs-val"${holidayTip
        ? ` data-tip="${escHtml(holidayTip)}"` : ''}>${slotLine}</span>`
    : `<span class="cs-val cs-none">Not selected…`}
```

- The visible text is byte-identical pre/post: only the attribute
  changes in the HTML string — `textContent` identical, the `.cs-val`
  styling unchanged. The strip already carries a `data-tip` through
  this exact innerHTML (the `cs-badge` element — the delegation
  witness).
- **`escapeAttr` = the page's existing global `escHtml`** (ui-common.js
  :1327–1329 — `& < > "` escape, already proven as a `data-tip` attr
  escaper at ui-common.js :2042). Reuse it; do NOT declare a local
  duplicate (the reuse standing rule). Holiday labels are user-facing
  data, never raw-interpolated.
- **No `data-tip-pos` attribute** — the tooltip layer only reads
  `'left'`; other values are ignored, and the DEFAULT (above, auto-flip
  below) is what the strip wants. Omitted, not defaulted.
- The literal law: `· {label}[ holiday] — no class` — the ` holiday`
  suffix appended ONLY when the label doesn't already carry "holiday"
  case-insensitive: `Christmas Eve` → `· Christmas Eve holiday — no
  class`; `Deepavali Holiday (In Lieu)` → `· Deepavali Holiday (In
  Lieu) — no class`. First `(week, dayIndex)` match wins. Glyphs
  assemble via `\u00B7` / `\u2014` (the page's em-dash convention).

## D2. Re-derivation

`renderTitleSummary()` runs on every anchor change; the attribute is
computed INSIDE the same render pass (no post-walk, no extra observers).
The dynamic-tooltip layer (`initDataTipTooltips()`) binds via
document-level delegation (mouseenter capture) so re-rendered
attributes work with zero wiring; known acceptance edge: a pointer
ALREADY resting on the strip during re-render shows no fresh tip until
the next mouse move — stated as accepted, receipted in verify.

## D3. Receipts (falsifiable set)

- W14 Thu anchor (`?session=38&week=14`): hover occurrence line →
  `· Christmas Eve holiday — no class`.
- W14 Fri anchor: `· Christmas Day holiday — no class`. The anchor URL
  alone resolves the pinned pair (`anchorSlotFromPinned()` reads
  `PAGE_DATA.pinnedSession.week` + `urlParams.day/start/originalVenue`,
  independent of the conflict list) — the actual session/day
  combination is picked AT APPLY TIME from the live DB (the same
  discipline the W8 receipt uses; the importer dropped cs 39/41's W14
  rows, so the list can't hand off — the URL is the receipt vehicle).
- W8 Mon anchor (a Monday source session — session id picked at apply
  time from the live DB, day 0 classes exist e.g. cs 71):
  `· Deepavali Holiday (In Lieu) — no class`.
- A non-holiday anchor (Week 7): no `data-tip`.
- `#subjectInfo` `textContent` identical pre/post (the mechanical
  visible-unchanged receipt).
- The full suite unchanged (client-only); NO JS lint claim is made for
  inline template JavaScript — correctness rides the Playwright +
  `textContent` identity receipts (the browser is the only honest
  validator of inline JS).

## D4. Frozen rejects

- No icon/emoji/prefix on the visible line (user ruling).
- No `?week=` selector move (user ruling; `ARRIVAL-GRID-NO-PIN` intact).
- No server rule change (`validateSlot` holiday rejection stays).
