# Review log — ui-footer-global

**Round 1 (2026-11-01, glm-5.3-flash, file-based recipe §B.4)** — combined review of
proposal/design/tasks. Raw output: kept in-session; verdict recorded here.

**Verdict: NOT ready as written — 1 blocker.** Then fixed:

- 🔴 **[BLOCK] Mobile full-bleed divergence** — `design.md` claimed `margin-left/right: -24px`
  as a single "verified" coupling, but `.app-container` x-padding is **12px at ≤768px**
  (`theme.css:1285–1286`) → the strip would overhang 12px/side and get clipped by
  `body{overflow-x:hidden}` (:156). **Applied fix:** the T4 block now mirrors the bleed
  per breakpoint (−24 desktop / −12 ≤768px) and design.md records the coupling as
  per-breakpoint + pins the `--fixed` geometry (`left/right: 0`, margins inert).
- 🟡 **Scope wording** — settings surfaces (`pages/settings/*` via starter-kit
  `layouts/app`) are not covered by F3/F4. **Applied fix:** consciously EXCLUDED and
  logged in design.md (Scope exclusion + NON-goals) and TASKS T5 (changelog note) —
  matches the user's spec (app pages + login/auth only).
- 💡 **Adopted** — `var(--radius-pill)` (:78/:142, precedent `.semester-chip` :645)
  replaces the literal `999px`; light-theme login legibility eyeball added to the T5
  receipts list.

**Round 2 (2026-11-01, glm-5.3-flash, file-based recipe §B.4)** — verified all three
round-1 fixes at exact source anchors (per-breakpoint mirror −24/−12 per
`theme.css:608–615` + `:1285–1286`; `--fixed` `margin: 0` genuinely neutralizes the
bleed given the T4 rule ordering — `.ui-footer--fixed` written AFTER the media mirror
at equal specificity; expiry of the light-token anchors :25/:22/:57/:18/:78 dark +
:92/:89/:124/:85/:142 light). Clean namespace grep (zero `.ui-footer` hits), 4-icon
set = "4 + ONE" accurate, `<flux:icon.*>` syntax proven in-repo, gates/ritual/commit
rules all consistent.

**Verdict: `### 🔴 Outstanding` — NONE (empty). PASS.** "Ready to proceed to /sdd-apply."
Lane FROZEN 2026-11-01; apply released only on the user's explicit approval word.

---

## Apply + verify (2026-11-03, head `29126e9` → this commit) — PASS

Applied T1–T5 from the frozen artifacts. §6 anchors re-grepped BEFORE editing and all
matched the prompt's re-validated column: `.app-container` `:863–871`, mobile
`.app-container` `:1550–1551`, `body{overflow-x:hidden}` `:156`, `--radius-pill`
`:78/:142`, `.semester-chip` `:889`, tokens `:18/:22/:25/:57` + light `:85/:89/:92/:124`,
`.login-card` open `:348` / close `:417` / `<script>` `:419`, `ui-template` `:45/:46/:47`,
login z-layers `.theme-toggle` `:81` + `.login-card` `:101`, `theme.css` 4316 lines,
settings surfaces still on `x-pages::settings.layout`. Zero `.ui-footer` hits before the
apply (F1–F5 genuinely absent).

### Verify — artifact by artifact
- **F1** `flux/icon/mail.blade.php` — present, Lucide credit header, structure identical to
  `folder-git-2.blade.php` (same props, solid-variant throw, `Flux::classes` matcher, svg
  wrapper); icon set 4 → 5.
- **F2** `partials/ui-footer.blade.php` — six segments in order, gap-only (no `·`
  separators), `$version` single-writer, `$fixed` prop, both links real `<a>` with
  `aria-label` + `title`, repo `target="_blank"` + `rel="noopener noreferrer"`.
- **F3/F4** exactly one include per layout, at the specified points (`ui-template` inside
  `.app-container` after `@yield`; `login-template` after the card close, before the
  script). No per-page includes anywhere.
- **F5** ONE additive block at EOF containing every required rule: base + gap-only flex,
  `margin-top: auto`, desktop −24px bleed, the `@media (max-width: 768px)` −12px mirror,
  `.ui-footer-chip` with `--radius-pill`, link + hover + `:focus-visible`, `.ui-footer--fixed`
  written AFTER the mirror, and the print hide. Hex/rgb scan inside the block: none.

### 🔴 Fixed
- **Auth-variant legibility.** Measured/eyeballed over the login photo, the plain
  `transparent` background left 12px text below AA contrast in light mode. The prompt's §4
  conditional tool was applied: a token-only `color-mix(in srgb, var(--color-bg) 62%,
  transparent)` scrim. Re-shot both themes — legible, no hex, no new token. Recorded in the
  changelog so the deviation from the design's literal `transparent` is visible.

### 🟡 Addressed
- The shared Lucide icons render at 24px from the vendor `:where()` rule; the new
  `.ui-footer-icon` (14px) outranks it by plain-class specificity — measured 14px on the
  live pages, so the 12px strip rhythm holds.
- Mobile receipt proves BOTH the wrap and the mirror: child edges 12…378 at a 390px
  viewport with `scrollWidth === 390` (no clipping, no horizontal scrollbar).

### Receipts
- `composer run lint:check` → passed · PHPStan → 0 errors · full suite
  (`DB_DATABASE=class_replacement_testing`) → **312 passed**, 1667 assertions.
- Live on `:8000` via `scripts/dev-serve.sh 8000`: login (fixed, scrim, dark + light,
  card 753 < strip 827), app pages full-bleed 0→1400 with `scrollWidth 1400`, short page
  bottom-pinned (strip bottom 884 / viewport 900), 390px wrap with the −12px mirror, and
  `@media print` → `display: none`.
- Demo DBs: no domain data written (the receipt only logged in as 5770 to render pages).

### 🔴 Outstanding
None. Lane marked `applied`; archiving the lane and moving this prompt into
`prompts/completed prompt (Archieve)/` both wait for the user's explicit word.
