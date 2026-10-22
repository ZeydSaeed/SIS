<?php

namespace App\Application\Exams\Support;

use App\Application\Exams\Commands\SeatClassInExamSessionCommand;
use App\Application\Exams\Contracts\ExamSeatingReadRepositoryInterface;
use App\Domain\Exams\Exceptions\ExamSeatingException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

/**
 * The target of a seating request: the session must belong to the exam and school and not be cancelled; the class must
 * be of the exam's school-year and the section of that class. Any refusal here seats nobody.
 */
final class ExamSeatingTargetResolver
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
        private readonly ExamSeatingReadRepositoryInterface $seating,
    ) {}

    /**
     * @return array{academic_year_id: int, subject_id: int}
     */
    public function resolve(SeatClassInExamSessionCommand $command): array
    {
        $session = $this->exams->findSessionByIdAndSchool($command->examSessionId, $command->schoolId);
        $exam = $this->exams->findByIdAndSchool($command->examId, $command->schoolId);
        if ($session === null || $exam === null || $session->examId !== $exam->id) {
            throw ExamSeatingException::sessionNotFound($command->examSessionId);
        }
        if ($session->status === ExamSessionStatus::Cancelled->value) {
            throw ExamSeatingException::sessionCancelled($command->examSessionId);
        }
        if (! $this->seating->classInSchoolYear($command->classId, $command->schoolId, $exam->academicYearId)) {
            throw ExamSeatingException::classNotInExamYear($command->classId);
        }
        if ($command->sectionId !== null && ! $this->seating->sectionInClass($command->sectionId, $command->classId)) {
            throw ExamSeatingException::sectionNotInClass($command->sectionId);
        }

        return ['academic_year_id' => $exam->academicYearId, 'subject_id' => $session->subjectId];
    }
}
