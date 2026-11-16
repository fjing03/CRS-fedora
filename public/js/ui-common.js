// ui-common.js — Shared JS helpers for all UI templates

function updateIcon(isDark) {
    const icon = document.getElementById('theme-icon');
    if (!icon) return;
    icon.innerHTML = isDark
        ? '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'
        : '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>';
}

function toggleTheme() {
    const html = document.documentElement;
    const isDark = html.classList.contains('dark');
    html.classList.toggle('light');
    html.classList.toggle('dark');
    localStorage.setItem('theme', isDark ? 'light' : 'dark');
    updateIcon(!isDark);
}

function navigateHome() {
    /* R-3 (round-3): logo goes to the page's role home (staff → My Timetable,
       student → Student My Timetable); pages without a homeUrl land on the
       welcome view. The layout emits window.PAGE_HOME. */
    window.location.href = window.PAGE_HOME || '/';
}

function updateWeekArrows(prevDisabled, nextDisabled) {
    const prev = document.querySelector('.week-arrow[aria-label="Previous week"]');
    const next = document.querySelector('.week-arrow[aria-label="Next week"]');
    if (prev) prev.disabled = prevDisabled;
    if (next) next.disabled = nextDisabled;
}

/* Scoped variant of updateWeekArrows: the pair inside ONE .week-nav parent. Kept
   separate so updateWeekArrows' document-order contract cannot drift — the page
   with two week fields must never let the second nav's arrows go stale. */
function updateWeekArrowsIn(nav, prevDisabled, nextDisabled) {
    if (!nav) return;
    const prev = nav.querySelector('.week-arrow[aria-label="Previous week"]');
    const next = nav.querySelector('.week-arrow[aria-label="Next week"]');
    if (prev) prev.disabled = prevDisabled;
    if (next) next.disabled = nextDisabled;
}

// ───── Week helpers (shared by timetable pages) ─────

function currentWeekIndex() {
    const parts = MockData.semester.startDate.split('-');
    const semesterStart = new Date(parts[0], parts[1] - 1, parts[2]);
    const today = new Date(getTodayMs());
    today.setHours(0, 0, 0, 0);
    const idx = Math.floor((today - semesterStart) / 86400000 / 7);
    return Math.max(0, Math.min(MockData.semester.weeks - 1, idx));
}

function updateProgress() {
    const pct = ((currentWeek + 1) / MockData.semester.weeks) * 100;
    const fill = document.getElementById('progressFill');
    const label = document.getElementById('progressLabel');
    if (fill) fill.style.width = pct + '%';
    if (label) label.textContent = 'Week ' + (currentWeek + 1) + ' of ' + MockData.semester.weeks;
}

function generateWeekData() {
    const parts = MockData.semester.startDate.split('-');
    const start = new Date(parts[0], parts[1] - 1, parts[2]);
    const arr = [];
    const todayMs = getTodayMs();
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const fmt = d => `${d.getDate()} ${months[d.getMonth()]} ${d.getFullYear()}`;
    const fmtShort = d => `${d.getDate()} ${months[d.getMonth()]}`;
    for (let w = 1; w <= MockData.semester.weeks; w++) {
        const ms = start.getTime() + (w - 1) * 7 * 86400000;
        const mon = new Date(ms);
        const sun = new Date(ms + 6 * 86400000);
        const days = [];
        for (let d = 0; d < 7; d++) {
            const dt = new Date(ms + d * 86400000);
            let holiday = false;
            let holidayLabel = '';
            MockData.holidays.forEach(function(h) {
                if (h.week === w && h.dayIndex === d) {
                    holiday = true;
                    holidayLabel = h.label;
                }
            });
            days.push({
                abbr: ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'][d],
                date: fmt(dt),
                sunday: d === 6,
                today: dt.getTime() === todayMs,
                holiday: holiday,
                holidayLabel: holidayLabel,
            });
        }
        arr.push({ label: `Week ${w}`, range: `${fmt(mon)} ~ ${fmt(sun)}`, rangeShort: `${fmtShort(mon)} ~ ${fmtShort(sun)}`, days });
    }
    /* mark the semester's earliest bookable day (the lead-time boundary) —
       grid builders render the "Bookings open" chip in its time-col when the
       page opts in (cfg.bookableBadge) */
    const fb = firstBookableDay(arr);
    if (fb) arr[fb.week].days[fb.day].firstBookable = true;
    return arr;
}

// ───── Lead-Time Rule (3 working days) ─────
/**
 * The first selectable date under the lead-time rule: the 3rd working day
 * from the MockData.mockNow anchor (Mon-Fri, skipping the page's holiday
 * flags). A slot on this date IS selectable (>= 3, per the agreed boundary).
 * @param {Array} weekData — page week data (days carry .date 'j M Y' + .holiday)
 * @returns {Date}
 */
function leadTimeCutoff(weekData) {
    const today = new Date(getTodayMs());
    const holidays = new Set();
    (weekData || []).forEach(function(w) {
        (w.days || []).forEach(function(d) { if (d.holiday) holidays.add(d.date); });
    });
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const wd = new Date(today);
    let count = 0;
    while (count < 3) {
        wd.setDate(wd.getDate() + 1);
        const dow = wd.getDay();
        if (dow === 0 || dow === 6) continue; /* weekend */
        if (holidays.has(wd.getDate() + ' ' + months[wd.getMonth()] + ' ' + wd.getFullYear())) continue;
        count++;
    }
    return wd;
}

/**
 * True when a slot may NOT be selected: it lies in the past or fewer than
 * 3 working days from the MockData.mockNow anchor.
 * @param {Array}  weekData — page week data (days carry .date 'j M Y' + .holiday)
 * @param {number} weekIdx
 * @param {number} dayIdx
 * @returns {boolean} true = the cell renders read-only
 */
function isSlotTooSoon(weekData, weekIdx, dayIdx) {
    const day = weekData && weekData[weekIdx] && weekData[weekIdx].days && weekData[weekIdx].days[dayIdx];
    if (!day) return false;
    const slot = new Date(day.date);
    slot.setHours(0, 0, 0, 0);
    const today = new Date(getTodayMs());
    if (slot <= today) return true; /* past, or today itself */
    return slot < leadTimeCutoff(weekData);
}

/**
 * A week is BOOKABLE when at least one day can host a replacement request:
 * not Sunday, not a holiday, and not too-soon (past / inside the
 * 3-working-day window). Drives the arrangement page's hidden-unbookable
 * weeks rule and its "Earliest bookable" destination — one source of truth
 * with isSlotTooSoon so they can never drift apart.
 * @param {Array}  weekData
 * @param {number} weekIdx
 * @returns {boolean} true = at least one selectable slot exists that week
 */
function weekHasBookableSlot(weekData, weekIdx) {
    const week = weekData && weekData[weekIdx];
    if (!week) return false;
    for (var d = 0; d < week.days.length; d++) {
        const day = week.days[d];
        if (day.sunday || day.abbr === 'Sun' || day.holiday) continue;
        if (isSlotTooSoon(weekData, weekIdx, d)) continue;
        return true;
    }
    return false;
}

/**
 * The earliest bookable day in a given week (first day that can host a
 * request) — i.e. the lead-time boundary day for that week.
 * @param {Array}  weekData
 * @param {number} weekIdx
 * @returns {number} day index, or -1 when the week has no bookable day
 */
function firstBookableDayIn(weekData, weekIdx) {
    const week = weekData && weekData[weekIdx];
    if (!week) return -1;
    for (var d = 0; d < week.days.length; d++) {
        const day = week.days[d];
        if (day.sunday || day.abbr === 'Sun' || day.holiday) continue;
        if (isSlotTooSoon(weekData, weekIdx, d)) continue;
        return d;
    }
    return -1;
}

/**
 * The earliest bookable day across the whole semester — the "Earliest
 * bookable" destination: { week, day } indexes, or null when no week is
 * bookable at all (end-of-semester edge; pages must guard).
 * @param {Array} weekData
 * @returns {{week:number, day:number}|null}
 */
function firstBookableDay(weekData) {
    if (!weekData) return null;
    for (var w = 0; w < weekData.length; w++) {
        const d = firstBookableDayIn(weekData, w);
        if (d >= 0) return { week: w, day: d };
    }
    return null;
}

/**
 * Pulses the earliest bookable day row so the arrangement page's
 * "Earliest bookable" action lands on the exact origin (the lead-time
 * boundary day), not just somewhere in the week. Day rows carry
 * tr[data-day-index] (builder sets tr.dataset.dayIndex); the pulse uses the
 * shared success token.
 * @param {Array}  weekData
 * @param {number} weekIdx — the week on screen (must hold a bookable day)
 */
function flashEarliestBookableDay(weekData, weekIdx) {
    const body = document.getElementById('tableBody');
    const dayIdx = firstBookableDayIn(weekData, weekIdx);
    if (!body || dayIdx < 0) return;
    const rows = body.querySelectorAll('tr[data-day-index="' + dayIdx + '"]');
    rows.forEach(function(r) {
        flashRowPulse(r);
    });
}

/* The ONE pulse mechanic (notif-click-highlight): the shared flash the
   notification anchor + the earliestBtn jump both use — the SAME class
   (.bookable-flash, the 1.15 s pulse) with a reflow restart on repeats. */
var BOOKABLE_PULSE_MS = 500;   /* one cycle — keep in step with theme.css */
var BOOKABLE_PULSE_COUNT = 3;  /* how many flashes a jump paints */

/**
 * @param {Element} el
 * @param {string} [tone] — 'warning' paints the yellow arm (the just-cancelled
 *   row, 2026-11-06). Omitted = the shipped green family, byte-identical to
 *   every existing caller (earliest / today / notification / timetable jumps).
 */
function flashRowPulse(el, tone) {
    el.classList.remove('bookable-flash', 'bookable-flash--warning');
    void el.offsetWidth; /* restart the animation on repeat visits */
    el.classList.add('bookable-flash');
    if (tone === 'warning') el.classList.add('bookable-flash--warning');
    /* the class must outlive the WHOLE run (3 × 0.5 s), or the last pulses never
       play — the old 1300 ms cap was sized for a single 1.15 s pulse */
    setTimeout(function() {
        el.classList.remove('bookable-flash', 'bookable-flash--warning');
    }, BOOKABLE_PULSE_MS * BOOKABLE_PULSE_COUNT + 150);
}

/**
 * Renders the contextual lead-time banner: visible only while the viewed
 * week contains blocked days (past / inside the 3-working-day window).
 * Same banner family as the booking-intent notice, neutral info tone.
 * @param {string} elId
 * @param {Array}  weekData
 * @param {number} weekIdx — the week currently on screen
 */
function renderLeadTimeNote(elId, weekData, weekIdx) {
    const el = document.getElementById(elId);
    if (!el) return;
    const week = weekData && weekData[weekIdx];
    const blocked = [];
    let fullyPast = true;
    if (week) {
        const today = new Date(getTodayMs());
        week.days.forEach(function(d, dIdx) {
            if (isSlotTooSoon(weekData, weekIdx, dIdx)) blocked.push(dIdx);
            const dt = new Date(d.date);
            dt.setHours(0, 0, 0, 0);
            if (dt >= today) fullyPast = false;
        });
    }
    if (!blocked.length) { el.innerHTML = ''; el.style.display = 'none'; return; }
    const cutoff = leadTimeCutoff(weekData);
    const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const dateStr = days[cutoff.getDay()] + ', ' + cutoff.getDate() + ' ' + months[cutoff.getMonth()] + ' ' + cutoff.getFullYear();
    /* copy: a fully-past week reads differently from the current one.
       The live variants state the rule itself ("a minimum of 3 working days'
       advance notice") and name the blocked span in words, because "some
       slots are too soon" never told the user WHY. The span is the first→last
       blocked day of the Mon→Sun week (days[] is getDay()-indexed, so the
       day index shifts by one); a mid-week holiday inside the span is
       swallowed by it, which is honest — that day is not bookable either. */
    let copy;
    if (fullyPast) {
        copy = 'This week has already passed \u2014 bookable from <strong>' + dateStr + '</strong> onward.';
    } else {
        const first = blocked[0];
        const last = blocked[blocked.length - 1];
        const wholeWeek = blocked.length === week.days.length;
        const span = wholeWeek
            ? ''
            : ' \u2014 ' + days[(first + 1) % 7] + (first === last ? '' : ' to ' + days[(last + 1) % 7]);
        const lead = wholeWeek
            ? 'The whole week is too soon'
            : (blocked.length >= 5 ? 'Most of this week is too soon' : 'Some slots this week are too soon');
        copy = lead + span + '. A minimum of <strong>3 working days\u2019</strong> advance notice is required. Bookable from <strong>' + dateStr + '</strong> onward.';
    }
    el.className = 'lead-time-note';
    el.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>'
        + '<span>' + copy + '</span>';
    el.style.display = '';
}

function updateWeekSubtitle() {
    const el = document.getElementById('weekSubtitle');
    if (el) {
        const week = weekData[currentWeek];
        let subtitle = 'Week ' + (currentWeek + 1) + ' of ' + MockData.semester.weeks;
        if (week.start && week.end) {
            subtitle += ' \u00B7 ' + DateHelper.fmt(week.start) + ' \u00B7 ' + DateHelper.fmt(week.end);
        } else if (week.range) {
            subtitle += ' \u00B7 ' + week.range;
        }
        el.textContent = subtitle;
    }
}

function updateWeekArrowState(selectId) {
    /* No id = today's behaviour exactly: #weekFilter, and the first pair in
       document order. With an id, only that select's own .week-nav pair moves. */
    if (selectId) {
        var scoped = document.getElementById(selectId);
        if (!scoped) return;
        updateWeekArrowsIn(
            scoped.parentNode && scoped.parentNode.closest ? scoped.parentNode.closest('.week-nav') : null,
            scoped.selectedIndex <= 0,
            scoped.selectedIndex >= scoped.options.length - 1
        );
        return;
    }
    var sel = document.getElementById('weekFilter');
    updateWeekArrows(sel.selectedIndex <= 0, sel.selectedIndex >= sel.options.length - 1);
}

// ───── Today button (shared by all timetable pages) ─────
// Each page must define: buildTimetable()
// Optionally define: updateSummary(), saveWeek()

document.addEventListener('DOMContentLoaded', function () {
    initNotifPanel();
    setTimeout(applyNotifHighlight, 150);   /* after the pages' own first render pass */
    setTimeout(applyReplacementHighlight, 150);
});

/* ─── The notification hands-off highlight (notif-click-highlight) ───
   A notif-hl URL param anchors the target record on arrival:
     r<request_id>  → the approval queue / request-history table row
     s<session_id>  → the cancelled class cell on /my-timetable-ui (with
                      notif-w = the occurrence week — the narrow exception
                      to ARRIVAL-GRID-NO-PIN, user 2026-10-30, notif links
                      ONLY). Everything else keeps the pin law.
   The flash = the SAME bookable-flash pulse the #earliestBtn jump uses.
   The URL params strip FIRST (unconditional — even on a miss, so a refresh
   never re-flashes). */
function applyNotifHighlight() {
    const params = new URLSearchParams(location.search);
    const raw = params.get('notif-hl');
    if (!raw) return;
    /* 2026-11-11: read the week BEFORE the deletes below — the old `s` arm read it
       after, so its week switch never fired (finding F1). The student `o` arm needs
       it too, and both now take it from this one capture. */
    const notifWeekRaw = params.get('notif-w');
    params.delete('notif-hl');
    params.delete('notif-w');
    const qs = params.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));

    if (raw.charAt(0) === 'r') {
        /* replacement-merge-one-table (M2/10b, 2026-11-04): the merged row is
           keyed by OCCURRENCE (data-id) but the stored link carries the REQUEST
           id — so the matcher tries data-request-id FIRST and falls back to
           data-id. Two separate lookups, never one comma-joined selector: a
           selector list resolves by document order, and an occurrence id
           (session*100+week) could otherwise win over the intended row. */
        const id = raw.slice(1);
        tryFind('tr[data-request-id="' + id + '"]', 14, 'tr[data-id="' + id + '"]');   /* the queue rows render past the loader — 14 × 250 ms ≈ 3.5 s budget (async; load unblocked) */
    } else if (raw.charAt(0) === 's') {
        // STAGE 2 — the my-timetable arrival: the week switch MUST ride the
        // page's own wrapper (it re-syncs currentWeek + saveWeek). Only
        // /my-timetable-ui exposes it; other surfaces degrade silently.
        if (location.pathname !== '/my-timetable-ui' || typeof window.selectWeek !== 'function') return;
        const wk = parseInt(notifWeekRaw || '', 10);
        if (!isNaN(wk) && wk >= 1 && wk <= 14) window.selectWeek(String(wk - 1));   // 1-based, the pinned rule
        const sid = raw.slice(1);
        /* cancelled-slot-reuse S19: a request landing and its cancelled
           original can share one cell — the SOURCE jump must flash the
           original occurrence, never its same-cell landing neighbour.
           Landing blocks carry data-rid; the original never does. */
        tryFind('#tableBody .event-block[data-sid="' + sid + '"]:not([data-rid])', 14);   /* the week rebuild margin */
    } else if (raw.charAt(0) === 'o') {
        /* 2026-11-11 (student notices): the student Class Replacements row is keyed
           by the CLASS OCCURRENCE (`<sessionId>:<week>`), which is exactly the
           `o<sessionId>` + notif-w pair the staff cancelled-class arm already
           carries. Gated on that page's own table body, so no other surface can be
           flashed by a stray `o` target. */
        if (!document.getElementById('upcomingBody')) return;
        const sid = raw.slice(1);
        const wk = notifWeekRaw;
        tryFind(wk ? 'tr[data-id="' + sid + ':' + wk + '"]' : 'tr[data-id^="' + sid + ':"]', 14);
    }
}

/**
 * The arrival arm for `Go to My Timetable` (mytimetable-jump-highlight).
 *
 * Shape copied from applyNotifHighlight() with two deliberate differences:
 *   1. the week is read BEFORE the params are deleted — applyNotifHighlight()
 *      deletes `notif-w` and then reads it back, so its week switch never fires
 *      (finding F1, out of scope there);
 *   2. there is NO fallback selector: a miss ends silently. Flashing the wrong
 *      class would be worse than not flashing at all.
 */
function applyReplacementHighlight() {
    const params = new URLSearchParams(location.search);
    const raw = params.get('repl-hl');
    if (!raw) return;
    const weekRaw = params.get('repl-w');
    params.delete('repl-hl');
    params.delete('repl-w');
    const qs = params.toString();
    history.replaceState(null, '', location.pathname + (qs ? '?' + qs : ''));

    /* only the pages that HAVE the blocks (staff and student timetables share the
       grid builder, so both emit `data-rid`); elsewhere the params are simply gone */
    const TIMETABLE_PATHS = ['/my-timetable-ui', '/student-my-timetable-ui'];
    if (TIMETABLE_PATHS.indexOf(location.pathname) === -1 || typeof window.selectWeek !== 'function') return;

    const wk = parseInt(weekRaw || '', 10);
    if (!isNaN(wk) && wk >= 1 && wk <= 14) window.selectWeek(String(wk - 1));   // 1-based
    tryFind('#tableBody .event-block[data-rid="' + raw + '"]', 20);            /* 5 s budget: the grid renders after the week restore */
}

/* the retried finder (the payload-built rows may render past the page's
   loader — the retries are ASYNC timers, load-blocking); found: scroll
   into view THEN the flash (the earliest flow's jump-then-pulse shape);
   not found: graceful silent skip. */
function tryFind(sel, left, fallbackSel) {
    const el = document.querySelector(sel) || (fallbackSel ? document.querySelector(fallbackSel) : null);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () {
            /* Re-query at flash time (2026-11-04): the landing page can rebuild
               its table between the find and the 500 ms beat — the skeleton
               teardown on /my-replacements does exactly that — so an element
               captured now may be detached by then, and flashing it would paint
               a pulse nobody can see. Flash whatever carries the selector at
               the moment of the pulse, falling back to the element we found. */
            const live = document.querySelector(sel) || (fallbackSel ? document.querySelector(fallbackSel) : null) || el;
            flashRowPulse(live);
        }, 500);
        return;
    }
    if (left > 0) setTimeout(function () { tryFind(sel, left - 1, fallbackSel); }, 250);
    /* else: the graceful skip (e.g. a filtered-out / paginated-away row) */
}

function jumpToToday() {
    currentWeek = currentWeekIndex();
    window.currentWeek = currentWeek;
    if (typeof window.buildTimetable === 'function') window.buildTimetable();
    var sel = document.getElementById('weekSelect');
    if (sel) sel.selectedIndex = currentWeek;
    if (typeof window.updateWeekSubtitle === 'function') window.updateWeekSubtitle();
    if (typeof window.updateSummary === 'function') window.updateSummary();
    if (typeof window.updateProgress === 'function') window.updateProgress();
    var grid = document.querySelector('.grid-wrapper');
    if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function initTodayBtn() {
    var btn = document.getElementById('todayBtn');
    if (btn) btn.addEventListener('click', jumpToToday);
}

class WeekNavigator {
    constructor(semesterData, weekData, selectId, storageKey, weekFilter, options) {
        this._semester = semesterData;
        this._weekData = weekData;
        this._currentWeek = 0;
        this._selectId = selectId || 'weekSelect';
        /* namespaced by default (sweep-fixes-round-1, F-11): pages that don't
           pass a key get one derived from their select id, so sibling pages
           can't overwrite each other's saved position anymore (the old
           shared-collision note below). Explicit keys win when passed. */
        this._storageKey = storageKey || ('weekNav-' + (this._selectId || 'weekSelect'));

        /* optional visibility filter (page-supplied): when present, weeks
           failing it (e.g. no bookable slot) are skipped in navigation and
           resolved away on load/select; null = every week is navigable
           (venue-timetable keeps its full list — it's a browsing/history page) */
        this._weekFilter = weekFilter || null;
        /* week-memory-until-logout (user law 2026-10-20): the SESSION-SCOPED
           storage mode — the per-login marker (login_week_stamp, minted at
           the Fortify login hook) binds a page's week memory to the login:
           fresh login / logout / expiry = every page stands on its own
           today; in-session navs save per-page versions until logout.
           save()/load() branch on _sessionScoped; localStorage is NEVER
           read in that mode (the old cross-login memory dies). */
        this._sessionScoped = !!(options && options.sessionScoped);
        this._loginStamp = (options && options.loginStamp) || '';
        this._weekMemKey = 'weekmem-' + this._storageKey + '@' + this._loginStamp;
    }

    get currentWeek() {
        return this._currentWeek;
    }

    get weekData() {
        return this._weekData;
    }

    get semester() {
        return this._semester;
    }

    jumpToToday() {
        this._currentWeek = this._currentWeekIndex();
        this._updateSelect();
        this._buildTimetable();
        this._updateSubtitle();
        this._updateProgress();
        this._scrollToGrid();
        this.save();
        /* notif-jump-flash-family: the today column FLASHES after the jump
           — the same bookable family pulse the earliestBtn jump uses
           (jump-then-pulse shape). */
        setTimeout(() => {
            document.querySelectorAll('td.today-cell').forEach((td) => flashRowPulse(td));
        }, 250);
    }

    prevWeek() {
        const target = this._resolveStep(this._currentWeek - 1, -1);
        if (target >= 0 && target < this._currentWeek) {
            this._beforeNavigate();
            this._currentWeek = target;
            this._updateSelect();
            this._buildTimetable();
            this._updateSubtitle();
            this._updateProgress();
            this._updateArrows();
            this.save();
        }
    }

    nextWeek() {
        const target = this._resolveStep(this._currentWeek + 1, 1);
        if (target >= 0 && target > this._currentWeek) {
            this._beforeNavigate();
            this._currentWeek = target;
            this._updateSelect();
            this._buildTimetable();
            this._updateSubtitle();
            this._updateProgress();
            this._updateArrows();
            this.save();
        }
    }

    selectWeek(index) {
        if (this._weekFilter && !this._weekFilter(index)) {
            const resolved = this._resolveStep(index, 1);
            if (resolved < 0) return; /* nothing visible — stay put */
            index = resolved;
        }
        this._beforeNavigate();
        this._currentWeek = index;
        this._updateSelect();
        this._buildTimetable();
        this._updateSubtitle();
        this._updateProgress();
        this._updateArrows();
        this.save();
    }

    /**
     * Steps from `from` in direction `dir` (-1 back / +1 forward) until a
     * week passes the visibility filter; -1 when none does. Without a
     * filter this is the identity (`from` itself), so unfiltered pages
     * behave exactly as before.
     */
    _resolveStep(from, dir) {
        var i = from;
        var max = this._semester.weeks - 1;
        while (i >= 0 && i <= max) {
            if (!this._weekFilter || this._weekFilter(i)) return i;
            i += dir;
        }
        return -1;
    }

    /** First visible (weekFilter-passing) week index, or -1 when none. */
    firstVisibleWeek() {
        return this._resolveStep(0, 1);
    }

    /**
     * "Earliest bookable" destination: jump to the first week that can host
     * a request and flash its boundary day. Falls back to real "today" when
     * no week is bookable at all (end-of-semester edge — grid renders
     * read-only rather than the dropdown going empty).
     */
    jumpToEarliestBookable() {
        var target = this.firstVisibleWeek();
        if (target < 0) return this.jumpToToday();
        if (target !== this._currentWeek) {
            this._beforeNavigate();
            this._currentWeek = target;
            this._updateSelect();
            this._buildTimetable();
            this._updateSubtitle();
            this._updateProgress();
            this._updateArrows();
            this._scrollToGrid();
            this.save();
        }
        if (typeof this.flashEarliestBookable === 'function') this.flashEarliestBookable();
    }

    onWeekChange() {
        var sel = document.getElementById(this._selectId);
        if (sel) this.selectWeek(parseInt(sel.value, 10));
    }

    _beforeNavigate() {
        if (typeof this.onBeforeNavigate === 'function') this.onBeforeNavigate();
    }

    save() {
        try {
            if (this._sessionScoped) {
                /* SYMMETRIC gate: no stamp = no memory writes (the register
                   auto-login path never mints one — today-only session). */
                if (this._loginStamp === '') return;
                sessionStorage.setItem(this._weekMemKey, this._currentWeek);
                return;   /* session mode: localStorage never touched */
            }
            localStorage.setItem(this._storageKey, this._currentWeek);
        } catch (e) { /* ignore */ }
    }

    load() {
        try {
            if (this._sessionScoped) {
                /* session mode: a stamp-less or mismatched context =
                   memoryless → today. The legacy migration and the
                   localStorage family NEVER run here (the cross-login
                   memory dies by construction). The stamp lives in the
                   server session; logout/expiry invalidates it → every
                   fresh login re-lands today ✓ law held through every
                   server path. */
                var sv = this._loginStamp ? sessionStorage.getItem(this._weekMemKey) : null;
                if (sv !== null && !isNaN(parseInt(sv, 10))) {
                    this._currentWeek = Math.max(0, Math.min(this._semester.weeks - 1, parseInt(sv, 10)));
                }
            } else {
                /* one-time legacy migration (F-11): pages that used to share the
                   generic 'currentWeek' key adopt it once, then it is retired —
                   first visited page inherits the old position, later pages fall
                   through to today. Harmless because demo week picks don't persist. */
                var saved = localStorage.getItem(this._storageKey);
                if (saved === null) {
                    var legacy = localStorage.getItem('currentWeek');
                    if (legacy !== null) {
                        localStorage.setItem(this._storageKey, legacy);
                        localStorage.removeItem('currentWeek');
                        saved = legacy;
                    }
                }
                if (saved !== null) {
                    var idx = parseInt(saved, 10);
                    if (!isNaN(idx)) {
                        this._currentWeek = Math.max(0, Math.min(this._semester.weeks - 1, idx));
                    }
                }
            }
        } catch (e) { /* ignore */ }
        /* snap to a visible week when the saved one is hidden now (e.g. a
           stored week that no longer holds a bookable slot) */
        if (this._weekFilter && !this._weekFilter(this._currentWeek)) {
            var fv = this.firstVisibleWeek();
            if (fv >= 0) this._currentWeek = fv;
        }
    }

    initKeyboard() {
        if (this._keyboardBound) return;
        this._keyboardBound = true;
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
            if (e.key === '[') {
                e.preventDefault();
                this.prevWeek();
            } else if (e.key === ']') {
                e.preventDefault();
                this.nextWeek();
            }
        });
    }

    initTodayBtn() {
        var btn = document.getElementById('todayBtn');
        if (btn) btn.addEventListener('click', () => this.jumpToToday());
    }

    saveWeek() {
        this.save();
    }

    loadSavedWeek() {
        this.load();
    }

    initWeekKeyboardShortcuts() {
        this.initKeyboard();
    }

    updateWeekSubtitle() {
        this._updateSubtitle();
    }

    updateWeekProgress() {
        this._updateProgress();
    }

    _currentWeekIndex() {
        var parts = this._semester.startDate.split('-');
        var semesterStart = new Date(parts[0], parts[1] - 1, parts[2]);
        var today = new Date(getTodayMs());
        today.setHours(0, 0, 0, 0);
        var idx = Math.floor((today - semesterStart) / 86400000 / 7);
        return Math.max(0, Math.min(this._semester.weeks - 1, idx));
    }

    _buildTimetable() {
        if (typeof window.buildTimetable === 'function') window.buildTimetable();
    }

    _updateSelect() {
        var sel = document.getElementById(this._selectId);
        if (!sel) return;
        /* with a filter the options are a subset — match by value, not position */
        if (this._weekFilter) {
            var v = String(this._currentWeek);
            for (var o = 0; o < sel.options.length; o++) {
                if (sel.options[o].value === v) { sel.selectedIndex = o; return; }
            }
            return;
        }
        sel.selectedIndex = this._currentWeek;
    }

    _updateSubtitle() {
        var el = document.getElementById('weekSubtitle');
        if (el) {
            var week = this._weekData[this._currentWeek];
            var subtitle = 'Week ' + (this._currentWeek + 1) + ' of ' + this._semester.weeks;
            if (week.start && week.end) {
                subtitle += ' \u00B7 ' + DateHelper.fmt(week.start) + ' \u00B7 ' + DateHelper.fmt(week.end);
            } else if (week.range) {
                subtitle += ' \u00B7 ' + week.range;
            }
            el.textContent = subtitle;
        }
    }

    _updateProgress() {
        var pct = ((this._currentWeek + 1) / this._semester.weeks) * 100;
        var fill = document.getElementById('progressFill');
        var label = document.getElementById('progressLabel');
        if (fill) fill.style.width = pct + '%';
        if (label) label.textContent = 'Week ' + (this._currentWeek + 1) + ' of ' + this._semester.weeks;
    }

    _updateArrows() {
        var prev = document.querySelector('.week-arrow[aria-label="Previous week"]');
        var next = document.querySelector('.week-arrow[aria-label="Next week"]');
        /* with a filter, arrow ends = the visible set's edges */
        if (this._weekFilter) {
            if (prev) prev.disabled = this._resolveStep(this._currentWeek - 1, -1) < 0;
            if (next) next.disabled = this._resolveStep(this._currentWeek + 1, 1) < 0;
            return;
        }
        if (prev) prev.disabled = this._currentWeek <= 0;
        if (next) next.disabled = this._currentWeek >= this._semester.weeks - 1;
    }

    _scrollToGrid() {
        var grid = document.querySelector('.grid-wrapper');
        if (grid) grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

// ───── Time slot helpers (shared by timetable pages) ─────

const hours = [
    '08:00', '08:30', '09:00', '09:30',
    '10:00', '10:30', '11:00', '11:30',
    '12:00', '12:30',
    '13:00', '13:30', '14:00', '14:30',
    '15:00', '15:30', '16:00', '16:30',
    '17:00', '17:30', '18:00', '18:30'
];

// ───── Shared lane layout (cancelled-slot-reuse D6/S17) ─────

/**
 * Pure deterministic lane planner for timetable events.
 *
 * Input: unique, already-scoped event records with INCLUSIVE half-hour grid
 * indices `start..end` and a day index `di`. The day is partitioned into
 * connected interval-overlap components (half-open `[start, end+1)` —
 * adjacent intervals never overlap); each component is laid out
 * independently from lane 0, so distant non-overlapping blocks reuse lanes,
 * and a day's lane count is its largest component's, minimum one.
 *
 * Within a component, cancelled records sort FIRST, then every other
 * record; each group sorts by (start, end, eventKey) with a full-record
 * serialization as the final tiebreak, so the output is invariant under
 * input shuffling. Cancellations take the first-fit LOWEST lane whose
 * placed records do not overlap; active records take the first-fit lowest
 * lane that is strictly BELOW every directly overlapping cancellation and
 * free of overlapping actives.
 *
 * The input is never mutated; true time and span are preserved; every
 * event — same coordinates or not — keeps its own record (identity dedupe
 * is the payload's job; geometry is never identity). Layout makes no
 * availability decision: it only stacks history.
 *
 * @param {Array} events
 * @returns {{days: Object<string, {placed: Array<{event: object, lane: number, span: number}>, lanes: number}>, lanes: number}}
 */
function layoutTimetableDay(events) {
    var list = (events || []).filter(function (e) {
        return e && typeof e.start === 'number' && typeof e.end === 'number';
    });

    function groupOf(e) { return e.status === 'cancelled' ? 0 : 1; }
    /* Documented stable comparator: cancelled-first group, then start, end,
       eventKey, then a serialization of the remaining identity fields — the
       last line makes the order total even for legacy key-less records, so
       a shuffled input can never change the output. */
    function sortKey(e) {
        return [groupOf(e), e.start, e.end, String(e.eventKey || ''),
            JSON.stringify([e.code, e.type, e.venue, e.lecturer, e.status, e.di, e.name])];
    }
    function cmp(a, b) {
        var ka = sortKey(a), kb = sortKey(b);
        for (var i = 0; i < ka.length; i++) {
            if (ka[i] < kb[i]) return -1;
            if (ka[i] > kb[i]) return 1;
        }
        return 0;
    }
    /* Chronological component order: sort by (start, end) first so the
       interval-overlap components form in time order; cmp breaks exact
       ties. `list` is a fresh array — the caller's input is untouched. */
    list.sort(function (a, b) {
        if (a.start !== b.start) return a.start - b.start;
        if (a.end !== b.end) return a.end - b.end;
        return cmp(a, b);
    });

    function overlaps(a, b) { return a.start < b.end && b.start < a.end; }   /* half-open */

    var days = {};
    var maxLanes = 1;

    var byDay = {};
    list.forEach(function (e) {
        var d = String(e.di);
        (byDay[d] = byDay[d] || []).push(e);
    });

    Object.keys(byDay).forEach(function (d) {
        var evs = byDay[d];   /* chronological sort → per-day chronological */

        /* 1. connected interval-overlap components, in start order */
        var components = [];
        var comp = null;
        evs.forEach(function (e) {
            var halfEnd = e.end + 1;
            if (!comp || e.start >= comp.end) {
                comp = { end: halfEnd, events: [e] };
                components.push(comp);
            } else {
                comp.events.push(e);
                if (halfEnd > comp.end) comp.end = halfEnd;
            }
        });

        var placed = [];
        var dayLanes = 1;

        /* D6 step 2: restore cancellation-first allocation order inside
           each component; step 4: lane bookkeeping is per component, so
           independent components restart from lane zero. */
        components.forEach(function (c) {
            c.events.sort(cmp);
            var cancByLane = [];
            var actByLane = [];
            var compLanes = 1;

            /* 3a. cancellations first — first-fit into the lowest
                   non-overlapping lane. Stored and candidate intervals are
                   both half-open {start, end+1}. */
            c.events.forEach(function (e) {
                if (groupOf(e) !== 0) return;
                var interval = { start: e.start, end: e.end + 1 };
                var lane = 0;
                while ((cancByLane[lane] || []).some(function (cc) { return overlaps(cc, interval); })) lane++;
                (cancByLane[lane] = cancByLane[lane] || []).push(interval);
                compLanes = Math.max(compLanes, lane + 1);
                placed.push({ event: e, lane: lane, span: e.end - e.start + 1 });
            });
            /* 3b. active records — first-fit, but strictly below EVERY
                   directly overlapping cancellation, and never on top of an
                   overlapping active in the chosen lane */
            c.events.forEach(function (e) {
                if (groupOf(e) === 0) return;
                var interval = { start: e.start, end: e.end + 1 };
                var minLane = 0;
                cancByLane.forEach(function (laneCanc, lane) {
                    (laneCanc || []).forEach(function (cc) {
                        if (overlaps(cc, interval)) minLane = Math.max(minLane, lane + 1);
                    });
                });
                var lane = minLane;
                while ((actByLane[lane] || []).some(function (aa) { return overlaps(aa, interval); })) lane++;
                (actByLane[lane] = actByLane[lane] || []).push(interval);
                compLanes = Math.max(compLanes, lane + 1);
                placed.push({ event: e, lane: lane, span: e.end - e.start + 1 });
            });
            dayLanes = Math.max(dayLanes, compLanes);
        });

        /* deterministic render order: lane, then the same stable sort key */
        placed.sort(function (a, b) {
            if (a.lane !== b.lane) return a.lane - b.lane;
            return cmp(a.event, b.event);
        });

        days[d] = { placed: placed, lanes: dayLanes };
        maxLanes = Math.max(maxLanes, dayLanes);
    });

    return { days: days, lanes: maxLanes };
}

/**
 * Dedupe by the payload's stable eventKey ONLY — never day/start or lane
 * (cancelled-slot-reuse D7/S22). Combined-cohort duplicates of one identity
 * count once; distinct coincident identities (same start, different key)
 * each count. Legacy rows without a key stay separate events — coordinates
 * are never treated as identity.
 * @param {Array} events
 * @returns {Array}
 */
function dedupeEventsByKey(events) {
    var seen = {};
    return (events || []).filter(function (e) {
        if (!e) return false;
        if (e.eventKey === undefined || e.eventKey === null || e.eventKey === '') return true;
        if (seen[e.eventKey]) return false;
        seen[e.eventKey] = true;
        return true;
    });
}

// ───── Shared timetable grid builder ─────

/**
 * Build a timetable grid. Call from page-level buildTimetable().
 * @param {object} cfg
 * @param {Array} cfg.events - Array of event objects for the current week
 * @param {Array} cfg.days - Array of day objects from weekData
 * @param {function} cfg.onEventClick - Callback (event, dayIndex) when an event block is clicked
 * @param {string} [cfg.tableId='timetable'] - ID of the table element
 * @param {string} [cfg.headId='tableHead'] - ID of the thead element
 * @param {string} [cfg.bodyId='tableBody'] - ID of the tbody element
 * @param {string} [cfg.emptyId='emptyState'] - ID of the empty state element
 */
/**
 * Build a timetable grid. Call from page-level buildTimetable().
 * @param {object} cfg
 * @param {Array} cfg.events - Array of event objects for the current week
 * @param {Array} cfg.days - Array of day objects from weekData
 * @param {function} [cfg.onEventClick] - Click handler for event blocks (event, dayIndex)
 * @param {function} [cfg.cellRender] - Optional custom cell renderer (td, dayIndex, hourIndex, day).
 *   When provided, the header + day-column scaffolding is still shared but each hour cell is
 *   delegated to this callback instead of the default event-block layout. Used by
 *   replacement-arrangement + venue-timetable (selection cell model).
 */
function buildReplacementNote(e, opts) {
    opts = opts || {};
    var checkOwnership = opts.checkOwnership;
    if (typeof checkOwnership === 'function' && !checkOwnership(e)) return '';
    if (e.status === 'cancelled' && e.proposed) {
        /* cancelled-blocks-stay-visible, USER DECISION 2026-10-27: a
           cancelled block WITH a proposed (pending) replacement shows
           "(Proposed ⏳)" — the SAME hint idiom the natural-conflict blocks
           carry (the arm must precede the plain-Cancelled arm or it would
           swallow the hint). */
        return '<span class="ev-note">(Proposed ⏳)</span>';
    }
    /* cancelled-blocks-stay-visible (user law 2026-10-27): the cancelled
       block's note is PLAIN — the third sibling of '(Replaced for …)' and
       '(Proposed ⏳)'. The TIP stays plain too (the user decision: no reason
       string in the tip/note; the home badge keeps it via the payload). */
    if (e.status === 'cancelled') {
        return '<span class="ev-note">(Cancelled)</span>';
    }
    if (e.status === 'replacement' && e.remarks) {
        return '<span class="ev-note">(Replaced for ' + e.remarks + ')</span>';
    }
    // SDD full-block-reservation user directive 2026-10-17 (option A): the
    // natural conflict/holiday occurrence with a covered proposal shows the
    // TEXT-SUFFIX hint INSIDE the red block ("Proposed ⏳") — the amber block
    // rides the make-up slot's own payload now.
    if (e.proposed) {
        return '<span class="ev-note">(Proposed ⏳)</span>';
    }
    if (e.status === 'pending' && e.requestedAt) {
        var submitted = e.requestedAt.split(',')[0];
        return '<span class="ev-note">(Pending since ' + submitted + ')</span>';
    }
    return '';
}

function buildTimetableGrid(cfg) {
    const head = document.getElementById(cfg.headId || 'tableHead');
    const body = document.getElementById(cfg.bodyId || 'tableBody');
    const tableEl = document.getElementById(cfg.tableId || 'timetable');
    const emptyEl = document.getElementById(cfg.emptyId || 'emptyState');
    head.innerHTML = '';
    body.innerHTML = '';

    if (!cfg.cellRender) {
        if (cfg.events.length === 0) {
            tableEl.style.display = 'none';
            emptyEl.style.display = 'flex';
            return;
        }
        tableEl.style.display = '';
        emptyEl.style.display = 'none';
    } else {
        tableEl.style.display = '';
    }

    const timeHeaderRow = document.createElement('tr');
    const cornerTh = document.createElement('th');
    cornerTh.className = 'time-header-col';
    cornerTh.style.cssText = 'position: sticky; left: 0; z-index: 40;';
    cornerTh.innerHTML = '<span class="card-title">Day / Time</span>';
    timeHeaderRow.appendChild(cornerTh);

    for (let i = 0; i < hours.length; i += 2) {
        const th = document.createElement('th');
        th.className = 'hour-header';
        th.colSpan = 2;
        th.innerHTML = '<span class="hour-top">' + hours[i] + '</span><span class="hour-bottom">' + (hours[i + 2] || add30min(hours[i + 1])) + '</span>';
        timeHeaderRow.appendChild(th);
    }
    head.appendChild(timeHeaderRow);

    /* The default-path event block factory (my-timetable / student / cohort).
       ONE focusable, clickable target per event identity: data-event-key
       rides every payload event that carries one (cancelled-slot-reuse
       S14/S18); data-sid / data-rid keep their legacy jump contracts. */
    /* The status word's glyph as an HTML string — makeEventBlock() builds its block
       content as a string, so the DOM builder inside TimetableResponsive cannot be
       used there. Same two shapes, one file, no second vocabulary (item #9). */
    function statusIconHtml(kind) {
        const paths = kind === 'cancelled'
            ? '<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>'
            : '<path d="M12 3 2.6 20h18.8L12 3z"/><path d="M12 9v5M12 17.2v.2"/>';
        return '<svg class="tt-status-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            + 'stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            + paths + '</svg>';
    }

    function makeEventBlock(e, span, day, cfg, di) {
        const isConflict = day.holiday;
        const div = document.createElement('div');
        div.className = 'event-block span-' + span;
        div.setAttribute('tabindex', '0');
        div.__eventData = e;
        div.dataset.name = e.name || '';
        div.dataset.venue = e.venue || '';
        /* notif-click-highlight (the documented bridge patch): the
           cell carries its class session id so the notification
           deep-link highlight can find the cancelled class cell. */
        div.dataset.sid = String(e.sessionId ?? e.id);
        /* mytimetable-jump-highlight (2026-11-04): a replacement block
           also carries its request id, so `Go to My Timetable` can flash
           THIS block and never the class's natural one. Absent on every
           other block — the selector is unambiguous by construction. */
        if (e.requestId !== undefined && e.requestId !== null && e.requestId !== '') {
            div.dataset.rid = String(e.requestId);
        }
        /* cancelled-slot-reuse S14: the payload's stable identity rides the
           block — never invented from coordinates. */
        if (e.eventKey !== undefined && e.eventKey !== null && e.eventKey !== '') {
            div.dataset.eventKey = String(e.eventKey);
        }
        /* Hover tooltip = subject name (data-name) · page context ·
           lecturer segment · STATUS — the 2026-10-25 hover voice
           (the user's call): the lecturer segment is page-supplied
           via tipLecturerFn (my-timetable drops it, cohort reads YOU
           for own classes since 2026-11-05; default = the staff name) and the status
           word always renders CAPS (NORMAL / PENDING / REPLACEMENT). */
        const tipCtx = (typeof cfg.tooltipExtra === 'function')
            ? cfg.tooltipExtra(e)
            : '';
        const tipLect = (typeof cfg.tipLecturerFn === 'function')
            ? cfg.tipLecturerFn(e)
            : (e.lecturer || '—');
        const tipText = [tipCtx, tipLect, eventStatusLabel(e, isConflict).toUpperCase()]
            .filter(Boolean).join(' · ');
        if (cfg.responsive) {
            /* my-timetable-responsive-prompt T12(a) — the TIP MIGRATION, and it
               lives inside this flag only (spec R5). The two pieces the CSS
               `::after` (theme.css: `content: attr(data-name) ' · ' attr(data-tip2)`)
               used to join become ONE `data-tip` for the shared engine, composed by
               the exact expression design §6 pins — so the rendered sentence is
               byte-identical to today's, and the four callers without the flag keep
               the old attribute pair (T0a baseline: tests/fixtures/t0a-baseline.json).
               `data-name` stays set: it is the name half of the expression above. */
            div.dataset.tip = [div.dataset.name, tipText].filter(Boolean).join(' · ');
        } else {
            div.dataset.tip2 = tipText;
        }
        if (typeof cfg.statusClassFn === 'function') {
            cfg.statusClassFn(div, e, isConflict);
        } else if (e.status === 'cancelled') {
            // cancelled-blocks-stay-visible (law ①): the cancelled
            // block renders the EXISTING conflict family — one loud
            // red on personal timetables, no new CSS class. The arm
            // sits FIRST so a cancelled-on-holiday-day block stays
            // red even when isConflict is true.
            div.classList.add('event-conflict');
        } else if (isConflict || e.status === 'conflict') {
            // §10.0: conflict & PH are the same "this class won't run as
            // scheduled" story — one loud red on personal timetables
            // (everything here is the viewer's own; owner-gating with a
            // quiet tint only applies to the cohort/venue browse pages)
            div.classList.add('event-conflict');
        } else if (e.status === 'normal') {
            div.classList.add('event-normal');
        } else if (e.status === 'replacement') {
            div.classList.add('event-replacement');
        } else if (e.status === 'pending') {
            div.classList.add('event-pending');
        }

        const startTime = to12h(hours[e.start]);
        const endTime = to12h(hours[e.end + 1] || add30min(hours[e.end]));

        let extraHtml = '';
        if (typeof cfg.replacementNoteFn === 'function') {
            extraHtml = cfg.replacementNoteFn(e) || '';
        } else if (e.status === 'replacement' && e.remarks) {
            extraHtml = '<span class="ev-note">(Replaced for ' + e.remarks + ')</span>';
        }

        /* T12(b) — the ONE desktop change (spec R4): at >=1280 a cancelled or
           conflicted block prints `✕ Cancelled` / `⚠ Conflict` UNDER the time, in the
           block's own colour. `.ev-note` supplies the shipped note tier (11px/600,
           centred, margin-top 1px) and inherits the block's `color`, so this is the
           block's own colour with no new skin; `.tt-block-status` is the stable hook
           (no rules of its own). A public holiday prints NO word inside a block, and
           nothing else about the block moves — code, venue, time, note keep their
           order. Flag-only, so the other four callers stay byte-identical (T0a). */
        let statusWord = '';
        if (cfg.responsive && !isConflict && (e.status === 'cancelled' || e.status === 'conflict')) {
            statusWord = '<span class="ev-note tt-block-status">'
                + statusIconHtml(e.status)
                + (e.status === 'cancelled' ? 'Cancelled' : 'Conflict') + '</span>';
        }

        /* …and the word REPLACES the legacy `(Cancelled)` note it duplicates, the
           same rule the phone card follows (R3: one status word, not two). Found
           live: the desktop block printed "✕ Cancelled (Cancelled)" — the word
           added by T12 beside the note the page's own replacementNoteFn still
           supplied. The user's word, 2026-11-15: "remove the old ev note".
           Flag-only, and only when a word is actually printed, so the other four
           callers keep the note and stay byte-identical (T0a). */
        if (statusWord && /\(Cancelled\)/.test(extraHtml)) extraHtml = '';

        div.innerHTML =
            '<span class="ev-code">' + e.code + '(' + e.type + ')</span>' +
            '<span class="ev-venue">' + e.venue + '</span>' +
            '<span class="ev-time">' + startTime + ' ~ ' + endTime + '</span>' +
            statusWord +
            extraHtml;

        div.addEventListener('click', function() { cfg.onEventClick(e, di); });
        return div;
    }

    cfg.days.forEach((day, di) => {
        const dayEvents = cfg.events.filter(e => e.di === di);

        /* Legacy single-row custom-cell path (replacement-arrangement and
           any cellRender caller that has NOT opted into stacked history
           lanes via cfg.laneLayout): byte-identical to the pre-lane
           behaviour — one physical row per day, last-write-wins slotMap,
           5-argument cellRender. */
        if (cfg.cellRender && !cfg.laneLayout) {
            /* cancelled-blocks-stay-visible (user law 2026-10-27): cancelled
               events REJOIN the grid — the red-cancelled state rides the
               status (the class family maps it to event-conflict). The two
               filters below previously DISCARDED cancelled events, which
               made them invisible on every surface. The arr/venue share the
               cellRender path with payloads that never carry cancelled
               events — inert there. */
            const tr = document.createElement('tr');
            tr.dataset.dayIndex = di;

            const dayTd = document.createElement('td');
            let dayColClass = 'time-col';
            if (day.today) dayColClass += ' today';
            if (day.holiday || day.sunday) dayColClass += ' offday';
            dayTd.className = dayColClass;
            dayTd.innerHTML = HtmlBuilder.dayHeader(day);
            /* "Bookings open" chip: the semester's earliest bookable day, when the
               page opts in (cfg.bookableBadge) — data-driven via generateWeekData's
               firstBookable flag, so it tracks the anchor and never hardcodes */
            if (cfg.bookableBadge && day.firstBookable) {
                dayTd.insertAdjacentHTML('beforeend', '<span class="bookable-badge">Bookings open</span>');
            }
            tr.appendChild(dayTd);

            const slotMap = {};
            hours.forEach((_, hi) => { slotMap[hi] = null; });
            dayEvents.forEach(e => {
                for (let hi = e.start; hi <= e.end; hi++) {
                    if (hi === e.start) {
                        slotMap[hi] = { event: e, span: e.end - e.start + 1 };
                    } else {
                        slotMap[hi] = { event: null, span: 0, occupied: true, status: e.status };
                    }
                }
            });

            hours.forEach((h, hi) => {
                const td = document.createElement('td');
                let cellClass = 'hour-cell';
                if (day.today) cellClass += ' today-cell';
                if (day.sunday || day.holiday) cellClass += ' offday-slot';
                td.className = cellClass;
                td.dataset.day = di;
                td.dataset.hour = hi;
                cfg.cellRender(td, di, hi, day, slotMap[hi]);
                tr.appendChild(td);
            });
            body.appendChild(tr);
            return;
        }

        /* Stacked path (cancelled-slot-reuse D6/S17/S18): the shared PURE
           lane planner decides the rows — cancelled history stacks ABOVE the
           active records it overlaps, independent blocks reuse lanes, and a
           normal no-overlap day keeps its single physical row (existing
           height). Layout makes no availability decision. */
        const lay = layoutTimetableDay(dayEvents);
        const layDay = lay.days[String(di)] || { placed: [], lanes: 1 };
        const laneCount = Math.max(1, layDay.lanes);

        /* lane-agnostic facts for the custom-cell context (S20): covered =
           ANY event occupies the cell (history included); blocked = a
           NON-cancelled one does (real room occupancy — pending/occupied
           still block; cancelled history never does). */
        const covered = {};
        const blocked = {};
        dayEvents.forEach(e => {
            const hist = e.status === 'cancelled';
            for (let hi = e.start; hi <= e.end; hi++) {
                covered[hi] = true;
                if (!hist) blocked[hi] = true;
            }
        });

        /* Venue-only base availability row (D6/S20): the PAGE decides which
           days need it (cfg.baseRowDays = array of day indexes) — one extra
           physical row below the history lanes exposing the truly free cells
           that history would otherwise cover. It is not an event and never
           enters a summary. */
        const hasBase = !!(cfg.cellRender && Array.isArray(cfg.baseRowDays)
            && cfg.baseRowDays.indexOf(di) >= 0);
        const physicalRows = laneCount + (hasBase ? 1 : 0);

        for (let row = 0; row < physicalRows; row++) {
            const isBase = hasBase && row === physicalRows - 1;
            const lane = isBase ? laneCount : row;

            const tr = document.createElement('tr');
            tr.dataset.dayIndex = di;
            tr.dataset.laneRow = isBase ? 'base' : String(lane);

            const dayTd = document.createElement('td');
            let dayColClass = 'time-col';
            if (day.today) dayColClass += ' today';
            if (day.holiday || day.sunday) dayColClass += ' offday';
            if (row === 0) {
                /* the day header spans EVERY physical row of the day —
                   lane rows plus a base row when one exists */
                dayTd.className = dayColClass;
                dayTd.rowSpan = physicalRows;
            } else {
                /* continuation header: display:none on desktop (the rowspan
                   owns the axis), restored per-card on mobile by theme.css
                   so every card still names its day */
                dayTd.className = dayColClass + ' time-col-cont';
            }
            dayTd.innerHTML = HtmlBuilder.dayHeader(day);
            /* "Bookings open" chip: the semester's earliest bookable day, when the
               page opts in (cfg.bookableBadge) — data-driven via generateWeekData's
               firstBookable flag, so it tracks the anchor and never hardcodes */
            if (cfg.bookableBadge && day.firstBookable && row === 0) {
                dayTd.insertAdjacentHTML('beforeend', '<span class="bookable-badge">Bookings open</span>');
            }
            tr.appendChild(dayTd);

            /* this lane's own records (the base row carries none) */
            const lanePlaced = isBase ? [] : layDay.placed.filter(p => p.lane === lane);
            const laneStart = {};
            const laneCover = {};
            lanePlaced.forEach(p => {
                for (let hi = p.event.start; hi <= p.event.end; hi++) {
                    laneCover[hi] = true;
                    if (hi === p.event.start) laneStart[hi] = p;
                }
            });

            for (let hi = 0; hi < hours.length; hi++) {
                const td = document.createElement('td');
                let cellClass = 'hour-cell';
                if (day.today) cellClass += ' today-cell';
                if (day.sunday || day.holiday) cellClass += ' offday-slot';
                td.className = cellClass;
                td.dataset.day = di;
                td.dataset.hour = hi;

                if (cfg.cellRender) {
                    /* stacked custom-cell contract (S18/S20): the SAME info
                       shape (this lane's own record) plus a BACKWARD-
                       COMPATIBLE sixth argument — lane geometry, real
                       occupancy facts, and the designated selectable
                       instance of a free cell. */
                    let info = null;
                    if (laneStart[hi]) {
                        info = { event: laneStart[hi].event, span: laneStart[hi].span };
                    } else if (laneCover[hi]) {
                        const cp = lanePlaced.find(p => p.event.start < hi && p.event.end >= hi);
                        info = { event: null, span: 0, occupied: true, status: cp ? cp.event.status : undefined };
                    }
                    const ctx = {
                        lane: lane,
                        laneCount: laneCount,
                        base: isBase,
                        covered: !!covered[hi],
                        blocked: !!blocked[hi],
                        /* the ONE selectable instance of a free cell: lane 0
                           when history does not cover it, the base row when
                           it does — never one green card per lane. */
                        designated: isBase ? !!covered[hi] : (lane === 0 && !covered[hi]),
                    };
                    cfg.cellRender(td, di, hi, day, info, ctx);
                    tr.appendChild(td);
                    continue;
                }

                const p = laneStart[hi];
                if (p) {
                    const e = p.event;
                    const div = makeEventBlock(e, p.span, day, cfg, di);
                    td.appendChild(div);

                    if (p.span > 1) {
                        td.colSpan = p.span;
                    }
                } else if (laneCover[hi]) {
                    td.style.display = 'none';
                } else {
                    const div = document.createElement('div');
                    div.className = 'cell-empty';
                    td.appendChild(div);
                }

                tr.appendChild(td);
            }

            body.appendChild(tr);
        }
    });
}

// ───── Shared summary calculator ─────

/**
 * Compute summary stats and update DOM elements.
 * @param {Array} events - Array of event objects for the current week
 * @param {Array} days - Array of day objects from weekData
 */
/* ───── My-teaching stats (shared: cohort + venue summary bars) ───── */

/**
 * Count + hours of the classes taught by the logged-in lecturer within a
 * page's (already week/filter-scoped) events array. Dedupes by the payload's
 * stable eventKey ONLY (cancelled-slot-reuse D7/S22) so merged-cohort
 * duplicate rows count once while same-start DISTINCT identities (a landing
 * request and its cancelled original) can no longer overwrite each other —
 * the old day:start last-write-wins map is gone. Legacy rows without a key
 * stay separate (never merged by coordinates). Offday events are skipped by
 * callers whose grid renders them as PH cells.
 */
function myTeachingStats(events) {
    const seen = {};
    const mine = [];
    (events || []).forEach(function (e) {
        if (!e || e.lecturer !== MockData.currentUser.name) return;
        /* cancelled-blocks-stay-visible (the locked summary law): a cancelled
           class is not taught — it leaves the My Classes/My Hours count (the
           honest-numbers law, and the frozen #sumTotal=6 receipt keeps its
           meaning) but APPEARS in the conflicts tally via computeSummary. */
        if (e.status === 'cancelled') return;
        if (e.eventKey !== undefined && e.eventKey !== null && e.eventKey !== '') {
            if (seen[e.eventKey]) return;
            seen[e.eventKey] = true;
        }
        mine.push(e);
    });
    const hours = mine.reduce(function (s, e) { return s + (e.end - e.start + 1) * 0.5; }, 0);
    /* confirmed+pending split (summary-cards-confirmed-pending, 2026-11-02):
       a head whose FINAL status is `pending` is a class still awaiting
       approval. It stays inside classes/hours (those keys keep their exact
       values — the plain callers are byte-identical), and is additionally
       reportable as the pending part. Additive keys only. */
    const pendingMine = mine.filter(function (e) { return e.status === 'pending'; });
    const pendingHours = pendingMine.reduce(function (s, e) { return s + (e.end - e.start + 1) * 0.5; }, 0);
    return {
        classes: mine.length,
        hours: hours,
        pendingClasses: pendingMine.length,
        pendingHours: pendingHours,
    };
}

function computeSummary(events, days, opts) {
    /* confirmed+pending split (summary-cards-confirmed-pending, 2026-11-02):
       the `{ splitPending: true }` OPT-IN renders sumTotal / sumHours /
       sumMyClasses / sumMyHours as `X` + a tertiary `+Y` tail (Y = the pending
       share). DEFAULT FROZEN: a caller that passes no opts keeps the exact
       previous plain numbers — my-timetable + student-my-timetable never opt in. */
    const splitPending = !!(opts && opts.splitPending);
    /* cancelled-blocks-stay-visible (the locked summary law): My Classes /
       My Hours exclude cancelled blocks (myTeachingStats gates them) while
       the conflicts tally INCLUDES them — the red block is a conflict
       requiring replacement. The three timetable blades' `filter(e =>
       e.status !== 'cancelled')` wrappers were REMOVED in this lane (they
       starved the tally); the full arrays now reach here. */
    /* cancelled-slot-reuse D7/S22: every tally below reads the eventKey
       dedupe — never day/start or lane, and never the physical lane rows
       (a base availability row is not an event and contributes zero). */
    const evs = dedupeEventsByKey(events);
    let total = evs.length - evs.filter(e => e.status === 'cancelled').length;
    let replacement = 0, pending = 0, conflict = 0, hrs = 0, pendingHrs = 0;

    evs.forEach(e => {
        if (e.status === 'replacement') replacement++;
        if (e.status === 'pending') pending++;
        if (e.status === 'conflict' || e.status === 'cancelled' || (days[e.di] && days[e.di].holiday)) conflict++;
        if (e.status !== 'cancelled') {
            const span = (e.end - e.start + 1) * 0.5;
            hrs += span;
            /* the pending share of the SAME tally (never a re-derivation) */
            if (e.status === 'pending') pendingHrs += span;
        }
    });

    /* Null-guarded: pages whose summary bar drops a card (e.g. cohort's
       Replacements/Pending → My Teaching cards) keep the shared builder
       crash-free; existing ids keep their exact previous behavior. */
    const set = function (id, v) {
        const el = document.getElementById(id);
        if (el) el.textContent = v;
    };
    /* `X+Y` writer: the SAME value, split into its confirmed part and its
       pending part. The plain branch reproduces the old textContent byte for
       byte (the opt-out callers prove it). */
    const fmt = function (v) { return v % 1 === 0 ? String(v) : v.toFixed(1); };
    const setSplit = function (id, full, pendingPart) {
        const el = document.getElementById(id);
        if (!el) return;
        if (!splitPending) {
            el.textContent = fmt(full);
            return;
        }
        el.innerHTML = fmt(full - pendingPart)
            + '<span class="sum-pending">+' + fmt(pendingPart) + '</span>';
    };

    setSplit('sumTotal', total, pending);
    setSplit('sumHours', hrs, pendingHrs);
    set('sumReplacement', replacement);
    set('sumPending', pending);
    set('sumConflict', conflict);

    const my = myTeachingStats(events);
    setSplit('sumMyClasses', my.classes, my.pendingClasses);
    setSplit('sumMyHours', my.hours, my.pendingHours);
}

// ───── Shared modal open helper ─────

/**
 * Open the class detail modal with common fields.
 * @param {object} cfg
 * @param {object} cfg.event - The event object
 * @param {number} cfg.dayIndex - Day index
 * @param {Array} cfg.days - Day data array
 * @param {Array} cfg.extraFields - Additional fields to append before Status
 * @param {string} [cfg.modalId='classModal'] - Modal element ID
 * @param {string} [cfg.title] - Custom title (default: event.code)
 * @param {Array} [cfg.groups] - Grouped layout: [{ heading, rows: [{ label, value, strong? }] }].
 *   When present, renders one category per group; 2+ groups become a tab bar
 *   (same tabbed taxonomy as my-request-history's Request Details). Additive —
 *   omit for the flat event layout.
 */
/**
 * Render grouped rows inside a detail modal: 2+ groups → a modal tab bar with
 * one tab per category (like my-request-history's Request Details); a single
 * group → plain stacked body. Shared by openClassModal and page modals.
 * @param {object} opts - { modalId, title, subtitle?, timeline? }
 * @param {Array} groups - [{ heading, rows: [{ label, value, strong? }] }]
 * @returns {number} category count rendered (1 = plain body, 2+ = tabs)
 */
function renderModalGroups(opts, groups) {
    const tabs = (groups || []).filter(g => g.rows && g.rows.length).map(function (g) {
        return {
            key: String(g.heading).toLowerCase().replace(/[^a-z0-9]+/g, '-'),
            label: g.heading,
            html: DetailModal.section(g.heading, g.rows.map(function (r) {
                return DetailModal.row(r.label, r.value, { strong: r.strong });
            }).join(''))
        };
    });
    if (tabs.length > 1) {
        DetailModal.render(Object.assign({}, opts, { tabs: tabs }));
    } else {
        DetailModal.render(Object.assign({}, opts, { body: tabs.length ? tabs[0].html : '' }));
    }
    return tabs.length;
}

function openClassModal(cfg) {
    // ── Grouped layout (additive, §10.0 rules 5/6 — grouped categories as tabs) ──
    if (cfg.groups) {
        renderModalGroups({
            modalId: cfg.modalId || 'classModal',
            title: cfg.title || 'Class Details',
            subtitle: cfg.subtitle || ''
        }, cfg.groups);
        return;
    }

    const event = cfg.event;
    const di = cfg.dayIndex;
    const days = cfg.days;

    const isConflict = days[di] && days[di].holiday;
    // §10.0 rule 2 — one source: badge class AND badge text from the same `st`.
    // A class falling on a public holiday is "Public Holiday" (red), NOT
    // "Conflict" — holiday ≠ clash, two different §10.0 meanings. Grid block
    // already styles it event-public-holiday; the badge now matches.
    const st = isConflict ? 'public-holiday' : (event.status || 'normal');
    /* my-timetable-responsive-prompt T13 — the LONG word, PAGE-SCOPED (spec R4).
       The bare `Cancelled` can be read as the REQUEST being cancelled, so the two
       timetable pages pass `longCancelledLabel: true` and their Status row reads
       `Class Cancelled`. The flag is per-call on purpose: this modal is shared with
       Cohort, Venue and Student Replacement History, whose Status badge is not this
       lane's to change — every other caller keeps `Cancelled` byte-identically. */
    const displayStatus = st === 'public-holiday'
        ? 'Public Holiday'
        : (st === 'cancelled' && cfg.longCancelledLabel === true)
            ? 'Class Cancelled'
            : st.charAt(0).toUpperCase() + st.slice(1);

    const startStr = to12h(hours[event.start]);
    const endStr = to12h(hours[event.end + 1] || add30min(hours[event.end]));

    /* venue-status-parity T7 (bug #10): the cancelled arm was missing, so a
       cancelled class's popup read "Scheduled class with no issues" on every
       page. Same vocabulary as the badge / the block note. */
    const statusDesc = isConflict ? 'Class falls on a public holiday — no class runs' : event.status === 'pending' ? 'Replacement request awaiting approval' : event.status === 'conflict' ? 'Scheduling conflict — needs attention' : event.status === 'cancelled' ? 'Class cancelled — a replacement is needed' : event.status === 'replacement' ? 'Approved replacement session — this class runs at this slot' : 'Scheduled class with no issues';

    /* 2026-11-10 (user, modal consistency #3): Class Information now leads with the
       record's own ID — code, name and class type went from three rows to ONE
       (the separate "Subject Name" / "Class Type" rows are gone).
       Same day, ruling A: the subject is not a ROW at all any more — the subtitle
       above the tabs already prints it, and printing it twice in one pop-up was
       the same repeat the Room Name row was deleted for. `subjectText` is the one
       wording, used by the subtitle. */
    const subjectText = (event.code || '')
        + (event.name ? ' \u2014 ' + event.name : '')
        + (event.type ? ' (' + UiLabels.classType(event.type, 'long') + ')' : '');

    const idFromEvent = event.sessionId != null ? event.sessionId : event.id;
    const rows = [
        { label: 'Session ID', value: idFromEvent ? '#' + idFromEvent : '\u2014' },
        { label: 'Lecturer', value: event.lecturer },
        /* 2026-11-10 (user, modal consistency #4): the Schedule reads
           Venue (code — name) · Week · Day & Date · Time & Duration. */
        { label: 'Venue', value: ModalFacts.venue(event.venue, event.venueName, cfg.venueSeats) },
        { label: 'Week', value: cfg.week ? 'Week ' + cfg.week : '\u2014' },
        { label: 'Day & Date', value: ModalFacts.dayDate(dayNames[di] || days[di].abbr, days[di].date) },
        { label: 'Time & Duration', value: ModalFacts.timeDuration(startStr, endStr, (event.end - event.start + 1) / 2), strong: true },
        { label: 'Status', value: '<span class="badge badge-' + st + '">' + displayStatus + '</span>' },
        { label: 'Status Description', value: statusDesc },
        { label: 'Remarks', value: event.remarks || '\u2014' },
    ];
    /* the quotable reference is now the FIRST row ("Session ID", #3 above): `id` is
       the grid's row key on the venue page, so `sessionId` still wins when present
       (venue-status-parity T6, bug #5 — a time_slot id must never be quoted). */

    /* venue-note-privacy (2026-11-02, user ruling A+A): the REQUEST facts —
       when it was submitted and by whom — belong to the person who owns the
       record: the block's own lecturer (`mine` / the lecturer name) or the
       requester. Anyone else opening the amber block on a room screen sees the
       class facts and the Pending status (already public via the block colour)
       but not these two rows. Same gate the "View Full Request" footer link
       has always used; the requester comparison keeps parity with it. */
    const isRecordOwner = event.mine === true
        || event.lecturer === (typeof MockData !== 'undefined' && MockData.currentUser ? MockData.currentUser.name : undefined)
        || (event.requestedBy && typeof MockData !== 'undefined' && MockData.currentUser && event.requestedBy === MockData.currentUser.name);

    if (event.status === 'pending' && isRecordOwner) {
        rows.splice(rows.length - 1, 0,
            { label: 'Requested At', value: event.requestedAt || '\u2014' },
            { label: 'Requested By', value: event.requestedBy || '\u2014' }
        );
    }

    if (cfg.extraFields) {
        const statusIdx = rows.findIndex(f => f.label === 'Status');
        cfg.extraFields.forEach((f, i) => { rows.splice(statusIdx + i, 0, f); });
    }

    // ── Confirmed replacement: show the replaced original conflicted class ──
    // `replacedFor` (dd-Mon-yyyy, seeded) or a date-shaped remarks string at
    // cohort level. The original kept the weekly slot, so its time/venue are
    // this block's own schedule.
    if (String(event.status) === 'replacement') {
        const rf = event.replacedFor ||
            ((event.remarks || '').match(/^\d{2}-[A-Z][a-z]{2}-\d{4}$/) || [])[0];
        if (rf) {
            const m = rf.match(/^(\d{2})-([A-Za-z]{3})-(\d{4})$/);
            const MONTHS = { jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5,
                jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11 };
            const dayName = m && MONTHS[m[2].toLowerCase()] !== undefined
                ? dayNames[(new Date(+m[3], MONTHS[m[2].toLowerCase()], +m[1]).getDay() + 6) % 7] || ''
                : '';
            const stIdx = rows.findIndex(f => f.label === 'Status');
            /* USER 2026-10-19: the Original block splits day|date, start|end
               time, and carries the venue NAME row (the code row stays) —
               same shape the history modal splits its times. */
            const ovn = event.originalVenueName ||
                (((typeof MockData !== 'undefined' && MockData.venues) || [])
                    .find(function (v) { return v.code === event.venue; }) || {}).type || '\u2014';
            /* USER 2026-10-19 (accuracy): the ORIGINAL rows read the original
               session's own times (backed by originalStartTime/-EndTime); the
               landing block's clock is the fallback for mock-era rows only. */
            rows.splice(stIdx, 0,
                { label: 'Original Day', value: dayName || '\u2014' },
                { label: 'Original Date', value: rf, strong: true },
                { label: 'Original Start Time', value: event.originalStartTime ? to12h(event.originalStartTime) : startStr, strong: true },
                { label: 'Original End Time', value: event.originalEndTime ? to12h(event.originalEndTime) : endStr },
                { label: 'Original Venue', value: event.originalVenue || event.venue || '\u2014' },
                { label: 'Original Venue Name', value: ovn });
            if (event.replacedReason) {
                rows.splice(stIdx + 6, 0, { label: 'Original Conflict', value: event.replacedReason });
            }
            // the raw date no longer belongs under Remarks — it lives in Original Date
            for (let i = rows.length - 1; i >= 0; i--) {
                if (rows[i].label === 'Remarks' && rows[i].value === rf) { rows.splice(i, 1); }
            }
        }
    }

    // ── Auto-grouped layout (§10.0 rules 5/6 — detail grouped, not a wall) ──
    // Same three-category taxonomy the request-history modal uses. Rows keep
    // their order inside each bucket; unknown labels default to Class Information.
    const STATUS_LABELS = ['Status', 'Status Description', 'Requested At', 'Requested By', 'Rejection Reason', 'Remarks'];
    const SCHEDULE_LABELS = ['Day', 'Date', 'Start Time', 'End Time', 'Duration', 'Venue',
        'Day & Date', 'Time & Duration', 'Week'];
    function bucketOf(label) {
        if (label.indexOf('Original ') === 0) return 'Original Slot';
        if (STATUS_LABELS.indexOf(label) >= 0) return 'Status';
        if (SCHEDULE_LABELS.indexOf(label) >= 0) return 'Schedule';
        return 'Class Information';
    }
    const buckets = rows.reduce(function (acc, r) {
        const k = bucketOf(r.label);
        (acc[k] = acc[k] || []).push(r);
        return acc;
    }, {});
    const groups = ['Class Information', 'Schedule', 'Original Slot', 'Status']
        .filter(k => buckets[k] && buckets[k].length)
        .map(k => ({ heading: k, rows: buckets[k] }));

    renderModalGroups({
        modalId: cfg.modalId || 'classModal',
        title: cfg.title || 'Class Details',
        /* 2026-11-10 (user, modal consistency #1): the subtitle carries the class
           TYPE too — `AMSE2003 — Software Testing and Quality Assurance (Lecture)`.
           And since ruling A it is the ONE place the subject is printed: the row
           that repeated it was deleted. */
        subtitle: subjectText,
        /* the timeline rail was removed 2026-11-10 (user) — no config here */
    }, groups);

    // Footer cleanup runs on EVERY open: the footer is static markup, so an
    // anchor appended for a previous pending class would otherwise linger on
    // a normal class's modal (reported 2026-10-08 — "why View Full Request?").
    var _mOverlay = document.getElementById(cfg.modalId || 'classModal');
    var _mFooter = _mOverlay ? _mOverlay.querySelector('.modal-footer') : null;
    if (_mFooter) {
        _mFooter.querySelectorAll('a.btn-action').forEach(function(b) { b.remove(); });
    }

    // Add "View Full Request" button to footer right side for OWN pending
    // requests only: requests[] ids resolve on my-request-history, so another
    // lecturer's pending (e.g. cohort page) must not link into this user's
    // request history (reported 2026-10-08 — id-collision leak).
    if (event.status === 'pending' && event.requestId && event.requestedBy === MockData.currentUser.name && _mFooter) {
        var viewBtn = document.createElement('a');
        viewBtn.href = '/my-replacements?notif-hl=r' + encodeURIComponent(event.requestId) + '&view=request';
        viewBtn.className = 'btn-action';
        viewBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg> View Full Request';
        viewBtn.style.textDecoration = 'none';
        var rightGroup = _mFooter.querySelector('.modal-footer-right');
        if (rightGroup) {
            rightGroup.appendChild(viewBtn);
        } else {
            _mFooter.appendChild(viewBtn);
        }
    }
}

// ───── Unified Detail Modal (shared inspection UI) ─────
// Renders into a `.modal.modal--detail` shell:
//   title strip  ->  optional GLOBAL timeline (never part of a tab)
//                   ->  tab bar (only when 2+ categories) + active panel
// Footer/actions are handled by the page (unchanged).
//
// Usage (page builder):
//   DetailModal.render({
//     modalId: 'modalOverlay',       // overlay element id
//     title: 'Request Details',
//     subtitle: '#12 · BMIT2201 — Data Structures',
//     status: { text: 'Pending', cls: 'status-pending' },  // optional
//     (no timeline option any more — the rail was removed 2026-11-10)
//       { label: 'Submitted', time: '12 Aug, 3:30 AM', state: 'completed' },
//       { label: 'Viewed',    time: '12 Aug, 7:00 AM', state: 'completed' },
//       { label: 'Reviewed',  time: 'Pending',          state: 'active'  }
//     ],
//     tabs: [                        // categories; tab bar auto-hidden if length <= 1
//       { key: 'info', label: 'Request Information', html: '...' },
//       ...
//     ]
//   });

/* BMSE3153-class-info (user law 2026-10-27): a multi-cohort class's
   "Total Students" row renders the per-cohort breakdown — `A + B = total`
   from the payload's POSITIONAL `studentCounts` (cohorts.student_count,
   real data — never hardcoded). Single-cohort keeps the plain count;
   no data reads '—' (honest zeros stay '0'). Promoted from the third
   copy (my-timetable :164 / cohort-timetable :571 / venue-timetable
   slot modal) — every page's class-detail modal calls this one law. */
window.totalStudentsLabel = function (event) {
    const total = event.studentCount;
    const counts = event.studentCounts;
    if (event.cohorts && event.cohorts.length > 1 && Array.isArray(counts)
        && counts.length === event.cohorts.length) {
        return counts.join(' + ') + ' = '
            + (total !== undefined && total !== null
                ? String(total)
                : String(counts.reduce((a, b) => a + b, 0)));
    }
    return total !== undefined && total !== null ? String(total) : '\u2014';
};

/* BMSE3153-class-info (user law 2026-10-27): the request-row variant —
   multi-cohort rows render `A + B = total` from the payload's POSITIONAL
   `cohortCounts`; single-cohort keeps the plain count (no "20 = 20").
   Promoted from the 3rd copy (my-request-history / request-approval
   detail modal / replacement-home quick view) — one shared law. */
window.cohortBreakdown = function (r) {
    if (!r.cohortCounts || !r.cohorts || r.cohorts.length < 2
        || r.cohortCounts.length !== r.cohorts.length) return String(r.totalStudents);
    return r.cohortCounts.join(' + ') + ' = ' + r.totalStudents;
};

const DetailModal = {
    _overlay: null,
    _body: null,

    render(cfg) {
        if (!this._overlay) {
            this._overlay = document.getElementById(cfg.modalId || 'modalOverlay');
            this._body = this._overlay.querySelector('.modal-body');
        } else if (this._overlay.id !== (cfg.modalId || 'modalOverlay')) {
            this._overlay = document.getElementById(cfg.modalId || 'modalOverlay');
            this._body = this._overlay.querySelector('.modal-body');
        }
        if (!this._overlay || !this._body) return;

        const modalEl = this._overlay.querySelector('.modal');
        if (modalEl) modalEl.classList.add('modal--detail');

        const titleEl = this._overlay.querySelector('.modal-title');
        if (titleEl) {
            titleEl.innerHTML = escHtml(cfg.title || '') +
                (cfg.subtitle ? '<span class="modal-subtitle">' + escHtml(cfg.subtitle) + '</span>' : '');
        }
        // Header status badge intentionally removed — status is shown as a
        // badge row inside the body (see DetailModal.row with a .badge value).

        let html = '';
        /* 2026-11-10 (user, modal consistency #1): the timeline rail is GONE from
           every details modal — no `.modal-timeline`, no note, no dots. */

        /* replacement-merge-one-table (M7/T9, 2026-11-04): ONE optional fixed
           facts strip above the tabs. Additive — every existing caller omits
           it and renders exactly what it rendered before. */
        if (cfg.factsHtml) html += '<div class="detail-facts">' + cfg.factsHtml + '</div>';

        const tabs = cfg.tabs || [];
        if (tabs.length > 1) {
            html += '<div class="modal-tabs">' + tabs.map((t, i) =>
                '<button type="button" class="modal-tab' + (i === 0 ? ' active' : '') + '" data-tab="' + t.key + '">' + escHtml(t.label) + '</button>'
            ).join('') + '</div>';
        }

        html += tabs.map((t, i) =>
            '<div class="modal-tab-panel' + (i === 0 ? ' active' : '') + '" data-panel="' + t.key + '">' + (t.html || '') + '</div>'
        ).join('') + (tabs.length === 0 && cfg.body ? cfg.body : '');

        this._body.innerHTML = html;

        if (this._body.querySelector('.modal-tab')) {
            this._body.querySelectorAll('.modal-tab').forEach(btn => {
                btn.addEventListener('click', () => this.selectTab(btn.dataset.tab));
            });
        }

        this.open();
    },

    selectTab(key) {
        if (!this._body) return;
        this._body.querySelectorAll('.modal-tab').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === key);
        });
        this._body.querySelectorAll('.modal-tab-panel').forEach(panel => {
            panel.classList.toggle('active', panel.dataset.panel === key);
        });
    },

    /* 2026-11-10 (user, modal consistency #1): DetailModal.timeline() was REMOVED
       with the rail it drew (`.modal-timeline` + its note + the dots). */

    // Render a definition section (caption + rows) inside a tab.
    section(title, rowsHtml) {
        return '<div class="detail-group"><div class="detail-group-title">' + escHtml(title) + '</div>' + (rowsHtml || '') + '</div>';
    },

    // Render a single definition row (label + value with optional variant).
    row(label, value, opts) {
        opts = opts || {};
        if (value === null || value === '' || value === undefined) return '';
        const cls = ['detail-value'];
        if (opts.strong) cls.push('detail-value--strong');
        if (opts.muted) cls.push('detail-value--muted');
        return '<div class="detail-row"><span class="detail-label">' + escHtml(label) + '</span><span class="' + cls.join(' ') + '">' + value + '</span></div>';
    },

    blank() {
        if (this._body) this._body.innerHTML = '';
    },

    open() {
        if (this._overlay) {
            this._overlay.classList.add('show');
            this._overlay.style.display = 'flex';
        }
    },

    close() {
        if (this._overlay) {
            this._overlay.classList.remove('show');
            this._overlay.style.display = 'none';
        }
    }
};

function escHtml(s) {
    return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// ───── Shared timetable keyboard + copy + swipe helpers ─────

/**
 * Initialize arrow-key week navigation and Enter-to-open-modal on timetable pages.
 * @param {object} cfg
 * @param {function} cfg.prevWeek
 * @param {function} cfg.nextWeek
 * @param {function} cfg.openModal - Callback (eventData)
 * @param {string} [cfg.modalId='classModal']
 */
function initTimetableKeyboardHandlers(cfg) {
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'SELECT' || document.getElementById(cfg.modalId || 'classModal').style.display === 'flex') return;
        if (e.key === 'ArrowLeft') { cfg.prevWeek(); }
        if (e.key === 'ArrowRight') { cfg.nextWeek(); }
        /* cancelled-slot-reuse S19: click, Enter AND Space open the SAME
           event — one activation seam for every focusable block target. */
        if ((e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar')
            && e.target.classList && e.target.classList.contains('event-block')) {
            e.preventDefault();
            const eventData = e.target.__eventData;
            if (eventData) cfg.openModal(eventData);
        }
    });
}

/**
 * Initialize click-to-copy on .ev-code elements with toast feedback.
 * @param {string} [toastId='copyToast']
 */
function initEvCodeCopy(toastId) {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('ev-code')) {
            navigator.clipboard.writeText(e.target.textContent).then(function() {
                const toastEl = document.getElementById(toastId || 'copyToast');
                if (toastEl) {
                    toastEl.textContent = 'Copied ' + e.target.textContent;
                    toastEl.classList.add('show');
                    setTimeout(function() { toastEl.classList.remove('show'); }, 1500);
                }
            });
        }
    });
}

/**
 * Initialize mobile swipe gestures on the grid-scroll element for week navigation.
 * @param {function} onPrev - Callback for swipe right
 * @param {function} onNext - Callback for swipe left
 */
function initGridSwipeGestures(onPrev, onNext) {
    if (window.innerWidth <= 768) {
        const gridScroll = document.querySelector('.grid-scroll');
        if (gridScroll) {
            initSwipeGesture({
                element: gridScroll,
                onSwipeLeft: onNext,
                onSwipeRight: onPrev
            });
        }
    }
}

// ───── Navigation ─────

/* The replacement handoff — the occurrence's real identity:
   (class_session_id, week_number), the same D1 pair the request row keys on.
   The arrangement page resolves + ownership-checks it server-side; `from`
   drives the Back button. Promoted from the per-page code/date param schemes
   (replacement-home cards, my-timetable Replace Now, cancel-flow Arrange Now). */
/**
 * The ONE jump from a replacement record to its block on My Timetable
 * (mytimetable-jump-highlight, user 2026-11-04). Both entry points call this —
 * the row's icon button and the details modal's `Go to My Timetable` — so the
 * URL is built once.
 *
 * `repl-hl` carries the REPLACEMENT REQUEST id (not a class, date or week: those
 * are not identities), `repl-w` the 1-based landing week the page should show.
 */
function goToReplacementInTimetable(requestId, week, basePath) {
    if (!requestId) return;
    /* 2026-11-09 (SDD student-class-replacements §7): the student page shares this
       helper. The third argument is the page the jump belongs to; the staff call
       sites pass nothing and keep their exact URL. */
    const base = basePath || '/my-timetable-ui';
    window.location.href = base + '?repl-hl=' + encodeURIComponent(requestId) +
        (week ? '&repl-w=' + encodeURIComponent(week) : '');
}

function goToReplacementFor(sessionId, week, from) {
    if (!sessionId || !week) return;
    window.location.href = '/replacement-arrangement?session=' + encodeURIComponent(sessionId) +
        '&week=' + encodeURIComponent(week) +
        (from ? '&from=' + encodeURIComponent(from) : '');
}

function goToReplacement(code, cohort, opts, from) {
    let url = '/replacement-arrangement';
    const params = [];
    if (code) params.push('code=' + encodeURIComponent(code));
    if (cohort) params.push('cohort=' + encodeURIComponent(cohort));
    if (opts) {
        if (opts.day !== undefined) params.push('day=' + opts.day);
        if (opts.start !== undefined) params.push('start=' + opts.start);
        if (opts.end !== undefined) params.push('end=' + opts.end);
        if (opts.venue) params.push('originalVenue=' + encodeURIComponent(opts.venue));
        if (opts.duration !== undefined && opts.duration !== null && !isNaN(opts.duration)) {
            params.push('duration=' + opts.duration);
        }
    }
    if (from) params.push('from=' + from);
    if (params.length) url += '?' + params.join('&');
    window.location.href = url;
}

// ───── Table sorting ─────

function compareBy(sortState, va, vb) {
    if (va < vb) return sortState.dir === 'asc' ? -1 : 1;
    if (va > vb) return sortState.dir === 'asc' ? 1 : -1;
    return 0;
}

function makeSortableHeader(col, sortState, render) {
    const th = document.createElement('th');
    th.className = col.cls;
    if (col.tip) th.setAttribute('data-tip', col.tip);
    if (col.sortable) {
        th.classList.add('sortable');
        var arrow = '';
        if (sortState.field === col.field) {
            arrow = '<span class="sort-arrow">' + (sortState.dir === 'asc' ? '&#9650;' : '&#9660;') + '</span>';
        }
        th.innerHTML = col.label + arrow;
        th.addEventListener('click', function() {
            if (sortState.field === col.field) {
                sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
            } else {
                sortState.field = col.field;
                sortState.dir = 'asc';
            }
            render();
        });
    } else {
        th.textContent = col.label;
    }
    return th;
}

// ───── Pagination ─────

/**
 * Initialize a Rows Per Page selector.
 * @param {object} cfg
 * @param {string} cfg.selectId - ID of the <select> element
 * @param {string} cfg.storageKey - localStorage key (null = no persistence)
 * @param {number|string} cfg.defaultVal - Default page size ('all' for Infinity)
 * @param {function} cfg.onChange - Callback receiving the new page size (number or Infinity)
 */
function initRpp(cfg) {
    var sel = document.getElementById(cfg.selectId);
    if (!sel) return;

    if (cfg.storageKey) {
        var saved = localStorage.getItem(cfg.storageKey);
        if (saved !== null) {
            sel.value = saved;
        }
    }

    var initial = sel.value;
    var parsed = initial === 'all' ? Infinity : parseInt(initial) || cfg.defaultVal;
    cfg.onChange(parsed);

    sel.addEventListener('change', function () {
        var val = this.value;
        var pageSize = val === 'all' ? Infinity : parseInt(val) || cfg.defaultVal;
        if (cfg.storageKey) {
            localStorage.setItem(cfg.storageKey, val);
        }
        cfg.onChange(pageSize);
    });
}

function paginate(cfg) {
    const totalPages = Math.ceil(cfg.data.length / cfg.pageSize);
    const info = document.getElementById(cfg.infoId);
    if (cfg.data.length === 0) {
        info.textContent = 'Showing 0 of 0';
    } else {
        const from = (cfg.state.currentPage - 1) * cfg.pageSize + 1;
        const to = Math.min(cfg.state.currentPage * cfg.pageSize, cfg.data.length);
        info.textContent = 'Showing ' + from + '-' + to + ' of ' + cfg.data.length;
    }

    const controls = document.getElementById(cfg.controlsId);
    controls.innerHTML = '';

    const prev = document.createElement('button');
    prev.className = 'page-btn';
    prev.textContent = '\u2039';
    prev.disabled = cfg.state.currentPage <= 1;
    prev.addEventListener('click', function() {
        if (cfg.state.currentPage > 1) {
            cfg.state.currentPage--;
            cfg.render();
        }
    });
    controls.appendChild(prev);

    for (var p = 1; p <= totalPages; p++) {
        (function(page) {
            const btn = document.createElement('button');
            btn.className = 'page-btn';
            if (page === cfg.state.currentPage) btn.classList.add('active');
            btn.textContent = String(page);
            btn.addEventListener('click', function() {
                cfg.state.currentPage = page;
                cfg.render();
            });
            controls.appendChild(btn);
        })(p);
    }

    const next = document.createElement('button');
    next.className = 'page-btn';
    next.textContent = '\u203A';
    next.disabled = cfg.state.currentPage >= totalPages;
    next.addEventListener('click', function() {
        if (cfg.state.currentPage < totalPages) {
            cfg.state.currentPage++;
            cfg.render();
        }
    });
    controls.appendChild(next);
}

function updateResultCount(cfg) {
    const count = document.getElementById(cfg.elId);
    count.textContent = 'Showing ' + cfg.data.length + ' of ' + cfg.total + ' ' + cfg.label;
}

class TableController {
    constructor(config) {
        this._columns = config.columns || [];
        this._sortState = config.sortState || { field: '', dir: 'asc' };
        this._render = config.render || function() {};
    }

    sort(field) {
        if (this._sortState.field === field) {
            this._sortState.dir = this._sortState.dir === 'asc' ? 'desc' : 'asc';
        } else {
            this._sortState.field = field;
            this._sortState.dir = 'asc';
        }
        this._render();
    }

    compareBy(va, vb) {
        if (va < vb) return this._sortState.dir === 'asc' ? -1 : 1;
        if (va > vb) return this._sortState.dir === 'asc' ? 1 : -1;
        return 0;
    }

    makeHeader(col) {
        const th = document.createElement('th');
        th.className = col.cls;
        if (col.sortable) {
            th.classList.add('sortable');
            var arrow = '';
            if (this._sortState.field === col.field) {
                arrow = '<span class="sort-arrow">' + (this._sortState.dir === 'asc' ? '&#9650;' : '&#9660;') + '</span>';
            }
            th.innerHTML = col.label + arrow;
            th.addEventListener('click', () => {
                this.sort(col.field);
            });
        } else {
            th.textContent = col.label;
        }
        return th;
    }

    paginate(data, page, pageSize) {
        const totalPages = Math.ceil(data.length / pageSize);
        const from = (page - 1) * pageSize + 1;
        const to = Math.min(page * pageSize, data.length);
        return {
            totalPages,
            from,
            to,
            total: data.length
        };
    }

    updateResultCount(data, total, label) {
        return 'Showing ' + data.length + ' of ' + total + ' ' + label;
    }

    initRpp(cfg) {
        var sel = document.getElementById(cfg.selectId);
        if (!sel) return;

        if (cfg.storageKey) {
            var saved = localStorage.getItem(cfg.storageKey);
            if (saved !== null) {
                sel.value = saved;
            }
        }

        var initial = sel.value;
        var parsed = initial === 'all' ? Infinity : parseInt(initial) || cfg.defaultVal;
        cfg.onChange(parsed);

        sel.addEventListener('change', function () {
            var val = this.value;
            var pageSize = val === 'all' ? Infinity : parseInt(val) || cfg.defaultVal;
            if (cfg.storageKey) {
                localStorage.setItem(cfg.storageKey, val);
            }
            cfg.onChange(pageSize);
        });
    }

    get sortState() {
        return this._sortState;
    }
}

// ───── Modal helpers ─────

// Shared detail-modal close (promoted from per-page copies, §10.0 rule 7 —
// the ui-class-detail-modal partial hardcodes onclick="closeModal()").
// Pages may still define their own closeModal (their later script blocks
// override this) — new pages only need a copy if they use a different modal id.
function closeModal() {
    var m = document.getElementById('classModal');
    if (m) m.style.display = 'none';
}

function closeOnEsc(closeFn) {
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeFn();
    });
}

function closeOnOverlayClick(e, closeFn) {
    if (e.target === e.currentTarget) closeFn();
}

// ───── Summary visibility helper ─────

// Auto-hide the summary section (cards + hint) when a filtered view is empty —
// there is nothing to summarize (§10.0 rule 7, promoted from
// replacement-history / my-request-history / replacement-home /
// request-approval which all follow the same pattern).
function syncSummarySection(visible) {
    var s = document.getElementById('summarySection');
    if (s) s.style.display = visible ? '' : 'none';
}

// ───── Summary flip cards (timetable pages) ─────// One delegated controller for the opt-in flip-card variant. Hover is CSS-only;
// click/tap and Enter/Space toggle `.is-flipped`. The info button opens the
// shared explanation modal without flipping the card, and closing restores both
// the card state and focus.
var SummaryCards = {
    _lastTrigger: null,

    init: function () {
        if (this._bound) return;
        this._bound = true;

        document.addEventListener('click', function (e) {
            var info = e.target.closest ? e.target.closest('[data-summary-info]') : null;
            if (info) {
                e.preventDefault();
                e.stopPropagation();
                SummaryCards.open(info);
                return;
            }

            /* summary-cards-button: a plain (non-flip) card flips by tap inside the
               sheet only — outside it nothing matches and the page is unchanged. */
            var card = e.target.closest
                ? e.target.closest('.summary-section--flip .summary-card, #summarySheetBody .summary-card')
                : null;
            if (card) SummaryCards.toggle(card);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                var modal = document.getElementById('summaryExplainModal');
                if (modal && modal.style.display !== 'none') {
                    e.preventDefault();
                    SummaryCards.close();
                }
                return;
            }

            if (e.key !== 'Enter' && e.key !== ' ') return;
            var target = e.target;
            /* The info button owns its own activation — do not also flip the card. */
            if (target.closest && target.closest('[data-summary-info]')) return;
            var card = target.closest
                ? target.closest('.summary-section--flip .summary-card, #summarySheetBody .summary-card')
                : null;
            if (!card) return;
            e.preventDefault();
            SummaryCards.toggle(card);
        });

        /* Once the pointer leaves, the click-pinned unflipped state is released,
           so hover behaves normally on the next visit. */
        document.addEventListener('mouseout', function (e) {
            var card = e.target.closest
                ? e.target.closest('.summary-section--flip .summary-card, #summarySheetBody .summary-card')
                : null;
            if (!card || card.contains(e.relatedTarget)) return;
            card.classList.remove('is-unflipped');
        });
    },

    toggle: function (card) {
        /* A click means "pin this state": `.is-unflipped` keeps a mouse still
           resting on the card from re-flipping it via :hover. */
        if (card.classList.contains('is-flipped')) {
            card.classList.remove('is-flipped');
            card.classList.add('is-unflipped');
            card.setAttribute('aria-expanded', 'false');
        } else {
            card.classList.remove('is-unflipped');
            card.classList.add('is-flipped');
            card.setAttribute('aria-expanded', 'true');
        }
    },

    open: function (trigger) {
        var modal = document.getElementById('summaryExplainModal');
        var body = document.getElementById('summaryExplainBody');
        if (!modal || !body) return;

        var card = trigger.closest('.summary-card');
        var title = card ? card.querySelector('.summary-label') : null;
        var source = card ? card.querySelector('.summary-explanation-source') : null;
        var explanation = source ? source.innerHTML : '';

        var heading = document.getElementById('summaryExplainTitle');
        if (heading && title) heading.textContent = title.textContent;

        body.innerHTML = '<div class="summary-explanation">' + (explanation || '') + '</div>';

        this._lastTrigger = trigger;
        this._lastCard = card;
        modal.style.display = 'flex';
        document.body.classList.add('summary-locked');

        var close = modal.querySelector('.modal-close');
        if (close) close.focus({ preventScroll: true });
    },

    close: function () {
        var modal = document.getElementById('summaryExplainModal');
        if (!modal) return;
        modal.style.display = 'none';
        document.body.classList.remove('summary-locked');

        if (this._lastCard) {
            this._lastCard.classList.remove('is-unflipped');
            this._lastCard.classList.add('is-flipped');
            this._lastCard.setAttribute('aria-expanded', 'true');
        }
        if (this._lastTrigger) this._lastTrigger.focus({ preventScroll: true });
        this._lastTrigger = null;
        this._lastCard = null;
    }
};

document.addEventListener('DOMContentLoaded', function () {
    SummaryCards.init();
});

// ───── Summary sheet (phones: the cards live behind one header button) ─────
// summary-cards-button, 2026-11-14. At <=768px the page's own #summarySection is
// MOVED into #summarySheetBody (never copied), because the pages hide/show that
// section through its inline `display` (syncSummarySection, cohort-timetable's
// empty view) and only a move carries that intent into the sheet. At >=769px the
// node goes back to its exact place and the sheet never shows.
//
// Layering law: the sheet is a plain .modal-overlay-style div (z 1200), so the
// existing #summaryExplainModal (.modal-overlay, z 999/1000) can be lifted above
// it with ONE scoped rule. A native <dialog>.showModal() would have used the top
// layer and could never have been beaten by that dialog (measured).
var SummarySheet = {
    _mq: null,
    _opener: null,
    _placeholder: null,
    _observer: null,
    _onKeyDown: null,

    init: function () {
        /* THE GATE — my-timetable-responsive-prompt (T9, 2026-11-15), and it must
           stay the first line of init(). On the two timetable pages that carry
           `.tt-responsive` this module is NOT the owner of #summarySection:
           sync() relocates that node into #summarySheetBody at <=768 REGARDLESS of
           the header button (the button only gates visibility), while
           TimetableResponsive moves the same node into #ttSheetBody. With both
           alive the two relocators fight for one node, this module's
           MutationObserver and its print handlers yank it back out of the sheet,
           and unrelocate()'s parent guard then leaves TWO placeholders behind. So
           on those pages TimetableResponsive is the single owner and nothing here
           ever binds — not the matchMedia listener, not beforeprint/afterprint,
           not the observer, not _bindKeys.
           The `#summarySheet` frame that partials/ui-summary-bar always renders
           stays in the DOM, and that is fine: it is inert, not merely quiet. Nothing
           binds to it (both its handlers are inline on its own hidden overlay and X),
           and nothing calls open() on these pages once T11/T14 drop the header's
           `summaryButton` opt-in — the only caller of open() in the whole file is
           that header button's inline onclick. */
        if (document.querySelector('.tt-responsive')) return;
        if (this._bound) return;
        this._bound = true;

        this._mq = window.matchMedia('(max-width: 768px)');
        var self = this;
        var onChange = function () { self.sync(); };
        if (this._mq.addEventListener) this._mq.addEventListener('change', onChange);
        else if (this._mq.addListener) this._mq.addListener(onChange);

        /* Printing can never capture the sheet: put the cards back inline for the
           paper (paper is ~794px wide, above the breakpoint, where inline is the
           intended look) and return them to the sheet afterwards. */
        window.addEventListener('beforeprint', function () { self.unrelocate(); });
        window.addEventListener('afterprint', function () { self.sync(); });

        this.sync();
    },

    _section: function () { return document.getElementById('summarySection'); },
    _body: function () { return document.getElementById('summarySheetBody'); },
    _sheet: function () { return document.getElementById('summarySheet'); },

    /* Which state is the sheet in for this width? Relocation + button visibility.
       Runs at init, on every breakpoint change and after printing. */
    sync: function () {
        var section = this._section();
        var body = this._body();
        if (!section || !body) return;

        if (this._mq.matches) {
            this.relocate(section, body);
        } else {
            if (section.parentNode === body) this.unrelocate();
            this.close();
        }

        this.syncButton();

        /* The page can hide the section at RUNTIME (syncSummarySection(false) when
           a filtered view is empty, and cohort-timetable writing style.display
           directly), so button visibility cannot be decided here alone. */
        if (!this._observer && window.MutationObserver) {
            var self = this;
            this._observer = new MutationObserver(function () { self.syncButton(); });
            this._observer.observe(section, { attributes: true, attributeFilter: ['style', 'class'] });
        }
    },

    /* Hide the opener whenever the page has nothing to summarise. The predicate is
       the section's OWN display — never offsetParent: inside the hidden sheet the
       section has no offsetParent on every phone page, which would hide the button
       in the normal state. */
    syncButton: function () {
        var btn = document.getElementById('summaryButton');
        if (!btn) return;
        var section = this._section();
        var pageHidIt = !section || window.getComputedStyle(section).display === 'none';
        btn.hidden = !this._mq.matches || pageHidIt;
    },

    relocate: function (section, body) {
        if (!section || !body || section.parentNode === body) return;

        var ph = document.createElement('span');
        ph.className = 'summary-section-placeholder';
        ph.hidden = true;
        if (section.parentNode) section.parentNode.insertBefore(ph, section);
        this._placeholder = ph;
        body.appendChild(section);

        /* The flip variant already ships tabindex/role/aria-label per card (and is
           driven by data-summary). The plain variant has none, and inside the sheet
           its cards must be reachable and announce themselves. */
        var cards = section.querySelectorAll('.summary-card');
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            if (card.hasAttribute('data-summary') || card.getAttribute('data-sheet-a11y')) continue;
            var label = card.querySelector('.summary-label');
            card.setAttribute('tabindex', '0');
            card.setAttribute('role', 'group');
            card.setAttribute('aria-expanded', 'false');
            card.setAttribute('aria-label',
                (label ? label.textContent.trim() : 'Summary card') + ': tap to show what it counts');
            card.setAttribute('data-sheet-a11y', '1');
        }
    },

    unrelocate: function () {
        var section = this._section();
        if (!section) return;

        if (this._placeholder && this._placeholder.parentNode) {
            this._placeholder.parentNode.insertBefore(section, this._placeholder);
            this._placeholder.parentNode.removeChild(this._placeholder);
        }
        this._placeholder = null;

        /* Strip exactly what relocate() added, marker included, so repeated
           cycles cannot accumulate state. The tap state has to go too: the widened
           delegations no longer match plain cards outside the sheet, so a carried
           .is-unflipped would leave a desktop card hover-dead for the session. */
        var marked = section.querySelectorAll('[data-sheet-a11y]');
        for (var i = 0; i < marked.length; i++) {
            marked[i].removeAttribute('tabindex');
            marked[i].removeAttribute('role');
            marked[i].removeAttribute('aria-expanded');
            marked[i].removeAttribute('aria-label');
            marked[i].removeAttribute('data-sheet-a11y');
        }
        /* The tap state goes for EVERY card, not only the plain variant: a card
           left flipped in the sheet must come back inline in its hover state.
           (Class Replacements became a flip-card page on 2026-11-14, so the
           "plain cards only" strip would have left it pinned.) */
        var cards = section.querySelectorAll('.summary-card');
        for (var j = 0; j < cards.length; j++) {
            cards[j].classList.remove('is-flipped');
            cards[j].classList.remove('is-unflipped');
        }
    },

    open: function (btn) {
        var sheet = this._sheet();
        var section = this._section();
        if (!sheet || !section) return;
        if (btn && btn.hidden) return;
        if (window.getComputedStyle(section).display === 'none') return;
        if (window.FiltersPanel) FiltersPanel.close(false);

        this._opener = btn || document.getElementById('summaryButton');
        sheet.hidden = false;
        document.body.classList.add('summary-sheet-open');
        if (this._opener) this._opener.setAttribute('aria-expanded', 'true');

        var close = sheet.querySelector('.summary-sheet-close');
        if (close) close.focus({ preventScroll: true });

        this._bindKeys();
    },

    close: function () {
        var sheet = this._sheet();
        if (!sheet) return;

        var wasOpen = !sheet.hidden;
        sheet.hidden = true;
        document.body.classList.remove('summary-sheet-open');
        this._unbindKeys();

        var opener = this._opener;
        this._opener = null;
        if (opener) opener.setAttribute('aria-expanded', 'false');
        /* A resize-up close targets a button that is display:none at >=769px — the
           focus call is a deliberate silent no-op there. */
        if (wasOpen && opener) opener.focus({ preventScroll: true });
    },

    /* Capture phase on purpose: it runs BEFORE the ⓘ's own bubble-phase Esc
       handler, so the two can be arbitrated deterministically. */
    _bindKeys: function () {
        if (this._onKeyDown) return;
        var self = this;
        this._onKeyDown = function (e) {
            var sheet = self._sheet();
            if (!sheet || sheet.hidden) return;

            var explain = document.getElementById('summaryExplainModal');
            var explainOpen = !!(explain && explain.style.display !== 'none');

            if (e.key === 'Escape') {
                /* One keypress, one layer: while the ⓘ is up its own handler closes
                   it, and the sheet (plus the scroll lock) survives. */
                if (explainOpen) return;
                e.preventDefault();
                e.stopPropagation();
                self.close();
                return;
            }

            if (e.key !== 'Tab' || explainOpen) return;   /* trap suspended for the ⓘ */

            var panel = sheet.querySelector('.summary-sheet');
            if (!panel) return;
            var nodes = panel.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
                'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            var items = [];
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].offsetWidth || nodes[i].offsetHeight || nodes[i].getClientRects().length) {
                    items.push(nodes[i]);
                }
            }
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            var active = document.activeElement;

            if (e.shiftKey) {
                if (active === first || !panel.contains(active)) {
                    e.preventDefault();
                    last.focus();
                }
            } else if (active === last) {
                e.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', this._onKeyDown, true);
    },

    _unbindKeys: function () {
        if (!this._onKeyDown) return;
        document.removeEventListener('keydown', this._onKeyDown, true);
        this._onKeyDown = null;
    }
};

document.addEventListener('DOMContentLoaded', function () {
    SummarySheet.init();
});

// ───── Filters panel (filters-button, 2026-11-15) ─────
// The page's own .toolbar is MOVED into #filtersPanelBody, never rebuilt, so every
// control id and every listener the page already bound survives. A hidden
// placeholder span keeps the toolbar's seat in #filterToolbarHost for printing and
// for the case where this module never boots. The move happens at EVERY width —
// only the panel's shape changes at 768 (popover → bottom sheet), so there is no
// matchMedia relocation branch here. The `.filters-ready` class is added LAST in
// init(): the reveal of the button IS the proof the move succeeded (design §2), so
// a page whose JS never runs keeps today's toolbar inline and simply has no door.
var FiltersPanel = {
    _overlay: null,
    _panel: null,
    _button: null,
    _host: null,
    _placeholder: null,
    _opener: null,
    _mq: null,
    _onKeyDown: null,
    _onResize: null,

    init: function () {
        if (this._bound) return;

        this._panel = document.getElementById('filtersPanel');
        if (!this._panel) return;                     /* page never opted in */

        this._overlay = document.getElementById('filtersOverlay');
        this._button = document.getElementById('filtersButton');
        this._host = document.getElementById('filterToolbarHost');

        var toolbar = document.querySelector('.toolbar');
        if (!this._host || !toolbar) return;          /* nothing to move, or nowhere to go back to */

        this._bound = true;
        var self = this;

        /* The page emits the placeholder; create it only if a page forgot, so
           unrelocate() always has a seat to put the toolbar back into. */
        this._placeholder = this._host.querySelector('.filters-toolbar-placeholder');
        if (!this._placeholder) {
            this._placeholder = document.createElement('span');
            this._placeholder.className = 'filters-toolbar-placeholder';
            this._placeholder.hidden = true;
            this._host.insertBefore(this._placeholder, this._host.firstChild);
        }

        /* Crossing 768 while open keeps the panel open and re-places it: a rotated
           phone must not throw away a half-entered search. */
        this._mq = window.matchMedia('(max-width: 768px)');
        var onBreakpoint = function () {
            if (self._overlay && !self._overlay.hidden) self._place();
        };
        if (this._mq.addEventListener) this._mq.addEventListener('change', onBreakpoint);
        else if (this._mq.addListener) this._mq.addListener(onBreakpoint);

        if (this._button) {
            this._button.addEventListener('click', function () { self.open(); });
        }
        if (this._overlay) {
            this._overlay.addEventListener('click', function (e) {
                if (e.target === self._overlay) self.close();
            });
        }
        var close = this._panel.querySelector('.filters-panel-close');
        if (close) close.addEventListener('click', function () { self.close(); });

        /* Print never captures the frame: the toolbar goes back inline for the
           paper (so the fields still print) and returns into the panel after. */
        window.addEventListener('beforeprint', function () { self.unrelocate(); });
        window.addEventListener('afterprint', function () { self.relocate(); });

        this.relocate();

        /* Sync the closed state, then reveal — always last. */
        if (this._overlay) this._overlay.hidden = true;
        if (this._button) this._button.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('filters-panel-open');

        document.documentElement.classList.add('filters-ready');
    },

    _body: function () { return document.getElementById('filtersPanelBody'); },

    /* .toolbar → #filtersPanelBody. Idempotent: the SAME node, never a copy. */
    relocate: function () {
        var toolbar = document.querySelector('.toolbar');
        var body = this._body();
        if (!toolbar || !body || !this._host) return;
        if (toolbar.parentNode === body) return;

        /* The placeholder stays in the host, hidden (the SummarySheet discipline):
           it is what keeps the toolbar's seat while the toolbar is away. */
        var ph = this._placeholder;
        if (ph && ph.parentNode !== this._host) this._host.insertBefore(ph, this._host.firstChild);
        body.appendChild(toolbar);
    },

    /* .toolbar → its seat in #filterToolbarHost, next to the placeholder. */
    unrelocate: function () {
        var toolbar = document.querySelector('.toolbar');
        var host = this._host;
        if (!toolbar || !host) return;
        if (toolbar.parentNode === host) return;

        var ph = this._placeholder;
        if (ph && ph.parentNode === host) host.insertBefore(toolbar, ph);
        else host.appendChild(toolbar);
    },

    open: function () {
        var overlay = this._overlay;
        var panel = this._panel;
        if (!overlay || !panel || !overlay.hidden) return;

        /* One overlay at a time (design §6.7): the bell and the summary sheet sit at
           the same z-index 1200, so without this the winner would be DOM order. */
        closeNotifPanel();
        if (window.SummarySheet) SummarySheet.close();

        this._opener = document.getElementById('filtersButton') || this._button;
        overlay.hidden = false;
        if (this._button) this._button.setAttribute('aria-expanded', 'true');
        document.body.classList.add('filters-panel-open');

        this._place();

        /* The first control, never a .week-arrow: those are landmarks, not entries. */
        var body = this._body();
        if (body) {
            /* A text field or a select first: focusing the switch would pop its tip
               the moment the panel opens (the student page's switch is its first
               control). Checkboxes are skipped for that reason, not as a11y policy. */
            var first = body.querySelector(
                'input:not([type=hidden]):not([type=checkbox]), select, button:not(.week-arrow)'
            );
            if (first) first.focus({ preventScroll: true });
        }

        this._bindKeys();
        this._bindResize();
    },

    /* restoreFocus=false is for the co-existence rule only: a bell or a summary
       sheet opening on top closes this panel without stealing their focus. */
    close: function (restoreFocus) {
        var overlay = this._overlay;

        if (overlay) overlay.hidden = true;
        document.body.classList.remove('filters-panel-open');
        if (this._button) this._button.setAttribute('aria-expanded', 'false');

        this._unbindKeys();
        this._unbindResize();

        var opener = this._opener;
        this._opener = null;
        if (restoreFocus !== false && opener) opener.focus({ preventScroll: true });
    },

    /* ≥769: the card, anchored under the button's left edge and clamped inside the
       viewport. ≤768: CSS owns the bottom sheet, so the inline box is cleared. */
    _place: function () {
        var panel = this._panel;
        var btn = this._button;
        if (!panel || !btn) return;

        if (window.innerWidth <= 768) {
            panel.style.left = '';
            panel.style.top = '';
            panel.style.width = '';
            panel.style.maxHeight = '';
            return;
        }

        var r = btn.getBoundingClientRect();
        var w = Math.min(460, window.innerWidth - 16);
        var left = Math.min(Math.max(r.left, 8), window.innerWidth - w - 8);
        panel.style.left = left + 'px';
        panel.style.top = (r.bottom + 8) + 'px';
        panel.style.width = w + 'px';
        panel.style.maxHeight = Math.max(200, window.innerHeight - (r.bottom + 8) - 8) + 'px';
    },

    /* A resize re-places the card and MUST NEVER close the panel (design §6.3) —
       deliberately not the summary sheet's resize-up close. Bound only while open. */
    _bindResize: function () {
        if (this._onResize) return;
        var self = this;
        var pending = false;
        this._onResize = function () {
            if (!self._overlay || self._overlay.hidden) return;
            if (pending) return;
            pending = true;
            var run = function () { pending = false; self._place(); };
            if (window.requestAnimationFrame) window.requestAnimationFrame(run);
            else setTimeout(run, 100);
        };
        window.addEventListener('resize', this._onResize);
        window.addEventListener('orientationchange', this._onResize);
    },

    _unbindResize: function () {
        if (!this._onResize) return;
        window.removeEventListener('resize', this._onResize);
        window.removeEventListener('orientationchange', this._onResize);
        this._onResize = null;
    },

    /* A dialog ABOVE this panel owns the next Escape: the guide dialog and the row
       modals set style.display='flex', the summary ⓘ is 'none' at rest. */
    _dialogAbove: function () {
        if (document.querySelector('.modal-overlay[style*="flex"]')) return true;
        var explain = document.getElementById('summaryExplainModal');
        return !!(explain && explain.style.display !== 'none');
    },

    /* Capture phase on purpose: it runs BEFORE the pages' bubble-phase Escape
       handlers, so the layers can be arbitrated deterministically. */
    _bindKeys: function () {
        if (this._onKeyDown) return;
        var self = this;
        this._onKeyDown = function (e) {
            if (!self._overlay || self._overlay.hidden) return;

            if (e.key === 'Escape') {
                if (self._dialogAbove()) return;      /* one keypress, one layer */
                e.preventDefault();
                e.stopPropagation();
                self.close();
                return;
            }

            if (e.key !== 'Tab' || self._dialogAbove()) return;   /* trap suspended for the dialog */

            var panel = self._panel;
            if (!panel) return;
            var nodes = panel.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
                'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            var items = [];
            for (var i = 0; i < nodes.length; i++) {
                if (nodes[i].offsetWidth || nodes[i].offsetHeight || nodes[i].getClientRects().length) {
                    items.push(nodes[i]);
                }
            }
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            var active = document.activeElement;

            if (e.shiftKey) {
                if (active === first || !panel.contains(active)) {
                    e.preventDefault();
                    last.focus();
                }
            } else if (active === last) {
                e.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', this._onKeyDown, true);
    },

    _unbindKeys: function () {
        if (!this._onKeyDown) return;
        document.removeEventListener('keydown', this._onKeyDown, true);
        this._onKeyDown = null;
    }
};

// ───── Guide modal ─────
// One shared dialog, opened by the page header's guide icon. The opener keeps
// aria-expanded in sync; closing restores focus and unlocks the page.
var GuideModal = {
    _opener: null,

    open: function (opener) {
        var modal = document.getElementById('guideModal');
        if (!modal) return;

        this._opener = opener || null;
        modal.style.display = 'flex';
        document.body.classList.add('summary-locked');
        if (opener) opener.setAttribute('aria-expanded', 'true');

        var close = modal.querySelector('.modal-close') || modal.querySelector('.btn-close-modal');
        if (close) close.focus({ preventScroll: true });
    },

    close: function () {
        var modal = document.getElementById('guideModal');
        if (!modal) return;
        modal.style.display = 'none';
        document.body.classList.remove('summary-locked');

        if (this._opener) {
            this._opener.setAttribute('aria-expanded', 'false');
            this._opener.focus({ preventScroll: true });
        }
        this._opener = null;
    }
};

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var modal = document.getElementById('guideModal');
    if (modal && modal.style.display !== 'none') {
        e.preventDefault();
        GuideModal.close();
    }
});

class ModalController {
    constructor(modalId, renderFn) {
        this._modalId = modalId;
        this._renderFn = renderFn;
        this._onKeyDown = null;
        this._onOverlayClick = null;
    }

    open(data) {
        this.close();
        this._renderFn(data);
        const modal = document.getElementById(this._modalId);
        if (modal) modal.style.display = 'flex';
        this._onKeyDown = (e) => {
            if (e.key === 'Escape') this.close();
        };
        this._onOverlayClick = (e) => {
            if (e.target === modal) this.close();
        };
        document.addEventListener('keydown', this._onKeyDown);
        if (modal) modal.addEventListener('click', this._onOverlayClick);
    }

    close() {
        const modal = document.getElementById(this._modalId);
        if (modal) modal.style.display = 'none';
        if (this._onKeyDown) {
            document.removeEventListener('keydown', this._onKeyDown);
            this._onKeyDown = null;
        }
        if (this._onOverlayClick) {
            if (modal) modal.removeEventListener('click', this._onOverlayClick);
            this._onOverlayClick = null;
        }
    }

    isOpen() {
        const modal = document.getElementById(this._modalId);
        return modal ? modal.style.display !== 'none' : false;
    }
}

// ───── BulkSelection (shared by my-request-history + request-approval) ─────
// Owns the set of selected ids and the bulk action bar UI (visibility + count),
// plus resetting row/header checkboxes on clear(). Pages own their own refresh
// (renderTable / highlight) after mutating.

class BulkSelection {
    constructor(cfg) {
        cfg = cfg || {};
        this.ids = new Set();
        this.bar = document.getElementById(cfg.barId || 'bulkActionBar');
        this.count = document.getElementById(cfg.countId || 'bulkCount');
        this.checkboxSelector = cfg.checkboxSelector || null;   // e.g. '.row-checkbox'
        this.headerCheckboxId = cfg.headerCheckboxId || null;   // e.g. 'headerCheckbox'
    }

    get size() { return this.ids.size; }

    has(id) { return this.ids.has(id); }

    add(id) { this.ids.add(id); this.updateBar(); }

    delete(id) { this.ids.delete(id); this.updateBar(); }

    toggle(id) { this.ids.has(id) ? this.ids.delete(id) : this.ids.add(id); this.updateBar(); }

    setAll(ids) { this.ids = new Set(ids || []); this.updateBar(); }

    clear() {
        this.ids.clear();
        if (this.checkboxSelector) {
            document.querySelectorAll(this.checkboxSelector).forEach(function (cb) { cb.checked = false; });
        }
        if (this.headerCheckboxId) {
            var hc = document.getElementById(this.headerCheckboxId);
            if (hc) hc.checked = false;
        }
        this.updateBar();
    }

    updateBar() {
        var bar = this.bar;
        if (!bar) return;
        if (this.ids.size === 0) {
            bar.classList.remove('visible');
            return;
        }
        bar.classList.add('visible');
        if (this.count) this.count.textContent = this.ids.size + ' selected';
    }
}

// ───── Login page helpers ─────

function togglePassword() {
    const pw = document.getElementById('password');
    const eye = document.getElementById('eye-icon');
    const isHidden = pw.type === 'password';
    pw.type = isHidden ? 'text' : 'password';
    eye.innerHTML = isHidden
        ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94"/><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/><path d="M14.12 14.12a3 3 0 1 1-4.24-4.24"/>'
        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}

function ripple(e, btn) {
    const rect = btn.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = e.clientX - rect.left - size / 2;
    const y = e.clientY - rect.top - size / 2;
    const el = document.createElement('span');
    el.className = 'ripple';
    el.style.width = el.style.height = size + 'px';
    el.style.left = x + 'px';
    el.style.top = y + 'px';
    btn.appendChild(el);
    setTimeout(() => el.remove(), 500);
}

// ───── Mobile Navigation ─────

/**
 * Initialize keyboard shortcuts for week navigation ([ and ]).
 * Each page must define either prevWeek()/nextWeek() or prevWeekFilter()/nextWeekFilter().
 */
function initWeekKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
        if (e.key === '[') {
            e.preventDefault();
            if (typeof prevWeek === 'function') prevWeek();
            else if (typeof prevWeekFilter === 'function') prevWeekFilter();
        } else if (e.key === ']') {
            e.preventDefault();
            if (typeof nextWeek === 'function') nextWeek();
            else if (typeof nextWeekFilter === 'function') nextWeekFilter();
        }
    });
}

// ───── Mobile Navigation Drawer ─────

function openNavDrawer() {
    // AD-12 direction 2: opening the drawer closes the notifications panel.
    // typeof guard — hoisted drawer code may run before panel definitions
    // in some load orders (ui-template inline script vs ui-common.js).
    if (typeof closeNotifPanel === 'function') closeNotifPanel();
    const drawer = document.getElementById('navDrawer');
    const overlay = document.getElementById('navDrawerOverlay');
    drawer.classList.add('open');
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeNavDrawer() {
    const drawer = document.getElementById('navDrawer');
    const overlay = document.getElementById('navDrawerOverlay');
    drawer.classList.remove('open');
    overlay.classList.remove('open');
    document.body.style.overflow = '';
}

function initMobileNav() {
    const hamburger = document.getElementById('navHamburger');
    const drawer = document.getElementById('navDrawer');
    const overlay = document.getElementById('navDrawerOverlay');
    const closeBtn = document.getElementById('navDrawerClose');

    if (!hamburger || !drawer || !overlay) return;

    function toggleDrawer() {
        if (drawer.classList.contains('open')) closeNavDrawer();
        else openNavDrawer();
    }

    hamburger.addEventListener('click', toggleDrawer);
    closeBtn.addEventListener('click', closeNavDrawer);
    overlay.addEventListener('click', closeNavDrawer);

    // Swipe left to close
    let touchStartX = 0;
    drawer.addEventListener('touchstart', (e) => {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    drawer.addEventListener('touchend', (e) => {
        const diff = touchStartX - e.changedTouches[0].clientX;
        if (diff > 50) closeNavDrawer();
    }, { passive: true });

    // Close on Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && drawer.classList.contains('open')) closeNavDrawer();
    });
}

// ───── Notifications Panel (the NavNotifications islands' JS shell) ─────
// SDD notifications-real-bridge: the badge/pill/rows are SERVER-rendered by
// the wire:poll.10s.visible islands; the unread state lives in the DB
// (notifications.read_at) — the localStorage/mock store ('notifications-read-
// user-<staffId>' + window.MockData.notifications) is RETIRED. This section
// keeps ONLY the shell + the mark-read wiring: open/close/Esc/overlay, the
// pageLivewire() resolver, and the D4 durable POST (durableMarkNotifRead).
// Still-true design notes: ONE FEED (notif-unified-feed, 2026-10-29) — the
// panel views the recipient's whole mailbox on every page; the badge/pill
// count the same number (F-2) and the recipient sees only their own rows.
// All element lookups are guarded — the panel partial may be absent (the
// login pages).

/* ───── Notifications (SDD notifications-real-bridge) ─────
   The bell/panel markup is SERVER-rendered by the NavNotifications islands
   (wire:poll.10s) — the badge, pill, list and caught-up swap all live in the
   component. The read path is the DURABLE POST below (the D4 law: mark read
   BEFORE the native navigation, keepalive so in-flight navigation can't kill
   the write). The localStorage/mock store is retired (round-2 🟡s). */

/** The PAGE component resolver (SDD notifications-real-bridge hotfix):
 *  the bell island renders BEFORE the page content, so Livewire.first() now
 *  resolves to nav-notifications and page actions (approve/reject/cancel/
 *  intentSessionOptions/submitBlocks/cancelClass) would miss their methods.
 *  The house's page actions must ALWAYS hop onto the page component.
 *  Livewire-4 shape note (pagelivewire-wire-call-shape, 2026-10-29): all()
 *  returns RAW Component objects (no .call); the call API lives on $wire
 *  (aliases call->$call) — the resolver returns comp.$wire; the island
 *  exclusion is spelling-agnostic (the registered name form varied). */
window.pageLivewire = function () {
    if (!window.Livewire) return null;
    const all = window.Livewire.all ? window.Livewire.all() : [];
    const page = all.find(function (c) {
        const name = String((c.name || (c.snapshot && c.snapshot.memo && c.snapshot.memo.name) || '')).toLowerCase();
        return name !== 'navnotifications' && name !== 'nav-notifications';
    });
    if (!page) return null;
    // Livewire 4: the call API lives on $wire. The guard keeps a page action from
    // blanking the JS-owned week select (see withWeekLivewireGuard).
    return withWeekLivewireGuard(page.$wire ? page.$wire : page);
};

/** Durable mark-read: idempotent, auth-scoped, keepalive. */
function durableMarkNotifRead(id) {
    const csrf = document.head.querySelector('meta[name="csrf-token"]');
    try {
        fetch('/notifications/read', {
            method: 'POST',
            keepalive: true,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrf ? csrf.content : '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ id: Number(id) }),
        }).catch(function () { /* volatile-fail is acceptable; the row re-offers */ });
    } catch (e) { /* ignore */ }
}

/**
 * Put ONE row away in place (notif-click-behaviour, 2026-11-04) — the panel's
 * host Livewire component owns the write, so the row leaves the list on the
 * next morph. Returns false when there is no component to ask (the caller then
 * falls back to plain navigation rather than swallowing the click).
 *
 * The server re-validates the id (`NavNotifications::hideOne` → the manage-mode
 * gate) and stamps `hidden_at`; nothing is deleted, and the Hidden shelf is the
 * way back.
 */
function hideNotifRowInPlace(row) {
    if (typeof window.Livewire === 'undefined' || !window.Livewire.all) return false;
    const id = Number(row.getAttribute('data-id'));
    if (!id) return false;
    /* WHICH component: the island that OWNS this row — found by containment, not
       by a `[wire\:id]` selector (the escaped selector is easy to get wrong and
       threw in the first pass) and not by pageLivewire() (that deliberately
       skips the notification islands). Livewire 4 note: all() returns raw
       components; the call API lives on $wire (see pageLivewire()). */
    try {
        const owner = window.Livewire.all().find(function (c) {
            return c && c.el && c.el.contains && c.el.contains(row);
        });
        const target = owner && (owner.$wire ? owner.$wire : owner);
        if (!target || typeof target.call !== 'function') return false;
        target.call('hideOne', id);
        return true;
    } catch (err) {
        return false;
    }
}

function openNotifPanel() { // AD-12 direction 1 — drawer never stacks with panel
    if (window.FiltersPanel) FiltersPanel.close(false);
    closeNavDrawer();
    /* The other half of "one overlay at a time" (my-timetable-responsive-prompt R6,
       T9): the bell and the Week summary sheet sit on the same layer, so opening the
       bell closes the sheet from HERE — inside this file's own bell code, so no blade
       has to know the sheet exists and no onclick grows a second handler.
       restoreFocus=false: the bell owns the focus, and closeSheet() must not pull it
       back to #ttSummaryButton. Safe when the sheet is closed: closeSheet() then only
       re-asserts the resting state (it is idempotent). */
    if (window.TimetableResponsive && typeof TimetableResponsive.closeSheet === 'function') {
        TimetableResponsive.closeSheet(false);
    }
    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('notifOverlay');
    const bell = document.querySelector('.notif-btn');
    if (panel) panel.classList.add('open');
    if (overlay) overlay.classList.add('open');
    if (bell) bell.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden'; // AD-11 — inline lock, both breakpoints
}

function closeNotifPanel() {
    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('notifOverlay');
    const bell = document.querySelector('.notif-btn');
    if (panel) panel.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
    if (bell) bell.setAttribute('aria-expanded', 'false');
    // Release the body lock only if the nav drawer isn't open too (AD-11) —
    // releasing while the drawer holds its own lock would let the body scroll.
    const drawer = document.getElementById('navDrawer');
    if (!drawer || !drawer.classList.contains('open')) {
        document.body.style.overflow = '';
    }
}

function toggleNotifPanel() {
    const panel = document.getElementById('notifPanel');
    if (!panel) return;
    if (panel.classList.contains('open')) closeNotifPanel();
    else openNotifPanel();
}

function initNotifPanel() {
    if (window.__notifPanelInitialized) return;
    window.__notifPanelInitialized = true;

    const panel = document.getElementById('notifPanel');
    const overlay = document.getElementById('notifOverlay');

    const bell = document.querySelector('.notif-btn');
    if (bell && !bell.hasAttribute('onclick')) {
        bell.addEventListener('click', toggleNotifPanel);
    }
    if (overlay) overlay.addEventListener('click', closeNotifPanel); // outside click (AD-13)

    // Row click = durable mark read + restore scroll, BEFORE the anchor's
    // native navigation (D4/AD-15). Capture phase so it runs ahead; the
    // disabled (inactive) rows carry no data-id and are never marked.
    // notif-click-highlight: the ANCHOR rides along — data-notif-target
    // (r=request / s=session; the lane's gate emits it ONLY for the mapped
    // surfaces) plus data-notif-week on the s-rows — appended to the href so
    // the landing page can flash the target (the inactive guard stays FIRST,
    // keep-as-it). A row without the attribute → plain navigation.
    const list = document.getElementById('notifList');
    if (list) {
        list.addEventListener('click', function (e) {
            const row = e.target.closest ? e.target.closest('.notif-row[data-id]') : null;
            if (!row || !row.getAttribute('data-id')) return;
            const scrollTop = list.scrollTop;
            durableMarkNotifRead(row.getAttribute('data-id'));

            /* notif-click-behaviour (user, 2026-11-04): the click follows the
               row's RELATED RECORD STATUS, not its stored link.
                 completed          → put the row away in place, no navigation:
                                      the class already happened, there is
                                      nothing to jump to;
                 approved, cancelled → land on the row the notification is
                                      about and flash it (the anchor below);
                 anything else      → the row's own stored link, as before. */
            if (row.getAttribute('data-notif-status') === 'completed' && hideNotifRowInPlace(row)) {
                e.preventDefault();
                return;
            }

            const target = row.getAttribute('data-notif-target');
            const link = row.getAttribute('href');
            if (target && link) {
                const norm = link.startsWith('/') ? link : '/' + link;
                row.setAttribute('href', norm + (norm.includes('?') ? '&' : '?')
                    + 'notif-hl=' + encodeURIComponent(target)
                    + ((target.charAt(0) === 's' || target.charAt(0) === 'o') && row.getAttribute('data-notif-week')
                        ? '&notif-w=' + encodeURIComponent(row.getAttribute('data-notif-week'))
                        : ''));
            }
            // the island's next poll re-render keeps the read state; restore
            // the scroll after the morph settles (AD-15).
            setTimeout(function () { list.scrollTop = scrollTop; }, 50);
        }, true);
    }

    // Esc closes only while the panel is open (AD-13).
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        const p = document.getElementById('notifPanel');
        if (p && p.classList.contains('open')) closeNotifPanel();
    });
}

document.addEventListener('DOMContentLoaded', function () {
    initNotifPanel();
});

// ───── Swipe Gesture ─────

function initSwipeGesture(config) {
    const { element, onSwipeLeft, onSwipeRight, threshold = 50 } = config;
    let touchStartX = 0;
    let touchStartY = 0;
    let lastSwipeTime = 0;

    element.addEventListener('touchstart', (e) => {
        touchStartX = e.touches[0].clientX;
        touchStartY = e.touches[0].clientY;
    }, { passive: true });

    element.addEventListener('touchend', (e) => {
        const now = Date.now();
        if (now - lastSwipeTime < 300) return; // debounce

        const diffX = touchStartX - e.changedTouches[0].clientX;
        const diffY = Math.abs(touchStartY - e.changedTouches[0].clientY);

        if (Math.abs(diffX) > threshold && diffY < 100) {
            lastSwipeTime = now;
            if (diffX > 0) onSwipeLeft();
            else onSwipeRight();
        }
    }, { passive: true });
}

// ───── Collapsible Day Cards ─────

function initCollapsibleCards() {
    document.querySelectorAll('.day-card-header').forEach(header => {
        header.addEventListener('click', () => {
            header.classList.toggle('collapsed');
            const content = header.nextElementSibling;
            content.classList.toggle('collapsed');
        });
    });
}

// ───── DateHelper (static utility class) ─────

class DateHelper {
    static to12h(t) {
        const [hStr, m] = t.split(':');
        const h = parseInt(hStr);
        const ampm = h >= 12 ? 'PM' : 'AM';
        const h12 = h === 0 ? 12 : h > 12 ? h - 12 : h;
        return h12 + ':' + m + ' ' + ampm;
    }

    /**
     * Convert a 24h time range string like "09:00 – 11:00" (also "09:00-11:00",
     * "09:00 - 11:00") into "9:00 AM to 11:00 AM". Returns the input unchanged
     * when it does not look like a time range. `sep` overrides the joiner
     * (My Replacements' REQUESTED REPLACEMENT cell passes ' ~ ').
     */
    static format12hRange(t, sep) {
        if (!t) return t;
        const m = String(t).match(/(\d{1,2}:\d{2})\s*[-–—]\s*(\d{1,2}:\d{2})/);
        if (!m) return t;
        return DateHelper.to12h(m[1]) + (sep || ' to ') + DateHelper.to12h(m[2]);
    }

    static formatDate(iso) {
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const parts = iso.split('-');
        return parseInt(parts[2]) + ' ' + months[parseInt(parts[1]) - 1] + ' ' + parts[0];
    }

    static formatDateTime(iso) {
        if (!iso) return '';
        const [datePart, timePart] = iso.split('T');
        const [y, mo, d] = datePart.split('-');
        const [h, mi] = timePart.split(':');
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const day = parseInt(d);
        const month = months[parseInt(mo) - 1];
        const year = parseInt(y);
        let hh = parseInt(h);
        const mm = mi;
        const ampm = hh >= 12 ? 'PM' : 'AM';
        hh = hh === 0 ? 12 : hh > 12 ? hh - 12 : hh;
        return day + ' ' + month + ' ' + year + ', ' + hh + ':' + mm + ' ' + ampm;
    }

    static fmt(d) {
        return d.getDate() + ' ' + d.toLocaleString('en', { month: 'short' }) + ' ' + d.getFullYear();
    }

    static fmtShort(d) {
        return d.getDate() + ' ' + d.toLocaleString('en', { month: 'short' });
    }

    static weekRangeLabel(weekNum) {
        var range = weekRanges.find(function(w) { return w.value === String(weekNum); });
        if (!range) return 'Week ' + weekNum;
        var startParts = range.start.split('-');
        var endParts = range.end.split('-');
        var start = new Date(startParts[0], startParts[1] - 1, startParts[2]);
        var end = new Date(endParts[0], endParts[1] - 1, endParts[2]);
        if (window.innerWidth <= 768) {
            return 'Week ' + weekNum + ' \u00B7 ' + DateHelper.fmtShort(start) + ' ~ ' + DateHelper.fmtShort(end);
        }
        return 'Week ' + weekNum + ' \u00B7 ' + DateHelper.fmt(start) + ' ~ ' + DateHelper.fmt(end);
    }

    static add30min(t) {
        const [h, m] = t.split(':').map(Number);
        const total = h * 60 + m + 30;
        return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
    }

    static dayAbbr(day) {
        return day.substring(0, 3);
    }

    static isoDayName(iso) {
        return DateHelper.weekdayName(iso);
    }

    /**
     * The weekday NAME of a date string — "2026-11-16", "16 Nov 2026" or
     * "16-Nov-2026" → "Monday". Returns '' when the string is not a date.
     *
     * 2026-11-11 (user report, #earliestBtn): the arrangement tooltip read
     * "Sunday, 16 Nov 2026" for a MONDAY. Nothing was wrong with the date — the
     * name was taken from `dayNames[weekData[..].days[..].day]` with a
     * Sunday-first array, while weekData.days runs MONDAY→SUNDAY, so every name
     * printed one day early. A name derived from the date itself cannot drift
     * from the date printed beside it, whatever the index convention.
     *
     * Local midnight, all parts checked, so a DST/UTC boundary can neither shift
     * the day nor be smuggled in by an impossible date (31 Feb → '').
     */
    static weekdayName(dateStr) {
        var NAMES = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
        var MONTHS = { jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5,
            jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11 };
        var s = String(dateStr == null ? '' : dateStr).trim();
        var y, mo, d;
        var iso = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})$/);
        var dmy = s.match(/^(\d{1,2})[\s-]([A-Za-z]{3,})[\s-](\d{4})$/);
        if (iso) {
            y = parseInt(iso[1]); mo = parseInt(iso[2]) - 1; d = parseInt(iso[3]);
        } else if (dmy) {
            y = parseInt(dmy[3]); d = parseInt(dmy[1]);
            mo = MONTHS[dmy[2].slice(0, 3).toLowerCase()];
            if (mo === undefined) return '';
        } else {
            return '';
        }
        var dt = new Date(y, mo, d);
        if (dt.getFullYear() !== y || dt.getMonth() !== mo || dt.getDate() !== d) return '';
        return NAMES[dt.getDay()];
    }

    static getTodayMs() {
        /* anchored to MockData.mockNow (the fixed demo "today") when present,
           falling back to the real clock once that anchor is deleted —
           TODO(backend) note sits in mock-data.js mockNow; both stay in sync. */
        var src = (window.MockData && MockData.mockNow) ? new Date(MockData.mockNow) : new Date();
        src.setHours(0, 0, 0, 0);
        return src.getTime();
    }
}

// ───── Days-left display contract (replacement-home surfaces: table cell,
// mobile card footer, quick-view modal Status row — 3rd-duplication promo) ─────

/* ───── Working days (user law 2026-11-07, SDD waived — display only) ─────
   The class-date countdown counts WORKING days: Mon–Fri and not one of the
   semester's own holiday rows (the same set the timetable greys out and the
   booking lead-time rule already skips — never a hard-coded calendar). */

/* The semester's public-holiday DATES as 'Y-m-d' keys — the ONE derivation in
   the page. MockData.holidays carries week + dayIndex only, so the dates come
   from semester.startDate (the same arithmetic as generateWeekData). Shared by
   the class-date countdown AND ClassCancellation's cancel window (its private
   copy was deleted 2026-11-07). A missing payload degrades to an empty set (the
   weekend-only rule). */
function workingDayHolidayKeys() {
    const out = new Set();
    const s = window.MockData && MockData.semester ? MockData.semester.startDate : null;
    if (!s) return out;
    const p = String(s).split('-');
    const start = new Date(parseInt(p[0], 10), parseInt(p[1], 10) - 1, parseInt(p[2], 10));
    (MockData.holidays || []).forEach(function (h) {
        const dt = new Date(start.getFullYear(), start.getMonth(), start.getDate() + ((h.week - 1) * 7) + h.dayIndex);
        out.add(workingDayKey(dt));
    });
    return out;
}

/* Local-date identity 'Y-m-d' (never toISOString — that would drift a day). */
function workingDayKey(d) {
    return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
}

/* Date | 'YYYY-MM-DD' → a LOCAL midnight Date (null when unparseable). */
function workingDayParse(value) {
    if (value instanceof Date) {
        return new Date(value.getFullYear(), value.getMonth(), value.getDate());
    }
    const m = String(value == null ? '' : value).match(/^(\d{4})-(\d{2})-(\d{2})$/);
    return m ? new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10)) : null;
}

/* Mon–Fri and not a holiday. */
function isWorkingDayDate(d, holidayKeys) {
    const day = d.getDay();
    if (day === 0 || day === 6) return false;
    return !(holidayKeys || workingDayHolidayKeys()).has(workingDayKey(d));
}

/* Signed working-day distance: working days in (from, to] forward, negated when
   `to` is earlier. Both endpoints are local midnights, so the walk is symmetric. */
function workingDaysBetween(from, to) {
    const a = workingDayParse(from);
    const b = workingDayParse(to);
    if (!a || !b) return 0;
    if (a.getTime() === b.getTime()) return 0;
    const forward = b.getTime() > a.getTime();
    const first = forward ? a : b;
    const last = forward ? b : a;
    const holidays = workingDayHolidayKeys();
    let count = 0;
    const cursor = new Date(first.getFullYear(), first.getMonth(), first.getDate() + 1);
    for (let guard = 0; cursor.getTime() <= last.getTime() && guard < 5000; guard++) {
        if (isWorkingDayDate(cursor, holidays)) count++;
        cursor.setDate(cursor.getDate() + 1);
    }
    return forward ? count : -count;
}

/* The My Replacements number: signed working-day distance from today to the
   class date. A non-working class date (Sunday, a holiday) would count 0 while
   the calendar says otherwise, so the result clamps to ±1 — "0" must keep
   meaning today and only today. */
function workingDaysDiff(iso) {
    const target = workingDayParse(iso);
    if (!target) return 0;
    const today = new Date(getTodayMs());
    const days = workingDaysBetween(today, target);
    if (days !== 0) return days;
    const calendar = Math.round((target.getTime() - today.getTime()) / 86400000);
    return calendar === 0 ? 0 : (calendar > 0 ? 1 : -1);
}

/* The ONE band rule for a day countdown (user law 2026-11-07): past + today red,
   1–7 working days amber, 8+ green. Both pages that print a countdown call this;
   the colour classes themselves live in theme.css. */
function urgencyBandClass(days) {
    if (days <= 0) return 'urgency-high';
    if (days <= 7) return 'urgency-mid';
    return 'urgency-low';
}

/* daysLeft → label (working days): <0 "Overdue (n Working Days Ago)" · 0 "Today"
   · 1 "1 Working Day Left" · else "n Working Days Left". Urgency classes reuse
   the table vocabulary (urgency-high/mid/low — the only defined CSS); overdue
   renders in the same red as imminent (error semantics). */
function daysLeftLabel(days) {
    if (days < 0) {
        const n = Math.abs(days);
        return 'Overdue (' + n + (n === 1 ? ' Working Day' : ' Working Days') + ' Ago)';
    }
    if (days === 0) return 'Today';
    return days + (days === 1 ? ' Working Day Left' : ' Working Days Left');
}

/* Status enum → display label (§10.0: same name for the same meaning).
   Some datasets store lowercase enums ('normal') vs Title-case ('Pending') —
   this renders ONE Title-case vocabulary everywhere. CSS badge classes stay
   keyed by the raw enum (badge-normal etc.), so visuals are untouched. */
const StatusText = {
    map: { normal: 'Normal', replacement: 'Replacement', pending: 'Pending',
           approved: 'Approved', rejected: 'Rejected', conflict: 'Conflict' },
    label(status) {
        var s = String(status);
        var hit = this.map[s.toLowerCase()];
        if (hit) return hit;
        return s.charAt(0).toUpperCase() + s.slice(1);
    }
};

/* Canonical session-type labels (§10.0.2 — same name + same meaning everywhere). */
const UiLabels = {
    /* form: 'code' → L|T|P · 'long' → Lecture|Tutorial|Practical · 'canonical' → 'Lecture (L)' … */
    classType(type, form) {
        const long = { L: 'Lecture', T: 'Tutorial', P: 'Practical' }[type] || type;
        if (form === 'code') return type;
        if (form === 'canonical') return long + ' (' + type + ')';
        return long;
    },

    /* ───── The Course column's hover text (user brief 2026-11-09) ─────
       Shape: `CODE, Full Course Name (L)` + em dash + `courseTipSubject`.
       ONE place: both page headers ask `courseTipSubject` and both row builders ask
       `courseTip(...)`, so the wording (and the em-dash shape) can never drift page
       to page. Codes and names come from the row's own data; the class type uses
       the `(L)` code form — the same shape the cell itself prints. */
    courseTipSubject: 'Subject or Course affected by the conflict',
    courseTip(code, name, type) {
        const head = [String(code == null ? '' : code).trim(), String(name == null ? '' : name).trim()]
            .filter(Boolean).join(', ');
        const kind = type ? ' (' + this.classType(type, 'code') + ')' : '';
        return (head || 'Course') + kind + ' \u2014 ' + this.courseTipSubject;
    },
};

// ───── Backward-compatible global aliases (delegate to DateHelper) ─────

function to12h(t) { return DateHelper.to12h(t); }
function formatDate(iso) { return DateHelper.formatDate(iso); }
function formatDateTime(iso) { return DateHelper.formatDateTime(iso); }
function fmt(d) { return DateHelper.fmt(d); }
function add30min(t) { return DateHelper.add30min(t); }

/* The venue's per-type session vocabulary (SDD lt-venue-capacity-rule D5).
   ONE canonical phrase per room law (§10.0 same-meaning-same-wording — no
   word-order variants): Lecture Hall & Tutorial Rooms both admit L/T
   (capacity-gated); labs keep P. Unknown rawType → '' so a payload typo
   can never advertise a wrong law. */
function venueTypeWording(rawType) {
    /* lh-l-only-revert (2026-10-20): Lecture Halls narrow back to L-only
       (the phrase per RAW type; the tutorials keep the L/T dual shape). */
    const phrases = { lab: 'Practical (P)', cisco_lab: 'Practical (P)',
                      lecture_hall: 'Lecture (L)', tutorial: 'Tutorial (L/T)' };
    return phrases[rawType] || '';   // unknown → blank
}

/* ───── Course / venue / duration cell vocabulary (2026-11-09) ─────
   Both replacement tables (staff My Replacements + student Replacement History)
   print the same slot block, so these two formatters moved here from the staff
   page — one wording, one markup, no second copy. `venueLine` keeps the house
   house icon; an empty code renders nothing (a slot with no room says nothing
   rather than inventing one). */
function venueLine(code, name) {
    if (!code) return '';
    /* 2026-11-10 (user, modal consistency #4): one venue wording everywhere —
       ModalFacts.venue never repeats a code the room's own name already carries */
    var label = window.ModalFacts ? ModalFacts.venue(code, name) : (name ? code + ' \u2014 ' + name : code);
    return '<span class="cell-sub"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>' + label + '</span>';
}

function durationWords(hours) {
    /* 2026-11-10: a non-number must never print "(NaN minutes)" */
    if (hours == null || !isFinite(Number(hours)) || Number(hours) <= 0) return '';
    hours = Number(hours);
    if (hours === 1) return '1 hour';
    if (Number.isInteger(hours)) return hours + ' hours';
    return (hours * 60) + ' minutes';
}

/* ═══════════════════════════════════════════════════════════════════════════
   ModalFacts — the ONE wording for the detail modals' rows (user, 2026-11-10,
   "Modal Consistency" #4). Three formulations, used by Class / Slot / Request /
   Replacement Details so the same fact never reads two ways:
     Day & Date        Wed, 11 Nov 2026            (Title Case day, comma)
     Time & Duration   9:00 AM ~ 11:00 AM (2 hours) (the `~` joiner law)
     Venue             B010 — Tutorial Room
   ═══════════════════════════════════════════════════════════════════════════ */
var ModalFacts = {
    /* 'MONDAY' / 'Mon' / 'Monday' → 'Mon'; date strings pass through untouched */
    _day(day) {
        var raw = String(day == null ? '' : day).trim();
        if (!raw) return '';
        var three = raw.substring(0, 3).toLowerCase();
        return three.charAt(0).toUpperCase() + three.slice(1);
    },

    dayDate(day, date) {
        var d = this._day(day);
        var t = String(date == null ? '' : date).trim().replace(/,$/, '');
        if (!d) return t || '\u2014';
        if (!t) return d;
        return d + ', ' + t;
    },

    timeDuration(start12, end12, hours) {
        var start = String(start12 == null ? '' : start12).trim();
        if (!start) return '\u2014';
        var run = start + (String(end12 == null ? '' : end12).trim() ? ' ~ ' + String(end12).trim() : '');
        var words = durationWords(hours);
        return words ? run + ' (' + words + ')' : run;
    },

    /* `B002 — Tutorial Room (35 seats)` (user ruling B, 2026-11-10).
       The code LEADS, the room's own name loses the copy of the code it carries
       (every room in this dataset does: "Tutorial Room B002", "Computer Lab B005"),
       and the seat count is optional — only the venue page asks for it. */
    /* the room's own name, with the copy of its code removed — every room in this
       dataset carries one ("Tutorial Room B002" → "Tutorial Room"). Used by the
       Venue row above and by the arrangement page's selected-slot card tooltip. */
    roomName(code, name) {
        var c = String(code == null ? '' : code).trim();
        var n = String(name == null ? '' : name).trim();
        if (!n || !c) return n;
        var esc = c.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        return n.replace(new RegExp('\\s*[\\u2013\\u2014-]?\\s*' + esc, 'ig'), ' ')
                .replace(/\s+/g, ' ')
                .replace(/^[\s\u2013\u2014-]+|[\s\u2013\u2014-]+$/g, '');
    },

    venue(code, name, seats) {
        var c = String(code == null ? '' : code).trim();
        var n = this.roomName(c, name);
        if (!c) return n || '\u2014';
        var base = n ? c + ' \u2014 ' + n : c;
        var seatsN = Number(seats);
        if (seats != null && isFinite(seatsN) && seatsN > 0) {
            base += ' (' + seatsN + (seatsN === 1 ? ' seat' : ' seats') + ')';
        }
        return base;
    },
};
window.ModalFacts = ModalFacts;

/* Human label for an event's run-status — the hover-tooltip suffix on every
   timetable page ("· Normal / Pending / …"). A public-holiday day outranks
   the event's own status (its class won't run for a different reason). */
function eventStatusLabel(e, isConflict) {
    if (isConflict) return 'Public Holiday';
    switch (e.status) {
        case 'conflict':    return 'Conflict';
        case 'pending':     return 'Pending';
        case 'replacement': return 'Replacement';
        case 'cancelled':   return 'Cancelled';
        default:            return 'Normal';
    }
}
function dayAbbr(day) { return DateHelper.dayAbbr(day); }
function isoDayName(iso) { return DateHelper.isoDayName(iso); }
function getTodayMs() { return DateHelper.getTodayMs(); }

/* ───── The merged My Replacements cell vocabulary (request-approval-ui 1h,
   2026-11-04) ─────
   The merged lecturer page grew these three pieces page-locally; a second page
   needing the same cell would have been the third copy, so they live here and
   `classBlock(..., { merged: true })` uses them. Frozen templates and every
   legacy caller omit the flag and render byte-identically. */
function uiHoursWords(hours) {
    var n = Number(hours);
    return n === 1 ? '1 hour' : n + ' hours';
}

/* 'H:i – H:i' → hours (null when the range cannot be read) */
function uiHoursFromRange(range) {
    var m = String(range || '').match(/(\d{1,2}):(\d{2})\s*[–—-]\s*(\d{1,2}):(\d{2})/);
    if (!m) return null;
    var mins = (parseInt(m[3], 10) * 60 + parseInt(m[4], 10)) - (parseInt(m[1], 10) * 60 + parseInt(m[2], 10));
    if (mins <= 0) return null;
    return Math.round((mins / 60) * 10) / 10;
}

/* pin icon + `CODE — Name` (name only when the payload carries one).
   `opts.venueCodeOnly` keeps the pin and drops ONLY the name — request-approval's
   table columns read `pin B010`, never `pin B010 — Computer Lab B010` (user
   2026-11-04: the icon stays). Default path unchanged. */
function uiVenueLine(code, name, opts) {
    if (!code) return '';
    var label = (opts && opts.venueCodeOnly) ? code : (name ? code + ' \u2014 ' + name : code);
    return '<span class="cell-sub"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>' + label + '</span>';
}


// ───── HtmlBuilder (static utility class) ─────

class HtmlBuilder {
    static classBlock(r, opts) {
        opts = opts || {};
        var d = DateHelper.dayAbbr(r.classDay);
        var dateStr = DateHelper.formatDate(r.classDate);
        var wn = getWeekNumber(r.classDate);
        var weekTag = wn ? ' (Week ' + wn + ')' : '';
        var timeStr = DateHelper.to12h(r.timeStart) + ' ~ ' + DateHelper.to12h(r.timeEnd);
        var hrs = r.duration + ' hr' + (r.duration > 1 ? 's' : '');
        /* 1h (2026-11-04): the merged My Replacements cell — duration in words
           and the icon-led venue line the lecturer page prints. Opt-in, so the
           frozen templates and the legacy grid stay byte-identical. */
        if (opts.merged) {
            /* 2026-11-10 (user, "Table Columns"): no duration text in the slot
               columns — the time range already says how long it runs. */
            var venueHtml = uiVenueLine(r.venue, r.venueName, opts);
            return '<div class="cell-class-block">' +
                '<span class="class-day-date">' + d + ', ' + dateStr + weekTag + '</span>' +
                '<br><span class="class-time">' + timeStr + '</span>' +
                (venueHtml ? '<br>' + venueHtml : '') +
                '</div>';
        }
        /* 2026-11-05 UI/UX brief (Update 3): the Approve/Reject context card's
           ORIGINAL block drops the duration and prints the venue code on its
           own line (user follow-up: the code alone — no `Venue Code:` label).
           Opt-in, so every other caller stays byte-identical. */
        if (opts.originalCard) {
            return '<div class="cell-class-block">' +
                '<span class="class-day-date">' + d + ', ' + dateStr + weekTag + '</span>' +
                '<br><span class="class-time">' + timeStr + '</span>' +
                (r.venue ? '<br><span class="class-venue">' + escHtml(r.venue) + '</span>' : '') +
                '</div>';
        }
        return '<div class="cell-class-block"><span class="class-day-date">' + d + ', ' + dateStr + weekTag + '</span><br><span class="class-time">' + timeStr + '</span> <span class="class-duration">(' + hrs + ')</span></div>';
    }

    static replacementBlock(r, opts) {
        opts = opts || {};
        if (!r.replacementDate) return '<span style="color:var(--color-on-surface-variant);opacity:0.5">&mdash;</span>';
        var d = DateHelper.dayAbbr(DateHelper.isoDayName(r.replacementDate));
        var dateStr = DateHelper.formatDate(r.replacementDate);
        var wn = getWeekNumber(r.replacementDate);
        var weekTag = wn ? ' (Week ' + wn + ')' : '';
        /* 2026-11-07 (user): a PENDING request prints how soon its make-up slot
           starts, beside that slot's own date — the same working-day countdown
           My Replacements uses. Opt-in (`opts.pendingCountdown`) so every other
           caller of this builder (and the frozen templates) stays byte-identical. */
        var pendingDays = '';
        if (opts.pendingCountdown && String(r.status || '').toLowerCase() === 'pending') {
            var wd = workingDaysDiff(r.replacementDate);
            // 2026-11-07 (user): the countdown gets its own line under the date —
            // date / urgency / time — on this page too (same law as My Replacements).
            pendingDays = '<br><span class="' + urgencyBandClass(wd) + '">' + daysLeftLabel(wd) + '</span>';
        }
        if (opts.merged) {
            /* 2026-11-10 (user, "Table Columns"): the duration text is gone here too,
               so `uiHoursFromRange` is no longer needed to render the cell. */
            var repVenue = uiVenueLine(r.replacementVenue || r.venue, r.replacementVenueName, opts);
            return '<div class="cell-class-block">' +
                '<span class="class-day-date">' + d + ', ' + dateStr + weekTag + '</span>' + pendingDays +
                '<br><span class="class-time">' + DateHelper.format12hRange(r.replacementTime, ' ~ ') + '</span>' +
                (opts.showVenue !== false && repVenue ? '<br>' + (opts.slotBadge ? opts.slotBadge : '') + repVenue : '') +
                '</div>';
        }
        var html = '<div class="cell-class-block">'
            + '<span class="class-day-date">' + d + ', ' + dateStr + weekTag + '</span><br>'
            + '<span class="class-time' + (opts.colorStatus === false ? '' : ' ' + statusClass(r.status)) + '">'
            + DateHelper.format12hRange(r.replacementTime, ' ~ ') + '</span>';
        if (opts.showVenue !== false) {
            var venue = r.replacementVenue || r.venue || '—';
            html += '<br><span class="class-venue">'
                + (opts.slotBadge ? opts.slotBadge : '')
                + '<span class="venue-label">' + venue + '</span></span>';
        }
        return html + '</div>';
    }

    static dayHeader(day) {
        let html = '<span class="day-label">' + day.abbr + '</span><span class="date-label">' + day.date + '</span>';
        if (day.holiday) {
            /* generic badge text (displays PUBLIC HOLIDAY via the existing
               uppercase transform); the specific holiday name, when known,
               rides the shared data-tip hover tooltip */
            const tipAttr = day.holidayLabel ? ' data-tip="' + day.holidayLabel + '"' : '';
            html += '<span class="holiday-badge"' + tipAttr + '>Public Holiday</span>';
        }
        if (day.today) {
            html += '<span class="today-badge">Today</span>';
        } else if (day.sunday && !day.holiday) {
            html += '<span class="off-badge">OFF</span>';
        }
        return html;
    }

    static requestCard(r, opts) {
        var lecturerHtml = '';
        if (opts.lookupLecturer) {
            var l = opts.lookupLecturer(r.lecturer);
            lecturerHtml = l ? l.name + ' (' + l.staffId + ')' : r.lecturer;
        } else {
            lecturerHtml = r.lecturer;
        }
        var urgencyHtml = '';
        var urgencyText = '';
        if (opts.urgencyLevel && opts.urgencyClass && opts.urgencyLabel) {
            /* 2026-11-07 (user law): urgency measures the PROPOSED replacement,
               not the original class. Pages pass the replacement date on its own
               or via this fallback (the request-approval page's rows carry both). */
            var level = opts.urgencyLevel(r.replacementDate || r.classDate);
            var urgencyTipAttr = (opts.urgencyTip && opts.urgencyTip(r)) ? ' data-tip="' + opts.urgencyTip(r) + '"' : '';
            urgencyHtml = '<span class="urgency-badge ' + opts.urgencyClass(level) + '"' + urgencyTipAttr + '>' + opts.urgencyLabel(level) + '</span>';
            urgencyText = opts.urgencyLabel(level);
        }
        var ageHtml = opts.requestAgeHtml ? opts.requestAgeHtml(r.requestedAt, urgencyText) : '';
        var statusTip = (opts.statusTip && opts.statusTip(r.status)) ? ' data-tip="' + opts.statusTip(r.status) + '"' : '';
        return '<div class="card-header">' +
            '<span class="card-code">#' + r.id + '</span>' +
            '<span class="badge ' + statusClass(r.status) + '"' + statusTip + '>' + r.status + '</span>' +
            '</div>' +
            '<div class="card-body">' +
            '<div><strong>Course:</strong> ' + r.courseCode + ' - ' + r.courseName + '</div>' +
            '<div><strong>Date:</strong> ' + DateHelper.formatDate(r.classDate) + ', ' + DateHelper.to12h(r.timeStart) + '</div>' +
            '<div><strong>Lecturer:</strong> ' + lecturerHtml + '</div>' +
            (urgencyHtml ? '<div><strong>Urgency:</strong> ' + urgencyHtml + '</div>' : '') +
            (ageHtml ? '<div>' + ageHtml + '</div>' : '') +
            '</div>';
    }

    static myRequestCard(r, opts) {
        var typeLabel = UiLabels.classType(r.classType, 'long');
        var dayStr = DateHelper.dayAbbr(r.classDay);
        var dateStr = DateHelper.formatDate(r.classDate);
        var timeStr = DateHelper.to12h(r.timeStart) + ' ~ ' + DateHelper.to12h(r.timeEnd);
        var ageHtml = opts.requestAgeHtml ? opts.requestAgeHtml(r.requestedAt) : '';
        var statusTip = (opts.statusTip && opts.statusTip(r.status)) ? ' data-tip="' + opts.statusTip(r.status) + '"' : '';
        return '<div class="card-header">' +
            '<span class="card-code">' + r.courseCode + ' (' + typeLabel + ')</span>' +
            '<span class="badge ' + statusClass(r.status) + '"' + statusTip + '>' + r.status + '</span>' +
            '</div>' +
            '<div class="card-body">' +
            '<strong>' + r.courseName + '</strong><br>' +
            dayStr + ', ' + dateStr + '<br>' +
            timeStr + ' · ' + r.venue +
            '</div>' +
            '<div class="card-footer">' +
            ageHtml +
            '<span>' + r.cohorts.join(', ') + '</span>' +
            '</div>';
    }

    static replacementHomeRow(c, opts) {
        var typeLabel = UiLabels.classType(c.type, 'code');
        /* replacement-merge-one-table (M4, 2026-11-04): the merged page's row is
           SEVEN cells — course, cohort, original class, reason, requested
           replacement, status, action. The four page-owned cells arrive as opts
           callbacks, so the page keeps every piece of its own wording. Today's
           nine-cell body below stays byte-identical for the frozen mock
           template, which never passes `merged`. */
        if (opts.merged) {
            return [
                {
                    html: '<span class="cell-code">' + c.code + '</span>' +
                        '<span class="cell-name">' + c.name + ' <span class="cell-type-label">(' + typeLabel + ')</span></span>',
                    cls: 'col-code'
                },
                { html: opts.cohortsCell ? opts.cohortsCell(c) : c.cohorts.join('<br>'), cls: 'col-cohort' },
                { html: opts.formatClassBlock(c), cls: 'col-original' },
                { html: opts.reasonCell(c), cls: 'col-reason' },
                { html: opts.requestCell(c), cls: 'col-requested' },
                { html: opts.statusCell(c), cls: 'col-status' },
                { html: opts.actionCell(c), cls: 'col-action' }
            ];
        }
        var days = opts.daysLeft(c.date);
        var urgencyCls = opts.urgencyClass(days);
        /* replacement-home-status-badges D7 (2026-11-03): a HANDLED row shows an
           em dash; the page's sort key still reads the real date, so the order
           does not jump when a row becomes handled. `opts.isHandled` is optional
           — the legacy mock template keeps today's output. */
        /* 2026-11-07 (working-days + countdown placement): the countdown is hidden
           on a row whose class date no longer matters — approved / completed.
           `hideCountdown` supersedes `isHandled` here; the legacy/mock callers
           that only pass `isHandled` keep their exact behaviour. */
        var hideDays = opts.hideCountdown
            ? opts.hideCountdown(c)
            : (opts.isHandled ? opts.isHandled(c) : false);
        var daysText = hideDays ? '\u2014' : daysLeftLabel(days);
        /* D1/D2: the page supplies the Action cell (badge / label+button); the
           default stays today's Arrange button for the mock template. */
        var actionHtml = opts.actionCell
            ? opts.actionCell(c)
            : '<button class="btn-action" onclick="event.stopPropagation(); goToReplacementFor(\'' + c.sessionId + '\',\'' + c.week + '\', \'replacement-home\')"><svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg> Arrange Replacement</button>';
        return [
            { html: opts.index, cls: 'col-no' },
            { html: '<span class="cell-code">' + c.code + '</span><span class="cell-name">' + c.name + ' <span class="cell-type-label">(' + typeLabel + ')</span></span>', cls: 'col-code' },
            { html: opts.formatClassBlock(c), cls: 'col-original' },
            { html: '<span class="' + urgencyCls + '">' + daysText + '</span>', cls: 'col-urgency' },
            { html: c.venue, cls: 'col-venue' },
            { html: String(c.totalStudents), cls: 'col-students' },
            { html: c.cohorts.join('<br>'), cls: 'col-cohort' },
            { html: '<span class="badge ' + opts.badgeClass(c.conflictReason) + '">' + c.conflictReason + '</span>', cls: 'col-reason' },
            { html: actionHtml, cls: 'col-action' }
        ];
    }

    static replacementHomeCard(c, opts) {
        var typeLabel = UiLabels.classType(c.type, 'long');
        var days = opts.daysLeft(c.date);
        var urgencyCls = opts.urgencyClass(days);
        /* D5/D7 (optional, so the mock template is untouched): the request
           badge rides the header and a handled row's days pill shows an em dash. */
        var statusBadge = opts.statusBadge ? opts.statusBadge(c) : '';
        var hideDays = opts.hideCountdown
            ? opts.hideCountdown(c)
            : (opts.isHandled && opts.isHandled(c));
        var daysText = hideDays ? '\u2014' : daysLeftLabel(days);
        /* the merged page supplies the composed text ("Public Holiday — Christmas
           Day" / "Class Cancelled — Annual Leave"); legacy and mock callers omit
           the hook and keep the raw reason. */
        return '<div class="rc-header">' +
            '<span class="rc-code">' + c.code + ' <span class="cell-type-label">(' + typeLabel + ')</span></span>' +
            statusBadge +
            /* 2026-11-11 (user): the badge names only the kind; `reasonTip` carries
               the detail (a holiday's name / the cancellation reason) into the same
               hover the desktop table uses. Pages that pass no tip keep no tip. */
            '<span class="badge ' + opts.badgeClass(c.conflictReason) + '"' +
                ((opts.reasonTip && opts.reasonTip(c))
                    ? ' data-tip="' + String(opts.reasonTip(c)).replace(/"/g, '&quot;') + '"'
                    : '') +
                '>' + (opts.reasonText ? opts.reasonText(c) : c.conflictReason) + '</span>' +
            '</div>' +
            '<div class="rc-body">' +
            '<strong>' + c.name + '</strong><br>' +
            c.day + ', ' + DateHelper.formatDate(c.date) + ' · Week ' + getWeekNumber(c.date) + '<br>' +
            DateHelper.to12h(c.timeStart) + ' ~ ' + DateHelper.to12h(c.timeEnd) + ' · ' + c.venue +
            '</div>' +
            '<div class="rc-footer">' +
            '<span class="' + urgencyCls + '">' + daysText + '</span>' +
            '<span>' + c.cohorts.join(', ') + '</span>' +
            '</div>';
    }

    static rowTip(/* ...parts */) {
        return Array.prototype.filter
            .call(arguments, function(p) { return p != null && p !== ''; })
            .join(' | ');
    }

    static tipAttr(tooltipStr) {
        return ' data-tip="' + tooltipStr.replace(/"/g, '&quot;') + '"';
    }
}

// ───── Skeleton Loading ─────

const SkeletonLoader = {
    show(container, type = 'rows', count = 5) {
        container.innerHTML = '';
        const isTbody = container.tagName === 'TBODY';
        for (let i = 0; i < count; i++) {
            if (isTbody) {
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 9;
                td.innerHTML = '<div class="skeleton skeleton-row"></div>';
                tr.appendChild(td);
                container.appendChild(tr);
            } else {
                const el = document.createElement('div');
                el.className = `skeleton skeleton-${type === 'rows' ? 'row' : 'card'}`;
                container.appendChild(el);
            }
        }
    },
    hide(container) {
        if (!container) return;
        container.querySelectorAll('.skeleton, .skeleton-row, .skeleton-card').forEach(el => el.remove());
        container.querySelectorAll('tr').forEach(tr => {
            if (tr.querySelector('.skeleton')) tr.remove();
        });
    },
    with(callback, container, count = 10, delay = 400) {
        this.show(container, 'rows', count);
        const hide = () => setTimeout(() => this.hide(container), delay);
        setTimeout(() => {
            try {
                const result = callback();
                if (result && typeof result.then === 'function') {
                    return result.then(hide, (err) => { hide(); throw err; });
                }
                hide();
            } catch (err) { hide(); throw err; }
        }, 50);
    },
    showSummary() {
        document.querySelectorAll('.summary-card .summary-value').forEach(el => {
            el.dataset.original = el.innerHTML;
            el.innerHTML = '<div class="skeleton" style="height:24px;width:40px;display:inline-block"></div>';
        });
    },
    hideSummary() {
        document.querySelectorAll('.summary-card .summary-value').forEach(el => {
            delete el.dataset.original;
        });
    }
};

// ───── Scroll Restoration ─────

function saveScrollPosition(key) {
    sessionStorage.setItem('scroll_' + key, window.scrollY);
}

function restoreScrollPosition(key) {
    const pos = sessionStorage.getItem('scroll_' + key);
    if (pos) window.scrollTo(0, parseInt(pos));
}

function clearScrollPosition(key) {
    sessionStorage.removeItem('scroll_' + key);
}

function initScrollRestore(pageKey) {
    window.addEventListener('pageshow', (e) => {
        if (e.persisted) restoreScrollPosition(pageKey);
    });
    document.querySelectorAll('.nav-item, .nav-drawer-item').forEach(link => {
        link.addEventListener('click', () => clearScrollPosition(pageKey));
    });
    window._scrollToTop = function() { window.scrollTo(0, 0); };
}

// Auto-save on scroll (debounced)
let scrollTimer;
window.addEventListener('scroll', () => {
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
        const pageKey = document.body.dataset.page;
        if (pageKey) saveScrollPosition(pageKey);
    }, 200);
}, { passive: true });

// ───── ToastManager (singleton class) ─────

class ToastManager {
    constructor() {
        this._timer = null;
        this._current = null;
    }

    /* Plain variant — unchanged contract (every existing positional caller
       keeps working); delegates to the shared presenter with asHtml=false. */
    show(message, undoCallback, duration = 5000, linkText = '', linkUrl = '', details = '', onManualDismiss = null, onAutoDismiss = null) {
        this._present(message, { undoCallback: undoCallback, duration: duration, linkText: linkText, linkUrl: linkUrl, details: details, onManualDismiss: onManualDismiss, onAutoDismiss: onAutoDismiss }, false);
    }

    /* Rich variant (user's UI directive 2026-10-24): renders the message as
       HTML so keyword spans can carry the theme tokens (CAP + bold + color —
       var(--color-on-error/primary/success-container) only, NEVER hex).
       CALLERS MUST pass developer-authored CONSTANT strings — never user data. */
    showRich(message, duration = 5000) {
        this._present(message, { duration: duration }, true);
    }

    _present(message, opts, asHtml) {
        const bar = document.getElementById('toastBar');
        if (!bar) return;

        this._current = { message: message, onManualDismiss: opts.onManualDismiss || null, onAutoDismiss: opts.onAutoDismiss || null };

        const undoCallback = opts.undoCallback;
        const duration = opts.duration || 5000;
        const linkText = opts.linkText || '';
        const linkUrl = opts.linkUrl || '';
        const details = opts.details || '';

        const msgEl = bar.querySelector('.toast-message');
        const detailsEl = bar.querySelector('.toast-details');
        const undoBtn = bar.querySelector('.toast-undo');
        const linkEl = bar.querySelector('.toast-link');

        if (asHtml) msgEl.innerHTML = message; else msgEl.textContent = message;

        if (details && detailsEl) {
            detailsEl.textContent = details;
            detailsEl.style.display = 'block';
        } else if (detailsEl) {
            detailsEl.style.display = 'none';
        }

        if (undoCallback) {
            undoBtn.style.display = 'inline-block';
            undoBtn.onclick = () => {
                /* Dismiss FIRST (U1, 2026-10-08): callbacks commonly show
                   their own confirmation toast — dismissing AFTER the callback
                   wiped the callback's bar ~0 ms after it appeared, so the
                   user saw the toast vanish with no feedback. */
                this.dismiss();
                undoCallback();
            };
        } else {
            /* A5 (2026-10-11 user decision): a toast without an undo callback
               must not offer a dead Undo — hide it AND strip any stale
               handler so the affordance is inert by construction. */
            undoBtn.style.display = 'none';
            undoBtn.onclick = null;
        }

        if (linkText && linkUrl && linkEl) {
            linkEl.textContent = linkText;
            linkEl.href = linkUrl;
            linkEl.style.display = 'inline-block';
        } else if (linkEl) {
            linkEl.style.display = 'none';
        }

        bar.classList.add('visible');

        clearTimeout(this._timer);
        this._timer = setTimeout(() => {
            this.dismiss();
            /* Full display = seen (2026-10-08 decision): a toast that survived
               its whole duration retires itself via onAutoDismiss (e.g. the
               cancellation undo toast snoozes). Manual ✕ goes through close()
               instead; navigating away early kills the timer — no hook, and
               the toast legitimately re-shows on the next load. */
            const hook = this._current && this._current.onAutoDismiss;
            if (hook) hook();
        }, duration);
    }

    dismiss() {
        const bar = document.getElementById('toastBar');
        if (bar) bar.classList.remove('visible');
        clearTimeout(this._timer);
    }

    /* Manual close (the ✕ in the layout toast bar). Unlike the auto-timeout
       dismiss(), this fires the current toast's onManualDismiss hook so a
       dismissal can have consequences (e.g. snoozing the cancellation toast). */
    close() {
        this.dismiss();
        const hook = this._current && this._current.onManualDismiss;
        if (hook) hook();
    }
}

const toast = new ToastManager();

// ───── State Persistence (localStorage) ─────

/**
 * Generic state persistence helper.
 * @param {string} storageKey - localStorage key
 * @param {object} config - { fields: [{id, type, key, transform?}] }
 *   type: 'select' | 'checkbox-group' | 'variable'
 *   id: DOM element id (for select/checkbox-group)
 *   key: property name in saved state
 *   transform: optional fn(val) => savedValue
 */
function createStatePersistence(storageKey, config) {
    return {
        save(extraFields) {
            try {
                const state = {};
                config.fields.forEach(f => {
                    if (f.type === 'select') {
                        const el = document.getElementById(f.id);
                        state[f.key] = f.transform ? f.transform(el.value) : el.value;
                    } else if (f.type === 'checkbox-group') {
                        const checkboxes = document.querySelectorAll(f.selector || '#' + f.id + ' input[type="checkbox"]');
                        const vals = {};
                        checkboxes.forEach(cb => { vals[cb.value] = cb.checked; });
                        state[f.key] = vals;
                    } else if (f.type === 'variable') {
                        state[f.key] = window[f.varName];
                    }
                });
                if (extraFields) Object.assign(state, extraFields);
                localStorage.setItem(storageKey, JSON.stringify(state));
            } catch (e) { /* ignore */ }
        },
        restore(defaults) {
            let state = null;
            try { state = JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch (e) { state = null; }
            if (!state) return defaults || {};

            config.fields.forEach(f => {
                if (state[f.key] === undefined) return;
                if (f.type === 'select') {
                    const el = document.getElementById(f.id);
                    if (el) el.value = state[f.key];
                } else if (f.type === 'checkbox-group') {
                    const checkboxes = document.querySelectorAll(f.selector || '#' + f.id + ' input[type="checkbox"]');
                    checkboxes.forEach(cb => {
                        cb.checked = state[f.key][cb.value] !== false;
                    });
                } else if (f.type === 'variable') {
                    window[f.varName] = state[f.key];
                }
            });

            return state;
        }
    };
}



const dayNames = ['MONDAY', 'TUESDAY', 'WEDNESDAY', 'THURSDAY', 'FRIDAY', 'SATURDAY', 'SUNDAY'];

// ───── Week Filter Navigation ─────

/**
 * Handle week filter change. Pages may override the rebuild behaviour by
 * passing an options object with `onRebuild` (e.g. renderTable) and
 * `onBeforeRebuild` (e.g. saveFilters / reset page state).
 * Falling back to globals keeps the simple `<select onchange="weekFilterChanged(this.value)">`
 * working for HOME.
 * @param {object|*} opts - Options object, or ignored value for simple usage
 */
function weekFilterChanged(opts) {
    if (opts && typeof opts === 'object') {
        if (typeof opts.onBeforeRebuild === 'function') opts.onBeforeRebuild();
        if (typeof opts.onRebuild === 'function') {
            opts.onRebuild();
        } else if (typeof buildTable === 'function') {
            buildTable();
        } else if (typeof renderTable === 'function') {
            renderTable();
        }
    } else {
        if (typeof pageState !== 'undefined' && pageState) pageState.currentPage = 1;
        if (typeof buildTable === 'function') {
            buildTable();
        } else if (typeof renderTable === 'function') {
            renderTable();
        }
    }
    updateWeekArrowState();
}

function prevWeekFilter(selectId) {
    const sel = document.getElementById(selectId || 'weekFilter');
    if (!sel) return; /* page has no week filter — its own keyboard nav applies */
    if (sel.selectedIndex > 0) {
        sel.selectedIndex--;
        sel.dispatchEvent(new Event('change'));
    }
}

function nextWeekFilter(selectId) {
    const sel = document.getElementById(selectId || 'weekFilter');
    if (!sel) return;
    if (sel.selectedIndex < sel.options.length - 1) {
        sel.selectedIndex++;
        sel.dispatchEvent(new Event('change'));
    }
}

// ── Promoted from my-request-history (shared helpers) ──

let _weekRanges = null;
function buildWeekRanges() {
    const monthMap = { 'Jan': 0, 'Feb': 1, 'Mar': 2, 'Apr': 3, 'May': 4, 'Jun': 5, 'Jul': 6, 'Aug': 7, 'Sep': 8, 'Oct': 9, 'Nov': 10, 'Dec': 11 };
    function parseDate(s) {
        const p = s.trim().split(' ');
        return new Date(parseInt(p[2]), monthMap[p[1]], parseInt(p[0]));
    }
    const data = generateWeekData();
    return data.map(function(w, i) {
        const parts = w.range.split(' ~ ');
        const start = parseDate(parts[0]);
        const end = parseDate(parts[1]);
        const iso = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        return {
            value: String(i + 1),
            label: w.label + ' \u00b7 ' + w.range,
            labelShort: w.label + ' \u00b7 ' + w.rangeShort,
            start: iso(start),
            end: iso(end),
        };
    });
}
Object.defineProperty(window, 'weekRanges', {
    get: function() {
        if (!_weekRanges) _weekRanges = buildWeekRanges();
        return _weekRanges;
    }
});

function statusClass(status) {
    const map = {
        'Pending': 'status-pending',
        'Approved': 'status-approved',
        'Rejected': 'status-rejected',
        'Cancelled': 'status-cancelled',
        'Completed': 'status-completed'
    };
    return map[status] || '';
}

/**
 * Build HTML for a request-age indicator (shared: my-request-history, request-approval).
 * Age is computed relative to ACTUAL today (the mock data is generated relative
 * to `new Date()`), so "yesterday" shows as Yesterday, not a drift from a fixed date.
 *
 * Color legend:
 *   Today / Yesterday (≤1 day)  → age-fresh  (primary / green)
 *   2–3 days                    → age-waiting (tertiary / amber)
 *   4+ days                     → age-stale  (error / red)
 *
 * Uses `data-tip` (rendered above the element by initDataTipTooltips) instead of
 * the native `title`, which browsers draw below.
 * @param {string} requestedAt - ISO/timestamp string of when the request was made
 * @param {string} [urgencyLabel] - Optional "Urgent"/"Normal" to surface in the tooltip
 * @returns {string} HTML string
 */
function requestAgeHtml(requestedAt, urgencyLabel) {
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    const req = new Date(requestedAt);
    if (isNaN(req.getTime())) return '<div class="request-age request-age--unknown">—</div>';
    req.setHours(0, 0, 0, 0);
    const diff = Math.round((now.getTime() - req.getTime()) / 86400000);
    if (diff < 0) return '<div class="request-age request-age--unknown">—</div>';
    let label;
    if (diff === 0) {
        label = 'Today';
    } else if (diff === 1) {
        label = 'Yesterday';
    } else {
        label = diff + ' days ago';
    }
    const cls = diff <= 1 ? 'age-fresh' : diff <= 3 ? 'age-waiting' : 'age-stale';
    const tip = urgencyLabel
        ? label + ' · Urgency: ' + urgencyLabel
        : label + ' · ' + (cls === 'age-fresh' ? 'recently submitted' : cls === 'age-waiting' ? '2–3 days old' : '4+ days old');
    return '<div class="request-age ' + cls + '" data-tip="' + tip + '">' + label + '</div>';
}


function getWeekRange(weekVal) {
    const found = weekRanges.find(function(w) { return w.value === weekVal; });
    return found || null;
}

function isInWeek(classDate, weekVal) {
    if (weekVal === 'all') return true;
    const range = getWeekRange(weekVal);
    if (!range) return true;
    return classDate >= range.start && classDate <= range.end;
}

function getWeekNumber(iso) {
    for (var i = 0; i < weekRanges.length; i++) {
        if (iso >= weekRanges[i].start && iso <= weekRanges[i].end) return weekRanges[i].value;
    }
    return '';
}

/**
 * Populate a week <select> with options from weekRanges or weekData.
 * @param {string} selectId - ID of the <select> element
 * @param {object} cfg - { includeAll:bool, ranges:bool, selected:value, labelFn }
 */
function populateWeekSelect(selectId, cfg) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    cfg = cfg || {};
    const isMobile = window.innerWidth <= 768;
    const useRanges = cfg.ranges !== false; // default: weekRanges (string "1".."14")
    const source = useRanges ? weekRanges : weekData;

    /* remember this select's cfg so a runtime breakpoint-crossing (768) can
       re-format labels (full ↔ compact) without a reload — same spirit as the
       venue mobileCardList resize hook. Re-runs preserve the selection. */
    populateWeekSelect._registry = populateWeekSelect._registry || {};
    populateWeekSelect._registry[selectId] = cfg;
    populateWeekSelect._mobileState = populateWeekSelect._mobileState || {};
    populateWeekSelect._mobileState[selectId] = isMobile;
    if (!populateWeekSelect._resizeWired) {
        populateWeekSelect._resizeWired = true;
        var _pwTimer;
        window.addEventListener('resize', function() {
            clearTimeout(_pwTimer);
            _pwTimer = setTimeout(function() {
                var mobileNow = window.innerWidth <= 768;
                Object.keys(populateWeekSelect._registry).forEach(function(id) {
                    var el = document.getElementById(id);
                    if (!el || populateWeekSelect._mobileState[id] === mobileNow) return;
                    var preserve = el.value;
                    populateWeekSelect(id, populateWeekSelect._registry[id]);
                    el.value = preserve; /* cfg.selected may be stale after re-populate */
                });
            }, 200);
        });
    }

    let html = '';
    /* 2026-11-07 (user): the "all" option names WHICH date the weeks refer to —
       `All Original Class Weeks` on My Replacements, `All Requested Replacement
       Weeks` on the PL queue. Default stays `All Weeks` for other callers. */
    if (cfg.includeAll) html += '<option value="all">' + (cfg.allLabel || 'All Weeks') + '</option>';

    /* optional visibility filter (weekData path only): only weeks passing it
       become options, keeping ABSOLUTE week numbers as values so saved
       positions, URL params and cross-page references stay meaningful */
    const filter = (cfg.weekFilter && !useRanges) ? cfg.weekFilter : null;
    const list = filter
        ? source.map(function(w, i) { return { w: w, i: i }; }).filter(function(x) { return filter(x.i); })
        : source;

    list.forEach(function(x, i) {
        const w = list === source ? x : x.w;
        const value = useRanges ? w.value : (list === source ? i : x.i);
        let label;
        if (cfg.labelFn) {
            label = cfg.labelFn(w, value, isMobile, useRanges);
        } else if (useRanges) {
            label = isMobile ? (w.labelShort || w.label) : w.label;
        } else {
            label = isMobile ? (w.label + ' \u00B7 ' + w.rangeShort) : (w.label + ' \u00B7 ' + w.range);
        }
        html += '<option value="' + value + '">' + label + '</option>';
    });

    sel.innerHTML = html;
    if (cfg.selected !== undefined) {
        sel.value = String(cfg.selected);
    }
    return sel;
}

/* ── Reading a week <select> (2026-11-11, my-replacements report) ────────
   The week selects are built by JavaScript, so a page re-render can leave one
   momentarily UNSELECTED (value '', selectedIndex -1). Three readers used to
   treat that as a real week: the chip printed a bare label ("Original Slot
   Week: " with nothing after it), the table filter matched nothing (0 rows) and
   the ‹ › arrows did nothing. An empty value is NOT a week — it means "no week
   filter" — so one reader and one labeller own the decision now:
     · weekFilterValue(id) → the value, or 'all' when the select is unselected
     · weekFilterLabel(id) → the option's own text, else the weekRanges label
                             for that value (mobile labelFn respected first)   */
function weekFilterValue(selectId) {
    const sel = document.getElementById(selectId || 'weekFilter');
    const v = sel ? String(sel.value || '') : '';
    return v || 'all';
}

function weekFilterLabel(selectId) {
    const sel = document.getElementById(selectId || 'weekFilter');
    if (!sel) return '';
    const opt = sel.selectedOptions && sel.selectedOptions[0];
    if (opt && String(opt.textContent || '').trim()) return opt.textContent;
    const list = (typeof weekRanges !== 'undefined' && weekRanges) || [];
    const hit = list.find(function (w) { return String(w.value) === String(sel.value); });
    return hit ? hit.label : '';
}

/* ── A JS-owned week <select> must survive a Livewire round-trip ─────────
   Reported 2026-11-06: on request-approval-ui after Approve/Reject and on
   my-replacements after Cancel, the Week dropdown went blank while its filter
   chip stayed, printing a bare 'Week: '.

   Cause: the week selects have NO server-side options — every page builds them
   with populateWeekSelect() from weekRanges / weekData. A page component that
   re-renders morphs its DOM from the server's HTML, where that <select> is
   empty, so the options are deleted and the value collapses to ''.

   Fix: the value is snapshotted before the round-trip and the options + value
   are put back after it. Two entry points, because the timing differs:
     • withWeekLivewireGuard() wraps pageLivewire()'s call(), so the restore
       lands BEFORE the caller's .then() — which is what rebuilds the filter
       chips (they read the select's value).
     • the 'commit' hook is the net for every other round-trip (wire:click and
       friends) that re-renders a page component.
   Only the JS-built options and the value are restored: attributes still morph,
   so cohort-timetable's server-driven `disabled` keeps working. */
function snapshotWeekSelects() {
    window.__weekSelectKeep = {};
    Object.keys(populateWeekSelect._registry || {}).forEach(function (id) {
        const el = document.getElementById(id);
        if (el) window.__weekSelectKeep[id] = el.value;
    });
}

function restoreWeekSelects() {
    const kept = window.__weekSelectKeep;
    if (!kept) return;
    Object.keys(kept).forEach(function (id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (el.options.length === 0) {
            populateWeekSelect(id, populateWeekSelect._registry[id] || {});
        }
        const want = kept[id];
        const has = !!want && Array.prototype.some.call(el.options, function (o) { return o.value === want; });
        if (has) { el.value = want; return; }
        /* 2026-11-11: the selection can be lost even when the OPTION LIST is
           intact, and a lost selection used to be left alone (selectedIndex -1),
           which the page then read as "the empty week": a blank chip, an empty
           table and dead ‹ › arrows. Nothing to restore → settle on the neutral
           'all' option, else the first one. */
        if (el.selectedIndex < 0) {
            const allOpt = Array.prototype.find.call(el.options, function (o) { return o.value === 'all'; });
            if (allOpt) el.value = 'all';
            else if (el.options.length) el.selectedIndex = 0;
        }
    });
    window.__weekSelectKeep = null;
}

/** Wrap a $wire so `.call()` brackets itself with the week-select guard. */
function withWeekLivewireGuard(api) {
    if (!api || typeof api.call !== 'function' || typeof Proxy !== 'function') return api;
    const guardedCall = function () {
        snapshotWeekSelects();
        const promise = api.call.apply(api, arguments);
        if (promise && typeof promise.then === 'function') {
            return promise.then(function (result) {
                restoreWeekSelects();
                return result;
            });
        }
        restoreWeekSelects();
        return promise;
    };
    return new Proxy(api, {
        get: function (target, prop) {
            if (prop === 'call') return guardedCall;
            const value = target[prop];
            return typeof value === 'function' ? value.bind(target) : value;
        },
    });
}

(function wireWeekSelectGuard() {
    function wire() {
        if (!window.Livewire || typeof window.Livewire.hook !== 'function') return;
        window.Livewire.hook('commit', function (payload) {
            snapshotWeekSelects();
            if (payload && typeof payload.succeed === 'function') {
                payload.succeed(function () {
                    restoreWeekSelects();
                    setTimeout(restoreWeekSelects, 0); // second, idempotent pass
                });
            }
            if (payload && typeof payload.fail === 'function') {
                payload.fail(function () { window.__weekSelectKeep = null; });
            }
        });
    }
    if (window.Livewire && typeof window.Livewire.hook === 'function') wire();
    else document.addEventListener('livewire:init', wire);
})();

/* The PL nav badge count — REAL-state-first (SDD span-aware-approval-flow
   user report 2026-10-18): the layout's nav partial renders the server count
   into `window.PENDING_NAV_COUNT`; the approval page can pass a fresh count
   after a decision (the wire response's own row set). The MockData fallback
   remains ONLY for untouched-era streams (a mock page without the partial's
   count never renders a fake number anymore). */
function updateNavBadge(count) {
    if (count === undefined || count === null) {
        if (typeof window.PENDING_NAV_COUNT === 'number') {
            count = window.PENDING_NAV_COUNT;
        } else if (window.MockData && MockData.approvalRequests) {
            count = MockData.approvalRequests.filter(function(r) { return r.status === 'Pending'; }).length;
        } else {
            count = 0;
        }
    }
    var badge = document.getElementById('navPendingBadge');
    if (badge) {
        badge.textContent = String(count);
        badge.style.display = count > 0 ? 'inline-block' : 'none';
    }
}

/**
 * Rebuild a table's body with the standard skeleton-loading recipe:
 * reset page → scroll to top → show skeleton → render → hide skeleton.
 * @param {object} cfg
 * @param {function} cfg.render - Function that renders the table body
 * @param {function} [cfg.after] - Extra logic to run after render (e.g. updateWeekArrowState)
 * @param {string} [cfg.bodyId='tableBody'] - tbody element ID
 * @param {number} [cfg.count=10] - Skeleton row count
 * @param {number} [cfg.delay=400] - Skeleton delay
 */
function rebuildTable(cfg) {
    if (typeof pageState !== 'undefined' && pageState) pageState.currentPage = 1;
    if (typeof currentPage !== 'undefined') currentPage = 1;
    window.scrollTo(0, 0);
    SkeletonLoader.showSummary();
    SkeletonLoader.with(function() {
        cfg.render();
        SkeletonLoader.hideSummary();
        if (typeof cfg.after === 'function') cfg.after();
    }, document.getElementById(cfg.bodyId || 'tableBody'), cfg.count || 10, cfg.delay || 400);
}

// ───── BackNavigator (dynamic back button) ─────

class BackNavigator {
    static #routes = {
        'replacement-home': '/my-replacements',
        'my-request-history': '/my-replacements',
        'venue-timetable': '/venue-timetable-ui',
        'my-timetable': '/my-timetable-ui',
        'cohort-timetable': '/cohort-timetable'   /* SDD cohort-venue-replace-now T3: the CANONICAL route (routes/web.php :37) — the -ui URL is the temporary :99 redirect, scheduled for removal; adding the canonical row keeps the back-nav immune to that cleanup */
    };

    static getDefault() {
        return '/my-replacements';
    }

    static getBackUrl() {
        var params = new URLSearchParams(window.location.search);
        var from = params.get('from');
        var url = BackNavigator.#routes[from] || BackNavigator.getDefault();
        /* keep the booking context alive across the round-trip: the venue page's
           "Booking for:" banner runs on these params */
        if (from === 'venue-timetable') {
            var keep = [];
            if (params.get('code')) keep.push('code=' + encodeURIComponent(params.get('code')));
            if (params.get('cohort')) keep.push('cohort=' + encodeURIComponent(params.get('cohort')));
            if (keep.length) url += '?' + keep.join('&');
        }
        return url;
    }

    static navigate() {
        window.location.href = BackNavigator.getBackUrl();
    }
}

// ───── Data-Tip Tooltip (above the element, avoids grid-wrapper overflow:hidden) ─────
// Shows a fixed tooltip ABOVE any element carrying a `data-tip` attribute
// (table headers, legend items, icon buttons, etc.). Clamps to the viewport
// so the tip is always fully visible.

function initDataTipTooltips() {
    // Idempotent: the global layout init + any per-page legacy initHeaderTooltips()
    // alias both call this — guard so only ONE tooltip div + listener set is created.
    if (window.__dataTipTooltipsInitialized) return;
    window.__dataTipTooltipsInitialized = true;

    var tip = document.createElement('div');
    tip.className = 'data-tip-tooltip';
    document.body.appendChild(tip);
    /* Focus owns the tooltip while the focused element still carries data-tip;
       a pointer leaving that element must not hide the keyboard tooltip. */
    var focusedTipEl = null;

    function findTip(node) {
        if (!node) return null;
        return node.closest ? node.closest('[data-tip]') : null;
    }
    function hide() {
        tip.style.opacity = '0';
        tip.style.visibility = 'hidden';
    }

    function show(el) {
        var r = el.getBoundingClientRect();
        var tipText = el.getAttribute('data-tip') || '';
        if (!tipText) { hide(); return; }
        tip.textContent = tipText;
        /* SDD tidy-arrangement-cell-tooltips (2026-10-27): the multi-line
           tips (the arrangement's conflict-state header + one block per
           class) render pre-line; every existing single-line tooltip stays
           nowrap byte-for-byte (the global rule is untouched). */
        if (tipText.indexOf('\n') !== -1) {
            tip.classList.add('data-tip-tooltip--multi');
        } else {
            tip.classList.remove('data-tip-tooltip--multi');
        }
        // Force layout so offsetWidth/offsetHeight are accurate (text just set)
        tip.style.visibility = 'hidden';
        tip.style.opacity = '1';
        tip.style.left = '0px';
        tip.style.top = '0px';
        var tw = tip.offsetWidth;
        var th = tip.offsetHeight;
        var left, top;
        if (el.getAttribute('data-tip-pos') === 'left') {
            // Tooltip to the LEFT, vertically centred (top-bar buttons)
            left = r.left - tw - 8;
            top = r.top + (r.height / 2) - (th / 2);
            left = Math.max(4, left);
            top = Math.max(4, top);
        } else {
            left = r.left + (r.width / 2) - (tw / 2);
            left = Math.max(4, Math.min(left, window.innerWidth - tw - 4));
            top = r.top - th - 6;
            if (top < 4) {
                // Not enough room above — show below the element instead
                top = r.bottom + 6;
            }
        }
        tip.style.left = left + 'px';
        tip.style.top = top + 'px';
        tip.style.opacity = '1';
        tip.style.visibility = 'visible';
    }

    document.addEventListener('mouseenter', function(e) {
        var el = findTip(e.target);
        if (el) show(el);
    }, true);

    document.addEventListener('mouseleave', function(e) {
        /* While a data-tip control has keyboard focus, focus owns the tooltip;
           a pointer leaving another control must not hide it. */
        if (focusedTipEl) return;
        if (findTip(e.target)) hide();
    }, true);

    // Safety: hide when the pointer leaves a tip target so stale tips don't linger
    document.addEventListener('mouseout', function(e) {
        if (focusedTipEl) return;
        if (findTip(e.relatedTarget)) return;
        if (findTip(e.target)) hide();
    }, true);

    /* 2026-11-11 (user, "UI Update — Compact Tip Tooltip"): keyboard parity —
       the same data-tip tooltip now shows on focus and hides on blur. */
    document.addEventListener('focusin', function(e) {
        focusedTipEl = findTip(e.target);
        if (focusedTipEl) show(focusedTipEl);
    }, true);

    document.addEventListener('focusout', function(e) {
        var el = findTip(e.target);
        if (!el) return;
        if (focusedTipEl === el) focusedTipEl = null;
        hide();
    }, true);
}

// Back-compat alias: pages that used initHeaderTooltips() before.
function initHeaderTooltips() {
    initDataTipTooltips();
}

/* ═══════════════════════════════════════════════════════════════
   VenueDropdown — nested grouped venue picker
   ═══════════════════════════════════════════════════════════════ */
class VenueDropdown {
    /**
     * @param {HTMLElement} container  — .venue-dd element
     * @param {Object}      opts
     * @param {Array}        opts.venues         — MockData.venues array
     * @param {Function}     opts.onSelect       — callback(code) when venue selected
     * @param {string}       [opts.initialCode]  — pre-selected venue code
     *
     * 2026-11-10 (user, code cleanup): the VENUE FAVOURITES feature is PERMANENTLY
     * REMOVED — no star in any room row, no Favourites group, no toggle, no
     * localStorage key. The leftover star beside B103 came from this component's
     * own `isFav` branch. Recent venues keep their own keys and are untouched.
     * @param {Function}     [opts.filter]       — filter function(v) for venues
     */
    constructor(container, opts) {
        this.container = container;
        this.venues = (opts.venues || []).slice();
        this.onSelect = opts.onSelect || function() {};
        this.recentStorageKey = opts.recentStorageKey || 'venueRecent';
        this.maxRecent = opts.maxRecent || 5;
        /* SDD rearrangement-toolbar-tooltips Batch 2: the arrangement's
           selector-voice trigger tip ('Replacement VENUE Selector: …');
           unset = the historical tip (other consumers byte-identical) */
        this.triggerTipPrefix = opts.triggerTipPrefix || null;
        /* Opt-in page-local trigger label renderer (arrangement uses
           code + seats); unset = every other page's existing label. */
        this.triggerLabel = typeof opts.triggerLabel === 'function' ? opts.triggerLabel : null;
        this.filter = opts.filter || null;
        this.selectedCode = opts.initialCode || null;

        this.trigger = container.querySelector('.venue-dd-trigger');
        this.panel = container.querySelector('.venue-dd-panel');

        this._onDocClick = this._onDocClick.bind(this);
        this._onKeydown = this._onKeydown.bind(this);

        this._buildGroups();
        this._bindEvents();

        if (this.selectedCode) this._updateTrigger();
    }

    /* ── Public API ─────────────────────────────────────────── */

    select(code) {
        /* unknown/stale code (e.g. a favourite left over from older mock data):
           ignore instead of selecting into a phantom venue */
        if (!this.venues.some(v => v.code === code)) return;
        this.selectedCode = code;
        this._updateTrigger();
        this._updateActive();
        this._close();
        this.onSelect(code);
    }

    /**
     * Venues that survive the active filter (or all venues when unfiltered) —
     * used by Ctrl+1/2/3 venue switching and the "fits N students" note.
     */
    getFiltered() {
        return this.filter ? this.venues.filter(this.filter) : this.venues.slice();
    }

    getSelected() {
        return this.selectedCode;
    }

    getRecent() {
        try { return JSON.parse(localStorage.getItem(this.recentStorageKey) || '[]'); }
        catch (e) { return []; }
    }

    updateRecent(code) {
        let recent = this.getRecent();
        recent = recent.filter(c => c !== code);
        recent.unshift(code);
        if (recent.length > this.maxRecent) recent = recent.slice(0, this.maxRecent);
        localStorage.setItem(this.recentStorageKey, JSON.stringify(recent));
    }

    setFilter(filterFn) {
        this.filter = filterFn;
        this._buildGroups();
        this._updateActive();
    }

    destroy() {
        document.removeEventListener('click', this._onDocClick, true);
        document.removeEventListener('keydown', this._onKeydown, true);
    }

    /* ── Build columns ──────────────────────────────────────── */

    _buildGroups() {
        this.panel.innerHTML = '';
        this.columns = [];
        this.activeType = null;
        this.activeBlock = null;
        this.activeFloor = null;
        this.activeUnit = null;

        // Four hierarchy columns: Type | Block | Floor | Room
        for (let i = 0; i < 4; i++) {
            const col = document.createElement('div');
            col.className = 'venue-col';
            this.panel.appendChild(col);
            this.columns.push(col);
        }

        this._buildTypeColumn();
        this._clearColumnsFrom(1);
    }

    _getVenues() {
        return this.filter ? this.venues.filter(this.filter) : this.venues;
    }

    /* Dropdown category: CiscoLab (B006) groups under Lab — the room tooltip
       still reveals its true "Cisco Lab" identity. */
    _typeOf(v) { return v.type === 'CiscoLab' ? 'Lab' : v.type; }

    _clearColumnsFrom(start) {
        for (let i = start; i < this.columns.length; i++) {
            const col = this.columns[i];
            col.innerHTML = '';
            col.classList.remove('visible');
        }
    }

    _addSectionHeader(col, label) {
        const header = document.createElement('div');
        header.className = 'venue-col-header';
        header.textContent = label;
        col.appendChild(header);
    }

    _buildTypeColumn() {
        const col = this.columns[0];
        col.innerHTML = '';
        col.classList.add('visible');
        const venues = this._getVenues();

        /* USER 2026-10-19: Recent is COMMENTED OUT (both pages share this
           component). 2026-11-10 (user): FAVOURITES is not "commented out" any
           more — the feature is PERMANENTLY REMOVED (see the constructor note);
           do not restore the group, the star or the localStorage key. */
        // Recent — expandable 2-level group: Recent › [rooms]
        // if (this.getRecent().some(code => venues.some(v => v.code === code))) {
        //     const recCount = this.getRecent().filter(code => venues.some(v => v.code === code)).length;
        //     const row = this._createParentItem('Recent', { unit: 'recent', tip: 'Recent — ' + recCount + ' venues' });
        //     row.addEventListener('mouseenter', () => this._buildUnitColumn('recent'));
        //     row.addEventListener('click', (e) => {
        //         e.stopPropagation();
        //         this._buildUnitColumn('recent');
        //     });
        //     col.appendChild(row);
        // }

        this._addSectionHeader(col, 'Types');

        if (venues.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'venue-dd-empty';
            empty.textContent = 'No venues available';
            col.appendChild(empty);
            return;
        }

        const typeOrder = ['Tutorial', 'LectureHall', 'Lab'];
        const typeLabels = { Tutorial: 'Tutorial', LectureHall: 'Lecture Hall', Lab: 'Lab' };
        typeOrder.forEach(type => {
            const catVenues = venues.filter(v => this._typeOf(v) === type);
            if (catVenues.length === 0) return;
            const row = this._createParentItem(typeLabels[type] || type, { type, tip: (typeLabels[type] || type) + ' — ' + catVenues.length + ' venues' });
            row.addEventListener('mouseenter', () => this._buildBlockColumn(type));
            row.addEventListener('click', (e) => {
                e.stopPropagation();
                this._buildBlockColumn(type);
            });
            col.appendChild(row);
        });

        this._updateActiveParents();
    }

    _buildUnitColumn(unit) {
        /* only 'recent' remains — the 'favourites' unit was removed 2026-11-10 */
        this.activeType = null;
        this.activeBlock = null;
        this.activeFloor = null;
        this.activeUnit = unit;
        this._clearColumnsFrom(1);
        const col = this.columns[1];
        col.classList.add('visible');
        this._addSectionHeader(col, 'Recent');

        const venues = this._getVenues();
        const codes = this.getRecent();
        const list = codes
            .map(code => venues.find(v => v.code === code))
            .filter(Boolean);

        if (list.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'venue-dd-empty';
            empty.textContent = 'No items';
            col.appendChild(empty);
            return;
        }

        list.forEach(v => col.appendChild(this._createRoomItem(v, 'recent')));
        this._updateActiveParents();
    }

    _buildBlockColumn(type) {
        this.activeType = type;
        this.activeUnit = null;
        this._clearColumnsFrom(1);
        const col = this.columns[1];
        col.classList.add('visible');
        this._addSectionHeader(col, 'Blocks');

        const venues = this._getVenues();
        const catVenues = venues.filter(v => this._typeOf(v) === type);
        const blocks = [...new Set(catVenues.map(v => v.code.charAt(0)))].sort();

        if (blocks.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'venue-dd-empty';
            empty.textContent = 'No blocks';
            col.appendChild(empty);
            return;
        }

        blocks.forEach(block => {
            const inBlock = catVenues.filter(v => v.code.charAt(0) === block).length;
            const row = this._createParentItem('Block ' + block, { block, tip: 'Block ' + block + ' — ' + inBlock + ' venues' });
            row.addEventListener('mouseenter', () => this._buildFloorColumn(type, block));
            row.addEventListener('click', (e) => {
                e.stopPropagation();
                this._buildFloorColumn(type, block);
            });
            col.appendChild(row);
        });

        this._updateActiveParents();
    }

    _buildFloorColumn(type, block) {
        this.activeType = type;
        this.activeBlock = block;
        this._clearColumnsFrom(2);
        const col = this.columns[2];
        col.classList.add('visible');
        this._addSectionHeader(col, 'Floors');

        const venues = this._getVenues();
        const blockVenues = venues.filter(v => this._typeOf(v) === type && v.code.charAt(0) === block);

        // Floor from 2nd char: 0 = Ground, 1+ = Floor N
        const floorMap = {};
        blockVenues.forEach(v => {
            const d = parseInt(v.code.charAt(1));
            const label = d === 0 ? 'Ground Floor' : 'Floor ' + d;
            if (!floorMap[label]) floorMap[label] = [];
            floorMap[label].push(v);
        });
        const floorOrder = Object.keys(floorMap).sort((a, b) => {
            const da = parseInt(a) || 0;
            const db = parseInt(b) || 0;
            return da - db;
        });

        if (floorOrder.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'venue-dd-empty';
            empty.textContent = 'No floors';
            col.appendChild(empty);
            return;
        }

        floorOrder.forEach(floor => {
            const inFloor = blockVenues.filter(v => {
                const d = parseInt(v.code.charAt(1));
                return (d === 0 ? 'Ground Floor' : 'Floor ' + d) === floor;
            }).length;
            const row = this._createParentItem(floor, { floor, tip: floor + ', Block ' + block + ' — ' + inFloor + ' venues' });
            row.addEventListener('mouseenter', () => this._buildRoomColumn(type, block, floor));
            row.addEventListener('click', (e) => {
                e.stopPropagation();
                this._buildRoomColumn(type, block, floor);
            });
            col.appendChild(row);
        });

        this._updateActiveParents();
    }

    _buildRoomColumn(type, block, floor) {
        this.activeType = type;
        this.activeBlock = block;
        this.activeFloor = floor;
        this._clearColumnsFrom(3);
        const col = this.columns[3];
        col.classList.add('visible');
        this._addSectionHeader(col, 'Rooms');

        const venues = this._getVenues();
        const floorVenues = venues.filter(v => {
            if (this._typeOf(v) !== type || v.code.charAt(0) !== block) return false;
            const d = parseInt(v.code.charAt(1));
            const label = d === 0 ? 'Ground Floor' : 'Floor ' + d;
            return label === floor;
        });

        if (floorVenues.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'venue-dd-empty';
            empty.textContent = 'No rooms';
            col.appendChild(empty);
            return;
        }

        floorVenues.forEach(v => col.appendChild(this._createRoomItem(v, 'hierarchy')));
        this._updateActiveParents();
    }

    _createParentItem(label, data) {
        const row = document.createElement('div');
        row.className = 'venue-col-item venue-col-item-parent';
        for (const k in data) row.dataset[k] = data[k];

        const name = document.createElement('span');
        name.className = 'venue-col-item-name';
        name.textContent = label;
        row.appendChild(name);

        const chevron = document.createElement('span');
        chevron.className = 'venue-col-item-chevron';
        chevron.textContent = '›';
        row.appendChild(chevron);

        return row;
    }

    _createRoomItem(v, groupType) {
        const row = document.createElement('div');
        row.className = 'venue-col-item venue-col-item-room';
        row.dataset.code = v.code;
        if (v.code === this.selectedCode) row.classList.add('selected');

        /* Full-name tooltip (shared data-tip utility — fixed, shown ABOVE the
           item): "B006 · Cisco Lab · Ground Floor, Block B". The dropdown
           groups CiscoLab under Lab, so this is where its true name shows. */
        const floorNum = parseInt(v.code.charAt(1));
        const floorLabel = floorNum === 0 ? 'Ground Floor' : 'Floor ' + floorNum;
        const typeName = VenueDropdown.humanizeType(v.type); /* one vocabulary — the trigger label + this tip agree (user 2026-10-27) */
        row.dataset.tip = v.code + ' · ' + typeName + ' · ' + floorLabel + ', Block ' + v.code.charAt(0);

        /* 2026-11-10 (user): no venue favourite star anywhere — the room row
           carries only the Recent clock when it comes from the Recent group */
        if (groupType === 'recent') {
            const icon = document.createElement('span');
            icon.className = 'venue-col-item-icon';
            icon.textContent = '🕐';
            row.appendChild(icon);
        }

        const name = document.createElement('span');
        name.className = 'venue-col-item-name';
        name.textContent = v.code;
        row.appendChild(name);

        const meta = document.createElement('span');
        meta.className = 'venue-col-item-meta';
        meta.textContent = v.capacity + ' seats';
        row.appendChild(meta);

        if (v.code === this.selectedCode) {
            const check = document.createElement('span');
            check.className = 'venue-col-item-check';
            check.textContent = '✓';
            row.appendChild(check);
        }

        row.addEventListener('click', (e) => {
            e.stopPropagation();
            this.select(v.code);
        });

        return row;
    }

    _updateActiveParents() {
        if (!this.columns) return;
        this.columns[0].querySelectorAll('.venue-col-item-parent').forEach(row => {
            if (row.dataset.type) {
                row.classList.toggle('active', row.dataset.type === this.activeType);
            } else if (row.dataset.unit) {
                row.classList.toggle('active', row.dataset.unit === this.activeUnit);
            }
        });
        this.columns[1].querySelectorAll('.venue-col-item-parent').forEach(row => {
            row.classList.toggle('active', row.dataset.block === this.activeBlock);
        });
        this.columns[2].querySelectorAll('.venue-col-item-parent').forEach(row => {
            row.classList.toggle('active', row.dataset.floor === this.activeFloor);
        });
    }

    /* ── Open / Close ───────────────────────────────────────── */

    _open() {
        this.container.classList.add('open');
        // Reset to just the Type column on every open
        this.activeType = null;
        this.activeBlock = null;
        this.activeFloor = null;
        this.activeUnit = null;
        this._clearColumnsFrom(0);
        this._buildTypeColumn();
        document.addEventListener('click', this._onDocClick, true);
        document.addEventListener('keydown', this._onKeydown, true);
    }

    _close() {
        this.container.classList.remove('open');
        document.removeEventListener('click', this._onDocClick, true);
        document.removeEventListener('keydown', this._onKeydown, true);
    }

    _toggle() {
        if (this.container.classList.contains('open')) {
            this._close();
        } else {
            this._open();
        }
    }

    /* ── Event handlers ─────────────────────────────────────── */

    _bindEvents() {
        this.trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            this._toggle();
        });
    }

    _onDocClick(e) {
        if (!this.container.contains(e.target)) {
            this._close();
        }
    }

    _onKeydown(e) {
        if (e.key === 'Escape') this._close();
    }

    /* ── Helpers ────────────────────────────────────────────── */

    /* One vocabulary for a venue type's written name (user 2026-10-27):
       Tutorial → "Tutorial Room", CiscoLab → "Cisco Lab" (a space — the raw
       enum is unreadable), LectureHall → "Lecture Hall", Lab unchanged.
       Static so every surface (trigger label, tips, badges) shares one law. */
    static humanizeType(type) {
        return { Tutorial: 'Tutorial Room', CiscoLab: 'Cisco Lab', LectureHall: 'Lecture Hall' }[type] || type;
    }

    _updateTrigger() {
        /* the chevron is a separate svg (same shared arrow as the week-select);
           only the label span receives text */
        const label = this.trigger.querySelector('.venue-dd-label');
        if (!this.selectedCode) {
            if (label) label.textContent = 'Select a venue';
            else this.trigger.textContent = 'Select a venue';
            this.trigger.removeAttribute('data-tip'); /* nothing to enlarge on */
            return;
        }
        const v = this.venues.find(x => x.code === this.selectedCode);
        if (v) {
            /* venue-trigger-label law (user 2026-10-26 + 2026-10-27), the
               mock-statics-die re-cut: the LABEL names the SHORT form
               (the server-stamped `short_name` — G1(b), the display law
               lives server-side; the venueRealNames JS mirror is DELETED);
               the HOVER tooltip shows the FULL name when it differs from
               the short (`B005 — Computer Laboratory 5 (28 seats)`), and
               the label text verbatim for the code-suffixed rooms (the
               code is never duplicated: 'Tutorial Room B002'). The class
               owns both so pages can't drift apart. */
            const suffixRe = new RegExp('\\s*' + v.code + '\\s*$');
            let base = v.short_name
                || (v.name ? v.name.replace(suffixRe, '').trim() : '')
                || VenueDropdown.humanizeType(v.type);
            const text = v.code + ' \u2014 ' + base;
            if (this.triggerLabel) {
                const html = String(this.triggerLabel(v) || '');
                if (label) label.innerHTML = html;
                else this.trigger.textContent = text; /* legacy markup fallback */
            } else if (label) {
                label.textContent = text;
            } else {
                this.trigger.textContent = text; /* legacy markup fallback */
            }
            // the tooltip: the FULL name when room_name (trimmed) differs
            // from the short base (the labs: name = 'Computer Laboratory 5'
            //   base = 'Computer Lab 5' → the full rides); the code-
            // suffixed rooms: name−suffix === base → the tip = the label.
            const longBase = v.name ? v.name.replace(suffixRe, '').trim() : '';
            const tipBase = (longBase && longBase !== base) ? longBase : base;
            const tipText = v.code + ' \u2014 ' + tipBase;
            /* SDD rearrangement-toolbar-tooltips Batch 2: the arrangement passes
               triggerTipPrefix ('Replacement VENUE Selector: ') → the new
               '… (Capacity: N seats)' identity shape; unset = the historical
               'N seats' shape (venue-timetable byte-identical). */
            this.trigger.setAttribute('data-tip', this.triggerTipPrefix
                ? this.triggerTipPrefix + tipText + ' (Capacity: ' + v.capacity + ' seats)'
                : tipText + ' (' + v.capacity + ' seats)');
        }
    }

    _updateActive() {
        if (!this.columns) return;
        this.columns.forEach(col => {
            col.querySelectorAll('.venue-col-item-room').forEach(row => {
                const isSel = row.dataset.code === this.selectedCode;
                row.classList.toggle('selected', isSel);
                let check = row.querySelector('.venue-col-item-check');
                if (isSel && !check) {
                    check = document.createElement('span');
                    check.className = 'venue-col-item-check';
                    check.textContent = '✓';
                    row.appendChild(check);
                } else if (!isSel && check) {
                    check.remove();
                }
            });
        });
        this._updateActiveParents();
    }
}

// ───── ClassCancellation (cancel-class-enhancement — design §1) ─────

/**
 * Owns the whole cancel-class lifecycle: isCancellable guards (S1–S5),
 * reason validation (S7/S8), twin mutation across myTimetable +
 * cohortTimetable (§3), the sessionStorage ledger (§2) and the cancellation
 * toast (§6). Storage touches are ALL guarded (§10): a throwing sessionStorage
 * degrades to the in-memory mirror (this tab only, documented trade-off —
 * file:// / privacy modes).
 */
const ClassCancellation = {
    /* §1 reason vocabulary — class-cancel-db-persist: the user's 7-leaf
       list + catch-all `other`, each as {value, label} where value is the
       DB enum (class_exceptions.reason CHECK, 10 values) and label is the
       radio text (the frozen proposal vocabulary table). The enum is the
       client store of truth: it rides the ledger's cancelledReason and the
       home's conflictReason (the home's titleCase law renders it).
       public_holiday/official_event stay in the DB CHECK but are never
       offered here. */
    REASONS: [
        { value: 'medical_leave', label: 'Medical/Sick Leave' },
        { value: 'annual_leave', label: 'Annual Leave' },
        { value: 'emergency_leave', label: 'Emergency Leave' },
        { value: 'hospitalisation_leave', label: 'Hospitalisation Leave' },
        { value: 'maternity_leave', label: 'Maternity Leave' },
        { value: 'compassionate_leave', label: 'Compassionate Leave' },
        { value: 'paternity_leave', label: 'Paternity Leave' },
        { value: 'other', label: 'Other' },
    ],
    LEDGER_KEY: 'classCancellationLedger',  // sessionStorage — tab-scope demo reset
    _seq: 0,               // entry-id suffix — no same-millisecond collision
    _MONTHS: ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
    _memLedger: [],        // §10 fallback mirror when storage throws

    /* §3 matching key: 0-indexed week + code + di (0 = Mon) + start
       (half-hour index) + lecturer. A merged-cohort myTimetable block
       intentionally matches SEVERAL cohortTimetable twins (S16). */
    matchKeyOf(event, weekIndex) {
        return { week: weekIndex, code: event.code, di: event.di,
                 start: event.start, lecturer: event.lecturer };
    },

    /* class-cancel-db-persist: enum → the modal label (the radio label is
       the law — 'Medical/Sick Leave' etc.); unknown values pass through. */
    labelOf(value) {
        const r = this.REASONS.find(function(x) { return x.value === value; });
        return r ? r.label : value;
    },

    /* S7/S8 — Yes-button gating: reason required + in the vocabulary;
       'other' demands a non-empty trimmed detail. */
    validate(reason, detail) {
        if (!reason || !this.REASONS.some(function(r) { return r.value === reason; })) {
            /* 2026-11-05 UI/UX update (user): the "Please select a cancellation
               reason." message is GONE from the UI — the gate still refuses, it
               just stays silent (2026-11-10: the static instruction is gone too,
               so the heading's red asterisk and the disabled Yes button are the
               whole cue — still no second sentence here). */
            return { ok: false, hint: '' };
        }
        if (reason === 'other' && !String(detail || '').trim()) {
            /* 2026-11-10 (user, item 5 follow-up): the line `Please describe the
               reason for "Other".` is GONE. The labelled block above the textarea
               already carries `Other reason *` in red-plus-asterisk, and Yes stays
               disabled — a second sentence said the same thing twice. The gate
               still refuses (ok:false); it is silent, exactly like the missing-
               reason arm above. The SAME paragraph still carries the real
               refusals (window / no longer cancellable / server error). */
            return { ok: false, hint: '' };
        }
        return { ok: true, hint: '' };
    },

    /* S1–S5 guards, recomputed per call (cheap; the confirm path re-checks
       at S6): own class + status 'normal' OR 'replacement' (a confirmed
       replacement slot is still the lecturer's own scheduled class — FR
       2.16) + non-holiday, non-Sunday day + the DATE-grain cancellation
       window (S5 — SUPERSEDED by the user law 2026-10-27, lane
       class-cancel-2-working-day-window: the restore-era "END datetime
       strictly in the future" contract is retired; the whole TODAY stays
       cancellable even after its end time — the user's "26th (Today)"
       bullet). */
    isCancellable(event, days, weekIndex) {
        if (!event) return false;
        if (event.lecturer !== MockData.currentUser.name) return false;  // S2
        if (event.status !== 'normal' && event.status !== 'replacement') return false; // S3
        const day = days && days[event.di];
        if (!day || day.holiday || day.sunday || day.abbr === 'Sun') return false; // S4
        /* S5 — the window law (the date-grain swap): cancellable iff the
           class's DATE sits on / after (today − 2 working days). The
           midnight strip keeps the compare DATE-only (a class never spans
           midnight in this grid). */
        const clsEnd = this.endDateTime(event, days, weekIndex);
        if (!clsEnd) return false;
        const clsDateMs = new Date(clsEnd.getFullYear(), clsEnd.getMonth(), clsEnd.getDate()).getTime();
        return clsDateMs >= this._cutoffDate().getTime();                // S5 (the window law)
    },

    /* S5's window origin (SDD class-cancel-2-working-day-window, user law
       2026-10-27, holiday-aware 2026-11-05): midnight of (today − 2 WORKING
       days). Walks back from today's midnight skipping Sat+Sun AND the
       semester's public holidays — the 2026-11-05 UI/UX brief: "exclude
       weekends and applicable public holidays when calculating working
       days". The user's Monday example still holds when no holiday sits in
       the window: the two previous working days are FRI (1 back) and THU
       (2 back), so the cutoff lands Thursday. The S4 guard still blocks
       cancelling ON a holiday independently. Anchored on getTodayMs() (the
       demo-clock law family shared with leadTimeCutoff/isSlotTooSoon; the
       real-clock fallback is exactly what the three cancel consumers run —
       MockData.mockNow = null on all of them). */
    _cutoffDate() {
        const holidays = this._holidayDateKeys();
        const d = new Date(getTodayMs());
        let counted = 0;
        let guard = 0;                     // a broken payload can never hang the page
        while (counted < 2 && guard++ < 60) {
            d.setDate(d.getDate() - 1);
            const dw = d.getDay();
            if (dw === 0 || dw === 6) continue;               // Sun = 0, Sat = 6
            if (holidays.has(this._dateKey(d))) continue;     // public holiday
            counted++;
        }
        return d;
    },

    /* The semester's public-holiday DATES as `y-m-d` keys — ONE derivation for
       the whole page: `workingDayHolidayKeys()` (the countdown's own source)
       also feeds this cancel-class window, so a holiday added to the payload can
       never be a holiday for one rule and an ordinary day for the other.
       2026-11-07 (user decision): the private copy was deleted, not kept in
       step by hand. Same source data as before (MockData.semester.startDate +
       MockData.holidays `{week, dayIndex}`), same unpadded key format as
       `_dateKey()`. */
    _holidayDateKeys() {
        return workingDayHolidayKeys();
    },

    /* One local-date identity for the holiday set and the walk-back. */
    _dateKey(d) {
        return d.getFullYear() + '-' + (d.getMonth() + 1) + '-' + d.getDate();
    },

    /**
     * The class's END datetime as a real Date (the S5/S6 boundary).
     *
     * Parse (deterministic — design §10 pin):
     * - DATE: `days[event.di].date` in the pinned 'DD Mon YYYY' format
     *   produced by generateWeekData's `fmt` ('21 Sep 2026'). Split on
     *   whitespace → [dd, Mon, yyyy]; the month index comes from the same
     *   English short-month vocabulary (_MONTHS) — the string is NEVER
     *   handed to the Date constructor, so no locale/parse anxiety.
     * - TIME: derived EXACTLY like the grid renders it (§1): the end time
     *   string is `hours[event.end + 1] || add30min(hours[event.end])`
     *   over the shared `hours` half-hour array. Those values are 'HH:MM'
     *   24h strings in this dataset ('13:00'); a 12h companion ('1:00 PM')
     *   is tolerated by _parseTime for robustness.
     * Both halves assemble in LOCAL time via new Date(y, mo, dd, hh, mm);
     * callers compare against the real clock.
     * Returns null for a malformed/unmatched day (guards treat null > now
     * as false → not cancellable, never a throw).
     */
    endDateTime(event, days, weekIndex) {
        const day = days && days[event.di];
        if (!day) return null;
        const p = String(day.date).trim().split(/\s+/);
        const mo = this._MONTHS.indexOf(p[1]);
        if (p.length !== 3 || mo < 0) return null;
        const minutes = this._parseTime(hours[event.end + 1] || DateHelper.add30min(hours[event.end]));
        return new Date(parseInt(p[2], 10), mo, parseInt(p[0], 10),
                        Math.floor(minutes / 60), minutes % 60);
    },

    /* 'HH:MM' 24h or 'H:MM AM/PM' 12h → minutes since midnight (0 fallback). */
    _parseTime(t) {
        const m = String(t).trim().match(/^(\d{1,2}):(\d{2})\s*([AaPp][Mm])?$/);
        if (!m) return 0;
        let h = parseInt(m[1], 10);
        const mi = parseInt(m[2], 10);
        if (m[3]) {
            if (/[pP]/.test(m[3]) && h < 12) h += 12;
            if (/[aA]/.test(m[3]) && h === 12) h = 0;
        }
        return h * 60 + mi;
    },

    /* Any accepted time vocabulary → 'HH:MM' 24h (row timeStart/timeEnd). */
    _to24h(t) {
        const minutes = this._parseTime(t);
        return String(Math.floor(minutes / 60)).padStart(2, '0') + ':' +
               String(minutes % 60).padStart(2, '0');
    },

    /* Row-label formatter: 'DFT2(S1)' → 'DFT2 (S1)' and 'RSD2(S1)G2' →
       'RSD2 (S1) G2' — the spacing of the existing conflictedClasses rows
       + faculties cohort labels. Unrecognized strings pass through
       verbatim (also keeps already-formatted row labels intact). */
    formatCohortLabel(c) {
        const m = String(c).trim().match(/^([A-Z]{3}\d)\(S(\d)\)(?:G(\d))?$/);
        if (!m) return String(c).trim();
        return m[1] + ' (S' + m[2] + ')' + (m[3] ? ' G' + m[3] : '');
    },

    /* Row cohort labels: prefer event.cohorts, else split the merged
       'A + B' cohort string some events carry (§2.6 single blocks store
       one cohort in event.cohort only). */
    cohortLabels(event) {
        const raw = Array.isArray(event.cohorts) ? event.cohorts :
            (event.cohort ? String(event.cohort).split(' + ') : []);
        return raw.map(this.formatCohortLabel, this);
    },

    _eventMatches(ev, mk) {
        return !!ev && ev.code === mk.code && ev.di === mk.di &&
               ev.start === mk.start && ev.lecturer === mk.lecturer;
    },

    /* §3 twin matching across BOTH stores (0-indexed weeks on both sides
       — myTimetable.eventsByWeek keys, cohortTimetable item.week). The
       cohort store nests each block in item.event, so resolve/unions take
       {store:'my'|'cohort', eventRef} pairs; multi-match is expected for
       merged-cohort blocks (S16), zero is valid for cohortTimetable-only
       blocks (row totalStudents = 0 per §3). */
    resolveMatches(mk) {
        const out = [];
        if (!mk) return out;
        const week = (MockData.myTimetable && MockData.myTimetable.eventsByWeek)[mk.week];
        if (Array.isArray(week)) {
            week.forEach(function(ev) {
                if (this._eventMatches(ev, mk)) out.push({ store: 'my', eventRef: ev });
            }, this);
        }
        (MockData.cohortTimetable.events || []).forEach(function(item) {
            if (item.week === mk.week && this._eventMatches(item.event, mk)) {
                out.push({ store: 'cohort', eventRef: item.event });
            }
        }, this);
        return out;
    },

    /* ISO 'YYYY-MM-DD' from 0-indexed weekIndex + di via the §2 pin:
       semester.startDate + 7*weekIndex + di days — the SAME math
       generateWeekData uses (index 0 is the Week-1 Monday), NOT the
       'DD Mon YYYY' display string that replacement-home's
       daysLeft()/getWeekNumber() parsers reject. Shared so the cancel
       modal's Arrange-Now URL reuses the one date pin. */
    isoDate(weekIndex, di) {
        const parts = String(MockData.semester.startDate).split('-');
        const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        d.setDate(d.getDate() + weekIndex * 7 + di);
        return d.getFullYear() + '-' +
               String(d.getMonth() + 1).padStart(2, '0') + '-' +
               String(d.getDate()).padStart(2, '0');
    },

    _nextRowId() {
        let maxId = 0;
        (MockData.conflictedClasses || []).forEach(function(r) {
            if (r && r.id > maxId) maxId = r.id;
        });
        return maxId + 1;
    },

    /**
     * §2 row build — a conflictedClasses-shaped row (so replacement-home's
     * existing table/card renderers consume it verbatim):
     * - date: ISO 'YYYY-MM-DD' from the semester start-date math (§2 pin);
     *   day: full name derived from that ISO date (DateHelper.isoDayName);
     * - timeStart/timeEnd: 24h 'HH:MM' from the half-hour indices, using
     *   the same grid vocabulary (hours[i+1] || add30min(hours[end]));
     * - duration: half-hour span → hours ((end - start + 1) / 2);
     * - totalStudents: the twin myTimetable match(es) studentCounts sum
     *   when resolvable, else 0 (§3 — cohort-only blocks stay valid);
     * - cohorts: formatted labels from the event's cohorts; the
     *   row-reason IS the chosen cancellation reason (S13 chip etc.);
     * - id: next free integer (max existing row id + 1).
     */
    rowFromEvent(event, days, weekIndex, reason) {
        let total = 0;
        this.resolveMatches(this.matchKeyOf(event, weekIndex)).forEach(function(m) {
            if (m.store !== 'my') return;
            const ev = m.eventRef;
            if (Array.isArray(ev.studentCounts)) {
                ev.studentCounts.forEach(function(c) { total += (c || 0); });
            } else if (typeof ev.studentCount === 'number') {
                total += ev.studentCount;
            }
        });
        const iso = this.isoDate(weekIndex, event.di);
        return {
            id: this._nextRowId(),
            sessionId: event.id,
            week: weekIndex + 1,
            code: event.code,
            name: event.name,
            type: event.type,
            lecturer: event.lecturer,   // cancelled classes are own by construction — keeps them visible under replacement-home's owner scoping
            date: iso,
            day: DateHelper.isoDayName(iso),
            timeStart: this._to24h(hours[event.start]),
            timeEnd: this._to24h(hours[event.end + 1] || DateHelper.add30min(hours[event.end])),
            duration: (event.end - event.start + 1) / 2,
            venue: event.venue,
            totalStudents: total,
            cohorts: this.cohortLabels(event),
            conflictReason: this.labelOf(reason),   // the home badge EQUALS the modal label (the contract's intent — payload rows carry labels, the ledger keeps the enum)
        };
    },

    /* RAW ledger helpers — every touch try/catch-guarded (§10). */

    ledgerRead() {
        try {
            const raw = sessionStorage.getItem(this.LEDGER_KEY);
            if (raw) {
                const arr = JSON.parse(raw);
                if (Array.isArray(arr)) return arr;
            }
        } catch (e) { /* storage unavailable / malformed — fall through */ }
        return this._memLedger;
    },

    ledgerAppend(entry) {
        this._memLedger.push(entry);
        try {
            const raw = sessionStorage.getItem(this.LEDGER_KEY);
            const arr = raw ? JSON.parse(raw) : [];
            if (!Array.isArray(arr)) arr = [];
            arr.push(entry);
            sessionStorage.setItem(this.LEDGER_KEY, JSON.stringify(arr));
        } catch (e) { /* §10 degrade — the mirror already holds the entry */ }
    },

    ledgerRemove(entry) {
        const id = entry && entry.id;
        this._memLedger = this._memLedger.filter(function(e) { return e.id !== id; });
        try {
            const raw = sessionStorage.getItem(this.LEDGER_KEY);
            const arr = raw ? JSON.parse(raw) : [];
            if (Array.isArray(arr)) {
                const next = arr.filter(function(e) { return e.id !== id; });
                if (next.length !== arr.length) {
                    sessionStorage.setItem(this.LEDGER_KEY, JSON.stringify(next));
                }
            }
        } catch (e) { /* §10 degrade */ }
    },

    /* Replace-by-id with a shallow copy — the caller's object wins, so a
       later consumed:'arranged' (S13b) or chip-flag clear persists
       through replay. */
    ledgerUpdate(entry) {
        const id = entry && entry.id;
        if (!id) return;
        const i = this._memLedger.findIndex(function(e) { return e.id === id; });
        if (i >= 0) this._memLedger[i] = Object.assign({}, entry);
        try {
            const raw = sessionStorage.getItem(this.LEDGER_KEY);
            const arr = raw ? JSON.parse(raw) : [];
            if (Array.isArray(arr)) {
                for (let k = 0; k < arr.length; k++) {
                    if (arr[k].id === id) { arr[k] = Object.assign({}, entry); break; }
                }
                sessionStorage.setItem(this.LEDGER_KEY, JSON.stringify(arr));
            }
        } catch (e) { /* §10 degrade — mirror already updated */ }
    },

    /* S1–S5 + S7/S8 pre-flight — shared by cancel() and the confirm await
       (class-cancel-db-persist): the S6 re-check and the window hint stay
       single-homed; the server bridge only runs after this passes. */
    preflight(event, days, weekIndex, reason, detail) {
        if (!this.isCancellable(event, days, weekIndex)) {
            /* SDD class-cancel-2-working-day-window (D3, review round-1):
               TWO-WAY hint re-keyed on the WINDOW law — the window wording
               iff the class date is genuinely OUTSIDE the window; the
               generic fallback for the S2/S3/S4 failures (e.g. a twin
               already cancelled via ledger replay in another tab) — an
               in-window class must never read a specific false claim. */
            const clsEnd = this.endDateTime(event, days, weekIndex);
            const outside = !clsEnd || (new Date(clsEnd.getFullYear(), clsEnd.getMonth(), clsEnd.getDate()).getTime()
                < this._cutoffDate().getTime());
            return { ok: false,
                     hint: outside
                           ? 'This class is outside the 2-working-day cancellation window.'
                           : 'This class can no longer be cancelled.' };
        }
        const check = this.validate(reason, detail);
        if (!check.ok) return check;

        const mk = this.matchKeyOf(event, weekIndex);
        if (!this.resolveMatches(mk).length) {
            return { ok: false, hint: 'This class is no longer in the timetable.' };
        }
        return { ok: true, hint: '', matchKey: mk };
    },

    /**
     * S9 — re-check guards at CONFIRM time (S6) + reason validation
     * (S7/S8), cancel EVERY resolved twin in place (status 'cancelled' +
     * cancelledReason/cancelledDetail — merged-cohort multi-match gets all
     * of them, S16), insert the row when its id is not already present,
     * write the ledger entry, return {ok:true, entry} or {ok:false, hint}.
     */
    cancel(event, days, weekIndex, reason, detail) {
        const pre = this.preflight(event, days, weekIndex, reason, detail);
        if (!pre.ok) return pre;
        const mk = pre.matchKey;
        const matches = this.resolveMatches(mk);
        matches.forEach(function(m) {
            m.eventRef.status = 'cancelled';
            m.eventRef.cancelledReason = reason;
            m.eventRef.cancelledDetail = detail ? String(detail).trim() : '';
        });

        const row = this.rowFromEvent(event, days, weekIndex, reason);
        const rows = MockData.conflictedClasses;
        if (!rows.some(function(r) { return r.id === row.id; })) rows.push(row);

        const entry = {
            id: 'cxl-' + Date.now() + '-' + (++this._seq),
            matchKey: mk,
            sessionId: (event.sessionId != null) ? event.sessionId : event.id,  // the server bridge's handle
            exceptionId: null,   // stamped from the server response in the confirm await
            reason: reason,
            detail: detail ? String(detail).trim() : '',
            cancelledAt: new Date().toISOString(),
            row: row,
            chip: true,
        };
        this.ledgerAppend(entry);
        return { ok: true, entry: entry };
    },

    /* S12/S13b — newest entry that is neither removed nor consumed, or
       null. Consumed entries (consumed:'arranged') keep replaying the
       cancelled state but stop the toast + chip (§6 lifecycle). */
    activeEntry() {
        const entries = this.ledgerRead();
        for (let i = entries.length - 1; i >= 0; i--) {
            const e = entries[i];
            if (e && !e.removed && !e.consumed) return e;
        }
        return null;
    },

    /* class-cancel-db-persist: the ledger↔row matcher for SERVER-shaped
       rows (replacement-home payload rows carry sessionId + week, ids
       like session*100+week — never equal to the client row ids). Used by
       the chip render + applyLedger's retention. */
    entryCoversRow(entry, row) {
        if (!entry || !row) return false;
        if (entry.row && entry.row.id === row.id) return true;   // client-appended, in-tab
        if (entry.sessionId != null && row.sessionId != null &&
            Number(entry.sessionId) === Number(row.sessionId) &&
            entry.matchKey && Number.isInteger(entry.matchKey.week) &&
            entry.matchKey.week + 1 === row.week) return true;   // server payload row
        return false;
    },

    /**
     * S12/S13/S15 replay — once per page load: re-apply every entry's
     * cancelled state idempotently (status already 'cancelled' → plain
     * overwrite; row pushed only when its id is absent). Entries whose
     * match keys resolve to NOTHING are stale → DROPPED from the ledger
     * (S15) so the toast never nags on dead entries.
     *
     * class-cancel-db-persist: a server-bridged entry (sessionId +
     * exceptionId — an exception ROW THE SERVER VERIFIED) is NEVER dead:
     * its week left the grid (the exception removed it from the events)
     * but the handles persist. NON-bridged entries keep the S15 self-evict.
     * (2026-11-05: the undo path was deleted with the affordance.)
     */
    applyLedger() {
        const self = this;
        this.ledgerRead().slice().forEach(function(entry) {
            const matches = self.resolveMatches(entry.matchKey);
            if (!matches.length) {
                if (entry.sessionId != null && entry.exceptionId != null) {
                    return;   // server-bridged: retain while the handles persist
                }
                self.ledgerRemove(entry);
                return;
            }
            matches.forEach(function(m) {
                m.eventRef.status = 'cancelled';
                m.eventRef.cancelledReason = entry.reason;
                m.eventRef.cancelledDetail = entry.detail;
            });
            const rows = MockData.conflictedClasses || [];
            if (entry.row && !rows.some(function(r) { return r.id === entry.row.id; })) {
                /* Own-records backfill: ledgers persisted before replacement-home
                   became owner-scoped store rows without `lecturer`. Cancellations
                   are own-only by construction, so the twin event's lecturer is
                   the owner — without this the replayed row would be filtered out
                   of replacement-home (S12/S13b). */
                if (!entry.row.lecturer) {
                    entry.row.lecturer = (matches[0] && matches[0].eventRef.lecturer) ||
                                         MockData.currentUser.name;
                }
                rows.push(entry.row);
            }
        });
    },

    /* Public read accessor for per-entry chip rendering (S13): a COPY of
       the raw ledger (pages must not mutate the raw array). Newest last,
       per-entry chips — NOT just activeEntry() (§6 stack semantics). */
    allEntries() {
        return this.ledgerRead().slice();
    },

    /* S13b — mark every entry whose matchKey.code equals `code` as
       consumed:'arranged'. The entry is RETAINED (applyLedger keeps
       replaying the cancelled state) but the chip + load toast stop and
       undo is no longer offered (§6 lifecycle). Returns how many entries
       changed; a submit for a different subject touches nothing. */
    markConsumedByCode(code) {
        let changed = 0;
        this.allEntries().forEach(function(entry) {
            if (entry && !entry.consumed && entry.matchKey &&
                entry.matchKey.code === code) {
                entry.consumed = 'arranged';
                this.ledgerUpdate(entry);
                changed++;
            }
        }, this);
        return changed;
    },

    /* The ONE cancellation-confirmation toast: the load bootstrap AND the
       "I'll Do It Later" path both call it; the confirm path must NOT
       double-toast (the modal's success state carries the message instead).
       Snooze rule (2026-10-08 decision): the toast shows for 5 s — surviving
       the full display OR clicking ✕ both count as "seen" and snooze it for
       the session; only a quick navigate-away (timer killed) re-toasts on the
       next load.

       2026-11-05 (user ruling): Cancel Class is FINAL and undoing is gone from
       the system, so this is a plain confirmation — no callback, no server
       round-trip. Renamed from showUndoToast() once the undo path was deleted. */
    showCancelToast() {
        toast.show('Class cancelled — arrange replacement when ready', null);
    },

    /* Toast snooze — the user explicitly closed the cancellation toast, so
       stop re-showing it on every page load (2026-10-08 feedback: the toast
       kept returning after every refresh). The LEDGER ENTRY IS KEPT: the
       cancelled state + chip still replay; only the toast is silenced. */
    snoozeToast() {
        const entry = this.activeEntry();
        if (entry && !entry.toastSnoozed) {
            entry.toastSnoozed = true;
            this.ledgerUpdate(entry);
        }
    },

    /* Does ANY live entry still deserve a load toast? A snoozed entry stays
       silent; a NEW cancellation (fresh entry, unsnoozed) toasts again.

       2026-11-05 (manual-conflict-test report): a reminder is ONE-SHOT, not one
       per page load. The bridged-entry retention rule above (kept from the
       undo era) can hold a note for a cancellation that no longer exists on
       the server — an entry the app can never clear on its own — so the old
       predicate nagged on EVERY load forever. `toastShown` is written by
       markLoadToastShown() the moment the reminder is actually displayed:
       the reminder still arrives once, it just cannot become permanent. */
    needsLoadToast() {
        return this.allEntries().some(function(e) {
            return e && !e.removed && !e.consumed && !e.toastSnoozed && !e.toastShown;
        });
    },

    /* The load toast has been DISPLAYED — spend its one turn. Marking every
       still-live entry (not just the first) keeps the rule uniform: whichever
       entry the toast was about, no entry re-fires on the next page load. */
    markLoadToastShown() {
        const self = this;
        this.allEntries().forEach(function(e) {
            if (e && !e.removed && !e.consumed && !e.toastSnoozed && !e.toastShown) {
                e.toastShown = true;
                self.ledgerUpdate(e);
            }
        });
    },
};

/* S12 bootstrap — registered HERE, not in the layout (design §2): a
   separate DOMContentLoaded listener owned by ui-common.js so it fires on
   EVERY page while the layout's own init hook stays untouched. Replay AND
   the load toast both wait for DOMContentLoaded: ui-common.js loads in the
   body BEFORE the #toastBar markup, and ToastManager.show() silently
   no-ops without it (design §2 🔴1). */
document.addEventListener('DOMContentLoaded', function() {
    try {
        ClassCancellation.applyLedger();
        /* 2026-11-05 (user ruling, manual conflict test step 8): a BLOCKED arrival
           explains ITSELF first. Landing on /my-replacements?blocked=request-pending
           used to show the cancellation reminder instead of the entry-guard
           message, so the reason you were sent here never appeared. The order is
           now: the blocked arrival speaks, and the cancellation reminder keeps its
           turn for the NEXT page load — it is neither shown nor spent here. */
        if (/[?&]blocked=/.test(location.search)) {
            showEntryGuardToast();
            return;
        }
        if (ClassCancellation.needsLoadToast()) {
            ClassCancellation.showCancelToast();
            ClassCancellation.markLoadToastShown();   // ONE reminder per cancellation, not per load
        } else {
            showEntryGuardToast();                    // arrival feedback after a BLOCKED arrangement entry
        }
    } catch (e) { /* degrade — page init must never break over the ledger */ }
});

/* ENTRY GUARD arrival toast (2026-11-02, lane arrangement-entry-guard).
   The arrangement page refuses entry for an occurrence that already carries an
   active (pending/approved) replacement request and redirects here with
   ?blocked=request-pending|request-approved. The message names the reason and
   links STRAIGHT to the page that manages the request — no dead end.
   Same arrival-param family as the other cross-page arrived-state toasts
   (the house mechanic: cheap, bookmark-safe, no flash ceremony). */
function showEntryGuardToast() {
    const blocked = new URLSearchParams(location.search).get('blocked');
    if (blocked !== 'request-pending' && blocked !== 'request-approved') return;
    /* 2026-11-04 (user decision): the body used to end with the page name while
       the link was labelled with it too, so toast._present() printed the name
       twice. The body now carries the fact only; the link carries the page name
       once. The state word stays in caps. The link names the merged page and its
       canonical URL (replacement-merge-one-table, commit 3). */
    if (blocked === 'request-approved')
        toast.show('This class already has an APPROVED replacement.',
            null, 8000, 'My Replacements', '/my-replacements');
    else
        toast.show('This class already has a PENDING replacement request.',
            null, 8000, 'My Replacements', '/my-replacements');
}

/* bfcache guard — Arrange-Now (or any future leave-with-modal-open path)
   can be revisited via the browser BACK button; the restored snapshot
   replays with the cancel modal still shown. Close it here — open() fully
   resets the confirm/success state on the next open (2026-10-08 user
   report: modal still open after BACK from replacement-arrangement). */
window.addEventListener('pageshow', function(e) {
    if (e.persisted) {
        try { CancelClassModal.close(); } catch (err) { /* degrade */ }
    }
});

// ───── CancelClassModal + CancelClass.renderButton (cancel-class-enhancement — design §5) ─────

/**
 * Controller for the promoted partial `partials/ui-cancel-class-modal.blade.php`
 * (included by my-timetable, cohort-timetable and venue-timetable). Owns the
 * confirm state (impact preview, reason radios, S7/S8 Yes-gating, S6
 * confirm-time re-check) and the success state (S10 Arrange-Now navigation,
 * S11 Later → rebuild + undo toast). The cancel ITSELF stays in
 * ClassCancellation.cancel — this object is pure UI orchestration.
 */
const CancelClassModal = {
    /* { event, days, weekIndex, onCancelled, entry } for the current open */
    _ctx: null,
    _bound: false,

    /* Bind the partial's dynamic widgets ONCE, lazily at first open —
       ui-common.js loads before the partial markup in the body. */
    _bind() {
        if (this._bound) return;
        const list = document.getElementById('cancelReasonList');
        const detail = document.getElementById('cancelOtherDetail');
        if (list) {
            list.addEventListener('change', (function(e) {
                if (e.target && e.target.name === 'cancelReason') this._refreshGate();
            }).bind(this));
        }
        if (detail) detail.addEventListener('input', this._refreshGate.bind(this));
        /* Esc closes WITHOUT cancelling (confirm state) — one shared
           document listener, active only while the overlay is shown. */
        document.addEventListener('keydown', (function(e) {
            const overlay = document.getElementById('cancelClassOverlay');
            if (e.key === 'Escape' && overlay && overlay.classList.contains('show')) this.dismiss();
        }).bind(this));
        this._bound = true;
    },

    /* ───── The whole-day block (SDD cancel-whole-day) ─────────────────────── */

    /**
     * Ask the server for THIS lecturer's classes on the opened class's date.
     * One read per open; the block is hidden until it answers, and hidden for
     * good when the day has no other class (user decision 6).
     */
    _loadDayScope() {
        const scope = document.getElementById('cancelDayScope');
        const ctx = this._ctx;
        if (!scope || !ctx) return;

        scope.style.display = 'none';
        scope.innerHTML = '';
        this._dayPayload = null;

        const livewire = window.pageLivewire ? window.pageLivewire() : (window.Livewire && window.Livewire.first ? window.Livewire.first() : null);
        if (!livewire || typeof livewire.call !== 'function') return;

        /* approved-replacement-cancellation T3: one token per open — the read is
           async, so an answer belonging to an EARLIER open must never land in
           this modal's scope. */
        const seq = this._openSeq;

        const week = ctx.weekIndex + 1;
        const day = ctx.event.di;
        const isApproved = ctx.event.status === 'replacement';
        /* normal opened: the opened class itself arrives in the day list and is
           dropped by its own session id. Approved opened: the shown coordinate is
           the LANDING week — a recurring row of the same session on this weekday
           is another week's legitimate class (its exception lives in its own
           source week), so NOTHING is excluded by session id here. */
        const wanted = String(ctx.event.sessionId != null ? ctx.event.sessionId : ctx.event.id);
        /* the opened request's OWN make-up note is removed (it is this modal's
           subject, not another week's block); every other note stays */
        const openedReqId = (isApproved && ctx.event.requestId != null)
            ? Number(ctx.event.requestId) : null;

        livewire.call('myDayClasses', week, day).then((res) => {
            if (this._openSeq !== seq || this._ctx !== ctx) return;   // stale modal's answer
            if (!res || res.ok !== true || !Array.isArray(res.classes)) return;
            const others = isApproved ? res.classes.slice()
                : res.classes.filter((c) => String(c.sessionId) !== wanted);
            const makeUps = (Array.isArray(res.makeUps) ? res.makeUps : [])
                .filter((m) => openedReqId === null || m.requestId == null ||
                    Number(m.requestId) !== openedReqId);
            const pendingMakeUps = makeUps.filter((m) => m.request === 'pending');

            /* 2026-11-11 (user bug report): a day with only the opened class but a
               waiting request still has something this box can do — the request is
               withdrawable — so the block appears for it too. */
            if (others.length === 0 && pendingMakeUps.length === 0 && makeUps.length === 0) return;
            this._dayPayload = {
                day: res.day,
                date: res.date,
                others: others,
                makeUps: makeUps,
            };
            this._renderDayScope(others, this._dayPayload.makeUps);
        }).catch(() => { /* the block simply does not appear */ });
    },

    /**
     * The cohort cell (user, 2026-11-11): the row prints the compact code, the
     * hover says the full name. One cohort ⇒ its own name on the tip; several ⇒
     * `n cohorts` and one `code — full name` per line, the shared multi-line
     * data-tip shape (\n + pre-line) like every other tip in the app.
     */
    _cohortCell(c) {
        const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        const details = Array.isArray(c.cohortDetails) && c.cohortDetails.length
            ? c.cohortDetails
            : (Array.isArray(c.cohorts) ? c.cohorts.map((l) => ({ label: l, full: l })) : []);

        if (details.length === 0) {
            return '<span class="cdl-cohort">\u2014</span>';
        }

        if (details.length === 1) {
            return '<span class="cdl-cohort" data-tip="' + esc(details[0].full || details[0].label) + '">' +
                esc(details[0].label) + '</span>';
        }

        const tip = details.map((d) => (d.full && d.full !== d.label ? d.label + ' \u2014 ' + d.full : d.label)).join('\n');

        return '<span class="cdl-cohort" data-tip="' + esc(tip) + '">' + details.length + ' cohorts</span>';
    },

    _renderDayScope(others, makeUps) {
        /* one wording for the amber pill, wherever it appears (user, 2026-11-11) */
        const tip = ' data-tip="This Request will be Cancelled or Withdrawn"';
        const scope = document.getElementById('cancelDayScope');
        if (!scope) return;

        const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        /* 2026-11-11 (user): a PENDING make-up block is a row of its own — ticking
           it withdraws that request; the class it was arranged for is untouched.
           An APPROVED one keeps the quiet note (withdrawing a promise is a
           different decision, not this box's job). */
        const pendingMakeUps = (makeUps || []).filter((m) => m.request === 'pending');
        const noteOnly = (makeUps || []).filter((m) => m.request !== 'pending');

        const makeupRows = pendingMakeUps.map((m) => {
            const cohorts = Array.isArray(m.cohorts) ? m.cohorts : [];
            const cohortCell = cohorts.length > 1
                ? '<span class="cdl-cohort" data-tip="' + esc(cohorts.join('\n')) + '">' + cohorts.length + ' cohorts</span>'
                : (cohorts[0] ? '<span class="cdl-cohort">' + esc(cohorts[0]) + '</span>' : '');
            return '<li><label class="cancel-day-row">' +
                '<input type="checkbox" data-request="' + esc(m.requestId) + '">' +
                '<span class="cdl-time">' + esc(to12h(m.start) + ' ~ ' + to12h(m.end)) + '</span>' +
                '<span class="cdl-what">' + esc(m.code + '(' + (m.type || '') + ')') + '</span>' +
                '<span class="cdl-venue">' + esc(m.venue) + '</span>' +
                '<span class="cdl-facts">' + cohortCell +
                '<span class="cdl-flag"' + tip + '>1 request</span></span>' +
                '</label></li>';
        }).join('');

        const rows = others.map((c) => {
            const cohortCell = this._cohortCell(c);
            const waiting = (c.request === 'pending' || c.request === 'approved')
                ? '<span class="cdl-flag"' + tip + '>1 request</span>'
                : '';
            return '<li><label class="cancel-day-row">' +
                '<input type="checkbox" data-session="' + esc(c.sessionId) + '">' +
                '<span class="cdl-time">' + esc(to12h(c.start) + ' ~ ' + to12h(c.end)) + '</span>' +
                '<span class="cdl-what">' + esc(c.code + '(' + (c.type || '') + ')') + '</span>' +
                '<span class="cdl-venue">' + esc(c.venue) + '</span>' +
                '<span class="cdl-facts">' + cohortCell + waiting + '</span>' +
                '</label></li>';
        }).join('');

        scope.innerHTML =
            '<div class="cancel-day-head">' +
                '<label class="cancel-day-toggle">' +
                    '<input type="checkbox">' +
                    '<span>' +
                        '<span class="cd-lead">Also cancel my other </span>' +
                        '<span class="cd-class-half"><span class="cd-count">' + others.length + '</span> <span class="cd-class-word">' +
                            (others.length === 1 ? 'class' : 'classes') + '</span>' +
                            '<span class="cd-plus"> + </span></span>' +
                        '<span class="cd-req"><span class="cd-count-req">' + pendingMakeUps.length +
                        '</span> <span class="cd-req-word">' + (pendingMakeUps.length === 1 ? 'request' : 'requests') + '</span></span>' +
                        ' on the <span class="info-keyword">same day</span></span>' +
                '</label>' +
                '<button type="button" class="cd-collapse" aria-expanded="true" title="Show or hide these classes">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>' +
                '</button>' +
            '</div>' +
            '<ul class="cancel-day-list">' + rows + makeupRows + '</ul>' +
            this._makeUpNote(noteOnly);

        /* 2026-11-11 (user): the block opens UNTICKED and FOLDED — the whole day is
           an offer, not a default. The sentence still names how many there are;
           only the selection starts empty. */
        scope.classList.remove('is-on');
        scope.classList.add('is-collapsed');
        scope.style.display = '';

        // one delegated listener pair: the head tick is select-all, a row tick
        // re-counts, the chevron folds — no per-row wiring to leak on re-render
        scope.querySelector('.cancel-day-toggle input').addEventListener('change', (e) => {
            this._setDayAll(scope, e.target.checked);
        });
        scope.querySelector('.cd-collapse').setAttribute('aria-expanded', 'false');
        scope.querySelector('.cd-collapse').addEventListener('click', () => {
            scope.classList.toggle('is-collapsed');
            const btn = scope.querySelector('.cd-collapse');
            if (btn) btn.setAttribute('aria-expanded', scope.classList.contains('is-collapsed') ? 'false' : 'true');
        });
        scope.querySelector('.cancel-day-list').addEventListener('change', () => {
            this._refreshDayGate();
        });

        this._refreshDayGate();
    },

    /**
     * The make-up note (user, 2026-11-11): a make-up block that FALLS on this day
     * belongs to another week's class, so it is named here instead of being given a
     * tick — ticking it would cancel a class from a different week.
     */
    _makeUpNote(makeUps) {
        if (!makeUps.length) return '';
        const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

        const lines = makeUps.map((m) => esc(m.code + '(' + (m.type || '') + ')') +
            ' \u00b7 week ' + esc(m.week) + ' \u00b7 ' + esc(to12h(m.start) + ' ~ ' + to12h(m.end)) +
            (m.venue ? ' \u00b7 ' + esc(m.venue) : '')).join('\n');

        return '<p class="cancel-day-note" data-tip="' + esc(lines) + '">' +
            (makeUps.length === 1 ? '1 make-up block' : makeUps.length + ' make-up blocks') +
            ' that day ' +
            (makeUps.length === 1 ? 'belongs' : 'belong') +
            ' to another week \u2014 cancel ' + (makeUps.length === 1 ? 'it' : 'them') + ' from ' +
            (makeUps.length === 1 ? 'its' : 'their') + ' own class.</p>';
    },

    _dayBoxes(scope) {
        return Array.prototype.slice.call(scope.querySelectorAll('.cancel-day-row input'));
    },

    _setDayAll(scope, on) {
        this._dayBoxes(scope).forEach((b) => { b.checked = on; });
        if (on) scope.classList.remove('is-collapsed');
        this._refreshDayGate();
    },

    /** Which classes will go WITH the opened one (the ticked rows). */
    _daySelection() {
        const scope = document.getElementById('cancelDayScope');
        if (!scope || scope.style.display === 'none' || !scope.classList.contains('is-on')) return [];
        return this._dayBoxes(scope).filter((b) => b.checked).map((b) => ({
            session: b.dataset.session || null,
            request: b.dataset.request || null,
        }));
    },

    /** Keeps the three live numbers agreeing: the sentence, the head tick, the button. */
    _refreshDayGate() {
        const scope = document.getElementById('cancelDayScope');
        if (!scope || scope.style.display === 'none') return;

        const boxes = this._dayBoxes(scope);
        const on = boxes.filter((b) => b.checked).length;
        const head = scope.querySelector('.cancel-day-toggle input');
        if (head) {
            head.checked = on > 0;
            head.indeterminate = on > 0 && on < boxes.length;
        }
        scope.classList.toggle('is-on', on > 0);

        /* 2026-11-11 (user): the sentence names BOTH kinds of row it can carry —
           classes and requests — because a make-up row withdraws a request, not a
           class. Nothing selected ⇒ the totals; otherwise the ticks. */
        const classesTotal = boxes.filter((b) => !b.dataset.request).length;
        const reqsTotal = boxes.filter((b) => !!b.dataset.request).length;
        const classesOn = boxes.filter((b) => !b.dataset.request && b.checked).length;
        const reqsOn = boxes.filter((b) => !!b.dataset.request && b.checked).length;
        const anyTick = on > 0;

        const shownClasses = anyTick ? classesOn : classesTotal;
        const shownReqs = anyTick ? reqsOn : reqsTotal;

        /* a day with no OTHER class reads "cancel 1 request on the same day" */
        const classHalf = scope.querySelector('.cd-class-half');
        const plus = scope.querySelector('.cd-plus');
        const lead = scope.querySelector('.cd-lead');
        if (classHalf) classHalf.style.display = shownClasses > 0 ? '' : 'none';
        if (plus) plus.style.display = shownClasses > 0 && shownReqs > 0 ? '' : 'none';
        if (lead) lead.textContent = shownClasses > 0 ? 'Also cancel my other ' : 'Also cancel ';

        const count = scope.querySelector('.cd-count');
        if (count) count.textContent = String(shownClasses);
        const classWord = scope.querySelector('.cd-class-word');
        if (classWord) classWord.textContent = shownClasses === 1 ? 'class' : 'classes';

        const reqWrap = scope.querySelector('.cd-req');
        const reqCount = scope.querySelector('.cd-count-req');
        if (reqCount) reqCount.textContent = String(shownReqs);
        const reqWord = scope.querySelector('.cd-req-word');
        if (reqWord) reqWord.textContent = shownReqs === 1 ? 'request' : 'requests';
        if (reqWrap) reqWrap.style.display = shownReqs > 0 ? '' : 'none';

        /* 2026-11-11 (user): the red button mirrors the sentence — both kinds, both
           counts — because it is the last thing read before an irreversible click. */
        const yes = document.getElementById('confirmCancelClassBtn');
        if (yes) {
            const classes = classesOn + 1;                  // the opened class is always one
            let label = 'Yes, Cancel ' + classes + ' Class' + (classes === 1 ? '' : 'es');
            if (reqsOn > 0) {
                label += ' + ' + reqsOn + ' Request' + (reqsOn === 1 ? '' : 's');
            }
            yes.textContent = anyTick ? label : 'Yes, Cancel Class';
        }
    },

    /* ───── S1–S5 guard + confirm-state render. `context.onCancelled` (optional)
       is the page's grid-rebuild callback used by the Later path. */
    open(event, days, weekIndex, context) {
        if (!ClassCancellation.isCancellable(event, days, weekIndex)) return;  // S1–S5
        this._ctx = {
            event: event,
            days: days,
            weekIndex: weekIndex,
            onCancelled: (context && typeof context.onCancelled === 'function') ? context.onCancelled : null,
            entry: null,
        };
        /* approved-replacement-cancellation T3: a fresh token per open — the
           async day-scope read checks it before its answer is applied. */
        this._openSeq = (this._openSeq || 0) + 1;
        this._bind();
        this._renderReasons();
        this._renderImpact();
        /* 2026-11-11 (SDD cancel-whole-day): the whole-day block needs the day's
           classes, which only the server knows on the cohort/venue pages. The
           modal opens immediately; the block appears when the read answers, and
           stays absent when the day has nothing else to cancel. */
        this._loadDayScope();
        /* 2026-11-10 (item 5): a fresh open starts with a BLANK "Other" detail and
           its block closed; _refreshGate() then sets the wrapper from the reason. */
        const detail = document.getElementById('cancelOtherDetail');
        if (detail) detail.value = '';
        const detailWrap = document.getElementById('cancelOtherWrap');
        if (detailWrap) detailWrap.style.display = 'none';
        this._swapState('confirm');
        this._refreshGate();  // S7 initial hint + disabled Yes
        const overlay = document.getElementById('cancelClassOverlay');
        if (overlay) overlay.classList.add('show');
    },

    /* Raw close — no cancel performed, no rebuild (confirm state only). */
    close() {
        const overlay = document.getElementById('cancelClassOverlay');
        if (overlay) overlay.classList.remove('show');
    },

    /* Esc / overlay-click / header × / Keep Class. Once a cancel has been
       performed (success state), closing MUST still rebuild the grid +
       toast — the cancel already happened — so it routes to the Later path. */
    dismiss() {
        if (this._ctx && this._ctx.entry) { this.later(); return; }
        this.close();
    },

    /* Render the reason radios from the ClassCancellation enum (the markup
       keeps only the #cancelReasonList container — design §5). */
    _renderReasons() {
        const list = document.getElementById('cancelReasonList');
        if (!list) return;
        /* 2026-11-10 (user brief): the heading carries the app's red required
           asterisk (a visual mark only — it is NOT part of any label), and the
           eight reasons sit TWO per row inside `.cancel-reasons` (one per row
           under 520px). Labels and values are untouched. */
        list.innerHTML = '<div class="detail-group">'
            + '<div class="detail-group-title">Cancellation Reason'
            + ' <span class="cancel-required-mark">*</span></div>'
            + '<div class="cancel-reasons"></div></div>';
        const group = list.querySelector('.cancel-reasons');
        if (!group) return;
        ClassCancellation.REASONS.forEach(function(reason) {
            const label = document.createElement('label');
            label.className = 'cancel-reason-option';
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'cancelReason';
            input.value = reason.value;   // the DB enum travels (class-cancel-db-persist)
            label.appendChild(input);
            label.appendChild(document.createTextNode(reason.label));   // the plain modal label — NOT the {value,label} object
            group.appendChild(label);
        });
    },

    /* The class facts (design §5, restyled by the 2026-11-05 UI/UX update):
       the SAME grey box the Confirm Cancellation modal uses — `dl.cancel-details`
       — carrying course, week, full day + date, clock + duration, venue
       `CODE — Name`, cohorts joined `+`, and the per-cohort student breakdown.
       Every value comes from the page payload (the day's own date string, the
       server's positional `studentCounts`); nothing here is hardcoded. */
    _renderImpact() {
        const el = document.getElementById('cancelClassImpact');
        const ctx = this._ctx;
        if (!el || !ctx) return;
        const ev = ctx.event;
        const day = ctx.days && ctx.days[ev.di];
        const startStr = to12h(hours[ev.start]);
        const endStr = to12h(hours[ev.end + 1] || add30min(hours[ev.end]));
        /* duration = half-hour span / 2 — the same math the row build uses */
        const duration = (ev.end - ev.start + 1) / 2;
        const FULL_DAY = { Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday',
            Thu: 'Thursday', Fri: 'Friday', Sat: 'Saturday', Sun: 'Sunday' };
        const dayName = day ? (FULL_DAY[day.abbr] || day.abbr) : '';
        /* the venue NAME rides the payload when the page ships it (venueName);
           the MockData.venues mirror is the fallback every page already has */
        const venueName = ev.venueName ||
            (((typeof MockData !== 'undefined' && MockData.venues) || [])
                .find(function (v) { return v.code === ev.venue; }) || {}).name || '';
        const venueText = ev.venue
            ? (venueName ? ev.venue + ' \u2014 ' + venueName : ev.venue)
            : '\u2014';
        const cohorts = ClassCancellation.cohortLabels(ev);
        const facts = [
            ['Course', escHtml(ev.code) + ' (' + escHtml(ev.type || '') + ') \u2014 ' + escHtml(ev.name || '')],
            ['Week', 'Week ' + (ctx.weekIndex + 1)],
            ['Day & Date', escHtml(dayName) + (day ? ' \u00b7 ' + escHtml(day.date) : '')],
            ['Time', escHtml(startStr) + ' ~ ' + escHtml(endStr) + ' (' + uiHoursWords(duration) + ')'],
            ['Venue', escHtml(venueText)],
            ['Cohort(s)', escHtml(cohorts.join(' + ') || '\u2014')],
            /* A + B = total for multi-cohort classes (the shared law) */
            ['Students', escHtml(window.totalStudentsLabel(ev))],
        ];
        el.innerHTML = DetailModal.section('Class Information',
            '<dl class="cancel-details">' + facts.map(function (f) {
                return '<dt>' + f[0] + '</dt><dd>' + f[1] + '</dd>';
            }).join('') + '</dl>'
        );
    },

    /* (the old §3 `studentsFor()` twin-sum helper died with the old preview:
       the restyle reads the event's OWN positional studentCounts through the
       shared totalStudentsLabel() law, so no twin lookup is needed.) */

    _selectedReason() {
        const picked = document.querySelector('#cancelReasonList input[name="cancelReason"]:checked');
        return picked ? picked.value : '';
    },

    /* S7/S8 — reason + Other-detail gate the Yes button and the inline hint. */
    _refreshGate() {
        const reason = this._selectedReason();
        const detail = document.getElementById('cancelOtherDetail');
        const hint = document.getElementById('cancelReasonHint');
        const yes = document.getElementById('confirmCancelClassBtn');
        /* the labelled "Other" BLOCK (label + textarea) shows/hides as one unit —
           item 5. Only the wrapper's display changes; the typed text is never
           cleared, so switching away and back keeps it. */
        const detailWrap = document.getElementById('cancelOtherWrap');
        if (detailWrap) detailWrap.style.display = reason === 'other' ? '' : 'none';
        const check = ClassCancellation.validate(reason, detail ? detail.value : '');
        if (hint) hint.textContent = check.hint;
        if (yes) yes.disabled = !check.ok;
        /* 2026-11-10 (user brief): the short CANNOT UNDONE strip is visible ONLY
           while Yes is armed — a cleared reason, or "Other" left blank, hides it
           again. _swapState() owns the success state (wrapper hidden wholesale). */
        const undoWarn = document.getElementById('cancelClassUndoWarn');
        if (undoWarn) undoWarn.style.display = check.ok ? '' : 'none';
    },

    /* S6 + S9 — confirm-time re-check, then the server-bridged cancel
       (class-cancel-db-persist): the Yes pending-disables FIRST (the
       double-fire guard), the pre-flight passes only then, the Livewire
       trait call runs, and the SERVER response is the truth — ok → the
       existing client mutation + the ledger (exceptionId stamped for the
       undo); fail → the honest hint, NO flip, NO ledger (the midnight-roll
       receipt: the client's own re-check never overrides the server).

       approved-replacement-cancellation T3: an OPENED APPROVED replacement is
       its own target — its requestId plus the shown LANDING week/day ride the
       call (cancelClass's 5th/6th args, or the day call's approved list); its
       success is receipt-only, never the recurring ledger. */
    async confirm() {
        const ctx = this._ctx;
        if (!ctx || ctx.entry) return;
        const detail = document.getElementById('cancelOtherDetail');
        const yes = document.getElementById('confirmCancelClassBtn');
        yes.disabled = true;   // pending-disable FIRST — one await, one write
        const reason = this._selectedReason();
        const trimmedDetail = detail ? String(detail.value).trim() : '';
        const pre = ClassCancellation.preflight(ctx.event, ctx.days, ctx.weekIndex, reason, trimmedDetail);

        if (!pre.ok) {
            /* ORDER MATTERS (2026-11-05, the blank-message bug): _refreshGate()
               re-arms the Yes button AND rewrites the hint from validate() —
               which is EMPTY whenever the reason itself is fine. Writing the
               refusal copy BEFORE it therefore erased it, so a refused cancel
               explained nothing. The copy goes AFTER the gate, on a freshly
               queried element. */
            this._refreshGate();   // re-gate for a retry after the user fixes the input
            const refusal = document.getElementById('cancelReasonHint');
            if (refusal) refusal.textContent = pre.hint;

            return;
        }
        /* 2026-11-11 (SDD cancel-whole-day): a ticked block means the decision is
           the WHOLE day. The server re-resolves the set from (week, day), so this
           list only decides whether to take that path — never what it cancels. */
        const daySel = this._daySelection();
        const dayIds = daySel.filter((x) => x.session).map((x) => x.session);
        const dayRequests = daySel.filter((x) => x.request).map((x) => x.request);

        /* my-timetable events carry `id`; the venue/cohort payloads carry `sessionId` */
        const openedId = String(ctx.event.sessionId != null ? ctx.event.sessionId : ctx.event.id);
        /* approved-replacement-cancellation T3: an OPENED APPROVED replacement is
           its own target — identified by its requestId and the shown LANDING
           week/day, never by its source session. The requestId is REQUIRED (the
           timetable stamps it on every replacement block); without it the modal
           cannot name the request, so it refuses instead of silently falling
           back to a normal cancellation. */
        const isApproved = ctx.event.status === 'replacement';
        const approvedReqId = isApproved && ctx.event.requestId != null
            ? String(ctx.event.requestId) : null;
        if (isApproved && approvedReqId === null) {
            /* same order law as the refusals below: the copy lands AFTER
               _refreshGate(), which would otherwise overwrite it */
            this._refreshGate();
            const missing = document.getElementById('cancelReasonHint');
            if (missing) {
                missing.textContent = 'This replacement can no longer be cancelled from here — refresh the page and try again.';
            }
            return;
        }

        /* the opened class is always part of the decision; the ticked rows are the
           rest — an UNTICKED row must never be sent, or it would be cancelled.
           An approved opened block rides the day call's APPROVED list ONLY — its
           source session is NEVER sent among the normal selections, because a
           recurring row of that same session on this landing day is another
           week's legitimate class, not the opened one. */
        const sessionsToCancel = isApproved ? dayIds.slice() : (openedId ? [openedId] : []).concat(dayIds);
        const approvedIds = approvedReqId ? [approvedReqId] : [];

        let res = null;
        try {
            res = daySel.length > 0
                ? await window.pageLivewire().call('cancelClassDay', ctx.weekIndex + 1, ctx.event.di, reason, trimmedDetail, dayRequests, sessionsToCancel, approvedIds)
                : isApproved
                    /* single approved: (sourceSessionId, LANDING week, reason,
                       detail, requestId, LANDING dayIndex) — the landing window
                       is the server-enforced one, per the frozen contract */
                    ? await window.pageLivewire().call('cancelClass',
                        openedId, ctx.weekIndex + 1, reason, trimmedDetail, approvedReqId, ctx.event.di)
                    : await window.pageLivewire().call('cancelClass',
                        String((ctx.event.sessionId != null) ? ctx.event.sessionId : ctx.event.id),
                        ctx.weekIndex + 1, reason, trimmedDetail);
        } catch (e) { res = { ok: false, hint: 'Server error — the class is unchanged.' }; }
        if (!res || res.ok !== true) {
            /* same order law as above, plus a RE-QUERY: the Livewire round-trip
               can re-render the modal, so the pre-await node may be detached. */
            this._refreshGate();
            const serverRefusal = document.getElementById('cancelReasonHint');
            if (serverRefusal) {
                serverRefusal.textContent = (res && res.hint) || 'Server error — the class is unchanged.';
            }

            return;
        }
        /* the day path already cancelled in one transaction — the client patches
           nothing. approved-replacement-cancellation T3: the success entry is
           stamped from the receipt BEFORE the success state, so Escape / header
           × / overlay dismissal refresh exactly like Close (later()). */
        if (daySel.length > 0) {
            const rows = Array.isArray(res.cancelled) ? res.cancelled : [];
            /* the opened target, only when the receipt actually has it */
            const primary = rows.find((r) => isApproved
                ? r.request_id != null && Number(r.request_id) === Number(approvedReqId)
                : r.request_id == null && r.session_id != null &&
                  String(r.session_id) === openedId &&
                  r.week != null && Number(r.week) === ctx.weekIndex + 1) || null;
            const pick = primary || rows[0] || null;
            ctx.entry = pick && pick.session_id != null && pick.week != null
                ? { sessionId: pick.session_id, week: pick.week,
                    row: { sessionId: pick.session_id, week: pick.week } }
                : { row: {} };
            this._renderDaySuccess(res, reason);
            this._swapState('success');
            return;
        }

        if (isApproved) {
            /* approved-replacement-cancellation T3: an approved cancellation is
               SERVER-FACT ONLY — no ClassCancellation.cancel, no MockData
               conflicted row, no ledger append/update. A ledger entry would
               replay the LANDING week as a cancelled recurring occurrence (the
               ledger maps week+code+day, and a landing is not one). The entry
               carries the receipt's SOURCE pair so Arrange Now opens the
               request's own source-week occurrence. */
            const rows = Array.isArray(res.cancelled) ? res.cancelled : [];
            /* saved-only receipt: the ONLY acceptable row is the exact
               request_id match that also carries its source session/week
               pair — never rows[0], never anything derived from ctx.event
               (the server said success, so no fact is invented here). */
            const row = rows.find((r) => r.request_id != null &&
                    Number(r.request_id) === Number(approvedReqId) &&
                    r.session_id != null && r.week != null) || null;
            if (!row) {
                /* same order law as the refusals above: the copy lands AFTER
                   _refreshGate(), which would otherwise overwrite it */
                this._refreshGate();
                const unconfirmed = document.getElementById('cancelReasonHint');
                if (unconfirmed) {
                    unconfirmed.textContent = 'The server could not confirm this replacement cancellation — reload to check its status.';
                }
                return;
            }
            const summary = document.getElementById('cancelClassSummary');
            if (summary) {
                const day = ctx.days && ctx.days[ctx.event.di];
                summary.textContent = row.code + ' (' + (row.type || '') + ') · ' +
                    ((day && day.abbr) || '') + ' · ' + ((day && day.date) || '') + ' · ' +
                    to12h(row.start) + ' ~ ' + to12h(row.end);
            }
            ctx.entry = { sessionId: row.session_id, week: row.week,
                row: { sessionId: row.session_id, week: row.week } };
            this._swapState('success');
            return;
        }

        const out = ClassCancellation.cancel(ctx.event, ctx.days, ctx.weekIndex, reason, trimmedDetail);
        if (!out.ok) {
            /* The server row is WRITTEN but the client guards now disagree
               (midnight-roll edge, seconds apart). The client still must not
               flip against its own guards — the hint reads honestly; a
               reload resolves to the server truth (applyLedger self-evict).
               2026-11-05: re-query, never the pre-await node. */
            const clientRefusal = document.getElementById('cancelReasonHint');
            if (clientRefusal) {
                clientRefusal.textContent = out.hint ||
                    'The class is cancelled on the server but the timetable disagrees — reload to see the truth.';
            }

            return;
        }
        out.entry.exceptionId = res.exceptionId || null;
        ClassCancellation.ledgerUpdate(out.entry);
        ctx.entry = out.entry;
        const summary = document.getElementById('cancelClassSummary');
        if (summary) {
            const ev = ctx.event;
            const day = ctx.days && ctx.days[ev.di];
            summary.textContent = ev.code + ' (' + (ev.type || '') + ') · ' +
                ((day && day.abbr) || '') + ' · ' + ((day && day.date) || '') + ' · ' +
                to12h(hours[ev.start]) + ' ~ ' + to12h(hours[ev.end + 1] || add30min(hours[ev.end]));
        }
        this._swapState('success');
    },

    /**
     * The whole-day success screen: the total and the list come ONLY from the
     * server's acknowledged `res.cancelled` receipt rows — never from the ticks
     * and never a fabricated opened row (approved-replacement-cancellation T3:
     * a defensive partial receipt shows exactly the acknowledged set). The
     * opened target is ordered first only when it is actually in the receipt.
     * The reason, the day line and the withdrawn pill keep the existing shape.
     */
    _renderDaySuccess(res, reason) {
        const single = document.getElementById('cancelClassSummary');
        const singleTitle = document.getElementById('cancelClassDoneTitle');
        const box = document.getElementById('cancelDaySuccess');
        if (!box) return;

        const esc = (v) => String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        const ctx = this._ctx;
        /* ONLY acknowledged rows — the receipt is the whole truth */
        const rows = (res && Array.isArray(res.cancelled)) ? res.cancelled : [];
        const withdrawn = Number((res && res.requestsWithdrawn) || 0);
        const closedWithClasses = Number((res && res.requestsClosed) || 0);

        /* the opened target first — only if the receipt actually has it */
        const openedId = ctx ? String(ctx.event.sessionId != null ? ctx.event.sessionId : ctx.event.id) : null;
        const openedReqId = (ctx && ctx.event.status === 'replacement' && ctx.event.requestId != null)
            ? Number(ctx.event.requestId) : null;
        const openedWeek = ctx ? ctx.weekIndex + 1 : null;
        const isPrimary = (r) => openedReqId != null
            ? (r.request_id != null && Number(r.request_id) === openedReqId)
            : (r.request_id == null && r.session_id != null && openedId !== null &&
               String(r.session_id) === openedId && r.week != null &&
               Number(r.week) === openedWeek);
        const primary = rows.find(isPrimary) || null;
        const ordered = primary ? [primary].concat(rows.filter((r) => r !== primary)) : rows.slice();

        /* the day, in the app's own words: "Wed, 18 Nov 2026" */
        const day = this._dayPayload || {};
        const iso = String(day.date || '');
        const prettyDay = iso
            ? DateHelper.dayAbbr(DateHelper.isoDayName(iso)) + ', ' + DateHelper.formatDate(iso)
            : '';
        const reasonLabel = (ClassCancellation.REASONS.find((r) => r.value === reason) || {}).label || reason;

        /* the schedule rows print the receipt's OWN server facts — one row per
           acknowledged cancellation, receipt order (opened target first when
           present); nothing here is synthesized from the unsaved payload */
        const scheduleRow = (r) => {
            const cohorts = Array.isArray(r.cohorts) ? r.cohorts : [];
            const cohortCell = cohorts.length > 1
                ? '<span class="cdl-cohort" data-tip="' + esc(cohorts.join('\n')) + '">' + cohorts.length + ' cohorts</span>'
                : '<span class="cdl-cohort">' + esc(cohorts[0] || '\u2014') + '</span>';
            return '<li><span class="tick">\u2713</span>' +
                '<span class="cdl-time">' + esc(to12h(r.start) + ' ~ ' + to12h(r.end)) + '</span>' +
                '<span class="cdl-what">' + esc((r.code || '') + '(' + (r.type || '') + ')') + '</span>' +
                '<span class="cdl-venue">' + esc(r.venue || '') + '</span>' +
                '<span class="cdl-facts">' + cohortCell + '</span></li>';
        };

        const total = rows.length;
        const withdrawnTotal = withdrawn + closedWithClasses;

        if (single) single.style.display = 'none';
        if (singleTitle) singleTitle.style.display = 'none';

        box.style.display = '';
        box.innerHTML =
            '<p class="cancel-day-result">\u2713 ' + total + (total === 1 ? ' class' : ' classes') + ' cancelled</p>' +
            '<p class="cancel-day-meta">' + esc(prettyDay) + (reasonLabel ? ' \u00b7 ' + esc(reasonLabel) : '') + '</p>' +
            '<div class="cancel-day-totals">' +
                '<div><span class="cdt-value">' + total + '</span><span class="cdt-label">' + (total === 1 ? 'Class' : 'Classes') + ' cancelled</span></div>' +
                '<div><span class="cdt-value">' + withdrawnTotal + '</span><span class="cdt-label">' + (withdrawnTotal === 1 ? 'Request' : 'Requests') + ' withdrawn</span></div>' +
            '</div>' +
            '<ul class="cancel-day-done">' + ordered.map(scheduleRow).join('') + '</ul>';
    },

    /* S10 — navigate to the arrangement page carrying the cancelled class's
       values (goToReplacementWith param scheme, design §2 ISO date pin). */
    arrangeNow() {
        const ctx = this._ctx;
        if (!ctx || !ctx.entry) return;
        /* Close BEFORE navigating: the browser's BACK button restores this
           page from bfcache, and an open success modal would replay with it
           (2026-10-08 user report). open() resets state on next open. */
        this.close();
        const row = ctx.entry.row || {};
        /* approved-replacement-cancellation T3: a server receipt entry carries
           the SOURCE pair, so Arrange Now opens the request's own source-week
           occurrence — never the landing week. A degenerate entry without a
           code+date handoff degrades to the plain arrangement page. */
        if (row.sessionId && row.week) {
            goToReplacementFor(row.sessionId, row.week, 'my-timetable');
        } else if (row.code && row.date) {
            window.location.href = '/replacement-arrangement?code=' + encodeURIComponent(row.code) +
                '&date=' + encodeURIComponent(row.date) +
                '&duration=' + row.duration;
        } else {
            window.location.href = '/replacement-arrangement';
        }
        /* navigation unloads the page — the bootstrap toast fires there (§6) */
    },

    /* S11 — close, let the page rebuild its grid via onCancelled, then the
       shared undo toast (the confirm path never double-toasts, §6). */
    later() {
        const ctx = this._ctx;
        this.close();
        if (ctx && typeof ctx.onCancelled === 'function') ctx.onCancelled();
        ClassCancellation.showCancelToast();
    },

    /* Swap confirm ↔ success state in-place, footer buttons included. */
    _swapState(state) {
        const confirmState = document.getElementById('cancelClassConfirmState');
        const successState = document.getElementById('cancelClassSuccessState');
        const keep = document.getElementById('cancelKeepClassBtn');
        const yes = document.getElementById('confirmCancelClassBtn');
        const confirmWrap = document.getElementById('cancelConfirmWrap');
        const arrange = document.getElementById('cancelArrangeNowBtn');
        const laterBtn = document.getElementById('cancelLaterBtn');
        const success = state === 'success';
        /* the warning lives INSIDE the wrapper, so hiding the wrapper is what
           guarantees it can never appear in the success state (2026-11-10) */
        if (confirmWrap) confirmWrap.style.display = success ? 'none' : '';
        if (confirmState) confirmState.style.display = success ? 'none' : '';
        if (successState) successState.style.display = success ? '' : 'none';
        if (keep) keep.style.display = success ? 'none' : '';
        if (yes) yes.style.display = success ? 'none' : '';
        const dayClose = document.getElementById('cancelDayCloseBtn');
        const dayDone = success && !!document.getElementById('cancelDaySuccess') &&
            document.getElementById('cancelDaySuccess').style.display !== 'none';
        /* the whole-day screen is not a single next step: one Close, no Arrange */
        if (arrange) arrange.style.display = (success && !dayDone) ? '' : 'none';
        if (laterBtn) laterBtn.style.display = (success && !dayDone) ? '' : 'none';
        if (dayClose) dayClose.style.display = dayDone ? '' : 'none';
    },
};

/**
 * Cancel Class? button for class-detail modal footers (design §5) — works
 * for ALL THREE modal producers: my-timetable / cohort (the shared
 * ui-class-detail-modal partial's .modal-footer) and venue (its own
 * eventModal .modal-footer). Pages call it right after their modal render:
 *
 *   CancelClass.renderButton(overlayEl.querySelector('.modal-footer'),
 *                            event, days, weekIndex, onCancelled);
 *
 * Self-hiding (S1–S5): appends ONLY when ClassCancellation.isCancellable —
 * a previously appended button is always removed first, so re-renders never
 * stack duplicates and a now-non-cancellable class loses its button. When
 * the page defines .btn-cancel-class styling (my-timetable page-styles) it
 * is reused; otherwise the theme's ghost .btn-danger token style applies.
 */
const CancelClass = {
    _styleChecked: null,

    _hasBtnCancelClassStyle() {
        if (this._styleChecked !== null) return this._styleChecked;
        let found = false;
        try {
            const sheets = document.styleSheets;
            for (let i = 0; i < sheets.length && !found; i++) {
                let rules = null;
                try { rules = sheets[i].cssRules; } catch (e) { continue; }  // cross-origin
                if (!rules) continue;
                for (let r = 0; r < rules.length; r++) {
                    if (rules[r].selectorText &&
                        rules[r].selectorText.indexOf('.btn-cancel-class') >= 0) {
                        found = true;
                        break;
                    }
                }
            }
        } catch (e) { /* fall through — not found → ghost btn-danger */ }
        this._styleChecked = found;
        return found;
    },

    /* The earliest eligible class DATE in the page's own date vocabulary
       ("Tue, 3 Nov 2026"). Reads the SAME _cutoffDate() the S5 guard compares
       against, so the tooltip can never advertise a window the button does
       not honour — one computation, two consumers. */
    _earliestEligibleLabel() {
        const d = ClassCancellation._cutoffDate();
        const iso = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
        return DateHelper.dayAbbr(DateHelper.isoDayName(iso)) + ', ' + DateHelper.formatDate(iso);
    },

    renderButton(container, event, days, weekIndex, onCancelled) {
        if (!container) return;
        // Remove any previously appended cancel button BEFORE the guard, so a
        // class that turned non-cancellable loses its stale button too.
        container.querySelectorAll('[data-cancel-class-btn]').forEach(function(b) { b.remove(); });
        if (!ClassCancellation.isCancellable(event, days, weekIndex)) return;  // S1–S5
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = this._hasBtnCancelClassStyle() ? 'btn-cancel-class' : 'btn-danger';
        btn.setAttribute('data-cancel-class-btn', '1');
        btn.textContent = 'Cancel Class?';
        /* 2026-11-05 UI/UX update (user): the hover tooltip states the real
           eligibility window — the earliest class date is 2 WORKING days back
           (weekends AND public holidays excluded) and every class from that
           date onward qualifies (the S4/S5 guards own the truth). Both lines
           ride the shared data-tip renderer's multi-line mode. */
        btn.setAttribute('data-tip',
            'You Can Cancel Classes Scheduled From ' + this._earliestEligibleLabel() + ' Onwards.' +
            '\nRule: The Earliest Class You Can CANCEL Is 2 Working Days Ago.');
        btn.addEventListener('click', function() {
            CancelClassModal.open(event, days, weekIndex, { onCancelled: onCancelled });
        });
        // Respect the footer's left/right grouping when the producer uses it
        // (same convention as openClassModal's "View Full Request" append).
        const rightGroup = container.querySelector('.modal-footer-right');
        (rightGroup || container).appendChild(btn);
    },
};



/* ═════ LastVisit — the dd memory (SDD last-visit-dd-memory) ═════
   Per-tab sessionStorage; the USER isolation lives in the id-suffixed key
   (the helper NEVER reads MockData identity on real-DB pages — the id
   arrives EXPLICITLY from each page's payload). try/catch-guarded: the
   memory is a nicety, private-mode/quota silence is the contract. */
const LastVisit = {
    key(name, id) {
        return 'lastvisit-' + name + '-' + (id || '');
    },
    remember(name, value, id) {
        try { sessionStorage.setItem(this.key(name, id), JSON.stringify(value)); }
        catch (e) {
            /* private-mode / quota — silence */
        }
    },
    recall(name, id) {
        try {
            const raw = sessionStorage.getItem(this.key(name, id));
            return raw === null ? null : JSON.parse(raw);
        } catch (e) { return null; }
    },
    forget(name, id) {
        try { sessionStorage.removeItem(this.key(name, id)); } catch (e) { /* silence */ }
    },
};
window.LastVisit = LastVisit;



/* ═════ TimetableResponsive — the ≤1279px chrome for the two timetable pages ═════
   SDD my-timetable-responsive-prompt (lane frozen 2026-11-15). Batch A = the shell
   (state, breakpoints, `data-tt-mode`); Batch B = the phone (day strip, agenda card,
   next-class hint — T4/T5/T6); Batch C = the tablet window (transposed grid, window
   cell — T7/T8); Batch D = the Week summary sheet (T9). The two blades still do not
   call init(): they opt in at T11 (staff, phase A) and T14 (student, phase B).

   The scope class `.tt-responsive` is the whole safety story: the page root carries
   it, every rule the lane adds to theme.css is prefixed with it, and this module
   only ever acts when it finds one.

   State law (design §3): ONE object, `{ width, day, mode }`. `mode` is
   'agenda' | 'window' | 'full'; `day` is 0..6 (Monday first) and is CLAMPED on every
   crossing, never reset, so a day selected at 900px is still the selected day at
   1300px and a 7-day week can never leave a stale index. The three matchMedia
   queries are the only listeners: the top bar is a fixed `var(--top-bar-height)`,
   so there is nothing to measure and no separate `resize` listener (design §3). */
const TimetableResponsive = {
    /* The page's config as passed by init() (design §6): { scope, pageKey, readonly,
       onEventClick, events(), days(), findNextClass(fromWeek, fromDay) }. Replaced on
       every init(), so a week change hands in the week the grid just drew. */
    _cfg: null,
    _bound: false,
    /* T11's extra behaviour, decided 2026-11-15: the FIRST init() opens the week on
       TODAY's index when this week contains today, else Monday. Set here (not in a
       blade) and only once, so a week change never yanks the reader off the day they
       picked. */
    _dayInitialised: false,
    /* one hidden placeholder per MOVED node, keyed by `#id` / `.class` — the
       SummarySheet discipline landing in T9: the same node is moved, never copied,
       and the placeholder is the exact seat it goes back to. */
    _placeholders: {},
    /* the sheet's controls are static markup, so they are bound exactly once; the
       module re-inits on every week change and must never double-bind. */
    _sheetBound: false,
    /* the one document keydown this module owns, while the sheet is open */
    _onKeyDown: null,

    /* The four bands live in the queries, not in a number: width is cached only for
       consumers that want to read it, never for a decision (R2). */
    _state: { width: 0, day: 0, mode: 'full' },

    /* (max-width: 767px) · (min-width: 768px) and (max-width: 1023px) ·
       (min-width: 1024px) and (max-width: 1279px). No match at ≥1280 — and that
       silence IS 'full'. */
    _mq: [],

    init: function (cfg) {
        this._cfg = cfg || {};

        /* buildTimetable() runs again on every week change, so init() must be
           idempotent: the cfg is replaced, the listeners are bound exactly once. */
        if (!this._bound) {
            this._bound = true;
            this._mq = [
                window.matchMedia('(max-width: 767px)'),
                window.matchMedia('(min-width: 768px) and (max-width: 1023px)'),
                window.matchMedia('(min-width: 1024px) and (max-width: 1279px)'),
            ];
            var self = this;
            var onChange = function () { self.sync(); };
            for (var i = 0; i < this._mq.length; i++) {
                if (this._mq[i].addEventListener) this._mq[i].addEventListener('change', onChange);
                else if (this._mq[i].addListener) this._mq[i].addListener(onChange);
            }

            /* R8: printing can never capture the sheet. The two moved nodes go back
               inline for the paper (the print rules in theme.css un-hide them there,
               because the shipped SummarySheet's own print unrelocate is gated off on
               these pages) and return into #ttSheetBody afterwards — the summary
               bar's own beforeprint/afterprint pattern. Bound once, beside the three
               queries, never per week. */
            window.addEventListener('beforeprint', function () { self._sheetBeforePrint(); });
            window.addEventListener('afterprint', function () { self._sheetAfterPrint(); });
        }

        /* First init only: land on today when the current week contains it, else
           Monday (index 0). Runs BEFORE sync(), which then renders the strip and the
           agenda for that day. */
        if (!this._dayInitialised) {
            this._dayInitialised = true;
            var days = this._days();
            var startDay = 0;
            for (var d = 0; d < days.length; d++) {
                if (this._isToday(days[d])) { startDay = d; break; }
            }
            this._state.day = startDay;
        }

        this.sync();
    },

    /* Which band are we in? Checked in width order, first match wins. */
    _mode: function () {
        if (this._mq[0] && this._mq[0].matches) return 'agenda';
        if (this._mq[1] && this._mq[1].matches) return 'window';
        if (this._mq[2] && this._mq[2].matches) return 'window';
        return 'full';
    },

    /* Mode + day clamp → the attribute the CSS keys on → the builder of that band.
       Runs at init, on each of the three media queries and after buildTimetable(). */
    sync: function () {
        var root = document.querySelector('.tt-responsive');
        /* No scope class = not this lane's page: leave it byte-identical (§10). */
        if (!root) return;

        var mode = this._mode();
        this._state.width = window.innerWidth;
        this._state.mode = mode;

        /* Clamp, never reset — a Wednesday stays a Wednesday across a crossing. */
        var day = parseInt(this._state.day, 10);
        if (isNaN(day)) day = 0;
        this._state.day = Math.min(6, Math.max(0, day));

        /* The CSS keys on `data-tt-mode` (three rules in theme.css); `data-tt-page`
           is the phase-B hook ('staff' | 'student'), set from cfg.pageKey so the
           blade and the module cannot disagree. */
        root.setAttribute('data-tt-mode', mode);
        if (this._cfg && this._cfg.pageKey) root.setAttribute('data-tt-page', this._cfg.pageKey);

        /* R6: the tools block ships `hidden` and only this module offers it, below
           1280 — so at ≥1280 the receipt is toBeHidden(), never toHaveCount(0). */
        var tools = document.getElementById('ttTools');
        if (tools) tools.hidden = mode === 'full';

        /* R2: the legacy 22-column table must never render under the agenda or the
           window, or the sideways scroll this lane exists to remove comes back.
           'full' restores exactly what the page had before this module ran. */
        var table = document.getElementById('timetable');
        if (table) table.style.display = mode === 'full' ? '' : 'none';

        /* The semester chip is the prototype's: `202605 Semester` on the phone and the
           dated range from 768 up (user, 2026-11-15, item #5). The chip belongs to the
           shared page header, so this only runs on a page carrying the scope class. */
        this._syncSemesterChip(mode);

        /* ≥1280 is today's page: nothing of this lane renders there. The window grid
           is a plain div outside its media block, so it is hidden by hand — unlike
           the strip/agenda/hint, which the base rules in theme.css already hide. */
        var winGrid = document.getElementById('ttWindowGrid');

        /* R6: below 1280 the sheet holds the legend + the cards, so its opener is
           offered; at >=1280 the sheet does not exist (proposal §9c) — this closes it
           and puts the two moved nodes back before the early return. */
        this._syncSheet(mode);

        if (mode === 'full') {
            if (winGrid) winGrid.hidden = true;
            return;
        }

        this.buildStrip();
        if (mode === 'agenda') {
            /* one surface at a time: a window left over from 900px must not paint
               under the agenda, and the empty agenda node collapses (`:empty`) */
            if (winGrid) winGrid.hidden = true;
            this.buildAgenda();
        } else {
            var agendaEl = document.getElementById('ttAgenda');
            if (agendaEl) agendaEl.innerHTML = '';
            this._buildWindow();
        }
    },

    /* ── Batch B/C surfaces (T4–T8) ───────────────────────────────────── */

    /* cfg.days() / cfg.events() are the page's OWN closures — they read the week
       the grid has just drawn, so the strip, the agenda and the window can never
       show a week the table does not. Both are guarded: a page rebuilding itself
       must never be left with a half-drawn surface (design §10). */
    _days: function () {
        var cfg = this._cfg || {};
        try { return (typeof cfg.days === 'function' && cfg.days()) || []; } catch (e) { return []; }
    },

    _events: function () {
        var cfg = this._cfg || {};
        try { return (typeof cfg.events === 'function' && cfg.events()) || []; } catch (e) { return []; }
    },

    /* `day.date` is generateWeekData's `D Mon YYYY`; parse it to LOCAL midnight and
       compare against getTodayMs() — the same anchor MockData.mockNow feeds (the
       blades set mockNow = null for the real clock), so "today" can never drift.
       Returns null when the string is not a date. */
    _dayDate: function (day) {
        var MONTHS = { jan: 0, feb: 1, mar: 2, apr: 3, may: 4, jun: 5,
            jul: 6, aug: 7, sep: 8, oct: 9, nov: 10, dec: 11 };
        var m = String((day && day.date) || '').match(/^(\d{1,2})[\s-]([A-Za-z]{3,})[\s-](\d{4})$/);
        if (!m) return null;
        var mo = MONTHS[m[2].slice(0, 3).toLowerCase()];
        if (mo === undefined) return null;
        var dt = new Date(parseInt(m[3], 10), mo, parseInt(m[1], 10));
        if (dt.getFullYear() !== parseInt(m[3], 10) || dt.getMonth() !== mo) return null;
        return dt;
    },

    _isToday: function (day) {
        if (!day) return false;
        if (day.today === true) return true;          /* the payload's own flag */
        var dt = this._dayDate(day);
        return !!dt && dt.getTime() === getTodayMs();
    },

    /* The R7 accessible name needs `Monday`, not `Mon`. DateHelper.weekdayName
       derives it FROM THE DATE (the 2026-11-11 lesson: an index-derived name
       prints one day early), so it is the helper to reuse here. */
    _fullDayName: function (day) {
        var fromDate = DateHelper.weekdayName(day && day.date);
        if (fromDate) return fromDate;
        var FULL = { Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday',
            Fri: 'Friday', Sat: 'Saturday', Sun: 'Sunday' };
        return (day && (FULL[day.abbr] || day.abbr)) || '';
    },

    /* `10:00 AM ~ 11:00 AM` — the exact expression makeEventBlock() uses, so the
       card, the window cell and the desktop block can never print a different
       clock. Reuses to12h(), the shared `hours` axis and DateHelper.add30min(). */
    _timeRange: function (e) {
        var startT = to12h(hours[e.start]);
        var endT = to12h(hours[e.end + 1] || add30min(hours[e.end]));
        return startT + ' ~ ' + endT;
    },

    /* The SPOKEN status (aria-label / tip), never the visible word: R4 gives the
       long form `Class Cancelled` to the modal Status row and to the agenda card's
       spoken status, while every visible block keeps the short one (a 60–110px cell
       cannot carry it). Reuses eventStatusLabel() — the one status vocabulary. */
    _spokenStatus: function (e, isHoliday) {
        if (isHoliday) return eventStatusLabel(e, true);           /* 'Public Holiday' */
        if (e.status === 'cancelled') return 'Class Cancelled';    /* spec R4, spoken only */
        return eventStatusLabel(e, false);
    },

    /* The status word's GLYPH, drawn rather than typed (user, 2026-11-15, prototype
       comparison #9): `\u2715` and `\u26a0` are text characters, so they render at
       different weights and heights on different phones. One 12px inline SVG per kind,
       in the block's own colour via `currentColor`, looks the same everywhere. */
    _statusIcon: function (kind) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'tt-status-icon');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2.5');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('stroke-linejoin', 'round');
        svg.setAttribute('aria-hidden', 'true');
        var n1, n2;
        if (kind === 'cancelled') {
            n1 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            n1.setAttribute('x1', '6'); n1.setAttribute('y1', '6');
            n1.setAttribute('x2', '18'); n1.setAttribute('y2', '18');
            n2 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
            n2.setAttribute('x1', '18'); n2.setAttribute('y1', '6');
            n2.setAttribute('x2', '6'); n2.setAttribute('y2', '18');
        } else {
            n1 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            n1.setAttribute('d', 'M12 3 2.6 20h18.8L12 3z');
            n2 = document.createElementNS('http://www.w3.org/2000/svg', 'path');
            n2.setAttribute('d', 'M12 9v5M12 17.2v.2');
        }
        svg.appendChild(n1);
        svg.appendChild(n2);
        return svg;
    },

    /* The hover tip, composed exactly as design §6 states:
       `[name, [tipCtx, tipLect, status].filter(Boolean).join(' · ')]` joined the
       same way — the shape the flag's tip migration gives the desktop block, with the
       lecturer segment empty on these pages (the viewer is the lecturer; the segment
       stays in the expression so phase B composes identically). A page may supply both
       pieces through cfg.tooltipExtra / cfg.tipLecturerFn, exactly as it supplies them
       to buildTimetableGrid; absent, the sentence is `AMSE2003 … · CANCELLED`. */
    _tipFor: function (e, isHoliday) {
        var cfg = this._cfg || {};
        var tipCtx = (typeof cfg.tooltipExtra === 'function') ? (cfg.tooltipExtra(e) || '') : '';
        var tipLect = (typeof cfg.tipLecturerFn === 'function') ? (cfg.tipLecturerFn(e) || '') : '';
        var status = eventStatusLabel(e, isHoliday).toUpperCase();
        return [e.name, [tipCtx, tipLect, status].filter(Boolean).join(' · ')].filter(Boolean).join(' · ');
    },

    /* The cards of one day that share time with `e` — inclusive half-hour indices,
       the prototype's test. An identical eventKey is the SAME identity (the house
       dedupe law), so a merged-cohort duplicate can never fake a clash. */
    _othersOverlapping: function (e, list) {
        return (list || []).filter(function (o) {
            if (!o || o === e) return false;
            if (e.eventKey !== undefined && e.eventKey !== null && e.eventKey !== ''
                && o.eventKey === e.eventKey) return false;
            return o.start <= e.end && e.start <= o.end;
        });
    },

    /* `Overlaps 10:30 AM ~ 12:30 PM · BMCS2023 (T)` when exactly one other class,
       `Overlaps 2 other classes` when several — the two approved forms (R3/R3b),
       and the same words are appended to aria-label. */
    _overlapText: function (e, others) {
        if (!others || !others.length) return '';
        if (others.length === 1) {
            var o = others[0];
            return 'Overlaps ' + this._timeRange(o) + ' · '
                + (o.code || '') + (o.type ? ' (' + o.type + ')' : '');
        }
        return 'Overlaps ' + others.length + ' other classes';
    },

    /* ── T5/T8 — THE CARD (one builder, two surfaces) ──
       Frozen design §4.2 markup: `.tt-card-top` (.tt-time / .tt-venue), `.tt-course`
       (code + type), `.tt-note` (the date note + ONE status word) and `.tt-overlap`
       on a clash. The SAME builder feeds the phone agenda and the tablet window, so
       both carry the same focusable <button>, the same aria-label and the same
       data-tip; only the CSS sizes differ (14px phone tiers, the brief's 11/10/9.5px
       tablet tiers — design §5 / R3b). The subject NAME is never on the card: it
       stays in aria-label, the tip and the details modal (R3).

       Reused: to12h/hours/add30min through _timeRange(), buildReplacementNote() for
       the note wording, eventStatusLabel() for the status words. NOT reused because
       no helper exists: buildTimetableGrid()'s colour ladder is inline inside
       makeEventBlock(), so the ladder below mirrors it arm for arm — and defers to a
       page's cfg.statusClassFn when one is passed, the same contract the builder has.
       `cfg.readonly` needs no branch: a card ONLY opens the details modal, so phase B
       (read-only) and phase A behave identically and no cancel affordance is ever
       attached here (R9). */
    _card: function (e, day, dayEvents) {
        var cfg = this._cfg || {};
        var isHoliday = !!(day && day.holiday);
        var others = this._othersOverlapping(e, dayEvents);
        var overlap = this._overlapText(e, others);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'event-block tt-card';
        if (overlap) btn.classList.add('is-overlap');

        /* Colour ladder — makeEventBlock()'s, arm for arm. A holiday day's block is
           the CONFLICT red on these two personal pages (that is what the live grid
           paints, and what the approved preview's statusMeta says);
           `event-public-holiday` is the browse pages' "not yours" hairline and is
           deliberately NOT used here. */
        if (typeof cfg.statusClassFn === 'function') {
            cfg.statusClassFn(btn, e, isHoliday);
        } else if (e.status === 'cancelled') {
            btn.classList.add('event-conflict');
        } else if (isHoliday || e.status === 'conflict') {
            btn.classList.add('event-conflict');
        } else if (e.status === 'normal') {
            btn.classList.add('event-normal');
        } else if (e.status === 'replacement') {
            btn.classList.add('event-replacement');
        } else if (e.status === 'pending') {
            btn.classList.add('event-pending');
        }

        /* Identity rides the card exactly as it rides a desktop block: data-id is
           the design's hook, data-sid the session the page's own modal reads.
           `__eventData` too — the shared key seam (initTimetableKeyboardHandlers)
           reads exactly that field, preventDefaults the key and opens the modal;
           without it an Enter/Space on a card was swallowed with nothing to show
           (found by T16a's keyboard receipt, not by the design). */
        btn.__eventData = e;
        btn.dataset.id = String(e.id == null ? '' : e.id);
        btn.dataset.sid = String(e.sessionId == null ? (e.id == null ? '' : e.id) : e.sessionId);
        var tip = this._tipFor(e, isHoliday);
        if (tip) btn.dataset.tip = tip;

        var spoken = [
            this._timeRange(e),
            (e.code || '') + (e.type ? ' (' + e.type + ')' : ''),
            e.name || '',
            e.venue || '',
            this._spokenStatus(e, isHoliday)
        ].filter(Boolean).join(', ');
        if (overlap) spoken += ', ' + overlap;
        btn.setAttribute('aria-label', spoken);

        /* PRIMARY tier — the time range and the venue code, one size and weight */
        var top = document.createElement('span');
        top.className = 'tt-card-top';
        var timeEl = document.createElement('span');
        timeEl.className = 'tt-time';
        timeEl.textContent = this._timeRange(e);
        var venueEl = document.createElement('span');
        venueEl.className = 'tt-venue';
        venueEl.textContent = e.venue || '';
        top.appendChild(timeEl);
        top.appendChild(venueEl);
        btn.appendChild(top);

        /* SECONDARY tier — the course code + type, smaller and grey. The subject
           name is NOT here (R3): it lives in the spoken label, the tip, the modal. */
        var course = document.createElement('span');
        course.className = 'tt-course';
        course.textContent = (e.code || '') + (e.type ? ' (' + e.type + ')' : '');
        btn.appendChild(course);

        /* TERTIARY tier — the note line: at most the date note plus ONE status word.
           The date note is the house wording (buildReplacementNote: `(Pending since
           …)` / `(Cancelled)` / `(Replaced for …)` / `(Proposed ⏳)`), read from the
           page's own replacementNoteFn when it passes one so the card and the grid
           can never diverge. Only Cancelled/Conflict print a word, and a public
           holiday prints none at all — the day itself is the label (R3). */
        var noteHtml = '';
        try {
            noteHtml = (typeof cfg.replacementNoteFn === 'function')
                ? (cfg.replacementNoteFn(e) || '')
                : buildReplacementNote(e);
        } catch (err) { noteHtml = ''; }
        var wordStatus = (!isHoliday && (e.status === 'cancelled' || e.status === 'conflict')) ? e.status : null;

        /* R3 caps the note line at "the date note and ONE status word" — so the
           word REPLACES the legacy status note it duplicates. A cancelled class
           used to read `(Cancelled) ✕ Cancelled` on one line (caught by the
           verify audit's browser look); a date note (pending since …, replaced
           for …, proposed) is a different fact and stays beside the word. */
        if (wordStatus && /Cancelled|Conflict/.test(noteHtml.replace(/<[^>]*>/g, ''))) {
            noteHtml = '';
        }

        if (noteHtml || wordStatus) {
            var note = document.createElement('span');
            note.className = 'tt-note';
            if (noteHtml) {
                /* the note contract is HTML (buildReplacementNote returns its own
                   <span class="ev-note">), so it is the one innerHTML here */
                note.innerHTML = noteHtml;
            }
            if (wordStatus) {
                var word = document.createElement('span');
                word.className = 'tt-status status-' + wordStatus;
                word.appendChild(this._statusIcon(wordStatus));
                word.appendChild(document.createTextNode(
                    wordStatus === 'cancelled' ? 'Cancelled' : 'Conflict'));
                note.appendChild(word);
            }
            btn.appendChild(note);
        }

        if (overlap) {
            var line = document.createElement('span');
            line.className = 'tt-overlap';
            line.textContent = overlap;
            btn.appendChild(line);
        }

        /* The card opens the page's OWN details modal — the same handler a desktop
           block calls, so nothing about the modal is re-implemented here. */
        btn.addEventListener('click', function () {
            if (typeof cfg.onEventClick === 'function') cfg.onEventClick(e, e.di);
        });
        return btn;
    },

    /* ── T4 — the day strip ── */
    /* The live payload's `semester.label` is the bare code (`202605`) while the preview
       writes `202605 Semester`; the word is added here when the data omits it, so the
       chip looks like the prototype without editing the payload. */
    _semesterShort: function () {
        var sem = null;
        try { sem = (window.MockData && window.MockData.semester) || null; } catch (err) { sem = null; }
        if (!sem) return '';
        var label = String(sem.label || '').trim();
        if (!label) return '';
        return /semester/i.test(label) ? label : label + ' Semester';
    },

    /* The range half of `chipText` (`<code> · <dd Mon yyyy> ~ <dd Mon yyyy>`), so a
       subtitle never repeats the code it already printed. */
    _semesterRange: function () {
        var sem = null;
        try { sem = (window.MockData && window.MockData.semester) || null; } catch (err) { sem = null; }
        if (!sem || !sem.chipText) return '';
        var parts = String(sem.chipText).split(' · ');
        return parts.length > 1 ? parts.slice(1).join(' · ') : '';
    },

    /* `202605 Semester` in agenda mode, the dated range otherwise — read from the same
       MockData the page's own boot writes, so the two can never disagree. */
    _syncSemesterChip: function (mode) {
        var chip = document.getElementById('semesterChip');
        if (!chip) return;
        var sem = null;
        try { sem = (window.MockData && window.MockData.semester) || null; } catch (err) { sem = null; }
        if (!sem) return;
        var text = (mode === 'agenda')
            ? this._semesterShort()
            : (sem.chipText || this._semesterShort());
        if (text && chip.textContent !== text) chip.textContent = text;
    },

    buildStrip: function () {
        var strip = document.getElementById('ttDayStrip');
        if (!strip) return;
        var days = this._days();
        var events = this._events();
        var selected = this._state.day;
        var self = this;

        strip.innerHTML = '';
        days.forEach(function (d, di) {
            var count = events.filter(function (e) { return e && Number(e.di) === di; }).length;
            var isToday = self._isToday(d);
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tt-day'
                + (isToday ? ' is-today' : '')
                + (d && d.holiday ? ' is-holiday' : '');
            /* no `is-ahead` any more: the user reversed the dimming on 2026-11-15
               (a chip after today read grey and looked disabled). */
            btn.dataset.day = String(di);
            btn.setAttribute('aria-pressed', String(di === selected));
            /* aria-current="date" rides the REAL today, whether or not it is the
               selected day (user, 2026-11-15, prototype comparison #8 — the preview
               marks today always). A blind user should be able to find today in the
               strip even while looking at another day; `aria-pressed` still says which
               day is the selection, so the two never claim the same thing. */
            if (isToday) btn.setAttribute('aria-current', 'date');

            /* R7 accessible name, MERGED from the prototype and the shipped wording
               (user, 2026-11-15, prototype comparison #7 option C):
                 `Monday, 9 Nov 2026, 1 class, Today, Deepavali Holiday (In Lieu)`
               — the date and the class count from the shipped label, `Today` and the
               holiday's own NAME from the preview. `selected` is gone because
               aria-pressed already carries it, and a generic "public holiday" became
               the holiday's real name. The state lives in the label, not in the chip's
               pixels, so it stays testable. */
            var name = self._fullDayName(d);
            if (d && d.date) name += ', ' + d.date;
            name += ', ' + count + ' ' + (count === 1 ? 'class' : 'classes');
            if (isToday) name += ', Today';
            if (d && d.holiday) name += ', ' + (d.holidayLabel || 'public holiday');
            btn.setAttribute('aria-label', name);
            /* the same facts as attributes, so a receipt never has to parse prose */
            btn.dataset.count = String(count);
            btn.dataset.holiday = d && d.holiday ? '1' : '0';
            btn.dataset.today = isToday ? '1' : '0';

            /* The chip is the PREVIEW's, not an inversion of it: the abbreviation
               is the small line and the DAY OF THE MONTH is the bold one
               (`Mon` / `9`), so a chip answers "which day is this?" without
               counting classes. The class count stays in the accessible name
               (R7) — it is data a screen reader wants and a 44px chip cannot
               spend two lines on. User ruling 2026-11-15: the date, not the
               count. Found by reading the preview's own renderer
               (`<abbr>${d.abbr}<strong>${date}</strong>`) after the user asked
               why the chip showed `Mon 2`. */
            btn.appendChild(document.createTextNode((d && d.abbr) || ''));
            var dayNum = document.createElement('strong');
            var dm = String((d && d.date) || '').match(/^(\d{1,2})\b/);
            dayNum.textContent = dm ? dm[1] : (d && d.num != null ? String(d.num) : '');
            btn.appendChild(dayNum);
            strip.appendChild(btn);
        });

        /* ONE delegated listener, bound once: buildStrip() re-renders the chips on
           every sync, so per-chip listeners would have to be re-attached forever. */
        if (!this._stripBound) {
            this._stripBound = true;
            strip.addEventListener('click', function (ev) {
                var chip = ev.target && ev.target.closest ? ev.target.closest('[data-day]') : null;
                if (!chip || !strip.contains(chip)) return;
                var i = parseInt(chip.getAttribute('data-day'), 10);
                if (isNaN(i)) return;
                self.setDay(i);          /* the selected day is a no-op (R5b) */
            });
        }
    },

    /* ── T5 — the agenda (≤767) ── */
    buildAgenda: function () {
        var host = document.getElementById('ttAgenda');
        if (!host) return;
        var days = this._days();
        var events = this._events();
        var di = this._state.day;
        var day = days[di] || null;
        /* time order, then the shortest first, then code — a stable, readable list */
        var list = events.filter(function (e) { return e && Number(e.di) === di; })
            .sort(function (a, b) {
                return (a.start - b.start) || (a.end - b.end)
                    || String(a.code || '').localeCompare(String(b.code || ''));
            });
        var self = this;
        host.innerHTML = '';
        /* The approved preview's EMPTY STATES, not a red card on a holiday: a
           public-holiday day shows one panel (`Public Holiday · <label>` + the
           date + the next class), and a day with no classes shows the shipped
           empty-state shell with the same hint inside it (user, 2026-11-15:
           "Public Holiday shud shows this", with the preview as the sample).
           Cards only ever render on a day that has classes. */
        if (day && day.holiday) {
            host.appendChild(this._emptyPanel(day, true));
        } else if (!list.length) {
            host.appendChild(this._emptyPanel(day, false));
        } else {
            list.forEach(function (e) { host.appendChild(self._card(e, day, list)); });
        }
        this._syncTools();
    },

    /* ── The empty/holiday panel (preview markup: ui-empty-state's own shell) ── */
    _emptyPanel: function (day, isHoliday) {
        var panel = document.createElement('div');
        panel.className = 'empty-state tt-empty' + (isHoliday ? ' tt-holiday' : '');

        var icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('class', 'empty-icon');
        icon.setAttribute('viewBox', '0 0 24 24');
        icon.setAttribute('fill', 'none');
        icon.setAttribute('stroke', 'currentColor');
        icon.setAttribute('stroke-width', '2');
        icon.setAttribute('stroke-linecap', 'round');
        icon.setAttribute('stroke-linejoin', 'round');
        icon.setAttribute('aria-hidden', 'true');
        var cal = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        cal.setAttribute('x', '3'); cal.setAttribute('y', '4');
        cal.setAttribute('width', '18'); cal.setAttribute('height', '18');
        cal.setAttribute('rx', '2');
        var l1 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        l1.setAttribute('x1', '16'); l1.setAttribute('y1', '2');
        l1.setAttribute('x2', '16'); l1.setAttribute('y2', '6');
        var l2 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        l2.setAttribute('x1', '8'); l2.setAttribute('y1', '2');
        l2.setAttribute('x2', '8'); l2.setAttribute('y2', '6');
        var l3 = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        l3.setAttribute('x1', '3'); l3.setAttribute('y1', '10');
        l3.setAttribute('x2', '21'); l3.setAttribute('y2', '10');
        [cal, l1, l2, l3].forEach(function (n) { icon.appendChild(n); });
        panel.appendChild(icon);

        var title = document.createElement('h3');
        title.className = 'empty-title';
        var label = (day && day.holidayLabel) ? String(day.holidayLabel) : '';
        title.textContent = isHoliday
            ? ('Public Holiday' + (label ? ' · ' + label : ''))
            : ('No classes on ' + this._fullDayName(day));
        panel.appendChild(title);

        /* the preview prints the date line on the HOLIDAY panel only — an empty
           weekday already says which day it is in its own title */
        if (isHoliday) {
            var dateLine = document.createElement('p');
            dateLine.className = 'empty-text';
            dateLine.textContent = this._fullDayName(day) + ', ' + ((day && day.date) || '');
            panel.appendChild(dateLine);
        }

        var hint = this._nextHintNode();
        if (hint) panel.appendChild(hint);
        return panel;
    },

    /* The hint inside the panel, in the preview's own shape: a small
       `⏱ Next class →` caption over one line `Tue 10 Nov · 8–10 AM` and the
       venue chip. `No more classes this semester.` when the scan finds none. */
    _nextHintNode: function () {
        var cfg = this._cfg || {};
        var res = null;
        if (typeof cfg.findNextClass === 'function') {
            try { res = cfg.findNextClass(this._currentWeek(), this._state.day); } catch (e) { res = null; }
        }
        if (!res || res.week == null || res.day == null) {
            var none = document.createElement('p');
            none.className = 'tt-next-hint';
            none.textContent = 'No more classes this semester.';
            return none;
        }
        /* The PAGE hands back the day's own labels (`res.date` / `res.abbr`). Falling
           back to the current week's `days()` is what produced a hint labelled
           "Tue 10 Nov" from week 7 with a week-8 target (found by T16a). */
        var days = this._days();
        var target = days[parseInt(res.day, 10)] || null;
        var ev = res.event || null;
        /* `10 Nov` — the preview drops the year in the hint ("Tue 10 Nov · 8–10 AM"),
           because the year is on the page once and never ambiguous in a week hint */
        var when = String(res.date || (target && target.date) || '').replace(/\s+\d{4}\s*$/, '');
        var abbr = String(res.abbr || (target && target.abbr) || '');

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'tt-next-hint';
        btn.dataset.nextWeek = String(res.week);
        btn.dataset.nextDay = String(res.day);
        btn.setAttribute('aria-label', 'Go to ' + this._fullDayName(target) + ', '
            + String(res.date || (target && target.date) || ''));

        var caption = document.createElement('span');
        caption.className = 'tt-next-caption';
        caption.appendChild(this._clockIcon());
        caption.appendChild(document.createTextNode('Next class '));
        var arrow = document.createElement('span');
        arrow.setAttribute('aria-hidden', 'true');
        arrow.textContent = '→';
        caption.appendChild(arrow);
        btn.appendChild(caption);

        var details = document.createElement('span');
        details.className = 'tt-next-details';
        var line = document.createElement('span');
        line.appendChild(document.createTextNode((abbr ? abbr + ' ' : '') + when));
        if (ev && typeof ev.start === 'number' && typeof ev.end === 'number') {
            line.appendChild(document.createTextNode(' · '));
            var strong = document.createElement('strong');
            strong.textContent = this._shortTime(ev.start, ev.end);
            line.appendChild(strong);
        }
        details.appendChild(line);
        if (ev && ev.venue) {
            var venue = document.createElement('span');
            venue.className = 'tt-next-venue';
            venue.setAttribute('aria-label', 'Venue ' + ev.venue);
            venue.textContent = ev.venue;
            details.appendChild(venue);
        }
        btn.appendChild(details);

        var self = this;
        btn.addEventListener('click', function () { self.nextClass(); });
        return btn;
    },

    _clockIcon: function () {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'tt-next-icon');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('fill', 'none');
        svg.setAttribute('stroke', 'currentColor');
        svg.setAttribute('stroke-width', '2');
        svg.setAttribute('stroke-linecap', 'round');
        svg.setAttribute('aria-hidden', 'true');
        var c = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        c.setAttribute('cx', '12'); c.setAttribute('cy', '12'); c.setAttribute('r', '9');
        var p1 = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
        p1.setAttribute('points', '12 7 12 12 16 14');
        svg.appendChild(c); svg.appendChild(p1);
        return svg;
    },

    /* `8–10 AM` when both ends share a meridiem, `11:30 AM–1 PM` when they do not
       — the preview's own shortTimeOf(), same two rules. */
    _shortTime: function (start, end) {
        var a = to12h(hours[start]);
        var b = to12h(hours[end + 1] || add30min(hours[end]));
        var compact = function (v) { return String(v).replace(':00', ''); };
        return compact(a.slice(-2) === b.slice(-2) ? a.slice(0, -3) : a) + '–' + compact(b);
    },

    /* The tools row's heading + count (design §4.1 ships both empty; the module fills
       them for the surface it just drew). Same words the approved preview uses. */
    _syncTools: function () {
        var days = this._days();
        var events = this._events();
        var heading = document.getElementById('ttDayHeading');
        var countEl = document.getElementById('ttDayCount');
        var di = this._state.day;
        if (this._state.mode === 'agenda') {
            var n = events.filter(function (e) { return e && Number(e.di) === di; }).length;
            /* `Monday, 9 Nov 2026` — the prototype names the day AND its date, so the
               heading answers "which Monday is this?" without reading the strip
               (user, 2026-11-15: follow the prototype, item #1). */
            if (heading) {
                var day0 = days[di] || null;
                var label = this._fullDayName(day0) || '';
                if (day0 && day0.date) label += ', ' + day0.date;
                heading.textContent = label;
            }
            if (countEl) countEl.textContent = n + (n === 1 ? ' class this day' : ' classes this day');
            return;
        }
        var size = this._windowSize();
        var start = this._windowStart(size);
        var first = days[start], last = days[start + size - 1];
        if (heading) heading.textContent = ((first && first.abbr) || '') + ' ~ ' + ((last && last.abbr) || '');
        if (countEl) countEl.textContent = size + ' days visible';
    },

    /* ── T7 — the transposed window grid (768–1279) ──
       N = 2 at 768–1023, 3 at 1024–1279; the window starts at the selected day and
       is clamped so it never runs past Sunday: start = min(day, 7 - N). Rendered into
       #ttWindowGrid, and the legacy #timetable is hidden by sync() — the real table
       has days as ROWS and 22 half-hour columns, so hiding rows could never look like
       the approved transposed grid (design §5). */
    _windowSize: function () {
        if (this._mq[2] && this._mq[2].matches) return 3;
        if (this._mq[1] && this._mq[1].matches) return 2;
        return window.innerWidth >= 1024 ? 3 : 2;
    },

    _windowStart: function (size) {
        return Math.max(0, Math.min(this._state.day, 7 - size));
    },

    _buildWindow: function () {
        var grid = document.getElementById('ttWindowGrid');
        if (!grid) return;
        var days = this._days();
        var events = this._events();
        var size = this._windowSize();
        var start = this._windowStart(size);
        var self = this;

        grid.hidden = false;
        /* the ONE custom property the CSS template reads (theme.css §window mode) */
        grid.style.setProperty('--tt-day-count', String(size));
        grid.innerHTML = '';

        function addCell(cls, col, row, text) {
            var cell = document.createElement('div');
            cell.className = cls;
            cell.style.gridColumn = String(col);
            cell.style.gridRow = String(row);
            if (text !== undefined) cell.textContent = text;
            grid.appendChild(cell);
            return cell;
        }

        /* the 62px header row: a corner label + one column per visible day */
        addCell('tt-whead', 1, 1, 'Time');
        for (var i = 0; i < size; i++) {
            var head = addCell('tt-whead', i + 2, 1);
            var d = days[start + i] || {};
            head.appendChild(document.createTextNode(d.abbr || ''));
            var dateLine = document.createElement('small');
            dateLine.textContent = d.date || '';
            head.appendChild(dateLine);
            if (d.holiday) {
                var hol = document.createElement('small');
                hol.textContent = 'Public Holiday';
                head.appendChild(hol);
            }
        }

        /* the time axis down the left: the same 22 half-hour rows the grid uses, so
           each of the 11 hours is two rows (design §5) */
        for (var slot = 0; slot < hours.length; slot++) {
            addCell('tt-axis', 1, slot + 2, hours[slot]);
            for (var c = 0; c < size; c++) addCell('tt-gcell', c + 2, slot + 2);
        }

        /* one cell per event, placed by day index and half-hour span. The shared
           lane planner decides the side-by-side split (cancelled history stacks
           above the live class it overlaps, exactly as on the desktop grid) — two
           classes sharing time must never be drawn on top of each other. */
        for (var col = 0; col < size; col++) {
            var di = start + col;
            var day = days[di] || null;
            var list = events.filter(function (e) { return e && Number(e.di) === di; });
            var lay = layoutTimetableDay(list).days[String(di)] || { placed: [], lanes: 1 };
            var lanes = Math.max(1, lay.lanes || 1);
            lay.placed.forEach(function (p) {
                var e = p.event;
                var card = self._card(e, day, list);
                var span = Math.max(1, p.span || (e.end - e.start + 1));
                var geom = 'grid-column:' + (col + 2)
                    + ';grid-row:' + (e.start + 2) + ' / span ' + span;
                if (lanes > 1) {
                    geom += ';justify-self:start;width:calc(100% / ' + lanes + ' - 4px)'
                        + ';margin-left:calc(100% * ' + (p.lane || 0) + ' / ' + lanes + ')';
                }
                card.setAttribute('style', geom);
                grid.appendChild(card);
            });
        }

        this._syncTools();
        this._buildNextHint();
    },

    /* ── T6 — the next-class hint ──
       Only an EMPTY selected day or a PUBLIC HOLIDAY offers it (R5); any other day
       hides it. cfg.findNextClass(fromWeek, fromDay) is the page's cross-week source
       (events() is the current week only), so the hint is rendered by ASKING it —
       design §4.4 calls the result "that event's week", and the label needs that
       event's start time and code, so `res.event` is used when the page supplies it
       and the hint degrades to the weekday alone when it does not. A page without
       the knob gets no hint at all (graceful, R5). */
    _currentWeek: function () {
        var w = parseInt(window.currentWeek, 10);
        if (!isNaN(w)) return w;
        try { return currentWeekIndex(); } catch (e) { return 0; }
    },

    /* Retired 2026-11-15: the hint now rides INSIDE the empty/holiday panel
       (`_nextHintNode()`), exactly as the preview draws it. The `#ttNextHint`
       node still ships in the chrome partial, so this keeps it hidden rather
       than leaving a stale row under the panel. */
    _buildNextHint: function () {
        var legacy = document.getElementById('ttNextHint');
        if (legacy) { legacy.innerHTML = ''; legacy.hidden = true; }
        return;
    },


    /* ── T4 — day selection ──
       Re-renders the strip and the surface for a new day. A tap on the day that is
       ALREADY selected does nothing at all — no re-render, no scroll, no focus jump
       (R5b). In window mode `_buildWindow()` moves the window so the tapped day is
       its first column, clamped at Sunday. */
    setDay: function (i) {
        var day = parseInt(i, 10);
        if (isNaN(day)) return;
        day = Math.max(0, Math.min(6, day));
        if (day === this._state.day) return;
        this._state.day = day;
        if (this._state.mode === 'full') return;     /* ≥1280 renders nothing of ours */
        this.buildStrip();
        if (this._state.mode === 'agenda') this.buildAgenda();
        else this._buildWindow();
    },

    /* ── T6 — the jump: week → day → focus + scroll ──
       THE ROUTE, stated exactly (task T6 asked for it in a comment): both timetable
       blades export `window.selectWeek(index)`, which is `weekNav.selectWeek(index)`
       + `window.currentWeek` + the session memory. WeekNavigator.selectWeek() re-values
       #weekSelect, calls the page's own `buildTimetable()` (the same hook the week
       <select> fires on change) and saves. That is the minimal existing route: no new
       endpoint, no blade edit, and no second data shape — cfg.events()/cfg.days() close
       over the page's `currentWeek`, so after the call they hand back the NEW week.
       `window.nextWeek()`/`window.prevWeek()` remain the fallback for a page that has
       not exported selectWeek. */
    nextClass: function () {
        var cfg = this._cfg || {};
        if (typeof cfg.findNextClass !== 'function') return;
        var fromWeek = this._currentWeek();
        var res = null;
        try { res = cfg.findNextClass(fromWeek, this._state.day); } catch (e) { res = null; }
        if (!res) return;
        var week = parseInt(res.week, 10);
        var day = parseInt(res.day, 10);
        if (isNaN(week) || isNaN(day)) return;

        if (typeof window.selectWeek === 'function') {
            window.selectWeek(week);
        } else {
            /* fallback for a page that has not exported selectWeek: step week by week
               with the hooks these blades do export, bounded so a page that never
               moves falls through instead of spinning */
            var guard = 0;
            while (this._currentWeek() !== week && guard++ < 60) {
                if (this._currentWeek() < week && typeof window.nextWeek === 'function') window.nextWeek();
                else if (this._currentWeek() > week && typeof window.prevWeek === 'function') window.prevWeek();
                else break;
            }
        }

        /* The page's rebuild re-enters init() once T11/T14 land; sync() here is
           idempotent and keeps a standalone probe page working. */
        this.sync();
        this.setDay(day);

        var heading = document.getElementById('ttDayHeading');
        if (heading) {
            heading.setAttribute('tabindex', '-1');
            if (typeof heading.focus === 'function') heading.focus({ preventScroll: true });
            if (typeof heading.scrollIntoView === 'function') {
                heading.scrollIntoView({ block: 'center', behavior: 'instant' });
            }
        }
    },

    /* ═════ T9 — the Week summary sheet (R6, design §6) ═══════════════════════════
       Below 1280 theme.css hides the page's own `.legend-bar` and `#summarySection`
       on screen, so the summary lives behind `#ttSummaryButton`. The sheet MOVES those
       two nodes — the SAME node, never a copy, each with a hidden placeholder holding
       its seat so the restore is exact (the SummarySheet discipline) — and BUILDS one
       extra line, `#ttSheetRange`, from MockData.semester.chipText. The header's
       #semesterChip keeps its own copy, which is why the range line is generated and
       not relocated: there is no range NODE on the page to move.
       Body order is fixed and asserted by spec R6: legend → the five cards → range.

       One overlay at a time: openSheet() closes the bell below; the bell closes this
       sheet from inside openNotifPanel()'s own code. (No FiltersPanel line here on
       purpose — neither timetable page opts into it, and this file must not grow a
       second half-wired pairing.) */

    _sheet: function () { return document.getElementById('ttSheet'); },
    _sheetBody: function () { return document.getElementById('ttSheetBody'); },
    _legendNode: function () { return document.querySelector('.legend-bar'); },
    _summaryNode: function () { return document.getElementById('summarySection'); },

    /* The resting/offered state, run from sync(): below 1280 the opener is offered and
       the controls are bound (once); at >=1280 the sheet does not exist, so it is
       closed and the two nodes go home. A page without the nodes hides the button and
       openSheet() no-ops (design §10) — it never throws and never half-opens. */
    _syncSheet: function (mode) {
        var sheet = this._sheet();
        var body = this._sheetBody();
        var btn = document.getElementById('ttSummaryButton');
        if (!sheet || !body) { if (btn) btn.hidden = true; return; }

        this._bindSheetControls();

        if (!this._legendNode() || !this._summaryNode()) { if (btn) btn.hidden = true; return; }
        if (btn) btn.hidden = false;

        if (mode === 'full') this.closeSheet(false);
    },

    /* The shell ships the three controls and binds none of them (no inline onclick is
       allowed on the chrome partial), so the module binds them — idempotently, because
       sync() runs again on every week change and on every breakpoint crossing. */
    _bindSheetControls: function () {
        if (this._sheetBound) return;
        var sheet = this._sheet();
        if (!sheet) return;
        this._sheetBound = true;
        var self = this;

        var btn = document.getElementById('ttSummaryButton');
        if (btn && !btn.hasAttribute('onclick')) {
            btn.addEventListener('click', function () { self.openSheet(); });
        }

        var close = sheet.querySelector('.tt-sheet-close');
        if (close) close.addEventListener('click', function () { self.closeSheet(); });

        /* The veil closes; a click inside the dialog never does (same test the house
           overlays use: the event target must BE the overlay). */
        sheet.addEventListener('click', function (e) {
            if (e.target === sheet) self.closeSheet();
        });
    },

    openSheet: function () {
        var sheet = this._sheet();
        var body = this._sheetBody();
        var legend = this._legendNode();
        var section = this._summaryNode();
        var btn = document.getElementById('ttSummaryButton');

        /* design §10: a page with no nodes gets no sheet and no button. */
        if (!sheet || !body || !legend || !section) {
            if (btn) btn.hidden = true;
            return;
        }
        if (!sheet.hidden) return;              /* already open: no second bind, no re-move */

        /* One overlay at a time: the bell sits on the same layer (z 1200). */
        if (typeof closeNotifPanel === 'function') closeNotifPanel();

        /* Move, never copy — and in this order, so the body reads legend → cards.
           The semester range moved INTO the sheet's subtitle (the prototype's header),
           so the body has no third line to append. */
        this._relocate(legend);
        this._relocate(section);
        this._syncSheetSubtitle();

        sheet.hidden = false;
        document.body.classList.add('tt-sheet-open');   /* the NEW lock class, not summary-sheet-open */
        if (btn) btn.setAttribute('aria-expanded', 'true');

        var close = sheet.querySelector('.tt-sheet-close');
        if (close) close.focus({ preventScroll: true });

        this._bindKeys();
    },

    /* restoreFocus=false is for the co-existence rules only (the bell opening on top
       closes the sheet without stealing its focus). Restores even when the sheet is
       already closed, so it is safe to call as a "put everything back" guard. */
    closeSheet: function (restoreFocus) {
        var sheet = this._sheet();
        if (!sheet) return;
        var wasOpen = !sheet.hidden;

        /* The two movers go back to their seats (their placeholders are removed with
           them); nothing generated lives in the body any more. */
        this._restore(this._legendNode());
        this._restore(this._summaryNode());

        sheet.hidden = true;
        document.body.classList.remove('tt-sheet-open');
        this._unbindKeys();

        var btn = document.getElementById('ttSummaryButton');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        /* At >=1280 the button sits inside a hidden #ttTools, so this focus call is a
           deliberate silent no-op there — the SummarySheet.close() convention. */
        if (wasOpen && restoreFocus !== false && btn && !btn.hidden) btn.focus({ preventScroll: true });
    },

    /* ── the movers: the same node + a hidden placeholder for its exact seat ── */
    _nodeKey: function (node) {
        if (!node) return '';
        if (node.id) return '#' + node.id;
        var cls = String(node.className || '').split(/\s+/)[0];
        return cls ? '.' + cls : '';
    },

    _relocate: function (node) {
        var body = this._sheetBody();
        if (!node || !body || node.parentNode === body) return node;   /* idempotent */

        var ph = document.createElement('span');
        ph.className = 'tt-sheet-placeholder';
        ph.hidden = true;
        if (node.parentNode) node.parentNode.insertBefore(ph, node);
        this._placeholders[this._nodeKey(node)] = ph;
        body.appendChild(node);
        return node;
    },

    _restore: function (node) {
        if (!node) return node;

        var key = this._nodeKey(node);
        var ph = this._placeholders[key];
        /* No placeholder means this node was never moved by openSheet(), so there is
           nothing to restore — the parent guard that keeps a second relocator from
           fighting us (design §2). */
        if (ph && ph.parentNode) {
            ph.parentNode.insertBefore(node, ph);
            ph.parentNode.removeChild(ph);
        }
        delete this._placeholders[key];
        return node;
    },

    /* The sheet's SUBTITLE — the prototype puts the week number and the semester in the
       header (`Week 8 · 202605 Semester · 21 Sep 2026 ~ 27 Dec 2026`) instead of a range
       line under the cards (user, 2026-11-15, item #4). Built from the page's own week
       and MockData, so it can never print a different semester than the header chip. */
    _syncSheetSubtitle: function () {
        var el = document.getElementById('ttSheetSubtitle');
        if (!el) return;
        var week = this._currentWeek();
        var parts = [];
        if (!isNaN(week)) parts.push('Week ' + (week + 1));
        var short = this._semesterShort();
        if (short) parts.push(short);
        var range = this._semesterRange();
        if (range) parts.push(range);
        el.textContent = parts.join(' · ');
    },

    /* Print, both halves (R8). before: the nodes go back inline, where theme.css's
       print rules un-hide them. after: they return into #ttSheetBody while the sheet
       is closed — below 1280 that is where the summary lives, and the page hides both
       nodes inline anyway, so nothing is lost, doubled or left on screen. */
    _sheetBeforePrint: function () {
        this._restore(this._legendNode());
        this._restore(this._summaryNode());
    },

    _sheetAfterPrint: function () {
        if (this._state.mode === 'full') return;        /* >=1280: inline is home */
        var body = this._sheetBody();
        var legend = this._legendNode();
        var section = this._summaryNode();
        if (!body || !legend || !section) return;

        this._relocate(legend);
        this._relocate(section);
        this._syncSheetSubtitle();
    },

    /* A dialog ABOVE this sheet owns the next Escape: the class details modal, the
       guide dialog and the summary explain modal all show themselves with inline
       display:flex on a `.modal-overlay` (FiltersPanel's own predicate, reused), and
       the cancel modal shows itself with `.show`. While one is up, Esc and Tab are
       left entirely to it. */
    _dialogAbove: function () {
        var overlays = document.querySelectorAll('.modal-overlay');
        for (var i = 0; i < overlays.length; i++) {
            if (this._shown(overlays[i])) return true;
        }
        var cancel = document.getElementById('cancelClassOverlay');
        if (cancel && cancel.classList.contains('show') && this._shown(cancel)) return true;
        return this._shown(document.getElementById('summaryExplainModal'));
    },

    /* Shown, not merely present: `style.display !== 'none'` is true for an element
       that has never been styled at all, and #summaryExplainModal ships with no
       inline display — so the FiltersPanel predicate this was copied from would
       have reported a dialog above the sheet on every page, suspending both the
       Tab trap and Escape (found by T16b's trap receipt). */
    _shown: function (el) {
        if (!el) return false;
        var cs = window.getComputedStyle(el);
        if (cs.display === 'none' || cs.visibility === 'hidden' || cs.opacity === '0') return false;
        return !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    },

    /* Capture phase on purpose, the SummarySheet mechanism copied arm for arm: it runs
       BEFORE the pages' and SummaryCards' bubble-phase Escape handlers, so one
       keypress closes exactly one layer. Bound only while the sheet is open. */
    _bindKeys: function () {
        if (this._onKeyDown) return;
        var self = this;
        this._onKeyDown = function (e) {
            var sheet = self._sheet();
            if (!sheet || sheet.hidden) return;

            if (e.key === 'Escape') {
                if (self._dialogAbove()) return;    /* one Escape = one layer */
                e.preventDefault();
                e.stopPropagation();
                self.closeSheet();
                return;
            }

            if (e.key !== 'Tab' || self._dialogAbove()) return;   /* trap suspended for the dialog */

            var panel = sheet.querySelector('.tt-sheet');
            if (!panel) return;
            var nodes = panel.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), ' +
                'textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            var items = [];
            for (var i = 0; i < nodes.length; i++) {
                /* Two filters the size test alone cannot do (found by T16b's trap
                   receipt — Tab walked out into the footer on the 6th press):
                   the cards' ⓘ buttons match `button:not([disabled])` but are taken
                   out of the tab order, and they keep their 44x44 box while
                   visibility:hidden. Either would make `last` a node Tab can never
                   reach, so the wrap never fired. */
                if (nodes[i].tabIndex < 0) continue;
                if (window.getComputedStyle(nodes[i]).visibility === 'hidden') continue;
                if (nodes[i].offsetWidth || nodes[i].offsetHeight || nodes[i].getClientRects().length) {
                    items.push(nodes[i]);
                }
            }
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            var active = document.activeElement;

            if (e.shiftKey) {
                if (active === first || !panel.contains(active)) {
                    e.preventDefault();
                    last.focus();
                }
            } else if (active === last) {
                e.preventDefault();
                first.focus();
            }
        };
        document.addEventListener('keydown', this._onKeyDown, true);
    },

    _unbindKeys: function () {
        if (!this._onKeyDown) return;
        document.removeEventListener('keydown', this._onKeyDown, true);
        this._onKeyDown = null;
    },
};
window.TimetableResponsive = TimetableResponsive;
