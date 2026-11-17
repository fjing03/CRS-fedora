# Review log — Summary card back spacing and explanation detail

> Change: `summary-card-detail-copy`
> Reviewer rounds are recorded here. A round is only "closed" when every
> blocking item is either fixed or explicitly waived by the user.

## Round 0 — 2026-11-13 (author pre-check, before review)

This lane was opened after the user chose a separate lane over extending
`my-timetable-ui-cleanup`. Nothing has been written to code yet.

### Findings already resolved during prototyping

- **`nth-child(-n+2)` reserve was wrong.** Reserving the button's corner only on
  the two lines level with it made "Still needs action" wrap at 390px, because
  line 1 lost 38px while line 3 kept it. Replaced with a uniform 38px reserve.
  Swept 30/32/34/36/38/40/44/46px over 30 cards at 390px: 30–40 pass, 44
  re-wraps "Available", 46 re-wraps two. 38px chosen, mid-band.
- **`card['class']` cannot key the detail data.** `card-total` is used for four
  different cards and appears twice on `request-approval` (Total Requests and
  Total Reviewed). Detail must be passed per card. The existing `$descriptions`
  fallback map has the same latent bug; left untouched, recorded here.
- **Proposed spacing verified, not assumed.** Live pages measured with the
  patch applied: 18px above / 18px below, 3 lines, 0 text under the button, at
  both 1360px and 390px on all six pages.
- **Current spacing reproduces the complaint.** 7px above / 48px below — the
  block is top-jammed, which is what the user saw.
- **Four bullets overflow 122px at 390px.** Measured widths: 136, 146, 120,
  106px. Fixed by shortening three and reordering one; the Venue change drops
  no words.
- **No JS change needed.** `SummaryCards.open()` already prints
  `.summary-explanation-source`'s `innerHTML`, so the partial can emit the new
  markup into that node without touching the controller. Confirmed by reading
  `public/js/ui-common.js` (`SummaryCards`, ~line 1971).

### 🔴 Outstanding

- (empty — no blocking items raised yet)

### Next

- Run the `sdd-reviewer` round before any code is written.

## All-artifacts Round 1 — 2026-11-13 10:20

**Process deviation, recorded honestly:** this round reviewed `proposal.md`,
`design.md`, `specs/` and `tasks.md` together instead of one batch at a time
with a freeze between each. It is therefore **not** a valid Batch-1 gate and
nothing is frozen by it. All four remain open and were fixed together below.

### 🔴 Fixed
- **Formula chips carried hardcoded numbers that contradict the live card.**
  No view passes `'value' =>` (verified: zero matches), so the partial prints
  `0` and JS overwrites it; a chip saying "36 slots = 18 h" would lie on any
  other week. The prototype also contradicts itself (Total Classes chips
  6+2+1 = 9 against a front value of 6). Decision: **chips carry terms and
  operators only, never a live number.** SD-05 rewritten accordingly.
- **SD-02 claimed 320–1440px without measuring it.** Measured: the patch
  *regresses* 320px (baseline 5 lines/spill −14 → patched 6 lines/**+14px
  spill**) and 360px also spills (+5px). Fixed by a single-column summary bar
  below 390px; verified 320/360/375/390/414 all at 3 lines with no spill.
  SD-02 now states the verified widths and the sub-390 rule.
- **"Approved or rejected" was not actually preserved** in Total Reviewed's
  detail, though SD-03 and T5 both promised it. Added to the items.
- **`card['class']` in the prototype is wrong for three cards** (Venue My
  Teaching Classes, Venue Occupied, Cohort My Teaching Classes). T4/T5 now say:
  copy `detail` and bullets only, never `class`.

### 🟡 Addressed
- **§3 arithmetic corrected: 110px, not 122px.** Confirmed by measurement:
  desc 160 − pad-right 38 − list padding-left 12 = **110**. The old figure
  omitted the list indent and would have let a 120px bullet be judged as
  fitting.
- **Width evidence came from bold text, but shipped bullets are plain.**
  Recorded; the real page was re-measured and Approved Requests still wraps to
  4 lines at 390px, so the shortening still stands.
- **Venue "Available" reorder is a no-op** under a uniform reserve (line 3 is
  the same width as line 1) and its 106px bullet fits 110px. Dropped; Venue
  now needs no copy change.
- **`.explain-*` must not be scoped under `.summary-section--flip`** — the
  modal is a sibling of `.summary-section`. T2 now states they are unscoped.
- **Data shape could not express chip colours** (`is-blue`/`is-pending`) and
  carried no wrapper for the muted meaning. Added `'c'` and a required
  `<span>` around the meaning.
- **Nothing told implementers to keep `explanation`** once `detail` exists.
  T3 now makes it mandatory, because deleting it reactivates the exact
  `$descriptions[class]` collision this lane avoids.
- **Missing tasks:** `php artisan test --filter=ThemeCssIntegrityTest`
  (parses `theme.css`; a typo in the new rules fails it), a dark-theme check,
  and the `PI_Chat_Context_Handover.md` §0/§1 update.
- **Prototype prose described the rejected `nth-child` scheme** and mislabelled
  three cards' classes. Corrected in T1.

### 🔴 Outstanding
- (empty — all blocking items fixed in the artifacts; needs a clean re-review)

### Verified independently in this round
- 390px: card 178px, text column **110px**, 3 lines after shortening.
- 320px: baseline fits (5 lines, −14), patched spills (+14) → the sub-390
  single-column rule is required, not cosmetic.
- `SummaryCards.open()` prints `.summary-explanation-source.innerHTML`
  (`ui-common.js:2248-2261`) → the "no JS change" claim holds.
- `card-total` really is used for four meanings and appears twice on
  `request-approval` (lines 297 and 309).
- `ThemeCssIntegrityTest.php` exists and parses `theme.css`.

## All-artifacts Round 2 — 2026-11-13 10:45

User decision after Round 1: **option A — keep live numbers out of the formula
chips.** The chips state the relationship only. A JS feature to fill them at
open time was explicitly declined for this lane.

### 🔴 Fixed
- **Chips are now term-only everywhere.** Prototype rewritten: 11 formula
  definitions converted from `{v:'36', t:'slots × 30 min'}` to
  `{v:'slots × 30 min'}`, and the renderer stopped printing a numeric caption.
  Verified: **0 chips begin with a digit**.
- **Redundant terminal chip dropped where it repeated the card title.** Total
  Reviewed now renders `approved + rejected + completed`; the `= reviewed` chip
  both repeated the heading and pushed the strip to a second row.
- **Sub-390px single column added to the prototype** so the reference shows the
  fix rather than only describing it.
- **Prototype prose corrected** — the header and legend no longer describe the
  abandoned `padding-right: 8px` + `nth-child(-n+2)` scheme, and the "five
  bullets" count is now three.

### 🟡 Addressed
- Chip type resized (`19px` → `12.5px`): 19px was sized for digits and is too
  heavy for words.
- `design.md` §4 now records both the no-numbers rule and the
  drop-the-redundant-terminal-chip rule.

### 🔴 Outstanding
- (empty)

### Verification
- **Live pages, full patch + shortened copy, 320 / 360 / 375 / 390 / 414 / 768 /
  1024 / 1360px, all six pages: max 3 lines, max spill −12px.** Every width ok.
- Prototype at its two faithful states: desktop 235px cards — all 6 fit in both
  variants; phone 390px frame (178px cards) — Current fails on My Replacements
  and Request Approval, Proposed fits all 6.
- Formula strips render one row (`h=36px`) for reviewed / total / hours.
- Zero page errors.

## All-artifacts Round 3 — 2026-11-13 11:05

Third review found 4 blockers. All closed; two were genuine holes in the
previous round's "fixed" claims.

### 🔴 Fixed
- **Media-query placement would have silently disabled the sub-390 fix.** Both
  the new rule and the existing `@media (max-width: 768px)` rule are
  `.summary-section--flip .summary-bar` (specificity 0,2,0), so source order
  decides. Proven against the **real** `theme.css`: inserting the 389px rule
  *before* the 768px block leaves 2 columns `'155px 155px'` at 320px; inserting
  it *after* gives `'320px'` (one column). Design §2 and T1 now state the
  placement requirement explicitly, with the reason.
- **"Approved or rejected" was still absent** from Total Reviewed's
  `detail.items` — the previous round claimed it fixed but only reworded. Added
  as the "Already decided" meaning: "Approved or rejected, plus completed."
- **`normal + replacement + pending = total` is false.** `computeSummary()`
  does `total = evs.length - cancelled`, so **conflict
  blocks are counted in total**; the demo data has five of them. The `= total`
  chip is gone. Recorded as design §8: this is a pre-existing value/copy
  contradiction, not something this lane should quietly "fix".
- **SD-06 and the proposal's out-of-scope list forbade the very column change
  SD-02 requires.** SD-06 now says "unchanged at 390px and above; the sub-390
  single column is the only exception", and the proposal's out-of-scope line
  now names the 5/3/2-column layout, not "column widths".

### 🟡 Addressed
- **Venue copy change purged from three places**: proposal Impact table, the
  proposal in-scope bullet, and the prototype's `next:` array + note.
- **Count drift unified**: four bullet edits, three distinct wordings.
  Corrected in `proposal.md`, `tasks.md` T8 and the prototype header.
- **SD-02's "two columns at 390px and above" was wrong**: it is 2 columns
  390–768, 3 at 769–1024, 5 above. Reworded.
- **T1's prototype-correction bullet retargeted** — the prose it named was
  already fixed; it now points at the genuinely stale helper text.
- **`10px` radius → `var(--radius-md)`** (SD-08 forbids literals).
- **Venue's provenance corrected** in design §5: it reads a precomputed
  `totalsData`, it never calls `computeSummary`, so T6 must not send an
  implementer looking for an `updateSummary()` that does not exist.
- **`ui-common.js` line reference corrected** 1976-1984 → **`SummaryCards.open()`**.
- **My Replacements' hours chip rewritten** to `sum of class lengths =
  time to replace`; `c.duration` is variable, so "slots × 30 min" was wrong.
- **Proposal §Why** now says the modal shows the relationship, not the sum,
  matching the Round-2 decision.

### 🔴 Outstanding
- (empty)

### Verification
- Real-`theme.css` cascade test at 320px: rule before the 768 block → 2 columns
  (broken); after → 1 column (fixed).
- Prototype: 0 numeric chips, 0 `undefined` text; desktop 235px cards all 6 fit
  in both variants; 390 frame Current fails 2 pages / Proposed fits all 6;
  **all 30 modals** open with content; 0 page errors.
- Formula strips single-row (36px) for total / hours / replacement-hours /
  reviewed.

## Re-sync — 2026-11-14 (not a review round)

This lane was idle while other lanes kept shipping summary-card work. The
artifacts were reconciled against the **actual working tree** so that the lane
describes shipped reality. This entry records every **decision-level change**
rather than quietly editing frozen text.

### Method

Read the current code first, then measured on the live dev server
(`php artisan serve` on `:8000`, `DB_DATABASE=class_replacement_fresh`, staff
`5425` / student `24DFT0001`), then edited the artifacts. No app code, no tests,
no other lane's files, no `docs/demo-db-data-changes.md` were touched.

### The headline: **nothing in this lane has shipped**

| Premise | Verified state |
|---|---|
| `line-height: 1.45` | **absent** — still `1.15` (`theme.css:1771`) |
| `padding-right: 38px` | **absent** — still `30px` (`theme.css:1772`) |
| `gap: 4px` | **absent** — still `0` (`theme.css:1783`) |
| `.summary-info` top-right | **absent** — still `bottom: 0` (`theme.css:1795`) |
| `@media (max-width: 389px)` | **absent** |
| `.explain-*` rules | **absent** — 0 matches in `public/`, `resources/` |
| `$card['detail']` | **absent** — nowhere in partial or views |
| Modal is one paragraph | **yes** — 1 `DIV.summary-explanation`, 0 `<br>`, 0 `<li>` (the `body.innerHTML` line in `SummaryCards.open()`) |
| Four bullets wrap at 390px | **yes** — exactly 4 with the patch applied |

T1–T8 all remain open.

### 🔴 Decision-level changes to the artifacts

1. **The bullets are no longer plain text — §3's blanket claim was wrong.**
   All six flip pages now pass three `<strong>`-accented bullets each, shipped
   by `my-timetable-ui-cleanup`. The earlier statement "the shipped bullets are
   plain text (the prototype shows them bold)" is true only for the **four
   bullets this lane shortens**. Rewritten; T5's "add no `<strong>`" retained
   and its rationale restated.

2. **The reserve sweep band was wrong: ≤39px, not ≤40px.** Re-measured at
   390px over 25 cards with the shortened copy: 36 / 38 / **39px → 0 wraps**,
   **40px → 1** ("Includes completed" on Request Approval · Total Reviewed),
   41–42px → 1, 44 / 46px → 2. The old text claimed "30–40 passes". 38px stays
   the choice, but the margin is 1px, not 2. This matters because T1 previously
   only warned about 44px.

   *Self-correction:* my first attempt at this sweep produced a
   non-monotonic result (30px→3, 38px→4, 39px→0) because it appended a new
   `<style>` tag per measurement instead of setting one value and forcing a
   reflow. The numbers above are from the corrected method and are monotonic.
   A note in `design.md` §2 warns against the broken method.

3. **375px also regresses without the sub-390 rule (+5px, 5 lines).** The old
   table claimed only 320px and 360px regressed. 375px is inside the `< 390px`
   band so the fix already covers it — **no design change** — but the stated
   evidence was wrong and is corrected.

4. **Two baseline numbers in the width table were wrong.** 320px baseline is
   **6 lines / spill 0** (not 5 / −14) and 360px is **5 lines / −14** (not
   4 / −29). This also means the **shipped CSS already spills at 320px**, which
   the spec must not pretend otherwise — recorded as a `[pre-existing-gap]`
   marker under SD-02 rather than silently dropped.

5. **The 375px column is 103px, not 102px; the 375px card is 171px, not 170px.**
   Cosmetic, but the column figure is what §3's fit judgement rests on.

6. **§5's claim "traceable to the page's own `updateSummary()`" was false for
   Venue.** Venue has no `updateSummary()`; it reads precomputed `totalsData`
   (`VenueTimetable.php:824`, `myHours = count * 0.5`). The table already said
   so in a footnote while the prose contradicted it. Prose fixed.

7. **New `design.md` §9** records what the other lanes changed and what it
   means here — chiefly that the back face *already states the formula in
   words*, so the modal's `items` must expand those bullets rather than assert
   a different rule. This is a real constraint the original design could not
   have known.

8. **New "What is already done" block in `tasks.md`** so an implementer does not
   re-create the flip cards, the partial branch, the sibling modal, the 96px
   height or the `valueId`s.

### 🟡 Corrections of fact, no design change

- `ui-common.js` `open()` is at **`SummaryCards.open()`**, and it has drifted twice; cite the function, not the line. The `innerHTML` line
  is **2261**.
- The modal is a sibling of `.summary-section` because the section div **closes
  at line 82** and the modal opens at **84** (`ui-summary-bar.blade.php`).
- The prototype lives at **`ui-prototype/`** (repo parent), not
  `ui-prototype/`. Both the proposal and the design pointed at a path that does
  not exist inside `CRS-fedora-backend`.
- `ThemeCssIntegrityTest` exists and does assert `.summary-card` plus brace
  balance (`tests/Feature/ThemeCssIntegrityTest.php:139`).
- Re-confirmed: `computeSummary` still counts conflict blocks in `total`
  `computeSummary()`, which sets `total = evs.length - cancelled`, so §8's open question stands unchanged.
- Re-confirmed: Request Approval's Total Reviewed is
  `['Approved','Rejected','Completed']` (`request-approval.blade.php:717`).
- Re-confirmed: zero `'value' =>` matches, so no chip may carry a live number.
- Re-confirmed: `replacement-history` includes the partial **without**
  `flipCards` (`replacement-history.blade.php:154`), so the no-`detail` fallback
  still cannot be exercised on a live page.

### What could NOT be verified

- **The student page was not in the bulk width sweep.** It needs the student
  login; it was checked separately only for card count (5), ids (`sum*`),
  labels, and computed `line-height: 13.8px`. T7 must sweep it properly.
- **`/my-replacements` and `/request-approval-ui` short-bullet behaviour was
  measured with copy substituted in the DOM**, not from an implemented
  `detail`/short-bullet build — because none exists. The 3-line result is
  therefore a prediction of the applied patch, not a shipped measurement.
- **The `.explain-*` modal has never rendered**, so its dark-theme contrast,
  chip wrapping and footnote spacing are unverified by construction. T7 keeps
  those checks open.
- **`updateSummary()` was not re-read line by line for every page**; the
  formula table relies on `computeSummary`, `VenueTimetable.php:824` and
  `request-approval.blade.php:717`. T6 is still the place to confirm the rest.

### Deliberately NOT changed here

- `page-changelogs/replacement-home-changelog.md` and
  `request-approval-changelog.md` say the info button "opens the full **formula**
  in the shared 420px explanation modal". **There is no formula** — the modal
  shows a paragraph. That copy belongs to another lane's entry; flagged to the
  user rather than edited from here.
- The frozen Round 1–3 text above is left intact; this entry supersedes it
  rather than rewriting history.
