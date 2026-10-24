<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;

/** The requester withdraws a review that is still open. */
final readonly class WithdrawGradeReviewCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $reviewId,
        public ?int $actorUserId,
        public ?string $idempotencyKey,
    ) {}
}
