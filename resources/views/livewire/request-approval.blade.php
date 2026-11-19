{{-- The frozen UI design from ui-design-templates/, byte-for-byte, with
     only the data source bridged to the backend (see the BRIDGE marks
     below) + the Livewire actions (SDD span-aware-approval-flow). --}}

@section('title', 'Request Approval')

@section('page-styles')

        /* 2026-11-14 (modal-responsive-width): the page-local 680px pin is GONE.
           The shared rule above 600px is `width: fit-content; min-width: 420px`
           with 680 as the ceiling, so this pop-up shrinks with its content like
           every other one instead of always reserving 680px. */

        /* ───── Column Widths ───── */
        .col-no { width: 40px; text-align: center; }
        #timetable :is(.col-original, .col-replacement) {
            --approval-slot-width: clamp(184px, 12vw, 224px);
            width: var(--approval-slot-width);
            min-width: var(--approval-slot-width);
            white-space: normal;
        }
        #timetable :is(.col-original, .col-replacement) .class-time {
            white-space: nowrap;
        }
        .col-replacement .cell-class-block .class-time {
            font-weight: 600;
        }
        #timetable .col-replacement .cell-class-block :is(.urgency-high, .urgency-mid, .urgency-low) {
            white-space: normal;
        }
        .col-replacement .cell-class-block .class-venue {
            font-size: 11px;
            color: var(--color-on-surface-variant);
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }
        /* Slot-validity badge — sits to the LEFT of the venue; bg varies by status */
        .slot-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            flex-shrink: 0;
        }
        .slot-badge.slot-valid {
            background: var(--color-success-container);
            color: var(--color-on-success-container);
            border: 1px solid var(--color-success);
        }
        .slot-badge.slot-conflict {
            background: var(--color-error-container);
            color: var(--color-on-error-container);
            border: 1px solid var(--color-error);
        }

        /* ───── Status Badges (page-specific overrides) ───── */
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: filter 0.15s;
        }
        .badge:hover {
            filter: brightness(1.1);
        }
        .badge-subtitle {
            display: block;
            font-size: 11px;
            font-weight: 400;
            opacity: 0.7;
            margin-top: 2px;
        }


        /* ───── Action Buttons ───── */
        .btn-approve { padding: 4px 10px; border-radius: var(--radius-sm); border: 1px solid var(--color-success); background: transparent; color: var(--color-success); font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.15s, color 0.15s; }
        .btn-approve:hover { background: var(--color-success); color: var(--color-on-success); }
        .btn-reject { padding: 4px 10px; border-radius: var(--radius-sm); border: 1px solid var(--color-error); background: transparent; color: var(--color-error); font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.15s, color 0.15s; }
        .btn-reject:hover { background: var(--color-error); color: var(--color-on-error); }
        .btn-view { padding: 4px 10px; border-radius: var(--radius-sm); border: 1px solid var(--color-outline); background: transparent; color: var(--color-on-surface-variant); font-size: 12px; font-weight: 500; cursor: pointer; transition: background 0.15s; }
        .btn-view:hover { background: var(--color-surface-variant); }

        /* ───── Summary Card Colors (approved/rejected/total accents in theme.css) ───── */

        /* ───── Bulk Selection (page-specific overrides) ───── */
        .col-checkbox input[type="checkbox"] { width: 15px; height: 15px; accent-color: var(--color-primary); cursor: pointer; }
        .bulk-action-bar {
            display: none;
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 50;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            background: var(--color-surface);
            border-top: 1px solid var(--color-outline);
            box-shadow: var(--shadow-lg);
        }
        .bulk-action-bar.visible { display: flex; }
        .bulk-action-bar .bulk-count { font-size: 13px; font-weight: 600; color: var(--color-on-surface); }
        .btn-bulk-clear {
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            border: 1px solid var(--color-outline);
            background: transparent;
            color: var(--color-on-surface-variant);
            font-family: inherit;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s;
        }
        .btn-bulk-clear:hover { background: var(--color-surface-variant); }

        /* ───── Request Age Indicator (base .request-age styles in theme.css) ───── */

        /* ───── Reject Preset Chips ───── */
        .reject-presets { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
        .reject-preset-chip {
            padding: 4px 10px;
            border-radius: var(--radius-lg);
            border: 1px solid var(--color-outline);
            background: transparent;
            color: var(--color-on-surface-variant);
            font-size: 12px;
            cursor: pointer;
            transition: background 0.15s, color 0.15s;
        }
        .reject-preset-chip:hover { background: var(--color-surface-variant); }

        /* ───── Viewed Row Indicator ───── */
        .row-viewed td:first-child { border-left: 3px solid var(--color-primary); }

        /* ───── Keyboard Highlight ───── */
        .row-focused { background: var(--color-primary-container) !important; border-left: 3px solid var(--color-primary); }

        /* ───── Row Flash Animations ───── */
        .row-flash-approved { animation: flashGreen 0.6s ease; }
        .row-flash-rejected { animation: flashRed 0.6s ease; }
        @keyframes flashGreen { 0% { background: var(--color-success-container); } 100% { background: transparent; } }
        @keyframes flashRed { 0% { background: var(--color-error-container); } 100% { background: transparent; } }

        /* ───── Lecturer Cell ───── */
        .lecturer-cell-name { font-weight: 600; font-size: 13px; color: var(--color-on-surface); }
        .lecturer-cell-id { font-size: 11px; color: var(--color-on-surface-variant); opacity: 0.7; font-weight: 400; }
        .lecturer-cell-email {
            font-size: 12px; color: var(--color-primary); cursor: pointer;
            display: inline-flex; align-items: center; gap: 4px;
            padding: 2px 8px; border-radius: var(--radius-md);
            background: var(--color-primary-container); color: var(--color-on-primary-container);
            transition: opacity 0.15s;
        }
        .lecturer-cell-email:hover { opacity: 0.8; }
        .lecturer-cell-email .copy-icon { font-size: 10px; opacity: 0.6; }

        /* 2026-11-14 (modal-responsive-width): the page-local 1025+ ceiling that
           used to live here is GONE — one shared ceiling now governs every
           pop-up, and this page does not re-pin it. */

        /* ───── Approval context card (Approve/Reject modals) ───── */
        .approval-compare { display: flex; flex-direction: column; gap: 12px; margin-bottom: 12px; }
        .approval-card { border: 1px solid var(--color-outline); border-radius: var(--radius-md); padding: 10px 12px; background: var(--color-surface); }
        .approval-card-head { display: flex; align-items: center; flex-wrap: wrap; gap: 6px; margin-bottom: 4px; }
        .approval-id { font-size: 12px; font-weight: 700; color: var(--color-on-surface-variant); }
        .approval-card .cell-code { display: inline-block; font-weight: 700; }
        .approval-course { font-size: 13px; font-weight: 600; color: var(--color-on-surface); margin-bottom: 2px; }
        .approval-classtype { font-size: 11px; font-weight: 500; color: var(--color-on-surface-variant); }
        .approval-lecturer { display: flex; align-items: center; gap: 7px; font-size: 12px; color: var(--color-on-surface-variant); margin-bottom: 10px; }
        .approval-compare-row { display: flex; align-items: stretch; gap: 8px; }
        .cmp-card { flex: 1; min-width: 0; border-radius: var(--radius-md); padding: 8px 10px; }
        .cmp-label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: var(--color-on-surface-variant); margin-bottom: 4px; }
        .cmp-original { background: var(--color-surface-variant); opacity: 0.85; }
        .cmp-replacement { background: var(--color-success-container); border-left: 3px solid var(--color-success); }
        .cmp-arrow { align-self: center; font-size: 16px; font-weight: 700; color: var(--color-on-surface-variant); flex-shrink: 0; }
        .approval-meta { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
        .meta-chip { font-size: 11px; padding: 2px 8px; border-radius: var(--radius-md); background: var(--color-surface-variant); color: var(--color-on-surface-variant); }
        .approval-conflict { margin-top: 8px; font-size: 12px; padding: 6px 8px; border-radius: var(--radius-sm); background: var(--color-error-container); color: var(--color-on-error-container); }
        @media (max-width: 768px) {
            .approval-compare-row { flex-direction: column; }
            .cmp-arrow { transform: rotate(90deg); }
        }

        #viewAllRequests { margin-top: 16px; }
        @media (any-pointer: coarse) {
            #viewAllRequests { min-width: 48px; min-height: 48px; }
        }

        /* ───── Responsive Card View (base .request-card styles in theme.css) ───── */
        @media (max-width: 768px) {
            .grid-wrapper, .pagination-bar, .sort-hint { display: none !important; }
            /* ══ the phone card shows the slot being APPROVED (2026-11-17) ══
               Ported from `ui-prototype/my-replacements.html` (`wrapRequestCard` + its
               `.cl-rc-*` block). Before this the card printed `Course: / Date: /
               Lecturer:` with the ORIGINAL class date and never the requested slot —
               so a PL on a phone was deciding on a slot the card did not show. */
            .cl-rc-head { display: flex; align-items: center; gap: 8px;
                padding-bottom: 8px; margin-bottom: 8px;
                border-bottom: 1px solid var(--color-outline-strong); }
            .cl-rc-head .rc-code { flex: 1 1 auto; min-width: 0; font-size: 13px; font-weight: 600; }
            .cl-id-pill { display: inline-flex; align-items: center; flex: 0 0 auto;
                padding: 1px 8px; border-radius: 999px;
                background: var(--color-surface-variant);
                color: var(--color-on-surface-variant);
                font-size: 10.5px; font-weight: 700; line-height: 1.6;
                letter-spacing: .02em; white-space: nowrap; }
            .cl-rc-slot { display: grid; grid-template-columns: 72px minmax(0, 1fr);
                column-gap: 8px; align-items: start; }
            .cl-rc-label { margin-top: 2px; font-size: 9px; line-height: 1.2;
                letter-spacing: .06em; text-transform: uppercase;
                color: var(--color-on-surface-variant); }
            .cl-rc-label .cl-week-pill { display: inline-flex; margin-top: 3px;
                padding: 1px 7px; border-radius: var(--radius-pill);
                background: var(--color-surface-variant);
                color: var(--color-on-surface-variant); text-transform: none;
                letter-spacing: 0; font-size: 9.5px; font-weight: 700; line-height: 1.5;
                white-space: nowrap; }
            .cl-rc-grid { display: grid;
                grid-template-columns: minmax(0, 1fr) minmax(0, 1.28fr);
                column-gap: 6px; row-gap: 1px; align-items: baseline; }
            .cl-rc-date { grid-area: 1 / 1; font-size: 12px; font-weight: 600; line-height: 1.35; }
            .cl-rc-urg { grid-area: 1 / 2; font-size: 9.5px; font-weight: 600;
                line-height: 1.3; text-align: right; }
            .cl-rc-time { grid-area: 2 / 1; font-size: 11px; line-height: 1.35; }
            .cl-rc-venue { grid-area: 2 / 2; justify-self: end; font-size: 11px; line-height: 1.35; }
            .cl-rc-venue .slot-badge { margin-left: 4px; vertical-align: -2px; }
            .cl-rc-foot { display: flex; align-items: baseline; gap: 10px;
                margin-top: 8px; padding-top: 8px;
                border-top: 1px solid var(--color-outline-strong); }
            .cl-rc-course { font-size: 11px; line-height: 1.25; font-weight: 600;
                color: var(--color-on-surface); min-width: 0; overflow-wrap: anywhere; }
            .cl-rc-meta { display: flex; align-items: baseline; gap: 6px;
                margin-left: auto; font-size: 11px; line-height: 1.25;
                color: var(--color-on-surface-variant); white-space: nowrap; }
            .cl-rc-meta .cl-req-age { font-weight: 600; }
            .cl-rc-meta .cl-req-age.age-fresh { color: var(--color-success); }
            .cl-rc-meta .cl-req-age.age-waiting { color: var(--color-warning); }
            .cl-rc-meta .cl-req-age.age-stale { color: var(--color-error); }
            @media (max-width: 360px) { .cl-rc-urg { white-space: normal; } }
            /* ≥600: this card has ONE band, so the 2 × 2 becomes a single line with
               the countdown keeping the right edge — the drawing's own rule for the
               tablet width (task 11, T6/AC8) */
            @media (min-width: 600px) {
                .cl-rc-grid { display: flex; flex-wrap: wrap;
                    align-items: baseline; gap: 2px 14px; }
                .cl-rc-date, .cl-rc-time, .cl-rc-venue { flex: 0 0 auto; }
                .cl-rc-urg { order: 9; margin-left: auto; }
            }
            .requests-cards { display: grid !important; gap: 12px; padding: 12px; }
            .card-view { display: block; }
        }
        @media (min-width: 769px) {
            .requests-cards { display: none !important; }
        }

@endsection

{{-- Real-data bridge (SDD span-aware-approval-flow): the frozen template runs
     UNCHANGED below except the documented BRIDGE marks; PAGE_DATA.requests
     carries the mock's exact row shape derived from the real DB (PL scope).
     NO content section — the root rides top-level (my-request-history). --}}
<div class="lw-page ui-cards-page">
    @include('partials.page-data-script', ['payload' => $payload])

@include('partials.ui-page-header', [
    'title' => 'Request Approval',
    'guideToggle' => true,
    'guideModal' => true,
    'summaryButton' => true,
])

@include('partials.ui-guide-modal', [
    'guideSubtitle' => 'Request Approval',
    'guideContentView' => 'partials.guides.request-approval',
    'guideHasShortcuts' => true,
])

<!-- ─── Toolbar (filters-button: the host + placeholder keep the toolbar's seat,
       so FiltersPanel can MOVE it into the panel and back — never rebuild it). ─── -->
<div id="filterToolbarHost" wire:ignore>
    <span class="filters-toolbar-placeholder" hidden></span>
    <div class="toolbar">
        <div class="toolbar-left">
            <div class="filter-field">
                <label class="filter-label" for="searchInput">Search</label>
                <input class="search-input" id="searchInput" placeholder="Search course code, name, or lecturer..." data-tip="Search by course code, name, or lecturer">
            </div>
            <div class="filter-line">
                <div class="filter-field">
                    <label class="filter-label" for="statusFilter">Status</label>
                    <select class="filter-select" id="statusFilter" data-tip="Filter by Request Status">
                        <option value="all">All Status</option>
                        <option value="Pending" selected>Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                        {{-- No Cancelled option (2026-11-14): a cancelled request never reaches this page —
                             the payload carries pending, approved, rejected and completed-by-date rows only,
                             so the option could only ever show an empty list. Same ruling as My Replacements
                             on 2026-11-06. --}}
                        <option value="Completed">Completed</option>
                    </select>
                </div>
                <div class="filter-field filter-field--toggle">
                    <label class="filter-label" for="hideCompletedToggle">Hide COMPLETED</label>
                    <label class="toggle-wrapper" id="completedToggle" data-tip="ON = completed requests are excluded from the list">
                        <input type="checkbox" id="hideCompletedToggle" checked onchange="toggleHideCompleted()">
                        <span class="toggle-track"><span class="toggle-thumb"></span></span>
                        <span class="toggle-label">Exclude <span class="info-keyword">COMPLETED</span> Status</span>
                    </label>
                </div>
            </div>
            {{-- The ADDED field (design §4.3): the ORIGINAL slot week, rendered ABOVE
                 the existing requested one to follow the panel's field order. --}}
            <div class="filter-field">
                <label class="filter-label" for="weekFilterOriginal">Original slot week</label>
                @include('partials.ui-week-nav', ['prevOnclick' => "prevWeekFilter('weekFilterOriginal')", 'prevTip' => 'Previous original week', 'nextOnclick' => "nextWeekFilter('weekFilterOriginal')", 'nextTip' => 'Next original week', 'selectId' => 'weekFilterOriginal', 'selectOnclick' => 'onOrigWeekFilterChange(this.value)', 'selectTip' => 'Show only rows whose original slot falls in this week', 'showTodayBtn' => false])
            </div>
            <div class="filter-field">
                <label class="filter-label" for="weekFilter">Requested slot week</label>
                @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeekFilter()', 'prevTip' => 'Previous week', 'nextOnclick' => 'nextWeekFilter()', 'nextTip' => 'Next week', 'selectId' => 'weekFilter', 'selectOnclick' => 'onWeekFilterChange(this.value)', 'selectTip' => 'Pick the Requested Replacement week to display', 'showTodayBtn' => false])
            </div>
        </div>
    </div>
</div>


@include('partials.ui-filter-panel', [
    'filtersReset' => true,
    'filtersResetOnclick' => 'resetFilters()',
])

{{-- 2026-11-17: this page used to hand-roll the grid markup, so it was the one grid with
     no scroll cue — the shared partial is what carries it. Same ids as before
     (wrapper #gridWrapper, table #timetable, #tableHead, #tableBody), so the page's
     own scripts and the responsive table↔cards swap are untouched. --}}
@include('partials.ui-grid-table', [
    'wrapperId' => 'gridWrapper',
    'tableClass' => 'timetable data-table',
])

<div class="requests-cards" id="requestsCards" style="display:none"></div>

<div class="pagination-bar" id="paginationBar">
    @include('partials.ui-rpp', ['id' => 'rowsPerPage', 'default' => 10, 'options' => [10, 20, 50]])
    <span class="pagination-info" id="paginationInfo"></span>
    <div class="pagination-controls" id="paginationControls"></div>
</div>

<div class="bulk-action-bar" id="bulkActionBar">
    <span class="bulk-count" id="bulkCount"></span>
    <button class="btn-bulk-clear" onclick="clearSelection()">Clear</button>
    <button class="btn-approve" onclick="bulkApprove()">Approve Selected</button>
    <button class="btn-reject" onclick="bulkReject()">Reject Selected</button>
</div>

<!-- ─── Empty State (above the summary; summary auto-hides when the view is empty) ─── -->
<div class="empty-state" id="emptyState" style="display:none">
    <div class="empty-icon">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
    </div>
    <h3 id="emptyTitle">No requests found</h3>
    <p id="emptyText">No requests match your search or filter criteria.</p>
    <button type="button" class="btn-outline" id="viewAllRequests" onclick="clearAllFilters()" hidden>View all requests</button>
</div>

@include('partials.ui-summary-bar', [
    'flipCards' => true,
    'summaryScope' => 'filtered',
    'cards' => [
        ['class' => 'card-total', 'valueId' => 'summaryTotal', 'label' => 'Total Requests',
            'bullets' => ['Matches filters', 'All statuses', 'One list'],
            'explanation' => 'Requests that match the <span class="info-keyword">filters you have set</span>.',
            'detail' => [
                'items' => [
                    ['Matches filters', '<span>Search, status and week narrow the list<br>before this number is taken.</span>'],
                    ['All statuses', '<span>Pending, approved, rejected,<br>cancelled and completed.</span>'],
                    ['One list', '<span>Every request from lecturers<br>in your programme.</span>'],
                ],
            ]],
        ['class' => 'card-pending', 'valueId' => 'summaryPending', 'label' => 'Pending Requests',
            'bullets' => ['Awaiting you', 'No decision yet', 'Approve or reject'],
            'explanation' => 'Requests <span class="warn-keyword">waiting for your decision</span> — no approval or rejection yet.',
            'detail' => [
                'items' => [
                    ['Awaiting you', '<span>Only requests you have not decided on.</span>'],
                    ['No decision yet', '<span>Approve or reject it to clear the card.</span>'],
                    ['Request age', '<span>Colour shows how long it has waited:<br><span style="color:var(--color-primary)">● 1 day</span> · <span style="color:var(--color-tertiary)">● 2–3 days</span> · <span style="color:var(--color-error)">● 4+ days</span></span>'],
                ],
            ]],
        ['class' => 'card-approved', 'valueId' => 'summaryApproved', 'label' => 'Approved Requests',
            'bullets' => ['You approved', 'Confirmed', '<strong class="summary-replacement">Blue</strong> blocks'],
            'explanation' => 'Requests <span class="info-keyword">you approved</span> — the replacement is confirmed.',
            'detail' => [
                'items' => [
                    ['You approved', '<span>Your own decision, not another PL’s.</span>'],
                    ['Replacement confirmed', '<span>The lecturer’s class is now replaced.</span>'],
                    ['Shown in blue', '<span>The new block is blue on the timetable.</span>'],
                ],
            ]],
        ['class' => 'card-rejected', 'valueId' => 'summaryRejected', 'label' => 'Rejected Requests',
            'bullets' => ['You declined', 'Arrange again', 'No slot move'],
            'explanation' => 'Requests <span class="warn-keyword">you declined</span> — the lecturer must arrange again.',
            'detail' => [
                'items' => [
                    ['You declined', '<span>The request was turned down.</span>'],
                    ['Lecturer must re-arrange', '<span>It returns to <strong>Classes to Arrange</strong><br>for the lecturer.</span>'],
                    ['No slot move', '<span>The class stays where it was.</span>'],
                ],
            ]],
        ['class' => 'card-total', 'valueId' => 'summaryReviewed', 'label' => 'Total Reviewed',
            'bullets' => ['Already decided', 'Approved/rejected', 'Includes completed'],
            'explanation' => 'Requests already <span class="info-keyword">decided</span>: approved, rejected or completed.',
            'detail' => [
                'formula' => [['v' => 'approved', 'c' => 'is-blue'], ['op' => '+'], ['v' => 'rejected'], ['op' => '+'], ['v' => 'completed']],
                'items' => [
                    ['Already decided', '<span>Approved or rejected, plus completed.</span>'],
                    ['Completed', '<span>The replacement class has already run.</span>'],
                    ['Not pending', '<span>Pending requests are never<br>counted in this card.</span>'],
                ],
            ]]
    ]
])

<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="modalTitle">Request Details</span>
            <button class="modal-close" onclick="closeModal()" data-tip="Close">✕</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer">
            {{-- CONSISTENCY 3F (2026-11-03): no Close button (the header ✕ and the
                 overlay/Esc still close); Reject sits bottom-LEFT. --}}
            <div class="modal-footer-left">
                <button class="btn-danger" id="rejectRequestBtn" onclick="openRejectModal(currentModalId)" style="display:none">✕ Reject</button>
            </div>
            <div class="modal-footer-right" style="gap: 8px">
                {{-- 2026-11-17 (task 10, decision 8/10): Approve is the one filled primary;
                     Reject stays the outline above it. Close never owns the fill. --}}
                <button class="btn-approve is-primary" id="approveRequestBtn" onclick="approveRequest(currentModalId)" style="display:none">✓ Approve</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="rejectReasonModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="rejectReasonTitle">Reject Requests</h2>
            <button class="modal-close" onclick="closeRejectModal()" data-tip="Close">✕</button>
        </div>
        <div class="modal-body">
            <div id="rejectSummary" class="approval-compare"></div>
            <div class="reject-presets">
                <button class="reject-preset-chip" onclick="applyRejectPreset('Venue Unavailable')">Venue Unavailable</button>
                <button class="reject-preset-chip" onclick="applyRejectPreset('Insufficient Notice')">Insufficient Notice</button>
                <button class="reject-preset-chip" onclick="applyRejectPreset('Slot Conflict')">Slot Conflict</button>
                <button class="reject-preset-chip" onclick="applyRejectPreset('Lecturer Unavailable')">Lecturer Unavailable</button>
                <button class="reject-preset-chip" onclick="applyRejectPreset('')">Other</button>
            </div>
            <p class="section-heading-sub" style="margin-bottom:8px">Reason (required):</p>
            <textarea id="rejectReasonInput" placeholder="Enter rejection reason..." rows="4" class="modal-textarea"></textarea>
                @include('partials.ui-irreversible-line')
        </div>
        <div class="modal-footer">
            <div class="modal-footer-left">
                <button class="btn-outline" id="cancelRejectBtn" onclick="closeRejectModal()">No, Keep Requests Pending</button>
            </div>
            <div class="modal-footer-right">
                <button class="btn-danger" id="confirmRejectBtn" disabled onclick="rejectRequest()">Yes, Reject Requests</button>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="approveNotesModal">
    <div class="modal">
        <div class="modal-header">
            <h2 id="approveNotesTitle">Approve Request</h2>
            <button class="modal-close" onclick="closeApproveNotesModal()" data-tip="Close">✕</button>
        </div>
        <div class="modal-body">
            <div id="approveNotesSummary" class="approval-compare"></div>
            <p class="section-heading-sub" style="margin-bottom:8px">Notes (optional):</p>
            <textarea id="approveNotesInput" placeholder="Optional note for the audit trail..." rows="2" class="modal-textarea"></textarea>
                @include('partials.ui-irreversible-line')
        </div>
        <div class="modal-footer">
            <div class="modal-footer-left">
                <button class="btn-outline" onclick="closeApproveNotesModal()">No, Keep Requests Pending</button>
            </div>
            <div class="modal-footer-right">
                <button class="btn-approve" id="confirmApproveBtn" onclick="confirmApproveWithNotes()">Yes, Approve Requests</button>
            </div>
        </div>
    </div>
</div>
</div><!-- /lw-page -->


@section('page-scripts')
        initHeaderTooltips();
        // ── Shared data from mock-data.js ──

        // Status tooltips come from the payload (`requestStatusTip`, the shared
        // M5 vocabulary in the PL voice) — 1f/3B deleted the page-local 3-key
        // map here, which was why a Completed or Cancelled badge had no tip.

        function statusTipAttr(r) {
            return r.requestStatusTip ? ' data-tip="' + r.requestStatusTip + '"' : '';
        }

        // ── Lecturer lookup + copy email ──
        function lookupLecturer(name) {
            return MockData.lecturers.find(function(l) { return l.name === name; }) || null;
        }

        function copyEmail(email, event) {
            event.stopPropagation();
            navigator.clipboard.writeText(email).then(function() {
                toast.show('Email copied: ' + email, null, 2000);
            });
        }

        // ── Slot validity helper ──
        function slotValidityHtml(r) {
            const badge = '<span class="slot-badge ' + (r.slotValidity === 'conflict' ? 'slot-conflict' : 'slot-valid') + '">' + (r.slotValidity === 'conflict' ? '⚠' : '✓') + '</span>';
            if (r.slotValidity === 'conflict') {
                return badge + (r.conflictReason ? ' <span style="color:var(--color-error);font-size:11px">' + r.conflictReason + '</span>' : '');
            }
            return badge;
        }

        // ── Request age helper (§7.5) ──
        // ── Cell helpers (kept when the urgency family was removed, 2026-11-07) ──
        /* the app's compact cohort code (`RSD3 (S1) G1` → `RSD3(S1)G1`) */
        function compactCohort(label) {
            return String(label == null ? '' : label).replace(/\s+/g, '');
        }

        /* 3C — "B103 — Tutorial Room": the code, then the room name when the
           payload has one. The merged page's `venueLine()` is the same rule. */
        function venueText(code, name) {
            if (!code) return null;
            /* 2026-11-10 (user, #4): the ONE venue wording — ModalFacts never
               repeats a code the room's own name already carries */
            return window.ModalFacts ? ModalFacts.venue(code, name) : (name ? code + ' \u2014 ' + name : code);
        }

        // ── Filter persistence (§7) ──
        /* filters-button (2026-11-15): the boot order is initRpp() (which fires its
           onChange → saveFilters) BEFORE SkeletonLoader runs restoreFilters(), so
           an unconditional save overwrote the stored view with the just-populated
           defaults and every reload forgot the filters — the original week filter
           included, i.e. a pre-existing bug. Saves are therefore ignored until the
           restore has read the stored view; nothing else calls saveFilters() that
           early. */
        var filtersRestored = false;
        function saveFilters() {
            if (!filtersRestored) return;
            const state = {
                weekFilter: document.getElementById('weekFilter')?.value || 'all',
                origWeekFilter: weekFilterValue('weekFilterOriginal'),
                statusFilter: document.getElementById('statusFilter')?.value || 'Pending',
                rpp: rpp,
                sortField: sortState.field,
                sortDir: sortState.dir,
                hideCompleted: hideCompleted
            };
            localStorage.setItem('request-approval-filters', JSON.stringify(state));
        }

        function restoreFilters() {
            filtersRestored = true;      /* from here on, saves are real again */
            const saved = localStorage.getItem('request-approval-filters');
            if (!saved) return false;
            try {
                const state = JSON.parse(saved);
                if (state.weekFilter) document.getElementById('weekFilter').value = state.weekFilter;
                /* The original week is a later field: a saved view that predates it (or
                   names a week this build does not list) falls back to ALL WEEKS rather
                   than blanking the select and matching nothing. */
                if (state.origWeekFilter) {
                    var origWeekSel = document.getElementById('weekFilterOriginal');
                    if (origWeekSel && Array.prototype.some.call(origWeekSel.options, function (o) { return o.value === state.origWeekFilter; })) {
                        origWeekSel.value = state.origWeekFilter;
                    }
                }
                if (state.statusFilter) document.getElementById('statusFilter').value = state.statusFilter;
                if (state.rpp) { rpp = state.rpp; document.getElementById('rowsPerPage').value = state.rpp; }
                if (state.sortField) { sortState.field = state.sortField; sortState.dir = state.sortDir || 'asc'; }
                if (state.hideCompleted !== undefined) { hideCompleted = state.hideCompleted; document.getElementById('hideCompletedToggle').checked = hideCompleted; }
                return true;
            } catch (e) { return false; }
        }

        // ── Approval context card (§7.2) — shared by the Approve and Reject
        //    confirm modals. Header chips (id, code, slot validity —
        //    same badge classes/colours as the table), lecturer row, and an
        //    Original → Replacement comparison pair built from the SAME
        //    HtmlBuilder blocks the table uses, so the confirm step shows the
        //    decision cues without duplicating any formatting logic. ──
        /* opts.hideRequestId (2026-11-05, user): the Reject modal prints the id
           in its own title, so the card stops repeating it there; the Approve
           modal keeps it (its title carries no id). */
        function approvalCard(r, opts) {
            opts = opts || {};
            const conflict = r.slotValidity === 'conflict';
            const slotBadge = '<span class="slot-badge ' + (conflict ? 'slot-conflict' : 'slot-valid')
                + '" data-tip="' + escHtml(conflict ? (r.conflictReason || 'Slot conflict') : 'Slot available') + '">'
                + (conflict ? '⚠' : '✓') + '</span>';
            const lec = lookupLecturer(r.lecturer);
            const lecturerHtml = lec
                ? escHtml(lec.name) + ' <span class="lecturer-cell-id">(' + escHtml(lec.staffId) + ')</span>'
                : escHtml(r.lecturer);
            return ''
                + '<div class="approval-card">'
                +   '<div class="approval-card-head">'
                +     (opts.hideRequestId ? '' : '<span class="approval-id">#' + r.id + '</span>')
                +     '<span class="cell-code">' + escHtml(r.courseCode) + '</span>'
                +     slotBadge
                +   '</div>'
                +   '<div class="approval-course">' + escHtml(r.courseName) + ' <span class="approval-classtype">[' + escHtml(UiLabels.classType(r.classType, 'long')) + ']</span></div>'
                +   '<div class="approval-lecturer"><span>' + lecturerHtml + '</span></div>'
                +   '<div class="approval-compare-row">'
                +     '<div class="cmp-card cmp-original"><span class="cmp-label">Original</span>' + HtmlBuilder.classBlock(r, { originalCard: true }) + '</div>'
                +     '<span class="cmp-arrow" aria-hidden="true">→</span>'
                +     '<div class="cmp-card cmp-replacement"><span class="cmp-label">Replacement</span>' + HtmlBuilder.replacementBlock(r, { colorStatus: false }) + '</div>'
                +   '</div>'
                +   '<div class="approval-meta">'
                +     '<span class="meta-chip">' + r.duration + ' hr' + (r.duration > 1 ? 's' : '') + '</span>'
                +     '<span class="meta-chip">' + r.totalStudents + ' students</span>'
                +     (r.cohorts && r.cohorts.length ? '<span class="meta-chip">' + escHtml(r.cohorts.join(', ')) + '</span>' : '')
                +   '</div>'
                +   (conflict ? '<div class="approval-conflict">⚠ ' + escHtml(r.conflictReason || 'Slot conflict') + '</div>' : '')
                + '</div>';
        }

        // ── Sort state ──
        let sortState = { field: 'requestedAt', dir: 'asc' };
        let currentPage = 1;
        let rpp = 10;
        const RPP_OPTIONS = [10, 20, 50];

        // ── Feature state ──
        let bulk = new BulkSelection({ barId: 'bulkActionBar', countId: 'bulkCount' });
        let viewedIds = new Set();
        let currentApproveIds = [];
        let currentRejectId = null;
        let focusedRowIndex = -1;
        let isInitialLoad = true;
        let hideCompleted = true;

        // ── RPP (rows per page) ──
        function initLocalRpp() {
            const saved = localStorage.getItem('request-approval-rpp');
            if (saved && RPP_OPTIONS.includes(parseInt(saved))) {
                rpp = parseInt(saved);
                document.getElementById('rowsPerPage').value = saved;
            }
        }

        function toggleHideCompleted() {
            hideCompleted = document.getElementById('hideCompletedToggle').checked;
            currentPage = 1;
            renderTable();
            updateSummary();
            saveFilters();
        }

        // ── Table columns ──
        /* request-approval-ui CONSISTENCY (2026-11-03): Students removed, Cohort
           added before Original Class, `#` renamed to Request ID (it prints the
           REAL id), Lecturer renamed to Staff (Proposer), Proposed Replacement
           renamed to Requested Replacement. */
        const columns = [
            { label: 'Request ID', sortable: false, tip: 'The request identifier the lecturer sees (#) — not sortable' },
            { label: 'Requested Timestamp', sortable: true, field: 'requestedAt', tip: 'When the replacement was requested. Age colour: green ≤1 day, amber 2–3 days, red 4+ days' },
            { label: 'Staff (Proposer)', sortable: true, field: 'lecturer', tip: 'The staff member who submitted the request — sorted by name' },
            { label: 'Course', sortable: true, field: 'courseCode', tip: UiLabels.courseTipSubject },
            { label: 'Cohort', sortable: false, tip: 'Cohort(s) affected by this class — one per line; not sortable because a multi-cohort row has no single value' },
            { label: 'Original Slot', cls: 'col-original', sortable: true, field: 'classDate', tip: 'Original slot the request refers to — its state varies (still upcoming, replaced, cancelled, holiday, etc.)' },
            { label: 'Requested Slot', cls: 'col-replacement', sortable: true, field: 'replacementDate', tip: 'The new slot proposed for this class — date, time and venue' },
            { label: 'Request Status', sortable: true, field: 'status', tip: 'Current approval status' },
            { label: 'Actions', sortable: false, tip: 'Approve or reject this request' }
        ];

        // ── Filter state ──
        let currentFiltered = [];

        // ── Render table header ──
        // Shared house helper (cf. my-request-history) — flip + re-render live
        // in makeSortableHeader; this callback adds the page's own side effects.
        function renderHeader() {
            const thead = document.getElementById('tableHead');
            thead.innerHTML = '';
            const tr = document.createElement('tr');
            const thCheck = document.createElement('th');
            thCheck.className = 'col-checkbox';
            thCheck.innerHTML = '<input type="checkbox" id="selectAll" onchange="toggleSelectAll()">';
            tr.appendChild(thCheck);
            columns.forEach(function(col) {
                tr.appendChild(makeSortableHeader(col, sortState, function() {
                    currentPage = 1;
                    renderTable();
                    saveFilters();
                }));
            });
            thead.appendChild(tr);
        }

        // ── Sort logic ──
        function sortData(data) {
            const field = sortState.field;
            const dir = sortState.dir === 'asc' ? 1 : -1;
            return [...data].sort((a, b) => {
                let va, vb;
                va = a[field] || '';
                vb = b[field] || '';
                /* R-4b (round-3): lecturer stores an id — sort by resolved name */
                if (field === 'lecturer') {
                    va = (typeof lookupLecturer === 'function' && lookupLecturer(a.lecturer)) ? lookupLecturer(a.lecturer).name : a.lecturer;
                    vb = (typeof lookupLecturer === 'function' && lookupLecturer(b.lecturer)) ? lookupLecturer(b.lecturer).name : b.lecturer;
                }
                if (typeof va === 'string') return va.localeCompare(vb) * dir;
                return (va - vb) * dir;
            });
        }

        // ── Filter logic ──
        function filterData() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            const status = document.getElementById('statusFilter').value;
            const week = weekFilterValue('weekFilter');   /* '' is not a week */
            const origWeek = weekFilterValue('weekFilterOriginal');

            let result = MockData.approvalRequests.filter(r => {
                if (status !== 'all' && r.status !== status) return false;
                /* 2026-11-07 (user): the week filter on THIS page is about the
                   slot the PL is being asked to approve — the REQUESTED
                   REPLACEMENT date, not the original class date. A request whose
                   class sits in week 14 but whose proposal lands in week 7 belongs
                   to week 7 here. */
                if (week !== 'all' && !isInWeek(r.replacementDate, week)) return false;
                /* the ADDED original-week filter (design §4.3): the ORIGINAL class
                   date — composes (AND) with the requested-week test above. */
                if (origWeek !== 'all' && !isInWeek(r.classDate, origWeek)) return false;
                if (hideCompleted && r.status === 'Completed') return false;
                if (search) {
                    const haystack = (r.courseCode + ' ' + r.courseName + ' ' + r.lecturer).toLowerCase();
                    if (!haystack.includes(search)) return false;
                }
                return true;
            });

            return result;
        }

        // ── Render a single row ──
        function renderRow(r, offset, i) {
            const isSelected = bulk.has(r.id);
            const isViewed = viewedIds.has(r.id);
            let rowClass = '';
            if (isViewed) rowClass += ' row-viewed';
            if (isSelected) rowClass += ' row-selected';

            var lecName = (typeof lookupLecturer === 'function' && lookupLecturer(r.lecturer)) ? lookupLecturer(r.lecturer).name : r.lecturer;

            let html = '<tr data-id="' + r.id + '"' + (rowClass ? ' class="' + rowClass.trim() + '"' : '')
                + ' onclick="openModalById(' + r.id + ')" style="cursor:pointer">';

            // Checkbox column — stopPropagation so clicking it doesn't open the row modal
            if (r.status === 'Pending') {
                html += '<td class="col-checkbox"><input type="checkbox" ' + (isSelected ? 'checked' : '') + ' onchange="toggleRowSelect(' + r.id + ')" onclick="event.stopPropagation()"></td>';
            } else {
                html += '<td class="col-checkbox"></td>';
            }

            /* 1d: the table prints the REAL request id — the header says Request ID */
            html += '<td class="col-request-id">#' + r.id + '</td>';
            html += '<td>' + formatDateTime(r.requestedAt) + requestAgeHtml(r.requestedAt) + '</td>';
            /* the name alone (task 12, 2026-11-17): the staff ID and the copy-email line left the
               CELL, not just the paint — a CSS hide would keep both reachable by select-all and by
               a screen reader. The pop-up's Class Info tab prints the ID and the email chip, one
               click away, and `lecturer-cell-id` / `lecturer-cell-email` still have live callers
               (the confirm card and that same Class Info row), so their rules stay in the <style>.
               The wrapper stays with the name: it is the only consumer of the 600-weight rule. */
            var lec = lookupLecturer(r.lecturer);
            if (lec) {
                html += '<td><div class="lecturer-cell-name">' + lec.name + '</div></td>';
            } else {
                html += '<td>' + r.lecturer + '</td>';
            }
            /* 2026-11-09 (user, same day, second look): the CELL carries no hover
               text — the column is explained once, on its header.
               Task 12 (2026-11-17): one line, `CODE (TYPE)`. This cell GAINS the type — it
               printed none before — while every other cell in the lane loses something. The
               name lives in the pop-up's subtitle and is still searchable by the data array.
               CourseColumnTipTest matches the start of the next statement as text, so the
               `cell-code` div is opened inline with `r.courseCode` — do not hoist it. */
            html += '<td><div class="cell-code">' + r.courseCode
                + (r.classType ? ' (' + UiLabels.classType(r.classType, 'code') + ')' : '')
                + '</div></td>';
            /* 1b: one compact cohort code per line (`RSD3(S1)G2`), never a joined blob —
               the modal prints the same codes joined with ' + '. */
            var cohortCodes = (r.cohorts || []).map(compactCohort);
            html += '<td class="col-cohort">' + (cohortCodes.length ? cohortCodes.join('<br>') : '—') + '</td>';
            html += '<td class="col-original">' + HtmlBuilder.classBlock(r, { merged: true, venueCodeOnly: true }) + '</td>';

            // Proposed Replacement — slot validity as a status-colored badge beside the venue
            const slotBadge = '<span class="slot-badge ' + (r.slotValidity === 'conflict' ? 'slot-conflict' : 'slot-valid') + '" data-tip="' + (r.slotValidity === 'conflict' ? (r.conflictReason || 'Slot conflict') : 'Slot available') + '">' + (r.slotValidity === 'conflict' ? '⚠' : '✓') + '</span>';
            /* 2026-11-07 (user): the requested column prints how soon a PENDING
               make-up starts (working-day countdown beside the slot's date). The
               column has no urgency badge any more — this is the one number. */
            html += '<td class="col-replacement">' + HtmlBuilder.replacementBlock(r, { colorStatus: false, merged: true, venueCodeOnly: true, slotBadge: slotBadge, pendingCountdown: true }) + '</td>';

            html += '<td><span class="badge ' + statusClass(r.status) + '"' + statusTipAttr(r) + ' onclick="openModalById(' + r.id + ')" style="cursor:pointer">' + r.requestStatusLabel + '</span></td>';

            const actions = r.status === 'Pending'
                ? '<div class="action-row"><button class="btn-approve" onclick="event.stopPropagation();approveRequest(' + r.id + ')">✓ Approve</button><button class="btn-reject" onclick="event.stopPropagation();openRejectModal(' + r.id + ')">✕ Reject</button></div>'
                : '<button class="btn-view" onclick="event.stopPropagation();openModalById(' + r.id + ')">👁 View</button>';
            html += '<td>' + actions + '</td>';
            html += '</tr>';
            return html;
        }

        function renderEmptyQueue(total) {
            const noPending = total === 0
                && document.getElementById('statusFilter').value === 'Pending'
                && document.getElementById('searchInput').value === ''
                && weekFilterValue('weekFilter') === 'all'
                && weekFilterValue('weekFilterOriginal') === 'all';
            document.getElementById('emptyTitle').textContent = noPending ? 'No pending requests' : 'No requests found';
            document.getElementById('emptyText').textContent = noPending
                ? 'There are no requests waiting for your review.'
                : 'No requests match your search or filter criteria.';
            document.getElementById('viewAllRequests').hidden = !noPending || MockData.approvalRequests.length === 0;
        }

        // ── Render table body ──
        function renderBody(sorted) {
            const tbody = document.getElementById('tableBody');
            const offset = (currentPage - 1) * rpp;
            const page = sorted.slice(offset, offset + rpp);

            if (page.length === 0) {
                tbody.innerHTML = '';
                renderEmptyQueue(sorted.length);
                document.getElementById('emptyState').style.display = '';
                document.getElementById('gridWrapper').style.display = 'none';
                document.getElementById('paginationBar').style.display = 'none';
                syncSummarySection(false);
                return;
            }

            document.getElementById('emptyState').style.display = 'none';
            document.getElementById('gridWrapper').style.display = '';
            document.getElementById('paginationBar').style.display = '';
            syncSummarySection(true);

            let html = '';
            page.forEach((r, i) => {
                html += renderRow(r, offset, i);
            });
            tbody.innerHTML = html;

            updateBatchBar();
            highlightRow();
        }

        // ── Update summary cards ──
        function updateSummary() {
            const total = currentFiltered.length;
            const pending = currentFiltered.filter(r => r.status === 'Pending').length;
            const approved = currentFiltered.filter(r => r.status === 'Approved').length;
            const rejected = currentFiltered.filter(r => r.status === 'Rejected').length;
            const reviewed = currentFiltered.filter(r => ['Approved', 'Rejected', 'Completed'].includes(r.status)).length;

            document.getElementById('summaryTotal').textContent = total;
            document.getElementById('summaryPending').textContent = pending;
            document.getElementById('summaryApproved').textContent = approved;
            document.getElementById('summaryRejected').textContent = rejected;
            document.getElementById('summaryReviewed').textContent = reviewed;
        }

        // ── Update pagination ──
        function updatePagination() {
            const total = currentFiltered.length;
            const totalPages = Math.ceil(total / rpp);
            const info = document.getElementById('paginationInfo');
            const controls = document.getElementById('paginationControls');

            if (total === 0) {
                info.textContent = 'No results';
                controls.innerHTML = '';
                return;
            }

            const start = (currentPage - 1) * rpp + 1;
            const end = Math.min(currentPage * rpp, total);
            info.textContent = 'Showing ' + start + '-' + end + ' of ' + total;

            let html = '';
            if (currentPage > 1) {
                html += '<button class="page-btn" onclick="goToPage(' + (currentPage - 1) + ')">‹</button>';
            }
            for (let p = 1; p <= totalPages; p++) {
                html += '<button class="page-btn' + (p === currentPage ? ' active' : '') + '" onclick="goToPage(' + p + ')">' + p + '</button>';
            }
            if (currentPage < totalPages) {
                html += '<button class="page-btn" onclick="goToPage(' + (currentPage + 1) + ')">›</button>';
            }
            controls.innerHTML = html;
        }

        function goToPage(page) {
            currentPage = page;
            renderBody(sortData(currentFiltered));
            updatePagination();
        }

        /* ── Update result count — the page-local shadow DELETED (SDD
           ra-result-count-showing-of): renderTable() now calls the SHARED
           ui-common.js updateResultCount with the same cfg my-request-history
           uses — the identical 'Showing X of Y results' format. ── */

        // ── Active filter chips ──
        function renderFilterChips() {
            const container = document.getElementById('filterChips');
            const chips = [];
            const status = document.getElementById('statusFilter').value;
            const search = document.getElementById('searchInput').value.trim();
            /* 2026-11-11: an unselected week box is NOT a week (weekFilterValue) */
            const week = weekFilterValue('weekFilter');
            const origWeek = weekFilterValue('weekFilterOriginal');

            if (status !== 'all') {
                chips.push('<span class="filter-chip">Status: ' + status + '<button class="filter-chip-remove" onclick="document.getElementById(\'statusFilter\').value=\'all\';renderTable()" data-tip="Remove">&times;</button></span>');
            }
            /* The ADDED original week (design §4.4): inserted BEFORE the requested
               one so the chips follow the panel's field order — Status · Original ·
               Requested · Search · exclusion · Clear all. */
            if (origWeek !== 'all') {
                const origWeekLabel = weekFilterLabel('weekFilterOriginal') || origWeek;
                chips.push('<span class="filter-chip">Original Slot Week: ' + origWeekLabel + '<button class="filter-chip-remove" onclick="document.getElementById(\'weekFilterOriginal\').value=\'all\';onOrigWeekFilterChange()" data-tip="Remove">&times;</button></span>');
            }
            if (week !== 'all') {
                const weekLabel = weekFilterLabel('weekFilter') || week;
                chips.push('<span class="filter-chip">Requested Slot Week: ' + weekLabel + '<button class="filter-chip-remove" onclick="document.getElementById(\'weekFilter\').value=\'all\';onWeekFilterChange()" data-tip="Remove">&times;</button></span>');
            }
            if (search) {
                chips.push('<span class="filter-chip">Search: "' + search + '"<button class="filter-chip-remove" onclick="document.getElementById(\'searchInput\').value=\'\';renderTable()" data-tip="Remove">&times;</button></span>');
            }
            if (hideCompleted) {
                chips.push('<span class="filter-chip">Completed hidden<button class="filter-chip-remove" onclick="document.getElementById(\'hideCompletedToggle\').checked=false;toggleHideCompleted()" data-tip="Remove">&times;</button></span>');
            }
            if (chips.length > 0) {
                chips.push('<button type="button" class="filter-chip filter-chip-clear-all" data-tip="Remove every active filter" onclick="clearAllFilters()">Clear all</button>');
            }
            /* No prefix label (design §2.1): the row is named by the button. */
            container.innerHTML = chips.join('');
        }

        // ── Main render ──
        function renderTable() {
            currentFiltered = filterData();
            const sorted = sortData(currentFiltered);
            renderHeader();
            /* survey finding B3: reassigned ONCE, before the first render */
            wrapRequestCard();
            renderBody(sorted);
            updateSummary();
            updatePagination();
            updateResultCount({ elId: 'resultCount', data: currentFiltered, total: MockData.approvalRequests.length, label: 'results' });
            renderCards(sorted);
            renderFilterChips();
        }

        /* ══ the phone card, ported from the drawing (2026-11-17) ══════════
           `ui-prototype/my-replacements.html` carries this as `wrapRequestCard`, a
           page-local wrapper around the shared `HtmlBuilder.requestCard`. Ported the
           same way: this page reassigns its own renderer, no other surface moves. */
        function shortRange(a, b) {
            var m = /^(\d{1,2}:\d{2})\s*(AM|PM)$/i.exec(a);
            var n = /^(\d{1,2}:\d{2})\s*(AM|PM)$/i.exec(b);
            if (!m || !n) return a + ' \u2013 ' + b;
            return m[2].toUpperCase() === n[2].toUpperCase()
                ? m[1] + '\u2013' + n[1] + ' ' + m[2].toUpperCase()
                : a + ' \u2013 ' + b;
        }

        function wrapRequestCard() {
            if (window.__clReqCardWrap) return;
            if (typeof HtmlBuilder === 'undefined' || !HtmlBuilder.requestCard) return;
            window.__clReqCardWrap = true;

            var original = HtmlBuilder.requestCard.bind(HtmlBuilder);

            HtmlBuilder.requestCard = function (r, opts) {
                if (!r.replacementDate) return original(r, opts);

                var iso = r.replacementDate;
                var parts = String(r.replacementTime || '').split(/\s*[\u2013\u2014-]\s*/);
                var range = parts.length > 1
                    ? shortRange(DateHelper.to12h(parts[0]), DateHelper.to12h(parts[1]))
                    : (r.replacementTime || '');
                // The desktop requested slot also counts down only while Pending.
                var countdown = '';
                if (r.status === 'Pending') {
                    var days = workingDaysDiff(iso);
                    countdown = '<span class="cl-rc-urg ' + urgencyBandClass(days) + '">' +
                        daysLeftLabel(days) + '</span>';
                }
                var statusTip = r.requestStatusTip
                    ? ' data-tip="' + String(r.requestStatusTip).replace(/"/g, '&quot;') + '"'
                    : '';
                /* one calculation: the page's own age chip, re-skinned inline */
                var m = /class="request-age ([a-z-]+)"[^>]*>([^<]*)</.exec(requestAgeHtml(r.requestedAt));
                var ageHtml = m ? '<span class="cl-req-age ' + m[1] + '">' + m[2] + '</span>' : '';
                var slot = r.slotValidity === 'conflict'
                    ? '<span class="slot-badge slot-conflict" data-tip="This make-up slot is NO LONGER FREE">\u26a0</span>'
                    : '<span class="slot-badge slot-valid" data-tip="Slot available">\u2713</span>';

                return '<div class="cl-rc-head">' +
                    '<span class="cl-id-pill">#' + r.id + '</span>' +
                    '<span class="rc-code">' + (r.lecturer || '') + '</span>' +
                    '<span class="badge ' + statusClass(r.status) + '"' + statusTip + '>' +
                      (r.requestStatusShort || r.status) + '</span>' +
                  '</div>' +
                  '<div class="cl-rc-slot">' +
                    '<div class="cl-rc-label">Requested' +
                      '<span class="cl-week-pill">Week ' + getWeekNumber(iso) + '</span></div>' +
                    '<div class="cl-rc-grid">' +
                      '<span class="cl-rc-date">' +
                        DateHelper.dayAbbr(DateHelper.weekdayName(iso)) + ', ' +
                        formatDate(iso) + '</span>' +
                      countdown +
                      '<span class="cl-rc-time">' + range + '</span>' +
                      '<span class="cl-rc-venue">' + (r.replacementVenue || '') + slot + '</span>' +
                    '</div>' +
                  '</div>' +
                  '<div class="cl-rc-foot">' +
                    '<span class="cl-rc-course">' + r.courseCode +
                      ' <span class="cell-type-label">(' + (r.classType || '') + ')</span></span>' +
                    '<span class="cl-rc-meta">' + ageHtml + '</span>' +
                  '</div>';
            };
        }

        // ── Render cards (mobile responsive view) ──
        function renderCards(sorted) {
            const container = document.getElementById('requestsCards');
            if (!container) return;
            container.innerHTML = '';
            sorted.forEach(function(r) {
                const card = document.createElement('div');
                card.className = 'request-card';
                card.setAttribute('data-id', r.id);
                card.innerHTML = HtmlBuilder.requestCard(r, {
                    lookupLecturer: lookupLecturer,
                    requestAgeHtml: requestAgeHtml,
                    statusTip: function() { return r.requestStatusTip; }
                });
                card.addEventListener('click', function() { openModalById(r.id); });
                container.appendChild(card);
            });
        }

        // ── Bulk selection (§7.1) ──
        function toggleSelectAll() {
            const visible = currentFiltered.filter(r => r.status === 'Pending');
            if (bulk.size === visible.length) { bulk.clear(); }
            else { bulk.setAll(visible.map(r => r.id)); }
            renderTable();
        }

        function toggleRowSelect(id) {
            bulk.toggle(id);
            renderTable();
        }

        function updateBatchBar() {
            bulk.updateBar();
        }

        function clearSelection() {
            bulk.clear();
            renderTable();
        }

        function bulkApprove() {
            const ids = [...bulk.ids];
            openApproveNotesModal(ids);
        }

        function bulkReject() {
            currentRejectId = null;
            setRejectModalTitle(null);
            document.getElementById('rejectReasonInput').value = '';
            document.getElementById('confirmRejectBtn').disabled = true;
            document.getElementById('rejectReasonModal').classList.add('show');
            document.getElementById('rejectReasonInput').focus();
        }

        // ── Reject presets (§7.3) ──
        function applyRejectPreset(text) {
            const input = document.getElementById('rejectReasonInput');
            input.value = text;
            updateRejectConfirmState();
            input.focus();
        }

        // ── Week filter change handler ──
        function onWeekFilterChange(value) {
            if (typeof pageState !== 'undefined' && pageState) pageState.currentPage = 1;
            currentPage = 1;
            saveFilters();
            renderTable();
            updateWeekArrowState('weekFilter');
        }

        /* the ADDED original-week field (design §4.3) — same shape as
           onWeekFilterChange(), scoped to its own nav's arrows. */
        function onOrigWeekFilterChange(value) {
            if (typeof pageState !== 'undefined' && pageState) pageState.currentPage = 1;
            currentPage = 1;
            saveFilters();
            renderTable();
            updateWeekArrowState('weekFilterOriginal');
        }

        // ── Modal ──
        let currentModalId = null;

        function openModalById(id) {
            const r = MockData.approvalRequests.find(x => x.id === id);
            if (!r) return;
            currentModalId = r.id;
            viewedIds.add(r.id);

            /* ── 3A (2026-11-04): the timeline is GONE (3D needs the tab room, and
               the rail is known-broken — see the demo note it used to print).
               Its information is not lost: the old Request Info tab is absorbed
               by the fixed facts strip below, which is the merged page's M7
               shell. ── */

            /* The facts strip — the four things a PL decides with. */
            var modalLec = lookupLecturer(r.lecturer);
            var staffFact = modalLec
                /* 2026-11-17 (task 10, decision 5): the strip carries the proposer's NAME
                   only. The staff ID and the email chip moved into Class Info, so the strip
                   stays one badge-led fact per row and the footer's copy-email affordance
                   stopped living in a facts strip. */
                ? '<span class="df-item df-item--full"><span class="df-label">Staff (Proposer)</span>' + modalLec.name + '</span>'
                : '<span class="df-item df-item--full"><span class="df-label">Staff (Proposer)</span>' + (r.lecturer || '\u2014') + '</span>';

            /* the slot-validity badge: the strip shows it while the slot still
               matters (pending); the replacement tab always shows it. */
            var slotBadgeInline = '<span class="slot-badge ' + (r.slotValidity === 'conflict' ? 'slot-conflict' : 'slot-valid') + '" data-tip="' +
                ((r.slotValidity === 'conflict' ? (r.conflictReason || 'Slot conflict') : 'Slot available').replace(/"/g, '&quot;')) + '">' +
                (r.slotValidity === 'conflict' ? '⚠' : '✓') + '</span> ';
            var conflictFact = r.status === 'Pending'
                ? '<span class="df-item df-item--full"><span class="df-label">Slot Validity</span>' +
                    slotBadgeInline + (r.slotValidity === 'conflict' ? 'This make-up slot is NO LONGER FREE' : 'Slot available') + '</span>'
                : '';

            /* one badge-led fact per row (decision 3): Staff (Proposer) · Status ·
               Slot Validity. Cohorts and Students moved to Class Info; the last row is
               Slot Validity, not Conflict — it is the slot's state, and the old label
               claimed to be the conflict reason. */
            var factsHtml =
                staffFact +
                '<span class="df-item"><span class="df-label">Status</span>' +
                    '<span class="badge ' + statusClass(r.status) + '"' + statusTipAttr(r) + '>' +
                    r.requestStatusLabel + '</span></span>' +
                conflictFact;

            // (the old "Request Information" tab is the facts strip above now)

            // ── USER 2026-10-18: CONSISTENT HUMAN DATES ('17 Oct 2026' style),
            //    hour words, and the already-split Start/End rows (below) ──
            function fullDate(iso) {
                if (!iso) return '—';
                const m = String(iso).match(/(\d{4})-(\d{2})-(\d{2})/);
                if (!m) return iso;
                const months =
                    ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                return parseInt(m[3]) + ' ' + months[parseInt(m[2]) - 1] + ' ' + m[1];
            }
            function fullTime(t) {
                if (!t) return '—';
                const s = String(t);
                return DateHelper.to12h(/\d/.test(s) && s.length >= 4 ? s.slice(0, 5) : s);
            }
            function fullDay(iso) {
                if (!iso) return '—';
                const m = String(iso).match(/(\d{4})-(\d{2})-(\d{2})/);
                if (!m) return '—';
                return ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][new Date(parseInt(m[1]), parseInt(m[2]) - 1, parseInt(m[3])).getDay()];
            }
            function durationWords(d) {
                const n = Number(d);
                return (n === 1 ? '1 hour' : n + ' hours');
            }
            /* the countdown's VALUE only — the row label says "Days Left", and the
               words/colours come from the one shared pair (2026-11-17, task 10) */
            function workingDaysSpan(iso) {
                const d = workingDaysDiff(iso);
                return '<span class="' + urgencyBandClass(d) + '">' + daysLeftLabel(d) + '</span>';
            }
            /* the Week row: semester start + 7×n — the payload carries no week key */
            function weekOfIso(iso) {
                if (!iso) return null;
                const start = (window.MockData && MockData.semester && MockData.semester.startDate) || null;
                if (!start) return null;
                const a = new Date(start + 'T00:00:00');
                const b = new Date(String(iso).slice(0, 10) + 'T00:00:00');
                if (isNaN(a.getTime()) || isNaN(b.getTime())) return null;
                const n = Math.floor(Math.round((b - a) / 86400000) / 7) + 1;
                const weeks = (MockData.semester && MockData.semester.weeks) || 14;
                return (n >= 1 && n <= weeks) ? 'Week ' + n : null;
            }
            /* 'H:i – H:i' → hours (a NUMBER, so durationWords owns the wording).
               2026-11-10 (#4): it must never return NaN — a 12-hour string or a
               missing separator degrades to null (no "(NaN minutes)" ever). */
            function rangeHours(t) {
                const m = String(t || '').match(/(\d{1,2}):(\d{2})\s*(?:AM|PM)?\s*[–—-]\s*(\d{1,2}):(\d{2})/i);
                if (!m) return null;
                const mins = (parseInt(m[3]) * 60 + parseInt(m[4])) - (parseInt(m[1]) * 60 + parseInt(m[2]));
                if (!isFinite(mins) || mins <= 0) return null;
                return Math.round((mins / 60) * 10) / 10;
            }
            /* BMSE3153-class-info (user law 2026-10-27): the shared
               cohortBreakdown (ui-common.js) renders the per-cohort
               `A + B = total` breakdown for multi-cohort rows. */

            // Tab 1: Original Slot Schedule — one value per row, no grouping
            // CONSISTENCY 3E (2026-11-03): the Week row is derived from the date
            // (semester start + 7×n), so the payload needs no new key.
            // 3A (2026-11-04): students + cohorts moved to the facts strip.
            // 3E-3 (2026-11-04): start/end collapse into ONE `Time` row, the
            // merged page's shape.
            /* 2026-11-10 (user, modal consistency #4): Day & Date and Time & Duration
               are ONE row each — the same wording as the class/slot modals. */
            const origSection = DetailModal.section('Original Slot Schedule',
                DetailModal.row('Request ID', '#' + r.id, { strong: true }) +
                DetailModal.row('Week', weekOfIso(r.classDate)) +
                DetailModal.row('Day & Date', ModalFacts.dayDate(r.classDay, fullDate(r.classDate)), { strong: true }) +
                DetailModal.row('Time & Duration', ModalFacts.timeDuration(fullTime(r.timeStart), fullTime(r.timeEnd), r.duration), { strong: true }) +
                DetailModal.row('Venue', venueText(r.venue, r.venueName))
            );

            // Tab 2: Requested Slot Schedule — only when a slot was
            // proposed (3D). The replacementTime carries "H:i – H:i".
            const repParts = (r.replacementTime || '').split(/\s*[–—-]\s*/);
            /* 2026-11-10 (user ruling B7): the badge already PAINTS the glyph —
               `slotBadgeInline` is a green tick / red warning circle — so the
               words beside it must not repeat it ("✓ ✓ Slot available") */
            const validityText = r.slotValidity === 'conflict'
                ? 'This make-up slot is NO LONGER FREE' + (r.conflictReason ? ' — ' + r.conflictReason : '')
                : 'Slot available';

            /* 2026-11-07 (user, "Modal Tab Order & Class Type Display"): the
               Requested Slot Schedule is the LEFT-MOST tab and the one the
               modal opens on — it is the thing the PL is being asked to approve.
               `DetailModal.render` activates index 0, so the order IS the default.
               A row with no proposal keeps Original first: the tab cannot be
               invented for a request that never proposed a slot. */
            const tabs = [];

            if (r.replacementDate) {
                const repSection = DetailModal.section('Requested Slot Schedule',
                    DetailModal.row('Week', weekOfIso(r.replacementDate)) +
                    DetailModal.row('Day & Date', ModalFacts.dayDate(fullDay(r.replacementDate), fullDate(r.replacementDate)), { strong: true }) +
                    /* 2026-11-17 (task 10, decision 7): this pop-up never had a countdown;
                       it gets one as its own row, while the request is still pending */
                    (r.status === 'Pending' || r.status === 'pending'
                        ? DetailModal.row('Days Left', workingDaysSpan(r.replacementDate))
                        : '') +
                    DetailModal.row('Time & Duration', ModalFacts.timeDuration(
                        repParts.length ? DateHelper.to12h(repParts[0]) : null,
                        repParts.length > 1 ? DateHelper.to12h(repParts[1]) : null,
                        rangeHours(r.replacementTime)
                    ), { strong: true }) +
                    DetailModal.row('Venue', venueText(r.replacementVenue, r.replacementVenueName)) +
                    DetailModal.row('Slot Validity', slotBadgeInline + validityText)
                );
                tabs.push({ key: 'replacement', label: 'Requested', html: repSection });
            }

            tabs.push({ key: 'original', label: 'Original', html: origSection });

            /* Tab 3 — the status, FAR RIGHT (3D): badge + ONE sentence, then the
               PL-only facts the old Request Info / Review tabs used to hold. */
            /* 2026-11-17 (task 10, decision 4): Class Info, before Status. The PL's own
               block carries the proposer, which the staff pop-up does not have. */
            tabs.push({
                key: 'class-info',
                label: 'Class Info',
                html: DetailModal.section('Class Info',
                    /* decision 5: the ID and the email live HERE, in Class Info — the
                       same lookup the strip used, so the chip keeps its copy-email tip */
                    DetailModal.row('Staff (Proposer)',
                        (modalLec ? modalLec.name : (r.lecturer || '')) +
                        (modalLec && modalLec.staffId
                            ? ' <span class="detail-value--muted">(' + modalLec.staffId + ')</span>'
                            : '') +
                        (modalLec && modalLec.email
                            ? ' <span class="lecturer-cell-email" onclick="copyEmail(\'' + modalLec.email + '\', event)" data-tip="Click to copy email">' + modalLec.email + ' <span class="copy-icon">📋</span></span>'
                            : '')) +
                    DetailModal.row('Cohorts', (r.cohorts || []).map(compactCohort).join(' + ') || '\u2014') +
                    DetailModal.row('Students', window.cohortBreakdown ? window.cohortBreakdown(r) : r.totalStudents)
                )
            });

            const statusHtml =
                '<div class="detail-row"><span class="detail-label">Status</span><span class="detail-value">' +
                    '<span class="badge ' + statusClass(r.status) + '"' + statusTipAttr(r) + '>' + r.requestStatusLabel + '</span>' +
                '</span></div>' +
                DetailModal.row('Status Description', r.requestStatusSentence || '') +
                DetailModal.row('Requested At', formatDateTime(r.requestedAt)) +
                (r.rejectionReason ? DetailModal.row('Rejection Reason', r.rejectionReason, { strong: true }) : '') +
                (r.remarks ? DetailModal.row('Remark', r.remarks) : '') +
                (r.reviewedBy ? DetailModal.row('Reviewed By', r.reviewedBy) : '') +
                (r.reviewedAt ? DetailModal.row('Reviewed At', formatDateTime(r.reviewedAt)) : '');
            tabs.push({
                key: 'status',
                label: 'Status',
                html: DetailModal.section('Status', statusHtml)
            });

            DetailModal.render({
                modalId: 'modalOverlay',
                title: 'Request Details',
                /* 2026-11-07 (user): the class TYPE is part of the identity —
                   `#28 · AMCS2093 — Operating Systems (Lecture)`, full word, from
                   the request's own session (`classType`, the mapper's classRow
                   key). `UiLabels.classType(…, 'long')` is the one label law, so
                   the modal cannot drift from the table card's `[Lecture]`. */
                /* 2026-11-10 (user ruling B5): the subtitle carries the SUBJECT only —
                   the request's own number was repeated there and again as the
                   "Request ID" row, so it lives in the Request Status tab alone. */
                subtitle: r.courseCode + ' \u2014 ' + r.courseName
                    + (r.classType ? ' (' + UiLabels.classType(r.classType, 'long') + ')' : ''),
                factsHtml: factsHtml,
                tabs: tabs
            });

            // Toggle footer buttons — Reject sits bottom-LEFT, Approve bottom-RIGHT
            document.getElementById('rejectRequestBtn').style.display = r.status === 'Pending' ? '' : 'none';
            document.getElementById('approveRequestBtn').style.display = r.status === 'Pending' ? '' : 'none';

            renderTable();
        }

        function openModal(index) {
            const r = currentFiltered[index];
            if (!r) return;
            openModalById(r.id);
        }

        function closeModal() {
            DetailModal.close();
            currentModalId = null;
        }

        // ── Approve (§7.6 — approval notes modal) ──
        function approveRequest(id) {
            closeModal();               // auto-close Request Details when acting from it
            openApproveNotesModal([id]);
        }

        function openApproveNotesModal(ids) {
            currentApproveIds = ids;
            const rows = ids.map(function(id) {
                return MockData.approvalRequests.find(function(x) { return x.id === id; });
            }).filter(Boolean);
            document.getElementById('approveNotesTitle').textContent =
                ids.length === 1 ? 'Approve Request' : 'Approve ' + ids.length + ' Requests';
            document.getElementById('approveNotesSummary').innerHTML = rows.map(function(row) { return approvalCard(row); }).join('');
            document.getElementById('approveNotesInput').value = '';
            document.getElementById('approveNotesModal').classList.add('show');
            document.getElementById('approveNotesInput').focus();
        }

        function confirmApproveWithNotes() {
            const notes = document.getElementById('approveNotesInput').value.trim();
            const ids = currentApproveIds;
            const label = ids.length === 1 ? 'Request #' + ids[0] : ids.length + ' requests';
            const notesLine = notes ? '\nNotes: ' + notes : '';
            closeApproveNotesModal();
            /* D12 (SDD): NO undo — decisions are final server-side; bulk =
               sequential FCFS via the real ApproveRequest action; stale rows
               fail politely and their toast carries the conflict text. */
            pageLivewire().call('approve', JSON.stringify(ids), notes).then(function(res) {
                applyDecisionResponse(res, label + ' approved.' + notesLine);
            });
        }

        function closeApproveNotesModal() {
            document.getElementById('approveNotesModal').classList.remove('show');
            currentApproveIds = [];
        }

        // ── Reject modal ──
        /* UI/UX update 2026-11-05 (user): the Request ID now lives in the modal
           title (`Rejection Reason (#28)`), taken from the request being acted
           on. Bulk reject has no single ID, so its title stays plain. */
        function setRejectModalTitle(id) {
            const title = document.getElementById('rejectReasonTitle');
            if (title) title.textContent = (id == null) ? 'Reject Requests' : 'Reject Request (#' + id + ')';
        }

        function openRejectModal(id) {
            closeModal();               // auto-close Request Details when acting from it
            currentRejectId = id;
            setRejectModalTitle(id);
            const r = MockData.approvalRequests.find(function(x) { return x.id === id; });
            document.getElementById('rejectSummary').innerHTML = r ? approvalCard(r, { hideRequestId: true }) : '';
            document.getElementById('rejectReasonInput').value = '';
            document.getElementById('confirmRejectBtn').disabled = true;
            document.getElementById('rejectReasonModal').classList.add('show');
            document.getElementById('rejectReasonInput').focus();
        }

        function updateRejectConfirmState() {
            const hasReason = document.getElementById('rejectReasonInput').value.trim().length > 0;
            document.getElementById('confirmRejectBtn').disabled = !hasReason;
        }

        function rejectRequest() {
            const reason = document.getElementById('rejectReasonInput').value.trim();
            if (!reason) {
                toast.show('Please provide a rejection reason.', null, 3000);
                return;
            }
            const isBulk = currentRejectId === null;
            const ids = isBulk ? [...bulk.ids] : [currentRejectId];
            const label = isBulk ? ids.length + ' request(s)' : 'Request #' + currentRejectId;
            closeRejectModal();
            /* D12 (SDD): NO undo; ONE shared reason covers the whole batch. */
            pageLivewire().call('reject', JSON.stringify(ids), reason).then(function(res) {
                applyDecisionResponse(res, label + ' rejected.');
            });
        }

        function closeRejectModal() {
            document.getElementById('rejectReasonModal').classList.remove('show');
            currentRejectId = null;
        }

        /* Wire-call response: swap the payload rows + report per-row outcomes.
           Success rows leave the list (the server state already flipped);
           conflict rows toast their conflict strings politely (Q1). */
        function applyDecisionResponse(res, label) {
            if (res && Array.isArray(res.requests)) {
                MockData.approvalRequests = res.requests;
                window.PAGE_DATA.requests = res.requests;
                window.PENDING_NAV_COUNT = res.requests.filter(function(r) { return r.status === 'Pending'; }).length;
            }
            bulk.clear();
            renderTable();
            updateNavBadge(window.PENDING_NAV_COUNT);
            const conflicts = (res && Array.isArray(res.results)) ? res.results.filter(r => !r.ok) : [];
            if (conflicts.length === 0) {
                toast.show(label, null, 3000);
                return;
            }
            const first = conflicts[0];
            const more = conflicts.length > 1 ? ' (+' + (conflicts.length - 1) + ' more)' : '';
            toast.show('Request #' + first.id + ' — ' + (first.conflict_reason || 'Slot conflict') + more, 'error', 5000);
        }

        // ── Review next auto-advance (§7j) ──
        function reviewNextAfterAction(actedOnId) {
            const actedIndex = actedOnId ? currentFiltered.findIndex(r => r.id === actedOnId) : -1;
            let nextIndex = -1;
            for (let i = actedIndex + 1; i < currentFiltered.length; i++) {
                if (currentFiltered[i].status === 'Pending') { nextIndex = i; break; }
            }
            if (nextIndex >= 0) {
                focusedRowIndex = nextIndex;
                openModalById(currentFiltered[nextIndex].id);
            } else {
                focusedRowIndex = -1;
                closeModal();
            }
        }

        // ── Keyboard shortcuts (§7i) ──
        function highlightRow() {
            const rows = document.querySelectorAll('#tableBody tr');
            rows.forEach((tr, i) => {
                tr.classList.toggle('row-focused', i === focusedRowIndex);
            });
        }

        // ── Populate week filter ──
        function populateWeekFilter() {
            populateWeekSelect('weekFilter', {
                includeAll: true,
                allLabel: 'All Weeks',
                labelFn: function(w) { return w.label; }
            });
            populateWeekSelect('weekFilterOriginal', {
                includeAll: true,
                allLabel: 'All Weeks',
                labelFn: function (w) { return w.label; }
            });
        }

        /* ── Reset filters ── the #resetFilters button's own law (tip:
           'Reset all filters to DEFAULT') — the PAGE DEFAULTS keep the
           default exclusions (status Pending, hideCompleted on) by design. */
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'Pending';
            document.getElementById('weekFilter').value = 'all';
            document.getElementById('weekFilterOriginal').value = 'all';
            rpp = 10;
            document.getElementById('rowsPerPage').value = '10';
            hideCompleted = true;
            document.getElementById('hideCompletedToggle').checked = true;
            bulk.clear();
            viewedIds.clear();
            currentPage = 1;
            sortState = { field: 'requestedAt', dir: 'asc' };
            localStorage.removeItem('request-approval-filters');
            renderTable();
            updateWeekArrowState('weekFilter');
            updateWeekArrowState('weekFilterOriginal');
        }

        /* SDD filter-clear-all-off (the user's 2026-10-30 ruling): the chips
           bar "Clear all" = EVERY filter OFF — unlike resetFilters, the
           status goes to 'all' (not the default 'Pending') and the
           hideCompleted exclusion turns OFF (BOTH statements — the variable
           the chip branch reads + the checkbox the ✕ sets) — so the bar
           renders EMPTY (no chips, no prefix label). Chips
           disappear because the filters ARE off, never a visual hide. */
        function clearAllFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('weekFilter').value = 'all';
            document.getElementById('weekFilterOriginal').value = 'all';
            rpp = 10;
            document.getElementById('rowsPerPage').value = '10';
            hideCompleted = false;
            document.getElementById('hideCompletedToggle').checked = false;
            bulk.clear();
            viewedIds.clear();
            currentPage = 1;
            sortState = { field: 'requestedAt', dir: 'asc' };
            localStorage.removeItem('request-approval-filters');
            renderTable();
            updateWeekArrowState('weekFilter');
            updateWeekArrowState('weekFilterOriginal');
        }

        /* the inline onclick resolves via the top-level scope chain anyway —
           exported explicitly so the dependency is visible (mirrors MRH). */
        window.clearAllFilters = clearAllFilters;
        window.resetFilters = resetFilters;
        window.onOrigWeekFilterChange = onOrigWeekFilterChange;

        // ── Deep link support ──
        function checkDeepLink() {
            const params = new URLSearchParams(window.location.search);
            const id = parseInt(params.get('id'));
            if (!id) return;
            const r = MockData.approvalRequests.find(function(x) { return x.id === id; });
            if (!r) {
                toast.show('Request #' + id + ' not found', 'error');
                return;
            }
            openModalById(id);
        }

        // ── DOMContentLoaded ──
        document.addEventListener('DOMContentLoaded', function() {
            /* ── REAL-DATA BRIDGE (SDD span-aware-approval-flow T9): the
               component's payload replaces the mock data source; the frozen
               template stays byte-for-byte. (The my-request-history pattern —
               inside DOMContentLoaded because this file parses before the
               layout's mock-data.js / ui-common.js.) ── */
            MockData.semester = window.PAGE_DATA?.semester ?? MockData.semester;
            MockData.holidays = window.PAGE_DATA?.holidays ?? MockData.holidays;
            MockData.currentUser = window.PAGE_DATA?.currentUser ?? MockData.currentUser;
            MockData.approvalRequests = Array.isArray(window.PAGE_DATA?.requests) ? window.PAGE_DATA.requests : MockData.approvalRequests;
            MockData.lecturers = Array.isArray(window.PAGE_DATA?.lecturers) ? window.PAGE_DATA.lecturers : MockData.lecturers;
            /* 2026-11-07: the requested-column countdown reads the REAL today on
               this live page (mock-data.js pins mockNow to Mon 5 Oct 2026 for the
               frozen template). Same line every other live page carries. */
            MockData.mockNow = null;
            populateWeekFilter();
            updateWeekArrowState('weekFilter');
            document.getElementById('statusFilter').value = 'Pending';
            /* the shared page header carries the semester chip every other page
               shows; this page's hand-rolled header never had one */
            var semesterChip = document.getElementById('semesterChip');
            if (semesterChip) semesterChip.textContent = MockData.semester.chipText;

            // Skeleton loading
            SkeletonLoader.showSummary();
            SkeletonLoader.with(function() {
                isInitialLoad = false;
                restoreFilters();
                initLocalRpp();
                renderTable();
                updateNavBadge();
                checkDeepLink();
                SkeletonLoader.hideSummary();
            }, document.getElementById('tableBody'), 10, 400);

            initRpp({
                selectId: 'rowsPerPage',
                storageKey: 'request-approval-rpp',
                defaultVal: 10,
                onChange: function(size) {
                    rpp = size;
                    rebuildTable({ render: renderTable });
                    saveFilters();
                }
            });

            document.getElementById('searchInput').addEventListener('input', function() {
                rebuildTable({ render: renderTable });
                saveFilters();
            });
            document.getElementById('statusFilter').addEventListener('change', function() {
                rebuildTable({ render: renderTable });
                saveFilters();
            });
            /* filters-button: #weekFilterOriginal carries its own inline
               onchange (like the requested field), so no second listener here —
               one user change must run one render. */
            /* filters-button: the panel head's Reset carries the inline onclick, so
               the toolbar's old Reset button and its click listener are gone (a null
               here would throw and kill every listener wired after it). */
            FiltersPanel.init();

            document.getElementById('modalOverlay').addEventListener('click', function(e) {
                if (e.target === this) closeModal();
            });
            document.getElementById('rejectReasonModal').addEventListener('click', function(e) {
                if (e.target === this) closeRejectModal();
            });
            document.getElementById('approveNotesModal').addEventListener('click', function(e) {
                if (e.target === this) closeApproveNotesModal();
            });

            document.getElementById('rejectReasonInput').addEventListener('input', updateRejectConfirmState);

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Modal close — 3-layer Escape
                if (e.key === 'Escape') {
                    if (document.getElementById('approveNotesModal').classList.contains('show')) {
                        closeApproveNotesModal();
                    } else if (document.getElementById('rejectReasonModal').classList.contains('show')) {
                        closeRejectModal();
                    } else if (document.getElementById('modalOverlay').classList.contains('show')) {
                        closeModal();
                    } else {
                        focusedRowIndex = -1;
                        highlightRow();
                    }
                    return;
                }

                // Let Enter activate the help button without also opening the highlighted row.
                if (e.key === 'Enter' && e.target.matches('.guide-toggle-icon[aria-controls="guideModal"]')) return;

                // Pause keyboard nav when modal is open
                if (document.querySelector('.modal-overlay.show')) return;
                if (!currentFiltered.length) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    focusedRowIndex = Math.min(focusedRowIndex + 1, currentFiltered.length - 1);
                    highlightRow();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    focusedRowIndex = Math.max(focusedRowIndex - 1, 0);
                    highlightRow();
                } else if (e.key === 'Enter' && focusedRowIndex >= 0) {
                    openModalById(currentFiltered[focusedRowIndex].id);
                } else if ((e.key === 'a' || e.key === 'A') && focusedRowIndex >= 0) {
                    const r = currentFiltered[focusedRowIndex];
                    if (r.status === 'Pending') approveRequest(r.id);
                } else if ((e.key === 'r' || e.key === 'R') && focusedRowIndex >= 0) {
                    const r = currentFiltered[focusedRowIndex];
                    if (r.status === 'Pending') openRejectModal(r.id);
                }
            });
        });
@endsection
