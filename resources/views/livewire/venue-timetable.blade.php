{{-- Livewire full-page view (SDD: venue-timetable-db; rewritten to event blocks
     by venue-event-blocks-db). Real slot-level data feeds the shared grid
     engine in cellRender mode — the frozen two-axis block language (colour =
     status, border = ownership). READ-only v1 (booking = Slice B). Legacy mock
     template untouched for the D10 fallback. Week nav is client-side (Slice A
     pattern); venue switching navigates `?venue=` (full render, scripts re-run).

     cohort-venue-responsive-port PHASE 2 — dated 2026-11-17 — the page root carries the
     shared `.tt-responsive` scope with pageKey "venue", so TimetableResponsive
     (public/js/ui-common.js) owns the narrower bands — one-day window below
     768, two-day window 768–1023, three-day window 1024–1279 (bands.phoneMode
     'window': the SAME shared _buildWindow renders the bookable grid at every
     responsive width), and the existing full-week desktop at ≥1280 on BOTH
     pointers (no wide-touch band on this page). The Week summary sheet holds
     the legend + the five cards below 1280 (partials/ui-tt-chrome); the room
     row sits OUTSIDE the chrome's sticky block so it scrolls away while the
     week/day controls stay pinned. The free cells are rendered by the SAME
     live state closures the desktop cellRender uses (roomCellState /
     isServedTail / roomBeforeNextEvent / cellBlockedByLive / isSlotTooSoon),
     and booking still goes through the live available-tooltip → Select a
     Session modal → arrangement handoff — the prototype's mock preview and its
     session-sheet restyle are NOT ported (approved proposal §3 exception). --}}

@section('title', 'Venue Timetable')

<div class="lw-page tt-responsive" data-tt-page="venue">

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
        /* ───── cohort-venue-responsive-port PHASE 2: the toolbar bands ─────
           dated 2026-11-17.
           The prototype's `.venue-toolbar` shape: the room row and the week
           controls are SEPARATE rows below 1280 (the room row scrolls away, the
           week/day row stays pinned by the chrome's sticky block), and ≥1280 the
           ONE desktop bar is reassembled — the wrapper takes the shipped
           .semester-bar card skin and both rows dissolve with `display:
           contents`, so their children auto-place into the same FIVE columns
           (2026-11-17 ruling: venue wrap | week nav | Today | Earliest | print,
           print pinned to the right edge). The chrome's #ttSticky carries the
           week controls + the day strip at every width; at ≥1280 the strip is
           display:none (theme base rule) and nothing of the responsive lane
           renders. The wrapper carries the surface the old .semester-bar--venue
           had (padding 12px 16px, surface card, gap 8) — desktop pixels
           unchanged. */
        .tt-venue-bar .semester-bar--venue {
            margin: 0;
        }
        @media (min-width: 1280px) {
            .tt-responsive[data-tt-page="venue"] .tt-venue-bar {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto auto auto minmax(0, 1fr);
                align-items: center;
                gap: 8px;
                padding: 12px 16px;
                background: var(--color-surface);
                border: 1px solid var(--color-outline);
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-sm);
            }
            .tt-responsive[data-tt-page="venue"] .tt-venue-bar .venue-room-row,
            .tt-responsive[data-tt-page="venue"] .tt-venue-bar .tt-sticky,
            .tt-responsive[data-tt-page="venue"] .tt-venue-bar .venue-controls-bar {
                display: contents;
            }
            .tt-responsive[data-tt-page="venue"] .tt-venue-bar .venue-dropdown-wrap {
                justify-self: start;
            }
            .tt-responsive[data-tt-page="venue"] .tt-venue-bar .print-btn {
                justify-self: end;
            }
        }
        /* Below 1280 the room row is a plain block (the prototype's
           .venue-preview-room: no card, the trigger fills the row) and the
           controls bar wraps — the week row takes its own line, Today / Earliest
           / Print share the next one. */
        @media (max-width: 1279px) {
            .tt-responsive[data-tt-page="venue"] .venue-room-row {
                background: transparent;
                border: 0;
                box-shadow: none;
                border-radius: 0;
                padding: 0;
                margin: 0 0 8px;
                flex-wrap: wrap;
                gap: 8px;
            }
            .tt-responsive[data-tt-page="venue"] .venue-room-row .venue-dropdown-wrap {
                flex: 1;
                min-width: 0;
            }
            /* the dropdown fills the free row width; the capacity/description
               chips keep their own content width beside it (prototype row) */
            .tt-responsive[data-tt-page="venue"] .venue-room-row .venue-dd {
                flex: 1;
                min-width: 0;
            }
            .tt-responsive[data-tt-page="venue"] .venue-room-row .venue-dd-trigger {
                width: 100%;
                min-height: 44px;
                display: flex;
                gap: 16px;
                align-items: center;
                justify-content: space-between;
            }
            .tt-responsive[data-tt-page="venue"] .venue-room-row #venueLabel {
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .tt-responsive[data-tt-page="venue"] .tt-sticky .venue-controls-bar {
                flex-wrap: wrap;
                row-gap: 6px;
            }
            .tt-responsive[data-tt-page="venue"] .tt-sticky .venue-controls-bar .week-nav {
                flex: 1 1 100%;
            }
        }
        /* Venue keeps Print at 768–1279 (proposal §2: hide it below 768
           ONLY — the shared ≤1279 rule hides it for the personal pages).
           Page-scoped so the shared rule's intent stays untouched. */
        @media (min-width: 768px) and (max-width: 1279px) {
            .tt-responsive[data-tt-page="venue"] .tt-sticky .print-btn {
                display: inline-flex;
                margin-left: auto;
            }
        }
        /* the desktop booking-hint line: the count row carries the suggestion
           below 1280, so the line would be a second one */
        @media (max-width: 1279px) {
            .tt-responsive[data-tt-page="venue"] .booking-hint-line {
                display: none;
            }
        }
        @media (max-width: 767px) {
            .tt-responsive[data-tt-page="venue"] .tt-sticky .print-btn {
                display: none;   /* phone only: the browser's own print menu */
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

        /* ───── PHASE 2, dated 2026-11-17: the responsive free cells (window band) ─────
           The SAME live cell-state vocabulary the desktop cellRender paints, in
           the prototype's `.venue-slot` shapes. Every class reuses the shipped
           vocabulary — `.cell-content` / `.cell-available` / `.cell-no-room` /
           `.cell-held` / `.cell-too-soon` (their hover-label rules in this
           page's own styles and in theme.css apply unchanged) — with three
           page-scoped additions: the 18:30 tail (`Not Enough Slots`, plural, on
           hover AND focus, activation blocked), the base-availability offer
           under cancelled history (S20's timeline answer), and the shipped red
           off-day column. Tokens only. */
        /* the free cells fill their background cell (the desktop .cell-content
           positioning needs a positioned ancestor — the module's grid cell) */
        .tt-responsive[data-tt-page="venue"] .tt-gcell {
            position: relative;
        }
        /* Sunday / public-holiday empties: the shipped red day column
           (prototype `.venue-slot.off-day`), never bookable, never relabelled. */
        .tt-responsive[data-tt-page="venue"] .v-off {
            background: var(--color-error-container);
            color: var(--color-on-error-container);
            cursor: var(--cursor-cancel);
            pointer-events: none;
        }
        .tt-responsive[data-tt-page="venue"] .v-off-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        /* The 18:30 half-hour (proposal §3): green but it cannot START a
           booking — 'Not Enough Slots' (plural, prototype words) on hover AND
           focus, aria-disabled in the markup, activation blocked in JS. The
           desktop's mid-day `.cell-no-room` law (green + cursor + hover words
           only) is untouched — this arm is the tail only. */
        .tt-responsive[data-tt-page="venue"] .v-tail {
            --hover-label: 'Not Enough\A Slots';
            white-space: pre-line;
            cursor: not-allowed;
        }
        .tt-responsive[data-tt-page="venue"] .v-tail:hover { filter: none; }
        .tt-responsive[data-tt-page="venue"] .v-tail:focus-visible { filter: none; }
        /* the hover label also answers the keyboard (prototype: hover/focus) */
        .tt-responsive[data-tt-page="venue"] .cell-available:focus-visible::after,
        .tt-responsive[data-tt-page="venue"] .v-tail:focus-visible::after,
        .tt-responsive[data-tt-page="venue"] .cell-too-soon:focus-visible::after {
            opacity: 1;
        }
        /* cancelled-slot-reuse S20 in the timeline (followup F): a free cell
           under cancelled history paints in the module's RESERVED SIDE LANE
           (tt-base-lane) — the node's own left/right inline geometry puts it
           beside, never over, the history card. The page styles only the
           offer's time label here. */
        .tt-responsive[data-tt-page="venue"] .v-base-offer .cell-time-label {
            display: block;
            font-size: 11px;
            font-weight: 600;
            line-height: 1.2;
            text-align: center;
            padding: 2px;
            overflow: hidden;
        }
        /* No hover on a touch screen (standing rule): the reused hover skins
           stop listening to the pointer; focus keeps the same words. The
           chrome's hover skins are theme.css shared rules, so their
           neutralisers ride this page (same recipe as the Cohort port) — the
           resting states keep their colours, nothing goes transparent. */
        @media (hover: none) {
            .tt-responsive[data-tt-page="venue"] .cell-available:hover {
                filter: none;
                z-index: auto;
            }
            .tt-responsive[data-tt-page="venue"] .cell-available:hover::after,
            .tt-responsive[data-tt-page="venue"] .cell-too-soon:hover::after {
                opacity: 0;
            }
            .tt-responsive[data-tt-page="venue"] .cell-available:active {
                transform: none;
            }
            .tt-responsive[data-tt-page="venue"] .tt-summary-btn:hover { background: var(--color-surface); }
            .tt-responsive[data-tt-page="venue"] .week-arrow:hover { background: transparent; color: var(--color-on-surface-variant); }
            .tt-responsive[data-tt-page="venue"] .today-btn:hover,
            .tt-responsive[data-tt-page="venue"] .print-btn:hover { opacity: 1; transform: none; filter: none; background: transparent; color: var(--color-on-surface-variant); }
            .tt-responsive[data-tt-page="venue"] .timetable tbody td:hover,
            .tt-responsive[data-tt-page="venue"] .timetable td.hour-cell:hover { background: transparent; }
            .tt-responsive[data-tt-page="venue"] .event-block:hover { filter: none; box-shadow: none; }
            .tt-responsive[data-tt-page="venue"] .summary-card:hover .summary-card-inner { transform: none; }
            .tt-responsive[data-tt-page="venue"] .legend-item:hover { background: transparent; }
        }

        /* ───── 48 × 48 on touch, capability not width (the ONE gap the touch
           walk found): the room picker trigger is 36px by the shared skin and
           44px by this page's ≤1279 row rule — on a coarse pointer it grows to
           the floor at EVERY width, wide tablets included, while a mouse
           desktop keeps today's compact 36px. The picker sheet's own controls
           (close, search, group headers, venue rows) take the same floor. */
        @media (any-pointer: coarse) {
            .tt-responsive[data-tt-page="venue"] .venue-room-row .venue-dd-trigger,
            .tt-responsive[data-tt-page="venue"] .tt-venue-bar .venue-dd-trigger {
                min-height: 48px;
                height: 48px;
            }
            dialog#venueTimetablePicker button {
                box-sizing: border-box;
                min-width: 48px;
                min-height: 48px;
            }
            /* the search field outranks the generic input rule on specificity,
               so it carries the floor under its own selector */
            dialog#venueTimetablePicker .venue-picker-search input {
                min-width: 48px;
                min-height: 48px;
            }
        }

        /* ───── The 19:00 boundary row (proposal §3: exactly 22 half-hours) ───── */
        .tt-responsive[data-tt-page="venue"] .v-end-row { display: contents; }
        .tt-responsive[data-tt-page="venue"] .v-end-time {
            border-bottom: 0;
            color: var(--color-on-surface-variant);
        }
        .tt-responsive[data-tt-page="venue"] .v-end-line { background: var(--color-surface); }
        /* the extra 24px footer row + the prototype's 12px axis — the phone
           band is the SAME window grid now, so one rule covers all bands */
        @media (max-width: 1279px) {
            .tt-responsive[data-tt-page="venue"] .tt-window-grid {
                grid-template-rows: 62px repeat(22, 44px) 24px;
            }
            .tt-responsive[data-tt-page="venue"] .tt-axis { font-size: 12px; }
        }
        @media (any-pointer: coarse) {
            .tt-responsive[data-tt-page="venue"] .tt-window-grid {
                grid-template-rows: 62px repeat(22, 48px) 24px;
            }
        }

        /* the window header's day-state badges (followup D): the shipped
           badge family under the date, centred like the prototype's */
        @media (max-width: 1279px) {
            .tt-responsive .tt-window-grid .tt-whead .v-daybadges {
                display: block;
            }
            .tt-responsive .tt-window-grid .tt-whead .today-badge,
            .tt-responsive .tt-window-grid .tt-whead .off-badge,
            .tt-responsive .tt-window-grid .tt-whead .bookable-badge {
                margin-top: 4px;
                margin-left: auto;
                margin-right: auto;
                max-width: 90%;
                line-height: 1.3;
            }
        }

        /* the tools row: count + Summary only — the selected date / window range is
           NEVER repeated above the grid at ANY responsive width (the strip and the
           grid headers already name the days; the count line speaks the window).
           Prototype contract (venue-timetable-responsive-preview.html lines 20–28).
           The module still writes its window heading; the page hides it below 1280
           with CSS, so no JS fixups are needed. (Was phone-only 767 before the
           contract-finish check caught the tablet windows repeating the range.) */
        @media (max-width: 1279px) {
            .tt-responsive[data-tt-page="venue"] .tt-day-heading {
                display: none;
            }
        }

        /* ───── The whole-day pulse (Earliest bookable green / Today blue) ─────
           The shipped mechanic: `flashRowPulse`'s .bookable-flash classes over
           the theme.css keyframes. The desktop rows already pulse through the
           shared rules; the responsive day columns take the same classes on the
           tagged background cells (.tt-gcell[data-v-day]) and their green cells. */
        .tt-responsive[data-tt-page="venue"] .tt-gcell.bookable-flash {
            animation: bookable-pulse 0.5s ease-in-out 3;
        }
        .tt-responsive[data-tt-page="venue"] .tt-gcell.bookable-flash--today {
            animation: bookable-pulse-primary 0.5s ease-in-out 3;
        }
        .tt-responsive[data-tt-page="venue"] .cell-content.bookable-flash--today {
            animation: bookable-pulse-primary 0.5s ease-in-out 3;
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
    <!-- ─── Responsive chrome + toolbar (cohort-venue-responsive-port PHASE 2,
         dated 2026-11-17) ───
         The prototype's `.venue-toolbar` shape: the room row and the week
         controls are separate rows below 1280 (the room row scrolls away, the
         week/day row stays pinned inside the chrome's sticky block), and ≥1280
         the wrapper reassembles the ONE desktop bar via display:contents —
         same FIVE columns, same card skin (the page styles own the swap). The
         week row is RENDERED to a string and handed to partials/ui-tt-chrome
         (a static wrap in the blade, never a runtime move; ui-week-nav itself
         is never edited — same pattern as My Timetable and Cohort). Every
         control in the chrome is bound by TimetableResponsive; no inline
         onclick is added. -->
    @php
        $ttWeekNav = '<div class="semester-bar venue-controls-bar">'
            . view('partials.ui-week-nav', [
                'prevOnclick' => 'prevWeek()',
                'nextOnclick' => 'nextWeek()',
                'selectId' => 'weekSelect',
                'selectOnclick' => 'selectWeek(this.value)',
                'selectTinted' => true,
                'showPrint' => true,
                'showEarliestBtn' => true,
            ])->render()
            . '</div>';
    @endphp
    <div class="tt-venue-bar">
        {{-- The ROOM ROW — the part that scrolls away below 1280. Same
             includes and chips as before; the week nav moved into the chrome
             above, the desktop grid reassembles the bar. --}}
        <div class="semester-bar semester-bar--venue venue-room-row">
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
        </div>
        @include('partials.ui-tt-chrome', ['ttWeekNav' => $ttWeekNav, 'ttReadonly' => false])
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
    ])
    @include('partials.ui-print-sheet', ['printOwnership' => true])
    @include('partials.ui-print-modal')
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
            /* PHASE 2 (cohort-venue-responsive-port): ONE set of per-render facts
               shared by the desktop grid AND the responsive chrome — the base-
               availability days (cancelled-slot-reuse S20) and the ROOM-AWARE
               earliest bookable day. Recomputed in buildTimetable(), read by the
               module hooks and the buttons; never a second availability logic. */
            let desktopBaseRowDays = [];
            let responsiveEarliest = null;

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

            /* PHASE 2 (proposal §2): the picker follows the CURRENT behaviour —
               the searchable sheet on a TOUCH-capable device at ANY width (the
               iPad Pro never requires hover) or at ≤768px, and the anchored
               four-column cascade for a mouse. Input capability, not width
               alone: `any-pointer: coarse` / `hover: none` decide, exactly like
               the prototype's useVenueSheet(). */
            const venueCoarseQuery = window.matchMedia('(any-pointer: coarse)');
            const venueNoHoverQuery = window.matchMedia('(hover: none)');
            function useVenueSheet() {
                return window.innerWidth <= 768 || venueCoarseQuery.matches || venueNoHoverQuery.matches;
            }
            function syncVenuePickerMode() {
                const trigger = document.querySelector('#venueSelectDropdown .venue-dd-trigger');
                if (trigger) trigger.setAttribute('aria-haspopup', useVenueSheet() ? 'dialog' : 'true');
            }
            syncVenuePickerMode();

            function openVenuePicker() {
                const dialog = document.getElementById('venueTimetablePicker');
                if (!dialog || !useVenueSheet()) return;
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
                   listener, so a phone/touch tap never opens the desktop
                   cascade — and a WIDE touch device gets the sheet too. */
                document.addEventListener('click', function (event) {
                    if (!useVenueSheet()) return;
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

                /* leaving the sheet mode (touch removed / grown past 768 on a
                   mouse) closes the dialog and hands back the cascade — the
                   same mode sync the prototype runs on resize + query change */
                const venueSheetStops = function () {
                    if (window.innerWidth > 768 && !venueCoarseQuery.matches && !venueNoHoverQuery.matches) closeVenuePicker();
                    syncVenuePickerMode();
                };
                window.addEventListener('resize', venueSheetStops);
                [venueCoarseQuery, venueNoHoverQuery].forEach(function (q) {
                    if (q.addEventListener) q.addEventListener('change', venueSheetStops);
                    else if (q.addListener) q.addListener(venueSheetStops);
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
            function roomCellState(di, hi, weekIdx) {
                /* PHASE 2 (room-aware earliest): the optional week index lets the
                   Earliest-bookable lookup scan the WHOLE semester without
                   mutating the viewed week; every existing caller omits it and
                   reads the current week exactly as before. */
                const week = roomCellStatesByWeek[weekIdx === undefined ? currentWeek : weekIdx];
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
            function nextActualBlockAfter(di, hi, weekIdx) {
                const last = lastOfferIndex(di, weekIdx);
                for (let k = hi + 1; k <= last; k++) {
                    if (roomCellState(di, k, weekIdx) !== 'free') return k;
                }
                return Infinity;
            }

            /* The LAST half-hour of this day that has a real room row. A `blocked`
               (held) row counts as served on purpose: it is a real row, just not
               free, so it must end a run and must not extend the tail. -1 = the
               day offers nothing at all. */
            function lastOfferIndex(di, weekIdx) {
                const week = roomCellStatesByWeek[weekIdx === undefined ? currentWeek : weekIdx];
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
            function isServedTail(di, hi, weekIdx) {
                const last = lastOfferIndex(di, weekIdx);
                return last >= hours.length - 3 && hi > last;
            }

            function nextEventStartAfter(di, hi, weekIdx) {
                const dayEvents = eventsData[weekIdx === undefined ? currentWeek : weekIdx] || [];
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
            function spanBlocked(di, hi, spanSlots, weekIdx) {
                const wk = weekIdx === undefined ? currentWeek : weekIdx;
                const spanTo = Math.min(hi + spanSlots, hours.length);
                for (let k = hi; k < spanTo; k++) {
                    if (roomCellState(di, k, wk) !== 'free') return true;
                }
                return (eventsData[wk] || []).some(function (ev) {
                    return ev.di === di && !isHistoryEvent(ev)
                        && (hi < ev.end + 1) && (spanTo - 1 >= ev.start);
                });
            }

            /* Does a LIVE (non-cancelled) event occupy one cell? Real room
               occupancy — pending and occupied still block; cancelled history
               never does (S20). */
            function cellBlockedByLive(di, hi, weekIdx) {
                return (eventsData[weekIdx === undefined ? currentWeek : weekIdx] || []).some(function (ev) {
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
            function roomBeforeNextEvent(di, hi, weekIdx) {
                const next = Math.min(nextEventStartAfter(di, hi, weekIdx), nextActualBlockAfter(di, hi, weekIdx), hours.length);
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

            /* ═══ cohort-venue-responsive-port PHASE 2 (dated 2026-11-17) — the responsive
               bands ═══
               The module (TimetableResponsive, ui-common.js) owns the chrome;
               this page supplies the opt-in profile and the LIVE cell-state
               hooks. The state closures are the DESKTOP ones — roomCellState /
               isServedTail / roomBeforeNextEvent / cellBlockedByLive /
               isSlotTooSoon / dayNeedsBaseRow — never a second calculation, so
               the green cells and the Available card can never disagree.
               Booking still goes through the live available-tooltip →
               Select a Session modal → arrangement handoff (the approved
               proposal keeps the shipped modal; the prototype's mock preview
               and session-sheet restyle are NOT ported). */

            /* The too-soon hover words — one composition, two consumers (the
               desktop paint loop above and the responsive cells). */
            function tooSoonTipText() {
                const cutoff = leadTimeCutoff(weekData);
                const days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                return 'Rule : Only bookable at least 3 working days from today.'
                    + '\nEarliest Bookable : ' + days[cutoff.getDay()] + ', '
                    + cutoff.getDate() + ' ' + months[cutoff.getMonth()] + ' ' + cutoff.getFullYear();
            }

            /* ── The ROOM-AWARE earliest bookable lookup (PHASE 2) ──
               firstBookableDay(weekData) is the LEAD-TIME cutoff only (ui-common
               :160–205) — it cannot see the room, so it can name a day whose
               room is fully held: the green dot would sit on a day with nothing
               to book and the jump would land on a full day. The prototype and
               the approved plan demand ONE genuinely free slot lookup for the
               dot AND the button. This lookup reuses the page's own live
               closures — roomCellState / isServedTail / cellBlockedByLive /
               spanBlocked / isSlotTooSoon, with their new optional week index —
               never a second availability calculation. No backend rule changes;
               the room matrix and event payload stay the page's truth. */
            function firstBookableSlotInWeek(weekIdx) {
                for (var di = 0; di < 7; di++) {
                    var day = (weekData[weekIdx] && weekData[weekIdx].days[di]) || null;
                    if (!day || day.sunday || day.holiday) continue;
                    if (isSlotTooSoon(weekData, weekIdx, di)) continue;
                    for (var slot = 0; slot < hours.length; slot++) {
                        var state = roomCellState(di, slot, weekIdx);
                        /* the same two green states the cells paint: a real free
                           offer, or the evening tail (isServedTail) */
                        var bookable = state === 'free'
                            || (state === 'nooffer' && isServedTail(di, slot, weekIdx));
                        if (!bookable) continue;
                        if (cellBlockedByLive(di, slot, weekIdx)) continue;
                        /* the slot must host the MINIMUM booking the page offers
                           (the hover floor): 60 continuous minutes of free room,
                           judged by the SAME span check the pop-up's Arrange
                           press runs (cancelled history never blocks, S20) */
                        if (spanBlocked(di, slot, MIN_BOOK_SLOTS, weekIdx)) continue;
                        return { di: di, slot: slot };
                    }
                }
                return null;
            }
            function venueEarliestBookable() {
                for (var week = 0; week < (weekData || []).length; week++) {
                    var found = firstBookableSlotInWeek(week);
                    if (found) return { week: week, day: found.di, slot: found.slot };
                }
                return null;
            }

            /* ── The responsive free cell — ONE state machine, two surfaces ──
               The window-band hook (cfg.freeCell) and the phone timeline both
               render through this. Mirrors the desktop cellRender's available
               branches arm for arm; a class reuses the shipped vocabulary so
               the page's own hover-label rules and theme.css apply unchanged:
                 free + room  → .cell-content .cell-available (+ .cell-no-room
                                when roomBeforeNextEvent < MIN_BOOK_SLOTS — the
                                current live law: green, cursor + hover words)
                 18:30 tail   → .v-tail: green, 'Not Enough Slots' (plural, the
                                prototype's words) on hover AND focus, marked
                                aria-disabled, activation blocked (proposal §3)
                 too soon     → .cell-too-soon + the shared rule words
                 Sunday / PH  → .v-off: the shipped red day column ('OFF'/'PH')
                 held (A21)   → .cell-content .cell-held (neutral, never fake
                                green for a raw occupied row)
                 no offer row → nothing (the bare background cell)
               A live event's cell → nothing: its card covers the column there,
               exactly like the desktop lane-0 branch.
               The AVAILABLE count is NOT touched here: countedAvailable stays
               the desktop paint loop's single counting point (user accuracy
               2026-10-19), so the card can never disagree with the screen. */
            function venueSlotNode(di, slot, day, opts) {
                opts = opts || {};
                if (!day) return null;
                if (day.sunday || day.holiday) {
                    var off = document.createElement('div');
                    off.className = 'cell-content v-off';
                    var offLabel = document.createElement('span');
                    offLabel.className = 'v-off-label';
                    offLabel.textContent = day.holiday ? 'PH' : 'OFF';
                    off.appendChild(offLabel);
                    return off;
                }
                if (isSlotTooSoon(weekData, currentWeek, di)) {
                    var soon = document.createElement('div');
                    soon.className = 'cell-content cell-too-soon';
                    soon.setAttribute('data-tip', tooSoonTipText());
                    return soon;
                }
                var cellState = roomCellState(di, slot);
                var tail = cellState === 'nooffer' && isServedTail(di, slot);
                if (cellState !== 'free' && !tail) {
                    if (cellState === 'blocked') {
                        var held = document.createElement('div');
                        held.className = 'cell-content cell-held';
                        return held;
                    }
                    return null;
                }
                if (cellBlockedByLive(di, slot)) return null;   /* its card covers it */

                var node = document.createElement('div');
                node.className = 'cell-content cell-available';
                var isTailSlot = slot === hours.length - 1;
                if (isTailSlot) {
                    node.classList.add('v-tail');
                    node.setAttribute('aria-disabled', 'true');
                } else if (roomBeforeNextEvent(di, slot) < MIN_BOOK_SLOTS) {
                    node.classList.add('cell-no-room');   /* green, words-only — the 2026-11-05 law */
                }
                /* Followup F: a free cell under cancelled history paints in the
                   RESERVED SIDE LANE the module marks on the gcell (tt-base-lane
                   + data-tt-base=1). The gcell's shared paddingLeft inset is
                   ignored by an absolute inset-0 fill, so the node takes the
                   lane's own geometry — from the lane edge to the cell's right
                   edge — so the history card (in the remaining sub-lanes) and
                   the green offer are BOTH visible and BOTH reachable. The
                   offer keeps the desktop base-row's time label. */
                if (opts && opts.base) {
                    node.classList.add('v-base-offer');
                    var laneCount = Math.max(1, (opts.laneCount !== undefined ? opts.laneCount : ((opts.lanes || 0) + 1)));
                    var lane = (opts.lane !== undefined) ? opts.lane : (opts.lanes || 0);
                    node.style.left = 'calc(100% * ' + lane + ' / ' + laneCount + ')';
                    node.style.right = '0';
                    var baseTime = document.createElement('span');
                    baseTime.className = 'cell-time-label';
                    baseTime.textContent = to12h(hours[slot]) + ' ~ ' + to12h(hours[slot + 1] || add30min(hours[slot]));
                    node.appendChild(baseTime);
                }
                node.tabIndex = 0;
                /* BOTH coordinate contracts: the desktop page's data-day/
                   data-hour pair and the prototype/tests' data-free-day/
                   data-free-slot pair (tests/cohort-venue-responsive.spec.ts SEAM). */
                node.dataset.day = String(di);
                node.dataset.hour = String(slot);
                node.dataset.freeDay = String(di);
                node.dataset.freeSlot = String(slot);
                node.setAttribute('role', 'button');
                node.setAttribute('aria-label', 'Available slot: ' + day.abbr + ' ' + hours[slot]
                    + (isTailSlot ? ' — Not Enough Slots' : ''));
                node.addEventListener('focus', function () { focusedCell = node; });
                if (isTailSlot) {
                    /* activation blocked: no toast, no modal, no session picker */
                    node.addEventListener('click', function (ev) { ev.preventDefault(); });
                } else {
                    node.addEventListener('click', function (ev) {
                        focusedCell = node;
                        showAvailableTooltip(ev, di, slot, node);
                    });
                }
                return node;
            }

            /* ── The 19:00 boundary row (exactly 22 half-hours before it) ──
               One display:contents wrapper so its two children become the
               footer grid row's cells on BOTH surfaces. */
            function venueEndRow() {
                var wrap = document.createElement('div');
                wrap.className = 'v-end-row';
                var axis = document.createElement('div');
                axis.className = 'tt-axis v-end-time';
                axis.style.gridColumn = '1';
                axis.style.gridRow = String(hours.length + 2);
                axis.textContent = '19:00';
                var line = document.createElement('div');
                line.className = 'v-end-line';
                line.style.gridColumn = '2 / -1';
                line.style.gridRow = String(hours.length + 2);
                line.setAttribute('aria-hidden', 'true');
                wrap.appendChild(axis);
                wrap.appendChild(line);
                return wrap;
            }

            /* ── The card voice — the SAME ownership/status/tip law as the
               desktop blocks (§10.0 two-axis language + the privacy gates) ── */
            function venueStatusClassFor(div, e, isHoliday) {
                if (isHoliday) {
                    /* PH day: the viewer's OWN class stays loud red, others'
                       hairline — the desktop cellRender's T21 branch */
                    div.classList.add(e.mine ? 'event-conflict' : 'event-public-holiday');
                } else if (e.status === 'replacement') {
                    div.classList.add(e.mine ? 'event-mine-replacement' : 'event-others-replacement');
                } else if (e.status === 'pending') {
                    div.classList.add(e.mine ? 'event-mine-pending' : 'event-others-pending');
                } else if (e.status === 'conflict' || e.status === 'cancelled') {
                    div.classList.add(e.mine ? 'event-conflict' : 'event-public-holiday');
                } else {
                    div.classList.add(e.mine ? 'event-mine' : 'event-others');
                }
            }
            function venueTipLecturer(e) {
                return (e.mine === true || e.lecturer === MockData.currentUser.name) ? 'YOU' : (e.lecturer || '—');
            }
            function venueTooltipExtra(e) {
                return (e.cohorts && e.cohorts.length) ? e.cohorts.join(' + ') : (e.cohort || '');
            }
            function venueReplacementNote(e) {
                return buildReplacementNote(e, {
                    checkOwnership: function (ev) {
                        return ev.mine === true || ev.lecturer === MockData.currentUser.name;
                    }
                });
            }

            /* ── The window header's day-state badge (followup D) ──
               cfg.dayBadgeFn(day, di) feeds the module's transposed header; the
               page supplies the SHIPPED badge family under the date. The holiday
               small is the module's own — no duplicate PH text here. TODAY comes
               from the strip's aria-current="date" (the module's own truth, set
               only on the real today); BOOKINGS OPEN comes from the page's ONE
               room-aware earliest lookup (the same answer the strip's green dot
               and the Earliest bookable button use). */
            function venueDayBadge(day, di) {
                var wrap = document.createElement('span');
                wrap.className = 'v-daybadges';
                var week = window.currentWeek;
                var isEarliest = !!(responsiveEarliest
                    && responsiveEarliest.week === week
                    && responsiveEarliest.day === di);
                var todayChip = document.querySelector('#ttDayStrip .tt-day[data-day="' + di + '"][aria-current="date"]');
                if (todayChip) {
                    var b = document.createElement('span');
                    b.className = 'today-badge';
                    b.textContent = 'Today';
                    wrap.appendChild(b);
                } else if (day && day.sunday) {
                    var o = document.createElement('span');
                    o.className = 'off-badge';
                    o.textContent = 'OFF';
                    wrap.appendChild(o);
                }
                if (isEarliest) {
                    var e = document.createElement('span');
                    e.className = 'bookable-badge';
                    e.textContent = 'Bookings open';
                    wrap.appendChild(e);
                }
                return wrap.childNodes.length ? wrap : null;
            }

            /* ── The tools row words (followup C) ──
               cfg.countLine(mode, size) runs on EVERY _syncTools — sync, day
               tap, resize — so the count can never be overwritten by the
               module's fallback. The prototype keeps the CLASS count of the
               visible window next to the Summary button, and on an empty-but-
               bookable window the booking suggestion, decided from the SAME
               state closures — never suggested on a Sunday, holiday or too-soon
               window. The selected date is never repeated above the grid (the
               strip and the grid header name it) — the heading's suppression is
               the page CSS's, phone band only. */
            function venueSelectedDay() {
                var chip = document.querySelector('#ttDayStrip .tt-day[aria-pressed="true"]');
                var d = chip ? parseInt(chip.getAttribute('data-day'), 10) : 0;
                return isNaN(d) ? 0 : d;
            }
            function venueVisibleBookable(visibleDays) {
                for (var i = 0; i < visibleDays.length; i++) {
                    var di = visibleDays[i];
                    var day = (weekData[currentWeek] && weekData[currentWeek].days[di]) || null;
                    if (!day || day.sunday || day.holiday) continue;
                    if (isSlotTooSoon(weekData, currentWeek, di)) continue;
                    for (var slot = 0; slot < hours.length; slot++) {
                        var state = roomCellState(di, slot);
                        if ((state === 'free' || (state === 'nooffer' && isServedTail(di, slot)))
                            && !cellBlockedByLive(di, slot)) return true;
                    }
                }
                return false;
            }
            function venueCountLine(mode, size) {
                if (!size || size >= 7) return null;   /* full week: the tools row is hidden */
                var start = Math.min(venueSelectedDay(), 7 - size);
                var visible = [];
                for (var i = 0; i < size; i++) visible.push(start + i);
                var events = eventsData[currentWeek] || [];
                var classCount = events.filter(function (e) { return e && visible.indexOf(Number(e.di)) !== -1; }).length;
                if (classCount) return classCount + (classCount === 1 ? ' class' : ' classes');
                return venueVisibleBookable(visible)
                    ? 'No classes · tap a green slot to arrange'
                    : 'No classes · no bookable slots';
            }

            /* ── The whole-day pulse (prototype §flash contract) ──
               Earliest bookable = green, Today = blue; the WHOLE day pulses in
               the responsive band (the desktop rows keep the shared
               flashRowPulse pulses). Same class vocabulary (flashRowPulse's
               .bookable-flash + the today arm), same 3 × 0.5s run, reflow
               restart on repeats. */
            var VENUE_PULSE_MS = 1650;   /* 3 × 0.5s + slack — flashRowPulse's own cap */
            function pulseNodes(nodes, tone) {
                nodes.forEach(function (el) {
                    el.classList.remove('bookable-flash', 'bookable-flash--today');
                    void el.offsetWidth;
                    el.classList.add('bookable-flash');
                    if (tone === 'today') el.classList.add('bookable-flash--today');
                });
                setTimeout(function () {
                    nodes.forEach(function (el) {
                        el.classList.remove('bookable-flash', 'bookable-flash--today');
                    });
                }, VENUE_PULSE_MS);
            }
            function flashResponsiveDay(di, tone, week) {
                if (typeof week === 'number' && window.currentWeek !== week) return null;
                var root = document.querySelector('.tt-responsive');
                if (!root || root.getAttribute('data-tt-mode') === 'full') return null;
                var sel = '[data-v-day="' + di + '"]';
                var nodes = Array.prototype.slice.call(
                    document.querySelectorAll('#ttWindowGrid ' + sel));
                var withCells = nodes.slice();
                nodes.forEach(function (cell) {
                    Array.prototype.push.apply(withCells,
                        Array.prototype.slice.call(cell.querySelectorAll('.cell-available')));
                });
                if (withCells.length) pulseNodes(withCells, tone);
                return withCells;
            }
            function focusResponsiveDayCell(di, slot) {
                var host = document.getElementById('ttWindowGrid');
                if (!host) return;
                var cell = (slot !== undefined && slot !== null)
                    ? (host.querySelector('[data-free-day="' + di + '"][data-free-slot="' + slot + '"]')
                        || host.querySelector('[data-free-day="' + di + '"]'))
                    : host.querySelector('[data-free-day="' + di + '"]');
                if (cell) {
                    cell.scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
                    try { cell.focus({ preventScroll: true }); } catch (e) {}
                } else {
                    host.scrollIntoView({ block: 'start', behavior: 'instant' });
                }
            }

            /* ── THE init — one cfg, idempotent, one week source ──
               Called from buildTimetable() after the desktop grid drew, so the
               module's closures hand back the SAME week. The phone band IS the
               shared _buildWindow now (bands.phoneMode 'window' — the shared
               followup): the same transposed bookable grid at width 1, no page
               renderer. sticky keeps the default (the week/day controls stay
               pinned; the room row sits OUTSIDE the chrome and scrolls away).
               emptyWeekPanel:false keeps the room's timeline and the desktop
               grid painting even on a week with zero class events (a free room
               is a bookable surface, not an empty state). */
            function syncResponsive() {
                if (!window.TimetableResponsive) return;
                TimetableResponsive.init({
                    scope: 'tt-responsive',
                    pageKey: 'venue',
                    sticky: true,
                    bands: { phoneMax: 767, tablet2Max: 1023, desktopMin: 1280, wideTouchDays: 0, phoneMode: 'window' },
                    emptyWeekPanel: false,
                    onEventClick: function (e) { openModal(e); },
                    events: function () { return eventsData[currentWeek] || []; },
                    days: function () { return (weekData[currentWeek] && weekData[currentWeek].days) || []; },
                    /* ONE eligibility lookup for the green dot AND the Earliest
                       bookable button — the ROOM-AWARE one computed in
                       buildTimetable() (the lead-time helper cannot see the
                       room). */
                    earliestDay: function () { return responsiveEarliest; },
                    statusClassFn: venueStatusClassFor,
                    tipLecturerFn: venueTipLecturer,
                    tooltipExtra: venueTooltipExtra,
                    replacementNoteFn: venueReplacementNote,
                    countLine: venueCountLine,
                    dayBadgeFn: venueDayBadge,
                    /* contract finish (2026-11-17): the strip chip's data-tip is ONLY
                       for the useful chips — Today, the room-aware Earliest bookable
                       day, and a holiday's name; an ordinary day names NO tooltip
                       (prototype lines 20–28). The aria-label is the module's own
                       and stays byte-identical. TODAY/earliest flags come from the
                       module's state; the holiday from the day payload — so a chip
                       that is Today AND earliest AND a holiday joins all three. */
                    dayTip: function (day, di, state) {
                        var parts = [];
                        if (state && state.today) parts.push('Today');
                        if (state && state.earliest) parts.push('Earliest bookable day for this venue');
                        if (day && day.holiday) parts.push(day.holidayLabel || 'Public Holiday');
                        return parts.join(' · ');
                    },
                    /* the desktop S20 base days — the module reserves the side
                       lane beside the cancelled history there */
                    baseRowDays: function () { return desktopBaseRowDays; },
                    freeCell: function (di, slot, day, gcell, ctx) {
                        /* the tag rides EVERY background cell, so the whole-day
                           pulse reaches the occupied cells' columns too */
                        if (gcell && gcell.dataset) gcell.dataset.vDay = String(di);
                        return venueSlotNode(di, slot, day, ctx || {});
                    },
                    windowFooter: venueEndRow
                });
            }

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
                    build: function (state) {
                        /* AMENDMENT 1 §A6: the venue gains the semester union. Frozen
                           design §3.3 gave it none ("one teaching week IS the semester's
                           pattern") because its normal view printed the CURRENT week
                           filtered — but a dropdown labelled All Weeks cannot print one
                           week: that would be a lie in the heading. Frozen PR-16 already
                           demanded a deduplicated union on the sheet, so the union is
                           what the spec always wanted. A room's whole-semester occupancy
                           is exactly what a room sheet is for; a class meeting on
                           alternate weeks prints once. */
                        var all = state.week === null;
                        var wk = all ? currentWeek : state.week;
                        var sem = MockData.semester.weeks;
                        var weekEvents = (eventsData[wk] || []);
                        return {
                            events: all
                                ? printSemesterSet(eventsData)
                                : printWeekSet(weekEvents, state.normal),
                            days: all ? printNormalDays() : weekData[wk].days,
                            weekCount: all
                                ? 'Week 1 ~ ' + sem
                                : 'Week ' + (wk + 1) + ' of ' + sem,
                            weekDates: all ? '(Whole Semester)' : ' · ' + weekData[wk].range,
                            emptyTitle: all || state.normal ? 'No normal classes.' : 'No classes this week.',
                            emptyText: all
                                ? 'This venue carries no normal class in the semester.'
                                : (state.normal
                                    ? 'Week ' + (wk + 1) + ' carries no normal class in this venue.'
                                    : 'This venue is free all week.'),
                        };
                    },
                    /* the dialog's week list, from THIS page's own week data (A4), so
                       every option carries the range the screen would show */
                    weeks: function () {
                        var out = [];
                        (weekData || []).forEach(function (w, i) {
                            out.push({ value: String(i), label: 'Week ' + (i + 1) + (w && w.range ? ' · ' + w.range : '') });
                        });
                        return out;
                    },
                    screenWeek: function () { return currentWeek; },
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
                   Computed once per render, not once per cell. PHASE 2: the
                   composition moved into tooSoonTipText() so the responsive
                   cells speak the same words from one source. */
                const tooSoonTip = tooSoonTipText();
                /* ── PHASE 2: the ROOM-AWARE earliest bookable day ──
                   firstBookableDay() (ui-common) is the LEAD-TIME cutoff only —
                   it can name a day whose room is fully held, so the green dot,
                   the "Bookings open" chip and the Earliest button would point
                   at a day with nothing to book. The prototype's rule: ONE
                   genuinely free slot lookup for all three. venueEarliest
                   Bookable() below reuses the page's own live closures (room
                   matrix, isServedTail, live blockers, the 60-minute minimum
                   span) — no backend rule change, no second availability logic.
                   The payload's lead-time flag is the NECESSARY half; the page
                   re-marks the flag with the room-aware answer so the desktop
                   chip, the dot and the jump can never disagree. */
                responsiveEarliest = venueEarliestBookable();
                (weekData || []).forEach(function (w) {
                    (w.days || []).forEach(function (d) { d.firstBookable = false; });
                });
                if (responsiveEarliest && weekData[responsiveEarliest.week]
                    && weekData[responsiveEarliest.week].days[responsiveEarliest.day]) {
                    weekData[responsiveEarliest.week].days[responsiveEarliest.day].firstBookable = true;
                }
                /* cancelled-slot-reuse S20: the base availability row is
                   offered ONLY where history would otherwise cover a
                   selectable free cell — decided from the real payload's
                   live statuses, never from the cancelled geometry alone. */
                desktopBaseRowDays = [];
                (weekData[currentWeek].days || []).forEach(function (_, di) {
                    if (dayNeedsBaseRow(di)) desktopBaseRowDays.push(di);
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
                    baseRowDays: desktopBaseRowDays,
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
                /* cohort-venue-responsive-port PHASE 2: the chrome re-renders for
                   the week that just landed — after the desktop build, so the
                   closures (events/days/state) hand the module the SAME week the
                   grid just drew. init() is idempotent (cfg replaced, listeners
                   bound once), so every week change re-enters safely. */
                syncResponsive();
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
            /* Contract finish 2026-11-17 (parent-confirmed V9 bug): the recalled week
               (selector says 6) must ALSO bridge to the shared responsive renderer
               through window.currentWeek — the module's cfg closures read THAT bridge
               on a URL room switch, so a stale bridge rendered week 8's dates under a
               week-6 selector. The selection callbacks already update both; only the
               bootstrap path missed the sync. One line, before buildTimetable(). */
            window.currentWeek = currentWeek;
            document.getElementById('semesterChip').textContent = MockData.semester.chipText;
            populateWeekSelect('weekSelect', { ranges: false, selected: currentWeek });

            buildTimetable();
            updateWeekSubtitle();
            updateProgress();

            weekNav.initTodayBtn();
            initWeekKeyboardShortcuts();

            /* PHASE 2: Today pulses the WHOLE visible day blue in the responsive
               bands (the desktop's shared jumpToToday already flashes the
               today-cell column). The day index is read from the module's strip
               truth (aria-current="date" — set only on the real today), after a
               deferred settle, because the shared jump may land its week a beat
               later. Bound once, page-owned. */
            (function initResponsiveTodayFlash() {
                const btn = document.getElementById('todayBtn');
                if (!btn) return;
                let tries = 0;
                btn.addEventListener('click', function () {
                    (function settleToday() {
                        const chip = document.querySelector('#ttDayStrip .tt-day[aria-current="date"]');
                        if (!chip) {
                            if (tries++ < 12) setTimeout(settleToday, 80);
                            return;
                        }
                        const di = parseInt(chip.getAttribute('data-day'), 10);
                        if (!isNaN(di)) flashResponsiveDay(di, 'today', window.currentWeek);
                    })();
                });
            })();

            /* venue-timetable-bookable-cells §6.2 — the "Earliest bookable" action.
               NOT weekNav.jumpToEarliestBookable(): that resolves its destination
               with firstVisibleWeek(), and this page passes NO week filter (it is a
               browsing page that keeps every week — ui-common.js), so the shared
               method would jump to week 0. PHASE 2: the destination is the page's
               ROOM-AWARE lookup (venueEarliestBookable) — the SAME answer the
               strip's green dot and the window header's "Bookings open" badge
               read — because the shared lead-time helper cannot see the room and
               would name a day whose room is fully held. The tooltip is computed,
               never hardcoded (the shared sentence). */
            (function initEarliestBookable() {
                const btn = document.getElementById('earliestBtn');
                if (!btn) return;
                const paintTip = function () {
                    const target = responsiveEarliest || venueEarliestBookable();
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
                    const target = venueEarliestBookable();
                    if (!target) return;                 /* nothing bookable — silent */
                    selectWeek(target.week);
                    /* PHASE 2: the desktop pulse goes to the TARGET day's OWN rows.
                       flashEarliestBookableDay() re-derives the lead-time day, which
                       can be a different (room-full) day — the whole-day pulse must
                       land where the jump did. Same flashRowPulse mechanic. */
                    document.querySelectorAll('#tableBody tr[data-day-index="' + target.day + '"]')
                        .forEach(function (row) { flashRowPulse(row); });
                    /* the responsive bands: move the window to the jumped day (the
                       prototype sets state.day = di), pulse the whole day column
                       green, and focus/scroll the exact free slot. No-op at ≥1280. */
                    const root = document.querySelector('.tt-responsive');
                    if (root && root.getAttribute('data-tt-mode') !== 'full'
                        && window.TimetableResponsive) {
                        TimetableResponsive.setDay(target.day);
                        if (flashResponsiveDay(target.day, 'bookable', target.week) !== null) {
                            focusResponsiveDayCell(target.day, target.slot);
                        }
                    }
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
