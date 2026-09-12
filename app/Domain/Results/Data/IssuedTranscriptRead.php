<?php

namespace App\Domain\Results\Data;

final readonly class IssuedTranscriptRead
{
    public function __construct(
        public int $id,
        public int $studentId,
        public int $transcriptVersion,
        public string $transcriptNumber,
        public string $payloadHash,
        public ?string $storageKey,
        public string $issuedAt,
    ) {}
}
