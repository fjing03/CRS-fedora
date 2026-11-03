# Review log — replacement-home-status-badges

Append-only. Round 1 runs **before** any code (per the prompt §11). No `@sdd-reviewer`
subagent exists in this session, so the review is a code-anchored reading of the artifacts
against the prompt and the tree at head `068a5ed`; findings and fixes are recorded verbatim.

---

## Round 1 (pre-code) — 2026-11-03

Baseline: `prompts/pi-replacement-home-status-badges.md` (nine frozen decisions, §6/§7
contracts) + the code anchors (`ReplacementHome.php:66–90`, `ui-common.js:2481–2515` and
`:2866–2875`, `replacement-home.blade.php:194/202/243/381/438/557/611/656/296`,
`my-request-history.blade.php:321/392/915`).

### 🔴 Fixed

- **F1 — the D9 precedence as first drafted would have skipped approved rows' labels.**
  The draft applied the holiday override **before** the exception arm, which rewrote a
  lecturer-chosen `annual_leave` on a holiday week into "Public Holiday" (wrong: the user's
  leave is the real cause). The frozen prompt's shape is narrower and correct — the
  override fires **only** when the exception reason is `official_event`, i.e. the approval
  stamp. `design.md` §1.3 now carries the prompt's exact branch and the trade-off note.
- **F2 — the sort group key cannot be a pre-sort.** A first draft sorted the array into the
  two groups and then ran the column comparator; the comparator re-orders *across* the
  groups, so handled rows would drift back to the top on a date sort. Fixed: the group key
  is the first branch **inside** the existing comparator (`design.md` §3.2).
- **F3 — the days-left em dash must not move the sort key.** If `isHandled` replaced the
  comparator's value, the order would change as a row became handled (D7 forbids it). The
  builder renders the dash; `opts.daysLeft` keeps computing the real date for `sortState`.
- **F4 — the page-jump must capture the id synchronously.** `ui-common.js` strips
  `notif-hl` at 150 ms and MRH's first render is behind a 400 ms skeleton, so reading the
  URL inside the post-render callback would always be null. Fixed in the design (§4.1):
  capture at the top of `DOMContentLoaded`, apply after the first render.

### 🟡 Addressed

- **A1 — the mock template shares both builders.** Every new option is optional with
  today's behaviour as the default (`design.md` §2); the legacy
  `ui-design-templates/replacement-home-UI-design-template.blade.php` is untouched.
- **A2 — the Arrange button markup is copied, not retyped.** The prompt insists on a
  verbatim copy (SVG included) with `__SID__`/`__WEEK__` placeholders so the two buttons
  cannot drift; recorded as T3's requirement.
- **A3 — `statusClass()` takes TitleCase, the payload carries lowercase.** The page
  therefore builds the class from `statusLabel(c.requestStatus)`; using the raw payload
  value would have produced `status-pending`-less badges.
- **A4 — the em dash on a handled row** reuses the existing urgency span (no new colour,
  no new class); a muted class would have been a visual change the user did not ask for.
- **A5 — the read-only browser receipt vs the demo DB.** The demo DB has a **pending** row
  but **no approved** row; receipt 3 (Days Left `—`) is therefore asserted on the pending
  row, which shares the same code path, and the substitution is stated in the changelog
  rather than faked with a demo write (trap 1 / §10).
- **A6 — the page-2 jump cannot be receipted automatically** (needs >10 rows for one
  lecturer). Recorded now in `design.md` §6 and required in the changelog as a named manual
  path — the prompt explicitly asks for that honesty.

### 🔴 Outstanding

None.
