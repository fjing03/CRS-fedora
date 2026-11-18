<?php

namespace App\Services;

use App\Models\ClassSession;
use App\Models\Module;
use App\Models\User;

/** Read-only projection of room rules, not a verdict about a specific booking. */
final class VenueGuideRules
{
    private const SESSION_LABELS = ['P' => 'Practical (P)', 'L' => 'Lecture (L)', 'T' => 'Tutorial (T)'];

    /**
     * @return array{defaultTypes: list<string>, programmes: list<string>|null, exceptions: list<array{code: string, types: list<string>, name?: string}>, hasRoomExceptions: bool}
     */
    public function forViewer(string $roomType, string $roomCode, ?User $viewer, ?int $semesterId): array
    {
        $rules = LectureSessionCatalog::ruleMap();
        $isLecturer = $viewer !== null && $viewer->isLecturer();
        $isPl = $isLecturer && $viewer->lecturer?->is_pl === true;
        $taught = [];
        if ($roomCode !== '' && ! $isPl && $isLecturer && $semesterId !== null
            && LectureSessionCatalog::roomSupportsModuleExceptions($roomType, $roomCode, $rules['b006_venue_code'])) {
            $sessions = ClassSession::query()
                ->where('lecturer_id', $viewer->id)
                ->where('semester_id', $semesterId)
                ->with('module:id,module_code')
                ->get(['module_id', 'session_type']);
            foreach ($sessions as $session) {
                if ($session->module !== null) {
                    $taught[$session->module->module_code][] = $session->session_type;
                }
            }
        }

        $summary = self::summarize($roomType, $roomCode, $rules, $isPl, $taught);
        if ($summary['exceptions'] !== []) {
            $names = Module::query()->whereIn('module_code', array_column($summary['exceptions'], 'code'))
                ->pluck('module_name', 'module_code');
            foreach ($summary['exceptions'] as &$exception) {
                $name = $names->get($exception['code']);
                if (is_string($name) && $name !== '') {
                    $exception['name'] = $name;
                }
            }
            unset($exception);
        }

        return $summary;
    }

    /**
     * @param  array{P?: list<string>, T?: list<string>, L?: list<string>, lab_access: array<string, list<string>>, b006_focs_programmes: list<string>, b006_banned_programmes: list<string>, b006_venue_code: string}  $rules
     * @param  array<string, list<string>>  $taught
     * @return array{defaultTypes: list<string>, programmes: list<string>|null, exceptions: list<array{code: string, types: list<string>, name?: string}>, hasRoomExceptions: bool}
     */
    public static function summarize(string $roomType, string $roomCode, array $rules, bool $isPl, array $taught): array
    {
        $defaults = [];
        $exceptions = [];
        $hasRoomExceptions = false;
        $programmes = null;
        if ($roomCode !== '') {
            foreach (self::SESSION_LABELS as $type => $label) {
                if (is_array($rules[$type] ?? null) && in_array($roomType, $rules[$type], true)) {
                    $defaults[] = $label;
                }
            }
            if ($roomCode === $rules['b006_venue_code']) {
                $programmes = array_values(array_diff($rules['b006_focs_programmes'], $rules['b006_banned_programmes']));
            }
            if (LectureSessionCatalog::roomSupportsModuleExceptions($roomType, $roomCode, $rules['b006_venue_code'])) {
                $modules = $rules['lab_access'];
                ksort($modules);
                foreach ($modules as $module => $allowed) {
                    $types = [];
                    foreach (self::SESSION_LABELS as $type => $label) {
                        if (! is_array($rules[$type] ?? null) || ! in_array($type, $allowed, true)) {
                            continue;
                        }
                        $hasRoomExceptions = true;
                        if ($isPl || in_array($type, $taught[$module] ?? [], true)) {
                            $types[] = $label;
                        }
                    }
                    if ($types !== []) {
                        $exceptions[] = ['code' => $module, 'types' => $types];
                    }
                }
            }
        }

        return ['defaultTypes' => $defaults, 'programmes' => $programmes, 'exceptions' => $exceptions, 'hasRoomExceptions' => $hasRoomExceptions];
    }
}
