# Design — pagelivewire-wire-call-shape

## D1 The resolver (the only code change)

`public/js/ui-common.js:1890–1899` →

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

- The comment block above it (the hotfix's rationale) stays and gains one
  line: the Livewire 4 shape note (`all()` returns raw Components; the name
  is studly; the call API lives on $wire).

## D2 The receipts (the apply proof)

1. **:8000 read-only** (as 5425): per page — the resolver name equals the page
   component and `.call` is `function`; the venue Arrange click renders the
   intent modal with a 200 `intentSessionOptions` wire POST; the caught-up/AD-2
   island receipts re-run green (no regression from the shape change).
2. **:8030 testing DB** (disposable): fresh seeded; approve / reject /
   cancelRequest confirm clicks each emit wire 200 and advance the request
   state (approved → notifications rows; rejected; cancelled); a fixed-shim
   cancelClass already proven (18 update rows + audit) — repeat via the REAL
   resolver now.
3. Gates: node --check, the full PHPUnit suite (phpstan/pint unaffected — no
   PHP touched), the notifications-island.spec.ts Playwright file.

## D3 The call-site audit (unchanged surfaces)

All 8 consumers call `pageLivewire().call(...)` and read nothing else off the
returned object: `my-request-history.blade.php:647,699`;
The exact audit list: `ui-common.js:4112,4393` (cancelClassUndo/cancelClass),
`my-request-history.blade.php:647,699`, `replacement-arrangement.blade.php:2170`,
`request-approval.blade.php:1074,1112`, `venue-timetable.blade.php:446`.
NONE reads `.name`/`.snapshot`/`.data` — shape change safe (grep receipted;
the reviewer's full-repo grep agrees: 8 sites, nothing missed).

## D4 Why $wire and not `.call` back-compat

Adding a `.call` shim onto the component object would ALSO fix the call sites
in one line — but `$wire` is the framework-sanctioned proxy (its `.call` runs
the FULL action pipeline incl. the request origin metadata used for the wire
telemetry) and matches what the pre-hotfix law returned (`first()` = `$wire`).
Returning `$wire` restores the ORIGINAL house contract; a custom shim would
diverge from the framework API and reintroduce risk. Rejected: the shim.
