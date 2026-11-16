{{-- Livewire full-page view (SDD wire-backend-into-refactored-ui). Read-only
     student page: sessions + request statuses (FR 1.2/1.3). --}}

@section('title', 'Student My Timetable')

<div class="lw-page tt-responsive" data-tt-page="student">

    <style>
        .timetable td.hour-cell.offday-slot { background: transparent; }
        .timetable td.today-cell.offday-slot { background: transparent; }
        .timetable td.hour-cell.offday-slot .cell-empty { background: transparent; }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', [
        'title' => 'Student My Timetable',
        'guideToggle' => true,
        'guideModal' => true,
        'chips' => $cohortCode ? [['label' => $cohortCode]] : [],
    ])

    @include('partials.ui-guide-modal', [
        'guideTitle' => 'How to use this page',
        'guideDescriptionHtml' => 'Your weekly <span class="info-keyword">class schedule</span> for the whole semester — every session you attend, across all your courses.',
        'guideItems' => [
            '<strong>Week</strong> — use the arrows or the week list to move between weeks; <strong>Today</strong> jumps back to the current one',
            '<strong>Class details</strong> — click a class block to see its course, lecturer, venue and request status',
            '<strong>Colours</strong> — Normal Class, Replacement, Pending and Conflict / Public Holiday; hover a colour in the legend to see what it means',
            '<strong>The five numbers</strong> above the grid count the week you are looking at, not the whole semester',
            '<strong>Read-only</strong> — you cannot request or cancel a class here. Your Programme Leader arranges replacements; <strong>Class Replacements</strong> shows where each one stands',
        ],
    ])

    <!-- ─── Week nav (my-timetable-responsive-prompt T14) ─── -->
    {{-- Same mechanism as the staff page (T11): the week row is rendered to a string
         and the chrome partial wraps it in its sticky `.tt-sticky` row. Same arguments
         as before plus the three tips R5 asks for; ui-week-nav is never edited, and
         the `.semester-bar` wrapper travels with the row (theme.css's
         `.tt-sticky .semester-bar` rule and the desktop surface both need it). --}}
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

    <!-- ─── Responsive chrome (my-timetable-responsive-prompt T14, phase B) ─── -->
    {{-- The same include as the staff page, READ-ONLY (`ttReadonly => true`, spec
         R9): a card opens the details modal and nothing else — no cancel wiring, no
         modal-footer work. `summaryButton` is gone from the header above: with
         SummarySheet gated off on this page that icon would call a sheet that never
         opens (tasks T14).

         The same placement as the staff page and for the same measured reason: the
         week row travels inside this include, so "after the legend/summary" would put
         the week picker below the five cards at >=1280. In the old `.semester-bar`
         seat the phone reads week row -> strip -> tools -> agenda (the grid, legend and
         summary are hidden there) and >=1280 reads today's order. --}}
    @include('partials.ui-tt-chrome', ['ttWeekNav' => $ttWeekNav, 'ttReadonly' => true])

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')
        @include('partials.ui-empty-state', ['title' => 'No classes this week', 'text' => $hasCohort ? 'All classes for this week have been cancelled.' : 'You are not assigned to a cohort yet.'])

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
                        ['Normal', '<span>A class that runs as scheduled.</span>'],
                        ['Replacement', '<span>A different lecturer teaches it.</span>'],
                        ['Pending', '<span>Waiting for a PL decision.<br>Counted only in the small <strong>+N</strong>.</span>'],
                        ['Cancelled', '<span>Not counted in this card.</span>'],
                    ],
                    'foot' => 'Counts the week selected above.',
                ]],
            ['class' => 'card-hours', 'valueId' => 'sumHours', 'label' => 'Class Hours',
                'bullets' => ['Teaching time', '<strong>30 min</strong> / slot', '<strong class="summary-pending">+N</strong> = pending'],
                'explanation' => 'Hours of your <span class="info-keyword">normal + replacement + pending</span> classes this week (each slot = <strong>30 minutes</strong>). Cancelled classes are not counted. The small <strong>+N</strong> is the pending part.',
                'detail' => [
                    'formula' => [['v' => 'slots × 30 min'], ['op' => '='], ['v' => 'class time']],
                    'items' => [
                        ['Measured in slots', '<span>Each timetable slot is <strong>30 minutes</strong>.<br>Two slots make one hour.</span>'],
                        ['Pending', '<span>Counted in the small <strong>+N</strong> only.</span>'],
                        ['Cancelled', '<span>Excluded — no class happens.</span>'],
                    ],
                    'foot' => 'Same week as Total Classes.',
                ]],
            ['class' => 'card-replacement', 'valueId' => 'sumReplacement', 'label' => 'Replacements',
                'bullets' => ['New class time', 'PL approved', '<strong class="summary-replacement">Blue</strong> blocks'],
                'explanation' => 'Classes a <span class="info-keyword">different lecturer</span> teaches for your class this week.',
                'detail' => [
                    'items' => [
                        ['New class time', '<span>The replacement moves to a new slot.</span>'],
                        ['PL approved first', '<span>A PL must approve it.<br>Until then it is Pending, not here.</span>'],
                        ['Shown in blue', '<span>Blue blocks mark the new class time.</span>'],
                    ],
                ]],
            ['class' => 'card-pending', 'valueId' => 'sumPending', 'label' => 'Pending',
                'bullets' => ['Awaiting PL', 'Not confirmed', '<strong class="summary-pending">+N</strong> on totals'],
                'explanation' => 'Your classes still <span class="warn-keyword">waiting for a replacement to be approved</span> this week.',
                'detail' => [
                    'items' => [
                        ['Awaiting PL', '<span>A replacement is requested, but<br>no programme leader has decided.</span>'],
                        ['Not confirmed', '<span>The class is not replaced yet.</span>'],
                        ['Appears as <strong>+N</strong>', '<span>Pending classes are also added to<br>Total Classes and Class Hours.</span>'],
                    ],
                ]],
            ['class' => 'card-conflict', 'valueId' => 'sumConflict', 'label' => 'Conflicts',
                'bullets' => ['Cancelled/clash', 'Or a holiday', '<strong class="summary-excluded">Needs make-up</strong>'],
                'explanation' => 'Classes that cannot run as scheduled — a <span class="warn-keyword">clash</span>, a cancellation, or a <strong>public holiday</strong>.',
                'detail' => [
                    'items' => [
                        ['Clash', '<span>Another class already booked in the slot.</span>'],
                        ['Cancelled', '<span>The class was called off.</span>'],
                        ['Public holiday', '<span>The whole day is closed.</span>'],
                    ],
                    'foot' => 'Your lecturer must arrange a make-up class.',
                ]],
        ]
    ])

    <!-- ═══ View-Only Modal ═══ -->
    @include('partials.ui-class-detail-modal')

    <!-- ═══ Copy Toast ═══ -->
    <div class="copy-toast" id="copyToast"></div>

    <script>
        /* Real-data bridge. DOMContentLoaded: mock-data.js / ui-common.js are
           parsed AFTER @yield('content') in the layout body, so top-level
           access here would throw before they load. */
        document.addEventListener('DOMContentLoaded', function () {
            MockData.semester = @json($semesterJs);
            MockData.holidays = @json($holidaysJs);
            /* REAL clock (user report 2026-11-08): without this the page's
               Today anchored the mock-era demo date (mockNow = Mon 5 Oct 2026),
               so the Today button jumped to Week 3 and no day carried the
               "Today" badge in the real current week — the same clearing line
               the seven other live pages carry (my-timetable / cohort / venue /
               arrangement / home / approval / history). */
            MockData.mockNow = null;
            /* Real viewer identity (all-pages-design-parity S3): the shared
               openClassModal compares event.requestedBy against this — without
               it the student page's comparison runs against the mock-data
               default persona and a student's own pending request never
               matches. */
            MockData.currentUser = @json(['name' => auth()->user()?->name ?? '']);

            window.weekData = generateWeekData();
            const weekData = window.weekData;
            const eventsData = @json($eventsByWeek);

            window.currentWeek = {{ (int) ($currentWeek ?? 0) }};
            let currentWeek = window.currentWeek;

            /* week-memory-until-logout (T3): the session-scoped mode — the
               per-login stamp minted at the Fortify login hook binds this
               page's week memory to the login session (logout/expiry re-lands
               today); the load() below reads ONLY the stamp-scoped sessionStorage
               — the old localStorage memory never survives a login anymore. */
            const weekNav = new WeekNavigator(MockData.semester, weekData, null, null, null, { sessionScoped: true, loginStamp: @json(session('login_week_stamp')) });
            weekNav._currentWeek = currentWeek;

            function openModal(event) {
                openClassModal({
                    week: window.currentWeek + 1,
                    event: event,
                    dayIndex: event.di,
                    days: weekData[currentWeek].days,
                    title: event.code || 'Class Details',
                    /* my-timetable-responsive-prompt T13 (spec R4): the Status row
                       reads `Class Cancelled` on these two pages only. Blocks keep the
                       short word (a 60-110px cell cannot carry the long one). */
                    longCancelledLabel: true
                });
            }

            function closeModal() {
                document.getElementById('classModal').style.display = 'none';
            }

            function closeModalOutside(e) {
                closeOnOverlayClick(e, closeModal);
            }

            closeOnEsc(closeModal);

            /* T6/T14 — the next-class hint's cross-week source (design §6's
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
                /* ONE set of closures for both consumers (T14): the card must speak
                   exactly like the block it stands in for, so the grid's own
                   replacementNoteFn is named once and handed to both calls. */
                var replacementNoteFn = function (e) { return buildReplacementNote(e); };
                /* The student page passes no tipLecturerFn to the grid, so the builder's
                   own default supplies the lecturer segment (`e.lecturer || '—'`, the
                   student preview's block tip). Handing the module the same function
                   keeps the card's tip byte-identical to the block's (R3b/R5). */
                var tipLecturerFn = function (e) { return e.lecturer || '—'; };

                buildTimetableGrid({
                    events: eventsData[currentWeek] || [],
                    days: weekData[currentWeek].days,
                    onEventClick: function (e) { openModal(e); },
                    tipLecturerFn: tipLecturerFn,
                    replacementNoteFn: replacementNoteFn,
                    /* T12's two flag-only edits live inside this flag (spec R1/R5) */
                    responsive: true
                });
                updateSummary();

                /* Phase B's chrome (T14): READ-ONLY throughout — a card calls this
                   page's own details handler and nothing else, so no cancel wiring and
                   no modal-footer work reaches it (R9). init() is idempotent, so the
                   week change that re-runs buildTimetable() replaces the cfg without
                   re-binding. `weekNav` is deliberately NOT passed (optional/unused). */
                TimetableResponsive.init({
                    scope: 'tt-responsive',
                    pageKey: 'student',
                    readonly: true,
                    onEventClick: function (e) { openModal(e); },
                    events: function () { return eventsData[currentWeek] || []; },
                    days: function () { return weekData[currentWeek].days; },
                    tipLecturerFn: tipLecturerFn,
                    replacementNoteFn: replacementNoteFn,
                    findNextClass: findNextClass
                });
            }

            function updateSummary() {
                /* cancelled-blocks-stay-visible: the filter is REMOVED — the
                   full array reaches computeSummary. */
                /* 2026-11-11 (user ruling): Total Classes = Normal + Replacement
                   + Pending and Class Hours = their hours, printed the Cohort/
                   Venue way — finished part big, pending part as `+N` (9+1). */
                computeSummary(eventsData[currentWeek] || [], weekData[currentWeek].days, { splitPending: true });
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

            function saveWeek() { weekNav.save(); window.currentWeek = currentWeek; }

            weekNav.load();
            currentWeek = weekNav.currentWeek;

            const chipEl = document.getElementById('semesterChip');
            if (chipEl) chipEl.textContent = MockData.semester.chipText;

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
            window.closeModal = closeModal;
            window.closeModalOutside = closeModalOutside;
            window.buildTimetable = buildTimetable;
            window.updateSummary = updateSummary;
        });
    </script>
</div>
