{{-- partials/notif-row.blade.php — SDD notifications-real-bridge T2/D6: THE
     notification row renders server-side (the frozen classes, byte-preserved).
     ONE block serves BOTH branches (the lifecycle D4 law):
       inactive_row or the composed inactive_reason → <span class="notif-row notif-row--inactive">,
                       no href, no data-id (never click-markable) + the reason tip.
       active        → <a class="notif-row [--read]">, data-id + href + the data-tip
                       (the marked target of the durable POST capture listener).
     SDD notif-new-request-ux (T3): `submitted` rows render `_display`
     (the composer's title/subtitle/desc strings, the stored fallbacks);
     the `$rowTip` branches: inactive → the D1/D15 reason copy by
     $inactive_reason; active + `_display['tip']` → the multiline tip with the
     Requested-at line inserted from the island's T4 closures; else the
     legacy `title — body`. aria mirrors (the surviving parts joined out;
     ` (inactive)` suffix). Expects: $n, $glyph, the time closures + the
     Batch-2 $relLong/$tipAbs from the island scope (@include's flow).

     notif-manage-hide (2026-11-02) — an OPTIONAL `$variant` parameter:
       null/absent → TODAY'S OUTPUT BYTE-FOR-BYTE (the frozen partial contract;
                     every pre-existing caller is untouched)
       'pick'      → the manage-mode row: a <label> carrying .bulk-checkbox,
                     the SAME icon/body/time (no anchor — a checkbox inside an
                     <a> is invalid HTML, and the row IS the tick target)
       'hidden'    → the shelf row: non-anchor, dimmed, the time stacked over a
                     small `Unhide` action (.nr-right)
     The activation law, tips and composed strings are shared by all three —
     one copy of the meaning, three carriers. --}}
@php
    $reason = $n->getAttribute('inactive_reason');
    $m = $minutesAgo($n->created_at);
    $display = $n->getAttribute('_display');
    /* the activation law: the classified family rides $reason (the D15
       reason first-match); the legacy non-submitted family's D4 law stays on
       $inactive_row (missing/cancelled only — never the decided). */
    $activation = $reason !== null
        || ($n->inactive_row && ! in_array($n->type, ['submitted', 'awaiting'], true) && $display === null);
    $variant = $variant ?? null;
    $tag = $activation ? 'span' : 'a';
    if ($variant === 'pick') {
        $tag = 'label';
    } elseif ($variant === 'hidden') {
        $tag = 'div';
    }
    $rowClass = 'notif-row'.($activation
        ? ' notif-row--inactive'
        : ($n->read_at !== null ? ' notif-row--read' : ''));
    if ($variant === 'pick') {
        $rowClass .= ' notif-row--pick';
    } elseif ($variant === 'hidden') {
        $rowClass .= ' notif-row--hidden';
    }
    $picked = $variant === 'pick' && in_array((int) $n->id, array_map('intval', $selectedIds ?? []), true);
    if ($picked) {
        $rowClass .= ' row-selected';   /* the promoted pair (My Request History) */
    }
    $composedTitle = $display !== null ? $display['title'] : $n->title;
    /* user spec 2026-11-04 §1: `submitted` rows read Title Case even on the arms
       where the composer never ran (inactive / hidden / requestless legacy) — the
       stored title is the old lowercase string, the row's meaning is unchanged. */
    if ($display === null && $n->type === 'submitted') {
        $composedTitle = (int) $n->request_id > 0
            ? 'New Replacement Request (ID #'.(int) $n->request_id.')'
            : 'New Replacement Request';
    }
    /* notif-inactive-hide-sender (2026-11-05, spec R2/R4): the panel reads Title
       Case, and the two self-owned kinds say `Your`. The STORED title is never
       touched — this is a render-time projection. */
    $senderSelf = (bool) $n->getAttribute('sender_self');
    $senderLine = $n->getAttribute('sender_line');
    /* the KINDS are recognised by type OR by the stored sentence: the emitters
       write the cancellation notice as type `update` (the frozen tile key) and the
       proposer's confirmation as `update` too, so type alone would miss both. */
    $isCancelKind = $n->type === 'cancelled' || strcasecmp((string) $n->title, 'Scheduled class cancelled') === 0;
    $isSubmitKind = $n->type === 'submitted' || strcasecmp((string) $n->title, 'Replacement request submitted') === 0;
    if ($senderSelf && $isCancelKind) {
        $rowTitle = 'Your Scheduled Class Cancelled';
    } elseif ($senderSelf && $isSubmitKind) {
        $rowTitle = 'Your Replacement Request Submitted';
    } else {
        $rowTitle = \App\Support\TitleCase::of($composedTitle);
    }
    /* R3: ONE sender line — `by YOU` / `by Name (staff_id)`. When no actor can be
       resolved the old pair of spans renders instead, so nothing is lost. */
    $senderSubtitle = $senderSelf ? 'by YOU' : ($senderLine !== null && $senderLine !== '' ? 'by '.$senderLine : null);
    $composedBody = $display !== null ? $display['desc'] : $n->body;
    $subtitle = $display !== null ? ($display['subtitle'] ?? null) : null;
    $staffLine = $n->getAttribute('staff_line');   /* R5 — computed per render, survives read/hidden */
    if ($activation) {
        /* D1/D15 — the reason-family copies, first-match by $reason */
        $rowTip = match ($reason) {
            'cancelled' => 'This notification is inactive because the request was cancelled by the requester.',
            'approved' => 'This notification is inactive because the request was approved.',
            'rejected' => 'This notification is inactive because the request was rejected.',
            /* 2026-11-05 (user option B, copy accepted verbatim): the two STALE
               families — a cancellation that was undone, and a decision whose
               request was replaced by a newer one on the same occurrence. */
            'undone' => 'This notification is inactive because the cancellation was undone.',
            'superseded' => 'This notification is inactive because a newer request replaced this one.',
            /* 2026-11-04 (user ruling): there is no `read` reason any more — a
               read row of a still-pending request stays ACTIVE (see
               NavNotifications::resolveRequests). Reading is not a decision. */
            default => 'This notification is inactive because the request was cancelled by the requester.',
        };
        $rowLabel = $rowTitle.' — '.$composedBody.' (inactive)';
    } elseif ($display !== null && $display['tip'] !== null && $display['tip'] !== '') {
        /* T3/T4: the multiline tip = the composer's FACT lines with the dynamic
           Requested-at line 2 inserted after the Staff line (the T4 closures). */
        $lines = explode("\n", $display['tip']);
        /* 2026-11-04 (user spec): Title Case label; line 1 is now
           `Staff (Proposer):` — the guard follows the label, not the old text. */
        $requestedAt = 'Requested At: '.$tipAbs($n->created_at).' ('.$relLong($n->created_at).')';
        if (($lines[0] ?? '') !== '' && str_starts_with($lines[0], 'Staff')) {
            array_splice($lines, 1, 0, [$requestedAt]);
        } else {
            array_splice($lines, 0, 0, [$requestedAt]);
        }
        $rowTip = implode("\n", $lines);
        $rowLabel = $display['aria'];
    } else {
        $rowTip = $n->title.' — '.$n->body;
        $rowLabel = $n->title.' — '.$n->body;
    }
    /* notif-click-highlight: the target anchor rides the row — r(request id)
       for the approval/history surfaces, s(session id)+week for the cancel
       rows landing on /my-timetable-ui. GATED by the row's STORED link: the
       legacy persisted rows (the old /cohort-timetable, /venue-timetable-ui
       strings) emit NOTHING → plain navigation (the lifecycle law). The
       inactive arm stays anchor-less (keep-as-it). */
    $notifTarget = null;
    $notifWeek = null;
    /* 2026-11-04 (user): class news for a LECTURER lands on the merged page, not
       the timetable — My Replacements is where the class and its request state
       live together, so the click can point at the row itself. The stored link
       stays the gate (the lifecycle law); only the LECTURER timetable rows are
       re-pointed, and the legacy persisted `/my-timetable-ui` rows are mapped
       too, so old notifications behave like new ones. Student rows keep
       `/student-my-timetable-ui` untouched. */
    $href = $n->link;
    if (! $activation) {
        if ($n->link === '/request-approval-ui' && $n->request_id !== null) {
            $notifTarget = 'r'.$n->request_id;
        } elseif ($n->link === '/my-request-history-ui' && $n->request_id !== null) {
            $notifTarget = 'r'.$n->request_id;
        } elseif ($n->class_session_id !== null
            && ($n->link === '/my-timetable-ui' || $n->link === '/my-replacements')) {
            /* 2026-11-05: the NEW cancel rows are written with the merged-page
               link (notif-click-behaviour), and the legacy stored rows keep
               /my-timetable-ui — BOTH must resolve the same `o` target, or a
               fresh cancellation would arrive with nothing to flash. */
            $href = '/my-replacements';
            if ($n->request_id !== null) {
                /* the replacement-confirmed row: the request IS the target */
                $notifTarget = 'r'.$n->request_id;
            } else {
                /* a CANCELLED class has no request yet — the row is the class's
                   own occurrence, matched on session + week (the `o` arm) */
                $notifTarget = 'o'.$n->class_session_id;
                $notifWeek = $n->week;
            }
        }
    }
@endphp
<{{ $tag }} class="{{ $rowClass }}" @if($variant === null && ! $activation) data-id="{{ $n->id }}" @endif @if($variant === null && $notifTarget) data-notif-target="{{ $notifTarget }}" @endif @if($variant === null && $notifWeek !== null) data-notif-week="{{ $notifWeek }}" @endif @if($variant === null && ! $activation && $n->getAttribute('notif_status') !== null) data-notif-status="{{ $n->getAttribute('notif_status') }}" @endif @if($variant === 'hidden') data-tip="{{ $rowTip }}" @elseif($activation) data-tip="{{ $rowTip }}" aria-label="{{ $rowLabel }}" @else href="{{ $href }}" data-tip="{{ $rowTip }}" aria-label="{{ $rowLabel }}" @endif>
    @if($variant === 'pick')
        <input type="checkbox" class="bulk-checkbox" wire:click="toggleSelect({{ (int) $n->id }})" @checked($picked) aria-label="{{ $rowLabel }}">
    @endif
    <span class="notif-row-icon notif-tile-{{ $n->type }}"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $glyph !!}</svg></span>
    <span class="notif-row-body">
        <span class="notif-row-title">{{ $rowTitle }}</span>
        {{-- R5 (notif-recipient-rulings, 2026-11-03): the `submitted` composer's own
             subtitle wins (on a live row it IS the staff line); the computed
             `staff_line` renders when its TEXT differs — so a future change to the
             composed line adds a line instead of silently dropping this one. Both
             null => nothing, byte-identical to before. --}}
        @if($senderSubtitle)
            <span class="notif-row-subtitle">{{ $senderSubtitle }}</span>
        @else
            @if($subtitle) <span class="notif-row-subtitle">{{ $subtitle }}</span> @endif@if($staffLine && $staffLine !== $subtitle) <span class="notif-row-subtitle">{{ $staffLine }}</span> @endif
        @endif
        <span class="notif-row-desc">{{ $composedBody }}</span>
    </span>
    @if($variant === 'hidden')
        <span class="nr-right">
            <span class="notif-row-time">{{ $relTime($m) }}</span>
            <button type="button" class="notif-unhide" wire:click="unhide({{ (int) $n->id }})">Unhide</button>
        </span>
    @elseif($variant === null && $activation)
        {{-- notif-inactive-hide-sender (2026-11-05, spec R1): an inactive row can
             be put away straight from the feed — the shelf's `.nr-right` shape,
             the same `hideOne` gate (`isHideableRow`: read OR inactive). --}}
        <span class="nr-right">
            <span class="notif-row-time" data-tip="{{ $absTime($n->created_at) }}" data-tip-pos="left">{{ $relTime($m) }}</span>
            <button type="button" class="notif-hide" wire:click="hideOne({{ (int) $n->id }})">Hide</button>
        </span>
    @else
        <span class="notif-row-time" data-tip="{{ $absTime($n->created_at) }}" data-tip-pos="left">{{ $relTime($m) }}</span>
    @endif
</{{ $tag }}>
