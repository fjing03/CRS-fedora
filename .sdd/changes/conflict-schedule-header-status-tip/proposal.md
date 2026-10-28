# Proposal — conflict-schedule-header-status-tip

## The user's ruling (2026-10-28, verbatim intent)

1. **Move the hover tooltip**: the tooltip now rides the **Conflict
   Schedule header** (`.cs-head`) — hovering the header shows the status
   warning (holiday e.g. `Christmas Day holiday — no class`).
2. **Cancelled class status in the SAME tooltip**: for cancelled
   occurrences show `⚠️ Class Cancelled — {reason label}`
   (e.g. `⚠️ Class Cancelled — Annual Leave`).
3. "Ensure the tooltip content matches the actual class status and
   does not affect existing layout or functionality."

This SUPERSEDES the `holiday-occurrence-badge` placement ruling (the
user's own later instruction — the occurrence span keeps NO tip; the
header carries the status-only tip). The strip layout stays
byte-identical (attribute-only change).

## Root/shape of the data

- The status lives in the arrangement's conflict events payload
  (`conflictEventsPayload`): `{status: 'cancelled' | 'conflict'}` per
  occurrence — but the CANCELLED events carry no reason yet.
- The label law ALREADY exists and is THE shared one:
  `App\Support\ReasonLabel::from(enum)` (`'medical_leave'` →
  `Medical/Sick Leave`, `annual_leave` → `Annual Leave`) — the
  promote-on-3rd receipt (home badge + timetable `cancelledReason`).
- The holiday literal law is the applied
  `holiday-occurrence-badge` literal (`· {label}[ holiday] — no class`,
  first (week, dayIndex) match, the ` holiday` suffix only when absent).
- Precedence = the payload's own: **cancelled (exception) first, then
  holiday** — the same order `conflictEventsPayload` grants statuses,
  mirrored client-side.
- Neither → no tip (the content always MATCHES the actual status; no
  status, no tooltip).

## The change

1. **Server (additive key)**: `conflictEventsPayload`'s `$events[]`
   event array gains
   `'cancelledReason' => $exception !== null ? ReasonLabel::from((string) $exception->reason) : null`.
   Pure additive; the home/timetable consumers untouched (their own
   payloads already carry the label via the same helper).
2. **Client**: the strip render (`renderTitleSummary`) resolves the
   anchor occurrence's status from `MockData.cohortTimetable.events`:
   `week + sessionId + dayIndex + start` must ALL match (the
   0-based week bridge rides both sides). `cancelled` → the ⚠️ tip with
   the payload's reason; else the holiday literal via the existing
   lookup; else none. The conditional `data-tip` attr moves to the
   `.cs-head` div in the template; the occurrence span returns to
   attribute-less (the move).
3. **`escHtml`** stays the escaper; the falsifiable receipts:
   - cancelled anchor (cs 23/W8 `medical_leave` →
     `⚠️ Class Cancelled — Medical/Sick Leave`),
   - holiday anchor (cs 40/W14 Fri → `· Christmas Day holiday — no
     class`),
   - normal anchor (cs 40/W7) → NO tip,
   - the visible `textContent` unchanged pre/post (attribute-only).

## Behaviour table (the frozen contract)

| Anchor occurrence status | `.cs-head` tooltip |
|---|---|
| Cancelled (class exception) | `⚠️ Class Cancelled — {ReasonLabel}` |
| Holiday day | `· {Holiday label}[ holiday] — no class` (the frozen literal) |
| Normal occurrence | none |
| Occurrence line (`.cs-val`) | none (the move — the tooltip-ONLY scope is the header now) |

## Out of scope

- The `other_detail` audit-log dig (an `other` reason renders 'Other'
  per the current law) — not worth the join; recorded honestly.
- The class-detail modal / the grid tooltips / the pickers — untouched.
- No layout, no styling, no copy on the visible strip.
