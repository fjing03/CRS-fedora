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
| `pi-summary-cards-confirmed-pending.md` | Summary-cards lane shipped: `e1429b1` (feat) + `054c7b9` (test) + `689aa86` (changelogs) + `da0cd82` (handover). Ran in the NORMAL fix lane (SDD waived by the user), so the design record is the three changelog entries. The two open decisions were never answered — both prompt-stated DEFAULTS were applied (Total Slots plain; `X+Y` always, `+0` included) |
| `Investigate Livewire JavaScript.md` | Investigation delivered: `docs/Livewire-JS-Investigation-Report.md` + `docs/livewire-js-investigation.md` (`7107abb`) |
| `pi-prompt-session-handoff-2026-10-24.md` | Superseded by `prompts/for-ai-to-read/PI_Chat_Context_Handover.md` |
| `pi-prompt-too-late-red-cells.md` | Landed in `page-changelogs/venue-timetable-ui-changelog.md` ("too late" read-only cells) |
| `pi-prompt-venue-holiday-grid-overflow.md` | Landed in `page-changelogs/venue-timetable-ui-changelog.md` (holiday row) |
| `Bug Fix: Conflicted Subject Does Not Auto-Select After Changing Subjects.md` | Landed: `tests/arrangement-conflict-autoselect.spec.ts` + the arrangement changelog "auto-select" entries |
| `pi-prompt-Bug: Conflicted Booking Intent Incorrectly Displayed as Selected.md` | Landed: arrangement changelog "booking intent" entries + `venue-intent-banner-kind` |

Note: most `pi-prompt-*.md` files are gitignored (`.gitignore:38`), so some of
these archives are filesystem-only and were never committed.
