# proposal — `selection-budget-original-duration` (user law 2026-10-24, verified live)

## Why

The arrangement page's selection budget boots `MAX_SELECTION = 4` and the
commit clamp only ever RAISES it (`MAX_SELECTION = max(MAX_SELECTION,
BLOCK_SPAN)`). On the pinned 1-hour conflict (AMSE2003 T W14, 11:00–12:00),
the user could pick TWO different 1-hour slots in two weeks (2+2 = 4 = the
stale budget) — the live report today, screenshot-backed. The 2-hour L
case only rejects because its own span EQUALS the stale 4. The budget must
be **flexible to the Original Conflicted Class duration** (the user's
verbatim law).

## The law

`MAX_SELECTION` = **the pinned occurrence's own span** (the Original
Class's duration in half-hour slots), dynamically, at every
occurrence-grain commit:

- 1-hour class → `MAX_SELECTION = 2` (one 1-hour slot total);
- 2-hour class → `MAX_SELECTION = 4` (one 2-hour slot total);
- picking a second slot in another week → the existing budget check
  rejects with the toast (the wording updated: the one block is the
  budget — "clear your selection first");
- the summary counter reads the SAME dynamic budget
  ("0 of 2 slots" / "0 of 4 slots").

## 🔴 Resolutions (the verify-round findings, folded pre-freeze)

- **R1 — the F-8 exemption narrows to the BLOCK WIDTH ONLY.** The prior
  law ("URL keeps the INITIAL authority": `urlSpanLocked && opts.auto`
  commits skip the clamp) preserves ONLY `BLOCK_SPAN` (the block width —
  a deliberate handoff size). The **BUDGET flex (`MAX_SELECTION`) applies
  to EVERY commit — including `urlSpanLocked && opts.auto` ones** —
  because the budget quantity = "how much ONE block may hold", which is
  the occurrence's own duration regardless of who set the width. Without
  this the venue-timetable handoff path keeps the stale 4 (the flagged
  incident's main route).
- **R2 — ALL THREE `MAX_SELECTION` write sites route:**
  (a) the boot URL branch (~:3665) — budget = the URL span;
  (b) the pinned-session hydration (~:2624) — budget = the occurrence
  span;
  (c) the commit clamp (~:3380) — budget = the committed slot's span
  (the min/max 1..4 family unchanged). The plain landing keeps the boot
  default 4/4 until a first commit (the counter's "0 of 4" → "0 of N" on
  the first commit — declarative behavior note).
- **R3 — the superseded decision NAMED:** the ~:3662 comment records
  "the multi-week BUDGET stays at least the approved 4 slots so a shorter
  class can still book twice" — that 4-slot-floor decision is
  **SUPERSEDED by today's law** (the user's verbatim "limit to one
  ...slot based on Original Conflicted Class time"); the comment rewrites
  to record the retirement. **The existing-test reds BY DESIGN:**
  `tests/confirm-guards.spec.ts` rows asserting `#infoTotal` "4 of 4
  slots" (3 places) re-point to the occurrence-grain strings per their
  rows' own contexts; no other spec's assertions shift — **amended (verify round 2): the
  `ui-regression.spec.ts` TC-4.1/4.2/4.1 rows are IN the blast radius too**
  (their `#infoTotal` asserts read the plain landing's first-committed
  occurrence; each row re-points per its own anchor occurrence's span, or
  the row's subject index 1 first-slot span is verified = 4 during the
  apply — the receipts rule decides, never assumed). Also the
  MANUAL-TEST-CASES.md arrangement row re-points its "2 of 4" wording to
  the dynamic budget (the doc honesty rider).

## 🟡 resolutions (the reviewer's should-fixes)

- The counter's first dynamic moment: per R2's site list — the URL
  arrivals show at boot; the pinned/manifest at the commit.
- The test's counter strings PINNED: after a 1-hour pick = "2 of 2
  slots"; after a 2-hour pick = "4 of 4 slots" (0 remaining).
- Partial-span occurrences follow naturally (a 30-min class → budget 1;
  a 4-slot-capped class → 4) — no "1h/2h only" special-casing.
- The F-8 comment's "≤8 slots" drift + the retirement note fold together.

## Explicitly preserved (no regression)

- **The URL `&duration=` arrivals keep their BLOCK-WIDTH authority**
  (the F-8 law narrows per R1: the width stays; the budget flexes).
- The boot default `MAX_SELECTION = 4` / `BLOCK_SPAN = 4` stays for the
  no-ctx plain landings.
- No copy changes beyond the one toast anchoring clause ("another week's"
  → "your selection" — the budget is now occurrence-grain, the old text
  reads wrong).
- The multi-week selection mechanics, the merge/undo/replay machinery —
  untouched.

## Impact scope

- `resources/views/livewire/replacement-arrangement.blade.php` — the
  commit clamp (MAX_SELECTION flex) + the toast anchoring wording;
- `tests/selection-budget-original-duration.spec.ts` (NEW): the T row
  (pick 1h in W6, pick in W7 → rejected, the summary keeps ONE selection)
  + the L regression guard (pick 2h, pick W6 → rejected with the 4-slot
  toast);
- `page-changelogs/replacement-arrangement-changelog.md` (dated entry);
- the amended blast files: `tests/confirm-guards.spec.ts`,
  `tests/ui-regression.spec.ts` (TC-4.x), `tests/MANUAL-TEST-CASES.md`;
- `tests/selection-budget-original-duration.spec.ts` (the freeze note:
  the R2(a)×cap clause covers TC-1.4 per the reviewer; the post-clear
  counter sentence rides in design).

## Acceptance

The lane's spec rows green ×2; the standing gates (pint/phpstan-1G/
phpunit 258; the spot battery `--grep-invert "TC70"`); no other test
red-shifts (the pick-family specs excluding the documented reds).
