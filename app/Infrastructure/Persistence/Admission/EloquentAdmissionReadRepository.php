<?php

namespace App\Infrastructure\Persistence\Admission;

use App\Application\Admission\Contracts\AdmissionReadRepositoryInterface;
use App\Database\SchemaHelper;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use Illuminate\Support\Facades\DB;

/**
 * Admission workspace reads — cached shell, windowed pagination,
 * Active-period SQL scope, lean stage rows (no per-row transition arrays).
 */
final class EloquentAdmissionReadRepository implements AdmissionReadRepositoryInterface
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly AdmissionWorkspaceCache $cache,
    ) {}

    public function periodShell(int $schoolId, int $academicYearId): array
    {
        return [
            'periods' => $this->cache->rememberPeriods(
                $schoolId,
                $academicYearId,
                fn (): array => $this->loadPeriods($schoolId, $academicYearId),
            ),
            'period_counts' => $this->cache->rememberPeriodCounts(
                $schoolId,
                $academicYearId,
                fn (): array => $this->loadPeriodApplicationCounts($schoolId, $academicYearId),
            ),
        ];
    }

    public function workspace(
        int $schoolId,
        int $academicYearId,
        ?int $statusFilter = null,
        bool $includeApplications = true,
        int $page = 1,
        int $perPage = self::DEFAULT_PER_PAGE,
        ?int $applicationPeriodId = null,
        ?string $search = null,
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));

        $periods = $this->cache->rememberPeriods(
            $schoolId,
            $academicYearId,
            fn (): array => $this->loadPeriods($schoolId, $academicYearId),
        );
        $periodCounts = $this->cache->rememberPeriodCounts(
            $schoolId,
            $academicYearId,
            fn (): array => $this->loadPeriodApplicationCounts($schoolId, $academicYearId),
        );
        $statusCounts = $this->cache->rememberStatusCounts(
            $schoolId,
            $academicYearId,
            $applicationPeriodId,
            fn (): array => $this->loadActivePeriodStatusCounts(
                $schoolId,
                $academicYearId,
                $applicationPeriodId,
            ),
        );
        $refs = $this->cache->rememberSchoolRefs(
            $schoolId,
            fn (): array => [
                'schools' => $this->loadSchools($schoolId),
                'branches' => $this->loadBranches($schoolId),
                'departments' => $this->loadDepartments($schoolId),
                'specializations' => $this->loadSpecializations($schoolId),
            ],
        );
        $gradeLevels = $this->cache->rememberGradeLevels(
            fn (): array => $this->loadGradeLevels(),
        );

        $applications = [];
        $documents = [];
        $total = 0;
        $totalPages = 1;

        if ($includeApplications) {
            [$applications, $total] = $this->paginatedActivePeriodApplications(
                $schoolId,
                $academicYearId,
                $statusFilter,
                $page,
                $perPage,
                $applicationPeriodId,
                $search,
            );
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
            if ($page > $totalPages) {
                $page = $totalPages;
                [$applications, $total] = $this->paginatedActivePeriodApplications(
                    $schoolId,
                    $academicYearId,
                    $statusFilter,
                    $page,
                    $perPage,
                    $applicationPeriodId,
                    $search,
                );
            }
            $applicationIds = array_map(static fn (array $app): int => $app['id'], $applications);
            $documents = $this->documentTypeLabels($applicationIds);
        }

        return [
            'periods' => $periods,
            'applications' => $applications,
            'documents' => $documents,
            'grade_levels' => $gradeLevels,
            'schools' => $refs['schools'],
            'branches' => $refs['branches'],
            'departments' => $refs['departments'],
            'specializations' => $refs['specializations'],
            'workflow_steps' => $this->workflowSteps(),
            'period_counts' => $periodCounts,
            'status_counts' => $statusCounts,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * @return list<array{status:int, key:string}>
     */
    private function workflowSteps(): array
    {
        return array_map(
            static fn (ApplicationStatus $status): array => [
                'status' => $status->value,
                'key' => $status->name,
            ],
            ApplicationStatus::pipelineSteps(),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadPeriods(int $schoolId, int $academicYearId): array
    {
        return DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->orderByDesc('id')
            ->get([
                'id',
                'academic_year_id',
                'school_id',
                'name',
                'start_date',
                'end_date',
                'max_applications',
                'status',
                'created_at',
            ])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'academic_year_id' => (int) $row->academic_year_id,
                'school_id' => (int) $row->school_id,
                'name' => (string) $row->name,
                'start_date' => (string) $row->start_date,
                'end_date' => (string) $row->end_date,
                'max_applications' => $row->max_applications !== null ? (int) $row->max_applications : null,
                'status' => (int) $row->status,
                'created_at' => (string) $row->created_at,
            ])
            ->all();
    }

    private function activeApplicationsBaseQuery(
        int $schoolId,
        int $academicYearId,
        ?int $statusFilter,
        ?int $applicationPeriodId = null,
        ?string $search = null,
    ) {
        $query = DB::table(SchemaHelper::qualified('admission', 'applications').' as apps')
            ->join(
                SchemaHelper::qualified('admission', 'application_periods').' as periods',
                'periods.id',
                '=',
                'apps.application_period_id',
            )
            ->where('periods.school_id', $schoolId)
            ->where('periods.academic_year_id', $academicYearId)
            ->where('periods.status', ApplicationPeriodStatus::Active->value);

        if ($applicationPeriodId !== null) {
            $query->where('apps.application_period_id', $applicationPeriodId);
        }

        if ($statusFilter !== null) {
            $query->where('apps.status', $statusFilter);
        }

        $needle = trim((string) $search);
        if ($needle !== '') {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_substr($needle, 0, 80)).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('apps.first_name', 'ilike', $term)
                    ->orWhere('apps.father_name', 'ilike', $term)
                    ->orWhere('apps.grandfather_name', 'ilike', $term)
                    ->orWhere('apps.great_grandfather_name', 'ilike', $term)
                    ->orWhere('apps.last_name', 'ilike', $term)
                    ->orWhere('apps.application_number', 'ilike', $term)
                    ->orWhereRaw(
                        "concat_ws(' ', apps.first_name, apps.father_name, apps.grandfather_name, apps.great_grandfather_name, apps.last_name) ilike ?",
                        [$term],
                    );
            });
        }

        return $query;
    }

    /**
     * One round-trip: page rows + total via COUNT(*) OVER().
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function paginatedActivePeriodApplications(
        int $schoolId,
        int $academicYearId,
        ?int $statusFilter,
        int $page,
        int $perPage,
        ?int $applicationPeriodId = null,
        ?string $search = null,
    ): array {
        $offset = ($page - 1) * $perPage;

        $rows = $this->activeApplicationsBaseQuery(
            $schoolId,
            $academicYearId,
            $statusFilter,
            $applicationPeriodId,
            $search,
        )
            ->orderBy('apps.created_at')
            ->orderBy('apps.id')
            ->offset($offset)
            ->limit($perPage)
            ->get([
                'apps.id',
                'apps.application_period_id',
                'apps.application_number',
                'apps.first_name',
                'apps.father_name',
                'apps.grandfather_name',
                'apps.great_grandfather_name',
                'apps.last_name',
                'apps.mother_name',
                'apps.target_school_id',
                'apps.branch_id',
                'apps.grade_level_id',
                'apps.intended_grade_name',
                'apps.department_name',
                'apps.specialization_id',
                'apps.specialization_name',
                'apps.status',
                'apps.submitted_at',
                'apps.reviewed_by',
                'apps.reviewed_at',
                'apps.notes',
                'apps.created_at',
                'apps.updated_at',
                DB::raw('COUNT(*) OVER() as full_count'),
            ]);

        $total = $rows->isEmpty() ? 0 : (int) $rows->first()->full_count;

        $applications = $rows->map(static function ($row): array {
            return [
                'id' => (int) $row->id,
                'application_period_id' => (int) $row->application_period_id,
                'application_number' => (string) $row->application_number,
                'first_name' => (string) $row->first_name,
                'father_name' => $row->father_name !== null ? (string) $row->father_name : null,
                'grandfather_name' => $row->grandfather_name !== null ? (string) $row->grandfather_name : null,
                'great_grandfather_name' => $row->great_grandfather_name !== null ? (string) $row->great_grandfather_name : null,
                'last_name' => (string) $row->last_name,
                'mother_name' => $row->mother_name !== null ? (string) $row->mother_name : null,
                'target_school_id' => $row->target_school_id !== null ? (int) $row->target_school_id : null,
                'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
                'grade_level_id' => $row->grade_level_id !== null ? (int) $row->grade_level_id : null,
                'intended_grade_name' => $row->intended_grade_name !== null ? (string) $row->intended_grade_name : null,
                'department_name' => $row->department_name !== null ? (string) $row->department_name : null,
                'specialization_id' => $row->specialization_id !== null ? (int) $row->specialization_id : null,
                'specialization_name' => $row->specialization_name !== null ? (string) $row->specialization_name : null,
                'status' => (int) $row->status,
                'submitted_at' => $row->submitted_at !== null ? (string) $row->submitted_at : null,
                'reviewed_by' => $row->reviewed_by !== null ? (int) $row->reviewed_by : null,
                'reviewed_at' => $row->reviewed_at !== null ? (string) $row->reviewed_at : null,
                'notes' => $row->notes !== null ? (string) $row->notes : null,
                'created_at' => (string) $row->created_at,
                'updated_at' => (string) $row->updated_at,
            ];
        })->all();

        return [$applications, $total];
    }

    /**
     * @param  list<int>  $applicationIds
     * @return list<array{id:int, application_id:int, document_type:int}>
     */
    private function documentTypeLabels(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        return DB::table(SchemaHelper::qualified('admission', 'application_documents'))
            ->whereIn('application_id', $applicationIds)
            ->orderBy('application_id')
            ->orderBy('document_type')
            ->get(['id', 'application_id', 'document_type'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'application_id' => (int) $row->application_id,
                'document_type' => (int) $row->document_type,
            ])
            ->all();
    }

    /**
     * @return array<int, array{total: int, submitted: int}>
     */
    private function loadPeriodApplicationCounts(int $schoolId, int $academicYearId): array
    {
        $submittedFrom = ApplicationStatus::Submitted->value;
        $rows = DB::table(SchemaHelper::qualified('admission', 'applications').' as apps')
            ->join(
                SchemaHelper::qualified('admission', 'application_periods').' as periods',
                'periods.id',
                '=',
                'apps.application_period_id',
            )
            ->where('periods.school_id', $schoolId)
            ->where('periods.academic_year_id', $academicYearId)
            ->groupBy('apps.application_period_id')
            ->select('apps.application_period_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN apps.status >= ? THEN 1 ELSE 0 END) as submitted',
                [$submittedFrom],
            )
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->application_period_id] = [
                'total' => (int) $row->total,
                'submitted' => (int) $row->submitted,
            ];
        }

        return $counts;
    }

    /**
     * @return array<int, int>
     */
    private function loadActivePeriodStatusCounts(
        int $schoolId,
        int $academicYearId,
        ?int $applicationPeriodId = null,
    ): array {
        $query = DB::table(SchemaHelper::qualified('admission', 'applications').' as apps')
            ->join(
                SchemaHelper::qualified('admission', 'application_periods').' as periods',
                'periods.id',
                '=',
                'apps.application_period_id',
            )
            ->where('periods.school_id', $schoolId)
            ->where('periods.academic_year_id', $academicYearId)
            ->where('periods.status', ApplicationPeriodStatus::Active->value);

        if ($applicationPeriodId !== null) {
            $query->where('apps.application_period_id', $applicationPeriodId);
        }

        $rows = $query
            ->groupBy('apps.status')
            ->select('apps.status')
            ->selectRaw('COUNT(*) as total')
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->status] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * @return list<array{id:int, name:string}>
     */
    private function loadGradeLevels(): array
    {
        return DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->where('status', 1)
            ->orderBy('level_order')
            ->get(['id', 'name'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();
    }

    /**
     * @return list<array{id:int, name:string}>
     */
    private function loadSchools(int $schoolId): array
    {
        return DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('id', $schoolId)
            ->get(['id', 'name'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();
    }

    /**
     * @return list<array{id:int, name:string}>
     */
    private function loadBranches(int $schoolId): array
    {
        return DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();
    }

    /**
     * @return list<array{id:int, branch_id:int|null, name:string}>
     */
    private function loadDepartments(int $schoolId): array
    {
        return DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'branch_id', 'name'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
                'name' => (string) $row->name,
            ])
            ->all();
    }

    /**
     * @return list<array{id:int, department_id:int|null, name:string}>
     */
    private function loadSpecializations(int $schoolId): array
    {
        return DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'department_id', 'name'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'department_id' => $row->department_id !== null ? (int) $row->department_id : null,
                'name' => (string) $row->name,
            ])
            ->all();
    }
}
