# explore-brief — `class-cancel-2-working-day-window` (2026-10-27)

## User law (verbatim)

"Allow user to Cancel Class MAX 2 Working days before TODAY and After.
For example, if today is 26th Oct (Monday), Allow User to Cancel Class of
26th Oct (Monday) and >= 27th (Future)."

Hmm — the ACTUAL example law (verbatim, the user's two messages):

Message 1: "if today is 26th Oct (Monday), Allow User to Cancel Class of
22nd (Previous Thursday), 23rd (Previous Friday), 26th (Today) and >= 27th
(Future)."

→ the allowed SET = { today − 2 working days … today, and ALL future }.
Note the example INCLUDES the 22nd (previous Thursday) and 23rd (previous
Friday) — a 2-WORKING-DAY look-back (Mon equates working-days-back to
Fri = 1, Thu = 2). AND the user's manual-testing note: "No need to test
after edit, i'll test it manually."

## The state space (verified dead-code receipts)

**Hint vocabulary:** S9's `cancel()` shortfall branch keys on the OLD
strict-future test (`end.getTime() <= Date.now()`) — with the window
re-grained to DATE the shortfall wording must follow the NEW law
(a pre-cutoff DATE = "outside the cancellation window", not "already
ended"), and an in-window PAST-TIME class (today 9 AM class, now 3 PM)
must still be cancellable — the hints too.

**Doc receipt:** prior SDD receipts my said "END datetime strictly in the
future (real clock per design §1)" — that §1 was the class-modal-
affordances restore contract, now superseded BY THIS USER LAW (the S5
line itself is the ONLY site; document the supersession in doc-comment).

## Rejected approaches and why

1. **Multi-city / ISO end-of-day boundary via `.setHours(23,59,59,999)`** —
   rejected: the user's law reads DATE-GRAIN (the whole 26th stays
   cancellable even at 23:59), and the whole-datetimes then compare
   equal-or-later to TODAY'S midnight — the cleanest window receipt
   stays `classDateMs >= cutoffMs`.
2. **Include Sat/Sun in the working-day walkback** — rejected: the
   example law面条 22nd/23rd = the two preceding WORKING days only;
   counting calendar days would make the Fri example (today=Mon) reach
   back to Sat 24th (a NON-working day) as working-day #2 — contradicting
   "the previous Thursday, the previous Friday" wording.
3. **Holiday-aware walkback** — rejected (deferred-decision): the
   example names only working-vs-weekend; holidays create the "no class
   to cancel on THAT date anyway" S4 block, which already gates the
   cancel (the class on a holiday date is NOT cancellable — the DEAD
   state). Counting holidays as non-working would ADD behaviour beyond
   the user's example; document the assumption instead.
4. **Changing the S4 holiday/Sunday guard** — NOT in scope: the user's
   law re-grains the TIME window only; the day-family guards stay.

## Final solution mapping

- `isCancellable`'s S5 line — replace `end.getTime() > Date.now()` with
  the NEW receipt: `classDateMs >= cutoffDateMs` where cutoffDateMs =
  midnight of (today − 2 working days)
- NEW helper `_cutoffDate()` — walk back from today's midnight, skipping
  Sat+Sun, 2 working days — deterministic LOCAL time
- the class date as MIDNIGHT via the existing `endDateTime()` date-half
  (the pinned 'DD Mon YYYY' vocabulary) — reuse, don't parse twice
- the S9 shortfall hint re-words: outside-window ("This class is outside
  the 2-working-day cancellation window."), in-window = the cancel runs
- the S4/S6/S7/S8/S16 receipts untouched (the family stays)

## Known open questions

- Q1 (deferred): do HOLIDAYS count as working days in the walkback? The
  example names only weekends; the S4 holiday guard already blocks
  cancelling ON a holiday, so the walkback's holiday treatment only
  shifts the cutoff by ≤2 days. Documented assumption: weekends only.
