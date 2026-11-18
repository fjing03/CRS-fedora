# Batch 1 — document existing keyboard shortcuts

User requested a new modal tab if useful. Add **Shortcuts** to the three Batch 1 guides because each has page-specific keys. Reuse the existing desktop-only tab behavior (hidden at ≤768px) and table styles. Do not change either approved content tab or Batch 2.

- **My Replacements:** `/` focuses Search when Filters is open; left/right arrows page through results; `[`/`]` change the Original slot week filter; Escape closes open details/confirmation before clearing filters; `?` now opens this tab above 768px. Replace the redundant standalone shortcuts dialog and its dismiss branch. For `?`, allow Shift (needed to type the key), but yield to Ctrl/Alt/Meta, editing fields and open modals. A normal help open still starts on Getting started.
- **Venue Timetable:** `[`/`]` change week; `B` on a focused Available grid cell shows the slot actions without arranging or submitting. No new hotkey.
- **Request Approval:** up/down highlights requests, Enter opens highlighted details, `A`/`R` open the corresponding decision dialog only for a highlighted Pending request, Escape closes a dialog or clears the highlight. No new hotkey. State explicitly that opening a decision dialog does not submit it.

Reuse the existing dialog-local Escape handler: stop propagation before closing and restore focus to the help opener without clearing filters or row highlight. Verify desktop tab selection, the narrow/touch layout, visibility changes and reset, the migrated `?` and unchanged Arrangement, plus render/readability and approval copy. No decision submissions, fixtures, or changes to booking/authorization/data.

Independent review passed. **34 Chromium guide tests passed** in the testing service, including both themes, touch, the 768/769px boundary, `?` and Escape with an active search, and legacy and Arrangement regression checks. Screenshots inspected from `/tmp/crs-batch1-shortcuts-pass/`. The empty approval queue prevented real-row decision-dialog checks; those descriptions follow the existing handlers. No requests were submitted or decided.
