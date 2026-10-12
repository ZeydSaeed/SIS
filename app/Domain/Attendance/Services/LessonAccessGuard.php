<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;

/**
 * A teacher works only on lessons they teach that day (the scheduled teacher, the substitute of that date, or
 * the co-teacher). An authorised reviewer may act on behalf of any teacher of the school — always recorded.
 */
final class LessonAccessGuard
{
    public static function assertCanWork(?int $actorTeacherId, int $teacherId, ?int $coTeacherId, bool $canActOnBehalf): void
    {
        if ($canActOnBehalf) {
            return;
        }
        if ($actorTeacherId === null) {
            throw AttendanceRuleViolation::teacherProfileMissing();
        }
        if ($actorTeacherId !== $teacherId && $actorTeacherId !== $coTeacherId) {
            throw AttendanceRuleViolation::lessonAccessDenied();
        }
    }
}
