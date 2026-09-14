<?php

namespace App\Application\Audit\Queries;

use App\Application\Audit\DTOs\LoginHistoryDTO;
use App\Domain\Audit\Repositories\LoginHistoryRepositoryInterface;

final class GetLoginHistoryHandler
{
    public function __construct(
        private readonly LoginHistoryRepositoryInterface $loginHistory,
    ) {}

    public function handle(GetLoginHistoryQuery $query): ?LoginHistoryDTO
    {
        $row = $this->loginHistory->findByIdForSchool($query->schoolId, $query->entryId);
        if ($row === null) {
            return null;
        }

        return new LoginHistoryDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            userId: $row->userId,
            ipAddress: $row->ipAddress,
            userAgent: $row->userAgent,
            loginStatus: $row->loginStatus,
            createdAt: $row->createdAt,
        );
    }
}
