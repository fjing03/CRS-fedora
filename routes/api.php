<?php

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
