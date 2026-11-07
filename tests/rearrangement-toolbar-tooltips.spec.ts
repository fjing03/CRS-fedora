import { test, expect, type Page } from '@playwright/test';

/* SDD rearrangement-toolbar-tooltips — the lean-path apply's receipt.
   Three hover tooltips (data-tip, shared engine, ABOVE):
   a) #slotTriggerText — 'Conflicted SLOT Selector: (L) Week 14, Thursday, 24 Dec 2026, 2:30 PM ~ 3:30 PM @ B107'
      (dynamic, refreshed at EVERY trigger writer; fails open: the placeholder
      shows NO tip; the arrival anchor now carries the REAL session_type from
      pinnedSessionPayload — the 2026-10-31 user no-tip report fix);
   b) #weekSelector — the static selectTip passed to ui-week-nav (arrows stay
      tip-less: prev/next params remain null → other pages byte-identical);
   c) #subjectSelector — 'Conflicted SUBJECT Selector : CODE - NAME'
      (plain hyphen in the TIP even though option text uses an em dash;
      fail-open: cleared whenever nothing is selected).
   Visible texts unchanged; no mechanism change.
   Fixture verified live (the 2026-10-31 probes): staff-5425,
   ?session=67&week=14&from=replacement-home pins AMSE2003 (L) — the anchor
   derives day/start/venue/type from PAGE_DATA.pinnedSession, so the SAME
   clear-guards URL exercises the anchor writer. */

const BASE = (process.env.CRS_BASE ?? 'http://localhost:8000');
const PW = 'Tarumt@2026';

const SLOT_TIP_RE =
  /^Conflicted SLOT Selector: \([A-Z]\) Week \d+, (Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday), \d{1,2} [A-Z][a-z]{2} \d{4}, \d{1,2}:\d{2} (AM|PM) ~ \d{1,2}:\d{2} (AM|PM) @ \S+$/;

/* Batch 2 (2026-10-30): the venue trigger's selector-voice tip; the cohort
   tag; the star removal. */
const VENUE_TIP_RE = /^Replacement VENUE Selector: \S+ — .+ \(Capacity: \d+ seats\)$/;

async function loginStaff(page: Page, id: string): Promise<void> {
  await page.goto(`${BASE}/login/staff`, { waitUntil: 'networkidle' });
  await page.fill('input[name="login_id"]', id);
  await page.fill('input[name="password"]', PW);
  await page.click('button[type="submit"]');
  await page.waitForURL(/(my-timetable|dashboard)/, { timeout: 15_000 });
}

/** the runtime-truth of the arrival anchor, read from the page itself */
async function pinnedFacts(page: Page) {
  return await page.evaluate(() => {
    const p = (window.PAGE_DATA || {}).pinnedSession || null;
    if (!p) return null;
    const wk = (p.week || 1) - 1;
    const dayData = (window.weekData && window.weekData[wk] && window.weekData[wk].days[Number(p.day)]) || null;
    return {
      type: String(p.type),
      week: p.week,
      venue: String(p.venue),
      date: dayData && dayData.date ? String(dayData.date) : '',
    };
  });
}

test('arrival: a) the anchored slot tip carries the REAL facts; b) the week selectTip; tips refresh on a real pick', async ({ page }) => {
  await loginStaff(page, '5425');
  await page.goto(`${BASE}/replacement-arrangement?session=67&week=14&from=replacement-home`);
  await page.waitForTimeout(3400); // pinned arrival (house value)

  // -- b) STATIC week tip, byte-exact; the arrows stay tip-less
  await expect(page.locator('#weekSelector')).toHaveAttribute('data-tip', 'Select the WEEK that you want to arrange the replacement in');
  await expect(page.locator('.week-nav button.week-arrow[data-tip]')).toHaveCount(0);

  // -- a) the ANCHOR tip: full format + runtime-truth spot values
  const trigger = page.locator('#slotTriggerText');
  const tip = await trigger.getAttribute('data-tip');
  expect(tip || '', 'the arrival anchor MUST tip (the 2026-10-31 no-tip report fix)').toMatch(SLOT_TIP_RE);

  const facts = await pinnedFacts(page);
  expect(facts, 'the fixture: session=67 pins a session').not.toBeNull();
  expect(tip).toContain(`(${facts!.type}) Week ${facts!.week}`);   // the REAL type letter + week
  expect(tip).toContain(`@ ${facts!.venue}`);                       // the REAL venue
  if (facts!.date) expect(tip).toContain(facts!.date);              // the REAL date (weekData)

  // SDD rearrangement-toolbar-tooltips Batch 2 (3c): the trigger text = 'Week {N} · {Mon} {D}, {YEAR} · {range}'
  const visible = (await trigger.textContent()) || '';
  expect(visible).toMatch(/^Week \d+ · /);
  expect(visible).not.toContain('Conflicted SLOT');
  // cross-check: the tip's week + the date agreement with the VISIBLE label (the year rides)
  const dParts = facts!.date.split(' ');
  expect(visible).toContain(`Week ${facts!.week} · ${dParts[1]} ${dParts[0]}, ${dParts[2]}`);

  // -- Batch 2 req 1: the venue selector's trigger tip (the identity shape —
  //    the data-tip attribute is read straight off the DOM: hovering would
  //    leave the shared tooltip overlaying the slot picker's clicks)
  expect(await page.locator('.venue-dd-trigger').getAttribute('data-tip')).toMatch(VENUE_TIP_RE);

  // -- a-tap 2) the pick writer: OPEN the panel — Batch 2 req 3a rides the
  //    same open (NO favourite star anywhere in the slot panel) — then pick
  //    the first slot (no prior selection → commits directly; the store
  //    rebuilds honestly at "0 of N")
  await page.locator('#slotTrigger').click();
  await page.waitForTimeout(700);
  await expect(page.locator('#slotPanel .slot-dd-item-star')).toHaveCount(0);
  await page.locator('#slotPanel .slot-dd-item').first().locator('.slot-dd-item-label').click();
  await page.waitForTimeout(900);
  const tip2 = await trigger.getAttribute('data-tip');
  expect(tip2 || '', 'a real pick MUST refresh its own full tip').toMatch(SLOT_TIP_RE);
  const visible2 = (await trigger.textContent()) || '';
  const pickedWeek = visible2.match(/^Week (\d+) ·/)?.[1];
  if (pickedWeek) expect(tip2).toContain(`Week ${pickedWeek}`);
  // -- Batch 2 req 3b: the FIRST item's label = the Week spelling + the year + the cohort tag
  const itemLabel = await page.locator('#slotPanel .slot-dd-item').first().locator('.slot-dd-item-label').textContent();
  expect(itemLabel || '').toMatch(/Week \d+ · [A-Z][a-z]{2} \d{1,2}, \d{4}/);
  expect(itemLabel || '').toContain('@ B');
  await expect(page.locator('#slotPanel .slot-dd-item').first().locator('.slot-dd-item-cohort')).toHaveCount(1);

  // -- a-tap 3) the subject-commit path: with a zeroed store the guard does not
  //    fire (verify: no modal) → the change COMMITS and the NEW subject's
  //    first-conflict auto-pin writes its own full tip (probe-verified truth)
  await page.locator('#subjectSelector').selectOption({ index: 1 });
  await page.waitForTimeout(1100);
  await expect(page.locator('#confirmModal')).toBeHidden(); // 0-store change commits
  const tip3 = await trigger.getAttribute('data-tip');
  expect(tip3 || '', 'the new subject auto-pin carries its own full tip').toMatch(SLOT_TIP_RE);
  const visible3 = (await trigger.textContent()) || '';
  const pinned3 = visible3.match(/^Week (\d+) ·/)?.[1];
  if (pinned3) expect(tip3).toContain(`Week ${pinned3}`);
  // c) the subject select's own tip followed the commit (same runtime truth)
  const subj = await page.evaluate(() => {
    const sel = document.getElementById('subjectSelector') as HTMLSelectElement;
    const opt = sel.selectedOptions[0];
    return { value: sel.value, text: opt ? opt.text : '', tip: sel.getAttribute('data-tip') };
  });
  const emD = subj.text.indexOf(' — ');
  expect(subj.tip).toBe('Conflicted SUBJECT Selector : ' + subj.value + ' - ' + (emD >= 0 ? subj.text.slice(emD + 3) : subj.text));
});

test('empty states + mechanism: no pin → no tip anywhere; the subject tip follows the selection; hover shows the shared tooltip ABOVE', async ({ page }) => {
  await loginStaff(page, '5425');
  await page.goto(`${BASE}/replacement-arrangement`); // no session/week pin
  await page.waitForTimeout(2800);

  // a) fail-open: the placeholder carries NO tip
  const trigger = page.locator('#slotTriggerText');
  await expect(trigger).toHaveText('Select a slot to replace');
  expect(await trigger.getAttribute('data-tip')).toBe(null);

  // c) fail-open on the subject too (nothing selected on a bare load)
  const subject = page.locator('#subjectSelector');
  expect(await subject.getAttribute('data-tip')).toBe(null);

  // c) select a subject → tip = prefix + CODE - NAME (plain hyphen split from the em-dash option text)
  await subject.selectOption({ index: 1 });
  await page.waitForTimeout(700);
  const self = await page.evaluate(() => {
    const sel = document.getElementById('subjectSelector') as HTMLSelectElement;
    const opt = sel.selectedOptions[0];
    return { value: sel.value, text: opt ? opt.text : '', tip: sel.getAttribute('data-tip') };
  });
  const emDash = self.text.indexOf(' — ');
  const expected = 'Conflicted SUBJECT Selector : ' + self.value + ' - ' + (emDash >= 0 ? self.text.slice(emDash + 3) : self.text);
  expect(self.tip).toBe(expected);

  // c fail-open 2) cleared select → attribute gone
  await subject.selectOption('');
  await page.waitForTimeout(500);
  expect(await subject.getAttribute('data-tip')).toBe(null);

  // mechanism reuse: hovering the tipped week select shows the SHARED tooltip above it
  await page.hover('#weekSelector');
  await page.waitForTimeout(500);
  const above = await page.evaluate(() => {
    const tipEl = document.querySelector('.data-tip-tooltip');
    const selEl = document.getElementById('weekSelector');
    if (!tipEl || !selEl) return { present: false } as const;
    const t = tipEl.getBoundingClientRect();
    const s = selEl.getBoundingClientRect();
    return { present: true, tipBottom: t.bottom, selTop: s.top, word: (tipEl.textContent || '').slice(0, 12) } as const;
  });
  expect(above.present).toBe(true);
  expect((above.word || '').startsWith('Select the ')).toBe(true); // the SHARED engine renders the SAME tip text
  expect(above.tipBottom).toBeLessThanOrEqual(above.selTop + 2); // ABOVE, with clamp tolerance
});

test('revert writer: with a REAL selection the subject guard pops; No restores the selection-sourced tip', async ({ page }) => {
  await loginStaff(page, '5425');
  await page.goto(`${BASE}/replacement-arrangement?session=67&week=14&from=replacement-home`);
  await page.waitForTimeout(3400); // pinned arrival (house value)

  // build a REAL selection first (the clear-guards pickW6 flow: a full Week-6 cell pick)
  const td = page.locator('#tableBody td[data-day="2"][data-hour="16"] .cell-content');
  await td.click();
  await page.waitForTimeout(900);
  await expect(page.locator('#infoTotal')).toContainText('of 4 slots'); // the store receipt

  const trigger = page.locator('#slotTriggerText');
  const preTip = await trigger.getAttribute('data-tip');
  expect(preTip || '', 'the cell pick wrote its own tip').toMatch(SLOT_TIP_RE);
  const preVisible = (await trigger.textContent()) || '';
  const preWeek = preVisible.match(/^Week (\d+) ·/)?.[1];
  if (preWeek) expect(preTip).toContain(`Week ${preWeek}`);

  // subject change WITH a selection → the guard pops; No keeps the selection
  await page.locator('#subjectSelector').selectOption({ index: 1 });
  await page.waitForTimeout(900);
  await expect(page.locator('#confirmModal')).toBeVisible();
  await expect(page.locator('#modalTitle')).toHaveText('Change Subject?');
  await page.locator('#confirmModal .btn-outline').click(); // No, Keep My Selection
  await page.waitForTimeout(900);
  await expect(page.locator('#infoTotal')).toContainText('of 4 slots'); // the store restored

  // the tip must STILL agree with the visible label (whatever the restore chose)
  const postTip = await trigger.getAttribute('data-tip');
  expect(postTip || '', 'the revert path leaves a FULL tip').toMatch(SLOT_TIP_RE);
  const postVisible = (await trigger.textContent()) || '';
  const postWeek = postVisible.match(/^Week (\d+) ·/)?.[1];
  if (postWeek) expect(postTip).toContain(`Week ${postWeek}`);
  const postSubject = await page.evaluate(() => (document.getElementById('subjectSelector') as HTMLSelectElement)?.value);
  expect(postSubject).toBe('AMSE2003'); // the revert receipt (clear-guards contract)
});
