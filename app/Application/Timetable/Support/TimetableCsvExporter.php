<?php

namespace App\Application\Timetable\Support;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\TimetableBoard;

/**
 * «تصدير CSV» (spec §85): one row per lesson, grouped by section or by teacher, in day / lesson order —
 * names resolved from the given maps (subjects, teachers, sections, rooms, groups).
 */
final class TimetableCsvExporter
{
    private const DAYS = [1 => 'الأحد', 2 => 'الإثنين', 3 => 'الثلاثاء', 4 => 'الأربعاء', 5 => 'الخميس', 6 => 'الجمعة', 7 => 'السبت'];

    /**
     * @param  list<array<string, mixed>>  $lessons
     * @param  array<int, string>  $subjects
     * @param  array<int, string>  $teachers
     * @param  array<int, string>  $sections
     * @return list<list<string>>
     */
    public function rows(array $lessons, TimetableBoard $board, array $subjects, array $teachers, array $sections, string $by): array
    {
        $times = [];
        foreach ($board->periods as $p) {
            $times[$p->id] = $p;
        }
        $rooms = array_map(static fn (array $r): string => $r['name'], $board->rooms);
        $groups = array_map(static fn (array $g): string => $g['name'], $board->groups);

        $expanded = [];
        foreach ($lessons as $l) {
            if (($l['joined_to'] ?? null) !== null && $by === 'teacher') {
                continue;
            }
            $owners = $by === 'teacher' ? array_filter([$l['teacher_id'], $l['co_teacher_id'] ?? null]) : [$l['section_id']];
            foreach ($owners as $owner) {
                $expanded[] = ['owner' => $owner] + $l;
            }
        }
        usort($expanded, static fn (array $a, array $b): int => [$a['owner'], $a['day_of_week'], $board->lessonOrdinal($a['period_id']) ?? 99]
            <=> [$b['owner'], $b['day_of_week'], $board->lessonOrdinal($b['period_id']) ?? 99]);

        $rows = [[$by === 'teacher' ? 'المعلم' : 'الشعبة', 'اليوم', 'الحصة', 'الوقت', 'المادة', $by === 'teacher' ? 'الشعبة' : 'المعلم', 'القاعة', 'المجموعة', 'الأسبوع']];
        foreach ($expanded as $l) {
            $period = $times[$l['period_id']] ?? null;
            $rows[] = [
                $by === 'teacher' ? ($teachers[$l['owner']] ?? '#'.$l['owner']) : ($sections[$l['owner']] ?? '#'.$l['owner']),
                self::DAYS[$l['day_of_week']] ?? (string) $l['day_of_week'],
                (string) ($board->lessonOrdinal($l['period_id']) ?? ''),
                $period instanceof PeriodSnapshot ? substr($period->startTime, 0, 5).'–'.substr($period->endTime, 0, 5) : '',
                $subjects[$l['subject_id']] ?? '#'.$l['subject_id'],
                $by === 'teacher' ? ($sections[$l['section_id']] ?? '#'.$l['section_id']) : ($teachers[$l['teacher_id']] ?? '#'.$l['teacher_id']),
                ($l['room_id'] ?? null) !== null ? ($rooms[$l['room_id']] ?? '#'.$l['room_id']) : '',
                ($l['group_id'] ?? null) !== null ? ($groups[$l['group_id']] ?? '#'.$l['group_id']) : '',
                ($l['week_no'] ?? null) !== null ? (string) $l['week_no'] : '',
            ];
        }

        return $rows;
    }
}
