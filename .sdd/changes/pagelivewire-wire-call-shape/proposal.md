# Proposal — pagelivewire-wire-call-shape (combined small-bugfix batch)

## Problem (reproduced live, 2026-10-29; investigation report in the chat)

Every page action that routes through the house's `pageLivewire()` resolver
(approve / reject / cancelRequest / cancelRequests / submitBlocks /
cancelClass / cancelClassUndo / intentSessionOptions — the 8 call sites)
produces **"no visible response"**: no modal result, no toast, no wire POST,
no server log entry. Live console receipt:

```
TypeError: pageLivewire(...).call is not a function
```

Two independent API facts make the committed resolver (6be2235) inert in the
current runtime (Livewire 4.3.3):

1. **The name compare is unreliable.** The LIVE DOM snapshot receipt shows
   the islands registering as `NavNotifications` (StudlyCase) while the page
   components register kebab (`venue-timetable`); the committed resolver
   excludes `!== 'nav-notifications'` (lowercase kebab) — which the
   Mechanism-level Finder walk says SHOULD match (`Finder.php:319–352`
   kebab-cases) — an inconsistency between the source walk and the live
   receipt that this lane settles empirically (the T2 :8000 result prints
   the resolver's ACTUAL excluded `c.name`). Whatever the registered form,
   the normalization excludes both spellings.
2. **The raw component has no `.call`.** In Livewire 4 `all()` returns raw
   `Component` objects; the calling API lives on **`component.$wire`**
   (aliases table `call → $call`; `first()`/`find()` return `.$wire`, which
   is exactly why the PRE-hotfix `Livewire.first().call(...)` at least
   reached the server and produced the Oct-28 MethodNotFound 500s).

## The change (one function in `public/js/ui-common.js`)

```js
window.pageLivewire = function () {
    if (!window.Livewire) return null;
    const all = window.Livewire.all ? window.Livewire.all() : [];
    const page = all.find(function (c) {
        const name = String((c.name || (c.snapshot && c.snapshot.memo && c.snapshot.memo.name) || '')).toLowerCase();
        return name !== 'navnotifications' && name !== 'nav-notifications';
    });
    if (!page) return null;
    return page.$wire ? page.$wire : page;   // Livewire 4: the call API lives on $wire
};
```

- Case-insensitive, separator-insensitive island exclusion (both Studien /
  kebab spellings excluded), the resolver's original INTENT preserved.
- Returns the `$wire` proxy — the SAME object shape the pre-hotfix house law
  (`Livewire.first().call(...)`) was built on, so all 8 call sites keep their
  `.call(...)` ergonomics unchanged. NO call-site edits.
- A page with NO non-island component (mock-fallback legacy pages) returns
  `null` instead of an island — the same failure class those pages already
  had (no pageLivewire component exists there), now honest.

## In scope

- The resolver rewrite only. No UI classes/copy, no call-site changes, no
  backend changes.
- Re-validation receipts (the user-approved plan item 2) re-driven via
  Playwright on the disposable testing DB + the :8000 read-only live probe:
  the resolver returns the page component, `.call` is a function, and the
  approve / reject / cancelRequest confirm clicks each emit a wire POST and
  complete their server action.

## Out of scope

- The `#tooltipBookBtn` keyboard-reachability/AT affordance (P2 candidate,
  separate lane).
- Any notification-lane changes (R8/bug-5 is intended behaviour; receipts
  confirm the emitter is healthy).

## Risks

- Returning `$wire` changes the returned object SHAPE: anything that read
  `.name`/`.snapshot` off `pageLivewire()` would now miss them. Grep (the
  design D2 audit) confirms no consumer reads properties other than calling
  `.call(...)`; the 8 call sites all do `pageLivewire().call(...)`.
- The islands' `$wire` exists too (they are full components) — the exclusion
  must stay name-based (it is; shapes are not used to discriminate).

## Success criteria

- The live :8000 probe: `pageLivewire()` returns the page component
  (`venue-timetable`/`request-approval`/…), `typeof .call === 'function'`.
- The venue Arrange click → `intentSessionOptions` wire POST 200 and the
  session-choices modal renders.
- The testing-DB UI drives: approve / reject / cancelRequest confirm clicks
  each emit 200s and change their slot/request state server-side
  (notifications fan-out intact).
- The existing island Playwright receipts stay green; the JS suite gates
  pass; ONE commit, no push.
