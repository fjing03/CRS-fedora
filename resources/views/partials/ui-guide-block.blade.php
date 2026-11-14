@php
    $guideTitle = $guideTitle ?? 'Quick Guide';
    $guideItems = $guideItems ?? [];
    $showToggle = $showToggle ?? true;
    $guideDescriptionHtml = $guideDescriptionHtml ?? null;
@endphp

<div class="guide-block{{ $showToggle ? '' : ' guide-block--content' }}" id="guideBlock">
    @if($showToggle)
        <button class="guide-toggle" onclick="document.getElementById('guideBlock').classList.toggle('open')">
            <span class="guide-icon">
                @include('partials.ui-guide-icon', ['size' => 16])
            </span>
            {{ $guideTitle }}
            <span class="guide-chevron">&#9662;</span>
        </button>
    @endif
    <div class="guide-content">
        @if($guideDescriptionHtml)
            <div class="guide-description">{!! $guideDescriptionHtml !!}</div>
        @endif
        @if(count($guideItems) > 0)
            <ul class="guide-list">
                @foreach($guideItems as $item)
                    <li>{!! $item !!}</li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
