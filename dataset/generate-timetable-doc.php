<?php

// dataset/generate-timetable-doc.php — regenerates dataset/timetable.md from
// the live DB. Authority rule: the DB is authoritative; the doc regenerates
// from it, never hand-edited. (Ported off the deleted ClassSessionsSeeder —
// SDD change import-real-schedule-records, design D10.)
//
// Run under the Laravel app, e.g.:
//   php artisan tinker --execute="require base_path('dataset/generate-timetable-doc.php');"
// against whichever connection the doc should reflect (per T10: the freshly
// seeded TEST db pre-demo-import; the demo DB post-import).

use Illuminate\Support\Facades\DB;

$dayName = [0 => 'Monday', 1 => 'Tuesday', 2 => 'Wednesday', 3 => 'Thursday', 4 => 'Friday', 5 => 'Saturday'];

// Cohort id -> label, DatabaseSeeder::cohortCode() derivation.
$cohortLabels = [];
foreach (DB::table('cohorts')->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
    ->select('cohorts.id', 'programmes.programme_code', 'cohorts.current_year', 'cohorts.semester', 'cohorts.tutorial_group')->get() as $c) {
    $cohortLabels[$c->id] = sprintf('%s%d(S%d)G%d', $c->programme_code, $c->current_year, $c->semester, $c->tutorial_group);
}
$room = DB::table('venues')->pluck('room_code', 'id');
$module = DB::table('modules')->pluck('module_code', 'id');
$staff = DB::table('lecturers')->pluck('staff_id', 'user_id');

// Cohort labels per session (sorted — deterministic rendering; T-5 compares
// order-insensitive sets).
$cohortNamesBySession = [];
foreach (DB::table('session_cohorts')->get() as $sc) {
    $cohortNamesBySession[$sc->class_session_id][] = $cohortLabels[$sc->cohort_id] ?? '?';
}
foreach ($cohortNamesBySession as &$names) {
    sort($names);
}
unset($names);

// Day-grouping contract (§4.1): sort by (day, start) or day headers interleave.
$sessions = DB::table('class_sessions')
    ->orderBy('day_of_week')
    ->orderBy('start_time')
    ->get();

$rows = [];
$lastDay = -1;
foreach ($sessions as $s) {
    if ($s->day_of_week !== $lastDay) {
        $rows[] = '';
        $rows[] = "## {$dayName[$s->day_of_week]} (day_of_week = {$s->day_of_week})";
        $rows[] = '| start–end | venue | module | lecturer | type | cohorts |';
        $rows[] = '|---|---|---|---|---|---|';
        $lastDay = $s->day_of_week;
    }
    $names = implode(', ', $cohortNamesBySession[$s->id] ?? []);
    $rows[] = '| '.substr($s->start_time, 0, 5).'–'.substr($s->end_time, 0, 5)
        ." | {$room[$s->venue_id]} | {$module[$s->module_id]} | {$staff[$s->lecturer_id]} | {$s->session_type} | {$names} |";
}

// Counts from the DB itself — occupied includes the holiday rule (W8 Mon,
// W14 Thu, W14 Fri stay available), so it is slots×14 minus holiday rows.
$sessionCount = count($sessions);
$linkCount = DB::table('session_cohorts')->count();
$occupied = DB::table('time_slots')->where('status', 'occupied')->count();
$counts = "\n## Counts\n\n- Session count: {$sessionCount}\n- Session-cohort rows: {$linkCount}\n- Occupied time slots: {$occupied}\n";

file_put_contents(
    base_path('dataset/timetable.md'),
    "Baseline Timetable (generated from the real schedule import — source of truth: the DB, never hand-edited)\n"
    .implode("\n", $rows).$counts,
);

// numeric self-check (explicit prints — not just the doc's own Counts block)
echo 'SELF-CHECK session count='.$sessionCount." session_cohorts={$linkCount} occupied={$occupied}\n";
echo "expected: 101 / 155 / 3963\n";
