<?php

namespace App\Application\Enrollment\Contracts;

use App\Application\Enrollment\DTOs\EnrollmentDTO;

interface EnrollmentReadRepositoryInterface
{
    public function findDetail(int $enrollmentId, int $schoolId): ?EnrollmentDTO;

    /**
     * `$classId` / `$sectionId` / `$branchId` / `$departmentId`: one id or a list of ids (several choices of one ribbon filter).
     *
     * @param  int|list<int>|null  $classId
     * @param  int|list<int>|null  $sectionId
     * @param  int|list<int>|null  $branchId
     * @param  int|list<int>|null  $departmentId
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
        int|array|null $classId = null,
        int|array|null $sectionId = null,
        ?string $departmentName = null,
        ?int $specializationId = null,
        int|array|null $branchId = null,
        int|array|null $departmentId = null,
        ?int $gradeLevelId = null,
    ): array;

    /**
     * @return array<int, int>
     */
    public function countByStatus(
        int $schoolId,
        ?int $academicYearId = null,
        ?int $gender = null,
        int|array|null $classId = null,
        int|array|null $sectionId = null,
        ?string $departmentName = null,
        ?int $specializationId = null,
        int|array|null $branchId = null,
        int|array|null $departmentId = null,
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
    public function listFilterOptions(int $schoolId, ?int $academicYearId, int|array|null $classId = null): array;

    /**
     * Full placement timeline for one or more students (active + superseded segments).
     *
     * @param list<int> $studentIds
     * @return list<EnrollmentDTO>
     */
    public function listPlacementHistory(
        int $schoolId,
        array $studentIds,
        ?int $academicYearId = null,
    ): array;
}
