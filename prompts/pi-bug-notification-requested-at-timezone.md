# BUG investigation — the notification "Requested at" is 8 hours behind (UTC shown as if it were local)

> **Status: investigation only — no code was changed. Awaiting the user's approval.**
> Reported 2026-11-02 against `replacement-arrangement` → notifications panel →
> Request #26. Prepared for a PI session to act on after approval.

---

## 1. Symptom (verbatim)

| Surface | Shown | Should be |
|---|---|---|
| Notification tooltip (`Requested at:`) | `2 Nov 2026, 11:00 AM (1 minute ago)` | `2 Nov 2026, 7:00 PM` |
| `/my-request-history-ui` → General Info → Request #26 | `2 Nov 2026, 7:00 PM` | (correct) |

The difference is exactly **+08:00** — the `Asia/Kuala_Lumpur` offset. The relative
part (`1 minute ago`) is correct; only the absolute clock is wrong.

## 2. Evidence (read-only checks, 2026-11-02)

**The database holds UTC, and the DB session is not the problem.**

```
replacement_requests.id = 26
  submitted_at            = 2026-11-02 11:00:12          (stored, naive)
  submitted_at AT TIME ZONE 'UTC'                        = 2026-11-02 19:00:12+08
  (… AT TIME ZONE 'UTC') AT TIME ZONE 'Asia/Kuala_Lumpur' = 2026-11-02 19:00:12

notifications.id = 99 / 100 → created_at = 2026-11-02 11:00:12   (same instant)
```

**The app clock is UTC.**

```
config/app.php:81            'timezone' => 'UTC'
php -r date_default_timezone_get()   → UTC
php artisan tinker now()             → 2026-11-02 13:11:53 (UTC)
psql "show timezone"                 → Asia/Kuching   ← the psql client only, irrelevant
```

So `2026-11-02 11:00:12Z` **is** 19:00:12 in Malaysia. Storing UTC is correct;
the defect is purely in one display path.

## 3. Root cause

**The history page converts; the notifications do not.**

| Path | Code | Result |
|---|---|---|
| History page | `app/Livewire/MyRequestHistory.php:81` → `ReplacementRequestMapper::requestedAtDisplay()` (`app/Services/ReplacementRequestMapper.php:54–57`) → `->setTimezone('Asia/Kuala_Lumpur')` | `19:00` ✅ |
| Notification tooltip | `resources/views/livewire/nav-notifications.blade.php:41–44` `$tipAbs = fn($at) => $at->format('j M Y, g:i A')` — **no conversion** | `11:00` ❌ |
| Notification row hover | same file `:34–37` `$absTime` — **no conversion** | `11:00` ❌ |

Both closures are consumed by `resources/views/partials/notif-row.blade.php`:

- `:73` and `:76` — the multiline tooltip's `Requested at: … (… ago)` line;
- `:119` — the row's `data-tip` absolute time (the `relTime()` text next to it is fine).

**Why it slipped in:** the `notif-abstime-precision` lane (2026-10-30) deliberately
switched these closures to *"format the STORED timestamp directly — no
now()−minutes reconstruction"* to stop second-truncation drift. That fixed the
drift but cemented the UTC rendering, because nothing in the notification path
ever applied the project's display timezone.

**Two related defects found while tracing (same family):**

1. `app/Livewire/MyRequestHistory.php:82` formats `decided_at` **raw**
   (`$r->decided_at->format('Y-m-d\TH:i:s')`) while `requestedAt` on the same
   page is converted. The detail modal therefore shows **Requested At 7:00 PM
   but Reviewed At 11:00 AM** for the same request — inconsistent by 8 hours.
   (`RequestApproval`/`ReplacementRequestMapper::toRow` convert `reviewedAt`
   correctly; only this page's own row builder does not.)
2. The tooltip's label says **"Requested at"** but the value is
   `$n->created_at` (the notification's creation stamp), not
   `$n->request->submitted_at`. They are identical for #26 (both 11:00:12), so
   this is **not** the cause, but they can diverge (a re-notification later) and
   the label would then lie.

## 4. Does a reusable, system-wide time function already exist?

**Yes — but only half a one, and it is not used by the notifications.**

`app/Services/ReplacementRequestMapper.php` already owns the project decision
(user, 2026-10-19):

```php
public const DISPLAY_TZ = 'Asia/Kuala_Lumpur';          // :43
public function stampDisplay(?CarbonInterface $stamp, string $format): ?string  // :46
public function requestedAtDisplay(CarbonInterface $stamp): string             // :54
```

Current consumers (all convert correctly):

| Consumer | Line |
|---|---|
| `MyRequestHistory` (requestedAt) | `app/Livewire/MyRequestHistory.php:81` |
| `ReplacementHistory` (requestedAt) | `app/Livewire/ReplacementHistory.php:115` |
| `VenueTimetable` popup (requestedAt) | `app/Livewire/VenueTimetable.php:455` |
| PL queue / API (`toRow`, requested + reviewed) | `app/Services/ReplacementRequestMapper.php:109–110` |
| Timetable timeline popup | `app/Concerns/ResolvesTimetableTimeline.php:422` |

**Limits of the existing function:**

- It lives on a *request-row* service, not a general helper, so a Blade closure
  cannot call it without `app(...)`.
- Its own docblock records a deliberate scope: *"Apply ONLY on the Requested At
  path (scoped per the user's go-ahead; the other timestamps stay
  reported-only)."* Extending it to every stamp is therefore a **user decision**,
  not a free refactor.
- There is **no JS-side converter**: `DateHelper.formatDateTime`
  (`public/js/ui-common.js:2213–2227`) is a pure string reformatter — it splits
  `Y-m-d\TH:i:s` and never applies a timezone. Whatever the PHP side sends is
  what the user sees. That is why the history page must (and does) send MYT
  strings.

## 5. Blast radius (checked, not assumed)

| Site | What it shows | Verdict |
|---|---|---|
| Notification tooltip (`notif-row:73/76`) | `Requested at: 2 Nov 2026, 11:00 AM` | ❌ 8 h behind |
| Notification row hover (`notif-row:119`) | `2 Nov, 11:00 AM` | ❌ 8 h behind |
| Notification relative labels (`1 minute ago`, `4h ago`) | relative | ✅ correct (UTC vs UTC) |
| Notification pill/badge counts | counts | ✅ unaffected |
| History **Requested At** (table + modal) | `7:00 PM` | ✅ converted |
| History **Reviewed At** (modal) | `decided_at` raw | ❌ 8 h behind when a decision exists |
| PL queue / approval page timestamps | via `toRow()` | ✅ converted |
| Venue timetable pending popup | via `stampDisplay` | ✅ converted |
| Dashboard / my-timetable / cohort / student pages | no absolute DB stamps | ✅ unaffected |
| Settings → passkeys | `diffForHumans()` only | ✅ relative, unaffected |
| Audit log surface | not rendered as absolute times | ✅ unaffected |

Every notification **type** (`submitted`, `approved`, `rejected`, `cancelled`,
`update`) shares the same two closures, so all of them are 8 h behind wherever
they render an absolute time.

## 6. Recommended solution (for approval — not implemented)

**Fix the display, never the data.** The stored UTC values are correct; rewriting
them would break sorting, age colours and every converted surface.

**A. Smallest correct fix (the reported bug)**
In `nav-notifications.blade.php`, make the two closures convert before
formatting — reuse the existing constant, do not re-derive it:

```php
$absTime = fn ($at) => $at
    ? $at->copy()->setTimezone(\App\Services\ReplacementRequestMapper::DISPLAY_TZ)->format('j M, g:i A')
    : '';
// $tipAbs: same, with 'j M Y, g:i A'
```

This fixes both the tooltip line and the row hover, for every notification type,
with no layout/copy/logic change.

**B. Stop the class of bug (recommended together with A)**
Promote the conversion into one tiny shared place — e.g. a static
`LocalTime::stamp(?CarbonInterface, string $format): ?string` (or a public static
method on the mapper) that owns `DISPLAY_TZ`; have
`ReplacementRequestMapper::stampDisplay()` delegate to it and have the
notification closures call it. Then fix
`MyRequestHistory.php:82` (`reviewedAt`) to use the same helper.

Result: one owner for "the user's clock", the history modal becomes internally
consistent, and a future page that formats a timestamp has an obvious function to
reach for.

**C. Optional semantic fix**
Build the tooltip line from `$n->request?->submitted_at ?? $n->created_at`, so
"Requested at" is the request's stamp even if a notification is regenerated later.

**What we should NOT do**

- Do **not** rewrite the DB rows, and do **not** change `APP_TIMEZONE`/`app.timezone`
  (that would shift `now()`, every stored row and every test at once).
- Do **not** hardcode `19:00` or `->addHours(8)` — DST-free today, a trap forever;
  `setTimezone` is the correct tool.
- Do **not** touch the relative-time logic — it is already correct.
- Do **not** change the history page's `requestedAt` — it is already right.
- Do **not** hardcode Request #26's value anywhere.

## 7. Proposed acceptance tests (after approval)

- [ ] Request #26 tooltip in the notifications panel reads `2 Nov 2026, 7:00 PM`;
      the `(… ago)` tail still says the same relative value as before.
- [ ] Hovering the row's time chip shows `2 Nov, 7:00 PM`.
- [ ] A notification created now shows the current **MYT** wall clock, not UTC.
- [ ] All types (new request / approved / rejected / cancelled / update) agree.
- [ ] History modal shows Requested At **and** Reviewed At in MYT (part B).
- [ ] Sorting, age colours, badge counts and the 10 s poll are unchanged.
- [ ] Dark + light, 390 px, no console errors.
- [ ] Existing tests stay green; a new assertion pins one known instant converted
      (e.g. `2026-11-02 11:00:12Z → 2 Nov 2026, 7:00 PM`), not the request id.

## 8. Questions for the user (needed before implementing)

1. Fix **only** the notification tooltip (A), or also the row hover (same fix) and
   the `reviewedAt` inconsistency in the history modal (B)?
2. Promote the shared helper (B, recommended) or call the mapper constant directly
   from the Blade closures (A only)?
3. Should "Requested at" read the request's `submitted_at` rather than the
   notification's `created_at` (C)?
4. The mapper's docblock says the conversion was scoped to "Requested At" only,
   with other stamps "reported-only" (your 2026-10-19 decision). Do you want to
   widen that decision now, or keep the scope and fix only the stamps above?

**No code has been modified. Waiting for approval before any implementation.**
