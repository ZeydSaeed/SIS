<?php

namespace App\Application\Communication\Queries;

use App\Application\Communication\DTOs\NotificationJobDTO;
use App\Domain\Communication\Repositories\NotificationJobRepositoryInterface;

final class ListNotificationJobsHandler
{
    public function __construct(
        private readonly NotificationJobRepositoryInterface $jobs,
    ) {}

    /** @return list<NotificationJobDTO> */
    public function handle(ListNotificationJobsQuery $query): array
    {
        $limit = min(max($query->limit, 1), 100);
        $rows = $this->jobs->listForSchool($query->schoolId, $query->status, $limit);

        return array_map(static fn ($row): NotificationJobDTO => new NotificationJobDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            jobId: $row->jobId,
            templateId: $row->templateId,
            targetFilter: $row->targetFilter,
            totalCount: $row->totalCount,
            sentCount: $row->sentCount,
            status: $row->status,
            createdBy: $row->createdBy,
            createdAt: $row->createdAt,
            completedAt: $row->completedAt,
        ), $rows);
    }
}
