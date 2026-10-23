import { test, expect } from '@playwright/test';

/**
 * SDD arrival-grid-no-pin (user law 2026-10-20): the ?session=…&week=
 * pin carries DATA ONLY — the strip + the submit pair — and NEVER
 * positions the grid. The landing: pinned (memoryless) = Earliest
 * Bookable; pinned + a saved week = STILL Earliest Bookable (the saved
 * loses on pinned landings); combined pinned+intent URLs hand the
 * landing to the destination jump (the user's A decision); the bridge
 * writes NOTHING into arrangementWeek (the rent is dead).
 */
test.use({ storageState: '.auth/staff-5770.json' });

test('R1 — the pinned arrival lands on Earliest Bookable while the strip keeps the pair', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.removeItem('arrangementWeek');
    localStorage.removeItem('currentWeek');
  });
  await page.goto('/replacement-arrangement?session=49&week=14&from=my-timetable', { waitUntil: 'networkidle' });
  await page.waitForSelector('.conflict-strip', { timeout: 15_000 });
  await page.waitForTimeout(900);
  // the grid = the earliest bookable week (the law's landing)
  const eb = await page.evaluate(() => (window as any).firstBookableDay((window as any).weekData));
  expect(eb).not.toBeNull();
  const sel = await page.locator('#weekSelector').inputValue();
  expect(parseInt(sel, 10)).toBe(eb.week);
  // the strip still = 49's own facts (the pin's data life)
  const strip = await page.locator('#subjectInfo').innerText();
  expect(strip).toContain('(P)');
  expect(strip).toContain('Week 14');
  expect(strip).toContain('B011');
  // the pair the submit honours = the arrival pair
  const pin = await page.evaluate(() => (window as any).pinnedOccurrence);
  expect(pin).toEqual({ sessionId: 49, week: 14 });
  // the bridge writes NOTHING (the two-key cleanup makes the null sound)
  const saved = await page.evaluate(() => localStorage.getItem('arrangementWeek'));
  expect(saved).toBeNull();
});

test('R2 — a pinned arrival ignores a saved week (and does not touch it)', async ({ page }) => {
  // the two-hop: land fresh → derive a bookable index differing from the earliest
  await page.goto('/replacement-arrangement', { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  const picked = await page.evaluate(() => {
    const eb = (window as any).firstBookableDay((window as any).weekData);
    const weeks = (window as any).weekData.length;
    for (let i = weeks - 1; i >= 0; i--) {
      if (i !== eb.week && (window as any).weekHasBookableSlot((window as any).weekData, i)) return i;
    }
    return -1;
  });
  expect(picked).toBeGreaterThan(0);
  // seed the saved week, then arrive PINNED
  await page.addInitScript((w: number) => {
    localStorage.removeItem('currentWeek');
    localStorage.setItem('arrangementWeek', String(w));
  }, picked);
  await page.goto('/replacement-arrangement?session=39&week=14&from=replacement-home', { waitUntil: 'networkidle' });
  await page.waitForSelector('.conflict-strip', { timeout: 15_000 });
  await page.waitForTimeout(900);
  // the saved week LOSES on pinned arrivals: the grid = earliest bookable
  const eb = await page.evaluate(() => (window as any).firstBookableDay((window as any).weekData));
  const sel = await page.locator('#weekSelector').inputValue();
  expect(parseInt(sel, 10)).toBe(eb.week);
  // the strongest rent-is-dead receipt: nothing wrote during the boot
  const saved = await page.evaluate(() => localStorage.getItem('arrangementWeek'));
  expect(saved).toBe(String(picked));
});

test('R3 — the destination intent keeps its jump (the A contract)', async ({ page }) => {
  await page.addInitScript(() => {
    localStorage.removeItem('arrangementWeek');
    localStorage.removeItem('currentWeek');
  });
  // the booked date's week = W8's Tuesday (10 Nov 2026 = the C2 family's own shape)
  await page.goto('/replacement-arrangement?venue=B110&date=' + encodeURIComponent('10 Nov 2026') + '&time=' + encodeURIComponent('11:00') + '&from=venue-timetable', { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);
  // the grid's week = the booked date's week (NOT the earliest) — the
  // resolve jump owns this landing (its pre-existing contract, the A decision)
  const landing = await page.evaluate(() => {
    // the date string lives on the grid's row: find the week whose day carries it
    const data = (window as any).weekData;
    for (let w = 0; w < data.length; w++) {
      for (let d = 0; d < data[w].days.length; d++) {
        if (data[w].days[d] && data[w].days[d].date === '10 Nov 2026') return w;
      }
    }
    return -1;
  });
  expect(landing).toBeGreaterThan(0);
  const sel = await page.locator('#weekSelector').inputValue();
  expect(parseInt(sel, 10)).toBe(landing);
});
