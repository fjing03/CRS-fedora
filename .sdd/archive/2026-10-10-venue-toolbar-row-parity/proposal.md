# Proposal — venue-toolbar-row-parity

## Why

User-reported visual drift against the frozen fjing venue design (`f8b35a2` template — byte-identical copy at `resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php`): **the week selector and venue selector render on separate rows**, while the frozen design puts them in ONE `.semester-bar` toolbar row.

Root cause (verified via git archaeology): commit `adc95bb` (2026-10-09, first DB wiring, "venue-timetable-ui reads real DB data") split the frozen single-bar layout into two `.semester-bar` rows — week nav first, `#weekSubtitle` between, venue selector last — and silently dropped the print button. **No SDD authorized the split or the button drop**: neither venue SDD archive (`2026-10-09-venue-timetable-db`, `2026-10-10-venue-event-blocks-db`) mentions toolbar layout; later parity passes checked colour/legend/cards, never toolbar geometry. The sibling `cohort-timetable` page already renders its selector + week nav in one bar — the venue page is the only odd one out.

## Scope

### S1 — Merge the two `.semester-bar` rows into one (frozen fjing order)

`resources/views/livewire/venue-timetable.blade.php` only:
- ONE `.semester-bar`: `venue-dropdown-wrap` (dropdown + `venue-capacity` span) first, then the `ui-week-nav` include — same order as fjing (venue → week nav) and the cohort-timetable pattern. Note: the `venue-capacity` span replaces fjing's fav-btn slot in the wrap — documented divergence.
- Pass `'showPrint' => true` — the partial's built-in print stub (shared "Coming soon" toast; `toast` global exists at `ui-common.js:2572`). Restores the print button lost in `adc95bb`.
- `#weekSubtitle` stays immediately under the merged bar: it is a shared `ui-common.js` element (same as my-timetable pages) and carries the week date range our week-select options don't show — a documented divergence from fjing, which has no subtitle.
- **Fav-star ☆ is NOT restored** (user decision, scope c): mock localStorage feature with no DB-page justification; recorded as a documented divergence.

### S2 — What must NOT change

- Component PHP untouched (payload, week nav behaviour, venue filtering, B005).
- Legend / cards / grid / PH rendering untouched.
- No partial changes: `ui-week-nav`'s print position (right after Today) matches the sibling pages' house pattern; fjing's `margin-left:auto` inline hack is not ported.
- `tests/venue-db.spec.ts` unaffected — it interacts with `#weekSelect` and asserts no toolbar geometry.

### S3 — Tests + verify

- Gates: `composer run lint:check`, `vendor/bin/phpstan analyse --memory-limit=1G`, `php artisan test --filter=VenueTimetable`, Playwright `venue-db`.
- Visual: stale-cache two-command restart (AGENTS.md) + screenshot the toolbar row.
- No DB touch → records-intact n/a (view-only change).

### S4 — Archive

One verify pass; archive to `.sdd/archive/`; **no push without explicit authorization.**

## Out of scope

- Fav-star restore (documented divergence).
- Other pages (cohort / my-timetable / student already match their patterns).
- Week-select option labels gaining date ranges (subtitle covers it).

## Success criteria

- Venue + week selectors (+ Today, print stub) render on ONE row, matching the frozen fjing layout and the cohort-timetable pattern (the capacity span stands where fjing's fav-btn sits — documented divergence).
- Print stub present with "Coming soon" tooltip + toast on click.
- All gates green; archived; nothing pushed without authorization.
