# Design — lh-l-only-revert

## D1 — the law row (ruleMap)
```php
'P' => ['lab', 'cisco_lab'],
'T' => ['tutorial'],
'L' => ['tutorial', 'lecture_hall'],
```
(the L row unchanged; the 2026-10-19 note in the docs re-dated).

## D2 — the wording helper's split (ui-common.js)
```js
function venueTypeWording(rawType) {
    const set = ({ lab: 'Practical (P)', cisco_lab: 'Practical (P)',
                   lecture_hall: 'Lecture (L)', tutorial: 'Tutorial (L/T)' });
    return rawType != null && set[rawType] != null ? set[rawType] : '';
}
```
- The `only` suffix rule branches on the raw type (page-side, because the
  helper is UI-common-only):
  - lab / cisco → 'hosts Practical (P) sessions only.'
  - lecture_hall → 'hosts Lecture (L) sessions only.'
  - tutorial (dual L/T) → 'hosts Tutorial (L/T) sessions.'
- **The wrappers live OUTSIDE the channel T1's PHP carries**: the
  venue-timetable page's own hosts line (blade :404's wrapper) and the
  arrangement page's `requiredTypeHuman`/zero-fit line EACH branch their
  'only' suffix on the raw type — the helper supplies the phrase; the
  'only' appends PAGE-side keyed on `rawType ∈ {lab, cisco_lab,
  lecture_hall}` (T-tutorials stay 'only'-free).
- `LectureSessionCatalogTest`'s pinned T row retunes (the designed
  contract change noted in tasks T1).

## D3 — the test retune
- The `test_engine_admits_l_and_t...` → retitled + retuned:
  L admits tutorials+halls; T admits tutorials ONLY; P stays labs.
- The producer receipt `sessionAllowedForVenue('T','lecture_hall', …)`
  asserts FALSE now (the polarity flips; the "data-not-law" wording stays
  but about the L case).
- The `T` row in the trailing docs keeps the 2026-10-19 note as the GONE
  delta (the user's follow-up on 2026-10-20).

## D4 — the docs' R1 rows
```
| `T` (Tutorial)  | `tutorial` |
| `L` (Lecture)   | `tutorial`, `lecture_hall` |
```
+ a 2026-10-20 dated note (this ruling).

## D5 — the changelog wording
`replacement-arrangement` + `venue-timetable-ui` + `venue-dropdown`: the
T-in-LH narrowbacks; the dd copy splits; the arrangement's "hosts X"
sentence reads the split.
