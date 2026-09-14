<?php

namespace App\Application\Academic\Queries;

use App\Application\Academic\DTOs\AcademicYearDTO;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;

final class GetAcademicYearHandler
{
    public function __construct(
        private readonly AcademicYearRepositoryInterface $years,
    ) {}

    public function handle(GetAcademicYearQuery $query): ?AcademicYearDTO
    {
        $row = $this->years->findById($query->academicYearId);
        if ($row === null) {
            return null;
        }

        return new AcademicYearDTO(
            id: $row->id,
            code: $row->code,
            name: $row->name,
            startDate: $row->startDate,
            endDate: $row->endDate,
            isCurrent: $row->isCurrent,
            status: $row->status,
            createdAt: $row->createdAt,
            updatedAt: $row->updatedAt,
        );
    }
}
