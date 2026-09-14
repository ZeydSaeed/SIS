<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Enrollment\DTOs\ClassDTO;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;

final class GetClassHandler
{
    public function __construct(
        private readonly EnrollmentStructureRepositoryInterface $structure,
    ) {}

    public function handle(GetClassQuery $query): ?ClassDTO
    {
        $row = $this->structure->findClass($query->schoolId, $query->classId);
        if ($row === null) {
            return null;
        }

        return new ClassDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            academicYearId: $row->academicYearId,
            gradeLevelId: $row->gradeLevelId,
            code: $row->code,
            name: $row->name,
            capacity: $row->capacity,
            status: $row->status,
            createdAt: $row->createdAt,
            updatedAt: $row->updatedAt,
        );
    }
}
