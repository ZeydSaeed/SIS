<?php

namespace App\Domain\Exams\Services;

use App\Domain\Exams\Data\ExamSnapshot;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\ValueObjects\ExamStatus;

/**
 * CreateExamSession preconditions — keeps Application handler within ARCH-103.
 */
final class CreateExamSessionGuard
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
    ) {}

    public function assertReady(
        int $schoolId,
        int $examId,
        int $subjectId,
        string $startTime,
        string $endTime,
        int $maxGrade,
        int $passGrade,
        ?int $roomId,
    ): ExamSnapshot {
        if ($endTime <= $startTime) {
            throw ExamValidationException::invalidSessionTimeRange();
        }

        if ($passGrade < 0 || $passGrade > $maxGrade || $maxGrade <= 0) {
            throw ExamValidationException::invalidPassMaxGrade();
        }

        $exam = $this->exams->findByIdAndSchool($examId, $schoolId);
        if ($exam === null) {
            throw ExamValidationException::parentExamNotFound();
        }

        if ($exam->status === ExamStatus::Cancelled->value) {
            throw ExamValidationException::parentExamCancelled();
        }

        if (! $this->exams->subjectExists($subjectId)) {
            throw ExamValidationException::missingSubject();
        }

        if ($roomId !== null && ! $this->exams->roomBelongsToSchool($roomId, $schoolId)) {
            throw ExamValidationException::roomNotInSchool();
        }

        return $exam;
    }
}
