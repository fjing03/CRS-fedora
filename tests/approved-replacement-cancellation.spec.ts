import { test, expect, type Page } from '@playwright/test';
import { abortUnlessTestingTarget } from './helpers/receipt-target-guard';

/**
 * approved-replacement-cancellation.spec.ts — T3 (D4) browser COMPONENT tests.
 *
 * READ-ONLY contract tests of the shared CancelClassModal client flow for an
 * OPENED APPROVED replacement, running against the SERVED public/js/ui-common.js
 * on /login/staff (the one route that loads it without an auth session) with
 * the ACTUAL partial markup `partials/ui-cancel-class-modal.blade.php` injected
 * (Blade comments stripped; the compact irreversible include replaced by its
 * verbatim rendered <p> — ui-irreversible-line.blade.php, compact arm).
 *
 * HONEST SCOPE (labelled): this is NOT a DB-lifecycle suite. The real backend
 * write path (locks, receipts, releases, notifications) is proven by the
 * separate backend Feature tests (design D6). Here:
 *   - `window.pageLivewire` is a LOCAL STUB: every Livewire call is recorded
 *     into a local JS array and answered from a synthetic in-test result;
 *   - MockData.currentUser/semester/events are LOCAL fixtures (page-level
 *     only) so the REAL ClassCancellation preflight (S1–S5 + validate) runs;
 *   - every non-GET/HEAD transport (incl. the hashed /livewire-f983032f/update
 *     and notifications/read) is ABORTED and Arrange Now navigation is
 *     intercepted (route-fulfilled locally) — no app mutation can reach the
 *     server from this suite;
 *   - Arrange Now is recorded by wrapping/defining goToReplacementFor (its
 *     production form is /replacement-arrangement?session=..&week=..), with
 *     the route backstop proving the same fact if the wrap is ineffective.
 *
 * Covered (D4): exact single approved call identity (source session, LANDING
 * week/day, requestId) with NO recurring ledger/MockData writes; mixed
 * approved+normal+pending day batch as separate lists; defensive partial
 * receipt (tutorial-only) rendering only acknowledged rows; source-week
 * Arrange Now; server-failure inline hint with no local mutation; missing
 * requestId refusal; missing exact single receipt NOT invented; success
 * dismiss refresh callback; _loadDayScope same-session other-week retention
 * + own make-up note removal; stale async scope answer ignored. Plus
 * responsive light/dark screenshots at 1440 and 390 (SYNTHETIC component UI
 * only — fixture-driven, not a live page).
 *
 * Fixture independence: the request ids (5501/5502), session ids (77/88/99)
 * and week numbers are synthetic — nothing here depends on the manual
 * server's request #19 or any live row.
 */

abortUnlessTestingTarget('tests/approved-replacement-cancellation.spec.ts');

/* ui-common.js globals — lexical consts / page payload globals referenced
   inside page.evaluate. Ambient declarations only (erased at runtime). */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
declare const CancelClassModal: any;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
declare const ClassCancellation: any;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
declare const MockData: any;

/** The harness surface this spec installs on window (compile-time only). */
type Harness = {
  __lwCalls: { method: string; args: unknown[] }[];
  __lwPending: { res: (v: unknown) => void }[];
  __lwNext: unknown;
  __navLog: { sessionId: unknown; week: unknown; page: string }[];
  __refreshed: boolean;
  __resetState: () => void;
  __openApproved: (o?: Record<string, unknown>, opts?: { recordRefresh?: boolean }) => void;
  __resolveDay: (i: number, p: unknown) => void;
  __setNext: (r: unknown) => void;
  __localMutationProbe: () => {
    conflicted: number; ledgerMemory: number; ledgerSessional: number; approvedStatus: string | null;
  };
} & Record<string, unknown>;

/* ── The ACTUAL partial markup (Blade comments stripped; the compact
      irreversible @include replaced by its verbatim rendered output) ── */
const MODAL_HTML = `
<div class="modal-overlay" id="cancelClassOverlay" onclick="if(event.target===this)CancelClassModal.dismiss()">
    <div class="modal modal--cancel">
        <div class="modal-header">
            <span class="modal-title" id="cancelClassTitle">Cancel this Class?</span>
            <button class="modal-close" onclick="CancelClassModal.dismiss()" data-tip="Close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="cancelClassConfirmState">
                <div id="cancelClassImpact"></div>
                <div id="cancelDayScope" class="cancel-day-scope" style="display:none"></div>
                <hr class="modal-divider">
                <div id="cancelReasonList"></div>
                <div id="cancelOtherWrap" style="display:none; margin-top:10px">
                    <label class="cancel-other-label" for="cancelOtherDetail">Other reason <span class="cancel-required-mark">*</span></label>
                    <textarea id="cancelOtherDetail" class="modal-textarea" rows="3"
                              placeholder="Describe the reason for &quot;Other&quot;&hellip;"
                              required></textarea>
                </div>
                <p id="cancelReasonHint" class="hint-text" style="margin:8px 0 0"></p>
            </div>
            <div id="cancelClassSuccessState" style="display:none">
                <p id="cancelClassDoneTitle" style="margin:0 0 10px; font-weight:600; font-size:15px;">✓ Class cancelled</p>
                <p id="cancelClassSummary" class="hint-text" style="margin:0"></p>
                <div id="cancelDaySuccess" style="display:none"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-outline" id="cancelKeepClassBtn" onclick="CancelClassModal.dismiss()">No, Keep Class</button>
            <div class="cancel-confirm-wrap" id="cancelConfirmWrap">
                <p class="confirm-warning confirm-warning--compact" id="cancelClassUndoWarn" style="display:none">CANNOT UNDONE</p>
                <button class="btn-danger" id="confirmCancelClassBtn" onclick="CancelClassModal.confirm()" disabled>Yes, Cancel Class</button>
            </div>
            <button class="btn-action" id="cancelArrangeNowBtn" style="display:none" onclick="CancelClassModal.arrangeNow()">Arrange Replacement Now</button>
            <button class="btn-outline" id="cancelLaterBtn" style="display:none" onclick="CancelClassModal.later()">I'll Do It Later</button>
            <button class="btn-outline" id="cancelDayCloseBtn" style="display:none" onclick="CancelClassModal.dismiss()">Close</button>
        </div>
    </div>
</div>`;

/* Synthetic server receipt rows (design D3 shape) */
const APPROVED_RECEIPT = {
  session_id: 77, week: 3, request_id: 5501,            // week 3 = SOURCE week
  start: '13:00', end: '14:00', code: 'DFT2', type: 'L', venue: 'B001',
  cohorts: ['RSD3(S1)G1'], landing_week: 6, landing_day: 2,
};
const TUTORIAL_RECEIPT = {
  session_id: 88, week: 6, start: '15:00', end: '16:00', code: 'TUT1', type: 'T',
  venue: 'B002', cohorts: ['RSD3(S2)'],
};

/** Day-scope read result: one OTHER normal class + one OTHER pending request.
 *  start/end are 'HH:MM' strings — the shape the server's myDayClasses actually
 *  returns (grid indices are the timetable EVENT vocabulary, not the server read). */
const DAY_PAYLOAD = {
  ok: true, day: 2, date: '2027-03-31',                // week 6, Wednesday 31 Mar 2027
  classes: [
    { sessionId: 88, start: '15:00', end: '16:00', code: 'TUT1', type: 'T', venue: 'B002', cohorts: ['RSD3(S2)'], request: null },
  ],
  makeUps: [
    { requestId: 5502, request: 'pending', week: 6, start: '13:00', end: '14:00', code: 'NIM2', type: 'L', venue: 'B003', cohorts: ['RSD3(S2)'] },
  ],
};

const pickReason = (page: Page, value = 'medical_leave') =>
  page.check(`#cancelReasonList input[name="cancelReason"][value="${value}"]`);

/** Livewire calls EXCLUDING the read-only day-scope read. */
const readCalls = (page: Page) =>
  page.evaluate(() => (window as unknown as Harness).__lwCalls
    .filter((c) => c.method !== 'myDayClasses'));

test.describe('CancelClassModal — approved replacement cancellation (D4, read-only component)', () => {
  let navUrls: string[];

  test.beforeEach(async ({ page }) => {
    navUrls = [];
    /* Extra safeguard: EVERY non-GET/HEAD transport (incl. the hashed
       /livewire-f983032f/update and notifications/read) is aborted —
       nothing can reach the server even if a stub leaks. */
    await page.route('**/*', (route) =>
      ['GET', 'HEAD'].includes(route.request().method()) ? route.continue() : route.abort());
    /* Arrange Now backstop: navigation intercepted and fulfilled locally.
       Production lands on /replacement-arrangement?session=..&week=.. */
    await page.route('**/replacement-arrangement*', (route) => {
      navUrls.push(route.request().url());
      return route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>intercepted</title>' });
    });
    await page.goto('/login/staff', { waitUntil: 'networkidle' });
    await expect(page.locator('#password')).toBeVisible();   // the login page (serves ui-common.js)
    await page.evaluate(() => {
      const w = window as unknown as Harness;

      /* ── LOCAL MockData fixtures (page-level only; the login page ships
         none) — these feed the REAL preflight, nothing else ── */
      if (typeof MockData === 'undefined' || !MockData) w.MockData = {};
      MockData.currentUser = { name: 'Dr A. Fixture' };
      MockData.semester = { startDate: '2027-02-22', weeks: 14 };
      MockData.holidays = [];
      MockData.conflictedClasses = [];                     // asserted NOT to grow on approved paths
      MockData.myTimetable = { eventsByWeek: {} };
      MockData.cohortTimetable = { events: [] };

      /* ── gap-fill stubs (LABELLED): installed ONLY when the login page
         does not ship the name; real ui-common definitions always win.
         Component-level test doubles, never app state. ── */
      const setIfMissing = (name: string, value: unknown) => {
        if (typeof (window as unknown as Record<string, unknown>)[name] === 'undefined') {
          (window as unknown as Record<string, unknown>)[name] = value;
        }
      };
      setIfMissing('getTodayMs', () => { const d = new Date(); d.setHours(0, 0, 0, 0); return d.getTime(); });
      setIfMissing('hours', Array.from({ length: 49 }, (_: unknown, i: number) => {
        const m = 480 + i * 30;
        return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
      }));
      setIfMissing('to12h', (t: string) => {
        const p = String(t).split(':');
        let h = parseInt(p[0], 10);
        const ap = h < 12 ? 'AM' : 'PM';
        h = h % 12 || 12;
        return h + ':' + (p[1] || '00') + ' ' + ap;
      });
      setIfMissing('escHtml', (v: unknown) => String(v == null ? '' : v)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'));
      setIfMissing('uiHoursWords', (d: number) => (d === 1 ? '1 hour' : d + ' hours'));
      setIfMissing('totalStudentsLabel', (ev: { studentCounts?: number[]; studentCount?: number }) =>
        Array.isArray(ev.studentCounts)
          ? String(ev.studentCounts.reduce((a: number, c: number) => a + (c || 0), 0))
          : String(ev.studentCount || 0));
      setIfMissing('DetailModal', {
        section: (title: string, html: string) =>
          '<div class="detail-group"><div class="detail-group-title">' + title + '</div>' + html + '</div>',
      });
      setIfMissing('DateHelper', {
        add30min: (t: string) => {
          const p = String(t).split(':');
          const m = parseInt(p[0], 10) * 60 + parseInt(p[1] || '0', 10) + 30;
          return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
        },
        isoDayName: (iso: string) =>
          ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][
            new Date(iso + 'T00:00:00').getDay()],
        dayAbbr: (n: string) => String(n).slice(0, 3),
        formatDate: (iso: string) => {
          const d = new Date(iso + 'T00:00:00');
          const M = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
          return d.getDate() + ' ' + M[d.getMonth()] + ' ' + d.getFullYear();
        },
      });
      setIfMissing('workingDayHolidayKeys', () => [] as string[]);
      setIfMissing('toast', { show: () => {} });

      /* ── LOCAL pageLivewire STUB: calls recorded; results synthetic ── */
      w.__lwCalls = [];
      w.__lwPending = [];
      w.__lwNext = undefined;
      w.pageLivewire = function () {
        return {
          call: function (method: string) {
            const args = Array.prototype.slice.call(arguments, 1) as unknown[];
            w.__lwCalls.push({ method, args });
            if (method === 'myDayClasses') {
              return new Promise((res) => { w.__lwPending.push({ res }); });
            }
            const r = w.__lwNext;
            w.__lwNext = undefined;
            return Promise.resolve(r);
          },
        };
      };

      /* ── Arrange Now recorder: production goes
         goToReplacementFor(sessionId, week, page) →
         /replacement-arrangement?session=..&week=.. — recorded, never
         navigated (the route backstop in Node proves the same fact if the
         wrap is ineffective, e.g. a lexical binding). ── */
      w.__navLog = [];
      w.goToReplacementFor = function (s: unknown, wk: unknown, pg: string) {
        w.__navLog.push({ sessionId: s, week: wk, page: pg });
      };

      /* ── fixture event + open helpers ── */
      const OWNER = MockData.currentUser.name;
      const WEEK_INDEX = 5;                                // shown LANDING week = week 6 (1-based)
      /* week 6 of semester 2027-02-22 = Mon 29 Mar – Sun 4 Apr 2027;
         the opened class sits on Wed 31 Mar 2027 */
      const DAY_DATES = ['29 Mar 2027', '30 Mar 2027', '31 Mar 2027', '1 Apr 2027', '2 Apr 2027', '3 Apr 2027', '4 Apr 2027'];
      const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((a: string, i: number) => ({
        abbr: a, date: DAY_DATES[i], holiday: false, sunday: a === 'Sun',
      }));
      const APPROVED = {
        id: 901, sessionId: 77, status: 'replacement', requestId: 5501,
        di: 2, start: 10, end: 11, code: 'DFT2', type: 'L', name: 'Fixture Subject',   // grid 10..11 = 13:00–14:00, the receipt's own times
        lecturer: OWNER, venue: 'B001', venueName: 'Fixture Hall',
        cohorts: ['RSD3(S1)G1'], studentCounts: [12, 8], studentCount: 20,
      };

      w.__resetState = function () {
        w.__lwCalls.length = 0;
        w.__lwPending.length = 0;
        w.__lwNext = undefined;
        w.__navLog.length = 0;
        w.__refreshed = false;
        MockData.conflictedClasses.length = 0;
        MockData.myTimetable.eventsByWeek = {};
        try { sessionStorage.removeItem('classCancellationLedger'); } catch (e) { /* degrade */ }
        ClassCancellation._memLedger.length = 0;
      };

      w.__openApproved = function (overrides?: Record<string, unknown>, opts?: { recordRefresh?: boolean }) {
        const ev = JSON.parse(JSON.stringify(APPROVED)) as Record<string, unknown>;
        if (overrides) {
          for (const k in overrides) {
            if (overrides[k] === undefined) delete ev[k]; else ev[k] = overrides[k];
          }
        }
        MockData.myTimetable.eventsByWeek[WEEK_INDEX] = [ev];   // preflight §3 twin store
        const ctx: Record<string, unknown> = {};
        if (opts && opts.recordRefresh) {
          w.__refreshed = false;
          ctx.onCancelled = function () { w.__refreshed = true; };
        }
        CancelClassModal.open(ev, DAYS, WEEK_INDEX, ctx);
        return ev;
      };

      w.__resolveDay = function (i: number, p: unknown) {
        const pending = w.__lwPending[i];
        if (pending) pending.res(p);
      };
      w.__setNext = function (r: unknown) { w.__lwNext = r; };
      w.__localMutationProbe = function () {
        let sessional: unknown[] = [];
        try { sessional = JSON.parse(sessionStorage.getItem('classCancellationLedger') || '[]'); } catch (e) { /* degrade */ }
        const ev0 = (MockData.myTimetable.eventsByWeek[5] || [])[0];
        return {
          conflicted: MockData.conflictedClasses.length,
          ledgerMemory: ClassCancellation._memLedger.length,
          ledgerSessional: sessional.length,
          approvedStatus: ev0 ? String(ev0.status) : null,
        };
      };
      w.__resetState();
    });
    await page.evaluate((html: string) => {
      document.body.insertAdjacentHTML('beforeend', html);
    }, MODAL_HTML);
  });

  test('single approved: exact cancelClass identity/landing args, receipt-only success, NO ledger/MockData writes', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(); });
    await pickReason(page);
    await page.evaluate((r) => { (window as unknown as Harness).__setNext(r); },
      { ok: true, cancelled: [APPROVED_RECEIPT] });
    await page.click('#confirmCancelClassBtn');

    await expect(page.locator('#cancelClassSuccessState')).toBeVisible();
    const calls = await readCalls(page);
    expect(calls).toHaveLength(1);
    expect(calls[0]).toEqual({ method: 'cancelClass', args: ['77', 6, 'medical_leave', '', '5501', 2] });
    const summary = await page.locator('#cancelClassSummary').textContent();
    expect(summary).toContain('DFT2 (L)');
    expect(summary).toContain('Wed');
    expect(summary).toContain('31 Mar 2027');
    /* the receipt carries its own landing facts — nothing fabricated client-side */
    expect(page.locator('#cancelClassConfirmState')).toBeHidden();
    const probe = await page.evaluate(() => (window as unknown as Harness).__localMutationProbe());
    expect(probe).toEqual({ conflicted: 0, ledgerMemory: 0, ledgerSessional: 0, approvedStatus: 'replacement' });
  });

  test('day batch: opened approved + ticked normal + ticked pending → three separate lists', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(); });
    await page.evaluate((p) => { (window as unknown as Harness).__resolveDay(0, p); }, DAY_PAYLOAD);
    await expect(page.locator('#cancelDayScope')).toBeVisible();
    await page.click('#cancelDayScope .cd-collapse');   // the block opens FOLDED — expand before ticking the rows
    await page.check('#cancelDayScope input[data-session="88"]');
    await page.check('#cancelDayScope input[data-request="5502"]');
    expect(await page.locator('#confirmCancelClassBtn').textContent()).toBe('Yes, Cancel 2 Classes + 1 Request');
    await pickReason(page);
    await page.evaluate((r) => { (window as unknown as Harness).__setNext(r); }, {
      ok: true,
      cancelled: [APPROVED_RECEIPT, TUTORIAL_RECEIPT],
      requestsWithdrawn: 1,
      requestsClosed: 0,
    });
    await page.click('#confirmCancelClassBtn');

    await expect(page.locator('#cancelDaySuccess')).toBeVisible();
    const calls = await readCalls(page);
    expect(calls).toHaveLength(1);
    /* approved target ONLY in the approved list; its source session 77 is
       NEVER sent among the normal selections; pending stays request-keyed */
    expect(calls[0]).toEqual({
      method: 'cancelClassDay',
      args: [6, 2, 'medical_leave', '', ['5502'], ['88'], ['5501']],
    });
    const rows = await page.locator('#cancelDaySuccess .cancel-day-done li').allTextContents();
    expect(rows).toHaveLength(2);
    /* the opened target orders first — it IS in the receipt */
    expect(rows[0]).toContain('DFT2');
    expect(rows[1]).toContain('TUT1');
    const box = await page.locator('#cancelDaySuccess').textContent();
    expect(box).toContain('2 classes cancelled');
    expect(box).toContain('withdrawn');   // the pending tick → 1 request withdrawn total
  });

  test('defensive partial receipt (tutorial-only): success count 1, ONLY the acknowledged row, no fabricated opened approved', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(); });
    await page.evaluate((p) => { (window as unknown as Harness).__resolveDay(0, p); }, DAY_PAYLOAD);
    await expect(page.locator('#cancelDayScope')).toBeVisible();
    await page.click('#cancelDayScope .cd-collapse');   // expand the folded block before ticking
    await page.check('#cancelDayScope input[data-session="88"]');
    await page.check('#cancelDayScope input[data-request="5502"]');
    await pickReason(page);
    await page.evaluate((r) => { (window as unknown as Harness).__setNext(r); },
      { ok: true, cancelled: [TUTORIAL_RECEIPT] });
    await page.click('#confirmCancelClassBtn');

    await expect(page.locator('#cancelDaySuccess')).toBeVisible();
    const box = await page.locator('#cancelDaySuccess').textContent();
    expect(box).toContain('1 class cancelled');
    const rows = await page.locator('#cancelDaySuccess .cancel-day-done li').allTextContents();
    expect(rows).toHaveLength(1);
    expect(rows[0]).toContain('TUT1');
    expect(box).not.toContain('DFT2');   // the opened approved was NOT acknowledged → never rendered
  });

  test('successful single approved → Arrange Now intercepted at the SOURCE week (3), never the landing week (6)', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(); });
    await pickReason(page);
    await page.evaluate((r) => { (window as unknown as Harness).__setNext(r); },
      { ok: true, cancelled: [APPROVED_RECEIPT] });
    await page.click('#confirmCancelClassBtn');
    await expect(page.locator('#cancelArrangeNowBtn')).toBeVisible();
    await page.click('#cancelArrangeNowBtn');

    await expect.poll(async () => {
      const nav = await page.evaluate(() => (window as unknown as Harness).__navLog);
      return nav.length + navUrls.length;
    }).toBeGreaterThanOrEqual(1);
    const nav = await page.evaluate(() => (window as unknown as Harness).__navLog);
    if (nav.length > 0) {
      expect(nav).toEqual([{ sessionId: 77, week: 3, page: 'my-timetable' }]);
    } else {
      expect(navUrls).toHaveLength(1);
      expect(navUrls[0]).toContain('/replacement-arrangement');
      expect(navUrls[0]).toContain('session=77');
      expect(navUrls[0]).toContain('week=3');   // SOURCE week — landing week 6 must never appear
      expect(navUrls[0]).not.toContain('week=6');
    }
  });

  test('failed server result: inline refusal, confirm state kept, NO local mutation', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(); });
    await pickReason(page);
    await page.evaluate((r) => { (window as unknown as Harness).__setNext(r); },
      { ok: false, hint: 'The landing week is outside the cancellation window.' });
    await page.click('#confirmCancelClassBtn');

    await expect(page.locator('#cancelReasonHint')).toHaveText('The landing week is outside the cancellation window.');
    await expect(page.locator('#cancelClassSuccessState')).toBeHidden();
    const probe = await page.evaluate(() => (window as unknown as Harness).__localMutationProbe());
    expect(probe).toEqual({ conflicted: 0, ledgerMemory: 0, ledgerSessional: 0, approvedStatus: 'replacement' });
  });

  test('approved block without requestId is REFUSED — no silent fallback to a normal cancellation', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved({ requestId: undefined }); });
    await pickReason(page);
    await page.click('#confirmCancelClassBtn');

    await expect(page.locator('#cancelReasonHint'))
      .toHaveText('This replacement can no longer be cancelled from here — refresh the page and try again.');
    expect(await readCalls(page)).toEqual([]);   // no cancelClass/cancelClassDay ever sent
    await expect(page.locator('#cancelClassSuccessState')).toBeHidden();
  });

  test('single success without the exact request_id receipt is NOT invented', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(); });
    await pickReason(page);
    await page.evaluate((r) => { (window as unknown as Harness).__setNext(r); }, {
      ok: true,
      cancelled: [{ session_id: 77, week: 6, request_id: 9999, start: '13:00', end: '14:00', code: 'DFT2', type: 'L', venue: 'B001', cohorts: [] }],
    });
    await page.click('#confirmCancelClassBtn');

    await expect(page.locator('#cancelReasonHint'))
      .toHaveText('The server could not confirm this replacement cancellation — reload to check its status.');
    await expect(page.locator('#cancelClassSuccessState')).toBeHidden();
    const probe = await page.evaluate(() => (window as unknown as Harness).__localMutationProbe());
    expect(probe).toEqual({ conflicted: 0, ledgerMemory: 0, ledgerSessional: 0, approvedStatus: 'replacement' });
  });

  test('success dismissal (Esc/×/overlay = dismiss) refreshes the grid via onCancelled, like Close', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(undefined, { recordRefresh: true }); });
    await pickReason(page);
    await page.evaluate((r) => { (window as unknown as Harness).__setNext(r); },
      { ok: true, cancelled: [APPROVED_RECEIPT] });
    await page.click('#confirmCancelClassBtn');
    await expect(page.locator('#cancelClassSuccessState')).toBeVisible();
    await page.click('.modal-close');

    const st = await page.evaluate(() => {
      const w = window as unknown as Harness;
      const overlay = document.getElementById('cancelClassOverlay') as HTMLElement | null;
      return { refreshed: w.__refreshed, shown: !!(overlay && overlay.classList.contains('show')) };
    });
    expect(st.refreshed).toBe(true);
    expect(st.shown).toBe(false);
  });

  test('_loadDayScope (approved open): same-session other-week normal row RETAINED, own make-up note removed', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(); });
    /* the opened replacement is session 77 — a recurring row of the SAME
       session on this landing weekday is another week's legitimate class;
       the opened request's OWN make-up note (5501/DFT2) is removed, every
       other note (5502/NIM2) stays */
    await page.evaluate((p) => { (window as unknown as Harness).__resolveDay(0, p); }, {
      ok: true, day: 2, date: '2027-03-31',
      classes: [
        { sessionId: 77, start: '14:00', end: '15:00', code: 'OTH9', type: 'L', venue: 'B004', cohorts: [], request: null },
      ],
      makeUps: [
        { requestId: 5501, request: 'approved', week: 3, start: '13:00', end: '14:00', code: 'DFT2', type: 'L', venue: 'B001', cohorts: [] },
        { requestId: 5502, request: 'approved', week: 4, start: '10:00', end: '11:00', code: 'NIM2', type: 'L', venue: 'B003', cohorts: [] },
      ],
    });
    await expect(page.locator('#cancelDayScope')).toBeVisible();

    const scope = await page.evaluate(() => (document.getElementById('cancelDayScope') as HTMLElement).innerHTML);
    expect(scope).toContain('data-session="77"');   // same-session other-week row kept (NO session-id exclusion)
    expect(scope).toContain('OTH9');
    expect(scope).toContain('NIM2');                // the other request's note stays
    expect(scope).not.toContain('DFT2');            // the OWN make-up note is gone
  });

  test('stale async day-scope answer from an EARLIER open is ignored', async ({ page }) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(); });   // open #1 → pending[0]
    await page.evaluate(() => {
      const w = window as unknown as Harness;
      CancelClassModal.close();                       // dismiss open #1 unanswered
      w.__openApproved();                             // open #2 → pending[1]
    });
    await page.evaluate((p) => { (window as unknown as Harness).__resolveDay(1, p); }, {
      ok: true, day: 2, date: '2027-03-31',
      classes: [{ sessionId: 88, start: '15:00', end: '16:00', code: 'FRESH', type: 'T', venue: 'B002', cohorts: [], request: null }],
      makeUps: [],
    });
    await expect(page.locator('#cancelDayScope')).toBeVisible();
    /* NOW the stale answer for open #1 lands — the seq token must reject it */
    await page.evaluate((p) => { (window as unknown as Harness).__resolveDay(0, p); }, {
      ok: true, day: 2, date: '2027-03-31',
      classes: [{ sessionId: 99, start: '12:00', end: '13:00', code: 'STALE', type: 'L', venue: 'B009', cohorts: [], request: null }],
      makeUps: [],
    });

    const scope = await page.evaluate(() => (document.getElementById('cancelDayScope') as HTMLElement).innerHTML);
    expect(scope).toContain('FRESH');
    expect(scope).not.toContain('STALE');
  });

  test('responsive light/dark screenshots at 1440 and 390 — SYNTHETIC component UI only (fixture modal, not a live page)', async ({ page }, testInfo) => {
    await page.evaluate(() => { (window as unknown as Harness).__openApproved(); });
    await pickReason(page);
    await expect(page.locator('#confirmCancelClassBtn')).toBeEnabled();
    await expect(page.locator('#cancelClassOverlay')).toHaveClass(/show/);

    for (const [width, height] of [[1440, 900], [390, 844]] as const) {
      await page.setViewportSize({ width, height });
      for (const theme of ['light', 'dark'] as const) {
        await page.evaluate((t: string) => {
          const html = document.documentElement;
          html.classList.remove('light', 'dark');
          html.classList.add(t);
          try { localStorage.setItem('theme', t); } catch (e) { /* degrade */ }
        }, theme);
        await page.waitForTimeout(120);   // theme repaint settle
        await page.screenshot({ path: testInfo.outputPath(`modal-${theme}-${width}.png`), fullPage: true });
      }
    }
  });
});
