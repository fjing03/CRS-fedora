# Queue + emails — runbook (SDD span-aware-approval-flow, tasks T19)

## Dev / demo behavior (the standing posture)

- **Nothing really sends.** `.env` keeps `MAIL_MAILER=log` — everyQueued email
  lands IN `storage/logs/laravel.log` (you can read its To/Subject/body
  right there). `phpunit.xml` uses the `array` driver instead (tests assert
  via `Mail::fake`/`Queue::fake` — zero email traffic).
- **The queue is database-backed** (FR 4.16): `QUEUE_CONNECTION=database` —
  mail inserts live in the `jobs` table until a worker claims them. A
  MISSING worker never loses an email (it waits); the demo flow:
  1. make a decision (approve/reject a request);
  2. `php artisan queue:work --once` — the NEXT queued job is consumed,
     rendered, and written to the log;
  3. failed jobs park in `failed_jobs` (stock Laravel behavior — retry via
     `queue:work --retry` or delete).

## Real sending — the Gmail SMTP flip (SV-approved; ops step, zero code)

`.env` is gitignored — **the App Password never goes into a commit, a prompt, a
chat paste, or a screenshot.**

| # | Setting | Today | Target | Note |
|---|---|---|---|---|
| 1 | `MAIL_MAILER` | `log` | `smtp` | the only line that changes behaviour |
| 2 | `MAIL_HOST` | `127.0.0.1` | `smtp.gmail.com` | |
| 3 | `MAIL_PORT` | `2525` | `587` | 587 = STARTTLS; 465 also works (Laravel picks `smtps` by port) |
| 4 | `MAIL_SCHEME` | `null` | **leave `null`** | ⚠ Laravel 12 has **no `MAIL_ENCRYPTION`** (checked: `config/mail.php` + `MailManager::createSmtpTransport`); null means "smtp", and 587 negotiates STARTTLS by itself. Setting `MAIL_ENCRYPTION` does nothing. |
| 5 | `MAIL_USERNAME` | `null` | your full Gmail address | |
| 6 | `MAIL_PASSWORD` | `null` | the 16-character Google **App Password** (requires 2FA) | not your normal password |
| 7 | `MAIL_FROM_ADDRESS` | `hello@example.com` | the same address as the username | Gmail rejects a mismatched `From` |
| 8 | `MAIL_FROM_NAME` | `"${APP_NAME}"` → renders **"Laravel"** (because `APP_NAME=Laravel`) | `"TAR UMT Class Replacement System"` | set the name directly; do **not** rename `APP_NAME` just for mail |
| 9 | `QUEUE_CONNECTION` | `database` | **keep `database`** | FR 4.16 — queued mail waits in `jobs`; nothing is lost without a worker |
| 10 | `MAIL_EHLO_DOMAIN` (optional) | unset | `localhost` | only if Gmail complains about the EHLO name |

> **2026-11-03 — executed.** The flip was run and receipted end-to-end; the
> "Today" column above is the pre-flip snapshot. Receipt + gotchas:
> *"2026-11-03 · M2 flip — receipt"* below.

**Verify before believing it**

1. `php artisan config:clear`
2. One direct message to yourself (proves the credentials, bypasses the queue):
   ```
   php artisan tinker --execute="Mail::raw('SMTP test', fn(\$m) => \$m->to('YOUR@gmail.com')->subject('CRS SMTP test'));"
   ```
3. Then the queued path: make a throwaway decision →
   `php artisan queue:work --once` → check the inbox **and** `failed_jobs`.

**⚠ Recipient safety before any demo.** The seeded lecturers/students carry real
`tarc.edu.my` addresses. With `MAIL_MAILER=smtp`, approving a request really
emails them. Either (a) demo only accounts whose addresses are yours, or
(b) revert to `MAIL_MAILER=log` for the demo and show the rendered mail in
`storage/logs/laravel.log`. Decide **before** flipping.

**Revert** is one line: `MAIL_MAILER=log` + `php artisan config:clear`.

## 2026-11-03 · M2 flip — receipt (EXECUTED end-to-end)

`.env` was flipped and proven. Framework in this repo is `laravel/framework ^13.17`;
the "no `MAIL_ENCRYPTION`" finding was re-verified in
`vendor/laravel/framework/src/Illuminate/Mail/MailManager.php:196-200` —
`MAIL_SCHEME` is the only scheme knob, and port 587 negotiates STARTTLS by itself.

| Step | Result |
|---|---|
| 8 rows set (`MAIL_MAILER`, `HOST`, `PORT`, `USERNAME`, `PASSWORD`, `FROM_ADDRESS`, `FROM_NAME`) | `config('mail.default')` = `smtp`, host `smtp.gmail.com`, port `587`, username set — `MAIL_SCHEME=null` + `QUEUE_CONNECTION=database` untouched, `APP_NAME` untouched |
| `php artisan config:clear` | cleared (no config cache existed) |
| Direct `Mail::raw` → `foojing03@gmail.com` | landed in the inbox |
| Queued dummy `ReplacementDecided` → `onQueue('m2-test')` → `queue:work --queue=m2-test --once` | `RUNNING → 4s DONE`; `m2-test` drained; `failed_jobs` 0; landed in `poongfj-sm23@student.tarc.edu.my` |

**Two gotchas that cost time**

1. The App Password pasted **with its spaces** (`xxxx xxxx xxxx xxxx`) breaks
   dotenv — "Encountered unexpected whitespace at [...]", and EVERY artisan
   command fails until fixed. Use the 16 characters without spaces.
2. `MAIL_FROM_ADDRESS` must equal `MAIL_USERNAME` — the first attempt still had
   `hello@example.com`; Gmail rejects that From.

**Backlog — untouched by the test, still loaded:** the `default` queue holds
**10** jobs (8 `ReplacementSubmitted` + 2 `ReplacementDecided`, queued
2026-11-01/02) addressed to real `tarc.edu.my` people (`limjz@`,
`mohdnurrahmat@`, `surayaini@`). That is why the queued test used the isolated
`m2-test` queue. **No full `queue:work` has run since the flip.**

**Posture (M2 item 3) = DECIDED 2026-11-03 — split by repo.**

- **`CRS-fedora-backend` (dev) = `log`.** The SMTP block stays in `.env`
  (host/port/username/password/from) so a future flip is one line; a worker only
  renders the 10 backlog jobs into `storage/logs/laravel.log`. No purge needed.
- **`CRS-demo` (viva, DB `class_replacement_viva`) = `smtp`**, flipped
  2026-11-03 (creds copied from the dev `.env`; `MAIL_SCHEME=null`, port 587; no
  worker running). ⚠ It holds **27 stale jobs** (14 `ReplacementApprovalNotice`
  → `23rsd0011–0024@student.tarc.edu.my`, 10 `ReplacementSubmitted` +
  3 `ReplacementDecided` → `limjz@`/`mohdnurrahmat@`/`surayaini@`) queued
  2026-11-01/02 — **purge before the first worker run** (demo-DB gate:
  snapshot → DELETE → log in `docs/demo-db-data-changes.md`). After the purge, a
  viva action emails only the fresh recipients.

**App Password:** it appeared in a chat/error paste during this run; the user
**declined rotation** on 2026-11-03. Residual risk stays until it is rotated
(revoke at `myaccount.google.com/apppasswords` → new App Password → `.env`
16 chars, no spaces → `php artisan config:clear` → one `Mail::raw` re-test).

## The three emails (recipients per the FR contract)

| FR | Mailable | Who | When |
|---|---|---|---|
| 4.15 | `ReplacementSubmitted` | every `lecturers.is_pl` PL | a submit succeeds |
| 2.13 | `ReplacementDecided` | the proposer (Q13: even when the PL is the proposer) | approve / reject (+ the reason when rejected) |
| 1.9 | `ReplacementApprovalNotice` | the affected cohorts' students | approve only |

All three are capture-at-QUEUE-TIME (the mail body is a snapshot built from
the PRE-release row state — a rejected row's deleted junction never blocks
the worker's render).
