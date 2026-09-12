<?php

namespace App\Domain\Results\Data;

final readonly class TermResultRollupRow
{
    public function __construct(
        public int $termResultId,
        public int $termId,
        public int $subjectId,
        public ?string $weightedTotal,
        public ?int $passFail,
        public bool $incomplete,
    ) {}
}
