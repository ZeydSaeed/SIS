<?php

namespace App\Infrastructure\Persistence\Admission;

use App\Database\SchemaHelper;
use App\Domain\Admission\Data\CreateApplicationDraftData;
use App\Domain\Admission\Data\CreateApplicationPeriodData;
use App\Domain\Admission\Data\RegisterApplicationDocumentData;
use App\Domain\Admission\Data\TransferApplicationData;
use App\Domain\Admission\Data\UpdateApplicationDraftData;
use App\Domain\Admission\Data\UpdateApplicationFollowUpData;
use App\Domain\Admission\Data\UpdateApplicationPeriodData;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use Illuminate\Support\Facades\DB;

final class EloquentAdmissionRepository implements AdmissionRepositoryInterface
{
    public function __construct(
        private readonly AdmissionWorkspaceCache $workspaceCache,
    ) {}

    public function createPeriod(CreateApplicationPeriodData $data): int
    {
        $id = (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))->insertGetId([
            'academic_year_id' => $data->academicYearId,
            // Periods are shared by the directorate's schools in the academic year.
            'school_id' => null,
            'directorate_id' => $data->directorateId,
            'name' => $data->name,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'max_applications' => $data->maxApplications,
            'status' => $data->status,
            'created_at' => now(),
        ]);
        $this->workspaceCache->forgetPeriods($data->academicYearId);

        return $id;
    }

    public function createApplication(CreateApplicationDraftData $data): int
    {
        $now = now();

        $id = (int) DB::table(SchemaHelper::qualified('admission', 'applications'))->insertGetId([
            'application_period_id' => $data->applicationPeriodId,
            'school_id' => $data->targetSchoolId,
            'application_number' => $data->applicationNumber,
            'first_name' => $data->firstName,
            'father_name' => $data->fatherName,
            'grandfather_name' => $data->grandfatherName,
            'great_grandfather_name' => $data->greatGrandfatherName,
            'last_name' => $data->lastName,
            'mother_name' => $data->motherName,
            'maternal_father_name' => $data->maternalFatherName,
            'maternal_grandfather_name' => $data->maternalGrandfatherName,
            'national_id' => $data->nationalId,
            'birth_date' => $data->birthDate,
            'birth_place' => $data->birthPlace,
            'gender' => $data->gender,
            'target_school_id' => $data->targetSchoolId,
            'grade_level_id' => $data->gradeLevelId,
            'intended_grade_name' => $data->intendedGradeName,
            'request_kind' => $data->requestKind,
            'branch_id' => $data->branchId,
            'branch_name' => $data->branchName,
            'department_name' => $data->departmentName,
            'specialization_id' => $data->specializationId,
            'specialization_name' => $data->specializationName,
            'governorate' => $data->governorate,
            'administrative_unit' => $data->administrativeUnit,
            'neighborhood' => $data->neighborhood,
            'father_occupation' => $data->fatherOccupation,
            'mother_occupation' => $data->motherOccupation,
            'student_mobile' => $data->studentMobile,
            'guardian_mobile' => $data->guardianMobile,
            'previous_school_name' => $data->previousSchoolName,
            'graduation_year' => $data->graduationYear,
            'previous_gpa' => $data->previousGpa,
            'mathematics_grade' => $data->mathematicsGrade,
            'physics_grade' => $data->physicsGrade,
            'previous_study_track' => $data->previousStudyTrack,
            'status' => ApplicationStatus::Draft->value,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'notes' => $data->notes,
            'student_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->workspaceCache->forgetForPeriodId($data->applicationPeriodId);

        return $id;
    }

    public function generateApplicationNumber(int $schoolId, int $academicYearId): string
    {
        $prefix = sprintf('APP-%d-%d-', $schoolId, $academicYearId);
        $latest = DB::table(SchemaHelper::qualified('admission', 'applications').' as apps')
            ->join(
                SchemaHelper::qualified('admission', 'application_periods').' as periods',
                'periods.id',
                '=',
                'apps.application_period_id',
            )
            ->where('apps.school_id', $schoolId)
            ->where('periods.academic_year_id', $academicYearId)
            ->where('apps.application_number', 'like', $prefix.'%')
            ->orderByDesc('apps.id')
            ->value('apps.application_number');

        $seq = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $seq = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    public function findPeriod(int $periodId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('id', $periodId)
            ->first([
                'id',
                'academic_year_id',
                'directorate_id',
                'name',
                'status',
                'start_date',
                'end_date',
                'max_applications',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'academic_year_id' => (int) $row->academic_year_id,
            'directorate_id' => $row->directorate_id !== null ? (int) $row->directorate_id : null,
            'name' => (string) $row->name,
            'status' => (int) $row->status,
            'start_date' => (string) $row->start_date,
            'end_date' => $row->end_date !== null ? (string) $row->end_date : null,
            'max_applications' => $row->max_applications !== null ? (int) $row->max_applications : null,
        ];
    }

    public function schoolDirectorateId(int $schoolId): ?int
    {
        $id = DB::table(SchemaHelper::qualified('organization', 'schools'))->where('id', $schoolId)->value('directorate_id');

        return $id !== null ? (int) $id : null;
    }

    public function updatePeriod(UpdateApplicationPeriodData $data): void
    {
        $existing = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('id', $data->periodId)
            ->first(['academic_year_id']);

        DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('id', $data->periodId)
            ->update([
                'academic_year_id' => $data->academicYearId,
                'name' => $data->name,
                'start_date' => $data->startDate,
                'end_date' => $data->endDate,
                'max_applications' => $data->maxApplications,
            ]);

        if ($existing !== null) {
            $this->workspaceCache->forgetPeriods((int) $existing->academic_year_id);
        }

        $this->workspaceCache->forgetForPeriodId($data->periodId);
    }

    public function updatePeriodStatus(int $periodId, int $status): void
    {
        DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('id', $periodId)
            ->update([
                'status' => $status,
            ]);
        $this->workspaceCache->forgetForPeriodId($periodId);
    }

    public function countApplicationsInPeriod(int $periodId, int $schoolId): int
    {
        return (int) DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('application_period_id', $periodId)
            ->where('school_id', $schoolId)
            ->count();
    }

    public function findApplicationForSchool(int $applicationId, int $schoolId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('admission', 'applications').' as apps')
            ->join(
                SchemaHelper::qualified('admission', 'application_periods').' as periods',
                'periods.id',
                '=',
                'apps.application_period_id',
            )
            ->leftJoin(
                SchemaHelper::qualified('organization', 'schools').' as target_schools',
                'target_schools.id',
                '=',
                'apps.target_school_id',
            )
            ->join(
                SchemaHelper::qualified('organization', 'schools').' as app_schools',
                'app_schools.id',
                '=',
                'apps.school_id',
            )
            ->where('apps.id', $applicationId)
            ->where('apps.school_id', $schoolId)
            ->first([
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
                'apps.target_school_id',
                'apps.branch_id',
                'apps.branch_name',
                'apps.grade_level_id',
                'apps.intended_grade_name',
                'apps.department_name',
                'apps.specialization_id',
                'apps.specialization_name',
                'apps.governorate',
                'apps.administrative_unit',
                'apps.neighborhood',
                'apps.father_occupation',
                'apps.mother_occupation',
                'apps.student_mobile',
                'apps.guardian_mobile',
                'apps.previous_school_name',
                'apps.graduation_year',
                'apps.previous_gpa',
                'apps.previous_study_track',
                'apps.mathematics_grade',
                'apps.physics_grade',
                'apps.request_kind',
                'apps.status',
                'apps.notes',
                'apps.student_id',
                'apps.school_id',
                'periods.academic_year_id',
                'target_schools.name as target_school_name',
                'app_schools.name as app_school_name',
            ]);

        if ($row === null) {
            return null;
        }

        $branchName = $this->nullableString($row->branch_name);
        $branchId = $row->branch_id !== null ? (int) $row->branch_id : null;
        if ($branchId === null && $branchName !== null) {
            $branchId = $this->resolveBranchIdForSchool((int) $row->school_id, $branchName);
        }

        return [
            'id' => (int) $row->id,
            'application_period_id' => (int) $row->application_period_id,
            'application_number' => (string) $row->application_number,
            'first_name' => (string) $row->first_name,
            'father_name' => $this->nullableString($row->father_name),
            'grandfather_name' => $this->nullableString($row->grandfather_name),
            'great_grandfather_name' => $this->nullableString($row->great_grandfather_name),
            'last_name' => (string) $row->last_name,
            'mother_name' => $this->nullableString($row->mother_name),
            'maternal_father_name' => $this->nullableString($row->maternal_father_name),
            'maternal_grandfather_name' => $this->nullableString($row->maternal_grandfather_name),
            'national_id' => $this->nullableString($row->national_id),
            'birth_date' => substr((string) $row->birth_date, 0, 10),
            'birth_place' => $this->nullableString($row->birth_place),
            'gender' => (int) $row->gender,
            'branch_id' => $branchId,
            'branch_name' => $branchName,
            'grade_level_id' => $row->grade_level_id !== null ? (int) $row->grade_level_id : null,
            'intended_grade_name' => $this->nullableString($row->intended_grade_name),
            'department_name' => $this->nullableString($row->department_name),
            'specialization_id' => $row->specialization_id !== null ? (int) $row->specialization_id : null,
            'specialization_name' => $this->nullableString($row->specialization_name),
            'governorate' => $this->nullableString($row->governorate),
            'administrative_unit' => $row->administrative_unit !== null ? (int) $row->administrative_unit : null,
            'neighborhood' => $this->nullableString($row->neighborhood),
            'father_occupation' => $this->nullableString($row->father_occupation),
            'mother_occupation' => $this->nullableString($row->mother_occupation),
            'student_mobile' => $this->nullableString($row->student_mobile),
            'guardian_mobile' => $this->nullableString($row->guardian_mobile),
            'previous_school_name' => $this->nullableString($row->previous_school_name),
            'graduation_year' => $row->graduation_year !== null ? (int) $row->graduation_year : null,
            'previous_gpa' => $row->previous_gpa !== null ? (float) $row->previous_gpa : null,
            'previous_study_track' => $row->previous_study_track !== null ? (int) $row->previous_study_track : null,
            'mathematics_grade' => $row->mathematics_grade !== null ? (float) $row->mathematics_grade : null,
            'physics_grade' => $row->physics_grade !== null ? (float) $row->physics_grade : null,
            'request_kind' => (int) ($row->request_kind ?? 2),
            'school_name' => $this->nullableString($row->app_school_name ?? $row->target_school_name),
            'status' => (int) $row->status,
            'notes' => $this->nullableString($row->notes),
            'student_id' => $row->student_id !== null ? (int) $row->student_id : null,
            'school_id' => (int) $row->school_id,
            'academic_year_id' => (int) $row->academic_year_id,
        ];
    }

    public function listDocumentsForApplication(int $applicationId): array
    {
        return DB::table(SchemaHelper::qualified('admission', 'application_documents'))
            ->where('application_id', $applicationId)
            ->orderBy('document_type')
            ->orderBy('id')
            ->get(['id', 'document_type', 'storage_key', 'file_name', 'file_hash'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'document_type' => (int) $row->document_type,
                'storage_key' => (string) $row->storage_key,
                'file_name' => (string) $row->file_name,
                'file_hash' => (string) $row->file_hash,
            ])
            ->all();
    }

    private function resolveBranchIdForSchool(int $schoolId, string $branchName): ?int
    {
        $name = trim($branchName);
        if ($name === '') {
            return null;
        }

        $branches = SchemaHelper::qualified('organization', 'branches');
        $exact = DB::table($branches)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->where('name', $name)
            ->value('id');
        if ($exact !== null) {
            return (int) $exact;
        }

        $rows = DB::table($branches)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->get(['id', 'name']);

        foreach ($rows as $candidate) {
            $candidateName = trim((string) $candidate->name);
            if ($candidateName === '') {
                continue;
            }
            if (mb_stripos($candidateName, $name) !== false || mb_stripos($name, $candidateName) !== false) {
                return (int) $candidate->id;
            }
        }

        return null;
    }

    public function findAcceptedApplicationIdsWithoutStudent(int $schoolId): array
    {
        return DB::table(SchemaHelper::qualified('admission', 'applications').' as apps')
            ->join(
                SchemaHelper::qualified('admission', 'application_periods').' as periods',
                'periods.id',
                '=',
                'apps.application_period_id',
            )
            ->where('apps.school_id', $schoolId)
            ->where('apps.status', ApplicationStatus::Accepted->value)
            ->whereNull('apps.student_id')
            ->orderBy('apps.id')
            ->limit(100)
            ->pluck('apps.id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function updateDraft(UpdateApplicationDraftData $data): void
    {
        $payload = [
            'notes' => $data->notes,
            'reviewed_at' => $data->reviewedAt,
            'updated_at' => now(),
        ];

        if ($data->updatePlacement) {
            $payload['branch_id'] = $data->branchId;
            $payload['branch_name'] = $data->branchName;
            $payload['department_name'] = $data->departmentName;
            $payload['grade_level_id'] = $data->gradeLevelId;
            $payload['intended_grade_name'] = $data->intendedGradeName;
            $payload['specialization_id'] = $data->specializationId;
            $payload['specialization_name'] = $data->specializationName;
        }

        DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('id', $data->applicationId)
            ->update($payload);
        $this->workspaceCache->forgetForApplicationId($data->applicationId);
    }

    public function updateFollowUp(UpdateApplicationFollowUpData $data): void
    {
        $payload = [
            'first_name' => $data->firstName,
            'father_name' => $data->fatherName,
            'grandfather_name' => $data->grandfatherName,
            'great_grandfather_name' => $data->greatGrandfatherName,
            'last_name' => $data->lastName,
            'updated_at' => now(),
        ];

        if ($data->updateRejectionReason) {
            $payload['rejection_reason'] = $data->rejectionReason;
            $payload['notes'] = $data->rejectionReason;
        }

        if ($data->updateWithdrawalReason) {
            $payload['withdrawal_reason'] = $data->withdrawalReason;
            $payload['notes'] = $data->withdrawalReason;
        }

        DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('id', $data->applicationId)
            ->update($payload);
        $this->workspaceCache->forgetForApplicationId($data->applicationId);
    }

    public function transitionApplicationStatus(
        int $applicationId,
        int $toStatus,
        ?int $reviewedBy,
        ?string $notes,
    ): void {
        $payload = [
            'status' => $toStatus,
            'updated_at' => now(),
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ];

        if ($toStatus === ApplicationStatus::Submitted->value) {
            $payload['submitted_at'] = now();
        }

        if ($notes !== null) {
            $payload['notes'] = $notes;
        }

        if ($toStatus === ApplicationStatus::Rejected->value && $notes !== null && trim($notes) !== '') {
            $payload['rejection_reason'] = trim($notes);
        }

        if ($toStatus === ApplicationStatus::Withdrawn->value && $notes !== null && trim($notes) !== '') {
            $payload['withdrawal_reason'] = trim($notes);
        }

        DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('id', $applicationId)
            ->update($payload);
        $this->workspaceCache->forgetForApplicationId($applicationId);
    }

    public function markConverted(int $applicationId, int $studentId, ?int $reviewedBy): void
    {
        DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('id', $applicationId)
            ->update([
                'status' => ApplicationStatus::Converted->value,
                'student_id' => $studentId,
                'reviewed_by' => $reviewedBy,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);
        $this->workspaceCache->forgetForApplicationId($applicationId);
    }

    public function transferApplication(TransferApplicationData $data): void
    {
        $apps = SchemaHelper::qualified('admission', 'applications');

        if (SchemaHelper::isPostgreSql()) {
            // RLS policy admission_applications_transfer: the moved row may land in this school.
            // Transaction-local (is_local = true) — cleared on commit/rollback.
            DB::statement(
                "SELECT set_config('app.transfer_target_school_id', ?, true)",
                [(string) $data->toSchoolId],
            );
        }

        DB::table($apps)
            ->where('id', $data->applicationId)
            ->update([
                'school_id' => $data->toSchoolId,
                'target_school_id' => $data->toSchoolId,
                'request_kind' => $data->toRequestKind,
                'application_period_id' => $data->toPeriodId,
                // Placement belongs to the old school's branches — chosen again in the new one.
                'branch_id' => $data->toSchoolId === $data->fromSchoolId ? DB::raw('branch_id') : null,
                'updated_at' => now(),
            ]);

        DB::table(SchemaHelper::qualified('admission', 'application_transfers'))->insert([
            'application_id' => $data->applicationId,
            'from_school_id' => $data->fromSchoolId,
            'to_school_id' => $data->toSchoolId,
            'from_request_kind' => $data->fromRequestKind,
            'to_request_kind' => $data->toRequestKind,
            'from_period_id' => $data->fromPeriodId,
            'to_period_id' => $data->toPeriodId,
            'reason' => $data->reason,
            'transferred_by' => $data->transferredBy,
            'created_at' => now(),
        ]);

        $this->workspaceCache->forgetStatusForPeriod($data->fromSchoolId, $data->fromAcademicYearId, $data->fromPeriodId);
        $this->workspaceCache->forgetSchoolYear($data->fromSchoolId, $data->fromAcademicYearId);
        $this->workspaceCache->forgetStatusForPeriod($data->toSchoolId, $data->toAcademicYearId, $data->toPeriodId);
        $this->workspaceCache->forgetSchoolYear($data->toSchoolId, $data->toAcademicYearId);
    }

    public function registerDocument(RegisterApplicationDocumentData $data): int
    {
        $id = (int) DB::table(SchemaHelper::qualified('admission', 'application_documents'))->insertGetId([
            'application_id' => $data->applicationId,
            'document_type' => $data->documentType,
            'storage_key' => $data->storageKey,
            'file_name' => $data->fileName,
            'file_hash' => $data->fileHash,
            'created_at' => now(),
        ]);
        $this->workspaceCache->forgetForApplicationId($data->applicationId);

        return $id;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
