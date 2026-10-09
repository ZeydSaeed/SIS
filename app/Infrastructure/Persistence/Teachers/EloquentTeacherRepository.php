<?php

namespace App\Infrastructure\Persistence\Teachers;

use App\Database\SchemaHelper;
use App\Domain\Teachers\Data\TeacherQualificationSnapshot;
use App\Domain\Teachers\Data\TeacherSchoolMembershipSnapshot;
use App\Domain\Teachers\Data\TeacherSnapshot;
use App\Domain\Teachers\Data\TeacherSubjectSnapshot;
use App\Domain\Teachers\Data\TeachingAssignmentData;
use App\Domain\Teachers\Repositories\TeacherRepositoryInterface;
use App\Domain\Teachers\ValueObjects\QualificationStatus;
use App\Domain\Teachers\ValueObjects\TeachingAssignmentStatus;
use Illuminate\Support\Facades\DB;

final class EloquentTeacherRepository implements TeacherRepositoryInterface
{
    public function academicYearExists(int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('id', $academicYearId)->exists();
    }

    public function nationalIdTaken(string $nationalId, ?int $exceptTeacherId = null): bool
    {
        return DB::table(SchemaHelper::qualified('teachers', 'teachers'))
            ->where('national_id', $nationalId)
            ->when($exceptTeacherId !== null, fn ($q) => $q->where('id', '<>', $exceptTeacherId))
            ->exists();
    }

    public function importIdentity(string $employeeCode, int $schoolId, int $academicYearId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('teachers', 'teachers'))
            ->whereRaw('upper(employee_code) = ?', [strtoupper(trim($employeeCode))])
            ->first(['id', 'first_name', 'father_name', 'grandfather_name', 'last_name', 'national_id', 'specialization_field', 'hire_date', 'status', 'abbreviation', 'academic_title_id']);
        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'first_name' => (string) $row->first_name,
            'father_name' => $row->father_name !== null ? (string) $row->father_name : null,
            'grandfather_name' => $row->grandfather_name !== null ? (string) $row->grandfather_name : null,
            'last_name' => (string) $row->last_name,
            'national_id' => $row->national_id !== null ? (string) $row->national_id : null,
            'specialization_field' => $row->specialization_field !== null ? (string) $row->specialization_field : null,
            'hire_date' => $row->hire_date !== null ? substr((string) $row->hire_date, 0, 10) : null,
            'status' => (int) $row->status,
            'abbreviation' => $row->abbreviation !== null ? (string) $row->abbreviation : null,
            'academic_title_id' => $row->academic_title_id !== null ? (int) $row->academic_title_id : null,
            'in_school' => $this->belongsToSchool((int) $row->id, $schoolId, $academicYearId),
        ];
    }

    public function employeeCodeExists(string $employeeCode): bool
    {
        return DB::table(SchemaHelper::qualified('teachers', 'teachers'))
            ->where('employee_code', $employeeCode)
            ->exists();
    }

    public function employeeCodeTakenByOther(string $employeeCode, int $exceptTeacherId): bool
    {
        return DB::table(SchemaHelper::qualified('teachers', 'teachers'))
            ->where('employee_code', $employeeCode)
            ->where('id', '!=', $exceptTeacherId)
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
        ?string $fatherName = null,
        ?string $grandfatherName = null,
    ): int {
        return (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'user_id' => $userId,
            'employee_code' => $employeeCode,
            'national_id' => $nationalId,
            'first_name' => $firstName,
            'father_name' => $fatherName,
            'grandfather_name' => $grandfatherName,
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
        ?int $employmentType = null,
    ): int {
        $this->bindSchool($schoolId);

        $existing = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->first(['id', 'left_at']);

        if ($existing !== null) {
            DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
                ->where('id', (int) $existing->id)
                ->update($employmentType === null
                    ? ['left_at' => null, 'is_primary' => $isPrimary]
                    : ['left_at' => null, 'is_primary' => $isPrimary, 'employment_type' => $employmentType]);

            return (int) $existing->id;
        }

        return (int) DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))->insertGetId([
            'teacher_id' => $teacherId,
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'is_primary' => $isPrimary,
            'employment_type' => $employmentType,
            'left_at' => null,
            'created_at' => $createdAt,
        ]);
    }

    public function setEmploymentType(int $teacherId, int $schoolId, int $academicYearId, ?int $employmentType): void
    {
        $this->bindSchool($schoolId);

        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('left_at')
            ->update(['employment_type' => $employmentType]);
    }

    public function setWorkloadLimits(int $teacherId, int $schoolId, int $academicYearId, ?int $weeklyMin, ?int $weeklyMax, ?int $dailyMax): void
    {
        $this->bindSchool($schoolId);

        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('left_at')
            ->update([
                'weekly_lessons_min' => $weeklyMin,
                'weekly_lessons_max' => $weeklyMax,
                'daily_lessons_max' => $dailyMax,
            ]);
    }

    public function activeLessonCount(array $teacherIds, int $schoolId): int
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)
            ->where('lifecycle_status', 1)
            ->where(fn ($q) => $q->whereIn('teacher_id', $teacherIds)->orWhereIn('co_teacher_id', $teacherIds))
            ->count();
    }

    public function activeTeachingAssignmentExists(TeachingAssignmentData $data): bool
    {
        $this->bindSchool($data->schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))
            ->where('teacher_id', $data->teacherId)
            ->where('academic_year_id', $data->academicYearId)
            ->where('subject_id', $data->subjectId)
            ->where('branch_id', $data->branchId)
            ->where('status', TeachingAssignmentStatus::Active)
            ->when($data->departmentId === null, fn ($q) => $q->whereNull('department_id'), fn ($q) => $q->where('department_id', $data->departmentId))
            ->when($data->classId === null, fn ($q) => $q->whereNull('class_id'), fn ($q) => $q->where('class_id', $data->classId))
            ->when($data->sectionId === null, fn ($q) => $q->whereNull('section_id'), fn ($q) => $q->where('section_id', $data->sectionId))
            ->exists();
    }

    public function addTeachingAssignment(TeachingAssignmentData $data): int
    {
        $this->bindSchool($data->schoolId);

        return (int) DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))->insertGetId([
            'teacher_id' => $data->teacherId,
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'subject_id' => $data->subjectId,
            'branch_id' => $data->branchId,
            'department_id' => $data->departmentId,
            'class_id' => $data->classId,
            'section_id' => $data->sectionId,
            'status' => TeachingAssignmentStatus::Active,
            'effective_from' => $data->effectiveFrom,
            'effective_to' => null,
            'created_at' => $data->at,
            'updated_at' => $data->at,
        ]);
    }

    public function findTeachingAssignment(int $schoolId, int $assignmentId): ?array
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))
            ->where('id', $assignmentId)
            ->where('school_id', $schoolId)
            ->first(['teacher_id', 'subject_id', 'status']);

        return $row === null ? null : [
            'teacher_id' => (int) $row->teacher_id,
            'subject_id' => (int) $row->subject_id,
            'status' => (int) $row->status,
        ];
    }

    public function endTeachingAssignment(int $schoolId, int $assignmentId, string $effectiveTo, string $at): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))
            ->where('id', $assignmentId)
            ->where('school_id', $schoolId)
            ->where('status', TeachingAssignmentStatus::Active)
            ->update($this->endedFields($effectiveTo, $at)) > 0;
    }

    public function endTeachingAssignmentsForSubject(
        int $teacherId,
        int $schoolId,
        int $academicYearId,
        int $subjectId,
        string $effectiveTo,
        string $at,
    ): int {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('subject_id', $subjectId)
            ->where('status', TeachingAssignmentStatus::Active)
            ->update($this->endedFields($effectiveTo, $at));
    }

    /** @return array<string, mixed> */
    private function endedFields(string $effectiveTo, string $at): array
    {
        return [
            'status' => TeachingAssignmentStatus::Ended,
            'effective_to' => $effectiveTo,
            'updated_at' => $at,
        ];
    }

    public function leaveSchool(int $teacherId, int $schoolId, int $academicYearId, string $leftAt): void
    {
        $this->bindSchool($schoolId);

        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('left_at')
            ->update([
                'left_at' => $leftAt,
                'is_primary' => false,
            ]);
    }

    public function findInSchool(int $teacherId, int $schoolId, ?int $academicYearId = null): ?TeacherSnapshot
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('teachers', 'teachers').' as t')
            ->join(SchemaHelper::qualified('teachers', 'teacher_schools').' as ts', 'ts.teacher_id', '=', 't.id')
            ->where('t.id', $teacherId)
            ->where('ts.school_id', $schoolId)
            ->whereNull('ts.left_at')
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
        $this->bindSchool($schoolId);

        $base = DB::table(SchemaHelper::qualified('teachers', 'teachers').' as t')
            ->join(SchemaHelper::qualified('teachers', 'teacher_schools').' as ts', 'ts.teacher_id', '=', 't.id')
            ->where('ts.school_id', $schoolId)
            ->where('ts.academic_year_id', $academicYearId)
            ->whereNull('ts.left_at');

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
            ->where('school_id', $schoolId)
            ->whereNull('left_at');

        if ($academicYearId !== null) {
            $q->where('academic_year_id', $academicYearId);
        }

        return $q->exists();
    }

    public function isPrimaryInSchool(int $teacherId, int $schoolId, int $academicYearId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('is_primary', true)
            ->whereNull('left_at')
            ->exists();
    }

    public function setMembershipPrimary(
        int $teacherId,
        int $schoolId,
        int $academicYearId,
        bool $isPrimary,
    ): void {
        $this->bindSchool($schoolId);

        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->whereNull('left_at')
            ->update(['is_primary' => $isPrimary]);
    }

    public function updateTeacher(int $teacherId, array $fields): void
    {
        DB::table(SchemaHelper::qualified('teachers', 'teachers'))
            ->where('id', $teacherId)
            ->update($fields);
    }

    public function changeEmployeeCode(int $schoolId, int $teacherId, string $employeeCode, string $updatedAt): void
    {
        $this->bindSchool($schoolId);

        DB::table(SchemaHelper::qualified('teachers', 'teachers'))
            ->where('id', $teacherId)
            ->update([
                'employee_code' => $employeeCode,
                'updated_at' => $updatedAt,
            ]);
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

    public function restoreQualification(int $teacherId, int $qualificationId): bool
    {
        return DB::table(SchemaHelper::qualified('teachers', 'teacher_qualifications'))
            ->where('teacher_id', $teacherId)
            ->where('id', $qualificationId)
            ->where('status', QualificationStatus::Voided)
            ->update([
                'status' => QualificationStatus::Active,
                'effective_to' => null,
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

    public function listSubjectAssignments(int $teacherId, int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teacher_subjects'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->orderBy('id')
            ->get(['id', 'teacher_id', 'subject_id', 'academic_year_id', 'school_id', 'created_at'])
            ->map(fn (object $row): TeacherSubjectSnapshot => new TeacherSubjectSnapshot(
                id: (int) $row->id,
                teacherId: (int) $row->teacher_id,
                subjectId: (int) $row->subject_id,
                academicYearId: (int) $row->academic_year_id,
                schoolId: (int) $row->school_id,
                createdAt: (string) $row->created_at,
            ))
            ->all();
    }

    public function listSubjectAssignmentsForSchool(int $schoolId, int $academicYearId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teacher_subjects'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->orderBy('id')
            ->get(['id', 'teacher_id', 'subject_id', 'academic_year_id', 'school_id', 'created_at'])
            ->map(fn (object $row): TeacherSubjectSnapshot => new TeacherSubjectSnapshot(
                id: (int) $row->id,
                teacherId: (int) $row->teacher_id,
                subjectId: (int) $row->subject_id,
                academicYearId: (int) $row->academic_year_id,
                schoolId: (int) $row->school_id,
                createdAt: (string) $row->created_at,
            ))
            ->all();
    }

    public function findSubjectAssignmentById(
        int $teacherId,
        int $schoolId,
        int $assignmentId,
    ): ?TeacherSubjectSnapshot {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('teachers', 'teacher_subjects'))
            ->where('id', $assignmentId)
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->first(['id', 'teacher_id', 'subject_id', 'academic_year_id', 'school_id', 'created_at']);

        if ($row === null) {
            return null;
        }

        return new TeacherSubjectSnapshot(
            id: (int) $row->id,
            teacherId: (int) $row->teacher_id,
            subjectId: (int) $row->subject_id,
            academicYearId: (int) $row->academic_year_id,
            schoolId: (int) $row->school_id,
            createdAt: (string) $row->created_at,
        );
    }

    public function listSchoolMemberships(int $teacherId, int $schoolId, ?int $academicYearId = null): ?array
    {
        $this->bindSchool($schoolId);

        if (! $this->belongsToSchool($teacherId, $schoolId, $academicYearId)) {
            // Still allow listing if teacher has any membership (including left) in this school.
            $inSchool = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
                ->where('teacher_id', $teacherId)
                ->where('school_id', $schoolId)
                ->exists();
            if (! $inSchool) {
                return null;
            }
        }

        $q = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($academicYearId !== null) {
            $q->where('academic_year_id', $academicYearId);
        }

        return $q->get([
            'id',
            'teacher_id',
            'school_id',
            'academic_year_id',
            'is_primary',
            'left_at',
            'created_at',
        ])->map(fn (object $row): TeacherSchoolMembershipSnapshot => new TeacherSchoolMembershipSnapshot(
            id: (int) $row->id,
            teacherId: (int) $row->teacher_id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            isPrimary: (bool) $row->is_primary,
            leftAt: $row->left_at !== null ? (string) $row->left_at : null,
            createdAt: (string) $row->created_at,
        ))->all();
    }

    public function findSchoolMembershipById(
        int $teacherId,
        int $schoolId,
        int $membershipId,
    ): ?TeacherSchoolMembershipSnapshot {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('id', $membershipId)
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->first([
                'id',
                'teacher_id',
                'school_id',
                'academic_year_id',
                'is_primary',
                'left_at',
                'created_at',
            ]);

        if ($row === null) {
            return null;
        }

        return new TeacherSchoolMembershipSnapshot(
            id: (int) $row->id,
            teacherId: (int) $row->teacher_id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            isPrimary: (bool) $row->is_primary,
            leftAt: $row->left_at !== null ? (string) $row->left_at : null,
            createdAt: (string) $row->created_at,
        );
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
