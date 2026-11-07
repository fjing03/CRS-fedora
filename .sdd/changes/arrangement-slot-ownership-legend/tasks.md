# Tasks — arrangement-slot-ownership-legend

Frozen artifacts: `proposal.md`, `design.md`, `specs/arrangement-slot-styling/spec.md`.
Order follows design D10; T1–T2 are behaviour-neutral on their own.

## T1 — Engine: the `mine` flag (≤1h)

- [x] `app/Services/MatrixIntersectionEngine.php`
  - [x] `lecturerBusyRanges()` — class-side rows gain `'mine' => true` (and its `makeUpRangesForLecturer()` rows too).
  - [x] `classSessionRangesForCohorts()` — rows gain `'mine' => false`.
  - [x] `makeUpRangesForCohorts()` — select `replacement_requests.proposer_id`; rows gain
        `'mine' => $viewerId !== null && (int) $row->proposer_id === $viewerId`; takes `?int $viewerId`.
  - [x] `cohortBusyRanges(...)` gains `?int $viewerId = null` and passes it down.
  - [x] `@return` docblocks updated for all four producers **and** the pass-through `busyRanges()`
        (engine:115–128) — the shape now carries `room`, `mine` and optionally `status`.
- [x] Witness: `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` passes; and
      `grep -rn "BusyRanges(" app/` shows exactly **four call-site groups plus two definitions**:
      engine `:58/59`, engine `:125/126` (inside the pass-through `busyRanges()`, defined `:114–128`),
      engine `:210/211`, `ReplacementArrangement:719/733`; definitions at engine `:312` and `:403`.
      Every consumer reads `day/start/end` only — that is the complete enumeration.

## T2 — Payload: 6th tuple key + twin merge (≤1h)

- [x] `app/Livewire/ReplacementArrangement.php`
  - [x] `$toRanges()` gains `bool $defaultMine`; emits `(bool) ($range['mine'] ?? $defaultMine)` as key 5.
  - [x] lecturer loop → `$defaultMine = true`; cohort loop → `false`, and the engine call passes
        `$lecturerId` as the viewer.
  - [x] new `private function dedupeBusyRanges(array $ranges): array` — collapse rows sharing
        `day|start|end|label|statusKind` into one with `mine = any(rows.mine)`; applied after each
        `array_merge`, before it is stored.
  - [x] docblock: the 6th key's meaning + why twins merge in the payload (the tooltip counts blocks).
- [x] Witness: `git diff --stat` touches only the Livewire component (no Blade change yet); then boot the
      page's own end-to-end proof in T7 — `DB_DATABASE=class_replacement_testing php artisan test --filter=ReplacementArrangement`
      (that component test renders the payload, so a broken tuple shape fails there).

## T3 — Blade: one resolver + attach order (≤2h)

- [x] `resources/views/livewire/replacement-arrangement.blade.php`
  - [x] add `classifyCell({ gridStatus, engineMatches, engineBlocked })` returning
        `'cell-pending' | 'cell-reserved' | 'cell-occupied cell-occupied--mine' | 'cell-occupied' | null`;
        export it on `window` beside the page's other exports.
  - [x] `cellRender()` uses it for the class, then falls through to the unchanged too-soon/available arms.
  - [x] tip attach order per design D3: engine tip when `engineMatches` has a labelled block, else the
        grid-library tip; identical attribute writes (`data-tip`, `data-tip-pos`, `aria-label`,
        `tabindex`, `cursor`).
  - [x] comment block at the resolver stating the precedence and that the red rule asks "is *any* row
        mine?" (never "is any row not mine?"), plus the cross-family note.
- [x] Witness: on the demo page as 5425, B111 W9 Mon 13:30–15:30 with a cohort-4 subject now reads
      `cell-reserved` (was `cell-pending`) and the tooltip still lists one block.

## T4 — CSS: two families, two thicknesses (≤1h)

- [x] `public/css/theme.css`
  - [x] `.cell-pending { border: 3px solid var(--color-tertiary); }`
  - [x] `.cell-reserved { background: var(--color-tertiary-container); }` (no border; hover overlay untouched)
  - [x] `.cell-occupied--mine { border: 3px solid var(--color-error); }`
  - [x] `.legend-swatch.event-selection:hover { border-color: var(--color-primary); }` (legend only — the
        grid's red deselect affordance stays)
- [x] Witness: informal eyeball receipts `/tmp/opencode/arrangement-ownership-light.png` and
      `…-dark.png` (same grid, both themes; T9a re-shoots the canonical pair the changelog cites):
      borders do not resize cells; the 14px swatches show the 3px ring.

## T5 — Shared partial: optional hint input (≤1h)

- [x] `resources/views/partials/ui-legend-bar.blade.php`
  - [x] `ownershipHint` accepts `true` (today's exact markup + text, byte-identical) **or** an array of
        `['demo' => 'osd-thick'|'', 'text' => string]` rendered in order, joined by `·`.
  - [x] `demo => ''` renders the bare `.osd-demo` (already borderless — no new CSS).
- [x] Witness (runnable now, no later task needed):
      `php artisan tinker --execute='echo view("partials.ui-legend-bar", ["ownershipHint" => true, "items" => []])->render();'`
      still prints `osd-thick` **and** `osd-thin` + the old wording, while the array form prints no
      `osd-thin` and exactly one `·` (the joiner, with no leading/trailing separator). T8 then pins the
      same thing formally for both reference pages.

## T6 — Legend items on the page (≤1h)

- [x] `replacement-arrangement.blade.php` legend include → six `class`-based items + the array hint
      (`thick border (3px) = yours` / `no border = others'`), per design D5's table: Available ·
      Your Current Selection · Pending (You) · **Pending (Others)** · **Conflict (Yours)** ·
      **Conflict (Others)** — the last tip naming **VENUE CONFLICT** and **COHORT CONFLICT** only
      (no room-type clause) as **two lines joined by a `\n`** (never `<br>`), the Conflict (Yours) tip
      naming no cohort and stating `thick border (3px)`; emphasis by capitals (`FREE SLOT — CLICK`,
      `SELECTED`, `WAITING`, `CANNOT BOOK`).
- [x] Witness: screenshot; each swatch matches a real cell state on the same screen.

## T7 — Feature tests: the flag + the merge (≤2h)

- [x] `tests/Feature/MakeUpBusyVectorTest.php` — cohort make-up rows: `mine = false` for `null` and for a
      non-proposer viewer, `mine = true` for the proposer; `lecturerBusyRanges()` rows all `true`;
      cohort class rows all `false`.
- [x] `tests/Feature/ReplacementArrangementTest.php` — every `busyByWeek[session][week]` tuple has 6 keys;
      own class `true` / cohort `false`; the twin case collapses to **one** row with `mine = true`; the
      legacy `''` key rows are `true`.
- [x] Witness: `DB_DATABASE=class_replacement_testing php artisan test --filter='MakeUpBusyVector|ReplacementArrangement'`.

## T8 — Shared-partial + reference-page test (≤1h)

- [x] `tests/Feature/UiLegendBarTest.php` (new, DB-free for the partial half)
  - [x] `ownershipHint => true` → exact today's markup (both demos + wording).
  - [x] array form → given demos/text, no `osd-thin`.
  - [x] `cohort-timetable` / `venue-timetable` rendered HTML still contains the unchanged hint line
        (testing DB, authenticated).
- [x] Witness: `DB_DATABASE=class_replacement_testing php artisan test --filter=UiLegendBar`.

## T9a — Browser receipt: fixture + both families (≤2h)

- [x] `tests/arrangement-slot-ownership.spec.ts` (new) — header guard
      `abortUnlessTestingTarget(..., { requireAllowSql: true })`, `CRS_BASE` required.
  - [x] the T9a `describe` holds **one `test()`** that walks 5425 → 5770 → the red family sequentially
        (a second test would trip its own `status='available'` precondition and leak the first
        fixture), and its `beforeEach`/`afterAll` live **inside** that describe so the T9b block stays
        fixture-free. Include the 5425-own-pending mirror: as 5425 on B010 **week 7** (seeded request
        26, proposer 1) the cells are `cell-pending` 3px, which is the "mirror image" the Done-when
        asks for.
  - [x] `beforeEach`: discover two adjacent `available` slots (B010, week 8, Friday) + assert session
        39/40 identity; insert a pending request (session 39, proposer 5770, `week_number = 9`), junction
        rows, flip both slots to `pending` (with `updated_at`); record ids. `afterAll` reverses exactly
        that.
  - [x] as **5425** (session 40): the two cells are `cell-reserved`, `border-top-width: 0px`, amber;
        `data-tip` shows the pending-make-up header with exactly one block; `aria-label` begins `Pending — `.
  - [x] as **5770** (session 39): the same cells are `cell-pending`, `border-top-width: 3px`.
  - [x] red family on seeded data: same B010 view, week 8 Thursday 13:30 → 5425 sees
        `cell-occupied` 0px, 5770 sees `cell-occupied.cell-occupied--mine` 3px.
  - [x] screenshots `/tmp/opencode/arrangement-ownership-light.png` + `…-dark.png`.
- [x] Witness: `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 npx playwright test tests/arrangement-slot-ownership.spec.ts`
      green, and green on a second run (idempotent fixture).

## T9b — Browser receipt: resolver matrix + legend (≤1.5h)

- [x] same spec file, second `describe` block with **no `beforeEach` insert** (fixture-free; DB read-only):
  - [x] `window.classifyCell()` cases: rules 1–4, cross-family (my class + other's pending → amber,
        no border), arm disagreement (grid 3 + engine other → mine), duplicate twins (`mine:true` +
        `mine:false` → 3px), 5-key tuple (no amber answer).
  - [x] legend: six rows present in order with the decided labels (`Pending (Others)`,
        `Conflict (Yours)`, `Conflict (Others)`); each swatch's class list matches its row; **every row
        has a non-empty `data-tip`**; the `Conflict (Others)` tip mentions `VENUE CONFLICT` and
        `COHORT CONFLICT`, splits into exactly **two** lines, and contains no `<` or `>` (the `<br>`
        trap); the `Conflict (Yours)` tip mentions no cohort and contains `thick border (3px)`; both
        pending tips contain `WAITING`; the Available tip contains `FREE SLOT — CLICK` and the
        Selection tip `SELECTED`; the hint line reads
        `thick border (3px) = yours · no border = others'` and the bar contains no `osd-thin` swatch.
- [x] Witness: the same `npx playwright test tests/arrangement-slot-ownership.spec.ts` run stays green.

## T10 — Docs (≤1h)

- [x] `page-changelogs/replacement-arrangement-changelog.md` — the law, resolver order, merge rule, the
      counter delta (and that the bar is commented out today), legend table, receipts.
- [x] `prompts/for-ai-to-read/CodingMAIN.md` — the grid paragraph: two-axis language on this page with
      **0px** instead of 0.5px, the red-scoped 3px rule, the merge rule.
- [x] `prompts/for-ai-to-read/my-coding-rules.md` — one bullet.

## T11 — Gates (≤1.5h)

- [x] `composer run lint:check`
- [x] `vendor/bin/phpstan analyse --memory-limit=1G --no-progress`
- [x] full PHPUnit on `class_replacement_testing` (background run, poll the log)
- [x] neighbour specs: `week-filter-preserved`, `arrangement-entry-guard`, `venue-status-parity`
- [x] reseed the testing DB; kill :8030/:8010

## Commit plan (one concern each)

1. `feat(arrangement): carry slot ownership in the busy vectors` (T1+T2, tests T7)
2. `ui(arrangement): colour pending and conflict cells by ownership` (T3+T4)
3. `ui(arrangement): legend matches the grid's ownership law` (T5+T6, test T8)
4. `test(arrangement): ownership styling receipt` (T9a + T9b)
5. `docs(ai-context): the arrangement grid's ownership law` (T10 + changelog)

## Done when

- [x] Every spec requirement R1–R6 has its named evidence.
- [x] As 5425, 5770's pending slot reads **Pending (Others)** (amber, 0px) with a cohort-4 subject
      selected, and "Pending (You)" (amber, 3px) as 5770 — and the mirror image for 5425's own pending.
- [x] No demo-DB write, no other page's markup changed, full suite green.
