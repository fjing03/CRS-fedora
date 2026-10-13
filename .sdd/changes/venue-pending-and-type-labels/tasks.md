# Tasks — venue-pending-and-type-labels

> Batch 3 · 2026-10-13 · Implements frozen proposal.md + design.md.
> Order: Capability 1 (PHP+tests) → Capability 2 (labels+spec) → gates → live verify → docs.
> Every task ≤ 2 h. Apply-phase ritual after ANY PHP/blade edit: kill server, clear views, restart.

## Capability 1 — venue-page pending events (U2b + U3)

- [ ] **T1 — Emit reserved-slot events** (`app/Livewire/VenueTimetable.php`)
  After the imported-class loop: collect `$slots` rows with `class_session_id === null`
  AND `status === 'pending'`; eager-load `ReplacementRequest` (`status = 'pending'`,
  `whereIn('replacement_time_slot_id', …)`, with `classSession.module/.lecturer/.cohorts.programme`)
  keyed by `replacement_time_slot_id`; emit one event per slot per design D1 field table
  (`status 'pending'`, `mine = proposer_id === $myUserId`, remarks byte-matching the
  legend tip). Week guard 1..$weeks; orphan slots emit nothing. Keep the imported-path
  skip untouched. ~45 min
- [ ] **T2 — Feature tests** (`tests/Feature/VenueTimetableTest.php`, follow the existing
  seeded + crafted-row pattern)
  (a) own pending reservation → event with `status 'pending'`, `mine true`, class facts
  from the original session; (b) another lecturer's → `mine false`; (c) cancelled/rejected
  request → no event; (d) reserved slot outside 1..$weeks → no event; (e) orphan pending
  slot (no request row) → no event; (f) existing green tests unchanged. ~60 min
- [ ] **T3 — Live check (Capability 1)** — restart ritual, login 5770 →
  `/venue-timetable-ui` → B110 → week 4: amber Pending block, **thick border**, showing
  the replaced class's code/name (fixture: req #1 / slot 27309). `&_=` + outerHTML
  freshness check. ~15 min

## Capability 2 — shared three-way type label (B1)

- [ ] **T4 — `UiLabels.classType` helper** (`public/js/ui-common.js`, near `DateHelper`)
  Contract per design D2: `'code'` → `L|T|P`, `'long'` → `Lecture|Tutorial|Practical`,
  `'canonical'` → `'Lecture (L)'…`; unknown type passes through. Refactor the anchor
  `ui-common.js:943` to call it (7th consumer). ~20 min
- [ ] **T5 — Swap the six mislabel sites** (byte-identical L/T output; P gains the
  correct label): `ui-common.js:2351` (long), `:2372` (code), `:2389` (long),
  `replacement-home.blade.php:560` (long), `my-request-history.blade.php:767` (long),
  `replacement-history.blade.php:373` (canonical). Frozen `ui-design-templates/*`
  untouched. ~30 min
- [ ] **T6 — Playwright spec** (`tests/type-labels.spec.ts`, new): login 5770 →
  `/replacement-home-ui` → AMCS2093 #39 (Thu 13:30 B010) + #49 (Fri 11:00 B011) rows
  render "(P)"/Practical; one L row + one T row byte-identical to today. ~45 min

## Gates & docs

- [ ] **T7 — Gates**: `composer run lint:check`; `vendor/bin/phpstan analyse
  --memory-limit=1G` (0 errors); `php artisan test` (baseline 167/167 → 167+N);
  `npx playwright test` (**full suite** — T1 changes venue-page events, and
  `tests/venue-timetable.spec.ts` / `pages-parity.spec.ts` assert that page's text; the
  new `tests/type-labels.spec.ts` must be green too). ~30 min
- [ ] **T8 — Changelogs**: postscripts in `page-changelogs/venue-timetable-ui-changelog.md`
  and `replacement-home-changelog.md`; postscripts in the my-request-history and
  replacement-history changelogs for their modal label swaps (house rule: every UI page
  change gets a postscript). ~20 min
- [ ] **T9 — review-log apply entry + commit** (conventional commits: `feat(ui):` /
  `fix(ui):`), recording live-verification evidence and the gates numbers. ~15 min
