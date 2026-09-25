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
    private const DEFAULT_PER_PAGE = 17;

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
        ?string $enrollmentStatus = null,
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
                $enrollmentStatus,
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
                    $enrollmentStatus,
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
            'accepted_students' => $this->loadAcceptedStudents(
                $schoolId,
                $academicYearId,
                $applicationPeriodId,
            ),
        ];
    }

    /**
     * Converted applicants linked to a student row (ribbon «الطلبة المقبولين»).
     *
     * @return list<array{id:int, full_name:string, academic_year_id:int, academic_year_name:string, period_id:int, period_name:string}>
     */
    private function loadAcceptedStudents(
        int $schoolId,
        int $academicYearId,
        ?int $applicationPeriodId,
    ): array {
        unset($applicationPeriodId);
        $apps = SchemaHelper::qualified('admission', 'applications');
        $periods = SchemaHelper::qualified('admission', 'application_periods');
        $years = SchemaHelper::qualified('academic', 'academic_years');

        // School-wide roster for the dialog filters (year + period).
        $query = DB::table($apps.' as apps')
            ->join($periods.' as periods', 'periods.id', '=', 'apps.application_period_id')
            ->join($years.' as years', 'years.id', '=', 'periods.academic_year_id')
            ->where('periods.school_id', $schoolId)
            ->where('apps.status', ApplicationStatus::Converted->value)
            ->whereNotNull('apps.student_id')
            ->select([
                'apps.id',
                'apps.first_name',
                'apps.father_name',
                'apps.grandfather_name',
                'apps.great_grandfather_name',
                'apps.last_name',
                'periods.id as period_id',
                'periods.name as period_name',
                'years.id as academic_year_id',
                'years.name as academic_year_name',
            ]);

        if ($academicYearId > 0) {
            $query->orderByRaw('case when periods.academic_year_id = ? then 0 else 1 end', [$academicYearId]);
        }

        return $query
            ->orderByDesc('apps.id')
            ->limit(500)
            ->get()
            ->map(static function ($row): array {
            $parts = array_filter([
                (string) $row->first_name,
                $row->father_name !== null ? (string) $row->father_name : null,
                $row->grandfather_name !== null ? (string) $row->grandfather_name : null,
                $row->great_grandfather_name !== null ? (string) $row->great_grandfather_name : null,
                (string) $row->last_name,
            ], static fn (?string $part): bool => $part !== null && trim($part) !== '');

            return [
                'id' => (int) $row->id,
                'full_name' => implode(' ', $parts),
                'academic_year_id' => (int) $row->academic_year_id,
                'academic_year_name' => (string) $row->academic_year_name,
                'period_id' => (int) $row->period_id,
                'period_name' => (string) $row->period_name,
            ];
        })->all();
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
        ?string $enrollmentStatus = null,
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

        $this->applyEnrollmentStatusFilter($query, $schoolId, $academicYearId, $statusFilter, $enrollmentStatus);

        return $query;
    }

    /**
     * Filter converted applicants by student-profile completeness
     * (same checklist as resources/js/.../student-profile-gaps.ts).
     * awaiting = has gaps → UI «متابعة الملف»
     * completed = no gaps → UI «مستوفي»
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyEnrollmentStatusFilter(
        $query,
        int $schoolId,
        int $academicYearId,
        ?int $statusFilter,
        ?string $enrollmentStatus,
    ): void {
        if ($statusFilter !== ApplicationStatus::Converted->value) {
            return;
        }

        if ($enrollmentStatus !== 'awaiting' && $enrollmentStatus !== 'completed') {
            return;
        }

        $students = SchemaHelper::qualified('students', 'students');
        $profileComplete = function ($sub) use ($students): void {
            $textFilled = static fn (string $column): string => "nullif(btrim(stu.{$column}::text), '') is not null";

            $sub->select(DB::raw('1'))
                ->from($students.' as stu')
                ->whereColumn('stu.id', 'apps.student_id')
                ->whereRaw($textFilled('first_name'))
                ->whereRaw($textFilled('father_name'))
                ->whereRaw($textFilled('grandfather_name'))
                ->whereRaw($textFilled('great_grandfather_name'))
                ->whereRaw($textFilled('last_name'))
                ->whereRaw($textFilled('mother_name'))
                ->whereRaw($textFilled('maternal_father_name'))
                ->whereRaw($textFilled('maternal_grandfather_name'))
                ->whereNotNull('stu.birth_date')
                ->whereRaw($textFilled('birth_place'))
                ->whereIn('stu.gender', [1, 2])
                ->whereRaw($textFilled('nationality'))
                ->whereIn('stu.religion', [1, 2, 3])
                ->whereRaw($textFilled('national_id'))
                ->whereNotNull('stu.mawalid_date')
                ->whereRaw($textFilled('registration_place'))
                ->whereRaw($textFilled('governorate'))
                ->whereRaw($textFilled('neighborhood'))
                ->whereRaw($textFilled('locality'))
                ->whereRaw($textFilled('house_number'))
                ->whereRaw($textFilled('school_name'))
                ->whereNotNull('stu.admitted_academic_year_id')
                ->whereNotNull('stu.school_start_date')
                ->whereRaw($textFilled('guardian_triple_name'))
                ->whereRaw($textFilled('mobile'))
                ->whereRaw($textFilled('guardian_mobile'))
                ->where(function ($transfer) use ($textFilled): void {
                    // Transfer fields optional unless any transfer value is present.
                    $transfer->where(function ($empty): void {
                        $empty->where(function ($q): void {
                            $q->whereNull('stu.previous_school_name')
                                ->orWhereRaw("btrim(stu.previous_school_name::text) = ''");
                        })
                            ->whereNull('stu.transfer_document_number')
                            ->whereNull('stu.transfer_document_date');
                    })->orWhere(function ($filled) use ($textFilled): void {
                        $filled->whereRaw($textFilled('previous_school_name'))
                            ->whereNotNull('stu.transfer_document_number')
                            ->whereNotNull('stu.transfer_document_date');
                    });
                });
        };

        $query->whereNotNull('apps.student_id');
        if ($enrollmentStatus === 'awaiting') {
            $query->whereNotExists($profileComplete);
        } else {
            $query->whereExists($profileComplete);
        }
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
        ?string $enrollmentStatus = null,
    ): array {
        $offset = ($page - 1) * $perPage;

        $rows = $this->activeApplicationsBaseQuery(
            $schoolId,
            $academicYearId,
            $statusFilter,
            $applicationPeriodId,
            $search,
            $enrollmentStatus,
        )
            ->orderBy('apps.first_name')
            ->orderBy('apps.father_name')
            ->orderBy('apps.grandfather_name')
            ->orderBy('apps.great_grandfather_name')
            ->orderBy('apps.last_name')
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
                'apps.maternal_father_name',
                'apps.maternal_grandfather_name',
                'apps.national_id',
                'apps.birth_date',
                'apps.birth_place',
                'apps.gender',
                'apps.governorate',
                'apps.neighborhood',
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
                'apps.student_id',
                'apps.created_at',
                'apps.updated_at',
                DB::raw('COUNT(*) OVER() as full_count'),
            ]);

        $total = $rows->isEmpty() ? 0 : (int) $rows->first()->full_count;

        $studentIds = $rows
            ->map(static fn ($row): ?int => $row->student_id !== null ? (int) $row->student_id : null)
            ->filter(static fn (?int $id): bool => $id !== null)
            ->values()
            ->all();
        $enrolledStudentIds = $this->activeEnrollmentStudentIds($schoolId, $academicYearId, $studentIds);
        $studentRecords = $this->studentRecordsById($studentIds);

        $applications = $rows->map(static function ($row) use ($enrolledStudentIds, $studentRecords): array {
            $studentId = $row->student_id !== null ? (int) $row->student_id : null;
            $status = (int) $row->status;
            $needsEnrollment = $status === 9
                && $studentId !== null
                && ! isset($enrolledStudentIds[$studentId]);

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
                'maternal_father_name' => $row->maternal_father_name !== null ? (string) $row->maternal_father_name : null,
                'maternal_grandfather_name' => $row->maternal_grandfather_name !== null ? (string) $row->maternal_grandfather_name : null,
                'national_id' => $row->national_id !== null ? (string) $row->national_id : null,
                'birth_date' => substr((string) $row->birth_date, 0, 10),
                'birth_place' => $row->birth_place !== null ? (string) $row->birth_place : null,
                'gender' => (int) $row->gender,
                'governorate' => $row->governorate !== null ? (string) $row->governorate : null,
                'neighborhood' => $row->neighborhood !== null ? (string) $row->neighborhood : null,
                'target_school_id' => $row->target_school_id !== null ? (int) $row->target_school_id : null,
                'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
                'grade_level_id' => $row->grade_level_id !== null ? (int) $row->grade_level_id : null,
                'intended_grade_name' => $row->intended_grade_name !== null ? (string) $row->intended_grade_name : null,
                'department_name' => $row->department_name !== null ? (string) $row->department_name : null,
                'specialization_id' => $row->specialization_id !== null ? (int) $row->specialization_id : null,
                'specialization_name' => $row->specialization_name !== null ? (string) $row->specialization_name : null,
                'status' => $status,
                'submitted_at' => $row->submitted_at !== null ? (string) $row->submitted_at : null,
                'reviewed_by' => $row->reviewed_by !== null ? (int) $row->reviewed_by : null,
                'reviewed_at' => $row->reviewed_at !== null ? (string) $row->reviewed_at : null,
                'notes' => $row->notes !== null ? (string) $row->notes : null,
                'student_id' => $studentId,
                'needs_enrollment' => $needsEnrollment,
                'student_record' => $studentId !== null ? ($studentRecords[$studentId] ?? null) : null,
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

    /**
     * @param  list<int>  $studentIds
     * @return array<int, true>
     */
    private function activeEnrollmentStudentIds(int $schoolId, int $academicYearId, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $enrollments = SchemaHelper::qualified('enrollment', 'enrollments');
        $rows = DB::table($enrollments)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', 1)
            ->whereNull('effective_to')
            ->whereIn('student_id', $studentIds)
            ->distinct()
            ->pluck('student_id');

        $map = [];
        foreach ($rows as $studentId) {
            $map[(int) $studentId] = true;
        }

        return $map;
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, array<string, mixed>>
     */
    private function studentRecordsById(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $students = SchemaHelper::qualified('students', 'students');
        $rows = DB::table($students)
            ->whereIn('id', $studentIds)
            ->get([
                'id',
                'student_code',
                'first_name',
                'father_name',
                'grandfather_name',
                'great_grandfather_name',
                'last_name',
                'mother_name',
                'maternal_father_name',
                'maternal_grandfather_name',
                'guardian_triple_name',
                'governorate',
                'neighborhood',
                'locality',
                'house_number',
                'birth_date',
                'birth_place',
                'registration_place',
                'gender',
                'nationality',
                'religion',
                'mawalid_date',
                'national_id',
                'previous_school_name',
                'transfer_document_number',
                'transfer_document_date',
                'school_start_date',
                'admitted_class_name',
                'notes',
                'mobile',
                'guardian_mobile',
                'email',
                'school_name',
                'branch_id',
                'department_name',
                'specialization_name',
                'stage_name',
                'section_name',
                'admitted_academic_year_id',
                'status',
            ]);

        $map = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $map[$id] = [
                'id' => $id,
                'student_code' => (string) $row->student_code,
                'first_name' => (string) $row->first_name,
                'father_name' => $row->father_name !== null ? (string) $row->father_name : null,
                'grandfather_name' => $row->grandfather_name !== null ? (string) $row->grandfather_name : null,
                'great_grandfather_name' => $row->great_grandfather_name !== null ? (string) $row->great_grandfather_name : null,
                'last_name' => (string) $row->last_name,
                'mother_name' => $row->mother_name !== null ? (string) $row->mother_name : null,
                'maternal_father_name' => $row->maternal_father_name !== null ? (string) $row->maternal_father_name : null,
                'maternal_grandfather_name' => $row->maternal_grandfather_name !== null ? (string) $row->maternal_grandfather_name : null,
                'guardian_triple_name' => $row->guardian_triple_name !== null ? (string) $row->guardian_triple_name : null,
                'governorate' => $row->governorate !== null ? (string) $row->governorate : null,
                'neighborhood' => $row->neighborhood !== null ? (string) $row->neighborhood : null,
                'locality' => $row->locality !== null ? (string) $row->locality : null,
                'house_number' => $row->house_number !== null ? (string) $row->house_number : null,
                'birth_date' => substr((string) $row->birth_date, 0, 10),
                'birth_place' => $row->birth_place !== null ? (string) $row->birth_place : null,
                'registration_place' => $row->registration_place !== null ? (string) $row->registration_place : null,
                'gender' => (int) $row->gender,
                'nationality' => $row->nationality !== null ? (string) $row->nationality : null,
                'religion' => (int) $row->religion,
                'mawalid_date' => $row->mawalid_date !== null ? substr((string) $row->mawalid_date, 0, 10) : null,
                'national_id' => $row->national_id !== null ? (string) $row->national_id : null,
                'previous_school_name' => $row->previous_school_name !== null ? (string) $row->previous_school_name : null,
                'transfer_document_number' => $row->transfer_document_number !== null ? (int) $row->transfer_document_number : null,
                'transfer_document_date' => $row->transfer_document_date !== null ? substr((string) $row->transfer_document_date, 0, 10) : null,
                'school_start_date' => $row->school_start_date !== null ? substr((string) $row->school_start_date, 0, 10) : null,
                'admitted_class_name' => $row->admitted_class_name !== null ? (string) $row->admitted_class_name : null,
                'notes' => $row->notes !== null ? (string) $row->notes : null,
                'mobile' => $row->mobile !== null ? (string) $row->mobile : null,
                'guardian_mobile' => $row->guardian_mobile !== null ? (string) $row->guardian_mobile : null,
                'email' => $row->email !== null ? (string) $row->email : null,
                'school_name' => $row->school_name !== null ? (string) $row->school_name : null,
                'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
                'department_name' => $row->department_name !== null ? (string) $row->department_name : null,
                'specialization_name' => $row->specialization_name !== null ? (string) $row->specialization_name : null,
                'stage_name' => $row->stage_name !== null ? (string) $row->stage_name : null,
                'section_name' => $row->section_name !== null ? (string) $row->section_name : null,
                'academic_year_id' => $row->admitted_academic_year_id !== null ? (int) $row->admitted_academic_year_id : null,
                'status' => (int) $row->status,
            ];
        }

        return $map;
    }
}
