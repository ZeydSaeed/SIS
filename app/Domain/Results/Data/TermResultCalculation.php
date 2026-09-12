<?php

namespace App\Domain\Results\Data;

final readonly class TermResultCalculation
{
    /**
     * @param  list<int>  $sourceGradeIds
     * @param  array<string, mixed>  $policyPin
     */
    public function __construct(
        public ?string $weightedTotal,
        public ?int $passFail,
        public bool $incomplete,
        public string $sourceFingerprint,
        public array $sourceGradeIds,
        public array $policyPin,
    ) {}
}
