<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

/** Accept (the grade is corrected through the official correction) or reject (the grade stays) a review. */
final readonly class DecideGradeReviewCommand implements Command
{
    public const ACCEPT = 'accept';

    public const REJECT = 'reject';

    public function __construct(
        public int $schoolId,
        public int $reviewId,
        public string $decision,
        public string $note,
        public ?string $newScore,
        public bool $isAbsent,
        public ?int $reviewerId,
        public ?string $idempotencyKey,
    ) {}
}
