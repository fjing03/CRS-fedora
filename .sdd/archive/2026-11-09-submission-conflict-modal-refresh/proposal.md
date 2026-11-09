# Proposal — `submission-conflict-modal-refresh` (the user's UI/UX spec, 2026-10-30)

**The situation (the user's premise, agreed):** concurrent submissions on the
same slot = the FIRST wins, the SECOND's submission reports the FR 4.10
conflict via the "Submission Conflict" modal on `/replacement-arrangement`.
Expected behaviour, not a bug — but the modal is vague/misstyled (the P3
explore family flagged it too). The user's spec, verbatim-scoped:

1. **The confirm button becomes "Refresh Page"** on BOTH conflict types, and
   its action = `location.reload()` (the current URL — `session`, `week`,
   `from`, `code`, `cohort`, `venue`, `date`, `time` AND the notif
   `notif-hl`/`notif-w` params ride along automatically); NO auto-resubmit.
2. **Type B gets the multiline layout** (dynamic facts, no hardcoding):
   intro → the `label` line (venue · abbr+date · time range — the existing
   `label` builder) → "The block overlaps with an **EXISTING OCCUPIED
   BOOKING** at <12h range>." — the range = **the FIRST `at HH:MM–HH:MM`
   match in `r.reason`** (the failing/overlap pair; the holder-range paren,
   when present, is NOT surfaced in the headline line — the overlap is the
   user-facing fact; the holder detail stays server-wording-only), humanised
   via the shared `to12h`. **Parse-failure fallback:** if no range matches,
   the raw `r.reason` renders verbatim as a single row line (no headline or
   label line — the Type-B multiline layout needs the range; the raw reason
   stands alone, still under the shared NOTE/footer). → the NOTE → "Please
   **REFRESH** this page and **PICK** another available slot."
3. **The NOTE on BOTH types:** "Another staff member may have booked this
   slot before your submission."
4. **Keyword classes (theme tokens only, no new colors):** red =
   `.warn-keyword` (exists: error + bold + uppercase) for CONFLICT/
   OCCUPIED/UNAVAILABLE; green = NEW `.go-keyword` (`var(--color-success)`,
   bold, uppercase) for REFRESH/PICK; blue = NEW `.ge-keyword` (`var(--color-primary)`,
   bold, uppercase) for NOTE/SELECTED SLOT/BOOKING. The existing
   `.info-keyword` stays UNTOUCHED (it has other consumers — filter
   descriptions; restyling it would be an unrelated change).
5. **The error style (scoped, additive):** `#confirmModal .modal.error-modal`
   gains a red-based face — the title red, a red hairline under the header,
   the failed-rows container outlined in `--color-error-container` — toggled
   per call site (the `btn-danger` pattern), reset in `hideConfirmModal`.
   "Refresh Page" renders via the EXISTING `danger:true` red button (clear +
   easy to identify).
6. **Type A keeps its message** ("That slot was just taken.") + the shared
   NOTE + keywords + the Refresh Page button. **Detection:** a row is
   Type B IFF its `r.reason` contains the literal `runs into an existing
   booking` (covers both the `slot_not_available` occupied and the
   `span_overlap` 'Your block' variants + the holder paren); everything else
   (Type A, duplicate_active_request, holiday, reserve_interrupted, …) =
   the VERBATIM server row — same NAME — the NOTE + the keyword classes +
   the Refresh Page button apply at the MODAL level (shared across all
   rows), no per-reason rewording (the round-1 🟡-1 fold: the third-reason
   rows stay byte-verbatim; only the NOTE + the shared footer render).

**Server untouched.** `conflictText()` (ReplacementArrangement.php :1011+)
and every validation stay byte-identical — this is display-only. The client
parses `r.reason` (it already renders it); no new server fields.

## The change surface

- `resources/views/livewire/replacement-arrangement.blade.php`: the
  Submission Conflict call (:2211-2217) — body markup + the failed-row
  builder + the Refresh Page opts; `showConfirmModal`'s error-style toggle;
  `hideConfirmModal`'s class reset.
- `public/css/theme.css`: two keyword classes — `.go-keyword`
  (`var(--color-success)`, bold, uppercase) + `.ge-keyword`
  (`var(--color-primary)`, bold, uppercase; the round-1 💡 fold: the
  naming convention pairs with `.go-keyword`, staying distinct from the
  existing `.info-keyword` and its 15+ consumers) — plus the additive
  `#confirmModal .modal.error-modal` face.

## Out of scope

- The server conflict logic/messages (the user's constraint 7).
- Auto re-select/re-submit anything.
- The info-keyword consumers elsewhere.
- Any DB write.

## Verify

- Both types' rows assert: the NOTE present; the Refresh Page button
  reloads (URL params preserved — location.reload() keeps the full
  query string); no auto-resubmit (the callback code reads reload-only).
- The honest-success path + the OTHER confirm-modal call-sites keep their
  labels/behaviour (the danger/label opts already per-site).
- Gates: pint + phpstan-1G 0; the full suite; the arrangement family
  Playwright rows (--grep-invert "TC70"). NO push; no DB writes.
