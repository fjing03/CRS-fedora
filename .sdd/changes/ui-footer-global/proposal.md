# Proposal — ui-footer-global

**Date:** 2026-11-01 · **Executor:** main session (user's explicit assignment — NOT the PI chat)
**Kind:** new shared UI partial · display-only · no PHP logic, no DB, no routes, no js

## User decision record (2026-11-01, all choices made by the user in conversation)

| # | Decision |
|---|---|
| 1 | ONE system-wide footer, **centered single row** (flex-wrap; wraps to quiet lines on narrow screens) |
| 2 | Segments: system name `TAR UMT Class Replacement System` · version chip **`v1.0` (chip IN, single-writer in the partial)** |
| 3 | Owner line: `© 2026 Poong Foo Jing · Bachelor in Information Technology (Honours) (Software Systems Development) · TAR UMT Sabah (RSD)` (name/programme/branch verbatim from the user) |
| 4 | Honest demo-data disclaimer: `Demo dataset — all names & schedules simulated` |
| 5 | Contact: **`poongfj@gmail.com`** as an icon-led mailto |
| 6 | GitHub: **repo deep-link** `https://github.com/fjing03/CRS-fedora` (user chose deep-link OVER profile link), new-tab + `rel="noopener noreferrer"`, icon-led (`folder-git-2`, existing shared icon) |
| 7 | Shown on **login/auth pages too** — a `--fixed` transparent strip pinned to the viewport bottom over the animated login background |
| 8 | Colors: theme.css tokens only (§10.0) — no hex/rgb |
| 9 | Promoted-to-shared from day one: partial wired from the layouts, never inline per page |

## Why

Before this lane, no system footer exists anywhere (only modal footers): the app ends
abruptly after the content. A footer gives every page (examiner-facing) the identity,
version, ownership, provenance (repo), and the honest demo-data disclaimer in ONE
quiet strip — satisfying "minimise plain text" (one strip, not per-page clutter),
"honest claims" (the demo disclaimer is the doc-level equivalent of the honest-zeros
law), and the Composition principle (§10.2/§10.3).

## Out of scope

- No `ui-common.js` changes (no JS at all — pure markup + CSS).
- No vendor Flux edits; the shared icon set grows by exactly ONE member (`mail`).
- No app `@section('page-styles')` edits — the footer CSS is ONE additive block in `theme.css`.
- No push (the user pushes). The CRS-demo clone (`:8001`) receives the footer via its own git pull (bundled with the pending `venues.description` pull).
