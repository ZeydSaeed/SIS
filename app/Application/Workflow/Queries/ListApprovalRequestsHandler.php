<?php

namespace App\Application\Workflow\Queries;

use App\Application\Workflow\DTOs\ApprovalRequestDTO;
use App\Domain\Workflow\Repositories\ApprovalRequestRepositoryInterface;

final class ListApprovalRequestsHandler
{
    public function __construct(
        private readonly ApprovalRequestRepositoryInterface $requests,
    ) {}

    /**
     * @return list<ApprovalRequestDTO>
     */
    public function handle(ListApprovalRequestsQuery $query): array
    {
        return array_map(
            static fn ($s): ApprovalRequestDTO => new ApprovalRequestDTO(
                id: $s->id,
                schoolId: $s->schoolId,
                flowId: $s->flowId,
                entityType: $s->entityType,
                entityId: $s->entityId,
                currentStep: $s->currentStep,
                status: $s->status,
                requestedBy: $s->requestedBy,
                createdAt: $s->createdAt,
                completedAt: $s->completedAt,
            ),
            $this->requests->listBySchool(
                $query->schoolId,
                $query->entityType,
                $query->entityId,
                $query->requestStatus,
            ),
        );
    }
}
