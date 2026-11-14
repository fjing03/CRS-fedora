@php
    /* Shared guide dialog. The page description and guide items arrive from the
       page's existing ui-guide-block arguments, so no instruction text is
       duplicated or lost. */
    $guideTitle = $guideTitle ?? 'How to use this page';
    $guideDescriptionHtml = $guideDescriptionHtml ?? null;
    $guideItems = $guideItems ?? [];
@endphp

<div class="modal-overlay" id="guideModal" style="display:none"
     onclick="if(event.target===this)GuideModal.close()">
    <div class="modal modal--guide" role="dialog" aria-modal="true"
         aria-labelledby="guideModalTitle">
        <div class="modal-header">
            <h3 class="modal-title" id="guideModalTitle">{{ $guideTitle }}</h3>
            <button type="button" class="modal-close" data-tip="Close"
                    aria-label="Close" onclick="GuideModal.close()">✕</button>
        </div>
        <div class="modal-body">
            @if($guideDescriptionHtml)
                <p class="page-desc">{!! $guideDescriptionHtml !!}</p>
            @endif
            @if(count($guideItems) > 0)
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
