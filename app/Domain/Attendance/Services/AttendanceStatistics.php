<?php

namespace App\Domain\Attendance\Services;

/**
 * Attendance rates — one formula for every report, dashboard and alert.
 *
 *   decided            = present + late + left_early + absent + excused   (unmarked rows never count)
 *   attendance_rate    = (present + late + left_early) / decided × 100
 *   absence_rate       = (absent + excused) / decided × 100
 *   unexcused_rate     = absent / decided × 100
 *   late_rate          = late / decided × 100
 *
 * Rates describe recorded data only; they do not explain causes.
 */
final class AttendanceStatistics
{
    public const FORMULA = 'نسبة الحضور = (حاضر + متأخر + خروج مبكر) ÷ (إجمالي الحالات المحددة) × 100؛ الغياب يشمل الغياب بعذر وبدون عذر؛ الحالات غير المحددة لا تدخل في الحساب.';

    /**
     * @param  array<string, int|float|string|null>  $counts  present, absent, late, excused, left_early
     * @return array{decided: int, attendance_rate: float|null, absence_rate: float|null, unexcused_rate: float|null, late_rate: float|null}
     */
    public static function rates(array $counts): array
    {
        $present = (int) ($counts['present'] ?? 0);
        $absent = (int) ($counts['absent'] ?? 0);
        $late = (int) ($counts['late'] ?? 0);
        $excused = (int) ($counts['excused'] ?? 0);
        $leftEarly = (int) ($counts['left_early'] ?? 0);
        $decided = $present + $absent + $late + $excused + $leftEarly;

        return [
            'decided' => $decided,
            'attendance_rate' => self::percent($present + $late + $leftEarly, $decided),
            'absence_rate' => self::percent($absent + $excused, $decided),
            'unexcused_rate' => self::percent($absent, $decided),
            'late_rate' => self::percent($late, $decided),
        ];
    }

    public static function percent(int $part, int $whole): ?float
    {
        return $whole > 0 ? round($part * 100 / $whole, 1) : null;
    }
}
