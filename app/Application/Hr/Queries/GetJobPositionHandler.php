<?php

namespace App\Application\Hr\Queries;

use App\Application\Hr\DTOs\JobPositionDTO;
use App\Domain\Hr\Repositories\HrRepositoryInterface;

final class GetJobPositionHandler
{
    public function __construct(
        private readonly HrRepositoryInterface $hr,
    ) {}

    public function handle(GetJobPositionQuery $query): ?JobPositionDTO
    {
        $s = $this->hr->findJobPosition($query->schoolId, $query->jobPositionId);
        if ($s === null) {
            return null;
        }

        return new JobPositionDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            code: $s->code,
            name: $s->name,
            category: $s->category,
            status: $s->status,
            createdAt: $s->createdAt,
            updatedAt: $s->updatedAt,
        );
    }
}
