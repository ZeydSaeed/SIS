<?php

namespace App\Domain\Results\Data;

final readonly class OfficialTranscriptSource
{
    public function __construct(
        public int $gpaResultId,
        public ?string $gpaValue,
        public string $scaleCode,
        public string $gpaFingerprint,
        public ?int $annualResultId,
        public ?string $annualFingerprint,
    ) {}
}
