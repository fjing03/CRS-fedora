{{-- Inactive shell: later tasks move the existing controls here, never copies. --}}
<section id="arrangementMobile" class="arrangement-mobile" aria-label="Replacement day view" hidden inert>
    <details class="arrangement-mobile-context" data-arrangement-mobile-context>
        <summary data-arrangement-mobile-context-summary>Original class</summary>
        <div data-arrangement-mobile-context-facts></div>
        <div data-arrangement-mobile-subject-host></div>
        <div data-arrangement-mobile-slot-host></div>
    </details>

    <div class="arrangement-mobile-controls" data-arrangement-mobile-controls>
        <div data-arrangement-mobile-venue-host></div>
        <div data-arrangement-mobile-week-host></div>
        <div role="group" aria-label="Replacement day" data-arrangement-mobile-days></div>
    </div>

    <div class="arrangement-mobile-tools" role="group" aria-label="Timetable information" data-arrangement-mobile-tools>
        <div data-arrangement-mobile-earliest-host></div>
        <button type="button" data-arrangement-mobile-legend aria-haspopup="dialog" aria-controls="arrangementMobileInfo">
            <svg class="arrangement-mobile-legend-icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="3" y="14" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/>
            </svg>
            <span>Colours &amp; borders</span>
        </button>
        <div data-arrangement-mobile-tip-host></div>
    </div>

    <section class="arrangement-mobile-day" aria-labelledby="arrangementMobileDayHeading">
        <h2 id="arrangementMobileDayHeading" data-arrangement-mobile-day-heading>Replacement day</h2>
        <p data-arrangement-mobile-empty hidden></p>
        <div data-arrangement-mobile-timeline></div>
    </section>

    <p class="arrangement-mobile-live" data-arrangement-mobile-live aria-live="polite" aria-atomic="true"></p>

    <div class="arrangement-mobile-actions" role="group" aria-label="Replacement selection" data-arrangement-mobile-actions>
        <div data-arrangement-mobile-summary-host></div>
        <div data-arrangement-mobile-submit-host></div>
    </div>

    <dialog id="arrangementMobileInfo" class="arrangement-mobile-info" aria-labelledby="arrangementMobileInfoHeading" data-arrangement-mobile-info>
        <div class="modal-header">
            <h2 id="arrangementMobileInfoHeading" data-arrangement-mobile-info-heading>Timetable information</h2>
            <button type="button" class="btn btn-outline" aria-label="Close timetable information" data-arrangement-mobile-info-close>Close</button>
        </div>
        <div class="modal-body" data-arrangement-mobile-info-body></div>
        <div class="modal-footer" data-arrangement-mobile-info-actions></div>
    </dialog>
</section>
