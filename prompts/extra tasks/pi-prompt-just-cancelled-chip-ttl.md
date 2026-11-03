# PI PROMPT — the "Just cancelled" chip: give it a recency window, then switch it back on

> **Paste the whole file into a PI session.** It is self-contained: §1 explains what
> the chip is and exactly when it appears/disappears today, §2 states the gap, §3 is
> the task. Read §1 before touching code — the current state is *disabled*, and
> restoring it wrongly is the main risk.
> Everything below is this repo's law; do not soften it per task.

---

## 0. Task card

| Field | Value |
|---|---|
| **Mode** | `IMPLEMENT` |
| **Task** | Give `/replacement-home-ui`'s **"Just cancelled"** chip a recency window (display-only TTL), then restore the chip — it is currently commented out |
| **Why** | The chip says **"Just"** but never expires: the ledger has no TTL and `sessionStorage` only dies with the tab, so the badge (and its Undo) can sit on the page for hours and misreport the cancellation as recent |
| **In scope** | `resources/views/livewire/replacement-home.blade.php` (the commented block), `public/js/ui-common.js` (the `ClassCancellation` object only), `tests/cancel-class.spec.ts` (the skipped S13 chip test) **or** a new read-only spec, `page-changelogs/replacement-home-changelog.md` |
| **Out of scope** | the undo **toast** lifecycle, `applyLedger`'s replay semantics, `markConsumedByCode`, the `class_exceptions` server model, `resources/views/ui-design-templates/replacement-home-UI-design-template.blade.php` (design reference, not live), other pages, other lanes, other sessions' uncommitted files |
| **Deliverable** | code + receipt + changelog entry + commit (never push) |
| **SDD** | **skip** — display-only, one predicate + one restore, inside one page. Say so in your report. (If you find yourself touching the server model or more than 3 files, stop and ask.) |
| **DB impact** | **none** — no migration, no query change. Seeding a *browser* ledger entry for the receipt is allowed; writing the demo DB is **not** |
| **Done when** | ① the chip renders again on a matching recent entry ② it does **not** render for an entry older than the window ③ the ledge replay / undo / toast behaviour is byte-identical ④ the receipt in §5 goes green ⑤ changelog entry written |

---

## 1. What the chip is, and when it appears / disappears (verified 2026-11-02)

### Purpose

`/replacement-home-ui` lists the viewer's **own** conflicted classes (a
`class_exceptions` stamp = cancelled, or a public holiday) — `app/Livewire/ReplacementHome.php`
:46–80. When the viewer cancels a class themselves, the Cancel-Class flow writes a
**cancellation ledger entry** into the tab's `sessionStorage`
(`classCancellationLedger`, `public/js/ui-common.js` :3770 / :4131). The home page then
marks **that row** with the chip so the user can see "this one is mine, from a moment
ago" and undo it:

```html
<span class="just-cancelled-chip">Just cancelled</span>
```

…plus a small **Undo** icon button next to it (`.just-cancelled-undo`).

### Appears when **all** of these are true

1. A ledger entry exists (`sessionStorage.classCancellationLedger`) with `chip: true`
   and `!consumed` — written by `ClassCancellation.cancel()` (:4131–4143).
2. The class is still listed on this page (own conflicted class, and **no**
   pending/approved request for that pair — the D1 skip, `ReplacementHome.php` :72).
3. The entry matches the row — `ClassCancellation.entryCoversRow(entry, row)` (:4180):
   `entry.row.id === row.id` (client row) **or**
   `entry.sessionId === row.sessionId && entry.matchKey.week + 1 === row.week`
   (server row — this is what makes it survive a reload).
4. `entry.chip` is truthy and `entry.consumed` is unset (:463 in the blade).

Rendered in **cell index 2 = the "Original Class" column** of that row.

### Disappears when (today)

| Trigger | Where |
|---|---|
| **Undo** pressed (chip's Undo **or** the toast) | `undo()` → `ledgerRemove` (:4156) + the server `cancelClassUndo` |
| A replacement is **submitted for that subject** | `markConsumedByCode()` sets `consumed: 'arranged'` (:4246) — the entry is kept, the chip stops |
| The class gets a pending/approved request | the row itself leaves this page (D1) |
| The pair no longer resolves **and** the entry is not server-bridged | `applyLedger()` self-evict (:4208–4216) |
| Tab closed / site data cleared | `sessionStorage` is tab-scoped |

**There is no time-based expiry anywhere.** That is the whole point of this task.

### ⚠ Current state (the reason this prompt exists)

The chip block in `resources/views/livewire/replacement-home.blade.php` is
**commented out** (search `TEMPORARILY DISABLED 2026-11-02`). Its ~37 lines are
prefixed with `// `; the marker comment above them names this prompt file. The CSS
(`.just-cancelled-chip`, `.just-cancelled-undo`), the `ClassCancellation` ledger
machinery and `undoCancelledByRowId()` (:515) are **all still live and untouched**.

**Do not "restore by retyping"**: delete the `// ` prefixes so the block comes back
byte-identical, then add the TTL (see §3).

---

## 2. The gap, in one line

`classCancellationLedger` entries carry `cancelledAt` (an ISO string, written at
:4135) but **nothing reads it for display**, so "Just cancelled" can be three hours —
or three tabs — stale.

## 3. The task

**Add a display-only recency window; keep storage exactly as it is.**

1. In `public/js/ui-common.js`'s `ClassCancellation` object, add **one constant**
   (`CHIP_TTL_MS = 30 * 60 * 1000` — 30 minutes; a teaching block) and **one
   predicate** with a docblock, e.g.:

   ```js
   /* S13 recency (2026-11-02): the chip says "JUST" — it may only render while the
      entry is inside the window. Storage/replay is untouched: an expired entry keeps
      replaying the cancelled state and simply stops wearing the chip (and its Undo).
      A missing/unparseable `cancelledAt` = NOT recent (fail closed). */
   entryIsRecent(entry) { … }
   ```

   Use `Date.parse(entry.cancelledAt)`; **fail closed** on a missing/bad stamp
   (old ledgers from before the field existed must not render a chip forever).
2. Restore the commented block and add `&& ClassCancellation.entryIsRecent(entry)` to
   the existing `some(...)` predicate — **that is the only logic change.** Do not
   restructure the block, do not move it out of the Original-Class cell, do not
   change the Undo wiring.
3. **Do not** prune the entry when it expires, do not touch `applyLedger`,
   `activeEntry`, `markConsumedByCode` or the toast. An expired entry stays in
   `sessionStorage` (harmless, bounded by the tab's life).
4. Keep the wording `Just cancelled` and both class names; keep the CSS untouched
   (it is already in the page's `<style>`).
5. **Changelog** — append a dated entry to
   `page-changelogs/replacement-home-changelog.md` (newest first) stating: the chip
   had no TTL, the word "Just" was unearned, the window is 30 min, storage/replay
   unchanged, and that the chip had been temporarily disabled on 2026-11-02 and is
   now back on.

**Decision already taken by the user (do not re-open):** the window is a
**display-only** filter of **30 minutes**; storage stays. The trade-off is accepted:
once the chip expires, the home page no longer offers Undo for that cancellation
(the 5-second toast is long gone by then). If the user later wants undo to outlive
the window, that is a **separate lane** (a real un-cancel affordance on the class's
own modal) — do not build it here.

## 4. Constraints

- Colours from `theme.css` tokens only (no new colour is needed — the CSS stays).
- No new dependency. No DB change. Never push. Conventional commit
  (`ui:` or `fix:` — one concern), only the files this task touches.
- The chip is **per-tab, per-user**; never move that state to the server.
- The cancelled-class list must stay honest: the row keeps showing the class with its
  reason badge whether or not the chip is on.

## 5. Receipts (how to prove it without touching the demo DB)

The demo DB is **read-only** for agents, so seed the *browser* ledger instead. This
exact probe went green before the chip was disabled (`chips: 1`) and green after it
(`chips: 0`), so it is a known-good instrument:

```js
// playwright, storageState '.auth/staff-5425.json', origin http://localhost:8000
await ctx.addInitScript((v) => {
  try { if (!sessionStorage.getItem('classCancellationLedger')) sessionStorage.setItem('classCancellationLedger', v); } catch (e) {}
}, JSON.stringify([{
  id: 'cxl-probe-1',
  matchKey: { week: 7, code: 'AMSE1003', di: 0, start: 2, lecturer: 'Surayaini Binti Basri' },
  sessionId: 99, exceptionId: 9, reason: 'medical_leave', detail: '',
  cancelledAt: new Date().toISOString(),        // ← set this to now − 31 min for the negative case
  row: { id: 9908, code: 'AMSE1003' }, chip: true, priorStatus: 'normal',
}]));
// then: goto http://localhost:8000/replacement-home-ui and count `.just-cancelled-chip`
```

- **positive:** `cancelledAt` = now → **1 chip + 1 Undo**
- **negative:** `cancelledAt` = now − 31 min → **0 chips**, and the row is still listed
- **missing stamp:** entry without `cancelledAt` → **0 chips** (fail closed)
- also assert: 4 rows still render, `pageerror` count 0, and that an expired entry did
  **not** remove the row.
- `node --check public/js/ui-common.js`; if you un-skip the S13 test in
  `tests/cancel-class.spec.ts`, it needs the testing-DB guard (`:8030`) like its
  neighbours — the rest of that file is S-guarded.
- Restart with `scripts/dev-serve.sh 8000` (it clears the view cache and starts the
  server with `opcache.revalidate_freq=0`, so your edit is live on the next request —
  do **not** diagnose "my change didn't take" as OPcache when you forgot this step).

## 6. Stop and ask

- if restoring the block would change anything other than the chip + Undo render;
- if the user wants the window to be longer/shorter than 30 min, or per-reason;
- if you conclude undo must outlive the chip (that is the separate lane);
- if a write to the demo DB looks necessary (it never should be).

## 7. Report

Short: what changed (`file:line`), the receipt numbers (4 cases above), the changelog
entry title, the commit hash + "nothing pushed", SDD = `skipped — display-only`, and
then the archive question from the repo template §10 ("Do you want me to archive this
prompt into `prompts/completed prompt (Archieve)/`?").
