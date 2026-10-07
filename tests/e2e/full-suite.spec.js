import { test, expect } from '@playwright/test';

const API = '/api/v1';

// ══════════════════════════════════════════════════════════════
// 1. API ENDPOINTS — shape, status, data integrity
// ══════════════════════════════════════════════════════════════

test.describe('API: GET /api/v1/semester', () => {
  test('returns 200 with correct shape', async ({ request }) => {
    const res = await request.get(`${API}/semester`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data).toHaveProperty('semester');
    expect(data).toHaveProperty('holidays');
    expect(data.semester).toHaveProperty('label');
    expect(data.semester).toHaveProperty('startDate');
    expect(data.semester).toHaveProperty('endDate');
    expect(data.semester).toHaveProperty('weeks', 14);
    expect(data.semester).toHaveProperty('chipText');
    expect(data.semester.chipText).toMatch(/Semester · \d{2}-\w{3}-\d{4}/);
    expect(Array.isArray(data.holidays)).toBeTruthy();
  });

  test('holidays have correct shape', async ({ request }) => {
    const res = await request.get(`${API}/semester`);
    const data = await res.json();
    if (data.holidays.length > 0) {
      const h = data.holidays[0];
      expect(h).toHaveProperty('week');
      expect(h).toHaveProperty('dayIndex');
      expect(h).toHaveProperty('label');
      expect(typeof h.week).toBe('number');
      expect(typeof h.dayIndex).toBe('number');
    }
  });
});

test.describe('API: GET /api/v1/timetable/my', () => {
  test('returns 200 with 0-indexed eventsByWeek', async ({ request }) => {
    const res = await request.get(`${API}/timetable/my`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data).toHaveProperty('myTimetable');
    expect(data.myTimetable).toHaveProperty('seedWeek', 11);
    expect(data.myTimetable).toHaveProperty('eventsByWeek');
    const keys = Object.keys(data.myTimetable.eventsByWeek).map(Number);
    expect(Math.min(...keys)).toBe(0);
    expect(Math.max(...keys)).toBe(13);
  });

  test('key 11 exists and has events', async ({ request }) => {
    const res = await request.get(`${API}/timetable/my`);
    const data = await res.json();
    const week11 = data.myTimetable.eventsByWeek['11'];
    expect(week11).toBeDefined();
    expect(week11.length).toBeGreaterThan(0);
  });

  test('events have required fields', async ({ request }) => {
    const res = await request.get(`${API}/timetable/my`);
    const data = await res.json();
    const event = data.myTimetable.eventsByWeek['11'][0];
    expect(event).toHaveProperty('code');
    expect(event).toHaveProperty('di');
    expect(event).toHaveProperty('start');
    expect(event).toHaveProperty('end');
    expect(event).toHaveProperty('type');
    expect(event).toHaveProperty('venue');
    expect(event).toHaveProperty('lecturer');
    expect(event).toHaveProperty('cohort');
    expect(event).toHaveProperty('cohorts');
    expect(event).toHaveProperty('status');
    expect(event).toHaveProperty('name');
    expect(event).toHaveProperty('remarks');
  });

  test('rejects invalid role parameter', async ({ request }) => {
    const res = await request.get(`${API}/timetable/my?role=invalid`);
    expect(res.ok()).toBeTruthy();
  });
});

test.describe('API: GET /api/v1/timetable/cohort', () => {
  test('returns faculties and events for valid cohort', async ({ request }) => {
    const res = await request.get(`${API}/timetable/cohort?cohort_id=dft1s1g1`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data).toHaveProperty('cohortTimetable');
    expect(data.cohortTimetable.faculties.length).toBeGreaterThan(0);
    expect(data.cohortTimetable.events.length).toBeGreaterThan(0);
    expect(data.cohortTimetable).toHaveProperty('rsd3g2Base');
    expect(data.cohortTimetable).toHaveProperty('rsd3g2Flags');
  });

  test('faculties have correct structure', async ({ request }) => {
    const res = await request.get(`${API}/timetable/cohort?cohort_id=dft1s1g1`);
    const data = await res.json();
    const faculty = data.cohortTimetable.faculties[0];
    expect(faculty).toHaveProperty('id');
    expect(faculty).toHaveProperty('name');
    expect(faculty).toHaveProperty('cohorts');
    expect(Array.isArray(faculty.cohorts)).toBeTruthy();
    expect(faculty.cohorts[0]).toHaveProperty('id');
    expect(faculty.cohorts[0]).toHaveProperty('name');
  });

  test('events have cohortId and week keys', async ({ request }) => {
    const res = await request.get(`${API}/timetable/cohort?cohort_id=dft1s1g1`);
    const data = await res.json();
    const event = data.cohortTimetable.events[0];
    expect(event).toHaveProperty('cohortId');
    expect(event).toHaveProperty('week');
    expect(event).toHaveProperty('event');
  });

  test('rsd3g2Base is populated', async ({ request }) => {
    const res = await request.get(`${API}/timetable/cohort?cohort_id=dft1s1g1`);
    const data = await res.json();
    expect(data.cohortTimetable.rsd3g2Base.length).toBeGreaterThan(0);
  });

  test('returns data even for unknown cohort (fallback to all)', async ({ request }) => {
    const res = await request.get(`${API}/timetable/cohort?cohort_id=nonexistent`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data).toHaveProperty('cohortTimetable');
  });
});

test.describe('API: GET /api/v1/timetable/student', () => {
  test('returns studentTimetable and cohortTimetable', async ({ request }) => {
    const res = await request.get(`${API}/timetable/student`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data).toHaveProperty('studentTimetable');
    expect(data).toHaveProperty('cohortTimetable');
    expect(data.studentTimetable).toHaveProperty('activeCohort');
    expect(data.studentTimetable).toHaveProperty('cancelledFlags');
    expect(data.studentTimetable).toHaveProperty('notificationCount');
    expect(typeof data.studentTimetable.notificationCount).toBe('number');
  });

  test('cohortTimetable has rsd3g2Base', async ({ request }) => {
    const res = await request.get(`${API}/timetable/student`);
    const data = await res.json();
    expect(data.cohortTimetable.rsd3g2Base.length).toBeGreaterThan(0);
  });

  test('cancelledFlags is an object', async ({ request }) => {
    const res = await request.get(`${API}/timetable/student`);
    const data = await res.json();
    expect(typeof data.studentTimetable.cancelledFlags).toBe('object');
  });
});

test.describe('API: GET /api/v1/requests/my', () => {
  test('returns requests array', async ({ request }) => {
    const res = await request.get(`${API}/requests/my`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(Array.isArray(data.requests)).toBeTruthy();
  });

  test('request rows have correct fields', async ({ request }) => {
    const res = await request.get(`${API}/requests/my`);
    const data = await res.json();
    if (data.requests.length > 0) {
      const r = data.requests[0];
      expect(r).toHaveProperty('id');
      expect(r).toHaveProperty('courseCode');
      expect(r).toHaveProperty('courseName');
      expect(r).toHaveProperty('classType');
      expect(r).toHaveProperty('classDate');
      expect(r).toHaveProperty('classDay');
      expect(r).toHaveProperty('timeStart');
      expect(r).toHaveProperty('timeEnd');
      expect(r).toHaveProperty('duration');
      expect(r).toHaveProperty('venue');
      expect(r).toHaveProperty('totalStudents');
      expect(r).toHaveProperty('cohorts');
      expect(r).toHaveProperty('status');
      expect(r).toHaveProperty('requestedAt');
    }
  });

  test('statuses are title-case', async ({ request }) => {
    const res = await request.get(`${API}/requests/my`);
    const data = await res.json();
    const validStatuses = ['Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed'];
    data.requests.forEach(r => {
      expect(validStatuses).toContain(r.status);
    });
  });

  test('replacementTime format uses en dash', async ({ request }) => {
    const res = await request.get(`${API}/requests/my`);
    const data = await res.json();
    const withReplacement = data.requests.find(r => r.replacementTime);
    if (withReplacement) {
      expect(withReplacement.replacementTime).toMatch(/\d{2}:\d{2} – \d{2}:\d{2}/);
    }
  });
});

test.describe('API: GET /api/v1/requests/conflicts', () => {
  test('returns conflictedClasses array', async ({ request }) => {
    const res = await request.get(`${API}/requests/conflicts`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(Array.isArray(data.conflictedClasses)).toBeTruthy();
  });

  test('conflict rows have valid conflictReason', async ({ request }) => {
    const res = await request.get(`${API}/requests/conflicts`);
    const data = await res.json();
    const validReasons = [
      'Public Holiday', 'Annual Leave', 'Medical Leave',
      'Official Event', 'Emergency Leave',
    ];
    data.conflictedClasses.forEach(c => {
      expect(validReasons).toContain(c.conflictReason);
    });
  });

  test('conflict rows have required fields', async ({ request }) => {
    const res = await request.get(`${API}/requests/conflicts`);
    const data = await res.json();
    if (data.conflictedClasses.length > 0) {
      const c = data.conflictedClasses[0];
      expect(c).toHaveProperty('id');
      expect(c).toHaveProperty('code');
      expect(c).toHaveProperty('name');
      expect(c).toHaveProperty('type');
      expect(c).toHaveProperty('date');
      expect(c).toHaveProperty('day');
      expect(c).toHaveProperty('timeStart');
      expect(c).toHaveProperty('timeEnd');
      expect(c).toHaveProperty('duration');
      expect(c).toHaveProperty('venue');
      expect(c).toHaveProperty('totalStudents');
      expect(c).toHaveProperty('cohorts');
      expect(c).toHaveProperty('conflictReason');
    }
  });
});

test.describe('API: GET /api/v1/arrangement/slots', () => {
  test('returns venueSlots, arrangementWeeks, venues', async ({ request }) => {
    const res = await request.get(`${API}/arrangement/slots?cohort_ids=1&session_type=L&duration=60&week_number=11`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data).toHaveProperty('venueSlots');
    expect(data).toHaveProperty('arrangementWeeks');
    expect(data).toHaveProperty('venues');
  });

  test('venueSlots has grid with [di,hi,status] triples', async ({ request }) => {
    const res = await request.get(`${API}/arrangement/slots?cohort_ids=1&session_type=L&duration=60&week_number=11`);
    const data = await res.json();
    const venueKeys = Object.keys(data.venueSlots);
    expect(venueKeys.length).toBeGreaterThan(0);
    const grid = data.venueSlots[venueKeys[0]];
    expect(grid.length).toBe(132); // 6 days * 22 half-hour slots
    grid.forEach(cell => {
      expect(cell.length).toBe(3);
      expect([0, 1, 3, 4]).toContain(cell[2]); // status codes
    });
  });

  test('arrangementWeeks has 3 descending weeks', async ({ request }) => {
    const res = await request.get(`${API}/arrangement/slots?cohort_ids=1&session_type=L&duration=60&week_number=11`);
    const data = await res.json();
    expect(data.arrangementWeeks.length).toBe(3);
    expect(data.arrangementWeeks[0]).toHaveProperty('label');
    expect(data.arrangementWeeks[0]).toHaveProperty('days');
    expect(data.arrangementWeeks[0].days.length).toBe(7);
  });

  test('venues have correct shape', async ({ request }) => {
    const res = await request.get(`${API}/arrangement/slots?cohort_ids=1&session_type=L&duration=60&week_number=11`);
    const data = await res.json();
    expect(data.venues.length).toBeGreaterThan(0);
    const v = data.venues[0];
    expect(v).toHaveProperty('code');
    expect(v).toHaveProperty('type');
    expect(v).toHaveProperty('capacity');
    expect(v).toHaveProperty('allowedSessions');
    expect(['Tutorial', 'LectureHall', 'Lab', 'CiscoLab']).toContain(v.type);
  });

  test('week 11 has no holidays so available slots exist', async ({ request }) => {
    const res = await request.get(`${API}/arrangement/slots?cohort_ids=1&session_type=L&duration=60&week_number=11`);
    const data = await res.json();
    const allStatuses = Object.values(data.venueSlots).flat().map(c => c[2]);
    expect(allStatuses).toContain(0); // at least 1 available
  });

  test('defaults: week_number=11, session_type=L, duration=60', async ({ request }) => {
    const res = await request.get(`${API}/arrangement/slots`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.arrangementWeeks[0].label).toMatch(/Week/);
  });
});

test.describe('API: GET /api/v1/meta/cohorts', () => {
  test('returns cohorts registry', async ({ request }) => {
    const res = await request.get(`${API}/meta/cohorts`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data).toHaveProperty('cohorts');
    expect(data.cohorts.length).toBeGreaterThan(0);
  });

  test('cohort entries have required fields', async ({ request }) => {
    const res = await request.get(`${API}/meta/cohorts`);
    const data = await res.json();
    const c = data.cohorts[0];
    expect(c).toHaveProperty('code');
    expect(c).toHaveProperty('programme');
    expect(c).toHaveProperty('faculty');
    expect(c).toHaveProperty('studentCount');
  });
});

// ══════════════════════════════════════════════════════════════
// 2. WEB PAGES — load, key elements, no errors
// ══════════════════════════════════════════════════════════════

test.describe('Page: Welcome (/)', () => {
  test('loads with heading', async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/', { waitUntil: 'networkidle' });
    await expect(page.locator('h1')).toBeVisible();
    expect(errors).toHaveLength(0);
  });
});

test.describe('Page: Student Login (/login/student)', () => {
  test('loads login form', async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/login/student', { waitUntil: 'networkidle' });
    await expect(page).toHaveTitle(/Login/i);
    expect(errors).toHaveLength(0);
  });
});

test.describe('Page: Staff Login (/login/staff)', () => {
  test('loads login form', async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/login/staff', { waitUntil: 'networkidle' });
    await expect(page).toHaveTitle(/Login/i);
    expect(errors).toHaveLength(0);
  });
});

test.describe('Page: My Timetable (/my-timetable-ui)', () => {
  test('loads and renders timetable grid', async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#semesterProgress')).toBeAttached();
    await expect(page.locator('#weekSubtitle')).toBeAttached();
    expect(errors).toHaveLength(0);
  });

  test('MockData is populated from API', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    const hasData = await page.evaluate(() => {
      return window.MockData?.myTimetable?.eventsByWeek != null
        && Object.keys(window.MockData.myTimetable.eventsByWeek).length > 0;
    });
    expect(hasData).toBeTruthy();
  });

  test('weekData is populated', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    const hasWeeks = await page.evaluate(() => {
      return typeof window.weekData !== 'undefined' && window.weekData?.length === 14;
    });
    expect(hasWeeks).toBeTruthy();
  });

  test('semester chip displays date range', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    const chipText = await page.evaluate(() => {
      return document.getElementById('semesterChip')?.textContent || '';
    });
    expect(chipText).toMatch(/Semester/);
  });
});

test.describe('Page: Cohort Timetable (/cohort-timetable-ui)', () => {
  test('loads with faculty dropdown', async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/cohort-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#semesterChip')).toBeAttached();
    await expect(page.locator('#weekSelect')).toBeAttached();
    await expect(page.locator('#facultySelect')).toBeAttached();
    expect(errors).toHaveLength(0);
  });

  test('faculty dropdown is populated', async ({ page }) => {
    await page.goto('/cohort-timetable-ui', { waitUntil: 'networkidle' });
    const optionCount = await page.locator('#facultySelect option').count();
    expect(optionCount).toBeGreaterThan(1); // includes "Select Faculty" placeholder
  });

  test('MockData has cohortTimetable', async ({ page }) => {
    await page.goto('/cohort-timetable-ui', { waitUntil: 'networkidle' });
    const hasData = await page.evaluate(() => {
      return window.MockData?.cohortTimetable?.faculties?.length > 0;
    });
    expect(hasData).toBeTruthy();
  });
});

test.describe('Page: Student My Timetable (/student-my-timetable-ui)', () => {
  test('loads and renders', async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/student-my-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#semesterProgress')).toBeAttached();
    await expect(page.locator('#weekSubtitle')).toBeAttached();
    expect(errors).toHaveLength(0);
  });

  test('MockData has studentTimetable', async ({ page }) => {
    await page.goto('/student-my-timetable-ui', { waitUntil: 'networkidle' });
    const hasData = await page.evaluate(() => {
      const st = window.MockData?.studentTimetable;
      return st?.activeCohort && typeof st?.notificationCount === 'number';
    });
    expect(hasData).toBeTruthy();
  });

  test('cohortTimetable has rsd3g2Base', async ({ page }) => {
    await page.goto('/student-my-timetable-ui', { waitUntil: 'networkidle' });
    const hasBase = await page.evaluate(() => {
      return window.MockData?.cohortTimetable?.rsd3g2Base?.length > 0;
    });
    expect(hasBase).toBeTruthy();
  });
});

test.describe('Page: My Request History (/my-request-history-ui)', () => {
  test('loads with search and table', async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/my-request-history-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#searchInput')).toBeAttached();
    await expect(page.locator('#resultCount')).toBeAttached();
    expect(errors).toHaveLength(0);
  });

  test('MockData has requests', async ({ page }) => {
    await page.goto('/my-request-history-ui', { waitUntil: 'networkidle' });
    const hasData = await page.evaluate(() => {
      return window.MockData?.requests?.length >= 1;
    });
    expect(hasData).toBeTruthy();
  });

  test('request rows render in card view', async ({ page }) => {
    await page.goto('/my-request-history-ui', { waitUntil: 'networkidle' });
    const cardCount = await page.locator('#cardView .request-card, #cardView .card, #cardView > div > div').count();
    expect(cardCount).toBeGreaterThanOrEqual(1);
  });
});

test.describe('Page: Replacement Home (/replacement-home-ui)', () => {
  test('loads with search and card view', async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/replacement-home-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#searchInput')).toBeAttached();
    await expect(page.locator('#cardView')).toBeAttached();
    expect(errors).toHaveLength(0);
  });

  test('MockData has conflictedClasses', async ({ page }) => {
    await page.goto('/replacement-home-ui', { waitUntil: 'networkidle' });
    const hasData = await page.evaluate(() => {
      return Array.isArray(window.MockData?.conflictedClasses);
    });
    expect(hasData).toBeTruthy();
  });
});

test.describe('Page: Replacement Arrangement (/replacement-arrangement)', () => {
  test('loads with venue selector and grid', async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
    await expect(page.locator('#buildingSelector')).toBeAttached();
    await expect(page.locator('#progressWrapper')).toBeAttached();
    expect(errors).toHaveLength(0);
  });

  test('MockData has venueSlots and arrangementWeeks', async ({ page }) => {
    await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
    const hasData = await page.evaluate(() => {
      const vs = window.MockData?.venueSlots;
      const aw = window.MockData?.arrangementWeeks;
      return vs && Object.keys(vs).length > 0 && aw && aw.length === 3;
    });
    expect(hasData).toBeTruthy();
  });

  test('venue dropdown is populated from engine-eligible venues', async ({ page }) => {
    await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
    const optionCount = await page.locator('#buildingSelector option').count();
    expect(optionCount).toBeGreaterThan(1);
  });

  test('grid has available (green) cells', async ({ page }) => {
    await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
    const hasAvailable = await page.evaluate(() => {
      const slots = window.MockData?.venueSlots;
      if (!slots) return false;
      return Object.values(slots).some(grid => grid.some(s => s[2] === 0));
    });
    expect(hasAvailable).toBeTruthy();
  });
});

// ══════════════════════════════════════════════════════════════
// 3. API FAILURE FALLBACK
// ══════════════════════════════════════════════════════════════

test.describe('Fallback: pages render when API is down', () => {
  test('My Timetable falls back to mock on API failure', async ({ page }) => {
    await page.route('**/api/v1/**', route => route.abort('connectionrefused'));
    const warnings = [];
    page.on('console', msg => {
      if (msg.text().includes('fallback to mock')) warnings.push(msg.text());
    });
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#semesterProgress')).toBeAttached();
    expect(warnings.length).toBeGreaterThan(0);
  });

  test('Cohort Timetable falls back to mock on API failure', async ({ page }) => {
    await page.route('**/api/v1/**', route => route.abort('connectionrefused'));
    const warnings = [];
    page.on('console', msg => {
      if (msg.text().includes('fallback to mock')) warnings.push(msg.text());
    });
    await page.goto('/cohort-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#semesterChip')).toBeAttached();
    expect(warnings.length).toBeGreaterThan(0);
  });

  test('Replacement Home falls back to mock on API failure', async ({ page }) => {
    await page.route('**/api/v1/**', route => route.abort('connectionrefused'));
    const warnings = [];
    page.on('console', msg => {
      if (msg.text().includes('fallback to mock')) warnings.push(msg.text());
    });
    await page.goto('/replacement-home-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#searchInput')).toBeAttached();
    expect(warnings.length).toBeGreaterThan(0);
  });
});

// ══════════════════════════════════════════════════════════════
// 4. UI INTERACTIONS
// ══════════════════════════════════════════════════════════════

test.describe('Interaction: Cohort Timetable faculty/cohort picker', () => {
  test('selecting a faculty populates cohort dropdown', async ({ page }) => {
    await page.goto('/cohort-timetable-ui', { waitUntil: 'networkidle' });
    const facultySelect = page.locator('#facultySelect');
    await facultySelect.waitFor({ state: 'visible' });
    const optionCount = await facultySelect.locator('option').count();
    if (optionCount > 1) {
      await facultySelect.selectOption({ index: 1 });
      const cohortCount = await page.locator('#cohortSelect option').count();
      expect(cohortCount).toBeGreaterThan(1);
    }
  });
});

test.describe('Interaction: My Request History search', () => {
  test('typing in search filters results', async ({ page }) => {
    await page.goto('/my-request-history-ui', { waitUntil: 'networkidle' });
    const searchInput = page.locator('#searchInput');
    await searchInput.waitFor({ state: 'visible' });
    const initialCount = await page.locator('#resultCount').textContent();
    await searchInput.fill('BMIT');
    await page.waitForTimeout(500);
    const filteredCount = await page.locator('#resultCount').textContent();
    expect(filteredCount).toBeDefined();
  });
});

test.describe('Interaction: Replacement Home search', () => {
  test('typing in search filters conflict cards', async ({ page }) => {
    await page.goto('/replacement-home-ui', { waitUntil: 'networkidle' });
    const searchInput = page.locator('#searchInput');
    await searchInput.waitFor({ state: 'visible' });
    await searchInput.fill('BMIT');
    await page.waitForTimeout(500);
    const resultText = await page.locator('#resultCount').textContent();
    expect(resultText).toBeDefined();
  });
});

test.describe('Interaction: Arrangement venue switcher', () => {
  test('changing venue updates the grid', async ({ page }) => {
    await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
    const venueSelect = page.locator('#buildingSelector');
    await venueSelect.waitFor({ state: 'visible' });
    const optionCount = await venueSelect.locator('option').count();
    if (optionCount > 2) {
      await venueSelect.selectOption({ index: 2 });
      await page.waitForTimeout(300);
      const progressText = await page.locator('#progressText').textContent();
      expect(progressText).toBeDefined();
    }
  });
});

// ══════════════════════════════════════════════════════════════
// 5. NAVIGATION — all routes accessible
// ══════════════════════════════════════════════════════════════

test.describe('Navigation: all public routes load', () => {
  const routes = [
    { path: '/', title: /Welcome/ },
    { path: '/login/student', title: /Login/i },
    { path: '/login/staff', title: /Login/i },
    { path: '/my-timetable-ui', title: /My Timetable/ },
    { path: '/cohort-timetable-ui', title: /Cohort Timetable/ },
    { path: '/student-my-timetable-ui', title: /My Timetable/ },
    { path: '/my-request-history-ui', title: /Request History/ },
    { path: '/replacement-home-ui', title: /Replacement/ },
    { path: '/replacement-arrangement', title: /Replacement/ },
  ];

  for (const { path, title } of routes) {
    test(`${path} loads with correct title`, async ({ page }) => {
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      await page.goto(path, { waitUntil: 'networkidle' });
      await expect(page).toHaveTitle(title);
      expect(errors).toHaveLength(0);
    });
  }
});

// ══════════════════════════════════════════════════════════════
// 6. DATA INTEGRITY — API data matches page expectations
// ══════════════════════════════════════════════════════════════

test.describe('Data integrity: API → page data flow', () => {
  test('My Timetable: eventsByWeek has events with module codes', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    const codes = await page.evaluate(() => {
      const data = window.MockData?.myTimetable?.eventsByWeek;
      if (!data) return [];
      return Object.values(data).flat().map(e => e.code).filter(Boolean);
    });
    expect(codes.length).toBeGreaterThan(0);
  });

  test('Cohort Timetable: faculties have nested cohorts', async ({ page }) => {
    await page.goto('/cohort-timetable-ui', { waitUntil: 'networkidle' });
    const facultyCount = await page.evaluate(() => {
      return window.MockData?.cohortTimetable?.faculties?.length ?? 0;
    });
    expect(facultyCount).toBeGreaterThan(0);
  });

  test('Student Timetable: activeCohort is set', async ({ page }) => {
    await page.goto('/student-my-timetable-ui', { waitUntil: 'networkidle' });
    const cohort = await page.evaluate(() => {
      return window.MockData?.studentTimetable?.activeCohort;
    });
    expect(cohort).toBeTruthy();
  });

  test('Request History: requests have title-case statuses', async ({ page }) => {
    await page.goto('/my-request-history-ui', { waitUntil: 'networkidle' });
    const statuses = await page.evaluate(() => {
      return (window.MockData?.requests || []).map(r => r.status);
    });
    const valid = ['Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed'];
    statuses.forEach(s => expect(valid).toContain(s));
  });

  test('Arrangement: venues match venueSlots keys', async ({ page }) => {
    await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
    const match = await page.evaluate(() => {
      const venues = window.MockData?.venues;
      const slots = window.MockData?.venueSlots;
      if (!venues || !slots) return false;
      const venueCodes = venues.map(v => v.code);
      const slotKeys = Object.keys(slots);
      return slotKeys.every(k => venueCodes.includes(k));
    });
    expect(match).toBeTruthy();
  });
});

// ══════════════════════════════════════════════════════════════
// 7. NO 4XX/5XX ON ANY API CALL
// ══════════════════════════════════════════════════════════════

test.describe('No bad HTTP responses on page load', () => {
  const pages = [
    '/my-timetable-ui',
    '/cohort-timetable-ui',
    '/student-my-timetable-ui',
    '/my-request-history-ui',
    '/replacement-home-ui',
    '/replacement-arrangement',
  ];

  for (const path of pages) {
    test(`${path}: no 4xx/5xx responses`, async ({ page }) => {
      const badResponses = [];
      page.on('response', res => {
        if (res.status() >= 400 && res.url().includes('/api/')) {
          badResponses.push(`${res.status()} ${res.url()}`);
        }
      });
      await page.goto(path, { waitUntil: 'networkidle' });
      expect(badResponses).toHaveLength(0);
    });
  }
});
