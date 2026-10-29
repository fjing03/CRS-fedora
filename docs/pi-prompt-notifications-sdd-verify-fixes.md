# PI Prompt — notifications-real-bridge: re-verify the SDD findings, fix what you agree on

**Date:** 2026-10-29
**Change:** `.sdd/changes/notifications-real-bridge/` (status: frozen, apply done 2026-10-28)
**From:** the main session (an independent `/sdd-verify` run found defects; the USER has
selected 5 of them for fixing)
**Your role:** re-verify each item INDEPENDENTLY, then fix ONLY the items you AGREE with.

## The protocol (per item — read carefully)

For EACH of the 5 items below:

1. **Re-check it yourself** — read the SDD artifacts (`proposal.md`, `design.md`,
   `tasks.md`, `review-log.md`) AND the actual code. Do not take my word for it.
2. **If you AGREE** the defect is real: fix it, add/adjust tests, run the gates,
   commit (conventional, one commit per concern).
3. **If you DISAGREE**: change NOTHING for that item — not one byte — and report back
   exactly WHY (with file + line evidence).
4. Partial agreement is fine: agree on 3 of 5 → fix those 3, leave the other 2
   untouched with your reasoning.

**Final report format** (back to the user, in the conversation):

```
Item 1  — whenVisible:      AGREE — fixed (commit <hash>)   |  DISAGREE — <why>
Item 2  — Playwright:       AGREE — fixed (commit <hash>)   |  DISAGREE — <why>
Item 3  — feature tests:    AGREE — fixed (commit <hash>)   |  DISAGREE — <why>
Item 4  — read-all route:   AGREE — fixed (commit <hash>)   |  DISAGREE — <why>
Item 5  — T6 demo rows:     AGREE — fixed (commit <hash>)   |  DISAGREE — <why>
Item 6  — notif-row partial:      AGREE — fixed | DISAGREE — <why>
Item 7  — D4 "missing" case:      AGREE — fixed | DISAGREE — <why>
Item 8  — emitter API naming:     AGREE — fixed | DISAGREE — <why>
Item 9  — stale JS header:        AGREE — fixed | DISAGREE — <why>
Item 10 — full suite baseline:    CONFIRMED (<n>/<n> green) | FAILED (<details>)
Gates: pint <..> / phpstan <..> / php artisan test <..> / playwright <..>
Commits made: <list>
```

---

## The 5 major items (the user's selection)

### Item 1 — `wire:poll.10s ->whenVisible()` is missing on BOTH mounts

**Evidence (my finding — re-verify):** `grep -rn "whenVisible" resources/views/ public/js/
app/Livewire/NavNotifications.php` → zero hits. The island root is
`resources/views/livewire/nav-notifications.blade.php:12`
(`<div {{ $attributes->merge(['wire:poll.10s' => true]) }}>`) and the two mounts pass no
modifier: `partials/ui-nav-bar.blade.php:55` (bell) and
`partials/ui-notifications-panel.blade.php:10` (panel).

**Why it matters:** design D2, tasks T2, and the proposal's own risk line all require
`->whenVisible()` "so background tabs idle". Without it every open tab polls the server
every 10 s forever.

**Agreed fix:** apply `wire:poll.10s->whenVisible()` on both mounts (the cleanest way is
the attribute key on the island root merge — but note `whenVisible` needs a visible
element to observe; the bell island and the panel island each need their own root
visible-check to make sense — a hidden panel must not starve the poll of the PANEL island
while the BELL island keeps polling. Think it through: the panel island lives inside
`.notif-panel` which is `display:none` until opened. `whenVisible` on a permanently
hidden element = the panel island never polls. Verify Livewire's behavior and pick the
law that keeps the DESIGN INTENT (idle when the tab is backgrounded) without breaking the
panel's first-open freshness. If after checking you conclude `whenVisible` cannot satisfy
both on this markup — that is grounds to DISAGREE on this item; explain it.)

### Item 2 — T4's Playwright receipts do not exist

**Evidence:** no file under `tests/*.spec.ts` references `notifPanel`, `notif-row`,
`notif-bell`, `notifications/read` or the island. The review-log's "Applied receipt"
section is a live MANUAL browser demo, not durable spec code.

**Why it matters:** design D7 + tasks T4 explicitly list these receipts: badge updates
within a poll cycle WITHOUT reload; the frozen visual diff = zero on the panel; the
disabled (inactive) row's tooltip text; the cross-page AD-2 tabs; and — a round-2 review
requirement — an OPEN panel survives a poll morph (open state, list scrollTop, toggle
preserved).

**Agreed fix:** write the receipts as a real spec file (house style: `tests/*.spec.ts`,
Playwright, login as staff **5770 / `Turumt@2026`**, server on **:8000** with fresh
views). Constraints:
- The demo DB (`class_replacement_fresh`) is READ-ONLY for you: no INSERT/UPDATE from a
  spec or tinker without the USER's explicit approval in advance. The two existing
  receipt rows (`title LIKE 'Demo receipt — poll cycle%'`, user 5425 — see
  `docs/demo-db-data-changes.md:213` and review-log) may be REUSED read-only as the
  unread-row fixtures. If a receipt genuinely needs a new DB row, STOP and ask the user.
- Keep each receipt tolerant of the shared live DB (assert on relative state, not
  absolute counts where another session could drift them).

### Item 3 — Feature-test gaps

**Evidence:** `tests/Feature/NotificationsBridgeTest.php` has 7 tests. Missing versus D3/D7:
- **the rejected-event emitter test** (only submitted/approve/cancel/reminder are covered);
- **the mark-read endpoint tests** (`POST /notifications/read` idempotency + auth scope —
  it must never mark ANOTHER user's row);
- **the batch-resolve receipt** (the island renders with exactly ONE `whereIn` per render —
  the D4 round-2 N+1 law; assert query counts or a DB::listen capture).

**Agreed fix:** add those tests to the existing feature test (or a sibling file), covering
exactly what D3/D7 name — no speculative extras. If Item 4 lands (route deleted), the
mark-all test targets the island's `markAll` action instead of the endpoint.

### Item 4 — `/notifications/read-all` is dead code (USER RULING: Option A)

**Evidence:** `routes/web.php:110` + `NotificationReadController::readAll()` exist but
nothing calls them; the button uses `wire:click="markAll"`
(`nav-notifications.blade.php:50`). The artifacts contradict: design **D2** says mark-all
is an island action; design **D3** says "mark-all = POST /read-all".

**THE USER'S RULING (2026-10-29, final — do not re-litigate):** **Option A** — keep the
island action `wire:click="markAll"` (instant badge/list zeroing; best demo UX), and
**DELETE the unused `POST /notifications/read-all` route + the `readAll()` controller
method**. Resolve the D2/D3 contradiction in favor of D2. The row-click durable
`POST /notifications/read` path stays EXACTLY as is (D3's real law — navigation is
in-flight there, mark-all has no such race).

**Agreed fix:** confirm nothing references the route (grep JS/Blade/tests/docs), then
delete route + method; adjust any test/doc that named it. If you find a REAL consumer of
the endpoint that I missed → DISAGREE with that evidence instead.

### Item 5 — T6: the demo seed rows were never drafted

**Evidence:** `docs/demo-db-data-changes.md` only logs the 2 agent-run poll-receipt rows
(:213). T6 and D8 ("receipts strategy B + one C") require DRAFTED INSERTs so the bell is
visibly alive in the demo: a `submitted` row for a PL, an `approved` row for **5425**,
and an `awaiting` reminder row.

**Agreed fix:** draft the INSERT SQL (correct schema: `user_id, role_view, type, title,
body, link, request_id, class_session_id, week, read_at, timestamps`; frozen tile keys;
per-role link law from D1) into `docs/demo-db-data-changes.md` in the file's existing
convention, clearly marked **USER-RUN — never agent-run**. **Do NOT execute them.** Any
request_id you reference must be a REAL existing id (read-only query to confirm) or NULL.

---

## The 5 minor items (the user's selection — same agree/disagree protocol)

These are the 🟡/💡 findings from the same verify pass. They are SMALLER than the
majors, but the user has asked you to re-check and (if you agree) fix them too. Items 7
and 8 in particular may legitimately end in DISAGREE — they are judgment calls, and
"it's cosmetic / the sketch was illustrative" is a valid, passing answer.

### Item 6 — `partials/notif-row.blade.php` was never created (T2/D6 named it)

**Evidence:** design D6 + tasks T2 both name `partials/notif-row.blade.php` as the home
for the row markup + the glyph + the rel/abs time. In reality the markup is INLINE in
`resources/views/livewire/nav-notifications.blade.php` (roughly :71–:88), duplicated
between the active-anchor and the inactive-span branch. The OUTCOME the design wanted
(server-rendered row, JS copies retired) is fully met — this is a structural deviation,
not a behavior bug.

**Agreed fix (if you agree):** extract the row into the named partial, parameterized so
the active/inactive branch is one block (not two near-copies), and keep the rendered
HTML byte-for-byte identical (the frozen-classes law + the Playwright visual receipt
from Item 2 must still pass). If you judge the extraction riskier than the duplication
it removes, DISAGREE with that reasoning instead.

### Item 7 — D4's "request missing OR cancelled" vs. the implementation's cancelled-only

**Evidence:** design D4 says a row is disabled when "The request missing OR
`status = 'cancelled'`". `NavNotifications::resolveRequests()` (:121–124) only sets
`inactive_row` when the status is exactly `'cancelled'`; a MISSING request id falls
through the `->get(..., 'missing')` default and stays ACTIVE. In practice the case is
unreachable — the FK is `nullOnDelete`, so a deleted request nulls `request_id` and the
row loses its link target anyway. Cosmetic.

**Agreed fix (if you agree):** treat a missing status as inactive too (one line) so code
matches the written law, with a feature test for the missing case. **DISAGREE is equally
acceptable** if you judge the dead branch worse than the doc mismatch — in that case say
so and the design's D4 wording gets a note instead (the MAIN session will handle the doc
side; you touch no SDD artifact content beyond the checkbox/log bookkeeping).

### Item 8 — Emitter API is event-named vs. the design sketch's `send()/notifyPls()`

**Evidence:** design D1 sketches `send()` + `notifyPls()` + `notifyCohorts()`; the real
`NotificationEmitter` uses `requestSubmitted/requestDecided/approvalNotice/classCancelled/
pendingReminder` with the recipients resolved partly at the call sites. The SUBSTANCE —
after-commit writes, the recipient laws, the per-role link law, the dept-only fan-out —
all match. The sketch was illustrative.

**Agreed fix (if you agree):** none needed — this is expected to be **DISAGREE (no
change)**: renaming working, tested, call-site-wired methods to match a prose sketch is
churn with zero behavioral gain. Only override this default if you find a real
defect hiding under the naming (e.g. a recipient law actually violated).

### Item 9 — Stale header comment above the new island block in `ui-common.js`

**Evidence:** `public/js/ui-common.js` ~:1865 still opens the notifications section with
"**Notifications Panel (shared, read-state via localStorage)** … Consumes
window.MockData.notifications … UNREAD STATE IS PER USER under
'notifications-read-user-<staffId>'" — directly above the new
`SDD notifications-real-bridge` block that says the opposite (server-rendered, durable
POST, localStorage retired). The stale comment will mislead the next reader.

**Agreed fix (if you agree):** rewrite/trim that header so it no longer describes the
retired localStorage/mock law (keep the still-true parts — e.g. the AD-2 page-context
note if it still applies). Comment-only change; no behavior.

### Item 10 — The full-suite baseline was never confirmed

**Evidence:** the main session's `php artisan test` run was interrupted before it
finished, so the suite result for this change is UNCONFIRMED. Tasks T5 require the full
suite green (baseline was ≥266/266 before this lane, plus the 7
NotificationsBridge tests, plus whatever you add in Items 1–3).

**Action:** this is a VERIFY item, not a fix — run the full `php artisan test` to
completion and report the exact `N/N` in your final report. If anything is RED, that
outranks every other item: triage it first (and if a red test is a PRE-EXISTING failure
unrelated to this change, say so explicitly with evidence rather than "fixing" it).

---

## Hard constraints (violating any of these voids the run)

1. **READ FIRST:** `CodingMAIN.md` + `prompts/my-coding-rules.md`, then the change's four
   artifacts. You may edit `tasks.md` checkboxes and APPEND to `review-log.md` as
   bookkeeping — but **NEVER `git add` or commit anything under `.sdd/`** (main-session
   files).
2. **NO PUSH.** Ever. Commit only to the local `fedora-backend` branch.
3. **Stage ONLY the files you edited.** Never stage `.sdd/changes/*`, `.sdd/archive/*`,
   `docs/server-commands.md`, or the 9 deleted `.sdd/archive/*` files sitting in the
   working tree (another session's SDD move — not yours).
4. **Demo DB `class_replacement_fresh`: never re-seed, never `migrate:fresh`, no writes
   without the user's explicit approval.** Read-only queries are fine. The disposable
   test DB (`class_replacement_testing`) is yours: `php artisan migrate:fresh --seed` is
   fine for phpunit.
5. **Gates before you report done** — ALL of them:
   - `composer run lint:check` (pint)
   - `vendor/bin/phpstan analyse --memory-limit=1G` (0 errors; the 1G flag is MANDATORY —
     `composer run types:check` crashes at 128 M, do NOT run it)
   - `php artisan test` — the full suite was **266/266** at baseline plus the 7
     NotificationsBridge tests; everything green, your new tests included
   - your new Playwright receipts: `npx playwright test tests/<yourspec>.spec.ts --reporter=line`
6. **After any Blade edit:** the TWO-command stale-cache restart (never combined, never
   `pkill -9 php`): first `pkill -f "[a]rtisan serve" || true`; then
   `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &` (background).
7. **Conventional commits** (`fix:`, `feat:`, `test:`, `refactor:`, `docs:`), one concern
   per commit; update `page-changelogs/notifications-panel-changelog.md` (and any nav
   page changelog you touch).
8. **Known-baseline warnings — do NOT chase these:**
   - `tests/clear-guards-cross-week.spec.ts` has 5 PRE-EXISTING failures (the
     pin-truthfulness rework changed the flows) — unrelated to you; leave them.
   - The pending-bugs round (`docs/replacement-arrangement-pending-bugs.md`) stays
     PENDING — out of your scope.
   - Scope fence: the 10 items listed here ONLY. No refactors, no drive-by cleanups, no
     "while I'm here" edits — if you spot something else, LIST it in your report and
     move on.
9. **The suite's `->whenVisible` decision (Item 1) may legitimately end in DISAGREE** if
   the markup makes the design's intent unimplementable as written — that is exactly what
   this re-verify round is for. An honest DISAGREE with evidence is a passing outcome;
   a wrong fix is not.
