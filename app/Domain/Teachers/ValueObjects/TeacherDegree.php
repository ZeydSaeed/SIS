<?php

namespace App\Domain\Teachers\ValueObjects;

/**
 * «الشهادة الحاصل عليها» — the highest degree the teacher holds (teachers.teachers.degree).
 * Independent of the certificate's subject («التخصص», specialization_field).
 */
final class TeacherDegree
{
    public const Secondary = 1; // إعدادية

    public const Diploma = 2; // دبلوم

    public const Bachelor = 3; // بكالوريوس

    public const HigherDiploma = 4; // دبلوم عالي

    public const Master = 5; // ماجستير

    public const Doctorate = 6; // دكتوراه

    /** @return list<int> */
    public static function all(): array
    {
        return [self::Secondary, self::Diploma, self::Bachelor, self::HigherDiploma, self::Master, self::Doctorate];
    }

    public static function isValid(?int $value): bool
    {
        return $value === null || in_array($value, self::all(), true);
    }

    /** Error code when an update carries an unknown degree; null = acceptable (or not updated). */
    public static function rejection(bool $update, ?int $degree): ?string
    {
        return ! $update || self::isValid($degree) ? null : 'teachers.degree_invalid';
    }

    /**
     * Columns an update writes: the degree only when it is updated.
     *
     * @return array{degree?: ?int}
     */
    public static function columns(bool $update, ?int $degree): array
    {
        return $update ? ['degree' => $degree] : [];
    }
}
