@php
    $items = $items ?? [
        ['class' => 'event-normal', 'label' => 'Normal Class', 'tip' => 'Scheduled class with no issues'],
        ['class' => 'event-replacement', 'label' => 'Replacement', 'tip' => 'Approved replacement session'],
        ['class' => 'event-pending', 'label' => 'Pending', 'tip' => 'Replacement request awaiting approval'],
        ['class' => 'event-conflict', 'label' => 'Conflict / Public Holiday', 'tip' => 'Scheduling conflict or public holiday — this class will not run as scheduled'],
    ];
@endphp

<div class="legend-bar">
    <span class="legend-hint" aria-hidden="true"><span class="hint-hover">Hover a colour to learn more</span><span class="hint-touch">Tap a colour to learn more</span></span>
    <div class="legend-items">
        @foreach($items as $item)
            {{-- The hint promises a tap on a touch screen; without a focus stop that promise cannot be
                 kept — the tip engine has no touch path, and a div carrying data-tip alone is
                 unsummonable. tabindex makes the tip reachable by tap (tap focuses, focus parity
                 shows it) and by Tab. role=img, not a roleless div: a generic computes a name in
                 Chromium but announces unevenly in real screen readers, which is a false pass. role=img
                 makes the child spans presentational, so the label must carry the colour name AND the
                 meaning. The 48px floor that goes with this lives in theme.css. --}}
            <div class="legend-item"
                 @if(!empty($item['tip'])) data-tip="{{ $item['tip'] }}" tabindex="0" role="img" aria-label="{{ $item['label'] }}: {{ $item['tip'] }}" @endif>
                {{-- Swatches carry the real block class so the legend shows the
                     exact status styling (fill + border) instead of a flat colour. --}}
                <span class="legend-swatch{{ empty($item['class']) ? '' : ' '.$item['class'] }}" @if(empty($item['class'])) style="background: {{ $item['color'] }};" @endif></span>
                <span>{{ $item['label'] }}</span>
            </div>
        @endforeach
    </div>
    @if(!empty($ownershipHint))
        @if(is_array($ownershipHint))
            {{-- A page whose "others" border is not the 0.5px hairline passes
                 its own list — e.g. the arrangement grid, where others = no
                 border at all — so the bar never claims a style the page does
                 not draw. A `demo` of '' renders the bare .osd-demo
                 (borderless). --}}
            <span class="legend-ownership-hint">
                @foreach($ownershipHint as $i => $line)
                    @if($i > 0)&nbsp;·&nbsp;@endif<span class="osd-demo{{ empty($line['demo']) ? '' : ' '.$line['demo'] }}"></span> {{ $line['text'] }}
                @endforeach
            </span>
        @else
            {{-- Default (cohort + venue): kept as literal markup so their output
                 stays byte-identical to what shipped before this option existed. --}}
            <span class="legend-ownership-hint">
                <span class="osd-demo osd-thick"></span> thick border (3px) = your classes
                &nbsp;·&nbsp;
                <span class="osd-demo osd-thin"></span> thin border (0.5px) = others'
            </span>
        @endif
    @endif
</div>
