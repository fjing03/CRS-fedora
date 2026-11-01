# Tasks — ui-footer-global

Frozen task list (small batch — proposal/design/tasks combined into ONE reviewer round, per the small-lane clause):

- [ ] **T1** `resources/views/flux/icon/mail.blade.php` — ONE new icon in the shared set (Lucide mail; structure cloned from `folder-git-2.blade.php`).
- [ ] **T2** `resources/views/partials/ui-footer.blade.php` — the partial per design Anchor content (segments ①–⑥, gap-wrapping, single-writer version).
- [ ] **T3** Wire BOTH layouts:
      `layouts/ui-template.blade.php` → `@include('partials.ui-footer')` inside `.app-container` after `@yield('content')`;
      `layouts/login-template.blade.php` → `@include('partials.ui-footer', ['fixed' => true])` after the `.login-card` closing `</div>`.
- [ ] **T4** `theme.css` — ONE additive `.ui-footer*` block at EOF, containing ALL of:
      base `.ui-footer` (tokens only; `margin-top: auto`; desktop full-bleed `margin-left/right: -24px`);
      the `@media (max-width: 768px)` mirror `margin-left/right: -12px` (matches `.app-container` mobile x-padding, `theme.css:1285–1286` — review round 1 blocker);
      `.ui-footer-chip` (`var(--radius-pill)`), `.ui-footer-link` (+hover), `.ui-footer--fixed` (`left/right: 0; bottom: 0; margin: 0; background: transparent; z-index: 5`);
      `@media print { .ui-footer { display: none; } }`.
- [ ] **T5** Gates + ritual + receipts + record:
      `composer run lint:check` ✓ · `vendor/bin/phpstan analyse --memory-limit=1G` → errors:0 · `php artisan test` → expect 277 green;
      stale-serve ritual (TWO separate commands: `pkill -f "[a]rtisan serve" || true`, then `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`);
      Playwright receipts: login page footer (fixed/transparent strip — **consciously eyeball LIGHT theme legibility over the photo bg; a token-only `color-mix` scrim is the tool if it fails**) · app footer bottom-pinned (my-timetable + cohort-timetable as 5770) · **mobile 390px wrap (also proves the −12px mirror — no clipped edges)** · light-theme pass-through
      → NEW `page-changelogs/ui-footer-changelog.md` entry (include the settings-surface exclusion note);
      commit `ui: the system-wide footer — identity, version chip, demo-data disclaimer, contact + repo links (ui-footer-global)`
      — stage ONLY the 5 code files + changelog + this SDD dir; **NEVER push**.
