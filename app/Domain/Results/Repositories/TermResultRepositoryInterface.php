<?php

namespace App\Domain\Results\Repositories;

use App\Domain\Results\Data\CurrentTermResultSnapshot;
use App\Domain\Results\Data\PersistOperationalTermResultData;
use App\Domain\Results\Data\TermGradeContribution;

interface TermResultRepositoryInterface
{
    /**
     * @return list<TermGradeContribution>
     */
    public function listOperationalContributions(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        int $termId,
        int $subjectId,
    ): array;

    /**
     * @return list<TermGradeContribution>
     */
    public function listOfficialContributions(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        int $termId,
        int $subjectId,
    ): array;

    /**
     * @return array{student_id:int, academic_year_id:int}|null
     */
    public function findEnrollmentIdentity(int $enrollmentId, int $schoolId, int $academicYearId): ?array;

    public function nextResultVersion(
        int $schoolId,
        int $enrollmentId,
        int $termId,
        int $subjectId,
    ): int;

    /**
     * @return list<int>
     */
    public function listRequiredSessionIds(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        int $termId,
        int $subjectId,
    ): array;

    /**
     * @param  list<int>  $sessionIds
     */
    public function countFinalizedCurrentGradesForSessions(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
        array $sessionIds,
    ): int;

    public function insertOperational(PersistOperationalTermResultData $data): int;

    public function insertOfficial(PersistOperationalTermResultData $data): int;

    public function findCurrentOperational(
        int $schoolId,
        int $enrollmentId,
        int $termId,
        int $subjectId,
    ): ?CurrentTermResultSnapshot;

    public function findCurrentOfficial(
        int $schoolId,
        int $enrollmentId,
        int $termId,
        int $subjectId,
    ): ?CurrentTermResultSnapshot;
}
