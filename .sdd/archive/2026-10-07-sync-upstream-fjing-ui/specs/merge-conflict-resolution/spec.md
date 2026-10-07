# Spec — merge-conflict-resolution

Scope of this spec: the observable *requirements* the merge must satisfy — searchable properties of post-merge artifacts. Aligned with `proposal.md` (frozen) criterion 2 and `design.md` §4/§5/§8.

## Scenarios

### S1 — RBAC matrix keeps cohort scoping (H4, ours)
Post-merge `CodingMAIN.md` **MUST** (the two output assertions are the gate):
- `grep -c "cohort-scoped, FR 1.3–1.4"` ≥ 1 (ours' cohort-scoped row survives verbatim in the §6 permissions matrix)
- `grep -c "View global replacement history ledger"` == 0 — the phrase must be **absent**, not merely reduced; students can never hold a *global* per-role row in the §6 permissions matrix
Justification record (immutable, evidence-based):
- `../final/FR&NFR.md` FR 1.3/1.4 = *"…for their cohort"*; FR 2.15 = *"own"* — upstream's row contradicts all three
- `../final/FR&NFR.md` is 110 lines; the FR-1.2 gloss our repo carries at `CodingMAIN.md:166` (§6 matrix note) is the cross-document note the spec's narrative refers to

### S2 — §7.2 takes upstream 3-state wholesale (H9, theirs)
Post-merge `CodingMAIN.md` **MUST**:
- `grep -c "Available, Pending, and Occupied"` ≥ 1 (upstream's 3-state FR 4.11 lands)
- `grep -c "Pending (Self), Reserved (Other), and Occupied"` — **≥ 1 pre-merge** (executed: 1, at `CodingMAIN.md:251` — the phrase is unique to ours' 4-state FR 4.11 row; `:45`'s slot-state row uses `Pending (Self)` with a distinct tail and does **not** match), **== 0 post-merge** (the sentinel phrase dies exactly when H9 takes theirs)
Evidence chain (immutable):
- `../final/FR&NFR.md:61` FR 4.11 is 3-state with `(Harel, 1987)`
- the token `reserved` appears **nowhere** in `../final/FR&NFR.md` (case-insensitive; file is 110 lines)
- the `time_slots_status_check` CHECK constraint (`2026_08_03_000006_create_time_slots_table.php:31`) lists **exactly** `available`, `pending`, `occupied` — described semantically rather than quoted, since the live line carries escaped quotes a literal grep cannot match
- `grep -rn "'reserved'" app/ database/migrations/` == 0 — code never sets or reads it

### S3 — Route key parity (S7/S8 follow-on)
Post-merge `routes/web.php` **MUST**:
- keep the `$uiPages` array + `foreach` loop (never upstream's explicit `Route::get` closures)
- execute the §5 fenced command: expected **8** at S8 completion (**7** immediately post-S7, before the S8 keyed edits — run the gate at S8 completion, not mid-merge):
  `grep -cE "^\s+'/(my-timetable|cohort-timetable|student-my-timetable|replacement-history|replacement-home|my-request-history|venue-timetable|request-approval)-ui' =>" routes/web.php`
- contain **no** key `'upcoming-replacements-ui'` (`grep -c "upcoming-replacements" routes/web.php` == 0; pre-merge truth: 3 occurrences at :49/:51/:52)
- contain key `'replacement-history-ui'` carrying **all four** design §4.1 fields verbatim — `'component' => 'App\Livewire\ReplacementHistory'`, `'legacy' => 'ui-design-templates.replacement-history-UI-design-template'`, `'nav' => 'replacement-history'`, `'mw' => ['auth', 'role:student']` — asserted as **exact array contents, not merely "including"**: `legacy` and `nav` are load-bearing because `class_exists` at `routes/web.php:88` is false pre-merge, so `$page['legacy']`/`$page['nav']` are the *only* render path (verified `:90–92` fallback) — a key stripped to component+mw produces an undefined-array-key 500, the exact failure mode the proposal's risk table forbids
- `grep -c "class_exists"` ≥ 1 at `routes/web.php:88`'s `$useComponent` ternary — the legacy-view fallback must survive

### S4 — page-changelogs, both sides, none dropped (S7)
For each of `cohort-timetable-ui` / `my-timetable` / `replacement-arrangement` / `student-my-timetable-ui` / `venue-timetable` / `my-request-history` / `replacement-home` / `request-approval` **MUST**:
- `git diff <S3-tag>..HEAD -- <path>` shows **no deletions vs our pre-merge side** (diffed against the S3 tag, never against `438bdfe` — that ref predates the concurrent session's 4 commits and would misattribute their edits to the merge)
- newest-first ordering respected where a conflict was resolved (theirs 10-02 block before ours 08-31 block in the 3 resolved files)

**And the 9th, explicitly required by frozen criterion 2** (the one both-modified auto-merge — historically the most silently-failure-prone path):
- `page-changelogs/todo list/todo-list.md` (path spans a literal space — quote it!) diffed against the S3 tag shows **both retained**: our `633eeb3` rollover-line edits (upstream's `fix(auth)` commit touched it) *and* upstream's independent edits to the same file; it produces **no** conflict, so git's result is accepted — this gate exists to confirm acceptance was correct, unlike S4's files where upstream-vs-ours interaction is noisy

### S5 — old-name file deleted (S7)
*`page-changelogs/upcoming-replacements-ui-changelog.md`* must not exist post-merge (`test ! -f …`); `git rm`, not checkout-restore

### S6 — `ui-common.js` merged cleanly (S7)
`public/js/ui-common.js` **MUST**: contain zero conflict markers; keep the greppable symbol set — `to12h`, `formatDate`, `toggleTheme`, `updateIcon`, `navigateHome` (pre-merge truth: `:11/:3/:20/:1409/:1410` rescanned 2026-10-06 Batch 3 Round 1) *plus* the consolidation-entry points `getWeekNumber`, `statusClass`, `requestAgeHtml`, `getWeekRange`, `isInWeek`, `populateWeekSelect`, `updateNavBadge`, `rebuildTable` (all `function` definitions verified present pre-merge) — so post-merge gating greps a real name, not "consolidated helpers" vibes. A missing helper is a **regression to report**, never a hand-patch

### S7 — documentation debt is registered, not silently papered over (merge-time)
Post-merge `CodingMAIN.md` **MUST** still show the 3 surviving slot-state `reserved` references (:46, :53, :145 pre-merge; coordinates **may shift** post-merge since hunks H1/H3/H14 sit above them — assert the *texts survive contentually*, not fixed line numbers; post-truth per Batch 3 Round 1: `reserved` should drop **6 → 5** sites because the :251 slot-state row dies with H9's 3-state takeover) — the merge **must not** edit §3/§4, because both sit outside every conflict hunk; the contradiction (DB CHECK + FR source on the other side) is instead recorded as follow-up debt in `design.md` §13, and this spec asserts the *debt registration exists*, not that the doc was "fixed"

## Boundary invariants (negative requirements)
- The merge **must not** physically edit `database/migrations/*` or `dataset/*` (the concurrent session owns them) — asserted by post-merge `git status` (§8 criterion 8)
- The merge **must not** restore the deleted `page-changelogs/upcoming-replacements-ui-changelog.md` — it stays `git rm`-ed, never resurrected by checkout or conflict-marker recovery
- The merge **must not** replace our `$uiPages` loop with upstream's explicit `Route::get` closures — upstream's per-route lines for 5 of ours' existing routes appear in the conflict hunk (merged-tree lines 94–116) and are resolved away; the loop + keyed edits (§4.1) is the only sanctioned resolution
