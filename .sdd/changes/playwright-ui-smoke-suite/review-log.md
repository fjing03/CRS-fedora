# Review Log — playwright-ui-smoke-suite

## proposal Round 1 — 2026-08-06 09:40

### 🔴 Fixed
- None

### 🟡 Addressed
- ESM syntax pinned: config + specs use `import` syntax (package.json `"type": "module"`) — added to in-scope row
- 9 routes enumerated in AC2 — proposal now self-contained
- @playwright/test version pin (~1.62.1, matches global install) — deferred to design.md D4 row
- .gitignore row: `/playwright/.cache/` dropped (browser cache lives at `~/.cache/ms-playwright`)
- explore-brief §2 header typo (D1–D10 → D1–D8) noted for next brief touch

### 🔴 Outstanding
- None — proposal.md PASSES and is FROZEN.

## design Round 1 — 2026-08-06 09:55

### 🔴 Fixed
- None

### 🟡 Addressed
- Login key selectors corrected to real DOM: `form[action="/login"]` + `#login_id` (no email input exists on either login page)
- `.gitignore` list restored to match frozen proposal: `test-results/`, `playwright-report/`, `/playwright/.cache/`
- A8 parallelism pinned to concrete `fullyParallel: true` (no workers override)

### 🔴 Outstanding
- None — design.md PASSES and is FROZEN.

## spec Round 1 — 2026-08-06 10:05

### 🔴 Fixed
- None

### 🟡 Addressed
- S-4 split: same-origin missing asset → 4xx response path (E-6); added unreachable-origin asset → genuine `requestfailed` path (E-5)
- S-5 Given changed to static landmark removal (no JS error) so failure is a deterministic selector timeout
- E-7 now includes `<title>` assertion per frozen design A6
- §4 notes S-2..S-5 are dev-only manual demonstrations per proposal AC5

### 🔴 Outstanding
- None — specs/ui-smoke-gate/spec.md PASSES and is FROZEN.

## tasks Round 1 — 2026-08-06 10:15

### 🔴 Fixed
- None

### 🟡 Addressed
- Changelog scope widened: "every page file touched during apply (Tasks 3/4/6)" — first run happens in Task 3/4, not Task 6
- Task 2 verify step → `npx playwright test --list --pass-with-no-tests`; DoD reworded (config created, E-1 verified at Task 3)
- Task 5 revert check → grep for injection marker instead of `git diff` cleanliness (previously-fixed templates would never diff clean)

### 🔴 Outstanding
- None — tasks.md PASSES and is FROZEN.
