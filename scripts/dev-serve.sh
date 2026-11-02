#!/usr/bin/env bash
#
# dev-serve.sh — start the local dev server WITHOUT the OPcache stale-code trap.
#
# WHY THIS EXISTS (2026-11-02, SDD venue-status-parity, verified twice):
# this machine's CLI opcache is `enable_cli=On` with
# `opcache.revalidate_freq=180`. The long-running `php artisan serve` process
# therefore keeps serving a PHP file's PREVIOUS bytecode for up to ~3 minutes
# after you edit it (it only re-stats the file every 180 s). Symptom: "my fix
# didn't take effect", 500s naming a class you DID import, a payload key you DID
# add being absent. Blade/JS/CSS are not affected the same way (compiled views
# are re-created, and the view cache is cleared below).
#
# Running the built-in server with `-d opcache.revalidate_freq=0` makes PHP
# re-check every file on every request, so an edit is live on the next page load.
# (`php artisan serve` cannot pass -d flags to the child it spawns, so this
# script invokes the same built-in server + Laravel router directly — parity
# checked against Illuminate\Foundation\Console\ServeCommand::serverCommand().)
#
# USAGE
#   scripts/dev-serve.sh              # :8000, the default .env database
#   scripts/dev-serve.sh 8030         # :8030
#   DB_DATABASE=class_replacement_testing scripts/dev-serve.sh 8030
#
# It KILLS any server already listening on that port, clears the compiled views,
# then starts a nohup'd server and prints a health code. One command, so the
# "never combine pkill and the start in one shell string" trap cannot bite.
set -euo pipefail

PORT="${1:-8000}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

ROUTER="vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"
[ -f "$ROOT/server.php" ] && ROUTER="server.php"

# kill only THIS port's server (the [x] trick keeps the pattern from matching
# this script's own argv)
pkill -f "[a]rtisan serve --port=${PORT}" 2>/dev/null || true
pkill -f "[-]S 127.0.0.1:${PORT} " 2>/dev/null || true
sleep 1

rm -f storage/framework/views/*.php

cd "$ROOT/public"
nohup php -d opcache.revalidate_freq=0 -d opcache.validate_timestamps=1 \
    -S "127.0.0.1:${PORT}" "../${ROUTER}" \
    > "/tmp/opencode/serve-${PORT}.log" 2>&1 &

sleep 3
CODE="$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${PORT}/login" || echo 000)"
echo "dev server :${PORT} -> HTTP ${CODE} (opcache revalidate_freq=0${DB_DATABASE:+, db=${DB_DATABASE}})"
echo "log: /tmp/opencode/serve-${PORT}.log"
[ "$CODE" = "302" ] || { echo "FAILED — see the log above" >&2; exit 1; }
