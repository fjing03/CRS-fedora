<?php

namespace App\Livewire;

use App\Models\ClassSession;
use App\Models\Notification;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Models\User;
use App\Models\Venue;
use App\Services\ReplacementRequestMapper;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The notifications island (SDD notifications-real-bridge): ONE component,
 * TWO mounts (design D2) —
 *   island 'bell'  → the unread TOTAL badge (the updateNotifHeaderState law)
 *   island 'panel' → the WHOLE panel body server-side (the head pill + the
 *                    mark-all visibility + the filter + the list + the caught-up
 *                    swap), from the SAME unread total.
 * Both `wire:poll.10s` (updates without a page refresh — the user's ruling);
 * the read path stays the durable POST (D3) — the rows are NATIVE anchors.
 * ONE FEED (notif-unified-feed — the user's 2026-10-29 ruling, superseding
 * D2's AD-2 page-category tabs): the panel shows the RECIPIENT-WIDE feed on
 * every page — same rows, order and badge everywhere. The lifecycle law:
 * rows never delete; an inactive link target renders the row disabled + the
 * user's tooltip.
 *
 * notif-manage-hide (2026-11-02, the user's approved design): the complaint
 * "the panel accumulates everything forever" now has a TIDY-UP path that keeps
 * the never-delete law intact — HIDING is a soft, reversible flag
 * (`notifications.hidden_at`, stamped on hide / cleared on unhide; NO row is
 * ever deleted, no destructive copy anywhere). The gate is the EXACT
 * COMPLEMENT of the badge predicate — hideable ⇔ `read_at !== null ||
 * inactive_reason !== null` — so a hidden row can never be badge-counted by
 * construction, and `render()`'s badge block stays untouched. The gate is
 * PHP-derived (`inactive_reason` is not a column), therefore every posted id
 * is RE-VALIDATED server-side in hideSelected(): the client's ticks are a
 * proposal, never an authority.
 *
 * SDD notif-new-request-ux (design §1/§2/§4): `submitted` rows (and,
 * deliberately, `awaiting` reminders — the D2 side-effect) compose
 * SERVER-SIDE per render; the inactive classifier classifies the REASON
 * (first-match cancelled > approved > rejected), not a boolean — a
 * decided SUBMITTED row renders inactive (the real-bridge D4
 * links-stay-alive law OVERTURNED for `submitted` rows), while non-submitted
 * request-linked rows stay in notif-click-highlight's both-clickable law.
 * READING is not a decision (the user's 2026-11-04 ruling): a read row of a
 * still-pending request stays ACTIVE and composed, so `read` is no longer an
 * inactive reason (the old D15 read arm is retired).
 * read_at is never stamped by the display path; the badge = the BATCH-DERIVED
 * actionable unread (the standalone COUNT query is gone — D5) and the
 * composer NEVER runs a per-row span()/Venue::find/Semester::find
 * (the ONE-`replacement_requests`-query law, test :253-274).
 */
class NavNotifications extends Component
{
    /**
     * WHICH mount this is: 'bell' or 'panel'.
     *
     * DELIBERATELY NOT called `$variant` (renamed 2026-11-04). Blade's
     * `@include` shares the PARENT'S SCOPE, so a public property named
     * `$variant` was inherited by `partials/notif-row`, whose own
     * `$variant = $variant ?? null` could not override it (a real string is
     * not "missing"). Every inbox row therefore failed the partial's
     * `$variant === null` guard: `data-id`, `data-notif-target` and
     * `data-notif-week` were never printed, which silently killed the
     * mark-read POST and the click-to-flash handoff from 2026-11-02 until the
     * rename. A mount-role name that the row partial does not use cannot
     * collide this way again.
     */
    public string $island = 'bell';

    /** Kept ACCEPTED for mount compatibility (the panel mounts pass the page
     *  key); it no longer selects anything — one feed per recipient, not per
     *  page. */
    public string $pageKey = '';

    /** The unread-only toggle (server-owned so the poll re-render preserves it). */
    public bool $unreadOnly = true;

    /** The active-only toggle (SDD-waived UI request, 2026-11-01; meaning
     *  clarified by the user 2026-11-04): "active" = **the request has not been
     *  decided yet**. It hides exactly the rows the inactive classifier flags
     *  (cancelled / approved / rejected, or the legacy D4 disabled edge) — NOT
     *  the ones you have merely read, which stay visible because reading a
     *  pending request leaves it pending. Never the badge. */
    public bool $activeOnly = false;

    /** The type filter ('' = all types). Server-owned; wire:model.live. */
    public string $typeFilter = '';

    /** Manage mode (notif-manage-hide): the filter row is replaced by the
     *  selection bar and the list renders ONLY the hideable rows, pre-ticked. */
    public bool $manageMode = false;

    /** The ticks posted by manage mode — an INTENT list; hideSelected()
     *  re-validates every id against the server-side gate before writing.
     *
     * @var array<int, int>
     */
    public array $selectedIds = [];

    /** The hidden shelf (footer `Hidden (N)` → Back returns to the inbox). */
    public bool $hiddenView = false;

    /** The last hide batch — the Undo toast's payload. Kept in COMPONENT state
     *  (not the DOM) so the 10 s `wire:poll` re-render can never wipe a live
     *  undo affordance; `dismissUndo()` (its timer) or `undoHide()` clears it.
     *
     * @var array<int, int>
     */
    public array $undoIds = [];

    public function mount(string $island = 'bell', string $pageKey = ''): void
    {
        $this->island = $island;
        $this->pageKey = $pageKey;
    }

    public function render(): View
    {
        $userId = (int) Auth::id();
        $rows = collect();
        $unreadTotal = 0;
        $unreadInView = 0;
        $hidden = collect();
        $hideable = collect();

        if ($userId > 0) {
            $base = Notification::query()->where('user_id', $userId);

            /* ONE FEED: the viewed slice IS the recipient's whole mailbox —
               the badge law (F-2) and the panel count are the same number. */
            $unreadInView = $unreadTotal;

            $rows = $this->resolveRequests(
                (clone $base)->orderByDesc('created_at')->get()
            );

            /* D5 (notif-new-request-ux): the badge = the BATCH-DERIVED
               actionable unread — the classifier's exact unread-filter
               predicate (`read_at === null && inactive_reason === null`) over
               the WHOLE mailbox collection; read_at is never stamped by the
               display path. */
            $unreadTotal = $unreadInView = $rows
                ->filter(fn (Notification $n) => $n->read_at === null
                    && $n->getAttribute('inactive_reason') === null)
                ->count();

            /* notif-manage-hide: the mailbox splits into the INBOX (hidden_at
               NULL) and the HIDDEN shelf (hidden_at stamped). The shelf rides
               the same resolved rows, so its rows compose identically. */
            $hidden = $rows->filter(fn (Notification $n) => $n->hidden_at !== null)->values();
            $rows = $rows->filter(fn (Notification $n) => $n->hidden_at === null)->values();

            /* The hideable set = the badge predicate's EXACT COMPLEMENT
               (read_or_inactive), computed over the INBOX only — the same
               server-side gate hideSelected() re-applies to posted ids. */
            $hideable = $rows->filter(fn (Notification $n) => $this->isHideableRow($n))->values();

            /* The VIEW filters (never touch the badge — the D5/F-2 law):
               unread-only → literal unread predicate; active-only → the SAME
               activation law the partial renders by; type filter → the row's
               stored type. Composable, all server-owned. */
            $rows = $rows
                ->when($this->unreadOnly, fn (Collection $c) => $c->filter(
                    fn (Notification $n) => $n->read_at === null
                        && $n->getAttribute('inactive_reason') === null
                ))
                ->when($this->activeOnly, fn (Collection $c) => $c->filter(
                    fn (Notification $n) => ! $this->isInactiveRow($n)
                ))
                ->when($this->typeFilter !== '', fn (Collection $c) => $c->filter(
                    fn (Notification $n) => $n->type === $this->typeFilter
                ))
                ->values();
        }

        return view('livewire.nav-notifications', [
            /* manage mode REPLACES the filter row by the selection bar, so the
               list it edits is the whole hideable inbox (never a filtered
               slice — "Select all" must mean all of them). */
            'rows' => $this->manageMode ? $hideable : $rows,
            'hiddenRows' => $hidden,
            'hideableCount' => $hideable->count(),
            'hiddenCount' => $hidden->count(),
            'unreadTotal' => $unreadTotal,
            'unreadInView' => $unreadInView,
        ]);
    }

    /**
     * The active-only predicate — MIRRORS the row partial's activation law
     * exactly (partials/notif-row.blade.php `$activation`): the classified
     * family rides the D15 reason; the legacy non-submitted family's D4 law
     * rides the missing/cancelled edge. One truth, two copies of intent
     * (display + filter) — both documented here and in the partial.
     */
    private function isInactiveRow(Notification $n): bool
    {
        $reason = $n->getAttribute('inactive_reason');
        if ($reason !== null) {
            return true;
        }

        if (! in_array($n->type, ['submitted', 'awaiting'], true)
            && $n->getAttribute('inactive_row') === true
            && $n->getAttribute('_display') === null) {
            return true;
        }

        return false;
    }

    /**
     * The HIDE gate — the badge predicate's exact complement. `inactive_reason`
     * is PHP-derived (resolveRequests), never a column, so this can only ever
     * be evaluated here (server-side) and never trusted from the client.
     */
    private function isHideableRow(Notification $n): bool
    {
        return $n->read_at !== null || $n->getAttribute('inactive_reason') !== null;
    }

    /**
     * The status a row is ABOUT when there is no request to read it from.
     *
     * A class-cancellation notice is written the moment a class is cancelled —
     * usually before the lecturer has submitted anything — so `request_id` is
     * null. Its subject is the cancelled occurrence, and that is what the click
     * behaviour needs to know (notif-click-behaviour, 2026-11-04).
     */
    private function classOnlyNotifStatus(Notification $n): ?string
    {
        if ($n->request_id !== null) {
            return null;    // request rows resolve through effectiveStatus()
        }

        return $n->class_session_id !== null && $n->link === '/my-timetable-ui'
            ? 'cancelled'
            : null;
    }

    /**
     * The user's own INBOX rows for the posted ids, RESOLVED (so the derived
     * `inactive_reason` exists) — the authorization + gate surface shared by
     * every hide path. Never trusts the client: foreign ids, already-hidden
     * ids and non-hideable ids simply fall out.
     *
     * @param  array<int, mixed>  $ids
     * @return Collection<int, Notification>
     */
    private function gateableInboxRows(array $ids): Collection
    {
        $userId = (int) Auth::id();
        $wanted = collect($ids)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($userId < 1 || $wanted->isEmpty()) {
            return collect();
        }

        $rows = Notification::query()
            ->where('user_id', $userId)
            ->whereNull('hidden_at')
            ->whereIn('id', $wanted->all())
            ->orderByDesc('created_at')
            ->get();

        return $this->resolveRequests($rows)
            ->filter(fn (Notification $n) => $this->isHideableRow($n))
            ->values();
    }

    /**
     * The inbox ids that MAY be hidden (manage mode's pre-tick + Select all).
     *
     * @return array<int, int>
     */
    private function hideableInboxIds(): array
    {
        return $this->gateableInboxRows(
            Notification::query()
                ->where('user_id', (int) Auth::id())
                ->whereNull('hidden_at')
                ->pluck('id')
                ->all()
        )->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Enter manage mode: every hideable inbox row arrives PRE-TICKED. */
    public function enterManage(): void
    {
        $this->hiddenView = false;
        $this->manageMode = true;
        $this->undoIds = [];
        $this->selectedIds = $this->hideableInboxIds();
    }

    /** Leave manage mode — nothing is written, the ticks are dropped. */
    public function exitManage(): void
    {
        $this->manageMode = false;
        $this->selectedIds = [];
    }

    /** Tick/untick one row (the id is re-gated at write time). */
    public function toggleSelect(int $id): void
    {
        $id = (int) $id;
        $selected = array_map('intval', $this->selectedIds);

        $this->selectedIds = in_array($id, $selected, true)
            ? array_values(array_filter($selected, fn (int $other) => $other !== $id))
            : array_values(array_unique([...$selected, $id]));
    }

    public function selectAllVisible(): void
    {
        $this->selectedIds = $this->hideableInboxIds();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    /**
     * Hide the ticked rows: RE-VALIDATED through the gate (a tampered or stale
     * id is dropped, never written), stamped `hidden_at = now()` — rows are
     * NEVER deleted — and kept as the Undo payload. Exits manage mode.
     */
    public function hideSelected(): void
    {
        $allowed = $this->gateableInboxRows($this->selectedIds)
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->manageMode = false;
        $this->selectedIds = [];

        if ($allowed === []) {
            $this->undoIds = [];

            return;
        }

        Notification::query()
            ->where('user_id', (int) Auth::id())
            ->whereIn('id', $allowed)
            ->update(['hidden_at' => now()]);

        $this->undoIds = $allowed;
    }

    /**
     * Put ONE row away without leaving the panel (notif-click-behaviour,
     * user 2026-11-04): clicking a notification whose related record is
     * COMPLETED hides that row in place instead of navigating — the class has
     * already happened, so there is nothing left to jump to.
     *
     * Same gate as manage mode (`gateableInboxRows`): the id is re-validated
     * server-side, a foreign / already-hidden / non-hideable id simply falls
     * out, and `hidden_at` is stamped — rows are NEVER deleted. No Undo here:
     * the shelf (`showHidden()` → Unhide) is the way back.
     */
    public function hideOne(int $id): void
    {
        $allowed = $this->gateableInboxRows([$id])
            ->pluck('id')->map(fn ($value) => (int) $value)->all();

        if ($allowed === []) {
            return;
        }

        Notification::query()
            ->where('user_id', (int) Auth::id())
            ->whereIn('id', $allowed)
            ->update(['hidden_at' => now()]);
    }

    /** Undo the last hide batch — the same rows return to the inbox. */
    public function undoHide(): void
    {
        $ids = array_map('intval', $this->undoIds);
        $this->undoIds = [];

        if ($ids === []) {
            return;
        }

        Notification::query()
            ->where('user_id', (int) Auth::id())
            ->whereIn('id', $ids)
            ->update(['hidden_at' => null]);
    }

    /** The Undo toast's timer/close — drops the affordance, writes nothing. */
    public function dismissUndo(): void
    {
        $this->undoIds = [];
    }

    /** Footer `Hidden (N)` → the shelf. */
    public function showHidden(): void
    {
        $this->manageMode = false;
        $this->selectedIds = [];
        $this->hiddenView = true;
    }

    /** The shelf's Back → the inbox (filters and scroll state survive). */
    public function showInbox(): void
    {
        $this->hiddenView = false;
    }

    /** Restore ONE hidden row to the inbox. */
    public function unhide(int $id): void
    {
        Notification::query()
            ->where('user_id', (int) Auth::id())
            ->whereKey((int) $id)
            ->whereNotNull('hidden_at')
            ->update(['hidden_at' => null]);
    }

    /** Restore EVERY hidden row (the shelf's `Unhide all`). */
    public function unhideAll(): void
    {
        Notification::query()
            ->where('user_id', (int) Auth::id())
            ->whereNotNull('hidden_at')
            ->update(['hidden_at' => null]);
    }

    /** Mark-all the user's mailbox read (the ZERO-badge path; the frozen law). */
    public function markAll(): void
    {
        Notification::query()
            ->where('user_id', (int) Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    /**
     * The lifecycle batch-resolve (round-2 🟡 N+1 law: NEVER a per-row find()
     * on a 10 s poll) — extended per SDD notif-new-request-ux §1/§2:
     *   - ONE `replacement_requests` batch whereIn + the eager loads
     *     (class_sessions/modules/users/lecturers/time_slots/venues only —
     *     the ONE-QUERY law unbroken);
     *   - ONE span join via the mapper's spansForRequests + the venue /
     *     semester batches — the composer NEVER runs a per-row span(),
     *     Venue::find, Semester::find or replacementStrings;
     *   - per-row `_display` attach for `submitted` rows (the composer) and
     *     `_inactive_reason` for the classified family.
     *
     * @param  Collection<int, Notification>  $rows
     * @return Collection<int, Notification>
     */
    private function resolveRequests(Collection $rows)
    {
        $requestIds = $rows->filter(fn (Notification $n) => $n->request_id !== null)
            ->map(fn (Notification $n) => (int) $n->request_id)
            ->unique()->values();

        if ($requestIds->isEmpty()) {
            /* R5: the staff line is COLUMN-driven (request_id / class_session_id),
               so a cancellation-only inbox still needs the class batch — the
               classifier's attributes are not set yet at this exit. */
            /* notif-click-behaviour (2026-11-04): the click needs to know what
               the row is ABOUT, and a cancellation inbox has no request to
               read that from — the class-only arm answers it. */
            $rows->each(function (Notification $n): void {
                $n->setAttribute('notif_status', $this->classOnlyNotifStatus($n));
            });

            return $this->attachStaffLines($rows, collect());
        }

        $requests = ReplacementRequest::query()
            ->whereIn('id', $requestIds)
            ->with(['classSession.module', 'replacementTimeSlot.venue', 'proposer.lecturer', 'approver.lecturer'])
            ->get()
            ->keyBy('id');

        /* §1's explicit batches — the composer reads ONLY these maps. */
        $mapper = app(ReplacementRequestMapper::class);
        $spans = $mapper->spansForRequests($requestIds->all());

        /* The venue batch covers BOTH the junction spans AND the legacy
           anchor fallback (the requests' eager replacementTimeSlot.venue —
           the displaySlot fallback branch rides the loaded relation). */
        $venueIds = collect($spans)->pluck('venue_id')->filter()->unique()->values()
            ->merge($requests->pluck('replacementTimeSlot.venue_id')->filter()->unique()->values())
            ->unique()->values();
        $venues = $venueIds->isEmpty()
            ? collect()
            : Venue::query()->whereIn('id', $venueIds)->get()->keyBy('id');

        $semesterIds = $requests->pluck('semester_id')->filter()->unique()->values();
        $semesters = $semesterIds->isEmpty()
            ? collect()
            : Semester::query()->whereIn('id', $semesterIds)->get()->keyBy('id');

        /* Legacy anchor fallback shape (design §3): a request with NO
         * junction rows composes from the LOADED replacementTimeSlot — the
         * eager `replacementTimeSlot.venue` never lazy-loads on the poll. */
        $anchorSpan = static fn (ReplacementRequest $request): ?array => $request->replacementTimeSlot === null
            ? null
            : [
                'week' => (int) $request->replacementTimeSlot->week_number,
                'day' => (int) $request->replacementTimeSlot->day_of_week,
                'venue_id' => (int) $request->replacementTimeSlot->venue_id,
                'start' => (string) $request->replacementTimeSlot->start_time,
                'end' => (string) $request->replacementTimeSlot->end_time,
                'count' => 1,
            ];

        foreach ($rows as $row) {
            /* The legacy lifecycle law (notifications-real-bridge D4) lives
               on for the NON-submitted family: the missing/cancelled request
               renders the row disabled. Other statuses keep that family's
               rows clickable with their own stored title/body (the
               notif-click-highlight both-clickable law). */
            $legacyStatus = $requests->has((int) $row->request_id)
                ? $requests->get((int) $row->request_id)->status
                : null;
            $row->setAttribute('inactive_row', $row->request_id !== null
                && ($legacyStatus === null || $legacyStatus === 'cancelled'));

            /* notif-click-behaviour (user, 2026-11-04): "update the navigation
               behaviour based on the notification's related class status" —
               so every row carries the RELATED RECORD's live status, resolved
               the same way the pages resolve it (derived Completed included,
               never a stored column). The click handler branches on it:
               completed → put the row away in place; approved / cancelled →
               land on My Replacements and flash the row; anything else keeps
               the row's own stored link. */
            $related = $requests->get((int) $row->request_id);
            $row->setAttribute('notif_status', $related !== null
                ? $mapper->effectiveStatus(
                    $related,
                    Carbon::parse((string) $semesters->get((int) $related->semester_id)->start_date)
                )
                : $this->classOnlyNotifStatus($row));

            /* §4's classifier (D15, amended 2026-11-04): the REASON, first-match
               cancelled > approved > rejected — `read` is deliberately ABSENT
               (reading is not a decision). The classified family =
               `submitted` (the user's parts 1–3 + 5/6) plus `awaiting`
               reminders — the decided-awaiting flip = the DELIBERATE D2
               side-effect (asserted in the receipts, logged in T9). */
            if ($row->request_id === null
                || ! in_array($row->type, ['submitted', 'awaiting'], true)) {
                continue;
            }

            $request = $requests->get((int) $row->request_id);
            $status = $request?->status;

            /* 2026-11-04 (the user's ruling): READING is not a decision. A read
               `submitted`/`awaiting` row whose request is still pending stays
               ACTIVE — clickable, and still composed (title with the ID, the
               staff line, the schedule) — so the notification remains a way
               back to the request. The badge still stops counting it (D5's
               predicate is `read_at === null && inactive_reason === null`) and
               the hide gate still allows it (`read_at !== null`), so only the
               ROW's life changed, never the count. The old `read` reason —
               "inactive because you have already read this request" — is gone
               with it; `read` can no longer be an inactive reason at all. */
            $reason = null;
            if ($status === null) {
                /* The missing row = data drift only (FK nullOnDelete); the
                   preserved 'cancelled' copy stays truthful (the in-app
                   delete path does not exist) — the documented edge. */
                $reason = 'cancelled';
            } elseif ($status === 'cancelled') {
                $reason = 'cancelled';
            } elseif ($status === 'approved') {
                $reason = 'approved';
            } elseif ($status === 'rejected') {
                $reason = 'rejected';
            }

            $row->setAttribute('inactive_reason', $reason);
            if ($reason !== null || $row->type !== 'submitted') {
                continue;
            }

            /* No `$request !== null` guard: `$status = $request?->status`, so a
               null request can only produce a null status, which the cancelled
               branch above already consumed via the `continue`. Reaching here
               therefore PROVES the request exists (PHPStan proved the old guard
               constant-true once the read branch went). */
            $row->setAttribute(
                '_display',
                $this->composeSubmitted($row, $request, $spans, $venues, $semesters, $anchorSpan)
            );
        }

        return $this->attachStaffLines($rows, $requests);
    }

    /**
     * R5 (notif-recipient-rulings, 2026-11-03): the "other party" staff line —
     * `Name (staff_id)` — for EVERY type in EVERY state. It is a computed
     * attribute on the row, never a column and never `_display` (which is only
     * attached to live `submitted` rows and dies on read).
     *
     * Two batched sources inside the island's ONE-QUERY law: the requests map
     * (proposer + approver, already eager-loaded) and ONE ClassSession batch for
     * the class-level rows (a cancellation). No per-row find().
     *
     * @param  Collection<int, Notification>  $rows
     * @param  Collection<int, ReplacementRequest>  $requests
     * @return Collection<int, Notification>
     */
    private function attachStaffLines(Collection $rows, Collection $requests)
    {
        $classIds = $rows->filter(fn (Notification $n) => $n->request_id === null && $n->class_session_id !== null)
            ->pluck('class_session_id')->unique()->values();

        $sessions = $classIds->isEmpty()
            ? collect()
            : ClassSession::query()->with(['lecturer.lecturer'])
                ->whereIn('id', $classIds->all())->get()->keyBy('id');

        foreach ($rows as $row) {
            $row->setAttribute('staff_line', $this->staffLine($row, $requests, $sessions));
        }

        return $rows;
    }

    /**
     * The staff-line rule (design §4.3):
     *  - request row → its proposer; when the reader IS the proposer, the actor
     *    (the PL who decided) — null while nobody has decided;
     *  - class row → the class lecturer; null for a self-only event (the owner
     *    cancelling their own class) and for an unusable session.
     *
     * @param  Collection<int, ReplacementRequest>  $requests
     * @param  Collection<int, ClassSession>  $sessions
     */
    private function staffLine(Notification $row, Collection $requests, Collection $sessions): ?string
    {
        $request = $row->request_id !== null ? $requests->get((int) $row->request_id) : null;

        if ($request !== null) {
            $subject = $request->proposer;
            if ($subject === null) {
                return null;
            }
            if ((int) $subject->id === (int) $row->user_id) {
                $actor = $request->approver;                 // the PL who decided

                return $actor !== null ? $this->staffLabel($actor) : null;
            }

            return $this->staffLabel($subject);
        }

        $session = $row->class_session_id !== null ? $sessions->get((int) $row->class_session_id) : null;
        $owner = $session?->lecturer;

        return $owner === null || (int) $owner->id === (int) $row->user_id ? null : $this->staffLabel($owner);
    }

    private function staffLabel(User $user): string
    {
        $staffId = $user->lecturer !== null ? (string) $user->lecturer->staff_id : '';

        return $staffId !== '' ? $user->name.' ('.$staffId.')' : (string) $user->name;
    }

    /**
    /**
     * The `submitted` composer (design §2) — the fail-open matrix per D6:
     * IDENTITY lines per-line omitted (a missing proposer drops line 1, a
     * missing classSession/module drops line 5); SCHEDULE lines all-or-
     * nothing with the WHOLE tip (a missing semester fail-opens the tip, the
     * desc still composes); time tokens from the RAW 24 h span times,
     * UNspaced en dash; D11 — the venue name null → the code alone; the
     * whole-tip fallback = `title — body`.
     *
     * `_display['tip']` carries the FACT lines (Staff / Schedule / Venue /
     * Subject); the dynamic Requested-at line rides the partial's T4
     * closures per the frozen T3/T4 split.
     *
     * @param  array<int, array{week: int, day: int, venue_id: int, start: string, end: string, count: int}>  $spans
     * @param  Collection<int, Venue>  $venues
     * @param  Collection<int, Semester>  $semesters
     * @param  \Closure(ReplacementRequest): (array{week: int, day: int, venue_id: int, start: string, end: string, count: int}|null)  $anchorSpan
     * @return array{title: string, subtitle: ?string, desc: string, aria: string, tip: ?string}
     */
    private function composeSubmitted(
        Notification $row,
        ReplacementRequest $request,
        array $spans,
        Collection $venues,
        Collection $semesters,
        \Closure $anchorSpan,
    ): array {
        $span = $spans[(int) $row->request_id] ?? $anchorSpan($request);
        $venue = $span !== null
            ? ($venues->has((int) $span['venue_id']) ? $venues->get((int) $span['venue_id']) : null)
            : null;

        /* 1) title: `New replacement request (ID #76)` — the ID is stored on
              the legacy rows too, so they surface it for free (D7). */
        $title = $row->request_id > 0
            ? 'New replacement request (ID #'.(int) $row->request_id.')'
            : (string) $row->title;

        /* 2) subtitle: `Name (staff_id)`; staff_id null → name alone; the
              missing proposer = no subtitle span at all. */
        $subtitle = null;
        if ($request->proposer !== null) {
            $staffId = $request->proposer->lecturer !== null
                ? (string) $request->proposer->lecturer->staff_id
                : null;
            $subtitle = $staffId !== '' && $staffId !== null
                ? $request->proposer->name.' ('.$staffId.')'
                : (string) $request->proposer->name;
        }

        /* The SCHEDULE facts (the mapper's ONE format source) — the semester
           row is REQUIRED for the tip's date (NO now() fallback); a missing
           semester nulls the strings and the WHOLE tip fail-opens while the
           desc (which needs no date) still composes. */
        $semStart = $semesters->has((int) $request->semester_id)
            ? Carbon::parse((string) $semesters->get((int) $request->semester_id)->start_date)
            : null;
        $strings = ($span !== null && $semStart !== null)
            ? app(ReplacementRequestMapper::class)->stringsFromSpan($span, $semStart, $venue)
            : null;

        /* 3) desc: `Week {w} · {day} · {start}–{end} · {venue.code}` from the
              RAW 24 h span times, UNspaced en dash, H:i (the pg time column
              arrives with seconds) — the ask's exact format. Fails open to
              the stored body. */
        $shortTime = static fn (string $t): string => substr($t, 0, 5);
        $desc = ($span !== null)
            ? 'Week '.$span['week'].' · '.ReplacementRequestMapper::dayName($span['day'])
                .' · '.$shortTime($span['start']).'–'.$shortTime($span['end'])
                .' · '.($venue !== null ? (string) $venue->room_code : '')
            : (string) $row->body;

        /* 4) aria: the composed title — subtitle — desc, the surviving parts
              joined out; ` (inactive)` rides the partial's suffix law. */
        $ariaParts = array_values(array_filter([$title, $subtitle, $desc], fn ($p) => $p !== null && $p !== ''));
        $aria = implode(' — ', $ariaParts);

        /* 5) tip: the FACT lines joined with the multiline engine's `\n`;
              line 1 (Staff) per-line omits on a missing proposer; line 5
              (Subject): from the eager classSession+module (NEVER classRow()
              on the poll); lines 3+4 ride $strings — all-or-nothing with the
              whole tip. The dynamic Requested-at line = the partial's
              closure-built insertion (the frozen T3/T4 split). */
        $tip = null;
        if ($strings !== null) {
            $lines = [];

            if ($request->proposer !== null) {
                $staffId = $request->proposer->lecturer !== null
                    ? (string) $request->proposer->lecturer->staff_id
                    : null;
                $lines[] = 'Staff: '.($staffId !== null && $staffId !== '' ? $staffId.' — ' : '').$request->proposer->name;
            }

            $lines[] = 'Proposed Schedule: Week '.$span['week']
                .' · '.Carbon::parse((string) $strings['date'])->format('j M Y')
                .' · '.$shortTime($span['start']).'–'.$shortTime($span['end']);

            $lines[] = 'Proposed Venue: '.$strings['venue']
                .($strings['venueName'] !== null ? ' — '.$strings['venueName'] : '');

            $session = $request->classSession;
            $module = $session !== null ? $session->module : null;
            if ($session !== null && $module !== null) {
                $lines[] = 'Subject: '.(string) $module->module_code
                    .' ('.(string) $session->session_type.') — '.(string) $module->module_name;
            }

            if (count($lines) >= 3) {
                $tip = implode("\n", $lines); // the partial inserts the Requested-at line 2
            }
        }

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'desc' => $desc,
            'aria' => $aria,
            'tip' => $tip,
        ];
    }
}
