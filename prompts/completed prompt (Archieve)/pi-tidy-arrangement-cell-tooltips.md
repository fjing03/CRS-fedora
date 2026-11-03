# Paste-ready prompt for the PI session — tidy the arrangement-page cell tooltips

> Generated 2026-10-27 (main session). Everything below is self-contained — paste the
> whole block into the PI session as-is.

---

# Task: Tidy the occupied/pending cell tooltips on the arrangement page
# (conflict-state header + one block per class + per-block CONFLICT TAGS)

**Requested by user (2026-10-27).** On `/replacement-arrangement`, the venue-grid
occupied cells currently render ONE wall-of-text tooltip with multiple sessions
glued by ` | ` — e.g.:

  data-tip="You — BMSE3153(P) · B009 · 13:00-14:00 · Surayaini Binti Basri (5425) ·
  cohorts: RSD3(S1)G2, RSD3(S1)G3 | AMIT2034(L) · B010 · 13:30-15:30 · Daniel Royd
  Michael (5652) · cohorts: DSF2(S1)G1"

Reproduce: login as **5425** (Surayaini, /login/staff, `Tarumt@2026`) and open
`/replacement-arrangement?session=40&week=5&from=replacement-home` — Tue 13:30 cell.

## The 3-layer chain (verified today)

1. `app/Livewire/ReplacementArrangement.php` — `busyByWeekPayload()` (~:392) and the
   holder-text builder (~:471 comment "the viewer's own classes prefix 'You — '",
   `$cohortTexts` ~:511). Each busy tuple stays EXACTLY `[di, startHi, endHi, holderText, statusKind]`
   (statusKind = 'pending' | null) — **only the TEXT of key[3] changes format; tuple shape is frozen.**
2. `resources/views/livewire/replacement-arrangement.blade.php` — `attachHolder()` (~:1710):
   `engineMatches.map(b => b[3]).filter(Boolean).join(' | ')` and the
   `(…b[4] === 'pending' ? 'Pending — ' : 'Occupied by ')` aria-prefix.
   Also the older `cellData[3]` tip branch (~:1735) — give it the same renderer treatment.
3. `public/css/theme.css:3719` — `.data-tip-tooltip { white-space: nowrap; }` (single-line only).

## Target format (user-approved 2026-10-27, exact — WITH per-block conflict tags)

**Occupied cell (red):**
```
⚠ Occupied — 2 classes in the way

Conflict: 🏛 Venue · 👥 Cohorts · 🧑 Staff (you)
B009 · BMSE3153 (P) · 13:00–14:00
You — Surayaini Binti Basri (5425)
RSD3(S1)G2, RSD3(S1)G3

Conflict: 👥 Cohorts
B010 · AMIT2034 (L) · 13:30–15:30
Daniel Royd Michael (5652)
DSF2(S1)G1
```
**All-pending cell (amber):**
```
⏳ Pending make-up — awaiting approval

Conflict: 🏛 Venue · 👥 Cohorts
B107 · AMCS2093 (T) · 09:00–10:00
En. Lim Jia Zheng (5770)
DSF1(S1)G1
```
**Single-class cell:** same shape, header `⚠ Occupied — 1 class in the way`.

Rules:
- Header (JS-side, from engineMatches): all-pending → the ⏳ header; any non-pending →
  the ⚠ Occupied header with N = number of blocks (existing SAFE rank unchanged).
- Per block: the **Conflict-tags line is the block's FIRST line**, then the 3 data
  lines. Tags joined ` · `, in fixed order 🏛 Venue → 👥 Cohorts → 🧑 Staff (you);
  only the matching axes render. Fallback when NO axis matches: pending rows show
  `Conflict: ⏳ Pending hold`; otherwise omit the Conflict line entirely.
- **Conflict-tag derivation (CLIENT-side, in `attachHolder()` — no payload change):**
  - 🏛 **Venue** — block's room (first ` · `-token of its data line 1) equals the
    CURRENTLY selected venue (`currentVenue`). Live: re-derives when the user switches
    venue without a reload.
  - 👥 **Cohorts** — block's data line 3 cohort list intersects the target session's
    cohorts (client already has them: `ctxCourse.cohorts`, cf. blade ~:2525).
  - 🧑 **Staff (you)** — block contains the `You — ` ownership prefix (the holder
    builder only adds it for `lecturerBusyRanges` rows = the viewer's own duty).
- Per block data lines (PHP-side holder text, 3 lines, `\n`): `ROOM · CODE(TYPE) · START–END`
  (room FIRST, en-dash) / lecturer line with the existing `You — ` ownership prefix
  / cohorts line WITHOUT the `cohorts: ` label.
- Blocks sorted by room code; blocks joined with `\n\n` (blank line).
- `aria-label` stays ONE flat line (screen readers): keep the existing
  'Pending — ' / 'Occupied by ' prefix; per block append ` — Conflict: <tags>`
  (same words, no emoji), blocks joined with ' / '.
- Theme change is ADDITIVE ONLY: `.data-tip-tooltip--multi { white-space: pre-line; }`
  applied by the data-tip renderer (find it near `ui-common.js:3146` closest('[data-tip]'))
  when the tip string contains '\n'. Every existing single-line tooltip must stay
  byte-for-byte identical — do NOT remove the global `nowrap`.

## Checks before editing

- `grep -rn "cohorts: \|' | '" tests/` — if any test (e.g. ReplacementArrangementTest)
  pins the old holder text or the join, update expectations to the new format.
- The blade comment block "TEMP-HOLDER-LABEL (user, 2026-10-27 … REMOVE AFTER THE FINAL
  VIVA" — keep the feature and that instruction; only the text format changes.
- `grep -n "' | '" resources/views/livewire/venue-timetable.blade.php` — if the venue
  page has the identical holder-join pattern, apply the SAME format there too and note
  it in venue-timetable-changelog.md as well (same meaning, same look).

## NON-goals
- No engine/busy-vector semantics changes — conflict tags are DISPLAY-ONLY, derived
  client-side in the blade; the payload tuples and `busyCellHolderLabels()` keying
  stay untouched. No cell class/color changes; no DB changes;
  no changes to which cells are occupied/pending.
- Do NOT delete the TEMP-HOLDER-LABEL tooltip feature (viva directive).

## Land it
1. Changelog: `page-changelogs/replacement-arrangement-changelog.md` — dated entry in
   house table format (what changed + why + the new format).
2. Gates: `composer run lint:check` ✓ · `vendor/bin/phpstan analyse --memory-limit=1G` → 0
   · `php artisan test` full suite green.
3. Stale-cache restart ritual — TWO separate commands, never one combined string, never
   `pkill -9 php`: first `pkill -f "[a]rtisan serve" || true`, then
   `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`.
   NOTE: a second demo server may be running on :8001 from the `CRS-demo` sibling folder —
   after the pkill, also restart it: `cd ../CRS-demo && nohup php artisan serve --port=8001 > /tmp/opencode/demo-server.log 2>&1 &`.
4. Verify live (cache-bust with `&_='+Date.now()`): as 5425 open the exact URL above —
   Tue 13:30 cell tooltip shows the header + 2 sorted blocks; the pending amber cells
   show the ⏳ header; hover any OTHER tooltip (venue page, event blocks) → unchanged.
5. Commit: `fix(ui): tidy occupied/pending cell tooltips — conflict header + per-block venue/cohort/staff tags`
   Stage ONLY your files. Push ONLY on the user's explicit word.
