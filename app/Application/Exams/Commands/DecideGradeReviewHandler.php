<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Results\DecideGradeReviewResult;
use App\Application\Exams\Support\GradeReviewIndependence;
use App\Domain\Exams\Exceptions\GradeReviewException;
use App\Domain\Exams\Repositories\GradeReviewRepositoryInterface;
use App\Domain\Exams\Support\GradeIdempotencyGuard;

/**
 * Accepting goes through the official correction (VOID + INSERT, with the review number in its reason) and records
 * the resulting grade; rejecting leaves the grade as it is. Dependent results are NOT recalculated here: they are
 * rebuilt through the results rebuild path and the publication drift check flags what changed.
 */
final class DecideGradeReviewHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DecideGradeReview';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly GradeReviewRepositoryInterface $reviews,
        private readonly GradeReviewIndependence $independence,
        private readonly CorrectStudentGradeHandler $correct,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DecideGradeReviewResult
    {
        assert($command instanceof DecideGradeReviewCommand);

        $key = GradeIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return new DecideGradeReviewResult((int) $cached['review_id'], true, $cached['resulting_grade_id'] ?? null);
        }
        $this->assertWellFormed($command);
        $reviewerId = (int) $command->reviewerId;

        $resulting = $this->unitOfWork->transaction(function () use ($command, $reviewerId, $key): ?int {
            $review = $this->reviews->lock($command->schoolId, $command->reviewId)
                ?? throw GradeReviewException::notFound($command->reviewId);
            if ($review['status'] !== 'under_review') {
                throw GradeReviewException::invalidState($review['status']);
            }
            if ($review['assigned_to'] !== $reviewerId) {
                throw GradeReviewException::notAssignee();
            }
            $this->independence->assertIndependent($review, $reviewerId);
            $note = trim($command->note);

            if ($command->decision === DecideGradeReviewCommand::ACCEPT) {
                $resulting = $this->correct->handle(new CorrectStudentGradeCommand(
                    schoolId: $command->schoolId,
                    gradeId: $review['grade_id'],
                    academicYearId: $review['academic_year_id'],
                    score: $command->isAbsent ? null : $command->newScore,
                    isAbsent: $command->isAbsent,
                    reason: $review['review_number'].': '.$note,
                    correctedBy: $reviewerId,
                    idempotencyKey: 'grade-review:'.hash('sha256', $key),
                ))->newGradeId;
                $this->reviews->accept($review['id'], $reviewerId, $note, (int) $resulting);
            } else {
                $resulting = null;
                $this->reviews->reject($review['id'], $reviewerId, $note);
            }
            $this->idempotency->store($key, self::COMMAND_NAME, ['review_id' => $review['id'], 'resulting_grade_id' => $resulting]);

            return $resulting;
        });

        return new DecideGradeReviewResult($command->reviewId, false, $resulting);
    }

    private function assertWellFormed(DecideGradeReviewCommand $command): void
    {
        if (! in_array($command->decision, [DecideGradeReviewCommand::ACCEPT, DecideGradeReviewCommand::REJECT], true)) {
            throw GradeReviewException::decisionInvalid($command->decision);
        }
        if (trim($command->note) === '') {
            throw GradeReviewException::noteRequired();
        }
        if ($command->decision === DecideGradeReviewCommand::ACCEPT && ! $command->isAbsent && ($command->newScore === null || $command->newScore === '')) {
            throw GradeReviewException::newScoreRequired();
        }
        if ($command->reviewerId === null) {
            throw GradeReviewException::conflictOfInterest();
        }
    }
}
