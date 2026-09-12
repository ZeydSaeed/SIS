<?php

namespace App\Domain\Academic\Repositories;

interface AcademicYearRepositoryInterface
{
    public function findIdByCode(string $code): ?int;

    /**
     * @return int New academic year id
     */
    public function insert(
        string $code,
        string $name,
        string $startDate,
        string $endDate,
        bool $isCurrent,
        int $status,
    ): int;
}
