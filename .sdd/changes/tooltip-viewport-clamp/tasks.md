# Tasks — tooltip viewport clamp

> **Apply status:** **APPLIED** 2026-11-14 — commit `65f1dc1`. See `apply-log.md`.
> **Apply order:** T1 → T2 → T3 → T5 (T4 proven by probe; the `U2a-T18` spec run is open — see the apply log).

- [x] T1. CSS: `max-width: min(340px, calc(100vw - 24px))` + `box-sizing: border-box`.
- [x] T2. JS: one `placeAvailableTooltip(rect)` (clamp horizontally, flip upward when short).
- [x] T3. JS: remember the anchor rectangle; re-place on `resize` while showing.
- [x] T4. Probe at 1440 / 1024 / 768 / 390 / 360: right-edge and bottom-edge cells, plus a live resize and the 520px-tall flip. Done by probe; the `U2a-T18` spec run stays open (the `:8030` lane cannot log in — 0 users).
- [x] T5. Changelog + `php artisan view:clear` (the blade is cached) + `git diff --check`; commit only this lane's files.
