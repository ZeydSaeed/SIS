<?php

namespace App\Domain\Academic\Repositories;

use App\Domain\Academic\Data\AcademicYearSnapshot;

interface AcademicYearRepositoryInterface
{
    public function findIdByCode(string $code): ?int;

    public function findById(int $id): ?AcademicYearSnapshot;

    /**
     * @return list<AcademicYearSnapshot>
     */
    public function listAll(): array;

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
