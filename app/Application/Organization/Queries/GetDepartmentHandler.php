<?php

namespace App\Application\Organization\Queries;

use App\Application\Organization\DTOs\DepartmentDTO;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;

final class GetDepartmentHandler
{
    public function __construct(
        private readonly DepartmentRepositoryInterface $departments,
    ) {}

    public function handle(GetDepartmentQuery $query): ?DepartmentDTO
    {
        $row = $this->departments->findForSchool($query->schoolId, $query->departmentId);
        if ($row === null) {
            return null;
        }

        return new DepartmentDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            branchId: $row->branchId,
            code: $row->code,
            name: $row->name,
            departmentType: $row->departmentType,
            status: $row->status,
            createdAt: $row->createdAt,
            updatedAt: $row->updatedAt,
        );
    }
}
