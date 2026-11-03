import { test, expect } from '@playwright/test';
import { execSync } from 'node:child_process';

/**
 * request-completed-status — the BROWSER receipts (R3/R4/R5 of
 * specs/request-status-display/spec.md).
 *
 * These receipts need a row whose replacement class has ALREADY happened, and
 * neither demo database has one that reads Completed today:
 *   · class_replacement_fresh — no approved request at all;
 *   · class_replacement_viva  — request 27 is approved at week 7, and its
 *     replacement date is TODAY (3 Nov 2026), so by C2 it reads Approved today
 *     and Completed only from 4 Nov. No demo/viva write is made to force it (C9).
 *
 * So the spec runs against the DISPOSABLE testing DB on :8030, plants ONE
 * fixture (id-keyed) and sweeps exactly those ids afterwards. Module-level skip
 * unless the testing lane is selected AND the psql target is the testing DB
 * (hardcoded below — this spec can never touch a demo database).
 *
 *   DB_DATABASE=class_replacement_testing php artisan migrate:fresh --seed
 *   scripts/dev-serve.sh 8030            # with DB_DATABASE=class_replacement_testing
 *   CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030 \
 *     npx playwright test tests/request-completed-status.spec.ts
 */

const TESTING_LANE = (process.env.CRS_MODE === 'testing')
  && (process.env.CRS_ALLOW_SQL === '1')
  && String(process.env.CRS_BASE ?? '').includes('8030');

test.skip(!TESTING_LANE, 'request-completed-status receipts need the disposable testing DB on :8030 (see the header)');

const BASE = String(process.env.CRS_BASE ?? '');
const PSQL = 'psql -q -t -U philler -d class_replacement_testing -c ';

const FIXTURE = { requestId: 0, slotId: 0, exceptionId: 0, userId: 0, sessionId: 23 };

function sql(query: string): string {
  const oneLine = query.replace(/[\r\n]+/g, ' ').replace(/\s{2,}/g, ' ').trim();
  return execSync(PSQL + JSON.stringify(oneLine), { stdio: 'pipe' }).toString().trim();
}

function sweep(): void {
  if (FIXTURE.requestId) {
    sql(`DELETE FROM replacement_request_slots WHERE replacement_request_id = ${FIXTURE.requestId};`);
    sql(`DELETE FROM replacement_requests WHERE id = ${FIXTURE.requestId};`);
  }
  if (FIXTURE.slotId) sql(`DELETE FROM time_slots WHERE id = ${FIXTURE.slotId};`);
  if (FIXTURE.exceptionId) sql(`DELETE FROM class_exceptions WHERE id = ${FIXTURE.exceptionId};`);
  FIXTURE.requestId = 0;
  FIXTURE.slotId = 0;
  FIXTURE.exceptionId = 0;
}

/**
 * One approved request whose replacement class is two MONTHS ago, on a
 * conflicted occurrence of staff 5425 (session 23 = BMSE3153 (P), Tue 13:00).
 * Week 2 of semester 202605 (start 2026-09-21) is 2026-09-29 — safely past.
 */
function plant(): void {
  sweep();
  FIXTURE.userId = Number(sql(`SELECT u.id FROM users u JOIN lecturers l ON l.user_id = u.id WHERE l.staff_id = '5425' LIMIT 1;`));
  const semesterId = Number(sql(`SELECT id FROM semesters ORDER BY id LIMIT 1;`));

  FIXTURE.exceptionId = Number(sql(`INSERT INTO class_exceptions (class_session_id, week_number, reason, created_at, updated_at)
      VALUES (${FIXTURE.sessionId}, 3, 'official_event', now(), now()) RETURNING id;`));
  FIXTURE.slotId = Number(sql(`INSERT INTO time_slots (semester_id, class_session_id, week_number, day_of_week, start_time, end_time, venue_id, status, version, created_at, updated_at)
      VALUES (${semesterId}, NULL, 2, 1, '17:00:00', '17:30:00', 1, 'available', 1, now(), now()) RETURNING id;`));
  FIXTURE.requestId = Number(sql(`INSERT INTO replacement_requests (semester_id, proposer_id, class_session_id, week_number, replacement_time_slot_id, status, submitted_at, decided_at, created_at, updated_at)
      VALUES (${semesterId}, ${FIXTURE.userId}, ${FIXTURE.sessionId}, 3, ${FIXTURE.slotId}, 'approved', now(), now(), now(), now()) RETURNING id;`));
}

/** The setup project logs in against CRS_BASE, so its storageState is this
 *  run's host — no extra login here (the Fortify limiter is 5/min per
 *  login_id+ip and the setup already spends one). */
test.use({ storageState: '.auth/staff-5425.json' });

test.beforeAll(() => {
  plant();
});

test.afterAll(() => {
  sweep();
});

test('receipt 1 — the home toggle is checked on load and hides the Completed row, and unticking reveals it (R3/R4)', async ({ page }) => {
  await page.goto(`${BASE}/replacement-home-ui?bust=pw-rc1`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);

  // C4: the toggle exists, is labelled and CHECKED on every load
  const toggle = page.locator('#completedToggle');
  await expect(toggle).toHaveCount(1);
  await expect(toggle).toContainText('Exclude Completed');
  await expect(page.locator('#hideCompleted')).toBeChecked();

  const badge = page.locator('#tableBody a.badge-link[href$="r' + FIXTURE.requestId + '"]');
  await expect(badge, 'R3: while Exclude Completed is ON the completed row is not listed').toHaveCount(0);

  // untick → the row appears with a Completed badge that carries this request's id
  // (the input itself is visually hidden behind the styled track — click that)
  await page.locator('#completedToggle .toggle-track').click();
  await page.waitForTimeout(700);
  await expect(badge).toHaveCount(1);
  await expect(badge).toHaveText('Completed');
  await expect(badge).toHaveAttribute('href', `/my-request-history-ui?notif-hl=r${FIXTURE.requestId}`);

  // the row is a handled row: no Arrange button, and the em dash in Days Left
  /* the `has` locator is resolved INSIDE each row — never nest `#tableBody` in it */
  const row = page.locator('#tableBody tr', {
    has: page.locator(`a.badge-link[href$="r${FIXTURE.requestId}"]`),
  });
  await expect(row.locator('.col-action .btn-action')).toHaveCount(0);
  await expect(row.locator('.col-urgency')).toHaveText('—');

  // re-ticking hides it again (the filter really runs both ways)
  await page.locator('#completedToggle .toggle-track').click();
  await page.waitForTimeout(600);
  await expect(badge).toHaveCount(0);
});

test('receipt 2 — the Completed badge lands on its row and the reveal exception un-hides it (R4/R5)', async ({ page }) => {
  await page.goto(`${BASE}/replacement-home-ui?bust=pw-rc2`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);

  await page.locator('#completedToggle .toggle-track').click();
  await page.waitForTimeout(600);
  const badge = page.locator('#tableBody a.badge-link[href$="r' + FIXTURE.requestId + '"]');
  await expect(badge).toHaveCount(1);
  await badge.click();

  await page.waitForURL(/my-request-history-ui/, { timeout: 15_000 });
  const row = page.locator(`tr[data-id="${FIXTURE.requestId}"]`);

  // R5: the toggle is the ONLY reason this row was missing, so the page
  // un-ticks it for the user and shows the row…
  await expect(row, 'the completed request is revealed, not toasted').toHaveCount(1, { timeout: 10_000 });
  await expect(page.locator('#hideCompleted')).not.toBeChecked();
  await expect(row.locator('.badge').first()).toHaveText('Completed');
  // …and the saved filter now matches what the user is looking at
  const saved = await page.evaluate(() => localStorage.getItem('mrh-filters'));
  expect(saved === null || JSON.parse(saved).excludeCompleted === false).toBe(true);
});

test('receipt 3 — any OTHER hiding cause keeps the toast and leaves the toggle alone (R5)', async ({ page }) => {
  await page.goto(`${BASE}/my-request-history-ui?bust=pw-rc3`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(800);

  // a status filter that excludes Completed — a second hiding cause
  await page.selectOption('#statusFilter', 'Pending');
  await page.waitForTimeout(600);

  await page.goto(`${BASE}/my-request-history-ui?notif-hl=r${FIXTURE.requestId}`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(1200);

  const toast = page.locator('#toastBar');
  await expect(toast).toBeVisible();
  await expect(toast.locator('.toast-message')).toContainText('hidden by your current filters');
  await expect(page.locator('#hideCompleted'), 'the toggle is NOT touched when another filter hides the row').toBeChecked();
  await expect(page.locator(`tr[data-id="${FIXTURE.requestId}"]`)).toHaveCount(0);
});
