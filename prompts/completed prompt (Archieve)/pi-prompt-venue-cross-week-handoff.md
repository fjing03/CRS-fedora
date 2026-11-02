# PI prompt — Venue → Arrangement cross-week handoff pins the WRONG occurrence

**What this is:** paste-ready prompt for the PI chat session (that AI cannot see images — everything is
described in words/URLs). It reports the bug + my diagnosis, asks the PI to **explore & verify
independently first**, then give its **honest opinion**.

**Hard rule for the PI:** explore + verify + opine ONLY — **no edits, no commits** until the user
explicitly approves (reference: the last unapproved commit `5dfc8d1` — this time explore means look only).

**Status:** awaiting PI verification + honest opinion → then user decision on the fix.

---

Copy everything below this line:

````markdown
# Cross-week handoff bug — venue-timetable → replacement-arrangement pins the wrong occurrence
(Diagnosis report + verification request. EXPLORE & VERIFY FIRST — then give your honest opinion.
 **DO NOT APPLY ANY FIX. No edits. No commits.** I approve or veto explicitly afterwards.)

## Context
Repo: TARUMT Class Replacement System (Laravel 12 + Livewire + Blade, branch `fedora-backend`).
Two pages involved:
- Producer: `http://127.0.0.1:8000/venue-timetable-ui?venue=B111` →
  `resources/views/livewire/venue-timetable.blade.php`
- Consumer: `http://127.0.0.1:8000/replacement-arrangement?...&from=venue-timetable` →
  `app/Livewire/ReplacementArrangement.php` + `resources/views/livewire/replacement-arrangement.blade.php`

## The bug (exact repro, real data)
1. Log in as staff 5770 (En. Lim Jia Zheng). Open the venue page at **B111**.
2. Click a free slot: **Tue, 03 Nov 2026 at 13:00** (Week 7 of the semester).
3. The "Arrange a replacement in B111" modal opens → the session dropdown lists the lecturer's
   CONFLICTED occurrences. Pick the row: **`(L) W8, Mon, Nov 09 . 11:00 ~ 13:00`** (AMCS2093).
4. Click **Arrange** → redirects to:
   `.../replacement-arrangement?venue=B111&date=03%20Nov%202026&time=13%3A00&from=venue-timetable&code=AMCS2093&session=97&cohort=DFT2%20(S1)%20G1&week=7`
5. **BUG:** the pinned "Conflict Schedule" strip shows
   **"AMCS2093 — Operating Systems (L) · Week 7 · Mon, Nov 02 2026 · 11:00 AM–1:00 PM @ B110"** —
   the **W7 occurrence**, but the user picked the **W8** occurrence (Mon, Nov 09, 11:00–13:00).

**Worst part — it is not just cosmetic:** `window.pinnedOccurrence` is what `submitBlocks`
(replacement-arrangement.blade.php :1987–1994) sends as the ORIGINAL occurrence. So pressing submit
would file the replacement request against the **wrong occurrence** (W7 instead of the picked W8).

## My diagnosis (please verify independently — do not take it on trust)
The URL param `week` does double duty (slot week + occurrence week) and the design assumed they are
always the same week. This flow is CROSS-week (slot in W7, occurrence in W8), so the conflation breaks.

**Producer side (`venue-timetable.blade.php`):**
- :484 — picker rows DO carry the occurrence week: `item.dataset.week = String(occ.week || '')` → `'8'`
- :442 (`chooseSession`) — stores only `{ sessionId, cohortLabel }` → **week dropped**
- :553–562 (`confirmIntentSession`) — passes sid + cohort only
- :580 (`bookVenueWithSession`) — hardcodes `'&week=' + encodeURIComponent(currentWeek + 1)` → the
  venue page's VIEWED week (= the slot's week = **7**), not the picked occurrence's week (**8**)

**Consumer side (`replacement-arrangement`):**
- PHP `pinnedSessionPayload` (:181–204) pins `(session_id=97, week=<URL week>=7)` →
  `PAGE_DATA.pinnedSession`
- Blade :3446–3448 hydrates `window.pinnedOccurrence = { sessionId: 97, week: 7 }`
- :3500/:3512 boot jumps the grid to the pin's week
- `resolveBookingIntent` (:2642–2654) finds `date=03 Nov 2026` in W7 → jumps the grid to the SLOT's
  week and builds the booking intent (by design: "if the booked date lives in another week, jump to it")
- :2590 ("2c — the pin bridge") RE-PINS on the auto-select fallback match:
  `window.pinnedOccurrence = { sessionId: matched.sessionId, week: i.weekIndex + 1 }` —
  `i.weekIndex` = the booked SLOT's week → **week 7 again**
- Net: every path converges on week 7 → strip shows W7 · Mon Nov 02 @ B110 → submit targets W7.

**Why it never showed before:** same-week flows make the conflation invisible (slot week ==
occurrence week). The occurrence-week-pin-truthfulness run covered cohort→arrangement, NOT this
venue→arrangement cross-week path.

## Proposed minimal fix (PROPOSED ONLY — verify it, then opine; do NOT apply)
**Producer — `venue-timetable.blade.php`:**
1. `confirmIntentSession` (:562) — also read `pickedRow.dataset.week` and pass it along
2. `bookVenueWithSession` (:565/:580) — new param `occurrenceWeek`;
   `'&week=' + (occurrenceWeek ? occurrenceWeek : currentWeek + 1)`
   (rows without an occurrence week — the legacy fallback row at :469 has `week: ''` — keep today's behaviour)

**Consumer — `replacement-arrangement.blade.php`:**
3. :2590 — stop clobbering a server pin of the same session:
   `week: (window.pinnedOccurrence && Number(matched.sessionId) === window.pinnedOccurrence.sessionId)
    ? window.pinnedOccurrence.week : i.weekIndex + 1`

**Deliberately unchanged:** the arrival grid still shows the SLOT's week (so the booked block
preselects/auto-selects exactly as today); only the pinned OCCURRENCE (strip + submit payload) follows
the picked W8 row.

## Please explore & verify (before your opinion)
1. The repro + the code chain above — line by line. Is any step wrong or missing?
2. The **data facts**: session 97 = AMCS2093 (L), lecturer 5770 — does it really have conflicted
   occurrences at W7 (Mon 02 Nov, @ B110) AND W8 (Mon 09 Nov)? What venue does the W8 occurrence sit
   in? (demo DB `class_replacement_fresh`, read-only queries only — the demo DB is never re-seeded)
3. **Product-semantics question I could not answer alone:** is a replacement SLOT in an EARLIER week
   than the occurrence it replaces (slot W7 replacing a W8 class) actually VALID per the FR engine /
   slot state machine — or should the venue page never have offered that pairing in the first place?
   If it is invalid, the right fix might be producer-side validation, not (only) week-threading.
4. Alternatives: is there a cleaner way to carry the occurrence week than threading it through the URL
   (e.g. a separate `&occWeek=` param instead of overloading `week`, or sessionStorage)? Weigh in.
5. Blast radius of the proposed 3 edits: same-week handoffs should be byte-identical
   (occurrence week == currentWeek + 1 → same URL). Cohort handoffs carry no `date` param
   (`resolveBookingIntent` early-returns). Replacement-home pins (:3215, :2966) are different lines.
   Verify none of that breaks.

## What I want back
Your honest opinion on: (a) is my root-cause chain correct? (b) is the proposed fix right, or is
there a better/safer shape (see question 3–4)? (c) any blast-radius landmines I missed?

## HARD CONSTRAINT — do NOT fix yet
Explore, verify, opine. Do NOT edit any file, do NOT run any fix, do NOT commit — even if you fully
agree. I will explicitly approve or veto the fix afterwards. (Ref: the `5dfc8d1` incident — "explore"
means look only.)
````
