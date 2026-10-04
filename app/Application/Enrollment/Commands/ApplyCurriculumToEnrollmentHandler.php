<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\ApplyCurriculumToEnrollmentResult;
use App\Domain\Enrollment\Contracts\EnrollmentCurriculumPort;
use App\Domain\Enrollment\Events\EnrollmentSubjectAssigned;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentSubjectRepositoryInterface;
use App\Domain\Enrollment\Services\AssignEnrollmentSubjectGuard;

final class ApplyCurriculumToEnrollmentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ApplyCurriculumToEnrollment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly EnrollmentSubjectRepositoryInterface $enrollmentSubjects,
        private readonly EnrollmentCurriculumPort $curriculum,
        private readonly AssignEnrollmentSubjectGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ApplyCurriculumToEnrollmentResult
    {
        assert($command instanceof ApplyCurriculumToEnrollmentCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return ApplyCurriculumToEnrollmentResult::success((int) $cached['assigned_count']);
            }
        }

        $enrollment = $this->enrollments->findByIdAndSchool($command->enrollmentId, $command->schoolId);
        if ($enrollment === null || ! $enrollment->isActive()) {
            return ApplyCurriculumToEnrollmentResult::failure(['enrollment.not_found']);
        }

        $alreadyLinked = array_flip($this->enrollmentSubjects->linkedSubjectIds(
            $command->schoolId,
            $command->enrollmentId,
        ));
        $pending = array_values(array_filter(
            $this->curriculum->requiredSubjectIdsForPlacement(
                $command->schoolId,
                $enrollment->academicYearId,
                $enrollment->classId,
                $enrollment->departmentId,
            ),
            static fn (int $subjectId): bool => ! isset($alreadyLinked[$subjectId]),
        ));

        $assigned = 0;
        $skipped = [];
        foreach ($pending as $subjectId) {
            $rejection = $this->guard->rejectionCode($command->schoolId, $command->enrollmentId, $subjectId);
            if ($rejection !== null) {
                $skipped[] = $rejection.':'.$subjectId;

                continue;
            }

            $this->assign($command, $subjectId);
            $assigned++;
        }

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, ['assigned_count' => $assigned]);
        }

        return ApplyCurriculumToEnrollmentResult::success($assigned, $skipped);
    }

    private function assign(ApplyCurriculumToEnrollmentCommand $command, int $subjectId): void
    {
        $this->unitOfWork->transaction(function () use ($command, $subjectId): void {
            $linkId = $this->enrollmentSubjects->assignOrReactivate(
                $command->schoolId,
                $command->enrollmentId,
                $subjectId,
                false,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new EnrollmentSubjectAssigned(
                $linkId,
                $command->enrollmentId,
                $subjectId,
                new \DateTimeImmutable,
            ));
        });
    }
}
