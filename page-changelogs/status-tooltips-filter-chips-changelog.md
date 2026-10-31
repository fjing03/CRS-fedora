# Changelog — Status & Urgency Tooltips + Filter Chips (My Request History / Request Approval)

> SDD change: `status-tooltips-filter-chips` (proposed 2026-10-30, applied
> 2026-10-30 after the user's approval; artifacts frozen in
> `.sdd/changes/status-tooltips-filter-chips/`).

## What shipped

- **Status tooltips** — the user's exact frozen wording on EVERY status
  badge of both pages (desktop tables, both MRH cancel dialogs, both
  detail modals, and both MOBILE cards via `opts.statusTip` threading into
  the shared `HtmlBuilder`). Existing tooltip mechanism (`data-tip` →
  `initDataTipTooltips`, **above** the element) — no new tooltip CSS/JS.
- **The Overdue urgency level** (request-approval only — the user's
  sanctioned badge-logic change; presentation only): past-dated rows get
  the solid-red `urgency-overdue` pill + "N days past" tooltip, keep the
  `row-urgent` highlight, and STILL match the Urgent filter
  (`urgencyMatches()`); dynamic day-count tips (`urgencyTip`, singular
  "day", N=0 → "TODAY") on the confirm modal, table cell, and mobile card;
  the Urgency column-header tip synced to the three-level copy.
- **Filter bars (both pages)** — `#clearFilters` → `#resetFilters` with
  the tip "Reset all filters to DEFAULT"; the neutral dashed **Clear all**
  chip inside `#filterChips` (reuses each page's OWN reset function — a
  real reset, default chips legitimately remain); RA's label → "Active
  Filters:" (MRH already had it).

## Files Changed

### `resources/views/livewire/my-request-history.blade.php`
| Timestamp | Location | Change | Detail |
|---|---|---|---|
| 2026-10-30 | page-scripts (~:737/:748) | added | page-local `statusTooltip()` (5 frozen strings) + `statusTipAttr()` |
| 2026-10-30 | :526, :633, :679, :776 | edited | `data-tip` appended to the table / quickCancel / batchCancel / detail-modal badges (`onclick` kept) |
| 2026-10-30 | :601 | edited | `renderCards()` passes `statusTip: statusTooltip` (mobile card) |
| 2026-10-30 | :205 | edited | `#clearFilters` → `#resetFilters`, tip → "Reset all filters to DEFAULT" |
| 2026-10-30 | :877-885, :998 | added | `resetAllFilters()` extraction + `window.resetAllFilters` export (DOMContentLoaded wrapper) |
| 2026-10-30 | :386-388 | edited | `renderFilterChips()` appends the Clear-all chip while `chips.length > 0` |
| 2026-10-30 | :916 | edited | reset listener → `#resetFilters` + `resetAllFilters` |
| 2026-11-01 | exports block (:998+) | added | `window.statusTooltip = statusTooltip` — T5's map-fallback receipt needed the page's own map reachable from `page.evaluate` (the MRH page script is closure-scoped); no behaviour change |

### `resources/views/livewire/request-approval.blade.php`
| Timestamp | Location | Change | Detail |
|---|---|---|---|
| 2026-10-30 | page-scripts (~:427/:436) | added | `statusTooltip()` (3 frozen strings) + `statusTipAttr()` |
| 2026-10-30 | :694, :954 | edited | `data-tip` on the queue-table badge (keeps `onclick`) + detail-modal Status row |
| 2026-10-30 | :846 | edited | `requestCard` opts gain `statusTip` + `urgencyTip` |
| 2026-10-30 | :410-418 | edited | `urgencyLevel/Class/Label` gain the `overdue` level (on top of `urgencyDays`) |
| 2026-10-30 | :73 | added | `.urgency-overdue` solid-red pill (tokens only) |
| 2026-10-30 | :427-431 | added | `urgencyMatches()` (Urgent filter keeps overdue rows; guard stays) |
| 2026-10-30 | :434-442 | added | `urgencyTip(r)` dynamic day-count tooltip |
| 2026-10-30 | :534, :710 | edited | urgency badges (confirm modal + table cell) carry `data-tip` |
| 2026-10-30 | :594 | edited | Urgency column-header tip → the three-level copy |
| 2026-10-30 | :678 | edited | overdue rows keep the `row-urgent` highlight |
| 2026-10-30 | ~filter | edited | equality predicate → `urgencyMatches(...)` |
| 2026-10-30 | :272, :1295 | edited | `#clearFilters` → `#resetFilters` + new tip; listener updated |
| 2026-10-30 | :833-836 | edited | Clear-all chip + label `Filters:` → `Active Filters:` |

### `public/js/ui-common.js`
| Timestamp | Location | Change | Detail |
|---|---|---|---|
| 2026-10-30 | `HtmlBuilder.requestCard` (~:2352-2359) | edited | consumes `opts.statusTip` + `opts.urgencyTip` (absent opts → byte-identical output; no tooltip logic) |
| 2026-10-30 | `HtmlBuilder.myRequestCard` (~:2375-2378) | edited | consumes `opts.statusTip` (same fallback law) |

### `public/css/theme.css`
| Timestamp | Location | Change | Detail |
|---|---|---|---|
| 2026-10-30 | :3593-3604 | added | `.filter-chip.filter-chip-clear-all` neutral dashed pill + hover (tokens only; wins the cascade over the filled `.filter-chip` without `!important`) |

### `tests/status-tooltips-filter-chips.spec.ts`
| Timestamp | Location | Change | Detail |
|---|---|---|---|
| 2026-10-30 | (new file) | added | the Playwright receipts (T5) — per-status map-vs-tip loops both pages + `page.evaluate` map fallbacks, dynamic urgency formats, the UNCONDITIONAL Overdue receipt (in-page `MockData.urgencyReferenceDate` shift — zero DB writes), renames/tips/labels/header-tip, Clear-all defaults law + individual ✕, mobile-card threading, modal spot-checks |

### `page-changelogs/*`
| Timestamp | Location | Change | Detail |
|---|---|---|---|
| 2026-10-30 | my-request-history-changelog.md, request-approval-changelog.md, this file | added | the three receipts (house law) |

## Apply closure (2026-11-01 — T5/T6/T8)

- **The TODAY ruling:** the user KEPT the frozen wording `Urgent — the
  Proposed Class is TODAY.` (recorded in the change's review-log) — receipted
  as the exact string in `T5.RA-2`.
- **T5 receipt:** `tests/status-tooltips-filter-chips.spec.ts` — 6/6 GREEN on
  the fresh-ritual server (:8000, read-only throughout).
- **T6 gates:** pint ✓ · phpstan(1G) 0 errors ✓ · `node --check ui-common.js` ✓.
  Regression: `request-approval.spec.ts` + `request-approval-verify.spec.ts` —
  9 passed + 3 PRE-EXISTING, UNRELATED failures (REQ-1/REQ-2 run as staff 5770
  against another user's timetable data; U3-T9f is the approve-wire toast
  probe — demo-state drift; none touch this lane's surfaces). NOTE (task
  correction): the tasks' "staff 5770" house-style note cannot drive
  `/request-approval-ui` (route mw = auth + pl) — the receipt follows the
  frozen existing spec's storageState (staff-5425, the demo PL).
- **Commit topology note:** the request-approval / ui-common.js / theme.css
  parts of T1–T4 reached HEAD inside two other lanes' commits
  (`4a77a1f` notif-jump-flash, `bb46a19` ra-result-count-showing-of —
  piggybacked while the lane was parked); this closure's own series stages
  ONLY the remaining files (the MRH blade + this changelog).
