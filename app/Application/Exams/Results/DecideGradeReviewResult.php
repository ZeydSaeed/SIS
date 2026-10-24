<?php

namespace App\Application\Exams\Results;

final readonly class DecideGradeReviewResult
{
    public function __construct(
        public int $reviewId,
        public bool $fromIdempotencyCache = false,
        public ?int $resultingGradeId = null,
    ) {}
}
