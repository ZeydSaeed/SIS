<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\DeactivateEnrollmentSubjectResult;
use App\Domain\Enrollment\Events\EnrollmentSubjectDeactivated;
use App\Domain\Enrollment\Repositories\EnrollmentSubjectRepositoryInterface;
use App\Domain\Enrollment\Support\EnrollmentIdempotencyGuard;

final class DeactivateEnrollmentSubjectHandler implements CommandHandler
{
    private const COMMAND_NAME = 'DeactivateEnrollmentSubject';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentSubjectRepositoryInterface $enrollmentSubjects,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DeactivateEnrollmentSubjectResult
    {
        assert($command instanceof DeactivateEnrollmentSubjectCommand);
        $key = EnrollmentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return DeactivateEnrollmentSubjectResult::fromIdempotency((int) $cached['link_id']);
        }

        $link = $this->enrollmentSubjects->findActive($command->schoolId, $command->linkId);
        if ($link === null) {
            return DeactivateEnrollmentSubjectResult::failure(['enrollment.subject_link_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $link): void {
            $this->enrollmentSubjects->deactivate($command->schoolId, $command->linkId);
            $this->outbox->stage(new EnrollmentSubjectDeactivated(
                $command->linkId,
                $link->enrollmentId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['link_id' => $command->linkId]);
        });

        return DeactivateEnrollmentSubjectResult::success($command->linkId);
    }
}
