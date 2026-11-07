import { test, expect } from '@playwright/test';

/* SDD waived — display-only change (user decision 2026-11-07): the class-date
   countdown counts WORKING days (Mon–Fri, minus the semester's own holiday rows)
   and the bands became past/today red · 1–7 amber · 8+ green. Read-only: a GET
   plus in-page evaluation, no database writes.

   The page's own "today" comes from `getTodayMs()`, which honours
   `MockData.mockNow` when it is set — the brief's fixed anchor is Sat 7 Nov 2026,
   the day the rule was written. */

test.use({ storageState: '.auth/staff-5425.json' });

const BASE = (process.env.CRS_BASE ?? 'http://localhost:8000') + '/my-replacements';

test.beforeEach(async ({ page }) => {
  await page.goto(BASE, { waitUntil: 'networkidle' });
  await page.locator('.timetable, #tableBody').first().waitFor({ state: 'visible', timeout: 15_000 }).catch(() => {});
  // pin the anchor + the holiday set the brief was written against
  await page.evaluate(() => {
    const s = window as any;
    s.__wdSaved = { now: s.MockData?.mockNow ?? null };
    s.MockData.semester = { startDate: '2026-09-21' };
    s.MockData.holidays = [
      { week: 8, dayIndex: 0, label: 'Deepavali Holiday (In Lieu)' },
      { week: 14, dayIndex: 3, label: 'Christmas Eve (Sabah State)' },
      { week: 14, dayIndex: 4, label: 'Christmas Day' },
    ];
    s.MockData.mockNow = new Date(2026, 10, 7); // Sat 7 Nov 2026
  });
});

test.afterEach(async ({ page }) => {
  await page.evaluate(() => {
    const s = window as any;
    if (s.__wdSaved) s.MockData.mockNow = s.__wdSaved.now;
  });
});

test('the count skips weekends and the semester holidays', async ({ page }) => {
  const result = await page.evaluate(() => {
    const s = window as any;
    const f = (iso: string) => s.workingDaysDiff(iso);
    return {
      today: f('2026-11-07'),        // Sat — today
      yesterday: f('2026-11-06'),    // Fri — 1 calendar day back, 0 working → clamp 1
      twoBack: f('2026-11-04'),      // Wed — Thu 5 + Fri 6 = 2
      tomorrowSun: f('2026-11-08'),  // Sun — non-working → clamp 1
      mondayHoliday: f('2026-11-09'),// the Deepavali-in-lieu row → 0 → clamp 1
      tuesday: f('2026-11-10'),      // 1
      wednesday: f('2026-11-11'),    // 2
      boundary7: f('2026-11-18'),    // 7
      boundary8: f('2026-11-19'),    // 8
      holidayWalk: f('2026-12-24'),  // the holiday itself contributes nothing
      nextHoliday: f('2026-12-25'),
    };
  });

  expect(result.today).toBe(0);
  expect(result.yesterday, 'a non-working gap clamps to 1, never 0').toBe(-1);
  expect(result.twoBack).toBe(-2);
  expect(result.tomorrowSun).toBe(1);
  expect(result.mondayHoliday, 'the holiday is not a working day').toBe(1);
  expect(result.tuesday).toBe(1);
  expect(result.wednesday).toBe(2);
  expect(result.boundary7).toBe(7);
  expect(result.boundary8).toBe(8);
  // both Christmas rows are holidays, so walking one calendar day further adds
  // NOTHING to the count (24 → 25 Dec is a 0-working-day step)
  expect(result.nextHoliday, 'the 25th is a holiday too — it adds no working day').toBe(result.holidayWalk);
});

test('the labels read Working Days, and only today says Today', async ({ page }) => {
  const labels = await page.evaluate(() => {
    const s = window as any;
    const l = (n: number) => s.daysLeftLabel(n);
    return [0, 1, 2, 7, 8, -1, -2, -24].map(l);
  });

  expect(labels[0]).toBe('Today');
  expect(labels[1]).toBe('1 Working Day Left');
  expect(labels[2]).toBe('2 Working Days Left');
  expect(labels[3]).toBe('7 Working Days Left');
  expect(labels[4]).toBe('8 Working Days Left');
  expect(labels[5]).toBe('Overdue (1 Working Day Ago)');
  expect(labels[6]).toBe('Overdue (2 Working Days Ago)');
  expect(labels[7]).toBe('Overdue (24 Working Days Ago)');
  // never "0 Working Days…"
  expect(labels.some((t: string) => /^0 Working/.test(t))).toBe(false);
});

test('the far band is green, not grey', async ({ page }) => {
  const colours = await page.evaluate(() => {
    const probe = (cls: string) => {
      const el = document.createElement('span');
      el.className = cls;
      el.textContent = 'x';
      el.style.position = 'absolute';
      document.body.appendChild(el);
      const c = getComputedStyle(el).color;
      el.remove();
      return c;
    };
    const token = document.createElement('span');
    token.style.color = 'var(--color-success)';
    token.textContent = 'x';
    document.body.appendChild(token);
    const success = getComputedStyle(token).color;
    token.remove();
    return { low: probe('urgency-low'), mid: probe('urgency-mid'), success, warning: (() => {
      const el = document.createElement('span');
      el.style.color = 'var(--color-warning)';
      el.textContent = 'x';
      document.body.appendChild(el);
      const c = getComputedStyle(el).color;
      el.remove();
      return c;
    })() };
  });

  expect(colours.low).toBe(colours.success);
  // …and the middle band is the amber one (the brief's `urgency-med` typo would
  // have produced a class with no rule at all — no colour, no nowrap)
  expect(colours.mid).toBe(colours.warning);
});

test('the rendered countdowns use the new words', async ({ page }) => {
  const texts = await page.evaluate(() =>
    Array.from(document.querySelectorAll('#tableBody .col-original .urgency-high, #tableBody .col-original .urgency-mid, #tableBody .col-original .urgency-low'))
      .map((el) => (el.textContent || '').trim()),
  );
  for (const t of texts) {
    expect(t).toMatch(/^(Today|Overdue \(\d+ Working Days? Ago\)|\d+ Working Days? Left)$/);
  }

  // the Requested Replacement column may carry a countdown too (pending only) —
  // when it does, it uses the same vocabulary and sits beside the date
  const repl = await page.evaluate(() =>
    Array.from(document.querySelectorAll('#tableBody .col-requested .urgency-high, #tableBody .col-requested .urgency-mid, #tableBody .col-requested .urgency-low'))
      .map((el) => (el.textContent || '').trim()),
  );
  for (const t of repl) {
    expect(t).toMatch(/^(Today|Overdue \(\d+ Working Days? Ago\)|\d+ Working Days? Left)$/);
  }
});

/* 2026-11-07 (user): the countdown owns its OWN line under the date —
   date / urgency / time — and the pop-up reads it in BRACKETS. Same words,
   same colours, one calculation; only the placement changed. */
test('the countdown sits on its own line, and the pop-up brackets it', async ({ page }) => {
  const cell = await page.evaluate(() => {
    const block = document.querySelector('#tableBody .col-original .cell-class-block');
    if (!block) return null;
    const html = block.innerHTML;
    const lines = (block as HTMLElement).innerText.split('\n').map((s) => s.trim()).filter(Boolean);
    return {
      dateThenBreak: /<span class="class-day-date">[^<]*<\/span><br><span class="urgency-(high|mid|low)"/.test(html),
      breakThenTime: /<\/span><br><span class="class-time">/.test(html),
      noInlineCountdown: /<\/span> <span class="urgency-/.test(html),
      lines,
    };
  });
  expect(cell, 'the original-class cell renders').not.toBeNull();
  expect(cell!.dateThenBreak, 'date, then <br>, then the countdown').toBe(true);
  expect(cell!.breakThenTime, 'the countdown is followed by <br> and the time').toBe(true);
  expect(cell!.noInlineCountdown, 'the old inline shape is gone').toBe(false);
  // three lines: date / countdown / time (+ venue)
  expect(cell!.lines[1]).toMatch(/^(Today|Overdue \(\d+ Working Days? Ago\)|\d+ Working Days? Left)$/);

  // the pop-up: `Date  9 Nov 2026 (1 Working Day Left)`
  await page.locator('#tableBody tbody tr, #tableBody tr').first().click();
  await page.waitForTimeout(700);
  /* 2026-11-07 (user): the pop-up now OPENS on the Proposed tab when the row has
     one, so this receipt clicks Original explicitly — it is the class date whose
     countdown it asserts. */
  const origTab = page.locator('#quickViewModal .modal-tab', { hasText: 'Original Class Schedule' });
  if (await origTab.count()) { await origTab.first().click(); await page.waitForTimeout(300); }
  const modalDate = await page.evaluate(() => {
    const body = document.getElementById('qvBody');
    if (!body) return null;
    const lines = (body as HTMLElement).innerText.split('\n').map((s) => s.trim()).filter(Boolean);
    const i = lines.indexOf('Date');
    return i >= 0 ? lines[i + 1] : null;
  });
  /* Brackets — except an OVERDUE label, which already carries its own brackets and
     therefore follows an em dash (user answer B, 2026-11-07). */
  expect(modalDate, 'the modal prints the countdown in brackets, or after a dash when overdue').toMatch(
    /^\d{1,2} \w{3} \d{4} (\((Today|\d+ Working Days? Left)\)|— Overdue \(\d+ Working Days? Ago\))$/,
  );
});
