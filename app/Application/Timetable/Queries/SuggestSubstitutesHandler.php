<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\TimetableInsightDTO;
use App\Application\Timetable\Support\TimetableBoardLoader;
use App\Domain\Timetable\Repositories\ScheduleExceptionRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\Services\SubstituteRanker;

/**
 * «بديل ليوم» (spec §36, §120): ranked substitutes for one lesson on one date. Teachers already covering
 * another lesson in that slot on that date (schedule_exceptions) are out. Choosing one creates the existing
 * schedule exception — Attendance keeps reading the effective timetable.
 */
final class SuggestSubstitutesHandler
{
    public function __construct(
        private readonly TimetableBoardLoader $boards,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
        private readonly ScheduleExceptionRepositoryInterface $exceptions,
        private readonly SubstituteRanker $ranker,
    ) {}

    public function handle(SuggestSubstitutesQuery $query): ?TimetableInsightDTO
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $query->date);
        $board = $this->boards->load($query->schoolId, $query->academicYearId);
        $lesson = array_values(array_filter($board->schedules, static fn (array $s): bool => $s['id'] === $query->scheduleId))[0] ?? null;
        if ($date === false || $lesson === null) {
            return null;
        }
        $weekday = (int) $date->format('w') + 1;
        if ($weekday !== $lesson['day_of_week']) {
            return new TimetableInsightDTO(['candidates' => [], 'reason' => 'weekday_mismatch']);
        }
        $slotIds = array_column(array_filter($board->schedules, static fn (array $s): bool => $s['day_of_week'] === $lesson['day_of_week'] && $s['period_id'] === $lesson['period_id']), 'id');
        $covering = [];
        foreach ($this->exceptions->listForSchool($query->schoolId, null, $query->date, $query->date, 1, 500)['items'] as $e) {
            if ($e->substituteTeacherId !== null && in_array($e->scheduleId, $slotIds, true)) {
                $covering[] = $e->substituteTeacherId;
            }
        }
        $names = array_column($this->workspace->teachers($query->schoolId, $query->academicYearId), 'short_name', 'id');

        return new TimetableInsightDTO(['candidates' => $this->ranker->rank($board, $lesson, $covering, $names), 'reason' => null]);
    }
}
