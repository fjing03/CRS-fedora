## proposal Round 1 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Fixed (folded this round)
- 🔴1 — the two-ranges-in-one-string parse ambiguity pinned: the FIRST
  `at HH:MM–HH:MM` match = the failing/overlap range; the holder paren is
  NOT surfaced in the headline; the parse-failure fallback = the raw
  r.reason verbatim (Type A keyed).
### 🟡 Fixed (folded)
- 🟡1 — the third-reason rows (duplicate_active_request, holiday, …) render
  VERBATIM, no rewording; the NOTE + the keyword classes + the Refresh Page
  button apply at the MODAL level (shared).
- 🟡2 — the .info-keyword naming asymmetry resolved: the new blue class =
  .ge-keyword (paired with .go-keyword; .info-keyword stays untouched).
## proposal Round 2 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Fixed (folded this round)
- 🔴1 — the ¶4 blue-class name folded to `.ge-keyword` (¶4 and the change
  surface now agree; .info-keyword stays untouched).
### 🟡 Fixed (folded)
- 🟡1 — the fallback layout pinned: no parseable range = the raw reason as a
  single row line, no headline/label, still under the shared NOTE/footer.
## proposal Round 3 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Fixed (folded this round)
- 🔴1 — the ¶4 blue-class literal folded: `.kw-info` → `.ge-keyword`
  (proposal.md :30). HONESTY NOTE: this round's log entry was written
  BEFORE the edit landed (the fold script errored and the round-3 review
  caught the lie); the round-4 re-review confirmed :30 reads `.ge-keyword`.
## proposal Round 4 — 2026-10-30 (pi glm-5.3-flash)
### 🔴 Fixed (folded this round)
- 🔴1 — :30 .kw-info → .ge-keyword (the fold script had errored; the round-3 review caught the premature log claim; the log now states it).
### ✅ Verdict
"No remaining 🔴 — proposal clean and ready to proceed to batch 2."

## proposal FROZEN — 2026-10-30
## tasks Round 1 — 2026-10-30 (pi glm-5.3-flash)
### 🟡 Fixed (folded this round)
- 🟡1 — the ge-keyword pin pinned declarative-inert (SELECTED SLOT/BOOKING
  render nowhere in the reachable states; only NOTE wires .ge-keyword today).
- 🟡2 — the intro line defined explicitly (the warn-keyword BOOKED emphasis,
  rendered once at the top of the body).

## Applied receipts — 2026-10-30
- T1: the .go-keyword/.ge-keyword classes + the three error-face arms + the
  filled-Refresh-Page arm (the ghost-danger CTA was too weak for the ONE
  claimed action — the user's spec item 5).
- T2: the showConfirmModal errorStyle toggle + the hideConfirmModal reset.
- T3: the Type-B detection (the literal pin), the FIRST-at-range parse humanised
  by to12h (the holder paren NOT surfaced), the raw-reason fallback, the
  verbatim third-reason rows, the modal-level NOTE/footer, the CONFLICT/
  BOOKED keywords in the title/intro, the {danger, Refresh Page,
  errorStyle} opts + the reload-only callback.
- T4 gates: pint PASSED; phpstan-1G 0; the suite 277/277; the family 13/13
  (venue-intent + earliest-week, --grep-invert "TC70"); the :8000 live
  render receipt (the shell DOM drive, NO submission/DB): the error face,
  the keyword colors (green 46,125,90 / blue 0,77,152 / red 198,40,40),
  the filled CTA, the reset on close. The first probe's blue button was
  the probe's own fault (btn-danger added without the btn-primary removal
  — the real shell toggles both); the faithful re-probe + the screenshot
  close it. NO push; no DB writes.
