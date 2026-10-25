# docs/ — Project Documentation

Current, load-bearing docs. Superseded material is in [`archive/`](archive/) — do not follow archived instructions.

| File | What it is | Status |
|------|-----------|--------|
| [`venue-restrictions.md`](venue-restrictions.md) | B005 no-Diploma / B006 allow-disallow + AMIT2034 lab-only / booking rules | **Active** — B005 enforced (drives live derived conflicts); the rest are the spec for Wave 3b booking validation |
| [`replacement-arrangement-pending-bugs.md`](replacement-arrangement-pending-bugs.md) | Arrangement page bug round (2026-10-24): BUG-01–03, 05–07 pending user verify; BUG-04 closed by user decision (no bell there, by design) | **Active** — findings only, no fixes yet |
| [`fcb-theme-colors.md`](fcb-theme-colors.md) | FCB-inspired color system — the design source for `public/css/theme.css` tokens | **Reference** — implemented in theme.css; consult when touching colors |
| [`two-machine-workflow.md`](two-machine-workflow.md) | MSI (primary) + HP (secondary) two-machine protocol: one-trunk branch policy, serialize rules, per-machine DB landscape, boot ritual | **Active** — read when working across machines, touching branches/remotes, or unsure which DB a name refers to |
| [`server-commands.md`](server-commands.md) | Start / restart / stop `php artisan serve` — the stale-cache ritual, deep-clean sequence, fish-shell quirks, queue-worker restart | **Active** — use whenever the dev server must be started, restarted after a UI change, or stopped |
| [`demo-db-data-changes.md`](demo-db-data-changes.md) | Running log of intentional demo-DB edits (live SQL + matching seeder line per entry) + snapshot convention | **Active** — read before touching the demo DB or `DatabaseSeeder.php`; append entries, never "repair" logged drift |
| [`archive/FYP-BRIEFING.md`](archive/FYP-BRIEFING.md) | Old AI-briefing snapshot (2026-08-11) | Superseded by `CodingMAIN.md` |
| [`archive/BACKEND-TASKS.md`](archive/BACKEND-TASKS.md) | Phase-1 backend build plan | Done — schema fully migrated; kept as booking-schema rationale (Wave 3b) |
| [`archive/LEFTOVER_TASKS.md`](archive/LEFTOVER_TASKS.md) | Post-merge chore tracker (2026-08-27) | Done — historical record |
| [`archive/CodingMAINfedora.md`](archive/CodingMAINfedora.md) | Old fedora CodingMAIN variant | Folded into canonical `CodingMAIN.md` |
| [`archive/CodingMAIN-upstream-original.md`](archive/CodingMAIN-upstream-original.md) | The original root CodingMAIN.md before the 2026-10-10 rewrite | Kept for upstream-merge diffing |

Canonical entry points: **`/AGENTS.md`** (session rules) → **`/CodingMAIN.md`** (project spec).
- `docs/queue-and-emails.md` — SDD span-aware-approval-flow runbook: dev = log mailer (nothing sends), the database-backed queue job lifecycle, `queue:work --once` for demo decisions, the SMTP later-flip.
