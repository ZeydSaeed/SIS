<?php

namespace App\Domain\Results\Data;

final readonly class OfficialAnnualGpaSource
{
    public function __construct(
        public int $annualResultId,
        public ?string $averageWeightedTotal,
        public bool $incomplete,
        public string $sourceFingerprint,
    ) {}
}
