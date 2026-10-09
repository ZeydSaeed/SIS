<?php

namespace App\Domain\Timetable\Repositories;

use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\PersistPeriodData;

interface PeriodRepositoryInterface
{
    public function find(int $schoolId, int $periodId): ?PeriodSnapshot;

    /** @return list<PeriodSnapshot>  the active periods of the school day (retired breaks excluded) */
    public function listForSchool(int $schoolId): array;

    public function insert(PersistPeriodData $data): int;

    /** Periods are never deleted (attendance + schedules reference them) — only re-timed. */
    public function update(int $periodId, PersistPeriodData $data): void;

    /**
     * Writes a whole school day at once (renumbering never trips the unique number): rows with an id
     * are re-timed / renumbered, rows without one are added. Call inside a transaction.
     *
     * @param  list<array{id: int|null, data: PersistPeriodData}>  $day
     */
    public function replaceDay(int $schoolId, array $day): void;

    /** «حذف استراحة»: the break leaves the day (status 2) — the row stays (never deleted). */
    public function retire(int $schoolId, int $periodId): void;

    /** True when an active schedule sits in the period (it cannot become a break). */
    public function hasActiveSchedules(int $schoolId, int $periodId): bool;
}
