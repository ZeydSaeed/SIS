<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Results\CorrectStudentGradeResult;
use App\Database\StudentGradesPartitionManager;
use App\Domain\Exams\Data\CreateStudentGradeData;
use App\Domain\Exams\Events\StudentGradeCorrected;
use App\Domain\Exams\Exceptions\GradeNotFoundException;
use App\Domain\Exams\Exceptions\InvalidGradeCorrectionException;
use App\Domain\Exams\Exceptions\MissingGradePartitionException;
use App\Domain\Exams\Repositories\StudentGradeRepositoryInterface;
use App\Domain\Exams\Services\StudentGradeWriteGuard;
use App\Domain\Exams\ValueObjects\GradeStatus;

final class CorrectStudentGradeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CorrectStudentGrade';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentGradeRepositoryInterface $grades,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CorrectStudentGradeResult
    {
        assert($command instanceof CorrectStudentGradeCommand);

        if (trim($command->reason) === '') {
            throw InvalidGradeCorrectionException::forReason('Correction reason is required.');
        }

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return CorrectStudentGradeResult::fromIdempotency(
                    (int) $cached['previous_grade_id'],
                    (int) $cached['new_grade_id'],
                    (int) $cached['academic_year_id'],
                );
            }
        }

        if (! StudentGradesPartitionManager::partitionExists($command->academicYearId)) {
            throw MissingGradePartitionException::forAcademicYear($command->academicYearId);
        }

        [$previousId, $newId] = $this->unitOfWork->transaction(function () use ($command): array {
            $current = $this->grades->lockByIdentity(
                $command->gradeId,
                $command->academicYearId,
                $command->schoolId,
            );

            if ($current === null) {
                throw GradeNotFoundException::forIdentity($command->gradeId, $command->academicYearId);
            }

            $chain = $this->grades->correctionChainIds($current->id, $current->academicYearId);
            StudentGradeWriteGuard::assertCanCorrect(
                $current,
                $command->isAbsent,
                $command->score,
                $chain,
            );

            $this->grades->markVoided($current->id, $current->academicYearId);

            $enteredAt = now()->toIso8601String();
            $newId = $this->grades->insert(new CreateStudentGradeData(
                academicYearId: $current->academicYearId,
                schoolId: $current->schoolId,
                examEnrollmentId: $current->examEnrollmentId,
                examSessionId: $current->examSessionId,
                enrollmentId: $current->enrollmentId,
                studentId: $current->studentId,
                subjectId: $current->subjectId,
                score: $command->isAbsent ? null : $command->score,
                maxScore: $current->maxScore,
                isAbsent: $command->isAbsent,
                status: GradeStatus::Entered->value,
                isCurrent: true,
                correctionOfGradeId: $current->id,
                enteredBy: $command->correctedBy,
                enteredAt: $enteredAt,
            ));

            $this->outbox->stage(new StudentGradeCorrected(
                previousGradeId: $current->id,
                newGradeId: $newId,
                academicYearId: $current->academicYearId,
                schoolId: $current->schoolId,
                examEnrollmentId: $current->examEnrollmentId,
                studentId: $current->studentId,
                score: $command->isAbsent ? null : $command->score,
                maxScore: $current->maxScore,
                isAbsent: $command->isAbsent,
                correctedBy: $command->correctedBy,
                reason: $command->reason,
                occurredAt: new \DateTimeImmutable,
            ));

            return [$current->id, $newId];
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'previous_grade_id' => $previousId,
                'new_grade_id' => $newId,
                'academic_year_id' => $command->academicYearId,
            ]);
        }

        return CorrectStudentGradeResult::success($previousId, $newId, $command->academicYearId);
    }
}
