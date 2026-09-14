<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Enrollment\DTOs\SectionDTO;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;

final class GetSectionHandler
{
    public function __construct(
        private readonly EnrollmentStructureRepositoryInterface $structure,
    ) {}

    public function handle(GetSectionQuery $query): ?SectionDTO
    {
        $row = $this->structure->findSection($query->schoolId, $query->sectionId);
        if ($row === null) {
            return null;
        }

        return new SectionDTO(
            id: $row->id,
            classId: $row->classId,
            schoolId: $row->schoolId,
            code: $row->code,
            name: $row->name,
            capacity: $row->capacity,
            homeroomTeacherId: $row->homeroomTeacherId,
            status: $row->status,
            createdAt: $row->createdAt,
            updatedAt: $row->updatedAt,
        );
    }
}
