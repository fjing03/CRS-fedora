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
