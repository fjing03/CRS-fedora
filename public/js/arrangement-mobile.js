/* Page-local presentation adapter. Booking state remains in the page closure. */
(function (global) {
    'use strict';

    /* ── Dependency-free helpers (Node-testable; no DOM, no booking writes) ── */

    /* Fail closed: only an explicit `eligible === true` day may be offered. */
    function isEligibleDay(day) {
        return !!day && day.eligible === true;
    }

    /* The day's original index (never its position after any filtering). */
    function dayIndexOf(days, position) {
        const day = days[position];
        const index = day ? Number(day.index) : NaN;
        return Number.isInteger(index) && index >= 0 ? index : position;
    }

    /**
     * Pick a viewed day without renumbering: keep the previous original index
     * when that day is still eligible, else the first eligible day, else null.
     * @param {Array<{index?:number, eligible?:boolean}>} days
     * @param {number|null} previousIndex original day index
     * @returns {number|null} original day index
     */
    function chooseEligibleDay(days, previousIndex) {
        const list = Array.isArray(days) ? days : [];
        if (!list.length) return null;
        const previous = Number(previousIndex);
        if (Number.isInteger(previous) && previous >= 0) {
            for (let i = 0; i < list.length; i++) {
                if (dayIndexOf(list, i) === previous && isEligibleDay(list[i])) return previous;
            }
        }
        for (let i = 0; i < list.length; i++) {
            if (isEligibleDay(list[i])) return dayIndexOf(list, i);
        }
        return null;
    }

    /**
     * Validate a saved store entry ({day,startHour,endHour}, end exclusive).
     * @returns {{dayIndex:number,startHour:number,endHour:number,slotCount:number}|null}
     */
    function savedSpan(entry) {
        if (!entry || typeof entry !== 'object') return null;
        const dayIndex = Number(entry.day);
        const startHour = Number(entry.startHour);
        const endHour = Number(entry.endHour);
        if (!Number.isInteger(dayIndex) || dayIndex < 0) return null;
        if (!Number.isInteger(startHour) || startHour < 0) return null;
        if (!Number.isInteger(endHour) || endHour <= startHour) return null;
        return { dayIndex: dayIndex, startHour: startHour, endHour: endHour, slotCount: endHour - startHour };
    }

    /**
     * Copy the page store into a plain, sorted read-only entry list. Entries
     * that cannot be expressed as indexes are skipped; unrenderable weeks keep
     * their identity (dayKnown:false) instead of being dropped.
     * @param {object} store selectedSlotsByVenue
     * @param {Array} weekData page week data
     */
    function savedEntries(store, weekData) {
        const entries = [];
        if (!store || typeof store !== 'object' || !Array.isArray(weekData)) return entries;
        Object.keys(store).forEach(function (venueKey) {
            const byWeek = store[venueKey];
            if (!byWeek || typeof byWeek !== 'object') return;
            Object.keys(byWeek).forEach(function (weekKey) {
                const span = savedSpan(byWeek[weekKey]);
                const weekIndex = Number(weekKey);
                if (!span || !Number.isInteger(weekIndex) || weekIndex < 0) return;
                const week = weekData[weekIndex] || null;
                const day = week && Array.isArray(week.days) ? week.days[span.dayIndex] : null;
                entries.push({
                    key: String(venueKey) + '::' + weekIndex,
                    venueKey: String(venueKey),
                    weekIndex: weekIndex,
                    weekLabel: String((week && week.label) || ('Week ' + (weekIndex + 1))),
                    dayIndex: span.dayIndex,
                    startHour: span.startHour,
                    endHour: span.endHour,
                    slotCount: span.slotCount,
                    dayKnown: !!day,
                    dayAbbr: day ? String(day.abbr || '') : '',
                    date: day ? String(day.date || '') : '',
                });
            });
        });
        entries.sort(function (a, b) {
            return a.weekIndex - b.weekIndex || a.dayIndex - b.dayIndex || a.startHour - b.startHour;
        });
        return entries;
    }

    const utils = Object.freeze({
        chooseEligibleDay: chooseEligibleDay,
        savedSpan: savedSpan,
        savedEntries: savedEntries,
    });

    /* ── T06: one breakpoint owner + same-node relocation plan ──
       Node identity only — the SAME existing elements move to the hosts in the
       mobile partial, never copies. Hosts are resolved inside the mobile root so
       a missing host skips that node (fail closed) instead of guessing. */
    const MOBILE_MEDIA_QUERY = '(max-width: 768px)';
    const MOBILE_BODY_CLASS = 'arrangement-mobile-mode';

    const RELOCATION_PLAN = [
        { node: '#subjectSelector', host: '[data-arrangement-mobile-subject-host]' },
        { node: '#slotPicker', host: '[data-arrangement-mobile-slot-host]' },
        { node: '#buildingSelectorDropdown', host: '[data-arrangement-mobile-venue-host]' },
        /* Chips are appended after the dropdown, so the host keeps that order. */
        { node: '#venueChipGroup', host: '[data-arrangement-mobile-venue-host]' },
        { node: '.toolbar-filters .week-nav', host: '[data-arrangement-mobile-week-host]' },
        { node: '#earliestBtn', host: '[data-arrangement-mobile-earliest-host]' },
        { node: '#summaryTip', host: '[data-arrangement-mobile-tip-host]' },
        { node: '#submitTip', host: '[data-arrangement-mobile-submit-host]' },
        { node: '#subjectInfo', host: '[data-arrangement-mobile-context-facts]' },
    ];

    /* Inactive desktop hosts: kept mounted (existing selectors still resolve),
       hidden + inert while mobile is active, restored on exit. */
    const DESKTOP_CONTAINERS = [
        '.toolbar-restructure',
        '.sel-summary',
        '.grid-wrapper',
        '.legend-bar',
    ];

    /* ── T07: eligible-day pointer + empty-semester presentation ──
       Day choice is controller-only presentation state. It never writes the
       booking store, never selects a block and never changes the venue; a
       default week move reuses the page's existing week navigator through
       ports.chooseWeek. */
    const DAYS_SELECTOR = '[data-arrangement-mobile-days]';
    const DAY_HEADING_SELECTOR = '[data-arrangement-mobile-day-heading]';
    const EMPTY_SELECTOR = '[data-arrangement-mobile-empty]';
    const TIMELINE_SELECTOR = '[data-arrangement-mobile-timeline]';
    const EMPTY_TEXT = 'No bookable dates remaining this semester.';
    const MAX_WEEK_HOPS = 3;

    /* ── T08: explicit visible-target reveal ──
       A reveal is presentation only: it moves the viewed day, scrolls the
       VISIBLE mobile target into view and reuses the page's existing
       flash/pulse mechanics. It never selects a block, switches venue,
       rebuilds a week or calls refresh. T09 owns the timeline rows; a target
       with no visible row (or no hour at all) falls back to the day button. */
    const PULSE_CLASS = 'attention-pulse';
    const PULSE_MS = 1300;
    const HOUR_ROW_ATTRIBUTE = 'data-mobile-hour';

    /* ── T09: one-day timeline + continuous saved span ──
       Presentation only. One row mirrors one canonical cell by its ORIGINAL
       hour index; the blue span is drawn from the saved start/end indexes.
       Colours/ownership come exclusively from the whitelisted canonical
       classes the snapshot already carries (the same list the page's port
       filters), never from a second classification. T10 wires taps, T11 owns
       reason sheets, T12 the saved bar and T14 the full geometry. */
    const CANONICAL_STATE_CLASSES = [
        'cell-available', 'cell-selected', 'cell-pending', 'cell-reserved',
        'cell-occupied', 'cell-occupied--mine', 'cell-sun', 'cell-ph',
        'cell-too-soon',
    ];
    const ROW_CLASS = 'arrangement-mobile-row';
    const ROW_UNKNOWN_CLASS = 'arrangement-mobile-row--unknown';
    const SPAN_CLASS = 'arrangement-mobile-span';
    const ROW_STATE_ATTRIBUTE = 'data-mobile-state';
    const ROW_DAY_ATTRIBUTE = 'data-mobile-day';
    const SPAN_SIZE_ATTRIBUTE = 'data-mobile-span';

    /* ── T10: one delegated timeline tap → the canonical selection path ──
       Only a canonical `available` row is a selection candidate. The blue
       span and every blocked state belong to T11's details sheets, and an
       unknown row is disabled, so neither is routed. The adapter never draws
       an optimistic block or writes state: the page port calls the existing
       toggleCell/selectBlock path and the coalesced refresh renders the
       committed result. */
    const ROW_KIND_ATTRIBUTE = 'data-mobile-row';
    const ALREADY_SELECTED_TEXT = 'You already selected a class-length block. Open its details to remove it before choosing another.';
    const STALE_ROW_TEXT = 'That time just became unavailable. Pick another free time.';

    /* ── T11: touch-open information sheets + exact-keyed removal ──
       ONE native <dialog> from the existing partial renders every sheet. Text
       is built with textContent/DOM nodes only (holder text is never parsed as
       markup), reasons are filtered to the permitted canonical text, and no
       sheet auto-dismisses. Remove calls the page's existing removeSavedBlock()
       once through one port; the committed result is rendered by the coalesced
       refresh, never by an optimistic DOM delete. */
    const INFO_SELECTOR = '[data-arrangement-mobile-info]';
    const INFO_HEADING_SELECTOR = '[data-arrangement-mobile-info-heading]';
    const INFO_BODY_SELECTOR = '[data-arrangement-mobile-info-body]';
    const INFO_ACTIONS_SELECTOR = '[data-arrangement-mobile-info-actions]';
    const INFO_CLOSE_SELECTOR = '[data-arrangement-mobile-info-close]';
    const LEGEND_SELECTOR = '[data-arrangement-mobile-legend]';
    const TIP_TRIGGER_SELECTOR = '#summaryTip';
    const SPAN_KIND = 'span';
    /* The desktop selection-wipe hint is not a canonical reason; drop whole
       lines that start with it (and its variants) before showing a sheet. */
    const SELECTION_WIPE_PREFIX = /^\s*clear your selection first\b/i;
    const TIP_LABEL_PREFIX = /^tip:\s*/i;
    /* Last-resort only: mirrors the page's SUMMARY_TIP_BASE when every live
       Tip carrier is unavailable. The live state-aware message always wins. */
    const TIP_BASE_GUIDANCE = 'Click an available (green) time slot to begin.';
    const STATE_FALLBACK_LABELS = {
        pending: 'Pending',
        reserved: 'Reserved',
        conflict: 'Conflict',
        'too-soon': 'Not bookable yet',
        sunday: 'Sunday',
        holiday: 'Public holiday',
        selected: 'Selected',
        available: 'Available',
        unknown: 'Unavailable',
    };
    /* Real canonical swatch classes: Available fill, the blue selection block,
       borderless Pending and borderless Conflict (no ownership ring anywhere). */
    const LEGEND_ROWS = [
        {
            className: 'cell-available',
            label: 'Available',
            text: 'Free time you can choose for the replacement.',
        },
        {
            className: 'event-selection',
            label: 'Your Current Selection',
            text: 'The saved class-length block for the replacement. Tap it to inspect its details or remove it.',
        },
        {
            className: 'cell-pending',
            label: 'Pending',
            text: 'A replacement request is waiting for approval. Tap a Pending time to see whether it is yours and why.',
        },
        {
            className: 'cell-occupied',
            label: 'Conflict',
            text: 'The time is already taken. Tap a Conflict time to see whose class it is and why.',
        },
    ];

    /* ── T12: always-visible saved-summary bar ──
       One bar summarises ALL saved entries (never only the viewed day/week
       pointer). It reuses the T11 sheet for details/removal and the relocated
       original Submit node as the only submit control. Defensive display only:
       multiple legacy entries are shown with individually keyed Remove actions;
       this never permits creating a second block. */
    const SUMMARY_HOST_SELECTOR = '[data-arrangement-mobile-summary-host]';
    const SUMMARY_OPENER_ATTRIBUTE = 'data-arrangement-mobile-summary-opener';
    const SUMMARY_OPENER_SELECTOR = '[' + SUMMARY_OPENER_ATTRIBUTE + ']';
    const SUMMARY_EMPTY_TITLE = 'Select a time';
    const SUMMARY_EMPTY_HINT = 'Tap a green time to begin.';
    const SUMMARY_REVIEW_HINT = 'Tap to review or remove';
    /* Stored slots exist but no entry can be expressed yet (loading/failed
       snapshot): say so instead of drawing a false empty bar. */
    const SUMMARY_SAVED_TITLE = 'Selection saved';
    const SUMMARY_SAVED_HINT = 'Your saved block is kept. Open its details when the timetable finishes loading.';

    /* ── T13: keyboard isolation, dialog focus and one polite live region ──
       The page owns the document key guards; this adapter owns the sheet's
       initial focus, identity-based return (rows/summary are rebuilt by every
       settled render) and the single announcement channel. Nothing here reads
       a port, writes booking state or moves the viewed week/day by itself. */
    const LIVE_SELECTOR = '[data-arrangement-mobile-live]';

    /* ── T14: measured mobile geometry + compact original context ──
       The sticky control cluster and the fixed action bar are measured with
       getBoundingClientRect; the real heights are written as page-root (body)
       custom properties so the page inset, row scroll-margin and the page-local
       toast offset never rely on a guessed footer. One ResizeObserver (or the
       window resize fallback) is owned here and removed on exit/destroy. */
    const CONTROLS_SELECTOR = '[data-arrangement-mobile-controls]';
    const ACTIONS_SELECTOR = '[data-arrangement-mobile-actions]';
    const CONTEXT_SUMMARY_SELECTOR = '[data-arrangement-mobile-context-summary]';
    const STICKY_HEIGHT_VAR = '--arrangement-mobile-sticky-height';
    const BAR_HEIGHT_VAR = '--arrangement-mobile-bar-height';
    const CONTEXT_EMPTY_TEXT = 'Select a course and original slot';
    const CONTEXT_SEPARATOR = ' \u00b7 ';
    const CONTEXT_TIME_SEPARATOR = ' ~ ';

    /* ── T16: expired-but-retained saved entries ──
       The page clock reconciliation marks a saved identity retained when its
       own date is no longer eligible. It stays in the SAME summary bar and
       opens the SAME details sheet (Remove lives there), while mobile Submit
       stays disabled even if a later eligible date exists. Nothing here reads
       or mutates the page store. */
    const RETENTION_TITLE = 'Saved selection is no longer bookable.';
    const RETENTION_HINT = 'Remove it below, then choose another date.';

    /* ── T18: mobile-origin request latch ──
       The page owns the latch and exposes it read-only as
       snapshot.submissionBusy. The adapter only honours it in the existing
       Submit gates, so a refreshed/retention-cleared render, a breakpoint exit
       or a teardown can never re-enable a request that is still in flight. */

    /* ── Controller shell ── */

    function create({ root, ports = {} }) {
        let mounted = false;
        let destroyed = false;
        let lastSnapshot = null;
        /* T05: one scheduler only. Repeated refresh calls collapse into a single
           microtask; the latest reason wins. Nothing else in this shell may
           schedule work. */
        let refreshQueued = false;
        let queuedReason = null;
        let notifying = false;
        /* T04: the read-only snapshot port is stored once, never called for
           booking writes. Missing port = fail closed (no snapshot). */
        const readSnapshotPort = typeof ports.readSnapshot === 'function' ? ports.readSnapshot : null;
        /* Optional presentation-only notification; never part of booking state. */
        const onSnapshotPort = typeof ports.onSnapshot === 'function' ? ports.onSnapshot : null;
        /* T06: the page owns both existing panel closers. The adapter calls this
           one page port — never broad DOM clicks. */
        const closePanelsPort = typeof ports.closePanels === 'function' ? ports.closePanels : null;
        /* T10: the ONE canonical selection command (toggleCell via the page) and
           the mobile-scoped 5-second transient. No second selection/submit path. */
        const chooseAvailablePort = typeof ports.chooseAvailable === 'function' ? ports.chooseAvailable : null;
        const showTransientPort = typeof ports.showTransient === 'function' ? ports.showTransient : null;
        /* T11: the ONE removal command. The page port calls the existing
           removeSavedBlock(venueKey, weekKey) exactly once; no new rule. */
        const removeSelectionPort = typeof ports.removeSelection === 'function' ? ports.removeSelection : null;
        /* T19: the fallback scheduler's handle is kept so destroy() can clear a
           still-pending turn exactly like the pulse timer. The primary
           queueMicrotask path is not a timer and needs no handle. */
        let refreshTimer = null;
        const scheduleTurn = typeof global.queueMicrotask === 'function'
            ? function (run) { global.queueMicrotask(run); }
            : function (run) { refreshTimer = global.setTimeout(run, 0); };

        /* T06 layout state. Initialised from the markup (root starts hidden and
           inert) and restored on exit. */
        let mobileActive = false;
        let mediaQuery = null;
        let mediaListener = null;
        let movedEntries = [];
        let hiddenContainers = [];
        let rootWasHidden = root ? !!root.hidden : true;
        let rootWasInert = root ? !!root.inert : true;
        /* T19: capture the shell's own static text once, so destroy restores it
           instead of leaving adapter-rendered presentation in a dead shell. */
        const contextSummaryNode = root ? root.querySelector(CONTEXT_SUMMARY_SELECTOR) : null;
        const contextSummaryWas = contextSummaryNode ? contextSummaryNode.textContent : null;
        const infoHeadingNode = root ? root.querySelector(INFO_HEADING_SELECTOR) : null;
        const infoHeadingWas = infoHeadingNode ? infoHeadingNode.textContent : null;

        /* T07 presentation state. `viewedDayIndex`/`lastWeekIndex` are original
           indexes, never positions after filtering; `emptyState` gates the
           empty-semester message and date controls. */
        const chooseWeekPort = typeof ports.chooseWeek === 'function' ? ports.chooseWeek : null;
        let viewedDayIndex = null;
        let lastWeekIndex = null;
        let emptyState = false;
        /* Disabled-control bookkeeping for the empty semester (mobile only). */
        let emptyControlState = null;
        /* Defensive week-hop guard: one request per source week, capped. */
        let hopFromWeek = null;
        let hopAttempts = 0;
        /* A hop runs inside the notification turn, so the page's own refresh
           call is suppressed by the recursion guard; read once after it. */
        let hopRefreshRequested = false;
        let daysClickBound = false;
        let timelineClickBound = false;

        /* T11 presentation state: one sheet opener for basic focus return, a
           single-shot removal guard and the Tip suppression bookkeeping. */
        let infoOpener = null;
        let infoOpenerCandidate = null;
        let infoCloseBound = false;
        let infoKeydownBound = false;
        let removalRequested = false;
        let tipSuppressed = false;
        let tipMessage = '';
        /* T13 focus state. The descriptor survives the opener being rebuilt;
           the two flags make restoration exactly-once even when a native close
           event follows the explicit closeInfo() call. */
        let infoFocusDescriptor = null;
        let infoFocusPending = false;
        let infoDeferFocus = false;
        let focusAfterRender = null;
        /* T13 announcement state. The first snapshot only sets the baseline;
           later renders speak only when the saved total really changed. */
        let lastPresentedDayIndex = null;
        let selectionTotalBaselineSet = false;
        let lastSelectionTotal = null;

        /* T08: one pending explicit reveal (original indexes) waiting for the
           settled snapshot that matches it, plus this adapter's own pulse
           cleanup timer. Both are presentation state only. */
        let pendingReveal = null;
        let pulseTimer = null;
        let pulseTargetElement = null;

        /* T14 layout measurement state: one observer (or the window resize
           fallback) plus the last written values, so a settled render or an
           observer callback only writes when the real height changed. */
        let insetsObserver = null;
        let resizeMeasureBound = false;
        let lastStickyHeight = '';
        let lastBarHeight = '';

        /* T16: true only while this adapter itself forced the original Submit
           disabled for a retained/expired entry, so the desktop exit can give
           it back to the page's normal total gate. */
        let retentionSubmitForced = false;

        /* T18: the last settled read of the page-owned mobile request latch.
           Cached so a desktop exit or destroy cannot re-enable Submit after the
           adapter has dropped its snapshot (the latch itself stays in the page). */
        let submissionBusyCached = false;

        function readPortSnapshot() {
            if (!readSnapshotPort) {
                lastSnapshot = null;
                return null;
            }
            let value = null;
            try {
                value = readSnapshotPort();
            } catch (err) {
                value = null;
            }
            lastSnapshot = value && typeof value === 'object' ? value : null;
            return lastSnapshot;
        }

        /* The settled turn: read the canonical port exactly once, cache it, then
           (optionally) notify. Runs only while mounted; a queued run that loses
           its mount (destroy) does nothing. */
        function runRefresh() {
            refreshQueued = false;
            refreshTimer = null;
            const reason = queuedReason;
            queuedReason = null;
            if (!mounted || destroyed) return null;
            notifying = true;
            try {
                const value = readPortSnapshot();
                /* T07: render the day picker/empty state from the snapshot just
                   read. Rendering never calls refresh (the guard above stays
                   authoritative) and never writes booking state. */
                try {
                    render(value, true);
                } catch (err) {
                    /* A presentation failure must not break the settled turn. */
                }
                if (onSnapshotPort) {
                    try {
                        onSnapshotPort(value, reason);
                    } catch (err) {
                        /* A presentation callback must never break the page. */
                    }
                }
                return value;
            } finally {
                notifying = false;
                if (hopRefreshRequested) {
                    hopRefreshRequested = false;
                    /* Same single scheduler: one follow-up read after the turn
                       that moved the week, so the moved snapshot is rendered. */
                    refresh('week-hop');
                }
            }
        }

        /* Coalesced refresh hook: queue at most one turn, keep the latest reason.
           Calls during the notification turn are ignored (recursion guard). */
        function refresh(reason) {
            if (!mounted || destroyed || notifying) return;
            if (typeof reason === 'string' && reason) queuedReason = reason;
            else if (!queuedReason) queuedReason = 'refresh';
            if (refreshQueued) return;
            refreshQueued = true;
            scheduleTurn(runRefresh);
        }

        /* Read-only projection for later tasks: the cached snapshot when
           mounted, else one fresh port read; never writes the store, DOM or
           network. Missing/erroring port fails closed to null. */
        function snapshot() {
            if (destroyed) return null;
            if (mounted && lastSnapshot) return lastSnapshot;
            return readPortSnapshot();
        }

        /* ── T06: breakpoint-owned layout (relocate ⇄ restore, idempotent) ── */

        function closePanels() {
            if (!closePanelsPort) return;
            try {
                closePanelsPort();
            } catch (err) {
                /* The page port owns its own failures; layout must still move. */
            }
        }

        /**
         * Enter mobile: close panels first, then move the same nodes. A comment
         * marker stays at each original position so exit can restore exact order.
         */
        function enterMobile() {
            if (mobileActive || destroyed || !mounted || !root) return;
            const doc = root.ownerDocument || global.document;
            if (!doc) return;
            mobileActive = true;

            closePanels();

            rootWasHidden = !!root.hidden;
            rootWasInert = !!root.inert;
            movedEntries = [];
            RELOCATION_PLAN.forEach(function (spec) {
                const node = doc.querySelector(spec.node);
                const host = root.querySelector(spec.host);
                if (!node || !host || !node.parentNode) return;
                const marker = doc.createComment('arrangement-mobile:restore');
                const parent = node.parentNode;
                const nextSibling = node.nextSibling;
                parent.insertBefore(marker, node);
                host.appendChild(node);
                movedEntries.push({
                    node: node,
                    marker: marker,
                    parent: parent,
                    nextSibling: nextSibling,
                });
            });

            hiddenContainers = [];
            DESKTOP_CONTAINERS.forEach(function (selector) {
                const container = doc.querySelector(selector);
                if (!container) return;
                hiddenContainers.push({
                    el: container,
                    hidden: !!container.hidden,
                    inert: !!container.inert,
                });
                container.hidden = true;
                container.inert = true;
            });

            root.hidden = false;
            root.inert = false;
            if (doc.body) doc.body.classList.add(MOBILE_BODY_CLASS);
            /* Re-apply the day/empty presentation from the cached snapshot:
               the breakpoint change itself must not rebuild the week or leave
               a hidden stale date picker. */
            renderFromCache();
            /* No cached snapshot yet: still suppress the relocated Tip
               trigger's floating tooltip as soon as mobile becomes active. */
            applySummaryTip(lastSnapshot);
            /* T14: after relocation/show, measure the real sticky cluster and
               fixed bar and start observing them (one observer, or resize). */
            measureInsets();
            attachInsets();
        }

        /**
         * Exit mobile: close panels, move nodes back before their markers (or the
         * recorded parent/next sibling if a marker was lost), restore the exact
         * hidden/inert state, hide/inert the mobile root and drop the body class.
         */
        function exitMobile() {
            if (!mobileActive) return;
            mobileActive = false;

            closePanels();
            /* T11: a mobile information sheet does not survive the breakpoint;
               close it (once) before nodes move back to their desktop hosts. */
            closeInfo();
            /* T18: honour the page-owned request latch from the last settled
               read — it must survive this breakpoint, so neither the empty gate
               nor the retention lock may re-enable a busy Submit. */
            submissionBusyCached = submissionBusy(lastSnapshot);
            /* Desktop must not inherit the mobile empty-semester disable. */
            releaseEmptyControls(true);
            /* T16: nor the mobile-only retention Submit lock. */
            releaseRetentionSubmit(lastSnapshot);
            /* A reveal target and its pulse belong to the mobile surface. */
            pendingReveal = null;
            clearPulse();

            movedEntries.forEach(function (entry) {
                const node = entry.node;
                if (!node) return;
                if (entry.marker && entry.marker.parentNode) {
                    entry.marker.parentNode.insertBefore(node, entry.marker);
                    entry.marker.parentNode.removeChild(entry.marker);
                } else if (entry.parent) {
                    const next = entry.nextSibling && entry.nextSibling.parentNode === entry.parent
                        ? entry.nextSibling
                        : null;
                    entry.parent.insertBefore(node, next);
                }
            });
            movedEntries = [];

            hiddenContainers.forEach(function (item) {
                item.el.hidden = item.hidden;
                item.el.inert = item.inert;
            });
            hiddenContainers = [];

            if (root) {
                root.hidden = rootWasHidden;
                root.inert = rootWasInert;
            }
            const doc = root ? (root.ownerDocument || global.document) : global.document;
            /* T14: stop observing and drop the measured page-root properties
               before desktop inherits any mobile inset. */
            detachInsets();
            if (doc && doc.body) doc.body.classList.remove(MOBILE_BODY_CLASS);
            /* T11: desktop gets the latest Tip message/aria state back so its
               hover and focus tooltip stay correct. */
            applySummaryTip(lastSnapshot);
        }

        /* ── T07: viewed day + empty semester (read-only presentation) ── */

        function weekIndexOf(weeks, position) {
            const week = weeks[position];
            const index = week ? Number(week.index) : NaN;
            return Number.isInteger(index) && index >= 0 ? index : position;
        }

        function dayFor(days, index) {
            const list = Array.isArray(days) ? days : [];
            /* `null` must not coerce to day 0 via Number(null). */
            if (index === null || index === undefined || index === '') return null;
            const wanted = Number(index);
            if (!Number.isInteger(wanted) || wanted < 0) return null;
            for (let i = 0; i < list.length; i++) {
                if (dayIndexOf(list, i) === wanted) return list[i];
            }
            return null;
        }

        function dayText(day) {
            const abbr = String((day && day.abbr) || '');
            const date = String((day && day.date) || '');
            if (abbr && date) return abbr + ', ' + date;
            return date || abbr;
        }

        /* Compact picker label: keep the full date for the accessible name,
           show only "Tue" over "17" on the button face. */
        function dayNumber(day) {
            const date = String((day && day.date) || '');
            const parsed = new Date(date);
            if (!Number.isNaN(parsed.getTime())) return String(parsed.getDate());
            const match = /(\d{1,2})/.exec(date);
            return match ? match[1] : '';
        }

        /* Saved/prefilled entry for the current week+venue only, read from the
           read-only snapshot copy. Never creates or changes a selection. */
        function savedViewDay(snapshot, weekIndex, days) {
            if (!snapshot || weekIndex === null) return null;
            const venue = String(snapshot.venueCode || '');
            const selections = snapshot.selections || {};
            const candidate = function (entry) {
                if (!entry) return null;
                if (Number(entry.weekIndex) !== weekIndex) return null;
                if (String(entry.venueKey || '') !== venue) return null;
                const index = Number(entry.dayIndex);
                return Number.isInteger(index) && index >= 0 ? index : null;
            };
            let index = candidate(selections.current);
            if (index === null && Array.isArray(selections.entries)) {
                for (let i = 0; i < selections.entries.length; i++) {
                    index = candidate(selections.entries[i]);
                    if (index !== null) break;
                }
            }
            if (index === null) return null;
            return isEligibleDay(dayFor(days, index)) ? index : null;
        }

        function renderDays(days) {
            const host = root.querySelector(DAYS_SELECTOR);
            if (!host) return;
            const doc = root.ownerDocument || global.document;
            if (!doc) return;
            /* Only adapter-owned children live in this empty host. */
            while (host.firstChild) host.removeChild(host.firstChild);
            const list = Array.isArray(days) ? days : [];
            for (let i = 0; i < list.length; i++) {
                if (!isEligibleDay(list[i])) continue;
                const index = dayIndexOf(list, i);
                const button = doc.createElement('button');
                button.setAttribute('type', 'button');
                button.setAttribute('data-day-index', String(index));
                button.setAttribute('aria-pressed', index === viewedDayIndex ? 'true' : 'false');
                const label = dayText(list[i]);
                if (label) button.setAttribute('aria-label', label);
                const weekday = doc.createElement('span');
                weekday.className = 'arrangement-mobile-day-weekday';
                weekday.textContent = String((list[i] && list[i].abbr) || '') || label.split(',')[0];
                const number = doc.createElement('strong');
                number.className = 'arrangement-mobile-day-number';
                number.textContent = dayNumber(list[i]);
                button.appendChild(weekday);
                button.appendChild(number);
                host.appendChild(button);
            }
        }

        function renderDayHeading(days) {
            const heading = root.querySelector(DAY_HEADING_SELECTOR);
            if (!heading) return;
            const day = dayFor(days, viewedDayIndex);
            /* No eligible day -> clear rather than leave a stale date. */
            heading.textContent = day ? dayText(day) : '';
        }

        function emptyControlElements() {
            const doc = root.ownerDocument || global.document;
            const list = [];
            if (!doc) return list;
            const weekSelect = doc.getElementById('weekSelector');
            if (weekSelect) list.push(weekSelect);
            const arrows = doc.querySelectorAll('.week-arrow');
            if (arrows) {
                for (let i = 0; i < arrows.length; i++) list.push(arrows[i]);
            }
            const earliest = doc.getElementById('earliestBtn');
            if (earliest) list.push(earliest);
            return list;
        }

        /* Capture once, then re-assert on every settled empty render so an
           unrelated counter update cannot re-enable a date control. */
        function applyEmptyControls() {
            const doc = root.ownerDocument || global.document;
            if (!doc) return;
            if (!emptyControlState) {
                emptyControlState = {
                    entries: emptyControlElements().map(function (el) {
                        return { el: el, disabled: !!el.disabled };
                    }),
                    submit: null,
                };
                const submit = doc.getElementById('submitRequestBtn');
                if (submit) emptyControlState.submit = { el: submit, disabled: !!submit.disabled };
            }
            emptyControlState.entries.forEach(function (item) {
                if (item.el) item.el.disabled = true;
            });
            if (emptyControlState.submit && emptyControlState.submit.el) {
                emptyControlState.submit.el.disabled = true;
            }
        }

        /* Restore the date controls to their pre-empty values. Submit is left
           to the page's existing counter/snapshot state when the semester
           becomes eligible again; only a full mobile exit restores it — and
           never while a mobile-origin request is still in flight (T18). */
        function releaseEmptyControls(full) {
            if (!emptyControlState) return;
            const state = emptyControlState;
            emptyControlState = null;
            state.entries.forEach(function (item) {
                if (item.el) item.el.disabled = item.disabled;
            });
            if (full && state.submit && state.submit.el) {
                state.submit.el.disabled = state.submit.disabled || submissionBusyCached;
            }
        }

        function setEmptyState(isEmpty) {
            emptyState = !!isEmpty;
            const emptyEl = root.querySelector(EMPTY_SELECTOR);
            const timeline = root.querySelector(TIMELINE_SELECTOR);
            if (emptyEl) {
                if (emptyState) {
                    emptyEl.textContent = EMPTY_TEXT;
                    emptyEl.hidden = false;
                } else {
                    emptyEl.textContent = '';
                    emptyEl.hidden = true;
                }
            }
            /* T09 fills this host from the same snapshot; this only gates
               visibility for the empty semester. */
            if (timeline) timeline.hidden = emptyState;
            if (!mobileActive) return;
            if (emptyState) applyEmptyControls();
            else releaseEmptyControls(false);
        }

        /* Current week lost its last eligible day while another week still has
           one: move there through the existing page week navigator. The venue is
           never touched and a still-invalid new snapshot cannot recurse. */
        function maybeHopWeek(weeks, weekIndex, days) {
            if (!chooseWeekPort || !mobileActive || emptyState) return;
            if (weekIndex === null || !weeks.length) return;
            if (days.some(isEligibleDay)) {
                hopFromWeek = null;
                return;
            }
            if (hopFromWeek === weekIndex || hopAttempts >= MAX_WEEK_HOPS) return;
            let next = null;
            for (let i = 0; i < weeks.length; i++) {
                const index = weekIndexOf(weeks, i);
                if (index <= weekIndex) continue;
                if (weeks[i] && weeks[i].eligible === true) {
                    next = index;
                    break;
                }
            }
            if (next === null) {
                for (let i = 0; i < weeks.length; i++) {
                    const index = weekIndexOf(weeks, i);
                    if (index >= weekIndex) continue;
                    if (weeks[i] && weeks[i].eligible === true) {
                        next = index;
                        break;
                    }
                }
            }
            if (next === null || next === weekIndex) return;
            hopFromWeek = weekIndex;
            hopAttempts++;
            try {
                chooseWeekPort(next);
                /* Inside the settled turn the page's refresh is ignored, so
                   queue exactly one follow-up read (never a recursive render). */
                if (notifying) hopRefreshRequested = true;
            } catch (err) {
                /* Page port owns its failure; stay on the cached week. */
            }
        }

        /**
         * Render the eligible day picker, heading and empty state from one
         * read-only snapshot. Creates no selection and never calls refresh().
         * @param {object|null} snapshot
         * @param {boolean} allowWeekHop only a settled refresh may repair an
         *   ineligible current week; a breakpoint change must not navigate.
         */
        function render(snapshot, allowWeekHop) {
            if (!root || destroyed) return null;
            const snap = snapshot && typeof snapshot === 'object' ? snapshot : null;
            const weeks = snap && Array.isArray(snap.weeks) ? snap.weeks : [];
            const weekIndex = snap && Number.isInteger(snap.weekIndex) ? snap.weekIndex : null;
            const week = weekIndex !== null && weeks[weekIndex] ? weeks[weekIndex] : null;
            const days = week && Array.isArray(week.days) ? week.days : [];
            /* Only a real week list may claim the semester is empty; a missing
               or failed snapshot must never show a false "no dates" message. */
            const hasWeekList = !!snap && Array.isArray(snap.weeks);
            const anyEligibleWeek = hasWeekList && weeks.some(function (item) {
                return !!item && item.eligible === true;
            });
            /* Initial view prefers an eligible saved/prefilled day in the
               current week+venue; every later snapshot keeps the viewed
               original day index when still eligible (weekday retention),
               else falls back to the first eligible day. */
            const previous = lastWeekIndex === null
                ? savedViewDay(snap, weekIndex, days)
                : viewedDayIndex;
            const priorPresentedDay = lastPresentedDayIndex;
            viewedDayIndex = chooseEligibleDay(days, previous);
            /* T08: a matching pending explicit reveal wins over weekday
               retention and is applied exactly once, by this render. */
            const reveal = consumePendingReveal(snap, days);
            const dayChanged = priorPresentedDay !== null
                && viewedDayIndex !== null
                && viewedDayIndex !== priorPresentedDay;
            lastPresentedDayIndex = viewedDayIndex;
            lastWeekIndex = weekIndex;
            renderDays(days);
            renderDayHeading(days);
            setEmptyState(hasWeekList && !anyEligibleWeek);
            /* T09: the timeline follows the resolved day/empty state. It is
               hidden (never unhidden) while T07's empty message is active. */
            renderTimeline(snap);
            /* T12: the saved-summary bar reads ALL saved entries, so browsing
               another day/week (or the empty semester) never hides it. */
            renderSummaryBar(snap);
            /* T16: an expired retained entry keeps mobile Submit disabled even
               when a later eligible date exists. */
            applyRetentionSubmit(snap);
            /* T14: compact subject + original date/time in the collapsed
               <details>; presentation text from snapshot.original only. */
            renderContextSummary(snap);
            /* T13: one announcement per real user-visible change. Initial
               hydration only sets the baseline; identical refreshes/resizes
               stay silent. */
            noteSelectionChange(snap);
            if (dayChanged) announceDay(viewedDayIndex, days);
            if (allowWeekHop) maybeHopWeek(weeks, weekIndex, days);
            if (reveal) revealNode(revealElement(reveal), reveal.tone);
            /* T11: keep only the Tip trigger's shared floating tooltip
               suppressed while mobile, and remember the latest message so
               desktop restores it on exit. */
            applySummaryTip(snap);
            /* T13: a Remove closes the sheet before the settled refresh; the
               rebuilt control is only focusable once this render is done. */
            restoreFocusAfterRender();
            /* T14: every settled render may have changed the summary/timeline
               height; re-measure the owned insets after the DOM is final. */
            if (mobileActive) measureInsets();
            return { weekIndex: weekIndex, viewedDayIndex: viewedDayIndex, empty: emptyState };
        }

        /* Presentation re-render from the cached snapshot only (breakpoint
           enter). No port read, no refresh, no week navigation, no booking
           writes: eligibility repair belongs to the next settled turn. */
        function renderFromCache() {
            if (!lastSnapshot) return null;
            return render(lastSnapshot, false);
        }

        /* ── T08: explicit visible-target reveal (presentation only) ── */

        function prefersReducedMotion() {
            if (typeof global.matchMedia !== 'function') return false;
            try {
                const query = global.matchMedia('(prefers-reduced-motion: reduce)');
                return !!query && query.matches === true;
            } catch (err) {
                return false;
            }
        }

        /* Drop this adapter's own pulse class/timer (page-row flash keeps its
           own shared timer and is not owned here). */
        function clearPulse() {
            if (pulseTimer !== null) {
                global.clearTimeout(pulseTimer);
                pulseTimer = null;
            }
            if (pulseTargetElement) {
                if (pulseTargetElement.classList) pulseTargetElement.classList.remove(PULSE_CLASS);
                pulseTargetElement = null;
            }
        }

        function pulseNode(node) {
            if (!node || !node.classList) return;
            clearPulse();
            node.classList.remove(PULSE_CLASS);
            void node.offsetWidth; /* restart the animation on a repeat reveal */
            node.classList.add(PULSE_CLASS);
            pulseTargetElement = node;
            pulseTimer = global.setTimeout(function () {
                pulseTimer = null;
                if (pulseTargetElement && pulseTargetElement.classList) {
                    pulseTargetElement.classList.remove(PULSE_CLASS);
                }
                pulseTargetElement = null;
            }, PULSE_MS);
        }

        function isVisibleTarget(node) {
            if (!node) return false;
            if (node.hidden) return false;
            if (typeof node.closest === 'function' && node.closest('[hidden]')) return false;
            return true;
        }

        /* The visible element for a target: a real T09 timeline row when one
           exists for this hour, else the visible day button (Earliest has no
           hour at all). Never falls back to the hidden canonical table. */
        function revealElement(target) {
            if (!root || !target) return null;
            const timeline = root.querySelector(TIMELINE_SELECTOR);
            if (timeline && target.hourIndex !== null) {
                const row = timeline.querySelector('[' + HOUR_ROW_ATTRIBUTE + '="' + target.hourIndex + '"]');
                if (isVisibleTarget(row)) return row;
            }
            const dayHost = root.querySelector(DAYS_SELECTOR);
            const button = dayHost ? dayHost.querySelector('[data-day-index="' + target.dayIndex + '"]') : null;
            return isVisibleTarget(button) ? button : null;
        }

        /* Scroll + the existing feedback family. Reduced motion keeps the
           scroll (instant) and skips the animation; no booking write. */
        function revealNode(node, tone) {
            if (!node) return;
            const reduced = prefersReducedMotion();
            if (typeof node.scrollIntoView === 'function') {
                try {
                    node.scrollIntoView({
                        block: 'center',
                        behavior: reduced ? 'auto' : 'smooth',
                    });
                } catch (err) {
                    try { node.scrollIntoView(); } catch (ignored) { /* no scroll support */ }
                }
            }
            if (reduced) return;
            if (tone === 'flash' && typeof global.flashRowPulse === 'function') {
                try {
                    global.flashRowPulse(node);
                    return;
                } catch (err) {
                    /* Fall through to the page's pulse class. */
                }
            }
            pulseNode(node);
        }

        function revealDays(snapshot, weekIndex) {
            const week = snapshot && Number.isInteger(weekIndex) && Array.isArray(snapshot.weeks)
                ? snapshot.weeks[weekIndex] : null;
            return week && Array.isArray(week.days) ? week.days : [];
        }

        /* The requested week/venue must be the one the snapshot really shows:
           an explicit week wins, a missing one resolves from the snapshot. */
        function revealMatchesSnapshot(snapshot, target) {
            if (!snapshot || !target) return false;
            if (!Number.isInteger(snapshot.weekIndex)) return false;
            if (snapshot.weekIndex !== target.weekIndex) return false;
            return String(snapshot.venueCode || '') === target.venueKey;
        }

        function resolveRevealTarget(input) {
            const snap = lastSnapshot;
            if (!snap) return null;
            const options = input && typeof input === 'object' ? input : {};
            const rawWeek = options.weekIndex;
            const weekIndex = rawWeek === null || rawWeek === undefined || rawWeek === ''
                ? snap.weekIndex : Number(rawWeek);
            if (!Number.isInteger(weekIndex) || weekIndex < 0) return null;
            const rawVenue = options.venueKey;
            const venueKey = rawVenue === null || rawVenue === undefined || rawVenue === ''
                ? String(snap.venueCode || '') : String(rawVenue);
            if (!venueKey) return null;
            const rawDay = options.dayIndex;
            const dayIndex = rawDay === null || rawDay === undefined || rawDay === '' ? NaN : Number(rawDay);
            if (!Number.isInteger(dayIndex) || dayIndex < 0) return null;
            const rawHour = options.hourIndex;
            const hour = rawHour === null || rawHour === undefined || rawHour === '' ? NaN : Number(rawHour);
            return {
                weekIndex: weekIndex,
                venueKey: venueKey,
                dayIndex: dayIndex,
                hourIndex: Number.isInteger(hour) && hour >= 0 ? hour : null,
                tone: options.tone === 'pulse' ? 'pulse' : 'flash',
            };
        }

        /**
         * Reveal an explicit eligible target on the visible mobile surface.
         * Call only after the page's existing navigation/venue change has
         * committed. Returns false when the request is unusable for this
         * surface; a target whose week/venue is not in the cached snapshot yet
         * is kept for the next settled render() (one shot), never forced.
         * @param {{weekIndex?:number, venueKey?:string, dayIndex:number,
         *   hourIndex?:number|null, tone?:'flash'|'pulse'}} input
         * @returns {boolean} accepted (applied now, or pending a matching render)
         */
        function revealTarget(input) {
            if (destroyed || !mounted || !mobileActive || !root) return false;
            const target = resolveRevealTarget(input);
            if (!target) return false;
            if (!revealMatchesSnapshot(lastSnapshot, target)) {
                /* The committed navigation is newer than the coalesced read:
                   keep it for the settled render that shows the new week/venue. */
                pendingReveal = target;
                return true;
            }
            const days = revealDays(lastSnapshot, target.weekIndex);
            if (!isEligibleDay(dayFor(days, target.dayIndex))) return false;
            pendingReveal = null;
            const previousDay = viewedDayIndex;
            viewedDayIndex = target.dayIndex;
            lastPresentedDayIndex = target.dayIndex;
            renderDays(days);
            renderDayHeading(days);
            /* T09: rebuild the day's rows before resolving the visible target,
               so a same-week reveal can flash the real hour row. */
            renderTimeline(lastSnapshot);
            revealNode(revealElement(target), target.tone);
            /* T13: explicit reveals are user actions too; speak once. */
            if (previousDay !== target.dayIndex) announceDay(target.dayIndex, days);
            return true;
        }

        /* Exactly once per settled render: apply a matching pending reveal, or
           clear it without side effects when it no longer fits. */
        function consumePendingReveal(snapshot, days) {
            if (!pendingReveal) return null;
            const target = pendingReveal;
            pendingReveal = null;
            if (!revealMatchesSnapshot(snapshot, target)) return null;
            if (!isEligibleDay(dayFor(days, target.dayIndex))) return null;
            viewedDayIndex = target.dayIndex;
            return target;
        }

        /* ── T09: daily timeline (canonical rows, one saved span) ── */

        /* Only the snapshot's whitelisted classes may paint a row. Unknown
           classes are ignored (fail closed at the caller). */
        function canonicalStateClasses(cell) {
            const list = cell && Array.isArray(cell.stateClasses) ? cell.stateClasses : [];
            const out = [];
            for (let i = 0; i < list.length; i++) {
                const name = list[i];
                if (CANONICAL_STATE_CLASSES.indexOf(name) === -1) continue;
                if (out.indexOf(name) === -1) out.push(name);
            }
            return out;
        }

        function hasStateClass(classes, name) {
            return classes.indexOf(name) !== -1;
        }

        /* One token for later tap routing (T10/T11). It never changes the
           applied classes or the text: too-soon/sunday/holiday are the
           canonical read-only families, selected/available the selectable
           ones, conflict the occupied family. */
        function primaryStateToken(classes) {
            if (hasStateClass(classes, 'cell-too-soon')) return 'too-soon';
            if (hasStateClass(classes, 'cell-sun')) return 'sunday';
            if (hasStateClass(classes, 'cell-ph')) return 'holiday';
            if (hasStateClass(classes, 'cell-pending')) return 'pending';
            if (hasStateClass(classes, 'cell-reserved')) return 'reserved';
            if (hasStateClass(classes, 'cell-selected')) return 'selected';
            if (hasStateClass(classes, 'cell-occupied')) return 'conflict';
            if (hasStateClass(classes, 'cell-available')) return 'available';
            return 'unknown';
        }

        /* Canonical state words only. An empty label (available/sun/PH carry
           none) reads `Available` for a free cell and a neutral `Unavailable`
           otherwise. Raw `detailText` is never rendered: the desktop
           selection-wipe hint and holder prose belong to T11's sheets. */
        function timelineStateText(cell, classes) {
            const raw = cell && typeof cell.stateLabel === 'string' ? cell.stateLabel : '';
            const text = raw.replace(/\s*\n\s*/g, ' ').replace(/\s+/g, ' ').trim();
            if (text) return text;
            if (hasStateClass(classes, 'cell-available')) return 'Available';
            return 'Unavailable';
        }

        /* +30 minutes for the last half-hour row: the shared helper first, a
           local HH:MM fallback when it is unavailable (never booking logic). */
        function addHalfHour(value) {
            if (typeof global.add30min === 'function') {
                try {
                    return String(global.add30min(value));
                } catch (err) {
                    /* fall through to the local clock fallback */
                }
            }
            const match = /^(\d{1,2}):(\d{2})$/.exec(String(value || ''));
            if (!match) return '';
            const total = (Number(match[1]) * 60 + Number(match[2]) + 30) % (24 * 60);
            const hh = String(Math.floor(total / 60));
            const mm = String(total % 60);
            return (hh.length < 2 ? '0' + hh : hh) + ':' + (mm.length < 2 ? '0' + mm : mm);
        }

        /* End of a half-hour index (exclusive) as an axis clock string:
           `hours[end]` when present, else +30 minutes from the last slot. */
        function clockEnd(hours, endExclusive) {
            if (!Array.isArray(hours)) return '';
            const end = Number(endExclusive);
            if (!Number.isInteger(end)) return '';
            if (end >= 0 && end < hours.length && hours[end]) return String(hours[end]);
            const last = end - 1;
            if (last < 0 || last >= hours.length || !hours[last]) return '';
            return addHalfHour(hours[last]);
        }

        /* 24-hour axis label for one row: `08:00–08:30`. */
        function hourRangeText(hours, startIndex, endExclusive) {
            const start = startIndex >= 0 && startIndex < hours.length ? String(hours[startIndex] || '') : '';
            const end = clockEnd(hours, endExclusive);
            if (start && end) return start + '\u2013' + end;
            return start || end;
        }

        /* Readable 12-hour range for the saved block. Falls back to the
           snapshot's own 24-hour strings when the shared helpers are absent. */
        function twelveHourRange(hours, startIndex, endExclusive) {
            const start = startIndex >= 0 && startIndex < hours.length ? String(hours[startIndex] || '') : '';
            const end = clockEnd(hours, endExclusive);
            const convert = typeof global.to12h === 'function' ? global.to12h : null;
            const show = function (value) {
                if (!value) return '';
                if (!convert) return value;
                try {
                    return String(convert(value));
                } catch (err) {
                    return value;
                }
            };
            const startText = show(start);
            const endText = show(end);
            if (startText && endText) return startText + ' ~ ' + endText;
            return startText || endText;
        }

        /* Validate a saved entry copy from the snapshot (`dayIndex`, exclusive
           `endHour`, original indexes). */
        function entrySpan(entry) {
            if (!entry || typeof entry !== 'object') return null;
            const dayIndex = Number(entry.dayIndex);
            const startHour = Number(entry.startHour);
            const endHour = Number(entry.endHour);
            if (!Number.isInteger(dayIndex) || dayIndex < 0) return null;
            if (!Number.isInteger(startHour) || startHour < 0) return null;
            if (!Number.isInteger(endHour) || endHour <= startHour) return null;
            return { dayIndex: dayIndex, startHour: startHour, endHour: endHour, slotCount: endHour - startHour };
        }

        /* The saved span for the CURRENT week+venue+viewed day. Every saved
           entry is read (not just the visible pointer); legacy multiple
           entries are displayed by T12, so the timeline still draws one. */
        function selectedSpanFor(snapshot, weekIndex, dayIndex, hours) {
            if (!snapshot || weekIndex === null || dayIndex === null) return null;
            const hourCount = Array.isArray(hours) ? hours.length : 0;
            if (!hourCount) return null;
            const selections = snapshot.selections || {};
            const venueKey = String(snapshot.venueCode || '');
            const matches = function (entry) {
                if (!entry || typeof entry !== 'object') return false;
                if (Number(entry.weekIndex) !== weekIndex) return false;
                if (String(entry.venueKey || '') !== venueKey) return false;
                return Number(entry.dayIndex) === dayIndex;
            };
            const usable = function (entry) {
                if (!matches(entry)) return null;
                const span = entrySpan(entry);
                if (!span) return null;
                return span.endHour <= hourCount ? span : null;
            };
            const list = Array.isArray(selections.entries) ? selections.entries : [];
            for (let i = 0; i < list.length; i++) {
                const span = usable(list[i]);
                if (span) return span;
            }
            return usable(selections.current);
        }

        /* The span may only be painted where this view's canonical cells are
           really free/selected. An expired or unrenderable saved entry (a
           blocked, unknown or too-soon cell under it) stays normal rows and
           is never painted blue over them. */
        function spanRenderable(cellsByHour, span) {
            if (!span) return false;
            for (let hour = span.startHour; hour < span.endHour; hour++) {
                const cell = cellsByHour[hour];
                if (!cell || cell.known !== true) return false;
                const classes = canonicalStateClasses(cell);
                if (!hasStateClass(classes, 'cell-selected') && !hasStateClass(classes, 'cell-available')) {
                    return false;
                }
            }
            return true;
        }

        function createTimelineRow(doc, hours, hourIndex, cell) {
            const button = doc.createElement('button');
            button.setAttribute('type', 'button');
            const classes = canonicalStateClasses(cell);
            const known = !!cell && cell.known === true && classes.length > 0;
            button.className = known ? ROW_CLASS + ' ' + classes.join(' ') : ROW_CLASS + ' ' + ROW_UNKNOWN_CLASS;
            button.setAttribute('data-mobile-row', 'hour');
            button.setAttribute(HOUR_ROW_ATTRIBUTE, String(hourIndex));
            button.setAttribute(ROW_DAY_ATTRIBUTE, String(viewedDayIndex));
            button.setAttribute(ROW_STATE_ATTRIBUTE, known ? primaryStateToken(classes) : 'unknown');
            /* Unknown/missing canonical cells fail closed: not a green row and
               not an interactive selection target. */
            if (!known) {
                button.disabled = true;
                button.setAttribute('aria-disabled', 'true');
            }
            const range = hourRangeText(hours, hourIndex, hourIndex + 1);
            const stateText = known ? timelineStateText(cell, classes) : 'Unavailable';
            const time = doc.createElement('span');
            time.className = 'arrangement-mobile-row-time';
            time.textContent = range;
            const state = doc.createElement('span');
            state.className = 'arrangement-mobile-row-state';
            state.textContent = stateText;
            button.appendChild(time);
            button.appendChild(state);
            button.setAttribute('aria-label', range + ', ' + stateText);
            return button;
        }

        /* ONE blue block at the saved start hour; its grid row span gives it
           the equivalent height of the covered half-hours (which are skipped,
           so no row is duplicated). */
        function createSelectedSpan(doc, snapshot, hours, span) {
            const button = doc.createElement('button');
            button.setAttribute('type', 'button');
            button.className = SPAN_CLASS;
            button.setAttribute('data-mobile-row', 'span');
            button.setAttribute(ROW_STATE_ATTRIBUTE, 'selected');
            button.setAttribute(HOUR_ROW_ATTRIBUTE, String(span.startHour));
            button.setAttribute(ROW_DAY_ATTRIBUTE, String(span.dayIndex));
            button.setAttribute(SPAN_SIZE_ATTRIBUTE, String(span.slotCount));
            if (button.style) button.style.gridRow = 'span ' + span.slotCount;
            else button.setAttribute('style', 'grid-row: span ' + span.slotCount);
            const timeText = twelveHourRange(hours, span.startHour, span.endHour);
            const time = doc.createElement('span');
            time.className = 'arrangement-mobile-span-time';
            time.textContent = timeText;
            button.appendChild(time);
            const venueText = String(
                (snapshot.controls && snapshot.controls.venue && snapshot.controls.venue.text)
                || snapshot.venueCode || ''
            ).trim();
            if (venueText) {
                const venue = doc.createElement('span');
                venue.className = 'arrangement-mobile-span-venue';
                venue.textContent = venueText;
                button.appendChild(venue);
            }
            button.setAttribute('aria-label', 'Selected ' + timeText + (venueText ? ', ' + venueText : ''));
            return button;
        }

        /**
         * Render the viewed day as ordered 30-minute rows plus, when the
         * canonical cells allow it, one continuous blue saved span. Read-only:
         * no port read, no refresh, no selection/store mutation.
         * @param {object|null} snapshot cached canonical snapshot
         */
        function renderTimeline(snapshot) {
            if (!root || destroyed) return null;
            const host = root.querySelector(TIMELINE_SELECTOR);
            if (!host) return null;
            const doc = root.ownerDocument || global.document;
            if (!doc) return null;
            /* Adapter-owned children only; clearing also drops stale rows. */
            while (host.firstChild) host.removeChild(host.firstChild);

            const snap = snapshot && typeof snapshot === 'object' ? snapshot : null;
            const weeks = snap && Array.isArray(snap.weeks) ? snap.weeks : [];
            const weekIndex = snap && Number.isInteger(snap.weekIndex) ? snap.weekIndex : null;
            const week = weekIndex !== null && weeks[weekIndex] ? weeks[weekIndex] : null;
            const days = week && Array.isArray(week.days) ? week.days : [];
            const day = dayFor(days, viewedDayIndex);
            const hours = snap && Array.isArray(snap.hours) ? snap.hours : [];

            /* No snapshot, no eligible viewed day, no axis, or T07's empty
               semester: hidden, never a stale interactive timeline. */
            if (!snap || !day || viewedDayIndex === null || !hours.length || emptyState) {
                host.hidden = true;
                return null;
            }
            host.hidden = false;

            const cells = Array.isArray(snap.cells) ? snap.cells : [];
            const cellsByHour = Object.create(null);
            for (let i = 0; i < cells.length; i++) {
                const cell = cells[i];
                if (!cell) continue;
                if (Number(cell.dayIndex) !== viewedDayIndex) continue;
                const hourIndex = Number(cell.hourIndex);
                if (!Number.isInteger(hourIndex) || hourIndex < 0 || hourIndex >= hours.length) continue;
                cellsByHour[hourIndex] = cell;
            }

            const span = selectedSpanFor(snap, weekIndex, viewedDayIndex, hours);
            const drawSpan = spanRenderable(cellsByHour, span);

            for (let hi = 0; hi < hours.length; hi++) {
                if (drawSpan && hi === span.startHour) {
                    host.appendChild(createSelectedSpan(doc, snap, hours, span));
                    hi = span.endHour - 1;
                    continue;
                }
                host.appendChild(createTimelineRow(doc, hours, hi, cellsByHour[hi] || null));
            }
            return host;
        }

        function onDayClick(event) {
            if (!mounted || destroyed) return;
            const target = event && event.target;
            if (!target || typeof target.closest !== 'function') return;
            const button = target.closest(DAYS_SELECTOR + ' [data-day-index]');
            if (!button) return;
            const snap = lastSnapshot;
            const week = snap && Number.isInteger(snap.weekIndex) && Array.isArray(snap.weeks)
                ? snap.weeks[snap.weekIndex] : null;
            const days = week && Array.isArray(week.days) ? week.days : [];
            const index = Number(button.getAttribute('data-day-index'));
            if (!isEligibleDay(dayFor(days, index))) return;
            const previousDay = viewedDayIndex;
            viewedDayIndex = index;
            lastPresentedDayIndex = index;
            /* Day browsing is presentation only: re-render from the cached
               snapshot, never rebuild the week or write the booking store. */
            renderDays(days);
            renderDayHeading(days);
            renderTimeline(lastSnapshot);
            /* T13: one polite confirmation per real day change. */
            if (previousDay !== index) announceDay(index, days);
        }

        /* ── T10: tap routing and pre-checks (read-only until the page port) ── */

        function snapshotDays(snapshot) {
            if (!snapshot || !Number.isInteger(snapshot.weekIndex) || !Array.isArray(snapshot.weeks)) return [];
            const week = snapshot.weeks[snapshot.weekIndex];
            return week && Array.isArray(week.days) ? week.days : [];
        }

        function snapshotCell(snapshot, dayIndex, hourIndex) {
            const cells = snapshot && Array.isArray(snapshot.cells) ? snapshot.cells : [];
            for (let i = 0; i < cells.length; i++) {
                const cell = cells[i];
                if (!cell || typeof cell !== 'object') continue;
                if (Number(cell.dayIndex) !== dayIndex) continue;
                if (Number(cell.hourIndex) !== hourIndex) continue;
                return cell;
            }
            return null;
        }

        /* The cached snapshot must still agree with the row the user tapped:
           same viewed original day, an eligible date and a known canonical
           free cell. Anything else is stale (or blocked, which is T11). */
        function rowIsSelectable(snapshot, dayIndex, hourIndex) {
            if (!snapshot || dayIndex !== viewedDayIndex) return false;
            if (!isEligibleDay(dayFor(snapshotDays(snapshot), dayIndex))) return false;
            const cell = snapshotCell(snapshot, dayIndex, hourIndex);
            if (!cell || cell.known !== true) return false;
            return hasStateClass(canonicalStateClasses(cell), 'cell-available');
        }

        /* Any saved entry anywhere — including another week/venue — blocks a
           new selection. Nothing is replaced, cleared or optimistically drawn. */
        function hasSavedSelection(snapshot) {
            const selections = snapshot && snapshot.selections;
            if (!selections || typeof selections !== 'object') return false;
            const entries = Array.isArray(selections.entries) ? selections.entries : [];
            if (entries.length > 0) return true;
            const total = Number(selections.totalSlots);
            if (Number.isFinite(total) && total > 0) return true;
            return !!selections.current;
        }

        function notifyTransient(message) {
            if (!showTransientPort || typeof message !== 'string' || !message) return;
            try {
                showTransientPort(message);
            } catch (err) {
                /* The page owns the shared toast; a failure changes nothing. */
            }
        }

        /**
         * One delegated root click: T11's info routing (sheet chrome, legend,
         * Tip, blue span, blocked rows) runs first, then only a real
         * `data-mobile-row="hour"` with `data-mobile-state="available"` reaches
         * the T10 canonical selection path. The page port is called at most
         * once and never before the cached snapshot re-check. No optimistic
         * DOM/state write happens here.
         */
        function onTimelineClick(event) {
            if (!mounted || destroyed || !mobileActive || !root) return;
            const target = event && event.target;
            if (!target || typeof target.closest !== 'function') return;
            /* T11: sheets/legend/Tip/removal run first; only an available hour
               row falls through to the T10 selection routing below. */
            if (routeInfoClick(target)) return;
            const row = target.closest('[' + ROW_KIND_ATTRIBUTE + '="hour"]');
            if (!row) return;
            const timeline = row.closest(TIMELINE_SELECTOR);
            if (!timeline) return;
            if (row.getAttribute(ROW_STATE_ATTRIBUTE) !== 'available') return;
            const rawDay = row.getAttribute(ROW_DAY_ATTRIBUTE);
            const rawHour = row.getAttribute(HOUR_ROW_ATTRIBUTE);
            if (rawDay === null || rawHour === null) return;
            const dayIndex = Number(rawDay);
            const hourIndex = Number(rawHour);
            if (!Number.isInteger(dayIndex) || dayIndex < 0) return;
            if (!Number.isInteger(hourIndex) || hourIndex < 0) return;

            const snap = lastSnapshot;
            if (!rowIsSelectable(snap, dayIndex, hourIndex)) {
                notifyTransient(STALE_ROW_TEXT);
                return;
            }
            if (hasSavedSelection(snap)) {
                notifyTransient(ALREADY_SELECTED_TEXT);
                return;
            }
            if (!chooseAvailablePort) return;
            try {
                /* The page resolves the real canonical cell and calls the
                   existing toggleCell() once; all guard feedback stays there. */
                chooseAvailablePort(dayIndex, hourIndex);
            } catch (err) {
                /* The page's existing guard/toast path owns its failures. */
            }
        }

        /* ── T11: information sheets, legend, Tip and exact-keyed removal ── */

        /* The relocated Tip trigger may be inside or outside the mobile root;
           resolve the real node either way (never a copy). */
        function summaryTipNode() {
            if (!root) return null;
            const doc = root.ownerDocument || global.document;
            if (mobileActive) {
                const inRoot = root.querySelector(TIP_TRIGGER_SELECTOR);
                if (inRoot) return inRoot;
            }
            return doc && typeof doc.getElementById === 'function'
                ? doc.getElementById('summaryTip') : null;
        }

        /* The live state-aware Tip: the trigger's data-tip first, then the
           always-maintained aria-label (the adapter keeps that untouched). */
        function tipTextFrom(snapshot) {
            const tips = snapshot && snapshot.tips && snapshot.tips.summary;
            const direct = tips && typeof tips.tip === 'string' ? tips.tip.trim() : '';
            if (direct) return direct;
            const aria = tips && typeof tips.ariaLabel === 'string' ? tips.ariaLabel.trim() : '';
            return aria.replace(TIP_LABEL_PREFIX, '').trim();
        }

        /* Suppress ONLY the relocated trigger's shared hover/focus tooltip by
           removing its data-tip while mobile; the aria-label and the page's
           controller message stay authoritative. Desktop restores the latest
           message (not the boot-stale one) on breakpoint exit. */
        function applySummaryTip(snapshot) {
            const next = tipTextFrom(snapshot);
            if (next) tipMessage = next;
            const tip = summaryTipNode();
            if (!tip || typeof tip.getAttribute !== 'function') return;
            if (mobileActive) {
                if (tip.getAttribute('data-tip') !== null && typeof tip.removeAttribute === 'function') {
                    tip.removeAttribute('data-tip');
                    tipSuppressed = true;
                }
                return;
            }
            if (!tipSuppressed) return;
            if (tipMessage && typeof tip.setAttribute === 'function') {
                tip.setAttribute('data-tip', tipMessage);
            }
            tipSuppressed = false;
        }

        /* An existing critical (non-info) modal owns the screen: info must not
           sit above it, and opening info closes any info sheet first. */
        function criticalModalOpen() {
            if (!root) return false;
            const doc = root.ownerDocument || global.document;
            if (!doc) return false;
            const confirm = typeof doc.getElementById === 'function' ? doc.getElementById('confirmModal') : null;
            if (confirm && confirm.style) {
                const display = String(confirm.style.display || '');
                if (display && display !== 'none') return true;
            }
            if (typeof doc.querySelectorAll === 'function') {
                const open = doc.querySelectorAll('dialog[open]');
                const ours = typeof root.querySelector === 'function' ? root.querySelector(INFO_SELECTOR) : null;
                for (let i = 0; i < open.length; i++) {
                    if (open[i] !== ours) return true;
                }
            }
            return false;
        }

        /* One sheet flow: title + DOM-only body + optional text actions. Never
           writes HTML, never sets a timer, never mutates booking state. */
        function openInfo(title, contentBuilder, actions) {
            if (!mounted || destroyed || !mobileActive || !root) return false;
            const dialog = root.querySelector(INFO_SELECTOR);
            if (!dialog) return false;
            const doc = root.ownerDocument || global.document;
            if (!doc) return false;
            const body = dialog.querySelector(INFO_BODY_SELECTOR);
            if (!body) return false;
            if (criticalModalOpen()) {
                closeInfo();
                return false;
            }
            const heading = dialog.querySelector(INFO_HEADING_SELECTOR);
            const actionHost = dialog.querySelector(INFO_ACTIONS_SELECTOR);
            if (heading) heading.textContent = title === null || title === undefined ? '' : String(title);
            while (body.firstChild) body.removeChild(body.firstChild);
            if (actionHost) {
                while (actionHost.firstChild) actionHost.removeChild(actionHost.firstChild);
            }
            removalRequested = false;

            if (typeof contentBuilder === 'function') {
                let content = null;
                try {
                    content = contentBuilder(doc);
                } catch (err) {
                    content = null;
                }
                if (Array.isArray(content)) {
                    content.forEach(function (node) {
                        if (node) body.appendChild(node);
                    });
                } else if (content) {
                    body.appendChild(content);
                }
            }

            if (actionHost && Array.isArray(actions)) {
                actions.forEach(function (spec) {
                    if (!spec || typeof spec.action !== 'function') return;
                    const button = doc.createElement('button');
                    button.setAttribute('type', 'button');
                    button.className = spec.className || 'btn btn-outline';
                    button.textContent = spec.label === null || spec.label === undefined ? '' : String(spec.label);
                    button.addEventListener('click', function (event) {
                        if (event && typeof event.preventDefault === 'function') event.preventDefault();
                        spec.action(event);
                    });
                    actionHost.appendChild(button);
                });
            }

            if (dialog.open) return true;
            if (typeof dialog.showModal !== 'function') return false;
            /* Prefer the tapped control so touch focus returns reliably; fall
               back to the document's active element. The descriptor survives a
               rebuilt opener; T13 still keeps the node as a last resort. */
            infoOpener = infoOpenerCandidate || doc.activeElement || null;
            infoOpenerCandidate = null;
            infoFocusDescriptor = focusDescriptorFor(infoOpener);
            infoFocusPending = true;
            infoDeferFocus = false;
            focusAfterRender = null;
            try {
                dialog.showModal();
            } catch (err) {
                infoOpener = null;
                infoFocusDescriptor = null;
                infoFocusPending = false;
                return false;
            }
            /* T13: named dialog with an explicit initial target. Native
               showModal focus containment still owns Tab/Shift+Tab. */
            focusInfoClose(dialog);
            return true;
        }

        /* Close once; the native close event restores focus, with an explicit
           fallback for engines that do not emit it synchronously. */
        function closeInfo() {
            if (!root) return false;
            const dialog = root.querySelector(INFO_SELECTOR);
            if (!dialog || !dialog.open) return false;
            try {
                if (typeof dialog.close === 'function') dialog.close();
                else dialog.open = false;
            } catch (err) {
                dialog.open = false;
            }
            if (infoFocusPending) onInfoClosed();
            return true;
        }

        /* T13: describe an opener by identity, not by node lifetime. A settled
           render replaces every summary/row/span button; the descriptor lets
           the close handler find the CURRENT control. */
        function focusDescriptorFor(node) {
            if (!node || typeof node !== 'object') return null;
            if (node === summaryTipNode()) return { kind: 'tip' };
            if (typeof node.closest === 'function') {
                if (node.closest(INFO_SELECTOR)) return null;
                if (node.closest(SUMMARY_OPENER_SELECTOR)) return { kind: 'summary' };
                if (node.closest(LEGEND_SELECTOR)) return { kind: 'legend' };
                const span = node.closest('[' + ROW_KIND_ATTRIBUTE + '="' + SPAN_KIND + '"]');
                if (span) {
                    return {
                        kind: 'span',
                        dayIndex: Number(span.getAttribute(ROW_DAY_ATTRIBUTE)),
                        hourIndex: Number(span.getAttribute(HOUR_ROW_ATTRIBUTE)),
                    };
                }
                const row = node.closest('[' + ROW_KIND_ATTRIBUTE + '="hour"]');
                if (row) {
                    return {
                        kind: 'row',
                        dayIndex: Number(row.getAttribute(ROW_DAY_ATTRIBUTE)),
                        hourIndex: Number(row.getAttribute(HOUR_ROW_ATTRIBUTE)),
                    };
                }
            }
            return null;
        }

        /* Focus only a focusable, currently visible control. */
        function focusableNode(node) {
            if (!node || typeof node.focus !== 'function') return null;
            if (node.isConnected === false) return null;
            if (node.disabled) return null;
            if (node.hidden) return null;
            let cursor = node;
            while (cursor) {
                if (cursor.hidden) return null;
                cursor = cursor.parentNode;
            }
            return node;
        }

        function focusNode(node) {
            if (!node || typeof node.focus !== 'function') return false;
            try {
                node.focus();
                return true;
            } catch (err) {
                return false;
            }
        }

        function currentDayButton() {
            if (!root) return null;
            const host = root.querySelector(DAYS_SELECTOR);
            if (!host) return null;
            const pressed = host.querySelector('[aria-pressed="true"]');
            if (pressed) return pressed;
            if (viewedDayIndex === null) return null;
            return host.querySelector('[data-day-index="' + viewedDayIndex + '"]');
        }

        function firstVisibleRow() {
            if (!root) return null;
            const timeline = root.querySelector(TIMELINE_SELECTOR);
            if (!timeline || timeline.hidden) return null;
            const list = timeline.querySelectorAll('[' + ROW_KIND_ATTRIBUTE + '="hour"]');
            for (let i = 0; i < list.length; i++) {
                if (!list[i].disabled) return list[i];
            }
            return null;
        }

        /* Descriptor -> the corresponding CURRENT control, then the frozen
           fallback order: summary opener, current day, first visible row,
           legend. */
        function resolveFocusTarget(descriptor) {
            if (!root) return null;
            if (descriptor && descriptor.kind === 'summary') {
                const summary = focusableNode(root.querySelector(SUMMARY_OPENER_SELECTOR));
                if (summary) return summary;
            } else if (descriptor && descriptor.kind === 'legend') {
                const legend = focusableNode(root.querySelector(LEGEND_SELECTOR));
                if (legend) return legend;
            } else if (descriptor && descriptor.kind === 'tip') {
                const tip = focusableNode(summaryTipNode());
                if (tip) return tip;
            } else if (descriptor && (descriptor.kind === 'row' || descriptor.kind === 'span')) {
                const timeline = root.querySelector(TIMELINE_SELECTOR);
                if (timeline) {
                    const kind = descriptor.kind === 'span' ? SPAN_KIND : 'hour';
                    const list = timeline.querySelectorAll('[' + ROW_KIND_ATTRIBUTE + '="' + kind + '"]');
                    for (let i = 0; i < list.length; i++) {
                        if (Number(list[i].getAttribute(ROW_DAY_ATTRIBUTE)) !== descriptor.dayIndex) continue;
                        if (Number(list[i].getAttribute(HOUR_ROW_ATTRIBUTE)) !== descriptor.hourIndex) continue;
                        const match = focusableNode(list[i]);
                        if (match) return match;
                    }
                }
            }
            return focusableNode(root.querySelector(SUMMARY_OPENER_SELECTOR))
                || focusableNode(currentDayButton())
                || focusableNode(firstVisibleRow())
                || focusableNode(root.querySelector(LEGEND_SELECTOR));
        }

        function focusInfoClose(dialog) {
            if (!dialog || typeof dialog.querySelector !== 'function') return;
            const close = dialog.querySelector(INFO_CLOSE_SELECTOR);
            if (focusableNode(close)) focusNode(close);
        }

        function onInfoClosed() {
            const descriptor = infoFocusDescriptor;
            const opener = infoOpener;
            infoFocusDescriptor = null;
            infoOpener = null;
            if (!infoFocusPending) return;
            infoFocusPending = false;
            /* T13: Remove closes the sheet before the settled refresh rebuilds
               its rows; wait for that render so focus lands on the replacement,
               never on a disconnected node. */
            if (infoDeferFocus) {
                infoDeferFocus = false;
                focusAfterRender = descriptor;
                return;
            }
            if (criticalModalOpen()) return;
            focusNode(resolveFocusTarget(descriptor) || focusableNode(opener));
        }

        /* Called at the end of a settled render only. Exactly once. */
        function restoreFocusAfterRender() {
            if (!focusAfterRender) return;
            const descriptor = focusAfterRender;
            focusAfterRender = null;
            if (criticalModalOpen()) return;
            focusNode(resolveFocusTarget(descriptor));
        }

        /* ── T13: one polite announcement channel ──
           Every message is written as text; no markup and no repeated message
           for an unchanged refresh. */
        function announce(message) {
            if (!root || destroyed) return;
            const live = root.querySelector(LIVE_SELECTOR);
            if (!live) return;
            const text = message === null || message === undefined ? '' : String(message);
            if (!text) {
                live.textContent = '';
                return;
            }
            /* Re-setting identical text is not announced by some readers. */
            if (live.textContent === text) live.textContent = '';
            live.textContent = text;
        }

        function announceDay(dayIndex, days) {
            const day = dayFor(days, dayIndex);
            if (!day) return;
            announce('Showing ' + dayText(day));
        }

        /* Number of saved slots, defensively: totalSlots is authoritative;
           legacy payloads without it fall back to the entry count. */
        function selectionTotalOf(snapshot) {
            const selections = snapshot && snapshot.selections;
            if (!selections || typeof selections !== 'object') return null;
            const total = Number(selections.totalSlots);
            if (Number.isFinite(total) && total >= 0) return total;
            return Array.isArray(selections.entries) ? selections.entries.length : 0;
        }

        function noteSelectionChange(snapshot) {
            const total = selectionTotalOf(snapshot);
            if (total === null) return;
            if (!selectionTotalBaselineSet) {
                selectionTotalBaselineSet = true;
                lastSelectionTotal = total;
                return;
            }
            if (total === lastSelectionTotal) return;
            const previous = lastSelectionTotal;
            lastSelectionTotal = total;
            if (total > previous) announce('Selection saved.');
            else announce('Selection removed.');
        }

        function sheetFactList(doc, facts) {
            const list = doc.createElement('ul');
            list.className = 'arrangement-mobile-info-facts';
            (facts || []).forEach(function (fact) {
                if (!fact) return;
                const value = fact[1] === null || fact[1] === undefined ? '' : String(fact[1]).trim();
                if (!value) return;
                const item = doc.createElement('li');
                const label = doc.createElement('span');
                label.className = 'arrangement-mobile-info-fact-label';
                label.textContent = String(fact[0]);
                const text = doc.createElement('span');
                text.className = 'arrangement-mobile-info-fact-value';
                text.textContent = value;
                item.appendChild(label);
                item.appendChild(text);
                list.appendChild(item);
            });
            return list;
        }

        /* Only permitted canonical reason text: whole lines starting with the
           desktop selection-wipe hint are removed; blanks are tidied without
           flattening real multiline holder text. */
        function permittedReasonText(raw) {
            const source = raw === null || raw === undefined ? '' : String(raw);
            const lines = source.replace(/\r\n?/g, '\n').split('\n');
            const kept = [];
            for (let i = 0; i < lines.length; i++) {
                const line = lines[i].replace(/[ \t]+$/, '');
                if (SELECTION_WIPE_PREFIX.test(line)) continue;
                kept.push(line);
            }
            let text = kept.join('\n');
            text = text.replace(/^\s*\n+/, '').replace(/\n+\s*$/, '');
            text = text.replace(/\n{3,}/g, '\n\n');
            return text.trim();
        }

        function sheetReasonBlock(doc, raw) {
            const text = permittedReasonText(raw);
            if (!text) return null;
            const para = doc.createElement('p');
            para.className = 'arrangement-mobile-info-reason';
            para.textContent = text;
            return para;
        }

        function sheetVenueText(snapshot) {
            const controls = snapshot && snapshot.controls ? snapshot.controls : {};
            const text = controls.venue && typeof controls.venue.text === 'string'
                ? controls.venue.text.trim() : '';
            if (text) return text;
            return String((snapshot && snapshot.venueCode) || '').trim();
        }

        function sheetTimeText(snapshot, start, endExclusive) {
            const hours = snapshot && Array.isArray(snapshot.hours) ? snapshot.hours : [];
            return twelveHourRange(hours, start, endExclusive);
        }

        function durationText(slotCount) {
            const slots = Number(slotCount);
            if (!Number.isInteger(slots) || slots <= 0) return '';
            const hours = slots / 2;
            const label = Number.isInteger(hours) ? String(hours) : hours.toFixed(1);
            return label + (hours === 1 ? ' hour' : ' hours');
        }

        /* Canonical label wins; an empty label falls back to the state token,
           never to invented prose. */
        function sheetStateText(cell, token) {
            const label = cell ? timelineStateText(cell, canonicalStateClasses(cell)) : '';
            if (label && label !== 'Unavailable') return label;
            return STATE_FALLBACK_LABELS[token] || 'Unavailable';
        }

        /* Exact saved identity: current week + venue + day + start hour, read
           from the snapshot entry list first and only then the current pointer. */
        function savedEntryFor(snapshot, weekIndex, venueKey, dayIndex, startHour) {
            if (!snapshot || weekIndex === null || !venueKey) return null;
            const selections = snapshot.selections;
            if (!selections || typeof selections !== 'object') return null;
            const matches = function (entry) {
                if (!entry || typeof entry !== 'object') return false;
                const span = entrySpan(entry);
                if (!span) return false;
                if (Number(entry.weekIndex) !== weekIndex) return false;
                if (String(entry.venueKey || '') !== venueKey) return false;
                if (span.dayIndex !== dayIndex) return false;
                return span.startHour === startHour;
            };
            const entries = Array.isArray(selections.entries) ? selections.entries : [];
            for (let i = 0; i < entries.length; i++) {
                if (matches(entries[i])) return entries[i];
            }
            return matches(selections.current) ? selections.current : null;
        }

        /* Remove through the page's existing removeSavedBlock() once: the exact
           venueKey/weekIndex the sheet displayed. No optimistic delete. */
        function removeSavedSelection(venueKey, weekIndex) {
            if (!removeSelectionPort || removalRequested) return;
            removalRequested = true;
            const venue = String(venueKey || '');
            const week = Number(weekIndex);
            if (venue && Number.isInteger(week) && week >= 0) infoDeferFocus = true;
            closeInfo();
            if (!venue || !Number.isInteger(week) || week < 0) return;
            try {
                removeSelectionPort(venue, week);
            } catch (err) {
                /* The page's removeSavedBlock owns its failure; nothing is drawn. */
            }
            refresh('remove');
        }

        function openSelectedSpanSheet(spanEl) {
            const snap = lastSnapshot;
            if (!snap || !spanEl) return false;
            const dayIndex = Number(spanEl.getAttribute(ROW_DAY_ATTRIBUTE));
            const startHour = Number(spanEl.getAttribute(HOUR_ROW_ATTRIBUTE));
            if (!Number.isInteger(dayIndex) || dayIndex < 0) return false;
            if (!Number.isInteger(startHour) || startHour < 0) return false;
            if (dayIndex !== viewedDayIndex) return false;
            const weekIndex = Number.isInteger(snap.weekIndex) ? snap.weekIndex : null;
            const venueKey = String(snap.venueCode || '');
            const entry = savedEntryFor(snap, weekIndex, venueKey, dayIndex, startHour);
            const spanInfo = entry ? entrySpan(entry) : null;
            const size = Number(spanEl.getAttribute(SPAN_SIZE_ATTRIBUTE));
            const start = spanInfo ? spanInfo.startHour : startHour;
            const end = spanInfo ? spanInfo.endHour
                : (Number.isInteger(size) && size > 0 ? startHour + size : NaN);
            if (!Number.isInteger(end) || end <= start) return false;

            const day = dayFor(snapshotDays(snap), dayIndex);
            const original = snap.original && typeof snap.original === 'object' ? snap.original : {};
            const code = String(original.code || '').trim();
            const name = String(original.name || '').trim();
            const subject = code && name ? code + ' \u2014 ' + name : (name || code);
            const facts = [
                ['Course', subject],
                ['Date', day ? dayText(day) : ''],
                ['Time', sheetTimeText(snap, start, end)],
                ['Duration', durationText(end - start)],
                ['Venue', entry ? String(entry.venueKey || '') : sheetVenueText(snap)],
            ];
            const actions = [];
            if (entry && removeSelectionPort) {
                const venue = String(entry.venueKey || '');
                const week = Number(entry.weekIndex);
                if (venue && Number.isInteger(week) && week >= 0) {
                    actions.push({
                        label: 'Remove selection',
                        className: 'btn btn-danger',
                        action: function () {
                            removeSavedSelection(venue, week);
                        },
                    });
                }
            }
            return openInfo('Saved selection', function (doc) {
                return [sheetFactList(doc, facts)];
            }, actions);
        }

        function openBlockedRowSheet(row) {
            const snap = lastSnapshot;
            if (!snap || !row) return false;
            const dayIndex = Number(row.getAttribute(ROW_DAY_ATTRIBUTE));
            const hourIndex = Number(row.getAttribute(HOUR_ROW_ATTRIBUTE));
            if (!Number.isInteger(dayIndex) || dayIndex < 0) return false;
            if (!Number.isInteger(hourIndex) || hourIndex < 0) return false;
            if (dayIndex !== viewedDayIndex) return false;
            const cell = snapshotCell(snap, dayIndex, hourIndex);
            if (!cell || cell.known !== true) return false;
            const classes = canonicalStateClasses(cell);
            if (!classes.length) return false;
            const token = primaryStateToken(classes);
            const stateLabel = sheetStateText(cell, token);
            const day = dayFor(snapshotDays(snap), dayIndex);
            const facts = [
                ['State', stateLabel],
                ['Date', day ? dayText(day) : ''],
                ['Time', sheetTimeText(snap, hourIndex, hourIndex + 1)],
                ['Venue', sheetVenueText(snap)],
            ];
            return openInfo(stateLabel, function (doc) {
                const nodes = [sheetFactList(doc, facts)];
                let reason = sheetReasonBlock(doc, cell.detailText);
                if (!reason) reason = sheetReasonBlock(doc, cell.ariaText);
                if (reason) nodes.push(reason);
                return nodes;
            }, []);
        }

        function openLegendSheet() {
            return openInfo('Colours & borders', function (doc) {
                const list = doc.createElement('ul');
                list.className = 'arrangement-mobile-info-legend';
                LEGEND_ROWS.forEach(function (row) {
                    const item = doc.createElement('li');
                    const swatch = doc.createElement('span');
                    swatch.className = 'arrangement-mobile-info-swatch ' + row.className;
                    swatch.setAttribute('aria-hidden', 'true');
                    const text = doc.createElement('div');
                    text.className = 'arrangement-mobile-info-legend-text';
                    const label = doc.createElement('strong');
                    label.textContent = row.label;
                    const para = doc.createElement('p');
                    para.textContent = row.text;
                    text.appendChild(label);
                    text.appendChild(para);
                    item.appendChild(swatch);
                    item.appendChild(text);
                    list.appendChild(item);
                });
                return list;
            }, []);
        }

        function openTipSheet() {
            const snap = lastSnapshot;
            const message = tipTextFrom(snap) || tipMessage || TIP_BASE_GUIDANCE;
            return openInfo('Tip', function (doc) {
                const para = doc.createElement('p');
                para.className = 'arrangement-mobile-info-tip';
                para.textContent = message;
                return para;
            }, []);
        }

        /* One routing gate on the existing root click listener: sheet chrome,
           legend, Tip, the blue span and blocked rows. Available hour rows and
           unknown rows return false so T10's selection path stays untouched. */
        function routeInfoClick(target) {
            if (!root || !target || typeof target.closest !== 'function') return false;
            infoOpenerCandidate = null;
            const dialog = root.querySelector(INFO_SELECTOR);
            if (dialog && target.closest(INFO_SELECTOR)) {
                if (target === dialog || target.closest(INFO_CLOSE_SELECTOR)) closeInfo();
                /* Never let a sheet click fall through to rows/tools; action
                   buttons own their own handlers. */
                return true;
            }
            if (!dialog) return false;
            /* T12: the saved-summary opener runs through the same sheet; its
               own button is the opener so focus returns to the bar. */
            const summary = target.closest(SUMMARY_OPENER_SELECTOR);
            if (summary) {
                infoOpenerCandidate = summary;
                openSummarySheet();
                return true;
            }
            const legend = target.closest(LEGEND_SELECTOR);
            if (legend) {
                infoOpenerCandidate = legend;
                openLegendSheet();
                return true;
            }
            if (target.closest(TIP_TRIGGER_SELECTOR)) {
                infoOpenerCandidate = summaryTipNode() || target;
                openTipSheet();
                return true;
            }
            const span = target.closest('[' + ROW_KIND_ATTRIBUTE + '="' + SPAN_KIND + '"]');
            if (span && span.closest(TIMELINE_SELECTOR)) {
                infoOpenerCandidate = span;
                openSelectedSpanSheet(span);
                return true;
            }
            const row = target.closest('[' + ROW_KIND_ATTRIBUTE + '="hour"]');
            if (!row || !row.closest(TIMELINE_SELECTOR)) return false;
            const state = String(row.getAttribute(ROW_STATE_ATTRIBUTE) || '');
            if (state === 'available' || state === 'unknown') return false;
            infoOpenerCandidate = row;
            openBlockedRowSheet(row);
            return true;
        }

        /* ── T12: saved-summary bar (read-only) ── */

        /* Normalise every saved entry (all venues/weeks/days) plus the current
           pointer only as a fail-safe. Entries are never filtered down to the
           viewed week/day: that would hide or drop legacy data. */
        function summaryEntryList(snapshot) {
            const out = [];
            const selections = snapshot && snapshot.selections;
            if (!selections || typeof selections !== 'object') return out;
            const snapshotWeeks = snapshot && Array.isArray(snapshot.weeks) ? snapshot.weeks : [];
            const push = function (entry) {
                if (!entry || typeof entry !== 'object') return;
                const span = entrySpan(entry);
                if (!span) return;
                const weekIndex = Number(entry.weekIndex);
                if (!Number.isInteger(weekIndex) || weekIndex < 0) return;
                const venueKey = String(entry.venueKey || '').trim();
                if (!venueKey) return;
                const week = snapshotWeeks[weekIndex] || null;
                const days = week && Array.isArray(week.days) ? week.days : [];
                out.push({
                    venueKey: venueKey,
                    weekIndex: weekIndex,
                    weekLabel: String((week && week.label) || ('Week ' + (weekIndex + 1))),
                    dayIndex: span.dayIndex,
                    startHour: span.startHour,
                    endHour: span.endHour,
                    slotCount: span.slotCount,
                    day: dayFor(days, span.dayIndex),
                });
            };
            const entries = Array.isArray(selections.entries) ? selections.entries : [];
            for (let i = 0; i < entries.length; i++) push(entries[i]);
            /* Absent/unexpressible entries must not produce a false empty bar:
               the current block still summarises what is really saved. */
            if (!out.length) push(selections.current);
            return out;
        }

        /* Date + 12-hour range for one saved entry (current CRS formats). */
        function summaryEntryTimeText(snapshot, entry) {
            const date = entry.day ? dayText(entry.day) : entry.weekLabel;
            const time = sheetTimeText(snapshot, entry.startHour, entry.endHour);
            return [date, time].filter(Boolean).join(' \u00b7 ');
        }

        /* Venue + duration, shown only when the bar really has room. */
        function summaryEntryMetaText(entry) {
            const duration = durationText(entry.slotCount);
            return [entry.venueKey, duration].filter(Boolean).join(' \u00b7 ');
        }

        function summaryTextNode(doc, className, text) {
            const node = doc.createElement('span');
            node.className = className;
            node.textContent = text === null || text === undefined ? '' : String(text);
            return node;
        }

        function summaryNotice(doc, title, hint, modifier) {
            const box = doc.createElement('div');
            box.className = 'arrangement-mobile-summary arrangement-mobile-summary--' + modifier;
            box.appendChild(summaryTextNode(doc, 'arrangement-mobile-summary-title', title));
            box.appendChild(summaryTextNode(doc, 'arrangement-mobile-summary-meta', hint));
            return box;
        }

        /* T16: defensive read of the page's read-only retention metadata. */
        function retentionInfo(snapshot) {
            const retention = snapshot && snapshot.retention;
            if (!retention || typeof retention !== 'object') return { any: false };
            const count = Number(retention.count);
            if (retention.any === true) return { any: true };
            return { any: Number.isFinite(count) && count > 0 };
        }

        function retentionNotice(doc) {
            return summaryNotice(doc, RETENTION_TITLE, RETENTION_HINT, 'retention');
        }

        function retentionSubmitNode() {
            const doc = root ? (root.ownerDocument || global.document) : global.document;
            return doc && typeof doc.getElementById === 'function'
                ? doc.getElementById('submitRequestBtn') : null;
        }

        /* T18: defensive read of the page's read-only request latch. Only the
           page sets/clears it; the adapter only honours it. */
        function submissionBusy(snapshot) {
            return !!(snapshot && snapshot.submissionBusy === true);
        }

        /* Force the ONE original Submit disabled while a retained entry exists
           or a mobile-origin request is in flight. When neither applies this
           writes nothing: the page's normal counter (total) gate — with the T18
           latch already folded into it — owns the restored value, so no busy
           request can be re-enabled here. */
        function applyRetentionSubmit(snapshot) {
            if (!mobileActive || destroyed) return;
            submissionBusyCached = submissionBusy(snapshot);
            const retained = retentionInfo(snapshot).any;
            retentionSubmitForced = retained;
            if (!retained && !submissionBusyCached) return;
            const btn = retentionSubmitNode();
            if (!btn) return;
            if (!btn.disabled) btn.disabled = true;
        }

        /* Desktop must not inherit the mobile retention lock or the page-owned
           request latch: restore only the page's ordinary "has saved slots"
           gate, and never while a mobile-origin request is still in flight. */
        function releaseRetentionSubmit(snapshot) {
            const busy = submissionBusyCached || submissionBusy(snapshot);
            if (!retentionSubmitForced && !busy) return;
            retentionSubmitForced = false;
            const btn = retentionSubmitNode();
            if (!btn) return;
            const total = Number(snapshot && snapshot.selections && snapshot.selections.totalSlots);
            btn.disabled = busy || !(Number.isFinite(total) && total > 0);
        }

        /* The ONE details opener: real button, never a link or a second form. */
        function summaryButton(doc, title, meta, modifier) {
            const button = doc.createElement('button');
            button.setAttribute('type', 'button');
            button.className = 'arrangement-mobile-summary arrangement-mobile-summary--' + modifier;
            button.setAttribute(SUMMARY_OPENER_ATTRIBUTE, '');
            button.setAttribute('aria-haspopup', 'dialog');
            button.setAttribute('aria-controls', 'arrangementMobileInfo');
            button.setAttribute('aria-label', meta ? title + '. ' + meta : title);
            button.appendChild(summaryTextNode(doc, 'arrangement-mobile-summary-title', title));
            if (meta) button.appendChild(summaryTextNode(doc, 'arrangement-mobile-summary-meta', meta));
            return button;
        }

        /**
         * Fill the mobile action bar from the same read-only snapshot. Reads
         * every saved entry; empty copy carries no details action. Never reads a
         * port, never calls refresh() and never writes booking state.
         * @param {object|null} snapshot
         */
        function renderSummaryBar(snapshot) {
            if (!root || destroyed) return null;
            const host = root.querySelector(SUMMARY_HOST_SELECTOR);
            if (!host) return null;
            const doc = root.ownerDocument || global.document;
            if (!doc) return null;
            while (host.firstChild) host.removeChild(host.firstChild);
            const snap = snapshot && typeof snapshot === 'object' ? snapshot : null;
            const entries = summaryEntryList(snap);
            const retained = retentionInfo(snap);
            if (!entries.length) {
                if (hasSavedSelection(snap)) {
                    host.appendChild(summaryNotice(doc, SUMMARY_SAVED_TITLE, SUMMARY_SAVED_HINT, 'notice'));
                } else {
                    host.appendChild(summaryNotice(doc, SUMMARY_EMPTY_TITLE, SUMMARY_EMPTY_HINT, 'empty'));
                }
                if (retained.any) host.appendChild(retentionNotice(doc));
                return host;
            }
            /* T16: guidance sits in this same bar, above the one details
               opener; there is no second removal control. */
            if (retained.any) host.appendChild(retentionNotice(doc));
            if (entries.length === 1) {
                host.appendChild(summaryButton(
                    doc,
                    summaryEntryTimeText(snap, entries[0]),
                    summaryEntryMetaText(entries[0]),
                    'single'
                ));
                return host;
            }
            host.appendChild(summaryButton(
                doc,
                entries.length + ' selected blocks',
                SUMMARY_REVIEW_HINT,
                'multiple'
            ));
            return host;
        }

        /* One Remove action per entry, each bound to that entry's exact
           venueKey/weekIndex; the existing single-shot guard and port apply. */
        function summaryRemoveActions(entries) {
            const actions = [];
            if (!removeSelectionPort || !entries.length) return actions;
            entries.forEach(function (entry, position) {
                if (!entry.venueKey) return;
                if (!Number.isInteger(entry.weekIndex) || entry.weekIndex < 0) return;
                actions.push({
                    label: entries.length === 1
                        ? 'Remove selection'
                        : 'Remove selection ' + (position + 1),
                    className: 'btn btn-danger',
                    action: function () {
                        removeSavedSelection(entry.venueKey, entry.weekIndex);
                    },
                });
            });
            return actions;
        }

        /**
         * The summary button's details sheet, opened by the SAME T11 dialog.
         * Subject appears once; every saved entry lists date/time/duration/venue
         * with its own keyed Remove. Text/DOM only, no mutation until Remove.
         */
        function openSummarySheet() {
            const snap = lastSnapshot;
            if (!snap) return false;
            const entries = summaryEntryList(snap);
            const original = snap.original && typeof snap.original === 'object' ? snap.original : {};
            const code = String(original.code || '').trim();
            const name = String(original.name || '').trim();
            const subject = code && name ? code + ' \u2014 ' + name : (name || code);
            const total = entries.length;
            const heading = total > 1 ? 'Saved selections' : 'Saved selection';
            const actions = summaryRemoveActions(entries);
            return openInfo(heading, function (doc) {
                const nodes = [];
                if (subject) nodes.push(sheetFactList(doc, [['Course', subject]]));
                if (!total) {
                    const note = doc.createElement('p');
                    note.className = 'arrangement-mobile-info-entry';
                    note.textContent = SUMMARY_SAVED_HINT;
                    nodes.push(note);
                    return nodes;
                }
                entries.forEach(function (entry, position) {
                    if (total > 1) {
                        const title = doc.createElement('p');
                        title.className = 'arrangement-mobile-info-entry';
                        title.textContent = 'Selection ' + (position + 1) + ' of ' + total;
                        nodes.push(title);
                    }
                    nodes.push(sheetFactList(doc, [
                        ['Date', entry.day ? dayText(entry.day) : entry.weekLabel],
                        ['Time', sheetTimeText(snap, entry.startHour, entry.endHour)],
                        ['Duration', durationText(entry.slotCount)],
                        ['Venue', entry.venueKey],
                    ]));
                });
                return nodes;
            }, actions);
        }

        /* ── T14: compact original context + measured insets ── */

        /* Subject label from the SAME snapshot.copy the page's summary uses:
           code/name/type only. No occurrence resolution happens here. */
        function contextSubjectText(original, snapshot) {
            const code = String((original && original.code) || '').trim();
            const name = String((original && original.name) || '').trim();
            const type = String((original && original.type) || '').trim();
            let subject = code && name ? code + ' \u2014 ' + name : (code || name);
            if (subject && type) subject += ' (' + type + ')';
            if (!subject && snapshot && snapshot.controls && snapshot.controls.subject) {
                subject = String(snapshot.controls.subject.text || '').trim();
            }
            return subject;
        }

        /* Compact line for the collapsed <details>: subject, then the real
           original weekday/date and 12-hour start/end. Missing subject ->
           short guidance; missing slot -> subject only. */
        function contextSummaryText(snapshot) {
            const original = snapshot && snapshot.original && typeof snapshot.original === 'object'
                ? snapshot.original : null;
            /* Fail closed on the snapshot's own subject flag: no subject means
               the short guidance, never a stale code/name. */
            if (!original || original.hasSubject !== true) return CONTEXT_EMPTY_TEXT;
            const subject = contextSubjectText(original, snapshot);
            if (!subject) return CONTEXT_EMPTY_TEXT;
            const parts = [subject];
            if (original && original.hasSlot === true) {
                const weekday = String(original.weekdayName || '').trim();
                const date = String(original.date || '').trim();
                const day = weekday && date ? weekday + ', ' + date : (weekday || date);
                if (day) parts.push(day);
                const start = String(original.startTime || '').trim();
                const end = String(original.endTime || '').trim();
                const time = start && end ? start + CONTEXT_TIME_SEPARATOR + end : (start || end);
                if (time) parts.push(time);
            }
            return parts.join(CONTEXT_SEPARATOR);
        }

        function renderContextSummary(snapshot) {
            if (!root || destroyed) return null;
            const node = root.querySelector(CONTEXT_SUMMARY_SELECTOR);
            if (!node) return null;
            node.textContent = contextSummaryText(snapshot);
            return node;
        }

        /* Page-root custom properties live on <body> (an ancestor of both the
           mobile root and the layout-level toast); they are removed on exit. */
        function pageBody() {
            const doc = root ? (root.ownerDocument || global.document) : global.document;
            return doc && doc.body ? doc.body : null;
        }

        function bodyStyle() {
            const body = pageBody();
            return body && body.style ? body.style : null;
        }

        /* A real, positive measured height; null means keep the CSS fallback. */
        function measuredHeight(node) {
            if (!node || typeof node.getBoundingClientRect !== 'function') return null;
            let rect = null;
            try {
                rect = node.getBoundingClientRect();
            } catch (err) {
                return null;
            }
            const height = rect ? Number(rect.height) : NaN;
            if (!Number.isFinite(height) || height <= 0) return null;
            return Math.ceil(height);
        }

        /* Measure the sticky cluster and the fixed action bar. Writing only on
           a real change keeps the observer from re-triggering itself. */
        function measureInsets() {
            if (!mobileActive || destroyed || !root) return;
            const style = bodyStyle();
            if (!style || typeof style.setProperty !== 'function') return;
            const sticky = measuredHeight(root.querySelector(CONTROLS_SELECTOR));
            if (sticky !== null) {
                const stickyValue = sticky + 'px';
                if (stickyValue !== lastStickyHeight) {
                    style.setProperty(STICKY_HEIGHT_VAR, stickyValue);
                    lastStickyHeight = stickyValue;
                }
            }
            const bar = measuredHeight(root.querySelector(ACTIONS_SELECTOR));
            if (bar !== null) {
                const barValue = bar + 'px';
                if (barValue !== lastBarHeight) {
                    style.setProperty(BAR_HEIGHT_VAR, barValue);
                    lastBarHeight = barValue;
                }
            }
        }

        /* One ResizeObserver for both measured hosts; the window resize
           listener is only the fallback when ResizeObserver is unavailable. */
        function attachInsets() {
            if (destroyed || !mobileActive || !root) return;
            const controls = root.querySelector(CONTROLS_SELECTOR);
            const actions = root.querySelector(ACTIONS_SELECTOR);
            const Observer = global.ResizeObserver;
            if (!insetsObserver && typeof Observer === 'function' && (controls || actions)) {
                try {
                    insetsObserver = new Observer(function () { measureInsets(); });
                    if (controls) insetsObserver.observe(controls);
                    if (actions) insetsObserver.observe(actions);
                } catch (err) {
                    insetsObserver = null;
                }
            }
            if (!insetsObserver && !resizeMeasureBound && typeof global.addEventListener === 'function') {
                global.addEventListener('resize', measureInsets);
                resizeMeasureBound = true;
            }
        }

        function clearMeasuredInsets() {
            const style = bodyStyle();
            if (style && typeof style.removeProperty === 'function') {
                style.removeProperty(STICKY_HEIGHT_VAR);
                style.removeProperty(BAR_HEIGHT_VAR);
            }
            lastStickyHeight = '';
            lastBarHeight = '';
        }

        function detachInsets() {
            if (insetsObserver) {
                try {
                    insetsObserver.disconnect();
                } catch (err) {
                    /* An observer that cannot disconnect is simply dropped. */
                }
                insetsObserver = null;
            }
            if (resizeMeasureBound) {
                if (typeof global.removeEventListener === 'function') {
                    global.removeEventListener('resize', measureInsets);
                }
                resizeMeasureBound = false;
                /* The listener was the fallback owner; drop its values too. */
            }
            clearMeasuredInsets();
        }

        function attachMediaListener() {
            if (mediaQuery || typeof global.matchMedia !== 'function') return;
            try {
                mediaQuery = global.matchMedia(MOBILE_MEDIA_QUERY);
            } catch (err) {
                mediaQuery = null;
                return;
            }
            if (!mediaQuery) return;
            mediaListener = function (event) {
                if (!mounted || destroyed) return;
                if (event && event.matches) enterMobile();
                else exitMobile();
            };
            if (typeof mediaQuery.addEventListener === 'function') {
                mediaQuery.addEventListener('change', mediaListener);
            } else if (typeof mediaQuery.addListener === 'function') {
                /* Legacy Safari/WebView fallback. */
                mediaQuery.addListener(mediaListener);
            }
        }

        function detachMediaListener() {
            if (!mediaQuery || !mediaListener) {
                mediaQuery = null;
                mediaListener = null;
                return;
            }
            if (typeof mediaQuery.removeEventListener === 'function') {
                mediaQuery.removeEventListener('change', mediaListener);
            } else if (typeof mediaQuery.removeListener === 'function') {
                mediaQuery.removeListener(mediaListener);
            }
            mediaQuery = null;
            mediaListener = null;
        }

        /* Mount-time sync: desktop stays exactly as rendered; mobile relocates. */
        function syncLayout() {
            if (!mounted || destroyed) return;
            if (mediaQuery && mediaQuery.matches) enterMobile();
            else exitMobile();
        }

        function isMobile() {
            return mobileActive;
        }

        /* T13: the page's document key handler must stand down while the one
           information sheet owns the screen (including breakpoint transitions). */
        function isInfoOpen() {
            if (!root) return false;
            const dialog = root.querySelector(INFO_SELECTOR);
            return !!(dialog && dialog.open);
        }

        /* T13/T23a: explicit native-dialog Tab containment. Native modal focus
           works in mainstream Chromium, but keeping the cycle in the sheet is a
           deterministic accessibility guarantee and also covers engines that
           let focus escape to the page chrome. */
        function infoFocusables(dialog) {
            const selector = 'button:not([disabled]), [href], input:not([disabled]), '
                + 'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
            return Array.prototype.filter.call(dialog.querySelectorAll(selector), function (node) {
                return !node.hidden && (node.offsetParent !== null || node === dialog);
            });
        }

        function onInfoKeydown(event) {
            if (!event || event.key !== 'Tab') return;
            const dialog = root ? root.querySelector(INFO_SELECTOR) : null;
            if (!dialog || !dialog.open) return;
            const list = infoFocusables(dialog);
            if (!list.length) {
                event.preventDefault();
                return;
            }
            const first = list[0];
            const last = list[list.length - 1];
            const active = global.document ? global.document.activeElement : null;
            if (!active || !dialog.contains(active)) {
                event.preventDefault();
                (event.shiftKey ? last : first).focus();
                return;
            }
            if (event.shiftKey && active === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && active === last) {
                event.preventDefault();
                first.focus();
            }
        }

        function mount() {
            if (mounted || destroyed || !root) return;
            mounted = true;
            /* One delegated day listener; buttons are rebuilt per render. */
            if (!daysClickBound && typeof root.addEventListener === 'function') {
                root.addEventListener('click', onDayClick);
                daysClickBound = true;
            }
            /* T10: one delegated timeline listener, bound once at mount. Rows,
               the blue span and breakpoint re-renders never add listeners. */
            if (!timelineClickBound && typeof root.addEventListener === 'function') {
                root.addEventListener('click', onTimelineClick);
                timelineClickBound = true;
            }
            /* T11: the native dialog owns Escape; this only restores focus. */
            const infoDialog = root.querySelector(INFO_SELECTOR);
            if (infoDialog && !infoCloseBound && typeof infoDialog.addEventListener === 'function') {
                infoDialog.addEventListener('close', onInfoClosed);
                infoCloseBound = true;
            }
            if (infoDialog && !infoKeydownBound && typeof infoDialog.addEventListener === 'function') {
                infoDialog.addEventListener('keydown', onInfoKeydown);
                infoKeydownBound = true;
            }
            attachMediaListener();
            syncLayout();
            // T06 owns breakpoint activation only. Day rendering, selection and
            // sheets stay with later tasks; the mount refresh is read-only.
            refresh('mount');
        }

        function destroy() {
            if (destroyed) return;
            exitMobile();
            detachMediaListener();
            /* T14: also covers a destroy that happens while desktop is active
               (exitMobile is a no-op there); both calls are idempotent. */
            detachInsets();
            const infoDialog = root ? root.querySelector(INFO_SELECTOR) : null;
            infoOpener = null;
            infoOpenerCandidate = null;
            infoFocusDescriptor = null;
            infoFocusPending = false;
            infoDeferFocus = false;
            focusAfterRender = null;
            removalRequested = false;
            if (infoCloseBound && infoDialog) {
                if (typeof infoDialog.removeEventListener === 'function') {
                    infoDialog.removeEventListener('close', onInfoClosed);
                }
                infoCloseBound = false;
            }
            if (infoKeydownBound && infoDialog) {
                if (typeof infoDialog.removeEventListener === 'function') {
                    infoDialog.removeEventListener('keydown', onInfoKeydown);
                }
                infoKeydownBound = false;
            }
            if (daysClickBound && root && typeof root.removeEventListener === 'function') {
                root.removeEventListener('click', onDayClick);
            }
            daysClickBound = false;
            if (timelineClickBound && root && typeof root.removeEventListener === 'function') {
                root.removeEventListener('click', onTimelineClick);
            }
            timelineClickBound = false;
            destroyed = true;
            mounted = false;
            refreshQueued = false;
            queuedReason = null;
            /* T19: a fallback-scheduled turn that has not run yet is cancelled
               here; runRefresh() also refuses once destroyed. */
            if (refreshTimer !== null) {
                global.clearTimeout(refreshTimer);
                refreshTimer = null;
            }
            lastSnapshot = null;
            /* Presentation pointers only; booking state stays in the page. */
            viewedDayIndex = null;
            lastWeekIndex = null;
            emptyState = false;
            releaseEmptyControls(true);
            hopFromWeek = null;
            hopAttempts = 0;
            hopRefreshRequested = false;
            pendingReveal = null;
            clearPulse();
            if (!root) return;
            /* Drop adapter-rendered day state; the host is an empty shell. */
            const dayHost = root.querySelector(DAYS_SELECTOR);
            if (dayHost) {
                while (dayHost.firstChild) dayHost.removeChild(dayHost.firstChild);
            }
            const heading = root.querySelector(DAY_HEADING_SELECTOR);
            if (heading) heading.textContent = '';
            /* T14: drop the rendered empty-semester text as well. */
            const emptyMessage = root.querySelector(EMPTY_SELECTOR);
            if (emptyMessage) {
                emptyMessage.textContent = '';
                emptyMessage.hidden = true;
            }
            /* T14: restore the shell's own context summary text (no stale
               subject/original wording survives teardown). */
            const contextSummary = root.querySelector(CONTEXT_SUMMARY_SELECTOR);
            if (contextSummary && contextSummaryWas !== null) {
                contextSummary.textContent = contextSummaryWas;
            }
            /* T09: drop adapter-rendered rows; the host is an empty shell. */
            const timelineHost = root.querySelector(TIMELINE_SELECTOR);
            if (timelineHost) {
                while (timelineHost.firstChild) timelineHost.removeChild(timelineHost.firstChild);
                timelineHost.hidden = true;
            }
            /* T12: drop the adapter-rendered summary; the host is an empty shell. */
            const summaryHost = root.querySelector(SUMMARY_HOST_SELECTOR);
            if (summaryHost) {
                while (summaryHost.firstChild) summaryHost.removeChild(summaryHost.firstChild);
            }
            /* T13: the live region keeps no stale announcement after teardown. */
            const live = root.querySelector(LIVE_SELECTOR);
            if (live) live.textContent = '';
            lastPresentedDayIndex = null;
            selectionTotalBaselineSet = false;
            lastSelectionTotal = null;
            /* T11: drop the sheet's adapter-built body/actions (each action
               button's listener goes with it) and restore its static heading. */
            const dialog = root.querySelector(INFO_SELECTOR);
            const infoBody = root.querySelector(INFO_BODY_SELECTOR);
            if (infoBody) {
                while (infoBody.firstChild) infoBody.removeChild(infoBody.firstChild);
            }
            const infoActions = root.querySelector(INFO_ACTIONS_SELECTOR);
            if (infoActions) {
                while (infoActions.firstChild) infoActions.removeChild(infoActions.firstChild);
            }
            const infoHeading = root.querySelector(INFO_HEADING_SELECTOR);
            if (infoHeading && infoHeadingWas !== null) {
                infoHeading.textContent = infoHeadingWas;
            }
            if (dialog?.open) dialog.close();
            root.hidden = true;
            root.inert = true;
        }

        // Later tasks consume ports.removeSelection/submit and the remaining
        // surfaces. T07 consumes chooseWeek; T08 adds the presentation-only
        // revealTarget; T09 renders the day timeline and saved span; T10 adds
        // the delegated timeline tap that calls the page's canonical
        // chooseAvailable/toggleCell path; T11 adds the one info-sheet flow,
        // legend/Tip sheets and exact-keyed removeSavedBlock via a page port;
        // T12 adds the read-only saved-summary bar that reuses that sheet;
        // T13 adds isInfoOpen, sheet initial/return focus and one live region;
        // T14 adds the compact context summary and the measured sticky/bar
        // insets (one observer + resize fallback, removed on exit/destroy);
        // T16 renders the retained/expired guidance in the SAME bar and keeps
        // the relocated Submit disabled while such an entry exists (its clock
        // timer/listeners belong to the page closure, not this adapter).
        // T18 additionally honours the page-owned request latch
        // (snapshot.submissionBusy) in those same Submit gates; no submit
        // handler is added here.
        // T19 completes teardown: the T05 scheduler (queueMicrotask, or one
        // cancellable fallback timeout), T08 pulse timer, T14 single observer
        // (or window resize fallback), root/dialog delegates, info/focus state,
        // measured insets, pending reveal and every rendered host are released
        // exactly once; the page's clock timeout/listeners belong to the page
        // closure and are torn down by its own pagehide hook.
        // No booking writes live here.
        return { mount, refresh, destroy, snapshot, isMobile, isInfoOpen, revealTarget, closeInfo };
    }

    global.ArrangementMobile = { create: create, utils: utils };
})(window);
