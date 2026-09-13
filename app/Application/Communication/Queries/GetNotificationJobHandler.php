<?php

namespace App\Application\Communication\Queries;

use App\Application\Communication\DTOs\NotificationJobDTO;
use App\Domain\Communication\Repositories\NotificationJobRepositoryInterface;

final class GetNotificationJobHandler
{
    public function __construct(
        private readonly NotificationJobRepositoryInterface $jobs,
    ) {}

    public function handle(GetNotificationJobQuery $query): ?NotificationJobDTO
    {
        $s = $this->jobs->findByIdForSchool($query->schoolId, $query->jobId);
        if ($s === null) {
            return null;
        }

        return new NotificationJobDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            jobId: $s->jobId,
            templateId: $s->templateId,
            targetFilter: $s->targetFilter,
            totalCount: $s->totalCount,
            sentCount: $s->sentCount,
            status: $s->status,
            createdBy: $s->createdBy,
            createdAt: $s->createdAt,
            completedAt: $s->completedAt,
        );
    }
}
