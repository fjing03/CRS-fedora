# Explore brief — merge-upstream-fjing-4dc4d06

**Date:** 2026-10-08 · **Vehicle:** follow-up sync of the parked upstream delta `36c4d2c..4dc4d06`
**Predecessor:** `2026-10-07-sync-upstream-fjing-ui` (merged up to `36c4d2c`; this delta landed *during* Wave 2's freeze and was parked with "zero collision with Wave-2 scope").

## 1. Ground truth (all verified first-hand, 2026-10-08)

- Our HEAD `52e25ca` (Wave 2 archived); `merge-base HEAD upstream/fjing` = `36c4d2c` exactly.
- Delta = **5 commits / 23 paths**, all UI/docs/SDD-history, zero backend files:

| Commit | Content |
|---|---|
| `a86e327` | venue timetable booked classes render as cohort-style event blocks (the visible redesign the user noticed) |
| `f5d12ed` | mock-data: placeholder subject names → real names |
| `142ec2e` | holiday badges read generic PUBLIC HOLIDAY, name on hover |
| `bc748a3` | tabbed grouping for every info detail modal |
| `4dc4d06` | replacement origin trail + missing conflict colouring + holiday badge |

- Path census: 3 blades (`venue-timetable-UI-design-template` 141 ln, `replacement-home-UI-design-template`, `CohortTimetable-UI-design-template`), `theme.css` (+3), `mock-data.js` (170 ln), `ui-common.js` (7 hunks), 8 × `page-changelogs/*`, 8 × `.sdd/archive/2026-10-07-venue-event-blocks/*` (their own SDD history), `tests/venue-timetable.spec.ts`.

## 2. Conflict analysis (the thing the predecessor's "zero collision" claim got nuanced)

- Raw path intersection vs our HEAD **is NOT empty**: 7 shared paths (6 `page-changelogs/*` + `public/js/ui-common.js`). The original claim was scoped to *Wave-2's* file set; against the whole HEAD it needs this refinement.
- **`git merge-tree --write-tree` exits 0 with no conflicted-file list** → git auto-merges all 23 paths; no ledger resolution needed (predecessor had 6 real conflicts; this one has **0**).
- Why the 7 overlaps are safe, verified per-hunk:
  - `page-changelogs/*`: append-only on both sides; interleaved sections, no semantic conflict.
  - `ui-common.js`: our only divergence from base is `jumpToToday()` (Wave-1 `window.*` guards). Upstream's 7 hunks (verified via `git diff 36c4d2c..4dc4d06`) do **not** touch `jumpToToday` → auto-merge keeps our guard + adds their hunks. **Semantic-review pass planned post-merge anyway.**

## 3. Cross-change side effects checked

- `mock-data.js` `currentUser` hardcode (`5770`/LJZ) survives in upstream's tip → **Wave-3 static-panel scope unchanged**; `ui-nav-bar.blade.php` untouched by the delta.
- `dataset/`, seeders, tests/Feature: untouched by delta → T-1..T-5 invariants, 110-test baseline, doc parity all unaffected.
- Their `.sdd/archive/2026-10-07-venue-event-blocks/` files arrive as history — no interference with our `.sdd/` tree.
- `tests/venue-timetable.spec.ts` (Playwright): updated by them; **not part of our gates** (Feature/phpunit only) — smoke covers the page live instead.

## 4. Decisions captured here (feed proposal)

- **D1:** merge commit (no fast-forward — we are 6 ahead); resolution ledger = "auto, none".
- **D2:** post-merge semantic eyeball of merged `ui-common.js` + merged changelog headers (cheap, catches what merge-tree can't).
- **D3:** gates after merge = unchanged house set (phpunit 110 baseline, phpstan-1G, pint adminer-only, live smoke of venue timetable page) + invariants unchanged (35/44/1988).
- **D4:** `wire-backend-into-refactored-ui` (Wave 3) is NOT started here — merge first so Wave 3 wires against the newest UI. The 3-batch rename unfreeze + static-panel fix stay entirely in Wave 3.

## 5. Rejected approaches

- **Fast-forward** — impossible/incorrect: our branch has 6 local commits (Wave 2 + docs); a real merge commit preserves both histories.
- **Cherry-pick the 5 commits** — rewrites their hashes vs upstream; future syncs would see phantom conflicts. Plain merge keeps history truthful.
- **Squash-merge** — loses per-commit provenance the next sync's ledger may need.

## 6. Open questions

- None blocking. (Spec file omitted: single-purpose mechanical merge, no cross-module behavior change — per workflow "specs optional for simple changes"; reviewer to arbitrate in Batch 1.)
