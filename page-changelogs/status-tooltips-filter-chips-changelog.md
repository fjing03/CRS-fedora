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

---

## 2026-11-06 — Auto-Reject rule: the Pending tip states its own deadline

User brief `prompts/Auto-Reject Pending Requests.md`. Two halves, one clock.

### The rule (backend, no UI)

A **Pending** replacement request is **automatically rejected** when its
proposed start is within — or exactly — **3 working hours** of now, on the
TARUMT Sabah calendar: **Mon–Fri, 08:30–17:30**, excluding weekends and the
semester's own `holidays` rows. Requests further out stay Pending; every other
status is never considered.

- `app/Support/WorkingHours.php` — THE office-hours clock. `gapMinutes()` counts
  only working time between two instants (a weekend proposal starts counting at
  the next opening bell; a past start has 0), `subtractWorkingMinutes()` walks
  the same calendar backwards (the tooltip's deadline), and `holidays()` resolves
  the semester's `holidays` rows to real dates through `semesters.start_date` —
  no second holiday list. `MIN_GAP_MINUTES = 180`.
- `app/Actions/AutoRejectRequest.php` — the write leg. Same release family as
  `CancelPendingRequest` (`pending→available` version-checked flip, junction
  DELETE) so the reserved cells come back, but stamped `rejected` with the fixed
  system reason; **`approver_id` stays NULL** (no human decided). Audit rows use
  the allowed `rejected` action with `details.auto = true`, attributed to the
  request's proposer (`audit_logs.user_id` is NOT NULL and there is no system
  user). No mail — the in-app feed already derives the rejection from the status.
- `app/Services/PendingRequestExpiry.php` — the sweep. Rejects only `pending`
  rows whose window has closed; `--dry-run` answers without writing.
- `php artisan requests:auto-reject` (`--dry-run`), scheduled every 5 minutes in
  `routes/console.php`. **Not** a write-on-read: browsing a page never mutates
  data, which is why the demo DB stays safe.

The brief's example table is the unit contract (`tests/Feature/WorkingHoursTest`):
Fri 11:30 → 14:30 = 3 h rejects, → 15:00 = 3.5 h stays; Fri 16:30 → Mon 09:30
= 2 h rejects, → Mon 11:00 = 3.5 h stays.

### The Pending badge tip (both pages)

`.badge.status-pending` now carries the deadline, in the SHARED vocabulary
(`ReplacementRequestMapper::statusTip`), so My Replacements and the PL queue can
never disagree:

> Your request has been Submitted and is AWAITING the Programme Leader's
> decision (Approve or Reject).
> **If the Programme Leader has not Approved this Request by Fri, 13 Nov 2026,
> 10:00 AM, it will be Automatically Rejected.**

The PL's voice reads the same fact from the other side: *If you have not
Approved it by …*. The deadline = proposed start − 3 working hours, computed per
request (row's own `replacementDate` / `replacementTime`), so it skips weekends
and holidays exactly like the rule.

**Two LINES, not `<br>`:** the brief's markup was `<br>`, but the shared
`data-tip` engine renders `textContent` (+ `pre-line` for a `\n`), so HTML would
print literally. The tooltip looks the same; the mechanism is the house one.

Decided rows (Approved / Rejected / Cancelled / Completed) keep their shipped
one-line tips byte-identical, and a pending row whose deadline cannot be
resolved omits the second line rather than inventing one.

### Receipts

- `tests/Feature/WorkingHoursTest.php` — 7 passed (the brief's table verbatim,
  evenings/weekend/holiday skips, the deadline as the gap's inverse).
- `tests/Feature/AutoRejectPendingRequestsTest.php` — 10 passed (inside rejects,
  boundary 180 rejects, 255 stays, weekend law, only-pending, dry-run writes
  nothing, audit shape, second sweep is a no-op, both tips).
- `tests/status-tooltips-filter-chips.spec.ts` — **9 / 9 passed** on the demo DB
  (:8010, read-only); the pending receipts now pin the two-line SHAPE (a fixed
  calendar date would rot).
- Demo DB, read-only: `requests:auto-reject --dry-run` → *Scanned 2 pending
  request(s); would auto-reject: none* (both demo requests sit 3.5 h out — the
  brief's own "remain Pending" row). No demo row was written.
