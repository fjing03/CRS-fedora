## Batch 1 (proposal.md) Round 1 — 2026-10-30 07:31 UTC
### 🔴 Fixed
- (none — first pass)
### 🟡 Addressed
- (none — first pass)
### 🔴 Outstanding
- 🟡 Ruling 6 vs Out-of-scope tension (proposal.md:28 vs :96): "No status/DB logic changes beyond the sanctioned Overdue level" carves an exception into *status* logic, while Out-of-scope forbids *any* status-logic change. The Overdue exception is urgency/badge logic — reword ruling 6 to scope the exception explicitly ("no request-status logic changes; sanctioned exception = request-approval urgency badge level only").
- 🟡 Same-day tooltip string "Urgent — the Proposed Class is TODAY." (proposal.md:84-85) is new wording not present in the user's spec or Q&A tables. Sensible N=0 handling, but flag for user confirmation or at minimum assert it in the Playwright receipt so verify doesn't surprise anyone.
- PASS otherwise: full requirement coverage, no scope creep, consistent with explore-brief anchors and rulings; proceed to design.md after the two rewordings.

## Batch 1 (proposal.md) Round 2 — 2026-10-30 (fold at freeze)
### 🟡 Addressed
- Ruling 6 reworded: the sanctioned exception is now explicitly the
  request-approval URGENCY BADGE level (presentation logic) — request-status
  logic/DB/queue composition named untouched. Airtight vs the user's
  "do not change request status logic" law.
- The same-day "Urgent — the Proposed Class is TODAY." string is now marked
  in-proposal as (a) pending user confirmation and (b) asserted in the
  Playwright receipt either way.
### 🔴 Outstanding
- (none) — verdict: proposal FROZEN.

## Batch 2 (design.md) Round 1 — 2026-10-30
### 🔴 Fixed
- (none in this round — see Outstanding; fixes applied after the verdict)
### 🟡 Addressed
- (none in this round)
### 🔴 Outstanding
- 🔴1 Mobile-card status badges are MISSED on BOTH pages. MRH :632/:678 are
  the quickCancel/batchCancel confirm-dialog badges, not mobile cards; the
  real mobile cards come from shared ui-common.js HtmlBuilder.myRequestCard
  (~:2369, invoked from blade renderCards :601) and HtmlBuilder.requestCard
  (~:2340, invoked at RA blade :825) — neither emits data-tip today. The
  frozen proposal requires tips on mobile cards, so D1/D4 are amended:
  thread a page-local `opts.statusTip(status)` (both builders) and
  `opts.urgencyTip(r)` (requestCard, alongside the existing
  urgencyLevel/Class/Label callbacks) into HtmlBuilder. Minimal opt-in
  string threading; no tooltip logic in ui-common.js; absent opts keep
  existing call sites working. Relabel :632/:678 as cancel-dialog badges.
- 🔴2 D5 test 5's "bar empties" contradicts D3c's defaults law. After
  clear-all, default-state chips REMAIN (MRH: Exclude Completed; RA:
  Status: Pending + Exclude Completed) because those filters stay active by
  design. Amend the receipt to assert the bar returns to EXACTLY each
  page's default-state chips, controls at defaults, table row count = the
  non-default-filters result.
- 🟡3 Make the Overdue receipt unconditional: shift
  window.MockData.urgencyReferenceDate in-page + re-render, assert badge +
  tip + row-urgent + still matches the Urgent filter. Zero DB writes.
- 🟡4 Pin RA status='All' before the 3-status tip loop (default Pending
  makes the receipt vacuous).
- 🟡5 State that the `if (urgencyFilter !== 'all')` guard at RA :631 stays.
- 🟡6 D0 anchor: initDataTipTooltips is ~:3001, not :2917.
- 🟡7 (fold at freeze, main-session addition) RA's Urgency column-header tip
  (:562, "<=3 days: Urgent, 4+: Normal") becomes factually wrong once
  Overdue exists — copy synced to three levels (tooltip copy fix, no logic).
- Verdict: FAIL pending the two 🔴 design.md amendments (proposal stays
  frozen; both are design-level).

## Batch 2 (design.md) Round 2 — 2026-10-30
### 🔴 Fixed
- 🔴1 Mobile-card badge sites RESOLVED and re-verified against source:
  D1 relabels MRH :632/:678 as cancel-dialog badges and adds the real
  mobile-card sites (HtmlBuilder.myRequestCard ui-common.js:2369 ← blade
  renderCards :601; HtmlBuilder.requestCard :2340 ← RA blade :825); the
  opts.statusTip/opts.urgencyTip threading rides the builders' EXISTING
  opts pattern (urgencyLevel/Class/Label, requestAgeHtml); D4's ui-common.js
  row holds (minimal opt-in, statusClass untouched, absent opts keep old
  call sites working); D5 test 6 adds the mobile-card receipt both pages.
- 🔴2 D5 test 5 defaults law RESOLVED: bar must return to exactly each
  page's default-state chips (MRH: Exclude Completed; RA: Status: Pending +
  Exclude Completed); D3c reset bodies re-verified element-for-element vs
  the live listeners (:880-887 / :1177).
### 🟡 Addressed
- 🟡3 Unconditional Overdue receipt RESOLVED (in-page
  MockData.urgencyReferenceDate shift feasible — read at call time,
  PAGE_DATA overrides at :1217).
- 🟡4 status='All' pinned before the 3-status loop.
- 🟡5 urgencyFilter !== 'all' guard-stays stated at D2.5 (:631 verified).
- 🟡6 D0 anchor corrected (:3000/:3001).
- 🟡7 RA Urgency column-header tip copy sync added as D2.7 (:562 verified
  stale-copy risk).
- Fold at freeze: D0 reworded ("no new tooltip MECHANISM or CSS" — the D4
  threading carve-out no longer reads as a contradiction); D3a notes the
  frozen ui-design-templates/*.blade.php grep hits (reference templates,
  not live pages, untouched).
### 🔴 Outstanding
- (none) — no new contradictions (D4 file-table site counts match D1/D2;
  RA :502 confirmed as the approvalCard shared by Approve+Reject modals,
  no status badge there, 3-status count complete; scope fence intact).
- Verdict: PASS — design.md FROZEN.

## Batch 3 (specs) Round 1 — 2026-10-30
### 🔴 Fixed
- (none — first pass)
### 🟡 Addressed
- 🟡1 Clear-all button visibility law restated in BOTH specs (soft-freeze
  fold): the button PERSISTS beside default-state chips (D3b
  chips.length > 0 — visible even at default load) and disappears only
  when NO chip remains (✕-remove every chip → bar hides via :empty); the
  chips-bar Given now says chips render at default state too.
- 🟡2 RA receipt 1 gains the fixture-independent fallback (page.evaluate →
  statusTooltip('Pending'|'Approved'|'Rejected') asserted against the
  frozen map — Pending receipted without pending rows); MRH receipt 1
  gains the same fallback for Pending/Completed.
- 🟡3 The :562 column-header tip's exact new copy pinned in the spec AND
  design.md D2.7 (`Days until the original class: past = Overdue (solid
  red), ≤3 days = Urgent (red), 4+ days = Normal`) + asserted in RA
  receipt 3.
- Fold at freeze (cosmetic): "Overday"→"Overdue" typo fixed in the RA
  spec + explore-brief risk line; MRH receipt 6 now names all THREE
  changelog files.
### 🔴 Outstanding
- (none) — all tooltip strings verbatim vs the frozen proposal; Overdue
  machine, defaults law, renames, labels, threading receipts, page fences
  all trace clean; anchors spot-verified live.
- Verdict: PASS — specs FROZEN.

## Batch 4 (tasks.md) Round 1 — 2026-10-30
### 🔴 Fixed
- (none — first pass)
### 🟡 Addressed
- 🟡1 T3 gains the frozen requestAgeHtml() re-read clause (D2.6 risk — the
  label flows through automatically; final copy reported at apply).
  Reviewer's own source read: requestAgeHtml (ui-common.js:2802-2820)
  surfaces the label inside its data-tip, so "… · Urgency: Overdue" reads
  correctly — the re-read confirms at apply.
- 🟡2 T4 now copies D3b's pinned snippet + NEUTRAL restyle VERBATIM: the
  button's own data-tip="Remove every active filter"; the CSS = transparent
  background + dashed --color-outline-strong + --color-on-surface-variant
  ink, hover → --color-error (NOT the filled --color-primary-container
  chip look — the live .filter-chip is filled, theme.css:3579-3584, so
  without the neutral restyle the action would read as an active chip).
### 🔴 Outstanding
- (none) — full coverage T1-T8 (threading covered three ways; :562 sync;
  singular-day/TODAY strings; unconditional Overdue receipt — feasibility
  verified live: urgencyDays reads MockData at call time RA :409/:423,
  PAGE_DATA override runs once at init :1222, so the in-page shift
  survives client re-renders, zero DB writes; clear-all defaults law; 3
  changelogs). Anchors verified LIVE (exact or <=7-line drift). House law
  baked into T6/T8 verbatim. No contradictions with frozen artifacts.
- Verdict: PASS — tasks.md FROZEN. All four batches frozen.

## apply Round (T5-T6 receipt) — 2026-11-01

- 🔴→✅ **The TODAY-string ruling CLOSED by the user (2026-11-01): keep the
  frozen wording `Urgent — the Proposed Class is TODAY.`** — asserted in the
  receipt (T5.RA-2) as the frozen artifacts demanded.
- T5 receipt: `tests/status-tooltips-filter-chips.spec.ts` — 6 tests GREEN
  (MRH-1/2/3 + RA-1/2/3; the full map hover loops with the page's own-map
  fallbacks, fail-open unknowns, modal + mobile threading, the UNCONDITIONAL
  Overdue shift, the TODAY exact-string, the Urgent-filter-keeps-Overdue
  law, the header-tip copy, clear-all law + ✕ both pages).
- T6 gates: pint ✓ · phpstan(1G) 0 ✓ · node --check ui-common.js ✓ ·
  two-command ritual ✓ · request-approval specs: 9 passed + 3 PRE-EXISTING
  UNRELATED failures (req-approval-verify REQ-1/REQ-2 run as staff 5770 —
  a different user's timetable data; U3-T9f = the approve-wire toast
  probe, demo-state drift — none touch the lane's surfaces).
- Implementation deltas at receipt time: ONE line added to
  my-request-history.blade.php (`window.statusTooltip = statusTooltip;`
  beside the other window exports — the MRH map lives inside the
  DOMContentLoaded closure, so the frozen map-fallback receipt needed the
  page's own map exposed; no behaviour change) + the T5 spec itself.
- **D2.6 closed — the request-age tooltip's FINAL copy:** `requestAgeHtml`
  (ui-common.js:2811) reads `{age word} · Urgency: {level label}` —
  `Today · Urgency: Overdue` / `N days ago · Urgency: Urgent|Normal`.
  The three-level label flows through automatically; RA rows always carry
  the urgency suffix (no row is label-less).
