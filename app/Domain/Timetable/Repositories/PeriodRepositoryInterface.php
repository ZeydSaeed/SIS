<?php

namespace App\Domain\Timetable\Repositories;

use App\Domain\Timetable\Data\PeriodSnapshot;

interface PeriodRepositoryInterface
{
    public function find(int $schoolId, int $periodId): ?PeriodSnapshot;

    /** @return list<PeriodSnapshot> */
    public function listForSchool(int $schoolId): array;
}
