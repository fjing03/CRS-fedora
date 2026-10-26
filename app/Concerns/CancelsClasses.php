<?php

namespace App\Concerns;

use App\Actions\CancelClass;
use App\Models\AuditLog;
use App\Models\ClassException;
use App\Models\ClassSession;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Renderless;

/**
 * Bridge between the client Cancel Class flow and the CancelClass action
 * (class-cancel-db-persist). One shared trait for every page surface that
 * can cancel or undo — MyTimetable, CohortTimetable, VenueTimetable and
 * ReplacementHome — called via `window.Livewire.first().call(...)` (the
 * submitBlocks precedent: renderless array responses, no HTTP route).
 */
trait CancelsClasses
{
    /**
     * Cancel the caller's own occurrence for one week. Ownership-scoped
     * resolve: a fabricated id resolves to null. The server response is
     * the truth.
     *
     * @return array{ok: bool, exceptionId?: int, hint?: string}
     */
    #[Renderless]
    public function cancelClass(string $sessionId, int $weekNumber, string $reason, ?string $otherDetail = null): array
    {
        $session = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->find((int) $sessionId);
        if ($session === null) {
            return ['ok' => false, 'hint' => 'Class not found among your classes.'];
        }

        if (! CancelClass::validReason($reason)) {
            return ['ok' => false, 'hint' => 'Please select a cancellation reason.'];
        }

        $cancelled = (new CancelClass)(auth()->user(), $session, $weekNumber, $reason, $otherDetail);
        if (! $cancelled) {
            return ['ok' => false, 'hint' => 'This class is already cancelled.'];
        }

        $exception = ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', (int) $weekNumber)
            ->first();

        return ['ok' => true, 'exceptionId' => (int) $exception->id];
    }

    /**
     * The true inverse: delete the caller's own exception row (ownership
     * via the session join) and audit the erasure with the reason it
     * removed. Reads the row BEFORE the delete, inside one transaction.
     *
     * @return array{ok: bool}
     */
    #[Renderless]
    public function cancelClassUndo(string $sessionId, int $weekNumber): array
    {
        $session = ClassSession::query()
            ->where('lecturer_id', auth()->id())
            ->find((int) $sessionId);
        if ($session === null) {
            return ['ok' => false];
        }

        $exception = ClassException::query()
            ->where('class_session_id', $session->id)
            ->where('week_number', (int) $weekNumber)
            ->first();
        if ($exception === null) {
            return ['ok' => false];
        }

        $erasedReason = (string) $exception->reason;

        DB::transaction(function () use ($exception): void {
            $details = $exception->toArray();
            $exception->delete();

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'class_cancel_undone',
                'details' => $details,
            ]);
        });

        return ['ok' => true];
    }
}
