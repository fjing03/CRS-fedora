<?php

namespace App\Support\Notifications;

use App\Models\ClassSession;
use App\Models\Notification;
use App\Models\ReplacementRequest;
use App\Models\Venue;
use App\Support\ClassTypeLabel;
use App\Support\TitleCase;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * NotificationTip — THE one tooltip vocabulary for the notification feed
 * (user UI update 2026-11-06, "Organise Notification Row Hover Tooltips").
 *
 * Pure formatting, no queries: the panel's island passes the maps it already
 * loaded (requests, sessions, spans, venues, semesters, exceptions/holidays), so
 * the tip costs no per-row read and the "one `replacement_requests` query per
 * render" law is untouched.
 *
 * Kinds (recognised by the tile type + the stored title — the same pair the row
 * partial uses) and the labels each one carries:
 *
 *   new_request     (submitted, pending)     Staff / Requested At / Subject & Cohort /
 *                                            Original (+reason) / Proposed / Request ID
 *   awaiting        (PL reminder)            same as new_request
 *   submitted_copy  (the proposer's own)     same, minus Staff
 *   class_cancelled (update + the sentence)  Subject / Cancelled Class Schedule / Reason
 *   class_updated   (approved, timetable)    Subject / Original (+reason) / Approved / ID
 *   approval_notice (approved, proposer)     same as class_updated
 *   rejected                                 Subject / Rejection Reason / Original / Proposed / ID
 *
 * Every line is omitted when its facts are missing (the user's rule), and a tip
 * needs at least two lines to exist at all — otherwise the row keeps the legacy
 * `title — body` fallback. A `data-tip` is TEXT: lines are joined with `\n`
 * (the shared engine renders textContent + pre-line), never HTML.
 */
final class NotificationTip
{
    /** The automatic rejection sentence the user approved (short form). */
    public const AUTO_REJECT_LABEL = 'Automatically Rejected by System';

    private const DAY_NAMES = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    /**
     * The kind of an ACTIVE row, from its frozen tile type + stored title.
     * `unknown` keeps the legacy fallback (a row we have no layout for).
     */
    public static function kind(string $type, string $title): string
    {
        $same = static fn (string $a, string $b): bool => strcasecmp(trim($a), trim($b)) === 0;

        return match (true) {
            $type === 'submitted' => 'new_request',
            $type === 'awaiting' => 'awaiting',
            $type === 'rejected' => 'rejected',
            $type === 'approved' && $same($title, 'Replacement approved') => 'approval_notice',
            $type === 'approved' => 'class_updated',
            $same($title, 'Replacement request submitted') => 'submitted_copy',
            $same($title, 'Scheduled class cancelled') => 'class_cancelled',
            default => 'unknown',
        };
    }

    /** Does this kind carry the `Requested At:` line (the partial inserts it)? */
    public static function wantsRequestedAt(string $kind): bool
    {
        return in_array($kind, ['new_request', 'awaiting', 'submitted_copy'], true);
    }

    /**
     * The composed tip, or null when the facts cannot make two lines.
     *
     * @param  array{week: int, day: int, venue_id: int, start: string, end: string, count: int}|null  $span
     * @param  string|null  $reason  the ORIGINAL occurrence's cause, already resolved
     *                               (`Public Holiday — Christmas Eve` / `Annual Leave` / null)
     * @param  string  $audience  `student` rows carry no Staff line; every other line is shared
     */
    public function compose(
        string $kind,
        ?ReplacementRequest $request,
        ?ClassSession $session,
        ?array $span,
        ?Venue $venue,
        ?CarbonInterface $semesterStart,
        ?string $reason,
        string $audience = 'lecturer',
        ?int $rowWeek = null,
    ): ?string {
        $lines = [];

        if ($kind === 'unknown') {
            return null;
        }

        $module = $session?->module;
        $code = $module !== null ? (string) $module->module_code : '';
        $name = $module !== null ? (string) $module->module_name : '';
        $typeLabel = $session !== null ? ClassTypeLabel::of((string) $session->session_type) : '';

        /* ── identity ────────────────────────────────────────────────────── */
        if ($audience !== 'student' && in_array($kind, ['new_request', 'awaiting'], true) && $request?->proposer !== null) {
            $staffId = $request->proposer->lecturer !== null ? (string) $request->proposer->lecturer->staff_id : '';
            $lines[] = 'Staff (Proposer): '.(string) $request->proposer->name
                .($staffId !== '' ? ' ('.$staffId.')' : '');
        }

        $subjectPrefix = match ($kind) {
            'new_request', 'awaiting', 'submitted_copy' => 'Subject & Cohort: ',
            default => 'Subject: ',
        };
        if ($code !== '') {
            $subject = $code.($typeLabel !== '' ? ' ('.$typeLabel.')' : '')
                .($name !== '' && ! self::sameCodeAndName($code, $name) ? ' — '.$name : '');
            if ($subjectPrefix === 'Subject & Cohort: ') {
                $cohorts = $this->cohortLabels($session);
                if ($cohorts !== []) {
                    $subject .= ' · '.implode(' & ', $cohorts);
                }
            }
            $lines[] = $subjectPrefix.$subject;
        }

        /* ── schedules ───────────────────────────────────────────────────── */
        $originalWeek = $request?->week_number;
        $originalReason = $reason;

        if ($kind === 'class_cancelled') {
            /* no request exists — the row's own session + week are the class */
            $week = $request !== null ? (int) $request->week_number : $rowWeek;
            $line = $this->scheduleLine('Cancelled Class Schedule', $week, $session, $semesterStart);
            if ($line !== null) {
                $lines[] = $line;
            }
            if ($reason !== null) {
                $lines[] = 'Reason: '.$reason;
            }
        } else {
            if ($originalWeek !== null && $session !== null) {
                /* 2026-11-10 (user): the tip names the same two schedules as the
                   pop-up tabs — Original Slot Schedule / Requested Slot Schedule.
                   The tip is BUILT on read, so every stored row follows. */
                $lines[] = $this->scheduleLine(
                    'Original Slot Schedule',
                    (int) $originalWeek,
                    $session,
                    $semesterStart,
                    $originalReason,
                );
            }
            if ($span !== null) {
                $lines[] = $this->spanLine(
                    in_array($kind, ['approval_notice', 'class_updated'], true)
                        ? 'Approved Replacement Schedule'
                        : 'Requested Slot Schedule',
                    $span,
                    $venue,
                    $semesterStart,
                );
            }
        }

        /* ── decision detail ─────────────────────────────────────────────── */
        if ($kind === 'rejected' && $request !== null) {
            $autoRejected = (string) $request->status === 'rejected' && $request->approver_id === null;
            $reasonText = $autoRejected
                ? self::AUTO_REJECT_LABEL
                : TitleCase::of($request->rejection_reason);
            if ($reasonText !== '') {
                /* the reason reads ABOVE the schedules — the user's item 4 order */
                $insertAt = 1;   /* after the Subject line */
                array_splice($lines, $insertAt, 0, ['Rejection Reason: '.$reasonText]);
            }
        }

        /* ── the identity tail ───────────────────────────────────────────── */
        if ($request !== null) {
            $lines[] = 'Request ID: #'.(int) $request->id;
        }

        $lines = array_values(array_filter($lines, static fn (?string $line): bool => $line !== null && $line !== ''));

        return count($lines) >= 2 ? implode("\n", $lines) : null;
    }

    /** A code used as its own module name (the seeded AMSE2003 row) prints once. */
    private static function sameCodeAndName(string $code, string $name): bool
    {
        return strcasecmp(trim($code), trim($name)) === 0;
    }

    /**
     * The cohorts' SHORT labels (`DFT2` = programme code + year), deduped — the
     * shape the shipped submitted tip uses.
     *
     * @return list<string>
     */
    private function cohortLabels(?ClassSession $session): array
    {
        if ($session === null) {
            return [];
        }

        /** @var list<string> $labels */
        $labels = $session->cohorts
            ->map(fn ($c): ?string => $c->programme !== null
                ? (string) $c->programme->programme_code.(int) $c->current_year
                : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $labels;
        /* the collection re-keys to 0..n-1 — a list for the implode below */
    }

    /** `Week 8 · Mon, 9 Nov 2026 · 11:00 AM ~ 1:00 PM @ B110` (+ the reason in parentheses). */
    private function scheduleLine(
        string $label,
        ?int $week,
        ?ClassSession $session,
        ?CarbonInterface $semesterStart,
        ?string $reason = null,
    ): ?string {
        if ($week === null || $week < 1 || $session === null || $semesterStart === null) {
            return null;
        }

        $day = (int) $session->day_of_week;
        $date = $semesterStart->copy()->addWeeks($week - 1)->addDays($day);
        $venue = $session->venue !== null ? (string) $session->venue->room_code : '';

        return $label.': '.$this->weekDayDate($week, $day, $date)
            .' · '.$this->times((string) $session->start_time, (string) $session->end_time)
            .($venue !== '' ? ' @ '.$venue : '')
            .($reason !== null && $reason !== '' ? ' ('.$reason.')' : '');
    }

    /**
     * @param  array{week: int, day: int, venue_id: int, start: string, end: string, count: int}  $span
     */
    private function spanLine(string $label, array $span, ?Venue $venue, ?CarbonInterface $semesterStart): ?string
    {
        $week = (int) $span['week'];
        $day = (int) $span['day'];

        if ($week < 1 || $semesterStart === null) {
            return null;
        }

        $date = $semesterStart->copy()->addWeeks($week - 1)->addDays($day);
        $code = $venue !== null ? (string) $venue->room_code : '';

        return $label.': '.$this->weekDayDate($week, $day, $date)
            .' · '.$this->times((string) $span['start'], (string) $span['end'])
            .($code !== '' ? ' @ '.$code : '');
    }

    private function weekDayDate(int $week, int $day, CarbonInterface $date): string
    {
        $dayName = self::DAY_NAMES[$day] ?? '';

        return 'Week '.$week.' · '.($dayName !== '' ? $dayName.', ' : '').$date->format('j M Y');
    }

    /** `11:00 AM ~ 1:00 PM` — 12-hour clocks, a tilde joiner, one space each side. */
    private function times(string $start, string $end): string
    {
        return Carbon::parse($start)->format('g:i A').' ~ '.Carbon::parse($end)->format('g:i A');
    }
}
