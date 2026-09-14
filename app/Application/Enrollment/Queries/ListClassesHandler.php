<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Enrollment\DTOs\ClassDTO;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;

final class ListClassesHandler
{
    public function __construct(
        private readonly EnrollmentStructureRepositoryInterface $structure,
    ) {}

    /**
     * @return list<ClassDTO>
     */
    public function handle(ListClassesQuery $query): array
    {
        return array_map(
            static fn ($row): ClassDTO => new ClassDTO(
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
            ),
            $this->structure->listClasses($query->schoolId, $query->academicYearId),
        );
    }
}
