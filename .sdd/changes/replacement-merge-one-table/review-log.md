# Review log — replacement-merge-one-table

Append-only. One entry per review round per batch.

## proposal Round 1 — 2026-11-03 21:38

### 🔴 Fixed
- (none — first round)

### 🟡 Addressed
- (none — first round)

### 🔴 Outstanding
- **"Reads only" is false; the cancel path has no owner.** §4 ("This lane reads only", twice)
  contradicts M6/M10 (ban icon cancels a pending request through the Confirm Cancellation modal).
  The only implementation (`MyRequestHistory::cancelRequest/cancelRequests` →
  `CancelPendingRequest` with ownership/pending guard + D6 release + audit;
  `my-request-history.blade.php:275` `#cancelConfirmOverlay`) lives in what §3 item 4 leaves as
  "retires or becomes a thin forwarder", and §3 item 1 scopes the component change as payload-only.
  Risk: ban icon with no action, or a local status write that bypasses the guard/release/audit.
  Fix: name `CancelPendingRequest` reuse + relocate `#cancelConfirmOverlay`; reword §4.
- **Test-impact inventory is wrong.** "25 + 34 test files" is unsourced (measured: 20 files
  reference `/replacement-home-ui`, 22 reference `/my-request-history-ui`; the brief says 23), and
  "the history-only ones (bulk cancel, quick-cancel column, timeline)" omits
  (a) the reversed landed receipts `tests/replacement-home-status-badges.spec.ts:41-64` (D2
  badge-link) and `tests/request-completed-status.spec.ts:104` (badge href),
  (b) the class-level tests no alias can save — `tests/Feature/ReplacementHistoriesTest.php:178-190,206-229,250-290,346-380`
  and `tests/Feature/SpanReservationTest.php:454-476`,
  (c) page-identity e2e specs `tests/e2e/history.spec.js:6`,
  `tests/e2e/smoke-my-request-history.spec.js:6`, `tests/e2e/auth-wiring.spec.js:294`,
  `tests/e2e/full-suite.spec.js:453-472,593,645,692`, which the alias cannot keep green.
  The mitigation "the alias keeps URL-level specs green" is therefore false and is the stated
  reason no further test work is scoped.

### 🟡 Non-blocking (fold into §3/§2 or park in §8)
- **URL-consumer inventory incomplete + "alias" undefined.** `notif-row.blade.php:96` does a strict
  `===` compare on the stored link string before emitting the highlight anchor; `requestHref()`
  (`replacement-home.blade.php:376`, used at `:383`, `:643`, `:649`, `:768`) is the code M6 deletes
  and would point the merged page at itself. A route-table alias at the same URI preserves the query
  string; a redirect silently drops `?notif-hl=` and `?blocked=…` (asserted in
  `ReplacementArrangementTest.php:643,723`, `arrangement-entry-guard.spec.ts:144`). The proposed
  "both URLs render the merged page" test passes for both designs, so it does not cover the risk.
- **`?notif-hl=r<id>` has no target on a class-keyed row.** `applyNotifHighlight`
  (`ui-common.js:320-341`) matches `tr[data-id="<requestId>"]` and then skips silently
  (`:356`); the merged row id is the occurrence (`ReplacementHome.php:118`). Needs both id spaces
  (`data-request-id`) or a server-side mapping.
- **Brief's filters/sort/reset-law block dropped.** Search · week navigator · status select ·
  Exclude Completed ON by default (completed lane C4) · reset · chips with ×/Clear all and the two
  different reset laws · needs-action-first grouping with sort inside groups · `.sort-hint` ·
  `.result-count` · the dash rule. M1–M12 carry none of it, yet M9 presupposes it.
- **CSS scope is three definitions, not one.** Besides the icon-only button (§3 item 8), the brief
  needs a neutral-grey conflict badge (replacing nine page-local classes,
  `replacement-home.blade.php:27-59`) and an **outlined** `Need to Arrange` pill; `theme.css` has
  only filled variants (`:1161-1183`).
- **Brief's flagged blocker not carried.** `ui-page-header.blade.php:22` escapes `$description`, so
  the locked copy with `.info-keyword` spans prints as literal HTML. Brief offers `descriptionHtml`
  + `{!! !!}` for this page, or switching the shared partial (`ui-summary-bar` is the precedent).
- **Modal shell ambiguous.** Home uses `#quickViewModal` (`replacement-home.blade.php:303`,
  `:724-757`), history uses `#modalOverlay` (`my-request-history.blade.php:256`, `:864`);
  `tests/ui-regression.spec.ts:149` pins both id spaces. M7 specifies contents only, and it is
  unstated whether the phone-card quick view survives.

### Notes
- Citation nit: `ReplacementHome.php:139-142` is `cohortCounts`; the `effectiveStatus()` call is
  `:151-157`.
- "25 + 34" is unsourced; measured 20 / 22.
- `routes/web.php:71` keeps `'nav' => 'replacement-history'` for the alias while the component
  renders `activeNav = 'replacement-arrangement'` — harmless, worth one sentence so nobody "fixes"
  it into a second nav highlight.
- Days-left stays client-side (`urgency-*` + `daysLeftLabel()`); any move of filtering to the
  server must keep that computation in the payload.

**Verdict: FAIL** — 2 blocking, 6 non-blocking. No design decision needs re-opening.
