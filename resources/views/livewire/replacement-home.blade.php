{{-- The frozen UI design from ui-design-templates/, byte-for-byte, with only
     the data source bridged to the backend (see the BRIDGE marks below). The
     component's render() applies layouts.ui-template — no @extends here
     (Livewire full-page: exactly one root element in the content section). --}}

@section('title', 'Replacement Arrangement — Class Replacement System')

@section('page-styles')

        /* ───── Column Widths ───── */
        .col-original { width: 200px; }
        .col-original { white-space: normal; }
        .col-urgency { width: 150px; } /* fits "Overdue (12 days ago)" without wrapping */
        .col-venue { width: 70px; }
        .col-cohort { width: 130px; }
        .col-reason { width: 140px; vertical-align: middle; }
        .col-action { width: 150px; }

        .grid-scroll .timetable { min-width: 1120px; }

        /* ───── Urgency ───── */
        .urgency-high { color: var(--color-error); font-weight: 700; }
        .urgency-mid { color: var(--color-warning); font-weight: 600; }
        .urgency-low { color: var(--color-on-surface-variant); font-weight: 500; }

        /* ───── Reason Badges ───── */
        .badge-holiday {
            background: var(--color-error-container);
            color: var(--color-on-error-container);
        }
        .badge-annual-leave {
            background: var(--color-primary-container);
            color: var(--color-on-primary-container);
        }
        .badge-medical-leave {
            background: var(--color-tertiary-container);
            color: var(--color-on-tertiary-container);
        }
        .badge-official-event {
            background: var(--color-primary-container);
            color: var(--color-on-primary-container);
        }
        .badge-emergency-leave {
            background: var(--color-error);
            color: var(--color-on-error);
        }
        /* class-cancel-db-persist — the 5 new vocab leaves (tokens only,
           the same-name/same-color §10.0 law; grouped where identical): */
        .badge-hospitalisation-leave,
        .badge-compassionate-leave,
        .badge-paternity-leave {
            background: var(--color-primary-container);
            color: var(--color-on-primary-container);
        }
        .badge-maternity-leave {
            background: var(--color-tertiary-container);
            color: var(--color-on-tertiary-container);
        }
        .badge-other {
            background: var(--color-secondary-container);
            color: var(--color-on-secondary-container);
        }

        /* ───── Just-cancelled Chip ───── */
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

        /* Undo affordance beside the chip (toast-snooze companion) */
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

        /* ───── Summary Card Colors ───── */
        .summary-card.card-conflict .summary-value { color: var(--color-error); }
        .summary-card.card-venues .summary-value { color: var(--color-primary); }
        .summary-card.card-students .summary-value { color: var(--color-tertiary); }
        .summary-card.card-duration .summary-value { color: var(--color-primary); }
        .summary-card.card-courses .summary-value { color: var(--color-on-primary-container); }

        .btn-replace-now {
            padding: 8px 20px;
            border-radius: var(--radius-sm);
            border: none;
            background: var(--color-primary);
            color: var(--color-on-primary);
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: filter 0.15s;
        }
        .btn-replace-now:hover {
            filter: brightness(1.08);
        }

        /* ───── Responsive Card View ───── */
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
            .grid-wrapper, .pagination-bar, .sort-hint { display: none !important; }
            .card-view { display: block; }
            #kbShortcutsBtn { display: none !important; }
        }

@endsection

{{-- Real-data bridge (SDD wire-backend-into-refactored-ui): the mock
     template runs UNCHANGED below; only its data source is swapped.
     PAGE_DATA.conflictedClasses carries the same row shape as
     MockData.conflictedClasses, derived from the real DB. --}}
<div class="lw-page">
    @include('partials.page-data-script', ['payload' => $payload])

        <!-- ─── Page Header ─── -->
        @include('partials.ui-page-header', ['title' => 'Replacement Arrangement', 'description' => 'The following classes require replacement arrangements. Select a class to submit a replacement request.'])

        @include('partials.ui-guide-block', [
            'guideTitle' => 'How to use this page',
            'guideItems' => [
                '<strong>Sort</strong> — click any column header to sort ascending/descending',
                '<strong>Search</strong> — type in the search box to filter by course code or name',
                '<strong>Hover headers</strong> — hover a column name to see what it means',
                '<strong>Start arranging</strong> — click the Action button on a row to begin the replacement process',
                '<strong>Week filter</strong> — use the week navigator to view conflicts for a specific week',
            ]
        ])

        <!-- ─── Toolbar ─── -->
        <div class="toolbar">
            <div class="toolbar-left">
                <div class="search-wrapper">
                    <svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input class="search-input" id="searchInput" placeholder="Search by course code or name...">
                </div>
                @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeekFilter()', 'nextOnclick' => 'nextWeekFilter()', 'selectId' => 'weekFilter', 'selectOnclick' => 'weekFilterChanged(this.value)', 'showTodayBtn' => false])
            </div>
            <div class="toolbar-right">
                <span class="result-count" id="resultCount">Showing 4 of 4 classes</span>
                {{--<button id="kbShortcutsBtn" class="btn-icon" onclick="showKeyboardShortcuts()" title="Keyboard Shortcuts" style="margin-left:auto; width:36px; height:36px; display:flex; align-items:center; justify-content:center; border:1px solid var(--color-outline); border-radius:8px; color:var(--color-on-surface-variant); background:var(--color-surface); cursor:pointer;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2" ry="2"/><path d="M6 8h.001"/><path d="M10 8h.001"/><path d="M14 8h.001"/><path d="M18 8h.001"/><path d="M8 12h.001"/><path d="M12 12h.001"/><path d="M16 12h.001"/><path d="M7 16h10"/></svg>
                </button>--}}
            </div>
        </div>

        <div class="sort-hint">Click <strong>Course Code &amp; Name</strong> or <strong>Original Class</strong> to sort</div>

        <!-- ─── Grid Wrapper ─── -->
        @include('partials.ui-grid-table', ['tableClass' => 'timetable data-table'])

        <!-- ─── Card View (mobile) ─── -->
        <div class="card-view" id="cardView"></div>

        <!-- ─── Pagination ─── -->
        <div class="pagination-bar" id="paginationBar">
            @include('partials.ui-rpp', ['id' => 'rppSelect', 'default' => 10, 'options' => [10, 25, 50, 'all']])
            <span class="pagination-info" id="paginationInfo">Showing 1-10 of 4</span>
            <div class="pagination-controls" id="paginationControls"></div>
        </div>

        <!-- ─── Empty State (above the summary; summary auto-hides when the view is empty) ─── -->
        @include('partials.ui-empty-state', ['title' => 'No classes currently require replacement arrangements.', 'text' => 'Try adjusting your search or filter criteria.'])

        <!-- ─── Summary Dashboard (own-records scope — the table lists only
              the logged-in lecturer's conflicted classes) ─── -->
        @include('partials.ui-summary-bar', [
            'cards' => [
                ['class' => 'card-conflict', 'valueId' => 'summaryMyConflicted', 'label' => 'My Conflicted Classes',
                    'description' => 'Your classes that <span class="warn-keyword">need a replacement arrangement</span>.'],
                ['class' => 'card-duration', 'valueId' => 'summaryMyHours', 'label' => 'My Hours to Cover',
                    'description' => 'Total <span class="info-keyword">hours of your class time</span> that need to be rescheduled.'],
                ['class' => 'card-courses', 'valueId' => 'summaryMyCourses', 'label' => 'My Courses',
                    'description' => 'Number of <span class="info-keyword">your different courses</span> affected by the conflicts.'],
            ]
        ])

        <!-- Keyboard Shortcuts Modal -->
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
                    <div class="modal-field"><span class="modal-field-label">Open quick view</span><span class="modal-field-value"><code style="padding:2px 6px;border:1px solid var(--color-outline);border-radius:var(--radius-xs);font-size:12px;background:var(--color-surface-variant)">Enter</code></span></div>
                    <div class="modal-field"><span class="modal-field-label">Show shortcuts</span><span class="modal-field-value"><code style="padding:2px 6px;border:1px solid var(--color-outline);border-radius:var(--radius-xs);font-size:12px;background:var(--color-surface-variant)">?</code></span></div>
                    <p class="hint-text" style="margin-top:12px;text-align:center">Shortcuts only work when no input field is focused.</p>
                </div>
                <div class="modal-footer">
                    <button class="btn-close-modal" onclick="hideKeyboardShortcuts()">Close</button>
                </div>
            </div>
        </div>

        <!-- Quick View Modal -->
        <style>
            #quickViewModal .modal-footer {
                justify-content: space-between !important;
                width: 100% !important;
            }
        </style>
        <div class="modal-overlay" id="quickViewModal" onclick="if(event.target===this)hideQuickView()">
            <div class="modal" style="max-width:500px">
                <div class="modal-header">
                    <span class="modal-title" id="qvTitle">Replacement Details</span>
                    <button class="modal-close" onclick="hideQuickView()" data-tip="Close">✕</button>
                </div>
                <div class="modal-body" id="qvBody"></div>
                <div class="modal-footer">
                    <button class="btn-close-modal" onclick="hideQuickView()">Close</button>
                    <button class="btn-action" id="qvArrangeBtn" onclick="qvArrange()">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        Arrange Replacement
                    </button>
                </div>
            </div>
        
    </div></div>

@section('page-scripts')
        /* Own-records scope: this page is the logged-in lecturer's personal
           conflict dashboard — only classes THEY teach are listed/counted.
           A FUNCTION (not a load-time snapshot) so rows the ClassCancellation
           ledger appends at runtime (always own, lecturer set by
           rowFromEvent) are picked up on every rebuild. Seeded rows carry
           `lecturer` (§2.10). */
        function myConflictedRows() {
            return MockData.conflictedClasses.filter(function (c) {
                return c.lecturer === MockData.currentUser.name;
            });
        }

        function badgeClass(reason) {
            const map = {
                'Public Holiday': 'badge-holiday',
                'Annual Leave': 'badge-annual-leave',
                'Medical/Sick Leave': 'badge-medical-leave',
                'Medical Leave': 'badge-medical-leave',   // legacy rows
                'Official Event': 'badge-official-event',
                'Emergency Leave': 'badge-emergency-leave',
                'Hospitalisation Leave': 'badge-hospitalisation-leave',
                'Maternity Leave': 'badge-maternity-leave',
                'Compassionate Leave': 'badge-compassionate-leave',
                'Paternity Leave': 'badge-paternity-leave',
                'Other': 'badge-other'
            };
            return map[reason] || '';
        }

        function formatClassBlock(c) {
            var d = dayAbbr(c.day);
            var dateStr = formatDate(c.date);
            var wn = getWeekNumber(c.date);
            var weekTag = wn ? ' (Week ' + wn + ')' : '';
            var timeStr = to12h(c.timeStart) + ' to ' + to12h(c.timeEnd);
            var hrs = c.duration + ' hr' + (c.duration > 1 ? 's' : '');
            return '<div class="cell-class-block"><span class="class-day-date">' + d + ', ' + dateStr + weekTag + '</span><br><span class="class-time">' + timeStr + '</span> <span class="class-duration">(' + hrs + ')</span></div>';
        }

        function daysLeft(iso) {
            /* one clock with the rest of the app: the shared demo anchor
               (DateHelper.getTodayMs) — not the raw browser date (which
               diverged from the lead-time pages during the mock phase). */
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

        var state = { rpp: 10 };
        const pageState = { currentPage: 1 };
        let sortState = { field: 'date', dir: 'asc' };
        let currentFiltered = [];

        function buildTable() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            const reason = 'all';
            const weekVal = document.getElementById('weekFilter').value;

            let filtered = myConflictedRows().filter(function(c) {
                const matchesSearch = query === '' ||
                    c.code.toLowerCase().includes(query) ||
                    c.name.toLowerCase().includes(query);
                const matchesReason = reason === 'all' || c.conflictReason === reason;
                const matchesWeek = weekVal === 'all' || String(getWeekNumber(c.date)) === weekVal;
                return matchesSearch && matchesReason && matchesWeek;
            });

            if (sortState.field) {
                filtered.sort(function(a, b) {
                    var va, vb;
                    if (sortState.field === 'date') {
                        va = a.date;
                        vb = b.date;
                    } else if (sortState.field === 'code') {
                        va = a.code;
                        vb = b.code;
                    } else if (sortState.field === 'daysLeft') {
                        /* R-4b (round-3): computed urgency — numeric, so Overdue
                           (negative) sorts before future classes ascending */
                        va = daysLeft(a.date);
                        vb = daysLeft(b.date);
                    } else if (sortState.field === 'venue') {
                        va = a.venue;
                        vb = b.venue;
                    }
                    return compareBy(sortState, va, vb);
                });
            }

            currentFiltered = filtered;

            const offset = (pageState.currentPage - 1) * state.rpp;
            const pageData = filtered.slice(offset, offset + state.rpp);

            const head = document.getElementById('tableHead');
            const body = document.getElementById('tableBody');
            head.innerHTML = '';
            body.innerHTML = '';

            const tr = document.createElement('tr');
            const columns = [
                { label: '#', cls: 'col-no', sortable: false, tip: 'Row number' },
                { label: 'Course Code & Name', cls: 'col-code', sortable: true, field: 'code', tip: 'Course affected by the conflict' },
                { label: 'Original Class', cls: 'col-original', sortable: true, field: 'date', tip: 'Original class session with a conflict' },
                { label: 'Days Left', cls: 'col-urgency', sortable: true, field: 'daysLeft', tip: 'Days remaining before the original class — ascending puts Overdue/most urgent first' },
                { label: 'Venue', cls: 'col-venue', sortable: true, field: 'venue', tip: 'Assigned venue for the class' },
                { label: 'Students', cls: 'col-students', sortable: false, tip: 'Number of enrolled students' },
                { label: 'Cohort(s)', cls: 'col-cohort', sortable: false, tip: 'Affected student cohorts' },
                { label: 'Conflict Reason', cls: 'col-reason', sortable: false, tip: 'Why the scheduling conflict exists' },
                { label: 'Action', cls: 'col-action', sortable: false, tip: 'Start arranging a replacement' },
            ];
            columns.forEach(function(col) {
                tr.appendChild(makeSortableHeader(col, sortState, function() {
                    pageState.currentPage = 1;
                    buildTable();
                }));
            });
            head.appendChild(tr);

            if (pageData.length === 0) {
                document.getElementById('emptyState').style.display = 'flex';
            } else {
                document.getElementById('emptyState').style.display = 'none';
                pageData.forEach(function(c, i) {
                    const row = document.createElement('tr');
                    var cells = HtmlBuilder.replacementHomeRow(c, {
                        index: String(offset + i + 1),
                        daysLeft: daysLeft,
                        urgencyClass: urgencyClass,
                        formatClassBlock: formatClassBlock,
                        badgeClass: badgeClass
                    });
                    cells.forEach(function(cell) {
                        const td = document.createElement('td');
                        td.className = cell.cls;
                        td.innerHTML = cell.html;
                        row.appendChild(td);
                    });
                    /* S13 — "Just cancelled" chip iff THIS row has its own
                       un-undone, un-consumed ledger entry (per-entry chips,
                       §6 stack semantics). class-cancel-db-persist: the
                       match widened through ClassCancellation.entryCoversRow
                       — a client-appended row (id match) OR a server
                       payload row (sessionId+week match, the ids
                       session*100+week never equal the client ids) — so
                       the chip + Undo survive a reload. Page-local
                       placement — cell index 2 is the Original Class
                       block (col-original); do NOT mutate shared
                       HtmlBuilder.replacementHomeRow (§5). Undo clears it
                       on the next buildTable (S14). */
                    const cxlEntries = ClassCancellation.allEntries();
                    if (cxlEntries.some(function(entry) {
                            return !entry.consumed && entry.chip &&
                                   ClassCancellation.entryCoversRow(entry, c);
                        })) {
                        const chip = document.createElement('span');
                        chip.className = 'just-cancelled-chip';
                        chip.textContent = 'Just cancelled';
                        row.children[2].appendChild(chip);
                        /* Undo affordance — keeps undo reachable when the
                           load toast was ✕-snoozed for the session
                           (toast-snooze fix, 2026-10-08). Icon-first, stops
                           row propagation so quickView doesn't fire. */
                        const undoBtn = document.createElement('button');
                        undoBtn.type = 'button';
                        undoBtn.className = 'just-cancelled-undo';
                        undoBtn.setAttribute('data-tip', 'Undo cancellation');
                        undoBtn.setAttribute('aria-label', 'Undo cancellation');
                        undoBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"></polyline><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path></svg>';
                        undoBtn.onclick = function(ev) {
                            ev.stopPropagation();
                            undoCancelledByRowId(c.id, undoBtn, c);
                        };
                        row.children[2].appendChild(undoBtn);
                    }
                    (function(row, idx) {
                        row.onclick = function() { quickView(idx); };
                        row.style.cursor = 'pointer';
                    })(row, i);
                    body.appendChild(row);
                });
            }

            paginate({ data: currentFiltered, pageSize: state.rpp, state: pageState, infoId: 'paginationInfo', controlsId: 'paginationControls', render: buildTable });
            updateResultCount({ elId: 'resultCount', data: currentFiltered, total: myConflictedRows().length, label: 'classes' });
            updateSummary();
            renderCards();
        }

        /* Undo a cancellation from the home list (toast-snooze companion):
           posts the server undo first (class-cancel-db-persist — the Undo
           affordance is the cross-page undo surface), THEN rebuilds the
           table (row + chip + undo button vanish) and mirrors the S14
           toast. A failed undo keeps the cancel standing: the honest hint,
           the button re-enables, no client reversal.

           The row lookup widened for the REAL payload (class-cancel-db-persist):
           a client-appended row matches by id; a server payload row (id
           session*100+week) matches by sessionId+week — and on a successful
           undo the SCREENSHOT server row is filtered OUT of the page's own
           copy (non-destructive filter reassignment; the MockData store law)
           so the rebuild shows it gone immediately. */
        async function undoCancelledByRowId(rowId, undoBtn, row) {
            const entry = ClassCancellation.allEntries().find(function(e) {
                return !e.consumed && ClassCancellation.entryCoversRow(e, row || { id: rowId });
            });
            if (!entry) return;
            if (undoBtn) undoBtn.disabled = true;   // pending-disable — one await, one write
            const res = await ClassCancellation.undoViaServer(entry);
            if (!res.ok) {
                toast.show(res.hint || 'Class restore failed — refresh to see the truth.', null);
                if (undoBtn) undoBtn.disabled = false;
                return;
            }
            if (row && row.sessionId != null && entry.matchKey &&
                Number.isInteger(entry.matchKey.week)) {
                MockData.conflictedClasses = (MockData.conflictedClasses || []).filter(function(r) {
                    return !(r.sessionId != null &&
                             Number(r.sessionId) === Number(row.sessionId) &&
                             r.week === entry.matchKey.week + 1);
                });
            }
            buildTable();
            toast.show('Class restored.', null);
        }

        function renderCards() {
            var container = document.getElementById('cardView');
            if (!container) return;
            container.innerHTML = '';
            currentFiltered.forEach(function(c) {
                var card = document.createElement('div');
                card.className = 'replacement-card';
                card.setAttribute('role', 'button');
                card.setAttribute('tabindex', '0');
                card.addEventListener('click', function() { goToReplacementWith(c.sessionId, c.week); });
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); goToReplacementWith(c.sessionId, c.week); }
                });
                card.innerHTML = HtmlBuilder.replacementHomeCard(c, {
                    daysLeft: daysLeft,
                    urgencyClass: urgencyClass,
                    badgeClass: badgeClass
                });
                container.appendChild(card);
            });
        }

        function updateSummary() {
            const total = myConflictedRows().length;
            const filtered = currentFiltered;

            const duration = filtered.reduce(function(sum, c) { return sum + c.duration; }, 0);
            const courses = new Set(filtered.map(function(c) { return c.code; }));

            document.getElementById('summaryMyConflicted').textContent = total;
            document.getElementById('summaryMyHours').textContent = duration;
            document.getElementById('summaryMyCourses').textContent = courses.size;

            const show = filtered.length > 0;
            syncSummarySection(show);
        }

        function populateWeekDropdown() {
            populateWeekSelect('weekFilter', { includeAll: true });
        }

        /* BRIDGE PATCH: the handoff carries the occurrence's real identity —
           (class_session_id, week_number), the same D1 pair the request row
           keys on — instead of code+date strings the page had to reverse-
           engineer. The arrangement page resolves + ownership-checks it. */
        function goToReplacementWith(sessionId, week) {
            window.location.href = '/replacement-arrangement?session=' + encodeURIComponent(sessionId) +
                '&week=' + encodeURIComponent(week) + '&from=replacement-home';
        }


        function showKeyboardShortcuts() {
            var el = document.getElementById('keyboardModal');
            if (el) { el.classList.add('show'); }
        }

        function hideKeyboardShortcuts() {
            var el = document.getElementById('keyboardModal');
            if (el) { el.classList.remove('show'); }
        }

        var qvCurrent = null;

        function quickView(idx) {
            var c = currentFiltered[idx];
            if (!c) return;

            qvCurrent = c;

            var daysLeftVal = daysLeft(c.date);
            var urgencyCls = urgencyClass(daysLeftVal); /* single vocabulary: table's urgency-high/mid/low */
            var wn = getWeekNumber(c.date);
            var weekTag = wn ? ' (Week ' + wn + ')' : '';
            var typeLabel = UiLabels.classType(c.type, 'long');

            DetailModal.render({
                modalId: 'quickViewModal',
                title: 'Replacement Details',
                subtitle: c.code + ' · ' + c.name + ' (' + typeLabel + ')',
                tabs: [
                    { key: 'status', label: 'Status', html: DetailModal.section('Status',
                        DetailModal.row('Status', '<span class="urgency-badge ' + urgencyCls + '">' + daysLeftLabel(daysLeftVal) + '</span>') +
                        DetailModal.row('Status Description', daysLeftVal < 0 ? 'Class date has passed — you can still arrange a replacement in any upcoming week' : daysLeftVal <= 3 ? 'Urgent — arrange a replacement soon' : daysLeftVal <= 7 ? 'Approaching — plan a replacement' : 'Within normal lead time') +
                        DetailModal.row('Conflict Reason', '<span class="badge ' + badgeClass(c.conflictReason) + '">' + c.conflictReason + '</span>')
                    ) },
                    { key: 'class', label: 'Class', html: DetailModal.section('Class',
                        DetailModal.row('Subject Code', c.code, { strong: true }) +
                        DetailModal.row('Subject Name', c.name) +
                        DetailModal.row('Class Type', typeLabel) +
                        DetailModal.row('Cohort(s)', c.cohorts.join(', ')) +
                        /* BMSE3153-class-info (user law 2026-10-27): the shared
                           cohortBreakdown (ui-common.js) renders the per-cohort
                           `A + B = total` breakdown for multi-cohort rows. */
                        DetailModal.row('Students', window.cohortBreakdown(c))
                    ) },
                    { key: 'schedule', label: 'Schedule', html: DetailModal.section('Schedule',
                        DetailModal.row('Week', 'Week ' + (wn || '-')) +
                        DetailModal.row('Day', c.day) +
                        DetailModal.row('Date', formatDate(c.date) + weekTag) +
                        DetailModal.row('Start Time', to12h(c.timeStart)) +
                        DetailModal.row('End Time', to12h(c.timeEnd)) +
                        DetailModal.row('Duration', c.duration + ' hr' + (c.duration > 1 ? 's' : '')) +
                        DetailModal.row('Venue', c.venue)
                    ) },
                ]
            });
        }

        function qvArrange() {
            if (qvCurrent) {
                /* capture-then-hide (SDD qv-arrange-null-order-fix D1: the
                   frozen template :588–594 shape) — hideQuickView() nulls
                   qvCurrent, so the lead is captured BEFORE the hide or the
                   later read throws (the button's former silent no-op). */
                var sid = qvCurrent.sessionId;
                var wk = qvCurrent.week;
                hideQuickView();
                goToReplacementWith(sid, wk);
            }
        }

        function hideQuickView() {
            qvCurrent = null;
            DetailModal.close();
        }

        function clearAll() {
            document.getElementById('searchInput').value = '';
            document.getElementById('weekFilter').value = 'all';
            sortState.field = 'date';
            sortState.dir = 'asc';
            pageState.currentPage = 1;
            buildTable();
            updateWeekArrowState();
        }

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

        document.addEventListener('keydown', function(e) {
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
                if (qvEl && qvEl.classList.contains('show')) {
                    hideQuickView();
                    return;
                }
                var el = document.getElementById('keyboardModal');
                if (el && el.classList.contains('show')) {
                    hideKeyboardShortcuts();
                    return;
                }
                clearAll();
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
            if (e.key === 'Enter') {
                var focused = document.querySelector('.table-body tr:focus, .table-body tr:focus-within');
                if (focused) {
                    var rows = Array.from(document.querySelectorAll('.table-body tr'));
                    var idx = rows.indexOf(focused);
                    if (idx >= 0) { quickView(idx); }
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            /* ── Real-data bridge — runs here because mock-data.js /
               ui-common.js parse after @yield('content'). Everything below
               is the frozen UI design, byte-for-byte. ── */
            MockData.conflictedClasses = window.PAGE_DATA.conflictedClasses;
            MockData.semester = window.PAGE_DATA.semester;
            MockData.holidays = window.PAGE_DATA.holidays;
            MockData.currentUser = window.PAGE_DATA.currentUser;
            MockData.mockNow = null; /* real clock — the demo anchor is mock-era only */
            /* class-cancel-db-persist: ui-common's own DOMContentLoaded ran
               applyLedger BEFORE this bridge filled MockData.conflictedClasses
               with the real payload rows — a server-bridged ledger entry's
               retention check consulted an empty set and silently dropped it
               (the chip never carried). Re-run here, after the swap, while
               definitions are idempotent. */
            ClassCancellation.applyLedger();
            initHeaderTooltips();
            const conflictedClasses = myConflictedRows();   // initial snapshot for early readers
            document.getElementById('semesterChip').textContent = MockData.semester.chipText;
            populateWeekDropdown();
            SkeletonLoader.showSummary();
            SkeletonLoader.with(function() { buildTable(); SkeletonLoader.hideSummary(); }, document.getElementById('tableBody'), 10, 400);
            updateWeekArrowState();
            initWeekKeyboardShortcuts();
            initRpp({
                selectId: 'rppSelect',
                storageKey: 'rpp-page-size',
                defaultVal: 10,
                onChange: function(size) {
                    state.rpp = size;
                    rebuildTable({ render: buildTable });
                }
            });
            document.getElementById('searchInput').addEventListener('input', function() {
                rebuildTable({ render: buildTable });
            });
            document.getElementById('weekFilter').addEventListener('change', function() {
                rebuildTable({ render: buildTable, after: updateWeekArrowState });
            });
        });
@endsection
