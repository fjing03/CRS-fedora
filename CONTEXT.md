# TAR UMT Class Replacement System

A lecturer-facing system for moving a class that cannot run as scheduled, plus the
read-only timetables that show the result. This file is the **glossary and nothing else**:
what each word means here, and which words we don't use for it. It is not a spec — the spec
is `prompts/for-ai-to-read/CodingMAIN.md`, and the rules are `my-coding-rules.md`.

## The people

**Lecturer**:
A member of teaching staff. Logs in at `/login/staff` with a staff ID.
_Avoid_: Teacher, tutor, user

**PL**:
A lecturer with programme-leader rights, who can approve or reject requests for the programme.
Holds rights; it is not a separate kind of account. Demo: `5425`.
_Avoid_: Approver, admin, CSR, manager

**CSR**:
The role that creates requests on a lecturer's behalf. Not an account in the demo database.
_Avoid_: Admin, coordinator

**Student**:
Logs in at `/login/student` with a student ID; sees their own timetable and their class's
replacements, and can never change anything.
_Avoid_: User, pupil

## Time and teaching

**Semester**:
A dated teaching period with a code (`202605`) and a fixed number of **teaching weeks** (14).
Every timetable on screen and on paper belongs to exactly one.
_Avoid_: Term, session (that word means something else here)

**Teaching week**:
One numbered week inside a semester (1–14), with a date range and, sometimes, a public holiday.
_Avoid_: Calendar week, ISO week

**Slot**:
A half-hour position on the week grid, identified by day and start time. The unit of booking.
_Avoid_: Time slot row, cell

**Public holiday**:
A dated non-teaching day inside a semester. On paper it paints the day's row red; on screen
it mutes the day and marks it.
_Avoid_: Holiday, PH day (fine in prose, not in UI copy)

## The class and its fate

**Class**:
One recurring teaching event: module, lecturer, cohort(s), venue, day, start and end time.
It recurs every teaching week of the semester.
_Avoid_: Session, lesson, lecture (a lecture is one *type* of class)

**Class occurrence**:
One class on one calendar date. "The same class" and "the same occurrence" are different
things: you cancel an occurrence, not a class, and a replacement always replaces an occurrence.
_Avoid_: Instance, event

**Class exception**:
A record that a class occurrence will not run (leave, official event, maternity). The cause,
not the outcome.
_Avoid_: Cancellation (that word belongs to the request)

**Status** — exactly one per class occurrence, and the vocabulary is closed:
- **Normal** — the class runs as scheduled.
- **Replacement** — this occurrence is a make-up class moved here from another slot.
- **Pending** — a request exists for it and nobody has decided yet.
- **Cancelled** — it will not run.
- **Conflict** — it clashes with another class, or it falls on a public holiday.
_Avoid_: "Active", "scheduled", "approved" as status words

## The request

**Replacement request**:
A lecturer's (or CSR's) proposal to move one class occurrence to a new slot, with a reason.
It is the unit of approval, and the thing that is pending, approved, rejected or cancelled.
_Avoid_: Booking, swap, change

**Arrangement**:
The act of choosing the new slot for a cancelled occurrence, and the page that does it.
_Avoid_: Rebooking, rescheduling

**Original slot** / **Requested slot**:
The class occurrence's own day and time / the new day and time being asked for. A request
swaps one for the other; the history keeps both.
_Avoid_: Old/new time, source/target

**Span**:
The contiguous half-hours a class occupies. "Num-hour class" is not a thing here — a class is
two spans of 2 or 3 slots, and an approval must release the whole span or none of it.
_Avoid_: Duration, length

**Blocked**:
A request attempt the server refuses before it exists (slot taken, too late, not enough slot).
Not an error, and not a rejection — nothing was created.
_Avoid_: Failed, invalid

## The screens

**Surface**:
One user-facing screen, as a thing to design and review. A surface is not a route: three
routes render the My Replacements surface, and two render Class Replacements. There are eight
app surfaces plus two login surfaces.
_Avoid_: Page (a page is what the browser loads), view, screen

**Drawing**:
The prototype HTML file that shows a surface's intended look, in `ui-prototype/`. The picture
and the intent live there; a lane's paper points at it rather than restating it.
_Avoid_: Mockup, design file, prototype (say "drawing" when you mean the reference)

**State**:
One situation a surface can be in: landing/populated, empty, modal open, filters panel open,
toast showing. A drift can exist in one state and not another, so states are reviewed
separately.
_Avoid_: Mode, variant

**Drift**:
A difference between a live surface and its drawing, confirmed against the drawing's own
values. Taste alone is not drift.
_Avoid_: Gap, mismatch, bug (a bug is broken behaviour; drift may look fine)

**Pop-up**:
A modal dialog over the surface. Use for the details dialog, the Cancel Class dialog, the guide.
_Avoid_: Modal (in UI copy), overlay (that's the dimmed backdrop)

**Sheet**:
A bottom sheet on a phone — the week summary, the filters panel, the venue picker. Always a
phone thing, always dismissible.
_Avoid_: Drawer, panel (see Filters panel)

**Filters panel**:
The one panel behind the **Filters** button on the three list surfaces. It is a popover on a
tablet and desktop, a sheet on a phone — one panel, two shapes.
_Avoid_: Filter bar, toolbar

**The paper**:
What the printer prints: A4 landscape, its own header, its own table, its own three-chip key.
Never "the printout of the screen" — it is a separate surface built for paper.
_Avoid_: Print sheet, printout, report (each of those means something else)

**Key**:
The three colour chips that explain the paper (Normal · Replacement · Conflict / Public
Holiday). The screen has a five-chip **legend** instead; they are different things and must not
share a word.
_Avoid_: Legend (on paper), scale
