# AGENTS.md — bootstrap pointer (TARUMT Class Replacement System)

> Agent harnesses auto-load **`AGENTS.md` at the repository root**, so this file stays
> here as a pointer only. The real, maintained AI context lives in
> **`prompts/for-ai-to-read/`** (moved there 2026-11-02). Nothing else is kept here.

## Read these before doing anything, in this order

1. **`prompts/for-ai-to-read/AGENTS.md`** — how to work in this repo: read-first list, SDD/UI rules, standing rules, git policy.
2. **`prompts/for-ai-to-read/CodingMAIN.md`** — the single source of truth; read it **in full** (§4 DB safety, §7 FR & NFR, §10 conventions + UI rules, §10.6 git policy).
3. **`prompts/for-ai-to-read/my-coding-rules.md`** — the user's own rulebook in quick form (10 UI rules, canonical colour map, OOP/PHP/naming, commit/SDD policy).
4. **`prompts/for-ai-to-read/PI_Chat_Context_Handover.md`** — §0 Quick Status + the **latest** §0.x addendum: what the last session did, what is next, what is parked.

The memory import below is that same rulebook, inlined by harnesses that support `@path`:

@prompts/for-ai-to-read/my-coding-rules.md

## Path convention for the moved files

Paths written **inside** those files are relative to the **repository root** unless they start with `../` (then they are relative to the file itself). So a mention of `CodingMAIN.md`, `AGENTS.md`, `PI_Chat_Context_Handover.md` or `my-coding-rules.md` "at the root" means the copy in `prompts/for-ai-to-read/`. Dated entries in `page-changelogs/` and `.sdd/archive/` still use the old root paths — those were deliberately not rewritten.

## Non-negotiables (unchanged, repeated here only because they are safety rules)

- **Never push** unless the user explicitly says so. Commit only the files the current task touches; never stage another session's work log (`docs/demo-db-data-changes.md`) unless that log *is* the task.
- **Never** `migrate:fresh` / `migrate:refresh` / `db:wipe` / reseed the demo DBs. **Never** `--env=testing` (no `.env.testing` exists — it silently boots the demo DB); select the testing DB only with `DB_DATABASE=class_replacement_testing`.
- Schema changes on the demo DBs are **additive only** (nullable column / index; never `change`/`drop`/data statements) — verified `pg_dump -Fc` snapshot first, rehearse on `class_replacement_testing`, print the target, then plain `migrate`, row-count receipt + log in `docs/demo-db-data-changes.md`.
- Conventional commits (`feat:`/`fix:`/`ui:`/`oop:`/`refactor:`/`style:`/`docs:`/`test:`). Reuse Flux/Livewire/Tailwind + `theme.css` + `ui-common.js`; no new dependencies; colours from `theme.css` custom-property tokens only.
