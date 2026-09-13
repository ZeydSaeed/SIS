<?php

namespace App\Application\Audit\Queries;

use App\Application\Audit\DTOs\LoginHistoryDTO;
use App\Domain\Audit\Repositories\LoginHistoryRepositoryInterface;

final class ListLoginHistoryHandler
{
    public function __construct(
        private readonly LoginHistoryRepositoryInterface $loginHistory,
    ) {}

    /** @return list<LoginHistoryDTO> */
    public function handle(ListLoginHistoryQuery $query): array
    {
        $limit = min(max($query->limit, 1), 100);
        $rows = $this->loginHistory->listForSchool($query->schoolId, $query->userId, $limit);

        return array_map(static fn ($row): LoginHistoryDTO => new LoginHistoryDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            userId: $row->userId,
            ipAddress: $row->ipAddress,
            userAgent: $row->userAgent,
            loginStatus: $row->loginStatus,
            createdAt: $row->createdAt,
        ), $rows);
    }
}
