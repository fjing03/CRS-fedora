# proposal — `local-calendar-rules` (option D, 2026-11-08)

**Approval-gated plan. No code written yet.**

## The correction that shrinks this change

My first S2 note said the booking rules were 8 hours loose. **That was wrong**, and I checked:

`App\Support\WorkingHours` already converts every instant to `Asia/Kuala_Lumpur` before it reads a
calendar (`at()` → `setTimezone(LocalTime::TZ)`), so **the 3-working-day notice (FR 2.17), the
3-working-hour auto-reject, the holidays and the working-minute maths are already correct**. Same for
`SubmitReplacementRequest`, `AutoRejectRequest` and `PendingRequestExpiry` — they move instants, not
calendar dates.

So option D is **not** a staff-booking change. It is a handful of *calendar reads* that still use the
UTC date.

## What is actually wrong (the whole list)

| # | Site | What it asks | Today's answer (00:00–08:00 KL) |
|---|---|---|---|
| 1 | `ReplacementRequestMapper::effectiveStatus()` :105 | "is the class date before today?" → Completed vs Approved | Today's answer is **yesterday** → a class that happened today keeps reading *Approved* until 08:00 |
| 2 | `ReplacementRequestMapper::sinceLabel()` :254 | "today / 1 day ago / n days ago" | Day counts off by one in that window |
| 3 | `ResolvesTimetableTimeline::currentTimelineWeekIndex()` :132 | which week to land on | **one week low early Monday** → the page opens on last week |
| 4 | `ResolvesTimetableTimeline::weekData()` :99 | which day column is "today" (server payload) | yesterday's column carries the flag |
| 5 | `RequestApproval` :42 `urgencyReferenceDate` | the client's reference date for urgency labels | one day behind |
| 6 | fallback dates: `ResolvesTimetableTimeline` :55/:98/:172, `RequestApproval` :180, `ReplacementHome` :59/:362, `ReplacementArrangement` :455, `ApproveRequest` :321/:354 | `now()->toDateString()` **only when the semester is missing** | never runs in practice; switch for consistency |

## The change

1. **One helper addition** — extend the existing `App\Support\LocalTime`:
   ```php
   public static function today(): Carbon        // Carbon::now(LocalTime::TZ)->startOfDay()
   public static function dateString(): string   // the same, as 'Y-m-d'
   ```
   (Same instant, campus calendar — the trick that keeps instant maths correct.)
2. **Swap only the calendar reads above** — `today()` → `LocalTime::today()`, and
   `now()->toDateString()` → `LocalTime::dateString()`.

## What is deliberately NOT touched

- **Every stored timestamp** (`submitted_at`, `decided_at`, `created_at`, audit stamps) stays UTC.
  Changing them would corrupt history — this is the risk B/C carried.
- `WorkingHours` (already KL), `LocalTime::stamp()` display conversion, relative ages
  ("2 hours ago" — UTC to UTC is already right), the session-lifetime middleware, the 403 page,
  and every route/permission.
- `config/app.php`'s `'timezone' => 'UTC'` stays — the file's own comment says moving it would shift
  `now()`, every stored row and the whole suite.

## Test plan

- Freeze the clock at **00:30 KL on a Monday** (`Carbon::setTestNow`) and pin:
  a class dated that day is *Completed*, the landing week is the current one, `sinceLabel` says
  *today*, `urgencyReferenceDate` is that day.
- Re-run with a normal mid-day clock: every existing expectation must be unchanged.
- Existing suites to keep green: `WorkingHoursTest`, `SubmitGatesTest`, `AutoRejectPendingRequestsTest`,
  `MyReplacementsPayloadTest`, `ReplacementHomeTest`, `ReplacementHistoriesTest`,
  `TimetableSeedInvariantsTest`, plus pint + phpstan.

## Risk

- **Staff side:** no rule changes for booking or approval (already KL). The visible differences are
  "Completed" flipping at the right moment, the landing week being right early Monday, and day-ago
  labels being right — all in the window where they are wrong today.
- **Code risk:** small and local (one helper method + six call sites + fallbacks), no data change, no
  migration, no new dependency. Rollback = revert one commit.

## Order (when approved)

1. `LocalTime::today()` / `dateString()` + unit test.
2. Sites 1–2 (mapper), then 3–4 (timeline), then 5–6 (page payloads).
3. Frozen-clock tests, full focused suites, pint, phpstan.
4. Changelog + ai-context note; one commit (`fix(time): the calendar rules ask the campus clock`).

---

**APPLIED 2026-11-08** — see `design.md`, `tasks.md`, `review-log.md`.
