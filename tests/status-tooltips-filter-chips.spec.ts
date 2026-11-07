import { test, expect, type Page } from '@playwright/test';

/* SDD status-tooltips-filter-chips — T5 receipt (the two specs' Receipts
   sections). House base: the frozen request-approval.spec.ts pattern —
   storageState staff-5425 (the demo's PL). NOTE: the tasks.md "staff 5770"
   note cannot reach /request-approval-ui (route mw = auth + pl; 5425 is the
   demo PL) — the existing spec's storageState governs; /:8000 DEMO DB is
   READ-ONLY throughout (the Overdue receipt shifts the IN-PAGE
   MockData.urgencyReferenceDate + re-renders — zero writes). */

test.use({ storageState: '.auth/staff-5425.json' });

const BASE = (process.env.CRS_BASE ?? 'http://localhost:8000');
const MRH = '/my-replacements';
const RA = '/request-approval-ui';

/* replacement-merge-one-table (T19): the merged page owns NO status map — the
   labels and tips are SERVER-built and arrive in the payload. These are the six
   strings `ReplacementRequestMapper::statusTip()` returns (design §1.6); the
   receipts below compare the rendered `data-tip` with the payload's own value,
   so a map that drifted on either side fails. */
const MRH_TIPS = [
  'No request has been made for this class yet — it still needs arranging.',
  'The Programme Leader has APPROVED your request.',
  'The Programme Leader has REJECTED your request.',
  'You MANUALLY CANCELLED this request.',
  'The replacement class has been CONDUCTED — this request is complete.',
];
/* Auto-reject rule (2026-11-06): a PENDING tip is two lines — the shipped
   sentence, then the deadline the rule fires at (`\n`, never HTML: the shared
   data-tip engine renders textContent with pre-line). The deadline is computed
   per request, so the receipt pins the SHAPE, not one calendar date. */
const MRH_PENDING_HEAD = "Your request has been Submitted and is AWAITING the Programme Leader's decision (Approve or Reject).";
/* 2026-11-06 UI update ("Pending Status Tooltip"): the lecturer sentence names the
   Auto-Reject Rule, the 3-working-hour requirement and the consequence, with the
   request's own deadline in the brief's parentheses. */
const MRH_PENDING_TAIL_RE = /^Auto-Reject Rule: If This Request Has Not Been Approved At Least 3 Working Hours \(([A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4}, \d{1,2}:\d{2} (AM|PM))\) Before The Proposed Class Start Time, It Will Be AUTOMATICALLY REJECTED\.$/;

function isLecturerStatusTip(tip: string | null): boolean {
  if (!tip) return false;
  if (MRH_TIPS.includes(tip)) return true;
  const [head, ...rest] = tip.split('\n');
  return head === MRH_PENDING_HEAD && rest.length === 1 && MRH_PENDING_TAIL_RE.test(rest[0]);
}
/* 1f/3B (2026-11-04): the queue badge now prints the shared M5 label and takes
   its tip from the payload's own `requestStatusTip` (PL voice) — the page-local
   3-key map is gone, which is why a Completed (or Cancelled) badge used to have
   no tip at all. Keys are the label's STATUS part: a Completed badge appends
   "— <n> days ago", so it is normalised before the lookup. */
const RA_MAP: Record<string, string> = {
  Approved: 'You have APPROVED this request.',
  Rejected: 'You have REJECTED this request.',
  Completed: 'The replacement class has been CONDUCTED — this request is complete.',
};
const RA_PENDING_HEAD = 'This request is AWAITING your review and decision (Approve or Reject).';
const RA_PENDING_TAIL_RE = /^If you have not Approved it by ([A-Z][a-z]{2}, \d{1,2} [A-Z][a-z]{2} \d{4}, \d{1,2}:\d{2} (AM|PM)), it will be Automatically Rejected\.$/;

function isPlStatusTip(key: string, tip: string | null): boolean {
  if (!tip) return false;
  if (key in RA_MAP) return tip === RA_MAP[key];
  if (key !== 'Pending') return false;
  const [head, ...rest] = tip.split('\n');
  return head === RA_PENDING_HEAD && rest.length === 1 && RA_PENDING_TAIL_RE.test(rest[0]);
}
const labelKey = (label: string): string => label.split(' — ')[0].trim();

/* the frozen urgency receipt regex (design.md:217) */
const URGENCY_RE =
  /^(Normal|Urgent|Overdue) — (\d+ days? (left before|past) the Proposed Class\.|the Proposed Class is TODAY\.)$/;

async function sharedTip(page: Page, target: string): Promise<{ text: string; above: boolean } | null> {
  await page.hover(target);
  await page.waitForTimeout(450);
  return await page.evaluate((sel) => {
    const tip = document.querySelector('.data-tip-tooltip');
    const el = document.querySelector(sel);
    if (!tip) return null;
    const t = tip.getBoundingClientRect();
    const e = el ? el.getBoundingClientRect() : null;
    return { text: tip.textContent || '', above: !e || t.bottom <= e.top + 2 };
  }, target);
}

async function setMRHAll(page: Page): Promise<number> {
  await page.selectOption('#statusFilter', 'all');
  await page.waitForTimeout(700);
  return await page.locator('#tableBody .badge[data-tip]').count();
}

async function setRAAll(page: Page): Promise<number> {
  await page.selectOption('#statusFilter', 'all');
  await page.waitForTimeout(700);
  return await page.locator('#tableBody span.badge[data-tip]').count();
}

/* ── MRH ─────────────────────────────────────────────────────────────── */

test('T5.MRH-1: per-row status hover loop + the FULL 5-string map fallback + fail-open', async ({ page }) => {
  await page.goto(`${BASE}${MRH}`);
  await page.waitForTimeout(1200);
  await setMRHAll(page);

  // every rendered badge carries the SERVER tip for its own row
  const badges = page.locator('#tableBody .col-status .badge[data-tip]');
  const count = await badges.count();
  expect(count, 'the page renders at least one status badge').toBeGreaterThan(0);

  const serverTips = await page.evaluate(() =>
    Array.from(document.querySelectorAll('#tableBody tr')).map((tr) => {
      const badge = tr.querySelector('.col-status .badge');
      const id = tr.getAttribute('data-request-id');
      const row = (window as any).MockData.conflictedClasses.find(
        (c: any) => String(c.requestId ?? '') === String(id ?? ''),
      );
      return { tip: badge?.getAttribute('data-tip') ?? null, payloadTip: row?.requestStatusTip ?? null };
    }),
  );

  for (const pair of serverTips) {
    expect(pair.tip, 'the badge tip comes from the payload').toBe(pair.payloadTip);
    expect(isLecturerStatusTip(pair.tip), `the server's own six-state tip (pending is two lines): ${JSON.stringify(pair.tip)}`).toBe(true);
  }

  const shared = await sharedTip(page, '#tableBody .col-status .badge[data-tip]');
  expect(shared, 'the SHARED data-tip engine renders it').not.toBeNull();
  expect(shared!.text).toBe(serverTips[0].tip);
  expect(shared!.above, 'the tooltip renders ABOVE the badge').toBe(true);
});

test('T5.MRH-2: resetFilters rename + tip; Clear-all law + individual ✕', async ({ page }) => {
  await page.goto(`${BASE}${MRH}`);
  await page.waitForTimeout(1200);

  // the rename + retip (and the old id is gone)
  await expect(page.locator('#resetFilters')).toHaveAttribute('data-tip', 'Reset all filters to DEFAULT');
  await expect(page.locator('#clearFilters')).toHaveCount(0);
  await expect(page.locator('#filterChips .filter-chip-clear-all')).toHaveCount(1); // persists at default

  // default-state chips law: exactly the Exclude COMPLETED Status chip + Clear all
  await page.waitForTimeout(400);
  let chips = await page.locator('#filterChips .filter-chip:not(.filter-chip-clear-all)');
  await expect(chips).toHaveCount(1);
  await expect(chips.first()).toContainText(/Exclude COMPLETED/);

  // two non-default filters → chips grow
  await page.fill('#searchInput', 'AMSE');
  await page.waitForTimeout(600);
  await page.selectOption('#statusFilter', 'approved');
  await page.waitForTimeout(600);
  chips = page.locator('#filterChips .filter-chip:not(.filter-chip-clear-all)');
  expect(await chips.count()).toBeGreaterThanOrEqual(3); // Exclude COMPLETED Status + Status + Search

  // SDD filter-clear-all-off (2026-10-30, the user's ruling): Clear all = EVERY
  // filter OFF — the exclusion chips go too, and the bar renders EMPTY (no
  // chips, no 'Active Filters:', no Clear-all pill). The Reset button keeps
  // the DEFAULT law (untouched).
  await page.locator('#filterChips .filter-chip-clear-all').click();
  await page.waitForTimeout(700);

  // every control OFF (not to ITS default — hideCompleted goes FALSE here;
  // the Reset button is the one that restores the default checkbox state)
  await expect(page.locator('#searchInput')).toHaveValue('');
  await expect(page.locator('#statusFilter')).toHaveValue('all');
  await expect(page.locator('#hideCompleted')).not.toBeChecked();
  // the bar renders NOTHING: no chips, no label, no Clear-all pill
  chips = page.locator('#filterChips .filter-chip');
  await expect(chips).toHaveCount(0);
  await expect(page.locator('#filterChips')).toHaveText('');
  await expect(page.locator('#filterChips .filter-chip-clear-all')).toHaveCount(0);
  await expect(page.locator('#filterChips .filter-chips-label')).toHaveCount(0);

  // individual ✕ law still stands: re-arm ONE chip via the visible toggle,
  // then the ✕ empties the bar
  await page.locator('#completedToggle').click(); // the hidden switch's visible label
  await page.waitForTimeout(400);
  chips = page.locator('#filterChips .filter-chip:not(.filter-chip-clear-all)');
  await expect(chips).toHaveCount(1);
  await expect(chips.first()).toContainText(/Exclude COMPLETED/);
  await page.locator('#filterChips .filter-chip .filter-chip-remove').first().click();
  await page.waitForTimeout(600);
  await expect(page.locator('#hideCompleted')).not.toBeChecked();
  await expect(page.locator('#filterChips')).toHaveText(''); // the bar empty again
});

test('T5.MRH-3: detail-modal Status row + mobile-card threading', async ({ page }) => {
  await page.goto(`${BASE}${MRH}`);
  await page.waitForTimeout(1200);
  const badgeEl = page.locator('#tableBody .col-status .badge[data-tip]').first();
  const status = (((await badgeEl.textContent()) || '')).trim();
  const expected = await badgeEl.getAttribute('data-tip');

  // the ROW is the door (M6): clicking the badge bubbles to the row's handler
  await badgeEl.click();
  await page.waitForTimeout(700);
  const modalBadge = page.locator('#quickViewModal .modal-body .badge[data-tip]').first();
  await expect(modalBadge).toHaveText(status);
  await expect(modalBadge).toHaveAttribute('data-tip', expected);
  await page.keyboard.press('Escape');

  // mobile card: the shared HtmlBuilder badge threads opts.statusTip
  await page.setViewportSize({ width: 375, height: 800 });
  await page.waitForTimeout(600);
  const cardBadge = page.locator('#cardView .replacement-card .badge[data-tip]').first();
  await expect(cardBadge).toBeVisible();
  const cardTip = await cardBadge.getAttribute('data-tip');
  expect(isLecturerStatusTip(cardTip), 'the card badge carries the same server tip').toBe(true);
  const shared = await sharedTip(page, '#cardView .replacement-card .badge[data-tip]');
  expect(shared).not.toBeNull();
  expect(shared!.above).toBe(true);
});

/* ── RA ──────────────────────────────────────────────────────────────── */

test('T5.RA-1: every status badge carries the payload tip (one source, no local map)', async ({ page }) => {
  await page.goto(`${BASE}${RA}`);
  await page.waitForTimeout(1400);
  const count = await setRAAll(page);
  expect(count, 'the queue renders at least one status badge on All').toBeGreaterThan(0);

  /* NO [data-tip] filter: a badge without a tip must fail here — that was the
     real defect (Completed/Cancelled rendered bare under the 3-key map). */
  const badges = page.locator('#tableBody td span.badge');
  const n = await badges.count();
  expect(n, 'status badges render').toBeGreaterThan(0);
  for (let i = 0; i < n; i++) {
    const b = badges.nth(i);
    const label = ((await b.textContent()) || '').trim();
    const key = labelKey(label);
    expect(key, `queue badge '${label}' is a known state (Completed included now)`).toBeTruthy();
    const tip = await b.getAttribute('data-tip');
    expect(isPlStatusTip(key, tip), `'${label}' carries its tip (pending adds the auto-reject deadline)`).toBe(true);
  }

  /* the payload is the single source: each rendered badge's text AND tip are
     the row's own `requestStatusLabel` / `requestStatusTip`, not a second copy */
  const drift = await page.evaluate(() => {
    const rows = ((window as any).MockData?.approvalRequests ?? []) as any[];
    const byId = new Map<string, any>(rows.map((r) => [String(r.id), r]));
    return Array.from(document.querySelectorAll('#tableBody tr[data-id]')).map((tr) => {
      const id = (tr as HTMLElement).dataset.id || '';
      const row = byId.get(id);
      const badge = tr.querySelector('td span.badge');
      return {
        id,
        same:
          !!row &&
          !!badge &&
          (badge.textContent || '').trim() === row.requestStatusLabel &&
          (badge.getAttribute('data-tip') || '') === row.requestStatusTip,
      };
    });
  });
  expect(drift.length).toBeGreaterThan(0);
  expect(drift.filter((d) => !d.same).map((d) => d.id), 'rows whose badge differs from the payload').toEqual([]);

  const shared = await sharedTip(page, '#tableBody td span.badge[data-tip]');
  expect(shared!.above).toBe(true);
});

test('T5.RA-2: urgency badge↔tip word-per-row; UNCONDITIONAL Overdue shift; TODAY-string; Urgent filter keeps Overdue', async ({ page }) => {
  await page.goto(`${BASE}${RA}`);
  await page.waitForTimeout(1400);

  // ensure rows on screen (All) for the loop
  await setRAAll(page);
  const urg = page.locator('#tableBody span.urgency-badge[data-tip]');
  const n = await urg.count();
  expect(n).toBeGreaterThan(0);

  // per-row: the format regex AND the badge↔tip leading-word consistency
  for (let i = 0; i < n; i++) {
    const b = urg.nth(i);
    const label = ((await b.textContent()) || '').trim();
    const tip = (await b.getAttribute('data-tip')) || '';
    expect(tip).toMatch(URGENCY_RE);
    /* request-approval-ui CONSISTENCY 1e (2026-11-03): the badge now prints the
       level AND the remaining days (`Normal — 67 days left`); the tip keeps its
       fuller sentence, so the shared part is the leading LEVEL word. */
    expect(label).toMatch(/^(Normal|Urgent|Overdue) — /);
    expect(tip.startsWith(label.split(' — ')[0] + ' — '), `badge '${label}' ↔ tip leading word`).toBe(true);
  }

  const shift = await page.evaluate(() => {
    const first = document.querySelector('#tableBody tr[data-id]');
    const id = first ? Number((first as HTMLElement).dataset.id) : null;
    const row = MockData.approvalRequests.find((r: any) => r.id === id) || MockData.approvalRequests[0];
    const orig = MockData.urgencyReferenceDate;
    // UNCONDITIONAL Overdue: shift the in-page reference a YEAR after the row
    /* 2026-11-07 (user law): urgency measures the Proposed Replacement date */
    MockData.urgencyReferenceDate = new Date((new Date((row.replacementDate || row.classDate) + 'T00:00:00')).getTime() + 365 * 86400000);
    renderTable();
    const badges = Array.from(document.querySelectorAll('#tableBody span.urgency-badge[data-tip]')) as HTMLElement[];
    const over = badges.map((b) => ({ label: (b.textContent || '').trim(), tip: b.getAttribute('data-tip') || '' }));
    const urgentRows = document.querySelectorAll('#tableBody tr.row-urgent').length;
    // TODAY-string: anchor the reference EXACTLY on the first visible row's date
    MockData.urgencyReferenceDate = new Date((row.replacementDate || row.classDate) + 'T00:00:00');
    renderTable();
    const today = Array.from(document.querySelectorAll('#tableBody span.urgency-badge[data-tip]'))
      .map((b) => ({ label: (b.textContent || '').trim(), tip: b.getAttribute('data-tip') || '' }));
    // restore + hand the state back (the page never writes anywhere)
    MockData.urgencyReferenceDate = orig;
    renderTable();
    return { over, urgentRows, today };
  });

  expect(shift.over.length).toBeGreaterThan(0);
  for (const o of shift.over) {
    expect(o.label).toMatch(/^Overdue — \d+ days? past$/);          // solid-red badge + days past
    expect(o.tip).toMatch(/^Overdue — \d+ days? past the Proposed Class\.$/); // days past
  }
  expect(shift.urgentRows).toBeGreaterThan(0);                    // row-urgent kept
  // the Urgent FILTER keeps overdue rows (urgencyMatches law) — one clean
  // re-shift + filter + render, all inside the page
  const overdueUnderUrgentFilter = await page.evaluate(() => {
    const first = document.querySelector('#tableBody tr[data-id]') as HTMLElement | null;
    const id = first ? Number(first.dataset.id) : null;
    const row = MockData.approvalRequests.find((r: any) => r.id === id) || MockData.approvalRequests[0];
    MockData.urgencyReferenceDate = new Date((new Date((row.replacementDate || row.classDate) + 'T00:00:00')).getTime() + 365 * 86400000);
    const s = document.getElementById('statusFilter') as HTMLSelectElement;
    s.value = 'all';
    renderTable();
    setUrgencyFilter('urgent');
    return Array.from(document.querySelectorAll('#tableBody span.urgency-badge[data-tip]'))
      .map((b) => (b.textContent || '').trim());
  });
  expect(overdueUnderUrgentFilter.length).toBeGreaterThan(0);
  for (const label of overdueUnderUrgentFilter) expect(label).toMatch(/^Overdue — /); // ALL overdue under the Urgent filter

  expect(shift.today.some((t) => t.label === 'Urgent — today' && t.tip === 'Urgent — the Proposed Class is TODAY.'), 'the N=0 TODAY receipt (user-kept tip wording 2026-11-01)').toBe(true);

  // the column-header tip equals the pinned three-level copy
  const headerTip = await page.locator('#tableHead th[data-tip]', { hasText: 'Urgency' }).getAttribute('data-tip');
  expect(headerTip).toBe('Days until the proposed replacement class: past = Overdue (solid red), ≤3 days = Urgent (red), 4+ days = Normal');
});

test('T5.RA-3: resetFilters + label + Clear-all law + ✕ + mobile cards (status + urgency threading)', async ({ page }) => {
  await page.goto(`${BASE}${RA}`);
  await page.waitForTimeout(1400);

  await expect(page.locator('#resetFilters')).toHaveAttribute('data-tip', 'Reset all filters to DEFAULT');
  await expect(page.locator('#clearFilters')).toHaveCount(0);
  await expect(page.locator('#filterChips .filter-chips-label')).toHaveText('Active Filters:'); // was 'Filters:'
  // default chips: Status: Pending + Exclude COMPLETED Status (+ Clear all persists at default)
  await page.waitForTimeout(400);
  let chips = page.locator('#filterChips .filter-chip:not(.filter-chip-clear-all)');
  await expect(chips).toHaveCount(2);
  await expect(chips.first()).toContainText('Status: Pending');
  await expect(chips.nth(1)).toContainText('Exclude COMPLETED Status');
  await expect(page.locator('#filterChips .filter-chip-clear-all')).toHaveCount(1);

  // non-default filters → chips grow
  await page.fill('#searchInput', 'AMSE');
  await page.waitForTimeout(600);
  await page.selectOption('#statusFilter', 'Approved');
  await page.waitForTimeout(600);
  chips = page.locator('#filterChips .filter-chip:not(.filter-chip-clear-all)');
  expect(await chips.count()).toBeGreaterThanOrEqual(3); // Status(Approved) + Exclude COMPLETED Status + Search

  // individual ✕ first (the pre-law state still suits it): remove the
  // 'Status: Approved' chip → the others persist
  await page.locator('#filterChips .filter-chip .filter-chip-remove').first().click();
  await page.waitForTimeout(600);
  await expect(page.locator('#statusFilter')).toHaveValue('all');
  chips = page.locator('#filterChips .filter-chip:not(.filter-chip-clear-all)');
  expect(await chips.count()).toBeGreaterThanOrEqual(2); // Exclude COMPLETED Status + Search remain
  await expect(page.locator('#filterChips .filter-chip-clear-all')).toHaveCount(1);

  // SDD filter-clear-all-off (2026-10-30, the user's ruling): Clear all = EVERY
  // filter OFF — status goes to 'all' (not the default 'Pending'), the
  // hideCompleted exclusion turns OFF, and the bar renders EMPTY (no chips,
  // no 'Active Filters:', no Clear-all pill). The Reset button keeps the
  // DEFAULT law (untouched).
  await page.locator('#filterChips .filter-chip-clear-all').click();
  await page.waitForTimeout(700);
  await expect(page.locator('#searchInput')).toHaveValue('');
  await expect(page.locator('#statusFilter')).toHaveValue('all');
  await expect(page.locator('#hideCompletedToggle')).not.toBeChecked();
  chips = page.locator('#filterChips .filter-chip');
  await expect(chips).toHaveCount(0); // default chips go too — EVERYTHING off
  await expect(page.locator('#filterChips')).toHaveText(''); // no label text either
  await expect(page.locator('#filterChips .filter-chip-clear-all')).toHaveCount(0);

  // mobile cards: status tip + urgency tip through the shared HtmlBuilder
  await page.setViewportSize({ width: 375, height: 800 });
  await page.waitForTimeout(600);
  const cardStatus = page.locator('#requestsCards .request-card .badge[data-tip]').first();
  await expect(cardStatus).toBeVisible();
  await expect(cardStatus).toHaveAttribute('data-tip', RA_MAP[labelKey(((await cardStatus.textContent()) || '').trim())]);
  const cardUrg = page.locator('#requestsCards .request-card .urgency-badge[data-tip]').first();
  await expect(cardUrg).toBeVisible();
  await expect(((await cardUrg.getAttribute('data-tip')) || '')).toMatch(URGENCY_RE);
  const shared = await sharedTip(page, '#requestsCards .request-card .urgency-badge[data-tip]');
  expect(shared).not.toBeNull();
  expect(shared!.above).toBe(true);
});
