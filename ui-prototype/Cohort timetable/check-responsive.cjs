/* Prototype-only browser checks. No Laravel, login, SQL, or API access.
   Start a loopback static server at repo root, then:
     node 'ui-prototype/Cohort timetable/check-responsive.cjs'
   Optional CRS_PROTOTYPE_BASE overrides the default http://127.0.0.1:8091.
*/
const { chromium } = require('../../node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const base = process.env.CRS_PROTOTYPE_BASE || 'http://127.0.0.1:8091';
const parsed = new URL(base);
assert(['127.0.0.1', 'localhost'].includes(parsed.hostname), 'Use a loopback STATIC server only');
assert(!['8000','8001','8030'].includes(parsed.port), 'Never point prototype checks at a CRS server');
const url = base + '/ui-prototype/Cohort%20timetable/cohort-timetable-responsive-preview.html';
const widths = [320,360,390,430,600,743,744,767,768,820,834,1024,1180,1279,1280,1366,1440,1920];
const receipt = { matrix: [], interactions: [] };
(async () => {
 const browser = await chromium.launch({headless:true});
 try {
 for (const touch of [false,true]) {
  const context = await browser.newContext({hasTouch:touch});
  const page = await context.newPage(); const errors=[]; const forbidden=[];
  page.on('pageerror', e=>errors.push(e.message));
  await page.route('**/*', route=> {
   const r=route.request(); const target=new URL(r.url());
   if(target.origin!==parsed.origin || r.method()!=='GET') {forbidden.push(r.method()+' '+r.url());return route.abort();}
   return route.continue();
  });
  for (const theme of ['light','dark']) for (const width of widths) {
   await page.setViewportSize({width,height:width<744?844:1024});
   await page.goto(url+'?theme='+theme); await page.waitForTimeout(100);
   const expected=width<744?'agenda':width<1280||touch?'window':'full';
   const metrics=await page.evaluate(()=>{
    const visible=e=>e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden'&&!e.closest('#navDrawer:not(.open)');
    const nodes=[...document.querySelectorAll('button,select')].filter(e=>visible(e)&&!e.disabled);
    const week=document.querySelector('#previewWeek'),style=getComputedStyle(week),canvas=document.createElement('canvas').getContext('2d');canvas.font=style.font;
    const labelRoom=week.clientWidth-parseFloat(style.paddingLeft)-parseFloat(style.paddingRight);
    const maxLabel=Math.max(...[...week.options].map(o=>canvas.measureText(o.textContent).width));
    return {labelRoom,maxLabel,mode:document.body.dataset.mode,overflow:document.documentElement.scrollWidth-innerWidth,
     small:nodes.filter(e=>{const r=e.getBoundingClientRect();return r.width<47.9||r.height<47.9;}).map(e=>e.id||e.className),
     summary:[...document.querySelectorAll('#previewExtras .summary-label')].map(e=>e.textContent),
     days:document.querySelectorAll('#previewDays button').length,
     columns:document.querySelector('#previewTablet .preview-tablet-grid')?.style.getPropertyValue('--day-count'),
     currentDate:document.querySelector('[aria-current="date"]')?.dataset.day};
   });
   assert.equal(metrics.mode,expected,`mode ${width}/${touch}/${theme}`);
   assert.equal(metrics.overflow,0,`horizontal overflow ${width}/${touch}/${theme}`);
   assert.equal(metrics.days,7); assert.equal(metrics.currentDate,'0');
   assert(metrics.maxLabel<=metrics.labelRoom,`week label clipped at ${width}/${touch}/${theme}: ${metrics.maxLabel}>${metrics.labelRoom}`);
   assert.deepEqual(metrics.summary,['Total Classes','Teaching Hours','My Teaching Classes','My Teaching Hours','Conflicts']);
   if(touch) assert.deepEqual(metrics.small,[],`touch targets ${width}/${theme}`);
   if(expected==='window') assert.equal(Number(metrics.columns),width<1024?2:width<1280?3:4);
   receipt.matrix.push({width,touch,theme,mode:metrics.mode,overflow:metrics.overflow});
  }
  // Same day/week across cohort selection; faculty without taught cohorts clears it.
  await page.setViewportSize({width:390,height:844});await page.goto(url);
  const sharedBefore=await page.evaluate(()=>JSON.stringify(window.MockData));
  await page.selectOption('#previewWeek','10');await page.locator('[data-day="3"]').click();
  await page.selectOption('#cohortSelect','dsf2s1');
  assert.equal(await page.inputValue('#previewWeek'),'10');assert.equal(await page.locator('[aria-pressed="true"][data-day]').getAttribute('data-day'),'3');
  await page.click('#previewToday');assert.equal(await page.inputValue('#previewWeek'),'8');assert.equal(await page.locator('[aria-pressed="true"][data-day]').getAttribute('data-day'),'0');
  await page.selectOption('#facultySelect','fafb');assert.equal(await page.inputValue('#cohortSelect'),'');assert(await page.locator('#previewEmpty').isVisible());assert(await page.locator('#previewWeek').isDisabled());
  await page.selectOption('#cohortSelect','raf2s3g2');assert(await page.locator('#previewWeek').isEnabled());
  await page.selectOption('#facultySelect','focs');assert.equal(await page.inputValue('#cohortSelect'),'dsf1s1');
  await page.locator('[data-day="3"]').click();
  assert.equal(await page.evaluate(()=>document.activeElement.dataset.day),'3');
  await page.setViewportSize({width:744,height:1024});await page.waitForTimeout(200);
  assert.equal(await page.locator('[aria-pressed="true"][data-day]').getAttribute('data-day'),'3');
  await page.setViewportSize({width:390,height:844});await page.waitForTimeout(200);await page.click('#previewToday');
  receipt.interactions.push(`selection + Today + resize/day focus (${touch?'touch':'mouse'})`);
  // Details: ownership is independent of colour, and only own class offers actions.
  await page.locator('#previewAgenda [data-event]').first().click();assert(await page.locator('#previewCancel').isVisible());
  await page.click('#previewCancel');assert(await page.locator('#previewConfirmCancel').isDisabled());
  await page.locator('#previewReasonList input').first().check();assert(await page.locator('#previewIrreversible').isVisible());
  await page.click('#previewConfirmCancel');assert((await page.textContent('#modalBody')).includes('No class or request was changed'));
  await page.click('#previewCloseBtn');await page.locator('#previewAgenda [data-event]').nth(1).click();assert.equal(await page.locator('#previewCancel').count(),0);
  await page.keyboard.press('Escape');assert.equal(await page.locator('#previewOverlay').isVisible(),false);
  receipt.interactions.push(`owner-only details + local cancellation (${touch?'touch':'mouse'})`);
  // Summary is whole-week, all five cards, focus trap and nested explanation return.
  await page.click('#previewSummary');assert.equal(await page.locator('#modalBody .summary-card').count(),5);
  await page.locator('#modalBody [data-summary="0"]').click();await page.locator('#modalBody [data-summary-info="0"]').click();
  assert.equal(await page.textContent('#previewCloseBtn'),'Back to summary');await page.click('#previewCloseBtn');
  assert.equal(await page.locator('#modalBody .summary-card').count(),5);
  await page.locator('#previewClose').focus();await page.keyboard.press('Shift+Tab');
  assert(await page.evaluate(()=>document.querySelector('#previewOverlay').contains(document.activeElement)));
  await page.keyboard.press('Escape');assert.equal(await page.evaluate(()=>document.activeElement.id),'previewSummary');
  await page.click('#previewMenu');await page.locator('#navDrawerClose').focus();await page.keyboard.press('Shift+Tab');
  assert(await page.evaluate(()=>document.querySelector('#navDrawer').contains(document.activeElement)));
  await page.keyboard.press('Escape');assert.equal(await page.evaluate(()=>document.activeElement.id),'previewMenu');
  assert.equal(await page.evaluate(()=>JSON.stringify(window.MockData)),sharedBefore);
  receipt.interactions.push(`summary, explanation, traps, drawer, immutable data (${touch?'touch':'mouse'})`);
  // Public holiday keeps classes, not just the notice; scenarios cover edge fixtures.
  await page.goto(url+'?week=7&day=0');assert(await page.locator('#previewHoliday .preview-holiday').isVisible());assert.equal(await page.locator('#previewAgenda [data-event]').count(),2);
  for(const scenario of ['overlap','empty-day','empty-week','proposed']) {
   await page.goto(url+'?scenario='+scenario);
   if(scenario==='overlap')assert.equal(await page.locator('#previewAgenda .ev-overlap').count(),3);
   if(scenario==='empty-day')assert(await page.locator('#previewAgenda .empty-state').isVisible());
   if(scenario==='empty-week'){assert(await page.locator('#previewEmpty').isVisible());assert(await page.locator('#previewDesktop').isHidden());}
   if(scenario==='proposed'){const card=page.locator('#previewAgenda [data-event]').first();assert((await card.textContent()).includes('Proposed'));assert.equal(await card.locator('.preview-status').count(),0);}
  }
  for(const width of [744,1024,1440]) {
   await page.setViewportSize({width,height:1024});await page.goto(url+'?scenario=empty-week');
   assert(await page.locator('#previewEmpty').isVisible());assert(await page.locator('#previewTablet').isHidden());assert(await page.locator('#previewDesktop').isHidden());
   await page.goto(url+'?scenario=overlap');
   const surface=width===1440&&!touch?'#previewDesktop':'#previewTablet';
   assert.equal(await page.locator(surface+' [data-event="sample-overlap"]').count(),1);
  }
  await page.setViewportSize({width:390,height:844});
  receipt.interactions.push(`holiday, overlap/empty week in every mode, empty day, proposed (${touch?'touch':'mouse'})`);
  await page.goto(url);await page.emulateMedia({media:'print'});await page.evaluate(()=>dispatchEvent(new Event('beforeprint')));
  assert(await page.locator('#previewDesktop').isVisible());assert.equal(await page.locator('#previewDesktop [data-event]').count(),9);
  assert(await page.locator('#previewDays').isHidden());assert(await page.locator('#previewExtras').isVisible());
  await page.emulateMedia({media:'screen'});await page.evaluate(()=>dispatchEvent(new Event('afterprint')));assert.equal(await page.locator('body').getAttribute('data-mode'),'agenda');
  receipt.interactions.push(`full-week print and restore (${touch?'touch':'mouse'})`);
  assert.deepEqual(errors,[]);assert.deepEqual(forbidden,[]);await context.close();
 }
 fs.writeFileSync('/tmp/cohort-preview-receipt.json',JSON.stringify(receipt,null,2));
 console.log(`PASS: ${receipt.matrix.length} width/theme/input combinations; ${receipt.interactions.length} interaction groups. No page errors or non-static/write requests. Receipt: /tmp/cohort-preview-receipt.json`);
 } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
