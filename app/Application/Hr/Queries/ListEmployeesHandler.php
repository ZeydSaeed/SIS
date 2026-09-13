<?php

namespace App\Application\Hr\Queries;

use App\Application\Hr\DTOs\EmployeeDTO;
use App\Domain\Hr\Repositories\HrRepositoryInterface;

final class ListEmployeesHandler
{
    public function __construct(
        private readonly HrRepositoryInterface $hr,
    ) {}

    /**
     * @return list<EmployeeDTO>
     */
    public function handle(ListEmployeesQuery $query): array
    {
        return array_map(
            static fn ($s): EmployeeDTO => new EmployeeDTO(
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
            ),
            $this->hr->listEmployeesBySchool($query->schoolId, $query->academicYearId, $query->status),
        );
    }
}
