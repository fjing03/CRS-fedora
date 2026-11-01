<?php

namespace App\Livewire;

use App\Models\Notification;
use App\Models\ReplacementRequest;
use App\Models\Semester;
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
 *   variant 'bell'  → the unread TOTAL badge (the updateNotifHeaderState law)
 *   variant 'panel' → the WHOLE panel body server-side (the head pill + the
 *                     mark-all visibility + the filter + the list + the caught-up
 *                     swap), from the SAME unread total.
 * Both `wire:poll.10s` (updates without a page refresh — the user's ruling);
 * the read path stays the durable POST (D3) — the rows are NATIVE anchors.
 * ONE FEED (notif-unified-feed — the user's 2026-10-29 ruling, superseding
 * D2's AD-2 page-category tabs): the panel shows the RECIPIENT-WIDE feed on
 * every page — same rows, order and badge everywhere. The lifecycle law:
 * rows never delete; an inactive link target renders the row disabled + the
 * user's tooltip.
 *
 * SDD notif-new-request-ux (design §1/§2/§4): `submitted` rows (and,
 * deliberately, `awaiting` reminders — the D2 side-effect) compose
 * SERVER-SIDE per render; the inactive classifier classifies the REASON
 * (first-match cancelled > approved > rejected > read), not a boolean — a
 * decided SUBMITTED row renders inactive (the real-bridge D4
 * links-stay-alive law OVERTURNED for `submitted` rows), while non-submitted
 * request-linked rows stay in notif-click-highlight's both-clickable law.
 * read_at is never stamped by the display path; the badge = the BATCH-DERIVED
 * actionable unread (the standalone COUNT query is gone — D5) and the
 * composer NEVER runs a per-row span()/Venue::find/Semester::find
 * (the ONE-`replacement_requests`-query law, test :253-274).
 */
class NavNotifications extends Component
{
    public string $variant = 'bell';

    /** Kept ACCEPTED for mount compatibility (the panel mounts pass the page
     *  key); it no longer selects anything — one feed per recipient, not per
     *  page. */
    public string $pageKey = '';

    /** The unread-only toggle (server-owned so the poll re-render preserves it). */
    public bool $unreadOnly = true;

    public function mount(string $variant = 'bell', string $pageKey = ''): void
    {
        $this->variant = $variant;
        $this->pageKey = $pageKey;
    }

    public function render(): View
    {
        $userId = (int) Auth::id();
        $rows = collect();
        $unreadTotal = 0;
        $unreadInView = 0;

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

            $rows = $this->unreadOnly
                ? $rows->filter(fn (Notification $n) => $n->read_at === null
                    && $n->getAttribute('inactive_reason') === null)
                : $rows->values();
        }

        return view('livewire.nav-notifications', [
            'rows' => $rows,
            'unreadTotal' => $unreadTotal,
            'unreadInView' => $unreadInView,
        ]);
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
            return $rows;
        }

        $requests = ReplacementRequest::query()
            ->whereIn('id', $requestIds)
            ->with(['classSession.module', 'replacementTimeSlot.venue', 'proposer.lecturer'])
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

            /* §4's classifier (D15): the REASON, first-match
               cancelled > approved > rejected > read. The classified family =
               `submitted` (the user's parts 1–3 + 5/6) plus `awaiting`
               reminders — the decided-awaiting flip = the DELIBERATE D2
               side-effect (asserted in the receipts, logged in T9). */
            if ($row->request_id === null
                || ! in_array($row->type, ['submitted', 'awaiting'], true)) {
                continue;
            }

            $request = $requests->get((int) $row->request_id);
            $status = $request?->status;

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
            } elseif ($row->read_at !== null) {
                $reason = 'read';
            }

            $row->setAttribute('inactive_reason', $reason);
            if ($reason !== null || $row->type !== 'submitted') {
                continue;
            }

            if ($request !== null) {
                $row->setAttribute(
                    '_display',
                    $this->composeSubmitted($row, $request, $spans, $venues, $semesters, $anchorSpan)
                );
            }
        }

        return $rows;
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
