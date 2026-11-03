# Proposal — "Completed" request status (derived at read)

- **Lane:** `request-completed-status`
- **Created:** 2026-11-03
- **Source prompt:** `prompts/pi-request-completed-status.md` (the user's grill session, decisions locked 2026-11-03)
- **Prerequisite:** `replacement-home-status-badges` — **satisfied** (`a16727c` feat, `2e58547` apply/verify). This lane builds on that lane's `requestStatus` payload key and its home badge/label Action cell.
- **Order (C8):** badges lane first, this lane second, two separate SDD lanes, two separate commits.

## 1. Why

The database already **permits** `completed` (`replacement_requests_status_check`), and three
screens already *carry* a Completed badge, a Completed filter option and a Completed timeline
step. But **nothing in the app ever writes `completed`**, so:

- every "Exclude Completed" toggle has never hidden anything;
- the Completed status filter has never matched a row;
- My Requests' final timeline step has never read "Completed".

An examiner looking at an approved request whose replacement class has already happened sees
**Approved** — a status that is true about the *decision* but false about the *class*. The user's
ruling: show it as **Completed**, worked out when the page reads the row. No workflow, no button,
no write.

## 2. The nine locked decisions (quoted, verbatim from the prompt — not re-opened)

| # | Decision |
|---|---|
| **C1** | Completion is **derived at read time**, not stored. No new column, no migration, no audit entry, no backfill. |
| **C2** | The rule: stored status `approved` **and** the replacement class date is **strictly before today** → show **Completed**. (A class on Tuesday reads Completed on Wednesday.) |
| **C3** | It applies on **every screen that prints a request status**: My Requests, Request Approval (PL), the Replacement Home, and the JSON API — all through one shared helper. |
| **C4** | **Exclude Completed stays ON by default** on all three pages, and the Replacement Home gets the same toggle it never had. Same label, same tooltip wording, checked on every load. |
| **C5** | The home's Completed badge **is** a link to My Requests; and My Requests may **untick Exclude Completed for you** when that toggle is the *sole* reason the row is missing, then jump. Any other hiding cause keeps the existing toast. |
| **C6** | The My Requests timeline's final step is timed with the **replacement date**, not the approval timestamp. |
| **C7** | **No notification** for completion. The bell keeps its five events (handover ruling 1). |
| **C8** | Order of work: the **status-badges lane first**, this lane second, both in the same session, two separate SDD lanes and commits. |
| **C9** | **No demo-database write** for this lane. The viva twin already has the needed data (see §10). |

**Definition used throughout:** *effective status* = what the row should say, which is the stored
status except for the single `approved` + past-date case.

## 3. The behaviour matrix (the test oracle)

| Stored | Replacement date | Effective | Badge | Excluded by default |
|---|---|---|---|---|
| approved | tomorrow | approved | Approved (link) | no |
| approved | today | approved | Approved (link) | no |
| approved | yesterday | **completed** | **Completed (link)** | **yes** |
| approved | unreadable (no slot/venue) | approved | Approved (link) | no |
| pending | any | pending | Pending (link) | no |
| rejected / cancelled | any | unchanged | muted label + Arrange | no |

Cross-page rules:

- On Request Approval the same request reads **Completed**, and its `Approved` count no longer
  includes it (`Reviewed` does — that count already matches `['Approved','Rejected','Completed']`).
- The API's `status` field reads `Completed` for it.

## 4. In scope

| # | Surface | Change |
|---|---|---|
| 1 | `app/Services/ReplacementRequestMapper.php` | the ONE helper `effectiveStatus()` (prompt §6 contract, verbatim) |
| 2 | `ReplacementRequestMapper::toRow()` | PL queue **and** the API ride it (`$prettyStatus`) |
| 3 | `MyRequestHistory::requestRow()` | the lecturer page rides it |
| 4 | `ReplacementHome` payload | `requestStatus` becomes the effective value (adds the relations the helper needs) |
| 5 | `my-request-history.blade.php` | C6 timeline retime + C5 reveal exception inside `jumpToRequestRow()` |
| 6 | `replacement-home.blade.php` | C4 toggle (checked every load, no storage) + the row filter + the counts taken after it |
| 7 | tests | 5-case matrix on the testing DB; browser spec for the home toggle + the badge link |
| 8 | changelogs | home, My Requests, Request Approval |

## 5. Out of scope (and why)

- **Any button, permission, scheduler or job that marks a request completed** — the user asked for
  a display rule, not a workflow; a button needs a permission, an action, an audit row and a
  column, and nothing would show Completed until somebody clicked.
- **Any write on read** — a read that mutates the DB is the worst possible behaviour on a demo
  database, and it makes dedup a second problem.
- **Changing the stored status** — `whereIn('status', ['pending','approved'])` keeps its literal
  meaning and the active-request partial unique index is untouched.
- **Notifications, emails, bell rows** (C7).
- **Fixing the cross-semester display base** (trap 6 in the prompt): every database holds one
  semester, so the bug is latent; recorded as a known issue in `design.md` §6, not fixed here.
- **Demo-data changes** (C9) and **CSS** (the Completed badge colour already exists).
- **The `.filter-select` / merged-table work** other lanes are proposing — untouched.

## 6. Rejected alternatives (the user's grill session — do not revive)

| Approach | Why it was rejected |
|---|---|
| A "Mark completed" button (lecturer or PL) | Needs a permission, an action, an audit row and a column; nothing shows Completed until someone clicks |
| Writing the status on page load | A read mutating the DB — worst behaviour on a demo DB, and dedup becomes a second problem |
| Deriving it only on the home page | The exact contradiction that deferred this feature in the first place |
| Hiding completed rows permanently | The user asked for a toggle, consistent with the other two pages |
| Notifying on completion | Nothing runs on a timer; the "event" is a calendar boundary, not an action |
| Retiming the completed step to the approval time | Plainly false, and the modal is where an examiner looks |

## 7. Risks

| Risk | Mitigation |
|---|---|
| A **stored** `approved` row silently becomes `Completed` on the PL page, shifting the Approved count | That is the intended behaviour (C2/C3); the PL page's counts already split `Approved` vs `Reviewed` — verified in `design.md` §5, no page change |
| The helper guesses "Completed" when the slot is unreadable | Trap 4: `null` date → return the stored status. Explicit test |
| Time-grain comparison flips at the wrong moment | Trap 2: compare `startOfDay()` values only |
| The JS demo clock leaking into the rule | Trap 3: the server decides; the page only renders the string it is given |
| A page that prints the status but misses the helper (the fourth copy) | C3's call-site table is closed: three producers, all named in `design.md` §2; the API rides `toRow()` |
| The reveal exception un-doing a filter the user wanted | It fires **only** when the Exclude-Completed toggle is the sole cause, and it persists the visible state (`saveFilters()`) so the UI never lies about what it shows |
| Another lane's dirty files slipping into the commit | Trap 1: explicit `git add` paths, per-lane commit |
| Existing badges-lane test pins the raw stored status | Named in `design.md` §7: that one assertion is updated **with** the lane, because the behaviour it pinned is exactly what C2 changes |

## 8. Sequencing

1. This lane (helper + three surfaces + the two page behaviours).
2. Nothing after that. The one-table merge (a separate proposed lane) later reuses
   `requestHref()` and `effectiveStatus()`; it does not block this lane and this lane does not
   block it.
