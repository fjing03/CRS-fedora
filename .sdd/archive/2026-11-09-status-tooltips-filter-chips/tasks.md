# Tasks — status-tooltips-filter-chips

Source: frozen `proposal.md`, `design.md`, `specs/my-request-history.md`,
`specs/request-approval.md`.
Rules: line anchors below are pre-apply snapshots — re-grep at apply time
(blades drift). One concern per commit at the end (T8), conventional
commits, **never push**. Re-verify house law: no hardcoded colors (tokens
only), no new dependencies, no DB writes.

- [x] T1 **MRH status tooltips.** Add the page-local `statusTooltip(status)`
      map (the 5 frozen strings) to `my-request-history.blade.php`; append
      `data-tip` (only when the map returns a non-empty string) at the 5
      sites: desktop table row (:~526, keep its `onclick`), quickCancel
      dialog badge (:~632), batchCancelSelected dialog badge (:~678),
      detail-modal Status row (:~758), and thread `opts.statusTip` into
      the shared `HtmlBuilder.myRequestCard` (`ui-common.js:2369`, consumed
      as `(opts.statusTip && opts.statusTip(r.status)) ? … : ''` — absent
      opt keeps old call sites working) from `renderCards()` (:~601).
- [x] T2 **RA status tooltips.** Page-local `statusTooltip(status)` (the 3
      frozen strings) in `request-approval.blade.php`; `data-tip` at the
      queue table row (:~679, keep `onclick`), the detail-modal Status row
      (:~938), and `opts.statusTip` into `HtmlBuilder.requestCard`
      (`ui-common.js:2340`) from the blade's call (:~825).
- [x] T3 **RA Overdue state + urgency tooltips.** `urgencyLevel()` (:~404)
      refactors onto `urgencyDays()` (`d < 0 → 'overdue'`); `urgencyClass`
      → `urgency-overdue`; `urgencyLabel` → `Overdue`; page-styles pill
      `.urgency-overdue { background: var(--color-error); color:
      var(--color-on-error); }` beside :~70–72; row highlight (:~646)
      `level === 'urgent' || level === 'overdue'` → `row-urgent`;
      `urgencyMatches()` replaces the :~632 equality (guard at :~631
      stays; the filter-chip button set NOT extended); new `urgencyTip(r)`
      (4 frozen rows, singular day, N=0 → TODAY string) + `data-tip` at
      :~502 (approvalCard), :~678 (table cell), and `opts.urgencyTip` into
      `requestCard` alongside its existing urgency callbacks; sync the
      :~562 column-header tip to the pinned three-level copy; re-read the
      `requestAgeHtml()` age-chip tooltip at apply (the frozen D2.6 risk —
      the label flows through automatically; report the final copy).
- [x] T4 **Filter bars (both pages).** `#clearFilters` → `#resetFilters`
      (:~205 / :~271) with `data-tip="Reset all filters to DEFAULT"`,
      listeners updated (:~880 / :~1258); MRH: extract the inline reset
      body into `resetAllFilters()` used by BOTH the listener and the new
      clear-all; RA: clear-all calls the existing `resetFilters()` (:~1177)
      verbatim; both `renderFilterChips()` (:~365–387 / :~777–803) append
      the `Clear all` button after the chips while `chips.length > 0`,
      copying D3b's pinned snippet VERBATIM (`class="filter-chip
      filter-chip-clear-all" data-tip="Remove every active filter"`,
      label `Clear all`); RA label `Filters:`
      → `Active Filters:` (:~801); add the one `.filter-chip-clear-all`
      rule to `public/css/theme.css` copying D3b's NEUTRAL restyle
      verbatim (transparent background + dashed `--color-outline-strong`
      border + `--color-on-surface-variant` ink, hover → `--color-error`
      — NOT the filled `--color-primary-container` chip look, or the
      action reads as an active chip).
- [x] T5 **Playwright receipts.** Write
      `tests/status-tooltips-filter-chips.spec.ts` per the two specs'
      Receipts sections (map-vs-tip hover loops + page.evaluate map
      fallback both pages; urgency regex + badge↔tip word + the
      UNCONDITIONAL Overdue receipt via the in-page
      `MockData.urgencyReferenceDate` shift; renames/tips/label/header-tip;
      clear-all defaults law + individual ✕; mobile-card threading; modal
      spot-checks). House style: staff 5770 / `Turumt@2026`, server :8000.
- [x] T6 **Gates (run after T1–T5 land).** `composer run lint:check` +
      `vendor/bin/phpstan analyse --memory-limit=1G` (0 errors) +
      `node --check public/js/ui-common.js`; restart with the TWO-COMMAND
      ritual (`pkill -f "[a]rtisan serve"`; then `rm -f
      storage/framework/views/*.php && php artisan serve --port=8000 &`);
      run the new spec green; run the existing my-request-history +
      request-approval specs to prove zero regression (known unrelated
      baseline: clear-guards-cross-week 5 failures — ignore those).
- [x] T7 **Changelogs ×3.** `page-changelogs/my-request-history-changelog.md`,
      `page-changelogs/request-approval-changelog.md`,
      `page-changelogs/status-tooltips-filter-chips-changelog.md`
      (already scaffolded) — one row per file changed, per house table.
- [x] T8 **Commit series (NOT pushed).** Conventional commits split by
      concern (e.g. `ui: status tooltips on my-request-history`,
      `ui: overdue urgency badge + tooltips on request-approval`,
      `ui: filter-chip clear-all + reset renames`, `test: …`, `docs: …`);
      stage ONLY the intended files — never `.sdd/changes/*`, never
      `docs/server-commands.md`; stop and report for the user's manual
      push.

## Verification receipt to report back
- Per-status tip verified on desktop rows, mobile cards, dialogs, and
  detail modals (both pages) — with the exact frozen strings.
- Overdue: past-dated row shows the solid-red badge + "N days past" tip,
  keeps `row-urgent`, still matches the Urgent filter; the TODAY string
  behaviour reported to the user (pending their confirmation).
- Clear-all empties every NON-default filter on both pages (controls back
  to each page's own defaults; default chips legitimately remain); Reset
  tip reads "Reset all filters to DEFAULT"; RA label "Active Filters:".
- All gates green; 3 changelogs updated; commits local, unpushed.
