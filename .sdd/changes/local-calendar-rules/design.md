# design — `local-calendar-rules` (2026-11-08)

## D1 — one helper, same instant

`App\Support\LocalTime` gains:

```php
LocalTime::today(): Carbon        // Carbon::now('Asia/Kuala_Lumpur')->startOfDay()
LocalTime::dateString(): string   // that date as 'Y-m-d'
LocalTime::daysSince(CarbonInterface|string $date): int   // whole campus days, past-positive
```

Same instant, campus zone — so comparisons against stored UTC instants stay correct; only the
*calendar* reading changes. `daysSince()` parses both sides as campus midnights, so an 8-hour
offset can never move a day count.

## D2 — the sites that changed (and only those)

| File | Line (before) | Change |
|---|---|---|
| `app/Services/ReplacementRequestMapper.php` | `effectiveStatus()` :105 | date-string compare: `Carbon::parse($date)->toDateString() < LocalTime::dateString()` |
| " | `sinceLabel()` :254 | `abs(LocalTime::daysSince($date))` |
| `app/Concerns/ResolvesTimetableTimeline.php` | `weekData()` :99 | `LocalTime::dateString()` |
| " | `currentTimelineWeekIndex()` :132 | `LocalTime::daysSince($semester->start_date)` |
| " | :55/:98/:172 fallbacks | `LocalTime::today()` |
| `app/Models/Semester.php` | `active()` :44 | `whereDate('start_date', '<=', LocalTime::dateString())` |
| `app/Livewire/RequestApproval.php` | :42 `urgencyReferenceDate` | `LocalTime::dateString()` (+ :180 fallback) |
| `app/Livewire/ReplacementHome.php` | :59/:362 fallbacks | `LocalTime::today()` |
| `app/Livewire/ReplacementArrangement.php` | :455 fallback | `LocalTime::today()` |
| `app/Actions/ApproveRequest.php` | :321/:354 mail date base | `LocalTime::today()` |
| `app/Services/LectureSessionCatalog.php` | :633 fallback | `LocalTime::today()` |

## D3 — deliberately untouched

- **Every stored timestamp** (`submitted_at`, `decided_at`, `created_at`, audit rows) stays UTC.
- **`App\Support\WorkingHours`** — it already converts every instant to campus time before reading
  a calendar (`at()`), so FR 2.17's 3 working days, the 3-working-hour auto-reject, holidays and the
  working-minute maths are unchanged. The user's requirement ("follow the working hours / working
  days rule as the staff side") is exactly this: no edit, and a test proves the same answers.
- `LocalTime::stamp()`, relative ages, `config('app.timezone') = 'UTC'`, session lifetime, 403 page,
  routes and permissions.

## D4 — why string compare for the Completed boundary

`Carbon::parse('Y-m-d')` is midnight in the APP zone (UTC) while `LocalTime::today()` is midnight in
MYT — comparing those instants is an 8-hour-shifted comparison that happens to give the right answer.
Comparing `Y-m-d` strings removes the trap entirely: no midnight, no offset, no off-by-one.
