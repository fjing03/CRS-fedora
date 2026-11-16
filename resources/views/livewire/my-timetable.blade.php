{{-- Livewire full-page view (SDD wire-backend-into-refactored-ui). Real data
     feeds the shared grid engine; page CSS/JS inline per design D2. Legacy
     mock template untouched for the D10 fallback. --}}

@section('title', 'My Timetable')

<div class="lw-page tt-responsive" data-tt-page="staff">

    <style>
        /* Page styles (moved from legacy page-styles section, D2) */
        .modal-footer {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 24px 20px;
        }
        .modal-footer-left { display: flex; align-items: center; }
        .modal-footer-right { display: flex; align-items: center; gap: 10px; }
        /* SDD cohort-venue-replace-now (the promote-to-shared receipt, user
           ask 2026-10-25): the .btn-replace-now rules moved to theme.css —
           the ONE source the three table pages share (my-timetable used to
           hold the only definition while cohort/venue buttons were
           unstyled). No local duplicate remains; the visuals = the same
           token set (--color-error-container family). */
        .timetable td.hour-cell.offday-slot { background: transparent; }
        .timetable td.today-cell.offday-slot { background: transparent; }
        .timetable td.hour-cell.offday-slot .cell-empty { background: transparent; }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'My Timetable', 'guideToggle' => true, 'guideModal' => true])

    @include('partials.ui-guide-modal', [
        'guideTitle' => 'How to use this page',
        'guideDescriptionHtml' => 'View your weekly <span class="info-keyword">class schedule</span> and manage <span class="info-keyword">replacement requests</span> across all cohorts.',
        'guideItems' => [
            '<strong>Week navigation</strong> — use arrows or Today button to browse weeks',
            '<strong>Slot status</strong> — Normal (green), Conflicted (red), Pending (amber)',
            '<strong>Request replacement</strong> — click any conflicted slot to open the request form',
            '<strong>View details</strong> — click any slot to see class details',
        ]
    ])

    <!-- ─── Week nav (my-timetable-responsive-prompt T11) ─── -->
    {{-- The week row is RENDERED to a string here and handed to the chrome partial,
         which wraps it in its sticky `.tt-sticky` row (design §4.1: a static wrap in
         the blade, never a runtime move). Same arguments as before, plus the three
         tips R5 asks for; ui-week-nav itself is never edited (design §8) and renders
         byte-identically for every caller that passes no tips. The `.semester-bar`
         wrapper travels WITH the row: theme.css's `.tt-sticky .semester-bar` rule and
         the desktop surface both need it, so only the row's position changes. --}}
    @php
        $ttWeekNav = '<div class="semester-bar">'
            . view('partials.ui-week-nav', [
                'prevOnclick' => 'prevWeek()',
                'nextOnclick' => 'nextWeek()',
                'selectId' => 'weekSelect',
                'selectOnclick' => 'selectWeek(this.value)',
                'selectTinted' => true,
                'showPrint' => true,
                'prevTip' => 'Previous week',
                'nextTip' => 'Next week',
                'selectTip' => 'Pick the week to display',
            ])->render()
            . '</div>';
    @endphp

    <!-- ─── Responsive chrome (my-timetable-responsive-prompt T11) ─── -->
    {{-- The sticky week row (above), the day strip, the tools row, the agenda mount,
         the next-class hint and the Week summary sheet — design §4.1's markup.

         PLACEMENT, and why it is the old `.semester-bar` seat rather than after the
         legend/summary includes: the week row now travels INSIDE this include, so
         "after the legend/summary" would drop the week picker BELOW the five summary
         cards at >=1280 (measured 2026-11-15) — a desktop regression, while R1/design
         §9 row 5 promise the page is exactly today's there. In this seat both bands
         read correctly with no CSS work: below 1280 the grid/legend/summary are
         hidden and the order is the preview's (week row -> strip -> tools -> agenda);
         at >=1280 the strip/tools/agenda/hint/sheet are hidden, so the page is
         today's order (week row -> grid -> legend -> summary). Every control in it is
         bound by TimetableResponsive; there is no inline onclick, so nothing needs
         exporting. --}}
    @include('partials.ui-tt-chrome', ['ttWeekNav' => $ttWeekNav, 'ttReadonly' => false])

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')
        @include('partials.ui-empty-state', ['title' => 'No classes this week', 'text' => 'All classes for this week have been cancelled.'])

    <!-- ─── Legend Bar ─── -->
    @include('partials.ui-legend-bar')

    <!-- ─── Weekly Summary Bar ─── -->
    @include('partials.ui-summary-bar', [
        'flipCards' => true,
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Classes',
                'bullets' => ['<strong class="summary-normal">Normal</strong> + <strong class="summary-replacement">blue</strong>', '<strong class="summary-pending">+N</strong> = pending', 'No <strong class="summary-excluded">red</strong> blocks'],
                'explanation' => '<span class="info-keyword">Normal + replacement + pending</span> classes this week. Cancelled classes are not counted. The small <strong>+N</strong> is the pending part.',
                'detail' => [
                    'formula' => [['v' => 'normal'], ['op' => '+'], ['v' => 'replacement', 'c' => 'is-blue'], ['op' => '+'], ['v' => 'pending', 'c' => 'is-pending']],
                    'items' => [
                        ['Normal', '<span>Class runs as scheduled.</span>'],
                        ['Replacement', '<span>A replacement lecturer teaches it.</span>'],
                        ['Pending', '<span>Waiting for a PL decision.<br>Counted only in the small <strong>+N</strong>.</span>'],
                        ['Cancelled', '<span>Not counted in this card.</span>'],
                    ],
                    'foot' => 'Counts the period and week selected above, plus any active filter.',
                ]],
            ['class' => 'card-hours', 'valueId' => 'sumHours', 'label' => 'Teaching Hours',
                'bullets' => ['Teaching time', '<strong>30 min</strong> / slot', '<strong class="summary-pending">+N</strong> = pending'],
                'explanation' => 'Hours of those classes for the selected period (each slot = <strong>30 minutes</strong>). Cancelled classes are not counted.',
                'detail' => [
                    'formula' => [['v' => 'slots × 30 min'], ['op' => '='], ['v' => 'teaching time']],
                    'items' => [
                        ['Measured in slots', '<span>Each timetable slot is <strong>30 minutes</strong>.<br>Two slots make one hour.</span>'],
                        ['Pending', '<span>Counted in the small <strong>+N</strong> only.</span>'],
                        ['Cancelled', '<span>Excluded — no teaching happens.</span>'],
                    ],
                    'foot' => 'Same period and filters as Total Classes.',
                ]],
            ['class' => 'card-replacement', 'valueId' => 'sumReplacement', 'label' => 'Replacements',
                'bullets' => ['New class time', 'PL approved', '<strong class="summary-replacement">Blue</strong> blocks'],
                'explanation' => 'Classes a replacement lecturer teaches.',
                'detail' => [
                    'items' => [
                        ['Approved first', '<span>A PL must approve the request.<br>Until then it is Pending, not here.</span>'],
                        ['Shown in blue', '<span>Blue blocks mark the new class time.</span>'],
                    ],
                ]],
            ['class' => 'card-pending', 'valueId' => 'sumPending', 'label' => 'Pending',
                'bullets' => ['Awaiting PL', 'Not confirmed', '<strong class="summary-pending">+N</strong> on totals'],
                'explanation' => 'Classes still waiting for a replacement to be approved.',
                'detail' => [
                    'items' => [
                        ['What it means', '<span>A request is sent, but no PL has decided yet.</span>'],
                        ['Not confirmed', '<span>The class is not yet a replacement.</span>'],
                        ['Appears as <strong>+N</strong>', '<span>Pending classes are also added to<br>Total Classes and Teaching Hours.</span>'],
                    ],
                ]],
            ['class' => 'card-conflict', 'valueId' => 'sumConflict', 'label' => 'Conflicts',
                'bullets' => ['Cancelled/clash', 'Or a holiday', '<strong class="summary-excluded">Needs make-up</strong>'],
                'explanation' => 'Classes that cannot run as scheduled — a clash, a cancellation, or a <strong>public holiday</strong>.',
                'detail' => [
                    'items' => [
                        ['Clash', '<span>Another class already booked in the slot.</span>'],
                        ['Cancelled', '<span>The class was called off.</span>'],
                        ['Public holiday', '<span>The whole day is closed.</span>'],
                    ],
                    'foot' => 'Each conflict needs a make-up class to be arranged.',
                ]],
        ]
    ])

    <!-- ═══ Class Detail Modal (footer: Replace Now on conflicted days) ═══ -->
    @section('modal-footer')
        <div class="modal-footer-left">
            <button class="btn-close-modal" onclick="closeModal()">Close</button>
        </div>
        <div class="modal-footer-right">
            <!-- occurrence-week-pin-truthfulness D-P4: the handoff reads the
                 event's EXACT-PAIR stamp week (never window.currentWeek —
                 its stale restore/Today-button/shortcut desync made #11 book
                 week 5 while the fact lived at W14). -->
            <button class="btn-replace-now" id="btnReplaceNow" style="display:none" onclick="goToReplacementFor(currentModalEvent?.id, rarityReplaceOccurrenceWeek(), 'my-timetable')"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg> Arrange Replacement</button>
        </div>
    @endsection
    @include('partials.ui-class-detail-modal')
    @include('partials.ui-cancel-class-modal')

    <!-- ═══ Copy Toast ═══ -->
    <div class="copy-toast" id="copyToast"></div>

    <script>
        /* Real-data bridge. DOMContentLoaded: mock-data.js / ui-common.js are
           parsed AFTER @yield('content') in the layout body, so top-level
           access here would throw before they load. */
        document.addEventListener('DOMContentLoaded', function () {
            /* REAL clock (user report 2026-10-19): without this the page's
               TODAY anchored the mock-era demo date (mockNow Oct 5) — same
               clearing line the cohort/venue/history pages carry. */
            MockData.mockNow = null;
            MockData.semester = @json($semesterJs);
            MockData.holidays = @json($holidaysJs);
            MockData.currentUser = @json(['name' => auth()->user()?->name ?? '', 'staffId' => (string) (auth()->user()?->lecturer?->staff_id ?? '')]);

            window.weekData = generateWeekData();
            const weekData = window.weekData;
            const eventsData = @json($eventsByWeek);
            window.eventsData = eventsData;   // SDD occurrence-week-pin-truthfulness: the tests + the popup gate read the served truth

            /* class-modal-affordances T1: the cancel machinery's twin store —
               resolveMatches reads MockData.myTimetable.eventsByWeek (the
               legacy bridging that the live cutover lost); the restored
               wiring re-points it to the SAME rows the grid renders. */
            MockData.myTimetable = { eventsByWeek: eventsData };

            window.currentWeek = {{ (int) ($currentWeek ?? 0) }};
            let currentWeek = window.currentWeek;
            window.currentModalEvent = null;

            /* week-memory-until-logout (T3): the session-scoped mode — the
               per-login stamp minted at the Fortify login hook binds this
               page's week memory to the login session (logout/expiry re-lands
               today); the load() below reads ONLY the stamp-scoped sessionStorage
               — the old localStorage memory never survives a login anymore. */
            const weekNav = new WeekNavigator(MockData.semester, weekData, null, null, null, { sessionScoped: true, loginStamp: @json(session('login_week_stamp')) });
            weekNav._currentWeek = currentWeek;

            function saveWeek() { weekNav.save(); window.currentWeek = currentWeek; }

            function openModal(event) {
                window.currentModalEvent = event;

                const days = weekData[currentWeek].days;
                const isConflict = (days[event.di] && days[event.di].holiday) || event.status === 'conflict';

                const replaceBtn = document.getElementById('btnReplaceNow');
                /* occurrence-week-pin-truthfulness D-P4: the EXACT-PAIR gate —
                   the button exists ONLY where the occurrence's own week
                   carries a live conflict fact (holiday / exception /
                   venue-restriction; the ResolvesTimetableTimeline week loop
                   stamps it). Livewire's marker morph can blank the DOM
                   clone's extra key — re-read the truth from the SERVED
                   array (id+di identity) before deciding. */
                var stampedEvent = event;
                try {
                    (window.eventsData[currentWeek] || []).forEach(function (x) {
                        if (x && x.id === event.id && x.di === event.di && x.occurrenceFact) {
                            stampedEvent = x;
                        }
                    });
                } catch (e) {}
                replaceBtn.style.display = stampedEvent.occurrenceFact ? 'flex' : 'none';

                let cohortValue = event.cohort;
                /* BMSE3153-class-info (user law 2026-10-27): the shared
                   totalStudentsLabel (ui-common.js) renders the per-cohort
                   `A + B = total` breakdown for multi-cohort classes. */
                const studentValue = window.totalStudentsLabel(event);

                openClassModal({
                    week: window.currentWeek + 1,
                    event: event,
                    dayIndex: event.di,
                    days: days,
                    modalId: 'classModal',
                    title: 'Class Details',
                    /* my-timetable-responsive-prompt T13 (spec R4): the Status row
                       reads `Class Cancelled` on THESE two pages only — the bare word
                       can be read as the request being cancelled. Blocks keep the
                       short word (a 60-110px cell cannot carry the long one). */
                    longCancelledLabel: true,
                    extraFields: [
                        { label: 'Cohort', value: cohortValue },
                        { label: 'Total Students', value: studentValue }
                    ]
                });

                /* class-modal-affordances T1: the Cancel Class? affordance
                   restored (the legacy MyTimetable template's wiring ported
                   — RESTORE-FIRST persistence per the user: visual + the
                   ledger until reload; the CancelClass action stays
                   unrouted). Self-hides via isCancellable (S1–S5). */
                CancelClass.renderButton(
                    document.querySelector('#classModal .modal-footer'),
                    stampedEvent, days, currentWeek,
                    function () {
                        closeModal();
                        buildTimetable();
                    }
                );
            }

            /* the popup's handoff: the STAMPED event's week — re-derived from
               the served array (the morph-clone-proof channel; D-P4). */
            function rarityReplaceOccurrenceWeek() {
                var ev = window.currentModalEvent;
                if (ev && ev.occurrenceFact && ev.occurrenceFact.week) {
                    return ev.occurrenceFact.week;
                }
                var found = null;
                try {
                    (window.eventsData[currentWeek] || []).forEach(function (x) {
                        if (x && ev && x.id === ev.id && x.di === ev.di && x.occurrenceFact) {
                            found = x.occurrenceFact.week;
                        }
                    });
                } catch (e) {}
                return found;
            }

            function closeModal() {
                document.getElementById('classModal').style.display = 'none';
            }

            function closeModalOutside(e) {
                closeOnOverlayClick(e, closeModal);
            }

            closeOnEsc(closeModal);

            /* T6/T11 — the next-class hint's cross-week source (design §6's
               `findNextClass(fromWeek, fromDay)`). `events()` is the CURRENT week
               only, so the hint asks this instead; it scans the page's OWN eventsData
               (the same payload — no second data shape): the rest of this week from
               the selected day, then the weeks after it, and the first day carrying a
               class wins. That day's earliest event (start, then code) is the one the
               hint names, so `{ week, day, event }` is enough to jump and to print
               `Next class: Wed 10:00 AM · AMCS1033`. Returns null when the semester
               has nothing later. */
            function findNextClass(fromWeek, fromDay) {
                var week = parseInt(fromWeek, 10);
                var day = parseInt(fromDay, 10);
                if (isNaN(week)) week = currentWeek;
                if (isNaN(day)) day = 0;

                /* THE PAGE'S OWN WEEK WINS, not the caller's number: the module's
                   `_currentWeek()` reads `window.currentWeek`, which a Livewire week
                   swap can leave a step behind the payload these closures already
                   point at — that mismatch put "week 8" on a hint whose class was in
                   week 7 (found by T16a's holiday receipt). `currentWeek` here is the
                   very variable `events()`/`days()` close over, so all three agree. */
                if (typeof currentWeek === 'number' && !isNaN(currentWeek)) week = currentWeek;

                for (var wk = week; wk < weekData.length; wk++) {
                    var list = eventsData[wk] || [];
                    if (!list.length) continue;
                    for (var di = (wk === week ? day + 1 : 0); di < 7; di++) {
                        var best = null;
                        for (var i = 0; i < list.length; i++) {
                            var e = list[i];
                            if (!e || Number(e.di) !== di) continue;
                            if (!best || e.start < best.start
                                || (e.start === best.start && String(e.code || '') < String(best.code || ''))) {
                                best = e;
                            }
                        }
                        if (best) {
                            /* the day's own labels ride the result, so the hint never
                               has to borrow them from whichever week it happens to have
                               rendered (that is how "Tue 10 Nov" got a week-8 dataset) */
                            var dayMeta = (weekData[wk] && weekData[wk].days && weekData[wk].days[di]) || {};
                            return { week: wk, day: di, event: best,
                                date: dayMeta.date || '', abbr: dayMeta.abbr || '' };
                        }
                    }
                }
                return null;
            }

            function buildTimetable() {
                currentWeek = weekNav.currentWeek;
                /* ONE set of closures for both consumers (T11): the module's cards must
                   speak exactly like the grid's blocks, so the grid's own closures are
                   named once here and handed to both calls. */
                var tooltipExtra = function (e) {
                    // Viewer is the lecturer — tooltip shows the cohort(s).
                    if (e.cohorts && e.cohorts.length) return e.cohorts.join(' + ');
                    return e.cohort || '';
                };
                /* the user's hover-voice directive 2026-10-25: the staff
                   name is DROPPED here — every block on this page is the
                   viewer's own, so "name · cohorts · STATUS" says it all. */
                var tipLecturerFn = function () { return ''; };
                var replacementNoteFn = function (e) {
                    return buildReplacementNote(e);
                };

                buildTimetableGrid({
                    events: eventsData[currentWeek] || [],
                    days: weekData[currentWeek].days,
                    onEventClick: function (e) { openModal(e); },
                    tooltipExtra: tooltipExtra,
                    tipLecturerFn: tipLecturerFn,
                    replacementNoteFn: replacementNoteFn,
                    /* T12's two flag-only edits (the tip migration + the desktop
                       status word) live inside this flag; the other four callers
                       never pass it (spec R1/R5). */
                    responsive: true
                });
                updateSummary();

                /* The <=1279 chrome (T11): the day strip, the agenda, the window grid,
                   the next-class hint and the Week summary sheet, all read from the
                   SAME week the grid just drew. init() is idempotent — buildTimetable()
                   runs again on every week change, replacing the cfg, never re-binding
                   the listeners. `weekNav` is deliberately NOT passed (optional and
                   unused: the week row's tips come from the blade). */
                TimetableResponsive.init({
                    scope: 'tt-responsive',
                    pageKey: 'staff',
                    readonly: false,
                    onEventClick: function (e) { openModal(e); },
                    events: function () { return eventsData[currentWeek] || []; },
                    days: function () { return weekData[currentWeek].days; },
                    tooltipExtra: tooltipExtra,
                    tipLecturerFn: tipLecturerFn,
                    replacementNoteFn: replacementNoteFn,
                    findNextClass: findNextClass
                });
            }

            function updateSummary() {
                const events = eventsData[currentWeek] || [];
                /* cancelled-blocks-stay-visible: the filter is REMOVED — the
                   full array reaches computeSummary (its tally counts the
                   cancelled conflict; myTeachingStats gates the teaching
                   numbers). */
                /* 2026-11-11 (user ruling): Total Classes = Normal + Replacement
                   + Pending, Teaching Hours = their hours, and the cards say it
                   the way the Cohort and Venue pages do — the finished part big,
                   the pending part as a small `+N` (9+1, 12+1). The split is the
                   shared builder's opt-in, so all four pages print one shape. */
                computeSummary(events, weekData[currentWeek].days, { splitPending: true });
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
            }

            function prevWeek() {
                weekNav.prevWeek();
                currentWeek = weekNav.currentWeek;
                window.currentWeek = currentWeek;
                saveWeek();
            }

            function nextWeek() {
                weekNav.nextWeek();
                currentWeek = weekNav.currentWeek;
                window.currentWeek = currentWeek;
                saveWeek();
            }

            function selectWeek(index) {
                weekNav.selectWeek(parseInt(index, 10));
                currentWeek = weekNav.currentWeek;
                window.currentWeek = currentWeek;
                saveWeek();
            }

            weekNav.load();
            currentWeek = weekNav.currentWeek;
            document.getElementById('semesterChip').textContent = MockData.semester.chipText;
            populateWeekSelect('weekSelect', { ranges: false, selected: currentWeek });

            buildTimetable();
            updateWeekSubtitle();
            updateProgress();

            weekNav.initTodayBtn();
            initWeekKeyboardShortcuts();

            initTimetableKeyboardHandlers({
                prevWeek: prevWeek,
                nextWeek: nextWeek,
                openModal: openModal,
                modalId: 'classModal'
            });

            initEvCodeCopy('copyToast');

            initGridSwipeGestures(prevWeek, nextWeek);

            /* onclick= handlers in partials resolve via window */
            window.prevWeek = prevWeek;
            window.nextWeek = nextWeek;
            window.selectWeek = selectWeek;
            window.openModal = openModal;
            window.rarityReplaceOccurrenceWeek = rarityReplaceOccurrenceWeek;   // occurrence-week-pin-truthfulness: the handoff reads the STAMPED week
            window.buildTimetable = buildTimetable;
            window.updateSummary = updateSummary;
            window.closeModal = closeModal;
            window.closeModalOutside = closeModalOutside;
        });
    </script>
</div>
