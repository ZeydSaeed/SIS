<?php

namespace App\Application\Timetable\Support;

use App\Domain\Timetable\Data\TimetableBoard;

/**
 * Partial regeneration (spec §31 mode 7): a run's scope by branch / department / class / sections / teachers /
 * subjects, resolved to the compiler's section / teacher / subject filters. Empty = the whole school.
 */
final class GenerationScope
{
    /**
     * @param  array<string, mixed>  $scope
     * @return array{section_ids?: list<int>|null, teacher_ids?: list<int>|null, subject_ids?: list<int>|null}
     */
    public static function resolve(TimetableBoard $board, array $scope): array
    {
        $sections = isset($scope['section_ids']) && $scope['section_ids'] !== [] ? array_map('intval', $scope['section_ids']) : null;
        $branch = isset($scope['branch_id']) ? (int) $scope['branch_id'] : null;
        $department = isset($scope['department_id']) ? (int) $scope['department_id'] : null;
        $class = isset($scope['class_id']) ? (int) $scope['class_id'] : null;
        if ($branch !== null || $department !== null || $class !== null) {
            $matched = [];
            foreach ($board->sectionInfo as $sectionId => $info) {
                if (($branch === null || in_array($branch, $info['branch_ids'], true))
                    && ($department === null || in_array($department, $info['department_ids'], true))
                    && ($class === null || $info['class_id'] === $class)) {
                    $matched[] = $sectionId;
                }
            }
            $sections = $sections === null ? $matched : array_values(array_intersect($sections, $matched));
        }

        return [
            'section_ids' => $sections,
            'teacher_ids' => isset($scope['teacher_ids']) && $scope['teacher_ids'] !== [] ? array_map('intval', $scope['teacher_ids']) : null,
            'subject_ids' => isset($scope['subject_ids']) && $scope['subject_ids'] !== [] ? array_map('intval', $scope['subject_ids']) : null,
        ];
    }

    /**
     * Generation objectives (spec §74) as extra soft rules on top of the school's: fewer teacher gaps, no last
     * lesson, practical blocks in the morning.
     *
     * @param  list<string>  $objectives
     * @return list<array{id: int, rule_type: string, priority: int, scope: array<string, int|null>, params: array<string, mixed>}>
     */
    public static function objectiveRules(TimetableBoard $board, array $objectives): array
    {
        $rules = [];
        $lessons = count($board->lessonPeriodIds);
        if (in_array('minimize_teacher_gaps', $objectives, true)) {
            $rules[] = ['id' => 0, 'rule_type' => 'teacher_max_gaps_per_day', 'priority' => 4, 'scope' => [], 'params' => ['max' => 0]];
        }
        if (in_array('avoid_last_lesson', $objectives, true) && $lessons > 1) {
            $rules[] = ['id' => 0, 'rule_type' => 'forbidden_slots', 'priority' => 5, 'scope' => [], 'params' => ['lessons' => [$lessons]]];
        }
        if (in_array('morning_practicals', $objectives, true) && $lessons > 3) {
            foreach (array_unique($board->practicalSubjectIds) as $subjectId) {
                $rules[] = ['id' => 0, 'rule_type' => 'preferred_slots', 'priority' => 5, 'scope' => ['subject_id' => $subjectId], 'params' => ['lessons' => range(1, (int) ceil($lessons / 2))]];
            }
        }

        return $rules;
    }

    /**
     * What-if scenario (spec §79): extra unavailability on a copy of the board — never applied.
     *
     * @param  list<array{type: string, id: int, days: list<int>}>  $changes
     */
    public static function whatIf(TimetableBoard $board, array $changes): TimetableBoard
    {
        $extra = [];
        foreach ($changes as $change) {
            $column = match ($change['type'] ?? '') {
                'teacher_absent' => 'teacher_id',
                'room_closed' => 'room_id',
                'workshop_closed' => 'workshop_id',
                default => null,
            };
            if ($column === null) {
                continue;
            }
            foreach ($change['days'] ?? [] as $day) {
                foreach ($board->lessonPeriodIds as $periodId) {
                    $extra[] = ['teacher_id' => null, 'room_id' => null, 'section_id' => null, 'workshop_id' => null, $column => (int) $change['id'],
                        'day' => (int) $day, 'period_id' => $periodId, 'week_no' => null, 'kind' => 1];
                }
            }
        }

        return $extra === [] ? $board : $board->withAvailability($extra);
    }
}
