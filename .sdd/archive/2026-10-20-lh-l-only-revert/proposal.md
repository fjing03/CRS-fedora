# Proposal — lh-l-only-revert

## Why (the user's 2026-10-20 ruling, verbatim)
> "update : the Lecture Hall only allow for L only."

The dual-admission ruling (2026-10-19) let T book Lecture Halls via the
`lt-venue-capacity-rule` change. The follow-up ruling narrows it BACK:
**Lecture Halls admit L ONLY; Tutorials keep L/T; Labs stay P-only.**

## In scope (ONE rule row's descent + its echo set)
- `LectureSessionCatalog::ruleMap()` — the T row → `['tutorial']` (the L row
  STAYS `['tutorial', 'lecture_hall']` — tutorials still admit L).
- `ui-common.js`'s `venueTypeWording` — the phrase splits per raw type:
  `lecture_hall` → `'Lecture (L)'`; `tutorial` → `'Tutorial (L/T)'`
  (dual); labs keep `'Practical (P)'`.
- `LtVenueCapacityRuleTest` — the T-in-LH rows flip (T at B110 = rejected;
  the L-in-tutorial shapes stay; the "T-in-LH is data-not-law" guard
  becomes the OPPOSITE polarity per the new law).
- `docs/venue-restrictions.md` — the R1 T row + the 2026-10-20 note.
- The changelogs: `replacement-arrangement`, `venue-timetable-ui`,
  `venue-dropdown`.

## Explicitly NOT in scope
- The L row / the tutorials' L/T dual admission / the P row — untouched.
- The F5 keys, the D-P4 stamp, the dd memory (already archived), the
  span-aware leftovers (Q1) — untouched.

## Impact scope
| File | Change |
|---|---|
| `app/Services/LectureSessionCatalog.php` | the T row narrows |
| `public/js/ui-common.js` | the wording helper's per-key split |
| `tests/Feature/LtVenueCapacityRuleTest.php` | the T-in-LH rows revert |
| `docs/venue-restrictions.md` | the R1 row + the note |
| changelogs ×3 | dated rows |

## Success criteria
- `occurrenceFact('T', B110-lecture_hall)` → null (rejected); a tutorial's
  T booking → accepted; the arrangement's/copies render per-split phrases;
  phpunit + the in-scope playwright stay green.
