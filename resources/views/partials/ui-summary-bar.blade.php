@php
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

<div class="summary-section" id="summarySection">
    <div class="summary-hint">
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        <span>Stats reflect the <strong>selected period</strong> and any active filters. Hover a card to learn more.</span>
    </div>
    <div class="summary-bar" id="summaryBar">
        @foreach ($cards as $card)
            @php
                $desc = $card['description'] ?? $descriptions[$card['class']] ?? $card['label'];
            @endphp
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
        @endforeach
    </div>
</div>
