import { test } from '@playwright/test';
test.use({ storageState: '.auth/staff-5770.json' });
const BASE = 'http://127.0.0.1:8030';

test('the nine rulings, measured now', async ({ browser }) => {
  const rows: string[] = [];
  const at = async (w: number) => {
    const ctx = await browser.newContext({ viewport: { width: w, height: 950 }, storageState: '.auth/staff-5770.json' });
    const page = await ctx.newPage();
    await page.goto(BASE + '/my-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(900);
    return { ctx, page };
  };

  // #1 #2 #3 — the week dropdown
  for (const w of [390, 768, 1024]) {
    const { ctx, page } = await at(w);
    const r = await page.evaluate(() => {
      const s = document.getElementById('weekSelect') as HTMLSelectElement;
      const cs = getComputedStyle(s);
      const p = document.createElement('span');
      p.style.cssText = 'position:absolute;visibility:hidden;white-space:nowrap;font:' + cs.font;
      document.body.appendChild(p);
      let worst = 0;
      Array.from(s.options).forEach((o) => { p.textContent = o.textContent; worst = Math.max(worst, p.getBoundingClientRect().width); });
      p.remove();
      const box = s.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
      return { fs: cs.fontSize, worst: Math.round(worst), box: Math.round(box),
        label: s.options[s.selectedIndex].text, chip: (document.getElementById('semesterChip')?.textContent || '').trim() };
    });
    rows.push(`#1/#2/#3 @${w}: type ${r.fs}, label "${r.label}", year ${/\b20\d\d\b/.test(r.label) ? 'YES' : 'no'}, worst ${r.worst}/${r.box} ${r.worst <= r.box ? 'FITS' : 'CLIPS'}`);
    if (w === 390) {
      // #5 keep — the 320 toolbar wrap
      await page.setViewportSize({ width: 320, height: 950 });
      await page.waitForTimeout(600);
      const wrap = await page.evaluate(() => {
        const t = document.getElementById('todayBtn')!.getBoundingClientRect();
        const s = document.getElementById('weekSelect')!.getBoundingClientRect();
        return { sameRow: Math.abs(t.top - s.top) < 20 };
      });
      rows.push(`#5 keep @320: Today on the week row? ${wrap.sameRow ? 'yes (changed!)' : 'no — wraps as you chose'}`);
      await page.setViewportSize({ width: 768, height: 950 });
      await page.waitForTimeout(600);
      await page.evaluate(() => (window as any).TimetableResponsive.openSheet());
      await page.waitForTimeout(600);
      const sheet = await page.evaluate(() => {
        const el = document.querySelector('.tt-sheet') as HTMLElement;
        const r = el.getBoundingClientRect();
        const foot = el.querySelector('.tt-sheet-foot button') as HTMLElement | null;
        const body = el.querySelector('.tt-sheet-body') as HTMLElement;
        return { w: Math.round(r.width), h: Math.round(r.height), vh: window.innerHeight,
          foot: !!foot, footH: foot ? Math.round(foot.getBoundingClientRect().height) : 0,
          fits: body.scrollHeight <= body.clientHeight + 1 };
      });
      rows.push(`#6 keep @768: sheet ${sheet.w}px wide (full width = kept)`);
      rows.push(`#7 @768: foot Close ${sheet.foot ? 'present' : 'MISSING'}, ${sheet.footH}px tall`);
      rows.push(`#8 @768: sheet ${sheet.h}px of ${sheet.vh}px (${Math.round(100 * sheet.h / sheet.vh)}%), cards fit without scrolling: ${sheet.fits}`);
      await page.keyboard.press('Escape');
      // #9 keep — the semester chip above the phone band
      const chip = (await page.locator('#semesterChip').textContent() || '').trim();
      rows.push(`#9 keep @768: chip "${chip}" (no "Semester" word = kept)`);
    }
    await ctx.close();
  }

  // #4 — tablet heading
  for (const w of [768, 1024]) {
    const { ctx, page } = await at(w);
    rows.push(`#4 @${w}: heading "${(await page.locator('#ttDayHeading').textContent() || '').trim()}"`);
    await ctx.close();
  }

  // #10 — empty weekday
  {
    const { ctx, page } = await at(390);
    const chips = await page.locator('#ttDayStrip .tt-day').evaluateAll((els) => els.map((e) => ({
      day: (e as HTMLElement).dataset.day, count: Number((e as HTMLElement).dataset.count), holiday: (e as HTMLElement).dataset.holiday })));
    const empty = chips.find((c) => c.count === 0 && c.holiday !== '1');
    if (empty) {
      await page.locator('#ttDayStrip .tt-day').nth(Number(empty.day)).click();
      await page.waitForTimeout(500);
      const p = await page.evaluate(() => {
        const el = document.querySelector('#ttAgenda .tt-empty') as HTMLElement;
        const cs = getComputedStyle(el);
        return { title: (el.querySelector('.empty-title')?.textContent || '').trim(), border: cs.borderTopWidth, bg: cs.backgroundColor };
      });
      rows.push(`#10 @390: "${p.title}" | border ${p.border} | fill ${p.bg}`);
    } else rows.push('#10 @390: fixture — no empty weekday this week');
    await ctx.close();
  }

  // #11 — whole empty week, three bands
  for (const w of [390, 900, 1440]) {
    const { ctx, page } = await at(w);
    await page.evaluate(() => {
      const g = window as any; g.eventsData[g.currentWeek] = []; g.selectWeek(g.currentWeek);
    });
    await page.waitForTimeout(1200);
    const r = await page.evaluate(() => ({
      panels: Array.from(document.querySelectorAll('.tt-responsive .empty-state')).filter((e) => (e as HTMLElement).offsetParent !== null).length,
      title: (document.getElementById('emptyTitle')?.textContent || '').trim(),
      hint: (document.querySelector('#emptyState .tt-next-hint')?.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 28),
    }));
    rows.push(`#11 @${w}: ${r.panels} panel(s), "${r.title}", hint ${r.hint ? 'inside' : 'MISSING'}`);
    await ctx.close();
  }

  // #12 — guide dialog on our page, and untouched on the venue page
  {
    const { ctx, page } = await at(390);
    await page.locator('[aria-label="How to use this page"]').first().click();
    await page.waitForTimeout(700);
    const g = await page.evaluate(() => {
      const m = document.querySelector('.modal--guide') as HTMLElement;
      const t = m.querySelector('.modal-title') as HTMLElement;
      return { align: getComputedStyle(t).textAlign, sub: (m.querySelector('.modal-subtitle')?.textContent || '').trim(),
        groups: m.querySelectorAll('.detail-group').length };
    });
    rows.push(`#12 @390 My Timetable: title ${g.align}, subtitle "${g.sub}", ${g.groups} labelled sections`);
    await page.goto(BASE + '/venue-timetable-ui', { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    await page.locator('[aria-label="How to use this page"]').first().click();
    await page.waitForTimeout(700);
    const v = await page.evaluate(() => {
      const m = document.querySelector('.modal--guide') as HTMLElement;
      return { align: getComputedStyle(m.querySelector('.modal-title') as HTMLElement).textAlign, optIn: m.classList.contains('modal--guide-left') };
    });
    rows.push(`#12 @390 Venue Timetable (untouched): title ${v.align}, opt-in ${v.optIn}`);
    await ctx.close();
  }

  console.log('RULINGS\n' + rows.join('\n'));
});
