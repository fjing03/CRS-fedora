# Tasks — pagelivewire-wire-call-shape

## T1. The resolver fix (≤0.25h)
- [x] `public/js/ui-common.js:1890–1898` — the D1 rewrite + the shape note
      in the header comment.
- [x] `node --check public/js/ui-common.js`.

## T2. The receipts (≤0.75h)
- [x] :8000 read-only probes (resolver shape per page; the venue Arrange
      click lands `intentSessionOptions` and renders the modal). PRINT the
      resolver's actual excluded (first) component name — settles the
      Studly-vs-kebab naming premise for the record (review fold 1).
- [x] :8030 UI drives: approve / reject / cancelRequest confirm clicks each
      emit wire 200s and complete their server actions (notifications fan-out
      intact); cancelClass through the REAL resolver (the consistency class).
- [x] Re-run `notifications-island.spec.ts` on :8000 (no island regression).

## T3. Gates + bookkeeping (≤0.3h)
- [x] The stale-serve ritual (JS is static but the views may hold compiled
      fragments — cheap and safe).
- [x] php artisan test full suite (no PHP changes expected to move it).
- [x] `page-changelogs/notifications-panel-changelog.md` + commit (ONE,
      conventional, no push).

## T4. SDD bookkeeping (≤0.1h)
- [x] sdd.yaml → applied after green; reviewed round logged; archive only on
      the user's word.
