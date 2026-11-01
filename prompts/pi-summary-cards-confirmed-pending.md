# Paste-ready prompt for the PI session — Summary Cards: "Confirmed + Pending" split

> Generated 2026-10-31 (main session). Everything below is self-contained — paste the
> whole block into the PI session as-is.

---

## Process: SDD lane (prompt = frozen spec)

- Run this as an SDD change: create `.sdd/changes/summary-cards-confirmed-pending/`
  (sdd.yaml + proposal.md + design.md + tasks.md, model format on the venue-* lanes).
- `proposal.md` = the user decision + scope from THIS prompt (no re-exploration).
- `design.md` MUST record:
  (a) the `{ splitPending: true }` opt-in flag and the "shared-helper default frozen"
      law (computeSummary/myTeachingStats stay byte-identical for my-timetable +
      student-my-timetable unless a caller passes the flag);
  (b) the additive-only theme.css rule (`.sum-pending`, tertiary token);
  (c) a `Promoted to shared` section (the `.sum-pending` span pattern, if shared);
  (d) the two flagged assumptions below + the user's answer at apply time.
- `tasks.md` = checkboxes mirroring the "Land it" section; fill `review-log.md` as you go.
- All gates/verify/commit steps below stay exactly as written.

# Task: Summary-card update on 3 pages — "Subjects" rename + Confirmed+Pending split

**Requested by user (2026-10-31).** Three coordinated changes, all display-only.

## 1) `/replacement-home-ui` — rename "My Courses" → "My Subjects"

`resources/views/livewire/replacement-home.blade.php` ~:253 (the `ui-summary-bar`
card array):
- label `'My Courses'` → `'My Subjects'`
- description `'Number of <span class="info-keyword">your different courses</span> affected by the conflicts.'`
  → `'Number of <span class="info-keyword">your different subjects</span> affected by the conflicts.'`
- The back-face text the user quoted (`.summary-card-front/back` HTML) lives in the
  shared partial `resources/views/partials/ui-summary-bar.blade.php` — check it renders
  from the card array (it does today); change ONLY the array values above.
- **Keep `valueId => 'summaryMyCourses'` and the `card-courses` class unchanged** —
  internal ids, JS at ~:570 writes to them; renaming risks breakage for zero user value.

## 2) `/cohort-timetable` — the 4 non-Conflicts cards show `Confirmed+Pending`

Cards (blade ~:140–153): Total Classes (`sumTotal`), Teaching Hours (`sumHours`),
My Teaching Classes (`sumMyClasses`), My Teaching Hours (`sumMyHours`).
The **Conflicts card (`sumConflict`) stays a plain number — untouched.**

Target values (user's verified example — staff 5770, Week 9):
| Card | Today | Target |
|---|---|---|
| Total Classes | 12 | **11+1** |
| Teaching Hours | 18 | **16+2** |
| My Teaching Classes | 6 | **4+2** |
| My Teaching Hours | 4 | **3+1** |

Format `X+Y`: X = confirmed portion (normal + replacement), Y = pending portion —
i.e. the same underlying counts, split by `status === 'pending'`. Hours split the same
way (pending hours = hours of pending events). **Always render `X+Y`, even when Y=0**
(keeps the story visible; flagged as an assumption — if the user prefers plain `X`
when there is no pending, it is a one-line change in the same helper).

### ⚠ TRAP — `computeSummary` is a SHARED helper

`computeSummary(events, days)` lives in `public/js/ui-common.js` ~:987 and is called by
**cohort-timetable (~:463/:549), my-timetable (~:248) AND student-my-timetable (~:125)**.
`myTeachingStats` (~:978) is shared the same way. You MUST NOT change the default
behavior — my-timetable and the student page keep their plain numbers byte-for-byte.

Do it via an options flag:
```js
function computeSummary(events, days, opts) {
    const splitPending = !!(opts && opts.splitPending);
    // …existing tally unchanged; additionally track pendingHrs and, when
    // splitPending, render `X+Y` strings for sumTotal/sumHours/sumMyClasses/sumMyHours
}
```
- cohort-timetable passes `{ splitPending: true }` at BOTH call sites; the other two
  blades pass nothing → identical output.
- Same split needed inside `myTeachingStats` (it dedupes by head slot — a pending head
  must count as pending; return `{ classes, hours, pendingClasses, pendingHours }`,
  additive keys only, existing keys keep their values).
- Pending hours: an event's `(end - start + 1) * 0.5` attributed to the pending bucket
  when `e.status === 'pending'`.

### Styling of the `+Y` part
Render as `<span class="sum-pending">+Y</span>` inside the value. One additive CSS
rule, token colors only, in `theme.css` (NOT inline, NOT per-page):
` .summary-value .sum-pending { color: var(--color-tertiary); font-weight: 600; } `
(tertiary = the pending family per §10.0 — same-name-same-color canon). The X part
keeps the card's existing color.

### Descriptions (item 2c) — update the 4 cards' `description` strings (~:142–149)
Explain the split, house tone, e.g.:
- Total Classes: `Confirmed (replacement & normal) + <span class="info-keyword">pending</span> classes for <span class="info-keyword">this cohort</span> in the selected week.`
- Teaching Hours: `Confirmed + <span class="info-keyword">pending teaching hours</span> for this cohort in the selected week (each slot = <strong>30 minutes</strong>).`
- My Teaching Classes: `Sessions <span class="info-keyword">taught by you</span> this week — confirmed + <strong>pending</strong>; each class counts separately.`
- My Teaching Hours: `Confirmed + pending hours of <span class="info-keyword">your classes</span> this week (each slot = <strong>30 minutes</strong>).`
- Conflicts card description: unchanged.

## 3) `/venue-timetable-ui?venue=B110` — same concept for its 5 cards

Cards (blade ~:166–179): Total Slots (`sumTotal`), Available (`sumAvailable`),
My Teaching Classes (`sumMyClasses`), My Teaching Hours (`sumMyHours`),
Occupied (`sumOccupied`). All values come from the server payload
`totalsData = @json($totalsByWeek)` (blade ~:237), written by `updateSummary()` (~:831),
built in `app/Livewire/VenueTimetable.php` (`$totalsByWeek` ~:416; occupied accrues
pending/approved make-up rows ~:431/:516; myClasses/myHours ~:564).

The "pending" component here = **slots held by PENDING make-up requests** (the amber
`cell-pending` family the grid already renders). Split accordingly:
- **Occupied** = confirmed occupied **+ pending-held** → `X+Y`
- **Total Slots** = unchanged total, but if the venue's week carries pending-held
  slots show `total` plain (the pending split inside Occupied already tells the story) —
  unless the user says otherwise, keep Total Slots plain.
- **Available** = plain (bookable cells; no pending component — description unchanged
  except wording touch if needed).
- **My Teaching Classes / My Teaching Hours** = your confirmed classes in this venue
  **+ your pending make-ups targeting this venue** → `X+Y`.

Implementation: add `pending` keys to the PHP `$totalsByWeek` rows (e.g.
`occupiedPending`, `myPendingClasses`, `myPendingHours`) — additive keys, existing
keys unchanged — and render the same `<span class="sum-pending">+Y</span>` markup in
`updateSummary()` (reuse the same CSS class from §2). If deriving the my-pending
component server-side is awkward, the client already knows pending via the cell
statuses — either way, do NOT re-derive occupied counts from scratch.

### Descriptions (item 2c) — the 5 cards' `description` strings (~:168–177)
Update the Occupied and My cards to mention the split, e.g.:
- Occupied: `Slots occupied by <span class="info-keyword">scheduled classes</span> — confirmed + <strong>pending</strong> make-up holds.`
- My Teaching Classes: `Sessions <span class="info-keyword">you teach</span> in this venue this week — confirmed + <strong>pending</strong>.`
- My Teaching Hours: `Confirmed + pending hours of <span class="info-keyword">your classes</span> in this venue this week.`
- Total Slots / Available: unchanged.

## NON-goals
- No changes to which events are counted (statuses, dedupe, holiday rules) — only the
  DISPLAY splits. The locked summary law (cancelled excluded from classes/hours,
  included in conflicts) stays exactly as-is.
- my-timetable + student-my-timetable cards: byte-for-byte unchanged (no flag passed).
- replacement-home: label/description text only — no value/JS changes.
- No DB changes; no new dependencies; colors only from theme.css tokens.

## Land it
1. Changelogs (3): `page-changelogs/replacement-home-changelog.md`,
   `cohort-timetable-changelog.md`, `venue-timetable-changelog.md` — dated entries in
   house table format (what/why, the `X+Y` format, the shared-helper flag).
2. Gates: `composer run lint:check` ✓ · `vendor/bin/phpstan analyse --memory-limit=1G` → 0
   · `php artisan test` full suite green (any test pinning summary text/ids must be
   updated — grep `grep -rn "sumTotal\|My Courses" tests/` first).
3. Stale-cache restart ritual — TWO separate commands, never combined, never
   `pkill -9 php`: first `pkill -f "[a]rtisan serve" || true`, then
   `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`.
   NOTE: a demo server may run on :8001 from `../CRS-demo` — restart it too if killed:
   `cd ../CRS-demo && nohup php artisan serve --port=8001 > /tmp/opencode/demo-server.log 2>&1 &`.
4. Verify live (cache-bust `&_='+Date.now()`):
   - `/replacement-home-ui` → card reads "My Subjects".
   - `/cohort-timetable` as **5770**, Week 9 → `11+1` / `16+2` / `4+2` / `3+1`,
     Conflicts plain; hover-back descriptions mention the split.
   - `/my-timetable` as 5770 → summary numbers UNCHANGED from before the change
     (the shared-helper regression check).
   - `/venue-timetable-ui?venue=B110` → Occupied + My cards show the split; Available /
     Total Slots plain.
5. Commit: `feat(ui): summary cards — My Subjects rename + confirmed+pending split (cohort & venue pages)`
   Stage ONLY your files. Push ONLY on the user's explicit word.
