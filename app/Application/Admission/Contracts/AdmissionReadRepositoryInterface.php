<?php

namespace App\Application\Admission\Contracts;

interface AdmissionReadRepositoryInterface
{
    /**
     * Lightweight period list + occupancy counts (no applications).
     *
     * @return array{
     *     periods: list<array<string, mixed>>,
     *     period_counts: array<int, array{total:int, submitted:int}>
     * }
     */
    public function periodShell(int $schoolId, int $academicYearId): array;

    /**
     * @return array{
     *     periods: list<array<string, mixed>>,
     *     applications: list<array<string, mixed>>,
     *     documents: list<array<string, mixed>>,
     *     grade_levels: list<array{id:int, name:string}>,
     *     schools: list<array{id:int, name:string}>,
     *     branches: list<array{id:int, name:string}>,
     *     departments: list<array{id:int, branch_id:int|null, name:string}>,
     *     specializations: list<array{id:int, department_id:int|null, name:string}>,
     *     workflow_steps: list<array{status:int, key:string}>,
     *     period_counts: array<int, array{total:int, submitted:int}>,
     *     status_counts: array<int, int>,
     *     pagination: array{page:int, per_page:int, total:int, total_pages:int}
     * }
     */
    public function workspace(
        int $schoolId,
        int $academicYearId,
        ?int $statusFilter = null,
        bool $includeApplications = true,
        int $page = 1,
        int $perPage = 17,
        ?int $applicationPeriodId = null,
        ?string $search = null,
        ?string $enrollmentStatus = null,
    ): array;
}
