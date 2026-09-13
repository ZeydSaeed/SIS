<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\AssignEnrollmentSubjectResult;
use App\Domain\Enrollment\Events\EnrollmentSubjectAssigned;
use App\Domain\Enrollment\Repositories\EnrollmentSubjectRepositoryInterface;
use App\Domain\Enrollment\Services\AssignEnrollmentSubjectGuard;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;

final class AssignEnrollmentSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AssignEnrollmentSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentSubjectRepositoryInterface $enrollmentSubjects,
        private readonly AssignEnrollmentSubjectGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AssignEnrollmentSubjectResult
    {
        assert($command instanceof AssignEnrollmentSubjectCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AssignEnrollmentSubjectResult::fromIdempotency((int) $cached['link_id']);
        }

        $error = $this->guard->rejectionCode(
            $command->schoolId,
            $command->enrollmentId,
            $command->subjectId,
        );
        if ($error !== null) {
            return AssignEnrollmentSubjectResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->enrollmentSubjects->assignOrReactivate(
                $command->schoolId,
                $command->enrollmentId,
                $command->subjectId,
                $command->isElective,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new EnrollmentSubjectAssigned(
                $id,
                $command->enrollmentId,
                $command->subjectId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['link_id' => $id]);

            return $id;
        });

        return AssignEnrollmentSubjectResult::success($id);
    }
}
