# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

**One persona, two hats.** A lecturer at TAR UMT (Sabah) who owns classes across several cohorts.
They hold the programme-leader (PL) right on some programmes, so the same person arranges their own
replacements *and* approves colleagues' requests for their programme. The demo data makes this
explicit: staff `5425` is both a lecturer and a PL.

Their situation: a class cannot run as timetabled — they are on leave, on official duty, or the day
is a public holiday — and the make-up has to be found inside a working week, in a room that is
actually free, before the deadline passes. They are between teaching duties, often on a laptop, and
the job has to be finished in the gap they have.

**Students** are a second, read-only audience: they see their own timetable and the replacements for
their cohort, and can change nothing. They are not part of the lecturer's job and must not shape its
screens. *(The CSR role exists in the problem statement as a person who files requests on a
lecturer's behalf; there is no CSR account in the demo database, so nothing in this product assumes
one.)*

## Product Purpose

To move a class that cannot run to a slot where it can, and to keep everyone who depends on that
class looking at the same truth afterwards.

Success: within one working week of the cancellation, the class has a new slot, the room is booked
once, the PL's decision has been made, and the student's timetable already shows it.

## Positioning

**A replacement is only offered where the slot is genuinely free, and it moves the whole class as
one span.** A neighbouring product could not truthfully say either half: it can reserve a room and
hope, or move one hour of a two-hour class and call it done.

Supporting claims, both true of this code and both worth showing once the headline is earned:

- **The whole span moves, or nothing does.** A two-hour class is a four-slot span; approval releases
  all of it or refuses, never half.
- **The loop closes.** Cancellation → proposal → approval → the student's timetable, all reading the
  same class occurrence, with an audit trail of who decided what.

## Operating Context

- **A semester is 14 teaching weeks**, each with a date range and sometimes a public holiday. Every
  timetable on screen and on paper belongs to exactly one semester.
- **The unit of work is a class occurrence**: one class on one date. You cancel an occurrence, not a
  "class" in the abstract, and a replacement always replaces an occurrence.
- **Requests are filed by lecturers** (or, in the problem statement, by a CSR on their behalf) and
  decided by the PL of the programme the class belongs to.
- **The replacement is found in the arrangement page**, which is the product's centre of gravity: a
  venue picker, a week picker, a bookable week grid, and a running summary of what the request will
  say.
- **Demo and viva runs**: the same product is shown to examiners on a demo database whose classes,
  names and schedules are simulated. The interface says so in its own footer.
- **Printed timetables are real output**: a lecturer pins the week's timetable to a wall, so the
  product prints an A4 sheet built for paper, not a screenshot of the screen.

## Capabilities and Constraints

Confirmed capabilities: request a replacement and approve or reject it; cancel a single class or a
whole teaching day; find and hold a slot that is free; per-cohort and per-venue timetables; a
read-only student view; a printed week sheet in both a single-week and a whole-semester form; an
audit trail; and a dashboard of what needs attention.

Confirmed constraints:

- **A student never acts.** Every student surface is read-only by design, not by permissions alone.
- **The demo databases are not disposable.** They hold real class data and the user's manual test
  data; schema changes are additive only, and nothing may be reseeded or wiped. *(Inferred from the
  repository's own standing rules, not stated by the user in this interview.)*
- **No new dependencies**; the interface is built from the existing stylesheet's tokens, the existing
  partials and the existing JavaScript helpers. *(Inferred from the repo rulebook.)*
- **Touch sizes are 48 × 48 on touch-capable devices**, gated on capability rather than window width,
  and **there is no hover on a touch screen**. *(Inferred from the repo's 2026-11-15/16 rulings.)*
- **Viva twin**: the product runs against a second copy of the database so a demonstration can never
  endanger the working data. *(Inferred from the repository.)*

## Brand Commitments

None stated by the user, and none implied by assets: this is an internal faculty tool with an
institutional name and logo, not a brand. The name "Class Replacement System" and the TAR UMT
identity are fixed.

## Evidence on Hand

- A working application with real imported class data, not mock screens: `resources/views/livewire/`
  and `app/Livewire/`.
- Hand-built prototypes that carry the intended look **and** their reasons in code comments:
  `ui-prototype/` (timetable previews, print preview, the three replacement pages).
- A measured UI survey with paired captures and findings: `docs/ui-drift-2026-11-17.md`.
- Demo logins and roles: `docs/demo-logins.md`.
- No testimonials, no customers, no benchmark numbers, no press. Future work must not invent any.

## Product Principles

1. **Prove the slot, don't promise it.** Wherever a slot is proposed, the interface says whether it is
   still free; a ✓ or ⚠ beside a proposed time is product truth, not decoration.
2. **The whole span, or none of it.** Any half-move is a bug, and the interface must never make a
   partial move look possible.
3. **One occurrence, one truth.** A cancellation, a request, an approval and a student's timetable
   are four views of the same occurrence; if they disagree, the product is lying.
4. **The demo must be legible, the task must be fast** *(from the user's answer: built as if real,
   judged as a demonstration)*. Show the mechanism where it is being explained — inside the pop-up —
   and keep it out of the way of the work.
5. **Read-only means read-only.** Student surfaces never grow an action.

## Accessibility & Inclusion

**Not a project goal.** This is a coursework system built for a viva demonstration; developing for
people with disabilities — screen-reader support, keyboard-only audits, assistive-technology testing,
a formal WCAG conformance pass — is **out of scope** and must not be claimed in this repository.

Three practical rules are kept anyway, because they make the tool work on the cheap laptops and
phones the faculty actually uses, not as a disability programme:

- **48 × 48 minimum touch targets** on capability-detected touch devices;
- **visible keyboard focus**, and no hover-dependent affordance on a touch screen;
- **both themes readable**, light and dark, because the app ships both.

**WCAG 2.2 AA is named as the yardstick** those three are checked against — a measuring stick, never
a conformance claim. If a future task extends them, it extends them for usability first.
