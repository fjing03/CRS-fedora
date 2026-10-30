{{-- partials/notif-row.blade.php — SDD notifications-real-bridge T2/D6: THE
     notification row renders server-side (the frozen classes, byte-preserved).
     ONE block serves BOTH branches (the lifecycle D4 law):
       inactive_row  → <span class="notif-row notif-row--inactive">, no href,
                       no data-id (never click-markable) + the user's tooltip.
       active        → <a class="notif-row [--read]">, data-id + href +
                       the title — body tooltip (the marked target of the
                       durable POST capture listener in ui-common.js).
     Expects: $n (Notification), $glyph (the tile SVG path data). The time
     closures are inherited from the island scope (@include's scope flow). --}}
@php
    $m = $minutesAgo($n->created_at);
    $tag = $n->inactive_row ? 'span' : 'a';
    $rowClass = 'notif-row'.($n->inactive_row
        ? ' notif-row--inactive'
        : ($n->read_at !== null ? ' notif-row--read' : ''));
    $rowTip = $n->inactive_row
        ? 'This notification is inactive because the request was cancelled.'
        : ($n->title.' — '.$n->body);
    $rowLabel = $n->title.' — '.$n->body.($n->inactive_row ? ' (inactive)' : '');
    /* notif-click-highlight: the target anchor rides the row — r(request id)
       for the approval/history surfaces, s(session id)+week for the cancel
       rows landing on /my-timetable-ui. GATED by the row's STORED link: the
       legacy persisted rows (the old /cohort-timetable, /venue-timetable-ui
       strings) emit NOTHING → plain navigation (the lifecycle law). The
       inactive arm stays anchor-less (keep-as-it). */
    $notifTarget = null;
    $notifWeek = null;
    if (!$n->inactive_row) {
        if ($n->link === '/request-approval-ui' && $n->request_id !== null) {
            $notifTarget = 'r'.$n->request_id;
        } elseif ($n->link === '/my-request-history-ui' && $n->request_id !== null) {
            $notifTarget = 'r'.$n->request_id;
        } elseif ($n->link === '/my-timetable-ui' && $n->class_session_id !== null) {
            $notifTarget = 's'.$n->class_session_id;
            $notifWeek = $n->week;
        }
    }
@endphp
<{{ $tag }} class="{{ $rowClass }}" @if(! $n->inactive_row) data-id="{{ $n->id }}" @endif @if($notifTarget) data-notif-target="{{ $notifTarget }}" @endif @if($notifWeek !== null) data-notif-week="{{ $notifWeek }}" @endif @if($n->inactive_row) data-tip="{{ $rowTip }}" aria-label="{{ $rowLabel }}" @else href="{{ $n->link }}" data-tip="{{ $rowTip }}" aria-label="{{ $rowLabel }}" @endif>
    <span class="notif-row-icon notif-tile-{{ $n->type }}"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $glyph !!}</svg></span>
    <span class="notif-row-body">
        <span class="notif-row-title">{{ $n->title }}</span>
        <span class="notif-row-desc">{{ $n->body }}</span>
    </span>
    <span class="notif-row-time" data-tip="{{ $absTime($n->created_at) }}" data-tip-pos="left">{{ $relTime($m) }}</span>
</{{ $tag }}>
