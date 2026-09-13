<?php

namespace App\Domain\Finance\Repositories;

use App\Domain\Finance\Data\FeeTypeSnapshot;

interface FeeTypeRepositoryInterface
{
    public function create(
        int $schoolId,
        string $code,
        string $name,
        string $amount,
        bool $isRecurring,
        int $status,
        string $createdAt,
    ): int;

    public function findIdBySchoolAndCode(int $schoolId, string $code): ?int;

    public function find(int $schoolId, int $feeTypeId): ?FeeTypeSnapshot;

    public function setStatus(int $schoolId, int $feeTypeId, int $status): void;

    /**
     * @return list<FeeTypeSnapshot>
     */
    public function listBySchool(int $schoolId, ?int $status = null): array;
}
