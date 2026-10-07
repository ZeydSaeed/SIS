<?php

namespace App\Domain\Timetable\Services;

/**
 * «جدول الطالب» (spec §37–38, §98): a student's week is derived from membership — the section's
 * whole-class lessons plus the lessons of the groups the student belongs to. A division whose groups have
 * lessons but where the student is in no group is reported, not guessed.
 */
final class StudentTimetableFilter
{
    /**
     * @param  list<array<string, mixed>>  $sectionLessons  lessons of the student's section (group_id null = whole class)
     * @param  list<int>  $groupIds  the student's groups
     * @param  array<int, int>  $groupDivision  group id → division id
     * @return array{lessons: list<array<string, mixed>>, unassigned_divisions: list<int>}
     */
    public function filter(array $sectionLessons, array $groupIds, array $groupDivision): array
    {
        $mine = array_flip($groupIds);
        $myDivisions = [];
        foreach ($groupIds as $g) {
            if (isset($groupDivision[$g])) {
                $myDivisions[$groupDivision[$g]] = true;
            }
        }
        $lessons = [];
        $unassigned = [];
        foreach ($sectionLessons as $lesson) {
            $group = $lesson['group_id'] ?? null;
            if ($group === null || isset($mine[$group])) {
                $lessons[] = $lesson;
            } elseif (isset($groupDivision[$group]) && ! isset($myDivisions[$groupDivision[$group]])) {
                $unassigned[$groupDivision[$group]] = true;
            }
        }
        usort($lessons, static fn (array $a, array $b): int => [$a['day_of_week'], $a['period_id']] <=> [$b['day_of_week'], $b['period_id']]);

        return ['lessons' => $lessons, 'unassigned_divisions' => array_keys($unassigned)];
    }
}
