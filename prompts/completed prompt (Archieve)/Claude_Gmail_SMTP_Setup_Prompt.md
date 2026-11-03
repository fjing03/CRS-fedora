# Prompt for Claude: Guide Me Through Gmail SMTP Setup for My Laravel Project

## Your role

Act as a patient Laravel email-setup guide for a beginner. Use simple English, explain technical terms briefly, and guide me one step at a time. Do not assume I already understand Laravel mail settings, SMTP, environment files, or queues.

## My goal

I want my Laravel project to send real email notifications to users' real inboxes. I prefer Gmail because real delivery is more useful and professional for my project demonstration.

I can use my personal Gmail account. Two-Step Verification is already enabled on that account.

## Project context

- This is a Laravel project.
- The email notification mailables and the actions that queue them are reported to already exist.
- The queue is reported to use the database driver (`QUEUE_CONNECTION=database`).
- The current mail configuration may use `MAIL_MAILER=log`, but please ask me to check the actual `.env` settings instead of assuming.
- The remaining work may include Gmail SMTP configuration, a Google App Password, queue-worker operation, and testing. Verify the actual setup before concluding that anything is complete.
- I am a beginner, so tell me exactly where to click, what file or terminal to open, and what to look for.

## Important requirement and approval

My current NFR 7.2 says the system must stay on localhost and must not send data outside. I believe this requirement may be flexible, and I may make a small change to it if necessary. However, I still need to confirm with my project supervisor whether using Gmail SMTP is acceptable.

Sending real emails through Gmail sends email data outside my computer — including a test email to my own address. Therefore:

- Explain this impact clearly.
- Do not claim Gmail SMTP complies with the current NFR 7.2.
- Do not edit my report, NFR, source code, or project files.
- Until I confirm supervisor approval, help me inspect and prepare the setup only (checking settings, writing down the values, understanding the commands) — but do NOT guide me to send any test email yet, not even to my own address.
- Do not start any separate SDD exploration or design activity.

## How to guide me

1. Start by asking me to open the Google App Passwords page (myaccount.google.com/apppasswords) and report whether the option is available. Do not ask me to share any password.
2. If the option is NOT available (this happens when the Gmail account is managed by an organisation, e.g. a university Google Workspace account), stop and help me choose a fallback with me: ask my supervisor/IT, or use my own personal Gmail account instead. Do not improvise around a blocked App Password.
3. Explain how to create a Google App Password if the option is available. Never tell me to use my normal Gmail password. Remind me to give it a name like "FYP Laravel" so I can revoke it later.
4. Ask me to open my Laravel project's `.env` file and check only the `MAIL_` settings. I should NOT paste them into chat — instead, tell me the exact target values and let me confirm each one matches or needs changing.
5. Give me the exact Gmail SMTP settings and explain each line:
   - `MAIL_MAILER=smtp`
   - `MAIL_HOST=smtp.gmail.com`
   - `MAIL_PORT=587`
   - `MAIL_ENCRYPTION=tls`
   - `MAIL_USERNAME=` my full Gmail address
   - `MAIL_PASSWORD=` the 16-character App Password (no spaces)
   - `MAIL_FROM_ADDRESS=` the SAME Gmail address as the username
   - `MAIL_FROM_NAME=` my project's name
   Explain that port 465/SSL is the common wrong answer, and that a mismatched `MAIL_FROM_ADDRESS` is the common cause of rejected mail.
6. Explain that Laravel caches configuration: after changing `.env` I must run `php artisan config:clear` (only after confirming I am in the Laravel project directory). Also explain that a RUNNING `php artisan queue:work` process keeps the OLD configuration until it is restarted — so if a worker is running, I must stop it (Ctrl+C) and start it again after the `.env` change.
7. Explain what `php artisan queue:work` does, why the terminal must remain open while it runs, and how to check whether the database queue is configured correctly (`QUEUE_CONNECTION=database` in `.env`, and the `jobs` table exists). For a first test, `php artisan queue:work --once` is a safe incremental option (it processes exactly one job and exits).
8. Testing rules: sending ANY test email is an external send, so it happens ONLY after I confirm supervisor approval. When approved, help me test with my own email address first. Remind me to check the Spam/Promotions folder before concluding a mail is lost. If delivery fails, help me diagnose in this order: the terminal error, `storage/logs/laravel.log`, and the `failed_jobs` table (old failed jobs do NOT retry by themselves — `php artisan queue:retry all` or a fresh test send).
9. Do not say the setup works until the actual test passes (the mail visible in my inbox).
10. After the project or demo ends, remind me to revoke the App Password in the same Google page (it is a full-account SMTP key).
11. Keep the process incremental: give me one manageable step, wait for my result, then continue. Do not overwhelm me with every step at once.

## Security rules

- Never ask me to paste an App Password, normal password, API key, or other secret into chat.
- Remind me not to commit `.env` or credentials to Git.
- Use placeholder values when showing examples.
- Do not send test emails to real users before I have approval and confirmed the test works.
- Remind me to revoke the App Password when the project ends.

## Start now

Give me only the first step: how to check whether Google allows me to create an App Password, and ask me what I see. Do not begin by changing files or giving me a large list of commands.
