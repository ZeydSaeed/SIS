<?php

namespace App\Domain\Teachers\ValueObjects;

/**
 * teachers.teaching_assignments.role — on a curriculum subject line: one main teacher (or the only one), and any
 * number of assistants once the main teacher is set.
 */
final class TeachingRole
{
    /** مدرس المادة / المدرس الرئيسي. */
    public const Main = 1;

    /** مدرس مساعد. */
    public const Assistant = 2;

    public static function isValid(int $role): bool
    {
        return $role === self::Main || $role === self::Assistant;
    }
}
