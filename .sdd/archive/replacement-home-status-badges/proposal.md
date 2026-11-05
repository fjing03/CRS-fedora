# Proposal — replacement-home-status-badges

**Date:** 2026-11-03 · **Ask:** `prompts/pi-replacement-home-status-badges.md` (the nine
decisions were made by the user on 2026-11-03; the prompt is the approval).
**Scope:** display only — no schema, no migration, no demo-DB write, no merge with My
Requests.

---

## Why

`/replacement-home-ui` lists the lecturer's conflicted class occurrences — the ones that
still need an arrangement. Today a row **disappears** the moment its request goes pending
or approved (`ReplacementHome.php` skipped those occurrences). That hide was an agent
fix from 2026-10-11, never a user ruling, and it leaves the lecturer unable to see what
happened to a class they just arranged: the row vanishes, the reason vanishes, and the
only trace lives on a different page.

The user's decision (D1) reverses it: **keep the row**, and let its Action cell carry the
request's state — a clickable badge when the request is pending/approved, a muted label
above the restored Arrange button when it was rejected/cancelled.

## The nine decisions (locked — quoted, not re-opened)

| # | Decision |
|---|---|
| **D1** | A row with a **pending** or **approved** request stays in the table (this deliberately reverses the 2026-10-11 hide). Rejected/cancelled rows already return — unchanged |
| **D2** | Action cell: **Pending** / **Approved** as a clickable badge linking to `/my-request-history-ui?notif-hl=r<request_id>`. Rejected/cancelled: a muted **Rejected** / **Cancelled** label **stacked above** the Arrange button; the label is **not** clickable |
| **D3** | **Completed is not in this change.** It has no producer in the app. Do not add a Completed badge, do not build a completion step |
| **D4** | Update exactly three strings on the home page (subtitle, guide item, empty state). **Leave the three summary cards untouched** — they remain conflict totals |
| **D5** | Quick view: for a handled row the footer button becomes **View Request** (same destination as the badge) and the modal gains a real *Replacement Status* row. The mobile card shows the same badge and its tap goes to My Requests |
| **D6** | Highlight: add a **page-jump inside My Requests only**; if an active filter hides the row, show the existing toast instead of doing nothing. **Do not touch `applyNotifHighlight` or the bell** |
| **D7** | Days Left shows **"—"** on handled rows. Sorting still uses the real date, so order does not jump when a row becomes handled |
| **D8** | Sort order: rows that still need action first, handled rows after; the chosen column sorts **within** each group |
| **D9** | A public-holiday row keeps **"Public Holiday"** after approval (the `official_event` stamp is a consequence, not a cause) |

**Definition used throughout:** *handled* = the occurrence's newest request has a status
that is neither `rejected` nor `cancelled`. *Needs action* = no request, or the newest
request is `rejected` / `cancelled`.

## Acceptance matrix (the test oracle)

| Newest request status | Action cell | Days Left | Sorted | Click destination |
|---|---|---|---|---|
| none | Arrange Replacement | real | first group | arrangement page |
| `pending` | **Pending** badge (link) | `—` | handled group | MRH, `?notif-hl=r<id>` |
| `approved` | **Approved** badge (link) | `—` | handled group | MRH, `?notif-hl=r<id>` |
| `rejected` | muted **Rejected** label + Arrange button | real | first group | button → arrangement page |
| `cancelled` | muted **Cancelled** label + Arrange button | real | first group | button → arrangement page |

## In scope (the four files + tests)

`app/Livewire/ReplacementHome.php` (the payload) · `public/js/ui-common.js` (two optional
builder callbacks) · `resources/views/livewire/replacement-home.blade.php` (helpers, sort
group, card tap, quick view, three strings, two page-local CSS rules) ·
`resources/views/livewire/my-request-history.blade.php` (the page-jump) + feature tests +
one read-only browser spec + two changelogs + this lane.

## Out of scope (explicit)

- Any `completed` status, completion job or date-derived "Completed" label.
- **Any merge of the two pages** — the two-page shape is deliberate; the destination stays
  in the single helper `requestHref(c)` so a later merge edits one line.
- Schema changes / migration / demo-DB writes.
- The Request Approval payload, the notification bell, `applyNotifHighlight`, the three
  summary cards.
- Another lane's dirty files (never staged).

## Rejected alternatives (do not revive)

| Approach | Why rejected |
|---|---|
| Fake "Completed" from `approved` + a past date | My Requests would still say "Approved" — two screens disagree in front of the audience |
| A real completion step inside this lane | No scheduler runs under `artisan serve`; `completed` would stop counting as active while the DB's duplicate guard is a partial unique index on `pending, approved` → a double-request hole |
| Clickable Rejected/Cancelled label | The backend would have to ship the newest non-active request too, and two click targets in a 150 px cell invite misclicks |
| Hide past handled rows | That is the completion rule in disguise |
| Put the page-jump in the shared highlighter | Promote-on-3rd: only two landing pages need it, and the bell lane is in flight |
| Keep the payload's active-only query | It cannot tell a rejected row from a never-arranged one — the muted label would be impossible |

## Risks and how they are held down

| Risk | Mitigation |
|---|---|
| Handled rows "stay forever" with no expiry (completion is deferred) | Accepted and stated in the changelog: the list is a status board until the completion lane lands |
| The `official_event` stamp rewrites a calendar-holiday cause | D9's precedence: a holiday + `official_event` renders "Public Holiday". Trade-off documented (a lecturer who *chose* Official Event on a holiday week sees "Public Holiday" — indistinguishable in the data) |
| The badge doubles as the row's quick-view click | `event.stopPropagation()` on the badge (+ the card's tap branches before the arrangement handoff) |
| The notif highlighter strips `notif-hl` at 150 ms, before MRH renders | D6: the id is captured **synchronously** in MRH's own `DOMContentLoaded` handler and applied after the first render |
| A fourth state (`completed`) ever appears | The handled predicate is "not rejected/cancelled", so it degrades safely — without advertising it |

## Sequencing (agreed 2026-11-03)

1. **this lane** — the row keeps its status; two pages; the badge links to My Requests;
2. the completed-status lane (`prompts/pi-request-completed-status.md`) — the fifth state;
3. a later one-table merge.

Nothing here pre-builds the merge; `/my-request-history-ui` keeps working as a destination.
