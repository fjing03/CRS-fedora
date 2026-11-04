@php
    $title = $title ?? null;
    $description = $description ?? null;
    /* replacement-merge-one-table (§2.1b): an OPTIONAL rich description so the
       merged page's H1 keeps CLASS / REQUEST / ONE LIST emphasis. The ?? null is
       required — this partial has callers that pass [] (the arranging page and
       the frozen mock template), and a bare @if on an undefined key throws. */
    $descriptionHtml = $descriptionHtml ?? null;
    $chips = $chips ?? [];
@endphp

<div class="page-header">
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
