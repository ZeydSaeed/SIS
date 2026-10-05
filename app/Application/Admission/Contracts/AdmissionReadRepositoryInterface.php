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
     * Schools an application may be filed in (the user's active linked schools),
     * each with its active branches and each branch's active departments (الاختصاص).
     *
     * @param  list<int>  $schoolIds
     * @return list<array{
     *     id:int,
     *     name:string,
     *     directorate_id:int,
     *     directorate_name:string,
     *     directorate_active:bool,
     *     branches: list<array{id:int, name:string, departments: list<array{id:int, name:string}>}>
     * }>
     */
    public function schoolOptions(array $schoolIds): array;

    /**
     * «متابعة طلبات التقديم» roster across the given (authorized) schools — each row
     * carries its school so the dialog can show the students of every school.
     *
     * @param  list<int>  $schoolIds
     * @return list<array<string, mixed>>
     */
    public function acceptedRoster(array $schoolIds, int $academicYearId): array;

    /**
     * Applications of the school that can still move (not converted to a student).
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function transferableApplications(int $schoolId, ?string $search, int $page, int $perPage): array;

    /**
     * Latest transfers into or out of the school.
     *
     * @return list<array<string, mixed>>
     */
    public function transferHistory(int $schoolId, int $limit): array;

    /**
     * Active periods of every academic year (shared by all schools).
     *
     * @return list<array{id:int, name:string, academic_year_id:int, academic_year_name:string}>
     */
    public function activePeriodsWithYears(): array;

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
     *     pagination: array{page:int, per_page:int, total:int, total_pages:int},
     *     accepted_students: list<array{
     *         id:int,
     *         full_name:string,
     *         academic_year_id:int,
     *         academic_year_name:string,
     *         period_id:int,
     *         period_name:string,
     *         request_kind:int,
     *         status:int,
     *         notes:?string,
     *         rejection_reason:?string,
     *         withdrawal_reason:?string
     *     }>
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
        bool $includeAcceptedStudents = false,
    ): array;
}
