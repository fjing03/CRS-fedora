{{-- ═══ Cancel Class Confirmation Modal (cancel-class-enhancement, design §5) ═══
     Promoted partial — included by my-timetable, cohort-timetable and
     venue-timetable (3rd-duplication rule, AGENTS #7). Static shell only:
     the CancelClassModal controller (ui-common.js) fills the impact preview,
     renders the reason radios and swaps confirm ↔ success states.
     House modal classes only (modal-overlay/modal/modal-*, btn-*) — tokens.

     2026-11-05 UI/UX update (user): title reads "Cancel this Class?", the shell
     is 40% wider (`.modal--cancel` = 588px, still capped by the responsive
     `.modal` media queries), the class facts sit in the SAME grey `dl` box the
     Confirm Cancellation modal uses (`dl.cancel-details`), a full-width rule
     separates them from the reason block, and the old inline
     "Please select a cancellation reason." hint is replaced by the static
     instruction below the rule. Bottom-left reads "No, Keep Class". --}}
<div class="modal-overlay" id="cancelClassOverlay" onclick="if(event.target===this)CancelClassModal.dismiss()">
    <div class="modal modal--cancel">
        <div class="modal-header">
            <span class="modal-title" id="cancelClassTitle">Cancel this Class?</span>
            <button class="modal-close" onclick="CancelClassModal.dismiss()" data-tip="Close">&times;</button>
        </div>
        <div class="modal-body">
            <!-- ─── Confirm state (impact preview + reason gating) ─── -->
            <div id="cancelClassConfirmState">
                {{-- the class facts — CancelClassModal._renderImpact() fills the
                     section title "Class Information" + the grey dl box --}}
                <div id="cancelClassImpact"></div>
                {{-- 2026-11-05: rule + the required-reason instruction (replaces
                     the dynamic "Please select a cancellation reason." hint) --}}
                <hr class="modal-divider">
                <p class="cancel-reason-required" id="cancelClassRequired">You MUST Select a Cancellation Reason</p>
                <div id="cancelReasonList"></div>
                <textarea id="cancelOtherDetail" class="modal-textarea" rows="3"
                          placeholder="Describe the reason for &quot;Other&quot;&hellip;"
                          style="display:none; margin-top:10px"></textarea>
                {{-- the gate's own refusal copy (a server refusal, or the
                     blank-"Other"-detail hint) still lands here --}}
                <p id="cancelReasonHint" class="hint-text" style="margin:8px 0 0; min-height:14px"></p>
                {{-- 2026-11-05 (user ruling): the Undo affordance is GONE, so the
                     decision is FINAL — the shared irreversible line says so
                     before the confirm button. --}}
                <span id="cancelClassIrreversible">@include('partials.ui-irreversible-line')</span>
            </div>
            <!-- ─── Success state (swapped in-place, same overlay) ─── -->
            <div id="cancelClassSuccessState" style="display:none">
                <p style="margin:0 0 10px; font-weight:600; font-size:15px;">✓ Class cancelled</p>
                <p id="cancelClassSummary" class="hint-text" style="margin:0"></p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-outline" id="cancelKeepClassBtn" onclick="CancelClassModal.dismiss()">No, Keep Class</button>
            <button class="btn-danger" id="confirmCancelClassBtn" onclick="CancelClassModal.confirm()" disabled>Yes, Cancel Class</button>
            <button class="btn-action" id="cancelArrangeNowBtn" style="display:none" onclick="CancelClassModal.arrangeNow()">Arrange Replacement Now</button>
            <button class="btn-outline" id="cancelLaterBtn" style="display:none" onclick="CancelClassModal.later()">I'll Do It Later</button>
        </div>
    </div>
</div>
