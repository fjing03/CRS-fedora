# UI/UX Update — My Replacements: highlight the new request after submit

**Date:** 2026-11-05 · **For:** PI · **Status:** brief only — no code written yet
(all line numbers are from today's `HEAD`)

---

## 1. The ask

> After successfully submitting a Replacement Request:
> 1. highlight the new `<tr>` record on the My Replacements page — reuse the existing UI
>    design (the one used for **Today** and **Earliest bookable**);
> 2. a toast pops out near the bottom-left (same position as the other toasts) — **remove it**.

In plain words: submitting a request should take the lecturer straight to `/my-replacements`
and **point at the row that was just created** with the pulse the app already uses for "look
here". The success toast that appears today should disappear, because the highlight replaces it.

---

## 2. What happens today (read this before changing anything)

**The submit success branch** — `resources/views/livewire/replacement-arrangement.blade.php`

```js
const sub = buildSubmissionToastMessage();
deselectBlock();
Object.keys(selectedSlotsByVenue).forEach(k => { selectedSlotsByVenue[k] = {}; });
selectionHistory.length = 0;                 // Ctrl+Z must not resurrect sent slots
updateCounter();
toast.show(sub.message, null, 5000, 'View →', '/my-replacements', sub.details);   // line 2264
setTimeout(function() { window.location.href = '/my-replacements'; }, 1200);      // line 2268
```

- The toast text is built by `buildSubmissionToastMessage()` (line 2108): the message
  `✓ Submitted — Pending Approval` plus a details line such as
  `B110 · Mon, 9 Nov 2026 · 11:00 AM–1:00 PM`.
- The 1200 ms delay exists **only** so that toast can be read. Without the toast the delay
  has no purpose.
- The branch also runs `ClassCancellation.markConsumedByCode(...)`, `setRecentSlot(...)`
  and the selection clean-up — **none of that may be lost**.

**What the server returns** — `app/Livewire/ReplacementArrangement.php`

`submitBlocks()` returns one entry per selected block (lines 1116–1129) with just
`ok` and `reason`. The underlying action
(`app/Actions/SubmitReplacementRequest.php`, `succeed()` line 186) **already returns
`request_id`** — the Livewire layer simply drops it. That id is what the redirect needs.

**How a row is identified on My Replacements** — `resources/views/livewire/replacement-home.blade.php`

Both ids are on every row (lines ~688–694):

```js
row.dataset.id = c.id;                    // the occurrence (class) id
row.dataset.requestId = c.requestId || '';// the request id — newest request wins
```

The page opens with **"Exclude Completed" ticked** (line 209) and restores any saved filters
(`restoreFilters()`); a freshly submitted request is `pending`, so the default filter shows it.

**Two arrival mechanisms already exist**

| Param | Handler | Flashes? |
| --- | --- | --- |
| `?notif-hl=r<id>` (+ `&view=request`) | `jumpToMyRow()` line 997 | No — it opens/pages to the row |
| `?notif-hl=o<sessionId>&notif-w=<week>` | `jumpToMyOccurrence()` line 1048 | **Yes** — `tryFind('tr[data-id="…"]', 20)` line 1066 |

**The flash itself (reuse, do not reinvent)**

- `tryFind(sel, retries, fallbackSel)` — `public/js/ui-common.js:390`: scrolls the element
  into view, then re-queries and flashes it after 500 ms; retries every 250 ms.
- `flashRowPulse(el)` — `public/js/ui-common.js:222`: adds `.bookable-flash` for the whole run.
- `@keyframes bookable-pulse` — `public/css/theme.css` ~2779–2803: 3 pulses × 0.5 s, 4 px ring,
  63 % `--color-success`. This is the same language as the Today button / Earliest-bookable jump.

**Today:** nothing highlights a freshly submitted request. There is no `?new…` param; the
redirect goes to a bare `/my-replacements`.

---

## 3. Change 1 — remove the success toast

In the success branch of `replacement-arrangement.blade.php`:

1. Delete the `toast.show(...)` call (line 2264) — message, `View →` link and details line all go.
2. Delete `buildSubmissionToastMessage()` (line 2108) and the now-unused `sub` variable; it has
   no other caller (verify with a grep before deleting).
3. Replace the `setTimeout(…, 1200)` with an immediate navigation to the new URL (see §4.1).
4. Keep every other statement in that branch exactly as it is (consumed-ledger marker, recent
   slot, deselect, slot clearing, history wipe, counter).
5. Do **not** touch the conflict branch (`showConfirmModal('Submission CONFLICT', …)`) — a failed
   submission keeps its modal, no redirect, no highlight.

No other toast on any page changes. The bottom-left stack and its CSS stay as they are.

---

## 4. Change 2 — highlight the new row

### 4.1 Carry the id across the redirect

- **Server (additive only):** in `ReplacementArrangement::submitBlocks()`, add one key to the
  per-block result:

  ```php
  'request_id' => $result['ok'] ? ($result['request_id'] ?? null) : null,
  ```

  Nothing else reads the array, so this is a pure addition. The failure entries keep
  `request_id` absent/null.
- **Client:** after the loop, collect every `ok` result's `request_id` and redirect:

  ```js
  const ids = (results || []).filter(r => r && r.ok && r.request_id).map(r => r.request_id);
  window.location.href = '/my-replacements' + (ids.length ? '?new-req=' + ids.join(',') : '');
  ```

  Use a new, dedicated param — **`new-req`**. Do **not** reuse `notif-hl` (that is the
  notification jump, it is stripped at 150 ms by the shared highlighter, and its filter
  fallback shows its own toast).

### 4.2 Consume it on the page

Add `jumpToNewRequest(ids)` next to `jumpToMyRow()` in `replacement-home.blade.php`, and call it
from the existing `DOMContentLoaded` arrival block (line ~1114) — capture `new-req`
**synchronously**, the same way `pendingHl` is captured, because the table is behind the
400 ms skeleton.

Behaviour per id, in order:

1. Find the index in `currentFiltered` by `requestId` (the array is filled by `buildTable()`).
2. **Not found** → clear the filters that could be hiding it — `searchInput`, `statusFilter`,
   `weekFilter` — then `buildTable()` and look again. **Leave `hideCompleted` ticked:** a new
   request is `pending`, which that filter shows by default, so it can never be the culprit and
   un-ticking it would only flood the list with completed rows (user, 2026-11-05).
   **Do not call `saveFilters()`** (user ruling 2026-11-05): the clearance is for this visit only,
   so the lecturer's saved filters come back next time. `buildTable()` already reads the inputs
   from the DOM, so setting them programmatically is enough — do not dispatch `change` events,
   which would persist.
3. **Still not found** → silent skip. No toast (the old "hidden by your current filters"
   toast is exactly the kind of message this update removes).
4. **Found** → page it into view (`pageState.currentPage = Math.floor(idx / state.rpp) + 1`,
   rebuild when the page changes) and then
   `tryFind('tr[data-request-id="' + id + '"]', 20)`. No `fallbackSel` — flashing the wrong
   row would be worse than not flashing.

Finally strip the param with `history.replaceState` (same pattern as
`applyReplacementHighlight()`, `ui-common.js` ~360) so a refresh or Back does not replay the pulse.

### 4.3 Two nuances worth knowing

- **One submission = one request.** `selectBlock()` (`replacement-arrangement.blade.php:1602`)
  refuses a second pick while a block is selected ("You already have a selected block. Clear it
  first."), so the normal — and only — path creates a single request and a single new row
  (user, 2026-11-05). The id list in `?new-req=` and the `for` loop on arrival stay generic
  because the server action returns an array; with one id the loop runs once.
- **The pulse is shared.** 3 pulses ≈ 1.5 s of ring, then the row returns to normal. No lasting
  tint, no new keyframe, no new colour (user ruling 2026-11-05).

---

## 5. Reuse map

| What | Where | Why |
| --- | --- | --- |
| `flashRowPulse(el)` | `public/js/ui-common.js:222` | the exact pulse the user asked to reuse |
| `tryFind(sel, left, fallbackSel)` | `public/js/ui-common.js:390` | scroll + retry + flash after skeleton teardown |
| `.bookable-flash` / `@keyframes bookable-pulse` | `public/css/theme.css` ~2779–2803 | the visuals — no new CSS |
| `jumpToMyRow()` filter/page logic | `replacement-home.blade.php:997` | index lookup, filter fallback, page-into-view |
| arrival capture + skeleton wrapper | `replacement-home.blade.php:1114–1148` | where the new hook goes |
| `history.replaceState` param strip | `ui-common.js` ~360 (`applyReplacementHighlight`) | one-shot param |

---

## 6. Edge cases and expected behaviour

| Case | Expected |
| --- | --- |
| One block submitted | land on `/my-replacements?new-req=28`, row 28 pulses, URL cleaned |
| Several ids passed to `new-req` | the loop flashes every id that matches a row — today unreachable, since only one block can be picked (`selectBlock`) |
| Some blocks fail, some succeed | conflict modal as today, **no** redirect, no highlight |
| A filter (search / status / week) hides the new row | those filters cleared **for this visit only**, row flashed; saved filters return next time |
| "Exclude Completed" is ticked (the page default) | no effect — the new request is Pending, so it stays visible; the tick is left alone |
| Row cannot be found at all | silent, no toast |
| Lecturer refreshes or presses Back | param already stripped → no second pulse |
| Slow skeleton / slow server render | `tryFind` retries for ~5 s |
| Notification jumps, Today / Earliest bookable | unchanged |

---

## 7. Constraints (house rules)

- No new dependencies; colours only from `theme.css` custom properties; reuse
  Flux/Livewire/Tailwind + `theme.css` + `ui-common.js`.
- Demo DB is read-only for writes; testing DB only via `DB_DATABASE=class_replacement_testing`;
  Playwright specs that WRITE only with `CRS_MODE=testing CRS_ALLOW_SQL=1 CRS_BASE=http://127.0.0.1:8030`;
  never loosen `tests/helpers/receipt-target-guard.ts`.
- Update the page changelogs: `page-changelogs/replacement-arrangement-changelog.md` (toast removed)
  and `page-changelogs/replacement-home-changelog.md` (arrival highlight).
- PHPUnit baseline 344 / 1899 must stay green; `pint` clean; `node --check public/js/ui-common.js`.
- Conventional commit, e.g. `ui(my-replacements): the new request is highlighted, not toasted`.
- Never push.

---

## 8. Acceptance criteria

1. No toast appears at any point after a successful submission (assert zero toast nodes).
2. The browser lands on `/my-replacements` immediately (no 1.2 s pause).
3. The new row scrolls into view and pulses exactly like the Today / Earliest-bookable jump
   (3 × 0.5 s ring), with no new CSS.
4. The URL no longer carries `new-req` once the page has settled; refresh shows no pulse.
5. The submission creates exactly one row — and that row is the one that flashes.
6. A filter that would hide the row is cleared, and the row is still flashed.
7. The conflict path is byte-identical (modal, reasons, no redirect).
8. No console errors; other pages' toasts untouched.

---

## 9. Decisions (settled 2026-11-05)

1. **Highlight style:** flash three times with the shared pulse, then the row looks normal.
   No lasting tint, no new style.
2. **Filters that hide the new row:** clear them so the row shows and flashes.
3. **Several new rows:** not reachable — `selectBlock()` allows one block per submission, so there
   is one request and one row. The code still flashes every id it is handed, for safety.
4. **Clearing filters:** for this visit only. Do not call `saveFilters()`; the lecturer's saved
   filters come back the next time they open the page. Only search, status and week are cleared —
   **"Exclude Completed" stays ticked** (it is the page default and a pending request is never
   hidden by it).
