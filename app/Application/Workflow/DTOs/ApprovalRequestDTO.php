<?php

namespace App\Application\Workflow\DTOs;

final readonly class ApprovalRequestDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $flowId,
        public string $entityType,
        public int $entityId,
        public int $currentStep,
        public int $status,
        public ?int $requestedBy,
        public string $createdAt,
        public ?string $completedAt,
    ) {}
}
