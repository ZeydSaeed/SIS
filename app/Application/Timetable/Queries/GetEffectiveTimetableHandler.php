<?php

namespace App\Application\Timetable\Queries;

use App\Application\Timetable\DTOs\TimetableLessonsDTO;
use App\Application\Timetable\Support\EffectiveTimetable;

final class GetEffectiveTimetableHandler
{
    public function __construct(
        private readonly EffectiveTimetable $effective,
    ) {}

    public function handle(GetEffectiveTimetableQuery $query): ?TimetableLessonsDTO
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $query->date);
        if ($date === false) {
            return null;
        }
        $result = $this->effective->on($query->schoolId, $query->academicYearId, $date, $query->sectionId, $query->teacherId);

        return new TimetableLessonsDTO($result['lessons'], [
            'date' => $query->date,
            'version_id' => $result['version_id'],
            'day_of_week' => $result['day_of_week'],
            'week_no' => $result['week_no'],
        ]);
    }
}
