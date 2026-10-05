<?php

namespace App\Domain\Admission\Repositories;

use App\Domain\Admission\Data\CreateApplicationDraftData;
use App\Domain\Admission\Data\CreateApplicationPeriodData;
use App\Domain\Admission\Data\RegisterApplicationDocumentData;
use App\Domain\Admission\Data\TransferApplicationData;
use App\Domain\Admission\Data\UpdateApplicationDraftData;
use App\Domain\Admission\Data\UpdateApplicationFollowUpData;
use App\Domain\Admission\Data\UpdateApplicationPeriodData;

interface AdmissionRepositoryInterface
{
    public function createPeriod(CreateApplicationPeriodData $data): int;

    public function createApplication(CreateApplicationDraftData $data): int;

    public function generateApplicationNumber(int $schoolId, int $academicYearId): string;

    /**
     * Periods are shared by the schools of their directorate (directorate_id NULL = legacy, every school).
     *
     * @return array{
     *     id:int,
     *     academic_year_id:int,
     *     directorate_id:?int,
     *     name:string,
     *     status:int,
     *     start_date:string,
     *     end_date:?string,
     *     max_applications:?int
     * }|null
     */
    public function findPeriod(int $periodId): ?array;

    /** The directorate (المديرية) a school belongs to. */
    public function schoolDirectorateId(int $schoolId): ?int;

    public function updatePeriod(UpdateApplicationPeriodData $data): void;

    public function updatePeriodStatus(int $periodId, int $status): void;

    /** Applications of one school in a (shared) period — max_applications applies per school. */
    public function countApplicationsInPeriod(int $periodId, int $schoolId): int;

    /**
     * @return array{
     *     id:int,
     *     application_period_id:int,
     *     application_number:string,
     *     first_name:string,
     *     father_name:?string,
     *     grandfather_name:?string,
     *     great_grandfather_name:?string,
     *     last_name:string,
     *     mother_name:?string,
     *     maternal_father_name:?string,
     *     maternal_grandfather_name:?string,
     *     national_id:?string,
     *     birth_date:string,
     *     birth_place:?string,
     *     gender:int,
     *     grade_level_id:?int,
     *     intended_grade_name:?string,
     *     department_name:?string,
     *     specialization_id:?int,
     *     specialization_name:?string,
     *     governorate:?string,
     *     neighborhood:?string,
     *     administrative_unit:?int,
     *     father_occupation:?string,
     *     mother_occupation:?string,
     *     student_mobile:?string,
     *     guardian_mobile:?string,
     *     previous_school_name:?string,
     *     graduation_year:?int,
     *     previous_gpa:?float,
     *     previous_study_track:?int,
     *     mathematics_grade:?float,
     *     physics_grade:?float,
     *     request_kind:int,
     *     school_name:?string,
     *     status:int,
     *     notes:?string,
     *     student_id:?int,
     *     school_id:int,
     *     academic_year_id:int
     * }|null
     */
    public function findApplicationForSchool(int $applicationId, int $schoolId): ?array;

    /**
     * @return list<array{id:int, document_type:int, storage_key:string, file_name:string, file_hash:string}>
     */
    public function listDocumentsForApplication(int $applicationId): array;

    /**
     * Accepted applications that never received a student row (failed convert leftovers).
     *
     * @return list<int>
     */
    public function findAcceptedApplicationIdsWithoutStudent(int $schoolId): array;

    public function updateDraft(UpdateApplicationDraftData $data): void;

    public function updateFollowUp(UpdateApplicationFollowUpData $data): void;

    public function transitionApplicationStatus(
        int $applicationId,
        int $toStatus,
        ?int $reviewedBy,
        ?string $notes,
    ): void;

    public function markConverted(int $applicationId, int $studentId, ?int $reviewedBy): void;

    public function registerDocument(RegisterApplicationDocumentData $data): int;

    /** Move the application and append the transfer history row (caller binds the RLS transfer target). */
    public function transferApplication(TransferApplicationData $data): void;
}
