<?php

namespace App\Domain\Timetable\Services;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\PersistPeriodData;
use App\Domain\Timetable\ValueObjects\PeriodType;

/**
 * A school day is a row of periods: each starts before it ends, numbers are unique,
 * and no two periods share any minute. Returns the first violated rule as an error code.
 */
final class PeriodTimeGuard
{
    /** @param  list<PeriodSnapshot>  $existing  the school's periods (the edited one is skipped by id) */
    public function error(PersistPeriodData $data, array $existing, ?int $periodId = null): ?string
    {
        if ($data->periodNumber < 1 || $data->periodNumber > 20) {
            return 'timetable.period_number_invalid';
        }

        if (PeriodType::tryFrom($data->periodType) === null) {
            return 'timetable.period_type_invalid';
        }

        $start = self::minutes($data->startTime);
        $end = self::minutes($data->endTime);
        if ($start === null || $end === null || $start >= $end) {
            return 'timetable.period_time_invalid';
        }

        foreach ($existing as $period) {
            if ($period->id === $periodId) {
                continue;
            }

            if ($period->periodNumber === $data->periodNumber) {
                return 'timetable.period_number_taken';
            }

            $otherStart = self::minutes($period->startTime);
            $otherEnd = self::minutes($period->endTime);
            if ($otherStart !== null && $otherEnd !== null && $start < $otherEnd && $otherStart < $end) {
                return 'timetable.period_overlap';
            }
        }

        return null;
    }

    /** "HH:MM" or "HH:MM:SS" → minutes after midnight. */
    public static function minutes(string $time): ?int
    {
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', trim($time), $m) !== 1) {
            return null;
        }

        return ((int) $m[1]) * 60 + (int) $m[2];
    }
}
