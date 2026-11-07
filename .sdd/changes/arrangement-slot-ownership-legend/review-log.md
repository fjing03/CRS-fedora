# Review log — arrangement-slot-ownership-legend

## proposal Round 1 — 2026-11-06
### 🔴 Fixed
- §2.3 conflated "mine" with "my cohort", which contradicted the user's law (cohort conflicts = 0px)
  and Q1A's engine table (cohort class rows are `mine = false`). The red family's 3px cause is now
  stated exactly: only `lecturerBusyRanges()` rows (my own occupied class / my own approved make-up).
### 🟡 Addressed
- The shared legend partial's hint block is two hardcoded demo swatches; this page needs a borderless
  `osd-none` demo + its own wording. The proposal's impact row and the brief (§6.4) now say so, with
  the default keeping cohort/venue byte-identical.
- The "Unavailable (Yours)" tip no longer names "your cohort" (the cause lives in the cell tooltip;
  the legend row is about ownership).
- Counter deltas noted in the risk table (an others'-pending cell stops inflating "Pending").
- The closed-red class keeps `.cell-occupied` as its base so the summary counters stay correct
  (acceptance item 6).
### 🔴 Outstanding
- none

## design Round 1 — 2026-11-06
### 🔴 Fixed
- The browser fixture reused B010 week 7 Friday 14:00+14:30 — the exact pair `TestingFixtureSeeder`
  holds for request 26 (proposer 1 = 5425). Reusing it would flip the expected class (5425's own
  pending wins rule 1) and make the grid arm's `pluck()` last-wins ambiguous for two co-resident
  junctions. D7 now *discovers* the slot pair at runtime (week 8, Friday, `status='available'`, last
  two of the day), asserts the precondition before insert, and documents why (round-1 🔴).
### 🟡 Addressed
- The resolver's precedence is now one pure exported function `classifyCell(facts)`, and D7 pins all
  four rules plus the three crossing cases (cross-family, arm disagreement, duplicate `mine` rows) and
  the 5-key stale tuple — instead of only the duplicate case.
- The conflict family now has its own receipt: red 3px as the owner (5770) vs red 0px for the same
  cells viewed by 5425, on seeded data with no extra fixture.
- The two frozen-proposal mitigations that D7 had dropped are restored: `tests/Feature/UiLegendBarTest.php`
  pins the shared partial's default hint markup and both reference pages' unchanged legend line; the
  counter test is waived *explicitly* (the summary bar is commented out at blade:1064–1079) with the
  class-count + resolver substitute named.
### 🔴 Outstanding
- none

## design Round 2 — 2026-11-06
### 🔴 Fixed
- D7 steps 2–3 still said "week 7" after the fixture moved to week 8; both now read week 8, matching the
  discovery SQL.
- **Duplicate `mine` rows would have broken the cell tooltip**: the lecturer/cohort twins of one class
  are byte-identical today (so `array_unique` collapses them) and `renderHolderTip()` counts
  `blocks.length`; a 6th key would have made them distinct, printing "2 classes in the way" and the
  block twice. `busyByWeekPayload()` now merges twins that share the first five keys with
  `mine = OR(twins)` via a typed `dedupeBusyRanges()` helper, so the client receives one row per
  blocking fact and every tip/aria attach stays byte-identical. D2 and D3 state the rule; the feature
  test asserts one merged row.
### 🟡 Addressed
- The fixture's `replacement_requests.week_number` is now specified as the replaced occurrence's week
  and explicitly ≠ the tested week (9, mirroring request 26's 14), because the entry guard would
  redirect the page out otherwise.
- The discovered slot pair must be adjacent (30 minutes apart), asserted with the `available`
  precondition.
- `afterAll` no longer claims to delete notifications (a `DB::table` fixture fires no events).
- The "osd-none" naming and the discoverable slot example (17:30/17:00) are corrected.
### 🔴 Outstanding
- none

## design Round 3 — 2026-11-06
### ✅ Verdict
- No 🔴 outstanding. design.md **frozen**.
### 🟡 Addressed (additive, soft-freeze)
- D1's docblock sweep now names the pass-through merge `busyRanges()` (engine:115–128) too, with the
  note that it takes no viewer (cohort rows stay `mine = false`) and that its readers only touch
  `day/start/end`, so `phpstan analyse` cannot be surprised by the new key.
- D3 now spells out the **tip-attach order** after the class merge (engine tip when `engineMatches`
  has a labelled block, else the grid-library tip, then the four attribute writes), because today the
  two attaches live inside the two branches being merged.
- D7 inserts the explicit `time_slots.status = 'pending'` flip for the two discovered slots (both
  arms filter on it) and adds a receipt assertion on `data-tip` / `aria-label` so a tip regression on
  exactly the merged cells cannot pass silently.
- Gates wording softened (`view:cache` does not check inline JS; the browser spec is the JS gate);
  the entry-guard line reference corrected to `:211–218`.

## specs Round 1 — 2026-11-06
### 🔴 Fixed
- none raised.
### 🟡 Addressed
- R2 now pins `mine === false` explicitly and states that a missing 6th key must not produce the
  amber "others" answer (matching the frozen design's stale-payload clause).
- R1's merge bullet names the layer (`busyByWeekPayload()` / `dedupeBusyRanges()` before shipping) and
  says the engine keeps emitting twins.
- R6's cleanup clause now covers restoring the `time_slots.status` the fixture modified.
- R4's verification row names the reference-page assertion's real cost (testing DB, authenticated) and
  the `.event-selection:hover` red-border quirk the legend swatch could inherit.
### 🔴 Outstanding
- none

## specs Round 2 — 2026-11-06
### 🔴 Fixed
- R2's unqualified "a cell MUST be 3px iff a blocking row is own" contradicted its own precedence table
  and the cross-family scenario (my own class + another staff's pending → amber, 0px). The clause is
  now **red-family-scoped**, and it explicitly defers the amber thicknesses to R3 (which fixes them by
  state), so both the cross-family cell and a grid-arm-only own pending are correct.
### 🟡 Addressed
- R2's missing-key parenthetical is scoped to engine matches (grid status 4 stays authoritative).
- R3's borderless list now includes an overlapping block (frozen proposal §2.3 had it).
- The `.event-selection:hover` question is **decided**, not left to the browser round: one scoped
  `.legend-swatch.event-selection:hover` rule keeps the primary border; the grid's red deselect
  affordance is untouched. Design D4/D8 carry the same qualification in the doc summary.
### 🔴 Outstanding
- none

## tasks Round 1 — 2026-11-06
### 🔴 Fixed
- T9's legend receipt omitted the per-row `data-tip` assertions the spec's R4 evidence requires. T9b now
  asserts every row's tip is non-empty and that the "Unavailable (Yours)" tip names no cohort.
### 🟡 Addressed
- T5's witness is now runnable at T5's own turn (render the partial with `ownershipHint => true` and
  check `osd-thin` + wording); T8 keeps the formal reference-page assertions.
- T2's witness is concrete (`git diff --stat` + the `ReplacementArrangement` component test) instead of
  "page still renders"; T1 enumerates its four call-sites.
- T9 was over the 2h cap and is split into T9a (fixture + both families + screenshots, ≤2h) and T9b
  (resolver matrix + legend, ≤1.5h), both inside commit 4.
- T4/T9 name the screenshot paths; the env prefix is consistent (`DB_DATABASE=class_replacement_testing`)
  in both test witnesses; the `updated_at` detail is kept on the slot-status flip.
### 🔴 Outstanding
- none

## tasks Round 2 — 2026-11-06
### 🔴 Fixed
- T1's witness enumeration was wrong (it claimed four literal grep hits and cited a non-existent `:283`
  anchor, contradicting its own docblock bullet). It now names the real output: four call-site groups
  (engine :58/59, :125/126 inside `busyRanges()` :114–128, :210/211, ReplacementArrangement :719/733)
  plus the two definitions (:312/:403), all reading `day/start/end` only.
### 🟡 Addressed
- T9a's lifecycle is pinned: one `test()` walking 5425 → 5770 → red family, with `beforeEach`/`afterAll`
  scoped inside that describe so the T9b block stays fixture-free; the missing "mirror image" evidence
  is now included (5425 on B010 week 7 → its own seeded pending, 3px).
- T5's witness is unambiguous (the array form prints exactly one `·` as the joiner).
- T4's screenshots are labelled informal eyeball receipts; T9a re-shoots the canonical pair.
### 🔴 Outstanding
- none

## Freeze — 2026-11-06
All four batches passed: proposal (2 rounds), design (3), specs (3), tasks (3). No 🔴 outstanding.
**Ready for `/sdd-apply arrangement-slot-ownership-legend`.** Nothing applied, no code or CSS edited this
session beyond the SDD artifacts and the brief copy at
`prompts/UI Update & Bug Fix — Pending Slots, Conflicts & Legend Styling.md`.

## Label decision — 2026-11-06 (post-freeze, decision-level → full-chain unfreeze)
User, after reading the plain-English preview:
- `Reserved by Others` → **`Pending (Others)`** (pair with Pending (You)).
- `Unavailable (Yours)` / `Unavailable (Others)` → **`Conflict (Yours)`** / **`Conflict (Others)`**,
  matching the cohort/venue legend's own word.
- The **Conflict (Others)** hover text must name a **venue conflict** and a **cohort conflict**
  explicitly (plus the room-cannot-host case, so "Conflict" stays truthful).

Unfroze and re-worded: `proposal.md` §2.4, `design.md` D5 (table + tips), the spec's R4 and its
verification row, `tasks.md` T6 + T9b's assertions, and the `explore-brief.md` §6.2 baseline table.
No algorithm, semantic or responsibility change — labels and one tooltip sentence only.
### 🔴 Fixed (same round)
- spec R4's row list still carried the old labels (the earlier edit missed a line wrap) — now
  `Pending (Others)`, `Conflict (Yours)`, `Conflict (Others)`.
- Post-change expected-state text in `proposal.md` acceptance #1 and `tasks.md`'s Done-when now read
  **Pending (Others)** (the other `Reserved by Others` mentions are pre-change history and stay).
### 🟡 Addressed
- `explore-brief.md` Q6A row list and §7's row-6 pointer re-worded; §1.1's table keeps the user's
  original law wording.
- `Conflict (Yours)` tip now says "your own class **or make-up**" (matches R3), and design D7 item 7
  carries the full legend/tip assertion list.

### Final tip wording (user iteration, 2026-11-06)
- Emphasis by capitals in the legend tips: **WAITING**, **CANNOT BOOK**, **VENUE CONFLICT**,
  **COHORT CONFLICT** (a `data-tip` is text; markup would print literally).
- Conflict (Yours): "CANNOT BOOK — your own **Normal class or Replacement class** is in this slot —
  thick border (3px)" (the other pages' own words; "make-up" retired).
- Conflict (Others): "CANNOT BOOK — **VENUE CONFLICT** (this slot is used by another class in this
  room), **COHORT CONFLICT** (the related cohorts have another class at that time)". The room-type
  clause was **dropped by the user**; design D5 and explore-brief §7 item 3 record that wrong-room
  cells stay red with no legend line (cell tooltip only) so `/sdd-verify` does not treat it as a gap.
- Pending (Others) states `no border`; both "yours" rows state `thick border (3px)`.
- Propagation: design D5, spec R4 (+ verification row), tasks T6/T9b assertions, explore-brief §6.2.

### Tip polish round 2 (user, 2026-11-06)
- Available: `FREE SLOT — CLICK …`; Your Current Selection: `… you have SELECTED …` (capitals = emphasis).
- Conflict (Others) is now **two lines**. The user asked for `<br>`; a `data-tip` is TEXT on this page
  (the shared engine sets `textContent` + `pre-line`), so a `<br>` would print as literal characters.
  Implemented as a `\n` inside the Blade string — same visual break, and the spec now forbids markup in
  the tip, with a browser assertion that the value contains no `<`/`>` and splits into exactly two lines.

## Apply — 2026-11-06
All tasks T1–T11 executed. Two findings worth recording:

1. **`classifyCell()` must live in the outer `DOMContentLoaded` scope.** It was first placed inside
   `buildTimetable()`, so `window.classifyCell = classifyCell` threw a ReferenceError at the export
   block — the page still rendered, but the cell classes silently degraded. Caught by the receipt.
2. **A staff member's second login on the same browser context does not switch the session**
   (measured: the form posts, `/dashboard` loads, the identity stays the first user). The receipt uses
   ONE CONTEXT PER IDENTITY.
3. **The spec must run alone.** `tests/week-filter-preserved.spec.ts` re-seeds
   `TestingFixtureSeeder` in its own `beforeEach`, and that seeder deletes every replacement request —
   which wipes this fixture mid-run (symptom: an orphaned pending slot reads "reserved").
   `assertFixtureAlive()` now turns that into an explicit message.
4. **Red-check done:** with the pre-fix "every busy row is mine" flag the spec fails; restored green.
5. **`?week=` deep links land one week late** on this page (pre-existing); the receipt lands on the
   week through the page's own selector by label.
6. `venue-status-parity.spec.ts` is written against the OLD demo state (request 26 on B010 week 7) and
   fails on a freshly seeded DB — pre-existing rot, not this lane's; left untouched.
