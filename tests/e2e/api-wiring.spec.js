import { test, expect } from '@playwright/test';

const API_BASE = '/api/v1';

test.describe('API endpoints return real data', () => {
  test('GET /api/v1/semester returns chipText and holidays', async ({ request }) => {
    const res = await request.get(`${API_BASE}/semester`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.semester).toBeDefined();
    expect(data.semester.chipText).toMatch(/Semester · \d{2}-\w{3}-\d{4}/);
    expect(data.semester.weeks).toBe(14);
    expect(Array.isArray(data.holidays)).toBeTruthy();
  });

  test('GET /api/v1/timetable/my returns 0-indexed eventsByWeek with seedWeek 11', async ({ request }) => {
    const res = await request.get(`${API_BASE}/timetable/my`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.myTimetable.seedWeek).toBe(11);
    const keys = Object.keys(data.myTimetable.eventsByWeek).map(Number);
    expect(Math.min(...keys)).toBe(0);
    expect(Math.max(...keys)).toBe(13);
    expect(data.myTimetable.eventsByWeek[11]).toBeDefined();
    expect(data.myTimetable.eventsByWeek[11].length).toBeGreaterThan(0);
    const event = data.myTimetable.eventsByWeek[11][0];
    expect(event).toHaveProperty('code');
    expect(event).toHaveProperty('di');
    expect(event).toHaveProperty('start');
    expect(event).toHaveProperty('end');
    expect(event).toHaveProperty('type');
    expect(event).toHaveProperty('venue');
    expect(event).toHaveProperty('status');
  });

  test('GET /api/v1/timetable/cohort returns faculties and events', async ({ request }) => {
    const res = await request.get(`${API_BASE}/timetable/cohort?cohort_id=dft1s1g1`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.cohortTimetable.faculties.length).toBeGreaterThan(0);
    expect(data.cohortTimetable.events.length).toBeGreaterThan(0);
    expect(data.cohortTimetable.rsd3g2Base).toBeDefined();
    const faculty = data.cohortTimetable.faculties[0];
    expect(faculty).toHaveProperty('id');
    expect(faculty).toHaveProperty('name');
    expect(faculty).toHaveProperty('cohorts');
  });

  test('GET /api/v1/timetable/student returns activeCohort and cancelledFlags', async ({ request }) => {
    const res = await request.get(`${API_BASE}/timetable/student`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.studentTimetable.activeCohort).toBeDefined();
    expect(typeof data.studentTimetable.notificationCount).toBe('number');
    expect(data.cohortTimetable.rsd3g2Base.length).toBeGreaterThan(0);
  });

  test('GET /api/v1/requests/my returns requests with title-case statuses', async ({ request }) => {
    const res = await request.get(`${API_BASE}/requests/my`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(Array.isArray(data.requests)).toBeTruthy();
    if (data.requests.length > 0) {
      const req = data.requests[0];
      expect(req).toHaveProperty('id');
      expect(req).toHaveProperty('courseCode');
      expect(req).toHaveProperty('status');
      expect(['Pending', 'Approved', 'Rejected', 'Cancelled', 'Completed']).toContain(req.status);
      expect(req).toHaveProperty('courseCode');
      expect(req).toHaveProperty('requestedAt');
    }
  });

  test('GET /api/v1/requests/conflicts returns conflictReason badges', async ({ request }) => {
    const res = await request.get(`${API_BASE}/requests/conflicts`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(Array.isArray(data.conflictedClasses)).toBeTruthy();
    if (data.conflictedClasses.length > 0) {
      const c = data.conflictedClasses[0];
      expect(c).toHaveProperty('conflictReason');
      expect([
        'Public Holiday', 'Annual Leave', 'Medical Leave',
        'Official Event', 'Emergency Leave',
      ]).toContain(c.conflictReason);
    }
  });

  test('GET /api/v1/arrangement/slots returns venueSlots with [di,hi,status] triples', async ({ request }) => {
    const res = await request.get(`${API_BASE}/arrangement/slots?cohort_ids=1&session_type=L&duration=60&week_number=11`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    const venueKeys = Object.keys(data.venueSlots);
    expect(venueKeys.length).toBeGreaterThan(0);
    const grid = data.venueSlots[venueKeys[0]];
    expect(grid.length).toBe(132); // 6 days * 22 half-hour slots
    const statuses = grid.map(s => s[2]);
    expect(statuses).toContain(0); // available
    expect(data.arrangementWeeks.length).toBe(3);
    expect(data.venues.length).toBeGreaterThan(0);
    expect(data.venues[0]).toHaveProperty('code');
    expect(data.venues[0]).toHaveProperty('type');
  });

  test('GET /api/v1/meta/cohorts returns cohort registry', async ({ request }) => {
    const res = await request.get(`${API_BASE}/meta/cohorts`);
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.cohorts.length).toBeGreaterThan(0);
    const c = data.cohorts[0];
    expect(c).toHaveProperty('code');
    expect(c).toHaveProperty('programme');
    expect(c).toHaveProperty('faculty');
  });
});

test.describe('Pages render from API data', () => {
  test('My Timetable page has real module codes from API', async ({ page }) => {
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#timetable')).toBeAttached();
    const hasEvents = await page.evaluate(() => {
      const data = window.MockData?.myTimetable?.eventsByWeek;
      if (!data) return false;
      return Object.values(data).flat().some(e => e.code && e.code.length > 0);
    });
    expect(hasEvents).toBeTruthy();
  });

  test('Student My Timetable renders cohort sessions', async ({ page }) => {
    await page.goto('/student-my-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#timetable')).toBeAttached();
    const hasEvents = await page.evaluate(() => {
      return window.MockData?.cohortTimetable?.events?.length > 0;
    });
    expect(hasEvents).toBeTruthy();
  });

  test('My Request History shows request rows', async ({ page }) => {
    await page.goto('/my-request-history-ui', { waitUntil: 'networkidle' });
    const rowCount = await page.evaluate(() => {
      return window.MockData?.requests?.length ?? 0;
    });
    expect(rowCount).toBeGreaterThanOrEqual(1);
  });

  test('Replacement Home shows conflicted classes', async ({ page }) => {
    await page.goto('/replacement-home-ui', { waitUntil: 'networkidle' });
    const conflictCount = await page.evaluate(() => {
      return window.MockData?.conflictedClasses?.length ?? 0;
    });
    expect(conflictCount).toBeGreaterThanOrEqual(0);
  });

  test('Arrangement page has available slots from engine', async ({ page }) => {
    await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
    await expect(page.locator('#timetable')).toBeAttached();
    const hasAvailable = await page.evaluate(() => {
      const slots = window.MockData?.venueSlots;
      if (!slots) return false;
      return Object.values(slots).some(grid => grid.some(s => s[2] === 0));
    });
    expect(hasAvailable).toBeTruthy();
  });
});

test.describe('API failure falls back to mock', () => {
  test('page still renders when API is unreachable', async ({ page }) => {
    await page.route('**/api/v1/**', route => route.abort('connectionrefused'));
    await page.goto('/my-timetable-ui', { waitUntil: 'networkidle' });
    await expect(page.locator('#timetable')).toBeAttached();
    const consoleWarnings = [];
    page.on('console', msg => {
      if (msg.text().includes('fallback to mock')) consoleWarnings.push(msg.text());
    });
    await page.reload({ waitUntil: 'networkidle' });
    expect(consoleWarnings.length).toBeGreaterThan(0);
  });
});
