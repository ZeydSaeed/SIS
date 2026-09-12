<?php

namespace App\Domain\Results\Repositories;

use App\Domain\Results\Data\CurrentAnnualResultSnapshot;
use App\Domain\Results\Data\PersistOperationalAnnualResultData;
use App\Domain\Results\Data\TermResultRollupRow;

interface AnnualResultRepositoryInterface
{
    /**
     * @return list<TermResultRollupRow>
     */
    public function listCurrentOperationalTermRows(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): array;

    /**
     * @return list<TermResultRollupRow>
     */
    public function listCurrentOfficialTermRows(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): array;

    /**
     * @return array{student_id:int, academic_year_id:int}|null
     */
    public function findEnrollmentIdentity(int $enrollmentId, int $schoolId, int $academicYearId): ?array;

    public function nextResultVersion(int $schoolId, int $enrollmentId, int $academicYearId): int;

    public function insertOperational(PersistOperationalAnnualResultData $data): int;

    public function insertOfficial(PersistOperationalAnnualResultData $data): int;

    public function findCurrentOperational(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?CurrentAnnualResultSnapshot;

    public function findCurrentOfficial(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?CurrentAnnualResultSnapshot;
}
