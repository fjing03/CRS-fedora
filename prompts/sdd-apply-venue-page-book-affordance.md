/sdd-apply venue-page-book-affordance

Apply the in-flight SDD change `venue-page-book-affordance` (U2a) — the venue-timetable booking affordance port: 100% client-side on `resources/views/livewire/venue-timetable.blade.php` + Playwright test files; zero PHP/backend changes. 15 tasks / 3 phases. Gate status lives in `.sdd/changes/venue-page-book-affordance/review-log.md` (its tail is authoritative — read it before acting; as of 2026-10-15: proposal FROZEN (4 rounds), design FROZEN (independent confirmation), tasks.md INTERIM pending its confirmation round).

Read first (mandatory):
- CodingMAIN.md — single source of truth.
- docs/venue-restrictions.md — B005/B006, P→labs: read before any venue/booking work.
- .sdd/changes/venue-page-book-affordance/review-log.md — full review history + current gate.
- .sdd/changes/venue-page-book-affordance/proposal.md — scope contract (frozen).
- .sdd/changes/venue-page-book-affordance/design.md — D0–D7 decisions (frozen 2026-10-15).
- .sdd/changes/venue-page-book-affordance/tasks.md — the 15 tasks + ⛔ banners (binding).
- resources/views/ui-design-templates/venue-timetable-UI-design-template.blade.php — frozen port source (line map in design D0).

## Gates (in order — no skipping)

**1. tasks.md confirmation round (the last gate).** Run the independent reviewer subprocess — never review your own artifacts in the main session:

```bash
pi --model commandcode/z-ai/glm-5.3-flash -p --tools read,grep,find,ls \
  --system-prompt "$(cat ~/.config/opencode/agents/sdd-reviewer.md)" \
  "ROUND: tasks.md round-1 CONFIRMATION review for change 'venue-page-book-affordance'. proposal.md and design.md are FROZEN (design confirmed 2026-10-15 by independent round). tasks.md round 1 was an implementer self-review honestly labelled NOT independent — your round confirms it. Review tasks.md against design.md, proposal.md, and review-log.md (verify prior fixes actually landed). Report [RED] blocking / [YELLOW] should-fix / [BLUE] optional. If no blocking defects, state explicitly: tasks.md round 1 CONFIRMED — freeze eligible."
```

Reviewer notes: `--model` flag REQUIRED (pi's default model 403s in CLI); do NOT use `commandcode/deepseek/deepseek-v4-pro` (hangs silently); `z-ai/glm-5.3-flash` verified working; expect minutes of silent tool-reading before the verdict.

- 🔴 → fix `tasks.md` → append to review-log.md → re-review (max 5 rounds).
- On pass: apply the reviewer-directed frozen-artifact amendment from the 2026-10-15 design round — add the five words **"on the pre-change blade"** to design.md D5 step 1 — and log it as reviewer-approved.
- Append the round to review-log.md (honest attribution: independent subprocess reviewer + model; save transcript beside the artifacts), then declare **TASKS FROZEN**.
- Only after the real freeze (or an explicit user waiver, logged) may apply start.

**2. /sdd-apply.** Follow `tasks.md` in order; the ⛔ banners in tasks.md are binding. Subagents do the editing where the sdd-workflow skill prescribes it; the orchestrating session gates and verifies.

## Take note (violation costs real data or the user's design)

1. **⛔ TC70** (`tests/venue-timetable.spec.ts:690–730`) must NEVER execute in ANY Playwright run until T8 retires it. Every run of that spec before T8 lands uses `--grep-invert "TC70"`. Never repair TC70's selectors to reach its confirm click — it drives a real destructive `CancelClass` write against the demo DB.
2. **T2 baseline runs PRE-blade-edit, before T4** — full suite = 316 tests / 25 files (12 .ts + 13 .js) with the grep guard. Order contamination invalidates the T13/T14 live-verify diffs.
3. **One login** — storageState scaffolding: setup project in `playwright.config.ts` + `tests/auth.setup.ts` → `.auth/staff-5770.json`. NEVER per-test logins inside the 65-test file. `/venue-timetable-ui` is auth + role:lecturer gated (NOT guest-reachable). Fortify limiter: 5/min per login_id|type|ip.
4. **99.9%-user-UI rule** — port byte-faithful from the frozen template; ONLY data bridges + documented one-line `BRIDGE PATCH`es. The 5-string copy set is verbatim user-approved — never reword. Wording changes are user-directed and recorded in `page-changelogs/` as user decisions.
5. **T5 bridge ordering is correctness-critical** — insert `MockData.mockNow = null;` beside the existing bridge lines (:111–112) BEFORE `generateWeekData()` (:114) and `currentWeekIndex()` (:119). Declared behavior change: the venue page opens on the REAL current week — document in the changelog (T13 canary).
6. **Inherited quirks stay byte-faithful** — focusedCell staleness, document-level keydown vs inputs (design D6) — not silently fixed.
7. **Stale-cache restart ritual after ANY blade/PHP edit** — two separate commands, never one string: first `pkill -f "[a]rtisan serve" || true`, then `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`. Kill the old server FIRST. NEVER `pkill -9 php`. Re-login as 5770 after restarts.
8. **PHP close-out** — `composer run lint:check` AND `vendor/bin/phpstan analyse --memory-limit=1G` (never `composer run types:check`; crashes at 128M).
9. **Demo DB sanctity** — `class_replacement_fresh`: never re-seed, never `migrate:fresh`; the user's real pending requests #1–#3 are untouchable. Tests run against `class_replacement_testing` only. Cleanup SQL is run by the USER (`.sdd/changes/wire-backend-into-refactored-ui/review-log.md`).
10. **Reviewer-endorsed suggestions** (from the 2026-10-15 design confirmation 💡s, recommended): keep the frozen expression `'A booking needs ' + (BOOK_SPAN * 30) + ' minutes …'` instead of the unfolded `'120 minutes'`; rewrite T8 without the editorializing self-negotiation; D5 test 4 targets non-weekend/non-holiday weekday cells for `.cell-too-soon`; T1 verifies grep/setup semantics via `npx playwright test --list -g "TC46"`.
11. **Changelog (T14)** — update `page-changelogs/venue-timetable-ui-changelog.md`: user-decision copy table, the mockNow behavior change, the box-shadow exception, the sumAvailable nit.
12. **Git policy** — branch `fedora-backend`; NEVER push unless the user explicitly says "push" (push target origin `fjing03/CRS-fedora.git` only; upstream PULL-ONLY). Conventional commits (`ui:`, `feat:`, `fix:`, `test:`, `docs:`). Do NOT touch `docs/server-commands.md` or its `docs/README.md` index line (another session's work).
13. **Honest zeros** — never fabricate pending/replacement counts; the shared `.cell-too-soon` hover "Min. 3 working days ahead" (`theme.css:2199`) stays as-is.

## Close-out

`sdd.md` tracker row + `/sdd-verify` against the proposal artifacts → suggest `/sdd-archive`. Report to the user: gate history, test results, files touched, changelog entry — and explicitly confirm TC70's disposition (retired in T8, never executed).
