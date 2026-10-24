<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Results\WithdrawGradeReviewResult;
use App\Domain\Exams\Exceptions\GradeReviewException;
use App\Domain\Exams\Repositories\GradeReviewRepositoryInterface;
use App\Domain\Exams\Support\GradeIdempotencyGuard;

final class WithdrawGradeReviewHandler implements CommandHandler
{
    private const COMMAND_NAME = 'WithdrawGradeReview';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GradeReviewRepositoryInterface $reviews,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): WithdrawGradeReviewResult
    {
        assert($command instanceof WithdrawGradeReviewCommand);

        $key = GradeIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return new WithdrawGradeReviewResult((int) $cached['review_id'], true);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $review = $this->reviews->lock($command->schoolId, $command->reviewId)
                ?? throw GradeReviewException::notFound($command->reviewId);
            if (! in_array($review['status'], ['submitted', 'under_review'], true)) {
                throw GradeReviewException::invalidState($review['status']);
            }
            if ($review['requested_by'] === null || $review['requested_by'] !== $command->actorUserId) {
                throw GradeReviewException::notRequester();
            }
            $this->reviews->withdraw($review['id']);
            $this->idempotency->store($key, self::COMMAND_NAME, ['review_id' => $review['id']]);
        });

        return new WithdrawGradeReviewResult($command->reviewId);
    }
}
