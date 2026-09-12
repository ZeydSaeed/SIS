<?php

namespace App\Domain\Results\Data;

final readonly class PersistOperationalTermResultData
{
    /**
     * @param  array<string, mixed>  $policyPin
     */
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $studentId,
        public int $academicYearId,
        public int $termId,
        public int $subjectId,
        public int $resultVersion,
        public ?string $weightedTotal,
        public ?int $passFail,
        public bool $incomplete,
        public string $sourceFingerprint,
        public int $calculationVersion,
        public array $policyPin,
        public string $calculatedAt,
        public ?string $correlationId,
        public ?int $createdBy,
    ) {}
}
