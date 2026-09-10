<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Results\VoidStudentGradeResult;
use App\Domain\Exams\Events\StudentGradeVoided;
use App\Domain\Exams\Exceptions\GradeNotFoundException;
use App\Domain\Exams\Exceptions\InvalidGradeCorrectionException;
use App\Domain\Exams\Repositories\StudentGradeRepositoryInterface;
use App\Domain\Exams\Services\StudentGradeRules;
use App\Domain\Exams\ValueObjects\GradeStatus;

final class VoidStudentGradeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'VoidStudentGrade';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentGradeRepositoryInterface $grades,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): VoidStudentGradeResult
    {
        assert($command instanceof VoidStudentGradeCommand);

        if (trim($command->reason) === '') {
            throw InvalidGradeCorrectionException::forReason('Void reason is required.');
        }

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return VoidStudentGradeResult::fromIdempotency(
                    (int) $cached['grade_id'],
                    (int) $cached['academic_year_id'],
                );
            }
        }

        $this->unitOfWork->transaction(function () use ($command): void {
            $grade = $this->grades->lockByIdentity(
                $command->gradeId,
                $command->academicYearId,
                $command->schoolId,
            );

            if ($grade === null) {
                throw GradeNotFoundException::forIdentity($command->gradeId, $command->academicYearId);
            }

            StudentGradeRules::assertCanVoid($grade->isCurrent, GradeStatus::from($grade->status));

            $this->grades->markVoided($grade->id, $grade->academicYearId);

            $this->outbox->stage(new StudentGradeVoided(
                gradeId: $grade->id,
                academicYearId: $grade->academicYearId,
                schoolId: $grade->schoolId,
                examEnrollmentId: $grade->examEnrollmentId,
                studentId: $grade->studentId,
                voidedBy: $command->voidedBy,
                reason: $command->reason,
                occurredAt: new \DateTimeImmutable,
            ));
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'grade_id' => $command->gradeId,
                'academic_year_id' => $command->academicYearId,
            ]);
        }

        return VoidStudentGradeResult::success($command->gradeId, $command->academicYearId);
    }
}
