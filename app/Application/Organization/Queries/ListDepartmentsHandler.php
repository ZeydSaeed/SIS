<?php

namespace App\Application\Organization\Queries;

use App\Application\Organization\DTOs\DepartmentDTO;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;

final class ListDepartmentsHandler
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
    ) {}

    /**
     * @return list<DepartmentDTO>
     */
    public function handle(ListDepartmentsQuery $query): array
    {
        return array_map(
            static fn ($row): DepartmentDTO => new DepartmentDTO(
                id: $row->id,
                schoolId: $row->schoolId,
                branchId: $row->branchId,
                code: $row->code,
                name: $row->name,
                departmentType: $row->departmentType,
                status: $row->status,
                createdAt: $row->createdAt,
                updatedAt: $row->updatedAt,
            ),
            $this->departments->listForSchool($query->schoolId, $query->branchId),
        );
    }
}
