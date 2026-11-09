# proposal — `student-class-replacements` (2026-11-09)

**Approval-gated. No code written yet.** Baseline for this proposal is `explore-brief.md` in the same
folder — eight user rulings taken in a grilling session on 2026-11-09, with the volumes measured on the
demo database.

## Why

The student Replacement History answers the wrong question. It lists **requests** — so it can only show
classes somebody has already asked to move. A class the lecturer cancelled an hour ago, or a class that
falls on a public holiday next week, is **invisible until a request exists**, even though the student's
own timetable says the class is gone.

The staff page answers the better question — *which of my classes are conflicted, and where does each one
stand?* — and the user's brief asks for the student page to work the same way, read-only.

Measured on `class_replacement_fresh` (all 14 weeks, the student's own cohort):

| Cohort | Sessions | Conflicted occurrences (cancelled class **or** holiday) | of which have a request |
|---|---|---|---|
| DFT2(S1)G1 (`24DFT0001`) | 11 | 6 | 1 |
| DSF2(S1)G1 | 18 | 11 | 8 |
| RSD2(S1)G2 (`24RSD0009`) | 16 | 11 | 2 |

Most of those rows today do not exist on the page at all. The rest of this proposal says what replaces it.

## What changes (behaviour, not implementation)

1. **A row becomes a conflicted class occurrence**, not a request: one row per (session × week) whose
   occurrence has a `class_exceptions` row **or** lands on a calendar holiday. Request or no request.
2. **Unarranged classes get their own words.** No request, a cancelled request and a rejected request all
   read **`Awaiting Arrangement`**. Arranged rows keep the staff labels: `Pending — Waiting for
   Approval`, `Approved`, `Completed — 1 day ago`. The rejection or cancellation itself is never shown.
3. **The week filter follows the class's own week**, and its All-option reads **`All Original Class
   Weeks`** (today's `All Requested Slots Weeks` was right for the request-based page and is ambiguous
   now: an arranged row's request can sit in another week entirely).
4. **The one toggle stays the staff control** — `Exclude COMPLETED Status`, on by default — and
   "completed" is decided by the **make-up date only**, exactly as on the staff page. A class with no
   make-up therefore never counts as completed and stays listed for the term; this consequence was put to
   the user and accepted.
5. **The page is renamed.** Nav label **Class Replacements**, address **`/student-class-replacements`**.
   The old address `/replacement-history-ui` keeps pointing at the same page (house practice: several
   addresses, no redirect, query strings survive); nothing stored links to it (checked).
6. **Read-only means read-only.** No arrange, no cancel, no request management of any kind, and no action
   column. Rows open the detail pop-up as today, and the pop-up gains **Go to My Timetable**, which jumps
   to the student timetable **and flashes the block** — the staff behaviour, measured working today.
7. **The summary strip is five cards:** `Awaiting Arrangement` · `Pending` · `Approved` · `Completed` ·
   `Class Hours Affected`. `Total` is dropped; `Hours` is renamed because it was ambiguous.
8. **`Class Hours Affected` counts the class hours of every visible row**, arranged or not — "how much of
   my teaching was hit", never "hours I will attend".
9. **`Approved` is a new card, and today's `Upcoming` predicate dies with it.** `Upcoming` counted
   approved rows whose week was still ahead; the new card counts every approved row in the visible set, so
   the old `r.status === 'approved' && week >= current` shape must not survive as a leftover.

## The contract (what must stay true)

| Concern | Rule |
|---|---|
| Who sees what | a student sees only occurrences of **their own cohort's** sessions |
| Writes | **none** — no PHP action, no POST, no state change is added by this page |
| The conflict wording | stays the student-guarded one (2026-11-09, Q1=B): a holiday keeps its real name (`Public Holiday — Deepavali Holiday (In Lieu)`), every other exception reads `Class Cancelled`; a leave type never reaches a student |
| The requested slot | the **latest request per (session, week)** — never the first, never a mix. A cohort can hold several requests against one occurrence in mixed states (the demo RSD2 row has an approved and a cancelled one); the newest by `submitted_at`, id as tie-break |
| The requested slot's shape | the junction span, with the anchor fallback (the junction is deleted on cancel/reject) |
| Empty requested slot | an unarranged row prints an em dash; the words "Awaiting PL approval" disappear from the page |
| Existing behaviours kept | row click → pop-up, sortable headers, week arrows/Today, persisted view state, mobile cards, the empty state, `role=button` + keyboard activation |
| The staff pages | untouched, except that the shared jump helper must serve two pages |
| Default order | class date ascending — a tracker reads chronologically; the sortable headers keep working as today |

## In scope

- `app/Livewire/ReplacementHistory.php` — the data bridge: conflicted occurrences instead of requests.
- `resources/views/livewire/replacement-history.blade.php` — columns, statuses, filters, cards, pop-up.
- `routes/web.php` + `resources/views/partials/ui-nav-bar.blade.php` — the new address and label.
- `public/js/ui-common.js` — `goToReplacementInTimetable()` gains a page-aware target; the arrival arm
  `applyReplacementHighlight()` accepts the student timetable (one line today).
- `app/Services/ReplacementRequestMapper.php` — **only if** the design puts the student status wording
  there (open question 3 in the brief).
- Tests: the page's payload/source invariants, the nav/route tests that name the old URL, and one
  browser receipt for the read-only + jump behaviours.
- The page's changelog entry.

## Explicitly out of scope

- Any student write action (arrange, cancel, reject, approve) — read-only by ruling.
- The staff My Replacements / Request Approval pages, their payload and their wording.
- The notification system and its deep links (students' links already land on My Timetable).
- The timetable grid, its blocks and its tooltips (only the *arrival* arm changes).
- Cohort/Request-ID columns, search, status filter, rows-per-page, result count and `Reset Filters` on the
  student page (parked by the 2026-11-09 Q11=A ruling).
- The three stale-sentence findings of the absorbed `student-history-copy-polish` lane — its first two die
  with the rewrite; its third (the `Completed` card reading 0) is open question 1 of the brief and must be
  settled in the design, not silently.

## Risks and how they are handled

1. **The page becomes mostly holidays.** Six to eleven rows per student, most of them `Public Holiday`.
   That is the ruling (R1) and the point of the page — but the empty state, the default filter and the
   cards must make the list legible, not alarming.
2. **An unarranged class never becomes "completed".** Accepted consequence of R4; the toggle still hides
   every arranged past row, so the default view shrinks over the term rather than growing.
3. **Two vocabularies for one state.** `Awaiting Arrangement` (student) vs `Need to Arrange` (staff). The
   design must keep them in ONE place so they cannot drift, and must not change the staff words.
4. **A route rename touching tests.** Five files name the old URL (`routes/web.php`, `ui-nav-bar.blade.php`,
   `NavIdentityTest.php`, `RouteGateMatrixTest.php`, `nav-identity.spec.ts`) plus the component's
   `activeNav` key; the alias entry keeps behaviour while the tests move to the new address.
5. **Deriving occurrences is more expensive than listing requests.** One query per input (sessions,
   exceptions, holidays, requests, spans, venues) — no per-row queries; the design must keep that shape.

## Acceptance criteria (the verification target)

1. A student's page lists **every** conflicted occurrence of their cohort: holiday-hit classes with no
   request included.
2. An unarranged occurrence reads `Awaiting Arrangement`; no row on the page ever reads `Need to Arrange`,
   `Cancelled by YOU`, `Rejected`, or `Awaiting PL approval`.
3. An occurrence with a live request reads the staff label for its derived status, and the REQUESTED SLOT
   column prints the real make-up slot; an unarranged occurrence prints an em dash there.
4. The week filter narrows on the **class's** week, and its All-option reads `All Original Class Weeks`.
5. `Exclude COMPLETED Status` hides exactly the rows whose make-up date has passed; unarranged rows are
   never hidden by it.
6. The page answers at `/student-class-replacements`; `/replacement-history-ui` still renders the same
   page; the nav says "Class Replacements".
7. Nothing on the page can change data; the pop-up's Go to My Timetable lands on the student timetable with
   the replacement block flashing, and the button is absent on a row that has no requested slot.
8. The five cards read `Awaiting Arrangement`, `Pending`, `Approved`, `Completed`, `Class Hours Affected`,
   each counting the visible set (hours from class durations per R8).
