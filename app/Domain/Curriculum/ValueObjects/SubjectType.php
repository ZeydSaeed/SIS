<?php

namespace App\Domain\Curriculum\ValueObjects;

enum SubjectType: int
{
    case Core = 1;
    case Elective = 2;
    case Practical = 3;
    case Theory = 4;

    /**
     * A subject's set of types («نوع المادة», several allowed): unique and ascending, so the first is the primary
     * type (curriculum.subjects.subject_type). Null when empty or when one value is not a type.
     *
     * @param  array<mixed>  $types
     * @return list<int>|null
     */
    public static function normalizeSet(array $types): ?array
    {
        $set = [];
        foreach ($types as $type) {
            if (! is_numeric($type) || self::tryFrom((int) $type) === null) {
                return null;
            }
            $set[(int) $type] = (int) $type;
        }
        if ($set === []) {
            return null;
        }
        ksort($set);

        return array_values($set);
    }
}
