{{-- ⋅ Select a Session modal — SDD arrange-intent-session-choice (design D2) ⋅
     House-consistent with the shared detail-sheet shell: .modal--detail
     (left title + subtitle), .detail-group/.detail-group-title/.detail-row
     definition rows, .filter-select inputs, plain .modal-footer — all from
     theme.css; bottom-sheet ≤768px comes free from the shared responsive
     rules. State is driven by the page script (openIntentSessionModal). --}}
<div class="modal-overlay" id="intentSessionModal" style="display:none" onclick="intentOverlayClose(event)">
    <div class="modal modal--detail">
        <div class="modal-header">
            <span class="modal-title">Select a Session<span class="modal-subtitle" id="intentSubtitle"></span></span>
            <button class="modal-close" onclick="closeIntentSessionModal()" data-tip="Cancel">&times;</button>
        </div>
        <div class="modal-body">
            <div id="intentNotice" class="detail-group" style="display:none">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--color-error); vertical-align: -2px; margin-right: 6px;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span id="intentNoticeText"></span>
            </div>
            <div id="intentPickers" class="detail-group">
                <div class="detail-group-title">Class information</div>
                <div class="detail-row">
                    <span class="detail-label">Subject</span>
                    <span class="detail-value"><select id="intentSubjectSelect" class="filter-select"></select></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Session</span>
                    <span class="detail-value"><select id="intentSessionSelect" class="filter-select"></select></span>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-close-modal" id="intentCancelBtn" onclick="closeIntentSessionModal()">Cancel</button>
            <button class="btn-book" id="intentArrangeBtn" onclick="confirmIntentSession()">Arrange</button>
        </div>
    </div>
</div>
