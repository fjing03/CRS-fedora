# Review log — arrangement-summary-and-slot-rows

Append-only. Batch order: proposal → design → specs → tasks.

## proposal Round 1 — 2026-11-14

Reviewer: independent read-only (`commandcode/z-ai/glm-5.3-flash`).

### 🔴 Fixed
- The brief invented `#conflictToggle`; the real id is `#conflictCollapse` (button 2468, listener
  7502). Corrected in `explore-brief.md` — the propose/design baseline would have propagated a
  phantom selector.
- §4 claimed `confirm-guards.spec.ts` clicks `.slot-dd-item-label`; it clicks `.slot-dd-item`
  (no label reference at all). Evidence corrected, and `arrangement-conflict-autoselect.spec.ts:144`
  — which *reads* the label text — added to the untouched-spec list.
- §4 said the Blade is dirty with another session's work. It is **clean** (`bd4a8e8`); what is
  untracked is the spec folder, including `selection-summary-collapse.spec.ts`. The staging
  constraint was re-stated against the real tree.

### 🟡 Addressed
- Line references tightened (label template 6737-6740, `cohortHtml` 6723-6725, listener 7520-7531,
  icons 3654-3659).
- The `👥` in §1 is now flagged as the real inline SVG glyph, not an emoji.
- The row stack is specified as `span` rows (icon first) so the implementer does not invent markup.

### 🔴 Outstanding
- None.

## proposal Round 2 — 2026-11-14

### 🔴 Fixed
- Two line references from round 1's "tightening" were themselves wrong and are now measured:
  the template string is `item.innerHTML` **6733-6736** (label span on **6734**), and the cohort tag is
  `const cohortHtml` **6721** → span **6722** → `: ''` **6723**. The round-1 note claiming these were
  corrected is superseded by this round.

### 🟡 Addressed
- "Three more specs" → **four** (the enumeration was already complete; the count was not).
- Untracked wording narrowed: of the touched specs only `selection-summary-collapse.spec.ts` is
  untracked; `venue-selector-responsive.spec.ts` is untracked but not this lane's.
- The zero-cohort case is now stated: no cohort row at all.
- The click-line list is marked not exhaustive (also 65, 98, 121).
- The dead fold CSS (`.sel-summary.is-collapsed .sel-summary-body`, blade 521) is recorded as a
  design-batch decision rather than left to the implementer.

### 🔴 Outstanding
- None.

## proposal Round 3 — 2026-11-14

### 🔴 Fixed
- **`tests/arrangement-mobile.spec.ts` was missing from the row-touching set.** Its helper
  (641-654) locates a row by substring on the whitespace-collapsed `textContent`. Rather than
  rewrite it, the lane **drops the day-first date change** it had invented: §2.2 row 1 keeps
  `Nov 10, 2026`, so every token that helper needs is unchanged, and the proposal now names the file
  as "must keep passing untouched" with the reason. The date-rule drift is **parked** as §7.

### 🟡 Addressed
- The `👥` stand-in is now flagged as the real inline users SVG (blade 6722).
- The "never a rail" evidence is corrected to the CSS (rail rules 1894-1929 / 2007-2011 name only the
  conflict and details cards).
- Cohort-source range corrected to 6706-6721; the ask's listener reference corrected to 7520-7531.
- §4 now states exactly which assertion in `rearrangement-toolbar-tooltips.spec.ts` breaks (`@ B`)
  and which survive (the date regex, the cohort tag, the star-absence check).

### 🔴 Outstanding
- None.

## proposal Round 4 — 2026-11-14

### 🔴 Fixed
- The tree-state sentence said `arrangement-mobile.spec.ts` "carries another session's uncommitted
  edits" — it is tracked and **clean**. Re-measured: modified specs are `top-zone-round2.spec.ts` and
  `venue-timetable.spec.ts` (other work, not to be swept); untracked are
  `selection-summary-collapse.spec.ts` and `venue-selector-responsive.spec.ts` (only the first is ours).

### 🟡 Addressed
- The `icons` object is not "the hover pop-up's": it lives inside `updateSelectionSummary()`
  (3653 in 3571-…), out of scope at the slot-row builder. The brief now names the mechanism gap and
  hands the choice (copy verbatim vs hoist) to the design batch.
- `confirm-guards.spec.ts` pointer corrected (:156 clicks, :146 is a count probe).

### 🔴 Outstanding
- None.

## Lane closed — 2026-11-14 (user: "SKIP & make the two changes now")

The user stopped the lane after design round 1 and granted the **small-change exemption**
(`prompts/for-ai-to-read/my-coding-rules.md` §5): confined to one page's own CSS / markup / script,
no data or backend logic, one `git revert` undoes it, and nothing here becomes a pattern a third
page reuses. The two edits were applied directly.

What the lane still bought, and was used:

- the exact regions (button 2594-2606, listener 7518-7531, `.sel-summary.is-collapsed` rule 521,
  label template 6733-6736, cohort build 6721-6723);
- the spec inventory: **two** specs pin the old behaviour and had to be rewritten honestly
  (`selection-summary-collapse.spec.ts` for the fold, `rearrangement-toolbar-tooltips.spec.ts` for
  `@ B`), and five more read or click the row and must keep passing;
- the finding that `icons` lived inside `updateSelectionSummary()` and could not be reached from the
  row builder — so the glyphs were hoisted to one `RA_ICONS` constant instead of duplicated;
- the cohort-count semantics (raw list length, not de-duped codes).

Applied and verified: four labelled rows with glyphs, badge top-aligned, Summary permanently open,
both themes, 0 page errors. `selection-summary-collapse.spec.ts` passes.

**Two spec failures in `rearrangement-toolbar-tooltips.spec.ts` are pre-existing and data-driven:**
`?session=67` now redirects to `/my-replacements?blocked=request-pending` (that session has a pending
request in the restored testing data), so the page never renders and `#weekSelector` is absent. This
lane did not touch that guard.
