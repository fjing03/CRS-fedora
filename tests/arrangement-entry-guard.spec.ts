import { test, expect } from '@playwright/test';
import type { BrowserContext, Page } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * ENTRY GUARD RECEIPT (lane arrangement-entry-guard, 2026-11-02) — the user's
 * rule: PREVENT entry for an occurrence that is already under an ACTIVE
 * (pending/approved) replacement request. "Only unproposed + self/own classes."
 *
 * THREE layers of proof, all against the DISPOSABLE testing DB (:8030):
 *   1. SERVER (mount)     — a fresh entry (typed URL / F5 / link) redirects out
 *                           with ?blocked= and the landing page toasts it.
 *   2. BFCACHE (pageshow) — a RESTORED DOM must not survive once the occurrence
 *                           became handled; the client's revalidateEntry call
 *                           (fired on pageshow persisted=true) re-runs the same
 *                           check server-side. Simulated deterministically: the
 *                           page is loaded UNBLOCKED, the pending request is
 *                           created AFTER load (exactly the Back-after-submit
 *                           situation), then a synthetic persisted pageshow is
 *                           dispatched.
 *   3. WITHDRAWAL LOOP    — cancelling/rejecting the request makes the
 *                           occurrence unproposed again → the same URL loads.
 * Plus: the pin-first half of the URL-vs-pin doctrine (a pending request on the
 * LIVE PIN blocks even when the URL pair is free).
 *
 * Fixture: ONE replacement_requests row per test, id-keyed + swept (the T7
 * hygiene); never a class_exceptions write, never the demo DB :8000.
 *
 * REQUIRES the testing DB seeded + :8030:
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   DB_DATABASE=class_replacement_testing php artisan serve --port=8030 &
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/arrangement-entry-guard.spec.ts
 */

const BASE = process.env.CRS_BASE ?? '';
const ALLOWED = process.env.CRS_MODE === 'testing'
  && process.env.CRS_ALLOW_SQL === '1'
  && BASE.includes(':803');

const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';

/* session 99 = AMSE1003 (L) Mon 09:00–11:00 (staff 5425's own class, seed
   semester 1) — the SAME occurrence the other arrangement receipts boot. */
const SESSION_ID = 99;
const WEEK = 8;

function sql(query: string): string {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  return execSync(PSQL + JSON.stringify(oneLine), { encoding: 'utf8' });
}

/** One ACTIVE request for (session 99, week 8) — idempotent: any earlier row
    for the pair is removed first (the partial unique allows one active row). */
function insertPendingRequest(status = 'pending'): void {
  sweepRequest();
  sql(`INSERT INTO replacement_requests
         (semester_id, proposer_id, class_session_id, week_number, replacement_time_slot_id,
          status, submitted_at, created_at, updated_at)
       VALUES (1, 1, ${SESSION_ID}, ${WEEK},
               (SELECT id FROM time_slots WHERE status = 'available' ORDER BY id LIMIT 1),
               '${status}', now(), now(), now());`);
}

function sweepRequest(): void {
  /* the guard spec's last test SUBMITS through the real pipeline, which
     (a) writes audit_logs rows pointing at the request (that FK has no
     cascade) and (b) flips the reserved time_slots to 'pending'. Both are
     undone FIRST so the spec is run-idempotent (the T7 hygiene): the slot
     reset is read from the request rows BEFORE they go. */
  sql(`UPDATE time_slots SET status = 'available', version = version + 1
         WHERE id IN (SELECT replacement_time_slot_id FROM replacement_requests
                        WHERE class_session_id = ${SESSION_ID} AND week_number = ${WEEK})
            OR id IN (SELECT rrs.time_slot_id FROM replacement_request_slots rrs
                        JOIN replacement_requests r ON r.id = rrs.replacement_request_id
                       WHERE r.class_session_id = ${SESSION_ID} AND r.week_number = ${WEEK});`);
  sql(`DELETE FROM audit_logs WHERE replacement_request_id IN
         (SELECT id FROM replacement_requests WHERE class_session_id = ${SESSION_ID} AND week_number = ${WEEK});`);
  sql(`DELETE FROM replacement_requests WHERE class_session_id = ${SESSION_ID} AND week_number = ${WEEK};`);
}

const TOAST_TEXT = 'already has a PENDING replacement request';

/* ONE UI login per run, shared browser context: the Fortify limiter is
   5 logins/min per identity+IP and `artisan serve` is single-threaded, so a
   per-test login flaked on the login page itself (measured ~1 in 4 runs),
   never on the guard. The run's saved storageState CANNOT be reused here:
   auth.setup.ts saves it for the baseURL host (localhost:8000), and this spec
   deliberately runs on the testing host (127.0.0.1:8030) — cookies are
   host-scoped. */
let context: BrowserContext;
let page: Page;

async function login(p: Page): Promise<void> {
  await p.goto(`${BASE}/login/staff?_=guard-login-${Date.now()}`);
  await p.fill('#login_id', '5425');
  await p.fill('#password', 'Tarumt@2026');
  await p.click('#loginBtn');
  await p.waitForURL(/my-timetable|dashboard/, { timeout: 60_000 });
}

/* the guard spec boots the heavy arrangement payload twice per test; the
   global 30s test timeout leaves no room for the login waitForURL, so this
   file opts into a wider budget (infra headroom, not a product change). */
test.describe.configure({ mode: 'serial', timeout: 90_000 });

test.beforeAll(async ({ browser }) => {
  if (!ALLOWED) return;
  sweepRequest();
  context = await browser.newContext();
  page = await context.newPage();
  await login(page);
});
test.afterAll(async () => {
  if (!ALLOWED) return;
  sweepRequest();
  await context?.close();
});
test.beforeEach(() => { if (ALLOWED) sweepRequest(); });
test.afterEach(() => { if (ALLOWED) sweepRequest(); });

test('layer A: a fresh entry for a pending occurrence redirects out with the reason', async () => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');
  insertPendingRequest();

  await page.goto(`${BASE}/replacement-arrangement?session=${SESSION_ID}&week=${WEEK}&from=my-timetable&_=${Date.now()}`);
  await page.waitForURL(/blocked=request-pending/, { timeout: 20_000 });

  // the landing page is the `from` source, NOT the arrangement page
  expect(page.url()).toContain('/my-timetable-ui?blocked=request-pending');
  // …and it says WHY, with the management link (house arrival toast)
  const toast = page.locator('#toastBar .toast-message');
  await expect(toast).toContainText(TOAST_TEXT, { timeout: 10_000 });
  await expect(page.locator('#toastBar .toast-link')).toHaveAttribute('href', '/my-replacements');
});

test('layer A: an APPROVED occurrence blocks the same way (from the default landing)', async () => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');
  insertPendingRequest('approved');

  await page.goto(`${BASE}/replacement-arrangement?session=${SESSION_ID}&week=${WEEK}&_=${Date.now()}`);
  await page.waitForURL(/blocked=request-approved/, { timeout: 20_000 });

  expect(page.url()).toContain('/my-replacements?blocked=request-approved');
  await expect(page.locator('#toastBar .toast-message')).toContainText('already has an APPROVED replacement', { timeout: 10_000 });
});

test('layer B: a restored (bfcache) DOM is revalidated and redirected once the occurrence is handled', async () => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');

  // 1. load the page UNBLOCKED — this is the DOM the browser would freeze
  await page.goto(`${BASE}/replacement-arrangement?session=${SESSION_ID}&week=${WEEK}&from=my-timetable&_=${Date.now()}`);
  await page.waitForTimeout(2500);
  expect(page.url()).toContain('/replacement-arrangement');
  expect(await page.locator('#subjectSelector').isVisible()).toBe(true);

  // 2. the user submits elsewhere / the request exists NOW — the frozen page
  //    knows nothing about it (exactly the Back-after-submit situation)
  insertPendingRequest();

  // 3. the browser restores the page: pageshow with persisted = true
  await page.evaluate(() => {
    window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
  });

  // 4. the client's revalidateEntry call runs the server guard → redirect out
  await page.waitForURL(/blocked=request-pending/, { timeout: 20_000 });
  expect(page.url()).toContain('/my-timetable-ui?blocked=request-pending');
});

test('layer B: the LIVE PIN is validated first — a pending request on the pin blocks even when the URL pair is free', async () => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');

  // URL pair = week 3 (NOT the pending one) — the URL-vs-pin doctrine: after a
  // post-boot subject switch the URL lags the pin, so an URL-only key is a hole
  await page.goto(`${BASE}/replacement-arrangement?session=${SESSION_ID}&week=3&from=my-timetable&_=${Date.now()}`);
  await page.waitForTimeout(2500);

  // move the LIVE PIN to (99, 8) exactly as an auto-pin would, then make that
  // occurrence handled
  await page.evaluate(() => {
    (window as unknown as { pinnedOccurrence: { sessionId: number; week: number } }).pinnedOccurrence = { sessionId: 99, week: 8 };
  });
  insertPendingRequest();

  await page.evaluate(() => {
    window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true }));
  });

  await page.waitForURL(/blocked=request-pending/, { timeout: 20_000 });
});

test('the withdrawal loop: a cancelled/rejected request makes the occurrence unproposed again', async () => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');
  insertPendingRequest('rejected');

  await page.goto(`${BASE}/replacement-arrangement?session=${SESSION_ID}&week=${WEEK}&from=my-timetable&_=${Date.now()}`);
  await page.waitForTimeout(2500);

  // NO redirect: the page renders for the unproposed occurrence again
  expect(page.url()).toContain('/replacement-arrangement');
  expect(await page.locator('#subjectSelector').isVisible()).toBe(true);
  expect(await page.locator('#toastBar .toast-message').textContent()).not.toContain(TOAST_TEXT);
});

test('the user\u2019s repro, end to end: arrange \u2192 submit \u2192 Back is blocked with the reason', async () => {
  test.skip(!ALLOWED, 'testing-DB-only receipt (:8030)');

  // 1. the REAL flow: boot the conflicted occurrence, pick a bookable 2h run,
  //    submit through the page's own confirm modal
  await page.goto(`${BASE}/replacement-arrangement?session=${SESSION_ID}&week=${WEEK}&from=my-timetable&_=${Date.now()}`);
  await page.waitForTimeout(2500);

  const spot = await page.evaluate(() => {
    /* the block needs BLOCK_SPAN (4) CONTIGUOUS free cells — scan the grid */
    for (const day of [0, 1, 2, 3, 4]) {
      for (let h = 0; h < 20; h++) {
        const cells = [0, 1, 2, 3].map((k) =>
          document.querySelector(`#tableBody td[data-day="${day}"][data-hour="${h + k}"] .cell-content`));
        if (cells.every((c) => c && c.classList.contains('cell-available'))) return { day, hour: h };
      }
    }
    return null;
  });
  expect(spot).not.toBeNull();

  await page.locator(`#tableBody td[data-day="${spot!.day}"][data-hour="${spot!.hour}"] .cell-content`).click();
  await expect(page.locator('#tableBody .event-selection')).toHaveCount(1);
  await page.click('button:has-text("Submit Request")');
  await page.locator('#confirmModal button', { hasText: 'Yes, Submit Request' }).first().click();
  await page.waitForURL(/\/my-replacements/, { timeout: 30_000 });

  // 2. BACK — the occurrence is now handled, so the page must NOT come back
  //    with the Conflict Schedule panel. Whether the browser serves a bfcache
  //    restore (layer B revalidates) or a fresh load (layer A guards the
  //    mount), the END STATE is the same: out of the page, with the reason.
  await page.goBack();
  await page.waitForURL(/blocked=request-pending/, { timeout: 30_000 });
  expect(page.url()).toContain('/my-timetable-ui?blocked=request-pending');
  await expect(page.locator('#toastBar .toast-message')).toContainText(TOAST_TEXT, { timeout: 10_000 });
});
