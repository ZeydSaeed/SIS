<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Results\FinalizeStudentGradeResult;
use App\Domain\Exams\Events\StudentGradeFinalized;
use App\Domain\Exams\Exceptions\GradeNotFoundException;
use App\Domain\Exams\Exceptions\InvalidGradeCorrectionException;
use App\Domain\Exams\Repositories\StudentGradeRepositoryInterface;
use App\Domain\Exams\Services\StudentGradeRules;
use App\Domain\Exams\Support\GradeIdempotencyGuard;
use App\Domain\Exams\ValueObjects\GradeStatus;

final class FinalizeStudentGradeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'FinalizeStudentGrade';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentGradeRepositoryInterface $grades,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): FinalizeStudentGradeResult
    {
        assert($command instanceof FinalizeStudentGradeCommand);

        $idempotencyKey = GradeIdempotencyGuard::requireKey($command->idempotencyKey);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return FinalizeStudentGradeResult::fromIdempotency(
                (int) $cached['grade_id'],
                (int) $cached['academic_year_id'],
            );
        }

        $this->unitOfWork->transaction(function () use ($command, $idempotencyKey): void {
            $grade = $this->grades->lockByIdentity(
                $command->gradeId,
                $command->academicYearId,
                $command->schoolId,
            );

            if ($grade === null) {
                throw GradeNotFoundException::forIdentity($command->gradeId, $command->academicYearId);
            }

            if (! $grade->isCurrent) {
                throw InvalidGradeCorrectionException::forReason('Only the current grade can be finalized.');
            }

            StudentGradeRules::assertCanFinalize(GradeStatus::from($grade->status));

            $finalizedAt = now()->toIso8601String();
            $this->grades->markFinalized($grade->id, $grade->academicYearId, $finalizedAt);

            $this->outbox->stage(new StudentGradeFinalized(
                gradeId: $grade->id,
                academicYearId: $grade->academicYearId,
                schoolId: $grade->schoolId,
                examEnrollmentId: $grade->examEnrollmentId,
                studentId: $grade->studentId,
                finalizedBy: $command->finalizedBy,
                occurredAt: new \DateTimeImmutable,
            ));

            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'grade_id' => $command->gradeId,
                'academic_year_id' => $command->academicYearId,
            ]);
        });

        return FinalizeStudentGradeResult::success($command->gradeId, $command->academicYearId);
    }
}
