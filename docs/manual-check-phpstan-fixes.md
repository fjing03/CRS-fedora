# Manual check — PHPStan fixes (P1 and P2)

Companion to `docs/phpstan-deep-scan-2026-11-18.md`.

That report lists five spots where the code may read something that isn't there. The AI
fixes them in priority order, stopping after each so a human can look. This file is the
looking.

**What these checks can and cannot prove:** they are a *damage check* — "did the change
break something visible?" They are not proof that the bug is fixed. The broken data state
cannot be built by hand, because the database cascades and the request disappears with the
slot. Proving the fix is the automated test's job, not this file's.

---

## Before you start

- Dev server running on port 8000. Do not restart it between checks — you are checking the
  fix that was just made.
- Log in at `/login/staff` with staff ID **5425** (demo password).
- The checks are done on `/cohort-timetable`.

---

## Check P1 — cancelling one class

Covers `app/Concerns/CancelsClasses.php:81` (cancel, then look up the receipt).

| # | Do this | Pass looks like | Fail looks like |
|---|---|---|---|
| 1 | Open `/cohort-timetable`, pick a week with classes | The timetable draws | Blank page or error |
| 2 | Cancel one class from its dialog, choosing a reason | Success message, class marked cancelled | Error page, red toast, nothing happens |
| 3 | Reload the page | The class is still cancelled | Class is active again |
| 4 | Cancel the *same* class a second time | "Already cancelled" | Any error page |
| 5 | Check how many cancellation records exist for that class and week | Exactly **one** | Zero, or two |

- [ ] P1 passes

**If step 4 shows an error page, or step 5 shows zero or two records, do not accept the
fix.** Ask the AI to revert P1 and re-examine it.

Note: "already cancelled" on the *second* attempt is correct behaviour. The same message on
the *first* attempt is a failure.

---

## Check P2 — the day list

Covers `app/Concerns/CancelsClasses.php:472 and 479` (one bad row killing the day list).

| # | Do this | Pass looks like | Fail looks like |
|---|---|---|---|
| 1 | Open a day with several classes | Every class for that day is listed | Missing rows |
| 2 | Read the start and end times and venue codes | All filled in, correct | Blank cells, odd times |
| 3 | Cancel one class from the day view | List updates, stays populated | List empties |
| 4 | Reload the page | The list draws again | Blank, or an error |
| 5 | Count the rows and compare with before the change | Same count | A row disappeared |

- [ ] P2 passes

**If the list empties, a row disappears, or times/venues go blank, do not accept the fix.**

---

## What to tell the AI

| When | Reply |
|---|---|
| After Phase 1 (verdicts only) | `go` |
| After P1 is checked | `p1 ok` — or describe what failed |
| After P2 is checked | `p2 ok` — or describe what failed |

If a check fails, do not say "ok" to move on. The next fix would land on top of a broken
one, and you would no longer know which change caused what.

---

## Sign-off

| Check | Date | Result | Notes |
|---|---|---|---|
| P1 | | | |
| P2 | | | |

---

## Do not

- Do not edit the database by hand to create the "missing slot" case. The cascade removes the
  request first, so the state you would create is not the state the bug describes.
- Do not fix anything mid-check. Finish the check, note the result, then reply to the AI.
