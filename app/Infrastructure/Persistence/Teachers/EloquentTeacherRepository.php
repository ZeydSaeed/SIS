<?php

namespace App\Infrastructure\Persistence\Teachers;

use App\Database\SchemaHelper;
use App\Domain\Teachers\Data\TeacherQualificationSnapshot;
use App\Domain\Teachers\Data\TeacherSnapshot;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\ValueObjects\QualificationStatus;
use Illuminate\Support\Facades\DB;

final class EloquentTeacherRepository implements TeacherRepositoryInterface
{
    public function employeeCodeExists(string $employeeCode): bool
    {
        return DB::table(SchemaHelper::qualified('teachers', 'teachers'))
            ->where('employee_code', $employeeCode)
            ->exists();
    }

    public function createTeacher(
        ?int $userId,
        string $employeeCode,
        ?string $nationalId,
        string $firstName,
        string $lastName,
        string $fullName,
        ?string $specializationField,
        ?string $hireDate,
        int $status,
        string $createdAt,
    ): int {
        return (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'user_id' => $userId,
            'employee_code' => $employeeCode,
            'national_id' => $nationalId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'specialization_field' => $specializationField,
            'hire_date' => $hireDate,
            'status' => $status,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function assignSchool(
        int $teacherId,
        int $schoolId,
        int $academicYearId,
        bool $isPrimary,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))->insertGetId([
            'teacher_id' => $teacherId,
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'is_primary' => $isPrimary,
            'created_at' => $createdAt,
        ]);
    }

    public function findInSchool(int $teacherId, int $schoolId, ?int $academicYearId = null): ?TeacherSnapshot
    {
        $q = DB::table(SchemaHelper::qualified('teachers', 'teachers').' as t')
            ->join(SchemaHelper::qualified('teachers', 'teacher_schools').' as ts', 'ts.teacher_id', '=', 't.id')
            ->where('t.id', $teacherId)
            ->where('ts.school_id', $schoolId)
            ->orderByDesc('ts.is_primary')
            ->orderBy('ts.id');

        if ($academicYearId !== null) {
            $q->where('ts.academic_year_id', $academicYearId);
        }

        $row = $q->first([
            't.id', 't.user_id', 't.employee_code', 't.national_id', 't.first_name', 't.last_name',
            't.full_name', 't.specialization_field', 't.hire_date', 't.status',
            'ts.school_id', 'ts.academic_year_id', 'ts.is_primary',
        ]);

        return $row === null ? null : $this->map($row);
    }

    public function listForSchool(int $schoolId, int $academicYearId, int $page, int $perPage): array
    {
        $base = DB::table(SchemaHelper::qualified('teachers', 'teachers').' as t')
            ->join(SchemaHelper::qualified('teachers', 'teacher_schools').' as ts', 'ts.teacher_id', '=', 't.id')
            ->where('ts.school_id', $schoolId)
            ->where('ts.academic_year_id', $academicYearId);

        $total = (int) (clone $base)->distinct('t.id')->count('t.id');

        $rows = $base
            ->orderBy('t.employee_code')
            ->forPage($page, $perPage)
            ->get([
                't.id', 't.user_id', 't.employee_code', 't.national_id', 't.first_name', 't.last_name',
                't.full_name', 't.specialization_field', 't.hire_date', 't.status',
                'ts.school_id', 'ts.academic_year_id', 'ts.is_primary',
            ]);

        return [
            'items' => $rows->map(fn ($row): TeacherSnapshot => $this->map($row))->all(),
            'total' => $total,
        ];
    }

    public function belongsToSchool(int $teacherId, int $schoolId, ?int $academicYearId = null): bool
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId);

        if ($academicYearId !== null) {
            $q->where('academic_year_id', $academicYearId);
        }

        return $q->exists();
    }

    public function updateTeacher(int $teacherId, array $fields): void
    {
        DB::table(SchemaHelper::qualified('teachers', 'teachers'))
            ->where('id', $teacherId)
            ->update($fields);
    }

    public function setStatus(int $teacherId, int $status, string $updatedAt): void
    {
        DB::table(SchemaHelper::qualified('teachers', 'teachers'))
            ->where('id', $teacherId)
            ->update([
                'status' => $status,
                'updated_at' => $updatedAt,
            ]);
    }

    public function subjectExists(int $subjectId): bool
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->exists();
    }

    public function findSubjectAssignmentId(
        int $teacherId,
        int $subjectId,
        int $academicYearId,
        int $schoolId,
    ): ?int {
        $id = DB::table(SchemaHelper::qualified('teachers', 'teacher_subjects'))
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function assignSubject(
        int $teacherId,
        int $subjectId,
        int $academicYearId,
        int $schoolId,
        string $createdAt,
    ): int {
        return (int) DB::table(SchemaHelper::qualified('teachers', 'teacher_subjects'))->insertGetId([
            'teacher_id' => $teacherId,
            'subject_id' => $subjectId,
            'academic_year_id' => $academicYearId,
            'school_id' => $schoolId,
            'created_at' => $createdAt,
        ]);
    }

    public function unlinkSubject(
        int $teacherId,
        int $subjectId,
        int $academicYearId,
        int $schoolId,
    ): bool {
        return DB::table(SchemaHelper::qualified('teachers', 'teacher_subjects'))
            ->where('teacher_id', $teacherId)
            ->where('subject_id', $subjectId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->delete() > 0;
    }

    public function addQualification(
        int $teacherId,
        int $qualificationType,
        string $title,
        ?string $institution,
        ?int $yearObtained,
        ?string $documentStorageKey,
        string $createdAt,
    ): int {
        return (int) DB::table(SchemaHelper::qualified('teachers', 'teacher_qualifications'))->insertGetId([
            'teacher_id' => $teacherId,
            'qualification_type' => $qualificationType,
            'title' => $title,
            'institution' => $institution,
            'year_obtained' => $yearObtained,
            'document_storage_key' => $documentStorageKey,
            'status' => QualificationStatus::Active,
            'effective_from' => $createdAt,
            'effective_to' => null,
            'created_at' => $createdAt,
        ]);
    }

    public function findQualification(int $teacherId, int $qualificationId): ?TeacherQualificationSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('teachers', 'teacher_qualifications'))
            ->where('teacher_id', $teacherId)
            ->where('id', $qualificationId)
            ->first([
                'id',
                'teacher_id',
                'qualification_type',
                'title',
                'institution',
                'year_obtained',
                'document_storage_key',
                'status',
                'effective_from',
                'effective_to',
                'created_at',
            ]);

        return $row === null ? null : $this->mapQualification($row);
    }

    public function setQualificationDocumentStorageKey(
        int $teacherId,
        int $qualificationId,
        string $documentStorageKey,
    ): void {
        DB::table(SchemaHelper::qualified('teachers', 'teacher_qualifications'))
            ->where('teacher_id', $teacherId)
            ->where('id', $qualificationId)
            ->where('status', QualificationStatus::Active)
            ->update([
                'document_storage_key' => $documentStorageKey,
            ]);
    }

    public function voidQualification(int $teacherId, int $qualificationId, string $effectiveTo): bool
    {
        return DB::table(SchemaHelper::qualified('teachers', 'teacher_qualifications'))
            ->where('teacher_id', $teacherId)
            ->where('id', $qualificationId)
            ->where('status', QualificationStatus::Active)
            ->update([
                'status' => QualificationStatus::Voided,
                'effective_to' => $effectiveTo,
            ]) === 1;
    }

    public function listQualifications(int $teacherId): array
    {
        return DB::table(SchemaHelper::qualified('teachers', 'teacher_qualifications'))
            ->where('teacher_id', $teacherId)
            ->orderBy('id')
            ->get([
                'id',
                'teacher_id',
                'qualification_type',
                'title',
                'institution',
                'year_obtained',
                'document_storage_key',
                'status',
                'effective_from',
                'effective_to',
                'created_at',
            ])
            ->map(fn (object $row): TeacherQualificationSnapshot => $this->mapQualification($row))
            ->all();
    }

    private function mapQualification(object $row): TeacherQualificationSnapshot
    {
        return new TeacherQualificationSnapshot(
            id: (int) $row->id,
            teacherId: (int) $row->teacher_id,
            qualificationType: (int) $row->qualification_type,
            title: (string) $row->title,
            institution: $row->institution !== null ? (string) $row->institution : null,
            yearObtained: $row->year_obtained !== null ? (int) $row->year_obtained : null,
            documentStorageKey: $row->document_storage_key !== null ? (string) $row->document_storage_key : null,
            status: (int) $row->status,
            effectiveFrom: (string) $row->effective_from,
            effectiveTo: $row->effective_to !== null ? (string) $row->effective_to : null,
            createdAt: (string) $row->created_at,
        );
    }

    private function map(object $row): TeacherSnapshot
    {
        return new TeacherSnapshot(
            id: (int) $row->id,
            userId: $row->user_id !== null ? (int) $row->user_id : null,
            employeeCode: (string) $row->employee_code,
            nationalId: $row->national_id !== null ? (string) $row->national_id : null,
            firstName: (string) $row->first_name,
            lastName: (string) $row->last_name,
            fullName: (string) $row->full_name,
            specializationField: $row->specialization_field !== null ? (string) $row->specialization_field : null,
            hireDate: $row->hire_date !== null ? (string) $row->hire_date : null,
            status: (int) $row->status,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            isPrimary: (bool) $row->is_primary,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
