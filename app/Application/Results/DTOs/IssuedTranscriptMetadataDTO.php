<?php

namespace App\Application\Results\DTOs;

final readonly class IssuedTranscriptMetadataDTO
{
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $studentId,
        public int $academicYearId,
        public int $transcriptId,
        public int $transcriptVersion,
        public string $transcriptNumber,
        public string $payloadHash,
        public ?string $storageKey,
        public string $issuedAt,
    ) {}
}
