<?php

namespace App\Infrastructure\Persistence\Admission;

use App\Database\SchemaHelper;
use App\Domain\Admission\Data\CreateApplicationDraftData;
use App\Domain\Admission\Data\CreateApplicationPeriodData;
use App\Domain\Admission\Data\RegisterApplicationDocumentData;
use App\Domain\Admission\Data\UpdateApplicationPeriodData;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use Illuminate\Support\Facades\DB;

final class EloquentAdmissionRepository implements AdmissionRepositoryInterface
{
    public function createPeriod(CreateApplicationPeriodData $data): int
    {
        return (int) DB::table(SchemaHelper::qualified('admission', 'application_periods'))->insertGetId([
            'academic_year_id' => $data->academicYearId,
            'school_id' => $data->schoolId,
            'name' => $data->name,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'max_applications' => $data->maxApplications,
            'status' => $data->status,
            'created_at' => now(),
        ]);
    }

    public function createApplication(CreateApplicationDraftData $data): int
    {
        $now = now();

        return (int) DB::table(SchemaHelper::qualified('admission', 'applications'))->insertGetId([
            'application_period_id' => $data->applicationPeriodId,
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
            'department_name' => $data->departmentName,
            'specialization_id' => $data->specializationId,
            'specialization_name' => $data->specializationName,
            'governorate' => $data->governorate,
            'neighborhood' => $data->neighborhood,
            'status' => ApplicationStatus::Draft->value,
            'submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'notes' => $data->notes,
            'student_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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
            ->where('periods.school_id', $schoolId)
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

    public function findPeriodForSchool(int $periodId, int $schoolId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('id', $periodId)
            ->where('school_id', $schoolId)
            ->first([
                'id',
                'school_id',
                'academic_year_id',
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
            'school_id' => (int) $row->school_id,
            'academic_year_id' => (int) $row->academic_year_id,
            'name' => (string) $row->name,
            'status' => (int) $row->status,
            'start_date' => (string) $row->start_date,
            'end_date' => (string) $row->end_date,
            'max_applications' => $row->max_applications !== null ? (int) $row->max_applications : null,
        ];
    }

    public function updatePeriod(UpdateApplicationPeriodData $data): void
    {
        DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('id', $data->periodId)
            ->update([
                'name' => $data->name,
                'start_date' => $data->startDate,
                'end_date' => $data->endDate,
                'max_applications' => $data->maxApplications,
            ]);
    }

    public function updatePeriodStatus(int $periodId, int $status): void
    {
        DB::table(SchemaHelper::qualified('admission', 'application_periods'))
            ->where('id', $periodId)
            ->update([
                'status' => $status,
            ]);
    }

    public function countApplicationsInPeriod(int $periodId): int
    {
        return (int) DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('application_period_id', $periodId)
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
            ->where('apps.id', $applicationId)
            ->where('periods.school_id', $schoolId)
            ->first([
                'apps.id',
                'apps.application_period_id',
                'apps.application_number',
                'apps.first_name',
                'apps.last_name',
                'apps.national_id',
                'apps.birth_date',
                'apps.gender',
                'apps.grade_level_id',
                'apps.specialization_id',
                'apps.status',
                'apps.notes',
                'apps.student_id',
                'periods.school_id',
            ]);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'application_period_id' => (int) $row->application_period_id,
            'application_number' => (string) $row->application_number,
            'first_name' => (string) $row->first_name,
            'last_name' => (string) $row->last_name,
            'national_id' => $row->national_id !== null ? (string) $row->national_id : null,
            'birth_date' => (string) $row->birth_date,
            'gender' => (int) $row->gender,
            'grade_level_id' => $row->grade_level_id !== null ? (int) $row->grade_level_id : null,
            'specialization_id' => $row->specialization_id !== null ? (int) $row->specialization_id : null,
            'status' => (int) $row->status,
            'notes' => $row->notes !== null ? (string) $row->notes : null,
            'student_id' => $row->student_id !== null ? (int) $row->student_id : null,
            'school_id' => (int) $row->school_id,
        ];
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

        DB::table(SchemaHelper::qualified('admission', 'applications'))
            ->where('id', $applicationId)
            ->update($payload);
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
    }

    public function registerDocument(RegisterApplicationDocumentData $data): int
    {
        return (int) DB::table(SchemaHelper::qualified('admission', 'application_documents'))->insertGetId([
            'application_id' => $data->applicationId,
            'document_type' => $data->documentType,
            'storage_key' => $data->storageKey,
            'file_name' => $data->fileName,
            'file_hash' => $data->fileHash,
            'created_at' => now(),
        ]);
    }
}
