<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Results\EnterStudentGradeResult;
use App\Database\StudentGradesPartitionManager;
use App\Domain\Exams\Data\CreateStudentGradeData;
use App\Domain\Exams\Events\StudentGradeEntered;
use App\Domain\Exams\Exceptions\ExamEnrollmentNotFoundException;
use App\Domain\Exams\Exceptions\MissingGradePartitionException;
use App\Domain\Exams\Repositories\StudentGradeRepositoryInterface;
use App\Domain\Exams\Services\StudentGradeWriteGuard;
use App\Domain\Exams\ValueObjects\GradeStatus;

final class EnterStudentGradeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'EnterStudentGrade';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentGradeRepositoryInterface $grades,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): EnterStudentGradeResult
    {
        assert($command instanceof EnterStudentGradeCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return EnterStudentGradeResult::fromIdempotency(
                    (int) $cached['grade_id'],
                    (int) $cached['academic_year_id'],
                );
            }
        }

        $context = $this->grades->findExamEnrollmentContext($command->examEnrollmentId, $command->schoolId);
        if ($context === null) {
            throw ExamEnrollmentNotFoundException::forId($command->examEnrollmentId);
        }

        if (! StudentGradesPartitionManager::partitionExists($context->academicYearId)) {
            throw MissingGradePartitionException::forAcademicYear($context->academicYearId);
        }

        $current = $this->grades->findCurrentForExamEnrollment(
            $context->examEnrollmentId,
            $context->academicYearId,
            $context->schoolId,
        );

        StudentGradeWriteGuard::assertCanEnter($context, $current, $command->isAbsent, $command->score);

        $enteredAt = now()->toIso8601String();
        $gradeId = $this->unitOfWork->transaction(function () use ($command, $context, $enteredAt): int {
            $id = $this->grades->insert(new CreateStudentGradeData(
                academicYearId: $context->academicYearId,
                schoolId: $context->schoolId,
                examEnrollmentId: $context->examEnrollmentId,
                examSessionId: $context->examSessionId,
                enrollmentId: $context->enrollmentId,
                studentId: $context->studentId,
                subjectId: $context->subjectId,
                score: $command->isAbsent ? null : $command->score,
                maxScore: $context->maxScore,
                isAbsent: $command->isAbsent,
                status: GradeStatus::Entered->value,
                isCurrent: true,
                correctionOfGradeId: null,
                enteredBy: $command->enteredBy,
                enteredAt: $enteredAt,
            ));

            $this->outbox->stage(new StudentGradeEntered(
                gradeId: $id,
                academicYearId: $context->academicYearId,
                schoolId: $context->schoolId,
                examEnrollmentId: $context->examEnrollmentId,
                studentId: $context->studentId,
                score: $command->isAbsent ? null : $command->score,
                maxScore: $context->maxScore,
                isAbsent: $command->isAbsent,
                status: GradeStatus::Entered->value,
                enteredBy: $command->enteredBy,
                occurredAt: new \DateTimeImmutable,
            ));

            return $id;
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'grade_id' => $gradeId,
                'academic_year_id' => $context->academicYearId,
            ]);
        }

        return EnterStudentGradeResult::success($gradeId, $context->academicYearId);
    }
}
