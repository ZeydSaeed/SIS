<?php

namespace App\Domain\Timetable\Specifications;

use App\Domain\Timetable\ValueObjects\ActivityType;

/**
 * What makes an activity definition valid (spec §5–7, §12–13):
 * - weekly 1–40, block 1–6 and ≤ weekly; a distribution (e.g. "2+2+1") of parts 1–6 summing to weekly;
 * - a known activity type, week pattern 0 (every week) or a week of the cycle;
 * - at least one target, no section twice with the same group, a group only in its own section;
 * - exactly one lead teacher, no teacher twice, a co-teacher's sessions ≤ weekly;
 * - at most one room source (room, room type or workshop);
 * - the lead teacher is assigned the subject (teacher_subjects) — G5 binding, same as hand placement.
 */
final class ActivityDefinitionRules
{
    /**
     * @param  array{activity_type: int, weekly_count: int, block_length: int, distribution: string|null, room_id: int|null, room_type: int|null, workshop_id: int|null, week_pattern: int}  $data
     * @param  list<array{section_id: int, group_id: int|null}>|null  $targets  null = unchanged (update)
     * @param  list<array{teacher_id: int, role: int, sessions: int|null}>|null  $teachers  null = unchanged (update)
     * @param  array<int, int>  $groupSection  group id → section id
     * @return list<string>
     */
    public static function errors(array $data, ?array $targets, ?array $teachers, int $cycleWeeks, array $groupSection = [], ?bool $leadQualified = true): array
    {
        $errors = [];
        $weekly = $data['weekly_count'];
        if ($weekly < 1 || $weekly > 40 || $data['block_length'] < 1 || $data['block_length'] > 6 || $data['block_length'] > $weekly) {
            $errors[] = 'timetable.activity_load_invalid';
        }
        if ($data['distribution'] !== null && ! self::distributionValid($data['distribution'], $weekly)) {
            $errors[] = 'timetable.activity_distribution_invalid';
        }
        if (ActivityType::tryFrom($data['activity_type']) === null) {
            $errors[] = 'timetable.activity_type_invalid';
        }
        if ($data['week_pattern'] < 0 || $data['week_pattern'] > $cycleWeeks || ($data['week_pattern'] > 0 && $cycleWeeks === 1)) {
            $errors[] = 'timetable.activity_week_invalid';
        }
        if (count(array_filter([$data['room_id'], $data['room_type'], $data['workshop_id']], static fn ($v): bool => $v !== null)) > 1) {
            $errors[] = 'timetable.activity_room_ambiguous';
        }
        if ($targets !== null) {
            $keys = array_map(static fn (array $t): string => $t['section_id'].':'.($t['group_id'] ?? 0), $targets);
            if ($targets === [] || count(array_unique($keys)) !== count($keys)) {
                $errors[] = 'timetable.activity_targets_invalid';
            }
            foreach ($targets as $t) {
                if ($t['group_id'] !== null && ($groupSection[$t['group_id']] ?? null) !== $t['section_id']) {
                    $errors[] = 'timetable.activity_group_not_in_section';
                    break;
                }
            }
        }
        if ($teachers !== null) {
            $ids = array_column($teachers, 'teacher_id');
            $leads = count(array_filter($teachers, static fn (array $t): bool => $t['role'] === 1));
            if ($teachers === [] || $leads !== 1 || count(array_unique($ids)) !== count($ids)) {
                $errors[] = 'timetable.activity_teachers_invalid';
            }
            foreach ($teachers as $t) {
                if (! in_array($t['role'], [1, 2, 3], true) || ($t['sessions'] !== null && ($t['sessions'] < 1 || $t['sessions'] > $weekly))) {
                    $errors[] = 'timetable.activity_teachers_invalid';
                    break;
                }
            }
            if ($leadQualified === false) {
                $errors[] = 'timetable.teacher_not_assigned_subject';
            }
        }

        return array_values(array_unique($errors));
    }

    public static function distributionValid(string $distribution, int $weekly): bool
    {
        if (preg_match('/^[1-6](\+[1-6])*$/', $distribution) !== 1) {
            return false;
        }

        return array_sum(array_map('intval', explode('+', $distribution))) === $weekly;
    }
}
