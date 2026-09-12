<?php

namespace App\Domain\Results\Data;

final readonly class AnnualResultCalculation
{
    /**
     * @param  list<int>  $sourceTermResultIds
     * @param  array<string, mixed>  $policyPin
     */
    public function __construct(
        public int $subjectsCounted,
        public int $subjectsPassed,
        public int $subjectsIncomplete,
        public ?string $averageWeightedTotal,
        public bool $incomplete,
        public string $sourceFingerprint,
        public array $sourceTermResultIds,
        public array $policyPin,
    ) {}
}
