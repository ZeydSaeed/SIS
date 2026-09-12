<?php

namespace App\Domain\Timetable\Repositories;

use App\Domain\Timetable\Data\PersistScheduleExceptionData;

interface ScheduleExceptionRepositoryInterface
{
    public function insert(PersistScheduleExceptionData $data): int;

    public function update(int $exceptionId, PersistScheduleExceptionData $data): void;

    public function existsForScheduleDate(int $schoolId, int $scheduleId, string $exceptionDate): bool;
}
