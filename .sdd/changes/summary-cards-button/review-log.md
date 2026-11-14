# Review log — `summary-cards-button`

Append-only. One entry per reviewer round.

---

## proposal Round 1 — 2026-11-14 (late evening)

Artifacts under review: `proposal.md` (nothing frozen at the time). Baseline: `explore-brief.md`,
the source brief `prompts/pi-prompt-summary-cards-button.md`, and the four real files it names.
Reviewer: separate read-only pi subprocess (`commandcode/z-ai/glm-5.3-flash`,
`--tools read,grep,find,ls`), never the authoring session.

**Verdict: ready to proceed to design.md — no 🔴 blockers.**

### 🔴 Fixed

- none raised

### 🟡 Addressed

1. **Esc precedence and Tab-trap suspension vs the ⓘ dialog.** The ⓘ lives outside the sheet, so a
   literal trap keeps Tab hostage and one Esc would close both layers. Pinned in §5.4: the sheet's Esc
   handler no-ops while `#summaryExplainModal` is visible, and the trap is suspended for the ⓘ's
   lifetime. (Behaviour — to be restated in `design.md`.)
2. **Empty-view button visibility had no runtime trigger.** `sync()` alone (init + media-query change)
   cannot see a page hiding the cards mid-session (`syncSummarySection(false)` on an empty filtered
   week). Pinned: `SummarySheet` wraps the existing global `syncSummarySection`, plus a re-check in
   `open()`.
3. **`wire:ignore` claim corrected.** The section's origin and its destination are both inside
   `venue-timetable`'s `wire:ignore` subtree (373–474); the move does not leave it. §4 and §8 reworded —
   the residual risk is smaller than first written.
4. **Test item (a) was impossible as written.** `toHaveCount(0)` at 769 counts attached nodes; the
   button is rendered and only `display:none`. Now visibility-based. Inventory corrected too: TC58
   (`venue-timetable.spec.ts:548–551`, 375×667) and `cancelled-slot-reuse.spec.ts:259–260` already read
   the cards at phone width and stay green only because they count/read attached nodes — they are the
   canaries and must not be "tightened" into visibility assertions.
5. **"byte-identical" overstated.** §2.5 reworded to "visually and behaviourally identical", with the
   one `display:none` button + hidden overlay named as markup.

### 💡 Taken

- §1 saving stated as "~170–350px" (Replacement History's section is 172px, not 348px).
- §5.2 now carries the **48px mobile-floor opt-out** requirement (the same one `.guide-toggle-icon`
  needs, `theme.css:1192–1196`), otherwise two 44px icons overflow at 320.
- Dim literal `rgba(0,0,0,.45)` documented as a deliberate byte-for-byte reuse of the frozen
  `.modal-overlay` value, so the tokens-only rule stays traceable.
- Option set completed: **Option D** (separate shared wrapper partial — the brief's own example) is now
  tabled and rejected as dominated by Option A.
- Test item 4 reworded ("open at 390 → 800 → 390"; the button does not exist at 800).

### 🔴 Outstanding

- none.

### 💡 Noted for later batches

- 🟡1 and 🟡2 are decision-level behaviour: they must reappear in `design.md` and in the specs
  (Batch 3) before those freeze.

---

### Reviewer output, verbatim

```
## 🔍 Review Report: `summary-cards-button`

**Scope of this round:** `proposal.md` only. Nothing is frozen. Baselines: `explore-brief.md` (primary), `prompts/pi-prompt-summary-cards-button.md` (source brief), plus the four real files (`ui-page-header.blade.php`, `ui-summary-bar.blade.php`, `ui-common.js`, `theme.css`) and the Playwright specs the test plan cites. Every load-bearing fact in the proposal was checked against the actual files.

---

### 🔴 Critical Issues (implementation blockers)

**None found.** The proposal's facts check out against the real files: all seven include line numbers are exact (`my-timetable:55`, `cohort-timetable:141`, `venue-timetable:415`, `replacement-home:243`, `replacement-history:154`, `request-approval:287`, `student-my-timetable:40`); `syncSummarySection` is at `ui-common.js:2191–2194` and sets **inline** `display` as claimed; `summary-locked` is indeed a shared, unrefcounted class used by both `SummaryCards.open/close` (`:2281/:2291`) and `GuideModal` (`:2320/:2331`); the flip grid is 2-col at ≤768 (`theme.css:1947`) and 1-col below 390 (`:1965`); the venue `wire:ignore` div spans `373–474` exactly; the arrangement summary include is genuinely commented out (comment opens at `:2718`); none of the eight blades carries `wire:model`/`wire:click` (only the separate `nav-notifications` component does); the new ids `summaryButton`/`summarySheet*` have no collisions anywhere (arrangement's `#summaryBody` is a different node, as explore §2 warns); and the cited spec assertions exist (`pages-parity:69,100`, `ui-regression:387`, `venue-timetable:329–337`, `cancelled-slot-layout:245–250`, the first three running at Playwright's default 1280 viewport — no viewport overrides in those files).

---

### 🟡 Should Fix (non-blocking but recommended)

1. **Esc/Tab-trap interplay with the ⓘ dialog is unspecified and will fail test-plan item 5 as designed.** §5.4 says `open()` traps Tab within the `[role="dialog"]` and collapses the trap only on `close()`. But when the ⓘ opens *above* the sheet (frozen requirement 5), focus moves into `#summaryExplainModal`, which is **outside** the trapped container — a literal implementation keeps Tab hostage inside the sheet, making the ⓘ's close button keyboard-unreachable. Worse, a single Esc fires **both** document-level handlers: `SummaryCards`' existing Esc handler (`ui-common.js:2221–2228`) closes the ⓘ *and* the sheet's new Esc listener closes the sheet — so the page behind unlocks-slash-vanishes, violating test item 5's "the page behind stays locked". The proposal correctly solves the scroll-lock half of this layering (own class, §5.4) but is silent on Esc precedence and trap suspension. **Fix:** pin in §5.4 (or design.md) that the sheet's Esc handler no-ops while `#summaryExplainModal` is open, and the trap is suspended for the duration of the ⓘ.

2. **Decision 4's "hide the button when cards are hidden" has no trigger mechanism, and the naive one fails in exactly the scenario it exists for.** §5.4 checks button visibility inside `sync()`, which runs at init and on media-query `change` only. But `syncSummarySection(false)` fires at **runtime** (cohort-timetable's empty view, `blade:472,538`): a phone user who filters to an empty week mid-session hides the cards while the Summary button is already rendered — and tapping it opens an empty sheet, precisely what decision 4 forbids. Three implementers would build this three ways (media-query-only check; backstop re-check in `open()`; wrapping/patching `window.syncSummarySection`). **Fix:** state the mechanism — the cleanest is that `SummarySheet` wraps the existing `syncSummarySection` global (one function, one call site, no per-page code), with an `open()` visibility re-check as a backstop.

3. **§4's wire:ignore claim is factually wrong in one detail.** It says "the section moves out of that subtree" — but the summary bar include sits at `venue-timetable.blade.php:415`, **inside** the `wire:ignore` div (373–474), and the new sheet frame is emitted by the same partial (§5.3), so the move is *within* that subtree, not out of it. This doesn't change the code (the JS relocation is identical either way) but it mis-frames the residual risk and would misdirect the venue-page re-check. **Fix:** reword §4/§8 — both the section's origin and its destination are inside the `wire:ignore` subtree, so a future morph cannot touch the moved node either; the named risk is actually smaller than stated.

4. **Automated test item (a) will fail as literally written.** "Button count 0 at 769" is impossible with a bare `toHaveCount(0)`: the button is Blade-rendered for opted pages and merely `display:none` at ≥769 (§5.2), and Playwright `toHaveCount` counts attached nodes regardless of visibility. The assertion must be visibility-based (`toBeHidden()` / `:visible` count). Related inventory gap: the §6 sentence "existing specs … all run at ≥1280" is inaccurate — **TC58** (`venue-timetable.spec.ts:548–551`) asserts `.summary-bar .summary-card` count 5 at **375×667**, i.e. exactly at the move breakpoint. It stays green only because `toHaveCount` counts attached nodes and the moved cards remain attached (it is, in fact, a useful canary for the move). Same for `cancelled-slot-reuse.spec.ts:259–260`, which reads summary card values through a helper. Add both to the inventory so nobody later "tightens" them into visibility assertions and manufactures a false regression.

5. **"Desktop (≥769) byte-identical" (§2.5) is overstated.** Opted pages gain a `display:none` button and a hidden overlay in the desktop DOM. Visually and behaviourally identical is the true (and sufficient) claim; "byte-identical" invites a false `/sdd-verify` failure. Reword.

---

### ✅ What's Done Well

- **The premise correction is carried honestly.** §1 refuses the source brief's "fills the first screen" claim and states the real value (reach, ~350px shorter page) — exactly what explore §1 demanded.
- **Option C's rejection carries measured evidence** (the `elementFromPoint` top-layer probe), not opinion; Option B's cons trace to the verified inline-`display` contract (explore §5); Option A's cost (DOM move) is named, not buried.
- **The scroll-lock collision is spotted and solved pre-emptively** (own `summary-sheet-open` class, with the reason spelled out) — this is the kind of defect that usually surfaces in testing.
- **Decisions 1–4 map one-to-one onto explore §7** with honest option framing on the arrangement page (including why (c) is not recommended).
- The test plan covers the whole frozen-requirement surface: per-width manual steps, keyboard/SR, resize round-trip, ⓘ layering, tooltip clipping, empty view, pressed-pair check, 320px header fit.

---

### 💡 Optional Suggestions

- §1's "~350px shorter" holds for flip pages (348px) but Replacement History's section is 172px (explore §1) — "~170–350px" is the honest range.
- §5.2 doesn't mention that `.summary-button` needs the same **explicit 44px opt-out** from the 48px mobile button floor that `.guide-toggle-icon` has (`theme.css:1192–1196` documents this requirement). One line in design.md, or test item 9's "two 44px icons" fails.
- The dim `rgba(0,0,0,.45)` is a literal rather than a token — defensible since it is byte-for-byte the frozen `.modal-overlay` value (`theme.css:2039`), but note the reuse in design.md so the "tokens only" rule stays traceable.
- The "shared wrapper partial" variant the source brief named as an example option is never tabled. Option A dominates it (fewer touch points) and §5.3 says why; one sentence would make the option set complete against the brief's example list.
- Test item 4's "800→390→800 while open" is impossible as written (the button doesn't exist at 800); reword to "open at 390 → resize to 800 → resize back to 390".

---

### ⚖️ Review Verdict

**Ready to proceed to `design.md` (Batch 2).** No 🔴 blockers. Conditions: issues 🟡1 (Esc/trap precedence) and 🟡2 (empty-view trigger mechanism) are **decision-level behaviour** and must be pinned in design.md (and reflected in the eventual specs) before specs freeze; 🟡3–🟡5 are one-line corrections to the proposal itself — since nothing is frozen yet, fix them now while the file is still open. Suggested next step: apply the 🟡3–🟡5 wording fixes to `proposal.md`, then create `design.md` with 🟡1 and 🟡2 resolved, and record this round in `review-log.md`.
```

---

## design Round 1 — 2026-11-14 (late evening)

Artifacts under review: `design.md`. `proposal.md` frozen (round 1).

**Verdict: not ready — one blocker.** Resolved the proposal round 1 conditions (Esc precedence, trap
suspension, empty-view mechanism) but its own visibility predicate defeated the module.

### 🔴 Fixed
- `syncButton()` used `offsetParent === null` to mean "the page hid the cards". At ≤768 the section sits
  inside the `hidden` overlay, where `offsetParent` is null in the normal state — the button could never
  appear and `open()` always no-opped. Predicate replaced with the section's own computed
  `display === 'none'`, which every writer sets and which stays `block` inside a hidden ancestor.

### 🟡 Addressed
- `mouseout` release widened to plain cards inside the sheet (otherwise a tap-unflipped card could never
  be re-flipped by a pointer).
- `unrelocate()` now strips `is-flipped` / `is-unflipped` from plain cards (a class carried back inline
  at ≥769 could never be cleared and would leave the desktop card hover-dead for the session).
- Plain card backs got a height budget: measured in the live page (142px at a 175px card), pinned 148px
  at 2 columns and 132px at 1 column, scoped to `#summarySheetBody`.
- The grid fix is scoped to `#summarySheetBody`, so the nine reference templates keep today's columns.
- The flip pages' in-sheet hint copy stays exactly as today: the touch sentence is emitted only for the
  plain variant, and the hide rule is variant-scoped.
- Print no longer relies on media re-evaluation: `beforeprint` → `unrelocate()`, `afterprint` → `sync()`,
  plus the print rule and a `page.emulateMedia({ media: 'print' })` assertion.

### 💡 Taken
- Veil literal comment corrected (same colour as `.modal-overlay`, not byte-for-byte).
- `close()` now names the Esc capture listener it unbinds; the resize-up focus no-op is documented.
- `relocate()` documented idempotent; the one-frame flash at first paint is accepted and recorded.

### 🔴 Outstanding
- none.

---

## design Round 2 — 2026-11-14 (late evening)

**Verdict: not ready — one blocker**, a fresh one hiding inside the round-1 hint fix.

### 🔴 Fixed
- The hint hide rule was unscoped (`#summarySheetBody .summary-hint-pointer`), which would have deleted
  the **whole hint line on all six flip pages** inside the sheet — contradicting the design's own "their
  copy is untouched" and doing so with no decision behind it. Rule now scoped to
  `.summary-section:not(.summary-section--flip)`.

### 🟡 Addressed
- The 1-column height budget was derived at 389 (the widest 1-column case) instead of 320, where the card
  is ~272px and the same sentence needs ~115–130px; a fixed-height face clips rather than grows. Pinned
  to 132px at 320, with the implementer required to re-measure at 320 before locking it.
- The print path kept `body.summary-sheet-open { overflow: hidden }`, which can truncate a multi-page
  printout; the print block now resets it to `visible`.

### 💡 Taken
- A comment on the new unscoped hover rule records that it subsumes the flip variant's own rule, so nobody
  "cleans up" one of them.
- `unrelocate()` strips the `data-sheet-a11y` marker itself, so repeated cycles cannot accumulate markers.

### 🔴 Outstanding
- none.

---

## design Round 3 — 2026-11-14 (late evening) — frozen

Scope of this round: confirmation of §4.3, §5.1 and §5 only.

**Verdict: all three findings resolved; ready for apply.** Evidence: the hint rule is variant-scoped; the
132px budget is argued at 320 and delegated to a re-measure; the print block resets the lock. No
breakage found in those sections.

**`design.md` is FROZEN** (declarative-only changes from here; any decision-level change needs a
full-chain unfreeze).

---

## specs Round 1 — 2026-11-14 (late evening)

Artifacts under review: `specs/summary-sheet/spec.md`. `proposal.md` + `design.md` frozen.

**Verdict: not ready — one blocker.** Traceability was complete and the boundaries strong; two sentences
contradicted the frozen design.

### 🔴 Fixed
- R6's "the only additions are the button and the hidden overlay" and R5's "templates unaffected … nothing
  else may change" both contradicted design §4.2/§4.3, which deliberately add a `hidden` overlay and split
  the hint into two spans. A correct build would have failed verify. R6 now says "no visible additions"
  and names the accepted invisible deltas; R5 accepts the inherited overlay **and** the plain-variant hint
  spans. The template count was corrected (nine include the partial, eight render it).

### 🟡 Addressed
- Brief want 6's pressed-pair clause restored to R1.
- R7 regained the automated ⓘ `z-index` assertion and gained an Esc-precedence assertion (one Escape with
  the ⓘ open closes only the ⓘ and leaves the page locked) — the two decision-level behaviours from the
  proposal round had no automated evidence.
- R6 gained the `afterprint` half: cards return into the sheet after printing from a phone-sized window.
- R5 pins the tablet band at 1024 as well as 769.
- R7 now names the accessible facts to collect instead of referring to "the facts above".

### 💡 Taken
- R1 names the bar-chart glyph; the header-fit check at 320 is an assertion; the scope line distinguishes
  the seven opted Livewire pages from the nine reference templates.

### 🔴 Outstanding
- none.

---

## specs Round 2 — 2026-11-14 (late evening) — frozen

Scope: confirmation of the six round-1 items.

**Verdict: five of six fully resolved; one clause needed a one-line reword before apply** (R5 still
forbade the hint-span delta it had just accepted), applied spec-side with the frozen artifacts untouched.
With that reword the spec is ready.

**`specs/` is FROZEN.**

---

## tasks Round 1 — 2026-11-14 (late evening)

Artifacts under review: `tasks.md`. Proposal, design and specs frozen.

**Verdict: not ready — two blockers, both in the evidence task.**

### 🔴 Fixed
- **T11 had no assertion for R3's repaint-after-relocation** — the lane's one structural risk (the DOM
  move against `wire:ignore`/page repaint) would have gone to verify unevidenced. T11 now pins, on
  venue-timetable at 390: open the sheet, close, change the week/filter, assert the ids inside
  `#summarySheetBody` carry the **updated** values (and the same change at 1440 leaves inline cards
  updated).
- **T11 had no back-overflow assertion** — and design delegates the 132px budget to an implementer
  re-measure at 320, so a wrong number would ship silently as clipped text. T11 now asserts
  `scrollHeight <= clientHeight + 1` for every `.summary-card-back`, both variants, at 320/390/768.

### 🟡 Addressed
- Trap suspension gained Tab evidence (reach the ⓘ's close button while it is open, containment resumes).
- The `afterprint` half is asserted, not just implemented.
- T4/T7 dependency named instead of implied.
- T13 trimmed to the spec's own screenshot requirement (full matrix on two representative pages; 390+1440
  on the rest) so it stays inside 2 hours.
- The two orphaned evidence clauses — a tooltip near the sheet's top edge not being clipped, and the plain
  cards' `role="group"` + label — are now asserted.

### 💡 Taken
- T9/T10 name the plain page by both its component and its route, so nobody hunts for a
  `student-class-replacements.blade.php` that does not exist.
- The resize-to-900 check says why any ≥769 value proves the strip.

### 🔴 Outstanding
- none.

---

## tasks Round 2 — 2026-11-14 (late evening)

**Verdict: blockers resolved; two conditions left.**

### 🟡 Addressed
- The patch pushed T11 to six assertion groups over three pages and two themes — realistically 3–5 hours,
  against the file's own ≤2h claim. Split into **T11a** (visibility, relocation, repaint, empty view) and
  **T11b** (closing, layering, plain variant, print); all twelve assertions (a)–(l) survive, each exactly
  once.
- The T4/T7 residual: the observer was claimed twice, now it is T7's alone.

### 🔴 Outstanding
- T4's `init()` still claimed a `first sync()` that, by design §6, ends with `syncButton()`.

---

## tasks Round 3 — 2026-11-14 (late evening) — frozen

Scope: the two round-2 conditions only.

Applied after the round: T4 now says the observer **and the `syncButton()` tail** are T7's, and the first
`sync()` ships as the move/un-move half only; T11a's Satisfies line gained **R3 (repaint after
relocation)**, which the reviewer caught as a dead-end in the trace.

All twelve assertions (a)–(l) are present exactly once (T11a: a, b, c, h, k · T11b: d, e, f, g, i, j, l).

**`tasks.md` is FROZEN. The lane is ready for `/sdd-apply` and STOPS here.**
