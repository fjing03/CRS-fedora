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
        /* too late in the day for a full booking — hover says so instead */
        .cell-available.cell-no-fit { --hover-label: 'Too late to book'; cursor: not-allowed; }
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
    @include('partials.ui-page-header', ['title' => 'Venue Timetable', 'description' => 'Inspect any venue\'s weekly occupancy — real slot states from the timetable database.'])

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
            <span class="venue-capacity" id="venueCapacity"></span>
        </div>
        @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeek()', 'nextOnclick' => 'nextWeek()', 'selectId' => 'weekSelect', 'selectOnclick' => 'selectWeek(this.value)', 'showPrint' => true])
    </div>

    <!-- ─── Week Subtitle ─── -->
    <div class="week-subtitle" id="weekSubtitle"></div>

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')
        @include('partials.ui-empty-state', ['title' => 'No classes this week', 'text' => 'This venue is free all week.'])

    <!-- ─── Legend Bar ─── 4 items + ownership hint (venue-event-blocks-db design §2,
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
                'description' => 'Sessions <span class="info-keyword">you teach</span> in this venue this week — <strong>each class counts separately</strong>.'],
            ['class' => 'card-hours', 'valueId' => 'sumMyHours', 'label' => 'My Teaching Hours',
                'description' => 'Total hours of <span class="info-keyword">your classes</span> in this venue this week (each slot = <strong>30 minutes</strong>).'],
            ['class' => 'card-conflict', 'valueId' => 'sumOccupied', 'label' => 'Occupied',
                'description' => 'Slots occupied by <span class="info-keyword">scheduled classes</span> in the selected week.'],
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
    @include('partials.ui-class-detail-modal')

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
            const totalsData = @json($totalsByWeek);

            window.currentWeek = currentWeekIndex();
            let currentWeek = window.currentWeek;
            window.currentModalEvent = null;
            let countedAvailable = 0;   /* the render's own bookable-cell count — the AVAILABLE card reads it (user accuracy 2026-10-19) */

            const weekNav = new WeekNavigator(MockData.semester, weekData, null, 'venueTimetableWeek');
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
                capacityEl.textContent = 'Capacity: ' + venueInfo.capacity;
            }

            function openModal(event) {
                window.currentModalEvent = event;
                openClassModal({
                    event: event,
                    dayIndex: event.di,
                    days: weekData[currentWeek].days,
                    modalId: 'classModal',
                    title: 'Slot Details',
                    extraFields: [
                        { label: 'Cohort', value: event.cohort || '—' },
                        { label: 'Total Students', value: event.studentCount ? String(event.studentCount) : '—' },
                        { label: 'Room Name', value: venueInfo.name || '—' },
                        { label: 'Capacity', value: venueInfo.capacity ? String(venueInfo.capacity) : '—' }
                    ]
                });
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
            /* Slots one booking spans on the arrangement page (its default
               MAX_SELECTION: 4 half-hour slots = 2h). A booking starting too
               late in the day can't fit — don't offer Arrange on those cells. */
            const BOOK_SPAN = 4;
            let focusedCell = null;

            function showAvailableTooltip(ev, di, hi) {
                if (hi + BOOK_SPAN > hours.length) {
                    toast.show('A booking needs ' + (BOOK_SPAN * 30) + ' minutes — not enough time left in the day.');
                    return;
                }
                const tooltip = document.getElementById('availableTooltip');
                const dayName = weekData[currentWeek].days[di]?.abbr || '';
                const dateStr = weekData[currentWeek].days[di]?.date || '';
                const time = hours[hi] || '';

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
            let intentContext = null; // {dateStr, timeStr} of the BOOKED slot

            function openIntentSessionModal(time, di, hi) {
                intentModalOpen = true;
                intentContext = {
                    dateStr: weekData[currentWeek].days[di].date,
                    timeStr: hours[hi],
                };
                const week1 = currentWeek + 1;
                Livewire.first().call('intentSessionOptions', week1).then(function (model) {
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
                subtitle.textContent = 'Booking ' + model.venue + ' · ' + weekData[currentWeek].days[di].abbr + ', ' + weekData[currentWeek].days[di].date + ' · ' + hours[hi];
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

                function spanOccupied(spanSlots) {
                    /* venue occupancy for THIS day: any event whose 30-min span
                       overlaps [hi, hi + spanSlots) disqualifies the block */
                    const day = di;
                    const spanTo = Math.min(hi + spanSlots, hours.length);
                    const dayEvents = eventsData[currentWeek] || [];
                    return dayEvents.some(function (ev) {
                        return ev.di === day && (hi < ev.end + 1) && (spanTo - 1 >= ev.start);
                    });
                }

                function chooseSession(item) {
                    sessionDd.querySelectorAll('.session-dd-item').forEach(function (n) { n.classList.remove('active'); });
                    item.classList.add('active');
                    sessionTriggerLabel.textContent = item.dataset.label;
                    sessionDd.dataset.pick = item.dataset.label;
                    sessionPick = { sessionId: item.dataset.sid, cohortLabel: item.dataset.cohort || '' };
                    sessionDd.classList.remove('open');
                }

                function refreshSessions() {
                    const allFit = [];
                    let dayEndFails = 0;  /* the class itself runs past the day end */
                    let spanFails = 0;    /* the span runs into an existing event BELOW */
                    const mi = subjectSel.value;
                    let minSpan = Infinity;
                    (fitting[mi].order || []).forEach(function (sid) {
                        const e = fitting[mi].sessions[sid];
                        minSpan = Math.min(minSpan, (e.durationSlots || 1));
                        /* class-level fitness first (duration/day-end + this week's
                           occupancy); the session then EXPANDS into one row per
                           flagged occurrence (user request 2026-10-16) */
                        if (hi + (e.durationSlots || 1) > hours.length) { dayEndFails++; return; }
                        if (spanOccupied(e.durationSlots || 1)) { spanFails++; return; }
                        allFit.push([sid, e]);
                    });
                    sessionPanel.innerHTML = '';
                    sessionPick = null;
                    allFit.forEach(function (pair) {
                        const sid = pair[0]; const e = pair[1];
                        const start = (e.startTime || '').slice(0, 5);
                        const end = (e.endTime || '').slice(0, 5);
                        const cohort = (e.cohortLabels && e.cohortLabels[0]) || (e.cohorts && e.cohorts[0]) || '';
                        const occs = e.conflictOccurrences && e.conflictOccurrences.length ? e.conflictOccurrences : [{ week: '', dayAbbr: e.dayAbbr || '', label: (e.date || ''), reason: e.conflictReason || '' }];
                        occs.forEach(function (occ) {
                            const item = document.createElement('div');
                            item.className = 'session-dd-item';
                            item.setAttribute('role', 'option');
                            item.dataset.sid = String(sid);
                            item.dataset.cohort = cohort;
                            /* row label — the user's requested format: type, week, day, date, time range */
                            item.dataset.label = '(' + e.type + ')' + (occ.week ? ' W' + occ.week + ',' : '') + (occ.dayAbbr ? ' ' + occ.dayAbbr + ',' : '') + (occ.label ? ' ' + occ.label : '') + ' . ' + start + ' ~ ' + end;
                            item.textContent = item.dataset.label;
                            /* details tooltip — the shared data-tip system (ui-common) renders any [data-tip] row */
                            item.setAttribute('data-tip', fitting[mi].code + ' ' + fitting[mi].name +
                                ' · ' + (e.studentCount || 0) + ' students' + (cohort ? ' (' + cohort + ')' : '') +
                                ' · usually ' + (e.venue || '—') + ' ' + (occ.dayAbbr || '') + ' ' + start + '–' + end +
                                ' · conflict: ' + (occ.reason || e.conflictReason || '—'));
                            item.dataset.week = String(occ.week || '');
                            item.onclick = function () { chooseSession(item); };
                            sessionPanel.appendChild(item);
                        });
                    });
                    /* pre-select the first row (the native select was auto-first too) */
                    const first = sessionPanel.querySelector('.session-dd-item');
                    if (first) chooseSession(first);
                    if (allFit.length === 0) {
                        /* honest reason split (user report 2026-10-16): the day-end
                           sentence was shown even when the real blocker was the
                           span running into an existing event below the cell */
                        if (spanFails > 0) {
                            /* gap guidance (user request 2026-10-16): name the smallest
                               candidate length as the needed continuous gap (30-min slots) */
                            const gapHours = Math.round((minSpan * 30) / 60 * 10) / 10;
                            noteText.innerHTML = noticeHtml(
                                'None of your classes can run in ' + model.venue + ' at this cell',
                                hours[hi] + ' plus the class length runs into an existing event below.',
                                'Please book with at least a ' + (gapHours === 1 ? '1-hour' : gapHours + '-hour') + ' gap',
                                'Between this block and the next event — pick a cell with enough free room beneath it.');
                        } else {
                            noteText.innerHTML = noticeHtml(
                                'They do not fit before the day end (' + dayEnd + ')',
                                'None of your classes can run in ' + model.venue + ' — pick an earlier cell.');
                        }
                        notice.style.display = 'block';
                        pickers.style.display = 'none';
                        arrangeBtn.style.display = 'none';
                    } else {
                        notice.style.display = 'none';
                        pickers.style.display = 'block';
                        arrangeBtn.style.display = '';
                        arrangeBtn.disabled = false;
                    }
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
                   occurrence-grain visual choice collapses to the session handoff */
                const pickedRow = dd.querySelector('.session-dd-item.active');
                bookVenueWithSession(venueInfo.code, intentContext.dateStr, intentContext.timeStr, moduleLabel, pickedRow ? pickedRow.dataset.sid : '', pickedRow ? (pickedRow.dataset.cohort || '') : '');
            }

            function bookVenueWithSession(venueCode, date, time, code, sessionId, cohortLabel) {
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
                    + '&week=' + encodeURIComponent(currentWeek + 1);
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
                    showAvailableTooltip(null, parseInt(focusedCell.dataset.day, 10), parseInt(focusedCell.dataset.hour, 10));
                }
            });

            function buildTimetable() {
                currentWeek = weekNav.currentWeek;
                countedAvailable = 0;
                buildTimetableGrid({
                    events: eventsData[currentWeek] || [],
                    days: weekData[currentWeek].days,
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
                            /* `.event-block::after` renders "name · lecturer · status"
                               (data-name + this data-tip2 — uniform format). */
                            div.dataset.tip2 = (e.lecturer || '—') + ' · ' + eventStatusLabel(e, day.holiday);
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
                            } else if (e.status === 'conflict') {
                                // owner-gated: mine loud 3px red; others' hairline red (§10.0 two-axis)
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
                                (e.status === 'replacement' && e.remarks
                                ? '<span class="ev-note">(Replaced for ' + e.remarks + ')</span>'
                                : '');
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
                            /* a booking can't fit if it would run past the day end */
                            if (hi + BOOK_SPAN > hours.length) div.classList.add('cell-no-fit');
                            div.tabIndex = 0;
                            div.dataset.day = di;
                            div.dataset.hour = hi;
                            div.setAttribute('role', 'button');
                            div.setAttribute('aria-label', 'Available slot: ' + day.abbr + ' ' + hours[hi]);

                            div.addEventListener('click', function(ev) {
                                focusedCell = div;
                                showAvailableTooltip(ev, di, hi);
                            });
                            div.addEventListener('focus', function() { focusedCell = div; });
                            td.appendChild(div);
                        }
                    }
                });
                updateSummary();
            }

            function updateSummary() {
                const t = totalsData[currentWeek] || { total: 0, available: 0, occupied: 0, myClasses: 0, myHours: '0' };
                const set = function (id, v) {
                    const el = document.getElementById(id);
                    if (el) el.textContent = String(v);
                };
                set('sumTotal', t.total);
                /* the card shows ONLY bookable cells (the grid's too-soon rows
                   excluded; user accuracy 2026-10-19) */
                set('sumAvailable', countedAvailable);
                set('sumMyClasses', t.myClasses);
                set('sumMyHours', t.myHours);
                set('sumOccupied', t.occupied);
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
