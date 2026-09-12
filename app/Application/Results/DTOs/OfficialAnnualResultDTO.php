<?php

namespace App\Application\Results\DTOs;

final readonly class OfficialAnnualResultDTO
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $annualResultId,
        public int $resultVersion,
        public ?string $averageWeightedTotal,
        public bool $incomplete,
        public string $sourceFingerprint,
    ) {}
}
