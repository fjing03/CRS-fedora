{{-- The frozen UI design from ui-design-templates/, byte-for-byte, with only
     the data source bridged to the backend (see the BRIDGE marks below). The
     component's render() applies layouts.ui-template — no @extends here
     (Livewire full-page: exactly one root element in the content section). --}}

@section('title', 'Replacement Arrangement — Class Replacement System')

@section('page-styles')

        /* Hover chip removed — the preview block carries the message instead */
        .cell-available::after { content: none; }
        .event-selection-preview .preview-label {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            padding: 3px 12px;
            border-radius: 999px;
            background: var(--color-tertiary);
            color: var(--color-on-tertiary);
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }
        .event-selection-preview.preview-fail .preview-label {
            background: var(--color-error);
            color: var(--color-on-error);
        }

        .top-bar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 50;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px 64px;
            pointer-events: none;
        }
        .top-bar > * { pointer-events: auto; }

        .back-btn {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            height: 36px;
            padding: 0 14px;
            border-radius: var(--radius-xl);
            border: none;
            background: var(--color-primary);
            color: var(--color-on-primary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
            transition: transform 0.15s, box-shadow var(--transition);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1), 0 1px 3px rgba(0, 0, 0, 0.06);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .back-btn:hover { transform: translateY(-50%) scale(1.04); box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15), 0 1px 4px rgba(0, 0, 0, 0.08); }
        .back-btn:active { transform: translateY(-50%) scale(0.96); }

        .top-center {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .top-logo {
            height: 32px;
            width: auto;
            display: block;
            cursor: pointer;
        }
        .top-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--color-on-surface);
            letter-spacing: -0.3px;
        }

        .theme-toggle {
            position: fixed;
            top: 14px;
            right: 16px;
            z-index: 50;
            width: 36px;
            height: 36px;
            border-radius: var(--radius-md);
            border: 1px solid var(--color-outline);
            background: var(--color-surface);
            color: var(--color-on-surface-variant);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            transition: background var(--transition), transform 0.15s, border-color var(--transition);
            box-shadow: var(--shadow-sm);
        }
        .theme-toggle:hover { transform: scale(1.08); }
        .theme-toggle:active { transform: scale(0.95); }

        .app-container {
            padding-top: 68px;
        }

        .selector-dropdown {
            height: 36px;
            padding: 0 32px 0 12px;
            border-radius: var(--radius-md); /* the week-select family */
            border: 1px solid var(--color-outline);
            background: var(--color-surface);
            color: var(--color-on-surface);
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: border-color 0.15s, box-shadow 0.15s;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%239EAAB8' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 10px center;
        }
        .light .selector-dropdown {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%235A6978' stroke-width='1.5' fill='none' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        }
        .selector-dropdown:hover { border-color: var(--color-primary); }
        .selector-dropdown:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(var(--color-primary-rgb, 0, 77, 152), 0.15); }
        .selector-dropdown option { background: var(--color-surface); color: var(--color-on-surface); }

        .toolbar-center {
            text-align: center;
            flex: 1;
            min-width: 0;
        }
        /* venue-description-doc-b006-rule: the plain room description line */
        .toolbar-center .venue-description {
            font-size: 12px;
            color: var(--color-on-surface-variant);
            margin-top: 2px;
        }
        .toolbar-center .toolbar-subtitle {
            font-size: 14px;
            font-weight: 500;
            color: var(--color-on-surface-variant);
        }
        .toolbar-center .toolbar-meta {
            font-size: 13px;
            color: var(--color-on-surface-variant);
            margin-top: 2px;
            opacity: 0.8;
        }
        .toolbar-center .selector-dropdown {
            margin-bottom: 4px;
        }
        .toolbar-center .selector-dropdown:disabled {
            opacity: 0.7;
            cursor: var(--cursor-cancel);
        }

        /* ── Toolbar Restructure (2-zone) ── */
        .toolbar-restructure {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            flex-wrap: wrap;
        }
        .toolbar-primary {
            display: flex;
            flex-direction: row; /* slot picker sits to the right of the subject selector */
            flex-wrap: wrap; /* drops below the select when the row is too tight */
            align-items: center;
            gap: 12px;
            flex: 1;
            min-width: 280px;
        }
        .toolbar-primary .selector-dropdown {
            flex: 1 1 220px;
            min-width: 0;
            max-width: 225px; /* subject label ellipsizes before the week select does */
        }
        .toolbar-primary .toolbar-subtitle {
            font-size: 14px;
            font-weight: 500;
            color: var(--color-on-surface);
        }
        /* ── Booking intent banner (arrival from venue-timetable "Book" action) ── */
        .booking-intent {
            display: flex;
            align-items: center;
            gap: 8px;
            background: var(--color-primary-container);
            color: var(--color-on-primary-container);
            border: 1px solid color-mix(in srgb, var(--color-primary) 35%, transparent);
            border-radius: var(--radius-md);
            padding: 8px 12px;
            font-size: 13px;
            margin: 0 0 10px;
        }
        .booking-intent svg { flex-shrink: 0; }
        .booking-intent strong { font-weight: 600; }
        .bi-close {
            margin-left: auto;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 16px;
            line-height: 1;
            padding: 2px 6px;
            border-radius: var(--radius-sm);
        }
        /* conflict variant: booked slot can't be auto-selected */
        .booking-intent-manual {
            background: var(--color-error-container);
            color: var(--color-on-error-container);
            border-color: color-mix(in srgb, var(--color-error) 35%, transparent);
        }
        .semester-chip { display: none; }
        .page-header .semester-chip { display: none; }
        .toolbar-filters {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
        }
        @media (min-width: 769px) and (max-width: 1200px) {
            /* the week-nav group wraps to its own right-aligned line BEFORE the
               subject/venue pair gets squeezed by the wider full-label select */
            .toolbar-filters {
                flex: 1 0 100%;
                justify-content: flex-end;
            }
        }
        .toolbar-center {
            display: flex;
            align-items: center;
            gap: 6px; /* venue trigger + its chips need breathing room */
            flex: 0 0 auto; /* content-sized — leftover space goes to the subject/slot pair */
        }
        .toolbar-center .venue-dd-trigger {
            min-width: 0;
            max-width: 216px; /* widest current label + arrow; longer names ellipsize (tip carries the full name) */
        }

        /* ── Slot Dropdown (custom) ── width 60% wider (user, 2026-10-24):
           trigger flex-basis 180→288 / cap 240→384, panel 320→512 (×1.6). */
        .slot-dd {
            position: relative;
            flex: 1 1 288px;
            max-width: 384px;
        }
        .slot-dd-trigger {
            display: flex;
            align-items: center;
            gap: 8px;
            height: 36px;
            padding: 0 12px;
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md); /* the week-select family */
            background: var(--color-surface);
            color: var(--color-on-surface);
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: border-color 0.15s, box-shadow 0.15s;
            width: 100%;
            text-align: left;
        }
        .slot-dd-trigger span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .slot-dd-trigger:hover {
            border-color: var(--color-primary);
        }
        .slot-dd-trigger svg {
            margin-left: auto;
            flex-shrink: 0;
            opacity: 0.6;
        }
        .slot-dd-panel {
            display: none;
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            min-width: 512px; /* 320 → 512: the user's 60%-wider rule (2026-10-24) */
            max-height: 280px;
            overflow-y: auto;
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            z-index: 50;
        }
        .slot-dd-panel.open {
            display: block;
        }
        .slot-dd-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            cursor: pointer;
            transition: background 0.12s;
            border-bottom: 1px solid var(--color-outline-variant);
        }
        .slot-dd-item:last-child {
            border-bottom: none;
        }
        .slot-dd-item:hover {
            background: var(--color-surface-variant);
        }
        .slot-dd-item.selected {
            background: var(--color-primary-container);
        }
        .slot-dd-item-label {
            flex: 1;
            font-size: 13px;
            color: var(--color-on-surface);
        }
        .slot-dd-item-badge {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: var(--radius-lg);
        }
        /* SDD rearrangement-toolbar-tooltips Batch 2 (req 3b): the cohort
           tag inside the slot label — the users icon + the short names,
           theme tokens only */
        .slot-dd-item-cohort {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: var(--color-on-surface-variant);
            white-space: nowrap;
        }
        .slot-dd-item-cohort svg {
            flex-shrink: 0;
        }

        .slot-dd-item-date {
            font-size: 12px;
            color: var(--color-on-surface-variant);
            margin-left: auto;
            white-space: nowrap;
            flex-shrink: 0;
        }

        .slot-dd-tooltip {
            display: none;
            position: absolute;
            padding: 6px 12px;
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            font-size: 12px;
            color: var(--color-on-surface);
            white-space: nowrap;
            pointer-events: none;
            z-index: 60;
        }

        .cell-selected {
            background: var(--color-primary-container);
            border: 2px solid var(--color-primary);
            box-shadow: inset 0 0 0 1px var(--color-primary);
            cursor: pointer;
        }
        /* Selected block: time range at rest; hover/focus shows the theme's
           danger pill (theme.css .event-block.event-selection::after) */
        .event-selection .ev-time-label {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            color: var(--color-on-primary-container);
            transition: opacity 0.15s;
        }
        .event-selection:hover .ev-time-label,
        .event-selection:focus-visible .ev-time-label { opacity: 0; }

        .cell-time-label { display: none; }

        /* `.footer-area` / `.footer-left` / `.footer-right` were REMOVED here
           2026-11-10 (user, "UI Update — Action Area"). The buttons live in
           `.sel-summary-action`, right of the tip. */

        .selection-counter {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--color-on-surface-variant);
            padding: 10px 14px;
            background: var(--color-surface-variant);
            border-radius: var(--radius-sm);
            height: 40px;
        }
        .selection-counter .count-num {
            font-weight: 700;
            color: var(--color-primary);
        }
        .selection-counter .count-max {
            font-weight: 600;
            color: var(--color-on-surface-variant);
        }

        .btn {
            padding: 10px 22px;
            border-radius: var(--radius-md);
            border: none;
            font-family: inherit;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.15s, box-shadow var(--transition), background var(--transition);
        }
        .btn:active { transform: scale(0.97); }



        .btn-primary {
            background: var(--color-primary);
            color: var(--color-on-primary);
            transition: transform 0.15s, filter 0.15s, box-shadow var(--transition);
        }
        /* Hover/active mirror the shared .btn-action design (replacement-home) */
        .btn-primary:hover {
            filter: brightness(1.08);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        .btn-primary:active {
            transform: scale(0.97);
        }

        /* ── When block is selected, disable hover on other cells ── */
        .has-selection .cell-available { cursor: var(--cursor-cancel) !important; }
        .has-selection .cell-available:hover { filter: none; box-shadow: none; }
        .has-selection .cell-available:hover::after { opacity: 0 !important; }

        /* Grid locked until a subject is picked — free cells read as not-allowed */
        .timetable.no-subject .cell-available { cursor: var(--cursor-cancel); }
        .attention-pulse { animation: attention-pulse 1.2s ease-out 1; }
        @keyframes attention-pulse {
            0%   { box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-primary) 45%, transparent); }
            70%  { box-shadow: 0 0 0 8px color-mix(in srgb, var(--color-primary) 0%, transparent); }
            100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-primary) 0%, transparent); }
        }

        /* Footer button tooltips — the wrapper span keeps hover alive while the
           inner button is disabled (disabled controls swallow mouse events) */
        .btn-tip { display: inline-flex; cursor: var(--cursor-cancel); }
        .btn-tip .btn:disabled { pointer-events: none; }

        .btn-primary:disabled {
            opacity: 0.35;
            cursor: var(--cursor-cancel);
            filter: none !important;
            box-shadow: none !important;
            transform: none !important;
        }
        .btn-primary:disabled:hover { filter: none !important; box-shadow: none !important; }

        /* ===== Selection Summary ===== */
        .sel-summary {
            margin-top: 14px;
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            transition: background var(--transition), border-color var(--transition);
            overflow: hidden;
        }

        .sel-summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            border-bottom: 1px solid var(--color-outline);
        }

        .sel-summary-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--color-on-surface);
        }

        .sel-summary-count {
            font-size: 13px;
            font-weight: 500;
            color: var(--color-on-surface-variant);
        }
        .sel-summary-count strong {
            color: var(--color-primary);
            font-weight: 700;
        }

        .sel-summary-empty {
            padding: 44px 20px;
            text-align: center;
            color: var(--color-on-surface-variant);
        }
        .sel-summary-empty svg {
            width: 44px; height: 44px;
            opacity: 0.25;
            margin-bottom: 10px;
            color: var(--color-on-surface-variant);
        }
        .sel-summary-empty p {
            font-size: 13px;
            line-height: 1.7;
        }
        .sel-summary-empty p:first-of-type {
            font-weight: 500;
            color: var(--color-on-surface);
            margin-bottom: 4px;
        }

        .sel-summary-grid {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
            gap: 10px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--color-outline);
        }

        .sel-summary-card {
            background: var(--color-primary-container);
            color: var(--color-on-primary-container);
            border: 1px solid transparent;
            border-radius: var(--radius-md);
            padding: 12px 14px;
            position: relative;
            animation: cardSlideIn 0.2s ease;
            transition: background 0.2s, border-color 0.15s, transform 0.15s;
        }
        .sel-summary-card:hover {
            transform: translateY(-2px);
            border-color: var(--color-primary);
        }

        /* SDD summary-card-click-navigate: the clickable-card affordance */
        .sel-summary-card { cursor: pointer; }
        .sel-summary-card:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }

        @keyframes cardSlideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes cardFadeOut {
            from { opacity: 1; transform: scale(1); }
            to { opacity: 0; transform: scale(0.95); }
        }
        .sel-summary-card.card-removing {
            animation: cardFadeOut 0.2s ease forwards;
            pointer-events: none;
        }


        /* 2026-11-10 (user, "UI Update 1 — Selected Summary Card"): FOUR rows in a
           fixed order — Venue / Week & Date / Time / Duration — each with its own
           icon and ALL of them at one size, one weight, one colour. The old four
           classes had 13/14/11/12 px and 700/600/—/500 weights, so the card read as
           three different things. */
        .card-row {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.5;
            color: inherit;
        }
        .card-row + .card-row { margin-top: 1px; }
        .card-row svg { flex: 0 0 auto; opacity: 0.85; }
        .card-row .card-row-text { min-width: 0; }

        .card-remove {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 24px; height: 24px;
            border-radius: var(--radius-sm);
            border: none;
            background: transparent;
            color: var(--color-on-primary-container);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            line-height: 1;
            transition: background 0.15s, color 0.15s;
            opacity: 0.5;
        }
        .card-remove:hover {
            background: var(--color-error-container);
            color: var(--color-error);
            opacity: 1;
        }

        .sel-summary-info {
            display: none;
            padding: 14px 20px;
            border-bottom: 1px solid var(--color-outline);
        }
        .info-rows {
            display: flex;
            flex-wrap: wrap;
            gap: 16px 36px;
        }
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--color-on-surface);
        }

        /* 2026-11-11 (user, "UI Update — Compact Tip Tooltip"): the long hint is
           now a compact [💡 Tip] trigger immediately LEFT of Submit Request. */
        .sel-summary-action {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
            padding: 12px 20px;
            /* no rule of its own: the grid above already ends with one, and the
               info strip adds a second when it is showing — a border here would
               double it. The tip row had none before this change either. */
        }
        .sel-summary-tip-trigger {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 32px;
            padding: 0 11px;
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-md);
            background: transparent;
            color: var(--color-on-surface-variant);
            font-family: inherit;
            font-size: 12px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
            cursor: help;
            transition: background 0.15s, color 0.15s, border-color 0.15s;
        }
        .sel-summary-tip-trigger svg { flex-shrink: 0; }
        .sel-summary-tip-trigger:hover,
        .sel-summary-tip-trigger:focus-visible {
            background: var(--color-surface-variant);
            color: var(--color-on-surface);
        }
        .sel-summary-tip-trigger:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }
        .sel-summary-action #submitTip { flex: 0 0 auto; }

        @media (max-width: 1024px) {
            .sel-summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            /* the 7×22 grid cannot squeeze into a phone viewport — scroll it
               instead of clipping (selection needs the real grid) */
            .grid-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
            .grid-scroll table { min-width: 820px; }
            .top-title { font-size: 16px; }
            .top-logo { height: 26px; }
            .toolbar-restructure {
                flex-direction: column;
                align-items: stretch;
            }
            .toolbar-primary {
                min-width: 0;
                width: 100%;
            }
            .toolbar-primary .selector-dropdown {
                flex-basis: 100%;
                max-width: 100%;
            }
            .slot-dd {
                flex-basis: 100%;
                max-width: 100%;
            }
            .toolbar-filters {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                flex-basis: auto; /* column parent — basis would otherwise be height */
            }
            .toolbar-center {
                flex-direction: column;
                align-items: stretch;
            }
            .slot-dd-trigger {
                min-width: 0;
                width: 100%;
            }
            .slot-dd-panel {
                min-width: 100%;
            }
            /* the retired `.footer-area` overrides are replaced by the action row:
               on a phone the tip sits above a full-width Submit button */
            .sel-summary-action {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            .sel-summary-tip-trigger { align-self: center; }
            .sel-summary-action #submitTip { align-self: stretch; }
            .sel-summary-action .btn {
                width: 100%;
                justify-content: center;
                padding: 8px 14px;
                font-size: 12px;
            }
            .time-header-col, .time-col { width: 14%; min-width: 120px; }
            .hour-header, .timetable td.hour-cell { min-width: 60px; }
            .header-logo { height: 44px; }
            .sel-summary-grid { grid-template-columns: 1fr; }
            .sel-summary-info .info-rows { flex-direction: column; gap: 10px; }

            /* Compact selection summary cards on mobile */
            .sel-summary-card {
                padding: 10px 12px;
            }
            .card-row { font-size: 11px; }
            .theme-toggle { display: none; }

            /* Compact timetable grid on mobile — overrides theme.css card-block layout */
            .timetable td.hour-cell {
                width: 48px;
                height: 48px;
                padding: 0;
                display: inline-block;
                position: relative;
            }
            .timetable tbody tr {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
            }
            .cell-content {
                position: static;
                width: 48px;
                height: 48px;
                min-height: 48px;
                font-size: 9px;
                padding: 2px;
            }
            .cell-time-label {
                position: absolute;
                inset: 0;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 12px;
                color: var(--color-on-surface-variant);
                line-height: 1.2;
                opacity: 0.7;
                pointer-events: none;
                text-align: center;
                white-space: pre-line;
            }
        }

        /* ───── F3: Progress Indicator ───── */
        .progress-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 8px 0;
        }
        .progress-bar {
            flex: 1;
            height: 8px;
            background: var(--color-outline);
            border-radius: var(--radius-xs);
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            border-radius: var(--radius-xs);
            transition: width 0.3s, background 0.3s;
            background: var(--color-outline);
        }
        .progress-text {
            font-size: 12px;
            color: var(--color-on-surface-variant);
            white-space: nowrap;
        }

        /* ───── F4: Venue Capacity Badge ───── */
        .venue-warning {
            color: var(--color-error);
            margin-left: 4px;
        }

        /* ───── F7: Toast Notification — handled by shared ui-common.js ───── */

        /* ───── F1: Keyboard Shortcuts ───── */
        .cell-focused {
            outline: 2px solid var(--color-primary);
            outline-offset: -2px;
        }
        .help-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.45);
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .help-overlay.active {
            display: flex;
        }
        .help-card {
            background: var(--color-surface);
            border-radius: var(--radius-lg);
            padding: 24px;
            max-width: 400px;
            width: 90%;
            box-shadow: var(--shadow-lg);
        }
        .help-card h3 {
            margin: 0 0 16px 0;
            font-size: 16px;
            color: var(--color-on-surface);
        }
        .help-card .shortcut-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
            color: var(--color-on-surface-variant);
        }
        .help-card .shortcut-key {
            font-family: monospace;
            background: var(--color-surface-variant);
            padding: 2px 6px;
            border-radius: var(--radius-xs);
            font-size: 12px;
        }
        .help-card .help-close {
            margin-top: 16px;
            text-align: right;
        }
        /* Desktop keeps the compact ×; the mobile sheet swaps in a text
           "Close" action to match the Saved Selection header. */
        .modal-close-text {
            display: none;
        }

        /* ───── SDD arrangement-mobile-day-view T06: responsive relocation shell ─────
           ONE matchMedia owner in public/js/arrangement-mobile.js adds
           `body.arrangement-mobile-mode` and moves the existing controls into the
           mobile partial. These rules only (a) keep the mobile root hidden until
           that activation, (b) defensively hide the now-empty desktop hosts while
           the mode class is on, and (c) make the moved originals simple/full-width.
           Full mobile composition, sticky clusters, 44px geometry and safe areas
           are T14's scope; desktop rules above are untouched. Colors: none added. */
        #arrangementMobile,
        #arrangementMobile[hidden] {
            display: none;
        }
        body.arrangement-mobile-mode #arrangementMobile:not([hidden]) {
            display: block;
        }

        @media (max-width: 768px) {
            /* The JS already sets hidden + inert on these; display:none is the
               defensive half because .legend-bar/.toolbar-restructure are flex
               and would otherwise beat the UA [hidden] rule. */
            body.arrangement-mobile-mode .toolbar-restructure,
            body.arrangement-mobile-mode .sel-summary,
            body.arrangement-mobile-mode .grid-wrapper,
            body.arrangement-mobile-mode .legend-bar {
                display: none;
            }

            #arrangementMobile {
                padding: 0 4px 24px;
            }
            #arrangementMobile .arrangement-mobile-controls,
            #arrangementMobile .arrangement-mobile-tools,
            #arrangementMobile .arrangement-mobile-day,
            #arrangementMobile .arrangement-mobile-actions {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin: 0 0 12px;
            }
            #arrangementMobile .arrangement-mobile-context {
                margin: 0 0 12px;
            }
            #arrangementMobile [data-arrangement-mobile-context-facts],
            #arrangementMobile [data-arrangement-mobile-subject-host],
            #arrangementMobile [data-arrangement-mobile-slot-host],
            #arrangementMobile [data-arrangement-mobile-venue-host],
            #arrangementMobile [data-arrangement-mobile-week-host],
            #arrangementMobile [data-arrangement-mobile-earliest-host],
            #arrangementMobile [data-arrangement-mobile-tip-host],
            #arrangementMobile [data-arrangement-mobile-submit-host] {
                display: flex;
                flex-direction: column;
                gap: 8px;
                min-width: 0;
            }
            #arrangementMobile #subjectSelector,
            #arrangementMobile .slot-dd,
            #arrangementMobile .slot-dd-trigger,
            #arrangementMobile #buildingSelectorDropdown,
            #arrangementMobile .venue-dd-trigger,
            #arrangementMobile .week-nav,
            #arrangementMobile #earliestBtn {
                width: 100%;
                max-width: 100%;
                flex: 0 0 auto;
            }
            #arrangementMobile .venue-side-chips {
                flex-wrap: wrap;
            }
            /* #submitTip owns #submitRequestBtn — stretch the existing pair,
               never a second submit control. */
            #arrangementMobile #submitTip {
                display: flex;
                width: 100%;
            }
            #arrangementMobile #submitTip .btn {
                width: 100%;
                justify-content: center;
            }
            /* T07: eligible-day buttons and the empty-semester message only.
               Sticky clusters, spacing and 44px polish remain T14. */
            #arrangementMobile [data-arrangement-mobile-days] {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
            }
            #arrangementMobile [data-arrangement-mobile-days] button {
                flex: 1 0 44px;
                flex-direction: column;
                gap: 1px;
                min-height: 52px;
                padding: 4px 6px;
                border: 1px solid var(--color-outline);
                border-radius: var(--radius-md);
                background: var(--color-surface);
                color: var(--color-on-surface);
                font: inherit;
                cursor: pointer;
            }
            #arrangementMobile .arrangement-mobile-day-weekday {
                font-size: 11px;
                line-height: 1.1;
            }
            #arrangementMobile .arrangement-mobile-day-number {
                font-size: 14px;
                line-height: 1.1;
                font-weight: 700;
            }
            #arrangementMobile [data-arrangement-mobile-days] button[aria-pressed="true"] {
                border-color: var(--color-primary);
                background: var(--color-primary-container);
                color: var(--color-on-primary-container);
            }
            #arrangementMobile [data-arrangement-mobile-empty] {
                margin: 0;
                padding: 12px;
                border: 1px solid var(--color-outline);
                border-radius: var(--radius-sm);
                color: var(--color-on-surface-variant);
            }
            /* T09: one-day timeline rows and the continuous blue saved span.
               Rows are real buttons painted ONLY by the canonical cell-*
               classes the snapshot port carries; the span is drawn from saved
               start/end indexes. Sticky placement, measured insets, safe areas
               and 44/48px polish stay T14. */
            #arrangementMobile [data-arrangement-mobile-timeline] {
                display: grid;
                grid-auto-rows: minmax(var(--arrangement-mobile-row-height, 44px), auto);
                gap: 4px;
                min-width: 0;
            }
            /* T07 hides the host with the `hidden` property; this rule must
               keep losing to it while the semester is empty. */
            #arrangementMobile [data-arrangement-mobile-timeline][hidden] {
                display: none;
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-row,
            #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-span {
                position: relative;
                display: flex;
                align-items: center;
                gap: 10px;
                width: 100%;
                min-width: 0;
                padding: 8px 12px;
                border: 1px solid var(--color-outline);
                border-radius: var(--radius-sm);
                color: var(--color-on-surface);
                font: inherit;
                text-align: left;
                overflow-wrap: anywhere;
            }
            /* The canonical cell classes draw state fills in theme.css; their
               hover overlays are grid-cell mechanics, so retire them here. */
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-available::after,
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-occupied::after,
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-pending::after,
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-reserved::after,
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-sun::after,
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-ph::after,
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-too-soon::after {
                content: none;
            }
            /* Readable foreground per canonical family (fills stay theme.css's). */
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-available {
                color: var(--color-on-success-container);
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-pending,
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-reserved {
                color: var(--color-on-tertiary-container);
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-occupied {
                color: var(--color-on-error-container);
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-selected {
                color: var(--color-on-primary-container);
                border-color: var(--color-primary);
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .cell-too-soon,
            #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-row--unknown {
                color: var(--color-on-surface-variant);
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-row--unknown {
                background: var(--color-surface-variant);
                cursor: not-allowed;
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-row-time {
                flex: 0 0 auto;
                font-variant-numeric: tabular-nums;
                font-weight: 600;
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-row-state {
                flex: 1 1 auto;
                min-width: 0;
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-span {
                flex-direction: column;
                align-items: flex-start;
                justify-content: center;
                gap: 2px;
                border: 2px solid var(--color-primary);
                background: var(--color-primary-container);
                color: var(--color-on-primary-container);
                cursor: pointer;
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-span-time {
                font-weight: 700;
            }
            #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-span-venue {
                font-size: 12px;
            }
            /* T11: one native information sheet + legend swatches + readable
               text. Minimal on purpose — T14 owns the full bottom-sheet
               geometry, measured/safe-area insets and 44px polish. Colours are
               theme tokens only; Pending/Conflict swatches stay borderless. */
            #arrangementMobile dialog.arrangement-mobile-info {
                width: min(560px, calc(100vw - 16px));
                max-width: calc(100vw - 16px);
                max-height: 85dvh;
                margin: auto auto 0;
                padding: 0;
                border: 1px solid var(--color-outline);
                border-radius: var(--radius-sm) var(--radius-sm) 0 0;
                background: var(--color-surface);
                color: var(--color-on-surface);
                overflow: auto;
            }
            #arrangementMobile dialog.arrangement-mobile-info::backdrop {
                background: var(--color-scrim);
            }
            #arrangementMobile dialog.arrangement-mobile-info .modal-header {
                gap: 8px;
                padding: 12px;
                border-bottom: 1px solid var(--color-outline);
            }
            #arrangementMobile dialog.arrangement-mobile-info .modal-header h2 {
                margin: 0;
                font-size: 1rem;
                overflow-wrap: anywhere;
            }
            #arrangementMobile dialog.arrangement-mobile-info .modal-body {
                padding: 12px;
                overflow-wrap: anywhere;
                white-space: pre-line;
            }
            #arrangementMobile dialog.arrangement-mobile-info .modal-body p {
                margin: 0;
            }
            #arrangementMobile dialog.arrangement-mobile-info .modal-footer {
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-end;
                padding: 12px;
                border-top: 1px solid var(--color-outline);
            }
            #arrangementMobile .arrangement-mobile-info-facts {
                list-style: none;
                margin: 0;
                padding: 0;
                display: flex;
                flex-direction: column;
                gap: 4px;
            }
            #arrangementMobile .arrangement-mobile-info-facts li {
                display: flex;
                flex-wrap: wrap;
                gap: 2px 8px;
            }
            #arrangementMobile .arrangement-mobile-info-fact-label {
                flex: 0 0 auto;
                font-weight: 600;
                color: var(--color-on-surface-variant);
            }
            #arrangementMobile .arrangement-mobile-info-fact-value {
                flex: 1 1 auto;
                min-width: 0;
                color: var(--color-on-surface);
            }
            #arrangementMobile .arrangement-mobile-info-reason {
                margin-top: 10px !important;
                color: var(--color-on-surface);
            }
            #arrangementMobile .arrangement-mobile-info-legend {
                list-style: none;
                margin: 0;
                padding: 0;
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            #arrangementMobile .arrangement-mobile-info-legend li {
                display: flex;
                align-items: flex-start;
                gap: 12px;
                padding: 10px 12px;
                border: 1px solid var(--color-outline-variant);
                border-radius: var(--radius-md);
                background: var(--color-surface-variant);
            }
            #arrangementMobile .arrangement-mobile-info-legend-text strong {
                font-size: 13px;
            }
            #arrangementMobile .arrangement-mobile-info-legend-text p {
                color: var(--color-on-surface-variant);
                font-size: 12px;
            }
            #arrangementMobile .arrangement-mobile-info-legend-text {
                min-width: 0;
            }
            #arrangementMobile .arrangement-mobile-info-legend-text p {
                margin: 2px 0 0;
            }
            /* Real canonical swatch classes supply the fills; event-selection
               keeps its blue outline. Pending/Conflict own no border. */
            #arrangementMobile .arrangement-mobile-info-swatch {
                flex: 0 0 auto;
                width: 26px;
                height: 26px;
                border-radius: var(--radius-xs);
                cursor: default;
            }
            #arrangementMobile .arrangement-mobile-info-swatch::after {
                content: none;
            }
            /* ───── SDD arrangement-mobile-day-view T12: saved-summary bar ─────
               The action bar (summary + the relocated original Submit) stays
               visible while the page scrolls, with safe-area padding and enough
               reserved page-end space that content is never hidden behind it.
               T14 replaces this with measured insets/full geometry; colours are
               theme tokens only and no shared CSS is changed. */
            #arrangementMobile {
                padding-bottom: calc(112px + env(safe-area-inset-bottom, 0px));
            }
            #arrangementMobile [data-arrangement-mobile-summary-host] {
                display: flex;
                flex-direction: column;
                gap: 4px;
                min-width: 0;
            }
            #arrangementMobile .arrangement-mobile-actions {
                position: fixed;
                right: 0;
                bottom: 0;
                left: 0;
                z-index: 45;
                margin: 0;
                padding: 8px 8px calc(8px + env(safe-area-inset-bottom, 0px));
                border-top: 1px solid var(--color-outline);
                background: var(--color-surface);
            }
            #arrangementMobile .arrangement-mobile-summary {
                display: flex;
                flex-direction: column;
                gap: 2px;
                width: 100%;
                min-height: 44px;
                padding: 6px 10px;
                border: 1px solid var(--color-outline);
                border-radius: var(--radius-sm);
                background: var(--color-surface-variant);
                color: var(--color-on-surface);
                font: inherit;
                text-align: left;
                overflow-wrap: anywhere;
            }
            #arrangementMobile button.arrangement-mobile-summary {
                cursor: pointer;
            }
            #arrangementMobile .arrangement-mobile-summary--empty {
                color: var(--color-on-surface-variant);
            }
            /* T16: a saved entry whose own date expired stays inspectable in
               the SAME bar; this is the remove-and-choose guidance. */
            #arrangementMobile .arrangement-mobile-summary--retention {
                border-color: var(--color-warning);
                background: var(--color-warning-container);
                color: var(--color-on-warning-container);
            }
            #arrangementMobile .arrangement-mobile-summary--single,
            #arrangementMobile .arrangement-mobile-summary--multiple {
                border-color: var(--color-primary);
                background: var(--color-primary-container);
                color: var(--color-on-primary-container);
            }
            #arrangementMobile .arrangement-mobile-summary-title {
                font-weight: 700;
            }
            #arrangementMobile .arrangement-mobile-summary-meta {
                font-size: 12px;
                color: inherit;
            }
            #arrangementMobile .arrangement-mobile-info-entry {
                margin-top: 12px !important;
                font-weight: 600;
            }
            #arrangementMobile .arrangement-mobile-info-entry:first-child {
                margin-top: 0 !important;
            }
            /* ───── SDD arrangement-mobile-day-view T13: stable keyboard focus ─────
               One visually hidden polite live region and a visible focus ring
               for every mobile control. No geometry change: T14 keeps the 44px
               targets, sticky clusters and measured insets. */
            #arrangementMobile .arrangement-mobile-live {
                position: absolute;
                width: 1px;
                height: 1px;
                margin: -1px;
                padding: 0;
                overflow: hidden;
                clip: rect(0 0 0 0);
                clip-path: inset(50%);
                white-space: nowrap;
                border: 0;
            }
            #arrangementMobile button:focus-visible,
            #arrangementMobile select:focus-visible,
            #arrangementMobile summary:focus-visible,
            #arrangementMobile [tabindex]:focus-visible {
                outline: 2px solid var(--color-primary);
                outline-offset: 2px;
            }

            /* ───── SDD arrangement-mobile-day-view T14: sticky/header/safe-area
               composition ─────
               Page-local only (body.arrangement-mobile-mode, inside the 768px
               query). The header returns to document flow, one control cluster
               sticks, the saved bar is fixed, and the MEASURED sticky/bar heights
               (set on <body> by arrangement-mobile.js) drive the page inset, the
               row scroll-margin and the page-local toast offset. Desktop rules
               above are untouched; colours/radii come from theme tokens only. */
            body.arrangement-mobile-mode .app-container {
                padding-top: 0;
            }
            /* Mobile top zone follows the approved prototype: a primary
               built-in title bar with equal-width back/theme controls and the
               crest + title centred between them. */
            body.arrangement-mobile-mode .top-bar {
                position: static;
                /* Full-bleed against the 12px mobile app-container padding. */
                margin: 0 -12px;
                padding: calc(10px + env(safe-area-inset-top, 0px)) 14px 10px;
                justify-content: center;
                gap: 10px;
                background: var(--color-primary-container);
                color: var(--color-on-primary-container);
                border-bottom: 1px solid var(--color-outline);
            }
            body.arrangement-mobile-mode .back-btn {
                position: static;
                flex: 0 0 auto;
                width: 44px;
                height: 44px;
                min-width: 44px;
                min-height: 44px;
                padding: 0;
                box-sizing: border-box;
                border: 1px solid var(--color-outline-strong);
                border-radius: var(--radius-pill);
                background: var(--color-surface);
                color: var(--color-on-primary-container);
                box-shadow: none;
                transform: none;
            }
            body.arrangement-mobile-mode .back-btn:hover,
            body.arrangement-mobile-mode .back-btn:active {
                box-shadow: none;
                transform: none;
            }
            /* Mobile header: arrow only; the accessible name stays "Back". */
            body.arrangement-mobile-mode .back-btn .today-btn-label,
            body.arrangement-mobile-mode .back-btn > span {
                display: none;
            }
            body.arrangement-mobile-mode .top-center {
                flex: 1 1 auto;
                min-width: 0;
                justify-content: center;
                gap: 10px;
                color: var(--color-on-primary-container);
            }
            body.arrangement-mobile-mode .top-bar > .theme-toggle {
                position: static;
                display: flex;
                flex: 0 0 auto;
                width: 44px;
                height: 44px;
                min-width: 44px;
                min-height: 44px;
                padding: 0;
                box-sizing: border-box;
                border: 1px solid var(--color-outline-strong);
                border-radius: var(--radius-pill);
                background: var(--color-surface);
                color: var(--color-on-primary-container);
                box-shadow: none;
            }
            body.arrangement-mobile-mode .top-bar > .theme-toggle:hover,
            body.arrangement-mobile-mode .top-bar > .theme-toggle:active {
                transform: none;
                opacity: 0.9;
            }
            body.arrangement-mobile-mode .top-bar > .back-btn:focus-visible,
            body.arrangement-mobile-mode .top-bar > .theme-toggle:focus-visible {
                outline: 2px solid var(--color-on-primary-container);
                outline-offset: 2px;
            }
            body.arrangement-mobile-mode .top-title {
                color: var(--color-on-primary-container);
                font-size: clamp(16px, 4.6vw, 18px);
                line-height: 1.12;
                text-align: center;
                overflow-wrap: anywhere;
            }
            body.arrangement-mobile-mode .top-logo {
                height: 28px;
            }

            /* Compact expandable original context: one 44px summary line and
               padded facts; the conflict strip wraps without hiding facts. */
            body.arrangement-mobile-mode #arrangementMobile .arrangement-mobile-context {
                border: 1px solid var(--color-outline);
                border-radius: var(--radius-sm);
                background: var(--color-surface);
                color: var(--color-on-surface);
            }
            body.arrangement-mobile-mode #arrangementMobile .arrangement-mobile-context > summary {
                display: flex;
                align-items: center;
                gap: 8px;
                min-height: 44px;
                padding: 10px 12px;
                font-weight: 600;
                line-height: 1.3;
                overflow-wrap: anywhere;
                cursor: pointer;
            }
            body.arrangement-mobile-mode #arrangementMobile .arrangement-mobile-context[open] > summary {
                border-bottom: 1px solid var(--color-outline);
            }
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-context-facts],
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-subject-host],
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-slot-host] {
                padding: 8px 12px;
            }
            /* Sticky venue/week/day cluster only. */
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-controls] {
                position: sticky;
                top: 0;
                z-index: 30;
                background: var(--color-bg);
                padding: 8px 12px;
                border-bottom: 1px solid var(--color-outline);
            }
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-days] {
                flex-wrap: nowrap;
                gap: 6px;
                max-width: 100%;
                min-width: 0;
                padding-bottom: 2px;
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
            }
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-days] button {
                flex: 1 0 44px;
                display: inline-flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                min-width: 44px;
                min-height: 52px;
                white-space: normal;
            }
            body.arrangement-mobile-mode #arrangementMobile #subjectSelector,
            body.arrangement-mobile-mode #arrangementMobile .slot-dd-trigger,
            body.arrangement-mobile-mode #arrangementMobile .venue-dd-trigger,
            body.arrangement-mobile-mode #arrangementMobile #weekSelector,
            body.arrangement-mobile-mode #arrangementMobile .week-arrow,
            body.arrangement-mobile-mode #arrangementMobile #earliestBtn {
                min-height: 44px;
            }
            body.arrangement-mobile-mode #arrangementMobile .week-arrow {
                min-width: 44px;
            }
            body.arrangement-mobile-mode #arrangementMobile .slot-dd-item,
            body.arrangement-mobile-mode #arrangementMobile .venue-col-item {
                min-height: 44px;
            }

            /* Tools row above the timeline: wraps instead of scrolling. */
            body.arrangement-mobile-mode #arrangementMobile .arrangement-mobile-tools {
                flex-direction: row;
                flex-wrap: wrap;
                align-items: center;
                gap: 8px;
                padding: 8px 12px;
            }
            body.arrangement-mobile-mode #arrangementMobile .arrangement-mobile-tools > * {
                flex: 0 1 auto;
                min-width: 0;
            }
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-tip-host] {
                display: inline-flex;
                flex: 0 0 auto;
            }
            /* Tip and the icon-only Earliest action share the row after the
               legend; no full-width row is reserved for the icon. */
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-earliest-host] {
                display: inline-flex;
                flex: 0 0 auto;
                order: 3;
            }
            body.arrangement-mobile-mode #arrangementMobile #earliestBtn .today-btn-label {
                display: none;
            }
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-legend],
            body.arrangement-mobile-mode #arrangementMobile #earliestBtn,
            body.arrangement-mobile-mode #arrangementMobile #summaryTip {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 44px;
                min-height: 44px;
            }
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-legend] {
                gap: 6px;
                padding: 8px 12px;
                border: 1px solid var(--color-outline);
                border-radius: var(--radius-md);
                background: var(--color-surface);
                box-shadow: var(--shadow-sm);
                color: var(--color-on-surface);
                font: inherit;
                font-weight: 600;
                cursor: pointer;
            }
            body.arrangement-mobile-mode #arrangementMobile .arrangement-mobile-legend-icon {
                flex: 0 0 auto;
                width: 16px;
                height: 16px;
                color: var(--color-primary);
            }

            /* 48px nominal timeline rows; the saved span keeps its grid span. */
            body.arrangement-mobile-mode #arrangementMobile {
                --arrangement-mobile-row-height: 48px;
                /* The fixed bar's clearance now lives below the footer, so the
                   last slot is followed immediately by the footer. */
                padding: 0 4px;
            }
            body.arrangement-mobile-mode .ui-footer {
                padding-bottom: calc(var(--arrangement-mobile-bar-height, 132px) + env(safe-area-inset-bottom, 0px) + 8px);
            }
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-row,
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-timeline] .arrangement-mobile-span {
                min-height: 48px;
                scroll-margin-top: calc(var(--arrangement-mobile-sticky-height, 0px) + 12px);
                scroll-margin-bottom: calc(var(--arrangement-mobile-bar-height, 112px) + 12px);
            }

            /* Fixed measured action bar (summary + the original Submit). It may
               grow/wrap; its real height feeds --arrangement-mobile-bar-height. */
            body.arrangement-mobile-mode #arrangementMobile .arrangement-mobile-actions {
                position: fixed;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 40;
                margin: 0;
                padding: 10px 12px calc(10px + env(safe-area-inset-bottom, 0px));
                border-top: 1px solid var(--color-outline);
                background: var(--color-surface);
                box-shadow: var(--shadow-sm);
            }
            body.arrangement-mobile-mode #arrangementMobile [data-arrangement-mobile-summary-host] {
                gap: 8px;
            }
            body.arrangement-mobile-mode #arrangementMobile .arrangement-mobile-summary {
                min-height: 44px;
                padding: 10px 12px;
            }
            body.arrangement-mobile-mode #arrangementMobile #submitTip .btn {
                min-height: 44px;
            }

            /* Toast sits above the measured bar (page-local position only). */
            body.arrangement-mobile-mode .toast-bar {
                bottom: calc(var(--arrangement-mobile-bar-height, 112px) + env(safe-area-inset-bottom, 0px) + 12px);
            }

            /* Bottom-sheet geometry: full width, 85dvh, internal scroll, sticky
               heading/Close and safe-area padding; T11 text/swatch styles stay. */
            body.arrangement-mobile-mode #arrangementMobile dialog.arrangement-mobile-info {
                position: fixed;
                inset: auto 0 0;
                width: 100%;
                max-width: 100%;
                max-height: 85dvh;
                margin: 0;
                border-radius: 18px 18px 0 0;
                padding-bottom: calc(16px + env(safe-area-inset-bottom, 0px));
                overflow-y: auto;
            }
            body.arrangement-mobile-mode #arrangementMobile dialog.arrangement-mobile-info .modal-header {
                position: sticky;
                top: 0;
                z-index: 1;
                background: var(--color-surface);
            }
            body.arrangement-mobile-mode #arrangementMobile dialog.arrangement-mobile-info [data-arrangement-mobile-info-close],
            body.arrangement-mobile-mode #arrangementMobile dialog.arrangement-mobile-info .btn {
                min-height: 44px;
            }
            body.arrangement-mobile-mode #arrangementMobile dialog.arrangement-mobile-info [data-arrangement-mobile-info-close] {
                min-width: 44px;
            }

            /* One mobile sheet language for every pop-up: the Saved Selection
               info sheet, the existing confirmation modal (Unsaved Changes,
               change guards, submit/conflict feedback) and the shortcuts card
               share the same bottom-sheet frame, header and action rail. */
            body.arrangement-mobile-mode #confirmModal {
                align-items: flex-end;
                justify-content: center;
                padding: 0;
                background: var(--color-scrim);
            }
            body.arrangement-mobile-mode #confirmModal .modal {
                width: 100%;
                max-width: 100%;
                max-height: 85dvh;
                margin: 0;
                padding: 0 0 calc(16px + env(safe-area-inset-bottom, 0px));
                border-radius: 18px 18px 0 0;
                box-shadow: none;
                overflow-y: auto;
            }
            body.arrangement-mobile-mode #confirmModal .modal-header {
                position: sticky;
                top: 0;
                z-index: 1;
                padding: 14px 16px 10px;
                background: var(--color-surface);
                border-bottom: 1px solid var(--color-outline);
            }
            body.arrangement-mobile-mode #confirmModal .modal-title {
                text-align: left;
                font-size: 1.1rem;
            }
            body.arrangement-mobile-mode #confirmModal .modal-close {
                width: auto;
                height: auto;
                min-width: 44px;
                min-height: 44px;
                padding: 0 8px;
                border: 0;
                border-radius: var(--radius-md);
                background: transparent;
                color: var(--color-on-surface);
                font-size: 13px;
            }
            body.arrangement-mobile-mode #confirmModal .modal-close-x {
                display: none;
            }
            body.arrangement-mobile-mode #confirmModal .modal-close-text {
                display: inline;
            }
            body.arrangement-mobile-mode #confirmModal .modal-body {
                padding: 16px;
            }
            body.arrangement-mobile-mode #confirmModal .modal-footer {
                flex-direction: column;
                align-items: stretch;
                gap: 8px;
                padding: 12px 16px;
                border-top: 1px solid var(--color-outline);
            }
            body.arrangement-mobile-mode #confirmModal .modal-footer .btn {
                width: 100%;
                min-height: 44px;
                height: auto;
            }
            body.arrangement-mobile-mode #confirmModal .modal-footer .btn-outline {
                border: 1px solid var(--color-outline);
                background: transparent;
                color: var(--color-on-surface);
            }

            body.arrangement-mobile-mode .help-overlay {
                align-items: flex-end;
                padding: 0;
                background: var(--color-scrim);
            }
            body.arrangement-mobile-mode .help-card {
                width: 100%;
                max-width: 100%;
                max-height: 85dvh;
                margin: 0;
                padding: 16px 16px calc(16px + env(safe-area-inset-bottom, 0px));
                border: 1px solid var(--color-outline);
                border-radius: 18px 18px 0 0;
                box-shadow: none;
                overflow-y: auto;
            }
            body.arrangement-mobile-mode .help-card .help-close .btn {
                width: 100%;
                min-height: 44px;
                border: 1px solid var(--color-outline);
                background: transparent;
                color: var(--color-on-surface);
            }

            /* Motion is never required to read state; reduced motion drops the
               nonessential page-local transitions/scroll behaviour. */
            @media (prefers-reduced-motion: reduce) {
                body.arrangement-mobile-mode #arrangementMobile *,
                body.arrangement-mobile-mode #arrangementMobile *::before,
                body.arrangement-mobile-mode #arrangementMobile *::after {
                    transition-duration: 0.01ms !important;
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    scroll-behavior: auto !important;
                }
                body.arrangement-mobile-mode .toast-bar {
                    animation-duration: 0.01ms !important;
                }
                body.arrangement-mobile-mode #confirmModal .modal,
                body.arrangement-mobile-mode .help-card {
                    animation-duration: 0.01ms !important;
                }
            }
        }

        /* ── Replacement Arrangement top zone (2026-11-12) ─────────────
           Two cards: Conflict Schedule and Replacement Schedule. The old
           conflict strip and single toolbar row are gone. */
        .toolbar-restructure.ra-top-zone {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            gap: 12px;
            flex-wrap: nowrap;
        }
        .ra-block {
            background: var(--color-surface);
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: 12px 16px;
        }
        .ra-block-head {
            display: flex;
            align-items: center;
            gap: 8px;
            min-height: 16px;
            margin-bottom: 9px;
        }
        .ra-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            line-height: 1;
        }
        .ra-eyebrow svg { flex-shrink: 0; }
        .ra-eyebrow--error { color: var(--color-on-error-container); }
        .ra-eyebrow--primary { color: var(--color-on-primary-container); }
        .light .ra-eyebrow--error { color: var(--color-error); }
        .light .ra-eyebrow--primary { color: var(--color-primary); }
        .ra-card-toggle {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 28px;
            height: 28px;
            padding: 0;
            border: 0;
            border-radius: 6px;
            background: transparent;
            color: var(--color-on-surface-variant);
            cursor: pointer;
        }
        .ra-card-toggle:hover { background: var(--color-surface-variant); }
        .ra-card-toggle:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }
        .ra-card-toggle:disabled { opacity: 0.35; cursor: not-allowed; }
        .ra-card-chevron { transition: transform 0.15s ease; }
        .ra-card-toggle[aria-expanded="true"] .ra-card-chevron { transform: rotate(180deg); }
        .ra-collapsed-summary { display: none; margin: 2px 0 0; }
        #subjectInfo.is-collapsed .ra-collapsed-summary { display: block; }
        .ra-collapsed-line1 {
            font-size: 13px;
            font-weight: 600;
            line-height: 1.35;
            color: var(--color-on-surface);
        }
        .ra-collapsed-line2 {
            margin-top: 2px;
            font-size: 12px;
            font-weight: 500;
            line-height: 1.35;
            color: var(--color-on-surface-variant);
        }
        .ra-course-code { font-weight: 700; }
        .ra-original-controls {
            display: grid;
            grid-template-columns: minmax(0, 0.45fr) minmax(0, 0.55fr);
            gap: 16px;
        }
        .ra-replacement-controls {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }
        .ra-replacement-venue { flex: 0 1 auto; }
        .ra-replacement-week { flex: 0 0 auto; margin-left: auto; }
        .ra-field { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .ra-field-label {
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--color-on-surface-variant);
        }
        .ra-original-controls .selector-dropdown {
            width: 100%;
            min-width: 0;
            max-width: none;
            height: 44px;
            background: var(--color-surface-variant);
        }
        .ra-original-controls .slot-dd {
            width: 100%;
            min-width: 0;
            max-width: none;
            flex-basis: auto;
        }
        .ra-original-controls .slot-dd-trigger {
            height: 44px;
            padding: 4px 12px;
            background: var(--color-surface-variant);
        }
        .ra-original-controls .slot-dd-panel {
            width: 100%;
            min-width: 0;
        }
        #slotTriggerText {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-width: 0;
            gap: 1px;
        }
        #slotTriggerText > span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #slotTriggerText > span:last-child {
            font-size: 12px;
            font-weight: 500;
            color: var(--color-on-surface-variant);
        }
        #slotTriggerText .ra-venue-pill { overflow: visible; }
        .ra-slot-type {
            color: var(--color-on-primary-container);
            font-weight: 700;
        }
        .light .ra-slot-type { color: var(--color-primary); }
        .ra-venue-pill {
            display: inline-flex;
            align-items: center;
            padding: 2px 6px;
            border: 1px solid var(--color-outline);
            border-radius: var(--radius-pill);
            background: var(--color-surface-variant);
            color: var(--color-on-surface);
            font-size: 11px;
            font-weight: 700;
            line-height: 1.2;
            vertical-align: middle;
        }
        .slot-dd-trigger .ra-venue-pill { background: var(--color-surface); }
        .ra-details { margin-top: 12px; border-top: 1px solid var(--color-outline); }
        .ra-details-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            width: 100%;
            padding: 10px 0 0;
            border: 0;
            background: none;
            color: inherit;
            font: inherit;
            text-align: left;
            cursor: pointer;
        }
        .ra-details-toggle:focus-visible {
            outline: 2px solid var(--color-primary);
            outline-offset: 2px;
        }
        .ra-details-summary {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--color-on-surface-variant);
        }
        .ra-details-chevron {
            flex-shrink: 0;
            opacity: 0.7;
            transition: transform 0.15s;
        }
        .ra-details.open .ra-details-chevron { transform: rotate(180deg); }
        .ra-details-body {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1.5fr) minmax(0, 0.8fr);
            gap: 10px 20px;
            padding: 10px 0 2px;
        }
        .ra-detail-label {
            display: block;
            margin-bottom: 3px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--color-on-surface-variant);
        }
        .ra-detail-value {
            display: block;
            font-size: 12.5px;
            line-height: 1.35;
            color: var(--color-on-surface);
        }
        #detailCohorts { display: flex; flex-wrap: wrap; gap: 6px; }
        .ra-replacement-venue .venue-dd-trigger {
            background: var(--color-surface-variant);
        }
        .ra-replacement-venue .toolbar-center { gap: 8px; }
        .ra-replacement-venue .venue-dd-label {
            display: inline-flex;
            align-items: center;
            flex: 1 1 auto;
            min-width: 0;
            gap: 8.8px;
        }
        .ra-replacement-venue .venue-dd-trigger .dd-arrow { margin-left: auto; }
        .ra-venue-code {
            font-size: 13px;
            font-weight: 600;
            color: var(--color-on-surface);
        }
        .ra-venue-seats {
            font-size: 12px;
            font-weight: 500;
            color: var(--color-on-surface-variant);
        }
        .ra-replacement-week .week-select {
            width: auto;
            background: var(--color-surface-variant);
        }
        .ra-replacement-week .toolbar-filters {
            display: flex;
            align-items: center;
            gap: 8px;
            flex: none;
            min-width: 0;
        }
        .ra-replacement-week .week-nav { width: auto; }

        .ra-desc-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 9px;
            border: 0;
            font: inherit;
            cursor: help;
        }
        .venue-side-chips .ra-desc-chip { padding: 5px 9px; }
        .venue-side-chips .ra-desc-chip svg { width: 14px; height: 14px; }

        @media (max-width: 900px) {
            .ra-original-controls { grid-template-columns: 1fr; }
            .ra-replacement-controls {
                flex-direction: column;
                align-items: stretch;
            }
            .ra-replacement-week {
                width: 100%;
                margin-left: 0;
            }
            .ra-replacement-week .toolbar-filters { width: 100%; }
            .ra-replacement-week .week-nav { flex: 1; }
            .ra-replacement-week .week-select { flex: 1; min-width: 0; }
        }

        body.arrangement-mobile-mode #subjectInfo {
            border: 0;
            border-radius: 0;
            box-shadow: none;
            padding: 0;
            background: transparent;
        }
        body.arrangement-mobile-mode #subjectInfo .ra-block-head {
            min-height: 44px;
            margin-bottom: 2px;
        }
        body.arrangement-mobile-mode #subjectInfo .ra-card-toggle {
            width: 44px;
            height: 44px;
        }
        body.arrangement-mobile-mode .ra-original-controls,
        body.arrangement-mobile-mode .ra-replacement-controls { display: block; }
        body.arrangement-mobile-mode .ra-field-label { display: none; }
        body.arrangement-mobile-mode #subjectInfo .ra-course-field { display: none; }
        body.arrangement-mobile-mode #buildingSelectorDropdown .venue-dd-trigger {
            width: 100%;
            background: var(--color-surface-variant);
        }
        body.arrangement-mobile-mode #buildingSelectorDropdown .venue-dd-label {
            display: inline-flex;
            align-items: center;
            flex: 1 1 auto;
            min-width: 0;
            gap: 8.8px;
        }
        body.arrangement-mobile-mode #buildingSelectorDropdown .dd-arrow { margin-left: auto; }
        body.arrangement-mobile-mode #weekSelector { background: var(--color-surface-variant); }
        body.arrangement-mobile-mode .ra-original-controls .selector-dropdown,
        body.arrangement-mobile-mode #subjectSelector {
            height: 52.8px;
            width: 100%;
            background: var(--color-surface-variant);
        }
        body.arrangement-mobile-mode #slotPicker { width: 100%; }
        body.arrangement-mobile-mode #slotPicker .slot-dd {
            width: 100%;
            max-width: none;
            min-width: 0;
            flex-basis: auto;
        }
        body.arrangement-mobile-mode #slotPicker .slot-dd-panel {
            width: 100%;
            min-width: 0;
        }
        body.arrangement-mobile-mode .ra-original-controls .slot-dd-trigger,
        body.arrangement-mobile-mode #slotPicker .slot-dd-trigger {
            height: 44px;
            width: 100%;
            background: var(--color-surface-variant);
        }
        body.arrangement-mobile-mode .ra-details-toggle { min-height: 44px; }
        body.arrangement-mobile-mode .ra-details-body {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            gap: 14px 24px;
        }
        body.arrangement-mobile-mode .ra-detail {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            flex: 0 0 auto;
            max-width: 100%;
            text-align: left;
        }
        body.arrangement-mobile-mode .ra-detail-label {
            margin-bottom: 6px;
            font-size: 11px;
        }
        body.arrangement-mobile-mode .ra-detail-value {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-start;
            gap: 6px;
            text-align: left;
        }
        body.arrangement-mobile-mode .ra-detail-value .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            box-sizing: border-box;
            min-width: 48px;
            min-height: 34px;
            height: 34px;
            padding: 0 14px;
            border-radius: var(--radius-pill);
            font-size: 13px;
            font-weight: 600;
            line-height: 1;
            white-space: nowrap;
        }

@endsection

{{-- Real-data bridge (SDD wire-backend-into-refactored-ui): the mock
     template runs UNCHANGED below except for the documented one-line
     patches marked 'BRIDGE PATCH'. PAGE_DATA carries the mock's exact
     data shapes, derived from the real DB. --}}
<div class="lw-page">
    @include('partials.page-data-script', ['payload' => $payload])
    <div class="top-bar">
        <button class="back-btn" onclick="goBack()" aria-label="Back">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="19" y1="12" x2="5" y2="12"/>
                <polyline points="12 19 5 12 12 5"/>
            </svg>
            <span>Back</span>
        </button>
        <div class="top-center">
            <img src="/images/logo_icon.png" alt="Logo" class="top-logo" onclick="navigateHome()">
            <span class="top-title">Replacement Arrangement</span>
        </div>
        <button class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle theme">
            <svg id="theme-icon" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
            </svg>
        </button>
    </div>

    @include('partials.ui-page-header', [])

        @include('partials.ui-guide-block', [
            'guideTitle' => 'How to use this page',
            'guideItems' => [
                '<strong>Select course</strong> — choose the course to arrange a replacement for',
                '<strong>Slot status</strong> — Available (green), Unavailable (booked / Sunday / public holiday)',
                '<strong>Select slot</strong> — click an available slot to propose it as the replacement',
                '<strong>Submit</strong> — confirm your selection to send the request for approval',
                '<strong>Back</strong> — use the back button to return to the conflict list',
            ]
        ])

        <div class="booking-intent-wrap" id="bookingIntent"></div>

        <!-- 2026-11-10 (user, "UI Update — Too-Soon Tooltip"): the standalone
             lead-time banner is GONE — its message now rides the too-soon cell's own
             hover tooltip (see leadTimeTip() below). -->

        <div class="toolbar toolbar-restructure ra-top-zone">
            <section class="ra-block" id="subjectInfo" aria-labelledby="conflictHeading">
                <div class="ra-block-head">
                    <span class="ra-eyebrow ra-eyebrow--error" id="conflictHeading">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        Conflict Schedule
                    </span>
                    <button class="ra-card-toggle" type="button" id="conflictCollapse"
                            aria-expanded="true" aria-controls="conflictCardBody"
                            aria-label="Collapse Conflict Schedule">
                        <svg class="ra-card-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </button>
                </div>

                <div class="ra-collapsed-summary" id="conflictCollapsedSummary">
                    <div class="ra-collapsed-line1" id="conflictSummaryLine1"></div>
                    <div class="ra-collapsed-line2" id="conflictSummaryLine2"></div>
                </div>

                <div id="conflictCardBody">
                    <div class="ra-original-controls">
                        <div class="ra-field ra-course-field">
                            <label class="ra-field-label" for="subjectSelector">Course</label>
                            <select class="selector-dropdown" id="subjectSelector" onchange="onSubjectChange()">
                                <option value="">Select a course</option>
                            </select>
                        </div>

                        {{-- Slot Picker: custom dropdown (button + panel) --}}
                        <div class="ra-field" id="slotPicker" style="display:none;">
                            <label class="ra-field-label" for="slotTrigger">Slot</label>
                            <div class="slot-dd">
                                <button class="slot-dd-trigger" type="button" id="slotTrigger" onclick="toggleSlotPanel()">
                                    <span id="slotTriggerText">
                                        <span id="slotTriggerLine1">Select a slot to replace</span>
                                        <span id="slotTriggerLine2"></span>
                                    </span>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="6" viewBox="0 0 10 6" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 1l4 4 4-4"/></svg>
                                </button>
                                <div class="slot-dd-panel" id="slotPanel"></div>
                                <div class="slot-dd-tooltip" id="slotTooltip"></div>
                            </div>
                        </div>
                    </div>

                    <div class="ra-details" id="details" hidden>
                        <button class="ra-details-toggle" type="button" id="detailsToggle"
                                aria-expanded="false" aria-controls="detailsBody">
                            <span class="ra-details-summary" id="detailsSummary"></span>
                            <svg class="ra-details-chevron" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="m6 9 6 6 6-6"/>
                            </svg>
                        </button>
                        <div class="ra-details-body" id="detailsBody" hidden>
                            <div class="ra-detail">
                                <span class="ra-detail-label">Reason</span>
                                <span class="ra-detail-value" id="detailReason"></span>
                            </div>
                            <div class="ra-detail">
                                <span class="ra-detail-label">Cohorts</span>
                                <span class="ra-detail-value" id="detailCohorts"></span>
                            </div>
                            <div class="ra-detail">
                                <span class="ra-detail-label">Students</span>
                                <span class="ra-detail-value" id="detailStudents"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="ra-block" aria-labelledby="replacementHeading">
                <div class="ra-block-head">
                    <span class="ra-eyebrow ra-eyebrow--primary" id="replacementHeading">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <polyline points="17 1 21 5 17 9"/>
                            <path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                            <polyline points="7 23 3 19 7 15"/>
                            <path d="M21 13v2a4 4 0 0 1-4 4H3"/>
                        </svg>
                        Replacement Schedule
                    </span>
                </div>

                <div class="ra-replacement-controls">
                    <div class="ra-field ra-replacement-venue">
                        <span class="ra-field-label">Replacement venue</span>
                        <div class="toolbar-center">
                            @include('partials.ui-venue-dropdown', ['selectId' => 'buildingSelector'])
                            {{-- venue-side-chips: the description badge stays; the
                                 separate capacity chip is intentionally gone. --}}
                            <div class="venue-side-chips" id="venueChipGroup" style="display:none;">
                                <span class="badge badge-past ra-desc-chip" id="arrVenueDescription" aria-label="Room description"></span>
                            </div>
                        </div>
                    </div>

                    <div class="ra-field ra-replacement-week">
                        <span class="ra-field-label">Replacement week</span>
                        <div class="toolbar-filters">
                            @include('partials.ui-week-nav', ['prevOnclick' => 'weekNav.prevWeek()', 'nextOnclick' => 'weekNav.nextWeek()', 'selectId' => 'weekSelector', 'selectOnclick' => 'weekNav.selectWeek(parseInt(this.value, 10))', 'showTodayBtn' => true, 'showPrint' => false, 'selectTip' => 'Select the WEEK that you want to arrange the replacement in', 'todayLabel' => 'Earliest bookable', 'todayTip' => 'Jump to the earliest bookable slot', 'todayIcon' => 'earliest'])
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- Progress bar — commented out for now
        <div class="progress-wrapper" id="progressWrapper">
            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
            </div>
            <span class="progress-text" id="progressText">Selected 0 of 4 slots</span>
        </div>
        --}}

        <!-- 2026-11-10 (user): the "Showing venues that fit N students" note is
             REMOVED — the venue filter still runs, it just no longer narrates itself
             in a line above the grid. -->

        @include('partials.ui-grid-table')

        {{-- SDD arrangement-slot-ownership-legend (user law, 2026-11-06; rewritten
             2026-11-11 on the user's ruling): COLOUR alone now says "cannot book"
             — the thick 3px ownership ring is GONE from both the conflict and the
             pending family, so the legend draws exactly what the grid draws and
             the ownership hint line ("thick = yours") was DELETED, because it
             described a border the page no longer paints. Ownership is words on
             hover only ("Your Conflict" / "Your Pending" vs the others' arms).
             Capitals do the emphasis: a data-tip is plain text (the engine sets
             textContent + pre-line), so line breaks are \n, never <br>. --}}
        @include('partials.ui-legend-bar', [
            'items' => [
                ['class' => 'cell-available', 'label' => 'Available', 'tip' => 'FREE SLOT — CLICK to select as the replacement (slots inside the 3-working-day window are read-only)'],
                ['class' => 'event-selection', 'label' => 'Your Current Selection', 'tip' => 'The slot(s) you have SELECTED for the replacement'],
                ['class' => 'cell-pending', 'label' => 'Pending', 'tip' => "A replacement request for this slot is WAITING for approval\nHOVER the slot to see whether it is YOURS (you can follow it up) or another staff member's (you CANNOT BOOK it)"],
                /* 2026-11-11 (user): one Conflict row, drawn plain — your own
                   conflict looks the same as a venue/cohort conflict now; the
                   hover words name which one it is. */
                ['class' => 'cell-occupied', 'label' => 'Conflict', 'tip' => "CANNOT BOOK — the slot is taken by a scheduled class\nHOVER the slot to see WHY: your own class, a VENUE CONFLICT (another class in this room), or a COHORT CONFLICT (the related cohorts have another class at that time)"],
            ]
        ])

        {{-- Summary Bar — commented out for now
        @include('partials.ui-summary-bar', [
            'cards' => [
                ['class' => 'card-total', 'valueId' => 'sumTotal', 'label' => 'Total Slots',
                    'description' => 'All time slots shown for <span class="info-keyword">this venue</span> in the selected week.'],
                ['class' => 'card-available', 'valueId' => 'sumAvailable', 'label' => 'Available',
                    'description' => '<span class="info-keyword">Free slots</span> you can select as the replacement.'],
                ['class' => 'card-pending', 'valueId' => 'sumPending', 'label' => 'Pending',
                    'description' => 'Your replacement requests <span class="warn-keyword">awaiting approval</span>.'],
                ['class' => 'card-conflict', 'valueId' => 'sumUnavailable', 'label' => 'Unavailable',
                    'description' => '<span class="warn-keyword">Cannot select</span> — booked by others, Sunday, or public holiday.'],
            ]
        ])
        --}}

        <div class="sel-summary" id="selSummary">
            <div class="sel-summary-header">
                <span class="sel-summary-title">Selection Summary</span>
                {{--<span class="sel-summary-count"><strong id="summaryCount">0</strong> / 4 Selected</span>--}}
            </div>
            <div class="sel-summary-empty" id="summaryEmpty">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                    <line x1="12" y1="14" x2="12" y2="18"/>
                    <line x1="10" y1="16" x2="14" y2="16"/>
                </svg>
                <p>No time slots selected.</p>
            </div>
            <div class="sel-summary-grid" id="summaryGrid"></div>
            <div class="sel-summary-info" id="summaryInfo">
                <div class="info-rows">
                    <div class="info-item">
                        <span class="info-label">Total Selected</span>
                        <span class="info-value" id="infoTotal">0 of 4 slots</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Total Duration</span>
                        <span class="info-value" id="infoDuration">0 hours</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Venue</span>
                        <span class="info-value" id="infoBuilding">B103</span>
                    </div>
                </div>
            </div>
            {{-- 2026-11-11 (user, "UI Update — Compact Tip Tooltip"): the long hint
                 is a compact [💡 Tip] trigger immediately LEFT of Submit Request.
                 The trigger and the button are siblings; only the trigger's
                 `data-tip` text is rewritten, never its icon/label contents. --}}
            <div class="sel-summary-action">
                <button type="button" class="sel-summary-tip-trigger" id="summaryTip"
                        data-tip="Click an available (green) time slot to begin."
                        aria-label="Tip: Click an available (green) time slot to begin.">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 18h6"/>
                        <path d="M10 22h4"/>
                        <path d="M12 2a7 7 0 0 0-4 12.7V17h8v-2.3A7 7 0 0 0 12 2Z"/>
                    </svg>
                    <span>Tip</span>
                </button>
                <span class="btn-tip" id="submitTip" data-tip="Select a time slot first to enable submission">
                    <button class="btn btn-primary" id="submitRequestBtn" onclick="proceed()">
                        Submit Request
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/>
                            <polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </button>
                </span>
            </div>
        </div>

        {{-- 2026-11-10 (user, "UI Update — Action Area"): Clear ALL is COMMENTED OUT,
             not deleted — it returns with multi-block selection. While it is out of the
             DOM, `#clearAllBtn`/`#clearTip` are simply absent, and every script that
             touched them is null-guarded (updateCounter).
        <span class="btn-tip" id="clearTip" data-tip="Nothing to clear yet — select a time slot first">
            <button class="btn btn-danger" id="clearAllBtn" disabled onclick="clearAll()">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="3 6 5 6 21 6"/>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Clear ALL
            </button>
        </span>
        --}}
        {{-- `.footer-area` removed 2026-11-10 (user): the wrapper, its left/right
             groups and their mobile overrides are gone; the action row above owns the
             Submit button now. --}}

        @include('partials.ui-arrangement-mobile')

        <div class="modal-overlay" id="confirmModal" style="display:none">
        <div class="modal">
            <div class="modal-header">
                <span class="modal-title" id="modalTitle">Confirm</span>
                <button class="modal-close" onclick="hideConfirmModal(event)" data-tip="Close">
                    <span class="modal-close-x" aria-hidden="true">&times;</span>
                    <span class="modal-close-text">Close</span>
                </button>
            </div>
            <div class="modal-body" id="modalBody"></div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="hideConfirmModal(event)">Close</button>
                <button class="btn btn-primary" id="modalConfirmBtn">Confirm</button>
                <button class="btn btn-primary" id="modalActionBtn" style="display:none"></button>
            </div>
        </div>
    </div>

<div class="help-overlay" id="helpOverlay" onclick="if(event.target===this)hideHelp()">
    <div class="help-card">
        <h3>Keyboard Shortcuts</h3>
        <div class="shortcut-row"><span>Navigate grid</span><span class="shortcut-key">↑ ↓ ← →</span></div>
        <div class="shortcut-row"><span>Select / deselect slot</span><span class="shortcut-key">Enter / Space</span></div>
        <div class="shortcut-row"><span>Undo last selection</span><span class="shortcut-key">Ctrl+Z</span></div>
        <div class="shortcut-row"><span>Previous week</span><span class="shortcut-key">[</span></div>
        <div class="shortcut-row"><span>Next week</span><span class="shortcut-key">]</span></div>
        <div class="shortcut-row"><span>Switch venue (1-3)</span><span class="shortcut-key">Ctrl+1/2/3</span></div>
        <div class="shortcut-row"><span>Close modal / clear focus</span><span class="shortcut-key">Escape</span></div>
        <div class="shortcut-row"><span>Show this help</span><span class="shortcut-key">?</span></div>
        <div class="help-close">
            <button class="btn btn-outline" onclick="hideHelp()">Close</button>
        </div>
    </div>

    </div></div>

<script src="/js/arrangement-mobile.js?v={{ filemtime(public_path('js/arrangement-mobile.js')) }}"></script>

@section('page-scripts')
        /* WRAP: the frozen script's top-level boot (generateWeekData,
           WeekNavigator, MockData reads) needs mock-data.js / ui-common.js,
           which parse after @yield('content') — so the whole script runs on
           DOMContentLoaded (the my-timetable conversion pattern); the
           onclick / inline-handler globals are exported to window at the
           bottom. Everything else is the frozen UI design, byte-for-byte
           except the BRIDGE PATCH marks. */
        document.addEventListener('DOMContentLoaded', function () {
        // Closure-owned adapter; boot-time refreshes are safe before creation.
        let arrangementMobile = null;
        /* T05: refresh is COALESCED by the adapter — many calls settle into one
           read-only snapshot per turn, and the latest reason wins. Call it only
           at settled points (after a whole build/counter/summary/Tip update),
           never between half-committed steps and never from a confirmation
           callback: a pending confirmation leaves the committed context alone. */
        function refreshArrangementMobile(reason) {
            arrangementMobile?.refresh(reason);
        }

        /* ── Real-data bridge — swap the mock's data source for the backend. ── */
        MockData.semester = window.PAGE_DATA.semester;
        MockData.holidays = window.PAGE_DATA.holidays;
        MockData.currentUser = window.PAGE_DATA.currentUser;
        MockData.venues = window.PAGE_DATA.venues;
        /* arrange-intent-session-choice D0/D1: the single-source rule data */
        window.__ruleMap = window.PAGE_DATA.ruleMap;
        MockData.courses = window.PAGE_DATA.courses;
        MockData.cohortTimetable = { events: window.PAGE_DATA.conflictEvents };
        /* week-keyed: real slot states are week-specific — the mock's
           single-semester map becomes [weekIdx][venueCode] (cellRender
           lookup patched below) */
        /* SDD occupied-cell-explanations (the payload gate's dictionary
           fallback): the grid rows' 4th key carries INDICES into the
           deduped block-text library — one copy of every holder block,
           no per-row duplication. */
        MockData.holderTexts = window.PAGE_DATA.holderTexts || [];
        MockData.venueSlots = window.PAGE_DATA.venueSlotsByWeek;
        /* D4 rule 1 — the engine's busy vectors + ineligible venues, so the
           grid never offers a cell the FR 4.3–4.7 pre-check would refuse */
        MockData.busyByWeek = window.PAGE_DATA.busyByWeek;
        MockData.blockedVenues = window.PAGE_DATA.blockedVenues;
        MockData.mockNow = null; /* real clock — the demo anchor is mock-era only */
        // Max selectable slots — the BUDGET flexes to the CURRENT occurrence's
        // own span (SDD selection-budget-original-duration, the user law
        // 2026-10-24: ONE occurrence-length slot total — the boot default 4 is
        // the no-occurrence plain landing's placeholder only).
        let MAX_SELECTION = 4;
        /* the size of ONE block (slots) — follows the committed occurrence's
           duration; the BUDGET (MAX_SELECTION) flexes with it at every
           commit site (A/B/C). */
        let BLOCK_SPAN = 4;
        /* true when the URL carried &duration= (my-timetable/venue handoffs):
           the ORIGINAL class's size is known, so auto-default slot picks
           (recent/first-option) must NOT resize the block away
           from it — only an explicit user pick in the slot panel may. */
        let urlSpanLocked = false;
        /* SDD arrange-intent-session-choice: the honest five-branch cause of the
           booking banner (renderBookingIntent) + the fallback prefill state. */
        let intentReason = '';           // '' | 'in-the-way' | 'too-soon' | 'held' | 'span' | 'type'
        let fallbackAttribution = null;  // {module, type, venue} — consumed by the intent toast
        let intentFallbackNoMatch = false; // fallback armed and found NOTHING compatible

        const weekData = generateWeekData();
        window.weekData = weekData; /* ui-common's populateWeekSelect reads the global */
        /* BRIDGE PATCH (explore P0 A1): the shared WeekNavigator rebuilds the
           grid through window.buildTimetable on every week change — every
           other timetable page exports it; the arrangement page never did,
           so week navigation updated the label while the CELLS stayed on the
           boot week (stale PH/pending columns, the "free cell → conflict
           popup" trap on every other week). */
        window.buildTimetable = buildTimetable;
        const venueSlotData = MockData.venueSlots;

        let selectedSlotsByVenue = {};
        var weekNav = new WeekNavigator(MockData.semester, weekData, 'weekSelector', 'arrangementWeek',
            /* hide the unbookable weeks (no bookable slot: past/current/holiday
               weeks are skipped — the selector lists absolute week numbers) */
            function(i) { return weekHasBookableSlot(weekData, i); });
        weekNav.onBeforeNavigate = function() { saveCurrentWeek(); };
        /* the "Earliest bookable" action flashes the lead-time boundary day */
        /* T08: on mobile the SAME action reveals the visible boundary day of
           the destination week (even when the week does not change); desktop
           keeps the original shared row flash. No selection is created. */
        weekNav.flashEarliestBookable = function() {
            if (arrangementMobile?.isMobile()) {
                const dayIdx = firstBookableDayIn(weekData, weekNav.currentWeek);
                if (dayIdx < 0) return;
                arrangementMobile.revealTarget({
                    weekIndex: weekNav.currentWeek,
                    venueKey: currentVenue.code || currentVenue,
                    dayIndex: dayIdx,
                    hourIndex: null,
                    tone: 'flash',
                });
                return;
            }
            flashEarliestBookableDay(weekData, weekNav.currentWeek);
        };
        /* restore-after-week-jump already happens inside buildTimetable()
           (loadCurrentWeek) — no after-navigate hook needed here */
        let currentVenue = 'B103';
        let venueDropdown = null;
        let selectedBlock = null;          // { day, startHour, endHour } or null
        let selectionHistory = [];          // block-level undo: each entry = { action:'select'|'deselect', block:{...} }
        let focusedCell = { day: null, hour: null };
        let revertingChange = false;        // suppress confirm guards while restoring a dropdown on cancel
        let lastSubject = '';               // last committed subject code
        let lastSlotIndex = null;           // last committed original-slot pick index
        let slotPickerSlots = [];           // currently rendered original-slot list (for cancel restore)

        /* ── T15: retention metadata for saved entries that cannot render ──
           selectedSlotsByVenue stays the ONLY selection store. This map only
           remembers which saved identity was unrenderable, so a null visual
           pointer cannot silently overwrite it (saveCurrentWeek) and the clock
           reconciliation (T16) can tell an expired entry from an explicit
           removal. Keyed "<venue>::<week>"; value = the exact saved block
           identity. In-memory only, exactly like the store: nothing is
           resurrected from metadata. */
        let retainedUnrenderableKeys = {};

        function sameBlock(a, b) {
            if (!a || !b) return false;
            const fields = ['day', 'startHour', 'endHour'];
            for (let i = 0; i < fields.length; i++) {
                const field = fields[i];
                if (a[field] === null || a[field] === undefined) return false;
                if (b[field] === null || b[field] === undefined) return false;
                const av = Number(a[field]);
                const bv = Number(b[field]);
                if (!Number.isFinite(av) || !Number.isFinite(bv) || av !== bv) return false;
            }
            return true;
        }

        function key(venueKey, weekKey) {
            const week = Number(weekKey);
            if (!Number.isInteger(week) || week < 0) return '';
            return String(venueKey) + '::' + week;
        }

        function markRetained(venueKey, weekKey, block) {
            const markerKey = key(venueKey, weekKey);
            const identity = block
                && block.day !== null && block.day !== undefined
                && block.startHour !== null && block.startHour !== undefined
                && block.endHour !== null && block.endHour !== undefined
                ? {
                    day: Number(block.day),
                    startHour: Number(block.startHour),
                    endHour: Number(block.endHour)
                } : null;
            if (!markerKey || !sameBlock(identity, identity)) return false;
            retainedUnrenderableKeys[markerKey] = identity;
            return true;
        }

        function isRetained(venueKey, weekKey, block) {
            const markerKey = key(venueKey, weekKey);
            if (!markerKey || !retainedUnrenderableKeys[markerKey]) return false;
            return block ? sameBlock(retainedUnrenderableKeys[markerKey], block) : true;
        }

        function clearRetained(venueKey, weekKey) {
            const markerKey = key(venueKey, weekKey);
            if (markerKey) delete retainedUnrenderableKeys[markerKey];
        }

        function clearAllRetained() {
            retainedUnrenderableKeys = {};
        }

        function saveCurrentWeek() {
            if (!selectedSlotsByVenue[currentVenue]) selectedSlotsByVenue[currentVenue] = {};
            const venueKey = currentVenue.code || currentVenue;
            const existing = selectedSlotsByVenue[currentVenue][weekNav.currentWeek] || null;
            if (selectedBlock) {
                selectedSlotsByVenue[currentVenue][weekNav.currentWeek] = { day: selectedBlock.day, startHour: selectedBlock.startHour, endHour: selectedBlock.endHour };
                clearRetained(venueKey, weekNav.currentWeek);
                return;
            }
            /* T15: a null pointer must not erase a saved entry that survives
               only as retained/unrenderable. Matching identity = preserve the
               store entry; anything else keeps the normal null save (and a
               missing entry is never resurrected from metadata). */
            if (existing && isRetained(venueKey, weekNav.currentWeek, existing)) return;
            selectedSlotsByVenue[currentVenue][weekNav.currentWeek] = null;
        }

        function loadCurrentWeek() {
            selectedBlock = null;
            clearPreview();
            const venueKey = currentVenue.code || currentVenue;
            const venueData = selectedSlotsByVenue[currentVenue] || {};
            const saved = venueData[weekNav.currentWeek] || null;
            if (!saved) {
                /* no saved identity to protect — never resurrect one */
                clearRetained(venueKey, weekNav.currentWeek);
                return;
            }
            const body = document.getElementById('tableBody');
            let restored = false;
            if (body) {
                const firstTd = body.querySelector(`td[data-day="${saved.day}"][data-hour="${saved.startHour}"]`);
                const firstCell = firstTd ? firstTd.querySelector('.cell-content') : null;
                if (firstCell && (firstCell.classList.contains('cell-available') || firstCell.classList.contains('cell-selected'))) {
                    // Check all cells in range are still available/selected
                    let allAvailable = true;
                    for (let h = saved.startHour; h < saved.endHour; h++) {
                        const cell = body.querySelector(`td[data-day="${saved.day}"][data-hour="${h}"] .cell-content`);
                        if (!cell || (!cell.classList.contains('cell-available') && !cell.classList.contains('cell-selected'))) { allAvailable = false; break; }
                    }
                    if (allAvailable) {
                        selectedBlock = { day: saved.day, startHour: saved.startHour, endHour: saved.endHour };
                        renderMergedBlock(body);
                        restored = true;
                    }
                }
            }
            if (restored) {
                clearRetained(venueKey, weekNav.currentWeek);
            } else {
                /* Unrenderable (expired / too-soon / Sunday / holiday / blocked):
                   keep the pointer null and the store untouched. The rebuild
                   already painted the real blocked states — never repaint the
                   old cells green and never call deselect/clearVisual here. */
                markRetained(venueKey, weekNav.currentWeek, saved);
            }
        }

        /* T15 hook for T16's live clock reconciliation (closure-scoped only —
           no global API). Read-only over the store: marks date-ineligible
           saved entries retained, keeps a marker until its identity is
           canonically restored, and drops only the stale visual pointer for a
           retained entry without repainting or rewriting anything. It starts
           no timer, listener, rebuild or save loop; T16 invokes it and then
           rebuilds. */
        function reconcileRetainedSelections() {
            const venueNow = currentVenue.code || currentVenue;
            const weekNow = weekNav ? Number(weekNav.currentWeek) : null;
            const seen = {};
            Object.keys(selectedSlotsByVenue).forEach(function (venueKey) {
                const weekMap = selectedSlotsByVenue[venueKey] || {};
                Object.keys(weekMap).forEach(function (weekKey) {
                    const block = weekMap[weekKey];
                    const weekIndex = Number(weekKey);
                    const markerKey = key(venueKey, weekIndex);
                    if (!markerKey) return;
                    if (!block) { delete retainedUnrenderableKeys[markerKey]; return; }
                    seen[markerKey] = true;
                    const week = weekData && weekData[weekIndex] ? weekData[weekIndex] : null;
                    const day = week && Array.isArray(week.days) ? week.days[Number(block.day)] : null;
                    const sunday = !!(day && (day.sunday || day.abbr === 'Sun'));
                    const holiday = !!(day && day.holiday);
                    const tooSoon = typeof isSlotTooSoon === 'function'
                        ? !!isSlotTooSoon(weekData, weekIndex, Number(block.day))
                        : true; /* helpers unavailable -> fail closed */
                    const eligible = !!day && !sunday && !holiday && !tooSoon;
                    if (!eligible) {
                        markRetained(venueKey, weekIndex, block);
                        if (String(venueKey) === String(venueNow) && weekIndex === weekNow
                            && selectedBlock && sameBlock(selectedBlock, block)) {
                            /* stale pointer only — cells keep their painted state;
                               no deselectBlock()/clearVisualSelection() green repaint */
                            selectedBlock = null;
                        }
                        return;
                    }
                    /* Eligible date: a marker may only drop once canonical loading
                       restored this exact identity (loadCurrentWeek does that).
                       A marker whose identity no longer matches protects nothing. */
                    if (retainedUnrenderableKeys[markerKey]) {
                        if (!isRetained(venueKey, weekIndex, block)) {
                            delete retainedUnrenderableKeys[markerKey];
                        } else if (String(venueKey) === String(venueNow) && weekIndex === weekNow
                            && selectedBlock && sameBlock(selectedBlock, block)) {
                            clearRetained(venueKey, weekIndex);
                        }
                    }
                });
            });
            /* a marker with no stored entry must never resurrect it */
            Object.keys(retainedUnrenderableKeys).forEach(function (markerKey) {
                if (!seen[markerKey]) delete retainedUnrenderableKeys[markerKey];
            });
        }

        /* ── T16: live clock reconciliation + eligible week-option rebuild ──
           ONE guarded entry point, shared by the local-midnight timeout, the
           visibility/focus listeners, the mobile selection port and (T17/T18)
           the confirmation opener. It never writes the booking store, never
           clears saved entries/history, never switches venue, never selects a
           time and never closes an open information sheet. */
        let mobileClockReconciling = false;
        let mobileClockLastDateKey = '';   // last LOCAL date a full pass ran for
        let mobileClockTimer = null;

        function mobileLocalDateKey(date) {
            const now = date instanceof Date ? date : new Date();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            return now.getFullYear() + '-' + month + '-' + day;
        }

        /* Existing helper only; week indexes stay ABSOLUTE. */
        function eligibleWeekIndexes() {
            const list = [];
            (weekData || []).forEach(function (week, index) {
                if (typeof weekHasBookableSlot === 'function' && weekHasBookableSlot(weekData, index)) {
                    list.push(index);
                }
            });
            return list;
        }

        /* Rebuild the EXISTING #weekSelector options through the shared helper
           (same ranges:false shape the boot uses). populateWeekSelect only
           rewrites innerHTML/value — it fires no synthetic change event. */
        function rebuildMobileWeekOptions(selected) {
            const cfg = {
                ranges: false,
                weekFilter: function (i) { return weekHasBookableSlot(weekData, i); }
            };
            if (Number.isInteger(selected)) cfg.selected = selected;
            populateWeekSelect('weekSelector', cfg);
            const sel = document.getElementById('weekSelector');
            if (sel && sel.options && sel.options.length === 0) {
                /* Zero eligible weeks: one disabled, non-navigable placeholder
                   stays inside the same select. No navigator fallback runs, so
                   no ineligible date can be exposed; T07's settled empty render
                   shows the exact message and disables the date controls. */
                const placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.textContent = 'No bookable weeks';
                placeholder.disabled = true;
                sel.appendChild(placeholder);
                sel.selectedIndex = 0;
            }
            return sel;
        }

        /**
         * Reconcile the live calendar. T15 metadata first, then rebuild the
         * week-option list from real weekData/weekHasBookableSlot, keep the
         * committed week when still eligible, otherwise resolve forward then
         * earliest via the ONE existing navigator. With zero eligible weeks it
         * writes the placeholder and refreshes T07's empty presentation only.
         * @returns {boolean} whether a full pass ran
         */
        function reconcileMobileClock() {
            if (!arrangementMobile || typeof arrangementMobile.isMobile !== 'function'
                || !arrangementMobile.isMobile()) return false;
            if (mobileClockReconciling) return false;
            const dateKey = mobileLocalDateKey(new Date());
            /* Focus/visibility/midnight can fire together. Eligibility here is
               date-grain, so one full pass per local date is enough and this
               guard stops any focus/visibility/rebuild feedback loop. */
            if (dateKey === mobileClockLastDateKey) return false;
            mobileClockReconciling = true;
            try {
                mobileClockLastDateKey = dateKey;
                /* 1. T15 first: mark date-ineligible saved identities, clear
                   only a stale matching pointer — never rewrite the store. */
                reconcileRetainedSelections();
                if (!weekNav) return true;
                const eligible = eligibleWeekIndexes();
                const current = Number(weekNav.currentWeek);
                if (!eligible.length) {
                    rebuildMobileWeekOptions(null);
                    /* No build/navigation: the committed week may be ineligible.
                       The settled refresh lets T07 show MA-03f and disable the
                       date controls + Submit; saved entries stay inspectable. */
                    refreshArrangementMobile('clock');
                    return true;
                }
                let target = eligible.indexOf(current) !== -1 ? current : null;
                if (target === null) {
                    for (let i = 0; i < eligible.length; i++) {
                        if (eligible[i] > current) { target = eligible[i]; break; }
                    }
                    if (target === null) target = eligible[0]; // earliest eligible
                }
                rebuildMobileWeekOptions(target);
                if (target === current) {
                    /* Same eligible week: rebuild canonical cells so newly
                       too-soon/holiday cells repaint (refresh follows). */
                    buildTimetable();
                } else {
                    /* One canonical navigation: onBeforeNavigate saves (T15
                       protected), then grid/select/arrows rebuild. Venue and
                       selected time are untouched. */
                    weekNav.selectWeek(target);
                }
                return true;
            } finally {
                mobileClockReconciling = false;
            }
        }

        /* One next-local-midnight timeout at a time; each fires the same
           guarded reconcile and re-arms for the following boundary. */
        function scheduleMobileClock() {
            if (mobileClockTimer !== null) return;
            const now = new Date();
            const next = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1, 0, 0, 1, 0);
            const delay = Math.max(1000, next.getTime() - now.getTime());
            mobileClockTimer = setTimeout(function () {
                mobileClockTimer = null;
                reconcileMobileClock();
                scheduleMobileClock();
            }, delay);
        }

        /* ── T17: final-confirmation identity/date validation (mobile-origin) ──
           proceed() captures the EXACT identity the confirmation presents; the
           existing Yes callback rechecks it immediately before the one Livewire
           call. Read-only over the selection store and the T15 marker map: no
           request latch (T18), no second submit path, no auto-retry/clear. The
           desktop-origin flow never captures a context and is unchanged. */
        const MOBILE_SUBMIT_REVIEW_TITLE = 'Review Your Saved <span class="warn-keyword">Selection</span>';
        const MOBILE_SUBMIT_REVIEW_HINT = 'Nothing was submitted and your saved selection was kept. Review it, remove it from the saved summary if needed, then choose another bookable date.';

        function mobileHasRetention() {
            return Object.keys(retainedUnrenderableKeys).length > 0;
        }

        function mobileTextOrNull(value) {
            if (value === null || value === undefined) return null;
            const text = String(value).trim();
            return text === '' ? null : text;
        }

        function mobileNumberOrNull(value) {
            if (value === null || value === undefined || value === '') return null;
            const num = Number(value);
            return Number.isFinite(num) ? num : null;
        }

        /* Deterministic shallow copy so two captures of the pinned occurrence
           compare by value in JSON, not by property insertion order. */
        function mobileComparableObject(value) {
            if (!value || typeof value !== 'object' || Array.isArray(value)) return null;
            const out = {};
            Object.keys(value).sort().forEach(function (name) {
                const raw = value[name];
                out[name] = raw === undefined ? null : raw;
            });
            return out;
        }

        function mobileSavedEntryIdentities() {
            const entries = [];
            Object.keys(selectedSlotsByVenue).forEach(function (venueKey) {
                const weekMap = selectedSlotsByVenue[venueKey] || {};
                Object.keys(weekMap).forEach(function (weekKey) {
                    const block = weekMap[weekKey];
                    if (!block) return;
                    const weekIndex = mobileNumberOrNull(weekKey);
                    if (weekIndex === null) return;
                    entries.push({
                        venueKey: String(venueKey),
                        weekIndex: weekIndex,
                        day: mobileNumberOrNull(block.day),
                        startHour: mobileNumberOrNull(block.startHour),
                        endHour: mobileNumberOrNull(block.endHour)
                    });
                });
            });
            entries.sort(function (a, b) {
                if (a.venueKey !== b.venueKey) return a.venueKey < b.venueKey ? -1 : 1;
                return a.weekIndex - b.weekIndex;
            });
            return entries;
        }

        /* The existing submit argument shape: the picked original slot wins,
           otherwise the arrival URL pair. Numbers are normalised for comparison
           only — the submitted JSON itself is untouched. */
        function mobileOriginalIdentity() {
            const source = selectedOriginalSlot
                ? { day: selectedOriginalSlot.day, start: selectedOriginalSlot.start, venue: selectedOriginalSlot.venue }
                : { day: urlParams.day, start: urlParams.start, venue: urlParams.originalVenue };
            return {
                day: mobileNumberOrNull(source.day),
                start: mobileNumberOrNull(source.start),
                venue: mobileTextOrNull(source.venue)
            };
        }

        function mobileRetentionIdentity() {
            const keys = Object.keys(retainedUnrenderableKeys).sort();
            return { count: keys.length, keys: keys };
        }

        /**
         * Read-only snapshot of everything the pending confirmation would send.
         * Never mutates the store or the marker map.
         * @returns {object} JSON-comparable presented identity
         */
        function captureMobileSubmissionIdentity() {
            return {
                entries: mobileSavedEntryIdentities(),
                subject: currentCourse ? String(currentCourse.code || '') : '',
                original: mobileOriginalIdentity(),
                pin: mobileComparableObject(window.pinnedOccurrence),
                retention: mobileRetentionIdentity()
            };
        }

        /**
         * Re-run the EXISTING date rules for every saved entry. A missing
         * week/day or unavailable helper fails closed.
         * @returns {boolean} true when every saved entry is still a bookable date
         */
        function mobileSavedDatesStillValid() {
            if (typeof isSlotTooSoon !== 'function') return false;
            const venueKeys = Object.keys(selectedSlotsByVenue);
            for (let v = 0; v < venueKeys.length; v++) {
                const weekMap = selectedSlotsByVenue[venueKeys[v]] || {};
                const weekKeys = Object.keys(weekMap);
                for (let w = 0; w < weekKeys.length; w++) {
                    const block = weekMap[weekKeys[w]];
                    if (!block) continue;
                    const weekIndex = Number(weekKeys[w]);
                    if (!Number.isInteger(weekIndex) || weekIndex < 0) return false;
                    const week = weekData && weekData[weekIndex] ? weekData[weekIndex] : null;
                    const dayIndex = Number(block.day);
                    const day = week && Array.isArray(week.days) && Number.isInteger(dayIndex)
                        ? week.days[dayIndex] : null;
                    if (!day) return false;
                    if (day.sunday || day.abbr === 'Sun' || day.holiday) return false;
                    if (isSlotTooSoon(weekData, weekIndex, dayIndex)) return false;
                }
            }
            return true;
        }

        /**
         * Yes-boundary validation: retention present, any saved date expired, or
         * any saved/subject/original/pin/retention detail changed since the
         * confirmation was presented all refuse the send.
         * @param {object} presented — captureMobileSubmissionIdentity() at open
         * @returns {boolean} true only for the exact still-valid presented identity
         */
        function mobileSubmissionStillValid(presented) {
            if (!presented || typeof presented !== 'object') return false;
            if (mobileHasRetention()) return false;
            if (!mobileSavedDatesStillValid()) return false;
            return JSON.stringify(captureMobileSubmissionIdentity()) === JSON.stringify(presented);
        }

        /* Persistent guidance only (no timer). Saved data is never cleared. */
        function showMobileSubmissionReview(cause) {
            let reason = 'Your saved selection is no longer bookable for the date it was picked for.';
            if (cause === 'retention') {
                reason = 'A saved selection is no longer bookable.';
            } else if (cause === 'identity') {
                reason = 'Your saved selection, subject, original slot or pinned occurrence changed while the confirmation was open.';
            }
            showAlertModal(MOBILE_SUBMIT_REVIEW_TITLE, reason + '<br><br>' + MOBILE_SUBMIT_REVIEW_HINT, null);
        }

        /* ── T18: mobile-origin request latch + failure recovery ──
           ONE page-owned latch around the ONE existing Livewire submit call.
           It is set synchronously after T17's Yes-boundary validation and is
           released only on a confirmed business rejection or an UNCONFIRMED
           transport/JS outcome. A desktop-origin request never sets it, and no
           adapter resize/destroy can drop it — it is page state, not adapter
           state. Never auto-retries, never clears the saved selection. */
        const MOBILE_SUBMIT_UNKNOWN_TITLE = 'Request <span class="warn-keyword">OUTCOME UNKNOWN</span>';
        const MOBILE_SUBMIT_UNKNOWN_BODY = 'We could not confirm that your replacement request reached the server, so it may or may not have been received.'
            + '<br><br>Open <span class="go-keyword">My Replacements</span> and check for a new request before submitting again.'
            + ' Your saved selection was kept and nothing will be sent again automatically.';

        let mobileSubmissionInFlight = false;

        /* The existing Submit plus the confirmation Yes/Action buttons. */
        function mobileSubmitBusyButtons() {
            const buttons = [];
            const submit = document.getElementById('submitRequestBtn');
            if (submit) buttons.push(submit);
            const confirmBtn = document.getElementById('modalConfirmBtn');
            if (confirmBtn) buttons.push(confirmBtn);
            const actionBtn = document.getElementById('modalActionBtn');
            if (actionBtn) buttons.push(actionBtn);
            return buttons;
        }

        /* Busy=true: disabled immediately (Submit + the hidden confirmation
           buttons, which no second Yes may reach). Busy=false restores exactly
           those nodes; updateCounter()/the adapter gates then re-derive Submit. */
        function setMobileSubmissionBusy(busy) {
            mobileSubmitBusyButtons().forEach(function (btn) {
                btn.disabled = !!busy;
            });
        }

        /* Release = clear the latch, then hand Submit back to the EXISTING
           updateCounter() gates (saved total + the T16 retention gate re-applied
           by the settled mobile refresh). Store/markers are never touched. */
        function releaseMobileSubmissionLatch() {
            if (!mobileSubmissionInFlight) return;
            mobileSubmissionInFlight = false;
            setMobileSubmissionBusy(false);
            updateCounter();
        }

        /* Persistent only (existing showAlertModal: one Close, no timer, no
           action callback). States the outcome is UNKNOWN — never a confirmed
           failure — and points at My Replacements before any retry. */
        function showMobileSubmissionUnknown() {
            showAlertModal(MOBILE_SUBMIT_UNKNOWN_TITLE, MOBILE_SUBMIT_UNKNOWN_BODY, null);
        }

        /* ── T19: page-level teardown for a real full-page departure ──
           The page closure owns T16's ONE next-midnight timeout and its two
           clock listeners; the adapter owns its own scheduler, pulse timer,
           observer/resize fallback, delegates, insets and rendered hosts. Both
           are released here, exactly once, and only after `pagehide` reports a
           genuine unload. Back/forward-cache departures (`persisted === true`)
           are skipped on purpose: the browser freezes this page and later
           resumes the SAME document, so its listeners and Livewire state must
           survive; the existing `pageshow` handler owns that restore. Nothing
           below reads or writes booking state, selectedSlotsByVenue, T15
           markers, selectionHistory or T18's mobileSubmissionInFlight — a real
           page load is what resets those — and no save/build/counter/submit
           function is called. A null-safe try/catch keeps the departure safe
           even if a DOM-poor teardown throws; the listeners are already gone. */
        let arrangementMobileTornDown = false;

        function onMobileClockVisibility() {
            if (document.visibilityState === 'visible') reconcileMobileClock();
        }

        function onMobileClockFocus() {
            reconcileMobileClock();
        }

        function teardownArrangementMobile() {
            if (arrangementMobileTornDown) return false;
            arrangementMobileTornDown = true;
            if (mobileClockTimer !== null) {
                clearTimeout(mobileClockTimer);
                mobileClockTimer = null;
            }
            document.removeEventListener('visibilitychange', onMobileClockVisibility);
            window.removeEventListener('focus', onMobileClockFocus);
            if (arrangementMobile && typeof arrangementMobile.destroy === 'function') {
                try {
                    arrangementMobile.destroy();
                } catch (err) {
                    /* The page is leaving; its clock resources are already gone. */
                }
            }
            return true;
        }

        /* `pagehide` covers navigation, reload and close. `persisted === true`
           means bfcache: keep the live controller (never a destroyed layout on
           Back). A missing event is treated as a real departure. */
        function onArrangementPageHide(event) {
            if (event && event.persisted === true) return;
            teardownArrangementMobile();
        }

        function getDays() {
            /* Empty-semester fail-safe: the week select can have no eligible
               option, so its value is ''. Fall back to the navigator's current
               (clamped) week instead of indexing weekData[NaN] and aborting the
               boot before the mobile empty state can mount. */
            const sel = document.getElementById('weekSelector');
            let idx = sel ? parseInt(sel.value, 10) : NaN;
            if (!Number.isInteger(idx) || !weekData[idx]) {
                idx = weekNav && Number.isInteger(weekNav.currentWeek) ? weekNav.currentWeek : 0;
            }
            const week = weekData[idx] || weekData[0];
            return week && Array.isArray(week.days) ? week.days : [];
        }

        function getGlobalTotal() {
            let total = 0;
            Object.values(selectedSlotsByVenue).forEach(venue => {
                Object.values(venue).forEach(block => {
                    if (block) total += (block.endHour - block.startHour);
                });
            });
            return total;
        }

        function updateCounter() {
            /* multi-week model: the counter and button states reflect ALL saved
               selections, not just the week on screen */
            const total = getGlobalTotal();
            const el = document.getElementById('selCount');
            if (el) el.textContent = total;
            const has = total > 0;
            const btn = document.getElementById('submitRequestBtn') || document.querySelector('.btn-primary');
            /* T18: the mobile-origin request latch keeps Submit disabled even
               while saved slots remain; only releaseMobileSubmissionLatch()
               re-derives it through this same gate. Desktop never sets it. */
            if (btn) btn.disabled = !has || mobileSubmissionInFlight;
            const clearBtn = document.getElementById('clearAllBtn');
            if (clearBtn) clearBtn.disabled = !has;
            /* Footer button tooltips — state-aware (buttons render above via the shared
               data-tip system; the wrapper span keeps hover alive while disabled) */
            const submitTip = document.getElementById('submitTip');
            if (submitTip) submitTip.setAttribute('data-tip', has
                ? 'Send your replacement request for approval'
                : 'Select a time slot first to enable submission');
            const clearTip = document.getElementById('clearTip');
            if (clearTip) clearTip.setAttribute('data-tip', has
                ? 'Clear all your selected slots'
                : 'Nothing to clear yet — select a time slot first');
            updateSelectionSummary();
            updateSelectionProgress();
            updateSummaryStats();
            /* settled: counter, summary cards and progress are all committed */
            refreshArrangementMobile('counter');
        }

        function updateSummaryStats() {
            /* Count exactly what the grid renders, so the cards match the timetable.
               Unavailable = Occupied + Booked-by-others(Reserved) + Sunday + Public Holiday.
               Available includes the user's current selection (still a pickable slot). */
            const available = document.querySelectorAll('.timetable .cell-content.cell-available').length
                + document.querySelectorAll('.timetable .cell-content.cell-selected').length;
            const pending = document.querySelectorAll('.timetable .cell-content.cell-pending').length;
            const occupied = document.querySelectorAll('.timetable .cell-content.cell-occupied').length;
            const reserved = document.querySelectorAll('.timetable .cell-content.cell-reserved').length;
            const sunday = document.querySelectorAll('.timetable .cell-content.cell-sun').length;
            const ph = document.querySelectorAll('.timetable .cell-content.cell-ph').length;
            const unavailable = occupied + reserved + sunday + ph;

            const sumTotal = document.getElementById('sumTotal');
            if (sumTotal) {
                sumTotal.textContent = available + pending + unavailable;
                document.getElementById('sumAvailable').textContent = available;
                document.getElementById('sumPending').textContent = pending;
                document.getElementById('sumUnavailable').textContent = unavailable;
            }
        }

        /* 2026-11-10 (user, "UI Update — Too-Soon Tooltip", final wording):
             cell hover   Min 3 / Working Days               (short, inside the cell)
             data-tip     Rule : Only bookable at least 3 working days from today.
                          Earliest Bookable : Friday, 13 Nov 2026.

           The date is NOT hard-coded: it is the same `leadTimeCutoff()` the grid uses
           to decide which cells are too soon, formatted with the FULL weekday name
           (`Friday, 13 Nov 2026`), so the words can never drift from the rule.
           `label` feeds the in-cell span (a real `<br>`); `text` goes to the shared
           tooltip, which renders TEXT — so it takes `\n` (its multi-line class breaks
           the line) and never a literal `<br>`, which would print as characters. */
        function leadTimeTip() {
            var cutoff = leadTimeCutoff(weekData);
            var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            var months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            var dateStr = days[cutoff.getDay()] + ', ' + cutoff.getDate() + ' ' + months[cutoff.getMonth()] + ' ' + cutoff.getFullYear();
            return {
                /* the cell's own hover label — short, two lines (real element, real `<br>`) */
                label: 'Min 3<br>Working Days',
                /* the detailed tooltip — `\n`, because its renderer writes textContent */
                text: 'Rule : Only bookable at least 3 working days from today.\nEarliest Bookable : ' + dateStr + '.'
            };
        }

        /* UI Update 1 (user, 2026-11-10): the venue row's hover tooltip is the room's
           NAME plus its seats — `Tutorial Room (35 seats)` — never the code twice
           (ModalFacts.roomName strips the copy the DB name already carries). */
        function summaryVenueTip(code) {
            var v = (MockData.venues || []).find(function (x) { return x.code === code; });
            if (!v) return '';
            var name = window.ModalFacts ? ModalFacts.roomName(code, v.name) : (v.name || '');
            var cap = Number(v.capacity);
            var seats = (v.capacity != null && isFinite(cap) && cap > 0)
                ? ' (' + cap + (cap === 1 ? ' seat' : ' seats') + ')'
                : '';
            return (name || '') + seats;
        }

        /* 2026-11-11 (user, "UI Update — Compact Tip Tooltip"): the visible
           label is just "Tip"; the sentence lives in the shared data-tip
           tooltip (hover + keyboard focus). */
        const SUMMARY_TIP_BASE = 'Click an available (green) time slot to begin.';
        function setSummaryTip(message) {
            const tip = document.getElementById('summaryTip');
            if (!tip) return;
            tip.setAttribute('data-tip', message);
            tip.setAttribute('aria-label', 'Tip: ' + message.replace(/\n/g, ' '));
            /* settled: Tip text + aria are both written */
            refreshArrangementMobile('tip');
        }

        function updateSelectionSummary() {
            /* multi-week model: the panel lists EVERY saved selection (any week),
               matching the Submit dialog and the enabled Submit button */
            const allBlocks = [];
            Object.keys(selectedSlotsByVenue).forEach(venueKey => {
                const venueData = selectedSlotsByVenue[venueKey];
                if (!venueData) return;
                Object.keys(venueData).forEach(weekKey => {
                    const block = venueData[weekKey];
                    if (!block) return;
                    const days = weekData[weekKey].days;
                    allBlocks.push({
                        venue: venueKey,
                        weekIdx: parseInt(weekKey, 10),
                        weekLabel: weekData[weekKey].label,
                        day: days[block.day],
                        dayIdx: block.day,
                        startHour: block.startHour,
                        endHour: block.endHour,
                        slotCount: block.endHour - block.startHour
                    });
                });
            });
            allBlocks.sort((a, b) => a.weekIdx - b.weekIdx || a.dayIdx - b.dayIdx || a.startHour - b.startHour);

            const grid = document.getElementById('summaryGrid');

            if (allBlocks.length === 0) {
                const sc = document.getElementById('summaryCount');
                if (sc) sc.textContent = '0';
                document.getElementById('summaryEmpty').style.display = '';
                grid.style.display = 'none';
                grid.innerHTML = '';
                document.getElementById('summaryInfo').style.display = 'none';
                setSummaryTip(SUMMARY_TIP_BASE);
                /* the 'N of M slots' counter lives outside the empty-state —
                   without this it kept the pre-clear value (stale '4 of 4'
                   after a deselect/clear) */
                document.getElementById('infoTotal').textContent = `0 of ${MAX_SELECTION} slots`;
                const durEl = document.getElementById('infoDuration');
                if (durEl) durEl.textContent = '0m';
                return;
            }

            document.getElementById('summaryEmpty').style.display = 'none';
            grid.style.display = 'grid';
            grid.innerHTML = '';
            document.getElementById('summaryInfo').style.display = '';

            allBlocks.forEach(b => {
                const startStr = hours[b.startHour];
                const endStr = add30min(hours[b.endHour - 1]);
                const endDisplay = add30min(hours[b.endHour - 1]); // end of last slot
                /* duration in hours (30-min slots): 4 → 2 Hours, 3 → 1.5 Hours, 2 → 1 Hours */
                const durH = b.slotCount / 2;
                const durLabel = (Number.isInteger(durH) ? durH : durH.toFixed(1)) + ' Hours';

                const card = document.createElement('div');
                card.className = 'sel-summary-card';
                card.dataset.venue = b.venue;
                card.dataset.week = b.weekIdx;
                /* SDD summary-card-click-navigate: the card is a hop button
                   (the × keeps its own onclick + its own tip; the card's
                   click early-returns on the ×). */
                card.setAttribute('data-tip', 'Click to Go to This Selected Slot on the Timetable.');
                card.setAttribute('tabindex', '0');
                card.setAttribute('role', 'button');
                card.addEventListener('keydown', function (e) {
                    if (e.key !== 'Enter' && e.key !== ' ') return;
                    if (e.target.closest && e.target.closest('.card-remove')) return;
                    e.preventDefault();
                    navigateToSavedBlock(b.venue, b.weekIdx, b);
                });
                card.addEventListener('click', function (e) {
                    if (e.target.closest && e.target.closest('.card-remove')) return;
                    navigateToSavedBlock(b.venue, b.weekIdx, b);
                });
                /* UI Update 1 (user, 2026-11-10) — the exact order + wording:
                     1  B111                       (tooltip: Tutorial Room (35 seats))
                     2  W8, Sat, 14 Nov 2026
                     3  12:00 PM ~ 2:00 PM
                     4  4 slots (2 Hours)                                          */
                const icons = {
                    venue: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>',
                    week: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
                    time: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
                    duration: '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 2h12M6 22h12M8 2v4a4 4 0 0 0 8 0V2M8 22v-4a4 4 0 0 1 8 0v4"/></svg>'
                };
                /* "Week 8" → "W8"; the date already arrives as `14 Nov 2026` */
                const weekShort = String(b.weekLabel).replace(/^Week\s*/i, 'W');
                const venueTip = summaryVenueTip(b.venue);
                const venueTipAttr = venueTip ? ' data-tip="' + String(venueTip).replace(/"/g, '&quot;') + '"' : '';
                card.innerHTML = `
                    <button class="card-remove" onclick="removeSavedBlock('${b.venue}', ${b.weekIdx})" data-tip="Remove this Selection — Action cannot be UNDONE" aria-label="Remove">×</button>
                    <div class="card-row"${venueTipAttr}>${icons.venue}<span class="card-row-text">${b.venue}</span></div>
                    <div class="card-row">${icons.week}<span class="card-row-text">${weekShort}, ${b.day.abbr}, ${b.day.date}</span></div>
                    <div class="card-row">${icons.time}<span class="card-row-text">${to12h(startStr)} ~ ${to12h(endDisplay)}</span></div>
                    <div class="card-row">${icons.duration}<span class="card-row-text">${b.slotCount} slots (${durLabel})</span></div>
                `;
                grid.appendChild(card);
            });

            const totalSlots = allBlocks.reduce((sum, b) => sum + b.slotCount, 0);
            const sc = document.getElementById('summaryCount');
            if (sc) sc.textContent = totalSlots;
            document.getElementById('infoTotal').textContent = `${totalSlots} of ${MAX_SELECTION} slots`;
            const totalMins = totalSlots * 30;
            const hrs = Math.floor(totalMins / 60);
            const mins = totalMins % 60;
            const durationStr = hrs > 0 ? `${hrs}h ${mins}m` : `${mins}m`;
            document.getElementById('infoDuration').textContent = durationStr;
            document.getElementById('infoBuilding').textContent = venueDropdown ? venueDropdown.getSelected() : currentVenue;

            if (getGlobalTotal() >= MAX_SELECTION) {
                setSummaryTip('Maximum selection reached.\nClick the selected block to remove it.');
            } else if (allBlocks.length > 0) {
                setSummaryTip('Click the selected block to remove it.');
            } else {
                setSummaryTip(SUMMARY_TIP_BASE);
            }
        }

        function deselectBlock() {
            if (!selectedBlock) return;
            const body = document.getElementById('tableBody');
            clearMergedBlock(body);
            // Restore cell-available on all cells in range
            for (let h = selectedBlock.startHour; h < selectedBlock.endHour; h++) {
                const td = body.querySelector(`td[data-day="${selectedBlock.day}"][data-hour="${h}"]`);
                if (td) {
                    const cellDiv = td.querySelector('.cell-content');
                    if (cellDiv) {
                        cellDiv.classList.remove('cell-selected');
                        cellDiv.classList.add('cell-available');
                        cellDiv.innerHTML = timeLabelHtml(h);
                    }
                }
            }
            pushHistory({ action: 'deselect', block: { ...selectedBlock }, venue: currentVenue.code || currentVenue, week: weekNav.currentWeek });
            selectedBlock = null;
            if (!selectedSlotsByVenue[currentVenue]) selectedSlotsByVenue[currentVenue] = {};
            selectedSlotsByVenue[currentVenue][weekNav.currentWeek] = null;
            /* T15: an explicit pointer removal retires its retention marker */
            clearRetained(currentVenue.code || currentVenue, weekNav.currentWeek);
            // Re-enable hover on available cells
            const table = body.closest('.timetable');
            if (table) table.classList.remove('has-selection');
            updateCounter();
            /* notif-ghost-retract-strip: updateCounter chains
               updateSelectionSummary/Progress/Stats at :1333-1335, so the
               explicit call below is the belt-braces duplicate — kept
               (harmless) since the deselect's early-return paths used to rely
               on it before :1334 chained the strip; the comment's old claim
               ('rendered by updateSelectionSummary, not updateCounter') is
               FALSE since the chain. */
            updateSelectionSummary();
        }

        // Like deselectBlock, but preserves saved data in selectedSlotsByVenue
        // so loadCurrentWeek can restore the selection when navigating back.
        function clearVisualSelection() {
            if (!selectedBlock) return;
            const body = document.getElementById('tableBody');
            clearMergedBlock(body);
            for (let h = selectedBlock.startHour; h < selectedBlock.endHour; h++) {
                const td = body.querySelector(`td[data-day="${selectedBlock.day}"][data-hour="${h}"]`);
                if (td) {
                    const cellDiv = td.querySelector('.cell-content');
                    if (cellDiv) {
                        cellDiv.classList.remove('cell-selected');
                        cellDiv.classList.add('cell-available');
                        cellDiv.innerHTML = timeLabelHtml(h);
                    }
                }
            }
            selectedBlock = null;
            const table = body.closest('.timetable');
            if (table) table.classList.remove('has-selection');
            updateCounter();
        }

        function renderMergedBlock(body) {
            if (!selectedBlock) return;
            const span = selectedBlock.endHour - selectedBlock.startHour;
            // Remove existing block if any
            clearMergedBlock(body);
            // Hide intermediate tds and set colSpan on first td
            for (let h = selectedBlock.startHour; h < selectedBlock.endHour; h++) {
                const td = body.querySelector(`td[data-day="${selectedBlock.day}"][data-hour="${h}"]`);
                if (!td) continue;
                if (h === selectedBlock.startHour) {
                    td.colSpan = span;
                } else {
                    td.style.display = 'none';
                }
                // Mark cells as selected
                const cellDiv = td.querySelector('.cell-content');
                if (cellDiv) {
                    cellDiv.classList.remove('cell-available');
                    cellDiv.classList.add('cell-selected');
                }
            }
            // Create event-block on first td
            const firstTd = body.querySelector(`td[data-day="${selectedBlock.day}"][data-hour="${selectedBlock.startHour}"]`);
            if (!firstTd) return;
            const div = document.createElement('div');
            div.className = 'event-block event-selection';
            div.setAttribute('tabindex', '0');
            const startStr = hours[selectedBlock.startHour];
            const endStr = add30min(hours[selectedBlock.endHour - 1]);
            div.innerHTML = `<span class="ev-time-label">${to12h(startStr)} ~ ${to12h(endStr)}</span>`;
            div.addEventListener('click', () => userDeselectSelectedBlock());
            firstTd.appendChild(div);
            // Disable hover on other available cells
            const table = body.closest('.timetable');
            if (table) table.classList.add('has-selection');
            // Blocked-state hint: green slots explain why they can't be picked (renders above
            // the cell via the shared data-tip tooltip) until the selection is cleared
            body.querySelectorAll('.cell-content.cell-available').forEach(c => {
                c.setAttribute('data-tip', 'Clear your selection first — click it to remove');
            });
        }

        function clearMergedBlock(body) {
            if (!body) body = document.getElementById('tableBody');
            // Remove existing event-block
            body.querySelectorAll('.event-selection').forEach(el => el.remove());
            /* matching predicate: strips ONLY the has-selection hints the
               setter above put on .cell-available — holder tips survive
               selections (SDD selection-wipe-spares-holder-tips). */
            body.querySelectorAll('.cell-content.cell-available[data-tip]').forEach(c => c.removeAttribute('data-tip'));
            // Reset colSpan and display on all cells
            if (selectedBlock) {
                for (let h = selectedBlock.startHour; h < selectedBlock.endHour; h++) {
                    const td = body.querySelector(`td[data-day="${selectedBlock.day}"][data-hour="${h}"]`);
                    if (td) {
                        td.colSpan = 1;
                        td.style.display = '';
                    }
                }
            }
        }

        function isCellAvailable(day, hour) {
            const body = document.getElementById('tableBody');
            const td = body.querySelector(`td[data-day="${day}"][data-hour="${hour}"] .cell-content`);
            return td && td.classList.contains('cell-available');
        }

        function canPlaceBlock(day, startHour) {
            const span = BLOCK_SPAN;
            if (startHour + span > hours.length) return false;
            for (let h = startHour; h < startHour + span; h++) {
                if (!isCellAvailable(day, h)) return false;
            }
            return true;
        }

        function selectBlock(day, startHour) {
            if (selectedBlock) {
                showAlertModal('Change <span class="warn-keyword">Block</span>?', 'You already have a selected <span class="warn-keyword">block</span>. Clear it first before selecting a new one.', {
                    label: 'Clear Current Selection',
                    fn: function() { userDeselectSelectedBlock(); }
                });
                return false;
            }
            const span = BLOCK_SPAN;
            if (!canPlaceBlock(day, startHour)) {
                /* keyword voice (user UI directive 2026-10-24): CAP + bold + theme
                   tokens via showRich — the em-dash kept as \u2014 (blade-safe ASCII) */
                toast.showRich(startHour + span > hours.length
                    ? '<strong style="color:var(--color-on-error-container)">Won\'t Fit Today</strong><span style="color:var(--color-on-primary-container)"> \u2014 pick an earlier </span><strong style="color:var(--color-on-success-container)">Cell</strong>.'
                    : (span * 30) + '-min block <strong style="color:var(--color-on-error-container)">Won\'t Fit</strong><span style="color:var(--color-on-primary-container)"> \u2014 some part is taken. </span>Pick a <strong style="color:var(--color-on-success-container)">Free Cell</strong> below.');
                return false;
            }
            /* multi-week budget: MAX_SELECTION slots in total across every week */
            if (getGlobalTotal() + span > MAX_SELECTION) {
                toast.show('You\u2019ve reached the ' + MAX_SELECTION + '-slot limit \u2014 clear your selection to pick another.');
                return false;
            }
            /* a real (manual) selection completes the booking — drop the intent banner */
            if (pendingBookingIntent) { pendingBookingIntent = null; bookingIntentMemory = null; renderBookingIntent(); }
            selectedBlock = { day, startHour, endHour: startHour + span };
            previewRange = null;
            const body = document.getElementById('tableBody');
            renderMergedBlock(body);
            if (!selectedSlotsByVenue[currentVenue]) selectedSlotsByVenue[currentVenue] = {};
            selectedSlotsByVenue[currentVenue][weekNav.currentWeek] = { day, startHour, endHour: startHour + span };
            /* T15: a new valid selection retires any marker for this key */
            clearRetained(currentVenue.code || currentVenue, weekNav.currentWeek);
            pushHistory({ action: 'select', block: { ...selectedBlock }, venue: currentVenue.code || currentVenue, week: weekNav.currentWeek });
            updateCounter();
            return true;
        }

        let previewRange = null;

        function previewBlock(day, startHour, el) {
            clearPreview();
            if (selectedBlock) return;
            const span = BLOCK_SPAN;
            const body = document.getElementById('tableBody');
            const noSubject = !currentCourse; /* locked grid: preview explains instead of inviting */
            const ok = !noSubject && canPlaceBlock(day, startHour);
            previewRange = { day, startHour, endHour: startHour + span };
            const firstTd = body.querySelector(`td[data-day="${day}"][data-hour="${startHour}"]`);
            if (!firstTd) return;
            const div = document.createElement('div');
            div.className = 'event-block event-selection-preview ' + (ok ? 'preview-ok' : 'preview-fail');
            div.style.position = 'absolute';
            div.style.top = firstTd.offsetTop + 'px';
            div.style.left = firstTd.offsetLeft + 'px';
            div.style.height = firstTd.offsetHeight + 'px';
            const table = document.getElementById('timetable');
            const borderSpacing = parseInt(getComputedStyle(table).borderSpacing) || 0;
            div.style.width = (firstTd.offsetWidth * span + (span - 1) * borderSpacing) + 'px';
            div.style.pointerEvents = 'none';
            const label = document.createElement('span');
            label.className = 'preview-label';
            label.textContent = ok ? 'Select these slots?' : (noSubject ? 'Pick a course first' : 'Not enough slots');
            div.appendChild(label);
            if (!ok) firstTd.style.cursor = 'not-allowed';
            table.appendChild(div);
        }

        function clearPreview() {
            const table = document.getElementById('timetable');
            if (!table) return;
            table.querySelectorAll('.event-selection-preview').forEach(el => el.remove());
            const body = document.getElementById('tableBody');
            if (body) body.querySelectorAll('td[data-hour]').forEach(td => { td.style.cursor = ''; });
            previewRange = null;
        }

        /* SDD arrangement-slot-ownership-legend (2026-11-06): the cell's ONE
           classification, from the two arms together. Colour = state
           (amber = a pending reservation, red = a conflict), border =
           ownership (3px = yours, none = others'). Pure, exported on
           window, and exercised directly by the browser receipt.

           Precedence — deliberately counter-intuitive in two places:
             1. own pending   (grid status 3, or a pending row with mine)
             2. others' pending (grid status 4, or a pending row without mine)
             3. own conflict  (any non-pending row with mine)
             4. any other block (engineBlocked, or grid status 1)
           Rules 1–2 decide the AMBER family and rule 3 the RED one, so a
           cell that is both my class and someone's pending is amber, and a
           red cell is 3px if ANY blocking row is mine — the rule asks "is
           any row mine?", never "is any row not mine?". A missing 6th key
           is not "others'" (it falls through to rule 4). */
        /* SDD arrangement-cell-state-labels (user 2026-11-07, answers 1A 2A 3A):
             ONE state resolver answers BOTH questions — the cell's class and the
           words it shows on hover. The words name the REASON, from data only:
           `mine` says the block is on your own calendar; the 7th tuple key
           `arms` says which calendar produced the row ('own'|'cohort'); the grid
           payload's status 1 says the ROOM is taken. Nothing parses a holder
           string. A missing 7th key (a stale tuple) simply yields no cohort arm,
           which is the old behaviour. */
        function classifyState(facts) {
            const matches = Array.isArray(facts.engineMatches) ? facts.engineMatches : [];
            const mine = function (b) { return b[5] === true; };
            const arms = function (b) { return Array.isArray(b[6]) ? b[6] : []; };
            const pending = matches.filter(function (b) { return b[4] === 'pending'; });
            const others = matches.filter(function (b) { return b[4] !== 'pending'; });

            if (facts.gridStatus === 3 || pending.some(mine)) {
                return { cls: 'cell-pending', label: 'Your Pending' };
            }
            if (facts.gridStatus === 4 || pending.some(function (b) { return b[5] === false; })) {
                return { cls: 'cell-reserved', label: 'Other<br>Pending' };
            }

            const ownBlocked = others.some(mine);
            const cohortBlocked = others.some(function (b) { return arms(b).indexOf('cohort') !== -1; });

            if (ownBlocked && cohortBlocked) {
                return { cls: 'cell-occupied cell-occupied--mine', label: 'Your &amp; Cohorts<br>Conflict' };
            }
            if (ownBlocked) {
                return { cls: 'cell-occupied cell-occupied--mine', label: 'Your<br>Conflict' };
            }
            if (cohortBlocked) {
                return { cls: 'cell-occupied', label: 'Cohorts<br>Conflict' };
            }
            if (facts.gridStatus === 1) {
                return { cls: 'cell-occupied', label: 'Venue<br>Conflict' };
            }
            if (facts.engineBlocked) {
                /* a block with no owner and no arm (a session type the room does
                   not accept, or a stale 5/6-key tuple): keep the old word */
                return { cls: 'cell-occupied', label: 'Occupied' };
            }

            return null;   /* → too-soon / available, decided by the caller */
        }

        function classifyCell(facts) {
            const state = classifyState(facts);

            return state !== null ? state.cls : null;
        }

        /* the hover words, as HTML (the span allows a <br>; a 2-line label is the
           most a ~40 px cell can hold — user answer 4A: wrap + shrink, same words) */
        function cellStateLabel(facts) {
            const state = classifyState(facts);

            return state !== null ? state.label : '';
        }

        function buildTimetable() {
            clearPreview(); /* a rebuild invalidates any open hover preview */
            const days = getDays();

            /* F-9 (round-2): venue-parity accessible bookable cell — focusable,
               announced, keyboard-operable; keyboard focus gets the same block
               preview mouse users get on hover. */
            function setupAvailableCell(div, di, hi) {
                div.tabIndex = 0;
                div.setAttribute('role', 'button');
                div.setAttribute('aria-label', 'Available slot: ' + days[di].abbr + ' ' + hours[hi]);
                div.addEventListener('click', () => toggleCell(di, hi, div));
                div.addEventListener('mouseenter', () => previewBlock(di, hi, div));
                div.addEventListener('mouseleave', () => clearPreview());
                div.addEventListener('focus', () => previewBlock(di, hi, div));
                div.addEventListener('blur', () => clearPreview());
                div.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleCell(di, hi, div); }
                });
            }

            buildTimetableGrid({
                events: [],
                days: days,
                bookableBadge: true,
                cellRender: function(td, di, hi, day) {
                    const div = document.createElement('div');
                    div.className = 'cell-content';

                    const isSunday = day.abbr === 'Sun';
                    /* BRIDGE PATCH (data shape only): real slot states are
                       week-specific — the map is [weekIdx][venueCode]. */
                    const venueData = (venueSlotData[weekNav.currentWeek] || {})[currentVenue] || [];
                    const cellData = venueData.find(d => d[0] === di && d[1] === hi);

                    /* BRIDGE PATCH (the frozen SDD's D4 rule 1): cells the engine's
                       busy vectors exclude — this class's lecturer/cohorts are
                       already in class that half-hour (cancelled classes freed by
                       class_exceptions) — and venues ineligible for the session's
                       type/headcount (engine Vector 4) render occupied, exactly
                       like the cell-state table mandates. The mock never needed
                       it (its data had no real conflicts); without it the grid
                       offers cells the server's FR 4.3–4.7 pre-check then
                       refuses — the "free cell → conflict popup" trap. */
                    /* vectors are keyed per SESSION (occurrence-grain) — the
                       L/T/P occurrences of one module have different cohort
                       sets; fall back to the code key only for legacy data */
                    const courseKey = currentSessionId !== null
                        ? String(currentSessionId)
                        : (currentCourse ? currentCourse.code : '');
                    const busyRanges = ((MockData.busyByWeek || {})[courseKey] || {})[weekNav.currentWeek] || [];
                    const engineBlocked = ((MockData.blockedVenues || {})[courseKey] || []).includes(currentVenue)
                        || busyRanges.some(b => b[0] === di && hi >= b[1] && hi < b[2]);

                    /* TEMP-HOLDER-LABEL (user, 2026-10-27): the occupied cell's
                       holder identity — module(venue · time · lecturer · cohorts
                       — currently amputated at the label arms. REMOVE AFTER THE
                       FINAL VIVA (delete both tip attaches here; the payload's
                       4th keys stay harmless). */
                    /* SDD makeup-busy-vector round 2: the busy-vector cells
                       distinguish the PENDING make-up rows (the 5th tuple =
                       the status kind, null for the class-side rows). The
                       SAFE rank: any non-pending matcher wins `occupied`
                       (a pending + an approved overlap in the same half-hour
                       still renders amber→red correctly the other way);
                       all-pending = `cell-pending` (the tertiary-container
                       await family — the same-name-same-color canon). */
                    const engineMatches = busyRanges.filter(b => b[0] === di && hi >= b[1] && hi < b[2]);
                    /* SDD tidy-arrangement-cell-tooltips (2026-10-27, the
                       user-approved format): the wall-of-text tooltip becomes
                       a conflict-state header + ONE readable block per
                       blocking class, sorted by room code. The aria stays
                       ONE flat line (screen readers) with the existing
                       'Pending — '/'Occupied by ' prefix and the ' / '
                       block join. The TEMP-HOLDER-LABEL feature stays (the
                       viva directive — the format is what changes). */
                    /* SDD tidy-arrangement-cell-tooltips round 3 (the
                       user's add): EACH block carries a why-subtitle —
                       ['👤 Your conflict', '🏛 Venue conflict',
                         '👥 Cohorts conflict'] — the three independent
                       checks named (the makeup-busy-vector ruling: the
                       creator's own calendar / the viewed venue / the
                       shared cohorts). Client-side parse: the room line,
                       the 'You — ' ownership marker, the cohort-line
                       intersection with courseCtx()'s cohorts. */
                    const normCohort = (x) => (x || '').replace(/\s+/g, '');
                    const renderHolderTip = function (matches) {
                        const blocks = matches.map(b => b[3]).filter(Boolean);
                        if (!blocks.length) return null;
                        blocks.sort((a, b) => {
                            const roomOf = (x) => (x.split('\n')[0].split(' · ')[0] || '').trim();
                            return roomOf(a).localeCompare(roomOf(b), undefined, { numeric: true });
                        });
                        const allPending = matches.length > 0
                            && matches.every(b => b[4] === 'pending');
                        const header = allPending
                            ? '\u23F3 Pending make-up \u2014 awaiting approval'
                            : '\u26A0 Occupied \u2014 ' + blocks.length
                                + ' class' + (blocks.length > 1 ? 'es' : '') + ' in the way';
                        const ctx = (typeof courseCtx === 'function') ? courseCtx() : null;
                        const ctxCohorts = (ctx && Array.isArray(ctx.cohorts)
                            ? ctx.cohorts : []).map(normCohort);
                        const tagsFor = function (block) {
                            const lines = block.split('\n');
                            const room = (lines[0].split(' · ')[0] || '').trim();
                            const own = (lines[1] || '').indexOf('You — ') === 0;
                            const blockCohorts = (lines[2] || '').split(',').map(normCohort).filter(Boolean);
                            const tags = [];
                            /* 2026-11-07 (user): the cell label and the tag now agree
                               — "Your conflict", not "Own conflict". */
                            if (own) tags.push('\u{1F464} Your conflict');
                            if (room && String(room) === String(currentVenue)) tags.push('\u{1F3DB}\u{FE0F} Venue conflict');
                            if (ctxCohorts.length && blockCohorts.some(function (c) { return ctxCohorts.indexOf(c) !== -1; })) {
                                tags.push('\u{1F465} Cohorts conflict');
                            }
                            return tags.join(' · ');
                        };
                        const tagged = blocks.map(function (block) { return tagsFor(block) ? tagsFor(block) + '\n' + block : block; });
                        const readable = header + '\n\n' + tagged.join('\n\n');
                        const flat = (allPending ? 'Pending — ' : 'Occupied by ')
                            + tagged.map(b => b.replace(/\n/g, ' · ').replace(/\s{2,}/g, ' ')).join(' / ');
                        return { readable: readable, flat: flat };
                    };
                    /* 2026-11-07 (user): the cell's tip sits ABOVE the cell — the
                       shared engine's default placement (centred over the element,
                       flipping below only when there is no room). It used to be
                       pinned `left`, which put a multi-line block of text over the
                       neighbouring day columns. Omitting `data-tip-pos` is the
                       "above" mode; `left` stays the top-bar buttons' mode. */
                    const attachHolder = function () {
                        const rendered = renderHolderTip(engineMatches);
                        if (rendered) {
                            div.setAttribute('data-tip', rendered.readable);
                            div.setAttribute('aria-label', rendered.flat);
                            div.setAttribute('tabindex', '0');
                            div.style.cursor = 'help';
                        }
                    };

                    if (isSunday || day.holiday) {
                        div.className += day.holiday ? ' cell-ph' : ' cell-sun';
                    } else {
                        /* SDD arrangement-slot-ownership-legend (2026-11-06):
                           ONE classification per cell. Before this, the engine
                           arm and the grid arm each chose a class on their own
                           and the engine arm won by being checked first — so
                           another staff member's pending (which reaches the
                           cohort vector ownerless) painted the "Pending (You)"
                           amber. Ownership is now data (the tuple's 6th key /
                           the grid's status 3-vs-4) and one resolver decides. */
                        const gridStatus = cellData ? cellData[2] : null;
                        const cellFacts = {
                            gridStatus: gridStatus,
                            engineMatches: engineMatches,
                            engineBlocked: engineBlocked
                        };
                        const ownershipClass = classifyCell(cellFacts);

                        /* The tips keep today's wording and order. The engine
                           tip wins when it has a labelled block (the same
                           precedence as before); otherwise the grid library
                           speaks. The payload merges twin rows, so the header
                           count and the block list are unchanged. */
                        if (engineMatches.some(function (b) { return !!b[3]; })) {
                            attachHolder();
                        } else if (cellData) {
                            /* SDD occupied-cell-explanations round (the grid arm
                               rides the SAME renderer as the engine arm): the
                               payload's 4th key is an ARRAY of INDICES into the
                               deduped holder-text library (the payload gate's
                               dictionary fallback) — resolve each to its block
                               string, then build one pseudo-match per element in
                               the engine arm's exact tuple shape and call the
                               unchanged renderHolderTip (header count, why-tags,
                               flat aria all parse per BLOCK). statusKind governs
                               the TIP ONLY: grid 1 (occupied) → null; grid 3/4
                               (pending families) → 'pending'. */
                            const holderTexts = (typeof MockData !== 'undefined' && MockData.holderTexts) || window.PAGE_DATA.holderTexts || [];
                            const gridHolderMatches = Array.isArray(cellData[3]) && cellData[3].length
                                ? cellData[3].map(id => [di, hi, hi + 1, holderTexts[id] || '', cellData[2] === 1 ? null : 'pending'])
                                    .filter(m => m[3])
                                : null;
                            if (gridHolderMatches) {
                                const gridTip = renderHolderTip(gridHolderMatches);
                                if (gridTip) {
                                    div.setAttribute('data-tip', gridTip.readable);
                                    div.setAttribute('aria-label', gridTip.flat);
                                    div.setAttribute('tabindex', '0');
                                    div.style.cursor = 'help';
                                }
                            }
                        }

                        /* ═══ UI Update 2 (user, 2026-11-10, ruling C) ═══
                           The 3-working-day rule now OUTRANKS the ownership classes:
                           every date inside the window reads `cell-too-soon`, even
                           when a class sits in it — a replacement cannot be booked
                           there, so the red "conflict" paint was saying the wrong
                           thing. The reason is NOT thrown away (ruling C): it stays
                           in the same cell's hover tooltip, under the headline.

                           PRESERVED on purpose (the user's words): `cell-ph` for a
                           public holiday — that arm is above this block and never
                           reaches it — and `cell-pending`, which keeps its amber
                           state and its "Your Pending" label. */
                        const isTooSoon = isSlotTooSoon(weekData, weekNav.currentWeek, di);
                        const keepPending = !!ownershipClass && ownershipClass.indexOf('cell-pending') !== -1;

                        if (isTooSoon && !keepPending) {
                            div.className += ' cell-too-soon';

                            /* the hover words are a REAL element (a ~40 px cell must
                               hold two lines, and CSS `content:` cannot carry a
                               `<br>`) — the same mechanic the conflict labels use;
                               `:has()` in theme.css retires the old `::after`. */
                            const tooSoonTip = leadTimeTip();
                            const tooSoonLabel = document.createElement('span');
                            tooSoonLabel.className = 'cell-state-label cell-state-label--too-soon';
                            tooSoonLabel.innerHTML = tooSoonTip.label;
                            div.appendChild(tooSoonLabel);

                            /* ruling C: the blocking reason still rides the tooltip,
                               under the rule + the earliest bookable date */
                            const why = div.getAttribute('data-tip');
                            div.setAttribute('data-tip', why ? tooSoonTip.text + '\n\n' + why : tooSoonTip.text);
                            const whyFlat = div.getAttribute('aria-label');
                            div.setAttribute('aria-label', tooSoonTip.text.replace(/\n/g, ' ') + (whyFlat ? ' ' + whyFlat : ''));
                            div.setAttribute('tabindex', '0');
                            div.style.cursor = 'not-allowed';
                        } else if (ownershipClass) {
                            div.className += ' ' + ownershipClass;

                            /* SDD arrangement-cell-state-labels D4: the hover
                               words are a REAL element, so the two-line labels
                               work (`<br>`) and the CSS `::after` overlay is
                               retired exactly here (see theme.css). */
                            const stateLabel = document.createElement('span');
                            /* ⛔ PERMANENT (user 2026-11-10): the occupied cell's
                               hover label STAYS — the old "remove before the viva"
                               request is revoked (see theme.css's note). */
                            stateLabel.className = 'cell-state-label';
                            stateLabel.innerHTML = cellStateLabel(cellFacts);
                            div.appendChild(stateLabel);
                        } else {
                            div.className += ' cell-available';
                            setupAvailableCell(div, di, hi);
                        }
                    }

                    const timeLabel = document.createElement('span');
                    timeLabel.className = 'cell-time-label';
                    timeLabel.textContent = hours[hi] + '\n' + add30min(hours[hi]);
                    div.appendChild(timeLabel);

                    td.appendChild(div);
                }
            });

            loadCurrentWeek();
            updateCounter();
            /* the banner was retired 2026-11-10 — the message lives in the cell's
               tooltip now (leadTimeTip), so no renderLeadTimeNote() call here */
            weekNav._updateArrows();
            /* T05 settled point: cells, saved-week restore, counters and week
               arrows are all committed — one coalesced snapshot follows. */
            refreshArrangementMobile('timetable');
        }

        function timeLabelHtml(hi) {
            return '<span class="cell-time-label">' + hours[hi] + '\n' + add30min(hours[hi]) + '</span>';
        }

        function toggleCell(di, hi, el) {
            // Grid locked until a subject is picked: guide instead of selecting
            if (!currentCourse) {
                toast.show('Select a course first to trigger the timeslots selector');
                pulseSubjectSelector();
                return;
            }
            // Lead-time rule: too-soon cells are read-only
            if (el.classList.contains('cell-too-soon')) {
                toast.show('Slots must be at least 3 working days from today.');
                return;
            }
            // If clicking inside the current block, deselect it
            if (selectedBlock && di === selectedBlock.day && hi >= selectedBlock.startHour && hi < selectedBlock.endHour) {
                discardSelection();
                return;
            }
            // Otherwise try to place a new block starting at this cell
            if (el.classList.contains('cell-available')) {
                selectBlock(di, hi);
            }
        }

        /* Draw the eye to the subject dropdown (used when a locked grid cell is clicked) */
        function pulseSubjectSelector() {
            const sel = document.getElementById('subjectSelector');
            if (!sel) return;
            sel.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            sel.classList.remove('attention-pulse');
            void sel.offsetWidth; /* restart the animation */
            sel.classList.add('attention-pulse');
            setTimeout(function() { sel.classList.remove('attention-pulse'); }, 1300);
        }

        function clearSelection() {
            discardSelection();
        }

        function onVenueChange() {
            if (revertingChange) return;
            const newVenue = venueDropdown ? venueDropdown.getSelected() : 'B103';
            /* SDD arrangement-clear-guards-cross-week (BUG-08): the guard widens
               from the pointer to the leave-guard family — after a week hop the
               pointer IS null while the store holds the selection; the pointer-
               only test let the change through silently (the user's live report). */
            if ((selectedBlock || getGlobalTotal() > 0) && newVenue !== currentVenue) {
                showConfirmModal(
                    'Change <span class="warn-keyword">Venue</span>?',
                    'You have selected slots on the grid. Changing the <span class="warn-keyword">venue</span> will clear them.<br>Continue?',
                    function() {
                        hideConfirmModal();
                        /* 2026-10-09 user decision: confirming the clear is an
                           explicit discard — the booking pre-fill is SPENT
                           (markBookingCancelled), not re-armed, matching the
                           subject/slot confirm modals. The spend + the wipe
                           moved INTO discardAllSelections() (review R1 blocker
                           3: the pointer-only =isBooking capture at this site
                           returned false after a hop; the store-wide scan
                           supersedes it — the trailing if (=isBooking) pair went
                           with it). */
                        discardAllSelections();
                        applyVenueChange(newVenue);
                    },
                    { danger: true, confirmLabel: 'Yes, Change', cancelLabel: 'No, Keep My Selection' }
                );
                // Cancel path: snap the dropdown back to the current venue
                const cancelBtn = document.querySelector('#confirmModal .btn-outline');
                if (cancelBtn) {
                    cancelBtn.onclick = function(e) {
                        hideConfirmModal(e);
                        revertingChange = true;
                        venueDropdown.select(currentVenue);
                        revertingChange = false;
                    };
                }
                return;
            }
            applyVenueChange(newVenue);
        }

        function applyVenueChange(newVenue) {
            saveCurrentWeek();
            currentVenue = newVenue;
            buildTimetable();
            /* venue-side-chips staleness fix (the user's B005/B006 report):
               the capacity + description chips re-fill per venue switch —
               window.__fillVenueChips is defined in the boot tail (after
               the page's data hydration; the guest-state guard skips the
               boot before it exists). */
            if (window.__fillVenueChips) window.__fillVenueChips(newVenue);
        }

        let confirmCallback = null;

        function showAlertModal(title, bodyHtml, action) {
            /* T11: never stack the mobile info sheet under a critical modal. */
            arrangementMobile?.closeInfo?.();
            /* 2026-10-24 user UI directive: titles carry the warn-keyword spans
               (CAP + bold + color) — innerHTML so the markup renders */
            document.getElementById('modalTitle').innerHTML = title;
            document.getElementById('modalBody').innerHTML = bodyHtml;
            const cancelBtn = document.querySelector('.modal-footer .btn-outline');
            const confirmBtn = document.getElementById('modalConfirmBtn');
            const actionBtn = document.getElementById('modalActionBtn');
            /* optional bottom-right action (2026-10-09): e.g. "Clear Current
               Selection" — OK stays as the plain dismiss (demoted to outline
               so the action reads as the primary choice) */
            if (action) {
                actionBtn.style.display = '';
                actionBtn.textContent = action.label;
                actionBtn.onclick = function() { hideConfirmModal(); action.fn(); };
                confirmBtn.className = 'btn btn-outline';
            } else {
                actionBtn.style.display = 'none';
            }
            cancelBtn.style.display = 'none';
            confirmBtn.textContent = 'Close';
            confirmBtn.className = action ? 'btn btn-outline' : 'btn btn-primary';
            confirmBtn.onclick = function() {
                cancelBtn.style.display = '';
                confirmBtn.textContent = 'Confirm';
                confirmBtn.className = 'btn btn-primary';
                hideConfirmModal();
            };
            document.getElementById('confirmModal').style.display = 'flex';
        }

        function showConfirmModal(title, bodyHtml, callback, opts) {
            opts = opts || {};
            /* T11: the existing confirmation/cancel path stays authoritative;
               the info sheet just gets out of the way first. */
            arrangementMobile?.closeInfo?.();
            document.getElementById('modalTitle').innerHTML = title;
            document.getElementById('modalBody').innerHTML = bodyHtml;
            confirmCallback = callback;
            const confirmBtn = document.getElementById('modalConfirmBtn');
            /* submission-conflict-modal-refresh (the user's spec item 5): the
               error face is opt-in per call site — the conflict modal passes
               errorStyle:true; every other call site stays untouched. Same
               re-derive + reset pattern as the danger class. */
            document.querySelector('#confirmModal .modal').classList.toggle('error-modal', !!opts.errorStyle);
            const dangerPattern = !!opts.danger;
            /* Danger is opt-in per call site (design §2): the 5 warning guards
               pass danger:true; submit-confirm + informational modals keep
               btn-primary. Re-derived on EVERY open — no stale class leaks. */
            document.querySelector('#confirmModal .modal').classList.toggle('error-modal', !!opts.errorStyle);
            confirmBtn.classList.toggle('btn-danger', !!opts.danger);
            confirmBtn.classList.toggle('btn-primary', !opts.danger);
            confirmBtn.textContent = opts.confirmLabel || 'Confirm';
            /* Safe-side label: warnings read "Stay"/"No, …" (a choice, not a
                popup dismissal); informational modals keep "Close". */
            const cancelBtn0 = document.querySelector('#confirmModal .btn-outline');
            if (cancelBtn0) cancelBtn0.textContent = opts.cancelLabel || 'Close';
            confirmBtn.onclick = function() {
                if (confirmCallback) confirmCallback();
                else hideConfirmModal();
            };
            document.getElementById('confirmModal').style.display = 'flex';
        }

        function hideConfirmModal(e) {
            if (e) e.stopPropagation();
            document.getElementById('confirmModal').style.display = 'none';
            confirmCallback = null;
            // Reset Cancel button to its default behavior after any custom handler
            const cancelBtn = document.querySelector('#confirmModal .btn-outline');
            if (cancelBtn) cancelBtn.onclick = function(ev) { hideConfirmModal(ev); };
            // Reset the optional action button + OK label/weight for the next open
            const actionBtn = document.getElementById('modalActionBtn');
            if (actionBtn) actionBtn.style.display = 'none';
            // submission-conflict-modal-refresh T2: the error face resets with
            // the same breath as the btn-danger class — no stale red face
            // leaks to the next open.
            document.querySelector('#confirmModal .modal').classList.remove('error-modal');
            const confirmBtn = document.getElementById('modalConfirmBtn');
            confirmBtn.textContent = 'Confirm';
            confirmBtn.className = 'btn btn-primary';
        }

        function confirmChangeWithSelection(actionLabel, proceedFn, cancelFn) {
            /* SDD arrangement-clear-guards-cross-week (review R1 blocker 1):
               the early-out widens with the SAME formula the guards widened
               with — post-hop selectedBlock IS null while the store holds the
               selection; the pointer-only early-out reeled through silently
               and the subject/slot modals NEVER opened. */
            if (!selectedBlock && getGlobalTotal() === 0) { proceedFn(); return; }
            /* 2026-10-24 user decision: the title NAMES the trigger (the "Change
               Venue?" family), not the shared "Clear Current Selection?" —
               keywords CAP + bold + colored via warn-keyword */
            const titleCaseLabel = actionLabel.replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            showConfirmModal(
                'Change <span class="warn-keyword">' + titleCaseLabel + '</span>?',
                'You have selected slots on the grid. Changing the <span class="warn-keyword">' + actionLabel + '</span> will remove them.<br>Continue?',
                function() {
                    hideConfirmModal();
                    /* 2026-10-09 user decision: the modal promises "will remove
                       them" — confirming IS an explicit discard. The intent
                       spend + the wipe moved INTO discardAllSelections()
                       (review R1 blocker 3: the pointer-only capture at this
                       site returned false after a hop — the pre-fill would
                       resurrect on the next reload against the explicit
                       discard; the store-wide scan supersedes it). */
                    discardAllSelections();
                    proceedFn();
                },
                { danger: true, confirmLabel: 'Yes, Change', cancelLabel: 'No, Keep My Selection' }
            );
            const cancelBtn = document.querySelector('#confirmModal .btn-outline');
            if (cancelBtn) {
                cancelBtn.onclick = function(e) {
                    hideConfirmModal(e);
                    if (cancelFn) cancelFn();
                };
            }
        }


        function proceed() {
            /* T18: a mobile-origin request is already in flight. The latch is
               page-owned (it survives adapter resize/destroy), so no second
               confirmation/duplicate send can start. Desktop-origin requests
               never set it, so that path is unchanged. */
            if (mobileSubmissionInFlight) return;
            /* T16: mobile rechecks the live calendar before the confirmation
               opens. T17: then captures the presented identity; already-expired
               dates or a retention marker refuse the confirmation outright.
               Desktop keeps its existing path unchanged (no captured context). */
            let mobileSubmission = null;
            if (arrangementMobile && typeof arrangementMobile.isMobile === 'function'
                && arrangementMobile.isMobile()) {
                reconcileMobileClock();
                mobileSubmission = captureMobileSubmissionIdentity();
                if (mobileSubmission.retention.count > 0 || !mobileSavedDatesStillValid()) {
                    showMobileSubmissionReview(mobileSubmission.retention.count > 0 ? 'retention' : 'expired');
                    return;
                }
            }
            if (getGlobalTotal() === 0) {
                showConfirmModal('No Selection', 'Please select at least one timeslot before proceeding.', null);
                return;
            }
            /* multi-week model: summarize EVERY saved selection, not just the week on screen */
            const shortDayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const rows = [];
            for (const venue in selectedSlotsByVenue) {
                for (const week in selectedSlotsByVenue[venue]) {
                    const block = selectedSlotsByVenue[venue][week];
                    if (!block) continue;
                    const dayData = weekData[week].days[block.day];
                    const startStr = hours[block.startHour];
                    const endStr = add30min(hours[block.endHour - 1]);
                    const slotCount = block.endHour - block.startHour;
                    rows.push(`<div style="padding:3px 0;font-size:13px;">${venue} · (${weekData[week].label}) ${shortDayNames[block.day]}, ${dayData.date}, ${to12h(startStr)} ~ ${to12h(endStr)} · ${slotCount} slots</div>`);
                }
            }
            const listHtml = rows.join('');
            showConfirmModal(
                'Confirm Your Selection',
                `<div style="margin-bottom:12px;font-weight:500;">You are about to submit a replacement request for the following ${rows.length > 1 ? rows.length + ' blocks' : 'block'}:</div>
                 <div style="border:1px solid var(--color-outline);border-radius:var(--radius-sm);padding:10px 14px;max-height:200px;overflow-y:auto;">${listHtml}</div>`,
                function() {
                    hideConfirmModal();
                    /* BRIDGE PATCH: the request really goes to the backend —
                       each saved block becomes one pending request (FR 2.9)
                       through the D8 rules + SubmitReplacementRequest (engine
                       pre-check, OCC reserve, D5). All-ok runs the mock's
                       verbatim success path below; any FR 4.10 conflict is
                       reported in the mock's own modal vocabulary and the
                       selection is KEPT for re-deciding. */
                    const blocks = [];
                    for (const venue in selectedSlotsByVenue) {
                        for (const week in selectedSlotsByVenue[venue]) {
                            const block = selectedSlotsByVenue[venue][week];
                            if (block) blocks.push({ venue: venue, week: parseInt(week, 10), day: block.day, startHour: block.startHour, endHour: block.endHour });
                        }
                    }
                    /* occurrence-week-pin-truthfulness F3: the client gate —
                       the pinned occurrence is MANDATORY (the server's
                       no_occurrence_pin loser mirrors it); the picker-first
                       message keeps the selection, no submit (the pick + the
                       Replace-Now URL hydration remain the two pin paths). */
                    if (!window.pinnedOccurrence) {
                        alert('Pick the slot on the grid first — the week being replaced must be the conflicted occurrence\'s own.\n\nClick a time cell to pin it, or use the Replace Now button on the class\'s conflicted day.');
                        return;
                    }
                    const orig = selectedOriginalSlot
                        ? { day: selectedOriginalSlot.day, start: selectedOriginalSlot.start, venue: selectedOriginalSlot.venue }
                        : { day: urlParams.day, start: urlParams.start, venue: urlParams.originalVenue };
                    /* T17: the mobile-origin identity is revalidated at the Yes
                       boundary, after the pin/orig preparation above. The
                       disabled Submit flag is NOT proof (it settles late). On
                       expiry/retention/change: no network call, saved data and
                       markers untouched, persistent review guidance instead. */
                    if (mobileSubmission) {
                        reconcileMobileClock();
                        if (!mobileSubmissionStillValid(mobileSubmission)) {
                            let cause = 'identity';
                            if (mobileHasRetention()) cause = 'retention';
                            else if (!mobileSavedDatesStillValid()) cause = 'expired';
                            hideConfirmModal();
                            showMobileSubmissionReview(cause);
                            return;
                        }
                        /* T18: latch synchronously AFTER the T17 validation and
                           immediately BEFORE the ONE call. A duplicate mobile Yes
                           cannot send again. Desktop never reaches this block. */
                        if (mobileSubmissionInFlight) return;
                        mobileSubmissionInFlight = true;
                        setMobileSubmissionBusy(true);
                    }
                    let submitRequest = null;
                    try {
                        submitRequest = pageLivewire().call('submitBlocks', JSON.stringify(blocks), currentCourse ? currentCourse.code : '', JSON.stringify(orig), JSON.stringify(window.pinnedOccurrence || {}));
                    } catch (submitError) {
                        /* Only the mobile-origin flow has a latch/uncertain state
                           to recover; desktop keeps its existing synchronous throw. */
                        if (!mobileSubmission) throw submitError;
                        releaseMobileSubmissionLatch();
                        showMobileSubmissionUnknown();
                        return;
                    }
                    /* A non-promise result cannot confirm any outcome. */
                    if (mobileSubmission && (!submitRequest || typeof submitRequest.then !== 'function')) {
                        releaseMobileSubmissionLatch();
                        showMobileSubmissionUnknown();
                        return;
                    }
                    const submitSettled = submitRequest
                        .then(function(results) {
                            const failed = [];
                            (results || []).forEach(function(r, i) {
                                if (r && !r.ok) {
                                    const b = blocks[i];
                                    let label = 'A selection';
                                    if (b) {
                                        const dayData = weekData[b.week] ? weekData[b.week].days[b.day] : null;
                                        label = b.venue + ' · ' + (dayData ? dayData.abbr + ', ' + dayData.date : '')
                                            + ' · ' + to12h(hours[b.startHour]) + ' ~ ' + to12h(add30min(hours[b.endHour - 1]));
                                    }
                                    const reason = r.reason || 'booking conflict';
                                    /* 2026-11-05 UI/UX update (user): the
                                       duplicate-occurrence conflict reads as TWO
                                       labelled lines — `Conflicted Slot:` with the
                                       week/day/date/time/venue, then `This
                                       Occurrence:` with the fact. The old
                                       "— see My Replacements" tail is gone. Every
                                       other conflict type keeps its row. */
                                    if (reason.indexOf('already has an active replacement request') >= 0) {
                                        const wk = b ? (weekData[b.week] || null) : null;
                                        const dd = (wk && b) ? wk.days[b.day] : null;
                                        const slotText = b
                                            ? (wk ? wk.label : 'Week ' + (b.week + 1)) + ', '
                                                + (dd ? dd.abbr + ', ' + dd.date : '') + ' \u00b7 '
                                                + to12h(hours[b.startHour]) + ' ~ '
                                                + to12h(add30min(hours[b.endHour - 1]))
                                                + ' @' + b.venue
                                            : '\u2014';
                                        failed.push('<div style="padding:3px 0;font-size:13px;">'
                                            + '<div><span style="font-weight:600;color:var(--color-on-surface);">Conflicted Slot</span>: ' + slotText + '</div>'
                                            + '<div style="margin-top:2px;"><span style="font-weight:600;color:var(--color-on-surface);">This Occurrence</span>: Already Has An Active Replacement Request.</div>'
                                            + '</div>');
                                        return;
                                    }
                                    /* submission-conflict-modal-refresh T3 (the user's
                                       spec items 2/4/6): the row layout splits by
                                       TYPE — Type B = the reason carries the literal
                                       'runs into an existing booking' (both the
                                       slot_not_available occupied variant and the
                                       span_overlap 'Your block' variant + the holder
                                       paren); the FIRST at HH:MM–HH:MM match = the
                                       failing/overlap range (the holder paren is NOT
                                       surfaced — the review pin); no-range fallback =
                                       the RAW reason as a single line. Every other
                                       reason row stays BYTE-verbatim. */
                                    const typeB = reason.indexOf('runs into an existing booking') >= 0;
                                    const m = typeB ? reason.match(/at (\d{2}:\d{2})\u2013(\d{2}:\d{2})/) : null;
                                    if (typeB && m) {
                                        failed.push('<div style="padding:3px 0;font-size:13px;">'
                                            + '<div style="font-weight:600;color:var(--color-on-surface);">' + label + '</div>'
                                            + '<div>' + 'The block overlaps with an '
                                            + '<span class="warn-keyword">EXISTING OCCUPIED BOOKING</span>'
                                            + ' at ' + to12h(m[1]) + ' ~ ' + to12h(m[2]) + '</div>'
                                            + '</div>');
                                    } else {
                                        /* the raw reason verbatim (Type A, holiday,
                                           duplicate_active_request, …) — no rewording */
                                        failed.push('<div style="padding:3px 0;font-size:13px;">' + label + ' \u2014 ' + reason + '</div>');
                                    }
                                }
                            });
                            if (failed.length === 0) {
                                /* S13b — the submitted request consumes this subject's
                                   cancellation ledger entry: the entry is RETAINED (the
                                   cancelled state keeps replaying) but its chip + the
                                   load toast stop and undo is no longer offered. */
                                if (currentCourse && typeof ClassCancellation !== 'undefined') {
                                    ClassCancellation.markConsumedByCode(currentCourse.code);
                                }
                                // Save as recent slot
                                if (selectedOriginalSlot) {
                                    setRecentSlot(selectedOriginalSlot);
                                }
                                /* 2026-11-05 UI/UX update (user): the success
                                   toast is GONE — the new row on My Replacements
                                   is the receipt, flashed with the shared
                                   three-pulse highlight (the Today /
                                   Earliest-bookable language). No 1.2 s beat:
                                   the redirect is immediate. The selection
                                   clean-up below is unchanged. */
                                deselectBlock();
                                Object.keys(selectedSlotsByVenue).forEach(k => { selectedSlotsByVenue[k] = {}; });
                                clearAllRetained();   /* T15: a successful submission retires every retention marker */
                                /* the request is sent — Ctrl+Z must not resurrect its slots */
                                selectionHistory.length = 0;
                                updateCounter();
                                const newIds = (results || [])
                                    .filter(function(r) { return r && r.ok && r.request_id; })
                                    .map(function(r) { return r.request_id; });
                                window.location.href = '/my-replacements'
                                    + (newIds.length ? '?new-req=' + newIds.join(',') : '');
                            } else {
                                /* T18: a business rejection IS a confirmed
                                   outcome — release the latch before the existing
                                   conflict modal; selection/store stay kept. */
                                if (mobileSubmission) releaseMobileSubmissionLatch();
                                /* submission-conflict-modal-refresh (the user's
                                   spec items 1/3/4/5/6): the conflict modal = the
                                   error-look shell (errorStyle → the red title +
                                   hairline + rows tint), the CONFLICT keyword, the
                                   NOTE on BOTH types, the green REFRESH/PICK footer,
                                   and the 'Refresh Page' button = location.reload()
                                   ONLY — the URL (session/week/from/code/cohort/
                                   venue/date/time + the notif params) rides along;
                                   NO auto-resubmit. */
                                showConfirmModal(
                                    'Submission <span class="warn-keyword">CONFLICT</span>',
                                    'Some selections could not be <span class="warn-keyword">BOOKED</span>:<br>'
                                        + '<div class="conflict-rows" style="border:1px solid var(--color-outline);border-radius:var(--radius-sm);padding:10px 14px;max-height:200px;overflow-y:auto;margin-top:8px;background:var(--color-error-container);">'
                                        + failed.join('')
                                        + '</div>'
                                        + '<div style="margin-top:10px;font-size:13px;"><span class="ge-keyword">NOTE</span>: Another Staff Member May Have Booked This Slot Before Your Submission.</div>'
                                        + '<div style="margin-top:4px;font-size:13px;">Please <span class="go-keyword">REFRESH</span> this page and <span class="go-keyword">PICK</span> another available slot.</div>',
                                    function() {
                                        hideConfirmModal();
                                        /* submission-conflict-refresh (the user's
                                           2026-10-30 ask): the conflict state KEEPS
                                           the selection ("kept for re-deciding"),
                                           so the beforeunload guard would pop the
                                           browser's leave-confirmation on this
                                           reload. The allowUnload bypass — the
                                           exact pattern navigateTo/goBack already
                                           use (:2321/:2342) — scoped to THIS
                                           callback only; the guard re-arms on the
                                           fresh page (a page-lifetime flag resets
                                           with the reload), so every other leave
                                           path keeps its protection. */
                                        allowUnload = true;
                                        location.reload();   /* the params ride along — never resubmit */
                                    },
                                    { danger: true, confirmLabel: 'Refresh Page', cancelLabel: 'Close', errorStyle: true }
                                );
                            }
                        });
                    if (mobileSubmission) {
                        submitSettled.catch(function() {
                            /* UNCONFIRMED transport/JS outcome: release the latch,
                               keep selection/store/retention markers, and say the
                               result is unknown. No auto-retry, no false failure.
                               If the latch is already released the outcome was
                               already reported (confirmed rejection), so do not
                               stack a second modal over it. */
                            if (!mobileSubmissionInFlight) return;
                            releaseMobileSubmissionLatch();
                            showMobileSubmissionUnknown();
                        });
                    }
                },
                { confirmLabel: 'Yes, Submit Request' }
            );
        }

        function clearAll() {
            showConfirmModal(
                'Clear All Selections',
                'Are you sure you want to clear all selections across <span class="warn-keyword">all</span> weeks?<br>You can undo this from the toast that appears.',
                function() {
                    hideConfirmModal();
                    var savedBlock = selectedBlock ? { ...selectedBlock } : null;
                    var savedSlots = JSON.parse(JSON.stringify(selectedSlotsByVenue));
                    var savedVenue = currentVenue.code || currentVenue;
                    var savedWeek = weekNav.currentWeek;
                    Object.keys(selectedSlotsByVenue).forEach(k => { selectedSlotsByVenue[k] = {}; });
                    deselectBlock();
                    updateCounter();
                    updateSelectionSummary();   // strip must drop to '0 of N' immediately (same staleness as deselectBlock)
                    toast.show('All selections cleared.', function() {
                        Object.keys(savedSlots).forEach(k => { selectedSlotsByVenue[k] = savedSlots[k]; });
                        /* only re-render when the view hasn't moved since the clear —
                           restoring a foreign week's block onto the current grid
                           would bake it into the wrong week on the next navigation */
                        if (savedBlock && savedVenue === (currentVenue.code || currentVenue) && savedWeek === weekNav.currentWeek) {
                            selectedBlock = savedBlock;
                            const body = document.getElementById('tableBody');
                            renderMergedBlock(body);
                        } else {
                            selectedBlock = null;
                        }
                        updateCounter();
                    });
                },
                { danger: true, confirmLabel: 'Yes, Clear All', cancelLabel: 'No, Clear Nothing' }
            );
        }

        function navigateTo(url) {
            if (selectedBlock || getGlobalTotal() > 0) {
                showConfirmModal(
                    'Unsaved Changes',
                    'You have selections that will be <span class="warn-keyword">lost</span> if you leave this page.<br>Are you sure you want to leave?',
                    function() {
                        /* Confirmed leave → navigate at once; selections are in-memory
                           and die with the page (no clearing ceremony / undo toast).
                           allowUnload bypasses the beforeunload guard (no double prompt). */
                        hideConfirmModal();
                        allowUnload = true;
                        window.location.href = url;
                    },
                    { danger: true, confirmLabel: 'Yes, Leave Page', cancelLabel: 'Stay' }
                );
            } else {
                window.location.href = url;
            }
        }

        function goBack() {
            if (selectedBlock || getGlobalTotal() > 0) {
                showConfirmModal(
                    'Unsaved Changes',
                    'You have selections that will be <span class="warn-keyword">lost</span> if you leave this page.<br>Are you sure you want to go back?',
                    function() {
                        /* Confirmed leave → navigate at once; selections are in-memory
                           and die with the page (no clearing ceremony / 5s undo wait).
                           allowUnload bypasses the beforeunload guard (no double prompt). */
                        hideConfirmModal();
                        allowUnload = true;
                        BackNavigator.navigate();
                    },
                    { danger: true, confirmLabel: 'Yes, Go Back', cancelLabel: 'Stay' }
                );
            } else {
                BackNavigator.navigate();
            }
        }

        /* Browser-level guard for the paths the in-page modal can't reach:
           refresh, close, address-bar navigation and the browser's own
           back/forward arrows. Browser controls the dialog text ("Changes
           you made may not be saved") — we only decide whether it appears. */
        let allowUnload = false;
        window.addEventListener('beforeunload', function(e) {
            if (!allowUnload && (selectedBlock || getGlobalTotal() > 0)) {
                e.preventDefault();
                e.returnValue = '';
                return '';
            }
        });

        /* bfcache restore (browser back/forward): Chrome resurrects the frozen page
           exactly as left — selections, undo history AND the allowUnload bypass all
           survive. The user already confirmed leaving ("selections will be lost"),
           so re-arm the guard and come back to a clean slate. */
        /* Chrome re-fills form controls on back/forward reloads: the subject dropdown
           can DISPLAY a value the page's JS never applied (currentCourse still null),
           leaving the grid locked under a filled selector. Reconcile so what is shown
           is what is applied. Runs on every load — restoration precedes pageshow. */
        function reconcileSubjectState() {
            const sel = document.getElementById('subjectSelector');
            if (sel && sel.value && !currentCourse) onSubjectChange();
        }

        /* ENTRY GUARD, layer B — the bfcache revalidation (the server's
           revalidateEntry re-runs the unproposed+own check).
           URL-VS-PIN DOCTRINE (2026-11-01, lane
           conflict-subject-switch-autoselect): the URL CAN LAG the live pin —
           a POST-boot subject switch moves window.pinnedOccurrence (the pair
           the submit honours) while the URL keeps the ARRIVAL pair. So BOTH
           ride: the PIN first (the occurrence the user is ACTUALLY arranging),
           the URL pair second (anchor-less states). A URL-only key would miss a
           pending request on the pinned occurrence and re-open this bug. */
        function revalidateEntryGuard() {
            /* A bfcache restore resumes Livewire ASYNCHRONOUSLY — the component
               may not be callable on the pageshow tick. Retry briefly instead of
               degrading silently (the guard must not miss the very case it
               exists for). */
            const attempt = function(tries) {
                try {
                    const call = window.pageLivewire ? window.pageLivewire() : null;
                    if (!call || typeof call.call !== 'function') {
                        if (tries > 0) setTimeout(function() { attempt(tries - 1); }, 250);
                        return;
                    }
                    const params = new URLSearchParams(window.location.search);
                    const pin = window.pinnedOccurrence || null;
                    const pinSession = pin && pin.sessionId ? Number(pin.sessionId) : null;
                    const pinWeek = pin && pin.week ? Number(pin.week) : null;
                    const urlSession = params.get('session') ? Number(params.get('session')) : null;
                    const urlWeek = params.get('week') ? Number(params.get('week')) : null;
                    call.call('revalidateEntry', pinSession, pinWeek, urlSession, urlWeek, params.get('from') || null)
                        .then(function(landing) {
                            /* the server returns the landing URL when the occurrence
                               is already handled — navigate HERE (plain location
                               change) so a partially-resumed Livewire can't
                               swallow it */
                            if (landing) window.location.href = landing;
                            else if (tries > 0) setTimeout(function() { attempt(tries - 1); }, 750);
                        })
                        .catch(function() {
                            if (tries > 0) setTimeout(function() { attempt(tries - 1); }, 500);
                        });
                } catch (err) { /* degrade — never break the restored page over the guard */ }
            };
            attempt(4);
        }

        window.addEventListener('pageshow', function(e) {
            reconcileSubjectState();
            if (!e.persisted) {
                /* belt & braces: form-restoration timing varies — re-check shortly */
                setTimeout(reconcileSubjectState, 120);
                return; /* normal load — nothing else to do */
            }
            /* ENTRY GUARD, layer B (bfcache) — an already-open request may have
               been submitted FROM this very page before the user pressed Back;
               the restored snapshot would still show the Conflict Schedule
               panel as if nothing happened. Re-validate server-side (the
               blocked case redirects out with ?blocked=; the honest case is a
               silent no-op so the bfcache comfort survives). */
            revalidateEntryGuard();
            allowUnload = false;
            Object.keys(selectedSlotsByVenue).forEach(k => { selectedSlotsByVenue[k] = {}; });
            clearAllRetained();     /* T15: the bfcache wipe leaves no marker behind */
            deselectBlock();        /* removes merged block + restores green cells */
            selectionHistory = [];  /* cleared after deselectBlock (it pushes an entry) — stale Ctrl+Z can't resurrect */
            updateCounter();        /* re-sync counter/summary/footer (also covers the no-block case) */
        });

        function navigateHome() {
            /* R-3 (round-3): same role-home rule as the shell — was hardcoded '/' */
            navigateTo(window.PAGE_HOME || '/');
        }

        function updateSelectionProgress() {
            const count = getGlobalTotal();
            const max = MAX_SELECTION;
            const pct = max > 0 ? (count / max) * 100 : 0;
            const fill = document.getElementById('progressFill');
            const text = document.getElementById('progressText');
            if (fill) {
                fill.style.width = pct + '%';
                if (count === 0) fill.style.background = 'var(--color-outline)';
                else if (count < max) fill.style.background = 'var(--color-tertiary)';
                else fill.style.background = 'var(--color-primary)';
            }
            if (text) text.textContent = 'Selected ' + count + ' of ' + max + ' slots';
        }

        function pushHistory(entry) {
            selectionHistory.push(entry);
        }

        function undoSelection() {
            if (selectionHistory.length === 0) return;
            const last = selectionHistory.pop();
            const body = document.getElementById('tableBody');
            const venueNow = currentVenue.code || currentVenue;

            if (last.action === 'select') {
                // Undo a block selection → deselect it, but only if the view is
                // still where the selection happened — otherwise the wrong
                // week's/venue's block would be removed
                if (selectedBlock && last.venue === venueNow && last.week === weekNav.currentWeek
                    && selectedBlock.day === last.block.day && selectedBlock.startHour === last.block.startHour) {
                    deselectBlock();
                }
            } else if (last.action === 'deselect') {
                // Undo a block deselect → re-select it into its ORIGINAL
                // week/venue; re-render only when the view still matches —
                // restoring a foreign week's block onto the current grid would
                // bake it into the wrong week on the next navigation
                if (!selectedBlock) {
                    /* lead-time rule: a slot that has slipped inside the
                       3-working-day window can no longer be resurrected */
                    if (isSlotTooSoon(weekData, last.week, last.block.day)) {
                        toast.show('That slot is now within 3 working days of today — it can no longer be selected.');
                        return;
                    }
                    if (!selectedSlotsByVenue[last.venue]) selectedSlotsByVenue[last.venue] = {};
                    selectedSlotsByVenue[last.venue][last.week] = { ...last.block };
                    if (last.venue === venueNow && last.week === weekNav.currentWeek) {
                        selectedBlock = { ...last.block };
                        renderMergedBlock(body);
                    }
                }
            }
            updateCounter();
            toast.show('Selection undone');
        }

        function focusCell(day, hour) {
            unfocusCell();
            const body = document.getElementById('tableBody');
            const td = body.querySelector('td[data-day="' + day + '"][data-hour="' + hour + '"]');
            if (td) {
                const cellDiv = td.querySelector('.cell-content');
                if (cellDiv) cellDiv.classList.add('cell-focused');
                /* keep the ring visible while arrowing through a tall grid */
                td.scrollIntoView({ block: 'nearest' });
            }
            focusedCell = { day: day, hour: hour };
        }

        function unfocusCell() {
            if (focusedCell.day !== null && focusedCell.hour !== null) {
                const body = document.getElementById('tableBody');
                const td = body.querySelector('td[data-day="' + focusedCell.day + '"][data-hour="' + focusedCell.hour + '"]');
                if (td) {
                    const cellDiv = td.querySelector('.cell-content');
                    if (cellDiv) cellDiv.classList.remove('cell-focused');
                }
            }
            focusedCell = { day: null, hour: null };
        }

        function showHelp() {
            document.getElementById('helpOverlay').classList.add('active');
        }

        function hideHelp() {
            document.getElementById('helpOverlay').classList.remove('active');
        }

        function handleKeyDown(e) {
            const modal = document.getElementById('confirmModal');
            if (modal && modal.style.display === 'flex') {
                if (e.key === 'Escape') hideConfirmModal(e);
                return;
            }
            const help = document.getElementById('helpOverlay');
            if (help && help.classList.contains('active')) {
                if (e.key === 'Escape') hideHelp();
                return;
            }

            /* T13: the one mobile information sheet owns the keyboard. No
               hidden-grid command may run behind it, and native dialog keys
               (Escape included) are deliberately not prevented here. */
            if (arrangementMobile?.isInfoOpen?.()) return;

            /* let form controls keep their native keys — the grid must not
               hijack arrows/space/enter from the subject or week selects */
            const tag = ((e.target && e.target.tagName) || '').toLowerCase();
            if (tag === 'select' || tag === 'input' || tag === 'textarea') {
                if (e.key === 'Escape') e.target.blur();
                return;
            }

            /* T13: mobile controls are real buttons/selects. Every legacy
               grid/undo/help/week/venue shortcut below belongs to the hidden
               desktop table; returning without preventDefault keeps native
               button/select activation working. Critical-modal Escape above
               still runs first on both layouts. */
            if (arrangementMobile?.isMobile?.()) return;

            const days = getDays();
            const maxDay = days.length - 1;
            const maxHour = hours.length - 1;

            switch (e.key) {
                case 'ArrowUp':
                    e.preventDefault();
                    if (focusedCell.day === null) focusCell(0, 0);
                    else if (focusedCell.day > 0) focusCell(focusedCell.day - 1, focusedCell.hour);
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    if (focusedCell.day === null) focusCell(0, 0);
                    else if (focusedCell.day < maxDay) focusCell(focusedCell.day + 1, focusedCell.hour);
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    if (focusedCell.day === null) focusCell(0, 0);
                    else if (focusedCell.hour > 0) focusCell(focusedCell.day, focusedCell.hour - 1);
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    if (focusedCell.day === null) focusCell(0, 0);
                    else if (focusedCell.hour < maxHour) focusCell(focusedCell.day, focusedCell.hour + 1);
                    break;
                case 'Enter':
                case ' ':
                    e.preventDefault();
                    if (focusedCell.day !== null) {
                        const body = document.getElementById('tableBody');
                        const td = body.querySelector('td[data-day="' + focusedCell.day + '"][data-hour="' + focusedCell.hour + '"]');
                        if (td) {
                            const cellDiv = td.querySelector('.cell-content');
                            if (cellDiv) toggleCell(focusedCell.day, focusedCell.hour, cellDiv);
                        }
                    }
                    break;
                case 'Escape':
                    unfocusCell();
                    break;
                case '[':
                    e.preventDefault();
                    weekNav.prevWeek();
                    break;
                case ']':
                    e.preventDefault();
                    weekNav.nextWeek();
                    break;
                case '?':
                    showHelp();
                    break;
                case 'z':
                case 'Z':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        undoSelection();
                    }
                    break;
                case '1':
                case '2':
                case '3':
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        /* switch to the Nth venue of the (filtered) dropdown list */
                        if (venueDropdown && typeof venueDropdown.getFiltered === 'function') {
                            const list = venueDropdown.getFiltered();
                            const v = list[parseInt(e.key, 10) - 1];
                            if (v) venueDropdown.select(v.code);
                        }
                    }
                    break;
            }
        }

        /* T13: ui-common.js registers its own `[`/`]` week listener that knows
           nothing about mobile mode or the information sheet. Intercept ONLY
           those two keys in the capture phase while either state is active, so
           the shared listener cannot navigate the hidden grid; every native
           key (Escape included) still reaches its real owner. Registered once
           at boot — resize never adds another listener. */
        function blockSharedWeekShortcuts(e) {
            if (!e || (e.key !== '[' && e.key !== ']')) return;
            const infoOpen = arrangementMobile?.isInfoOpen?.() === true;
            const mobile = arrangementMobile?.isMobile?.() === true;
            if (!infoOpen && !mobile) return;
            e.preventDefault();
            e.stopImmediatePropagation();
        }

        // ───── Subject Dropdown + URL Param Reading ─────

        let currentCourse = null;
        /* Occurrence-grain context (2026-10-12, explore A2/A3): one module code
           can hold L+T+P sessions — the PICKED slot's session decides type,
           cohorts, headcount and venue eligibility. null = module default. */
        let currentSessionId = null;
        function courseCtx() {
            if (!currentCourse) return null;
            const sess = currentSessionId !== null && currentCourse.sessions
                ? currentCourse.sessions[String(currentSessionId)]
                : null;
            return sess ? Object.assign({}, currentCourse, sess) : currentCourse;
        }
        let urlParams = {};

        /* arrange-intent-session-choice (user report 2026-10-16 follow-up): the
           ?session= handoff no longer runs the legacy occurrence pick (it fought
           the booking-intent auto-select with the confirm modal), so it anchors
           WITHOUT selection: the pinned occurrence's own facts act as the
           anchor for the summary + trigger, while submitBlocks falls back to
           urlParams for the original and pinnedOccurrence carries the request
           week. A real user pick replaces this virtual anchor. */
        function anchorSlotFromPinned() {
            if (urlParams.day === null || urlParams.start === null || !urlParams.originalVenue) return null;
            const pinned = window.PAGE_DATA && window.PAGE_DATA.pinnedSession;
            if (!pinned) return null;
            const wk = (pinned.week || 1) - 1;
            const dayData = (window.weekData && window.weekData[wk] && window.weekData[wk].days[Number(urlParams.day)]) || null;
            /* the anchor must carry the slot-body fields the real picks have
               (start/end are 30-minute slot indices; the span follows the
               pinned session's duration) — renderTitleSummary's to12h/add30min
               path reads anchorSlot.end, and a missing field would crash the
               boot mid-render (Add 30 undefined — user land-57 verify) */
            // OUT of the cap family (user-frozen round-2 🟡3): the banner's
            // anchor derives the ORIGINAL class's true length (display-only) —
            // altering it would make the banner lie about non-FOCS originals.
            const spanSlots = Math.min(Math.max(Math.round((pinned.duration || 0.5) * 2), 1), 8);
            return {
                week: wk,
                // SDD rearrangement-toolbar-tooltips: the anchor now carries
                // the real type letter (pinnedSession payload) + numeric day
                // (slotDayNames index space, same as weekData days) so the
                // trigger tip renders the FULL honest slot — fail-open stays
                // only for a genuinely empty session_type.
                type: String(pinned.type || ''),
                day: Number(urlParams.day),
                weekdayName: (window.weekData && dayData && dayData.abbr) || '',
                date: dayData && dayData.date ? dayData.date : '',
                start: Number(urlParams.start),
                end: Number(urlParams.start) + spanSlots - 1,
                venue: String(urlParams.originalVenue),
            };
        }

        /* SDD slot-cancel-restores-session-anchor: the session-path anchor's
           trigger writer — ONE place, slotTriggerLabel-format (the trigger's
           shared format law). Returns false when no anchor stands (the
           caller keeps its fallback). */
        function writeSlotTriggerFromAnchor() {
            const a = anchorSlotFromPinned();
            if (!a) return false;
            writeSlotTrigger(a);
            /* the anchor carries the REAL type letter + numeric day from the
               pinned session (SDD rearrangement-toolbar-tooltips) — the tip
               renders fully; applySlotTip still fail-opens (tip removed) if
               session_type came back empty (never fabricate a letter) */
            applySlotTip(a);
            /* SDD-WAIVED visual parity (user rule 2026-11-01, "is it possible
               to skip sdd?" — waiver logged in doc + changelog): the session
               arrival mirrors the anchor trigger + tooltip, but the
               .slot-dd-item.selected class lives ONLY inside selectSlot()
               — the boot mirror left all items unselected while the strip
               already named the schedule (user bug report 2026-11-01, staff
               5770 ?session=97&week=8). Toggle the matching item by FULL
               identity (sessionId + 0-based week + day + start + venue —
               never "index 0"); pure class toggle, NO state writes (no
               selectSlot/selectedOriginalSlot/lastSlotIndex) so the pinned
               pair + confirm-guard laws stay byte-identical. No match =
               no toggle (fail-open, "Select a slot" state stands). */
            if (currentCourse && urlParams.session) {
                const liveSlots = extractSlotsForSubject(currentCourse.code);
                const match = liveSlots.findIndex(s =>
                    Number(s.sessionId) === Number(urlParams.session)
                    && s.week === a.week
                    && s.day === a.day
                    && s.start === a.start
                    && s.venue === a.venue);
                if (match >= 0) {
                    document.querySelectorAll('.slot-dd-item').forEach(item =>
                        item.classList.toggle('selected', parseInt(item.dataset.index) === match));
                }
            }
            return true;
        }

        /* replacement-strip-stale-anchor (Bug 1, user-approved 2026-10-24):
           after boot, the strip's anchor follows the LIVE pinned pair (the
           subject-change auto-pin or the T18 ask boundary) when that
           occurrence exists in the CURRENT subject's conflict list — the
           boot-frozen virtual anchor (PAGE_DATA.pinnedSession + boot
           urlParams) previously glued the OLD subject's slot facts under
           the NEW subject's name (the 5425 B005/B006 report). The
           pre-boot arrivals and subjects WITHOUT that occurrence keep the
           boot anchor byte-identically.

           T04: extracted read-only so the mobile snapshot reuses this ONE
           precedence instead of deriving (or re-ordering) it. */
        function resolveOriginalAnchorSlot() {
            let anchorSlot = selectedOriginalSlot || null;
            if (!anchorSlot && window.__bootDone && currentCourse && window.pinnedOccurrence) {
                const livePinSlots = extractSlotsForSubject(currentCourse.code);
                const livePinMatch = livePinSlots.find(s =>
                    Number(s.sessionId) === Number(window.pinnedOccurrence.sessionId)
                    && s.week === (Number(window.pinnedOccurrence.week || 1) - 1));
                if (livePinMatch) anchorSlot = livePinMatch;
            }
            if (!anchorSlot) anchorSlot = anchorSlotFromPinned();
            return anchorSlot;
        }

        function topZoneMobileView() {
            return document.body.classList.contains('arrangement-mobile-mode')
                || window.matchMedia('(max-width: 768px)').matches;
        }

        function topZoneSlotWeekday(slot) {
            if (!slot) return '';
            if (slot.weekdayName) return slot.weekdayName;
            const dayData = (window.weekData && window.weekData[slot.week]
                && window.weekData[slot.week].days
                && window.weekData[slot.week].days[Number(slot.day)]) || null;
            if (dayData && dayData.abbr) return dayData.abbr;
            return (typeof slotDayAbbrs !== 'undefined' && slotDayAbbrs[slot.day]) || '';
        }

        function topZoneSlotDate(slot) {
            /* weekData already stores day-first dates ('24 Dec 2026'); keep
               that law and only trim the year on mobile. */
            const raw = String((slot && slot.date) || '').trim();
            if (!raw) return '';
            if (!topZoneMobileView()) return raw;
            const parts = raw.split(' ');
            return parts.length >= 2 ? parts[0] + ' ' + parts[1] : raw;
        }
        function topZoneSlotDateNoYear(slot) {
            const raw = String((slot && slot.date) || '').trim();
            if (!raw) return '';
            const parts = raw.split(' ');
            return parts.length >= 2 ? parts[0] + ' ' + parts[1] : raw;
        }

        function topZoneReasonFacts(slot) {
            if (!slot) return null;
            let dayIdx = (slot.day !== undefined && slot.day !== null) ? Number(slot.day) : -1;
            if (dayIdx < 0) {
                const days = (window.weekData && window.weekData[slot.week]
                    && window.weekData[slot.week].days) || [];
                const abbr = slot.weekdayName;
                dayIdx = days.findIndex(function (d) { return d && d.abbr !== undefined && d.abbr === abbr; });
                if (dayIdx < 0 && abbr) {
                    const abbrIndex = { Mon: 0, Tue: 1, Wed: 2, Thu: 3, Fri: 4, Sat: 5, Sun: 6 };
                    dayIdx = Object.prototype.hasOwnProperty.call(abbrIndex, abbr) ? abbrIndex[abbr] : -1;
                }
            }
            const sid = Number(slot.sessionId
                || (window.pinnedOccurrence && window.pinnedOccurrence.sessionId)
                || currentSessionId || 0);
            if (sid && dayIdx >= 0) {
                const evs = (window.MockData && MockData.cohortTimetable && MockData.cohortTimetable.events) || [];
                const hit = evs.find(function (e) {
                    return Number(e.week) === slot.week
                        && Number(e.event.sessionId) === sid
                        && Number(e.event.di) === dayIdx
                        && Number(e.event.start) === Number(slot.start);
                });
                if (hit && hit.event.status === 'cancelled') {
                    return {
                        text: 'Class Cancelled',
                        cls: 'badge-reason',
                        detail: hit.event.cancelledReason || ''
                    };
                }
            }
            if (slot.status === 'cancelled') {
                return { text: 'Class Cancelled', cls: 'badge-reason', detail: '' };
            }
            if (dayIdx >= 0) {
                const hs = (window.MockData && MockData.holidays) || [];
                const hitHol = hs.find(function (h) {
                    return Number(h.week) === slot.week + 1 && Number(h.dayIndex) === dayIdx;
                });
                if (hitHol) {
                    return {
                        text: 'Public Holiday',
                        cls: 'badge-holiday',
                        detail: String(hitHol.label || 'Public Holiday')
                    };
                }
                const dayData = (window.weekData && window.weekData[slot.week]
                    && window.weekData[slot.week].days
                    && window.weekData[slot.week].days[dayIdx]) || null;
                if (dayData && dayData.holiday) {
                    return {
                        text: 'Public Holiday',
                        cls: 'badge-holiday',
                        detail: String(dayData.holidayLabel || 'Public Holiday')
                    };
                }
            }
            return { text: 'Conflict', cls: 'badge-reason', detail: '' };
        }

        function measureDetailsSummary() {
            const el = document.getElementById('detailsSummary');
            if (!el) return;
            const full = el.dataset.full || '';
            const short = el.dataset.short || full;
            if (!full) { el.textContent = ''; return; }
            el.textContent = full;
            if (el.clientWidth > 0 && el.scrollWidth > el.clientWidth + 1) {
                el.textContent = short;
            }
        }

        if (!window.__raDetailsSummaryBound) {
            window.__raDetailsSummaryBound = true;
            let raDetailsSummaryTimer = null;
            window.addEventListener('resize', function () {
                clearTimeout(raDetailsSummaryTimer);
                raDetailsSummaryTimer = setTimeout(measureDetailsSummary, 120);
            });
        }

        function renderCollapsedSummary(hasCourse, slot, ctxCourse) {
            const card = document.getElementById('subjectInfo');
            const toggle = document.getElementById('conflictCollapse');
            const line1 = document.getElementById('conflictSummaryLine1');
            const line2 = document.getElementById('conflictSummaryLine2');
            const body = document.getElementById('conflictCardBody');
            if (!card || !toggle || !line1 || !line2) return;

            if (!hasCourse) {
                toggle.disabled = true;
                toggle.setAttribute('aria-disabled', 'true');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.setAttribute('aria-label', 'Collapse Conflict Schedule');
                card.classList.remove('is-collapsed');
                if (body) body.hidden = false;
                line1.textContent = '';
                line2.textContent = '';
                return;
            }

            toggle.disabled = false;
            toggle.removeAttribute('aria-disabled');

            if (!slot) {
                line1.innerHTML = '<span class="ra-course-code">' + escHtml(currentCourse.code) + '</span>';
                line2.textContent = '';
                return;
            }

            const type = slot.type || ctxCourse.type || '';
            const weekday = topZoneSlotWeekday(slot);
            const date = topZoneSlotDateNoYear(slot);
            line1.innerHTML = '<span class="ra-course-code">' + escHtml(currentCourse.code) + '</span> '
                + '<span class="ra-slot-type">(' + escHtml(type) + ')</span> · W' + (slot.week + 1)
                + ' · ' + escHtml(weekday) + ', ' + escHtml(date);
            const startStr = to12h(hours[slot.start]);
            const endStr = to12h(hours[slot.end + 1] || add30min(hours[slot.end]));
            line2.innerHTML = startStr + ' ~ ' + endStr + ' @ <span class="ra-venue-pill">'
                + escHtml(slot.venue) + '</span>';
        }

        function renderTitleSummary() {
            const hasCourse = !!currentCourse;
            const anchorSlot = resolveOriginalAnchorSlot();
            const hasSlot = !!anchorSlot;
            const ctxCourse = courseCtx() || {};

            const timetableEl = document.getElementById('timetable');
            if (timetableEl) timetableEl.classList.toggle('no-subject', !hasCourse);

            const details = document.getElementById('details');
            const detailsBody = document.getElementById('detailsBody');
            const detailsToggle = document.getElementById('detailsToggle');
            if (details && detailsBody && detailsToggle) {
                if (!hasSlot) {
                    details.hidden = true;
                    detailsBody.hidden = true;
                    detailsToggle.setAttribute('aria-expanded', 'false');
                    details.classList.remove('open');
                } else if (details.hidden) {
                    details.hidden = false;
                    detailsBody.hidden = false;
                    detailsToggle.setAttribute('aria-expanded', 'true');
                    details.classList.add('open');
                }
            }

            const reason = hasSlot ? topZoneReasonFacts(anchorSlot) : null;
            const reasonEl = document.getElementById('detailReason');
            if (reasonEl) {
                reasonEl.innerHTML = reason
                    ? '<span class="badge ' + reason.cls + '"'
                        + (reason.detail ? ' data-tip="' + escHtml(reason.detail) + '"' : '') + '>'
                        + escHtml(reason.text) + '</span>'
                    : '';
            }

            const cohorts = Array.isArray(ctxCourse.cohorts) ? ctxCourse.cohorts : [];
            const fullNames = Array.isArray(ctxCourse.cohortFullNames) ? ctxCourse.cohortFullNames : [];
            const counts = Array.isArray(ctxCourse.cohortCounts) ? ctxCourse.cohortCounts : [];
            const total = Number(ctxCourse.studentCount || 0);

            const cohortEl = document.getElementById('detailCohorts');
            if (cohortEl) {
                cohortEl.innerHTML = cohorts.map(function (label, i) {
                    return '<span class="badge badge-reason" data-tip="'
                        + escHtml(fullNames[i] || label) + '">' + escHtml(label) + '</span>';
                }).join('');
            }

            const studentEl = document.getElementById('detailStudents');
            if (studentEl) {
                if (hasCourse && cohorts.length) {
                    const value = counts.length > 1
                        ? counts.join(' + ') + ' = ' + total
                        : String(total);
                    studentEl.innerHTML = '<span class="badge badge-reason">' + escHtml(value) + '</span>';
                } else {
                    studentEl.innerHTML = '<span class="badge badge-reason">—</span>';
                }
            }

            const summaryEl = document.getElementById('detailsSummary');
            if (summaryEl) {
                let full = '';
                let short = '';
                if (reason) {
                    if (cohorts.length === 1) {
                        full = reason.text + ' · ' + cohorts[0] + ' · ' + total + ' students';
                        short = reason.text + ' · 1 cohort · ' + total;
                    } else if (cohorts.length > 1) {
                        full = reason.text + ' · ' + cohorts.length + ' cohorts · ' + total + ' students';
                        short = reason.text + ' · ' + cohorts.length + ' cohorts · ' + total;
                    } else {
                        full = reason.text + ' · —';
                        short = full;
                    }
                }
                summaryEl.dataset.full = full;
                summaryEl.dataset.short = short;
                summaryEl.textContent = full;
                setTimeout(measureDetailsSummary, 0);
            }

            renderCollapsedSummary(hasCourse, anchorSlot, ctxCourse);
            refreshArrangementMobile('original');
        }

        function buildSubjectDropdown() {
            const sel = document.getElementById('subjectSelector');
            const courses = MockData.courses || [];
            sel.innerHTML = '<option value="">Select a course</option>';
            courses.forEach(c => {
                if (extractSlotsForSubject(c.code).length === 0) return;
                const opt = document.createElement('option');
                opt.value = c.code;
                opt.textContent = c.code + ' — ' + c.name;
                sel.appendChild(opt);
            });
            applySubjectTip(sel); /* rebuild resets the selection to "" -> tip cleared (fail-open) */
        }

        /* SDD rearrangement-toolbar-tooltips: the subject dropdown's hover tip —
           mirrors the CURRENT selection (option text holds "CODE — Name"; the
           tip re-assembles with a plain hyphen per the spec's exact format).
           Fail-open: empty/unknown selection -> the tip is REMOVED. */
        function applySubjectTip(sel) {
            if (!sel) return;
            const opt = sel.selectedOptions && sel.selectedOptions[0];
            if (!sel.value || !opt || !opt.text) { sel.removeAttribute('data-tip'); return; }
            const sep = opt.text.indexOf(' — ');
            const name = sep >= 0 ? opt.text.slice(sep + 3) : opt.text;
            sel.setAttribute('data-tip', 'Conflicted COURSE Selector : ' + sel.value + ' - ' + name);
        }

        function onSubjectChange() {
            applySubjectTip(document.getElementById('subjectSelector')); /* tip follows the CURRENT selection on every entry */
            if (revertingChange) return;
            const code = document.getElementById('subjectSelector').value;

            /* SDD arrangement-clear-guards-cross-week (BUG-08): same family as
               the venue guard — cross-week store-aware. */
            if ((selectedBlock || getGlobalTotal() > 0) && code !== lastSubject) {
                confirmChangeWithSelection('course', function() {
                    lastSubject = code;
                    applySubjectChange(code);
                }, function() {
                    revertingChange = true;
                    document.getElementById('subjectSelector').value = lastSubject;
                    applySubjectTip(document.getElementById('subjectSelector')); /* programmatic revert fires no change event */
                    revertingChange = false;
                });
                return;
            }
            lastSubject = code;
            applySubjectChange(code);
        }

        /* ───── Booking intent (arrival from venue-timetable "Book" action) ─────
           The handoff carries ?venue&date&time — the venue page's "Book" click
           already confirmed that exact slot, so once a subject exists we
           auto-select it instead of making the user hunt for it again. */
        let pendingBookingIntent = null;
        let intentBannerSuppressed = false; /* sticky ×: reminder stays dismissed for this booking */
        let intentNeedsManual = false;      /* booked slot can't be auto-selected (overflow/unavailable) */
        let intentTooSoon = false;          /* ...specifically because it's inside the 3-working-day window */
        let bookingIntentMemory = null;     /* persistent copy of the booking — survives the one-shot
                                               consume so subject/venue changes can re-arm it */
        let intentCancelled = false;        /* user discarded the auto-select — reload must not resurrect it */

        function intentKey(i) { return i.venue + '|' + i.dateStr + '|' + i.timeStr; }

        /* SDD arrange-intent-session-choice (D3 item 7): fallback prefill.
           Fires only on full-intent arrivals that BYPASSED the producer modal
           (no ?session=) with no subject picked. Rules come from the PAGE_DATA
           rule map + the enriched payload (no JS-hosted matrix). Never overrides
           a manual pick; never armed for bare ?venue= deep links. */
        let fallbackArmed = false;
        function armIntentFallbackPrefill() {
            if (fallbackArmed) return;
            fallbackArmed = true;
            (window.__rDebug = window.__rDebug || []).push('r:cancelled?' + intentCancelled);
            if (intentCancelled) {  return; }   // gate: discarded bookings never resurrect
            if (!pendingBookingIntent) {  return; }                // nothing to fulfil
            
            if (urlParams.session) {  return; }                    // modal path short-circuit
            if (currentCourse) {  return; }                        // a manual pick owns the flow
            const i = pendingBookingIntent;
            const venueEntry = (MockData.venues || []).find(v => v.code === i.venue);
            const venueRaw = venueEntry && venueEntry.rawRoomType ? venueEntry.rawRoomType : '';
            if (!venueRaw) {  return; }
            const rule = window.__ruleMap || {};
            let matched = null;
            
            (MockData.courses || []).some(function (m) {
                return (m.order || Object.keys(m.sessions || {})).some(function (sid) {
                    const e = m.sessions[sid];
                    /* conflicted-only (user amendment 2026-10-15): the candidate
                       must be one the system flagged for THIS intent week */
                    if (!window.PAGE_DATA.intentConflicts || !window.PAGE_DATA.intentConflicts[String(sid)]) return false;
                    /* venue-restrictions-enforcement (design D3/D4): the ONE
                       client matrix read replaces the hand-rolled base-rows
                       + the repealed-B005 note — the fallback must offer a
                       lab intent for a networking L/T session exactly when
                       the modal + dropdown would. */
                    if (!venueMatrixAllows(e.type, venueRaw, i.venue, m.code, e.cohortCodes || [])) return false;
                    if (i.hourIndex + (e.durationSlots || 1) > hours.length) return false; // day-end arithmetic
                    matched = { module: m, sessionId: sid, entry: e };
                    return true;
                });
            });
            if (!matched) {
                
                /* no prefill — the cause banner (renderBookingIntent's no-match branch) */
                intentFallbackNoMatch = true;
                evaluateBookingFit();
                return;
            }
            
            /* 2a — the legacy urlParams family (the :3044 escape guard now skips
               any default slot pick; the "Initial authority" hydration parity) */
            urlParams.code = matched.module.code;
            urlParams.day = String(matched.entry.dayOfWeek);
            urlParams.start = String(Math.round((inMinutes(matched.entry.startTime) - 8 * 60) / 30));
            urlParams.originalVenue = i.venue;
            urlParams.duration = String(matched.entry.duration);
            /* 2b — the span consequences the one-shot boot clamp can't redo */
            const hrs = Math.min(Math.max(parseFloat(urlParams.duration), 0.5), 2);
            BLOCK_SPAN = Math.round(hrs * 2);
            /* selection-budget-original-duration site B: the budget flexes to the
               occurrence's own span (the raise-only max left the stale 4). */
            MAX_SELECTION = BLOCK_SPAN;
            urlSpanLocked = true;
            /* 2c — the pin bridge (the compatible occurrence, not the module's first) */
            window.pinnedOccurrence = { sessionId: Number(matched.sessionId), week: i.weekIndex + 1 };
            /* 2d — reset the stale flags from the boot's own evaluateBookingFit
               (they were set with the DEFAULT span BEFORE the fallback sized
               BLOCK_SPAN — the round-1 🔴1/Consumordering fix), arm attribution,
               bind via the subject path (onSubjectChange → applySubjectChange →
               consumeBookingIntent — the boot's ONLY pick), staleness tail after. */
            intentNeedsManual = false;
            intentTooSoon = false;
            intentReason = '';
            const sel = document.getElementById('subjectSelector');
            fallbackAttribution = { module: matched.module.code, type: matched.entry.type, venue: i.venue };
            /* shadow the intent: the FIRST consume runs on the pre-rebuild grid
               (canPlaceBlock reads the DOM) and may short-circuit manually — the
               2e tail rebuilds the grid, restores the intent, and re-consumes. */
            const intentShadow = { ...pendingBookingIntent };
            sel.value = matched.module.code;
            
            onSubjectChange();
            /* 2e — rebuild + re-consume (the round's ordering fix): if the first
               consume short-circuited on the pre-rebuild grid, restore the
               shadowed intent, evaluate with the REAL grid, and let the consume
               auto-select land (the attributed toast then renders). */
            if (!selectedBlock) {
                pendingBookingIntent = intentShadow;
                intentNeedsManual = false;
                intentTooSoon = false;
                intentReason = '';
                saveCurrentWeek();
                buildTimetable();
                evaluateBookingFit();
                if (pendingBookingIntent) {
                    consumeBookingIntent();
                }
            }
            if (fallbackAttribution) fallbackAttribution = null;  // pick-less exit clears the flag
        }

        function inMinutes(hhmm) {
            const parts = (hhmm || '').split(':');
            return (parseInt(parts[0], 10) || 0) * 60 + (parseInt(parts[1], 10) || 0);
        }

        function bookingKeyFromParams() {
            return (urlParams.venue || '') + '|' + (urlParams.date || '') + '|' + (urlParams.time || '');
        }

        function resolveBookingIntent() {
            pendingBookingIntent = null;
            intentBannerSuppressed = false;
            intentNeedsManual = false;
            intentTooSoon = false;
            intentCancelled = false;
            if (!urlParams.date || !urlParams.time) return;
            const hourIndex = hours.indexOf(urlParams.time);
            if (hourIndex < 0) return;
            /* the shared week (localStorage) is usually already right; if the
               booked date lives in another week, jump to it */
            let weekIdx = weekNav.currentWeek;
            let dayIndex = (weekData[weekIdx].days || []).findIndex(function(d) { return d.date === urlParams.date; });
            if (dayIndex < 0) {
                for (let w = 0; w < weekData.length && dayIndex < 0; w++) {
                    const di = (weekData[w].days || []).findIndex(function(d) { return d.date === urlParams.date; });
                    if (di >= 0) { weekIdx = w; dayIndex = di; }
                }
                if (dayIndex >= 0 && weekIdx !== weekNav.currentWeek) weekNav.selectWeek(weekIdx);
            }
            if (dayIndex < 0) return;
            pendingBookingIntent = {
                dayIndex: dayIndex,
                hourIndex: hourIndex,
                weekIndex: weekIdx,
                venue: urlParams.venue || '',
                dateStr: urlParams.date,
                timeStr: urlParams.time,
                dayAbbr: (weekData[weekIdx].days[dayIndex] || {}).abbr || ''
            };
            bookingIntentMemory = { ...pendingBookingIntent };
            /* a dismissed reminder stays dismissed for the SAME booking across
               navigation (sessionStorage) — the auto-select feature still works */
            try { intentBannerSuppressed = sessionStorage.getItem('bookingIntentDismissed') === intentKey(pendingBookingIntent); }
            catch (e) { intentBannerSuppressed = false; }
            /* a discarded auto-select also stays discarded for the SAME booking:
               the user explicitly removed the block, so reloads don't resurrect it */
            try { intentCancelled = sessionStorage.getItem('bookingIntentCancelled') === intentKey(pendingBookingIntent); }
            catch (e) { intentCancelled = false; }
            renderBookingIntent();
        }

        function renderBookingIntent() {
            const el = document.getElementById('bookingIntent');
            if (!el) return;
            if (!pendingBookingIntent || intentBannerSuppressed || intentCancelled) { el.innerHTML = ''; return; }
            const i = pendingBookingIntent;
            /* conflict case: the slot can't be auto-selected — error-toned notice */
            /* The fallback found NOTHING compatible (no subject picked yet) —
               the producer zero-compatible wording, used verbatim (user decision). */
            if (intentFallbackNoMatch && !currentCourse) {
                const venueEntry = (MockData.venues || []).find(v => v.code === i.venue);
                const raw = venueEntry ? (venueEntry.rawRoomType || '') : '';
                const human = { lab: 'Lab', cisco_lab: 'Lab', tutorial: 'tutorial room', lecture_hall: 'lecture hall' }[raw] || raw;
                const req = venueTypeWording(raw);
                const only = (raw === 'lab' || raw === 'cisco_lab' || raw === 'lecture_hall') ? ' only' : '';   /* lh-l-only-revert: halls rejoin the 'only' family; tutorials stay dual */
                el.innerHTML = `<div class="booking-intent booking-intent-manual">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><strong>Booking ${i.venue} · ${i.dayAbbr}, ${i.dateStr} · ${i.timeStr}</strong> — none of your classes can run in ${i.venue} — ${human} hosts ${req} sessions${only}.</span>
                    <button class="bi-close" onclick="dismissBookingIntent()" data-tip="Dismiss booking reminder">&times;</button>
                </div>`;
                return;
            }
            if (intentNeedsManual) {
                let reason;
                if (intentReason === 'too-soon') {
                    reason = 'is within 3 working days — please pick a later slot manually.';
                } else if (intentReason === 'span') {
                    reason = 'runs longer than the time left before the day end (' + hours[hours.length - 1] + ') — pick an earlier slot or change the subject to a shorter session.';
                } else if (intentReason === 'type' && currentCourse) {
                    reason = currentCourse.code + '(' + currentCourse.type + ') has no ' + requiredTypeHuman(currentVenue.code || currentVenue) + ' sessions that can run in ' + i.venue + '. Pick a different subject slot or change the venue.';
                } else if (intentReason === 'in-the-way') {
                    reason = 'is already covered by another of your selections — clear it or pick another slot manually.';
                } else {
                    reason = 'is <span style="color:var(--color-on-error-container)">no longer available</span> on the ' + (currentVenue.code || currentVenue) + ' grid <span style="color:var(--color-on-primary-container)">— an existing class already occupies it.</span> <span style="color:var(--color-on-success-container)">Pick another slot</span> manually.';
                }
                /* notif-ghost-retract-strip R2 (the user's 2026-10-30 bug report):
                   the attribution clause splits BY STATE. 'in-the-way' = a REAL
                   selection exists (the branch's own precondition, the consume
                   :3218-3227) — the frozen venue-intent-banner-kind wording stays
                   BYTE-IDENTICAL there ('the pre-selection stays' is honest).
                   EVERY OTHER reason = the booked slot is the problem and NOTHING
                   is selected (any placement dies with the banner :1617-1619) —
                   the frozen clause would LIE ('Pre-selected … stays' with no
                   selection), so NO clause renders there: the reason text
                   already carries the manual-pick instruction, the subject sits
                   pre-bound in the dropdown (the type-cause arm names the
                   module itself), and the 'sessions fit <venue>' half was
                   never re-verified at render — the user's 2026-10-30 ruling
                   (agree/apply, the no-claim line removed per their word:
                   'we no need this message'). No colors touched. */
                el.innerHTML = `<div class="booking-intent booking-intent-manual">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span><strong>Your booked slot (${i.dayAbbr}, ${i.dateStr} · ${i.timeStr}) ${reason}</strong>
${(fallbackAttribution && intentReason === 'in-the-way')
                        ? `<br><em><span style="color:var(--color-on-success-container)">Pre-selected ${fallbackAttribution.module} (${fallbackAttribution.type})</span> <span style="color:var(--color-on-primary-container)">— its sessions fit ${i.venue};</span> <span style="font-weight:700">pick another free slot</span> — <span style="color:var(--color-on-success-container)">the pre-selection stays</span>.</em>`
                        : ''}
                    <button class="bi-close" onclick="dismissBookingIntent()" data-tip="Dismiss booking reminder">&times;</button>
                </div>`;
                return;
            }
            const msg = currentCourse ? 'will pre-fill when you next pick a subject.' : 'pick a subject to pre-fill the slots.';
            el.innerHTML = `<div class="booking-intent">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                <span><strong>Booking ${i.venue} · ${i.dayAbbr}, ${i.dateStr} · ${i.timeStr}</strong> — ${msg}</span>
                <button class="bi-close" onclick="dismissBookingIntent()" data-tip="Dismiss booking reminder">&times;</button>
            </div>`;
        }

        function dismissBookingIntent() {
            if (pendingBookingIntent) {
                try { sessionStorage.setItem('bookingIntentDismissed', intentKey(pendingBookingIntent)); } catch (e) {}
            }
            intentBannerSuppressed = true;
            renderBookingIntent();
        }

        /* T08: on mobile the canonical table is hidden, so the two feedback
           helpers reveal the VISIBLE mobile target instead. Desktop keeps its
           original element targeting unchanged. */
        function pulseTargetCell(dayIndex, hourIndex) {
            if (arrangementMobile?.isMobile()) {
                arrangementMobile.revealTarget({ dayIndex: dayIndex, hourIndex: hourIndex, tone: 'pulse' });
                return;
            }
            const cell = document.querySelector(`#tableBody td[data-day="${dayIndex}"][data-hour="${hourIndex}"] .cell-content`);
            if (!cell) return;
            cell.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            cell.classList.remove('attention-pulse');
            void cell.offsetWidth; /* restart the animation */
            cell.classList.add('attention-pulse');
            setTimeout(function() { cell.classList.remove('attention-pulse'); }, 1300);
        }

        /* notif-jump-flash-family: the summary-card jump gets the SAME
           bookable-family flash as the #earliestBtn (the jumping-target
           pulse via the shared flashRowPulse); the blocked-cell callers
           keep pulseTargetCell's attention ring (the same-color law). */
        function flashTargetCell(dayIndex, hourIndex) {
            if (arrangementMobile?.isMobile()) {
                arrangementMobile.revealTarget({ dayIndex: dayIndex, hourIndex: hourIndex, tone: 'flash' });
                return;
            }
            const cell = document.querySelector(`#tableBody td[data-day="${dayIndex}"][data-hour="${hourIndex}"] .cell-content`);
            if (!cell) return;
            cell.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            flashRowPulse(cell);
        }

        /* Whether the booked slot can be auto-selected is known once the grid sits
           on the booked venue — evaluated at the end of applyUrlParams, i.e. on
           arrival and before any user interaction. If it can't fit, the user is
           told immediately instead of after picking a subject. */
        function evaluateBookingFit() {
            if (!pendingBookingIntent) return;
            const i = pendingBookingIntent;
            intentNeedsManual = !canPlaceBlock(i.dayIndex, i.hourIndex);
            intentTooSoon = intentNeedsManual && isSlotTooSoon(weekData, i.weekIndex, i.dayIndex);
            /* five-branch honest cause (design D3 item 4) — FIRST-true-wins in
               the pinned precedence; helpers are additive (no canPlaceBlock change). */
            intentReason = '';
            if (intentNeedsManual) {
                const span = BLOCK_SPAN;
                const spanOverrun = i.hourIndex + span > hours.length;
                let held = false;
                for (let h = i.hourIndex; h < Math.min(i.hourIndex + span, hours.length); h++) {
                    if (!isCellAvailable(i.dayIndex, h)) { held = true; break; }
                }
                if (currentCourse && subjectTypeIneligibleForVenue(currentCourse)) {
                    intentReason = 'type';     // FIFTH: the picked subject can never run here
                } else if (intentTooSoon) {
                    intentReason = 'too-soon'; // SECOND (frozen wording)
                } else if (held) {
                    intentReason = 'held';     // THIRD (occupancy)
                } else if (spanOverrun) {
                    intentReason = 'span';     // FOURTH (duration × day end)
                } else {
                    intentReason = 'held';     // conservative default (defensive; shouldn't occur)
                }
                pulseTargetCell(i.dayIndex, i.hourIndex); /* the banner names the slot in words — no toast needed */
            }
            renderBookingIntent();
        }

        /* display phrase for the admitted session types of a venue (notice
           copy only) — the shared law helper with the venueCode adapter */
        function requiredTypeHuman(venueCode) {
            const venueEntry = (MockData.venues || []).find(v => v.code === venueCode);
            return venueTypeWording(venueEntry ? (venueEntry.rawRoomType || '') : '');
        }

        /* the time a booked day's grid ends (day-end cause phrase) */
        /* FIFTH-branch helper: does the picked module have ANY session whose
           type the intent/current venue admits? Raw rule data (D0) — no JS matrix. */
        function subjectTypeIneligibleForVenue(course) {
            if (!course || !course.sessions) return false;
            const venueKey = currentVenue.code || currentVenue;
            const venueEntry = (MockData.venues || []).find(v => v.code === venueKey);
            const raw = venueEntry ? (venueEntry.rawRoomType || '') : '';
            if (!raw) return false;
            const requiredTypes = Object.keys(window.__ruleMap || {}).filter(function (k) {
                return Array.isArray(window.__ruleMap[k]) && window.__ruleMap[k].includes(raw);
            });
            if (requiredTypes.length === 0) return false;
            const byType = {};
            Object.keys(course.sessions).forEach(function (sid) {
                byType[course.sessions[sid].type] = true;
            });
            return !requiredTypes.some(function (t) { return byType[t]; });
        }

        /* The user explicitly discarded a selection — remember it for THIS booking
           (sessionStorage, same key family as the banner dismissal) so a reload of
           the same URL doesn't silently re-select the block they removed. */
        function markBookingCancelled() {
            bookingIntentMemory = null; /* the booking is spent — changes must not re-arm it */
            if (!urlParams.date || !urlParams.time) return;
            try { sessionStorage.setItem('bookingIntentCancelled', bookingKeyFromParams()); } catch (e) {}
        }

        /* Explicit user discard of the current block: kill the (now stale)
           "Pre-selected…" toast, remember the booking as handled, then deselect. */
        function discardSelection() {
            toast.dismiss();
            deselectBlock();
        }

        /* SDD arrangement-clear-guards-cross-week (BUG-08, review R1 folded):
           the clearing-change confirms fire on the CROSS-WEEK store
           (selectedBlock || getGlobalTotal() > 0) — so their "Yes, Change"
           proceed must discard the WHOLE store, not just the current-week
           pointer (deselectBlock nulls selectedBlock AND writes the CURRENT
           [venue:week] entry only; a foreign week's block survives = the
           counter lies "4 of 4" over an empty grid, the BUG-06 disagreement
           class). The clearAll() register (:2073) minus its confirm shell +
           toast/undo — the confirm IS the explicit discard (2026-10-09 user
           decision contract, already carried by the subject/slot proceeds). */
        function discardAllSelections() {
            /* review R1 blocker 3 — the booking-intent SPENT-contract across
               the store map: the pointer-only capture misses the cross-week
               case and the pre-fill would RESURRECT on the next reload
               against the user's explicit discard. Scan BEFORE the wipe with
               the SAME fields intentMatchesBlock compares (:2870: i.venue ===
               venueKey && i.weekIndex === weekKey && i.dayIndex === block.day
               && i.hourIndex === block.startHour); on ANY hit run the :2854
               spent pair. */
            if (bookingIntentMemory) {
                const i = pendingBookingIntent || bookingIntentMemory;
                for (const venueKey of Object.keys(selectedSlotsByVenue)) {
                    const weekMap = selectedSlotsByVenue[venueKey] || {};
                    for (const weekKey of Object.keys(weekMap)) {
                        const blk = weekMap[weekKey];
                        if (blk && i.venue === venueKey && i.weekIndex === parseInt(weekKey, 10)
                            && i.dayIndex === blk.day && i.hourIndex === blk.startHour) {
                            markBookingCancelled();   /* :2854 — bookingIntentMemory = null + the sessionStorage stamp */
                            pendingBookingIntent = null;
                            renderBookingIntent();
                            break;   /* one hit is decisive — the intent names ONE block */
                        }
                    }
                    if (!bookingIntentMemory) break;
                }
            }
            Object.keys(selectedSlotsByVenue).forEach(k => { selectedSlotsByVenue[k] = {}; });
            clearAllRetained();            /* T15: explicit discard retires every retention marker */
            deselectBlock();
            selectionHistory.length = 0;   /* review R1 advisory 4 — the explicit-discard undo law (proceed() :2051 + pageshow :2182: "stale Ctrl+Z can't resurrect"); without it the same-week deselect HISTORY would resurrect one block into a cleaned store while the cross-week payloads have no record */
            updateCounter();               /* review R1 blocker 2 — deselectBlock()'s rider is DEAD when !selectedBlock (its early-return :1441); the helper pays its OWN tail, the exact clearAll() law ("covers the no-block case" :2084) */
            updateSelectionSummary();      /* the :1349 strip mirrors the counter drop */
        }

        /* the intent is only spent when the user explicitly removes THE booked
           block (clicking it / its summary card) — subject or venue changes keep
           the booking alive for the next subject pick */
        function intentMatchesBlock(venueKey, weekKey, block) {
            /* the live intent is usually already consumed at check time —
               compare against the persistent booking memory as fallback */
            const i = pendingBookingIntent || bookingIntentMemory;
            if (!i || !block) return false;
            return i.venue === venueKey && i.weekIndex === parseInt(weekKey, 10)
                && i.dayIndex === block.day && i.hourIndex === block.startHour;
        }

        function userDeselectSelectedBlock() {
            const block = selectedBlock ? { ...selectedBlock } : null;
            const venueKey = currentVenue.code || currentVenue;
            const weekKey = weekNav.currentWeek;
            discardSelection();
            if (intentMatchesBlock(venueKey, weekKey, block)) markBookingCancelled();
        }

        /* summary-card × — remove one saved block (any week/venue) */
        /* T08: reveal the exact saved target on the visible mobile surface
           after the existing week/venue navigation has committed. The mobile
           port validates the now-current week/venue and eligibility; desktop
           keeps the original canonical-cell flash. */
        function revealSavedTarget(venueKey, weekIdx, block) {
            if (!block) return;
            if (arrangementMobile?.isMobile()) {
                arrangementMobile.revealTarget({
                    weekIndex: weekIdx,
                    venueKey: venueKey,
                    dayIndex: block.dayIdx,
                    hourIndex: block.startHour,
                    tone: 'flash',
                });
                return;
            }
            flashTargetCell(block.dayIdx, block.startHour);
        }

        /* SDD summary-card-click-navigate: the card hop onto the EXACT
           slot — the auto-pin law's revertingChange pair bypasses the
           venue-discard modal; the per-week memory restores the block
           via the store on each rebuild. */
        function navigateToSavedBlock(venueKey, weekIdx, block) {
            revertingChange = true;
            if (weekNav.currentWeek !== weekIdx) weekNav.selectWeek(weekIdx);
            if ((currentVenue.code || currentVenue) !== venueKey) {
                venueDropdown.select(venueKey);
                applyVenueChange(venueKey);
                revertingChange = false;
                revealSavedTarget(venueKey, weekIdx, block);
                return;
            }
            revertingChange = false;
            revealSavedTarget(venueKey, weekIdx, block);
        }

        function removeSavedBlock(venueKey, weekKey) {
            const data = selectedSlotsByVenue[venueKey];
            /* T15: explicit removal retires retention metadata on BOTH paths —
               including the off-view/null-pointer removal below (no green repaint) */
            clearRetained(venueKey, weekKey);
            if (!data || !data[weekKey]) return;
            const removed = { ...data[weekKey] };
            const isCurrentView = venueKey === (currentVenue.code || currentVenue)
                && parseInt(weekKey, 10) === weekNav.currentWeek;
            if (isCurrentView && selectedBlock) {
                discardSelection();
            } else {
                data[weekKey] = null;
                updateCounter();
            }
            if (intentMatchesBlock(venueKey, weekKey, removed)) markBookingCancelled();
        }

        /* Fires once a subject exists (URL auto-pick or user pick): */
        function consumeBookingIntent() {
            if (!pendingBookingIntent || !currentCourse) return;
            const i = pendingBookingIntent;
            const venueNow = currentVenue.code || currentVenue;
            const onBookedGrid = weekNav.currentWeek === i.weekIndex && venueNow === i.venue;

            if (intentCancelled) {
                /* the user already discarded this booking's auto-select — the intent
                   is spent: clear the banner, select nothing, stay quiet */
                pendingBookingIntent = null;
                renderBookingIntent();
                return;
            }

            (window.__rDebug = window.__rDebug || []).push('r:identical?' + (selectedBlock && onBookedGrid && selectedBlock.day === i.dayIndex && selectedBlock.startHour === i.hourIndex) + ' ng:' + onBookedGrid + ' sb:' + (selectedBlock ? selectedBlock.day + '/' + selectedBlock.startHour : '-'));
            
            /* an identical selection (restored from per-week memory) already fulfils it */
            if (selectedBlock && onBookedGrid && selectedBlock.day === i.dayIndex && selectedBlock.startHour === i.hourIndex) {
                pendingBookingIntent = null;
                renderBookingIntent();
                return;
            }

            /* browsed away from the booking's week/venue before picking a subject?
               the booking owns the context — snap the grid back (per-week memory
               keeps any selection made elsewhere); skipped while a selection is
               active so nothing is discarded behind the user's back */
            if (!onBookedGrid && !selectedBlock) {
                if (weekNav.currentWeek !== i.weekIndex) weekNav.selectWeek(i.weekIndex);
                if ((currentVenue.code || currentVenue) !== i.venue) venueDropdown.select(i.venue);
            }

            
            if (selectedBlock) {
                /* a different selection is in the way — guide manually instead of
                   dropping a jarring "clear first" modal mid-flow */
                intentReason = 'in-the-way';
                intentNeedsManual = true;
                pulseTargetCell(i.dayIndex, i.hourIndex);
                renderBookingIntent();
                return;
            }

            (window.__rDebug = window.__rDebug || []).push('r:manual?' + (intentNeedsManual || !canPlaceBlock(i.dayIndex, i.hourIndex)) + ' flags:' + intentNeedsManual + ' span:' + BLOCK_SPAN + ' reason:' + intentReason);
            
            if (intentNeedsManual || !canPlaceBlock(i.dayIndex, i.hourIndex)) {
                if (!intentReason) {
                    const span0 = BLOCK_SPAN;
                    if (i.hourIndex + span0 > hours.length) {
                        intentReason = 'span';
                    } else {
                        intentReason = 'held';
                    }
                }
                intentNeedsManual = true;
                pulseTargetCell(i.dayIndex, i.hourIndex);
                renderBookingIntent();
                return;
            }

            pendingBookingIntent = null;
            renderBookingIntent();
            
            if (selectBlock(i.dayIndex, i.hourIndex)) {
                if (fallbackAttribution) {
                    toast.show('Pre-selected ' + fallbackAttribution.module + ' (' + fallbackAttribution.type + ') — auto-picked because its sessions fit ' + fallbackAttribution.venue + '; change if needed.');
                    fallbackAttribution = null; /* one-shot (design D3 item 5) */
                } else {
                    toast.show('Pre-selected from your venue booking — click the block to adjust.');
                }
            }
        }

        function applySubjectChange(code) {
            clearPreview(); /* subject state changed — any open hover preview is stale */

            if (!code) {
                currentCourse = null;
                currentSessionId = null;
                selectedOriginalSlot = null;
                renderSlotPicker([]);
                renderTitleSummary();
                buildVenueFilter();
                return;
            }

            currentCourse = (MockData.courses || []).find(c => c.code === code);
            if (!currentCourse) return;

            /* occurrence-grain default: the pinned entry's session, else the
               module's first session (same representative the code-grain keys
               used to pick); the picked slot overrides from here on */
            const autoPinRide = window.pinnedOccurrence;
            const rowSessionCandidates = extractSlotsForSubject(code);
            rowSessionCandidates.sort((a, b) => (a.week - b.week) || (a.day - b.day) || (a.start - b.start));
            const firstConflictRow = rowSessionCandidates[0] || null;

            /* occurrence-week-pin-truthfulness (user request 2026-10-19/20, T18):
               a POST-BOOT subject switch AUTO-PINS the earliest conflicted
               occurrence — the same facts the picker would surface — the
               picker-first ask only when this subject carries NO conflicted
               slot (the user still picks freely afterward; nothing about the
               submit path changes: the pin IS the pinned pair the submit
               honours).
               session-arrival-pin-guard (user report 2026-10-20): ?session=
               arrivals switch the subject PROGRAMMATICALLY at the boot
               (pre-__bootDone) — the auto-pin must not hijack the arrival
               pin there; the else-ride keeps it and courseCtx() resolves
               the strip from the pinned session's own facts. The former
               window.urlParams shadow block is DELETED: no consumers exist
               (verified: the only occurrence repo-wide was the write), and
               routing these writes into the local urlParams would hijack
               the POST-BOOT anchor/submit fallback (the bug class this
               change kills). */
            if (firstConflictRow && window.__bootDone) {
                window.pinnedOccurrence = { sessionId: firstConflictRow.sessionId, week: firstConflictRow.week + 1 };
                currentSessionId = Number(firstConflictRow.sessionId);
            } else {
                /* no conflicted occurrence for this subject → keep the
                   picker-first ask (the honest guard); on a boot-time
                   arrival WITH a pin, the ride below is THIS branch: the
                   arrival pin stays the user's */
                const wanted = autoPinRide ? String(autoPinRide.sessionId) : null;
                currentSessionId = wanted && currentCourse.sessions && currentCourse.sessions[wanted]
                    ? Number(wanted)
                    : (currentCourse.sessions ? Number(Object.keys(currentCourse.sessions)[0]) : null);
            }

            selectedOriginalSlot = null;
            /* replacement-strip-stale-anchor (Bug 1, second half): the
               "Auto-pinned …" trigger note MUST survive renderSlotPicker
               (which re-sets "Select a slot to replace" internally) — write
               the trigger AFTER the picker re-render, not before it. */
            // Extract and render conflict/cancelled slots for this subject
            const slots = rowSessionCandidates;
            renderSlotPicker(slots);
            writeSlotTrigger(firstConflictRow || null);
            applySlotTip(firstConflictRow || null); /* same conditional: real slot -> full tip, else cleared */

            /* THE WEEK DECISION (was: the auto-nav jump — the user's
               “choose the first conflicted slot for me”). The strip's anchor
               still names the pinned class (the trigger note above) and the
               grid's own buildTimetable runs right after this function's tail;
               only the WEEK is now decided by decideSwitchWeek().
               session-arrival-pin-guard T6 (user decision B, 2026-10-20) still
               holds through the same __bootDone conjunct: pre-boot arrivals
               never run this block at all, so the pinned week stands.
               arrangement-week-keep-on-subject-switch (2026-11-08) replaces the
               old "jump to the occurrence's week" — see the comment below. */
            /* arrangement-week-keep-on-subject-switch (user brief 2026-11-07,
               decided 2026-11-08): the week NO LONGER jumps to the clash week.
               It stays on the week the selector shows when that week is
               bookable for the newly selected subject (the date rule AND at
               least one free cell in the settled room), else it moves to the
               earliest bookable week; when nothing is bookable it stays put and
               says nothing (D5/D6). The auto-pin, the slot-box label, the
               venue sync and the tail's auto-commit are untouched (D1). */
            if (firstConflictRow && window.__bootDone && typeof weekNav !== 'undefined') {
                const switchTarget = decideSwitchWeek(firstConflictRow);
                if (switchTarget.week !== weekNav.currentWeek) {
                    weekNav.selectWeek(switchTarget.week);
                    currentWeek = weekNav.currentWeek;
                    window.currentWeek = currentWeek;
                    saveCurrentWeek();
                }
            }
            renderTitleSummary();
            buildVenueFilter();
            consumeBookingIntent();
            /* vectors are session-keyed — a subject switch re-keys the grid
               (guarded: during boot the week selector is not ready yet; the
               boot's own buildTimetable renders the final state once) */
            if (window.__bootDone) { saveCurrentWeek(); buildTimetable(); }
            /* arr-slot-original-venue-sync ROUND 2 (the staff-5425 sighting
               68/14 → BMSE2163, user word 2026-10-24): the POST-boot auto-pin
               must agree the venue with the strip BEFORE a manual pick — the
               strip already read "@ B010" while the dropdown stayed on the
               inherited B100 (P-ineligible) = the whole-red grid. The auto-pin
               pick NEVER reaches commitSlotSelection (applyDefaultSlotSelection's
               branch-1 pinned-slot early-return skips it), so the sync runs HERE.
               The equal/no-target rebuild is skipped (phase 'auto-pin'): the tail's
               own buildTimetable already rendered. Pre-boot arrivals == untouched
               (window.__bootDone guard; the bridge's opts.auto fallback stays). */
            /* arr-slot-original-venue-sync ROUND 3 (the user's bug report
               2026-11-01, SDD-waived — waiver in the changelog): the POST-boot
               subject switch must AUTO-COMMIT the matching conflict slot, not
               only mirror it on the strip. ROOT CAUSE of the old
               nothing-selected state: renderSlotPicker's tail
               applyDefaultSlotSelection hits its BRANCH-1
               (urlParams.code && day && start && originalVenue — the handoff
               params PERSIST in the URL) designed for the BOOT, where
               applyUrlParams's own branch performs the one pick — a POST-boot
               subject switch has no later pick, so the picker rendered
               UNSELECTED while the trigger/strip named the pinned conflict row
               (the comment above already recorded "the auto-pin pick NEVER
               reaches commitSlotSelection"). THE FIX: after the auto-pin
               writes the pin, find the picker row that MATCHES the pin
               (solver-identity: sessionId + week + day + start + venue, all
               runtime — never hardcoded) and commit it through the EXISTING
               machinery (selectSlot opts.auto — the same shape as every
               default pick): the selected class, selectedOriginalSlot,
               lastSlotIndex, the trigger/tip, the original-venue sync and the
               URL truth ALL stay ONE writer. No-rows/missing-row falls back to
               the old syncVenueFamily behaviour. The confirm guard cannot
               fire: a user subject switch either early-outs (empty store) or
               discards first (confirmChangeWithSelection → discardAllSelections
               THEN applySubjectChange), so the store is already empty; an
               identical re-pick rides the SDD-WAIVED same-slot identity guard
               (no modal, byte-equal state). */
            if (window.__bootDone && firstConflictRow) {
                const pinIdx = slots.findIndex(s =>
                    Number(s.sessionId) === Number(firstConflictRow.sessionId)
                    && s.week === firstConflictRow.week
                    && s.day === firstConflictRow.day
                    && s.start === firstConflictRow.start
                    && s.venue === firstConflictRow.venue);
                if (pinIdx !== -1) {
                    /* span-follow-subject-switch: opts.subjectSwitch opts this
                       POST-boot pick OUT of the F-8 URL width lock — the new
                       occurrence's own duration must size the block (see
                       commitSlotSelection's SPAN-FOLLOW-SUBJECT-SWITCH law). */
                    selectSlot(slots[pinIdx], pinIdx, { auto: true, subjectSwitch: true });
                } else {
                    syncVenueFamily(firstConflictRow, 'auto-pin');
                }
            }
        }

        /* venue law (SDD lt-venue-capacity-rule, user ruling 2026-10-19):
           ONE rule map governs eligibility — Lecture Hall & Tutorial Rooms
           admit L AND T (capacity-gated), labs stay P-only. This filter
           reads `window.__ruleMap[ctx.type]` over the venue's RAW
           `room_type` (the pay-what-you-are key; display `type` is only
           shown). Fail-closed when the map is missing — a payload regression
           must never re-open the law drift. (The B005 no-Diploma arm was
           REPEALED 2026-10-27, SDD b005-diploma-allowed — deleting it only
           widens the accept set; the fail-closed map guard stands.) */
        /* venue-restrictions-enforcement (SDD design D4): the ONE client
           matrix read mirroring the catalog's PHP gate (design D2) — ONE
           formula, NO early-return chain that could bypass the venue deny
           gates. Capacity is NOT part of this matrix (engine-side).
           Programme identification: the ALPHA PREFIX of the stripped
           compact cohort label ('DFT2 (S1) G1'-family — spacing stripped;
           a distinct 'RAFS' extracts as RAFS: no prefix collisions). */
        function venueMatrixAllows(type, rawRoomType, venueCode, moduleCode, cohortCodes) {
            const rule = window.__ruleMap;
            if (!rule) return false;                       // map absent → deny (fail-closed)
            const tag = (rawRoomType || '').toLowerCase();
            const rowKeys = rule[type];
            if (!Array.isArray(rowKeys)) return false;     // unknown type — fail closed

            const labs = ['lab', 'cisco_lab'];
            const baseRow = rowKeys.indexOf(tag) !== -1;
            /* 2026-11-07 (SDD lab-access-type-aware): the roster is per module
               AND session type — `lab_access[moduleCode]` lists the types whose
               own seeded session sits in a lab. Both additive arms (any lab,
               and B006 by room code) require the type to be listed; an unknown
               module is `[]` → no widening (the fail-closed reading stands). */
            const modLabTypes = !!moduleCode ? ((rule.lab_access || {})[moduleCode] || []) : [];
            const labsAdditive = modLabTypes.indexOf(type) !== -1
                && labs.indexOf(tag) !== -1;
            const b006Code = rule.b006_venue_code || 'B006';
            const b006Widen = venueCode === b006Code && modLabTypes.indexOf(type) !== -1;
            if (! (baseRow || labsAdditive || b006Widen)) return false;

            if (venueCode === b006Code) {
                const focs = rule.b006_focs_programmes || [];
                const banned = rule.b006_banned_programmes || [];
                const cohorts = cohortCodes || [];
                for (let k = 0; k < cohorts.length; k++) {
                    const p = (String(cohorts[k]).replace(/\s+/g, '').match(/^[A-Z]+/) || [''])[0];
                    if (!p) return false;               // unparsable label — fail closed
                    if (banned.indexOf(p) !== -1) return false;
                    if (focs.indexOf(p) === -1) return false;
                }
            }
            // (was) the B005 no-Diploma deny arm — REPEALED 2026-10-27
            // (SDD b005-diploma-allowed). Rule-map consumers compose on.
            return true;
        }

        function venueEligible(v, ctx) {
            if (!v || !ctx) return true;
            /* venueEligible's callers pass the spaced display labels — the
               matrix needs the compact alpha prefixes; strip both shapes. */
            const cohortCodes = (ctx.cohorts || []).map(function(c) { return String(c).replace(/\s+/g, ''); });
            if (!venueMatrixAllows(ctx.type, v.rawRoomType, v.code, ctx.code, cohortCodes)) return false;
            var capOk = v.capacity >= (ctx.studentCount || 0);   // the engine-side rule, mirrored client-side as always
            return capOk;
        }

        /* ── arrangement-week-keep-on-subject-switch ─────────────────────────
           "Bookable for THIS subject" = the existing DATE rule (not Sunday, not
           a holiday, not inside the 3-working-day window) AND at least one free
           half-hour in the room the page settles on (the pinned occurrence's own
           room). The page's slot map is a TAKEN register —
           venueSlotsByWeekPayload() emits rows ONLY for occupied/pending
           time_slots — so a half-hour with NO row is a free cell, exactly where
           the grid's classifyState() paints .cell-available. */
        function weekBookableForSubject(weekIdx, row) {
            /* the date rule is NEVER fail-open: a payload glitch must not keep a
               week the week selector itself hides (D5) */
            if (!weekHasBookableSlot(weekData, weekIdx)) return false;

            try {
                if (!row || !currentCourse) return true;   // free-cell half fails open
                const roomCode = row.venue;
                const venueRecord = (MockData.venues || []).find(v => v.code === roomCode);
                const ctx = courseCtx();
                if (!roomCode || !venueRecord || !ctx || !window.__ruleMap) return true;

                /* the room cannot host this occurrence at all → no free cell */
                if ((MockData.blockedVenues || {})[String(row.sessionId)]
                    && MockData.blockedVenues[String(row.sessionId)].indexOf(roomCode) !== -1) return false;
                if (!venueEligible(venueRecord, ctx)) return false;

                /* this room's TAKEN half-hours for the week, as a day:hour set */
                const taken = {};
                const roomRows = ((MockData.venueSlots || {})[weekIdx] || {})[roomCode] || [];
                roomRows.forEach(r => { taken[r[0] + ':' + r[1]] = true; });

                const busyRanges = ((MockData.busyByWeek || {})[String(row.sessionId)] || {})[weekIdx] || [];
                const days = (weekData[weekIdx] || {}).days || [];

                for (let d = 0; d < days.length; d++) {
                    const day = days[d];
                    if (!day || day.sunday || day.abbr === 'Sun' || day.holiday) continue;
                    if (isSlotTooSoon(weekData, weekIdx, d)) continue;
                    for (let h = 0; h < hours.length; h++) {
                        if (taken[d + ':' + h]) continue;
                        if (busyRanges.some(b => b[0] === d && h >= b[1] && h < b[2])) continue;
                        return true;   // a cell the grid would paint green
                    }
                }
                return false;
            } catch (e) {
                return true;           // missing/broken payload → keep the week
            }
        }

        /* the earliest week bookable for this subject, or null when none is */
        function firstBookableWeekForSubject(row) {
            const weeks = (weekNav && weekNav._semester && weekNav._semester.weeks) || (weekData || []).length;
            for (let w = 0; w < weeks; w++) {
                if (weekBookableForSubject(w, row)) return w;
            }
            return null;
        }

        /* the decision (design §3): keep / earliest / silent stay */
        function decideSwitchWeek(row) {
            const kept = weekNav ? weekNav.currentWeek : 0;
            if (weekBookableForSubject(kept, row)) return { week: kept, why: 'kept' };
            const earliest = firstBookableWeekForSubject(row);
            if (earliest !== null) return { week: earliest, why: 'earliest' };
            return { week: kept, why: 'none' };      // D6: silent stay
        }

        function buildVenueFilter() {
            if (!venueDropdown) return;

            /* 2026-11-10 (user): the "Showing venues that fit N students" note was
               removed from the page — the FILTER is the surviving contract, so only
               that is applied here now. */
            if (currentCourse) {
                var ctxCourse = courseCtx(); /* occurrence-grain: follows the picked slot */
                venueDropdown.setFilter(function(v) {
                    return venueEligible(v, ctxCourse);
                });
            } else {
                venueDropdown.setFilter(null);
            }
        }

        // ───── Slot Picker (conflict/cancelled slots for selected subject) ─────

        let selectedOriginalSlot = null;
        const slotDayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        const slotDayAbbrs = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        function extractSlotsForSubject(courseCode) {
            const slots = [];
            const events = (MockData.cohortTimetable || {}).events || [];
            
            events.forEach(entry => {
                const e = entry.event;
                if (!e) return;
                if (e.code === courseCode && (e.status === 'conflict' || e.status === 'cancelled')) {
                    const weekIdx = entry.week;
                    const dayIdx = e.di;
                    // Build date string from weekData
                    let dateStr = '';
                    try {
                        if (weekData[weekIdx] && weekData[weekIdx].days && weekData[weekIdx].days[dayIdx]) {
                            dateStr = weekData[weekIdx].days[dayIdx].date;
                        }
                    } catch(ex) {}
                    slots.push({
                        week: weekIdx,
                        day: dayIdx,
                        dayName: slotDayNames[dayIdx] || 'Unknown',
                        sessionId: e.sessionId, /* occurrence identity — the pin/URL/context follow the pick */
                        start: e.start,
                        end: e.end,
                        venue: e.venue,
                        status: e.status,
                        name: e.name,
                        type: e.type,
                        code: courseCode,
                        date: dateStr
                    });
                }
            });
            
            return slots.sort((a, b) => a.week - b.week || a.day - b.day || a.start - b.start);
        }

        function renderSlotPicker(slots) {
            const picker = document.getElementById('slotPicker');
            const panel = document.getElementById('slotPanel');

            slotPickerSlots = slots || [];

            if (!slots || slots.length === 0) {
                picker.style.display = 'none';
                /* the hidden-stale-panel quirk (the user's Item 1, SDD-waived
                   2026-11-01): the return used to skip the panel clear, so the
                   PREVIOUS subject's rows — INCLUDING their `.selected` class —
                   survived hidden in the DOM. Clear the panel alongside hiding;
                   the TRIGGER state stays untouched (the caller owns it:
                   selectedOriginalSlot is already null at both call sites and
                   writeSlotTriggerFromAnchor/placeholder run after). */
                panel.innerHTML = '';
                return;
            }
            
            picker.style.display = '';
            panel.innerHTML = '';
            selectedOriginalSlot = null;
            writeSlotTrigger(null);
            applySlotTip(null); /* reset: no slot picked -> no tip */

            slots.forEach((slot, index) => {
                const startStr = to12h(hours[slot.start]);
                const endStr = to12h(hours[slot.end + 1] || add30min(hours[slot.end]));
                /* 2026-11-10 (user, "UI Update — Slot Dropdown Badge"): the badge
                   names the REAL reason, read from the slot's own data —
                     · the day it falls on is a public holiday → `Public Holiday`
                     · the class is cancelled                        → `Cancelled`
                     · otherwise (a clash)                            → `Conflict`
                   The holiday fact is the SAME one the grid paints (`weekData[…]
                   .days[…].holiday`, filled by generateWeekData from MockData
                   .holidays), so the badge can never disagree with the grid. No
                   string here is hardcoded to one occurrence. */
                const reason = topZoneReasonFacts(slot);
                const statusLabel = reason.text;
                const statusClass = reason.cls;
                const statusDetail = reason.detail;
                // Parse "18 Aug 2026" → "Aug 18" + the year
                const dateParts = slot.date.split(' ');
                const shortDate = dateParts[1] + ' ' + dateParts[0];
                const year = dateParts[2] || '';
                /* SDD rearrangement-toolbar-tooltips Batch 2 (req 3b): the
                   pinned session's cohorts ride the label — shortened to
                   their first token ('DSF2 (S1) G1' → 'DSF2'), deduped,
                   joined ' + ', ALL dynamic (never hardcoded). */
                const course = (MockData.courses || []).find(function(c) { return c.code === slot.code; });
                const sess = course && course.sessions ? course.sessions[String(slot.sessionId)] : null;
                const cohortNames = (function() {
                    const seen = {};
                    const out = [];
                    ((sess && sess.cohorts) || []).forEach(function(cn) {
                        const short = (cn || '').split(' ')[0];
                        if (short && !seen[short]) { seen[short] = true; out.push(short); }
                    });
                    return out;
                })();
                const cohortHtml = cohortNames.length
                    ? '<span class="slot-dd-item-cohort"><svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg> ' + cohortNames.join(' + ') + '</span>'
                    : '';
                const fullLabel = '(' + slot.type + ') Week ' + (slot.week + 1) + ' · ' + slotDayAbbrs[slot.day] + ', ' + slot.date + ', ' + startStr + ' ~ ' + endStr + ' @ ' + slot.venue;

                const item = document.createElement('div');
                item.className = 'slot-dd-item';
                item.dataset.index = index;
                /* Batch 2: the label = (TYPE) Week N · Mon D, YEAR · range @ venue
                   · the cohorts icon+names; the slot favourite star is GONE (req 3a) —
                   its machinery (FAV_KEY/getFavouriteSlot/toggleSlotFavourite +
                   the default-priority branch) removed with it. */
                item.innerHTML = `
                    <span class="slot-dd-item-label"><span class="cell-type-label">(${slot.type})</span> Week ${slot.week + 1} · ${shortDate}${year ? ', ' + year : ''} · ${startStr} - ${endStr} @ ${slot.venue}${cohortHtml ? ' · ' : ''}${cohortHtml}</span>
                    <span class="slot-dd-item-badge badge ${statusClass}"${statusDetail ? ` data-tip="${escHtml(statusDetail)}"` : ''}>${escHtml(statusLabel)}</span>
                `;

                // Hover tooltip
                const tooltip = document.getElementById('slotTooltip');
                item.addEventListener('mouseenter', (e) => {
                    const itemRect = item.getBoundingClientRect();
                    const pickerRect = picker.getBoundingClientRect();
                    tooltip.textContent = fullLabel;
                    tooltip.style.top = (itemRect.top - pickerRect.top) + 'px';
                    tooltip.style.left = (panel.offsetWidth - 1) + 'px';
                    tooltip.style.display = 'block';
                });
                item.addEventListener('mouseleave', () => {
                    tooltip.style.display = 'none';
                });
                const badgeEl = item.querySelector('.slot-dd-item-badge');
                if (badgeEl && statusDetail) {
                    badgeEl.addEventListener('mouseenter', () => { tooltip.style.display = 'none'; });
                }

                // Click to select
                item.addEventListener('click', () => {
                    selectSlot(slot, index);
                    closeSlotPanel();
                });

                panel.appendChild(item);
            });

            // Apply default selection priority
            applyDefaultSlotSelection(slots);
        }

        function selectSlot(slot, index, opts) {
            if (revertingChange) return;

            /* SDD arrangement-clear-guards-cross-week (BUG-08): same family as
               the venue guard — cross-week store-aware. PINNED (review R1
               advisory 6): the auto default picks ride {auto:true} at panel
               build only (boot / subject change — a fresh or JUST-WIPED
               store) so the widened guard cannot nag an automatic action. */
            if ((selectedBlock || getGlobalTotal() > 0) && index !== lastSlotIndex) {
                /* SAME-SLOT NO-CONFIRM (user bug 2026-11-01, SDD-waived — waiver
                   in page-changelogs/replacement-arrangement-changelog.md): the
                   guard compares INDEXES only, so on a session arrival (the
                   anchor mirror is DELIBERATELY uncommitted, lastSlotIndex
                   null) re-picking the ONLINE-mirrored slot popped "Change
                   Time Slot?" even though the (grid) selections carry the
                   same slot. Identity, not index, governs: same as the
                   committed pick (slotPickerSlots[lastSlotIndex]) OR, when
                   nothing is bound, the same identity as the PINNED anchor
                   behind the current store. Same-session commit re-keys
                   nothing (commitSlotSelection only reacts to an identity
                   CHANGE) — the grid selections survive byte-identical. */
                const committed = (lastSlotIndex !== null && lastSlotIndex !== undefined && slotPickerSlots[lastSlotIndex]) || null;
                let sameSlot = false;
                if (slot) {
                    if (committed) {
                        sameSlot = Number(slot.sessionId) === Number(committed.sessionId)
                            && slot.week === committed.week
                            && slot.day === committed.day
                            && slot.start === committed.start
                            && slot.venue === committed.venue;
                    } else if (window.pinnedOccurrence && window.pinnedOccurrence.sessionId) {
                        const anchorA = anchorSlotFromPinned();
                        const wk0 = Number(window.pinnedOccurrence.week || 1) - 1;
                        sameSlot = !!anchorA
                            && Number(slot.sessionId) === Number(window.pinnedOccurrence.sessionId)
                            && slot.week === wk0
                            && slot.day === anchorA.day
                            && slot.start === anchorA.start
                            && slot.venue === anchorA.venue;
                    }
                }
                if (!sameSlot) {
                    confirmChangeWithSelection('time slot', function() {
                        lastSlotIndex = index;
                        commitSlotSelection(slot, index);
                    }, function() {
                    // Snap the picker back to the previously committed slot
                    revertingChange = true;
                    if (lastSlotIndex !== null && lastSlotIndex !== undefined && slotPickerSlots[lastSlotIndex]) {
                        commitSlotSelection(slotPickerSlots[lastSlotIndex], lastSlotIndex);
                    } else {
                        selectedOriginalSlot = null;
                        document.querySelectorAll('.slot-dd-item').forEach(item => item.classList.remove('selected'));
                        /* SDD slot-cancel-restores-session-anchor: on a handoff
                           state lastSlotIndex-bound null means the PINNED
                           anchor stands uncommitted — restore the trigger to
                           that anchor, NOT the placeholder; the placeholder
                           stays only for genuinely anchor-less states. */
                        if (!writeSlotTriggerFromAnchor()) {
                            writeSlotTrigger(null);
                            applySlotTip(null); /* placeholder -> no tip (the anchor path sets its own) */
                        }
                        renderTitleSummary();
                    }
                    revertingChange = false;
                });
                return;
                }
            }
            lastSlotIndex = index;
            commitSlotSelection(slot, index, opts);
        }

        /* shared: the trigger's human slot label — ONE format for the two
           writers (a user pick and the post-switch auto-pin). The BRIDGE PATCH
           (+1 week number) stays: slot.week is the 0-based weekData index. */
        /* SDD rearrangement-toolbar-tooltips Batch 2 (req 3c): the trigger
           text sizes to 'Week {N} · {Day}, {D Mon YEAR}' — day-first, with
           the year riding the weekData date dynamically. */
        function slotTriggerLabel(slot) {
            if (!slot) return null;
            const startStr = to12h(hours[slot.start]);
            const endStr = to12h(hours[slot.end + 1] || add30min(hours[slot.end]));
            const weekday = topZoneSlotWeekday(slot);
            const date = topZoneSlotDate(slot);
            return {
                line1: '<span class="ra-slot-type">(' + escHtml(slot.type || '') + ')</span> Week '
                    + (slot.week + 1) + ' · ' + escHtml(weekday) + ', ' + escHtml(date),
                line2: escHtml(startStr) + ' ~ ' + escHtml(endStr)
                    + ' @ <span class="ra-venue-pill">' + escHtml(slot.venue || '') + '</span>'
            };
        }

        /* ONE writer for #slotTriggerText. Callers pass the full slot object;
           null resets the placeholder as a single text node. */
        function writeSlotTrigger(slot) {
            const el = document.getElementById('slotTriggerText');
            if (!el) return;
            if (!slot) {
                el.textContent = 'Select a slot to replace';
                return;
            }
            const label = slotTriggerLabel(slot);
            if (!label) return;
            el.innerHTML = '<span id="slotTriggerLine1">' + label.line1 + '</span>'
                + '<span id="slotTriggerLine2">' + label.line2 + '</span>';
        }

        /* SDD rearrangement-toolbar-tooltips: the slot picker trigger's hover
           tip — assembled from the SAME slot object the visible writer uses
           (REAL type letter, 0-based week + 1, slotDayNames, slot.date, to12h
           range, venue code). Fail-open: no slot or missing type/week/venue
           facts (e.g. the virtual pinned anchor) -> the tip is REMOVED. */
        function applySlotTip(slot) {
            const el = document.getElementById('slotTriggerText');
            if (!el) return;
            if (!slot || slot.week == null || !slot.type || !slot.venue) { el.removeAttribute('data-tip'); return; }
            const startStr = to12h(hours[slot.start]);
            const endStr = to12h(hours[slot.end + 1] || add30min(hours[slot.end]));
            el.setAttribute('data-tip', 'Conflicted SLOT Selector: (' + slot.type + ') Week ' + (slot.week + 1) + ', ' + slotDayAbbrs[slot.day] + ', ' + slot.date + ', ' + startStr + ' ~ ' + endStr + ' @ ' + slot.venue);
        }

        /* ORIGINAL-VENUE SYNC (user law 2026-10-24, arr-slot-original-venue-sync;
           shared so BOTH the picked-slot path (commitSlotSelection) and the
           POST-boot auto-pin path (applySubjectChange's tail) agree the
           dropdown with the schedule's ORIGINAL room):
           - the picked conflict schedule's own room is the selected venue +
             label (slot.venue — runtime, never hardcoded), BOTH the value AND
             the .venue-dd-label, in the revertingChange pair (no mid-switch
             "Change Venue?" confirm — the slot-change consent already ran).
           - the OPTIONS keep the existing buildVenueFilter law.
           - NOT offered → the STANDING fallback: the first filtered venue ONLY
             when the current venue would render dead (else keep current + the
             count note — no new chrome).
           - the CTX must be FRESH: the caller may have JUST switched subjects
             (the post-boot auto-pin commits/render before the tail re-filter;
             the ROUND-2 68/14 sighting) — so the helper re-runs
             buildVenueFilter() (no-op-equal for an already-fresh ctx). */
        function syncVenueFamily(slot, phase) {
            buildVenueFilter(); /* decide on the FRESH ctx (see above) */
            const origCode = slot && slot.venue;
            const filtered = venueDropdown.getFiltered ? venueDropdown.getFiltered() : [];
            const curVenue = String(currentVenue.code || currentVenue || '');
            let target = null;
            if (origCode && filtered.some(v => v.code === origCode)) {
                target = String(origCode);   /* offered: the schedule's own room wins (also defeats the select() phantom-guard silently ignoring a code outside the deck) */
            } else {
                const curV = (MockData.venues || []).find(v => v.code === curVenue);
                if (!curV || !venueEligible(curV, courseCtx())) {
                    const first = filtered[0];
                    if (first && first.code) target = String(first.code);
                }
                /* else: current venue stays eligible -> keep it (design D4); a slot without a venue rides the same keep/dead check. */
            }
            if (target && String(target) !== curVenue) {
                if (selectedBlock) deselectBlock();
                revertingChange = true;
                venueDropdown.select(target);   /* value + label parity (_updateTrigger) */
                revertingChange = false;
                applyVenueChange(target);       /* grid + __fillVenueChips + favstar */
            } else if (target) {
                venueDropdown.select(target);   /* parity refire even when equal — onSelect fires under reverting (design D3); panel _close() is the already-closed state */
                saveCurrentWeek();
                buildTimetable();
            } else if (phase !== 'auto-pin') {
                /* the equal/no-target rebuild: the manual PICK already sits on
                   the (re)rendered grid — rebuilding keeps the pending
                   intent/banner state honest; the AUTO-PIN path skips this —
                   applySubjectChange's tail buildTimetable already positioned
                   the grid (a second null-effect rebuild would be noise). */
                saveCurrentWeek();
                buildTimetable();
            }
        }

        function commitSlotSelection(slot, index, opts) {
            /* SDD selection-budget-original-duration (2026-10-24, the user's
               law): the block width follows the picked conflict slot's
               duration — same clamp family as the URL branch (0.5–2 h →
               ≤4 slots; slot indices are 30-min units) — and the BUDGET
               (MAX_SELECTION) FLEXES to the occurrence's own span: ONE
               occurrence-length block total across every week (the old
               raise-only `max()` left a 1-hour class a stale 4-slot budget —
               two 1-hour picks slipped through; the user's live report).
               The F-8 "URL keeps INITIAL authority" now governs ONLY the
               WIDTH: an AUTO default pick (urlSpanLocked && opts.auto)
               cannot resize a URL-&duration= block, but the budget STILL
               flexes to the committed span (the occurrence's own facts
               govern the budget at every site, bootstrap or manual).
               SUPERSERED: the Oct-09 "4-slot floor so a shorter class can
               book twice" boot decision (see also the URL branch's note).
               SPAN-FOLLOW-SUBJECT-SWITCH (2026-11-01, the user's bug report
               "selected slot appears selected but the duration is wrong",
               SDD-waived): the conflict-subject-switch-autoselect fix routes
               the POST-boot subject-switch pick through opts.auto TOO — under
               a ?session= arrival urlSpanLocked is TRUE and the F-8 guard
               then skipped the resize, so a 2 h lecture arrival kept the
               block 4 slots wide AFTER switching to a 1 h practical (the
               hover preview AND the payload). A post-boot switch is NOT the
               URL's boot pick: the switch's auto commit opts out of the
               F-8 width lock via opts.subjectSwitch, and the picked
               occurrence's own span (slotSpan) sizes the block — the same
               law the manual pick path always had. */
            const slotSpan = Math.min(Math.max(slot.end - slot.start + 1, 1), 4);
            if (slotSpan !== BLOCK_SPAN
                && !(urlSpanLocked && opts && opts.auto && !(opts && opts.subjectSwitch))) {
                BLOCK_SPAN = slotSpan;
            }
            if (MAX_SELECTION !== slotSpan) {
                MAX_SELECTION = slotSpan;   /* selection-budget-original-duration site C: the budget = the occurrence's span */
                updateCounter();
            }
            /* BRIDGE PATCH (2026-10-12 user report): the picked slot IS the
               occurrence being replaced — re-derive the session context (type,
               cohorts, venue eligibility, grid vectors), move the D1 pin, and
               track it in the URL so refresh/back stay truthful. */
                if (slot.sessionId && Number(slot.sessionId) !== currentSessionId) {
                currentSessionId = Number(slot.sessionId);
                buildVenueFilter();
                window.pinnedOccurrence = { sessionId: slot.sessionId, week: slot.week + 1 };
                const from = new URLSearchParams(window.location.search).get('from') || 'replacement-home';
                history.replaceState({}, '', '/replacement-arrangement?session=' + slot.sessionId + '&week=' + (slot.week + 1) + '&from=' + from);
                /* the selected venue may not be ELIGIBLE for the new occurrence's
                   type/headcount/programme rules (e.g. T's B107 tutorial for the
                   50-seat L) — an ineligible venue renders every cell occupied.
                   Fall back to the first venue the matrix allows (the occurrence's
                   own room can itself violate the rule — e.g. a P seeded in a
                   tutorial room); the switch bypasses onVenueChange's confirm —
                   the slot-change confirm already consented to the switch.
                   ARR-SLOT-ORIGINAL-VENUE-SYNC (design D1): this fallback is the
                   ARRIVAL/AUTO-PIN law — it now runs ONLY for opts.auto picks
                   (the pre-boot URL default + the post-boot subject-switch pin);
                   the manual path's venue law moved to the ORIGINAL-VENUE SYNC
                   below (the user's 2026-10-24 law). */
                if (opts && opts.auto) {
                    const curVenue = currentVenue.code || currentVenue;
                    const curV = (MockData.venues || []).find(v => v.code === curVenue);
                    if (curV && !venueEligible(curV, courseCtx())) {
                        const target = (venueDropdown.getFiltered ? venueDropdown.getFiltered() : [])[0];
                        if (target && target.code) {
                            if (selectedBlock) deselectBlock();
                            revertingChange = true;
                            venueDropdown.select(target.code);
                            revertingChange = false;
                            applyVenueChange(target.code);
                        }
                    } else if (window.__bootDone) {
                        saveCurrentWeek();
                        buildTimetable();
                    }
                }
            }
            /* ORIGINAL-VENUE SYNC (user law 2026-10-24, arr-slot-original-venue-sync):
               every pick re-selects the picked conflict schedule's
               ORIGINAL room — slot.venue (runtime, the same object the picker
               rows + the strip anchor read): BOTH the dropdown's selected value
               AND the .venue-dd-label. Options keep the buildVenueFilter law.
               Orig NOT offered -> the STANDING fallback: the first filtered
               venue ONLY when the current venue would render dead (else keep
               current + the count note — no new chrome). The move
               bypasses onVenueChange by the revertingChange pair (the
               slot-change confirm already consented). GAP A closed: the family
               runs on same-session picks too; GAP B closed: an eligible
               inherited room yields to the schedule's own room.
               ROUND 2 (the staff-5425 sighting 68/14 → BMSE2163): POST-boot
               auto pins (the subject-switch auto-pin, the fallback's own
               auto default) ride {auto:true} and previously SKIPPED the
               family — the strip already read "@ B010" while the dropdown
               stayed on the inherited B100 (P-ineligible) = the whole-red
               grid until a manual pick. The gate therefore reads
               __bootDone ALONE: PRE-boot arrivals keep the URL/pinned venue
               law (the bridge's opts.auto fallback above); the
               auto-pin law follows the strip.
            */
            if (window.__bootDone) {
                syncVenueFamily(slot, 'pick');
            }
            selectedOriginalSlot = slot;
            /* BRIDGE PATCH (2026-10-11 user report): slot.week is the 0-based weekData
   index (the mock's expander counts w from 0) — printing it raw showed the
   picker one week behind (Nov 9 = "W7", Dec 25 = "W13"). The label is
   human-facing: show the semester's week NUMBER (+1); all internal uses of
   slot.week stay 0-based indices. The formatting itself lives in the shared
   slotTriggerLabel() (the auto-pin writer uses it too). */
writeSlotTrigger(slot);
            applySlotTip(slot); /* SDD rearrangement-toolbar-tooltips: full slot details on the same pick */

            // Update selected state in panel
            document.querySelectorAll('.slot-dd-item').forEach(item => {
                item.classList.toggle('selected', parseInt(item.dataset.index) === index);
            });

            renderTitleSummary();
        }

        function toggleSlotPanel() {
            const panel = document.getElementById('slotPanel');
            const isOpen = panel.classList.contains('open');
            if (isOpen) {
                closeSlotPanel();
            } else {
                panel.classList.add('open');
                // Close on outside click
                setTimeout(() => {
                    document.addEventListener('click', closeSlotPanelOnOutside);
                }, 0);
            }
        }

        function closeSlotPanel() {
            const panel = document.getElementById('slotPanel');
            const tooltip = document.getElementById('slotTooltip');
            panel.classList.remove('open');
            tooltip.style.display = 'none';
            document.removeEventListener('click', closeSlotPanelOnOutside);
        }

        function closeSlotPanelOnOutside(e) {
            const picker = document.getElementById('slotPicker');
            if (!picker.contains(e.target)) {
                closeSlotPanel();
            }
        }

        /* SDD rearrangement-toolbar-tooltips Batch 2 (req 3a): the SLOT
           favourite machinery (FAV_KEY/getFavouriteSlot/toggleSlotFavourite)
           is GONE — its only consumer was the removed dropdown star; the
           default-priority branch that re-picked it dropped with it (Recent
           → First now). 2026-11-10 (user, code cleanup): the VENUE favourites
           (the shared VenueDropdown's star) are gone as well — the whole
           feature is permanently removed, both kinds. */

        // ── Recent Slot (last submitted, localStorage) ──
        const RECENT_KEY = 'replacement-recent';

        function getRecentSlot() {
            try {
                return JSON.parse(localStorage.getItem(RECENT_KEY) || 'null');
            } catch (e) { return null; }
        }

        function setRecentSlot(slot) {
            localStorage.setItem(RECENT_KEY, JSON.stringify({ code: slot.code, day: slot.day, start: slot.start, end: slot.end, venue: slot.venue, week: slot.week }));
        }

        // ── Default Selection Priority ──
        function applyDefaultSlotSelection(slots) {
            // 1. URL params from Venue Timetable (already handled in applyUrlParams)
            /* the handoff's PINNED slot (home or venue-timetable) outranks
               recent/first — the default picks fired here used to run
               AFTER it and fight it (favourite re-picked the L occurrence on a
               T handoff, flipping the context AND the venue). If the URL pinned
               a slot, applyUrlParams's own branch below does the only pick. */
            if (urlParams.code && urlParams.day !== null && urlParams.start !== null && urlParams.originalVenue) return;
            // 2. Favourite — REMOVED, and now removed app-wide (2026-11-10):
            //    the slot star went in Batch 2, the venue star in the cleanup
            // 3. Recent
            const recent = getRecentSlot();
            if (recent) {
                const match = slots.find(s => s.code === recent.code && s.day === recent.day && s.start === recent.start && s.venue === recent.venue);
                if (match) {
                    const idx = slots.indexOf(match);
                    selectSlot(match, idx, { auto: true });
                    return;
                }
            }
            // 4. First option
            if (slots.length > 0) {
                selectSlot(slots[0], 0, { auto: true });
            }
        }

        function readUrlParams() {
            const params = new URLSearchParams(window.location.search);
            urlParams = {
                code: params.get('code'),
                cohort: params.get('cohort'),
                venue: params.get('venue'),
                date: params.get('date'),
                time: params.get('time'),
                day: params.get('day'),
                start: params.get('start'),
                end: params.get('end'),
                originalVenue: params.get('originalVenue'),
                duration: params.get('duration'),
                /* BRIDGE PATCH: the handoff pins the exact occurrence being
                   replaced — the backend's own identity (class_session_id +
                   week_number, the D1 pair). The server resolves +
                   ownership-checks it (PAGE_DATA.pinnedSession) and its facts
                   hydrate the legacy urlParams below, so the existing
                   auto-pick + BLOCK_SPAN machinery runs unchanged. */
                session: params.get('session'),
                week: params.get('week')
            };
            return urlParams;
        }

        function applyUrlParams() {
            /* a hand-typed/tampered venue param must not render a phantom venue:
               ignore it — and the booking that named it — and load the default */
            if (urlParams.venue && !MockData.venues.some(v => v.code === urlParams.venue)) {
                urlParams.venue = null;
                urlParams.date = null;
                urlParams.time = null;
            }
            /* Booking intent (venue-timetable "Book" handoff): resolve date+time
               into a grid target first — the shared week is already restored */
            resolveBookingIntent();
            const sel = document.getElementById('subjectSelector');
            /* venue BEFORE subject, so the booking-intent auto-select (fired by
               the subject pick below) lands on the booked venue's grid */
            /* BRIDGE PATCH: originalVenue (home handoff) selects like venue
               (venue-timetable handoff) */
            if (urlParams.venue || urlParams.originalVenue) {
                if (venueDropdown) {
                    venueDropdown.select(urlParams.venue || urlParams.originalVenue);
                }
            }
            if (urlParams.code) {
                sel.value = urlParams.code;
                onSubjectChange();
            }
            
            /* Auto-select the ORIGINAL occurrence slot — legacy handoff shape
               (?code&day&start&originalVenue, replacement home/history). The
               venue-timetable SESSION handoff (?session=&week=) is NOT that
               shape: its booking intent already auto-selected the block at the
               BOOKED cell, and this legacy pick would re-select the session's
               own slot — a second selectSlot with the block live → the spurious
               "Clear Current Selection?" confirm (user report 2026-10-16).
               The pinned occurrence still binds via PAGE_DATA.pinnedSession,
               so submitBlocks targets the right week regardless. */
            if (urlParams.code && urlParams.day !== null && urlParams.start !== null && urlParams.originalVenue && !urlParams.session) {
                const slots = extractSlotsForSubject(urlParams.code);
                const matchingSlot = slots.find(s => 
                    s.day === parseInt(urlParams.day) && 
                    s.start === parseInt(urlParams.start) && 
                    s.venue === urlParams.originalVenue
                );
                if (matchingSlot) {
                    const slotIndex = slots.indexOf(matchingSlot);
                    selectSlot(matchingSlot, slotIndex, { auto: true });
                }
            }
            evaluateBookingFit();
        }

        /* (the mock's own DOMContentLoaded wrapper is unwrapped — this whole
            script already runs inside the one above) */
        {
            readUrlParams();
            /* BRIDGE PATCH: hydrate the pinned occurrence (server-resolved,
               ownership-checked). window.pinnedOccurrence rides along to
               submitBlocks so the request targets THIS occurrence's week —
               not whichever week the grid happens to show. */
            window.pinnedOccurrence = null;
            if (window.PAGE_DATA.pinnedSession) {
                const pinned = window.PAGE_DATA.pinnedSession;
                window.pinnedOccurrence = { sessionId: pinned.id, week: pinned.week };
                urlParams.code = pinned.code;
                urlParams.day = String(pinned.day);
                urlParams.start = String(pinned.start);
                urlParams.originalVenue = pinned.venue;
                urlParams.duration = String(pinned.duration);
            }
            // If the original class duration was passed in (hours), size the block
            // span from it — clamped to 0.5–2h (FULL-BLOCK user cap: FOCS
            // blocks run 0.5–2h, the 4-slot cap). SDD
            // selection-budget-original-duration site A: the BUDGET flexes to the
            // span too — the Oct-09 "4-slot floor so a shorter class can book
            // twice" decision is SUPERSEDED (the user law 2026-10-24: one
            // occurrence-length slot total).
            if (urlParams.duration && !isNaN(parseFloat(urlParams.duration))) {
                const hrs = Math.min(Math.max(parseFloat(urlParams.duration), 0.5), 2);
                BLOCK_SPAN = Math.round(hrs * 2);
                MAX_SELECTION = BLOCK_SPAN;
                urlSpanLocked = true;
            }
            buildSubjectDropdown();
            renderTitleSummary();

            /* init venue dropdown */
            venueDropdown = new VenueDropdown(
                document.getElementById('buildingSelectorDropdown'),
                {
                    venues: MockData.venues,
                    /* BRIDGE PATCH: the replacement-home handoff names the
                       class's original venue (FR 2.5 default) */
                    initialCode: urlParams.originalVenue || urlParams.venue || currentVenue,
                    /* SDD rearrangement-toolbar-tooltips Batch 2 (req 1): the
                       selector-voice trigger tip — code + full name + capacity */
                    triggerTipPrefix: 'Replacement VENUE Selector: ',
                    triggerLabel: function(v) {
                        return '<span class="ra-venue-code">' + escHtml(v.code) + '</span>'
                            + ' <span class="ra-venue-seats">' + escHtml(String(v.capacity || '')) + ' seats</span>';
                    },
                    onSelect: function(code) { onVenueChange(); }
                }
            );
            /* venue-trigger-label law (user 2026-10-27): the seats-free label +
               the seats-bearing data-tip are OWNED by the shared VenueDropdown
               (_updateTrigger) now — the old textContent-copy observer here
               would overwrite that tip with the plain label, so it's gone. */
            /* BRIDGE PATCH: keep currentVenue in step with the initial venue */
            currentVenue = venueDropdown.getSelected() || currentVenue;
            /* venue-description-doc-b006-rule + venue-side-chips (the user's
               chip ask, amended 2026-10-23): right of the venue dd —
               (1) the capacity badge = icon + number + hover tooltip;
               (2) the description badge = info-icon + hover tooltip
                   carrying the FULL description (the user's option: ok on
                   this page). Reads MockData.venues (the replaced real-data
                   list = venuesPayload, canonical from the DB). Chips only
                   here — the plain line is retired (the tooltip carries it).
               STALENESS FIX (the user's B005/B006 report): the fill is a
               NAMED function re-invoked on EVERY venue change — the one
               boot pass left the chips crumb after a dd switch. */
            window.__fillVenueChips = function (code) {
                const chipEl = document.getElementById('venueChipGroup');
                if (!chipEl) return;
                const ddV = (MockData.venues || []).find(v => v.code === code) || null;
                if (!ddV) return;
                const descA = document.getElementById('arrVenueDescription');
                if (descA) {
                    descA.textContent = '';
                    descA.insertAdjacentHTML('beforeend',
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>');
                    if (ddV.description) {
                        descA.setAttribute('data-tip', ddV.description);
                        descA.setAttribute('aria-label', 'Room description: ' + ddV.description);
                    } else {
                        descA.removeAttribute('data-tip');
                        descA.setAttribute('aria-label', 'Room description');
                    }
                }
                chipEl.style.display = '';
            };
            window.__fillVenueChips(urlParams.venue || currentVenue);
            document.getElementById('semesterChip').textContent = MockData.semester.chipText;
            /* ARRANGEMENT-EARLIEST-WEEK-DEFAULT (user decisions Q2 A + Q3 A,
               2026-10-20) — as amended by ARRIVAL-GRID-NO-PIN (user law
               2026-10-20): ?session=…&week= now carries the occurrence PAIR
               (the strip + the submit target) — NEVER the landing. The
               saved-week probe runs BEFORE load() — load()'s one-time
               legacy migration (F-11) would promote the 'currentWeek' key
               into 'arrangementWeek' during the run, and a post-load probe
               could never tell memoryless from migrated.
               The landing rule:
               • plain, no-URL landings keep the Q2-A chain (a saved week
                 wins; earliest fills the memoryless void — firstBookableDay
                 skips Sundays + holidays + the lead-time too-soon days).
               • pinned arrivals land on the Earliest Bookable week ALWAYS
                 ("doesn't matter where they came from") — even over a
                 saved week.
               • COMBINED pinned+intent URLs (?session + &venue&date&time —
                 the A6 family): the resolveBookingIntent jump WINS the
                 landing (the booked-date week) — the destination is the
                 fresher user-instruction (the A decision).
               SILENT by decision (Q3 A): no toast; the selector
               self-documents. No save call — the memoryless state stays
               memoryless until the user navigates. The OLD localStorage
               keys live readable-only in history; the bridge writes
               nothing. */
            var hadSavedWeek = false;
            try {
                hadSavedWeek = localStorage.getItem('arrangementWeek') !== null
                    || localStorage.getItem('currentWeek') !== null;
            } catch (e) { hadSavedWeek = false; }

            /* restore the previously saved week (localStorage), else falls back to week 1 */
            weekNav.load();
            if (!window.pinnedOccurrence && hadSavedWeek) {
                /* plain + saved: the working-week resume (load() already
                   restored the position — nothing to write here) */
            } else {
                const eb = firstBookableDay(weekData);
                if (eb) weekNav._currentWeek = eb.week;
            }
            populateWeekSelect('weekSelector', {
                ranges: false,
                selected: weekNav.currentWeek,
                /* unbookable weeks (no bookable slot) are hidden from the list —
                   WeekNavigator's weekFilter keeps navigation consistent */
                weekFilter: function(i) { return weekHasBookableSlot(weekData, i); }
                /* labels = shared default: full "Week N · DD Mon YYYY ~ DD Mon YYYY"
                   on desktop (matches the venue page), compact on mobile */
            });
            /* ARRIVAL-GRID-NO-PIN: the post-populate selectWeek(pinnedIdx)
               call is DELETED — the bridge no longer positions (or persists
               arrangementWeek from) the pinned arrival; the grid rebuilds at
               the landing rule's week via the boot's own buildTimetable,
               while the pinned pair keeps riding the strip + the submit. */
            /* dated tooltip on the "Earliest bookable" action (computed, not hardcoded);
               the boundary day's chip lives in the grid time-col (cfg.bookableBadge) */
            const earliest = firstBookableDay(weekData);
            const earliestBtn = document.getElementById('earliestBtn');
            if (earliestBtn && earliest) {
                /* 2026-11-11 (user report): this read "Sunday, 16 Nov 2026" — a Monday.
                   `earliest.day` is a MONDAY-based index (weekData.days runs Mon→Sun),
                   so indexing a Sunday-first array named the day before. The name now
                   comes from the same date string the tooltip prints, so the two halves
                   can never disagree (DateHelper.weekdayName). */
                const edate = weekData[earliest.week].days[earliest.day];
                const eday = DateHelper.weekdayName(edate.date);
                earliestBtn.setAttribute('data-tip', 'Jump to the earliest bookable slot \u2014 ' + (eday ? eday + ', ' : '') + edate.date + ' (Week ' + (earliest.week + 1) + ')');
            }
            /* applyUrlParams AFTER week selector is ready (triggers onVenueChange → buildTimetable) */
            applyUrlParams();
            /* from here on, subject/slot changes may rebuild the grid + vectors
               (during boot those rebuilds are deferred to the boot's own
               buildTimetable — the week selector is not readable before this) */
            window.__bootDone = true;
            /* arr-slot-original-venue-sync Test-5: the value/label parity assert
               reads the dropdown's underlying selection via this read-only
               hook (venueDropdown + currentVenue are closure-scoped; this
               mirrors the page's __fillVenueChips/__rDebug probe surfaces). */
            window.__venueSelected = function () { return venueDropdown ? venueDropdown.getSelected() : null; };

            /* arrangement-week-keep-on-subject-switch: read-only receipt/debug
               surface (house pattern: window.classifyCell, __venueSelected). The
               Playwright receipt drives the decision through it; it is NOT page
               UI and must never write state. */
            window.__switchWeek = {
                bookable: weekBookableForSubject,
                earliest: firstBookableWeekForSubject,
                decide: decideSwitchWeek,
            };
            /* Subject tooltip (SDD rearrangement-toolbar-tooltips): mirrors the
               current selection's code + full name; fail-open — no selection,
               no tip (replaces the old syncSubjectTip guide-text empty state) */
            const subjectSel = document.getElementById('subjectSelector');
            subjectSel.addEventListener('change', function() { applySubjectTip(subjectSel); });
            applySubjectTip(subjectSel);
            lastSubject = document.getElementById('subjectSelector').value;
            /* ensure grid always renders on load (applyUrlParams only triggers via venue param) */
            buildTimetable();
            /* session-path anchor: mirror the pinned occurrence's slot in the
               picker trigger (cosmetic — no selectSlot, no confirm modal); a
               real panel pick replaces it like any manual pick */
            if (!selectedOriginalSlot) writeSlotTriggerFromAnchor();
            /* arrange-intent-session-choice, session-path 2e parity (user report
               2026-10-16, follow-up): the booking-intent consume fired during
               the boot on a PRE-rebuild grid (isCellAvailable needs the DOM)
               and could land nothing, leaving just the pulse/manual banner.
               With no block placed and the intent still alive, re-consume on
               the REAL grid — the same deterministic extra hop the fallback's
               2e tail established for the no-session path. */
            if (!selectedBlock && pendingBookingIntent && !intentCancelled) {
                evaluateBookingFit();
                consumeBookingIntent();
            }
            /* venue-intent-ghost-success-spot (the user's live bug report,
               2026-10-23): the boot's PRE-build consume could place a block
               against a not-yet-real venue grid (canPlaceBlock reads the DOM;
               the venue's real occupancy paints with the boot's own
               buildTimetable ABOVE) — the success toast fired; then
               loadCurrentWeek() (inside that build) nulls selectedBlock and
               its restore guard refuses the span (a real span cell = not
               cell-available) → silent despawn, the intent already spent.
               The ghost is detectable AFTER the build ONLY via the SAVED
               entry (selectedSlotsByVenue), never via selectedBlock.
               Re-validate the saved block against the REAL grid; on a
               mismatch drop the entry AND re-arm the intent from the
               untouched urlParams facts (the session-cancel rule stays
               honored inside resolveBookingIntent) — evaluateBookingFit()
               then speaks the honest manual banner (with the
               venue-intent-banner-kind attribution clause) + the pulse, and
               the lying success toast retracts (dismiss = no side effects).
               The success toast only ever valid when the block SURVIVES the
               real grid. */
            if (!selectedBlock) {
                const savedGhost = (selectedSlotsByVenue[currentVenue] || {})[weekNav.currentWeek] || null;
                if (savedGhost) {
                    const bodyB = document.getElementById('tableBody');
                    let ghost = false;
                    for (let h = savedGhost.startHour; h < savedGhost.endHour; h++) {
                        const tdB = bodyB.querySelector(`td[data-day="${savedGhost.day}"][data-hour="${h}"] .cell-content`);
                        if (!tdB || !tdB.classList.contains('cell-available')) { ghost = true; break; }
                    }
                    if (ghost) {
                        selectedSlotsByVenue[currentVenue][weekNav.currentWeek] = null;
                        try { toast.dismiss(); } catch (e) {}   /* the ghost toast retracts (dismiss = no onManualDismiss side effects) */
                        /* arm the banner's kind clause for the SESSION path too
                           (fallbackAttribution only ever arms on the no-session
                           path): the pre-selected module = urlParams.code — the
                           honest fit claim rests on the producer modal, which
                           offered exactly this session for this venue. */
                        if (!fallbackAttribution && urlParams.code) {
                            const modG = (MockData.courses || []).find(c => c.code === urlParams.code) || null;
                            if (modG) {
                                const entG = modG.sessions ? (modG.sessions[String(urlParams.session)] || null) : null;
                                fallbackAttribution = { module: modG.code, type: (entG && entG.type) || modG.type || '', venue: urlParams.venue || currentVenue };
                            }
                        }
                        resolveBookingIntent();
                        evaluateBookingFit();
                        /* notif-ghost-retract-strip R1: the strip MUST mirror
                           the retract the same breath — updateCounter chains
                           updateSelectionSummary/Progress/Stats (:1333-1335),
                           so the ghost's summary card, the 'N of M' counter
                           and the submit/clear button states all drop with
                           the store entry (the strip ran only on this
                           retract's ABSENCE for two receipted lanes — the
                           user's 2026-10-30 bug report). Order matters: the
                           rider reads the store SETTLED (after the re-arm's
                           possible selectWeek rebuild). */
                        updateCounter();
                    }
                }
            }
            /* SDD arrange-intent-session-choice (D3, soft-freeze placement amendment):
               the fallback needs the RENDERED grid (isCellAvailable reads the DOM,
               :1528) — so it runs after the boot's own buildTimetable. The fallback's
               onSubjectChange runs with __bootDone true (uniform post-boot behavior:
               its rebuild + saveCurrentWeek are the accepted, deterministic extra hop). */
            armIntentFallbackPrefill();

            document.addEventListener('keydown', handleKeyDown);
            /* T13: capture-phase guard for the shared ui-common week shortcut
               owner. One registration for the page lifetime. */
            document.addEventListener('keydown', blockSharedWeekShortcuts, true);
            initWeekKeyboardShortcuts();

            document.getElementById('earliestBtn')?.addEventListener('click', function() {
                try {
                    saveCurrentWeek();
                    weekNav.jumpToEarliestBookable();
                    var sel = document.getElementById('weekSelector');
                    if (sel) sel.value = weekNav.currentWeek;
                } catch (err) {
                    window.__earliestBtnError = err.message + ' | ' + (err.stack || '').split('\n').slice(0,3).join(' ');
                }
            });

            document.getElementById('conflictCollapse')?.addEventListener('click', function () {
                if (this.disabled) return;
                const card = document.getElementById('subjectInfo');
                const body = document.getElementById('conflictCardBody');
                if (!card || !body) return;
                const collapsed = !card.classList.contains('is-collapsed');
                card.classList.toggle('is-collapsed', collapsed);
                body.hidden = collapsed;
                this.setAttribute('aria-expanded', String(!collapsed));
                this.setAttribute('aria-label', collapsed
                    ? 'Expand Conflict Schedule'
                    : 'Collapse Conflict Schedule');
                if (!collapsed) setTimeout(measureDetailsSummary, 0);
            });

            document.getElementById('detailsToggle')?.addEventListener('click', function () {
                const body = document.getElementById('detailsBody');
                const details = document.getElementById('details');
                if (!body) return;
                const opening = body.hidden;
                body.hidden = !opening;
                this.setAttribute('aria-expanded', String(opening));
                if (details) details.classList.toggle('open', opening);
                if (opening) setTimeout(measureDetailsSummary, 0);
            });

            let topZoneResizeTimer = null;
            window.addEventListener('resize', function () {
                clearTimeout(topZoneResizeTimer);
                topZoneResizeTimer = setTimeout(function () {
                    const slot = selectedOriginalSlot || anchorSlotFromPinned();
                    if (slot) writeSlotTrigger(slot); else writeSlotTrigger(null);
                    renderTitleSummary();
                }, 160);
            });

            const mobileContext = document.querySelector('[data-arrangement-mobile-context]');
            if (mobileContext) {
                mobileContext.addEventListener('toggle', function () {
                    if (mobileContext.open) setTimeout(measureDetailsSummary, 0);
                });
            }
        }

        /* onclick= / inline-handler globals resolve via window */
        window.goBack = goBack;
        window.navigateTo = navigateTo;
        window.confirmChangeWithSelection = confirmChangeWithSelection;   // selection-budget T3: the closure fns the guard rows read
        window.toggleTheme = toggleTheme;
        window.navigateHome = navigateHome;
        window.onSubjectChange = onSubjectChange;
        window.toggleSlotPanel = toggleSlotPanel;
        window.proceed = proceed;
        window.clearAll = clearAll;
        window.clearSelection = clearSelection;
        window.classifyCell = classifyCell;   // arrangement-slot-ownership-legend: the browser receipt calls it directly
        window.classifyState = classifyState;         // arrangement-cell-state-labels: class + words
        window.cellStateLabel = cellStateLabel;       // the hover words alone (<br> joined)
        window.hideConfirmModal = hideConfirmModal;
        window.showHelp = showHelp;
        window.hideHelp = hideHelp;
        window.dismissBookingIntent = dismissBookingIntent;
        window.removeSavedBlock = removeSavedBlock;
        /* the ui-week-nav partial's inline onclick calls weekNav.* */
        window.weekNav = weekNav;

        /* ── T04 — read-only canonical snapshot port ────────────────────────
           Copies page state and the canonical cell text only. It never writes
           the selection store, never inserts HTML, never classifies raw
           payloads and never touches the network. Missing/unrecognised cells
           fail closed. T05 owns the coalesced refresh; T06 owns layout. */
        const SNAPSHOT_STATE_CLASSES = [
            'cell-available', 'cell-selected', 'cell-pending', 'cell-reserved',
            'cell-occupied', 'cell-occupied--mine', 'cell-sun', 'cell-ph',
            'cell-too-soon'
        ];

        /* textContent that keeps real line breaks (the canonical labels use
           `<br>`; tooltips use `\n`). Text only — never copied as markup. */
        function snapshotText(node) {
            if (!node) return '';
            let out = '';
            Array.prototype.forEach.call(node.childNodes, function (child) {
                if (child.nodeType === 3) {
                    out += child.nodeValue;
                } else if (child.nodeType === 1) {
                    out += child.nodeName === 'BR' ? '\n' : snapshotText(child);
                }
            });
            return out.replace(/[ \t\u00a0]+/g, ' ').replace(/ *\n */g, '\n').trim();
        }

        function snapshotAttr(el, name) {
            if (!el) return '';
            const value = el.getAttribute(name);
            return value === null || value === undefined ? '' : String(value).trim();
        }

        function snapshotShown(el) {
            if (!el || el.hidden) return false;
            if (el.closest('[hidden]') || el.closest('[inert]')) return false;
            if (!window.getComputedStyle) return true;
            const style = window.getComputedStyle(el);
            return !!style && style.display !== 'none' && style.visibility !== 'hidden';
        }

        function readArrangementSnapshot() {
            const activeWeek = weekNav ? Number(weekNav.currentWeek) : null;
            const weekIndex = Number.isInteger(activeWeek) ? activeWeek : null;
            const venueCode = String((venueDropdown && venueDropdown.getSelected()) || currentVenue || '');
            const activeWeekData = weekIndex !== null && weekData && weekData[weekIndex]
                ? weekData[weekIndex] : null;
            const ctx = typeof courseCtx === 'function' ? courseCtx() : null;
            const anchor = typeof resolveOriginalAnchorSlot === 'function'
                ? resolveOriginalAnchorSlot() : null;

            /* Real week/day indexes and the existing helper eligibility. No
               renumbering, no occupancy filter, no prototype clock. */
            const weeks = (weekData || []).map(function (week, wi) {
                const days = (week && Array.isArray(week.days) ? week.days : []).map(function (day, di) {
                    const sunday = !!(day && (day.sunday || day.abbr === 'Sun'));
                    const holiday = !!(day && day.holiday);
                    const tooSoon = typeof isSlotTooSoon === 'function'
                        ? !!isSlotTooSoon(weekData, wi, di)
                        : true; /* helpers unavailable -> fail closed */
                    return {
                        index: di,
                        abbr: day ? String(day.abbr || '') : '',
                        date: day ? String(day.date || '') : '',
                        sunday: sunday,
                        holiday: holiday,
                        holidayLabel: day ? String(day.holidayLabel || '') : '',
                        today: !!(day && day.today),
                        firstBookable: !!(day && day.firstBookable),
                        tooSoon: tooSoon,
                        eligible: !sunday && !holiday && !tooSoon
                    };
                });
                return {
                    index: wi,
                    label: week ? String(week.label || '') : '',
                    range: week ? String(week.range || '') : '',
                    rangeShort: week ? String(week.rangeShort || '') : '',
                    eligible: typeof weekHasBookableSlot === 'function'
                        ? !!weekHasBookableSlot(weekData, wi) : false,
                    days: days
                };
            });

            /* Canonical cells for the CURRENT week/venue: every original day
               and every hour index. State text comes from the resolved cell,
               never from the raw payload. */
            const body = document.getElementById('tableBody');
            const hourAxis = Array.isArray(hours) ? hours.slice() : [];
            const dayCount = activeWeekData && Array.isArray(activeWeekData.days)
                ? activeWeekData.days.length : 0;
            const cells = [];
            let knownCells = 0;
            for (let di = 0; di < dayCount; di++) {
                for (let hi = 0; hi < hourAxis.length; hi++) {
                    const cellEl = body
                        ? body.querySelector('td[data-day="' + di + '"][data-hour="' + hi + '"] .cell-content')
                        : null;
                    const stateClasses = cellEl
                        ? SNAPSHOT_STATE_CLASSES.filter(function (cls) {
                            return cellEl.classList.contains(cls);
                        })
                        : [];
                    const known = !!cellEl && stateClasses.length > 0;
                    if (known) knownCells++;
                    const timeEl = cellEl ? cellEl.querySelector('.cell-time-label') : null;
                    cells.push({
                        dayIndex: di,
                        hourIndex: hi,
                        known: known,
                        stateClasses: stateClasses,
                        stateLabel: snapshotText(cellEl ? cellEl.querySelector('.cell-state-label') : null),
                        detailText: snapshotAttr(cellEl, 'data-tip'),
                        ariaText: snapshotAttr(cellEl, 'aria-label'),
                        timeLabel: snapshotText(timeEl)
                    });
                }
            }

            /* Saved entries are a plain copy from the page store; the pointer
               is the current-view block only (never the global summary). */
            const saved = window.ArrangementMobile && window.ArrangementMobile.utils
                ? window.ArrangementMobile.utils.savedEntries(selectedSlotsByVenue, weekData)
                : [];
            const currentBlock = selectedBlock ? {
                venueKey: venueCode,
                weekIndex: weekIndex,
                dayIndex: Number(selectedBlock.day),
                startHour: Number(selectedBlock.startHour),
                endHour: Number(selectedBlock.endHour)
            } : null;

            /* Original occurrence facts for the compact context. */
            const anchorWeek = anchor && Number.isInteger(Number(anchor.week)) ? Number(anchor.week) : null;
            const anchorDayIndex = anchor && Number.isInteger(Number(anchor.day)) ? Number(anchor.day) : null;
            const anchorDay = anchorWeek !== null && anchorDayIndex !== null && weekData
                && weekData[anchorWeek] && Array.isArray(weekData[anchorWeek].days)
                ? weekData[anchorWeek].days[anchorDayIndex] : null;
            const startSource = anchor && Number.isInteger(Number(anchor.start))
                ? hourAxis[Number(anchor.start)] : '';
            const endIndex = anchor && Number.isInteger(Number(anchor.end)) ? Number(anchor.end) : null;
            const endSource = endIndex !== null
                ? (hourAxis[endIndex + 1] || (hourAxis[endIndex] ? add30min(hourAxis[endIndex]) : ''))
                : '';

            const original = {
                hasSubject: !!currentCourse,
                code: currentCourse ? String(currentCourse.code || '') : '',
                name: currentCourse ? String(currentCourse.name || '') : '',
                type: ctx ? String(ctx.type || '') : '',
                cohorts: ctx && Array.isArray(ctx.cohorts) ? ctx.cohorts.map(String) : [],
                cohortCounts: ctx && Array.isArray(ctx.cohortCounts) ? ctx.cohortCounts.map(Number) : [],
                studentCount: ctx && ctx.studentCount !== null && ctx.studentCount !== undefined
                    ? Number(ctx.studentCount) : null,
                hasSlot: !!anchor,
                weekIndex: anchorWeek,
                dayIndex: anchorDayIndex,
                weekdayName: anchor
                    ? String(anchor.weekdayName || (anchorDay && anchorDay.abbr) || '')
                    : '',
                date: anchor
                    ? String(anchor.date || (anchorDay && anchorDay.date) || '')
                    : '',
                /* original-slot indexes as the page anchor stores them
                   (end is INCLUSIVE) — unlike saved selection entries, whose
                   endHour is exclusive. */
                startIndex: anchor && Number.isInteger(Number(anchor.start)) ? Number(anchor.start) : null,
                endIndex: endIndex,
                startTime: startSource ? to12h(startSource) : '',
                endTime: endSource ? to12h(endSource) : '',
                venue: anchor ? String(anchor.venue || '') : '',
                pin: window.pinnedOccurrence ? {
                    sessionId: Number(window.pinnedOccurrence.sessionId),
                    weekIndex: Number(window.pinnedOccurrence.week || 1) - 1
                } : null
            };

            /* Existing control values/visibility, read only. */
            const subjectSel = document.getElementById('subjectSelector');
            const subjectOption = subjectSel && subjectSel.options && subjectSel.selectedIndex >= 0
                ? subjectSel.options[subjectSel.selectedIndex] : null;
            const slotTriggerText = document.getElementById('slotTriggerText');
            const venueLabel = document.querySelector('#buildingSelectorDropdown .venue-dd-label');
            const weekSel = document.getElementById('weekSelector');
            const weekOption = weekSel && weekSel.options && weekSel.selectedIndex >= 0
                ? weekSel.options[weekSel.selectedIndex] : null;
            const summaryTipEl = document.getElementById('summaryTip');
            const submitTipEl = document.getElementById('submitTip');
            const submitBtn = document.getElementById('submitRequestBtn');
            const subjectInfoEl = document.getElementById('subjectInfo');

            /* T16: read-only retention metadata — WHICH saved identities T15
               currently retains. The store itself is never exposed or mutated. */
            const retainedKeys = Object.keys(retainedUnrenderableKeys || {});

            return {
                readOnly: true,
                source: 'canonical-table',
                weekIndex: weekIndex,
                weekNumber: weekIndex !== null ? weekIndex + 1 : null,
                venueCode: venueCode,
                weeks: weeks,
                hours: hourAxis,
                cells: cells,
                cellsComplete: cells.length > 0 && knownCells === cells.length,
                selections: {
                    entries: saved,
                    current: currentBlock,
                    totalSlots: typeof getGlobalTotal === 'function' ? getGlobalTotal() : 0
                },
                /* T16: metadata only (never the store). The mobile bar keeps
                   retained entries inspectable and Submit disabled. */
                retention: {
                    any: retainedKeys.length > 0,
                    count: retainedKeys.length,
                    keys: retainedKeys.slice()
                },
                /* T18: read-only request-latch state so the adapter's Submit
                   gates can never re-enable a request that is still in flight. */
                submissionBusy: mobileSubmissionInFlight === true,
                original: original,
                subjectInfoText: subjectInfoEl
                    ? snapshotText(subjectInfoEl).replace(/\n/g, ' ') : '',
                controls: {
                    subject: {
                        visible: snapshotShown(subjectSel),
                        value: subjectSel ? String(subjectSel.value || '') : '',
                        text: subjectOption ? snapshotText(subjectOption) : ''
                    },
                    slot: {
                        visible: snapshotShown(slotTriggerText),
                        text: snapshotText(slotTriggerText)
                    },
                    venue: {
                        visible: snapshotShown(venueLabel),
                        code: venueCode,
                        text: snapshotText(venueLabel)
                    },
                    week: {
                        visible: snapshotShown(weekSel),
                        value: weekSel ? String(weekSel.value || '') : '',
                        text: weekOption ? snapshotText(weekOption) : '',
                        index: weekIndex
                    }
                },
                tips: {
                    summary: {
                        visible: snapshotShown(summaryTipEl),
                        text: snapshotText(summaryTipEl),
                        tip: snapshotAttr(summaryTipEl, 'data-tip'),
                        ariaLabel: snapshotAttr(summaryTipEl, 'aria-label')
                    },
                    submit: {
                        visible: snapshotShown(submitTipEl),
                        text: snapshotText(submitTipEl),
                        tip: snapshotAttr(submitTipEl, 'data-tip'),
                        ariaLabel: snapshotAttr(submitTipEl, 'aria-label'),
                        disabled: submitBtn ? !!submitBtn.disabled : null
                    }
                }
            };
        }

        /* ── T10: mobile selection ports (the ONE canonical path) ──
           Resolve the real canonical cell the table's own handlers use, then
           call the existing toggleCell() exactly once. Availability, subject,
           lead-time, full-span, budget and day-end rules stay in that path —
           the adapter only decides WHETHER to ask, never how to book. */
        function chooseAvailable(dayIndex, hourIndex) {
            const day = Number(dayIndex);
            const hour = Number(hourIndex);
            if (!Number.isInteger(day) || day < 0) return false;
            if (!Number.isInteger(hour) || hour < 0) return false;
            /* T16: the mobile row may belong to a date that expired since the
               last settled read. Reconcile BEFORE resolving the cell; when the
               canonical week moved, this stale row is refused (no cross-date
               selection, no silent navigation of its own). */
            if (arrangementMobile && typeof arrangementMobile.isMobile === 'function'
                && arrangementMobile.isMobile()) {
                const weekBefore = weekNav ? Number(weekNav.currentWeek) : null;
                reconcileMobileClock();
                const weekAfter = weekNav ? Number(weekNav.currentWeek) : null;
                if (weekAfter !== weekBefore) return false;
            }
            const cell = document.querySelector(
                '#tableBody td[data-day="' + day + '"][data-hour="' + hour + '"] .cell-content'
            );
            if (!cell) return false;
            toggleCell(day, hour, cell);
            return true;
        }

        /* T10: mobile selection feedback is transient (5000ms) through the
           existing shared toast. Desktop wording/timing are untouched. */
        function showTransient(message) {
            toast.show(message, null, 5000);
        }

        /* T11: the information sheet's explicit Remove. It only forwards the
           exact venue/week identity the sheet displayed to the existing
           removeSavedBlock(); no new removal rule and no second control. */
        function removeSelection(venueKey, weekIndex) {
            const key = venueKey === null || venueKey === undefined ? '' : String(venueKey);
            const week = Number(weekIndex);
            if (!key) return false;
            if (!Number.isInteger(week) || week < 0) return false;
            removeSavedBlock(key, week);
            return true;
        }

        // Mount only after URL/intent, real-grid ghost checks and fallback prefill
        // have settled. T04 supplies the read-only snapshot; T06 activates layout.
        arrangementMobile = ArrangementMobile.create({
            root: document.getElementById('arrangementMobile'),
            ports: {
                readSnapshot: readArrangementSnapshot,
                /* T10: mobile tap -> the same canonical toggleCell/selectBlock
                   path the desktop row click uses. No second selection path. */
                chooseAvailable: chooseAvailable,
                /* T10: 5-second mobile transient via the shared toast manager. */
                showTransient: showTransient,
                /* T11: the sheet's Remove -> the existing removeSavedBlock()
                   path once, with the exact venue/week key shown. */
                removeSelection: removeSelection,
                /* T06: the page owns the two existing panel closers — the adapter
                   calls this port instead of synthesising broad DOM clicks. */
                closePanels: function () {
                    /* Only invoke the existing closers when a panel is actually
                       open; their removeEventListener calls otherwise count as
                       new removals on every resize even though nothing changed. */
                    const slotPanel = document.getElementById('slotPanel');
                    if (slotPanel && slotPanel.classList.contains('open')) closeSlotPanel();
                    if (venueDropdown && venueDropdown.container
                        && venueDropdown.container.classList.contains('open')
                        && typeof venueDropdown._close === 'function') {
                        venueDropdown._close();
                    }
                },
                /* T07: only default week move when the current week loses its
                   last eligible day. Reuses the existing navigator (its own
                   filters/guards); no venue choice and no booking write. */
                chooseWeek: function (index) {
                    if (!weekNav) return;
                    const target = Number(index);
                    if (!Number.isInteger(target) || target < 0) return;
                    weekNav.selectWeek(target);
                },
            },
        });
        arrangementMobile.mount();
        /* T16: the page owns the live clock lifecycle — one next-local-midnight
           timeout plus visibility/focus listeners, each calling the SAME guarded
           reconcile. The adapter adds no timer/observer for this; every path
           no-ops on desktop and after the first pass on a given local date.
           T19: the listeners are named so its pagehide teardown can remove
           exactly these two and clear exactly this one timeout. */
        document.addEventListener('visibilitychange', onMobileClockVisibility);
        window.addEventListener('focus', onMobileClockFocus);
        window.addEventListener('pagehide', onArrangementPageHide);
        scheduleMobileClock();
        });
@endsection
