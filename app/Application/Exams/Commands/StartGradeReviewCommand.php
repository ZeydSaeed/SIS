<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

/** The reviewer takes an open review (submitted to under review). */
final readonly class StartGradeReviewCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $reviewId,
        public ?int $reviewerId,
        public ?string $idempotencyKey,
    ) {}
}
