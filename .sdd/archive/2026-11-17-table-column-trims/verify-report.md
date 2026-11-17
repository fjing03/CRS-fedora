# Verify — `table-column-trims`

Verified 2026-11-17 against `spec-and-tasks.md` (frozen, round 1), `proposal.md` and `design.md`.
**Verdict: PASS**, with the attribution of ten unrelated red receipts recorded below.

## Requirements

| # | requirement | verdict | evidence |
|---|---|---|---|
| TR-01 | Request Approval's Staff cell is the name alone, wrapper kept, `else` branch untouched | **PASS** | live: `Lim Jia Zheng` / `Surayaini Binti Basri`, `600` weight, `13px`, no `(5425)`, no email line; `lecturer-cell-name` present, `lecturer-cell-id`/`-email` rules still in `<style>` |
| TR-02 | Request Approval's Course cell is `CODE (TYPE)` on one line, prefix preserved, no `cell-name`, no tip | **PASS** | live `AMCS2093 (L)`; `CourseColumnTipTest` 4/4 (17 assertions) including the prefix regex |
| TR-03 | My Replacements' merged Course cell rewritten; `html:` before `cls:`; leading space; legacy body byte-identical | **PASS** | live `BMIT2013 (L)`; `cell-name` count 0; legacy body still at `:4137`; the `cls: 'col-code'` regex green |
| TR-04 | Student `courseCell()` matches, guard included, comment rewritten | **PASS** | live `BMIT2013 (L)`, 10 of 10 rows labelled; the comment now describes the cell that exists |
| TR-05 | One wording law, no `()` on a missing type | **PASS** | guard present in all three; `(P)`, `(L)`, `(T)` read from the live DOM |
| TR-06 | No new class, no `theme.css` line | **PASS** | `git show --stat 579bc1d` — no `theme.css`, no new class name |
| TR-07 | Pop-up unchanged: name, `(staffId)`, copy-email chip, subtitle `CODE — Name (Type)` | **PASS** | live: strip `Staff (Proposer) — Lim Jia Zheng`; Class Info holds `(5425)` + chip; subtitle `AMSE2003 — … (Lecture)` |
| TR-08 | Pinned tests verified, never weakened | **PASS** | `CourseColumnTipTest` untouched and green; two *other* specs re-pointed (below) |
| TR-09 | The look-alike cards keep the ID by decision | **PASS** | `approvalCard` (`:620`) still builds `lecturer-cell-id`, untouched |
| TR-10 | Out of scope stays out | **PASS** | `git show --stat` — three product files, no widths, no backend, no `replacement-home.blade.php` |

## Scenarios

S1, S2, S3, S4, S5, S6, S7, S8, S10 — **PASS**, measured live at 1024 / 1280 / 1600 in both themes
(details in `apply-log.md`). S9 — **PASS as an observation**: Staff 188 → 146px, Course 250 → 118px,
table 1451 → 1277px; row height unchanged at 122px because the slot columns set it.

## The four DOM specs, before and after

**12 failed / 27 passed at HEAD and 12 failed / 27 passed with the trim — the same twelve names.**
Two of them were repaired because they were red for the wrong reason:

- `type-labels.spec.ts` — stale `.btn-close-modal` selector, red since `6856577`; **4/4 now**.
- `replacement-home-status-badges.spec.ts:149` — task 10's shortened tab label; **12/12 now**.

## Red receipts, attributed (not this lane)

`ui-regression` TC-3.x ×3 (timetable pop-up says `Class Information` where the test wants
`Subject Code`) · TC-4.1–4.5 (arrangement slot count) · TC-7.2 (summary-card scope) ·
`request-cancel-pending:66` (same slot count). Identical before and after this lane.

## Known limits

- The PL queue has no pending rows, so the trim was measured on handled rows only.
- `typeLabel` is now unused by the merged branch but must stay (the legacy body reads it).
- `request-approval.spec.ts`'s six data-blocked cases remain red; unchanged by this lane.
