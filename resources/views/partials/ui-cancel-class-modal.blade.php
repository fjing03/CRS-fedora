{{-- ═══ Cancel Class Confirmation Modal (cancel-class-enhancement, design §5) ═══
     Promoted partial — included by my-timetable, cohort-timetable and
     venue-timetable (3rd-duplication rule, AGENTS #7). Static shell only:
     the CancelClassModal controller (ui-common.js) fills the impact preview,
     renders the reason radios and swaps confirm ↔ success states.
     House modal classes only (modal-overlay/modal/modal-*, btn-*) — tokens.

     2026-11-05 UI/UX update (user): title reads "Cancel this Class?", the shell
     is 40% wider (`.modal--cancel` = 588px, still capped by the responsive
     `.modal` media queries), the class facts sit in the SAME grey `dl` box the
     Confirm Cancellation modal uses (`dl.cancel-details`), and a full-width rule
     separates them from the reason block. Bottom-left reads "No, Keep Class".

     2026-11-10 (user, "Cancel this Class? — Cancellation Reason block"):
       · the shouty `You MUST Select a Cancellation Reason` line is GONE — the
         rule now runs straight into the reasons, and the heading's required
         asterisk plus the disabled Yes button carry the message;
       · the eight reasons sit TWO per row (one per row under 520px);
       · `Cancellation Reason` carries the app's red required asterisk;
       · the irreversible warning moved to the FOOTER as the short
         `CANNOT UNDONE`, directly above Yes, and only while Yes is armed. --}}
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
                {{-- 2026-11-11 (SDD cancel-whole-day): the whole-day block. Its
                     content is built by CancelClassModal._renderDayScope() because
                     the day's classes come from a server read (myDayClasses), not
                     from the page payload; this shell only reserves the place.
                     Hidden unless the day really has other classes. --}}
                <div id="cancelDayScope" class="cancel-day-scope" style="display:none"></div>
                {{-- 2026-11-10: the rule runs straight into the reasons — the
                     removed instruction is not replaced by anything; the heading's
                     red asterisk + the disabled Yes button are the cue. --}}
                <hr class="modal-divider">
                <div id="cancelReasonList"></div>
                {{-- 2026-11-10 (user, item 5): "Other" keeps its place as the
                     eighth radio INSIDE the grid; choosing it reveals this
                     labelled block BELOW the whole grid (never a cell beside the
                     radio) — a small uppercase muted label with the app's red
                     required asterisk, then the full-width `.modal-textarea`.
                     The WRAPPER owns the visibility — the textarea is never shown
                     or hidden on its own, and its typed text survives a switch to
                     another reason and back. --}}
                <div id="cancelOtherWrap" style="display:none; margin-top:10px">
                    <label class="cancel-other-label" for="cancelOtherDetail">Other reason <span class="cancel-required-mark">*</span></label>
                    <textarea id="cancelOtherDetail" class="modal-textarea" rows="3"
                              placeholder="Describe the reason for &quot;Other&quot;&hellip;"
                              required></textarea>
                </div>
                {{-- the gate's own refusal copy still lands here — the window /
                     "no longer cancellable" / server-error messages. 2026-11-10
                     (user, item 5): the blank-"Other" sentence is gone, so the
                     paragraph no longer reserves a 14px gap; it takes space only
                     when there is something real to say. --}}
                <p id="cancelReasonHint" class="hint-text" style="margin:8px 0 0"></p>
                {{-- 2026-11-10: the irreversible warning no longer lives here — it
                     moved to the footer, directly above Yes (see .modal-footer). --}}
            </div>
            <!-- ─── Success state (swapped in-place, same overlay) ─── -->
            <div id="cancelClassSuccessState" style="display:none">
                <p style="margin:0 0 10px; font-weight:600; font-size:15px;">✓ Class cancelled</p>
                <p id="cancelClassSummary" class="hint-text" style="margin:0"></p>
                {{-- the whole-day success screen (2026-11-11): one line per class,
                     the withdrawn-request pill in red, and the total above. --}}
                <div id="cancelDaySuccess" style="display:none"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-outline" id="cancelKeepClassBtn" onclick="CancelClassModal.dismiss()">No, Keep Class</button>
            {{-- 2026-11-10: the short warning rides directly above Yes and only
                 while Yes is armed (CancelClassModal._refreshGate toggles it; the
                 wrapper is hidden wholesale in the success state). --}}
            <div class="cancel-confirm-wrap" id="cancelConfirmWrap">
                @include('partials.ui-irreversible-line', ['compact' => true])
                <button class="btn-danger" id="confirmCancelClassBtn" onclick="CancelClassModal.confirm()" disabled>Yes, Cancel Class</button>
            </div>
            <button class="btn-action" id="cancelArrangeNowBtn" style="display:none" onclick="CancelClassModal.arrangeNow()">Arrange Replacement Now</button>
            <button class="btn-outline" id="cancelLaterBtn" style="display:none" onclick="CancelClassModal.later()">I'll Do It Later</button>
            {{-- the whole-day success screen offers ONE button (user, 2026-11-11):
                 arranging four classes is not a single next step, so the screen
                 closes and the grid is refreshed from the server. --}}
            <button class="btn-outline" id="cancelDayCloseBtn" style="display:none" onclick="CancelClassModal.dismiss()">Close</button>
        </div>
    </div>
</div>
