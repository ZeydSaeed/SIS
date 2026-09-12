<?php

namespace App\Domain\Results\Data;

final readonly class PersistOperationalAnnualResultData
{
    /**
     * @param  array<string, mixed>  $policyPin
     */
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $studentId,
        public int $academicYearId,
        public int $resultVersion,
        public int $subjectsCounted,
        public int $subjectsPassed,
        public int $subjectsIncomplete,
        public ?string $averageWeightedTotal,
        public bool $incomplete,
        public string $sourceFingerprint,
        public int $calculationVersion,
        public array $policyPin,
        public string $calculatedAt,
        public ?string $correlationId,
        public ?int $createdBy,
    ) {}
}
