<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\ValueObjects\AttendancePolicy;

/**
 * Attendance-based academic risk indicator — deterministic and explainable, for human review only. It describes
 * attendance patterns; it does not claim that absence causes low achievement and triggers no automatic action.
 *
 *   rate        recent 30-day absence rate (absent + excused) × 0.8, at most 40 points
 *   trend       +15 when the rate rose by ≥ 10 points against the previous 30 days
 *   streak      +15 for ≥ 3 consecutive school days with an absence, +20 for ≥ 5
 *   subjects    +5 per subject at or above the policy absence count, at most 15
 *   practical   +10 when practical-lesson absences reach the policy absence count
 *   band        high ≥ 60 · medium ≥ 35 · low otherwise
 */
final class AcademicRiskIndicator
{
    public const METHOD = 'الدرجة = نسبة الغياب خلال 30 يوماً × 0.8 (حتى 40) + 15 عند ارتفاعها 10 نقاط عن الثلاثين يوماً السابقة + 15/20 لأيام غياب متتالية (3/5) + 5 لكل مادة تجاوزت حد الغياب (حتى 15) + 10 لغياب الدروس العملية. مرتفع ≥ 60، متوسط ≥ 35. مؤشر للمراجعة وليس حكماً.';

    /**
     * @param  array{recent_absences: int, recent_decided: int, previous_absences: int, previous_decided: int, absence_dates: list<string>, subjects_over: int, practical_absences: int}  $in
     * @return array{score: int, band: string, reasons: list<string>, recent_rate: float|null}
     */
    public static function assess(array $in, AttendancePolicy $policy): array
    {
        $recent = AttendanceStatistics::percent($in['recent_absences'], $in['recent_decided']);
        $previous = AttendanceStatistics::percent($in['previous_absences'], $in['previous_decided']) ?? 0.0;
        $streak = self::longestStreak($in['absence_dates']);

        $parts = [
            'rate' => $recent === null ? 0 : (int) min(40, round($recent * 0.8)),
            'trend' => $recent !== null && $recent - $previous >= 10 ? 15 : 0,
            'streak' => $streak >= 5 ? 20 : ($streak >= 3 ? 15 : 0),
            'subjects' => min(15, 5 * $in['subjects_over']),
            'practical' => $in['practical_absences'] >= $policy->absenceAlertCount ? 10 : 0,
        ];
        $score = array_sum($parts);
        $values = ['rate' => $recent, 'trend' => $recent === null ? null : round($recent - $previous, 1), 'streak' => $streak, 'subjects' => $in['subjects_over'], 'practical' => $in['practical_absences']];
        $reasons = [];
        foreach ($parts as $key => $points) {
            if ($points > 0) {
                $reasons[] = $key.':'.$values[$key];
            }
        }

        return ['score' => $score, 'band' => $score >= 60 ? 'high' : ($score >= 35 ? 'medium' : 'low'), 'reasons' => $reasons, 'recent_rate' => $recent];
    }

    /**
     * Longest run of consecutive school days (weekends of Friday/Saturday and gaps of one non-teaching day are
     * bridged) with at least one absence.
     *
     * @param  list<string>  $dates  Y-m-d
     */
    public static function longestStreak(array $dates): int
    {
        $days = array_values(array_unique($dates));
        sort($days);
        $best = 0;
        $run = 0;
        $previous = null;
        foreach ($days as $day) {
            $current = new \DateTimeImmutable($day);
            $gap = $previous === null ? null : (int) $previous->diff($current)->days;
            $run = $gap !== null && $gap <= 3 && self::onlyRestDaysBetween($previous, $current) ? $run + 1 : 1;
            $best = max($best, $run);
            $previous = $current;
        }

        return $best;
    }

    private static function onlyRestDaysBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): bool
    {
        for ($d = $from->modify('+1 day'); $d < $to; $d = $d->modify('+1 day')) {
            if (! in_array((int) $d->format('w'), [5, 6], true)) {
                return false;
            }
        }

        return true;
    }
}
