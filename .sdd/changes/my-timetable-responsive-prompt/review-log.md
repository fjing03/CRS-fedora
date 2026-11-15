# Review log — my-timetable-responsive-prompt

Append-only. Batch order: proposal → design → specs → tasks, each frozen before the next.

## proposal Round 1 — 2026-11-14

Reviewer: independent read-only (`commandcode/z-ai/glm-5.3-flash`), system prompt in
`/tmp/mt-resp/reviewer-system.txt`.

### 🔴 Fixed
- `buildTimetableGrid()` has **five** live callers, not four: the fifth is
  `replacement-arrangement.blade.php` through the legacy `cellRender` path. The proposal's
  "shared by four / the other three stay byte-identical" was wrong and its pin test would have
  missed the arrangement page. §6 and the risk table now name all five.
- §3 item 4 (day heading + Week summary button) carried no width qualifier and would have
  silently answered the brief's open §13c ("should Week summary be reachable from desktop").
  Now scoped below 1280px, and a new §9 lists all three §13 questions as **not decided here**,
  including the print button's narrow-width fate (§13a).

### 🟡 Addressed
- The print button's quoted string: the toast is `Printing is coming soon` (no period) and its
  hover tip is `Coming soon`. Corrected.

### 🔴 Outstanding
- None.

## proposal Round 2 — 2026-11-14

### 🔴 Fixed
- **The block tooltip migration had been dropped from scope.** The brief (§2) makes it part of
  the task: today the block tip is the CSS `::after { content: attr(data-name) … attr(data-tip2) }`
  hack (`theme.css:3848`) and `initDataTipTooltips()` only reads `data-tip`. §3.7 now assigns the
  `data-tip2 → data-tip` conversion (same composed words) plus the two missing control tips, scoped
  through the opt-in flag so the other four callers keep the old path. The risk table gained the
  matching row.

### 🟡 Addressed
- `review-log.md` was empty while `sdd.yaml` said `review_rounds: 0`; round 1 is now recorded.
- "iterated five times" was ambiguous (the brief's rounds 1–5 belong to the print page); reworded.

### 🔴 Outstanding
- None.

## Scope change — 2026-11-15 (user, not a review round)

The user asked whether Student My Timetable belongs in this lane. Decision: **yes, as phase B**,
because the two pages are one page with different data — both blades carry 0 `@media` blocks, include
the same six partials and call the same `buildTimetableGrid()` (student 252 lines, staff 352, and the
student blade is a strict subset of the staff blade's wiring).

Recorded in the proposal as §3b, with §1 (why), §5 (the other two timetable pages stay out), §6 (the
read-only constraint and the lockstep rule), §7 (one risk row), §8 (phase-B done criteria) and §9d
(the one new open question: what a student agenda card says, since its block status carries Pending
and approved Replacement). `sdd.yaml` gained `phase_b_page` / `phase_b_decided`; `page:` still names
the staff page, so nothing downstream has to change shape.

Consequence for the review: the proposal is **not frozen** (`proposal_review: pending`,
`proposal_frozen: false`), so widening it now costs one paragraph, not a re-freeze. The brief
(`ui-prototype/my-timetable-responsive-prompt.md`) still names My Timetable only; §3b is the
authority for the student page, and phase B asks the brief's author before it contradicts it.


## Prototype alignment — 2026-11-15 (user, not a review round)

Phase B's visual reference now exists: **`ui-prototype/student-my-timetable-mobile-preview.html`**,
built by copying `my-timetable-mobile-preview.html` and applying the four student deltas of §3b
(read-only, cohort chip + two-item nav, lecturer in the block tip, `Class Hours`). Nothing was
re-built by hand, so the two previews share one stylesheet block and one `card()` builder.

On the user's word the **staff** preview changed at the same time, and the proposal/brief were
brought into line rather than left to drift:

- **The class card gained three tiers** (§3.2): primary = time + venue code; secondary = code +
  type (smaller, grey); tertiary = one right-aligned note line (smallest, grey). The Cancelled /
  Conflict word moved off the line under the time and onto that note line, at note size, keeping
  its status colour — **below 1280px only**; the desktop grid block keeps its shipped order.
- **The subject name left the card below 1280px** (§3.2). It stays in the tip, the sheet subtitle
  and the `aria-label`.
- **Both previews' class pop-up now follows the decided shape** (§10): short tab labels and Close
  filled when it is alone.

`sdd.yaml` gained `reference_prototype_phase_b` and `prototype_alignment`. The proposal is still
**not frozen** (`review_rounds: 2`, `proposal_review: pending`), so these are edits inside the
open proposal, not a re-freeze. No code touched; no browser verification of the real pages.

## Prototype correction — 2026-11-15 (user, not a review round)

Three changes on the user's word, both previews, recorded in proposal §10.4:

1. **`Close` is never the filled primary.** Reverses the 2026-11-14 "Close takes the fill when it
   is alone" rule for this pop-up. The student page was always the alone case, so it shipped a blue
   Close as its normal state.
2. **The Status row reads `Class Cancelled`**, not `Cancelled`; blocks keep the short word for
   width. Spoken label and tip take the long form.
3. **`.badge-cancelled` has no skin in `theme.css`** — a real defect the prototype exposed: the
   Status row printed a transparent pill. First filled with `.badge-conflict`'s two tokens, then
   **changed the same day on the user's word: red belongs to `Public Holiday`** (`.badge-public-holiday`
   and `.badge-conflict` already share `--color-error` in §10.0), so the previews now give
   `.badge-cancelled` the **`.badge-reason`** pair — `--color-surface-variant` on
   `--color-on-surface-variant`, the same grey chip `Class Cancelled` wears on My Replacements.
   Porting still needs its `theme.css` line, so the design batch must carry it.

Checked in the browser at 402px on both previews: zero console errors, the badge computes
`rgb(228,232,238)` on `rgb(90,105,120)` in light theme (the cancelled chip's grey), `Close` computes
`.btn-close-modal`. The card's note line still reads the
short `Cancelled` and the card heights are unchanged.

## proposal Round 3 — 2026-11-15

First review of the two large edits made after round 2 (phase B; the 2026-11-15 prototype alignment).
**Verdict: not ready — three blockers, all cheap.**

### 🔴 Fixed
- **§3b.1 still said the student sheet's footer is the "filled `Close`"** — the exact rule §10.4
  reversed the same day, and both previews already ship the grey `btn-close-modal`. A phase-B
  implementer reads §3b first.
- **"Phase A must be able to ship alone" could not hold as written.** Three phase-A artefacts are not
  builder flags — the `any-pointer: coarse` rule, the below-1280 media queries and the
  `.badge-cancelled` skin — so they reach the student page (and, bare in `theme.css`, Cohort / Venue /
  Arrangement) whatever the builder does. §3b now names the mechanism: every phase-A CSS addition is
  scoped under the opt-in body class; `.badge-cancelled` is the one deliberate all-pages defect fix;
  anything else that cannot be scoped either becomes an explicit all-pages exception or waits.
- **The phone card's overlap affordance was missing from the proposal** although both previews carry
  it (5px left strip, `Overlaps …` line, the same words in the `aria-label`). §3.2 now specifies it as
  a fourth, conditional element, separate from the status note line.

### 🟡 Addressed
- §5's "no new skins" now carries the `.badge-cancelled` exception;
- the touch law is §4's **sixth** desktop exception and has its own §8 acceptance bullet, with the
  Week summary sheet's one-column-below-390px fallback and the measured spills;
- §9d states the default ("no new word" for Pending / approved Replacement) instead of "awaiting";
- factual fixes: `rarityReplace…` → "Replacement Arrangement handoff", "fourth caller" → "second",
  "5 widths" → "the same six widths", "three changes" → "the changes".

## proposal Round 4 — 2026-11-15 — FROZEN

**Verdict: ready to freeze after three line-edits; all three applied with the freeze.**

Round 3's blockers verified resolved and the mechanism is decidable. Closed with the freeze: the
three places that still counted "five" desktop exceptions (now six), the two imprecise clauses in the
new §3b paragraph (`.badge-cancelled` is an **all-pages** defect fix and must not be scoped under the
body class; an unscopable change does not become "ship A and B together" — it becomes an explicit
all-pages exception or waits), and these two log entries.

**`proposal.md` is FROZEN.** Next batch: `design.md`.

## design Round 1 — 2026-11-15

**Verdict: not ready — five blockers, and one of them (B1) was a wrong mechanism, not sloppy
wording.**

### 🔴 Fixed
- **The tablet "window" was built on classes that do not exist.** The design hid "day columns" in the
  existing table; the real builder has **days as rows** and 22 half-hour columns, so no column hiding
  can show two days, and the approved preview's tablet is **transposed** (days across, time down).
  §2/§5/§6 now specify the module's own transposed grid (`#ttWindowGrid`), 2 or 3 columns from the
  selected day, clamped at Mon/Sun — leaving `buildTimetableGrid()`'s output for Cohort / Venue /
  Arrangement untouched.
- **Dropping the `summaryButton` opt-in does not stop `SummarySheet`.** Its `init()` runs on every
  page and its `sync()` relocates `#summarySection` at ≤768 whatever the button says, so two
  relocators would have fought over one node and left two placeholders. `SummarySheet.init()` gains a
  `.tt-responsive` gate; the dead `#summarySheet` frame is acknowledged as harmless.
- **The sticky row and the page order were unspecified and contradicted the preview.** The week row is
  now **wrapped statically** in `.tt-sticky`; the legend bar and `#summarySection` are hidden below
  1280 and stay inline at ≥1280; `#ttTools` exists only below 1280; the chrome include's order is
  pinned so tablet reads week row → strip → tools → grid.
- **The touch law could not reach the shared top bar.** It is rendered outside the page root, so the
  design now records the boundary: the law covers every control **inside** the page, the top bar keeps
  its shipped sizes, and the all-pages option is named and not taken.
- **The desktop status word (proposal §3.3) was missing.** `makeEventBlock()` now prints
  `✕ Cancelled` / `⚠ Conflict` under the time at ≥1280, inside the `responsive: true` branch; the
  details pop-up's Status row prints `Class Cancelled`; `Close` needs no work (already grey).

### 🟡 Addressed
- overlap plural form + every card in a clash; the exact tip expression (`tipCtx · tipLect` inside
  tip2, not a dropped segment); the week-nav tips arrive through the blade's own `$prevTip` /
  `$nextTip` / `$selectTip` (no shared file edited); the sheet's semester-range line is **built**;
  no sticky measurement and no resize listener; the tablet day tap moves the window; the
  `summary-cards-button` amendment is named precisely (`FLIP_PAGES` has My Timetable only, and T11a's
  390/1440 relocation receipts cannot survive the gate); rollback restores the opt-in.

## design Round 2 — 2026-11-15

**Verdict: not ready — the B1 fix landed in §2 but its dead mechanism survived in §5/§6.**

### 🔴 Fixed
- §5 and §6 no longer teach `.day-col` / `.tt-window` (they matched nothing in the real grid); both
  now describe the transposed `#ttWindowGrid` and its mount point (under the strip, in the chrome
  partial), with the legacy table hidden in that mode and the flag doing only the tip migration and
  the desktop word.

### 🟡 Addressed
- "44 half-hour columns" → **22** (the real `hours` axis, 11 `colSpan=2` headers);
- `.tt-sticky` is now scoped below 1280 (the preview keeps desktop controls in flow, and an
  unqualified sticky row was a seventh desktop change);
- print un-hides the legend bar and `#summarySection` whatever the mode — without it a printout from a
  narrow window would lose them, because the sheet's own print unrelocate is gated off here;
- the tip expression is written out (`tipCtx · tipLect` live inside tip2);
- the desktop word is stated to live **inside the `responsive: true` branch**;
- "2-day window" no longer reads as "the strip shows two buttons".

## design Round 3 — 2026-11-15 — FROZEN

Confirm round: the residue is gone (§5/§6 now describe one mechanism, with a mount point), the numbers
match the code (22 columns), and every round‑2 🟡 verified. No new contradiction found.

**`design.md` is FROZEN.** Next batch: `specs/`.

*(Round 3 above was run before these two landed. Its verdict was "ready to freeze", with two
one-line 🟡s applied with the freeze: §9 row 2's width cell (stickiness is ≤1279) and `sync()` now
stated to set `data-tt-mode` on the page root, which three rules key on. Also renamed the details
"sheet" to "modal" in §9 row 8 so the two words cannot collide.)*

## specs Round 1 — 2026-11-15

**Verdict: not ready — three blockers, two of them contradictions between frozen artifacts.**

### 🔴 Fixed
- **The cancelled hover tip could not be both "byte-identical" and "long form".** Proposal §3.7 and
  design §6 make the migrated tip identical to today's (… `· CANCELLED`); proposal §10.4 says the tip
  takes `CLASS CANCELLED`. Resolved in favour of the frozen pair — **the tip stays short**, the modal
  Status row and the agenda card's spoken status take the long form — and recorded as a **provisional
  user ruling** with the one-string cost of reversing it.
- **The window-mode cell (768–1279) had no content contract at all.** Added R3b: the brief's tablet
  sizes, the status word on the note line, the overlap strip + line on every cell of a clash, the same
  focusable button / details modal / `aria-label` / `data-tip` as a desktop block, and an explicit "not
  R3's phone sizes".
- **The `e2e/timetable.spec.js` gate was unachievable and the artifacts mischaracterized it.** The file
  asserts that `#semesterProgress` / `#weekSubtitle` **are visible** — both were removed on 2026-11-13,
  so it is red before this lane. R10 now names it as an amended test (assertion removed or inverted,
  with a dated comment) and forbids "fixing" it by resurrecting that DOM.

### 🟡 Addressed
- the 48px law enumerated control by control (design §5's list is exemplary), with the print button's
  inclusion reconciled against proposal §5;
- `agenda` mode must hide the legacy table too;
- `summary-cards-button`'s amendment listed as five receipts plus the page list;
- `Class Cancelled` is **page-scoped** through a cfg flag on the shared modal, not an all-pages change;
- day accessible names pinned (`Monday, 3 classes, selected` / `, public holiday`);
- the three brief-only commitments carried in (R5b): week-label re-format, same weekday on week
  change, tapping the selected day is a no-op;
- the tools block asserts `toBeHidden()` at ≥1280; the sheet body's internal order pinned;
- recorded that design §9 row 10 ("one card at a time") and §3's strip cells are superseded by this
  spec (every card of a clash; all seven chips at every width).

---

*Round 2 (confirm) and the freeze are recorded below once the round ran.*

## specs Round 2 — 2026-11-15 — FROZEN

**Verdict: freeze after four declarative edits to the frozen design; all four applied with the
freeze.** The three round-1 blockers landed correctly in the spec; what remained was residue in the
design that would have travelled into `/sdd-apply` as a live contradiction:

1. design §4.2's "the hover tip takes the long form" → now says the tip stays the short `CANCELLED`
   and cites the spec's provisional ruling (frozen §3.7 + §6 are the pair that wins);
2. design §11's "the two pages' removed-element assertions stay green" → now states that
   `e2e/timetable.spec.js` is **red before this lane** and is amended, never "fixed" by resurrecting
   the removed DOM;
3. `.tt-agenda` added to the print hide list (without it a printout at ≤767 emits the agenda *and* the
   restored table);
4. the print button is "untouched in function", with the coarse 48px law still reaching it.

Also aligned the design's evidence widths to the spec's eight (360 / 430 added) and fixed two spec
typos. **`specs/timetable-responsive/spec.md` is FROZEN.** Next batch: `tasks.md`.

## tasks Round 1 — 2026-11-15

**Verdict: not ready — three blockers, all inside tasks.md.**

### 🔴 Fixed
- **The pin was ordered after the opt-in it guards.** It sat in Batch G, behind the builder edit and
  both page opt-ins, so its "baseline" would have been captured against the changed builder. New
  **T0a** is now the first task and edits nothing: it captures the three callers' `#timetable`
  `outerHTML` for a fixed week plus the two existing geometry runs (the ≥1280 *before*), and T15
  compares against that.
- **The student page kept its `summaryButton` opt-in.** With `SummarySheet` gated off, that header icon
  would survive, stay visible at ≤768 and call a sheet that never opens. T14 now removes it, with the
  reason inline.
- **The next-class hint had no data source.** `events()` is the current week only and `eventsData` is
  closure-local, so a future-week jump was impossible without a second data shape. `init()` gains
  `findNextClass(fromWeek, fromDay)`, implemented by the page over its own `eventsData`; design §6's
  cfg list now carries it.

### 🟡 Addressed
- the gate is now applied **before** the sheet that moves `#summarySection` (the old T9/T10 order would
  have had the implementer debug the exact two-relocator fight the design ruled out);
- the responsive spec split into three (phone / tablet+sheet / touch+desktop);
- T11 gains `data-tt-page="staff"`; T17 names **both** e2e page entries; T18/T19's "Satisfies" made
  honest; T2's list gains R5.

## tasks Round 2 — 2026-11-15 — FROZEN

**Verdict: ready to freeze after four one-liners; all applied with the freeze.** The three round-1
blockers verified resolved and the order is executable top to bottom.

- the preamble's pin cross-reference now reads **T15** (it still said T14);
- T15's wording matches its slot — it runs first in Batch G and compares against T0a's baseline, so it
  still catches builder drift from every batch above;
- design §6's cfg list now carries `findNextClass` (with the reason) and marks `weekNav` optional;
- T16a now claims **R9** with the student root's arms named (read-only cards, `Class Hours`, cohort
  chip, request state as colour + date note).

**`tasks.md` is FROZEN. All four batches are frozen; the lane is ready for `/sdd-apply` and nothing has
been applied.**
