<?php

namespace App\Domain\Results\Repositories;

use App\Domain\Results\Data\OfficialTranscriptSource;
use App\Domain\Results\Data\PersistIssuedTranscriptData;

interface TranscriptRepositoryInterface
{
    /**
     * @return array{student_id:int,academic_year_id:int}|null
     */
    public function findEnrollmentIdentity(int $enrollmentId, int $schoolId, int $academicYearId): ?array;

    public function findOfficialYearGpaSource(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?OfficialTranscriptSource;

    public function nextTranscriptVersion(int $schoolId, int $enrollmentId, int $academicYearId): int;

    public function insertIssued(PersistIssuedTranscriptData $data): int;

    public function findCurrentIssued(
        int $schoolId,
        int $enrollmentId,
        int $academicYearId,
    ): ?\App\Domain\Results\Data\IssuedTranscriptRead;
}
