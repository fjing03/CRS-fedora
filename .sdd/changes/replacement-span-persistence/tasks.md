# Tasks

- [x] T1 additive migration `replacement_span` (nullable smallint) + model property/fillable/cast
- [x] T2 `SubmitReplacementRequest` writes the span
- [x] T3 `spanFromAnchor($anchor, $span)` + `span()` + `ReplacementHome` fallback pass the value
- [x] T4 `crs:backfill-replacement-span` (dry run / `--commit` / `--force` for demo DBs)
- [x] T5 `TestingFixtureSeeder` carries spans (requests 24/25/26/10)
- [x] T6 `ReplacementSpanTest` + the legacy leg in `MyReplacementsPayloadTest`
- [x] T7 gates: pint, phpstan, targeted suites, full `php artisan test`
- [ ] T8 demo-DB migrate + backfill (needs the user's word + snapshot + receipt)
