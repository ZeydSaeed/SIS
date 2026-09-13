<?php

namespace App\Application\Hr\Queries;

use App\Application\Hr\DTOs\EmployeeDTO;
use App\Domain\Hr\Repositories\HrRepositoryInterface;

final class GetEmployeeHandler
{
    public function __construct(
        private readonly HrRepositoryInterface $hr,
    ) {}

    public function handle(GetEmployeeQuery $query): ?EmployeeDTO
    {
        $s = $this->hr->findEmployeeInSchool($query->schoolId, $query->employeeId);
        if ($s === null) {
            return null;
        }

        return new EmployeeDTO(
            id: $s->id,
            employeeNumber: $s->employeeNumber,
            userId: $s->userId,
            teacherId: $s->teacherId,
            nationalId: $s->nationalId,
            firstName: $s->firstName,
            lastName: $s->lastName,
            fullName: $s->fullName,
            hireDate: $s->hireDate,
            status: $s->status,
            jobPositionId: $s->jobPositionId,
            academicYearId: $s->academicYearId,
            createdAt: $s->createdAt,
        );
    }
}
