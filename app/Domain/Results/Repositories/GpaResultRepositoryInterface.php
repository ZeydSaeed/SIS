<?php

namespace App\Domain\Results\Repositories;

use App\Domain\Results\Data\CurrentGpaResultSnapshot;
use App\Domain\Results\Data\OfficialAnnualGpaSource;
use App\Domain\Results\Data\PersistOperationalGpaResultData;

interface GpaResultRepositoryInterface
{
    public function findOfficialAnnualSource(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?OfficialAnnualGpaSource;

    /**
     * @return array{student_id:int, academic_year_id:int}|null
     */
    public function findEnrollmentIdentity(int $enrollmentId, int $schoolId, int $academicYearId): ?array;

    public function nextResultVersion(int $schoolId, int $enrollmentId, int $academicYearId): int;

    public function insertOperational(PersistOperationalGpaResultData $data): int;

    public function insertOfficial(PersistOperationalGpaResultData $data): int;

    public function findCurrentOperational(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?CurrentGpaResultSnapshot;

    public function findCurrentOfficial(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?CurrentGpaResultSnapshot;

    public function findOfficialYearGpaRead(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?\App\Domain\Results\Data\OfficialYearGpaRead;
}
