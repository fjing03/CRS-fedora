# Manual test flow — the notification clock (MYT) and every time surface it touches

**What this proves.** Every user-facing **absolute** time in the notifications panel is printed in
**Asia/Kuala_Lumpur (MYT, +08)** by one helper (`App\Support\LocalTime`), while the app and the
database keep storing **UTC**; the relative labels ("just now", "20h ago") are untouched; and the
surfaces that were *already* MYT (the PL queue's Requested/Reviewed stamps) did not regress.

| | |
|---|---|
| Change under test | commit `67413e3` — `$absTime` / `$tipAbs` delegate to `LocalTime`; the mapper keeps `DISPLAY_TZ = LocalTime::TZ` and delegates both converters |
| Database | `class_replacement_fresh` on **:8000** — **READ-ONLY. Do not mark read, hide, approve, reject or cancel anything.** Every check below is a hover/click-open |
| Login | `/login/staff` — id **5425**, password **Tarumt@2026** (Surayaini, the demo PL; the requester-owner of the rows below). Optional second account: **5516** / same password (Mohd Rahmat — the other PL) |
| Time budget | ~10 minutes |
| If a value looks like the OLD behaviour | restart the server — `scripts/dev-serve.sh 8000` — then retest. This box has CLI OPcache `revalidate_freq=180`, so a stale server can serve the previous bytecode for up to 3 minutes |

---

## 0. Start clean

```bash
cd "/home/philler/Desktop/tarumt/degree/y3s1/BMCS3404 PROJECT I (4)/CRS-fedora-backend"
scripts/dev-serve.sh 8000        # kills the old server, clears compiled views, restarts
```

Expected output ends with `dev server :8000 -> HTTP 302 (opcache revalidate_freq=0)`.

---

## 1. The fixtures you will check against (read-only, already in the demo DB)

| Notification | Recipient | Type | Request | Stored `created_at` (UTC) | **Must display as (MYT)** | State |
|---|---|---|---|---|---|---|
| `#99` | 5425 | submitted | **#26** | `2026-11-02 11:00` | **`2 Nov 2026, 7:00 PM`** | unread, inbox, request **pending** |
| `#150` | 5425 | submitted | **#27** | `2026-11-03 03:10` | **`3 Nov 2026, 11:10 AM`** | unread, inbox, request **pending** |
| `#96` | 5425 | rejected | #24 | `2026-11-02 07:04` | *no absolute time is shown* | **read** → title/body tip |
| `#94` | 5425 | submitted | #24 | `2026-11-02 06:49` | *no chip tooltip* | **hidden shelf** |

Cross-check any other row yourself:

```bash
psql -q -t -U philler -d class_replacement_fresh -c "
select n.id, n.type, n.request_id,
       to_char(n.created_at,'DD Mon YYYY HH12:MI AM') as utc,
       to_char(n.created_at + interval '8 hours','DD Mon YYYY HH12:MI AM') as myt
from notifications n where n.request_id is not null order by n.id;"
```

---

## Flow A — the notifications panel (the bug that was fixed)

1. Log in as **5425** → land on `/my-timetable-ui` (any page has the bell).
2. Click the **bell** (`.notif-btn`, top-right). The panel must open with the rows newest-first.
   Note the two default toggles: **`Unread only` = ON**, **`Active only` = OFF** — so both rows used
   below (`#26`, `#27`, unread) are visible immediately. Leave them alone for Flow A.
3. Find the row titled **`New replacement request (ID #26)`**.
4. **Hover the row** (not the time chip). The multi-line tooltip must read:

   ```
   Staff: 5425 — Surayaini Binti Basri
   Requested at: 2 Nov 2026, 7:00 PM (…)
   Proposed Schedule: Week 7 · 6 Nov 2026 · 14:00–15:00
   Proposed Venue: B010 — Computer Lab B010
   Subject: AMSE2003 (P) — AMSE2003
   ```

   ✅ **PASS** = `7:00 PM`. ❌ `11:00 AM` = still UTC (stale server — restart and retest).
5. **Hover the row's time chip** (right side, shows `Yesterday`). The small tooltip must read
   **`2 Nov, 7:00 PM`** — the short shape, no year.
6. Repeat 3–5 on the row **`New replacement request (ID #27)`**:
   `Requested at: 3 Nov 2026, 11:10 AM (20 hours ago)`, chip `20h ago`, chip tooltip `3 Nov, 11:10 AM`.
7. **No console errors**: open DevTools → Console, reload, repeat steps 2–6 → the log must stay empty.

| # | Check | Expected | ☐ |
|---|---|---|---|
| A1 | Row #26 tooltip | `Requested at: 2 Nov 2026, 7:00 PM (…)` | ☐ |
| A2 | Row #26 chip tooltip | `2 Nov, 7:00 PM` | ☐ |
| A3 | Row #27 tooltip | `Requested at: 3 Nov 2026, 11:10 AM (…)` | ☐ |
| A4 | Row #27 chip tooltip | `3 Nov, 11:10 AM` | ☐ |
| A5 | No `11:00 AM` / `3:10 AM` anywhere in the panel | ✔ | ☐ |
| A6 | No page errors in Console | ✔ | ☐ |

---

## Flow B — things that must NOT have changed (the negative controls)

| # | Step | Expected | ☐ |
|---|---|---|---|
| B1 | Row #26 chip **text** (not tooltip) | `Yesterday` — relative labels are untouched | ☐ |
| B2 | Row #27 chip **text** | `20h ago` (a duration, never a clock) | ☐ |
| B3 | Row ordering | newest first (`#27` above `#26`) — the change never touches sorting | ☐ |
| B4 | The **bell badge** number | unchanged by opening/hovering/refreshing the panel | ☐ |
| B5 | Untick **`Unread only`** → find the read row **`Replacement rejected`** (`AMSE2003 (P) · W14 · rejected by … · Venue unavailable`) | hover shows that title — body text **without** a `Requested at:` line (only live *unread submitted* rows carry the line; a read/decided row is `_display`-less by design) | ☐ |
| B6 | Footer **`Hidden (2)`** → the shelf | hidden rows render; their time chip has **no** tooltip (pre-existing `hidden` variant) and nothing breaks | ☐ |
| B7 | **Back** to the inbox, then the **Active only** toggle and the **type dropdown** | rows filter exactly as before; hovering a surviving row still shows MYT | ☐ |
| B8 | Multi-line tooltip shape | the tip renders on several lines (`.data-tip-tooltip--multi`), not one run-on line | ☐ |

---

## Flow C — surfaces that were already MYT (regression guard for the mapper delegation)

1. Go to **`/request-approval-ui`** (same login 5425). The **Status** filter defaults to **`Pending`**.
2. Find **request #26** — it is in that default view. Read the **`#` / Request ID** column (`#26`) and the
   **Requested Timestamp** column → **`2 Nov 2026, 7:00 PM`** *(it read 7:00 PM before this change too —
   the mapper already converted; this proves the delegation changed nothing)*.
   *The search box matches course code / name / lecturer only — it does not search request ids.*
3. Set the **Status** filter to **`All`** (or `Rejected`) and open **request #24** (`#24` in the ID column,
   *Rejected Requests* card → 1) → the modal must show **Requested At = `2 Nov 2026, 2:49 PM`** and
   **Reviewed At = `2 Nov 2026, 3:04 PM`**.

| # | Check | Expected | ☐ |
|---|---|---|---|
| C1 | PL queue Requested Timestamp (#26) | `2 Nov 2026, 7:00 PM` | ☐ |
| C2 | PL modal #24 Requested At | `2 Nov 2026, 2:49 PM` | ☐ |
| C3 | PL modal #24 Reviewed At | `2 Nov 2026, 3:04 PM` | ☐ |

---

## Flow D — the ONE place deliberately left wrong (do **not** report as a bug)

`/my-request-history-ui` still formats its `reviewedAt` from the raw UTC value: the fix for it was
**rejected on purpose** because the merge lane (`replacement-merge-one-table`, task **T16**) deletes
that page class and its view.

1. Go to **`/my-request-history-ui`** → set the status filter to include **Rejected** (or All).
2. Open **request #24** → the modal must show:
   - **Requested At = `2 Nov 2026, 2:49 PM`** ✅ (this one is MYT — correct)
   - **Reviewed At = `2 Nov 2026, 7:04 AM`** ⚠️ **(expected today; the true MYT decision was 3:04 PM)**
3. Confirm the same modal's timeline "Under Review" step shows that same `7:04 AM` — consistent within the page, wrong only against MYT.

| # | Check | Expected | ☐ |
|---|---|---|---|
| D1 | MRH #24 Requested At | `2 Nov 2026, 2:49 PM` | ☐ |
| D2 | MRH #24 Reviewed At | `2 Nov 2026, 7:04 AM` — **known, documented, goes away with T16** | ☐ |

---

## Flow E — responsive + themes

| # | Step | Expected | ☐ |
|---|---|---|---|
| E1 | DevTools → 390 × 844 → open the panel → hover row #26 | chip tooltip `2 Nov, 7:00 PM`; tooltip stays inside the viewport; no horizontal scroll | ☐ |
| E2 | Toggle dark mode (`.dark` on `<html>` via the theme toggle) → repeat A1/A2 | same values, readable contrast | ☐ |

---

## Flow F — 60-second automated cross-check (no browser)

```bash
# 1) the helper's pins (fast, no DB writes)
DB_DATABASE=class_replacement_testing php artisan test --filter=LocalTimeTest

# 2) the panel's own receipts (read-only on :8000)
npx playwright test tests/notifications-island.spec.ts
```

Expected: **5 tests** pass for the filter run; **10 passed** for the island spec.

---

## Regression signatures — what a failure looks like

| If you see | It means | Do |
|---|---|---|
| `11:00 AM` / `3:10 AM` in the panel | the conversion is not loaded (stale server/OPcache) | `scripts/dev-serve.sh 8000`, retest |
| `3:00 AM` / `11:10 PM` (**+16 h**) | a double conversion (two `setTimezone` calls in the path) | stop, report — do not "fix" by removing one blindly |
| `19:00` / `07:00` (24-hour) in the panel | the format string changed (`g:i A` lost) | stop, report |
| `8 hours ago` on a row you just saw arrive | `$minutesAgo` got a converted instant instead of the stored one | stop, report |
| the *relative* chip text became an absolute time | `$relTime` was replaced instead of `$absTime` | stop, report |
| PL queue stamps shifted by −8 h | the mapper's delegation inverted the direction | stop, report |
| the panel shows **no** `Requested at:` line for an unread pending row | `_display` composition broke (not a clock bug) | report separately |

---

## Sign-off

☐ A1–A6 · ☐ B1–B8 · ☐ C1–C3 · ☐ D1–D2 (expected-wrong) · ☐ E1–E3 · ☐ F

**Cleanup: none.** No database write is part of this flow. If you want a *fresh* stamp end-to-end
(the current wall clock, not a 2-day-old row), do it on the disposable **testing** DB
(`scripts/dev-serve.sh 8030` with `DB_DATABASE=class_replacement_testing`) — never on `:8000`, and
reseed (`migrate:fresh --seed`) afterwards.
