# Review Log — debt-api-routes-agents-md

## proposal Round 1 — 2026-10-10
### 🔴 Fixed
- **T2/T3 contradiction on unused imports**: keeping both `use` lines while commenting all routes would fail `lint:check` (pint `laravel` preset enforces `no_unused_imports`). Fixed: T2 now comments out BOTH `use` lines with an "uncomment together with the routes below" note.
### 🟡 Addressed
- Orphan-claim accuracy: `tests/e2e/full-suite.spec.js` also references `/api/v1` (8 test.describe blocks + route aborts) — added to the legacy "do nothing" bucket alongside `api-wiring.spec.js`. Conclusion unchanged (both legacy, outside gates); code edit identical.
### 🔴 Outstanding
- (none)

**Verdict: PASS after the prescribed amendments** (reviewer: "FAIL pending one amendment — then ready"; both declarative fixes applied exactly as prescribed). Proposal FROZEN.

## verify — 2026-10-10
### Gates
- `composer run lint:check` → passed (pint, laravel preset)
- `composer run types:check` → passed (phpstan, 0 errors)
- `php artisan test` → 129/129 passed, 845 assertions
- `php artisan route:list | grep -c api/v1` → 0 (all 8 endpoints gone from route table)
- `php -l routes/api.php` → valid
- Gated specs (`venue-db`, `timetable-wiring`, `nav-identity`, `tests/Feature/`) → zero `/api/v1` references (re-checked post-edit)
### Applied
- `d731e53 chore(debt): comment out 8 orphaned api/v1 routes; fix AGENTS.md pkill -9 line`
### Notes
- T1 applied with a one-clause addition beyond the literal line swap: appended "(Never `pkill -9 php` — it force-kills every PHP process on the machine.)" so future agents see the *reason*, not just the new command. Declarative doc clarification, same intent.
- Flagged to user, out of scope here: AGENTS.md line 38 ("Reset demo data: `php artisan migrate:fresh --seed`") is the same class of harmful instruction (forbidden on the demo DB) — needs its own decision.

## post-archive addendum — 2026-10-10 (user-approved fold-in, same debt class)
- AGENTS.md line 38 ("Reset demo data: `php artisan migrate:fresh --seed`") fixed — same harmful-instruction class as line 37, explicitly agreed with user. New text: demo DB never re-seeded / never migrate:fresh; migrate:fresh --seed allowed only on class_replacement_testing; pristine-state restore via snapshot at /home/jinglinux/tarumt/backups/README.md.
- Safety snapshot taken OUTSIDE the repo: /home/jinglinux/tarumt/backups/class_replacement-pristine-2026-10-10.dump (303 KB, 26 tables, verified counts) + README with exact restore procedure for presentation resets.

## post-archive addendum 2 — 2026-10-10 (user-approved fold-in, same debt class)
- AGENTS.md line 37 (restart one-liner) fixed AGAIN — the 2026-10-10 safe-pattern replacement had a latent flaw: run as ONE `bash -c` compound, `pkill -f "[a]rtisan serve"` matches the wrapper's own cmdline (the tail contains `php artisan serve --port=8000`) and SIGTERMs it. Discovered live while applying venue-event-blocks-db T8 (the tool call killed itself). No single-string compound can be safe — the kill pattern must always match the serve text, so kill and serve MUST be separate commands. New text teaches the two-command split with the why. Third AGENTS.md harmful-instruction fix in this debt family (line 37 pkill -9, line 38 migrate:fresh --seed, line 37 self-kill).
