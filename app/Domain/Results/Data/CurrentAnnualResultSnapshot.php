<?php

namespace App\Domain\Results\Data;

final readonly class CurrentAnnualResultSnapshot
{
    public function __construct(
        public int $id,
        public int $resultVersion,
        public string $sourceFingerprint,
        public ?string $averageWeightedTotal,
        public bool $incomplete,
    ) {}
}
