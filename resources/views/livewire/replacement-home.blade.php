{{-- My Replacements — the merged lecturer page (SDD replacement-merge-one-table).
     One table, one row per class occurrence; the row's status is the newest
     request made for it. The toolbar, the pop-up, the confirm overlay and the
     phone cards are the app's existing pieces (M10), moved here — never a
     second version. --}}

@section('title', 'My Replacements — Class Replacement System')

@section('page-styles')

        /* ───── Seven columns (M4, design §6) ───── */
        /* Fixed layout with a share for EVERY column. `.col-code` used to be
           `width: auto`, which made it the only column without a definite
           width — so it absorbed every spare pixel (704 px at 1920 px wide)
           while the multi-line cells stayed pinned at their hints. Percentages
           keep the grid honest at every viewport: content wraps inside its own
           column instead of taking width from its neighbours. */
        #timetable.data-table { table-layout: fixed; width: 100%; }
        /* 2026-11-04 (user): the code column gives up 40% of its share and the
           reason column takes 30% more — the holiday badge then fits on one
           line from ~1500 px up. Whole numbers, still summing to 100%. */
        .col-code { width: 11%; }
        .col-cohort { width: 10%; }
        .col-original { width: 18%; white-space: normal; }
        /* `.timetable td` is nowrap (theme.css) and outranks a bare class — the
           two badge columns opt out with an id + class, so a long holiday name
           wraps INSIDE its column instead of overflowing into the next one.
           The id also has to reach the <th>: under `table-layout: fixed` the
           header row is what decides the column widths. */
        #timetable .col-reason { width: 22%; vertical-align: middle; white-space: normal; }
        .col-requested { width: 16%; white-space: normal; }
        #timetable .col-status { width: 17%; white-space: normal; }
        .col-action { width: 6%; text-align: center; }

        /* ───── 2026-11-04 (user): nothing may paint outside its column ─────
           `.timetable td` is `white-space: nowrap` (theme.css) and only the four
           multi-line columns opt out. The two REMAINING text columns inherited
           nowrap, so under `table-layout: fixed` their content was wider than the
           box and painted straight over the next column — measured 68 px of
           overflow in COURSE CODE & NAME at 1280 px wide, 56 px at 1440, and the
           header label itself by 19 px. Both now wrap inside their own column
           (and long unbroken tokens break rather than push), and the header row
           wraps with them. Widths, order and the percentage shares are untouched. */
        #timetable .col-code,
        #timetable .col-cohort,
        #timetable thead th {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .grid-scroll .timetable { min-width: 1280px; }

        /* ───── Urgency (inline text inside the Original Class cell) ───── */
        /* keep the countdown on ONE line — with the columns now wrapping, a
           narrow Original Class column used to break it as "5 days / left" */
        .urgency-high { color: var(--color-error); font-weight: 700; white-space: nowrap; }
        .urgency-mid { color: var(--color-warning); font-weight: 600; white-space: nowrap; }
        .urgency-low { color: var(--color-on-surface-variant); font-weight: 500; white-space: nowrap; }

        /* ───── Just-cancelled Chip (ledger feature — its render block is
           switched off below until the TTL prompt lands; the CSS stays) ───── */
        .just-cancelled-chip {
            display: inline-block;
            background: var(--color-error-container);
            color: var(--color-on-error-container);
            padding: 2px 8px;
            border-radius: var(--radius-sm);
            font-size: 10px;
            font-weight: 600;
            margin-left: 6px;
            vertical-align: middle;
            white-space: nowrap;
        }
        .just-cancelled-undo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            margin-left: 4px;
            padding: 0;
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-sm);
            background: transparent;
            color: var(--color-on-surface-variant);
            cursor: pointer;
            vertical-align: middle;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .just-cancelled-undo:hover {
            background: var(--color-surface-variant);
            color: var(--color-on-surface);
        }

        /* ───── The five card value colours (M9, page-local, tokens only) ───── */
        .summary-card.card-conflict .summary-value { color: var(--color-error); }
        .summary-card.card-duration .summary-value { color: var(--color-primary); }
        .summary-card.card-pending .summary-value { color: var(--color-warning); }
        .summary-card.card-approved .summary-value { color: var(--color-success); }
        .summary-card.card-rejected .summary-value { color: var(--color-error); }

        /* ───── M15: the toolbar count yields below 1500 px (the pagination bar
           still reports it). Hidden, never removed. ───── */
        @media (max-width: 1499px) {
            #resultCount { display: none; }
        }

        /* ───── Responsive card view ───── */
        .replacement-card {
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md);
            padding: 14px 16px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: background 0.15s, box-shadow 0.15s;
        }
        .replacement-card:hover {
            background: var(--color-surface-variant);
            box-shadow: var(--shadow-sm);
        }
        .replacement-card:active {
            transform: scale(0.99);
        }
        .rc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        .rc-code {
            font-size: 14px;
            font-weight: 700;
            color: var(--color-on-surface);
        }
        .rc-body {
            font-size: 12px;
            color: var(--color-on-surface-variant);
            line-height: 1.6;
        }
        .rc-body strong {
            color: var(--color-on-surface);
            font-weight: 600;
        }
        .rc-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid var(--color-outline);
            font-size: 11px;
            color: var(--color-on-surface-variant);
        }
        .rc-footer .badge {
            font-size: 11px;
            padding: 3px 8px;
        }

        @media (max-width: 768px) {
            .grid-wrapper, .pagination-bar { display: none !important; }
            .card-view { display: block; }
            #kbShortcutsBtn { display: none !important; }
        }

@endsection

<div class="lw-page">
    @include('partials.page-data-script', ['payload' => $payload])

        <!-- ─── Page Header ─── -->
        @include('partials.ui-page-header', ['title' => 'My Replacements', 'descriptionHtml' =>
            'Every <span class="info-keyword">class</span> that needs a replacement, and every <span class="info-keyword">request</span> you have made for it — in <span class="info-keyword">one list</span>.'])

        @include('partials.ui-guide-block', [
            'guideTitle' => 'How to use this page',
            'guideItems' => [
                '<strong>Sort</strong> — click a sortable column header to sort ascending/descending; the arrow shows which one is active',
                '<strong>Search</strong> — type to filter by course code, course name or cohort code',
                '<strong>Hover headers</strong> — hover a column name to see what it means',
                '<strong>Track progress</strong> — the Action icon tells you what you can do: rotate to arrange, ban to cancel a pending request, eye to view the details',
                '<strong>Week filter</strong> — use the week navigator to view conflicts for a specific week',
            ]
        ])

        <!-- ─── Toolbar (moved from My Requests: one filter bar, M17) ─── -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="search-wrapper">
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input class="search-input" id="searchInput" placeholder="Search course code, name or cohort..." data-tip="Search by course code, course name or cohort code">
                </div>
                <select class="filter-select" id="statusFilter" data-tip="Request Status" aria-label="Request Status">
                    <option value="all">All Request Status</option>
                    <option value="none">Need to Arrange</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="cancelled">Cancelled</option>
                    <option value="completed">Completed</option>
                </select>
                @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeekFilter()', 'prevTip' => 'Previous week', 'nextOnclick' => 'nextWeekFilter()', 'nextTip' => 'Next week', 'selectId' => 'weekFilter', 'selectOnclick' => 'weekFilterChanged(this.value)', 'selectTip' => 'Pick the Original Class week to display', 'showTodayBtn' => false])
                <label class="toggle-wrapper" id="completedToggle" data-tip="ON = completed requests are excluded from the list">
                    <input type="checkbox" id="hideCompleted" checked>
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span class="toggle-label">Exclude <span class="info-keyword">COMPLETED</span> Status</span>
                </label>
                <button class="btn-clear" id="resetFilters" data-tip="Reset all filters to DEFAULT">Reset Filters</button>
            </div>
            <div class="toolbar-right">
                <span class="result-count" id="resultCount" data-tip="Classes matching the current filters">Showing 0 of 0 classes</span>
            </div>
        </div>

        <div class="filter-chips" id="filterChips"></div>

        <!-- ─── Grid Wrapper ─── -->
        @include('partials.ui-grid-table', ['tableClass' => 'timetable data-table'])

        <!-- ─── Card View (mobile) ─── -->
        <div class="card-view" id="cardView"></div>

        <!-- ─── Pagination ─── -->
        <div class="pagination-bar" id="paginationBar">
            @include('partials.ui-rpp', ['id' => 'rppSelect', 'default' => 10, 'options' => [10, 25, 50, 'all']])
            <span class="pagination-info" id="paginationInfo">Showing 1-10 of 0</span>
            <div class="pagination-controls" id="paginationControls"></div>
        </div>

        <!-- ─── Empty State (above the summary; summary auto-hides when the view is empty) ─── -->
        @include('partials.ui-empty-state', ['title' => 'No classes currently need attention.', 'text' => 'Try adjusting your search or filter criteria.'])

        <!-- ─── Five summary cards (M9) — the values follow the filters ─── -->
        @include('partials.ui-summary-bar', [
            'cards' => [
                ['class' => 'card-conflict', 'valueId' => 'summaryNeeds', 'label' => 'Classes to Arrange',
                    'description' => 'Your classes that <span class="warn-keyword">still need a replacement arrangement</span>.'],
                ['class' => 'card-duration', 'valueId' => 'summaryHours', 'label' => 'Class Hours to Replace',
                    'description' => 'Total <span class="info-keyword">hours of class time</span> that still need to be rescheduled.'],
                ['class' => 'card-pending', 'valueId' => 'summaryPending', 'label' => 'Pending Requests',
                    'description' => 'Requests still <span class="warn-keyword">waiting for approval</span>.'],
                ['class' => 'card-approved', 'valueId' => 'summaryApproved', 'label' => 'Approved Requests',
                    'description' => 'Requests the PL has <span class="info-keyword">approved</span>.'],
                ['class' => 'card-rejected', 'valueId' => 'summaryRejected', 'label' => 'Rejected Requests',
                    'description' => 'Requests that were <span class="warn-keyword">declined</span>.'],
            ]
        ])

        <!-- Keyboard Shortcuts Modal (kept — the `?` key still works; copy updated) -->
        <div class="modal-overlay" id="keyboardModal">
            <div class="modal" style="max-width:420px">
                <div class="modal-header">
                    <h3 class="modal-title">Keyboard Shortcuts</h3>
                    <button class="modal-close" onclick="hideKeyboardShortcuts()" data-tip="Close">✕</button>
                </div>
                <div class="modal-body">
                    <div class="modal-field"><span class="modal-field-label">Focus search</span><span class="modal-field-value"><code style="padding:2px 6px;border:1px solid var(--color-outline);border-radius:var(--radius-xs);font-size:12px;background:var(--color-surface-variant)">/</code></span></div>
                    <div class="modal-field"><span class="modal-field-label">Clear filters</span><span class="modal-field-value"><code style="padding:2px 6px;border:1px solid var(--color-outline);border-radius:var(--radius-xs);font-size:12px;background:var(--color-surface-variant)">Esc</code></span></div>
                    <div class="modal-field"><span class="modal-field-label">Next page</span><span class="modal-field-value"><code style="padding:2px 6px;border:1px solid var(--color-outline);border-radius:var(--radius-xs);font-size:12px;background:var(--color-surface-variant)">→</code></span></div>
                    <div class="modal-field"><span class="modal-field-label">Previous page</span><span class="modal-field-value"><code style="padding:2px 6px;border:1px solid var(--color-outline);border-radius:var(--radius-xs);font-size:12px;background:var(--color-surface-variant)">←</code></span></div>
                    <div class="modal-field"><span class="modal-field-label">Open the details</span><span class="modal-field-value"><code style="padding:2px 6px;border:1px solid var(--color-outline);border-radius:var(--radius-xs);font-size:12px;background:var(--color-surface-variant)">Enter</code></span></div>
                    <div class="modal-field"><span class="modal-field-label">Show shortcuts</span><span class="modal-field-value"><code style="padding:2px 6px;border:1px solid var(--color-outline);border-radius:var(--radius-xs);font-size:12px;background:var(--color-surface-variant)">?</code></span></div>
                    <p class="hint-text" style="margin-top:12px;text-align:center">Shortcuts only work when no input field is focused.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn-close-modal" onclick="hideKeyboardShortcuts()">Close</button>
                </div>
            </div>
        </div>

        <!-- Quick View Modal (M7 — the ONE detail shell; #modalOverlay retires) -->
        <style>
            #quickViewModal .modal-footer {
                justify-content: space-between !important;
                width: 100% !important;
            }
        </style>
        <div class="modal-overlay" id="quickViewModal" onclick="if(event.target===this)hideQuickView()">
            <div class="modal" style="max-width:616px">
                <div class="modal-header">
                    <span class="modal-title" id="qvTitle">Replacement Details</span>
                    <button class="modal-close" onclick="hideQuickView()" data-tip="Close">✕</button>
                </div>
                <div class="modal-body" id="qvBody"></div>
                <div class="modal-footer">
                    <button class="btn-outline" onclick="hideQuickView()">Close</button>
                    <button class="btn-danger" id="cancelRequestBtn" style="display:none"
                            onclick="openCancelConfirm(currentRequestId, currentOccurrenceId)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>
                        Cancel Request
                    </button>
                    <button class="btn-action" id="qvArrangeBtn" onclick="qvArrange()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        <span id="qvArrangeLabel">Arrange Replacement</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Cancel Confirmation Modal (moved verbatim from My Requests — M10) -->
        <div class="modal-overlay" id="cancelConfirmOverlay" style="display:none">
            <div class="modal" style="max-width:635px">
                <div class="modal-header">
                    <h3 class="modal-title">Confirm Cancellation</h3>
                    <button class="modal-close" onclick="closeCancelConfirm()" data-tip="Close">✕</button>
                </div>
                <div class="modal-body" id="cancelConfirmBody">
                    <div class="modal-icon modal-icon--warning" aria-hidden="true">
                        {{-- warning face — the destructive intent reads before the words do --}}
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9" />
                            <path d="M15.18 16.32A4.49 4.49 0 0 0 12.02 15a4.49 4.49 0 0 0-3.2 1.32" />
                            <path d="M9.38 9.75h.01v.02h-.01z" fill="currentColor" />
                            <path d="M14.63 9.75h.01v.02h-.01z" fill="currentColor" />
                        </svg>
                    </div>
                    <p class="confirm-warning">Are you sure you want to <span class="warn-action">CANCEL</span> this Replacement Request?</p>
                    <p class="confirm-irreversible">THIS ACTION CANNOT BE UNDONE.</p>
                    <dl class="cancel-details" id="cancelConfirmDetails"></dl>
                </div>
                <div class="modal-footer">
                    <div class="modal-footer-left">
                        <button class="btn-outline" onclick="closeCancelConfirm()">No, Keep It</button>
                    </div>
                    <div class="modal-footer-right">
                        <button class="btn-danger" id="confirmCancelAction">Yes, Cancel Request</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

@section('page-scripts')
        /* Own-records scope: this page is the logged-in lecturer's personal
           conflict dashboard — only classes THEY teach are listed/counted.
           A FUNCTION (not a load-time snapshot) so rows the ClassCancellation
           ledger appends at runtime (always own, lecturer set by
           rowFromEvent) are picked up on every rebuild. */
        function myConflictedRows() {
            return MockData.conflictedClasses.filter(function (c) {
                return c.lecturer === MockData.currentUser.name;
            });
        }

        /* ─── The six-state vocabulary is SERVER-built (design §1.6) ─────────
           These three accessors are the ONLY readers of the payload's label /
           tip / short keys. They default to the no-request state, because one
           row source — a class cancelled through the timetable
           (ClassCancellation.applyLedger) — has no request block at all, and a
           missing key must never print `undefined` (S1 at one site). */
        function statusLabelOf(c) {
            return c.requestStatusLabel || 'Need to Arrange';
        }
        function statusShortOf(c) {
            return c.requestStatusShort || 'Need to Arrange';
        }
        function statusTipOf(c) {
            return c.requestStatusTip || 'No request has been made for this class yet \u2014 it still needs arranging.';
        }
        function statusDescOf(c) {
            return c.requestStatusDesc || 'No replacement has been proposed for this class yet.';
        }

        function isHandled(c) {
            return !!c.requestStatus && c.requestStatus !== 'rejected' && c.requestStatus !== 'cancelled';
        }

        /* the status sort key — explicit, so the order is not the alphabet */
        var STATUS_RANK = { rejected: 1, cancelled: 2, pending: 3, approved: 4, completed: 5 };
        function statusRank(c) {
            return c.requestStatus ? (STATUS_RANK[c.requestStatus] || 6) : 0;
        }

        /* ─── The conflict badge: two classes only (M10/§6) ─── */
        function badgeClass(reason) {
            return reason === 'Public Holiday' ? 'badge-holiday' : 'badge-reason';
        }

        /* The badge says WHICH kind of conflict, not just its colour (design
           authority: explore-brief row 4). A holiday names the day from the
           `holidays` row; every other reason reads "Class Cancelled". The
           prototype's literal fallback stays for the `public_holiday`
           exception that no calendar row backs. */
        function reasonText(c) {
            if (c.conflictReason === 'Public Holiday') {
                return 'Public Holiday \u2014 ' + (c.holidayName || 'Semester holiday');
            }
            return 'Class Cancelled \u2014 ' + c.conflictReason;
        }

        /* ─── Cells (design §3.3) ─── */
        function cohortsCell(c) {
            return c.cohorts && c.cohorts.length ? c.cohorts.join('<br>') : '<span class="cell-dash">\u2014</span>';
        }

        function reasonBadge(c) {
            return '<span class="badge ' + badgeClass(c.conflictReason) + '">' + reasonText(c) + '</span>';
        }

        function venueLine(code, name) {
            if (!code) return '';
            var label = name ? code + ' \u2014 ' + name : code;
            return '<span class="cell-sub"><svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6"/></svg>' + label + '</span>';
        }

        function durationWords(hours) {
            if (!hours) return '';
            if (hours === 1) return '1 hour';
            if (Number.isInteger(hours)) return hours + ' hours';
            return (hours * 60) + ' minutes';
        }

        function rangeHours(range) {
            if (!range) return 0;
            var parts = String(range).split(/\s*[\u2013\u2014-]\s*/);
            if (parts.length < 2) return 0;
            var a = parts[0].split(':'), b = parts[1].split(':');
            return (parseInt(b[0], 10) * 60 + parseInt(b[1], 10) - (parseInt(a[0], 10) * 60 + parseInt(a[1], 10))) / 60;
        }

        function formatClassBlock(c) {
            var handled = isHandled(c);
            var days = daysLeft(c.date);
            var daysText = handled ? '\u2014' : '<span class="' + urgencyClass(days) + '">' + daysLeftLabel(days) + '</span>';
            var wn = getWeekNumber(c.date);
            var weekTag = wn ? ' (Week ' + wn + ')' : '';
            return '<div class="cell-class-block">' +
                '<span class="class-day-date">' + dayAbbr(c.day) + ', ' + formatDate(c.date) + weekTag + '</span> ' + daysText +
                '<br><span class="class-time">' + to12h(c.timeStart) + ' to ' + to12h(c.timeEnd) + '</span> ' +
                '<span class="class-duration">(' + durationWords(c.duration) + ')</span>' +
                '<br>' + venueLine(c.venue, null) + '</div>';
        }

        function requestCell(c) {
            if (!c.replacementDate) return '<span class="cell-dash">\u2014</span>';
            var wn = c.replacementWeek;
            var weekTag = wn ? ' (Week ' + wn + ')' : '';
            return '<div class="cell-class-block">' +
                '<span class="class-day-date">' + dayAbbr(c.replacementDay) + ', ' + formatDate(c.replacementDate) + weekTag + '</span>' +
                '<br><span class="class-time">' + String(c.replacementTime || '').replace(' \u2013 ', ' to ') + '</span> ' +
                '<span class="class-duration">(' + durationWords(rangeHours(c.replacementTime)) + ')</span>' +
                '<br>' + venueLine(c.replacementVenue, null) + '</div>';
        }

        /* The badge is NOT a door (M6): a plain span, no anchor, no onclick. */
        function statusCell(c) {
            var key = c.requestStatus || 'none';
            var cls = c.requestStatus ? 'status-' + c.requestStatus : 'badge-outline status-none';
            return '<span class="badge ' + cls + '" data-tip="' + String(statusTipOf(c)).replace(/"/g, '&quot;') + '">' +
                statusLabelOf(c) + '</span>';
        }

        var ROTATE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>';
        var BAN_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>';
        var EYE_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';

        function actionCell(c) {
            if (c.requestStatus === 'pending') {
                return '<button type="button" class="btn-icon btn-icon--ghost btn-icon--danger" data-tip="Cancel this request" aria-label="Cancel this request" onclick="event.stopPropagation(); openCancelConfirm(' + c.requestId + ', ' + c.id + ')">' + BAN_SVG + '</button>';
            }
            if (c.requestStatus === 'approved' || c.requestStatus === 'completed') {
                return '<button type="button" class="btn-icon btn-icon--ghost" data-tip="View this request" aria-label="View this request" onclick="event.stopPropagation(); quickViewByRowId(' + c.id + ')">' + EYE_SVG + '</button>';
            }
            return '<button type="button" class="btn-icon btn-icon--primary" data-tip="Arrange a replacement for this class" aria-label="Arrange a replacement" onclick="event.stopPropagation(); goToReplacementFor(' + JSON.stringify(c.sessionId) + ',' + JSON.stringify(c.week) + ', \'replacement-home\')">' + ROTATE_SVG + '</button>';
        }

        function statusBadgeForCard(c) {
            var key = c.requestStatus || 'none';
            var cls = c.requestStatus ? 'status-' + c.requestStatus : 'badge-outline status-none';
            return '<span class="badge ' + cls + '" data-tip="' + String(statusTipOf(c)).replace(/"/g, '&quot;') + '">' + statusShortOf(c) + '</span>';
        }

        /* ─── Days / urgency (unchanged law) ─── */
        function daysLeft(iso) {
            const now = new Date(getTodayMs());
            now.setHours(0, 0, 0, 0);
            const target = new Date(iso + 'T00:00:00');
            return Math.ceil((target - now) / (1000 * 60 * 60 * 24));
        }

        function urgencyClass(days) {
            if (days <= 7) return 'urgency-high';
            if (days <= 30) return 'urgency-mid';
            return 'urgency-low';
        }

        /* ─── State ─── */
        var state = { rpp: 10 };
        const pageState = { currentPage: 1 };
        let sortState = { field: 'date', dir: 'asc' };
        let currentFiltered = [];
        let currentRequestId = null;
        let currentOccurrenceId = null;
        let pendingCancelId = null;

        /* ─── Filters (M17 + M16's saved key) ─── */
        function saveFilters() {
            localStorage.setItem('mrh-filters', JSON.stringify({
                status: document.getElementById('statusFilter').value,
                search: document.getElementById('searchInput').value,
                week: document.getElementById('weekFilter').value,
                excludeCompleted: document.getElementById('hideCompleted').checked
            }));
        }

        function restoreFilters() {
            var raw = localStorage.getItem('mrh-filters');
            if (!raw) return;
            try {
                var f = JSON.parse(raw);
                if (f.status) document.getElementById('statusFilter').value = f.status;
                if (f.search) document.getElementById('searchInput').value = f.search;
                if (f.week) document.getElementById('weekFilter').value = f.week;
                if (typeof f.excludeCompleted === 'boolean') document.getElementById('hideCompleted').checked = f.excludeCompleted;
            } catch (e) {}
        }

        function completedHidden(c) {
            var box = document.getElementById('hideCompleted');
            return !!box && box.checked && c.requestStatus === 'completed';
        }

        function visibleBaseRows() {
            return myConflictedRows().filter(function (c) { return !completedHidden(c); });
        }

        function renderFilterChips() {
            var container = document.getElementById('filterChips');
            if (!container) return;
            var chips = [];
            var status = document.getElementById('statusFilter').value;
            var search = document.getElementById('searchInput').value.trim();
            var week = document.getElementById('weekFilter').value;
            var excludeCompleted = document.getElementById('hideCompleted').checked;

            if (status !== 'all') {
                var statusLabel = document.getElementById('statusFilter').selectedOptions[0].textContent;
                chips.push('<span class="filter-chip">Status: ' + statusLabel + '<button class="filter-chip-remove" onclick="document.getElementById(\'statusFilter\').value=\'all\';pageState.currentPage=1;saveFilters();buildTable()" data-tip="Remove">&times;</button></span>');
            }
            if (week !== 'all') {
                var weekLabel = document.getElementById('weekFilter').selectedOptions[0] ? document.getElementById('weekFilter').selectedOptions[0].textContent : week;
                chips.push('<span class="filter-chip">Week: ' + weekLabel + '<button class="filter-chip-remove" onclick="document.getElementById(\'weekFilter\').value=\'all\';pageState.currentPage=1;saveFilters();buildTable()" data-tip="Remove">&times;</button></span>');
            }
            if (search) {
                chips.push('<span class="filter-chip">Search: "' + search + '"<button class="filter-chip-remove" onclick="document.getElementById(\'searchInput\').value=\'\';pageState.currentPage=1;saveFilters();buildTable()" data-tip="Remove">&times;</button></span>');
            }
            if (excludeCompleted) {
                chips.push('<span class="filter-chip">Exclude COMPLETED Status<button class="filter-chip-remove" onclick="document.getElementById(\'hideCompleted\').checked=false;pageState.currentPage=1;saveFilters();buildTable()" data-tip="Remove">&times;</button></span>');
            }
            if (chips.length > 0) {
                chips.push('<button type="button" class="filter-chip filter-chip-clear-all" data-tip="Remove every active filter" onclick="clearAllFilters()">Clear all</button>');
            }
            container.innerHTML = chips.length > 0 ? '<span class="filter-chips-label">Active Filters:</span>' + chips.join('') : '';
        }

        /* Reset = the PAGE DEFAULTS (exclusion ON). */
        function resetAllFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('weekFilter').value = 'all';
            document.getElementById('hideCompleted').checked = true;
            localStorage.removeItem('mrh-filters');
            pageState.currentPage = 1;
            rebuildTable({ render: buildTable, after: updateWeekArrowState });
        }

        /* Clear all = EVERY filter off (exclusion OFF) — the chip bar empties. */
        function clearAllFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('weekFilter').value = 'all';
            document.getElementById('hideCompleted').checked = false;
            localStorage.removeItem('mrh-filters');
            pageState.currentPage = 1;
            rebuildTable({ render: buildTable, after: updateWeekArrowState });
        }

        /* ─── Table ─── */
        function buildTable() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const compactQuery = query.replace(/\s+/g, '');
            const status = document.getElementById('statusFilter').value;
            const weekVal = document.getElementById('weekFilter').value;

            let filtered = myConflictedRows().filter(function (c) {
                if (completedHidden(c)) return false;

                var matchesSearch = query === ''
                    || c.code.toLowerCase().includes(query)
                    || c.name.toLowerCase().includes(query)
                    || (c.cohorts || []).some(function (label) {
                        var plain = String(label).toLowerCase();
                        return plain.includes(query) || plain.replace(/\s+/g, '').includes(compactQuery);
                    });

                var matchesStatus = status === 'all'
                    || (status === 'none' ? !c.requestStatus : c.requestStatus === status);
                var matchesWeek = weekVal === 'all' || String(getWeekNumber(c.date)) === weekVal;

                return matchesSearch && matchesStatus && matchesWeek;
            });

            filtered.sort(function (a, b) {
                /* §3.4: the group key is the FIRST branch inside the comparator,
                   so the chosen column only sorts WITHIN a group. */
                var na = isHandled(a) ? 1 : 0, nb = isHandled(b) ? 1 : 0;
                if (na !== nb) return na - nb;

                var va, vb;
                if (sortState.field === 'code') {
                    va = a.code; vb = b.code;
                } else if (sortState.field === 'reason') {
                    va = a.conflictReason; vb = b.conflictReason;
                } else if (sortState.field === 'replacementDate') {
                    va = a.replacementDate || '9999'; vb = b.replacementDate || '9999';
                } else if (sortState.field === 'status') {
                    var r = statusRank(a) - statusRank(b);
                    if (r !== 0) return sortState.dir === 'asc' ? r : -r;
                    return a.id - b.id;
                } else {
                    va = a.date; vb = b.date;
                }

                var cmp = compareBy(sortState, va, vb);
                return cmp !== 0 ? cmp : a.id - b.id;
            });

            currentFiltered = filtered;

            const offset = pageState.currentPage > 0 ? (pageState.currentPage - 1) * state.rpp : 0;
            const pageData = filtered.slice(offset, offset + state.rpp);

            const head = document.getElementById('tableHead');
            const body = document.getElementById('tableBody');
            head.innerHTML = '';
            body.innerHTML = '';

            const columns = [
                { label: 'Course Code &amp; Name', cls: 'col-code', sortable: true, field: 'code', tip: 'Course affected by the conflict' },
                { label: 'Cohort', cls: 'col-cohort', sortable: false, tip: 'Affected cohorts — not sortable: a multi-cohort row has no single value' },
                { label: 'Original Class', cls: 'col-original', sortable: true, field: 'date', tip: 'The class that cannot run as timetabled' },
                { label: 'Conflict Reason', cls: 'col-reason', sortable: true, field: 'reason', tip: 'Why this class cannot run' },
                { label: 'Requested Replacement', cls: 'col-requested', sortable: true, field: 'replacementDate', tip: 'The new slot proposed for this class' },
                { label: 'Request Status', cls: 'col-status', sortable: true, field: 'status', tip: 'Where the request stands — the newest request wins' },
                { label: 'Action', cls: 'col-action', sortable: false, tip: 'What you can do with this class' },
            ];

            const tr = document.createElement('tr');
            columns.forEach(function (col) {
                const th = makeSortableHeader(col, sortState, function () {
                    pageState.currentPage = 1;
                    buildTable();
                });
                th.setAttribute('data-label', col.label.replace(/&amp;/g, '&'));
                tr.appendChild(th);
            });
            head.appendChild(tr);

            if (pageData.length === 0) {
                document.getElementById('emptyState').style.display = 'flex';
            } else {
                document.getElementById('emptyState').style.display = 'none';
                pageData.forEach(function (c) {
                    const row = document.createElement('tr');
                    /* §3.4/§5.2 — BOTH ids: the occurrence (data-id) and, when
                       there is one, the request (data-request-id). The arrival
                       matcher tries the request id first. */
                    row.dataset.id = c.id;
                    row.dataset.requestId = c.requestId || '';

                    var cells = HtmlBuilder.replacementHomeRow(c, {
                        merged: true,
                        formatClassBlock: formatClassBlock,
                        cohortsCell: cohortsCell,
                        reasonCell: function (row) { return reasonBadge(row); },
                        requestCell: requestCell,
                        statusCell: statusCell,
                        actionCell: actionCell
                    });
                    cells.forEach(function (cell) {
                        const td = document.createElement('td');
                        td.className = cell.cls;
                        td.innerHTML = cell.html;
                        row.appendChild(td);
                    });

                    /* The "Just cancelled" chip + Undo stay switched OFF until
                       the recency rule lands (prompts/pi-prompt-just-cancelled-chip-ttl.md).
                       When restored, the chip appends to children[2] — the
                       Original Class cell, the same index as before the merge. */

                    row.style.cursor = 'pointer';
                    row.addEventListener('click', function () { quickViewByRow(c); });
                    body.appendChild(row);
                });
            }

            paginate({ data: currentFiltered, pageSize: state.rpp, state: pageState, infoId: 'paginationInfo', controlsId: 'paginationControls', render: buildTable });
            updateResultCount({ elId: 'resultCount', data: currentFiltered, total: visibleBaseRows().length, label: 'classes' });
            updateSummary();
            renderCards();
            renderFilterChips();
        }

        function refreshLabel(text) {
            var el = document.getElementById('qvArrangeLabel');
            if (el) el.textContent = text;
        }

        function updateSummary() {
            const filtered = currentFiltered;
            const needs = filtered.filter(function (c) { return !isHandled(c); });
            const hours = needs.reduce(function (sum, c) { return sum + c.duration; }, 0);

            document.getElementById('summaryNeeds').textContent = needs.length;
            document.getElementById('summaryHours').textContent = hours;
            document.getElementById('summaryPending').textContent = filtered.filter(function (c) { return c.requestStatus === 'pending'; }).length;
            document.getElementById('summaryApproved').textContent = filtered.filter(function (c) { return c.requestStatus === 'approved'; }).length;
            document.getElementById('summaryRejected').textContent = filtered.filter(function (c) { return c.requestStatus === 'rejected'; }).length;

            syncSummarySection(filtered.length > 0);
        }

        function renderCards() {
            var container = document.getElementById('cardView');
            if (!container) return;
            container.innerHTML = '';
            currentFiltered.forEach(function (c) {
                var card = document.createElement('div');
                card.className = 'replacement-card';
                card.setAttribute('role', 'button');
                card.setAttribute('tabindex', '0');
                card.addEventListener('click', function () { quickViewByRow(c); });
                card.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        quickViewByRow(c);
                    }
                });
                card.innerHTML = HtmlBuilder.replacementHomeCard(c, {
                    daysLeft: daysLeft,
                    urgencyClass: urgencyClass,
                    badgeClass: badgeClass,
                    reasonText: reasonText,
                    isHandled: isHandled,
                    statusBadge: statusBadgeForCard
                });
                container.appendChild(card);
            });
        }

        function populateWeekDropdown() {
            populateWeekSelect('weekFilter', { includeAll: true });
        }

        /* BRIDGE PATCH: the handoff carries the occurrence's real identity —
           (class_session_id, week_number) — instead of code+date strings. */
        function goToReplacementWith(sessionId, week) {
            goToReplacementFor(sessionId, week, 'replacement-home');
        }

        function showKeyboardShortcuts() {
            var el = document.getElementById('keyboardModal');
            if (el) { el.classList.add('show'); }
        }

        function hideKeyboardShortcuts() {
            var el = document.getElementById('keyboardModal');
            if (el) { el.classList.remove('show'); }
        }

        /* ─── The detail pop-up (M7/M8) ─── */
        function quickViewByRow(c) {
            if (!c) return;
            currentRequestId = c.requestId || null;
            currentOccurrenceId = c.id;

            var typeLabel = UiLabels.classType(c.type, 'long');
            var tabs = [];

            /* Tab 1 — the class as timetabled. */
            var origHtml = DetailModal.section('Original Class Schedule',
                DetailModal.row('Class ID', '#' + c.sessionId, { strong: true }) +
                DetailModal.row('Week', getWeekNumber(c.date) ? 'Week ' + getWeekNumber(c.date) : null) +
                DetailModal.row('Day', c.day) +
                DetailModal.row('Date', formatDate(c.date)) +
                DetailModal.row('Time', to12h(c.timeStart) + ' ~ ' + to12h(c.timeEnd), { strong: true }) +
                DetailModal.row('Duration', durationWords(c.duration)) +
                /* the ONE place the room NAME is printed (M7) */
                DetailModal.row('Venue', venueLine(c.venue, c.venueName))
            );
            tabs.push({ key: 'original', label: 'Original Class Schedule', html: origHtml });

            /* Tab 2 — the requested slot; absent when nothing is proposed. */
            if (c.replacementDate) {
                tabs.push({
                    key: 'replacement',
                    label: 'Proposed Replacement Schedule',
                    html: DetailModal.section('Proposed Replacement Schedule',
                        DetailModal.row('Request ID', '#' + c.requestId, { strong: true }) +
                        DetailModal.row('Week', c.replacementWeek ? 'Week ' + c.replacementWeek : null) +
                        DetailModal.row('Day', c.replacementDay) +
                        DetailModal.row('Date', formatDate(c.replacementDate)) +
                        DetailModal.row('Time', String(c.replacementTime || '').replace(' \u2013 ', ' ~ '), { strong: true }) +
                        DetailModal.row('Duration', durationWords(rangeHours(c.replacementTime))) +
                        DetailModal.row('Venue', venueLine(c.replacementVenue, c.replacementVenueName))
                    )
                });
            }

            /* Tab 3 — the status: badge + ONE sentence + [reason] + [remark]. */
            var statusHtml = '<div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">' +
                statusCell(c) + '</span></div>' +
                DetailModal.row('Status Description', statusDescOf(c)) +
                (c.requestStatus === 'rejected' && c.rejectionReason
                    ? DetailModal.row('Rejection Reason', c.rejectionReason, { strong: true })
                    : '') +
                (c.remarks ? DetailModal.row('Remark', c.remarks) : '');
            tabs.push({
                key: 'status',
                label: 'Request Status (' + statusShortOf(c) + ')',
                html: DetailModal.section('Status', statusHtml)
            });

            DetailModal.render({
                modalId: 'quickViewModal',
                title: 'Replacement Details',
                subtitle: c.code + ' \u00b7 ' + c.name + ' (' + typeLabel + ')',
                factsHtml:
                    '<span class="df-item"><span class="df-label">Cohorts</span>' + ((c.cohorts || []).join(', ') || '\u2014') + '</span>' +
                    '<span class="df-item"><span class="df-label">Students</span>' + (window.cohortBreakdown ? window.cohortBreakdown(c) : c.totalStudents) + '</span>' +
                    '<span class="df-item df-item--full"><span class="df-label">Conflict</span>' + reasonBadge(c) + '</span>',
                tabs: tabs
            });

            /* Footer gates (§4.1): Cancel only while pending; Arrange hidden when handled. */
            var cancelBtn = document.getElementById('cancelRequestBtn');
            if (cancelBtn) cancelBtn.style.display = c.requestStatus === 'pending' ? 'inline-flex' : 'none';
            var arrangeBtn = document.getElementById('qvArrangeBtn');
            if (arrangeBtn) arrangeBtn.style.display = isHandled(c) ? 'none' : 'inline-flex';
        }

        function quickViewByRowId(occurrenceId) {
            var c = myConflictedRows().find(function (row) { return row.id === occurrenceId; });
            if (c) quickViewByRow(c);
        }

        function qvArrange() {
            var c = myConflictedRows().find(function (row) { return row.id === currentOccurrenceId; });
            if (!c) { hideQuickView(); return; }
            var sid = c.sessionId;
            var week = c.week;
            hideQuickView();
            goToReplacementFor(sid, week, 'replacement-home');
        }

        function hideQuickView() {
            currentRequestId = null;
            currentOccurrenceId = null;
            DetailModal.close();
        }

        /* ─── Cancel: ONE write path, re-read from the server (§4.4) ─── */
        function openCancelConfirm(requestId, occurrenceId) {
            if (requestId === undefined || requestId === null) return;
            pendingCancelId = requestId;
            currentRequestId = requestId;
            if (occurrenceId !== undefined) currentOccurrenceId = occurrenceId;

            /* the summary is re-read from the CURRENT row set at open time —
               never from a captured object that a previous cancel could stale */
            var c = myConflictedRows().find(function (row) { return Number(row.requestId) === Number(requestId); });
            if (c) {
                currentOccurrenceId = c.id;
                /* the facts the user is about to give up, in reading order:
                   what the class is, who is in it, when it was, when it moves to */
                var origWeek = getWeekNumber(c.date);
                var origWhen = (origWeek ? 'Week ' + origWeek + ', ' : '') + dayAbbr(c.day) + ', ' + formatDate(c.date) +
                    ', ' + to12h(c.timeStart) + ' \u2013 ' + to12h(c.timeEnd) + (c.venue ? ' @ ' + c.venue : '');
                /* the replacement time arrives as a 24-hour range ('10:00 \u2013 11:00');
                   the original reads 12-hour, so convert — one clock face per modal */
                var propRange = String(c.replacementTime || '').split(/\s*[\u2013-]\s*/);
                var propTime = propRange.length === 2
                    ? to12h(propRange[0]) + ' \u2013 ' + to12h(propRange[1])
                    : (c.replacementTime || '');
                var propWhen = c.replacementDate
                    ? (c.replacementWeek ? 'Week ' + c.replacementWeek + ', ' : '') + dayAbbr(c.replacementDay) + ', ' +
                      formatDate(c.replacementDate) + (propTime ? ', ' + propTime : '') +
                      (c.replacementVenue ? ' @ ' + c.replacementVenue : '')
                    : '\u2014';
                /* §10.0.2 — the canonical short code, so the modal and the table agree */
                var kindLabel = UiLabels.classType(c.type, 'code');
                var facts = [
                    ['Course', c.code + ' \u2014 ' + c.name + (kindLabel ? ' (' + kindLabel + ')' : '')],
                    ['Cohort', (c.cohorts && c.cohorts.length) ? c.cohorts.join(' & ') : '\u2014'],
                    ['Original Class Schedule', origWhen],
                    ['Proposed Class Schedule', propWhen],
                ];
                document.getElementById('cancelConfirmDetails').innerHTML = facts.map(function (f) {
                    return '<dt>' + f[0] + '</dt><dd>' + f[1] + '</dd>';
                }).join('');
            }
            document.getElementById('cancelConfirmOverlay').style.display = 'flex';
        }

        function closeCancelConfirm() {
            document.getElementById('cancelConfirmOverlay').style.display = 'none';
        }

        function patchRow(freshRow) {
            if (!freshRow) return;
            var list = MockData.conflictedClasses || [];
            for (var i = 0; i < list.length; i++) {
                if (Number(list[i].id) === Number(freshRow.id)) {
                    list[i] = freshRow;
                    return;
                }
            }
            list.push(freshRow);
        }

        function bindCancelConfirm() {
            var btn = document.getElementById('confirmCancelAction');
            if (!btn) return;
            btn.addEventListener('click', function () {
                /* the double-submit guard: null the id BEFORE the call, and
                   disable the button while the request is in flight */
                if (pendingCancelId === null) return;
                var cancelId = pendingCancelId;
                pendingCancelId = null;
                btn.disabled = true;

                var restore = function () { btn.disabled = false; };

                pageLivewire().call('cancelRequest', cancelId).then(function (result) {
                    restore();
                    var ok = result && result.ok;
                    var row = result ? result.row : null;

                    if (!ok) {
                        closeCancelConfirm();
                        hideQuickView();
                        buildTable();
                        toast.show('This request can no longer be cancelled.', null);
                        return;
                    }

                    patchRow(row);
                    closeCancelConfirm();
                    hideQuickView();
                    pageState.currentPage = 1;
                    buildTable();
                    toast.show('Request #' + cancelId + ' cancelled.', null);
                }).catch(function () {
                    restore();
                    closeCancelConfirm();
                    hideQuickView();
                    toast.show('This request can no longer be cancelled.', null);
                });
            });
        }

        /* ─── Arrival (M16) — ?notif-hl=r<id> [&view=request] ─── */
        function jumpToMyRow(requestId, openRequest) {
            if (!requestId) return;

            var inPayload = (MockData.conflictedClasses || []).find(function (c) {
                return Number(c.requestId) === Number(requestId);
            });

            /* `view=request` opens the modal even when a filter hides the row —
               the lookup is the PAYLOAD, not the DOM (S14.4). */
            if (openRequest && inPayload) {
                quickViewByRow(inPayload);
                return;
            }

            var idx = currentFiltered.findIndex(function (c) { return Number(c.requestId) === Number(requestId); });

            if (idx < 0) {
                var otherFiltersActive = document.getElementById('statusFilter').value !== 'all'
                    || document.getElementById('searchInput').value.trim() !== ''
                    || document.getElementById('weekFilter').value !== 'all';
                var onlyCompletedHidesIt = !!inPayload && inPayload.requestStatus === 'completed'
                    && document.getElementById('hideCompleted').checked
                    && !otherFiltersActive;

                if (!onlyCompletedHidesIt) {
                    toast.show('That request is hidden by your current filters.', null);
                    return;
                }

                document.getElementById('hideCompleted').checked = false;
                saveFilters();
                buildTable();
                idx = currentFiltered.findIndex(function (c) { return Number(c.requestId) === Number(requestId); });
                if (idx < 0) {
                    toast.show('That request is hidden by your current filters.', null);
                    return;
                }
            }

            var page = Math.floor(idx / state.rpp) + 1;
            if (page !== pageState.currentPage) {
                pageState.currentPage = page;
                buildTable();
            }
        }

        document.addEventListener('keydown', function (e) {
            var tag = (e.target || {}).tagName || '';
            var isInput = (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT');

            if (e.key === '/' && !isInput) {
                e.preventDefault();
                var s = document.getElementById('searchInput');
                if (s) s.focus();
                return;
            }
            if (e.key === 'Escape') {
                var qvEl = document.getElementById('quickViewModal');
                if (qvEl && qvEl.classList.contains('show')) { hideQuickView(); return; }
                var cc = document.getElementById('cancelConfirmOverlay');
                if (cc && cc.style.display === 'flex') { closeCancelConfirm(); return; }
                var el = document.getElementById('keyboardModal');
                if (el && el.classList.contains('show')) { hideKeyboardShortcuts(); return; }
                clearAllFilters();
                return;
            }
            if (e.key === '?' && !isInput) {
                e.preventDefault();
                showKeyboardShortcuts();
                return;
            }
            if (isInput) return;
            if (e.key === 'ArrowRight') { goNextPage(); }
            if (e.key === 'ArrowLeft') { goPrevPage(); }
        });

        function goNextPage() {
            var totalPages = Math.ceil(currentFiltered.length / state.rpp);
            if (pageState.currentPage < totalPages) {
                pageState.currentPage++;
                buildTable();
            }
        }

        function goPrevPage() {
            if (pageState.currentPage > 1) {
                pageState.currentPage--;
                buildTable();
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            /* D6: a home badge arrives as ?notif-hl=r<request_id>. Capture it
               SYNCHRONOUSLY — the shared highlighter strips the param at 150 ms
               and the first render sits behind the skeleton. */
            var hlRaw = new URLSearchParams(location.search).get('notif-hl');
            var pendingHl = (hlRaw && hlRaw.charAt(0) === 'r') ? hlRaw.slice(1) : null;
            var openOnArrival = new URLSearchParams(location.search).get('view') === 'request';

            MockData.conflictedClasses = window.PAGE_DATA.conflictedClasses;
            MockData.semester = window.PAGE_DATA.semester;
            MockData.holidays = window.PAGE_DATA.holidays;
            MockData.currentUser = window.PAGE_DATA.currentUser;
            MockData.mockNow = null; /* real clock — the demo anchor is mock-era only */

            /* class-cancel-db-persist: ui-common's own DOMContentLoaded ran
               applyLedger BEFORE this bridge filled MockData.conflictedClasses
               — a server-bridged entry's retention check saw an empty set and
               was silently dropped. Re-run here, after the swap (idempotent). */
            ClassCancellation.applyLedger();
            initHeaderTooltips();
            document.getElementById('semesterChip').textContent = MockData.semester.chipText;
            populateWeekDropdown();
            restoreFilters();

            SkeletonLoader.showSummary();
            SkeletonLoader.with(function () {
                buildTable();
                SkeletonLoader.hideSummary();
                if (pendingHl) { jumpToMyRow(pendingHl, openOnArrival); pendingHl = null; }
            }, document.getElementById('tableBody'), 10, 400);

            updateWeekArrowState();
            initWeekKeyboardShortcuts();

            initRpp({
                selectId: 'rppSelect',
                storageKey: 'rpp-page-size',
                defaultVal: 10,
                onChange: function (size) {
                    state.rpp = size;
                    rebuildTable({ render: buildTable });
                }
            });

            document.getElementById('searchInput').addEventListener('input', function () {
                saveFilters();
                rebuildTable({ render: buildTable });
            });
            document.getElementById('statusFilter').addEventListener('change', function () {
                saveFilters();
                rebuildTable({ render: buildTable });
            });
            document.getElementById('weekFilter').addEventListener('change', function () {
                saveFilters();
                rebuildTable({ render: buildTable, after: updateWeekArrowState });
            });
            document.getElementById('hideCompleted').addEventListener('change', function () {
                saveFilters();
                pageState.currentPage = 1;
                buildTable();
            });
            document.getElementById('resetFilters').addEventListener('click', resetAllFilters);

            document.getElementById('cancelConfirmOverlay').addEventListener('click', function (e) {
                if (e.target === this) closeCancelConfirm();
            });
            bindCancelConfirm();
        });

        /* inline handlers resolve their globals here */
        window.quickViewByRow = quickViewByRow;
        window.quickViewByRowId = quickViewByRowId;
        window.hideQuickView = hideQuickView;
        window.qvArrange = qvArrange;
        window.openCancelConfirm = openCancelConfirm;
        window.closeCancelConfirm = closeCancelConfirm;
        window.clearAllFilters = clearAllFilters;
        window.resetAllFilters = resetAllFilters;
        window.buildTable = buildTable;
        window.saveFilters = saveFilters;
        window.pageState = pageState;
        window.hideKeyboardShortcuts = hideKeyboardShortcuts;
@endsection
