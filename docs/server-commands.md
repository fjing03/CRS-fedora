# Server Commands — Start / Restart / Stop

Cheat sheet for the Laravel dev server (`php artisan serve`) on this project.
Works in **bash** and **fish** (fish quirks noted inline).

> **Why a ritual at all?** The old server process holds **compiled Blade views
> in memory** — change a view, restart wrong, and you keep seeing yesterday's
> HTML. Half of all "ghost bugs" are stale process + browser cache.

---

## ▶ Start (server currently stopped)

Run as **two separate commands**, in order:

```bash
pkill -f "[a]rtisan serve" || true
```

```bash
rm -f storage/framework/views/*.php && php artisan serve --port=8000 &
```

**Verify it's up** (run as its own command):

```bash
curl -s -o /dev/null -w "HTTP %{http_code}" http://127.0.0.1:8000/login
```

Expected: `HTTP 302` (redirect to the login page = Laravel is alive).

---

## 🔁 Restart (after ANY UI / Blade change) — the standard ritual

Same two commands as Start. Kill first, then clear views + start:

```bash
pkill -f "[a]rtisan serve" || true
```

```bash
rm -f storage/framework/views/*.php && php artisan serve --port=8000 &
```

Then **hard-refresh the browser once**: `Ctrl+Shift+R` (Mac: `Cmd+Shift+R`).

### ⚠️ Hard rules

| Rule | Why |
|---|---|
| ❌ **Never** glue the kill and the start into ONE command | the kill pattern matches its own wrapper and kills the command itself |
| ❌ **Never** `pkill -9 php` | force-kills every PHP process on the machine |
| ❌ **Never** put the literal text `artisan serve` in the same command as `pkill` (e.g. a `pgrep` check after) | the wrapper's own command line contains that text → self-match → SIGTERM to yourself (run the check as a **separate** command) |
| ✅ Kill → clear → start, always in separate commands | old process must be dead before views are cleared |

---

## ⏹ Stop

```bash
pkill -f "[a]rtisan serve" || true
```

Verify (separate command):

```bash
pgrep -af "artisan serve"
```

No output = fully stopped.

---

## 🧹 Deep clean (ghost bugs, or after big changes)

Run **in this order**, each as its own command:

```bash
pkill -f "[a]rtisan serve" || true
```

```bash
php artisan optimize:clear
```

```bash
php artisan view:clear
```

```bash
npm run build
```

```bash
php artisan serve --port=8000 &
```

| Step | Ghost it kills |
|---|---|
| `pkill` | stale server process (in-memory compiled views) |
| `optimize:clear` | cached config + routes + views + events |
| `view:clear` | leftover compiled Blade (fish-safe: no glob needed) |
| `npm run build` | stale JS/CSS bundle *(skip if no source assets changed)* |
| `serve` | fresh process reads everything new |

**Browser side after the restart:**

1. Hard refresh: `Ctrl+Shift+R`
2. Still weird? DevTools (`F12`) → **Application → Storage → Clear site data**
   (wipes `localStorage` ghosts like remembered week/favourites)
3. Testing with DevTools open? Tick **Network → Disable cache** — then plain
   `F5` is already clean.

---

## 🍣 Fish shell notes

- Fish **errors** on wildcards that match zero files:
  `rm -f storage/framework/views/*.php` → `No matches for wildcard`.
  That means the folder is *already empty* — or use the glob-free
  `php artisan view:clear` instead.
- All commands above work in fish as written; just keep them on **separate
  lines**.

---

## 📋 Which change needs what

| You changed… | Action |
|---|---|
| Any `.blade.php` / view | **Restart ritual** (2 commands) + hard refresh |
| Plain PHP (`app/…`, routes) | Usually just refresh the browser; restart if it looks ignored |
| Queued jobs / mail (Slice C) | Restart ritual **+ restart the queue worker** (below) |
| JS/CSS source (`public/js`, `theme.css` via Vite) | `npm run build` + hard refresh |
| `.env` / config | `php artisan optimize:clear` (no full restart needed) |
| DB rows only | Nothing — data is read live per request |

### Queue worker (emails / jobs)

Long-running process — it keeps OLD code in memory until restarted:

```bash
pkill -f "queue:work" || true
```

```bash
php artisan queue:work &
```

---

**Rule of thumb:** changed anything you can see → run the restart ritual.
When in doubt, restart — it costs 5 seconds and never lies to you.
