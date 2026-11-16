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
            /* 2026-11-14 (tooltip-viewport-clamp, user): the box had no ceiling, so a
               cell near the right edge pushed it past the window and the text was cut
               off. 340px holds the longest demo sentence in a few lines; the calc keeps
               it inside a narrow window (the JS clamp then pulls it fully in). */
            max-width: min(340px, calc(100vw - 24px));
            box-sizing: border-box;
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
        /* 2026-11-14 (venue-evening-tail, user): the grid no longer emits
           `.cell-no-fit` — the day-end case now speaks the same "Not Enough Slot"
           language as every other "your class cannot fit here" cell. The rules
           below are kept because the phone/print layers and older cached views
           still reference the class; nothing new applies it. */
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
        /* cancelled-slot-reuse A21: a cell the room's REAL rows hold (a
           pre-cleanup raw occupied/pending cell with no live holder to
           render) — NEUTRAL surface tone, no hover label, never a fabricated
           NORMAL/available look. Token-only (the reserved/neutral pair). */
        .cell-content.cell-held {
            background: var(--color-surface-variant);
            cursor: not-allowed;
        }
        .cell-content.cell-held:hover { filter: none; }
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
        /* 2026-11-10 (user): the week cluster — arrows + week picker + Today +
           Earliest bookable — sits in the MIDDLE of the semester bar, and really
           centred, not "as centred as the venue box allows": the two flexible
           columns on either side are equal (minmax(0,1fr)), so the middle three
           land on the bar's own centre line. The venue box keeps the left edge,
           the print button the right edge. Five columns = the five children the
           bar has on this page.
           Below 900px there is no room to centre a ~600px cluster, so the bar
           keeps today's wrapping flex row (theme.css .semester-bar). */
        @media (min-width: 900px) {
            .semester-bar--venue {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto auto auto minmax(0, 1fr);
                align-items: center;
                gap: 8px;
            }
            .semester-bar--venue > .venue-dropdown-wrap {
                justify-self: start;
            }
            .semester-bar--venue > .print-btn {
                justify-self: end;
            }
        }
        /* Session-choice modal: dropdowns fill their detail-row value column */
        #intentPickers .filter-select {
            width: 100%;
        }
        #intentNotice {
            font-size: 14px;
            color: var(--color-on-surface);
        }

        /* ───── Venue selector: darker trigger + responsive panel ─────
           2026-11-13 (user): the venue trigger must not read as white — it
           uses the same darker surface-variant fill as the arrangement page,
           so the control separates from the white toolbars and the grid. */
        .semester-bar--venue .venue-dd-trigger {
            background: var(--color-surface-variant);
            color: var(--color-on-surface);
            min-width: 0;
            max-width: min(340px, 100%);
        }
        .semester-bar--venue .venue-dropdown-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 0;
            max-width: 100%;
        }
        /* 2026-11-13 (user): the four columns are fluid instead of a fixed
           800px block. Before, opening the panel or letting hover reveal the
           later columns pushed it past the viewport (DevTools narrow desktop
           clipped the Rooms column). The clamp function lives in the page
           script; this rule makes the panel able to shrink at all. */
        @media (min-width: 769px) {
            .semester-bar--venue .venue-dd-panel {
                width: max-content;
                max-width: calc(100vw - 24px);
            }
            .semester-bar--venue .venue-dd-panel .venue-col {
                flex: 0 1 150px;
                width: 150px;
                min-width: 0;
                max-width: 150px;
            }
            .semester-bar--venue .venue-dd-panel .venue-col-header { padding: 8px 10px 6px; }
            .semester-bar--venue .venue-dd-panel .venue-col-item {
                min-width: 0;
                padding: 8px 10px;
                gap: 6px;
            }
            .semester-bar--venue .venue-dd-panel .venue-col-item .venue-col-item-meta {
                flex-shrink: 1;
                min-width: 0;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        /* ───── Mobile venue sheet (<=768px) ─────
           Same pattern as the replacement-arrangement page: one searchable
           bottom sheet instead of the wide four-column cascade. */
        dialog#venueTimetablePicker {
            width: min(560px, 100%);
            max-width: 100%;
            max-height: 88dvh;
            margin: auto auto 0;
            padding: 0;
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
            background: var(--color-surface);
            color: var(--color-on-surface);
            overflow: hidden;
        }
        dialog#venueTimetablePicker::backdrop {
            background: var(--color-scrim);
        }
        dialog#venueTimetablePicker[open] {
            display: flex;
            flex-direction: column;
        }
        dialog#venueTimetablePicker .venue-picker-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding: 12px 16px;
            border-bottom: 1px solid var(--color-outline);
        }
        dialog#venueTimetablePicker .venue-picker-header h2 {
            margin: 0;
            font-size: 1rem;
        }
        dialog#venueTimetablePicker .venue-picker-search {
            padding: 12px 16px;
            border-bottom: 1px solid var(--color-outline);
        }
        dialog#venueTimetablePicker .venue-picker-search input {
            box-sizing: border-box;
            width: 100%;
            min-height: 44px;
            padding: 0 12px;
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md);
            background: var(--color-surface-variant);
            color: var(--color-on-surface);
            font: inherit;
        }
        dialog#venueTimetablePicker .venue-picker-body {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overscroll-behavior: contain;
            padding: 8px 16px calc(16px + env(safe-area-inset-bottom, 0px));
            scrollbar-width: thin;
        }
        /* 2026-11-13 (user): category headers are visually distinct from the
           venue rows — darker filled bar, sticky while their group scrolls,
           and a real expand/collapse button on the right. */
        dialog#venueTimetablePicker .venue-picker-group {
            position: sticky;
            top: 0;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            width: 100%;
            margin: 8px 0 2px;
            padding: 10px 10px 10px 12px;
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-sm);
            background: var(--color-surface-variant);
            color: var(--color-on-surface);
            font: inherit;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            text-align: left;
            cursor: pointer;
        }
        dialog#venueTimetablePicker .venue-picker-group:hover,
        dialog#venueTimetablePicker .venue-picker-group:focus-visible {
            border-color: var(--color-outline-strong);
        }
        dialog#venueTimetablePicker .venue-picker-group:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 1px;
        }
        dialog#venueTimetablePicker .venue-picker-group-label {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        dialog#venueTimetablePicker .venue-picker-group-chevron {
            flex: 0 0 auto;
            width: 14px;
            height: 14px;
            color: var(--color-on-surface-variant);
            transition: transform 0.15s ease;
        }
        dialog#venueTimetablePicker .venue-picker-group[aria-expanded="false"] .venue-picker-group-chevron {
            transform: rotate(-90deg);   /* right-pointing when collapsed */
        }
        dialog#venueTimetablePicker .venue-picker-group-items[hidden] { display: none; }
        dialog#venueTimetablePicker .venue-picker-item {
            display: flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            min-height: 44px;
            padding: 9px 4px;
            border: 0;
            border-bottom: 1px solid var(--color-outline-variant);
            background: transparent;
            color: var(--color-on-surface);
            font: inherit;
            text-align: left;
            cursor: pointer;
        }
        dialog#venueTimetablePicker .venue-picker-item:hover,
        dialog#venueTimetablePicker .venue-picker-item:focus-visible {
            background: var(--color-surface-variant);
        }
        dialog#venueTimetablePicker .venue-picker-item.selected {
            background: var(--color-primary-container);
            color: var(--color-on-primary-container);
        }
        dialog#venueTimetablePicker .venue-picker-item-main {
            display: flex;
            flex-direction: column;
            gap: 1px;
            flex: 1 1 auto;
            min-width: 0;
        }
        dialog#venueTimetablePicker .venue-picker-item-main strong { font-size: 13px; }
        dialog#venueTimetablePicker .venue-picker-item-name,
        dialog#venueTimetablePicker .venue-picker-item-meta {
            font-size: 12px;
            color: var(--color-on-surface-variant);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        dialog#venueTimetablePicker .venue-picker-item.selected .venue-picker-item-name,
        dialog#venueTimetablePicker .venue-picker-item.selected .venue-picker-item-meta {
            color: inherit;
        }
        dialog#venueTimetablePicker .venue-picker-item-meta { flex: 0 0 auto; }
        dialog#venueTimetablePicker .venue-picker-empty {
            margin: 12px 0;
            font-size: 13px;
            color: var(--color-on-surface-variant);
            font-style: italic;
        }
        @media (max-width: 768px) {
            .semester-bar--venue .venue-dd-panel { display: none !important; }
            .semester-bar--venue .venue-dd-trigger { max-width: 100%; }
        }
    </style>

    <!-- ─── Page Header ─── -->
    @include('partials.ui-page-header', ['title' => 'Venue Timetable', 'guideToggle' => true, 'guideModal' => true, 'summaryButton' => true])

    @include('partials.ui-guide-modal', [
        'guideTitle' => 'How to use this page',
        'guideDescriptionHtml' => 'Inspect any <span class="info-keyword">venue\'s weekly occupancy</span> — <span class="info-keyword">real slot states</span> from the timetable database.',
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
    <!-- ─── Semester Bar ─── venue + week selectors on ONE row (frozen fjing
         layout, f8b35a2; matches the cohort-timetable pattern). The 2026-10-09
         DB wiring (adc95bb) had split them into two bars — unauthorised drift,
         fixed in venue-toolbar-row-parity. Print stub restored via showPrint;
         fav-star ☆ stays dropped (documented divergence — mock localStorage),
         the capacity span stands where fjing's fav-btn sits. -->
    <div class="semester-bar semester-bar--venue">
        <div class="venue-dropdown-wrap">
            @include('partials.ui-venue-dropdown', ['selectId' => 'venueSelect'])
            {{-- venue-side-chips: the capacity badge (icon + number + tip) AND,
                 2026-11-10 (user), the room DESCRIPTION — which used to be a plain
                 grey line under the dropdown and is now the same info-icon chip the
                 arrangement page wears (`#arrVenueDescription`): `.badge badge-past`
                 + info glyph, the description in the hover tip. Same group, same
                 4px gap, same 12px glyph on both pages. Each chip stays hidden until
                 the payload fills it — no empty stubs. --}}
            <div class="venue-side-chips">
                <span class="badge badge-capacity" id="venueCapacity" style="display:none;"></span>
                <span class="badge badge-past" id="venueDescription" style="display:none;"></span>
            </div>
        </div>
        @include('partials.ui-week-nav', ['prevOnclick' => 'prevWeek()', 'nextOnclick' => 'nextWeek()', 'selectId' => 'weekSelect', 'selectOnclick' => 'selectWeek(this.value)', 'selectTinted' => true, 'showPrint' => true, 'showEarliestBtn' => true])
    </div>

    <!-- ─── Grid Wrapper ─── -->
    @include('partials.ui-grid-table')

    <!-- ─── Print surfaces (SDD timetable-print-v2, 2026-11-16) ───
         A room code and a room name already identify themselves, so no ID prints: one
         em dash, spaces either side. The three empty-case traps are the design's own
         (design §6.2) — `$venue` is an ARRAY and the empty-venues path passes
         `name => ''`, so `??` would never fire and the sheet would print a bare
         `Venue:`; a room with no name falls back to its code; and only when BOTH are
         empty does it say `Select a venue`. -->
    @php
        $printVenueCode = (string) ($venue['code'] ?? '');
        $printVenueName = (string) ($venue['name'] ?? '');
        if ($printVenueCode === '' && $printVenueName === '') {
            $printVenueContext = 'Select a venue';
        } else {
            $printVenueContext = $printVenueCode.' — '.($printVenueName !== '' ? $printVenueName : $printVenueCode);
        }
    @endphp
    @include('partials.ui-print-header', [
        'printTitle' => 'Venue Timetable',
        'printSub' => 'Venue occupancy',
        'printContext' => $printVenueContext,
        'printOwnership' => true,
    ])
    @include('partials.ui-print-sheet')
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
        'flipCards' => true,
        'cards' => [
            ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Slots',
                'bullets' => ['Every slot shown', '<strong>30 min</strong> each', '<strong class="summary-pending">+N</strong> = pending'],
                'explanation' => 'Every time slot shown for <span class="info-keyword">this venue</span> this week (30 minutes each). The small <strong>+N</strong> is the part held by pending requests.',
                'detail' => [
                    'formula' => [['v' => 'slots × 30 min'], ['op' => '='], ['v' => 'the full week']],
                    'items' => [
                        ['Every slot shown', '<span>All slots the venue grid displays,<br>free or taken.</span>'],
                        ['Each slot is 30 min', '<span>Two slots make one hour.</span>'],
                        ['Pending', '<span>Added in the small <strong>+N</strong> only.</span>'],
                    ],
                ]],
            ['class' => 'card-available', 'valueId' => 'sumAvailable', 'label' => 'Available',
                'bullets' => ['Free to book', 'No Sunday/holiday', 'Click a green slot'],
                'explanation' => 'Free slots you can book this week. <span class="warn-keyword">Sundays and public holidays</span> are not counted.',
                'detail' => [
                    'items' => [
                        ['Free to book', '<span>No class, request or holiday in the slot.</span>'],
                        ['Click a green slot', '<span>Green means it can be booked.</span>'],
                        ['No Sundays or holidays', '<span>Closed days never count as available.</span>'],
                    ],
                ]],
            ['class' => 'card-replacement', 'valueId' => 'sumMyClasses', 'label' => 'My Teaching Classes',
                'bullets' => ['Classes you teach', 'Counted per class', '<strong class="summary-pending">+N</strong> = pending'],
                'explanation' => 'Classes <span class="info-keyword">you teach</span> in this venue this week: normal + replacement + pending, each class counted on its own. The small <strong>+N</strong> is the pending part.',
                'detail' => [
                    'items' => [
                        ['Classes you teach', '<span>Only your own classes in this venue.</span>'],
                        ['Counted per class', '<span>A two-hour class counts once,<br>not twice.</span>'],
                        ['Pending', '<span>Added in the small <strong>+N</strong> only.</span>'],
                    ],
                ]],
            ['class' => 'card-hours', 'valueId' => 'sumMyHours', 'label' => 'My Teaching Hours',
                'bullets' => ['Your teaching', '<strong>30 min</strong> / slot', '<strong class="summary-pending">+N</strong> = pending'],
                'explanation' => 'Hours of the classes <span class="info-keyword">you teach</span> in this venue this week (each slot = <strong>30 minutes</strong>). The small <strong>+N</strong> is the pending part.',
                'detail' => [
                    'formula' => [['v' => 'slots × 30 min'], ['op' => '='], ['v' => 'your teaching']],
                    'items' => [
                        ['Your teaching only', '<span>Other lecturers’ classes are excluded.</span>'],
                        ['Each slot is 30 min', '<span>Two slots make one hour.</span>'],
                        ['Pending', '<span>Added in the small <strong>+N</strong> only.</span>'],
                    ],
                ]],
            ['class' => 'card-conflict', 'valueId' => 'sumOccupied', 'label' => 'Occupied',
                'bullets' => ['Taken by others', 'Normal + pending', '<strong class="summary-pending">+N</strong> = pending'],
                'explanation' => 'Slots taken by other classes this week: normal + replacement + pending. The small <strong>+N</strong> is the part held by pending requests.',
                'detail' => [
                    'items' => [
                        ['Taken by others', '<span>Booked by classes that are not yours.</span>'],
                        ['Normal + pending', '<span>Both count as occupied,<br>so the slot cannot be booked.</span>'],
                        ['Pending', '<span>Shown as the small <strong>+N</strong>.</span>'],
                    ],
                ]],
        ]
    ])

    <p class="booking-hint-line">Click any green empty slot to arrange a replacement in this venue.</p>
    </div><!-- /wire:ignore: JS-owned surface -->

    <!-- ─── Available Slot Tooltip (U2a — frozen template :461–463) ─── -->
    <div class="available-tooltip" id="availableTooltip">
        <p id="tooltipText">Arrange a replacement in B110 on Thu, 22 Oct 2026 at 14:00?</p>
        <button class="btn-book" id="tooltipBookBtn">Arrange</button>
    </div>

    {{-- Mobile venue picker (2026-11-13, user): below 769px the wide
         four-column cascade is replaced by one searchable sheet, matching the
         replacement-arrangement page. Selection still goes through
         VenueDropdown.select(), so last-visit memory and the ?venue= full
         render are untouched. --}}
    <dialog id="venueTimetablePicker" aria-labelledby="venueTimetablePickerHeading" data-venue-timetable-picker>
        <div class="venue-picker-header">
            <h2 id="venueTimetablePickerHeading">Choose venue</h2>
            <button type="button" class="btn btn-outline" aria-label="Close venue picker" data-venue-picker-close>Close</button>
        </div>
        <div class="venue-picker-search">
            <input type="search" placeholder="Search code or room name" aria-label="Search venues" autocomplete="off" data-venue-picker-search>
        </div>
        <div class="venue-picker-body" data-venue-picker-list></div>
    </dialog>

    <!-- ═══ Class Detail Modal ═══ -->
    @section('modal-footer')
        <div class="modal-footer-left">
            <button class="btn-close-modal" onclick="closeModal()">Close</button>
        </div>
        <div class="modal-footer-right">
            <button class="btn-replace-now" id="btnReplaceNow" style="display:none" onclick="goToReplacementFor(currentModalEvent?.sessionId, currentWeek + 1, 'venue-timetable')"><svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg> Arrange Replacement</button> <!-- SDD cohort-venue-replace-now T2 (user law 2026-10-24): currentWeek was the 0-BASED index — the arrangement's pinnedSessionPayload validates the 1-BASED human week (1..weeks); the raw index also made W1 a silent no-op (the !week guard ate index 0). The page's own other flows (intentSessionOptions, the arrange-URL fallback) already pass +1. -->
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
            /* cancelled-slot-reuse: the served payload on `window` — the same
               bridge my-timetable exposes (`window.eventsData`), so tests can
               stage a synthetic scoped payload and re-render without scraping
               the DOM. Read-only by convention. */
            window.eventsData = eventsData;
            /* cancelled-slot-reuse A21: the served REAL room matrix on `window`
               — week index → 'di:hi' → 'free' | 'blocked' (a missing key = no
               offer row). The green/bookable decisions read it; synthetic
               tests must stage a truthful matrix fixture alongside their
               events and restore both, never bypass the guard. */
            const roomCellStatesByWeek = @json($roomCellStatesByWeek);
            window.roomCellStatesByWeek = roomCellStatesByWeek;
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
            const venueDropdown = new VenueDropdown(
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

            /* ── 2026-11-13 (user): keep the desktop panel inside the viewport.
               The panel is a 4 x 150px cascade, so it is ~600px wide the
               moment hover reveals the later columns. Without a clamp it ran
               under a docked DevTools pane / off a narrow window and the Rooms
               column became unclickable. Same law as the arrangement page:
               cap the width in CSS, then slide `left` so the box stays 12px
               inside the viewport; re-clamp whenever the panel's width changes
               because columns appear on HOVER, not only on click. */
            function fitVenuePanel() {
                const dd = document.getElementById('venueSelectDropdown');
                if (!dd || !dd.classList.contains('open')) return;
                const panel = dd.querySelector('.venue-dd-panel');
                const trigger = dd.querySelector('.venue-dd-trigger');
                if (!panel || !trigger) return;
                const width = panel.getBoundingClientRect().width;
                const triggerLeft = trigger.getBoundingClientRect().left;
                const margin = 12;
                let left = triggerLeft;
                const max = window.innerWidth - margin - width;
                if (left > max) left = max;
                if (left < margin) left = margin;
                panel.style.left = Math.round(left - triggerLeft) + 'px';
                panel.style.right = 'auto';
            }

            function resetVenuePanel() {
                const panel = document.querySelector('#venueSelectDropdown .venue-dd-panel');
                if (!panel) return;
                panel.style.left = '';
                panel.style.right = '';
            }

            const venueDdRoot = document.getElementById('venueSelectDropdown');
            if (venueDdRoot) {
                const trigger = venueDdRoot.querySelector('.venue-dd-trigger');
                const panel = venueDdRoot.querySelector('.venue-dd-panel');
                trigger?.addEventListener('click', function () {
                    setTimeout(function () {
                        if (venueDdRoot.classList.contains('open')) fitVenuePanel();
                        else resetVenuePanel();
                    }, 0);
                });
                panel?.addEventListener('click', function () {
                    setTimeout(fitVenuePanel, 0);
                }, true);
                if (panel && typeof ResizeObserver === 'function') {
                    new ResizeObserver(function () {
                        if (venueDdRoot.classList.contains('open')) fitVenuePanel();
                    }).observe(panel);
                }
                window.addEventListener('resize', function () {
                    if (venueDdRoot.classList.contains('open')) fitVenuePanel();
                });
            }

            /* ── 2026-11-13 (user): mobile venue sheet (<=768px).
               Same pattern as the arrangement page — one searchable bottom
               sheet instead of the wide cascade. Selection still goes through
               venueDropdown.select(), preserving last-visit memory and the
               ?venue= full render. */
            function venuePickerParts(code) {
                const text = String(code || '');
                const blockMatch = text.match(/^([A-Za-z]+)/);
                const floorMatch = text.match(/\d/);
                return {
                    block: blockMatch ? blockMatch[1].toUpperCase() : 'OTHER',
                    floor: floorMatch ? floorMatch[0] : '0'
                };
            }

            function renderVenuePicker(query) {
                const host = document.querySelector('[data-venue-picker-list]');
                if (!host) return;
                host.textContent = '';
                const q = String(query || '').trim().toLowerCase();
                const venues = servedVenues.filter(function (v) {
                    if (!q) return true;
                    const label = [v.code, v.name, v.short_name, VenueDropdown.humanizeType(v.type)]
                        .join(' ').toLowerCase();
                    return label.indexOf(q) !== -1;
                }).slice().sort(function (a, b) {
                    return String(a.code).localeCompare(String(b.code), undefined, { numeric: true });
                });

                if (!venues.length) {
                    const empty = document.createElement('p');
                    empty.className = 'venue-picker-empty';
                    empty.textContent = 'No venues match that search.';
                    host.appendChild(empty);
                    return;
                }

                const selected = venueDropdown ? venueDropdown.getSelected() : null;
                const groups = new Map();
                venues.forEach(function (v) {
                    const parts = venuePickerParts(v.code);
                    const key = parts.block + '|' + parts.floor;
                    if (!groups.has(key)) groups.set(key, { block: parts.block, floor: parts.floor, venues: [] });
                    groups.get(key).venues.push(v);
                });

                Array.from(groups.values()).sort(function (a, b) {
                    return a.block === b.block
                        ? Number(a.floor) - Number(b.floor)
                        : a.block.localeCompare(b.block);
                }).forEach(function (group, groupIndex) {
                    /* Default: only the first (topmost) category is expanded.
                       When a search is active every group with a match opens, so
                       a hit is never hidden behind a collapsed header. */
                    const expanded = q ? true : groupIndex === 0;
                    const groupLabel = 'Block ' + group.block + ' \u00b7 Floor ' + group.floor;

                    const heading = document.createElement('button');
                    heading.type = 'button';
                    heading.className = 'venue-picker-group';
                    heading.setAttribute('aria-expanded', String(expanded));
                    heading.setAttribute('aria-controls', 'venuePickerGroup' + groupIndex);
                    heading.dataset.venueGroup = group.block + '|' + group.floor;

                    const label = document.createElement('span');
                    label.className = 'venue-picker-group-label';
                    label.textContent = groupLabel;

                    const chevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                    chevron.setAttribute('class', 'venue-picker-group-chevron');
                    chevron.setAttribute('width', '14');
                    chevron.setAttribute('height', '14');
                    chevron.setAttribute('viewBox', '0 0 24 24');
                    chevron.setAttribute('fill', 'none');
                    chevron.setAttribute('stroke', 'currentColor');
                    chevron.setAttribute('stroke-width', '2');
                    chevron.setAttribute('stroke-linecap', 'round');
                    chevron.setAttribute('stroke-linejoin', 'round');
                    chevron.setAttribute('aria-hidden', 'true');
                    const chevronPath = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                    chevronPath.setAttribute('d', 'm6 9 6 6 6-6');
                    chevron.appendChild(chevronPath);

                    heading.appendChild(label);
                    heading.appendChild(chevron);

                    const items = document.createElement('div');
                    items.className = 'venue-picker-group-items';
                    items.id = 'venuePickerGroup' + groupIndex;
                    if (!expanded) items.hidden = true;

                    heading.addEventListener('click', function () {
                        const isOpen = heading.getAttribute('aria-expanded') === 'true';
                        heading.setAttribute('aria-expanded', String(!isOpen));
                        items.hidden = isOpen;
                    });

                    host.appendChild(heading);
                    host.appendChild(items);

                    group.venues.forEach(function (v) {
                        const isSelected = v.code === selected;
                        const item = document.createElement('button');
                        item.type = 'button';
                        item.className = 'venue-picker-item' + (isSelected ? ' selected' : '');
                        item.setAttribute('aria-pressed', String(isSelected));
                        item.dataset.venueCode = v.code;

                        const main = document.createElement('span');
                        main.className = 'venue-picker-item-main';
                        const code = document.createElement('strong');
                        code.textContent = v.code;
                        const name = document.createElement('span');
                        name.className = 'venue-picker-item-name';
                        const displayName = window.ModalFacts
                            ? window.ModalFacts.roomName(v.code, v.name)
                            : v.name;
                        name.textContent = displayName || VenueDropdown.humanizeType(v.type);
                        main.appendChild(code);
                        main.appendChild(name);

                        const meta = document.createElement('span');
                        meta.className = 'venue-picker-item-meta';
                        meta.textContent = v.capacity ? v.capacity + ' seats' : '';

                        item.appendChild(main);
                        item.appendChild(meta);
                        item.addEventListener('click', function () {
                            closeVenuePicker();
                            if (venueDropdown) venueDropdown.select(v.code);
                        });
                        items.appendChild(item);
                    });
                });
            }

            function openVenuePicker() {
                const dialog = document.getElementById('venueTimetablePicker');
                if (!dialog || window.innerWidth > 768) return;
                if (venueDropdown && typeof venueDropdown._close === 'function') venueDropdown._close();
                const search = dialog.querySelector('[data-venue-picker-search]');
                if (search) search.value = '';
                renderVenuePicker('');
                if (!dialog.open) {
                    if (typeof dialog.showModal === 'function') dialog.showModal();
                    else dialog.setAttribute('open', '');
                }
            }

            function closeVenuePicker() {
                const dialog = document.getElementById('venueTimetablePicker');
                if (!dialog) return;
                if (dialog.open && typeof dialog.close === 'function') dialog.close();
                else dialog.removeAttribute('open');
            }

            (function initVenuePicker() {
                const dialog = document.getElementById('venueTimetablePicker');
                if (!dialog) return;
                const search = dialog.querySelector('[data-venue-picker-search]');
                const closeBtn = dialog.querySelector('[data-venue-picker-close]');

                closeBtn?.addEventListener('click', closeVenuePicker);
                search?.addEventListener('input', function () {
                    renderVenuePicker(search.value);
                });
                dialog.addEventListener('click', function (event) {
                    if (event.target === dialog) closeVenuePicker();
                });

                /* Capture phase runs before VenueDropdown's own trigger
                   listener, so a phone tap never opens the desktop cascade. */
                document.addEventListener('click', function (event) {
                    if (window.innerWidth > 768) return;
                    const target = event.target;
                    const trigger = target && target.closest
                        ? target.closest('#venueSelectDropdown .venue-dd-trigger')
                        : null;
                    if (!trigger) return;
                    event.preventDefault();
                    event.stopPropagation();
                    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
                    openVenuePicker();
                }, true);

                window.addEventListener('resize', function () {
                    if (window.innerWidth > 768) closeVenuePicker();
                });
            })();

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

            /* 2026-11-10 (user): the room description is a CHIP, not a line —
               byte-identical to the arrangement page's `#arrVenueDescription`
               fill: the same info glyph, the description in the hover tip and
               `Room description: …` in the aria label. Served from the DB via
               the venue payload (canonical always). */
            const venueDescEl = document.getElementById('venueDescription');
            if (venueDescEl && venueInfo.description) {
                venueDescEl.textContent = '';
                venueDescEl.insertAdjacentHTML('beforeend',
                    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>');
                venueDescEl.setAttribute('data-tip', venueInfo.description);
                venueDescEl.setAttribute('aria-label', 'Room description: ' + venueInfo.description);
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
                        { label: 'Total Students', value: window.totalStudentsLabel(event) }
                        /* "Room Name" and "Capacity" used to sit here (user, 2026-11-10):
                           the room is already the Schedule's Venue row, so the name was
                           printed twice in one pop-up — the seats now ride WITH the venue
                           (`B002 — Tutorial Room (35 seats)`) and the two rows are gone. */
                    ],
                    /* only this page knows the room's seat count */
                    venueSeats: venueInfo.capacity || null
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
               for the post-selection check.
               cancelled-slot-reuse S20: CANCELLED history is not occupancy —
               it is ignored here, so a freed range below never limits a span. */
            function isHistoryEvent(ev) { return ev.status === 'cancelled'; }

            /* cancelled-slot-reuse A21: the served room matrix is the REAL
               room truth for the viewed week — 'free' only where the venue's
               own time_slots rows are all available; 'blocked' where any row
               holds the cell (pre-cleanup raw occupancy with no active holder
               included); missing = no offer row. Fail closed: the green /
               bookable decisions below consult it, because event geometry
               alone cannot see a raw occupied cell. */
            function roomCellState(di, hi) {
                const week = roomCellStatesByWeek[currentWeek];
                if (!week) return 'nooffer';
                const state = week[di + ':' + hi];
                return state === undefined ? 'nooffer' : state;
            }

            /* First hour after `hi` whose real cell is NOT a free offer (held
               row). Infinity = none — the room run ends there even when no event
               geometry covers the cell.

               2026-11-14 (venue-evening-tail, user): the scan stops at the day's
               LAST served row. The half-hours after it carry no slot row at all
               and are no longer treated as a blocker, which is what puts
               17:30–18:00 back to a plain green cell. A hole INSIDE the served
               range still ends the run (A21 preserved). */
            function nextActualBlockAfter(di, hi) {
                const last = lastOfferIndex(di);
                for (let k = hi + 1; k <= last; k++) {
                    if (roomCellState(di, k) !== 'free') return k;
                }
                return Infinity;
            }

            /* The LAST half-hour of this day that has a real room row. A `blocked`
               (held) row counts as served on purpose: it is a real row, just not
               free, so it must end a run and must not extend the tail. -1 = the
               day offers nothing at all. */
            function lastOfferIndex(di) {
                const week = roomCellStatesByWeek[currentWeek];
                if (!week) return -1;
                let last = -1;
                for (let k = 0; k < hours.length; k++) {
                    if (week[di + ':' + k] !== undefined) last = k;
                }
                return last;
            }

            /* 2026-11-14 (venue-evening-tail, user): the grid always draws 08:00–19:00
               but a room's rows stop at 17:30, so the last two half-hours had no
               cell at all — a blank hole at the right edge of every day. They are
               painted as available cells instead. The guard keeps the change honest:
               only a day whose last served row sits in the grid's FINAL hour gets
               them, so a room that closes at midday is never painted green to the
               evening. */
            function isServedTail(di, hi) {
                const last = lastOfferIndex(di);
                return last >= hours.length - 3 && hi > last;
            }

            function nextEventStartAfter(di, hi) {
                const dayEvents = eventsData[currentWeek] || [];
                let next = Infinity;
                dayEvents.forEach(function (ev) {
                    if (ev.di !== di || isHistoryEvent(ev) || ev.start <= hi) return;
                    if (ev.start < next) next = ev.start;
                });
                return next;
            }

            /* Would a LIVE event already sit inside [hi, hi + spanSlots)? Lifted out
               of the modal closure so stage 1 (hover) and stage 2 (after the
               class is picked) can never disagree. Cancelled history never
               blocks a span (S20); the room matrix does (A21) — a held row or
               a missing offer ends the span even with no event over it. */
            function spanBlocked(di, hi, spanSlots) {
                const spanTo = Math.min(hi + spanSlots, hours.length);
                for (let k = hi; k < spanTo; k++) {
                    if (roomCellState(di, k) !== 'free') return true;
                }
                return (eventsData[currentWeek] || []).some(function (ev) {
                    return ev.di === di && !isHistoryEvent(ev)
                        && (hi < ev.end + 1) && (spanTo - 1 >= ev.start);
                });
            }

            /* Does a LIVE (non-cancelled) event occupy one cell? Real room
               occupancy — pending and occupied still block; cancelled history
               never does (S20). */
            function cellBlockedByLive(di, hi) {
                return (eventsData[currentWeek] || []).some(function (ev) {
                    return ev.di === di && !isHistoryEvent(ev)
                        && hi >= ev.start && hi <= ev.end;
                });
            }

            /* The base availability row exists ONLY on days where history
               visually covers a cell that is otherwise selectable (free of
               live occupancy, inside the open bookable window — not Sunday,
               not a public holiday, not too soon, AND a real free offer in
               the room matrix (A21): a pre-cleanup raw occupied cell under
               history is neutral, not selectable). Those are the days that
               need the separation (D6/S20); every other day keeps today's
               height. */
            function dayNeedsBaseRow(di) {
                const day = weekData[currentWeek].days[di];
                if (!day || day.sunday || day.holiday) return false;
                if (isSlotTooSoon(weekData, currentWeek, di)) return false;
                const dayEvents = (eventsData[currentWeek] || []).filter(function (ev) { return ev.di === di; });
                for (let hi = 0; hi < hours.length; hi++) {
                    const coveredByHistory = dayEvents.some(function (ev) {
                        return isHistoryEvent(ev) && hi >= ev.start && hi <= ev.end;
                    });
                    if (coveredByHistory && !cellBlockedByLive(di, hi) && roomCellState(di, hi) === 'free') return true;
                }
                return false;
            }

            /* Room between a free cell and the NEXT real block, in half-hour
               slots. The next block is the nearer of the next live event and the
               first non-free SERVED cell (A21) — a raw occupied row with no event
               geometry still ends the run — and never later than the grid's own
               end (2026-11-14, venue-evening-tail): the unoffered tail is not a
               blocker, so the answer is always finite (`hours.length - hi`). */
            function roomBeforeNextEvent(di, hi) {
                const next = Math.min(nextEventStartAfter(di, hi), nextActualBlockAfter(di, hi), hours.length);
                return next - hi;
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

                /* 2026-11-14 (tooltip-viewport-clamp, user): the box used to be pinned
                   to the cell's left edge whatever the window width — near the right
                   edge it ran off-screen and its text was clipped, and narrowing the
                   window left it stranded. Every path (click AND the B shortcut) now
                   goes through ONE placement: measure the box, pull it inside the
                   window, and open it above the cell when there is no room below. */
                tooltip._anchorCell = cell || (ev && ev.target) || null;
                tooltip.classList.add('show');
                placeAvailableTooltip();
            }

            /* Place the open tooltip against its anchor cell. Re-run on resize, so a
               narrowing window can never leave it outside the viewport.
               Two measured passes: the first fixes the coordinates, the second
               absorbs the few pixels the text gains once it re-wraps at the new
               width (otherwise the box could end exactly on the window edge). */
            function placeAvailableTooltip() {
                const t = document.getElementById('availableTooltip');
                const cell = t._anchorCell;
                if (!cell || !cell.isConnected) {
                    hideAvailableTooltip();
                    return;
                }
                const rect = cell.getBoundingClientRect();
                /* A narrow window can push the grid sideways and take the cell out of
                   sight. The box still stays READABLE (the clamp below pins it inside
                   the window) rather than vanishing under the user's cursor — the
                   Arrange action it carries still belongs to that cell. */
                const m = 8;
                t.style.transform = '';
                /* Measure at the widest the window allows, NOT at the final spot: a
                   shrink-to-fit fixed box takes its width from the space left of it,
                   so measuring after moving it would re-wrap it narrower and the
                   next clamp would move it again (a loop that ends on the edge). At
                   left = m its natural width is final, and the clamp below always
                   leaves room for exactly that width. */
                t.style.left = m + 'px';
                t.style.top = m + 'px';
                const box = t.getBoundingClientRect();

                let left = Math.min(rect.left, window.innerWidth - box.width - m);
                left = Math.max(m, left);
                let top = rect.bottom + 4;
                if (top + box.height + m > window.innerHeight) {
                    const above = rect.top - box.height - 4;
                    top = above >= m ? above : Math.max(m, window.innerHeight - box.height - m);
                }
                t.style.left = Math.round(left) + 'px';
                t.style.top = Math.round(top) + 'px';
            }

            function hideAvailableTooltip() {
                const t = document.getElementById('availableTooltip');
                t.classList.remove('show');
                t.style.transform = '';
                t._anchorCell = null;
            }

            /* close tooltip on scroll (cell position is no longer relevant) */
            window.addEventListener('scroll', hideAvailableTooltip, { passive: true });
            /* …but a RESIZE keeps it: re-place it against the same cell (hiding only
               when the cell itself is gone) — 2026-11-14, tooltip-viewport-clamp. */
            window.addEventListener('resize', function () {
                const t = document.getElementById('availableTooltip');
                if (!t.classList.contains('show')) return;
                /* after the layout settles, not during it */
                requestAnimationFrame(function () { placeAvailableTooltip(); });
            }, { passive: true });
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
                /* the pop-up sits BELOW the tooltip (overlay 999 vs 9999), so the
                   tooltip must go — otherwise it floats over the dimmed page and a
                   resize would re-place it there (tooltip-viewport-clamp review). */
                hideAvailableTooltip();
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

            /* ═══ The paper (SDD timetable-print-v2, 2026-11-16) ═══
               The sheet is its own table, built by the SAME builder (design §1). It is
               built on the DEFAULT path — no `cellRender`, no `laneLayout`, no
               `baseRowDays` — because on paper there are no free / no-room / too-soon /
               held / holiday cells to blank, only class blocks (PR-15), and because the
               builder's empty-week panel only runs without `cellRender`, so a week whose
               only blocks are pending prints the truth instead of a blank table (PR-11).
               Venue puts the COURSE in the top-left corner (the page already names the
               venue) and leads with the COHORTS in the centre, one per line. Ownership
               is the payload's own `e.mine` (the server's truth), which is what the
               key's ownership line promises. */
            function printCohortLines(e) {
                if (e.cohorts && e.cohorts.length) return e.cohorts.slice();
                return e.cohort ? [e.cohort] : [];
            }

            function printBlockShape(e) {
                var cohorts = printCohortLines(e);
                return {
                    cornerLeft: e.code,
                    cornerRight: printSurname(e.lecturer),
                    centre: cohorts.length ? cohorts : [e.code],
                    note: printNoteText(buildReplacementNote(e, {
                        checkOwnership: function (ev) { return ev.mine === true; },
                    })),
                };
            }

            function printBlockClass(div, e, isHoliday) {
                if (isHoliday || e.status === 'conflict' || e.status === 'cancelled') div.classList.add('print-conflict');
                else if (e.status === 'replacement') div.classList.add('print-replacement');
                else div.classList.add('print-normal');
                div.classList.add(e.mine === true ? 'print-mine' : 'print-others');
            }

            function buildPrintSheet() {
                PrintSheet.init({
                    build: function (normal) {
                        var weekEvents = (eventsData[currentWeek] || []);
                        return {
                            /* the venue page has no semester union: on a room sheet one
                               teaching week IS the semester's pattern, so the normal view
                               filters this week (design §3.3) */
                            events: normal
                                ? weekEvents.filter(function (e) { return e.status === 'normal'; })
                                : printRunSet(weekEvents),
                            days: normal ? printNormalDays() : weekData[currentWeek].days,
                            weekCount: normal
                                ? 'Week 1 ~ ' + MockData.semester.weeks
                                : 'Week ' + (currentWeek + 1) + ' of ' + MockData.semester.weeks,
                            weekDates: normal ? '(Whole Semester)' : ' · ' + weekData[currentWeek].range,
                            emptyTitle: normal ? 'No normal classes.' : 'No classes this week.',
                            emptyText: normal
                                ? 'This venue carries no normal class in the semester.'
                                : 'This venue is free all week.',
                        };
                    },
                    printShape: printBlockShape,
                    printClassFn: printBlockClass,
                });
            }

            function buildTimetable() {
                currentWeek = weekNav.currentWeek;
                countedAvailable = 0;
                /* 2026-11-14 (user): hovering a too-soon cell now explains the
                   rule and names the real earliest bookable day, the same words
                   the arrangement page uses. The date is NOT hard-coded: it is
                   the SHARED leadTimeCutoff() that already decides which cells
                   are too soon, so the words can never drift from the rule.
                   Computed once per render, not once per cell. */
                const tooSoonTip = (function () {
                    const cutoff = leadTimeCutoff(weekData);
                    const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                    return 'Rule : Only bookable at least 3 working days from today.'
                        + '\nEarliest Bookable : ' + days[cutoff.getDay()] + ', '
                        + cutoff.getDate() + ' ' + months[cutoff.getMonth()] + ' ' + cutoff.getFullYear();
                })();
                /* cancelled-slot-reuse S20: the base availability row is
                   offered ONLY where history would otherwise cover a
                   selectable free cell — decided from the real payload's
                   live statuses, never from the cancelled geometry alone. */
                const baseRowDays = [];
                (weekData[currentWeek].days || []).forEach(function (_, di) {
                    if (dayNeedsBaseRow(di)) baseRowDays.push(di);
                });
                buildTimetableGrid({
                    events: eventsData[currentWeek] || [],
                    days: weekData[currentWeek].days,
                    /* parity with the arrangement grid: the semester's earliest
                       bookable day carries the "Bookings open" chip (data-driven
                       via generateWeekData's firstBookable flag) */
                    bookableBadge: true,
                    /* cancelled-slot-reuse S18: stacked history lanes — the
                       shared PURE planner stacks cancelled history above the
                       active records it overlaps; the arrangement grid is
                       NOT stacked (it never passes laneLayout). */
                    laneLayout: true,
                    baseRowDays: baseRowDays,
                    /* Page paints every cell (venue-event-blocks-db design §1):
                       cellRender mode = the frozen two-axis block language.
                       PH/Sunday empties get offday cells; too-soon renders
                       read-only; free slots are bookable (U2a Arrange
                       affordance); blocks carry colour=status + border=ownership. */
                    cellRender: function (td, di, hi, day, info, ctx) {
                        /* Stacked custom-cell context (cancelled-slot-reuse
                           S18/S20) — the shared builder's BACKWARD-COMPATIBLE
                           sixth argument. Geometry + real-occupancy facts of
                           THIS cell across the whole day:
                             lane / laneCount / base — which physical row this is;
                             covered — ANY event (history included) occupies it;
                             blocked — a LIVE (non-cancelled) one does (real
                               room occupancy: pending/occupied still block);
                             designated — the ONE selectable instance of a free
                               cell (lane 0, or the base row when history covers
                               it), so history lanes never duplicate the green
                               booking controls and the base row never doubles
                               a cell lane 0 already exposes. */
                        ctx = ctx || { lane: 0, laneCount: 1, base: false, covered: false, blocked: false, designated: true };
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
                            /* cancelled-slot-reuse S14/S19: the SAME identity
                               fields the default grid builder carries — the
                               payload's stable key plus the legacy sid/rid
                               jump contracts — so a block is findable by its
                               identity on every surface, never by coordinates. */
                            div.dataset.sid = String(e.sessionId ?? e.id);
                            if (e.requestId !== undefined && e.requestId !== null && e.requestId !== '') {
                                div.dataset.rid = String(e.requestId);
                            }
                            if (e.eventKey !== undefined && e.eventKey !== null && e.eventKey !== '') {
                                div.dataset.eventKey = String(e.eventKey);
                            }
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
                                /* timetable-print-v2 (2026-11-16): the ONE block on any
                                   page that did not speak the house time joiner — `'-'`
                                   where the shared builder and the rest of the app use
                                   `' ~ '` (design §9 / PR-03). This is a screen change
                                   the contract orders, not a print one, and it is the
                                   pin's single permitted difference. */
                                '<span class="ev-time">' + startTime + ' ~ ' + endTime + '</span>' +
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
                        } else if (!ctx.designated) {
                            /* Stacked geometry (S20): ANOTHER physical row owns
                               this column's booking control — a history lane's
                               copy of a free column, or a base-row hour lane 0
                               already exposes. Inert background only; never a
                               second green card, never a duplicated control. */
                            const div = document.createElement('div');
                            div.className = 'cell-content lane-spacer';
                            td.appendChild(div);
                        } else if (ctx.blocked) {
                            /* a LIVE pending/occupied blocker keeps the cell
                               blocked (S10/S20) — the room is not bookable
                               here, whatever the cancelled history looks like. */
                            const div = document.createElement('div');
                            div.className = 'cell-content lane-spacer';
                            td.appendChild(div);
                        } else if (isSlotTooSoon(weekData, currentWeek, di)) {
                            /* lead-time rule (frozen template :861–866): inside
                               the 3-working-day window — rendered read-only
                               (no Arrange tooltip, no keyboard). 2026-11-14
                               (user): it still explains itself on hover — the
                               in-cell two-line label is the short form, this
                               data-tip is the long form. */
                            const div = document.createElement('div');
                            div.className = 'cell-content cell-too-soon';
                            div.setAttribute('data-tip', tooSoonTip);
                            td.appendChild(div);
                        } else {
                            /* cancelled-slot-reuse A21: the served room matrix
                               gates the green. A DB-held cell (a pre-cleanup
                               raw occupied/pending row with no live holder to
                               render) reads as a NEUTRAL held cell — never a
                               fabricated NORMAL; a cell with no offer row
                               stays inert. Only a served free offer is
                               bookable — no missing-grid free fallback. */
                            const cellState = roomCellState(di, hi);
                            /* 2026-11-14 (venue-evening-tail, user): the half-hours
                               after the day's last served row are painted as
                               available cells, count as Available, and never wear the
                               red day-end class — the day-end red belongs to a REAL
                               offer that cannot fit. The 18:30 cell keeps the same
                               "Not Enough Slot" language the 17:30 cell used to
                               carry, because only 30 minutes are left. */
                            const tail = cellState === 'nooffer' && isServedTail(di, hi);
                            if (cellState === 'free' || tail) {
                                /* Available slot — frozen template :873–884. The
                                   AVAILABLE card counts ONLY what the grid renders
                                   bookable (user 2026-10-19: too-soon rows were
                                   inflating it) — this branch is the single
                                   counting point, so the card can never disagree
                                   with the cells on screen. */
                                countedAvailable++;
                                const div = document.createElement('div');
                                div.className = 'cell-content cell-available';
                                /* 2026-11-14 (venue-evening-tail, user): ONE language for
                                   "no class of yours fits here" — the green "Not Enough
                                   Slot" state. The old red "Too Late to Book" day-end arm is
                                   retired: with the evening slots live, 18:30–19:00 is a real
                                   cell with only 30 minutes left, and the user asked for it
                                   to read like its neighbour rather than paint the whole day
                                   red at the end. `roomBeforeNextEvent` already answers
                                   `hours.length - hi` at the day end, so no arm is needed. */
                                if (roomBeforeNextEvent(di, hi) < MIN_BOOK_SLOTS) div.classList.add('cell-no-room');
                                /* cancelled-slot-reuse A19: ONLY a BASE-row free offer
                                   carries its own time label — the phone's block-table
                                   layout turns the desktop absolute cell fill into normal
                                   flow, so a text-less cell collapsed to a thin green bar
                                   with no time. Safe textContent (never innerHTML), the
                                   page's own to12h/add30min pair, the standard `~` range
                                   joiner; lane-0 cells keep the desktop hover affordance
                                   and no geometry changes. */
                                if (ctx.base) {
                                    div.classList.add('cell-base-offer');
                                    const baseTime = document.createElement('span');
                                    baseTime.className = 'cell-time-label';
                                    baseTime.textContent = to12h(hours[hi]) + ' ~ ' + to12h(hours[hi + 1] || add30min(hours[hi]));
                                    div.appendChild(baseTime);
                                }
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
                            } else {
                                const div = document.createElement('div');
                                div.className = 'cell-content ' + (cellState === 'blocked' ? 'cell-held' : 'lane-spacer');
                                td.appendChild(div);
                            }
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
                buildPrintSheet();
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
                    /* 2026-11-11: `target.day` is MONDAY-based (weekData.days runs
                       Mon→Sun), so indexing a Sunday-first name list with it named the
                       day before ("Sunday, 16 Nov 2026"). The name is derived from the
                       date it is printed beside — DateHelper.weekdayName. */
                    const dayFacts = weekData[target.week].days[target.day];
                    const dayName = DateHelper.weekdayName(dayFacts.date);
                    btn.setAttribute('data-tip', 'Jump to the earliest bookable slot \u2014 '
                        + (dayName ? dayName + ', ' : '') + dayFacts.date + ' (Week ' + (target.week + 1) + ')');
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
            /* cancelled-slot-reuse: the stacked-geometry helpers ride `window`
               for the same reason eventsData does — receipts assert the served
               truth (history-ignoring spans/room math) instead of scraping
               pixels. Read-only. */
            window.nextEventStartAfter = nextEventStartAfter;
            window.spanBlocked = spanBlocked;
            window.roomBeforeNextEvent = roomBeforeNextEvent;
            window.roomCellState = roomCellState;
            window.closeModal = closeModal;
            window.closeModalOutside = closeModalOutside;
        });
    </script>
</div>
