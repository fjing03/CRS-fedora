# Tasks — notif-ad2-pagekey-tab

## T1. The one-line fix (≤0.25h)
- [x] `resources/views/partials/ui-nav-bar.blade.php:127` —
      `'pageKey' => $pageKey ?? $activeNav ?? ''` + the D2 comment.

## T2. The mapping receipt (≤0.5h)
- [x] The NotificationsBridgeTest addition (D3): the camel keys map to the
      intended tabs (pl / student / lecturer rows in-list assertions),
      empty-key lecturer default.

## T3. Gates + bookkeeping (≤0.5h)
- [x] lint:check + phpstan 0 + the full suite (276 → 277+).
- [x] The stale-serve ritual + the :8000 Playwright island receipts green
      (re-run vs the FIXED behavior; the testing-DB spec unchanged but
      rerun for regression).
- [x] `page-changelogs/notifications-panel-changelog.md` entry; close BOTH
      stale "UNDER USER VERDICT" in-code notes (comment-only): `ui-common.js`
      AND the pageKey repair note in `tests/notifications-island.spec.ts:49-52`
      (round-1 🟡); tighten the receipt's row-visible leg there (assert the pl
      row's anchor is really rendered in the list on /request-approval-ui).
- [x] ONE conventional commit (`fix: ad2 — ...`), no push.

## T4. SDD bookkeeping (≤0.15h)
- [x] sdd.yaml → applied after the gates; the lane archived ONLY on the
      user's word (the house archive rule).

## T6 (demo, user-run, optional)
- [x] The user may already have run T6's drafted rows from the re-verify
      round (docs/demo-db-data-changes.md 2026-10-29) — no new demo rows
      are needed for this lane: the real pl row 3 is the pl-tab proof on
      /request-approval-ui.
