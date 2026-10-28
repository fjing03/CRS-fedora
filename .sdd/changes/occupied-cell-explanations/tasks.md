# Tasks — occupied-cell-explanations

## T1. Thread the shared holder map (≤1h)
- [ ] `ReplacementArrangement::mount()`: compute `$holderLabels` once;
      pass into `busyByWeekPayload(..., $holderLabels)` (new param,
      DEFAULTS REMOVED — explicit) and `venueSlotsByWeekPayload(...,
      $holderLabels)`.
- [ ] DELETE the internal call inside `busyByWeekPayload()` at :405
      (`$holderLabels = $this->busyCellHolderLabels(...)`) — the
      threaded parameter replaces it (double-compute guard).
- [ ] `busyCellHolderLabels()` body untouched.
- Gate: `php -l`; phpstan row shapes compile.

## T2. The grid emission (≤1.5h)
- [ ] `venueSlotsByWeekPayload()`: build the per-call view
      `[weekIdx][day:room][] = [startIdx, endIdx, labels[]]` —
      **`explode(':', $key, 3)` LIMIT-3 parse** (room = remainder).
- [ ] Per slot row: containment `$s <= $hi && $hi < $e` over that
      day+room list; `$picked` merge + `array_values(array_unique())`
      across ALL matched entries; emit `[day, startIdx, $status,
      $picked ?: null]` (index 3 appended).
- [ ] `@return` docblock moves to the 4-tuple shape (updated, never
      deleted).
- [ ] Confirmed-empty (`holderLabels null`) rows stay 3-key-equivalent:
      emit `[day, startIdx, status, null]` — index positions stable.

## T3. The Blade conversion (≤1h)
- [ ] In the cellRender's `cellData` arm: build
      `gridHolderMatches` = one pseudo-match `[di, hi, hi+1, label,
      cellData[2] === 1 ? null : 'pending']` per `holderLabels` element;
      attach via the UNCHANGED `renderHolderTip()` (readable →
      `data-tip`+`data-tip-pos="left"`, flat → `aria-label`,
      `tabindex=0`, `cursor=help`) — the engine arm's attribute set.
- [ ] REPLACE the occupied arm's old single-string
      `if (cellData[3])` attach block with the shared converter (the
      3/4 arms' classes stay untouched — statusKind governs the tip
      only).
- [ ] `php -l`; no other arm touched; the holiday/sun arms stay FIRST.

## T4. Regression receipts (≤1.5h)
- [ ] `tests/Feature/ReplacementArrangementTest` additions: the fixture
      width-N span → array-of-blocks on every covered half-hour incl.
      the tail; the room/wrong-room isolation; the null arm; the
      statusKind mapping (1→null, 3/4→'pending' via the payload).
- [ ] Playwright probes (read-only): B010/W7 Thu 14:00–14:30 (AMCS2093
      block + 🏛 tautology tag), Fri 16:00–16:30 (AMIS1003), own Thu
      12:00–13:00 (`You — `), Week 14 `cell-ph` primacy; screenshots +
      DOM snippets. (phpunit asserts the PAYLOAD status codes and label
      arrays; the client statusKind→tip-kind derivation is Playwright-
      only — no unit test for inline JS.)
- [ ] **Payload-size gate**: measure the probe page's `PAGE_DATA` blob;
      >90KB ⇒ enact the dictionary fallback (design D5) before freeze.

## T5. Gates + bookkeeping (≤1h)
- [ ] `composer run lint:check` + `vendor/bin/phpstan analyse
      --memory-limit=1G` + full phpunit (≥265/265).
- [ ] Stale-serve ritual — TWO separate commands, never one string:
      1. `pkill -f "[a]rtisan serve"`
      2. `rm -f storage/framework/views/*.php && php artisan serve --port=8000`
      then restart the :8001 CRS-demo sibling
      (`cd ../CRS-demo && nohup php artisan serve --port=8001 >
      /tmp/opencode/demo-server.log 2>&1 &`).
- [ ] `page-changelogs/replacement-arrangement-changelog.md` entry.
- [ ] ONE conventional commit: `ui: the grid-occupied cells carry the holder tips — ...`
      (no push).
