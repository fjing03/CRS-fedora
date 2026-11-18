<?php

namespace Tests\Feature;

use App\Livewire\CohortTimetable;
use App\Models\ClassException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\CancelledSlotFixture;
use Tests\TestCase;

final class CancelClassReceiptTest extends TestCase
{
    use DatabaseTransactions;

    public function test_single_cancellation_returns_the_written_receipt_without_reading_it_back(): void
    {
        CancelledSlotFixture::freezeClock();

        try {
            $fixture = new CancelledSlotFixture;
            $fixture->build();
            $this->actingAs($fixture->users['a']);
            $sessionId = $fixture->sessions['src']->id;
            $week = CancelledSlotFixture::SOURCE_WEEK;
            $reason = CancelledSlotFixture::SOURCE_REASON;
            $timetable = new CohortTimetable;

            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $result = $timetable->cancelClass((string) $sessionId, $week, $reason);
                $queries = array_column(DB::getQueryLog(), 'query');
            } finally {
                DB::disableQueryLog();
                DB::flushQueryLog();
            }

            $occurrence = ClassException::query()->where('class_session_id', $sessionId)->where('week_number', $week);
            $exception = $occurrence->sole();
            $this->assertSame(['ok' => true, 'exceptionId' => (int) $exception->id], $result);
            $this->assertSame($reason, $exception->reason);

            $this->assertSame(
                ['ok' => false, 'hint' => 'This class is already cancelled.'],
                $timetable->cancelClass((string) $sessionId, $week, $reason),
            );
            $this->assertSame((int) $exception->id, (int) $occurrence->sole()->id);

            $insertFound = false;
            foreach ($queries as $sql) {
                if (str_starts_with($sql, 'insert into "class_exceptions"')) {
                    $insertFound = true;
                }
                if ($insertFound && str_starts_with($sql, 'select ')) {
                    $this->assertStringNotContainsString('from "class_exceptions"', $sql, 'The written receipt must not be re-read.');
                }
            }
            $this->assertTrue($insertFound, 'The query trace must contain the receipt insert.');
        } finally {
            CancelledSlotFixture::unfreezeClock();
        }
    }
}
