# Design — Slot ownership styling & legend for `replacement-arrangement`

Frozen baseline: `proposal.md` (round 2 pass) + `explore-brief.md`.
Everything here implements the 8 grill decisions (1A–8A). No schema change, no demo-DB write.

---

## D1 — Ownership becomes data in the engine

`app/Services/MatrixIntersectionEngine.php`

Every busy-range row gains one additive key: `mine` (`bool`) = *does this blocking row belong to
the viewing lecturer's own calendar?*

| Producer | Rows | `mine` |
|---|---|---|
| `lecturerBusyRanges()` → class-side | the viewer's own scheduled classes (occupied) | `true` |
| `lecturerBusyRanges()` → `makeUpRangesForLecturer()` | the viewer's own pending + approved make-ups | `true` |
| `cohortBusyRanges()` → `classSessionRangesForCohorts()` | **any** lecturer's class carrying one of the cohorts | `false` |
| `cohortBusyRanges()` → `makeUpRangesForCohorts()` | **any** lecturer's make-up for a session of those cohorts | `proposer_id === $viewerId` |

Signature change (additive, default keeps today's behaviour for every other caller):

```php
public function cohortBusyRanges(int $semesterId, array $cohortIds, int $weekNumber, ?int $viewerId = null): array
```

`makeUpRangesForCohorts()` selects `replacement_requests.proposer_id` alongside the existing columns
and maps `'mine' => $viewerId !== null && (int) $row->proposer_id === $viewerId`. With the default
`null`, `mine` is `false` everywhere — inert for the enforcement path
(`EnforcementEngine`/`OCCValidator`/submit pre-checks), which only reads `day/start/end/room`.

Why the parameter and not `auth()` inside the engine: the engine stays a pure service (it already
takes `lecturerId` as an argument); the Blade-facing caller passes `auth()->id()`.

The `@return` docblocks of the four producers are updated to
`array<int, array{day:int,start:string,end:string,room:string,mine:bool,status?:string}>`, **and so is
the pass-through merge `busyRanges()`** (engine:115–128), whose current `@return array<int, array{day,
start, end}>` goes stale the moment rows carry `mine`/`room`/`status` — `phpstan analyse` would then
fail or force an undocumented deviation. `busyRanges()` takes no viewer, so it keeps the default
(`cohortBusyRanges(..., null)`), and its readers (`findAvailableSlots`, `validateSlot`) only touch
`day/start/end`: the flag is inert there.
Note: a slot that is *both* the viewer's own class and one of their cohorts' classes now yields two
rows for the same range (`mine` true and false) — that is intended and harmless; see D3.

## D2 — The payload ships the flag as the tuple's 6th key

`app/Livewire/ReplacementArrangement.php::busyByWeekPayload()`

| Key | 0 | 1 | 2 | 3 | 4 | 5 (new) |
|---|---|---|---|---|---|---|
| Meaning | day | start hour idx | end hour idx (exclusive) | holder label \| null | `'pending'` \| `'approved'` \| null | `mine: bool` |

- `$toRanges()` gains a `bool $defaultMine` argument and emits `(bool) ($range['mine'] ?? $defaultMine)`.
- Lecturer loop passes `$defaultMine = true`; cohort loop passes `false` and relies on the row's own flag.
- The cohort call passes the viewer: `$this->engine()->cohortBusyRanges($semesterId, $cohortIds, $w, $lecturerId)`.
- **Dedup — twins merge, they do not multiply (round-2 🔴).** A slot that is the viewer's own class
  *and* one of their selected cohorts' classes is emitted twice (lecturer vector `mine:true`, cohort
  vector `mine:false`) with the **same** `[day, start, end, label, status]`. Today those two rows are
  byte-identical, so `array_unique(..., SORT_REGULAR)` collapses them and `renderHolderTip()`'s
  `blocks.length` stays honest. Adding the 6th key would make them distinct, and every own-class cell
  whose cohort is also in the vector would print *"⚠ Occupied — 2 classes in the way"* with the block
  listed twice — silently breaking the cell tooltip while D3 promises the attaches are unchanged.

  So `busyByWeekPayload()` gains one private helper and stops relying on `array_unique` alone:

  ```php
  /** Collapse rows that are the SAME blocking fact; mine = OR of the twins. */
  private function dedupeBusyRanges(array $ranges): array   // key = day|start|end|label|statusKind
  ```

  Merge rule: identical first five keys → one row, `mine = any(rows.mine)`. Rows that differ in
  `label` or `statusKind` stay separate (they are different facts, e.g. a pending and an approved
  make-up in the same half-hour). Exact duplicates still collapse as before. The client therefore
  receives at most one row per blocking fact, `renderHolderTip()` is untouched, and the resolver's
  "is *any* row mine?" is unambiguous. `array_unique(..., SORT_REGULAR)` stays as the first pass.
- The 5th key's docblock is extended: "…and the 6th says whether the row is the viewer's own calendar
  (the cell's border is decided by it)".

`busyByWeek` is consumed by exactly one view (`replacement-arrangement.blade.php`); the other
timetable pages use their own payloads, so the extra key cannot leak.

## D3 — The cell resolver (one classification, not "first arm wins")

`resources/views/livewire/replacement-arrangement.blade.php`, inside `cellRender()` — the class
decision is extracted; the `data-tip` / `aria-label` attaches keep their **existing wording and
order**, which requires one structural care-point: today `attachHolder()` (engine tip) lives *inside*
the engine branch (blade:1802–1808) and the grid-library tip lives *inside* the grid branch
(blade:1837–1845), which is why an engine-blocked cell never consults grid status 3/4. After the
merge, attach in this order, unchanged in content:

1. if `engineMatches` has at least one labelled block → `renderHolderTip(engineMatches)` (the engine
   tip: pending-make-up header / "Occupied — N class(es)" + why-tags);
2. else if `cellData` carries holder indices → the grid-library tip;
3. `setAttribute('data-tip', …)`, `data-tip-pos="left"`, `aria-label`, `tabindex="0"`,
   `style.cursor = 'help'` exactly as today.

The content is byte-identical because the D2 merge leaves one row per blocking fact — the header
count, the block list and the flat aria string all see the same rows they see today.

```js
const gridStatus = cellData ? cellData[2] : null;              // 1 occupied · 3 my pending · 4 their pending
const pendingMatches = engineMatches.filter(b => b[4] === 'pending');
const otherMatches   = engineMatches.filter(b => b[4] !== 'pending');
const isMine = b => b[5] === true;                             // data, never the 'You — ' label

const pendingMine   = gridStatus === 3 || pendingMatches.some(isMine);
const pendingOthers = gridStatus === 4 || pendingMatches.some(b => b[5] === false);
const conflictMine  = otherMatches.some(isMine);
const blocked       = engineBlocked || gridStatus === 1;
```

The rules live in **one pure function**, `classifyCell(facts)` (exported on `window` next to the
page's other exports so the browser spec can exercise the precedence table directly with crafted
tuples — no fixture needed):

```js
function classifyCell({ gridStatus, engineMatches, engineBlocked }) -> 'cell-pending' | 'cell-reserved'
                                                             | 'cell-occupied cell-occupied--mine'
                                                             | 'cell-occupied' | null  // → too-soon/available
```

Class decision, in this order (the full Q2A precedence — the crossing cases are counter-intuitive
on purpose, so they are written down):

| # | Test | Class | Why this order |
|---|---|---|---|
| 1 | `pendingMine` | `cell-pending` | my own reservation is the most actionable fact |
| 2 | `pendingOthers` | `cell-reserved` | someone else's reservation beats a plain block — amber with no border |
| 3 | `conflictMine` | `cell-occupied cell-occupied--mine` | a red cell is 3px **iff any** blocking row is mine |
| 4 | `blocked` | `cell-occupied` | cohort-only, another lecturer's class, or venue-ineligible |
| 5 | `isSlotTooSoon(...)` | `cell-too-soon` | unchanged |
| 6 | else | `cell-available` + `setupAvailableCell()` | unchanged |

Three cases worth pinning in tests:

- **Duplicates**: my own class that also carries my selected cohort is **merged in the payload**
  (D2) to one row with `mine = true`, so rule 3 sees a single 3px cause and the tooltip still counts
  one class. The resolver still asks *"is any row mine?"* — never *"is any row not mine?"* — as
  defence if a future producer ever ships twins. A cell whose only rows are `mine:false` is 0px.
- **Cross-family**: my own class + another staff's pending on the same half-hour → rule 2 wins (amber,
  no border). The tooltip still lists my class. Decided by Q2A; recorded here so a reviewer does not
  read it as a bug.
- **Arm disagreement**: grid says 3, engine says another's pending → rule 1 wins (mine). The grid arm
  is the authoritative owner of a reserved slot (server-side `proposer_id` test).

Every tuple now carries key 5, so `b[5] === false` is a real answer. A *missing* key (a page loaded
before the deploy) is not "others'" either: an all-pending engine cell would fall through rules 1–2
and land on red rule 4 for one page load. Acceptable (a reload fixes it), and stated here so it is
not mistaken for a rule bug.

## D4 — CSS: two families, two thicknesses

`public/css/theme.css` (existing rules at 2654–2721, extended in place)

```css
/* pending family — amber fill for both, ownership by border */
.cell-pending { border: 3px solid var(--color-tertiary); }          /* mine */
.cell-reserved { background: var(--color-tertiary-container); }     /* theirs — no border */
/* conflict family — red fill for all, ownership by border */
.cell-occupied { /* unchanged: background: var(--color-error-container) */ }
.cell-occupied--mine { border: 3px solid var(--color-error); }
```

- Tokens only (`--color-tertiary`, `--color-error`, `--color-tertiary-container`); no literal colour,
  so light/dark follow automatically (tertiary at `theme.css:37/39/104/106`, error at `:42/44/109/111`).
- `.cell-reserved` loses its `--color-surface-variant` fill. Its `::after` hover overlay keeps the
  neutral surface colours (the overlay is a label, not a state) — minimal diff.
- `.cell-occupied--mine` is **added to** the base class in JS, so
  `querySelectorAll('.cell-content.cell-occupied')` in `updateSummaryStats()` keeps counting it.
- `box-sizing: border-box` is global (`theme.css:6`): a 3px border cannot resize a cell or move the
  grid. The time label is centred; at 14px-wide legend swatches the 3px ring still reads.
- No `--color-warning` here: that token stays the just-cancelled pulse (ruling: one flash mechanic).
- One scoped legend-only rule keeps the selection swatch honest: `.legend-swatch.event-selection:hover`
  keeps its primary border (the grid's `.event-selection:hover` intentionally turns red as the
  deselect affordance, `theme.css:2541–2546`; a legend row is not a control).
- `--hover-label` strings are unchanged (`Pending` / `Reserved` / `Occupied`).

## D5 — The legend

`resources/views/livewire/replacement-arrangement.blade.php` — replace the `@include('partials.ui-legend-bar', [...])`
item array (currently 1054–1061, five flat `color` swatches) with six `class` items:

| Swatch class | Label | Tip |
|---|---|---|
| `cell-available` | Available | FREE SLOT — CLICK to select as the replacement (slots inside the 3-working-day window are read-only) |
| `event-selection` | Your Current Selection | The slot(s) you have SELECTED for the replacement |
| `cell-pending` | Pending (You) | Your replacement request for this slot is WAITING for approval — thick border (3px) |
| `cell-reserved` | Pending (Others) | Another staff member's replacement request is WAITING for approval here — no border, you CANNOT BOOK it |
| `cell-occupied cell-occupied--mine` | Conflict (Yours) | CANNOT BOOK — your own Normal class or Replacement class is in this slot — thick border (3px) |
| `cell-occupied` | Conflict (Others) | CANNOT BOOK — VENUE CONFLICT (this slot is used by another class in this room),\nCOHORT CONFLICT (the related cohorts have another class at that time) |

**The Conflict (Others) tip is two lines.** A `data-tip` is text: the line break is the two-character
sequence `\n` inside the Blade string (the shared engine sets `textContent` and switches to
`pre-line`), never a `<br>` — markup would print literally. The break falls after the comma that ends
the VENUE CONFLICT clause.

Ownership hint line, passed through a new optional partial input:

```blade
@include('partials.ui-legend-bar', [
    'ownershipHint' => [
        ['demo' => 'osd-thick', 'text' => 'thick border (3px) = yours'],
        ['demo' => '',          'text' => "no border = others'"],
    ],
    'items' => [...],
])
```

`resources/views/partials/ui-legend-bar.blade.php` — today `ownershipHint` is a bool with **two
hardcoded demos** (`osd-thick` 3px, `osd-thin` 0.5px) around fixed text. Extend it to accept either:

- `true` → today's exact markup and text (cohort + venue keep byte-identical output), or
- an **array of `['demo' => class, 'text' => string]`** → rendered in order, joined by `·`.

`demo => ''` renders the bare `.osd-demo` (no border — `theme.css:1694` sets none), which is the
"no border" marker; **no new CSS rule is needed** and none is added. (The proposal/brief call this
variant `osd-none`; it is realized as the bare `.osd-demo`, so a later reader must not go looking for
an `osd-none` rule.) `data-tip` per item and the
hover/touch hint stay untouched.

## D6 — Counters keep their meaning

`updateSummaryStats()` (blade ~1339–1360) is unchanged — and note the summary bar itself is
currently commented out (blade:1064–1079), so the delta is a DOM-count change today, not a visible
one; the changelog says exactly that so nobody reads a "drop" into it. After the fix an others'-pending engine cell
counts as **Reserved → Unavailable**, so on affected grids "Pending" drops and "Unavailable" rises by
the same amount (the total is stable). This is the honest number and is called out in the changelog.

Counter test waiver (frozen proposal §5 (a)): a *visible* counter test is impossible while the
summary bar is commented out. Honest substitute, accepted here and recorded in the review log: the
class-count semantics are pinned by (i) the base-class rule (`.cell-occupied--mine` keeps
`.cell-occupied`) and (ii) the resolver test, which proves every red cell carries `cell-occupied` and
every pending cell carries exactly one of `cell-pending` / `cell-reserved`.

Known pre-existing gap, deliberately untouched: `.cell-too-soon` cells are counted neither as
available nor as unavailable, so `sumTotal` can be short; out of scope for this brief (logged in the
changelog as an observation, not fixed).

## D7 — Tests

### Feature (testing DB — no browser)

`tests/Feature/MakeUpBusyVectorTest.php` (extend the existing ownership case):

- cohort make-up rows owned by another lecturer carry `mine = false` at `viewerId = null` and at
  `viewerId = other`;
- the same rows carry `mine = true` for `viewerId = proposer`;
- `lecturerBusyRanges()` rows all carry `mine = true`;
- cohort class-side rows all carry `mine = false`.

`tests/Feature/ReplacementArrangementTest.php` (new case):

- `busyByWeek[sessionId][weekIdx]` tuples have exactly 6 keys everywhere;
- the viewer's own class row → `mine === true`; a cohort row → `false`;
- a slot that is both the viewer's class and their cohort's class → **one** row, `mine === true`
  (the D2 merge rule), and the tuple count is unchanged by the flag;
- legacy `''` (lecturer-freedom) key rows are `mine === true`.

### Legend shared-partial test (frozen proposal §5 (b))

`tests/Feature/UiLegendBarTest.php` (new, no DB): rendering `partials.ui-legend-bar` with
`ownershipHint => true` and default items produces the **exact** today's hint markup
(`osd-thick` 3px + `osd-thin` 0.5px + "thick border (3px) = your classes · thin border (0.5px) =
others'"); with the array form it renders the given demos/text in order and no `osd-thin`. Plus a
component-level assertion that `cohort-timetable` and `venue-timetable` rendered HTML still contains
the unchanged hint line (the two reference pages are the blast radius the partial could break).

### Browser (one spec, testing DB, guarded)

`tests/arrangement-slot-ownership.spec.ts`, modelled on `notifications-island-testing-db.spec.ts`
(`abortUnlessTestingTarget(..., { requireAllowSql: true })`, `CRS_BASE` required, no bare run):

1. `beforeEach` **discovers** its slot pair at runtime and inserts its own fixture with `RETURNING id`
   (ids recorded; `afterAll` deletes exactly those rows — junction first, then the request — and puts
   the two `time_slots.status` back to `available`; re-run idempotent). No notifications exist to
   delete: the fixture writes with `DB::table`, which fires no model events:

   ```sql
   SELECT ts.id FROM time_slots ts JOIN venues v ON v.id = ts.venue_id
   WHERE ts.semester_id = <active> AND ts.week_number = 8 AND ts.day_of_week = 4
     AND v.room_code = 'B010' AND ts.status = 'available'
   ORDER BY ts.start_time DESC LIMIT 2;                    -- the day's latest two, e.g. 17:30 + 17:00
   ```

   **Slot-choice rule (round-1 🔴):** never hardcode a pair — `TestingFixtureSeeder` already holds
   B010 **week 7 Friday 14:00+14:30** for request 26 (proposer 1 = 5425), and reusing those slots would
   both flip the expected class (5425's own pending wins rule 1) and make the grid arm's
   `pluck(proposer_id, time_slot_id)` last-wins ambiguous for two co-resident junctions. The discovered
   pair must be asserted `status = 'available'` **before** the insert, so a dirty DB fails loudly at
   step 1 instead of as a misleading class mismatch.

   The pending request belongs to **staff 5770's** session 39 (AMCS2093 P, cohort 4); the viewer-side
   session is **staff 5425's** session 40 (AMSE2003 P, cohort 4) — the shared cohort is what pulls the
   row into 5425's cohort vector with `mine = false`.

   The insert **must flip both discovered slots to `status = 'pending'` (with `updated_at`)** — every
   consumer (`makeUpRangesForCohorts`, `venueSlotsByWeekPayload`) filters
   `time_slots.status IN ('pending','occupied')`, so without the flip both arms see nothing and every
   assertion fails for the wrong reason. `afterAll` restores them to `available`.

   Its `replacement_requests.week_number` is the **replaced occurrence's** week and must **not** equal
   the week under test (8): `ReplacementArrangement`'s entry guard blocks a page whose own session
   already carries an active request at that `week_number` (`ReplacementArrangement.php:211–218`), so a
   fixture written as `week_number = 8` would redirect 5770 out before any cell renders. Follow the
   seeded convention (`TestingFixtureSeeder` writes request 26 as `week_number = 14` while its slots
   sit in week 7): use `week_number = 9`.

   The two discovered slots must also be **adjacent** (30 minutes apart) — assert that alongside the
   `status = 'available'` precondition, so a drifted seed cannot yield a non-contiguous "pair" that
   makes a 2-slot request invalid.
2. As **5425** (selecting the cohort-sharing subject, opening B010, **week 8**): the two discovered
   cells are `.cell-reserved` with computed `border-top-width: 0px` and the amber container
   background.
3. As **5770** (selecting its own session, same venue/**week 8**): the same cells are `.cell-pending`
   with computed `border-top-width: 3px`.
4. **Red family, real cells (round-1 🟡2):** in the same B010 view — as **5425**, week 8 Thursday
   13:30 (session 39's own class, cohort 4, `mine = false`) the cells are `.cell-occupied` with
   `border-top-width: 0px`; as **5770** the same cells are `.cell-occupied.cell-occupied--mine` with
   `3px`. This is the 3px/0px law of the *conflict* half, on seeded data, with no extra fixture.
5. **The tip survives the merge (round-3 🟡):** on the discovered cell, `data-tip` starts with the
   pending-make-up header, contains **exactly one** block line (not two), and `aria-label` starts with
   `Pending — `.
6. **Resolver precedence, crafted facts (round-1 🟡1):** call `window.classifyCell(...)` directly for
   the four rules and the crossing cases — `pendingMine` beats `pendingOthers`; `pendingOthers` beats
   `conflictMine` (my class + another's pending → amber, no border); `conflictMine` beats a plain
   block; a cell with duplicate `mine:true` + `mine:false` rows is 3px (rule 3 asks "is *any* row
   mine?"); a tuple with 5 keys does not produce a "mine" answer.
7. The legend: all six rows present with the decided labels, every row's `data-tip` non-empty, the
   `Conflict (Others)` tip naming both a venue conflict and a cohort conflict, the `Conflict (Yours)`
   tip naming no cohort, and the hint line reading `thick border (3px) = yours · no border = others'`, each swatch carries its item's class, and the hint line reads
   `thick border (3px) = yours · no border = others'`.
8. Receipts saved to `page-changelogs/` (light + dark screenshots via the theme toggle).

`tests/week-filter-preserved` / `arrangement-entry-guard` etc. are re-run as neighbours — the legend
and cell classes must not disturb the week-select guard or the entry guard.

### Out-of-scope note

`resources/views/ui-design-templates/replacement-arrangement-UIdesign-template.blade.php` keeps its own
copy of the per-arm class logic (:1616–1626) and will now drift from the live page. Design references
are not loaded pages and recent lanes leave them alone; recorded here so the archive follow-up can
decide once, not per lane.

### Gates

`composer run lint:check` → `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` → full
PHPUnit → the two Playwright specs. (`php artisan view:cache` only proves the Blade directives
compile; it says nothing about inline `<script>` syntax, so the browser spec is the real JS gate — the
resolver is exported on `window` and exercised there.)

## D8 — Docs

- `page-changelogs/replacement-arrangement-changelog.md`: the law, the resolver order, the duplicate
  rule, the counter delta, the legend table, the receipts.
- `prompts/for-ai-to-read/CodingMAIN.md`: extend the grid paragraph — the arrangement grid joins the
  two-axis language (colour = state, border = ownership), with **0px** rather than the 0.5px hairline
  used by cohort/venue, and the chicken-and-egg rule, worded for the **red** family only: "a `.cell-occupied` cell is 3px iff any
  blocking row is mine" (the amber thicknesses are fixed by state, so a cross-family cell and a
  grid-arm-only own pending are both correct).
- `prompts/for-ai-to-read/my-coding-rules.md`: one bullet (same rule, quick form).

## D9 — Rejected alternatives

See `explore-brief.md` §4 (label parsing, first-arm-wins, `--color-warning`, 0.5px hairline, split
"conflict"/"not bookable", 5-row legend, cohort/venue label reuse, per-cell queries, changing the
grid's 1/3/4 status contract).

## D10 — Order of work

1. Engine flag + docblocks → 2. payload key + docblock → 3. Blade resolver → 4. CSS → 5. partial hint
input → 6. Blade legend items → 7. feature tests → 8. browser spec + screenshots → 9. docs/changelog
→ 10. gates + full suite.

Steps 1–2 are behaviour-neutral on their own (nothing reads the flag yet), which keeps each commit
small and revertible.
