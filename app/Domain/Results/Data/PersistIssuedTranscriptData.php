<?php

namespace App\Domain\Results\Data;

final readonly class PersistIssuedTranscriptData
{
    /**
     * @param  array<string, mixed>  $policyPin
     */
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public int $enrollmentId,
        public int $academicYearId,
        public int $transcriptVersion,
        public string $transcriptNumber,
        public ?string $storageKey,
        public string $payloadHash,
        public string $sourceFingerprint,
        public array $policyPin,
        public string $issuedAt,
        public ?int $issuedBy,
        public ?string $correlationId,
    ) {}
}
