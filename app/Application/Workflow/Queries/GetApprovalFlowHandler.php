<?php

namespace App\Application\Workflow\Queries;

use App\Application\Workflow\DTOs\ApprovalFlowDTO;
use App\Domain\Workflow\Repositories\ApprovalFlowRepositoryInterface;

final class GetApprovalFlowHandler
{
    public function __construct(
        private readonly ApprovalFlowRepositoryInterface $flows,
    ) {}

    public function handle(GetApprovalFlowQuery $query): ?ApprovalFlowDTO
    {
        $s = $this->flows->findById($query->schoolId, $query->flowId);
        if ($s === null) {
            return null;
        }

        return new ApprovalFlowDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            entityType: $s->entityType,
            name: $s->name,
            steps: $s->steps,
            isActive: $s->isActive,
            createdAt: $s->createdAt,
        );
    }
}
