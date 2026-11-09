# Design — notifications-real-bridge

## D1. Schema + the emitter (the after-commit law)

```php
Schema::create('notifications', function (Blueprint $t) {
    $t->id();
    $t->foreignId('user_id')->constrained()->cascadeOnDelete();
    $t->string('role_view', 12);        // pl | lecturer | student  (AD-2 key)
    $t->string('type', 16);             // submitted|awaiting|approved|rejected|update (frozen tile keys)
    $t->string('title');
    $t->string('body');
    $t->string('link');
    $t->foreignId('request_id')->nullable()->constrained('replacement_requests')->nullOnDelete();
    $t->foreignId('class_session_id')->nullable()->constrained()->nullOnDelete();
    $t->unsignedTinyInteger('week')->nullable();
    $t->timestamp('read_at')->nullable();
    $t->timestamps();
    $t->index(['user_id', 'read_at']);
    $t->index(['request_id']);
});
```

`App\Support\Notifications\NotificationEmitter` (beside ReasonLabel):

```php
public function send(User $to, string $roleView, string $type, string $title,
                     string $body, string $link,
                     ?int $requestId = null, ?int $classSessionId = null,
                     ?int $week = null): void;   // DB::afterCommit create
public function notifyPls(...);                  // users where is_pl (the PL mail law's recipient query)
public function notifyCohorts(ClassSession $s, string $type, ...);  // the SAME recipient
                                                 // resolution queueCohortMail uses
                                                 // ($cohort->students()->with('user'))
public function notifyDeptPls(ClassSession $s, ...);  // the PLs of the class
                                                 // owner's department (the
                                                 // cancelled event's ruling)
```

Call sites (BESIDE the existing Mail::queue lines, inside the same
afterCommit closures):

| `CancelClass` receipt: it queues NO mail and has NO afterCommit closure — the island emitter ADDS its own `DB::afterCommit` wrapper there (the only new closure).
| Action | Emitter call |
|---|---|
| `SubmitReplacementRequest` | every PL: type `submitted`, role pl, link `/request-approval-ui` |
| `ApproveRequest` | the proposer: `approved`/lecturer, link `/my-request-history-ui`; every cohort student: `approved`/student, link **`/student-my-timetable-ui`** (the per-role link law below) |
| `RejectRequest` | the proposer: `rejected`/lecturer, link `/my-request-history-ui` |
| `CancelClass` | **THREE fan-outs (the user's ruling, clarified with the 5770/ABCD6767 example)**: the class's own lecturer (`update`/lecturer, link **`/cohort-timetable`**), every cohort student (`update`/student, link **`/student-my-timetable-ui`**), and the PLs **of the class owner's department** (`update`/pl, link **`/venue-timetable-ui`** — the emitter adds `notifyDeptPls($session)` scoped by the owner's `lecturers.dept_id`; DEPT-ONLY, not a faculty compound: the owner's dept = `Lecturer::where('user_id', $session->lecturer_id)->value('dept_id')`) |

**The per-role link law (round-2 🔴1 — cohort-timetable is lecturer-gated, a student row must never link it):**

| Recipient role_view | Link |
|---|---|
| pl (submitted/reminder rows) | `/request-approval-ui` |
| pl (cancelled rows) | `/venue-timetable-ui` (venue-centric truth; `role:lecturer` passes for PLs) |
| lecturer (approved/rejected/cancelled) | `/my-request-history-ui` (outcomes) / `/cohort-timetable` (cancel-owner record) |
| student (approved/cancelled) | `/student-my-timetable-ui` (the approval mail's own "check your timetable" law) |

## D2. The islands (ONE class, two mount points)

`App\Livewire\NavNotifications`:
- **Mount A (the bell)** in `partials/ui-nav-bar.blade.php`: the badge
  span replaces the static `#notifBadge` (the SAME id/classes; the count
  = the user's unread TOTAL — `updateNotifHeaderState`'s law).
- **Mount B (the WHOLE panel content)** in
  `partials/ui-notifications-panel.blade.php` **wraps the entire
  `.notif-panel` body — head (pill + mark-all visibility), filters
  (the unread-only toggle), the list, and the caught-up swap render
  server-side from the same unread total** (round-2 🔴2: a static head
  would desync the pill/mark-all after a durable POST or a poll).
  `#notifList` renders the frozen row markup (`.notif-row` + `--read`,
  the tiles, the glyph SVG partial, the abs-time tip, the caught-up swap
  via `#notifEmpty`); the toggle + mark-all are island state/actions
  (the toggle = a live property so the poll re-render preserves it).
- The ONE class / TWO mounts discriminator = a public `variant`
  property (`'bell' | 'panel'`) — each mount renders its slice only.
- `wire:poll.10s ->whenVisible()` on BOTH mounts; `initNotifPanel`
  keeps ONLY open/close/Esc/overlay + the scroll-restore law — its
  `markAllNotifsRead` binding and the toggle→`renderNotifList` change
  listener are DELETED (server-owned now); the capture-phase row click
  listener is REWRITTEN onto the durable POST (D3) + the scroll restore.
  The AD-2 tabs: the viewed category derives from the page key — the
  server equivalent of `NOTIF_ROLE_BY_PAGE` via the island's `pageKey`
  mount argument (submitted/reminder → pl; approved/rejected →
  lecturer; cancelled/update → student).
- `PENDING_NAV_COUNT` untouched (a different truth — never unified).

## D3. The read path (the D4 law preserved)

- The rows stay NATIVE anchors; the capture-phase click listener (the
  existing :2171 pattern, retired-store swap) fires
  `fetch('/notifications/read', {method:'POST', keepalive:true,
  body: JSON.stringify({id}), headers: {X-CSRF-Token, Content-Type}})`
  BEFORE the navigation — a durable fire-and-forget write; NO Livewire
  round-trip in the click; no middle-click loss.
- The route: `POST /notifications/read` (auth) → `read_at = now()` on
  the user's row (idempotent). Mark-all = `POST /notifications/read-all`.
- The badge refreshes on the next poll cycle + every page load.

## D4. The lifecycle (the user's law, at render)

A row with `request_id` resolves its request live at render — **batch-resolved**:
ONE `whereIn('id', $rowRequestIds)` keyed map per island render (the
round-2 🟡 N+1 law — never per-row `find()` on a 10 s poll). The request
missing OR `status = 'cancelled'` → the row renders DISABLED: no href,
`.notif-row--inactive` (muted via the existing tokens), the body keeps
the original copy, and `data-tip="This notification is inactive because
the request was cancelled."` — never click-markable (mark-all is the
path to zero). Approved/rejected/pending rows keep their links.

## D5. The reminder command

`notifications:remind-pending {--hours=24}`: for every pending request
older than the threshold, one row per PL (role pl, type `awaiting`, link
request-approval-ui, request_id set) — skipped when an UNREAD `awaiting`
row for the same (user, request) exists; a read reminder re-arms the
next run. `routes/console.php` gains `->daily()->at('08:00')`; the demo
runs it on demand.

## D6. The mock retirement (the file stays)

- `mock-data.js` §2.13 rows + the :1710 persona sweep stay in the FILE,
  unread by the panel.
- `ui-common.js` retires: refreshNotifBadge, renderNotifList,
  getNotifReads/persistNotifReads/notifReadKey/notifUserId,
  markNotifRead/markAllNotifsRead, the AD-8/F-2 seed block, the
  notifDevToggle TEMP block — and the capture-phase row listener is
  REWRITTEN onto the durable POST (D3).
- The glyph/abs-time helpers stay as JS OR move into the island's Blade —
  design choice: the ROW markup renders server-side (the island), so the
  glyph svg + relTime/absTime move to a Blade partial
  (`partials/notif-row.blade.php`) — the JS copies retire.

## D7. Tests (the suite grows)

- `NotificationEmitterTest`: the five events → the rows (recipients,
  role_view, types per the mapping law); after-commit visibility.
- `NavNotificationsTest`: the badge count law; the lifecycle disabled
  row (+ the tooltip text); the mark-read/mark-all endpoints
  (idempotent, auth-scoped); the reminder command (threshold, dedupe,
  re-arm).
- Playwright: the badge updates within a poll cycle without a reload;
  the frozen visual diff = zero on the panel; the disabled row's
  tooltip; **an OPEN panel survives a poll cycle** (the open state, the
  list scrollTop, the toggle preserved — AD-15 under randomise).
- The dept-scoped recipients test (the D1 `notifyDeptPls` law).

## D8. The user's final rulings (2026-10-28, post-explore)

- **Receipts strategy: B + one C** — the full behavioural receipts live in
  the disposable test DB (the suite); the demo DB gets a few HAND-RUN
  seed rows (the user runs the INSERTs per `docs/demo-db-data-changes.md`)
  so the bell is visibly alive in the demo; NO real booking flow is
  executed on the demo DB for receipts; the real requests #1–#4 stay
  untouched.
- **Reminder threshold: 24 h** ✓; **poll interval: 10 s** ✓.
- **Cancelled-class fan-out: the class's lecturer + the cohort students +
  the PLs of the class owner's department** (the clarified ruling —
  5770/ABCD6767/DFT2 ⇒ 5770 + all DFT2 students + the FOCS/DCIT PLs; the
  actor keeps a record row too).
- **The SV approved Gmail SMTP** (2026-10-28) — the SMTP flip is now a
  DEMO-PACK OPS STEP (the .env values + the personal-Gmail App Password);
  it stays OUT of this lane's code; the NFR 7.2 doc revision is the
  user's own edit when they make it.

## D9. Round-2 folds (declarative)

- The retired-JS list additionally names `resetNotifDemo`, `notifIds`,
  and the two retired calls inside `openNotifPanel` (:2086–2087).
- The demo-DB seed rows are NOT agent-run: the hand-run INSERTs are
  DRAFTED in T6 and handed to the user (the data-changes convention).
- T5's :8001 clause = KILLING a stale :8001 sibling process during the
  ritual; the serve command itself runs :8000 (the house law).
