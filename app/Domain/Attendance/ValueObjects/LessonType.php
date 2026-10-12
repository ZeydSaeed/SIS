<?php

namespace App\Domain\Attendance\ValueObjects;

/** attendance.sessions.lesson_type — نظري / عملي. */
enum LessonType: int
{
    case Theory = 1;
    case Practical = 2;

    /**
     * Practical when the subject is practical (curriculum SubjectType 3) or the lesson runs in a
     * laboratory / workshop room (organization.room_types.kind 2 / 3).
     */
    public static function infer(?int $subjectType, ?int $roomKind): self
    {
        return $subjectType === 3 || in_array($roomKind, [2, 3], true) ? self::Practical : self::Theory;
    }
}
