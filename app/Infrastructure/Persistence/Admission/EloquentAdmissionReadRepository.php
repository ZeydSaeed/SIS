<?php

namespace App\Infrastructure\Persistence\Admission;

use App\Application\Admission\Contracts\AdmissionReadRepositoryInterface;
use App\Database\SchemaHelper;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use Illuminate\Support\Facades\DB;

final class EloquentAdmissionReadRepository implements AdmissionReadRepositoryInterface
{
    public function workspace(int $schoolId, int $academicYearId): array
    {
        $periods = DB::table(SchemaHelper::qualified('admission', 'application_periods'))
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

        $applications = DB::table(SchemaHelper::qualified('admission', 'applications').' as apps')
            ->join(
                SchemaHelper::qualified('admission', 'application_periods').' as periods',
                'periods.id',
                '=',
                'apps.application_period_id',
            )
            ->where('periods.school_id', $schoolId)
            ->where('periods.academic_year_id', $academicYearId)
            ->orderByDesc('apps.id')
            ->limit(200)
            ->get([
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
                'apps.submitted_at',
                'apps.reviewed_by',
                'apps.reviewed_at',
                'apps.notes',
                'apps.student_id',
                'apps.created_at',
                'apps.updated_at',
            ])
            ->map(static function ($row): array {
                $status = ApplicationStatus::tryFrom((int) $row->status) ?? ApplicationStatus::Draft;

                return [
                    'id' => (int) $row->id,
                    'application_period_id' => (int) $row->application_period_id,
                    'application_number' => (string) $row->application_number,
                    'first_name' => (string) $row->first_name,
                    'last_name' => (string) $row->last_name,
                    'national_id' => $row->national_id !== null ? (string) $row->national_id : null,
                    'birth_date' => (string) $row->birth_date,
                    'gender' => (int) $row->gender,
                    'grade_level_id' => (int) $row->grade_level_id,
                    'specialization_id' => $row->specialization_id !== null ? (int) $row->specialization_id : null,
                    'status' => (int) $row->status,
                    'submitted_at' => $row->submitted_at !== null ? (string) $row->submitted_at : null,
                    'reviewed_by' => $row->reviewed_by !== null ? (int) $row->reviewed_by : null,
                    'reviewed_at' => $row->reviewed_at !== null ? (string) $row->reviewed_at : null,
                    'notes' => $row->notes !== null ? (string) $row->notes : null,
                    'student_id' => $row->student_id !== null ? (int) $row->student_id : null,
                    'created_at' => (string) $row->created_at,
                    'updated_at' => (string) $row->updated_at,
                    'allowed_transitions' => array_map(
                        static fn (ApplicationStatus $s): int => $s->value,
                        $status->allowedTransitions(),
                    ),
                    'can_convert' => $status->canConvertToStudent(),
                ];
            })
            ->all();

        $applicationIds = array_map(static fn (array $app): int => $app['id'], $applications);

        $documents = $applicationIds === []
            ? []
            : DB::table(SchemaHelper::qualified('admission', 'application_documents'))
                ->whereIn('application_id', $applicationIds)
                ->orderByDesc('id')
                ->get([
                    'id',
                    'application_id',
                    'document_type',
                    'storage_key',
                    'file_name',
                    'file_hash',
                    'created_at',
                ])
                ->map(static fn ($row): array => [
                    'id' => (int) $row->id,
                    'application_id' => (int) $row->application_id,
                    'document_type' => (int) $row->document_type,
                    'storage_key' => (string) $row->storage_key,
                    'file_name' => (string) $row->file_name,
                    'file_hash' => (string) $row->file_hash,
                    'created_at' => (string) $row->created_at,
                ])
                ->all();

        $gradeLevels = DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();

        $workflowSteps = array_map(
            static fn (ApplicationStatus $status): array => [
                'status' => $status->value,
                'key' => $status->name,
            ],
            ApplicationStatus::pipelineSteps(),
        );

        return [
            'periods' => $periods,
            'applications' => $applications,
            'documents' => $documents,
            'grade_levels' => $gradeLevels,
            'workflow_steps' => $workflowSteps,
        ];
    }
}
