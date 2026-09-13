<?php

namespace App\Domain\Teachers\Repositories;

use App\Domain\Teachers\Data\TeacherQualificationSnapshot;
use App\Domain\Teachers\Data\TeacherSnapshot;

interface TeacherRepositoryInterface
{
    public function employeeCodeExists(string $employeeCode): bool;

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
    ): int;

    public function assignSchool(
        int $teacherId,
        int $schoolId,
        int $academicYearId,
        bool $isPrimary,
        string $createdAt,
    ): int;

    public function findInSchool(int $teacherId, int $schoolId, ?int $academicYearId = null): ?TeacherSnapshot;

    /**
     * @return array{items: list<TeacherSnapshot>, total: int}
     */
    public function listForSchool(int $schoolId, int $academicYearId, int $page, int $perPage): array;

    public function belongsToSchool(int $teacherId, int $schoolId, ?int $academicYearId = null): bool;

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

    /**
     * @return list<TeacherQualificationSnapshot>
     */
    public function listQualifications(int $teacherId): array;
}
