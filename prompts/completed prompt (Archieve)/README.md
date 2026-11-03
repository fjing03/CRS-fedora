# Completed prompts (archive)

Finished, one-off task prompts live here. **Nothing in this folder is an active
instruction — do not execute anything from it.** It exists so the live `prompts/`
folder only shows work that is still open.

**Nothing arrives here automatically:** every task ends with the agent asking the
user *"do you want me to archive this prompt?"* (rule: `../PI-PROMPT-TEMPLATE.md`
§10), and only a "yes" moves a file in.

## What belongs here

A prompt moves here once its task is **provably finished**:

- an SDD lane of the same name sits in `.sdd/archive/`, or the lane's
  `sdd.yaml` reads `status: applied`; **or**
- the change is committed and recorded in a `page-changelogs/*.md` entry, or the
  prompt names a commit that exists (`git log --oneline`).

If neither is true, the prompt **stays in `prompts/`** — "probably done" is not
a reason to archive.

## What deliberately stayed in `prompts/`

| File | Why it is not here |
|---|---|
| `PI-PROMPT-TEMPLATE.md` | Reusable system-wide template, not a task |
| `sdd-propose-template.md`, `sdd-propose-backend-template.md` | Reusable `/sdd-propose` templates |
| `sdd-propose-ui-page.md` | Auto-referenced by `AGENTS.md` — every new UI page must read it |
| `sdd.md` | Living SDD tracker (index of applied / in-progress / queued) |
| `context-transfer-prompt.md` | Tooling: moves context between sessions |
| `pi-tidy-arrangement-cell-tooltips.md` | Lane `.sdd/changes/rearrangement-toolbar-tooltips` is `status: active` |
| `pi-prompt-UIUX Update Replacement Arrangement Toolbar.md` | Same active lane as above |
| `Claude_Gmail_SMTP_Setup_Prompt.md` | Not provably done: `config('mail.default')` is still `log`, so Gmail SMTP was never enabled in this app |

## Contents (archived 2026-11-02)

| Prompt | Why it counts as finished |
|---|---|
| `run-auth-wiring.md` | `.sdd/archive/2026-10-17-auth-wiring` (+ `2026-08-06-auth-wiring`) |
| `run-logout-modal.md` | `.sdd/archive/2026-08-03-logout-modal` |
| `run-my-request-history-ux.md` | `.sdd/archive/my-request-history-ux-features` |
| `run-occurrence-week-pin-truthfulness.md` | `.sdd/archive/2026-10-23-occurrence-week-pin-truthfulness` |
| `run-replacement-home-ux.md` | `.sdd/archive/2026-08-06-replacement-home-ux-features` |
| `run-skeleton-loading-scroll-restore.md` | `.sdd/archive/2026-08-08-skeleton-loading-scroll-restore` |
| `run-student-my-timetable.md` | `.sdd/archive/student-my-timetable-ui` |
| `run-toast-undo-bar.md` | `.sdd/archive/2026-08-03-toast-undo-bar` |
| `sdd-propose-mobile-responsive.md` | `.sdd/archive/2026-08-03-mobile-responsive` |
| `sdd-apply-venue-page-book-affordance.md` | `.sdd/archive/2026-10-17-venue-page-book-affordance` |
| `sdd-explore-replacement-arrangement-bugs.md` | `.sdd/archive/2026-10-23-replacement-arrangement-bug-hunt` |
| `opencode-prompt-arr-slot-original-venue-sync.md` | `.sdd/archive/2026-10-24-arr-slot-original-venue-sync` |
| `opencode-prompt-week-memory-until-logout.md` | `.sdd/archive/2026-10-23-week-memory-until-logout` |
| `pi-prompt-venue-cross-week-handoff.md` | `.sdd/archive/2026-10-23-venue-cross-week-handoff` |
| `pi-prompt-continue-notif-new-request-ux-apply.md` | Lane `notif-new-request-ux` = `status: applied`; handover §0.6 records it CLOSED |
| `pi-prompt-UIUX Update New Replacement Request.md` | Same closed lane (`notif-new-request-ux`) |
| `pi-prompt-Bug Conflict Schedule and Slot D.md` | Lane `conflict-schedule-header-status-tip` = `status: applied` |
| `pi-bug-arrangement-reentry-after-pending.md` | Entry-guard fix shipped: `62cbbb0` (fix) + `b0685ae` (tests) + `99329be` (changelog) |
| `pi-prompt-notif-type-filter-redesign.md` | `210a232` (notifTypeFilter re-skin) + `f0b7215` (changelog) |
| `pi-prompt-notif-manage-hide.md` | Batch hide + hidden shelf shipped: `467357b` (feat) + `f5cc56d` (ui) + `fc81dd3` (test) + `405b4a3` (changelog); receipts 291/291 PHPUnit + 5/5 and 10/10 Playwright |
| `BUG Investigation — Venue Timetable & Cohort Timetable.md` | The 4 reported bugs + 8 audit bugs fixed and archived: SDD `venue-status-parity` → `.sdd/archive/2026-11-02-venue-status-parity`; commits `f09891a` (venue) / `f12874e` (cohort) / `4a98556` (shared modal) / `9eb2ded` (tests) / `eb05c01` (docs). Receipts: PHPUnit 295/295, NEW read-only `tests/venue-status-parity.spec.ts` 8/8 × 2 |
| `pi-ui-footer-global.md` | Ran the frozen SDD lane `ui-footer-global` → `sdd.yaml status: applied`; commit `fea58ad` (5 code files: mail icon, the partial, both layout includes, the one CSS block) + `page-changelogs/ui-footer-changelog.md`. Gates: pint passed, PHPStan 0, suite 312/312. Live on `:8000`: login strip fixed/legible dark+light, app pages bottom-pinned, 390px wrap with the −12px mirror, print hidden |
| `pi-summary-cards-confirmed-pending.md` | Summary-cards lane shipped: `e1429b1` (feat) + `054c7b9` (test) + `689aa86` (changelogs) + `da0cd82` (handover). Ran in the NORMAL fix lane (SDD waived by the user), so the design record is the three changelog entries. The two open decisions were never answered — both prompt-stated DEFAULTS were applied (Total Slots plain; `X+Y` always, `+0` included) |
| `Investigate Livewire JavaScript.md` | Investigation delivered: `docs/Livewire-JS-Investigation-Report.md` + `docs/livewire-js-investigation.md` (`7107abb`) |
| `pi-prompt-session-handoff-2026-10-24.md` | Superseded by `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` |
| `pi-prompt-too-late-red-cells.md` | Landed in `page-changelogs/venue-timetable-ui-changelog.md` ("too late" read-only cells) |
| `pi-prompt-venue-holiday-grid-overflow.md` | Landed in `page-changelogs/venue-timetable-ui-changelog.md` (holiday row) |
| `Bug Fix: Conflicted Subject Does Not Auto-Select After Changing Subjects.md` | Landed: `tests/arrangement-conflict-autoselect.spec.ts` + the arrangement changelog "auto-select" entries |
| `pi-prompt-Bug: Conflicted Booking Intent Incorrectly Displayed as Selected.md` | Landed: arrangement changelog "booking intent" entries + `venue-intent-banner-kind` |

| `pi-replacement-home-status-badges.md` | Ran the SDD lane `replacement-home-status-badges` → `sdd.yaml status: applied`; commit `a16727c` (feat, 13 files) + `2e58547` (apply/verify round). Nine locked decisions D1–D9 (the 2026-10-11 hide reversed: an arranged occurrence keeps its row; Pending/Approved badge links to `?notif-hl=r<id>`; Rejected/Cancelled muted label above the restored Arrange button; `—` in Days Left on handled rows; handled rows sort last; a holiday keeps "Public Holiday" over the `official_event` stamp) + the My Requests page-jump. Gates: pint passed, PHPStan 0, full suite 315 passed / 1685 assertions, NEW read-only `tests/replacement-home-status-badges.spec.ts` 7/7. Live on `:8000`: two handled rows carry `Pending` + `—` (`r26`/`r27`), handled rows are rows 4–5 of 5, a badge click lands on My Requests with `bookable-flash` at 662 ms, the 390 px card navigates, the quick view reads **View Request** + *Replacement Status*. Honest gap: the page-2 jump needs >10 requests (the demo user has 4) — a named manual path, not claimed |
| `pi-request-completed-status.md` | Ran the SDD lane `request-completed-status` → `sdd.yaml status: applied`; commit `e2b2f4c` (feat, 18 files) + `7d2bb9a` (apply/verify round). Nine locked decisions C1–C9: `approved` + a replacement class DATE strictly before today reads **Completed**, derived at read through ONE helper (`ReplacementRequestMapper::effectiveStatus()`) feeding all four surfaces; the home page gains **Exclude Completed** (checked every load, no storage); the badge links to My Requests, which un-ticks that toggle only when it is the sole hiding cause; the timeline's final step is timed by the replacement date. No column, no migration, no notification, no demo-DB write. Gates: pint passed, PHPStan 0, full suite 320 passed / 1723 assertions, NEW `tests/request-completed-status.spec.ts` 3/3 on the disposable `:8030`, demo read-only specs 22/22 on `:8000`. Honest gaps: viva request 27's replacement date is 2026-11-03 (today) so it reads Approved today and Completed from 4 Nov; the probe inserts 2 extra queries per approved row |
| `pi-notification-recipient-rulings.md` | Ran the SDD lane `notif-recipient-rulings` → `sdd.yaml status: applied`; commits `a28142d` (feat) + `2e3066a` (tests) + `94d14d8` (docs). Five user rulings R1–R5 + the staff subtitle: R1 a non-PL proposer gets one confirmation row (PL fan-out untouched), R2 never email the proposer their own request (skipped **by id**), R3 the owner's `approved` row merged into `replacementConfirmed()` (once, before the cohort loop), R4 a cancellation fans out to owner + cohort students only (reverses the 2026-10-30 three-fan-out ruling), R5 the `Name (staff_id)` line in every state as a computed attribute. Receipts: bridge + email 29/29 (186 assertions), full suite 301/301 at the time, live `:8030` — 5770 rows read `Surayaini Binti Basri (5425)`, a real UI cancel wrote 42 rows (1 owner + 41 students, **0 PL**). No migration |

Note: most `pi-prompt-*.md` files are gitignored (`.gitignore:38`), so some of
these archives are filesystem-only and were never committed.
