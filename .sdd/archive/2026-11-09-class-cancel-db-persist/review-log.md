# review-log — class-cancel-db-persist

## Round 1 — 2026-10-27 (glm-5.3-flash)
### 🔴 Fixed (all folded into proposal.md)
 ① CancelClass::REASONS + CancelClassRequest rules widen to the 10 (the CHECK
   alone writes nothing); the signature gains `?string $otherDetail` riding the
   audit details; the Request mirrors the enum.
 ② the Other description rides the trait → the Action → the audit details; validate()'s
   literal re-keys on 'other'; the ledger/home conflictReason now hold the ENUM (the
   home titleCase law renders it; the medical_leave → 'Medical Leave' drift declared).
 ③ ReplacementHome joins the trait list — the home chip's Undo (:4151 cross-page at any
   time) is a PINNED acceptance mechanic; window.Livewire.first() resolves the page
   component (the my-request-history :647 pattern verified).
 ④ both undo surfaces (the 5s toast + the home chip) post cancelClassUndo
   await-then-revert — ok reverts client+server, fail keeps the cancel + the hint; the
   undo audit reads the row BEFORE the delete (the reason it erased).
### 🟡 Addressed
 - the Yes pending-disables during the server await; the SERVER response is the truth
   (no client flip on fail — the midnight-roll adoption folded).
 - the migration down() mirrors the 2026_08_24 narrowing-hazard comment; the demo
   rollback SQL added to the sheet.
 - the Playwright re-keys scoped: tests/cancel-class.spec.ts radio locators ×4 + the
   ledger/label assertions (:243) + e2e/full-suite.spec.js:218 + e2e/api-wiring.spec.js:83.
 - the brief's stale "5→9" line corrected to 5→10.
## Round 2 — 2026-10-27
### 🔴 Section added (proposal.md): cancelReasonEnum → titleCase → badgeClass mapping
 - ②' the SECOND 'Other' literal (CancelClassModal._refreshGate :4376 `reason === 'Other'`)
   re-keys on 'other' (the reviewer's heaviest find — the confirm's Yes gate would
   never enable).
 - ③' the home BADGE map (`badgeClass`, replacement-home.blade.php :302–:312) has NO
   colors for the NEW leaves — the enum values would render theipheral (map[reason] || '').
   Added: `Hospitalisation/Compassionate/Paternity` join
   `--color-primary-container`/`--color-on-primary-container` (the 'Official Event'
   precedent), Maternity trimestre `--color-tertiary-container`
   /`--color-on-tertiary-container` (the medical pair, the sibling leave), `Other`
   = `--color-secondary-container`/`--color-on-secondary-container` (the neutral
   grey family); the home's `titleCase()` LAW extends inline: `medical_leave` →
   'Medical/Sick Leave' (the MODAL label equals the home badge now — the drift
   receipt retired), `other` → 'Other', the 4 new leaves titleCase naturally.
 - ③'' the FILTER dropdown (:353 `matchesReason === c.conflictReason`) is enum-typed
   now (the home badge's reason labels = the enum titleCase — the filter and the
   damage walked together).
### 🟡 Retired-Other literal cleanup — the home's filter row keeps working:
 - the home's status filter dropdown re-keys to the enum-typed conflictReason (the
   damage receipt).
### 💡 Adopted
 - undoViaServer promoted to ONE shared ClassCancellation call (both surfaces
   single-homed, the 🔴2 fix).
### 🔴 Fixed (round-2 folds in proposal.md)
 - ②' the SECOND Other literal (_refreshGate :4376) re-keys on 'other' — the
   confirm's Yes gate would never enable under the label law.
 - ③' the home badgeClass map gains the 5 new enum → token-pair entries
   (hospitalisation/maternity = the page's local CSS family; compassionate/
   paternity the primary-container neutral; other the secondary-container) —
   otherwise the new leaves render badge-colorless.
 - ③' the home titleCase law extends inline (medical_leave → 'Medical/Sick
   Leave' — the modal label EQUALS the home badge now, the drift receipt
   retired; other → 'Other').
 - ③'' the home's status filter re-keys to the enum-typed conflictReason.
 - the undoViaServer call promoted (both surfaces single-homed).
### 🟡 Addressed
 - the CancelClass docblock "5 schema CHECK values" + the
   ReplacementFlowTest:356 failure message re-keys — folded into T1's
   file list (the pint/phpstan reminder obligation).
## Verdict: READY TO FREEZE — proposal.md is receipt-complete; design.md + tasks.md next.

## Round 3 — 2026-10-27 (design + tasks first round)
### 🔴 Fixed (design/tasks folds)
 - the undo await-then-NODAUN ordering: the toast callback is RESTRUCTURED
   (the async IIFE — disables the Undo affordance, awaits, THEN deep-links
   ?restored=1; the pending-disable receipt; the user-navigates-away caveat
   documented); the home chip pending-disables its Undo button.
 - the trait wraps delete-then-audit in DB::transaction (the cancel-Action
   pattern — no deserted audit row).
 - T2's sheet target re-keyed to docs/demo-db-data-changes.md (the
   convention; the proposal is frozen and carries the sheet already).
 - tasks.md T3's receipts strengthened; the explore-brief's stale "5→9"
   line corrected to 5→10.
### 🟡 Triaged as receipts (no change)
 - the home's :353 filter is hard-typed 'all' — no dropdown exists
   (verified: the receipt drops the word "dropdown");
 - CancelClass returning the exception id directly vs the trait's
   two-step query — the two-step stays (the smallest-diff principle);
### Verdict in-progress: re-review on the fold above pending.

## Round 4 — 2026-10-27 (the round-3 fold lands)
### 🔴 Fixed
 - the two Scope rows added (the home blade's 4 receipts + the demo-db
   sheet paste-target).
 - the D5 down() contradiction struck — the frozen proposal's stance wins.
 - the D1 snippet folds the DB::transaction wrap + the real use imports.
### 🟡 Folded
 - the pseudo import line cleaned.
## Verdict: internal consistency reached — READY TO FREEZE / APPLY.

## Round 5 — 2026-10-27 (FINAL)
 - all three round-3 folds verified landed; no residual diffs; two
   cosmetic suggestions only (the validReason host naming + the enum-typo
   conflated message — noted for the implementer).
 - VERDICT: READY TO FREEZE / READY FOR APPLY.
 - the artifacts FROZEN: proposal.md, design.md, tasks.md. Next = /sdd-apply.
