# Tasks — arrangement venue picker: collapsible category headers

> **Change:** `arrangement-venue-picker-collapse`
> **Apply status:** **APPLIED** 2026-11-14 (user: "yes. proceed.") — see `review-log.md` §Apply log.
> One receipt is missing on purpose: `arrangement-mobile.spec.ts` needs a destructive reseed, and
> that database now holds the user's restored manual data.
> **Files touched:** `replacement-arrangement.blade.php`,
> `tests/venue-dropdown-responsive.spec.ts`,
> `page-changelogs/replacement-arrangement-changelog.md`
> **Shared files touched:** **none**

## ⛔ Before anything else

Three facts make this an unusual change. Read all three before editing.

**1. The blade is already dirty.** `+424 / −164` uncommitted from two earlier
sessions (2026-11-12 venue sheet, 2026-11-13 Selection Summary arrow). The
changelog carries an uncommitted `###` block at **3812–3846**.
`tests/venue-dropdown-responsive.spec.ts` is **untracked**.

**2. A second lane is applying on these same two files.**
`cancelled-slot-reuse` is `status: applying`, `implementation_approved: true`,
and mid-run. Its **B02** ("Update arrangement desktop and mobile") edits this
blade; its **B03** ("Update arrangement changelog") edits this changelog.

> **Apply gate — CLEARED 2026-11-14.** `cancelled-slot-reuse` **B02 and B03 are both ticked**
> (and its Part B work is committed as `74ab29b`, with the room-holder follow-up in `b1f13fd`).
> The original wording of the gate is kept below because its reasoning still governs T1:
> there is no "apply alongside" option: no rollback mechanism can separate two
> lanes' edits in one file, because the edit regions are themselves inside
> uncommitted work.
>
> **The gate covers T1, not T2.** T1 captures the rollback oracle and the green
> baseline. If it runs before B02 lands, the saved `renderArrangementVenuePicker`
> is the **pre-B02** function — B02 then edits inside it, and a rollback would
> paste the old text back over B02's work. That is the same data loss the gate
> exists to prevent, arriving through ordering instead of through a git command.

**3. No whole-file restore anywhere.** `git checkout`, `git restore`,
`git stash`, `git clean` and a `cp` restore of the whole blade are all equally
destructive here — they drop the pre-existing work *and* anything
`cancelled-slot-reuse` writes in between. Rollback is a region restore only
(`proposal.md` §Rollback summary).

---

## T1 (0.5h) — Baseline and region capture

- [x] Record reality, do not restate the numbers above:
      `git status --porcelain resources/views/livewire/replacement-arrangement.blade.php page-changelogs/replacement-arrangement-changelog.md tests/venue-dropdown-responsive.spec.ts`
      and `git diff --stat` on the first two. Paste both into `review-log.md`.
- [x] Locate the two regions **by name**, not by line number (they drift while
      the other lane writes):
      `grep -n "arrangement-mobile-venue-group {" resources/views/livewire/replacement-arrangement.blade.php`
      `grep -n "function renderArrangementVenuePicker" resources/views/livewire/replacement-arrangement.blade.php`
- [x] Save the **current text of the two regions only** to
      `/tmp/arr-venue-collapse/` (not the whole file):
      `region-css.txt` = the `.arrangement-mobile-venue-group` rule **from the
      selector through its closing brace (1257–1264, 8 lines)**;
      `region-js.txt` = the whole `renderArrangementVenuePicker` function.
      This is the rollback reference. Capture it **after** the gate, never before
      — see the warning above.
- [x] **Check `:8030` is free** before starting it — `ss -ltnp | grep 8030` —
      and confirm with the user if something holds it. `scripts/dev-serve.sh`
      kills whatever owns the port, and `cancelled-slot-reuse` **B03** runs its
      arrangement browser regressions on that same disposable lane.
- [x] Start the testing lane and capture a green baseline **with the guard
      satisfied** — see T6 for the exact invocation. Use
      `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030`.
- [x] If a test is already failing, stop and report. Note which spec and why. Do
      **not** fix it inside this lane, and do **not** loosen
      `abortUnlessTestingTarget` — that is forbidden by AGENTS.md.

## T2 (0.5h) — CSS: the bar

- [x] Replace the plain-caption rule at
      `#arrangementMobile .arrangement-mobile-venue-group` with the bar
      geometry from `design.md` §3 — sticky `top: 0; z-index: 1`, flex
      `space-between`, `10px 10px 10px 12px`, `--color-surface-variant` fill,
      `1px solid var(--color-outline)`, `var(--radius-sm)`, `font: inherit`
      **before** `font-size: 11px` (reversing them silently kills the 11px),
      700 weight, `0.6px` letter-spacing, uppercase, `text-align: left`,
      `cursor: pointer`.
- [x] Add `-label` — the ellipsis trio: `min-width: 0; overflow: hidden;
      text-overflow: ellipsis; white-space: nowrap`.
- [x] Add `-chevron` (`flex: 0 0 auto; width/height: 14px;
      color: var(--color-on-surface-variant); transition: transform 0.15s ease`)
      and the collapsed rotation
      `[aria-expanded="false"] …-chevron { transform: rotate(-90deg) }`.
- [x] Add `-items[hidden] { display: none }`.
- [x] Add hover → `--color-outline-strong` (border-colour only) and
      `:focus-visible` → `outline: 2px solid var(--color-primary); outline-offset: 1px`.
- [x] Do **not** touch `.arrangement-mobile-venue-item`, its `.selected` /
      `-main` / `-name` / `-meta` rules, or `.arrangement-mobile-venue-body`
      padding.
- [x] Do **not** touch the blade's own `prefers-reduced-motion` block
      (1811–1827) — it already zeroes transitions inside `#arrangementMobile`
      and covers the new chevron.

## T3 (0.75h) — JS: `renderArrangementVenuePicker`

- [x] **Change the callback signature** from `forEach(function (group) {`
      (blade 5372) to `forEach(function (group, groupIndex) {`. Nothing below
      works without the index, and the current callback does not receive it.
- [x] `heading` becomes `<button type="button">` with
      `aria-expanded` (`groupIndex === 0`, or `true` when `q` is non-empty),
      `aria-controls="arrangementPickerGroup" + groupIndex`, and
      `data-venue-group`.
- [x] Build the label span and the chevron. Use
      `document.createElementNS('http://www.w3.org/2000/svg', …)` for **both**
      the `svg` and the `path` — `createElement('svg')` silently never paints
      (`design.md` §8). Class: `arrangement-mobile-venue-group-chevron`.
- [x] Create the `-items` div, `id="arrangementPickerGroup" + groupIndex`,
      `items.hidden = !expanded`.
- [x] Append the heading and the items div to `host`; append each **item** to
      the items div (`design.md` §4). Item markup, classes, `data-venue-code`,
      `aria-pressed` and the `venueDropdown.select` click stay byte-identical.
- [x] Click handler toggles `aria-expanded` and `items.hidden` for its own group
      only, reading the current state from the DOM (`design.md` §5).
- [x] Leave the empty-search early return where it is — before grouping, so no
      header is rendered for "No venues match that search."

## T4 (0.75h) — Test

- [x] Add one test to `tests/venue-dropdown-responsive.spec.ts` covering the
      assertions in `design.md` §12:
      first group expanded / later groups collapsed; bar background ≠ item
      background ≠ body background; each header is a `BUTTON` with exactly one
      `svg.arrangement-mobile-venue-group-chevron` and a resolving
      `aria-controls`; clicking header 1 collapses it and clicking header 2
      expands it.
- [x] **Poll** the chevron `transform` after collapse — a bare read races the
      0.15s transition. Do **not** assert `transform` under reduced-motion
      emulation (the blade zeroes the transition there).
- [x] **Prove rule 2 properly:** search `tutorial` (matches every
      `humanizeType` → `'Tutorial Room'`, which spans several blocks/floors).
      **First assert the group count is `> 1`** — that guard is the point of the
      test; without it a data change could reduce the search to one group and the
      assertion would pass vacuously, which is the exact hole that hid this rule.
      Then assert **every** matching group is `aria-expanded="true"`. A
      single-venue search (`B010`, `B100`) resolves to one group at index 0 and
      opens with or without the `q ? true :` branch — it proves nothing.
      If `tutorial` yields a single group on the served data the guard fires **by
      design** — fall back to `b` (block B spans floors `'0'` and `'1'`:
      B002/B005/B006/B009/B010 vs B100/B103/B111). Do not delete the guard to
      make the test pass.
- [x] Do not add `abortUnlessTestingTarget` to this file — it belongs to another
      session and T6's explicit invocation already covers the risk.

## T5 (0.25h) — Changelog

- [x] Add a **new `##` entry at the top** of
      `page-changelogs/replacement-arrangement-changelog.md` (the file is
      newest-first; the newest `##` is currently `## 2026-11-13 —
      cancelled-slot-reuse Part B: the room-only holder is now the ACTUAL
      holder (S24/S25)` at line 1). Cross-reference the venue-page precedent
      (`page-changelogs/venue-timetable-ui-changelog.md`, 2026-11-13 entry).
- [x] Include the one-line **ownership note**: `renderArrangementVenuePicker`
      belongs to this lane and must not be reverted by
      `cancelled-slot-reuse` **B02** or **B03**.
- [x] Do **not** move, reorder or rewrite the uncommitted `###` block at lines
      **3812–3846** (2026-11-12 venue-sheet entry, 2026-11-13 Selection Summary
      entry). It belongs to another session.
- [x] Note the known UX parity point so it is not later "fixed" as a regression:
      after reopening the sheet, a venue chosen from a non-first group is no
      longer visible, because the collapse state resets.

## T6 (0.5h) — Verify

- [x] **Verify no other lane is mid-run on `:8030`** before restarting it.
      `scripts/dev-serve.sh 8030` kills whatever holds the port, and
      `cancelled-slot-reuse` **B03** runs its arrangement browser regressions on
      that same disposable lane — a restart here would terminate its run.
      Check with `ss -ltnp | grep 8030` and confirm with the user if it is busy.
- [x] **Restart the blade.** Blade edits are not picked up otherwise and
      the new test fails for a phantom reason:
      `DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030`
- [x] Run both specs with the guard satisfied.
      **PARTLY — `venue-dropdown-responsive.spec.ts` 7/7 PASS. `arrangement-mobile.spec.ts` was NOT run: it needs a
      destructive `migrate:fresh --seed` on `class_replacement_testing`, which now holds the user's restored
      manual data. Static check of its `t22DifferentVenue` selectors only.** This exact form is required —
      without it `arrangement-mobile.spec.ts` aborts at collection and
      `venue-dropdown-responsive.spec.ts` (unguarded) would drive the demo DB:

      ```
      CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
        npx playwright test tests/venue-dropdown-responsive.spec.ts tests/arrangement-mobile.spec.ts
      ```

- [x] Manual check at 390px in the arrangement mobile sheet: the bar is visibly
      darker than the rows and the body; first group open; a closed group opens
      on tap; the chevron rotates; a multi-group search opens every matching
      group; selecting still sets the chip.
- [x] Both themes (light and dark) — tokens only, so no per-theme override
      should be needed.
- [x] Keyboard: Tab reaches the bar, Enter/Space toggles, focus ring is visible.
- [x] `git diff` the blade and confirm the only hunks are this lane's two
      regions — no stray reformatting of the pre-existing work.
- [x] `git diff page-changelogs/replacement-arrangement-changelog.md` and confirm
      the only change is the new entry at the top; the block at 3812–3846 must be
      untouched.

## T7 (0.25h) — Receipt

- [x] Write the actual pass counts into `review-log.md` (honest zeros; if a spec
      fails for unrelated data drift, name it and do not claim green).
- [x] Record the final line numbers of both regions and note whether
      `cancelled-slot-reuse` B02/B03 had landed by then.
- [x] Note that `tests/venue-dropdown-responsive.spec.ts` stays **untracked** —
      this lane's test cannot be committed without dragging another session's
      file along. Say so explicitly rather than staging it.
- [x] Leave the lane at `status: applied`, ready for `/sdd-verify`.

## Out of scope — do not do these

- No `theme.css` edit. No `ui-common.js` edit. No new Blade partial.
- No class unification with the venue page — that is the 3rd-copy promotion, a
  separate change.
- No change to the desktop four-column `venue-dd-panel`.
- No change to the selection flow, guards or chips.
- No commit, stage or reformat of the pre-existing uncommitted work.
- No `git checkout`, `git restore`, `git stash` or `git clean`.
- No relaxation of `abortUnlessTestingTarget` or the `:8030` guard.
