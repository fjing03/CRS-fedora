{{-- Livewire full-page view (SDD: venue-timetable-db; rewritten to event blocks
     by venue-event-blocks-db). Real slot-level data feeds the shared grid
     engine in cellRender mode — the frozen two-axis block language (colour =
     status, border = ownership). READ-only v1 (booking = Slice B). Legacy mock
     template untouched for the D10 fallback. Week nav is client-side (Slice A
     pattern); venue switching navigates `?venue=` (full render, scripts re-run). --}}

@section('title', 'Venue Timetable')

<div class="lw-page">

    <style>
        /* Page styles: available-cell tooltip (U2a — frozen template :178–212,
           box-shadow kept as the frozen literal, documented exception), the
           frozen hover labels (template :315–317, reworded per user decision
           2026-10-13), and the honest booking hint. Tokens only per §10.0. */
        /* ───── Available Cell Tooltip ───── */
        .available-tooltip {
            position: fixed;
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-sm);
            padding: 12px 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.12), 0 1px 4px rgba(0,0,0,0.06);
            z-index: 9999;
            display: none;
            min-width: 250px;
        }
        .available-tooltip.show {
            display: block;
        }
        .available-tooltip p {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: var(--color-on-surface);
        }
        .available-tooltip .btn-book {
            background: var(--color-primary);
            color: var(--color-on-primary);
            border: none;
            padding: 6px 16px;
            border-radius: var(--radius-sm);
            cursor: pointer;
            font-size: 13px;
        }
        .available-tooltip .btn-book:hover {
            opacity: 0.9;
        }
        .available-tooltip .btn-book:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        /* ───── Available slot hover label ───── */
        .cell-available { --hover-label: 'Arrange ?'; }
        /* too late in the day for a full booking — hover says so instead.
           Two-line label law (user, 2026-10-25): "Too Late" / "to Book" —
           \A is the CSS line-break escape; white-space: pre-line makes the
           ::after pill honour it (it inherits from this cell). */
        .cell-available.cell-no-fit { --hover-label: 'Too Late\A to Book'; white-space: pre-line; cursor: not-allowed; }
        /* SDD prompt too-late-red-cells (user law 2026-10-25, the red-cell
           directive): no-fit cells must READ as not-bookable — red at REST
           and a RED hover pill. Today they inherit the green/blue bookable
           look (only the hover words differ), so users mistook them for
           bookable slots. Tokens only: error red on both surfaces (the
           .cell-occupied precedent). The bookable hover filter/brightness
           stays off these (cursor: not-allowed already set above). Legend
           frozen at 5 chips — the label stays the two-line text untouched.
           .cell-too-soon is a DIFFERENT state — untouched. */
        .cell-available.cell-no-fit { background: var(--color-error-container); }
        /* venue-timetable-bookable-cells (user ruling 2026-11-05): a free cell
           with too little room before the NEXT class is not bookable — but it
           keeps its normal background: only the cursor and the hover words
           change. The day-end run stays .cell-no-fit's job (red), because a
           following event always ends the run before the day does. */
        .cell-available.cell-no-room { --hover-label: 'Not Enough Slot'; cursor: not-allowed; }
        .cell-available.cell-no-room:hover { filter: none; }
        .cell-available.cell-no-fit::after { background: var(--color-error); color: var(--color-on-error); }
        .cell-available.cell-no-fit:hover { filter: none; }
        .booking-hint-line {
            margin: 10px 0 0;
            font-size: 12px;
            color: var(--color-on-surface-variant);
            font-style: italic;
        }
        .venue-capacity {
            font-size: 12px;
            color: var(--color-on-surface-variant);
        }
        /* venue-description-doc-b006-rule: the plain room description line */
        .venue-description {
            font-size: 12px;
            color: var(--color-on-surface-variant);
            margin-top: 2px;
        }
        /* Session-choice modal: dropdowns fill their detail-row value column */
        #intentPickers .filter-select {
            width: 100%;
        }
        #intentNotice {
            font-size: 14px;
            color: var(--color-on-surface);
        }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'Venue Timetable', 'descriptionHtml' => 'Inspect any <span class="info-keyword">venue\'s weekly occupancy</span> — <span class="info-keyword">real slot states</span> from the timetable database.'])

    @include('partials.ui-guide-block', [
        'guideTitle' => 'How to use this page',
        'guideItems' => [
            '<strong>Venue selection</strong> — pick a venue from the dropdown; the grid shows its full semester',
            '<strong>Week navigation</strong> — use arrows, the dropdown or Today button to browse weeks',
            '<strong>Slot colours</strong> — colour = status (green = class, amber = pending, red = conflict / public holiday), border = ownership (thick = yours, hairline = others\'); your own classes on holidays show loud red — they won\'t run',
            '<strong>View details</strong> — click an occupied slot to see module, lecturer and cohorts',
        ]
    ])

    <!-- ─── JS-owned surface (wire:ignore): the grid cells, week navigation,
         summary values and progress bar are all built client-side by the page
         script — a Livewire update would morph the server's empty shells over
         them (SDD arrange-intent-session-choice: the modal's wire call is this
         page's first update). Venue switching is ?venue= full render, so the
         server never needs to update inside this block. ─── -->
    <div wire:ignore>
    <!-- ─── Semester Progress ─── -->
    <div class="semester-progress" id="semesterProgress">
        <div class="progress-label" id="progressLabel"></div>
        <div class="progress-track"><div class="progress-fill" id="progressFill"></div></div>
    </div>

    <!-- ─── Semester Bar ─── venue + week selectors on ONE row (frozen fjing
         layout, f8b35a2; matches the cohort-timetable pattern). The 2026-10-09
         DB wiring (adc95bb) had split them into two bars — unauthorised drift,
         fixed in venue-toolbar-row-parity. Print stub restored via showPrint;
         fav-star ☆ stays dropped (documented divergence — mock localStorage),
         the capacity span stands where fjing's fav-btn sits. -->
    <div class="semester-bar">
        <div class="venue-dropdown-wrap">
            @include('partials.ui-venue-dropdown', ['selectId' => 'venueSelect'])
            {{-- venue-side-chips (the user's chip ask 2026-10-23): the capacity
                 badge = icon + number + hover tooltip, RIGHT of the venue dd.
                 The description chip on THIS page = TBD by the user (#1 not ok
                 here yet) — the plain description line below stays. --}}
            <span class="badge badge-capacity" id="venueCapacity" style="display:none;"></span>
            {{-- venue-description-doc-b006-rule: the plain room description
                 (hidden until the payload fills it — no empty stub) --}}
            <div class="venue-description" id="venueDescription" style="display:none;"></div>
        </div>
        @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeek()', 'nextOnclick' => 'nextWeek()', 'selectId' => 'weekSelect', 'selectOnclick' => 'selectWeek(this.value)', 'showPrint' => true, 'showEarliestBtn' => true])
    </div>

    <!-- ─── Week Subtitle ─── -->
    <div class="week-subtitle" id="weekSubtitle"></div>

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')
        @include('partials.ui-empty-state', ['title' => 'No classes this week', 'text' => 'This venue is free all week.'])

    <!-- ─── Legend Bar ─── 5 items + ownership hint (venue-event-blocks-db design §2,
         frozen upstream design at f8b35a2). Available tip stays honest for the
         read-only page (booking = Slice B); swatches carry real block classes. -->
    @include('partials.ui-legend-bar', ['ownershipHint' => true, 'items' => [
        ['color' => 'var(--color-success-container)', 'label' => 'Available', 'tip' => "Free slot — click to arrange a replacement in this venue (Sunday, holiday slots and slots less than 3 working days away can't be booked)"],
        ['class' => 'event-normal', 'label' => 'Normal', 'tip' => 'Scheduled class with no issues'],
        ['class' => 'event-replacement', 'label' => 'Replacement', 'tip' => 'Approved replacement session — the class ran at its NEW slot (an approved request renders here as the dark block)'],
        ['class' => 'event-pending', 'label' => 'Pending', 'tip' => 'Replacement request awaiting PL approval'],
        ['class' => 'event-conflict', 'label' => 'Conflict / Public Holiday', 'tip' => "This class will not run as scheduled — scheduling conflict or public holiday (remaining holiday slots show as empty 'PH' cells)"],
    ]])

    <!-- ─── Weekly Summary Bar ─── pinned 5-card set (Occupied kept: documented
         deviation, venue-event-blocks-db design §3); keyword spans per upstream -->
    @include('partials.ui-summary-bar', [
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Slots',
                'description' => 'All time slots shown for <span class="info-keyword">this venue</span> in the selected week.'],
            ['class' => 'card-available', 'valueId' => 'sumAvailable', 'label' => 'Available',
                'description' => '<span class="info-keyword">Free time slots</span> that can be booked for this venue — Sundays and public holidays excluded.'],
            ['class' => 'card-replacement', 'valueId' => 'sumMyClasses', 'label' => 'My Teaching Classes',
                'description' => 'Sessions <span class="info-keyword">you teach</span> in this venue this week — confirmed + <strong>pending</strong>; each class counts separately.'],
            ['class' => 'card-hours', 'valueId' => 'sumMyHours', 'label' => 'My Teaching Hours',
                'description' => 'Confirmed + pending hours of <span class="info-keyword">your classes</span> in this venue this week (each slot = <strong>30 minutes</strong>).'],
            ['class' => 'card-conflict', 'valueId' => 'sumOccupied', 'label' => 'Occupied',
                'description' => 'Slots occupied by <span class="info-keyword">scheduled classes</span> — confirmed + <strong>pending</strong> make-up holds.'],
        ]
    ])

    <p class="booking-hint-line">Click any green empty slot to arrange a replacement in this venue.</p>
    </div><!-- /wire:ignore: JS-owned surface -->

    <!-- ─── Available Slot Tooltip (U2a — frozen template :461–463) ─── -->
    <div class="available-tooltip" id="availableTooltip">
        <p id="tooltipText">Arrange a replacement in B110 on Thu, 22 Oct 2026 at 14:00?</p>
        <button class="btn-book" id="tooltipBookBtn">Arrange</button>
    </div>

    <!-- ═══ Class Detail Modal ═══ -->
    @section('modal-footer')
        <div class="modal-footer-left">
            <button class="btn-close-modal" onclick="closeModal()">Close</button>
        </div>
        <div class="modal-footer-right">
            <button class="btn-replace-now" id="btnReplaceNow" style="display:none" onclick="goToReplacementFor(currentModalEvent?.sessionId, currentWeek + 1, 'venue-timetable')"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg> Replace Now</button> <!-- SDD cohort-venue-replace-now T2 (user law 2026-10-24): currentWeek was the 0-BASED index — the arrangement's pinnedSessionPayload validates the 1-BASED human week (1..weeks); the raw index also made W1 a silent no-op (the !week guard ate index 0). The page's own other flows (intentSessionOptions, the arrange-URL fallback) already pass +1. -->
        </div>
    @endsection
    <!-- SDD cohort-venue-replace-now T2 (apply-phase receipt): the venue page
         had this include BEFORE its modal-footer section — the shared
         partial's section-check saw NO 'modal-footer' section yet and
         rendered its fallback (Close only): the frozen class-modal-affordances
         venue Replace Now button HAS NEVER RENDERED (its assert
         `!r || display==='none'` accepts an ABSENT r).
         Reordered AFTER the section (the my-timetable :94→:106 / cohort
         :165→:174 order = the precedent) so the footer actually mounts.
         NOTE: no @-directives may appear in this comment (Blade compiles
         them even inside HTML comments — the 500 ParseError receipt
         2026-10-25). -->
    @include('partials.ui-class-detail-modal')

    @include('partials.ui-cancel-class-modal')

    <!-- ─── Select a Session modal (SDD arrange-intent-session-choice D2) ─── -->
    @include('partials.ui-intent-session-modal')

    <script>
        /* Real-data bridge. DOMContentLoaded: mock-data.js / ui-common.js are
           parsed AFTER @yield('content') in the layout body (my-timetable pattern). */
        document.addEventListener('DOMContentLoaded', function () {
            MockData.semester = @json($semesterJs);
            MockData.holidays = @json($holidaysJs);
            /* Clock-anchor bridge (U2a D1, 4 in-repo precedents): the too-soon
               branch and week-open must run on the REAL date, not the mock
               demo anchor. Declared behavior change — page opens on the real
               current week (changelog-documented). */
            MockData.mockNow = null;

            window.weekData = generateWeekData();
            const weekData = window.weekData;
            const eventsData = @json($eventsByWeek);
            /* venue-slot-validation (2026-11-06): no class-length hint is served
               any more — the hover floor is a fixed 60 minutes and the real
               duration is read from the class the user picks in the pop-up. */
            /* class-modal-affordances T3: the cancel machinery's twin store
               (the same bridge as my-timetable's — resolveMatches reads
               MockData.myTimetable.eventsByWeek). */
            MockData.myTimetable = { eventsByWeek: eventsData };
            MockData.currentUser = @json(['name' => auth()->user()?->name ?? '', 'staffId' => (string) (auth()->user()?->lecturer?->staff_id ?? '')]); // class-modal-affordances: the ownership bridge (was the missing piece)
            const totalsData = @json($totalsByWeek);

            window.currentWeek = currentWeekIndex();
            let currentWeek = window.currentWeek;
            window.currentModalEvent = null;
            let countedAvailable = 0;   /* the render's own bookable-cell count — the AVAILABLE card reads it (user accuracy 2026-10-19) */

            /* week-memory-until-logout (T4): the session-scoped mode — the
               per-login stamp binds this page's week memory to the login
               session (logout/expiry re-lands today); the :766 load() call
               reads ONLY the stamp-scoped sessionStorage (no new wiring). */
            const weekNav = new WeekNavigator(MockData.semester, weekData, null, 'venueTimetableWeek', null, { sessionScoped: true, loginStamp: @json(session('login_week_stamp')) });
            weekNav._currentWeek = currentWeek;

            const venueInfo = @json($venue);

            // last-visit-dd-memory (D4): ONE hoisted const, TWO consumers —
            // the dd's opts + the restore's known-code guard. The name
            // names what the blade SERVED (never conflate with the saved
            // sessionStorage payload or the known boolean).
            const servedVenues = @json($venuesJs);
            const ownerStaffId = @json((string) (auth()->user()?->lecturer?->staff_id ?? ''));

            /* Restore (last-visit-dd-memory): the ?venue= ABSENCE guard —
               the URL stays the SSOT (an explicit ?venue= wins byte-perfect);
               the saved code must exist in the SERVED list (the
               VenueDropdown.select's ignore-unknown behaviour lines up), and
               the one-time location.replace reuses the existing full render
               (no history spam). Sits inside the DOMContentLoaded handler —
               the script already is. */
            const urlVenue = new URLSearchParams(window.location.search).get('venue');
            const savedVenue = urlVenue ? null : LastVisit.recall('venuePage', ownerStaffId);
            const known = servedVenues.some(v => v.code === savedVenue);
            if (!urlVenue && known && savedVenue !== venueInfo.code) {
                window.location.replace('?venue=' + encodeURIComponent(savedVenue));
                return;   // the rest of the init runs after the replacement render
            }

            /* Venue switching = ?venue= full render (scripts re-run naturally);
               the shared factory is callback-based — no forking. */
            new VenueDropdown(
                document.getElementById('venueSelectDropdown'),
                {
                    venues: servedVenues,
                    initialCode: venueInfo.code || undefined,
                    onSelect: function (code) {
                        // last-visit-dd-memory (D4): the memory rides THIS page's
                        // own closure (never inside the shared VenueDropdown
                        // class — replacement-arrangement's dd must not remember).
                        LastVisit.remember('venuePage', code, ownerStaffId);
                        if (code && code !== venueInfo.code) {
                            window.location.assign('?venue=' + encodeURIComponent(code));
                        }
                    }
                }
            );

            const capacityEl = document.getElementById('venueCapacity');
            if (capacityEl && venueInfo.capacity) {
                /* venue-side-chips (the user's chip ask 2026-10-23): the
                   capacity badge = icon + number + hover tooltip — the
                   shared .badge pill family (badge-past neutral). */
                capacityEl.textContent = '';
                capacityEl.insertAdjacentHTML('beforeend',
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>');
                capacityEl.insertAdjacentText('beforeend', String(venueInfo.capacity));
                capacityEl.setAttribute('data-tip', 'Capacity \u2014 ' + venueInfo.capacity + ' seats');
                capacityEl.style.display = '';
            }

            /* venue-description-doc-b006-rule (the sv's directive 2026-10-23): the
               plain room description as a data-tip on the dropdown trigger —
               one line, no priority wording. Served from the DB via the
               venue payload (canonical always). */
            const venueDescEl = document.getElementById('venueDescription');
            if (venueDescEl && venueInfo.description) {
                venueDescEl.textContent = venueInfo.description;
                venueDescEl.style.display = '';
            }

            function openModal(event) {
                window.currentModalEvent = event;
                openClassModal({
                    week: window.currentWeek + 1,
                    event: event,
                    dayIndex: event.di,
                    days: weekData[currentWeek].days,
                    modalId: 'classModal',
                    title: 'Slot Details',
                    extraFields: [
                        { label: 'Cohort', value: event.cohort || '—' },
                        /* BMSE3153-class-info (user law 2026-10-27): the shared
                           totalStudentsLabel (ui-common.js) renders the per-
                           cohort `A + B = total` breakdown for multi-cohort
                           classes (slot payload carries studentCounts). */
                        { label: 'Total Students', value: window.totalStudentsLabel(event) },
                        { label: 'Room Name', value: venueInfo.name || '—' },
                        { label: 'Capacity', value: venueInfo.capacity ? String(venueInfo.capacity) : '—' }
                    ]
                });

                /* class-modal-affordances T3: the restored footer wiring.
                   Replace Now = OWN + the conflict fact (the viewed week —
                   the venue grid is week-scoped, the FACT-week-only law by
                   construction); Cancel = the isCancellable guards. */
                /* venue-status-parity T4 (bugs #4/#12): the gate is the SERVED
                   exact-pair conflict fact — the same datum my-timetable reads.
                   `occurrenceFact` is null when a live proposal/approval already
                   HANDLES the pair (no button — a dead end) and null on a normal
                   week, while a cancelled / holiday pair carries one (button).
                   The hand-rolled `status === 'conflict'` heuristic missed the
                   holiday half and could not see a proposal. Livewire's marker
                   morph can blank the DOM clone's extra key, so the truth is
                   re-read from the SERVED array by id+di. */
                const rn = document.getElementById('btnReplaceNow');
                if (rn) {
                    const isMine = event.mine === true || event.lecturer === MockData.currentUser?.name;
                    let stamped = event;
                    try {
                        /* identity = sessionId + day + start (NOT `id`: the derived
                           holiday family carries id = null, so an id match would
                           pair one block's fact with another's and show a
                           dead-end button). */
                        (eventsData[currentWeek] || []).forEach(function (x) {
                            if (x && x.sessionId === event.sessionId
                                && x.di === event.di && x.start === event.start
                                && x.occurrenceFact) {
                                stamped = x;
                            }
                        });
                    } catch (e) { /* pre-boot: the clone is the truth */ }
                    rn.style.display = (isMine && stamped.occurrenceFact) ? 'flex' : 'none';
                }
                /* venue-status-parity T4 (bug #7): an already-cancelled class must
                   not offer "Cancel Class?" — the action is spent (the other pages
                   hide it on the cancelled block). */
                if (event.status !== 'cancelled') {
                    CancelClass.renderButton(
                        document.querySelector('#classModal .modal-footer'),
                        event, weekData[currentWeek].days, currentWeek,
                        function () {
                            closeModal();
                            buildTimetable();
                        }
                    );
                }
            }

            function closeModal() {
                document.getElementById('classModal').style.display = 'none';
            }

            function closeModalOutside(e) {
                closeOnOverlayClick(e, closeModal);
            }

            closeOnEsc(closeModal);

            /* ═══ Available tooltip + Arrange handoff (U2a — ported from the
               frozen template :1044–1117; wording per user decision 2026-10-13;
               no code/cohort appends — the venue page has no subject context
               and the consumer already handles their absence). */
            /* venue-slot-validation (2026-11-06, user brief §2/§5): the HOVER
               floor is one continuous hour and nothing else. It is NOT a class
               assumption — the class the user intends to move is unknown until
               they pick one in the pop-up, and the real duration is validated
               there (MIN_BOOK_SLOTS is only the smallest booking the page will
               ever offer). */
            const MIN_BOOK_SLOTS = 2;   /* 2 half-hour slots = 60 minutes */
            let focusedCell = null;

            /* Index of the NEXT event start below a cell on the same day
               (Infinity = nothing below). One definition for the paint loop and
               for the post-selection check. */
            function nextEventStartAfter(di, hi) {
                const dayEvents = eventsData[currentWeek] || [];
                let next = Infinity;
                dayEvents.forEach(function (ev) {
                    if (ev.di !== di || ev.start <= hi) return;
                    if (ev.start < next) next = ev.start;
                });
                return next;
            }

            /* Would an event already sit inside [hi, hi + spanSlots)? Lifted out
               of the modal closure so stage 1 (hover) and stage 2 (after the
               class is picked) can never disagree. */
            function spanBlocked(di, hi, spanSlots) {
                const spanTo = Math.min(hi + spanSlots, hours.length);
                return (eventsData[currentWeek] || []).some(function (ev) {
                    return ev.di === di && (hi < ev.end + 1) && (spanTo - 1 >= ev.start);
                });
            }

            /* Room between a free cell and the NEXT event, in half-hour slots
               (Infinity = nothing below). */
            function roomBeforeNextEvent(di, hi) {
                const next = nextEventStartAfter(di, hi);
                return next === Infinity ? Infinity : next - hi;
            }

            function showAvailableTooltip(ev, di, hi, cell) {
                /* venue-timetable-bookable-cells: a cell that cannot hold ANY of
                   this venue's classes says so BEFORE the click — same bottom-left
                   message the page already uses, no arrange pop-up. */
                if (cell && cell.classList.contains('cell-no-room')) {
                    toast.show('Not enough room below this class — pick a Free Cell.');
                    return;
                }
                if (hi + MIN_BOOK_SLOTS > hours.length) {
                    toast.show('A booking needs ' + (MIN_BOOK_SLOTS * 30) + ' minutes — not enough time left in the day.');
                    return;
                }
                const tooltip = document.getElementById('availableTooltip');
                const dayCode = weekData[currentWeek].days[di]?.abbr || '';
                /* the arrange question is a sentence, so the day word is spelled
                   out (audit §0 ruling 3: `Thursday` in prose, `Thu` in lists) */
                const dayLong = {
                    Mon: 'Monday', Tue: 'Tuesday', Wed: 'Wednesday', Thu: 'Thursday',
                    Fri: 'Friday', Sat: 'Saturday', Sun: 'Sunday'
                };
                const dayName = dayLong[dayCode] || dayCode;
                const dateStr = weekData[currentWeek].days[di]?.date || '';
                const time = hours[hi] ? to12h(hours[hi]) : '';

                document.getElementById('tooltipText').textContent =
                    'Arrange a replacement in ' + venueInfo.code + ' on ' + dayName + ', ' + dateStr + ' at ' + time + '?';

                document.getElementById('tooltipBookBtn').onclick = function() {
                    /* U2a handoff → this change routes through the modal: the
                       chosen subject/stream/format/queue accompanies the
                       anchor session (arrange-intent-session-choice D2). */
                    openIntentSessionModal(time, di, hi);
                };

                if (ev && ev.target) {
                    const rect = ev.target.getBoundingClientRect();
                    tooltip.style.left = rect.left + 'px';
                    tooltip.style.top = (rect.bottom + 4) + 'px';
                } else {
                    tooltip.style.left = '50%';
                    tooltip.style.top = '50%';
                    tooltip.style.transform = 'translate(-50%, -50%)';
                }

                tooltip.classList.add('show');
            }

            function hideAvailableTooltip() {
                document.getElementById('availableTooltip').classList.remove('show');
                document.getElementById('availableTooltip').style.transform = '';
            }

            /* close tooltip on scroll (cell position is no longer relevant) */
            window.addEventListener('scroll', hideAvailableTooltip, { passive: true });
            document.querySelector('.grid-scroll')?.addEventListener('scroll', hideAvailableTooltip, { passive: true });

            function bookVenue(venueCode, date, time) {
                hideAvailableTooltip();
                /* a fresh Arrange click is a new intent: if THIS slot was
                   previously cancelled (auto-select discarded) or its banner
                   dismissed, start clean */
                const bookingKey = venueCode + '|' + date + '|' + time;
                try {
                    if (sessionStorage.getItem('bookingIntentCancelled') === bookingKey) sessionStorage.removeItem('bookingIntentCancelled');
                    if (sessionStorage.getItem('bookingIntentDismissed') === bookingKey) sessionStorage.removeItem('bookingIntentDismissed');
                } catch (e) {}
                window.location.href = '/replacement-arrangement?venue=' + encodeURIComponent(venueCode) + '&date=' + encodeURIComponent(date) + '&time=' + encodeURIComponent(time) + '&from=venue-timetable';
            }

            /* ═══ Select a Session modal (arrange-intent-session-choice D2) ═══
               The clicked slot's week/hour ride along so the client can do the
               per-session arithmetic only (duration fit vs day end). All rule
               data (type→room_type, B005) comes server-side in the wire
               response — the client hosts no rule. */
            let intentModalOpen = false;
            let intentContext = null; // {dateStr, timeStr, di, hi, venue} of the BOOKED slot

            function openIntentSessionModal(time, di, hi) {
                intentModalOpen = true;
                intentContext = {
                    dateStr: weekData[currentWeek].days[di].date,
                    timeStr: hours[hi],
                    /* venue-slot-validation D3: stage 2 needs the cell's own
                       coordinates, and `confirmIntentSession` sits outside the
                       render closure. */
                    di: di,
                    hi: hi,
                    venue: venueInfo.code,
                };
                const week1 = currentWeek + 1;
                pageLivewire().call('intentSessionOptions', week1).then(function (model) {
                    if (!intentModalOpen) return;
                    renderIntentSessionModal(model, time, di, hi);
                });
            }

            function closeIntentSessionModal() {
                intentModalOpen = false;
                const el = document.getElementById('intentSessionModal');
                el.style.display = 'none';
                const sessionDd = document.getElementById('intentSessionDd');
                if (sessionDd) sessionDd.classList.remove('open');
                el.classList.remove('show');
                if (focusedCell) { try { focusedCell.focus(); } catch (e) {} }
            }

            /* outside click closes the occurrence dropdown (venue-dropdown parity) */
            document.addEventListener('mousedown', function (e) {
                const dd = document.getElementById('intentSessionDd');
                if (dd && dd.classList.contains('open') && !dd.contains(e.target)) dd.classList.remove('open');
            });

            function intentOverlayClose(ev) {
                if (ev.target === ev.currentTarget) closeIntentSessionModal();
            }

            function renderIntentSessionModal(model, time, di, hi) {
                const el = document.getElementById('intentSessionModal');
                const notice = document.getElementById('intentNotice');
                const noteText = document.getElementById('intentNoticeText');
                const subtitle = document.getElementById('intentSubtitle');
                subtitle.textContent = 'Booking ' + model.venue + ' · ' + weekData[currentWeek].days[di].abbr + ', ' + weekData[currentWeek].days[di].date + ' · ' + to12h(hours[hi]);
                const pickers = document.getElementById('intentPickers');
                const subjectSel = document.getElementById('intentSubjectSelect');
                const sessionDd = document.getElementById('intentSessionDd');
                const sessionTrigger = document.getElementById('intentSessionTrigger');
                const sessionTriggerLabel = document.getElementById('intentSessionTriggerLabel');
                const sessionPanel = document.getElementById('intentSessionPanel');
                const arrangeBtn = document.getElementById('intentArrangeBtn');
                const dayEnd = hours[hours.length - 1];
                /* occurrence-grain selection (user request 2026-10-16): the picked
                   row = one flagged occurrence; Arrange books that SESSION at the
                   clicked cell (handoff contract unchanged). */
                let sessionPick = null;

                subjectSel.innerHTML = '';
                sessionTriggerLabel.textContent = 'Select the conflicted class…';
                sessionPanel.innerHTML = '';
                sessionDd.classList.remove('open');
                sessionDd.dataset.pick = '';

                /* Zero-fit / zero-compatible: no dropdowns, byte-frozen notice */
                const fitting = (model.modules || []);
                if (fitting.length === 0) {
                    noteText.innerHTML = noticeHtml(
                        'None of your classes can run in ' + model.venue,
                        roomTypeHuman(model.roomType) + ' hosts ' + sessionTypesHuman(model.roomType)
                            + ' sessions' + ((model.roomType === 'lab' || model.roomType === 'cisco_lab' || model.roomType === 'lecture_hall') ? ' only' : '') + '.');   // lh-l-only-revert: the halls rejoin the 'only' family
                    notice.style.display = 'block';
                    pickers.style.display = 'none';
                    arrangeBtn.style.display = 'none';
                    /* show parity with the shared shells (ui-common DetailModal.open): flex centers it */
                    el.classList.add('show');
                    el.style.display = 'flex';
                    return;
                }

                notice.style.display = 'none';
                pickers.style.display = 'block';
                arrangeBtn.style.display = '';

                fitting.forEach(function (m, mi) {
                    const opt = document.createElement('option');
                    opt.value = String(mi);
                    opt.textContent = m.code + ' — ' + m.name;
                    subjectSel.appendChild(opt);
                });

                /* venue-slot-validation (user brief §2 Stage 1): the modal no
                   longer judges a class it has not been given. Every session of
                   the selected subject is listed; the real duration is checked
                   when the user presses Arrange (`confirmIntentSession`). The
                   rows carry `data-slots` (the session's own length) and
                   `data-fit` (would it fit from this cell?) so stage 2 and the
                   pre-selection read one source of truth. */
                function chooseSession(item) {
                    sessionDd.querySelectorAll('.session-dd-item').forEach(function (n) { n.classList.remove('active'); });
                    item.classList.add('active');
                    sessionTriggerLabel.textContent = item.dataset.label;
                    sessionDd.dataset.pick = item.dataset.label;
                    sessionPick = { sessionId: item.dataset.sid, cohortLabel: item.dataset.cohort || '' };
                    sessionDd.classList.remove('open');
                }

                function sessionFits(e) {
                    const slots = e.durationSlots || 1;
                    if (hi + slots > hours.length) return false;
                    return !spanBlocked(di, hi, slots);
                }

                function refreshSessions() {
                    const mi = subjectSel.value;
                    const subject = fitting[mi];
                    sessionPanel.innerHTML = '';
                    sessionPick = null;
                    let firstFit = null;
                    let firstAny = null;
                    (subject.order || []).forEach(function (sid) {
                        const e = subject.sessions[sid];
                        /* the session then EXPANDS into one row per flagged
                           occurrence (user request 2026-10-16) */
                        const start = (e.startTime || '').slice(0, 5);
                        const end = (e.endTime || '').slice(0, 5);
                        const cohort = (e.cohortLabels && e.cohortLabels[0]) || (e.cohorts && e.cohorts[0]) || '';
                        const occs = e.conflictOccurrences && e.conflictOccurrences.length ? e.conflictOccurrences : [{ week: '', dayAbbr: e.dayAbbr || '', label: (e.date || ''), reason: e.conflictReason || '' }];
                        const fits = sessionFits(e);
                        occs.forEach(function (occ) {
                            const item = document.createElement('div');
                            item.className = 'session-dd-item';
                            item.setAttribute('role', 'option');
                            item.dataset.sid = String(sid);
                            item.dataset.cohort = cohort;
                            item.dataset.slots = String(e.durationSlots || 1);
                            item.dataset.fit = fits ? '1' : '0';
                            /* row label — the user's requested format: type, week, day, date, time range */
                            item.dataset.label = '(' + e.type + ')' + (occ.week ? ' W' + occ.week + ',' : '') + (occ.dayAbbr ? ' ' + occ.dayAbbr + ',' : '') + (occ.label ? ' ' + occ.label : '') + ' . ' + start + ' ~ ' + end;
                            item.textContent = item.dataset.label;
                            /* details tooltip — the shared data-tip system (ui-common) renders any [data-tip] row */
                            item.setAttribute('data-tip', subject.code + ' ' + subject.name +
                                ' · ' + (e.studentCount || 0) + ' students' + (cohort ? ' (' + cohort + ')' : '') +
                                ' · usually ' + (e.venue || '—') + ' ' + (occ.dayAbbr || '') + ' ' + start + '–' + end +
                                ' · conflict: ' + (occ.reason || e.conflictReason || '—'));
                            item.dataset.week = String(occ.week || '');
                            item.onclick = function () { chooseSession(item); };
                            sessionPanel.appendChild(item);
                            if (!firstAny) firstAny = item;
                            if (fits && !firstFit) firstFit = item;
                        });
                    });
                    /* pre-select the first row that FITS (the old convenience),
                       else the first row — the user may still press Arrange and
                       be told why it does not fit */
                    const first = firstFit || firstAny;
                    if (first) chooseSession(first);
                    arrangeBtn.disabled = !firstAny;
                    notice.style.display = 'none';
                    pickers.style.display = 'block';
                    arrangeBtn.style.display = '';
                }

                subjectSel.onchange = function () { refreshSessions(); };
                sessionTrigger.onclick = function () { sessionDd.classList.toggle('open'); };
                refreshSessions();
                /* show parity with the shared shells (ui-common DetailModal.open :1185) */
                el.classList.add('show');
                el.style.display = 'flex';
            }

            /* rich notice typography (user-approved 2026-10-16): CAPS+bold head
               in error red, muted reason line, amber CAPS guidance — token-only
               classes from theme.css (.notification-head/.notice-*) */
            function noticeHtml(head, muted, guideCaps, guideTail) {
                let html = '<strong class="notice-head">' + head + '</strong>' +
                    '<span class="notice-muted">' + muted + '</span>';
                if (guideCaps) {
                    html += '<span class="notice-guide">' + guideCaps + '</span>' +
                        '<span class="notice-muted">' + guideTail + '</span>';
                }
                return html;
            }

            /* roomTypeHuman = the framing copy ONLY (page-local, user-frozen —
               not the promote set). sessionTypesHuman folds into the SHARED law
            helper (SDD lt-venue-capacity-rule D5 via ui-common.js); the
            sentence's 'only' attaches ONLY to the P rooms keyed on raw. */
            function roomTypeHuman(raw) {
                return { lab: 'Lab', cisco_lab: 'Lab', tutorial: 'tutorial room', lecture_hall: 'lecture hall' }[raw] || raw;
            }
            function sessionTypesHuman(raw) {
                return venueTypeWording(raw);
            }

            function confirmIntentSession() {
                const dd = document.getElementById('intentSessionDd');
                const pickLabel = dd ? dd.dataset.pick : '';
                if (!pickLabel || !intentContext) return;
                const sel = document.getElementById('intentSubjectSelect');
                const moduleLabel = sel.options[sel.selectedIndex] ? sel.options[sel.selectedIndex].textContent.split(' — ')[0] : '';
                /* the picked SESSION id rides on the panel rows (data-sid) — the
                   occurrence-grain visual choice collapses to the session handoff.
                   venue-cross-week-handoff (T1): the row's OWN occurrence week
                   (data-week) rides too — the URL's &week= becomes the pinned
                   occurrence's week instead of the viewed slot's week. */
                const pickedRow = dd.querySelector('.session-dd-item.active');

                /* venue-slot-validation (user brief §2 Stage 2 / §3): the ONE place
                   where a duration verdict is allowed. The class is picked, its
                   real length is known, so the cell is checked against it — day
                   end first, then the events below. Failure never navigates. */
                if (pickedRow) {
                    const slots = Math.max(1, parseInt(pickedRow.dataset.slots, 10) || 1);
                    const start = intentContext.hi;
                    const dayEnd = hours[hours.length - 1];
                    if (start + slots > hours.length) {
                        showSlotWarning('dayEnd', { slots: slots, start: start, dayEnd: dayEnd });
                        return;
                    }
                    if (spanBlocked(intentContext.di, start, slots)) {
                        showSlotWarning('span', {
                            slots: slots,
                            start: start,
                            next: nextEventStartAfter(intentContext.di, start),
                        });
                        return;
                    }
                }

                bookVenueWithSession(venueInfo.code, intentContext.dateStr, intentContext.timeStr, moduleLabel, pickedRow ? pickedRow.dataset.sid : '', pickedRow ? (pickedRow.dataset.cohort || '') : '', pickedRow ? (pickedRow.dataset.week || '') : '');
            }

            /* ── Slot-too-short warning (venue-slot-validation D4) ───────────
               An in-page warning modal, never a browser alert, shown only after
               a class was picked. `hours[]` labels are the grid's own language,
               and the duration is the picked class's real `data-slots`. */
            function slotsHuman(slots) {
                const n = Math.max(1, slots || 1) / 2;
                return (n % 1 === 0 ? String(n) : n.toFixed(1)) + (n === 1 ? ' hour' : ' hours');
            }

            function showSlotWarning(kind, facts) {
                const overlay = document.getElementById('slotWarningOverlay');
                if (!overlay) return;
                const venue = (intentContext && intentContext.venue) || venueInfo.code;
                const startLabel = hours[facts.start] || '';
                const dur = slotsHuman(facts.slots);
                const head = document.getElementById('slotWarningHead');
                const body = document.getElementById('slotWarningBody');
                const guide = document.getElementById('slotWarningGuide');
                const tail = document.getElementById('slotWarningTail');
                if (kind === 'dayEnd') {
                    head.textContent = 'This class does not fit before the day end (' + facts.dayEnd + ')';
                    body.textContent = startLabel + ' plus the selected class duration (' + dur + ') runs past ' + facts.dayEnd + '.';
                    guide.textContent = 'PICK AN EARLIER CELL';
                    tail.innerHTML = '— the grid ends at <strong style="color:var(--color-on-success-container)">' + facts.dayEnd + '</strong>.';
                } else {
                    head.textContent = 'None of your classes can run in ' + venue + ' at this cell';
                    body.textContent = startLabel + ' plus the selected class duration (' + dur + ') runs into an existing event below'
                        + (facts.next !== Infinity && hours[facts.next] ? ' — the class starting at ' + hours[facts.next] : '') + '.';
                    guide.textContent = 'NOT ENOUGH FREE TIME BELOW';
                    tail.innerHTML = '— pick a <strong style="color:var(--color-on-success-container)">Free Cell</strong>.';
                }
                /* the intent modal goes away first: the warning is the answer to
                   the Arrange click, and closing it must land on the timetable */
                closeIntentSessionModal();
                overlay.classList.add('show');
                overlay.style.display = 'flex';
                const btn = document.getElementById('slotWarningPickBtn');
                if (btn) { try { btn.focus(); } catch (e) {} }
            }

            function closeSlotWarning(ev) {
                const overlay = document.getElementById('slotWarningOverlay');
                if (!overlay) return;
                /* the overlay's own click closes; a click inside the card does not */
                if (ev && ev.type === 'click' && ev.target !== ev.currentTarget) return;
                overlay.classList.remove('show');
                overlay.style.display = 'none';
                if (focusedCell) { try { focusedCell.focus(); } catch (e) {} }
            }

            /* venue-cross-week-handoff (T2): occurrenceWeek = the picked row's
               own occurrence week; the URL's &week= = THE PINNED occurrence's
               week (pinnedSessionPayload's assumed meaning). The pre-select
               (chooseSession(first) at the pool build) marks its row .active,
               so the auto-picked first row's week threads too. Rows WITHOUT a
               week (week:'' legacy rows) keep the viewed-week fallback
               byte-for-byte. &date/&time stay the slot's own landing facts
               (design D3). */
            function bookVenueWithSession(venueCode, date, time, code, sessionId, cohortLabel, occurrenceWeek) {
                hideAvailableTooltip();
                const bookingKey = venueCode + '|' + date + '|' + time;
                try {
                    if (sessionStorage.getItem('bookingIntentCancelled') === bookingKey) sessionStorage.removeItem('bookingIntentCancelled');
                    if (sessionStorage.getItem('bookingIntentDismissed') === bookingKey) sessionStorage.removeItem('bookingIntentDismissed');
                } catch (e) {}
                closeIntentSessionModal();
                window.location.href = '/replacement-arrangement?venue=' + encodeURIComponent(venueCode)
                    + '&date=' + encodeURIComponent(date)
                    + '&time=' + encodeURIComponent(time)
                    + '&from=venue-timetable'
                    + '&code=' + encodeURIComponent(code)
                    + '&session=' + encodeURIComponent(sessionId)
                    + '&cohort=' + encodeURIComponent(cohortLabel)
                    + '&week=' + encodeURIComponent(occurrenceWeek ? occurrenceWeek : currentWeek + 1);
            }

            /* B shortcut opens on the Tab-focused cell; Escape hides the
               tooltip (the modal's Escape path is the existing closeOnEsc;
               both fire harmlessly — same pairing as the frozen template). */
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') {
                    hideAvailableTooltip();
                    return;
                }
                if ((e.key === 'b' || e.key === 'B') && focusedCell && focusedCell.classList.contains('cell-available')) {
                    showAvailableTooltip(null, parseInt(focusedCell.dataset.day, 10), parseInt(focusedCell.dataset.hour, 10), focusedCell);
                }
            });

            function buildTimetable() {
                currentWeek = weekNav.currentWeek;
                countedAvailable = 0;
                buildTimetableGrid({
                    events: eventsData[currentWeek] || [],
                    days: weekData[currentWeek].days,
                    /* parity with the arrangement grid: the semester's earliest
                       bookable day carries the "Bookings open" chip (data-driven
                       via generateWeekData's firstBookable flag) */
                    bookableBadge: true,
                    /* Page paints every cell (venue-event-blocks-db design §1):
                       cellRender mode = the frozen two-axis block language.
                       PH/Sunday empties get offday cells; too-soon renders
                       read-only; free slots are bookable (U2a Arrange
                       affordance); blocks carry colour=status + border=ownership. */
                    cellRender: function (td, di, hi, day, info) {
                        /* PH-day exception (holiday ONLY — Sundays hide all
                           events): the viewer's OWN classes still render as
                           loud red blocks on public-holiday days ("your class
                           won't run"); everyone else's stay empty 'PH' cells. */
                        const offMine = info && info.event && day.holiday && info.event.mine;
                        /* T21 (occurrence-week-pin-truthfulness rider): the PH
                           branch splits — Sundays stay frozen (hide-all); PH
                           days render their event blocks (derived + imported),
                           the PH cell only when the cell carries NO event:
                             OWN event → the loud 3px red (event-conflict)
                             OTHERS' → the red hairline (event-public-holiday)
                           (the D-P10 coexistence + the §10.0 two-axis rule). */
                        if (day.sunday) {
                            const div = document.createElement('div');
                            div.className = 'cell-sun';
                            td.appendChild(div);
                        /* T23 (the grid-overflow fix, user-approved 2026-10-20):
                           continuation cells (span−1 family: event null +
                           occupied true — ownership-independent) fall through
                           to branch 4's display:none — holiday rows total
                           hours.length again; the ph cells only render for
                           genuinely-empty cells. Covers all three ownership
                           cases (mine / others' / derived). */
                        } else if (day.holiday && !offMine && !(info && info.event) && !(info && info.occupied)) {
                            const div = document.createElement('div');
                            div.className = 'cell-content cell-ph';
                            td.appendChild(div);
                        } else if (info && info.event) {
                            const e = info.event;
                            const div = document.createElement('div');
                            div.className = 'event-block span-' + info.span;   // span = e.end - e.start + 1 (half-hours)
                            div.setAttribute('tabindex', '0');
                            div.setAttribute('role', 'button');
                            div.setAttribute('aria-label', 'View class details: ' + e.code + ' ' + hours[hi]);
                            div._evt = e; div._di = di; div.__eventData = e;   // parity with default builder (keyboard handlers)
                            div.dataset.name = e.name || '';
                            div.dataset.venue = e.venue || '';
                            /* `.event-block::after` renders "name · lecturer|YOU · cohorts · STATUS"
                               (data-name + this data-tip2 — the user's hover-voice
                               directive 2026-10-25, relabelled ME → YOU 2026-11-05): the
                               viewer's OWN classes read YOU (e.mine = server truth, same
                               flag the block border uses), cohorts join in written order
                               lecturer → cohorts, status CAPS. The cohort segment
                               COLLAPSES when a session carries no cohort data — never
                               a '—' filler. */
                            const cohTxt = (e.cohorts && e.cohorts.length) ? e.cohorts.join(' + ') : (e.cohort || '');
                            div.dataset.tip2 = [e.mine ? 'YOU' : (e.lecturer || '—'), cohTxt, eventStatusLabel(e, day.holiday).toUpperCase()].filter(Boolean).join(' · ');
                            if (day.holiday) {
                                /* T21: on a PH day — OWN = loud 3px red, OTHERS =
                                   the red hairline (the D-P10 two-axis shape) */
                                div.classList.add(e.mine ? 'event-conflict' : 'event-public-holiday');
                            } else if (e.status === 'replacement') {
                                // USER 2026-10-19: the approved replacement carries the
                                // REPLACEMENT blue (my-timetable's own
                                // event-replacement class) — ownership borders stay.
                                div.classList.add(e.mine ? 'event-mine-replacement' : 'event-others-replacement');
                            } else if (e.status === 'pending') {
                                div.classList.add(e.mine ? 'event-mine-pending' : 'event-others-pending');
                            } else if (e.status === 'conflict' || e.status === 'cancelled') {
                                /* owner-gated: mine loud 3px red; others' hairline red
                                   (§10.0 two-axis). venue-status-parity T4 (bug #2):
                                   a CANCELLED class is red too — the class will not
                                   run, which is the same thing the conflict colour
                                   means. `event-public-holiday` is the shared
                                   hairline-red class for the OTHER lecturer's red
                                   blocks (colour = status, border = ownership). */
                                div.classList.add(e.mine ? 'event-conflict' : 'event-public-holiday');
                            } else {
                                div.classList.add(e.mine ? 'event-mine' : 'event-others');
                            }
                            const startTime = to12h(hours[e.start]);
                            const endTime = to12h(hours[e.end + 1] || add30min(hours[e.end]));
                            div.innerHTML =
                                '<span class="ev-code">' + e.code + '(' + e.type + ')</span>' +
                                '<span class="ev-venue">' + e.venue + '</span>' +
                                '<span class="ev-time">' + startTime + ' - ' + endTime + '</span>' +
                                /* venue-status-parity T4 (bugs #1/#2/#3/#8): the
                                   venue page hand-rolled ONE note arm and silently
                                   dropped the rest, so a pending block lost "(Pending
                                   since …)" and a cancelled block lost "(Cancelled)".
                                   `buildReplacementNote` is the SHARED renderer the
                                   other two pages already use — WITH the ownership
                                   gate (2026-11-02 fix, user report): the notes
                                   carry REQUEST facts ("Pending since 02 Nov 2026",
                                   "Proposed ⏳"), and another lecturer must not read
                                   them off the room screen. The predicate mirrors
                                   this page's own ownership law (`mine` = server
                                   truth: the class's lecturer, or the request's
                                   proposer on an amber block) with the cohort page's
                                   name fallback. */
                                buildReplacementNote(e, {
                                    checkOwnership: function (ev) {
                                        return ev.mine === true
                                            || ev.lecturer === MockData.currentUser?.name;
                                    }
                                });
                            div.addEventListener('click', function () { openModal(e); });
                            td.appendChild(div);
                            if (info.span > 1) td.colSpan = info.span;   // parity: builder sets it only when span > 1
                        } else if (info && info.occupied) {
                            td.style.display = 'none';                   // continuation consumed by head's colSpan
                                        } else if (isSlotTooSoon(weekData, currentWeek, di)) {
                            /* lead-time rule (frozen template :861–866): inside
                               the 3-working-day window — rendered read-only
                               (no Arrange tooltip, no keyboard) */
                            const div = document.createElement('div');
                            div.className = 'cell-content cell-too-soon';
                            td.appendChild(div);
                        } else {
                            /* Available slot — frozen template :873–884. The
                               AVAILABLE card counts ONLY what the grid renders
                               bookable (user 2026-10-19: too-soon rows were
                               inflating it) — this branch is the single
                               counting point, so the card can never disagree
                               with the cells on screen. */
                            countedAvailable++;
                            const div = document.createElement('div');
                            div.className = 'cell-content cell-available';
                            /* a class can't fit if it would run past the day end —
                               judged by the SHORTEST class this venue can host */
                            if (hi + MIN_BOOK_SLOTS > hours.length) div.classList.add('cell-no-fit');
                            /* …and it can't fit if the next class below leaves too
                               little room (cursor + hover words only, no colour) */
                            else if (roomBeforeNextEvent(di, hi) < MIN_BOOK_SLOTS) div.classList.add('cell-no-room');
                            div.tabIndex = 0;
                            div.dataset.day = di;
                            div.dataset.hour = hi;
                            div.setAttribute('role', 'button');
                            div.setAttribute('aria-label', 'Available slot: ' + day.abbr + ' ' + hours[hi]);

                            div.addEventListener('click', function(ev) {
                                focusedCell = div;
                                showAvailableTooltip(ev, di, hi, div);
                            });
                            div.addEventListener('focus', function() { focusedCell = div; });
                            td.appendChild(div);
                        }
                    }
                });
                updateSummary();
            }

            function updateSummary() {
                const t = totalsData[currentWeek]
                    || { total: 0, available: 0, occupied: 0, myClasses: 0, myHours: '0',
                         pending: 0, myPendingClasses: 0, myPendingHours: '0' };
                const set = function (id, v) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = String(v);
                };
                /* confirmed+pending split (summary-cards-confirmed-pending,
                   2026-11-02): the `+Y` tail is the PENDING-held part — slots
                   held by live make-up requests (Occupied) and YOUR pending
                   make-ups landing in this venue (the My cards). Total Slots
                   stays PLAIN: the split inside Occupied already tells it. */
                const split = function (id, confirmed, pending) {
                    const el = document.getElementById(id);
                    if (!el) return;
                    el.innerHTML = String(confirmed)
                        + '<span class="sum-pending">+' + String(pending) + '</span>';
                };
                /* venue-status-parity T8 (bug #9, the user's `+` ruling): Total
                   Slots is the only composition card that was still plain — it
                   silently dropped the pending-held rows (118 of 120 on B010 w7).
                   `X+Y` keeps both facts and matches the other cards' law (the
                   tail ALWAYS renders, `+0` included). */
                split('sumTotal', t.total, t.pending || 0);
                /* the card shows ONLY bookable cells (the grid's too-soon rows
                   excluded; user accuracy 2026-10-19) — and it is already the
                   POST-hold count, so it stays plain (the user's ruling: a `-`
                   would subtract the same slots twice). */
                set('sumAvailable', countedAvailable);
                split('sumMyClasses', t.myClasses, t.myPendingClasses || 0);
                split('sumMyHours', t.myHours, t.myPendingHours || '0');
                split('sumOccupied', t.occupied, t.pending || 0);
                updateWeekArrows(currentWeek <= 0, currentWeek >= weekData.length - 1);
            }

            function saveWeek() { weekNav.save(); window.currentWeek = currentWeek; }

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

            /* venue-timetable-bookable-cells §6.2 — the "Earliest bookable" action.
               NOT weekNav.jumpToEarliestBookable(): that resolves its destination
               with firstVisibleWeek(), and this page passes NO week filter (it is a
               browsing page that keeps every week — ui-common.js), so the shared
               method would jump to week 0. Compose the same two shared pieces the
               arrangement page's button ends up using instead:
                 firstBookableDay() → selectWeek() → flashEarliestBookableDay().
               The tooltip is computed, never hardcoded (the shared sentence). */
            (function initEarliestBookable() {
                const btn = document.getElementById('earliestBtn');
                if (!btn) return;
                const paintTip = function () {
                    const target = firstBookableDay(weekData);
                    if (!target) return;
                    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    const dayFacts = weekData[target.week].days[target.day];
                    btn.setAttribute('data-tip', 'Jump to the earliest bookable slot \u2014 '
                        + dayNames[target.day] + ', ' + dayFacts.date + ' (Week ' + (target.week + 1) + ')');
                };
                paintTip();
                btn.addEventListener('click', function () {
                    const target = firstBookableDay(weekData);
                    if (!target) return;                 /* nothing bookable — silent */
                    selectWeek(target.week);
                    flashEarliestBookableDay(weekData, target.week);
                });
            })();

            initTimetableKeyboardHandlers({
                prevWeek: prevWeek,
                nextWeek: nextWeek,
                openModal: openModal,
                modalId: 'classModal'
            });

            initGridSwipeGestures(prevWeek, nextWeek);

            /* onclick= handlers in partials resolve via window */
            window.closeIntentSessionModal = closeIntentSessionModal;
            window.intentOverlayClose = intentOverlayClose;
            window.confirmIntentSession = confirmIntentSession;
            /* venue-slot-validation: the warning modal's own handlers (header ×,
               overlay click, footer actions) live in the shared partial */
            window.closeSlotWarning = closeSlotWarning;
            window.prevWeek = prevWeek;
            window.nextWeek = nextWeek;
            window.selectWeek = selectWeek;
            window.openModal = openModal;
            window.buildTimetable = buildTimetable;
            window.updateSummary = updateSummary;
            window.closeModal = closeModal;
            window.closeModalOutside = closeModalOutside;
        });
    </script>
</div>
