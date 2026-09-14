<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Enrollment\DTOs\SectionDTO;
use App\Domain\Enrollment\Repositories\EnrollmentStructureRepositoryInterface;

final class ListClassSectionsHandler
{
    public function __construct(
        private readonly EnrollmentStructureRepositoryInterface $structure,
    ) {}

    /**
     * @return list<SectionDTO>|null null when class not in school
     */
    public function handle(ListClassSectionsQuery $query): ?array
    {
        $rows = $this->structure->listSectionsForClass($query->schoolId, $query->classId);
        if ($rows === null) {
            return null;
        }

        return array_map(
            static fn ($row): SectionDTO => new SectionDTO(
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
            ),
            $rows,
        );
    }
}
