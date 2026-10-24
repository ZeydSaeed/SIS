<?php

namespace App\Application\Exams\Results;

final readonly class SaveExamTypeWeightVersionResult
{
    public function __construct(
        public int $versionNo,
        public bool $fromIdempotencyCache = false,
    ) {}
}
