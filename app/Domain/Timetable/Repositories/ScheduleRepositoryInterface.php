<?php

namespace App\Domain\Timetable\Repositories;

use App\Domain\Timetable\Data\PersistScheduleData;
use App\Domain\Timetable\Data\ScheduleSnapshot;

interface ScheduleRepositoryInterface
{
    public function assertWritableRefs(PersistScheduleData $data): void;

    public function assertNoActiveSlotConflicts(PersistScheduleData $data, ?int $excludeScheduleId = null): void;

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

    public function reactivate(int $schoolId, int $scheduleId, string $reactivatedAt): void;

    /**
     * Moves several active lessons at once (swap two lessons, slide a block). Each lands only on a
     * slot free for its section and teacher once the others have left theirs. Call inside a transaction.
     *
     * @param  list<array{schedule_id: int, day: int, period_id: int}>  $moves
     */
    public function relocate(int $schoolId, array $moves, string $at): void;
}
