<?php

namespace App\Domain\Results\Data;

final readonly class PersistOperationalGpaResultData
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
        public ?string $gpaValue,
        public string $scaleCode,
        public ?int $sourceAnnualResultId,
        public bool $incomplete,
        public string $sourceFingerprint,
        public int $calculationVersion,
        public array $policyPin,
        public string $calculatedAt,
        public ?string $correlationId,
        public ?int $createdBy,
    ) {}
}
