# Critique ledger — who owns what (2026-11-17)

Read-only bookkeeping. Purpose: stop two sessions from touching the same file, or from reading each other's
work-in-progress as a defect.

## The five findings

| # | Finding | Status | Files it touches | Owner |
|---|---|---|---|---|
| P1-a | Arrangement grid `scrollWidth 1976` vs `clientWidth 1390` — 586px off-canvas | **Kept as is** — owner's decision 2026-11-17, not a bug to fix | `replacement-arrangement.blade.php` | closed |
| P1-b | Three phone patterns: Cohort 2138px accordions, Venue 4249px mini-grids, My/Student sanctioned chips | **In flight** — the other session, after the responsive port | `cohort-timetable.blade.php`, `venue-timetable.blade.php`, `theme.css`, `ui-common.js` | other session |
| P2 | Load-bearing meanings live in hover; touch is banned from them | **SHIPPED 2026-11-17** — legend items are focus stops (48×48 on coarse), hint re-keyed to `any-pointer: coarse`, arrangement tips say OPEN, `#submitTip` announced + focusable while disabled. Phase 2 (`<th>`, badges) open | `partials/ui-legend-bar.blade.php`, `theme.css`, `livewire/student-my-timetable.blade.php`, `livewire/replacement-arrangement.blade.php` | this session |
| P2-b | Dead Class Details column at 1440 | **Shipped** `67cc84b` (option B + spanning rule) | `replacement-arrangement.blade.php` | done |
| P3 | `+0` noise on four summary cards | **Untouched** — still prints: `ui-common.js:1598` (`'<span class="sum-pending">+'`) | `ui-common.js` (`computeSummary`), the summary card arrays | unclaimed |

## Collision matrix

| File | P2 (this session) | P1-b / P3 (other session) |
|---|---|---|
| `public/js/ui-common.js` | **no** | **yes** — P3 lives here, and P1-b's engine work |
| `public/css/theme.css` | **done** — hint re-key, 48px floor, focus ring, `.legend-item` out of the 1024 40px block | **yes** — P1-b's phone-port CSS |
| `partials/ui-legend-bar.blade.php` | **yes** | only if the legend is restyled for phones |
| `livewire/replacement-arrangement.blade.php` | **yes** — 2 tips + `#submitTip` sync | only if P3 touches its card array |
| `livewire/cohort-timetable.blade.php`, `venue-timetable.blade.php` | **no** (only via the shared legend partial) | **yes** |

**The one real overlap is `theme.css`.** Everything else is separable.

## Why P2 waits rather than racing

P2 changes the legend's height on coarse pointers (16px rows → 48px). The legend renders on My, Student,
Cohort, Venue and the Arrangement — the same pages the other session is reviewing. Applying mid-sweep would:

1. put a 48px legend into their touch captures, where it reads as a regression they caused;
2. append to `theme.css` while they are appending to `theme.css`;
3. make any red in their test run ambiguous between their finding and my floor.

## What flips the answer to "apply"

Any one of these:

- their session reports done, or its sweep passes the Arrangement / Cohort / Venue pages;
- they say `theme.css` is untouched for the rest of their run;
- the owner accepts the capture contamination and says apply anyway.

## Note on P3 (not mine to start)

`computeSummary` still emits `+0`. Removing it would also clear two contrast failures recorded in
`docs/ui-contrast-2026-11-17.md` (C8, and the `+N` badge at 4.04:1). Whoever takes P3 should take the contrast
receipt with it — it is in `ui-common.js`, which P2 does not touch.
