<?php

namespace App\Domain\Timetable\Constraints;

/**
 * Every constraint rule the engine understands — one place for what a rule means, where it may apply and
 * which parameters it takes. Stored rules (timetable.constraint_rules) and system defaults share it.
 *
 * Kinds (how the solver evaluates a rule):
 *   teacher_day   a teacher's lessons on one day        section_day   a section's lessons on one day
 *   teacher_week  a teacher's whole week                 section_week  a section's subject over the week
 *   placement     one lesson where it sits               pair          two activities relative to each other
 *   teacher_pair  two teachers relative to each other
 *
 * Precedence: for override rules, the most specific matching rule of a type wins for each teacher /
 * section / subject (system < school < branch < department < grade < class < section < subject < teacher
 * < activity; see {@see self::specificity()}). Placement, pair and teacher_pair rules all apply together.
 */
final class ConstraintRuleCatalogue
{
    public const SCOPES = ['branch_id', 'department_id', 'grade_level_id', 'class_id', 'section_id', 'teacher_id', 'subject_id', 'room_id', 'activity_id', 'other_activity_id'];

    private const SECTION_SCOPES = ['branch_id', 'department_id', 'grade_level_id', 'class_id', 'section_id'];

    /**
     * @var array<string, array{kind: string, scopes: list<string>, requires: list<string>, params: array<string, string>, override: bool}>
     *                                                                                                                                      params: name => 'int' | 'int?' | 'days' | 'days?' | 'lessons' | 'lessons?'
     */
    private const RULES = [
        'teacher_max_per_day' => ['kind' => 'teacher_day', 'scopes' => ['teacher_id'], 'requires' => [], 'params' => ['max' => 'int'], 'override' => true],
        'teacher_min_per_day' => ['kind' => 'teacher_day', 'scopes' => ['teacher_id'], 'requires' => [], 'params' => ['min' => 'int'], 'override' => true],
        'teacher_max_gaps_per_day' => ['kind' => 'teacher_day', 'scopes' => ['teacher_id'], 'requires' => [], 'params' => ['max' => 'int0'], 'override' => true],
        'teacher_max_consecutive' => ['kind' => 'teacher_day', 'scopes' => ['teacher_id'], 'requires' => [], 'params' => ['max' => 'int'], 'override' => true],
        'teacher_max_days' => ['kind' => 'teacher_week', 'scopes' => ['teacher_id'], 'requires' => [], 'params' => ['max' => 'int'], 'override' => true],
        'section_max_per_day' => ['kind' => 'section_day', 'scopes' => self::SECTION_SCOPES, 'requires' => [], 'params' => ['max' => 'int'], 'override' => true],
        'section_min_per_day' => ['kind' => 'section_day', 'scopes' => self::SECTION_SCOPES, 'requires' => [], 'params' => ['min' => 'int'], 'override' => true],
        'section_no_gaps' => ['kind' => 'section_day', 'scopes' => self::SECTION_SCOPES, 'requires' => [], 'params' => [], 'override' => true],
        'section_end_by' => ['kind' => 'section_day', 'scopes' => self::SECTION_SCOPES, 'requires' => [], 'params' => ['lesson' => 'int'], 'override' => true],
        'subject_max_per_day' => ['kind' => 'section_day', 'scopes' => [...self::SECTION_SCOPES, 'subject_id'], 'requires' => [], 'params' => ['max' => 'int'], 'override' => true],
        'subject_not_consecutive_days' => ['kind' => 'section_week', 'scopes' => [...self::SECTION_SCOPES, 'subject_id'], 'requires' => ['subject_id'], 'params' => [], 'override' => false],
        'forbidden_slots' => ['kind' => 'placement', 'scopes' => [...self::SECTION_SCOPES, 'teacher_id', 'subject_id', 'room_id', 'activity_id'], 'requires' => [], 'params' => ['days' => 'days?', 'lessons' => 'lessons?'], 'override' => false],
        'preferred_slots' => ['kind' => 'placement', 'scopes' => [...self::SECTION_SCOPES, 'teacher_id', 'subject_id', 'room_id', 'activity_id'], 'requires' => [], 'params' => ['days' => 'days?', 'lessons' => 'lessons?'], 'override' => false],
        'activity_before' => ['kind' => 'pair', 'scopes' => ['activity_id', 'other_activity_id'], 'requires' => ['activity_id', 'other_activity_id'], 'params' => [], 'override' => false],
        'activity_same_day' => ['kind' => 'pair', 'scopes' => ['activity_id', 'other_activity_id'], 'requires' => ['activity_id', 'other_activity_id'], 'params' => [], 'override' => false],
        'activity_not_same_day' => ['kind' => 'pair', 'scopes' => ['activity_id', 'other_activity_id'], 'requires' => ['activity_id', 'other_activity_id'], 'params' => [], 'override' => false],
        'activity_not_same_time' => ['kind' => 'pair', 'scopes' => ['activity_id', 'other_activity_id'], 'requires' => ['activity_id', 'other_activity_id'], 'params' => [], 'override' => false],
        'teachers_not_simultaneous' => ['kind' => 'teacher_pair', 'scopes' => ['teacher_id'], 'requires' => ['teacher_id'], 'params' => ['other_teacher_id' => 'int'], 'override' => false],
    ];

    private const SPECIFICITY = [
        'branch_id' => 1, 'department_id' => 2, 'grade_level_id' => 3, 'class_id' => 4, 'section_id' => 5,
        'subject_id' => 6, 'teacher_id' => 7, 'room_id' => 7, 'activity_id' => 8, 'other_activity_id' => 0,
    ];

    /** @return list<string> */
    public static function types(): array
    {
        return array_keys(self::RULES);
    }

    public static function exists(string $type): bool
    {
        return isset(self::RULES[$type]);
    }

    public static function kind(string $type): string
    {
        return self::RULES[$type]['kind'];
    }

    public static function isOverride(string $type): bool
    {
        return self::RULES[$type]['override'];
    }

    /** @return list<string> */
    public static function scopesOf(string $type): array
    {
        return self::RULES[$type]['scopes'];
    }

    /** @return array<string, string> */
    public static function paramsOf(string $type): array
    {
        return self::RULES[$type]['params'];
    }

    /**
     * Checks a rule as entered: known type, only allowed scopes, required scopes present, valid params.
     *
     * @param  array<string, int|null>  $scope
     * @param  array<string, mixed>  $params
     * @return list<string> error codes (empty = valid)
     */
    public static function validate(string $type, array $scope, array $params, int $priority): array
    {
        if (! self::exists($type)) {
            return ['timetable.rule_type_unknown'];
        }
        $errors = [];
        if ($priority < 1 || $priority > 6) {
            $errors[] = 'timetable.rule_priority_invalid';
        }
        $definition = self::RULES[$type];
        foreach (self::SCOPES as $column) {
            if (($scope[$column] ?? null) !== null && ! in_array($column, $definition['scopes'], true)) {
                $errors[] = 'timetable.rule_scope_not_allowed';
                break;
            }
        }
        foreach ($definition['requires'] as $column) {
            if (($scope[$column] ?? null) === null) {
                $errors[] = 'timetable.rule_scope_required';
                break;
            }
        }
        if (($scope['activity_id'] ?? null) !== null && ($scope['activity_id'] ?? null) === ($scope['other_activity_id'] ?? null)) {
            $errors[] = 'timetable.rule_same_activity';
        }
        foreach ($definition['params'] as $name => $kind) {
            if (! self::validParam($params[$name] ?? null, $kind)) {
                $errors[] = 'timetable.rule_params_invalid';
                break;
            }
        }
        if ($definition['kind'] === 'placement' && ($params['days'] ?? []) === [] && ($params['lessons'] ?? []) === []) {
            $errors[] = 'timetable.rule_params_invalid';
        }
        if ($type === 'teachers_not_simultaneous' && (int) ($params['other_teacher_id'] ?? 0) === (int) ($scope['teacher_id'] ?? 0)) {
            $errors[] = 'timetable.rule_same_teacher';
        }

        return array_values(array_unique($errors));
    }

    /** @param  array<string, int|null>  $scope */
    public static function specificity(array $scope): int
    {
        $score = 0;
        foreach (self::SPECIFICITY as $column => $weight) {
            if (($scope[$column] ?? null) !== null) {
                $score += $weight;
            }
        }

        return $score;
    }

    /**
     * Keeps only the params the rule takes, normalised (ints, sorted distinct lists).
     *
     * @param  array<string, mixed>  $params
     * @return array<string, int|list<int>>
     */
    public static function normaliseParams(string $type, array $params): array
    {
        $clean = [];
        foreach (self::RULES[$type]['params'] ?? [] as $name => $kind) {
            $value = $params[$name] ?? null;
            if ($value === null || $value === []) {
                continue;
            }
            if (is_array($value)) {
                $list = array_values(array_unique(array_map('intval', $value)));
                sort($list);
                $clean[$name] = $list;
            } else {
                $clean[$name] = (int) $value;
            }
        }

        return $clean;
    }

    private static function validParam(mixed $value, string $kind): bool
    {
        $optional = str_ends_with($kind, '?');
        $base = rtrim($kind, '?');
        if ($value === null || $value === []) {
            return $optional;
        }

        return match ($base) {
            'int' => is_numeric($value) && (int) $value >= 1 && (int) $value <= 40,
            'int0' => is_numeric($value) && (int) $value >= 0 && (int) $value <= 40,
            'days' => is_array($value) && array_filter($value, static fn ($d): bool => ! is_numeric($d) || (int) $d < 1 || (int) $d > 7) === [],
            'lessons' => is_array($value) && array_filter($value, static fn ($l): bool => ! is_numeric($l) || (int) $l < 1 || (int) $l > 20) === [],
            default => false,
        };
    }
}
