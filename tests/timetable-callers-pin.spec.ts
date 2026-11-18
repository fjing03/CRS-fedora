import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * timetable-callers-pin.spec.ts — the T0a baseline pin
 * (SDD `my-timetable-responsive-prompt`, T15; spec R1 "the blast radius" + R10).
 *
 * WHAT THIS FILE PROVES. The builder's markup is pinned byte-for-byte, so an
 * unplanned change to `buildTimetableGrid()`'s output is reported at full
 * resolution instead of as "a string changed". `tests/fixtures/t0a-baseline.json`
 * was first captured 2026-11-15 on the served `ui-common.js` BEFORE the
 * `my-timetable-responsive-prompt` lane touched anything, when the promise was
 * narrower: a caller that does NOT pass the `responsive` flag stays
 * BYTE-IDENTICAL, because the flag's only two effects (T12's tip migration and
 * the ≥1280 status word) belonged to the two pages that opted in.
 *
 * THAT NARROWER PROMISE WAS RETIRED ON 2026-11-18 (user: "make all timetable
 * pages 'Cancelled'"). The `✕ Cancelled` / `⚠ Conflict` status word is now the
 * timetables' ONE vocabulary on every caller, not only the two that pass the
 * flag, so the SYNTHETIC capture was re-taken (`synthetic.capturedOn`) and now
 * records the whole-word shape; the two LIVE captures are still the 2026-11-15
 * bytes. The tip half is unchanged: a flag-less caller still keeps the legacy
 * `data-name` + `data-tip2` pair, and the migration still never reaches it.
 *
 * THREE RECEIPTS, TWO KINDS:
 *
 *  1. SYNTHETIC (the branch that actually changes). The same five-event array,
 *     the same seven days, the same closure SHAPES the capture used, driven
 *     through `/login` — the only route that loads `ui-common.js` without a
 *     session. Compares `#tableHead.innerHTML`, `#tableBody.innerHTML` and
 *     `#timetable`'s inline `display`.
 *  2. LIVE ×2 — `#timetable.outerHTML` on the two pages whose served grid was
 *     captured: Cohort (`?week=9`) and Venue (`B005&week=9`). Both pages keep the
 *     legacy `data-name` + `data-tip2` attribute PAIR, and that pair's presence
 *     is itself the flag-absent proof (the migration would have replaced it with
 *     a single `data-tip`).
 *     Arrangement paints no grid until a course is picked (recorded deviation in
 *     the apply log), so its identity rides the synthetic call — same function,
 *     same flags — plus these two live pages.
 *
 * HOW A FAILURE READS. Never "a string changed": every receipt reports the first
 * differing byte OFFSET, both sizes and the two snippets at that offset, under a
 * message that names the frozen invariant. A live grid is built from DB rows and
 * today's date, so a legitimate difference is still reported at full resolution —
 * this pin is not softened to a class count.
 *
 * Read-only: no SQL, no writes. It still refuses any target but the disposable
 * 8030 testing serve (receipt-target-guard).
 */

abortUnlessTestingTarget('tests/timetable-callers-pin.spec.ts');

test.use({ storageState: '.auth/staff-5770.json' });

/* The frozen baseline. Loaded with fs (not a JSON import) so the file is read
   exactly as committed, with no bundler or resolver in the way. */
const BASELINE = JSON.parse(
  readFileSync(new URL('./fixtures/t0a-baseline.json', import.meta.url), 'utf8'),
) as {
  capturedOn: string;
  synthetic: { capturedOn?: string; head: string; body: string; tableDisplay: string };
  syntheticEvents: Array<Record<string, unknown>>;
  syntheticDays: Array<Record<string, unknown>>;
  cohort: { path: string; html: string };
  venue: { path: string; html: string };
};

interface Difference {
  offset: number;
  baselineBytes: number;
  actualBytes: number;
  baselineSlice: string;
  actualSlice: string;
}

/** First differing byte/index of two strings, or null when identical. */
function firstDifference(actual: string, baseline: string): Difference | null {
  if (actual === baseline) return null;
  const shortest = Math.min(actual.length, baseline.length);
  let offset = 0;
  while (offset < shortest && actual[offset] === baseline[offset]) offset++;
  const slice = (s: string) => JSON.stringify(s.slice(Math.max(0, offset - 60), offset + 80));
  return {
    offset,
    baselineBytes: baseline.length,
    actualBytes: actual.length,
    baselineSlice: slice(baseline),
    actualSlice: slice(actual),
  };
}

/**
 * Assert one pinned string. On a mismatch the message names the invariant and
 * the measured difference, so a failure reads as "the builder moved" — with the
 * evidence attached — and not as "a string changed".
 */
function assertPinned(label: string, invariant: string, actual: string, baseline: string): void {
  const d = firstDifference(actual, baseline);
  const detail = d === null ? null : [
    `${label} MOVED — the pinned invariant is broken.`,
    `  invariant: ${invariant}`,
    `  first differing offset: ${d.offset}`,
    `  baseline ${d.baselineBytes} bytes · now ${d.actualBytes} bytes `
      + `(delta ${d.actualBytes - d.baselineBytes >= 0 ? '+' : ''}${d.actualBytes - d.baselineBytes})`,
    `  baseline @offset: ${d.baselineSlice}`,
    `  actual   @offset: ${d.actualSlice}`,
  ].join('\n');
  expect(detail, `${label} — ${invariant}`).toBeNull();
}

/**
 * Page-side read of the pinned grid: how many event blocks it paints and which
 * tip attribute they carry. Feeds the flag-absent proof — a single `data-tip`
 * would mean T12's migration reached a caller that never passed `responsive`.
 */
/**
 * The live captures carry the week's "Today" markers, and which week a page lands on
 * depends on the app's clock — so a pin that compared them raw would go red whenever
 * the calendar crossed a week boundary, and say "the builder moved". Both sides are
 * therefore compared with the today markers removed and with the page moved to the
 * week the baseline captured (read from the baseline's own first date label).
 */
function withoutToday(html: string): string {
  return html
    /* token-wise, never a substring: `today-badge` is a class NAME that must survive,
       only the bare `today` marker and `today-cell` are the clock's own */
    .replace(/ class="([^"]*)"/g, (_m, cls: string) => {
      const kept = cls.split(/\s+/).filter((t: string) => t && t !== 'today' && t !== 'today-cell');
      return kept.length ? ` class="${kept.join(' ')}"` : '';
    })
    /* the clock's badge is the SAME slot whether it says Today or OFF — canonicalise
       the pair, so a week that has since stopped being "this week" cannot make the
       builder look changed */
    .replace(/<span class="(?:today|off)-badge">[^<]*<\/span>/g, '<span class="clock-badge"></span>')
    /* the venue grid's lead-time line moves with the calendar by design ("at least 3
       working days from today") — it is data about TODAY, not builder output */
    .replace(/Earliest Bookable : [A-Za-z]+, \d{1,2} [A-Za-z]{3} \d{4}/g, 'Earliest Bookable : <date>');
}

/** First `date-label` of a captured grid — the week the baseline painted. */
function firstDateLabel(html: string): string {
  const m = html.match(/date-label">([^<]+)</);
  return m ? m[1].trim() : '';
}

/** Ask the page for the week the baseline captured, through the page's own control. */
async function goToPinnedWeek(page: Page, baselineHtml: string): Promise<string> {
  const wanted = firstDateLabel(baselineHtml);
  const weeks = await page.evaluate(() => Number((window as any).weekData?.length ?? 0));
  for (let i = 0; i < weeks; i++) {
    await page.evaluate((w) => (window as any).selectWeek(w), i);
    await page.waitForTimeout(250);
    const first = await page.evaluate(() => {
      const el = document.querySelector('#timetable .date-label');
      return el ? (el.textContent || '').trim() : '';
    });
    if (first === wanted) return first;
  }
  return '';
}

async function pinnedSnapshot(page: Page) {
  return page.evaluate(() => {
    const table = document.getElementById('timetable');
    return {
      display: table ? (table as HTMLElement).style.display : null,
      tip2: table ? table.querySelectorAll('.event-block[data-tip2]').length : 0,
      tip: table ? table.querySelectorAll('.event-block[data-tip]').length : 0,
      blocks: table ? table.querySelectorAll('.event-block').length : 0,
    };
  });
}

// ─────────────────────── 1. synthetic: the /login call ───────────────────────

test.describe('T15a — synthetic pin: the builder called without `responsive`', () => {
  test('head, body and table display are byte-identical to the T0a capture', async ({ browser }) => {
    /* A GUEST context on purpose: /login is behind the `guest` middleware, so the
       staff storageState would be bounced to a dashboard and ui-common.js would
       load inside an app page (where the page's own buildTimetable() paints the
       real grid). The capture was taken on the bare login page; the pin must be
       taken on the same page. */
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await page.goto('/login', { waitUntil: 'networkidle' });

    const seen = await page.evaluate(({ events, days }) => {
      /* The capture's scaffolding: the builder is driven directly on a bare page,
         so the three ids it requires are inserted first (the login page has none
         of them, and `emptyState` must exist or the non-cellRender path throws). */
      document.body.insertAdjacentHTML('beforeend', [
        '<table class="timetable" id="timetable">',
        '<thead id="tableHead"></thead><tbody id="tableBody"></tbody></table>',
        '<div id="emptyState"></div>',
      ].join(''));

      /* The capture's own ownership test: LEE is the viewer for this call, which
         is why tipLecturerFn answers 'YOU' for LEE and the two-axis ladder below
         paints LEE's blocks as mine. TAN's stay "not mine". */
      const isMine = (e: { lecturer?: string }) => e.lecturer === 'LEE';

      (window as unknown as { buildTimetableGrid: (cfg: unknown) => void }).buildTimetableGrid({
        events,
        days,
        onEventClick: function () { /* the pin reads markup, never clicks */ },
        tipLecturerFn: function (e: { lecturer?: string }) {
          return isMine(e) ? 'YOU' : (e.lecturer || '—');
        },
        /* The cohort page's ladder shape: cancelled rides the conflict family
           (ownership decides loud red vs the hairline variant), and the plain
           arms are the ownership pair. Deliberately NOT the live page's full
           ladder — the capture's shape is what is pinned. */
        statusClassFn: function (div: HTMLElement, e: { status?: string; lecturer?: string }, isConflict: boolean) {
          if (e.status === 'cancelled') {
            div.classList.add(isMine(e) ? 'event-conflict' : 'event-public-holiday');
            return;
          }
          if (isConflict || e.status === 'conflict') {
            div.classList.add(isMine(e) ? 'event-conflict' : 'event-public-holiday');
            return;
          }
          div.classList.add(isMine(e) ? 'event-mine' : 'event-others');
        },
      });

      return {
        head: document.getElementById('tableHead')!.innerHTML,
        body: document.getElementById('tableBody')!.innerHTML,
        display: (document.getElementById('timetable') as HTMLElement).style.display,
      };
    }, { events: BASELINE.syntheticEvents, days: BASELINE.syntheticDays });

    await ctx.close();

    /* Fixture integrity first: an empty or truncated baseline would make every
       comparison below pass vacuously. The capture's real sizes (head 1 388,
       body 17 334 bytes) are floors here, not tautologies. */
    expect(BASELINE.synthetic.head.length, 'the baseline head is a real capture').toBeGreaterThan(500);
    expect(BASELINE.synthetic.body.length, 'the baseline body is a real capture').toBeGreaterThan(5_000);
    expect(seen.body.length, 'the synthetic call painted a grid').toBeGreaterThan(5_000);

    /* R1, stated as the two assertions it is made of. The message is the point:
       when this fails, it is the BUILDER that moved — the `responsive` flag was
       not passed, so no page opted in and no CSS can be blamed. */
    assertPinned(
      'synthetic #tableHead.innerHTML',
      'R1: buildTimetableGrid() output is pinned byte-for-byte '
        + `(captured ${BASELINE.synthetic.capturedOn ?? BASELINE.capturedOn})`,
      seen.head,
      BASELINE.synthetic.head,
    );
    assertPinned(
      'synthetic #tableBody.innerHTML',
      'R1: buildTimetableGrid() output is pinned byte-for-byte '
        + `(captured ${BASELINE.synthetic.capturedOn ?? BASELINE.capturedOn})`,
      seen.body,
      BASELINE.synthetic.body,
    );
    expect(
      seen.display,
      'R1: with events present and the flag absent, #timetable keeps the inline display '
        + 'the builder set (`\'\'` — emptied, i.e. the stylesheet decides)',
    ).toBe(BASELINE.synthetic.tableDisplay);
  });
});

// ───────────────────── 2. live: the two served callers ─────────────────────

test.describe('T15b — live pin: the two served callers keep the legacy pair', () => {
  test.setTimeout(90_000);

  for (const caller of [
    { key: 'cohort', name: 'Cohort Timetable', path: BASELINE.cohort.path, html: BASELINE.cohort.html },
    { key: 'venue', name: 'Venue Timetable', path: BASELINE.venue.path, html: BASELINE.venue.html },
  ]) {
    test(`${caller.name} (${caller.path}) serves the captured #timetable outerHTML`, async ({ page }) => {
      await page.goto(caller.path, { waitUntil: 'networkidle' });

      /* Wait for the grid the page actually paints, not for a timeout: these
         pages are not opted in, so the mode still writes `data-tip2` on every
         event block. Its presence is the flag-absent proof, asserted twice. */
      await expect(
        page.locator('#timetable .event-block[data-tip2]').first(),
        `${caller.name}: the grid must paint event blocks carrying the legacy data-tip2 pair`,
      ).toBeAttached({ timeout: 20_000 });

      /* move to the baseline's week first, then strip the today markers from both
         sides — see withoutToday()'s comment for why this is not a weakening */
      const reached = await goToPinnedWeek(page, caller.html);
      expect(
        reached,
        `${caller.name}: the page can reach the week the baseline captured `
          + `("${firstDateLabel(caller.html)}") through its own week control`,
      ).toBe(firstDateLabel(caller.html));

      const snapshot = await pinnedSnapshot(page);
      expect(caller.html.length, `${caller.name}: the baseline grid is a real capture`).toBeGreaterThan(5_000);
      expect(snapshot.blocks, `${caller.name}: the week paints event blocks`).toBeGreaterThan(0);
      expect(
        snapshot.tip,
        `${caller.name}: the T12 tip migration must NOT reach a page that never passed `
          + '`responsive: true` (a single data-tip would replace the legacy pair)',
      ).toBe(0);
      expect(
        snapshot.tip2,
        `${caller.name}: every event block still carries the legacy data-tip2 half`,
      ).toBe(snapshot.blocks);

      const html = await page.locator('#timetable').evaluate((el) => el.outerHTML);
      assertPinned(
        `${caller.name} #timetable.outerHTML`,
        'R1: buildTimetableGrid() output is byte-identical when `responsive` is absent — '
          + `compared against the served capture of ${BASELINE.capturedOn} `
          + '(a difference here is the builder moving, or the week serving different rows)',
        withoutToday(html),
        withoutToday(caller.html),
      );
    });
  }
});
