# Proposal — Reuse cancelled class slots and keep both timetable blocks

Date: 2026-11-13
Change: `cancelled-slot-reuse`
Batch: 1 — proposal
Status: proposal frozen after independent review Round 1; not approved for implementation

## 1. Why

A cancelled class will not run. Its lecturer and cohorts already stop blocking that occurrence in the availability checks, but its original room cells may remain occupied. The user now wants all three resources released for that occurrence.

The user also wants the cancellation to remain visible when someone books that room/time. A cancelled history block is not a second active booking. The timetable must show both records clearly, without painting one over the other or making the cancelled record block the new request.

The supplied screenshots show different venues (B100/B010 versus B111); they are not evidence of an actual double booking. This is a requested rule and display change, not a claim that two active bookings have been found.

## 2. Authority and approval limits

The choices in `explore-brief.md` are the baseline. They replace the earlier decision to keep cancelled rooms reserved. They do NOT introduce a leave/unavailability calendar.

**This document is planning only.** No app code, test code, schema, services or data is changed. Implementation requires a later explicit approval after design/spec/tasks review. The existing-data cleanup is a separate apply gate: immediately before it, present the current affected rows and obtain approval for that exact write. Approval to write this proposal is not approval to run the cleanup.

Do not edit the formal FR/NFR copy without separate permission. Do not commit or push this planning batch without the user's instruction.

## 3. Part A — booking rule

1. Cancel one class occurrence → release its original room/time reservation for that semester, session and week. Remove that occurrence from lecturer/cohort busy time, as the current exception-aware checks already do.
2. Do not change the recurring session or other weeks. Do not block the whole date or infer leave from the reason. A whole-day cancel applies the same rule to each selected class only; unticked classes survive.
3. The exception and cancellation history remain. Cancellation is final; no undo feature is added.
4. Preserve the existing closure of pending/approved requests attached to a cancelled occurrence, their booked-slot release, audits and notifications. A ticked make-up row still withdraws its request only; it does not cancel its source class.
5. A different eligible class, including one taught by the same lecturer or attended by the same cohort, may book the released cells if every independent check passes.
6. The same cancelled class may request its original date/time/room again. It still needs a valid replacement occurrence, no existing live request for that occurrence, the usual checks, submission confirmation and PL approval. No automatic approval or general new-class booking flow is added.
7. A cancelled record never counts as active occupancy. Existing classes and pending/approved make-ups still block their lecturer, cohorts and room.
8. Frontend availability, backend submission and approval validity must agree. Merely hiding a red block is not a release.

### Booking examples after cancellation

Assume the target lecturer/cohorts have no other clash and the target room is eligible and otherwise free. All rows remain subject to the existing notice/holiday/duration checks.

| Target at the cancelled occurrence's time | New rule |
|---|---|
| Same room, different cohort | May request |
| Different room, different cohort | May request |
| Different room, same cohort | May request |
| Same lecturer, different course/cohort | May request |
| Same class, original room/time | May request again |
| Any of the above with another active room/lecturer/cohort booking | Refuse |
| Same coordinates in an uncancelled week | Original recurring class still blocks |

### Unchanged safeguards

Ownership and student read-only access; one live request per source occurrence; pending reservation; exactly one winner per room cell under concurrent submissions; full-span checks; venue type/module/capacity rules; Sunday/holiday restrictions; three-working-day notice; three-working-hour automatic rejection; request status/audit/notification semantics except the newly recorded original-room release. No scheduler changes in this lane.

## 4. Part A — timetable display

### Shared behaviour

- Keep cancelled and new Pending/approved blocks separately visible whenever they overlap in a viewer's scope. Neither silently replaces, merges with, or covers the other.
- Stack the blocks vertically in separate lanes; enlarge that day's row only as needed. The cancelled block is above the new booking.
- Keep each event's true start/end position and duration, including partial overlaps, adjacent blocks and different spans. Do not shorten two hours to one cell, duplicate events, or shift their time to fit.
- Handle more than two overlapping records with stable ordering. Within the cancellation group and within the new-booking group, use a deterministic order to be defined in design.
- Each visible block has its own course, time, venue and status, existing colours/ownership styling, accessible focus target and correct details popup. Notification jumps must target the intended event, not a cancelled neighbour sharing its cells.
- If the new request is withdrawn/rejected, its active booking block disappears as it does today; the original cancellation remains. If approved at the overlapping position, the cancelled history block remains above the approved replacement. Existing behaviour for approved make-ups at non-overlapping positions is otherwise unchanged.
- Cancelled history does not consume room capacity, teaching hours or bookable-cell totals. Summary cards must follow their documented formulas; do not count extra layout lanes as extra occupied time or active classes. Any cancellation-history class count remains governed by the existing card formula.

### Pages and privacy

| Page | Relevant records |
|---|---|
| Staff My Timetable | That lecturer's cancelled classes and own new bookings; do not add other lecturers' unrelated bookings |
| Venue Timetable | Cancelled history in that room plus its new bookings, respecting existing limits on other staff's request details |
| Cohort Timetable | Records involving the selected cohort only |
| Student My Timetable / permitted student cohort view | Records involving the student's cohort only; no staff-only cancellation reason or request details |

“All pages” means one overlap rule across these read-only timetable surfaces, not a permission to show unrelated events. Desktop, tablet, phone, both themes and existing print output must retain both relevant records without clipping or unreadable overlap. This is compatibility with print, not the separate `timetable-print-v2` redesign.

### Arrangement grid

A released cell looks like any other available green cell when all other checks pass. No new freed-by-cancellation tooltip or badge. A remaining active clash, holiday, pending hold or notice restriction still wins. The booking grid is not the stacked history view.

## 5. Part B — explain remaining room-only conflicts

Include the parked Thursday 12 November Small tasks item 1 as a separate, wording/data-display part, intended for its own commit after Part A.

- A room-only blocked cell must name its actual active holder on hover even if the viewer shares neither that lecturer nor that cohort.
- Use the existing shared text tooltip style and data-driven wording; keep the existing cell label and booking answer.
- Include enough allowed facts to explain the conflict: course/type, room and actual time, and the relevant public status. Respect the current restrictions on other staff's request information. Do not expose remarks, private cancellation reasons or hidden request data.
- Where more than one genuine blocking record applies, preserve the distinct facts without duplicates. The final copy and field list are to be defined in design using existing tooltip vocabulary.
- A freed cancelled occurrence is not an active holder and must not be described as holding the room. The old to-do example ending in “cancelled” is superseded for freed cells by Part A. Cancelled history remains available in its own timetable block/details instead.
- Part B itself changes no eligibility rule. It must not restore the old “keep room blocked” rule or add a note to green cells.

## 6. Existing cancellations — planned cleanup, not executed

Target **only** `class_replacement_fresh`, the database behind port 8000. `class_replacement_viva`, `class_replacement_demo` and other databases are excluded. All automated/write tests and rehearsals use `class_replacement_testing` on port 8030, selected explicitly by `DB_DATABASE=class_replacement_testing`; never `--env=testing`.

Before any later demo-data write:

1. Prepare an explicit dry-run inventory of cancelled occurrence pairs and their original room cells. Re-read live state; do not assume that session 67 or any screenshot still describes the current data.
2. Rehearse the cleanup on testing, including already released occurrences and cells since reused by a new request. The testing-only automatic checker must be accounted for when preparing repeatable fixtures; do not change it silently.
3. Take a verified `pg_dump -Fc` snapshot; `pg_restore --list` must pass. Record target and before counts/identities. Show the exact proposed changes and obtain separate user approval.
4. Release only the original recurring reservation still owned by each cancelled occurrence. Never free a current pending/approved request's cells, even if a legacy `class_session_id` still points to the old class. Refuse and report ambiguous ownership rather than guessing. Existing released rows remain unchanged; rerunning does not release a later booking.
5. Preserve request statuses, request-slot links, cancellation reasons/history, recurring schedules, other weeks and other data. Use normal version-safe state changes and record the affected identities and release evidence.
6. Verify after counts, actual freed cells, request-slot links and all preserved fields; log the receipt in `docs/demo-db-data-changes.md`. Do not stage another session's entries.

No destructive reseed, broad status update, schema/data migration or automatic cleanup on page load is allowed. Slot/schema strategy belongs in the reviewed design; no schema change is presumed necessary.

### Rollback boundary

Stopping the new behaviour does not make a cancelled class active again. Before any new bookings, a scoped rollback may restore only the cells from the recorded cleanup receipt after verifying they are still unused; versions never move backwards. Once new requests use the cells, do not blindly mark them occupied again or restore an old whole-DB snapshot over newer work. Stop the rollout, present the new state, and obtain a recovery decision. The design must specify the operational steps before apply.

## 7. Likely impact (to confirm in design)

- `app/Actions/CancelClassBatch.php`, shared original-occurrence release with the existing approval path where suitable; `CancelClass` delegation and request release safety.
- `app/Concerns/ResolvesTimetableTimeline.php` and timetable Livewire payloads, including `VenueTimetable`'s separate slot-backed builder and suppression/merging logic.
- `MatrixIntersectionEngine`, submission/OCC/approval validity readers as necessary for consistent release and rebooking, without weakening concurrency guards.
- Shared `public/js/ui-common.js` timetable layout, `public/css/theme.css`, and page-specific timetable renderers. Avoid separate copied stacking algorithms.
- Arrangement holder payload/tooltip composition and its existing renderer, plus compatibility with the arrangement phone view.
- A guarded existing-data cleanup facility/runbook, tests, matching page changelogs and later context updates.

No new dependencies. Keep unrelated dirty files and lanes untouched; coordinate shared-file edits with the other active session before implementation.

## 8. Acceptance and verification planned

These are requirements for later tests, not claims of tests run now:

1. Cancelling session 67 in Week 9 frees its original 09:00–11:00 room cells; other weeks and unticked day classes remain unchanged. Single/day cancellation share the rule and remain all-or-nothing on failure.
2. Eligible own/other-class reuse succeeds, including the exact same class/date/time/room, while a normal-week class and every unrelated active clash still block. Full-span/concurrent requests cannot produce two active bookings.
3. Releasing or approving an original occurrence never frees a later pending/approved booking of those cells. This includes same-class same-slot rebooking.
4. Every relevant timetable shows two independently clickable, readable stacked blocks for exact and partial overlaps, cancelled first. Test 30-minute, one-hour, two-hour and multiple-history cases, no-overlap and adjacent-event regressions, own/other lecturer and cohort filtering.
5. Approval at the same coordinates retains both records; rejection/withdrawal/automatic rejection removes the active proposal only and leaves the cancellation. No lost reservation links, stuck cells, false busy ranges or wrong modal duration.
6. Green arrangement cells have no new cancellation hover; red room-only cells explain the real active holder. Student/other-staff privacy remains intact.
7. Guarded cleanup dry-run writes nothing; apply is identity-scoped, repeatable and leaves live-request cells alone. Snapshot/rehearsal/approval/receipt and rollback checks are demonstrated before any demo write.
8. Run backend tests on testing only; lint, static analysis, JS syntax and compiled-view checks. Playwright receipts use the exact testing-target guards; cover desktop/tablet/phone, both themes, keyboard/modal targeting, summary formulas and print compatibility. Report pre-existing failures honestly.

## 9. Delivery boundary

Proposed implementation split: Part A (release rule, shared stacking and its tests) and Part B (room-only hover explanation and its tests), separate commits. Existing-data cleanup has its own approval/receipt and is not bundled into a code migration.

This batch stops after writing/reviewing the proposal. Later design, specifications and tasks must pass their own review gates. Do not execute any part merely because this file exists.
