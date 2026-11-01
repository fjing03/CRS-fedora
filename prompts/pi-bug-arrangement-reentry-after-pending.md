# Paste-ready prompt for the PI session — BUG: arrangement page re-entry while a request is Pending

> Generated 2026-10-31 (main session). Self-contained — paste the whole block into PI as-is.

---

# Task: Block arrangement-page entry for an occurrence that already has an ACTIVE (pending/approved) request

**Reported by user (2026-10-31).** Entry-guard bug with a bfcache second layer. Display +
guard only — no DB changes, no engine changes.

## Repro (user's exact scenario)

1. Login **5770** (own class, e.g. AMCS2093 (P) session 49, Week 14, Fri Dec 25 11:00–12:00 @ B011).
2. Open `http://127.0.0.1:8000/replacement-arrangement?session=49&week=14&from=my-timetable`
   → "Conflict Schedule" pinned panel renders; pick a slot; **submit** → request becomes
   **Pending** (waiting for PL); page redirects to `/my-request-history-ui` after the beat
   (blade ~:2246).
3. Click the **browser Back button** → the same arrangement URL renders again with the
   Conflict Schedule panel — **as if no request existed**. A hard F5 / re-pasted URL does the
   same (server layer).

**Expected (user's rule):** the system must PREVENT entry — the occurrence's slot must be
checked against the Proposed Classes. **ONLY allow unproposed + self/own classes.**
"Self/own" is already enforced server-side; the **"unproposed" half is missing**.

## Root cause (verified today — fix both layers)

**Layer 1 — server (the real hole):**
`app/Livewire/ReplacementArrangement.php`
- `pinnedSessionPayload()` :197–223 — resolves `?session=&week=` and checks ONLY
  `where('lecturer_id', auth()->id())` (:205). It never checks whether session+week is
  already under an active request, so the pin boots (`'pinnedSession'` mount payload :131 →
  client `window.pinnedOccurrence` ~:4086–4092 → Conflict Schedule panel ~:2791) regardless.
- The D1 full-block reservation :302–318 already defines "handled": a
  `ReplacementRequest` with `status IN ('pending','approved')` keyed by
  `class_session_id.':'+week_number` — it filters such occurrences out of the conflicted
  **grid**, but the **pinned panel bypasses it**. The guard must reuse this exact
  "active" definition (parity — rejected/withdrawn/cancelled requests do NOT block).

**Layer 2 — bfcache:**
`resources/views/livewire/replacement-arrangement.blade.php`
- The `pageshow` handler :2390 (`if (!e.persisted) …`) restores the pre-submit DOM on
  back-button (stale Conflict Schedule + stale selections). After the Layer-1 guard exists,
  a bfcache restore would still show the stale state — revalidate on `e.persisted` too.

## Fix spec

### A) Server guard in `mount()` (must cover EVERY entry path — typed URL, my-timetable link, notification deep-link, F5)

In `pinnedSessionPayload()` (or right after it in `mount()`), once session+week resolve as
the viewer's OWN class:
- If a `ReplacementRequest` exists for `class_session_id = session AND week_number = week`
  with `status IN ('pending','approved')` → **do not render the arrangement UI for this
  pin**. Redirect out + honest toast.
- If no such request → behave exactly as today.
- No `?session=` param → page loads as today (the grid is already D1-filtered).
- Verify one thing while you're in there: a submitted request's slots CAN span weeks —
  confirm whether the request's own `week_number` always equals the pinned week (the D1
  key assumes so). If a pending request can cover the pinned week via a slot while the
  request row's `week_number` differs, the guard must ALSO match
  `ReplacementRequestSlot` weeks for that session. Report what you find either way.

**Landing after the block:** redirect to the `from` source when known
(`from=my-timetable` → `/my-timetable`, `from=replacement-home` → `/replacement-home-ui`,
default `/replacement-home-ui`), with a toast naming the reason and linking to the
request, e.g.:

> This class already has a <span class="warn-keyword">pending</span> replacement request —
> track or withdraw it in **My Request History**.  (action → `/my-request-history-ui`)

Use the house toast/flash mechanism already used for cross-page messages; wording in house
honest-toast style. An **approved** request blocks the same way (message may say
"approved replacement" — the point: the occurrence is handled, management lives in My
Request History).

### B) bfcache revalidation in the `pageshow` handler (blade :2390)

On `e.persisted`, the restored DOM must not survive if the occurrence is now handled.
Two acceptable approaches — pick the one that fits house patterns best:
1. **Targeted:** Livewire-call a small `revalidateEntry` action that runs the same A)
   check (keyed off the URL's session/week, which rides along) and redirects when blocked;
   non-blocked restores stay as-is.  ← preferred (keeps bfcache comfort for the normal
   back-and-forth)
2. **Blanket:** `allowUnload = true` (the exact bypass pattern at :2280 / :2321 / :2342)
   then `location.reload()` on every `e.persisted` — server guard re-runs on the fresh
   load. Only choose this if (1) turns out messy; it costs the restored-selection comfort
   for ALL states.

### C) The withdrawal loop must work (acceptance, no code expected)

After the guard lands, withdrawing/rejecting the pending request in My Request History
makes the occurrence **unproposed** again → the arrangement URL must work normally once
more. Verify this end-to-end.

## NON-goals

- No change to the D1 grid filtering (:302–318), no change to which classes appear in the
  conflicted list.
- No my-timetable card/button changes (if my-timetable still offers an "Arrange" link for a
  handled occurrence, the guard catches it at entry — defense in depth is enough; note it
  in the changelog if you see it).
- No changes to the submit flow, withdrawal flow, or any DB schema.
- Non-blocked back/forward navigation keeps working (bfcache comfort preserved unless you
  chose approach B2, and then only with the user's nod).

## Process

Normal fix lane (changelogs + gates) — no SDD needed unless your fix requires design
decisions beyond this spec. If it does, stop and ask before inventing.

## Land it

1. Changelog: `page-changelogs/replacement-arrangement-changelog.md` — dated entry in
   house table format (entry guard + bfcache revalidation, the unproposed+own rule, the
   withdrawal loop).
2. Gates: `composer run lint:check` ✓ · `vendor/bin/phpstan analyse --memory-limit=1G` → 0
   · `php artisan test` full suite green.
3. Stale-cache restart ritual — TWO separate commands, never combined, never
   `pkill -9 php`: first `pkill -f "[a]rtisan serve" || true`, then
   `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`.
   NOTE: a demo server may run on :8001 from `../CRS-demo` — if your kill took it down,
   restart it: `cd ../CRS-demo && nohup php artisan serve --port=8001 > /tmp/opencode/demo-server.log 2>&1 &`.
4. Verify live (login 5770 `Tarumt@2026`, cache-bust `&_='+Date.now()`):
   - Fresh repro: arrange ANY own conflicted occurrence → submit → **Back** → expect
     **redirect + toast**, NOT the Conflict Schedule panel. Also F5 / re-paste the same
     URL → blocked.
   - Same URL for an occurrence with only a **rejected/withdrawn** request → page loads
     normally (unproposed again).
   - `?session=` of ANOTHER lecturer's class → still nulled as today (ownership
     no-regression).
   - Back-and-forth (arrangement → my-timetable → Back) on a NON-submitted occurrence →
     behaves per your B-approach choice (restore, or clean reload) without errors.
5. Commit: `fix: arrangement entry guard — block re-entry for occurrences under an active request (server + bfcache layers)`
   Stage ONLY your files. Push ONLY on the user's explicit word.
