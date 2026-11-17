# Tasks — Summary card back spacing and explanation detail

> Change: `summary-card-detail-copy`
> Frozen inputs: `proposal.md`, `design.md`, `specs/summary-detail/spec.md`
> **Re-synced 2026-11-14; applied and verified 2026-11-14.** All T1–T8 complete.
> The `state:` lines below record what the working tree looked like *before* this
> apply — they are history, not current status. Receipts: `apply-log.md`.

## What is already done (not by this lane — do not redo)

- [x] Flip cards, `.summary-card-back`, `.summary-points`, `.summary-info`,
      the sibling `#summaryExplainModal` and `SummaryCards` all ship.
- [x] All six views pass `bullets` + `explanation` per card.
- [x] 96px card height and every `valueId` are already correct.

This lane starts at T1.

## T1 — Back spacing CSS (0.5h) · `state: shipped 2026-11-14`

- [x] In the `.summary-section--flip` block of `public/css/theme.css`, set
      `.summary-card-back { justify-content: center; padding: 6px 8px; }`
      (currently `justify-content: flex-start`).
- [x] Set `.summary-card-back .summary-description { line-height: 1.45;
      padding-right: 38px; }` (currently `1.15` / `30px`, lines 1771–1772).
- [x] Set `.summary-points { gap: 4px; }` (currently `gap: 0`, line 1783).
- [x] Move `.summary-info` to `top: 0; bottom: auto;`, keeping its 44×44px
      target and 16px glyph (currently `bottom: 0`, line 1795).
- [x] Add `@media (max-width: 389px) { .summary-section--flip .summary-bar
      { grid-template-columns: 1fr; } }` **after** the existing
      `@media (max-width: 768px)` flip block. Required, and placement is part
      of it: both selectors are specificity 0,2,0, so if this block sits
      earlier the 768px 2-column rule wins and the spill returns at 320/360px.
      Without the rule at all the patch spills +14px at 320px and +5px at
      360px **and 375px**.
- [x] Comment the reserve: `38 + 8px padding = 46px, clear of the 44px button;
      39px is the last reserve that does not re-wrap a bullet, 40px re-wraps
      "Includes completed"`.
- [x] Leave `ui-prototype/summary-card-back-preview.html` as it is. Its
      header, legend, bullet count ("four bullet edits, three distinct
      wordings") and the dropped Venue-reorder note are **already corrected** —
      verify rather than edit.
- [x] Do not touch `.summary-card` height, the value colour map, or the column
      layout at 390px and above. The single-column rule below 390px is the one
      intended exception.

## T2 — Modal detail CSS (0.5h) · `state: shipped 2026-11-14` (0 `explain-` matches)

- [x] Add `.explain-formula` (flex, wrap, 6px gap, 16px bottom margin).
- [x] Add `.explain-chip` (surface-variant + outline, `var(--radius-md)`,
      centred bold label) and `.explain-chip.is-blue` / `.is-pending` using
      `--color-on-primary-container` / `--color-on-tertiary-container`.
- [x] Add `.explain-plus` for the operator glyphs.
- [x] Add `.explain-list` (grid, 11px gap, no markers), `.explain-list li`
      (13px, `1.4` leading, bold term) and `.explain-list li span`
      (`--color-on-surface-variant`, this is what makes the meaning muted).
- [x] Add `.explain-foot` (11.5px, muted, hairline top border).
- [x] **These rules must be unscoped** (or scoped to `.modal--summary`). Do
      NOT nest them under `.summary-section--flip`: `#summaryExplainModal` is
      a sibling of `.summary-section`, so a scoped rule styles nothing while
      still passing a "content exists" check.
- [x] Confirm every value is a token; no hard-coded colour.

## T3 — Shared partial (1h) · `state: shipped 2026-11-14` (`detail` read nowhere)

- [x] In `resources/views/partials/ui-summary-bar.blade.php`, accept optional
      `$card['detail']` with `formula`, `items`, `foot`.
- [x] Render it into `.summary-explanation-source`, falling back to the
      existing `explanation` paragraph when `detail` is absent.
- [x] Emit `detail` only in the flip branch; the non-flip branch keeps today's
      `.summary-description` string.
- [x] `items` is `[term, meaning]`: emit the term in `<strong>`, then `<br>`,
      then the meaning inside `<span>`. Without the `<span>` the meaning is not
      muted.
- [x] Chip fields: `'v'` label, optional `'c'` colour class, `'op'` operator.
- [x] Use `{!! !!}` for term and meaning (both carry HTML in practice),
      matching the existing `{!! $explanation !!}` at line 56.
- [x] **Keep `explanation` on every card even when `detail` is present.** It is
      what the current modal prints and what the fallback uses; all six views
      already pass it, so this is a "do not delete" instruction, not new work.
      If it were deleted, `$card['explanation'] ?? $desc` would fall through to
      `$descriptions[$card['class']]`, which is wrong for `card-total` and
      `card-replacement` — the exact class collision this lane exists to avoid.
- [x] Do **not** key anything on `$card['class']`; `card-total` covers four
      meanings and appears twice on `request-approval`.
- [x] Leave `$descriptions` untouched.

## T4 — Detail data, four timetable pages (1.5h) · `state: shipped 2026-11-14`

- [x] `my-timetable.blade.php`: `detail` for Total Classes, Teaching Hours,
      Replacements, Pending, Conflicts (5 cards, verified).
- [x] `student-my-timetable.blade.php`: same five, student voice
      ("Class Hours", "your class"); ids stay `sum*`. Verified live: labels are
      Total Classes / **Class Hours** / Replacements / Pending / Conflicts, ids
      `sumTotal` / `sumHours` / `sumReplacement` / `sumPending` / `sumConflict`.
- [x] `cohort-timetable.blade.php`: same five (verified).
- [x] `venue-timetable.blade.php`: Total Slots, Available, My Teaching Classes,
      My Teaching Hours, Occupied. **No bullet copy change** — measured 0
      wrapping bullets at 390px with the patch applied.
- [x] Copy `detail` and bullets only from the prototype. Its `class` values are
      wrong for three cards (Venue My Teaching Classes, Venue Occupied, Cohort
      My Teaching Classes); taking them would change card colours. Verified: the
      live Venue cards use `card-total`, `card-available`, `card-replacement`,
      `card-hours`.
- [x] **Do not restate the shipped bullets in `items`.** They already say
      `Normal + blue` / `+N = pending` / `No red blocks`; the items must expand
      those, not assert a different rule (`design.md` §9.1).

## T5 — Detail data, two replacement pages (1h) · `state: shipped 2026-11-14`

- [x] `replacement-home.blade.php`: `detail` for Classes to Arrange, Class Hours
      to Replace, Pending, Approved, Rejected; shorten
      `Replacement confirmed` → `Confirmed` (line 256).
- [x] `request-approval.blade.php`: `detail` for Total Requests, Pending,
      Approved, Rejected, Total Reviewed; shorten
      `Replacement confirmed` → `Confirmed` (line 304),
      `Lecturer must re-arrange` → `Arrange again` (line 307),
      `Approved or rejected` → `Approved/rejected` (line 310).
- [x] Confirm each shortened bullet's full wording appears in that card's
      `detail` items — including **"Approved or rejected"** on Total Reviewed.
- [x] Add no `<strong>` to the shortened bullets. These four are plain on the
      live pages even though most other bullets now carry `<strong>`; keeping
      them plain is the minimal change (`design.md` §3).

## T6 — Check the formulas (0.5h) · `state: done 2026-11-14`

- [x] Read each page's summary arithmetic again and confirm every chip term and
      operator matches it (design §5). Note Venue has **no** `updateSummary()`
      — it reads `totalsData` from `VenueTimetable.php`.
- [x] Confirm no chip contains a live number (design §4). Zero `'value' =>`
      matches today, so any literal would be wrong.
- [x] Confirm `request-approval`'s Total Reviewed excludes Pending — verified
      at `request-approval.blade.php:717`:
      `['Approved','Rejected','Completed']`.
- [x] Confirm Teaching/Class Hours describe 30-minute slots and exclude
      cancelled classes.
- [x] Confirm **no** chip asserts `= Total` on Total Classes: `computeSummary`
      counts conflict blocks in `total` (it sets `total = evs.length - cancelled`), so that sum is
      false whenever a red block exists.
- [x] My Replacements' hours come from `c.duration` (variable), not a slot
      count — its chip must say `sum of class lengths = time to replace`, not
      "slots × 30 min".
- [x] Confirm each `detail` agrees with its card's **shipped bullets**; the
      back face already states the formula in words (`design.md` §9.1).

## T7 — Verify (1.5h) · `state: verified 2026-11-14`

**Already measured during the re-sync** (with the patch injected, not applied),
so T7 should confirm the applied build rather than rediscover these:

- 320 / 360 / 375 / 390 / 414 / 768 / 1024 / 1360px, five staff pages:
  18px above / 18px below, **max 3 lines, max spill −12px, 0 overlaps**.
- Sub-390 single column gives 1 column at 320/360/375 and 2 at 390/414.
- Baseline (no patch) is 7px above / 48px below at both 1360px and 390px.
- Modal today renders 1 `DIV.summary-explanation`, 0 `<br>`, 0 `<li>`.
- Student page has 5 flip cards with `sum*` ids; it needs the student login, so
  it was **not** in the bulk sweep — T7 must check it explicitly.

- [x] `php -l` every touched view; `php artisan view:cache`;
      `php artisan test --filter=ThemeCssIntegrityTest` (it parses `theme.css`
      for balanced braces and requires `.summary-card` to exist — a typo in the
      new rules fails it); `git diff --check`.
- [x] Headless Chromium at **320, 360, 375, 390, 414, 768, 1024, 1360px** on
      all six pages: ≤3 lines per back, no spill below the back face, no text
      under the button. **Include the student page** — it needs the student
      login and was not covered by the re-sync sweep.
- [x] Confirm the summary bar is one column below 390px and two columns at
      390px and above.
- [x] Open all 30 explanation modals; each shows a formula or ≥2 items; title
      matches the card label.
- [x] Confirm a card with no `detail` still renders the paragraph. **No live
      page exercises this** — every flip card passes `explanation`, and
      `replacement-history` includes the partial without `flipCards`
      (`replacement-history.blade.php:154`), so it has no modal. Use a
      temporary fixture rather than claiming it was checked on a page.
- [x] Dark theme: open a formula modal and confirm chips and muted meaning
      remain readable.
- [x] Confirm the info button's tooltip does not cover the bullets (it now sits
      at the card's top edge).

## T8 — Changelogs and handover (0.5h) · `state: done 2026-11-14`

- [x] One dated entry per touched page under `page-changelogs/`.
- [x] Note the modal lost its single paragraph and gained the formula strip,
      and that four bullets were shortened (three distinct wordings) with the
      full wording kept.
- [x] Note that the 2026-11-13 entries already credit the flip cards to
      `my-timetable-ui-cleanup`; this entry must not re-credit them.
- [x] Update `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` §0/§1.

## Dependencies

- T2 independent of T1.
- T3 depends on T2 (needs the class names).
- T4/T5 depend on T3.
- T6 depends on T4/T5.
- T7 depends on T1–T6.
- T8 depends on T7.

## Definition of done

SD-01–SD-08 are met. Every flip card at each verified width has visible line
spacing, a centred block, ≤3 lines and no text under the info button. The
explanation modal shows a formula strip of terms (never a live number) and
term/meaning bullets, with the paragraph fallback intact. No value, calculation,
filter or `valueId` changed. No DB, route or dependency change.

**Re-sync note:** the four bullets this lane shortens are still the *only*
copy changes it makes. The rest of the back copy was rewritten by
`my-timetable-ui-cleanup` and is out of scope here.
