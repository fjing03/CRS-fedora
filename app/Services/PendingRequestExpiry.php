<?php

namespace App\Services;

use App\Actions\AutoRejectRequest;
use App\Models\ReplacementRequest;
use App\Models\Semester;
use App\Support\WorkingHours;
use Illuminate\Support\Carbon;

/**
 * THE auto-reject sweep (user brief "Auto-Reject Pending Requests", 2026-11-06).
 *
 * Reads every PENDING request, resolves its proposed start through the shared
 * mapper (the same week/day arithmetic the row prints), and asks the working-hour
 * clock whether the 3-working-hour window has closed. The ones that have are
 * rejected through `AutoRejectRequest`.
 *
 * Deliberately NOT a write-on-read: it runs from `php artisan
 * requests:auto-reject` (scheduled every 5 minutes), so browsing a page never
 * mutates the demo data. `--dry-run` answers "what would be rejected now?"
 * without writing.
 *
 * Only `pending` rows are touched — approved / rejected / cancelled / completed
 * rows are never re-read or re-stamped.
 */
final class PendingRequestExpiry
{
    public function __construct(private readonly ReplacementRequestMapper $mapper) {}

    /**
     * @return array{scanned: int, due: array<int, int>, rejected: array<int, int>}
     */
    public function sweep(bool $dryRun = false): array
    {
        /** @var array<int, Semester> $semesters */
        $semesters = Semester::query()->get()->keyBy('id')->all();
        /** @var array<int, WorkingHours> $calendars */
        $calendars = [];

        $requests = ReplacementRequest::query()
            ->where('status', 'pending')
            ->with(['classSession'])
            ->orderBy('id')
            ->get();

        $due = [];
        $rejected = [];
        $now = now();

        foreach ($requests as $request) {
            $semester = $semesters[(int) $request->semester_id] ?? null;

            if ($semester === null) {
                continue; // unreadable semester: never guess a deadline
            }

            $start = Carbon::parse((string) $semester->start_date);
            $proposedStart = $this->mapper->proposedStart($request, $start);

            if ($proposedStart === null) {
                continue; // no readable span: never guess a deadline
            }

            $calendar = $calendars[(int) $semester->id] ??= new WorkingHours($semester);

            if (! $calendar->isTooSoon($now, $proposedStart)) {
                continue; // more than 3 working hours left → stays pending
            }

            $due[] = (int) $request->id;

            if ($dryRun) {
                continue;
            }

            if (app(AutoRejectRequest::class)((int) $request->id)) {
                $rejected[] = (int) $request->id;
            }
        }

        return ['scanned' => $requests->count(), 'due' => $due, 'rejected' => $rejected];
    }
}
