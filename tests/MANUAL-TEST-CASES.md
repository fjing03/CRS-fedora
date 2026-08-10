# Manual Test Cases — TARUMT Class Replacement System

> **Pre-requisite:** `php artisan migrate:fresh --seed` then `php artisan serve --port=8000`
> **Base URL:** `http://127.0.0.1:8000`

---

## TC-01: Welcome Page

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/` | Page loads with heading "TARUMT Class Replacement System" |
| 2 | Check browser console (F12) | No JS errors |

---

## TC-02: Student Login Page

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/login/student` | Login form renders |
| 2 | Check browser console | No JS errors |

---

## TC-03: Staff Login Page

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/login/staff` | Login form renders |
| 2 | Check browser console | No JS errors |

---

## TC-04: My Timetable (Lecturer View)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/my-timetable-ui` | Page loads, semester chip shows "202605 Semester · 31-Aug-2026 ~ 06-Dec-2026" |
| 2 | Check week subtitle | Shows current week label (e.g. "Week 11 · 09 Nov 2025 ~ 15 Nov 2025") |
| 3 | Check timetable grid | coloured blocks appear for seeded sessions (e.g. BMIT9012 on Tuesday) |
| 4 | Click left/right week arrows | Week changes, grid updates |
| 5 | Click a session block | Modal opens showing: course code, name, lecturer, venue, cohort, time, status |
| 6 | Press Escape or click outside modal | Modal closes |
| 7 | Check browser console | No JS errors, no 4xx/5xx network errors |

---

## TC-05: Cohort Timetable (Faculty Picker)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/cohort-timetable-ui` | Page loads, semester chip and week selector visible |
| 2 | Check Faculty dropdown | Populated with faculties (e.g. "FOCS", "FIE") |
| 3 | Select a faculty | Cohort dropdown populates with that faculty's cohorts |
| 4 | Select a cohort | Timetable grid renders with that cohort's sessions |
| 5 | Check RSD3(S1)G2 reconstruction | RSD3G2 base events appear on the grid |
| 6 | Change week | Grid updates to show selected week |
| 7 | Check browser console | No JS errors |

---

## TC-06: Student My Timetable

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/student-my-timetable-ui` | Page loads |
| 2 | Check semester chip | Shows semester info |
| 3 | Check timetable grid | Shows base sessions from demo student's cohort (e.g. BMIT7070, BMIT9012) |
| 4 | Click left/right week arrows | Week changes |
| 5 | Check "cancelled" sessions | If any class_exceptions exist, those sessions show cancelled styling |
| 6 | Check notification badge | Shows count of pending replacement requests (if any) |
| 7 | Check browser console | No JS errors |

---

## TC-07: My Request History

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/my-request-history-ui` | Page loads, search bar and result count visible |
| 2 | Check request cards | At least 3 cards appear (from ReplacementRequestsSeeder) |
| 3 | Check card fields | Each card shows: course code, class date/time, venue, status badge (Pending/Approved/Rejected) |
| 4 | Check status badges | Pending = yellow/orange, Approved = green, Rejected = red |
| 5 | Type "BMIT" in search bar | Cards filter to show only matching entries |
| 6 | Clear search bar | All cards reappear |
| 7 | Check result count text | Updates correctly (e.g. "Showing 3 of 3 requests") |
| 8 | Check browser console | No JS errors |

---

## TC-08: Replacement Home (Conflict Classes)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/replacement-home-ui` | Page loads, search bar and card view visible |
| 2 | Check conflict cards | At least 1 card appears (from class_exceptions seeder) |
| 3 | Check card fields | Each card shows: course code, name, type, date, day, time, venue, conflict reason badge |
| 4 | Check conflict reason badge | Shows correct label: "Public Holiday", "Annual Leave", "Medical Leave", "Official Event", or "Emergency Leave" |
| 5 | Type "BMIT" in search | Cards filter |
| 6 | Clear search | All cards reappear |
| 7 | Check browser console | No JS errors |

---

## TC-09: Replacement Arrangement (Venue Slot Grid)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/replacement-arrangement` | Page loads, venue selector and week grid visible |
| 2 | Check venue dropdown | Populated with venues (e.g. B103, B104, B107...) |
| 3 | Check week tabs | Shows 3 weeks (e.g. "Week 11", "Week 10", "Week 9") |
| 4 | Check grid | 6 days × 22 half-hour slots (08:00–18:30) |
| 5 | Check cell colours | Green = available, Grey = occupied, Yellow = pending, Blue = approved |
| 6 | Select a different venue | Grid updates to show that venue's slots |
| 7 | Click a green cell | Cell highlights as selected |
| 8 | Click another green cell | Selection count increases (e.g. "Selected 2 of 4 slots") |
| 9 | Click a selected cell again | Cell deselects |
| 10 | Check progress bar | Updates with selection count |
| 11 | Check "Selected" summary | Shows selected slots list at bottom |
| 12 | Check browser console | No JS errors |

---

## TC-10: API Endpoints Direct Check

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open `http://127.0.0.1:8000/api/v1/semester` | Returns JSON with `semester.chipText` and `holidays` array |
| 2 | Open `http://127.0.0.1:8000/api/v1/timetable/my` | Returns JSON with `myTimetable.seedWeek: 11` and `eventsByWeek` keys 0–13 |
| 3 | Open `http://127.0.0.1:8000/api/v1/timetable/cohort?cohort_id=dft1s1g1` | Returns JSON with `cohortTimetable.faculties`, `.events`, `.rsd3g2Base` |
| 4 | Open `http://127.0.0.1:8000/api/v1/timetable/student` | Returns JSON with `studentTimetable.activeCohort` and `cohortTimetable.rsd3g2Base` |
| 5 | Open `http://127.0.0.1:8000/api/v1/requests/my` | Returns JSON with `requests` array, ≥3 items, title-case statuses |
| 6 | Open `http://127.0.0.1:8000/api/v1/requests/conflicts` | Returns JSON with `conflictedClasses` array, valid `conflictReason` values |
| 7 | Open `http://127.0.0.1:8000/api/v1/arrangement/slots` | Returns JSON with `venueSlots`, `arrangementWeeks` (3 weeks), `venues` |
| 8 | Open `http://127.0.0.1:8000/api/v1/meta/cohorts` | Returns JSON with `cohorts` array, ≥1 item |

---

## TC-11: API Fallback to Mock

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open browser DevTools → Network tab | — |
| 2 | Open `http://127.0.0.1:8000/my-timetable-ui` | API calls to `/api/v1/semester` and `/api/v1/timetable/my` return 200 |
| 3 | Now stop the API (or block `/api/v1/*` in DevTools) | — |
| 4 | Reload `http://127.0.0.1:8000/my-timetable-ui` | Page still renders from bundled mock data |
| 5 | Check console | Warning: `[api] fallback to mock:` appears |
| 6 | Re-enable API, reload | Page now uses real API data |

---

## TC-12: Cross-Page Navigation

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | From any page, check nav bar links | All page links are visible |
| 2 | Click "My Timetable" link | Navigates to `/my-timetable-ui` |
| 3 | Click "Cohort Timetable" link | Navigates to `/cohort-timetable-ui` |
| 4 | Click "Student Timetable" link | Navigates to `/student-my-timetable-ui` |
| 5 | Click "Request History" link | Navigates to `/my-request-history-ui` |
| 6 | Click "Replacement Home" link | Navigates to `/replacement-home-ui` |
| 7 | Click "Arrangement" link | Navigates to `/replacement-arrangement` |
| 8 | Each page loads without errors | No JS errors, no blank pages |

---

## TC-13: Responsive / Mobile Check

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open any timetable page | — |
| 2 | Resize browser to mobile width (≤768px) | Layout adjusts, nav collapses to hamburger |
| 3 | Click hamburger menu | Nav menu opens |
| 4 | Click a nav link | Navigates to correct page |
| 5 | Check timetable grid | Scrolls horizontally, cells are readable |

---

## TC-14: Dark Mode Toggle

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open any page | — |
| 2 | Click dark mode toggle (moon/sun icon in nav) | Theme switches to dark |
| 3 | Check background and text colours | Dark background, light text |
| 4 | Refresh page | Theme persists (localStorage) |
| 5 | Toggle back to light | Theme switches to light |

---

## Notes

- **Demo data:** lecturer 5425 (Pn. Surayaini) has 4 sessions, 3 replacement requests (pending/approved/rejected), 1 class exception
- **Demo student:** first student belongs to cohort DFT1(S1)G1
- **Semester:** 202605 Semester, 14 weeks, starts 31-Aug-2026, ends 06-Dec-2026
- **If pages show blank:** run `php artisan migrate:fresh --seed` to reset demo data
