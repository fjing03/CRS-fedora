<?php

/*
| ORPHANED API ROUTES — commented out 2026-10 (SDD: debt-api-routes-agents-md)
|
| These 8 read endpoints (ApiReadController) have zero production callers —
| no page, script, or gated test hits /api/v1. They are COMMENTED, not
| deleted, so the booking write path (SDD Wave 3b Slice B) can re-enable
| them by uncommenting. Uncomment the two `use` lines together with the
| routes below. Legacy e2e specs (api-wiring.spec.js, full-suite.spec.js)
| still reference these paths and are parked per standing decision.
|
| To re-enable: uncomment everything below.
|

use App\Http\Controllers\Api\ApiReadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/semester', [ApiReadController::class, 'semester']);
    Route::get('/meta/cohorts', [ApiReadController::class, 'cohorts']);
    Route::get('/timetable/my', [ApiReadController::class, 'myTimetable']);
    Route::get('/timetable/cohort', [ApiReadController::class, 'timetableCohort']);
    Route::get('/timetable/student', [ApiReadController::class, 'timetableStudent']);
    Route::get('/arrangement/slots', [ApiReadController::class, 'arrangementSlots']);
    Route::get('/requests/my', [ApiReadController::class, 'myRequests']);
    Route::get('/requests/conflicts', [ApiReadController::class, 'conflicts']);
});

*/
