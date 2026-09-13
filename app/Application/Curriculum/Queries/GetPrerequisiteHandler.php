<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\PrerequisiteDTO;
use App\Domain\Curriculum\Repositories\PrerequisiteRepositoryInterface;

final class GetPrerequisiteHandler
{
    public function __construct(
        private readonly PrerequisiteRepositoryInterface $prerequisites,
    ) {}

    public function handle(GetPrerequisiteQuery $query): ?PrerequisiteDTO
    {
        $row = $this->prerequisites->findActive($query->prerequisiteId)
            ?? $this->prerequisites->findInactive($query->prerequisiteId);
        if ($row === null) {
            return null;
        }

        return new PrerequisiteDTO(
            id: $row->id,
            subjectId: $row->subjectId,
            prerequisiteSubjectId: $row->prerequisiteSubjectId,
            status: $row->status,
            createdAt: $row->createdAt,
        );
    }
}
