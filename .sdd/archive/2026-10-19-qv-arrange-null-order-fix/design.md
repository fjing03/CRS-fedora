# Design — qv-arrange-null-order-fix

## D1 — restore the frozen template's capture-then-hide shape
The frozen UI template (`ui-design-templates/replacement-home-UI-design-template.blade.php`
**:588–594**) already carries the correct ordering — the 99.9%-UI rule makes
it authority, the real page's bridge simply transplanted the call without
the capture lines:

```js
function qvArrange() {
    if (qvCurrent) {
        var c = qvCurrent.code; var d = qvCurrent.date; var dur = qvCurrent.duration;   // capture first
        hideQuickView();                                                               // hide second
        goToReplacementWith(c, d, dur);                                                // lead third
    }
}
```

The real page's bridge renamed the args (`sessionId`/`week` — the live rows
carry both, payload built at `app/Livewire/ReplacementHome.php:94–97`)
), so the fix is the template's
SHAPE with the bridge's own names:

```js
function qvArrange() {
    if (qvCurrent) {
        var sid = qvCurrent.sessionId;
        var wk = qvCurrent.week;
        hideQuickView();
        goToReplacementWith(sid, wk);
    }
}
```

- `hideQuickView()`'s nulling stays (used by the overlay, the ✕, Esc, Close —
  all callers capture before nulling under this rule).
- No other quick-view behaviour changes.

## D2 — verification vehicle
The Playwright repro (the proposal's proven steps: row click → quick view →
`#qvArrangeBtn` → the URL must carry ?session=…&week=…&from=). The test row
lives in the new `tests/replacement-home-qv.spec.ts` (dedicated spec — the
page has no lived-in suite; the harness reuses the shared `.auth` storage
states + `baseURL`). Gates: lint/lint-check only (no PHP in the delta);
phpunit untouched.

## D3 — skipped batches (record)
Specs skip (one-line fix; the design+tasks reviewed as one batch above).