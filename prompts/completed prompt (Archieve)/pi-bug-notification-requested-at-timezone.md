# Paste-ready prompt — fix the notification "Requested at" 8-hour skew (A + B + C)

> **Approved by the user on 2026-11-02: "I agree, with Recommendation A+B+C."**
> This file is the **implement brief** for that approval. Investigation is closed;
> the evidence below is the record, not an invitation to re-debate.
>
> ## Status: **SHIPPED 2026-11-04** — commit `67413e3` (**A + B1 only**)
>
> | Piece | Outcome |
> |---|---|
> | **A** — the two panel closures | ✅ applied: `$absTime` (`:38–40`) + `$tipAbs` (`:44–46`) delegate to `LocalTime` |
> | **B1** — `LocalTime` + mapper delegation | ✅ applied: `app/Support/LocalTime.php` (new); `DISPLAY_TZ = LocalTime::TZ`; both converters delegate → byte-identical |
> | **B2** — `MyRequestHistory`'s `reviewedAt` | ❌ **rejected by the user 2026-11-04** — that file is retired by `replacement-merge-one-table` (design §2.2 "retires — no forwarder"; **T16** deletes the class **and** its blade, and the merged page has **no** *Reviewed At* row), so the one-line fix is churn on a file already scheduled for deletion. **§3.6 below is NOT applied.** |
> | **C** — `request->submitted_at` for the tip line | ❌ **dropped 2026-11-04** — verified **zero `awaiting` reminder rows in both demo DBs**, and `notifications:remind-pending` is **not scheduled**, so it had no visible effect. **§3.4's `$requestedStamp` and §3.5's `setRelation` are NOT applied.** |
>
> **Receipts** (live `:8000`, demo DB, read-only):
> ```
> row "New replacement request (ID #26)"  data-tip
>   before: Requested at: 2 Nov 2026, 11:00 AM (…)
>   after : Requested at: 2 Nov 2026, 7:00 PM (Yesterday)
>   chip  : "Yesterday"   chip data-tip: "2 Nov, 7:00 PM"
> row (ID #27): Requested at: 3 Nov 2026, 11:10 AM (20 hours ago) · chip "3 Nov, 11:10 AM"
> 390 px: chip "2 Nov, 7:00 PM", panel renders, 0 page errors
> ```
>
> **Gates:** `composer run lint:check` passed · PHPStan 7 → **0** · full suite **325 passed /
> 1729 assertions** (was 320/1723) · `tests/notifications-island.spec.ts` **10/10** on `:8000`.
> New pins live in **`tests/Unit/LocalTimeTest.php`** (5), not `tests/Feature` as §6 proposed —
> `tests/Unit` is the home of the pure-helper tests (`OCCValidatorTest`, `MatrixIntersectionEngineTest`).
> Changelog: **`page-changelogs/notifications-panel-changelog.md`** only; the MRH changelog line in §8
> is N/A with B2.
>
> **Known-wrong until T16 ships (deliberate, documented):** `/my-request-history-ui`'s modal still
> prints the raw UTC `reviewedAt` — request 24 shows *Reviewed At 7:04 AM* while the true MYT
> decision was **3:04 PM**, next to a correct *Requested At 2:49 PM*. `request-approval.blade.php`
> reads `reviewedAt` from `toRow()` and is **already MYT**, so nothing that survives the merge
> carries the bug.

---

## 0. Task card

| Field | Value |
|---|---|
| **Mode** | `IMPLEMENT` |
| **Task** | Render every user-facing absolute timestamp in the notifications panel in **Asia/Kuala_Lumpur**, via ONE shared helper; fix the history page's `reviewedAt` to use the same helper; source the tooltip's "Requested at" from the request's `submitted_at`. |
| **Why** | Request #26's notification tooltip says `2 Nov 2026, 11:00 AM`; the history page says `7:00 PM`. The app clock and DB are UTC; the notifications format raw UTC. |
| **In scope** (as approved) | `app/Support/LocalTime.php` (new), `app/Services/ReplacementRequestMapper.php`, `app/Livewire/NavNotifications.php`, `app/Livewire/MyRequestHistory.php`, `resources/views/livewire/nav-notifications.blade.php`, `resources/views/partials/notif-row.blade.php`, one new test, two changelogs. |
| **As shipped** (`67413e3`) | `app/Support/LocalTime.php` (new) · `app/Services/ReplacementRequestMapper.php` · `resources/views/livewire/nav-notifications.blade.php` · `tests/Unit/LocalTimeTest.php` (new) · `page-changelogs/notifications-panel-changelog.md`. **Not touched:** `NavNotifications.php`, `MyRequestHistory.php`, `notif-row.blade.php`. |
| **Out of scope** | Any other page, any DB write, `config/app.php` timezone, JS (`ui-common.js`), layout/CSS, unrelated bugs. |
| **SDD** | **waived** — display-only formatting fix, ≤6 code files, no schema/API/behaviour change, fix approved by the user in conversation. Record `SDD-waived w/ user approval` in the commit message and the changelog (template §3b "single-file fix / mechanical follow-up" clause). |
| **DB impact** | **none** — never rewrite stored timestamps. |
| **Done when** (as approved) | Request #26's tooltip reads `2 Nov 2026, 7:00 PM`; the row hover reads `2 Nov, 7:00 PM`; the history modal shows Requested At **and** Reviewed At in MYT; all existing tests green; one new test pins the conversion. |
| **Done when** (as shipped) | ✅ tooltip `7:00 PM` · ✅ hover `2 Nov, 7:00 PM` · ⛔ history modal **not** fixed (B2 rejected — dies with T16) · ✅ suite green · ✅ 5 pins. |

## 1. Read first

- `app/Services/ReplacementRequestMapper.php:36–57` — the existing decision and
  the two converters you are about to generalise.
- `app/Support/ReasonLabel.php` — the house style for a tiny `final` helper
  (namespace `App\Support`, static method, docblock).
- `app/Livewire/NavNotifications.php:390–520` — `resolveRequests()` and the
  `_display` composer (where the requests are already loaded and keyed by id).
- `resources/views/livewire/nav-notifications.blade.php:26–45` — the time closures.
- `resources/views/partials/notif-row.blade.php:25–35, 60–80, 119` — the row, the
  tip lines, the hover time.
- `prompts/for-ai-to-read/AGENTS.md` (standing rules) + `my-coding-rules.md` +
  `CodingMAIN.md` §10 (conventions).
- `prompts/general&reusable prompt references/PI-PROMPT-TEMPLATE.md` §5 (changelog), §7 (gates), §10 (archive ask).

## 2. The evidence you are fixing (short form)

```
config/app.php:81  'timezone' => 'UTC'
DB (request 26)    submitted_at = 2026-11-02 11:00:12            ← UTC instant
                   = 2026-11-02 19:00:12 Asia/Kuala_Lumpur       ← what the user saw
notifications 99/100 created_at = 2026-11-02 11:00:12           ← same instant
```

- History page converts (`MyRequestHistory.php:81` → `requestedAtDisplay()`) → `7:00 PM` ✅
- Notifications do not (`nav-notifications.blade.php:34–44` → `->format(...)`) → `11:00 AM` ❌
- The relative label (`1 minute ago`) is correct — UTC vs UTC. Do not touch it.

## 3. Approved design (A + B + C)

**A — convert in the notification closures.**
**B — one shared helper owns the timezone; the mapper delegates to it; the
history page's `reviewedAt` joins it.**
**C — "Requested at" reads `request->submitted_at` (fallback `created_at`).**

### 3.1 New file — `app/Support/LocalTime.php`

```php
<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * THE user-facing clock (user decision 2026-10-19, generalised 2026-11-02):
 * the app/db clock is UTC while the user's clock is Asia/Kuala_Lumpur (+08).
 * Every user-facing ABSOLUTE stamp converts here; relative labels stay as-is.
 */
final class LocalTime
{
    public const TZ = 'Asia/Kuala_Lumpur';

    /** The MYT-form stamp — byte-shape identical with the previous UTC form. */
    public static function stamp(?CarbonInterface $stamp, string $format): ?string
    {
        return $stamp === null
            ? null
            : $stamp->copy()->setTimezone(self::TZ)->format($format);
    }
}
```

### 3.2 `app/Services/ReplacementRequestMapper.php`

- Keep `public const DISPLAY_TZ = LocalTime::TZ;` (constant expression; any
  external reader keeps working, and the mapper stops owning a second truth).
- `stampDisplay()` → `return LocalTime::stamp($stamp, $format);`
- `requestedAtDisplay()` → `return (string) LocalTime::stamp($stamp, 'Y-m-d\TH:i:s');`
  (the parameter is non-nullable, so the cast cannot hide a null; keep the
  docblock's "byte-shape identical" note).
- Add `use App\Support\LocalTime;`.

### 3.3 `resources/views/livewire/nav-notifications.blade.php` (A)

Replace only the bodies of the two closures (lines **34–44**); their comments
must be updated to say "stored instant → the user's clock".

```php
$absTime = function ($at): string {
    return (string) \App\Support\LocalTime::stamp($at, 'j M, g:i A');
};
// ...
$tipAbs = function ($at): string {
    return (string) \App\Support\LocalTime::stamp($at, 'j M Y, g:i A');
};
```

Do **not** touch `$relTime`, `$relLong`, `$minutesAgo` — they are relative and
already correct.

### 3.4 `resources/views/partials/notif-row.blade.php` (C)

**NOT APPLIED — C was dropped 2026-11-04** (no `awaiting` reminder rows exist in either demo DB and
`notifications:remind-pending` is not scheduled, so it changed nothing visible). The recipe below is
kept for the day that command is scheduled.

At the top of the partial (next to `$m = $minutesAgo($n->created_at);`, line ~29):

```php
/* C: "Requested at" names the REQUEST's stamp, not the notification's.
   Equal for a first notification; they diverge on a re-notification. */
$requestedStamp = $n->request?->submitted_at ?? $n->created_at;
```

Then use `$requestedStamp` **only** in the two "Requested at:" tip lines
(lines **73** and **76**), for both the absolute and the relative part:

```php
'Requested at: '.$tipAbs($requestedStamp).' ('.$relLong($requestedStamp).')'
```

**Keep `$m = $minutesAgo($n->created_at)` and the hover at line 119 on
`$n->created_at`.** Rationale: the row's age and the hover chip answer "when did
this notification arrive"; an approval issued today for a request from yesterday
must still read "just now". Only the labelled **Requested at** line switches.

### 3.5 `app/Livewire/NavNotifications.php` (C, no N+1) — **NOT APPLIED (C dropped 2026-11-04)**

`resolveRequests()` already loads the requests keyed by id (`$requests`) but does
not attach the relation. Attach it before the `foreach ($rows as $row)` loop
(line **445**), so `$n->request` never lazy-loads on the 10 s poll:

```php
$rows->each(static function (Notification $n) use ($requests): void {
    if ($n->request_id !== null) {
        $n->setRelation('request', $requests->get((int) $n->request_id));
    }
});
```

This is additive and changes no payload key; `_display` stays untouched.

### 3.6 `app/Livewire/MyRequestHistory.php` (B) — **NOT APPLIED (B2 rejected 2026-11-04; T16 retires the file)**

Line **82** currently formats `decided_at` raw — that is the "Reviewed At 11:00 AM
vs Requested At 7:00 PM" inconsistency on the same modal:

```php
'reviewedAt' => \App\Support\LocalTime::stamp($r->decided_at, 'Y-m-d\TH:i:s'),
```

(returns `null` for a pending row, exactly like the ternary it replaces).

## 4. What NOT to do

- **Do not** rewrite DB rows, and **do not** change `config/app.php`'s
  `'timezone' => 'UTC'` or add `APP_TIMEZONE`. That would shift `now()`, every
  stored row, the semester maths and the whole test suite at once.
- **Do not** hardcode `19:00` / Request #26, and **do not** use `->addHours(8)`
  or any arithmetic: `setTimezone()` is the only correct tool.
- **Do not** change the relative labels, the sort order, the 10 s poll, the
  badge/pill counts, or the `_display` payload shape.
- **Do not** convert "Schedule"/"Venue" fact lines — they are timetable data,
  not instants.
- **Do not** touch `ui-common.js` (`DateHelper.formatDateTime` is a pure string
  reformatter; the PHP side must hand it MYT strings).

## 5. Anchors (verified 2026-11-02, source HEAD `de7d00c` + local WIP)

Re-grep before editing; this page is under active parallel work.

| Anchor | Line |
|---|---|
| `$absTime` / `$tipAbs` closures | `nav-notifications.blade.php:34–44` |
| `$m = $minutesAgo(...)` | `notif-row.blade.php:29` |
| tip line 2 (submitted branch) | `notif-row.blade.php:73`, `:76` |
| hover absolute time | `notif-row.blade.php:119` |
| `resolveRequests()` loop | `NavNotifications.php:445` |
| `reviewedAt` | `MyRequestHistory.php:82` |
| mapper converters | `ReplacementRequestMapper.php:43–57` |
| app timezone | `config/app.php:81` |

## 6. Tests

- **New** `tests/Unit/LocalTimeTest.php` — **shipped there, not in `Feature`** (a pure helper with no
  DB/app dependency; `tests/Unit` is where `OCCValidatorTest` / `MatrixIntersectionEngineTest` live).
  Five pins were shipped: the conversion, the short panel shape, `null → null`, the caller's instant
  is never mutated, and `ReplacementRequestMapper::DISPLAY_TZ === LocalTime::TZ`:
  - `LocalTime::stamp(Carbon::parse('2026-11-02 11:00:12', 'UTC'), 'j M Y, g:i A')`
    === `'2 Nov 2026, 7:00 PM'`;
  - `null → null`;
  - the format string is respected (a second assert with `'j M, g:i A'`).
  - Optional: assert `ReplacementRequestMapper::DISPLAY_TZ === LocalTime::TZ`.
- **Existing assertions are format-only** and must stay green:
  `tests/Feature/NotificationsBridgeTest.php:240` asserts the `Requested at: `
  prefix; `tests/notifications-island.spec.ts:163` matches the shape
  `\d{1,2} [A-Z][a-z]{2} \d{4}, \d{1,2}:\d{2} (AM|PM)`. Neither pins `11:00`.
  If either fails, **stop and report** — it means someone else changed the shape.
- Do not weaken any existing assertion.

## 7. Gates + live receipts

1. `composer run lint:check`
2. `vendor/bin/phpstan analyse --memory-limit=1G` → 0 errors
3. `php artisan test` → quote the real count; expect the current green suite
   (the last known full run was 292 tests; re-measure, don't quote from memory).
4. Stale-cache ritual — **two separate commands, never one string**:
   `pkill -f "[a]rtisan serve" || true`
   then `rm -f storage/framework/views/*.php && php artisan serve --port=8000 &`
5. Live check on `:8000` (lecturer **5770** or **5425**):
   - notifications panel → Request #26 tooltip: `2 Nov 2026, 7:00 PM` (not 11:00 AM),
     relative tail unchanged;
   - hover the row's time chip → `2 Nov, 7:00 PM`;
   - ~~history page → detail modal: Requested At and Reviewed At both MYT~~ — **N/A (B2 rejected)**;
     that modal keeps the raw UTC `reviewedAt` until T16 deletes the page;
   - a freshly created notification shows the current MYT wall clock;
   - approved/rejected/cancelled reminders agree;
   - dark + light, 390 px, no console errors; screenshots into `/tmp/opencode/`.

## 8. Changelog (create-or-update rule)

- `page-changelogs/notifications-panel-changelog.md` — the main entry: the root
  cause (UTC stored, raw format), the shared `LocalTime` helper, the closure fix,
  the `request->submitted_at` source, and the receipts.
- ~~`page-changelogs/my-request-history-changelog.md`~~ — **N/A**, dropped with B2 (that page never changed).
- The entry notes `SDD-waived w/ user approval`.

## 9. Commit

```
fix: the notification "Requested at" follows the user's clock — one LocalTime helper (Asia/Kuala_Lumpur) owns the conversion, the mapper delegates to it, the notification tooltip + hover stop printing UTC, MyRequestHistory's reviewedAt stops disagreeing with its requestedAt, and the Requested-at line reads the request's submitted_at (A+B+C, SDD-waived)
```

Stage **only**: the 6 code files + the new test + the two changelogs.
**NEVER push.** After the fix, the Viva copy (`CRS-demo`, `:8001`) can
fast-forward — no migration, no DB work.

## 10. Close-out

- Report with the §9 template: files+lines, receipts (test counts, the exact
  before/after strings), commits, "nothing pushed", SDD-waived reason.
- Then ask: *"Task done. Do you want me to archive this prompt into
  `prompts/completed prompt (Archieve)/`?"* — move it only on a "yes"
  (`PI-PROMPT-TEMPLATE.md` §10).

## 11. Stop and ask

- If a test pins the UTC value (not just the shape), stop — the test encodes the
  bug and needs a decision, not a silent edit.
- If `$n->request` cannot be attached without touching `_display`, stop and
  report: C is optional relative to A+B and must not change the payload shape.
- If any mapped timezone or locale assumption fails (e.g. a `+08` offset is not
  constant), stop — `Asia/Kuala_Lumpur` is the user's recorded decision.
