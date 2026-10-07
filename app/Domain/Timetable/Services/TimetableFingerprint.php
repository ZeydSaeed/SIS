<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\TimetableBoard;

/**
 * A stable hash of what a timetable was built from (spec §99–100): sections, requirements (assignments ×
 * curriculum hours), configured activities, teachers, the school day and the settings. A version keeps the
 * fingerprint it was snapshotted with; when the live one differs, the source data changed since —
 * «قد يتأثر الجدول المنشور بتغيّر البيانات». Lessons on the grid are not part of it.
 */
final class TimetableFingerprint
{
    public function of(TimetableBoard $board): string
    {
        $requirements = array_map(static fn (array $r): string => $r['section_id'].':'.$r['subject_id'].':'.$r['teacher_id'].':'.($r['weekly'] ?? '-'), $board->requirements);
        sort($requirements);
        $activities = array_map(static function (array $a): string {
            $targets = array_map(static fn (array $t): string => $t['section_id'].'/'.($t['group_id'] ?? 0), $a['targets']);
            $teachers = array_map(static fn (array $t): string => $t['teacher_id'].'/'.$t['role'].'/'.($t['sessions'] ?? 0), $a['teachers']);
            sort($targets);
            sort($teachers);

            return implode('|', [$a['id'], $a['subject_id'], $a['weekly'], $a['block'], implode('+', $a['distribution'] ?? []), $a['room_id'] ?? 0, $a['room_type'] ?? 0, $a['workshop_id'] ?? 0, $a['week_pattern'], implode(',', $targets), implode(',', $teachers)]);
        }, $board->activities);
        sort($activities);
        $periods = array_map(static fn (PeriodSnapshot $p): string => $p->id.':'.$p->periodNumber.':'.$p->startTime.':'.$p->endTime.':'.$p->periodType, $board->periods);
        sort($periods);
        $sections = $board->sectionIds;
        sort($sections);
        $teachers = $board->activeTeacherIds;
        sort($teachers);

        return hash('sha256', json_encode([
            'requirements' => $requirements,
            'activities' => $activities,
            'periods' => $periods,
            'sections' => $sections,
            'teachers' => $teachers,
            'settings' => $board->settings->toArray(),
        ], JSON_THROW_ON_ERROR));
    }
}
