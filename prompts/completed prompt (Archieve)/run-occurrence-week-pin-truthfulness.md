   # Execute: SDD `occurrence-week-pin-truthfulness` — Steps 2–8

   You are the EXECUTING session for this SDD change. The investigation and all user
   rulings are DONE — do NOT re-investigate, do NOT re-ask the user any planning
   question. Follow the steps in order, one batch at a time.

   ## 0. Read first
   AGENTS.md → prompts/my-coding-rules.md → CodingMAIN.md (§10 conventions) →
   docs/queue-and-emails.md (existing) → the skill `sdd-workflow` (SDD kit usage,
   review loop, MAX_ROUNDS=5) → `~/.pi/agent/prompts/sdd-propose.md` (the reviewer
   subprocess recipe you MUST use for every batch freeze).

   ## 1. Mission
   The My-Timetable "Replace Now" hands off the VIEWED week (`window.currentWeek + 1`)
   instead of the conflicted occurrence's week; the arrangement honours it and the
   silent fallback `$occurrenceWeek ?? slotWeek` lets no-pin submits book the wrong
   occurrence. Consequence: request #11 recorded `week_number = 5` (the make-up week)
   instead of **14** (the holiday occurrence it replaced), its approve stamped a bogus
   `class_exceptions (67, 5)` and freed the LEGIT W5 class block. Fix = strict pin
   truthfulness (user-ruled) + venue-popup accuracy + demo-data repair (Route C).

   ## 2. LOCKED FACTS (verified 2026-10-19 via pgAdmin — do not re-run)
   - Semester `202605`: start **2026-09-21 (Mon)**, 14 weeks → W5 Thu = 22-Oct-2026,
     W14 Thu = 24-Dec-2026 = holiday **"Christmas Eve"** (holidays: W14 day 3).
   - Sessions: **#67** module 61, THU 09:00–11:00, venue 7, **L** (2h);
     **#68** THU 11:00–12:00, T (1h). **No W14 slot rows exist** for 67/68 (holiday import).
   - **Request #11**: session 67, `week_number = 5` (WRONG, true = 14), anchor 10693,
     status `approved` (self-approve, user_id 1, 08:31:39), junction 10693–96 (span 4,
     makeup W5 THU 14:00–16:00 B100 — a legitimate receipt).
   - **Request #12**: session 68, `week_number = 14` (CORRECT), anchor 10623, `pending`,
     junction 10623–24 (span 2, makeup W5 THU 09:00–10:00).
   - Audits: #11 = 4×submitted + 4×approved flips + 1 summary (id 37, anchor 10693);
     #12 = 2×submitted.
   - Exceptions: `(67, 5, official_event)` @08:31:39 = **BOGUS** (the #11 approve);
     `(94, 14, official_event)` @06:07:57 = CORRECT-keying positive control (#10:
     original W14, junction W5 B002).
   - Slot states W5: 10623–26 = #67's legit block — freed v2 by #11's release;
     10623–24 re-reserved v3 pending by #12; 10625–26 available v2; 10627–28 = #68
     legit block `occupied v1` (untouched).
   - Incident repro: my-timetable popup → Replace Now → URL
     `http://127.0.0.1:8000/replacement-arrangement?session=67&week=5&from=my-timetable`.
   - Key code sites: `my-timetable.blade.php:99`
     (`goToReplacementFor(currentModalEvent?.id, window.currentWeek + 1, 'my-timetable')`),
     `ui-common.js:1287` (goToReplacementFor), arrangement boot pin hydration
     (`PAGE_DATA.pinnedSession` → `window.pinnedOccurrence`),
     `ReplacementArrangement::submitBlocks` (`$requestWeek = $occurrenceWeek ?? $week`),
     `SubmitReplacementRequest::__invoke` (validates week 1–14 range only).
   - TO-VERIFY at design time (grep, don't assume): (a) the Replace-Now gate
     (`days[event.di].holiday` looks ALWAYS-undefined in weekData → did a normal W5
     block really offer Replace Now? why?); (b) whether `VenueTimetable` sets
     `originalStartTime/-EndTime/originalVenue/Name` keys post-2026-10-19 modal re-key
     (popup "Original Start 2:00–4:00 PM" fallback bug) — re-grep before writing fixes.

   ## 3. LOCKED RULINGS (user, 2026-10-19 — law)
   | # | Ruling |
   |---|---|
   | Q1 | span-aware-approval-flow leftovers (missing D3 QueryException catch + missing bulk tests) = **separate mini-SDD later** — NOT bundled here |
   | Q2 | **Strict pin**: no-pin submit → block politely; AND `<session, week>` must carry a real conflict fact (holiday / class_exceptions / venue-restriction) |
   | Q3 | Entry = my-timetable Replace Now; handoff proved `?week=5` |
   | Q4 | **Route C — DELETE requests #11 + #12, restore W5 truth, redo live through the fixed UI** (audits kept, FKs pre-nulled) |
   | Q5 | Pre-repair insurance = targeted pgAdmin export (table-level) |
   | Q6 | resolved by Q4 (#11 not kept) |

   ## 4. Fix inventory (tune at design, freeze in tasks)
   - **F1** `app/Actions/SubmitReplacementRequest.php`: occurrence-conflict validation —
     loser shape `occurrence_not_conflicted` (+ polite conflictText) when
     `<class_session_id, week_number>` carries NO holiday/exception/restriction fact.
     Reuse the SAME fact derivation the home list uses (LectureSessionCatalog /
     ReplacementHome parity) — ONE source.
   - **F2** `app/Livewire/ReplacementArrangement.php::submitBlocks`: remove the silent
     `$occurrenceWeek ?? $week` fallback — no pin → loser `no_occurrence_pin`.
   - **F3** `resources/views/livewire/replacement-arrangement.blade.php`: client submits
     blocked with the same copy when `window.pinnedOccurrence` is null (picker-first
     message); picker pick re-pins (existing :3159) stays THE pin path; Replace-Now
     entries hydrate the pin from the URL (existing :3392).
   - **F4** my-timetable Replace-Now gate + handoff week (only after the §2a grep):
     the button must fire ONLY for occurrences with a live conflict fact, and
     `goToReplacementFor` receives THAT occurrence's week (the viewed-week default
     dies; a cancelled/holiday-state popup carries its own week).
   - **F5** `app/Livewire/VenueTimetable.php`: add `originalStartTime / -EndTime /
     originalVenue / originalVenueName` to its replacement events IF the re-key
     hasn't landed there already (grep first) — tokens only, no new CSS.
   - **F6** tests `tests/Feature/` new file e.g.
     `tests/Feature/OccurrencePinTest.php`: submitBlocks no-pin → rejected;
     normal-week occurrence → `occurrence_not_conflicted`; holiday week → accepted;
     positive control fixture (original-week ≠ junction-week approve keys D3 right);
     F5 payload keys if F5 lands. Extend-existing files only where natural.

   ## 5. Step 2 — open the change
   ```bash
   SDD_ROOT=.sdd bash ~/.config/opencode/tools/sdd/new-change.sh occurrence-week-pin-truthfulness
 ```

 If the kit script is missing: mkdir -p .sdd/changes/occurrence-week-pin-truthfulness/specs
 - write sdd.yaml (name/created/status: active).
   Then write explore-brief.md = Sections 1–4 of THIS file (merged) + a
   "Rejected approaches" block: ① patch renders to derive dates from holidays
   (rejected — four renderers would fork; week_number is the SSOT, render code is
   correct); ② Route A "keep #11, edit week in place" (rejected by user for C);
   ③ snapshot-restore Route B (rejected — snapshot recency unknown here).

 6. Steps 3–5 — batches (proposal → design → tasks)

 For EACH batch: draft in this order proposal.md → design.md → specs/ (3 specs:
 pin-truthfulness, venue-original-fields, demo-data-repair) → tasks.md,
 then run the SDD reviewer subprocess (recipe in ~/.pi/agent/prompts/sdd-propose.md —
 model commandcode/z-ai/glm-5.3-flash, read-only tools), fix 🔴s, append
 review-log.md, freeze at zero-🔴 (MAX_ROUNDS=5). Review baseline per batch =
 all previously frozen artifacts + this handoff file + the explore brief.
 Design decisions to encode: D-P1 F1 loser shapes/copy; D-P2 F2 pin mandatory
 (the fallback's death sentence); D-P3 F3 client gate; D-P4 F4 the Replace-Now
 truth (with the §2a grep answer); D-P5 F5 venue keys; D-P6 Route-C repair SQL
 (§8) + no (67,14) stamp insert (holiday = the true fact; a correct approve
 WOULD stamp it — decide at design with the user's §Q6 answer in mind: default
 NO stamp until the user re-approves through the fixed UI); D-P7 render readers
 need NO change (they key week_number — state it so tasks don't over-edit).

 7. Step 6 — apply code slices

 Slice order: F1+F2 (server) → F3 (page) → F4 (gate) → F5 (venue) → F6 (tests).
 Standing: Conventional commits (fix:/ui:/feat:); composer run lint:check +
 vendor/bin/phpstan analyse --memory-limit=1G after each slice;
 phpunit baseline = 222 (+new); Playwright = in-scope suites, ALWAYS
 --grep-invert "TC70"; stale-cache ritual after Blade edits (TWO commands, never
 one string: pkill -f "[a]rtisan serve" || true THEN
 rm -f storage/framework/views/*.php && php artisan serve --port=8000 &,
 workers PHP_CLI_SERVER_WORKERS=8); NO git push (never, unless the user says).
 Never touch: replacement-home.blade.php + its changelog (concurrent zone
 qv-arrange-null-order-fix); note the venue-capacity law: ruleMap() L AND T →
 [tutorial, lecture_hall] — do NOT assert old venue pools in tests; reuse
 ReplacementRequestMapper::requestedAtDisplay()/stampDisplay() for any stamps;
 the note copy "(Replaced for <date>)" is user-VOTED verbatim — do not rename.
 Update page-changelogs/my-timetable-changelog.md,
 replacement-arrangement-changelog.md, (if F5) venue-timetable-changelog.md.

 8. Step 7 — data repair (Route C) — USER-RUN ONLY in pgAdmin, after their explicit go

 Pre-flight: pgAdmin → right-click each table → Import/Export → export:
 replacement_requests, replacement_request_slots, the time_slots slice
 (SELECT * FROM time_slots WHERE id BETWEEN 10620 AND 10700; as CSV),
 class_exceptions, audit_logs WHERE replacement_request_id IN (11,12) (§Q5=B).
 Then, in ONE transaction, after the user says "repair approved":

 ```sql
   BEGIN;
   UPDATE audit_logs SET replacement_request_id = NULL WHERE replacement_request_id IN (11,12);
   DELETE FROM replacement_request_slots WHERE replacement_request_id IN (11,12);
   DELETE FROM replacement_requests WHERE id IN (11,12);
   UPDATE time_slots SET status='available', version=version+1 WHERE id IN (10693,10694,10695,10696);
   UPDATE time_slots SET status='occupied',  version=version+1 WHERE id IN (10623,10624,10625,10626);
   DELETE FROM class_exceptions WHERE class_session_id = 67 AND week_number = 5;
   COMMIT;
 ```

 Order is load-bearing: row deletes BEFORE the occupied-restore (no live
 reservation may reference them). NO (67,14) insert in this script (§6 D-P6).
 Demo DB is NEVER written by application code at any step.

 9. Step 8 — post-repair verification

 - SQL (read-only): requests 11/12 gone; audits nulled; 10623–26 occupied;
   10693–96 available; exceptions = only (94,14); Semesters untouched.
 - User REDOES live through the fixed UI: my-timetable → class #67 → Replace Now →
   expect handoff ?session=67&week=14 (NOT 5) → strict validation passes (holiday
   fact) → book the makeup → submit → pin=14 lands, then PL approves → D3
   stamps (67,14), frees nothing (no W14 rows) — re-run #68's replacement the
   same way if the user wants #12 back.
 - Gates: pint · phpstan 0 · phpunit ≥222+new · Playwright in-scope suites
   (grep-invert TC70) · stale-cache ritual after blade edits.
 - Then /sdd-verify occurrence-week-pin-truthfulness (report format per
   ~/.pi/agent/prompts/sdd-verify.md) → on PASS suggest
   /sdd-archive occurrence-week-pin-truthfulness; remind the user the
   span-aware-approval-flow leftovers (§3 Q1) still await their own mini-SDD.

 10. Hard absolutes

 Demo DB class_replacement_fresh: NEVER migrate/seed/write via application code —
 writes exist ONLY as this file's user-run repair script. Never push. Never
 pkill -9 php. TC70 never executes. Honest zeros — no fabricated demo data.
 SDD_READ order and the reviewer recipe are non-skippable.
 SDD_EOF
 wc -l prompts/run-occurrence-week-pin-truthfulness.md</arg_value></tool_call>
