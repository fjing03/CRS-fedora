import { test, expect } from '@playwright/test';
import type { BrowserContext, Page } from '@playwright/test';
import { execSync } from 'node:child_process';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * arrangement-slot-ownership-legend — the browser receipt (2026-11-06).
 *
 * The law: COLOUR = state (amber = a pending reservation, red = a conflict),
 * BORDER = ownership (3px = yours, no border = someone else's). The bug this
 * lane fixes: another staff member's pending slot used to paint the
 * "Pending (You)" amber whenever the selected subject shared that cohort,
 * because the engine's busy vector is ownerless and had no ownership test.
 *
 * TWO describes:
 *   T9a (writes): a pending request owned by staff 5770 on a slot pair that a
 *        staff 5425 session's cohort also carries. Viewed by 5425 it must be
 *        "Pending (Others)" (amber, 0px); viewed by 5770, "Pending (You)"
 *        (amber, 3px). Red family on seeded data: 5770's own class is 3px for
 *        him and 0px for 5425. ONE test() — the fixture flips the slots, so a
 *        second test's `available` precondition would trip on its own fixture.
 *   T9b (read-only): the resolver's precedence table (crafted facts, incl. the
 *        two counter-intuitive crossings) and the legend's six rows + tips.
 *
 * Fixture hygiene: slots are DISCOVERED at runtime (never a hardcoded pair —
 * `TestingFixtureSeeder` already holds B010 week 7 Fri 14:00/14:30 for request
 * 26), asserted available + adjacent BEFORE the insert, and every row this
 * spec creates is deleted by afterAll, with the two slot statuses restored.
 *
 * RUN THIS SPEC ALONE. `tests/week-filter-preserved.spec.ts` re-seeds
 * `TestingFixtureSeeder` in its own beforeEach, and that seeder DELETEs every
 * replacement request — which would wipe this fixture mid-run (the symptom is
 * an orphaned pending slot reading "reserved"). `assertFixtureAlive()` turns
 * that into an explicit message instead of a misleading class mismatch.
 *
 * REQUIRES the testing DB seeded + a serve on :8030:
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/arrangement-slot-ownership.spec.ts
 */

// The standing guard (the 2026-11-01 data-loss incident): module-level abort
// BEFORE anything loads. Exact strings: no localhost, no :8000, no bare run.
abortUnlessTestingTarget('tests/arrangement-slot-ownership.spec.ts', { requireAllowSql: true });

const BASE = process.env.CRS_BASE as string;
const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';

/* Seed identities (semester 1): session 39 = staff 5770's AMCS2093 (P) Thu
   13:30 B010, cohort DSF2; session 40 = staff 5425's AMSE2003 (P) Thu 09:00
   B010, same cohort. The pending request below belongs to session 39 and its
   slots land on a week-8 Friday, so 5425 (who shares the cohort) sees it in
   her cohort vector while 5770 owns it. */
const OWNER_SESSION = 39;   // staff 5770
const VIEWER_SESSION = 40;  // staff 5425
const WEEK = 8;
const VENUE = 'B010';
const FIXTURE_MARK = 'PW ownership receipt';

/** RESTORED week_number must differ from the week under test (entry guard). */
const REQUEST_OCCURRENCE_WEEK = 9;

function sql(query: string): string {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  return execSync(PSQL + JSON.stringify(oneLine), { encoding: 'utf8' }).trim();
}

interface SlotPair { ids: number[]; starts: string[]; day: number; hours: number[] }

/** The FIRST TWO disjoint adjacent pairs of the day (one per staff member). */
function discoverTwoPairs(week: number): [SlotPair, SlotPair] {
  const first = discoverSlotPair(week);
  const taken = new Set(first.ids);
  const raw = sql(`SELECT ts.id, ts.start_time, ts.day_of_week
                     FROM time_slots ts JOIN venues v ON v.id = ts.venue_id
                    WHERE ts.semester_id = 1 AND ts.week_number = ${week}
                      AND ts.day_of_week = 4 AND v.room_code = '${VENUE}'
                      AND ts.status = 'available'
                    ORDER BY ts.start_time ASC`);
  const all = raw.split('\n').filter(Boolean).map((line) => {
    const [id, start, day] = line.split('|').map((x) => x.trim());
    return { id: Number(id), start, day: Number(day) };
  });
  const toMin = (t: string) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));
  for (let i = all.length - 2; i >= 0; i -= 1) {
    if (taken.has(all[i].id) || taken.has(all[i + 1].id)) continue;
    if (toMin(all[i + 1].start) - toMin(all[i].start) !== 30) continue;

    const rows = [all[i], all[i + 1]];
    return [first, {
      ids: rows.map((r) => r.id),
      starts: rows.map((r) => r.start),
      day: rows[0].day,
      hours: rows.map((r) => (toMin(r.start) - 8 * 60) / 30),
    }];
  }
  throw new Error(`a second adjacent free pair must exist on B010 week ${week} Friday`);
}

/** Two ADJACENT available half-hours, venue B010, that week's Friday. */
function discoverSlotPair(week: number): SlotPair {
  const raw = sql(`SELECT ts.id, ts.start_time, ts.day_of_week
                     FROM time_slots ts JOIN venues v ON v.id = ts.venue_id
                    WHERE ts.semester_id = 1 AND ts.week_number = ${week}
                      AND ts.day_of_week = 4 AND v.room_code = '${VENUE}'
                      AND ts.status = 'available'
                    ORDER BY ts.start_time ASC`);
  const all = raw.split('\n').filter(Boolean).map((line) => {
    const [id, start, day] = line.split('|').map((x) => x.trim());
    return { id: Number(id), start, day: Number(day) };
  });
  expect(all.length, `free half-hours must exist on B010 week ${week} Friday`).toBeGreaterThan(1);

  // the FIRST genuinely adjacent pair (30 minutes apart) — a 2-slot request
  // needs a real pair, and a drifted seed must not hand us a 90-minute "pair"
  const toMin = (t: string) => Number(t.slice(0, 2)) * 60 + Number(t.slice(3, 5));
  /* Pairs come from the END of the day on purpose: `arrangement-entry-guard`
     submits through the real pipeline by clicking the FIRST free cell, and a
     shared testing DB must not let the two fixtures fight over one slot. */
  let pairIndex = -1;
  for (let i = all.length - 2; i >= 0; i -= 1) {
    if (toMin(all[i + 1].start) - toMin(all[i].start) === 30) { pairIndex = i; break; }
  }
  expect(pairIndex, `an adjacent free pair must exist on B010 week ${week} Friday`).toBeGreaterThanOrEqual(0);

  const rows = [all[pairIndex], all[pairIndex + 1]];
  const day = rows[0].day;
  const hours = rows.map((r) => (toMin(r.start) - 8 * 60) / 30);

  return { ids: rows.map((r) => r.id), starts: rows.map((r) => r.start), day, hours };
}

/** Everything this spec created — cleaned up in afterAll, never a blind sweep. */
const created: { requestIds: number[]; slotIds: number[] } = { requestIds: [], slotIds: [] };

/** A pending request on `pair`, proposed by the staff member who owns `sessionId`. */
function insertPending(sessionId: number, staffId: string, pair: SlotPair): void {
  const proposer = sql(`SELECT user_id FROM lecturers WHERE staff_id = '${staffId}' LIMIT 1`);
  const anchor = pair.ids[0];
  const requestId = Number(sql(`INSERT INTO replacement_requests
       (semester_id, proposer_id, class_session_id, week_number, replacement_time_slot_id,
        status, remarks, submitted_at, created_at, updated_at)
     VALUES (1, ${proposer}, ${sessionId}, ${REQUEST_OCCURRENCE_WEEK}, ${anchor},
             'pending', '${FIXTURE_MARK}', now(), now(), now())
     RETURNING id`));

  created.requestIds.push(requestId);
  created.slotIds.push(...pair.ids);

  for (const slotId of pair.ids) {
    sql(`INSERT INTO replacement_request_slots (replacement_request_id, time_slot_id, created_at, updated_at)
         VALUES (${requestId}, ${slotId}, now(), now())`);
    sql(`UPDATE time_slots SET status = 'pending', version = version + 1, updated_at = now() WHERE id = ${slotId}`);
  }
}

function cleanupFixture(): void {
  for (const requestId of created.requestIds) {
    sql(`DELETE FROM replacement_request_slots WHERE replacement_request_id = ${requestId}`);
    sql(`DELETE FROM audit_logs WHERE replacement_request_id = ${requestId}`);
    sql(`DELETE FROM notifications WHERE request_id = ${requestId}`);
    sql(`DELETE FROM replacement_requests WHERE id = ${requestId}`);
  }
  for (const slotId of created.slotIds) {
    sql(`UPDATE time_slots SET status = 'available', version = version + 1, updated_at = now() WHERE id = ${slotId}`);
  }
  created.requestIds.length = 0;
  created.slotIds.length = 0;
}

/**
 * Land on a DATA week through the page's own week selector.
 *
 * The `?week=` deep link is off by one on this page (measured: `?week=7` lands
 * on data week 8, i.e. array index 7) — a pre-existing arrival quirk, not this
 * lane's business. The selector's option VALUES are array indexes, so
 * `week - 1` is the honest way to ask for a data week, and it is the same path
 * a user takes.
 */
async function gotoWeek(page: Page, sessionId: number, week: number, venue: string = VENUE): Promise<void> {
  await page.goto(`${BASE}/replacement-arrangement?session=${sessionId}&venue=${venue}&_=${Date.now()}`);
  await expect(page.locator('#subjectSelector')).toBeVisible({ timeout: 30_000 });
  await page.waitForFunction(() => document.querySelectorAll('#tableBody .cell-content').length > 0);

  // pick "Week N" by its LABEL (the option values are array indexes — asking
  // for the data week directly is the only honest, user-level way in)
  const value = await page.evaluate((w) => {
    const sel = document.getElementById('weekSelector') as HTMLSelectElement | null;
    const opt = Array.from(sel?.options ?? []).find((o) => new RegExp('^Week\\s+' + w + '\\b').test(o.textContent ?? ''));
    return opt ? opt.value : null;
  }, week);

  expect(value, `the week selector must offer "Week ${week}" (bookable weeks only)`).not.toBeNull();
  await page.selectOption('#weekSelector', value as string);
  await page.waitForFunction(() => document.querySelectorAll('#tableBody .cell-content').length > 0);
}

/** The fixture must still exist — a concurrent re-seed deletes it (see header). */
function assertFixtureAlive(): void {
  const alive = Number(sql(`SELECT count(*) FROM replacement_requests WHERE id IN (${created.requestIds.join(',')})`));
  expect(
    alive,
    'the fixture vanished mid-run — another spec re-seeded the database; run THIS spec alone',
  ).toBe(created.requestIds.length);
}

/**
 * arrangement-cell-state-labels (user 2026-11-07): hover a cell and read the
 * words it shows. The label is a real span now, so the receipt can assert the
 * TEXT, that it stays INSIDE the cell box (4A: wrap + shrink, never overflow),
 * and that the retired `::after` overlay is gone.
 */
async function hoverLabel(p: Page, locator: ReturnType<Page['locator']>) {
  await locator.hover();
  await p.waitForTimeout(220);

  return locator.evaluate((el) => {
    const span = el.querySelector('.cell-state-label') as HTMLElement | null;
    const cell = el.getBoundingClientRect();
    const box = span ? span.getBoundingClientRect() : null;
    return {
      html: span ? span.innerHTML : null,
      // innerText (not textContent): a `<br>` is a real line break there, so the
      // receipt reads the words a person sees ("Other Pending", never "OtherPending")
      text: span ? (span.innerText || span.textContent || '').replace(/\s+/g, ' ').trim() : null,
      display: span ? getComputedStyle(span).display : 'none',
      inside: box
        ? box.top >= cell.top - 1 && box.left >= cell.left - 1
          && box.bottom <= cell.bottom + 1 && box.right <= cell.right + 1
        : false,
      fits: span ? span.scrollHeight <= span.clientHeight + 1 : false,
      after: getComputedStyle(el, '::after').content,
      tip: el.getAttribute('data-tip'),
    };
  });
}

/** Every label the cells may show (user answers 1A/2A). */
const ALLOWED_LABELS = [
  'Your Pending',
  'Other Pending',
  'Venue Conflict',
  'Cohorts Conflict',
  'Your Conflict',
  'Your & Cohorts Conflict',
];

async function login(p: Page, staffId: '5425' | '5770'): Promise<void> {
  await p.goto(`${BASE}/login/staff?_=ownership-${staffId}-${Date.now()}`);
  await p.fill('#login_id', staffId);
  await p.fill('#password', 'Tarumt@2026');
  await p.click('#loginBtn');
  await p.waitForURL(/my-timetable|dashboard/, { timeout: 60_000 });
}

test.describe.configure({ mode: 'serial', timeout: 120_000 });

/* ── T9a — the two families on a real grid (WRITES: guarded fixture) ───── */
test.describe('ownership on the grid', () => {
  let pairA: SlotPair;   // staff 5770's pending
  let pairB: SlotPair;   // staff 5425's pending
  let ownerCtx: BrowserContext;
  let viewerCtx: BrowserContext;
  let ownerPage: Page;
  let viewerPage: Page;

  test.beforeAll(async ({ browser }) => {
    [pairA, pairB] = discoverTwoPairs(WEEK);
    insertPending(OWNER_SESSION, '5770', pairA);
    insertPending(VIEWER_SESSION, '5425', pairB);

    /* ONE CONTEXT PER IDENTITY. A second login on the same context does not
       switch the session (measured: the form posts, /dashboard loads, the
       identity stays the first user) — so the two views must never share
       cookies, or the receipt would compare a page against itself. */
    ownerCtx = await browser.newContext();
    ownerPage = await ownerCtx.newPage();
    await login(ownerPage, '5770');

    viewerCtx = await browser.newContext();
    viewerPage = await viewerCtx.newPage();
    await login(viewerPage, '5425');
  });

  test.afterAll(async () => {
    await ownerCtx?.close();
    await viewerCtx?.close();
    cleanupFixture();
  });

  test('each staff member sees their own pending ringed and the other one flat', async () => {
    const cellOn = (p: Page, day: number, hour: number) => p.locator(`#tableBody td[data-day="${day}"][data-hour="${hour}"] .cell-content`);
    const ringWidth = (locator: ReturnType<typeof cellOn>) => locator.evaluate((el) => getComputedStyle(el).borderTopWidth);

    /* ── 1. staff 5770 — pair A is HIS (3px), pair B is hers (0px) ────── */
    assertFixtureAlive();
    await gotoWeek(ownerPage, OWNER_SESSION, WEEK);

    const ownerPending = cellOn(ownerPage, pairA.day, pairA.hours[0]);
    await expect(ownerPending).toHaveClass(/cell-pending/);
    expect(await ringWidth(ownerPending)).toBe('3px');

    // the ring is the tertiary token, not a literal colour (theme-safe compare)
    const tertiary = await ownerPage.evaluate(() => {
      const probe = document.createElement('span');
      probe.style.color = 'var(--color-tertiary)';
      document.body.appendChild(probe);
      const rgb = getComputedStyle(probe).color;
      probe.remove();
      return rgb;
    });
    expect(await ownerPending.evaluate((el) => getComputedStyle(el).borderTopColor)).toBe(tertiary);

    // the tip is the pending-make-up header (the twin merge keeps ONE block)
    const tip = await ownerPending.getAttribute('data-tip');
    expect(tip, 'the cell tip is attached').toBeTruthy();
    expect(tip!.split('\n')[0]).toContain('Pending');
    expect(await ownerPending.getAttribute('aria-label')).toContain('Pending — ');

    const ownerSeesOthers = cellOn(ownerPage, pairB.day, pairB.hours[0]);
    await expect(ownerSeesOthers).toHaveClass(/cell-reserved/);
    await expect(ownerSeesOthers).not.toHaveClass(/cell-pending/);
    expect(await ringWidth(ownerSeesOthers)).toBe('0px');

    /* THE ENGINE ARM ALONE — the actual bug. The pending slot sits in B010, but
       the busy vector is a TIME vector: viewed from ANY room it still blocks,
       and that ownerless vector is where another staff member's pending used to
       paint itself "Pending (You)". Here the page's own payload (real tuples,
       real owner flag) is fed to the page's own resolver with NO grid row —
       exactly the arm-only situation — for both viewers. */
    const engineArm = await ownerPage.evaluate(({ day, hour }) => {
      const rows = ((window as any).PAGE_DATA.busyByWeek?.['39']?.[7] ?? [])
        .filter((r: any[]) => r[0] === day && r[1] === hour && r[4] === 'pending');
      return {
        rows,
        verdict: (window as any).classifyCell({ gridStatus: null, engineMatches: rows, engineBlocked: rows.length > 0 }),
      };
    }, { day: pairA.day, hour: pairA.hours[0] });
    expect(engineArm.rows.length).toBeGreaterThan(0);
    expect(engineArm.rows.every((r: any[]) => r[5] === true), "the owner's own make-up is flagged mine").toBe(true);
    expect(engineArm.verdict).toBe('cell-pending');

    // his own class (session 39, Thu 13:30) → red WITH the ring
    const ownerOwnClass = cellOn(ownerPage, 3, 11);
    await expect(ownerOwnClass).toHaveClass(/cell-occupied/);
    await expect(ownerOwnClass).toHaveClass(/cell-occupied--mine/);
    expect(await ringWidth(ownerOwnClass)).toBe('3px');

    /* ── 2. staff 5425 — the mirror image on the same grid ───────────── */
    assertFixtureAlive();
    await gotoWeek(viewerPage, VIEWER_SESSION, WEEK);

    const viewerSeesOthers = cellOn(viewerPage, pairA.day, pairA.hours[0]);
    await expect(viewerSeesOthers).toHaveClass(/cell-reserved/);
    await expect(viewerSeesOthers).not.toHaveClass(/cell-pending/);
    expect(await ringWidth(viewerSeesOthers)).toBe('0px');

    const viewerPending = cellOn(viewerPage, pairB.day, pairB.hours[0]);
    await expect(viewerPending).toHaveClass(/cell-pending/);
    expect(await ringWidth(viewerPending)).toBe('3px');

    // the engine arm alone again — the SAME time, from the OTHER viewer
    const viewerEngineArm = await viewerPage.evaluate(({ day, hour }) => {
      const rows = ((window as any).PAGE_DATA.busyByWeek?.['40']?.[7] ?? [])
        .filter((r: any[]) => r[0] === day && r[1] === hour && r[4] === 'pending');
      return {
        rows,
        verdict: (window as any).classifyCell({ gridStatus: null, engineMatches: rows, engineBlocked: rows.length > 0 }),
      };
    }, { day: pairA.day, hour: pairA.hours[0] });
    expect(viewerEngineArm.rows.length).toBeGreaterThan(0);
    expect(viewerEngineArm.rows.every((r: any[]) => r[5] === false), 'the same make-up is NOT hers').toBe(true);
    expect(viewerEngineArm.verdict, "engine arm alone: someone else's pending → amber, no ring").toBe('cell-reserved');

    // the same Thursday class is someone else's from her side → red, no ring
    const viewerOthersClass = cellOn(viewerPage, 3, 11);
    await expect(viewerOthersClass).toHaveClass(/cell-occupied/);
    await expect(viewerOthersClass).not.toHaveClass(/cell-occupied--mine/);
    expect(await ringWidth(viewerOthersClass)).toBe('0px');

    /* ── 3. the HOVER WORDS (arrangement-cell-state-labels) ──────────── */
    const ownerPendingLabel = await hoverLabel(ownerPage, ownerPending);
    expect(ownerPendingLabel.text, 'your own pending cell says so').toBe('Your Pending');
    expect(ownerPendingLabel.display, 'the label shows on hover').toBe('flex');
    expect(ownerPendingLabel.inside, 'the label stays inside the cell').toBe(true);
    expect(ownerPendingLabel.fits, 'the label is not clipped').toBe(true);
    expect(ownerPendingLabel.after, 'the old CSS overlay is retired where the span exists').toBe('none');
    expect(ownerPendingLabel.tip, 'the tooltip is untouched').toContain('Pending');

    const ownerOthersLabel = await hoverLabel(ownerPage, ownerSeesOthers);
    expect(ownerOthersLabel.text, "another staff member's pending says so").toBe('Other Pending');
    expect(ownerOthersLabel.inside).toBe(true);
    expect(ownerOthersLabel.fits).toBe(true);
    expect(ownerOthersLabel.after).toBe('none');

    /* the tip sits ABOVE the cell (user 2026-11-07) — the shared engine's default
       placement, flipping below only when there is no room. It used to be pinned
       `left`, which laid a multi-line block of text over the day columns. */
    const tipGeo = await ownerPage.evaluate(() => {
      const tip = document.querySelector('.data-tip-tooltip') as HTMLElement | null;
      const anchor = document.querySelector('#tableBody .cell-content:hover');
      if (!tip || !anchor) return null;
      const t = tip.getBoundingClientRect();
      const a = anchor.getBoundingClientRect();
      return {
        above: t.bottom <= a.top + 8,
        overlaps: !(t.right < a.left || t.left > a.right),
        opacity: getComputedStyle(tip).opacity,
        pinnedLeft: anchor.getAttribute('data-tip-pos'),
      };
    });
    expect(tipGeo, 'the hovered cell shows its tip').not.toBeNull();
    expect(tipGeo!.above, 'the tip sits ABOVE the hour cell').toBe(true);
    expect(tipGeo!.overlaps, 'and stays over that cell horizontally').toBe(true);
    expect(tipGeo!.opacity).toBe('1');
    expect(tipGeo!.pinnedLeft, 'no left-pin any more').toBeNull();

    const ownerOwnClassLabel = await hoverLabel(ownerPage, ownerOwnClass);
    expect(ownerOwnClassLabel.text, 'your own class → "Your Conflict"').toBe('Your Conflict');
    expect(ownerOwnClassLabel.inside).toBe(true);
    expect(ownerOwnClassLabel.fits).toBe(true);
    expect(ownerOwnClassLabel.after).toBe('none');

    /* the grid-wide sweep: no cell may invent a label outside the six. The span
       is `display:none` until hover, and `innerText` falls back to textContent
       for a hidden node — so the words are read from the MARKUP, where a `<br>`
       becomes the space a person sees. */
    const labelsOnGrid = await viewerPage.evaluate(() =>
      Array.from(document.querySelectorAll('#tableBody .cell-content .cell-state-label'))
        .map((el) => el.innerHTML
          .replace(/<br\s*\/?>/gi, ' ')
          .replace(/<[^>]*>/g, '')
          .replace(/&amp;/g, '&')
          .replace(/\s+/g, ' ')
          .trim()));
    expect(labelsOnGrid.length, 'the grid shows labels at all').toBeGreaterThan(0);
    expect(Array.from(new Set(labelsOnGrid)).every((t) => ALLOWED_LABELS.includes(t))).toBe(true);

    /* receipts: one grid showing every state at once, both themes. The page's
       OWN toggle is used — the app defaults to dark, so a hand-added `dark`
       class would produce two identical images. */
    const themeName = await viewerPage.evaluate(() => (document.documentElement.classList.contains('dark') ? 'dark' : 'light'));
    await viewerPage.screenshot({ path: `/tmp/opencode/arrangement-ownership-${themeName}.png`, fullPage: true });
    await viewerPage.evaluate(() => (window as any).toggleTheme?.());
    await viewerPage.waitForTimeout(400);
    const other = themeName === 'dark' ? 'light' : 'dark';
    await viewerPage.screenshot({ path: `/tmp/opencode/arrangement-ownership-${other}.png`, fullPage: true });
  });
});

/* ── T9b — the resolver's precedence table + the legend (read-only) ───── */
test.describe('resolver precedence and the legend', () => {
  let context: BrowserContext;
  let page: Page;

  test.beforeAll(async ({ browser }) => {
    context = await browser.newContext();
    page = await context.newPage();
    await login(page, '5425');
    await page.goto(`${BASE}/replacement-arrangement?venue=${VENUE}&_=${Date.now()}`);
    await page.waitForFunction(() => typeof (window as any).classifyCell === 'function');
  });

  test.afterAll(async () => { await context?.close(); });

  test('the five rules and the three crossing cases', async () => {
    const verdicts = await page.evaluate(() => {
      const mine = (status: string | null) => [4, 12, 16, 'block', status, true];
      const theirs = (status: string | null) => [4, 12, 16, 'block', status, false];
      const classify = (facts: any) => (window as any).classifyCell(facts);

      return {
        ownPendingGrid: classify({ gridStatus: 3, engineMatches: [], engineBlocked: false }),
        ownPendingEngine: classify({ gridStatus: null, engineMatches: [mine('pending')], engineBlocked: true }),
        othersPendingGrid: classify({ gridStatus: 4, engineMatches: [], engineBlocked: false }),
        othersPendingEngine: classify({ gridStatus: null, engineMatches: [theirs('pending')], engineBlocked: true }),
        ownConflict: classify({ gridStatus: null, engineMatches: [mine(null)], engineBlocked: true }),
        cohortConflict: classify({ gridStatus: null, engineMatches: [theirs(null)], engineBlocked: true }),
        gridOccupied: classify({ gridStatus: 1, engineMatches: [], engineBlocked: false }),
        free: classify({ gridStatus: null, engineMatches: [], engineBlocked: false }),
        // crossings
        ownPendingBeatsOthers: classify({ gridStatus: 3, engineMatches: [theirs('pending')], engineBlocked: true }),
        othersPendingBeatsMyClass: classify({ gridStatus: 4, engineMatches: [mine(null)], engineBlocked: true }),
        twinRowsMineWins: classify({ gridStatus: null, engineMatches: [mine(null), theirs(null)], engineBlocked: true }),
        fiveKeyTupleIsNotOthers: classify({ gridStatus: null, engineMatches: [[4, 12, 16, 'block', 'pending']], engineBlocked: true }),
      };
    });

    expect(verdicts.ownPendingGrid).toBe('cell-pending');
    expect(verdicts.ownPendingEngine).toBe('cell-pending');
    expect(verdicts.othersPendingGrid).toBe('cell-reserved');
    expect(verdicts.othersPendingEngine).toBe('cell-reserved');
    expect(verdicts.ownConflict).toBe('cell-occupied cell-occupied--mine');
    expect(verdicts.cohortConflict).toBe('cell-occupied');
    expect(verdicts.gridOccupied).toBe('cell-occupied');
    expect(verdicts.free).toBeNull();

    // the crossings the design writes down on purpose
    expect(verdicts.ownPendingBeatsOthers).toBe('cell-pending');
    expect(verdicts.othersPendingBeatsMyClass, 'another staff\'s pending beats my own class → amber, no ring').toBe('cell-reserved');
    expect(verdicts.twinRowsMineWins, 'is ANY row mine? → 3px').toBe('cell-occupied cell-occupied--mine');
    expect(verdicts.fiveKeyTupleIsNotOthers, 'a stale 5-key tuple is not "others"').toBe('cell-occupied');
  });

  /* arrangement-cell-state-labels (user 2026-11-07, answers 1A 2A 3A 4A):
     the words are DATA-DRIVEN — `mine` for ownership, the 7th tuple key `arms`
     for the reason, the grid payload's status 1 for the room. Nothing parses a
     holder string. This pins all six labels and their class beside them. */
  test('the six hover labels come from the data, not from the colour', async () => {
    const verdicts = await page.evaluate(() => {
      // [day, start, end, label, statusKind, mine, arms]
      const minePending = [4, 12, 16, 'block', 'pending', true, ['own']];
      const otherPending = [4, 12, 16, 'block', 'pending', false, ['cohort']];
      const myClass = [4, 12, 16, 'block', null, true, ['own']];
      const cohortClass = [4, 12, 16, 'block', null, false, ['cohort']];
      const twin = [4, 12, 16, 'block', null, true, ['own', 'cohort']];
      const state = (facts: any) => (window as any).classifyState(facts);

      return {
        yourPendingGrid: state({ gridStatus: 3, engineMatches: [], engineBlocked: false }),
        yourPendingEngine: state({ gridStatus: null, engineMatches: [minePending], engineBlocked: true }),
        otherPendingGrid: state({ gridStatus: 4, engineMatches: [], engineBlocked: false }),
        otherPendingEngine: state({ gridStatus: null, engineMatches: [otherPending], engineBlocked: true }),
        yourConflict: state({ gridStatus: null, engineMatches: [myClass], engineBlocked: true }),
        cohortsConflict: state({ gridStatus: null, engineMatches: [cohortClass], engineBlocked: true }),
        yourAndCohorts: state({ gridStatus: null, engineMatches: [twin], engineBlocked: true }),
        venueConflict: state({ gridStatus: 1, engineMatches: [], engineBlocked: false }),
        staleTuple: state({ gridStatus: null, engineMatches: [[4, 12, 16, 'block', null]], engineBlocked: true }),
        free: state({ gridStatus: null, engineMatches: [], engineBlocked: false }),
        // the words and the class must always agree — one resolver, one answer
        labelOfYourConflict: (window as any).cellStateLabel({ gridStatus: null, engineMatches: [myClass], engineBlocked: true }),
      };
    });

    expect(verdicts.yourPendingGrid).toEqual({ cls: 'cell-pending', label: 'Your Pending' });
    expect(verdicts.yourPendingEngine).toEqual({ cls: 'cell-pending', label: 'Your Pending' });
    expect(verdicts.otherPendingGrid).toEqual({ cls: 'cell-reserved', label: 'Other<br>Pending' });
    expect(verdicts.otherPendingEngine).toEqual({ cls: 'cell-reserved', label: 'Other<br>Pending' });
    expect(verdicts.yourConflict).toEqual({ cls: 'cell-occupied cell-occupied--mine', label: 'Your<br>Conflict' });
    expect(verdicts.cohortsConflict).toEqual({ cls: 'cell-occupied', label: 'Cohorts<br>Conflict' });
    expect(verdicts.yourAndCohorts, 'a twin keeps BOTH arms').toEqual({
      cls: 'cell-occupied cell-occupied--mine',
      label: 'Your &amp; Cohorts<br>Conflict',
    });
    expect(verdicts.venueConflict).toEqual({ cls: 'cell-occupied', label: 'Venue<br>Conflict' });
    expect(verdicts.staleTuple, 'a tuple with no arm keeps the old word').toEqual({ cls: 'cell-occupied', label: 'Occupied' });
    expect(verdicts.free).toBeNull();
    expect(verdicts.labelOfYourConflict).toBe('Your<br>Conflict');
  });

  test('the legend has eight rows built from the real cell classes', async () => {
    const rows = page.locator('.legend-bar .legend-item');
    await expect(rows).toHaveCount(8);

    /* arrangement-cell-state-labels (user answer 5B): the legend speaks the
       SAME words the cells show — one page, one vocabulary. */
    const expected = [
      ['Available', 'cell-available'],
      ['Your Current Selection', 'event-selection'],
      ['Your Pending', 'cell-pending'],
      ['Other Pending', 'cell-reserved'],
      ['Venue Conflict', 'cell-occupied'],
      ['Cohorts Conflict', 'cell-occupied'],
      ['Your Conflict', 'cell-occupied'],
      ['Your & Cohorts Conflict', 'cell-occupied'],
    ];

    for (let i = 0; i < expected.length; i += 1) {
      const row = rows.nth(i);
      await expect(row).toContainText(expected[i][0]);
      await expect(row.locator('.legend-swatch')).toHaveClass(new RegExp(expected[i][1]));
      await expect(await row.getAttribute('data-tip')).toBeTruthy();
    }

    // only the "yours" conflict swatches carry the ring
    await expect(rows.nth(4).locator('.legend-swatch')).not.toHaveClass(/cell-occupied--mine/);
    await expect(rows.nth(5).locator('.legend-swatch')).not.toHaveClass(/cell-occupied--mine/);
    await expect(rows.nth(6).locator('.legend-swatch')).toHaveClass(/cell-occupied--mine/);
    await expect(rows.nth(7).locator('.legend-swatch')).toHaveClass(/cell-occupied--mine/);
    // the pending swatches: yours is ringed, another staff member's is flat
    await expect(rows.nth(2).locator('.legend-swatch')).toHaveClass(/cell-pending/);
    await expect(rows.nth(3).locator('.legend-swatch')).toHaveClass(/cell-reserved/);

    // tip wording (capitals = emphasis; a data-tip is plain text)
    const tips = await rows.evaluateAll((els) => els.map((el) => el.getAttribute('data-tip') ?? ''));
    expect(tips[0]).toContain('FREE SLOT — CLICK');
    expect(tips[1]).toContain('SELECTED');
    expect(tips[2]).toContain('WAITING');
    expect(tips[2]).toContain('thick border (3px)');
    expect(tips[3]).toContain('WAITING');
    expect(tips[3]).toContain('no border');
    expect(tips[4]).toContain('VENUE CONFLICT');
    expect(tips[4]).not.toMatch(/cohort/i);
    expect(tips[5]).toContain('COHORTS CONFLICT');
    expect(tips[5]).not.toMatch(/venue conflict/i);
    expect(tips[6]).toContain('CANNOT BOOK');
    expect(tips[6]).toContain('thick border (3px)');
    expect(tips[7]).toMatch(/cohort/i);
    expect(tips[7]).toContain('thick border (3px)');
    expect(tips[7]).not.toMatch(/[<>]/, 'a data-tip is plain text — a <br> would print literally');

    // the hint line names both border states, and never claims the 0.5px hairline
    const hint = page.locator('.legend-ownership-hint');
    await expect(hint).toContainText('thick border (3px) = yours');
    await expect(hint).toContainText("no border = others'");
    await expect(page.locator('.legend-bar .osd-thin')).toHaveCount(0);

    /* The swatches borrow the CELL classes, and those carry a "hover label"
       overlay (`.cell-occupied::after` …) meant to fill a grid CELL. On a swatch
       it had no positioned ancestor, so on mouse-out it painted the whole
       viewport in the state colour for the 0.15s fade (the 2026-11-07 flash).
       A swatch must therefore never generate that pseudo box — while hovering or
       after leaving — and the grid cell must still get its label. */
    const swatches = page.locator('.legend-bar .legend-swatch');
    const count = await swatches.count();
    for (let i = 0; i < count; i += 1) {
      const sw = swatches.nth(i);
      expect(
        await sw.evaluate((el) => getComputedStyle(el, '::after').content),
        `legend swatch ${i} paints no hover-label overlay`,
      ).toBe('none');
      await sw.hover();
      await page.waitForTimeout(200);
      expect(
        await sw.evaluate((el) => getComputedStyle(el, '::after').content),
        `legend swatch ${i} stays overlay-free on hover`,
      ).toBe('none');
      await page.mouse.move(4, 4);
      await page.waitForTimeout(40);
      const leaving = await sw.evaluate((el) => {
        const cs = getComputedStyle(el, '::after');
        return { content: cs.content, width: cs.width };
      });
      expect(leaving.content, `legend swatch ${i} paints nothing on mouse-out`).toBe('none');
      expect(leaving.width, `legend swatch ${i} is never viewport-sized`).not.toBe('1440px');
    }

    /* …and the grid keeps the affordance the swatches gave up — since
       arrangement-cell-state-labels (2026-11-07) the grid's words are the REAL
       span, so the receipt follows them there: the cell still says WHY it is
       blocked, the span fills the cell, and the retired CSS overlay stays
       `none` on the grid too (one mechanism, not two). */
    const cell = page.locator('.cell-content.cell-occupied').first();
    await cell.hover();
    await page.waitForTimeout(220);
    const gridReceipt = await cell.evaluate((el) => {
      const span = el.querySelector('.cell-state-label') as HTMLElement | null;
      const rect = el.getBoundingClientRect();
      const box = span ? span.getBoundingClientRect() : null;
      return {
        after: getComputedStyle(el, '::after').content,
        display: span ? getComputedStyle(span).display : 'none',
        words: span ? span.innerHTML.replace(/<br\s*\/?>/gi, ' ') : '',
        // the cell's own border (3px on a "mine" cell) sits outside the label's
        // containing block, so "fills" means: inside the cell, and never the
        // viewport-shaped overlay of 2026-11-07
        fills: box
          ? box.width <= rect.width + 1 && box.height <= rect.height + 1
            && box.width > rect.width * 0.8 && box.height > rect.height * 0.8
          : false,
      };
    });
    expect(gridReceipt.after, 'the old overlay is retired on the grid').toBe('none');
    expect(gridReceipt.display, 'the state label shows on hover').toBe('flex');
    expect(gridReceipt.words, 'a red cell names its reason').toMatch(/Conflict/);
    expect(gridReceipt.fills, 'the label fills its own cell, never the viewport').toBe(true);
  });
});
