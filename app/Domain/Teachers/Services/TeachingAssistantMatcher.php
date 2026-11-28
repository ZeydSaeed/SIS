<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Teachers\ValueObjects\TeachingRole;

/**
 * «المدرس المساعد» of a main teaching on the «المعلمون» roster: assistants on the same subject and place (the
 * same curriculum line when both name one); a part (نظري / عملي) or a section left open on either side matches any.
 * Rows are the roster read model's teaching assignments (teacher_id, subject_id, branch_id, department_id,
 * class_id, section_id, curriculum_subject_id, subject_type, role).
 */
final class TeachingAssistantMatcher
{
    /**
     * @param  list<array<string, mixed>>  $rows  every active teaching of the school/year
     * @param  array<int, string>  $names  teacher id → full name
     * @return list<list<string>> assistant names per row, in the order of $rows (empty for assistant rows)
     */
    public static function namesPerRow(array $rows, array $names): array
    {
        $assistants = array_values(array_filter($rows, static fn (array $r): bool => $r['role'] === TeachingRole::Assistant));

        return array_map(
            static fn (array $row): array => $row['role'] === TeachingRole::Main ? self::namesFor($row, $assistants, $names) : [],
            $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $main
     * @param  list<array<string, mixed>>  $assistants
     * @param  array<int, string>  $names
     * @return list<string>
     */
    private static function namesFor(array $main, array $assistants, array $names): array
    {
        $found = [];
        foreach ($assistants as $a) {
            $name = $names[$a['teacher_id']] ?? null;
            if ($name !== null && $a['teacher_id'] !== $main['teacher_id'] && self::samePlace($main, $a) && ! in_array($name, $found, true)) {
                $found[] = $name;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $main
     * @param  array<string, mixed>  $a
     */
    private static function samePlace(array $main, array $a): bool
    {
        $sameLine = $main['curriculum_subject_id'] === null || $a['curriculum_subject_id'] === null
            ? $a['subject_id'] === $main['subject_id'] && $a['branch_id'] === $main['branch_id']
                && $a['department_id'] === $main['department_id'] && $a['class_id'] === $main['class_id']
            : $a['curriculum_subject_id'] === $main['curriculum_subject_id'];

        return $sameLine
            && self::openOrEqual($main['subject_type'], $a['subject_type'])
            && self::openOrEqual($main['section_id'], $a['section_id']);
    }

    private static function openOrEqual(mixed $x, mixed $y): bool
    {
        return $x === null || $y === null || $x === $y;
    }
}
