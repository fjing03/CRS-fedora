<?php

use App\Livewire\CohortTimetable;
use App\Livewire\MyTimetable;
use App\Livewire\StudentMyTimetable;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::get('/login/student', function () {
    return view('auth.login-student');
})->name('login.student');

Route::get('/login/staff', function () {
    return view('auth.login-staff');
})->name('login.staff');

/*
|--------------------------------------------------------------------------
| UI pages (SDD: wire-backend-into-refactored-ui — D10 branch)
|--------------------------------------------------------------------------
|
| Single-point branch: when mock_fallback is off AND the Livewire component
| class exists, the route serves the component; otherwise the untouched
| legacy design template. Identical middleware either way, auth FIRST (D3)
| so guests get the FR 4.14 login redirect before role checks run.
|
*/

$uiPages = [
    '/my-timetable-ui' => [
        'component' => MyTimetable::class,
        'legacy' => 'ui-design-templates.MyTimetable-UI-design-template',
        'nav' => 'my-timetable',
        'mw' => ['auth', 'role:lecturer'],
    ],
    '/cohort-timetable-ui' => [
        'component' => CohortTimetable::class,
        'legacy' => 'ui-design-templates.CohortTimetable-UI-design-template',
        'nav' => 'cohort-timetables',
        'mw' => ['auth'],
    ],
    '/student-my-timetable-ui' => [
        'component' => StudentMyTimetable::class,
        'legacy' => 'ui-design-templates.student-my-timetable-UI-design-template',
        'nav' => 'my-timetable',
        'mw' => ['auth', 'role:student'],
    ],
    '/replacement-history-ui' => [
        'component' => 'App\Livewire\ReplacementHistory',
        'legacy' => 'ui-design-templates.replacement-history-UI-design-template',
        'nav' => 'replacement-history',
        'mw' => ['auth', 'role:student'],
    ],
    '/replacement-home-ui' => [
        'component' => 'App\Livewire\ReplacementHome',
        'legacy' => 'ui-design-templates.replacement-home-UI-design-template',
        'nav' => 'replacement-arrangement',
        'mw' => ['auth', 'role:lecturer'],
    ],
    '/replacement-arrangement' => [
        'component' => 'App\Livewire\ReplacementArrangement',
        'legacy' => 'ui-design-templates.replacement-arrangement-UIdesign-template',
        'nav' => 'replacement-arrangement',
        'mw' => ['auth', 'role:lecturer'],
    ],
    '/my-request-history-ui' => [
        'component' => 'App\Livewire\MyRequestHistory',
        'legacy' => 'ui-design-templates.my-request-history-UI-design-template',
        'nav' => 'replacement-history',
        'mw' => ['auth', 'role:lecturer'],
    ],
    '/venue-timetable-ui' => [
        'component' => 'App\Livewire\VenueTimetable',
        'legacy' => 'ui-design-templates.venue-timetable-UI-design-template',
        'nav' => 'venue-timetable',
        'mw' => ['auth', 'role:lecturer'],
    ],
    '/request-approval-ui' => [
        'component' => 'App\Livewire\RequestApproval',
        'legacy' => 'ui-design-templates.request-approval-UI-design-template',
        'nav' => 'request-approval',
        'mw' => ['auth', 'pl'],
    ],
];

foreach ($uiPages as $uri => $page) {
    $useComponent = ! config('app.mock_fallback') && class_exists($page['component']);

    $action = $useComponent
        ? $page['component']
        : fn () => view($page['legacy'], ['activeNav' => $page['nav']]);

    Route::get($uri, $action)->middleware($page['mw']);
}

Route::middleware(['auth'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});
