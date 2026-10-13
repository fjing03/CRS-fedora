# Design — venue-pending-and-type-labels

> Batch 2 · 2026-10-13 · Implements frozen `proposal.md` (U2b + U3 + B1).
> No schema changes, no new dependencies, no frozen-UI markup changes.

## D1 — Venue-page pending events (U2b + U3)

### Data source

`VenueTimetable.php` already fetches the reserved rows — `$slots` (:72–75) includes
`status = 'pending'` rows of the venue; they are dropped only by the
`$session === null` skip (:91). Design: **keep the skip for the imported-class path**,
then add a second pass for the skipped reserved rows:

```php
// After the imported-class loop:
$slotIds = $slots->where('class_session_id', null)->where('status', 'pending')->pluck('id');
$requests = ReplacementRequest::with(['classSession.module', 'classSession.lecturer',
        'classSession.cohorts.programme'])
    ->where('status', 'pending')
    ->whereIn('replacement_time_slot_id', $slotIds)
    ->get()
    ->keyBy('replacement_time_slot_id');
```

Query choice: direct eager-loaded query (no new `TimeSlot::replacementRequest()` hasOne —
avoids touching the model surface for one consumer).

### Field mapping (existing event shape, verbatim)

| Field | Source |
|---|---|
| `id` | slot id (`$slot->id`) |
| `di/start/end` | the reserved slot's own `day_of_week` / slot indexes (same `slotIndex()` arithmetic as imported rows) |
| `code/name/type` | `request->classSession->module` — the **class being replaced** (what will occupy the new room) |
| `venue` | `$venue->room_code` |
| `lecturer` | `request->classSession->lecturer->name` |
| `cohort/cohorts/studentCount` | `request->classSession->cohorts` (existing `cohortLabel()`/`cohortCode()` helpers) |
| `status` | `'pending'` (amber per the existing legend — frozen decision, **no** conflict-derivation in this change) |
| `remarks` | `'Replacement request awaiting PL approval'` (byte-matches the legend tip at blade :81; the venue detail modal doesn't render remarks today — kept for payload honesty) |
| `mine` | `request->proposer_id === $myUserId` |

### Semantics pinned (review 🟡 items)

- **One event per pending slot — no coalescing.** Reserved slots cannot join the
  `w:day:session_id` coalescing groups (no `class_session_id`), and per spec Amendment 2b
  each request reserves exactly **one anchor half-hour slot**, so one event per slot is
  complete by construction. Events still flow through `mergeTwinEvents()` (defensive
  parity with imported events; the active-request partial unique index makes real twins
  impossible).
- **Week guard:** slots with `week_number` outside `1..$weeks` are skipped, same as the
  imported path (:91).
- **Orphan defence:** a `pending` slot with no matching active request (should not exist —
  cancel/submit keep slot+request in lockstep) emits **nothing**; no error, no log noise.
- **Unchanged on purpose:** ownership cards (`$mySlotCount` — "pending bookings are not
  teaching"), Available counts (the DB slot status is already `pending`, so counts are
  honest without new code), severity ladder, `theme.css` (zero CSS changes —
  `event-mine-pending`/`event-others-pending` exist at theme.css:1974/1979).

### Fixtures for verification

- **"Mine" leg — live:** demo request **#1** (status `pending`, slot **27309**, **B110**,
  week 4, proposer_id 11 = Lim Jia Zheng = staff 5770). As 5770: `/venue-timetable-ui`
  → B110 → week 4 → amber Pending block, **thick border**.
- **"Others'" leg — feature-test only:** no foreign-proposer pending request exists in the
  demo DB (reqs 2–3 are cancelled, same proposer). `VenueTimetableTest` creates a second
  lecturer's pending reservation and asserts `mine => false` (→ `event-others-pending`).
  Manual live check of this leg is explicitly not required.
- Cancelled/rejected requests (reqs 2, 3) must emit nothing.

## D2 — Shared three-way type label (B1)

### The helper

Anchor on the existing canonical map at `ui-common.js:943`
(`'Lecture (L)' / 'Practical (P)' / 'Tutorial (T)'`). Extract it into one shared static
(promote-on-3rd-duplication §10.6 — after this fix the same ternary would live in 7+
places otherwise):

```js
/* ui-common.js — canonical session-type labels (§10.0.2: same name + same meaning) */
const UiLabels = {
  /* form: 'code' → L|T|P · 'long' → Lecture|Tutorial|Practical · 'canonical' → 'Lecture (L)' … */
  classType(type, form) {
    const long = { L: 'Lecture', T: 'Tutorial', P: 'Practical' }[type] || type;
    if (form === 'code')   return type;
    if (form === 'canonical') return long + ' (' + type + ')';
    return long;
  },
};
```

(Namespace placement — top-level `UiLabels` next to `DateHelper`, or as a static on the
existing cards object — is implementer's choice; the *name* `UiLabels.classType` is the
contract.)

### Consumers (all six proposal sites + the anchor becomes the 7th)

| Site | Call |
|---|---|
| `ui-common.js:943` (class-detail modal — the anchor) | `UiLabels.classType(event.type, 'canonical')` |
| `ui-common.js:2351` (`myRequestCard`, history card) | `UiLabels.classType(r.classType, 'long')` |
| `ui-common.js:2372` (`replacementHomeRow`, home table) | `UiLabels.classType(c.type, 'code')` |
| `ui-common.js:2389` (`replacementHomeCard`, home card) | `UiLabels.classType(c.type, 'long')` |
| `replacement-home.blade.php:560` (quick view) | `UiLabels.classType(c.type, 'long')` |
| `my-request-history.blade.php:767` (detail modal) | `UiLabels.classType(r.classType, 'long')` |
| `replacement-history.blade.php:373` (detail modal) | `UiLabels.classType(r.type, 'canonical')` (preserves its existing "(L)" suffix style) |

Both history blades already run under `layouts.ui-template` (component `render()`) and
already call ui-common.js helpers from inline scripts (`weekFilterChanged`,
replacement-history:397 promote note) — ordering is proven. Frozen
`ui-design-templates/*` copies stay untouched.

### Test reconciliation (amended per review — automated, not manual)

Frozen criterion 4's "label helper maps P→Practical" is covered by an **automated
Playwright spec** — the repo already ships one (`npm test` = `npx playwright test`,
`@playwright/test` in devDependencies, specs incl. `tests/venue-timetable.spec.ts`,
`tests/venue-db.spec.ts`, `pages-parity.spec.ts` that assert page text against the demo
DB). New spec **`tests/type-labels.spec.ts`**: log in as 5770 → `/replacement-home-ui` →
assert the AMCS2093 **#39** (Thu 13:30 B010) and **#49** (Fri 11:00 B011) rows render
"**(P)**" / Practical, and one L row + one T row render byte-identical to today. Zero new
dependencies; demo-DB fixtures other specs already rely on. The live `&_=` freshness
ritual stays for the demo narrative only, not as the acceptance mechanism.

## Promoted to shared (record per AGENTS.md prompt rule 7)

- **`UiLabels.classType(type, form)`** (new, `public/js/ui-common.js`) — replaces the
  binary L/T ternary duplicated at 6 live sites; the pre-existing three-way map at
  `ui-common.js:943` (class-detail modal) is refactored to call it too (7th consumer).
  Contract: `'code'` → `L|T|P`, `'long'` → `Lecture|Tutorial|Practical`,
  `'canonical'` → `'Lecture (L)' | 'Tutorial (T)' | 'Practical (P)'`.

## Data flow (who calls who)

```
SubmitReplacementRequest (existing) → time_slots.status='pending' (class_session_id NULL)
                                     → replacement_requests (status 'pending',
                                       replacement_time_slot_id → slot)
VenueTimetable@mount/loadTimetable  → TimeSlot query (existing, already includes pending rows)
                                     → NEW: ReplacementRequest query by slot ids (eager: classSession.module/.lecturer/.cohorts.programme, proposer)
                                     → event shape (existing) → eventsByWeek → mergeTwinEvents (existing)
blade cellRender (existing, :211–212) → event-mine-pending | event-others-pending (theme.css existing)
UiLabels.classType (new) ← 6 mislabel sites + :943 anchor
```

## Apply-phase notes

- Stale-cache ritual after ANY PHP/blade edit (two separate commands per AGENTS.md).
- `&_=` + `outerHTML.includes(...)` freshness check for the live canary.
- Gates: `composer run lint:check`, `vendor/bin/phpstan analyse --memory-limit=1G`,
  `php artisan test` (baseline 167/167 → expected 167+N).
