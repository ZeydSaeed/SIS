<?php

namespace App\Application\Hr\Queries;

use App\Application\Hr\DTOs\JobPositionDTO;
use App\Domain\Hr\Repositories\HrRepositoryInterface;

final class ListJobPositionsHandler
{
    public function __construct(
        private readonly HrRepositoryInterface $hr,
    ) {}

    /**
     * @return list<JobPositionDTO>
     */
    public function handle(ListJobPositionsQuery $query): array
    {
        return array_map(
            static fn ($s): JobPositionDTO => new JobPositionDTO(
                id: $s->id,
                schoolId: $s->schoolId,
                code: $s->code,
                name: $s->name,
                category: $s->category,
                status: $s->status,
                createdAt: $s->createdAt,
                updatedAt: $s->updatedAt,
            ),
            $this->hr->listJobPositions($query->schoolId, $query->status),
        );
    }
}
