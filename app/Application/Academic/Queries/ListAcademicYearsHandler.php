<?php

namespace App\Application\Academic\Queries;

use App\Application\Academic\DTOs\AcademicYearDTO;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;

final class ListAcademicYearsHandler
{
    public function __construct(
        private readonly AcademicYearRepositoryInterface $years,
    ) {}

    /**
     * @return list<AcademicYearDTO>
     */
    public function handle(ListAcademicYearsQuery $query): array
    {
        return array_map(
            static fn ($row): AcademicYearDTO => new AcademicYearDTO(
                id: $row->id,
                code: $row->code,
                name: $row->name,
                startDate: $row->startDate,
                endDate: $row->endDate,
                isCurrent: $row->isCurrent,
                status: $row->status,
                createdAt: $row->createdAt,
                updatedAt: $row->updatedAt,
            ),
            $this->years->listAll(),
        );
    }
}
