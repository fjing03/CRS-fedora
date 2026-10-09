# Review Log — merge-upstream-ui-2026-10

## proposal Round 1 — 2026-10-09
### 🔴 Fixed
- **Card-set contradiction (scope item 2)**: proposal referenced upstream's "Unavailable" card and left the final card set conditional, contradicting the built view (card 4 is **Occupied** `#sumOccupied` per venue-timetable-db design R2/R3) and `tests/venue-db.spec.ts` (asserts `#sumTotal`=120, `#sumOccupied`=48) which the same proposal requires green. Fixed: card set pinned exactly — Total (`#sumTotal`), Available (`#sumAvailable`), My Teaching Classes (`#sumMyClasses`), My Teaching Hours (`#sumMyHours`), Occupied (`#sumOccupied`, label unchanged — "Unavailable" would be a regression against the frozen venue-timetable-db design). `#sumPending` is removed (no v1 pending data; upstream dropped it too). `tests/venue-db.spec.ts` card assertions explicitly amended in this change.
### 🟡 Addressed
- Server-restart + no-re-seed guards restated in the proposal (AGENTS.md actively instructs the harmful `pkill -9 php`; brief alone is not operative during apply).
- Upstream ungated spec subset: pinned to be enumerated concretely in tasks.md (Batch 3).
- Changelog entry for the venue view adaptation added to scope.
- `composer run lint:check` added to the gate list.
- Archive note: apply venue-timetable-db proposal soft-freeze erratum (scope item 2 week-filter wording) when archiving.
### 🔴 Outstanding
- (none)

## proposal Round 2 — 2026-10-09
### 🟡 Addressed
- Changelog target corrected (declarative one-token fix): venue adaptation entry goes to `page-changelogs/venue-timetable-ui-changelog.md` (has upstream-sync precedent), not `backend-automated-by-ai.md`.
### 🔴 Outstanding
- (none)

**Verdict: PASS** — proposal.md is FROZEN as of this round (with the line-25 declarative fix applied).

## design Round 1 — 2026-10-09
### 🔴 Fixed
- **Legend 6-vs-7 contradiction**: design enumerated 7 items while frozen proposal said "6-item legend". Authoritative count taken from `git show upstream/fjing:...venue-timetable-UI-design-template.blade.php`: **7 items** (Available, Your Classes, Others' Classes, Others' Pending, Your Pending, Your Conflict, Others' Conflict — the 6-item state was b47221e; e7f8036 split Your Conflict into Your/Others Conflict). Resolution: design was factually right → **soft-freeze erratum applied to frozen proposal scope item 2** (count 6 → 7 + authoritative item list, declarative correction against upstream reality, same class as the R2 "Unavailable" fix). Design §2.2 count corrected to "7 items" for consistency.
### 🟡 Addressed
- **Honest tips**: §2.2 no longer says "verbatim" for tips — labels/colours/swatch classes verbatim; action-implying tips (Available's "click to book") keep the honest rewording from venue-timetable-db design R1 fix 3, per the frozen proposal's "read-only and honest" pin.
- **Changelog bullet** added to design §2.2 (venue-timetable-ui-changelog.md) so the proposal requirement has design→tasks traceability.
- Test reference renumbered: summary-cards test is **test 2** in venue-db.spec.ts (test 1 = deep link).
- Card 1 display label: blade's "Total Slots" kept (cosmetic; the pin that matters is `#sumTotal`).
- Commit type note: `chore(sdd)` ≡ `docs(sdd)` if AGENTS.md enforcement matters.
- CSS fallback risk retired: `card-replacement`/`card-hours`/`card-conflict` verified present in the post-merge theme.css (lines 1288–1294, checked via merge-tree blob).
### 🔴 Outstanding
- (none — pending Round 2 confirmation)

## design Round 2 — 2026-10-09
### 🟡 Addressed
- Residual "6-item" references in frozen proposal (Why §, success criterion 2) corrected to "7-item" — declarative one-token errata per reviewer instruction.
### 🔴 Outstanding
- (none)

**Verdict: PASS** — design.md is FROZEN as of this round.

## tasks Round 1 — 2026-10-09
### 🟡 Addressed
- T5 tip rule widened to the frozen design rule: "EXCEPT action-implying ones (known instance: Available's)" instead of only Available's.
- Pre-checked during prep: `php artisan migrate:status` → 0 pending pre-merge (T3 expectation confirmed).
### 🔴 Outstanding
- (none)

**Verdict: PASS** — tasks.md is FROZEN as of this round. All batches frozen → proceed to apply.
