# Design — arrangement-earliest-week-default

## D1 — the boot edit (1, `replacement-arrangement.blade.php` — the
`weekNav.load()` block; live anchors: load **:3507**, pinned bridge
**:3514**, `populateWeekSelect` :3515)

Shipped today:

```js
            /* restore the previously saved week (localStorage), else falls back to week 1 */
            weekNav.load();
            /* BRIDGE PATCH: the pinned occurrence's week wins over the saved
               one — … */
            if (window.pinnedOccurrence) weekNav._currentWeek = window.pinnedOccurrence.week - 1;
```

After (the FROZEN shape — the single collapsed write; the round-1 draft's
redundant filter clause is retracted here so this is the only code sample):

```js
            /* ARRANGEMENT-EARLIEST-WEEK-DEFAULT (user decisions Q2 A + Q3 A,
               2026-10-20): the saved-week probe runs BEFORE load() — load()'s
               one-time legacy migration (F-11) would promote the 'currentWeek'
               key into 'arrangementWeek' during the run, and a post-load probe
               could never tell memoryless from migrated.
               A plain, no-URL, MEMORYLESS landing stands up on the Earliest
               Bookable week — the same truth the page's own "Earliest
               bookable" button uses (firstBookableDay skips Sundays +
               holidays + the lead-time too-soon days). A saved week keeps
               winning (the working-week promise; its entries = user-arrived
               or pinned positions); the pinned bridge above stays first
               (URL > saved > earliest > week 1). SILENT by decision (Q3 A):
               no toast; the selector self-documents. No save call — the
               memoryless state stays memoryless until the user navigates. */
            var hadSavedWeek = false;
            try {
                hadSavedWeek = localStorage.getItem('arrangementWeek') !== null
                    || localStorage.getItem('currentWeek') !== null;
            } catch (e) { hadSavedWeek = false; }

            /* restore the previously saved week (localStorage), else falls back to week 1 */
            weekNav.load();

            /* BRIDGE PATCH: the pinned occurrence's week wins over the saved
               one — … (the shipped comment, unchanged) */
            if (window.pinnedOccurrence) weekNav._currentWeek = window.pinnedOccurrence.week - 1;
            else if (!hadSavedWeek) {
                const eb = firstBookableDay(weekData);
                if (eb) weekNav._currentWeek = eb.week;
            }
```

- `var hadSavedWeek`: reassigned inside the try/catch + declared
  pre-`load()`; `const eb` inside the branch freezes the single-read
  shape. (Round-1 💡2: the earlier "matches the block's style"
  justification was inaccurate — this block is predominantly `const`;
  the real reasons are the two above.)
- The draft's redundant filter clause is RETRACTED (round-1 🟡2):
  `_weekFilter(ebWeek.week)` merely re-tests bookability on a week
  `firstBookableDay` returns bookable by construction, and the
  `_currentWeek === null` ternary was a no-op (the ctor inits to `0`).
  The collapse above is the authoritative shape T1 implements.
- `firstBookableDay` unguarded is safe (verified: the shipped boot
  already calls it unguarded inside the "Earliest bookable" button
  wiring); the `if (eb)` guard = the end-of-semester defence.
- NO `weekNav.save()`/`saveCurrentWeek()` anywhere in the default path —
  the memoryless state stays memoryless (the next plain visit re-lands
  earliest-bookable; an absent arrangementWeek key is the trigger).
- The pinned bridge + `populateWeekSelect(selected: weekNav.currentWeek)`
  + `selectWeek(pinnedIdx)` (:3526) untouched — the selector, the grid
  (`buildTimetable()` :3554 reads the select/nav) and the saved/pinned
  saves keep their exact shipped order.

## D2 — the priority chain (frozen)

| Landing class | Stands on | Source |
|---|---|---|
| Pinned (`?session=…&week=N`) | N | the bridge (untouched :3500/:3519) |
| No pin + saved (`arrangementWeek` or legacy `currentWeek`) | the saved week (filter-resolved if dead) | `weekNav.load()` (untouched) |
| No pin + no saved | **Earliest Bookable** (`firstBookableDay`) | THE NEW DEFAULT |
| No pin + no saved + no bookable week exists | Week 1 (the shipped fall-through) | unreachable in practice (`if (eb)` guard) |
| No pin + a GARBAGE saved value (non-numeric) | week 1 | `load()` ignores NaN; `hadSavedWeek` = true → the default deliberately does NOT fire (there WAS a memory) — round-1 💡4 |

## D3 — the intent nuance (existing contract, named)

A memoryless `?venue=&date=&time=` landing (the C2 family's shape):
the default sets the stand-up; `resolveBookingIntent` may then jump the
grid to the booked date's week when it differs — BY DESIGN (that path
pre-dates this change; its toasts/fallbacks stay this change's
neighbors, not its subjects). The default never runs AGAINST an intent
jump: the jump fires later and wins.

## D4 — blast matrix

| Surrogate | Today | After | Note |
|---|---|---|---|
| Plain + fresh storage | Week 1 | **Earliest Bookable** | THE FIX |
| Plain + saved week | it | it (untouched) | Q2 A |
| Pinned arrival | the pinned week | it | T6/B's receipts hold (`arrangementWeek` = the pinned week e.g. 13) |
| Intent (`venue+date+time`, memoryless) | the intent's jump family | stand-up earlier, then the same jump | late-loaded hygiene only |
| Post-boot subject switch (default-pin) | unchanged | unchanged | the auto-pin/auto-nav family untouched |
| Other pages (venue/my/cohort) | unchanged | unchanged | Q1 page-scoped |

## D5 — verification vehicle

NEW `tests/arrangement-earliest-week-default.spec.ts` (staff-5770
storage state for auth). HARDENING (round-1 🟡3): the storageState may
re-seed localStorage per context if a future auth setup ever visits a
week-saving page, so rows ENFORCE memorylessness with an add-init that
deletes `arrangementWeek` + `currentWeek` — never inherited, always
deterministic:

1. **D-row1 (the default)**: plain `/replacement-arrangement`, fresh
   context → evaluate `firstBookableDay(window.weekData)` → assert
   `#weekSelector`'s value equals that week index; assert the toast bar
   carries no landing message (silent, Q3 A).
2. **D-row2 (the saved week wins)**: land once fresh → evaluate a
   bookable index (`i` where `weekHasBookableSlot(window.weekData, i)`,
   chosen ≠ the default's week) → `arrangementWeek = String(i)` via
   add-init → reload → the selector = `String(i)`. NO hardcoded value
   (round-1 🟡4: a magic '9' could flake if that week ever fails the
   filter and `load()`'s resolve-away snaps forward).
3. **D-row3 (the pin wins memoryless)**: `?session=49&week=14` fresh
   context → the selector = '13' AND `arrangementWeek` = '13' (the
   bridge's designed save; T6/B's receipts re-stated here for a
   self-contained suite).

Regression set: `session-arrival-pin-guard.spec.ts` (3 rows — its
selector/memory asserts are this change's neighbors),
`default-pin.spec.ts` (3), `replace-now.spec.ts`,
`venue-cross-week-handoff.spec.ts`, `venue-intent-modal.spec.ts`
(C2's toast-poll noise = the stash-proven pre-existing family, listed
as noise). Gates: blade-JS only → `php -l` + the Playwright run with
`--grep-invert "TC70"`; phpunit not in the delta (no PHP edits).
Stale-cache ritual + re-auth if needed.

## D6 — skipped batches

Condensed single-round batch review (explore-brief baseline;
proposal/design/tasks reviewed together — the pattern of the last two
reviews, both of which froze on it) — ≤2h single-edit change; `specs/`
skip (the priority chain + the boot anchor map live here in D1–D5).
