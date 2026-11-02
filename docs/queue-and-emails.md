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

## The three emails (recipients per the FR contract)

| FR | Mailable | Who | When |
|---|---|---|---|
| 4.15 | `ReplacementSubmitted` | every `lecturers.is_pl` PL | a submit succeeds |
| 2.13 | `ReplacementDecided` | the proposer (Q13: even when the PL is the proposer) | approve / reject (+ the reason when rejected) |
| 1.9 | `ReplacementApprovalNotice` | the affected cohorts' students | approve only |

All three are capture-at-QUEUE-TIME (the mail body is a snapshot built from
the PRE-release row state — a rejected row's deleted junction never blocks
the worker's render).
