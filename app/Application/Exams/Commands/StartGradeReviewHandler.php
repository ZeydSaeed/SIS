<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Results\StartGradeReviewResult;
use App\Application\Exams\Support\GradeReviewIndependence;
use App\Domain\Exams\Exceptions\GradeReviewException;
use App\Domain\Exams\Repositories\GradeReviewRepositoryInterface;
use App\Domain\Exams\Support\GradeIdempotencyGuard;

final class StartGradeReviewHandler implements CommandHandler
{
    private const COMMAND_NAME = 'StartGradeReview';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GradeReviewRepositoryInterface $reviews,
        private readonly GradeReviewIndependence $independence,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): StartGradeReviewResult
    {
        assert($command instanceof StartGradeReviewCommand);

        $key = GradeIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return new StartGradeReviewResult((int) $cached['review_id'], true);
        }
        $reviewerId = $command->reviewerId ?? throw GradeReviewException::conflictOfInterest();

        $this->unitOfWork->transaction(function () use ($command, $reviewerId, $key): void {
            $review = $this->reviews->lock($command->schoolId, $command->reviewId)
                ?? throw GradeReviewException::notFound($command->reviewId);
            if ($review['status'] !== 'submitted') {
                throw GradeReviewException::invalidState($review['status']);
            }
            $this->independence->assertIndependent($review, $reviewerId);
            $this->reviews->start($review['id'], $reviewerId);
            $this->idempotency->store($key, self::COMMAND_NAME, ['review_id' => $review['id']]);
        });

        return new StartGradeReviewResult($command->reviewId);
    }
}
