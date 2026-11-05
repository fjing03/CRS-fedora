# Tasks — receipt-target-guard

- [x] T1 — the helper `tests/helpers/receipt-target-guard.ts`
      (`isDestructiveTestingTarget` / `abortUnlessTestingTarget` /
      `throwUnlessTestingTarget` + the boxed message with the exact blessed
      invocation; fail-closed exact strings; no defaults).
- [x] T2 — apply the guard:
      - `tests/notifications-island-testing-db.spec.ts`: module abort as the
        FIRST statement after the imports `{requireAllowSql: true}`; drop the
        `?? 'http://localhost:8000'` BASE default + the `ALLOWED` const + ALL
        THREE `test.skip(!ALLOWED, …)` lines (148/196/223) + all THREE hook
        gates (`if (!ALLOWED) return;` in beforeAll/afterEach/afterAll).
      - `tests/submission-conflict-refresh.spec.ts`: module abort
        `{requireAllowSql: true}`; drop `?? ''` default + `ALLOWED` +
        the `test.skip(!ALLOWED, …)` line.
      - `tests/ui-regression.spec.ts` TC-4.5: first-line
        `throwUnlessTestingTarget('…Submit-confirm writes a replacement_requests row…')`.
      - `tests/cancel-class.spec.ts` S9/S11 + S10 + S14 + S6: first-line
        `throwUnlessTestingTarget('…cancelClass persists a class_exceptions row…')`.
      - `tests/request-approval.spec.ts` T9f: STRUCTURAL REBUILD (reviewer
        round-1 required fix 1) — the old UI-click probe fired the decide wire
        with the first row's REAL id (write-capable on a live pending queue;
        its "fake id" comment was false). Rebuilt: direct
        `window.pageLivewire().call('approve', '["999999"]', …)` — the
        nonexistent id returns the 'already decided' loser with ZERO writes on
        ANY queue state; asserts the loser string in the response +
        pending-before == pending-after. No throw-guard needed: zero-write by
        construction.
      - File-top comment lines on both mixed files pointing at the incident entry.
- [x] T3 — gates/receipts (ALL BUT d — see the note):
      a. bare run ×2 → loud box + exit 1 (collection phase);
      b. wrong-base run → loud box + exit 1;
      c. `--list` on ui-regression + cancel-class → clean collection;
      d. the two pre-commands (testing DB only) + blessed invocation →
         both suites green;
      e. demo DB untouched: record `crs:db-row-counts` before/after the
         receipts — EXPECT UNCHANGED (whatever the current counts are).
      f. T9f's zero-write round-trip green at :8000 (runs on ANY queue state).

      **T3d OPEN (2026-11-05, re-verified by the pi session):** the conflict
      suite could not be reproduced green — two attempts at :8030 on a fresh
      seed failed INSIDE the spec's own arrival flow (first a login redirect
      timeout, then `#weekSelector` absent on the landed page), i.e. its staged
      race is not stable today and has no green record anywhere in this file.
      The GUARD concern is unaffected and proven separately (module abort at
      collection, exit 1 — see the receipts appended to review-log.md). The
      suite's own stability is its owner's follow-up, NOT a guard gate.
- [x] T4 — review round (glm), review-log receipts.
- [x] T5 — status → applied; commits (code, then sdd+records); no push.
