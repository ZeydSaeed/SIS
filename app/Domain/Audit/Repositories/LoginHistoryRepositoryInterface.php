<?php

namespace App\Domain\Audit\Repositories;

use App\Domain\Audit\Data\LoginHistorySnapshot;

interface LoginHistoryRepositoryInterface
{
    public function create(
        int $schoolId,
        int $userId,
        ?string $ipAddress,
        ?string $userAgent,
        int $loginStatus,
        string $createdAt,
    ): int;

    /** @return list<LoginHistorySnapshot> */
    public function listForSchool(int $schoolId, ?int $userId, int $limit): array;
}
