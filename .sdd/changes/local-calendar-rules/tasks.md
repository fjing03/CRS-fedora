# tasks — `local-calendar-rules` (2026-11-08)

- [x] T1 — audit every `today()` / `now()->toDateString()` use; separate RULE calendar reads from
      stored instants and from `WorkingHours` (already campus).
- [x] T2 — `LocalTime::today()` / `dateString()` / `daysSince()` + PHPDoc.
- [x] T3 — mapper (Completed boundary, day-ago label), timeline (today flags, landing week),
      `Semester::active()`, the page payloads (urgency reference + fallbacks), the approval mail date.
- [x] T4 — frozen-clock tests at **Mon 9 Nov 2026 00:30 MYT** (= Sun 8 Nov 16:30 UTC): 6 cases,
      including the staff-`WorkingHours`-unchanged proof.
- [x] T5 — broad suite (168 tests) + pint + phpstan.
- [x] T6 — browser sanity: staff pages (replacements / arrangement / approval / venue / cohort) and
      the student timetable load with zero console errors.
