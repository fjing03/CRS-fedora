@php
    /* The flip-card variant (96px, three bullets, per-card info button) is
       opt-in so the other summary pages keep their existing cards. */
    $flipCards = $flipCards ?? false;
    $descriptions = [
        'card-total'       => '<span class="info-keyword">Normal + replacement + pending</span> classes for the selected period. Cancelled classes are not counted.',
        'card-hours'       => 'Hours of those classes for the selected period (each slot = <strong>30 minutes</strong>). Cancelled classes are not counted.',
        'card-replacement' => 'Classes a <span class="info-keyword">replacement lecturer</span> teaches.',
        'card-pending'     => 'Classes still <span class="warn-keyword">waiting for a replacement to be approved</span>.',
        'card-conflict'    => 'Classes that cannot run as scheduled — a <span class="warn-keyword">clash</span>, a cancellation, or a <strong>public holiday</strong>.',
        'card-available'   => '<span class="info-keyword">Free time slots</span> that can be booked for this venue.',
        'card-approved'    => 'Requests <span class="info-keyword">approved</span> — the replacement is confirmed.',
        'card-rejected'    => 'Requests <span class="warn-keyword">declined</span> — they must be arranged again.',
        'card-venues'      => 'Number of <span class="info-keyword">unique venues</span> involved in the conflicted classes.',
        'card-students'    => 'Total <span class="info-keyword">students impacted</span> by the scheduling conflicts.',
        'card-duration'    => 'Total <span class="info-keyword">hours of class time</span> that need to be rescheduled.',
        'card-courses'     => 'Number of <span class="info-keyword">different courses</span> affected by the conflicts.',
    ];
@endphp

<div class="summary-section{{ $flipCards ? ' summary-section--flip' : '' }}" id="summarySection">
    <div class="summary-hint">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        {{-- summary-cards-button (2026-11-14): the sheet is a phone surface, so
             the plain (non-flip) variant gets a sentence that speaks to touch.
             The six flip pages already say "Hover or tap" — true on a touch
             screen — so they keep exactly one sentence. --}}
        <span class="summary-hint-pointer">Stats reflect the <strong>selected period</strong> and any active filters. @if($flipCards)Hover or tap a card to learn more.@else Hover a card to learn more.@endif</span>
        @unless($flipCards)
            <span class="summary-hint-touch">Stats reflect the <strong>selected period</strong> and any active filters. Tap a card to see what it counts.</span>
        @endunless
    </div>
    <div class="summary-bar" id="summaryBar">
        @foreach ($cards as $i => $card)
            @php
                $desc = $card['description'] ?? $descriptions[$card['class']] ?? $card['label'];
                $explanation = $card['explanation'] ?? $desc;
                $bullets = $card['bullets'] ?? null;
                /* SD-04: optional structured explanation. Additive — a card
                   without `detail` keeps today's single `explanation`
                   paragraph. `formula` and `foot` are optional; `items` is
                   what makes the two-line term/meaning list. */
                $detail = $card['detail'] ?? null;
            @endphp
            @if ($flipCards)
                <div class="summary-card {{ $card['class'] }}"
                     tabindex="0"
                     role="group"
                     data-summary="{{ $i }}"
                     aria-label="{{ $card['label'] }}: hover, tap or press Enter to flip">
                    <div class="summary-card-inner">
                        <div class="summary-card-front">
                            <span class="summary-value" id="{{ $card['valueId'] }}">{{ $card['value'] ?? 0 }}</span>
                            <span class="summary-label">{{ $card['label'] }}</span>
                        </div>
                        <div class="summary-card-back">
                            <div class="summary-description">
                                @if(is_array($bullets) && count($bullets) > 0)
                                    <ul class="summary-points">
                                        @foreach(array_slice($bullets, 0, 3) as $bullet)
                                            <li>{!! $bullet !!}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    {!! $desc !!}
                                @endif
                            </div>
                            {{-- SD-04: structured detail wins; the paragraph is
                                 the fallback for any card without one. --}}
                            <div class="summary-explanation-source" hidden>@if(is_array($detail))
{{-- formula strip: relationship only, never a live number (SD-05) --}}
@if(!empty($detail['formula']))
<div class="explain-formula">
@foreach($detail['formula'] as $chip)
@if(!empty($chip['op']))
<span class="explain-plus">{{ $chip['op'] }}</span>
@else
<span class="explain-chip{{ !empty($chip['c']) ? ' ' . $chip['c'] : '' }}">{{ $chip['v'] ?? '' }}</span>
@endif
@endforeach
</div>
@endif
{{-- term <br> meaning; the <span> is what makes the meaning muted --}}
@if(!empty($detail['items']))
<ul class="explain-list">
@foreach($detail['items'] as $item)
<li>{!! $item[0] ?? '' !!}<br><span>{!! $item[1] ?? '' !!}</span></li>
@endforeach
</ul>
@endif
@if(!empty($detail['foot']))<p class="explain-foot">{!! $detail['foot'] !!}</p>@endif
@else{!! $explanation !!}@endif</div>
                            <button type="button" class="summary-info"
                                    data-summary-info="{{ $i }}"
                                    aria-haspopup="dialog"
                                    aria-label="{{ $card['label'] }}: full explanation"
                                    data-tip="Full explanation">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                            </button>
                        </div>
                    </div>
                </div>
            @else
                <div class="summary-card {{ $card['class'] }}">
                    <div class="summary-card-inner">
                        <div class="summary-card-front">
                            <span class="summary-value" id="{{ $card['valueId'] }}">{{ $card['value'] ?? 0 }}</span>
                            <span class="summary-label">{{ $card['label'] }}</span>
                        </div>
                        <div class="summary-card-back">
                            <span class="summary-description">{!! $desc !!}</span>
                        </div>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</div>

@if ($flipCards)
    {{-- Full explanation popup — one per page, filled from the clicked card's
         own long description, so no formula is duplicated here. --}}
    <div class="modal-overlay" id="summaryExplainModal" style="display:none"
         onclick="if(event.target===this)SummaryCards.close()">
        <div class="modal modal--summary" role="dialog" aria-modal="true"
             aria-labelledby="summaryExplainTitle">
            <div class="modal-header">
                <h3 class="modal-title" id="summaryExplainTitle">Explanation</h3>
                <button type="button" class="modal-close" data-tip="Close"
                        aria-label="Close" onclick="SummaryCards.close()">✕</button>
            </div>
            <div class="modal-body" id="summaryExplainBody"></div>
            <div class="modal-footer">
                <div class="modal-footer-left"></div>
                <div class="modal-footer-right">
                    <button type="button" class="btn-close-modal" onclick="SummaryCards.close()">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif

{{-- summary-cards-button (2026-11-14): ONE shared sheet frame per page, emitted by
     the partial that owns the cards, so a page cannot ship the button without the
     panel. SummarySheet moves #summarySection into #summarySheetBody at <=768px and
     back at >=769px; the node, its ids and its numbers never change owner. --}}
<div class="summary-sheet-overlay" id="summarySheet" hidden
     onclick="if(event.target===this)SummarySheet.close()">
    <div class="summary-sheet" role="dialog" aria-modal="true" aria-labelledby="summarySheetTitle">
        <div class="summary-sheet-head">
            <h2 class="summary-sheet-title" id="summarySheetTitle">Summary</h2>
            <button type="button" class="summary-sheet-close" aria-label="Close Summary"
                    data-tip="Close" onclick="SummarySheet.close()">&#10005;</button>
        </div>
        <div class="summary-sheet-body" id="summarySheetBody"></div>
    </div>
</div>
