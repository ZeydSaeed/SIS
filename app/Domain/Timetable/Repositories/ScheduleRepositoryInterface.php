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

    /**
     * Locks (lockedAt set) or unlocks (null) active lessons; returns how many changed.
     *
     * @param  list<int>  $scheduleIds
     */
    public function setLocked(int $schoolId, array $scheduleIds, ?string $lockedAt, ?int $userId): int;

    /**
     * Replaces part of the working grid in one go (apply a generation run, restore a version): the listed
     * unlocked lessons are cancelled (kept as history), the rows are inserted — lead rows first, joined rows
     * pointing at their lead. Call inside a transaction.
     *
     * @param  list<int>  $cancelIds
     * @param  list<array{lead_key: string, is_lead: bool, section_id: int, group_id: int|null, day_of_week: int, period_id: int, week_no: int|null, subject_id: int, teacher_id: int, co_teacher_id: int|null, room_id: int|null, activity_id: int|null}>  $rows
     */
    public function replaceGrid(int $schoolId, int $academicYearId, array $cancelIds, array $rows, string $at, ?int $userId, ?string $correlationId): int;
}
