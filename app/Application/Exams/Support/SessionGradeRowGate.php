<?php

namespace App\Application\Exams\Support;

/**
 * Why a sheet row may not be entered before it reaches the grade write path (null = may proceed).
 * The write path itself still applies every grade rule (session state, score bounds, current grade, enrollment).
 */
final class SessionGradeRowGate
{
    public const DUPLICATE = 'grades.sheet.duplicate_row';

    public const NOT_IN_SESSION = 'grades.sheet.seat_not_in_session';

    public const FORBIDDEN = 'grades.sheet.seat_forbidden';

    /**
     * @param  array<int, true>  $eligible  active seats of the session
     * @param  array<int, true>  $allowed  seats the actor may grade
     * @param  array<int, true>  $seen  rows already handled in this submission
     */
    public static function rejection(int $seatId, array $eligible, array $allowed, array $seen): ?string
    {
        return match (true) {
            isset($seen[$seatId]) => self::DUPLICATE,
            ! isset($eligible[$seatId]) => self::NOT_IN_SESSION,
            ! isset($allowed[$seatId]) => self::FORBIDDEN,
            default => null,
        };
    }
}
