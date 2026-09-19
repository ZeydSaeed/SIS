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
                'apps.grade_level_id',
                'apps.intended_grade_name',
                'apps.department_name',
                'apps.specialization_id',
                'apps.specialization_name',
                'apps.governorate',
                'apps.neighborhood',
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
                    'father_name' => $row->father_name !== null ? (string) $row->father_name : null,
                    'grandfather_name' => $row->grandfather_name !== null ? (string) $row->grandfather_name : null,
                    'great_grandfather_name' => $row->great_grandfather_name !== null ? (string) $row->great_grandfather_name : null,
                    'last_name' => (string) $row->last_name,
                    'mother_name' => $row->mother_name !== null ? (string) $row->mother_name : null,
                    'maternal_father_name' => $row->maternal_father_name !== null ? (string) $row->maternal_father_name : null,
                    'maternal_grandfather_name' => $row->maternal_grandfather_name !== null ? (string) $row->maternal_grandfather_name : null,
                    'national_id' => $row->national_id !== null ? (string) $row->national_id : null,
                    'birth_date' => (string) $row->birth_date,
                    'birth_place' => $row->birth_place !== null ? (string) $row->birth_place : null,
                    'gender' => (int) $row->gender,
                    'target_school_id' => $row->target_school_id !== null ? (int) $row->target_school_id : null,
                    'grade_level_id' => $row->grade_level_id !== null ? (int) $row->grade_level_id : null,
                    'intended_grade_name' => $row->intended_grade_name !== null ? (string) $row->intended_grade_name : null,
                    'department_name' => $row->department_name !== null ? (string) $row->department_name : null,
                    'specialization_id' => $row->specialization_id !== null ? (int) $row->specialization_id : null,
                    'specialization_name' => $row->specialization_name !== null ? (string) $row->specialization_name : null,
                    'governorate' => $row->governorate !== null ? (string) $row->governorate : null,
                    'neighborhood' => $row->neighborhood !== null ? (string) $row->neighborhood : null,
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
            ->where('status', 1)
            ->orderBy('level_order')
            ->get(['id', 'name'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();

        $schools = DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('id', $schoolId)
            ->get(['id', 'name'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();

        $departments = DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
            ])
            ->all();

        $specializations = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->orderBy('name')
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
            'schools' => $schools,
            'departments' => $departments,
            'specializations' => $specializations,
            'workflow_steps' => $workflowSteps,
        ];
    }
}
