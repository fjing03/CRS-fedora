<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\Cohort;
use App\Models\Lecturer;
use App\Models\ReplacementRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\MatrixIntersectionEngine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ApiReadController extends Controller
{
    private function resolveUser(string $role): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        if ($user && $role === 'lecturer' && $user->isLecturer()) {
            return $user;
        }

        if ($user && $role === 'student' && $user->isStudent()) {
            return $user;
        }

        if ($role === 'lecturer') {
            /** @var User|null */
            return Lecturer::where('staff_id', '5425')->first()?->user;
        }

        if ($role === 'student') {
            /** @var User|null */
            return Student::first()?->user;
        }

        return null;
    }

    private const STUDENT_COUNTS = [
        'DFT1(S1)G1' => 30,
        'DFT2(S1)G1' => 28,
        'DSF1(S1)G1' => 24,
        'DSF2(S1)G1' => 22,
        'RSD1(S1)G1' => 18,
        'RSD2(S1)G1' => 16,
        'RSD2(S1)G2' => 16,
        'RSD2(S1)G3' => 15,
        'RSD3(S1)G1' => 14,
        'RSD3(S1)G2' => 14,
        'RSD3(S1)G3' => 13,
        'RAF2(S3)G2' => 12,
        'RAF2(S3)G4' => 10,
        'RBU1(S1)G1' => 20,
    ];

    public function semester(): JsonResponse
    {
        $semester = DB::table('semesters')->first();

        $holidays = DB::table('holidays')
            ->where('semester_id', $semester->id)
            ->get()
            ->map(fn ($h) => [
                'week' => (int) $h->week_number,
                'dayIndex' => (int) $h->day_of_week,
                'label' => $h->label,
            ]);

        $start = Carbon::parse($semester->start_date);
        $end = Carbon::parse($semester->end_date);

        $chipText = sprintf(
            '%s · %s ~ %s',
            $semester->label,
            $start->format('d-M-Y'),
            $end->format('d-M-Y')
        );

        return response()->json([
            'semester' => [
                'label' => $semester->label,
                'startDate' => $start->format('Y-m-d'),
                'endDate' => $end->format('Y-m-d'),
                'weeks' => (int) $semester->week_count,
                'chipText' => $chipText,
            ],
            'holidays' => $holidays,
        ]);
    }

    public function cohorts(): JsonResponse
    {
        $cohorts = Cohort::with('programme.faculty')->get();

        $mapped = $cohorts->map(function ($cohort) {
            $code = sprintf(
                '%s%d(S%d)G%d',
                $cohort->programme->programme_code,
                $cohort->current_year,
                $cohort->semester,
                $cohort->tutorial_group
            );

            return [
                'code' => $code,
                'programme' => $cohort->programme->programme_name,
                'year' => (int) $cohort->current_year,
                'semester' => (int) $cohort->semester,
                'group' => (int) $cohort->tutorial_group,
                'academicYear' => $cohort->academic_year,
                'intake' => $cohort->intake,
                'faculty' => $cohort->programme->faculty->faculty_code,
                'studentCount' => self::STUDENT_COUNTS[$code] ?? 10,
            ];
        });

        return response()->json(['cohorts' => $mapped]);
    }

    public function myRequests(): JsonResponse
    {
        $user = $this->resolveUser('lecturer');

        $semester = DB::table('semesters')->first();
        $semesterStart = Carbon::parse($semester->start_date);

        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $requests = DB::table('replacement_requests')
            ->where('proposer_id', $user->id)
            ->join('class_sessions', 'replacement_requests.class_session_id', '=', 'class_sessions.id')
            ->join('modules', 'class_sessions.module_id', '=', 'modules.id')
            ->join('venues', 'class_sessions.venue_id', '=', 'venues.id')
            ->leftJoin('users', 'replacement_requests.approver_id', '=', 'users.id')
            ->leftJoin('time_slots', 'replacement_requests.replacement_time_slot_id', '=', 'time_slots.id')
            ->leftJoin('venues as rtv', 'time_slots.venue_id', '=', 'rtv.id')
            ->select(
                'replacement_requests.id',
                'replacement_requests.class_session_id',
                'replacement_requests.submitted_at',
                'replacement_requests.week_number',
                'replacement_requests.status',
                'replacement_requests.rejection_reason',
                'replacement_requests.remarks',
                'replacement_requests.decided_at',
                'modules.module_code',
                'modules.module_name',
                'class_sessions.session_type',
                'class_sessions.day_of_week',
                'class_sessions.start_time',
                'class_sessions.end_time',
                'venues.room_code',
                'time_slots.week_number as rt_week_number',
                'time_slots.day_of_week as rt_day_of_week',
                'time_slots.start_time as rt_start_time',
                'time_slots.end_time as rt_end_time',
                'rtv.room_code as rt_room_code',
                'users.name as reviewer_name'
            )
            ->get();

        $mapped = $requests->map(function ($row) use ($semesterStart, $dayNames) {
            $classDate = $semesterStart->copy()
                ->addDays(($row->week_number - 1) * 7)
                ->addDays($row->day_of_week)
                ->format('Y-m-d');

            $classDay = $dayNames[$row->day_of_week];

            $startParts = explode(':', $row->start_time);
            $endParts = explode(':', $row->end_time);
            $timeStart = $startParts[0].':'.$startParts[1];
            $timeEnd = $endParts[0].':'.$endParts[1];

            $startCarbon = Carbon::parse($row->start_time);
            $endCarbon = Carbon::parse($row->end_time);
            $duration = abs($endCarbon->diffInHours($startCarbon));

            $cohorts = DB::table('session_cohorts')
                ->where('class_session_id', $row->class_session_id)
                ->join('cohorts', 'session_cohorts.cohort_id', '=', 'cohorts.id')
                ->join('programmes', 'cohorts.programme_id', '=', 'programmes.id')
                ->select('cohorts.current_year', 'cohorts.semester', 'cohorts.tutorial_group', 'programmes.programme_code')
                ->get()
                ->map(fn ($c) => sprintf('%s%d(S%d)G%d', $c->programme_code, $c->current_year, $c->semester, $c->tutorial_group))
                ->values();

            $cohortCounts = $cohorts->mapWithKeys(fn ($code) => [$code => self::STUDENT_COUNTS[$code] ?? 10]);
            $totalStudents = $cohortCounts->sum();

            $replacementDate = null;
            $replacementTime = null;
            $replacementVenue = null;

            if ($row->rt_week_number !== null) {
                $replacementDate = $semesterStart->copy()
                    ->addDays(($row->rt_week_number - 1) * 7)
                    ->addDays($row->rt_day_of_week)
                    ->format('Y-m-d');

                $rtStartParts = explode(':', $row->rt_start_time);
                $rtEndParts = explode(':', $row->rt_end_time);
                $replacementTime = $rtStartParts[0].':'.$rtStartParts[1].' – '.$rtEndParts[0].':'.$rtEndParts[1];

                $replacementVenue = $row->rt_room_code;
            }

            return [
                'id' => $row->id,
                'requestedAt' => Carbon::parse($row->submitted_at)->format('Y-m-d\TH:i:s'),
                'courseCode' => $row->module_code,
                'courseName' => $row->module_name,
                'classType' => $row->session_type,
                'classDate' => $classDate,
                'classDay' => $classDay,
                'timeStart' => $timeStart,
                'timeEnd' => $timeEnd,
                'duration' => $duration,
                'venue' => $row->room_code,
                'totalStudents' => $totalStudents,
                'cohortCounts' => $cohorts->count() >= 2 ? $cohortCounts->toArray() : null,
                'cohorts' => $cohorts->toArray(),
                'status' => ucfirst($row->status),
                'rejectionReason' => $row->rejection_reason,
                'remarks' => $row->remarks,
                'replacementDate' => $replacementDate,
                'replacementTime' => $replacementTime,
                'replacementVenue' => $replacementVenue,
                'reviewedBy' => $row->reviewer_name,
                'reviewedAt' => $row->decided_at ? Carbon::parse($row->decided_at)->format('Y-m-d\TH:i:s') : null,
            ];
        });

        return response()->json(['requests' => $mapped]);
    }

    public function conflicts(): JsonResponse
    {
        $user = $this->resolveUser('lecturer');

        $semester = DB::table('semesters')->first();
        $semesterStart = Carbon::parse($semester->start_date);
        $semesterId = $semester->id;

        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $reasonMap = [
            'public_holiday' => 'Public Holiday',
            'annual_leave' => 'Annual Leave',
            'medical_leave' => 'Medical Leave',
            'official_event' => 'Official Event',
            'emergency_leave' => 'Emergency Leave',
        ];

        $sessions = ClassSession::where('lecturer_id', $user->id)
            ->with(['module', 'venue', 'cohorts.programme'])
            ->get();

        $sessionIds = $sessions->pluck('id');

        $exceptions = DB::table('class_exceptions')
            ->whereIn('class_session_id', $sessionIds)
            ->get()
            ->keyBy(fn ($e) => $e->class_session_id.'_'.$e->week_number);

        $holidays = DB::table('holidays')
            ->where('semester_id', $semesterId)
            ->get()
            ->keyBy(fn ($h) => $h->week_number.'_'.$h->day_of_week);

        $conflictMap = [];

        foreach ($sessions as $session) {
            foreach ($holidays as $holiday) {
                if ((int) $holiday->day_of_week !== (int) $session->day_of_week) {
                    continue;
                }

                $key = $session->id.'_'.$holiday->week_number;

                if (isset($conflictMap[$key])) {
                    continue;
                }

                $conflictMap[$key] = [
                    'session' => $session,
                    'week_number' => (int) $holiday->week_number,
                    'reason' => $holiday->label,
                ];
            }
        }

        foreach ($exceptions as $exception) {
            $key = $exception->class_session_id.'_'.$exception->week_number;

            $session = $sessions->firstWhere('id', $exception->class_session_id);
            if (! $session) {
                continue;
            }

            $conflictMap[$key] = [
                'session' => $session,
                'week_number' => (int) $exception->week_number,
                'reason' => $reasonMap[$exception->reason] ?? $exception->reason,
            ];
        }

        $mapped = collect($conflictMap)->map(function ($conflict) use ($semesterStart, $dayNames) {
            $session = $conflict['session'];
            $weekNumber = $conflict['week_number'];

            $classDate = $semesterStart->copy()
                ->addDays(($weekNumber - 1) * 7)
                ->addDays((int) $session->day_of_week)
                ->format('Y-m-d');

            $classDay = $dayNames[(int) $session->day_of_week];

            $startParts = explode(':', $session->start_time);
            $endParts = explode(':', $session->end_time);
            $timeStart = $startParts[0].':'.$startParts[1];
            $timeEnd = $endParts[0].':'.$endParts[1];

            $startCarbon = Carbon::parse($session->start_time);
            $endCarbon = Carbon::parse($session->end_time);
            $duration = abs($endCarbon->diffInHours($startCarbon));

            $cohortCodes = $session->cohorts->map(fn ($c) => sprintf(
                '%s%d(S%d)G%d',
                $c->programme->programme_code,
                $c->current_year,
                $c->semester,
                $c->tutorial_group
            ))->values();

            $totalStudents = $cohortCodes->map(fn ($code) => self::STUDENT_COUNTS[$code] ?? 10)->sum();

            return [
                'id' => $session->id,
                'code' => $session->module->module_code,
                'name' => $session->module->module_name,
                'type' => $session->session_type,
                'date' => $classDate,
                'day' => $classDay,
                'timeStart' => $timeStart,
                'timeEnd' => $timeEnd,
                'duration' => $duration,
                'venue' => $session->venue->room_code,
                'totalStudents' => $totalStudents,
                'cohorts' => $cohortCodes->toArray(),
                'conflictReason' => $conflict['reason'],
            ];
        })->values();

        return response()->json(['conflictedClasses' => $mapped]);
    }

    public function myTimetable(): JsonResponse
    {
        $user = $this->resolveUser('lecturer');

        $semester = DB::table('semesters')->first();
        $semesterStart = Carbon::parse($semester->start_date);
        $weekCount = (int) $semester->week_count;

        $sessions = ClassSession::where('lecturer_id', $user->id)
            ->with(['module', 'venue', 'cohorts.programme'])
            ->get();

        $requests = ReplacementRequest::where('proposer_id', $user->id)
            ->with(['classSession.module', 'replacementTimeSlot'])
            ->get();

        $eventsByWeek = [];
        for ($w = 0; $w < $weekCount; $w++) {
            $eventsByWeek[(string) $w] = [];
        }

        foreach ($sessions as $session) {
            [$startH, $startM] = array_map('intval', explode(':', $session->start_time));
            [$endH, $endM] = array_map('intval', explode(':', $session->end_time));

            $startIdx = (int) (($startH - 8) * 60 + $startM) / 30;
            $endIdx = (int) (($endH - 8) * 60 + $endM) / 30;

            $cohortCodes = $session->cohorts->map(fn ($c) => sprintf(
                '%s%d(S%d)G%d',
                $c->programme->programme_code,
                $c->current_year,
                $c->semester,
                $c->tutorial_group
            ))->values();

            $studentCounts = $cohortCodes->map(fn ($code) => self::STUDENT_COUNTS[$code] ?? 10);

            $event = [
                'di' => $session->day_of_week,
                'start' => $startIdx,
                'end' => $endIdx,
                'code' => $session->module->module_code,
                'type' => $session->session_type,
                'venue' => $session->venue->room_code,
                'lecturer' => $user->name,
                'cohort' => $cohortCodes->join(' + '),
                'cohorts' => $cohortCodes->toArray(),
                'status' => 'normal',
                'name' => $session->module->module_name,
                'remarks' => '',
            ];

            if ($studentCounts->count() === 1) {
                $event['studentCount'] = $studentCounts->first();
            } else {
                $event['studentCounts'] = $studentCounts->toArray();
            }

            for ($w = 0; $w < $weekCount; $w++) {
                $eventsByWeek[(string) $w][] = $event;
            }
        }

        foreach ($requests as $req) {
            $weekIdx = $req->week_number - 1;
            if ($weekIdx < 0 || $weekIdx >= $weekCount) {
                continue;
            }

            $cs = $req->classSession;
            [$sH, $sM] = array_map('intval', explode(':', $cs->start_time));
            [$eH, $eM] = array_map('intval', explode(':', $cs->end_time));
            $sIdx = (int) (($sH - 8) * 60 + $sM) / 30;
            $eIdx = (int) (($eH - 8) * 60 + $eM) / 30;

            foreach ($eventsByWeek[(string) $weekIdx] as &$ev) {
                if ($ev['di'] !== $cs->day_of_week || $ev['start'] !== $sIdx || $ev['end'] !== $eIdx || $ev['code'] !== $cs->module->module_code) {
                    continue;
                }

                if ($req->status === 'pending') {
                    $ev['status'] = 'pending';
                    $ev['requestedAt'] = $req->submitted_at->format('d M Y, g:i A');
                    $ev['requestedBy'] = $user->name;
                } else {
                    $ev['status'] = 'replacement';
                    $rts = $req->replacementTimeSlot;
                    $rtsDate = $semesterStart->copy()
                        ->addWeeks($rts->week_number - 1)
                        ->addDays($rts->day_of_week);
                    $ev['remarks'] = $rtsDate->format('d-M-Y');
                }
                break;
            }
            unset($ev);
        }

        return response()->json([
            'myTimetable' => [
                'seedWeek' => 11,
                'eventsByWeek' => (object) $eventsByWeek,
            ],
        ]);
    }

    /** @return list<array{week: int, event: array<string, mixed>}> */
    private function cohortBaseEvents(int $cohortId): array
    {
        $semester = DB::table('semesters')->first();
        $weekCount = (int) $semester->week_count;

        $sessions = ClassSession::whereHas('cohorts', fn ($q) => $q->where('cohorts.id', $cohortId))
            ->with(['module', 'venue', 'cohorts.programme', 'lecturer'])
            ->get();

        $events = [];

        foreach ($sessions as $session) {
            [$startH, $startM] = array_map('intval', explode(':', $session->start_time));
            [$endH, $endM] = array_map('intval', explode(':', $session->end_time));

            $startIdx = (int) (($startH - 8) * 60 + $startM) / 30;
            $endIdx = (int) (($endH - 8) * 60 + $endM) / 30;

            $cohortCodes = $session->cohorts->map(fn ($c) => sprintf(
                '%s%d(S%d)G%d',
                $c->programme->programme_code,
                $c->current_year,
                $c->semester,
                $c->tutorial_group
            ))->values();

            $targetCode = $cohortCodes->first();
            $studentCount = self::STUDENT_COUNTS[$targetCode] ?? 10;

            $event = [
                'di' => $session->day_of_week,
                'start' => $startIdx,
                'end' => $endIdx,
                'code' => $session->module->module_code,
                'type' => $session->session_type,
                'venue' => $session->venue->room_code,
                'lecturer' => $session->lecturer->name,
                'cohort' => $targetCode,
                'cohorts' => $cohortCodes->toArray(),
                'studentCount' => $studentCount,
                'status' => 'normal',
                'name' => $session->module->module_name,
                'remarks' => '',
            ];

            for ($w = 0; $w < $weekCount; $w++) {
                $events[] = ['week' => $w, 'event' => $event];
            }
        }

        return $events;
    }

    private function slugifyCohortCode(string $code): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $code));
    }

    public function timetableCohort(Request $request): JsonResponse
    {
        $cohortIdParam = $request->query('cohort_id', '');

        $cohorts = Cohort::with('programme.faculty')->get();

        $faculties = $cohorts
            ->groupBy(fn ($c) => $c->programme->faculty->faculty_code)
            ->map(fn ($group, $facultyCode) => [
                'id' => $facultyCode,
                'name' => $group->first()->programme->faculty->faculty_name,
                'cohorts' => $group->map(fn ($c) => [
                    'id' => $this->slugifyCohortCode(sprintf(
                        '%s%d(S%d)G%d',
                        $c->programme->programme_code,
                        $c->current_year,
                        $c->semester,
                        $c->tutorial_group
                    )),
                    'name' => sprintf(
                        '%s%d(S%d)G%d',
                        $c->programme->programme_code,
                        $c->current_year,
                        $c->semester,
                        $c->tutorial_group
                    ),
                ])->values(),
            ])
            ->values();

        $events = [];
        foreach ($cohorts as $cohort) {
            $code = sprintf(
                '%s%d(S%d)G%d',
                $cohort->programme->programme_code,
                $cohort->current_year,
                $cohort->semester,
                $cohort->tutorial_group
            );
            $slug = $this->slugifyCohortCode($code);

            $cohortEvents = $this->cohortBaseEvents($cohort->id);
            foreach ($cohortEvents as $entry) {
                $events[] = [
                    'cohortId' => $slug,
                    'week' => $entry['week'],
                    'event' => $entry['event'],
                ];
            }
        }

        $rsd3g2Cohort = $cohorts->first(fn ($c) => $this->slugifyCohortCode(sprintf(
            '%s%d(S%d)G%d',
            $c->programme->programme_code,
            $c->current_year,
            $c->semester,
            $c->tutorial_group
        )) === 'rsd3s1g2');

        $rsd3g2Base = $rsd3g2Cohort ? $this->cohortBaseEvents($rsd3g2Cohort->id) : [];

        return response()->json([
            'cohortTimetable' => [
                'faculties' => $faculties,
                'events' => $events,
                'rsd3g2Base' => $rsd3g2Base,
                'rsd3g2Flags' => null,
            ],
        ]);
    }

    public function arrangementSlots(Request $request): JsonResponse
    {
        $user = $this->resolveUser('lecturer');

        $semester = DB::table('semesters')->first();
        $semesterId = (int) $semester->id;
        $semesterStart = Carbon::parse($semester->start_date);

        $cohortIdsParam = $request->query('cohort_ids', '');
        if ($cohortIdsParam !== '') {
            $cohortIds = array_map('intval', array_filter(explode(',', $cohortIdsParam)));
        } else {
            $firstSession = ClassSession::where('lecturer_id', $user->id)->first();
            $cohortIds = $firstSession
                ? $firstSession->cohorts()->pluck('cohorts.id')->map('intval')->toArray()
                : [];
        }

        if ($cohortIds === []) {
            return response()->json(['error' => 'No cohort IDs provided or resolved.'], 422);
        }

        $sessionType = $request->query('session_type', 'L');
        $duration = (int) $request->query('duration', 60);
        $venueId = $request->query('venue_id') !== null ? (int) $request->query('venue_id') : null;
        $weekNumber = (int) $request->query('week_number', 11);

        $engine = new MatrixIntersectionEngine;
        $results = $engine->findAvailableSlots(
            $user->id,
            $cohortIds,
            $semesterId,
            $weekNumber,
            $sessionType,
            $duration,
            $venueId,
        );

        $engineVenueIds = array_unique(array_column($results, 'venue_id'));

        $eligibleVenues = DB::table('venues')
            ->whereIn('id', $engineVenueIds)
            ->get(['id', 'room_code', 'room_type', 'capacity', 'allowed_session_types']);

        $typeMap = [
            'tutorial' => 'Tutorial',
            'lecture_hall' => 'LectureHall',
            'lab' => 'Lab',
            'cisco_lab' => 'CiscoLab',
        ];

        $venues = $eligibleVenues->map(fn ($v) => [
            'code' => $v->room_code,
            'type' => $typeMap[$v->room_type] ?? $v->room_type,
            'capacity' => (int) $v->capacity,
            'allowedSessions' => explode(',', $v->allowed_session_types),
        ])->values()->toArray();

        $venueCodeToId = $eligibleVenues->mapWithKeys(fn ($v) => [$v->room_code => $v->id])->toArray();

        $venueSlots = [];
        foreach ($eligibleVenues as $venue) {
            $venueSlots[$venue->room_code] = [];
            for ($di = 0; $di <= 5; $di++) {
                for ($hi = 0; $hi <= 21; $hi++) {
                    $venueSlots[$venue->room_code][] = [$di, $hi, 1];
                }
            }
        }

        foreach ($results as $slot) {
            $code = $slot['venue_code'];
            if (! isset($venueSlots[$code])) {
                continue;
            }
            [$startH, $startM] = array_map('intval', explode(':', $slot['start_time']));
            [$endH, $endM] = array_map('intval', explode(':', $slot['end_time']));
            $startHi = (int) (($startH - 8) * 60 + $startM) / 30;
            $endHi = (int) (($endH - 8) * 60 + $endM) / 30;
            for ($hi = $startHi; $hi < $endHi && $hi <= 21; $hi++) {
                $idx = $slot['day'] * 22 + $hi;
                $venueSlots[$code][$idx] = [$slot['day'], $hi, 0];
            }
        }

        $replacementRequests = DB::table('replacement_requests')
            ->join('time_slots', 'replacement_requests.replacement_time_slot_id', '=', 'time_slots.id')
            ->where('time_slots.week_number', $weekNumber)
            ->where('time_slots.venue_id', '!=', 0)
            ->whereIn('replacement_requests.status', ['pending', 'approved'])
            ->select(
                'replacement_requests.status',
                'time_slots.venue_id',
                'time_slots.day_of_week',
                'time_slots.start_time',
                'time_slots.end_time'
            )
            ->get();

        foreach ($replacementRequests as $rr) {
            $code = $eligibleVenues->firstWhere('id', $rr->venue_id)?->room_code;
            if (! $code || ! isset($venueSlots[$code])) {
                continue;
            }
            [$startH, $startM] = array_map('intval', explode(':', $rr->start_time));
            [$endH, $endM] = array_map('intval', explode(':', $rr->end_time));
            $startHi = (int) (($startH - 8) * 60 + $startM) / 30;
            $endHi = (int) (($endH - 8) * 60 + $endM) / 30;
            $state = $rr->status === 'pending' ? 3 : 4;
            for ($hi = $startHi; $hi < $endHi && $hi <= 21; $hi++) {
                $idx = $rr->day_of_week * 22 + $hi;
                $venueSlots[$code][$idx] = [$rr->day_of_week, $hi, $state];
            }
        }

        $holidays = DB::table('holidays')
            ->where('semester_id', $semesterId)
            ->get()
            ->keyBy(fn ($h) => $h->week_number.'_'.$h->day_of_week);

        $dayAbbr = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

        $arrangementWeeks = [];
        for ($w = 0; $w < 3; $w++) {
            $wk = $weekNumber - $w;
            $days = [];
            for ($d = 0; $d <= 6; $d++) {
                $date = $semesterStart->copy()->addDays(($wk - 1) * 7 + $d);
                $isHoliday = $holidays->has($wk.'_'.$d);
                $days[] = [
                    'abbr' => $dayAbbr[$d],
                    'date' => $date->format('d M Y'),
                    'holiday' => $isHoliday,
                ];
            }
            $arrangementWeeks[] = [
                'label' => 'Week '.$wk,
                'days' => $days,
            ];
        }

        return response()->json([
            'venueSlots' => $venueSlots,
            'arrangementWeeks' => $arrangementWeeks,
            'venues' => $venues,
        ]);
    }

    public function timetableStudent(Request $request): JsonResponse
    {
        $user = $this->resolveUser('student');
        $student = $user->student;
        $activeCohortId = $student->cohort_id;

        $cohort = Cohort::with('programme.faculty')->find($activeCohortId);
        $activeCode = sprintf(
            '%s%d(S%d)G%d',
            $cohort->programme->programme_code,
            $cohort->current_year,
            $cohort->semester,
            $cohort->tutorial_group
        );
        $activeSlug = $this->slugifyCohortCode($activeCode);

        $cohorts = Cohort::with('programme.faculty')->get();

        $faculties = $cohorts
            ->groupBy(fn ($c) => $c->programme->faculty->faculty_code)
            ->map(fn ($group, $facultyCode) => [
                'id' => $facultyCode,
                'name' => $group->first()->programme->faculty->faculty_name,
                'cohorts' => $group->map(fn ($c) => [
                    'id' => $this->slugifyCohortCode(sprintf(
                        '%s%d(S%d)G%d',
                        $c->programme->programme_code,
                        $c->current_year,
                        $c->semester,
                        $c->tutorial_group
                    )),
                    'name' => sprintf(
                        '%s%d(S%d)G%d',
                        $c->programme->programme_code,
                        $c->current_year,
                        $c->semester,
                        $c->tutorial_group
                    ),
                ])->values(),
            ])
            ->values();

        $events = [];
        foreach ($cohorts as $cohortRow) {
            $code = sprintf(
                '%s%d(S%d)G%d',
                $cohortRow->programme->programme_code,
                $cohortRow->current_year,
                $cohortRow->semester,
                $cohortRow->tutorial_group
            );
            $slug = $this->slugifyCohortCode($code);

            $cohortEvents = $this->cohortBaseEvents($cohortRow->id);
            foreach ($cohortEvents as $entry) {
                $events[] = [
                    'cohortId' => $slug,
                    'week' => $entry['week'],
                    'event' => $entry['event'],
                ];
            }
        }

        $rsd3g2Base = $this->cohortBaseEvents($activeCohortId);

        $sessionIds = ClassSession::whereHas('cohorts', fn ($q) => $q->where('cohorts.id', $activeCohortId))
            ->pluck('id');

        $requests = ReplacementRequest::whereIn('class_session_id', $sessionIds)
            ->with('classSession.module')
            ->get();

        $rsd3g2Flags = [];
        foreach ($requests as $req) {
            $weekIdx = $req->week_number - 1;
            if ($weekIdx < 0 || $weekIdx > 13) {
                continue;
            }
            $key = (string) $weekIdx;
            $rsd3g2Flags[$key][] = [
                $req->classSession->module->module_code,
                $req->status,
                $req->remarks ?? '',
            ];
        }

        $exceptions = DB::table('class_exceptions')
            ->whereIn('class_session_id', $sessionIds)
            ->get();

        $cancelledFlags = [];
        foreach ($exceptions as $exc) {
            $weekKey = (string) ($exc->week_number - 1);
            $session = ClassSession::where('id', $exc->class_session_id)->first();
            if ($session === null) {
                continue;
            }
            $moduleCode = $session->module?->module_code;
            if ($moduleCode) {
                $cancelledFlags[$weekKey][] = $moduleCode;
            }
        }

        $notificationCount = $requests->where('status', 'pending')->count();

        return response()->json([
            'cohortTimetable' => [
                'faculties' => $faculties,
                'events' => $events,
                'rsd3g2Base' => $rsd3g2Base,
                'rsd3g2Flags' => (object) $rsd3g2Flags,
            ],
            'studentTimetable' => [
                'activeCohort' => $activeSlug,
                'cancelledFlags' => (object) $cancelledFlags,
                'notificationCount' => $notificationCount,
            ],
        ]);
    }
}
