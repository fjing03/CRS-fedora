@php
    /* Shared guide dialog. The page description and guide items arrive from the
       page's existing ui-guide-block arguments, so no instruction text is
       duplicated or lost. */
    $guideTitle = $guideTitle ?? 'How to use this page';
    $guideDescriptionHtml = $guideDescriptionHtml ?? null;
    $guideItems = $guideItems ?? [];
    /* 2026-11-16 (my-timetable comparison, item #12): the approved prototype gives this
       dialog a subtitle and labelled sections instead of one bulleted list, and its
       title sits on the left. Both are OPT-IN so the seven other pages that share this
       dialog keep exactly what they have today: pass `guideSubtitle` and/or
       `guideGroups` ([[label, html], …]) to switch this instance only. */
    $guideSubtitle = $guideSubtitle ?? null;
    $guideGroups = $guideGroups ?? [];
    $guideLeft = ($guideSubtitle || count($guideGroups) > 0);
@endphp

<div class="modal-overlay" id="guideModal" style="display:none"
     onclick="if(event.target===this)GuideModal.close()">
    <div class="modal modal--guide{{ $guideLeft ? ' modal--guide-left' : '' }}" role="dialog"
         aria-modal="true" aria-labelledby="guideModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="guideModalTitle">{{ $guideTitle }}@if($guideSubtitle)<span
                    class="modal-subtitle">{{ $guideSubtitle }}</span>@endif</h3>
            <button type="button" class="modal-close" data-tip="Close"
                    aria-label="Close" onclick="GuideModal.close()">✕</button>
        </div>
        <div class="modal-body">
            @if($guideDescriptionHtml)
                <p class="page-desc">{!! $guideDescriptionHtml !!}</p>
            @endif
            @if(count($guideGroups) > 0)
                @foreach($guideGroups as $group)
                    <div class="detail-group">
                        <div class="detail-group-title">{{ $group[0] }}</div>
                        <p>{!! $group[1] !!}</p>
                    </div>
                @endforeach
            @elseif(count($guideItems) > 0)
                <ul class="guide-list">
                    @foreach($guideItems as $item)
                        <li>{!! $item !!}</li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div class="modal-footer">
            <div class="modal-footer-left"></div>
            <div class="modal-footer-right">
                <button type="button" class="btn-close-modal" onclick="GuideModal.close()">Close</button>
            </div>
        </div>
    </div>
</div>
