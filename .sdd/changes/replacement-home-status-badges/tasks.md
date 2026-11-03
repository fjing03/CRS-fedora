# Tasks — replacement-home-status-badges

Frozen order (each task names its receipt). Nothing starts until the review round in
`review-log.md` passes.

- [x] **T1 — payload** (`app/Livewire/ReplacementHome.php`)
      newest-request map (all statuses, `orderBy submitted_at, id`), delete the `continue`,
      add `requestId` + `requestStatus`, D9 reason precedence.
      *Receipt:* feature tests T1a/T1b/T2/T3/T4 green.

- [x] **T2 — shared builders** (`public/js/ui-common.js`)
      `replacementHomeRow`: optional `opts.isHandled` (em dash for Days Left) and
      `opts.actionCell` (default = today's Arrange button); `replacementHomeCard`: optional
      `opts.statusBadge` + `opts.isHandled` (days pill `—`).
      *Receipt:* `node --check`, and the legacy mock template still renders its old button
      (both options omitted).

- [x] **T3 — page helpers + sort + rows** (`replacement-home.blade.php`)
      `isHandled` / `statusLabel` / `requestHref` / `statusBadge` / `actionCell` +
      `ARRANGE_BUTTON_HTML` (verbatim copy of the shared button), the D8 group key inside
      the existing comparator, the two builder callbacks passed for rows.
      *Receipt:* spec receipts 1–4.

- [x] **T4 — cards + quick view (D5)** (`replacement-home.blade.php`)
      card tap/keydown branch, `statusBadge` on the card, the *Replacement Status* modal
      row, `#qvArrangeLabel` span + the `View Request` swap, `qvArrange()` branch.
      *Receipt:* spec receipts 5–6.

- [x] **T5 — the three strings (D4)** (`replacement-home.blade.php`)
      subtitle, guide item, empty state. Summary cards untouched.
      *Receipt:* the diff shows only those three strings.

- [x] **T6 — page-local CSS** (`replacement-home.blade.php` `@section('page-styles')`)
      `.badge-link`, `.action-stack` (two rules; no theme.css change).
      *Receipt:* the label sits above the button in the 390 px + desktop screenshots.

- [x] **T7 — the page-jump (D6)** (`my-request-history.blade.php`)
      synchronous `notif-hl` capture, `jumpToRequestRow()` called once after the first
      render, toast fallback when a filter hides the row.
      *Receipt:* navigation receipt in the spec; page-2 jump declared as a named manual
      path in the changelog (needs >10 rows).

- [x] **T8 — feature tests** (`tests/Feature/ReplacementHomeTest.php`)
      T1 rename/rewrite, T2 holiday precedence, T3 newest request, T4 nulls.
      *Receipt:* the measured count of `--filter=ReplacementHomeTest`.

- [x] **T9 — browser spec** (`tests/replacement-home-status-badges.spec.ts`, NEW, read-only)
      six receipts from `design.md` §6 against the live demo DB, no writes.
      *Receipt:* the measured pass count, run twice.

- [x] **T10 — gates** `composer run lint:check` · `vendor/bin/phpstan analyse
      --memory-limit=1G` · full suite on `class_replacement_testing` (expect ≥312, quote the
      real number) · `node --check public/js/ui-common.js`.

- [x] **T11 — changelogs + commit** `page-changelogs/replacement-home-changelog.md`,
      `page-changelogs/my-request-history-changelog.md`; one commit
      `feat(home): the row keeps its request status — pending/approved badge, rejected/cancelled label, handled-row sorting + the My Requests page-jump (replacement-home-status-badges)`.
      Stage only this lane's files. **Never push. Never archive.**

- [x] **T12 — review round 2** (after code): verify the implementation against the frozen
      artifacts, record honestly, leave `sdd.yaml` at `applied` (or `verify-failed`).
