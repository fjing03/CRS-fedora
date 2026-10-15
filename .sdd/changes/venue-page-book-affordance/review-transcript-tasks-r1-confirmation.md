All artifacts read; independent spot-checks of the cited sources complete. Here is the review.

## 🔍 Review Report: venue-page-book-affordance

**This round:** tasks.md is the only artifact under review. proposal.md is FROZEN (4 rounds) and design.md is FROZEN (round-1 real + round-2 independent confirmation + round-2-confirmation-B with declarative amendments). tasks.md's Tasks-Round-1 entry is an implementer self-review, not an sdd-reviewer round, and is treated only as a claim to be verified.

### ✅ Amendments landed correctly (verified in source, not prose)

1. **Design D5 disposition-everything rule + declarative extensions — present and consistent.** D5 step 2 carries "every baseline-red template-era test gets a pre-decided disposition, never improvised under gate pressure" with the two provable-today clusters (TC12/TC13 `#favStar` → retire, "NEVER modify the page to satisfy a test"; TC39–TC44 → re-point `#eventModal`→`#classModal`, retire unsatisfiable assertions). T10 mirrors it exactly.
2. **#favStar truly exists nowhere in the live blade** — grep over `resources/views/livewire/venue-timetable.blade.php` returns zero matches; the blade's comment at ~:56–62 documents it: *"fav-star ☆ stays dropped (documented divergence — mock localStorage), the capacity span stands where fjing's fav-btn sits."* TC12 (:124) / TC13 (:128) reference `#favStar` exclusively.
3. **Live modal id + zero cancel-class machinery — verified.** Grep finds only `classModal` (:154, :166, :294) via the shared `openClassModal` partial; zero `eventModal` / `cancelClass` / `data-cancel-class-btn` / `modal-footer` content anywhere in the live blade. TC70's locator chain at spec `:709/:711/:715` contradicts today's page exactly as design D5 describes (dies at :709 before the CancelClass write).
4. **T10 widened** to the full baseline-red audit ("Disposition **every** baseline-red test from the T2 record") ✓.
5. **T5 frozen toast expression** — T5 keeps `'A booking needs ' + (BOOK_SPAN * 30) + ' minutes — not enough time left in the day.'` (= 120 minutes, byte-faithful per :1048) ✓.
6. **T8 is a plain instruction** — editorial self-negotiation removed; retirement range `:690–730` matches the file (TC70 `:690` → describe-block close `:730`) ✓.

### ✅ Tasks vs frozen artifacts

- **D0–D7 all map to ≥1 task:** D0/D1 → T3/T4/T5 (incl. the clock bridge at the correct position, next to the existing real-data lines at :111–112 — verified those lines are `MockData.semester`/`MockData.holidays` immediately inside the `DOMContentLoaded` closure); D2 → T3/T5; D3 → T6; D4 → T5 + T11.3 end-to-end re-assertion; D5 → T1/T2/T7–T12; D6 → T4's optional `focusedCell = null` hardening (the self-review's 🔴 fix is really there); D7 → T1 (`.gitignore`, `auth.setup.ts`, `playwright.config.ts`), T13, T14, T15.
- **SC1–SC7 all covered**: SC1 → T3/T4/T5/T6 + T11.2/T11.5; SC2 → T5 + T11.3; SC3 → T4 (too-soon/no-fit read-only) + T5 (toast) + T11.4/T11.6; SC4 → T6; SC5 → T12 gates + T13 stale-cache canary incl. the declared real-week-open check; SC6 → T8/T9/T10 + T12 true full suite; SC7 → T14 with the user-decision table.
- **Ordering is safe:** T2 baseline pinned to the pre-change blade with an explicit "must run before T4" (the contamination hazard); T8 (TC70 retirement) precedes T10 — the first unfiltered full-file run — and T12, the full-suite gate. The ⛔ TC70 guard banner sits at tasks.md top and now correctly cites T8 (the round-2 confirmation 🟡 fixed the earlier "T9" slip). Every pre-T8 venue-timetable run in the task list (T1's verify run targets `type-labels.spec.ts` only — no TC70; T2 uses `--grep-invert "TC70"`).
- **Limiter math correct:** 1 login per run via the setup project (storageState reused within the run — venue-timetable + venue-book never log in per test); venue-db's 5 `loginAsStaff` call sites confirmed by grep (default 5425 used 3×, 4288, 5652 — each ≤3 per file), all inside the Fortify 5/min budget since per-file totals are ≤3 per staff ID. T13's manual re-login is outside Playwright.
- **Spot-checked task line-cites — all substantively accurate:** blade `.cell-empty` tint rules (T3, cites :16–22; block actually spans ~:14–20 — trivial drift, intent unambiguous); legend tip (~:79) and Slice-B hint line (`:102` — exact) in the blade's legend array / hint `<p>`; `cellRender` free branch currently painting `.cell-empty` inside the `DOMContentLoaded` closure :110–308; TC46 at `:453–460` with the count-guarded `/Book .+ on .+, .+ at .+/` regex ✓; TC49–TC51 at `:483–503` asserting absent `#bookingBanner`/`#errorBanner` ✓; TC39–TC44 at `:371–424` asserting `#eventModal` ✓; TC12/TC13 (`:123`/`:127` — design cites ":140", which is actually TC14's line; cosmetic); `tests/venue-db.spec.ts:88` = exactly `await expect(mondayRow.locator('.cell-empty')).toHaveCount(0);` ✓; `playwright.config.ts` has a single chromium project, no setup project, no dependencies — T1's additions land cleanly ✓.

### 🟡 Should Fix (non-blocking, fold into T10 when executing)

- **TC61 (`tests/venue-timetable.spec.ts:593–597`) clicks `#favStar` and is provably red today** — a third test in the exact cluster confirmation-B pre-decided, but T10's explicit lists name only TC12/TC13. It *is* covered procedurally by T10's disposition-everything rule (it will appear in the T2 baseline record and get a decision note), so this does not block — but for consistency with the "pre-decide everything provable from source today" standard, fold it into the TC12/TC13 retire disposition (same grounds: documented divergence, blade ~:56–62, never modify the page to satisfy a test) rather than leaving it to the catch-all. **No changes to frozen artifacts required** — this is an execution-time note for T10.

### 💡 Optional Suggestions

- Design D5's TC12/TC13 cite ":123/:140" — TC13 actually starts at :127 (:140 is TC14). Purely cosmetic.
- T10's disposition load could approach the 2h ceiling once TC61, the six TC39–44 items and ~9 template-era reds land together; if it overshoots at execution, split into "re-points" (mechanical) and "retirements" (decision notes) — the task as written permits either.

### ⚖️ Review Verdict

Coverage, ordering, sizing, limiter math, and all declarative amendments check out against the frozen proposal and design, and every spot-checked line-cite was substantively correct. The single 🟡 is handled procedurally by T10's own widened rule and needs no artifact change.

**VERDICT: CONFIRMED — no outstanding 🔴**

tasks.md is frozen; `venue-page-book-affordance` is cleared for `/sdd-apply`.
