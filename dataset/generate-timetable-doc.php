<?php

// dataset/generate-timetable-doc.php — one-off, kept for regeneration parity.
// Authority rule: the seeder/DB is authoritative; the doc regenerates from it, never hand-edited.
use Database\Seeders\ClassSessionsSeeder;
use Illuminate\Support\Facades\DB;

$seeder = new ClassSessionsSeeder;
$m = (new ReflectionClass($seeder))->getMethod('getSessionTemplates');
$tpl = $m->invoke($seeder,
    DB::table('lecturers')->pluck('user_id', 'staff_id')->toArray(),
    DB::table('venues')->pluck('id', 'room_code')->toArray(),
    DB::table('modules')->pluck('id', 'module_code')->toArray());

// day-grouping contract (§4.1): the template array is authored in module/cohort order,
// NOT day order — sort explicitly before rendering or day headers interleave.
usort($tpl, fn ($a, $b) => [$a['day_of_week'], $a['start_time']] <=> [$b['day_of_week'], $b['start_time']]);

$cn = [];
foreach (DB::table('cohorts')->join('programmes', 'programmes.id', '=', 'cohorts.programme_id')
    ->select('cohorts.id', 'programmes.programme_code', 'cohorts.current_year', 'cohorts.semester', 'cohorts.tutorial_group')->get() as $c) {
    $cn[sprintf('%s%d(S%d)G%d', $c->programme_code, $c->current_year, $c->semester, $c->tutorial_group)] = $c->id;
}
$flip = array_flip($cn);
$room = DB::table('venues')->pluck('room_code', 'id');
$staff = DB::table('lecturers')->pluck('staff_id', 'user_id');
$mod = DB::table('modules')->pluck('module_code', 'id');
$dayName = [0 => 'Monday', 1 => 'Tuesday', 2 => 'Wednesday', 3 => 'Thursday', 4 => 'Friday', 5 => 'Saturday'];

$scRows = 0;
$occ = 0;
$rows = [];
$lastDay = -1;
foreach ($tpl as $s) {
    $names = implode(', ', array_map(fn ($c) => $flip[$c] ?? '?', $s['cohorts'] ?? []));
    if ($s['day_of_week'] !== $lastDay) {
        $rows[] = '';
        $rows[] = "## {$dayName[$s['day_of_week']]} (day_of_week = {$s['day_of_week']})";
        $rows[] = '| start–end | venue | module | lecturer | type | cohorts |';
        $rows[] = '|---|---|---|---|---|---|';
        $lastDay = $s['day_of_week'];
    }
    $dur = (int) round((strtotime($s['end_time']) - strtotime($s['start_time'])) / 60);
    $occ += ($dur / 30) * 14;
    $scRows += count($s['cohorts'] ?? []);
    $rows[] = '| '.substr($s['start_time'], 0, 5).'–'.substr($s['end_time'], 0, 5)
        ." | {$room[$s['venue_id']]} | {$mod[$s['module_id']]} | {$staff[$s['lecturer_id']]} | {$s['session_type']} | {$names} |";
}
$counts = "\n## Counts\n\n- Session count: ".count($tpl)."\n- Session-cohort rows: {$scRows}\n- Occupied time slots: ".(int) $occ."\n";
file_put_contents(base_path('dataset/timetable.md'),
    "Baseline Timetable (generated from ClassSessionsSeeder — source of truth: seeder, never hand-edited)\n"
    .implode("\n", $rows).$counts);

// numeric self-check (explicit prints — not just the doc's own Counts block)
echo 'SELF-CHECK session count='.count($tpl)." session_cohorts={$scRows} occupied=".(int) $occ."\n";
echo "expected: 35 / 44 / 1988\n";
