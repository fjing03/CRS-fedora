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

## apply — 2026-10-09 (T1–T8 execution notes)
### Apply-time errata (declarative, ledgered)
1. **Upstream spec subset → N/A in fedora.** Design §3 expected the upstream de-staled specs green; wrong assumption. Fedora's UI routes are auth-FIRST (D3, identical middleware for component/legacy) with `APP_MOCK_FALLBACK` off, while upstream specs make auth-free mock-UI assumptions (0 login calls). Verified pre-merge spec (`da20c58`) has the same structure → never green in fedora, never in the pre-change gate list. Same class as the `cancel-class.spec.ts` exclusion. Real-page coverage stands on venue-db (3/3) + timetable-wiring (5/5) + nav-identity (3/3) + phpunit (123/123). Incidental upstream-subset result: 29/100 passed. design §3 + tasks T8 updated with the erratum.
2. **`public/adminer.php` pint failure is pre-existing** (last touched by old upstream commit `bc3bc28`; merge and composer config untouched by this batch). Fixed in passing under the lint:check gate (cosmetic fixers) and noted here.
3. **Legend swatch colours adapted to the real grid** where they differ from upstream's mock-event classes: Your Classes = `--color-primary` and Others' Classes = `--color-surface-variant` (match `.vt-cell-yours`/`.vt-cell-others` per §10.0 same-name-same-colour); pending/conflict swatches upstream-verbatim. Labels/tips otherwise verbatim with the honest Available tip per frozen design.
4. **Twin-merge test target adjusted**: the occupied/pending partial unique index makes even crafted twin rows impossible, so the defensive merge is unit-tested directly via reflection on `VenueTimetable::mergeTwinEvents()` (severity, cohort join, student sum, mine-OR, singleton pass-through) instead of a DB-path test. Design §2.3's "crafted-row feature test" intent preserved.
5. **Server restart footgun noted**: `pkill -f "[a]rtisan serve"` inside a compound shell command kills the issuing shell (pattern matches the wrapper's own command line). Split into a standalone invocation; AGENTS.md's `pkill -9 php` line remains superseded.
### Gates (final)
- lint:check ✅ · phpstan --memory-limit=1G 0 ✅ · phpunit 123/123 (829 assertions) ✅ · venue-db 3/3 (incl. 4288 ownership cards 8/14 live) ✅ · timetable-wiring 5/5 ✅ · nav-identity 3/3 ✅

## verify — 2026-10-09
### ✅ Verdict: VERIFY PASSES
- All 5 checks green: frozen-requirement traceability (component/blade/tests/changelog), merge scope + conflict resolution (both sides kept, prepend convention), gate results consistent (phpunit 123/123, venue-db 3/3 with live 4288 ownership 8/14, upstream subset N/A per §3 erratum), all 5 apply-time errata ledgered, venue-timetable-db final state consistent with its frozen set.
### 🟡 Addressed
- T9 premature archive checkbox corrected before archiving (was ticked while neither change was archived).
- Traceability note: the ownership flag is `mine` in shipped code (the baseEvent contract key the modal/engine consume); merge design/T4 called it `isMine` — substantively identical, note closes the gap.
- W8 SQL spot-check result recorded: B006 W8 total=120, occupied=38 (matches the pre-merge audit).
