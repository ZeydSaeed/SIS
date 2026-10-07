<?php

namespace App\Domain\Timetable\Support;

/**
 * The lessons of one teacher (or one section) on one day, read as positions in the day's lesson periods:
 * free periods between the first and the last lesson, and the longest unbroken run.
 */
final class DayRuns
{
    /**
     * @param  list<int>  $lessonPeriodIds  lesson periods of the day in order
     * @param  list<int>  $periodIds  periods taken that day
     * @return list<int> sorted distinct positions (periods outside the lesson list are ignored)
     */
    public static function positions(array $lessonPeriodIds, array $periodIds): array
    {
        $index = array_flip($lessonPeriodIds);
        $positions = [];
        foreach ($periodIds as $periodId) {
            if (isset($index[$periodId])) {
                $positions[$index[$periodId]] = $index[$periodId];
            }
        }
        sort($positions);

        return $positions;
    }

    /** @param  list<int>  $positions  sorted distinct */
    public static function gaps(array $positions): int
    {
        return $positions === [] ? 0 : $positions[count($positions) - 1] - $positions[0] + 1 - count($positions);
    }

    /** @param  list<int>  $positions  sorted distinct */
    public static function longestRun(array $positions): int
    {
        $longest = $run = 0;
        foreach ($positions as $i => $position) {
            $run = $i > 0 && $positions[$i - 1] === $position - 1 ? $run + 1 : 1;
            $longest = max($longest, $run);
        }

        return $longest;
    }
}
