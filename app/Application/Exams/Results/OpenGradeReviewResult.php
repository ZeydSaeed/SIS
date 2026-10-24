<?php

namespace App\Application\Exams\Results;

final readonly class OpenGradeReviewResult
{
    public function __construct(
        public int $reviewId,
        public bool $fromIdempotencyCache = false,
    ) {}
}
