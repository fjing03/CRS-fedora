# PI PROMPT TEMPLATE — paste-ready, system-wide

> **How to use (3 steps):** copy this file → fill every `<<…>>` slot in §0
> (and only §0, unless the task needs a different mode) → delete the mode you
> are not using → paste the whole thing into the PI session.
> The rules below are already this repo's laws; do not soften them per task.

---

## 0. Task card — the only part that changes per task

| Field | Value |
|---|---|
| **Mode** | `<<INVESTIGATE-ONLY` or `IMPLEMENT>>` |
| **Task** | `<<one sentence: what the user wants done>>` |
| **Why** | `<<the user-facing problem or the user's own words>>` |
| **In scope** | `<<files / dirs this task may touch>>` |
| **Out of scope** | `<<what must NOT be touched — other pages, other lanes, other sessions' files>>` |
| **Deliverable** | `<<findings report` or `code + tests + changelog + receipts>>` |
| **SDD** | `<<lane name` or `skip — one-line reason>>` (see §3b) |
| **DB impact** | `<<none` or `testing DB only` or `demo DB — user-gated per write>>` |
| **Done when** | `<<3–5 measurable bullets>>` |

---

## 1. Read first (every task, non-negotiable)

- `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` — §0 Quick Status + the **latest** §0.x addendum.
- **`prompts/for-ai-to-read/my-coding-rules.md` — the user's own rulebook: read it in full and
  obey it before writing any code** (the 10 UI rules, the canonical colour map,
  OOP/PHP/naming, the commit/SDD policy). `AGENTS.md` + `prompts/for-ai-to-read/CodingMAIN.md` §10 are
  the long form; where they differ, the stricter reading wins.
- `AGENTS.md`.
- `prompts/for-ai-to-read/CodingMAIN.md` §10 (conventions + the 10 UI rules); **§4 Database safety** if
  any DB or migration work is involved.
- The closest existing implementation of the same pattern — `<<path>>` — and,
  for UI work, its `page-changelogs/<<name>>.md`.

If any of these contradict this prompt, follow the repo file and say so in your
first message.

## 2. Objective

`<<2–4 plain-language lines: the outcome the user gets, not the technique.>>`

## 3. Scope

**In:** `<<…>>`

**Out (do not touch, even if it looks tempting):** `<<…>>`, other pages, other
SDD lanes, the parked test lane, unrelated formatting/renames, other sessions'
uncommitted files. If you believe an out-of-scope file truly must change,
**stop and ask** — do not "while I'm here" it.

## 3b. SDD — use it when it earns its keep, skip it when it does not

**Open a lane** (`/sdd-propose` → `/sdd-apply` → `/sdd-verify` → archive, or the
`sdd-workflow` skill; artefacts in `.sdd/changes/<kebab-name>/`: `proposal.md`,
`design.md`, `specs/`, `tasks.md`, `review-log.md`, `sdd.yaml`) when the task is
**any** of:

- **multi-file or cross-layer** — data model + Livewire/Blade + CSS + tests;
- a **new feature or page**, or a change to a state machine, permission,
  lifecycle or a shared contract other pages consume;
- requirements that still need settling, or more than one viable design;
- likely to **span sessions**, or to need review before implementation;
- anything touching the **demo DB schema** or a migration.

**Skip SDD** — and say so in one line in your report — when the task is:

- a single-file fix, CSS/copy tweak, colour-token swap, or a mechanical
  follow-up of an already-approved lane;
- a **read-only investigation** with no code change;
- small enough that the paperwork would outgrow the diff.

**If in doubt, ask the user** — never silently skip on a medium task. If the
user explicitly waives SDD, record `SDD-waived w/ user approval` in the commit
message **and** the changelog.

## 4. Constraints (repo laws — apply as-is)

- **UI:** colours from `theme.css` custom-property tokens only; no hardcoded
  hex/rgb; reuse existing keyword / toggle / modal / tooltip families; confirm or
  undo on critical actions; never duplicate markup, CSS or mock data — promote on
  the 3rd repetition.
- **Requirements are read-only unless the user approves.** `prompts/for-ai-to-read/FR&NFR copy.md`
  (and the `CodingMAIN.md` §7 tables that mirror it) may only be edited with the
  user's explicit "yes" **for that specific change** — an approval to build a
  feature is not an approval to rewrite its requirement. If the code disagrees
  with the requirement, report the mismatch and ask; never "fix" the requirement
  as a side effect of an implementation task.
- **No new dependencies.** Reuse Flux/Livewire/Tailwind + `theme.css` +
  `ui-common.js`.
- **Honesty:** show real data or honest zeros — never fabricate counts, states or
  "receipts". If you did not run something, say so.
- **Commits:** conventional (`feat:`/`fix:`/`ui:`/`oop:`/`refactor:`/`style:`/`docs:`/`test:`),
  **only the files this task touches**, **NEVER push**. Never stage another
  session's work log (e.g. `docs/demo-db-data-changes.md` unless that log *is*
  the task).
- **Data safety:** schema changes are **additive only** (nullable column / index;
  never `change`, `rename`, `drop*`, data statements); verified `pg_dump -Fc`
  snapshot first; rehearse on `class_replacement_testing`; print the DB target
  before running; row-count receipt before/after; log every demo-DB write.
  **⛔ Never** `migrate:fresh`, `migrate:refresh`, `db:wipe`, or any reseed
  against `class_replacement_fresh` / `class_replacement_demo`; **⛔ never**
  `--env=testing` (no `.env.testing` exists — it silently boots the demo DB);
  **never** edit `.env` or switch connections.
- **Secrets:** never print or commit `.env` values beyond non-sensitive keys.
- **After a UI Blade change:** clear the stale view cache with the two separate
  commands (precise kill, then `rm -f storage/framework/views/*.php && php
  artisan serve --port=8000 &`) — never in one combined shell string.

## 5. Steps — IMPLEMENT mode

1. Confirm what exists today (grep the real files; trust code over memory).
2. Write the smallest change that satisfies §0 "Done when"; keep existing ids,
   bindings, contracts and byte-identical output where the task does not ask for
   a change.
3. Update the matching `page-changelogs/*.md` for any UI change.
4. Add/adjust tests **only** for the behaviour you changed; never weaken an
   existing assertion to make it pass.
5. Run the gates (see §7) and fix what they report.

## 5b. Steps — INVESTIGATE-ONLY mode

1. Reproduce or observe the behaviour; collect evidence (paths, lines, command
   output, screenshots).
2. Trace the real cause to a specific file:line — no speculation presented as
   fact; label anything unverified as unverified.
3. State the fix options with trade-offs and a recommendation.
4. **Write nothing** — no code, no DB, no migration, no config. The output is the
   report.

## 6. Expected outputs

- **Implement:** the diff, the changelog entry, the receipts, the commit
  hash(es) — plus the SDD lane artefacts when §3b said to open one — and
  nothing else.
- **Investigate:** findings with evidence, root cause, options, recommendation,
  and the exact files that *would* change.

## 7. Verification & receipts (always)

- `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G`
  whenever PHP changed; `node --check` for touched JS.
- Playwright/spec runs only against the disposable testing DB
  (`DB_DATABASE=class_replacement_testing …`); say which lane and which spec.
- Visual work: measure, don't eyeball — widths/heights/counts, and the
  breakpoints listed in §0 "Done when"; screenshots into `/tmp/opencode/`.
- If a gate or spec fails for a reason **outside** this task, report it and stop
  rather than "fixing" unrelated code.

## 8. Stop and ask (never guess)

- the DB target is unclear, or a safety check fails;
- the change would need an out-of-scope file, a destructive command, or a push;
- the user's request conflicts with a repo law;
- a test failure looks unrelated to the change.

## 9. Report format (keep the reply short)

1. **What changed** — `file:line` list.
2. **Receipts** — command → result (numbers, not adjectives).
3. **Notes / risks** — anything the user must know, including what you did *not*
   verify.
4. **Commits** — hash(es) + "nothing pushed", and the **SDD state** (`lane
   <name> — applied / archived` or `skipped — why`).
5. **The archive question** — the explicit yes/no ask from §10 (or
   "not archivable yet — <reason>").

## 10. Prompt housekeeping — ALWAYS ask before archiving, never do it silently

Open task prompts live in `prompts/`. A **finished** prompt moves to
**`prompts/completed prompt (Archieve)/`** — note the user's spelling, and that
this folder's `README.md` states the evidence rule ("move only on proof") and
lists every prompt already archived.

**Every task, without exception, ends with this question — ask it, do not
assume the answer:**

> "Task done. Do you want me to archive this prompt into
> `prompts/completed prompt (Archieve)/`?"

Then act on the answer:

- **Yes** → move it (`git mv` when git tracks it, plain `mv` when it matches
  `.gitignore`, e.g. `pi-prompt-*.md`), add **one row** to that folder's README
  table (prompt → the evidence: archived SDD lane / `sdd.yaml status: applied` /
  commit hash / changelog entry), and commit as `docs:`.
- **No, or no answer** → leave it in `prompts/`, say so in the report, and move
  on. Never archive pre-emptively, never "while I'm here", never as a side
  effect of another task.
- **Not provably finished** → do not ask a misleading question. Say
  "not archivable yet — <reason>" (no archived lane, no commit, no changelog
  entry) and leave it.

Never archive the toolkit, only tasks: `PI-PROMPT-TEMPLATE.md`,
`sdd-propose-template.md`, `sdd-propose-backend-template.md`,
`sdd-propose-ui-page.md` (auto-referenced by `AGENTS.md`), `sdd.md` (living
tracker) and `context-transfer-prompt.md`.
