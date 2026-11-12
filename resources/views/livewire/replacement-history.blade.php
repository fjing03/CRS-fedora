{{-- The frozen UI design from ui-design-templates/, byte-for-byte, with only
     the data source bridged to the backend (see the BRIDGE marks below). The
     component's render() applies layouts.ui-template — no @extends here
     (Livewire full-page: exactly one root element in the content section). --}}

@section('title', 'Class Replacements')

@section('page-styles')

        /* ─── Class Replacements list: TABLE on desktop (shared .timetable/.data-table styles),
               CARDS on mobile only (user revision 2026-10-01) ─── */
        .upcoming-list {
            display: none; /* cards are mobile-only */
        }
        #upcomingBody tr {
            cursor: pointer;
        }
        #upcomingBody tr:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: -2px;
        }
        .col-status { white-space: nowrap; }
        .upcoming-card-wk {
            font-size: 11px;
            font-weight: 600;
            color: var(--color-on-surface-variant);
            background: var(--color-surface-variant);
            border-radius: var(--radius-sm);
            padding: 2px 8px;
            white-space: nowrap;
        }

        /* ─── Toolbar: stick Today to the right of the week nav
               (page-scoped override of the shared space-between distribution) ─── */
        .toolbar { justify-content: flex-start; }

        .upcoming-card {
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-lg);
            padding: 14px 16px;
            cursor: pointer;
            transition: background 0.15s, box-shadow 0.15s;
        }
        /* hover elevation mirrors theme.css .request-card / cell-hover pattern */
        .upcoming-card:hover {
            background: var(--color-surface-variant);
            box-shadow: var(--shadow-sm);
        }
        .upcoming-card:active {
            transform: scale(0.99);
        }
        .upcoming-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 8px;
        }
        .upcoming-card-code {
            font-size: 14px;
            font-weight: 700;
            color: var(--color-on-surface);
        }
        /* 2026-11-09 (grill Q13=A): the two slots are now full 4-line blocks
           (date / countdown / time+duration / venue), so the card STACKS them
           instead of squeezing them side by side with an arrow. */
        .upcoming-card-slots {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 8px;
            font-size: 12px;
            color: var(--color-on-surface);
        }
        .slot-label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.4px;
            text-transform: uppercase;
            color: var(--color-on-surface-variant);
            margin-bottom: 2px;
        }
        .upcoming-card-reason {
            font-size: 12px;
            margin-bottom: 6px;
        }
        .upcoming-card-lect {
            font-size: 12px;
            color: var(--color-on-surface-variant);
        }
        .upcoming-card.past {
            border: 1px solid var(--color-outline);
            color: var(--color-on-surface-variant);
        }

        /* ─── Mobile (≤768px): cards replace the table; toolbar stacks via theme.css shared rules ─── */
        @media (max-width: 768px) {
            #upcomingGridWrapper {
                display: none !important;
            }
            .upcoming-list {
                display: grid;
                grid-template-columns: 1fr;
                gap: 8px;
                margin-top: 14px;
            }
            /* whole card is the touch target — keep it ≥44px */
            .upcoming-card {
                min-height: 44px;
            }
        }
@endsection

{{-- Real-data bridge (SDD wire-backend-into-refactored-ui): the mock
     template runs UNCHANGED below except for the documented BRIDGE PATCH
     marks. PAGE_DATA.replacementHistory carries the mock's exact row shape,
     derived from the real DB (my cohort, approved + pending — FR 1.4). --}}
<div class="lw-page">
    @include('partials.page-data-script', ['payload' => $payload])

        <!-- ─── Page Header ─── -->
        @include('partials.ui-page-header', ['title' => 'Class Replacements', 'descriptionHtml' => 'Conflicted <span class="info-keyword">CLASSES</span> and where each one\'s replacement stands — read-only, for your cohort.', 'chips' => $cohortLabel !== null ? [['label' => $cohortLabel]] : []]) {{-- BRIDGE PATCH: cohort chip is per-user data --}}

        <!-- ─── Toolbar: week navigation + the staff's COMPLETED toggle ───
             2026-11-09 (user): this is My Replacements' control, same words, same
             default (ON = completed requests are excluded from the list). A
             replacement counts as COMPLETED once its requested DATE has passed,
             which is the derived status the badge above it prints. ─── -->
        <div class="toolbar">
            @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeekFilter()', 'nextOnclick' => 'nextWeekFilter()', 'selectId' => 'weekFilter', /* 2026-11-10 (user): NO Today button on this page */ 'selectOnclick' => "weekFilterChanged({onRebuild: renderUpcoming})", 'showTodayBtn' => false])
            <label class="toggle-wrapper" id="completedToggle" data-tip="ON = completed requests are excluded from the list">
                <input type="checkbox" id="hideCompleted" checked>
                <span class="toggle-track"><span class="toggle-thumb"></span></span>
                <span class="toggle-label">Exclude <span class="info-keyword">COMPLETED</span> Status</span>
            </label>
        </div>
        <div class="filter-chips" id="filterChips"></div>

        <!-- ─── Replacement list — desktop: shared table (JS renders rows); mobile: cards ─── -->
        @include('partials.ui-grid-table', ['wrapperId' => 'upcomingGridWrapper', 'tableId' => 'upcomingTable', 'headId' => 'upcomingHead', 'bodyId' => 'upcomingBody', 'tableClass' => 'timetable data-table'])
        <div id="upcomingList" class="upcoming-list"></div>

        <!-- ─── Empty state (shown by JS when the filtered list is empty; above the summary) ─── -->
        @include('partials.ui-empty-state', ['title' => 'No conflicted classes to show', 'text' => 'Nothing matches this view for '.($cohortLabel ?? 'your cohort').'. Try another week, or switch Exclude COMPLETED Status off.']) {{-- BRIDGE PATCH: cohort is per-user data --}}

        <!-- ─── Summary strip: Awaiting Arrangement / Pending / Approved / Completed / Class Hours Affected — hidden when the view is empty ─── -->
        @include('partials.ui-summary-bar', [ 'cards' => [
            /* 2026-11-09 (user): the hours card sits SECOND, right of Awaiting
               Arrangement — the two numbers a student reads first are "how many
               classes still need arranging" and "how much teaching that is". */
            ['class' => 'card-pending', 'valueId' => 'sumNone', 'label' => 'Classes Awaiting Arrangement', 'description' => 'Classes still <span class="warn-keyword">waiting for a replacement</span> — cancelled, on a holiday, and with no request sent yet.'],
            ['class' => 'card-hours', 'valueId' => 'sumHours', 'label' => 'Hours Awaiting Arrangement', 'description' => 'Hours of those classes. Classes already arranged <span class="info-keyword">are not counted</span>.'],
            ['class' => 'card-pending', 'valueId' => 'sumPending', 'label' => 'Pending', 'description' => 'Your requests <span class="warn-keyword">waiting for PL approval</span>.'],
            ['class' => 'card-replacement', 'valueId' => 'sumApproved', 'label' => 'Approved', 'description' => 'Replacements the PL <span class="info-keyword">approved</span> — attend the requested slot.'],
            ['class' => 'card-hours', 'valueId' => 'sumCompleted', 'label' => 'Completed', 'description' => 'Replacements that <span class="info-keyword">already took place</span>. Switch Exclude COMPLETED Status off to see them.'],
        ] ])

        <!-- ─── Class detail modal (shared shell for DetailModal.render) ─── -->
        @section('modal-footer')
            {{-- 2026-11-11 (SDD student-modal-staff-parity): the footer is STATIC and
                 laid out by `.modal-footer`'s own space-between — Close LEFT, the jump
                 RIGHT. The old page INJECTED the jump at the front of the rail, so the
                 two buttons swapped sides between rows with and without a request. --}}
            <button class="btn-outline" onclick="closeModal()">Close</button>
            {{-- the staff pop-up's own glyph and primary skin (one action, one icon) --}}
            <button class="btn-action" id="stJumpTimetableBtn" style="display:none" onclick="jumpToTimetable()">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <span>Go to My Timetable</span>
            </button>
        @endsection
        @include('partials.ui-class-detail-modal')

</div>

@section('page-scripts')
        /* WRAP: the frozen script's top-level boot reads MockData, which is
           re-pointed at the backend below and parsed after @yield('content')
           — so the whole script runs on DOMContentLoaded (the my-timetable
           conversion pattern); the inline-handler globals are exported to
           window at the bottom. Everything else is the frozen UI design,
           byte-for-byte except the BRIDGE PATCH marks. */
        document.addEventListener('DOMContentLoaded', function () {
        /* ── Real-data bridge — swap the mock's data source for the backend. ── */
        MockData.semester = window.PAGE_DATA.semester;
        MockData.holidays = window.PAGE_DATA.holidays;
        MockData.currentUser = window.PAGE_DATA.currentUser;
        MockData.replacementHistory = window.PAGE_DATA.replacementHistory;
        MockData.cohortTimetable = window.PAGE_DATA.cohortTimetable;
        MockData.mockNow = null; /* real clock — the demo anchor is mock-era only */

        // ─── Top-level functions (inline handler strings run in global scope) ───

        // ─── Persisted view state (house pattern, cf. my-request-history saveFilters/restoreFilters) ───
        const VIEW_KEY = 'replacement-history-view';
            /* legacy key from the pre-rename page (2026-10-06) */
            const LEGACY_VIEW_KEY = 'upcoming-replacements-view';
        function saveView() {
            try {
                localStorage.setItem(VIEW_KEY, JSON.stringify({
                    week: document.getElementById('weekFilter').value,
                    excludeCompleted: document.getElementById('hideCompleted').checked,
                }));
            } catch (e) { /* storage unavailable — non-fatal */ }
        }

        /* ── The two slot columns print the STAFF block (2026-11-09):
                 Tue, 3 Nov 2026 (Week 7)
                 Overdue (3 Working Days Ago)      ← the countdown line
                 2:00 PM ~ 4:00 PM (2 hours)
                 [house icon] B009
              Urgency is the shared working-day law (the WorkingHours calendar,
              holidays included — `workingDaysDiff`), never calendar days.

              Which column carries the countdown (user, grill Q3=A — the staff
              page's rule): the ORIGINAL slot always does, because the class is
              the thing that still needs a new slot; the REQUESTED slot only while
              the request is PENDING, because once it is decided a past slot would
              read "Overdue" for a replacement that has already taken place. */
        function slotBlock(slot, withCountdown) {
            if (!slot) return '<span class="cell-dash">\u2014</span>';
            const weekTag = slot.week ? ' (Week ' + slot.week + ')' : '';
            let countdown = '';
            if (withCountdown) {
                const days = workingDaysDiff(slot.date);
                countdown = '<br><span class="' + urgencyBandClass(days) + '">' + daysLeftLabel(days) + '</span>';
            }
            return '<div class="cell-class-block">' +
                '<span class="class-day-date">' + dayAbbr(slot.day) + ', ' + formatDate(slot.date) + weekTag + '</span>' + countdown +
                /* 2026-11-10 (user, "Table Columns"): no duration text in a slot
                   column — the time range says it plainly. */
                '<br><span class="class-time">' + to12h(slot.start) + ' ~ ' + to12h(slot.end) + '</span>' +
                '<br>' + venueLine(slot.venue) + '</div>';
        }

        /* The staff two-line COURSE cell: code on its own line, then the full
           name with the class type in small type. No hover text (user ruling). */
        function courseCell(r) {
            return '<span class="cell-code">' + r.code + '</span>' +
                '<span class="cell-name">' + r.name + ' <span class="cell-type-label">(' + r.type + ')</span></span>';
        }

        /* CONFLICT REASON — the CLASS's own cause. A calendar holiday keeps its
           real name; every other exception reads the bare "Class Cancelled"
           (user ruling, grill Q1=B: a student never sees the staff member's leave
           type). No cause at all prints an em dash rather than a guess. */
        function reasonCell(r) {
            if (!r.conflictKind) return '<span class="cell-dash">\u2014</span>';
            /* 2026-11-11 (user): the badge names ONLY the kind; the detail rides
               the hover. A holiday's name is public, so a student gets it
               ("Christmas Eve"). A cancellation's reason is the lecturer's own
               business — the student payload does not carry it by design, so that
               badge has no tip at all. */
            const isHoliday = r.conflictKind === 'Public Holiday';
            const text = isHoliday ? 'Public Holiday' : 'Class Cancelled';
            const cls = isHoliday ? 'badge-holiday' : 'badge-reason';
            const detail = isHoliday ? (r.holidayName || 'Semester holiday') : '';
            return '<span class="badge ' + cls + '"'
                + (detail ? ' data-tip="' + String(detail).replace(/"/g, '&quot;') + '"' : '')
                + '>' + text + '</span>';
        }

        /* REQUEST STATUS — the staff badge (class + server label). `none` is the
           unarranged state and wears the staff no-request shape (outline), never
           `status-none` alone: the student page has no request to name. */
        function statusCell(r) {
            const tip = String(r.statusTip || '').replace(/"/g, '&quot;');
            const cls = r.status === 'none' ? 'badge badge-outline status-none' : 'badge status-' + r.status;
            return '<span class="' + cls + '" data-tip="' + tip + '">' + r.statusLabel + '</span>';
        }

        /* "Requested 12:25 PM, 4 Nov 2026" — the modal's one line about the ask */
        function requestedAtLine(r) {
            return r.requestedAt ? 'Requested ' + r.requestedAt : '';
        }

        /* ── R-2 (round-3): sortable columns — shared makeSortableHeader house
           pattern (cf. request-approval / my-request-history). Null field =
           the natural chronological order below; sort state is session-only
           (the VIEW_KEY persists week/past filters, not the sort). ── */
        /* 2026-11-09 (user brief "Student Side UI Update", six rulings on this
           list): the student table now mirrors the staff one —
             COURSE · LECTURER · ORIGINAL SLOT · CONFLICT REASON · REQUESTED SLOT ·
             REQUEST STATUS
           The row-number `#` column is gone (it lied as soon as you sorted), the
           `New Slot` column is the `Requested Slot`, and the reason sits between
           the two slots the way the staff table orders them. The COURSE column's
           hover text is explained ONCE, on its header (the cell already prints
           code, name and type — a per-cell tip only repeated it). */
        const columns = [
            { label: 'Course', sortable: true, field: 'course', tip: UiLabels.courseTipSubject },
            { label: 'Lecturer', sortable: true, field: 'lecturer', tip: 'Lecturer handling the replacement' },
            { label: 'Original Slot', sortable: true, field: 'originalSlot', tip: 'Week, day and time of the class being replaced' },
            { label: 'Conflict Reason', sortable: true, field: 'reason', tip: 'Why this class cannot run as timetabled' },
            { label: 'Requested Slot', sortable: true, field: 'requestedSlot', tip: 'The new slot proposed for this class' },
            { label: 'Request Status', sortable: true, field: 'status', tip: 'Pending first, then approved, then completed' },
        ];
        let sortState = { field: null, dir: 'asc' };

        const sortKeys = {
            course: r => r.code + ' ' + r.name,
            lecturer: r => r.lecturer || '',
            originalSlot: r => [r.orig.week, r.orig.date, r.orig.start],
            reason: r => (r.conflictKind || ''),
            requestedSlot: r => (r.req ? [r.req.date, r.req.start] : null),   // unarranged rows sort last
            /* the status order, not the alphabet: pending → approved → completed,
               and the unarranged state LAST (design §2 — never `undefined`) */
            status: r => ({ pending: 0, approved: 1, completed: 2, none: 3 })[r.status],
        };

        function applySort(visible, all) {
            if (!sortState.field || !sortKeys[sortState.field]) {
                visible.sort(all
                    ? (a, b) => (a.week - b.week) || (a.di - b.di) || (a.start - b.start)
                    : (a, b) => (a.di - b.di) || (a.start - b.start));
                return;
            }
            const dir = sortState.dir === 'asc' ? 1 : -1;
            const key = sortKeys[sortState.field];
            visible.sort((a, b) => {
                const ka = key(a), kb = key(b);
                if (ka === null && kb === null) return 0;
                if (ka === null) return 1;   /* nulls stay last regardless of direction */
                if (kb === null) return -1;
                let c;
                if (Array.isArray(ka)) {
                    c = 0;
                    for (let i = 0; i < ka.length && c === 0; i++) {
                        c = (ka[i] < kb[i] ? -1 : ka[i] > kb[i] ? 1 : 0);
                    }
                } else {
                    c = (ka < kb ? -1 : ka > kb ? 1 : 0);
                }
                if (c !== 0) return c * dir;
                /* tiebreak: the natural chronological order */
                return (a.week - b.week) || (a.di - b.di) || (a.start - b.start);
            });
        }

        /* ── The Active Filters bar (2026-11-09): the student page had none. It
              names the two filters this page owns, in the same voice as the staff
              page's bar ("Original Slot Week: …", "Exclude COMPLETED
              Status"), and each chip removes its own filter. ── */
        function renderChips() {
            const container = document.getElementById('filterChips');
            if (!container) return;
            const chips = [];
            const sel = document.getElementById('weekFilter');
            /* 2026-11-11: an unselected box is NOT a week (weekFilterValue) and the
               label comes from the shared labeller, so it can never print blank */
            if (sel && weekFilterValue('weekFilter') !== 'all') {
                chips.push('<span class="filter-chip">Original Slot Week: ' + (weekFilterLabel('weekFilter') || weekFilterValue('weekFilter')) +
                    '<button class="filter-chip-remove" data-tip="Remove" data-remove="weekFilter">&times;</button></span>');
            }
            if (document.getElementById('hideCompleted').checked) {
                chips.push('<span class="filter-chip">Exclude COMPLETED Status' +
                    '<button class="filter-chip-remove" data-tip="Remove" data-remove="hideCompleted">&times;</button></span>');
            }
            /* 2026-11-10 (user): the staff pages' "Clear all" sits last in the bar,
               same markup, same tooltip. It only exists while a filter is active. */
            if (chips.length > 0) {
                chips.push('<button type="button" class="filter-chip filter-chip-clear-all" data-tip="Remove every active filter" onclick="clearAllFilters()">Clear all</button>');
            }
            container.innerHTML = chips.length > 0 ? '<span class="filter-chips-label">Active Filters:</span>' + chips.join('') : '';
        }

        /* Clear every filter this page owns and forget the saved view, so a reload
           cannot bring them back (the staff pages' law, 2026-11-09/2026-11-10).
           Exported on window at the bottom because this page's whole script runs
           inside a DOMContentLoaded closure (see the WRAP note above). */
        function clearAllFilters() {
            const sel = document.getElementById('weekFilter');
            if (sel && sel.value !== 'all') {
                sel.value = 'all';
                sel.dispatchEvent(new Event('change'));   // the week path rebuilds + re-renders
            }
            const hc = document.getElementById('hideCompleted');
            if (hc && hc.checked) {
                hc.checked = false;
            }
            try { localStorage.removeItem(VIEW_KEY); } catch (e) { /* storage unavailable — non-fatal */ }
            renderUpcoming();                              // ends with renderChips()
        }

        function renderUpcoming() {
            // AD-2: select values are 1-based strings; dataset weeks are 0-based.
            // 'all' = the All Original Slot Weeks option (shared helpers
            // are selectedIndex-based, so they stay NaN-safe).
            const all = weekFilterValue('weekFilter') === 'all';   /* '' is not a week */
            const w0 = all ? null : parseInt(document.getElementById('weekFilter').value, 10) - 1;
            /* 2026-11-09 (grill Q5=A): COMPLETED is the derived status, exactly as
               the badge prints it — an approved request whose requested DATE has
               passed. The filter and the badge therefore can never disagree. */
            const hideCompleted = document.getElementById('hideCompleted').checked;

            // AD-5: MockData is read-only — slice() before filtering.
            // 2026-11-09: a row is a CLASS OCCURRENCE, so the week filter reads the
            // occurrence's own week (r.week), and only a derived `completed` row is
            // hidden — an unarranged class is never "past" (R4).
            const visible = MockData.replacementHistory.slice()
                .filter(r => (all || r.week === w0) && !(hideCompleted && r.status === 'completed'));

            // AD-11: the five cards count the SAME visible set (R7/R8)
            document.getElementById('sumNone').textContent      = visible.filter(r => r.status === 'none').length;
            document.getElementById('sumPending').textContent   = visible.filter(r => r.status === 'pending').length;
            document.getElementById('sumApproved').textContent  = visible.filter(r => r.status === 'approved').length;
            document.getElementById('sumCompleted').textContent = visible.filter(r => r.status === 'completed').length;
            /* 2026-11-09 (user, supersedes R8): the hours card counts the teaching
               still WAITING — only the rows with no live request. An arranged class
               is no longer "awaiting", so its hours must not sit in this number. */
            document.getElementById('sumHours').textContent     = visible
                .filter(r => r.status === 'none')
                .reduce((s, r) => s + (r.orig.duration || 0), 0);

            applySort(visible, all);

            // ── Desktop table (shared .timetable/.data-table house pattern) ──
            const head = document.getElementById('upcomingHead');
            const body = document.getElementById('upcomingBody');
            head.innerHTML = '';
            const htr = document.createElement('tr');
            columns.forEach(function(col) {
                htr.appendChild(makeSortableHeader(col, sortState, function() {
                    renderUpcoming();
                }));
            });
            head.appendChild(htr);
            /* Six cells, the user's order. The requested slot's countdown rides
               only a PENDING request (slotBlock's second argument). */
            body.innerHTML = visible.map(r => {
                const pending = r.status === 'pending';
                /* 2026-11-11: the request id rides the row so a bell notice can
                   name it (`?notif-hl=r<id>`); the occurrence key stays data-id. */
                return '<tr role="button" tabindex="0" data-id="' + r.id + '"'
                    + (r.requestId ? ' data-request-id="' + r.requestId + '"' : '') + '>' +
                    '<td class="col-code">' + courseCell(r) + '</td>' +
                    '<td class="col-lecturer">' + r.lecturer + '</td>' +
                    '<td>' + slotBlock(r.orig, true) + '</td>' +
                    '<td class="col-reason">' + reasonCell(r) + '</td>' +
                    '<td>' + slotBlock(r.req, pending) + '</td>' +
                    '<td class="col-status">' + statusCell(r) + '</td>' +
                '</tr>';
            }).join('');
            document.getElementById('upcomingGridWrapper').style.display = visible.length === 0 ? 'none' : '';

            // ── Mobile cards: same facts, slots STACKED (2026-11-09 grill Q13=A) ──
            const list = document.getElementById('upcomingList');
            list.innerHTML = visible.map(r => {
                const pending = r.status === 'pending';
                return '<div class="upcoming-card' + (r.status === 'completed' ? ' past' : '') + '" role="button" tabindex="0" data-id="' + r.id + '"'
                    + (r.requestId ? ' data-request-id="' + r.requestId + '"' : '') + '>' +
                    '<div class="upcoming-card-head">' +
                        '<span class="upcoming-card-wk">' + (r.week + 1) + '</span>' +
                        '<span class="upcoming-card-code">' + r.code + ' \u00b7 ' + r.name + ' (' + r.type + ')</span>' +
                        statusCell(r) +
                    '</div>' +
                    '<div class="upcoming-card-slots">' +
                        '<div class="slot-block"><span class="slot-label">Original Slot</span>' + slotBlock(r.orig, true) + '</div>' +
                        '<div class="slot-block"><span class="slot-label">Requested Slot</span>' + slotBlock(r.req, pending) + '</div>' +
                    '</div>' +
                    '<div class="upcoming-card-reason"><span class="slot-label">Conflict Reason</span>' + reasonCell(r) + '</div>' +
                    '<div class="upcoming-card-lect">' + r.lecturer + '</div>' +
                '</div>';
            }).join('');

            const emptyState = document.getElementById('emptyState');
            if (emptyState) emptyState.style.display = visible.length === 0 ? 'flex' : 'none';
            // Nothing to summarize when the view is empty — cf. my-request-history
            syncSummarySection(visible.length > 0);
            renderChips();
        }

        /* the opened row, for the footer's jump (the button is static now) */
        let currentReplacement = null;

        function openReplacementModal(id) {
            /* the row id is an OCCURRENCE key ("<sessionId>:<week>") — never Number() it */
            const r = MockData.replacementHistory.find(x => String(x.id) === String(id));
            if (!r) return;
            currentReplacement = r;
            const pending = r.status === 'pending';
            const sessionId = String(r.id).split(':')[0];

            /* 2026-11-11 (SDD student-modal-staff-parity): this pop-up IS the staff
               Replacement Details pop-up — same title, same subtitle shape, same
               strip, same tab order, same row builders (DetailModal.row + ModalFacts),
               so the two pages cannot drift apart in wording or order. */
            const countdown = (iso) => {
                const d = workingDaysDiff(iso);
                const span = '<span class="' + urgencyBandClass(d) + '">' + daysLeftLabel(d) + '</span>';
                /* the staff law: brackets normally; an em dash before an Overdue
                   label, whose own brackets would otherwise double up */
                return d < 0 ? ' \u2014 ' + span : ' (' + span + ')';
            };

            /* one slot tab — Class ID / Request ID lead, exactly as on the staff page */
            const slotTab = (heading, slot, idLabel, idValue, withCountdown) => DetailModal.section(heading,
                DetailModal.row(idLabel, '#' + idValue, { strong: true }) +
                DetailModal.row('Week', slot.week ? 'Week ' + slot.week : null) +
                DetailModal.row('Day & Date', ModalFacts.dayDate(slot.day, formatDate(slot.date) + (withCountdown ? countdown(slot.date) : '')), { strong: true }) +
                DetailModal.row('Time & Duration', ModalFacts.timeDuration(to12h(slot.start), to12h(slot.end), slot.duration), { strong: true }) +
                DetailModal.row('Venue', ModalFacts.venue(slot.venue))
            );

            const tabs = [];
            /* the make-up schedule is what the row is about — first, and therefore
               the tab the pop-up opens on (DetailModal activates index 0) */
            if (r.req) {
                tabs.push({
                    key: 'requested',
                    label: 'Requested Slot Schedule',
                    html: slotTab('Requested Slot Schedule', r.req, 'Request ID', r.requestId, pending),
                });
            }
            tabs.push({
                key: 'original',
                label: 'Original Slot Schedule',
                html: slotTab('Original Slot Schedule', r.orig, 'Class ID', sessionId, true),
            });
            /* the Course tab is GONE (2026-11-11): the code/name/type live in the
               subtitle, the lecturer here with the two facts the staff strip prints */
            tabs.push({
                key: 'class-info',
                label: 'Class Info',
                html: DetailModal.section('Class Info',
                    DetailModal.row('Lecturer', r.lecturer) +
                    DetailModal.row('Cohorts', (r.cohorts || []).join(', ') || '\u2014') +
                    DetailModal.row('Students', r.totalStudents > 0 ? String(r.totalStudents) : '\u2014')
                ),
            });
            tabs.push({
                key: 'status',
                label: 'Request Status' + (r.statusShort ? ' (' + r.statusShort + ')' : ''),
                html: DetailModal.section('Status',
                    '<div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">' + statusCell(r) + '</span></div>' +
                    (r.status === 'completed' ? DetailModal.row('Status Note', 'This replacement has already taken place.') : '') +
                    (requestedAtLine(r) ? DetailModal.row('Request', requestedAtLine(r)) : '')
                ),
            });

            /* the Conflict Reason TAB is gone too (2026-11-11): the conflict is the
               strip above the tabs, printed IN FULL (there is room here, so nobody
               has to hover) — the staff pop-up's own ruling. A cancellation still
               prints the kind alone: a student is never sent the leave reason. */
            const isHoliday = r.conflictKind === 'Public Holiday';
            const conflictFull = !r.conflictKind
                ? '\u2014'
                : (isHoliday ? 'Public Holiday \u2014 ' + (r.holidayName || 'Semester holiday') : 'Class Cancelled');

            DetailModal.render({
                modalId: 'classModal',
                title: 'Replacement Details',
                subtitle: r.code + ' \u2014 ' + r.name + ' (' + UiLabels.classType(r.type, 'long') + ')',
                factsHtml: '<span class="df-item df-item--full"><span class="df-label">Conflict</span>' +
                    (r.conflictKind
                        ? '<span class="badge ' + (isHoliday ? 'badge-holiday' : 'badge-reason') + '">' + conflictFull + '</span>'
                        : '<span class="detail-value detail-value--muted">' + conflictFull + '</span>') +
                    '</span>',
                tabs: tabs,
            });

            /* no proposed slot → open on Class Info (2026-11-11, user decision 4) */
            if (!r.req) DetailModal.selectTab('class-info');

            /* the jump is STATIC and only becomes visible when the timetable really
               carries the new slot — approved or already done (the staff rule); a
               pending request has nothing to flash yet */
            const jumpBtn = document.getElementById('stJumpTimetableBtn');
            if (jumpBtn) {
                const landed = r.status === 'approved' || r.status === 'completed';
                jumpBtn.style.display = (landed && r.requestId && r.req) ? 'inline-flex' : 'none';
            }
        }

        /* the footer's jump — the row it belongs to is the one just opened */
        function jumpToTimetable() {
            if (!currentReplacement || !currentReplacement.requestId) return;
            goToReplacementInTimetable(
                currentReplacement.requestId,
                currentReplacement.req ? currentReplacement.req.week : 0,
                '/student-my-timetable-ui'
            );
        }
        /* inline onclick handlers only reach a window property */
        window.jumpToTimetable = jumpToTimetable;

        // ─── Modal close wiring — closeModal() is now the shared global in
        // ─── ui-common.js (§10.0 rule 7 promote); page keeps only the
        // ─── overlay/Escape wiring (cf. student-my-timetable).
        function closeModalOutside(e) {
            closeOnOverlayClick(e, closeModal);
        }

        closeOnEsc(closeModal);

        /* (the mock's own DOMContentLoaded wrapper is unwrapped — this whole
            script already runs inside the one above) */
        {
            const chipEl = document.getElementById('semesterChip');
            if (chipEl) chipEl.textContent = MockData.semester.chipText;

            /* includeAll: the All Weeks option renderUpcoming() filters on.
               Single writer = the shared helper (a page-side IIFE prepend used
               to double it up after includeAll landed 2026-10-06, and the old
               hand-inserted option also vanished on breakpoint re-populate).

               SDD student-side-health-check S7 (2026-11-08, user's option A):
               this page opens on **All Weeks**. It used to open on the CURRENT
               week, and since the filter hides every other week, a student whose
               only replacement sat in a later week saw an empty table plus
               0 / 0 / 0 cards — measured on both demo students (one approved in
               week 8, one pending in week 9). The Today button + the week arrows
               still narrow it to one week on demand.

               SDD student-class-replacements R3 (2026-11-09): a row is a CLASS
               OCCURRENCE now, so `r.week` is the occurrence's OWN week and the
               filter keys on it — that is why the All-option reads "All Original
               Class Weeks". */
            populateWeekSelect('weekFilter', { selected: 'all', includeAll: true, allLabel: 'All Original Slot Weeks' });

            // Restore persisted view (a first visit opens on All Weeks)
            (function() {
                let saved = {};
                try {
                    saved = JSON.parse(localStorage.getItem(VIEW_KEY) || '{}');
                } catch (e) { saved = {}; }
                /* legacy key from the pre-rename page (2026-10-06) — migrate once */
                if (Object.keys(saved).length === 0) {
                    try {
                        const legacy = JSON.parse(localStorage.getItem(LEGACY_VIEW_KEY) || 'null');
                        if (legacy) { saved = legacy; localStorage.setItem(VIEW_KEY, JSON.stringify(legacy)); }
                        localStorage.removeItem(LEGACY_VIEW_KEY);
                    } catch (e2) { /* ignore */ }
                }
                /* a saved week still wins over the All Weeks default — it is a
                   deliberate choice; the default only applies to a first visit */
                const sel = document.getElementById('weekFilter');
                if (saved.week && (saved.week === 'all' || sel.querySelector('option[value="' + saved.week + '"]'))) {
                    sel.value = saved.week;
                }
                /* the staff control replaces this page's old "Show past": the two
                   are inverses, so a saved showPast:true means "do not exclude". */
                if (saved.excludeCompleted === false || saved.showPast === true) {
                    document.getElementById('hideCompleted').checked = false;
                }
            })();
            updateWeekArrowState();

            document.getElementById('hideCompleted').addEventListener('change', function() {
                saveView();
                renderUpcoming();
            });

            /* Each Active-Filters chip removes its own filter. ONE delegated
               listener owns it (2026-11-10 bug fix): the old inline `onclick=`
               called `saveView()`, which lives inside this closure — the click
               threw, so the toggle turned off and the chip stayed. */
            const chipBar = document.getElementById('filterChips');
            if (chipBar) {
                chipBar.addEventListener('click', function(e) {
                    const btn = e.target.closest('.filter-chip-remove');
                    if (!btn) return;
                    e.preventDefault();
                    const which = btn.getAttribute('data-remove');
                    if (which === 'weekFilter') {
                        const sel = document.getElementById('weekFilter');
                        sel.value = 'all';
                        sel.dispatchEvent(new Event('change'));   // the week path re-renders + saves
                        return;
                    }
                    if (which === 'hideCompleted') {
                        document.getElementById('hideCompleted').checked = false;
                        saveView();
                        renderUpcoming();                        // ends with renderChips()
                    }
                });
            }
            document.getElementById('weekFilter').addEventListener('change', saveView);

            /* 2026-11-10 (user): this page has NO Today button — the week-nav
               include is told so (showTodayBtn => false) and the old AD-10
               handler went with the button. Do not re-add it here. */

            // Card/row events — delegated so listeners survive re-renders
            // (cards = mobile container, #upcomingBody rows = desktop table)
            const openFromEl = function(e, selector) {
                const el = e.target.closest(selector);
                if (el) openReplacementModal(el.dataset.id);   // the occurrence key stays a STRING
            };
            ['#upcomingList', '#upcomingBody'].forEach(function(sel) {
                const root = document.querySelector(sel);
                root.addEventListener('click', function(e) { openFromEl(e, sel + ' [data-id]'); });
                root.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter' && e.key !== ' ') return;
                    const el = e.target.closest(sel + ' [data-id]');
                    if (!el) return;
                    if (e.key === ' ') e.preventDefault(); // avoid page scroll
                    openReplacementModal(el.dataset.id);
                });
            });

            /* ─── Arrival from a bell notice (2026-11-11, user) ───
               The student bell lands HERE now, so the row the notice is about
               must be reachable. Two anchors ride the URL:
                 r<request_id>            → the row whose request that is
                 o<session_id>+notif-w    → the class occurrence (row id is
                                            `<sessionId>:<week>`, the week 1-based)
               A row hidden by the saved week filter or by Exclude COMPLETED Status
               is REVEALED first — the staff page's jumpToMyRow does the same — so
               the shared highlighter (ui-common, 150 ms) always has something to
               flash. Read SYNCHRONOUSLY, and read `notif-w` BEFORE anything strips
               it (the F1 lesson from applyNotifHighlight). */
            (function revealNotifRow() {
                const params = new URLSearchParams(location.search);
                const raw = params.get('notif-hl');
                if (!raw) return;
                const occWeek = params.get('notif-w');
                const rows = MockData.replacementHistory || [];
                let row = null;
                if (raw.charAt(0) === 'r') {
                    const rid = String(raw.slice(1));
                    row = rows.find(x => String(x.requestId) === rid) || null;
                } else if (raw.charAt(0) === 'o') {
                    const sid = String(raw.slice(1));
                    row = rows.find(x => String(x.id) === sid + ':' + occWeek) ||
                        (occWeek ? null : rows.find(x => String(x.id).indexOf(sid + ':') === 0)) || null;
                }
                if (!row) return;                    /* the shared arm skips silently */
                let changed = false;
                const sel = document.getElementById('weekFilter');
                if (sel && weekFilterValue('weekFilter') !== 'all' && Number(sel.value) !== row.week + 1) {
                    sel.value = String(row.week + 1);
                    changed = true;
                }
                const hc = document.getElementById('hideCompleted');
                if (hc && hc.checked && row.status === 'completed') {
                    hc.checked = false;
                    changed = true;
                }
                if (changed) { saveView(); renderUpcoming(); }
                /* desktop rows are pulsed by the shared highlighter; on the phone
                   layout the table is hidden, so pulse the CARD on the same beat */
                setTimeout(function () {
                    const tr = document.querySelector('tr[data-id="' + row.id + '"]');
                    if (tr && tr.offsetParent) return;
                    const card = document.querySelector('#upcomingList .upcoming-card[data-id="' + row.id + '"]');
                    if (!card) return;
                    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    flashRowPulse(card);
                }, 650);
            })();

            renderUpcoming();
        }

        /* inline-handler globals resolve via window.
           2026-11-09 (student-class-replacements): the shared modal's overlay calls
           `closeModalOutside(event)` from an inline attribute — without this export
           every overlay click threw "closeModalOutside is not defined" (pre-existing
           on this page; surfaced when the pop-up's new jump button was clicked). */
        window.renderUpcoming = renderUpcoming;
        window.closeModalOutside = closeModalOutside;
        window.clearAllFilters = clearAllFilters;      /* 2026-11-10: the Clear all chip */
        });
@endsection
