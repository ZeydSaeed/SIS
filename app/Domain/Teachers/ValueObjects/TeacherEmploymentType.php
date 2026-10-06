<?php

namespace App\Domain\Teachers\ValueObjects;

/**
 * نوع التعيين — per school/year membership (teachers.teacher_schools.employment_type).
 * Independent of TeacherStatus (نشط / غير نشط).
 */
final class TeacherEmploymentType
{
    /** ملاك — permanent staff of the school. */
    public const Permanent = 1;

    /** مكلف — assigned to teach here. */
    public const Assigned = 2;

    /** تنسيب — seconded from another school / directorate. */
    public const Seconded = 3;

    /** محاضر — lecturer (paid per lesson). */
    public const Lecturer = 4;

    /** عقد — contract. */
    public const Contract = 5;

    /** @return list<int> */
    public static function all(): array
    {
        return [self::Permanent, self::Assigned, self::Seconded, self::Lecturer, self::Contract];
    }

    public static function isValid(?int $value): bool
    {
        return $value === null || in_array($value, self::all(), true);
    }
}
