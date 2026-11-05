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

---

## Round 2 (post-code verify) — 2026-11-03 · PASS

Commit `a16727c`. Verified against the frozen `proposal.md` + `design.md`, D1–D9 one by one,
on the tree (not on intent).

### Verify — decision by decision

| # | Verified in the code | Receipt |
|---|---|---|
| D1 | `ReplacementHome.php`: the `continue` is gone, `$latestRequests` replaces `$activeRequests`, every row carries `requestId`/`requestStatus` | `ReplacementHomeTest` keeps-with-status test; live rows 4–5 stay on the table |
| D2 | `actionCell()` → badge `<a>` (handled) or `action-stack` with the ended label above the button | spec receipts 1 + 7; live `?notif-hl=r26` / `r27` |
| D3 | no `completed` anywhere in the diff; the predicate is "not rejected/cancelled" | grep of the diff |
| D4 | exactly three strings; the summary partial untouched | diff shows 3 string lines only |
| D5 | `#qvArrangeLabel`, the *Replacement Status* row, the card tap/keydown branch, card badge | spec receipts 5 + 6 |
| D6 | synchronous capture + `jumpToRequestRow()` once after the first render + toast fallback; `applyNotifHighlight` untouched | probe: flash observed 662 ms after arrival; see the gap below |
| D7 | `daysText = handled ? '—' : …` in both builders; the comparator still reads `daysLeft(a.date)` | spec receipt 3 |
| D8 | the group key is the first branch of the existing comparator | spec receipt 4 |
| D9 | the override fires only for `official_event` on a holiday occurrence | `ReplacementHomeTest` holiday-precedence test |

### 🔴 Fixed during apply

- **The feature-test splice duplicated a block.** The rewrite replaced from the *first* match
  of the old test name rather than the old test itself, so three existing tests appeared twice
  (`Cannot redeclare …`). Removed the duplicate region; the file now holds 10 tests, each
  unique.
- **The page-local CSS anchor was wrong** (`    @section('page-styles')` vs the file's
  unindented tag). The script asserts, so nothing was half-written; re-applied against the
  real anchor.
- **`.auth/staff-5425.json` was stale** for the ad-hoc probes (redirected to login). The
  Playwright run regenerates it through the setup project — the spec is unaffected; the probe
  was switched to a real login.

### 🟡 Addressed

- The Arrange button markup in the page is a **verbatim copy** of the shared builder's button
  (`__SID__`/`__WEEK__` placeholders, SVG included). It is wrapped in `.action-stack` even
  when no ended label exists — a uniform cell shape, no visual change (the stack collapses to
  one child).
- The browser spec went from six to **seven** receipts: one extra asserts that an actionable
  row still offers *Arrange Replacement* and shows **no** *Replacement Status* row (the
  no-regression half of D2/D5).

### Browser receipt (live `:8000`, read-only, staff 5425)

| # | Observed |
|---|---|
| 1 | two handled rows carry `Pending` anchors (`?notif-hl=r26`, `?notif-hl=r27`) and **no** Arrange button |
| 2 | clicking one lands on `/my-request-history-ui`, `tr[data-id="27"]` present, `bookable-flash` observed **662 ms** after arrival, the `notif-hl` param stripped |
| 3 | both handled rows read `—`; the three needs-action rows keep real countdowns |
| 4 | the handled rows are rows **4–5** of 5 — after every needs-action row |
| 5 | at 390 px the card header carries the badge and the tap navigates to My Requests |
| 6 | the quick view on a handled row: footer **View Request**, modal contains *Replacement Status* + `Pending` |
| 7 | an actionable row's quick view: footer **Arrange Replacement**, no status row |

**D6's page-2 branch is NOT automatically receipted** — it needs >10 requests for one lecturer
and the demo user has 4. Recorded as a named manual path in both changelogs, not claimed.

### Gates
`composer run lint:check` → passed · `vendor/bin/phpstan analyse --memory-limit=1G` → 0
errors · `node --check public/js/ui-common.js` → ok · full suite
(`DB_DATABASE=class_replacement_testing`) → **315 passed**, 1685 assertions (312 before this
lane; −1 old hide test, +4 new) · spec **7/7** read-only.

### 🔴 Outstanding
None. Lane `applied`. **Nothing archived** (waiting for the user's word); nothing pushed.
