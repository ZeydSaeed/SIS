<?php

namespace App\Application\Workflow\Queries;

use App\Application\Workflow\DTOs\ApprovalFlowDTO;
use App\Domain\Workflow\Repositories\ApprovalFlowRepositoryInterface;

final class ListApprovalFlowsHandler
{
    public function __construct(
        private readonly ApprovalFlowRepositoryInterface $flows,
    ) {}

    /**
     * @return list<ApprovalFlowDTO>
     */
    public function handle(ListApprovalFlowsQuery $query): array
    {
        return array_map(
            static fn ($s): ApprovalFlowDTO => new ApprovalFlowDTO(
                id: $s->id,
                schoolId: $s->schoolId,
                entityType: $s->entityType,
                name: $s->name,
                steps: $s->steps,
                isActive: $s->isActive,
                createdAt: $s->createdAt,
            ),
            $this->flows->listBySchool($query->schoolId, $query->entityType, $query->activeOnly),
        );
    }
}
