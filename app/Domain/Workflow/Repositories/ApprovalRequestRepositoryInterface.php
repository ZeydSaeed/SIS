<?php

namespace App\Domain\Workflow\Repositories;

use App\Domain\Workflow\Data\ApprovalRequestSnapshot;

interface ApprovalRequestRepositoryInterface
{
    public function create(
        int $schoolId,
        int $flowId,
        string $entityType,
        int $entityId,
        int $currentStep,
        int $status,
        ?int $requestedBy,
        string $createdAt,
    ): int;

    public function hasOpenForEntity(int $schoolId, string $entityType, int $entityId): bool;

    public function findById(int $schoolId, int $requestId): ?ApprovalRequestSnapshot;

    public function applyDecision(
        int $schoolId,
        int $requestId,
        int $status,
        int $currentStep,
        ?string $completedAt,
    ): void;

    /**
     * @return list<ApprovalRequestSnapshot>
     */
    public function listBySchool(
        int $schoolId,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $status = null,
    ): array;
}
