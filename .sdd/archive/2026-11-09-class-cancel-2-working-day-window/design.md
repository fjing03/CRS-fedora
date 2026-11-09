# design — `class-cancel-2-working-day-window`

Reads against the frozen proposal. Anchors verified 2026-10-27 (tree at
`8566e21`); the implementer re-greps.

## D1 — `_cutoffDate()` (NEW helper, inside `ClassCancellation`)

Placed after `endDateTime` (the date-half reuse site):

```js
/* SDD class-cancel-2-working-day-window (user law 2026-10-27): the
   cancel window opens 2 WORKING days before TODAY. Walk back from
   today's midnight skipping Sat+Sun exactly twice (the user's Monday
   example: Thu = 1, Fri = 2 today Mon means the two previous
   working days are FRI (1 back) and THU (2 back) — the walkback skips
   non-working days between them, which is why Sat 24/Sun 25 (week-end,
   not working days) sit INSIDE the window as calendar dates). Holidays
   are NOT counted as non-working (documented assumption — the example
   names only weekends; the S4 guard still blocks cancelling ON a
   holiday independently). */
_cutoffDate() {
    const d = new Date(getTodayMs());   // the demo clock anchor (the S5 real-clock law's family — MockData.mockNow falls to the real clock)
    let counted = 0;
    while (counted < 2) {
        d.setDate(d.getDate() - 1);
        const dw = d.getDay();
        if (dw !== 0 && dw !== 6) counted++;   // Sun = 0, Sat = 6
    }
    return d;   // midnight-anchored (getTodayMs already setHours(0,0,0,0) and setDate preserves the time)
}
```

## D2 — the S5 swap (the ONE gate line, consolidated per review round-1)

`isCancellable`'s S5 becomes:

```js
        /* S5 — REVIEW-AMENDED (user law 2026-10-27, class-cancel-2-working-
           day-window): the OLD strict-future END-time test is SUPERSEDED by
           a DATE-GRAIN window — cancellable iff the class's DATE sits on /
           after (today − 2 working days). The whole "today" stays
           cancellable even after its end time (the user's "26th (Today)"
           bullet); a today-9AM class at 3PM reads ALLOWED. */
        const clsEnd = this.endDateTime(event, days, weekIndex);
        if (!clsEnd) return false;
        const clsDateMs = new Date(clsEnd.getFullYear(), clsEnd.getMonth(), clsEnd.getDate()).getTime();   // the midnight strip: DATE-grain, locale-free per the §10 pin (no second parser pass)
        return clsDateMs >= this._cutoffDate().getTime();          // S5 (the window law)
```

## D3 — the S9 hint (TWO-WAY kept, re-keyed on the new law — review round-1 🟡)

`cancel()`'s guard branch re-keys: the WINDOW wording fires only when the
class date is genuinely OUTSIDE the window; every other guard failure
(S2/S3/S4 — e.g. a twin already cancelled via ledger replay, status
'cancelled') keeps the honest generic fallback:

```js
const clsEnd = this.endDateTime(event, days, weekIndex);
const outside = !clsEnd || (new Date(clsEnd.getFullYear(), clsEnd.getMonth(), clsEnd.getDate()).getTime()
    < this._cutoffDate().getTime());
return { ok: false,
         hint: outside
               ? 'This class is outside the 2-working-day cancellation window.'
               : 'This class can no longer be cancelled.' };
```

## D4 — the supersession note on the S5 comment

The doc-comment above `isCancellable` ("END datetime strictly in the
future, real clock per design §1") gains the supersession receipt (the
class-modal-affordances restore contract → the user's 2-working-day
window law, this lane).

#
## D6 — the SPEC-IMPACT cycle (review round-1 blocker 1 — required scope)

The user waived NEW testing ("i'll test it manually") — NOT the honesty of
the EXISTING standing battery. One existing row directly asserts the
SUPERSEDED law and would go red at the next battery run:

- `tests/cancel-class.spec.ts` **S5** (:160, "own class whose end time has
  passed shows no cancel button", Week 1 Mon 21 Sep 2026): under the NEW
  window the fixture date is STILL blocked (real-clock cutoff ≈ Oct 23 >
  Sep 21) → the row stays green VERBATIM; only its header comment gains
  the receipt (the row now tests the WINDOW guard, not the end-time guard).
- `tests/cancel-class.spec.ts` **S6** (:450, "class ending between modal
  open and confirm aborts with the 'already ended' hint", the Tue Dec 1
  fixture + `page.clock.setFixedTime`): under the new law this scenario is
  LEGAL-BY-DESIGN (a future-date class is cancellable even when its end
  passes mid-modal — the whole day stays alive per the user's "twenty-sixth"
  bullet). The row RETIRES with the honest skip + law receipt (the S17
  precedent), NO rewrite, NO run.
- `tests/cancel-class.spec.ts` **S17** (the :490 skip receipt): its blocking
  reason changes from the end-time guard to the WINDOW guard (Oct 21 <
  the Oct 23 cutoff) — comment amended, the skip itself stays.
- `ui-common.js` :4339-area stale header comments ("END datetime strictly
  in the future") amended to the window law.
- The S4 row (:437, the Deepavali Monday fixture): the S4 guard's block
  receipt UNCHANGED under the new law (a holiday date is blocked by S4
  regardless of the window) — comment touch-up only.

## D7 — housekeeping (review round-1): sdd.yaml created BEFORE any apply

## D5 — gates

- NO NEW SPEC, NO manual battery (the user's explicit testing note).
- `composer run lint:check` + `vendor/bin/phpstan analyse --memory-limit=1G`
  (cosmetic — the asset is JS; the renderButton host blades are untouched).
- Changelogs: my-timetable / cohort-timetable / venue-timetable — the
  three renderButton consumers.
- `docs/replacement-arrangement-pending-bugs.md`: no BUG-xx link (this is
  a feature law, not a bug).
- ONE conventional commit (`feat:`); NEVER push.
