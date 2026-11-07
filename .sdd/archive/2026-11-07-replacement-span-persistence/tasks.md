# Tasks

- [x] T1 additive migration `replacement_span` (nullable smallint) + model property/fillable/cast
- [x] T2 `SubmitReplacementRequest` writes the span
- [x] T3 `spanFromAnchor($anchor, $span)` + `span()` + `ReplacementHome` fallback pass the value
- [x] T4 `crs:backfill-replacement-span` (dry run / `--commit` / `--force` for demo DBs)
- [x] T5 `TestingFixtureSeeder` carries spans (requests 24/25/26/10)
- [x] T6 `ReplacementSpanTest` + the legacy leg in `MyReplacementsPayloadTest`
- [x] T7 gates: pint, phpstan, targeted suites, full `php artisan test`
- [x] T9 the third reader: `NavNotifications`' `$anchorSpan` closure was a SECOND copy of the
      anchor shape (`count = 1`) — the decision tip told a 2-hour rejection `11:00 AM ~ 11:30 AM`.
      It now delegates to `spanFromAnchor($slot, $replacement_span)` (request #38 / notif #221).
- [x] T8 demo-DB migrate + backfill (needs the user's word + snapshot + receipt)
