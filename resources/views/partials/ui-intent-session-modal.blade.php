{{-- ⋅ Select a Session modal — SDD arrange-intent-session-choice (design D2) ⋅
     Shared infrastructure per the user mandate: the .modal-overlay/.modal
     shell + .modal-footer/.btn-close-modal/.detail-row classes from the
     ui-class-detail-modal idiom + theme.css tokens; the bottom-sheet ≤768px
     variant comes free from the shared responsive rules. State is driven by
     the page script (openIntentSessionModal); this markup is dumb. --}}
<div class="modal-overlay" id="intentSessionModal" style="display:none" onclick="intentOverlayClose(event)">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title">Select a Session</span>
            <button class="modal-close" onclick="closeIntentSessionModal()" data-tip="Cancel">&times;</button>
        </div>
        <div class="modal-body">
            <div id="intentNotice" style="display:none"></div>
            <div id="intentPickers">
                <div class="detail-row">
                    <span class="detail-label">Subject</span>
                    <select id="intentSubjectSelect"></select>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Session</span>
                    <select id="intentSessionSelect"></select>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-close-modal" id="intentCancelBtn" onclick="closeIntentSessionModal()">Cancel</button>
            <button class="btn-book" id="intentArrangeBtn" onclick="confirmIntentSession()">Arrange</button>
        </div>
    </div>
</div>
