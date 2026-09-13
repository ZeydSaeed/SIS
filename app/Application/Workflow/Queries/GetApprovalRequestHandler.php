<?php

namespace App\Application\Workflow\Queries;

use App\Application\Workflow\DTOs\ApprovalRequestDTO;
use App\Domain\Workflow\Repositories\ApprovalRequestRepositoryInterface;

final class GetApprovalRequestHandler
{
    public function __construct(
        private readonly ApprovalRequestRepositoryInterface $requests,
    ) {}

    public function handle(GetApprovalRequestQuery $query): ?ApprovalRequestDTO
    {
        $s = $this->requests->findById($query->schoolId, $query->requestId);
        if ($s === null) {
            return null;
        }

        return new ApprovalRequestDTO(
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
        );
    }
}
