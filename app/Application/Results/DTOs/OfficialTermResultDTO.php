<?php

namespace App\Application\Results\DTOs;

final readonly class OfficialTermResultDTO
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $termId,
        public int $subjectId,
        public int $termResultId,
        public int $resultVersion,
        public ?string $weightedTotal,
        public bool $incomplete,
        public string $sourceFingerprint,
    ) {}
}
