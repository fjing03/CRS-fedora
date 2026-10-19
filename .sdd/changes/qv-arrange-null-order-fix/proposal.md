# Proposal — qv-arrange-null-order-fix

## Why (with the live repro)
On `/replacement-home-ui`, the **Replacement Details (Quick View) modal's
"Arrange Replacement" button** (`#qvArrangeBtn` at :280) has no visible
action when clicked. Playwright repro (2026-10-19, staff-5770:

```
PAGEERR: Cannot read properties of null (reading 'sessionId')
url unchanged: http://localhost:8000/replacement-home-ui
```

"`Cannot read properties of null (reading 'sessionId')` via
`replacement-home-ui`: clicking arranges NOTHING and no navigation happens."

Root cause: an **order-of-operation bug** in `qvArrange()`
(`replacement-home.blade.php` :592–597):

```js
function qvArrange() {
    if (qvCurrent) {
        hideQuickView();          // ① sets qvCurrent = null
        goToReplacementWith(qvCurrent.sessionId, qvCurrent.week);   // ② reads null.sessionId → throws
    }
}
```

`hideQuickView()` runs BEFORE the arguments are evaluated, so the lead is
nulled before it is read — the throw kills the function before the
navigation line executes.

## In scope
- The fix: capture the lead in a local BEFORE hiding (design D1).
- A regression test row (the page-jump contract asserts the captured
  values, not just absence-of-throw).
- The `replacement-home-changelog.md` dated entry.

## Explicitly NOT in scope
- The modal's own markup/closing behaviour (works).
- `hideQuickView()`'s nulling semantics (used everywhere else) — callers
  capture, the cleaner nulls: the established order in this codebase.
- Any other quick-view row (title concatenation etc. — cosmetic, separate).

## Impact scope
| File | Change |
|---|---|
| `resources/views/livewire/replacement-home.blade.php` | 2-line `qvArrange()` ordering fix (capture-lead-then-hide) |
| `page-changelogs/replacement-home-changelog.md` | dated fix entry |

## Success criteria
- Clicking the button hides the modal and navigates to
  `/replacement-arrangement?session=<row sessionId>&week=<row week>&from=replacement-home`.
- Playwright repro test: open a row's quick view → click → the URL carries
  the expected session/week.
- Gates green (pint/phpstan/phpunit as applicable for a blade+script fix).
