# Explore brief — status-tooltips-filter-chips

## Problem
Two request pages render status/urgency badges with NO hover explanation, and their
filter-chip bars are inconsistent (different reset tips, no "clear all", RA still says
"Filters:"). The user supplied exact tooltip wording per status + the urgency examples.

## Findings (code anchors, verified 2026-10-30)

### The tooltip system (reuse as-is — no new CSS/JS)
- `public/js/ui-common.js:2917 initDataTipTooltips()` — ONE floating `.data-tip-tooltip`
  div, document-level delegated `mouseenter`/`mouseleave`/`mouseout`. **Default position
  = ABOVE the element** (`top = r.top - th - 6`, viewport-clamped; falls back below only
  when no room). `data-tip-pos="left"` = the top-bar variant. `\n` in text →
  `.data-tip-tooltip--multi` (pre-line). Styles at `public/css/theme.css:3748`
  (inverse-surface tokens, nowrap, z-index 9999).
- Consequence: a `data-tip="…"` attribute on ANY element — including dynamically rebuilt
  table HTML — yields an above-element tooltip with zero new code. The spec's "reuse the
  existing tooltip, appear above" is already the system's own law.
- `statusClass()` is SHARED (`ui-common.js:2697`) — badge colours untouched.

### Status vocabulary (supported = NO invented statuses)
- `ReplacementRequestMapper:84` emits `ucfirst($status)` → **Pending / Approved /
  Rejected / Cancelled / Completed** (mock carries Completed; demo DB currently holds
  approved/cancelled/rejected; pending is live-supported by the queue filter).
- my-request-history renders ALL statuses — badge build sites: :526 (desktop table,
  onclick openModalById), :632 + :678 (mobile cards), :758 (detail modal Status row).
- request-approval queue renders ONLY Pending + Approved + Rejected (mapper rowsPayload =
  pending + decided). Cancelled/Completed never appear there.

### The two pages' filter bars
| | my-request-history | request-approval |
|---|---|---|
| Reset button | `#clearFilters` :205, tip "Reset all filters" | `#clearFilters` :271, tip "Reset all filters" |
| Reset listener | inline :880 — search/status/week cleared, `hideCompleted.checked=true` (default ON), `mrh-filters` storage dropped, rebuildTable | `resetFilters()` :1177 — status→'Pending', week all, urgencyFilter all, rpp 10, hideCompleted true, bulk.clear, viewedIds.clear, page 1, sort reset, chips-active toggles, `request-approval-filters` storage dropped |
| Chips bar | `#filterChips` :214; render :365–387; label already **"Active Filters:"** | `#filterChips` :280; render :777–803; label **"Filters:"** → rename |
| Chips | Status / Week / Search / Exclude Completed (individual ✕, inline onclick) | Status / Urgency / Week / Search / Exclude Completed (individual ✕) |
| Re-render | `renderTable()` :585 → `renderFilterChips()` | `renderTable()` :813 → `renderFilterChips()` |

- `.filter-chips` (theme.css :3568) = flex bar with `:empty → display:none`; chips ride
  `container.innerHTML = label + chips.join('')`. **A persistent button inside the bar
  must ride that innerHTML rebuild** (shown only when chips exist; `:empty` keeps hiding).
- `#clearFilters` has NO references outside the two blades (no spec/test collisions) —
  the id rename is safe.

### Urgency (request-approval only)
- Page-local helpers :404–423: `urgencyLevel(classDate)` = `diffDays <= 3 ? 'urgent' :
  'normal'` (negatives → 'urgent'), `urgencyClass`, `urgencyLabel` (Urgent | Normal),
  `urgencyDays(classDate)` = `ceil(classDate − MockData.urgencyReferenceDate)`
  (server-overridden via PAGE_DATA).
- Badge sites: :502 (queue card) + :678 (table cell). Row highlight :646: `level==='urgent'`
  → `row-urgent`; else `urgencyDays <= 7` → `row-soon`.
- Urgency FILTER :631–632: `.urgency-filter-chip` buttons (all/urgent/normal) filter by
  `urgencyLevel(r.classDate) === urgencyFilter` — **adding an 'overdue' level silently
  drops past rows from the Urgent filter unless the predicate is extended.**
- Badge CSS :70–72: `.urgency-urgent` = error-container/on-error-container (translucent
  red pill); `.urgency-normal` = success-container (green pill).

## Final decisions (frozen with the proposal — user rulings 2026-10-30)
1. **No new tooltip CSS/JS** — `data-tip` attributes only (system default = above).
2. **Per-page status→tip maps** as page-local `statusTooltip(status)` helpers — NOT
   shared (the two pages speak in different voices: proposer vs reviewer; different data,
   not duplication).
3. **MRH 5 statuses** (Cancelled/Rejected/Approved/Pending + Completed). **RA 3**
   (Rejected/Approved/Pending — cancelled/completed never render there).
4. **Q1 ruling:** Cancelled tip = "You MANUALLY CANCELLED this request." (the spec's
   "You Manually Manually this request." was a typo — confirmed by the user).
5. **Q2 ruling:** Completed tip ADDED: "The replacement class has been CONDUCTED — this
   request is complete."
6. **Q3 ruling: a REAL Overdue badge** on request-approval (a sanctioned badge-logic
   change): `urgencyLevel` gains `diffDays < 0 → 'overdue'`; `urgencyLabel → 'Overdue'`;
   new `.urgency-overdue` = solid `--color-error`/`--color-on-error` (the SAME red family
   as urgent's translucent container — one visible severity step up, same-name-same-color
   canon kept); overdue rows keep the `row-urgent` highlight (severity ≥ urgent); the
   Urgent FILTER keeps matching overdue rows (predicate extended — "preserve existing
   filtering"); tips per the user's examples.
7. **Q4 ruling:** the reset tip "Reset all filters to DEFAULT" applies to BOTH pages.
8. **Clear-all**: a `clearAllFilters()` function per page mirroring that page's OWN reset
   defaults, rendered as a chip-styled button inside the `#filterChips` innerHTML when
   chips exist; individual ✕ chips preserved; chips re-render from REAL filter state.
9. **RA label** "Filters:" → "Active Filters:" (MRH already has that label — untouched).
10. **Playwright receipt** after apply: tooltips per status both pages + dynamic urgency
    days (incl. overdue) + reset/clear-all behaviour + the RA label.

## Rejected approaches
- A shared `statusTooltip()` in ui-common.js — REJECTED: different voice per page.
- Keeping past dates as "Urgent" (tip-only Overdue) — REJECTED by the user (Q3).
- A "clear all" button OUTSIDE the chips bar — REJECTED: spec mandates inside `#filterChips`.
- Chips merely hidden while filters stay active — REJECTED by the spec itself.
- A new Overdue FILTER button — out of the user's scope; overdue stays inside the Urgent
  filter family instead (behaviour-preserving).

## Known risks
- The urgency-filter predicate change (urgent family) must not alter Normal filtering.
- The chips innerHTML rebuild must keep the Clear-all button in sync (it disappears when
  the last chip is removed — nothing left to clear).
- `requestAgeHtml(requestedAt, urgencyLabel(level))` (:662) threads the label into an age
  chip — flows through 'Overdue' automatically; verify the copy still reads right.
