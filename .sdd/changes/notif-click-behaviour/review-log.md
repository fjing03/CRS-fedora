# Review log — `notif-click-behaviour`

Self-review (no `@sdd-reviewer` subagent in this harness), then the measured receipts.

## Proposal Round 1 — 2026-11-04

### 🔴 Fixed
- The first draft treated "completed" as a stored status. It is **derived** (`effectiveStatus`), so a
  stored-column check would never fire. The design routes every row through the same resolver the pages use.

### 🟡 Addressed
- Split the cancelled case out explicitly: it has **no request id**, so a request-id-only design would
  have left that click with nothing to point at.

## Design Round 1 — 2026-11-04

### 🔴 Fixed (found by probing, not by reading)
- **Detached-node flash.** `tryFind()` flashed the element it resolved 500 ms earlier. On
  `/my-replacements` the skeleton teardown rebuilds the table inside that window, so the measured
  element was `isConnected === false` while carrying `.bookable-flash` — a pulse nobody could see. Fixed
  by re-querying at flash time (FR 6.2).
- **Invalid selector.** `hideNotifRowInPlace` first used `row.closest('[wire\\:id]')`; the probe threw
  `SyntaxError: '[wire:id]' is not a valid selector`. Replaced with a containment lookup over
  `Livewire.all()` — no escaping to get wrong.

### 🟡 Addressed
- Mapped the **legacy** stored `/my-timetable-ui` rows in the partial, so the demo's existing
  notifications behave like newly written ones instead of staying on the old destination.

## Specs Round 1 — 2026-11-04

### 🟡 Addressed
- Added FR 4.4 (a failed hide falls back to navigation) — the first draft would have swallowed the click.

## Apply — 2026-11-04 (measured on the demo DB, read-only GETs)

Panel rows as rendered (`#notifList`, staff 5425):

| status | href | anchor | week |
|---|---|---|---|
| `approved` | `/my-replacements` | `r26` | — |
| `cancelled` | `/my-replacements` | `o24` | 7 |
| `cancelled` | `/my-replacements` | `o23` | 7 |
| `pending` (submitted) | `/request-approval-ui` | `r28` | — |
| `rejected` | `/my-request-history-ui` | `r24` | — |

Arrivals (each opened directly, `waitForFunction` on `.bookable-flash`):

| URL | result |
|---|---|
| `/my-replacements?notif-hl=r26` | row `4014` (request 26) flashed |
| `/my-replacements?notif-hl=o67&notif-w=14` | row `6714` flashed |
| `/my-replacements?notif-hl=o23&notif-w=7` (no such row for this lecturer) | no flash, no error, params stripped |

Other receipts: `pageerrors: none` on every run; the Livewire owner lookup finds exactly 1
`NavNotifications` component with a call API; the completed-branch helper declines (returns false) for a
synthetic id 0 without throwing, so it falls through to navigation rather than swallowing the click.

**Not measured:** the completed → hide write (no completed record in the demo DB; hiding is a
user-gated demo write) and the full test suite (the testing DB was in use elsewhere).

## Completed receipt — 2026-11-04 (user option A: one gated demo write, restored)

The demo holds no completed request, so the **status** the branch reads was stood in for; everything
else is the real path.

**Status resolution (read-only).** `ReplacementRequestMapper::effectiveStatus()` against the demo's
approved request:

| replacement date | verdict |
|---|---|
| its own date (2026-11-13) | `approved` |
| a past date (2026-10-01) | **`completed`** |
| a future date (2026-12-01) | `approved` |

**The click (one write, through the feature).** Panel rows `7 → 6`; `notifications.id = 117` left the
list; **the URL did not change** (the handler's `preventDefault` fired, so no navigation); no JS errors;
`hidden_at = 2026-11-04 09:53:20` in the database.

**The restore (the UI's own undo).** Hidden shelf showed the row; `Unhide` put it back — shelf `3 → 2`,
`hidden_at = NULL`, hidden count back to its pre-test **2**.

**Snapshot first:** `backups/class_replacement_fresh-pre-notif-hide-2026-11-04.dump`, `pg_restore --list`
verified. The write pair is logged in `docs/demo-db-data-changes.md` (never staged). Net DB change: **zero**.

## Full suite — 2026-11-04 (the user stopped the other session)

`DB_DATABASE=class_replacement_testing php artisan test`, backgrounded and polled:

```
{"tool":"phpunit","result":"passed","tests":340,"passed":340,"assertions":1875,"duration_ms":450801}
```

**340 / 340 passed, 1875 assertions, 450.8 s.** Baseline before this lane was 336 passed / 1849
assertions, so the four new tests and the re-pointed `NotificationsBridgeTest` assertions are green.
`class_replacement_testing` was reseeded to the canonical state afterwards (101 sessions / 189 users /
0 requests); the demo databases were not touched by the run.
