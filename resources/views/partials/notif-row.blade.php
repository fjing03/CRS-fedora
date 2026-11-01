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
     Batch-2 $relLong/$tipAbs from the island scope (@include's flow). --}}
@php
    $reason = $n->getAttribute('inactive_reason');
    $m = $minutesAgo($n->created_at);
    $display = $n->getAttribute('_display');
    /* the activation law: the classified family rides $reason (the D15
       reason first-match); the legacy non-submitted family's D4 law stays on
       $inactive_row (missing/cancelled only — never the decided). */
    $activation = $reason !== null
        || ($n->inactive_row && ! in_array($n->type, ['submitted', 'awaiting'], true) && $display === null);
    $tag = $activation ? 'span' : 'a';
    $rowClass = 'notif-row'.($activation
        ? ' notif-row--inactive'
        : ($n->read_at !== null ? ' notif-row--read' : ''));
    $composedTitle = $display !== null ? $display['title'] : $n->title;
    $composedBody = $display !== null ? $display['desc'] : $n->body;
    $subtitle = $display !== null ? ($display['subtitle'] ?? null) : null;
    if ($activation) {
        /* D1/D15 — the reason-family copies, first-match by $reason */
        $rowTip = match ($reason) {
            'cancelled' => 'This notification is inactive because the request was cancelled by the requester.',
            'approved' => 'This notification is inactive because the request was approved.',
            'rejected' => 'This notification is inactive because the request was rejected.',
            'read' => 'This notification is inactive because you have already read this request.',
            default => 'This notification is inactive because the request was cancelled by the requester.',
        };
        $rowLabel = $composedTitle.' — '.$composedBody.' (inactive)';
    } elseif ($display !== null && $display['tip'] !== null && $display['tip'] !== '') {
        /* T3/T4: the multiline tip = the composer's FACT lines with the dynamic
           Requested-at line 2 inserted after the Staff line (the T4 closures). */
        $lines = explode("\n", $display['tip']);
        if (($lines[0] ?? '') !== '' && str_starts_with($lines[0], 'Staff:')) {
            $line2 = 'Requested at: '.$tipAbs($n->created_at).' ('.$relLong($n->created_at).')';
            array_splice($lines, 1, 0, [$line2]);
        } else {
            array_splice($lines, 0, 0, ['Requested at: '.$tipAbs($n->created_at).' ('.$relLong($n->created_at).')']);
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
    if (! $activation) {
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
<{{ $tag }} class="{{ $rowClass }}" @if(! $activation) data-id="{{ $n->id }}" @endif @if($notifTarget) data-notif-target="{{ $notifTarget }}" @endif @if($notifWeek !== null) data-notif-week="{{ $notifWeek }}" @endif @if($activation) data-tip="{{ $rowTip }}" aria-label="{{ $rowLabel }}" @else href="{{ $n->link }}" data-tip="{{ $rowTip }}" aria-label="{{ $rowLabel }}" @endif>
    <span class="notif-row-icon notif-tile-{{ $n->type }}"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $glyph !!}</svg></span>
    <span class="notif-row-body">
        <span class="notif-row-title">{{ $composedTitle }}</span>
        @if($subtitle) <span class="notif-row-subtitle">{{ $subtitle }}</span> @endif
        <span class="notif-row-desc">{{ $composedBody }}</span>
    </span>
    <span class="notif-row-time" data-tip="{{ $absTime($n->created_at) }}" data-tip-pos="left">{{ $relTime($m) }}</span>
</{{ $tag }}>
