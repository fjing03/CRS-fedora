# proposal — `class-cancel-2-working-day-window` (2026-10-27)

## Why

The user's law (2026-10-27, verbatim example): cancel eligibility becomes
a DATE window — from (today − 2 WORKING days) through today and ALL
future. Today's Monday example: the 22nd (previous Thursday), 23rd
(previous Friday), the 26th (today), and >= the 27th are ALL cancellable.
The old S5 gate (`end.getTime() > Date.now()` — strict-future TIME) is
superseded; the law is DATE-GRAIN (a today 9 AM class stays cancellable
at 3 PM — the user explicitly includes "26th (Today)").

## Scope

`public/js/ui-common.js` ONLY — the `ClassCancellation` object:

1. `isCancellable`'s S5 line: `end.getTime() > now` →
   `classDateMs >= cutoffDateMs` (midnight-vs-midnight, date-grain);
2. a NEW `_cutoffDate()` helper: walk back from today's midnight skipping
   Sat+Sun exactly 2 working days (the user's example: today Mon →
   cutoff Thu);
3. the S9 shortfall hint follows the new window ("This class is outside
   the 2-working-day cancellation window." — the "already ended" wording
   is retired, the whole day is cancellable per the law);
4. the supersession comment on the OLD S5 receipt (the class-modal-
   affordances strict-future contract).

NOT in scope: S2 (own), S3 (status normal/replacement), S4
(holiday/Sunday), S6–S17 (the confirm/ledger/twin machinery), the
pages' renderButton wiring, the backend (the Cancel action stays
unrouted — the restore-first contract), no other page.

## The cancel-window example baked into tests? NO —

The user's testing note: "No need to test after edit, i'll test it
manually." — NO new Playwright spec, NO manual battery. The change rides
the standing static gates only (php pint/phpstan if PHP were touched —
none here; `php -l` on the JS host blade pages — untouched; the JS file
has no linter in the repo). ONE doc receipt + ONE commit.

## Acceptance (the worked example)

Today = Mon 26 Oct 2026 (the real demo clock): cancelling a Thu 22 Oct
class → ALLOWED; Fri 23 → ALLOWED; Sat 24 / Sun 25 → allowed dates but
the S4 guard's holiday/Sunday family still applies per its own law; Mon
26 (today) → ALLOWED (any time of day); Tue 27 onward → ALLOWED; Wed 21
or earlier → BLOCKED ("This class is outside the 2-working-day
cancellation window.").

## Gates

(CLOCK RECEIPT, round-1 review folded — declarative): the demo pages run
on the REAL clock — `MockData.mockNow = null` is set on ALL three cancel
consumers (my-timetable.blade.php :105, cohort-timetable :183,
venue-timetable :227) — so the "today" in the worked example = the real
machine clock, NOT mock-data.js's Oct 5 anchor (which stays only for the
NOT-set pages; the walkback family shares the getTodayMs() anchor law).

`php -l` untouched (no PHP); `composer run lint:check` +
`vendor/bin/phpstan analyse --memory-limit=1G` (the JS lives in a
public asset; the lexical lint is free) — cosmetic only; the changelog
entries for the THREE page families the cancel machinery serves (my-
timetable, cohort-timetable, venue-timetable — the users of
CancelClass.renderButton); ONE conventional commit; NO push; NO playwright.
