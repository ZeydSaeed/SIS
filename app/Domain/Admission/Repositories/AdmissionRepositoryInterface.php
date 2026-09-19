<?php

namespace App\Domain\Admission\Repositories;

use App\Domain\Admission\Data\CreateApplicationDraftData;
use App\Domain\Admission\Data\CreateApplicationPeriodData;
use App\Domain\Admission\Data\RegisterApplicationDocumentData;
use App\Domain\Admission\Data\UpdateApplicationDraftData;
use App\Domain\Admission\Data\UpdateApplicationPeriodData;

interface AdmissionRepositoryInterface
{
    public function createPeriod(CreateApplicationPeriodData $data): int;

    public function createApplication(CreateApplicationDraftData $data): int;

    public function generateApplicationNumber(int $schoolId, int $academicYearId): string;

    /**
     * @return array{
     *     id:int,
     *     school_id:int,
     *     academic_year_id:int,
     *     name:string,
     *     status:int,
     *     start_date:string,
     *     end_date:string,
     *     max_applications:?int
     * }|null
     */
    public function findPeriodForSchool(int $periodId, int $schoolId): ?array;

    public function updatePeriod(UpdateApplicationPeriodData $data): void;

    public function updatePeriodStatus(int $periodId, int $status): void;

    public function countApplicationsInPeriod(int $periodId): int;

    /**
     * @return array{
     *     id:int,
     *     application_period_id:int,
     *     application_number:string,
     *     first_name:string,
     *     last_name:string,
     *     national_id:?string,
     *     birth_date:string,
     *     gender:int,
     *     grade_level_id:int,
     *     specialization_id:?int,
     *     status:int,
     *     notes:?string,
     *     student_id:?int,
     *     school_id:int
     * }|null
     */
    public function findApplicationForSchool(int $applicationId, int $schoolId): ?array;

    public function updateDraft(UpdateApplicationDraftData $data): void;

    public function transitionApplicationStatus(
        int $applicationId,
        int $toStatus,
        ?int $reviewedBy,
        ?string $notes,
    ): void;

    public function markConverted(int $applicationId, int $studentId, ?int $reviewedBy): void;

    public function registerDocument(RegisterApplicationDocumentData $data): int;
}
