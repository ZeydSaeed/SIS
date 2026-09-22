<?php

namespace App\Application\Enrollment\Contracts;

use App\Application\Enrollment\DTOs\EnrollmentDTO;

interface EnrollmentReadRepositoryInterface
{
    public function findDetail(int $enrollmentId, int $schoolId): ?EnrollmentDTO;

    /**
     * @return array{items: list<EnrollmentDTO>, pagination: array{page: int, per_page: int, total: int, last_page: int}}
     */
    public function paginate(
        int $schoolId,
        ?int $academicYearId,
        int $page,
        int $perPage,
        ?int $status = null,
        string $q = '',
        ?int $gender = null,
        ?int $classId = null,
        ?int $sectionId = null,
        ?string $departmentName = null,
        ?int $specializationId = null,
        ?int $branchId = null,
        ?int $departmentId = null,
    ): array;

    /**
     * @return array<int, int>
     */
    public function countByStatus(
        int $schoolId,
        ?int $academicYearId = null,
        ?int $gender = null,
        ?int $classId = null,
        ?int $sectionId = null,
        ?string $departmentName = null,
        ?int $specializationId = null,
        ?int $branchId = null,
        ?int $departmentId = null,
    ): array;

    /**
     * @return array{
     *   branches: list<array{id:int,code:string,name:string}>,
     *   classes: list<array{id:int,code:string,name:string}>,
     *   sections: list<array{id:int,class_id:int,code:string,name:string}>,
     *   departments: list<array{id:int,branch_id:int|null,code:string,name:string}>,
     *   specializations: list<array{id:int,department_id:int|null,code:string,name:string}>
     * }
     */
    public function listFilterOptions(int $schoolId, ?int $academicYearId, ?int $classId = null): array;
}
