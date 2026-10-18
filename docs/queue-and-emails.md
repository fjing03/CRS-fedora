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

## Real sending later (when SMTP exists)

Flip ONLY the mailer block in `.env` — zero code changes
(Mailables + queue already real):

```
MAIL_MAILER=smtp
MAIL_HOST=<provider or TARUMT relay>
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=<an approved sender>
```

…plus run the worker (`php artisan queue:work` — or `queue:work --tries=3`).

## The three emails (recipients per the FR contract)

| FR | Mailable | Who | When |
|---|---|---|---|
| 4.15 | `ReplacementSubmitted` | every `lecturers.is_pl` PL | a submit succeeds |
| 2.13 | `ReplacementDecided` | the proposer (Q13: even when the PL is the proposer) | approve / reject (+ the reason when rejected) |
| 1.9 | `ReplacementApprovalNotice` | the affected cohorts' students | approve only |

All three are capture-at-QUEUE-TIME (the mail body is a snapshot built from
the PRE-release row state — a rejected row's deleted junction never blocks
the worker's render).
