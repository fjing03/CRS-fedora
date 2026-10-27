{{-- PAGE-DATA-SHARD (shared partial — the Promote-on-3rd law) — 2026-10-27.
     The 5 replacement-family pages emit the page payload in ONE
     `<script>window.PAGE_DATA = @json($payload);</script>`. In debug mode
     Livewire's root-element detector strips each `<script>…</script>`
     element with a lazy regex (`.*?` crossing ~1MB of the arrangement
     page's PAGE_DATA) → pcre.backtrack_limit(1000000) exceeded →
     preg_replace returns null → `DOMDocument::loadHTML(null→'')`
     ValueError → an HTTP 500 on the page. The shard emits the payload in
     ≤16KB slices (cut ONLY on non-escape boundaries), re-joined via
     JSON.parse — the consumer contract (`window.PAGE_DATA`) is unchanged.
     Only runs the shard when the payload is big enough to matter. --}}
@php
    $json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    $chunks = [];
    // 60KB: the trigger threshold (Livewire's pcre backtrack is ~1M — far above,
    // but the detector's lazy run also pays per element; keeping the small
    // pages single-element costs nothing and eases the receipt greps).
    if (strlen($json) > 60000) {
        $size = 16000;
        $n = strlen($json);
        $i = 0;
        $lastSafe = 0;
        while ($i < $n) {
            if ($json[$i] === '\\') {
                // never split inside an escape: jump its whole length
                // (\uXXXX = 6, every other escape pair = 2)
                $i += ($json[$i + 1] ?? '') === 'u' ? 6 : 2;
            } else {
                $i++;
            }
            if ($i >= $lastSafe + $size && $i < $n && $i - $lastSafe >= $size) {
                $chunks[] = substr($json, $lastSafe, $i - $lastSafe);
                $lastSafe = $i;
            }
        }
        if ($lastSafe < $n) $chunks[] = substr($json, $lastSafe);
    }
@endphp
@if(count($chunks) > 1)
    <script>window.PAGE_DATA_chunks = [];</script>
    @foreach($chunks as $chunk)
        <script>window.PAGE_DATA_chunks.push({!! json_encode($chunk) !!});</script>
    @endforeach
    <script>try { window.PAGE_DATA = JSON.parse(window.PAGE_DATA_chunks.join('')); } catch (e) { console.error('PAGE_DATA shard parse failed', e); } delete window.PAGE_DATA_chunks;</script>
@else
    <script>window.PAGE_DATA = @json($payload);</script>
@endif
