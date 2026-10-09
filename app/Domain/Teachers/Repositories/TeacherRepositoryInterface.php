<?php

namespace App\Domain\Teachers\Repositories;

use App\Domain\Teachers\Data\TeacherQualificationSnapshot;
use App\Domain\Teachers\Data\TeacherSchoolMembershipSnapshot;
use App\Domain\Teachers\Data\TeacherSnapshot;
use App\Domain\Teachers\Data\TeacherSubjectSnapshot;
use App\Domain\Teachers\Data\TeachingAssignmentData;

interface TeacherRepositoryInterface
{
    public function employeeCodeExists(string $employeeCode): bool;

    /**
     * «استيراد المعلمين»: the teacher holding an employee code (case-insensitive) with the identity fields an import
     * row may leave blank, and whether the teacher is an active member of the school in the year.
     *
     * @return array{id: int, first_name: string, father_name: string|null, grandfather_name: string|null, last_name: string, national_id: string|null, specialization_field: string|null, hire_date: string|null, status: int, abbreviation: string|null, academic_title_id: int|null, in_school: bool}|null
     */
    public function importIdentity(string $employeeCode, int $schoolId, int $academicYearId): ?array;

    public function employeeCodeTakenByOther(string $employeeCode, int $exceptTeacherId): bool;

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
    ): int;

    public function assignSchool(
        int $teacherId,
        int $schoolId,
        int $academicYearId,
        bool $isPrimary,
        string $createdAt,
        ?int $employmentType = null,
    ): int;

    /** نوع التعيين of the active membership in the school/year (null clears it). */
    public function setEmploymentType(int $teacherId, int $schoolId, int $academicYearId, ?int $employmentType): void;

    /** Personal lesson limits of the active membership in the school/year (null clears a limit). */
    public function setWorkloadLimits(int $teacherId, int $schoolId, int $academicYearId, ?int $weeklyMin, ?int $weeklyMax, ?int $dailyMax): void;

    /** Lessons still on the timetable grid (active, lead or co-teacher) for these teachers in the school. */
    public function activeLessonCount(array $teacherIds, int $schoolId): int;

    public function activeTeachingAssignmentExists(TeachingAssignmentData $data): bool;

    public function addTeachingAssignment(TeachingAssignmentData $data): int;

    /**
     * @return array{teacher_id:int, subject_id:int, status:int}|null
     */
    public function findTeachingAssignment(int $schoolId, int $assignmentId): ?array;

    /** Ends an active assignment (status 2 + effective_to). False when not active in the school. */
    public function endTeachingAssignment(int $schoolId, int $assignmentId, string $effectiveTo, string $at): bool;

    /** Ends every active assignment of the teacher for the subject in the school/year. */
    public function endTeachingAssignmentsForSubject(
        int $teacherId,
        int $schoolId,
        int $academicYearId,
        int $subjectId,
        string $effectiveTo,
        string $at,
    ): int;

    public function leaveSchool(int $teacherId, int $schoolId, int $academicYearId, string $leftAt): void;

    public function findInSchool(int $teacherId, int $schoolId, ?int $academicYearId = null): ?TeacherSnapshot;

    /**
     * @return array{items: list<TeacherSnapshot>, total: int}
     */
    public function listForSchool(int $schoolId, int $academicYearId, int $page, int $perPage): array;

    public function belongsToSchool(int $teacherId, int $schoolId, ?int $academicYearId = null): bool;

    public function academicYearExists(int $academicYearId): bool;

    /** True when another teacher (not `$exceptTeacherId`) already has this national id. */
    public function nationalIdTaken(string $nationalId, ?int $exceptTeacherId = null): bool;

    public function isPrimaryInSchool(int $teacherId, int $schoolId, int $academicYearId): bool;

    public function setMembershipPrimary(
        int $teacherId,
        int $schoolId,
        int $academicYearId,
        bool $isPrimary,
    ): void;

    /**
     * @param  array{
     *     first_name?:string,
     *     last_name?:string,
     *     full_name?:string,
     *     national_id?:?string,
     *     specialization_field?:?string,
     *     hire_date?:?string,
     *     user_id?:?int,
     *     updated_at:string
     * }  $fields
     */
    public function updateTeacher(int $teacherId, array $fields): void;

    public function changeEmployeeCode(int $schoolId, int $teacherId, string $employeeCode, string $updatedAt): void;

    public function setStatus(int $teacherId, int $status, string $updatedAt): void;

    public function subjectExists(int $subjectId): bool;

    public function findSubjectAssignmentId(
        int $teacherId,
        int $subjectId,
        int $academicYearId,
        int $schoolId,
    ): ?int;

    public function assignSubject(
        int $teacherId,
        int $subjectId,
        int $academicYearId,
        int $schoolId,
        string $createdAt,
    ): int;

    public function unlinkSubject(
        int $teacherId,
        int $subjectId,
        int $academicYearId,
        int $schoolId,
    ): bool;

    public function addQualification(
        int $teacherId,
        int $qualificationType,
        string $title,
        ?string $institution,
        ?int $yearObtained,
        ?string $documentStorageKey,
        string $createdAt,
    ): int;

    public function findQualification(int $teacherId, int $qualificationId): ?TeacherQualificationSnapshot;

    public function setQualificationDocumentStorageKey(
        int $teacherId,
        int $qualificationId,
        string $documentStorageKey,
    ): void;

    public function voidQualification(int $teacherId, int $qualificationId, string $effectiveTo): bool;

    public function restoreQualification(int $teacherId, int $qualificationId): bool;

    /**
     * @return list<TeacherQualificationSnapshot>
     */
    public function listQualifications(int $teacherId): array;

    /**
     * @return list<TeacherSubjectSnapshot>
     */
    public function listSubjectAssignments(int $teacherId, int $schoolId, int $academicYearId): array;

    /**
     * Subject assignments of every teacher in the school for the year (one query — roster page).
     *
     * @return list<TeacherSubjectSnapshot>
     */
    public function listSubjectAssignmentsForSchool(int $schoolId, int $academicYearId): array;

    public function findSubjectAssignmentById(
        int $teacherId,
        int $schoolId,
        int $assignmentId,
    ): ?TeacherSubjectSnapshot;

    /**
     * @return list<TeacherSchoolMembershipSnapshot>|null
     */
    public function listSchoolMemberships(int $teacherId, int $schoolId, ?int $academicYearId = null): ?array;

    public function findSchoolMembershipById(
        int $teacherId,
        int $schoolId,
        int $membershipId,
    ): ?TeacherSchoolMembershipSnapshot;
}
