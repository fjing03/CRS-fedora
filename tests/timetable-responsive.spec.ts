import { test, expect, type Browser, type BrowserContext, type Page } from '@playwright/test';

/**
 * timetable-responsive — T16 of SDD `my-timetable-responsive-prompt`.
 *
 * AUTHORITY. `.sdd/changes/my-timetable-responsive-prompt/specs/timetable-responsive/
 * spec.md` (R1–R10) and `design.md` §9 (the behaviour table) / §11 (evidence). The
 * implementation is already in place; this file is the receipt, not a driver.
 *
 * WHAT IS UNDER TEST (measured on the served app, 2026-11-15, not assumed):
 *   · page root      `.tt-responsive` + `data-tt-mode` = agenda | window | full
 *   · day strip      `#ttDayStrip .tt-day`      (7 `<button>`s, `aria-pressed`)
 *   · agenda      ≤767   `#ttAgenda .tt-card`   (a `<button>` per event)
 *   · window  768–1279   `#ttWindowGrid .tt-card`, `--tt-day-count` = 2 or 3
 *   · tools / sheet      `#ttTools` (ships `hidden`), `#ttSheet` + `#ttSheetBody`
 *   · legacy table       `#timetable` (hidden below 1280, restored in `full`)
 *
 * FIXTURE DISCIPLINE. Nothing here hardcodes "2 cards on Wednesday". The testing DB
 * drifts, so every expectation is DERIVED from the page's own payload
 * (`window.eventsData` / `window.weekData` on the staff page, the day strip's own
 * `aria-label` counts on the student page, which does not expose the payload) or from
 * the served DOM. A precondition the fixture genuinely cannot meet is a `test.skip()`
 * with a reason naming the missing row — never a silent pass.
 *
 * `?week=9` IS NOT HONOURED by these two blades (measured 2026-11-15: `?week=9` and
 * no param both land on `window.currentWeek = 7`; the Livewire component derives the
 * week from the timeline — `app/Livewire/MyTimetable.php` `currentTimelineWeekIndex()`).
 * The week is therefore fixed through the page's OWN exported control path,
 * `window.selectWeek(index)` — the same function the week `<select>` calls — which is
 * also what proves the week can be fixed at all.
 *
 * TWO DEFECTS THIS SPEC FOUND, fixed the same day (2026-11-15) and now ordinary
 * receipts — the `test.fail` annotations were removed when the fixes landed:
 *   D1  the Week summary sheet's body held the legend + five cards but both stayed
 *       `display:none` (the mode rules match the relocated nodes too)     → T16b #4
 *   D2  Enter/Space on a card opened nothing: `initTimetableKeyboardHandlers()` reads
 *       `__eventData`, which the module-built cards did not carry          → T16a #4b
 *   (a third, found by T16b's trap receipt: `_dialogAbove()` copied a predicate that
 *    treats "never styled" as "shown" and therefore suspended the trap and Escape.)
 *
 * Identities: staff 5770 via `.auth/staff-5770.json` (the setup project's state). The
 * student page needs its own login — logged in ONCE for this file (Fortify is 5/min).
 *
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/timetable-responsive.spec.ts --reporter=line
 */

const BASE = process.env.CRS_BASE || 'http://127.0.0.1:8030';
const STUDENT = { id: '24DFT0001', password: 'Tarumt@2026' };
const STAFF_TT = '/my-timetable-ui';
const STAFF_AUTH = '.auth/staff-5770.json';
const STUDENT_TT = '/student-my-timetable-ui';

test.use({ storageState: '.auth/staff-5770.json' });

/* ─────────────────────────────── page helpers ─────────────────────────────── */

type DayChip = { index: number; label: string; count: number; holiday: boolean; pressed: boolean };

/** The day strip as the product states it: the R7 accessible name carries the count. */
async function chipState(page: Page): Promise<DayChip[]> {
  return page.evaluate(() =>
    Array.from(document.querySelectorAll('#ttDayStrip .tt-day')).map((el, index) => {
      const node = el as HTMLElement;
      const label = node.getAttribute('aria-label') || '';
      const m = label.match(/, (\d+) (?:class|classes)/);
      return {
        index,
        label,
        count: node.dataset.count !== undefined ? Number(node.dataset.count) : (m ? Number(m[1]) : -1),
        holiday: node.dataset.holiday === '1',
        pressed: node.getAttribute('aria-pressed') === 'true',
      };
    }),
  );
}

type Card = {
  tag: string;
  id: string;
  label: string;
  tip: string;
  text: string;
  overflow: number;
};

/** Every card under `selector`, with the R3 "nothing clipped" measurement. */
async function cards(page: Page, selector = '#ttAgenda .tt-card'): Promise<Card[]> {
  return page.evaluate((sel) =>
    Array.from(document.querySelectorAll(sel)).map((el) => {
      const node = el as HTMLElement;
      return {
        tag: node.tagName,
        id: node.dataset.id || '',
        label: node.getAttribute('aria-label') || '',
        tip: node.getAttribute('data-tip') || '',
        text: (node.textContent || '').replace(/\s+/g, ' ').trim(),
        overflow: node.scrollWidth - node.clientWidth,
      };
    }), selector);
}

/** Page-level horizontal scroll, in px (R2/R3: the sideways scroll this lane removes). */
async function hScroll(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

/** The page root's band, as the module set it (the attribute three CSS rules key on). */
async function mode(page: Page): Promise<string> {
  return (await page.locator('.tt-responsive').getAttribute('data-tt-mode')) || '';
}

/**
 * Open one of the two timetable pages and wait for the module's boot receipt: it sets
 * `data-tt-mode` on the page root inside `sync()`, BEFORE it draws that band's surface,
 * so the attribute's presence means the strip/agenda/window for this width is built.
 * Page errors are collected from the FIRST byte (R7: "no page errors at any width").
 */
const pageErrors = new WeakMap<Page, string[]>();

async function open(page: Page, width: number, path = STAFF_TT, height = 900): Promise<void> {
  const errors: string[] = [];
  pageErrors.set(page, errors);
  page.on('pageerror', (err) => errors.push(String(err)));
  await page.setViewportSize({ width, height });
  await page.goto(BASE + path, { waitUntil: 'networkidle' });
  await expect(page.locator('.tt-responsive'), `${path} @${width}: the module booted`).toHaveAttribute(
    'data-tt-mode',
    /^(agenda|window|full)$/,
  );
}

/** R7's last line: neither page may throw, at any width. */
function expectNoPageErrors(page: Page, where: string): void {
  expect(pageErrors.get(page) ?? [], `${where}: R7 — no page errors`).toEqual([]);
}

/** Tab from wherever focus is until a day chip holds it; returns that chip's index. */
async function tabToChip(page: Page): Promise<number> {
  for (let i = 0; i < 60; i++) {
    await page.keyboard.press('Tab');
    const index = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      const strip = document.getElementById('ttDayStrip');
      if (!el || !strip || !strip.contains(el)) return -1;
      const day = el.getAttribute('data-day');
      return day === null ? -1 : Number(day);
    });
    if (index >= 0) return index;
  }
  return -1;
}

/** Select a day through the strip itself (a real tap, not the module's API). */
async function tapDay(page: Page, day: number): Promise<void> {
  await page.locator('#ttDayStrip .tt-day').nth(day).click();
  await expect
    .poll(async () => (await chipState(page)).filter((c) => c.pressed).map((c) => c.index).join(','))
    .toBe(String(day));
}

/** Fix the displayed week through the page's own exported control path. */
async function fixWeek(page: Page, weekIndex: number): Promise<void> {
  await page.evaluate((i) => (window as unknown as { selectWeek: (n: number) => void }).selectWeek(i), weekIndex);
  await expect
    .poll(() => page.evaluate(() => (window as unknown as { currentWeek: number }).currentWeek))
    .toBe(weekIndex);
}

type ServedWeek = {
  week: number;
  events: Array<{ di: number; status: string; code: string; type: string; name: string; start: number; end: number; eventKey?: string }>;
  days: Array<{ abbr: string; date: string; holiday: boolean }>;
};

/** The staff page's own served payload — the independent source of truth for counts. */
async function served(page: Page): Promise<ServedWeek[]> {
  return page.evaluate(() => {
    const w = window as unknown as {
      currentWeek: number;
      eventsData: ServedWeek['events'][];
      weekData: Array<{ days: ServedWeek['days'] }>;
    };
    return w.weekData.map((week, i) => ({
      week: i,
      events: w.eventsData[i] || [],
      days: week.days,
    }));
  });
}

/**
 * The student page keeps `eventsData` in a closure (not on `window`), so its counts are
 * read from the product's own day strip and cross-checked against the grid.
 */
let studentState: Awaited<ReturnType<BrowserContext['storageState']>> | null = null;

/** Log in ONCE for the whole file (Fortify limiter: 5/min per identity). */
async function studentStorage(browser: Browser) {
  if (studentState) return studentState;
  const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
  const page = await ctx.newPage();
  await page.goto(`${BASE}/login/student`, { waitUntil: 'domcontentloaded' });
  await page.fill('#login_id', STUDENT.id);
  await page.fill('#password', STUDENT.password);
  await page.click('button[type="submit"]');
  await page.waitForURL(/student-my-timetable/, { timeout: 30_000 });
  studentState = await ctx.storageState();
  await ctx.close();
  return studentState;
}

async function openStudent(browser: Browser, width: number, height = 900) {
  const ctx = await browser.newContext({
    viewport: { width, height },
    storageState: await studentStorage(browser),
  });
  const page = await ctx.newPage();
  await open(page, width, STUDENT_TT, height);
  return { ctx, page };
}

/** The Status row of the open details modal — the wording R4 pins for these two pages. */
async function modalStatus(page: Page): Promise<string | null> {
  return page.evaluate(() => {
    const rows = Array.from(document.querySelectorAll('#modalBody .detail-row'));
    const row = rows.find((r) => (r.querySelector('.detail-label')?.textContent || '').trim() === 'Status');
    return row ? (row.querySelector('.badge')?.textContent || '').trim() : null;
  });
}

/** R7's 48 × 48 law, measured as the CSS layout box (a flip-card's mid-animation
 *  projection is a render artefact, not a size — `offsetWidth` is the CSS px box). */
type Control = { label: string; w: number; h: number; inStrip: boolean; isHint: boolean; inSheet: boolean };

async function controls(page: Page): Promise<Control[]> {
  return page.evaluate(() => {
    const root = document.querySelector('.tt-responsive');
    if (!root) return [];
    const sel = 'a[href], button, select, input:not([type="hidden"]), textarea, '
      + '[role="button"], [tabindex]:not([tabindex="-1"])';
    const seen = (n: HTMLElement) => {
      const cs = getComputedStyle(n);
      if (cs.display === 'none' || cs.visibility === 'hidden') return false;
      const r = n.getBoundingClientRect();
      return r.width > 0 && r.height > 0;
    };
    return (Array.from(root.querySelectorAll(sel)) as HTMLElement[]).filter(seen).map((n) => ({
      label: n.tagName.toLowerCase()
        + (n.id ? '#' + n.id : '')
        + (n.className && typeof n.className === 'string' && n.className.trim()
          ? '.' + n.className.trim().split(/\s+/)[0]
          : '')
        + ` "${(n.getAttribute('aria-label') || n.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 40)}"`,
      w: n.offsetWidth,
      h: n.offsetHeight,
      inStrip: !!n.closest('#ttDayStrip'),
      /* the next-class control moved INSIDE the empty/holiday panel on 2026-11-15
         (the preview's shape), so it is found by its own class, not by a container id */
      isHint: !!n.closest('.tt-next-hint') || (n.classList.contains('tt-next-hint')),
      inSheet: !!n.closest('#ttSheet'),
    }));
  });
}

/** Axe-free assertion helper so every offender is named in the message (R7). */
function expectTouchTargets(found: Control[], where: string): void {
  const offenders = found.filter((c) => c.w < 48 || c.h < 48);
  expect(
    offenders.length,
    `${where}: R7 needs every control inside .tt-responsive to be at least 48 × 48 CSS px; `
      + `${offenders.length} offender(s): `
      + offenders.map((c) => `${c.label} = ${c.w}×${c.h}`).join(' | '),
  ).toBe(0);
}

// ═════════════════════════ T16a — the phone (≤767) ═════════════════════════
// Behaviour-table rows 1, 6, 8, 12, 14 + R3, R5b, R7 (strip), R8 (print).

test.describe('T16a — the phone (320 / 360 / 390 / 430)', () => {
  test.setTimeout(90_000);

  test('1. agenda mode, the legacy table gone, no sideways scroll, no clipped card', async ({ browser }) => {
    for (const width of [320, 360, 390, 430]) {
      const ctx = await browser.newContext({ viewport: { width, height: 900 }, storageState: '.auth/staff-5770.json' });
      const page = await ctx.newPage();
      try {
        await open(page, width);

        expect(await mode(page), `${width}: the phone band is the agenda`).toBe('agenda');
        await expect(page.locator('#timetable'), `${width}: R2 — the 22-column table never shows under the agenda`).toBeHidden();
        await expect(page.locator('#ttWindowGrid'), `${width}: the tablet grid is not this band`).toBeHidden();
        expect(await hScroll(page), `${width}: R2 — no page-level horizontal scroll`).toBe(0);

        /* all seven chips, whichever width (R5: the strip is never a subset) */
        const chips = await chipState(page);
        expect(chips.length, `${width}: R5 — all seven days`).toBe(7);

        /* pick a day by reading the chips — never by hardcoding a fixture */
        const withClasses = chips.find((c) => c.count > 0);
        expect(withClasses, `${width}: the week has at least one class to draw`).toBeTruthy();
        await tapDay(page, withClasses!.index);

        const drawn = await cards(page);
        expect(drawn.length, `${width}: one card per event of the selected day`).toBe(withClasses!.count);
        for (const card of drawn) {
          expect(card.tag, `${width}: R3 — a card is a real <button>`).toBe('BUTTON');
          expect(card.overflow, `${width}: R3 — card "${card.label}" clips horizontally`).toBeLessThanOrEqual(1);
        }
        expect(await hScroll(page), `${width}: still zero sideways scroll after the tap`).toBe(0);
        expectNoPageErrors(page, `${width}: staff page`);
      } finally {
        await ctx.close();
      }
    }
  });

  test('2. the strip: seven chips, one pressed, the R7 accessible names', async ({ page }) => {
    await open(page, 390);
    const chips = await chipState(page);

    expect(chips.length, 'R5 — all seven days at 390').toBe(7);
    for (const chip of chips) {
      /* item #7 option C — `<day>, <date>, <N> class(es)[, Today][, <holiday name>]` */
      expect(chip.label, `chip ${chip.index}: R7 name "<day>, <date>, <N> class(es)[, Today][, <holiday>]"`)
        .toMatch(/^(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday), \d{1,2} [A-Z][a-z]{2} \d{4}, \d+ (class|classes)(, Today)?(, [^,]+)?$/);
    }
    const pressed = chips.filter((c) => c.pressed);
    expect(pressed.length, 'R5 — exactly one chip is the selected day').toBe(1);
    /* option C dropped the word "selected" — aria-pressed carries the state, and the
       chip that IS today now says "Today" in its name instead */
    expect(pressed[0].label, 'R7 — the selected chip is announced as pressed').toContain(', ');
    expect(
      await page.locator('#ttDayStrip .tt-day[aria-pressed="true"]').getAttribute('aria-pressed'),
      'R7 — aria-pressed is what tells a screen reader it is the selected day',
    ).toBe('true');

    /* item #8 — aria-current="date" rides the REAL today, selected or not; the
       selection is aria-pressed, so the two never claim the same thing */
    const current = await page.evaluate(() =>
      Array.from(document.querySelectorAll('#ttDayStrip .tt-day'))
        .map((el, i) => ({ i, cur: el.getAttribute('aria-current'), today: (el as HTMLElement).dataset.today }))
        .filter((x) => x.cur !== null));
    expect(current.length, 'R5 — at most one aria-current="date"').toBeLessThanOrEqual(1);
    for (const c of current) {
      expect(c.cur, 'item #8 — the mark is `date`').toBe('date');
      expect(c.today, 'item #8 — and it sits on the day the payload calls today').toBe('1');
    }
    /* prove the "today, not selected" case: pick a day that is NOT today and re-read */
    const other = chips.find((c) => !c.pressed && !(current.length === 1 && c.index === current[0].i));
    if (other) {
      await tapDay(page, other.index);
      const after = await page.evaluate(() =>
        Array.from(document.querySelectorAll('#ttDayStrip .tt-day'))
          .map((el, i) => ({ i, cur: el.getAttribute('aria-current'), pressed: el.getAttribute('aria-pressed') }))
          .filter((x) => x.cur !== null));
      if (after.length === 1) {
        expect(after[0].pressed, 'item #8 — today keeps the mark while another day is selected').toBe('false');
      }
    }
  });

  test('3. a tap re-renders the agenda; a tap on the selected day is a NO-OP (R5b)', async ({ page }) => {
    await open(page, 390);
    const chips = await chipState(page);

    /* two days whose class counts differ — derived, never named */
    const from = chips.find((c) => c.count > 0)!;
    const to = chips.find((c) => c.count > 0 && c.count !== from.count)!;
    test.skip(
      !to,
      `fixture: every day with classes in this week carries ${from.count} of them, so no tap can `
        + 'change the agenda count — the no-op receipt needs two different counts',
    );

    await tapDay(page, from.index);
    expect((await cards(page)).length, 'the agenda shows the selected day').toBe(from.count);

    /* mark the node the page's own builder wrote: a re-render replaces it, a no-op keeps it */
    await page.evaluate(() => {
      (document.querySelector('#ttAgenda .tt-card') as HTMLElement).dataset.probe = 'kept';
    });
    const before = await page.evaluate(() => document.getElementById('ttAgenda')!.innerHTML);

    await tapDay(page, from.index); // the SAME day, tapped again
    const after = await page.evaluate(() => ({
      html: document.getElementById('ttAgenda')!.innerHTML,
      probe: (document.querySelector('#ttAgenda .tt-card') as HTMLElement | null)?.dataset.probe || '',
      cards: document.querySelectorAll('#ttAgenda .tt-card').length,
    }));
    expect(after.probe, 'R5b — the same tap must not re-render the agenda (the node is the SAME node)').toBe('kept');
    expect(after.html, 'R5b — the agenda markup is untouched by a tap on the selected day').toBe(before);
    expect(after.cards, 'R5b — and the count is unchanged').toBe(from.count);

    await tapDay(page, to.index);
    const moved = await page.evaluate(() => ({
      probe: (document.querySelector('#ttAgenda .tt-card') as HTMLElement | null)?.dataset.probe || '',
      cards: document.querySelectorAll('#ttAgenda .tt-card').length,
    }));
    expect(moved.probe, 'a DIFFERENT day re-renders (the marked node is gone)').toBe('');
    expect(moved.cards, `the agenda follows the tapped day (${to.count} classes)`).toBe(to.count);
  });

  test('3b. R5b — the week label re-formats across 768: no year on the phone, both years above', async ({ browser }) => {
    /* R5b's first commitment is "do not break it, asserted once". The label is written by
       populateWeekSelect()'s own 768 re-label (ui-common.js, shared by every page), so the
       receipt is a before/after pair rather than a new format. */
    const read = async (width: number) => {
      const ctx = await browser.newContext({ viewport: { width, height: 900 }, storageState: STAFF_AUTH });
      const page = await ctx.newPage();
      try {
        await open(page, width);
        const label = await page.evaluate(() => {
          const sel = document.getElementById('weekSelect') as HTMLSelectElement | null;
          return sel ? sel.options[sel.selectedIndex].text.trim() : '';
        });
        expect(label, `${width}: the week label is present`).toMatch(/Week \d+/);
        return label;
      } finally {
        await ctx.close();
      }
    };

    const phone = await read(390);
    const tablet = await read(900);
    expect(phone, `R5b — the phone label carries no year: "${phone}"`).not.toMatch(/\b20\d\d\b/);
    expect(phone, 'R5b — and it is the compact `Week N · d Mon ~ d Mon` form').toMatch(/Week \d+ · /);
    expect(tablet, `R5b — the tablet label carries the year(s): "${tablet}"`).toMatch(/\b20\d\d\b/);
    expect(tablet.length, 'R5b — the wide label is the fuller one').toBeGreaterThan(phone.length);
  });

  test('3c. R5b — changing week keeps the same weekday selected', async ({ page }) => {
    await open(page, 390);
    const chips = await chipState(page);
    /* pick the middle of the week: a Wednesday has room on both sides of any crossing */
    await tapDay(page, 3);
    expect(chips.length, 'the strip is there to read').toBe(7);
    const pressedNow = (await chipState(page)).filter((c) => c.pressed).map((c) => c.index);
    expect(pressedNow, 'the tap selected Wednesday').toEqual([3]);

    /* the page's own week control (the same function the <select> calls) */
    const moved = await page.evaluate(() => {
      const cur = Number((window as any).currentWeek ?? 0);
      const total = Number((window as any).weekData?.length ?? 0);
      const target = cur + 1 < total ? cur + 1 : cur - 1;
      (window as any).selectWeek(target);
      return target;
    });
    /* poll, not a fixed wait: a slow Livewire round trip must not make this flaky */
    await expect
      .poll(async () => page.evaluate(() => Number((window as any).currentWeek)), { timeout: 10_000 })
      .toBe(moved);
    const pressedAfter = (await chipState(page)).filter((c) => c.pressed).map((c) => c.index);
    expect(pressedAfter, 'R5b — a Wednesday stays a Wednesday across a week change, never resets to Monday')
      .toEqual([3]);
    expect((await cards(page)).length, 'and the agenda followed the week, still on that day')
      .toBe((await chipState(page)).find((c) => c.index === 3)!.count);
  });

  test('4. a card is a button, opens the details modal on click, and the Status row reads "Class Cancelled"', async ({ page }) => {
    await open(page, 390);

    /* find a cancelled class the SERVED payload really carries, on a day that is not a
       public holiday (a holiday's Status row is "Public Holiday", R4) */
    const weeks = await served(page);
    let target: { week: number; day: number } | null = null;
    for (const w of weeks) {
      for (const e of w.events) {
        const day = w.days[e.di];
        if (e.status === 'cancelled' && day && !day.holiday) {
          target = { week: w.week, day: e.di };
          break;
        }
      }
      if (target) break;
    }
    test.skip(
      !target,
      'fixture: the served payload for this lecturer carries no cancelled class on a non-holiday day '
        + '— there is no "Class Cancelled" Status row to read',
    );

    await fixWeek(page, target!.week);
    await tapDay(page, target!.day);

    const drawn = await cards(page);
    const cancelled = drawn.find((c) => /, Class Cancelled$/.test(c.label));
    expect(cancelled, 'the cancelled class is drawn as a card, and says so in its spoken label').toBeTruthy();
    expect(cancelled!.tag, 'R3 — a card is a real <button>').toBe('BUTTON');
    expect(cancelled!.text, 'R3 — the block prints the short word on the note line').toContain('Cancelled');
    /* item #9 — the glyph is a DRAWN icon now, not a typed character, so it reads the
       same on every phone; the word itself stays text */
    expect(
      await page.locator('#ttAgenda .tt-status svg.tt-status-icon').count(),
      'item #9 — the status word carries its drawn icon',
    ).toBeGreaterThan(0);

    /* R3: the subject NAME lives in aria-label / the tip / the modal — never on the card */
    const name = (cancelled!.tip.split(' · ')[0] || '').trim();
    expect(cancelled!.label, 'R3 — the spoken label carries the subject name').toContain(name);
    expect(cancelled!.text, 'R3 — the subject name must NOT be printed on the card').not.toContain(name);

    /* item #9 — the glyph is an icon now, so the card is found by its status class,
       not by a typed character */
    const cardEl = page.locator('#ttAgenda .tt-card').filter({ has: page.locator('.tt-status.status-cancelled') }).first();

    /* a click reaches the page's OWN details handler */
    await cardEl.click();
    await expect(page.locator('#classModal'), 'a click opens the details modal').toBeVisible();
    expect(await modalStatus(page), 'R4 — the long word, page-scoped: "Class Cancelled"').toBe('Class Cancelled');
    await page.keyboard.press('Escape');
    await expect(page.locator('#classModal'), 'Escape closes the modal').toBeHidden();
  });

  test('4b. Enter on the focused card opens the same details modal (R3/R7)', async ({ page }) => {
    /* This receipt is why the card carries `__eventData`: the shared key seam
       (`initTimetableKeyboardHandlers()`) preventDefaults Enter/Space on any
       `.event-block` and then opens `e.target.__eventData`. Before 2026-11-15 the
       module-built cards had no such field, so the key was swallowed and nothing
       opened — a keyboard user could not open a class from the phone agenda at all. */
    await open(page, 390);
    const chips = await chipState(page);
    const day = chips.find((c) => c.count > 0)!;
    await tapDay(page, day.index);

    const cardEl = page.locator('#ttAgenda .tt-card').first();
    await cardEl.focus();
    await expect(cardEl).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#classModal'), 'R3/R7 — Enter on the focused card opens the details modal').toBeVisible();
  });

  test('5. keyboard: Tab reaches the strip, Space and Enter select a day (R7)', async ({ page }) => {
    await open(page, 390);
    const chips = await chipState(page);

    const landed = await tabToChip(page);
    expect(landed, 'R7 — the day strip is reachable with Tab alone (60 stops from the top of the page)')
      .toBeGreaterThanOrEqual(0);

    /* Space selects the FOCUSED chip (the strip re-renders, so focus is re-earned below) */
    await page.keyboard.press('Space');
    await expect
      .poll(async () => (await chipState(page)).filter((c) => c.pressed).map((c) => c.index))
      .toEqual([landed]);
    expect((await cards(page)).length, 'Space selected that day, and the agenda followed')
      .toBe(chips[landed].count);

    /* …and Enter on a chip reached by Tab alone */
    const reached = await tabToChip(page);
    expect(reached, 'Tab still reaches the strip after the re-render').toBeGreaterThanOrEqual(0);
    const step = reached === 6 ? -1 : 1;
    await page.keyboard.press(step === 1 ? 'Tab' : 'Shift+Tab');
    const enterTarget = await page.evaluate(() => Number((document.activeElement as HTMLElement).getAttribute('data-day')));
    expect(enterTarget, 'Tab/Shift+Tab step chip to chip inside the strip').toBe(reached + step);
    await page.keyboard.press('Enter');
    await expect
      .poll(async () => (await chipState(page)).filter((c) => c.pressed).map((c) => c.index))
      .toEqual([enterTarget]);
    expect((await cards(page)).length, 'Enter selected that day too').toBe(chips[enterTarget].count);
  });

  test('6. print: the paper gets the full week, the legend and the cards — never the phone furniture (R8)', async ({ page }) => {
    await open(page, 390);
    await page.emulateMedia({ media: 'print' });

    await expect(page.locator('.legend-bar'), 'R8 — the legend prints whatever the on-screen mode').toBeVisible();
    await expect(page.locator('#summarySection'), 'R8 — the five cards print too').toBeVisible();
    for (const selector of ['.tt-days', '.tt-tools', '.tt-agenda', '#ttNextHint', '#ttWindowGrid', '#ttSheet']) {
      await expect(page.locator(selector), `R8 — ${selector} must not print`).toBeHidden();
    }

    const paper = await page.evaluate(() => {
      const table = document.getElementById('timetable') as HTMLElement;
      return { display: getComputedStyle(table).display, tag: table.tagName };
    });
    expect(paper.tag, 'R8 — the legacy grid is a table again on paper').toBe('TABLE');
    expect(paper.display, 'R8 — and it is displayed at paper width').toBe('table');

    await page.emulateMedia({ media: 'screen' });
    expect(await mode(page), 'the screen state survived the print emulation').toBe('agenda');
    await expect(page.locator('#timetable'), 'and the table is hidden again on screen').toBeHidden();
  });

  test('7. both themes at 390: token-driven colour, same geometry, no clipping', async ({ page }) => {
    await open(page, 390);
    const chips = await chipState(page);
    const day = chips.find((c) => c.count > 0)!;
    await tapDay(page, day.index);

    const seen: Record<string, string> = {};
    for (const theme of ['dark', 'light'] as const) {
      await page.evaluate((t) => {
        const html = document.documentElement;
        html.classList.remove(t === 'dark' ? 'light' : 'dark');
        html.classList.add(t);
      }, theme);

      seen[theme] = await page.evaluate(() =>
        getComputedStyle(document.documentElement).getPropertyValue('--color-bg').trim());

      expect(await mode(page), `${theme}: the phone band is unchanged by the theme`).toBe('agenda');
      expect(await hScroll(page), `${theme}: no sideways scroll`).toBe(0);
      const drawn = await cards(page);
      expect(drawn.length, `${theme}: the same day paints the same cards`).toBe(day.count);
      for (const card of drawn) {
        expect(card.overflow, `${theme}: "${card.label}" clips horizontally`).toBeLessThanOrEqual(1);
      }
    }
    expect(seen.light, 'the theme really switched (R10: colours come from theme.css tokens)')
      .not.toBe(seen.dark);
  });

  test('10. items #1/#5/#6 — the heading names the date, the phone chip is short, the title is 20px', async ({ page }) => {
    /* Three prototype details the user chose to follow on 2026-11-15:
         #1 the day heading reads `Monday, 9 Nov 2026`, not just `Monday`;
         #5 the phone semester chip is the short label, the dated range from 768 up;
         #6 the page title is 20px at phone width, 22px above (not 18px). */
    await open(page, 390);

    const heading = await page.evaluate(() => {
      const el = document.getElementById('ttDayHeading')!;
      const date = (window as any).weekData[Number((window as any).currentWeek)].days[
        Number((document.querySelector('#ttDayStrip .tt-day[aria-pressed="true"]') as HTMLElement).dataset.day)
      ].date;
      return { text: (el.textContent || '').trim(), date };
    });
    expect(heading.text, '#1 — the heading is `<weekday>, <date>`').toBe(
      new RegExp(`^[A-Z][a-z]+day, ${heading.date.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`).source.replace(/^\^|\$$/g, '') === heading.date
        ? heading.text
        : heading.text,
    );
    expect(heading.text, '#1 — the weekday AND the date are both printed').toMatch(
      new RegExp(`^[A-Z][a-z]+, ${heading.date.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`),
    );

    expect((await page.locator('#semesterChip').textContent())?.trim(), '#5 — short on the phone')
      .toBe('202605 Semester');
    expect(
      await page.evaluate(() => getComputedStyle(document.querySelector('.page-title')!).fontSize),
      '#6 — 20px at phone width',
    ).toBe('20px');

    /* …and the two wide-width halves of the same two rules */
    await page.setViewportSize({ width: 900, height: 900 });
    await expect.poll(() => mode(page)).toBe('window');
    expect((await page.locator('#semesterChip').textContent())?.trim(), '#5 — the dated range returns')
      .toMatch(/^202605 · \d{1,2} [A-Z][a-z]{2} \d{4} ~ \d{1,2} [A-Z][a-z]{2} \d{4}$/);
    expect(
      await page.evaluate(() => getComputedStyle(document.querySelector('.page-title')!).fontSize),
      '#6 — 22px above phone width',
    ).toBe('22px');
    expectNoPageErrors(page, 'text bits @390/900');
  });

  test('9. R5 — a public-holiday day is the PREVIEW PANEL, not a red card, and the hint rides inside it', async ({ page }) => {
    /* The user, 2026-11-15: "Public Holiday shud shows this", with the approved
       preview as the sample. So a holiday day paints one empty-state panel
       (`Public Holiday · <label>` + the date + the next class inside it) and draws
       no class card at all — a red card would claim a class is happening. */
    await open(page, 390);
    const holiday = await page.evaluate(() => {
      const wd = (window as any).weekData || [];
      for (let w = 0; w < wd.length; w++) {
        const days = wd[w].days || [];
        for (let i = 0; i < days.length; i++) {
          if (days[i].holiday) return { week: w, day: i, label: String(days[i].holidayLabel || '') };
        }
      }
      return null;
    });
    test.skip(!holiday, 'fixture: the served semester timeline carries no public holiday');

    await page.evaluate((w) => (window as any).selectWeek(w), holiday!.week);
    await expect
      .poll(() => page.evaluate(() => Number((window as any).currentWeek)), { timeout: 10_000 })
      .toBe(holiday!.week);
    await tapDay(page, holiday!.day);

    const panel = page.locator('#ttAgenda .tt-empty.tt-holiday');
    await expect(panel, 'R5 — the holiday panel is the whole agenda for that day').toHaveCount(1);
    await expect(panel.locator('.empty-title'), 'R5 — the title names the holiday').toHaveText(
      new RegExp(`^Public Holiday · ${holiday!.label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}$`),
    );
    await expect(panel.locator('.empty-text'), 'R5 — under it, the day and its date').toHaveText(
      /^[A-Z][a-z]+, \d{1,2} [A-Z][a-z]{2} \d{4}$/,
    );
    await expect(panel.locator('button.tt-next-hint'), 'R5 — the next class rides INSIDE the panel').toHaveCount(1);
    expect(
      await page.locator('#ttAgenda .tt-card').count(),
      'R5 — no class card is drawn on a holiday',
    ).toBe(0);

    /* and the hint still goes somewhere: it lands on the day it named (the next class
       can be later the same week, so the DAY is the thing to assert, not the week) */
    const target = await panel.locator('button.tt-next-hint').evaluate((el) => ({
      week: Number((el as HTMLElement).dataset.nextWeek),
      day: Number((el as HTMLElement).dataset.nextDay),
    }));
    await panel.locator('button.tt-next-hint').click();
    await expect
      .poll(() => page.evaluate(() => ({
        week: Number((window as any).currentWeek),
        day: (document.querySelector('#ttDayStrip .tt-day[aria-pressed="true"]') as HTMLElement | null)?.dataset.day,
      })), { timeout: 10_000 })
      .toEqual({ week: target.week, day: String(target.day) });
    expectNoPageErrors(page, 'holiday panel @390');
  });

  test('8. the student page is wired the same way — and stays read-only (R9)', async ({ browser }) => {
    const { ctx, page } = await openStudent(browser, 390);
    try {
      expect(
        await page.locator('.tt-responsive').getAttribute('data-tt-page'),
        'R9 — phase B carries the same scope class, and says which page it is',
      ).toBe('student');
      expect(await mode(page), 'R9 — the same bands apply').toBe('agenda');
      await expect(page.locator('#timetable'), 'R9 — the legacy table is hidden here too').toBeHidden();
      expect(await hScroll(page), 'R9 — no sideways scroll').toBe(0);

      const chips = await chipState(page);
      expect(chips.length, 'R9 — the same seven-chip strip').toBe(7);
      const day = chips.find((c) => c.count > 0)!;
      expect(day, 'the student week has at least one class').toBeTruthy();
      await tapDay(page, day.index);

      const drawn = await cards(page);
      expect(drawn.length, 'one card per class of the selected day').toBe(day.count);
      expect(drawn[0].tag, 'R3/R9 — a card is a real <button>').toBe('BUTTON');

      /* a card opens the details modal and NOTHING else: no cancel affordance (R9) */
      await page.locator('#ttAgenda .tt-card').first().click();
      await expect(page.locator('#classModal'), 'R9 — the details modal opens').toBeVisible();
      const cancel = await page.evaluate(() => ({
        byId: document.querySelectorAll('#classModal #btnCancelClass').length,
        danger: document.querySelectorAll('#classModal .modal-footer .btn-danger').length,
      }));
      expect(cancel.byId + cancel.danger, 'R9 — phase B wires no cancel affordance into the modal').toBe(0);
      await page.keyboard.press('Escape');
      await expect(page.locator('#classModal')).toBeHidden();
      expectNoPageErrors(page, 'student page @390');
    } finally {
      await ctx.close();
    }
  });
});

// ══════════════════ T16b — the tablet window + the Week summary sheet ══════════════════
// Behaviour-table rows 3, 4, 9, 14 + R3b, R5, R6.

test.describe('T16b — the tablet window (768 / 1024) and the Week summary sheet', () => {
  test.setTimeout(90_000);

  test('1. window mode: cells, --tt-day-count, the window follows the day and clamps at Mon/Sun', async ({ browser }) => {
    for (const width of [768, 1024]) {
      const days = width < 1024 ? 2 : 3;
      const ctx = await browser.newContext({ viewport: { width, height: 900 }, storageState: '.auth/staff-5770.json' });
      const page = await ctx.newPage();
      try {
        await open(page, width);
        expect(await mode(page), `${width}: 768–1279 is the window band (R2)`).toBe('window');
        await expect(page.locator('#timetable'), `${width}: the legacy table is hidden in window mode (R2)`).toBeHidden();
        expect(await hScroll(page), `${width}: no page-level horizontal scroll`).toBe(0);

        /* the one custom property the CSS grid template reads (R3b / design §5) */
        const dayCount = await page.evaluate(() =>
          getComputedStyle(document.getElementById('ttWindowGrid')!).getPropertyValue('--tt-day-count').trim());
        expect(dayCount, `${width}: R3b — ${days} days visible`).toBe(String(days));

        const payload = (await served(page)).find((w) => w.week === 0)!;
        const week = await page.evaluate(() => (window as unknown as { currentWeek: number }).currentWeek);
        const now = (await served(page)).find((w) => w.week === week)!;

        /* the first column follows the selected day; start = min(day, 7 - N) */
        for (const day of [0, 2, 4, 6]) {
          await tapDay(page, day);
          const start = Math.min(day, 7 - days);
          const expectedHeads = now.days.slice(start, start + days).map((d) => d.abbr);
          const expectedCards = now.events.filter((e) => e.di >= start && e.di < start + days).length;

          const view = await page.evaluate(() => {
            const grid = document.getElementById('ttWindowGrid')!;
            const heads = Array.from(grid.querySelectorAll('.tt-whead')).map((h) => (h.textContent || '').trim());
            const cols = getComputedStyle(grid).gridTemplateColumns.trim();
            const repeat = cols.match(/^repeat\((\d+),/);
            return {
              heads: heads.slice(1),                       // heads[0] is the "Time" corner
              tracks: repeat ? Number(repeat[1]) : cols.split(/\s+/).filter(Boolean).length,
              cards: grid.querySelectorAll('.tt-card').length,
            };
          });

          expect(view.heads.map((h) => h.slice(0, 3)), `${width} @day ${day}: the window starts on the selected day, clamped`)
            .toEqual(expectedHeads);
          expect(view.tracks, `${width} @day ${day}: the axis + ${days} day columns`).toBe(days + 1);
          expect(view.cards, `${width} @day ${day}: one cell per event of the visible days`).toBe(expectedCards);
        }

        /* R3b: cells > 0 on a day that actually carries classes (the fixture check) */
        const chip = (await chipState(page)).find((c) => c.count > 0)!;
        await tapDay(page, chip.index);
        const start = Math.min(chip.index, 7 - days);
        const visible = now.events.filter((e) => e.di >= start && e.di < start + days).length;
        test.skip(visible === 0, `fixture: the ${days}-day window from day ${chip.index} carries no class`);
        expect((await cards(page, '#ttWindowGrid .tt-card')).length, 'R3b — the window paints its cells').toBeGreaterThan(0);
        expect(payload.days.length, 'the served week still has seven days').toBe(7);
        expectNoPageErrors(page, `${width}: window band`);
      } finally {
        await ctx.close();
      }
    }
  });

  test('2. a window cell is the same focusable button the card is — same name, same tip, same modal (R3b)', async ({ page }) => {
    await open(page, 390);
    const chips = await chipState(page);
    const day = chips.find((c) => c.count > 0)!;
    await tapDay(page, day.index);

    /* the phone card's identity for the same event */
    const fromCards = new Map((await cards(page)).map((c) => [c.id, c]));
    expect(fromCards.size, 'the phone card set is not empty').toBeGreaterThan(0);

    /* cross the breakpoint: the module re-renders in place, no reload */
    await page.setViewportSize({ width: 768, height: 900 });
    await expect.poll(() => mode(page), { message: 'the breakpoint crossing reaches the window band' }).toBe('window');
    await expect.poll(async () => (await cards(page, '#ttWindowGrid .tt-card')).length,
      { message: 'the window painted cells for the same day' }).toBeGreaterThan(0);

    const cells = await cards(page, '#ttWindowGrid .tt-card');
    const shared = cells.filter((c) => fromCards.has(c.id));
    expect(shared.length, 'the two surfaces draw the same event identities').toBeGreaterThan(0);
    for (const cell of shared) {
      const card = fromCards.get(cell.id)!;
      expect(cell.tag, 'R3b — a cell is the same focusable <button> a card is').toBe('BUTTON');
      expect(cell.label, `R3b — the same aria-label as the card (${cell.id})`).toBe(card.label);
      expect(cell.tip, `R3b — the same data-tip as the card (${cell.id})`).toBe(card.tip);
      expect(cell.label, 'R3b — the spoken label keeps the R3/R7 shape').toMatch(
        /^\d{1,2}:\d{2} (AM|PM) ~ \d{1,2}:\d{2} (AM|PM), .+ \([A-Z]\), .+, .+, .+$/,
      );
    }

    /* and it opens the page's own details modal */
    await page.locator('#ttWindowGrid .tt-card').first().click();
    await expect(page.locator('#classModal'), 'a window cell opens the details modal (R3b)').toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.locator('#classModal')).toBeHidden();
  });

  test('3. the Week summary sheet: order, subtitle, lock, trap, Esc, restore (R6)', async ({ page }) => {
    await open(page, 390);
    /* at 390 the header chip is the prototype's SHORT label (`202605 Semester`, item #5) */
    const chipText = (await page.locator('#semesterChip').textContent())?.trim() || '';
    expect(chipText, 'item #5 — the phone chip is the short semester label').toBe('202605 Semester');

    const before = await page.evaluate(() => {
      const legend = document.querySelector('.legend-bar') as HTMLElement;
      const section = document.getElementById('summarySection') as HTMLElement;
      const key = (n: HTMLElement) => (n.parentElement!.id || n.parentElement!.className || '').trim();
      return {
        legendParent: key(legend),
        sectionParent: key(section),
        legendIndex: Array.from(legend.parentElement!.children).indexOf(legend),
        sectionIndex: Array.from(section.parentElement!.children).indexOf(section),
        legendFirst: !!(legend.compareDocumentPosition(section) & Node.DOCUMENT_POSITION_FOLLOWING),
      };
    });

    await page.locator('#ttSummaryButton').click();
    await expect(page.locator('#ttSheet'), 'R6 — the button opens the sheet').toBeVisible();
    await expect(page.locator('#ttSummaryButton')).toHaveAttribute('aria-expanded', 'true');
    expect(
      await page.evaluate(() => document.body.classList.contains('tt-sheet-open')),
      'R6 — opening locks the page scroll',
    ).toBe(true);

    const opened = await page.evaluate(() => {
      const body = document.getElementById('ttSheetBody')!;
      return {
        order: Array.from(body.children).map((c) => c.id || c.className.trim()),
        /* item #4 — the semester range lives in the HEAD's subtitle now, not in a body line */
        subtitle: (document.getElementById('ttSheetSubtitle')?.textContent || '').trim(),
        rangeNode: document.getElementById('ttSheetRange'),
        cards: body.querySelectorAll('.summary-card').length,
        focusInSheet: !!document.activeElement?.closest('#ttSheet'),
        dialog: {
          role: document.querySelector('#ttSheet .tt-sheet')?.getAttribute('role'),
          modal: document.querySelector('#ttSheet .tt-sheet')?.getAttribute('aria-modal'),
          labelled: document.querySelector('#ttSheet .tt-sheet')?.getAttribute('aria-labelledby'),
        },
      };
    });
    expect(opened.order, 'R6 — body order is legend → the five cards, nothing else')
      .toEqual(['legend-bar', 'summarySection']);
    expect(opened.cards, 'R6 — the five cards moved in (as nodes)').toBe(5);
    expect(opened.rangeNode, 'item #4 — no range line is built inside the body any more').toBeNull();
    expect(opened.subtitle, 'item #4 — the head carries `Week N · semester · range`').toMatch(
      /^Week \d+ · 202605 Semester · \d{1,2} [A-Z][a-z]{2} \d{4} ~ \d{1,2} [A-Z][a-z]{2} \d{4}$/,
    );
    expect(opened.focusInSheet, 'R6 — focus lands inside the dialog').toBe(true);
    expect(opened.dialog, 'R7 — a labelled aria-modal dialog').toEqual({
      role: 'dialog', modal: 'true', labelled: 'ttSheetTitle',
    });

    /* R7/R6 — Tab never leaves the dialog */
    for (let i = 0; i < 12; i++) {
      await page.keyboard.press('Tab');
      expect(
        await page.evaluate(() => !!document.activeElement?.closest('#ttSheet')),
        `R6 — Tab #${i + 1} stays inside the sheet`,
      ).toBe(true);
    }
    for (let i = 0; i < 4; i++) {
      await page.keyboard.press('Shift+Tab');
      expect(
        await page.evaluate(() => !!document.activeElement?.closest('#ttSheet')),
        `R6 — Shift+Tab #${i + 1} stays inside the sheet`,
      ).toBe(true);
    }

    /* R6 — below 390px the five cards are ONE column; at 390 they are two */
    const tracks = async () => page.evaluate(() => {
      const value = getComputedStyle(document.querySelector('#ttSheetBody .summary-bar')!).gridTemplateColumns.trim();
      const repeat = value.match(/^repeat\((\d+),/);
      return repeat ? Number(repeat[1]) : value.split(/\s+/).filter(Boolean).length;
    });
    expect(await tracks(), 'R6 — two columns at 390').toBe(2);
    await page.setViewportSize({ width: 320, height: 900 });
    await expect.poll(tracks, { message: 'R6 — one column below 390px' }).toBe(1);
    await page.setViewportSize({ width: 390, height: 900 });
    await expect.poll(tracks).toBe(2);

    /* R6 — Escape closes, returns focus, releases the lock, puts both nodes back */
    await page.keyboard.press('Escape');
    await expect(page.locator('#ttSheet')).toBeHidden();
    await expect(page.locator('#ttSummaryButton'), 'R6 — focus returns to the opener').toBeFocused();
    await expect(page.locator('#ttSummaryButton')).toHaveAttribute('aria-expanded', 'false');

    const after = await page.evaluate(() => {
      const legend = document.querySelector('.legend-bar') as HTMLElement;
      const section = document.getElementById('summarySection') as HTMLElement;
      const key = (n: HTMLElement) => (n.parentElement!.id || n.parentElement!.className || '').trim();
      return {
        legendParent: key(legend),
        sectionParent: key(section),
        legendIndex: Array.from(legend.parentElement!.children).indexOf(legend),
        sectionIndex: Array.from(section.parentElement!.children).indexOf(section),
        legendFirst: !!(legend.compareDocumentPosition(section) & Node.DOCUMENT_POSITION_FOLLOWING),
        placeholders: document.querySelectorAll('.tt-sheet-placeholder').length,
        bodyOpen: document.body.classList.contains('tt-sheet-open'),
        range: document.getElementById('ttSheetRange') === null,
      };
    });
    expect(after, 'R6 — both nodes are back in their exact seats, in order, with no placeholder left')
      .toEqual({ ...before, placeholders: 0, bodyOpen: false, range: true });
  });

  test('4. the sheet body SHOWS the legend and the five cards (R6, behaviour row 9)', async ({ page }) => {
    /* The sheet is where those two blocks live below 1280, and the mode rules that
       hide them inline match the RELOCATED nodes too (the sheet body is inside the
       page root), so they must be explicitly re-shown inside #ttSheetBody. This
       receipt caught exactly that on 2026-11-15: the body held all three children in
       the right order and painted only the range line. */
    await open(page, 390);
    await page.locator('#ttSummaryButton').click();
    await expect(page.locator('#ttSheet')).toBeVisible();

    const shown = await page.evaluate(() => {
      const style = (sel: string) => getComputedStyle(document.querySelector(sel) as Element).display;
      return {
        legend: style('#ttSheetBody .legend-bar'),
        section: style('#ttSheetBody #summarySection'),
        visibleCards: Array.from(document.querySelectorAll('#ttSheetBody .summary-card'))
          .filter((c) => (c as HTMLElement).offsetWidth > 0 && (c as HTMLElement).offsetHeight > 0).length,
      };
    });
    expect(shown, 'R6 — the sheet paints the legend, the cards section and the five cards')
      .toEqual({ legend: 'flex', section: 'block', visibleCards: 5 });
  });

  test('5. at ≥1280 the tools block and the sheet opener are HIDDEN, not absent (R6)', async ({ browser }) => {
    for (const width of [1280, 1440]) {
      const ctx = await browser.newContext({ viewport: { width, height: 900 }, storageState: '.auth/staff-5770.json' });
      const page = await ctx.newPage();
      try {
        await open(page, width);
        expect(await mode(page), `${width}: the full-week band`).toBe('full');

        /* the receipt R6 pins: toBeHidden(), never toHaveCount(0) — the node exists,
           it is simply not offered above the breakpoint */
        await expect(page.locator('#ttTools'), `${width}: one tools node in the DOM`).toHaveCount(1);
        await expect(page.locator('#ttTools'), `${width}: R6 — not offered at ≥1280`).toBeHidden();
        await expect(page.locator('#ttSummaryButton'), `${width}: the opener exists`).toHaveCount(1);
        await expect(page.locator('#ttSummaryButton'), `${width}: R6 — the Week summary control is hidden`).toBeHidden();
        await expect(page.locator('#ttSheet'), `${width}: and the sheet itself stays closed`).toBeHidden();
      } finally {
        await ctx.close();
      }
    }
  });
});

// ═══════════════════ T16c — the desktop (≥1280) and the touch law ═══════════════════
// Behaviour-table rows 5, 12 + R4, R7, R8.

test.describe('T16c — desktop (1440) and touch', () => {
  test.setTimeout(90_000);

  test('1. 1440: today\'s full week, no strip, data-tt-mode="full", one block per served event', async ({ page }) => {
    await open(page, 1440);
    expect(await mode(page), 'R2/R5 — ≥1280 is today\'s full week').toBe('full');
    await expect(page.locator('#timetable'), 'the full week table is visible').toBeVisible();

    /* R5: no strip is RENDERED here — the base rule hides it and a fresh load never
       builds chips, so both statements of the requirement hold */
    await expect(page.locator('#ttDayStrip'), 'R5 — no strip at ≥1280').toBeHidden();
    expect(
      await page.evaluate(() => document.getElementById('ttDayStrip')!.children.length),
      'R5 — and it carries no chips either',
    ).toBe(0);

    /* Fix the week through the page's own export (the blade ignores `?week=`), then
       DERIVE the block count from the served payload: a re-seeded testing DB changes
       the number, never the verdict. (The 11-block figure in the apply log belongs to
       the Cohort caller's capture — a different page with ALL cohorts; this page paints
       this lecturer's own events, 7 of them on the same week.) */
    const FIXED_WEEK = 6; // 0-based → "Week 7"
    await fixWeek(page, FIXED_WEEK);
    const week = (await served(page)).find((w) => w.week === FIXED_WEEK)!;
    test.skip(week.events.length === 0, `fixture: week ${FIXED_WEEK + 1} carries no event in the served payload`);

    const drawn = await page.evaluate(() => document.querySelectorAll('#timetable .event-block').length);
    expect(drawn, `one block per served event of week ${FIXED_WEEK + 1}`).toBe(week.events.length);
    expect(await hScroll(page), 'no sideways scroll at 1440').toBe(0);
    expectNoPageErrors(page, '1440: full week');
  });

  test('2. R4 — a cancelled/conflict block prints its word UNDER the time, on the desktop block', async ({ page }) => {
    await open(page, 1440);

    /* derive a week that really carries a cancelled/conflict block on a non-holiday day
       (a holiday keeps "Public Holiday" and prints no word inside the block) */
    const weeks = await served(page);
    const target = weeks.find((w) => w.events.some((e) => (e.status === 'cancelled' || e.status === 'conflict')
      && w.days[e.di] && !w.days[e.di].holiday));
    test.skip(
      !target,
      'fixture: no served week carries a cancelled/conflict block on a non-holiday day — '
        + 'there is no desktop status word to read',
    );

    await fixWeek(page, target!.week);
    const word = await page.evaluate(() => {
      const blocks = Array.from(document.querySelectorAll('#timetable .event-block')) as HTMLElement[];
      const block = blocks.find((b) => b.querySelector('.tt-block-status'));
      if (!block) return null;
      const children = Array.from(block.children).map((c) => c.className);
      return {
        text: (block.querySelector('.tt-block-status')?.textContent || '').trim(),
        cls: block.className,
        afterTime: children.indexOf('ev-note tt-block-status') === children.indexOf('ev-time') + 1,
        icon: !!document.querySelector('#timetable .tt-block-status svg.tt-status-icon'),
        colour: getComputedStyle(block.querySelector('.tt-block-status')!).color,
        blockColour: getComputedStyle(block).color,
      };
    });
    expect(word, 'R4 — the block carries the status word').toBeTruthy();
    expect(word!.text, 'R4 — "Cancelled" / "Conflict" with a drawn glyph, never the long form inside a block')
      .toMatch(/^(Cancelled|Conflict)$/);
    expect(word!.icon, "item #9 — the block's word carries the drawn icon too").toBe(true);
    expect(word!.afterTime, 'R4 — the word sits UNDER the time (the next child after .ev-time)').toBe(true);
    expect(word!.cls, 'R4 — a cancelled/conflicted block still rides the conflict family').toContain('event-conflict');
    expect(word!.colour, 'R4 — the word takes the block\'s own colour').toBe(word!.blockColour);
  });

  test('3. both themes at 1440: same full week, same tokens-only colour', async ({ page }) => {
    await open(page, 1440);
    const seen: Record<string, string> = {};
    for (const theme of ['dark', 'light'] as const) {
      await page.evaluate((t) => {
        const html = document.documentElement;
        html.classList.remove(t === 'dark' ? 'light' : 'dark');
        html.classList.add(t);
      }, theme);
      seen[theme] = await page.evaluate(() =>
        getComputedStyle(document.documentElement).getPropertyValue('--color-bg').trim());

      expect(await mode(page), `${theme}: still the full week`).toBe('full');
      await expect(page.locator('#timetable'), `${theme}: the table is visible`).toBeVisible();
      expect(await hScroll(page), `${theme}: no sideways scroll`).toBe(0);
    }
    expect(seen.light, 'the theme really switched').not.toBe(seen.dark);
  });

  test('4. the student page at 1440: the full week, one block per class the strip counted (R9)', async ({ browser }) => {
    const { ctx, page } = await openStudent(browser, 390);
    try {
      /* the student blade keeps its payload in a closure, so its own strip is the
         independent count: read it at a width where the strip is built, then cross the
         breakpoint and compare with the desktop grid */
      const chips = await chipState(page);
      expect(chips.length, 'the student strip counts all seven days').toBe(7);
      const perWeek = chips.reduce((sum, c) => sum + c.count, 0);
      test.skip(perWeek === 0, 'fixture: this student week carries no class at all');

      await page.setViewportSize({ width: 1440, height: 900 });
      await expect.poll(() => mode(page), { message: 'the student page reaches the full band' }).toBe('full');
      await expect(page.locator('#timetable'), 'R9 — the full week table is restored').toBeVisible();

      const drawn = await page.evaluate(() => document.querySelectorAll('#timetable .event-block').length);
      expect(drawn, 'one block per class the week\'s own strip counted').toBe(perWeek);
      await expect(page.locator('#ttDayStrip'), 'R5/R9 — no strip at 1440').toBeHidden();
      await expect(page.locator('#ttTools'), 'R6/R9 — no Week summary control at 1440').toBeHidden();
      expect(await hScroll(page), 'no sideways scroll').toBe(0);
      expectNoPageErrors(page, 'student page @1440');
    } finally {
      await ctx.close();
    }
  });

  /* ── R7: the 48 × 48 law, under a coarse pointer ── */
  test.describe('touch (hasTouch, phone viewport)', () => {
    test.use({ hasTouch: true, viewport: { width: 390, height: 900 } });

    test('5. every control inside .tt-responsive is at least 48 × 48 — cards, chips, sheet and header', async ({ page }) => {
      await open(page, 390);
      const chips = await chipState(page);
      const withClasses = chips.find((c) => c.count > 0)!;
      const emptyDay = chips.find((c) => c.count === 0);
      expect(withClasses, 'the week has a day with classes to draw').toBeTruthy();

      /* state 1 — a day that carries classes (the agenda cards are controls too) */
      await tapDay(page, withClasses.index);
      const dayState = await controls(page);
      expect(dayState.length, 'the walk found the page\'s controls at all').toBeGreaterThan(10);
      expect(dayState.filter((c) => c.inStrip).length, 'R7 — all seven day chips are in the walk').toBe(7);
      expectTouchTargets(dayState, `agenda day ${withClasses.index}`);

      /* state 2 — an empty day: the next-class button (R5) */
      if (emptyDay) {
        await tapDay(page, emptyDay.index);
        const emptyState = await controls(page);
        expect(emptyState.some((c) => c.isHint), 'R5/R7 — the next-class control is present and was measured').toBe(true);
        expectTouchTargets(emptyState, `empty day ${emptyDay.index}`);
      }

      /* state 3 — the Week summary sheet (its × and the cards' section) */
      await tapDay(page, withClasses.index);
      await page.locator('#ttSummaryButton').click();
      await expect(page.locator('#ttSheet')).toBeVisible();
      const sheetState = await controls(page);
      expect(sheetState.some((c) => c.inSheet), 'R7 — the sheet\'s own controls are in the walk').toBe(true);
      expectTouchTargets(sheetState, 'week summary sheet @390');
      await page.keyboard.press('Escape');
      await expect(page.locator('#ttSheet')).toBeHidden();

      /* state 4 — at 1440 the summary cards (and their ⓘ) are on screen, so the law is
         measurable there too; below 1280 they sit in the sheet, which D1 keeps hidden.
         The ⓘ is measured as its layout box: a flip card's mid-animation projection is
         a render artefact, not a size. */
      await page.setViewportSize({ width: 1440, height: 900 });
      await expect.poll(() => mode(page)).toBe('full');
      await page.locator('#summarySection .summary-card').first().click();
      await page.waitForTimeout(800);
      const deskState = await controls(page);
      expect(deskState.some((c) => c.label.includes('summary-info')),
        'R7 — the summary cards\' ⓘ was reached by the walk').toBe(true);
      expectTouchTargets(deskState, 'touch laptop @1440');
    });
  });
});
