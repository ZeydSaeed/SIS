<?php

namespace App\Domain\Workflow\Repositories;

use App\Domain\Workflow\Data\ApprovalFlowSnapshot;

interface ApprovalFlowRepositoryInterface
{
    /**
     * @param  list<array{step:int, role:string}>  $steps
     */
    public function create(
        int $schoolId,
        string $entityType,
        string $name,
        array $steps,
        bool $isActive,
        string $createdAt,
    ): int;

    /**
     * @return list<ApprovalFlowSnapshot>
     */
    public function listBySchool(int $schoolId, ?string $entityType = null, ?bool $activeOnly = null): array;

    public function findById(int $schoolId, int $flowId): ?ApprovalFlowSnapshot;

    /**
     * Active flow for entity type (deterministic: lowest id when multiple).
     */
    public function findActiveByEntityType(int $schoolId, string $entityType): ?ApprovalFlowSnapshot;
}
