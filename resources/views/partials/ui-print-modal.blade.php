{{--
    The Print dialog (amendment 1 to SDD timetable-print-v2, 2026-11-16).

    Why a dialog at all: the paper can now be printed for THIS week or for the WHOLE
    SEMESTER, and the two settings that decide it (which weeks, and whether only normal
    classes) belong to printing alone — so they live here instead of in the page chrome.
    The Normal Class switch used to sit in the week row; it moved in, which also gave the
    phone week row its width back (task 8's T16d #3 measures that box).

    House rules: the same .modal-overlay / .modal / .modal-header / .modal-body /
    .modal-footer families as the details pop-ups (the 2026-11-10 modal-consistency law),
    tokens only, no new dependency. The overlay ALSO carries .print-modal-overlay, which
    the print stylesheet hides: under this flow every print happens with the dialog open,
    so without that rule the overlay would print over the sheet.

    Nothing here prints on its own. The header's printer button opens it; only the Print
    button at the foot calls window.print() (PrintSheet owns both, public/js/ui-common.js).
--}}
<div class="modal-overlay print-modal-overlay" id="printModal" role="dialog" aria-modal="true" aria-labelledby="printModalTitle" style="display:none">
    <div class="modal modal--print">
        <div class="modal-header">
            <h2 class="modal-title" id="printModalTitle">Print timetable<span class="modal-subtitle" id="printModalSub"></span></h2>
        </div>
        <div class="modal-body">
            <div class="print-modal__field">
                <label class="print-modal__label" for="printWeekSelect">Week</label>
                <select class="filter-select print-modal__select" id="printWeekSelect">
                    <option value="all">All Weeks (Whole Semester)</option>
                </select>
            </div>

            {{-- The switch: applies to ONE week. For All Weeks the paper is always the
                 normal view, so the control is DISABLED and reads ON — a state the
                 reader can see, not a silent override (user ruling 2026-11-16: B&C,
                 disable the switch only).

                 It is the HOUSE toggle (amendment 2, 2026-11-16): the same
                 .toggle-wrapper / .toggle-track / .toggle-thumb / .toggle-label the
                 replacement pages' filter bar and the notification rows use, with its
                 label visible beside the track as those rows show it. There is no
                 private `print-modal__switch` component any more, and no caption above
                 it: the filter bar hides .toggle-label and lets a 10px uppercase
                 `filter-label` carry the words, which is that panel's voice, not this
                 dialog's. --}}
            <div class="print-modal__field print-modal__field--switch">
                <label class="toggle-wrapper" id="printNormalSwitch">
                    <input type="checkbox" id="printNormalToggle" aria-label="Show normal class only" aria-describedby="printNormalHint">
                    <span class="toggle-track"><span class="toggle-thumb"></span></span>
                    <span class="toggle-label">Show normal class only</span>
                </label>
                <p class="print-modal__hint" id="printNormalHint">All Weeks prints normal classes only.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-outline" id="printModalCancel">Cancel</button>
            <button type="button" class="btn-action" id="printModalPrint">Print</button>
        </div>
    </div>
</div>
