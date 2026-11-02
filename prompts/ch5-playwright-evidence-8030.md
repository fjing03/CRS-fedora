# Prompt — refresh Chapter 5's browser evidence on the disposable `:8030` lane

> Hand this file to a fresh OpenCode session working in
> `CRS-fedora-backend`. It is self-contained: read it top to bottom, then act.
> The session does **not** need this conversation.

## Deliverables (all three)

1. **The run report** — create/overwrite the new file
   `../FYP/draft/KO stage/Ch5/ch5-browser-evidence-run.md`
   (= `/home/philler/Desktop/tarumt/degree/y3s1/BMCS3404 PROJECT I (4)/FYP/draft/KO stage/Ch5/ch5-browser-evidence-run.md`).
   A template with an `AWAITING RUN` banner already exists; replace the banner
   with a real header, fill every table from actual runs. Never invent a row.
2. **The draft** — update
   `../FYP/draft/KO stage/Ch5/ch5-testplan-draft.md`: the "Evidence freshness"
   block, the §5.3 counts, and the §5.5 Status/Evidence columns, pointing at the
   report file for proof.
3. **The test-infra patch** — `playwright.config.ts` + the 14 specs that
   hardcode `:8000`, committed in `CRS-fedora-backend` (see Phase 1).

Working directory: `.../BMCS3404 PROJECT I (4)/CRS-fedora-backend`
The two FYP files live **outside** this repo (in the `y3s1` repo).

Goal: every "pass" in the Chapter 5 draft must become a measured receipt from the
CURRENT code, produced in a real browser against port `:8030` (the disposable
testing-DB lane) — not inherited evidence recorded before tonight's commits.

## 0. Read first, in this order

1. `prompts/for-ai-to-read/AGENTS.md` — standing rules, port map, DB law.
2. `prompts/for-ai-to-read/my-coding-rules.md` — quick rulebook (commits, UI).
3. `prompts/for-ai-to-read/CodingMAIN.md` — §4 Database safety, §10.6 git policy,
   and the port line (~139).
4. `docs/demo-db-data-changes.md` — the TOP entry (2026-11-01 data-loss incident;
   this is why `receipt-target-guard` exists and is fail-closed).
5. `tests/helpers/receipt-target-guard.ts` — the exact contract it enforces.
6. The draft — "Evidence freshness", §5.3, §5.4, §5.5.

## 1. Hard constraints (non-negotiable)

- Ports: `:8000` = dev on `class_replacement_fresh`; `:8001` = demo twin.
  **Only `:8030` may be used for this task.** Never point a spec at `:8000`/`:8001`.
- Never use `--env=testing` (no `.env.testing` exists; it silently boots the demo DB).
- The ONLY database you may reset is `class_replacement_testing`
  (`migrate:fresh --seed` is fine there). Never `migrate:fresh` / `db:wipe` /
  reseed `class_replacement_fresh`, `class_replacement_demo`, `class_replacement_viva`.
- Never `pkill -9 php`. Never kill or restart the `:8000` server.
- No push. Conventional commits only (`test:`, `docs:`).
- Commit only the files this task touches.
- Never edit `prompts/for-ai-to-read/FR&NFR copy.md`.
- Do NOT fix application code. Record the failure, propose a fix, and ask the
  user first. (Explicit decision: fixes only after approval.)

## 2. Before anything: repo-state warning

`git status` first. Another session has uncommitted work in the test tree:

- modified: `tests/cancel-class.spec.ts`, `tests/notifications-island-testing-db.spec.ts`,
  `tests/request-approval.spec.ts`, `tests/submission-conflict-refresh.spec.ts`,
  `tests/ui-regression.spec.ts`
- untracked: `tests/helpers/receipt-target-guard.ts`, `.sdd/changes/receipt-target-guard/`

Never stage, revert, reformat or "clean up" any of that. If your patch must touch
one of those five spec files, make it a surgical single-line change and say so in
the report. If unsure, stop and ask. The report must also state that results
reflect the **working tree** (dirty tests), not HEAD.

## 3. Safety defect to fix BEFORE the first run (mandatory)

`tests/ui-regression.spec.ts:10` hardcodes `const BASE = 'http://localhost:8000'`,
while TC-4.5 at `:242` is guard-protected (`throwUnlessTestingTarget`). The guard
checks ENV VARS ONLY, not the navigation target — so with
`CRS_BASE=http://127.0.0.1:8030` exported the guard passes and that test submits
a real replacement request to `:8000` = the demo DB. That is the 2026-11-01
incident shape again. Fix that BASE line (Phase 1) before running anything, and
call it out in the commit message, the report and the final reply.

## 4. Phase 1 — make the base URL env-driven (test infra only)

- `playwright.config.ts`: `baseURL: process.env.CRS_BASE ?? 'http://localhost:8000'`
  (keep the `:8000` fallback so ordinary runs are unchanged).
- These 14 spec files hardcode `http://localhost:8000` and currently CANNOT be
  redirected by env:
  `arrangement-entry-guard`, `class-modal-affordances`, `clear-guards-cross-week`,
  `cohort-venue-replace-now`, `confirm-guards`, `login-staff-prefix`,
  `pages-parity`, `rearrangement-toolbar-tooltips`, `status-tooltips-filter-chips`,
  `timetable-wiring`, `ui-regression`, `venue-note-privacy`,
  `venue-restrictions-enforcement`, `warning-keywords`.
  Re-grep to confirm (`grep -rn "localhost:8000" tests/*.spec.ts`).
  Convert each to `const BASE = process.env.CRS_BASE ?? 'http://localhost:8000'`
  and compose URLs from it. Watch the two special shapes:
  `warning-keywords.spec.ts` (separate `RH` const) and `venue-note-privacy.spec.ts`
  (two literals; its comment about host-scoped storageState becomes relevant).
- Prove collection still works: `npx playwright test --list` (43 spec files).
- Commit: `test: let the browser specs target the disposable :8030 lane via CRS_BASE`

## 5. Phase 2 — start the lane

- Clear stale compiled views ONCE (do NOT restart `:8000`):
  `rm -f storage/framework/views/*.php`
- Reseed the disposable DB (allowed here):
  `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`
- Serve:
  `setsid nohup env DB_DATABASE=class_replacement_testing php artisan serve --host=127.0.0.1 --port=8030 > /tmp/opencode/serve-8030.log 2>&1 &`
- Verify HTTP 200 on `http://127.0.0.1:8030/login`.

**Use `127.0.0.1`, never `localhost`:** the guard's `REQUIRED_BASE` is exactly
`http://127.0.0.1:8030`, and session cookies are host-scoped, so mixing the two
silently logs you out.

## 6. Phase 3 — run

Export for every invocation:
`CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030`

- `--workers=1` — `php artisan serve` is single-threaded; parallel workers flake.
- `--reporter=list,html` — `playwright-report/` is gitignored; it is the HTML
  evidence the draft promises.
- Let the `setup` project run (writes `.auth/staff-5770.json`, `4363`, `5425`).
  Use `--no-deps` only for specs that log in themselves.

Order:
1. The draft's priority re-run list first (14 files, §"Evidence freshness").
2. Then every remaining file, EXCEPT the 6 guard-protected ones below.
3. Then each guard-protected write spec SOLO, reseeding immediately before each:
   `cancel-class`, `notifications-hide-manage`, `notifications-island-testing-db`,
   `request-cancel-pending`, `submission-conflict-refresh`, `ui-regression`.
   `DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed`
   then `npx playwright test tests/<file> --workers=1 --no-deps --reporter=list,html`
   (re-run setup if that spec relies on storageState; a reseed wipes sessions).
- Fortify login limiter is 5/min per identity+IP. If you hit it, wait 60 s and
  retry that file ONCE; never loop.

Record for every file: verdict, duration, command, and the code commit
(`git rev-parse --short HEAD`) plus "working tree dirty (tests)".

## 7. Phase 4 — triage every red

`:8030` runs the TESTING seed (189 users), not the demo dataset (169) — ids and
fixtures differ. Classify every failure as exactly one of:

(a) real regression in current code
(b) seed/fixture drift (the spec asserts demo-data ids/counts the testing seed
    does not reproduce) — cite expected vs actual values
(c) environment/timing (single-threaded serve, login limiter, cold boot)

For each red: file:line of the failing assertion, expected vs actual, class, and
the evidence path. NEVER edit a test to force green. NEVER mark a test case pass
without a run.

## 8. Phase 5 — the report file (new md)

Create/overwrite `../FYP/draft/KO stage/Ch5/ch5-browser-evidence-run.md`.
A template already exists; it starts with a loud `AWAITING RUN` banner. Replace
that banner with a real header and fill every section. Required structure:

1. **Header table** — run date/time, code commit hash, working-tree state,
   lane URL, DB name, seed command + time, Playwright version, node version.
2. **Commands actually run** — a single fenced block with the exact env vars and
   every `npx playwright test …` invocation, copy-pasteable.
3. **Summary** — specs total / run / pass / fail / skipped / not re-run, and the
   6 write receipts called out separately. Numbers must add up to 43.
4. **Per-spec results table** — `# | spec file | verdict | duration | note`,
   one row per spec, in the order they were run. No blanks: use `not re-run`
   with a reason.
5. **Priority list** — the draft's 14 files, each with its verdict.
6. **Write receipts** — for each of the 6: reseed time, spec, DB rows before and
   after (one line of SQL or `psql` output), verdict.
7. **Failures** — one subsection per red: id, spec, file:line, expected vs
   actual, classification (a/b/c), evidence path.
8. **Environment deviations** — anything that differs from a clean run (limiter
   waits, retries, reseeds, host/browser quirks).
9. **Not covered / residual risk** — what this run does NOT prove (e.g. NFR
   performance targets, real email delivery), stated plainly.
10. **Evidence locations** — `playwright-report/`, traces, screenshots (paths
    only; they are gitignored).

Honesty rules for this file:

- A result is only valid with a real date + commit hash + the command above it.
- If a section has no data, write `not run` / `none observed` — never leave it blank
  and never fill it from the draft's optimism.
- The report is evidence, not marketing: failures stay in, with their class.

## 9. Phase 6 — update the draft

- Rewrite "Evidence freshness" with: run date/time, commit hash, exact commands +
  env vars, and which passes are now measured vs still inherited. Link the
  report file by relative path.
- §5.3 numbers: 43 spec files; **6** guard-protected write files (the draft says
  5 — correct it); 37 read-only. Priority list is 14 files, not 12.
- §5.5 Status column: `pass (spec, date)` / `fail — see report F0N` / `not re-run`.
  Keep TBC and pending honest; never silently upgrade them.
- Edit tables/status/freshness only. Do not rewrite the informal prose, and do
  not "correct" unrelated claims (e.g. the framework version) in this task.

## 10. Phase 7 — commit + close the lane

- `CRS-fedora-backend`: commit only the Phase-1 test-infra patch.
- The draft and the report are in a DIFFERENT repo (`y3s1`). Path-scope them:
  `git -C ../.. status --short`; if anything unrelated is staged, STOP and ask.
  Then `git -C ../.. add "FYP/draft/KO stage/Ch5/ch5-testplan-draft.md" "FYP/draft/KO stage/Ch5/ch5-browser-evidence-run.md"`
  and commit `docs: refresh Chapter 5 browser evidence from the :8030 sweep`.
  Never stage the pending deletions or anything else in that repo.
- Kill only the lane: `pkill -f "[a]rtisan serve --host=127.0.0.1 --port=8030"`.
- Confirm `:8000` still answers and was untouched. No push.

## 11. Final reply

- Lane, date, commit hashes; counts run / pass / fail / not-re-run.
- Link the report file and the draft diff in one line each.
- Every red with its classification.
- Confirmation that no demo DB was written and that `:8030` is stopped.
- Anything that still needs the user's decision.

## Do not

- run anything against `:8000` / `:8001`, or leave `:8030` running
- install packages / edit `package.json` / add dependencies
- touch `app/`, `resources/`, `database/` — only `tests/` (base-URL lines) and
  `playwright.config.ts`
- mark a requirement pass by inference from a different test
- fix app code without explicit approval
