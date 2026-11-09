# Review log
No `sdd-reviewer` subagent in this harness; the four batches were self-reviewed against the brief
before freezing, and the deviations are recorded here.

## 🔴 Fixed
- First draft put `sender_line` into `_display`; that attribute only exists for live `submitted` rows
  and dies on read, so it would have gone blank exactly on the rows the brief targets. Moved to a
  computed attribute beside R5's `staff_line`.
- First draft hid the pill via CSS only; the Blade still emitted `hidden`+`display:none`, which the
  poll morph would restore. Both carriers changed.

## 🟡 Addressed
- The two subtitle spans (composed + staff line) collapse to one sender line — otherwise the panel
  would print `by X` twice.
- The brief lists "Read, inactive, unhidden". The button renders on every inactive row; the write gate
  (`isHideableRow`: read OR inactive) admits them all, so requiring `read_at` would only create a
  dead-looking row.

## 🔴 Outstanding
- none
