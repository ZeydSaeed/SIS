<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Results\OpenGradeReviewResult;
use App\Domain\Exams\Exceptions\GradeNotFoundException;
use App\Domain\Exams\Exceptions\GradeReviewException;
use App\Domain\Exams\Repositories\GradeReviewRepositoryInterface;
use App\Domain\Exams\Repositories\StudentGradeRepositoryInterface;
use App\Domain\Exams\Support\GradeIdempotencyGuard;
use App\Domain\Exams\ValueObjects\GradeStatus;

final class OpenGradeReviewHandler implements CommandHandler
{
    private const COMMAND_NAME = 'OpenGradeReview';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentGradeRepositoryInterface $grades,
        private readonly GradeReviewRepositoryInterface $reviews,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): OpenGradeReviewResult
    {
        assert($command instanceof OpenGradeReviewCommand);

        $key = GradeIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return new OpenGradeReviewResult((int) $cached['review_id'], true);
        }
        if (trim($command->reason) === '') {
            throw GradeReviewException::noteRequired();
        }
        $grade = $this->grades->findByIdentity($command->gradeId, $command->academicYearId, $command->schoolId)
            ?? throw GradeNotFoundException::forIdentity($command->gradeId, $command->academicYearId);
        if (! $grade->isCurrent || $grade->status === GradeStatus::Voided->value) {
            throw GradeReviewException::gradeNotReviewable();
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $grade, $key): int {
            if ($this->reviews->hasOpenFor($command->schoolId, $command->gradeId)) {
                throw GradeReviewException::alreadyOpen();
            }
            $id = $this->reviews->insert($command->schoolId, $command->academicYearId, $command->gradeId, $grade->score, $grade->isAbsent, trim($command->reason), $command->requestedBy, $command->correlationId);
            $this->idempotency->store($key, self::COMMAND_NAME, ['review_id' => $id]);

            return $id;
        });

        return new OpenGradeReviewResult($id);
    }
}
