{{-- ═══ The ONE irreversible warning line (2026-11-05, user ruling) ═══
     Every FINAL decision's confirmation modal states it in the same words and
     the same tokens: `UNDONE` in capitals, bold, warning colour (the existing
     `.warn-action` keyword + `.confirm-warning` line — no new class, no new
     colour). Include it as the LAST element of the modal body, directly above
     the buttons, so it is read before the decision is taken.

     Promoted by the 3rd-duplication rule (AGENTS #7): Cancel Class, Cancel
     Replacement Request, Approve Request and Reject Request all carry it.
     Reversible actions (submitting a request, hiding a notification, logout)
     deliberately do NOT — the line must never claim an undo that exists.

     2026-11-10 (user, "Cancel this Class? — Cancellation Reason block"): the
     Cancel Class modal passes ['compact' => true]. It gets the SHORT form,
     `CANNOT UNDONE`, as a small right-aligned strip that sits in the FOOTER
     directly above the Yes button, and it is visible only while that button is
     armed (CancelClassModal._refreshGate owns the toggle). The other three
     modals pass nothing and keep the sentence byte-identical. --}}
@if (!empty($compact))
<p class="confirm-warning confirm-warning--compact" id="cancelClassUndoWarn" style="display:none">CANNOT UNDONE</p>
@else
<p class="confirm-warning" style="margin:12px 0 0">This action cannot be <span class="warn-action">UNDONE</span>.</p>
@endif
