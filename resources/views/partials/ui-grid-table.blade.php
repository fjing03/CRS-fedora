@php
    $scrollId = $scrollId ?? 'gridScroll';
    $tableId = $tableId ?? 'timetable';
    $headId = $headId ?? 'tableHead';
    $bodyId = $bodyId ?? 'tableBody';
    $tableClass = $tableClass ?? 'timetable';
@endphp

<div class="grid-wrapper" @if(!empty($wrapperId)) id="{{ $wrapperId }}" @endif>
    <div class="grid-scroll" id="{{ $scrollId }}">
        <table class="{{ $tableClass }}" id="{{ $tableId }}">
            <thead id="{{ $headId }}"></thead>
            <tbody id="{{ $bodyId }}"></tbody>
        </table>
    </div>
    {{-- Scroll cue (2026-11-17). Decorative: aria-hidden, pointer-events none, never a
         target. It appears only when the grid actually continues, so a grid that fits
         its card stays clean. Print hides both the cue and the edge fade. --}}
    <span class="grid-scroll-cue grid-scroll-cue--left" aria-hidden="true">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg>
    </span>
    <span class="grid-scroll-cue grid-scroll-cue--right" aria-hidden="true">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
    </span>
</div>

@once
<script>
(function () {
    /* Marks each grid wrapper with what is still hidden to the right or left. One
       listener set per wrapper (tracked), so a Livewire re-render cannot stack them. */
    var wired = new WeakSet();
    function sync(wrapper) {
        var scroller = wrapper.querySelector('.grid-scroll');
        if (!scroller) return;
        var max = scroller.scrollWidth - scroller.clientWidth;
        wrapper.classList.toggle('is-more-right', max > 4 && scroller.scrollLeft < max - 4);
        wrapper.classList.toggle('is-more-left', scroller.scrollLeft > 4);
    }
    function wire() {
        document.querySelectorAll('.grid-wrapper').forEach(function (wrapper) {
            if (!wired.has(wrapper)) {
                wired.add(wrapper);
                var scroller = wrapper.querySelector('.grid-scroll');
                if (scroller) {
                    scroller.addEventListener('scroll', function () { sync(wrapper); }, { passive: true });
                    if (window.ResizeObserver) new ResizeObserver(function () { sync(wrapper); }).observe(scroller);
                }
                window.addEventListener('resize', function () { sync(wrapper); });
            }
            sync(wrapper);
        });
    }
    if (document.readyState !== 'loading') wire(); else document.addEventListener('DOMContentLoaded', wire);
    document.addEventListener('livewire:navigated', wire);
})();
</script>
@endonce
