<?php

namespace App\Domain\Timetable\Repositories;

use App\Domain\Timetable\Data\PersistScheduleData;
use App\Domain\Timetable\Data\ScheduleSnapshot;

interface ScheduleRepositoryInterface
{
    public function assertWritableRefs(PersistScheduleData $data): void;

    public function insertActive(PersistScheduleData $data): int;

    public function findById(int $schoolId, int $scheduleId): ?ScheduleSnapshot;

    /**
     * @return array{items: list<ScheduleSnapshot>, total: int}
     */
    public function listForSchoolYear(
        int $schoolId,
        int $academicYearId,
        ?int $sectionId,
        ?int $lifecycleStatus,
        int $page,
        int $perPage,
    ): array;

    public function updateActive(int $scheduleId, PersistScheduleData $data): void;

    public function cancel(int $schoolId, int $scheduleId, string $cancelledAt): void;
}
