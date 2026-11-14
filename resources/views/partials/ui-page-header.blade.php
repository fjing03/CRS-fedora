@php
    $title = $title ?? null;
    $description = $description ?? null;
    /* replacement-merge-one-table (§2.1b): an OPTIONAL rich description so the
       merged page's H1 keeps CLASS / REQUEST / ONE LIST emphasis. The ?? null is
       required — this partial has callers that pass [] (the arranging page and
       the frozen mock template), and a bare @if on an undefined key throws. */
    $descriptionHtml = $descriptionHtml ?? null;
    $chips = $chips ?? [];
    /* guide-toggle-icon-only: opt-in icon-only guide opener. Default false keeps
       every non-opted caller byte-identical. */
    $guideToggle = $guideToggle ?? false;
    $guideLabel = $guideLabel ?? 'How to use this page';
    /* Pages that moved their guide into the shared dialog pass true; the rest
       keep the inline #guideBlock toggle. */
    $guideModal = $guideModal ?? false;
    /* summary-cards-button: the phone Summary opener. Opt-in, so a page that
       does not pass it renders exactly what it did before. */
    $summaryButton = $summaryButton ?? false;
@endphp

<div class="page-header">
    <div class="page-header-main">
        @if($title)
            <h1 class="page-title">{{ $title }}</h1>
        @endif
        @if(count($chips) > 0)
            <div class="page-chips">
                <span class="semester-chip" id="semesterChip"></span>
                @foreach($chips as $chip)
                    <span class="{{ $chip['class'] ?? 'semester-chip' }}">{{ $chip['label'] }}</span>
                @endforeach
            </div>
        @else
            <span class="semester-chip" id="semesterChip"></span>
        @endif
        @if($descriptionHtml)
            <p class="page-desc">{!! $descriptionHtml !!}</p>
        @elseif($description)
            <p class="page-desc">{{ $description }}</p>
        @endif
    </div>
    @if($summaryButton)
        {{-- summary-cards-button: opens the phone sheet that holds this page's own
             summary cards. Rendered for the opted pages only; empty at >=769px
             by `.summary-button { display: none }`, and also `hidden` when the
             page has hidden the cards (SummarySheet.syncButton). --}}
        <button class="summary-button" type="button" id="summaryButton"
                aria-haspopup="dialog" aria-controls="summarySheet" aria-expanded="false"
                aria-label="Summary" data-tip="Summary"
                onclick="SummarySheet.open(this)">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="6" y1="20" x2="6" y2="14"/><line x1="12" y1="20" x2="12" y2="8"/><line x1="18" y1="20" x2="18" y2="11"/></svg>
        </button>
    @endif
    @if($guideToggle)
        <button class="guide-toggle-icon" type="button"
                aria-haspopup="{{ $guideModal ? 'dialog' : 'false' }}"
                aria-controls="{{ $guideModal ? 'guideModal' : 'guideBlock' }}"
                aria-expanded="false"
                aria-label="{{ $guideLabel }}"
                @if($guideModal)
                    onclick="GuideModal.open(this)"
                @else
                    onclick="var b=document.getElementById('guideBlock');if(!b)return;var o=b.classList.toggle('open');this.setAttribute('aria-expanded',o)"
                @endif>
            @include('partials.ui-guide-icon', ['size' => 18])
        </button>
    @endif
</div>
