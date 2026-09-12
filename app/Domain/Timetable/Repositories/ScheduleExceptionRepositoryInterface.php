<?php

namespace App\Domain\Timetable\Repositories;

use App\Domain\Timetable\Data\PersistScheduleExceptionData;
use App\Domain\Timetable\Data\ScheduleExceptionSnapshot;

interface ScheduleExceptionRepositoryInterface
{
    public function insert(PersistScheduleExceptionData $data): int;

    public function update(int $exceptionId, PersistScheduleExceptionData $data): void;

    public function existsForScheduleDate(int $schoolId, int $scheduleId, string $exceptionDate): bool;

    public function findById(int $schoolId, int $exceptionId): ?ScheduleExceptionSnapshot;

    /**
     * @return array{items: list<ScheduleExceptionSnapshot>, total: int}
     */
    public function listForSchool(
        int $schoolId,
        ?int $scheduleId,
        ?string $dateFrom,
        ?string $dateTo,
        int $page,
        int $perPage,
    ): array;
}
