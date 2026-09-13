<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\ReactivateEnrollmentSubjectResult;
use App\Domain\Enrollment\Events\EnrollmentSubjectReactivated;
use App\Domain\Enrollment\Repositories\EnrollmentSubjectRepositoryInterface;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;

final class ReactivateEnrollmentSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReactivateEnrollmentSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentSubjectRepositoryInterface $enrollmentSubjects,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReactivateEnrollmentSubjectResult
    {
        assert($command instanceof ReactivateEnrollmentSubjectCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReactivateEnrollmentSubjectResult::fromIdempotency((int) $cached['link_id']);
        }

        $link = $this->enrollmentSubjects->findInactive($command->schoolId, $command->linkId);
        if ($link === null) {
            return ReactivateEnrollmentSubjectResult::failure(['enrollment.subject_link_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $link): void {
            $this->enrollmentSubjects->reactivate($command->schoolId, $command->linkId);
            $this->outbox->stage(new EnrollmentSubjectReactivated(
                $command->linkId,
                $link->enrollmentId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['link_id' => $command->linkId]);
        });

        return ReactivateEnrollmentSubjectResult::success($command->linkId);
    }
}
