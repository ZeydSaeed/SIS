<?php

namespace App\Domain\Timetable\Services;

/**
 * «زحف المادة»: shifts a lesson one period later (+1) or earlier (−1) inside the section's day.
 * The lessons it runs into move along with it up to the first free period — the block slides
 * as a whole, nothing is dropped. Fails when the day has no free period in that direction.
 */
final class ScheduleShiftPlanner
{
    /**
     * @param  list<int>  $lessonPeriodIds  the day's lesson periods in order
     * @param  array<int, int>  $dayLessons  period id → schedule id (the section's lessons that day)
     * @return array{error: string|null, moves: list<array{schedule_id: int, period_id: int}>}
     */
    public function plan(array $lessonPeriodIds, array $dayLessons, int $scheduleId, int $direction): array
    {
        $start = array_search(array_search($scheduleId, $dayLessons, true), $lessonPeriodIds, true);
        if ($start === false || ($direction !== 1 && $direction !== -1)) {
            return ['error' => 'timetable.shift_invalid', 'moves' => []];
        }

        // The block: from the lesson up to (not including) the first free period in that direction.
        $block = [];
        $index = $start;
        while (isset($lessonPeriodIds[$index]) && isset($dayLessons[$lessonPeriodIds[$index]])) {
            $block[] = $index;
            $index += $direction;
        }
        if (! isset($lessonPeriodIds[$index])) {
            return ['error' => 'timetable.shift_no_room', 'moves' => []];
        }

        $moves = [];
        foreach ($block as $from) {
            $moves[] = ['schedule_id' => $dayLessons[$lessonPeriodIds[$from]], 'period_id' => $lessonPeriodIds[$from + $direction]];
        }

        return ['error' => null, 'moves' => $moves];
    }
}
