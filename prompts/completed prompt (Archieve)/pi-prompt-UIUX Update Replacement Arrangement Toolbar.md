# UI/UX Update: Replacement Arrangement Toolbar — hover tooltips

Page: `/replacement-arrangement` — the `.toolbar.toolbar-restructure`
element (already exists in the markup).
Scope ruling (user, 2026-10-31): **all three items are HOVER TOOLTIPS**
(`data-tip` attributes on the existing elements) — the VISIBLE texts
(`#slotTriggerText`'s "W7 · Nov 06 · …", the week select, the subject
select) stay unchanged. Reuse the existing tooltip design/positioning
(shared `data-tip` → `.data-tip-tooltip`, shown ABOVE the element,
`.data-tip-tooltip--multi` for `\n` lines).

## a) Slot picker trigger tooltip

Element: `<span id="slotTriggerText">` (the trigger of
`#slotPicker`, ~:1014).

Add a hover tooltip showing the CONFLICTED slot's full details,
dynamically (all values from the actual selected slot — never hardcoded):

```text
Conflicted SLOT Picker : ({TYPE}) Week {N}, {Day}, {Date}, {Time Range} @{Venue}
```

Example:

```text
Conflicted SLOT Picker : (L) Week 14, Thursday, 24 Dec 2026, 9:00 AM - 11:00 AM @ B100
```

Notes:
- `{TYPE}` = the slot's session type letter in CAPS as parenthesised —
  (L)/(P)/(T) — sourced from the slot's real type field (honest value;
  do not force a letter — this is adjacent to the deferred Bug-2
  type-letter lore, so report what the data actually yields).
- `{Time Range}` format: `9:00 AM - 11:00 AM` (spaces around the hyphen,
  per the example). Date format: `24 Dec 2026`. Venue: CODE only (`@ B100`).
- Normalisation: the example's stray space in "Thursday , 24" is a typo —
  render "Thursday, 24 Dec 2026".
- The tooltip must refresh whenever the selected conflicted slot changes
  (the trigger text is written at ~5 JS sites — all of them must refresh
  the tip too) and clear/degrade honestly when no slot is selected.

## b) Week selector tooltip

Element: `#weekSelector` (the `<select>` inside
`partials.ui-week-nav`, included at ~:1038).

The partial ALREADY supports this via its `selectTip` parameter
(`data-tip` on the select) — pass it:

```text
Select the WEEK that you want to arrange the replacement in
```

(Wording ruled by the user, 2026-10-31. Static tip — same for every week.
`prevTip`/`nextTip` for the arrows stay untouched.)

## c) Subject selector tooltip

Element: `<select id="subjectSelector">` (~:1007).

```text
Conflicted SUBJECT Selector : {Code} - {Name}
```

Example:

```text
Conflicted SUBJECT Selector : AMSE1003 - Software Engineering
```

Notes:
- Dynamic: reflects the CURRENTLY SELECTED subject (code + full name from
  the page's subject data); refresh on every `onSubjectChange()`.
- Empty state (no subject yet): degrade honestly — the prefix alone
  (`Conflicted SUBJECT Selector :`) or no tip; decide in the proposal.

## Requirements

- Populate all values dynamically from real page data; never hardcode
  example values.
- Reuse the existing tooltip design and positioning; no new tooltip
  mechanism or CSS.
- Preserve all existing behaviour: slot picker open/close + selection,
  week navigation, subject switching, booking flow — copy/attributes only.
- Do not modify unrelated logic (the page carries an uncommitted
  `submission-conflict-refresh` hunk — leave it alone).
- Test with Playwright after apply.
- SDD path: user-judged light path pending (see the change's proposal).
