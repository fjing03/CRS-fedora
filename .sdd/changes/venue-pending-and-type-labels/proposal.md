# Proposal — venue-pending-and-type-labels

> /sdd-propose · 2026-10-13 · Fixes bug-hunt cluster B (explore brief:
> `.sdd/changes/replacement-arrangement-bug-hunt/explore-brief.md`, IDs **U2b + U3 + B1**,
> re-verified still-broken 2026-10-13, HEAD `677a26c`).

## Why

**One pending reality, four pages must agree** (trait docblock + D1 + §10.0 table B) —
but the venue timetable never shows reserved replacement slots, and P classes are
mislabelled everywhere the shared label helpers run.

1. **U2b (P1)** — On `/venue-timetable-ui`, a reserved replacement slot renders *nowhere*:
   `VenueTimetable.php` builds events from `time_slots` and **skips every row whose
   `class_session_id` is NULL** (`if ($session === null …) continue;`, line 91) — and
   replacement reservations always carry NULL there (verified: slots 27309/27373/27851/
   27758/28451 all NULL while reserved). The page's own legend *promises* a "Pending"
   block ("Replacement request awaiting PL approval") — currently unreachable. Evidence:
   `evidence-2b-venue-pending-invisible.png`.
2. **U3 (P1)** — the pending-consistency sweep: my-timetable ✓, cohort ✓, home ✓
   (all via the shared trait) — the venue leg is the only broken page. Same root as U2b;
   one fix closes both.
3. **B1 (P1)** — P sessions labelled "(T)" / "Tutorial". The mock-era helpers are binary
   (`c.type === 'L' ? 'L' : 'T'`): `ui-common.js:2351/2372/2389` and
   `replacement-home.blade.php:560`. Live-proofed 2026-10-13 as staff 5770: home rows for
   AMCS2093 **P** sessions 39 (Thu 13:30 B010) and 49 (Fri 11:00 B011) both print "(T)".
   Violates §10.0.2 (same name + same meaning everywhere).

## What

Two small capabilities, no schema or engine changes:

### Capability 1 — venue-page pending visibility (U2b + U3)

`VenueTimetable.php` additionally emits events for **reserved slots**: `time_slots` rows
of this venue with `status = 'pending'`, `class_session_id = NULL`, joined to their active
`replacement_requests` row (`replacement_requests.replacement_time_slot_id → time_slots.id`,
`status = 'pending'` — approved handling is Slice C's approval backend, out of scope).

Each emitted event reuses the **existing event shape verbatim**
(`id/di/start/end/code/name/type/venue/lecturer/cohort/cohorts/studentCount/status/remarks/mine`),
with class facts (module, lecturer, cohorts, headcount) taken from the request's
**original** `class_session_id` session — the class being replaced is what occupies the
new room — and:

- `status` → `'pending'` (amber per the existing legend: "colour = status … amber = pending")
- `mine` → `proposer_id === myUserId` (border = ownership per the existing legend:
  **thick 3 px = your classes, hairline 0.5 px = others'**)

**User decision (2026-10-13):** reuse the existing venue legend exactly — Available /
Normal / Pending / Conflict / Public Holiday, thick border = yours, thin = others'. **No
new legend entry, no "(You)" suffix, no new colour** — the blade renderer already
branches `e.mine ? 'event-mine-pending' : 'event-others-pending'` (venue-timetable
blade :211–212), so this is a PHP data-source fix only; the frozen UI is untouched.

### Capability 2 — three-way type labels (B1)

Teach the shared label helpers the third type, per §10.0 canonical map:

| type | label | where |
|---|---|---|
| L | (L) / Lecture | unchanged |
| T | (T) / Tutorial | unchanged |
| **P** | **(P) / Practical** | **new — currently collapses to T/Tutorial** |

Sites (all six carry the identical binary ternary; the last two surfaced in review):

| # | Site | Form |
|---|---|---|
| 1 | `ui-common.js:2351` (`myRequestCard`) | long — history card |
| 2 | `ui-common.js:2372` (`replacementHomeRow`) | short — home table row |
| 3 | `ui-common.js:2389` (`replacementHomeCard`) | long — home card view |
| 4 | `replacement-home.blade.php:560` (quick view) | long |
| 5 | `my-request-history.blade.php:767` (detail modal "Class Type") | long |
| 6 | `replacement-history.blade.php:373` (detail modal "Class Type") | long + "(L)/(T)" suffix |

Frozen `ui-design-templates/*` copies of the same ternaries stay **untouched** — they are
mock-era references, not live code. Label text and colours follow §10.0 — no new colour
tokens. In-repo precedent for the canonical wording already exists at `ui-common.js:943`
(`'Lecture (L)' / 'Practical (P)' / 'Tutorial (T)'`) — the design batch anchors on it
(and on the §10.6 promote-on-3rd-duplication rule: after this fix the same three-way
logic would exist in 7+ places, so factor one shared helper). Implementation shape
(small shared helper vs inline three-way ternaries) is a design-batch decision.

## Out of scope

- **U2a** venue-page Book affordance (cluster C — separate change).
- **Approved-request rendering** on the venue page (needs Slice C's approval backend).
- **Server-side venue-type matrix** (`docs/venue-restrictions.md` — its own SDD).
- **Fix 3 / full block reservation** (spec amendment, parked).
- Any engine (`MatrixIntersectionEngine`) or submit-path change.

## Success criteria

1. As 5770 with one pending request on a Bxxx slot: `/venue-timetable-ui` for that
   venue/week renders an amber Pending block with the replaced class's code/name; thick
   border (own) vs hairline (someone else's) — verify both by viewing a slot reserved by
   another proposer if one exists, else code-review + test.
2. Home table/card/quick-view, history cards, **and both history detail modals**
   (`my-request-history` :767, `replacement-history` :373) print "(P)" / "Practical" for
   P sessions (AMCS2093 #39/#49 as the canary); L/T rendering byte-identical to today.
3. `php artisan test` green (baseline 167/167) + `vendor/bin/phpstan analyse
   --memory-limit=1G` 0 errors; `composer run lint:check` clean.
4. New feature tests: venue page emits pending events for reserved slots (mine + others),
   skips cancelled/rejected; label helper maps P→Practical.
5. `page-changelogs/venue-timetable-ui-changelog.md` (exists) +
   `replacement-home-changelog.md` postscripts updated.

## Specs

Skipped per workflow ("optional for simple changes"): two small capabilities, one repo,
no cross-module contracts beyond the existing event shape and §10.0 map. The design batch
carries the field-level detail.

## Affected files (expected)

- `app/Livewire/VenueTimetable.php` — emit reserved-slot events (+ eager loads)
- `public/js/ui-common.js` — label helpers ×3 sites (+ shared helper per promote rule)
- `resources/views/livewire/replacement-home.blade.php` — quick-view label :560
- `resources/views/livewire/my-request-history.blade.php` — detail-modal label :767
- `resources/views/livewire/replacement-history.blade.php` — detail-modal label :373
- `tests/Feature/VenueTimetableTest.php` (or new) — pending-event coverage
- `tests/type-labels.spec.ts` (new Playwright spec — see design "Test reconciliation")
- `tests/` label coverage if a helper is factored
- `page-changelogs/*.md` — postscripts
