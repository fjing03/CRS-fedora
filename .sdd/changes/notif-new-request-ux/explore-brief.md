# Explore brief — notif-new-request-ux

**Date:** 2026-10-31
**Ask:** `prompts/pi-prompt-UIUX Update New Replacement Request.md` (6 parts: title-with-ID,
requester subtitle, description=schedule+venue, multiline hover tooltip, inactive-on-
read/approved/rejected, cancelled tooltip copy, inactive⇒READ category)
**Raw facts (611 lines, file:line anchors):** `explore-notes.md` in this directory — the
completeness checklist below references it; read it before designing.

---

## 1. Crystallized decisions (rulings on the explorer's 14 open questions)

| # | Question | Ruling |
|---|---|---|
| D1 | N+2 string authority (ask has 2 variants, code a 3rd) | The REQUIREMENTS line wins: `This notification is inactive because the request was cancelled by the requester.` (the ask literally says "Use the exact tooltip message"; the HTML example is illustrative markup only). The current code string (`…was cancelled.`) is replaced; both hardcoded tests updated. |
| D2 | Scope of "New replacement request" rows | `type === 'submitted'` rows ONLY. The `awaiting` reminder rows keep current behaviour — tension flagged (a stale active reminder after a decision can linger) but NOT expanded beyond the ask's literal scope. |
| D3 | Read → inactive (submitted rows) | ADOPTED. A read `submitted` row renders `.notif-row--inactive` (unclickable `<span>`, no href/data-id). This NARROWLY AMENDS notif-click-highlight's "read or unread, both clickable" law: it survives only for non-submitted types. The first click still marks read + navigates; the row settles inactive on the next poll. |
| D4 | Approved/Rejected → inactive vs the real-bridge D4 "decided links stay alive" law | ADOPTED — the D4 law is OVERTURNED for `submitted` rows (the user's newest word). Feature test asserting "approved keeps href" is rewritten to assert the inactive rendering. The PL reaches decided requests via the Request Approval page's status filters (manual navigation). Biggest law change — surfaced to the user. |
| D5 | N+3 mechanics | CLASSIFICATION, not stamping: inactive rows are excluded from the unread-only filter AND from `$unreadTotal` (badge/pill), regardless of `read_at`. `read_at` is left untouched (`markAll` unchanged; no double-handling). Badge == pill == actionable-unread (honest-zero preserved). |
| D6 | Data source for parts 1–4 | Uniform RENDER-TIME composition from the extended batch load: chain `->with([...])` onto the existing `resolveRequests` whereIn — still ONE `replacement_requests`-mentioning query (the law counts only that table; eager loads hit users/lecturers/venues/modules/semesters). |
| D7 | Legacy/demo rows | NO backfill (demo-DB writes need explicit approval; old rows are honest history). Render-time composition gives LEGACY submitted rows the new title/subtitle/desc/tooltip anyway — uniform UX with zero DB writes. The stored `body` column keeps its emitted history (bridge test on the stored string stays green). |
| D8 | Timestamp format/source/TZ | Tooltip "Requested at" = the notification's stored `created_at`, formatted WITH year per the ask example (`29 Oct 2026, 10:44 AM`), in the same display TZ the panel's existing abs-time uses. The row's time chip keeps its current no-year format (unchanged). |
| D9 | Relative-time vocabulary | NEW verbose formatter for the tooltip line only: `just now / 5 minutes ago / 1 hour ago / Yesterday / 2 days ago`. The row chip keeps `5m ago`. Recomputed per 10 s poll = dynamic. |
| D10 | Tooltip geometry (5 lines above a row in a fixed panel) | Reuse the existing engine (`initDataTipTooltips`, above + clamping). NO new positioning CSS. If the receipt proves clipping at top rows, a minimal engine clamp/flip is in-scope. |
| D11 | Venue name null | Render the code ALONE (`B002`), never a dangling `B002 — `. |
| D12 | "All relevant notification pages" | The ONE panel on every staff page (one-feed law) — receipts verify the panel across the staff pages; student surfaces carry no `submitted` rows and are untouched. |
| D13 | aria-label | UPDATED to mirror the new composition (title-with-ID + subtitle + desc, ` (inactive)` suffix) — per the ask's HTML example. |
| D14 | Recipients | UNCHANGED (all PLs, any dept). Confirmed the ask intends no recipient change. |
| D15 | Inactive-row tooltip for NON-cancelled reasons (read/approved/rejected) — reviewer round-1 blocker | Reason-specific copy FAMILY in the same frozen sentence pattern, with STRONGER-FACT- WINS precedence cancelled > approved > rejected: cancelled → the exact D1 sentence (`…because the request was cancelled by the requester.`); approved → `This notification is inactive because the request was approved.`; rejected → `This notification is inactive because the request was rejected.`; read (request still undecided) → `This notification is inactive because you have already read this request.` Today's single-string branch (`notif-row.blade.php:17-18`) becomes a reason MAP fed by the inactive classifier (which must classify the REASON, not a boolean). Bridge-test :191-201 rewrite asserts the approved sentence; receipts cover each family member. |

## 2. Rejected approaches (and why)

- **Store-time formatting (emitter writes the new title/body)** — splits the UX (only new
  rows formatted; legacy rows would keep the old look), changes the emitter signature,
  breaks the stored-body feature assertion, and CANNOT serve part 4's rich tooltip (no
  column holds it). Render-time wins on uniformity + feasibility (D6).
- **Physical `read_at` stamping for N+3** — writes during a 10 s poll render (dirty),
  stamping code needed in approve/reject/cancel AND markAll paths, and legacy already-
  inactive rows stay unread until a backfill that needs demo-DB approval → the badge stays
  wrong exactly where it matters. Classification-only (D5) needs no writes.
- **Body-column convention / multiline encoding for the tooltip** — string parsing
  fragility, pollutes emitted history, breaks the stored-body test.
- **New schema (meta JSON column) for tooltip data** — migration churn for display-only
  data that render-time derives from existing relations; the table is owned by the applied
  real-bridge lane.
- **Clickable inactive rows (`<a class="notif-row--inactive">`)** to preserve click-
  highlight — contradicts the landed inactive law (unclickable span, no href/data-id,
  not-allowed cursor) and the ask's "must not appear as active notifications".
- **Expanding N+1 to ALL request-linked types (incl. `awaiting`)** — beyond the ask's
  literal scope; the stale-reminder tension is surfaced to the user instead of silently
  broadening the change.
- **Backfilling legacy rows** — unnecessary (D6/D7 give uniform UX read-only) and needs
  explicit demo-DB write approval.

## 3. Final solution shape (what the change will do)

**Row anatomy after the change** (submitted rows, active):
```
[icon]  New replacement request (ID #76)          <- part 1 (stored title, rendered with ID)
        Poong Foo Jing (5425)                     <- part 2 (NEW subtitle line)
        Week 7 · Friday, 09:00–11:00 · B002       <- part 3 (desc; requester name removed)
        10:44 AM                                  <- time chip (unchanged)
```
Hover (active request-linked rows) — 5-line `data-tip`, engine's multiline `\n` arm:
```
Staff: 5425 — Stephen Curry
Requested at: 29 Oct 2026, 10:44 AM (5 minutes ago)
Proposed Schedule: Week 7 · Friday, 6 Nov 2026 · 09:00–11:00
Proposed Venue: B002 — Tutorial Room
Subject: AMSE2003 (L) — Subject Name
```
Inactive (read | approved | rejected | cancelled, submitted rows): `.notif-row--inactive`,
no href/data-id, aria ` (inactive)`; cancelled rows carry the exact D1 sentence; all
inactive rows count as READ (D5) — never in the unread filter, never in the badge/pill.

**Render-time composition** (`notif-row.blade.php` + a small composer, fed by the
extended `resolveRequests` batch):
- parts 1/2/3 override the display for `type === 'submitted'` rows with a resolvable
  request; any missing fact FAILS OPEN to the stored `title — body` (honest fallback,
  legacy-safe).
- part 4 tooltip for active request-linked rows (submitted/approved/rejected/awaiting);
  non-request rows (class-cancelled/update) keep the single-line `title — body` tip.
- date for the tooltip's schedule line: `semester` relation + `week_number`/`day_of_week`
  (same derivation the ReplacementRequestMapper uses; `DAY_NAMES` + `DISPLAY_TZ` reused).

**Unread maths** (`NavNotifications.php`): `$unreadTotal` and the unread-only filter
exclude inactive rows; hidden-at-zero badge/pill/mark-all semantics unchanged; the count
is BATCH-DERIVED in PHP from the resolved rows (NO `whereHas`/subquery exclusion — that
would make the :55 count a SECOND `replacement_requests`-mentioning query and fail the
one-query law :253-274; the whole mailbox loads unfixed, so PHP classification is free).

## 4. Key cross-module data flows

- `SubmitReplacementRequest.php:194-230` → `NotificationEmitter::requestSubmitted`
  (UNCHANGED — no new params, stored body untouched) → `notifications` rows.
- `NavNotifications::render` → `resolveRequests` batch (EXTENDED: eager loads
  proposer/lecturer/module/venue/semester + the broadened inactive rule) → view vars.
- `notif-row.blade.php` (title/subtitle/desc/aria/tip composed server-side — must survive
  the 10 s morph, no JS one-shot bindings).
- `ui-common.js` tooltip engine (multiline arm :3038-3042) — no mechanism change.
- Jump targets UNCHANGED (click-highlight param flow intact for the first click).

## 5. Test-impact map (frozen at proposal; details in notes §4.7)

- UPDATE: `NotificationsBridgeTest.php:147` (cancelled sentence), `:191-201` (approved
  keeps href → inactive), `:151-166` (badge law: inactive excluded from unread total);
  `notifications-island-testing-db.spec.ts:131-133` (read rows stay anchors → inactive
  spans) + `:148` (exact tip string).
- KEEP GREEN: `:82` (stored body fact — untouched by render-time), `:253-274` (one-query
  law — extended batch must keep ONE `replacement_requests` mention), ONE-FEED equality
  (format-agnostic).
- NEW receipts (apply phase): the 6 parts + multiline tooltip + inactive-on-read/
  approved/rejected + badge exclusion + fail-open fallback.

## 6. Open questions for the USER (non-blocking; proposal flags them)

1. D3/D4 overturn two frozen laws (click-highlight read-row navigation for submitted
   rows; decided-links-stay-alive). Confirm or reword.
2. D1's exact string — confirm the requirements-line variant.
3. D2: leave the `awaiting` reminder stale-row tension unaddressed (literal ask scope)?
4. D7: no backfill — legacy rows show the new look via render-time, but their stored
   body keeps the old text. OK?
