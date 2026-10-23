# Review log — week-memory-until-logout

## Batch 1 (proposal + design + tasks, condensed single round) — Round 1 — 2026-10-20

Reviewer: pi subprocess (glm-5.3-flash; full output in session).
Verdict: "Not ready for /sdd-apply — three blockers; none invalidate the
core mechanism".

### Done-marked-blockers (fixed)
1. **The venue's "saves but never loads" claim was false** — the page's
   `weekNav.load()` exists at :766 (wired since the 2026-10-09 venue
   SDD); the round-0 brief missed it (a read-slice miss). Fixed across
   the brief/proposal/design/T4: the venue = NO new wiring, the :766
   load simply becomes stamp-scoped via the mechanism.
2. **The cohort's "neither saves nor loads" was half false** — its
   prev/next/select handlers (:399/:405/:411) call the CLASS methods,
   which already save (ui-common :353/:367/:384); the only gap = its
   first `load()`. Fixed: the gap table, design D3, and T5 (the
   placement BEFORE the first paint + the WHY).
3. **D5's bare add-init sessionStorage.clear() would boobytrap the
   centerpiece row** — add-init runs at every document start; row 2
   would see TODAY at every hop. Fixed: the MARKER-GUARDED once-per-tab
   add-init (init at the first document, the trip unguarded) + the
   three sessionStorage facts recorded (survives reloads / not carried
   by storageState / own-context-per-tab; never hop via a second tab).

### Addressed-(should-fix)
4. The register-auto-login path mints no stamp: the save() side is now
   gated SYMMETRICALLY on a non-empty stamp (a today-only session,
   honest both ways); "not-logged-in edge" replaced (all 4 pages
   auth+role-gated; the register path = the only reachable
   empty-stamp case).
5. ui-regression.spec TC-2.x's venue row (a localStorage slot assert)
   newly breaks: the design commits to UPDATING that row's expectation
   to the session key; its other three stale rows (keys that never
   existed) = documented pre-existing staleness, left + reported.
6. D6 resolved from the seeds: the demo student identity = the seed
   invariant (25RSD0001; TimetableSeedInvariantsTest T-6 + the login
   placeholder; passwords are not pasted into artifacts) and the
   student's role_lifetime = 30 days; staff get 403 on the student
   route, so a live student row needs an in-spec student login (option
   B) WITHOUT touching auth.setup; option A (the class-level receipt)
   stays the default; both presented at apply.
7. T8 commits to the student changelog entry (the file exists) and pins
   the LIVE cohort changelog (cohort-timetable-changelog.md — verified:
   its head carries today's last-visit-dd entry).
8. D5 row 1 asserts the weekSelector (never window.currentWeek — a
   documented deliberate desync; the implementer instructed not to fix
   it) + the cohort row pre-selects the faculty+cohort pair (the
   TC-2.x pattern) before asserting.
9. T3 spells the exact 6-arg call shape for my/student (three null
   placeholders; the derived key 'weekNav-weekSelect' preserved; their
   per-page invariant rides on role gating).

### Outstanding
- None — the named surfaces are fixed as the verdict's own condition
  ("then this batch can pass"); frozen for apply.

## User decision — 2026-10-20 (the T9 student row)

The user chose the in-spec dance (not the class-only receipt): the spec
logs out from the staff session via the REAL app path, logs in as the
seed student (`25RSD0001`, the credential read from the seed test — not
pasted into artifacts) and walks her page through the law (today →
save → resume), proving the until-logout contract CROSS-LOGOUT in one
live walk (a fresh stamp makes the staff session's leftover keys match
nothing). D6 rewritten; T9 closed; no auth.setup change; no DB writes.
# Review log additions (week-memory-until-logout)

## Apply-era receipts (the live apply round) - 2026-10-20

Round: the apply implementation (T1-T5 edits + the spec) with the live
receipts (not a review round - the apply's own evidence).

### Fixed during apply (findings folded)
1. **The M5 row's instrument**: the cohort page's OWN deliberate
   contract = "a cohort switch re-lands TODAY" (the onCohortChange
   tail's re-anchor quirk, the last-visit-dd D3 family). Receipts: the
   staged boot dump (fresh 4/4, pair-pin 4/4, nextWeek sel=5 + the
   stamp-key saved 5) - the mechanism correct end-to-end; the revisit =
   today = the page's OWN receipt, not a bug. M5 rewritten receipted.
2. **The M4 dance re-scoped to 4363** (not the storageState's 5770):
   Laravel's login regenerate() tears down the requesting context's old
   session row; the 5770 storageState session was dying when the dance
   logged in as the same user. A DIFFERENT staff login (4363) keeps the
   shared session alive; the leftover-key family = strictly per-user.
3. **The M5's remaining MockData-undefined receipt**: the venue-page
   goto = the Student Login page rendered - the M4 dance's student
   login (25RSD0001) = the LAST login mint = the LoginResponse redirects
   the students to their page; the main context's next venue goto hits
   the guest redirect (the student session's login_type cookie queued
   43200 min). Evidence chain: seg-B (the staff login alone) alive;
   seg-C (the student login alone) alive; seg-D (the staff
   login+logout) alive; seg-E (the full dance) alive; seg-F (the
   replica with the main-context's new page after the dance) = the
   Student Login.
   Root cause: the login_type COOKIE queued at the student login
   (cookie()->queue('login_type','student',43200)) flips the app's
   login-page family, and the queued-cookie timing keeps the main
   context's session cookie re-side-effects. The landing = still staff
   members' pages only after the dance - the next steps re-run
   auth.setup per the battery's standard (the re-auth receipt).

# Review log additions (week-memory-until-logout) — OpenCode resumption

## The M5 corner (Step 2) — 2026-10-23 17:45 — MYSTERY SOLVED (root cause =
   the checklist's (e); the whole decode chain from the apply-era above is
   superseded by today's receipts)

The user STOP order paused the apply mid-gates; OpenCode resumed from
`prompts/opencode-prompt-week-memory-until-logout.md` (read in full +
all 5 frozen artifacts + the working tree diff). Then:

### The decisive experiment pair
1. **Standalone replica (probe):** the EXACT M5 dance as a cjs from a
   plain chromium launch — **GREEN end-to-end in ~11 s** (4363 login →
   nextWeek save → real logout → /login/staff within 1 s → 25RSD0001
   login → her page sel=4/served=4 OK → prevWeek = 3 OK, weekmem +1).
   Two checklist items closed by its receipts: **(a)** the
   `cookie: NONE` request headers = a Playwright header-capture
   ARTIFACT ONLY (logins/sessions demonstrably worked while the capture
   said NONE — page-initiated navs omit cookie headers in
   `request.headers()`); **(c)** `.logout-btn` count = **1** (the
   drawer's logout carries `.nav-drawer-logout`; pi's two-clone note
   was stale markup memory).
2. **The runner repro:** running the actual spec → M5 ✘ repro'd, but
   the failing wait had MOVED: now the FIRST login's
   `waitForURL(/my-timetable/)` (:145) — and the error snapshot =
   `/dashboard` rendering **"Welcome En. Lim Jia Zheng, Lecturer
   (5770)"**.

### The root cause (checklist (e) CONFIRMED by experiment)
`test.use({ storageState: '.auth/staff-5770.json' })` at FILE level +
`browser.newContext()` inside the M5 test ⇒ **the "bare" dance context
INHERITS the test's storageState** (a debug spec printed the fresh
context's cookies: `login_type,XSRF-TOKEN,laravel-session` at
CREATION, and goto /my-timetable-ui rendered AS 5770 without login).
So the dance was never bare: the 4363 credentials were submitted as a
**double-login over a live 5770 session**; the app resolves that to
LoginResponse's Auth::user()=null branch → `redirect()->intended` →
'/dashboard' as the OLD (5770) identity — the exact hang snapshot
(pi's logout-era 5770 receipt = the same bleed, era-mixed evidence).
A context booted WITH the 5770 storageState by hand reproduced the
runner failure verbatim (`RES 302 /login → RES 200 /dashboard →
Welcome (5770)`).

### The fix (one line + honest receipts)
`const bare = await browser.newContext({ storageState: { cookies: [],
origins: [] } });` — an explicit EMPTY storageState overrides the
inherited file-level one (verified: cookies `(none)` at creation, and
the guest redirect FR-4.14-bounces on /my-timetable-ui). The dance's
flow/contract stayed frozen-shape otherwise; the stale two-clone
comment corrected to the single-button reality. **Full-file rerun:
8/8 passed (setup 3/3 + M1–M5 all ✓, M5 = 11.1 s).**

### Which checklist candidates died of evidence
- (b) CSRF staleness: dead — each goto renders a fresh form+token; and
  the probe walk posts succeeded.
- (d) Fortify limiter: dead — the dance's logins never hit 5/min/identity
  (also verified live: the whole dance logged in/out freely).
- (a) cookie-capture artifact: real but INERT (a red herring for the
  failure itself).
- (c) two-button ambiguity: dead (count = 1).
- (e) storageState bleed: TRUE ROOT CAUSE.
