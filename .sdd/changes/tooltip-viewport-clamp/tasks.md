# Tasks — tooltip viewport clamp

> **Apply status:** draft 2026-11-14.

- [ ] T1. CSS: `max-width: min(340px, calc(100vw - 24px))` + `box-sizing: border-box`.
- [ ] T2. JS: one `placeAvailableTooltip(rect)` (clamp horizontally, flip upward when short).
- [ ] T3. JS: remember the anchor rectangle; re-place on `resize` while showing.
- [ ] T4. Probe at 1440 / 1024 / 768 / 390: right-edge and bottom-edge cells, plus a live resize.
- [ ] T5. Changelog + `php artisan view:clear` (the blade is cached) + `git diff --check`; commit only this lane's files.
